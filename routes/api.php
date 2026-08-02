<?php

use App\Http\Controllers\Api\DevLogController;
use App\Http\Middleware\EnsureDevLogApiToken;
use Illuminate\Support\Facades\Route;

Route::middleware(EnsureDevLogApiToken::class)
    ->prefix('dev-logs')
    ->group(function () {
        Route::get('/', [DevLogController::class, 'index']);
        Route::post('/', [DevLogController::class, 'store']);
    });
