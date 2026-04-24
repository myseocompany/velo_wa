import AppLayout from '@/Layouts/AppLayout';
import { useTenantChannel } from '@/hooks/useEcho';
import { PageProps, TenantPlan, WaStatus, WaStatusUpdatedPayload, WhatsAppLine } from '@/types';
import { usePage } from '@inertiajs/react';
import axios from 'axios';
import {
    ChevronDown,
    ChevronUp,
    Loader2,
    Pencil,
    Plus,
    QrCode,
    RefreshCw,
    Star,
    Trash2,
    WifiOff,
    X,
} from 'lucide-react';

const MULTILINE_TIP_DISMISSED_KEY = 'aricrm_wa_multiline_tip_dismissed';

const PLAN_LABELS: Record<TenantPlan, string> = {
    trial: 'Trial',
    seed:  'Semilla',
    grow:  'Crecer',
    scale: 'Escalar',
};
import { useCallback, useEffect, useRef, useState } from 'react';

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
        connected:    { label: 'Conectado',    className: 'bg-ari-100 text-ari-800' },
        qr_pending:   { label: 'Esperando QR', className: 'bg-yellow-100 text-yellow-800' },
        disconnected: { label: 'Desconectado', className: 'bg-gray-100 text-gray-600' },
        banned:       { label: 'Bloqueado',    className: 'bg-red-100 text-red-800' },
    };
    const { label, className } = map[status] ?? map.disconnected;
    return (
        <span
            className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${className}`}
            aria-label={`Estado: ${label}`}
        >
            {label}
        </span>
    );
}

// ─── LineCard ─────────────────────────────────────────────────────────────────

interface LineCardProps {
    line: WhatsAppLine;
    qrCode: string | null;
    connecting: boolean;
    disconnecting: boolean;
    settingDefault: boolean;
    error: string | null;
    canManage: boolean;
    onConnect: () => void;
    onDisconnect: () => void;
    onSaveLabel: (label: string) => Promise<void>;
    onSetDefault: () => void;
    onDelete: () => Promise<void>;
    onClearError: () => void;
}

function LineCard({
    line,
    qrCode,
    connecting,
    disconnecting,
    settingDefault,
    error,
    canManage,
    onConnect,
    onDisconnect,
    onSaveLabel,
    onSetDefault,
    onDelete,
    onClearError,
}: LineCardProps) {
    const [editingLabel, setEditingLabel] = useState(false);
    const [labelDraft, setLabelDraft]     = useState(line.label);
    const [savingLabel, setSavingLabel]   = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [deleting, setDeleting]         = useState(false);
    const [expandedHealth, setExpandedHealth] = useState(false);
    const [healthLogs, setHealthLogs]     = useState<HealthLog[]>([]);
    const [healthMeta, setHealthMeta]     = useState<HealthMeta>({ consecutive_failures: 0, last_alert_at: null });
    const [healthLoading, setHealthLoading] = useState(false);
    const labelInputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (!editingLabel) setLabelDraft(line.label);
    }, [line.label, editingLabel]);

    useEffect(() => {
        if (editingLabel && labelInputRef.current) {
            labelInputRef.current.focus();
            labelInputRef.current.select();
        }
    }, [editingLabel]);

    async function saveLabel() {
        const trimmed = labelDraft.trim();
        if (!trimmed || trimmed === line.label) {
            setEditingLabel(false);
            return;
        }
        setSavingLabel(true);
        try {
            await onSaveLabel(trimmed);
            setEditingLabel(false);
        } catch {
            // error is surfaced via parent; keep input open so user can correct
        } finally {
            setSavingLabel(false);
        }
    }

    async function handleDelete() {
        setDeleting(true);
        try {
            await onDelete();
        } catch {
            // error is surfaced via the `error` prop from parent
        } finally {
            setDeleting(false);
            setConfirmDelete(false);
        }
    }

    async function loadHealthLogs() {
        setHealthLoading(true);
        try {
            const res = await axios.get<{ data: HealthLog[]; meta?: HealthMeta }>(
                `/api/v1/whatsapp/lines/${line.id}/health-logs`,
                { params: { limit: 20 } },
            );
            setHealthLogs(res.data.data ?? []);
            setHealthMeta(res.data.meta ?? { consecutive_failures: 0, last_alert_at: null });
        } finally {
            setHealthLoading(false);
        }
    }

    async function toggleHealth() {
        const next = !expandedHealth;
        setExpandedHealth(next);
        if (next && healthLogs.length === 0) {
            await loadHealthLogs();
        }
    }

    const isLoading = connecting || disconnecting;

    return (
        <div className={`overflow-hidden rounded-xl border bg-white shadow-sm ${line.is_default ? 'border-ari-300' : 'border-gray-200'}`}>
            {/* Card header */}
            <div className="px-5 py-4">
                <div className="flex flex-wrap items-start gap-3">
                    {/* Default radio */}
                    {canManage && (
                        <button
                            onClick={onSetDefault}
                            disabled={line.is_default || settingDefault}
                            aria-label={line.is_default ? 'Línea predeterminada' : 'Establecer como predeterminada'}
                            className="mt-0.5 flex-shrink-0 disabled:cursor-default"
                        >
                            <Star
                                className={`h-4 w-4 ${line.is_default ? 'fill-ari-500 text-ari-500' : 'text-gray-300 hover:text-ari-400'}`}
                            />
                        </button>
                    )}

                    {/* Label */}
                    <div className="flex min-w-0 flex-1 items-center gap-2">
                        {editingLabel ? (
                            <div className="flex items-center gap-2">
                                <input
                                    ref={labelInputRef}
                                    value={labelDraft}
                                    onChange={(e) => setLabelDraft(e.target.value)}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') void saveLabel();
                                        if (e.key === 'Escape') setEditingLabel(false);
                                    }}
                                    className="rounded-md border border-ari-400 px-2 py-0.5 text-sm font-medium focus:outline-none focus:ring-1 focus:ring-ari-500"
                                    maxLength={100}
                                />
                                <button
                                    onClick={() => void saveLabel()}
                                    disabled={savingLabel}
                                    className="text-xs font-medium text-ari-600 hover:text-ari-700 disabled:opacity-50"
                                >
                                    {savingLabel ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : 'Guardar'}
                                </button>
                                <button
                                    onClick={() => setEditingLabel(false)}
                                    className="text-xs text-gray-400 hover:text-gray-600"
                                >
                                    <X className="h-3.5 w-3.5" />
                                </button>
                            </div>
                        ) : (
                            <div className="flex items-center gap-1.5">
                                <span className="text-sm font-semibold text-gray-900">{line.label}</span>
                                {canManage && (
                                    <button
                                        onClick={() => setEditingLabel(true)}
                                        aria-label="Renombrar línea"
                                        className="text-gray-300 hover:text-gray-500"
                                    >
                                        <Pencil className="h-3 w-3" />
                                    </button>
                                )}
                            </div>
                        )}
                    </div>

                    {/* Status + phone */}
                    <div className="flex flex-wrap items-center gap-2">
                        <StatusBadge status={line.status} />
                        {line.phone && (
                            <span className="text-xs text-gray-500">{line.phone}</span>
                        )}
                        {!line.phone && line.status !== 'connected' && (
                            <span className="text-xs text-gray-400">Sin conectar</span>
                        )}
                    </div>

                    {/* Actions */}
                    {canManage && (
                        <div className="flex items-center gap-2">
                            {line.status !== 'connected' ? (
                                <button
                                    onClick={onConnect}
                                    disabled={isLoading}
                                    className="inline-flex items-center gap-1.5 rounded-lg bg-ari-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-ari-700 disabled:opacity-50"
                                >
                                    {connecting ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <QrCode className="h-3.5 w-3.5" />}
                                    Conectar
                                </button>
                            ) : (
                                <button
                                    onClick={onDisconnect}
                                    disabled={isLoading}
                                    className="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                                >
                                    {disconnecting ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <WifiOff className="h-3.5 w-3.5" />}
                                    Desconectar
                                </button>
                            )}

                            {confirmDelete ? (
                                <div className="flex items-center gap-1.5">
                                    <span className="text-xs text-gray-600">¿Eliminar?</span>
                                    <button
                                        onClick={() => void handleDelete()}
                                        disabled={deleting}
                                        className="inline-flex items-center gap-1 rounded-lg bg-red-600 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-red-700 disabled:opacity-50"
                                    >
                                        {deleting ? <Loader2 className="h-3 w-3 animate-spin" /> : 'Sí, eliminar'}
                                    </button>
                                    <button
                                        onClick={() => setConfirmDelete(false)}
                                        className="text-xs text-gray-400 hover:text-gray-600"
                                    >
                                        Cancelar
                                    </button>
                                </div>
                            ) : (
                                <button
                                    onClick={() => { onClearError(); setConfirmDelete(true); }}
                                    aria-label="Eliminar línea"
                                    className="rounded-lg p-1.5 text-gray-300 hover:bg-red-50 hover:text-red-500"
                                >
                                    <Trash2 className="h-4 w-4" />
                                </button>
                            )}
                        </div>
                    )}
                </div>

                {/* Connected info */}
                {line.status === 'connected' && line.connected_at && (
                    <p className="mt-2 text-xs text-gray-400">
                        Conectado desde {new Date(line.connected_at).toLocaleString('es-CO')}
                    </p>
                )}

                {/* Error */}
                {error && (
                    <div className="mt-3 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700">{error}</div>
                )}
            </div>

            {/* QR Code */}
            {qrCode && line.status !== 'connected' && (
                <div className="border-t border-yellow-100 bg-yellow-50 px-5 py-4 text-center">
                    <p className="mb-3 text-sm font-medium text-yellow-800">
                        Escanea con WhatsApp en tu teléfono
                    </p>
                    <img
                        src={qrCode}
                        alt={`QR Code para ${line.label}`}
                        className="mx-auto h-48 w-48 rounded-lg sm:h-56 sm:w-56"
                    />
                    <p className="mt-2 text-xs text-yellow-600">
                        El código expira en 60 segundos. Si expira, haz clic en "Conectar" nuevamente.
                    </p>
                </div>
            )}

            {/* Health logs toggle */}
            <div className="border-t border-gray-100">
                <div className="flex items-center justify-between px-5 py-2.5 text-xs text-gray-500">
                    <button
                        onClick={() => void toggleHealth()}
                        className="flex flex-1 items-center gap-1 py-0.5 text-left font-medium hover:text-gray-700"
                    >
                        <span>Historial de salud</span>
                        {expandedHealth && healthMeta.consecutive_failures > 0 && (
                            <span className="rounded-full bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold text-red-700">
                                {healthMeta.consecutive_failures} fallas
                            </span>
                        )}
                    </button>
                    <div className="flex items-center gap-2">
                        {expandedHealth && (
                            <button
                                onClick={() => void loadHealthLogs()}
                                disabled={healthLoading}
                                aria-label="Actualizar historial"
                                className="rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 disabled:opacity-50"
                            >
                                <RefreshCw className={`h-3.5 w-3.5 ${healthLoading ? 'animate-spin' : ''}`} />
                            </button>
                        )}
                        <button
                            onClick={() => void toggleHealth()}
                            aria-label={expandedHealth ? 'Ocultar historial' : 'Mostrar historial'}
                            className="rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                        >
                            {expandedHealth ? <ChevronUp className="h-3.5 w-3.5" /> : <ChevronDown className="h-3.5 w-3.5" />}
                        </button>
                    </div>
                </div>

                {expandedHealth && (
                    <div className="px-5 pb-4">
                        {healthLoading ? (
                            <div className="flex justify-center py-4">
                                <Loader2 className="h-5 w-5 animate-spin text-gray-400" />
                            </div>
                        ) : healthLogs.length === 0 ? (
                            <p className="py-2 text-xs text-gray-400">Sin registros todavía.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-100 text-xs">
                                    <thead>
                                        <tr className="text-left text-[10px] uppercase tracking-wide text-gray-400">
                                            <th className="px-2 py-1.5">Estado</th>
                                            <th className="px-2 py-1.5">Latency</th>
                                            <th className="px-2 py-1.5">Detalle</th>
                                            <th className="px-2 py-1.5">Fecha</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-50">
                                        {healthLogs.map((log) => (
                                            <tr key={log.id}>
                                                <td className="px-2 py-1.5">
                                                    <span className={`rounded-full px-1.5 py-0.5 text-[10px] font-medium ${
                                                        log.is_healthy ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'
                                                    }`}>
                                                        {log.is_healthy ? 'OK' : 'ERROR'}
                                                    </span>
                                                </td>
                                                <td className="px-2 py-1.5 text-gray-600">{log.response_ms ?? '—'} ms</td>
                                                <td className="max-w-[220px] truncate px-2 py-1.5 text-gray-400" title={log.error_message ?? (log.state ?? '')}>
                                                    {log.error_message ?? `state=${log.state ?? 'unknown'}`}
                                                </td>
                                                <td className="px-2 py-1.5 text-gray-400">
                                                    {log.checked_at ? new Date(log.checked_at).toLocaleString('es-CO') : '—'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}

// ─── AddLineModal ─────────────────────────────────────────────────────────────

interface AddLineModalProps {
    loading: boolean;
    error: string | null;
    onConfirm: (label: string) => void;
    onClose: () => void;
}

function AddLineModal({ loading, error, onConfirm, onClose }: AddLineModalProps) {
    const [label, setLabel] = useState('');
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        inputRef.current?.focus();
    }, []);

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        if (label.trim()) onConfirm(label.trim());
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="w-full max-w-sm overflow-hidden rounded-2xl bg-white shadow-xl">
                <div className="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h3 className="font-semibold text-gray-900">Nueva línea de WhatsApp</h3>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
                        <X className="h-5 w-5" />
                    </button>
                </div>
                <form onSubmit={handleSubmit} className="space-y-4 px-5 py-4">
                    <div>
                        <label className="mb-1 block text-xs font-medium text-gray-700">
                            Nombre de la línea <span className="text-red-500">*</span>
                        </label>
                        <input
                            ref={inputRef}
                            value={label}
                            onChange={(e) => setLabel(e.target.value)}
                            placeholder="ej: Ventas, Soporte, Principal…"
                            maxLength={100}
                            className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none"
                        />
                    </div>
                    {error && <p className="text-xs text-red-600">{error}</p>}
                    <div className="flex flex-col-reverse gap-2 pt-1 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            onClick={onClose}
                            className="min-h-10 w-full rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 sm:w-auto"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            disabled={loading || !label.trim()}
                            className="flex min-h-10 w-full items-center justify-center gap-1.5 rounded-lg bg-ari-600 px-4 py-2 text-sm font-medium text-white hover:bg-ari-700 disabled:opacity-50 sm:w-auto"
                        >
                            {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
                            Crear línea
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ─── Main component ───────────────────────────────────────────────────────────

export default function WhatsApp() {
    const { auth } = usePage<PageProps>().props;
    const tenant   = auth.tenant;
    const canManage = auth.user.role === 'owner' || auth.user.role === 'admin';

    const [lines, setLines]           = useState<WhatsAppLine[]>([]);
    const [loadingLines, setLoadingLines] = useState(true);
    const [qrMap, setQrMap]           = useState<Record<string, string>>({});
    const [connectingId, setConnectingId] = useState<string | null>(null);
    const [disconnectingId, setDisconnectingId] = useState<string | null>(null);
    const [settingDefaultId, setSettingDefaultId] = useState<string | null>(null);
    const [lineErrors, setLineErrors] = useState<Record<string, string>>({});
    const [showAddModal, setShowAddModal] = useState(false);
    const [addLoading, setAddLoading] = useState(false);
    const [addError, setAddError]     = useState<string | null>(null);
    const [migrationTipDismissed, setMigrationTipDismissed] = useState<boolean>(() => {
        try {
            return window.localStorage.getItem(MULTILINE_TIP_DISMISSED_KEY) === '1';
        } catch {
            return true;
        }
    });

    function dismissMigrationTip() {
        setMigrationTipDismissed(true);
        try {
            window.localStorage.setItem(MULTILINE_TIP_DISMISSED_KEY, '1');
        } catch {
            // ignore storage errors (private mode, quota, etc.)
        }
    }

    useEffect(() => {
        axios.get<{ data: WhatsAppLine[] }>('/api/v1/whatsapp/lines')
            .then((res) => setLines(res.data.data))
            .finally(() => setLoadingLines(false));
    }, []);

    const handleStatusUpdate = useCallback((data: unknown) => {
        const payload = data as WaStatusUpdatedPayload;
        if (!payload.line_id) return;

        setLines((prev) => prev.map((l) =>
            l.id === payload.line_id
                ? { ...l, status: payload.status, phone: payload.phone ?? l.phone, connected_at: payload.connected_at ?? l.connected_at }
                : l,
        ));

        if (payload.qr_code) {
            setQrMap((prev) => ({ ...prev, [payload.line_id!]: payload.qr_code! }));
        }
        if (payload.status === 'connected') {
            setQrMap((prev) => {
                const next = { ...prev };
                delete next[payload.line_id!];
                return next;
            });
        }
    }, []);

    useTenantChannel(tenant.id, 'wa.status.updated', handleStatusUpdate);

    function setLineError(lineId: string, msg: string) {
        setLineErrors((prev) => ({ ...prev, [lineId]: msg }));
    }

    function clearLineError(lineId: string) {
        setLineErrors((prev) => {
            const next = { ...prev };
            delete next[lineId];
            return next;
        });
    }

    async function handleConnect(lineId: string) {
        setConnectingId(lineId);
        clearLineError(lineId);
        try {
            const res = await axios.post<{ qr_code?: string; line?: WhatsAppLine }>(`/api/v1/whatsapp/lines/${lineId}/connect`);
            if (res.data.qr_code) {
                setQrMap((prev) => ({ ...prev, [lineId]: res.data.qr_code! }));
            }
            if (res.data.line) {
                const updated = res.data.line;
                setLines((prev) => prev.map((l) => l.id === lineId ? updated : l));
            } else {
                setLines((prev) => prev.map((l) => l.id === lineId ? { ...l, status: 'qr_pending' } : l));
            }
        } catch (e: unknown) {
            const msg = axios.isAxiosError(e) ? (e.response?.data?.message ?? 'Error al conectar') : 'Error al conectar';
            setLineError(lineId, msg);
        } finally {
            setConnectingId(null);
        }
    }

    async function handleDisconnect(lineId: string) {
        setDisconnectingId(lineId);
        clearLineError(lineId);
        try {
            await axios.post(`/api/v1/whatsapp/lines/${lineId}/disconnect`);
            setLines((prev) => prev.map((l) => l.id === lineId ? { ...l, status: 'disconnected', phone: null, connected_at: null } : l));
            setQrMap((prev) => {
                const next = { ...prev };
                delete next[lineId];
                return next;
            });
        } catch (e: unknown) {
            const msg = axios.isAxiosError(e) ? (e.response?.data?.message ?? 'Error al desconectar') : 'Error al desconectar';
            setLineError(lineId, msg);
        } finally {
            setDisconnectingId(null);
        }
    }

    async function handleSaveLabel(lineId: string, label: string) {
        try {
            const res = await axios.patch<{ data: WhatsAppLine }>(`/api/v1/whatsapp/lines/${lineId}`, { label });
            setLines((prev) => prev.map((l) => l.id === lineId ? res.data.data : l));
            clearLineError(lineId);
        } catch (e: unknown) {
            const msg = axios.isAxiosError(e) ? (e.response?.data?.message ?? 'Error al guardar el nombre') : 'Error al guardar el nombre';
            setLineError(lineId, msg);
            throw e;
        }
    }

    async function handleSetDefault(lineId: string) {
        const snapshot = lines;
        setSettingDefaultId(lineId);
        setLines((prev) => prev.map((l) => ({ ...l, is_default: l.id === lineId })));
        try {
            await axios.patch(`/api/v1/whatsapp/lines/${lineId}`, { is_default: true });
        } catch {
            setLines(snapshot);
        } finally {
            setSettingDefaultId(null);
        }
    }

    async function handleDelete(lineId: string) {
        try {
            await axios.delete(`/api/v1/whatsapp/lines/${lineId}`);
            setLines((prev) => prev.filter((l) => l.id !== lineId));
            setQrMap((prev) => {
                const next = { ...prev };
                delete next[lineId];
                return next;
            });
        } catch (e: unknown) {
            const msg = axios.isAxiosError(e) ? (e.response?.data?.message ?? 'Error al eliminar') : 'Error al eliminar';
            setLineError(lineId, msg);
            throw e;
        }
    }

    async function handleAddLine(label: string) {
        setAddLoading(true);
        setAddError(null);
        try {
            const res = await axios.post<{ data: WhatsAppLine }>('/api/v1/whatsapp/lines', { label });
            setLines((prev) => [...prev, res.data.data]);
            setShowAddModal(false);
            dismissMigrationTip();
        } catch (e: unknown) {
            const msg = axios.isAxiosError(e) ? (e.response?.data?.message ?? 'Error al crear la línea') : 'Error al crear la línea';
            setAddError(msg);
        } finally {
            setAddLoading(false);
        }
    }

    const sortedLines = [...lines].sort((a, b) => {
        if (a.is_default !== b.is_default) return a.is_default ? -1 : 1;
        return a.label.localeCompare(b.label);
    });

    const maxLines      = tenant.max_wa_lines;
    const unlimited     = maxLines === -1;
    const usedLines     = loadingLines ? tenant.current_wa_lines_count : sortedLines.length;
    const limitReached  = !unlimited && usedLines >= maxLines;
    const planLabel     = PLAN_LABELS[tenant.current_plan] ?? tenant.current_plan;
    const limitTooltip  = limitReached
        ? `Alcanzaste el límite del plan ${planLabel} (${maxLines} ${maxLines === 1 ? 'línea' : 'líneas'}). Mejora tu plan para agregar más.`
        : undefined;

    const showMigrationTip = !loadingLines && canManage && !migrationTipDismissed && sortedLines.length === 1 && !limitReached;

    return (
        <AppLayout title="WhatsApp">
            <div className="mx-auto max-w-3xl space-y-5 p-6">
                {/* Header */}
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold text-gray-900">Líneas de WhatsApp</h1>
                        <p className="mt-1 text-sm text-gray-500">
                            Conecta y gestiona los números de WhatsApp de tu cuenta.
                        </p>
                    </div>
                    {canManage && (
                        <div className="relative">
                            <button
                                onClick={() => { setAddError(null); setShowAddModal(true); }}
                                disabled={limitReached}
                                title={limitTooltip}
                                className="inline-flex flex-shrink-0 items-center gap-2 rounded-lg bg-ari-600 px-4 py-2 text-sm font-medium text-white hover:bg-ari-700 disabled:cursor-not-allowed disabled:bg-gray-300 disabled:hover:bg-gray-300"
                            >
                                <Plus className="h-4 w-4" />
                                Agregar línea
                            </button>
                            {!unlimited && !loadingLines && (
                                <p className="mt-1 text-right text-xs text-gray-400">
                                    {usedLines} de {maxLines} · Plan {planLabel}
                                </p>
                            )}
                            {unlimited && !loadingLines && (
                                <p className="mt-1 text-right text-xs text-gray-400">
                                    Plan {planLabel} · sin límite
                                </p>
                            )}
                            {showMigrationTip && (
                                <div
                                    role="dialog"
                                    aria-label="Novedad: múltiples líneas"
                                    className="absolute right-0 top-full z-20 mt-2 w-72 rounded-xl border border-ari-200 bg-white p-3 shadow-lg"
                                >
                                    <div className="mb-2 inline-flex items-center gap-1.5 rounded-full bg-ari-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-ari-700">
                                        Novedad
                                    </div>
                                    <p className="pr-6 text-sm text-gray-700">
                                        Ahora puedes conectar <span className="font-semibold">múltiples números</span> de WhatsApp a tu cuenta.
                                    </p>
                                    <button
                                        onClick={dismissMigrationTip}
                                        aria-label="Cerrar aviso"
                                        className="absolute right-2 top-2 rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                                    >
                                        <X className="h-3.5 w-3.5" />
                                    </button>
                                    <span
                                        aria-hidden="true"
                                        className="absolute -top-1.5 right-6 h-3 w-3 rotate-45 border-l border-t border-ari-200 bg-white"
                                    />
                                </div>
                            )}
                        </div>
                    )}
                </div>

                {/* Lines list */}
                {loadingLines ? (
                    <div className="flex justify-center py-12">
                        <Loader2 className="h-6 w-6 animate-spin text-gray-400" />
                    </div>
                ) : sortedLines.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-xl border-2 border-dashed border-gray-200 py-14 text-center">
                        <QrCode className="h-10 w-10 text-gray-300" />
                        <div>
                            <p className="text-sm font-medium text-gray-700">Sin líneas de WhatsApp</p>
                            <p className="mt-1 text-xs text-gray-400">Agrega tu primera línea para comenzar.</p>
                        </div>
                        {canManage && (
                            <button
                                onClick={() => { setAddError(null); setShowAddModal(true); }}
                                disabled={limitReached}
                                title={limitTooltip}
                                className="mt-1 inline-flex items-center gap-1.5 rounded-lg bg-ari-600 px-4 py-2 text-sm font-medium text-white hover:bg-ari-700 disabled:cursor-not-allowed disabled:bg-gray-300 disabled:hover:bg-gray-300"
                            >
                                <Plus className="h-4 w-4" />
                                Agregar línea
                            </button>
                        )}
                    </div>
                ) : (
                    <div className="space-y-4">
                        {sortedLines.map((line) => (
                            <LineCard
                                key={line.id}
                                line={line}
                                qrCode={qrMap[line.id] ?? null}
                                connecting={connectingId === line.id}
                                disconnecting={disconnectingId === line.id}
                                settingDefault={settingDefaultId === line.id}
                                error={lineErrors[line.id] ?? null}
                                canManage={canManage}
                                onConnect={() => void handleConnect(line.id)}
                                onDisconnect={() => void handleDisconnect(line.id)}
                                onSaveLabel={(label) => handleSaveLabel(line.id, label)}
                                onSetDefault={() => void handleSetDefault(line.id)}
                                onDelete={() => handleDelete(line.id)}
                                onClearError={() => clearLineError(line.id)}
                            />
                        ))}
                    </div>
                )}

                {/* Legend */}
                {!loadingLines && sortedLines.length > 0 && (
                    <div className="flex items-center gap-4 rounded-lg bg-gray-50 px-4 py-2.5 text-xs text-gray-500">
                        <Star className="h-3.5 w-3.5 flex-shrink-0 fill-ari-500 text-ari-500" />
                        <span>La línea marcada con estrella es la predeterminada y se usará cuando no se especifique una línea concreta.</span>
                    </div>
                )}
            </div>

            {showAddModal && (
                <AddLineModal
                    loading={addLoading}
                    error={addError}
                    onConfirm={handleAddLine}
                    onClose={() => setShowAddModal(false)}
                />
            )}
        </AppLayout>
    );
}
