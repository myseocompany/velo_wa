import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const loopbackHosts = new Set(['localhost', '127.0.0.1', '0.0.0.0', '::1']);

const isLoopbackHost = (host?: string): boolean => !host || loopbackHosts.has(host);

const toPort = (value: string | undefined, fallback: number): number => {
    const port = Number(value);

    return Number.isFinite(port) && port > 0 ? port : fallback;
};

const envSocketHost = import.meta.env.VITE_REVERB_HOST?.trim();
const envSocketScheme = import.meta.env.VITE_REVERB_SCHEME ?? 'http';
const browserHost = window.location.hostname;
const browserUsesTls = window.location.protocol === 'https:';

// If a production build accidentally ships with loopback socket values,
// use the current page origin so realtime updates still connect correctly.
const useBrowserSocketConfig = !isLoopbackHost(browserHost) && isLoopbackHost(envSocketHost);
const socketHost = useBrowserSocketConfig ? browserHost : envSocketHost ?? browserHost;
const socketSecure = useBrowserSocketConfig ? browserUsesTls : envSocketScheme === 'https';
const socketDefaultPort = socketSecure ? 443 : 80;
const socketPort = useBrowserSocketConfig
    ? toPort(window.location.port, socketDefaultPort)
    : toPort(import.meta.env.VITE_REVERB_PORT, socketDefaultPort);

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: socketHost,
    wsPort: socketPort,
    wssPort: socketPort,
    forceTLS: socketSecure,
    enabledTransports: ['ws', 'wss'],
    // Use axios for channel auth so CSRF token is included automatically
    authorizer: (channel: { name: string }) => ({
        authorize: (socketId: string, callback: (error: boolean, data: unknown) => void) => {
            axios
                .post('/broadcasting/auth', { socket_id: socketId, channel_name: channel.name })
                .then((res) => callback(false, res.data))
                .catch((err) => callback(true, err));
        },
    }),
});
