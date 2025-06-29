# MacOS-specific build configuration

# Enable Swift language
enable_language(Swift)

# Find required packages for MacOS
find_package(PkgConfig REQUIRED)

# MacOS-specific source files
set(MACOS_SOURCES
    AppDelegate.swift
)

# Create MacOS executable
add_executable(MonitorClient ${MACOS_SOURCES})

# Set MacOS-specific properties
set_target_properties(MonitorClient PROPERTIES
    MACOSX_BUNDLE TRUE
    OUTPUT_NAME "MonitorClient"
    MACOSX_BUNDLE_INFO_PLIST "${CMAKE_CURRENT_SOURCE_DIR}/Info.plist"
    SWIFT_COMPILATION_MODE wholemodule
)

# MacOS-specific include directories
target_include_directories(MonitorClient PRIVATE
    ${CMAKE_CURRENT_SOURCE_DIR}
    ${CMAKE_CURRENT_SOURCE_DIR}/include
)

# MacOS-specific frameworks
target_link_libraries(MonitorClient
    "-framework Cocoa"
    "-framework CoreGraphics"
    "-framework IOKit"
    "-framework Security"
    "-framework SystemConfiguration"
    "-framework WebKit"
    "-framework CoreData"
    "-framework Foundation"
    "-framework AppKit"
)

# MacOS-specific compiler definitions
target_compile_definitions(MonitorClient PRIVATE
    MACOSX
    __MACOSX__
)

# MacOS-specific compiler flags
target_compile_options(MonitorClient PRIVATE
)

# Install MacOS executable
install(TARGETS MonitorClient
    BUNDLE DESTINATION Applications
)

# Create app bundle
set_target_properties(MonitorClient PROPERTIES
    MACOSX_BUNDLE_GUI_IDENTIFIER "com.alice.MonitorClient"
    MACOSX_BUNDLE_BUNDLE_VERSION "${PROJECT_VERSION}"
    MACOSX_BUNDLE_SHORT_VERSION_STRING "${PROJECT_VERSION}"
    MACOSX_BUNDLE_LONG_VERSION_STRING "${PROJECT_VERSION}"
    MACOSX_BUNDLE_ICON_FILE "MonitorClient.icns"
    MACOSX_BUNDLE_INFO_PLIST "${CMAKE_CURRENT_SOURCE_DIR}/Info.plist"
) 