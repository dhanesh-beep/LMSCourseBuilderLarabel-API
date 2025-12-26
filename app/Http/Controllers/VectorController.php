<?php

namespace App\Http\Controllers;

use App\Services\PineconeService;
use Illuminate\Http\Request;

class VectorController extends Controller
{
    protected $pinecone;

    public function __construct(PineconeService $pinecone)
    {
        $this->pinecone = $pinecone;
    }

    public function store(Request $request)
    {
        set_time_limit(600);
        // Request: { "course_id": "1", "course_slug": "it-acceptable-use-policy-training", "doc_version": "v1", "text": "full text", "chunk_size": 1000, "overlap": 200 }
        return $this->pinecone->upsertChunks(
            $request->course_id,
            $request->course_slug,
            $request->doc_version,
            $request->text,
            $request->chunk_size ?? 1000,
            $request->overlap ?? 200
        );
    }

    public function destroyByFilter(Request $request)
    {
        set_time_limit(600);
        // Example: { "category": { "$eq": "tech" } }
        $result = $this->pinecone->deleteByFilter($request->input('filter'));
        return response()->json($result);
    }

   public function search(Request $request)
    {
        set_time_limit(600);
        // Request: { "query": "What is Laravel?" }
        $topDocs = $this->pinecone->ask($request->query);
        
        return response()->json([
            'context' => $topDocs,
            'message' => 'Top 3 matches found'
        ]);
    }

}