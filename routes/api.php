<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ActivityController;
use App\Http\Controllers\Api\V1\AiAgentController;
use App\Http\Controllers\Api\V1\AssignmentRuleController;
use App\Http\Controllers\Api\V1\AutomationController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\LoyaltyController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\MetricsController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PipelineDealController;
use App\Http\Controllers\Api\V1\QuickReplyController;
use App\Http\Controllers\Api\V1\ReservationController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Controllers\Api\V1\TeamController;
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
    Route::get('/whatsapp/health-logs', [WhatsAppController::class, 'healthLogs'])->name('whatsapp.health-logs');
    Route::middleware(['role:owner', 'throttle:whatsapp-control'])->group(function () {
        Route::post('/whatsapp/connect', [WhatsAppController::class, 'connect'])->name('whatsapp.connect');
        Route::post('/whatsapp/disconnect', [WhatsAppController::class, 'disconnect'])->name('whatsapp.disconnect');
    });

    // Conversations — all authenticated users
    Route::post('/conversations', [ConversationController::class, 'store'])->name('conversations.store');
    Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::patch('/conversations/{conversation}/assign', [ConversationController::class, 'assign'])->name('conversations.assign');
    Route::patch('/conversations/{conversation}/close', [ConversationController::class, 'close'])->name('conversations.close');
    Route::patch('/conversations/{conversation}/reopen', [ConversationController::class, 'reopen'])->name('conversations.reopen');
    Route::delete('/conversations/{conversation}', [ConversationController::class, 'destroy'])->name('conversations.destroy');

    // Messages — all authenticated users (message sends have dedicated throttle)
    Route::get('/conversations/{conversation}/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store'])->middleware('throttle:messages')->name('messages.store');
    Route::post('/conversations/{conversation}/messages/media', [MessageController::class, 'storeMedia'])->middleware('throttle:messages')->name('messages.store-media');
    Route::post('/conversations/{conversation}/messages/quick-reply/{quickReply}', [MessageController::class, 'storeQuickReply'])->middleware('throttle:messages')->name('messages.store-quick-reply');


    // AI agent
    Route::get('/ai-agent', [AiAgentController::class, 'show'])->name('ai-agent.show');
    Route::get('/ai-agents', [AiAgentController::class, 'index'])->name('ai-agents.index');
    Route::patch('/conversations/{conversation}/ai-agent-toggle', [ConversationController::class, 'toggleAiAgent'])->name('conversations.ai-agent-toggle');
    Route::middleware('role:admin')->group(function () {
        // Backward-compatible single-agent endpoints
        Route::put('/ai-agent', [AiAgentController::class, 'upsert'])->name('ai-agent.upsert');
        Route::patch('/ai-agent/toggle', [AiAgentController::class, 'toggle'])->name('ai-agent.toggle');

        // Multi-agent endpoints
        Route::post('/ai-agents', [AiAgentController::class, 'store'])->name('ai-agents.store');
        Route::put('/ai-agents/{aiAgent}', [AiAgentController::class, 'update'])->name('ai-agents.update');
        Route::delete('/ai-agents/{aiAgent}', [AiAgentController::class, 'destroy'])->name('ai-agents.destroy');
        Route::patch('/ai-agents/{aiAgent}/toggle', [AiAgentController::class, 'toggleAgent'])->name('ai-agents.toggle');
        Route::patch('/ai-agents/{aiAgent}/default', [AiAgentController::class, 'setDefault'])->name('ai-agents.default');
    });

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

    // Tags master — read open to all; write admin+
    Route::get('/tags', [TagController::class, 'index'])->name('tags.index');
    Route::middleware('role:admin')->group(function () {
        Route::post('/tags', [TagController::class, 'store'])->name('tags.store');
        Route::patch('/tags/{tag}', [TagController::class, 'update'])->name('tags.update');
        Route::delete('/tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');
    });

    // Contacts — read open to all; destructive operations admin+
    Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
    Route::get('/contacts/tags', [ContactController::class, 'tags'])->name('contacts.tags'); // alias → /tags
    Route::get('/contacts/duplicates', [ContactController::class, 'duplicates'])->name('contacts.duplicates');
    Route::get('/contacts/{contact}', [ContactController::class, 'show'])->name('contacts.show');
    Route::patch('/contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
    Route::get('/loyalty/contacts/{contact}/account', [LoyaltyController::class, 'account'])->name('loyalty.account');
    Route::get('/loyalty/contacts/{contact}/events', [LoyaltyController::class, 'events'])->name('loyalty.events');
    Route::post('/loyalty/contacts/{contact}/adjust', [LoyaltyController::class, 'adjust'])->middleware('role:admin')->name('loyalty.adjust');
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

    // Orders — all can read/create/update; delete admin+
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::put('/orders/{order}', [OrderController::class, 'update'])->name('orders.update');
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.update-status');
    Route::delete('/orders/{order}', [OrderController::class, 'destroy'])->middleware('role:admin')->name('orders.destroy');

    // Reservations — all can read/create/update; delete admin+
    Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
    Route::get('/reservations/slots', [ReservationController::class, 'slots'])->name('reservations.slots');
    Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');
    Route::get('/reservations/{reservation}', [ReservationController::class, 'show'])->name('reservations.show');
    Route::put('/reservations/{reservation}', [ReservationController::class, 'update'])->name('reservations.update');
    Route::patch('/reservations/{reservation}/status', [ReservationController::class, 'updateStatus'])->name('reservations.update-status');
    Route::delete('/reservations/{reservation}', [ReservationController::class, 'destroy'])->middleware('role:admin')->name('reservations.destroy');

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

    // Menu — all can read; write admin+
    Route::get('/menu/categories', [MenuController::class, 'indexCategories'])->name('menu.categories.index');
    Route::get('/menu/items', [MenuController::class, 'indexItems'])->name('menu.items.index');
    Route::get('/menu/preview', [MenuController::class, 'preview'])->name('menu.preview');
    Route::middleware('role:admin')->group(function () {
        Route::post('/menu/categories', [MenuController::class, 'storeCategory'])->name('menu.categories.store');
        Route::put('/menu/categories/{menuCategory}', [MenuController::class, 'updateCategory'])->name('menu.categories.update');
        Route::delete('/menu/categories/{menuCategory}', [MenuController::class, 'destroyCategory'])->name('menu.categories.destroy');
        Route::patch('/menu/categories/reorder', [MenuController::class, 'reorderCategories'])->name('menu.categories.reorder');
        Route::post('/menu/items', [MenuController::class, 'storeItem'])->name('menu.items.store');
        Route::put('/menu/items/{menuItem}', [MenuController::class, 'updateItem'])->name('menu.items.update');
        Route::delete('/menu/items/{menuItem}', [MenuController::class, 'destroyItem'])->name('menu.items.destroy');
        Route::patch('/menu/items/{menuItem}/toggle', [MenuController::class, 'toggleItem'])->name('menu.items.toggle');
        Route::patch('/menu/items/reorder', [MenuController::class, 'reorderItems'])->name('menu.items.reorder');
        Route::post('/menu/test', [MenuController::class, 'sendTest'])->name('menu.test');
    });

    // Tasks — all can create/read; agents limited to own tasks (enforced in controller)
    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
    Route::patch('/tasks/{task}/complete', [TaskController::class, 'complete'])->name('tasks.complete');
    Route::patch('/tasks/{task}/reopen', [TaskController::class, 'reopen'])->name('tasks.reopen');
});
