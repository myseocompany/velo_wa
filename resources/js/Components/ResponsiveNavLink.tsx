import { InertiaLinkProps, Link } from '@inertiajs/react';

export default function ResponsiveNavLink({
    active = false,
    className = '',
    children,
    ...props
}: InertiaLinkProps & { active?: boolean }) {
    return (
        <Link
            {...props}
            className={`flex w-full items-start border-l-4 py-2 pe-4 ps-3 ${
                active
                    ? 'border-brand-500 bg-brand-50 text-ink-900 focus:border-brand-600 focus:bg-brand-100 focus:text-ink-900'
                    : 'border-transparent text-ink-700 hover:border-brand-200 hover:bg-brand-50 hover:text-ink-900 focus:border-brand-200 focus:bg-brand-50 focus:text-ink-900'
            } text-base font-medium transition duration-150 ease-in-out focus:outline-none ${className}`}
        >
            {children}
        </Link>
    );
}
