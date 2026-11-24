<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AdminAuditLogger
{
    /**
     * Record a non-secret snapshot of an administrative mutation.
     * Never pass passwords, tokens, cookies, or provider credentials here.
     */
    public function record(
        Request $request,
        string $action,
        string $summary,
        ?Model $subject = null,
        array $before = [],
        array $after = [],
    ): AdminAuditLog {
        return AdminAuditLog::query()->create([
            'actor_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'summary' => mb_substr($summary, 0, 240),
            'before' => $before ?: null,
            'after' => $after ?: null,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
        ]);
    }
}
