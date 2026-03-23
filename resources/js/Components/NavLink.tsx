import { InertiaLinkProps, Link } from '@inertiajs/react';

export default function NavLink({
    active = false,
    className = '',
    children,
    ...props
}: InertiaLinkProps & { active: boolean }) {
    return (
        <Link
            {...props}
            className={
                'inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none ' +
                (active
                    ? 'border-ari-500 text-ari-900 focus:border-ari-600'
                    : 'border-transparent text-ari-700 hover:border-ari-200 hover:text-ari-900 focus:border-ari-200 focus:text-ari-900') +
                className
            }
        >
            {children}
        </Link>
    );
}
