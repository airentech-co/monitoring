#include "functions.h"
#include <QSettings>
#include <QFile>
#include <QDebug>
#include <QDir>
#include <QStandardPaths>

static QMap<QString, QVariant> g_settings;

QMap<QString, QVariant> loadSettings(const QString& filePath)
{
    QMap<QString, QVariant> settings;
    if (!QFile::exists(filePath)) {
        qWarning() << "Settings file not found:" << filePath;
        return settings;
    }
    QSettings ini(filePath, QSettings::IniFormat);
    QStringList keys = ini.allKeys();
    for (const QString& key : keys) {
        settings[key] = ini.value(key);
    }
    g_settings = settings;
    return settings;
}

QString getUsername()
{
    if (g_settings.contains("Server/username"))
        return g_settings["Server/username"].toString();
    return qgetenv("USER");
}

QString getClientName()
{
    if (g_settings.contains("Client/name"))
        return g_settings["Client/name"].toString();
    return getUsername();
}

QString getServerIP()
{
    if (g_settings.contains("Server/ip"))
        return g_settings["Server/ip"].toString();
    return "127.0.0.1";
}

int getServerPort()
{
    if (g_settings.contains("Server/port"))
        return g_settings["Server/port"].toInt();
    return 8924;
} 