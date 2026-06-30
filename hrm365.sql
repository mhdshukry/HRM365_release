-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 30, 2026 at 07:31 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hrm365`
--

-- --------------------------------------------------------

--
-- Table structure for table `advance_payments`
--

CREATE TABLE `advance_payments` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `deduction_month` varchar(10) NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('Pending','Approved','Paid','Cancelled') DEFAULT 'Paid',
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `paid_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_policies`
--

CREATE TABLE `attendance_policies` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `late_arrival_grace` int(11) DEFAULT 0,
  `early_departure_grace` int(11) DEFAULT 0,
  `overtime_rate_per_hour` decimal(10,2) DEFAULT 0.00,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance_policies`
--

INSERT INTO `attendance_policies` (`id`, `name`, `description`, `late_arrival_grace`, `early_departure_grace`, `overtime_rate_per_hour`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Strict Office Policy', 'Zero tolerance policy with standard overtime.', 0, 0, 2.00, 'Active', '2026-06-02 14:26:50', '2026-06-23 06:59:30'),
(2, 'Flexible Remote Policy', 'Allows 15 min buffer on both ends.', 15, 15, 1.50, 'Active', '2026-06-02 14:26:50', '2026-06-23 06:50:54');

-- --------------------------------------------------------

--
-- Table structure for table `attendance_records`
--

CREATE TABLE `attendance_records` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `shift_id` int(11) DEFAULT NULL,
  `attendance_policy_id` int(11) DEFAULT NULL,
  `date` date NOT NULL,
  `clock_in` datetime DEFAULT NULL,
  `clock_out` datetime DEFAULT NULL,
  `total_hours` decimal(5,2) DEFAULT 0.00,
  `break_hours` decimal(5,2) DEFAULT 0.00,
  `overtime_hours` decimal(5,2) DEFAULT 0.00,
  `overtime_amount` decimal(10,2) DEFAULT 0.00,
  `is_late` tinyint(4) DEFAULT 0,
  `is_early_departure` tinyint(4) DEFAULT 0,
  `is_absent` tinyint(1) DEFAULT 0,
  `is_holiday` tinyint(1) DEFAULT 0,
  `is_weekend` tinyint(1) DEFAULT 0,
  `status` enum('Present','Absent','Half Day','Holiday','On Leave','Pending') DEFAULT 'Pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_regularizations`
--

CREATE TABLE `attendance_regularizations` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `attendance_record_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `requested_clock_in` datetime NOT NULL,
  `requested_clock_out` datetime NOT NULL,
  `original_clock_in` datetime DEFAULT NULL,
  `original_clock_out` datetime DEFAULT NULL,
  `reason` text NOT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `details`, `ip_address`, `created_at`) VALUES
(1, 1, 'SYSTEM_PEOPLE_DATA_RESET', 'Cleared employee, user, attendance, leave, payroll, meeting, biometric, document, and audit data; recreated single admin user.', '::1', '2026-06-30 05:26:46'),
(2, 1, 'LOGIN_SUCCESS', 'User authenticated successfully via Web Portal.', '::1', '2026-06-30 05:26:55'),
(3, 1, 'EMPLOYEE_CREATED', 'EMPLOYEE_CREATED: EMP-10003 (Sairathan Krishnatheva)', '::1', '2026-06-30 05:29:00'),
(4, 1, 'USER_CREDENTIAL_SMS_SENT', 'Credential SMS for sairathan2@gmail.com: Your message was successfully delivered', '::1', '2026-06-30 05:29:42'),
(5, 1, 'USER_UPDATED', 'Updated system user: sairathan2@gmail.com', '::1', '2026-06-30 05:29:42'),
(6, 1, 'PAYROLL_GENERATED', 'Generated payroll for 2026-06', '::1', '2026-06-30 05:30:16');

-- --------------------------------------------------------

--
-- Table structure for table `biometric_punches`
--

CREATE TABLE `biometric_punches` (
  `id` int(11) NOT NULL,
  `biometric_user_id` varchar(50) NOT NULL,
  `punch_time` datetime NOT NULL,
  `punch_direction` varchar(20) DEFAULT 'UNKNOWN',
  `log_status` varchar(20) NOT NULL DEFAULT 'Pending',
  `terminal_sn` varchar(100) DEFAULT NULL,
  `is_synced` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `biometric_terminal_sn` varchar(100) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `employee_code` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `nic_number` varchar(30) DEFAULT NULL,
  `department` varchar(50) DEFAULT NULL,
  `designation` varchar(50) DEFAULT NULL,
  `status` enum('Active','On Leave','Resigned','Terminated') DEFAULT 'Active',
  `resignation_termination_date` date DEFAULT NULL,
  `biometric_user_id` varchar(50) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `address` text DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `employment_type` enum('Full-time','Part-time','Contract') DEFAULT NULL,
  `base_salary` decimal(10,2) DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `shift_id` int(11) DEFAULT NULL,
  `attendance_policy_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `employee_code`, `first_name`, `last_name`, `email`, `phone`, `nic_number`, `department`, `designation`, `status`, `resignation_termination_date`, `biometric_user_id`, `hire_date`, `created_at`, `updated_at`, `date_of_birth`, `gender`, `address`, `branch_id`, `employment_type`, `base_salary`, `profile_photo`, `shift_id`, `attendance_policy_id`) VALUES
