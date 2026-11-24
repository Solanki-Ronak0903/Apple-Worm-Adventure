$ErrorActionPreference = 'Stop'

# Run this script from anywhere. It resolves the Laravel project root from
# the script's location only when the script is placed in the project root.
$ProjectRoot = Get-Location
if (-not (Test-Path (Join-Path $ProjectRoot 'artisan')) -or
    -not (Test-Path (Join-Path $ProjectRoot 'composer.json'))) {
    throw 'Open PowerShell in the Apple Worm Adventure project root (the folder containing artisan and composer.json), then run this script again.'
}

$ObsoleteTests = @(
    'tests/Feature/PlatformerLevelMigrationTest.php',
    'tests/Feature/SideViewPrototypeTest.php'
)

Write-Host 'Checking for obsolete tests from the discontinued platformer/prototype build...' -ForegroundColor Cyan
foreach ($RelativePath in $ObsoleteTests) {
    $FullPath = Join-Path $ProjectRoot $RelativePath
    if (Test-Path -LiteralPath $FullPath -PathType Leaf) {
        Remove-Item -LiteralPath $FullPath -Force
        Write-Host "Removed: $RelativePath" -ForegroundColor Yellow
    } else {
        Write-Host "Already absent: $RelativePath" -ForegroundColor DarkGray
    }
}

$ReplacementTests = @(
    'tests/Feature/SideViewEngineIntegrationTest.php',
    'tests/Feature/SideViewGameplayRouteTest.php',
    'tests/Feature/SideViewPuzzleTest.php',
    'tests/Feature/SideViewHintTest.php'
)

$Missing = @()
foreach ($RelativePath in $ReplacementTests) {
    if (-not (Test-Path -LiteralPath (Join-Path $ProjectRoot $RelativePath) -PathType Leaf)) {
        $Missing += $RelativePath
    }
}

if ($Missing.Count -gt 0) {
    Write-Warning 'Some replacement side-view tests are missing:'
    $Missing | ForEach-Object { Write-Warning "  $_" }
    throw 'The project folder may not contain the latest gameplay-polish update. Do not continue until the matching update is extracted.'
}

Write-Host ''
Write-Host 'Obsolete test cleanup complete. No application, route, database, or player data was changed.' -ForegroundColor Green
Write-Host 'Now run:' -ForegroundColor Cyan
Write-Host '  php artisan optimize:clear'
Write-Host '  php artisan test'
