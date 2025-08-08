#!/bin/bash

# Remove old build directory
rm -rf build

# Create new build directory
mkdir build
cd build

# Configure with CMake
cmake ..

# Build
make -j$(nproc)

echo "Build complete. Executable is in build/MonitorClient"
