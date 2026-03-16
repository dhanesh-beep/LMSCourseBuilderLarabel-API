<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PdfExtractController;
use App\Http\Controllers\UserLearningChatbotController;
use App\Http\Controllers\ResponseController;
use App\Http\Controllers\HSE_data_upsert;
use App\Http\Controllers\PineconeController;

# Course and Quiz Builder
Route::post('/extract', [PdfExtractController::class, 'chatbotCourseBuilder']);

# Pinecone
Route::get('/pinecone/init', [PineconeController::class, 'initIndex']);
Route::post('/pinecone/upsert', [PineconeController::class, 'upsert']);
Route::post('/pinecone/update', [PineconeController::class, 'update']);

// Route::post('/pinecone/delete/ids', [PineconeController::class, 'deleteByIds']);
Route::post('/pinecone/delete/course', [PineconeController::class, 'deleteByCourse']);
Route::post('/pinecone/query', [PineconeController::class, 'query']);

# User Learning Chatbot - n8n
Route::post('/chatbot', [UserLearningChatbotController::class, 'n8nChatbot']);

# Pinecone - Extract Course Text form json file
// Route::post('/pinecone/extract-course-text', [PineconeController::class, 'extractCourseText']);

# Auto HSE data Upsert API
Route::post('/pinecone/auto-hsedata-upsert', [UserLearningChatbotController::class, 'n8nChatbot']);

# Extract HSE Data from Web
Route::post('/hse/pipeline', [HSE_data_upsert::class, 'runFullPipeline']);    

// Route::get('/hse/extract-hse-data', [HSE_data_upsert::class, 'extractHSEData']);       
// Route::get('/hse/extract', [HSE_data_upsert::class, 'extractHSEData']);
// Route::post('/hse/upsert', [HSE_data_upsert::class, 'upsertToPinecone']);
// Route::post('/hse/init-index', [HSE_data_upsert::class, 'initIndex']);
