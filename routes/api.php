<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContractController;
use App\Http\Controllers\Api\DevLogController;
use App\Http\Controllers\Api\DevLogUpdateController;
use App\Http\Controllers\Api\IssueController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Middleware\EnsureApiToken;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [AuthController::class, 'login'])->name('api.auth.login');

Route::middleware(EnsureApiToken::class)->name('api.')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');

    Route::apiResource('accounts', AccountController::class);

    Route::prefix('dev-logs')->group(function () {
        Route::get('/', [DevLogController::class, 'index']);
        Route::post('/', [DevLogController::class, 'store']);
        Route::post('{devLog}/updates', [DevLogUpdateController::class, 'store'])
            ->name('dev-logs.updates.store');
        Route::patch('{devLog}/updates/{update}', [DevLogUpdateController::class, 'update'])
            ->scopeBindings()
            ->name('dev-logs.updates.update');
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
