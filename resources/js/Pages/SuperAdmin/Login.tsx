import { Head, useForm } from '@inertiajs/react';
import { Shield, Loader2 } from 'lucide-react';

export default function SuperAdminLogin() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/superadmin/login');
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-950 px-4">
            <Head title="AriCRM Admin" />

            <div className="w-full max-w-sm">
                {/* Header */}
                <div className="mb-8 text-center">
                    <div className="mb-3 flex justify-center">
                        <div className="flex h-14 w-14 items-center justify-center rounded-xl bg-amber-500/10">
                            <Shield className="h-7 w-7 text-amber-400" />
                        </div>
                    </div>
                    <h1 className="text-2xl font-bold text-white">AriCRM Admin</h1>
                    <p className="mt-1 text-sm text-gray-400">Acceso restringido a operadores de plataforma</p>
                </div>

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-gray-300">
                            Correo electrónico
                        </label>
                        <input
                            type="email"
                            autoFocus
                            value={data.email}
                            onChange={e => setData('email', e.target.value)}
                            className="w-full rounded-lg border border-gray-700 bg-gray-800 px-3 py-2.5 text-sm text-white placeholder-gray-500 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                            placeholder="admin@empresa.com"
                            required
                        />
                        {errors.email && (
                            <p className="mt-1.5 text-xs text-red-400">{errors.email}</p>
                        )}
                    </div>

                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-gray-300">
                            Contraseña
                        </label>
                        <input
                            type="password"
                            value={data.password}
                            onChange={e => setData('password', e.target.value)}
                            className="w-full rounded-lg border border-gray-700 bg-gray-800 px-3 py-2.5 text-sm text-white placeholder-gray-500 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                            placeholder="••••••••"
                            required
                        />
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="flex w-full items-center justify-center gap-2 rounded-lg bg-amber-500 px-4 py-2.5 text-sm font-semibold text-gray-900 hover:bg-amber-400 disabled:opacity-60"
                    >
                        {processing && <Loader2 className="h-4 w-4 animate-spin" />}
                        Iniciar sesión
                    </button>
                </form>

                <p className="mt-6 text-center text-xs text-gray-600">
                    Todas las acciones en este panel quedan registradas en el log de auditoría.
                </p>
            </div>
        </div>
    );
}
