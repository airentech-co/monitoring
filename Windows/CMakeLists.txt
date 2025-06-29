# Windows-specific build configuration

# Find required packages for Windows
find_package(PkgConfig REQUIRED)

# Find SQLite3
find_library(SQLITE3_LIBRARY sqlite3)
if(NOT SQLITE3_LIBRARY)
    message(FATAL_ERROR "SQLite3 library not found")
endif()

# Find JsonCpp
find_library(JSONCPP_LIBRARY jsoncpp)
if(NOT JSONCPP_LIBRARY)
    message(FATAL_ERROR "JsonCpp library not found")
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
)

# Windows-specific libraries
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
)

# Windows-specific compiler definitions
target_compile_definitions(MonitorClient PRIVATE
    WIN32_LEAN_AND_MEAN
    _WIN32_WINNT=0x0601
    UNICODE
    _UNICODE
)

# Windows-specific compiler flags
if(MSVC)
    target_compile_options(MonitorClient PRIVATE
        /W4
        /wd4996  # Disable deprecation warnings
        /wd4267  # Disable size_t conversion warnings
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

# Copy dependencies
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