cmake_minimum_required(VERSION 3.16)
project(MonitorSystem VERSION 1.0.0 LANGUAGES CXX)

# Set C++ standard
set(CMAKE_CXX_STANDARD 17)
set(CMAKE_CXX_STANDARD_REQUIRED ON)

# Platform-specific settings
if(WIN32)
    set(PLATFORM "Windows")
    set(CMAKE_CXX_FLAGS "${CMAKE_CXX_FLAGS} /std:c++17")
elseif(APPLE)
    set(PLATFORM "MacOS")
    set(CMAKE_CXX_FLAGS "${CMAKE_CXX_FLAGS} -std=c++17")
elseif(UNIX)
    set(PLATFORM "Linux")
    set(CMAKE_CXX_FLAGS "${CMAKE_CXX_FLAGS} -std=c++17")
endif()

# Find required packages
find_package(PkgConfig REQUIRED)

# Platform-specific configurations
if(WIN32)
    # Windows configuration
    add_subdirectory(Windows)
elseif(APPLE)
    # macOS configuration
    add_subdirectory(MacOS)
elseif(UNIX)
    # Linux/Ubuntu configuration
    add_subdirectory(Ubuntu)
endif()

# Backend configuration
add_subdirectory(Backend)

# Print configuration summary
message(STATUS "Building for platform: ${PLATFORM}")
message(STATUS "C++ standard: ${CMAKE_CXX_STANDARD}")
message(STATUS "Build type: ${CMAKE_BUILD_TYPE}")

# Install targets with platform-specific destinations
if(APPLE)
    install(TARGETS MonitorClient
        BUNDLE DESTINATION Applications
    )
else()
    install(TARGETS MonitorClient
        RUNTIME DESTINATION bin
        LIBRARY DESTINATION lib
        ARCHIVE DESTINATION lib
    )
endif()

# Install backend files
install(DIRECTORY Backend/
    DESTINATION share/monitorsystem/backend
    FILES_MATCHING PATTERN "*.php"
    PATTERN "*.inc.php"
    PATTERN "*.json"
)

# Create package
include(CPack)
set(CPACK_PACKAGE_NAME "MonitorSystem")
set(CPACK_PACKAGE_VERSION ${PROJECT_VERSION})
set(CPACK_PACKAGE_DESCRIPTION_SUMMARY "Cross-platform monitoring system")
set(CPACK_PACKAGE_VENDOR "MonitorSystem Team")
set(CPACK_PACKAGE_CONTACT "support@monitorsystem.com")

if(WIN32)
    set(CPACK_GENERATOR "NSIS")
    set(CPACK_NSIS_PACKAGE_NAME "MonitorSystem")
    set(CPACK_NSIS_DISPLAY_NAME "MonitorSystem")
    set(CPACK_NSIS_CONTACT "support@monitorsystem.com")
    set(CPACK_NSIS_ENABLE_ICON_EXTRA "ON")
elseif(APPLE)
    set(CPACK_GENERATOR "DragNDrop")
    set(CPACK_DMG_VOLUME_NAME "MonitorSystem")
elseif(UNIX)
    set(CPACK_GENERATOR "DEB")
    set(CPACK_DEBIAN_PACKAGE_NAME "monitorsystem")
    set(CPACK_DEBIAN_PACKAGE_DEPENDS "libc6, libstdc++6, libsqlite3-0")
    set(CPACK_DEBIAN_PACKAGE_SECTION "utils")
    set(CPACK_DEBIAN_PACKAGE_PRIORITY "optional")
endif() 