<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\WaStatus;
use App\Events\WaStatusUpdated;
use App\Http\Controllers\Controller;
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
        $webhookUrl   = url('/api/v1/webhooks/evolution');

        // Delete any stale instance first — ensures a fresh QR every time
        try {
            $this->client->deleteInstance($instanceName);
        } catch (\Throwable) {
            // Ignore — instance might not exist
        }

        // Create a fresh instance (always returns a QR on v2.3.7)
        $createResult = $this->client->createInstance($instanceName, $webhookUrl);

        Log::info('Evolution createInstance', [
            'instance' => $instanceName,
            'hasQr'    => ! empty($createResult['qrcode']['base64'] ?? null),
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
