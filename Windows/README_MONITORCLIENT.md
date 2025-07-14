# MonitorClient for Windows

## Overview
MonitorClient is a Windows system monitoring client that collects and sends various system activity data to a remote server. It runs in the background, provides a system tray icon for status and control, and supports manual and automatic data reporting.

## Features

- **System Tray Integration**
  - Tray icon with context menu for quick actions
  - Status display for server connection and last data sent
  - Manual trigger: "Send All Requests Now" to immediately send all monitored data
  - Exit option

- **Data Collection & Reporting**
  - **Screenshots**: Periodically captures and uploads desktop screenshots
  - **Tic Events**: Sends periodic heartbeat/tic events to the server
  - **Browser History**: Collects and uploads browser history from Chrome, Firefox, Edge, and Chromium-based browsers
  - **Key Logs**: Captures and uploads keystrokes with application context
  - **USB Device Logs**: Monitors and reports USB device connect/disconnect events

- **Configurable Intervals**
  - Screenshot, tic, browser history, key log, and USB log intervals are defined in the code (see `SCREEN_INTERVAL`, `TIC_INTERVAL`, etc.)
  - Server IP, port, and username are configurable via `settings.ini`

- **Startup Behavior**
  - On startup, all monitored data is sent within 5 seconds so the tray menu shows up-to-date status

- **Robustness**
  - Handles network/server errors gracefully
  - Logs debug information to `monitor_debug.log`
  - Memory management for log data

## Tray Menu Actions
- **Status**: Shows current connection and last data sent (disabled, for display only)
- **Send All Requests Now**: Manually trigger sending all monitored data immediately
- **Exit**: Quit the application

## Build Instructions (MinGW)

1. **Install MinGW-w64** (if not already installed)
   - Download from [https://www.mingw-w64.org/](https://www.mingw-w64.org/)
   - Ensure `mingw32-make.exe` is in your PATH

2. **Open a terminal in the project root**

3. **Build the project**
   ```sh
   cd Windows
   mingw32-make
   ```
   This will build `MonitorClient.exe` in the `Windows/` directory.

4. **Run the application**
   ```sh
   ./MonitorClient.exe
   ```

## Configuration
- Edit `settings.ini` in the `Windows/` directory to set server IP, port, and username.

## Log File
- Debug and error logs are written to `monitor_debug.log` in the build directory.

## Notes
- Requires Windows 7 or later.
- Requires network access to the configured server.
- Some features (e.g., browser history) may require the monitored browsers to be installed.

---
For more details, see comments in `MonitorClient.cpp` or contact the project maintainer. 