-- HRM365 Database Schema

CREATE DATABASE IF NOT EXISTS hrm365;
USE hrm365;

-- Branches Table
CREATE TABLE IF NOT EXISTS branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    address TEXT,
    phone VARCHAR(20),
    email VARCHAR(100),
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Shifts Table (Attendance Mechanics)
CREATE TABLE IF NOT EXISTS shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    grace_period INT DEFAULT 0,
    is_night_shift BOOLEAN DEFAULT FALSE,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO shifts (name, description, start_time, end_time, grace_period) VALUES 
('Standard Day Shift', 'Regular 9-to-5 working hours.', '09:00:00', '17:00:00', 15);

-- Attendance Policies Table
CREATE TABLE IF NOT EXISTS attendance_policies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    late_arrival_grace INT DEFAULT 0,
    early_departure_grace INT DEFAULT 0,
    overtime_rate_per_hour DECIMAL(10, 2) DEFAULT 0.00,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO attendance_policies (name, description, late_arrival_grace, early_departure_grace, overtime_rate_per_hour) VALUES 
('Strict Office Policy', 'Zero tolerance policy with standard overtime.', 0, 0, 1.50),
('Flexible Remote Policy', 'Allows 15 min buffer on both ends.', 15, 15, 1.50);

-- Biometric Punch Logs Table
CREATE TABLE IF NOT EXISTS biometric_punches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    biometric_user_id VARCHAR(50) NOT NULL,
    punch_time DATETIME NOT NULL,
    punch_direction VARCHAR(20) DEFAULT 'UNKNOWN',
    log_status VARCHAR(20) NOT NULL DEFAULT 'Pending',
    terminal_sn VARCHAR(100),
    is_synced BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY dedup (biometric_user_id, punch_time)
);

-- Employees Table
CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_code VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    date_of_birth DATE,
    gender ENUM('Male', 'Female', 'Other'),
    address TEXT,
    branch_id INT NULL,
    department VARCHAR(50),
    designation VARCHAR(50),
    hire_date DATE NOT NULL,
    employment_type ENUM('Full-time', 'Part-time', 'Contract', 'Intern') DEFAULT 'Full-time',
    base_salary DECIMAL(10, 2) DEFAULT 0.00,
    status ENUM('Active', 'On Leave', 'Terminated') DEFAULT 'Active',
    biometric_user_id VARCHAR(50) UNIQUE NULL,
    profile_photo VARCHAR(255),
    shift_id INT NULL,
    attendance_policy_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
    FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE SET NULL,
    FOREIGN KEY (attendance_policy_id) REFERENCES attendance_policies(id) ON DELETE SET NULL
);

-- Attendance Records Table (Daily Timesheet Ledger)
CREATE TABLE IF NOT EXISTS attendance_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    shift_id INT NULL,
    attendance_policy_id INT NULL,
    date DATE NOT NULL,
    clock_in DATETIME NULL,
    clock_out DATETIME NULL,
    total_hours DECIMAL(5, 2) DEFAULT 0.00,
    break_hours DECIMAL(5, 2) DEFAULT 0.00,
    overtime_hours DECIMAL(5, 2) DEFAULT 0.00,
    overtime_amount DECIMAL(10, 2) DEFAULT 0.00,
    is_late BOOLEAN DEFAULT FALSE,
    is_early_departure BOOLEAN DEFAULT FALSE,
    is_absent BOOLEAN DEFAULT FALSE,
    is_holiday BOOLEAN DEFAULT FALSE,
    is_weekend BOOLEAN DEFAULT FALSE,
    status ENUM('Present', 'Absent', 'Half Day', 'Holiday', 'On Leave', 'Pending') DEFAULT 'Pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE SET NULL,
    FOREIGN KEY (attendance_policy_id) REFERENCES attendance_policies(id) ON DELETE SET NULL,
    UNIQUE KEY emp_date (employee_id, date)
);

-- Employee Bank Details
CREATE TABLE IF NOT EXISTS employee_bank_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    bank_name VARCHAR(100),
    account_name VARCHAR(100),
    account_number VARCHAR(50),
    swift_code VARCHAR(20),
    bank_branch VARCHAR(100),
    tax_id VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- Users Table (Authentication)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    role ENUM('admin', 'HR', 'manager', 'employee') DEFAULT 'employee',
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    language VARCHAR(20) DEFAULT 'English',
    department VARCHAR(50),
    employee_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE KEY unique_employee_login (employee_id)
);

-- Insert default admin user (password is 'admin123' hashed)
INSERT IGNORE INTO users (id, username, password, role) VALUES 
(1, 'admin', '$2y$10$8.4P71fP3yA/Y2hW2s1HfeY8v.aH18HHTZkIxz4E91j.M3F.jF0yG', 'admin');

-- Audit Logs Table
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Attendance Regularization Requests
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
);

-- Documents Vault
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Meetings Table
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
);

-- Leave Types Configuration Table
CREATE TABLE IF NOT EXISTS leave_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    max_days_per_year INT DEFAULT 0,
    is_paid BOOLEAN DEFAULT TRUE,
    color VARCHAR(20) DEFAULT '#3b82f6',
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO leave_types (id, name, description, max_days_per_year, is_paid, color) VALUES 
(1, 'Annual Leave', 'Standard paid time off for vacations.', 14, TRUE, '#3b82f6'),
(2, 'Sick Leave', 'Paid time off for medical reasons.', 7, TRUE, '#ef4444'),
(3, 'Maternity Leave', 'Statutory maternity leave.', 90, TRUE, '#ec4899'),
(4, 'Unpaid Leave', 'Time off without pay.', 365, FALSE, '#6b7280'),
(5, 'Short Leave', 'Short leave permission. One request consumes 0.25 day.', 6, TRUE, '#06b6d4'),
(6, 'Casual Leave', 'Paid casual leave for personal or urgent needs. Supports full-day and half-day applications.', 7, TRUE, '#f59e0b');

