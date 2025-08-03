#include "desktopinfo.h"
#include <QProcess>
#include <QDir>
#include <QFile>
#include <QTextStream>
#include <QStandardPaths>
#include <QStorageInfo>
#include <QSysInfo>
#include <QScreen>
#include <QGuiApplication>
#include <QDebug>
#include <unistd.h>
#include <sys/utsname.h>
#include <sys/sysinfo.h>

QString DesktopInfo::getDesktopEnvironment()
{
    QString desktop = qgetenv("XDG_CURRENT_DESKTOP");
    if (desktop.isEmpty()) {
        desktop = qgetenv("DESKTOP_SESSION");
    }
    if (desktop.isEmpty()) {
        desktop = qgetenv("GNOME_DESKTOP_SESSION_ID");
        if (!desktop.isEmpty()) {
            desktop = "GNOME";
        }
    }
    return desktop;
}

QString DesktopInfo::getWindowManager()
{
    QString wm = qgetenv("XDG_SESSION_TYPE");
    if (wm.isEmpty()) {
        wm = qgetenv("WINDOWMANAGER");
    }
    return wm;
}

QString DesktopInfo::getDisplayServer()
{
    QString display = qgetenv("WAYLAND_DISPLAY");
    if (!display.isEmpty()) {
        return "Wayland";
    }
    
    display = qgetenv("DISPLAY");
    if (!display.isEmpty()) {
        return "X11";
    }
    
    return "Unknown";
}

QString DesktopInfo::getSystemInfo()
{
    QString info;
    info += "Desktop Environment: " + getDesktopEnvironment() + "\n";
    info += "Window Manager: " + getWindowManager() + "\n";
    info += "Display Server: " + getDisplayServer() + "\n";
    info += "Architecture: " + getSystemArchitecture() + "\n";
    info += "Kernel: " + getKernelVersion() + "\n";
    info += "Distribution: " + getDistributionInfo() + "\n";
    info += "Qt Version: " + getQtVersion() + "\n";
    return info;
}

QStringList DesktopInfo::getAvailableDesktops()
{
    QStringList desktops;
    QDir dir("/usr/share/xsessions");
    if (dir.exists()) {
        QStringList entries = dir.entryList(QDir::Files);
        for (const QString &entry : entries) {
            if (entry.endsWith(".desktop")) {
                QString desktop = entry;
                desktops << desktop.remove(".desktop");
            }
        }
    }
    return desktops;
}

bool DesktopInfo::isWayland()
{
    return !qgetenv("WAYLAND_DISPLAY").isEmpty();
}

bool DesktopInfo::isX11()
{
    return !qgetenv("DISPLAY").isEmpty() && qgetenv("WAYLAND_DISPLAY").isEmpty();
}

QString DesktopInfo::getScreenResolution()
{
    QGuiApplication *app = qobject_cast<QGuiApplication*>(QCoreApplication::instance());
    if (app && !app->screens().isEmpty()) {
        QScreen *screen = app->screens().first();
        return QString("%1x%2").arg(screen->size().width()).arg(screen->size().height());
    }
    return "Unknown";
}

int DesktopInfo::getScreenCount()
{
    QGuiApplication *app = qobject_cast<QGuiApplication*>(QCoreApplication::instance());
    if (app) {
        return app->screens().size();
    }
    return 0;
}

QString DesktopInfo::getHostname()
{
    char hostname[256];
    if (gethostname(hostname, sizeof(hostname)) == 0) {
        return QString(hostname);
    }
    return "Unknown";
}

QString DesktopInfo::getUsername()
{
    return qgetenv("USER");
}

QString DesktopInfo::getHomeDirectory()
{
    return QStandardPaths::writableLocation(QStandardPaths::HomeLocation);
}

QString DesktopInfo::getCurrentWorkingDirectory()
{
    return QDir::currentPath();
}

QString DesktopInfo::getSystemArchitecture()
{
    return QSysInfo::currentCpuArchitecture();
}

QString DesktopInfo::getKernelVersion()
{
    struct utsname uts;
    if (uname(&uts) == 0) {
        return QString(uts.release);
    }
    return "Unknown";
}

QString DesktopInfo::getDistributionInfo()
{
    QString distro = "Unknown";
    
    // Try to read from /etc/os-release
    QFile file("/etc/os-release");
    if (file.open(QIODevice::ReadOnly | QIODevice::Text)) {
        QTextStream in(&file);
        while (!in.atEnd()) {
            QString line = in.readLine();
            if (line.startsWith("PRETTY_NAME=")) {
                distro = line.mid(12).remove('"');
                break;
            }
        }
        file.close();
    }
    
    return distro;
}

QString DesktopInfo::getQtVersion()
{
    return QString(qVersion());
}

QString DesktopInfo::getSystemUptime()
{
    struct sysinfo info;
    if (sysinfo(&info) == 0) {
        qint64 uptime = info.uptime;
        int days = uptime / 86400;
        int hours = (uptime % 86400) / 3600;
        int minutes = (uptime % 3600) / 60;
        return QString("%1 days, %2 hours, %3 minutes").arg(days).arg(hours).arg(minutes);
    }
    return "Unknown";
}

QString DesktopInfo::getMemoryInfo()
{
    struct sysinfo info;
    if (sysinfo(&info) == 0) {
        qint64 total = info.totalram * info.mem_unit;
        qint64 free = info.freeram * info.mem_unit;
        qint64 used = total - free;
        
        return QString("Total: %1 MB, Used: %2 MB, Free: %3 MB")
               .arg(total / (1024 * 1024))
               .arg(used / (1024 * 1024))
               .arg(free / (1024 * 1024));
    }
    return "Unknown";
}

QString DesktopInfo::getDiskSpaceInfo()
{
    QStorageInfo storage = QStorageInfo::root();
    if (storage.isValid()) {
        qint64 total = storage.bytesTotal();
        qint64 available = storage.bytesAvailable();
        qint64 used = total - available;
        
        return QString("Total: %1 GB, Used: %2 GB, Available: %3 GB")
               .arg(total / (1024 * 1024 * 1024))
               .arg(used / (1024 * 1024 * 1024))
               .arg(available / (1024 * 1024 * 1024));
    }
    return "Unknown";
} 