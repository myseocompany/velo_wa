<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'direction'      => $this->direction->value,
            'body'           => $this->body,
            'media_url'      => $this->media_url,
            'media_type'     => $this->media_type,
            'media_mime_type' => $this->media_mime_type,
            'media_filename' => $this->media_filename,
            'status'         => $this->status->value,
            'wa_message_id'  => $this->wa_message_id,
            'is_automated'   => $this->is_automated,
            'sent_by'        => $this->sent_by,
            'error_message'  => $this->error_message,
            'created_at'     => $this->created_at->toIso8601String(),
        ];
    }
}
