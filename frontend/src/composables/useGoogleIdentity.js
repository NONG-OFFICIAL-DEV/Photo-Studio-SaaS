/*
 * Thin wrapper around Google Identity Services (GIS) — the frontend gets a
 * signed ID token directly from Google, no server-side redirect/callback
 * dance needed. The script is injected lazily (once, shared across every
 * call site) rather than statically in index.html, so pages that never show
 * a Google button don't pay for it.
 */
let scriptPromise = null

function loadGoogleScript() {
  if (scriptPromise) return scriptPromise

  scriptPromise = new Promise((resolve, reject) => {
    if (window.google?.accounts?.id) {
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
   * Registers the client ID + credential callback. Google warns (and logs)
   * if this is called more than once for the same client, so callers
   * should call it exactly once per mounted page — see renderButton() for
   * the part that's safe (and meant) to call repeatedly.
   */
  async function initialize(onCredential) {
    const clientId = import.meta.env.VITE_GOOGLE_CLIENT_ID
    if (!clientId) return null

    const google = await loadGoogleScript()

    google.accounts.id.initialize({
      client_id: clientId,
      callback: (response) => onCredential(response.credential),
    })

    return google
  }

  /**
   * Renders the official "Continue with Google" button into `el`. Safe (and
   * meant) to call again whenever the app's locale changes (see callers'
   * `watch(locale, ...)`) — the button's text is drawn from `locale` fresh
   * on each call, so re-rendering updates the language live with no page
   * reload needed. `el.innerHTML` is cleared first so a re-render replaces
   * the button instead of stacking a second one inside the same container.
   */
  function renderButton(el, google, { text = 'continue_with', locale = 'en' } = {}) {
    if (!el || !google) return

    el.innerHTML = ''
    google.accounts.id.renderButton(el, { theme: 'outline', size: 'large', width: 336, text, shape: 'pill', locale })
  }

  return { initialize, renderButton }
}
