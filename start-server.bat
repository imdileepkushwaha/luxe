@echo off
cd /d "%~dp0"
REM LUXE dev server — fixed port 5000 (XAMPP Apache/phpMyAdmin typically stay on 80)
set PORT=5001
echo LUXE dev server: http://localhost:%PORT%/index.php
echo phpMyAdmin (XAMPP): usually http://localhost/phpmyadmin/  ^(port 80^)
where php >nul 2>&1
if %ERRORLEVEL%==0 (
  php -S localhost:%PORT% router.php
  goto :eof
)
if exist "C:\xampp\php\php.exe" (
  "C:\xampp\php\php.exe" -S localhost:%PORT% router.php
  goto :eof
)
echo PHP not found. Add PHP to PATH or edit this file to point to php.exe
pause
