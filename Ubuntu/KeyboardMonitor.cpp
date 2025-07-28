#include "KeyboardMonitor.h"
#include <QDebug>
#include <QDateTime>
#include <QTimeZone>
#include <QDir>
#include <QFile>
#include <QTextStream>
#include <linux/input.h>
#include <fcntl.h>
#include <unistd.h>
#include <sys/select.h>
#include <X11/Xlib.h>
#include <X11/Xutil.h>
#include <X11/Xatom.h>

KeyboardMonitor::KeyboardMonitor(QObject *parent)
    : QObject(parent)
    , running(false)
{
    monitorThread = nullptr;
}

KeyboardMonitor::~KeyboardMonitor()
{
    stopMonitoring();
}

bool KeyboardMonitor::startMonitoring()
{
    if (running) {
        return true;
    }

    findKeyboardDevices();
    
    if (keyboardDevices.isEmpty()) {
        qDebug() << "No keyboard devices found";
        return false;
    }

    qDebug() << "Found" << keyboardDevices.size() << "keyboard devices:";
    for (auto it = keyboardDevices.begin(); it != keyboardDevices.end(); ++it) {
        qDebug() << "  -" << it.value().deviceName << "at" << it.value().devicePath;
    }

    running = true;
    monitorThread = QThread::create([this]() { readEvents(); });
    monitorThread->start();

    return true;
}

void KeyboardMonitor::stopMonitoring()
{
    running = false;
    if (monitorThread) {
        monitorThread->wait();
        delete monitorThread;
        monitorThread = nullptr;
    }
    closeAllDevices();
}

void KeyboardMonitor::findKeyboardDevices()
{
    closeAllDevices();
    
    // Find keyboard devices
    QDir inputDir("/dev/input/by-id");
    if (!inputDir.exists()) {
        qDebug() << "Input directory not found";
        return;
    }

    QStringList filters;
    filters << "*-kbd" << "*-keyboard" << "*event-kbd" << "*event-kbd*";
    QStringList entries = inputDir.entryList(filters, QDir::Files);

    for (const QString &entry : entries) {
        QString path = inputDir.absoluteFilePath(entry);
        if (isKeyboardDevice(path)) {
            KeyboardDevice device;
            device.devicePath = path;
            
            // Get device name
            int testFd = open(path.toLocal8Bit().constData(), O_RDONLY);
            if (testFd >= 0) {
                char name[256] = "Unknown";
                if (ioctl(testFd, EVIOCGNAME(sizeof(name)), name) >= 0) {
                    device.deviceName = QString::fromUtf8(name);
                }
                close(testFd);
            }
            
            // Open device for monitoring
            device.fd = open(path.toLocal8Bit().constData(), O_RDONLY);
            if (device.fd >= 0) {
                keyboardDevices[path] = device;
                qDebug() << "Added keyboard device:" << device.deviceName << "at" << path;
            } else {
                qDebug() << "Failed to open keyboard device:" << path;
            }
        }
    }
}

void KeyboardMonitor::closeAllDevices()
{
    for (auto it = keyboardDevices.begin(); it != keyboardDevices.end(); ++it) {
        if (it.value().fd >= 0) {
            close(it.value().fd);
        }
    }
    keyboardDevices.clear();
}

