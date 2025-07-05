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
    
    # Create entitlements file for proper permissions
    cat > MonitorClient.app/Contents/entitlements.plist << 'EOF'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>com.apple.security.automation.apple-events</key>
    <true/>
    <key>com.apple.security.device.audio-input</key>
    <true/>
    <key>com.apple.security.device.camera</key>
    <true/>
    <key>com.apple.security.files.user-selected.read-write</key>
    <true/>
    <key>com.apple.security.network.client</key>
    <true/>
    <key>com.apple.security.network.server</key>
    <true/>
    <key>com.apple.security.personal-information.addressbook</key>
    <true/>
    <key>com.apple.security.personal-information.calendars</key>
    <true/>
    <key>com.apple.security.personal-information.location</key>
    <true/>
    <key>com.apple.security.personal-information.photos-library</key>
    <true/>
    <key>com.apple.security.temporary-exception.apple-events</key>
    <true/>
    <key>com.apple.security.temporary-exception.files.home-relative-path.read-write</key>
    <array>
        <string>/Library/</string>
        <string>/Desktop/</string>
        <string>/Documents/</string>
        <string>/Downloads/</string>
    </array>
</dict>
</plist>
EOF
    
    # Make executable
    chmod +x MonitorClient.app/Contents/MacOS/MonitorClient
    
    # Code sign the app (if developer certificate is available)
    echo "🔐 Attempting to code sign the app..."
    
    # Check if we have a developer certificate
    if security find-identity -v -p codesigning | grep -q "Developer ID Application"; then
        CERT_ID=$(security find-identity -v -p codesigning | grep "Developer ID Application" | head -1 | awk '{print $2}' | tr -d '"')
        echo "Found certificate: $CERT_ID"
        
        # Sign the executable
        codesign --force --deep --sign "$CERT_ID" --entitlements MonitorClient.app/Contents/entitlements.plist MonitorClient.app/Contents/MacOS/MonitorClient
        
        # Sign the app bundle
        codesign --force --deep --sign "$CERT_ID" --entitlements MonitorClient.app/Contents/entitlements.plist MonitorClient.app
        
        echo "✅ App signed successfully!"
    else
        echo "⚠️  No developer certificate found. App will not be signed."
        echo "   This may cause permission issues. Consider:"
        echo "   1. Installing Xcode and accepting developer agreement"
        echo "   2. Creating a self-signed certificate"
        echo "   3. Running with reduced security settings"
        
        # Create a self-signed certificate for testing
        echo "🔧 Creating self-signed certificate for testing..."
        security create-keychain -p "tempkeychain" build.keychain
        security default-keychain -s build.keychain
        security unlock-keychain -p "tempkeychain" build.keychain
        security set-keychain-settings -t 3600 -l ~/Library/Keychains/build.keychain
        
        # Create certificate
        security create-certificate-signing-request -o MonitorClient.csr -k build.keychain -s "MonitorClient Developer"
        security add-trusted-cert -d -r trustRoot -k build.keychain MonitorClient.csr
        
        # Sign with self-signed certificate
        codesign --force --deep --sign "MonitorClient Developer" --entitlements MonitorClient.app/Contents/entitlements.plist MonitorClient.app
        
        echo "✅ App signed with self-signed certificate!"
    fi
    
    echo "✅ App bundle created: MonitorClient.app"
    echo "📁 Location: $(pwd)/MonitorClient.app"
    echo "📝 Log file will be created at: $(pwd)/MonitorClient.log"
    echo ""
    echo "🔧 IMPORTANT: Before running, ensure proper permissions:"
    echo "   1. Go to System Preferences > Security & Privacy > Privacy"
    echo "   2. Add MonitorClient.app to:"
    echo "      - Accessibility"
    echo "      - Screen Recording"
    echo "      - Full Disk Access (for browser history)"
    echo "   3. If using self-signed certificate, also add to:"
    echo "      - Developer Tools"
    echo ""
    echo "To run the app:"
    echo "  ./launch_monitor.sh"
    echo "  or"
    echo "  open MonitorClient.app"
    echo ""
    echo "To monitor logs:"
    echo "  tail -f $(pwd)/MonitorClient.log"
    echo ""
    echo "To install system-wide:"
    echo "  sudo cp -R MonitorClient.app /Applications/"
    
else
    echo "❌ Compilation failed!"
    exit 1
fi 