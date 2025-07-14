cmake_minimum_required(VERSION 3.10)
project(MonitorClient)

# Windows-specific build configuration for MinGW-w64

# Find required packages for Windows
find_package(PkgConfig REQUIRED)

# Find SQLite3 for MinGW
find_library(SQLITE3_LIBRARY sqlite3 PATHS C:/msys64/mingw64/lib NO_DEFAULT_PATH)
if(NOT SQLITE3_LIBRARY)
    # Try alternative names for MinGW
    find_library(SQLITE3_LIBRARY libsqlite3 PATHS C:/msys64/mingw64/lib NO_DEFAULT_PATH)
    if(NOT SQLITE3_LIBRARY)
        message(FATAL_ERROR "SQLite3 library not found. Please install: pacman -S mingw-w64-x86_64-sqlite3")
    endif()
endif()

# Find JsonCpp for MinGW
find_library(JSONCPP_LIBRARY jsoncpp PATHS C:/msys64/mingw64/lib NO_DEFAULT_PATH)
if(NOT JSONCPP_LIBRARY)
    # Try alternative names for MinGW
    find_library(JSONCPP_LIBRARY libjsoncpp PATHS C:/msys64/mingw64/lib NO_DEFAULT_PATH)
    if(NOT JSONCPP_LIBRARY)
        message(FATAL_ERROR "JsonCpp library not found. Please install: pacman -S mingw-w64-x86_64-jsoncpp")
    endif()
endif()

# Windows-specific source files
set(WINDOWS_SOURCES
    MonitorClient.cpp
)

# Create Windows executable
add_executable(MonitorClient ${WINDOWS_SOURCES})

# Set Windows-specific properties
set_target_properties(MonitorClient PROPERTIES
    WIN32_EXECUTABLE TRUE
    OUTPUT_NAME "MonitorClient"
)

# Windows-specific include directories
target_include_directories(MonitorClient PRIVATE
    ${CMAKE_CURRENT_SOURCE_DIR}
    ${CMAKE_CURRENT_SOURCE_DIR}/include
    C:/msys64/mingw64/include
    C:/msys64/mingw64/include/jsoncpp
)

# Windows-specific libraries for MinGW
target_link_libraries(MonitorClient
    ${SQLITE3_LIBRARY}
    ${JSONCPP_LIBRARY}
    wininet
    gdiplus
    psapi
    setupapi
    user32
    kernel32
    advapi32
    shell32
    ole32
    uuid
    ws2_32
    iphlpapi
)

# Windows-specific compiler definitions
target_compile_definitions(MonitorClient PRIVATE
    WIN32_LEAN_AND_MEAN
    _WIN32_WINNT=0x0601
    UNICODE
    _UNICODE
)

# MinGW-specific compiler flags
if(MINGW)
    target_compile_options(MonitorClient PRIVATE
        -Wall
        -Wextra
        -Wno-deprecated-declarations
        -Wno-unused-parameter
        -static-libgcc
        -static-libstdc++
    )
endif()

# Install Windows executable
install(TARGETS MonitorClient
    RUNTIME DESTINATION bin
)

# Create Windows installer
if(CPACK_GENERATOR STREQUAL "NSIS")
    set(CPACK_NSIS_EXTRA_INSTALL_COMMANDS "
        WriteRegStr HKLM \\\"Software\\\\Microsoft\\\\Windows\\\\CurrentVersion\\\\Run\\\" \\\"MonitorSystem\\\" \\\"$INSTDIR\\\\bin\\\\MonitorClient.exe\\\"
    ")
    set(CPACK_NSIS_EXTRA_UNINSTALL_COMMANDS "
        DeleteRegValue HKLM \\\"Software\\\\Microsoft\\\\Windows\\\\CurrentVersion\\\\Run\\\" \\\"MonitorSystem\\\"
    ")
endif()

# Copy dependencies for MinGW
if(EXISTS "${SQLITE3_LIBRARY}")
    get_filename_component(SQLITE3_DLL "${SQLITE3_LIBRARY}" PATH)
    if(EXISTS "${SQLITE3_DLL}/sqlite3.dll")
        install(FILES "${SQLITE3_DLL}/sqlite3.dll" DESTINATION bin)
    endif()
endif()

if(EXISTS "${JSONCPP_LIBRARY}")
    get_filename_component(JSONCPP_DLL "${JSONCPP_LIBRARY}" PATH)
    if(EXISTS "${JSONCPP_DLL}/jsoncpp.dll")
        install(FILES "${JSONCPP_DLL}/jsoncpp.dll" DESTINATION bin)
    endif()
endif() 