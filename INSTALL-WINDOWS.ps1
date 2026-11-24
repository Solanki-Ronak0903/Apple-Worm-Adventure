$ErrorActionPreference = 'Stop'

Write-Host 'Apple Worm Adventure - Laravel setup' -ForegroundColor Green

if (!(Get-Command php -ErrorAction SilentlyContinue)) {
    throw 'PHP is not on PATH. Install PHP 8.2+ first.'
}

if (!(Get-Command composer -ErrorAction SilentlyContinue)) {
    throw 'Composer is not on PATH. Install Composer 2 first.'
}

if (!(Test-Path '.env')) {
    Copy-Item '.env.example' '.env'
    Write-Host 'Created .env. Edit database settings before continuing.' -ForegroundColor Yellow
}

composer install

if (!(Select-String -Path '.env' -Pattern '^APP_KEY=base64:' -Quiet)) {
    php artisan key:generate
}

Write-Host ''
Write-Host 'Next steps:' -ForegroundColor Cyan
Write-Host '1. Create the MySQL database apple_worm_adventure.'
Write-Host '2. Set DB_USERNAME and DB_PASSWORD in .env.'
Write-Host '3. For a fresh database with no admin, set ADMIN_EMAIL and a strong ADMIN_PASSWORD in .env.' -ForegroundColor Yellow
Write-Host '4. Run php artisan migrate --seed.'
Write-Host '5. Run php artisan serve.'