bool KeyboardMonitor::isKeyboardDevice(const QString &devicePath)
{
    int testFd = open(devicePath.toLocal8Bit().constData(), O_RDONLY);
    if (testFd < 0) {
        qDebug() << "Failed to open device:" << devicePath << "Error:" << strerror(errno);
        return false;
    }

    // Get device name
    char name[256] = "Unknown";
    if (ioctl(testFd, EVIOCGNAME(sizeof(name)), name) >= 0) {
        qDebug() << "Device name:" << name;
        // If the device name contains "keyboard", consider it a keyboard
        if (QString(name).contains("keyboard", Qt::CaseInsensitive)) {
            qDebug() << "Device name indicates it's a keyboard";
            close(testFd);
            return true;
        }
    }

    unsigned long evbit = 0;
    if (ioctl(testFd, EVIOCGBIT(0, sizeof(evbit)), &evbit) < 0) {
        qDebug() << "Failed to get event bits:" << strerror(errno);
        close(testFd);
        return false;
    }

    if (!(evbit & (1 << EV_KEY))) {
        qDebug() << "Device does not support key events";
        close(testFd);
        return false;
    }

    unsigned long keybit[KEY_MAX/8 + 1] = {0};
    if (ioctl(testFd, EVIOCGBIT(EV_KEY, sizeof(keybit)), keybit) < 0) {
        qDebug() << "Failed to get key bits:" << strerror(errno);
        close(testFd);
        return false;
    }

    // Check for various keyboard keys
    bool hasLetterKeys = false;
    bool hasNumberKeys = false;
    bool hasModifierKeys = false;
    bool hasFunctionKeys = false;

    // Check for letter keys (A-Z)
    for (int i = KEY_A; i <= KEY_Z; i++) {
        if (keybit[i/8] & (1 << (i % 8))) {
            hasLetterKeys = true;
            break;
        }
    }

    // Check for number keys (1-9)
    for (int i = KEY_1; i <= KEY_9; i++) {
        if (keybit[i/8] & (1 << (i % 8))) {
            hasNumberKeys = true;
            break;
        }
    }

    // Check for modifier keys
    hasModifierKeys = (keybit[KEY_LEFTSHIFT/8] & (1 << (KEY_LEFTSHIFT % 8))) ||
                     (keybit[KEY_LEFTCTRL/8] & (1 << (KEY_LEFTCTRL % 8))) ||
                     (keybit[KEY_LEFTALT/8] & (1 << (KEY_LEFTALT % 8)));

    // Check for function keys
    hasFunctionKeys = (keybit[KEY_F1/8] & (1 << (KEY_F1 % 8))) ||
                     (keybit[KEY_F2/8] & (1 << (KEY_F2 % 8))) ||
                     (keybit[KEY_F3/8] & (1 << (KEY_F3 % 8)));

    qDebug() << "Has letter keys:" << hasLetterKeys;
    qDebug() << "Has number keys:" << hasNumberKeys;
    qDebug() << "Has modifier keys:" << hasModifierKeys;
    qDebug() << "Has function keys:" << hasFunctionKeys;

    // Consider it a keyboard if it has any of these key types
    bool isKeyboard = hasLetterKeys || hasNumberKeys || hasModifierKeys || hasFunctionKeys;
    qDebug() << "Is keyboard:" << isKeyboard;

    close(testFd);
    return isKeyboard;
}

void KeyboardMonitor::readEvents()
{
    while (running) {
        fd_set readfds;
        FD_ZERO(&readfds);
        
        int maxFd = -1;
        QMap<int, QString> fdToDevicePath;
        
        // Set up file descriptors for select
        for (auto it = keyboardDevices.begin(); it != keyboardDevices.end(); ++it) {
            if (it.value().fd >= 0) {
                FD_SET(it.value().fd, &readfds);
                fdToDevicePath[it.value().fd] = it.key();
                if (it.value().fd > maxFd) {
                    maxFd = it.value().fd;
                }
            }
        }
        
        if (maxFd < 0) {
            // No valid file descriptors
            QThread::msleep(100);
            continue;
        }
        
        // Wait for events with timeout
        struct timeval timeout;
        timeout.tv_sec = 0;
        timeout.tv_usec = 100000; // 100ms timeout
        
        int result = select(maxFd + 1, &readfds, nullptr, nullptr, &timeout);
        
        if (result < 0) {
            if (errno != EINTR) {
                qDebug() << "Select error:" << strerror(errno);
                break;
            }
            continue;
        }
        
        if (result == 0) {
            // Timeout, continue loop
            continue;
        }
        
        // Check each file descriptor for events
        for (auto it = keyboardDevices.begin(); it != keyboardDevices.end(); ++it) {
            if (it.value().fd >= 0 && FD_ISSET(it.value().fd, &readfds)) {
                struct input_event event;
                ssize_t n = read(it.value().fd, &event, sizeof(event));
                
                if (n == sizeof(event)) {
                    if (event.type == EV_KEY) {
                        handleKeyEvent(event.code, event.value == 1, it.value());
                    }
                } else if (n < 0) {
                    if (errno != EAGAIN) {
                        qDebug() << "Error reading from keyboard device" << it.value().deviceName << ":" << strerror(errno);
                        // Close this device and remove it
                        close(it.value().fd);
                        it = keyboardDevices.erase(it);
                        if (it == keyboardDevices.end()) break;
                    }
                }
            }
        }
    }
}

