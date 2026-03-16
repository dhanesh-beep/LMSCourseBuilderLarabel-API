<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ResponseController;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use DOMDocument;
use DOMXPath;

/**
 * HSE_data_upsert
 * ─────────────────────────────────────────────────────────────────────────────
 * Pipeline:
 *   1. runFullPipeline() – Full pipeline: scrape → chunk → save JSON → embed → upsert Pinecone
 * ─────────────────────────────────────────────────────────────────────────────
 */
class HSE_data_upsert extends ResponseController
{
    /* ── Pinecone / Ollama config ── */
    private string $apiKey;
    private string $indexName;
    private string $region;
    private string $cloud       = 'aws';
    private int    $dimension   = 1024;
    private string $metric      = 'cosine';
    private string $ollamaHost;
    private string $ollamaModel;

    /* ── Chunking config ── */
    private int $chunkSize    = 400;   // words per chunk
    private int $chunkOverlap = 50;    // word overlap between chunks

    /* ── Source metadata ── */
    private string $lawUrl    = 'https://www.arbeidstilsynet.no/regelverk/lover/arbeidsmiljoloven--aml/';
    private string $lawName   = 'Arbeidsmiljøloven (AML)';
    private string $courseTitle = 'Arbeidsmiljøloven (AML) – Norwegian Work Environment Act';
    private string $lastUpdatedDate = '2025-06-20'; // fallback
    private string $companyCategory = 'all';
    private bool $isHseAccess = true;
    /* ── JSON cache path (Laravel storage) ── */
    private string $jsonPath  = 'hse/aml_chunks.json';

    public function __construct()
    {
        $this->apiKey     = env('PINECONE_API_KEY');
        $this->indexName  = 'hse-dataset-no';
        $this->region     = env('PINECONE_ENV', 'us-east-1');
        $this->ollamaHost = rtrim(env('OLLAMA_HOST', 'http://localhost:11434'), '/');
        $this->ollamaModel = env('OLLAMA_MODEL', 'mxbai-embed-large:latest');
    }

    /* ═══════════════════════════════════════════════════════════════════════
     * PUBLIC API ROUTES
     * ═══════════════════════════════════════════════════════════════════════ */

