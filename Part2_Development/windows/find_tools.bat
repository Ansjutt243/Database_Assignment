@echo off
rem =====================================================================
rem Shared by setup.bat, start.bat and install_schedule.bat (Windows).
rem Finds MySQL and PHP:
rem   MySQL: MYSQL_BIN_DIR, else the newest C:\Program Files\MySQL\MySQL Server *\bin,
rem          else mysql.exe on the PATH
rem   PHP:   PHP_BIN, else C:\php\php.exe, else php.exe on the PATH
rem PHP_ARGS loads the three extensions the system needs, so php.ini
rem does not have to be edited.
rem =====================================================================
if not defined MYSQL_BIN_DIR (
  for /d %%D in ("C:\Program Files\MySQL\MySQL Server *") do (
    if exist "%%~D\bin\mysql.exe" set "MYSQL_BIN_DIR=%%~D\bin"
  )
)
if not defined MYSQL_BIN_DIR (
  for /f "delims=" %%P in ('where mysql.exe 2^>nul') do if not defined MYSQL_BIN_DIR set "MYSQL_BIN_DIR=%%~dpP"
)
if not defined MYSQL_BIN_DIR (
  echo MySQL was not found. Install MySQL Server 8 - see the Installation Guide.
  exit /b 1
)
set "MYSQL=%MYSQL_BIN_DIR%\mysql.exe"
set "MYSQLADMIN=%MYSQL_BIN_DIR%\mysqladmin.exe"

if not defined PHP_BIN if exist "C:\php\php.exe" set "PHP_BIN=C:\php\php.exe"
if not defined PHP_BIN (
  for /f "delims=" %%P in ('where php.exe 2^>nul') do if not defined PHP_BIN set "PHP_BIN=%%P"
)
if not defined PHP_BIN (
  echo PHP was not found. Unzip PHP 8.2 to C:\php - see the Installation Guide.
  exit /b 1
)
for %%P in ("%PHP_BIN%") do set "PHP_DIR=%%~dpP"
set PHP_ARGS=-d extension_dir="%PHP_DIR%ext" -d extension=pdo_mysql -d extension=mbstring -d extension=openssl
exit /b 0
