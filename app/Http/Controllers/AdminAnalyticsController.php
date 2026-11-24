<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
use App\Models\GameSession;
use App\Models\Level;
use App\Models\RewardClaim;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminAnalyticsController extends Controller
{
    /**
     * Analytics are based only on persisted player activity. Admin accounts are
     * excluded so internal testing does not distort player-facing metrics.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'period' => ['nullable', 'in:7,30,90'],
        ]);

        $period = (int) ($validated['period'] ?? 30);
        $end = now();
        $start = $end->copy()->subDays($period - 1)->startOfDay();

        $sessions = GameSession::query()
            ->whereBetween('created_at', [$start, $end])
            ->whereHas('user', fn (Builder $query) => $query->where('is_admin', false));

        $totalSessions = (clone $sessions)->count();
        $completedSessions = (clone $sessions)->where('status', 'completed')->count();
        $failedSessions = (clone $sessions)->where('status', 'failed')->count();
        $activeSessions = (clone $sessions)->where('status', 'active')->count();
        $activePlayers = (clone $sessions)->distinct()->count('user_id');

        $returningPlayers = (clone $sessions)
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) >= 2')
            ->get()
            ->count();

        $newPlayers = User::query()
            ->where('is_admin', false)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $averageMoves = (clone $sessions)
            ->where('status', 'completed')
            ->where('moves_used', '>', 0)
            ->avg('moves_used');

        $usageTotals = (clone $sessions)->selectRaw(
            'COALESCE(SUM(hints_used), 0) as hints, COALESCE(SUM(revives_used), 0) as revives, COALESCE(SUM(boosters_used), 0) as boosters'
        )->first();

        $dailyRows = (clone $sessions)
            ->selectRaw('DATE(created_at) as activity_date')
            ->selectRaw('COUNT(*) as sessions_count')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count")
            ->selectRaw('COUNT(DISTINCT user_id) as active_players_count')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('activity_date')
            ->get()
            ->keyBy('activity_date');

        $dailyActivity = collect(range($period - 1, 0))->map(function (int $daysAgo) use ($dailyRows, $period): array {
            $date = now()->subDays($daysAgo)->toDateString();
            $row = $dailyRows->get($date);

            return [
                'date' => $date,
                'label' => Carbon::parse($date)->format($period === 7 ? 'D' : 'd M'),
                'show_label' => $period === 7 || $daysAgo === 0 || $daysAgo % ($period === 30 ? 5 : 15) === 0,
                'sessions' => (int) ($row->sessions_count ?? 0),
                'completed' => (int) ($row->completed_count ?? 0),
                'failed' => (int) ($row->failed_count ?? 0),
                'players' => (int) ($row->active_players_count ?? 0),
            ];
        })->values();

        $maxDailySessions = max(1, (int) $dailyActivity->max('sessions'));

        $sessionStatsByLevel = (clone $sessions)
            ->select('level_id')
            ->selectRaw('COUNT(*) as sessions_count')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count")
            ->selectRaw("AVG(CASE WHEN status = 'completed' AND moves_used > 0 THEN moves_used ELSE NULL END) as average_moves")
            ->groupBy('level_id')
            ->get()
            ->keyBy('level_id');

        $levelPerformance = Level::query()
            ->where('is_published', true)
            ->orderBy('number')
            ->get()
            ->map(function (Level $level) use ($sessionStatsByLevel): array {
                $stats = $sessionStatsByLevel->get($level->id);
                $attempts = (int) ($stats->sessions_count ?? 0);
                $completed = (int) ($stats->completed_count ?? 0);
                $failed = (int) ($stats->failed_count ?? 0);
                $world = (array) data_get($level->config, 'sideView.world', []);

                return [
                    'id' => $level->id,
                    'number' => $level->number,
                    'title' => $level->title,
                    'difficulty' => $level->difficulty,
                    'world' => $world['name'] ?? 'Unassigned world',
                    'sessions' => $attempts,
                    'completed' => $completed,
                    'failed' => $failed,
                    'completion_rate' => $attempts > 0 ? round(($completed / $attempts) * 100, 1) : 0,
                    'average_moves' => $stats?->average_moves !== null ? round((float) $stats->average_moves, 1) : null,
                ];
            });

        $topLevels = $levelPerformance
            ->filter(fn (array $level) => $level['sessions'] > 0)
            ->sortByDesc('sessions')
            ->take(5)
            ->values();

        $levelsToReview = $levelPerformance
            ->filter(fn (array $level) => $level['sessions'] >= 3)
            ->sortBy('completion_rate')
            ->take(5)
            ->values();

        $worldPerformance = $levelPerformance
            ->groupBy('world')
            ->map(function ($levels, string $world): array {
                $attempts = (int) $levels->sum('sessions');
                $completed = (int) $levels->sum('completed');

                return [
                    'world' => $world,
                    'levels' => $levels->count(),
                    'sessions' => $attempts,
                    'completed' => $completed,
                    'completion_rate' => $attempts > 0 ? round(($completed / $attempts) * 100, 1) : 0,
                ];
            })
            ->sortBy('world')
            ->values();

        $topPlayerRows = (clone $sessions)
            ->select('user_id')
            ->selectRaw('COUNT(*) as sessions_count')
            ->groupBy('user_id')
            ->orderByDesc('sessions_count')
            ->take(5)
            ->get();
        $topPlayerNames = User::query()
            ->whereIn('id', $topPlayerRows->pluck('user_id'))
            ->pluck('name', 'id');
        $topPlayers = $topPlayerRows->map(fn ($row) => [
            'name' => $topPlayerNames[$row->user_id] ?? 'Deleted player',
            'sessions' => (int) $row->sessions_count,
        ]);

        $claims = RewardClaim::query()
            ->whereBetween('created_at', [$start, $end])
            ->whereHas('user', fn (Builder $query) => $query->where('is_admin', false));
        $claimCounts = (clone $claims)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $claimActions = (clone $claims)
            ->selectRaw('action, COUNT(*) as total')
            ->groupBy('action')
            ->orderByDesc('total')
            ->get();

        $transactions = WalletTransaction::query()
            ->whereBetween('created_at', [$start, $end])
            ->whereHas('user', fn (Builder $query) => $query->where('is_admin', false));
        $coinsCredited = (int) (clone $transactions)->where('amount', '>', 0)->sum('amount');
        $coinsDebited = abs((int) (clone $transactions)->where('amount', '<', 0)->sum('amount'));
        $transactionTypes = (clone $transactions)
            ->selectRaw('type, COUNT(*) as entries, SUM(amount) as amount_total')
            ->groupBy('type')
            ->orderByDesc('entries')
            ->get();

        return view('admin.analytics.index', [
            'period' => $period,
            'startDate' => $start,
            'endDate' => $end,
            'metrics' => [
                'sessions' => $totalSessions,
                'completed' => $completedSessions,
                'failed' => $failedSessions,
                'active_sessions' => $activeSessions,
                'active_players' => $activePlayers,
                'returning_players' => $returningPlayers,
                'new_players' => $newPlayers,
                'completion_rate' => $totalSessions > 0 ? round(($completedSessions / $totalSessions) * 100, 1) : 0,
                'failure_rate' => $totalSessions > 0 ? round(($failedSessions / $totalSessions) * 100, 1) : 0,
                'average_moves' => $averageMoves !== null ? round((float) $averageMoves, 1) : null,
                'hints_used' => (int) ($usageTotals->hints ?? 0),
                'revives_used' => (int) ($usageTotals->revives ?? 0),
                'boosters_used' => (int) ($usageTotals->boosters ?? 0),
                'granted_claims' => (int) ($claimCounts['granted'] ?? 0),
                'pending_claims' => (int) ($claimCounts['pending'] ?? 0),
                'other_claims' => (int) $claimCounts->except(['granted', 'pending'])->sum(),
                'coins_credited' => $coinsCredited,
                'coins_debited' => $coinsDebited,
            ],
            'dailyActivity' => $dailyActivity,
            'maxDailySessions' => $maxDailySessions,
            'levelPerformance' => $levelPerformance,
            'topLevels' => $topLevels,
            'levelsToReview' => $levelsToReview,
            'worldPerformance' => $worldPerformance,
            'topPlayers' => $topPlayers,
            'claimActions' => $claimActions,
            'transactionTypes' => $transactionTypes,
            'totalAchievements' => Achievement::count(),
            'totalUnlocks' => UserAchievement::whereNotNull('unlocked_at')->count(),
        ]);
    }
}
