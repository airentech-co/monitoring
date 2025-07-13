# Updated Windows Monitor Client Features

This document describes the new features implemented in the updated Windows monitoring client.

## New Features

### 1. Temporary Screenshot Storage
- **Before**: Screenshots were saved permanently in a `screenshots/` directory
- **After**: Screenshots are saved to temporary files and automatically deleted after sending to server
- **Benefits**: Saves disk space, no accumulation of old screenshots

### 2. Settings.ini Configuration File
- **File**: `settings.ini` in the same directory as the executable
- **Format**: Standard INI file format
- **Sections**:
  - `[Server]`: Server configuration
  - `[Client]`: Client configuration  
  - `[Intervals]`: Monitoring intervals

#### Example settings.ini:
```ini
[Server]
ip=192.168.1.45
port=8924
username=default_user

[Client]
version=1.0
mac_address=

[Intervals]
screenshot=5
tic=30
history=120
keylog=60
usb=30
```

### 3. Username in All Requests
- **Before**: Only MAC address was sent with requests
- **After**: Username is included in all API requests
- **Format**: Added `"Username": "username"` field to all JSON requests
- **Benefits**: Better user identification and tracking

### 4. System Tray Icon
- **Icon**: Appears in Windows system tray
- **Visual Status**: 
  - Green icon: Server connected
  - Red icon: Server disconnected
- **Right-click Menu**:
  - **Status**: Shows detailed connection status
  - **Exit**: Closes the application

### 5. Connection Status Monitoring
- **Real-time Status**: Tracks connection status for all request types
- **Status Display**: Shows last successful/failed times for:
  - Screenshots
  - Tic events
  - Browser history
  - Key logs
  - USB logs
- **Error Notifications**: Shows error dialogs when connection fails

### 6. Automatic Connection Testing
- **Frequency**: Tests server connection every 60 seconds
- **Initial Test**: Tests connection on startup
- **Error Handling**: Shows error dialog if initial connection fails

## Technical Changes

### Screenshot Handling
```cpp
// Old: void takeScreenshot(const std::string& filePath)
// New: std::string takeScreenshot()
std::string screenshotPath = takeScreenshot();
sendScreenshot(screenshotPath); // Automatically deletes temp file
```

### Settings Loading
```cpp
bool loadSettings(); // Loads from settings.ini
// Updates: serverIP, serverPort, username, API_BASE_URL
```

### System Tray
```cpp
void setupSystemTray();     // Initialize tray icon
void updateTrayIcon();      // Update icon based on status
void showStatusDialog();    // Show status information
```

### Connection Status
```cpp
struct ConnectionStatus {
    bool serverConnected;
    std::string lastScreenshotStatus;
    std::string lastTicStatus;
    std::string lastHistoryStatus;
    std::string lastKeyLogStatus;
    std::string lastUSBLogStatus;
    std::string lastError;
    std::chrono::steady_clock::time_point lastSuccess;
};
```

## Building the Updated Client

### Prerequisites
- MinGW-w64 with C++11 support
- Required libraries: wininet, gdiplus, psapi, setupapi, sqlite3, jsoncpp

### Build Command
```bash
# Use the provided build script
build_updated.bat

# Or compile manually
g++ -std=c++11 -O2 -Wall MonitorClient.cpp -lwininet -lgdiplus -lpsapi -lsetupapi -lsqlite3 -ljsoncpp -lshcore -lcomctl32 -lshell32 -luser32 -lkernel32 -o MonitorClient.exe
```

### Installation
1. Copy `MonitorClient.exe` to target directory
2. Create `settings.ini` with proper server configuration
3. Run `MonitorClient.exe`
4. Check system tray for status icon

## Usage

### Starting the Client
1. Ensure `settings.ini` is in the same directory as `MonitorClient.exe`
2. Run `MonitorClient.exe`
3. The application will run in the background with a system tray icon

### Monitoring Status
- Right-click the system tray icon
- Select "Status" to view detailed connection information
- Icon color indicates connection status (green=connected, red=disconnected)

### Stopping the Client
- Right-click the system tray icon
- Select "Exit" to close the application

## Error Handling

### Connection Errors
- Initial connection failure shows error dialog
- Subsequent failures update tray icon to red
- Error details available in status dialog

### File Errors
- Missing `settings.ini` uses default settings
- Screenshot failures logged but don't stop monitoring
- Temporary file cleanup on application exit

## Logging

### Debug Log
- File: `monitor_debug.log`
- Contains detailed operation logs
- Useful for troubleshooting

### Status Information
- Available through system tray status dialog
- Shows last successful/failed times for all operations
- Displays current connection status

## Security Considerations

### Temporary Files
- Screenshots stored in system temp directory
- Automatically deleted after upload
- No persistent storage of sensitive data

### Configuration
- Settings stored in plain text INI file
- Username included in all requests
- Consider encrypting sensitive configuration if needed

## Troubleshooting

### Common Issues

1. **Tray icon not appearing**
   - Check if application has proper permissions
   - Verify window class registration

2. **Connection failures**
   - Verify server IP and port in settings.ini
   - Check network connectivity
   - Review server logs for authentication issues

3. **Build errors**
   - Ensure all required libraries are installed
   - Check MinGW installation and paths
   - Verify C++11 support

### Debug Information
- Check `monitor_debug.log` for detailed error information
- Use system tray status dialog for current state
- Monitor Windows Event Viewer for system-level errors 