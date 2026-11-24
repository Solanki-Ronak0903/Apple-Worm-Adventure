# Game Analytics – 19 November 2026

## Included

- Admin-only analytics workspace at `GET /admin/analytics`.
- Report windows for the last 7, 30, or 90 days.
- Daily session trend with completed and failed attempt segments.
- Core indicators: sessions, unique active players, new players, returning players (two or more sessions in the selected window), completion and failure rates, and average moves per completed session.
- Session assistance usage: hints, revives, and boosters.
- Level performance for every published level, including attempts, completions, failures, completion rate, and average moves.
- Most-played levels and a QA review signal for levels with at least three attempts, ordered by lower completion rate.
- World-level performance summaries.
- Most active players in the selected window.
- Reward claim status and action breakdown.
- Signed wallet ledger totals and transaction-type breakdown.
- Admin accounts are excluded from player activity, reward claim, and wallet metrics.
- Responsive charts and tables using the existing admin design system; no charting dependency or database migration is required.

## Metric definitions

- Session metrics are filtered using `game_sessions.created_at`.
- Completion rate = completed sessions / all sessions in the selected window.
- Failure rate = failed sessions / all sessions in the selected window.
- Active players = distinct non-admin users with a session in the selected window.
- Returning players = users with at least two sessions in the selected window. This is not cohort retention.
- Average moves uses completed sessions with a positive `moves_used` value.
- Coins credited/debited are calculated from positive/negative `wallet_transactions.amount` entries respectively.
- “Levels to review” is an attention signal, not an automatic claim that a level is too difficult. It requires at least three attempts.
- Achievement unlock and definition counts displayed in the report footer are all-time totals.

## Route and access

- `GET /admin/analytics` – `admin.analytics`
- Route is inside the existing `auth`, `admin`, and admin throttle middleware group.

## Data and compatibility

- Uses existing `game_sessions`, `users`, `levels`, `reward_claims`, `wallet_transactions`, and achievement tables.
- No migration or seeding is required.
- No changes are made to gameplay, level configuration, player progress, wallets, rewards, or achievement awarding logic.

## Tests added

`tests/Feature/AdminAnalyticsTest.php` covers:

- Admin-only access.
- Session, completion, failure, active-player, returning-player, and average-move aggregation.
- Exclusion of administrator activity.
- 7/30-day filter behaviour and invalid period validation.
- Reward claim and signed wallet ledger breakdown.

Run `php artisan test` on the Windows development machine after installing the archive. PHPUnit is not available in the build workspace because Composer dependencies are not installed there.
