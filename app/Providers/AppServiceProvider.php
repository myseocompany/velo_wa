<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        $this->configureRateLimiting();
        $this->configurePlanGates();
    }

    private function configurePlanGates(): void
    {
        $features = [
            'inbox', 'contacts', 'tasks', 'quick_replies',
            'menu', 'pipeline', 'orders', 'reservations',
            'loyalty', 'automations_unlimited', 'dashboard_full', 'api_access',
        ];

        foreach ($features as $feature) {
            Gate::define("use-{$feature}", function ($user) use ($feature) {
                return $user->tenant->canUse($feature);
            });
        }
    }

    private function configureRateLimiting(): void
    {
        // General API: 120 requests/minute per authenticated user
        RateLimiter::for('api', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(120)->by($request->user()->id)
                : Limit::perMinute(20)->by($request->ip());
        });

        // Message sending: 30 messages/minute per tenant (WhatsApp rate limit protection)
        RateLimiter::for('messages', function (Request $request) {
            $tenantId = $request->user()?->tenant_id ?? $request->ip();

            return Limit::perMinute(30)
                ->by("messages:{$tenantId}")
                ->response(fn () => response()->json([
                    'message' => 'Demasiados mensajes enviados. Espera un momento.',
                ], 429));
        });

        // AI playground: 20 tests/minute per tenant (LLM token cost protection)
        RateLimiter::for('playground', function (Request $request) {
            $tenantId = $request->user()?->tenant_id ?? $request->ip();

            return Limit::perMinute(20)
                ->by("playground:{$tenantId}")
                ->response(fn () => response()->json([
                    'message' => 'Demasiadas pruebas. Espera un momento.',
                ], 429));
        });

        // WhatsApp connect/disconnect: 5 attempts/minute per user (prevents abuse)
        RateLimiter::for('whatsapp-control', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?? $request->ip());
        });

        // Webhook ingestion: 500/minute per IP (Evolution API sends bursts)
        RateLimiter::for('webhooks', function (Request $request) {
            return Limit::perMinute(500)->by($request->ip());
        });
    }
}
