<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AutomationLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'automation_id'   => $this->automation_id,
            'conversation_id' => $this->conversation_id,
            'trigger_type'    => $this->trigger_type,
            'action_type'     => $this->action_type,
            'status'          => $this->status,
            'error_message'   => $this->error_message,
            'triggered_at'    => $this->triggered_at?->toIso8601String(),
        ];
    }
}
