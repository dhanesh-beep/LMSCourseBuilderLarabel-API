<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ResponseController;
use Illuminate\Http\Request;
use Smalot\PdfParser\Parser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
// use App\Models\Quiz;
// use App\Models\QuizQuestion;
// use App\Models\QuizQuestionOption;
 
class PdfExtractController extends ResponseController

{     
/****************************************START CHATBOT COURSE BUILDER END POINTS****************************************/
    private string $wandbApiKey = '9a2dd71fea975e82e9f4efcf5cabe5ded3b52326';
    private string $wandbProject = 'Flowise_LLM_Course_Builder_Eval_QUIZ';     

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

            $metrics['api_key'] = $this->wandbApiKey;
            $metrics['project_name'] = $this->wandbProject;
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
            //$chatflowId = '650969ed-b4b4-4e57-b74d-a87c7520c846';
            $chatflowId1 = 'f3d34c63-1cc5-4ef4-a988-0815329a1eaa';
            $apiHost = "https://cloud.flowiseai.com";

            $response = Http::timeout(900)
                ->connectTimeout(60)
                ->post("$apiHost/api/v1/prediction/$chatflowId1", [
                    'question' => $cleaned,
                ]);
                
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

            $responseData = $quiz['data']['quizzes'];  // quizzes array

            // // $quizTitles = [];
            // $quizIds = [];
            // $ADD_QUIZ_ID_NUMBER = 0;
            // foreach ($responseData as $index => $data) {
            //     // Create the quiz
            //     $quiz = Quiz::create([
            //         'title'         => $data['title'],
            //         'passing_score' => $data['passing_score'] ?? 0,
            //         'description'   => $data['description'] ?? null,
            //         'author'        => $data['author'] ?? null,
            //     ]);

            //     // Loop through questions
            //     foreach ($data['questions'] as $q) {
            //         $question = QuizQuestion::create([
            //             'quiz_id'        => $quiz->id,
            //             'question_title' => $q['question_title'],
            //             'question_type'  => $q['question_type'],
            //             'question_order' => $q['question_order']
            //         ]);

            //         // Loop through options
            //         foreach ($q['options'] as $opt) {
            //             QuizQuestionOption::create([
            //                 'question_id' => $question->id,
            //                 'option_text' => $opt['option_text'],
            //                 'is_correct'  => $opt['is_correct'],
            //             ]);
            //         }
            //     }

            //     if ($index == 0)
            //         $ADD_QUIZ_ID_NUMBER = $quiz->id;
            //     // collect each quiz title
            //     $quizTitles[] = $quiz['title'];
            //     // collect each quiz id
            //     $quizIds[] = $quiz->id;
            // }

            // ===============================================
            // QUIZ ANALYTICS + QUIZ TOKEN COUNT
            // ===============================================

            // flatten text for token counting
            $quizTextAccumulator = "";

            // result containers
            $quizCounts      = count($responseData);     // number of quizzes
            $quizIds         = [];
            $questionCounts  = [];  // per-quiz question count
            $totalTypeCount  = [ "single" => 0, "multiple" => 0 ];  // aggregated

            $Quiz_details = [
                "quiz_id"             => [],
                "question_count"      => [],
                "total_question_type" => [
                    "single"   => 0,
                    "multiple" => 0
                ]
            ];

            foreach ($responseData as $qz) {

                $quizIds[] = $qz['id'];

                // question count per quiz
                $qc = count($qz['questions']);
                $questionCounts[] = $qc;

                // collect text for token calculation
                $quizTextAccumulator .= $qz['title'] . " ";
                $quizTextAccumulator .= strip_tags($qz['description']) . " ";

                foreach ($qz['questions'] as $qs) {

                    // accumulate question text
                    $quizTextAccumulator .= $qs['question_title'] . " ";

                    // count question type
                    if ($qs['question_type'] === "single") {
                        $totalTypeCount["single"]++;
                    } elseif ($qs['question_type'] === "multiple") {
                        $totalTypeCount["multiple"]++;
                    }

                    // accumulate options text
                    foreach ($qs['options'] as $op) {
                        $quizTextAccumulator .= $op['option_text'] . " ";
                    }
                }
            }

            // -------------------------------
            // COUNT QUIZ TOKENS
            // -------------------------------
            $Count_quiz_token = $this->countTokens($quizTextAccumulator);

