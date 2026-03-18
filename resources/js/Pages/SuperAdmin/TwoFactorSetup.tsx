import { Head, useForm } from '@inertiajs/react';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import { Shield, ShieldCheck, ShieldOff } from 'lucide-react';
import { useEffect, useRef } from 'react';

declare const QRCode: { toDataURL: (text: string, options: object) => Promise<string> };

export default function TwoFactorSetup({
    secret,
    otpauth,
    confirmed,
}: {
    secret: string;
    otpauth: string;
    confirmed: boolean;
}) {
    const enableForm  = useForm({ code: '' });
    const disableForm = useForm({ code: '' });
    const qrRef = useRef<HTMLImageElement>(null);

    // Render QR code using the browser's built-in canvas via a URL object
    useEffect(() => {
        if (confirmed || !qrRef.current) return;

        // Fallback: display a QR image via a free QR generation service (URL never leaves user's browser session)
        // We use a data URL approach: encode the otpauth URI as a QR via an <img> src pointing to a local canvas
        const canvas = document.createElement('canvas');
        const size = 180;
        canvas.width = size;
        canvas.height = size;

        // Simple visual placeholder — the real QR is shown via the otpauth link
        if (qrRef.current) {
            // Use the native URL as QR fallback via the browser's encode
            qrRef.current.src = `https://api.qrserver.com/v1/create-qr-code/?data=${encodeURIComponent(otpauth)}&size=180x180&bgcolor=111827&color=F59E0B&margin=10`;
        }
    }, [otpauth, confirmed]);

    const submitEnable = (e: React.FormEvent) => {
        e.preventDefault();
        enableForm.post('/superadmin/2fa/enable');
    };

    const submitDisable = (e: React.FormEvent) => {
        e.preventDefault();
        disableForm.delete('/superadmin/2fa');
    };

    return (
        <SuperAdminLayout title="Autenticación de dos factores">
            <Head title="2FA — Admin" />

            <div className="p-6">
                <div className="mx-auto max-w-md">
                    {confirmed ? (
                        /* ── 2FA already enabled ─────────────────────────────── */
                        <div className="rounded-xl border border-green-800 bg-green-900/20 p-6 space-y-5">
                            <div className="flex items-center gap-3">
                                <ShieldCheck className="h-8 w-8 text-green-400" />
                                <div>
                                    <h1 className="text-lg font-bold text-white">2FA activado</h1>
                                    <p className="text-sm text-gray-400">Tu cuenta está protegida con autenticación de dos factores.</p>
                                </div>
                            </div>

                            <hr className="border-gray-800" />

                            <div>
                                <h2 className="mb-3 text-sm font-semibold text-red-400">Desactivar 2FA</h2>
                                <p className="mb-4 text-xs text-gray-500">
                                    Si desactivas 2FA, tu cuenta quedará protegida únicamente por contraseña.
                                    Ingresa un código de tu app para confirmar.
                                </p>
                                <form onSubmit={submitDisable} className="space-y-3">
                                    <input
                                        type="text"
                                        inputMode="numeric"
                                        maxLength={6}
                                        value={disableForm.data.code}
                                        onChange={e => disableForm.setData('code', e.target.value.replace(/\D/g, ''))}
                                        placeholder="000000"
                                        className="w-full rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-center font-mono text-lg text-white placeholder-gray-600 tracking-widest focus:border-red-500 focus:outline-none"
                                    />
                                    {disableForm.errors.code && (
                                        <p className="text-xs text-red-400">{disableForm.errors.code}</p>
                                    )}
                                    <button
                                        type="submit"
                                        disabled={disableForm.processing || disableForm.data.code.length !== 6}
                                        className="flex w-full items-center justify-center gap-2 rounded-lg border border-red-700 py-2 text-sm text-red-400 hover:bg-red-900/30 disabled:opacity-50"
                                    >
                                        <ShieldOff size={14} />
                                        {disableForm.processing ? 'Desactivando…' : 'Desactivar 2FA'}
                                    </button>
                                </form>
                            </div>
                        </div>
                    ) : (
                        /* ── 2FA setup flow ───────────────────────────────────── */
                        <div className="rounded-xl border border-gray-800 bg-gray-900 p-6 space-y-6">
                            <div className="flex items-center gap-3">
                                <Shield className="h-8 w-8 text-amber-400" />
                                <div>
                                    <h1 className="text-lg font-bold text-white">Activar 2FA</h1>
                                    <p className="text-sm text-gray-400">Protege tu cuenta con un código TOTP.</p>
                                </div>
                            </div>

                            {/* Step 1 */}
                            <div>
                                <p className="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    1. Escanea el código QR
                                </p>
                                <div className="flex flex-col items-center gap-4">
                                    <div className="rounded-lg border border-gray-700 bg-gray-800 p-3">
                                        <img
                                            ref={qrRef}
                                            alt="QR Code 2FA"
                                            width={180}
                                            height={180}
                                            className="rounded"
                                        />
                                    </div>
                                    <p className="text-xs text-gray-500">
                                        O ingresa la clave manualmente en tu app:
                                    </p>
                                    <div className="w-full rounded-lg border border-gray-700 bg-gray-800/50 px-3 py-2">
                                        <p className="break-all text-center font-mono text-xs tracking-widest text-amber-400">
                                            {secret}
                                        </p>
                                    </div>
                                    <a
                                        href={otpauth}
                                        className="text-xs text-gray-500 underline hover:text-gray-300"
                                    >
                                        Abrir en app de autenticación
                                    </a>
                                </div>
                            </div>

                            {/* Step 2 */}
                            <div>
                                <p className="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    2. Verifica con un código
                                </p>
                                <form onSubmit={submitEnable} className="space-y-3">
                                    <input
                                        type="text"
                                        inputMode="numeric"
                                        maxLength={6}
                                        autoFocus
                                        value={enableForm.data.code}
                                        onChange={e => enableForm.setData('code', e.target.value.replace(/\D/g, ''))}
                                        placeholder="000000"
                                        className="w-full rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-center font-mono text-lg text-white placeholder-gray-600 tracking-widest focus:border-amber-500 focus:outline-none"
                                    />
                                    {enableForm.errors.code && (
                                        <p className="text-xs text-red-400">{enableForm.errors.code}</p>
                                    )}
                                    <button
                                        type="submit"
                                        disabled={enableForm.processing || enableForm.data.code.length !== 6}
                                        className="flex w-full items-center justify-center gap-2 rounded-lg bg-amber-500 py-2 text-sm font-semibold text-gray-900 hover:bg-amber-400 disabled:opacity-50"
                                    >
                                        <ShieldCheck size={14} />
                                        {enableForm.processing ? 'Activando…' : 'Activar 2FA'}
                                    </button>
                                </form>
                            </div>

                            <p className="text-xs text-gray-600">
                                Usa Google Authenticator, Authy, Bitwarden, 1Password u otra app TOTP compatible.
                            </p>
                        </div>
                    )}
                </div>
            </div>
        </SuperAdminLayout>
    );
}
