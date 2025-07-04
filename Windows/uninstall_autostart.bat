@echo off
setlocal enabledelayedexpansion

REM MonitorClient Autostart Uninstallation Script for Windows
REM This script removes the MonitorClient from automatic startup

echo 🗑️  Uninstalling MonitorClient autostart...

REM Create startup folder path
set "STARTUP_FOLDER=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup"
set "SHORTCUT_PATH=%STARTUP_FOLDER%\MonitorClient.lnk"

REM Remove the startup shortcut
if exist "%SHORTCUT_PATH%" (
    echo Removing startup shortcut...
    del "%SHORTCUT_PATH%"
    echo ✅ MonitorClient autostart uninstalled successfully!
    echo.
    echo The app will no longer start automatically at login.
    echo You can still run it manually.
) else (
    echo ℹ️  MonitorClient autostart was not installed.
)

REM Check if the app is still running and offer to stop it
tasklist /FI "IMAGENAME eq MonitorClient.exe" 2>NUL | find /I /N "MonitorClient.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo.
    echo ⚠️  MonitorClient is still running.
    set /p "choice=Do you want to stop it now? (y/n): "
    if /i "!choice!"=="y" (
        taskkill /f /im MonitorClient.exe
        echo ✅ MonitorClient stopped.
    )
)

echo.
echo To reinstall autostart later, run: install_autostart.bat
pause 