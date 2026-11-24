# Level Management – 18 November 2026

This update adds the first safe level-management workflow to the Apple Worm Adventure admin workspace.

## Included

- Level library at `/admin/levels` with title/number search, world, difficulty and publication filters, sorting and pagination.
- Level detail page at `/admin/levels/{level}` with a tile-map preview, world metadata, activity counts, starting worm, collectible, crate, spike, switch and gate counts.
- Safe metadata editing for level title, difficulty, player lesson and three progressive hints.
- Explicit server-side level validation action.
- Publish/unpublish control. Publishing requires a valid side-view layout and a successful replay of a private, approved route through `SideViewPuzzleService`.
- Publication sequencing guards: Level 01 cannot be unpublished; levels cannot be published across a draft gap; higher published levels must be unpublished first; active sessions prevent unpublishing.
- Temporary validation sessions are deleted inside the validation transaction and do not grant player rewards or affect player progress.
- Database seeding now uses `firstOrCreate` for shipped levels so a later `db:seed` does not silently overwrite admin edits or publication choices.
- The admin sidebar and dashboard quick actions link to Level Management.
- Feature tests cover access, filters, map preview, metadata edits, validation replay, publication guards, invalid geometry and seeder preservation.

## Safe editing boundaries

This first level-management release does not permit editing tile coordinates, starting positions, terrain, apples, crates, spikes, switches, gates or portals. Those fields define puzzle mechanics and remain protected until a full map editor with validation and solution authoring is built.

The release validator uses private server-only approved move sequences for the shipped 25 levels. The sequences are not embedded in the player-facing level configuration. A level without an approved route remains unpublished.

## Routes

- `admin.levels` – `/admin/levels`
- `admin.levels.show` – `/admin/levels/{level}`
- `admin.levels.update` – `PUT /admin/levels/{level}`
- `admin.levels.validate` – `POST /admin/levels/{level}/validate`
- `admin.levels.publication` – `PATCH /admin/levels/{level}/publication`

All routes remain under the existing `auth`, `admin` and throttling middleware.

## Database and installation

No schema migration is required. Back up the project and database, extract the update over the project while preserving `.env`, `vendor` and the database, then run:

```powershell
composer dump-autoload
php artisan optimize:clear
php artisan test
php artisan serve
```

Do not run `migrate:fresh`. The updated seeder is intentionally non-destructive for existing level records; it will not reset admin-edited title, difficulty, lesson, hint or publication fields.

## Verification performed for this package

- PHP syntax validation completed for all PHP source and test files.
- JavaScript syntax validation completed for all shipped JavaScript files.
- All 25 catalog layouts passed structural coordinate and overlap checks.
- The complete PHPUnit suite was not run in this build environment because `vendor/` and Composer are unavailable. Run `php artisan test` locally before merging or deployment.
