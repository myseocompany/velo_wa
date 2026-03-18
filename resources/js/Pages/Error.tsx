import { Link } from '@inertiajs/react';
import { AlertTriangle, Lock, Search } from 'lucide-react';

interface ErrorProps {
    status: 403 | 404 | 500 | 503;
}

const errors = {
    403: {
        icon: <Lock className="h-16 w-16 text-red-400" />,
        title: 'Acceso denegado',
        description: 'No tienes permisos para ver esta página. Contacta al administrador si crees que esto es un error.',
    },
    404: {
        icon: <Search className="h-16 w-16 text-gray-400" />,
        title: 'Página no encontrada',
        description: 'La página que buscas no existe o ha sido movida.',
    },
    500: {
        icon: <AlertTriangle className="h-16 w-16 text-orange-400" />,
        title: 'Error del servidor',
        description: 'Algo salió mal de nuestro lado. Por favor intenta de nuevo más tarde.',
    },
    503: {
        icon: <AlertTriangle className="h-16 w-16 text-orange-400" />,
        title: 'Servicio no disponible',
        description: 'Estamos realizando mantenimiento. Por favor intenta de nuevo en unos minutos.',
    },
} satisfies Record<number, { icon: React.ReactNode; title: string; description: string }>;

export default function Error({ status }: ErrorProps) {
    const err = errors[status] ?? errors[500];

    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-gray-50 px-4 text-center">
            <div className="mb-6">{err.icon}</div>
            <h1 className="mb-2 text-3xl font-bold text-gray-900">{err.title}</h1>
            <p className="mb-8 max-w-md text-gray-500">{err.description}</p>
            <div className="flex gap-3">
                <Link
                    href="/dashboard"
                    className="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-700"
                >
                    Ir al dashboard
                </Link>
                <button
                    onClick={() => window.history.back()}
                    className="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    Volver
                </button>
            </div>
        </div>
    );
}
