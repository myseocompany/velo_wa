<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Channel;
use App\Enums\ConversationStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Conversation extends Model
{
    use BelongsToTenant, HasFactory, HasUuids, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'assigned_to', 'closed_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('conversation');
    }

    protected $fillable = [
        'tenant_id',
        'contact_id',
        'status',
        'channel',
        'assigned_to',
        'assigned_at',
        'first_message_at',
        'first_response_at',
        'last_message_at',
        'message_count',
        'closed_at',
        'closed_by',
        'reopen_count',
    ];

    protected function casts(): array
    {
        return [
            'status' => ConversationStatus::class,
            'channel' => Channel::class,
            'assigned_at' => 'datetime',
            'first_message_at' => 'datetime',
            'first_response_at' => 'datetime',
            'last_message_at' => 'datetime',
            'closed_at' => 'datetime',
            'message_count' => 'integer',
            'reopen_count' => 'integer',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(PipelineDeal::class);
    }

    /** Response time in seconds (Dt1). Returns null if no response yet. */
    public function dt1(): ?int
    {
        if (! $this->first_message_at || ! $this->first_response_at) {
            return null;
        }

        return (int) $this->first_message_at->diffInSeconds($this->first_response_at);
    }

    public function isOpen(): bool
    {
        return $this->status === ConversationStatus::Open;
    }
}
