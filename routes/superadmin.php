<?php

declare(strict_types=1);

use App\Http\Controllers\SuperAdmin\AuditController;
use App\Http\Controllers\SuperAdmin\AuthController;
use App\Http\Controllers\SuperAdmin\DashboardController;
use App\Http\Controllers\SuperAdmin\TenantController;
use Illuminate\Support\Facades\Route;

// Public: login
Route::prefix('superadmin')->name('superadmin.')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.post');

    // 2FA challenge (authenticated but not yet 2FA-verified)
    Route::middleware('platform.admin')->group(function () {
        Route::get('two-factor', [AuthController::class, 'showTwoFactor'])->name('two-factor');
        Route::post('two-factor', [AuthController::class, 'verifyTwoFactor'])->name('two-factor.verify');
    });

    // Authenticated + 2FA-verified
    Route::middleware(['platform.admin', 'platform.2fa'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        // Dashboard
        Route::get('/', DashboardController::class)->name('dashboard');

        // 2FA setup
        Route::get('2fa/setup', [AuthController::class, 'showTwoFactorSetup'])->name('2fa.setup');
        Route::post('2fa/enable', [AuthController::class, 'enableTwoFactor'])->name('2fa.enable');
        Route::delete('2fa', [AuthController::class, 'disableTwoFactor'])->name('2fa.disable');

        // Tenants
        Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index');
        Route::get('tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
        Route::patch('tenants/{tenant}/plan', [TenantController::class, 'updatePlan'])->name('tenants.plan');
        Route::post('tenants/{tenant}/impersonate', [TenantController::class, 'impersonate'])->name('tenants.impersonate');
        Route::post('tenants/{tenant}/wa/disconnect', [TenantController::class, 'disconnectWa'])->name('tenants.wa.disconnect');

        // Audit log
        Route::get('audit', [AuditController::class, 'index'])->name('audit');
    });
});
