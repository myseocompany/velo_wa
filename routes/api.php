<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\PipelineDealController;
use App\Http\Controllers\Api\V1\WhatsAppController;
use App\Http\Controllers\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Health check (no auth)
Route::get('/health', function () {
    return response()->json(['status' => 'ok', 'timestamp' => now()->toIso8601String()]);
});

// Webhook endpoint (no auth — signed with Evolution API key in header)
Route::post('/webhooks/evolution', [WebhookController::class, 'evolution'])->name('webhooks.evolution');

// Authenticated API routes
Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
    // Current user
    Route::get('/me', function (Request $request) {
        return response()->json($request->user()->load('tenant'));
    });

    // WhatsApp connection management
    Route::prefix('whatsapp')->group(function () {
        Route::get('/status', [WhatsAppController::class, 'status'])->name('whatsapp.status');
        Route::post('/connect', [WhatsAppController::class, 'connect'])->name('whatsapp.connect');
        Route::post('/disconnect', [WhatsAppController::class, 'disconnect'])->name('whatsapp.disconnect');
    });

    // Conversations + messages
    Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::get('/conversations/{conversation}/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store'])->name('messages.store');

    // Contacts
    Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');

    // Pipeline deals
    Route::get('/pipeline/deals', [PipelineDealController::class, 'index'])->name('pipeline.deals.index');
    Route::patch('/pipeline/deals/{pipelineDeal}/stage', [PipelineDealController::class, 'updateStage'])->name('pipeline.deals.update-stage');
});
