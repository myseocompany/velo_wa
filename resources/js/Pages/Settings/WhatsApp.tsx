import AppLayout from '@/Layouts/AppLayout';
import { useTenantChannel } from '@/hooks/useEcho';
import { PageProps, WaStatus } from '@/types';
import { usePage } from '@inertiajs/react';
import axios from 'axios';
import { CheckCircle, Loader2, QrCode, WifiOff } from 'lucide-react';
import { useCallback, useState } from 'react';

interface WaStatusPayload {
    status: WaStatus;
    phone: string | null;
    connected_at: string | null;
    qr_code: string | null;
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
                    <div className="flex items-center justify-between">
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
                            className="mx-auto h-64 w-64 rounded-lg"
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
                    <div className="flex gap-3">
                        {status !== 'connected' && (
                            <button
                                onClick={handleConnect}
                                disabled={loading}
                                className="inline-flex items-center gap-2 rounded-lg bg-ari-600 px-4 py-2 text-sm font-medium text-white hover:bg-ari-700 disabled:opacity-50"
                            >
                                {loading && <Loader2 className="h-4 w-4 animate-spin" />}
                                Conectar WhatsApp
                            </button>
                        )}
                        {status === 'connected' && (
                            <button
                                onClick={handleDisconnect}
                                disabled={loading}
                                className="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                            >
                                {loading && <Loader2 className="h-4 w-4 animate-spin" />}
                                Desconectar
                            </button>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
