<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyAccount extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'contact_id',
        'points_balance',
        'total_earned',
        'total_redeemed',
    ];

    protected function casts(): array
    {
        return [
            'points_balance' => 'integer',
            'total_earned' => 'integer',
            'total_redeemed' => 'integer',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(LoyaltyEvent::class)->latest('created_at');
    }
}

