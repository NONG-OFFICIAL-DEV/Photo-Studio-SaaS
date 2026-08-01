/*
 * The one place every money amount gets displayed from. Every plan/invoice/
 * order/report amount in this app is tracked and shown in USD regardless of
 * the tenant's `settings.currency` field (that field is editable in Settings
 * but nothing reads it for display yet — multi-currency display would need
 * its own dedicated work, not a silent side effect of this helper).
 *
 * Always 2 decimal places, even for whole-dollar amounts ($5.00, not $5) —
 * a inconsistent digit count next to other prices reads as an error, and a
 * quarterly/yearly price is often NOT a whole dollar amount (e.g. $13.50),
 * so never round away the cents (a prior bug here used toFixed(0) for a
 * "cleaner-looking" price and silently displayed $13.50 as $14).
 */
export function formatCurrency(value) {
  const amount = Number(value)
  return `$${(Number.isFinite(amount) ? amount : 0).toFixed(2)}`
}
