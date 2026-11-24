# Build Verification — Authentication & Account Security

## Source checks performed in this build workspace

- PHP syntax: 89 PHP files under `app`, `bootstrap`, `config`, `database`, `routes`, and `tests` passed `php -l`.
- JavaScript syntax: all 5 JavaScript files under `public` and `resources` passed `node --check`.
- Route source checks: password reset, password update, account password change, sign-out-other-devices, signed email verification, verification resend, and optional verified-email enforcement routes are present.
- Security settings: email verification is opt-in from Admin Settings and defaults off until mail delivery is configured.
- Reward mock safety: development mock rewards are enabled only when `APP_ENV` is neither `production` nor `prod`; there is no environment override to re-enable mock rewards in production.
- Regression tests added: 11 account/authentication tests and one reward-mock fail-closed test. The repository contains 99 feature/unit test methods in total.
- Database changes: one additive migration creates `account_security_events`; existing `users`, `password_reset_tokens`, and `sessions` tables are reused.
- No destructive migrations, database seeding, or player-data resets are required.

## Not executed in this workspace

- Full PHPUnit suite: not run because this workspace does not include `vendor` and Composer is unavailable.
- Real SMTP delivery: not tested; `.env.example` uses `MAIL_MAILER=log` for local development.
- Browser/mobile manual QA: not performed in this build workspace.

## Local verification

```powershell
composer dump-autoload
php artisan migrate
php artisan optimize:clear
php artisan test
```

Keep the existing `.env`, `vendor`, and database. Do not run `migrate:fresh`.
