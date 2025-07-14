@echo off
setlocal enabledelayedexpansion

REM MonitorClient Autostart Installation Script for Windows
REM This script installs the MonitorClient for automatic startup

echo Installing MonitorClient for autostart...

REM Get the directory where this script is located
set "SCRIPT_DIR=%~dp0"
set "SCRIPT_DIR=%SCRIPT_DIR:~0,-1%"

REM If executable not found in script directory, try current directory
if not exist "%SCRIPT_DIR%\MonitorClient.exe" (
    if exist "MonitorClient.exe" (
        set "SCRIPT_DIR=%CD%"
        echo [INFO] Using current directory: %SCRIPT_DIR%
    ) else (
        echo [ERROR] MonitorClient.exe not found in %SCRIPT_DIR% or current directory
        echo Please make sure you've built the executable and run this script from the same folder.
        pause
        exit /b 1
    )
)

REM Create startup folder path
set "STARTUP_FOLDER=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup"

REM Create shortcut in startup folder
echo Creating startup shortcut...

REM Create temporary PowerShell script
echo $WshShell = New-Object -comObject WScript.Shell > "%TEMP%\create_shortcut.ps1"
echo $Shortcut = $WshShell.CreateShortcut('%STARTUP_FOLDER%\MonitorClient.lnk') >> "%TEMP%\create_shortcut.ps1"
echo $Shortcut.TargetPath = '%SCRIPT_DIR%\MonitorClient.exe' >> "%TEMP%\create_shortcut.ps1"
echo $Shortcut.WorkingDirectory = '%SCRIPT_DIR%' >> "%TEMP%\create_shortcut.ps1"
echo $Shortcut.Description = 'MonitorClient - System Monitoring Tool' >> "%TEMP%\create_shortcut.ps1"
echo $Shortcut.Save() >> "%TEMP%\create_shortcut.ps1"

REM Execute the PowerShell script
powershell -ExecutionPolicy Bypass -File "%TEMP%\create_shortcut.ps1"

REM Clean up temporary file
del "%TEMP%\create_shortcut.ps1" 2>nul

if %ERRORLEVEL% EQU 0 (
    echo [SUCCESS] MonitorClient autostart installed successfully!
    echo.
    echo [INFO] App location: %SCRIPT_DIR%\MonitorClient.exe
    echo [INFO] Startup shortcut: %STARTUP_FOLDER%\MonitorClient.lnk
    echo.
    echo [INFO] The app will now start automatically when you log in.
    echo.
    echo Useful commands:
    echo   - Start now: "%SCRIPT_DIR%\MonitorClient.exe"
    echo   - Check if running: tasklist ^| findstr MonitorClient
    echo   - Stop: taskkill /f /im MonitorClient.exe
    echo.
    echo To uninstall autostart, run: uninstall_autostart.bat
) else (
    echo [ERROR] Failed to install autostart. Please check permissions and try again.
    pause
    exit /b 1
)

pause 