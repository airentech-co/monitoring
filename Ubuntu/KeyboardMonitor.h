#ifndef KEYBOARDMONITOR_H
#define KEYBOARDMONITOR_H

#include <QObject>
#include <QThread>
#include <QMap>
#include <QString>
#include <QDateTime>

struct KeyboardDevice {
    QString devicePath;
    QString deviceName;
    int fd;
    bool shiftPressed;
    bool ctrlPressed;
    bool altPressed;
    bool capsLockOn;
};

class KeyboardMonitor : public QObject
{
    Q_OBJECT

public:
    explicit KeyboardMonitor(QObject *parent = nullptr);
    ~KeyboardMonitor();

    bool startMonitoring();
    void stopMonitoring();
    void handleUsbEvent();

signals:
    void keyPressed(qint64 timestamp, const QString &windowTitle, const QString &keyText);

private slots:
    void readEvents();

private:
    void findKeyboardDevices();
    bool isKeyboardDevice(const QString &path);
    void closeAllDevices();
    QString getKeyText(int keyCode, KeyboardDevice &device);
    void handleKeyEvent(int keyCode, bool pressed, KeyboardDevice &device);
    QString getActiveWindowTitle();
    QString findDesktopFile(const QString &appClass);

    QMap<QString, KeyboardDevice> keyboardDevices;
    QThread *monitorThread;
    bool running;
};

#endif // KEYBOARDMONITOR_H 