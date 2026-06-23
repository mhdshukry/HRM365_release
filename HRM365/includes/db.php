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

    $attendancePoliciesTable = $pdo->query("SHOW TABLES LIKE 'attendance_policies'");
    if ($attendancePoliciesTable->rowCount() > 0) {
        $pdo->exec("
            UPDATE attendance_policies
            SET overtime_rate_per_hour = 1.50
            WHERE name IN ('Flexible Remote Policy', 'Strict Office Policy')
              AND overtime_rate_per_hour < 1.50
        ");
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

    $leaveTypesTable = $pdo->query("SHOW TABLES LIKE 'leave_types'");
    if ($leaveTypesTable->rowCount() > 0) {
        $pdo->exec("
            INSERT INTO leave_types (id, name, description, max_days_per_year, is_paid, color, status)
            VALUES
                (4, 'Unpaid Leave', 'Time off without pay.', 365, 0, '#6b7280', 'Active'),
                (5, 'Short Leave', 'Short leave permission. One request consumes 0.25 day.', 6, 1, '#06b6d4', 'Active'),
                (6, 'Casual Leave', 'Paid casual leave for personal or urgent needs. Supports full-day and half-day applications.', 7, 1, '#f59e0b', 'Active')
            ON DUPLICATE KEY UPDATE
                description = VALUES(description),
                max_days_per_year = VALUES(max_days_per_year),
                is_paid = VALUES(is_paid),
                color = VALUES(color),
                status = IF(status = 'Inactive', status, VALUES(status))
        ");
    }

    $leavePoliciesTable = $pdo->query("SHOW TABLES LIKE 'leave_policies'");
    if ($leavePoliciesTable->rowCount() > 0) {
        $pdo->exec("ALTER TABLE leave_policies MODIFY min_days_per_application DECIMAL(5, 2) DEFAULT 1.00");
        $pdo->exec("ALTER TABLE leave_policies MODIFY max_days_per_application DECIMAL(5, 2) DEFAULT 365.00");
        $pdo->exec("
            INSERT INTO leave_policies
                (name, description, leave_type_id, accrual_type, accrual_rate, carry_forward_limit, min_days_per_application, max_days_per_application, status)
            SELECT 'Standard Annual Policy', 'Base annual leave rules. Supports full-day and half-day applications.', 1, 'Yearly', 14.00, 5, 0.50, 14.00, 'Active'
            WHERE NOT EXISTS (SELECT 1 FROM leave_policies WHERE name = 'Standard Annual Policy' LIMIT 1)
        ");
        $pdo->exec("
            INSERT INTO leave_policies
                (name, description, leave_type_id, accrual_type, accrual_rate, carry_forward_limit, min_days_per_application, max_days_per_application, status)
            SELECT 'Standard Casual Policy', 'Base casual leave rules. Supports full-day and half-day applications.', 6, 'Yearly', 7.00, 0, 0.50, 7.00, 'Active'
            WHERE NOT EXISTS (SELECT 1 FROM leave_policies WHERE name = 'Standard Casual Policy' LIMIT 1)
        ");
        $pdo->exec("
            INSERT INTO leave_policies
                (name, description, leave_type_id, accrual_type, accrual_rate, carry_forward_limit, min_days_per_application, max_days_per_application, status)
            SELECT 'Standard Short Leave Policy', 'Short leave allowance. Each short leave application consumes 0.25 day.', 5, 'Monthly', 0.50, 0, 0.25, 0.25, 'Active'
            WHERE NOT EXISTS (SELECT 1 FROM leave_policies WHERE name = 'Standard Short Leave Policy' LIMIT 1)
        ");
        $pdo->exec("
            UPDATE leave_policies
            SET description = 'Short leave allowance. Each short leave application consumes 0.25 day.',
                accrual_type = 'Monthly',
                accrual_rate = 0.50,
                min_days_per_application = 0.25,
                max_days_per_application = 0.25
            WHERE leave_type_id = 5
        ");
    }
} catch (\PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>