            // LOGGING
            Log::info("QUIZ_ANALYSIS", [
                "quiz_counts"        => $quizCounts,
                "quiz_ids"           => $quizIds,
                "question_counts"    => $questionCounts,
                "total_type_count"   => $totalTypeCount,
                "Count_quiz_token"   => $Count_quiz_token
            ]);

            $ADD_QUIZ_ID_NUMBER= $quizCounts;

            //==================================Dynamic Start Quiz ID ADDTION Logic=================

            $start_quiz_id_prompt = "END of user Text.\n\n\n ADD_QUIZ_ID_number={$ADD_QUIZ_ID_NUMBER}";
            $cleaned_plus_start_quiz_id = $cleaned . "\n\n" . $start_quiz_id_prompt;

            //===================================================================================================================
            // 2🧩 Flowise Call- for Create Course
            //$chatflowId = '0ca67919-d561-4558-993c-0cc269ca19b6';
            $chatflowId = '9b692563-f28b-4e62-bd9f-58c080d7014e';
            $apiHost = "https://cloud.flowiseai.com";

            // $startTime = microtime(true);
            // $flowiseStart = Carbon::now();

            $response = Http::timeout(900)
                ->connectTimeout(60)
                ->post("$apiHost/api/v1/prediction/$chatflowId", [
                    'question' => $cleaned_plus_start_quiz_id,
                ]);

            // $flowiseEnd = Carbon::now();
            $duration = round(microtime(true) - $startTime, 2);

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

