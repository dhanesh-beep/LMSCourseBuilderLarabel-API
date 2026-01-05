<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ResponseController;
use Smalot\PdfParser\Parser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PineconeController extends ResponseController
{
    private string $apiKey;
    private string $indexName;
    private string $region;
    private int $dimension = 1024;
    private string $metric = 'cosine';
    private string $ollamaHost;
    private string $ollamaModel;

    public function __construct()
    {
        $this->apiKey = env('PINECONE_API_KEY');
        // $this->indexName = 'lms-course-knowledgebase-v2';   
        $this->indexName = 'lms-course-knowledgebase-testing'; 
        $this->region = env('PINECONE_ENV'); // us-east-1
        // $this->ollamaHost = rtrim(env('OLLAMA_HOST', 'http://localhost:11434'), '/');
        $this->ollamaHost = env('OLLAMA_HOST');
        // $this->ollamaModel = env('OLLAMA_MODEL', 'mxbai-embed-large:latest');   #Dimention 1024
        $this->ollamaModel = env('OLLAMA_MODEL');   #Dimention 1024
    }

    public function extractCourseText(Request $request): JsonResponse
    {
        try {
            // Get JSON content from file upload or request body
            if ($request->hasFile('file')) {
                $jsonString = file_get_contents($request->file('file')->getRealPath());
            } else {
                $jsonString = $request->getContent();
            }

            if (empty($jsonString)) {
                throw new \Exception('No JSON content provided. Please upload a JSON file or provide JSON in request body.');
            }

            // Decode JSON string to array
            $jsonData = json_decode($jsonString, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON format: ' . json_last_error_msg());
            }

            if (!isset($jsonData['answer']) || !is_array($jsonData['answer'])) {
                throw new \Exception('Invalid JSON structure: "answer" key not found or is not an array');
            }

            // Convert JSON to text using private method
            $courseText = $this->convertCourseJsonToText($jsonData);

            if (empty($courseText)) {
                throw new \Exception('No course content found in JSON file');
            }

            return response()->json([
                'status' => 'success',
                'course_text_file' => $courseText,
                'text_length' => strlen($courseText)
            ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        } catch (\Exception $e) {
            Log::error('Extract Course Text Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    /* =====================================================
     * 1️⃣ INIT / CHECK / CREATE INDEX
     * ===================================================== */
    public function initIndex(): JsonResponse
    {
        try {
            if (empty($this->apiKey)) {
                throw new \Exception('PINECONE_API_KEY not loaded');
            }

            if (empty($this->indexName)) {
                throw new \Exception('PINECONE_INDEX_NAME not loaded');
            }

            $headers = [
                'Api-Key' => $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Pinecone-API-Version' => '2025-10'
            ];

            /* ===============================
            * 1️⃣ LIST EXISTING INDEXES
            * =============================== */
            $listResponse = Http::timeout(30)
                ->connectTimeout(15)
                ->retry(3, 1000)
                ->withHeaders($headers)
                ->get('https://api.pinecone.io/indexes');

            if (!$listResponse->successful()) {
                throw new \Exception(
                    "List indexes failed | {$listResponse->status()} | {$listResponse->body()}"
                );
            }

            $indexes = collect($listResponse->json('indexes'))
                ->pluck('name')
                ->toArray();

            /* ===============================
            * 2️⃣ INDEX EXISTS → RETURN
            * =============================== */
            if (in_array($this->indexName, $indexes)) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Index already exists',
                    'index' => $this->indexName
                ]);
            }

            /* ===============================
            * 3️⃣ CREATE INDEX
            * =============================== */
            $createPayload = [
                'name' => $this->indexName,
                'vector_type' => 'dense',
                'dimension' => $this->dimension, // 1024
                'metric' => $this->metric,        // cosine
                'spec' => [
                    'serverless' => [
                        'cloud' => 'aws',
                        'region' => $this->region // us-east-1
                    ]
                ],
                'deletion_protection' => 'disabled'
            ];

            $createResponse = Http::timeout(60)
                ->connectTimeout(15)
                ->retry(3, 1000)
                ->withHeaders($headers)
                ->post('https://api.pinecone.io/indexes', $createPayload);

            if (!$createResponse->successful()) {
                throw new \Exception(
                    "Create index failed | {$createResponse->status()} | {$createResponse->body()}"
                );
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Index created successfully',
                'index' => $this->indexName
            ]);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Pinecone Connection Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to connect to Pinecone API. Please check your internet connection and firewall settings.',
                'details' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            Log::error('Pinecone Init Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    /* =====================================================
     * 2️⃣ UPSERT VECTORS (UPDATED: Accepts text input)
     * ===================================================== */
    public function upsert(Request $request): JsonResponse
    {
        ini_set('max_execution_time', 3600);
        ini_set('memory_limit', '1024M');

        try {
            $courseId = $request->input('course_id');
            $courseSlug = $request->input('course_slug', '');
            $version = $request->input('doc_version', 'v1');
            $text = $request->input('text'); // Text input to convert to vectors
            $chunkSize = $request->input('chunk_size', 1000);
            $chunkOverlap = $request->input('chunk_overlap', 100);

            // Guard against infinite loop in chunkText
            if ($chunkOverlap >= $chunkSize) {
                throw new \Exception('chunk_overlap must be less than chunk_size');
            }

            // Validate required fields
            if (!$courseId || empty($text)) {
                throw new \Exception('course_id and text are required');
            }

            // Validate Pinecone configuration
            if (empty($this->apiKey)) {
                throw new \Exception('PINECONE_API_KEY is not configured');
            }

            if (empty($this->indexName)) {
                throw new \Exception('PINECONE_INDEX_NAME is not configured');
            }

            if (empty($this->region)) {
                throw new \Exception('PINECONE_ENV (region) is not configured');
            }

            // Verify index exists (non-blocking - if verification fails, we'll still try upsert)
            // This is just a helpful check, not a hard requirement
            // We don't block on this - let the actual upsert call determine if index exists
            try {
                $indexVerified = $this->verifyIndexExists();
                if (!$indexVerified) {
                    Log::warning('Index verification failed, but proceeding with upsert attempt', [
                        'index_name' => $this->indexName,
                        'region' => $this->region,
                        'note' => 'Verification failed but upsert will be attempted. The actual upsert call will provide the real error if index is missing.'
                    ]);
                } else {
                    Log::info('Index verified successfully', [
                        'index_name' => $this->indexName
                    ]);
                }
            } catch (\Exception $e) {
                // Don't fail on verification error - just log it
                Log::warning('Index verification threw exception, but continuing', [
                    'error' => $e->getMessage(),
                    'index_name' => $this->indexName
                ]);
            }

            // Get initial text length
            $textLength = mb_strlen($text, 'UTF-8');
            
            Log::info('Pinecone Upsert: Starting process', [
                'course_id' => $courseId,
                'text_length' => $textLength,
                'chunk_size' => $chunkSize,
                'chunk_overlap' => $chunkOverlap  
            ]);

            // Get the index host from Pinecone
            $indexHost = $this->getIndexHost();
            if (empty($indexHost)) {
                $indexHost = "{$this->indexName}.svc.{$this->region}.pinecone.io";
            }
            $upsertUrl = "https://{$indexHost}/vectors/upsert";

            $batchSize = 40; // Slightly smaller batch size for better safety
            $processedCount = 0;
            $chunkIndex = 0;
            $start = 0;
            
            while ($start < $textLength) {
                $payload = [];
                $batchCount = 0;
                
                // Collect a batch of chunks
                while ($batchCount < $batchSize && $start < $textLength) {
                    $end = min($start + $chunkSize, $textLength);
                    $chunk = mb_substr($text, $start, $end - $start, 'UTF-8');
                    
                    try {
                        $embedding = $this->generateEmbedding($chunk);

                        if (!empty($embedding)) {
                            $payload[] = [
                                'id' => "{$courseId}_{$courseSlug}_{$version}_chunk_{$chunkIndex}",
                                'values' => $embedding,
                                'metadata' => [
                                    'course_id' => $courseId,
                                    // 'course_slug' => $courseSlug,
                                    // 'version' => $version,
                                    'chunk_index' => $chunkIndex,
                                    // 'content_type' => 'course_text',
                                    'text' => $chunk,
                                    'text_length' => strlen($chunk),
                                    // 'created_at' => now()->toIso8601String()
                                ]
                            ];
                        }
                    } catch (\Exception $e) {
                        Log::error('Error generating embedding for chunk', [
                            'chunk_index' => $chunkIndex,
                            'error' => $e->getMessage()
                        ]);
                    }

                    $chunkIndex++;
                    $batchCount++;
                    if ($end >= $textLength) {
                        $start = $textLength; // Force exit of outer loop
                        break;
                    }
                    
                    $start = $end - $chunkOverlap;
                }

                if (!empty($payload)) {
                    // Upsert this batch
                    try {
                        $response = Http::timeout(300)
                            ->withHeaders([
                                'Api-Key' => $this->apiKey,
                                'Content-Type' => 'application/json',
                                'X-Pinecone-Api-Version' => '2025-10'
                            ])->post($upsertUrl, ['vectors' => $payload]);

                        if (!$response->successful()) {
                            throw new \Exception("Upsert failed for batch around chunk {$chunkIndex}: " . $response->body());
                        }

                        $processedCount += count($payload);
                        Log::info("Pinecone Upsert: Progress update", [
                            'processed_vectors' => $processedCount,
                            'last_chunk_index' => $chunkIndex - 1
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Batch upsert error', [
                            'chunk_index_range' => ($chunkIndex - $batchCount) . " to " . ($chunkIndex - 1),
                            'error' => $e->getMessage()
                        ]);
                        throw $e;
                    }
                }

                // Explicitly clear payload and run GC
                unset($payload);
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }

            // Now we can unset the massive text
            unset($text);

            if ($processedCount === 0) {
                throw new \Exception('No vectors were successfully upserted. Please check logs for embedding errors.');
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Text successfully converted to vectors and upserted in memory-efficient batches',
                'upserted_vectors' => $processedCount,
                'total_chunks' => $chunkIndex,
                'course_id' => $courseId,
                'version' => $version
            ]);

        } catch (\Exception $e) {
            Log::error('Pinecone Upsert Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate embedding vector from text using open-source embedding model (Ollama)
     * 
     * @param string $text The text to convert to embedding
     * @return array The embedding vector
     * @throws \Exception If embedding generation fails
     */
    private function generateEmbedding(string $text): array
    {
        try {
            if (empty($text)) {
                throw new \Exception('Text cannot be empty for embedding generation');
            }

            if (empty($this->ollamaHost)) {
                throw new \Exception('OLLAMA_HOST is not configured. Please set it in your .env file.');
            }

            Log::debug('Generating embedding', [
                'model' => $this->ollamaModel,
                'text_length' => strlen($text),
                'ollama_host' => $this->ollamaHost
            ]);

            $response = Http::timeout(60)
                ->connectTimeout(15)
                ->retry(2, 1000)
                ->post("{$this->ollamaHost}/api/embeddings", [
                    'model' => $this->ollamaModel,
                    'prompt' => $text,
                ]);

            if (!$response->successful()) {
                throw new \Exception('Ollama API call failed: ' . $response->body());
            }

            $responseData = $response->json();

            if (!isset($responseData['embedding'])) {
                throw new \Exception('Invalid response from Ollama: embedding not found');
            }

            $embedding = $responseData['embedding'];

            if (!is_array($embedding) || empty($embedding)) {
                throw new \Exception('Invalid embedding format received from Ollama');
            }

            Log::debug('Embedding generated successfully', [
                'dimension' => count($embedding)
            ]);

            return $embedding;

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Ollama connection error', [
                'error' => $e->getMessage(),
                'ollama_host' => $this->ollamaHost
            ]);
            throw new \Exception('Failed to connect to Ollama embedding service. Please ensure Ollama is running and OLLAMA_HOST is correct.');
        } catch (\Exception $e) {
            Log::error('Embedding generation error', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }


    /* =====================================================
     * 3️⃣ UPDATE VECTOR (SAME AS UPSERT)
     * ===================================================== */
    public function update(Request $request): JsonResponse
    {
        // Pinecone update = upsert with same ID
        return $this->upsert($request);
    }

    /* =====================================================
     * 4️⃣ DELETE BY VECTOR IDS
     * ===================================================== */
    public function deleteByIds(Request $request): JsonResponse
    {
        try {
            $ids = $request->input('ids');

            if (empty($ids)) {
                throw new \Exception('Vector IDs are required');
            }

            $response = Http::timeout(30)
                ->connectTimeout(15)
                ->retry(2, 1000)
                ->withHeaders([
                    'Api-Key' => $this->apiKey,
                    'Content-Type' => 'application/json'
                ])->post(
                    "https://{$this->indexName}.svc.{$this->region}.pinecone.io/vectors/delete",
                    ['ids' => $ids]
                );

            return response()->json([
                'status' => 'success',
                'deleted_ids' => $ids
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /* =====================================================
     * 5️⃣ DELETE BY COURSE + VERSION (BEST PRACTICE)
     * Delete vectors using metadata filter (course_id and doc_version)
     * ===================================================== */
    public function deleteByCourse(Request $request): JsonResponse
    {
        try {
            // Get input parameters (same naming convention as upsert and query)
            $courseId = $request->input('course_id');
            $docVersion = $request->input('doc_version');
            $namespace = $request->input('namespace', ''); // Optional namespace

            // Validate required fields
            if (empty($courseId)) {
                throw new \Exception('course_id is required');
            }

            if (empty($docVersion)) {
                throw new \Exception('doc_version is required');
            }

            Log::info('Pinecone Delete: Processing delete request', [
                'course_id' => $courseId,
                'doc_version' => $docVersion,
                'namespace' => $namespace ?: 'default'
            ]);

            // Get the index host (official way per Pinecone docs)
            $indexHost = $this->getIndexHost();
            
            // Fallback: If we can't get host from API, construct it manually
            if (empty($indexHost)) {
                Log::warning('Could not retrieve index host from API, using fallback construction', [
                    'index_name' => $this->indexName,
                    'region' => $this->region
                ]);
                $indexHost = "{$this->indexName}.svc.{$this->region}.pinecone.io";
            }

            $deleteUrl = "https://{$indexHost}/vectors/delete";

            // Build filter using Pinecone's filter syntax with $eq operator
            // Format: {"field": {"$eq": "value"}}
            $filter = [
                'course_id' => ['$eq' => $courseId],
                'version' => ['$eq' => $docVersion]
            ];

            // Build delete payload
            $deletePayload = [
                'filter' => $filter
            ];

            // Add namespace if provided
            if (!empty($namespace)) {
                $deletePayload['namespace'] = $namespace;
            }

            Log::info('Pinecone Delete: Sending delete request', [
                'delete_url' => $deleteUrl,
                'filter' => $filter,
                'namespace' => $namespace ?: 'default'
            ]);

            // Delete vectors from Pinecone (following official Pinecone API format)
            $response = Http::timeout(60)
                ->connectTimeout(15)
                ->retry(2, 1000)
                ->withHeaders([
                    'Api-Key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                    'X-Pinecone-Api-Version' => '2025-10'
                ])->post($deleteUrl, $deletePayload);

            if (!$response->successful()) {
                Log::error('Pinecone delete failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'delete_url' => $deleteUrl,
                    'filter' => $filter
                ]);
                throw new \Exception('Delete failed: ' . $response->body());
            }

            $deleteResult = $response->json();

            Log::info('Pinecone Delete: Vectors deleted successfully', [
                'course_id' => $courseId,
                'doc_version' => $docVersion,
                'response' => $deleteResult
            ]);

            return response()->json([
                'status' => 'success',
                'message' => "Successfully deleted vectors for course '{$courseId}' (version: {$docVersion})",
                'course_id' => $courseId,
                'doc_version' => $docVersion,
                'details' => $deleteResult
            ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        } catch (\Exception $e) {
            Log::error('Pinecone Delete Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /* =====================================================
     * 6️⃣ QUERY - Search relevant data from Pinecone Vector DB
     * ===================================================== */
    public function query(Request $request): JsonResponse
    {
        ini_set('max_execution_time', 300);

        try {
            // Get input parameters (same naming convention as upsert)
            $userQuery = $request->input('user_query');
            $courseId = $request->input('course_id');
            $topK = $request->input('top_k', 5);
            $version = $request->input('doc_version'); // Optional: filter by specific version

            // Validate required fields
            if (empty($userQuery)) {
                throw new \Exception('user_query is required');
            }

            if (empty($courseId)) {
                throw new \Exception('course_id is required');
            }

            Log::info('Pinecone Query: Processing search request', [
                'user_query' => substr($userQuery, 0, 100), // Log first 100 chars
                'course_id' => $courseId,
                'top_k' => $topK,
                'version' => $version ?? 'all versions'
            ]);

            // Convert user query to embedding vector
            $queryVector = $this->generateEmbedding($userQuery);

            if (empty($queryVector)) {
                throw new \Exception('Failed to generate embedding for user query');
            }

            // Get the index host (official way per Pinecone docs)
            $indexHost = $this->getIndexHost();
            
            // Fallback: If we can't get host from API, construct it manually
            if (empty($indexHost)) {
                Log::warning('Could not retrieve index host from API, using fallback construction', [
                    'index_name' => $this->indexName,
                    'region' => $this->region
                ]);
                $indexHost = "{$this->indexName}.svc.{$this->region}.pinecone.io";
            }

            $queryUrl = "https://{$indexHost}/query";

            // Build filter for metadata search
            $filter = [
                'course_id' => $courseId
            ];

            // Add version filter if provided
            if (!empty($version)) {
                $filter['version'] = $version;
            }

            // Build query payload
            $queryPayload = [
                'vector' => $queryVector,
                'topK' => $topK,
                'includeMetadata' => true,
                'filter' => $filter
            ];

            Log::info('Pinecone Query: Sending query request', [
                'query_url' => $queryUrl,
                'vector_dimension' => count($queryVector),
                'filter' => $filter,
                'top_k' => $topK
            ]);

            // Query Pinecone (following official Pinecone API format)
            $response = Http::timeout(60)
                ->connectTimeout(15)
                ->retry(2, 1000)
                ->withHeaders([
                    'Api-Key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'X-Pinecone-Api-Version' => '2025-10'
                ])->post($queryUrl, $queryPayload);

            if (!$response->successful()) {
                Log::error('Pinecone query failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'query_url' => $queryUrl
                ]);
                throw new \Exception('Query failed: ' . $response->body());
            }

            $queryResults = $response->json();

            // Format and return results
            $formattedResults = [];
            if (isset($queryResults['matches']) && is_array($queryResults['matches'])) {
                foreach ($queryResults['matches'] as $match) {
                    $formattedResults[] = [
                        'id' => $match['id'] ?? null,
                        'score' => $match['score'] ?? 0,
                        'text' => $match['metadata']['text'] ?? '',
                        'metadata' => $match['metadata'] ?? []
                    ];
                }
            }

            Log::info('Pinecone Query: Results retrieved', [
                'total_matches' => count($formattedResults),
                'course_id' => $courseId
            ]);

            return response()->json([
                'status' => 'success',
                'query' => $userQuery,
                'course_id' => $courseId,
                'total_results' => count($formattedResults),
                'results' => $formattedResults
            ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        } catch (\Exception $e) {
            Log::error('Pinecone Query Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify that the Pinecone index exists and is accessible
     * 
     * @return bool True if index exists, false otherwise
     */
    private function verifyIndexExists(): bool
    {
        try {
            $headers = [
                'Api-Key' => $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Pinecone-API-Version' => '2025-10'
            ];

            // List all indexes
            $listResponse = Http::timeout(30)
                ->connectTimeout(15)
                ->withHeaders($headers)
                ->get('https://api.pinecone.io/indexes');

            if (!$listResponse->successful()) {
                Log::warning('Failed to list Pinecone indexes', [
                    'status' => $listResponse->status(),
                    'response' => $listResponse->body()
                ]);
                return false;
            }

            $indexes = collect($listResponse->json('indexes'))
                ->pluck('name')
                ->toArray();

            $exists = in_array($this->indexName, $indexes);

            if (!$exists) {
                Log::warning('Pinecone index not found', [
                    'index_name' => $this->indexName,
                    'available_indexes' => $indexes
                ]);
            } else {
                // Check if index is ready by describing it
                try {
                    $describeResponse = Http::timeout(30)
                        ->connectTimeout(15)
                        ->withHeaders($headers)
                        ->get("https://api.pinecone.io/indexes/{$this->indexName}");

                    if ($describeResponse->successful()) {
                        $indexInfo = $describeResponse->json();
                        $status = $indexInfo['status']['ready'] ?? false;
                        
                        Log::info('Pinecone index status', [
                            'index_name' => $this->indexName,
                            'ready' => $status,
                            'host' => $indexInfo['status']['host'] ?? 'N/A'
                        ]);

                        if (!$status) {
                            Log::warning('Pinecone index exists but is not ready', [
                                'index_name' => $this->indexName,
                                'status' => $indexInfo['status'] ?? 'unknown'
                            ]);
                        }
                    }
                } catch (\Exception $descEx) {
                    Log::warning('Could not describe index status', [
                        'error' => $descEx->getMessage()
                    ]);
                }
            }

            return $exists;

        } catch (\Exception $e) {
            Log::error('Error verifying index existence', [
                'error' => $e->getMessage(),
                'index_name' => $this->indexName
            ]);
            // Return false on error - let the upsert attempt fail with a better error message
            return false;
        }
    }

    /**
     * Get the unique host for an index (official Pinecone method)
     * See: https://docs.pinecone.io/guides/manage-data/target-an-index
     * 
     * @return string|null The index host (without https://) or null if not found
     */
    private function getIndexHost(): ?string
    {
        try {
            $headers = [
                'Api-Key' => $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Pinecone-Api-Version' => '2025-10'
            ];

            // Describe the index to get its host
            $describeResponse = Http::timeout(30)
                ->connectTimeout(15)
                ->withHeaders($headers)
                ->get("https://api.pinecone.io/indexes/{$this->indexName}");

            if (!$describeResponse->successful()) {
                Log::error('Failed to describe Pinecone index', [
                    'status' => $describeResponse->status(),
                    'response' => $describeResponse->body(),
                    'index_name' => $this->indexName,
                    'url' => "https://api.pinecone.io/indexes/{$this->indexName}"
                ]);
                return null;
            }

            $indexData = $describeResponse->json();
            
            // Log the full response structure for debugging
            Log::debug('Pinecone index description response', [
                'index_name' => $this->indexName,
                'response_keys' => array_keys($indexData),
                'status_keys' => isset($indexData['status']) ? array_keys($indexData['status']) : 'no status key'
            ]);
            
            // Extract host from status - check multiple possible locations
            $host = $indexData['status']['host'] 
                ?? $indexData['host'] 
                ?? $indexData['status']['hostname']
                ?? null;
            
            if (empty($host)) {
                Log::error('Index host not found in index description', [
                    'index_name' => $this->indexName,
                    'response_structure' => $indexData,
                    'status_ready' => $indexData['status']['ready'] ?? 'unknown'
                ]);
                return null;
            }

            // Remove https:// if present (we'll add it in the URL construction)
            $host = str_replace(['https://', 'http://'], '', $host);
            
            Log::info('Retrieved index host from Pinecone', [
                'index_name' => $this->indexName,
                'host' => $host,
                'ready' => $indexData['status']['ready'] ?? false
            ]);

            return $host;

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Connection error getting index host', [
                'error' => $e->getMessage(),
                'index_name' => $this->indexName,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error('Error getting index host', [
                'error' => $e->getMessage(),
                'index_name' => $this->indexName,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    private function convertCourseJsonToText($jsonInput): string
    {
        $textParts = [];
    
        foreach ($jsonInput['answer'] as $course) {
    
            // Course level
            if (!empty($course['title'])) {
                $textParts[] = $course['title'];
            }
    
            if (!empty($course['description'])) {
                $textParts[] = $course['description'];
            }
    
            // Lessons
            foreach ($course['lessons'] ?? [] as $lesson) {
    
                if (!empty($lesson['title'])) {
                    $textParts[] = $lesson['title'];
                }
    
                if (!empty($lesson['description'])) {
                    $textParts[] = $lesson['description'];
                }
    
                // Sections
                foreach ($lesson['sections'] ?? [] as $section) {
    
                    if (!empty($section['title'])) {
                        $textParts[] = $section['title'];
                    }
    
                    if (!empty($section['description'])) {
                        $textParts[] = $section['description'];
                    }
    
                    // Rows → Columns → Widgets
                    foreach ($section['rows'] ?? [] as $row) {
                        foreach ($row['columns'] ?? [] as $column) {
                            foreach ($column['widgets'] ?? [] as $widget) {
    
                                if (!empty($widget['content'])) {
                                    $textParts[] = $widget['content'];
                                }
                            }
                        }
                    }
                }
            }
        }
    
        // Combine everything into one text block
        $course_text_file = implode("\n\n", $textParts);
    
        return $course_text_file;
    }
    


}
