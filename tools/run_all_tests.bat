@echo off
setlocal
cd /d "%~dp0.."

echo Kin Cafe - Running all PHP regression tests
echo ==========================================

set PHP=c:\xampp\php\php.exe
if not exist "%PHP%" set PHP=php

for %%F in (
  includes\test_orders_history.php
  includes\test_menu_workflow.php
  includes\test_inventory.php
  includes\test_inventory_delete.php
  includes\test_account_delete.php
  includes\test_refcounter.php
  includes\test_advanced_analytics.php
  includes\benchmark_analytics.php
) do (
  echo.
  echo --- %%F ---
  "%PHP%" "%%F"
  if errorlevel 1 (
    echo FAILED: %%F
    exit /b 1
  )
)

echo.
echo --- tools\verify_system.php ---
"%PHP%" tools\verify_system.php
if errorlevel 1 exit /b 1

echo.
echo All PHP regression tests completed successfully.
exit /b 0
