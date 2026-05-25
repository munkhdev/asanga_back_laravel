$ErrorActionPreference = 'Stop'

$php = 'C:\Users\monhb\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.WinGet.Source_8wekyb3d8bbwe\php.exe'
$root = Split-Path -Parent $PSScriptRoot

Set-Location $root

& $php artisan key:generate --force
& $php artisan storage:link
& $php artisan migrate --force
& $php artisan config:clear
& $php artisan route:clear
& $php artisan view:clear
& $php artisan event:clear
& $php artisan optimize
& $php artisan config:cache
& $php artisan route:cache
& $php artisan view:cache

Write-Host 'Production optimize completed.' -ForegroundColor Green
