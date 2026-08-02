import { useI18n } from 'vue-i18n'

const SEVERITY_ICON = {
  info: 'mdi-information-outline',
  warning: 'mdi-alert-outline',
  danger: 'mdi-alert-circle-outline',
  success: 'mdi-check-circle-outline',
}
const SEVERITY_COLOR = {
  info: 'info',
  warning: 'warning',
  danger: 'error',
  success: 'success',
}

/*
 * Shared between the NotificationBell dropdown and the full Notifications
 * page — the `event` + structured params convention (see
 * app/Notifications/Billing on the backend) is rendered into text here,
 * the one place that needs to change if a new event type ships.
 */
export function useNotificationDisplay() {
  const { t } = useI18n()

  function icon(n) {
    return SEVERITY_ICON[n.severity] || 'mdi-bell-outline'
  }

  function color(n) {
    return SEVERITY_COLOR[n.severity] || 'primary'
  }

  function message(n) {
    return t(`notifications.events.${n.event}`, {
      tenant: n.tenant_name,
      plan: n.plan_name,
      days: n.days_left,
      amount: n.amount,
    })
  }

  return { icon, color, message }
}
