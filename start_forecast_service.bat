@echo off
setlocal

cd /d "%~dp0python_forecast_service"

if not exist "venv\Scripts\python.exe" (
    echo Creating Python virtual environment...
    python -m venv venv
    if errorlevel 1 (
        echo Failed to create virtual environment. Ensure Python 3.10+ is installed and on PATH.
        exit /b 1
    )
)

echo Activating virtual environment...
call venv\Scripts\activate.bat

echo Installing/updating dependencies...
pip install -r requirements.txt
if errorlevel 1 (
    echo Failed to install dependencies.
    exit /b 1
)

echo Starting Kin Cafe Forecast Service on http://127.0.0.1:5000
python app.py

endlocal
