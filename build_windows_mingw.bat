@echo off
echo Building MonitorSystem for Windows with MinGW-w64...

REM Add CMake to PATH
set PATH=C:\Program Files\CMake\bin;C:\msys64\mingw64\bin;%PATH%

REM Check if CMake is installed
where cmake >nul 2>nul
if %errorlevel% neq 0 (
    echo Error: CMake is not installed or not in PATH
    echo Please install CMake from https://cmake.org/download/
    pause
    exit /b 1
)

REM Check if MinGW-w64 is available
where g++ >nul 2>nul
if %errorlevel% neq 0 (
    echo Error: MinGW-w64 g++ compiler not found in PATH
    echo Please ensure MSYS2 is installed and g++ is in PATH
    pause
    exit /b 1
)

REM Set MinGW environment variables
set PATH=C:\msys64\mingw64\bin;%PATH%
set CMAKE_PREFIX_PATH=C:\msys64\mingw64

REM Create build directory
if not exist build_mingw mkdir build_mingw
cd build_mingw

REM Configure with CMake for MinGW
echo Configuring project with MinGW...
cmake .. -G "MinGW Makefiles" -DCMAKE_BUILD_TYPE=Release -DCMAKE_PREFIX_PATH=C:\msys64\mingw64
if %errorlevel% neq 0 (
    echo Error: CMake configuration failed
    pause
    exit /b 1
)

REM Build the project
echo Building project...
cmake --build . --config Release
if %errorlevel% neq 0 (
    echo Error: Build failed
    pause
    exit /b 1
)

echo Build completed successfully!
echo Executable location: build_mingw\MonitorClient.exe

REM Create installer (optional)
echo Creating installer...
cmake --build . --target package
if %errorlevel% neq 0 (
    echo Warning: Installer creation failed
) else (
    echo Installer created successfully!
)

cd ..
pause 