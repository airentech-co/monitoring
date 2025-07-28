#!/bin/bash

# Ubuntu MonitorClient Distribution Script
# Version: 1.0

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Configuration
PACKAGE_NAME="monitorclient"
PACKAGE_VERSION="1.0"
PACKAGE_ARCH="amd64"
DIST_DIR="dist"

print_status "Creating Ubuntu MonitorClient Distribution..."

# Get the directory where this script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Check if we're in the right directory
if [[ ! -f "CMakeLists.txt" ]]; then
    print_error "CMakeLists.txt not found. Please run this script from the Ubuntu MonitorClient directory."
    exit 1
fi

# Create distribution directory
print_status "Creating distribution directory..."
rm -rf "$DIST_DIR"
mkdir -p "$DIST_DIR"

# Make scripts executable
chmod +x install.sh
chmod +x create_deb_package.sh

# Copy source files to distribution
print_status "Copying source files..."
cp -r *.cpp *.h *.ini *.txt CMakeLists.txt "$DIST_DIR/"
cp -r utils "$DIST_DIR/"
cp install.sh "$DIST_DIR/"
cp create_deb_package.sh "$DIST_DIR/"

# Create README for distribution
cat > "$DIST_DIR/README.md" << 'EOF'
# Ubuntu MonitorClient v1.0

A comprehensive system monitoring client for Ubuntu that provides screenshot monitoring, USB device monitoring, system information gathering, and network communication to a backend server.

## Features

- **Screenshot Monitoring**: Automatic screenshot capture at configurable intervals
- **USB Device Monitoring**: Real-time detection and monitoring of USB devices
- **System Information**: Comprehensive system and desktop environment information
- **Network Communication**: Secure data transmission to backend server
- **Browser History Monitoring**: Support for multiple browsers (Firefox, Chrome, Edge, etc.)
- **Keyboard Activity Monitoring**: Keylogging capabilities (requires root privileges)
- **System Integration**: Desktop shortcuts, autostart, and systemd service

## Installation Options

### Option 1: Quick Install Script
```bash
chmod +x install.sh
./install.sh
```

### Option 2: Debian Package
```bash
chmod +x create_deb_package.sh
./create_deb_package.sh
sudo dpkg -i monitorclient_1.0_amd64.deb
sudo apt-get install -f  # Install dependencies if needed
```

## Configuration

Edit `/opt/monitorclient/config/settings.ini` to configure:
- Server IP and port
- Username
- Monitoring intervals
- Client version

## Usage

### Manual Start
```bash
/opt/monitorclient/MonitorClient
```

### Desktop Shortcut
Search for "MonitorClient" in the Applications menu

### Systemd Service
```bash
systemctl --user start monitorclient.service
systemctl --user enable monitorclient.service  # Enable autostart
```

## System Requirements

- Ubuntu 20.04 LTS or later
- Qt5 development libraries
- X11 development libraries
- udev development libraries
- SQLite3 development libraries

## Dependencies

The installation script will automatically install:
- build-essential
- cmake
- pkg-config
- qtbase5-dev and related packages
- libudev-dev
- libx11-dev
- libsqlite3-dev
- libdbus-1-dev

## File Locations

- **Executable**: `/opt/monitorclient/MonitorClient`
- **Configuration**: `/opt/monitorclient/config/settings.ini`
- **Desktop Shortcut**: `/usr/share/applications/MonitorClient.desktop`
- **Autostart**: `/etc/xdg/autostart/MonitorClient.desktop`
- **Systemd Service**: `/etc/systemd/user/monitorclient.service`
- **Logs**: `/opt/monitorclient/logs/`

## Uninstallation

### If installed via script:
```bash
/opt/monitorclient/uninstall.sh
```

### If installed via package:
```bash
sudo dpkg -r monitorclient
```

## Troubleshooting

### Keyboard Monitoring Permission Denied
Keyboard monitoring requires root privileges or input device group membership:
```bash
sudo usermod -a -G input $USER
# Log out and log back in
```

### Connection Refused
Ensure the backend server is running and the IP/port in settings.ini is correct.

### Screenshot Issues
Ensure the application has access to the X11 display server.

## License

This software is provided as-is for monitoring purposes.

## Support

For issues and support, check the logs in `/opt/monitorclient/logs/`.
EOF

# Create a simple build script
cat > "$DIST_DIR/build.sh" << 'EOF'
#!/bin/bash

# Simple build script for Ubuntu MonitorClient

set -e

echo "Building Ubuntu MonitorClient..."

# Check dependencies
if ! command -v cmake &> /dev/null; then
    echo "Installing dependencies..."
    sudo apt update
    sudo apt install -y build-essential cmake pkg-config \
        qtbase5-dev qtbase5-dev-tools \
        libqt5sql5-sqlite libqt5network5t64 libqt5dbus5t64 \
        libqt5widgets5t64 libqt5core5t64 \
        libudev-dev libx11-dev libxext-dev libxrandr-dev \
        libsqlite3-dev libdbus-1-3 libdbus-1-dev
fi

# Build
mkdir -p build
cd build
cmake ..
make -j$(nproc)

echo "Build completed successfully!"
echo "Run: ./build/MonitorClient"
EOF

chmod +x "$DIST_DIR/build.sh"

# Create a version info file
cat > "$DIST_DIR/VERSION" << EOF
Ubuntu MonitorClient v${PACKAGE_VERSION}
Build Date: $(date)
Architecture: ${PACKAGE_ARCH}
Qt Version: 5.15.13
EOF

# Create a checksum file
print_status "Creating checksums..."
cd "$DIST_DIR"
find . -type f -name "*.cpp" -o -name "*.h" -o -name "*.ini" -o -name "*.txt" -o -name "*.sh" -o -name "*.md" | sort | xargs sha256sum > checksums.txt

# Create distribution archive
print_status "Creating distribution archive..."
cd ..
tar -czf "${PACKAGE_NAME}_${PACKAGE_VERSION}_source.tar.gz" "$DIST_DIR"

print_success "Distribution created successfully!"

echo ""
echo "=========================================="
echo "  Distribution Package Complete!"
echo "=========================================="
echo ""
echo "Distribution files created:"
echo "- Source archive: ${PACKAGE_NAME}_${PACKAGE_VERSION}_source.tar.gz"
echo "- Distribution directory: $DIST_DIR/"
echo ""
echo "Contents of distribution:"
echo "- Source code (C++, headers, CMakeLists.txt)"
echo "- Configuration files (settings.ini)"
echo "- Installation scripts (install.sh, create_deb_package.sh)"
echo "- Documentation (README.md, VERSION)"
echo "- Build script (build.sh)"
echo "- Checksums (checksums.txt)"
echo ""
echo "To create a Debian package:"
echo "cd $DIST_DIR"
echo "./create_deb_package.sh"
echo ""
echo "To install directly:"
echo "cd $DIST_DIR"
echo "./install.sh"
echo ""
echo "Distribution ready for publishing!"
echo "" 