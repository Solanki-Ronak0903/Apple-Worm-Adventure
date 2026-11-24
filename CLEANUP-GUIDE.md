# Project Packaging Cleanup

The active game is the side-view tile-based puzzle engine. The following abandoned continuous platformer files were removed from this project package because the active routes and source no longer reference them:

- `app/Services/Game/PlatformerLevelFactory.php`
- `app/Services/Game/PlatformerSimulation.php`
- `resources/views/game/platformer-level.blade.php`
- `resources/views/game/platformer-prototype.blade.php`
- `public/js/platformer.js`
- `public/css/platformer.css`
- `PLATFORMER-PROTOTYPE.md`

Generated local artifacts such as PHPUnit result cache, compiled Blade files, local sessions and application logs are not included in the distributable ZIP.

The side-view puzzle services, player routes, wallet ledger, reward claims, achievements and current feature tests are retained.
