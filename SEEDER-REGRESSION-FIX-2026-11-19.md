# Seeder regression-test fix — 19 November 2026

## Root cause

The initial-release migration uses the query builder (`DB::table('levels')->update(...)`) to hide existing levels above 25. The test had already created an Eloquent `Level` instance with `is_published = true`. The raw query changed the database row to `false`, but the in-memory model still held `true`.

When the test later called `$futureLevel->update(['is_published' => true])`, Eloquent saw no dirty attribute because the model already held `true`. It therefore did not persist the intended administrator action. The subsequent assertion read the database value `false` and incorrectly made the existing `firstOrCreate` seeder behaviour look broken.

## Change made

- Refresh the model after running the raw migration, then assert that it is unpublished.
- Simulate the administrator publish action using the refreshed model.
- Assert publication immediately and again after running `DatabaseSeeder` a second time.
- Restrict the original-campaign `plannedRoute` checks to levels 1–25. Level 26 is a future placeholder with no approved map in this test; it is used to verify that an explicit admin publication choice survives reseeding.

## Production code

`DatabaseSeeder.php` is intentionally unchanged. It uses `Level::firstOrCreate` for the initial release levels and does not reset existing Level 26 publication state. Changing the seeder to force publication flags would reintroduce the admin-data-loss risk.

## Verification

- PHP syntax lint: all 105 PHP files passed in the build environment.
- JavaScript syntax: project scripts passed `node --check`.
- Full PHPUnit suite: must be run locally because this package does not include Composer's `vendor/` directory and the build environment does not have Composer or PDO SQLite installed.

Run locally:

```powershell
php artisan optimize:clear
php artisan test
```
