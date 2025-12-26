<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PdfExtractController;
use App\Http\Controllers\UserLearningChatbotController;
use App\Http\Controllers\ResponseController;
use App\Http\Controllers\PineconeController;

Route::get('/pinecone/init', [PineconeController::class, 'initIndex']);
Route::post('/pinecone/upsert', [PineconeController::class, 'upsert']);
Route::post('/pinecone/update', [PineconeController::class, 'update']);
Route::post('/pinecone/delete/ids', [PineconeController::class, 'deleteByIds']);
Route::post('/pinecone/delete/course', [PineconeController::class, 'deleteByCourse']);
Route::post('/pinecone/query', [PineconeController::class, 'query']);

Route::post('/extract', [PdfExtractController::class, 'chatbotCourseBuilder']);
Route::post('/userQueryReply', [UserLearningChatbotController::class, 'userQueryReply']);

Route::post('/chatbot', [UserLearningChatbotController::class, 'n8nChatbot']);
