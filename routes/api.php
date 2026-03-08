<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AssignmentRuleController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\PipelineDealController;
use App\Http\Controllers\Api\V1\QuickReplyController;
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

    // Conversations
    Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::patch('/conversations/{conversation}/assign', [ConversationController::class, 'assign'])->name('conversations.assign');
    Route::patch('/conversations/{conversation}/close', [ConversationController::class, 'close'])->name('conversations.close');
    Route::patch('/conversations/{conversation}/reopen', [ConversationController::class, 'reopen'])->name('conversations.reopen');

    // Messages
    Route::get('/conversations/{conversation}/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store'])->name('messages.store');
    Route::post('/conversations/{conversation}/messages/media', [MessageController::class, 'storeMedia'])->name('messages.store-media');
    Route::post('/conversations/{conversation}/messages/quick-reply/{quickReply}', [MessageController::class, 'storeQuickReply'])->name('messages.store-quick-reply');

    // Team members (for assignment dropdowns)
    Route::get('/team/members', [TeamController::class, 'members'])->name('team.members');

    // Contacts
    Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
    Route::get('/contacts/tags', [ContactController::class, 'tags'])->name('contacts.tags');
    Route::get('/contacts/{contact}', [ContactController::class, 'show'])->name('contacts.show');
    Route::patch('/contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
    Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');

    // Assignment rules
    Route::get('/assignment-rules', [AssignmentRuleController::class, 'index'])->name('assignment-rules.index');
    Route::post('/assignment-rules', [AssignmentRuleController::class, 'store'])->name('assignment-rules.store');
    Route::put('/assignment-rules/{assignmentRule}', [AssignmentRuleController::class, 'update'])->name('assignment-rules.update');
    Route::patch('/assignment-rules/{assignmentRule}/toggle', [AssignmentRuleController::class, 'toggle'])->name('assignment-rules.toggle');
    Route::delete('/assignment-rules/{assignmentRule}', [AssignmentRuleController::class, 'destroy'])->name('assignment-rules.destroy');

    // Quick replies
    Route::get('/quick-replies', [QuickReplyController::class, 'index'])->name('quick-replies.index');
    Route::post('/quick-replies', [QuickReplyController::class, 'store'])->name('quick-replies.store');
    Route::put('/quick-replies/{quickReply}', [QuickReplyController::class, 'update'])->name('quick-replies.update');
    Route::delete('/quick-replies/{quickReply}', [QuickReplyController::class, 'destroy'])->name('quick-replies.destroy');

    // Pipeline deals
    Route::get('/pipeline/deals/summary', [PipelineDealController::class, 'summary'])->name('pipeline.deals.summary');
    Route::get('/pipeline/deals', [PipelineDealController::class, 'index'])->name('pipeline.deals.index');
    Route::post('/pipeline/deals', [PipelineDealController::class, 'store'])->name('pipeline.deals.store');
    Route::get('/pipeline/deals/{pipelineDeal}', [PipelineDealController::class, 'show'])->name('pipeline.deals.show');
    Route::put('/pipeline/deals/{pipelineDeal}', [PipelineDealController::class, 'update'])->name('pipeline.deals.update');
    Route::delete('/pipeline/deals/{pipelineDeal}', [PipelineDealController::class, 'destroy'])->name('pipeline.deals.destroy');
    Route::patch('/pipeline/deals/{pipelineDeal}/stage', [PipelineDealController::class, 'updateStage'])->name('pipeline.deals.update-stage');
});
