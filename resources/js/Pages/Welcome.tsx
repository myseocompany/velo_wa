import { Head, Link } from '@inertiajs/react';
import { MessageSquare, Zap, BarChart3, Users, Shield, CheckCircle, ArrowRight, Star } from 'lucide-react';

const FEATURES = [
    {
        icon: <MessageSquare className="h-6 w-6 text-ari-600" />,
        title: 'Inbox unificado',
        desc: 'Gestiona todas las conversaciones de WhatsApp de tu equipo en un solo lugar, con asignación y seguimiento en tiempo real.',
    },
    {
        icon: <Users className="h-6 w-6 text-ari-600" />,
        title: 'Equipo colaborativo',
        desc: 'Asigna conversaciones a agentes, define roles y mide el desempeño individual de cada miembro de tu equipo.',
    },
    {
        icon: <Zap className="h-6 w-6 text-ari-600" />,
        title: 'Automatizaciones',
        desc: 'Responde automáticamente fuera de horario, asigna por palabras clave y mueve deals según acciones del contacto.',
    },
    {
        icon: <BarChart3 className="h-6 w-6 text-ari-600" />,
        title: 'Métricas y pipeline',
        desc: 'Visualiza Dt1, tasas de cierre, deals por etapa y desempeño de agentes con dashboards en tiempo real.',
    },
    {
        icon: <Shield className="h-6 w-6 text-ari-600" />,
        title: 'Multi-tenant seguro',
        desc: 'Cada negocio tiene sus datos completamente aislados. Ningún agente ve información de otra cuenta, jamás.',
    },
    {
        icon: <CheckCircle className="h-6 w-6 text-ari-600" />,
        title: 'Sin bans de WhatsApp',
        desc: 'Límites de velocidad automáticos para proteger tu número. Reconexión automática si la sesión se interrumpe.',
    },
];

const PLANS = [
    {
        name: 'Starter',
        price: '$29',
        period: '/mes',
        desc: 'Perfecto para equipos pequeños.',
        features: ['3 agentes', '2.000 contactos', '1 número de WhatsApp', 'Automatizaciones básicas', 'Soporte por email'],
        cta: 'Empezar gratis',
        highlight: false,
    },
    {
        name: 'Growth',
        price: '$79',
        period: '/mes',
        desc: 'Para equipos en crecimiento.',
        features: ['10 agentes', '15.000 contactos', '1 número de WhatsApp', 'Automatizaciones avanzadas', 'Pipeline de ventas', 'Soporte prioritario'],
        cta: 'Prueba 14 días gratis',
        highlight: true,
    },
    {
        name: 'Scale',
        price: '$199',
        period: '/mes',
        desc: 'Para operaciones grandes.',
        features: ['Agentes ilimitados', 'Contactos ilimitados', 'Multi-número', 'API de integración', 'SLA 99.9%', 'Soporte dedicado'],
        cta: 'Hablar con ventas',
        highlight: false,
    },
];

const TESTIMONIALS = [
    {
        quote: 'Redujimos nuestro tiempo de primera respuesta de 45 minutos a menos de 3. AriCRM cambió la forma en que operamos.',
        author: 'Carlos M.',
        role: 'Director de Ventas, Inmobiliaria Éxito',
    },
    {
        quote: 'Por fin podemos ver qué está haciendo cada agente en tiempo real. El pipeline de WhatsApp es exactamente lo que necesitábamos.',
        author: 'Valentina R.',
        role: 'Gerente de Atención al Cliente, AutoPartes CR',
    },
];

