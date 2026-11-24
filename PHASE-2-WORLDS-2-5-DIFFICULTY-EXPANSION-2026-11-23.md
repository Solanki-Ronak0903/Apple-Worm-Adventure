# Phase 2 — Worlds 2–5 Difficulty Expansion

Date: 23 November 2026

## Delivered

- Updated the five first-world regression expectations to match the redesigned Level 1 board and current move limit.
- Updated the Level 1 gameplay regression tests to follow the server-approved solution route rather than the old straight-right sequence.
- Added a safe-route fallback for rewarded hints on legacy sessions.
- Added a side-view rewarded-hint service that prefers the approved route and checks the immediate move against board geometry, hazards, gates and the current worm body.
- Redesigned Levels 6–25 with one additional required detour apple per level, and selected extra thorn tiles to close obvious shortcuts.
- Retained existing crate, switch, gate, platform and reverse-entry mechanics; the redesign is additive and does not change the movement engine.
- Updated per-level lesson text, progressive hints, shortest-route par targets and completion move limits.
- Added a database migration for Levels 6–25. It updates level definitions only and leaves player progress, wallets, reward claims and achievements untouched.
- Added a feature test asserting that Levels 6–25 have the added detour objective and that each par target matches its approved route length.

## Difficulty progression

- World 2 (6–10): detour collection, thorn shortcuts and crate positioning.
- World 3 (11–15): backtracking through high pockets, crate/rune sequencing, and a long multi-shelf switchback.
- World 4 (16–20): reverse collection routes, thorn-guarded returns, crate support, and multi-objective crystal puzzles.
- World 5 (21–25): high entry pockets, cloud-tier traversal, crate and rune combinations, and final reversal puzzles.

The new required apples are placed away from the simplest route. All 25 board definitions were replayed in an independent rules simulation; the server-approved route registry matches the generated routes for all 25 levels. The shortest route length is used as the three-star par target. Move limits retain the existing per-world experimentation buffer.

## Rewarded assists

Rewarded assists remain optional. The tutorial world has no reward prompts; +5 moves and double coins unlock from Level 6, and revive unlocks from Level 11. The provider is still a development simulation. A real rewarded-ad provider and signed server-side callback verification are still required before production ads can grant rewards.

## Verification status

- PHP syntax checks: to be run on the packaged source.
- Independent board/path search: 25/25 routes found; route registry matches all 25 shortest paths.
- Laravel PHPUnit suite: not executed in this build environment because the PHP runtime does not have `mbstring` or a PDO SQLite driver. Run `php artisan test` on the Windows development machine.
- Vite build: previous first-world package built successfully; rerun `npm.cmd run build` after extracting this package.

## Apply

Run:

```powershell
php artisan migrate
php artisan optimize:clear
php artisan test
npm.cmd run build
```

Do not run `php artisan migrate:fresh`.
