@echo off
chcp 65001 >nul
title EMS - Export for Hostinger
echo ============================================
echo   EMS - Export database ready for Hostinger
echo ============================================
echo.
C:\wamp64\bin\php\php8.2.30\php.exe "%~dp0tools\export_hosting.php"
echo.
if errorlevel 1 (
    echo FAILED - read the message above
) else (
    echo DONE - upload the file shown above from your Desktop
)
echo.
pause
