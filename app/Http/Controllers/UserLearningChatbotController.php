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
                    'requested_from',
                    'session_id',
                    'user_query',
                    'input_tokens',
                    'Output_text',
                    'output_tokens',
                    'recommand_question',
                    'chunks',
                    'source_type',
                    'confidence',
                    'total_execution_time',
                    'status'
                ]);
            }

            // Write the data row
            fputcsv($handle, [
                $data['timestamp'],
                $data['chat_intention'],
                $data['agent_ID'],
                $data['requested_from'],
                $data['session_id'],
                $data['user_query'],
                $data['input_tokens'],
                $data['Output_text'],
                $data['output_tokens'],
                is_array($data['recommand_question']) ? json_encode($data['recommand_question'], JSON_UNESCAPED_UNICODE) : $data['recommand_question'],
                is_array($data['chunks']) ? json_encode($data['chunks'], JSON_UNESCAPED_UNICODE) : $data['chunks'],
                $data['source_type'],
                $data['confidence'],
                $data['total_execution_time'],
                $data['status']
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
            // Use new n8n staging agent endpoint
            $N8N_WEBHOOK_URL = 'https://n8n.stagingapp.cloudq.io/webhook/' . env('Staging_n8n_LMS_agent_id');

            // Validate input
            $request->validate([
                'user_question' => 'required|string|min:1',
                'session_id' => 'required|string',
            ]);

            $userQuestion = trim($request->input('user_question'));
            $sessionId = trim($request->input('session_id'));
            $inputTokens = $this->countTokens($userQuestion);

            Log::info('N8N Chatbot Request', [
                'user_question' => $userQuestion,
                'webhook_url' => $N8N_WEBHOOK_URL,
                'session_id' => $sessionId
            ]);

            // Prepare payload for new API
            $payload = [
                'user_question' => $userQuestion,
                'session_id' => $sessionId
            ];

            Log::debug('N8N payload prepared', ['payload' => $payload, 'webhook_url' => $N8N_WEBHOOK_URL]);

            $n8nResponse = Http::timeout(60)
                ->connectTimeout(15)
                ->retry(2, 1000)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ])
                ->post($N8N_WEBHOOK_URL, $payload);

            if (!$n8nResponse->successful()) {
                $statusCode = $n8nResponse->status();
                $responseBody = $n8nResponse->body();
                Log::error('N8N API call failed', [
                    'status' => $statusCode,
                    'response_body' => $responseBody,
                    'user_question' => $userQuestion,
                    'webhook_url' => $N8N_WEBHOOK_URL
                ]);
                return response()->json([
                    'error' => 'Failed to get response from chatbot service',
                    'message' => $statusCode >= 500 
                        ? 'Chatbot service is temporarily unavailable. Please try again later.'
                        : 'Invalid response from chatbot service.',
                    'status_code' => $statusCode,
                    'details' => config('app.debug') ? $responseBody : null
                ], $statusCode >= 500 ? 503 : $statusCode);
            }

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

            // Parse new response structure
            $text = null;
            $recommend_question = null;
            $source_type = null;
            $confidence = null;
            $top_3_chunks = null;

            $n8nData = null;
            try {
                $n8nData = $n8nResponse->json();
            } catch (\Exception $jsonException) {
                Log::warning('N8N JSON parse failed', [
                    'error' => $jsonException->getMessage(),
                    'content_type' => $contentType,
                    'user_question' => $userQuestion
                ]);
            }

            // n8n returns an array with one object containing 'output'
            if (is_array($n8nData) && isset($n8nData[0]['output'])) {
                $output = $n8nData[0]['output'];
                $text = $output['text'] ?? null;
                $recommend_question = $output['recommend_question'] ?? null;
                $source_type = $output['source_type'] ?? null;
                $confidence = $output['confidence'] ?? null;
                $top_3_chunks = $output['top_3_chunks'] ?? null;
            } else {
                Log::error('Unexpected n8n response structure', [
                    'response' => $n8nData,
                    'user_question' => $userQuestion
                ]);
            }

            // Calculate metrics (use `text` for token counting)
            $outputTokens = $this->countTokens($text);
            $totalExecutionTime = round(microtime(true) - $startTime, 2);

            // Prepare logging data
            $logData = [
                'timestamp' => Carbon::now()->toDateTimeString(),
                'chat_intention' => 'course_content',
                'agent_ID' => env('Staging_n8n_LMS_agent_id'),
                'requested_from'   => 'Staging_Server',
                'session_id' => $sessionId,            
                'user_query' => $userQuestion,
                'input_tokens' => $inputTokens,
                'Output_text' => $text,
                'output_tokens' => $outputTokens,
                'recommand_question' => $recommend_question,
                 'chunks' => $top_3_chunks,
                'source_type'=> $source_type,
                'confidence'=> $confidence,
                'total_execution_time' => $totalExecutionTime,
                'status' => 'ok',
            ];

            Log::info('Chatbot Interaction Logs', $logData);

            // Return new response structure
            return response()->json([
                'user_question' => $userQuestion,
                'LLM_text_reply' => $text,
                'recommend_question' => $recommend_question,
                'source_type' => $source_type,
                'confidence' => $confidence,
                'chunks' => $top_3_chunks ?? 'Chunks under processing'
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
