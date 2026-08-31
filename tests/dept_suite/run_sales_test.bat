@echo off
chcp 65001 >nul
cd /d "%~dp0..\.."
echo.
echo   === EMS :: Sales Department Test ===
echo.
"C:\wamp64\bin\php\php8.2.30\php.exe" "tests\dept_suite\run.php" %*
echo.
pause