-- Leave Applications Table
CREATE TABLE IF NOT EXISTS leave_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    leave_type_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    covering_employee_id INT NULL,
    total_days DECIMAL(5, 2) NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    approved_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE,
    FOREIGN KEY (covering_employee_id) REFERENCES employees(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Payroll Records Table
CREATE TABLE IF NOT EXISTS payroll_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    payroll_month VARCHAR(10) NOT NULL, -- Format: YYYY-MM
    base_salary DECIMAL(10, 2) DEFAULT 0.00,
    overtime_hours DECIMAL(8, 2) DEFAULT 0.00,
    overtime_amount DECIMAL(10, 2) DEFAULT 0.00,
    deductions DECIMAL(10, 2) DEFAULT 0.00,
    unpaid_days DECIMAL(8, 2) DEFAULT 0.00,
    net_salary DECIMAL(10, 2) DEFAULT 0.00,
    status ENUM('Draft', 'Finalized', 'Paid') DEFAULT 'Draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE KEY emp_month (employee_id, payroll_month)
);

-- Leave Policies Table
CREATE TABLE IF NOT EXISTS leave_policies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    leave_type_id INT NOT NULL,
    accrual_type ENUM('Monthly', 'Quarterly', 'Yearly', 'Fixed allocation') NOT NULL,
    accrual_rate DECIMAL(5, 2) NOT NULL,
    carry_forward_limit INT DEFAULT 0,
    min_days_per_application DECIMAL(5, 2) DEFAULT 1.00,
    max_days_per_application DECIMAL(5, 2) DEFAULT 365.00,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE
);

INSERT INTO leave_policies (name, description, leave_type_id, accrual_type, accrual_rate, carry_forward_limit, min_days_per_application, max_days_per_application) VALUES
('Standard Annual Policy', 'Base annual leave rules. Supports full-day and half-day applications.', 1, 'Yearly', 14.00, 5, 0.50, 14.00),
('Standard Casual Policy', 'Base casual leave rules. Supports full-day and half-day applications.', 6, 'Yearly', 7.00, 0, 0.50, 7.00),
('Standard Sick Policy', 'Base sick leave rules. Supports full-day and half-day applications.', 2, 'Yearly', 7.00, 0, 0.50, 7.00),
('Standard Short Leave Policy', 'Short leave allowance. Each short leave application consumes 0.25 day.', 5, 'Monthly', 0.50, 0, 0.25, 0.25);

-- Leave Balances Ledger Table
CREATE TABLE IF NOT EXISTS leave_balances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    leave_type_id INT NOT NULL,
    leave_policy_id INT NULL,
    year INT NOT NULL,
    allocated_days DECIMAL(5, 2) DEFAULT 0.00,
    used_days DECIMAL(5, 2) DEFAULT 0.00,
    carried_forward DECIMAL(5, 2) DEFAULT 0.00,
    manual_adjustment DECIMAL(5, 2) DEFAULT 0.00,
    adjustment_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE,
    FOREIGN KEY (leave_policy_id) REFERENCES leave_policies(id) ON DELETE SET NULL,
    UNIQUE KEY emp_type_year (employee_id, leave_type_id, year)
);

-- Leave Requests Table
CREATE TABLE IF NOT EXISTS leave_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    leave_type_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT,
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE
);

-- Payroll Table
CREATE TABLE IF NOT EXISTS payroll (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    month VARCHAR(20) NOT NULL,
    base_salary DECIMAL(10, 2) NOT NULL,
    allowances DECIMAL(10, 2) DEFAULT 0.00,
    deductions DECIMAL(10, 2) DEFAULT 0.00,
    net_salary DECIMAL(10, 2) NOT NULL,
    status ENUM('Draft', 'Processed', 'Paid') DEFAULT 'Draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);
-- System Settings Table
CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES 
('company_name', 'HRM365 Enterprise'),
('timezone', 'Asia/Colombo'),
('holiday_country', 'LK'),
('currency', 'LKR'),
('grace_period_mins', '15'),
('late_deduction_amount', '50.00');

-- Holidays Table
CREATE TABLE IF NOT EXISTS holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    category ENUM('National', 'Religious', 'Company-specific', 'Other') DEFAULT 'National',
    description TEXT,
    is_recurring BOOLEAN DEFAULT FALSE,
    is_paid BOOLEAN DEFAULT TRUE,
    is_half_day BOOLEAN DEFAULT FALSE,
    applies_to_all_branches BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Holiday to Branch Mapping (Many-to-Many)
CREATE TABLE IF NOT EXISTS holiday_branches (
    holiday_id INT NOT NULL,
    branch_id INT NOT NULL,
    PRIMARY KEY (holiday_id, branch_id),
    FOREIGN KEY (holiday_id) REFERENCES holidays(id) ON DELETE CASCADE,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
);

INSERT INTO holidays (name, start_date, end_date, category, is_recurring, is_paid, applies_to_all_branches) VALUES
('New Year Day', '2026-01-01', '2026-01-01', 'National', TRUE, TRUE, TRUE),
('Company Annual Retreat', '2026-06-15', '2026-06-17', 'Company-specific', FALSE, TRUE, TRUE);
