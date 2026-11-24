<?php

namespace App\Services;

use App\Models\AccountSecurityEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AccountSecurityLogger
{
    /**
     * Store a minimal account-security event. Never pass passwords, reset tokens,
     * verification URLs, cookies, remember tokens, or provider credentials.
     */
    public function record(Request $request, string $event, ?User $user = null, array $metadata = []): void
    {
        $safeMetadata = [];
        foreach (['method', 'channel', 'fields', 'reason'] as $key) {
            if (array_key_exists($key, $metadata)) {
                $safeMetadata[$key] = is_array($metadata[$key])
                    ? array_values(array_map(static fn ($value) => Str::limit((string) $value, 50, ''), $metadata[$key]))
                    : Str::limit((string) $metadata[$key], 100, '');
            }
        }

        AccountSecurityEvent::query()->create([
            'user_id' => $user?->id,
            'event' => Str::limit($event, 80, ''),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'metadata' => $safeMetadata ?: null,
            'created_at' => now(),
        ]);
    }
}
