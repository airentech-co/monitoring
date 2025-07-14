# MonitorClient macOS

A sophisticated background monitoring application for macOS that provides comprehensive system activity tracking, user behavior analysis, and remote monitoring capabilities.

## 🚀 **Overview**

MonitorClient is a professional-grade monitoring solution designed for enterprise environments, IT administration, and system monitoring. It operates silently in the background, collecting detailed information about user activities, system events, and network communications.

### **Key Features**
- **🔍 Real-time Monitoring**: Keyboard activity, browser history, USB devices, screenshots
- **🌐 Network Integration**: Seamless communication with remote monitoring servers
- **⚡ Energy Optimized**: Battery-friendly design with intelligent resource management
- **🔒 Privacy Aware**: Respects user privacy while providing comprehensive monitoring
- **🛡️ System Integration**: Deep macOS integration with proper permissions

## 📋 **System Requirements**

### **Hardware Requirements**
- **Processor**: Intel Mac or Apple Silicon (M1/M2/M3)
- **Memory**: 512MB RAM minimum, 2GB recommended
- **Storage**: 100MB available space
- **Network**: Internet connection for server communication

### **Software Requirements**
- **macOS**: 10.15 (Catalina) or later
- **Permissions**: Accessibility, Input Monitoring
- **Architecture**: Universal Binary (Intel + Apple Silicon)

## 🏗️ **Project Structure**

```
MacOS/
├── MonitorClient/                    # Main application source
│   ├── AppDelegate.swift            # Core application logic
│   ├── main.swift                   # Application entry point
│   ├── Assets.xcassets/             # Application resources
│   └── MonitorClient.entitlements   # App permissions
├── MonitorClient.xcodeproj/         # Xcode project files
├── MonitorClientTests/              # Unit tests
├── MonitorClientUITests/            # UI tests
├── package/                         # Distribution package
├── build_sign.sh                    # Build and signing script
├── component.plist                  # Package configuration
├── postinstall                      # Post-installation script
├── README.md                        # This file
├── README_SIGNING.md               # Code signing guide
└── PERFORMANCE_OPTIMIZATION_SUMMARY.md  # Performance details
```

## 🔧 **Installation**

### **Development Setup**

1. **Clone the Repository**
   ```bash
   git clone <repository-url>
   cd monitoring/MacOS
   ```

2. **Open in Xcode**
   ```bash
   open MonitorClient.xcodeproj
   ```

3. **Build and Run**
   - Select target device/simulator
   - Press `Cmd + R` to build and run
   - Or use Product → Run from menu

### **Production Deployment**

1. **Build for Distribution**
   ```bash
   ./build_sign.sh
   ```

2. **Install Package**
   ```bash
   sudo installer -pkg MonitorClient-1.0.pkg -target /
   ```

3. **Configure Permissions**
   - System Preferences → Security & Privacy → Privacy → Accessibility
   - Add MonitorClient and enable it

## ⚙️ **Configuration**

### **Server Settings**
The app connects to a monitoring server for data transmission. Configure via the menu bar settings:

1. **Open Settings**: Click menu bar icon → Settings
2. **Enter Server Details**:
   - **Username**: User identifier for the monitoring system
   - **Server IP**: IP address of the monitoring server
   - **Port**: Server port (default: 8924)

### **Monitoring Intervals**
The app uses optimized intervals for different monitoring activities:

| Activity | Interval | Description |
|----------|----------|-------------|
| Screenshots | 60 seconds | Screen captures (when active) |
| Tic Events | 5 minutes | Server heartbeat |
| Browser History | 10 minutes | Web browsing data |
| Key Logs | 5 minutes | Keyboard activity |
| USB Events | 5 minutes | Device connections |

## 🔍 **Monitoring Capabilities**

### **1. Keyboard Monitoring**
- **Real-time Capture**: Every keystroke with application context
- **Modifier Keys**: Command, Option, Control, Shift detection
- **Application Tracking**: Which app received the input
- **Timestamp**: Precise timing of each keystroke

### **2. Browser History Monitoring**
- **Supported Browsers**: Safari, Chrome, Firefox, Edge, Opera, Yandex, Vivaldi, Brave
- **Data Collected**: URLs, page titles, visit timestamps
- **Profile Support**: Multiple browser profiles
- **Secure Handling**: Temporary file copies for database access

### **3. USB Device Monitoring**
- **Device Detection**: Connection and disconnection events
- **Device Information**: Name, vendor ID, product details
- **Real-time Tracking**: Immediate event capture
- **Comprehensive Logging**: Full device lifecycle

### **4. Screenshot Capture**
- **Multi-Display Support**: All connected monitors
- **Intelligent Scheduling**: Only when system is active
- **High Quality**: Optimized JPEG compression
- **Automatic Upload**: Direct server transmission

### **5. System Activity Tracking**
- **User Sessions**: Login/logout detection
- **Application Usage**: Active application monitoring
- **Network Status**: Server connectivity tracking
- **Performance Metrics**: Resource usage monitoring

## 🛡️ **Security & Privacy**

### **Data Protection**
- **Local Encryption**: Sensitive data encrypted at rest
- **Secure Transmission**: HTTPS/SSL for all network communications
- **Access Control**: Proper macOS permission handling
- **Data Retention**: Configurable data retention policies

### **Privacy Compliance**
- **User Consent**: Clear notification of monitoring activities
- **Data Minimization**: Only necessary data collected
- **Transparency**: Detailed logging of all activities
- **User Control**: Ability to disable specific monitoring features

### **System Integration**
- **Proper Permissions**: Accessibility and Input Monitoring
- **Background Operation**: LSUIElement for silent operation
- **System Notifications**: User-friendly status updates
- **Graceful Degradation**: Continues operation with limited permissions

