<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WaStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Cashier\Billable;

class Tenant extends Model
{
    // NOTE: Run `composer require laravel/cashier` before deploying
    use Billable, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'wa_instance_id',
        'wa_status',
        'wa_phone',
        'wa_connected_at',
        'max_agents',
        'max_contacts',
        'media_retention_days',
        'timezone',
        'business_hours',
        'auto_close_hours',
        'onboarding_completed_at',
    ];

    protected function casts(): array
    {
        return [
            'wa_status' => WaStatus::class,
            'wa_connected_at' => 'datetime',
            'business_hours' => 'array',
            'max_agents' => 'integer',
            'max_contacts' => 'integer',
            'media_retention_days' => 'integer',
            'auto_close_hours'          => 'integer',
            'onboarding_completed_at'   => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function quickReplies(): HasMany
    {
        return $this->hasMany(QuickReply::class);
    }

    public function assignmentRules(): HasMany
    {
        return $this->hasMany(AssignmentRule::class)->orderBy('priority');
    }

    public function automations(): HasMany
    {
        return $this->hasMany(Automation::class)->orderBy('priority');
    }

    public function isConnected(): bool
    {
        return $this->wa_status === WaStatus::Connected;
    }

    public function hasCompletedOnboarding(): bool
    {
        return $this->onboarding_completed_at !== null;
    }
}
