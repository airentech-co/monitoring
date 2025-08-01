//
//  AppDelegate.swift
//  MonitorClient
//
//  Created by Uranus on 7/5/25.
//  Updated by Uranus on 7/6/25.
//

import SQLite3
import Cocoa
import CoreGraphics
import IOKit
import IOKit.usb
import os.log

let CHECKER_IDENTIFIER = "com.airentech.MonitorClient"

let API_ROUTE = "/webapi.php"
let TIC_ROUTE = "/eventhandler.php"
var APP_VERSION = "1.0"

var TIME_INTERVAL = 60      // Screenshots every 60 seconds (optimized from 1)
let TIC_INTERVAL = 300      // Tic every 5 minutes (optimized from 30 seconds)
let HISTORY_INTERVAL = 600  // History every 10 minutes (optimized from 2 minutes)
let KEY_INTERVAL = 300      // Keys every 5 minutes (optimized from 1 minute)
var timer: Timer? = nil
var macAddress: String = ""
var activeRunning: Bool = false
var safariChecked: Double? = nil
var chromeChecked: Int64? = nil
var firefoxChecked: Int64? = nil
var edgeChecked: Int64? = nil
var operaChecked: Int64? = nil
var yandexChecked: Int64? = nil
var vivaldiChecked: Int64? = nil
var braveChecked: Int64? = nil
var lastBrowserTic: Double? = nil
var lastScreenshotCheck: Date = Date()
var lastHistoryCheck: Date = Date()
var lastTicCheck: Date = Date()
var lastKeyCheck: Date = Date()

// Browser bundle identifiers
private let browserBundleIDs = [
    "com.apple.Safari",
    "com.google.Chrome",
    "org.mozilla.firefox",
    "com.microsoft.edgemac",
    "com.operasoftware.Opera",
    "ru.yandex.desktop.yandex-browser",
    "com.vivaldi.Vivaldi",
    "com.brave.Browser"
]

struct BrowserHistoryLog: Codable {
    let browser: String
    let url: String
    let title: String
    let last_visit: Int64
    let date: String
}

struct KeyLog: Codable {
    let date: String
    let application: String
    let key: String
}

struct USBDeviceLog: Codable {
    let date: String
    let device_name: String
    let device_path: String
    let device_type: String
    let action: String
}

// Global callback function for CGEvent tap
func handleKeyDownCallback(
    proxy: CGEventTapProxy,
    type: CGEventType,
    event: CGEvent,
    refcon: UnsafeMutableRawPointer?
) -> Unmanaged<CGEvent>? {
    // Only handle keyDown events
    guard type == .keyDown else {
        return Unmanaged.passUnretained(event)
    }
    
    // Get the AppDelegate instance from the refcon
    guard let appDelegate = Unmanaged<AppDelegate>.fromOpaque(refcon!).takeUnretainedValue() as? AppDelegate else {
        return Unmanaged.passUnretained(event)
    }
    
    // Update last key capture time
    appDelegate.lastKeyCaptureTime = Date()
    
    // Safely create NSEvent from CGEvent
    guard let nsEvent = NSEvent(cgEvent: event) else {
        return Unmanaged.passUnretained(event)
    }
    
    // Get the active application
    let activeApp = NSWorkspace.shared.frontmostApplication
    let appName = activeApp?.localizedName ?? "Unknown"
    let bundleId = activeApp?.bundleIdentifier ?? "Unknown"
    
    // Get key information
    let keyCode = event.getIntegerValueField(.keyboardEventKeycode)
    let modifiers = appDelegate.checkModifierKeys(event)
    let pressedChar = nsEvent.charactersIgnoringModifiers ?? ""
    let currentDate = appDelegate.getCurrentDateTimeString()
    
    // Get readable key name for special keys
    let keyName = appDelegate.getKeyName(keyCode: Int(keyCode), character: pressedChar)
    
    let keyInfo = "\(appName) (\(bundleId)) \(modifiers)\(keyName)"
    appDelegate.logMessage("Key captured: \(keyInfo)", level: .debug)
    appDelegate.keyLogs.append(KeyLog(date: currentDate, application: "\(appName) (\(bundleId))", key: "\(modifiers)\(keyName)"))
    
    // Log key log count for debugging
    if appDelegate.keyLogs.count % 10 == 0 {
        appDelegate.logMessage("Key log count: \(appDelegate.keyLogs.count)", level: .debug)
    }
    
    return Unmanaged.passUnretained(event)
}

class AppDelegate: NSObject, NSApplicationDelegate, NSUserNotificationCenterDelegate {
    
    var storage: UserDefaults!
    var eventTap: CFMachPort?
    var keyLogs: [KeyLog]!
    var usbDeviceLogs: [USBDeviceLog]!
    var usbNotificationPort: IONotificationPortRef?
    var usbAddedIterator: io_iterator_t = 0
    var usbRemovedIterator: io_iterator_t = 0
    
    // Status bar menu components
    var statusBarItem: NSStatusItem?
    var statusMenu: NSMenu?
    var statusUpdateTimer: Timer?

    // Add these properties at the top of the class
    private var lastEventTapCheck: Date = Date()
    private var eventTapCheckInterval: TimeInterval = 30.0 // Check every 30 seconds (optimized from 3)
    private var isReestablishingEventTap: Bool = false
    var lastKeyCaptureTime: Date = Date()
    private let maxKeyCaptureGap: TimeInterval = 60.0 // Alert if no keys for 60 seconds (optimized from 10)
    
    // Last sent times for status tracking
    private var lastKeyLogSent: Date?
    private var lastBrowserHistorySent: Date?
    private var lastUSBLogSent: Date?
    private var lastScreenshotSent: Date?
    private var lastTicSent: Date?
    
    // Server connectivity tracking
    private var isServerConnected: Bool = false
    private var wasServerConnected: Bool = false // Track previous state for notifications
    
    // Client information (cached)
    private var clientIPAddress: String = "Unknown"

    // Logging system (optimized with buffering)
    let logFile = "MonitorClient.log"
    var logFileHandle: FileHandle?
    private var logBuffer: [String] = []
    private var logFlushTimer: Timer?
    private let maxLogBufferSize = 50

    // MARK: - Logging Functions
    
    private func setupLogging() {
        // Determine if we're in development or production mode
        let isDevelopment = Bundle.main.bundlePath.contains("DerivedData") || Bundle.main.bundlePath.contains("Debug")
        
        var logURL: URL?
        var logFileCreated = false
        
        if isDevelopment {
            // Development mode: save to local folder (current directory)
            let currentDir = FileManager.default.currentDirectoryPath
            logURL = URL(fileURLWithPath: currentDir).appendingPathComponent(logFile)
            
            do {
                if !FileManager.default.fileExists(atPath: logURL!.path) {
                    try "".write(to: logURL!, atomically: true, encoding: .utf8)
                }
                try "test".write(to: logURL!, atomically: true, encoding: .utf8)
                logFileCreated = true
                print("✅ Development mode: Log file created at: \(logURL!.path)")
            } catch {
                print("⚠️  Failed to create log file in development mode: \(error)")
            }
        } else {
            // Production mode: save to Applications folder
            let appBundlePath = Bundle.main.bundlePath
            let appContainerURL = URL(fileURLWithPath: appBundlePath).deletingLastPathComponent()
            let logDirPath = appContainerURL.appendingPathComponent("Logs").path
            
            // Create Logs directory if it doesn't exist
            if !FileManager.default.fileExists(atPath: logDirPath) {
                do {
                    try FileManager.default.createDirectory(atPath: logDirPath, withIntermediateDirectories: true, attributes: nil)
                } catch {
                    print("⚠️  Failed to create logs directory: \(error)")
                }
            }
            
            logURL = URL(fileURLWithPath: logDirPath).appendingPathComponent(logFile)
            
            do {
                if !FileManager.default.fileExists(atPath: logURL!.path) {
                    try "".write(to: logURL!, atomically: true, encoding: .utf8)
                }
                try "test".write(to: logURL!, atomically: true, encoding: .utf8)
                logFileCreated = true
                print("✅ Production mode: Log file created at: \(logURL!.path)")
            } catch {
                print("⚠️  Failed to create log file in production mode: \(error)")
                
                // Fallback to Documents folder if Applications folder is not writable
                let documentsPath = NSHomeDirectory() + "/Documents"
                let fallbackURL = URL(fileURLWithPath: documentsPath).appendingPathComponent(logFile)
                
                do {
                    if !FileManager.default.fileExists(atPath: fallbackURL.path) {
                        try "".write(to: fallbackURL, atomically: true, encoding: .utf8)
                    }
                    try "test".write(to: fallbackURL, atomically: true, encoding: .utf8)
                    logURL = fallbackURL
                    logFileCreated = true
                    print("✅ Fallback: Log file created in Documents folder: \(fallbackURL.path)")
                } catch {
                    print("⚠️  Failed to create log file in fallback location: \(error)")
                }
            }
        }
        
        // If we couldn't create a log file, use console only
        if !logFileCreated {
            print("⚠️  Could not create log file. Using console logging only.")
            logFileHandle = nil
        } else {
            // Open file handle for writing
            do {
                logFileHandle = try FileHandle(forWritingTo: logURL!)
                logFileHandle?.seekToEndOfFile()
                print("✅ Log file handle opened successfully")
            } catch {
                print("⚠️  Could not open log file handle: \(error). Using console logging only.")
                logFileHandle = nil
            }
        }
        
        // Always log startup information
        let username = storage.string(forKey: "username") ?? NSUserName()
        let startupInfo = """
        === MonitorClient Started ===
        Version: \(APP_VERSION)
        Mode: \(isDevelopment ? "Development" : "Production")
        Username: \(username)
        Mac Address: \(macAddress)
        Server IP: \(storage.string(forKey: "server-ip") ?? "unknown")
        Log file location: \(logURL?.path ?? "console only")
        Current directory: \(FileManager.default.currentDirectoryPath)
        Home directory: \(NSHomeDirectory())
        """
        
        print(startupInfo)
        
        // Write to log file if available
        if let data = startupInfo.data(using: .utf8) {
            logFileHandle?.write(data)
            logFileHandle?.synchronizeFile()
        }
        
        // System log
        let osLog = OSLog(subsystem: "com.alice.MonitorClient", category: "monitoring")
        os_log("MonitorClient started - Version: %{public}@, Username: %{public}@, Server: %{public}@", log: osLog, type: .info, APP_VERSION, username, storage.string(forKey: "server-ip") ?? "unknown")
    }
    
    func logMessage(_ message: String, level: LogLevel = .info) {
        let timestamp = getCurrentDateTimeString()
        let logEntry = "[\(timestamp)] [\(level.rawValue)] \(message)\n"
        
        // Console output - always print for debugging
        print(logEntry.trimmingCharacters(in: .whitespacesAndNewlines))
        
        // Buffered file logging (optimized)
        logBuffer.append(logEntry)
        
        // Flush buffer if it gets too large or for important messages
        if logBuffer.count >= maxLogBufferSize || level == .error {
            flushLogBuffer()
        }
        
        // System log
        let osLog = OSLog(subsystem: "com.alice.MonitorClient", category: "monitoring")
        os_log("%{public}@", log: osLog, type: level.osLogType, message)
    }
    
    private func flushLogBuffer() {
        guard !logBuffer.isEmpty else { return }
        
        let combinedLogs = logBuffer.joined()
        if let data = combinedLogs.data(using: .utf8) {
            logFileHandle?.write(data)
            logFileHandle?.synchronizeFile()
        }
        logBuffer.removeAll()
    }
    

    
    enum LogLevel: String {
        case debug = "DEBUG"
        case info = "INFO"
        case warning = "WARN"
        case error = "ERROR"
        
        var osLogType: OSLogType {
            switch self {
            case .debug: return .debug
            case .info: return .info
            case .warning: return .default
            case .error: return .error
            }
        }
    }
    
    private func logMonitoringEvent(_ event: String, details: String? = nil) {
        let message = details != nil ? "\(event): \(details!)" : event
        logMessage(message, level: .info)
    }
    
    private func logError(_ error: String, context: String? = nil) {
        let message = context != nil ? "[\(context!)] \(error)" : error
        logMessage(message, level: .error)
    }
    
    private func logSuccess(_ action: String, details: String? = nil) {
        let message = details != nil ? "✅ \(action): \(details!)" : "✅ \(action)"
        logMessage(message, level: .info)
    }

    func applicationDidFinishLaunching(_ aNotification: Notification) {
        // Insert code here to initialize your application
        
        print("=== MonitorClient Starting ===")
        print("App delegate initialized")
        print("App bundle path: \(Bundle.main.bundlePath)")
        print("App is running from: \(ProcessInfo.processInfo.arguments.first ?? "unknown")")
        
        // Check if running from distributed app
        let isDistributed = !Bundle.main.bundlePath.contains("DerivedData")
        print("Is distributed app: \(isDistributed)")
        
        do {
            // Since this is a background app (LSUIElement), ensure it doesn't quit when all windows are closed
            NSApp.setActivationPolicy(.accessory)
            print("Set activation policy to accessory")
            
            // Prevent any windows from being created
            NSApp.windows.forEach { window in
                window.close()
            }
            print("Closed any existing windows")
        } catch {
            print("ERROR during app initialization: \(error)")
        }
        
        storage = UserDefaults.init(suiteName: "alice.monitors")

        // Load settings from file
        loadSettings()

        // Configure server IP if not already set
        if storage.string(forKey: "server-ip") == nil {
            storage.set("192.168.1.45:8924", forKey: "server-ip")
            logMessage("Server IP configured: 192.168.1.45:8924", level: .info)
        } else {
            logMessage("Server IP already configured: \(storage.string(forKey: "server-ip") ?? "unknown")", level: .info)
        }
        
        // Only reset accessibility prompt flag if permission was revoked
        let wasPreviouslyGranted = storage.bool(forKey: "accessibility-was-granted")
        let currentPermissionGranted = AXIsProcessTrusted()
        
        if wasPreviouslyGranted && !currentPermissionGranted {
            // Permission was revoked, reset the prompt flag so we can show instructions again
            storage.removeObject(forKey: "accessibility-prompt-shown")
            logMessage("Accessibility permission was revoked, resetting prompt flag", level: .warning)
        } else if !wasPreviouslyGranted && currentPermissionGranted {
            // Permission was newly granted, update the flag
            storage.set(true, forKey: "accessibility-was-granted")
            logMessage("Accessibility permission newly granted", level: .info)
        }
        
        // Log current permission status
        logMessage("Accessibility permission status: \(currentPermissionGranted ? "GRANTED" : "DENIED") (was previously granted: \(wasPreviouslyGranted))", level: .info)
        
        // Check screen recording permission status on startup
        if #available(macOS 10.15, *) {
            let screenRecordingWasGranted = storage.bool(forKey: "screen-recording-was-granted")
            let screenRecordingCurrentGranted = CGPreflightScreenCaptureAccess()
            
            if screenRecordingWasGranted && !screenRecordingCurrentGranted {
                // Permission was revoked, reset the prompt flag so we can show instructions again
                storage.removeObject(forKey: "screen-recording-prompt-shown")
                logMessage("Screen recording permission was revoked, resetting prompt flag", level: .warning)
            } else if !screenRecordingWasGranted && screenRecordingCurrentGranted {
                // Permission was newly granted, update the flag
                storage.set(true, forKey: "screen-recording-was-granted")
                logMessage("Screen recording permission newly granted", level: .info)
            }
            
            // Log current screen recording permission status
            logMessage("Screen recording permission status: \(screenRecordingCurrentGranted ? "GRANTED" : "DENIED") (was previously granted: \(screenRecordingWasGranted))", level: .info)
        }

        APP_VERSION = Bundle.main.infoDictionary?["CFBundleShortVersionString"] as? String ?? "Unknown"

        keyLogs = []
        
        // Get MacAddress
        let address = getMacAddress()
        if !address.isEmpty {
            macAddress = address
            logMessage("Mac Address obtained: \(address)", level: .info)
        } else {
            logMessage("Warning: Could not get MacAddress", level: .warning)
            macAddress = ""
        }
        
        // Get Client IP Address (once at startup)
        clientIPAddress = getClientIPAddress()
        logMessage("Client IP Address obtained: \(clientIPAddress)", level: .info)

        // Setup logging after basic initialization
        setupLogging()

        // Check if MonitorChecker is running and start it if not
        logMonitoringEvent("Checking MonitorChecker")
        checkMonitorChecker()
        
        // Start keyboard monitoring
        logMonitoringEvent("Starting keyboard monitoring")
        
        // Check if app has proper entitlements
        if let bundleIdentifier = Bundle.main.bundleIdentifier {
            logMessage("App bundle identifier: \(bundleIdentifier)", level: .debug)
        }
        
        startKeyboardMonitoring();
        
        // Setup event tap invalidation monitoring
        logMonitoringEvent("Setting up event tap monitoring")
        setupEventTapInvalidationMonitoring()
        
