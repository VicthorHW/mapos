@echo off
setlocal

set "PHP_BIN=php"
where php >nul 2>nul
if errorlevel 1 (
    if exist "C:\xampp\php\php.exe" (
        set "PHP_BIN=C:\xampp\php\php.exe"
    ) else (
        echo PHP nao foi encontrado no PATH nem em C:\xampp\php\php.exe.
        exit /b 1
    )
)

"%PHP_BIN%" "%~dp0install.php" %*
exit /b %ERRORLEVEL%
