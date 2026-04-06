import AppLayout from '@/Layouts/AppLayout';
import { useTenantChannel } from '@/hooks/useEcho';
import { PageProps, WaStatus } from '@/types';
import { usePage } from '@inertiajs/react';
import axios from 'axios';
import { CheckCircle, Loader2, QrCode, RefreshCw, WifiOff } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

interface WaStatusPayload {
    status: WaStatus;
    phone: string | null;
    connected_at: string | null;
    qr_code: string | null;
}

interface HealthLog {
    id: string;
    instance_name: string;
    state: string | null;
    is_healthy: boolean;
    response_ms: number | null;
    error_message: string | null;
    checked_at: string | null;
}

interface HealthMeta {
    consecutive_failures: number;
    last_alert_at: string | null;
}

function StatusBadge({ status }: { status: WaStatus }) {
    const map: Record<WaStatus, { label: string; className: string }> = {
        connected:    { label: 'Conectado',      className: 'bg-ari-100 text-ari-800' },
        qr_pending:   { label: 'Esperando QR',   className: 'bg-yellow-100 text-yellow-800' },
        disconnected: { label: 'Desconectado',   className: 'bg-gray-100 text-gray-600' },
        banned:       { label: 'Bloqueado',       className: 'bg-red-100 text-red-800' },
    };
    const { label, className } = map[status] ?? map.disconnected;
    return (
        <span className={`inline-flex items-center rounded-full px-3 py-1 text-sm font-medium ${className}`}>
            {label}
        </span>
    );
}

