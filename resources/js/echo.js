import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

// Only open the realtime websocket when the Reverb server is actually reachable —
// i.e. when browsing directly on the host machine. Over ngrok or from a phone on
// the LAN, Reverb (localhost:8080) is unreachable, and an HTTPS page can't open a
// ws:// socket anyway (blocked as mixed content). Left unguarded, Echo/pusher-js
// retry-loops forever on every page, spamming console errors and burning cycles.
// Skipping it there keeps pages snappy; the only cost is that live leaderboard
// updates need a manual refresh during remote testing.
const host = window.location.hostname;
const reverbReachable = host === 'localhost' || host === '127.0.0.1';

if (reverbReachable) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}
