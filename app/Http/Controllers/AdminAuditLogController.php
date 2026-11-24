<?php

namespace App\Http\Controllers;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AdminAuditLogController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'action' => ['nullable', 'string', 'max:80'],
            'admin' => ['nullable', 'integer', 'exists:users,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $query = AdminAuditLog::query()->with('actor')->latest();
        $search = trim($validated['q'] ?? '');
        $action = $validated['action'] ?? '';
        $admin = $validated['admin'] ?? '';

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('summary', 'like', '%' . $search . '%')
                    ->orWhere('action', 'like', '%' . $search . '%')
                    ->orWhere('subject_type', 'like', '%' . $search . '%');
            });
        }
        if ($action !== '') {
            $query->where('action', $action);
        }
        if ($admin !== '') {
            $query->where('actor_id', (int) $admin);
        }
        if (! empty($validated['from'])) {
            $query->whereDate('created_at', '>=', $validated['from']);
        }
        if (! empty($validated['to'])) {
            $query->whereDate('created_at', '<=', $validated['to']);
        }

        return view('admin.audit.index', [
            'logs' => $query->paginate(25)->withQueryString(),
            'search' => $search,
            'selectedAction' => $action,
            'selectedAdmin' => $admin,
            'from' => $validated['from'] ?? '',
            'to' => $validated['to'] ?? '',
            'actions' => AdminAuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
            'admins' => User::query()->where('is_admin', true)->orderBy('name')->get(['id', 'name']),
            'totalLogs' => AdminAuditLog::count(),
            'todayLogs' => AdminAuditLog::whereDate('created_at', today())->count(),
        ]);
    }
}
