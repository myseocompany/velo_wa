import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import { Bot, QrCode, Shuffle, Zap } from 'lucide-react';

export default function SettingsIndex() {
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

                <div className="max-w-xl rounded-xl border border-gray-200 bg-white p-5">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <h2 className="text-base font-semibold text-gray-900">WhatsApp</h2>
                            <p className="mt-1 text-sm text-gray-500">
                                Conecta o desconecta tu número y revisa el estado de la instancia.
                            </p>
                        </div>
                        <QrCode className="h-6 w-6 text-brand-600" />
                    </div>

                    <div className="mt-4">
                        <Link
                            href="/settings/whatsapp"
                            className="inline-flex items-center rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"
                        >
                            Ir a conexión WhatsApp
                        </Link>
                    </div>
                </div>

                <div className="max-w-xl rounded-xl border border-gray-200 bg-white p-5">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <h2 className="text-base font-semibold text-gray-900">Reglas de asignación</h2>
                            <p className="mt-1 text-sm text-gray-500">
                                Define cómo se asignan automáticamente las conversaciones entrantes a los agentes.
                            </p>
                        </div>
                        <Shuffle className="h-6 w-6 text-brand-600" />
                    </div>

                    <div className="mt-4">
                        <Link
                            href="/settings/assignment-rules"
                            className="inline-flex items-center rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"
                        >
                            Gestionar reglas
                        </Link>
                    </div>
                </div>

                <div className="max-w-xl rounded-xl border border-gray-200 bg-white p-5">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <h2 className="text-base font-semibold text-gray-900">Respuestas rápidas</h2>
                            <p className="mt-1 text-sm text-gray-500">
                                Crea atajos para enviar mensajes frecuentes desde el inbox con `/`.
                            </p>
                        </div>
                        <Zap className="h-6 w-6 text-brand-600" />
                    </div>

                    <div className="mt-4">
                        <Link
                            href="/settings/quick-replies"
                            className="inline-flex items-center rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"
                        >
                            Gestionar respuestas
                        </Link>
                    </div>
                </div>

                <div className="max-w-xl rounded-xl border border-gray-200 bg-white p-5">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <h2 className="text-base font-semibold text-gray-900">Automatizaciones</h2>
                            <p className="mt-1 text-sm text-gray-500">
                                Responde, asigna o etiqueta conversaciones automáticamente según disparadores configurables.
                            </p>
                        </div>
                        <Bot className="h-6 w-6 text-brand-600" />
                    </div>

                    <div className="mt-4">
                        <Link
                            href="/settings/automations"
                            className="inline-flex items-center rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"
                        >
                            Gestionar automatizaciones
                        </Link>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
