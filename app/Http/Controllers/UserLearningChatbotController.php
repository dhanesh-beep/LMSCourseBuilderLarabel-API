<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ResponseController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class UserLearningChatbotController extends ResponseController
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
            $chatflowId = env('CHATFLOW_USER_CHATBOT_API');

            $apiHost = rtrim(env('FLOWISE_API_HOST'), '/');

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


    # n8n Chatbot API
    public function n8nChatbot(Request $request): JsonResponse
    {
        try {
            // $N8N_WEBHOOK_URL = env('N8N_CLOUD_WEBHOOK_URL');
            $N8N_WEBHOOK_URL = env('N8N_LOCAL_HOSTED_WEBHOOK_URL');

            // 1. Validate N8N webhook URL is configured
            if (empty($N8N_WEBHOOK_URL)) {
                Log::error('N8N webhook URL not configured');
                return response()->json([
                    'error' => 'Chatbot service is not configured. Please contact administrator.'
                ], 500);
            }

            // 2. Validate input
            $request->validate([
                'user_question' => 'required|string|min:1'
            ]);

            $userQuestion = trim($request->input('user_question'));

            Log::info('N8N Chatbot Request', [
                'user_question' => $userQuestion,
                'webhook_url' => $N8N_WEBHOOK_URL
            ]);

            // 3. Call n8n Cloud Webhook with proper timeout and retry
            // n8n expects: Content-Type: application/json and body: {"query": "..."}
            $n8nResponse = Http::timeout(60)
                ->connectTimeout(15)
                ->retry(2, 1000)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'text/plain, application/json'
                ])
                ->post($N8N_WEBHOOK_URL, [
                    'query' => $userQuestion
                ]);

            // 4. Handle n8n response
            if (!$n8nResponse->successful()) {
                $statusCode = $n8nResponse->status();
                $responseBody = $n8nResponse->body();
                
                Log::error('N8N API call failed', [
                    'status' => $statusCode,
                    'response_body' => $responseBody,
                    'user_question' => $userQuestion,
                    'webhook_url' => $N8N_WEBHOOK_URL
                ]);

                // Handle specific error cases
                if ($statusCode === 404) {
                    $errorData = $n8nResponse->json();
                    $errorMessage = $errorData['message'] ?? 'Webhook not found';
                    
                    return response()->json([
                        'error' => 'Webhook not found',
                        'message' => 'The chatbot webhook is not registered or the URL is incorrect.',
                        'details' => $errorMessage,
                        'hint' => 'Please verify your N8N_WEBHOOK_URL in the .env file and ensure the webhook exists in your n8n instance.'
                    ], 404);
                }

                if ($statusCode === 401 || $statusCode === 403) {
                    return response()->json([
                        'error' => 'Authentication failed',
                        'message' => 'The webhook authentication failed. Please check your n8n webhook credentials.'
                    ], $statusCode);
                }

                return response()->json([
                    'error' => 'Failed to get response from chatbot service',
                    'message' => $statusCode >= 500 
                        ? 'Chatbot service is temporarily unavailable. Please try again later.'
                        : 'Invalid response from chatbot service.',
                    'status_code' => $statusCode,
                    'details' => config('app.debug') ? $responseBody : null
                ], $statusCode >= 500 ? 503 : $statusCode);
            }

            // 5. Handle response - n8n returns plain text (text/plain) or JSON
            $responseBody = $n8nResponse->body();
            $contentType = $n8nResponse->header('Content-Type') ?? '';
            
            if (empty($responseBody)) {
                Log::error('N8N returned empty response', [
                    'user_question' => $userQuestion,
                    'content_type' => $contentType
                ]);
                
                return response()->json([
                    'error' => 'Empty response from chatbot service',
                    'message' => 'The chatbot service returned an empty response.'
                ], 500);
            }

            $llmReply = '';

            // Check if response is plain text (n8n "Respond to Webhook" with TEXT option)
            if (stripos($contentType, 'text/plain') !== false || stripos($contentType, 'text/html') !== false) {
                // Response is plain text - use body directly
                $llmReply = trim($responseBody);
                
                Log::info('N8N Chatbot Response (Plain Text)', [
                    'user_question' => $userQuestion,
                    'content_type' => $contentType,
                    'response_length' => strlen($llmReply)
                ]);
            } else {
                // Try to parse as JSON (for other response formats)
                $n8nData = null;
                try {
                    $n8nData = $n8nResponse->json();
                } catch (\Exception $jsonException) {
                    // If JSON parsing fails but we have a body, try using it as plain text
                    Log::warning('N8N JSON parse failed, treating as plain text', [
                        'error' => $jsonException->getMessage(),
                        'content_type' => $contentType,
                        'user_question' => $userQuestion
                    ]);
                    
                    // Fallback: use body as plain text
                    $llmReply = trim($responseBody);
                }

                // If we successfully parsed JSON, extract the reply
                if ($n8nData !== null) {
                    // n8n webhooks can return an array of execution results
                    // "Respond to Webhook" with TEXT returns: [{"output": "..."}]
                    // If it's an array, get the first item (most common case)
                    if (is_array($n8nData) && isset($n8nData[0])) {
                        // Check if it's a numeric array (list of results)
                        if (array_keys($n8nData) === range(0, count($n8nData) - 1)) {
                            // It's an array of results, get the first one
                            Log::debug('N8N response is an array, extracting first element', [
                                'array_length' => count($n8nData),
                                'first_element_keys' => is_array($n8nData[0]) ? array_keys($n8nData[0]) : 'not_array'
                            ]);
                            $n8nData = $n8nData[0];
                        }
                    }

                    // If still not an array or object, log and return error
                    if (!is_array($n8nData) && !is_object($n8nData)) {
                        Log::error('N8N response is not an array or object', [
                            'response_type' => gettype($n8nData),
                            'response_data' => $n8nData,
                            'response_body' => $responseBody,
                            'user_question' => $userQuestion
                        ]);
                        
                        return response()->json([
                            'error' => 'Unexpected response format from chatbot service',
                            'message' => 'The chatbot service returned an unexpected response format.',
                            'details' => config('app.debug') ? 'Response type: ' . gettype($n8nData) : null
                        ], 500);
                    }

                    // Convert object to array for easier handling
                    if (is_object($n8nData)) {
                        $n8nData = (array) $n8nData;
                    }

                    Log::info('N8N Chatbot Response (JSON)', [
                        'user_question' => $userQuestion,
                        'response_received' => !empty($n8nData),
                        'response_keys' => array_keys($n8nData),
                        'response_sample' => config('app.debug') ? array_slice($n8nData, 0, 3, true) : null
                    ]);

                    // Extract LLM reply from various possible JSON response formats
                    // Try direct keys first - prioritize 'output' since user is using "Respond with TEXT"
                    if (isset($n8nData['output']) && $n8nData['output'] !== null && $n8nData['output'] !== '') {
                        $llmReply = is_string($n8nData['output']) ? $n8nData['output'] : (string)$n8nData['output'];
                    } elseif (isset($n8nData['LLM_reply']) && $n8nData['LLM_reply'] !== null && $n8nData['LLM_reply'] !== '') {
                        $llmReply = is_string($n8nData['LLM_reply']) ? $n8nData['LLM_reply'] : (string)$n8nData['LLM_reply'];
                    } elseif (isset($n8nData['llm_reply']) && $n8nData['llm_reply'] !== null && $n8nData['llm_reply'] !== '') {
                        $llmReply = is_string($n8nData['llm_reply']) ? $n8nData['llm_reply'] : (string)$n8nData['llm_reply'];
                    } elseif (isset($n8nData['message']) && $n8nData['message'] !== null && $n8nData['message'] !== '') {
                        $llmReply = is_string($n8nData['message']) ? $n8nData['message'] : (string)$n8nData['message'];  // Most common n8n format
                    } elseif (isset($n8nData['text']) && $n8nData['text'] !== null && $n8nData['text'] !== '') {
                        $llmReply = is_string($n8nData['text']) ? $n8nData['text'] : (string)$n8nData['text'];
                    } elseif (isset($n8nData['response']) && $n8nData['response'] !== null && $n8nData['response'] !== '') {
                        $llmReply = is_string($n8nData['response']) ? $n8nData['response'] : (string)$n8nData['response'];
                    } elseif (isset($n8nData['answer']) && $n8nData['answer'] !== null && $n8nData['answer'] !== '') {
                        $llmReply = is_string($n8nData['answer']) ? $n8nData['answer'] : (string)$n8nData['answer'];
                    } elseif (isset($n8nData['data']['message']) && $n8nData['data']['message'] !== null && $n8nData['data']['message'] !== '') {
                        $llmReply = is_string($n8nData['data']['message']) ? $n8nData['data']['message'] : (string)$n8nData['data']['message'];  // Nested in data
                    } elseif (isset($n8nData['data']['text']) && $n8nData['data']['text'] !== null && $n8nData['data']['text'] !== '') {
                        $llmReply = is_string($n8nData['data']['text']) ? $n8nData['data']['text'] : (string)$n8nData['data']['text'];
                    } elseif (isset($n8nData['json']['message']) && $n8nData['json']['message'] !== null && $n8nData['json']['message'] !== '') {
                        $llmReply = is_string($n8nData['json']['message']) ? $n8nData['json']['message'] : (string)$n8nData['json']['message'];  // Nested in json
                    } elseif (isset($n8nData['json']['text']) && $n8nData['json']['text'] !== null && $n8nData['json']['text'] !== '') {
                        $llmReply = is_string($n8nData['json']['text']) ? $n8nData['json']['text'] : (string)$n8nData['json']['text'];
                    } elseif (isset($n8nData['body']['message']) && $n8nData['body']['message'] !== null && $n8nData['body']['message'] !== '') {
                        $llmReply = is_string($n8nData['body']['message']) ? $n8nData['body']['message'] : (string)$n8nData['body']['message'];  // Sometimes in body
                    }

                    // If still empty, try to get the first value if it's a string
                    if (empty($llmReply) && count($n8nData) === 1) {
                        $firstValue = reset($n8nData);
                        if (is_string($firstValue)) {
                            $llmReply = $firstValue;
                        }
                    }
                }
            }

            // If we still don't have a reply, log the full response for debugging
            if (empty($llmReply)) {
                Log::warning('N8N response does not contain expected reply field', [
                    'user_question' => $userQuestion,
                    'content_type' => $contentType,
                    'response_body' => $responseBody,
                    'response_length' => strlen($responseBody)
                ]);
                
                return response()->json([
                    'error' => 'No reply found in chatbot response',
                    'message' => 'The chatbot service responded but did not contain a reply message.',
                    'details' => config('app.debug') ? [
                        'content_type' => $contentType,
                        'response_preview' => substr($responseBody, 0, 200)
                    ] : null
                ], 500);
            }

            // 7. Return final response to frontend
            return response()->json([
                'user_question' => $userQuestion,
                'LLM_reply' => $llmReply
            ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('N8N Connection Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_question' => $request->input('user_question', 'N/A')
            ]);

            return response()->json([
                'error' => 'Failed to connect to chatbot service',
                'message' => 'Unable to reach the chatbot service. Please check your internet connection and try again.'
            ], 503);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'message' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);

        } catch (\Throwable $e) {
            Log::error('N8N Chatbot Exception', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_question' => $request->input('user_question', 'N/A')
            ]);

            $isDebug = config('app.debug', false);

            return response()->json([
                'error' => 'Internal server error',
                'message' => $isDebug ? $e->getMessage() : 'An unexpected error occurred. Please try again later.',
                'details' => $isDebug ? [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'type' => get_class($e)
                ] : null
            ], 500);
        }
    }
    
}
