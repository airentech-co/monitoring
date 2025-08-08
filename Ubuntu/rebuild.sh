#!/bin/bash

# Remove old build directory
rm -rf build

# Create new build directory and enter it
mkdir build && cd build

# Configure with CMake, forcing system libraries
export CMAKE_PREFIX_PATH=/usr:/usr/local
cmake .. \
    -DCMAKE_BUILD_TYPE=Debug \
    -DCMAKE_CXX_FLAGS="-g -O0" \
    -DCMAKE_EXE_LINKER_FLAGS="-Wl,--disable-new-dtags" \
    -DCMAKE_SKIP_RPATH=ON

# Build using system's number of cores
make -j$(nproc)

echo "Build complete. Executable is in build/MonitorClient"
