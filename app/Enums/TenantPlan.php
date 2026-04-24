<?php

declare(strict_types=1);

namespace App\Enums;

enum TenantPlan: string
{
    case Trial  = 'trial';
    case Seed   = 'seed';
    case Grow   = 'grow';
    case Scale  = 'scale';

    /**
     * Resolve plan from the active Stripe price ID.
     */
    public static function fromPriceId(?string $priceId): self
    {
        return match ($priceId) {
            config('services.stripe.price_seed')  => self::Seed,
            config('services.stripe.price_grow')  => self::Grow,
            config('services.stripe.price_scale') => self::Scale,
            default                                => self::Trial,
        };
    }

    /**
     * Features available per plan (cumulative — higher plans include lower plan features).
     */
    public function can(string $feature): bool
    {
        return match ($feature) {
            // Available on all plans including trial
            'inbox', 'contacts', 'tasks', 'quick_replies' => true,

            // Menu digital available from Seed
            'menu' => $this->level() >= self::Seed->level(),

            // Pipeline, orders, reservations, loyalty, full automations: Grow+
            'pipeline', 'orders', 'reservations', 'loyalty',
            'automations_unlimited', 'dashboard_full'
                => $this->level() >= self::Grow->level(),

            // API access: Scale only
            'api_access' => $this === self::Scale,

            default => false,
        };
    }

    /**
     * Max agents allowed (-1 = unlimited).
     */
    public function maxAgents(): int
    {
        return match ($this) {
            self::Trial => 1,
            self::Seed  => 1,
            self::Grow  => 3,
            self::Scale => -1,
        };
    }

    /**
     * Max contacts allowed (-1 = unlimited).
     */
    public function maxContacts(): int
    {
        return match ($this) {
            self::Trial => 100,
            self::Seed  => 500,
            self::Grow  => 2000,
            self::Scale => -1,
        };
    }

    /**
     * Max automations allowed (-1 = unlimited).
     */
    public function maxAutomations(): int
    {
        return match ($this) {
            self::Trial => 1,
            self::Seed  => 3,
            self::Grow, self::Scale => -1,
        };
    }

    /**
     * Max WhatsApp lines allowed (-1 = unlimited).
     */
    public function maxWhatsAppLines(): int
    {
        return match ($this) {
            self::Trial => 1,
            self::Seed  => 1,
            self::Grow  => 3,
            self::Scale => -1,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Trial',
            self::Seed  => 'Semilla',
            self::Grow  => 'Crecer',
            self::Scale => 'Escalar',
        };
    }

    private function level(): int
    {
        return match ($this) {
            self::Trial => 0,
            self::Seed  => 1,
            self::Grow  => 2,
            self::Scale => 3,
        };
    }
}
