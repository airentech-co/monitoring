#ifndef CONFIGDIALOG_H
#define CONFIGDIALOG_H

#include <QDialog>
#include <QLineEdit>
#include <QSpinBox>
#include <QPushButton>
#include <QVBoxLayout>
#include <QHBoxLayout>
#include <QLabel>
#include <QGroupBox>
#include <QSettings>

class ConfigDialog : public QDialog
{
    Q_OBJECT

public:
    ConfigDialog(QWidget *parent = nullptr);
    ~ConfigDialog();

    QString getServerIP() const;
    int getServerPort() const;
    QString getClientName() const;

private slots:
    void onSaveClicked();
    void onCancelClicked();
    void onTestConnectionClicked();

private:
    void loadSettings();
    void saveSettings();
    bool testConnection();

    QLineEdit *serverIPEdit;
    QSpinBox *serverPortEdit;
    QLineEdit *clientNameEdit;
    QPushButton *testButton;
    QPushButton *saveButton;
    QPushButton *cancelButton;
};

#endif // CONFIGDIALOG_H 