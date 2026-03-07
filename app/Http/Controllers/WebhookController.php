<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\WebhookLog;
use App\Services\WebhookHandlerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function evolution(Request $request, WebhookHandlerService $handler): JsonResponse
    {
        $payload  = $request->all();
        $event    = $payload['event'] ?? '';
        $instance = $payload['instance'] ?? '';

        // Verify the instance belongs to a known tenant
        if ($instance && ! Tenant::where('wa_instance_id', $instance)->exists()) {
            Log::debug('Webhook: unknown instance', ['instance' => $instance, 'event' => $event]);
            // Still accept (200) to stop Evolution API retries, but don't process
            return response()->json(['ok' => true, 'ignored' => true]);
        }

        Log::info('Webhook received', ['event' => $event, 'instance' => $instance]);

        // Store webhook log
        try {
            WebhookLog::create([
                'event_type' => $event,
                'payload'    => $payload,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Webhook: failed to store log', ['error' => $e->getMessage()]);
        }

        $handler->handle($payload);

        return response()->json(['ok' => true]);
    }
}
