<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\WaStatus;
use App\Events\WaStatusUpdated;
use App\Http\Controllers\Controller;
use App\Models\WaHealthLog;
use App\Services\WhatsAppClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
    public function __construct(private readonly WhatsAppClientService $client) {}

    public function status(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        // If tenant has an instance, verify live state against Evolution API
        if ($tenant->wa_instance_id) {
            try {
                $liveState = $this->client->getConnectionState($tenant->wa_instance_id);

                $liveStatus = match ($liveState) {
                    'open'       => WaStatus::Connected,
                    'connecting' => WaStatus::QrPending,
                    default      => WaStatus::Disconnected,
                };

                if ($liveStatus !== $tenant->wa_status) {
                    $tenant->update(['wa_status' => $liveStatus]);
                    $tenant = $tenant->fresh();
                }
            } catch (\Throwable) {
                // Evolution API unreachable — use cached state
            }
        }

        return response()->json([
            'status'       => $tenant->wa_status?->value,
            'phone'        => $tenant->wa_phone,
            'connected_at' => $tenant->wa_connected_at?->toIso8601String(),
            'instance_id'  => $tenant->wa_instance_id,
        ]);
    }

    public function connect(Request $request): JsonResponse
    {
        $tenant       = $request->user()->tenant;
        $instanceName = WhatsAppClientService::instanceName($tenant->id);
        $webhookApiKey = rawurlencode((string) config('services.evolution.key', ''));
        $webhookUrl    = url("/api/v1/webhooks/evolution?apikey={$webhookApiKey}");

        Log::info('[WA connect] start', [
            'tenant_id'    => $tenant->id,
            'instance'     => $instanceName,
            'evolution_url' => config('services.evolution.url'),
            'webhook_url'  => $webhookUrl,
            'app_url'      => config('app.url'),
        ]);

        // Delete any stale instance first — ensures a fresh QR every time
        try {
            $this->client->deleteInstance($instanceName);
            Log::info('[WA connect] stale instance deleted', ['instance' => $instanceName]);
        } catch (\Throwable $e) {
            Log::info('[WA connect] delete skipped (ok)', ['reason' => $e->getMessage()]);
        }

        // Create a fresh instance (always returns a QR on v2.3.7)
        try {
            $createResult = $this->client->createInstance($instanceName, $webhookUrl);
        } catch (\Throwable $e) {
            Log::error('[WA connect] createInstance FAILED', [
                'instance' => $instanceName,
                'error'    => $e->getMessage(),
            ]);
            return response()->json(['error' => $e->getMessage()], 502);
        }

        Log::info('[WA connect] createInstance response', [
            'instance' => $instanceName,
            'hasQr'    => ! empty($createResult['qrcode']['base64'] ?? null),
            'keys'     => array_keys($createResult),
            'raw'      => json_encode($createResult),
        ]);

        $qrBase64 = $this->extractQr($createResult);

        $tenant->update([
            'wa_instance_id' => $instanceName,
            'wa_status'      => WaStatus::QrPending,
        ]);

        broadcast(new WaStatusUpdated($tenant->fresh(), $qrBase64));

        return response()->json([
            'qr_code'    => $qrBase64,
            'expires_at' => now()->addSeconds(60)->toIso8601String(),
        ]);
    }

    public function disconnect(Request $request): JsonResponse
    {
        $tenant       = $request->user()->tenant;
        $instanceName = WhatsAppClientService::instanceName($tenant->id);

        if ($tenant->wa_instance_id) {
            try {
                $this->client->deleteInstance($instanceName);
            } catch (\Throwable) {
                // Proceed even if Evolution API call fails
            }
        }

        $tenant->update([
            'wa_instance_id' => null,
            'wa_status'      => WaStatus::Disconnected,
            'wa_phone'       => null,
            'wa_connected_at' => null,
        ]);

        broadcast(new WaStatusUpdated($tenant->fresh(), null));

        return response()->json(['ok' => true]);
    }

    public function healthLogs(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $tenantId = (string) $tenant->id;
        $limit = max(1, min((int) $request->integer('limit', 30), 200));

        $logs = WaHealthLog::query()
            ->where('tenant_id', $tenantId)
            ->latest('checked_at')
            ->limit($limit)
            ->get()
            ->map(fn (WaHealthLog $log) => [
                'id' => $log->id,
                'instance_name' => $log->instance_name,
                'state' => $log->state,
                'is_healthy' => $log->is_healthy,
                'response_ms' => $log->response_ms,
                'error_message' => $log->error_message,
                'checked_at' => $log->checked_at?->toIso8601String(),
            ]);

        return response()->json([
            'data' => $logs,
            'meta' => [
                'consecutive_failures' => (int) ($tenant->wa_health_consecutive_failures ?? 0),
                'last_alert_at' => $tenant->wa_health_last_alert_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Extract QR base64 from various Evolution API response structures.
     */
    private function extractQr(array $data): ?string
    {
        // v2.3: { "qrcode": { "base64": "data:image/png;base64,..." } }
        if (! empty($data['qrcode']['base64']) && is_string($data['qrcode']['base64'])) {
            return $data['qrcode']['base64'];
        }

        // v2 connect: { "base64": "data:image/png;base64,..." }
        if (! empty($data['base64']) && is_string($data['base64'])) {
            return $data['base64'];
        }

        return null;
    }
}
