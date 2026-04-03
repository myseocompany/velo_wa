<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaskPriority;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'assigned_to',
        'contact_id',
        'conversation_id',
        'deal_id',
        'title',
        'description',
        'due_at',
        'reminded_at',
        'completed_at',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'priority' => TaskPriority::class,
            'due_at' => 'datetime',
            'reminded_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(PipelineDeal::class, 'deal_id');
    }

    public function scopePending(Builder $query): void
    {
        $query->whereNull('completed_at');
    }

    public function scopeCompleted(Builder $query): void
    {
        $query->whereNotNull('completed_at');
    }

    public function scopeOverdue(Builder $query): void
    {
        $query->whereNull('completed_at')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now());
    }

    public function scopeToday(Builder $query): void
    {
        $query->whereNull('completed_at')
            ->whereNotNull('due_at')
            ->whereDate('due_at', today());
    }

    public function scopeThisWeek(Builder $query): void
    {
        $query->whereNull('completed_at')
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [now()->startOfWeek(), now()->endOfWeek()]);
    }
}
