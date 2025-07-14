# Monitor UI - Web Interface

A simple CodeIgniter-like PHP web interface for the monitoring system with authentication and real-time data viewing capabilities.

## Features

- **User Authentication**: Login system with admin and user roles
- **User Management**: Admin can create, edit, and delete users
- **IP-Username Mapping**: Manage monitor IP addresses and associate them with usernames
- **Real-time Screenshots**: View latest screenshots from all monitors with auto-refresh every 30 seconds
- **Log Management**: Browse browser history, key logs, and USB logs for each monitor
- **Responsive Design**: Modern Bootstrap-based UI that works on desktop and mobile

## Requirements

- PHP 7.4 or higher
- MySQL/MariaDB
- Web server (Apache/Nginx)
- PDO extension for PHP
- GD extension for image processing (optional)

## Installation

### 1. Database Setup

First, ensure your MySQL database is running and create the required tables:

```sql
-- Run the setup script
mysql -u root -p < setup_database.sql
```

Or manually execute the SQL commands in `setup_database.sql`.

### 2. Configuration

Edit the database configuration in `app/config/config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'acc_monitor');
define('DB_NAME', 'monitoring_db');
```

### 3. File Permissions

Ensure the web server has read access to the data directories:

```bash
chmod -R 755 ../data/
```

### 4. Web Server Configuration

#### Apache (.htaccess)
Create a `.htaccess` file in the root directory:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php/$1 [L]
```

#### Nginx
Add this to your nginx configuration:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

## Default Login Credentials

- **Admin User**:
  - Username: `admin`
  - Password: `admin123`
  - Role: Admin (full access)

- **Regular User**:
  - Username: `user`
  - Password: `user123`
  - Role: User (limited access)

## Usage

### Dashboard
- Overview of system statistics
- Recent monitor activity
- Quick access to all features

### User Management (Admin Only)
- Create new users
- Edit existing users
- Delete users
- Assign roles (admin/user)

### IP Mapping
- Add new monitor IP addresses
- Associate IPs with usernames
- Edit monitor information
- View monitor status

### View Data

#### Screenshots
- Real-time screenshots from all monitors
- Auto-refresh every 30 seconds
- Click to view full-size images
- Toggle auto-refresh on/off

#### Logs
- Browse logs by monitor
- View browser history, key logs, and USB logs
- Click to view log file contents
- File size and modification time display

## File Structure

```
html/
├── app/
│   ├── config/
│   │   ├── config.php          # Main configuration
│   │   └── database.php        # Database connection class
│   ├── controllers/
│   │   ├── Base.php            # Base controller
│   │   ├── Auth.php            # Authentication controller
│   │   ├── Dashboard.php       # Dashboard controller
│   │   ├── Users.php           # User management
│   │   ├── Monitors.php        # IP mapping management
│   │   └── View.php            # Data viewing controller
│   └── views/
│       ├── layouts/
│       │   └── main.php        # Main layout template
│       ├── auth/               # Authentication views
│       ├── dashboard/          # Dashboard views
│       ├── users/              # User management views
│       ├── monitors/           # IP mapping views
│       └── view/               # Data viewing views
├── public/                     # Public assets
├── index.php                   # Main entry point
├── setup_database.sql          # Database setup script
└── README.md                   # This file
```

## API Endpoints

### Authentication
- `POST /auth/login` - User login
- `GET /auth/logout` - User logout
- `POST /auth/register` - Create new user (admin only)

### Dashboard
- `GET /dashboard` - Main dashboard

### User Management
- `GET /users` - List all users
- `GET /users/create` - Create user form
- `POST /users/create` - Create new user
- `GET /users/edit/{id}` - Edit user form
- `POST /users/edit/{id}` - Update user
- `GET /users/delete/{id}` - Delete user

### Monitor Management
- `GET /monitors` - List all monitors
- `GET /monitors/create` - Create monitor form
- `POST /monitors/create` - Create new monitor
- `GET /monitors/edit/{id}` - Edit monitor form
- `POST /monitors/edit/{id}` - Update monitor
- `GET /monitors/delete/{id}` - Delete monitor

### Data Viewing
- `GET /view` - Main view page
- `GET /view/screenshots` - Screenshots page
- `GET /view/logs` - Logs overview
- `GET /view/logs/{id}` - Specific monitor logs
- `GET /view/api_screenshots` - Screenshots API (JSON)
- `GET /view/api_logs/{id}` - Logs API (JSON)

## Security Features

- Password hashing using PHP's `password_hash()`
- Session-based authentication
- Role-based access control
- SQL injection prevention with prepared statements
- XSS protection with `htmlspecialchars()`

## Customization

### Styling
The application uses Bootstrap 5. You can customize the appearance by:
- Modifying the CSS in `app/views/layouts/main.php`
- Adding custom CSS files to the `public/css/` directory
- Overriding Bootstrap classes

### Configuration
Edit `app/config/config.php` to modify:
- Database settings
- Application name and version
- File paths
- Session timeout

### Adding New Features
1. Create a new controller in `app/controllers/`
2. Extend the `Base` controller
3. Create corresponding views in `app/views/`
4. Add navigation links in `app/views/layouts/main.php`

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Check database credentials in `app/config/config.php`
   - Ensure MySQL service is running
   - Verify database exists

2. **Permission Denied**
   - Check file permissions on data directories
   - Ensure web server can read/write to required directories

3. **404 Errors**
   - Check web server configuration (Apache .htaccess or Nginx config)
   - Verify URL rewriting is enabled

4. **Images Not Loading**
   - Check file paths in configuration
   - Verify image files exist in data directories
   - Check web server permissions

### Debug Mode
To enable debug mode, add this to `app/config/config.php`:

```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## Support

For issues and questions:
1. Check the troubleshooting section above
2. Verify all requirements are met
3. Check web server error logs
4. Ensure database setup is complete

## License

This project is part of the monitoring system and follows the same license terms. 