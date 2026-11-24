# Apple Worm Adventure — Gameplay, Hints and Retry Update

Date: 20 November 2026

## What changed

### 1. Retry after a failed run

- The failure modal's **Try again** control is now a regular CSRF-protected HTML `POST` form.
- It calls the existing restart endpoint without relying on the puzzle JavaScript click handler.
- The endpoint resets the same game session and redirects to its play page.
- Claimed reward/booster state and its move allowance remain attached to the session; the board, score and move count reset.
- Completed sessions cannot be restarted through this endpoint.

### 2. Repeatable hints

- The first three requests reveal the authored progressive clues.
- The fourth request reveals the full approved route as a grouped walkthrough.
- Further requests repeat the full walkthrough; the hint button does not become permanently disabled.
- Hint requests continue to be counted in the session's `hints_used` field.

### 3. More varied puzzle objectives

Four existing campaign maps now combine their original obstacle with a rune switch and a locked gate:

- Level 8 — Stone Gate
- Level 12 — Side Pocket
- Level 19 — Prism Steps
- Level 23 — Cloud Garden

Each retains its original terrain, fruit, crate or thorn layout. The extra objective requires the player to locate and activate the rune before using the opened route.

### 4. More room to experiment

The three-star par targets remain unchanged. The completion move limits have been widened:

| World | Previous extra moves above par | New extra moves above par |
|---|---:|---:|
| World 1 | 5 | 6 |
| World 2 | 4 | 5 |
| World 3 | 3 | 4 |
| World 4 | 2 | 4 |
| World 5 | 2 | 4 |

This gives players more room to try a route, recover from a detour and learn the mechanics, without changing the efficient-play three-star target.

## Existing database update

A new migration, `2026_11_20_000001_expand_side_view_challenges_and_move_limits.php`, updates existing stock level records. It records the original values so the migration can be rolled back. It only adds the new map objects when the saved map geometry still matches the shipped stock map, and it preserves non-default custom move limits and customized hint text.

Before applying it to a database with valuable player data, make a database backup. Then run from the project directory:

```bash
php artisan migrate
php artisan optimize:clear
```

For a new database, the updated level catalog is used when levels are seeded.

## Verification performed for this update

- PHP syntax check: all 166 PHP files passed.
- JavaScript syntax check: all 3 files under `public/js` passed.
- Side-view campaign simulation: all 25 approved routes completed successfully.
- Level geometry checks: all 25 maps passed the project's geometry rules.
- Hint service harness: three progressive hints, full walkthrough, and repeatable walkthrough passed.

The full Laravel PHPUnit suite and Vite production build were not run in this environment: the ZIP does not include `vendor` or `node_modules`, Composer is not available here, and this environment has no PDO database drivers. Run `php artisan test` and `npm install && npm run build` on the normal development machine after dependencies are installed.
