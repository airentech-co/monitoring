#!/bin/bash

echo "Building MonitorSystem for MacOS..."

# Check if running on MacOS
if [[ "$OSTYPE" != "darwin"* ]]; then
    echo "Error: This script is for MacOS only"
    exit 1
fi

# Check if Xcode is installed
if ! command -v xcodebuild &> /dev/null; then
    echo "Error: Xcode is not installed"
    echo "Please install Xcode from the App Store"
    exit 1
fi

# Check if CMake is installed
if ! command -v cmake &> /dev/null; then
    echo "Error: CMake is not installed"
    echo "Please install CMake: brew install cmake"
    exit 1
fi

# Check if Homebrew is installed
if ! command -v brew &> /dev/null; then
    echo "Installing Homebrew..."
    /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
fi

# Install dependencies
echo "Installing dependencies..."
brew install cmake pkg-config

# Create build directory
mkdir -p build
cd build

# Configure with CMake
echo "Configuring project..."
cmake .. -DCMAKE_BUILD_TYPE=Release
if [ $? -ne 0 ]; then
    echo "Error: CMake configuration failed"
    exit 1
fi

# Build the project
echo "Building project..."
make -j$(sysctl -n hw.ncpu)
if [ $? -ne 0 ]; then
    echo "Error: Build failed"
    exit 1
fi

echo "Build completed successfully!"
echo "App bundle location: build/MonitorClient.app"

# Create package
echo "Creating package..."
make package
if [ $? -ne 0 ]; then
    echo "Warning: Package creation failed"
else
    echo "Package created successfully!"
fi

cd ..
echo "Build process completed!" 