QString KeyboardMonitor::getKeyText(int keyCode, KeyboardDevice &device)
{
    // Update modifier states
    if (keyCode == KEY_LEFTSHIFT || keyCode == KEY_RIGHTSHIFT) {
        device.shiftPressed = true;
        return "";
    } else if (keyCode == KEY_LEFTCTRL || keyCode == KEY_RIGHTCTRL) {
        device.ctrlPressed = true;
        return "";
    } else if (keyCode == KEY_LEFTALT || keyCode == KEY_RIGHTALT) {
        device.altPressed = true;
        return "";
    } else if (keyCode == KEY_CAPSLOCK) {
        device.capsLockOn = !device.capsLockOn;
        return "";
    }

    // Build modifier prefix
    QString modifierPrefix;
    if (device.ctrlPressed) modifierPrefix += "Ctrl + ";
    if (device.altPressed) modifierPrefix += "Alt + ";
    if (device.shiftPressed) modifierPrefix += "Shift + ";

    // Determine if the character should be uppercase
    bool isUpperCase = (device.shiftPressed != device.capsLockOn);

    // Get the base key text
    QString keyText;
    switch (keyCode) {
        case KEY_ESC: keyText = "Escape"; break;
        case KEY_1: keyText = device.shiftPressed ? "!" : "1"; break;
        case KEY_2: keyText = device.shiftPressed ? "@" : "2"; break;
        case KEY_3: keyText = device.shiftPressed ? "#" : "3"; break;
        case KEY_4: keyText = device.shiftPressed ? "$" : "4"; break;
        case KEY_5: keyText = device.shiftPressed ? "%" : "5"; break;
        case KEY_6: keyText = device.shiftPressed ? "^" : "6"; break;
        case KEY_7: keyText = device.shiftPressed ? "&" : "7"; break;
        case KEY_8: keyText = device.shiftPressed ? "*" : "8"; break;
        case KEY_9: keyText = device.shiftPressed ? "(" : "9"; break;
        case KEY_0: keyText = device.shiftPressed ? ")" : "0"; break;
        case KEY_MINUS: keyText = device.shiftPressed ? "_" : "-"; break;
        case KEY_EQUAL: keyText = device.shiftPressed ? "+" : "="; break;
        case KEY_BACKSPACE: keyText = "Backspace"; break;
        case KEY_TAB: keyText = "Tab"; break;
        case KEY_Q: keyText = isUpperCase ? "Q" : "q"; break;
        case KEY_W: keyText = isUpperCase ? "W" : "w"; break;
        case KEY_E: keyText = isUpperCase ? "E" : "e"; break;
        case KEY_R: keyText = isUpperCase ? "R" : "r"; break;
        case KEY_T: keyText = isUpperCase ? "T" : "t"; break;
        case KEY_Y: keyText = isUpperCase ? "Y" : "y"; break;
        case KEY_U: keyText = isUpperCase ? "U" : "u"; break;
        case KEY_I: keyText = isUpperCase ? "I" : "i"; break;
        case KEY_O: keyText = isUpperCase ? "O" : "o"; break;
        case KEY_P: keyText = isUpperCase ? "P" : "p"; break;
        case KEY_LEFTBRACE: keyText = device.shiftPressed ? "{" : "["; break;
        case KEY_RIGHTBRACE: keyText = device.shiftPressed ? "}" : "]"; break;
        case KEY_ENTER: keyText = "Enter"; break;
        case KEY_LEFTCTRL: keyText = "Left Control"; break;
        case KEY_A: keyText = isUpperCase ? "A" : "a"; break;
        case KEY_S: keyText = isUpperCase ? "S" : "s"; break;
        case KEY_D: keyText = isUpperCase ? "D" : "d"; break;
        case KEY_F: keyText = isUpperCase ? "F" : "f"; break;
        case KEY_G: keyText = isUpperCase ? "G" : "g"; break;
        case KEY_H: keyText = isUpperCase ? "H" : "h"; break;
        case KEY_J: keyText = isUpperCase ? "J" : "j"; break;
        case KEY_K: keyText = isUpperCase ? "K" : "k"; break;
        case KEY_L: keyText = isUpperCase ? "L" : "l"; break;
        case KEY_SEMICOLON: keyText = device.shiftPressed ? ":" : ";"; break;
        case KEY_APOSTROPHE: keyText = device.shiftPressed ? "\"" : "'"; break;
        case KEY_GRAVE: keyText = device.shiftPressed ? "~" : "`"; break;
        case KEY_LEFTSHIFT: keyText = "Left Shift"; break;
        case KEY_BACKSLASH: keyText = device.shiftPressed ? "|" : "\\"; break;
        case KEY_Z: keyText = isUpperCase ? "Z" : "z"; break;
        case KEY_X: keyText = isUpperCase ? "X" : "x"; break;
        case KEY_C: keyText = isUpperCase ? "C" : "c"; break;
        case KEY_V: keyText = isUpperCase ? "V" : "v"; break;
        case KEY_B: keyText = isUpperCase ? "B" : "b"; break;
        case KEY_N: keyText = isUpperCase ? "N" : "n"; break;
        case KEY_M: keyText = isUpperCase ? "M" : "m"; break;
        case KEY_COMMA: keyText = device.shiftPressed ? "<" : ","; break;
        case KEY_DOT: keyText = device.shiftPressed ? ">" : "."; break;
        case KEY_SLASH: keyText = device.shiftPressed ? "?" : "/"; break;
        case KEY_RIGHTSHIFT: keyText = "Right Shift"; break;
        case KEY_KPASTERISK: keyText = "*"; break;
        case KEY_LEFTALT: keyText = "Left Alt"; break;
        case KEY_SPACE: keyText = " "; break;
        case KEY_CAPSLOCK: keyText = "Caps Lock"; break;
        case KEY_F1: keyText = "F1"; break;
        case KEY_F2: keyText = "F2"; break;
        case KEY_F3: keyText = "F3"; break;
        case KEY_F4: keyText = "F4"; break;
        case KEY_F5: keyText = "F5"; break;
        case KEY_F6: keyText = "F6"; break;
        case KEY_F7: keyText = "F7"; break;
        case KEY_F8: keyText = "F8"; break;
        case KEY_F9: keyText = "F9"; break;
        case KEY_F10: keyText = "F10"; break;
        case KEY_NUMLOCK: keyText = "Num Lock"; break;
        case KEY_SCROLLLOCK: keyText = "Scroll Lock"; break;
        case KEY_KP7: keyText = "7"; break;
        case KEY_KP8: keyText = "8"; break;
        case KEY_KP9: keyText = "9"; break;
        case KEY_KPMINUS: keyText = "-"; break;
        case KEY_KP4: keyText = "4"; break;
        case KEY_KP5: keyText = "5"; break;
        case KEY_KP6: keyText = "6"; break;
        case KEY_KPPLUS: keyText = "+"; break;
        case KEY_KP1: keyText = "1"; break;
        case KEY_KP2: keyText = "2"; break;
        case KEY_KP3: keyText = "3"; break;
        case KEY_KP0: keyText = "0"; break;
        case KEY_KPDOT: keyText = "."; break;
        case KEY_F11: keyText = "F11"; break;
        case KEY_F12: keyText = "F12"; break;
        case KEY_RIGHTCTRL: keyText = "Right Control"; break;
        case KEY_RIGHTALT: keyText = "Right Alt"; break;
        case KEY_HOME: keyText = "Home"; break;
        case KEY_UP: keyText = "Up"; break;
        case KEY_PAGEUP: keyText = "Page Up"; break;
        case KEY_LEFT: keyText = "Left"; break;
        case KEY_RIGHT: keyText = "Right"; break;
        case KEY_END: keyText = "End"; break;
        case KEY_DOWN: keyText = "Down"; break;
        case KEY_PAGEDOWN: keyText = "Page Down"; break;
        case KEY_INSERT: keyText = "Insert"; break;
        case KEY_DELETE: keyText = "Delete"; break;
        default:
            keyText = QString("Key_%1").arg(keyCode);
    }

    // Return empty string for modifier keys
    if (keyCode == KEY_LEFTSHIFT || keyCode == KEY_RIGHTSHIFT ||
        keyCode == KEY_LEFTCTRL || keyCode == KEY_RIGHTCTRL ||
        keyCode == KEY_LEFTALT || keyCode == KEY_RIGHTALT ||
        keyCode == KEY_CAPSLOCK) {
        return "";
    }

    // Return the combined modifier prefix and key text
    return modifierPrefix + keyText;
}