export default function Welcome() {
    return (
        <div className="min-h-screen bg-gray-50 text-gray-900">
            <Head title="AriCRM — CRM de WhatsApp para equipos de ventas" />

            {/* Navbar */}
            <header className="sticky top-0 z-50 border-b border-gray-200 bg-white/80 backdrop-blur">
                <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                    <div className="flex items-center gap-2">
                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-ari-600">
                            <MessageSquare className="h-5 w-5 text-white" />
                        </div>
                        <span className="font-bold text-gray-900">AriCRM</span>
                    </div>
                    <nav className="hidden items-center gap-6 text-sm text-gray-500 md:flex">
                        <a href="#features" className="hover:text-gray-900 transition-colors">Características</a>
                        <a href="#pricing" className="hover:text-gray-900 transition-colors">Precios</a>
                    </nav>
                    <div className="flex items-center gap-3">
                        <Link href="/login" className="text-sm text-gray-500 hover:text-gray-900 transition-colors">
                            Iniciar sesión
                        </Link>
                        <Link
                            href="/register"
                            className="rounded-lg bg-ari-600 px-4 py-2 text-sm font-semibold text-white hover:bg-ari-700 transition-colors"
                        >
                            Empezar gratis
                        </Link>
                    </div>
                </div>
            </header>

            {/* Hero */}
            <section className="relative overflow-hidden py-24 text-center bg-white">
                <div className="pointer-events-none absolute inset-0 flex items-center justify-center">
                    <div className="h-96 w-96 rounded-full bg-ari-100/60 blur-3xl" />
                </div>
                <div className="relative mx-auto max-w-4xl px-6">
                    <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-ari-200 bg-ari-50 px-3 py-1 text-xs text-ari-700">
                        <Star size={12} />
                        WhatsApp CRM para equipos de ventas
                    </div>
                    <h1 className="text-4xl font-extrabold leading-tight tracking-tight text-gray-900 md:text-6xl">
                        Cierra más ventas<br />
                        <span className="text-ari-600">desde WhatsApp</span>
                    </h1>
                    <p className="mx-auto mt-6 max-w-2xl text-lg text-gray-500">
                        AriCRM convierte tu WhatsApp en un CRM completo. Gestiona contactos, asigna conversaciones a tu equipo, automatiza respuestas y mide resultados — todo en un solo lugar.
                    </p>
                    <div className="mt-10 flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
                        <Link
                            href="/register"
                            className="flex items-center gap-2 rounded-xl bg-ari-600 px-8 py-4 text-base font-bold text-white hover:bg-ari-700 transition-colors"
                        >
                            Empezar gratis
                            <ArrowRight size={18} />
                        </Link>
                        <a
                            href="#features"
                            className="rounded-xl border border-gray-200 px-8 py-4 text-base text-gray-700 hover:bg-gray-50 transition-colors"
                        >
                            Ver cómo funciona
                        </a>
                    </div>
                    <p className="mt-4 text-xs text-gray-400">
                        14 días gratis · Sin tarjeta de crédito · Cancela cuando quieras
                    </p>
                </div>
            </section>

            {/* Features */}
            <section id="features" className="py-20 bg-gray-50">
                <div className="mx-auto max-w-6xl px-6">
                    <div className="mb-12 text-center">
                        <h2 className="text-3xl font-bold text-gray-900">Todo lo que necesita tu equipo</h2>
                        <p className="mt-3 text-gray-500">Diseñado para equipos de ventas y atención al cliente que usan WhatsApp.</p>
                    </div>
                    <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                        {FEATURES.map(f => (
                            <div key={f.title} className="rounded-xl border border-gray-200 bg-white p-6 hover:border-ari-200 transition-colors">
                                <div className="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-ari-50">
                                    {f.icon}
                                </div>
                                <h3 className="mb-2 font-semibold text-gray-900">{f.title}</h3>
                                <p className="text-sm text-gray-500">{f.desc}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* Testimonials */}
            <section className="py-20 border-y border-gray-200 bg-white">
                <div className="mx-auto max-w-4xl px-6">
                    <h2 className="mb-12 text-center text-3xl font-bold text-gray-900">Lo que dicen nuestros clientes</h2>
                    <div className="grid gap-6 md:grid-cols-2">
                        {TESTIMONIALS.map(t => (
                            <div key={t.author} className="rounded-xl border border-gray-200 bg-gray-50 p-6">
                                <p className="mb-4 text-gray-600 italic">"{t.quote}"</p>
                                <p className="font-semibold text-gray-900">{t.author}</p>
                                <p className="text-xs text-gray-500">{t.role}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* Pricing */}
            <section id="pricing" className="py-20 bg-gray-50">
                <div className="mx-auto max-w-6xl px-6">
                    <div className="mb-12 text-center">
                        <h2 className="text-3xl font-bold text-gray-900">Precios simples y transparentes</h2>
                        <p className="mt-3 text-gray-500">Empieza gratis. Escala cuando lo necesites.</p>
                    </div>
                    <div className="grid gap-6 md:grid-cols-3">
                        {PLANS.map(plan => (
                            <div
                                key={plan.name}
                                className={`relative rounded-2xl border p-8 ${
                                    plan.highlight
                                        ? 'border-ari-600 bg-ari-50'
                                        : 'border-gray-200 bg-white'
                                }`}
                            >
                                {plan.highlight && (
                                    <div className="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-ari-600 px-3 py-0.5 text-xs font-bold text-white">
                                        Más popular
                                    </div>
                                )}
                                <p className="font-semibold text-gray-500">{plan.name}</p>
                                <div className="mt-2 flex items-end gap-1">
                                    <span className="text-4xl font-extrabold text-gray-900">{plan.price}</span>
                                    <span className="mb-1 text-gray-400">{plan.period}</span>
                                </div>
                                <p className="mt-1 text-sm text-gray-500">{plan.desc}</p>
                                <ul className="my-6 space-y-2">
                                    {plan.features.map(f => (
                                        <li key={f} className="flex items-center gap-2 text-sm text-gray-600">
                                            <CheckCircle size={14} className="shrink-0 text-ari-600" />
                                            {f}
                                        </li>
                                    ))}
                                </ul>
                                <Link
                                    href="/register"
                                    className={`block rounded-xl py-3 text-center text-sm font-semibold transition-colors ${
                                        plan.highlight
                                            ? 'bg-ari-600 text-white hover:bg-ari-700'
                                            : 'border border-gray-200 text-gray-700 hover:bg-gray-50'
                                    }`}
                                >
                                    {plan.cta}
                                </Link>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* CTA final */}
            <section className="py-20 text-center bg-white">
                <div className="mx-auto max-w-2xl px-6">
                    <h2 className="text-3xl font-bold text-gray-900">¿Listo para empezar?</h2>
                    <p className="mt-4 text-gray-500">
                        Únete a cientos de equipos que ya gestionan sus ventas desde WhatsApp con AriCRM.
                    </p>
                    <Link
                        href="/register"
                        className="mt-8 inline-flex items-center gap-2 rounded-xl bg-ari-600 px-8 py-4 font-bold text-white hover:bg-ari-700 transition-colors"
                    >
                        Crear cuenta gratis
                        <ArrowRight size={18} />
                    </Link>
                </div>
            </section>

            {/* Footer */}
            <footer className="border-t border-gray-200 bg-gray-900 py-10">
                <div className="mx-auto max-w-6xl px-6 flex flex-col items-center gap-4 md:flex-row md:justify-between">
                    <div className="flex items-center gap-2">
                        <div className="flex h-6 w-6 items-center justify-center rounded bg-ari-600">
                            <MessageSquare className="h-4 w-4 text-white" />
                        </div>
                        <span className="text-sm font-semibold text-white">AriCRM</span>
                    </div>
                    <p className="text-xs text-gray-500">© {new Date().getFullYear()} AriCRM. Todos los derechos reservados.</p>
                    <div className="flex gap-4 text-xs text-gray-500">
                        <a href="#" className="hover:text-gray-300">Privacidad</a>
                        <a href="#" className="hover:text-gray-300">Términos</a>
                    </div>
                </div>
            </footer>
        </div>
    );
}
