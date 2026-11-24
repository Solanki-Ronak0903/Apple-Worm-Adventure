<?php

namespace Tests;

use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Contracts\Auth\Authenticatable;

abstract class TestCase extends BaseTestCase
{
    /**
     * Authenticate as another test user using a fresh session.
     *
     * AuthenticateSession stores the authenticated user's password hash in the
     * session. Reusing one feature-test session while switching identities can
     * correctly invalidate the second identity, unlike two real browser sessions.
     */
    protected function actingAsFreshSession(Authenticatable $user, ?string $guard = null): static
    {
        $this->flushSession();

        return $this->actingAs($user, $guard);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Keep rate-limit counters isolated between feature tests. Production
        // rate limiting remains active and is tested within each test method.
        Cache::flush();
    }
}
