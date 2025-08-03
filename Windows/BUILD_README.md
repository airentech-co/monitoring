# MonitorClient Windows Build Instructions

## Prerequisites

### 1. MSYS2 Installation
- Download and install MSYS2 from https://www.msys2.org/
- Install to the default location: `C:\msys64`
- After installation, open MSYS2 terminal and update the system:
  ```bash
  pacman -Syu
  ```

### 2. Required Packages
The build script will automatically install these packages, but you can also install them manually:
```bash
pacman -S mingw-w64-x86_64-gcc
pacman -S mingw-w64-x86_64-cmake
pacman -S mingw-w64-x86_64-make
pacman -S mingw-w64-x86_64-sqlite3
pacman -S mingw-w64-x86_64-jsoncpp
pacman -S mingw-w64-x86_64-pkg-config
```

## Building the Application

### Option 1: Using the Build Script (Recommended)
1. Open Command Prompt as Administrator
2. Navigate to the Windows folder
3. Run the build script:
   ```cmd
   build.bat
   ```

### Option 2: Manual Build
1. Open MSYS2 MinGW 64-bit terminal
2. Navigate to the Windows folder
3. Create build directory:
   ```bash
   mkdir build
   cd build
   ```
4. Configure with CMake:
   ```bash
   cmake .. -G "MinGW Makefiles" -DCMAKE_BUILD_TYPE=Release
   ```
5. Build the project:
   ```bash
   cmake --build . --config Release
   ```

## Build Output

After successful build, you'll find:
- `MonitorClient.exe` - The main executable
- `settings.ini` - Configuration file (if it exists)

## Configuration

### settings.ini
Create or modify `settings.ini` with your server configuration:
```ini
[Server]
ip=192.168.1.45
port=8924
username=your_username
```

## Running the Application

1. Ensure `settings.ini` is properly configured
2. Run `MonitorClient.exe`
3. The application will start in the system tray
4. Right-click the tray icon for options:
   - Status information
   - Send all requests now
   - Exit

## Features

The Windows MonitorClient includes:
- **Screenshot Capture**: Automatic screen captures at configurable intervals
- **Browser History**: Chrome, Firefox, and Edge history monitoring
- **Key Logging**: Keyboard activity monitoring with application context
- **USB Device Monitoring**: USB device connection/disconnection tracking
- **System Tray Integration**: Background operation with tray icon
- **Connection Status**: Real-time server connection monitoring
- **Windows Notifications**: Toast notifications for events

## Troubleshooting

### Common Issues

1. **MSYS2 not found**
   - Ensure MSYS2 is installed at `C:\msys64`
   - Reinstall MSYS2 if necessary

2. **Build tools not found**
   - Run the build script as Administrator
   - Ensure MSYS2 packages are installed

3. **Dependencies not found**
   - The build script will install required packages automatically
   - If manual installation is needed, use the pacman commands listed above

4. **CMake configuration fails**
   - Check that all required packages are installed
   - Ensure you're using the correct MSYS2 environment

### Debug Information

The application creates a debug log file (`monitor_debug.log`) with detailed information about:
- Connection attempts
- Data collection activities
- Error messages
- USB device events

## Cleanup

To remove build artifacts and temporary files:
```cmd
clean.bat
```

## File Structure

```
Windows/
├── MonitorClient.cpp          # Main source code
├── CMakeLists.txt            # CMake configuration
├── settings.ini              # Configuration file
├── build.bat                 # Build script
├── clean.bat                 # Cleanup script
├── BUILD_README.md           # This file
└── build/                    # Build directory (created during build)
    └── MonitorClient.exe     # Generated executable
```

## Dependencies

- **SQLite3**: Browser history database access
- **JsonCpp**: JSON parsing and generation
- **Windows API**: System monitoring and UI
- **GDI+**: Screenshot capture
- **WinINet**: HTTP communication

## Security Notes

- The application requires administrative privileges for some monitoring features
- USB device monitoring requires device notification access
- Browser history access requires appropriate permissions
- The application runs in the background and may be flagged by antivirus software 