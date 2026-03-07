import { Link, usePage } from '@inertiajs/react';
import {
    BarChart2,
    ChevronDown,
    Inbox,
    LayoutDashboard,
    LogOut,
    MessageSquare,
    Settings,
    Users,
} from 'lucide-react';
import { PropsWithChildren, useState } from 'react';
import { PageProps } from '@/types';

interface NavItem {
    label: string;
    href: string;
    icon: React.ReactNode;
    routeName: string;
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
        label: 'Configuración',
        href: '/settings',
        icon: <Settings size={18} />,
        routeName: 'settings',
    },
];

export default function AppLayout({ children, title }: PropsWithChildren<{ title?: string }>) {
    const { auth } = usePage<PageProps>().props;
    const { user, tenant } = auth;
    const [userMenuOpen, setUserMenuOpen] = useState(false);
    const currentRoute = route().current();

    return (
        <div className="flex h-screen bg-gray-50">
            {/* Sidebar */}
            <aside className="flex w-60 flex-col border-r border-gray-200 bg-white">
                {/* Logo */}
                <div className="flex h-16 items-center gap-2 border-b border-gray-200 px-4">
                    <MessageSquare size={22} className="text-green-600" />
                    <span className="text-lg font-semibold text-gray-900">Velo</span>
                    {tenant && (
                        <span className="ml-auto max-w-[80px] truncate text-xs text-gray-400">
                            {tenant.name}
                        </span>
                    )}
                </div>

                {/* Navigation */}
                <nav className="flex-1 space-y-1 overflow-y-auto px-2 py-4">
                    {navItems.map((item) => {
                        const isActive = currentRoute === item.routeName;
                        return (
                            <Link
                                key={item.routeName}
                                href={item.href}
                                className={`flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors ${
                                    isActive
                                        ? 'bg-green-50 text-green-700'
                                        : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
                                }`}
                            >
                                <span className={isActive ? 'text-green-600' : 'text-gray-400'}>
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
                            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-green-100 text-xs font-semibold text-green-700">
                                {user.name.charAt(0).toUpperCase()}
                            </div>
                            <div className="min-w-0 flex-1 text-left">
                                <p className="truncate font-medium text-gray-900">{user.name}</p>
                                <p className="truncate text-xs text-gray-400">{user.role}</p>
                            </div>
                            <ChevronDown size={14} className="shrink-0 text-gray-400" />
                        </button>

                        {userMenuOpen && (
                            <div className="absolute bottom-full mb-1 w-full rounded-md border border-gray-200 bg-white py-1 shadow-lg">
                                <Link
                                    href="/profile"
                                    className="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                                    onClick={() => setUserMenuOpen(false)}
                                >
                                    Perfil
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
                <main className="flex-1 overflow-y-auto">{children}</main>
            </div>
        </div>
    );
}
