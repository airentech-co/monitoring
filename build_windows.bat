@echo off
echo Building MonitorSystem for Windows...

REM Check if CMake is installed
where cmake >nul 2>nul
if %errorlevel% neq 0 (
    echo Error: CMake is not installed or not in PATH
    echo Please install CMake from https://cmake.org/download/
    pause
    exit /b 1
)

REM Check if Visual Studio is available
where cl >nul 2>nul
if %errorlevel% neq 0 (
    echo Warning: Visual Studio compiler not found in PATH
    echo Trying to find Visual Studio...
    
    REM Try to find Visual Studio
    if exist "C:\Program Files (x86)\Microsoft Visual Studio\2019\Community\VC\Auxiliary\Build\vcvars64.bat" (
        call "C:\Program Files (x86)\Microsoft Visual Studio\2019\Community\VC\Auxiliary\Build\vcvars64.bat"
    ) else if exist "C:\Program Files\Microsoft Visual Studio\2022\Community\VC\Auxiliary\Build\vcvars64.bat" (
        call "C:\Program Files\Microsoft Visual Studio\2022\Community\VC\Auxiliary\Build\vcvars64.bat"
    ) else (
        echo Error: Visual Studio not found. Please install Visual Studio 2019 or 2022
        pause
        exit /b 1
    )
)

REM Create build directory
if not exist build mkdir build
cd build

REM Configure with CMake
echo Configuring project...
cmake .. -G "Visual Studio 16 2019" -A x64 -DCMAKE_BUILD_TYPE=Release
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
echo Executable location: build\bin\Release\MonitorClient.exe

REM Create installer
echo Creating installer...
cmake --build . --target package
if %errorlevel% neq 0 (
    echo Warning: Installer creation failed
) else (
    echo Installer created successfully!
)

cd ..
pause 