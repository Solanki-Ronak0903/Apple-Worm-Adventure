# Authentication Session Test Fix — 19 November 2026

## What caused the failures

The account-security update applies Laravel's `AuthenticateSession` middleware to authenticated routes. This middleware stores the current user's password hash in the browser session and invalidates that session if a later request presents a different user or a changed password hash.

Several feature tests called `$this->actingAs()` for multiple different users inside one test method while reusing a single session. That is not equivalent to separate browser sessions. The middleware correctly invalidated the second test identity, causing 302 redirects for web routes and 401 responses for JSON routes.

The account heading also contained a raw ampersand while the test used Laravel's escaped `assertSee()` behaviour. The view now uses the HTML entity `&amp;`.

## Changes

- Added `actingAsFreshSession()` to `tests/TestCase.php`. It clears the previous test session before authenticating a different test user.
- Updated the admin authorization tests and session-owner hint test to use isolated sessions when changing identities.
- Corrected the account heading's HTML ampersand.
- Added a regression test confirming that a session is invalidated after the account password hash changes.

## Security impact

No production authentication middleware was removed or weakened. The test changes model separate browser sessions correctly and preserve the `auth.session` protection.

## Installation

This is a source/test-only fix. No database migration or seeding is required.

Run:

```powershell
php artisan optimize:clear
php artisan test
```

The full PHPUnit suite must be run locally after replacing the project files.
