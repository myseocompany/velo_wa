<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\WebhookLog;
use App\Models\WhatsAppLine;
use App\Services\WebhookHandlerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function evolution(Request $request, WebhookHandlerService $handler): JsonResponse
    {
        $expectedApiKey = trim((string) config('services.evolution.key', ''));

        if ($expectedApiKey === '') {
            Log::error('Webhook auth misconfigured: EVOLUTION_API_KEY is empty');
            return response()->json(['message' => 'Webhook authentication misconfigured.'], 503);
        }

        $providedApiKey = $this->extractWebhookApiKey($request);

        if ($providedApiKey === '' || ! hash_equals($expectedApiKey, $providedApiKey)) {
            Log::warning('Webhook unauthorized request', [
                'ip'       => $request->ip(),
                'has_key'  => $providedApiKey !== '',
                'event'    => $request->input('event'),
                'instance' => $request->input('instance'),
            ]);

            return response()->json(['message' => 'Unauthorized webhook request.'], 401);
        }

        $payload  = $request->all();
        $event    = $payload['event'] ?? '';
        $instance = $payload['instance'] ?? '';
        $tenant   = null;
        $line     = null;

        // Verify the instance belongs to a known tenant
        if ($instance) {
            $line = WhatsAppLine::where('instance_id', $instance)->with('tenant')->first();
            if (! $line) {
                $legacyTenant = Tenant::where('wa_instance_id', $instance)->first();
                $line = $legacyTenant?->getOrCreateDefaultLine();
            }
            $tenant = $line?->tenant;

            if (! $tenant) {
                Log::debug('Webhook: unknown instance', ['instance' => $instance, 'event' => $event]);
                // Still accept (200) to stop Evolution API retries, but don't process
                return response()->json(['ok' => true, 'ignored' => true]);
            }
        }

        Log::info('Webhook received', ['event' => $event, 'instance' => $instance]);

        // Store webhook log
        try {
            WebhookLog::create([
                'tenant_id'  => $tenant?->id,
                'event_type' => $event,
                'payload'    => $payload,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Webhook: failed to store log', ['error' => $e->getMessage()]);
        }

        if ($line) {
            $handler->handle($payload, $line);
        }

        return response()->json(['ok' => true]);
    }

    private function extractWebhookApiKey(Request $request): string
    {
        $candidates = [
            $request->header('apikey'),
            $request->header('x-api-key'),
            $request->header('x-evolution-apikey'),
            $request->bearerToken(),
            $request->query('apikey'),
            $request->query('x-api-key'),
            $request->input('apikey'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return '';
    }
}
