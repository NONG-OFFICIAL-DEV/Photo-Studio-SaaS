/*
 * Thin wrapper around Google Identity Services' OAuth2 code client — the
 * frontend triggers a real Google popup from our own custom-styled button
 * (not Google's rendered widget), and gets back a one-time authorization
 * code. The backend exchanges that code server-to-server for an ID token
 * (see GoogleAuthorizationCodeExchanger), so no client secret ever reaches
 * the browser. The script is injected lazily (once, shared across every
 * call site) rather than statically in index.html, so pages that never
 * offer Google sign-in don't pay for it.
 */
let scriptPromise = null

function loadGoogleScript() {
  if (scriptPromise) return scriptPromise

  scriptPromise = new Promise((resolve, reject) => {
    if (window.google?.accounts?.oauth2) {
      resolve(window.google)
      return
    }

    const script = document.createElement('script')
    script.src = 'https://accounts.google.com/gsi/client'
    script.async = true
    script.defer = true
    script.onload = () => resolve(window.google)
    script.onerror = () => reject(new Error('Failed to load Google Identity Services.'))
    document.head.appendChild(script)
  })

  return scriptPromise
}

export function useGoogleIdentity() {
  /**
   * Preloads the GIS script so it's already resolved by click time —
   * callers should invoke this in onMounted(). Without it, requestSignIn()
   * would need to `await` script loading *inside* the click handler,
   * breaking the synchronous user-gesture chain the browser requires to
   * allow the sign-in popup (a delayed popup gets silently blocked).
   */
  function preload() {
    loadGoogleScript()
  }

  /**
   * Opens Google's real sign-in popup and calls `onCode(code)` with a
   * one-time authorization code on success. Must be called directly inside
   * a click handler (no `await` before it) to preserve the user gesture.
   */
  async function requestSignIn(onCode) {
    const clientId = import.meta.env.VITE_GOOGLE_CLIENT_ID
    if (!clientId) return

    const google = await loadGoogleScript()

    google.accounts.oauth2.initCodeClient({
      client_id: clientId,
      scope: 'openid email profile',
      ux_mode: 'popup',
      callback: (response) => {
        if (response.code) onCode(response.code)
      },
    }).requestCode()
  }

  return { preload, requestSignIn }
}
