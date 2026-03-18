<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ActivityController;
use App\Http\Controllers\Api\V1\AssignmentRuleController;
use App\Http\Controllers\Api\V1\AutomationController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\MetricsController;
use App\Http\Controllers\Api\V1\PipelineDealController;
use App\Http\Controllers\Api\V1\QuickReplyController;
use App\Http\Controllers\Api\V1\TenantSettingsController;
use App\Http\Controllers\Api\V1\WhatsAppController;
use App\Http\Controllers\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Health check (no auth)
Route::get('/health', function () {
    return response()->json(['status' => 'ok', 'timestamp' => now()->toIso8601String()]);
});

// Webhook endpoint (no auth — signed with Evolution API key in header)
Route::post('/webhooks/evolution', [WebhookController::class, 'evolution'])
    ->middleware('throttle:webhooks')
    ->name('webhooks.evolution');

// Authenticated API routes
Route::middleware(['auth:sanctum', 'tenant', 'throttle:api'])->group(function () {
    // Current user
    Route::get('/me', function (Request $request) {
        return response()->json($request->user()->load('tenant'));
    });

    // WhatsApp — status readable by all; connect/disconnect owner only
    Route::get('/whatsapp/status', [WhatsAppController::class, 'status'])->name('whatsapp.status');
    Route::middleware(['role:owner', 'throttle:whatsapp-control'])->group(function () {
        Route::post('/whatsapp/connect', [WhatsAppController::class, 'connect'])->name('whatsapp.connect');
        Route::post('/whatsapp/disconnect', [WhatsAppController::class, 'disconnect'])->name('whatsapp.disconnect');
    });

    // Conversations — all authenticated users
    Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::patch('/conversations/{conversation}/assign', [ConversationController::class, 'assign'])->name('conversations.assign');
    Route::patch('/conversations/{conversation}/close', [ConversationController::class, 'close'])->name('conversations.close');
    Route::patch('/conversations/{conversation}/reopen', [ConversationController::class, 'reopen'])->name('conversations.reopen');

    // Messages — all authenticated users (message sends have dedicated throttle)
    Route::get('/conversations/{conversation}/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store'])->middleware('throttle:messages')->name('messages.store');
    Route::post('/conversations/{conversation}/messages/media', [MessageController::class, 'storeMedia'])->middleware('throttle:messages')->name('messages.store-media');
    Route::post('/conversations/{conversation}/messages/quick-reply/{quickReply}', [MessageController::class, 'storeQuickReply'])->middleware('throttle:messages')->name('messages.store-quick-reply');

    // Team — members/workload readable by all; management admin+
    Route::get('/team/members', [TeamController::class, 'members'])->name('team.members');
    Route::get('/team/workload', [TeamController::class, 'workload'])->name('team.workload');
    Route::get('/team', [TeamController::class, 'index'])->name('team.index');
    Route::middleware('role:admin')->group(function () {
        Route::post('/team/invite', [TeamController::class, 'invite'])->name('team.invite');
        Route::patch('/team/{member}', [TeamController::class, 'update'])->name('team.update');
        Route::patch('/team/{member}/deactivate', [TeamController::class, 'deactivate'])->name('team.deactivate');
        Route::patch('/team/{member}/reactivate', [TeamController::class, 'reactivate'])->name('team.reactivate');
    });

    // Contacts — read open to all; destructive operations admin+
    Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
    Route::get('/contacts/tags', [ContactController::class, 'tags'])->name('contacts.tags');
    Route::get('/contacts/duplicates', [ContactController::class, 'duplicates'])->name('contacts.duplicates');
    Route::get('/contacts/{contact}', [ContactController::class, 'show'])->name('contacts.show');
    Route::patch('/contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
    Route::middleware('role:admin')->group(function () {
        Route::post('/contacts', [ContactController::class, 'store'])->name('contacts.store');
        Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');
        Route::post('/contacts/{contact}/merge', [ContactController::class, 'merge'])->name('contacts.merge');
    });

    // Assignment rules — read open to all; write admin+
    Route::get('/assignment-rules', [AssignmentRuleController::class, 'index'])->name('assignment-rules.index');
    Route::middleware('role:admin')->group(function () {
        Route::post('/assignment-rules', [AssignmentRuleController::class, 'store'])->name('assignment-rules.store');
        Route::put('/assignment-rules/{assignmentRule}', [AssignmentRuleController::class, 'update'])->name('assignment-rules.update');
        Route::patch('/assignment-rules/{assignmentRule}/toggle', [AssignmentRuleController::class, 'toggle'])->name('assignment-rules.toggle');
        Route::delete('/assignment-rules/{assignmentRule}', [AssignmentRuleController::class, 'destroy'])->name('assignment-rules.destroy');
    });

    // Quick replies — read open to all; write admin+
    Route::get('/quick-replies', [QuickReplyController::class, 'index'])->name('quick-replies.index');
    Route::middleware('role:admin')->group(function () {
        Route::post('/quick-replies', [QuickReplyController::class, 'store'])->name('quick-replies.store');
        Route::put('/quick-replies/{quickReply}', [QuickReplyController::class, 'update'])->name('quick-replies.update');
        Route::delete('/quick-replies/{quickReply}', [QuickReplyController::class, 'destroy'])->name('quick-replies.destroy');
    });

    // Pipeline deals — all can read/create/update; delete admin+
    Route::get('/pipeline/deals/summary', [PipelineDealController::class, 'summary'])->name('pipeline.deals.summary');
    Route::get('/pipeline/deals', [PipelineDealController::class, 'index'])->name('pipeline.deals.index');
    Route::post('/pipeline/deals', [PipelineDealController::class, 'store'])->name('pipeline.deals.store');
    Route::get('/pipeline/deals/{pipelineDeal}', [PipelineDealController::class, 'show'])->name('pipeline.deals.show');
    Route::put('/pipeline/deals/{pipelineDeal}', [PipelineDealController::class, 'update'])->name('pipeline.deals.update');
    Route::patch('/pipeline/deals/{pipelineDeal}/stage', [PipelineDealController::class, 'updateStage'])->name('pipeline.deals.update-stage');
    Route::delete('/pipeline/deals/{pipelineDeal}', [PipelineDealController::class, 'destroy'])->middleware('role:admin')->name('pipeline.deals.destroy');

    // Metrics
    Route::get('/metrics/agents', [MetricsController::class, 'agents'])->name('metrics.agents');
    Route::get('/metrics/export', [MetricsController::class, 'export'])->name('metrics.export');

    // Automations — read open to all; write admin+
    Route::get('/automations', [AutomationController::class, 'index'])->name('automations.index');
    Route::get('/automations/{automation}/logs', [AutomationController::class, 'logs'])->name('automations.logs');
    Route::middleware('role:admin')->group(function () {
        Route::post('/automations', [AutomationController::class, 'store'])->name('automations.store');
        Route::put('/automations/{automation}', [AutomationController::class, 'update'])->name('automations.update');
        Route::delete('/automations/{automation}', [AutomationController::class, 'destroy'])->name('automations.destroy');
        Route::patch('/automations/{automation}/toggle', [AutomationController::class, 'toggle'])->name('automations.toggle');
    });

    // Tenant settings — owner only
    Route::get('/tenant/settings', [TenantSettingsController::class, 'show'])->name('tenant.settings.show');
    Route::patch('/tenant/settings', [TenantSettingsController::class, 'update'])->middleware('role:owner')->name('tenant.settings.update');

    // Activity log — admin+
    Route::get('/activity', [ActivityController::class, 'index'])->middleware('role:admin')->name('activity.index');
});
