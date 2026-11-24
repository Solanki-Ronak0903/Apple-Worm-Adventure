# Admin Settings and Audit Logs Update — 19 November 2026

## Added modules

### Application Settings

The admin-only Settings page controls four application behaviours:

- `allow_registration` — blocks both the registration form and registration submission when disabled.
- `leaderboard_enabled` — makes the player leaderboard unavailable when disabled.
- `rewarded_actions_enabled` — blocks development-mock rewarded actions when disabled. Normal level-completion rewards are unaffected.
- `new_player_starting_coins` — sets the initial wallet balance for newly registered players only; existing balances are never rewritten.

Defaults preserve the current behaviour: registration enabled, leaderboard enabled, rewarded actions enabled, and 100 starting coins. Settings are stored in `admin_settings`; no secrets or `.env` values are editable through the UI.

### Administrator Audit Logs

The admin-only Audit History page records sensitive administrative mutations:

- Application settings changes.
- Level metadata edits and publication/unpublication.
- Achievement creation, edits, activation and deactivation.

Each entry stores the acting administrator (nullable if the account is later deleted), action, subject, summary, safe before/after snapshots, IP address, user agent and timestamp. Audit snapshots intentionally omit passwords, session cookies, claim tokens and provider credentials. Changes and their audit record are written in the same database transaction.

The web application provides no edit/delete action for audit entries. Filters include search, action, administrator and date range.

## New routes

- `GET /admin/settings` — `admin.settings`
- `PUT /admin/settings` — `admin.settings.update`
- `GET /admin/audit-logs` — `admin.audit`

All routes use the existing `auth`, `admin` and admin request-throttling middleware.

## Database changes

Two additive migrations:

- `2026_11_19_000002_create_admin_settings_table.php`
- `2026_11_19_000003_create_admin_audit_logs_table.php`

No existing tables are altered. Run `php artisan migrate`; do not run `migrate:fresh`.

## Verification note

PHP syntax validation is performed for all project PHP files. The complete Laravel PHPUnit suite must be run locally because this package workspace does not include `vendor` or the PDO SQLite extension.
