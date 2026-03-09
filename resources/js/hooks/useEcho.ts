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

interface PresenceMember { id: string; [key: string]: unknown; }

/**
 * Join the tenant's presence channel and track online members.
 * onJoin: called when someone joins (including self on first connect)
 * onLeave: called when someone leaves
 * onHere: called with the full initial member list
 */
export function useTenantPresence(
    tenantId: string | undefined,
    onJoin: (user: PresenceMember) => void,
    onLeave: (user: PresenceMember) => void,
    onHere: (users: PresenceMember[]) => void,
): void {
    useEffect(() => {
        if (!tenantId || !window.Echo) return;

        const channel = window.Echo.join(`presence-tenant.${tenantId}`)
            .here((members: PresenceMember[]) => onHere(members))
            .joining((member: PresenceMember) => onJoin(member))
            .leaving((member: PresenceMember) => onLeave(member));

        return () => {
            window.Echo.leave(`presence-tenant.${tenantId}`);
        };
    }, [tenantId, onJoin, onLeave, onHere]);
}