    /**
     * POST /hse/pipeline
     * Full pipeline: scrape → chunk → save JSON → embed → upsert Pinecone
     */
    public function runFullPipeline(): JsonResponse
    {
        ini_set('max_execution_time', 900);
        Log::info('[HSE] Starting full pipeline');

        // Step 1 – Extract & chunk
        $extractResult = $this->extractAndChunk();
        if (isset($extractResult['error'])) {
            return $this->badRequest('Extraction failed: ' . $extractResult['error']);
        }

        $chunks = $extractResult['chunks'];
        Log::info('[HSE] Extracted chunks', ['count' => count($chunks)]);

        // Step 2 – Save JSON
        Storage::put($this->jsonPath, json_encode($chunks, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        Log::info('[HSE] JSON saved', ['path' => $this->jsonPath]);

        // Step 3 – Init index (create if needed)
        $initResult = $this->ensureIndexExists();
        if (isset($initResult['error'])) {
            return $this->badRequest('Index init failed: ' . $initResult['error']);
        }

        // Step 4 – Upsert
        $upsertResult = $this->embedAndUpsert($chunks);
        if (isset($upsertResult['error'])) {
            return $this->badRequest('Upsert failed: ' . $upsertResult['error']);
        }

        return $this->actionSuccess('HSE pipeline completed successfully', [
            'chunks_extracted' => count($chunks),
            'chunks_upserted'  => $upsertResult['upserted'],
            'json_path'        => $this->jsonPath,
            'index'            => $this->indexName,
        ]);
    }

    /* ═══════════════════════════════════════════════════════════════════════
     * CORE PRIVATE METHODS
     * ═══════════════════════════════════════════════════════════════════════ */

    /**
     * Fetch, parse, and chunk the AML law page.
     * Returns ['chunks' => [...]] or ['error' => '...']
     */
    private function extractAndChunk(): array
    {
        // ── 1. Fetch HTML ──────────────────────────────────────────────────
        try {
            $response = Http::timeout(30)
                ->withHeaders(['Accept-Charset' => 'utf-8'])
                ->get($this->lawUrl);
        } catch (\Exception $e) {
            return ['error' => 'HTTP fetch failed: ' . $e->getMessage()];
        }

        if (!$response->successful()) {
            return ['error' => 'Failed to fetch webpage (HTTP ' . $response->status() . ')'];
        }

        $html = $response->body();

        // ── 2. Parse DOM ───────────────────────────────────────────────────
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $xpath    = new DOMXPath($dom);
        $pageTitle = $this->extractPageTitle($xpath);
        $lastUpdatedDate = $this->extractLastUpdatedDate($xpath) ?: $this->lastUpdatedDate;

        // ── 3. Walk chapters & sections ────────────────────────────────────
        $chunks     = [];
        $chapterNum = '';
        $chapterText = '';

        // Grab all h2 and h3 nodes in document order
        $headings = $xpath->query('//h2 | //h3');

        // Build a flat list of {type, text, node} for easier traversal
        $headingList = [];
        foreach ($headings as $node) {
            $text = $this->cleanText($node->textContent);
            $headingList[] = [
                'tag'  => strtolower($node->tagName),
                'text' => $text,
                'node' => $node,
            ];
        }

        $totalHeadings = count($headingList);

        for ($i = 0; $i < $totalHeadings; $i++) {
            $h = $headingList[$i];

            // ── Detect chapter (h2 containing "Kapittel") ─────────────────
            if ($h['tag'] === 'h2' && stripos($h['text'], 'Kapittel') !== false) {
                preg_match('/Kapittel\s+(\d+\s*[A-Za-z]?)/ui', $h['text'], $m);
                $chapterNum  = isset($m[1]) ? trim($m[1]) : '';
                $chapterText = $h['text'];
                continue;
            }

            // ── Detect section (h3 starting with §) ───────────────────────
            if ($h['tag'] === 'h3' && str_starts_with($h['text'], '§')) {
                $sectionText = $h['text'];
                preg_match('/§\s*(\d+(?:-\d+)?(?:\s*[a-zA-Z])?)/u', $sectionText, $sm);
                $sectionNum = isset($sm[1]) ? trim($sm[1]) : '';

                // Collect body text until next h2/h3
                $bodyText = $this->collectBodyText($h['node']);

                // Full text = section heading + body
                $fullText = $sectionText;
                if (!empty($bodyText)) {
                    $fullText .= "\n" . $bodyText;
                }

                // Build base paragraph_id
                $baseParaId = 'aml-'
                    . $this->slugify($chapterNum ?: 'preamble')
                    . '-'
                    . $this->slugify($sectionNum ?: 'intro');

                // ── Chunk the full text ────────────────────────────────────
                $textChunks = $this->chunkText($fullText, $this->chunkSize, $this->chunkOverlap);

                foreach ($textChunks as $idx => $chunk) {
                    $paraId = count($textChunks) > 1
                        ? "{$baseParaId}-chunk{$idx}"
                        : $baseParaId;

                    $chunks[] = [
                        'course_title'      => $this->courseTitle,
                        'lastUpdatedDate'   => $lastUpdatedDate,
                        'law_name'          => $this->lawName,
                        'company_category'  => $this->companyCategory,
                        'url'               => $this->lawUrl,
                        'page_title'        => $pageTitle,
                        'chapter'           => $chapterText,
                        'chapter_num'       => $chapterNum,
                        'section'           => $sectionText,
                        'paragraph_id'      => $paraId,
                        'text'              => $chunk,
                        'token_estimate'    => $this->estimateTokens($chunk),
                        'is_hse_access'     => $this->isHseAccess,
                    ];
                }
            }
        }

        // ── 4. Fallback – if DOM parsing found nothing, scrape raw <p> ─────
        if (empty($chunks)) {
            Log::warning('[HSE] No chapters/sections found via DOM – falling back to raw paragraphs');
            $chunks = $this->fallbackExtract($xpath, $pageTitle);
        }

        return ['chunks' => $chunks];
    }

    /**
     * Embed each chunk with Ollama and upsert vectors to Pinecone in batches.
     */
    private function embedAndUpsert(array $chunks): array
    {
        $indexHost = $this->getIndexHost();
        if (!$indexHost) {
            return ['error' => 'Could not resolve Pinecone index host. Check index name and API key.'];
        }

        $batchSize  = 50;   // Pinecone recommends ≤ 100 vectors per upsert
        $totalUpserted = 0;
        $errors     = [];

        $batches = array_chunk($chunks, $batchSize);

        foreach ($batches as $batchIdx => $batch) {
            $vectors = [];

            foreach ($batch as $rec) {
                $embedding = $this->getEmbedding($rec['text']);

                if ($embedding === null) {
                    Log::warning('[HSE] Embedding failed', ['id' => $rec['paragraph_id']]);
                    $errors[] = $rec['paragraph_id'];
                    continue;
                }

                // Pinecone metadata values must be scalar
                $vectors[] = [
                    'id'       => $rec['paragraph_id'],
                    'values'   => $embedding,
                    'metadata' => [
                        'course_title'   => (string) ($rec['course_title']   ?? ''),
                        'lastUpdatedDate'   => (string) ($rec['lastUpdatedDate']   ?? ''),
                        'company_category'  => (string) ($rec['company_category']  ?? 'NA'), 
                        'law_name'       => (string) ($rec['law_name']       ?? ''),
                        'url'            => (string) ($rec['url']            ?? ''),
                        'page_title'     => (string) ($rec['page_title']     ?? ''),
                        'chapter'        => (string) ($rec['chapter']        ?? ''),
                        'chapter_num'    => (string) ($rec['chapter_num']    ?? ''),
                        'section'        => (string) ($rec['section']        ?? ''),
                        'paragraph_id'   => (string) ($rec['paragraph_id']  ?? ''),
                        'text'           => (string) ($rec['text']           ?? ''),
                        'token_estimate' => (int)    ($rec['token_estimate'] ?? 0),
                        'is_hse_access'  => (bool)   ($rec['is_hse_access']  ?? true),
                    ],
                ];
            }

            if (empty($vectors)) {
                continue;
            }

            // Upsert batch to Pinecone
            $res = Http::withHeaders([
                'Api-Key'      => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("https://{$indexHost}/vectors/upsert", [
                'vectors'   => $vectors,
                'namespace' => 'aml',
            ]);

            if ($res->successful()) {
                $totalUpserted += count($vectors);
                Log::info("[HSE] Batch {$batchIdx} upserted", ['count' => count($vectors)]);
            } else {
                $msg = "[HSE] Batch {$batchIdx} upsert FAILED: " . $res->body();
                Log::error($msg);
                $errors[] = $msg;
            }

            // Small delay to avoid rate limits
            usleep(200_000); // 200ms
        }

        return [
            'upserted'       => $totalUpserted,
            'failed_ids'     => $errors,
            'total_chunks'   => count($chunks),
        ];
    }

    /* ═══════════════════════════════════════════════════════════════════════
     * PINECONE HELPERS
     * ═══════════════════════════════════════════════════════════════════════ */

    /**
     * Create the Pinecone serverless index if it does not exist.
     */
    private function ensureIndexExists(): array
    {
        // Check existing indexes
        $listRes = Http::withHeaders([
            'Api-Key' => $this->apiKey,
        ])->get('https://api.pinecone.io/indexes');

        if (!$listRes->successful()) {
            return ['error' => 'Failed to list Pinecone indexes: ' . $listRes->body()];
        }

        $existingIndexes = collect($listRes->json('indexes', []))
            ->pluck('name')
            ->toArray();

        if (in_array($this->indexName, $existingIndexes, true)) {
            Log::info('[HSE] Pinecone index already exists', ['index' => $this->indexName]);
            return ['message' => 'Index already exists', 'index' => $this->indexName, 'created' => false];
        }

        // Create serverless index
        $createRes = Http::withHeaders([
            'Api-Key'      => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.pinecone.io/indexes', [
            'name'      => $this->indexName,
            'dimension' => $this->dimension,
            'metric'    => $this->metric,
            'spec'      => [
                'serverless' => [
                    'cloud'  => $this->cloud,
                    'region' => $this->region,
                ],
            ],
        ]);

        if (!$createRes->successful()) {
            return ['error' => 'Failed to create Pinecone index: ' . $createRes->body()];
        }

        // Wait for index to become ready (max 60 s)
        $ready   = false;
        $attempt = 0;
        while (!$ready && $attempt < 12) {
            sleep(5);
            $statusRes = Http::withHeaders(['Api-Key' => $this->apiKey])
                ->get("https://api.pinecone.io/indexes/{$this->indexName}");
            $state = $statusRes->json('status.state', '');
            if ($state === 'Ready') {
                $ready = true;
            }
            $attempt++;
            Log::info("[HSE] Waiting for index ready... state={$state} attempt={$attempt}");
        }

        if (!$ready) {
            Log::warning('[HSE] Index created but not yet ready after 60s – proceeding anyway');
        }

        Log::info('[HSE] Pinecone index created', ['index' => $this->indexName]);
        return ['message' => 'Index created successfully', 'index' => $this->indexName, 'created' => true];
    }

    /**
     * Retrieve the index host URL from Pinecone describe endpoint.
     */
    private function getIndexHost(): ?string
    {
        $res = Http::withHeaders(['Api-Key' => $this->apiKey])
            ->get("https://api.pinecone.io/indexes/{$this->indexName}");

        if (!$res->successful()) {
            Log::error('[HSE] Failed to describe Pinecone index: ' . $res->body());
            return null;
        }

        return $res->json('host');
    }

    /* ═══════════════════════════════════════════════════════════════════════
     * OLLAMA EMBEDDING
     * ═══════════════════════════════════════════════════════════════════════ */

    /**
     * Get a 1024-dim embedding vector from Ollama for the given text.
     * Returns null on failure.
     */
    private function getEmbedding(string $text): ?array
    {
        try {
            $res = Http::timeout(60)
                ->post("{$this->ollamaHost}/api/embed", [
                    'model' => $this->ollamaModel,
                    'input' => $text,
                ]);

            if (!$res->successful()) {
                Log::warning('[HSE] Ollama embed failed', ['status' => $res->status(), 'body' => $res->body()]);
                return null;
            }

            // Ollama /api/embed returns { "embeddings": [[...]] }
            $embeddings = $res->json('embeddings');

            if (empty($embeddings) || !is_array($embeddings[0])) {
                Log::warning('[HSE] Ollama returned empty embeddings');
                return null;
            }

            $vector = $embeddings[0];

            // Validate dimension
            if (count($vector) !== $this->dimension) {
                Log::warning('[HSE] Unexpected embedding dimension', [
                    'expected' => $this->dimension,
                    'got'      => count($vector),
                ]);
            }

            return $vector;
        } catch (\Exception $e) {
            Log::error('[HSE] Ollama exception: ' . $e->getMessage());
            return null;
        }
    }

    /* ═══════════════════════════════════════════════════════════════════════
     * DOM / TEXT HELPERS
     * ═══════════════════════════════════════════════════════════════════════ */

    /**
     * Walk the next siblings of $node and collect text from <p>, <div>, <li>,
     * <span> until we hit another heading (h2/h3).
     */
    private function collectBodyText(\DOMNode $node): string
    {
        $parts       = [];
        $sibling     = $node->nextSibling;

        while ($sibling) {
            if ($sibling->nodeType === XML_ELEMENT_NODE) {
                $tag = strtolower($sibling->tagName);

                // Stop at next heading
                if (in_array($tag, ['h1', 'h2', 'h3', 'h4'], true)) {
                    break;
                }

                if (in_array($tag, ['p', 'div', 'li', 'span', 'ol', 'ul', 'blockquote'], true)) {
                    $t = $this->cleanText($sibling->textContent);
                    if ($t !== '') {
                        $parts[] = $t;
                    }
                }
            }
            $sibling = $sibling->nextSibling;
        }

        return implode("\n", $parts);
    }

    /**
     * Extract <title> or <h1> text for page_title metadata.
     */
    private function extractPageTitle(DOMXPath $xpath): string
    {
        $titleNode = $xpath->query('//title')->item(0);
        if ($titleNode) {
            return $this->cleanText($titleNode->textContent);
        }
        $h1Node = $xpath->query('//h1')->item(0);
        if ($h1Node) {
            return $this->cleanText($h1Node->textContent);
        }
        return $this->lawName;
    }

    /**
     * Extract last updated date from HTML using 'Siste endret' or 'Last modified'.
     */
    private function extractLastUpdatedDate(DOMXPath $xpath): ?string
    {
        // Query for text containing 'Siste endret' or 'Last modified'
        $nodes = $xpath->query("//*[contains(text(), 'Siste endret') or contains(text(), 'Last modified')]");
        foreach ($nodes as $node) {
            $text = $this->cleanText($node->textContent);
            // Match date patterns like YYYY-MM-DD or DD.MM.YYYY
            if (preg_match('/(\d{4}-\d{2}-\d{2}|\d{2}\.\d{2}\.\d{4})/', $text, $matches)) {
                $date = $matches[1];
                // Normalize to YYYY-MM-DD
                if (preg_match('/(\d{2})\.(\d{2})\.(\d{4})/', $date, $m)) {
                    $date = "{$m[3]}-{$m[2]}-{$m[1]}";
                }
                return $date;
            }
        }
        return null;
    }

    /**
     * Last-resort: if structured h2/h3 walk finds nothing, pull raw <p> text.
     */
    private function fallbackExtract(DOMXPath $xpath, string $pageTitle): array
    {
        $chunks  = [];
        $paras   = $xpath->query('//article//p | //main//p | //div[@class="content"]//p');

        $rawText = '';
        foreach ($paras as $p) {
            $t = $this->cleanText($p->textContent);
            if (strlen($t) > 40) {
                $rawText .= $t . "\n";
            }
        }

        $textChunks = $this->chunkText($rawText, $this->chunkSize, $this->chunkOverlap);
        foreach ($textChunks as $idx => $chunk) {
            $chunks[] = [
                'course_title'     => $this->courseTitle,
                'lastUpdatedDate'  => $lastUpdatedDate,
                'law_name'         => $this->lawName,
                'company_category' => $this->companyCategory,
                'url'              => $this->lawUrl,
                'page_title'       => $pageTitle,
                'chapter'          => '',
                'chapter_num'      => '',
                'section'          => '',
                'paragraph_id'     => "aml-fallback-chunk{$idx}",
                'text'             => $chunk,
                'token_estimate'   => $this->estimateTokens($chunk),
                'is_hse_access'    => $this->isHseAccess,
            ];
        }

        return $chunks;
    }

    /**
     * Split text into overlapping word-based chunks.
     *
     * @return string[]
     */
    private function chunkText(string $text, int $chunkSize, int $overlap): array
    {
        $words  = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        $total  = count($words);
        $chunks = [];
        $step   = max(1, $chunkSize - $overlap);

        for ($i = 0; $i < $total; $i += $step) {
            $slice = array_slice($words, $i, $chunkSize);
            if (empty($slice)) {
                break;
            }
            $chunks[] = implode(' ', $slice);
            if ($i + $chunkSize >= $total) {
                break;
            }
        }

        return $chunks ?: [''];
    }

    /**
     * Rough token estimate: word count × 1.3 (subword overhead).
     */
    private function estimateTokens(string $text): int
    {
        $wordCount = count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY));
        return (int) ceil($wordCount * 1.3);
    }

    /**
     * Normalize whitespace and decode HTML entities.
     */
    private function cleanText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }

    /**
     * Convert a string to a URL-friendly slug for use in paragraph IDs.
     */
    private function slugify(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = preg_replace('/\s+/', '-', $text);
        $text = preg_replace('/[^a-z0-9\-]/u', '', $text);
        return $text ?: 'x';
    }
}