        // Single timer for all tasks (optimized interval)
        timer = Timer.scheduledTimer(timeInterval: 5.0, target: self, selector: #selector(checkAllTasks), userInfo: nil, repeats: true)
        logSuccess("Main monitoring timer started")
        
        let center = NSWorkspace.shared.notificationCenter
        center.addObserver(self, selector: #selector(sessionDidBecomeActive), name: NSWorkspace.sessionDidBecomeActiveNotification, object: nil)
        center.addObserver(self, selector: #selector(sessionDidResignActive), name: NSWorkspace.sessionDidResignActiveNotification, object: nil)
        activeRunning = true
        
        // Add observer for application termination
        NSWorkspace.shared.notificationCenter.addObserver(
            self,
            selector: #selector(applicationDidTerminate(_:)),
            name: NSWorkspace.didTerminateApplicationNotification,
            object: nil
        )
        
        usbDeviceLogs = []
        logMonitoringEvent("Setting up USB monitoring")
        setupUSBMonitoring()
        
        // Send all monitoring requests on startup for quick verification
        DispatchQueue.main.asyncAfter(deadline: .now() + 2.0) {
            self.performStartupTests()
        }
        
        // Debug accessibility status on startup
        DispatchQueue.main.asyncAfter(deadline: .now() + 2.0) {
            self.debugAccessibilityStatus()
        }
        
        // Check app entitlements on startup
        DispatchQueue.main.asyncAfter(deadline: .now() + 3.0) {
            self.checkAppEntitlements()
        }
        
        // Set up notification delegate
        NSUserNotificationCenter.default.delegate = self
        
        // Setup status bar menu
        do {
            setupStatusBarMenu()
            print("Status bar menu setup completed successfully")
        } catch {
            print("ERROR setting up status bar menu: \(error)")
        }
        
        // Setup log flush timer (flush logs every 30 seconds)
        logFlushTimer = Timer.scheduledTimer(timeInterval: 30.0, target: self, selector: #selector(flushLogsPeriodically), userInfo: nil, repeats: true)
        
        logSuccess("MonitorClient initialization complete")
        print("=== MonitorClient Startup Complete ===")
        print("Status bar icon should be visible in menu bar")
        print("Check for 🗒️ icon in the top-right menu bar")
        
        // Show a test notification to verify app is running
        let notification = NSUserNotification()
        notification.title = "MonitorClient Started"
        notification.informativeText = "App is running in background. Look for 🗒️ icon in menu bar."
        notification.soundName = NSUserNotificationDefaultSoundName
        NSUserNotificationCenter.default.deliver(notification)
        
        // Check permissions and log status
        checkAndLogPermissions()
    }
    
    @objc func sessionDidBecomeActive(notification: Notification) {
        logMessage("User switched back to this session", level: .info)
        activeRunning = true
    }

    @objc func sessionDidResignActive(notification: Notification) {
        logMessage("User switched away from this session", level: .info)
        activeRunning = false
    }
    
    @objc func flushLogsPeriodically() {
        flushLogBuffer()
    }
    
    // Perform all monitoring tests on startup for quick verification
    @objc func performStartupTests() {
        logMessage("=== Starting Startup Tests ===", level: .info)
        
        // Test 1: Server connectivity (Tic event)
        logMessage("Test 1: Sending tic event for server connectivity", level: .info)
        DispatchQueue.global(qos: .background).async {
            do {
                try self.sendTicEvent()
            } catch {
                self.logError("Startup tic event failed: \(error)", context: "StartupTest")
            }
        }
        
        // Test 2: Screenshot capability
        logMessage("Test 2: Taking screenshot for permission verification", level: .info)
        DispatchQueue.global(qos: .background).async {
            // Check screen recording permission first
            if self.isScreenRecordingEnabled() {
                self.TakeScreenShotsAndPost()
            } else {
                self.logMessage("Screen recording permission not granted - requesting...", level: .warning)
                self.requestScreenRecordingPermission()
            }
        }
        
        // Test 3: Browser history collection
        logMessage("Test 3: Collecting browser history for access verification", level: .info)
        DispatchQueue.global(qos: .background).async {
            do {
                try self.sendBrowserHistories()
            } catch {
                self.logError("Startup browser history failed: \(error)", context: "StartupTest")
            }
        }
        
        // Test 4: Key logs (if any captured)
        logMessage("Test 4: Sending key logs for keyboard monitoring verification", level: .info)
        DispatchQueue.global(qos: .background).async {
            self.sendKeyLogs()
        }
        
        // Test 5: USB logs (if any captured)
        logMessage("Test 5: Sending USB logs for device monitoring verification", level: .info)
        DispatchQueue.global(qos: .background).async {
            do {
                try self.sendUSBLogs()
            } catch {
                self.logError("Startup USB logs failed: \(error)", context: "StartupTest")
            }
        }
        
        // Test 6: Server connectivity test
        logMessage("Test 6: Testing server connectivity", level: .info)
        DispatchQueue.global(qos: .background).async {
            self.testServerConnectivity()
        }
        
        logMessage("=== Startup Tests Initiated ===", level: .info)
        logMessage("All tests will complete within 5-10 seconds", level: .info)
        
        // Show notification about startup tests
        let notification = NSUserNotification()
        notification.title = "MonitorClient Startup Tests"
        notification.informativeText = "Running all monitoring tests to verify permissions and server connectivity. Check logs for results."
        notification.soundName = nil
        NSUserNotificationCenter.default.deliver(notification)
    }
    
    // Check if system is idle before performing heavy operations
    private func shouldPerformHeavyOperation() -> Bool {
        // Check for any recent user activity (keyboard, mouse, etc.)
        let lastKeyActivity = CGEventSource.secondsSinceLastEventType(.combinedSessionState, eventType: .keyDown)
        let lastMouseActivity = CGEventSource.secondsSinceLastEventType(.combinedSessionState, eventType: .leftMouseDown)
        
        // Consider system active if there was any input in the last 5 minutes
        return lastKeyActivity < 300 || lastMouseActivity < 300
    }
    
    // Check if screen recording permission is granted
    private func isScreenRecordingEnabled() -> Bool {
        if #available(macOS 10.15, *) {
            return CGPreflightScreenCaptureAccess()
        } else {
            // Fallback for older macOS versions
            return true
        }
    }
    
    // Request screen recording permission
    private func requestScreenRecordingPermission() {
        if #available(macOS 10.15, *) {
            // Check if we already have permission
            if CGPreflightScreenCaptureAccess() {
                logMessage("Screen recording permission already granted", level: .info)
                storage.set(true, forKey: "screen-recording-was-granted")
                return
            }
            
            // Check if permission was previously granted
            let wasPreviouslyGranted = storage.bool(forKey: "screen-recording-was-granted")
            
            // Only prompt once per session to avoid spam, unless permission was revoked
            let promptKey = "screen-recording-prompt-shown"
            if storage.bool(forKey: promptKey) && !wasPreviouslyGranted {
                logMessage("Screen recording prompt already shown this session and permission was never granted, skipping", level: .info)
                return
            }
            
            logMessage("Requesting screen recording permission...", level: .info)
            
            // Show detailed notification based on previous state
            let notification = NSUserNotification()
            notification.title = "MonitorClient - Screen Recording Permission Required"
            
            if wasPreviouslyGranted {
                // App was previously granted but permission was revoked
                notification.informativeText = """
                Screen recording permission was revoked. To restore monitoring:
                
                1. Open System Preferences > Security & Privacy > Privacy > Screen Recording
                2. Find "MonitorClient" in the list
                3. Click the "-" button to remove it
                4. Click the "+" button and add MonitorClient again
                5. Restart the MonitorClient app
                
                This will restore screenshot monitoring functionality.
                """
                logMessage("Screen recording permission was previously granted but has been revoked", level: .warning)
            } else {
                // First time requesting permission
                notification.informativeText = """
                MonitorClient needs screen recording permission to capture screenshots.
                
                To grant permission:
                1. Open System Preferences > Security & Privacy > Privacy > Screen Recording
                2. Click the lock icon and enter your password
                3. Click the "+" button and add MonitorClient
                4. Check the box next to MonitorClient
                5. Restart the MonitorClient app
                
                Without this permission, screenshot monitoring will not work.
                """
                logMessage("First time requesting screen recording permission", level: .info)
            }
            
            notification.soundName = NSUserNotificationDefaultSoundName
            NSUserNotificationCenter.default.deliver(notification)
            
            // Request permission
            CGRequestScreenCaptureAccess()
            
            // Check if permission was granted after request
            DispatchQueue.main.asyncAfter(deadline: .now() + 1.0) {
                if CGPreflightScreenCaptureAccess() {
                    self.logMessage("Screen recording permission granted", level: .info)
                    self.storage.set(true, forKey: "screen-recording-was-granted")
                    // Clear the prompt flag when permission is granted so we can show it again if revoked
                    self.storage.removeObject(forKey: promptKey)
                } else {
                    self.logMessage("Screen recording permission denied - detailed instructions sent via notification", level: .warning)
                    // Mark that we've shown the prompt (only if denied)
                    self.storage.set(true, forKey: promptKey)
                }
            }
            
            logMessage("Screen recording permission request sent", level: .info)
        } else {
            logMessage("Screen recording permission not required on this macOS version", level: .info)
        }
    }

    @objc func checkAllTasks() {
        let currentDate = Date()
        
        // Check accessibility permission periodically
        checkAccessibilityPermissionPeriodically()
        
        // Check screen recording permission periodically
        checkScreenRecordingPermissionPeriodically()
        
        // Add event tap validation check to main timer loop
        checkAndReestablishEventTapThrottled()
                
        // Check if it's time for screenshots (with activity check)
        if currentDate.timeIntervalSince(lastScreenshotCheck) >= TimeInterval(TIME_INTERVAL) {
            if shouldPerformHeavyOperation() {
                logMonitoringEvent("Screenshot monitoring triggered", details: "Interval: \(TIME_INTERVAL)s")
                let randomDelay = Double.random(in: 0...Double(TIME_INTERVAL))
                perform(#selector(TakeScreenShotsAndPost), with: nil, afterDelay: randomDelay)
            } else {
                logMessage("Skipping screenshot - system appears idle", level: .debug)
            }
            lastScreenshotCheck = currentDate
        }
        
        // Check if it's time for tic event
        if currentDate.timeIntervalSince(lastTicCheck) >= TimeInterval(TIC_INTERVAL) {
            logMonitoringEvent("Tic event monitoring triggered", details: "Interval: \(TIC_INTERVAL)s")
            DispatchQueue.global(qos: .background).async {
                do {
                    try self.sendTicEvent()
                } catch {
                    self.logError("Error sending tic event: \(error)", context: "TicEvent")
                }
            }
            lastTicCheck = currentDate
        }

        // Check if it's time for browser history
        if currentDate.timeIntervalSince(lastHistoryCheck) >= TimeInterval(HISTORY_INTERVAL) {
                logMonitoringEvent("Browser history monitoring triggered", details: "Interval: \(HISTORY_INTERVAL)s")
                DispatchQueue.global(qos: .background).async {
                    do {
                        try self.sendBrowserHistories()
                    } catch {
                        self.logError("Error sending browser histories: \(error)", context: "BrowserHistory")
                    }
                }
                lastHistoryCheck = currentDate
        }

        // Check if it's time for key log
        if currentDate.timeIntervalSince(lastKeyCheck) >= TimeInterval(KEY_INTERVAL) {
            logMonitoringEvent("Key log monitoring triggered", details: "Interval: \(KEY_INTERVAL)s, Keys collected: \(keyLogs.count)")
            DispatchQueue.global(qos: .background).async {
                self.sendKeyLogs()
            }
            logMonitoringEvent("USB log monitoring triggered", details: "USB events collected: \(usbDeviceLogs.count)")
            DispatchQueue.global(qos: .background).async {
                do {
                    try self.sendUSBLogs()
                } catch {
                    self.logError("Error sending usb logs: \(error)", context: "USBLog")
                }
            }
            lastKeyCheck = currentDate
        }
    }

    /// Throttled event tap validation to prevent app from getting stuck
    private func checkAndReestablishEventTapThrottled() {
        let currentDate = Date()
        
        // Check 1: Regular interval check (every 3 seconds)
        let shouldCheckInterval = currentDate.timeIntervalSince(lastEventTapCheck) >= eventTapCheckInterval
        
        // Check 2: Key capture gap detection (if no keys for 10 seconds)
        let keyCaptureGap = currentDate.timeIntervalSince(lastKeyCaptureTime)
        let shouldCheckKeyGap = keyCaptureGap >= maxKeyCaptureGap
        
        // Only proceed if one of the conditions is met
        guard shouldCheckInterval || shouldCheckKeyGap else {
            return
        }
        
        // Prevent multiple simultaneous re-establishment attempts
        guard !isReestablishingEventTap else {
            return
        }
        
        // Additional check: only re-establish if we have accessibility permission
        guard isInputMonitoringEnabled() else {
            // Don't log this message repeatedly to avoid spam
            let lastSkipLog = storage.double(forKey: "last-skip-log-time")
            let currentTime = Date().timeIntervalSince1970
            if currentTime - lastSkipLog > 30 { // Only log every 30 seconds
                logMessage("Skipping event tap check - no accessibility permission", level: .debug)
                storage.set(currentTime, forKey: "last-skip-log-time")
            }
            return
        }
        
        lastEventTapCheck = currentDate
        
        // Log the reason for checking (but less frequently)
        if shouldCheckKeyGap && keyCaptureGap.truncatingRemainder(dividingBy: 30) < 1 {
            logMessage("No key capture detected for \(Int(keyCaptureGap))s, checking event tap...", level: .debug)
        }
        
        // Perform the check asynchronously to avoid blocking the main thread
        DispatchQueue.global(qos: .utility).async { [weak self] in
            self?.performEventTapValidation()
        }
    }
    
    /// Perform actual event tap validation (called on background queue)
    private func performEventTapValidation() {
        // Quick check first - if event tap is nil, we need to re-establish
        guard let tap = eventTap else {
            logMessage("Event tap is nil, re-establishing...", level: .info)
            reestablishEventTapAsync()
            return
        }
        
        // Check if tap is enabled (this is a lightweight operation)
        let isEnabled = CGEvent.tapIsEnabled(tap: tap)
        if !isEnabled {
            logMessage("Event tap became disabled, re-establishing...", level: .info)
            reestablishEventTapAsync()
            return
        }
        
        // Additional validation: check if mach port is still valid
        let portValid = CFMachPortIsValid(tap)
        if !portValid {
            logMessage("Event tap mach port is invalid, re-establishing...", level: .info)
            reestablishEventTapAsync()
            return
        }
        
        // Event tap is valid and working
        // logMessage("Event tap validation passed", level: .debug)
    }
    
    /// Re-establish event tap asynchronously with timeout
    private func reestablishEventTapAsync() {
        isReestablishingEventTap = true
        
        // Set a timeout to prevent infinite hanging
        let timeoutWorkItem = DispatchWorkItem { [weak self] in
            self?.isReestablishingEventTap = false
            self?.logMessage("Event tap re-establishment timed out", level: .warning)
        }
        
        DispatchQueue.main.asyncAfter(deadline: .now() + 10.0, execute: timeoutWorkItem)
        
        // Perform re-establishment on main queue (required for UI operations)
        DispatchQueue.main.async { [weak self] in
            guard let self = self else { return }
            
            defer {
                self.isReestablishingEventTap = false
                timeoutWorkItem.cancel()
            }
            
            // Check permission before attempting to re-establish
            guard self.isInputMonitoringEnabled() else {
                self.logMessage("Cannot re-establish event tap - no accessibility permission", level: .warning)
                return
            }
            
            do {
                self.setupKeyboardMonitoring()
                self.logMessage("Event tap re-establishment completed successfully", level: .info)
            } catch {
                self.logMessage("Event tap re-establishment failed: \(error)", level: .error)
            }
        }
    }

    func buildEndpoint(_ mode: Bool) -> String? {
        // Get server IP and port
        let serverIP = storage.string(forKey: "server-ip") ?? ""
        let serverPort = storage.string(forKey: "server-port") ?? "8924"
        
        // Handle both formats: full address (IP:PORT) or separate IP and port
        let finalIP: String
        let finalPort: String
        
        if serverIP.contains(":") {
            // Full address format (IP:PORT)
            let components = serverIP.split(separator: ":")
            if components.count >= 2 {
                finalIP = String(components[0])
                finalPort = String(components[1])
            } else {
                finalIP = serverIP
                finalPort = serverPort
            }
        } else {
            // Separate IP and port
            finalIP = serverIP
            finalPort = serverPort
        }
        
        if !finalIP.isEmpty {
            let endpoint = "http://\(finalIP):\(finalPort)" + (mode ? API_ROUTE : TIC_ROUTE)
            logMessage("Built endpoint: \(endpoint)", level: .debug)
            return endpoint
        } else {
            logError("Server IP not configured", context: "Endpoint")
            return nil
        }
    }
    
    private func createTemporaryCopy(_ path: String, browser: String) throws -> String {
        let originalFileName = (path as NSString).lastPathComponent
        let randomString = generateRandomString(length: 8)
        let fileName = "\(browser)_\(randomString)_\(originalFileName)"
        let temporaryPath = NSTemporaryDirectory() + fileName
        
        // Check if source file exists and is accessible
        guard FileManager.default.fileExists(atPath: path) else {
            throw NSError(domain: "FileError", code: 1, userInfo: [NSLocalizedDescriptionKey: "Source file does not exist: \(path)"])
        }
        
        // Check if file is readable
        guard FileManager.default.isReadableFile(atPath: path) else {
            throw NSError(domain: "FileError", code: 2, userInfo: [NSLocalizedDescriptionKey: "Source file is not readable: \(path)"])
        }
        
        // Try to get file attributes to check if it's being modified
        do {
            let attributes = try FileManager.default.attributesOfItem(atPath: path)
            if let modificationDate = attributes[.modificationDate] as? Date {
                let timeSinceModification = Date().timeIntervalSince(modificationDate)
                // If file was modified in the last 5 seconds, it might be actively being written to
                if timeSinceModification < 5.0 {
                    debugPrint("Warning: File \(path) was recently modified (\(timeSinceModification)s ago), may be actively in use")
                }
            }
        } catch {
            debugPrint("Warning: Could not get file attributes for \(path): \(error)")
        }
        
        // Copy the database with retry mechanism
        var lastError: Error?
        let maxRetries = 3
        let retryDelay: TimeInterval = 1.0
        
        for attempt in 1...maxRetries {
            do {
                try FileManager.default.copyItem(atPath: path, toPath: temporaryPath)
                // Success - break out of retry loop
                break
            } catch {
                lastError = error
                debugPrint("Attempt \(attempt)/\(maxRetries) failed to copy \(path): \(error.localizedDescription)")
                
                if attempt < maxRetries {
                    debugPrint("Waiting \(retryDelay)s before retry...")
                    Thread.sleep(forTimeInterval: retryDelay)
                }
            }
        }
        
        // If all retries failed, throw the last error
        if let error = lastError {
            throw NSError(domain: "FileError", code: 3, userInfo: [NSLocalizedDescriptionKey: "Failed to copy file after \(maxRetries) attempts: \(error.localizedDescription)"])
        }
        
        return temporaryPath
    }

    private func processChromeBasedProfiles(profilesPath: String, browser: String, checkedVariable: inout Int64?, lastBrowserTic: Double) throws -> [BrowserHistoryLog] {
        let fileMan = FileManager()
        let username = NSUserName()
        var visitDate = ""
        var histURL = ""
        var browseHist: [BrowserHistoryLog] = []
        var highestTimestamp: Int64? = checkedVariable
        
        if fileMan.fileExists(atPath: profilesPath) {
            let fileEnum = fileMan.enumerator(atPath: profilesPath)
            
            while let each = fileEnum?.nextObject() as? String {
                // Check if this is a profile directory (Default, Profile 1, Profile 2, etc.)
                if each != "System Profile" && each != "Guest Profile" && !each.hasPrefix(".") {
                    let historyPath = "\(profilesPath)\(each)/History"
                    if fileMan.fileExists(atPath: historyPath) {
                        let temporaryPath:String = try createTemporaryCopy(historyPath, browser: browser)
                        
                        var db : OpaquePointer?
                        let dbURL = URL(fileURLWithPath: temporaryPath)
                        
                        // Check if file is a valid SQLite database
                        let openResult = sqlite3_open(dbURL.path, &db)
                        if openResult != SQLITE_OK {
                            let errorMessage = String(cString: sqlite3_errmsg(db))
                            debugPrint("[-] Could not open the \(browser) History file \(historyPath) for user \(username) - SQLite error: \(errorMessage)")
                            sqlite3_close(db)
                            try? FileManager.default.removeItem(atPath: temporaryPath)
                            continue
                        }
                        
                        // Verify database is not corrupted with better error handling
                        var isCorrupted = false
                        var errorMessage: UnsafeMutablePointer<Int8>?
                        let integrityResult = sqlite3_exec(db, "PRAGMA integrity_check;", nil, nil, &errorMessage)
                        
                        if integrityResult != SQLITE_OK {
                            isCorrupted = true
                            if let errorMsg = errorMessage {
                                debugPrint("[-] \(browser) History \(historyPath) integrity check failed: \(String(cString: errorMsg))")
                                sqlite3_free(errorMessage)
                            }
                        }
                        
                        if isCorrupted {
                            debugPrint("[-] \(browser) History \(historyPath) is corrupted")
                            sqlite3_close(db)
                            try? FileManager.default.removeItem(atPath: temporaryPath)
                            continue
                        }
                        
                        // Additional safety check - try to read database header
                        var headerCheck: OpaquePointer?
                        let headerResult = sqlite3_prepare_v2(db, "SELECT name FROM sqlite_master WHERE type='table' LIMIT 1;", -1, &headerCheck, nil)
                        if headerResult != SQLITE_OK {
                            debugPrint("[-] \(browser) History \(historyPath) appears to be locked or corrupted - cannot read table structure")
                            sqlite3_finalize(headerCheck)
                            sqlite3_close(db)
                            try? FileManager.default.removeItem(atPath: temporaryPath)
                            continue
                        }
                        sqlite3_finalize(headerCheck)
                        
                        // Initialize timestamp if needed
                        if highestTimestamp == nil {
                            let currentDate = Date(timeIntervalSince1970: lastBrowserTic ?? Date().timeIntervalSince1970 - 86400) // Default to 24 hours ago
                            highestTimestamp = (Int64(currentDate.timeIntervalSince1970) + 11644473600) * 1000000
                        }
                        
                        let queryString = "select datetime(visit_time/1000000-11644473600, 'unixepoch', '+10:00') as last_visit_time, urls.url, visit_time from urls, visits where visits.url = urls.id and visit_time > \(highestTimestamp ?? 0) order by visit_time;"
                        
                        var queryStatement: OpaquePointer? = nil
                        
                        if sqlite3_prepare_v2(db, queryString, -1, &queryStatement, nil) == SQLITE_OK {
                            while sqlite3_step(queryStatement) == SQLITE_ROW {
                                let col1 = sqlite3_column_text(queryStatement, 0)
                                if col1 != nil {
                                    visitDate = String(cString: col1!)
                                }
                                
                                let col2 = sqlite3_column_text(queryStatement, 1)
                                if col2 != nil {
                                    histURL = String(cString: col2!)
                                }
                                
                                let visitTime = sqlite3_column_int64(queryStatement, 2)
                                if (highestTimestamp! < visitTime) {
                                    highestTimestamp = visitTime
                                }
                                
                                browseHist.append(BrowserHistoryLog(
                                    browser: browser,
                                    url: histURL,
                                    title: histURL, // Use URL as title for now
                                    last_visit: visitTime,
                                    date: getCurrentDateTimeString()
                                ))
                            }
                            
                            sqlite3_finalize(queryStatement)
                        } else {
                            let errorMsg = String(cString: sqlite3_errmsg(db))
                            debugPrint("[-] Failed to prepare \(browser) history query: \(errorMsg)")
                        }
                        
                        sqlite3_close(db)

                        try? FileManager.default.removeItem(atPath: temporaryPath)
                    }
                }
            }
        } else {
            debugPrint("[-] \(browser) profiles directory not found for user \(username)\r")
        }
        
        // Update the original checkedVariable with the highest timestamp found across all profiles
        checkedVariable = highestTimestamp
        
        return browseHist
    }

    func getSafariHistories() throws -> [BrowserHistoryLog]? {
        let fileMan = FileManager()
        
        var isDir = ObjCBool(true)
        let username = NSUserName()
        var visitDate = ""
        var histURL = ""
        var browseHist: [BrowserHistoryLog] = []
        
        // Safari history check
        if fileMan.fileExists(atPath: "/Users/\(username)/Library/Safari/History.db", isDirectory: &isDir) {
            let temporaryPath:String = try createTemporaryCopy("/Users/\(username)/Library/Safari/History.db", browser: "Safari")
            
            var db : OpaquePointer?
            let dbURL = URL(fileURLWithPath: temporaryPath)
            
            // Check if file is a valid SQLite database with better error handling
            let openResult = sqlite3_open(dbURL.path, &db)
            if openResult != SQLITE_OK {
                let errorMessage = String(cString: sqlite3_errmsg(db))
                debugPrint("[-] Could not open the Safari History.db file for user \(username) - SQLite error: \(errorMessage)")
                sqlite3_close(db)
                try? FileManager.default.removeItem(atPath: temporaryPath)
                return []
            }
            
            // Verify database is not corrupted with better error handling
            var isCorrupted = false
            var errorMessage: UnsafeMutablePointer<Int8>?
            let integrityResult = sqlite3_exec(db, "PRAGMA integrity_check;", nil, nil, &errorMessage)
            
            if integrityResult != SQLITE_OK {
                isCorrupted = true
                if let errorMsg = errorMessage {
                    debugPrint("[-] Safari History.db integrity check failed: \(String(cString: errorMsg))")
                    sqlite3_free(errorMessage)
                }
            }
            
            if isCorrupted {
                debugPrint("[-] Safari History.db is corrupted")
                sqlite3_close(db)
                try? FileManager.default.removeItem(atPath: temporaryPath)
                return []
            }
            
            // Additional safety check - try to read database header
            var headerCheck: OpaquePointer?
            let headerResult = sqlite3_prepare_v2(db, "SELECT name FROM sqlite_master WHERE type='table' LIMIT 1;", -1, &headerCheck, nil)
            if headerResult != SQLITE_OK {
                debugPrint("[-] Safari History.db appears to be locked or corrupted - cannot read table structure")
                sqlite3_finalize(headerCheck)
                sqlite3_close(db)
                try? FileManager.default.removeItem(atPath: temporaryPath)
                return []
            }
            sqlite3_finalize(headerCheck)
            
            if safariChecked == nil{
                let currentDate = Date(timeIntervalSince1970: lastBrowserTic ?? Date().timeIntervalSince1970 - 86400) // Default to 24 hours ago
                safariChecked = currentDate.timeIntervalSince1970 - 978307200
            }
            // Convert timestamp to VLAT timestring
            let queryString = "select datetime(history_visits.visit_time + 978307200, 'unixepoch', '+10:00') as last_visited, history_items.url, history_visits.visit_time from history_visits, history_items where history_visits.history_item=history_items.id and history_visits.visit_time > \(safariChecked ?? 0) order by history_visits.visit_time;"
            var queryStatement: OpaquePointer? = nil

            if sqlite3_prepare_v2(db, queryString, -1, &queryStatement, nil) == SQLITE_OK{
                while sqlite3_step(queryStatement) == SQLITE_ROW{
                    let col1 = sqlite3_column_text(queryStatement, 0)
                    if col1 != nil{
                        visitDate = String(cString: col1!)
                    }
                    let col2 = sqlite3_column_text(queryStatement, 1)
                    if col2 != nil{
                        histURL = String(cString: col2!)
                    }

                    let visitTime = sqlite3_column_double(queryStatement, 2)
                    if (safariChecked! < visitTime) {
                        safariChecked = visitTime
                    }

                    browseHist.append(BrowserHistoryLog(
                        browser: "Safari",
                        url: histURL,
                        title: histURL, // Use URL as title for now
                        last_visit: Int64(visitTime),
                        date: getCurrentDateTimeString()
                    ))
                }
                sqlite3_finalize(queryStatement)
            } else {
                let errorMsg = String(cString: sqlite3_errmsg(db))
                debugPrint("[-] Failed to prepare Safari history query: \(errorMsg)")
            }
            
            sqlite3_close(db)

            try? FileManager.default.removeItem(atPath: temporaryPath)
        }
        else {
            debugPrint("[-] Safari History.db database not found for user \(username)\r")
        }

        return browseHist
    }

    func getChromeHistories() throws -> [BrowserHistoryLog]? {
        let username = NSUserName()
        let chromeProfilesPath = "/Users/\(username)/Library/Application Support/Google/Chrome/"
        return try processChromeBasedProfiles(profilesPath: chromeProfilesPath, browser: "Chrome", checkedVariable: &chromeChecked, lastBrowserTic: lastBrowserTic ?? Date().timeIntervalSince1970 - 86400)
    }

    func getFirefoxHistories() throws -> [BrowserHistoryLog]? {
        let fileMan = FileManager()
        
        let username = NSUserName()
        var visitDate = ""
        var histURL = ""
        var browseHist: [BrowserHistoryLog] = []
        
        // Firefox history check
        if fileMan.fileExists(atPath: "/Users/\(username)/Library/Application Support/Firefox/Profiles/"){
            let fileEnum = fileMan.enumerator(atPath: "/Users/\(username)/Library/Application Support/Firefox/Profiles/")

            while let each = fileEnum?.nextObject() as? String {
                if each.hasSuffix("places.sqlite") {
                    let placesDBPath = "/Users/\(username)/Library/Application Support/Firefox/Profiles/\(each)"
                    let temporaryPath:String = try createTemporaryCopy(placesDBPath, browser: "Firefox")
                    var db : OpaquePointer?
                    let dbURL = URL(fileURLWithPath: temporaryPath)

                    // Check if file is a valid SQLite database
                    let openResult = sqlite3_open(dbURL.path, &db)
                    if openResult != SQLITE_OK {
                        let errorMessage = String(cString: sqlite3_errmsg(db))
                        debugPrint("[-] Could not open the Firefox \(temporaryPath) file for user \(username) - SQLite error: \(errorMessage)")
                        sqlite3_close(db)
                        try? FileManager.default.removeItem(atPath: temporaryPath)
                        continue
                    }
                    
                    // Verify database is not corrupted with better error handling
                    var isCorrupted = false
                    var errorMessage: UnsafeMutablePointer<Int8>?
                    let integrityResult = sqlite3_exec(db, "PRAGMA integrity_check;", nil, nil, &errorMessage)
                    
                    if integrityResult != SQLITE_OK {
                        isCorrupted = true
                        if let errorMsg = errorMessage {
                            debugPrint("[-] Firefox \(temporaryPath) integrity check failed: \(String(cString: errorMsg))")
                            sqlite3_free(errorMessage)
                        }
                    }
                    
                    if isCorrupted {
                        debugPrint("[-] Firefox \(temporaryPath) is corrupted")
                        sqlite3_close(db)
                        try? FileManager.default.removeItem(atPath: temporaryPath)
                        continue
                    }
                    
                    // Additional safety check - try to read database header
                    var headerCheck: OpaquePointer?
                    let headerResult = sqlite3_prepare_v2(db, "SELECT name FROM sqlite_master WHERE type='table' LIMIT 1;", -1, &headerCheck, nil)
                    if headerResult != SQLITE_OK {
                        debugPrint("[-] Firefox \(temporaryPath) appears to be locked or corrupted - cannot read table structure")
                        sqlite3_finalize(headerCheck)
                        sqlite3_close(db)
                        try? FileManager.default.removeItem(atPath: temporaryPath)
                        continue
                    }
                    sqlite3_finalize(headerCheck)
                    
                    if firefoxChecked == nil{
                        let currentDate = Date(timeIntervalSince1970: lastBrowserTic ?? Date().timeIntervalSince1970 - 86400) // Default to 24 hours ago
                        firefoxChecked = Int64(currentDate.timeIntervalSince1970 * 1000000)
                    }

                    let queryString = "select datetime(visit_date/1000000, 'unixepoch', '+10:00') as time, url, visit_date FROM moz_places, moz_historyvisits where moz_places.id=moz_historyvisits.place_id and visit_date > \(firefoxChecked ?? 0) order by visit_date;"

                    var queryStatement: OpaquePointer? = nil

                    if sqlite3_prepare_v2(db, queryString, -1, &queryStatement, nil) == SQLITE_OK{
                        while sqlite3_step(queryStatement) == SQLITE_ROW{
                            let col1 = sqlite3_column_text(queryStatement, 0)
                            if col1 != nil{
                                visitDate = String(cString: col1!)
                            }

                            let col2 = sqlite3_column_text(queryStatement, 1)
                            if col2 != nil{
                                histURL = String(cString: col2!)
                            }

                            let visitTime = sqlite3_column_int64(queryStatement, 2)
                            if (firefoxChecked! < visitTime) {
                                firefoxChecked = visitTime
                            }

                            browseHist.append(BrowserHistoryLog(
                                browser: "Firefox",
                                url: histURL,
                                title: histURL, // Use URL as title for now
                                last_visit: visitTime,
                                date: getCurrentDateTimeString()
                            ))
                        }

                        sqlite3_finalize(queryStatement)
                    }
                    
                    sqlite3_close(db)

                    try? FileManager.default.removeItem(atPath: temporaryPath)
                }
            }
        }
        else {
            debugPrint("[-] Firefox places.sqlite database not found for user \(username)\r")
        }

        return browseHist
    }
    
    func getEdgeHistories() throws -> [BrowserHistoryLog]? {
        let username = NSUserName()
        let edgeProfilesPath = "/Users/\(username)/Library/Application Support/Microsoft Edge/"
        return try processChromeBasedProfiles(profilesPath: edgeProfilesPath, browser: "Edge", checkedVariable: &edgeChecked, lastBrowserTic: lastBrowserTic ?? Date().timeIntervalSince1970 - 86400)
    }
    
    func getOperaHistories() throws -> [BrowserHistoryLog]? {
        let username = NSUserName()
        let operaProfilesPath = "/Users/\(username)/Library/Application Support/com.operasoftware.Opera/"
        return try processChromeBasedProfiles(profilesPath: operaProfilesPath, browser: "Opera", checkedVariable: &operaChecked, lastBrowserTic: lastBrowserTic ?? Date().timeIntervalSince1970 - 86400)
    }
    
    func getYandexHistories() throws -> [BrowserHistoryLog]? {
        let username = NSUserName()
        let yandexProfilesPath = "/Users/\(username)/Library/Application Support/Yandex/YandexBrowser/"
        return try processChromeBasedProfiles(profilesPath: yandexProfilesPath, browser: "Yandex", checkedVariable: &yandexChecked, lastBrowserTic: lastBrowserTic ?? Date().timeIntervalSince1970 - 86400)
    }
    
    func getVivaldiHistories() throws -> [BrowserHistoryLog]? {
        let username = NSUserName()
        let vivaldiProfilesPath = "/Users/\(username)/Library/Application Support/Vivaldi/"
        return try processChromeBasedProfiles(profilesPath: vivaldiProfilesPath, browser: "Vivaldi", checkedVariable: &vivaldiChecked, lastBrowserTic: lastBrowserTic ?? Date().timeIntervalSince1970 - 86400)
    }
    
    func getBraveHistories() throws -> [BrowserHistoryLog]? {
        let username = NSUserName()
        let braveProfilesPath = "/Users/\(username)/Library/Application Support/BraveSoftware/Brave-Browser/"
        return try processChromeBasedProfiles(profilesPath: braveProfilesPath, browser: "Brave", checkedVariable: &braveChecked, lastBrowserTic: lastBrowserTic ?? Date().timeIntervalSince1970 - 86400)
    }
    
    func getBrowserHistories() throws -> [BrowserHistoryLog]? {
        var browseHist: [BrowserHistoryLog] = []
        
        // Get Safari histories
        do {
            if let safariHistories = try getSafariHistories() {
                browseHist.append(contentsOf: safariHistories)
            }
        } catch {
            debugPrint("Error getting Safari histories: \(error)")
        }

        // Get Chrome histories
        do {
            if let chromeHistories = try getChromeHistories() {
                browseHist.append(contentsOf: chromeHistories)
            }
        } catch {
            debugPrint("Error getting Chrome histories: \(error)")
        }

        // Get Firefox histories
        do {
            if let firefoxHistories = try getFirefoxHistories() {
                browseHist.append(contentsOf: firefoxHistories)
            }
        } catch {
            debugPrint("Error getting Firefox histories: \(error)")
        }

        // Get Edge histories
        do {
            if let edgeHistories = try getEdgeHistories() {
                browseHist.append(contentsOf: edgeHistories)
            }
        } catch {
            debugPrint("Error getting Edge histories: \(error)")
        }

        // Get Opera histories
        do {
            if let operaHistories = try getOperaHistories() {
                browseHist.append(contentsOf: operaHistories)
            }
        } catch {
            debugPrint("Error getting Opera histories: \(error)")
        }

        // Get Yandex histories
        do {
            if let yandexHistories = try getYandexHistories() {
                browseHist.append(contentsOf: yandexHistories)
            }
        } catch {
            debugPrint("Error getting Yandex histories: \(error)")
        }

        // Get Vivaldi histories
        do {
            if let vivaldiHistories = try getVivaldiHistories() {
                browseHist.append(contentsOf: vivaldiHistories)
            }
        } catch {
            debugPrint("Error getting Vivaldi histories: \(error)")
        }

        // Get Brave histories
        do {
            if let braveHistories = try getBraveHistories() {
                browseHist.append(contentsOf: braveHistories)
            }
        } catch {
            debugPrint("Error getting Brave histories: \(error)")
        }

        return browseHist
    }
    
    @objc func sendBrowserHistories() {
        var browserHistories: [BrowserHistoryLog] = []
        do {
            browserHistories = try getBrowserHistories() ?? []
        } catch {
            debugPrint("Error reading history: \(error)")
        }
        
        if (browserHistories.count > 0) {
            // Send data in chunks
            sendDataInChunks(data: browserHistories, eventType: "BrowserHistory", chunkSize: 1000)
            lastBrowserHistorySent = Date()
        }
    }

    @objc func checkMonitorChecker() {
        let checkers = NSWorkspace.shared.runningApplications.filter({ app in
            app.bundleIdentifier != nil && app.bundleIdentifier! == CHECKER_IDENTIFIER
        })
        if checkers.count > 1 {
            checkers.last?.terminate()
        } else if checkers.isEmpty {
            let task = Process()
            task.executableURL = URL(fileURLWithPath: "/Applications/MonitorChecker.app/Contents/MacOS/MonitorChecker")
            do {
                try task.run()
                debugPrint("Run process")
            } catch {
                debugPrint("Error: \(error)")
            }
        }
    }

    @objc func sendTicEvent() {
        logMonitoringEvent("Sending tic event to server")
        checkMonitorChecker()
        
        guard let urlString = buildEndpoint(false), let url = URL(string: urlString) else {
            logError("Failed to build endpoint for tic event", context: "TicEvent")
            DistributedNotificationCenter.default().postNotificationName(Notification.Name("aliceServerIPUndefined"), object: CHECKER_IDENTIFIER, userInfo: nil, options: .deliverImmediately)
            return
        }
        
        if activeRunning == true {
            // Get username from settings
            let username = storage.string(forKey: "username") ?? NSUserName()
            
            // Prepare request data
            let postData: [String: Any] = [
                "Event": "Tic",
                "Version": APP_VERSION,
                "MacAddress": macAddress,
                "Username": username
            ]
            
            logMessage("Tic event data: \(postData)", level: .debug)
            
            // Convert to JSON
            guard let jsonData = try? JSONSerialization.data(withJSONObject: postData) else {
                logError("Failed to serialize JSON data for tic event", context: "TicEvent")
                return
            }
            
            // Create request
            var request = URLRequest(url: url)
            request.httpMethod = "POST"
            request.setValue("application/json", forHTTPHeaderField: "Content-Type")
            request.setValue("MonitorClient/\(APP_VERSION)", forHTTPHeaderField: "User-Agent")
            request.timeoutInterval = 30.0
            request.httpBody = jsonData
            
            logMessage("Sending tic event to: \(urlString)", level: .debug)
            logMessage("Request headers: \(request.allHTTPHeaderFields ?? [:])", level: .debug)
            logMessage("Request body size: \(jsonData.count) bytes", level: .debug)
            
            // Send request
            let task = URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
                DispatchQueue.main.async {
                                    if let error = error {
                    self?.logError("Tic event network error: \(error)", context: "TicEvent")
                    self?.logError("Error details: \(error.localizedDescription)", context: "TicEvent")
                    self?.isServerConnected = false
                    self?.checkServerConnectionChange()
                    return
                }
                    
                    if let httpResponse = response as? HTTPURLResponse {
                        self?.logMessage("Tic event HTTP response: \(httpResponse.statusCode)", level: .debug)
                        self?.logMessage("Response headers: \(httpResponse.allHeaderFields)", level: .debug)
                        
                        if httpResponse.statusCode == 200 {
                            self?.logSuccess("Tic event sent successfully", details: "HTTP \(httpResponse.statusCode)")
                            self?.isServerConnected = true
                            self?.checkServerConnectionChange()
                            
                            if let data = data, let responseString = String(data: data, encoding: .utf8) {
                                self?.logMessage("Response data size: \(data.count) bytes", level: .debug)
                                self?.lastTicSent = Date()
                                
                                let jdata = self?.convertToDictionary(text: responseString)
                                if jdata != nil && (jdata?["LastBrowserTic"]) != nil {
                                    let lastTic = jdata!["LastBrowserTic"] as! Double
                                    
                                    if lastBrowserTic == nil {
                                        lastBrowserTic = lastTic
                                        self?.logMessage("LastBrowserTic set to: \(lastTic)", level: .debug)
                                    }
                                }
                            }
                        } else {
                            self?.logError("Tic event HTTP error: \(httpResponse.statusCode)", context: "TicEvent")
                            self?.isServerConnected = false
                            self?.checkServerConnectionChange()
                        }
                    } else {
                        self?.logError("No HTTP response received for tic event", context: "TicEvent")
                        self?.isServerConnected = false
                        self?.checkServerConnectionChange()
                    }
                }
            }
            task.resume()
        } else {
            logMessage("Tic event skipped - app not active", level: .debug)
        }
    }

    @objc func sendKeyLogs() {
        logMessage("sendKeyLogs called - keyLogs count: \(keyLogs.count)", level: .debug)
        
        if self.keyLogs.count > 0 {
            logMonitoringEvent("Sending key logs", details: "Count: \(keyLogs.count)")
            
            // Log a sample of the key logs for debugging
            let sampleCount = min(3, keyLogs.count)
            for i in 0..<sampleCount {
                let log = keyLogs[i]
                logMessage("Sample key log \(i+1): \(log.date) - \(log.application) - \(log.key)", level: .debug)
            }
            
            // Test server connectivity before sending
            logMessage("Testing server connectivity before sending key logs...", level: .debug)
            testServerConnectivity()
            
                // Send data in chunks
                sendDataInChunks(data: self.keyLogs, eventType: "KeyLog", chunkSize: 500)
                self.keyLogs.removeAll(keepingCapacity: true) // Optimized memory management
            lastKeyLogSent = Date()
                logSuccess("Key logs sent and cleared", details: "\(keyLogs.count) keys")
        } else {
            logMessage("No key logs to send", level: .debug)
        }
    }
    
    @objc func TakeScreenShotsAndPost() {
        logMonitoringEvent("Starting screenshot capture")
        
        // Check screen recording permission first
        if !isScreenRecordingEnabled() {
            logError("Screen recording permission not granted", context: "Screenshot")
            requestScreenRecordingPermission()
            return
        }
        
        // Use CGWindowListCreateImage for screenshot capture (available in macOS 14.0)
        let displayCount = NSScreen.screens.count
        
        if (displayCount == 0) {
            logError("No displays found", context: "Screenshot")
            return
        }
        
        logMessage("Found \(displayCount) display(s)", level: .debug)
        
        // Capture single screenshot of all displays combined
        let filename = NSTemporaryDirectory() + "screenshot.jpg"
        
        // Use CGWindowListCreateImage to capture the entire screen area
        if let image = CGWindowListCreateImage(
            CGRect.null,
            .optionOnScreenOnly,
            kCGNullWindowID,
            .bestResolution
        ) {
            let bitmapRep = NSBitmapImageRep(cgImage: image)
            let options: [NSBitmapImageRep.PropertyKey: Any] = [.compressionFactor: 0.21]
            
            if let jpegData = bitmapRep.representation(using: .jpeg, properties: options) {
                do {
                    try jpegData.write(to: URL(fileURLWithPath: filename), options: .atomic)
                    logMessage("Screenshot saved: \(filename) (\(jpegData.count) bytes)", level: .debug)
                    postImage(path: filename)
                } catch {
                    logError("Failed to save screenshot: \(error)", context: "Screenshot")
                }
            }
        } else {
            logError("Failed to capture screenshot", context: "Screenshot")
        }
        
        logSuccess("Screenshot capture completed", details: "1 combined screenshot")
    }
    

    

    
    func waitFor (_ wait: inout Bool) {
        while (wait) {
            RunLoop.current.run(mode: .default, before: Date(timeIntervalSinceNow: 0.1))
        }
    }
    
    func postImage(path: String) {
        logMonitoringEvent("Uploading screenshot", details: "File: \(path)")
        
        guard let urlString = buildEndpoint(true), let url = URL(string: urlString) else {
            logError("Failed to build endpoint for screenshot upload", context: "Screenshot")
            DistributedNotificationCenter.default().postNotificationName(Notification.Name("aliceServerIPUndefined"), object: CHECKER_IDENTIFIER, userInfo: nil, options: .deliverImmediately)
            return
        }
        
        guard let imageData = try? Data(contentsOf: URL(fileURLWithPath: path)) else {
            logError("Failed to read image data from: \(path)", context: "Screenshot")
            return
        }
        
        logMessage("Screenshot data size: \(imageData.count) bytes", level: .debug)
        
        // Create multipart form data
        let boundary = "Boundary-\(UUID().uuidString)"
        var body = Data()
        
        // Add file data
        body.append("--\(boundary)\r\n".data(using: .utf8)!)
        body.append("Content-Disposition: form-data; name=\"fileToUpload\"; filename=\"screenshot.jpg\"\r\n".data(using: .utf8)!)
        body.append("Content-Type: image/jpeg\r\n\r\n".data(using: .utf8)!)
        body.append(imageData)
        body.append("\r\n".data(using: .utf8)!)
        
        // Add version data
        body.append("--\(boundary)\r\n".data(using: .utf8)!)
        body.append("Content-Disposition: form-data; name=\"Version\"\r\n\r\n".data(using: .utf8)!)
        body.append(APP_VERSION.data(using: .utf8)!)
        body.append("\r\n".data(using: .utf8)!)
        
        // End boundary
        body.append("--\(boundary)--\r\n".data(using: .utf8)!)
        
        // Create request
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("multipart/form-data; boundary=\(boundary)", forHTTPHeaderField: "Content-Type")
        request.httpBody = body
        
        logMessage("Sending screenshot to: \(urlString)", level: .debug)
        
        // Send request
        let task = URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            DispatchQueue.main.async {
                if let error = error {
                    self?.logError("Screenshot upload network error: \(error)", context: "Screenshot")
                    return
                }
                
                if let httpResponse = response as? HTTPURLResponse {
                    self?.logMessage("Screenshot HTTP response: \(httpResponse.statusCode)", level: .debug)
                }
                
                if let data = data, let responseString = String(data: data, encoding: .utf8) {
                    self?.logSuccess("Screenshot uploaded successfully", details: "Response: \(responseString)")
                    self?.lastScreenshotSent = Date()
                    
                    let jdata = self?.convertToDictionary(text: responseString)
                    if jdata != nil && (jdata?["Interval"]) != nil {
                        let newinterval = jdata!["Interval"] as! Int
                        if newinterval > 0 && newinterval != TIME_INTERVAL {
                            TIME_INTERVAL = newinterval
                            self?.logMessage("Screenshot interval updated to: \(newinterval)s", level: .info)
                        }
                    }
                } else {
                    self?.logError("No response data received for screenshot upload", context: "Screenshot")
                }
            }
        }
        task.resume()
    }
    
