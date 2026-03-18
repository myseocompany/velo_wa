import { Head, useForm } from '@inertiajs/react';
import { Shield, Loader2 } from 'lucide-react';

export default function TwoFactor() {
    const { data, setData, post, processing, errors } = useForm({ code: '' });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/superadmin/two-factor');
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-950 px-4">
            <Head title="Verificación 2FA — Admin" />

            <div className="w-full max-w-sm text-center">
                <div className="mb-6 flex justify-center">
                    <div className="flex h-14 w-14 items-center justify-center rounded-xl bg-amber-500/10">
                        <Shield className="h-7 w-7 text-amber-400" />
                    </div>
                </div>

                <h1 className="mb-1 text-xl font-bold text-white">Verificación en dos pasos</h1>
                <p className="mb-8 text-sm text-gray-400">
                    Introduce el código de 6 dígitos de tu aplicación de autenticación.
                </p>

                <form onSubmit={submit} className="space-y-4">
                    <input
                        type="text"
                        inputMode="numeric"
                        pattern="\d{6}"
                        maxLength={6}
                        autoFocus
                        value={data.code}
                        onChange={e => setData('code', e.target.value.replace(/\D/g, ''))}
                        className="w-full rounded-lg border border-gray-700 bg-gray-800 px-4 py-3 text-center text-2xl font-mono tracking-[0.5em] text-white focus:border-amber-500 focus:outline-none"
                        placeholder="000000"
                        required
                    />
                    {errors.code && (
                        <p className="text-sm text-red-400">{errors.code}</p>
                    )}

                    <button
                        type="submit"
                        disabled={processing || data.code.length !== 6}
                        className="flex w-full items-center justify-center gap-2 rounded-lg bg-amber-500 px-4 py-2.5 text-sm font-semibold text-gray-900 hover:bg-amber-400 disabled:opacity-60"
                    >
                        {processing && <Loader2 className="h-4 w-4 animate-spin" />}
                        Verificar
                    </button>
                </form>

                <form method="POST" action="/superadmin/logout" className="mt-6">
                    <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''} />
                    <button type="submit" className="text-xs text-gray-500 hover:text-gray-300">
                        Cerrar sesión
                    </button>
                </form>
            </div>
        </div>
    );
}
