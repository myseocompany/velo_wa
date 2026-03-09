<?php

declare(strict_types=1);

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Models\Conversation;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Redirect root to dashboard or login
Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Authenticated + tenant-scoped routes
Route::middleware(['auth', 'verified', 'tenant'])->group(function () {
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
    })->name('contacts.show');

    // Pipeline (Phase 3)
    // Team workload
    Route::get('/team', function () {
        return Inertia::render('Team/Index');
    })->name('team');

    Route::get('/pipeline', function () {
        return Inertia::render('Pipeline/Index');
    })->name('pipeline');

    // Settings
    Route::get('/settings', function () {
        return Inertia::render('Settings/Index');
    })->name('settings');

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

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
