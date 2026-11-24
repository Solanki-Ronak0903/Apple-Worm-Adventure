# Side-view tile-puzzle redesign

## Approved direction

Apple Worm Adventure is now an original side-view, tile-based logic puzzle. It takes inspiration from classic worm puzzle mechanics without copying specific maps, names, sprites or art. The previous continuous run-and-jump prototype is not the gameplay direction and must not be extended.

## Design principles

- Make the player plan each tile move rather than rely on platforming reflexes.
- Keep the worm visibly segmented; the head follows a grid path and the body follows that exact path.
- Let apples change the puzzle by extending the body.
- Make gaps and ledges meaningful through a clear support/fall rule.
- Introduce pushable crates as puzzle tools in later levels.
- Add a rune switch and closed gate as a later-world objective.
- Use spikes as deliberate route constraints and the portal as the final objective.
- Animate the board and character without changing the underlying one-tile puzzle rules.
- Author original level maps with an intentional teaching objective and a verified solution.

## Current engine rules

1. A directional input requests one adjacent tile.
2. Solid terrain blocks movement.
3. A crate can be pushed one tile if its destination is inside the board and free of terrain, spikes, other crates and the worm.
4. A crate with no solid support below it settles downward until it is supported or reaches the bottom row.
5. Entering an apple removes it, adds score and grows the worm by one segment.
6. The worm remains supported while any body segment has a solid terrain tile or crate directly beneath it.
7. If no segment has support, the full connected worm falls one tile at a time. Falling outside the board or into spikes fails the run.
8. Spikes are lethal.
9. Touching a rune switch consumes it and opens its configured gate.
10. The level is won only when the head reaches the portal and no apples or required switches remain.
11. The Laravel server owns the game state and validates every requested move and completion.

This support model is an original, intentionally compact rule set for the current build. It must be play-tested and tuned before adding more mechanics.

## Architecture

- `app/Game/SideViewLevelCatalog.php`: 25 hand-authored board definitions.
- `app/Services/SideViewPuzzleService.php`: server-authoritative move, support, gravity, crate and completion rules.
- `GameController::puzzleMove`: authenticated, owner-checked, throttled endpoint.
- `resources/views/puzzle-game.blade.php`: gameplay page.
- `public/js/puzzle-game.js`: Canvas renderer, movement interpolation, effects and input controls.
- `public/css/puzzle-game.css`: responsive game presentation.
- The legacy top-down movement endpoint rejects side-view sessions, preventing two different rule sets from mutating the same game.
- `tests/Feature/SideViewPuzzleTest.php`: map, rules, view, switch/gate and known-solution regression coverage.

The legacy top-down move endpoint and view remain as a compatibility fallback for old level records without a `sideView` configuration. New seeded levels open the side-view puzzle page.

## Level design progression

- World 1: movement, growth, basic gaps, low ceilings and first crate.
- World 2: longer gaps, spikes, overhangs, ledges and crate placement.
- World 3: broken paths, block pockets, hazard lines, and the rune-switch gate.
- World 4: raised shelves, narrow safe routes, falls and mixed obstacles.
- World 5: multi-gap routes, crate footholds, elevated fruit and combined challenges.

Do not make new levels by randomly placing obstacles on an otherwise straight route. Each map should have a distinct lesson, a deliberate solution and an appropriate difficulty step.
