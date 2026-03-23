<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DealStage;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PipelineDeal extends Model
{
    use BelongsToTenant, HasFactory, HasUuids, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'stage', 'value', 'assigned_to', 'lost_reason'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('deal');
    }

    protected $fillable = [
        'tenant_id',
        'contact_id',
        'conversation_id',
        'title',
        'stage',
        'value',
        'currency',
        'lead_at',
        'qualified_at',
        'proposal_at',
        'negotiation_at',
        'closed_at',
        'lost_reason',
        'won_product',
        'assigned_to',
        'notes',
        'follow_up_at',
        'follow_up_note',
    ];

    protected function casts(): array
    {
        return [
            'stage' => DealStage::class,
            'value' => 'decimal:2',
            'lead_at' => 'datetime',
            'qualified_at' => 'datetime',
            'proposal_at' => 'datetime',
            'negotiation_at' => 'datetime',
            'closed_at'    => 'datetime',
            'follow_up_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function isClosed(): bool
    {
        return $this->stage->isClosed();
    }
}
