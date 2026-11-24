<?php

namespace App\Http\Controllers;

use App\Models\GameSession;
use App\Models\Level;
use App\Models\RewardClaim;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\AdminAuditLogger;
use App\Services\SideViewLevelValidator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function index()
    {
        $playersQuery = User::query()->where('is_admin', false);
        $players = (clone $playersQuery)->count();
        $newPlayers = (clone $playersQuery)->where('created_at', '>=', now()->subDays(7))->count();
        $activePlayers = GameSession::query()
            ->where('created_at', '>=', now()->subDays(7))
            ->whereHas('user', fn (Builder $query) => $query->where('is_admin', false))
            ->distinct('user_id')
            ->count('user_id');

        $playerSessions = GameSession::query()->whereHas('user', fn (Builder $query) => $query->where('is_admin', false));
        $totalSessions = (clone $playerSessions)->count();
        $completedSessions = (clone $playerSessions)->where('status', 'completed')->count();

        $weeklyCounts = GameSession::query()
            ->selectRaw('DATE(created_at) as activity_date, COUNT(*) as sessions_count')
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->whereHas('user', fn (Builder $query) => $query->where('is_admin', false))
            ->groupByRaw('DATE(created_at)')
            ->pluck('sessions_count', 'activity_date');

        $weeklyActivity = collect(range(6, 0))->map(function (int $daysAgo) use ($weeklyCounts) {
            $date = now()->subDays($daysAgo)->toDateString();

            return [
                'label' => now()->subDays($daysAgo)->format('D'),
                'date' => $date,
                'count' => (int) ($weeklyCounts[$date] ?? 0),
            ];
        });

        $topLevels = Level::query()
            ->where('is_published', true)
            ->withCount(['progress as completed_players_count' => fn (Builder $query) => $query->where('completed', true)->whereHas('user', fn (Builder $userQuery) => $userQuery->where('is_admin', false))])
            ->orderByDesc('completed_players_count')
            ->orderBy('number')
            ->take(5)
            ->get();

        return view('admin.dashboard', [
            'players' => $players,
            'newPlayers' => $newPlayers,
            'activePlayers' => $activePlayers,
            'publishedLevels' => Level::where('is_published', true)->count(),
            'totalSessions' => $totalSessions,
            'completedSessions' => $completedSessions,
            'completionRate' => $totalSessions > 0 ? round(($completedSessions / $totalSessions) * 100) : 0,
            'totalClaims' => RewardClaim::whereHas('user', fn (Builder $query) => $query->where('is_admin', false))->count(),
            'pendingClaims' => RewardClaim::where('status', 'pending')->whereHas('user', fn (Builder $query) => $query->where('is_admin', false))->count(),
            'coinsInCirculation' => (int) \App\Models\Wallet::whereHas('user', fn (Builder $query) => $query->where('is_admin', false))->sum('coins'),
            'transactionCount' => WalletTransaction::whereHas('user', fn (Builder $query) => $query->where('is_admin', false))->count(),
            'weeklyActivity' => $weeklyActivity,
            'topLevels' => $topLevels,
            'recentSessions' => GameSession::with(['user', 'level'])->whereHas('user', fn (Builder $query) => $query->where('is_admin', false))->latest()->take(8)->get(),
            'recentClaims' => RewardClaim::with('user')->whereHas('user', fn (Builder $query) => $query->where('is_admin', false))->latest()->take(6)->get(),
        ]);
    }

    public function players(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'in:all,player,admin'],
            'sort' => ['nullable', 'in:newest,oldest,most_active'],
        ]);

        $search = trim($validated['q'] ?? '');
        $role = $validated['role'] ?? 'player';
        $sort = $validated['sort'] ?? 'newest';

        $query = User::query()
            ->with('wallet')
            ->withCount('gameSessions')
            ->withCount(['progress as completed_levels_count' => fn (Builder $builder) => $builder->where('completed', true)])
            ->withCount(['achievements as unlocked_achievements_count' => fn (Builder $builder) => $builder->whereNotNull('unlocked_at')]);

        if ($role === 'player') {
            $query->where('is_admin', false);
        } elseif ($role === 'admin') {
            $query->where('is_admin', true);
        }

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");

                if (ctype_digit($search)) {
                    $builder->orWhereKey((int) $search);
                }
            });
        }

        match ($sort) {
            'oldest' => $query->orderBy('created_at'),
            'most_active' => $query->orderByDesc('game_sessions_count')->orderByDesc('created_at'),
            default => $query->latest(),
        };

        return view('admin.players.index', [
            'players' => $query->paginate(15)->withQueryString(),
            'search' => $search,
            'role' => $role,
            'sort' => $sort,
            'totalPlayers' => User::where('is_admin', false)->count(),
            'adminsCount' => User::where('is_admin', true)->count(),
        ]);
    }

    public function showPlayer(User $user)
    {
        $user->load([
            'wallet',
            'progress.level',
            'gameSessions' => fn ($query) => $query->with('level')->latest()->take(10),
            'transactions' => fn ($query) => $query->latest()->take(10),
            'achievements.achievement',
        ]);

        return view('admin.players.show', [
            'player' => $user,
            'completedLevels' => $user->progress->where('completed', true)->count(),
            'totalStars' => (int) $user->progress->sum('stars'),
            'unlockedAchievements' => $user->achievements->whereNotNull('unlocked_at')->count(),
            'sessionsPlayed' => $user->gameSessions()->count(),
        ]);
    }


    public function levels(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'world' => ['nullable', 'in:all,meadow,fernwood,ruins,crystal,cloud'],
            'difficulty' => ['nullable', 'in:all,tutorial,easy,medium,hard,advanced'],
            'status' => ['nullable', 'in:all,published,draft'],
            'sort' => ['nullable', 'in:number_asc,number_desc,most_played,most_completed'],
        ]);

        $search = trim($validated['q'] ?? '');
        $world = $validated['world'] ?? 'all';
        $difficulty = $validated['difficulty'] ?? 'all';
        $status = $validated['status'] ?? 'all';
        $sort = $validated['sort'] ?? 'number_asc';

        $query = Level::query()
            ->withCount('gameSessions')
            ->withCount(['progress as completed_players_count' => fn (Builder $builder) => $builder->where('completed', true)]);

        if ($world !== 'all') {
            $query->where('config->sideView->world->code', $world);
        }
        if ($difficulty !== 'all') {
            $query->where('difficulty', $difficulty);
        }
        if ($status === 'published') {
            $query->where('is_published', true);
        } elseif ($status === 'draft') {
            $query->where('is_published', false);
        }
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('title', 'like', "%{$search}%");
                if (ctype_digit($search)) {
                    $builder->orWhere('number', (int) $search);
                }
            });
        }

        match ($sort) {
            'number_desc' => $query->orderByDesc('number'),
            'most_played' => $query->orderByDesc('game_sessions_count')->orderBy('number'),
            'most_completed' => $query->orderByDesc('completed_players_count')->orderBy('number'),
            default => $query->orderBy('number'),
        };

        $worlds = [
            'meadow' => 'Sunlit Meadow',
            'fernwood' => 'Fernwood Trail',
            'ruins' => 'Mossy Ruins',
            'crystal' => 'Crystal Hollow',
            'cloud' => 'Sky Temple',
        ];

        return view('admin.levels.index', [
            'levels' => $query->paginate(15)->withQueryString(),
            'search' => $search,
            'world' => $world,
            'difficulty' => $difficulty,
            'status' => $status,
            'sort' => $sort,
            'worlds' => $worlds,
            'totalLevels' => Level::count(),
            'publishedLevels' => Level::where('is_published', true)->count(),
            'draftLevels' => Level::where('is_published', false)->count(),
            'totalSessions' => GameSession::count(),
        ]);
    }

    public function showLevel(Level $level)
    {
        $level = Level::query()
            ->withCount('gameSessions')
            ->withCount(['progress as completed_players_count' => fn (Builder $builder) => $builder->where('completed', true)])
            ->findOrFail($level->id);

        $layout = $level->config['sideView'] ?? null;
        $previewRows = is_array($layout) ? $this->buildLevelPreview($layout) : [];

        return view('admin.levels.show', [
            'level' => $level,
            'layout' => $layout,
            'previewRows' => $previewRows,
            'hintStages' => array_values($layout['hintStages'] ?? [$layout['hint'] ?? '', '', '']),
            'worldName' => $layout['world']['name'] ?? 'Legacy / unassigned',
        ]);
    }

    public function updateLevel(Request $request, Level $level, AdminAuditLogger $audit)
    {
        abort_unless(is_array($level->config['sideView'] ?? null), 404, 'Only side-view puzzle levels can be edited here.');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:80'],
            'difficulty' => ['required', 'in:tutorial,easy,medium,hard,advanced'],
            'lesson' => ['required', 'string', 'max:180'],
            'hint_1' => ['required', 'string', 'max:240'],
            'hint_2' => ['required', 'string', 'max:240'],
            'hint_3' => ['required', 'string', 'max:240'],
        ]);

        $currentLayout = $level->config['sideView'] ?? [];
        $before = [
            'title' => $level->title,
            'difficulty' => $level->difficulty,
            'lesson' => $currentLayout['lesson'] ?? null,
            'hints' => array_values($currentLayout['hintStages'] ?? [$currentLayout['hint'] ?? '', '', '']),
        ];

        $config = $level->config ?? [];
        $config['title'] = $validated['title'];
        $config['sideView']['title'] = $validated['title'];
        $config['sideView']['lesson'] = $validated['lesson'];
        $config['sideView']['hint'] = $validated['hint_1'];
        $config['sideView']['hintStages'] = [
            $validated['hint_1'],
            $validated['hint_2'],
            $validated['hint_3'],
        ];

        DB::transaction(function () use ($level, $validated, $config, $audit, $request, $before): void {
            $level->update([
                'title' => $validated['title'],
                'difficulty' => $validated['difficulty'],
                'config' => $config,
            ]);

            $audit->record($request, 'level.metadata_updated', 'Updated metadata for level ' . $level->number . '.', $level, $before, [
                'title' => $validated['title'],
                'difficulty' => $validated['difficulty'],
                'lesson' => $validated['lesson'],
                'hints' => [$validated['hint_1'], $validated['hint_2'], $validated['hint_3']],
            ]);
        });

        return redirect()->route('admin.levels.show', $level)
            ->with('status', 'Level details saved. Puzzle geometry and its verified route were left unchanged.');
    }

    public function validateLevel(Level $level, SideViewLevelValidator $validator)
    {
        $result = $validator->validate($level, (int) auth()->id());

        if (! $result['valid']) {
            return redirect()->route('admin.levels.show', $level)->withErrors($result['message']);
        }

        $message = $result['message'] . ' Verified in ' . $result['moves'] . ' moves.';

        return redirect()->route('admin.levels.show', $level)->with('status', $message);
    }

    public function updateLevelPublication(Request $request, Level $level, SideViewLevelValidator $validator, AdminAuditLogger $audit)
    {
        if ($level->is_published) {
            if ((int) $level->number === 1) {
                return back()->withErrors('Level 01 must remain published as the starting point of the adventure.');
            }
            if (Level::where('number', '>', $level->number)->where('is_published', true)->exists()) {
                return back()->withErrors('Unpublish higher-numbered levels first so the player progression remains continuous.');
            }
            if ($level->gameSessions()->where('status', 'active')->exists()) {
                return back()->withErrors('This level has active player sessions. Wait until those sessions finish before unpublishing it.');
            }

            DB::transaction(function () use ($level, $audit, $request): void {
                $level->update(['is_published' => false]);
                $audit->record($request, 'level.unpublished', 'Unpublished level ' . $level->number . '.', $level, ['is_published' => true], ['is_published' => false]);
            });

            return back()->with('status', 'Level unpublished. Existing player progress and historical sessions were preserved.');
        }

        if (Level::where('number', '<', $level->number)->where('is_published', false)->exists()) {
            return back()->withErrors('Publish the earlier levels first. The public campaign must remain in sequential order.');
        }

        $result = $validator->validate($level, (int) auth()->id());
        if (! $result['valid']) {
            return back()->withErrors('This level cannot be published: ' . $result['message']);
        }

        DB::transaction(function () use ($level, $audit, $request, $result): void {
            $level->update(['is_published' => true]);
            $audit->record($request, 'level.published', 'Published level ' . $level->number . ' after server-side solution replay.', $level, ['is_published' => false], ['is_published' => true, 'verified_moves' => $result['moves']]);
        });

        return back()->with('status', 'Level published after a successful server-side solution replay (' . $result['moves'] . ' moves).');
    }

    private function buildLevelPreview(array $layout): array
    {
        $width = max(1, min(30, (int) ($layout['width'] ?? 14)));
        $height = max(1, min(20, (int) ($layout['height'] ?? 8)));
        $markers = [];
        $add = function (array $points, string $type) use (&$markers): void {
            foreach ($points as $point) {
                if (isset($point['x'], $point['y'])) {
                    $markers[(int) $point['x'] . ':' . (int) $point['y']] = $type;
                }
            }
        };

        $add($layout['terrain'] ?? [], 'terrain');
        $add($layout['gates'] ?? [], 'gate');
        $add($layout['blocks'] ?? [], 'crate');
        $add($layout['spikes'] ?? [], 'spike');
        $add($layout['apples'] ?? [], 'apple');
        $add($layout['goldenApples'] ?? [], 'golden');
        $add($layout['switches'] ?? [], 'switch');

        $start = $layout['start'] ?? ['x' => 2, 'y' => 6, 'length' => 3, 'facing' => 'right'];
        [$dx, $dy] = match ($start['facing'] ?? 'right') {
            'left' => [1, 0], 'up' => [0, 1], 'down' => [0, -1], default => [-1, 0],
        };
        for ($segment = 0; $segment < max(2, min(6, (int) ($start['length'] ?? 3))); $segment++) {
            $x = (int) $start['x'] + ($dx * $segment);
            $y = (int) $start['y'] + ($dy * $segment);
            $markers[$x . ':' . $y] = $segment === 0 ? 'worm-head' : 'worm-body';
        }

        $portal = $layout['portal'] ?? null;
        if (is_array($portal) && isset($portal['x'], $portal['y'])) {
            $markers[(int) $portal['x'] . ':' . (int) $portal['y']] = 'portal';
        }

        $rows = [];
        for ($y = 0; $y < $height; $y++) {
            $row = [];
            for ($x = 0; $x < $width; $x++) {
                $row[] = $markers[$x . ':' . $y] ?? 'empty';
            }
            $rows[] = $row;
        }

        return $rows;
    }

    public function economy(Request $request)
    {
        $claims = RewardClaim::with('user')->latest()->paginate(30)->withQueryString();

        return view('admin.economy', compact('claims'));
    }
}
