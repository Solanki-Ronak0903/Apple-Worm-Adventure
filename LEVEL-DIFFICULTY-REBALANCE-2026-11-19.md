# Apple Worm Adventure — Level Difficulty Rebalance

Date: 19 November 2026

## Why this update exists

Manual screenshots from levels 8, 12, 14, 17, 21, 23 and 25 showed repeated visual patterns, especially the raised shelf over a spike row in levels 17 and 23. Later levels also had too much move freedom and Sky Temple still read visually like the meadow environment.

## Gameplay changes

- Level 17 is now **Shard Switchway**. The player collects the near apple, activates a crystal rune, opens a gate, crosses the guarded shelf and collects the far apple before the portal.
- Level 21 is now **Cloudstep Gauntlet**. It has three rising/falling platform tiers, three apples and a four-tile spike chasm. It is no longer a simple two-gap floor route.
- Levels 17 and 21 now enter from the left; the existing mirrored-entry levels remain 8, 12, 14, 23 and 25.
- Both changed levels have new approved server-side solution sequences and updated three-stage hints.
- Sky Temple now uses its own celestial background, floating temple pillars, cloud islands, sparkles and blue-violet cloudstone tiles instead of meadow hills and trees.

## Move budgets

Every released side-view level now has a server-enforced move limit in addition to its three-star par target. The campaign gives progressively less spare movement:

| World | Levels | Spare moves above par |
|---|---:|---:|
| Sunlit Meadow | 1–5 | 5 |
| Fernwood Trail | 6–10 | 4 |
| Mossy Ruins | 11–15 | 3 |
| Crystal Hollow | 16–20 | 2 |
| Sky Temple | 21–25 | 2 |

The HUD shows moves used, moves left and the three-star target. The server rejects further movement once the budget (including any server-awarded bonus moves) has been used. An extra-move reward allowance is still supported by the existing reward flow. The three-star target stays tied to `parMoves`; using bonus moves does not make the three-star target easier.

## Database update and safety

The migration `2026_11_19_000005_rebalance_side_view_levels_and_move_limits.php` updates the existing 25 level configurations and creates `side_view_level_rebalance_backups` with the prior map/settings values for rollback. It does not change player progress, game sessions, wallets, achievement history or reward transactions. For the two renamed levels, default title/lesson/hint text is updated only if it still matches the shipped defaults; administrator-customized metadata is preserved. The legacy config title is synchronized only when it still matches the old shipped title.

On an existing installation, run `php artisan migrate`; do not run `migrate:fresh` and do not rerun the seeder just for this update. The seeder intentionally preserves existing administrator changes.

## Verification performed for this package

- PHP syntax checks passed for 91 PHP files across app, bootstrap, config, database, routes and tests.
- JavaScript syntax checks passed for 5 files.
- All 25 stored solution routes replayed successfully through the production `SideViewPuzzleService` using the workspace simulator. Levels 17 and 21 replayed in 14 and 16 moves respectively.
- The Laravel PHPUnit suite could not be run in the packaging workspace because Composer dependencies / `vendor` and PDO SQLite are not installed there. Run `php artisan test` on the Windows project after migration.

## Windows update steps

```powershell
composer dump-autoload
php artisan migrate
php artisan optimize:clear
php artisan test
php artisan serve
```

Back up the project folder and database before applying the update.
