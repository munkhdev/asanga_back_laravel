$ErrorActionPreference = 'Stop'

$php = 'C:\Users\monhb\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.WinGet.Source_8wekyb3d8bbwe\php.exe'
$root = Split-Path -Parent $PSScriptRoot

Set-Location $root
& $php artisan queue:work --queue=default --sleep=3 --tries=3 --timeout=120 --max-time=3600
