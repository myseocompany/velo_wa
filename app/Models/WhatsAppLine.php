<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WaStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsAppLine extends Model
{
    use BelongsToTenant, HasFactory, HasUuids, SoftDeletes;

    protected $table = 'whatsapp_lines';

    protected $fillable = [
        'tenant_id',
        'label',
        'instance_id',
        'status',
        'phone',
        'connected_at',
        'is_default',
        'health_consecutive_failures',
        'health_last_alert_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => WaStatus::class,
            'connected_at' => 'datetime',
            'is_default' => 'boolean',
            'health_consecutive_failures' => 'integer',
            'health_last_alert_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'whatsapp_line_id');
    }

    public function healthLogs(): HasMany
    {
        return $this->hasMany(WaHealthLog::class, 'whatsapp_line_id');
    }

    public function isConnected(): bool
    {
        return $this->status === WaStatus::Connected;
    }
}
