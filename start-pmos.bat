@echo off
title PMOS Screening System
cd /d "%~dp0"

echo ============================================================
echo   PMOS Clinical Screening Support System
echo   The first time, setting up takes about 5 to 10 minutes.
echo   Please keep this window open.
echo ============================================================
echo.

where php >nul 2>nul
if errorlevel 1 (
    echo PHP was not found on this computer.
    echo Please install Laravel Herd first, restart the computer, then try again:
    echo     https://herd.laravel.com/download/windows
    pause
    exit /b 1
)

where npm >nul 2>nul
if errorlevel 1 (
    echo Node.js was not found on this computer.
    echo Please install Node.js ^(the LTS version^), restart the computer, then try again:
    echo     https://nodejs.org
    pause
    exit /b 1
)

php -m | findstr /i /c:"pdo_sqlite" >nul
if errorlevel 1 (
    echo PHP on this computer cannot use SQLite ^(the pdo_sqlite extension is missing or blocked^).
    echo If you installed Laravel Herd, restart the computer and try again.
    echo Otherwise use the online version:  https://pmos-screening.onrender.com
    pause
    exit /b 1
)

if not exist vendor\autoload.php (
    echo [1/5] Downloading the system's packages...
    call composer install --no-interaction
    if errorlevel 1 (
        echo Downloading the packages failed. Check the internet connection and try again.
        pause
        exit /b 1
    )
)

if not exist .env (
    echo [2/5] Creating the settings file...
    copy .env.example .env >nul
    call php artisan key:generate --no-interaction
)

if not exist database\database.sqlite (
    echo [3/5] Creating the database with the demo accounts and sample patients...
    type nul > database\database.sqlite
    call php artisan migrate --seed --force --no-interaction
    if errorlevel 1 (
        del database\database.sqlite
        echo Creating the database failed. Please send a photo of this window to the developer.
        pause
        exit /b 1
    )
    call php artisan pmos:sample-report-data --force --no-interaction
)

if not exist public\build\manifest.json (
    echo [4/5] Building the screens' design...
    call npm install --no-audit --no-fund
    call npm run build
)

echo.
echo [5/5] Starting the system...
echo ============================================================
echo   Open this address in your web browser:  http://127.0.0.1:8000
echo   Log in with:  doctor@chitawira.test   password: password
echo   Keep this window open while you use the system.
echo   To stop the system, close this window.
echo ============================================================
echo.

start "" cmd /c "timeout /t 4 >nul & start http://127.0.0.1:8000"
call php artisan serve --port=8000
pause
