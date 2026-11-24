# Gameplay polish update — 18 November 2026

## Included

- Three-stage authored hints for each of the 25 side-view puzzle levels.
- Hints are fetched from the authenticated, owner-checked game-session endpoint; the full hint-stage list is omitted from the client board payload.
- Hint progression is tracked in the current session state, stops after the third clue, and restarts with the puzzle board.
- Worm movement uses a short head-to-tail follow delay and a rounded connector curve to soften right-angle turns while server positions remain tile-aligned.
- Gravity moves extend the animation duration so falling is not compressed into the normal step duration.
- Apple and golden-apple collection now create expanding rings plus particle bursts.
- Crates animate as a push first, followed by a gravity-settle phase; dust and impact rings mark both departure and landing.
- Rune activation highlights the switch and gate; portal completion gets a separate violet burst.
- D-pad touch targets are larger on mobile, swipe cancellation is handled, and keyboard movement ignores text-entry controls.
- Reduced-motion preferences are respected by existing easing/particle guards and the new animation passes.

## Files changed

- `app/Game/SideViewLevelCatalog.php`
- `app/Services/SideViewHintService.php` (new)
- `app/Http/Controllers/GameController.php`
- `resources/views/puzzle-game.blade.php`
- `public/js/puzzle-game.js`
- `public/css/puzzle-game.css`
- `tests/Feature/SideViewHintTest.php` (new)
- `tests/Feature/SideViewEngineIntegrationTest.php`
- `README.md`

## Verification

- PHP syntax checks run across the PHP source, routes, config, migrations, seeders, and tests.
- JavaScript syntax checked with Node.js.
- All 25 level definitions loaded from `SideViewLevelCatalog` and each contains exactly three hint stages.
- Full Laravel PHPUnit execution is not available in this build container because the ZIP has no `vendor` directory, Composer executable, or PDO SQLite driver. Run `php artisan test` on the Windows development machine.
- A real browser/device play test is still required for subjective animation timing and mobile ergonomics.
