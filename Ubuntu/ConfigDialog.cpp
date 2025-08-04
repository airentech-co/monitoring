#include "ConfigDialog.h"
#include <QNetworkAccessManager>
#include <QNetworkReply>
#include <QNetworkRequest>
#include <QMessageBox>
#include <QApplication>
#include <QStandardPaths>
#include <QDir>

ConfigDialog::ConfigDialog(QWidget *parent)
    : QDialog(parent)
{
    setWindowTitle("MonitorClient Configuration");
    setFixedSize(400, 300);
    setModal(true);

    // Create layout
    QVBoxLayout *mainLayout = new QVBoxLayout(this);

    // Server settings group
    QGroupBox *serverGroup = new QGroupBox("Server Settings");
    QVBoxLayout *serverLayout = new QVBoxLayout(serverGroup);

    // Server IP
    QHBoxLayout *ipLayout = new QHBoxLayout();
    QLabel *ipLabel = new QLabel("Server IP:");
    serverIPEdit = new QLineEdit();
    ipLayout->addWidget(ipLabel);
    ipLayout->addWidget(serverIPEdit);
    serverLayout->addLayout(ipLayout);

    // Server Port
    QHBoxLayout *portLayout = new QHBoxLayout();
    QLabel *portLabel = new QLabel("Server Port:");
    serverPortEdit = new QSpinBox();
    serverPortEdit->setRange(1, 65535);
    serverPortEdit->setValue(8924);
    portLayout->addWidget(portLabel);
    portLayout->addWidget(serverPortEdit);
    serverLayout->addLayout(portLayout);

    // Client Name
    QHBoxLayout *nameLayout = new QHBoxLayout();
    QLabel *nameLabel = new QLabel("Client Name:");
    clientNameEdit = new QLineEdit();
    nameLayout->addWidget(nameLabel);
    nameLayout->addWidget(clientNameEdit);
    serverLayout->addLayout(nameLayout);

    // Test connection button
    testButton = new QPushButton("Test Connection");
    serverLayout->addWidget(testButton);

    mainLayout->addWidget(serverGroup);

    // Buttons
    QHBoxLayout *buttonLayout = new QHBoxLayout();
    saveButton = new QPushButton("Save");
    cancelButton = new QPushButton("Cancel");
    buttonLayout->addWidget(saveButton);
    buttonLayout->addWidget(cancelButton);
    mainLayout->addLayout(buttonLayout);

    // Connect signals
    connect(saveButton, &QPushButton::clicked, this, &ConfigDialog::onSaveClicked);
    connect(cancelButton, &QPushButton::clicked, this, &ConfigDialog::onCancelClicked);
    connect(testButton, &QPushButton::clicked, this, &ConfigDialog::onTestConnectionClicked);

    // Load current settings
    loadSettings();
}

ConfigDialog::~ConfigDialog()
{
}

QString ConfigDialog::getServerIP() const
{
    return serverIPEdit->text();
}

int ConfigDialog::getServerPort() const
{
    return serverPortEdit->value();
}

QString ConfigDialog::getClientName() const
{
    return clientNameEdit->text();
}

void ConfigDialog::loadSettings()
{
    QString settingsPath = QStandardPaths::writableLocation(QStandardPaths::ConfigLocation) + "/monitorclient/settings.ini";
    if (!QFile::exists(settingsPath)) {
        settingsPath = "settings.ini"; // Fallback to local file
    }

    QSettings settings(settingsPath, QSettings::IniFormat);
    
    serverIPEdit->setText(settings.value("Server/ip", "192.168.1.45").toString());
    serverPortEdit->setValue(settings.value("Server/port", 8924).toInt());
    clientNameEdit->setText(settings.value("Client/name", qgetenv("USER")).toString());
}

void ConfigDialog::saveSettings()
{
    QString configDir = QStandardPaths::writableLocation(QStandardPaths::ConfigLocation) + "/monitorclient";
    QDir().mkpath(configDir);
    QString settingsPath = configDir + "/settings.ini";

    QSettings settings(settingsPath, QSettings::IniFormat);
    
    settings.setValue("Server/ip", serverIPEdit->text());
    settings.setValue("Server/port", serverPortEdit->value());
    settings.setValue("Client/name", clientNameEdit->text());
    
    // Also save to local settings.ini for backward compatibility
    QSettings localSettings("settings.ini", QSettings::IniFormat);
    localSettings.setValue("Server/ip", serverIPEdit->text());
    localSettings.setValue("Server/port", serverPortEdit->value());
    localSettings.setValue("Client/name", clientNameEdit->text());
}

bool ConfigDialog::testConnection()
{
    QNetworkAccessManager *manager = new QNetworkAccessManager(this);
    QString url = QString("http://%1:%2").arg(serverIPEdit->text()).arg(serverPortEdit->value());
    
    QNetworkRequest request(url);
    QNetworkReply *reply = manager->get(request);
    
    QEventLoop loop;
    connect(reply, &QNetworkReply::finished, &loop, &QEventLoop::quit);
    loop.exec();
    
    bool success = (reply->error() == QNetworkReply::NoError);
    reply->deleteLater();
    manager->deleteLater();
    
    return success;
}

void ConfigDialog::onSaveClicked()
{
    if (serverIPEdit->text().isEmpty()) {
        QMessageBox::warning(this, "Error", "Server IP cannot be empty!");
        return;
    }
    
    saveSettings();
    accept();
}

void ConfigDialog::onCancelClicked()
{
    reject();
}

void ConfigDialog::onTestConnectionClicked()
{
    testButton->setEnabled(false);
    testButton->setText("Testing...");
    
    bool success = testConnection();
    
    testButton->setEnabled(true);
    testButton->setText("Test Connection");
    
    if (success) {
        QMessageBox::information(this, "Success", "Connection test successful!");
    } else {
        QMessageBox::warning(this, "Error", "Connection test failed!\nPlease check server IP and port.");
    }
} 