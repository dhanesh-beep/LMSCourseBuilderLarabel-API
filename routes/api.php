<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PdfExtractController;
use App\Http\Controllers\UserLearningChatbotController;
use App\Http\Controllers\ResponseController;
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

# User Learning Chatbot - Flowise
Route::post('/userQueryReply', [UserLearningChatbotController::class, 'userQueryReply']);

# User Learning Chatbot - n8n
Route::post('/chatbot', [UserLearningChatbotController::class, 'n8nChatbot']);

# Pinecone - Extract Course Text form json file
Route::post('/pinecone/extract-course-text', [PineconeController::class, 'extractCourseText']);

