$ErrorActionPreference = 'Stop'
Write-Host 'Apple Worm Adventure - Laravel setup' -ForegroundColor Green
if (!(Get-Command php -ErrorAction SilentlyContinue)) { throw 'PHP is not on PATH. Install PHP 8.2+ first.' }
if (!(Get-Command composer -ErrorAction SilentlyContinue)) { throw 'Composer is not on PATH. Install Composer 2 first.' }
if (!(Test-Path '.env')) { Copy-Item '.env.example' '.env'; Write-Host 'Created .env. Edit DB_USERNAME and DB_PASSWORD before continuing.' -ForegroundColor Yellow }
composer install
if (!(Select-String -Path '.env' -Pattern '^APP_KEY=base64:' -Quiet)) { php artisan key:generate }
Write-Host 'Next: create the MySQL database apple_worm_adventure, update .env, then run php artisan migrate --seed and php artisan serve.' -ForegroundColor Cyan
