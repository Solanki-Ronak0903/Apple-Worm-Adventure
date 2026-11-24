# Apple Worm Adventure — Authentication & Account Security Update

## Included

- Strong password requirements (minimum 12 characters, mixed case, number, symbol) on registration, password change and reset.
- Password change requires the current password.
- Forgot-password and reset-password flows use Laravel's password broker and the existing `password_reset_tokens` table.
- Generic reset request responses avoid revealing whether an email address has an account.
- Signed email verification links, resend route and verification status in the player account area.
- Optional administrator-controlled email-verification enforcement for game routes. It defaults off until SMTP delivery is configured; account and verification routes remain accessible.
- Account profile/email changes require current-password confirmation. Email changes clear `email_verified_at` and send a new verification link.
- Sign out other remembered devices through Laravel's session authentication middleware and `logoutOtherDevices`.
- Per-account and per-IP login throttling, per-account and per-IP password-reset throttling, session authentication checks and no-store headers on account/auth pages.
- Development-mock rewarded actions fail closed in production, even if the admin reward toggle is enabled. Mock rewards are allowed only when `APP_ENV` is not `production` or `prod`; there is no environment override to re-enable them on a public production host. Real provider verification is the next ads phase.
- Account security-event history for successful/failed login, logout, password changes/resets, email changes/verification and other-session revocation. Passwords, reset tokens, cookies and provider secrets are never stored in these event records.
- Admin Settings includes `require_email_verification`, with changes recorded through the existing audit logger.

## Database

One migration is added:

`2026_11_19_000004_create_account_security_events_table.php`

The existing `users.email_verified_at`, `password_reset_tokens` and `sessions` structures are reused. No destructive migration or seeding is required.

## Installation

```powershell
composer dump-autoload
php artisan migrate
php artisan optimize:clear
php artisan test
```

Keep the existing `.env`, `vendor`, and database. Do not run `migrate:fresh`.

## Email configuration

The example environment uses `MAIL_MAILER=log`; reset and verification messages will be written to the Laravel log during local development. Configure a real SMTP transport before enabling **Require verified email before playing** under `/admin/settings`.

For production, use HTTPS, `APP_DEBUG=false`, `SESSION_DRIVER=database`, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, a real mail service, and secret storage outside source control.

## Not included in this phase

- Multi-factor authentication (TOTP/passkeys); this can be a separate security milestone if required before production.
- External breach-password lookup.
- Real rewarded-ad provider integration. Rewarded actions remain a development mock locally and are disabled in production until the later ads phase implements signed provider verification.

## Verification coverage added

- 11 account/authentication feature tests covering registration policy, verification notification, sign-in normalization and event logging, profile/email changes, password change, reset request/reset completion, signed verification, optional verification enforcement, other-device sign-out, and private response headers.
- 1 reward-security regression test ensures the development mock cannot grant rewards when mock mode is disabled (the production-safe setting).
- Existing reward tests continue to exercise the mock in the PHPUnit `testing` environment.
