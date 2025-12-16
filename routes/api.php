<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PdfExtractController;
use App\Http\Controllers\UserLearningChatbotController;
use App\Http\Controllers\ResponseController;

Route::post('/extract', [PdfExtractController::class, 'chatbotCourseBuilder']);
Route::post('/userQueryReply', [UserLearningChatbotController::class, 'userQueryReply']);

