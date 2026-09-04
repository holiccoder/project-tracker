<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContractController;
use App\Http\Controllers\Api\DevLogController;
use App\Http\Controllers\Api\DevLogUpdateController;
use App\Http\Controllers\Api\FormSchemaController;
use App\Http\Controllers\Api\IssueController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectInvitationController;
use App\Http\Controllers\Api\ProjectMemberController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TaskCommentController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserProjectController;
use App\Http\Middleware\EnsureApiToken;
use App\Models\Project;
use Illuminate\Support\Facades\Route;

Route::bind('project', fn (string $value): Project => Project::whereKey($value)->orWhere('slug', $value)->firstOrFail());

Route::post('auth/login', [AuthController::class, 'login'])->name('api.auth.login');

Route::middleware(EnsureApiToken::class)->name('api.')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::get('form-schemas', [FormSchemaController::class, 'index'])->name('form-schemas.index');

    Route::apiResource('users', UserController::class);
    Route::apiResource('accounts', AccountController::class);

    Route::apiResource('dev-logs', DevLogController::class);
    Route::apiResource('dev-log-updates', DevLogUpdateController::class)->except(['update']);
    Route::match(['put', 'patch'], 'dev-log-updates/{devLogUpdate}', [DevLogUpdateController::class, 'updateStandalone'])
        ->name('dev-log-updates.update');
    Route::prefix('dev-logs')->scopeBindings()->group(function () {
        Route::post('{devLog}/updates', [DevLogUpdateController::class, 'storeForLog'])
            ->name('dev-logs.updates.store');
        Route::patch('{devLog}/updates/{update}', [DevLogUpdateController::class, 'update'])
            ->name('dev-logs.updates.update');
    });

    Route::apiResource('projects', ProjectController::class);

    Route::apiResource('tasks', TaskController::class);
    Route::patch('tasks/{task}/status', [TaskController::class, 'updateStatus']);
    Route::get('tasks/{task}/attachments/{path}', [TaskController::class, 'downloadAttachment'])
        ->where('path', '.*')
        ->name('tasks.attachments.download');

    Route::apiResource('issues', IssueController::class);
    Route::patch('issues/{issue}/status', [IssueController::class, 'updateStatus'])->name('issues.status');
    Route::get('issues/{issue}/attachment', [IssueController::class, 'downloadAttachment'])->name('issues.attachment.download');

    Route::apiResource('contracts', ContractController::class);
    Route::get('contracts/{contract}/download', [ContractController::class, 'download'])->name('contracts.download');

    Route::prefix('projects/{project}')->scopeBindings()->group(function () {
        Route::get('members', [ProjectMemberController::class, 'index'])->name('projects.members.index');
        Route::post('members', [ProjectMemberController::class, 'store'])->name('projects.members.store');
        Route::patch('members/{user}', [ProjectMemberController::class, 'update'])->name('projects.members.update');
        Route::delete('members/{user}', [ProjectMemberController::class, 'destroy'])->name('projects.members.destroy');

        Route::apiResource('payments', PaymentController::class)->except(['show']);
        Route::apiResource('invitations', ProjectInvitationController::class)->only(['index', 'store', 'destroy']);
        Route::post('dev-logs/batch', [DevLogController::class, 'batchStore'])->name('projects.dev-logs.batch');
    });

    Route::prefix('users/{user}')->scopeBindings()->group(function () {
        Route::get('projects', [UserProjectController::class, 'index'])->name('users.projects.index');
        Route::post('projects', [UserProjectController::class, 'store'])->name('users.projects.store');
        Route::patch('projects/{project}', [UserProjectController::class, 'update'])->name('users.projects.update');
        Route::delete('projects/{project}', [UserProjectController::class, 'destroy'])->name('users.projects.destroy');
    });

    Route::prefix('tasks/{task}')->scopeBindings()->group(function () {
        Route::get('comments', [TaskCommentController::class, 'index'])->name('tasks.comments.index');
        Route::post('comments', [TaskCommentController::class, 'store'])->name('tasks.comments.store');
        Route::delete('comments/{comment}', [TaskCommentController::class, 'destroy'])->name('tasks.comments.destroy');
    });
});
