# MonitorClient Changes

## Overview
The MonitorClient has been updated to provide better control over monitoring features and improved system tray functionality.

## Key Changes

### 1. Screenshot Control
- **Before**: Screenshots were taken automatically every 5 seconds
- **After**: Screenshots are only taken when explicitly requested via:
  - System tray menu: "Take Screenshot"
  - Server request (when implemented)

### 2. Enhanced System Tray
- **Detailed Status Display**: Shows comprehensive information including:
  - Server IP and port
  - Client name, local IP, and MAC address
  - Connection status (Connected/Disconnected)
  - Last sent times for each data type (screenshot, keylog, browser history, USB logs)
  - Monitoring feature status (Enabled/Disabled)

- **Menu Items**:
  - "Take Screenshot" - Manually trigger screenshot
  - "Configure..." - Open configuration dialog
  - "Send All Data" - Send all collected data to server
  - No exit button (application stays running)

### 3. Configuration Dialog
- **Server Settings**:
  - Server IP address
  - Server port
  - Client name
- **Features**:
  - Test connection button
  - Save settings to both system config and local settings.ini
  - Validation of required fields

### 4. Monitoring Control
- Individual enable/disable controls for each monitoring feature:
  - Screenshots (disabled by default)
  - Keylogging (enabled by default)
  - Browser History (enabled by default)
  - USB Monitoring (enabled by default)

### 5. Server Communication
- **HTTP 200 Status**: Uses HTTP 200 response status instead of tic events for server communication
- **Success Tracking**: Tracks last successful send time for each data type
- **Connection Testing**: Improved connection testing with status code validation

## Usage

### System Tray
- **Double-click**: Test server connection
- **Right-click**: Access menu with all options
- **Status**: Hover to see comprehensive status information including:
  - Server IP and port
  - Client information (name, IP, MAC)
  - Connection status
  - Last sent times for all data types
  - Monitoring feature status

### Configuration
1. Right-click system tray icon
2. Select "Configure..."
3. Enter server IP, port, and client name
4. Test connection if needed
5. Click "Save" to apply changes

### Manual Screenshot
1. Right-click system tray icon
2. Select "Take Screenshot"
3. Screenshot will be taken and sent to server

## Files Modified
- `MonitorClient.h` - Added new slots and member variables
- `MonitorClient.cpp` - Implemented new functionality
- `functions.h` - Added getClientName() function
- `functions.cpp` - Implemented getClientName()
- `settings.ini` - Updated with new client name setting
- `CMakeLists.txt` - Added ConfigDialog.cpp to build

## New Files
- `ConfigDialog.h` - Configuration dialog header
- `ConfigDialog.cpp` - Configuration dialog implementation

## Build Instructions
```bash
cd Ubuntu
mkdir build
cd build
cmake ..
make
```

## Configuration
Settings are stored in:
- System config: `~/.config/monitorclient/settings.ini`
- Local config: `./settings.ini` (for backward compatibility) 