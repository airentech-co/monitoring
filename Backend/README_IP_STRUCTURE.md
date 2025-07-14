# IP-Based Data Structure Architecture

This document describes the updated data directory architecture that separates log data by IP addresses instead of merging all clients' data into single files.

## Overview

The backend now organizes all monitoring data (screenshots, thumbnails, browser history, key logs, and USB logs) by IP address, making it easier to manage and analyze data for individual clients.

## New Directory Structure

### Before (Flat Structure)
```
data/
├── screens/
│   └── 2025-06-29/
│       └── 16.00/
│           └── screenshot.jpg
├── thumbnails/
│   └── 2025-06-29/
│       └── 16.00/
│           └── screenshot.jpg
└── logs/
    └── 2025-06-29/
        └── 23.00/
            ├── browser_history.txt
            ├── key_logs.txt
            └── usb_logs.txt
```

### After (IP-Based Structure)
```
data/
├── screens/
│   └── ip_192.168.1.100/    # IP Address (colons/dashes replaced with underscores)
│       └── 2025-06-29/
│           └── 16.00/
│               └── screenshot.jpg
│   └── ip_192.168.1.101/    # Another IP Address
│       └── 2025-06-29/
│           └── 16.00/
│               └── screenshot.jpg
├── thumbnails/
│   └── ip_192.168.1.100/
│       └── 2025-06-29/
│           └── 16.00/
│               └── screenshot.jpg
└── logs/
    └── ip_192.168.1.100/
        └── 2025-06-29/
            └── 23.00/
                ├── browser_history.txt
                ├── key_logs.txt
                └── usb_logs.txt
```

## Key Changes

### 1. Configuration Updates (`config.inc.php`)
- Added `getIPBasedPaths()` function to generate IP-based directory paths
- Uses IP address as primary identifier, falls back to IP ID, then MAC address

### 2. Screenshot Upload (`webapi.php`)
- Updated `uploadCacheScreen()` function to use IP-based directories
- Screenshots and thumbnails are now stored in IP-specific subdirectories

### 3. Log Processing (`eventhandler.php`)
- Updated all log functions (`addBrowserHistory()`, `addKeyLogs()`, `addUSBLogs()`) to use IP-based directories
- Each IP address's logs are stored separately

## Migration Tools

### 1. Migration Script (`migrate_to_ip_structure.php`)

This script helps migrate existing data from the old flat structure to the new IP-based structure.

#### Usage:
```bash
# Dry run to see what would be migrated
php migrate_to_ip_structure.php --dry-run --ip-id=1

# Actually migrate data for IP ID 1
php migrate_to_ip_structure.php --ip-id=1

# Migrate data for specific IP address
php migrate_to_ip_structure.php --ip-address=192.168.1.100
```

#### Features:
- Safe migration with dry-run option
- Creates necessary directories automatically
- Preserves existing file structure within IP directories
- Handles all data types (screenshots, thumbnails, logs)

### 2. IP Data Manager (`ip_data_manager.php`)

This utility script helps manage and view the new IP-based data structure.

#### Usage:
```bash
# List all IP directories with size information
php ip_data_manager.php --list

# Show detailed information for specific IP ID
php ip_data_manager.php --ip-id=1

# Show detailed information for specific IP address
php ip_data_manager.php --ip-address=192.168.1.100

# Clean up empty IP directories
php ip_data_manager.php --cleanup

# Show help
php ip_data_manager.php --help
```

#### Features:
- Overview of all IP directories with size information
- Detailed view of specific IP data including recent files
- Cleanup utility to remove empty directories
- File size formatting and statistics

## Implementation Details

### Client Identification
The system uses a hierarchical approach for client identification:
1. **Primary**: IP address from `$_SERVER['REMOTE_ADDR']`
2. **Fallback**: IP ID from database (if available)
3. **Secondary Fallback**: MAC address from client request (if available)
4. **Default**: "unknown_client" if none are available

### Directory Naming Convention
- IP Address: `ip_{address}` (e.g., `ip_192.168.1.100`, `ip_10.0.0.5`)
- IP ID: `ip_{id}` (e.g., `ip_1`, `ip_2`) - fallback only
- MAC Address: `mac_{address}` (e.g., `mac_AA_BB_CC_DD_EE_FF`) - secondary fallback only
- Unknown: `unknown_client`

### Backward Compatibility
The new structure maintains the same internal organization:
- Date-based directories (`YYYY-MM-DD`)
- Time-based subdirectories (`HH.00` or `HH.30`)
- Same file naming conventions

## Benefits

1. **Data Isolation**: Each client's data is completely separated by unique IP address
2. **Easier Management**: Clear organization by IP address/client
3. **Better Performance**: Reduced file contention when multiple clients upload simultaneously
4. **Simplified Analysis**: Easier to analyze data for specific clients
5. **Scalability**: Better support for multiple concurrent clients
6. **Device Tracking**: IP addresses provide unique device identification regardless of MAC changes

## Migration Process

1. **Backup**: Always backup your existing data before migration
2. **Test**: Run migration with `--dry-run` flag first
3. **Migrate**: Execute migration for each IP/client
4. **Verify**: Use IP Data Manager to verify the new structure
5. **Cleanup**: Remove old directories after verification

## Example Workflow

```bash
# 1. Backup existing data
cp -r data data_backup_$(date +%Y%m%d)

# 2. Test migration for IP address 192.168.1.100
php migrate_to_ip_structure.php --dry-run --ip-address=192.168.1.100

# 3. Perform actual migration
php migrate_to_ip_structure.php --ip-address=192.168.1.100

# 4. Verify the new structure
php ip_data_manager.php --list
php ip_data_manager.php --ip-address=192.168.1.100

# 5. Repeat for other IP addresses as needed
```

## Troubleshooting

### Common Issues

1. **Permission Errors**: Ensure the web server has write permissions to the data directories
2. **Missing IP Information**: Check that `getClientInfo()` function returns proper IP data
3. **Directory Creation Failures**: Verify disk space and permissions

### Debugging

Use the IP Data Manager to inspect the current state:
```bash
php ip_data_manager.php --list
```

Check specific IP address directories:
```bash
php ip_data_manager.php --ip-address=192.168.1.100
```

## Future Enhancements

- Database integration for IP management
- Automatic cleanup of old data
- Data compression for long-term storage
- Web interface for data management
- Export functionality for specific IP ranges 