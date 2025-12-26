<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ResponseController;
use Illuminate\Http\Request;
use Smalot\PdfParser\Parser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\QuizQuestionOption;
 
class PdfExtractController extends ResponseController
{
/****************************************START CHATBOT COURSE BUILDER END POINTS****************************************/

    public function chatbotCourseBuilder(Request $request)
    {    
        ini_set('max_execution_time', 900);

        try {
            if (!$request->hasFile('pdf_file')) {
                return response()->json(['error' => 'Please upload a valid PDF file'], 400);
            }

            $file = $request->file('pdf_file');
            if (!$file->isValid() || strtolower($file->getClientOriginalExtension()) !== 'pdf') {
                return response()->json(['error' => 'Invalid or non-PDF file'], 400);
            }

            //  Extract PDF
            $parser = new Parser();
            $pdf = $parser->parseFile($file->getPathname());
            $pages = $pdf->getPages();
            $pageCount = count($pages);

            // 🖼 Count images from PDF content
            $rawContent = file_get_contents($file->getPathname());
            preg_match_all('/\/Subtype\s*\/Image/', $rawContent, $matches);
            $pdfImageCount = count($matches[0]);

            // 🧹 Clean text
            $text = trim($pdf->getText());
            $cleaned = $this->cleanText($text);
            $inputTokens = $this->countTokens($cleaned);
            $startTime = microtime(true);
            $flowiseStart = Carbon::now();

            //-------------------------------------------------------------------------------------------
            // 1- 🧩 Flowise Call- for Quiz Creation
            $chatflowId1 = env('FLOWISE_AGENTFLOW_ID_QUIZ');
            $apiHost = env('FLOWISE_API_HOST');

            $response = Http::timeout(env('FLOWISE_TIMEOUT', 900))
                ->connectTimeout(env('FLOWISE_CONNECT_TIMEOUT', 60))
                ->post("$apiHost/api/v1/prediction/$chatflowId1", [
                    'question' => $cleaned,
                ]);

            // $response = Http::timeout(900)
            //     ->connectTimeout(60)
            //     ->post("$apiHost/api/v1/prediction/$chatflowId1", [
            //         'question' => $cleaned,
            //     ]);
                
            #$flowiseEnd = Carbon::now();

            if ($response->failed()) {
                return response()->json([
                    'error' => 'Flowise API call failed',
                    'details' => $response->body(),
                ], $response->status());
            }

            $flowiseData = json_decode($response->body(), true);
            $textResponse = $flowiseData['text'] ?? $response->body();
            $decoded = json_decode($textResponse, true);

            // keep full quiz_answer array (if present) for returning the full JSON
            $quizFull = $decoded['quiz_answer'] ?? null;
            // keep existing $quiz for downstream processing (first element or fallback)
            $quiz = $decoded['quiz_answer'][0] ?? $decoded ?? $textResponse;
            $quiz1 = $quiz;

            $responseData1 = $quiz['data'];  // quizzes array
            $responseData = $quiz['data']['quizzes'];  // quizzes array

            $quizIds = [];
            $quizTitles      = [];
            $questionCounts  = [];                       // per-quiz question count
            $quizCounts = 0;                  // collect quiz titles as an array
            $ADD_QUIZ_ID_NUMBER = 0;
            $quizTextAccumulator = "";
            $totalTypeCount  = [ "single" => 0, "multiple" => 0 ];  // aggregated

            foreach ($responseData as $index => $data) {
                $quizCounts++;
                // Create the quiz
                $quiz = Quiz::create([
                    'title'         => $data['title'],
                    'passing_score' => $data['passing_score'] ?? 0,
                    'description'   => $data['description'] ?? null,
                    'author'        => $data['author'] ?? null,
                ]);

                $qc = count($data['questions']);
                $questionCounts[] = $qc;
                // collect text for token calculation
                $quizTextAccumulator .= $data['title'] . " ";
                $quizTextAccumulator .= strip_tags($data['description']) . " ";
                // Loop through questions
                foreach ($data['questions'] as $q) {
                    // accumulate question text
                    $quizTextAccumulator .= $q['question_title'] . " ";
                    // count question type
                    if ($q['question_type'] === "single") {
                        $totalTypeCount["single"]++;
                    } elseif ($q['question_type'] === "multiple") {
                        $totalTypeCount["multiple"]++;
                    }
                    $question = QuizQuestion::create([
                        'quiz_id'        => $quiz->id,
                        'question_title' => $q['question_title'],
                        'question_type'  => $q['question_type'],
                        'question_order' => $q['question_order']
                    ]);

                    // Loop through options
                    foreach ($q['options'] as $opt) {
                        $quizTextAccumulator .= $opt['option_text'] . " ";
                        QuizQuestionOption::create([
                            'question_id' => $question->id,
                            'option_text' => $opt['option_text'],
                            'is_correct'  => $opt['is_correct'],
                        ]);
                    }
                }

                $quizIds[] = $quiz->id;
                $quizTitles[] = $quiz->title;

                 if ($index == 0){
                    $ADD_QUIZ_ID_NUMBER = $quiz->id;
                }
            }

            // -------------------------------
            // COUNT QUIZ TOKENS
            // -------------------------------
            $Count_quiz_token = $this->countTokens($quizTextAccumulator);

            // ==========================================
            // 🎯 Extract Quiz Evaluation Matrix
            // ==========================================
            $quizEvaluation = $quiz1['quiz_evaluation_matrix'] ?? null;
            // Default values if matrix missing
            $q_grounding_score          = $quizEvaluation['grounding_score']          ?? 0;
            $q_accuracyScore            = $quizEvaluation['accuracy_score']           ?? 0;
            $q_contextTokenOverlap      = $quizEvaluation['context_token_overlap']    ?? 0;
            $q_response_length_balance  = $quizEvaluation['response_length_score']    ?? 0;
            $q_relevance_score          = $quizEvaluation['relevance_score']          ?? 0;
            $q_evaluationSummary        = $quizEvaluation['evaluation_summary']       ?? 'No summary available';

            // Example: Add this to your overall log payload
            // $matrixLogs['quiz_evaluation_matrix'] = $quizEvaluationMatrix;

            // LOGGING
            Log::info("QUIZ_ANALYSIS", [
                "quiz_counts"        => $quizCounts,
                "quiz_ids"           => $quizIds,
                "quiz_titles"        => $quizTitles,
                "question_counts"    => $questionCounts,
                "total_type_count"   => $totalTypeCount,
                "Count_quiz_token"   => $Count_quiz_token,
                "q_grounding_score"          => $q_grounding_score,
                "q_accuracyScore"           => $q_accuracyScore,
                "q_contextTokenOverlap"     => $q_contextTokenOverlap,
                "q_response_length_balance" => $q_response_length_balance,
                "q_relevance_score"         => $q_relevance_score,
                "q_evaluationSummary"      => $q_evaluationSummary,
            ]);

            //==================================Dynamic Start Quiz ID ADDTION Logic==========================================
            // $start_quiz_id_prompt = "END of user Text.\n\n\n Generate Total Lesson_Counts={$quizCounts} lessons for this course using the provided Lesson titles List ={$quizTitles} \n\n\n , also use the ADD_QUIZ_ID_number={$ADD_QUIZ_ID_NUMBER} to add the quiz ids to the lessons";
            // Before building the prompt
            $quizTitlesString = implode(' | ', $quizTitles);   // or json_encode($quizTitles)

            // Build the prompt safely
            $start_quiz_id_prompt = "END of user Text.\n\n\n "
                . "Generate Total Lesson_Counts={$quizCounts} lessons for this course using the provided "
                . "Lesson titles List ={$quizTitlesString} \n\n\n "
                . ", also use the ADD_QUIZ_ID_number={$ADD_QUIZ_ID_NUMBER} to add the quiz ids to the lessons";

            $cleaned_plus_start_quiz_id = $cleaned . "\n\n" . $start_quiz_id_prompt;

            //===================================================================================================================
            // 2🧩 Flowise Call- for Create Course
            $chatflowId2 = env('FLOWISE_AGENTFLOW_ID_COURSE');

            // $startTime = microtime(true);
            // $flowiseStart = Carbon::now();

            $response = Http::timeout(env('FLOWISE_TIMEOUT', 900))
                ->connectTimeout(env('FLOWISE_CONNECT_TIMEOUT', 60))
                ->post("$apiHost/api/v1/prediction/$chatflowId2", [
                    'question' => $cleaned_plus_start_quiz_id,
                ]);

            // $flowiseEnd = Carbon::now();

            if ($response->failed()) {
                return response()->json([
                    'error' => 'Flowise API call failed',
                    'details' => $response->body(),
                ], $response->status());
            }

            $flowiseData = json_decode($response->body(), true);
            $textResponse = $flowiseData['text'] ?? $response->body();
            $decoded = json_decode($textResponse, true);

            $course = $decoded['answer'][0] ?? $decoded ?? $textResponse;
            $outputTokens = $this->countTokens(json_encode($course));

            // Safely extract evaluation_matrix from several possible response shapes
            $evaluation_matrix = [];

            // 1) top-level 'evaluation_matrix'
            if (isset($decoded['evaluation_matrix']) && is_array($decoded['evaluation_matrix'])) {
                $evaluation_matrix = $decoded['evaluation_matrix'];
            }

            // 2) top-level 'performance_metrix' -> array with item that contains 'evaluation_matrix'
            if (empty($evaluation_matrix) && isset($decoded['performance_metrix']) && is_array($decoded['performance_metrix'])) {
                $first = $decoded['performance_metrix'][0] ?? $decoded['performance_metrix'];
                if (is_array($first) && isset($first['evaluation_matrix']) && is_array($first['evaluation_matrix'])) {
                    $evaluation_matrix = $first['evaluation_matrix'];
                } elseif (is_array($first)) {
                    // sometimes the array element itself is the matrix
                    $evaluation_matrix = $first;
                }
            }

            // 3) nested under 'course' (your sample)
            if (empty($evaluation_matrix) && isset($decoded['course']) && is_array($decoded['course'])) {
                $c = $decoded['course'];
                if (isset($c['performance_metrix']) && is_array($c['performance_metrix'])) {
                    $first = $c['performance_metrix'][0] ?? $c['performance_metrix'];
                    if (is_array($first) && isset($first['evaluation_matrix']) && is_array($first['evaluation_matrix'])) {
                        $evaluation_matrix = $first['evaluation_matrix'];
                    } elseif (is_array($first)) {
                        $evaluation_matrix = $first;
                    }
                }
                if (empty($evaluation_matrix) && isset($c['evaluation_matrix']) && is_array($c['evaluation_matrix'])) {
                    $evaluation_matrix = $c['evaluation_matrix'];
                }
            }

            // 4) nested under 'answer' -> answer[0]['performance_metrix']
            if (empty($evaluation_matrix) && isset($decoded['answer']) && is_array($decoded['answer'])) {
                $a0 = $decoded['answer'][0] ?? null;
                if (is_array($a0)) {
                    if (isset($a0['evaluation_matrix']) && is_array($a0['evaluation_matrix'])) {
                        $evaluation_matrix = $a0['evaluation_matrix'];
                    } elseif (isset($a0['performance_metrix']) && is_array($a0['performance_metrix'])) {
                        $first = $a0['performance_metrix'][0] ?? $a0['performance_metrix'];
                        if (is_array($first) && isset($first['evaluation_matrix']) && is_array($first['evaluation_matrix'])) {
                            $evaluation_matrix = $first['evaluation_matrix'];
                        } elseif (is_array($first)) {
                            $evaluation_matrix = $first;
                        }
                    }
                }
            }

            // Ensure we have an array
            if (!is_array($evaluation_matrix)) {
                $evaluation_matrix = [];
            }

            // evaluation_matrix values (use null default to avoid array-to-string conversions)
            $grounding_score = $evaluation_matrix['grounding_score'] ?? null;
            $completeness_score = $evaluation_matrix['completeness_score'] ?? null;
            $response_length_balance = $evaluation_matrix['response_length_balance'] ?? null;
            $context_token_overlap = $evaluation_matrix['context_token_overlap'] ?? null;
            $overall_score = $evaluation_matrix['overall_score'] ?? null;
            $justification = $evaluation_matrix['justification'] ?? 'No justification';

            # Course Creation related Items calculation
            $lessons = count($course['lessons'] ?? []);
            $sections = collect($course['lessons'] ?? [])->sum(fn($l) => count($l['sections'] ?? []));
            $widgets = collect($course['lessons'] ?? [])->sum(function ($lesson) {
                return collect($lesson['sections'] ?? [])->sum(function ($section) {
                    return collect($section['rows'] ?? [])->sum(function ($row) {
                        return collect($row['columns'] ?? [])->sum(function ($column) {
                            return count($column['widgets'] ?? []);
                        });
                    });
                });
            });

            //  Count image widgets (from Flowise JSON)
            $imageWidgets = collect($course['lessons'] ?? [])->sum(function ($lesson) {
                return collect($lesson['sections'] ?? [])->sum(function ($section) {
                    return collect($section['rows'] ?? [])->sum(function ($row) {
                        return collect($row['columns'] ?? [])->sum(function ($column) {
                            return collect($column['widgets'] ?? [])
                                ->where('type', 'image')
                                ->count();
                        });
                    });
                });
            });

            $totalImageCount = $pdfImageCount + $imageWidgets;
            $courseTitle = $course['title'] ?? 'Untitled Course';
            // 🧾 Extract PDF file name (without extension)
            $pdfFileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $duration = round(microtime(true) - $startTime, 2);

            #🪶 Prepare metrics
            $metrics = [
                'requested_from' => "Backend_API_Client",     //for Website/UI use - Website_AI_API_Client
                'timestamp' => now()->toDateTimeString(),
                'pdf_file_name' => $pdfFileName,
                'Course_title' => $courseTitle,
                'duration_sec' => $duration,
                'PDF_pages_Counts' => $pageCount,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens+$Count_quiz_token,
                'total_tokens' => $inputTokens + $outputTokens + $Count_quiz_token,
                'total_lessons' => $lessons,
                'total_sections' => $sections,
                'total_widgets' => $widgets,
                'Image_counts' => $totalImageCount,
                #quiz info
                'quiz_count' => $quizCounts,
                'quiz_ids' => implode('|', $quizIds),
                'quiz_question_counts' => implode('|', $questionCounts),   //   $questionCountList
                'quiz_single_count' => $totalTypeCount['single'],
                'quiz_multiple_count' => $totalTypeCount['multiple'],
                'quiz_titles' => $quizTitlesString,   // already a string
                #quiz performance
                'q_grounding_score' => $q_grounding_score,
                'q_accuracyScore' => $q_accuracyScore,
                'q_contextTokenOverlap' => $q_contextTokenOverlap,
                'q_response_length_balance' => $q_response_length_balance,
                'q_relevance_score' => $q_relevance_score,
                'q_evaluationSummary'  => $q_evaluationSummary,
                #Course Builder Performance
                'grounding_score' => $grounding_score,
                'completeness_score' => $completeness_score,
                'response_length_balance' => $response_length_balance,
                'context_token_overlap' => $context_token_overlap,
                'overall_score' => $overall_score,
                'justification'  => $justification,
            ];

            $this->logToCSV($metrics);
            $this->logToWandb($metrics);

            return response()->json([
                'quiz'=> $quiz1,     //     responseData1
                'body' => $course,
                'meta' => $metrics,
            ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            Log::error('Flowise API Exception', ['message' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function cleanText($text)
    {
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/\s+([.,!?;:])/', '$1', $text);
        return trim($text);
    }

    private function countTokens($text)
    {
        if (!$text) return 0;
        $charCount = mb_strlen($text);
        return (int)ceil($charCount / 4);
    }

    private function logToCSV(array $metrics)
    {
        $csvFile = base_path('Course_Builder_LLM_Analysis.csv');
        $isNew = !file_exists($csvFile);

        $fp = fopen($csvFile, 'a');
        if ($isNew) {
            fputcsv($fp, array_keys($metrics));
        }
        fputcsv($fp, array_values($metrics));
        fclose($fp);

        Log::info('✅ Flowise metrics logged to CSV', $metrics);
    }

    private function logToWandb(array $metrics)
    {
        try {
            $python = 'python';
            $scriptPath = 'D:\\LMS Chatbot\\pdf-extractor-api\\wandb_logger.py';

            $metrics['api_key'] = env('WANDB_API_KEY');
            $metrics['project_name'] = env('WANDB_PROJECT');
            $metrics['run_name'] = 'Flowise_v0_' . now()->format('Y_m_d_H_i_s');
            $metrics['csv_path'] = base_path('Course_Builder_LLM_Analysis.csv');

            $cmd = "$python \"$scriptPath\"";
            $process = proc_open(
                $cmd,
                [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ],
                $pipes
            );

            if (is_resource($process)) {
                fwrite($pipes[0], json_encode($metrics));
                fclose($pipes[0]);

                stream_set_blocking($pipes[1], false);
                stream_set_blocking($pipes[2], false);

                $output = stream_get_contents($pipes[1]);
                $error  = stream_get_contents($pipes[2]);

                fclose($pipes[1]);
                fclose($pipes[2]);

                proc_close($process);

                if (!empty($error)) {
                    Log::error("W&B Python error: $error");
                } else {
                    Log::info("W&B Python output: $output");
                }
            } else {
                Log::error("Failed to start W&B Python process");
            }
        } catch (\Exception $e) {
            Log::error("W&B logging failed: " . $e->getMessage());
        }
    }

    /****************************************END CHATBOT COURSE BUILDER END POINTS****************************************/
}   

