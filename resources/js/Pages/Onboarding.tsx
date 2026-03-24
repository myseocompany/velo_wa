import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { useCallback, useState } from 'react';
import { CheckCircle, Loader2, MessageSquare, QrCode, Smartphone, Users, Zap } from 'lucide-react';
import { useTenantChannel } from '@/hooks/useEcho';

interface TenantInfo {
    id: string;
    name: string;
    wa_status: string;
    wa_phone: string | null;
}

const STEPS = [
    { id: 1, label: 'Bienvenida' },
    { id: 2, label: 'WhatsApp' },
    { id: 3, label: '¡Listo!' },
];

function StepIndicator({ current }: { current: number }) {
    return (
        <div className="flex items-center justify-center gap-2 mb-8">
            {STEPS.map((step, i) => (
                <div key={step.id} className="flex items-center gap-2">
                    <div className={`flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold transition-colors ${
                        current === step.id
                            ? 'bg-amber-500 text-gray-900'
                            : current > step.id
                            ? 'bg-green-500 text-white'
                            : 'bg-gray-800 text-gray-500'
                    }`}>
                        {current > step.id ? <CheckCircle size={16} /> : step.id}
                    </div>
                    <span className={`text-xs ${current === step.id ? 'text-white' : 'text-gray-600'}`}>
                        {step.label}
                    </span>
                    {i < STEPS.length - 1 && (
                        <div className={`h-px w-8 ${current > step.id ? 'bg-green-500' : 'bg-gray-800'}`} />
                    )}
                </div>
            ))}
        </div>
    );
}

interface WaStatusPayload {
    status: string;
    phone: string | null;
    qr_code: string | null;
}