export default function WhatsApp() {
    const { auth } = usePage<PageProps>().props;
    const tenant = auth.tenant;

    const [status, setStatus]       = useState<WaStatus>(tenant.wa_status);
    const [phone, setPhone]         = useState<string | null>(tenant.wa_phone);
    const [connectedAt, setConnectedAt] = useState<string | null>(tenant.wa_connected_at);
    const [qrCode, setQrCode]       = useState<string | null>(null);
    const [loading, setLoading]     = useState(false);
    const [error, setError]         = useState<string | null>(null);
    const [healthLogs, setHealthLogs] = useState<HealthLog[]>([]);
    const [healthMeta, setHealthMeta] = useState<HealthMeta>({ consecutive_failures: 0, last_alert_at: null });
    const [healthLoading, setHealthLoading] = useState(false);

    // Real-time status updates
    const handleStatusUpdate = useCallback((data: unknown) => {
        const payload = data as WaStatusPayload;
        setStatus(payload.status);
        setPhone(payload.phone ?? null);
        setConnectedAt(payload.connected_at ?? null);
        if (payload.qr_code) {
            setQrCode(payload.qr_code);
        }
        if (payload.status === 'connected') {
            setQrCode(null);
        }
    }, []);

    useTenantChannel(tenant.id, 'wa.status.updated', handleStatusUpdate);

    async function loadHealthLogs() {
        setHealthLoading(true);
        try {
            const res = await axios.get<{ data: HealthLog[]; meta?: HealthMeta }>('/api/v1/whatsapp/health-logs', {
                params: { limit: 20 },
            });
            setHealthLogs(res.data.data ?? []);
            setHealthMeta(res.data.meta ?? { consecutive_failures: 0, last_alert_at: null });
        } finally {
            setHealthLoading(false);
        }
    }

    useEffect(() => {
        loadHealthLogs();
    }, []);

    async function handleConnect() {
        setLoading(true);
        setError(null);
        try {
            const res = await axios.post('/api/v1/whatsapp/connect');
            setQrCode(res.data.qr_code ?? null);
            setStatus('qr_pending');
        } catch (e: unknown) {
            const msg = axios.isAxiosError(e) ? (e.response?.data?.message ?? 'Error al conectar') : 'Error al conectar';
            setError(msg);
        } finally {
            setLoading(false);
        }
    }

    async function handleDisconnect() {
        setLoading(true);
        setError(null);
        try {
            await axios.post('/api/v1/whatsapp/disconnect');
            setStatus('disconnected');
            setPhone(null);
            setConnectedAt(null);
            setQrCode(null);
        } catch (e: unknown) {
            const msg = axios.isAxiosError(e) ? (e.response?.data?.message ?? 'Error al desconectar') : 'Error al desconectar';
            setError(msg);
        } finally {
            setLoading(false);
        }
    }

    const canManage = auth.user.role === 'owner' || auth.user.role === 'admin';

    return (
        <AppLayout title="WhatsApp">
            <div className="mx-auto max-w-2xl space-y-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold text-gray-900">Conexión WhatsApp</h1>
                    <p className="mt-1 text-sm text-gray-500">
                        Conecta tu número de WhatsApp para gestionar conversaciones desde AriCRM.
                    </p>
                </div>

                {/* Status card */}
                <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="space-y-1">
                            <p className="text-sm font-medium text-gray-500">Estado</p>
                            <StatusBadge status={status} />
                        </div>
                        {status === 'connected' && (
                            <CheckCircle className="h-8 w-8 text-ari-500" />
                        )}
                        {status === 'disconnected' && (
                            <WifiOff className="h-8 w-8 text-gray-400" />
                        )}
                        {status === 'qr_pending' && (
                            <QrCode className="h-8 w-8 text-yellow-500" />
                        )}
                    </div>

                    {status === 'connected' && phone && (
                        <div className="mt-4 space-y-1 border-t border-gray-100 pt-4">
                            <p className="text-sm text-gray-500">
                                Número: <span className="font-medium text-gray-900">{phone}</span>
                            </p>
                            {connectedAt && (
                                <p className="text-sm text-gray-500">
                                    Conectado desde:{' '}
                                    <span className="font-medium text-gray-900">
                                        {new Date(connectedAt).toLocaleString('es-CO')}
                                    </span>
                                </p>
                            )}
                        </div>
                    )}
                </div>

                {/* QR Code */}
                {qrCode && status !== 'connected' && (
                    <div className="rounded-xl border border-yellow-200 bg-yellow-50 p-6 text-center">
                        <p className="mb-4 text-sm font-medium text-yellow-800">
                            Escanea este código QR con WhatsApp en tu teléfono
                        </p>
                        <img
                            src={qrCode}
                            alt="WhatsApp QR Code"
                            className="mx-auto h-48 w-48 rounded-lg sm:h-64 sm:w-64"
                        />
                        <p className="mt-3 text-xs text-yellow-600">
                            El código expira en 60 segundos. Si expira, haz clic en "Conectar" nuevamente.
                        </p>
                    </div>
                )}

                {/* Error */}
                {error && (
                    <div className="rounded-lg bg-red-50 p-4 text-sm text-red-700">{error}</div>
                )}

                {/* Actions */}
                {canManage && (
                    <div className="flex flex-col gap-3 sm:flex-row">
                        {status !== 'connected' && (
                            <button
                                onClick={handleConnect}
                                disabled={loading}
                                className="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-ari-600 px-4 py-2 text-sm font-medium text-white hover:bg-ari-700 disabled:opacity-50 sm:w-auto"
                            >
                                {loading && <Loader2 className="h-4 w-4 animate-spin" />}
                                Conectar WhatsApp
                            </button>
                        )}
                        {status === 'connected' && (
                            <button
                                onClick={handleDisconnect}
                                disabled={loading}
                                className="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 sm:w-auto"
                            >
                                {loading && <Loader2 className="h-4 w-4 animate-spin" />}
                                Desconectar
                            </button>
                        )}
                    </div>
                )}

                {/* Health monitoring */}
                <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div className="mb-4 flex items-center justify-between">
                        <div>
                            <h2 className="text-base font-semibold text-gray-900">Monitoreo Evolution API</h2>
                            <p className="text-xs text-gray-500">Histórico de salud del chequeo automático cada 5 minutos.</p>
                        </div>
                        <button
                            onClick={loadHealthLogs}
                            disabled={healthLoading}
                            className="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                        >
                            <RefreshCw className={`h-3.5 w-3.5 ${healthLoading ? 'animate-spin' : ''}`} />
                            Actualizar
                        </button>
                    </div>

                    <div className="mb-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-600">
                        <span className="font-medium">Fallas consecutivas:</span> {healthMeta.consecutive_failures}
                        {healthMeta.last_alert_at && (
                            <span className="ml-3">
                                <span className="font-medium">Última alerta:</span>{' '}
                                {new Date(healthMeta.last_alert_at).toLocaleString('es-CO')}
                            </span>
                        )}
                    </div>

                    {healthLogs.length === 0 ? (
                        <p className="text-sm text-gray-500">{healthLoading ? 'Cargando...' : 'Sin registros todavía.'}</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-100 text-sm">
                                <thead>
                                    <tr className="text-left text-xs uppercase tracking-wide text-gray-500">
                                        <th className="px-2 py-2">Estado</th>
                                        <th className="px-2 py-2">Latency</th>
                                        <th className="px-2 py-2">Instancia</th>
                                        <th className="px-2 py-2">Detalle</th>
                                        <th className="px-2 py-2">Fecha</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {healthLogs.map((log) => (
                                        <tr key={log.id}>
                                            <td className="px-2 py-2">
                                                <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                                                    log.is_healthy ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'
                                                }`}>
                                                    {log.is_healthy ? 'OK' : 'ERROR'}
                                                </span>
                                            </td>
                                            <td className="px-2 py-2 text-gray-700">{log.response_ms ?? '—'} ms</td>
                                            <td className="px-2 py-2 text-gray-600">{log.instance_name}</td>
                                            <td className="max-w-[260px] truncate px-2 py-2 text-gray-500" title={log.error_message ?? (log.state ?? '')}>
                                                {log.error_message ?? `state=${log.state ?? 'unknown'}`}
                                            </td>
                                            <td className="px-2 py-2 text-gray-500">
                                                {log.checked_at ? new Date(log.checked_at).toLocaleString('es-CO') : '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
