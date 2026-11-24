<?php

namespace App\Http\Controllers;

use App\Models\AdminSetting;
use App\Services\AdminAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminSettingsController extends Controller
{
    public function index()
    {
        return view('admin.settings.index', [
            'settings' => AdminSetting::allValues(),
        ]);
    }

    public function update(Request $request, AdminAuditLogger $audit)
    {
        // Missing checkboxes are intentionally normalized to false.
        $request->merge([
            'allow_registration' => $request->boolean('allow_registration'),
            'require_email_verification' => $request->boolean('require_email_verification'),
            'leaderboard_enabled' => $request->boolean('leaderboard_enabled'),
            'rewarded_actions_enabled' => $request->boolean('rewarded_actions_enabled'),
        ]);

        $validated = $request->validate([
            'allow_registration' => ['required', 'boolean'],
            'require_email_verification' => ['required', 'boolean'],
            'leaderboard_enabled' => ['required', 'boolean'],
            'rewarded_actions_enabled' => ['required', 'boolean'],
            'new_player_starting_coins' => ['required', 'integer', 'min:0', 'max:5000'],
        ]);

        $before = AdminSetting::allValues();
        $after = [
            'allow_registration' => (bool) $validated['allow_registration'],
            'require_email_verification' => (bool) $validated['require_email_verification'],
            'leaderboard_enabled' => (bool) $validated['leaderboard_enabled'],
            'rewarded_actions_enabled' => (bool) $validated['rewarded_actions_enabled'],
            'new_player_starting_coins' => (int) $validated['new_player_starting_coins'],
        ];
        $changedBefore = [];
        $changedAfter = [];

        foreach ($after as $key => $value) {
            if ($before[$key] !== $value) {
                $changedBefore[$key] = $before[$key];
                $changedAfter[$key] = $value;
            }
        }

        if ($changedAfter === []) {
            return redirect()->route('admin.settings')->with('status', 'Settings are already up to date.');
        }

        DB::transaction(function () use ($after, $request, $audit, $changedBefore, $changedAfter): void {
            foreach ($changedAfter as $key => $value) {
                $group = match ($key) {
                    'allow_registration', 'require_email_verification' => 'accounts',
                    'leaderboard_enabled' => 'gameplay',
                    'rewarded_actions_enabled' => 'rewards',
                    default => 'economy',
                };
                AdminSetting::putValue($key, $value, $group, (int) $request->user()->id);
            }

            $audit->record(
                $request,
                'settings.updated',
                'Updated application settings: ' . implode(', ', array_keys($changedAfter)),
                null,
                $changedBefore,
                $changedAfter,
            );
        });

        return redirect()->route('admin.settings')->with('status', 'Application settings saved and recorded in the audit log.');
    }
}
