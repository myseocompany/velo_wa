<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant;
use App\Models\WhatsAppLine;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WaStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly ?WhatsAppLine $line,
        public readonly ?string $qrCode,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("tenant.{$this->tenant->id}")];
    }

    public function broadcastAs(): string
    {
        return 'wa.status.updated';
    }

    public function broadcastWith(): array
    {
        if ($this->line) {
            return [
                'line_id'      => $this->line->id,
                'label'        => $this->line->label,
                'status'       => $this->line->status->value,
                'phone'        => $this->line->phone,
                'connected_at' => $this->line->connected_at?->toIso8601String(),
                'qr_code'      => $this->qrCode,
                'legacy'       => true,
            ];
        }

        return [
            'status'       => $this->tenant->wa_status->value,
            'phone'        => $this->tenant->wa_phone,
            'connected_at' => $this->tenant->wa_connected_at?->toIso8601String(),
            'qr_code'      => $this->qrCode,
        ];
    }
}
