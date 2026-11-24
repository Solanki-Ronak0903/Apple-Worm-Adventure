# Phase 2 — First World Puzzle Redesign and Reward Preview Rules

Date: 22 November 2026

## Goal

Raise the puzzle-design quality without replacing the existing side-view engine. The intended player experience is to think several moves ahead, experiment with the worm's body and the board geometry, and discover a satisfying route. Difficulty must come from puzzle construction, not awkward controls or an artificially restrictive move budget.

This is the first benchmark batch: World 1 (Levels 1–5). Levels 6–25 retain their existing authored boards in this update.

## First-world board changes

| Level | Puzzle | Main planning idea | Shortest verified route |
|---|---|---|---:|
| 1 — First Bite | Stone divider | Find the safe route above a floor-reaching divider | 12 moves |
| 2 — Long Reach | Broken floor and upper shelf | Collect the near apple and stay on the elevated connection until the far descent | 15 moves |
| 3 — Raised Garden | Staggered shelves and tall divider | Plan two different heights and both fruit approaches | 19 moves |
| 4 — Low Passage | Two stone fins and a thorn strip | Avoid the obvious lower crossing and use the protected upper lane | 16 moves |
| 5 — The Long Return | Crate, four-cell shaft, far apple and return route | Grow before pushing the crate, cross the shaft, turn around, collect the Golden Apple and return above the low roof to the portal | 25 moves |

Level 5 starts at (3, 6), has required apples at (5, 6) and (12, 6), a pushable crate at (6, 6), a Golden Apple at (9, 4), and a portal at (0, 6). Its shortest approved route is kept server-side in `SideViewLevelSolutions` and is not sent to the browser.

The current first-world three-star targets are 12, 15, 19, 16 and 25 moves. Completion limits retain a practice buffer; the update does not make a level dependent on an advertisement.

## Migration

New migration: `2026_11_22_000001_redesign_first_five_side_view_puzzles.php`.

It updates the active side-view configuration, visible level title, difficulty, world and map label for existing Level 1–5 rows. It does not delete player progress, rewards, achievements, wallet entries, publication status or game sessions. The rollback is intentionally a no-op because the content may already have been played; restore from a database backup if a deliberate rollback is required.

Run on the existing installation:

```bash
php artisan migrate
php artisan optimize:clear
```

Do not run `php artisan migrate:fresh` on a database containing player progress.

## Optional rewarded assists

The existing hint system remains free: progressive clues lead to a repeatable complete walkthrough. Rewarded previews are optional and progressively introduced:

- Level 1–5: no rewarded-action prompts.
- Level 6 onward: a one-time +5 move assist when three or fewer moves remain, and first-clear double coins after all puzzle objectives have been collected.
- Level 11 onward: one revive after a failed run, restoring the last safe checkpoint. A move-limit revive also grants five continuation moves.

The level unlock rules are enforced by `RewardController`, not just by the browser. Restart does not restore a consumed one-time reward. The reward UI explicitly says this is a development preview and that no real video ad is played. Production continues to fail closed until a real ad provider and its server-verifiable reward callback are integrated. No ad SDK/provider credentials were supplied in this phase.

## Verification performed in the build environment

- PHP syntax checks passed for application, migration and test PHP files.
- `node --check public/js/puzzle-game.js` passed.
- The catalog contains 25 levels and 25 private solution fixtures.
- An independent breadth-first puzzle simulator mirroring the side-view movement, growth, terrain, crate, gravity, hazard and portal rules found valid shortest routes for the redesigned first five and all other 20 existing maps. First-world shortest route lengths are 12, 15, 19, 16 and 25 moves.
- The simulator is a supporting static check, not a substitute for the Laravel feature suite or a human play test.

The complete Laravel PHPUnit suite and Vite build were previously confirmed by the project owner on Windows for the Phase 1 baseline. They have not been rerun against this Phase 2 change in this environment. Run them on Windows after applying the ZIP:

```powershell
php artisan migrate
php artisan optimize:clear
php artisan test
npm.cmd run build
```

Keep the existing `.env`, `vendor`, `node_modules`, database and generated `package-lock.json` when applying the source update. The supplied ZIP omits `vendor` and `node_modules` by design.

## Next batch

Use World 1 as the design benchmark. After the owner play-tests these five redesigned boards, redesign Worlds 2–5 in batches, keeping every approved solution server-side and replayable under the actual Laravel service tests.
