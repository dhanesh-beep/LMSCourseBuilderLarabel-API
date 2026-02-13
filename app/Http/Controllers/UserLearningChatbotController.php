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

    /**
     * Append chatbot interaction data to CSV file
     */
    private function appendToCsv(array $data): void
    {
        $csvFile = base_path('LMS_UserChatbot_Interaction_Logs.csv');

        try {
            $fileExists = file_exists($csvFile);

            $handle = fopen($csvFile, 'a');

            if (!$fileExists) {
                // Write headers if file doesn't exist
                fputcsv($handle, [
                    'timestamp',
                    'chat_intention',
                    'agent_ID',
                    'user_query',
                    'input_tokens',
                    'Output_text',
                    'recommand_question',
                    'output_tokens',
                    'total_execution_time'
                ]);
            }

            // Write the data row
            fputcsv($handle, [
                $data['timestamp'],
                $data['chat_intention'],
                $data['agent_ID'],
                $data['user_query'],
                $data['input_tokens'],
                $data['Output_text'],
                $data['recommand_question'],
                $data['output_tokens'],
                $data['total_execution_time']
            ]);

            fclose($handle);

        } catch (\Exception $e) {
            Log::error('Failed to write to CSV file', [
                'error' => $e->getMessage(),
                'file' => $csvFile,
                'data' => $data
            ]);
        }
    }

    // $entity = 'dhanesh_1991'; // replace with your wandb username or org
    // $project = 'Logs_user_chatbot';
    function logToWandbWeave(array $logData)
    {

        // Build a single trace payload compatible with Weave traces API
        $trace = [
            'trace_id' => uniqid('chat_trace_'),
            'timestamp_ms' => round(microtime(true) * 1000),
            'attributes' => [
                'chat_intention' => $logData['chat_intention'] ?? null,
                'agent_ID' => $logData['agent_ID'] ?? null,
                'user_query' => $logData['user_query'] ?? null,
                'Output_text' => $logData['Output_text'] ?? null,
                'recommand_question' => $logData['recommand_question'] ?? null,
                'input_tokens' => $logData['input_tokens'] ?? null,
                'output_tokens' => $logData['output_tokens'] ?? null,
                'total_execution_time' => $logData['total_execution_time'] ?? null,
            ]
        ];

        // Send only the single trace object to the Python helper; credentials
        // (WANDB_API_KEY, WANDB_ENTITY, WANDB_PROJECT) will be handled by the
        // Python script per your request.
        $json = json_encode($trace, JSON_UNESCAPED_UNICODE);

        // Prepare command to run Python script. Use absolute path to script.
        $pythonScript = base_path('User_Chatbot_Logs.py');
        $cmd = "python " . escapeshellarg($pythonScript) . " --stdin";

        $descriptors = [
            0 => ["pipe", "r"], // stdin
            1 => ["pipe", "w"], // stdout
            2 => ["pipe", "w"]  // stderr
        ];

        // Do not inject credentials into the child process; Python will read
        // them from its environment or other configuration.
        $procEnv = null;

        try {
            $process = proc_open($cmd, $descriptors, $pipes, base_path(), $procEnv);
            if (!is_resource($process)) {
                Log::error('Failed to start wandb python process', ['cmd' => $cmd]);
                return false;
            }

            // Write payload to python stdin
            fwrite($pipes[0], $json);
            fclose($pipes[0]);

            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);

            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            $returnCode = proc_close($process);

            if ($returnCode !== 0) {
                Log::error('wandb python logging failed', [
                    'return_code' => $returnCode,
                    'stdout' => $stdout,
                    'stderr' => $stderr,
                    'payload_preview' => substr($json, 0, 1000)
                ]);
                return false;
            }

            Log::info('wandb python logging succeeded', ['stdout' => $stdout]);
            return true;

        } catch (\Throwable $e) {
            Log::error('Exception while invoking wandb python logger', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    # n8n Chatbot API
    public function n8nChatbot(Request $request): JsonResponse
    {
        $startTime = microtime(true);

        try {
            // $N8N_WEBHOOK_URL = env('N8N_LOCAL_HOSTED_WEBHOOK_URL');
            $N8N_WEBHOOK_URL = env('N8N_PRODUCTION_HOSTED_WEBHOOK_URL');

            // Validate input
            $request->validate([
                'user_question' => 'required|string|min:1',
                'chatbot_request_from' => 'nullable|string',
                // 'course_id' => 'nullable|integer'
                'course_slug' => 'nullable|string'
            ]);

            $chatbotRequestFrom = $request->input('chatbot_request_from');

            if ($chatbotRequestFrom === 'lms') {
                $N8N_WEBHOOK_URL = rtrim($N8N_WEBHOOK_URL, '/') . '/' . env('P_n8n_LMS_agent_id');
            } elseif ($chatbotRequestFrom === 'general') {
                $N8N_WEBHOOK_URL = rtrim($N8N_WEBHOOK_URL, '/') . '/' . env('P_n8n_Gen_agent_id');
            }

            // 1. Validate N8N webhook URL is configured
            if (empty($N8N_WEBHOOK_URL)) {
                Log::error('N8N webhook URL not configured');
                return response()->json([
                    'error' => 'Chatbot service is not configured. Please contact administrator.'
                ], 500);
            }

            // Extract agent_ID from webhook URL (last part after splitting by '/')
            $urlParts = explode('/', rtrim($N8N_WEBHOOK_URL, '/'));
            $agent_ID = end($urlParts);

            // 2. Validate input and trim
            // Validation moved up to handle logic before URL construction if needed, 
            // but we already validated 'user_question' above.
            // Keeping variable assignment clean.

            $userQuestion = trim($request->input('user_question'));
            $inputTokens = $this->countTokens($userQuestion);

            Log::info('N8N Chatbot Request', [
                'user_question' => $userQuestion,
                'webhook_url' => $N8N_WEBHOOK_URL,
                'agent_ID' => $agent_ID
            ]);

            // 3. Call n8n Cloud Webhook with proper timeout and retry
            // n8n expects: Content-Type: application/json and body with full request payload
            $payload = $request->only(['user_question', 'course_slug', 'chatbot_request_from','session_id']);

            Log::debug('N8N payload prepared', ['payload' => $payload, 'webhook_url' => $N8N_WEBHOOK_URL]);

            $n8nResponse = Http::timeout(60)
                ->connectTimeout(15)
                ->retry(2, 1000)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'text/plain, application/json'
                ])
                ->post($N8N_WEBHOOK_URL, $payload);

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

            $text = null;
            $recommend_question = null;

            // Check if response is plain text (n8n "Respond to Webhook" with TEXT option)
            if (stripos($contentType, 'text/plain') !== false || stripos($contentType, 'text/html') !== false) {
                // Response is plain text - treat as the `text` field
                $text = trim($responseBody);

                Log::info('N8N Chatbot Response (Plain Text)', [
                    'user_question' => $userQuestion,
                    'content_type' => $contentType,
                    'response_length' => strlen((string)$text)
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

                    // Helper: recursively search nested arrays/objects for a key
                    $findKey = function ($haystack, $needle) use (&$findKey) {
                        if ($haystack === null) return null;
                        if (is_array($haystack)) {
                            // associative or numeric array
                            foreach ($haystack as $k => $v) {
                                if ($k === $needle) {
                                    return $v;
                                }
                                if (is_array($v) || is_object($v)) {
                                    $found = $findKey($v, $needle);
                                    if ($found !== null) return $found;
                                }
                            }
                        } elseif (is_object($haystack)) {
                            foreach (get_object_vars($haystack) as $k => $v) {
                                if ($k === $needle) {
                                    return $v;
                                }
                                if (is_array($v) || is_object($v)) {
                                    $found = $findKey($v, $needle);
                                    if ($found !== null) return $found;
                                }
                            }
                        }
                        return null;
                    };

                    // Extract expected keys `text` and `recommend_question` from anywhere in the response
                    $maybeText = $findKey($n8nData, 'text');
                    if ($maybeText !== null) {
                        $text = is_string($maybeText) ? $maybeText : (string)$maybeText;
                    }

                    // Search for recommend_question (correct spelling) or recommand_question (legacy spelling)
                    $maybeRec = $findKey($n8nData, 'recommend_question');
                    if ($maybeRec === null) {
                        // Fallback to legacy spelling
                        $maybeRec = $findKey($n8nData, 'recommand_question');
                    }
                    if ($maybeRec !== null) {
                        // Handle recommend_question as array or string
                        $recommand_question = is_array($maybeRec) ? $maybeRec : (string)$maybeRec;
                    }

                    // Fallbacks: check common locations
                    if ($text === null && isset($n8nData['output'])) {
                        // output may be a string or an object
                        if (is_string($n8nData['output'])) {
                            $text = $n8nData['output'];
                        } else {
                            $maybe = $findKey($n8nData['output'], 'text');
                            if ($maybe !== null) $text = is_string($maybe) ? $maybe : (string)$maybe;
                        }
                    }

                    // Fallback for recommend_question in output object
                    if ($recommand_question === null && isset($n8nData['output'])) {
                        $maybe = $findKey($n8nData['output'], 'recommend_question');
                        if ($maybe === null) {
                            // Try legacy spelling
                            $maybe = $findKey($n8nData['output'], 'recommand_question');
                        }
                        if ($maybe !== null) {
                            $recommand_question = is_array($maybe) ? $maybe : (string)$maybe;
                        }
                    }

                    // Backwards compatibility: if `text` missing but a single string value exists, use it
                    if ($text === null && is_array($n8nData) && count($n8nData) === 1) {
                        $firstValue = reset($n8nData);
                        if (is_string($firstValue)) {
                            $text = $firstValue;
                        }
                    }
                }
            }

            // Do not fail if `text` or `recommand_question` are null — continue and return them as-is.
            if ($text === null && $recommand_question === null) {
                Log::warning('N8N response missing both `text` and `recommand_question` keys', [
                    'user_question' => $userQuestion,
                    'content_type' => $contentType,
                    'response_body_preview' => substr($responseBody, 0, 200)
                ]);
            }

            // Calculate metrics (use `text` for token counting)
            $outputTokens = $this->countTokens($text);
            $totalExecutionTime = round(microtime(true) - $startTime, 2);

            // Prepare logging data
            $logData = [
                'timestamp' => Carbon::now()->toDateTimeString(),
                'chat_intention' => 'course_content',
                'agent_ID' => $agent_ID,
                'user_query' => $userQuestion,
                'input_tokens' => $inputTokens,
                'Output_text' => $text,
                'recommand_question' => $recommand_question,
                'output_tokens' => $outputTokens,
                'total_execution_time' => $totalExecutionTime
            ];

            // Log to Laravel logs
            Log::info('Chatbot Interaction Logs', $logData);

            // Append to CSV file
            $this->appendToCsv($logData);
            // Append to wandb - enabled to record traces in Weave/W&B
            // $this->logToWandbWeave($logData);

            // 7. Return final response to frontend — only the requested keys
            return response()->json([
                'user_question' => $userQuestion,
                'LLM_text_reply' => $text,
                'recommend_question' => $recommand_question
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
