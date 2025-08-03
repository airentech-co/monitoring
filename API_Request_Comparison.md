# Windows vs MacOS Monitor Client API Request Comparison

## Overview
Both Windows and MacOS versions of the Monitor Client implement the same core monitoring functionality but differ in implementation details, timing intervals, and platform-specific features.

## API Endpoints
Both versions use identical API endpoints:
- **Screenshots**: `/webapi.php` (multipart/form-data)
- **Events**: `/eventhandler.php` (application/json)

## Key Differences

### 1. Configuration Storage
| Feature | Windows | MacOS |
|---------|---------|-------|
| **Storage Method** | `settings.ini` file | `UserDefaults` (NSUserDefaults) |
| **Configuration** | INI format | Key-value pairs |
| **Location** | Application directory | System preferences |

### 2. Default Monitoring Intervals (seconds)
| Event Type | Windows | MacOS | Difference |
|------------|---------|-------|------------|
| **Screenshot** | 5 | 60 | MacOS 12x slower |
| **Tic Event** | 30 | 300 | MacOS 10x slower |
| **Browser History** | 120 | 600 | MacOS 5x slower |
| **Key Logs** | 60 | 300 | MacOS 5x slower |
| **USB Logs** | 30 | 300 | MacOS 10x slower |

### 3. Browser Support
| Browser | Windows | MacOS | Notes |
|---------|---------|-------|-------|
| **Chrome** | ✅ | ✅ | Both use SQLite |
| **Firefox** | ✅ | ✅ | Both use SQLite |
| **Edge** | ✅ | ✅ | Both use SQLite |
| **Safari** | ❌ | ✅ | MacOS only |
| **Opera** | ❌ | ✅ | MacOS only |
| **Yandex** | ❌ | ✅ | MacOS only |
| **Vivaldi** | ❌ | ✅ | MacOS only |
| **Brave** | ❌ | ✅ | MacOS only |

### 4. Screenshot Capture
| Feature | Windows | MacOS |
|---------|---------|-------|
| **Method** | GDI+ BitBlt | CGWindowListCreateImage |
| **Compression** | 0.8 | 0.21 |
| **Permissions** | None required | Screen Recording permission |
| **Format** | JPEG | JPEG |

### 5. Keyboard Monitoring
| Feature | Windows | MacOS |
|---------|---------|-------|
| **Method** | Windows Hook (WH_KEYBOARD_LL) | CGEvent Tap |
| **Permissions** | None required | Accessibility permission |
| **Event Types** | All keyboard events | keyDown only |
| **Key Mapping** | Virtual key codes to names | Native key names |

### 6. USB Device Monitoring
| Feature | Windows | MacOS |
|---------|---------|-------|
| **Method** | Device Interface Notifications | IOKit USB Notifications |
| **Device Types** | USB HUB, Device, Disk, CDROM, Keyboard, Mouse | USB Mass Storage, HID, Audio, Video, Network |
| **Data Structure** | `device`, `action` | `device_name`, `device_path`, `device_type`, `action` |

### 7. Data Structures

#### Browser History
**Windows:**
```json
{
  "browser": "string",
  "url": "string", 
  "title": "string"
}
```

**MacOS:**
```json
{
  "browser": "string",
  "url": "string",
  "title": "string", 
  "last_visit": "Int64",
  "date": "string"
}
```

#### USB Logs
**Windows:**
```json
{
  "date": "string",
  "device": "string",
  "action": "string"
}
```

**MacOS:**
```json
{
  "date": "string",
  "device_name": "string",
  "device_path": "string", 
  "device_type": "string",
  "action": "string"
}
```

### 8. Advanced Features

#### MacOS-Only Features
- **Data Chunking**: Automatic chunking of large datasets (1000 items for history, 500 for logs)
- **Permission Management**: Built-in permission request dialogs
- **Unified Logging**: System-level logging with os.log
- **Menu Bar Integration**: Native macOS menu bar app
- **System Notifications**: Native notification center integration

#### Windows-Only Features
- **System Tray**: Windows system tray integration
- **Toast Notifications**: Windows toast notification system
- **Active Window Detection**: Real-time active window title capture
- **Mouse Monitoring**: Mouse event capture capability

### 9. Error Handling
| Feature | Windows | MacOS |
|---------|---------|-------|
| **Network Timeout** | Default | 30 seconds |
| **Retry Mechanism** | Basic | Exponential backoff |
| **Logging** | File-based | Unified Logging (os.log) |
| **Error Recovery** | Manual | Automatic with fallbacks |

### 10. Performance Optimizations

#### Windows
- Direct GDI+ access for screenshots
- Low-level system hooks for input monitoring
- Immediate data transmission

#### MacOS
- Optimized screenshot capture with CGWindowListCreateImage
- Chunked data transmission to prevent timeouts
- Memory management with removeAll(keepingCapacity: true)
- Background processing with GCD

## Compatibility Notes

### API Compatibility
- Both versions send identical JSON structures for core events
- Response handling is consistent across platforms
- Error codes and status messages are standardized

### Data Format Compatibility
- Timestamps use different formats but represent the same data
- Browser history structures are compatible
- Key log formats are identical
- USB log formats differ but contain equivalent information

### Network Compatibility
- Both use HTTP POST requests
- Content-Type headers are consistent
- User-Agent strings follow the same pattern: `MonitorClient/1.0`

## Recommendations for Cross-Platform Development

1. **Standardize Intervals**: Consider aligning monitoring intervals between platforms
2. **Unify Data Structures**: Standardize USB log format across platforms
3. **Implement Chunking**: Add data chunking to Windows version for large datasets
4. **Enhance Error Handling**: Implement exponential backoff in Windows version
5. **Permission Management**: Add permission request dialogs to Windows version
6. **Logging Standardization**: Implement unified logging approach across platforms 