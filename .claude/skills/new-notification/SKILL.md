---
name: new-notification
description: Scaffold a new Notification class (User or Customer recipient) following this project's established shape, including the frontend i18n wiring. Use whenever adding a notification for a new event.
---

# Adding a new notification type

Every notification in this app follows the same shape (see `CLAUDE.md`'s "Notification system" section for the full rationale). This skill is the concrete step-by-step for adding one.

## 1. Decide the recipient type — it changes everything below

- **`User` recipient** (staff/owner/super admin) — use the `NotifiesViaPreferredChannels` trait (`App\Notifications\Concerns`). `via()` just returns `$this->preferredChannels($notifiable)`, which reads the user's own `wantsChannel('system'|'mail'|'telegram')` preferences. Telegram for this audience always goes through `TelegramAdminChannel` (platform-wide bot).
- **`Customer` recipient** — no preference system, no in-app bell. Write `via()` by hand based on what the customer actually has (`mail` if `email` is set, `\App\Notifications\Channels\TelegramTenantBotChannel::class` if `telegram_chat_id` is set). No `toDatabase()` — customers have no notification list to read it from. Telegram goes through the **tenant's own** bot, never the platform one.

## 2. Write the class

Place it under `app/Notifications/<Domain>/` (e.g. `Booking/`, `Invoice/`, `Billing/`), matching an existing domain folder or a new one if this is a new domain. Concrete reference: `app/Notifications/Booking/UpcomingBookingNotification.php`.

```php
class SomethingHappenedNotification extends Notification
{
    use NotifiesViaPreferredChannels; // User recipients only

    public function __construct(protected SomeModel $model) {}

    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event' => 'domain.something_happened', // dot-path, becomes the i18n key
            'severity' => 'info', // info | warning | danger | success
            // ...whatever params the message needs to interpolate
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    public function toMail(object $notifiable): MailMessage { /* real English copy here */ }

    public function toTelegram(object $notifiable): string { /* real English copy here */ }
}
```

**`toDatabase()`/`toArray()` carry only structured data — `event` + params, never hardcoded English.** The frontend renders the actual sentence from its own i18n template. `toMail()`/`toTelegram()` *do* write real English, since those channels have no frontend to render anything.

## 3. Dispatch it

Always `Notification::send($recipients, new SomethingHappenedNotification($model))` — never `$model->notify(...)`, even for a single recipient.

If this fires from a scheduled sweep rather than an immediate action (e.g. "N days before X"), follow the idempotent-reminder pattern already used for subscription-expiring/booking-upcoming/invoice-due: add a `*_notified_at` timestamp column, guard the sweep query with `whereNull(...)`, and set the timestamp only after a successful send. Register the command in `routes/console.php` via `Schedule::command(...)`.

## 4. Wire the frontend message

Add the key to **both** `frontend/src/locales/en.json` and `km.json`, nested by the `event` string's dot segments — `event: 'domain.something_happened'` becomes `notifications.events.domain.something_happened`. Use `{paramName}` placeholders matching whatever you put in `toDatabase()`:

```json
"domain": {
  "something_happened": "{customer} did the thing on {date}"
}
```

Then run the **i18n-parity** skill to verify both files stay in sync and no Khmer-script digits crept in.

Check `frontend/src/composables/useNotificationDisplay.js`'s `message()` function — if your new params (`customer`, `date`, etc.) aren't already in the list it passes to `t()`, add them there too, or the interpolation will render empty. `icon`/`color` come from `severity` generically and almost never need a change.

## 5. Verify

Write a Feature test asserting the notification was sent (`Notification::fake()` + `Notification::assertSentTo()`) — this is the standard pattern in this codebase (`Customer` recipients especially, since they have no database channel to check a row count against instead).
