import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { CheckCircle, CreditCard, ExternalLink, AlertCircle } from 'lucide-react';

interface Plan {
    name: string;
    price_id: string | null;
    price: string;
    max_agents: number | null;
    max_contacts: number | null;
}

interface Subscription {
    stripe_status: string;
    stripe_price: string | null;
    ends_at: string | null;
    trial_ends_at: string | null;
}

const PLAN_FEATURES: Record<string, string[]> = {
    starter: ['3 agentes', '2.000 contactos', 'Automatizaciones básicas', 'Soporte por email'],
    growth:  ['10 agentes', '15.000 contactos', 'Automatizaciones avanzadas', 'Pipeline de ventas', 'Soporte prioritario'],
    scale:   ['Agentes ilimitados', 'Contactos ilimitados', 'Multi-número', 'API de integración', 'SLA 99.9%', 'Soporte dedicado'],
};

const STATUS_LABELS: Record<string, string> = {
    active:             'Activa',
    trialing:           'En período de prueba',
    past_due:           'Pago pendiente',
    canceled:           'Cancelada',
    incomplete:         'Incompleta',
    incomplete_expired: 'Expirada',
    paused:             'Pausada',
};

export default function Billing({
    plans,
    subscription,
    pm_type,
    pm_last_four,
    trial_ends_at,
    on_trial,
    subscribed,
}: {
    plans: Record<string, Plan>;
    subscription: Subscription | null;
    pm_type: string | null;
    pm_last_four: string | null;
    trial_ends_at: string | null;
    on_trial: boolean;
    subscribed: boolean;
}) {
    const handleCheckout = (plan: string) => {
        router.post(`/settings/billing/checkout/${plan}`);
    };

    const handlePortal = () => {
        router.post('/settings/billing/portal');
    };

    const handleCancel = () => {
        if (confirm('¿Cancelar la suscripción? Tendrás acceso hasta el final del período actual.')) {
            router.post('/settings/billing/cancel');
        }
    };

    const handleResume = () => {
        router.post('/settings/billing/resume');
    };

    const isCanceled = subscription?.stripe_status === 'canceled' || (subscription?.ends_at !== null && subscription?.ends_at !== undefined);

    return (
        <AppLayout>
            <Head title="Facturación — AriCRM" />
            <div className="p-6 space-y-6 max-w-4xl">
                <h1 className="text-xl font-bold text-white">Facturación</h1>

                {/* Trial / Status banner */}
                {on_trial && trial_ends_at && (
                    <div className="flex items-center gap-3 rounded-xl border border-amber-700 bg-amber-900/20 p-4">
                        <AlertCircle className="h-5 w-5 shrink-0 text-amber-400" />
                        <p className="text-sm text-amber-300">
                            Tu período de prueba termina el{' '}
                            <strong>{new Date(trial_ends_at).toLocaleDateString('es')}</strong>.
                            Suscríbete para mantener el acceso.
                        </p>
                    </div>
                )}

                {/* Current subscription */}
                {subscription && (
                    <div className="rounded-xl border border-gray-800 bg-gray-900 p-5 space-y-4">
                        <h2 className="text-sm font-semibold text-white">Suscripción actual</h2>
                        <div className="flex flex-wrap items-center gap-4">
                            <span className={`rounded-full px-3 py-1 text-xs font-medium ${
                                subscription.stripe_status === 'active' || subscription.stripe_status === 'trialing'
                                    ? 'bg-green-500/20 text-green-400'
                                    : 'bg-red-500/20 text-red-400'
                            }`}>
                                {STATUS_LABELS[subscription.stripe_status] ?? subscription.stripe_status}
                            </span>

                            {pm_last_four && (
                                <div className="flex items-center gap-2 text-sm text-gray-400">
                                    <CreditCard size={14} />
                                    {pm_type} •••• {pm_last_four}
                                </div>
                            )}

                            {subscription.ends_at && (
                                <p className="text-xs text-gray-500">
                                    Acceso hasta: {new Date(subscription.ends_at).toLocaleDateString('es')}
                                </p>
                            )}
                        </div>

                        <div className="flex flex-wrap gap-3">
                            <button
                                onClick={handlePortal}
                                className="flex items-center gap-2 rounded-lg border border-gray-700 px-4 py-2 text-sm text-gray-300 hover:bg-gray-800"
                            >
                                <ExternalLink size={14} />
                                Gestionar en Stripe
                            </button>

                            {!isCanceled ? (
                                <button
                                    onClick={handleCancel}
                                    className="rounded-lg border border-red-800 px-4 py-2 text-sm text-red-400 hover:bg-red-900/20"
                                >
                                    Cancelar suscripción
                                </button>
                            ) : (
                                <button
                                    onClick={handleResume}
                                    className="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-amber-400"
                                >
                                    Reactivar suscripción
                                </button>
                            )}
                        </div>
                    </div>
                )}

                {/* Plans */}
                {!subscribed && (
                    <div>
                        <h2 className="mb-4 text-sm font-semibold text-white">Elige un plan</h2>
                        <div className="grid gap-4 md:grid-cols-3">
                            {Object.entries(plans).map(([key, plan]) => (
                                <div
                                    key={key}
                                    className={`relative rounded-xl border p-6 ${
                                        key === 'growth'
                                            ? 'border-amber-500 bg-amber-500/5'
                                            : 'border-gray-800 bg-gray-900'
                                    }`}
                                >
                                    {key === 'growth' && (
                                        <div className="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-amber-500 px-3 py-0.5 text-xs font-bold text-gray-900">
                                            Más popular
                                        </div>
                                    )}
                                    <p className="font-semibold text-white">{plan.name}</p>
                                    <p className="mt-1 text-2xl font-bold text-white">{plan.price}</p>

                                    <ul className="my-4 space-y-1.5">
                                        {(PLAN_FEATURES[key] ?? []).map(f => (
                                            <li key={f} className="flex items-center gap-2 text-xs text-gray-400">
                                                <CheckCircle size={12} className="text-amber-400 shrink-0" />
                                                {f}
                                            </li>
                                        ))}
                                    </ul>

                                    <button
                                        onClick={() => handleCheckout(key)}
                                        className={`w-full rounded-lg py-2 text-sm font-semibold transition-colors ${
                                            key === 'growth'
                                                ? 'bg-amber-500 text-gray-900 hover:bg-amber-400'
                                                : 'border border-gray-700 text-gray-300 hover:bg-gray-800'
                                        }`}
                                    >
                                        {key === 'scale' ? 'Hablar con ventas' : 'Suscribirse'}
                                    </button>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                <p className="text-xs text-gray-600">
                    Los pagos son procesados de forma segura por Stripe. AriCRM no almacena datos de tarjetas.
                </p>
            </div>
        </AppLayout>
    );
}
