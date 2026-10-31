@echo off
echo ========================================
echo   idexal Os - Complete Test Suite
echo ========================================
echo.

REM Start Laravel development server in background
echo Starting web platform...
start "" cmd /k "cd website-laravel-idexalOs && php artisan serve --host=0.0.0.0 --port=8000"
timeout /t 5 /nobreak >nul

REM Open browser to platform
echo Opening platform dashboard...
start http://localhost:8000/admin/login

REM Open testing dashboard
echo Opening test dashboard...
start "" c:\users\idexal\Desktop\idexal-os\test_dashboard_local.html

REM Open desktop app if exists
if exist "apps\desktop\dist-electron-*.exe" (
    echo Launching desktop application...
    start "" "apps\desktop\dist-electron-*.exe"
) else (
    echo Desktop installer not found - build first with: cd apps/desktop ^&^& pnpm run package:electron
)

echo.
echo ========================================
echo Tests running...
echo Web Platform: http://localhost:8000
echo Admin Panel: http://localhost:8000/admin/login
echo Testing Dashboard: test_dashboard_local.html
echo ========================================
echo.
pause
