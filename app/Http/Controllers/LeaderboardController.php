<?php

namespace App\Http\Controllers;

use App\Models\UserLevelProgress;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    public function index(Request $request)
    {
        $leaders = UserLevelProgress::query()
            ->where('completed', true)
            ->whereHas('level', fn ($query) => $query->where('is_published', true))
            ->selectRaw('user_id, SUM(best_score) as score, SUM(stars) as stars, COUNT(CASE WHEN completed = 1 THEN 1 END) as levels_completed')
            ->groupBy('user_id')
            ->orderByDesc('score')
            ->limit(50)
            ->with('user')
            ->get();

        return view('leaderboard', compact('leaders'));
    }
}
