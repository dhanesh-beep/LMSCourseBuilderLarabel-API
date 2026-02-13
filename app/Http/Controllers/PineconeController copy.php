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
     * 2️⃣ UPSERT VECTORS (UPDATED: Supports both text and JSON input)
     * ===================================================== */
    public function upsert(Request $request): JsonResponse
    {
        ini_set('max_execution_time', 3600);
        ini_set('memory_limit', '1024M');

        try {
            $courseId = $request->input('course_id');
            $courseSlug = $request->input('course_slug', '');
            $version = $request->input('doc_version', 'v1');

            // Support both text input (legacy) and JSON input (new dual-index semantic chunking)
            $text = $request->input('text');
            $jsonData = $request->input('json_data');

            // Support JSON file upload (similar to extractCourseText)
            if ($request->hasFile('file')) {
                $jsonString = file_get_contents($request->file('file')->getRealPath());
                $jsonData = json_decode($jsonString, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Invalid JSON format in uploaded file: ' . json_last_error_msg());
                }
            }

            // Determine chunking strategy
            $useSemanticChunking = !empty($jsonData);

            if ($useSemanticChunking) {
                // Decode JSON if it's a string
                if (is_string($jsonData)) {
                    $jsonData = json_decode($jsonData, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new \Exception('Invalid JSON format in json_data: ' . json_last_error_msg());
                    }
                }

                // Validate JSON structure
                if (!isset($jsonData['answer']) || !is_array($jsonData['answer'])) {
                    throw new \Exception('Invalid JSON structure: "answer" key not found or is not an array');
                }
            } else {
                // Legacy text-based chunking
                $chunkSize = $request->input('chunk_size', 1000);
                $chunkOverlap = $request->input('chunk_overlap', 100);

                // Guard against infinite loop in chunkText
                if ($chunkOverlap >= $chunkSize) {
                    throw new \Exception('chunk_overlap must be less than chunk_size');
                }
            }

            // Validate required fields
            if (!$courseId) {
                throw new \Exception('course_id is required');
            }

            if (empty($text) && empty($jsonData)) {
                throw new \Exception('Either text, json_data, or a JSON file upload is required');
            }

            // Validate Pinecone configuration
            if (empty($this->apiKey)) {
                throw new \Exception('PINECONE_API_KEY is not configured');
            }

            if (empty($this->region)) {
                throw new \Exception('PINECONE_ENV (region) is not configured');
            }

            Log::info('Pinecone Upsert: Starting dual-index process', [
                'course_id' => $courseId,
                'chunking_strategy' => $useSemanticChunking ? 'dual-index-text-based' : 'text-based'
            ]);

            $batchSize = 40;
            $processedCount = 0;
            $totalChunks = 0;
            $chunkSize = $request->input('chunk_size', 1000);
            $chunkOverlap = $request->input('chunk_overlap', 100);

            if ($useSemanticChunking) {
                // Extract both course texts from JSON
                $courseTexts = $this->extractCourseTexts($jsonData);
                $generalText = $courseTexts['general_text'];
                $lmsText = $courseTexts['lms_text'];

                if (empty($generalText) && empty($lmsText)) {
                    throw new \Exception('No course content found in JSON file');
                }

                $generalIndexName = 'general-course-knowledgebase-testing';
                $lmsIndexName = 'lms-course-knowledgebase-testing';

                $generalResult = null;
                $lmsResult = null;

                // Upsert general_course_text to general-course-knowledgebase-testing (only titles)
                if (!empty($generalText)) {
                    Log::info('Upserting general course text (titles only) to index', [
                        'index_name' => $generalIndexName,
                        'text_length' => strlen($generalText)
                    ]);

                    $generalResult = $this->upsertTextToIndex(
                        $generalText,
                        $generalIndexName,
                        $courseId,
                        $courseSlug,
                        $version,
                        $chunkSize,
                        $chunkOverlap
                    );

                    if (!$generalResult['success']) {
                        throw new \Exception("Failed to upsert general course text: " . ($generalResult['error'] ?? 'Unknown error'));
                    }

                    $processedCount += $generalResult['processed_count'];
                    $totalChunks += $generalResult['total_chunks'];
                }

                // Upsert lms_course_text to lms-course-knowledgebase-testing (everything)
                if (!empty($lmsText)) {
                    Log::info('Upserting LMS course text (full content) to index', [
                        'index_name' => $lmsIndexName,
                        'text_length' => strlen($lmsText)
                    ]);

                    $lmsResult = $this->upsertTextToIndex(
                        $lmsText,
                        $lmsIndexName,
                        $courseId,
                        $courseSlug,
                        $version,
                        $chunkSize,
                        $chunkOverlap
                    );

                    if (!$lmsResult['success']) {
                        throw new \Exception("Failed to upsert LMS course text: " . ($lmsResult['error'] ?? 'Unknown error'));
                    }

                    $processedCount += $lmsResult['processed_count'];
                    $totalChunks += $lmsResult['total_chunks'];
                }

            } else {
                // LEGACY: Text-based chunking with single index
                // Check if index exists and create it if not
                if (!$this->createIndexIfNotExists($this->indexName)) {
                    throw new \Exception("Failed to verify/create index: {$this->indexName}");
                }

                // Get the index host from Pinecone
                $indexHost = $this->getIndexHost();
                if (empty($indexHost)) {
                    $indexHost = "{$this->indexName}.svc.{$this->region}.pinecone.io";
                }
                $upsertUrl = "https://{$indexHost}/vectors/upsert";

                $textLength = mb_strlen($text, 'UTF-8');
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
                                // Build metadata with text field for both indexes
                                $metadata = [
                                    'course_id' => $courseId,
                                    'course_slug' => $courseSlug,
                                    'version' => $version,
                                    'chunk_index' => $chunkIndex,
                                    'content_type' => 'course_text',
                                    'text_length' => strlen($chunk)
                                ];

                                // Set text field - include course_slug for general index
                                if ($this->indexName === 'general-course-knowledgebase_testing') {
                                    $metadata['text'] = $courseSlug . "_" . $chunk;
                                } else {
                                    $metadata['text'] = $chunk;
                                }

                                $payload[] = [
                                    'id' => "{$courseId}_{$courseSlug}_{$version}_chunk_{$chunkIndex}",
                                    'values' => $embedding,
                                    'metadata' => $metadata
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
                            $start = $textLength;
                            break;
                        }

                        $start = $end - $chunkOverlap;
                    }

                    if (!empty($payload)) {
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

                    unset($payload);
                    if (function_exists('gc_collect_cycles')) {
                        gc_collect_cycles();
                    }
                }

                $totalChunks = $chunkIndex;
                unset($text);
            }

            if ($processedCount === 0) {
                throw new \Exception('No vectors were successfully upserted. Please check logs for embedding errors.');
            }

            $responseData = [
                'status' => 'success',
                'upserted_vectors' => $processedCount,
                'total_chunks' => $totalChunks,
                'course_id' => $courseId,
                'version' => $version
            ];

            if ($useSemanticChunking) {
                $responseData['message'] = 'Course texts successfully extracted and upserted to dual indexes';
                $responseData['chunking_strategy'] = 'dual-index-text-based';
                $responseData['indexes'] = [
                    'general_index' => 'general-course-knowledgebase_testing',
                    'lms_index' => 'lms-course-knowledgebase_testing'
                ];
                if (isset($generalResult)) {
                    $responseData['general_index_vectors'] = $generalResult['processed_count'];
                    $responseData['general_index_chunks'] = $generalResult['total_chunks'];
                }
                if (isset($lmsResult)) {
                    $responseData['lms_index_vectors'] = $lmsResult['processed_count'];
                    $responseData['lms_index_chunks'] = $lmsResult['total_chunks'];
                }
            } else {
                $responseData['message'] = 'Text successfully converted to vectors and upserted in memory-efficient batches';
                $responseData['chunking_strategy'] = 'text-based';
            }

            return response()->json($responseData);

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
            $topK = $request->input('top_k', 3);
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

    /**
     * Create a Pinecone index if it doesn't exist
     *
     * @param string $indexName The name of the index to create
     * @return bool True if index exists or was created successfully, false otherwise
     */
    private function createIndexIfNotExists(string $indexName): bool
    {
        try {
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
                Log::error('Failed to list indexes during upsert', [
                    'status' => $listResponse->status(),
                    'response' => $listResponse->body()
                ]);
                return false;
            }

            $indexes = collect($listResponse->json('indexes'))
                ->pluck('name')
                ->toArray();

            /* ===============================
            * 2️⃣ INDEX EXISTS → RETURN TRUE
            * =============================== */
            if (in_array($indexName, $indexes)) {
                Log::info('Index already exists, proceeding with upsert', [
                    'index_name' => $indexName
                ]);
                return true;
            }

            /* ===============================
            * 3️⃣ CREATE INDEX
            * =============================== */
            Log::info('Index does not exist, creating index', [
                'index_name' => $indexName
            ]);

            $createPayload = [
                'name' => $indexName,
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
                Log::error('Failed to create index during upsert', [
                    'index_name' => $indexName,
                    'status' => $createResponse->status(),
                    'response' => $createResponse->body()
                ]);
                return false;
            }

            Log::info('Index created successfully during upsert', [
                'index_name' => $indexName
            ]);

            // Wait a bit for index to be ready
            sleep(5);

            return true;

        } catch (\Exception $e) {
            Log::error('Error creating index during upsert', [
                'index_name' => $indexName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Get index host by index name
     *
     * @param string $indexName The name of the index
     * @return string|null The index host or null if not found
     */
    private function getIndexHostByName(string $indexName): ?string
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
                ->get("https://api.pinecone.io/indexes/{$indexName}");

            if (!$describeResponse->successful()) {
                Log::error('Failed to describe Pinecone index', [
                    'status' => $describeResponse->status(),
                    'response' => $describeResponse->body(),
                    'index_name' => $indexName,
                    'url' => "https://api.pinecone.io/indexes/{$indexName}"
                ]);
                return null;
            }

            $indexData = $describeResponse->json();

            // Extract host from status - check multiple possible locations
            $host = $indexData['status']['host']
                ?? $indexData['host']
                ?? $indexData['status']['hostname']
                ?? null;

            if (empty($host)) {
                Log::error('Index host not found in index description', [
                    'index_name' => $indexName,
                    'response_structure' => $indexData,
                    'status_ready' => $indexData['status']['ready'] ?? 'unknown'
                ]);
                return null;
            }

            return $host;

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Connection error getting index host', [
                'error' => $e->getMessage(),
                'index_name' => $indexName,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error('Error getting index host', [
                'index_name' => $indexName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Upsert text to a specific Pinecone index using text-based chunking
     *
     * @param string $text The text to upsert
     * @param string $indexName The name of the index
     * @param string $courseId The course ID
     * @param string $courseSlug The course slug
     * @param string $version The document version
     * @param int $chunkSize The chunk size
     * @param int $chunkOverlap The chunk overlap
     * @return array Array with 'success' boolean and 'processed_count' integer
     */
    private function upsertTextToIndex(
        string $text,
        string $indexName,
        string $courseId,
        string $courseSlug,
        string $version,
        int $chunkSize = 1000,
        int $chunkOverlap = 100
    ): array
    {
        try {
            // Check if index exists and create it if not
            if (!$this->createIndexIfNotExists($indexName)) {
                return [
                    'success' => false,
                    'processed_count' => 0,
                    'error' => "Failed to verify/create index: {$indexName}"
                ];
            }

            // Get the index host
            $indexHost = $this->getIndexHostByName($indexName);
            if (empty($indexHost)) {
                $indexHost = "{$indexName}.svc.{$this->region}.pinecone.io";
            }
            $upsertUrl = "https://{$indexHost}/vectors/upsert";

            $batchSize = 40;
            $processedCount = 0;
            $textLength = mb_strlen($text, 'UTF-8');
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
                            // Build metadata with text field for both indexes
                            $metadata = [
                                'course_id' => $courseId,
                                'course_slug' => $courseSlug,
                                'version' => $version,
                                'chunk_index' => $chunkIndex,
                                'content_type' => 'course_text',
                                'text_length' => strlen($chunk)
                            ];

                            // Set text field - include course_slug for general index
                            if ($indexName === 'general-course-knowledgebase-testing') {
                                $metadata['text'] = "Course_Title: ". $courseSlug . " " . $chunk;
                            } else {
                                $metadata['text'] = $chunk;
                            }

                            $payload[] = [
                                'id' => "{$courseId}_{$courseSlug}_{$version}_chunk_{$chunkIndex}",
                                'values' => $embedding,
                                'metadata' => $metadata
                            ];
                                }
                    } catch (\Exception $e) {
                        Log::error('Error generating embedding for chunk', [
                            'chunk_index' => $chunkIndex,
                            'index_name' => $indexName,
                            'error' => $e->getMessage()
                        ]);
                    }

                    $chunkIndex++;
                    $batchCount++;
                    if ($end >= $textLength) {
                        $start = $textLength;
                        break;
                    }

                    $start = $end - $chunkOverlap;
                }

                if (!empty($payload)) {
                    try {
                        $response = Http::timeout(300)
                            ->withHeaders([
                                'Api-Key' => $this->apiKey,
                                'Content-Type' => 'application/json',
                                'X-Pinecone-Api-Version' => '2025-10'
                            ])->post($upsertUrl, ['vectors' => $payload]);

                        if (!$response->successful()) {
                            throw new \Exception("Upsert failed for batch around chunk {$chunkIndex} in index {$indexName}: " . $response->body());
                        }

                        $processedCount += count($payload);
                        Log::info("Pinecone Upsert: Progress update for index {$indexName}", [
                            'processed_vectors' => $processedCount,
                            'last_chunk_index' => $chunkIndex - 1
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Batch upsert error', [
                            'chunk_index_range' => ($chunkIndex - $batchCount) . " to " . ($chunkIndex - 1),
                            'index_name' => $indexName,
                            'error' => $e->getMessage()
                        ]);
                        throw $e;
                    }
                }

                unset($payload);
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }

            return [
                'success' => true,
                'processed_count' => $processedCount,
                'total_chunks' => $chunkIndex
            ];

        } catch (\Exception $e) {
            Log::error('Error upserting text to index', [
                'index_name' => $indexName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'processed_count' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Extract both general and LMS course texts from JSON data
     *
     * @param array $jsonData The JSON data containing course structure
     * @return array Array with 'general_text' and 'lms_text' keys
     */
    private function extractCourseTexts($jsonData): array
    {
        $generalText = $this->General_convertCourseJsonToText($jsonData);
        $lmsText = $this->LMS_convertCourseJsonToText($jsonData);

        return [
            'general_text' => $generalText,
            'lms_text' => $lmsText
        ];
    }

    private function General_convertCourseJsonToText($jsonInput): string
    {
        $textParts = [];

        foreach ($jsonInput['answer'] as $course) {
            // Lessons - only titles
            foreach ($course['lessons'] ?? [] as $lesson) {

                if (!empty($lesson['title'])) {
                    $textParts[] = $lesson['title'];
                }

                // Sections - only titles
                foreach ($lesson['sections'] ?? [] as $section) {

                    if (!empty($section['title'])) {
                        $textParts[] = $section['title'];
                    }
                }
            }
        }

        // Combine everything into one text block
        $course_text_file = implode("\n\n", $textParts);

        return $course_text_file;
    }

    private function LMS_convertCourseJsonToText($jsonInput): string
    {
        $textParts = [];

        foreach ($jsonInput['answer'] as $course) {
        # copurse slag
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

    /**
     * Create semantic chunks from course JSON based on structure keywords
     * (title, Lesson, description, sections)
     *
     * @param array $jsonData The course JSON data
     * @param string $courseId The course ID
     * @param string $courseSlug The course slug
     * @param string $version The document version
     * @return array Array of chunks with metadata
     */
    private function createSemanticChunks(array $jsonData, string $courseId, string $courseSlug, string $version): array
    {
        $chunks = [];
        $chunkIndex = 0;

        if (!isset($jsonData['answer']) || !is_array($jsonData['answer'])) {
            throw new \Exception('Invalid JSON structure: "answer" key not found or is not an array');
        }

        foreach ($jsonData['answer'] as $courseIndex => $course) {
            // Course-level chunk (title + description)
            $courseContent = [];
            
            if (!empty($course['title'])) {
                $courseContent[] = "Course Title: " . $course['title'];
            }
            
            if (!empty($course['description'])) {
                $courseContent[] = "Course Description: " . $course['description'];
            }

            if (!empty($courseContent)) {
                $chunks[] = [
                    'content' => implode("\n\n", $courseContent),
                    'metadata' => [
                        'course_id' => $courseId,
                        'course_slug' => $courseSlug,
                        'version' => $version,
                        'chunk_index' => $chunkIndex++,
                        'chunk_type' => 'course',
                        'course_title' => $course['title'] ?? '',
                        'content_type' => 'course_text'
                    ]
                ];
            }

            // Process lessons
            foreach ($course['lessons'] ?? [] as $lessonIndex => $lesson) {
                // Lesson-level chunk (title + description)
                $lessonContent = [];
                
                if (!empty($lesson['title'])) {
                    $lessonContent[] = "Lesson Title: " . $lesson['title'];
                }
                
                if (!empty($lesson['description'])) {
                    $lessonContent[] = "Lesson Description: " . $lesson['description'];
                }

                if (!empty($lessonContent)) {
                    $chunks[] = [
                        'content' => implode("\n\n", $lessonContent),
                        'metadata' => [
                            'course_id' => $courseId,
                            'course_slug' => $courseSlug,
                            'version' => $version,
                            'chunk_index' => $chunkIndex++,
                            'chunk_type' => 'lesson',
                            'lesson_index' => $lessonIndex,
                            'lesson_title' => $lesson['title'] ?? '',
                            'course_title' => $course['title'] ?? '',
                            'content_type' => 'course_text'
                        ]
                    ];
                }

                // Process sections
                foreach ($lesson['sections'] ?? [] as $sectionIndex => $section) {
                    $sectionContent = [];
                    
                    if (!empty($section['title'])) {
                        $sectionContent[] = "Section Title: " . $section['title'];
                    }
                    
                    if (!empty($section['description'])) {
                        $sectionContent[] = "Section Description: " . $section['description'];
                    }

                    // Collect all widget content in this section
                    $widgetContents = [];
                    foreach ($section['rows'] ?? [] as $row) {
                        foreach ($row['columns'] ?? [] as $column) {
                            foreach ($column['widgets'] ?? [] as $widget) {
                                if (!empty($widget['content'])) {
                                    $widgetContents[] = $widget['content'];
                                }
                            }
                        }
                    }

                    if (!empty($widgetContents)) {
                        $sectionContent[] = "Content:\n" . implode("\n\n", $widgetContents);
                    }

                    if (!empty($sectionContent)) {
                        $chunks[] = [
                            'content' => implode("\n\n", $sectionContent),
                            'metadata' => [
                                'course_id' => $courseId,
                                'course_slug' => $courseSlug,
                                'version' => $version,
                                'chunk_index' => $chunkIndex++,
                                'chunk_type' => 'section',
                                'lesson_index' => $lessonIndex,
                                'section_index' => $sectionIndex,
                                'lesson_title' => $lesson['title'] ?? '',
                                'section_title' => $section['title'] ?? '',
                                'course_title' => $course['title'] ?? '',
                                'content_type' => 'course_text'
                            ]
                        ];
                    }
                }
            }
        }

        return $chunks;
    }

    private function convertCourseJsonToText($jsonInput): string
    {
        $textParts = [];
    
        foreach ($jsonInput['answer'] as $course) {
        # copurse slag
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
