<?php

namespace App\Http\Controllers;

use App\Models\Level;
use App\Models\RewardClaim;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function index()
    {
        return view('admin.dashboard', [
            'users' => User::count(),
            'levels' => Level::where('is_published', true)->count(),
            'claims' => RewardClaim::count(),
            'transactions' => WalletTransaction::count(),
            'recentClaims' => RewardClaim::latest()->take(15)->get(),
        ]);
    }

    public function economy(Request $request)
    {
        $claims = RewardClaim::latest()->paginate(30);

        return view('admin.economy', compact('claims'));
    }
}
