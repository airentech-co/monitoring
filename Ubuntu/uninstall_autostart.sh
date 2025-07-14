#!/bin/bash

# MonitorClient Autostart Uninstallation Script for Ubuntu/Linux
# This script removes the MonitorClient from automatic startup

echo "🗑️  Uninstalling MonitorClient autostart..."

# Define file paths
AUTOSTART_DIR="$HOME/.config/autostart"
DESKTOP_FILE="$AUTOSTART_DIR/monitorclient.desktop"
SYSTEMD_USER_DIR="$HOME/.config/systemd/user"
SERVICE_FILE="$SYSTEMD_USER_DIR/monitorclient.service"

# Remove desktop entry
if [ -f "$DESKTOP_FILE" ]; then
    echo "Removing desktop entry..."
    rm -f "$DESKTOP_FILE"
    echo "✅ Desktop entry removed."
else
    echo "ℹ️  Desktop entry not found."
fi

# Remove systemd service if it exists
if [ -f "$SERVICE_FILE" ]; then
    echo "Stopping and removing systemd service..."
    systemctl --user stop monitorclient.service 2>/dev/null
    systemctl --user disable monitorclient.service 2>/dev/null
    rm -f "$SERVICE_FILE"
    systemctl --user daemon-reload
    echo "✅ Systemd service removed."
else
    echo "ℹ️  Systemd service not found."
fi

# Check if the app is still running and offer to stop it
if pgrep -f "MonitorClient" > /dev/null; then
    echo ""
    echo "⚠️  MonitorClient is still running."
    read -p "Do you want to stop it now? (y/n): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        pkill -f "MonitorClient"
        echo "✅ MonitorClient stopped."
    fi
fi

echo ""
echo "✅ MonitorClient autostart uninstalled successfully!"
echo ""
echo "The app will no longer start automatically at login."
echo "You can still run it manually."
echo ""
echo "To reinstall autostart later, run: ./install_autostart.sh" 