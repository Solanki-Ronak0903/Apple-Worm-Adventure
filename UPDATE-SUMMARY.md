# Apple Worm Adventure — Admin Settings & Audit Logs

**Date:** 19 November 2026

## Delivered

- Added an admin-only Application Settings page.
- Added functional gates for new registration, leaderboard availability and development-mock rewarded actions.
- Added configurable starting coins for new players only; existing wallet balances are untouched.
- Added admin-only searchable/filterable Audit History.
- Added audit snapshots for settings, level metadata/publication and achievement create/update/status changes.
- Committed audited mutations and audit rows in the same database transaction.
- Added five feature tests covering permissions, settings persistence/audit, setting enforcement, new-account starting coins, and audit search/history.

## Data and safety

- Added `admin_settings` and `admin_audit_logs` tables through additive migrations.
- Existing player, wallet, level, session, achievement and reward records are not rewritten.
- Audit snapshots exclude passwords, cookies, claim tokens and provider secrets.
- Audit records have no edit/delete interface.
- Settings defaults preserve current behaviour.

## Local update

```powershell
composer dump-autoload
php artisan migrate
php artisan optimize:clear
php artisan test
```

Do not run `migrate:fresh` on an existing database.

## Verification caveat

109 PHP files passed `php -l`; 3 JavaScript files passed `node --check`; route/setting integration checks passed. Full PHPUnit and browser verification remain pending locally because this build workspace does not contain Composer `vendor` dependencies or PDO SQLite.
