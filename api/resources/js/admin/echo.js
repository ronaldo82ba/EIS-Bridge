import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { useAuthStore } from './store/authStore';

const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY;
const pusherCluster = import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1';

let echoInstance = null;

if (pusherKey) {
    window.Pusher = Pusher;

    echoInstance = new Echo({
        broadcaster: 'pusher',
        key: pusherKey,
        cluster: pusherCluster,
        forceTLS: (import.meta.env.VITE_PUSHER_SCHEME ?? 'https') === 'https',
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        },
        authorizer: (channel) => ({
            authorize: (socketId, callback) => {
                const token = useAuthStore.getState().token ?? localStorage.getItem('token');

                fetch('/broadcasting/auth', {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(token ? { Authorization: `Bearer ${token}` } : {}),
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        socket_id: socketId,
                        channel_name: channel.name,
                    }),
                })
                    .then((response) => {
                        if (!response.ok) {
                            throw new Error(`Broadcast auth failed (${response.status})`);
                        }

                        return response.json();
                    })
                    .then((data) => callback(null, data))
                    .catch((error) => callback(error, null));
            },
        }),
    });
}

export function isEchoEnabled() {
    return echoInstance !== null;
}

export function getEcho() {
    return echoInstance;
}

/**
 * Subscribe to a public channel event. Returns an unsubscribe function.
 */
export function subscribeToChannel(channelName, eventName, handler) {
    if (!echoInstance) {
        return () => {};
    }

    const channel = echoInstance.channel(channelName);
    channel.listen(eventName, handler);

    return () => {
        channel.stopListening(eventName, handler);
    };
}
