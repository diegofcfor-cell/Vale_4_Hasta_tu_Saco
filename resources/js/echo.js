import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

// El WebSocket va al mismo lugar que la página (el proxy de tunel/proxy.mjs
// lo pasa a Reverb), así funciona igual en localhost que por el túnel.
const https = window.location.protocol === 'https:';
const puerto = Number(window.location.port) || (https ? 443 : 80);

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: document.querySelector('meta[name=reverb-key]')?.content,
    wsHost: window.location.hostname,
    wsPort: puerto,
    wssPort: puerto,
    forceTLS: https,
    enabledTransports: ['ws', 'wss'],
});
