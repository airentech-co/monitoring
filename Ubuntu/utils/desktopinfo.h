#ifndef DESKTOPINFO_H
#define DESKTOPINFO_H

#include <QString>
#include <QStringList>

class DesktopInfo {
public:
    static QString getDesktopEnvironment();
    static QString getWindowManager();
    static QString getDisplayServer();
    static QString getSystemInfo();
    static QStringList getAvailableDesktops();
    static bool isWayland();
    static bool isX11();
    static QString getScreenResolution();
    static int getScreenCount();
    static QString getHostname();
    static QString getUsername();
    static QString getHomeDirectory();
    static QString getCurrentWorkingDirectory();
    static QString getSystemArchitecture();
    static QString getKernelVersion();
    static QString getDistributionInfo();
    static QString getQtVersion();
    static QString getSystemUptime();
    static QString getMemoryInfo();
    static QString getDiskSpaceInfo();
};

#endif // DESKTOPINFO_H 