<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TenantPlan;
use App\Enums\WaStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
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
        'onboarding_vertical',
        'wa_health_consecutive_failures',
        'wa_health_last_alert_at',
        'webhook_url',
        'webhook_secret',
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
            'wa_health_consecutive_failures' => 'integer',
            'wa_health_last_alert_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantUserMembership::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function whatsappLines(): HasMany
    {
        return $this->hasMany(WhatsAppLine::class);
    }

    public function defaultWhatsAppLine(): HasOne
    {
        return $this->hasOne(WhatsAppLine::class)->where('is_default', true);
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

    public function hasAnyConnectedLine(): bool
    {
        return $this->whatsappLines()->where('status', WaStatus::Connected)->exists();
    }

    public function getOrCreateDefaultLine(): WhatsAppLine
    {
        return DB::transaction(function (): WhatsAppLine {
            Tenant::whereKey($this->id)->lockForUpdate()->first();

            $default = $this->defaultWhatsAppLine()->first();
            if ($default) {
                return $default;
            }

            $first = $this->whatsappLines()->oldest()->first();
            if ($first) {
                $first->update(['is_default' => true]);
                return $first;
            }

            return $this->whatsappLines()->create([
                'label' => 'Principal',
                'is_default' => true,
                'instance_id' => $this->wa_instance_id,
                'status' => $this->wa_status ?? WaStatus::Disconnected,
                'phone' => $this->wa_phone,
                'connected_at' => $this->wa_connected_at,
                'health_consecutive_failures' => $this->wa_health_consecutive_failures ?? 0,
                'health_last_alert_at' => $this->wa_health_last_alert_at,
            ]);
        });
    }

    public function hasCompletedOnboarding(): bool
    {
        return $this->onboarding_completed_at !== null;
    }

    public function currentPlan(): TenantPlan
    {
        if ($this->onTrial() && ! $this->subscribed('default')) {
            return TenantPlan::Trial;
        }

        $priceId = $this->subscription('default')?->stripe_price;

        return TenantPlan::fromPriceId($priceId);
    }

    public function canUse(string $feature): bool
    {
        return $this->currentPlan()->can($feature);
    }

    public function currentWhatsAppLinesCount(): int
    {
        return $this->whatsappLines()->count();
    }
}
