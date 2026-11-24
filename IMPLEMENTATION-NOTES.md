# Apple Worm Adventure — implementation notes

## Active game mode

The 25-level release uses an original side-view, tile-based worm puzzle engine. It is not a run-and-jump platform game and it is not the previous top-down grid-board view. The older top-down controller/view are retained only as a compatibility fallback for level records without a `sideView` definition.

## Core mechanics

- One adjacent tile per directional input.
- Body segments follow the head's previous tile positions.
- Apples extend the worm and count toward the level objective.
- Solid terrain and crates provide support to the connected worm.
- When no segment is supported, the worm falls as a connected body.
- Crates can be pushed one tile and unsupported crates settle downward.
- Spikes are lethal.
- A rune switch opens its configured gate; required switches must be activated before the exit counts.
- The portal only completes a level after every apple and required switch is collected.

## Server/client split

The browser Canvas draws the scene, animates transitions and collects player input. It does not calculate authoritative outcomes. `GameController::puzzleMove` owner-checks the game session, validates the direction, locks the session row and delegates each move to `SideViewPuzzleService`. The server stores the updated state. `complete()` accepts only a server-recorded win with all apples and required switches cleared. The legacy top-down movement endpoint explicitly rejects side-view sessions.

## Level definitions

`SideViewLevelCatalog` contains 25 individually authored 14×8 tile maps. Each definition includes world theme, lesson, starting worm position and facing, terrain, apples, portal, crates, spikes, optional switches/gates and a move target. Several levels deliberately reverse the entry side. Five worlds provide a gradual mechanic progression. The old route metadata is retained inside the seeded config for compatibility with existing tests and legacy code.

## Existing features retained

Authentication, player profiles, level unlocking, progress, wallet ledger, achievements, leaderboard, admin routes and protected progress reset are not replaced by the game renderer. No new database schema is required for this update.

## Current QA status

- PHP syntax checked for source and test files.
- JavaScript syntax checked with `node --check`.
- Map coordinates and overlaps checked for all 25 authored maps.
- All 25 known winning direction sequences replayed against the production `SideViewPuzzleService`; every map reached the server-recorded win state.
- Level 1–5 progression explicitly checked: safe introduction, ground gap, raised shelf, low canopy and first crate bridge.
- The Canvas renderer initialized with a mocked DOM/Canvas runtime and completed a render frame.
- PHPUnit and real browser/mobile QA still need to be run on the developer machine because this package workspace does not include `vendor`, the SQLite PDO driver or a configured database. See `BUILD-VERIFICATION.md`.

## Known remaining work

The map set is a first playable release, not a final difficulty-balanced campaign. Tune the support/fall model through play-testing. More mechanics should be added only when fully supported by server rules, rendering, hints, completion logic and tests. Rewarded ads remain a development mock and the admin interface remains a basic overview/economy view.
