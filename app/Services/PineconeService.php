<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PineconeService
{
    protected $apiKey;
    protected $indexName;
    protected $region;
    protected $indexHost;
    protected $ollamaHost;
    protected $ollamaModel;

    public function __construct()
    {
        $this->apiKey = env('PINECONE_API_KEY');
        $this->indexName = 'lms-course-knowledgebase-v2'; // Use the existing index that was confirmed
        $this->region = env('PINECONE_ENV', 'us-east-1');
        $this->indexHost = "https://{$this->indexName}.svc.{$this->region}.pinecone.io";
        $this->ollamaHost = env('OLLAMA_HOST');
        $this->ollamaModel = env('OLLAMA_MODEL');
    }

    /**
     * NEW: Convert text to Vector using Ollama
     */
    public function generateEmbedding(string $text): array
    {
        $response = Http::timeout(60)
            ->connectTimeout(15)
            ->retry(2, 1000)
            ->post("{$this->ollamaHost}/api/embeddings", [
                'model' => $this->ollamaModel,
                'prompt' => $text,
            ]);

        return $response->json()['embedding'];
    }

    /**
     * Chunk text into smaller pieces
     */
    public function chunkText(string $text, int $chunkSize = 1000, int $overlap = 200): array
    {
        $chunks = [];
        $start = 0;

        while ($start < strlen($text)) {
            $end = $start + $chunkSize;
            $chunks[] = substr($text, $start, $chunkSize);
            $start = $end - $overlap;
        }

        return $chunks;
    }

    /**
     * Upsert chunks of text with metadata
     */
    public function upsertChunks(string $courseId, string $courseSlug, string $docVersion, string $text, int $chunkSize = 1000, int $overlap = 200)
    {
        $chunks = $this->chunkText($text, $chunkSize, $overlap);
        $vectors = [];

        foreach ($chunks as $i => $chunk) {
            $embedding = $this->generateEmbedding($chunk);

            $vectors[] = [
                'id' => "{$courseId}_{$courseSlug}_{$docVersion}_chunk_{$i}",
                'values' => $embedding,
                'metadata' => [
                    'course_id' => $courseId,
                    'course_slug' => $courseSlug,
                    'doc_version' => $docVersion,
                    'chunk_index' => $i,
                    'content_type' => 'course_text',
                    'text' => substr($chunk, 0, 100)  // optional (trim if long)
                ]
            ];
        }

        return $this->upsert($vectors);
    }

    /**
     * CRUD: Upsert using Text
     */
    public function upsertText(string $id, string $text, array $metadata = [])
    {
        $vector = $this->generateEmbedding($text);
        
        // Add the original text to metadata so we can read it back during RAG
        $metadata['text'] = $text;

        return $this->upsert([
            [
                'id' => $id,
                'values' => $vector,
                'metadata' => $metadata
            ]
        ]);
    }

    /**
     * RAG: Ask a question and get top 3 relevant text snippets
     */
    public function ask(string $userQuery)
    {
        // 1. Convert user question to vector
        $queryVector = $this->generateEmbedding($userQuery);

        // 2. Search Pinecone
        $results = $this->query($queryVector);

        // 3. Return only the relevant text/metadata
        return collect($results['matches'])->map(function ($match) {
            return [
                'score' => $match['score'],
                'text' => $match['metadata']['text'] ?? 'No text found',
                'metadata' => $match['metadata']
            ];
        });
    }

    /**
     * Upsert vectors to Pinecone
     */
    public function upsert(array $vectors, string $namespace = '')
    {
        $response = Http::timeout(60)
            ->connectTimeout(15)
            ->retry(2, 1000)
            ->withHeaders([
                'Api-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->indexHost}/vectors/upsert", [
                'vectors' => $vectors,
                'namespace' => $namespace,
            ]);

        return $response->json();
    }

    /**
     * Query vectors from Pinecone
     */
    public function query(array $vector, int $topK = 3, bool $includeMetadata = true, string $namespace = '')
    {
        $response = Http::timeout(30)
            ->connectTimeout(15)
            ->retry(2, 1000)
            ->withHeaders([
                'Api-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->indexHost}/query", [
                'vector' => $vector,
                'topK' => $topK,
                'includeMetadata' => $includeMetadata,
                'namespace' => $namespace,
            ]);

        return $response->json();
    }

    /**
     * Delete vectors by filter
     */
    public function deleteByFilter(array $filter, string $namespace = '')
    {
        $response = Http::timeout(30)
            ->connectTimeout(15)
            ->retry(2, 1000)
            ->withHeaders([
                'Api-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->indexHost}/vectors/delete", [
                'filter' => $filter,
                'namespace' => $namespace,
            ]);

        return $response->json();
    }
}