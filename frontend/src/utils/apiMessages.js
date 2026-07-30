import i18n from '@/plugins/i18n'

/**
 * Translates a backend error into the current locale. The backend always
 * sends an English `message` plus a stable `code` (see App\Exceptions\
 * ApiException / App\Traits\ApiResponse) — we look up `apiErrors.<code>`
 * in the frontend's own locale files instead of displaying the English
 * text verbatim. `params` carries values the backend baked into its
 * English message (a plan name, an amount, ...) for interpolation.
 *
 * Falls back to the raw backend message, then to a translated fallback
 * key, if no code is present or no matching translation exists.
 */
export function translateApiMessage(error, fallbackKey) {
  const { t, te } = i18n.global
  const data = error?.response?.data ?? error?.data ?? null
  const code = data?.code
  const params = data?.params ?? {}

  if (code && te(`apiErrors.${code}`)) {
    return t(`apiErrors.${code}`, params)
  }

  return data?.message || (fallbackKey ? t(fallbackKey) : t('common.actionFailed'))
}
