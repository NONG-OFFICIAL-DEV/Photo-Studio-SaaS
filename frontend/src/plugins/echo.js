import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import http from '@/apis/api'

window.Pusher = Pusher

let echo = null

/*
 * Laravel Echo's default authorizer assumes Sanctum's cookie/XSRF SPA
 * auth — this app uses a JWT Bearer token instead, so private-channel
 * subscription auth goes through the same `http` axios instance every
 * other request uses (token attach + 401-refresh already handled there),
 * hitting the backend's /api/broadcasting/auth (see
 * AppServiceProvider::configureBroadcasting()).
 */
function authorizer(channel) {
  return {
    authorize(socketId, callback) {
      http
        .post('/broadcasting/auth', { socket_id: socketId, channel_name: channel.name })
        .then((response) => callback(null, response.data))
        .catch((error) => callback(error, null))
    },
  }
}

/**
 * Connects (or returns the existing connection) — called from the auth
 * store right after a session is established, so there's never a live
 * socket for an unauthenticated visitor.
 */
export function connectEcho() {
  if (echo) return echo

  echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    wssPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
    authorizer,
  })

  return echo
}

export function disconnectEcho() {
  echo?.disconnect()
  echo = null
}

export function getEcho() {
  return echo
}