QString KeyboardMonitor::findDesktopFile(const QString& appClass) {
    // First try to find by filename
    QStringList paths;
    paths << "/usr/share/applications" << "/usr/local/share/applications" << QDir::homePath() + "/.local/share/applications";
    
    // Convert app class to lowercase for filename matching
    QString lowerAppClass = appClass.toLower();
    
    // Try case-insensitive match
    for (const QString& path : paths) {
        QDir dir(path);
        if (!dir.exists())
            continue;
            
        dir.setFilter(QDir::Files | QDir::NoDotAndDotDot);
        QStringList entries = dir.entryList(QStringList() << "*.desktop");
        
        for (const QString& entry : entries) {
            QString entryLower = entry.toLower();
            if (entryLower == lowerAppClass + ".desktop") {
                QString desktopFile = dir.filePath(entry);
                return desktopFile;
            }
        }
    }
    
    // If no exact match, try searching through content
    for (const QString& path : paths) {
        QDir dir(path);
        if (!dir.exists())
            continue;

        dir.setFilter(QDir::Files | QDir::NoDotAndDotDot);
        QStringList entries = dir.entryList(QStringList() << "*.desktop");

        for (const QString& entry : entries) {
            QFileInfo fileInfo(dir, entry);
            QFile file(fileInfo.absoluteFilePath());
            if (!file.open(QIODevice::ReadOnly | QIODevice::Text))
                continue;
            QTextStream in(&file);
            while (!in.atEnd()) {
                QString line = in.readLine();
                if (line.startsWith("StartupWMClass=") && line.contains(appClass, Qt::CaseInsensitive)) {
                    return fileInfo.absoluteFilePath();
                }
                if (line.startsWith("Exec=") && line.contains(appClass, Qt::CaseInsensitive)) {
                    return fileInfo.absoluteFilePath();
                }
                if (line.startsWith("TryExec=") && line.contains(appClass, Qt::CaseInsensitive)) {
                    return fileInfo.absoluteFilePath();
                }
            }
            file.close();
        }
    }
    
    qDebug() << "No desktop file found for:" << appClass;
    return "";
}

