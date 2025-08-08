cmake_minimum_required(VERSION 3.16)
project(MonitorClient)

# Ubuntu/Linux-specific build configuration
set(CMAKE_BUILD_TYPE Debug)
set(CMAKE_SKIP_RPATH ON)
set(CMAKE_EXE_LINKER_FLAGS "${CMAKE_EXE_LINKER_FLAGS} -Wl,--disable-new-dtags")
set(CMAKE_BUILD_RPATH_USE_ORIGIN ON)

# Find Qt5 components
find_package(Qt5 COMPONENTS Core Widgets Network Sql DBus REQUIRED)

# Find required system libraries
find_package(PkgConfig REQUIRED)
pkg_check_modules(UDEV REQUIRED libudev)

# Ubuntu-specific source files
set(UBUNTU_SOURCES
    main.cpp
    MonitorClient.cpp
    KeyboardMonitor.cpp
    ConfigDialog.cpp
    utils/desktopinfo.cpp
    functions.cpp
)

# Create Ubuntu executable with MOC support
add_executable(MonitorClient ${UBUNTU_SOURCES})

# Enable Qt MOC for the target
set_target_properties(MonitorClient PROPERTIES
    AUTOMOC ON
)

# Set Ubuntu-specific properties
set_target_properties(MonitorClient PROPERTIES
    OUTPUT_NAME "MonitorClient"
)

# Ubuntu-specific include directories
target_include_directories(MonitorClient PRIVATE
    ${CMAKE_CURRENT_SOURCE_DIR}
    ${CMAKE_CURRENT_SOURCE_DIR}/include
    ${UDEV_INCLUDE_DIRS}
)

# Find Threads package
find_package(Threads REQUIRED)

# Ubuntu-specific libraries
target_link_libraries(MonitorClient
    Qt5::Core
    Qt5::Widgets
    Qt5::Network
    Qt5::Sql
    Qt5::DBus
    ${UDEV_LIBRARIES}
    ${CMAKE_THREAD_LIBS_INIT}
    sqlite3
    X11
    Xext
    Xrandr
)

# Ubuntu-specific compiler definitions
target_compile_definitions(MonitorClient PRIVATE
    QT_DEPRECATED_WARNINGS
    _GNU_SOURCE
)

# Ubuntu-specific compiler flags
target_compile_options(MonitorClient PRIVATE
    -Wall
    -Wextra
    -std=c++17
)

# Install Ubuntu executable
install(TARGETS MonitorClient
    RUNTIME DESTINATION bin
)

# Install desktop file
install(FILES MonitorClient.desktop
    DESTINATION share/applications
)

# Install icon
install(FILES MonitorClient.png
    DESTINATION share/pixmaps
)

# Create autostart entry
install(FILES MonitorClient.desktop
    DESTINATION share/applications
    RENAME MonitorClient-autostart.desktop
)

# Install systemd service file
install(FILES MonitorClient.service
    DESTINATION lib/systemd/user
) 