            #🪶 Prepare metrics
            $metrics = [
                'chatflow_id' => $chatflowId,
                'timestamp' => now()->toDateTimeString(),
                'pdf_file_name' => $pdfFileName,
                'Course_title' => $courseTitle,
                'duration_sec' => $duration,
                'PDF_pages_Counts' => $pageCount,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens+$Count_quiz_token,
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
                #Performance
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
                'quiz'=> $quiz,
                'body' => $course,
                'meta' => $metrics,
            ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            Log::error('Flowise API Exception', ['message' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    /****************************************END CHATBOT COURSE BUILDER END POINTS****************************************/     
}







// namespace App\Http\Controllers;

// use App\Http\Controllers\ResponseController;
// use Illuminate\Http\Request;
// use Smalot\PdfParser\Parser;
// use Illuminate\Http\JsonResponse;
// use Illuminate\Support\Facades\Http;
// use Illuminate\Support\Facades\Log;
// use Carbon\Carbon;
// // use App\Models\Quiz;
// // use App\Models\QuizQuestion;
// // use App\Models\QuizQuestionOption;
 
// class PdfExtractController extends ResponseController

// {     
// /****************************************START CHATBOT COURSE BUILDER END POINTS****************************************/
//     private string $wandbApiKey = '9a2dd71fea975e82e9f4efcf5cabe5ded3b52326';
//     private string $wandbProject = 'Flowise_LLM_Course_Builder_Eval_QUIZ';      # 'Flowise_LLM_Course_Builder';

//     private function cleanText($text)
//     {
//         $text = preg_replace('/\s+/', ' ', $text);
//         $text = preg_replace('/\s+([.,!?;:])/', '$1', $text);
//         return trim($text);
//     }

//     private function countTokens($text)
//     {
//         if (!$text) return 0;
//         $charCount = mb_strlen($text);
//         return (int)ceil($charCount / 4);
//     }

//     private function logToCSV(array $metrics)
//     {
//         $csvFile = base_path('Course_Builder_LLM_Analysis.csv');
//         $isNew = !file_exists($csvFile);

//         $fp = fopen($csvFile, 'a');
//         if ($isNew) {
//             fputcsv($fp, array_keys($metrics));
//         }
//         fputcsv($fp, array_values($metrics));
//         fclose($fp);

//         Log::info('✅ Flowise metrics logged to CSV', $metrics);
//     }

//     private function logToWandb(array $metrics)
//     {
//         try {
//             $python = 'python';
//             $scriptPath = 'D:\\LMS Chatbot\\pdf-extractor-api\\wandb_logger.py';

//             $metrics['api_key'] = $this->wandbApiKey;
//             $metrics['project_name'] = $this->wandbProject;
//             $metrics['run_name'] = 'Flowise_v0_' . now()->format('Y_m_d_H_i_s');
//             $metrics['csv_path'] = base_path('Course_Builder_LLM_Analysis.csv');

//             $cmd = "$python \"$scriptPath\"";
//             $process = proc_open(
//                 $cmd,
//                 [
//                     0 => ['pipe', 'r'],
//                     1 => ['pipe', 'w'],
//                     2 => ['pipe', 'w'],
//                 ],
//                 $pipes
//             );

//             if (is_resource($process)) {
//                 fwrite($pipes[0], json_encode($metrics));
//                 fclose($pipes[0]);

//                 stream_set_blocking($pipes[1], false);
//                 stream_set_blocking($pipes[2], false);

//                 $output = stream_get_contents($pipes[1]);
//                 $error  = stream_get_contents($pipes[2]);

//                 fclose($pipes[1]);
//                 fclose($pipes[2]);

//                 proc_close($process);

//                 if (!empty($error)) {
//                     Log::error("W&B Python error: $error");
//                 } else {
//                     Log::info("W&B Python output: $output");
//                 }
//             } else {
//                 Log::error("Failed to start W&B Python process");
//             }
//         } catch (\Exception $e) {
//             Log::error("W&B logging failed: " . $e->getMessage());
//         }
//     }

//     public function chatbotCourseBuilder(Request $request)
//     {
//         ini_set('max_execution_time', 900);

//         try {
//             if (!$request->hasFile('pdf_file')) {
//                 return response()->json(['error' => 'Please upload a valid PDF file'], 400);
//             }

//             $file = $request->file('pdf_file');
//             if (!$file->isValid() || strtolower($file->getClientOriginalExtension()) !== 'pdf') {
//                 return response()->json(['error' => 'Invalid or non-PDF file'], 400);
//             }

//             //  Extract PDF
//             $parser = new Parser();
//             $pdf = $parser->parseFile($file->getPathname());
//             $pages = $pdf->getPages();
//             $pageCount = count($pages);

//             // 🖼 Count images from PDF content
//             $rawContent = file_get_contents($file->getPathname());
//             preg_match_all('/\/Subtype\s*\/Image/', $rawContent, $matches);
//             $pdfImageCount = count($matches[0]);

//             // 🧹 Clean text
//             $text = trim($pdf->getText());
//             $cleaned = $this->cleanText($text);
//             $inputTokens = $this->countTokens($cleaned);
//             $startTime = microtime(true);
//             $flowiseStart = Carbon::now();

//             //-------------------------------------------------------------------------------------------
//             // 1- 🧩 Flowise Call- for Quiz Creation
//             //$chatflowId = '650969ed-b4b4-4e57-b74d-a87c7520c846';
//             $chatflowId1 = 'f3d34c63-1cc5-4ef4-a988-0815329a1eaa';
//             $apiHost = "https://cloud.flowiseai.com";

//             $response = Http::timeout(900)
//                 ->connectTimeout(60)
//                 ->post("$apiHost/api/v1/prediction/$chatflowId1", [
//                     'question' => $cleaned,
//                 ]);
//             #$flowiseEnd = Carbon::now();

//             if ($response->failed()) {
//                 return response()->json([
//                     'error' => 'Flowise API call failed',
//                     'details' => $response->body(),
//                 ], $response->status());
//             }

//             $flowiseData = json_decode($response->body(), true);
//             $textResponse = $flowiseData['text'] ?? $response->body();
//             $decoded = json_decode($textResponse, true);

//             // keep full quiz_answer array (if present) for returning the full JSON
//             $quizFull = $decoded['quiz_answer'] ?? null;
//             // keep existing $quiz for downstream processing (first element or fallback)
//             $quiz = $decoded['quiz_answer'][0] ?? $decoded ?? $textResponse;

//             $responseData = $quiz['data']['quizzes'];  // quizzes array

//             // $quizTitles = [];
//             $quizIds = [];
//             $ADD_QUIZ_ID_NUMBER = 0;
//             foreach ($responseData as $index => $data) {
//                 // Create the quiz
//                 $quiz = Quiz::create([
//                     'title'         => $data['title'],
//                     'passing_score' => $data['passing_score'] ?? 0,
//                     'description'   => $data['description'] ?? null,
//                     'author'        => $data['author'] ?? null,
//                 ]);

//                 // Loop through questions
//                 foreach ($data['questions'] as $q) {
//                     $question = QuizQuestion::create([
//                         'quiz_id'        => $quiz->id,
//                         'question_title' => $q['question_title'],
//                         'question_type'  => $q['question_type'],
//                         'question_order' => $q['question_order']
//                     ]);

//                     // Loop through options
//                     foreach ($q['options'] as $opt) {
//                         QuizQuestionOption::create([
//                             'question_id' => $question->id,
//                             'option_text' => $opt['option_text'],
//                             'is_correct'  => $opt['is_correct'],
//                         ]);
//                     }
//                 }

//                 if ($index == 0)
//                     $ADD_QUIZ_ID_NUMBER = $quiz->id;
//                 // collect each quiz title
//                 $quizTitles[] = $quiz['title'];
//                 // collect each quiz id
//                 $quizIds[] = $quiz->id;
//             }

//             $Quiz_id = [
//                 "quiz_title" => $quizTitles,
//                 "quiz_ids"   => $quizIds
//             ];

//             // // Log it or send to W&B
//             Log::info("ADD_QUIZ_ID_number >>>>>>>>>>>>>>>>>>>>>>>>>".$ADD_QUIZ_ID_NUMBER);
//             Log::info("Quiz ID Tracking >>>>>>>>>>>>>>>>>>>>>>>>>" , $Quiz_id);


//             //==================================Dynamic Start Quiz ID ADDTION Logic=================

//             $start_quiz_id_prompt = "END of user Text.\n\n(start from next new line)\n ADD_QUIZ_ID_number={$ADD_QUIZ_ID_NUMBER}";
//             $cleaned_plus_start_quiz_id = $cleaned . "\n\n" . $start_quiz_id_prompt;

//             // ===================================================================================================================
//             // 2🧩 Flowise Call- for Create Course
//             //$chatflowId = '0ca67919-d561-4558-993c-0cc269ca19b6';
//             $chatflowId = '9b692563-f28b-4e62-bd9f-58c080d7014e';
//             $apiHost = "https://cloud.flowiseai.com";

//             // $startTime = microtime(true);
//             // $flowiseStart = Carbon::now();

//             $cleaned_plus_quiz_id = $cleaned;

//             $response = Http::timeout(900)
//                 ->connectTimeout(60)
//                 ->post("$apiHost/api/v1/prediction/$chatflowId", [
//                     'question' => $cleaned_plus_start_quiz_id,
//                 ]);

//             // $flowiseEnd = Carbon::now();
//             $duration = round(microtime(true) - $startTime, 2);

//             if ($response->failed()) {
//                 return response()->json([
//                     'error' => 'Flowise API call failed',
//                     'details' => $response->body(),
//                 ], $response->status());
//             }

//             $flowiseData = json_decode($response->body(), true);
//             $textResponse = $flowiseData['text'] ?? $response->body();
//             $decoded = json_decode($textResponse, true);

//             $course = $decoded['answer'][0] ?? $decoded ?? $textResponse;
//             $outputTokens = $this->countTokens(json_encode($course));

//             // Safely extract evaluation_matrix from several possible response shapes
//             $evaluation_matrix = [];

//             // 1) top-level 'evaluation_matrix'
//             if (isset($decoded['evaluation_matrix']) && is_array($decoded['evaluation_matrix'])) {
//                 $evaluation_matrix = $decoded['evaluation_matrix'];
//             }

//             // 2) top-level 'performance_metrix' -> array with item that contains 'evaluation_matrix'
//             if (empty($evaluation_matrix) && isset($decoded['performance_metrix']) && is_array($decoded['performance_metrix'])) {
//                 $first = $decoded['performance_metrix'][0] ?? $decoded['performance_metrix'];
//                 if (is_array($first) && isset($first['evaluation_matrix']) && is_array($first['evaluation_matrix'])) {
//                     $evaluation_matrix = $first['evaluation_matrix'];
//                 } elseif (is_array($first)) {
//                     // sometimes the array element itself is the matrix
//                     $evaluation_matrix = $first;
//                 }
//             }

//             // 3) nested under 'course' (your sample)
//             if (empty($evaluation_matrix) && isset($decoded['course']) && is_array($decoded['course'])) {
//                 $c = $decoded['course'];
//                 if (isset($c['performance_metrix']) && is_array($c['performance_metrix'])) {
//                     $first = $c['performance_metrix'][0] ?? $c['performance_metrix'];
//                     if (is_array($first) && isset($first['evaluation_matrix']) && is_array($first['evaluation_matrix'])) {
//                         $evaluation_matrix = $first['evaluation_matrix'];
//                     } elseif (is_array($first)) {
//                         $evaluation_matrix = $first;
//                     }
//                 }
//                 if (empty($evaluation_matrix) && isset($c['evaluation_matrix']) && is_array($c['evaluation_matrix'])) {
//                     $evaluation_matrix = $c['evaluation_matrix'];
//                 }
//             }

//             // 4) nested under 'answer' -> answer[0]['performance_metrix']
//             if (empty($evaluation_matrix) && isset($decoded['answer']) && is_array($decoded['answer'])) {
//                 $a0 = $decoded['answer'][0] ?? null;
//                 if (is_array($a0)) {
//                     if (isset($a0['evaluation_matrix']) && is_array($a0['evaluation_matrix'])) {
//                         $evaluation_matrix = $a0['evaluation_matrix'];
//                     } elseif (isset($a0['performance_metrix']) && is_array($a0['performance_metrix'])) {
//                         $first = $a0['performance_metrix'][0] ?? $a0['performance_metrix'];
//                         if (is_array($first) && isset($first['evaluation_matrix']) && is_array($first['evaluation_matrix'])) {
//                             $evaluation_matrix = $first['evaluation_matrix'];
//                         } elseif (is_array($first)) {
//                             $evaluation_matrix = $first;
//                         }
//                     }
//                 }
//             }

//             // Ensure we have an array
//             if (!is_array($evaluation_matrix)) {
//                 $evaluation_matrix = [];
//             }

//             // evaluation_matrix values (use null default to avoid array-to-string conversions)
//             $grounding_score = $evaluation_matrix['grounding_score'] ?? null;
//             $completeness_score = $evaluation_matrix['completeness_score'] ?? null;
//             $response_length_balance = $evaluation_matrix['response_length_balance'] ?? null;
//             $context_token_overlap = $evaluation_matrix['context_token_overlap'] ?? null;
//             $overall_score = $evaluation_matrix['overall_score'] ?? null;
//             $justification = $evaluation_matrix['justification'] ?? 'No justification';

//             # Course Creation related Items calculation
//             $lessons = count($course['lessons'] ?? []);
//             $sections = collect($course['lessons'] ?? [])->sum(fn($l) => count($l['sections'] ?? []));
//             $widgets = collect($course['lessons'] ?? [])->sum(function ($lesson) {
//                 return collect($lesson['sections'] ?? [])->sum(function ($section) {
//                     return collect($section['rows'] ?? [])->sum(function ($row) {
//                         return collect($row['columns'] ?? [])->sum(function ($column) {
//                             return count($column['widgets'] ?? []);
//                         });
//                     });
//                 });
//             });

//             //  Count image widgets (from Flowise JSON)
//             $imageWidgets = collect($course['lessons'] ?? [])->sum(function ($lesson) {
//                 return collect($lesson['sections'] ?? [])->sum(function ($section) {
//                     return collect($section['rows'] ?? [])->sum(function ($row) {
//                         return collect($row['columns'] ?? [])->sum(function ($column) {
//                             return collect($column['widgets'] ?? [])
//                                 ->where('type', 'image')
//                                 ->count();
//                         });
//                     });
//                 });
//             });

//             $totalImageCount = $pdfImageCount + $imageWidgets;
//             $courseTitle = $course['title'] ?? 'Untitled Course';
//             // 🧾 Extract PDF file name (without extension)
//             $pdfFileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

//             // 🪶 Prepare metrics
//             $metrics = [
//                 'chatflow_id' => $chatflowId,
//                 'timestamp' => now()->toDateTimeString(),
//                 'pdf_file_name' => $pdfFileName,
//                 'Course_title' => $courseTitle,
//                 'duration_sec' => $duration,
//                 'PDF_pages_Counts' => $pageCount,
//                 'input_tokens' => $inputTokens,
//                 'output_tokens' => $outputTokens,
//                 'total_lessons' => $lessons,
//                 'total_sections' => $sections,
//                 'total_widgets' => $widgets,
//                 'Image_counts' => $totalImageCount,
//                 #Performance
//                 'grounding_score' => $grounding_score,
//                 'completeness_score' => $completeness_score,
//                 'response_length_balance' => $response_length_balance,
//                 'context_token_overlap' => $context_token_overlap,
//                 'overall_score' => $overall_score,
//                 'justification'  => $justification
//                 #quiz info
//                 //'total_quizzes' => count($quizIds),
//                 //'quiz_ids' => implode('|', $quizIds),

//             ];

//             $this->logToCSV($metrics);
//             $this->logToWandb($metrics);

//             return response()->json([
//                 'quiz'=> $quiz,
//                 'body' => $course,
//                 'meta' => $metrics,
//             ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
//         } catch (\Throwable $e) {
//             Log::error('Flowise API Exception', ['message' => $e->getMessage()]);
//             return response()->json(['error' => $e->getMessage()], 500);
//         }
//     }
//     /****************************************END CHATBOT COURSE BUILDER END POINTS****************************************/     
// }



