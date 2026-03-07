<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\WaStatus;
use App\Events\WaStatusUpdated;
use App\Models\Tenant;
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

    public function handle(WhatsAppClientService $client): void
    {
        Tenant::whereNotNull('wa_instance_id')->each(function (Tenant $tenant) use ($client): void {
            $this->checkTenant($tenant, $client);
        });
    }

    private function checkTenant(Tenant $tenant, WhatsAppClientService $client): void
    {
        $instanceName = WhatsAppClientService::instanceName($tenant->id);

        try {
            $state = $client->getConnectionState($instanceName);
        } catch (Throwable $e) {
            Log::warning('CheckInstanceHealth: could not reach Evolution API', [
                'tenant_id' => $tenant->id,
                'error'     => $e->getMessage(),
            ]);
            return;
        }

        $currentStatus = $tenant->wa_status;

        if ($state === 'open' && $currentStatus !== WaStatus::Connected) {
            $tenant->update(['wa_status' => WaStatus::Connected]);
            broadcast(new WaStatusUpdated($tenant, null));
        } elseif ($state !== 'open' && $currentStatus === WaStatus::Connected) {
            $tenant->update(['wa_status' => WaStatus::Disconnected]);
            broadcast(new WaStatusUpdated($tenant, null));
        }
    }
}
