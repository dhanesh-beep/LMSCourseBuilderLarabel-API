<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class UserLearningChatbotController extends Controller
{
    /**
     * User Chatbot API
     * Accepts text input, sends to Flowise, returns LLM response
     */
    public function userQueryReply(Request $request): JsonResponse
    {
        ini_set('max_execution_time', 900);

        try {
            // ✅ Validate request
            $request->validate([
                'text' => 'required|string|min:1',
            ]);

            $rawText = trim($request->input('text'));
            $cleanedText = $this->cleanText($rawText);

            $inputTokens = $this->countTokens($cleanedText);
            $startTime = microtime(true);
            $flowiseStart = Carbon::now();

            // ✅ Flowise config
            //$chatflowId = env('FLOWISE_CHATFLOW_USER_CHATBOT_API_KEY');
            $apiHost = rtrim(env('FLOWISE_API_HOST'), '/');
            $chatflowId = 'e4f7694a-6df2-49c9-b1c5-3c277d4cc44d';  

            // ✅ Call Flowise
            $response = Http::timeout(env('FLOWISE_TIMEOUT', 900))
                ->connectTimeout(env('FLOWISE_CONNECT_TIMEOUT', 60))
                ->post("$apiHost/api/v1/prediction/$chatflowId", [
                    'question' => $cleanedText,
                ]);

            if ($response->failed()) {
                Log::error('Flowise API failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                return response()->json([
                    'error' => 'Flowise API call failed',
                    'details' => $response->body(),
                ], $response->status());
            }

            $flowiseData = $response->json();

            // ✅ Flowise usually returns { text: "..." }
            $llmText = $flowiseData['text'] ?? json_encode($flowiseData);

            $outputTokens = $this->countTokens($llmText);
            $duration = round(microtime(true) - $startTime, 2);

            // ✅ Metrics
            $metrics = [
                'date_time' => Carbon::now()->toDateTimeString(),
                'chatflow_id' => $chatflowId,
                'user_input' => $cleanedText,
                'input_tokens' => $inputTokens,
                'llm_response' => $llmText,
                'output_tokens' => $outputTokens,
                'duration_sec' => $duration,
            ];

            Log::info('Conversation Logs', $metrics,'User', $metrics);

            return response()->json([
                'LLM_return' => $llmText,
                'meta' => $metrics,
            ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        } catch (\Throwable $e) {
            Log::error('UserLearningChatbot exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Internal Server Error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clean input text
     */
    private function cleanText(string $text): string
    {
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/\s+([.,!?;:])/', '$1', $text);
        return trim($text);
    }

    /**
     * Rough token estimation (4 chars ≈ 1 token)
     */
    private function countTokens(?string $text): int
    {
        if (empty($text)) {
            return 0;
        }

        return (int) ceil(mb_strlen($text) / 4);
    }
}
