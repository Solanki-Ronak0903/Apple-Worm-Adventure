# Apple Worm Adventure

Laravel 12 + PHP 8.2+ puzzle game using Blade, JavaScript Canvas and MySQL/SQLite.

## Current gameplay

The active game is an original side-view, tile-based worm puzzle adventure. It includes 25 hand-authored levels across five themed worlds, tile movement, body-following, apple growth, gravity/support rules, crates, spikes, switches, gates, portal completion and three-stage progressive hints.

## Admin workspace

The protected admin workspace includes:

- Operational dashboard with player, session, completion, wallet and reward metrics.
- Player directory with search, role filtering, sorting, pagination and read-only player profile inspection.
- Reward economy and reward claim history.
- Level library with world/difficulty/publication filters, level activity counts and a tile-map preview.
- Safe level metadata editing for title, difficulty, player lesson and progressive hints.
- Server-side validation and sequential publish/unpublish controls for released puzzle levels.
- Achievement library with supported progress metrics, reward configuration, activation controls and preserved player unlock history.
- Game analytics using persisted player sessions, level performance, reward claims and signed wallet ledger records.
- Application settings for registration, leaderboard access, rewarded actions and starting coins for new players.
- Administrator audit history for settings, level and achievement changes, including safe before/after snapshots.

All admin routes require authentication, the `is_admin` flag and request throttling. Player management is read-only. Level map geometry is protected in this release; a full tile-map editor is a later milestone.

## Installation

```powershell
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Configure a dedicated administrator using `ADMIN_EMAIL`, `ADMIN_PASSWORD` and optional `ADMIN_NAME` in `.env` before the first seed. Never use a normal player's email for `ADMIN_EMAIL`.

The first `npm install` creates `package-lock.json` when one is not present. Keep that lockfile in version control; use `npm ci` for later reproducible installs. Do not commit `vendor/`, `node_modules/`, generated Laravel caches, logs, sessions, or your real `.env` file. The root `.gitignore` excludes these local/generated files.

## Tests

```powershell
composer dump-autoload
php artisan optimize:clear
php artisan test
```

Feature tests cover game mechanics, 25 puzzle replays, reward security, player administration, level management, achievement management and game analytics. Run the full suite locally after each update.

The test configuration uses an in-memory SQLite database. Ensure PHP has `pdo_sqlite`, `dom`, `mbstring`, and `xmlwriter` enabled before running PHPUnit. The application also needs `pdo_mysql` for its MySQL environment. The frontend build requires Node.js and the dependencies installed by `npm install`.

## Admin routes

- `/admin`
- `/admin/players`
- `/admin/players/{user}`
- `/admin/levels`
- `/admin/levels/{level}`
- `/admin/economy`
- `/admin/achievements`
- `/admin/analytics`
- `/admin/settings`
- `/admin/audit-logs`

## Level-management safety

The original Level Management module edits descriptive metadata only and did not require a schema migration. The later difficulty-rebalance update adds a separate migration documented below. Tile geometry, starting positions, collectibles, hazards, switches, gates and portals remain protected. Publishing a released level triggers a private server-side replay of its approved route. The validation session is removed and does not affect player progress or rewards.

The seeder uses `firstOrCreate` for shipped level records. Re-running `php artisan db:seed --class=DatabaseSeeder` will not overwrite existing administrator edits or publication status. Do not run `migrate:fresh` on a database containing player data.

## Latest regression fix — 19 November 2026

The initial-release regression test now refreshes the Level 26 Eloquent model after the one-time query-builder migration before simulating an administrator publishing the future level. A subsequent `DatabaseSeeder` run is asserted to preserve that choice. The production seeder remains unchanged and keeps its idempotent `firstOrCreate` behaviour. See `SEEDER-REGRESSION-FIX-2026-11-19.md`.

## Achievement Management (19 November 2026)

The admin workspace now includes `/admin/achievements` for achievement search, filtering, creation, metadata/criteria editing, unlock inspection, and activation controls. Supported progress metrics are apples, levels, stars, rewarded actions, and golden apples. Existing unlocks and wallet ledger records are preserved.

Apply the new migration before using this module:

```powershell
php artisan migrate
php artisan test
```

Do not run `migrate:fresh` against an existing project database.

## Admin Game Analytics

Administrators can open **Admin → Analytics** at `/admin/analytics`. The report supports 7, 30, and 90-day windows and summarizes player sessions, completion/failure rates, level and world performance, reward claims, wallet ledger movement, and usage of hints/revives/boosters. It uses existing records and does not require a database migration. Metric definitions and caveats are documented in `GAME-ANALYTICS-UPDATE.md`.

## Admin Settings and Audit Logs (19 November 2026)

The admin-only Settings page exposes four guarded controls: new registrations, public leaderboard availability, development-mock rewarded actions and the initial coin balance for new accounts. The starting balance does not alter existing player wallets. Defaults preserve the previous application behaviour.

Audit History records application-setting changes, level metadata and publication changes, and achievement definition/status changes. Each record includes the acting administrator, action, subject, summary, non-secret before/after values, IP address, user agent and timestamp. The mutation and audit entry are committed in the same database transaction. The application exposes no audit-log edit/delete route. Secrets, passwords, session cookies and reward claim tokens are not recorded.

This module adds two migrations. On an existing installation, run:

```powershell
composer dump-autoload
php artisan migrate
php artisan optimize:clear
php artisan test
```

No seeding is required for settings defaults. Do not run `migrate:fresh` on a database containing player data.


## Account security setup

This release adds account settings, strong password changes, password recovery, signed email verification, session invalidation, and account-security activity history. Configure a real SMTP mailer before enabling verification in production; the example environment uses the `log` mailer for local development.

Recommended production environment settings:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-smtp-user
MAIL_PASSWORD=your-smtp-password
MAIL_SCHEME=smtp
MAIL_FROM_ADDRESS=noreply@your-domain.example
MAIL_FROM_NAME="Apple Worm Adventure"
```

