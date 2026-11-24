# Side-view platformer gameplay

The side-view 2D platformer is now the default game engine for all 25 published levels. The normal authenticated route `/play/{level}` creates a Laravel `GameSession`, generates that level's deterministic course, stores the course snapshot with the session and renders `game.platformer-level`. The standalone `/prototype/side-view` route remains available for a non-persistent preview.

## Gameplay features

- Five visual world themes: Green Meadow, Enchanted Forest, Ancient Ruins, Crystal Caves and Sky Temple.
- Side-view courses with grass-topped platforms, gaps, different platform elevations, apples, optional golden apples, spikes, checkpoint and exit portal.
- Real-time acceleration, friction, gravity, jumping, coyote time, platform landing and checkpoint respawn.
- Worm made from path-following animated segments, with body breathing, squash/stretch, blink and face details.
- Apple bobbing/tilt, world-specific portal glow, particle bursts, screen shake and a camera that follows the player.
- Keyboard and touch controls, pause/restart, HUD and responsive canvas.

## Server-side completion validation

The browser records one input value per fixed 1/60-second simulation tick. Each value encodes left/right/jump as a bit field from `0` to `7`. On completion the client posts this recording to `POST /game/{session}/platformer-complete`. `PlatformerSimulation` replays the recording against the course snapshot stored in the session. The server requires the replay to reach the portal, collect every required red apple and stop on the exact completion frame. Only then is the trusted session state passed to the existing completion workflow for score, stars, achievements, level unlocks and wallet rewards. Client-reported position, score, lives and collectible counts are not trusted.

Movement is fixed-step at 60 Hz in both JavaScript and PHP. The recording is limited to the course's maximum frame count. The normal grid movement, hint, restart and rewarded-power-up endpoints reject side-view sessions where they are not supported.

## Run locally

1. Preserve the existing `.env` and database.
2. Extract the updated source into the project.
3. Run `php artisan optimize:clear`.
4. Start `php artisan serve`.
5. Open the level map, then start Level 01.

No database migration is included in this update. Existing player progress, wallet balances and transaction records remain in MySQL.

## QA status

PHP syntax and JavaScript syntax checks pass. All 25 deterministic courses were generated and completed in a test-driver input simulation that was replayed by the PHP server simulation. The project archive does not include `vendor`, so PHPUnit could not be run in this build environment. Full manual browser testing of controls, responsive layout, all level transitions and real user play remains required before production release.
