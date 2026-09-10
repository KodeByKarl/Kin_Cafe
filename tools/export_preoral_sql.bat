@echo off
setlocal
title Kin Cafe - Export Pre-oral SQL Dump

set "PROJECT_DIR=%~dp0.."
cd /d "%PROJECT_DIR%"

set "PHP_BIN="
where php >nul 2>nul
if %errorlevel% equ 0 (
    set "PHP_BIN=php"
) else if exist "C:\xampp\php\php.exe" (
    set "PHP_BIN=C:\xampp\php\php.exe"
) else if exist "D:\xampp\php\php.exe" (
    set "PHP_BIN=D:\xampp\php\php.exe"
)

if not defined PHP_BIN (
    echo PHP not found. Install XAMPP or add php.exe to PATH.
    exit /b 1
)

"%PHP_BIN%" tools\export_preoral_sql.php
exit /b %errorlevel%
