<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\WaStatus;
use App\Events\WaStatusUpdated;
use App\Models\Tenant;
use App\Models\WaHealthLog;
use App\Models\WhatsAppLine;
use App\Services\WhatsAppClientService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class CheckInstanceHealth implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const ALERT_FAILURE_THRESHOLD = 3;
    private const ALERT_COOLDOWN_MINUTES = 30;

    public function handle(WhatsAppClientService $client): void
    {
        Tenant::whereNotNull('wa_instance_id')
            ->whereDoesntHave('whatsappLines')
            ->each(fn (Tenant $tenant) => $tenant->getOrCreateDefaultLine());

        WhatsAppLine::withoutGlobalScope('tenant')
            ->whereNotNull('instance_id')
            ->where('status', '!=', WaStatus::Disconnected)
            ->with('tenant')
            ->each(function (WhatsAppLine $line) use ($client): void {
                $this->checkLine($line, $client);
        });
    }

    private function checkLine(WhatsAppLine $line, WhatsAppClientService $client): void
    {
        $tenant = $line->tenant;
        $instanceName = (string) $line->instance_id;
        $startedAt = microtime(true);

        try {
            $state = $client->getConnectionState($instanceName);
        } catch (Throwable $e) {
            $responseMs = (int) round((microtime(true) - $startedAt) * 1000);
            WaHealthLog::create([
                'tenant_id' => $tenant->id,
                'whatsapp_line_id' => $line->id,
                'instance_name' => $instanceName,
                'state' => null,
                'is_healthy' => false,
                'response_ms' => $responseMs,
                'error_message' => mb_substr($e->getMessage(), 0, 500),
                'checked_at' => now(),
            ]);

            $this->recordFailureAndAlert($line, "exception: {$e->getMessage()}");

            Log::warning('CheckInstanceHealth: could not reach Evolution API', [
                'tenant_id' => $tenant->id,
                'line_id' => $line->id,
                'error'     => $e->getMessage(),
            ]);
            return;
        }

        $responseMs = (int) round((microtime(true) - $startedAt) * 1000);
        $isHealthy = $state === 'open';
        WaHealthLog::create([
            'tenant_id' => $tenant->id,
            'whatsapp_line_id' => $line->id,
            'instance_name' => $instanceName,
            'state' => $state,
            'is_healthy' => $isHealthy,
            'response_ms' => $responseMs,
            'error_message' => null,
            'checked_at' => now(),
        ]);

        if ($isHealthy) {
            $this->recordRecoveryIfNeeded($line);
        } else {
            $this->recordFailureAndAlert($line, "state={$state}");
        }

        $currentStatus = $line->status;

        if ($state === 'open' && $currentStatus !== WaStatus::Connected) {
            $line->update(['status' => WaStatus::Connected]);
            $tenant->update(['wa_status' => WaStatus::Connected]);
            broadcast(new WaStatusUpdated($tenant, $line->fresh(), null));
        } elseif ($state !== 'open' && $currentStatus === WaStatus::Connected) {
            $line->update(['status' => WaStatus::Disconnected]);
            $tenant->update(['wa_status' => WaStatus::Disconnected]);
            broadcast(new WaStatusUpdated($tenant, $line->fresh(), null));
        }
    }

    private function recordFailureAndAlert(WhatsAppLine $line, string $reason): void
    {
        $line->increment('health_consecutive_failures');
        $line = $line->fresh();
        $tenant = $line->tenant;
        if ($line->is_default) {
            $tenant->increment('wa_health_consecutive_failures');
            $tenant = $tenant->fresh();
        }

        $failures = (int) ($line->health_consecutive_failures ?? 0);
        $lastAlertAt = $line->health_last_alert_at;
        $cooldownPassed = $lastAlertAt === null || $lastAlertAt->lte(now()->subMinutes(self::ALERT_COOLDOWN_MINUTES));

        if ($failures < self::ALERT_FAILURE_THRESHOLD || ! $cooldownPassed) {
            return;
        }

        $line->update(['health_last_alert_at' => now()]);
        if ($line->is_default) {
            $tenant->update(['wa_health_last_alert_at' => now()]);
        }

        activity()
            ->performedOn($tenant)
            ->withProperties([
                'tenant_id' => $tenant->id,
                'whatsapp_line_id' => $line->id,
                'consecutive_failures' => $failures,
                'reason' => $reason,
            ])
            ->event('wa_health')
            ->log('wa_health_unhealthy_threshold_reached');

        $message = sprintf(
            'WA health alert: tenant=%s failures=%d reason=%s',
            (string) $tenant->id,
            $failures,
            $reason
        );

        Log::error($message, ['tenant_id' => $tenant->id, 'line_id' => $line->id]);

        if (filled(config('logging.channels.slack.url'))) {
            Log::channel('slack')->error($message);
        }
    }

    private function recordRecoveryIfNeeded(WhatsAppLine $line): void
    {
        $tenant = $line->tenant;
        $failures = (int) ($line->health_consecutive_failures ?? 0);
        if ($failures >= self::ALERT_FAILURE_THRESHOLD) {
            activity()
                ->performedOn($tenant)
                ->withProperties([
                    'tenant_id' => $tenant->id,
                    'whatsapp_line_id' => $line->id,
                    'previous_consecutive_failures' => $failures,
                ])
                ->event('wa_health')
                ->log('wa_health_recovered');
        }

        if ($failures > 0) {
            $line->update(['health_consecutive_failures' => 0]);
            if ($line->is_default) {
                $tenant->update(['wa_health_consecutive_failures' => 0]);
            }
        }
    }
}
