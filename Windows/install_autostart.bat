@echo off
setlocal enabledelayedexpansion

REM MonitorClient Autostart Installation Script for Windows
REM This script installs the MonitorClient for automatic startup

echo 🚀 Installing MonitorClient for autostart...

REM Get the directory where this script is located
set "SCRIPT_DIR=%~dp0"
set "SCRIPT_DIR=%SCRIPT_DIR:~0,-1%"

REM Check if the executable exists
if not exist "%SCRIPT_DIR%\MonitorClient.exe" (
    echo ❌ Error: MonitorClient.exe not found in %SCRIPT_DIR%
    echo Please make sure you've built the executable first.
    pause
    exit /b 1
)

REM Create startup folder path
set "STARTUP_FOLDER=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup"

REM Create shortcut in startup folder
echo Creating startup shortcut...
powershell -Command "$WshShell = New-Object -comObject WScript.Shell; $Shortcut = $WshShell.CreateShortcut('%STARTUP_FOLDER%\MonitorClient.lnk'); $Shortcut.TargetPath = '%SCRIPT_DIR%\MonitorClient.exe'; $Shortcut.WorkingDirectory = '%SCRIPT_DIR%'; $Shortcut.Description = 'MonitorClient - System Monitoring Tool'; $Shortcut.Save()"

if %ERRORLEVEL% EQU 0 (
    echo ✅ MonitorClient autostart installed successfully!
    echo.
    echo 📁 App location: %SCRIPT_DIR%\MonitorClient.exe
    echo 📁 Startup shortcut: %STARTUP_FOLDER%\MonitorClient.lnk
    echo.
    echo 🔄 The app will now start automatically when you log in.
    echo.
    echo Useful commands:
    echo   • Start now: "%SCRIPT_DIR%\MonitorClient.exe"
    echo   • Check if running: tasklist ^| findstr MonitorClient
    echo   • Stop: taskkill /f /im MonitorClient.exe
    echo.
    echo To uninstall autostart, run: uninstall_autostart.bat
) else (
    echo ❌ Failed to install autostart. Please check permissions and try again.
    pause
    exit /b 1
)

pause 