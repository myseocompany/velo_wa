<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReservationStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reservation extends Model
{
    use BelongsToTenant, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'contact_id',
        'conversation_id',
        'assigned_to',
        'code',
        'status',
        'starts_at',
        'ends_at',
        'party_size',
        'notes',
        'requested_at',
        'confirmed_at',
        'seated_at',
        'completed_at',
        'cancelled_at',
        'no_show_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'party_size' => 'integer',
            'requested_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'seated_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'no_show_at' => 'datetime',
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
}

