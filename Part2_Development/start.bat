@echo off
rem =====================================================================
rem ML IT Helpdesk - start the system (Windows)
rem MySQL runs as a Windows service; this starts the PHP web server on
rem http://127.0.0.1:8080 and opens it in the browser.
rem Stop with Ctrl+C or by closing this window.
rem macOS / Linux: ./start.sh
rem =====================================================================
setlocal
cd /d "%~dp0"
call windows\find_tools.bat || goto :fail

"%MYSQLADMIN%" ping >nul 2>&1
if errorlevel 1 (
  echo MySQL is not running. Open Services, start "MySQL84" or "MySQL80", then try again.
  goto :fail
)
echo MySQL is running.
echo ML IT Helpdesk is running at  http://127.0.0.1:8080   (Ctrl+C to stop)
start "" http://127.0.0.1:8080
"%PHP_BIN%" %PHP_ARGS% -S 127.0.0.1:8080 -t app\public
exit /b 0

:fail
pause
exit /b 1
