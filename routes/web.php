<?php

use App\Http\Controllers\CommentController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
});

Route::middleware(['auth:web', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');

    Route::get('/projects/{project}/tasks/{task}', [TaskController::class, 'show'])
        ->scopeBindings()
        ->name('projects.tasks.show');
    Route::post('/projects/{project}/tasks', [TaskController::class, 'store'])
        ->scopeBindings()
        ->name('projects.tasks.store');
    Route::get('/projects/{project}/tasks/{task}/attachments/{path}', [TaskController::class, 'downloadAttachment'])
        ->where('path', '.*')
        ->scopeBindings()
        ->name('projects.tasks.attachments.download');
    Route::patch('/tasks/{task}/status', [TaskController::class, 'updateStatus'])
        ->name('tasks.status.update');

    Route::get('/projects/{project}/contracts/{contract}/download', [ContractController::class, 'download'])
        ->scopeBindings()
        ->name('projects.contracts.download');

    Route::post('/tasks/{task}/comments', [CommentController::class, 'store'])
        ->name('tasks.comments.store');
});

Route::get('/projects/{project}/issues/{issue}/attachment', [IssueController::class, 'downloadAttachment'])
    ->scopeBindings()
    ->middleware('auth:web,admin')
    ->name('projects.issues.attachment.download');

Route::get('/projects/invite/{token}', [InvitationController::class, 'accept'])
    ->name('projects.invite.accept');

Route::middleware('auth:web')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

Route::get('/login', fn () => redirect('/'));
Route::get('/register', fn () => redirect('/'));
