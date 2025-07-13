@echo off
echo Building Updated Monitor Client...

REM Set MinGW paths (adjust these paths according to your MinGW installation)
set MINGW_PATH=C:\mingw64
set PATH=%MINGW_PATH%\bin;%PATH%

REM Create build directory
if not exist build mkdir build
cd build

REM Compile with all necessary libraries
g++ -std=c++11 -O2 -Wall ^
    -I../include ^
    -I%MINGW_PATH%/include ^
    -I%MINGW_PATH%/include/jsoncpp ^
    -I%MINGW_PATH%/include/sqlite3 ^
    ../MonitorClient.cpp ^
    -L%MINGW_PATH%/lib ^
    -L%MINGW_PATH%/lib/jsoncpp ^
    -L%MINGW_PATH%/lib/sqlite3 ^
    -lwininet -lgdiplus -lpsapi -lsetupapi -lsqlite3 -ljsoncpp -lshcore -lcomctl32 -lshell32 -luser32 -lkernel32 ^
    -o MonitorClient.exe

if %ERRORLEVEL% EQU 0 (
    echo Build successful!
    echo MonitorClient.exe created in build directory
    echo.
    echo Copy settings.ini to the build directory and run MonitorClient.exe
) else (
    echo Build failed!
    echo Please check your MinGW installation and library paths
)

cd ..
pause 