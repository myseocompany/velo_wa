import { useEffect } from 'react';

/**
 * Subscribe to an event on the tenant's private channel.
 * Automatically unsubscribes when the component unmounts or dependencies change.
 */
export function useTenantChannel(
    tenantId: string | undefined,
    event: string,
    callback: (data: unknown) => void,
): void {
    useEffect(() => {
        if (!tenantId || !window.Echo) return;

        const channel = window.Echo.private(`tenant.${tenantId}`);
        channel.listen(`.${event}`, callback);

        return () => {
            channel.stopListening(`.${event}`, callback);
        };
    }, [tenantId, event, callback]);
}
