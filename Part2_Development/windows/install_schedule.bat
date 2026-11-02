@echo off
rem =====================================================================
rem Nightly backup at 18:00, Monday-Saturday (Design Document Table 41)
rem with Windows Task Scheduler.
rem   windows\install_schedule.bat            install
rem   windows\install_schedule.bat --remove   remove
rem Output: app\storage\logs\schedule.log
rem =====================================================================
setlocal
cd /d "%~dp0.."
set "ROOT=%CD%"
set "TASK=ML Helpdesk Nightly Backup"

if "%~1"=="--remove" (
  schtasks /Delete /TN "%TASK%" /F && echo Scheduled backup removed.
  exit /b
)

call windows\find_tools.bat || exit /b 1

rem the task runs a small batch file, so paths with spaces stay simple
set "JOB=%ROOT%\windows\scheduled_backup.bat"
(
  echo @echo off
  echo "%PHP_BIN%" %PHP_ARGS% "%ROOT%\app\cli\scheduled_backup.php" ^>^> "%ROOT%\app\storage\logs\schedule.log" 2^>^&1
) > "%JOB%"

schtasks /Create /TN "%TASK%" /SC WEEKLY /D MON,TUE,WED,THU,FRI,SAT /ST 18:00 /TR "\"%JOB%\"" /F
if errorlevel 1 exit /b 1
echo Installed. Nightly backup: Mon-Sat 18:00 -^> %ROOT%\app\storage\backups
echo Check with:  schtasks /Query /TN "%TASK%"
