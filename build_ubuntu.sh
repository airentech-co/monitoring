#!/bin/bash

echo "Building MonitorSystem for Ubuntu/Linux..."

# Check if running on Ubuntu/Linux
if [[ "$OSTYPE" != "linux-gnu"* ]]; then
    echo "Error: This script is for Ubuntu/Linux only"
    exit 1
fi

# Check if CMake is installed
if ! command -v cmake &> /dev/null; then
    echo "Error: CMake is not installed"
    echo "Please install CMake: sudo apt-get install cmake"
    exit 1
fi

# Check if Qt5 is installed
if ! pkg-config --exists Qt5Core; then
    echo "Error: Qt5 is not installed"
    echo "Please install Qt5: sudo apt-get install qt5-default qtbase5-dev"
    exit 1
fi

# Check if required libraries are installed
echo "Checking dependencies..."
sudo apt-get update
sudo apt-get install -y \
    build-essential \
    cmake \
    qt5-default \
    qtbase5-dev \
    libqt5sql5-sqlite \
    libsqlite3-dev \
    libudev-dev \
    libx11-dev \
    libxext-dev \
    libxrandr-dev \
    pkg-config

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
make -j$(nproc)
if [ $? -ne 0 ]; then
    echo "Error: Build failed"
    exit 1
fi

echo "Build completed successfully!"
echo "Executable location: build/MonitorClient"

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