# Apple Worm Adventure — Side-view puzzle update

## Before installing

1. Back up the current project folder and MySQL database.
2. Stop the Laravel development server with `Ctrl+C`.
3. Keep the current `.env`, `vendor` and `.git` directories.

## Apply the update

Extract the ZIP into the Laravel project root and replace matching source files. The archive excludes `.env`, `vendor`, `.git`, `node_modules`, generated caches, local sessions and application logs.

No database migration is included. The seeder refreshes the 25 published level definitions through `updateOrCreate`, preserving player progress, wallets, wallet transactions and achievements.

From PowerShell in the project directory run:

```powershell
composer dump-autoload
php artisan optimize:clear
php artisan db:seed --class=DatabaseSeeder
php artisan test
php artisan serve
```

For a new database, run `php artisan migrate --seed` after configuring `.env`. Do **not** run `php artisan migrate:fresh` on a database with player data.

## Verify in the browser

1. Sign in and open the level map.
2. Start Level 01. The new side-view tile-puzzle page should appear instead of the old top-down board or the abandoned jump-run preview.
3. Use arrow keys / WASD, the touch directional pad or swipe to move one tile at a time.
4. Verify apple growth, body-following animation, gravity/support, crate pushing, spikes, portal readiness, pause and restart.
5. Test the early levels and at least one level from every world on a desktop and a mobile-sized viewport.
6. Complete a level and confirm the existing Laravel completion/reward flow records progress once.

## Expected design

The game is a side-view tile puzzle, not a continuous platformer. There is no jump button. The board is deliberately original: tile geometry, level maps, naming, world art and UI are designed for this project rather than copied from a reference game.

The first release contains 25 authored maps across five worlds. Each one has a distinct layout and known solution sequence in the side-view feature tests. Browser play-testing is still needed to tune the difficulty and feel.

## Verification limits

This packaging environment has PHP and Node.js but no Composer executable and no `vendor` directory. PHP/JavaScript syntax checks and independent map replay checks can be run here, but the full PHPUnit suite must be executed locally after dependencies are installed.
