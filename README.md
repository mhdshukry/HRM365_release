# HRM365

<img width="959" height="500" alt="Image" src="https://github.com/user-attachments/assets/b7ce8a55-cd53-47e1-b837-063dc0363b11" />

HRM365 is a PHP/MySQL human resource management system designed for employee administration, attendance tracking, leave management, payroll support, calendars, meetings, reports, and ZKTeco biometric device integration.

## Features

- Dashboard with employee, leave, and attendance metrics
- Employee profiles, documents, bank details, branches, and user accounts
- Attendance policies, shifts, assignments, corrections, and regularizations
- Biometric punch intake from ZKTeco devices
- Leave types, leave policies, leave balances, applications, and approvals
- Holidays, calendar events, meetings, notifications, reports, and audit logs
- Payroll generation, payroll status updates, and payslip view

## Tech Stack

- PHP with PDO
- MySQL/MariaDB
- Apache/XAMPP
- JavaScript, CSS, Chart.js, Font Awesome
- Node.js middleware for ZKTeco device sync

## Project Structure

```text
HRM365_Release/
|-- HRM365/                         Main PHP application
|   |-- api/                        HTTP API endpoints
|   |-- css/                        Application styles
|   |-- includes/                   Shared database, auth, layout, and business logic
|   |-- js/                         Application JavaScript
|   |-- modules/                    Feature modules
|   |-- zkteco-middleware/          Node.js ZKTeco integration worker
|   |-- database.sql                Main database schema and seed data
|   |-- cli_sync.php                CLI attendance sync processor
|   |-- index.php                   Dashboard
|   `-- login.php                   Login page
|-- iclock/                         ZKTeco ADMS/iClock compatibility endpoints
|-- hrm365_database.sql             Database dump copy
`-- README.md
```

## Requirements

- XAMPP or equivalent Apache/PHP/MySQL stack
- PHP 8.x recommended
- MySQL or MariaDB
- Node.js 18+ for the ZKTeco middleware
- Composer is not required by the current codebase

## Installation

1. Place this repository inside your web root, for example:

   ```powershell
   D:\xampp\htdocs\HRM365_Release
   ```

2. Start Apache and MySQL from XAMPP.

3. Open the app in your browser:

   ```text
   http://localhost/HRM365_Release/HRM365/
   ```

4. The application connects to MySQL using the default local XAMPP credentials in `HRM365/includes/db.php`:

   ```text
   Host: 127.0.0.1
   User: root
   Password: empty
   Database: hrm365
   ```

5. On first load, the app creates the `hrm365` database if needed and initializes tables from `HRM365/database.sql`.

## Default Login

```text
Username: admin
Password: admin123
```

Change the default password before using the system in production or on a shared network.

## Manual Database Import

The app can initialize the database automatically, but you can also import the schema manually through phpMyAdmin or MySQL CLI.

```powershell
mysql -u root hrm365 < HRM365\database.sql
```

If the database does not exist yet:

```sql
CREATE DATABASE hrm365 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Then import the schema.

## ZKTeco Middleware

The Node.js middleware reads attendance records from a ZKTeco terminal and forwards them to the PHP intake API.

### Install Dependencies

```powershell
cd HRM365\zkteco-middleware
npm install
```

### Configure Device Connection

The middleware reads these optional environment variables:

```text
ZK_TERMINAL_IP      Default: 192.168.1.213
ZK_TERMINAL_PORT    Default: 4370
```

The middleware forwards punches to:

```text
http://localhost/HRM365/api/attendance/raw-punch.php
```

If your app is served from `http://localhost/HRM365_Release/HRM365/`, update `CORE_HRM_INGEST_URL` in `HRM365/zkteco-middleware/server.js` accordingly:

```text
http://localhost/HRM365_Release/HRM365/api/attendance/raw-punch.php
```

### Run Middleware

```powershell
npm start
```

## Attendance Sync

Raw biometric punches are stored in `biometric_punches`. To convert raw punches into attendance records, run:

```powershell
php HRM365\cli_sync.php
```

To sync one date:

```powershell
php HRM365\cli_sync.php 2026-06-23
```

For production use, schedule `cli_sync.php` with Windows Task Scheduler or cron.

## Biometric Punch API

Endpoint:

```text
POST /HRM365/api/attendance/raw-punch.php
Content-Type: application/json
```

Example payload:

```json
{
  "biometricUserId": "1001",
  "timestamp": "2026-06-23 08:30:00",
  "punchDirection": "CHECK_IN",
  "hardwareMechanism": "FINGERPRINT_BIOMETRIC",
  "terminalSn": "ZK_MIDDLEWARE"
}
```

Accepted `punchDirection` values are `CHECK_IN`, `CHECK_OUT`, and `UNKNOWN`.

## Main Modules

- `modules/dashboard` - dashboard and clock views
- `modules/employees` - employee management and documents
- `modules/users` - system user management
- `modules/branches` - branch setup
- `modules/shifts` - shift setup
- `modules/attendance_policies` - attendance rules
- `modules/attendance_records` - attendance records and corrections
- `modules/biometric_records` - biometric punch review, sync, and simulation
- `modules/regularizations` - attendance regularization workflow
- `modules/leave_types`, `modules/leave_policies`, `modules/leave_balances`, `modules/leave_applications` - leave management
- `modules/holidays` - holiday setup and public holiday import
- `modules/calendar` - calendar events
- `modules/meetings` - meeting management
- `modules/payroll` - payroll generation and payslips
- `modules/reports` - reports
- `modules/settings` - system settings and audit views

## Configuration Notes

- Database configuration is in `HRM365/includes/db.php`.
- Default currency/timezone settings are maintained in `system_settings`.
- The current default timezone is `Asia/Colombo`.
- Document uploads are handled through the employee document module.
- API intake writes development punch logs to `HRM365/punch_logs.txt`.

## Troubleshooting

- If login fails with database errors, confirm MySQL is running and `HRM365/database.sql` imported successfully.
- If pages redirect unexpectedly, clear browser sessions and log in again.
- If biometric punches arrive but attendance records do not update, run `php HRM365\cli_sync.php` and verify employees have matching `biometric_user_id` values.
- If the middleware cannot connect, confirm the ZKTeco device IP, port `4370`, firewall rules, and network reachability.
- If the middleware API call fails, confirm the app URL in `CORE_HRM_INGEST_URL` matches your local Apache path.

## Security Checklist

- Change the default admin password.
- Restrict access to database dumps and SQL files in production.
- Keep database credentials outside public source control for deployed environments.
- Protect uploaded employee documents with proper server permissions.
- Run the middleware only on trusted networks.
