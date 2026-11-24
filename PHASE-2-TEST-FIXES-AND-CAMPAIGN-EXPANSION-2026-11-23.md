# Apple Worm Adventure — Phase 2 Test Fixes and Campaign Expansion

Date: 23 November 2026

## Summary

This update keeps the approved World 1 redesign (Levels 1–5), fixes the five regressions reported after that update, and raises the challenge across Worlds 2–5 (Levels 6–25).

## Fixes for the five reported test failures

1. **Rewarded hint returned HTTP 422:** Reward handling now detects a side-view board from the level definition as well as the saved state mode. Older sessions or fixtures that predate the `mode` flag are routed to the side-view-aware hint service. This avoids using the legacy grid hint algorithm on a side-view board.
2. **Move HUD expected the old Level 1 limit:** the test now expects the redesigned Level 1 limit of 18 moves.
3. **Apple-growth test used the old straight-right route:** the test now follows the server-approved Level 1 solution until it collects the apple, then checks the worm growth.
4. **Portal test expected the old apple coordinate:** it now checks the redesigned Level 1 required apple at `(9, 6)`.
5. **Completion test sent nine straight-right moves:** it now follows the approved Level 1 route before checking the existing completion/reward flow.

A related Level 21 difficulty assertion was also updated to account for its new fifth thorn tile.

## Worlds 2–5 redesign (Levels 6–25)

Every level from 6 through 25 now has an additional required detour apple. Selected boards also have an extra thorn tile to close an overly obvious shortcut. Existing crates, switches, gates, platforms, reverse-entry starts and themes are retained.

- **World 2 — Fernwood Trail (6–10):** high-pocket fruit, backtracking, thorn-guarded shortcuts and crate repositioning.
- **World 3 — Mossy Ruins (11–15):** return routes, crate/rune sequencing, upper pockets and longer switchbacks.
- **World 4 — Crystal Hollow (16–20):** reversal routes, multi-objective shelf puzzles, crate support and thorn-guarded descents.
- **World 5 — Sky Temple (21–25):** high entry pockets, cloud-tier traversals, crate/rune combinations and final reversals.

The puzzle engine and movement rules are not replaced. The new challenges are built from existing mechanics so player controls, body-following, collision, gravity, rewards and session flow remain consistent.

## Solutions, stars and move budgets

- The private approved route registry is updated for all 25 boards.
- Each par target equals the route length recorded for that board.
- Completion move limits preserve an experimentation buffer above par; the buffer is not used to make the puzzle itself impossible.
- A separate independent rules simulation found matching approved routes for all 25 boards.
- A geometry audit found no overlaps between terrain, collectibles, hazards, crates, switches, gates, starts and portals in the authored board data.

## Rewarded assists

Rewarded hints use a side-view-specific route-aware safety check, including older saved sessions without a `mode` flag. The first safe directional hint was smoke-tested for all 25 level starts, including the legacy Level 1 session fixture.

Rewarded assists remain optional and do not form part of any approved solution. The current provider remains a **development mock**; real video-ad delivery and provider-signed server-side reward verification are not included in this update and are still required before production monetization.

## Verification status

Completed in the editing environment:

- PHP syntax lint: 80 project PHP files passed.
- Independent route search: approved route registry matches the generated solution for 25/25 boards.
- Board geometry audit: 25/25 boards passed the overlap checks.
- Rewarded-hint service smoke test: safe opening hint returned for 25/25 boards; legacy Level 1 fixture returned `right`.

Not run in this editing environment:

- Full Laravel PHPUnit suite: the available PHP runtime lacks `mbstring` and a PDO SQLite driver, so database-backed tests cannot be executed here.
- Vite build: no local `node_modules` is included in the source package. The previous first-world package's Vite build succeeded on the user's Windows machine; run the build again after applying this update.

The five failures reported from the user's previous Windows run have been addressed in the source and their test expectations updated, but the full suite must be rerun on Windows to confirm the final integrated result.

## Apply on the existing Windows project

Back up the project folder and database first. Extract/merge this package over the existing project, preserving `.env`, `vendor`, `node_modules`, `package-lock.json`, and the database. Then run:

```powershell
php artisan migrate
php artisan optimize:clear
php artisan test
npm.cmd run build
```

The new migration is `2026_11_23_000001_expand_side_view_difficulty_worlds_two_to_five.php`. It updates only level definitions for Levels 6–25; it does not modify player progress, wallets, reward claims or achievements.

**Do not run `php artisan migrate:fresh`.**
