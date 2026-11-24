# Admin Panel Update – 18 November 2026

This update builds the first management milestone on top of the existing side-view puzzle game.

## Included

- Dedicated responsive admin layout with sidebar navigation.
- Expanded admin dashboard: player counts, new/active players, published levels, sessions, completion rate, wallet coins, reward claims, weekly activity, popular levels and recent sessions.
- Player directory with name/email/ID search, role filter, sorting, pagination and quick activity summaries.
- Read-only player profile inspection: account details, wallet balance, level progress, stars, achievements, recent sessions and wallet ledger.
- Reward economy page moved into the admin workspace and linked to player profiles.
- Admin routes remain behind `auth`, `admin` and request throttling middleware.
- Feature tests for admin access control, dashboard data, player search and profile visibility.

## Safety boundaries

Player management is intentionally read-only in this first milestone. The current schema does not include account suspension state or a controlled admin-action audit trail. Do not add suspend/delete/wallet-adjustment buttons until those features have dedicated migrations, authorization policies, audit records and tests.

No database migration is required for this update. Existing player accounts, progress, wallets, rewards and game sessions are not modified by the admin screens.

## Routes

- `admin.dashboard` – `/admin`
- `admin.players` – `/admin/players`
- `admin.players.show` – `/admin/players/{user}`
- `admin.economy` – `/admin/economy`

## Local verification

Run on the project machine:

```powershell
composer dump-autoload
php artisan optimize:clear
php artisan test
```

The admin feature suite adds three tests. The previous local baseline was 58 passing tests, so a successful complete run is expected to report 61 passing tests, unless additional local tests have been added.

## Test fixes (18 November 2026)

- The level-library heading now keeps `Level library` as a continuous text node for accessibility and stable assertions.
- The admin library test checks the first page separately and uses the Sky Temple world filter rather than expecting a later-world entry on page one.
- The initial-release test now invokes the one-time release migration against a pre-existing published Level 26, then verifies the seeder creates the 25-level launch catalogue and preserves a subsequent administrator publication choice.
- The seeder itself was deliberately not changed: it must not overwrite level publication choices made from the admin workspace.

## Achievement Management

Achievement Management is now available under `/admin/achievements`. Admins can search, filter, create, edit, inspect unlock history, and activate/deactivate definitions. Only metrics already supported by the game achievement service are accepted. Achievement records are deactivated rather than deleted to preserve player history and reward transactions.

## Game Analytics – 19 November 2026

The Admin Panel now includes an analytics workspace at `/admin/analytics` with 7/30/90-day report windows, daily session activity, player engagement, level and world performance, QA signals for levels with repeated failed attempts, reward claim breakdowns, and signed wallet ledger summaries. Metrics exclude administrator accounts. See `GAME-ANALYTICS-UPDATE.md` for definitions and test coverage.


## Admin Settings and Audit Logs

Added `/admin/settings` and `/admin/audit-logs`. Settings manage registration availability, public leaderboard access, development-mock rewarded actions, and starting coins for new player accounts. Audit history captures settings, level metadata/publication, and achievement definition/status mutations. Audit records include safe before/after snapshots, actor, IP, user-agent and timestamp. Admin mutation + audit row are transactionally committed.
