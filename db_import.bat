@echo off
setlocal enabledelayedexpansion

set "MYSQL=C:\xampp\mysql\bin\mysql.exe"
set "DB=group_13"
set "USER=root"
set "PASS=root123456"

if not exist "%MYSQL%" (
    echo MySQL client not found:
    echo %MYSQL%
    pause
    exit /b 1
)

set COUNT=0

echo Available SQL files in this folder:
echo.

for %%F in (*.sql) do (
    set /a COUNT+=1
    set "FILE[!COUNT!]=%%F"
    echo !COUNT!. %%F
)

if "%COUNT%"=="0" (
    echo No .sql files found in this folder.
    pause
    exit /b 1
)

echo.
set /p CHOICE=Select file number: 

if not defined FILE[%CHOICE%] (
    echo Invalid selection.
    pause
    exit /b 1
)

set "SQLFILE=!FILE[%CHOICE%]!"

echo.
echo Selected: %SQLFILE%
echo This may overwrite existing tables in %DB%.
set /p CONFIRM=Continue? (y/N): 

if /I not "%CONFIRM%"=="y" (
    echo Cancelled.
    pause
    exit /b 0
)

"%MYSQL%" --default-character-set=utf8mb4 -u %USER% -p%PASS% %DB% < "%SQLFILE%"

if errorlevel 1 (
    echo.
    echo Import failed.
) else (
    echo.
    echo Import completed successfully.
)

pause
