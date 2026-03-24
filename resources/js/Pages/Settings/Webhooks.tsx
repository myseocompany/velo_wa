import AppLayout from '@/Layouts/AppLayout';
import { Head, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { PageProps } from '@/types';
import { Save, Webhook, Eye, EyeOff, CheckCircle, Copy } from 'lucide-react';
import axios from 'axios';

interface WebhookSettings {
    webhook_url: string | null;
    webhook_secret: string | null;
}

const EXAMPLE_PAYLOAD = `{
  "event": "message.received",
  "message_id": "uuid",
  "direction": "in",
  "body": "Hola, quiero info",
  "sent_at": "2026-03-24T10:00:00+00:00",
  "contact": {
    "id": "uuid",
    "name": "Juan García",
    "phone": "573001234567"
  },
  "conversation": { "id": "uuid", "status": "open" }
}`;

export default function SettingsWebhooks() {
    const { auth } = usePage<PageProps>().props;
    const isOwner = auth.user.role === 'owner';

    const [webhookUrl, setWebhookUrl]       = useState('');
    const [webhookSecret, setWebhookSecret] = useState('');
    const [showSecret, setShowSecret]       = useState(false);
    const [hasExistingSecret, setHasExistingSecret] = useState(false);

    const [loading, setSaving]   = useState(false);
    const [saved, setSaved]       = useState(false);
    const [error, setError]       = useState<string | null>(null);
    const [copied, setCopied]     = useState(false);
    const [fetching, setFetching] = useState(true);

    useEffect(() => {
        axios.get('/api/v1/tenant/settings')
            .then(res => {
                const data = res.data.data;
                setWebhookUrl(data.webhook_url ?? '');
                // The API masks the secret — just track whether one exists
                setHasExistingSecret(!!data.webhook_secret);
            })
            .finally(() => setFetching(false));
    }, []);

    const handleSave = async () => {
        if (!isOwner) return;
        setSaving(true);
        setError(null);
        try {
            const body: Record<string, string | null> = { webhook_url: webhookUrl || null };
            // Only send the secret if the user typed a new one
            if (webhookSecret) {
                body.webhook_secret = webhookSecret;
            }
            await axios.patch('/api/v1/tenant/settings', body);
            setSaved(true);
            if (webhookSecret) {
                setHasExistingSecret(true);
                setWebhookSecret('');
            }
            setTimeout(() => setSaved(false), 3000);
        } catch (err: any) {
            setError(err.response?.data?.message ?? 'Error al guardar.');
        } finally {
            setSaving(false);
        }
    };

    const handleCopy = () => {
        navigator.clipboard.writeText(EXAMPLE_PAYLOAD);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const handleClearWebhook = async () => {
        if (!isOwner) return;
        setSaving(true);
        setError(null);
        try {
            await axios.patch('/api/v1/tenant/settings', { webhook_url: null, webhook_secret: null });
            setWebhookUrl('');
            setWebhookSecret('');
            setHasExistingSecret(false);
            setSaved(true);
            setTimeout(() => setSaved(false), 2000);
        } catch (err: any) {
            setError(err.response?.data?.message ?? 'Error al guardar.');
        } finally {
            setSaving(false);
        }
    };

    if (fetching) {
        return (
            <AppLayout title="Webhooks">
                <Head title="Webhooks" />
                <div className="p-6 space-y-4">
                    {[1, 2, 3].map(i => (
                        <div key={i} className="h-20 animate-pulse rounded-xl bg-gray-200" />
                    ))}
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout title="Webhooks">
            <Head title="Webhooks" />

            <div className="max-w-2xl space-y-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold text-gray-900">Webhooks</h1>
                    <p className="mt-1 text-sm text-gray-500">
                        Notifica a tu CRM o sistema externo cada vez que llegue o salga un mensaje de WhatsApp.
                    </p>
                </div>

                {error && (
                    <div className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        {error}
                    </div>
                )}

                {/* Configuración */}
                <section className="rounded-xl border border-gray-200 bg-white p-5 space-y-5">
                    <div className="flex items-center gap-2">
                        <Webhook className="h-5 w-5 text-brand-600" />
                        <h2 className="text-base font-semibold text-gray-900">Endpoint de destino</h2>
                    </div>

                    {/* URL */}
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-gray-700">
                            URL del webhook
                        </label>
                        <input
                            type="url"
                            disabled={!isOwner}
                            value={webhookUrl}
                            onChange={e => setWebhookUrl(e.target.value)}
                            placeholder="https://tu-crm.com/api/webhooks/whatsapp"
                            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 disabled:bg-gray-50 disabled:text-gray-500"
                        />
                        <p className="mt-1.5 text-xs text-gray-400">
                            AriCRM hará un <code className="rounded bg-gray-100 px-1">POST</code> a esta URL con el payload JSON en cada mensaje entrante o saliente.
                        </p>
                    </div>

                    {/* Secret */}
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-gray-700">
                            Secreto de firma{' '}
                            <span className="font-normal text-gray-400">(opcional)</span>
                        </label>
                        <div className="relative">
                            <input
                                type={showSecret ? 'text' : 'password'}
                                disabled={!isOwner}
                                value={webhookSecret}
                                onChange={e => setWebhookSecret(e.target.value)}
                                placeholder={hasExistingSecret ? '••••••••  (secreto guardado)' : 'Ingresa un secreto para verificar la firma'}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2 pr-10 text-sm font-mono focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 disabled:bg-gray-50 disabled:text-gray-500"
                            />
                            <button
                                type="button"
                                onClick={() => setShowSecret(v => !v)}
                                className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                            >
                                {showSecret ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                            </button>
                        </div>
                        <p className="mt-1.5 text-xs text-gray-400">
                            Si configuras un secreto, cada request llevará el header{' '}
                            <code className="rounded bg-gray-100 px-1">X-AriCRM-Signature: sha256=&lt;hmac&gt;</code> para que puedas verificar la autenticidad.
                        </p>
                    </div>

                    {/* Eventos */}
                    <div className="rounded-lg bg-gray-50 border border-gray-200 p-4">
                        <p className="text-xs font-medium text-gray-600 mb-2">Eventos que se notifican</p>
                        <div className="space-y-1.5">
                            {[
                                { event: 'message.received', desc: 'Llega un mensaje de un contacto de WhatsApp' },
                                { event: 'message.sent',     desc: 'Un agente envía un mensaje al contacto' },
                            ].map(({ event, desc }) => (
                                <div key={event} className="flex items-start gap-2">
                                    <span className="rounded bg-brand-100 px-1.5 py-0.5 text-xs font-mono font-medium text-brand-700">
                                        {event}
                                    </span>
                                    <span className="text-xs text-gray-500">{desc}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Payload de ejemplo */}
                <section className="rounded-xl border border-gray-200 bg-white p-5 space-y-3">
                    <div className="flex items-center justify-between">
                        <h2 className="text-base font-semibold text-gray-900">Payload de ejemplo</h2>
                        <button
                            onClick={handleCopy}
                            className="flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-xs text-gray-600 hover:bg-gray-50"
                        >
                            {copied ? (
                                <>
                                    <CheckCircle className="h-3.5 w-3.5 text-green-500" />
                                    Copiado
                                </>
                            ) : (
                                <>
                                    <Copy className="h-3.5 w-3.5" />
                                    Copiar
                                </>
                            )}
                        </button>
                    </div>
                    <pre className="overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100 leading-relaxed">
                        {EXAMPLE_PAYLOAD}
                    </pre>
                </section>

                {/* Acciones */}
                {isOwner && (
                    <div className="flex items-center gap-3">
                        <button
                            onClick={handleSave}
                            disabled={loading}
                            className="flex items-center gap-2 rounded-lg bg-brand-600 px-5 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-60"
                        >
                            <Save className="h-4 w-4" />
                            {loading ? 'Guardando...' : 'Guardar'}
                        </button>

                        {webhookUrl && (
                            <button
                                onClick={handleClearWebhook}
                                disabled={loading}
                                className="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 disabled:opacity-60"
                            >
                                Desactivar webhook
                            </button>
                        )}

                        {saved && (
                            <span className="flex items-center gap-1.5 text-sm text-green-600">
                                <CheckCircle className="h-4 w-4" />
                                Guardado
                            </span>
                        )}
                    </div>
                )}

                {!isOwner && (
                    <p className="text-sm text-gray-400">Solo el propietario puede configurar webhooks.</p>
                )}
            </div>
        </AppLayout>
    );
}
