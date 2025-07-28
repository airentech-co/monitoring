#include <QCoreApplication>
#include <QDebug>
#include <QString>
#include <QNetworkAccessManager>
#include <QNetworkReply>
#include <QJsonObject>
#include <QJsonDocument>
#include <QSqlDatabase>
#include <QSqlQuery>
#include <QSqlError>
#include <QDir>
#include <QFile>
#include <QTextStream>
#include <QStandardPaths>
#include <QStorageInfo>
#include <QSysInfo>
#include <QNetworkInterface>
#include <QDBusInterface>
#include <QDBusReply>
#include <QDBusConnection>
#include <libudev.h>

#include "utils/desktopinfo.h"
#include "functions.h"

int main(int argc, char *argv[])
{
    QCoreApplication app(argc, argv);
    
    qDebug() << "=== Ubuntu MonitorClient Test Build ===";
    qDebug() << "Qt Version:" << qVersion();
    qDebug() << "System Architecture:" << QSysInfo::currentCpuArchitecture();
    qDebug() << "Kernel Version:" << QSysInfo::kernelVersion();
    qDebug() << "Product Name:" << QSysInfo::prettyProductName();
    
    // Test desktop info
    qDebug() << "Desktop Environment:" << DesktopInfo::getDesktopEnvironment();
    qDebug() << "Display Server:" << DesktopInfo::getDisplayServer();
    qDebug() << "Hostname:" << DesktopInfo::getHostname();
    qDebug() << "Username:" << DesktopInfo::getUsername();
    
    // Test functions
    QMap<QString, QVariant> settings = loadSettings("settings.ini");
    qDebug() << "Settings loaded:" << settings.size() << "entries";
    qDebug() << "Server IP:" << getServerIP();
    qDebug() << "Server Port:" << getServerPort();
    qDebug() << "Username:" << getUsername();
    
    // Test network interfaces
    QList<QNetworkInterface> interfaces = QNetworkInterface::allInterfaces();
    qDebug() << "Network Interfaces:" << interfaces.size();
    for (const QNetworkInterface& interface : interfaces) {
        if (interface.flags().testFlag(QNetworkInterface::IsUp) && 
            !interface.flags().testFlag(QNetworkInterface::IsLoopBack)) {
            qDebug() << "  Interface:" << interface.name() 
                     << "MAC:" << interface.hardwareAddress()
                     << "IP:" << interface.addressEntries().first().ip().toString();
        }
    }
    
    // Test storage info
    QStorageInfo root = QStorageInfo::root();
    if (root.isValid()) {
        qDebug() << "Root filesystem:" << root.rootPath();
        qDebug() << "Total space:" << root.bytesTotal() / (1024*1024*1024) << "GB";
        qDebug() << "Available space:" << root.bytesAvailable() / (1024*1024*1024) << "GB";
    }
    
    // Test udev
    struct udev *udev = udev_new();
    if (udev) {
        qDebug() << "Udev initialized successfully";
        udev_unref(udev);
    } else {
        qDebug() << "Failed to initialize udev";
    }
    
    // Test DBus
    QDBusConnection connection = QDBusConnection::sessionBus();
    if (connection.isConnected()) {
        qDebug() << "DBus session bus connected";
    } else {
        qDebug() << "Failed to connect to DBus session bus";
    }
    
    qDebug() << "=== Test Build Completed Successfully ===";
    
    return 0;
} 