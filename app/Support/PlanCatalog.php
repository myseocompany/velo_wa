<?php

declare(strict_types=1);

namespace App\Support;

class PlanCatalog
{
    /**
     * Public-facing plan metadata for landing and marketing screens.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function publicPlans(): array
    {
        return [
            'seed' => [
                'name' => 'Semilla',
                'price' => 'USD 19',
                'period' => '/mes',
                'desc' => 'Para iniciar con operación ligera.',
                'features' => ['1 agente', '500 contactos', 'Inbox', 'Contactos', 'Menú digital', 'Tareas', '3 automatizaciones'],
                'cta' => 'Empezar gratis',
                'highlight' => false,
            ],
            'grow' => [
                'name' => 'Crecer',
                'price' => 'USD 29',
                'period' => '/mes',
                'desc' => 'Para equipos en crecimiento.',
                'features' => ['3 agentes', '2.000 contactos', 'Todo Semilla', 'Pipeline Kanban', 'Pedidos', 'Reservas', 'Automatizaciones ilimitadas'],
                'cta' => 'Prueba 14 días gratis',
                'highlight' => true,
            ],
            'scale' => [
                'name' => 'Escalar',
                'price' => 'USD 59',
                'period' => '/mes',
                'desc' => 'Para operación avanzada.',
                'features' => ['Agentes ilimitados', 'Contactos ilimitados', 'Todo Crecer', 'API access', 'Soporte prioritario'],
                'cta' => 'Hablar con ventas',
                'highlight' => false,
            ],
        ];
    }

    /**
     * Billing plan metadata with Stripe price config keys.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function billingPlans(): array
    {
        return [
            'seed' => [
                'name' => 'Semilla',
                'price_id_key' => 'services.stripe.price_seed',
                'price' => 'USD 19/mes',
                'max_agents' => 1,
                'max_contacts' => 500,
                'features' => ['Inbox', 'Contactos', 'Menú digital', 'Tareas', '3 automatizaciones'],
            ],
            'grow' => [
                'name' => 'Crecer',
                'price_id_key' => 'services.stripe.price_grow',
                'price' => 'USD 29/mes',
                'max_agents' => 3,
                'max_contacts' => 2000,
                'features' => ['Todo Semilla', 'Pipeline Kanban', 'Pedidos', 'Reservas', 'Automatizaciones ilimitadas'],
            ],
            'scale' => [
                'name' => 'Escalar',
                'price_id_key' => 'services.stripe.price_scale',
                'price' => 'USD 59/mes',
                'max_agents' => null,
                'max_contacts' => null,
                'features' => ['Todo Crecer', 'Agentes ilimitados', 'Contactos ilimitados', 'API access', 'Soporte prioritario'],
            ],
        ];
    }
}
