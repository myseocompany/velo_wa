<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ConversationStatus;
use App\Enums\WaStatus;
use App\Events\WaStatusUpdated;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\WaHealthLog;
use App\Models\WhatsAppLine;
use App\Services\WhatsAppClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatsAppLineController extends Controller
{
    public function __construct(private readonly WhatsAppClientService $client) {}

    public function index(Request $request): JsonResponse
    {
        $lines = $request->user()->tenant->whatsappLines()
            ->orderByDesc('is_default')
            ->orderBy('label')
            ->get()
            ->map(fn (WhatsAppLine $line) => $this->serializeLine($line));

        return response()->json(['data' => $lines]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:100'],
        ]);

        $tenant = $request->user()->tenant;

        $line = DB::transaction(function () use ($tenant, $data): WhatsAppLine {
            Tenant::whereKey($tenant->id)->lockForUpdate()->first();

            $maxLines = $tenant->currentPlan()->maxWhatsAppLines();
            $currentLines = $tenant->whatsappLines()->count();

            abort_if(
                $maxLines !== -1 && $currentLines >= $maxLines,
                422,
                'Has alcanzado el límite de líneas de WhatsApp de tu plan.'
            );

            $hasLines = $tenant->whatsappLines()->exists();

            return $tenant->whatsappLines()->create([
                'label' => $data['label'],
                'is_default' => ! $hasLines,
            ]);
        });

        return response()->json(['data' => $this->serializeLine($line)], 201);
    }

    public function update(Request $request, WhatsAppLine $line): JsonResponse
    {
        $this->authorizeLine($request, $line);

        $data = $request->validate([
            'label' => ['sometimes', 'string', 'max:100'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        $tenant = $request->user()->tenant;

        DB::transaction(function () use ($tenant, $line, $data): void {
            $tenant->whatsappLines()->lockForUpdate()->get();

            $updates = [];
            if (array_key_exists('label', $data)) {
                $updates['label'] = $data['label'];
            }

            if (($data['is_default'] ?? false) === true) {
                $tenant->whatsappLines()->where('id', '!=', $line->id)->update(['is_default' => false]);
                $updates['is_default'] = true;
            }

            if ($updates !== []) {
                $line->update($updates);
            }
        });

        return response()->json(['data' => $this->serializeLine($line->fresh())]);
    }

    public function destroy(Request $request, WhatsAppLine $line): JsonResponse
    {
        $this->authorizeLine($request, $line);

        $hasOpenConversations = $line->conversations()
            ->whereIn('status', [ConversationStatus::Open->value, ConversationStatus::Pending->value])
            ->exists();

        if ($hasOpenConversations) {
            return response()->json(['message' => 'No puedes eliminar una línea con conversaciones abiertas.'], 422);
        }

        $hasOtherLines = $request->user()->tenant->whatsappLines()
            ->where('id', '!=', $line->id)
            ->exists();

        if ($line->is_default && $hasOtherLines) {
            return response()->json(['message' => 'Asigna otra línea como default primero.'], 422);
        }

        if ($line->instance_id) {
            try {
                $this->client->deleteInstance($line->instance_id);
            } catch (\Throwable $e) {
                Log::info('[WA line delete] delete skipped', ['line_id' => $line->id, 'reason' => $e->getMessage()]);
            }
        }

        $line->delete();

        return response()->json(['ok' => true]);
    }

    public function connect(Request $request, WhatsAppLine $line): JsonResponse
    {
        $this->authorizeLine($request, $line);

        $tenant = $request->user()->tenant;
        $instanceName = $line->instance_id ?: WhatsAppClientService::instanceName($line->id);
        $webhookApiKey = rawurlencode((string) config('services.evolution.key', ''));
        $webhookUrl = url("/api/v1/webhooks/evolution?apikey={$webhookApiKey}");

        Log::info('[WA line connect] start', [
            'tenant_id' => $tenant->id,
            'line_id' => $line->id,
            'instance' => $instanceName,
            'webhook_url' => $webhookUrl,
        ]);

        try {
            $this->client->deleteInstance($instanceName);
        } catch (\Throwable $e) {
            Log::info('[WA line connect] delete skipped (ok)', ['instance' => $instanceName, 'reason' => $e->getMessage()]);
        }

        try {
            $createResult = $this->client->createInstance($instanceName, $webhookUrl);
        } catch (\Throwable $e) {
            Log::error('[WA line connect] createInstance FAILED', [
                'instance' => $instanceName,
                'line_id' => $line->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => $e->getMessage()], 502);
        }

        $qrBase64 = $this->extractQr($createResult);

        $line->update([
            'instance_id' => $instanceName,
            'status' => WaStatus::QrPending,
        ]);

        if ($line->is_default) {
            $tenant->update([
                'wa_instance_id' => $instanceName,
                'wa_status' => WaStatus::QrPending,
            ]);
        }

        broadcast(new WaStatusUpdated($tenant->fresh(), $line->fresh(), $qrBase64));

        return response()->json([
            'qr_code' => $qrBase64,
            'expires_at' => now()->addSeconds(60)->toIso8601String(),
            'line' => $this->serializeLine($line->fresh()),
        ]);
    }

    public function disconnect(Request $request, WhatsAppLine $line): JsonResponse
    {
        $this->authorizeLine($request, $line);

        if ($line->instance_id) {
            try {
                $this->client->deleteInstance($line->instance_id);
            } catch (\Throwable) {
                // Proceed even if Evolution API call fails.
            }
        }

        $line->update([
            'instance_id' => null,
            'status' => WaStatus::Disconnected,
            'phone' => null,
            'connected_at' => null,
        ]);

        if ($line->is_default) {
            $request->user()->tenant->update([
                'wa_instance_id' => null,
                'wa_status' => WaStatus::Disconnected,
                'wa_phone' => null,
                'wa_connected_at' => null,
            ]);
        }

        broadcast(new WaStatusUpdated($request->user()->tenant->fresh(), $line->fresh(), null));

        return response()->json(['ok' => true]);
    }

    public function status(Request $request, WhatsAppLine $line): JsonResponse
    {
        $this->authorizeLine($request, $line);

        if ($line->instance_id) {
            try {
                $liveState = $this->client->getConnectionState($line->instance_id);

                $liveStatus = match ($liveState) {
                    'open' => WaStatus::Connected,
                    'connecting' => WaStatus::QrPending,
                    default => WaStatus::Disconnected,
                };

                if ($liveStatus !== $line->status) {
                    $line->update(['status' => $liveStatus]);
                    if ($line->is_default) {
                        $request->user()->tenant->update(['wa_status' => $liveStatus]);
                    }
                    $line = $line->fresh();
                }
            } catch (\Throwable) {
                // Evolution API unreachable: use cached state.
            }
        }

        return response()->json($this->serializeLine($line));
    }

    public function healthLogs(Request $request, WhatsAppLine $line): JsonResponse
    {
        $this->authorizeLine($request, $line);

        $limit = max(1, min((int) $request->integer('limit', 30), 200));

        $logs = WaHealthLog::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where(function ($query) use ($line): void {
                $query->where('whatsapp_line_id', $line->id);

                if ($line->is_default) {
                    $query->orWhereNull('whatsapp_line_id');
                }
            })
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
                'consecutive_failures' => (int) ($line->health_consecutive_failures ?? 0),
                'last_alert_at' => $line->health_last_alert_at?->toIso8601String(),
            ],
        ]);
    }

    private function authorizeLine(Request $request, WhatsAppLine $line): void
    {
        abort_unless($line->tenant_id === $request->user()->tenant_id, 403);
    }

    private function serializeLine(WhatsAppLine $line): array
    {
        return [
            'id' => $line->id,
            'label' => $line->label,
            'instance_id' => $line->instance_id,
            'status' => $line->status?->value,
            'phone' => $line->phone,
            'connected_at' => $line->connected_at?->toIso8601String(),
            'is_default' => $line->is_default,
            'health_consecutive_failures' => $line->health_consecutive_failures,
            'health_last_alert_at' => $line->health_last_alert_at?->toIso8601String(),
            'created_at' => $line->created_at?->toIso8601String(),
            'updated_at' => $line->updated_at?->toIso8601String(),
        ];
    }

    private function extractQr(array $data): ?string
    {
        if (! empty($data['qrcode']['base64']) && is_string($data['qrcode']['base64'])) {
            return $data['qrcode']['base64'];
        }

        if (! empty($data['base64']) && is_string($data['base64'])) {
            return $data['base64'];
        }

        return null;
    }
}
