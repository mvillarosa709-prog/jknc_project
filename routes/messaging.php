<?php

use App\Http\Controllers\MessengerWebhookController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

// Authentication is the Meta signature, not an interactive application session.
Route::get('/webhooks/messenger', [MessengerWebhookController::class, 'verify'])->name('webhooks.messenger.verify');
Route::post('/webhooks/messenger', [MessengerWebhookController::class, 'receive'])
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->name('webhooks.messenger.receive');
