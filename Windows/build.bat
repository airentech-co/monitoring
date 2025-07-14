@echo off
setlocal enabledelayedexpansion

echo ========================================
echo MonitorClient Windows Build Script
echo ========================================
echo.

:: Set MSYS2 paths
set MSYS2_PATH=C:\msys64
set MINGW64_PATH=%MSYS2_PATH%\mingw64
set MSYS2_BIN=%MSYS2_PATH%\usr\bin

:: Check if MSYS2 is installed
if not exist "%MSYS2_PATH%" (
    echo ERROR: MSYS2 not found at %MSYS2_PATH%
    echo Please install MSYS2 from https://www.msys2.org/
    echo and run this script again.
    pause
    exit /b 1
)

echo MSYS2 found at: %MSYS2_PATH%
echo.

:: Add MSYS2 to PATH for this session
set PATH=%MINGW64_PATH%\bin;%MSYS2_BIN%;%PATH%

:: Check if required tools are available
echo Checking build tools...

:: First, try to install packages if they're not available
echo Installing required packages...
%MSYS2_BIN%\pacman.exe -S --noconfirm --needed ^
    mingw-w64-x86_64-gcc ^
    mingw-w64-x86_64-cmake ^
    mingw-w64-x86_64-make ^
    mingw-w64-x86_64-sqlite3 ^
    mingw-w64-x86_64-jsoncpp

if %errorlevel% neq 0 (
    echo ERROR: Failed to install required packages.
    echo Please run the following command manually in MSYS2 terminal:
    echo pacman -S mingw-w64-x86_64-gcc mingw-w64-x86_64-cmake mingw-w64-x86_64-make mingw-w64-x86_64-sqlite3 mingw-w64-x86_64-jsoncpp
    pause
    exit /b 1
)

:: Now check if tools are available using full paths
if not exist "%MINGW64_PATH%\bin\gcc.exe" (
    echo ERROR: GCC not found at %MINGW64_PATH%\bin\gcc.exe
    pause
    exit /b 1
)

if not exist "%MINGW64_PATH%\bin\cmake.exe" (
    echo ERROR: CMake not found at %MINGW64_PATH%\bin\cmake.exe
    pause
    exit /b 1
)

if not exist "%MINGW64_PATH%\bin\mingw32-make.exe" (
    echo ERROR: Make not found at %MINGW64_PATH%\bin\mingw32-make.exe
    pause
    exit /b 1
)

echo Build tools found successfully.
echo.

:: Create build directory
if not exist "build" (
    echo Creating build directory...
    mkdir build
)

:: Change to build directory
cd build

:: Configure with CMake
echo Configuring with CMake...
cmake .. -G "MinGW Makefiles" ^
    -DCMAKE_BUILD_TYPE=Release ^
    -DCMAKE_C_COMPILER=C:/msys64/mingw64/bin/gcc.exe ^
    -DCMAKE_CXX_COMPILER=C:/msys64/mingw64/bin/g++.exe ^
    -DCMAKE_MAKE_PROGRAM=C:/msys64/mingw64/bin/mingw32-make.exe

if %errorlevel% neq 0 (
    echo ERROR: CMake configuration failed.
    pause
    exit /b 1
)

echo CMake configuration successful.
echo.

:: Build the project
echo Building MonitorClient...
cmake --build . --config Release

if %errorlevel% neq 0 (
    echo ERROR: Build failed.
    pause
    exit /b 1
)

echo.
echo ========================================
echo Build completed successfully!
echo ========================================
echo.

:: Check if executable was created
if exist "MonitorClient.exe" (
    echo Executable created: MonitorClient.exe
    echo Size: 
    dir MonitorClient.exe | find "MonitorClient.exe"
    echo.
    
    :: Copy executable to parent directory
    copy MonitorClient.exe ..\MonitorClient.exe >nul
    echo Executable copied to: ..\MonitorClient.exe
    echo.
    
    :: Copy settings.ini if it doesn't exist in parent directory
    if not exist "..\settings.ini" (
        if exist "..\settings.ini" (
            copy ..\settings.ini ..\settings.ini >nul
            echo Settings file copied.
        )
    )
    
    echo.
    echo ========================================
    echo Build Summary:
    echo ========================================
    echo - Executable: MonitorClient.exe
    echo - Build Type: Release
    echo - Compiler: MinGW-w64 GCC
    echo - Dependencies: SQLite3, JsonCpp
    echo.
    echo To run the application:
    echo 1. Ensure settings.ini is configured
    echo 2. Run: MonitorClient.exe
    echo.
    echo The application will run in the system tray.
    echo Right-click the tray icon for options.
    
) else (
    echo ERROR: Executable not found after build.
    echo Please check the build output for errors.
)

echo.
echo Press any key to exit...
pause >nul 