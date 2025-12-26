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

    public function __construct()
    {
        $this->apiKey = env('PINECONE_API_KEY');
        $this->indexName = env('PINECONE_INDEX_NAME');
        $this->region = env('PINECONE_ENV'); // us-east-1
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
     * 2️⃣ UPSERT VECTORS
     * ===================================================== */
    public function upsert(Request $request): JsonResponse
    {
        try {
            $courseId = $request->input('course_id');
            $version  = $request->input('doc_version', 'v1');
            $vectors  = $request->input('vectors'); // embeddings already generated

            if (!$courseId || empty($vectors)) {
                throw new \Exception('course_id and vectors are required');
            }

            $payload = [];

            foreach ($vectors as $i => $vector) {
                $payload[] = [
                    'id' => "{$courseId}_{$version}_chunk_{$i}",
                    'values' => $vector['values'],
                    'metadata' => array_merge(
                        $vector['metadata'] ?? [],
                        [
                            'course_id' => $courseId,
                            'version' => $version,
                            'chunk_index' => $i
                        ]
                    )
                ];
            }

            $response = Http::timeout(60)
                ->connectTimeout(15)
                ->retry(2, 1000)
                ->withHeaders([
                    'Api-Key' => $this->apiKey,
                    'Content-Type' => 'application/json'
                ])->post(
                    "https://{$this->indexName}.svc.{$this->region}.pinecone.io/vectors/upsert",
                    ['vectors' => $payload]
                );

            if (!$response->successful()) {
                throw new \Exception('Upsert failed');
            }

            return response()->json([
                'status' => 'success',
                'upserted_vectors' => count($payload)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
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
     * ===================================================== */
    public function deleteByCourse(Request $request): JsonResponse
    {
        try {
            $courseId = $request->input('course_id');
            $version  = $request->input('doc_version');

            if (!$courseId || !$version) {
                throw new \Exception('course_id and doc_version are required');
            }

            $response = Http::timeout(30)
                ->connectTimeout(15)
                ->retry(2, 1000)
                ->withHeaders([
                    'Api-Key' => $this->apiKey,
                    'Content-Type' => 'application/json'
                ])->post(
                    "https://{$this->indexName}.svc.{$this->region}.pinecone.io/vectors/delete",
                    [
                        'filter' => [
                            'course_id' => $courseId,
                            'version' => $version
                        ]
                    ]
                );

            return response()->json([
                'status' => 'success',
                'message' => "Deleted course {$courseId} ({$version})"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /* =====================================================
     * 6️⃣ QUERY (OPTIONAL TEST)
     * ===================================================== */
    public function query(Request $request): JsonResponse
    {
        try {
            $vector = $request->input('vector');
            $topK   = $request->input('top_k', 3);

            if (!$vector) {
                throw new \Exception('Query vector required');
            }

            $response = Http::timeout(30)
                ->connectTimeout(15)
                ->retry(2, 1000)
                ->withHeaders([
                    'Api-Key' => $this->apiKey,
                    'Content-Type' => 'application/json'
                ])->post(
                    "https://{$this->indexName}.svc.{$this->region}.pinecone.io/query",
                    [
                        'vector' => $vector,
                        'topK' => $topK,
                        'includeMetadata' => true
                    ]
                );

            return response()->json($response->json());

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