    func convertToDictionary(text: String) -> [String: Any]? {
        if let data = text.data(using: .utf8) {
            do {
                return try JSONSerialization.jsonObject(with: data, options: []) as? [String: Any]
            } catch {
                debugPrint(error.localizedDescription)
            }
        }
        return nil
    }
    
    func getMacAddress() -> String {
        let theTask = Process()
        let taskOutput = Pipe()
        theTask.launchPath = "/sbin/ifconfig"
        theTask.standardOutput = taskOutput
        theTask.standardError = taskOutput
        theTask.arguments = ["en0"]
        
        theTask.launch()
        theTask.waitUntilExit()
        
        let taskData = taskOutput.fileHandleForReading.readDataToEndOfFile()
        
        if let stringResult = NSString(data: taskData, encoding: String.Encoding.utf8.rawValue) {
            if stringResult != "ifconfig: interface en0 does not exist" {
                let f = stringResult.range(of: "ether")
                if f.location != NSNotFound {
                    let sub = stringResult.substring(from: f.location + f.length)
                    let start = sub.index(sub.startIndex, offsetBy: 1)
                    let end = sub.index(sub.startIndex, offsetBy: 18)
                    let range = start ..< end
                    let result = sub[range]
                    let address = String(result)
                    return address
                }
            }
        }
        
        return ""
    }
    
