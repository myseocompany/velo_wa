<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuickReplyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'shortcut'      => $this->shortcut,
            'title'         => $this->title,
            'body'          => $this->body,
            'has_variables' => $this->has_variables,
            'category'      => $this->category,
            'usage_count'   => $this->usage_count,
            'created_at'    => $this->created_at->toIso8601String(),
            'updated_at'    => $this->updated_at->toIso8601String(),
        ];
    }
}
