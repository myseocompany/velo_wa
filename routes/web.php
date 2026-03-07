<?php

declare(strict_types=1);

use App\Http\Controllers\ProfileController;
use App\Models\Conversation;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Redirect root to dashboard or login
Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Authenticated + tenant-scoped routes
Route::middleware(['auth', 'verified', 'tenant'])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard', [
            'stats' => [
                'open_conversations' => 0,
                'total_contacts'     => 0,
                'messages_today'     => 0,
                'avg_response_time'  => null,
            ],
        ]);
    })->name('dashboard');

    // Inbox
    Route::get('/inbox', function () {
        return Inertia::render('Inbox/Index');
    })->name('inbox');

    Route::get('/inbox/{conversation}', function (Conversation $conversation) {
        return Inertia::render('Inbox/Index', ['activeConversationId' => $conversation->id]);
    })->name('inbox.conversation');

    // Contacts (Phase 2)
    Route::get('/contacts', function () {
        return Inertia::render('Contacts/Index');
    })->name('contacts');

    // Pipeline (Phase 3)
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

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
