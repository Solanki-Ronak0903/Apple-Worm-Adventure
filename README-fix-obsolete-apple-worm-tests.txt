APPLE WORM ADVENTURE - OBSOLETE TEST CLEANUP

Why this is needed:
The latest gameplay-polish ZIP no longer contains the discontinued platformer/prototype tests. When extracted over an existing Windows project folder, extraction merges files and does not delete old files that are absent from the ZIP. The two obsolete test files therefore remained in the local tests/Feature folder and are still discovered by PHPUnit.

How to apply:
1. Copy fix-obsolete-apple-worm-tests.ps1 into the Laravel project root (the folder containing artisan and composer.json).
2. Open PowerShell in that folder.
3. Run:
   powershell -ExecutionPolicy Bypass -File .\fix-obsolete-apple-worm-tests.ps1
4. Then run:
   php artisan optimize:clear
   php artisan test

The script removes only these obsolete test files:
- tests/Feature/PlatformerLevelMigrationTest.php
- tests/Feature/SideViewPrototypeTest.php

It checks that the replacement side-view tests exist before finishing. It does not modify application code, routes, database contents, player progress, wallet balances, or rewards.

Based on the supplied test log, removing these two files removes five outdated test methods (one of which currently passes, four fail). The remaining current side-view integration, gameplay route, puzzle, and hint tests provide the active gameplay coverage. Expected outcome, assuming no other local modifications: 58 tests passed, 0 failed. The exact final count can vary if the project has additional local tests.
