@echo off
REM =====================================================================
REM  TradeSmart Operations Quiz Arena - one-click setup for XAMPP (Windows)
REM  1. Start Apache and MySQL in the XAMPP Control Panel
REM  2. Create an empty database called "quizsystem" in phpMyAdmin
REM  3. Double-click this file
REM =====================================================================
setlocal
cd /d "%~dp0"
set PHP=C:\xampp\php\php.exe
if not exist "%PHP%" set PHP=php

echo.
echo === TradeSmart Operations Quiz Arena setup ===
"%PHP%" -r "exit(version_compare(PHP_VERSION, '8.2.0', '>=') ? 0 : 1);"
if errorlevel 1 (
  echo ERROR: PHP 8.2 or newer is required. Please update XAMPP.
  pause & exit /b 1
)

if not exist "vendor\autoload.php" (
  if exist "vendor.zip" (
    echo Extracting PHP libraries from vendor.zip ...
    tar -xf vendor.zip
  ) else (
    echo Installing PHP libraries with Composer ...
    call composer install --no-interaction
  )
)
if not exist "vendor\autoload.php" (
  echo ERROR: PHP libraries are missing. Run "composer install" or extract vendor.zip.
  pause & exit /b 1
)

if not exist ".env" (
  copy ".env.example" ".env" >nul
  "%PHP%" artisan key:generate --force
)

echo Creating database tables and sample data ...
"%PHP%" artisan migrate --seed --force
if errorlevel 1 (
  echo.
  echo ERROR: Could not reach the database. Check that MySQL is running in XAMPP,
  echo that the "quizsystem" database exists, and the DB_ settings in .env.
  pause & exit /b 1
)
"%PHP%" artisan optimize:clear >nul

echo.
echo ============================================================
echo  Done!  Open http://localhost/quizsystem in your browser.
echo  Demo login:  admin@tradesmart.local  /  QuizArena@2026
echo  (change it under Users, or run: php artisan quiz:create-admin)
echo ============================================================
pause
