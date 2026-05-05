@echo off
chcp 65001 >nul
C:\xampp\mysql\bin\mysqldump.exe -u root -proot123456 --routines --triggers --single-transaction --databases group_13 > "%~dp0BackUp Database\group_13.sql"
echo Done: BackUp Database\group_13.sql
pause
