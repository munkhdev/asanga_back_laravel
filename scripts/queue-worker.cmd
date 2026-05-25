@echo off
set PHP_EXE=C:\Users\monhb\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.WinGet.Source_8wekyb3d8bbwe\php.exe
cd /d C:\webs\asanga\asanga_back_laravel_runtime
:loop
"%PHP_EXE%" artisan queue:work --queue=default --sleep=3 --tries=3 --timeout=120 --max-time=3600
goto loop