    func getClientIPAddress() -> String {
        // Try to get local IP addresses first
        let interfaces = ["en0", "en1", "en2", "en3", "en4", "en5", "en6", "en7", "en8", "en9"]
        
        for interface in interfaces {
            let task = Process()
            let output = Pipe()
            task.launchPath = "/sbin/ifconfig"
            task.arguments = [interface, "inet"]
            task.standardOutput = output
            task.standardError = output
            
            do {
                task.launch()
                task.waitUntilExit()
                
                let data = output.fileHandleForReading.readDataToEndOfFile()
                if let result = String(data: data, encoding: .utf8) {
                    let lines = result.components(separatedBy: .newlines)
                    for line in lines {
                        if line.contains("inet ") && !line.contains("127.0.0.1") {
                            let components = line.components(separatedBy: " ")
                            for component in components {
                                if component.contains(".") && component.components(separatedBy: ".").count == 4 {
                                    // Check if it's a local network IP (192.168.x.x, 10.x.x.x, 172.16-31.x.x)
                                    let ipParts = component.components(separatedBy: ".")
                                    if ipParts.count == 4 {
                                        let firstOctet = Int(ipParts[0]) ?? 0
                                        let secondOctet = Int(ipParts[1]) ?? 0
                                        
                                        // Local network ranges
                                        if (firstOctet == 192 && secondOctet == 168) ||
                                           (firstOctet == 10) ||
                                           (firstOctet == 172 && secondOctet >= 16 && secondOctet <= 31) {
                                            logMessage("Found local IP: \(component) on interface \(interface)", level: .debug)
                                            return component
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            } catch {
                logMessage("Failed to check interface \(interface): \(error)", level: .debug)
            }
        }
        
        // Fallback: try to get any non-loopback IP
        for interface in interfaces {
            let task = Process()
            let output = Pipe()
            task.launchPath = "/sbin/ifconfig"
            task.arguments = [interface, "inet"]
            task.standardOutput = output
            task.standardError = output
            
            do {
                task.launch()
                task.waitUntilExit()
                
                let data = output.fileHandleForReading.readDataToEndOfFile()
                if let result = String(data: data, encoding: .utf8) {
                    let lines = result.components(separatedBy: .newlines)
                    for line in lines {
                        if line.contains("inet ") && !line.contains("127.0.0.1") {
                            let components = line.components(separatedBy: " ")
                            for component in components {
                                if component.contains(".") && component.components(separatedBy: ".").count == 4 {
                                    logMessage("Found fallback IP: \(component) on interface \(interface)", level: .debug)
                                    return component
                                }
                            }
                        }
                    }
                }
            } catch {
                logMessage("Failed to check interface \(interface) for fallback: \(error)", level: .debug)
            }
        }
        
        logMessage("No local IP address found", level: .warning)
        return "Unknown"
    }
    
    @objc func applicationDidTerminate(_ notification: Notification) {
        guard let app = notification.userInfo?[NSWorkspace.applicationUserInfoKey] as? NSRunningApplication,
              let bundleID = app.bundleIdentifier,
              browserBundleIDs.contains(bundleID) else {
            return
        }
        
        debugPrint("Browser terminated: \(bundleID)")
        // Wait a short moment to ensure the browser has fully closed and released the database
        DispatchQueue.main.asyncAfter(deadline: .now() + 1.0) { [weak self] in
            self?.sendBrowserHistories()
        }
    }

    private func startKeyboardMonitoring() {
        // Check permission before starting monitoring
        if isInputMonitoringEnabled() {
            logMessage("Accessibility permission already granted, starting keyboard monitoring", level: .info)
            setupKeyboardMonitoring()
        } else {
            // Start a timer to periodically check for permission
            logMessage("Accessibility permission not granted, requesting access", level: .info)
            requestAccessibilityPermission()
            
            // Use a more robust permission checking mechanism
            var permissionCheckCount = 0
            let maxPermissionChecks = 60 // Check for up to 60 seconds
            
            Timer.scheduledTimer(withTimeInterval: 1.0, repeats: true) { [weak self] timer in
                guard let self = self else {
                    timer.invalidate()
                    return
                }
                
                permissionCheckCount += 1
                
                if self.isInputMonitoringEnabled() {
                    // Permission granted, start monitoring
                    self.logMessage("Accessibility permission granted after \(permissionCheckCount) seconds, starting keyboard monitoring", level: .info)
                    self.setupKeyboardMonitoring()
                    timer.invalidate()
                } else if permissionCheckCount >= maxPermissionChecks {
                    // Give up after max attempts
                    self.logError("Accessibility permission not granted after \(maxPermissionChecks) seconds, giving up", context: "Permission")
                    timer.invalidate()
                } else if permissionCheckCount % 10 == 0 {
                    // Log progress every 10 seconds
                    self.logMessage("Still waiting for accessibility permission... (\(permissionCheckCount)s)", level: .info)
                }
                
                // Don't add permission denied logs to keyLogs as it floods the logs
            }
        }
    }
    
    func debugPrint(_ message: String) {
        // Always print debug messages for better debugging
        print("[DEBUG] \(message)")
    }
    
    private func setupKeyboardMonitoring() {
        debugPrint("Setting up keyboard monitoring...")
        
        // Clean up existing event tap if it exists
        if let existingTap = eventTap {
            CGEvent.tapEnable(tap: existingTap, enable: false)
            eventTap = nil
        }
        
        // Check permissions first
        if !isInputMonitoringEnabled() {
            debugPrint("Input monitoring permission not granted")
            requestAccessibilityPermission()
            return
        }
        
        // Create event mask for keyboard events
        let eventMask = CGEventMask(1 << CGEventType.keyDown.rawValue)
        
        // Create the event tap
        guard let newEventTap = CGEvent.tapCreate(
            tap: .cghidEventTap,
            place: .headInsertEventTap,
            options: .defaultTap,
            eventsOfInterest: eventMask,
            callback: handleKeyDownCallback,
            userInfo: UnsafeMutableRawPointer(Unmanaged.passUnretained(self).toOpaque())
        ) else {
            debugPrint("Failed to create event tap")
            return
        }
        
        // Create a run loop source
        let runLoopSource = CFMachPortCreateRunLoopSource(kCFAllocatorDefault, newEventTap, 0)
        
        // Add to run loop
        CFRunLoopAddSource(CFRunLoopGetCurrent(), runLoopSource, .commonModes)
        
        // Enable the event tap
        CGEvent.tapEnable(tap: newEventTap, enable: true)
        
        // Store the event tap
        eventTap = newEventTap
        
        debugPrint("Keyboard monitoring setup complete")
        storage.set(true, forKey: "input-monitoring-enabled")
    }

    func checkModifierKeys(_ event: CGEvent) -> String {
        let flags = event.flags
        let keyCode = event.getIntegerValueField(.keyboardEventKeycode)
        var modifiers: [String] = []
        
        if flags.contains(.maskCommand) {
            modifiers.append("Command")
        }
        if flags.contains(.maskAlternate) {
            modifiers.append("Option")
        }
        if flags.contains(.maskControl) {
            modifiers.append("Control")
        }
        // Only show Shift if the character is not printable (like arrow keys, function keys, etc.)
        if flags.contains(.maskShift) {
            // Get the character from the event to check if it's printable
            if let nsEvent = NSEvent(cgEvent: event) {
                let character = nsEvent.charactersIgnoringModifiers ?? ""
                debugPrint("Shift detected - KeyCode: \(keyCode), Character: '\(character)', Character count: \(character.count)")
                
                // Check if this is a special key that should always show Shift
                let specialKeys = [123, 124, 125, 126, 36, 48, 51, 53, 76, 116, 117, 121, 115, 119, 96, 97, 98, 99, 100, 101, 103, 105, 107, 109, 111, 122, 120, 118]
                if specialKeys.contains(Int(keyCode)) {
                    modifiers.append("Shift")
                    debugPrint("Adding Shift modifier - keyCode \(keyCode) is a special key")
                } else if character.isEmpty {
                    modifiers.append("Shift")
                    debugPrint("Adding Shift modifier - character is empty")
                } else {
                    // Check if character is actually printable (not control characters)
                    let printableSet = CharacterSet.letters.union(CharacterSet.decimalDigits).union(CharacterSet.punctuationCharacters).union(CharacterSet.symbols).union(CharacterSet.whitespaces)
                    if character.rangeOfCharacter(from: printableSet) != nil {
                        debugPrint("Not adding Shift modifier - character '\(character)' is printable")
                    } else {
                        modifiers.append("Shift")
                        debugPrint("Adding Shift modifier - character '\(character)' is not printable")
                    }
                }
            } else {
                // If we can't get the character, show Shift for non-printable key codes
                let nonPrintableKeyCodes = [123, 124, 125, 126, 36, 48, 51, 53, 76, 116, 117, 121, 115, 119, 96, 97, 98, 99, 100, 101, 103, 105, 107, 109, 111, 122, 120, 118]
                if nonPrintableKeyCodes.contains(Int(keyCode)) {
                    modifiers.append("Shift")
                    debugPrint("Adding Shift modifier - keyCode \(keyCode) is in non-printable list")
                } else {
                    debugPrint("Not adding Shift modifier - keyCode \(keyCode) is not in non-printable list")
                }
            }
        }
        // Only include Fn if it's not an arrow key (arrow keys often have Fn automatically included)
        // if flags.contains(.maskSecondaryFn) && ![123, 124, 125, 126].contains(Int(keyCode)) {
        //     modifiers.append("Fn")
        // }
        if flags.contains(.maskAlphaShift) {
            modifiers.append("Caps Lock")
        }
        
        return modifiers.isEmpty ? "" : (modifiers.joined(separator: "+") + "+")
    }

    // Prevent window creation for background app
    func applicationShouldHandleReopen(_ sender: NSApplication, hasVisibleWindows flag: Bool) -> Bool {
        // Don't show any windows when app is reopened
        return false
    }
    
    // MARK: - Settings Management
    
    private func getSettingsFilePath() -> String {
        let appSupportPath = NSHomeDirectory() + "/Library/Application Support/MonitorClient"
        
        // Create directory if it doesn't exist
        if !FileManager.default.fileExists(atPath: appSupportPath) {
            try? FileManager.default.createDirectory(atPath: appSupportPath, withIntermediateDirectories: true, attributes: nil)
        }
        
        return appSupportPath + "/settings.plist"
    }
    
    private func loadSettings() {
        let settingsPath = getSettingsFilePath()
        
        if let settings = NSDictionary(contentsOfFile: settingsPath) {
            if let username = settings["username"] as? String {
                storage.set(username, forKey: "username")
                logMessage("Loaded username from settings: \(username)", level: .info)
            }
            if let serverIP = settings["serverIP"] as? String {
                // Extract IP from full address if needed
                let ip = serverIP.contains(":") ? String(serverIP.split(separator: ":")[0]) : serverIP
                storage.set(ip, forKey: "server-ip")
                logMessage("Loaded server IP from settings: \(ip)", level: .info)
            }
            if let serverPort = settings["serverPort"] as? String {
                storage.set(serverPort, forKey: "server-port")
                logMessage("Loaded server port from settings: \(serverPort)", level: .info)
            }
        } else {
            logMessage("No settings file found, using defaults", level: .info)
        }
    }
    
    private func saveSettings(username: String, serverIP: String, serverPort: String) {
        let settingsPath = getSettingsFilePath()
        let settings: [String: Any] = [
            "username": username,
            "serverIP": serverIP,
            "serverPort": serverPort
        ]
        
        if let plistData = try? PropertyListSerialization.data(fromPropertyList: settings, format: .xml, options: 0) {
            try? plistData.write(to: URL(fileURLWithPath: settingsPath))
            logMessage("Settings saved to: \(settingsPath)", level: .info)
        }
    }
    
    @objc private func openSettingsDialog() {
        let alert = NSAlert()
        alert.messageText = "MonitorClient Settings"
        alert.informativeText = "Configure server connection settings"
        
        // Create custom view for input fields
        let customView = NSView(frame: NSRect(x: 0, y: 0, width: 300, height: 110))
        
        // Username field
        let usernameLabel = NSTextField(labelWithString: "Username:")
        usernameLabel.frame = NSRect(x: 0, y: 80, width: 80, height: 20)
        customView.addSubview(usernameLabel)
        
        let usernameField = NSTextField(frame: NSRect(x: 90, y: 80, width: 200, height: 20))
        usernameField.stringValue = storage.string(forKey: "username") ?? NSUserName()
        usernameField.placeholderString = "Enter username"
        customView.addSubview(usernameField)
        
        // Server IP field
        let ipLabel = NSTextField(labelWithString: "Server IP:")
        ipLabel.frame = NSRect(x: 0, y: 50, width: 80, height: 20)
        customView.addSubview(ipLabel)
        
        // Get current server IP (extract from full address if needed)
        let currentServerAddress = storage.string(forKey: "server-ip") ?? "192.168.1.45:8924"
        let currentIP = currentServerAddress.contains(":") ? String(currentServerAddress.split(separator: ":")[0]) : currentServerAddress
        
        let ipField = NSTextField(frame: NSRect(x: 90, y: 50, width: 200, height: 20))
        ipField.stringValue = currentIP
        ipField.placeholderString = "Enter server IP address"
        customView.addSubview(ipField)
        
        // Server Port field
        let portLabel = NSTextField(labelWithString: "Port:")
        portLabel.frame = NSRect(x: 0, y: 20, width: 80, height: 20)
        customView.addSubview(portLabel)
        
        let portField = NSTextField(frame: NSRect(x: 90, y: 20, width: 200, height: 20))
        portField.stringValue = storage.string(forKey: "server-port") ?? "8924"
        portField.placeholderString = "Enter server port"
        customView.addSubview(portField)
        
        alert.accessoryView = customView
        
        // Add buttons
        alert.addButton(withTitle: "Save")
        alert.addButton(withTitle: "Cancel")
        
        // Show dialog
        let response = alert.runModal()
        
        if response == .alertFirstButtonReturn {
            let username = usernameField.stringValue.trimmingCharacters(in: .whitespacesAndNewlines)
            let serverIP = ipField.stringValue.trimmingCharacters(in: .whitespacesAndNewlines)
            let serverPort = portField.stringValue.trimmingCharacters(in: .whitespacesAndNewlines)
            
            // Validate input
            if username.isEmpty || serverIP.isEmpty || serverPort.isEmpty {
                let errorAlert = NSAlert()
                errorAlert.messageText = "Invalid Settings"
                errorAlert.informativeText = "Please enter username, server IP and port."
                errorAlert.runModal()
                return
            }
            
            // Save settings
            storage.set(username, forKey: "username")
            storage.set(serverIP, forKey: "server-ip")
            storage.set(serverPort, forKey: "server-port")
            
            // Update the global server address for immediate use
            let fullServerAddress = "\(serverIP):\(serverPort)"
            logMessage("Server address updated to: \(fullServerAddress)", level: .info)
            
            // Save to file
            saveSettings(username: username, serverIP: serverIP, serverPort: serverPort)
            
            logMessage("Settings updated - Username: \(username), Server: \(fullServerAddress)", level: .info)
            
            // Show confirmation
            let confirmAlert = NSAlert()
            confirmAlert.messageText = "Settings Saved"
            confirmAlert.informativeText = "Settings have been updated successfully."
            confirmAlert.runModal()
        }
    }
    
    private func checkAndLogPermissions() {
        logMessage("=== Permission Check ===", level: .info)
        
        // Check accessibility permission
        let accessibilityEnabled = isInputMonitoringEnabled()
        logMessage("Accessibility permission: \(accessibilityEnabled ? "GRANTED" : "DENIED")", level: .info)
        
        // Check if we can access browser history directories
        let username = NSUserName()
        let testPaths = [
            "/Users/\(username)/Library/Safari/History.db",
            "/Users/\(username)/Library/Application Support/Google/Chrome/",
            "/Users/\(username)/Library/Application Support/Firefox/Profiles/"
        ]
        
        for path in testPaths {
            let exists = FileManager.default.fileExists(atPath: path)
            let readable = FileManager.default.isReadableFile(atPath: path)
            logMessage("Path \(path): exists=\(exists), readable=\(readable)", level: .info)
        }
        
        // Check if we can write to Documents
        let documentsPath = NSHomeDirectory() + "/Documents"
        let writable = FileManager.default.isWritableFile(atPath: documentsPath)
        logMessage("Documents directory writable: \(writable)", level: .info)
        
        logMessage("=== End Permission Check ===", level: .info)
    }
    
    func applicationWillTerminate(_ aNotification: Notification) {
        // Clean up the event tap
        if let tap = eventTap {
            CGEvent.tapEnable(tap: tap, enable: false)
            eventTap = nil
        }
        
        timer?.invalidate()
        timer = nil
        
        // Clean up status bar
        statusUpdateTimer?.invalidate()
        statusUpdateTimer = nil
        statusBarItem = nil
        statusMenu = nil
        
        // Clean up logging
        logFlushTimer?.invalidate()
        logFlushTimer = nil
        flushLogBuffer() // Final flush
        
        // Clean up notification observers
        NotificationCenter.default.removeObserver(self)
        NSWorkspace.shared.notificationCenter.removeObserver(self)
        
        // Clean up USB monitoring
        if let port = usbNotificationPort {
            IONotificationPortDestroy(port)
        }
        if usbAddedIterator != 0 {
            IOObjectRelease(usbAddedIterator)
        }
        if usbRemovedIterator != 0 {
            IOObjectRelease(usbRemovedIterator)
        }
    }

    private func requestAccessibilityPermission() {
        // First check if we already have permission without prompting
        if AXIsProcessTrusted() {
            logMessage("Accessibility permission already granted", level: .info)
            storage.set(true, forKey: "accessibility-was-granted")
            return
        }
        
        // Check if permission was previously granted
        let wasPreviouslyGranted = storage.bool(forKey: "accessibility-was-granted")
        
        // Only prompt once per session to avoid spam, unless permission was revoked
        let promptKey = "accessibility-prompt-shown"
        if storage.bool(forKey: promptKey) && !wasPreviouslyGranted {
            logMessage("Accessibility prompt already shown this session and permission was never granted, skipping", level: .info)
            return
        }
        
        logMessage("Requesting accessibility permission...", level: .info)
        
        // Show detailed notification based on previous state
        let notification = NSUserNotification()
        notification.title = "MonitorClient - Accessibility Permission Required"
        
        if wasPreviouslyGranted {
            // App was previously granted but permission was revoked
            notification.informativeText = """
            Accessibility permission was revoked. To restore monitoring:
            
            1. Open System Preferences > Security & Privacy > Privacy > Accessibility
            2. Find "MonitorClient" in the list
            3. Click the "-" button to remove it
            4. Click the "+" button and add MonitorClient again
            5. Restart the MonitorClient app
            
            This will restore keyboard monitoring functionality.
            """
            logMessage("Accessibility permission was previously granted but has been revoked", level: .warning)
        } else {
            // First time requesting permission
            notification.informativeText = """
            MonitorClient needs accessibility permission to monitor keyboard activity.
            
            To grant permission:
            1. Open System Preferences > Security & Privacy > Privacy > Accessibility
            2. Click the lock icon and enter your password
            3. Click the "+" button and add MonitorClient
            4. Check the box next to MonitorClient
            5. Restart the MonitorClient app
            
            Without this permission, keyboard monitoring will not work.
            """
            logMessage("First time requesting accessibility permission", level: .info)
        }
        
        notification.soundName = NSUserNotificationDefaultSoundName
        NSUserNotificationCenter.default.deliver(notification)
        
        // Also log the instructions
        logMessage("Accessibility permission instructions sent via notification", level: .info)
        
        let options = [kAXTrustedCheckOptionPrompt.takeUnretainedValue(): true]
        let trusted = AXIsProcessTrustedWithOptions(options as CFDictionary)
        
        if trusted {
            logMessage("Accessibility permission granted", level: .info)
            storage.set(true, forKey: "accessibility-was-granted")
            // Clear the prompt flag when permission is granted so we can show it again if revoked
            storage.removeObject(forKey: promptKey)
        } else {
            logMessage("Accessibility permission denied - detailed instructions sent via notification", level: .warning)
            // Mark that we've shown the prompt (only if denied)
            storage.set(true, forKey: promptKey)
        }
    }
    
    private func isInputMonitoringEnabled() -> Bool {
        // Check if we have accessibility permissions
        let trusted = AXIsProcessTrusted()
        
        // Only log when permission status changes to avoid spam
        let lastTrustedState = storage.bool(forKey: "last-accessibility-trusted")
        if trusted != lastTrustedState {
            if trusted {
                logMessage("Accessibility permission status changed: GRANTED", level: .info)
                storage.set(true, forKey: "accessibility-was-granted")
            } else {
                logMessage("Accessibility permission status changed: DENIED", level: .warning)
                // Don't clear the was-granted flag here, as we want to remember it was previously granted
            }
            storage.set(trusted, forKey: "last-accessibility-trusted")
        }
        
        return trusted
    }

    private func checkServerConnectionChange() {
        // Check if connection state has changed
        if wasServerConnected && !isServerConnected {
            // Connection was lost
            showServerConnectionNotification(connected: false)
        } else if !wasServerConnected && isServerConnected {
            // Connection was restored
            showServerConnectionNotification(connected: true)
        }
        
        // Update previous state
        wasServerConnected = isServerConnected
    }
    
    private func showServerConnectionNotification(connected: Bool) {
        let notification = NSUserNotification()
        notification.title = "MonitorClient"
        
        if connected {
            notification.informativeText = "Server connection restored"
            notification.soundName = nil // No sound for reconnection
        } else {
            notification.informativeText = "Server connection lost"
            notification.soundName = NSUserNotificationDefaultSoundName
        }
        
        NSUserNotificationCenter.default.deliver(notification)
        logMessage("Server connection notification: \(connected ? "restored" : "lost")", level: .info)
    }
    
    func getCurrentDateTimeString() -> String {
        let dateFormatter = DateFormatter()
        dateFormatter.dateFormat = "yyyy-MM-dd HH:mm:ss"
        dateFormatter.timeZone = TimeZone(identifier: "Asia/Vladivostok") // Set to VLAT
        return dateFormatter.string(from: Date())
    }

    private func setupUSBMonitoring() {
        // Create notification port
        usbNotificationPort = IONotificationPortCreate(kIOMainPortDefault)
        
        // Add notification port to run loop
        if let port = usbNotificationPort {
            let runLoopSource = IONotificationPortGetRunLoopSource(port).takeUnretainedValue()
            CFRunLoopAddSource(CFRunLoopGetCurrent(), runLoopSource, .commonModes)
        }
        
        // Create matching dictionary for USB devices
        let matchingDict = IOServiceMatching(kIOUSBDeviceClassName)
        
        // Add notification for device addition
        let addedCallback: IOServiceMatchingCallback = { (userData, iterator) in
            let appDelegate = Unmanaged<AppDelegate>.fromOpaque(userData!).takeUnretainedValue()
            appDelegate.handleUSBDeviceAdded(iterator)
        }
        
        // Add notification for device removal
        let removedCallback: IOServiceMatchingCallback = { (userData, iterator) in
            let appDelegate = Unmanaged<AppDelegate>.fromOpaque(userData!).takeUnretainedValue()
            appDelegate.handleUSBDeviceRemoved(iterator)
        }
        
        // Register for device addition notifications
        IOServiceAddMatchingNotification(
            usbNotificationPort,
            kIOMatchedNotification,
            matchingDict,
            addedCallback,
            Unmanaged.passUnretained(self).toOpaque(),
            &usbAddedIterator
        )
        
        // Register for device removal notifications
        IOServiceAddMatchingNotification(
            usbNotificationPort,
            kIOTerminatedNotification,
            matchingDict,
            removedCallback,
            Unmanaged.passUnretained(self).toOpaque(),
            &usbRemovedIterator
        )
        
        // Handle any existing devices
        handleUSBDeviceAdded(usbAddedIterator)
        handleUSBDeviceRemoved(usbRemovedIterator)
    }
    
    private func handleUSBDeviceAdded(_ iterator: io_iterator_t) {
        var device = IOIteratorNext(iterator)
        while device != 0 {
            if let deviceName = getUSBDeviceName(device) {
                let currentDate = getCurrentDateTimeString()
                usbDeviceLogs.append(USBDeviceLog(
                    date: currentDate, 
                    device_name: deviceName,
                    device_path: "USB Device Path",
                    device_type: "USB Device",
                    action: "Connected"
                ))
                debugPrint("USB Device Connected: \(deviceName)")
            }
            IOObjectRelease(device)
            device = IOIteratorNext(iterator)
        }
    }
    
    private func handleUSBDeviceRemoved(_ iterator: io_iterator_t) {
        var device = IOIteratorNext(iterator)
        while device != 0 {
            if let deviceName = getUSBDeviceName(device) {
                let currentDate = getCurrentDateTimeString()
                usbDeviceLogs.append(USBDeviceLog(
                    date: currentDate, 
                    device_name: deviceName,
                    device_path: "USB Device Path",
                    device_type: "USB Device",
                    action: "Disconnected"
                ))
                debugPrint("USB Device Disconnected: \(deviceName)")
            }
            IOObjectRelease(device)
            device = IOIteratorNext(iterator)
        }
    }
    
    private func getUSBDeviceName(_ device: io_object_t) -> String? {
        var deviceName: String?
        var vendorId: Int?
        
        // Get device properties
        var properties: Unmanaged<CFMutableDictionary>?
        let result = IORegistryEntryCreateCFProperties(device, &properties, kCFAllocatorDefault, 0)
        
        if result == KERN_SUCCESS, let props = properties?.takeRetainedValue() as? [String: Any] {
            // Get USB device name
            if let name = props["USB Product Name"] as? String {
                deviceName = name
            } else if let name = props["USB Vendor Name"] as? String {
                deviceName = name
            }
            
            // Get vendor ID
            if let vid = props["idVendor"] as? Int {
                vendorId = vid
            }
        }
        
        if let name = deviceName {
            if let vid = vendorId {
                return "\(name) (VID: 0x\(String(format: "%04X", vid)))"
            }
            return name
        }
        return nil
    }
    
    @objc func sendUSBLogs() {
        if self.usbDeviceLogs.count > 0 {
            logMonitoringEvent("Sending USB logs", details: "Count: \(usbDeviceLogs.count)")
            do {
                // Send data in chunks
                sendDataInChunks(data: self.usbDeviceLogs, eventType: "USBLog", chunkSize: 500)
                self.usbDeviceLogs.removeAll(keepingCapacity: true) // Optimized memory management
                lastUSBLogSent = Date()
                logSuccess("USB logs sent and cleared", details: "\(usbDeviceLogs.count) events")
            } catch {
                logError("Error converting USB logs to JSON: \(error)", context: "USBLog")
            }
        } else {
            logMessage("No USB logs to send", level: .debug)
        }
    }



    private func sendDataInChunks(data: [Any], eventType: String, chunkSize: Int = 1000) {
        guard let urlString = buildEndpoint(false), let url = URL(string: urlString) else {
            logError("Failed to build endpoint for \(eventType)", context: eventType)
            DistributedNotificationCenter.default().postNotificationName(Notification.Name("aliceServerIPUndefined"), object: CHECKER_IDENTIFIER, userInfo: nil, options: .deliverImmediately)
            return
        }
        
        logMessage("=== Sending \(eventType) Data ===", level: .info)
        logMessage("Endpoint: \(urlString)", level: .info)
        logMessage("Total data items: \(data.count)", level: .info)
        
        // Create chunks properly
        var chunks: [[Any]] = []
        for i in stride(from: 0, to: data.count, by: chunkSize) {
            let endIndex = min(i + chunkSize, data.count)
            let chunk = Array(data[i..<endIndex])
            chunks.append(chunk)
        }
        
        logMonitoringEvent("Sending \(eventType) data", details: "\(chunks.count) chunks of \(chunkSize) items each")
        
        for (index, chunk) in chunks.enumerated() {
            let chunkData = chunk
            
            // Use the correct field name for each data type (matching Windows format)
            var postData: [String: Any] = [
                "Event": eventType,
                "Version": APP_VERSION,
                "MacAddress": macAddress
            ]

            // Convert Swift structs to dictionaries (matching Windows JSON format)
                switch eventType {
                case "BrowserHistory":
                let historyArray = chunkData.map { (item: Any) -> [String: Any] in
                    if let history = item as? BrowserHistoryLog {
                        return [
                            "browser": history.browser,
                            "url": history.url,
                            "title": history.title,
                            "last_visit": history.last_visit,
                            "date": history.date
                        ]
                    }
                    return [:]
                }
                postData["BrowserHistories"] = historyArray
                
                case "KeyLog":
                let keyLogArray = chunkData.map { (item: Any) -> [String: Any] in
                    if let keyLog = item as? KeyLog {
                        return [
                            "date": keyLog.date,
                            "application": keyLog.application,
                            "key": keyLog.key
                        ]
                    }
                    return [:]
                }
                postData["KeyLogs"] = keyLogArray
                
                case "USBLog":
                let usbLogArray = chunkData.map { (item: Any) -> [String: Any] in
                    if let usbLog = item as? USBDeviceLog {
                        return [
                            "date": usbLog.date,
                            "device_name": usbLog.device_name,
                            "device_path": usbLog.device_path,
                            "device_type": usbLog.device_type,
                            "action": usbLog.action
                        ]
                    }
                    return [:]
                }
                postData["USBLogs"] = usbLogArray
                
                default:
                    postData["Data"] = chunkData
                }

                logMessage("\(eventType) chunk \(index + 1)/\(chunks.count) data prepared", level: .debug)
                
                // Convert to JSON
                guard let jsonData = try? JSONSerialization.data(withJSONObject: postData) else {
                    logError("Failed to serialize JSON data for \(eventType) chunk \(index + 1)", context: eventType)
                    continue
                }
                
                // Create request
                var request = URLRequest(url: url)
                request.httpMethod = "POST"
                request.setValue("application/json", forHTTPHeaderField: "Content-Type")
            request.setValue("MonitorClient/\(APP_VERSION)", forHTTPHeaderField: "User-Agent")
            request.timeoutInterval = 30.0
                request.httpBody = jsonData
                
                logMessage("Sending \(eventType) chunk \(index + 1)/\(chunks.count) to: \(urlString)", level: .debug)
            logMessage("Request body size: \(jsonData.count) bytes", level: .debug)
            logMessage("Request headers: \(request.allHTTPHeaderFields ?? [:])", level: .debug)
            
            // Log the actual JSON being sent for debugging
            if let jsonString = String(data: jsonData, encoding: .utf8) {
                logMessage("Request JSON: \(jsonString)", level: .debug)
            }
                
                // Send request
                let task = URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
                    DispatchQueue.main.async {
                        if let error = error {
                            self?.logError("\(eventType) chunk \(index + 1)/\(chunks.count) network error: \(error)", context: eventType)
                            self?.logError("Error details: \(error.localizedDescription)", context: eventType)
                            self?.isServerConnected = false // Mark as disconnected on network error
                            return
                        }
                        
                        if let httpResponse = response as? HTTPURLResponse {
                            self?.logMessage("\(eventType) chunk \(index + 1)/\(chunks.count) HTTP response: \(httpResponse.statusCode)", level: .debug)
                        self?.logMessage("Response headers: \(httpResponse.allHeaderFields)", level: .debug)
                        
                        if httpResponse.statusCode != 200 {
                            self?.logError("\(eventType) chunk \(index + 1)/\(chunks.count) HTTP error: \(httpResponse.statusCode)", context: eventType)
                            self?.isServerConnected = false // Mark as disconnected on HTTP error
                        }
                        }
                        
                        if let data = data, let responseString = String(data: data, encoding: .utf8) {
                            self?.logSuccess("\(eventType) chunk \(index + 1)/\(chunks.count) sent successfully", details: "Response: \(responseString)")
                            self?.logMessage("Response data size: \(data.count) bytes", level: .debug)
                            self?.isServerConnected = true // Mark as connected on successful API call
                        } else {
                            self?.logError("No response data received for \(eventType) chunk \(index + 1)/\(chunks.count)", context: eventType)
                            if let data = data {
                                self?.logMessage("Raw response data: \(data)", level: .debug)
                            }
                        }
                    }
                }
                task.resume()
        }
                
        logMessage("=== End Sending \(eventType) Data ===", level: .info)
    }
    
    // Helper function to generate random string
    private func generateRandomString(length: Int) -> String {
        let characters = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"
        let randomString = String((0..<length).map { _ in
            characters.randomElement()!
        })
        return randomString
    }
    

    
    // Helper function to get readable key names
    func getKeyName(keyCode: Int, character: String) -> String {
        // First check for special key codes that should have specific labels
        switch keyCode {
        case 123: return "LEFT" // Left Arrow
        case 124: return "RIGHT" // Right Arrow
        case 125: return "DOWN" // Down Arrow
        case 126: return "UP" // Up Arrow
        case 36: return "ENTER" // Enter
        case 48: return "TAB" // Tab
        case 49: return " " // Space
        case 51: return "BACKSPACE" // Backspace
        case 53: return "ESCAPE" // Escape
        case 76: return "ENTER" // Enter
        case 116: return "PAGE_UP" // Page Up
        case 117: return "DELETE" // Delete
        case 121: return "PAGE_DOWN" // Page Down
        case 115: return "HOME"
        case 119: return "END"
        case 96: return "F5"
        case 97: return "F6"
        case 98: return "F7"
        case 99: return "F3"
        case 100: return "F8"
        case 101: return "F9"
        case 103: return "F11"
        case 105: return "F13"
        case 107: return "F14"
        case 109: return "F10"
        case 111: return "F12"
        case 122: return "F1"
        case 120: return "F2"
        case 118: return "F4"
        default:
            // If character is not empty and printable, use it
            if !character.isEmpty && character.rangeOfCharacter(from: CharacterSet.controlCharacters.inverted) != nil {
                return character
            }
            return "\(keyCode)"
        }
    }
            
    // New method to setup event tap invalidation monitoring
    private func setupEventTapInvalidationMonitoring() {
        // Monitor for system events that might invalidate event taps
        NotificationCenter.default.addObserver(
            self,
            selector: #selector(handleSystemEvent),
            name: NSWorkspace.didWakeNotification,
            object: nil
        )
        
        NotificationCenter.default.addObserver(
            self,
            selector: #selector(handleSystemEvent),
            name: NSWorkspace.screensDidWakeNotification,
            object: nil
        )
        
        NotificationCenter.default.addObserver(
            self,
            selector: #selector(handleSystemEvent),
            name: NSWorkspace.didLaunchApplicationNotification,
            object: nil
        )
        
        // Additional system events that might affect event taps
        NotificationCenter.default.addObserver(
            self,
            selector: #selector(handleSystemEvent),
            name: NSWorkspace.didTerminateApplicationNotification,
            object: nil
        )
        
        NotificationCenter.default.addObserver(
            self,
            selector: #selector(handleSystemEvent),
            name: NSWorkspace.didActivateApplicationNotification,
            object: nil
        )
        
        // Monitor for user session changes
        NotificationCenter.default.addObserver(
            self,
            selector: #selector(handleSystemEvent),
            name: NSWorkspace.sessionDidBecomeActiveNotification,
            object: nil
        )
        
        NotificationCenter.default.addObserver(
            self,
            selector: #selector(handleSystemEvent),
            name: NSWorkspace.sessionDidResignActiveNotification,
            object: nil
        )
        
        debugPrint("Event tap invalidation monitoring setup complete")
    }
    
    @objc private func handleSystemEvent(_ notification: Notification) {
        debugPrint("System event detected: \(notification.name.rawValue)")
        // Force check event tap validity after system events
        DispatchQueue.main.asyncAfter(deadline: .now() + 2.0) {
            self.checkAndReestablishEventTapThrottled()
        }
    }
    
    // Test method to manually trigger key log sending
    @objc func testSendKeyLogs() {
        logMessage("Manual key log test triggered", level: .info)
        sendKeyLogs()
    }
    
    // Reset accessibility permissions for testing
    @objc func resetAccessibilityPermissions() {
        logMessage("Resetting accessibility permission flags", level: .info)
        storage.removeObject(forKey: "accessibility-prompt-shown")
        storage.removeObject(forKey: "last-accessibility-trusted")
        storage.removeObject(forKey: "last-skip-log-time")
        logMessage("Accessibility permission flags reset", level: .info)
    }
    
    // Force request accessibility permission
    @objc func forceRequestAccessibilityPermission() {
        logMessage("Force requesting accessibility permission", level: .info)
        storage.removeObject(forKey: "accessibility-prompt-shown")
        requestAccessibilityPermission()
    }
    
    // Reset screen recording permissions for testing
    @objc func resetScreenRecordingPermissions() {
        logMessage("Resetting screen recording permission flags", level: .info)
        storage.removeObject(forKey: "screen-recording-prompt-shown")
        storage.removeObject(forKey: "last-screen-recording-check")
        storage.removeObject(forKey: "last-screen-recording-notification")
        logMessage("Screen recording permission flags reset", level: .info)
    }
    
    // Force request screen recording permission
    @objc func forceRequestScreenRecordingPermission() {
        logMessage("Force requesting screen recording permission", level: .info)
        storage.removeObject(forKey: "screen-recording-prompt-shown")
        requestScreenRecordingPermission()
    }
    
    // Open System Preferences to Accessibility settings
    @objc func openAccessibilityPreferences() {
        logMessage("Opening System Settings to Accessibility settings", level: .info)
        
        // Try multiple approaches to open System Settings
        let approaches = [
            // Approach 1: Direct URL scheme (works on macOS 13+)
            { () -> Bool in
                if let url = URL(string: "x-apple.systempreferences:com.apple.preference.security?Privacy_Accessibility") {
                    NSWorkspace.shared.open(url)
                    return true
                }
                return false
            },
            
            // Approach 2: Try System Settings (macOS 13+)
            { () -> Bool in
                let script = """
                tell application "System Settings"
                    activate
                    set current pane to pane id "com.apple.preference.security"
                    reveal anchor "Privacy_Accessibility"
                end tell
                """
                
                if let scriptObject = NSAppleScript(source: script) {
                    var error: NSDictionary?
                    scriptObject.executeAndReturnError(&error)
                    
                    if error == nil {
                        return true
                    }
                }
                return false
            },
            
            // Approach 3: Try System Preferences (older macOS)
            { () -> Bool in
                let script = """
                tell application "System Preferences"
                    activate
                    set current pane to pane id "com.apple.preference.security"
                    reveal anchor "Privacy_Accessibility"
                end tell
                """
                
                if let scriptObject = NSAppleScript(source: script) {
                    var error: NSDictionary?
                    scriptObject.executeAndReturnError(&error)
                    
                    if error == nil {
                        return true
                    }
                }
                return false
            },
            
            // Approach 4: Just open System Settings/Preferences
            { () -> Bool in
                // Try System Settings first (macOS 13+)
                if NSWorkspace.shared.open(URL(string: "x-apple.systempreferences:")!) {
                    return true
                }
                
                // Fallback to System Preferences
                if let url = URL(string: "x-apple.systempreferences:com.apple.preference.security") {
                    NSWorkspace.shared.open(url)
                    return true
                }
                
                return false
            }
        ]
        
        // Try each approach
        for (index, approach) in approaches.enumerated() {
            if approach() {
                logMessage("Successfully opened System Settings using approach \(index + 1)", level: .info)
                return
            }
        }
        
        // If all approaches fail, provide manual instructions
        logMessage("Failed to open System Settings automatically", level: .warning)
        logMessage("Please manually open System Settings > Privacy & Security > Accessibility", level: .info)
        logMessage("Then add this app to the Accessibility list", level: .info)
        
        // Show a user notification with manual instructions
        let notification = NSUserNotification()
        notification.title = "Manual Accessibility Setup Required"
        notification.informativeText = "Please open System Settings > Privacy & Security > Accessibility and add this app"
        notification.soundName = NSUserNotificationDefaultSoundName
        
        NSUserNotificationCenter.default.deliver(notification)
    }
    
    // Open System Preferences to Screen Recording settings
    @objc func openScreenRecordingPreferences() {
        logMessage("Opening System Settings to Screen Recording settings", level: .info)
        
        // Try multiple approaches to open System Settings
        let approaches = [
            // Approach 1: Direct URL scheme (works on macOS 13+)
            { () -> Bool in
                if let url = URL(string: "x-apple.systempreferences:com.apple.preference.security?Privacy_ScreenCapture") {
                    NSWorkspace.shared.open(url)
                    return true
                }
                return false
            },
            
            // Approach 2: Try System Settings (macOS 13+)
            { () -> Bool in
                let script = """
                tell application "System Settings"
                    activate
                    set current pane to pane id "com.apple.preference.security"
                    reveal anchor "Privacy_ScreenCapture"
                end tell
                """
                
                if let scriptObject = NSAppleScript(source: script) {
                    var error: NSDictionary?
                    scriptObject.executeAndReturnError(&error)
                    
                    if error == nil {
                        return true
                    }
                }
                return false
            },
            
            // Approach 3: Try System Preferences (older macOS)
            { () -> Bool in
                let script = """
                tell application "System Preferences"
                    activate
                    set current pane to pane id "com.apple.preference.security"
                    reveal anchor "Privacy_ScreenCapture"
                end tell
                """
                
                if let scriptObject = NSAppleScript(source: script) {
                    var error: NSDictionary?
                    scriptObject.executeAndReturnError(&error)
                    
                    if error == nil {
                        return true
                    }
                }
                return false
            },
            
            // Approach 4: Just open System Settings/Preferences
            { () -> Bool in
                // Try System Settings first (macOS 13+)
                if NSWorkspace.shared.open(URL(string: "x-apple.systempreferences:")!) {
                    return true
                }
                
                // Fallback to System Preferences
                if let url = URL(string: "x-apple.systempreferences:com.apple.preference.security") {
                    NSWorkspace.shared.open(url)
                    return true
                }
                
                return false
            }
        ]
        
        // Try each approach
        for (index, approach) in approaches.enumerated() {
            if approach() {
                logMessage("Successfully opened System Settings using approach \(index + 1)", level: .info)
                return
            }
        }
        
        // If all approaches fail, provide manual instructions
        logMessage("Failed to open System Settings automatically", level: .warning)
        logMessage("Please manually open System Settings > Privacy & Security > Screen Recording", level: .info)
        logMessage("Then add this app to the Screen Recording list", level: .info)
        
        // Show a user notification with manual instructions
        let notification = NSUserNotification()
        notification.title = "Manual Screen Recording Setup Required"
        notification.informativeText = "Please open System Settings > Privacy & Security > Screen Recording and add this app"
        notification.soundName = NSUserNotificationDefaultSoundName
        
        NSUserNotificationCenter.default.deliver(notification)
    }
    
    // Check accessibility permission periodically and request if needed
    private func checkAccessibilityPermissionPeriodically() {
        // Check every 15 minutes (increased from 5 minutes to reduce annoyance)
        let lastCheck = storage.double(forKey: "last-accessibility-check")
        let currentTime = Date().timeIntervalSince1970
        
        if currentTime - lastCheck > 900 { // 15 minutes
            storage.set(currentTime, forKey: "last-accessibility-check")
            
            if !isInputMonitoringEnabled() {
                // Only show notification if we haven't shown it recently
                let lastNotificationTime = storage.double(forKey: "last-accessibility-notification")
                if currentTime - lastNotificationTime > 3600 { // Only show notification once per hour
                    logMessage("Periodic accessibility check: permission not granted, showing notification...", level: .info)
                    
                    // Show user notification about accessibility permission
                    let notification = NSUserNotification()
                    notification.title = "MonitorClient Needs Accessibility Permission"
                    notification.informativeText = "Please grant accessibility permission to enable keyboard monitoring"
                    notification.soundName = NSUserNotificationDefaultSoundName
                    notification.actionButtonTitle = "Open Settings"
                    notification.otherButtonTitle = "Later"
                    
                    NSUserNotificationCenter.default.deliver(notification)
                    
                    storage.set(currentTime, forKey: "last-accessibility-notification")
                }
                
                // Only request permission if it was previously granted (revoked case)
                let wasPreviouslyGranted = storage.bool(forKey: "accessibility-was-granted")
                if wasPreviouslyGranted {
                    requestAccessibilityPermission()
                }
            } else {
                logMessage("Periodic accessibility check: permission granted", level: .debug)
            }
        }
    }
    
    // Check screen recording permission periodically and request if needed
    private func checkScreenRecordingPermissionPeriodically() {
        if #available(macOS 10.15, *) {
            // Check every 15 minutes (same as accessibility)
            let lastCheck = storage.double(forKey: "last-screen-recording-check")
            let currentTime = Date().timeIntervalSince1970
            
            if currentTime - lastCheck > 900 { // 15 minutes
                storage.set(currentTime, forKey: "last-screen-recording-check")
                
                if !CGPreflightScreenCaptureAccess() {
                    // Only show notification if we haven't shown it recently
                    let lastNotificationTime = storage.double(forKey: "last-screen-recording-notification")
                    if currentTime - lastNotificationTime > 3600 { // Only show notification once per hour
                        logMessage("Periodic screen recording check: permission not granted, showing notification...", level: .info)
                        
                        // Show user notification about screen recording permission
                        let notification = NSUserNotification()
                        notification.title = "MonitorClient Needs Screen Recording Permission"
                        notification.informativeText = "Please grant screen recording permission to enable screenshot monitoring"
                        notification.soundName = NSUserNotificationDefaultSoundName
                        notification.actionButtonTitle = "Open Settings"
                        notification.otherButtonTitle = "Later"
                        
                        NSUserNotificationCenter.default.deliver(notification)
                        
                        storage.set(currentTime, forKey: "last-screen-recording-notification")
                    }
                    
                    // Only request permission if it was previously granted (revoked case)
                    let wasPreviouslyGranted = storage.bool(forKey: "screen-recording-was-granted")
                    if wasPreviouslyGranted {
                        requestScreenRecordingPermission()
                    }
                } else {
                    logMessage("Periodic screen recording check: permission granted", level: .debug)
                }
            }
        }
    }
    
    // Test network connectivity to server
    @objc func testServerConnectivity() {
        guard let urlString = buildEndpoint(false), let url = URL(string: urlString) else {
            logError("Cannot test connectivity - invalid endpoint", context: "Connectivity")
            return
        }
        
        logMessage("Testing connectivity to: \(urlString)", level: .info)
        
        var request = URLRequest(url: url)
        request.httpMethod = "GET"
        request.setValue("MonitorClient/\(APP_VERSION)", forHTTPHeaderField: "User-Agent")
        request.timeoutInterval = 10.0
        
        let task = URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            DispatchQueue.main.async {
                if let error = error {
                    self?.logError("Connectivity test failed: \(error)", context: "Connectivity")
                    self?.isServerConnected = false
                    return
                }
                
                if let httpResponse = response as? HTTPURLResponse {
                    if httpResponse.statusCode == 200 {
                        self?.logSuccess("Connectivity test successful", details: "HTTP \(httpResponse.statusCode)")
                        self?.isServerConnected = true
                    } else {
                        self?.logError("Connectivity test failed - HTTP \(httpResponse.statusCode)", context: "Connectivity")
                        self?.isServerConnected = false
                    }
                } else {
                    self?.logError("Connectivity test failed - no HTTP response", context: "Connectivity")
                    self?.isServerConnected = false
                }
            }
        }
        task.resume()
    }
    
    @objc private func testSettings() {
        logMessage("=== Testing Settings ===", level: .info)
        
        let settingsPath = getSettingsFilePath()
        logMessage("Settings file path: \(settingsPath)", level: .info)
        
        let fileExists = FileManager.default.fileExists(atPath: settingsPath)
        logMessage("Settings file exists: \(fileExists)", level: .info)
        
        if let settings = NSDictionary(contentsOfFile: settingsPath) {
            logMessage("Settings loaded: \(settings)", level: .info)
        } else {
            logMessage("No settings file found", level: .info)
        }
        
        let currentServerAddress = storage.string(forKey: "server-ip") ?? "Not set"
        let currentPort = storage.string(forKey: "server-port") ?? "Not set"
        logMessage("Current server address: \(currentServerAddress)", level: .info)
        logMessage("Current port: \(currentPort)", level: .info)
        
        logMessage("=== End Settings Test ===", level: .info)
    }
    

    
    // MARK: - NSUserNotificationCenterDelegate
    
    func userNotificationCenter(_ center: NSUserNotificationCenter, didActivate notification: NSUserNotification) {
        if notification.actionButtonTitle == "Open Settings" {
            openAccessibilityPreferences()
        }
    }
    
    func userNotificationCenter(_ center: NSUserNotificationCenter, shouldPresent notification: NSUserNotification) -> Bool {
        return true
    }

    // Debug method to check accessibility permission status
    @objc func debugAccessibilityStatus() {
        logMessage("=== Accessibility Permission Debug ===", level: .info)
        
        // Check current permission status
        let isTrusted = AXIsProcessTrusted()
        logMessage("AXIsProcessTrusted(): \(isTrusted)", level: .info)
        
        // Check app bundle identifier
        if let bundleId = Bundle.main.bundleIdentifier {
            logMessage("Bundle Identifier: \(bundleId)", level: .info)
        }
        
        // Check if app is running from Xcode
        let isRunningFromXcode = ProcessInfo.processInfo.environment["XPC_SERVICE_NAME"] != nil || 
                                ProcessInfo.processInfo.environment["XCODE_RUNNING_FOR_PREVIEWS"] == "1"
        logMessage("Running from Xcode: \(isRunningFromXcode)", level: .info)
        
        // Check app path
        let appPath = Bundle.main.bundlePath
        logMessage("App Path: \(appPath)", level: .info)
        
        // Check if app is in Applications folder
        let isInApplications = appPath.contains("/Applications/")
        logMessage("In Applications folder: \(isInApplications)", level: .info)
        
        // Check stored permission flags
        let promptShown = storage.bool(forKey: "accessibility-prompt-shown")
        let lastTrusted = storage.bool(forKey: "last-accessibility-trusted")
        logMessage("Stored flags - Prompt shown: \(promptShown), Last trusted: \(lastTrusted)", level: .info)
        
        // Check event tap status
        if let tap = eventTap {
            let isEnabled = CGEvent.tapIsEnabled(tap: tap)
            let portValid = CFMachPortIsValid(tap)
            logMessage("Event tap - Enabled: \(isEnabled), Port valid: \(portValid)", level: .info)
        } else {
            logMessage("Event tap: nil", level: .info)
        }
        
        logMessage("=== End Debug ===", level: .info)
    }
    
    // Test keyboard monitoring manually
    @objc func testKeyboardMonitoring() {
        logMessage("=== Testing Keyboard Monitoring ===", level: .info)
        
        // Check permission
        if isInputMonitoringEnabled() {
            logMessage("✅ Accessibility permission granted", level: .info)
            
            // Try to setup keyboard monitoring
            setupKeyboardMonitoring()
            
            // Check if event tap was created
            if let tap = eventTap {
                let isEnabled = CGEvent.tapIsEnabled(tap: tap)
                logMessage("✅ Event tap created and enabled: \(isEnabled)", level: .info)
                
                // Add a test key log
                let testKeyLog = KeyLog(date: getCurrentDateTimeString(), application: "Test", key: "TEST_KEY")
                keyLogs.append(testKeyLog)
                logMessage("✅ Added test key log, total count: \(keyLogs.count)", level: .info)
                
            } else {
                logMessage("❌ Failed to create event tap", level: .error)
            }
        } else {
            logMessage("❌ Accessibility permission not granted", level: .error)
        }
        
        logMessage("=== End Test ===", level: .info)
    }
    
    // Test screen recording permission manually
    @objc func testScreenRecordingPermission() {
        logMessage("=== Testing Screen Recording Permission ===", level: .info)
        
        if #available(macOS 10.15, *) {
            let isEnabled = CGPreflightScreenCaptureAccess()
            logMessage("Screen recording permission: \(isEnabled ? "✅ GRANTED" : "❌ DENIED")", level: .info)
            
            if isEnabled {
                logMessage("✅ Screen recording permission granted", level: .info)
                
                // Try to take a test screenshot
                logMessage("Taking test screenshot...", level: .info)
                TakeScreenShotsAndPost()
                
            } else {
                logMessage("❌ Screen recording permission not granted", level: .error)
                logMessage("Requesting screen recording permission...", level: .info)
                requestScreenRecordingPermission()
            }
        } else {
            logMessage("ℹ️  Screen recording permission not required on this macOS version", level: .info)
        }
        
        logMessage("=== End Screen Recording Test ===", level: .info)
    }
    
    // Test browser history collection manually
    @objc func testBrowserHistoryCollection() {
        logMessage("=== Testing Browser History Collection ===", level: .info)
        
        do {
            let histories = try getBrowserHistories() ?? []
            logMessage("✅ Collected \(histories.count) browser history entries", level: .info)
            
            // Log a sample of the histories
            let sampleCount = min(3, histories.count)
            for i in 0..<sampleCount {
                let history = histories[i]
                logMessage("Sample history \(i+1): \(history.browser) - \(history.url)", level: .debug)
            }
            
            // Send the histories
            if histories.count > 0 {
                sendDataInChunks(data: histories, eventType: "BrowserHistory", chunkSize: 1000)
                logMessage("✅ Browser histories sent to server", level: .info)
            } else {
                logMessage("ℹ️  No browser histories to send", level: .info)
            }
            
        } catch {
            logError("Failed to collect browser histories: \(error)", context: "BrowserHistoryTest")
        }
        
        logMessage("=== End Browser History Test ===", level: .info)
    }
    
    // Check app entitlements and provide debugging guidance
    @objc func checkAppEntitlements() {
        logMessage("=== App Entitlements Check ===", level: .info)
        
        // Check if app has accessibility entitlements
        let hasAccessibilityEntitlement = Bundle.main.object(forInfoDictionaryKey: "NSAppleEventsUsageDescription") != nil ||
                                         Bundle.main.object(forInfoDictionaryKey: "NSSystemAdministrationUsageDescription") != nil
        
        logMessage("Has accessibility entitlements: \(hasAccessibilityEntitlement)", level: .info)
        
        // Check if running in sandbox
        let isSandboxed = Bundle.main.object(forInfoDictionaryKey: "com.apple.security.app-sandbox") != nil
        logMessage("App is sandboxed: \(isSandboxed)", level: .info)
        
        // Check if running from Xcode
        let isRunningFromXcode = ProcessInfo.processInfo.environment["XPC_SERVICE_NAME"] != nil || 
                                ProcessInfo.processInfo.environment["XCODE_RUNNING_FOR_PREVIEWS"] == "1"
        logMessage("Running from Xcode: \(isRunningFromXcode)", level: .info)
        
        // Provide guidance based on the situation
        if isRunningFromXcode {
            logMessage("⚠️  Running from Xcode - accessibility permissions may not work properly", level: .warning)
            logMessage("💡 Try building and running the app outside of Xcode", level: .info)
        }
        
        if isSandboxed {
            logMessage("⚠️  App is sandboxed - this may affect accessibility permissions", level: .warning)
        }
        
        if !hasAccessibilityEntitlement {
            logMessage("⚠️  App may be missing accessibility entitlements", level: .warning)
            logMessage("💡 Check your app's entitlements file", level: .info)
        }
        
        logMessage("=== End Entitlements Check ===", level: .info)
    }
    
    // Test server endpoint with a simple key log
    @objc func testServerEndpoint() {
        logMessage("=== Testing Server Endpoint ===", level: .info)
        
        guard let urlString = buildEndpoint(false), let url = URL(string: urlString) else {
            logError("Cannot test endpoint - invalid URL", context: "ServerTest")
            return
        }
        
        logMessage("Testing endpoint: \(urlString)", level: .info)
        
        // Prepare test data (matching Windows format)
        var postData: [String: Any] = [
            "Event": "KeyLog",
            "Version": APP_VERSION,
            "MacAddress": macAddress,
            "KeyLogs": [
                [
                    "date": getCurrentDateTimeString(),
                    "application": "TestApp (com.test.app)",
                    "key": "TEST_KEY"
                ]
            ]
                ]
        
        // Convert to JSON
        guard let jsonData = try? JSONSerialization.data(withJSONObject: postData) else {
            logError("Failed to serialize test JSON data", context: "ServerTest")
            return
        }
        
        // Create request
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue("MonitorClient/\(APP_VERSION)", forHTTPHeaderField: "User-Agent")
        request.timeoutInterval = 30.0
        request.httpBody = jsonData
        
        logMessage("Sending test request...", level: .info)
        logMessage("Request body size: \(jsonData.count) bytes", level: .debug)
        logMessage("Request headers: \(request.allHTTPHeaderFields ?? [:])", level: .debug)
        
        // Log the actual JSON being sent
        if let jsonString = String(data: jsonData, encoding: .utf8) {
            logMessage("Request JSON: \(jsonString)", level: .debug)
        }
        
        // Send request
        let task = URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            DispatchQueue.main.async {
                if let error = error {
                    self?.logError("Test request failed: \(error)", context: "ServerTest")
                    self?.logError("Error details: \(error.localizedDescription)", context: "ServerTest")
                    return
                }
                
                if let httpResponse = response as? HTTPURLResponse {
                    self?.logMessage("Test HTTP response: \(httpResponse.statusCode)", level: .info)
                    self?.logMessage("Response headers: \(httpResponse.allHeaderFields)", level: .debug)
                    
                    if httpResponse.statusCode != 200 {
                        self?.logError("Test HTTP error: \(httpResponse.statusCode)", context: "ServerTest")
                    }
                }
                
                if let data = data, let responseString = String(data: data, encoding: .utf8) {
                    self?.logSuccess("Test request successful", details: "Response: \(responseString)")
                    self?.logMessage("Response data size: \(data.count) bytes", level: .debug)
                } else {
                    self?.logError("No response data received for test request", context: "ServerTest")
                    if let data = data {
                        self?.logMessage("Raw response data: \(data)", level: .debug)
                    }
                }
            }
        }
        task.resume()
        
        logMessage("=== End Server Test ===", level: .info)
    }
    
    // MARK: - Status Bar Menu
    
    private func setupStatusBarMenu() {
        print("Setting up status bar menu...")
        
        // Create status bar item
        statusBarItem = NSStatusBar.system.statusItem(withLength: NSStatusItem.variableLength)
        print("Status bar item created")
        
        if let button = statusBarItem?.button {
            button.title = "🗒️"
            button.font = NSFont.systemFont(ofSize: 14)
            
            // Add click action for status bar icon
            button.action = #selector(statusBarIconClicked)
            button.target = self
            print("Status bar button configured")
        } else {
            print("ERROR: Could not get status bar button")
        }
        
        // Create menu
        statusMenu = NSMenu()
        statusMenu?.autoenablesItems = false
        
        // Add menu items
        setupStatusMenuItems()
        
        // Set the menu
        statusBarItem?.menu = statusMenu
        
        // Start status update timer (optimized interval)
        statusUpdateTimer = Timer.scheduledTimer(timeInterval: 10.0, target: self, selector: #selector(updateStatusMenu), userInfo: nil, repeats: true)
        
        logMessage("Status bar menu setup complete", level: .info)
    }
    
    private func setupStatusMenuItems() {
        guard let menu = statusMenu else { return }
        
        // Clear existing items
        menu.removeAllItems()
        
        // App title
        let titleItem = NSMenuItem(title: "MonitorClient v\(APP_VERSION)", action: nil, keyEquivalent: "")
        titleItem.isEnabled = false
        menu.addItem(titleItem)
        
        menu.addItem(NSMenuItem.separator())
        
        // Client information
        let clientItem = NSMenuItem(title: "Client: \(clientIPAddress) / \(macAddress)", action: nil, keyEquivalent: "")
        clientItem.tag = 99
        clientItem.isEnabled = false
        menu.addItem(clientItem)
        
        // Server information
        let serverIP = storage.string(forKey: "server-ip") ?? "Not configured"
        let serverPort = storage.string(forKey: "server-port") ?? "8924"
        let serverAddress = serverIP == "Not configured" ? "Not configured" : "\(serverIP):\(serverPort)"
        let serverItem = NSMenuItem(title: "Server: \(serverAddress)", action: nil, keyEquivalent: "")
        serverItem.tag = 100 // Tag for easy identification
        serverItem.isEnabled = false
        menu.addItem(serverItem)
        
        // Accessibility status
        let accessibilityItem = NSMenuItem(title: "Accessibility: Checking...", action: nil, keyEquivalent: "")
        accessibilityItem.tag = 101
        accessibilityItem.isEnabled = false
        menu.addItem(accessibilityItem)
        
        // Screen recording status
        let screenRecordingItem = NSMenuItem(title: "Screen Recording: Checking...", action: nil, keyEquivalent: "")
        screenRecordingItem.tag = 103
        screenRecordingItem.isEnabled = false
        menu.addItem(screenRecordingItem)
        
        // Keyboard monitoring status
        let keyboardItem = NSMenuItem(title: "Keyboard: Checking...", action: nil, keyEquivalent: "")
        keyboardItem.tag = 102
        keyboardItem.isEnabled = false
        menu.addItem(keyboardItem)
        
        // Last sent times section
        menu.addItem(NSMenuItem.separator())
        
        // Key logs last sent
        let keyLogSentItem = NSMenuItem(title: "Key Logs: Never sent", action: nil, keyEquivalent: "")
        keyLogSentItem.tag = 200
        keyLogSentItem.isEnabled = false
        menu.addItem(keyLogSentItem)
        
        // Browser history last sent
        let browserHistorySentItem = NSMenuItem(title: "Browser History: Never sent", action: nil, keyEquivalent: "")
        browserHistorySentItem.tag = 201
        browserHistorySentItem.isEnabled = false
        menu.addItem(browserHistorySentItem)
        
        // USB logs last sent
        let usbLogSentItem = NSMenuItem(title: "USB Logs: Never sent", action: nil, keyEquivalent: "")
        usbLogSentItem.tag = 202
        usbLogSentItem.isEnabled = false
        menu.addItem(usbLogSentItem)
        
        // Screenshots last sent
        let screenshotSentItem = NSMenuItem(title: "Screenshots: Never sent", action: nil, keyEquivalent: "")
        screenshotSentItem.tag = 203
        screenshotSentItem.isEnabled = false
        menu.addItem(screenshotSentItem)
        
        // Tic events last sent
        let ticSentItem = NSMenuItem(title: "Tic Events: Never sent", action: nil, keyEquivalent: "")
        ticSentItem.tag = 204
        ticSentItem.isEnabled = false
        menu.addItem(ticSentItem)
        

        
        // Settings
        let settingsItem = NSMenuItem(title: "Settings...", action: #selector(openSettingsDialog), keyEquivalent: ",")
        settingsItem.keyEquivalentModifierMask = [.command]
        menu.addItem(settingsItem)
        
        let openSettingsItem = NSMenuItem(title: "Open Accessibility Settings", action: #selector(openAccessibilityPreferences), keyEquivalent: "")
        menu.addItem(openSettingsItem)
        
        let openScreenRecordingItem = NSMenuItem(title: "Open Screen Recording Settings", action: #selector(openScreenRecordingPreferences), keyEquivalent: "")
        menu.addItem(openScreenRecordingItem)
        
        menu.addItem(NSMenuItem.separator())
        
        // Manual test actions
        let startupTestItem = NSMenuItem(title: "Run Startup Tests", action: #selector(performStartupTests), keyEquivalent: "")
        menu.addItem(startupTestItem)
        
        menu.addItem(NSMenuItem.separator())
        
        // Quit
        let quitItem = NSMenuItem(title: "Quit MonitorClient", action: #selector(quitApp), keyEquivalent: "q")
        quitItem.keyEquivalentModifierMask = [.command]
        menu.addItem(quitItem)
    }
    
    @objc private func statusBarIconClicked() {
        // Show a quick status notification
        let notification = NSUserNotification()
        notification.title = "MonitorClient Status"
        
        let serverIP = storage.string(forKey: "server-ip") ?? "Not configured"
        let serverPort = storage.string(forKey: "server-port") ?? "8924"
        let serverStatus: String
        if serverIP == "Not configured" {
            serverStatus = "Not configured"
        } else {
            let serverAddress = "\(serverIP):\(serverPort)"
            serverStatus = "\(serverAddress) (\(isServerConnected ? "Connected" : "Disconnected"))"
        }
        
        let isAccessibilityEnabled = isInputMonitoringEnabled()
        let isScreenRecordingEnabled = isScreenRecordingEnabled()
        let isKeyboardActive = eventTap != nil && CGEvent.tapIsEnabled(tap: eventTap!)
        
        notification.informativeText = """
        Client: \(clientIPAddress) / \(macAddress)
        Server: \(serverIP) (\(serverStatus))
        Accessibility: \(isAccessibilityEnabled ? "✅" : "❌")
        Screen Recording: \(isScreenRecordingEnabled ? "✅" : "❌")
        Keyboard: \(isKeyboardActive ? "✅" : "❌")
        Keys: \(keyLogs.count)
        USB Events: \(usbDeviceLogs.count)
        """
        
        notification.soundName = nil // No sound for status clicks
        NSUserNotificationCenter.default.deliver(notification)
    }
    
    @objc private func updateStatusMenu() {
        guard let menu = statusMenu else { return }
        
        // Update client information
        if let clientItem = menu.item(withTag: 99) {
            clientItem.title = "Client: \(clientIPAddress) / \(macAddress)"
        }
        
        // Update server status
        if let serverItem = menu.item(withTag: 100) {
            let serverIP = storage.string(forKey: "server-ip") ?? "Not configured"
            let serverPort = storage.string(forKey: "server-port") ?? "8924"
            if serverIP == "Not configured" {
                serverItem.title = "Server: Not configured ❌"
            } else {
                let serverAddress = "\(serverIP):\(serverPort)"
                serverItem.title = "Server: \(serverAddress) \(isServerConnected ? "✅" : "❌")"
            }
        }
        
        // Update accessibility status
        if let accessibilityItem = menu.item(withTag: 101) {
            let isEnabled = isInputMonitoringEnabled()
            accessibilityItem.title = "Accessibility: \(isEnabled ? "✅ Enabled" : "❌ Disabled")"
        }
        
        // Update screen recording status
        if let screenRecordingItem = menu.item(withTag: 103) {
            let isEnabled = isScreenRecordingEnabled()
            screenRecordingItem.title = "Screen Recording: \(isEnabled ? "✅ Enabled" : "❌ Disabled")"
        }
        
        // Update keyboard monitoring status
        if let keyboardItem = menu.item(withTag: 102) {
            let isEnabled = eventTap != nil && CGEvent.tapIsEnabled(tap: eventTap!)
            let keyCount = keyLogs.count
            keyboardItem.title = "Keyboard: \(isEnabled ? "✅ Active" : "❌ Inactive") (\(keyCount) keys)"
        }
        
        // Update last sent times
        updateLastSentTime(menu: menu, tag: 200, lastSent: lastKeyLogSent, type: "Key Logs")
        updateLastSentTime(menu: menu, tag: 201, lastSent: lastBrowserHistorySent, type: "Browser History")
        updateLastSentTime(menu: menu, tag: 202, lastSent: lastUSBLogSent, type: "USB Logs")
        updateLastSentTime(menu: menu, tag: 203, lastSent: lastScreenshotSent, type: "Screenshots")
        updateLastSentTime(menu: menu, tag: 204, lastSent: lastTicSent, type: "Tic Events")
    }
    
    private func updateLastSentTime(menu: NSMenu, tag: Int, lastSent: Date?, type: String) {
        if let item = menu.item(withTag: tag) {
            if let lastSent = lastSent {
                let formatter = DateFormatter()
                formatter.dateFormat = "HH:mm:ss"
                let timeString = formatter.string(from: lastSent)
                item.title = "\(type): \(timeString)"
            } else {
                item.title = "\(type): Never sent"
            }
        }
    }
    
    // Quit the app (useful for background apps)
    @objc func quitApp() {
        logMessage("Quitting MonitorClient...", level: .info)
        NSApplication.shared.terminate(nil)
    }
    
    // Test server with different data formats
    @objc func testServerFormats() {
        logMessage("=== Testing Different Server Formats ===", level: .info)
        
        guard let urlString = buildEndpoint(false), let url = URL(string: urlString) else {
            logError("Cannot test formats - invalid URL", context: "FormatTest")
            return
        }
        
        // Test 1: Simple Tic event (should work)
        testFormat(url: url, data: [
            "Event": "Tic",
            "Version": APP_VERSION,
            "MacAddress": macAddress
        ], name: "Tic Event")
        
        // Test 2: Empty KeyLogs array
        testFormat(url: url, data: [
            "Event": "KeyLog",
            "Version": APP_VERSION,
            "MacAddress": macAddress,
            "KeyLogs": []
        ], name: "Empty KeyLogs")
        
        // Test 3: Single key log (matching Windows format)
        testFormat(url: url, data: [
            "Event": "KeyLog",
            "Version": APP_VERSION,
            "MacAddress": macAddress,
            "KeyLogs": [
                [
                    "date": getCurrentDateTimeString(),
                    "application": "TestApp (com.test.app)",
                    "key": "A"
                ]
            ]
        ], name: "Single KeyLog")
        
        // Test 4: Single browser history (matching Windows format)
        testFormat(url: url, data: [
            "Event": "BrowserHistory",
            "Version": APP_VERSION,
            "MacAddress": macAddress,
            "BrowserHistories": [
                [
                    "browser": "Safari",
                    "url": "https://example.com",
                    "title": "Example Page",
                    "last_visit": 1234567890,
                    "date": getCurrentDateTimeString()
                ]
            ]
        ], name: "Single BrowserHistory")
        
        // Test 5: Single USB log (matching Windows format)
        testFormat(url: url, data: [
            "Event": "USBLog",
            "Version": APP_VERSION,
            "MacAddress": macAddress,
            "USBLogs": [
                [
                    "date": getCurrentDateTimeString(),
                    "device_name": "Test USB Device",
                    "device_path": "USB Device Path",
                    "device_type": "USB Device",
                    "action": "Connected"
                ]
            ]
        ], name: "Single USBLog")
    }
    
    private func testFormat(url: URL, data: [String: Any], name: String) {
        logMessage("Testing format: \(name)", level: .info)
        
        do {
            let jsonData = try JSONSerialization.data(withJSONObject: data)
            
            var request = URLRequest(url: url)
            request.httpMethod = "POST"
            request.setValue("application/json", forHTTPHeaderField: "Content-Type")
            request.setValue("MonitorClient/\(APP_VERSION)", forHTTPHeaderField: "User-Agent")
            request.timeoutInterval = 30.0
            request.httpBody = jsonData
            
            let task = URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
                DispatchQueue.main.async {
                    if let error = error {
                        self?.logError("\(name) failed: \(error)", context: "FormatTest")
                        return
                    }
                    
                    if let httpResponse = response as? HTTPURLResponse {
                        if httpResponse.statusCode == 200 {
                            self?.logSuccess("\(name) successful", details: "HTTP \(httpResponse.statusCode)")
                        } else {
                            self?.logError("\(name) failed - HTTP \(httpResponse.statusCode)", context: "FormatTest")
                        }
                    }
                    
                    if let data = data, let responseString = String(data: data, encoding: .utf8) {
                        self?.logMessage("\(name) response: \(responseString)", level: .debug)
                    }
                }
            }
            task.resume()
            
        } catch {
            logError("Failed to test \(name): \(error)", context: "FormatTest")
        }
    }
}



