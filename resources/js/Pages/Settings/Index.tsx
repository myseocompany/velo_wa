import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { Activity, Bot, Clock, DatabaseZap, QrCode, Shuffle, Users, Zap } from 'lucide-react';
import { PageProps } from '@/types';

interface SettingCard {
    title: string;
    description: string;
    icon: React.ReactNode;
    href: string;
    label: string;
    roles?: ('owner' | 'admin' | 'agent')[];
}

const CARDS: SettingCard[] = [
    {
        title: 'General',
        description: 'Zona horaria, horario laboral y cierre automático de conversaciones.',
        icon: <Clock className="h-6 w-6 text-ari-600" />,
        href: '/settings/general',
        label: 'Abrir',
        roles: ['owner'],
    },
    {
        title: 'Equipo',
        description: 'Invita miembros, cambia roles y gestiona el acceso al sistema.',
        icon: <Users className="h-6 w-6 text-ari-600" />,
        href: '/settings/team',
        label: 'Gestionar equipo',
        roles: ['owner', 'admin'],
    },
    {
        title: 'WhatsApp',
        description: 'Conecta o desconecta tu número y revisa el estado de la instancia.',
        icon: <QrCode className="h-6 w-6 text-ari-600" />,
        href: '/settings/whatsapp',
        label: 'Ir a conexión WhatsApp',
        roles: ['owner', 'admin'],
    },
    {
        title: 'Reglas de asignación',
        description: 'Define cómo se asignan automáticamente las conversaciones entrantes.',
        icon: <Shuffle className="h-6 w-6 text-ari-600" />,
        href: '/settings/assignment-rules',
        label: 'Gestionar reglas',
        roles: ['owner', 'admin'],
    },
    {
        title: 'Respuestas rápidas',
        description: 'Crea atajos para enviar mensajes frecuentes desde el inbox con `/`.',
        icon: <Zap className="h-6 w-6 text-ari-600" />,
        href: '/settings/quick-replies',
        label: 'Gestionar respuestas',
        roles: ['owner', 'admin'],
    },
    {
        title: 'Automatizaciones',
        description: 'Responde, asigna o etiqueta conversaciones automáticamente.',
        icon: <Bot className="h-6 w-6 text-ari-600" />,
        href: '/settings/automations',
        label: 'Gestionar automatizaciones',
        roles: ['owner', 'admin'],
    },
    {
        title: 'Calidad de datos',
        description: 'Detecta y fusiona contactos duplicados que comparten el mismo número.',
        icon: <DatabaseZap className="h-6 w-6 text-ari-600" />,
        href: '/settings/data-quality',
        label: 'Revisar duplicados',
        roles: ['owner', 'admin'],
    },
    {
        title: 'Registro de actividad',
        description: 'Auditoría de acciones realizadas por los miembros del equipo.',
        icon: <Activity className="h-6 w-6 text-ari-600" />,
        href: '/settings/activity',
        label: 'Ver registro',
        roles: ['owner', 'admin'],
    },
];

export default function SettingsIndex() {
    const { auth } = usePage<PageProps>().props;
    const role = auth.user.role;

    const visibleCards = CARDS.filter(c => !c.roles || c.roles.includes(role as any));

    return (
        <AppLayout title="Configuración">
            <Head title="Configuración" />

            <div className="space-y-5 p-6">
                <div>
                    <h1 className="text-2xl font-semibold text-gray-900">Configuración</h1>
                    <p className="mt-1 text-sm text-gray-500">
                        Gestiona integraciones y preferencias del tenant.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 max-w-3xl">
                    {visibleCards.map(card => (
                        <div key={card.href} className="rounded-xl border border-gray-200 bg-white p-5">
                            <div className="flex items-start justify-between gap-4">
                                <div className="flex-1">
                                    <h2 className="text-base font-semibold text-gray-900">{card.title}</h2>
                                    <p className="mt-1 text-sm text-gray-500">{card.description}</p>
                                </div>
                                {card.icon}
                            </div>
                            <div className="mt-4">
                                <Link
                                    href={card.href}
                                    className="inline-flex items-center rounded-lg bg-ari-600 px-4 py-2 text-sm font-medium text-white hover:bg-ari-700"
                                >
                                    {card.label}
                                </Link>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
