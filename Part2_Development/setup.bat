@echo off
rem =====================================================================
rem ML IT Helpdesk - one-time database setup (Windows)
rem Builds the mlhelpdesk database from scratch: tables, views, triggers,
rem procedures, pilot data and security roles, then runs the 41 tests.
rem WARNING: deletes any existing mlhelpdesk database.
rem Usage: double-click setup.bat, or run it in Command Prompt.
rem macOS / Linux: ./setup.sh
rem =====================================================================
setlocal
cd /d "%~dp0"
call windows\find_tools.bat || goto :fail

"%MYSQLADMIN%" ping >nul 2>&1
if errorlevel 1 (
  echo MySQL is not running. Open Services, start "MySQL84" or "MySQL80", then try again.
  goto :fail
)

rem the password is read without showing it on screen
for /f "usebackq delims=" %%p in (`powershell -NoProfile -Command "$p = Read-Host 'MySQL root password' -AsSecureString; [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($p))"`) do set "MYSQL_PWD=%%p"

cd database
echo Running 01_create_tables.sql ...
"%MYSQL%" -uroot < 01_create_tables.sql > nul || goto :fail
for %%f in (02_views_triggers_procedures.sql 03_pilot_data.sql 04_security_roles.sql) do (
  echo Running %%f ...
  "%MYSQL%" -uroot mlhelpdesk < %%f > nul || goto :fail
)
echo Running 06_validation_tests.sql ...
"%MYSQL%" -uroot -t mlhelpdesk < 06_validation_tests.sql > "%TEMP%\mlh_tests.txt" || goto :fail
powershell -NoProfile -Command "Get-Content '%TEMP%\mlh_tests.txt' | Select-Object -Last 5"
set "MYSQL_PWD="
echo.
echo Done. Start the application with start.bat
pause
exit /b 0

:fail
set "MYSQL_PWD="
echo.
echo Setup did not finish. Check the message above.
pause
exit /b 1