export default function Onboarding({ tenant }: { tenant: TenantInfo }) {
    const [step, setStep] = useState(1);
    const [completing, setCompleting] = useState(false);

    const [waStatus, setWaStatus] = useState(tenant.wa_status);
    const [qrCode, setQrCode]     = useState<string | null>(null);
    const [waLoading, setWaLoading] = useState(false);
    const [waError, setWaError]   = useState<string | null>(null);

    const handleWaStatusUpdate = useCallback((data: unknown) => {
        const payload = data as WaStatusPayload;
        setWaStatus(payload.status);
        if (payload.qr_code) setQrCode(payload.qr_code);
        if (payload.status === 'connected') setQrCode(null);
    }, []);

    useTenantChannel(tenant.id, 'wa.status.updated', handleWaStatusUpdate);

    async function handleConnect() {
        setWaLoading(true);
        setWaError(null);
        try {
            const res = await axios.post('/api/v1/whatsapp/connect');
            setQrCode(res.data.qr_code ?? null);
            setWaStatus('qr_pending');
        } catch (e: unknown) {
            const msg = axios.isAxiosError(e)
                ? (e.response?.data?.message ?? 'Error al conectar')
                : 'Error al conectar';
            setWaError(msg);
        } finally {
            setWaLoading(false);
        }
    }

    const handleComplete = () => {
        setCompleting(true);
        router.post('/onboarding/complete', {}, {
            onError: () => setCompleting(false),
        });
    };

    return (
        <div className="min-h-screen bg-gray-950 flex flex-col items-center justify-center px-4">
            <Head title="Configuración inicial — AriCRM" />

            {/* Logo */}
            <div className="mb-8 flex items-center gap-3">
                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-500">
                    <MessageSquare className="h-7 w-7 text-gray-900" />
                </div>
                <div>
                    <p className="text-xl font-bold text-white">AriCRM</p>
                    <p className="text-xs text-gray-500">Configuración inicial</p>
                </div>
            </div>

            <div className="w-full max-w-lg">
                <StepIndicator current={step} />

                {/* ── Step 1: Bienvenida ─────────────────────────── */}
                {step === 1 && (
                    <div className="rounded-2xl border border-gray-800 bg-gray-900 p-8 space-y-6">
                        <div className="text-center">
                            <h1 className="text-2xl font-bold text-white">
                                ¡Hola, {tenant.name}!
                            </h1>
                            <p className="mt-2 text-gray-400">
                                Vamos a configurar tu cuenta en 3 pasos. Solo tomará 2 minutos.
                            </p>
                        </div>

                        <div className="space-y-3">
                            {[
                                { icon: <Smartphone size={18} />, label: 'Conecta tu WhatsApp', desc: 'Escanea un QR con tu teléfono' },
                                { icon: <Users size={18} />, label: 'Invita a tu equipo', desc: 'Añade agentes para gestionar conversaciones' },
                                { icon: <Zap size={18} />, label: 'Automatiza respuestas', desc: 'Configura mensajes automáticos' },
                            ].map(item => (
                                <div key={item.label} className="flex items-start gap-3 rounded-lg bg-gray-800/50 p-3">
                                    <div className="mt-0.5 text-amber-400">{item.icon}</div>
                                    <div>
                                        <p className="text-sm font-medium text-white">{item.label}</p>
                                        <p className="text-xs text-gray-500">{item.desc}</p>
                                    </div>
                                </div>
                            ))}
                        </div>

                        <button
                            onClick={() => setStep(2)}
                            className="w-full rounded-xl bg-amber-500 py-3 font-semibold text-gray-900 hover:bg-amber-400 transition-colors"
                        >
                            Empezar →
                        </button>
                    </div>
                )}

                {/* ── Step 2: Conectar WhatsApp ───────────────────── */}
                {step === 2 && (
                    <div className="rounded-2xl border border-gray-800 bg-gray-900 p-8 space-y-6">
                        <div className="text-center">
                            <QrCode className="mx-auto mb-3 h-10 w-10 text-amber-400" />
                            <h2 className="text-xl font-bold text-white">Conecta WhatsApp</h2>
                            <p className="mt-1 text-sm text-gray-400">
                                Genera el código QR y escanéalo desde tu teléfono en{' '}
                                <span className="text-white">WhatsApp → Dispositivos vinculados</span>.
                            </p>
                        </div>

                        {/* Conectado */}
                        {waStatus === 'connected' && (
                            <div className="rounded-xl border border-green-700 bg-green-900/20 p-4 text-center">
                                <CheckCircle className="mx-auto mb-2 h-8 w-8 text-green-400" />
                                <p className="font-semibold text-green-300">¡WhatsApp conectado!</p>
                                {tenant.wa_phone && (
                                    <p className="mt-1 text-sm text-gray-400">{tenant.wa_phone}</p>
                                )}
                            </div>
                        )}

                        {/* QR generado */}
                        {qrCode && waStatus !== 'connected' && (
                            <div className="rounded-xl border border-amber-700/50 bg-amber-900/10 p-4 text-center space-y-3">
                                <img
                                    src={qrCode}
                                    alt="WhatsApp QR Code"
                                    className="mx-auto h-56 w-56 rounded-lg bg-white p-2"
                                />
                                <p className="text-xs text-amber-400">
                                    El código expira en 60 segundos. Si expira, haz clic en "Generar QR" nuevamente.
                                </p>
                            </div>
                        )}

                        {/* Sin QR y no conectado */}
                        {!qrCode && waStatus !== 'connected' && (
                            <div className="rounded-xl border border-gray-700 bg-gray-800/50 p-6 text-center space-y-4">
                                <Smartphone className="mx-auto h-10 w-10 text-gray-500" />
                                <p className="text-sm text-gray-400">
                                    Haz clic en el botón para generar el código QR de conexión.
                                </p>
                                <button
                                    onClick={handleConnect}
                                    disabled={waLoading}
                                    className="inline-flex items-center gap-2 rounded-xl bg-amber-500 px-6 py-2.5 font-semibold text-gray-900 hover:bg-amber-400 disabled:opacity-50 transition-colors"
                                >
                                    {waLoading && <Loader2 className="h-4 w-4 animate-spin" />}
                                    Generar QR
                                </button>
                            </div>
                        )}

                        {/* Botón regenerar cuando ya hay QR visible */}
                        {qrCode && waStatus !== 'connected' && (
                            <button
                                onClick={handleConnect}
                                disabled={waLoading}
                                className="w-full rounded-xl border border-gray-700 py-2.5 text-sm text-gray-400 hover:bg-gray-800 disabled:opacity-50 transition-colors inline-flex items-center justify-center gap-2"
                            >
                                {waLoading && <Loader2 className="h-4 w-4 animate-spin" />}
                                Generar nuevo QR
                            </button>
                        )}

                        {/* Error */}
                        {waError && (
                            <div className="rounded-lg bg-red-900/20 border border-red-700 p-3 text-sm text-red-400">
                                {waError}
                            </div>
                        )}

                        <div className="flex gap-3">
                            <button
                                onClick={() => setStep(1)}
                                className="flex-1 rounded-xl border border-gray-700 py-3 text-sm text-gray-400 hover:bg-gray-800 transition-colors"
                            >
                                ← Atrás
                            </button>
                            <button
                                onClick={() => setStep(3)}
                                className="flex-1 rounded-xl bg-amber-500 py-3 font-semibold text-gray-900 hover:bg-amber-400 transition-colors"
                            >
                                {waStatus === 'connected' ? 'Continuar →' : 'Omitir por ahora →'}
                            </button>
                        </div>
                    </div>
                )}

                {/* ── Step 3: Completar ───────────────────────────── */}
                {step === 3 && (
                    <div className="rounded-2xl border border-gray-800 bg-gray-900 p-8 space-y-6 text-center">
                        <div className="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-amber-500/10">
                            <CheckCircle className="h-10 w-10 text-amber-400" />
                        </div>

                        <div>
                            <h2 className="text-2xl font-bold text-white">¡Todo listo!</h2>
                            <p className="mt-2 text-gray-400">
                                Tu cuenta está configurada. Ya puedes empezar a gestionar tus conversaciones de WhatsApp.
                            </p>
                        </div>

                        <div className="rounded-xl bg-gray-800/50 p-4 text-left space-y-2">
                            <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">Próximos pasos</p>
                            <ul className="space-y-1.5 text-sm text-gray-400">
                                <li className="flex items-center gap-2">
                                    <span className="h-1.5 w-1.5 rounded-full bg-amber-400" />
                                    Invita agentes desde <span className="text-white">Ajustes → Equipo</span>
                                </li>
                                <li className="flex items-center gap-2">
                                    <span className="h-1.5 w-1.5 rounded-full bg-amber-400" />
                                    Configura respuestas rápidas en <span className="text-white">Ajustes → Respuestas</span>
                                </li>
                                <li className="flex items-center gap-2">
                                    <span className="h-1.5 w-1.5 rounded-full bg-amber-400" />
                                    Activa automatizaciones desde <span className="text-white">Ajustes → Automatizaciones</span>
                                </li>
                            </ul>
                        </div>

                        <button
                            onClick={handleComplete}
                            disabled={completing}
                            className="w-full rounded-xl bg-amber-500 py-3 font-semibold text-gray-900 hover:bg-amber-400 disabled:opacity-50 transition-colors"
                        >
                            {completing ? 'Entrando al panel…' : 'Ir al panel →'}
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
}
