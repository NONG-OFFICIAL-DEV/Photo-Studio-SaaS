---
name: arch-security-review
description: Review a diff or area of code for security risks specific to this app's architecture (multi-tenancy, UUID PKs, RBAC teams, Telegram bot routing, broadcasting auth). Complements the generic security-review skill — use both for a full audit; this one alone is not comprehensive.
---

# Architecture-specific security review

The built-in `security-review` skill already covers generic/OWASP-class risks (SQL injection, XSS, CSRF, insecure deserialization, generic auth bypass) — run that for broad coverage. **This skill exists for the risks a generic scanner has no way to know about**, because they only exist due to this app's specific architecture. Run both together; neither alone is a full audit.

## What to check

### 1. Tenant-scope gaps outside request context

`TenantScope` (via `BelongsToTenant`) only filters when `TenantContext::check()` is true — i.e. only inside a request that went through `IdentifyTenant` middleware. Any query on a tenant-owned model run from a **console command, queued job, or scheduled sweep** gets zero scoping — not narrow, not wrong, *none*.

- Any new console command / job touching a tenant-owned model: does it explicitly scope to the right tenant, or is it a deliberate all-tenants sweep (fine, per `InvoiceService::markOverdue()`'s established convention)?
- Any "look up all super admins" or "sweep every tenant" query reachable from code that *might* run inside an active tenant request context: does it use `User::withoutGlobalScopes()`? (Real prior bugs: `SubscriptionService::superAdmins()`, `PaymentConfirmationService::submit()`.)

### 2. UUID-vs-int cast collapsing to always-true

Every PK in this app is a UUID string. `(int) $a === (int) $b` on two UUIDs both casts to `0`, making the comparison always true — this was a real bug in the scaffolded `routes/channels.php` (any user could subscribe to any other user's private channel).

```bash
grep -rn "(int)" backend/app backend/routes --include="*.php" | grep -iE "id|uuid"
```

Check every hit: is either side actually a UUID? If so, the cast is wrong — compare as strings.

### 3. IDOR — route-bound models without structural tenant scoping

A controller action that resolves a model from a route parameter (`{customer}`, `{booking}`, etc.) must be unreachable cross-tenant *structurally* (via `TenantScope` on the query), not just filtered-then-checked. Since `IdentifyTenant` is deliberately prepended before `SubstituteBindings` in `bootstrap/app.php`, route-model binding should already resolve inside the tenant scope — verify any new route group doesn't accidentally skip the `tenant` middleware while still doing model binding.

### 4. RBAC team-id not set before a permission check

`spatie/laravel-permission`'s Teams feature is keyed on `tenant_id`. Any new code path that checks or assigns a role/permission must have `app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId)` called first (normally handled by existing middleware — check any new code that checks permissions *outside* a normal tenant request, e.g. a console command or job).

### 5. Customer-facing Telegram using the wrong bot

Customers must only ever be reached via **their tenant's own** bot (`Customer::tenant->telegram_bot_token`), never the platform-wide admin bot (`config('services.platform_telegram')`, which is `User`/staff-only via `TelegramAdminChannel`). Any new Customer notification: does its `via()` route Telegram through `TelegramTenantBotChannel`, not `TelegramAdminChannel`?

### 6. Broadcasting auth middleware regression

`/api/broadcasting/auth` must stay registered under `auth:api` (see `AppServiceProvider::configureBroadcasting()`). This app has no session/CSRF — if this route ever falls back to Laravel's default `web` guard (e.g. from a `channels:` key reappearing in `bootstrap/app.php`'s `withRouting()`), it silently breaks in a way that's easy to miss until someone tests unauthenticated broadcasting behavior directly.

### 7. Inbound webhooks without signature/secret verification

Any new inbound webhook route (Telegram, payment provider, etc.) must verify a secret or signature before trusting the payload — matching the existing convention (`webhook ignores a request with the wrong secret` test). Flag any webhook controller that reads `$request` data before that check.

### 8. Mass-assignment on sensitive fields

`tenant_id`, `is_super_admin`, and role/permission fields must never be client-fillable. Check any new Form Request / model `$fillable` change:

```bash
grep -rn "tenant_id\|is_super_admin" backend/app/Http/Requests backend/app/Models --include="*.php"
```

### 9. Plan/feature gate bypass

A gated feature must be enforced at the middleware layer (`subscription.active`, plan-feature middleware) on the actual API route, not just hidden in the frontend UI (`hasFeature()` router guard). Check any new gated route is in the right middleware group in `routes/api/v1.php` — a route in the wrong group is reachable directly even with a lapsed subscription or missing plan feature.

## Output

Report findings the same way the generic `security-review` skill does — file:line, concrete failure scenario, severity. Don't re-report generic OWASP-class findings here; that's the other skill's job.
