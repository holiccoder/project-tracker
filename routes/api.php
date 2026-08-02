<?php

use App\Http\Controllers\Api\ContractController;
use App\Http\Controllers\Api\DevLogController;
use App\Http\Controllers\Api\IssueController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Middleware\EnsureApiToken;
use Illuminate\Support\Facades\Route;

Route::middleware(EnsureApiToken::class)->group(function () {
    Route::prefix('dev-logs')->group(function () {
        Route::get('/', [DevLogController::class, 'index']);
        Route::post('/', [DevLogController::class, 'store']);
    });

    Route::apiResource('projects', ProjectController::class);

    Route::apiResource('tasks', TaskController::class);
    Route::patch('tasks/{task}/status', [TaskController::class, 'updateStatus']);
    Route::get('tasks/{task}/attachments/{path}', [TaskController::class, 'downloadAttachment'])
        ->where('path', '.*');

    Route::apiResource('issues', IssueController::class);
    Route::patch('issues/{issue}/status', [IssueController::class, 'updateStatus']);
    Route::get('issues/{issue}/attachment', [IssueController::class, 'downloadAttachment']);

    Route::apiResource('contracts', ContractController::class);
    Route::get('contracts/{contract}/download', [ContractController::class, 'download']);
});
