@echo off
taskkill /f /im ngrok.exe >nul 2>&1
PowerShell -WindowStyle Hidden -Command "Start-Process 'C:\ngrok\ngrok.exe' -ArgumentList 'http --url=damply-overslow-zana.ngrok-free.dev 80' -WindowStyle Hidden"
echo ngrok started in background
pause
