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

// Module-scoped so google.accounts.id.initialize() runs exactly once for the
// whole app session — not once per mounted page. Google logs a warning (and
// only keeps the last registration) if initialize() is called again for the
// same client, which used to happen every time the user switched between the
// Login and Register pages (each is a separate component instance with its
// own local "have I called this yet" guard). The credential callback still
// needs to change per page (Login vs Register handle it differently), so
// that part stays swappable via this module-level ref instead of going
// through a second real initialize() call.
let initPromise = null
let currentCredentialHandler = null

export function useGoogleIdentity() {
  /**
   * Registers the client ID + credential callback (google.accounts.id
   * .initialize() itself only ever runs once — see module-level comment
   * above). Safe to call from every page that needs the Google button;
   * each call just updates which page's callback receives the credential.
   */
  async function initialize(onCredential) {
    const clientId = import.meta.env.VITE_GOOGLE_CLIENT_ID
    if (!clientId) return null

    currentCredentialHandler = onCredential

    if (!initPromise) {
      initPromise = loadGoogleScript().then((google) => {
        google.accounts.id.initialize({
          client_id: clientId,
          callback: (response) => currentCredentialHandler?.(response.credential),
        })
        return google
      })
    }

    return initPromise
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
