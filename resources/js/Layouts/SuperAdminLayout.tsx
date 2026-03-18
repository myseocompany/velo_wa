import { Link, usePage, router } from '@inertiajs/react';
import { Activity, LayoutDashboard, LogOut, Shield, Building2, ChevronDown } from 'lucide-react';
import { PropsWithChildren, useState } from 'react';

interface PlatformAdmin {
    id: string;
    name: string;
    email: string;
    two_factor_enabled: boolean;
}

interface SuperAdminPageProps {
    platform_admin: PlatformAdmin | null;
    flash?: { success?: string; error?: string };
}

const navItems = [
    { label: 'Dashboard',  href: '/superadmin',         icon: <LayoutDashboard size={18} />, routeName: 'superadmin.dashboard' },
    { label: 'Tenants',    href: '/superadmin/tenants',  icon: <Building2 size={18} />,       routeName: 'superadmin.tenants.index' },
    { label: 'Auditoría',  href: '/superadmin/audit',    icon: <Activity size={18} />,        routeName: 'superadmin.audit' },
];

export default function SuperAdminLayout({ children, title }: PropsWithChildren<{ title?: string }>) {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const props = usePage<any>().props as SuperAdminPageProps;
    const admin = props.platform_admin;
    const flash = props.flash;
    const [menuOpen, setMenuOpen] = useState(false);
    const currentRoute = route().current();

    const logout = () => router.post('/superadmin/logout');

    return (
        <div className="flex h-screen bg-gray-950">
            {/* Sidebar */}
            <aside className="flex w-56 flex-col border-r border-gray-800 bg-gray-900">
                {/* Logo */}
                <div className="flex h-16 items-center gap-2 border-b border-gray-800 px-4">
                    <Shield className="h-6 w-6 text-amber-400" />
                    <div>
                        <p className="text-sm font-bold text-white">AriCRM</p>
                        <p className="text-xs text-gray-400">Admin Panel</p>
                    </div>
                </div>

                {/* Nav */}
                <nav className="flex-1 space-y-1 px-2 py-4">
                    {navItems.map(item => {
                        const isActive = currentRoute === item.routeName;
                        return (
                            <Link
                                key={item.routeName}
                                href={item.href}
                                className={`flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors ${
                                    isActive
                                        ? 'bg-amber-500/10 text-amber-400'
                                        : 'text-gray-400 hover:bg-gray-800 hover:text-white'
                                }`}
                            >
                                <span className={isActive ? 'text-amber-400' : 'text-gray-500'}>
                                    {item.icon}
                                </span>
                                {item.label}
                            </Link>
                        );
                    })}
                </nav>

                {/* Admin menu */}
                <div className="border-t border-gray-800 p-2">
                    <div className="relative">
                        <button
                            onClick={() => setMenuOpen(p => !p)}
                            className="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm text-gray-400 hover:bg-gray-800"
                        >
                            <div className="flex h-7 w-7 items-center justify-center rounded-full bg-amber-500/20 text-xs font-bold text-amber-400">
                                {admin?.name.charAt(0).toUpperCase() ?? '?'}
                            </div>
                            <div className="min-w-0 flex-1 text-left">
                                <p className="truncate text-xs font-medium text-gray-200">{admin?.name}</p>
                                <p className="truncate text-xs text-gray-500">Super Admin</p>
                            </div>
                            <ChevronDown size={12} className="text-gray-500" />
                        </button>

                        {menuOpen && (
                            <div className="absolute bottom-full mb-1 w-full rounded-md border border-gray-700 bg-gray-800 py-1 shadow-lg">
                                <Link
                                    href="/superadmin/2fa/setup"
                                    className="block px-4 py-2 text-xs text-gray-300 hover:bg-gray-700"
                                    onClick={() => setMenuOpen(false)}
                                >
                                    {admin?.two_factor_enabled ? '🔒 Gestionar 2FA' : '⚠️ Activar 2FA'}
                                </Link>
                                <button
                                    onClick={logout}
                                    className="flex w-full items-center gap-2 px-4 py-2 text-xs text-red-400 hover:bg-gray-700"
                                >
                                    <LogOut size={12} />
                                    Cerrar sesión
                                </button>
                            </div>
                        )}
                    </div>
                </div>
            </aside>

            {/* Content */}
            <div className="flex min-w-0 flex-1 flex-col overflow-hidden">
                {/* Flash messages */}
                {flash?.success && (
                    <div className="border-b border-green-800 bg-green-900/40 px-6 py-3 text-sm text-green-300">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="border-b border-red-800 bg-red-900/40 px-6 py-3 text-sm text-red-300">
                        {flash.error}
                    </div>
                )}
                <main className="flex-1 overflow-y-auto bg-gray-950 text-gray-100">
                    {children}
                </main>
            </div>
        </div>
    );
}
