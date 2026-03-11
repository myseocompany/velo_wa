<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    protected $fillable = [
        'conversation_id',
        'tenant_id',
        'direction',
        'body',
        'media_url',
        'media_type',
        'media_mime_type',
        'media_filename',
        'status',
        'wa_message_id',
        'error_message',
        'sent_by',
        'is_automated',
        // Allow explicit timestamp override for inbound messages (WhatsApp timestamp)
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'direction' => MessageDirection::class,
            'status' => MessageStatus::class,
            'is_automated' => 'boolean',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function isInbound(): bool
    {
        return $this->direction === MessageDirection::In;
    }

    public function isOutbound(): bool
    {
        return $this->direction === MessageDirection::Out;
    }

    public function hasMedia(): bool
    {
        return $this->media_url !== null;
    }
}
