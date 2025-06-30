#!/bin/bash

# MonitorClient Launch Script
# This script launches the MonitorClient app with proper configuration

# Get the directory where this script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Change to the script directory (where the app bundle is located)
cd "$SCRIPT_DIR"

# Check if the app bundle exists
if [ ! -d "MonitorClient.app" ]; then
    echo "Error: MonitorClient.app not found in $(pwd)"
    echo "Please make sure you're running this script from the MacOS directory"
    exit 1
fi

# Check if the executable exists
if [ ! -f "MonitorClient.app/Contents/MacOS/MonitorClient" ]; then
    echo "Error: MonitorClient executable not found"
    echo "Please rebuild the app using: ./build.sh"
    exit 1
fi

echo "Launching MonitorClient..."
echo "App location: $(pwd)/MonitorClient.app"
echo "Executable: $(pwd)/MonitorClient.app/Contents/MacOS/MonitorClient"
echo "Log file location: $(pwd)/MonitorClient.log"

# Launch the app using the open command (recommended for GUI apps)
open MonitorClient.app

# Alternative: Run the executable directly (for debugging)
# Uncomment the line below if you want to run it directly and see console output
# ./MonitorClient.app/Contents/MacOS/MonitorClient

echo ""
echo "✅ MonitorClient launched successfully!"
echo ""
echo "📝 Log file location: $(pwd)/MonitorClient.log"
echo ""
echo "Useful commands:"
echo "  • Monitor logs in real-time: tail -f $(pwd)/MonitorClient.log"
echo "  • View recent logs: tail -20 $(pwd)/MonitorClient.log"
echo "  • Check if app is running: ps aux | grep MonitorClient"
echo "  • Stop the app: pkill -f MonitorClient" 