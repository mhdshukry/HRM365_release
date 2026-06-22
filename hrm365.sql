-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 22, 2026 at 09:55 AM
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
(1, 'Strict Office Policy', 'Zero tolerance policy with standard overtime.', 0, 0, 1.50, 'Active', '2026-06-02 14:26:50', '2026-06-02 14:26:50'),
(2, 'Flexible Remote Policy', 'Allows 15 min buffer on both ends.', 15, 15, 1.00, 'Active', '2026-06-02 14:26:50', '2026-06-04 15:11:19');

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

--
-- Dumping data for table `attendance_records`
--

INSERT INTO `attendance_records` (`id`, `employee_id`, `shift_id`, `attendance_policy_id`, `date`, `clock_in`, `clock_out`, `total_hours`, `break_hours`, `overtime_hours`, `overtime_amount`, `is_late`, `is_early_departure`, `is_absent`, `is_holiday`, `is_weekend`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, NULL, '2026-06-02', '2026-06-02 08:35:00', '2026-06-02 18:30:00', 9.92, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 'Present', NULL, '2026-06-02 15:05:53', '2026-06-02 15:05:53'),
(2, 5, NULL, NULL, '2026-06-02', '2026-06-02 20:45:07', NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 'Present', NULL, '2026-06-02 15:15:33', '2026-06-02 15:15:33'),
(3, 4, NULL, NULL, '2026-06-02', '2026-06-02 20:46:30', NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 'Present', NULL, '2026-06-03 02:43:10', '2026-06-03 02:43:10'),
(4, 5, NULL, NULL, '2026-06-03', '2026-06-03 12:20:48', '2026-06-03 16:07:19', 3.78, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 'Present', NULL, '2026-06-03 06:51:00', '2026-06-03 10:40:31'),
(5, 4, NULL, NULL, '2026-06-03', '2026-06-03 12:20:49', '2026-06-03 16:07:21', 3.78, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 'Present', NULL, '2026-06-03 06:51:00', '2026-06-03 10:40:31'),
(10, 1, NULL, NULL, '2026-06-04', '2026-06-04 08:35:00', '2026-06-04 18:30:00', 9.92, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 'Present', NULL, '2026-06-04 03:44:26', '2026-06-04 03:44:26'),
(11, 5, 1, 1, '2026-06-04', '2026-06-04 09:13:23', '2026-06-04 10:19:21', 1.10, 0.00, 0.00, 0.00, 1, 1, 0, 0, 0, 'Present', NULL, '2026-06-04 03:50:30', '2026-06-17 10:36:56'),
(12, 4, 1, 1, '2026-06-04', '2026-06-04 09:16:08', '2026-06-04 09:19:46', 0.06, 0.00, 0.00, 0.00, 1, 1, 0, 0, 0, 'Present', NULL, '2026-06-04 03:50:30', '2026-06-04 03:50:30'),
(13, 1, NULL, NULL, '2026-06-17', '2026-06-17 08:35:00', '2026-06-17 18:30:00', 9.92, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 'Present', NULL, '2026-06-17 09:46:18', '2026-06-17 10:44:45'),
(15, 5, 1, 1, '2026-06-10', '2026-06-10 10:07:48', NULL, 0.00, 0.00, 0.00, 0.00, 1, 0, 0, 0, 0, 'Present', NULL, '2026-06-17 10:36:56', '2026-06-17 10:36:56'),
(16, 5, 1, 1, '2026-06-17', '2026-06-17 15:36:22', '2026-06-17 16:26:26', 0.83, 0.00, 0.00, 0.00, 1, 1, 0, 0, 0, 'Present', NULL, '2026-06-17 10:36:56', '2026-06-17 10:56:40'),
(17, 4, 1, 1, '2026-06-17', '2026-06-17 15:36:20', '2026-06-17 16:26:22', 0.83, 0.00, 0.00, 0.00, 1, 1, 0, 0, 0, 'Present', NULL, '2026-06-17 10:36:56', '2026-06-17 10:56:40'),
(23, 1, NULL, NULL, '2026-06-18', '2026-06-18 08:35:00', '2026-06-18 18:30:00', 9.92, 0.00, 1.50, 0.00, 0, 0, 0, 0, 0, 'Present', NULL, '2026-06-18 05:33:15', '2026-06-18 11:22:58'),
(72, 4, 1, 1, '2026-06-18', '2026-06-18 10:50:15', '2026-06-18 16:48:48', 5.98, 0.00, 0.00, 0.00, 1, 1, 0, 0, 0, 'Present', NULL, '2026-06-18 06:26:58', '2026-06-18 11:19:57'),
(73, 5, 1, 1, '2026-06-18', '2026-06-18 10:50:17', '2026-06-18 16:49:34', 5.99, 0.00, 0.00, 0.00, 1, 1, 0, 0, 0, 'Present', NULL, '2026-06-18 06:26:58', '2026-06-18 11:19:57'),
(74, 9, NULL, NULL, '2026-06-18', '2026-06-18 11:06:59', '2026-06-18 12:22:11', 1.25, 0.00, 0.00, 0.00, 1, 1, 0, 0, 0, 'Present', NULL, '2026-06-18 06:26:58', '2026-06-18 06:52:29'),
(105, 1, 1, 2, '2026-06-22', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(106, 2, 1, 2, '2026-06-22', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(107, 3, 1, 2, '2026-06-22', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(108, 4, 1, 1, '2026-06-05', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(109, 4, 1, 1, '2026-06-08', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(110, 4, 1, 1, '2026-06-09', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(111, 4, 1, 1, '2026-06-10', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(112, 4, 1, 1, '2026-06-11', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(113, 4, 1, 1, '2026-06-12', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(114, 4, 1, 1, '2026-06-15', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(115, 4, 1, 1, '2026-06-16', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(116, 4, 1, 1, '2026-06-19', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(117, 4, 1, 1, '2026-06-22', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(118, 5, 1, 1, '2026-06-05', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(119, 5, 1, 1, '2026-06-08', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(120, 5, 1, 1, '2026-06-09', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(121, 5, 1, 1, '2026-06-11', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(122, 5, 1, 1, '2026-06-12', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(123, 5, 1, 1, '2026-06-15', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(124, 5, 1, 1, '2026-06-16', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(125, 5, 1, 1, '2026-06-19', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(126, 5, 1, 1, '2026-06-22', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(127, 8, 1, 1, '2026-06-02', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(128, 8, 1, 1, '2026-06-03', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(129, 8, 1, 1, '2026-06-04', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(130, 8, 1, 1, '2026-06-05', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(131, 8, 1, 1, '2026-06-08', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(132, 8, 1, 1, '2026-06-09', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(133, 8, 1, 1, '2026-06-10', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(134, 8, 1, 1, '2026-06-11', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(135, 8, 1, 1, '2026-06-12', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(136, 8, 1, 1, '2026-06-15', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(137, 8, 1, 1, '2026-06-16', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(138, 8, 1, 1, '2026-06-17', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(139, 8, 1, 1, '2026-06-18', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(140, 8, 1, 1, '2026-06-19', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(141, 8, 1, 1, '2026-06-22', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(142, 9, 1, 1, '2026-06-01', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(143, 9, 1, 1, '2026-06-02', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(144, 9, 1, 1, '2026-06-03', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(145, 9, 1, 1, '2026-06-04', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(146, 9, 1, 1, '2026-06-05', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(147, 9, 1, 1, '2026-06-08', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(148, 9, 1, 1, '2026-06-09', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(149, 9, 1, 1, '2026-06-10', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(150, 9, 1, 1, '2026-06-11', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(151, 9, 1, 1, '2026-06-12', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(152, 9, 1, 1, '2026-06-15', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(153, 9, 1, 1, '2026-06-16', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(154, 9, 1, 1, '2026-06-17', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(155, 9, 1, 1, '2026-06-19', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(156, 9, 1, 1, '2026-06-22', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:29:02', '2026-06-22 07:29:02'),
(157, 9, 1, 1, '2026-05-18', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:30:29', '2026-06-22 07:30:29'),
(158, 9, 1, 1, '2026-05-19', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:30:29', '2026-06-22 07:30:29'),
(159, 9, 1, 1, '2026-05-20', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:30:29', '2026-06-22 07:30:29'),
(160, 9, 1, 1, '2026-05-21', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:30:29', '2026-06-22 07:30:29'),
(161, 9, 1, 1, '2026-05-22', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:30:29', '2026-06-22 07:30:29'),
(162, 9, 1, 1, '2026-05-25', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:30:29', '2026-06-22 07:30:29'),
(163, 9, 1, 1, '2026-05-26', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:30:29', '2026-06-22 07:30:29'),
(164, 9, 1, 1, '2026-05-27', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:30:29', '2026-06-22 07:30:29'),
(165, 9, 1, 1, '2026-05-28', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:30:29', '2026-06-22 07:30:29'),
(166, 9, 1, 1, '2026-05-29', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 1, 0, 0, 'Absent', 'Generated as unpaid absence by payroll engine', '2026-06-22 07:30:29', '2026-06-22 07:30:29');

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
(1, 1, 'BRANCH_CREATED', 'Created new branch: lushanth vasanthakumar', '::1', '2026-06-02 14:02:34'),
(2, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-02', '::1', '2026-06-02 14:47:59'),
(3, 1, 'EMPLOYEE_CREATED', 'Onboarded new employee: EMP-005 (lushanth vasanthakumar)', '::1', '2026-06-02 14:54:22'),
(4, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-02', '::1', '2026-06-02 15:05:53'),
(5, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-02', '::1', '2026-06-02 15:05:55'),
(6, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-02', '::1', '2026-06-02 15:05:56'),
(7, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-02', '::1', '2026-06-02 15:09:09'),
(8, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-02', '::1', '2026-06-02 15:09:13'),
(9, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-02', '::1', '2026-06-02 15:09:28'),
(10, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-02', '::1', '2026-06-02 15:09:30'),
(11, 1, 'EMPLOYEE_CREATED', 'Onboarded new employee: EMP-006 (test user vasanthakumar)', '::1', '2026-06-02 15:10:19'),
(12, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-02', '::1', '2026-06-02 15:10:30'),
(13, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-02', '::1', '2026-06-02 15:10:53'),
(14, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-02', '::1', '2026-06-02 15:10:55'),
(15, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-02', '::1', '2026-06-02 15:13:17'),
(16, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-02', '::1', '2026-06-02 15:15:33'),
(17, 1, 'PAYROLL_GENERATED', 'Generated payroll for 2026-06', '::1', '2026-06-03 02:58:19'),
(18, 1, 'LOGIN_SUCCESS', 'User authenticated successfully via Web Portal.', '::1', '2026-06-03 06:43:05'),
(19, 1, 'LOGIN_SUCCESS', 'User authenticated successfully via Web Portal.', '::1', '2026-06-03 10:36:54'),
(20, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-03', '::1', '2026-06-03 10:37:34'),
(21, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-03', '::1', '2026-06-03 10:37:51'),
(22, 1, 'LOGIN_SUCCESS', 'User authenticated successfully via Web Portal.', '::1', '2026-06-03 15:42:40'),
(23, 1, 'ATTENDANCE_ASSIGNMENT', 'Updated Shift & Policy bindings for Employee #4', '::1', '2026-06-03 17:15:16'),
(24, 1, 'ATTENDANCE_ASSIGNMENT', 'Updated Shift & Policy bindings for Employee #5', '::1', '2026-06-03 17:15:31'),
(25, 1, 'LOGIN_SUCCESS', 'User authenticated successfully via Web Portal.', '::1', '2026-06-04 03:43:13'),
(26, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-04', '::1', '2026-06-04 03:43:31'),
(27, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-04', '::1', '2026-06-04 03:43:33'),
(28, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-04', '::1', '2026-06-04 03:43:34'),
(29, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-04', '::1', '2026-06-04 03:43:37'),
(30, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-04', '::1', '2026-06-04 03:44:26'),
(31, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-04', '::1', '2026-06-04 03:44:28'),
(32, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-04', '::1', '2026-06-04 03:44:30'),
(33, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-04', '::1', '2026-06-04 03:45:13'),
(34, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-04', '::1', '2026-06-04 03:46:17'),
(35, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-04', '::1', '2026-06-04 03:50:30'),
(36, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-04', '::1', '2026-06-04 03:58:18'),
(37, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-04', '::1', '2026-06-04 03:58:46'),
(38, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-04', '::1', '2026-06-04 03:58:58'),
(39, 1, 'LOGIN_SUCCESS', 'User authenticated successfully via Web Portal.', '::1', '2026-06-04 15:07:55'),
(40, 1, 'ATTENDANCE_POLICY_STATUS_CHANGE', 'Changed Policy #2 status to Inactive', '::1', '2026-06-04 15:11:18'),
(41, 1, 'ATTENDANCE_POLICY_STATUS_CHANGE', 'Changed Policy #2 status to Active', '::1', '2026-06-04 15:11:19'),
(42, 1, 'LOGIN_SUCCESS', 'User authenticated successfully via Web Portal.', '::1', '2026-06-04 15:14:59'),
(43, 1, 'LOGIN_SUCCESS', 'User authenticated successfully via Web Portal.', '::1', '2026-06-17 09:41:40'),
(44, 1, 'LOGIN_SUCCESS', 'User authenticated successfully via Web Portal.', '::1', '2026-06-17 09:43:24'),
(45, 1, 'ATTENDANCE_CLOCK_IN', 'Clocked in at 11:46:18', '::1', '2026-06-17 09:46:18'),
(46, 1, 'ATTENDANCE_CLOCK_OUT', 'Clocked out at 11:46:22. Total Hours: 0h', '::1', '2026-06-17 09:46:22'),
(47, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-17', '::1', '2026-06-17 10:06:40'),
(48, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-17', '::1', '2026-06-17 10:10:28'),
(49, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-17', '::1', '2026-06-17 10:10:30'),
(50, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-17', '::1', '2026-06-17 10:10:30'),
(51, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-17', '::1', '2026-06-17 10:10:30'),
(52, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-17', '::1', '2026-06-17 10:38:02'),
(53, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-17', '::1', '2026-06-17 10:38:36'),
(54, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-17', '::1', '2026-06-17 10:44:45'),
(55, 1, 'EMPLOYEE_CREATED', 'Onboarded new employee: EMP-009 (M SK)', '::1', '2026-06-17 10:51:52'),
(56, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-17', '::1', '2026-06-17 10:52:52'),
(57, 1, 'LOGIN_SUCCESS', 'User authenticated successfully via Web Portal.', '::1', '2026-06-17 11:22:12'),
(58, 1, 'LOGIN_SUCCESS', 'User authenticated successfully via Web Portal.', '::1', '2026-06-18 04:23:53'),
(59, 1, 'PAYROLL_GENERATED', 'Generated payroll for 2026-06', '::1', '2026-06-18 05:21:47'),
(60, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:37:09'),
(61, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:37:24'),
(62, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:37:26'),
(63, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:37:26'),
(64, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:37:26'),
(65, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:41:14'),
(66, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:41:27'),
(67, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:41:28'),
(68, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:41:29'),
(69, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:41:29'),
(70, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:41:30'),
(71, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:41:30'),
(72, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:41:38'),
(73, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:41:40'),
(74, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:41:41'),
(75, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:41:44'),
(76, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:41:45'),
(77, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:41:55'),
(78, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:41:55'),
(79, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:42:15'),
(80, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:42:16'),
(81, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:42:57'),
(82, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:42:58'),
(83, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:42:59'),
(84, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:42:59'),
(85, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:43:00'),
(86, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:43:00'),
(87, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:43:00'),
(88, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:43:00'),
(89, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:43:00'),
(90, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:43:08'),
(91, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:43:29'),
(92, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:43:30'),
(93, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:46:39'),
(94, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:47:03'),
(95, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:47:04'),
(96, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:47:04'),
(97, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:47:13'),
(98, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:47:19'),
(99, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:47:20'),
(100, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:47:37'),
(101, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:47:38'),
(102, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:47:50'),
(103, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 05:50:45'),
(104, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 06:10:45'),
(105, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 06:11:03'),
(106, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 06:12:28'),
(107, 1, 'PAYROLL_GENERATED', 'Generated payroll for 2026-06', '::1', '2026-06-18 06:36:43'),
(108, 1, 'ATTENDANCE_ASSIGNMENT', 'Updated Shift & Policy bindings for Employee #9', '::1', '2026-06-18 06:51:38'),
(109, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 06:52:42'),
(110, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 06:52:49'),
(111, 1, 'BRANCH_STATUS_CHANGE', 'Changed branch #1 status to Inactive', '::1', '2026-06-18 08:18:20'),
(112, 1, 'BRANCH_STATUS_CHANGE', 'Changed branch #1 status to Active', '::1', '2026-06-18 08:18:22'),
(113, 1, 'USER_UPDATED', 'Updated system user: admin', '::1', '2026-06-18 08:23:14'),
(114, 1, 'ATTENDANCE_ASSIGNMENT', 'Updated Shift & Policy bindings for Employee #9', '::1', '2026-06-18 09:20:03'),
(115, 1, 'ATTENDANCE_ASSIGNMENT', 'Updated Shift & Policy bindings for Employee #9', '::1', '2026-06-18 09:20:37'),
(116, 1, 'PAYROLL_GENERATED', 'Generated payroll for 2026-05', '::1', '2026-06-18 09:24:27'),
(117, 1, 'PAYROLL_GENERATED', 'Generated payroll for 2026-06', '::1', '2026-06-18 09:24:36'),
(118, 1, 'PAYROLL_GENERATED', 'Generated payroll for 2026-06', '::1', '2026-06-18 09:24:39'),
(119, 1, 'ATTENDANCE_ASSIGNMENT', 'Updated Shift & Policy bindings for EMP-009', '::1', '2026-06-18 09:32:25'),
(120, 1, 'LOGIN_SUCCESS', 'User authenticated successfully via Web Portal.', '::1', '2026-06-18 09:39:32'),
(121, 1, 'LOGIN_SUCCESS', 'User authenticated successfully via Web Portal.', '::1', '2026-06-18 09:48:45'),
(122, 1, 'PAYROLL_STATUS_UPDATED', 'Set payroll EMP-009 2026-06 to Draft', '::1', '2026-06-18 10:22:48'),
(123, 1, 'PAYROLL_STATUS_UPDATED', 'Set payroll EMP-009 2026-06 to Paid', '::1', '2026-06-18 10:22:54'),
(124, 1, 'PAYROLL_STATUS_UPDATED', 'Set payroll EMP-009 2026-06 to Draft', '::1', '2026-06-18 10:23:00'),
(125, 1, 'PAYROLL_STATUS_UPDATED', 'Set payroll EMP-009 2026-06 to Paid', '::1', '2026-06-18 10:23:05'),
(126, 1, 'ATTENDANCE_ASSIGNMENT', 'Updated Shift & Policy bindings for EMP-001', '::1', '2026-06-18 10:27:51'),
(127, 1, 'ATTENDANCE_ASSIGNMENT', 'Updated Shift & Policy bindings for EMP-015', '::1', '2026-06-18 10:28:01'),
(128, 1, 'ATTENDANCE_ASSIGNMENT', 'Updated Shift & Policy bindings for EMP-042', '::1', '2026-06-18 10:28:12'),
(129, 1, 'ATTENDANCE_ASSIGNMENT', 'Updated Shift & Policy bindings for EMP-006', '::1', '2026-06-18 10:28:25'),
(130, 1, 'SETTINGS_UPDATED', 'Updated company settings: HRM365 Enterprise, Asia/Colombo, LKR, holidays LK', '::1', '2026-06-18 10:34:26'),
(131, 1, 'PUBLIC_HOLIDAYS_IMPORTED', 'Imported 24 public holidays for LK 2026; skipped 1', '::1', '2026-06-18 10:43:16'),
(132, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 11:22:58'),
(133, 1, 'BIOMETRIC_SYNC', 'Synchronized biometric data for 2026-06-18', '::1', '2026-06-18 11:22:59'),
(134, 1, 'LOGIN_SUCCESS', 'User authenticated successfully via Web Portal.', '::1', '2026-06-22 04:42:10'),
(135, 1, 'USER_CREATED', 'Created new system user: abcd@gmail.com', '::1', '2026-06-22 06:56:26'),
(136, 1, 'USER_UPDATED', 'Updated system user: abcd@gmail.com', '::1', '2026-06-22 06:57:14'),
(137, 2, 'LOGIN_SUCCESS', 'User authenticated successfully via Web Portal.', '::1', '2026-06-22 06:57:29'),
(138, 2, 'LEAVE_REQUESTED', 'Leave requested for Emp #5', '::1', '2026-06-22 07:01:52'),
(139, 1, 'LOGIN_SUCCESS', 'User authenticated successfully via Web Portal.', '::1', '2026-06-22 07:02:24'),
(140, 1, 'LEAVE_APPROVED', 'Approved leave request #1', '::1', '2026-06-22 07:03:22'),
(141, 1, 'PAYROLL_STATUS_UPDATED', 'Set payroll EMP-009 2026-06 to Draft', '::1', '2026-06-22 07:17:47'),
(142, 1, 'LOGIN_SUCCESS', 'User authenticated successfully via Web Portal.', '::1', '2026-06-22 07:25:15'),
(143, 2, 'LOGIN_SUCCESS', 'User authenticated successfully via Web Portal.', '::1', '2026-06-22 07:25:30'),
(144, 1, 'EMPLOYEE_UPDATED', 'EMPLOYEE_UPDATED: EMP-005 (lushanth vasanthakumar)', '::1', '2026-06-22 07:26:55'),
(145, 1, 'EMPLOYEE_UPDATED', 'EMPLOYEE_UPDATED: 10001 (lushanth vasanthakumar)', '::1', '2026-06-22 07:27:06'),
(146, 1, 'EMPLOYEE_UPDATED', 'EMPLOYEE_UPDATED: EMP-001 (John Doe)', '::1', '2026-06-22 07:27:19'),
(147, 1, 'EMPLOYEE_UPDATED', 'EMPLOYEE_UPDATED: EMP-042 (Sarah Smith)', '::1', '2026-06-22 07:27:47'),
(148, 1, 'EMPLOYEE_UPDATED', 'EMPLOYEE_UPDATED: EMP-042 (Sarah Smith)', '::1', '2026-06-22 07:27:54'),
(149, 1, 'EMPLOYEE_UPDATED', 'EMPLOYEE_UPDATED: EMP-015 (Mike Johnson)', '::1', '2026-06-22 07:28:04'),
(150, 1, 'PAYROLL_GENERATED', 'Generated payroll for 2026-06', '::1', '2026-06-22 07:29:02'),
(151, 1, 'PAYROLL_GENERATED', 'Generated payroll for 2026-05', '::1', '2026-06-22 07:30:29'),
(152, 1, 'PUBLIC_HOLIDAYS_IMPORTED', 'Imported 0 public holidays for LK 2026; skipped 25', '::1', '2026-06-22 07:47:50');

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

--
-- Dumping data for table `biometric_punches`
--

INSERT INTO `biometric_punches` (`id`, `biometric_user_id`, `punch_time`, `punch_direction`, `log_status`, `terminal_sn`, `is_synced`, `created_at`) VALUES
(1, '1001', '2026-06-02 08:35:00', 'CHECK_IN', 'Pending', 'SN-BRANCH-A', 1, '2026-06-02 14:48:03'),
(2, '1001', '2026-06-02 18:30:00', 'CHECK_OUT', 'Pending', 'SN-BRANCH-A', 1, '2026-06-02 14:48:03'),
(7, '9011', '2026-06-02 20:45:07', 'UNKNOWN', 'Pending', 'FQQ2254700186', 1, '2026-06-02 15:15:06'),
(8, '9999', '2026-06-02 20:46:30', 'UNKNOWN', 'Pending', 'FQQ2254700186', 1, '2026-06-02 15:16:30'),
(9, '9011', '2026-06-03 12:20:48', 'UNKNOWN', 'Pending', 'FQQ2254700186', 1, '2026-06-03 06:50:47'),
(10, '9999', '2026-06-03 12:20:49', 'UNKNOWN', 'Pending', 'FQQ2254700186', 1, '2026-06-03 06:50:49'),
(11, '9011', '2026-06-03 16:07:19', 'UNKNOWN', 'Pending', 'FQQ2254700186', 1, '2026-06-03 10:37:20'),
(12, '9999', '2026-06-03 16:07:21', 'UNKNOWN', 'Pending', 'FQQ2254700186', 1, '2026-06-03 10:37:22'),
(13, '1001', '2026-06-04 08:35:00', 'CHECK_IN', 'Pending', 'SN-BRANCH-A', 1, '2026-06-04 03:44:19'),
(14, '1001', '2026-06-04 18:30:00', 'CHECK_OUT', 'Pending', 'SN-BRANCH-A', 1, '2026-06-04 03:44:19'),
(19, '9011', '2026-06-04 09:13:23', 'UNKNOWN', 'Pending', 'FQQ2254700186', 1, '2026-06-04 03:50:19'),
(20, '9999', '2026-06-04 09:16:08', 'UNKNOWN', 'Pending', 'FQQ2254700186', 1, '2026-06-04 03:50:19'),
(21, '9999', '2026-06-04 09:19:46', 'UNKNOWN', 'Pending', 'FQQ2254700186', 1, '2026-06-04 03:50:19'),
(22, '9011', '2026-06-04 10:19:21', 'UNKNOWN', 'Pending', 'FQQ2254700186', 1, '2026-06-17 10:34:16'),
(23, '9011', '2026-06-10 10:07:48', 'UNKNOWN', 'Pending', 'FQQ2254700186', 1, '2026-06-17 10:34:16'),
(24, '9999', '2026-06-17 15:36:20', 'UNKNOWN', 'Pending', 'FQQ2254700186', 1, '2026-06-17 10:34:16'),
(25, '9011', '2026-06-17 15:36:22', 'UNKNOWN', 'Pending', 'FQQ2254700186', 1, '2026-06-17 10:34:16'),
(26, '1050', '2026-06-17 16:07:14', 'UNKNOWN', 'Pending', 'FQQ2254700186', 1, '2026-06-17 10:37:16'),
(27, '9999', '2026-06-17 16:08:10', 'UNKNOWN', 'Pending', 'FQQ2254700186', 1, '2026-06-17 10:38:13'),
(28, '9011', '2026-06-17 16:08:12', 'UNKNOWN', 'Pending', 'FQQ2254700186', 1, '2026-06-17 10:38:14'),
(29, '1001', '2026-06-17 08:35:00', 'CHECK_IN', 'Pending', 'SN-BRANCH-A', 1, '2026-06-17 10:44:37'),
(30, '1001', '2026-06-17 18:30:00', 'CHECK_OUT', 'Pending', 'SN-BRANCH-A', 1, '2026-06-17 10:44:37'),
(37, '9999', '2026-06-17 16:26:22', 'UNKNOWN', 'Pending', 'FQQ2254700186', 1, '2026-06-17 10:56:25'),
(38, '9011', '2026-06-17 16:26:26', 'UNKNOWN', 'Pending', 'FQQ2254700186', 1, '2026-06-17 10:56:29'),
(39, '1001', '2026-06-18 09:00:00', 'CHECK_IN', 'Redundant', 'UNKNOWN_DEVICE', 1, '2026-06-18 05:32:21'),
(40, '1001', '2026-06-18 08:35:00', 'CHECK_IN', 'Clock In', 'SN-BRANCH-A', 1, '2026-06-18 05:41:31'),
(42, '1001', '2026-06-18 18:30:00', 'CHECK_OUT', 'Clock Out', 'SN-BRANCH-A', 1, '2026-06-18 05:41:31'),
(46, '9999', '2026-06-18 10:50:15', 'UNKNOWN', 'Clock In', 'FQQ2254700186', 1, '2026-06-18 06:26:25'),
(47, '9011', '2026-06-18 10:50:17', 'UNKNOWN', 'Clock In', 'FQQ2254700186', 1, '2026-06-18 06:26:25'),
(48, '1050', '2026-06-18 11:06:59', 'UNKNOWN', 'Clock In', 'FQQ2254700186', 1, '2026-06-18 06:26:25'),
(49, '1050', '2026-06-18 11:10:58', 'UNKNOWN', 'Redundant', 'FQQ2254700186', 1, '2026-06-18 06:26:25'),
(50, '1050', '2026-06-18 11:12:45', 'UNKNOWN', 'Redundant', 'FQQ2254700186', 1, '2026-06-18 06:26:25'),
(51, '1050', '2026-06-18 11:16:52', 'UNKNOWN', 'Redundant', 'FQQ2254700186', 1, '2026-06-18 06:26:25'),
(52, '1050', '2026-06-18 11:18:04', 'UNKNOWN', 'Redundant', 'FQQ2254700186', 1, '2026-06-18 06:26:25'),
(53, '1050', '2026-06-18 11:20:22', 'UNKNOWN', 'Redundant', 'FQQ2254700186', 1, '2026-06-18 06:26:25'),
(54, '1050', '2026-06-18 11:32:48', 'UNKNOWN', 'Redundant', 'FQQ2254700186', 1, '2026-06-18 06:26:25'),
(55, '1050', '2026-06-18 11:34:22', 'UNKNOWN', 'Redundant', 'FQQ2254700186', 1, '2026-06-18 06:26:25'),
(56, '1050', '2026-06-18 11:35:25', 'UNKNOWN', 'Redundant', 'FQQ2254700186', 1, '2026-06-18 06:26:25'),
(57, '1050', '2026-06-18 11:41:09', 'UNKNOWN', 'Redundant', 'FQQ2254700186', 1, '2026-06-18 06:26:25'),
(58, '1050', '2026-06-18 11:52:02', 'UNKNOWN', 'Redundant', 'FQQ2254700186', 1, '2026-06-18 06:26:25'),
(59, '1050', '2026-06-18 11:55:59', 'UNKNOWN', 'Redundant', 'FQQ2254700186', 1, '2026-06-18 06:26:25'),
(60, '1050', '2026-06-18 11:57:40', 'UNKNOWN', 'Redundant', 'FQQ2254700186', 1, '2026-06-18 06:27:42'),
(61, '1050', '2026-06-18 12:22:11', 'UNKNOWN', 'Clock Out', 'FQQ2254700186', 1, '2026-06-18 06:52:13'),
(62, '1050', '2026-06-18 12:39:45', 'UNKNOWN', 'Redundant', 'FQQ2254700186', 1, '2026-06-18 11:19:29'),
(63, '9999', '2026-06-18 16:48:48', 'UNKNOWN', 'Clock Out', 'FQQ2254700186', 1, '2026-06-18 11:19:29'),
(64, '9011', '2026-06-18 16:49:34', 'UNKNOWN', 'Clock Out', 'FQQ2254700186', 1, '2026-06-18 11:19:36'),
(65, '1050', '2026-06-18 16:51:49', 'UNKNOWN', 'Redundant', 'FQQ2254700186', 1, '2026-06-18 11:21:50');

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
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`id`, `name`, `address`, `phone`, `email`, `status`, `created_at`, `updated_at`) VALUES
(1, 'lushanth vasanthakumar', '1\r\nbatticalo', '+94770227359', 'vlushanth@gmail.com', 'Active', '2026-06-02 14:02:34', '2026-06-18 08:18:22');

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
  `department` varchar(50) DEFAULT NULL,
  `designation` varchar(50) DEFAULT NULL,
  `status` enum('Active','On Leave','Terminated') DEFAULT 'Active',
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

INSERT INTO `employees` (`id`, `employee_code`, `first_name`, `last_name`, `email`, `phone`, `department`, `designation`, `status`, `biometric_user_id`, `hire_date`, `created_at`, `updated_at`, `date_of_birth`, `gender`, `address`, `branch_id`, `employment_type`, `base_salary`, `profile_photo`, `shift_id`, `attendance_policy_id`) VALUES
(1, 'EMP-001', 'John', 'Doe', 'john@example.com', '', 'IT', 'Software Engineer', 'Active', '1001', '2026-06-22', '2026-06-02 10:56:08', '2026-06-22 07:27:19', NULL, NULL, '', NULL, 'Full-time', 25000.00, NULL, 1, 2),
(2, 'EMP-042', 'Sarah', 'Smith', 'sarah@example.com', '', 'HR', 'HR Manager', 'Active', '1002', '2026-06-22', '2026-06-02 10:56:08', '2026-06-22 07:27:47', NULL, NULL, '', NULL, 'Full-time', 75000.00, NULL, 1, 2),
(3, 'EMP-015', 'Mike', 'Johnson', 'mike@example.com', '', 'Sales', 'Sales Executive', 'Active', '1003', '2026-06-22', '2026-06-02 10:56:08', '2026-06-22 07:28:04', NULL, NULL, '', NULL, 'Full-time', 40000.00, NULL, 1, 2),
(4, '10001', 'lushanth', 'vasanthakumar', 'vlushanth@gmail.com', '0770227359', 'IT', '', 'Active', '9999', '2026-06-02', '2026-06-02 11:05:41', '2026-06-22 07:27:06', NULL, NULL, '', NULL, 'Full-time', 70000.00, NULL, 1, 1),
(5, 'EMP-005', 'lushanth', 'vasanthakumar', 'sssvlushanth@gmail.com', '0770227359', 'IT', '', 'Active', '9011', '2026-06-02', '2026-06-02 14:54:22', '2026-06-22 07:26:55', '2026-04-30', 'Male', '1\r\nbatticaloa', 1, 'Full-time', 50000.00, NULL, 1, 1),
(8, 'EMP-006', 'test user', 'vasanthakumar', 'vlushsasanth@gmail.com', '0770227359', 'IT', '', 'Active', NULL, '2026-06-02', '2026-06-02 15:10:19', '2026-06-18 10:28:25', '2026-06-25', 'Female', '1\r\nbatticalo', NULL, 'Full-time', 0.00, NULL, 1, 1),
(9, 'EMP-009', 'M', 'SK', 'msk@gmail.com', '0756546038', 'IT', 'SE', 'Active', '1050', '2026-05-18', '2026-06-17 10:51:52', '2026-06-18 06:51:38', '2026-08-18', 'Male', 'Kattankudy', 1, 'Full-time', 30000.00, NULL, 1, 1);

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
(1, 5, '', '', '', '', '', '', '2026-06-02 14:54:22', '2026-06-02 14:54:22'),
(2, 8, '', '', '', '', '', '', '2026-06-02 15:10:19', '2026-06-02 15:10:19'),
(3, 9, 'ABC', 'MSK', '108234', '345', '7865', '122324', '2026-06-17 10:51:52', '2026-06-17 10:51:52'),
(4, 4, '', '', '', '', '', '', '2026-06-22 07:27:06', '2026-06-22 07:27:06'),
(5, 1, '', '', '', '', '', '', '2026-06-22 07:27:19', '2026-06-22 07:27:19'),
(6, 2, '', '', '', '', '', '', '2026-06-22 07:27:47', '2026-06-22 07:27:47'),
(7, 3, '', '', '', '', '', '', '2026-06-22 07:28:04', '2026-06-22 07:28:04');

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
(16, 'Id Ul-Alha', '2026-06-27', '2026-06-27', 'Religious', 'Imported public holiday for LK.', 0, 1, 0, 1, '2026-06-18 10:43:16'),
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

--
-- Dumping data for table `leave_applications`
--

INSERT INTO `leave_applications` (`id`, `employee_id`, `leave_type_id`, `covering_employee_id`, `start_date`, `end_date`, `total_days`, `reason`, `status`, `approved_by`, `created_at`, `updated_at`) VALUES
(1, 5, 2, NULL, '2026-06-24', '2026-06-24', 1.00, 'Sick leave', 'Approved', 1, '2026-06-22 07:01:52', '2026-06-22 07:03:22');

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

--
-- Dumping data for table `leave_balances`
--

INSERT INTO `leave_balances` (`id`, `employee_id`, `leave_type_id`, `leave_policy_id`, `year`, `allocated_days`, `used_days`, `carried_forward`, `manual_adjustment`, `adjustment_reason`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 2026, 14.00, 2.00, 0.00, 0.00, NULL, '2026-06-02 14:19:49', '2026-06-18 10:19:13'),
(2, 1, 2, 2, 2026, 7.00, 1.00, 0.00, 0.00, NULL, '2026-06-02 14:19:49', '2026-06-02 14:19:49'),
(3, 2, 1, 1, 2026, 14.00, 0.00, 0.00, 0.00, NULL, '2026-06-02 14:19:49', '2026-06-18 10:19:13'),
(7, 2, 2, 2, 2026, 7.00, 0.00, 0.00, 0.00, NULL, '2026-06-18 08:27:33', '2026-06-18 08:27:33'),
(8, 3, 1, 1, 2026, 14.00, 0.00, 0.00, 0.00, NULL, '2026-06-18 08:27:33', '2026-06-18 10:19:13'),
(9, 3, 2, 2, 2026, 7.00, 0.00, 0.00, 0.00, NULL, '2026-06-18 08:27:33', '2026-06-18 08:27:33'),
(10, 4, 1, 1, 2026, 14.00, 0.00, 0.00, 0.00, NULL, '2026-06-18 08:27:33', '2026-06-18 10:19:13'),
(11, 4, 2, 2, 2026, 7.00, 0.00, 0.00, 0.00, NULL, '2026-06-18 08:27:33', '2026-06-18 08:27:33'),
(12, 5, 1, 1, 2026, 14.00, 0.00, 0.00, 0.00, NULL, '2026-06-18 08:27:33', '2026-06-18 10:19:13'),
(13, 5, 2, 2, 2026, 7.00, 1.00, 0.00, 0.00, NULL, '2026-06-18 08:27:33', '2026-06-22 07:03:22'),
(14, 8, 1, 1, 2026, 14.00, 0.00, 0.00, 0.00, NULL, '2026-06-18 08:27:33', '2026-06-18 10:19:13'),
(15, 8, 2, 2, 2026, 7.00, 0.00, 0.00, 0.00, NULL, '2026-06-18 08:27:33', '2026-06-18 08:27:33'),
(16, 9, 1, 1, 2026, 14.00, 0.00, 0.00, 0.00, NULL, '2026-06-18 08:27:33', '2026-06-18 10:19:13'),
(17, 9, 2, 2, 2026, 7.00, 0.00, 0.00, 0.00, NULL, '2026-06-18 08:27:33', '2026-06-18 08:27:33');

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
  `min_days_per_application` int(11) DEFAULT 1,
  `max_days_per_application` int(11) DEFAULT 365,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_policies`
--

INSERT INTO `leave_policies` (`id`, `name`, `description`, `leave_type_id`, `accrual_type`, `accrual_rate`, `carry_forward_limit`, `min_days_per_application`, `max_days_per_application`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Standard Annual Policy', 'Base annual leave rules.', 1, 'Monthly', 1.16, 5, 1, 365, 'Active', '2026-06-02 14:19:49', '2026-06-02 14:19:49'),
(2, 'Standard Sick Policy', 'Base sick leave rules.', 2, 'Yearly', 7.00, 0, 1, 365, 'Active', '2026-06-02 14:19:49', '2026-06-02 14:19:49');

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

--
-- Dumping data for table `leave_requests`
--

INSERT INTO `leave_requests` (`id`, `employee_id`, `leave_type_id`, `start_date`, `end_date`, `reason`, `status`, `created_at`) VALUES
(1, 3, 1, '2026-06-03', '2026-06-05', 'Family vacation', 'Pending', '2026-06-02 14:13:29'),
(2, 1, 2, '2026-05-28', '2026-05-29', 'Flu', 'Approved', '2026-06-02 14:13:29');

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
(2, 'Sick Leave', 'Medical reasons.', 7, 1, '#ef4444', 'Active', '2026-06-02 14:13:29'),
(3, 'Maternity Leave', 'Statutory.', 90, 1, '#ec4899', 'Active', '2026-06-02 14:13:29'),
(4, 'Unpaid Leave', 'No pay.', 30, 0, '#6b7280', 'Active', '2026-06-02 14:13:29');

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

--
-- Dumping data for table `payroll`
--

INSERT INTO `payroll` (`id`, `employee_id`, `month`, `base_salary`, `allowances`, `deductions`, `net_salary`, `status`, `created_at`) VALUES
(1, 1, '2026-05', 6000.00, 500.00, 150.00, 6350.00, 'Paid', '2026-06-02 10:56:08'),
(2, 3, '2026-05', 5000.00, 400.00, 100.00, 5300.00, 'Paid', '2026-06-02 10:56:08'),
(3, 1, '2026-06', 5000.00, 0.00, 0.00, 5000.00, 'Draft', '2026-06-02 11:05:13'),
(4, 2, '2026-06', 5000.00, 0.00, 0.00, 5000.00, 'Draft', '2026-06-02 11:05:13'),
(5, 3, '2026-06', 5000.00, 0.00, 0.00, 5000.00, 'Draft', '2026-06-02 11:05:13'),
(9, 4, '2026-06', 5000.00, 0.00, 0.00, 5000.00, 'Draft', '2026-06-02 13:43:34');

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
  `net_salary` decimal(10,2) DEFAULT 0.00,
  `status` enum('Draft','Finalized','Paid') DEFAULT 'Draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll_records`
--

INSERT INTO `payroll_records` (`id`, `employee_id`, `payroll_month`, `base_salary`, `overtime_hours`, `overtime_amount`, `deductions`, `unpaid_days`, `net_salary`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, '2026-06', 25000.00, 1.50, 213.07, 1136.36, 1.00, 24076.71, 'Finalized', '2026-06-03 02:58:19', '2026-06-22 07:29:02'),
(2, 2, '2026-06', 75000.00, 0.00, 0.00, 3409.09, 1.00, 71590.91, 'Finalized', '2026-06-03 02:58:19', '2026-06-22 07:29:02'),
(3, 3, '2026-06', 40000.00, 0.00, 0.00, 1818.18, 1.00, 38181.82, 'Finalized', '2026-06-03 02:58:19', '2026-06-22 07:29:02'),
(4, 4, '2026-06', 70000.00, 0.00, 0.00, 31818.18, 10.00, 38181.82, 'Finalized', '2026-06-03 02:58:19', '2026-06-22 07:29:02'),
(5, 5, '2026-06', 50000.00, 0.00, 0.00, 20454.55, 9.00, 29545.45, 'Finalized', '2026-06-03 02:58:19', '2026-06-22 07:29:02'),
(6, 8, '2026-06', 0.00, 0.00, 0.00, 0.00, 15.00, 0.00, 'Finalized', '2026-06-03 02:58:19', '2026-06-22 07:29:02'),
(13, 9, '2026-06', 30000.00, 0.00, 0.00, 20454.55, 15.00, 9545.45, 'Finalized', '2026-06-18 05:21:47', '2026-06-22 07:29:02'),
(28, 1, '2026-05', 25000.00, 0.00, 0.00, 0.00, 0.00, 25000.00, 'Finalized', '2026-06-18 09:24:27', '2026-06-22 07:30:29'),
(29, 2, '2026-05', 75000.00, 0.00, 0.00, 0.00, 0.00, 75000.00, 'Finalized', '2026-06-18 09:24:27', '2026-06-22 07:30:29'),
(30, 3, '2026-05', 40000.00, 0.00, 0.00, 0.00, 0.00, 40000.00, 'Finalized', '2026-06-18 09:24:27', '2026-06-22 07:30:29'),
(31, 4, '2026-05', 70000.00, 0.00, 0.00, 0.00, 0.00, 70000.00, 'Finalized', '2026-06-18 09:24:27', '2026-06-22 07:30:29'),
(32, 5, '2026-05', 50000.00, 0.00, 0.00, 0.00, 0.00, 50000.00, 'Finalized', '2026-06-18 09:24:27', '2026-06-22 07:30:29'),
(33, 8, '2026-05', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Finalized', '2026-06-18 09:24:27', '2026-06-18 09:24:27'),
(34, 9, '2026-05', 30000.00, 0.00, 0.00, 13636.36, 10.00, 16363.64, 'Finalized', '2026-06-18 09:24:27', '2026-06-22 07:30:29');

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
(1, 'Standard Day Shift', 'Regular 9-to-5 working hours.', '09:00:00', '17:00:00', 15, 0, 'Active', '2026-06-02 14:22:03');

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
('grace_period_mins', '15', '2026-06-02 10:58:47'),
('holiday_country', 'LK', '2026-06-18 10:33:07'),
('late_deduction_amount', '50.00', '2026-06-02 10:58:47'),
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

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `role`, `status`, `language`, `department`, `employee_id`, `created_at`) VALUES
(1, 'admin', '$2y$10$9XzW5V5zMpwPN4HhfoFZOeARlP0qGgyhLSgxrXqIHEsbzbMTSkJUa', 'System Administrator', 'admin', 'Active', 'English', 'IT', 9, '2026-06-02 10:56:08'),
(2, 'abcd@gmail.com', '$2y$10$4IRGB/IBjpNI5WdezCl48eQk/re9GbwDPDd6jm7V5uBhOPJaqm2Z.', 'Dev', 'employee', 'Active', 'English', 'IT', 5, '2026-06-22 06:56:26');

--
-- Indexes for dumped tables
--

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
-- AUTO_INCREMENT for table `attendance_policies`
--
ALTER TABLE `attendance_policies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `attendance_records`
--
ALTER TABLE `attendance_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=170;

--
-- AUTO_INCREMENT for table `attendance_regularizations`
--
ALTER TABLE `attendance_regularizations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=153;

--
-- AUTO_INCREMENT for table `biometric_punches`
--
ALTER TABLE `biometric_punches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `employee_bank_details`
--
ALTER TABLE `employee_bank_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `leave_applications`
--
ALTER TABLE `leave_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `leave_balances`
--
ALTER TABLE `leave_balances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `leave_policies`
--
ALTER TABLE `leave_policies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `leave_types`
--
ALTER TABLE `leave_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `meetings`
--
ALTER TABLE `meetings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll`
--
ALTER TABLE `payroll`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `payroll_records`
--
ALTER TABLE `payroll_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `shifts`
--
ALTER TABLE `shifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

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
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
