<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiAgentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'system_prompt' => $this->system_prompt,
            'llm_model' => $this->llm_model,
            'is_enabled' => (bool) $this->is_enabled,
            'context_messages' => (int) $this->context_messages,
            'is_configured' => $this->exists,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
