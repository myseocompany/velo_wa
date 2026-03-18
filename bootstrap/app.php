<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        apiPrefix: 'api/v1',
        then: function () {
            \Illuminate\Support\Facades\Route::middleware('web')
                ->group(base_path('routes/superadmin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'tenant'              => \App\Http\Middleware\EnsureTenantContext::class,
            'instrument.dashboard' => \App\Http\Middleware\InstrumentDashboardRequest::class,
            'role'                => \App\Http\Middleware\RequireRole::class,
            'platform.admin'      => \App\Http\Middleware\EnsurePlatformAdmin::class,
            'platform.2fa'        => \App\Http\Middleware\EnsurePlatformAdminTwoFactor::class,
            'stop.impersonation'  => \App\Http\Middleware\StopImpersonation::class,
            'onboarding'          => \App\Http\Middleware\EnsureOnboardingComplete::class,
        ]);

        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $exception, Request $request) {
            if (! $request->inertia()) {
                return $response;
            }

            $status = $response->getStatusCode();
            if (in_array($status, [403, 404, 500, 503])) {
                return Inertia::render('Error', ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            return $response;
        });
    })->create();
