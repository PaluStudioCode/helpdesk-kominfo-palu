import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const isClient = typeof window !== 'undefined';
const hostname = isClient ? window.location.hostname : 'localhost';
const isLocalhost = hostname === 'localhost' || hostname === '127.0.0.1';
const isHttps = isClient && window.location.protocol === 'https:';

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: hostname,
    wsPort: isLocalhost ? (Number(import.meta.env.VITE_REVERB_PORT) || 8080) : (isHttps ? 443 : 80),
    wssPort: isLocalhost ? (Number(import.meta.env.VITE_REVERB_PORT) || 8080) : 443,
    forceTLS: isLocalhost ? false : isHttps,
    enabledTransports: ['ws', 'wss'],
});

