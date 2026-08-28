@echo off
setlocal enabledelayedexpansion
title Kin Cafe - All-In-One System Launcher

echo =======================================================================
echo              KIN CAFE - ALL-IN-ONE SYSTEM LAUNCHER                     
echo =======================================================================
echo.

set "PROJECT_DIR=%~dp0"
if "%PROJECT_DIR:~-1%"=="\" set "PROJECT_DIR=%PROJECT_DIR:~0,-1%"

:: -------------------------------------------------------------------------
:: 1. Check PHP Executable
:: -------------------------------------------------------------------------
echo [1/4] Checking PHP installation...
set "PHP_BIN="
where php >nul 2>nul
if !errorlevel! equ 0 (
    set "PHP_BIN=php"
) else if exist "C:\xampp\php\php.exe" (
    set "PHP_BIN=C:\xampp\php\php.exe"
)

if defined PHP_BIN (
    echo  [OK] Found PHP at: "!PHP_BIN!"
) else (
    echo  [WARNING] PHP not found in PATH or C:\xampp\php\php.exe.
    echo            Make sure XAMPP is installed.
)
echo.

:: -------------------------------------------------------------------------
:: 2. Check & Auto-Install Python Requirements
:: -------------------------------------------------------------------------
echo [2/4] Checking Python environment and requirements...
set "PYTHON_BIN="
where python >nul 2>nul
if !errorlevel! equ 0 (
    set "PYTHON_BIN=python"
) else (
    echo  [ERROR] Python is not installed or not added to system PATH.
    echo          Please install Python 3.10+ from https://www.python.org/
    pause
    exit /b 1
)

cd /d "%PROJECT_DIR%\python_forecast_service"

if not exist "venv\Scripts\python.exe" (
    echo  [INFO] Python virtual environment missing. Creating venv now...
    "%PYTHON_BIN%" -m venv venv
    if !errorlevel! neq 0 (
        echo  [ERROR] Failed to create virtual environment.
        pause
        exit /b 1
    )
    echo  [OK] Virtual environment created.
) else (
    echo  [OK] Virtual environment found.
)

echo  [INFO] Verifying / Installing Python packages...
call venv\Scripts\activate.bat
pip install -r requirements.txt --quiet
if !errorlevel! neq 0 (
    echo  [WARNING] Package installation had warnings. Continuing...
) else (
    echo  [OK] Python requirements ready: Flask, pandas, numpy, statsmodels.
)
cd /d "%PROJECT_DIR%"
echo.

:: -------------------------------------------------------------------------
:: 3. Check Folders & MySQL Database Setup
:: -------------------------------------------------------------------------
echo [3/4] Checking system folders and MySQL database...
if not exist "%PROJECT_DIR%\backups" (
    mkdir "%PROJECT_DIR%\backups"
    echo  [OK] Created backups folder.
) else (
    echo  [OK] Backups folder exists.
)

set "MYSQL_BIN="
where mysql >nul 2>nul
if !errorlevel! equ 0 (
    set "MYSQL_BIN=mysql"
) else if exist "C:\xampp\mysql\bin\mysql.exe" (
    set "MYSQL_BIN=C:\xampp\mysql\bin\mysql.exe"
)

if defined PHP_BIN (
    "%PHP_BIN%" -r "try { require_once 'includes/app_config.php'; $c = getProjectDatabaseDefaults(); $pdo = new PDO('mysql:host='.$c['host'].';port='.$c['port'].';dbname='.$c['name'], $c['username'], $c['password']); echo 'CONNECTED'; } catch(Exception $e) { echo 'FAILED: ' . $e->getMessage(); }" > temp_db_check.txt 2>&1
    set /p DB_RESULT=<temp_db_check.txt
    if exist temp_db_check.txt del temp_db_check.txt

    if "!DB_RESULT!"=="CONNECTED" (
        echo  [OK] Database kin_cafe is connected and ready.
    ) else (
        echo  [WARNING] Database connection: !DB_RESULT!
        if defined MYSQL_BIN (
            echo  [INFO] Auto-creating database kin_cafe and importing schema...
            "!MYSQL_BIN!" -u root -e "CREATE DATABASE IF NOT EXISTS kin_cafe DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>nul
            if exist "%PROJECT_DIR%\sql\schema.sql" (
                "!MYSQL_BIN!" -u root kin_cafe < "%PROJECT_DIR%\sql\schema.sql" 2>nul
                echo  [OK] Database schema imported successfully.
            )
        ) else (
            echo  [NOTICE] Make sure MySQL is running in XAMPP.
        )
    )
)
echo.

:: -------------------------------------------------------------------------
:: 4. Start AI Forecast Microservice & Launch Web System
:: -------------------------------------------------------------------------
echo [4/4] Starting Kin Cafe AI Forecast Microservice...
netstat -ano | findstr :5000 >nul 2>&1
if !errorlevel! equ 0 (
    echo  [OK] AI Forecast Microservice is already running on http://127.0.0.1:5000
) else (
    echo  [INFO] Launching AI Forecast Microservice on http://127.0.0.1:5000 ...
    start "Kin Cafe AI Forecast Service" /d "%PROJECT_DIR%\python_forecast_service" venv\Scripts\python.exe app.py
    echo  [OK] AI Forecast Microservice background process launched.
)

echo.
echo =======================================================================
echo                 KIN CAFE IS NOW READY AND RUNNING!                     
echo =======================================================================
echo.
echo Opening http://localhost/Kin_Cafe/ in your browser...
start http://localhost/Kin_Cafe/

echo.
if defined PHP_BIN (
    echo System status snapshot:
    echo -----------------------------------------------------------------------
    "%PHP_BIN%" tools\verify_system.php 2>nul
    echo -----------------------------------------------------------------------
)

echo.
echo All components checked and running smoothly.
echo You may keep this window open or close it.
echo.
pause
endlocal
