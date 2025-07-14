#!/bin/bash

# MonitorClient Autostart Installation Script for Ubuntu/Linux
# This script installs the MonitorClient for automatic startup

# Get the directory where this script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "🚀 Installing MonitorClient for autostart..."

# Check if the executable exists
if [ ! -f "$SCRIPT_DIR/MonitorClient" ]; then
    echo "❌ Error: MonitorClient executable not found in $SCRIPT_DIR"
    echo "Please make sure you've built the executable first."
    exit 1
fi

# Make sure the executable is executable
chmod +x "$SCRIPT_DIR/MonitorClient"

# Create autostart directory
AUTOSTART_DIR="$HOME/.config/autostart"
mkdir -p "$AUTOSTART_DIR"

# Create desktop entry file
DESKTOP_FILE="$AUTOSTART_DIR/monitorclient.desktop"

cat > "$DESKTOP_FILE" << EOF
[Desktop Entry]
Type=Application
Name=MonitorClient
Comment=System Monitoring Tool
Exec=$SCRIPT_DIR/MonitorClient
Path=$SCRIPT_DIR
Terminal=false
Hidden=false
X-GNOME-Autostart-enabled=true
EOF

# Set proper permissions
chmod 644 "$DESKTOP_FILE"

# For systemd-based systems, also create a user service
if command -v systemctl >/dev/null 2>&1; then
    SYSTEMD_USER_DIR="$HOME/.config/systemd/user"
    mkdir -p "$SYSTEMD_USER_DIR"
    
    SERVICE_FILE="$SYSTEMD_USER_DIR/monitorclient.service"
    
    cat > "$SERVICE_FILE" << EOF
[Unit]
Description=MonitorClient System Monitoring
After=network.target

[Service]
Type=simple
ExecStart=$SCRIPT_DIR/MonitorClient
WorkingDirectory=$SCRIPT_DIR
Restart=always
RestartSec=10
StandardOutput=append:$SCRIPT_DIR/MonitorClient.log
StandardError=append:$SCRIPT_DIR/MonitorClient.log

[Install]
WantedBy=default.target
EOF
    
    # Enable and start the service
    systemctl --user daemon-reload
    systemctl --user enable monitorclient.service
    systemctl --user start monitorclient.service
    
    echo "✅ Systemd service installed and started!"
fi

if [ $? -eq 0 ]; then
    echo "✅ MonitorClient autostart installed successfully!"
    echo ""
    echo "📁 App location: $SCRIPT_DIR/MonitorClient"
    echo "📁 Desktop entry: $DESKTOP_FILE"
    if [ -f "$SERVICE_FILE" ]; then
        echo "📁 Systemd service: $SERVICE_FILE"
    fi
    echo "📝 Log file: $SCRIPT_DIR/MonitorClient.log"
    echo ""
    echo "🔄 The app will now start automatically when you log in."
    echo ""
    echo "Useful commands:"
    echo "  • Start now: $SCRIPT_DIR/MonitorClient"
    echo "  • Check if running: ps aux | grep MonitorClient"
    echo "  • Stop: pkill -f MonitorClient"
    if command -v systemctl >/dev/null 2>&1; then
        echo "  • Systemd status: systemctl --user status monitorclient.service"
        echo "  • Systemd stop: systemctl --user stop monitorclient.service"
        echo "  • Systemd start: systemctl --user start monitorclient.service"
    fi
    echo "  • View logs: tail -f $SCRIPT_DIR/MonitorClient.log"
    echo ""
    echo "To uninstall autostart, run: ./uninstall_autostart.sh"
else
    echo "❌ Failed to install autostart. Please check permissions and try again."
    exit 1
fi 