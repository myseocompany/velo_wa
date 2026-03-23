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
                    ? 'border-ari-500 bg-ari-50 text-ari-900 focus:border-ari-600 focus:bg-ari-100 focus:text-ari-900'
                    : 'border-transparent text-ari-700 hover:border-ari-200 hover:bg-ari-50 hover:text-ari-900 focus:border-ari-200 focus:bg-ari-50 focus:text-ari-900'
            } text-base font-medium transition duration-150 ease-in-out focus:outline-none ${className}`}
        >
            {children}
        </Link>
    );
}
