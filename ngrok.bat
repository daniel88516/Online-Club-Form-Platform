@echo off
set "NGROK_PATH=C:\Users\danie\AppData\Local\Microsoft\WindowsApps\ngrok.exe"
set "MY_URL=damply-overslow-zana.ngrok-free.dev"

:: 1. 先清空舊程序
taskkill /f /im ngrok.exe >nul 2>&1

:: 2. 使用 PowerShell 啟動隱藏視窗 (這行執行完會自動關閉目前的黑框，但 ngrok 會留在後台)
powershell -WindowStyle Hidden -Command "Start-Process '%NGROK_PATH%' -ArgumentList 'http --url=%MY_URL% 80' -WindowStyle Hidden"

echo [OK] Tunnel started in background.
timeout /t 2 >nul