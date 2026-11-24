# Achievement Management – 19 November 2026

## Included

- Admin-only achievement library with search, status/metric filters, sorting, pagination and unlock counts.
- Create achievement definitions using the five progress metrics already supported by the game: `apples`, `levels`, `stars`, `rewards`, and `golden_apples`.
- Edit display name, description, icon, target and coin reward. The internal code is immutable.
- Inspect tracked-player and unlock totals plus recent player unlocks.
- Activate/deactivate achievements instead of deleting them.
- Existing earned achievements and wallet ledger entries are retained when an achievement is deactivated.
- Progress metric changes are blocked once player tracking records exist, to avoid reinterpreting saved progress.
- The game dashboard continues to show an inactive achievement to a player who already unlocked it.
- AchievementService ignores inactive definitions for future progress and unlocks.
- DatabaseSeeder uses `firstOrCreate` for achievement definitions so rerunning seeding does not overwrite admin-edited definitions.

## Migration

A new migration adds `achievements.is_active` with a default of `true`:

`2026_11_19_000001_add_is_active_to_achievements_table.php`

Run `php artisan migrate` before opening the new admin screen. Do not use `migrate:fresh` on an existing database.

## Admin routes

- `GET /admin/achievements`
- `GET /admin/achievements/create`
- `POST /admin/achievements`
- `GET /admin/achievements/{achievement}`
- `PUT /admin/achievements/{achievement}`
- `PATCH /admin/achievements/{achievement}/status`

All routes are inside the existing `auth`, `admin`, and admin throttle middleware group.

## Local verification

PHP syntax validation was run across application, migration, seeder, route and test PHP files. PHPUnit was not run in this build environment because Composer dependencies (`vendor`) are not present. Run the full suite on the Windows development machine after migration.