(1, 'EMP-10003', 'Sairathan', 'Krishnatheva', 'sairathan2@gmail.com', '0755610209', '953360209V', 'Finance', 'CEO', 'Active', NULL, 'EMP-10003', '2018-11-30', '2026-06-30 05:29:00', '2026-06-30 05:29:00', '1995-03-26', 'Male', '', NULL, 'Full-time', 0.00, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `employee_bank_details`
--

CREATE TABLE `employee_bank_details` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `account_name` varchar(100) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `swift_code` varchar(20) DEFAULT NULL,
  `bank_branch` varchar(100) DEFAULT NULL,
  `tax_id` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_bank_details`
--

INSERT INTO `employee_bank_details` (`id`, `employee_id`, `bank_name`, `account_name`, `account_number`, `swift_code`, `bank_branch`, `tax_id`, `created_at`, `updated_at`) VALUES
(1, 1, '', '', '', '', '', '', '2026-06-30 05:29:00', '2026-06-30 05:29:00');

-- --------------------------------------------------------

--
-- Table structure for table `employee_shift_overrides`
--

CREATE TABLE `employee_shift_overrides` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `weekday` tinyint(4) NOT NULL,
  `shift_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `holidays`
--

CREATE TABLE `holidays` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `category` enum('National','Religious','Company-specific','Other') DEFAULT 'National',
  `description` text DEFAULT NULL,
  `is_recurring` tinyint(1) DEFAULT 0,
  `is_paid` tinyint(1) DEFAULT 1,
  `is_half_day` tinyint(1) DEFAULT 0,
  `applies_to_all_branches` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `holidays`
--

INSERT INTO `holidays` (`id`, `name`, `start_date`, `end_date`, `category`, `description`, `is_recurring`, `is_paid`, `is_half_day`, `applies_to_all_branches`, `created_at`) VALUES
(1, 'Esala Full Moon Poya Day', '2026-07-29', '2026-07-29', 'Religious', 'Imported public holiday for LK.', 0, 1, 0, 1, '2026-06-18 10:39:17'),
(2, 'Duruthu Full Moon Poya Day', '2026-01-02', '2026-01-02', 'Religious', 'Imported public holiday for LK.', 0, 1, 0, 1, '2026-06-18 10:43:16'),
(3, 'Thai Pongal', '2026-01-14', '2026-01-14', 'Religious', 'Imported public holiday for LK.', 0, 1, 0, 1, '2026-06-18 10:43:16'),
(4, 'Navam Full Moon Poya Day', '2026-02-01', '2026-02-01', 'Religious', 'Imported public holiday for LK.', 0, 1, 0, 1, '2026-06-18 10:43:16'),
(5, 'Independence Day', '2026-02-04', '2026-02-04', 'National', 'Imported public holiday for LK.', 0, 1, 0, 1, '2026-06-18 10:43:16'),
(6, 'Medin Full Moon Poya Day', '2026-03-03', '2026-03-03', 'Religious', 'Imported public holiday for LK.', 0, 1, 0, 1, '2026-06-18 10:43:16'),
(7, 'Maha Shivaratri Day', '2026-03-15', '2026-03-15', 'Religious', 'Imported public holiday for LK.', 0, 1, 0, 1, '2026-06-18 10:43:16'),
(8, 'Eid al-Fitr', '2026-03-21', '2026-03-21', 'Religious', 'Imported public holiday for LK.', 0, 1, 0, 1, '2026-06-18 10:43:16'),
(9, 'Bak Full Moon Poya Day', '2026-04-01', '2026-04-01', 'Religious', 'Imported public holiday for LK.', 0, 1, 0, 1, '2026-06-18 10:43:16'),
(10, 'Good Friday', '2026-04-03', '2026-04-03', 'Religious', 'Imported public holiday for LK.', 0, 1, 0, 1, '2026-06-18 10:43:16'),
(11, 'Day prior to Sinhala and Tamil New Year Day', '2026-04-13', '2026-04-13', 'National', 'Imported public holiday for LK.', 0, 1, 0, 1, '2026-06-18 10:43:16'),
(12, 'Sinhala and Tamil New Year Day', '2026-04-14', '2026-04-14', 'National', 'Imported public holiday for LK.', 0, 1, 0, 1, '2026-06-18 10:43:16'),
(13, 'May Day and Full Moon Poya Day', '2026-05-01', '2026-05-01', 'National', 'Imported public holiday for LK.', 0, 1, 0, 1, '2026-06-18 10:43:16'),
(14, 'Vesak Full Moon Poya Day', '2026-05-30', '2026-05-30', 'Religious', 'Imported public holiday for LK.', 0, 1, 0, 1, '2026-06-18 10:43:16'),
(15, 'Day following Vesak Full Moon Poya Day', '2026-05-31', '2026-05-31', 'Religious', 'Imported public holiday for LK.', 0, 1, 0, 1, '2026-06-18 10:43:16'),
(17, 'Poson Full Moon Poya Day', '2026-06-29', '2026-06-29', 'Religious', 'Imported public holiday for LK.', 0, 1, 0, 1, '2026-06-18 10:43:16'),
(18, 'Milad un-Nabi', '2026-08-25', '2026-08-25', 'Religious', 'Imported public holiday for LK.', 0, 1, 0, 1, '2026-06-18 10:43:16'),
(19, 'Nikini Full Moon Poya Day', '2026-08-27', '2026-08-27', 'Religious', 'Imported public holiday for LK.', 0, 1, 0, 1, '2026-06-18 10:43:16'),
(20, 'Binara Full Moon Poya Day', '2026-09-26', '2026-09-26', 'Religious', 'Imported public holiday for LK.', 0, 1, 0, 1, '2026-06-18 10:43:16'),
(21, 'Vap Full Moon Poya Day', '2026-10-25', '2026-10-25', 'Religious', 'Imported public holiday for LK.', 0, 1, 0, 1, '2026-06-18 10:43:16'),
(22, 'Deepavali', '2026-11-08', '2026-11-08', 'Religious', 'Imported public holiday for LK.', 0, 1, 0, 1, '2026-06-18 10:43:16'),
(23, 'Il Full Moon Poya Day', '2026-11-24', '2026-11-24', 'Religious', 'Imported public holiday for LK.', 0, 1, 0, 1, '2026-06-18 10:43:16'),
(24, 'Unduvap Full Moon Poya Day', '2026-12-23', '2026-12-23', 'Religious', 'Imported public holiday for LK.', 0, 1, 0, 1, '2026-06-18 10:43:16'),
(25, 'Christmas Day', '2026-12-25', '2026-12-25', 'Religious', 'Imported public holiday for LK.', 0, 1, 0, 1, '2026-06-18 10:43:16');

-- --------------------------------------------------------

--
-- Table structure for table `holiday_branches`
--

CREATE TABLE `holiday_branches` (
  `holiday_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_applications`
--

CREATE TABLE `leave_applications` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `covering_employee_id` int(11) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` decimal(5,2) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_balances`
--

CREATE TABLE `leave_balances` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `leave_policy_id` int(11) DEFAULT NULL,
  `year` int(11) NOT NULL,
  `allocated_days` decimal(5,2) DEFAULT 0.00,
  `used_days` decimal(5,2) DEFAULT 0.00,
  `carried_forward` decimal(5,2) DEFAULT 0.00,
  `manual_adjustment` decimal(5,2) DEFAULT 0.00,
  `adjustment_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_policies`
--

CREATE TABLE `leave_policies` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `leave_type_id` int(11) NOT NULL,
  `accrual_type` enum('Monthly','Quarterly','Yearly','Fixed allocation') NOT NULL,
  `accrual_rate` decimal(5,2) NOT NULL,
  `carry_forward_limit` int(11) DEFAULT 0,
  `min_days_per_application` decimal(5,2) DEFAULT 1.00,
  `max_days_per_application` decimal(5,2) DEFAULT 365.00,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_policies`
--

INSERT INTO `leave_policies` (`id`, `name`, `description`, `leave_type_id`, `accrual_type`, `accrual_rate`, `carry_forward_limit`, `min_days_per_application`, `max_days_per_application`, `status`, `created_at`, `updated_at`) VALUES
(3, 'Standard Annual Policy', 'Base annual leave rules. Supports full-day and half-day applications.', 1, 'Yearly', 14.00, 5, 0.50, 14.00, 'Active', '2026-06-23 05:58:14', '2026-06-23 05:58:14'),
(4, 'Standard Short Leave Policy', 'Short leave allowance. Each short leave application consumes 0.25 day.', 5, 'Monthly', 0.50, 0, 0.25, 0.25, 'Active', '2026-06-23 05:58:14', '2026-06-23 05:58:14'),
(5, 'Standard Casual Policy', 'Base casual leave rules. Supports full-day and half-day applications.', 6, 'Yearly', 7.00, 0, 0.50, 7.00, 'Active', '2026-06-23 06:03:13', '2026-06-25 08:40:58'),
(6, 'Standard Medical Policy', 'Base medical leave rules. Supports full-day and half-day applications.', 2, 'Yearly', 7.00, 0, 0.50, 7.00, 'Active', '2026-06-25 05:48:39', '2026-06-25 08:40:52');

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_types`
--

CREATE TABLE `leave_types` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `max_days_per_year` int(11) DEFAULT 0,
  `is_paid` tinyint(1) DEFAULT 1,
  `color` varchar(20) DEFAULT '#3b82f6',
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_types`
--

INSERT INTO `leave_types` (`id`, `name`, `description`, `max_days_per_year`, `is_paid`, `color`, `status`, `created_at`) VALUES
(1, 'Annual Leave', 'Standard paid time off.', 14, 1, '#3b82f6', 'Active', '2026-06-02 14:13:29'),
(2, 'Medical Leave', 'Paid time off for medical reasons.', 7, 1, '#ef4444', 'Active', '2026-06-02 14:13:29'),
(3, 'Maternity Leave', 'Statutory maternity leave.', 84, 1, '#ec4899', 'Active', '2026-06-02 14:13:29'),
(4, 'Unpaid Leave', 'Time off without pay.', 365, 0, '#6b7280', 'Active', '2026-06-02 14:13:29'),
(5, 'Short Leave', 'Short leave permission. One request consumes 0.25 day.', 6, 1, '#06b6d4', 'Active', '2026-06-23 05:58:14'),
(6, 'Casual Leave', 'Paid casual leave for personal or urgent needs. Supports full-day and half-day applications.', 7, 1, '#f59e0b', 'Active', '2026-06-23 06:03:13');

-- --------------------------------------------------------

--
-- Table structure for table `meetings`
--

CREATE TABLE `meetings` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `organizer_id` int(11) NOT NULL,
  `attendees` text DEFAULT NULL,
  `status` enum('Scheduled','Cancelled','Completed') DEFAULT 'Scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll`
--

CREATE TABLE `payroll` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `month` varchar(20) NOT NULL,
  `base_salary` decimal(10,2) NOT NULL,
  `allowances` decimal(10,2) DEFAULT 0.00,
  `deductions` decimal(10,2) DEFAULT 0.00,
  `net_salary` decimal(10,2) NOT NULL,
  `status` enum('Draft','Processed','Paid') DEFAULT 'Draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_records`
--

CREATE TABLE `payroll_records` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `payroll_month` varchar(10) NOT NULL,
  `base_salary` decimal(10,2) DEFAULT 0.00,
  `overtime_hours` decimal(8,2) DEFAULT 0.00,
  `overtime_amount` decimal(10,2) DEFAULT 0.00,
  `deductions` decimal(10,2) DEFAULT 0.00,
  `unpaid_days` decimal(8,2) DEFAULT 0.00,
  `advance_amount` decimal(10,2) DEFAULT 0.00,
  `epf_employee_amount` decimal(10,2) DEFAULT 0.00,
  `epf_employer_amount` decimal(10,2) DEFAULT 0.00,
  `etf_employer_amount` decimal(10,2) DEFAULT 0.00,
  `net_salary` decimal(10,2) DEFAULT 0.00,
  `status` enum('Draft','Finalized','Paid') DEFAULT 'Draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll_records`
--

INSERT INTO `payroll_records` (`id`, `employee_id`, `payroll_month`, `base_salary`, `overtime_hours`, `overtime_amount`, `deductions`, `unpaid_days`, `advance_amount`, `epf_employee_amount`, `epf_employer_amount`, `etf_employer_amount`, `net_salary`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, '2026-06', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Finalized', '2026-06-30 05:30:16', '2026-06-30 05:30:16');

-- --------------------------------------------------------

--
-- Table structure for table `shifts`
--

CREATE TABLE `shifts` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `grace_period` int(11) DEFAULT 0,
  `is_night_shift` tinyint(1) DEFAULT 0,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shifts`
--

INSERT INTO `shifts` (`id`, `name`, `description`, `start_time`, `end_time`, `grace_period`, `is_night_shift`, `status`, `created_at`) VALUES
(1, 'Standard Day Shift', 'Regular 9-to-5 working hours.', '09:00:00', '17:00:00', 15, 0, 'Active', '2026-06-02 14:22:03'),
(2, 'Standard Night Shift', 'Regular 5-to-1 working hours.', '17:00:00', '01:00:00', 15, 1, 'Active', '2026-06-23 06:45:30');

-- --------------------------------------------------------

--
-- Table structure for table `shift_weekly_schedules`
--

CREATE TABLE `shift_weekly_schedules` (
  `id` int(11) NOT NULL,
  `shift_id` int(11) NOT NULL,
  `weekday` tinyint(4) NOT NULL,
  `is_working` tinyint(1) DEFAULT 1,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `is_night_shift` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shift_weekly_schedules`
--

INSERT INTO `shift_weekly_schedules` (`id`, `shift_id`, `weekday`, `is_working`, `start_time`, `end_time`, `is_night_shift`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, '09:00:00', '17:00:00', 0, '2026-06-25 07:56:49', '2026-06-25 07:56:49'),
(2, 2, 1, 1, '17:00:00', '01:00:00', 1, '2026-06-25 07:56:49', '2026-06-25 07:56:49'),
(4, 1, 2, 1, '09:00:00', '17:00:00', 0, '2026-06-25 07:56:49', '2026-06-25 07:56:49'),
(5, 2, 2, 1, '17:00:00', '01:00:00', 1, '2026-06-25 07:56:49', '2026-06-25 07:56:49'),
(7, 1, 3, 1, '09:00:00', '17:00:00', 0, '2026-06-25 07:56:49', '2026-06-25 07:56:49'),
(8, 2, 3, 1, '17:00:00', '01:00:00', 1, '2026-06-25 07:56:49', '2026-06-25 07:56:49'),
(10, 1, 4, 1, '09:00:00', '17:00:00', 0, '2026-06-25 07:56:49', '2026-06-25 07:56:49'),
(11, 2, 4, 1, '17:00:00', '01:00:00', 1, '2026-06-25 07:56:49', '2026-06-25 07:56:49'),
(13, 1, 5, 1, '09:00:00', '17:00:00', 0, '2026-06-25 07:56:49', '2026-06-25 07:56:49'),
(14, 2, 5, 1, '17:00:00', '01:00:00', 1, '2026-06-25 07:56:49', '2026-06-25 07:56:49'),
(16, 1, 6, 1, '09:00:00', '13:00:00', 0, '2026-06-25 07:56:49', '2026-06-25 08:02:25'),
(17, 2, 6, 1, '17:00:00', '21:00:00', 1, '2026-06-25 07:56:49', '2026-06-25 08:04:27'),
(19, 1, 7, 0, NULL, NULL, 0, '2026-06-25 07:56:49', '2026-06-25 07:56:49'),
(20, 2, 7, 0, NULL, NULL, 1, '2026-06-25 07:56:49', '2026-06-25 07:56:49');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('company_name', 'HRM365 Enterprise', '2026-06-02 10:56:08'),
('currency', 'LKR', '2026-06-18 05:25:25'),
('epf_employee_rate', '8.00', '2026-06-28 17:31:25'),
('epf_employer_rate', '12.00', '2026-06-28 17:31:25'),
('etf_employer_rate', '3.00', '2026-06-28 17:31:25'),
('grace_period_mins', '15', '2026-06-02 10:58:47'),
('holiday_country', 'LK', '2026-06-18 10:33:07'),
('late_deduction_amount', '50.00', '2026-06-02 10:58:47'),
('payroll_enable_epf', '1', '2026-06-28 20:20:18'),
('payroll_enable_etf', '1', '2026-06-28 20:20:18'),
('payroll_enable_overtime', '1', '2026-06-28 20:19:34'),
('sms_api_key', '309|pkrTVtkgUWcbNZbBpL2UHD8EQnnKkcZlTN2oyYlUeeca439e', '2026-06-30 05:19:11'),
('sms_api_url', 'https://app.text.lk/api/v3/sms/send', '2026-06-29 14:33:15'),
('sms_enabled', '1', '2026-06-29 14:04:20'),
('sms_provider', 'textlk', '2026-06-29 14:33:15'),
('sms_sender_name', 'JMS Lanka', '2026-06-30 05:19:11'),
('timezone', 'Asia/Colombo', '2026-06-18 09:24:11');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `role` enum('admin','HR','manager','employee') DEFAULT 'employee',
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `language` varchar(20) DEFAULT 'English',
  `department` varchar(50) DEFAULT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `phone`, `role`, `status`, `language`, `department`, `employee_id`, `created_at`) VALUES
(1, 'sairathan2@gmail.com', '$2y$10$yfSYoTYR6w8Fs83eslznMeBtV9QGM6BD8FRhmjBFtwNPXf9XbQtBq', 'Sairathan Krishnatheva', '+94755610209', 'admin', 'Active', 'English', 'Finance', 1, '2026-06-30 05:26:46');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `advance_payments`
--
ALTER TABLE `advance_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `paid_by` (`paid_by`);

--
-- Indexes for table `attendance_policies`
--
ALTER TABLE `attendance_policies`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `emp_date` (`employee_id`,`date`);

--
-- Indexes for table `attendance_regularizations`
--
ALTER TABLE `attendance_regularizations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `attendance_record_id` (`attendance_record_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `biometric_punches`
--
ALTER TABLE `biometric_punches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dedup` (`biometric_user_id`,`punch_time`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_code` (`employee_code`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `biometric_user_id` (`biometric_user_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `shift_id` (`shift_id`),
  ADD KEY `attendance_policy_id` (`attendance_policy_id`);

--
-- Indexes for table `employee_bank_details`
--
ALTER TABLE `employee_bank_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `employee_shift_overrides`
--
ALTER TABLE `employee_shift_overrides`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_weekday` (`employee_id`,`weekday`),
  ADD KEY `shift_id` (`shift_id`);

--
-- Indexes for table `holidays`
--
ALTER TABLE `holidays`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `holiday_branches`
--
ALTER TABLE `holiday_branches`
  ADD PRIMARY KEY (`holiday_id`,`branch_id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `leave_applications`
--
ALTER TABLE `leave_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `leave_type_id` (`leave_type_id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `covering_employee_id` (`covering_employee_id`);

--
-- Indexes for table `leave_balances`
--
ALTER TABLE `leave_balances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `emp_type_year` (`employee_id`,`leave_type_id`,`year`),
  ADD KEY `leave_type_id` (`leave_type_id`),
  ADD KEY `leave_policy_id` (`leave_policy_id`);

--
-- Indexes for table `leave_policies`
--
ALTER TABLE `leave_policies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `leave_type_id` (`leave_type_id`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `leave_type_id` (`leave_type_id`);

--
-- Indexes for table `leave_types`
--
ALTER TABLE `leave_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `meetings`
--
ALTER TABLE `meetings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `organizer_id` (`organizer_id`);

--
-- Indexes for table `payroll`
--
ALTER TABLE `payroll`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_emp_month` (`employee_id`,`month`);

--
-- Indexes for table `payroll_records`
--
ALTER TABLE `payroll_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `emp_month` (`employee_id`,`payroll_month`);

--
-- Indexes for table `shifts`
--
ALTER TABLE `shifts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `shift_weekly_schedules`
--
ALTER TABLE `shift_weekly_schedules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `shift_weekday` (`shift_id`,`weekday`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `unique_employee_login` (`employee_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `advance_payments`
--
ALTER TABLE `advance_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_policies`
--
ALTER TABLE `attendance_policies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `attendance_records`
--
ALTER TABLE `attendance_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_regularizations`
--
ALTER TABLE `attendance_regularizations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `biometric_punches`
--
ALTER TABLE `biometric_punches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `employee_bank_details`
--
ALTER TABLE `employee_bank_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `employee_shift_overrides`
--
ALTER TABLE `employee_shift_overrides`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `leave_applications`
--
ALTER TABLE `leave_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_balances`
--
ALTER TABLE `leave_balances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_policies`
--
ALTER TABLE `leave_policies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_types`
--
ALTER TABLE `leave_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `meetings`
--
ALTER TABLE `meetings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll`
--
ALTER TABLE `payroll`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_records`
--
ALTER TABLE `payroll_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `shifts`
--
ALTER TABLE `shifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `shift_weekly_schedules`
--
ALTER TABLE `shift_weekly_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2356;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `advance_payments`
--
ALTER TABLE `advance_payments`
  ADD CONSTRAINT `advance_payments_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `advance_payments_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `advance_payments_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `advance_payments_ibfk_4` FOREIGN KEY (`paid_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD CONSTRAINT `attendance_records_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance_regularizations`
--
ALTER TABLE `attendance_regularizations`
  ADD CONSTRAINT `attendance_regularizations_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_regularizations_ibfk_2` FOREIGN KEY (`attendance_record_id`) REFERENCES `attendance_records` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_regularizations_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `employees_ibfk_2` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `employees_ibfk_3` FOREIGN KEY (`attendance_policy_id`) REFERENCES `attendance_policies` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `employee_bank_details`
--
ALTER TABLE `employee_bank_details`
  ADD CONSTRAINT `employee_bank_details_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_shift_overrides`
--
ALTER TABLE `employee_shift_overrides`
  ADD CONSTRAINT `employee_shift_overrides_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_shift_overrides_ibfk_2` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `holiday_branches`
--
ALTER TABLE `holiday_branches`
  ADD CONSTRAINT `holiday_branches_ibfk_1` FOREIGN KEY (`holiday_id`) REFERENCES `holidays` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `holiday_branches_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leave_applications`
--
ALTER TABLE `leave_applications`
  ADD CONSTRAINT `leave_applications_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_applications_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_applications_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `leave_applications_ibfk_4` FOREIGN KEY (`covering_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `leave_balances`
--
ALTER TABLE `leave_balances`
  ADD CONSTRAINT `leave_balances_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_balances_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_balances_ibfk_3` FOREIGN KEY (`leave_policy_id`) REFERENCES `leave_policies` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `leave_policies`
--
ALTER TABLE `leave_policies`
  ADD CONSTRAINT `leave_policies_ibfk_1` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_requests_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `meetings`
--
ALTER TABLE `meetings`
  ADD CONSTRAINT `meetings_ibfk_1` FOREIGN KEY (`organizer_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll`
--
ALTER TABLE `payroll`
  ADD CONSTRAINT `payroll_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll_records`
--
ALTER TABLE `payroll_records`
  ADD CONSTRAINT `payroll_records_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shift_weekly_schedules`
--
ALTER TABLE `shift_weekly_schedules`
  ADD CONSTRAINT `shift_weekly_schedules_ibfk_1` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
