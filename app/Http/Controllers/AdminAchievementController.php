<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
use App\Services\AdminAuditLogger;
use App\Models\UserAchievement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class AdminAchievementController extends Controller
{
    private const METRICS = [
        'apples' => 'Apples collected',
        'levels' => 'Levels completed',
        'stars' => 'Stars earned',
        'rewards' => 'Rewarded actions used',
        'golden_apples' => 'Golden apples earned',
    ];

    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:all,active,inactive'],
            'metric' => ['nullable', Rule::in(array_merge(['all'], array_keys(self::METRICS)))],
            'sort' => ['nullable', 'in:name,most_unlocked,least_unlocked,newest'],
        ]);

        $search = trim($validated['q'] ?? '');
        $status = $validated['status'] ?? 'all';
        $metric = $validated['metric'] ?? 'all';
        $sort = $validated['sort'] ?? 'name';

        $query = Achievement::query()
            ->withCount([
                'userAchievements as unlocks_count' => fn (Builder $builder) => $builder->whereNotNull('unlocked_at'),
                'userAchievements as tracked_players_count',
            ]);

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if ($metric !== 'all') {
            // JSON key lookup is supported by both SQLite (tests) and MySQL (local deployment).
            $query->whereNotNull("criteria->{$metric}");
        }

        if ($search !== '') {
            $query->where(fn (Builder $builder) => $builder
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%"));
        }

        match ($sort) {
            'most_unlocked' => $query->orderByDesc('unlocks_count')->orderBy('name'),
            'least_unlocked' => $query->orderBy('unlocks_count')->orderBy('name'),
            'newest' => $query->latest(),
            default => $query->orderBy('name'),
        };

        return view('admin.achievements.index', [
            'achievements' => $query->paginate(15)->withQueryString(),
            'search' => $search,
            'status' => $status,
            'metric' => $metric,
            'sort' => $sort,
            'metrics' => self::METRICS,
            'totalAchievements' => Achievement::count(),
            'activeAchievements' => Achievement::where('is_active', true)->count(),
            'inactiveAchievements' => Achievement::where('is_active', false)->count(),
            'totalUnlocks' => UserAchievement::whereNotNull('unlocked_at')->count(),
        ]);
    }

    public function create()
    {
        return view('admin.achievements.create', ['metrics' => self::METRICS]);
    }

    public function store(Request $request, AdminAuditLogger $audit)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:achievements,code'],
            'name' => ['required', 'string', 'max:80'],
            'description' => ['required', 'string', 'max:240'],
            'icon' => ['required', 'string', 'max:16'],
            'metric' => ['required', Rule::in(array_keys(self::METRICS))],
            'target' => ['required', 'integer', 'min:1', 'max:100000'],
            'reward_coins' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        $achievement = DB::transaction(function () use ($validated, $audit, $request): Achievement {
            $achievement = Achievement::create([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'description' => $validated['description'],
                'icon' => $validated['icon'],
                'criteria' => [
                    $validated['metric'] => (int) $validated['target'],
                    'reward_coins' => (int) $validated['reward_coins'],
                ],
                'is_active' => true,
            ]);

            $audit->record($request, 'achievement.created', 'Created achievement ' . $achievement->code . '.', $achievement, [], [
                'code' => $achievement->code,
                'name' => $achievement->name,
                'metric' => $validated['metric'],
                'target' => (int) $validated['target'],
                'reward_coins' => (int) $validated['reward_coins'],
                'is_active' => true,
            ]);

            return $achievement;
        });

        return redirect()->route('admin.achievements.show', $achievement)
            ->with('status', 'Achievement created and activated. Existing player records were not changed.');
    }

    public function show(Achievement $achievement)
    {
        $achievement->loadCount([
            'userAchievements as unlocks_count' => fn (Builder $builder) => $builder->whereNotNull('unlocked_at'),
            'userAchievements as tracked_players_count',
        ]);

        $metric = $this->metricFor($achievement);
        $recentUnlocks = UserAchievement::query()
            ->with('user')
            ->where('achievement_id', $achievement->id)
            ->whereNotNull('unlocked_at')
            ->latest('unlocked_at')
            ->take(12)
            ->get();

        return view('admin.achievements.show', [
            'achievement' => $achievement,
            'metric' => $metric,
            'metricLabel' => self::METRICS[$metric] ?? 'Unsupported legacy metric',
            'metrics' => self::METRICS,
            'target' => (int) (($achievement->criteria ?? [])[$metric] ?? 1),
            'rewardCoins' => (int) (($achievement->criteria ?? [])['reward_coins'] ?? 0),
            'recentUnlocks' => $recentUnlocks,
        ]);
    }

    public function update(Request $request, Achievement $achievement, AdminAuditLogger $audit)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['required', 'string', 'max:240'],
            'icon' => ['required', 'string', 'max:16'],
            'metric' => ['required', Rule::in(array_keys(self::METRICS))],
            'target' => ['required', 'integer', 'min:1', 'max:100000'],
            'reward_coins' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        $currentMetric = $this->metricFor($achievement);
        $before = [
            'name' => $achievement->name,
            'description' => $achievement->description,
            'icon' => $achievement->icon,
            'metric' => $currentMetric,
            'target' => (int) (($achievement->criteria ?? [])[$currentMetric] ?? 1),
            'reward_coins' => (int) (($achievement->criteria ?? [])['reward_coins'] ?? 0),
        ];
        if ($validated['metric'] !== $currentMetric && $achievement->userAchievements()->exists()) {
            return back()->withErrors('The progress metric cannot be changed after players have started tracking this achievement. Create a new achievement with a new code instead.');
        }

        DB::transaction(function () use ($achievement, $validated, $audit, $request, $before): void {
            $achievement->update([
                'name' => $validated['name'],
                'description' => $validated['description'],
                'icon' => $validated['icon'],
                'criteria' => [
                    $validated['metric'] => (int) $validated['target'],
                    'reward_coins' => (int) $validated['reward_coins'],
                ],
            ]);

            $audit->record($request, 'achievement.updated', 'Updated achievement ' . $achievement->code . '.', $achievement, $before, [
                'name' => $validated['name'],
                'description' => $validated['description'],
                'icon' => $validated['icon'],
                'metric' => $validated['metric'],
                'target' => (int) $validated['target'],
                'reward_coins' => (int) $validated['reward_coins'],
            ]);
        });

        return redirect()->route('admin.achievements.show', $achievement)
            ->with('status', 'Achievement details updated. Existing unlock records and wallet transactions were preserved.');
    }

    public function toggleStatus(Request $request, Achievement $achievement, AdminAuditLogger $audit)
    {
        $wasActive = (bool) $achievement->is_active;
        DB::transaction(function () use ($achievement, $wasActive, $audit, $request): void {
            $achievement->update(['is_active' => ! $wasActive]);
            $audit->record($request, $achievement->is_active ? 'achievement.activated' : 'achievement.deactivated', ($achievement->is_active ? 'Activated' : 'Deactivated') . ' achievement ' . $achievement->code . '.', $achievement, ['is_active' => $wasActive], ['is_active' => (bool) $achievement->is_active]);
        });

        return redirect()->route('admin.achievements.show', $achievement)
            ->with('status', $achievement->is_active
                ? 'Achievement activated for future progress.'
                : 'Achievement deactivated. Existing player unlocks and rewards were preserved.');
    }

    private function metricFor(Achievement $achievement): string
    {
        foreach (array_keys(self::METRICS) as $metric) {
            if (array_key_exists($metric, (array) ($achievement->criteria ?? []))) {
                return $metric;
            }
        }

        return 'apples';
    }
}
