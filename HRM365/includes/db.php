<?php
$host = '127.0.0.1';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

if (!defined('APP_BASE_URL')) {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = rtrim(dirname($scriptName), '/');

    foreach (['/modules', '/api', '/includes'] as $segment) {
        $segmentPos = strpos($basePath, $segment);
        if ($segmentPos !== false) {
            $basePath = substr($basePath, 0, $segmentPos);
            break;
        }
    }

    define('APP_BASE_URL', $basePath === '/' ? '' : $basePath);
}

if (!function_exists('app_url')) {
    function app_url(string $path = ''): string
    {
        $base = APP_BASE_URL;
        $path = ltrim($path, '/');

        if ($base === '') {
            return '/' . $path;
        }

        return $path === '' ? $base : $base . '/' . $path;
    }
}

// 1. Connect to MySQL (without selecting a database)
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO("mysql:host=$host;charset=$charset", $user, $pass, $options);
    
    // 2. Create the database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS hrm365");
    $pdo->exec("USE hrm365");
    
    // 3. Initialize tables if they don't exist
    $schema = file_get_contents(__DIR__ . '/../database.sql');
    if ($schema !== false) {
        // Simple check to avoid running the full schema on every single request
        $stmt = $pdo->query("SHOW TABLES LIKE 'employees'");
        if ($stmt->rowCount() == 0) {
            // Explode by semicolon and execute, or just let PDO run the multi-query if supported
            // To ensure compatibility, we'll try to execute the raw SQL string
            try {
                $pdo->exec($schema);
            } catch (\PDOException $e) {
                // Ignore errors related to existing tables
            }
        }
    }

    $punchTable = $pdo->query("SHOW TABLES LIKE 'biometric_punches'");
    if ($punchTable->rowCount() > 0) {
        $logStatusColumn = $pdo->query("SHOW COLUMNS FROM biometric_punches LIKE 'log_status'");
        if ($logStatusColumn->rowCount() === 0) {
            $pdo->exec("ALTER TABLE biometric_punches ADD COLUMN log_status VARCHAR(20) NOT NULL DEFAULT 'Pending' AFTER punch_direction");
        }
    }

    $attendanceTable = $pdo->query("SHOW TABLES LIKE 'attendance_records'");
    if ($attendanceTable->rowCount() > 0) {
        $attendanceColumns = [
            'break_hours' => "ALTER TABLE attendance_records ADD COLUMN break_hours DECIMAL(5, 2) DEFAULT 0.00 AFTER total_hours",
            'is_absent' => "ALTER TABLE attendance_records ADD COLUMN is_absent BOOLEAN DEFAULT FALSE AFTER is_early_departure",
            'is_holiday' => "ALTER TABLE attendance_records ADD COLUMN is_holiday BOOLEAN DEFAULT FALSE AFTER is_absent",
            'is_weekend' => "ALTER TABLE attendance_records ADD COLUMN is_weekend BOOLEAN DEFAULT FALSE AFTER is_holiday",
            'notes' => "ALTER TABLE attendance_records ADD COLUMN notes TEXT AFTER status",
        ];

        foreach ($attendanceColumns as $column => $alterSql) {
            $columnCheck = $pdo->query("SHOW COLUMNS FROM attendance_records LIKE '{$column}'");
            if ($columnCheck->rowCount() === 0) {
                $pdo->exec($alterSql);
            }
        }

        $pdo->exec("ALTER TABLE attendance_records MODIFY status ENUM('Present', 'Absent', 'Half Day', 'Holiday', 'On Leave', 'Pending') DEFAULT 'Pending'");
    }

    $payrollTable = $pdo->query("SHOW TABLES LIKE 'payroll_records'");
    if ($payrollTable->rowCount() > 0) {
        $payrollColumns = [
            'overtime_hours' => "ALTER TABLE payroll_records ADD COLUMN overtime_hours DECIMAL(8, 2) DEFAULT 0.00 AFTER base_salary",
            'unpaid_days' => "ALTER TABLE payroll_records ADD COLUMN unpaid_days DECIMAL(8, 2) DEFAULT 0.00 AFTER deductions",
        ];

        foreach ($payrollColumns as $column => $alterSql) {
            $columnCheck = $pdo->query("SHOW COLUMNS FROM payroll_records LIKE '{$column}'");
            if ($columnCheck->rowCount() === 0) {
                $pdo->exec($alterSql);
            }
        }
    }

    $settingsTable = $pdo->query("SHOW TABLES LIKE 'system_settings'");
    if ($settingsTable->rowCount() > 0) {
        $pdo->exec("
            INSERT INTO system_settings (setting_key, setting_value)
            VALUES ('currency', 'LKR')
            ON DUPLICATE KEY UPDATE setting_value = IF(setting_value = '$', 'LKR', setting_value)
        ");
        $pdo->exec("
            INSERT INTO system_settings (setting_key, setting_value)
            VALUES ('timezone', 'Asia/Colombo')
            ON DUPLICATE KEY UPDATE setting_value = IF(setting_value = '' OR setting_value = 'Asia/Kolkata', 'Asia/Colombo', setting_value)
        ");
        $pdo->exec("
            INSERT INTO system_settings (setting_key, setting_value)
            VALUES ('holiday_country', 'LK')
            ON DUPLICATE KEY UPDATE setting_value = IF(setting_value = '', 'LK', setting_value)
        ");
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS attendance_regularizations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            attendance_record_id INT NOT NULL,
            date DATE NOT NULL,
            requested_clock_in DATETIME NOT NULL,
            requested_clock_out DATETIME NOT NULL,
            original_clock_in DATETIME NULL,
            original_clock_out DATETIME NULL,
            reason TEXT NOT NULL,
            status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
            approved_by INT NULL,
            approved_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            FOREIGN KEY (attendance_record_id) REFERENCES attendance_records(id) ON DELETE CASCADE,
            FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS meetings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            start_time DATETIME NOT NULL,
            end_time DATETIME NOT NULL,
            location VARCHAR(255),
            organizer_id INT NOT NULL,
            attendees TEXT,
            status ENUM('Scheduled', 'Cancelled', 'Completed') DEFAULT 'Scheduled',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (organizer_id) REFERENCES employees(id) ON DELETE CASCADE
        )
    ");

    $leaveApplicationsTable = $pdo->query("SHOW TABLES LIKE 'leave_applications'");
    if ($leaveApplicationsTable->rowCount() > 0) {
        $coveringEmployeeColumn = $pdo->query("SHOW COLUMNS FROM leave_applications LIKE 'covering_employee_id'");
        if ($coveringEmployeeColumn->rowCount() === 0) {
            $pdo->exec("ALTER TABLE leave_applications ADD COLUMN covering_employee_id INT NULL AFTER leave_type_id");
        }
    }

    $usersTable = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($usersTable->rowCount() > 0) {
        $employeeLoginIndex = $pdo->query("SHOW INDEX FROM users WHERE Key_name = 'unique_employee_login'");
        if ($employeeLoginIndex->rowCount() === 0) {
            $duplicateEmployeeLogins = $pdo->query("
                SELECT employee_id
                FROM users
                WHERE employee_id IS NOT NULL
                GROUP BY employee_id
                HAVING COUNT(*) > 1
                LIMIT 1
            ");
            if ($duplicateEmployeeLogins->rowCount() === 0) {
                $pdo->exec("ALTER TABLE users ADD UNIQUE KEY unique_employee_login (employee_id)");
            }
        }
    }
} catch (\PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>
