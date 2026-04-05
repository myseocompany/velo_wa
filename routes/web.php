<?php

declare(strict_types=1);

use App\Http\Controllers\BillingController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StripeWebhookController;
use App\Models\Conversation;
use App\Support\PlanCatalog;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Landing page
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Welcome', [
        'plans' => array_values(PlanCatalog::publicPlans()),
    ]);
})->name('home');

// Onboarding — runs before the onboarding middleware intercepts
Route::middleware(['auth', 'verified', 'tenant'])->group(function () {
    Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('/onboarding/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');
});

// Authenticated + tenant-scoped routes (onboarding guard applied)
Route::middleware(['auth', 'verified', 'tenant', 'onboarding'])->group(function () {
    Route::middleware('instrument.dashboard')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
    });

    // Inbox
    Route::get('/inbox', function () {
        return Inertia::render('Inbox/Index');
    })->name('inbox');

    Route::get('/inbox/{conversation}', function (Conversation $conversation) {
        return Inertia::render('Inbox/Index', ['activeConversationId' => $conversation->id]);
    })->name('inbox.conversation');

    // Contacts
    Route::get('/contacts', function () {
        return Inertia::render('Contacts/Index');
    })->name('contacts');

    Route::get('/contacts/{contact}', function (string $contact) {
        return Inertia::render('Contacts/Show', ['contactId' => $contact]);
    })->name('contacts.view');

    // Team
    Route::get('/team', function () {
        return Inertia::render('Team/Index');
    })->name('team');

    // Pipeline
    Route::get('/pipeline', function () {
        return Inertia::render('Pipeline/Index');
    })->name('pipeline');

    // Orders
    Route::get('/orders', function () {
        return Inertia::render('Orders/Index');
    })->name('orders');

    // Reservations
    Route::get('/reservations', function () {
        return Inertia::render('Reservations/Index');
    })->name('reservations');

    // Tasks
    Route::get('/tasks', function () {
        return Inertia::render('Tasks/Index');
    })->name('tasks');

    // Settings
    Route::get('/settings', function () {
        return Inertia::render('Settings/Index');
    })->name('settings');

    Route::get('/settings/general', function () {
        return Inertia::render('Settings/General');
    })->name('settings.general');

    Route::get('/settings/team', function () {
        return Inertia::render('Settings/Team');
    })->name('settings.team');

    Route::get('/settings/activity', function () {
        return Inertia::render('Settings/ActivityLog');
    })->name('settings.activity');

    Route::get('/settings/whatsapp', function () {
        return Inertia::render('Settings/WhatsApp');
    })->name('settings.whatsapp');

    Route::get('/settings/assignment-rules', function () {
        return Inertia::render('Settings/AssignmentRules');
    })->name('settings.assignment-rules');

    Route::get('/settings/quick-replies', function () {
        return Inertia::render('Settings/QuickReplies');
    })->name('settings.quick-replies');

    Route::get('/settings/automations', function () {
        return Inertia::render('Settings/Automations');
    })->name('settings.automations');

    Route::get('/settings/ai-agent', function () {
        return Inertia::render('Settings/AiAgent');
    })->name('settings.ai-agent');

    Route::get('/settings/webhooks', function () {
        return Inertia::render('Settings/Webhooks');
    })->name('settings.webhooks');

    Route::get('/settings/menu', function () {
        return Inertia::render('Settings/Menu');
    })->name('settings.menu');

    Route::get('/settings/data-quality', function () {
        return Inertia::render('Settings/DataQuality');
    })->name('settings.data-quality');

    // Billing — owner only
    Route::middleware('role:owner')->group(function () {
        Route::get('/settings/billing', [BillingController::class, 'show'])->name('settings.billing');
        Route::post('/settings/billing/checkout/{plan}', [BillingController::class, 'checkout'])->name('billing.checkout');
        Route::post('/settings/billing/portal', [BillingController::class, 'portal'])->name('billing.portal');
        Route::post('/settings/billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');
        Route::post('/settings/billing/resume', [BillingController::class, 'resume'])->name('billing.resume');
    });

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar'])->name('profile.avatar');
    Route::patch('/profile/notifications', [ProfileController::class, 'updateNotifications'])->name('profile.notifications');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';

// Stripe webhook (no auth — verified by Cashier via signature)
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleWebhook'])->name('cashier.webhook');

// Stop impersonation (accessible while impersonating, no tenant middleware)
Route::middleware('auth')->post('/impersonation/stop', function (\Illuminate\Http\Request $request) {
    $tenantId = $request->session()->get('impersonating_tenant_id');

    auth('web')->logout();

    $request->session()->forget(['impersonating_user_id', 'impersonating_tenant_id', 'impersonating_admin_id']);
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect("/superadmin/tenants/{$tenantId}");
})->name('impersonation.stop');
