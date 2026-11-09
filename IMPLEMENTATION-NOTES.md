# Apple Worm Adventure — Gameplay Engine Update

## Updated files
- `app/Http/Controllers/GameController.php`
- `app/Http/Controllers/RewardController.php`
- `public/js/game.js`
- `routes/web.php`

## What changed
- Game sessions are initialized with server-owned worm, apple, score, move, reward and checkpoint state.
- `POST /game/{session}/move` validates each direction, bounds, obstacles, worm collision and move limits on the server.
- The server records apple collection, worm growth, score and portal completion.
- `GET /game/{session}/state` exposes the authenticated owner's current state to the game UI.
- `POST /game/{session}/restart` resets an unfinished session on the server.
- Completion no longer accepts score, apple count, move count or win status from the browser. It requires the server's `won` state.
- Reward claims are owner-checked, UUID-token protected, limited per action/session, and applied to server state.
- Revive restores the last valid checkpoint. Extra moves are enforced by the movement endpoint. Double coins doubles the completion reward. Booster doubles the next apple's points.
- Browser JavaScript now sends directions and renders server-returned state.

## Important limitations
- Rewarded ads are still simulated (`development_mock`). Do not describe them as real ad integrations. A production ad provider must verify callbacks/signatures server-side.
- The hint currently gives a simple direction toward the next target; it is not a full pathfinding solver and does not guarantee an obstacle-free route.
- The existing 25 generated launch levels still need individual playability/solvability QA.
- The game engine uses the level config format already present in the project; no database migration is required for this update.

## Verification performed
- PHP syntax checks passed for both updated controllers and `routes/web.php`.
- JavaScript syntax check passed with `node --check public/js/game.js`.
- Full Laravel integration tests could not be run in this packaging environment because the ZIP intentionally excludes `vendor/` and no installed dependency tree is included. Run the local commands below after applying the patch.

## Local verification
```powershell
php artisan optimize:clear
php artisan route:list --path=game
php artisan test
```

Expected game routes include `move`, `restart`, `state`, `complete`, `fail`, `hint`, and `reward`.


## Initial release scope update (2026-11-02)
- The first public release is capped at 25 published levels.
- Existing records above Level 25 are unpublished, not deleted.
- The first 25 levels use a deterministic route with apples on the route and obstacles outside it; browser playability and difficulty still require manual review.
- Golden apples are intentionally withheld until server-side collection and scoring are implemented.
- Victory/failure UI now provides score, moves, apples and coin results, a next-level action, replay via a fresh session, retry and eligible mock revive.
- Apple collection adds a small visual pickup effect; game state, score and growth remain server-authoritative.
