@echo off
echo ========================================
echo MonitorClient Windows Clean Script
echo ========================================
echo.

:: Remove build directory
if exist "build" (
    echo Removing build directory...
    rmdir /s /q build
    echo Build directory removed.
) else (
    echo Build directory not found.
)

:: Remove temporary files
echo Removing temporary files...
if exist "temp_chrome_history.db" del /q temp_chrome_history.db
if exist "temp_firefox_history.db" del /q temp_firefox_history.db
if exist "temp_edge_history.db" del /q temp_edge_history.db
if exist "monitor_debug.log" del /q monitor_debug.log
if exist "screenshot_*.jpg" del /q screenshot_*.jpg

:: Remove executable
if exist "MonitorClient.exe" (
    echo Removing MonitorClient.exe...
    del /q MonitorClient.exe
    echo Executable removed.
) else (
    echo MonitorClient.exe not found.
)

echo.
echo Cleanup completed!
echo.
pause 