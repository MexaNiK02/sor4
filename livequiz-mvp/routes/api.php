<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ParticipantController;
use App\Http\Controllers\Api\QuestionImageController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Api\SessionController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => ['ok' => true, 'name' => config('app.name')]);

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/participant-auth/register', [AuthController::class, 'registerParticipant']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('api.token')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/participant/history', [AuthController::class, 'participantHistory']);

    Route::apiResource('quizzes', QuizController::class);
    Route::post('/question-images', [QuestionImageController::class, 'store']);
    Route::get('/quizzes/{quiz}/sessions', [QuizController::class, 'sessions']);
    Route::post('/quizzes/{quiz}/sessions', [QuizController::class, 'startSession']);

    Route::get('/sessions/{code}', [SessionController::class, 'showByCode']);
    Route::post('/sessions/{session}/start', [SessionController::class, 'start']);
    Route::post('/sessions/{session}/advance', [SessionController::class, 'advance']);
    Route::post('/sessions/{session}/finish', [SessionController::class, 'finish']);
    Route::get('/sessions/{session}/leaderboard', [SessionController::class, 'leaderboard']);
    Route::get('/sessions/{session}/answer-stats', [SessionController::class, 'answerStats']);
    Route::get('/sessions/{session}/export.csv', [SessionController::class, 'exportCsv']);
});

Route::post('/sessions/{code}/join', [SessionController::class, 'join']);

Route::get('/participants/{participant}/status', [ParticipantController::class, 'status']);
Route::post('/participants/{participant}/answers', [ParticipantController::class, 'answer']);
Route::get('/participants/{participant}/result', [ParticipantController::class, 'result']);
