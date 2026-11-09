# Apple Worm Adventure — Laravel Edition

A Laravel 12 + MySQL + Blade + vanilla JavaScript implementation starter for the VELOOP Rewards internship task. This is an original prototype; it does not copy the reference game's branding or artwork.

## What's included

- Responsive landing page, login/registration, player dashboard, level selector, game screen, wallet history, leaderboard, and admin overview.
- Laravel session authentication, password hashing, request validation, CSRF protection, admin middleware.
- Migrations for users, levels, sessions, progress, wallets, wallet transactions, reward claims, achievements, and user achievements.
- Seeder that publishes 25 launch levels with safe, repeatable route layouts and demo achievements. Future level records are kept unpublished until verified.
- Grid-based JavaScript game with keyboard and touch buttons, apples, obstacles, portal, move limit, score, restart, sound toggle, and hints.
- Reward actions for revive, double score, double coins, extra moves, hint, and booster are represented in the UI/backend. **The current ad provider is a development mock, not a real ad integration.**
- A demo admin account is seeded: `admin@appleworm.local` / `ChangeMe123!`. Change/remove it immediately and never deploy the demo credentials.

## Requirements

- PHP 8.2+ and Composer 2
- MySQL 8+ (or compatible MariaDB)
- Node.js 20+ and npm (only needed if you use Vite; the core CSS and game JS are served from `public/`)

Docker is not required.

## Windows setup (PowerShell)

1. Extract this ZIP to a folder, for example `C:\Users\Dell\Desktop\apple-worm-adventure-laravel`.
2. Open PowerShell in that folder.
3. Install PHP dependencies:

   ```powershell
   composer install
   ```

4. Create the environment file and key:

   ```powershell
   Copy-Item .env.example .env
   php artisan key:generate
   ```

5. In MySQL, create a database named `apple_worm_adventure`, or use phpMyAdmin:

   ```sql
   CREATE DATABASE apple_worm_adventure CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

6. Update `DB_USERNAME` and `DB_PASSWORD` in `.env` for your local MySQL. Ensure `DB_DATABASE=apple_worm_adventure`.
7. Run migrations and seed the 25 launch levels:

   ```powershell
   php artisan migrate --seed
   ```

8. Start the app:

   ```powershell
   php artisan serve
   ```

9. Open `http://127.0.0.1:8000`.

If PowerShell blocks `npm.ps1`, use `npm.cmd` rather than changing your execution policy. npm is not required for the game's core assets, because those are already in `public/css` and `public/js`.

## First-run checks

- Register a player account; it receives 100 demo coins.
- Open Levels and start level 1.
- Use arrow keys/WASD or on-screen directional controls.
- Collect apples and reach the portal.
- Check Wallet and Leaderboard after a level completion.
- Demo admin: log in with the credentials above and open `/admin`.

## API-like web endpoints

The prototype uses authenticated, CSRF-protected Laravel web routes returning JSON for game actions:

- `POST /game/{session}/complete`
- `POST /game/{session}/fail`
- `POST /game/{session}/hint`
- `POST /game/{session}/reward`

These are session-authenticated web endpoints, not a public stateless API. They are enough for this Blade prototype; if a separate mobile client is required, add API routes and Laravel Sanctum.

## Data model

- `users`: account and admin flag
- `levels`: data-driven level config (JSON)
- `game_sessions`: active/failed/completed sessions and counters
- `user_level_progress`: unlock state, best score, stars and best moves
- `wallets`: coin balance
- `wallet_transactions`: auditable wallet ledger
- `reward_claims`: idempotency token, provider, action and claim status
- `achievements`, `user_achievements`: achievement definitions and user unlocks

## Reward economy and safety

- Reward actions: revive, double score, double coins, extra moves, hint, booster.
- Claims require an authenticated user, owned session, valid action, and UUID claim token. Unique claim tokens and transaction boundaries reduce accidental duplicate grants.
- Wallet balance and wallet ledger are separate. Add stronger idempotency, action-specific caps, audit events and server-verified provider callbacks before public release.
- **The `development_mock` provider simulates completion. It is not evidence of a real ad view.** Never use mock rewards as real-money value.
- The current prototype validates each submitted movement against the server-owned board state, but the full economy and reward flow still needs security review before production. Implement authoritative server-side game simulation before production or final high-stakes evaluation.
- Current `double_score` is listed as an action but score doubling is not yet implemented as a post-completion claim flow. Implement this and action-specific caps before declaring all economy requirements production-complete.

## Initial release scope

The first launch is intentionally limited to **25 levels**. Existing level records above 25 are retained in the database but unpublished, so their records and any historical progress are not deleted. The level limit is configured in `config/game.php`; increase it only when the next batch has been designed, tested and approved.

## Important known limitations

This is a generated starter/prototype and must be tested on your machine before submission. The first release contains 25 data-driven layouts with a deterministic clear route. They still require browser-based playability and difficulty testing before submission. The current gameplay checks a basic grid route, but does not yet implement key/gate mechanics, a true worm-body growth mechanic, golden-apple scoring, route-solver hints, sound/music assets, full achievement progression, or a real rewarded-ad SDK. Golden apples are intentionally excluded from the first 25 levels until server-side collection and scoring are implemented. The game UI does grow the worm on apple collection, but the seeded layout needs QA. Double score and double coins are exposed as action names; only a basic mock double-coins grant is currently wired. Do not claim these limitations are complete features.

## Testing and quality checklist

```powershell
php artisan test
php artisan route:list
php artisan migrate:status
```

Manual QA checklist:
- Register, log in, log out, and try protected routes as a guest.
- Complete and fail levels; verify the level state and next unlock.
- Test duplicate reward claim tokens, wrong session ownership, and invalid reward types.
- Verify wallet balance equals the ledger balance after all transactions.
- Test mobile widths and touch controls.
- Replace mock rewarded ads with a provider SDK and server-side verification before production.
- Change seeded admin password and set `APP_DEBUG=false` in production.

## Deployment

Deploy the Laravel app to a PHP-capable host with MySQL (for example a managed PHP host or Render with a persistent database). Set environment variables securely, run `php artisan migrate --force`, seed only if appropriate, configure HTTPS, mail, logging, backups, and `APP_DEBUG=false`. Do not commit `.env` or secrets. If deploying to Vercel/Netlify, note they are not a natural fit for a traditional persistent Laravel PHP backend; use a PHP-capable backend host and point the frontend/domain to it.

## Internship demo checklist

Record: landing page, registration/login, dashboard, levels 1–25, gameplay, apple collection, obstacles, portal, success/failure, wallet, each demo reward, reward history, leaderboard, admin reward claims, database persistence, and mobile layout. Explicitly tell reviewers the ad flow is simulated until a real provider is integrated.
