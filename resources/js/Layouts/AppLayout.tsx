import { Link, usePage, router } from '@inertiajs/react';
import {
    BarChart2,
    ChevronDown,
    Inbox,
    LayoutDashboard,
    LogOut,
    Settings,
    ShieldAlert,
    User,
    UsersRound,
    Users,
} from 'lucide-react';
import { PropsWithChildren, useState } from 'react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import { PageProps, UserRole } from '@/types';

interface ExtendedPageProps extends PageProps {
    impersonation?: { active: boolean; tenant_id?: string; admin_id?: string };
}

interface NavItem {
    label: string;
    href: string;
    icon: React.ReactNode;
    routeName: string;
    roles?: UserRole[];
}

const navItems: NavItem[] = [
    {
        label: 'Dashboard',
        href: '/dashboard',
        icon: <LayoutDashboard size={18} />,
        routeName: 'dashboard',
    },
    {
        label: 'Inbox',
        href: '/inbox',
        icon: <Inbox size={18} />,
        routeName: 'inbox',
    },
    {
        label: 'Contactos',
        href: '/contacts',
        icon: <Users size={18} />,
        routeName: 'contacts',
    },
    {
        label: 'Pipeline',
        href: '/pipeline',
        icon: <BarChart2 size={18} />,
        routeName: 'pipeline',
    },
    {
        label: 'Equipo',
        href: '/team',
        icon: <UsersRound size={18} />,
        routeName: 'team',
    },
    {
        label: 'Configuración',
        href: '/settings',
        icon: <Settings size={18} />,
        routeName: 'settings',
        roles: ['owner', 'admin'],
    },
];

export default function AppLayout({ children, title }: PropsWithChildren<{ title?: string }>) {
    const { auth, impersonation } = usePage<ExtendedPageProps>().props;
    const { user } = auth;
    const [userMenuOpen, setUserMenuOpen] = useState(false);
    const currentRoute = route().current();

    const stopImpersonation = () => {
        router.post('/impersonation/stop');
    };

    const visibleItems = navItems.filter(item =>
        !item.roles || item.roles.includes(user.role as UserRole)
    );

    return (
        <div className="flex h-screen bg-gray-50">
            {/* Sidebar */}
            <aside className="flex w-60 flex-col border-r border-gray-200 bg-white">
                {/* Logo */}
                <div className="flex h-16 items-center gap-2 border-b border-gray-200 px-4">
                    <ApplicationLogo className="h-10 w-full object-contain" />
                </div>

                {/* Navigation */}
                <nav className="flex-1 space-y-1 overflow-y-auto px-2 py-4">
                    {visibleItems.map((item) => {
                        const isActive = currentRoute === item.routeName
                            || currentRoute?.startsWith(item.routeName + '.');
                        return (
                            <Link
                                key={item.routeName}
                                href={item.href}
                                className={`flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors ${
                                    isActive
                                        ? 'bg-ari-50 text-ari-900'
                                        : 'text-ari-900/75 hover:bg-ari-50 hover:text-ari-900'
                                }`}
                            >
                                <span className={isActive ? 'text-ari-500' : 'text-ari-900/40'}>
                                    {item.icon}
                                </span>
                                {item.label}
                            </Link>
                        );
                    })}
                </nav>

                {/* User menu */}
                <div className="border-t border-gray-200 p-2">
                    <div className="relative">
                        <button
                            onClick={() => setUserMenuOpen((prev) => !prev)}
                            className="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm text-gray-700 hover:bg-gray-100"
                        >
                            <div className="h-8 w-8 shrari-0 overflow-hidden rounded-full bg-ari-100">
                                {user.avatar_url ? (
                                    <img src={user.avatar_url} alt={user.name} className="h-full w-full object-cover" />
                                ) : (
                                    <div className="flex h-full w-full items-center justify-center text-xs font-semibold text-ari-700">
                                        {user.name.charAt(0).toUpperCase()}
                                    </div>
                                )}
                            </div>
                            <div className="min-w-0 flex-1 text-left">
                                <p className="truncate font-medium text-gray-900">{user.name}</p>
                                <p className="truncate text-xs text-gray-400">
                                    {user.role === 'owner' ? 'Propietario' : user.role === 'admin' ? 'Administrador' : 'Agente'}
                                </p>
                            </div>
                            <ChevronDown size={14} className="shrari-0 text-gray-400" />
                        </button>

                        {userMenuOpen && (
                            <div className="absolute bottom-full mb-1 w-full rounded-md border border-gray-200 bg-white py-1 shadow-lg">
                                <Link
                                    href="/profile"
                                    className="flex items-center gap-2 px-4 py-2 text-sm text-ari-900 hover:bg-ari-50"
                                    onClick={() => setUserMenuOpen(false)}
                                >
                                    <User size={14} />
                                    Mi perfil
                                </Link>
                                <Link
                                    href="/logout"
                                    method="post"
                                    as="button"
                                    className="flex w-full items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50"
                                    onClick={() => setUserMenuOpen(false)}
                                >
                                    <LogOut size={14} />
                                    Cerrar sesión
                                </Link>
                            </div>
                        )}
                    </div>
                </div>
            </aside>

            {/* Main content */}
            <div className="flex min-w-0 flex-1 flex-col overflow-hidden">
                {/* Impersonation banner */}
                {impersonation?.active && (
                    <div className="flex items-center justify-between bg-amber-500 px-4 py-2 text-sm font-medium text-white">
                        <div className="flex items-center gap-2">
                            <ShieldAlert className="h-4 w-4" />
                            <span>
                                Estás viendo el tenant como <strong>{user.name}</strong> (superadmin en modo impersonación)
                            </span>
                        </div>
                        <button
                            onClick={stopImpersonation}
                            className="rounded bg-white/20 px-3 py-1 text-xs font-semibold text-white hover:bg-white/30"
                        >
                            Salir de impersonación
                        </button>
                    </div>
                )}
                <main className="flex-1 overflow-y-auto">{children}</main>
            </div>
        </div>
    );
}
