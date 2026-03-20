@echo off
echo [SYSTEM] Stopping all ngrok background services...

:: /F = Force, /IM = Image Name (ngrok.exe)
taskkill /f /im ngrok.exe >nul 2>&1

:: Check if the command was successful
if %errorlevel% equ 0 (
    echo [SUCCESS] Your tunnel has been closed. Your website is now offline.
) else (
    echo [INFO] No active ngrok process found. (It's already closed!)
)

echo.
echo Press any key to exit.
pause >nul