#ifndef FUNCTIONS_H
#define FUNCTIONS_H

#include <QString>
#include <QVariant>
#include <QMap>

// Loads settings from an INI file (e.g., settings.ini)
QMap<QString, QVariant> loadSettings(const QString& filePath);

// Returns the username from settings or environment
QString getUsername();

// Returns the client name from settings
QString getClientName();

// Returns the server IP from settings
QString getServerIP();

// Returns the server port from settings
int getServerPort();

#endif // FUNCTIONS_H 