QString KeyboardMonitor::getActiveWindowTitle()
{
    Display* display = XOpenDisplay(nullptr);
    if (!display) {
        return "Unknown";
    }

    // Get the root window
    Window root = DefaultRootWindow(display);
    
    // Get the active window
    Atom activeWindow = XInternAtom(display, "_NET_ACTIVE_WINDOW", False);
    Atom actualType;
    int actualFormat;
    unsigned long nitems, bytesAfter;
    unsigned char* data = nullptr;
    
    if (XGetWindowProperty(display, root, activeWindow, 0, 1, False, XA_WINDOW,
                          &actualType, &actualFormat, &nitems, &bytesAfter, &data) == Success) {
        if (data) {
            Window active = *(Window*)data;
            XFree(data);
            
            // Try to get the window class name first
            XClassHint* classHint = XAllocClassHint();
            if (XGetClassHint(display, active, classHint) && classHint->res_name) {
                QString appClass = QString::fromUtf8(classHint->res_class);
                QString appName = QString::fromUtf8(classHint->res_name);
                XFree(classHint->res_name);
                XFree(classHint->res_class);
                XFree(classHint);
                
                // Find desktop file using the application name
                QString desktopFilePath = findDesktopFile(appClass);
                if (!desktopFilePath.isEmpty()) {
                    QFile file(desktopFilePath);
                    if (file.open(QIODevice::ReadOnly | QIODevice::Text)) {
                        QTextStream in(&file);
                        QString line;
                        bool inDesktopEntry = false;
                        
                        while (!in.atEnd()) {
                            line = in.readLine();
                            
                            if (line.startsWith("[Desktop Entry]")) {
                                inDesktopEntry = true;
                                continue;
                            }
                            
                            if (line.startsWith("[") && line.endsWith("]")) {
                                inDesktopEntry = false;
                                continue;
                            }
                            
                            if (inDesktopEntry && line.startsWith("Name=")) {
                                QString displayName = line.mid(5);
                                file.close();
                                XCloseDisplay(display);
                                return displayName;
                            }
                        }
                        file.close();
                    }
                }
                
                // If no desktop file found or no Name field, try to make the app name more readable
                QString displayName = appName;
                displayName.replace("-", " ");
                displayName = displayName.at(0).toUpper() + displayName.mid(1);
                XCloseDisplay(display);
                return displayName;
            }
            if (classHint) {
                XFree(classHint);
            }
            
            // Try to get the visible name
            Atom wmVisibleName = XInternAtom(display, "_NET_WM_VISIBLE_NAME", False);
            Atom utf8String = XInternAtom(display, "UTF8_STRING", False);
            
            if (XGetWindowProperty(display, active, wmVisibleName, 0, 1024, False, utf8String,
                                 &actualType, &actualFormat, &nitems, &bytesAfter, &data) == Success) {
                if (data) {
                    QString title = QString::fromUtf8((char*)data);
                    XFree(data);
                    XCloseDisplay(display);
                    return title;
                }
            }
            
            // Try _NET_WM_NAME as fallback
            Atom wmName = XInternAtom(display, "_NET_WM_NAME", False);
            if (XGetWindowProperty(display, active, wmName, 0, 1024, False, utf8String,
                                 &actualType, &actualFormat, &nitems, &bytesAfter, &data) == Success) {
                if (data) {
                    QString title = QString::fromUtf8((char*)data);
                    XFree(data);
                    XCloseDisplay(display);
                    return title;
                }
            }
            
            // Try WM_NAME as last resort
            if (XGetWindowProperty(display, active, XA_WM_NAME, 0, 1024, False, XA_STRING,
                                 &actualType, &actualFormat, &nitems, &bytesAfter, &data) == Success) {
                if (data) {
                    QString title = QString::fromUtf8((char*)data);
                    XFree(data);
                    XCloseDisplay(display);
                    return title;
                }
            }
        }
    }
    
    XCloseDisplay(display);
    return "Unknown";
}

void KeyboardMonitor::handleKeyEvent(int keyCode, bool pressed, KeyboardDevice &device)
{
    if (!pressed) {
        // Handle key release
        if (keyCode == KEY_LEFTSHIFT || keyCode == KEY_RIGHTSHIFT) {
            device.shiftPressed = false;
        } else if (keyCode == KEY_LEFTCTRL || keyCode == KEY_RIGHTCTRL) {
            device.ctrlPressed = false;
        } else if (keyCode == KEY_LEFTALT || keyCode == KEY_RIGHTALT) {
            device.altPressed = false;
        }
        return;
    }

    QString keyText = getKeyText(keyCode, device);
    if (!keyText.isEmpty()) {
        QString timestamp = QDateTime::currentDateTime().toTimeZone(QTimeZone("Asia/Vladivostok")).toString("yyyy-MM-dd HH:mm:ss");
        QString windowTitle = getActiveWindowTitle();
        emit keyPressed(QDateTime::currentMSecsSinceEpoch(), windowTitle, keyText);
    }
}

void KeyboardMonitor::handleUsbEvent()
{
    qDebug() << "USB event";
    stopMonitoring();
    qDebug() << "Stopped event";
    startMonitoring();
}