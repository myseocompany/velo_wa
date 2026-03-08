<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AutomationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'trigger_type'    => $this->trigger_type->value,
            'trigger_config'  => $this->trigger_config ?? [],
            'action_type'     => $this->action_type->value,
            'action_config'   => $this->action_config ?? [],
            'is_active'       => $this->is_active,
            'priority'        => $this->priority,
            'execution_count' => $this->execution_count,
            'created_at'      => $this->created_at->toIso8601String(),
            'updated_at'      => $this->updated_at->toIso8601String(),
        ];
    }
}