Keep SMTP credentials in `.env` or your host's secret manager, never in source control or the Admin Settings screen. Once email delivery has been tested, an administrator can enable **Require verified email before playing** under `/admin/settings`. The default is off to avoid blocking existing local/test accounts before mail is configured.

Players can manage their profile, change their password, recover forgotten passwords, verify a changed email address, and sign out other remembered devices from `/account`.

### Reward mock safety

The current rewarded-action adapter is still a development simulation. It is allowed only outside `production` and `prod` environments; there is no environment override to enable mock rewards on a public production host. The real rewarded-ad integration must verify a provider-signed callback before creating a granted reward claim.

## Level difficulty rebalance (19 November 2026)

The campaign now has server-enforced move budgets for all 25 side-view levels, with progressively less spare movement in later worlds. The HUD shows moves used, moves remaining and the three-star par target. Bonus moves already granted by the game's server reward flow extend the move budget without changing the star target.

Levels 17 and 21 have been redesigned to avoid repeating the same raised-shelf-over-spikes pattern: Level 17 is a crystal rune-and-gate puzzle; Level 21 is a three-tier cloud route over a spike chasm with three required apples. Sky Temple has a dedicated celestial background and cloudstone tile palette. See `LEVEL-DIFFICULTY-REBALANCE-2026-11-19.md` for the details.

This update adds `2026_11_19_000005_rebalance_side_view_levels_and_move_limits.php`. On an existing installation, back up the database, then run:

```powershell
composer dump-autoload
php artisan migrate
php artisan optimize:clear
php artisan test
```

The migration backs up the previous level layouts/settings in `side_view_level_rebalance_backups` before updating the campaign maps. It does not alter player progress, sessions, wallets, achievement history or reward transactions. Existing admin-customized title/lesson/hint fields are preserved when they no longer match the shipped defaults. Do not run `migrate:fresh` or rerun the seeder as part of this update.
## Latest gameplay update — 23 November 2026

Worlds 2–5 (Levels 6–25) now include required detour fruit and selected shortcut hazards to increase planning and backtracking. Approved solution routes, three-star par targets, move budgets, progressive hints and level metadata were updated. Five regressions reported after the first-world redesign were addressed: the side-view rewarded-hint path now supports older sessions without a mode flag, and Level 1 tests follow its new approved route and coordinates. Optional rewarded assists remain simulated in development; real ad-provider verification is still pending. See `PHASE-2-TEST-FIXES-AND-CAMPAIGN-EXPANSION-2026-11-23.md` for details and verification status.

On an existing installation, back up the database and run `php artisan migrate`, `php artisan optimize:clear`, `php artisan test`, and `npm.cmd run build`. This update adds `2026_11_23_000001_expand_side_view_difficulty_worlds_two_to_five.php`, which updates level definitions only; it does not alter player progress, wallets or reward claims. Do not run `migrate:fresh`.
