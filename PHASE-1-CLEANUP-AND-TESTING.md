# Phase 1 — Repository Cleanup and Verification

Date: 22 November 2026

## Completed in this package

- Added a root `.gitignore` for Composer dependencies, Node dependencies, local environment files, generated Laravel caches/logs/sessions, local SQLite databases, and PHPUnit state.
- Kept `composer.lock`; it is the Composer dependency lockfile and should be committed.
- Removed the captured Laravel log, compiled Blade views, cache data, session data, bootstrap package/service caches, and PHPUnit result cache.
- Removed the unused `public/favicon.ico.txt` placeholder.
- Removed the obsolete one-off test cleanup PowerShell script and its stale instructions. The two test files it targeted are not present in the current test suite.
- Removed the obsolete 2.5D prototype installation note.
- Kept `.env.example`; no real `.env` file was included.
- Excluded `vendor/` and `node_modules/` from the source ZIP, as they are install-generated dependency folders. The root `.gitignore` also excludes them from Git commits. When applying this ZIP over your existing project, keep your existing `vendor/` directory; for a fresh clone, run `composer install`.

## Static checks completed in this environment

- 92 project PHP source files passed `php -l`.
- 6 project JavaScript files passed `node --check`.
- Laravel route registration succeeded and reported 51 routes.
- ZIP integrity was verified after packaging.

## Checks blocked in this environment

- `php artisan test` could not start PHPUnit because this PHP runtime lacks the `dom`, `mbstring`, and `xmlwriter` extensions.
- `npm run build` could not start because `node_modules` is not present.
- Creating `package-lock.json` was attempted with npm, but package-registry access timed out. No fabricated lockfile was added.
- `composer validate` could not run because Composer is not installed in this environment.

## Complete these checks on the Windows development machine

Run these commands from the project root in Command Prompt or PowerShell:

```powershell
php -v
php -m
composer validate --no-check-publish
composer install
php artisan optimize:clear
php artisan route:list
php artisan migrate:status
php artisan test
npm install
npm run build
```

The PHPUnit configuration uses SQLite in-memory, so enable `pdo_sqlite` as well as the PHPUnit-required `dom`, `mbstring`, and `xmlwriter` extensions. The application also needs `pdo_mysql` for its configured MySQL database.

The first successful `npm install` should create `package-lock.json`. Commit that file. Future reproducible installs should use `npm ci`.

Do not run `php artisan migrate:fresh` against a database that contains player progress, wallets, rewards, achievements, or account data. Phase 1 does not require a destructive database reset or any migration.