## 📊 **Performance Optimization**

The app has been extensively optimized for energy efficiency and system performance:

### **Energy Management**
- **Intelligent Scheduling**: Operations only when system is active
- **Battery Optimization**: Reduced activity during low power
- **Sleep Respect**: Avoids operations during system sleep
- **Resource Conservation**: Minimal CPU and memory usage

### **Network Efficiency**
- **Batched Transmissions**: Multiple events sent together
- **Compression**: Data compressed before transmission
- **Connection Pooling**: Reuse network connections
- **Retry Logic**: Automatic retry for failed transmissions

### **Storage Optimization**
- **Buffered Logging**: Reduced disk I/O operations
- **Memory Management**: Efficient array handling
- **Temporary Files**: Proper cleanup of temporary data
- **Log Rotation**: Automatic log file management

## 🔧 **Development**

### **Building from Source**

1. **Prerequisites**
   - Xcode 14.0 or later
   - macOS 12.0 or later
   - Apple Developer Account (for distribution)

2. **Build Commands**
   ```bash
   # Debug build
   xcodebuild -project MonitorClient.xcodeproj -scheme MonitorClient -configuration Debug build
   
   # Release build
   xcodebuild -project MonitorClient.xcodeproj -scheme MonitorClient -configuration Release build
   ```

3. **Code Signing**
   ```bash
   # Run the signing script
   ./build_sign.sh
   ```

### **Testing**

1. **Unit Tests**
   ```bash
   xcodebuild -project MonitorClient.xcodeproj -scheme MonitorClient -destination 'platform=macOS' test
   ```

2. **Manual Testing**
   - Test permission requests
   - Verify network connectivity
   - Check monitoring accuracy
   - Validate energy consumption

### **Debugging**

1. **Log Files**
   - Development: `./MonitorClient.log`
   - Production: `/Applications/Logs/MonitorClient.log`

2. **Console Logs**
   ```bash
   log stream --predicate 'subsystem == "com.alice.MonitorClient"'
   ```

3. **Menu Bar Status**
   - Click menu bar icon for real-time status
   - View connection status, permissions, and activity

## 🚨 **Troubleshooting**

### **Common Issues**

1. **Permission Denied**
   - **Solution**: Grant Accessibility permission in System Preferences
   - **Steps**: System Preferences → Security & Privacy → Privacy → Accessibility

2. **Server Connection Failed**
   - **Solution**: Check server IP and port settings
   - **Steps**: Menu bar → Settings → Verify server details

3. **High CPU Usage**
   - **Solution**: Check for multiple instances
   - **Steps**: Activity Monitor → Search "MonitorClient" → Force quit duplicates

4. **Battery Drain**
   - **Solution**: Verify optimized intervals are active
   - **Steps**: Check log files for performance metrics

### **Log Analysis**

1. **Error Logs**
   ```bash
   grep "ERROR" /Applications/Logs/MonitorClient.log
   ```

2. **Performance Logs**
   ```bash
   grep "Performance" /Applications/Logs/MonitorClient.log
   ```

3. **Network Logs**
   ```bash
   grep "Network" /Applications/Logs/MonitorClient.log
   ```

## 📈 **Monitoring Dashboard**

The app provides a comprehensive status dashboard accessible via the menu bar:

### **Status Information**
- **Client Details**: IP address, MAC address
- **Server Status**: Connection status, server address
- **Permissions**: Accessibility and monitoring status
- **Activity Counts**: Keys captured, USB events, screenshots
- **Last Sync**: Timestamps of last data transmission

### **Quick Actions**
- **Settings**: Configure server and monitoring options
- **Test Functions**: Verify individual monitoring components
- **Accessibility**: Quick access to system preferences
- **Quit**: Graceful application shutdown

## 🔮 **Future Enhancements**

### **Planned Features**
- **Machine Learning**: Predictive user behavior analysis
- **Advanced Analytics**: Detailed usage pattern reports
- **Mobile Integration**: iOS companion app
- **Cloud Dashboard**: Web-based monitoring interface
- **API Integration**: RESTful API for third-party tools

### **Performance Improvements**
- **Adaptive Intervals**: Dynamic monitoring frequency
- **Power Management**: Enhanced battery optimization
- **Network Optimization**: Improved data transmission
- **Storage Efficiency**: Better data compression

## 📄 **License & Legal**

### **Usage Guidelines**
- **Enterprise Use**: Designed for corporate environments
- **User Consent**: Must obtain proper user consent
- **Data Protection**: Comply with local privacy laws
- **Security**: Implement appropriate security measures

### **Compliance**
- **GDPR**: European data protection compliance
- **CCPA**: California privacy law compliance
- **HIPAA**: Healthcare data protection (if applicable)
- **SOX**: Financial data protection (if applicable)

## 🤝 **Support & Contributing**

### **Getting Help**
- **Documentation**: Check this README and related files
- **Logs**: Review application logs for detailed information
- **Testing**: Use built-in test functions for diagnostics
- **Community**: Check project issues and discussions

### **Contributing**
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Submit a pull request

### **Reporting Issues**
- **Bug Reports**: Include log files and system information
- **Feature Requests**: Describe use case and benefits
- **Performance Issues**: Provide Activity Monitor screenshots
- **Security Concerns**: Report privately to maintainers

## 📞 **Contact**

For questions, support, or contributions:
- **Project Repository**: [GitHub Repository URL]
- **Documentation**: [Documentation URL]
- **Support**: [Support Email/URL]

---

**MonitorClient macOS** - Professional monitoring solution for enterprise environments.

*Built with ❤️ for macOS* 