#!/bin/bash

# Build script for macOS Monitor Client

echo "Building macOS Monitor Client..."

# Clean previous build
rm -f MonitorClient
rm -rf MonitorClient.app

# Compile the Swift application
swiftc -parse-as-library -o MonitorClient AppDelegate.swift \
    -framework Cocoa \
    -framework CoreGraphics \
    -framework IOKit \
    -framework Security \
    -framework SystemConfiguration \
    -framework WebKit \
    -framework CoreData \
    -framework Foundation \
    -framework AppKit

if [ $? -eq 0 ]; then
    echo "✅ Compilation successful!"
    
    # Create app bundle structure
    mkdir -p MonitorClient.app/Contents/MacOS
    mkdir -p MonitorClient.app/Contents/Resources
    
    # Copy executable
    cp MonitorClient MonitorClient.app/Contents/MacOS/
    
    # Copy Info.plist
    cp Info.plist MonitorClient.app/Contents/
    
    # Make executable
    chmod +x MonitorClient.app/Contents/MacOS/MonitorClient
    
    echo "✅ App bundle created: MonitorClient.app"
    echo "📁 Location: $(pwd)/MonitorClient.app"
    echo ""
    echo "To run the app:"
    echo "  open MonitorClient.app"
    echo ""
    echo "To install system-wide:"
    echo "  sudo cp -R MonitorClient.app /Applications/"
    
else
    echo "❌ Compilation failed!"
    exit 1
fi 