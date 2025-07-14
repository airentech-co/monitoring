#include <windows.h>
#include <shellapi.h>
#include <iostream>

int main() {
    std::cout << "Testing system notifications..." << std::endl;
    
    // Create a simple window for the notification
    WNDCLASSEXA wcex;
    ZeroMemory(&wcex, sizeof(WNDCLASSEXA));
    wcex.cbSize = sizeof(WNDCLASSEXA);
    wcex.lpfnWndProc = DefWindowProcA;
    wcex.hInstance = GetModuleHandleA(NULL);
    wcex.lpszClassName = "TestNotification";
    
    if (!RegisterClassExA(&wcex)) {
        std::cout << "Failed to register window class. Error: " << GetLastError() << std::endl;
        return 1;
    }
    
    HWND hWnd = CreateWindowExA(0, "TestNotification", "Test", WS_OVERLAPPED, 0, 0, 0, 0, NULL, NULL, GetModuleHandleA(NULL), NULL);
    if (!hWnd) {
        std::cout << "Failed to create window. Error: " << GetLastError() << std::endl;
        return 1;
    }
    
    ShowWindow(hWnd, SW_HIDE);
    
    // Create notification
    NOTIFYICONDATAA nid;
    ZeroMemory(&nid, sizeof(nid));
    nid.cbSize = sizeof(NOTIFYICONDATA);
    nid.hWnd = hWnd;
    nid.uID = 1;
    nid.uFlags = NIF_INFO | NIF_ICON | NIF_TIP;
    nid.dwInfoFlags = NIIF_INFO;
    nid.hIcon = LoadIconA(NULL, (LPCSTR)IDI_INFORMATION);
    strcpy_s(nid.szInfoTitle, sizeof(nid.szInfoTitle), "Test Notification");
    strcpy_s(nid.szInfo, sizeof(nid.szInfo), "This is a test notification from Monitor Client");
    strcpy_s(nid.szTip, sizeof(nid.szTip), "Monitor Client Test");
    
    std::cout << "Showing notification..." << std::endl;
    
    if (!Shell_NotifyIconA(NIM_ADD, &nid)) {
        std::cout << "Failed to show notification. Error: " << GetLastError() << std::endl;
    } else {
        std::cout << "Notification shown successfully!" << std::endl;
        Sleep(3000);
        Shell_NotifyIconA(NIM_DELETE, &nid);
    }
    
    DestroyWindow(hWnd);
    std::cout << "Test completed." << std::endl;
    return 0;
} 