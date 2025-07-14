# Monitor API Documentation

## Username Update API

### Endpoint
`POST /webapi.php`

### Parameters
- `action` (required): Must be set to `"update_username"`
- `username` (required): The new username (2-50 characters)

### Example Request
```bash
curl -X POST http://your-server/webapi.php \
  -d "action=update_username" \
  -d "username=john_doe"
```

### Response Format
```json
{
  "status": 1,
  "message": "Username updated successfully",
  "data": {
    "username": "john_doe"
  }
}
```

### Error Responses
```json
{
  "status": 0,
  "message": "Username is required"
}
```

```json
{
  "status": 0,
  "message": "Username must be between 2 and 50 characters"
}
```

```json
{
  "status": 0,
  "message": "Username already exists"
}
```

### Notes
- The API automatically detects the client's IP address
- Usernames must be unique across all monitors
- If no monitor record exists for the IP, one will be created automatically
- The username is stored in the `tbl_monitor_ips` table

## Tic Event API

### Endpoint
`POST /eventhandler.php`

### Parameters
- `Event` (required): Must be set to `"Tic"`
- `Version` (optional): Application version
- `MacAddress` (optional): MAC address of the client
- `Username` (optional): Current username of the client (2-50 characters)

### Example Request
```bash
curl -X POST http://your-server/eventhandler.php \
  -d "Event=Tic" \
  -d "Version=1.0" \
  -d "MacAddress=AA:BB:CC:DD:EE:FF" \
  -d "Username=john_doe"
```

### Response Format
```json
{
  "Status": "OK",
  "LastBrowserTic": 1640995200
}
```

### Notes
- The tic event updates the monitor status and last activity time
- If a username is provided, it will be updated in the database
- Username validation ensures uniqueness across all monitors
- The event also updates MAC address and app version if provided

## JSON Event API

### Endpoint
`POST /eventhandler.php`

### Content-Type
`application/json`

### Parameters
- `Event` (required): Event type (`BrowserHistory`, `KeyLog`, `USBLog`)
- `Version` (optional): Application version
- `MacAddress` (optional): MAC address of the client
- `Username` (optional): Current username of the client (2-50 characters)
- Event-specific data (varies by event type)

### Example Request
```bash
curl -X POST http://your-server/eventhandler.php \
  -H "Content-Type: application/json" \
  -d '{
    "Event": "BrowserHistory",
    "Version": "1.0",
    "MacAddress": "AA:BB:CC:DD:EE:FF",
    "Username": "john_doe",
    "BrowserHistories": [...]
  }'
```

### Notes
- All JSON events can include a username parameter
- Username will be updated automatically if provided
- Event-specific data is processed according to the event type

## Screenshot Upload API

### Endpoint
`POST /webapi.php`

### Parameters
- `fileToUpload` (required): The screenshot file to upload

### Example Request
```bash
curl -X POST http://your-server/webapi.php \
  -F "fileToUpload=@screenshot.png"
```

### Response Format
```json
{
  "Status": "OK",
  "Interval": 30
}
```

### Notes
- Screenshots are automatically organized by IP address and date/time
- Thumbnails are generated automatically
- The API returns the next monitoring interval in seconds 