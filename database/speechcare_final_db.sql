-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 07, 2026 at 10:32 AM
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
-- Database: `speechcare_final_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `full_name`, `email`, `password`, `created_at`, `updated_at`) VALUES
(1, 'Clinic Admin', 'admin@speechcare.com', '$2y$12$.9YxPR6xknwZsiVDuf5MreyltiPGBOWyX1knrwXmO8C7.bZjKQv2K', '2026-05-20 12:48:07', '2026-05-20 13:27:00');

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `appointment_id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `child_id` int(11) NOT NULL,
  `therapist_id` int(11) NOT NULL,
  `slot_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` enum('pending','approved','rejected','completed','needs_rescheduling') DEFAULT 'pending',
  `parent_message` text DEFAULT NULL,
  `admin_note` text DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `reschedule_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `parent_24hr_reminder_sent` tinyint(1) DEFAULT 0,
  `therapist_24hr_reminder_sent` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`appointment_id`, `parent_id`, `child_id`, `therapist_id`, `slot_id`, `appointment_date`, `start_time`, `end_time`, `status`, `parent_message`, `admin_note`, `rejection_reason`, `reschedule_reason`, `created_at`, `updated_at`, `parent_24hr_reminder_sent`, `therapist_24hr_reminder_sent`) VALUES
(1, 1, 1, 1, 1, '2026-05-25', '09:00:00', '10:00:00', 'completed', '', NULL, NULL, NULL, '2026-05-20 14:05:19', '2026-05-20 14:38:05', 0, 0),
(2, 1, 1, 1, 7, '2026-06-15', '09:00:00', '10:00:00', '', '', 'rrr', NULL, '', '2026-05-20 14:56:22', '2026-06-21 19:53:59', 1, 1),
(3, 1, 1, 3, 13, '2026-06-16', '09:00:00', '09:30:00', '', '', 'inevitable reason', NULL, NULL, '2026-06-09 18:08:46', '2026-06-13 04:36:20', 0, 0),
(4, 1, 1, 3, 13, '2026-06-16', '09:00:00', '09:30:00', 'rejected', '', 'for some personal reasons', 'for some personal reasons', NULL, '2026-06-13 04:37:35', '2026-06-13 04:38:43', 0, 0),
(5, 1, 1, 3, 62, '2026-07-14', '11:00:00', '11:30:00', 'approved', '', 'Therapist is unavailable due to approved leave. Please reschedule this appointment.', NULL, '', '2026-06-13 08:27:56', '2026-07-11 04:44:09', 0, 0),
(6, 1, 1, 3, 35, '2026-06-29', '15:00:00', '16:00:00', 'approved', '', 'Therapist is unavailable due to approved leave. Please reschedule this appointment.', NULL, '', '2026-06-13 11:47:51', '2026-06-14 12:34:51', 0, 0),
(7, 1, 1, 3, 22, '2026-06-30', '09:30:00', '10:00:00', 'approved', '', NULL, NULL, NULL, '2026-06-13 11:51:47', '2026-06-13 13:11:19', 0, 0),
(8, 1, 1, 3, 37, '2026-06-29', '17:00:00', '18:00:00', '', '', 'change another slot', NULL, '', '2026-06-13 13:08:25', '2026-06-14 11:54:41', 0, 0),
(9, 1, 1, 3, 36, '2026-06-29', '16:00:00', '17:00:00', '', '', 'Cancelled by parent: no', NULL, '', '2026-06-13 14:09:28', '2026-06-14 11:53:14', 0, 0),
(10, 1, 1, 3, 35, '2026-06-29', '15:00:00', '16:00:00', '', '', 'Cancelled by parent: we are unable to attend this session', NULL, '', '2026-06-13 14:57:38', '2026-06-14 11:40:03', 0, 0),
(11, 1, 1, 3, 44, '2026-06-15', '09:00:00', '10:00:00', '', '', 'book again', NULL, NULL, '2026-06-14 13:36:09', '2026-06-14 18:12:43', 1, 1),
(12, 1, 1, 3, 45, '2026-06-15', '10:00:00', '11:00:00', 'rejected', '', 'no', 'no', NULL, '2026-06-14 13:47:22', '2026-06-14 13:48:05', 0, 0),
(13, 1, 1, 3, 45, '2026-06-15', '10:00:00', '11:00:00', 'rejected', '', 'no', 'no', NULL, '2026-06-14 14:43:28', '2026-06-14 15:08:22', 0, 0),
(14, 1, 1, 3, 45, '2026-06-15', '10:00:00', '11:00:00', '', '', 'book again', NULL, NULL, '2026-06-14 15:08:57', '2026-06-14 15:18:19', 0, 0),
(15, 1, 1, 3, 45, '2026-06-15', '10:00:00', '11:00:00', '', '', 'book again', NULL, NULL, '2026-06-14 15:23:51', '2026-06-14 15:49:54', 1, 1),
(16, 1, 1, 3, 45, '2026-06-15', '10:00:00', '11:00:00', '', '', 'no', NULL, NULL, '2026-06-14 15:53:49', '2026-06-14 17:03:59', 1, 1),
(17, 1, 1, 3, 45, '2026-06-15', '10:00:00', '11:00:00', 'completed', '', NULL, NULL, NULL, '2026-06-14 17:04:43', '2026-06-15 14:00:15', 1, 1),
(18, 1, 1, 3, 44, '2026-06-15', '09:00:00', '10:00:00', 'completed', '', NULL, NULL, NULL, '2026-06-14 18:19:02', '2026-06-15 12:52:16', 1, 1),
(19, 1, 1, 3, 46, '2026-06-22', '09:00:00', '10:00:00', '', '', 'book another slot', NULL, NULL, '2026-06-15 09:13:18', '2026-06-15 18:02:49', 0, 0),
(20, 1, 1, 3, 54, '2026-06-16', '11:00:00', '11:30:00', 'approved', '', NULL, NULL, NULL, '2026-06-15 18:54:32', '2026-06-15 19:11:20', 1, 1),
(21, 1, 1, 3, 46, '2026-06-22', '09:00:00', '10:00:00', 'completed', '', NULL, NULL, NULL, '2026-06-21 20:25:22', '2026-06-22 07:26:47', 0, 0),
(22, 1, 1, 3, 64, '2026-06-22', '12:00:00', '13:00:00', 'approved', '', NULL, NULL, NULL, '2026-06-21 20:31:21', '2026-06-21 20:31:51', 0, 0),
(23, 1, 1, 3, 65, '2026-06-22', '13:00:00', '14:00:00', 'approved', '', NULL, NULL, NULL, '2026-06-22 07:24:37', '2026-06-22 07:25:21', 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `appointment_reschedule_history`
--

CREATE TABLE `appointment_reschedule_history` (
  `reschedule_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `therapist_id` int(11) NOT NULL,
  `old_slot_id` int(11) NOT NULL,
  `new_slot_id` int(11) NOT NULL,
  `old_date` date NOT NULL,
  `old_start_time` time NOT NULL,
  `old_end_time` time NOT NULL,
  `new_date` date NOT NULL,
  `new_start_time` time NOT NULL,
  `new_end_time` time NOT NULL,
  `reason` text DEFAULT NULL,
  `rescheduled_by` int(11) NOT NULL,
  `rescheduled_by_role` enum('parent','admin') NOT NULL DEFAULT 'parent',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointment_reschedule_history`
--

INSERT INTO `appointment_reschedule_history` (`reschedule_id`, `appointment_id`, `parent_id`, `therapist_id`, `old_slot_id`, `new_slot_id`, `old_date`, `old_start_time`, `old_end_time`, `new_date`, `new_start_time`, `new_end_time`, `reason`, `rescheduled_by`, `rescheduled_by_role`, `created_at`) VALUES
(1, 2, 1, 1, 5, 7, '2026-06-08', '09:00:00', '10:00:00', '2026-06-15', '09:00:00', '10:00:00', '', 1, 'parent', '2026-06-13 14:56:57'),
(2, 10, 1, 3, 32, 35, '2026-06-22', '15:00:00', '16:00:00', '2026-06-29', '15:00:00', '16:00:00', '', 1, 'parent', '2026-06-13 15:02:16'),
(3, 9, 1, 3, 29, 36, '2026-06-15', '15:00:00', '16:00:00', '2026-06-29', '16:00:00', '17:00:00', '', 1, 'parent', '2026-06-13 15:13:00'),
(4, 8, 1, 3, 23, 37, '2026-06-30', '10:00:00', '10:30:00', '2026-06-29', '17:00:00', '18:00:00', '', 1, 'parent', '2026-06-13 15:18:19'),
(5, 6, 1, 3, 25, 35, '2026-07-07', '09:00:00', '09:30:00', '2026-06-29', '15:00:00', '16:00:00', '', 1, 'parent', '2026-06-14 12:34:51'),
(6, 5, 1, 3, 21, 62, '2026-06-30', '09:00:00', '09:30:00', '2026-07-14', '11:00:00', '11:30:00', '', 1, 'parent', '2026-07-11 04:44:09');

-- --------------------------------------------------------

--
-- Table structure for table `children`
--

CREATE TABLE `children` (
  `child_id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `child_name` varchar(150) NOT NULL,
  `age` int(11) NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `speech_concern` text NOT NULL,
  `previous_therapy_history` text DEFAULT NULL,
  `additional_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `children`
--

INSERT INTO `children` (`child_id`, `parent_id`, `child_name`, `age`, `gender`, `speech_concern`, `previous_therapy_history`, `additional_notes`, `created_at`, `updated_at`) VALUES
(1, 1, 'Malini', 6, 'female', 'speech delay', '', '', '2026-05-20 13:52:11', '2026-05-20 13:52:11'),
(2, 2, 'sha', 3, 'female', 'difficult to pronounce some words', '', '', '2026-05-23 08:19:40', '2026-05-23 08:19:40');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_type` enum('parent','therapist','admin') NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `status` enum('unread','read') DEFAULT 'unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_type`, `user_id`, `title`, `message`, `status`, `created_at`) VALUES
(1, 'admin', 1, 'New Appointment Request', 'Amara submitted a new appointment request for Malini.', 'read', '2026-05-20 14:05:19'),
(2, 'parent', 1, 'Appointment Request Submitted', 'Your appointment request has been submitted and is waiting for admin approval.', 'unread', '2026-05-20 14:05:19'),
(15, 'parent', 1, 'Appointment Approved', 'Your appointment with Ms. Amali has been approved.', 'unread', '2026-05-20 14:31:04'),
(16, 'therapist', 1, 'New Approved Appointment', 'You have a new approved appointment with Malini.', 'unread', '2026-05-20 14:31:04'),
(17, 'parent', 1, 'Session Completed', 'Your child\'s therapy session has been completed. Session notes are now available.', 'unread', '2026-05-20 14:38:05'),
(18, 'admin', 1, 'New Therapist Leave Request', 'Ms. Amali submitted a Full Day leave request for Jun 01, 2026.', 'read', '2026-05-20 14:48:00'),
(19, 'therapist', 1, 'Leave Request Submitted', 'Your leave request has been submitted and is waiting for admin approval.', 'unread', '2026-05-20 14:48:00'),
(20, 'therapist', 1, 'Leave Request Approved', 'Your leave request for Jun 01, 2026 has been approved.', 'unread', '2026-05-20 14:51:39'),
(21, 'admin', 1, 'New Therapist Leave Request', 'Ms. Amali submitted a Full Day leave request for May 25, 2026.', 'read', '2026-05-20 14:54:21'),
(22, 'therapist', 1, 'Leave Request Submitted', 'Your leave request has been submitted and is waiting for admin approval.', 'unread', '2026-05-20 14:54:21'),
(23, 'therapist', 1, 'Leave Request Approved', 'Your leave request for May 25, 2026 has been approved.', 'unread', '2026-05-20 14:54:40'),
(24, 'admin', 1, 'New Appointment Request', 'Amara submitted a new appointment request for Malini.', 'read', '2026-05-20 14:56:22'),
(25, 'parent', 1, 'Appointment Request Submitted', 'Your appointment request has been submitted and is waiting for admin approval.', 'unread', '2026-05-20 14:56:22'),
(26, 'parent', 1, 'Appointment Approved', 'Your appointment with Ms. Amali has been approved.', 'unread', '2026-05-20 14:56:51'),
(27, 'therapist', 1, 'New Approved Appointment', 'You have a new approved appointment with Malini.', 'unread', '2026-05-20 14:56:51'),
(28, 'admin', 1, 'New Therapist Leave Request', 'Ms. Amali submitted a Full Day leave request for Jun 08, 2026.', 'unread', '2026-05-20 14:59:06'),
(29, 'therapist', 1, 'Leave Request Submitted', 'Your leave request has been submitted and is waiting for admin approval.', 'unread', '2026-05-20 14:59:06'),
(30, 'parent', 1, 'Appointment Needs Rescheduling', 'Your appointment for Malini on Jun 08, 2026 needs rescheduling because the therapist is unavailable.', 'unread', '2026-05-20 15:00:18'),
(31, 'therapist', 1, 'Leave Request Approved', 'Your leave request for Jun 08, 2026 has been approved.', 'unread', '2026-05-20 15:00:18'),
(32, 'admin', 1, 'New Appointment Request', 'Amara submitted a new appointment request for Malini.', 'unread', '2026-06-09 18:08:46'),
(33, 'parent', 1, 'Appointment Request Submitted', 'Your appointment request has been submitted and is waiting for admin approval.', 'unread', '2026-06-09 18:08:46'),
(34, 'parent', 1, 'Appointment Approved', 'Your appointment with Maryam has been approved.', 'unread', '2026-06-13 04:30:20'),
(35, 'therapist', 3, 'New Approved Appointment', 'You have a new approved appointment with Malini.', 'unread', '2026-06-13 04:30:20'),
(36, 'parent', 1, 'Appointment Cancelled', 'Your appointment was cancelled. Reason: inevitable reason', 'unread', '2026-06-13 04:36:20'),
(37, 'therapist', 3, 'Appointment Cancelled', 'An appointment with Malini was cancelled.', 'unread', '2026-06-13 04:36:20'),
(38, 'admin', 1, 'New Appointment Request', 'Amara submitted a new appointment request for Malini.', 'unread', '2026-06-13 04:37:35'),
(39, 'parent', 1, 'Appointment Request Submitted', 'Your appointment request has been submitted and is waiting for admin approval.', 'unread', '2026-06-13 04:37:35'),
(40, 'parent', 1, 'Appointment Rejected', 'Your appointment request was rejected. Reason: for some personal reasons', 'unread', '2026-06-13 04:38:43'),
(41, 'admin', 1, 'New Therapist Leave Request', 'Maryam submitted a Full Day leave request for Jun 16, 2026.', 'unread', '2026-06-13 07:20:22'),
(42, 'therapist', 3, 'Leave Request Submitted', 'Your leave request has been submitted and is waiting for admin approval.', 'unread', '2026-06-13 07:20:22'),
(43, 'therapist', 3, 'Leave Request Approved', 'Your leave request for Jun 16, 2026 has been approved.', 'unread', '2026-06-13 07:22:46'),
(44, 'admin', 1, 'New Therapist Leave Request', 'Maryam submitted a Full Day leave request for Jun 23, 2026.', 'unread', '2026-06-13 07:29:45'),
(45, 'therapist', 3, 'Leave Request Submitted', 'Your leave request has been submitted and is waiting for admin approval.', 'unread', '2026-06-13 07:29:45'),
(46, 'therapist', 3, 'Leave Request Rejected', 'Your leave request for Jun 23, 2026 was rejected. Reason: nooo', 'unread', '2026-06-13 07:31:14'),
(47, 'admin', 1, 'New Therapist Leave Request', 'Maryam submitted a Full Day leave request for Jun 23, 2026.', 'unread', '2026-06-13 07:46:09'),
(48, 'therapist', 3, 'Leave Request Submitted', 'Your leave request has been submitted and is waiting for admin approval.', 'unread', '2026-06-13 07:46:09'),
(49, 'therapist', 3, 'Leave Request Rejected', 'Your leave request for Jun 23, 2026 was rejected. Reason: noo', 'unread', '2026-06-13 07:46:49'),
(50, 'admin', 1, 'New Therapist Leave Request', 'Maryam submitted a Full Day leave request for Jun 23, 2026.', 'unread', '2026-06-13 07:51:09'),
(51, 'therapist', 3, 'Leave Request Submitted', 'Your leave request has been submitted and is waiting for admin approval.', 'unread', '2026-06-13 07:51:09'),
(52, 'therapist', 3, 'Leave Request Rejected', 'Your leave request for Jun 23, 2026 was rejected. Reason: no', 'unread', '2026-06-13 07:51:50'),
(53, 'admin', 1, 'New Therapist Leave Request', 'Maryam submitted a Full Day leave request for Jun 23, 2026.', 'unread', '2026-06-13 07:55:51'),
(54, 'therapist', 3, 'Leave Request Submitted', 'Your leave request has been submitted and is waiting for admin approval.', 'unread', '2026-06-13 07:55:51'),
(55, 'therapist', 3, 'Leave Request Rejected', 'Your leave request for Jun 23, 2026 was rejected. Reason: bjuu', 'unread', '2026-06-13 07:56:19'),
(56, 'admin', 1, 'New Therapist Leave Request', 'Maryam submitted a Full Day leave request for Jun 23, 2026.', 'unread', '2026-06-13 08:09:48'),
(57, 'therapist', 3, 'Leave Request Submitted', 'Your leave request has been submitted and is waiting for admin approval.', 'unread', '2026-06-13 08:09:48'),
(58, 'therapist', 3, 'Leave Request Rejected', 'Your leave request for Jun 23, 2026 was rejected. Reason: no', 'unread', '2026-06-13 08:10:18'),
(59, 'admin', 1, 'New Therapist Leave Request', 'Maryam submitted a Full Day leave request for Jun 23, 2026.', 'unread', '2026-06-13 08:18:20'),
(60, 'therapist', 3, 'Leave Request Submitted', 'Your leave request has been submitted and is waiting for admin approval.', 'unread', '2026-06-13 08:18:20'),
(61, 'therapist', 3, 'Leave Request Rejected', 'Your leave request for Jun 23, 2026 was rejected. Reason: no', 'unread', '2026-06-13 08:18:49'),
(62, 'admin', 1, 'New Therapist Leave Request', 'Maryam submitted a Full Day leave request for Jun 23, 2026.', 'unread', '2026-06-13 08:19:20'),
(63, 'therapist', 3, 'Leave Request Submitted', 'Your leave request has been submitted and is waiting for admin approval.', 'unread', '2026-06-13 08:19:20'),
(64, 'therapist', 3, 'Leave Request Approved', 'Your leave request for Jun 23, 2026 has been approved.', 'unread', '2026-06-13 08:19:46'),
(65, 'admin', 1, 'New Appointment Request', 'Amara submitted a new appointment request for Malini.', 'unread', '2026-06-13 08:27:56'),
(66, 'parent', 1, 'Appointment Request Submitted', 'Your appointment request has been submitted and is waiting for admin approval.', 'unread', '2026-06-13 08:27:56'),
(67, 'admin', 1, 'New Appointment Request', 'Amara submitted a new appointment request for Malini.', 'unread', '2026-06-13 11:47:51'),
(68, 'parent', 1, 'Appointment Request Submitted', 'Your appointment request has been submitted and is waiting for admin approval.', 'unread', '2026-06-13 11:47:51'),
(69, 'admin', 1, 'New Therapist Leave Request', 'Maryam submitted a Full Day leave request for Jul 07, 2026.', 'unread', '2026-06-13 11:48:37'),
(70, 'therapist', 3, 'Leave Request Submitted', 'Your leave request has been submitted and is waiting for admin approval.', 'unread', '2026-06-13 11:48:37'),
(71, 'parent', 1, 'Appointment Approved', 'Your appointment with Maryam has been approved.', 'unread', '2026-06-13 11:49:01'),
(72, 'therapist', 3, 'New Approved Appointment', 'You have a new approved appointment with Malini.', 'unread', '2026-06-13 11:49:01'),
(73, 'parent', 1, 'Appointment Needs Rescheduling', 'Your appointment for Malini on Jul 07, 2026 needs rescheduling because the therapist is unavailable.', 'unread', '2026-06-13 11:50:04'),
(74, 'therapist', 3, 'Leave Request Approved', 'Your leave request for Jul 07, 2026 has been approved.', 'unread', '2026-06-13 11:50:04'),
(75, 'admin', 1, 'New Appointment Request', 'Amara submitted a new appointment request for Malini.', 'unread', '2026-06-13 11:51:47'),
(76, 'parent', 1, 'Appointment Request Submitted', 'Your appointment request has been submitted and is waiting for admin approval.', 'unread', '2026-06-13 11:51:47'),
(77, 'parent', 1, 'Appointment Approved', 'Your appointment with Maryam has been approved.', 'unread', '2026-06-13 12:26:16'),
(78, 'therapist', 3, 'New Approved Appointment', 'You have a new approved appointment with Malini.', 'unread', '2026-06-13 12:26:16'),
(79, 'admin', 1, 'New Therapist Leave Request', 'Maryam submitted a Partial Day leave request for Jun 30, 2026.', 'unread', '2026-06-13 12:27:32'),
(80, 'therapist', 3, 'Leave Request Submitted', 'Your leave request has been submitted and is waiting for admin approval.', 'unread', '2026-06-13 12:27:32'),
(83, 'therapist', 3, 'Leave Request Approved', 'Your leave request for Jun 30, 2026 has been approved.', 'unread', '2026-06-13 12:28:45'),
(84, 'admin', 1, 'New Therapist Leave Request', 'Maryam submitted a Partial Day leave request for Jun 30, 2026.', 'unread', '2026-06-13 12:40:02'),
(85, 'therapist', 3, 'Leave Request Submitted', 'Your leave request has been submitted and is waiting for admin approval.', 'unread', '2026-06-13 12:40:02'),
(86, 'therapist', 3, 'Leave Request Rejected', 'Your leave request for Jun 30, 2026 was rejected. Reason: no', 'unread', '2026-06-13 12:40:28'),
(87, 'admin', 1, 'New Appointment Request', 'Amara submitted a new appointment request for Malini.', 'unread', '2026-06-13 13:08:25'),
(88, 'parent', 1, 'Appointment Request Submitted', 'Your appointment request has been submitted and is waiting for admin approval.', 'unread', '2026-06-13 13:08:25'),
(89, 'parent', 1, 'Appointment Approved', 'Your appointment with Maryam has been approved.', 'unread', '2026-06-13 13:09:09'),
(90, 'therapist', 3, 'New Approved Appointment', 'You have a new approved appointment with Malini.', 'unread', '2026-06-13 13:09:09'),
(91, 'admin', 1, 'New Therapist Leave Request', 'Maryam submitted a Partial Day leave request for Jun 30, 2026.', 'unread', '2026-06-13 13:10:40'),
(92, 'therapist', 3, 'Leave Request Submitted', 'Your leave request has been submitted and is waiting for admin approval.', 'unread', '2026-06-13 13:10:40'),
(93, 'parent', 1, 'Appointment Approved', 'Your appointment with Maryam has been approved.', 'unread', '2026-06-13 13:11:19'),
(94, 'therapist', 3, 'New Approved Appointment', 'You have a new approved appointment with Malini.', 'unread', '2026-06-13 13:11:19'),
(97, 'therapist', 3, 'Leave Request Approved', 'Your leave request for Jun 30, 2026 has been approved.', 'unread', '2026-06-13 13:17:12'),
(98, 'admin', 1, 'New Appointment Request', 'Amara submitted a new appointment request for Malini.', 'unread', '2026-06-13 14:09:28'),
(99, 'parent', 1, 'Appointment Request Submitted', 'Your appointment request has been submitted and is waiting for admin approval.', 'unread', '2026-06-13 14:09:28'),
(100, 'parent', 1, 'Appointment Approved', 'Your appointment with Maryam has been approved.', 'unread', '2026-06-13 14:09:58'),
(101, 'therapist', 3, 'New Approved Appointment', 'You have a new approved appointment with Malini.', 'unread', '2026-06-13 14:09:58'),
(102, 'admin', 1, 'New Therapist Leave Request', 'Maryam submitted a Full Day leave request for Jun 15, 2026.', 'unread', '2026-06-13 14:10:41'),
(103, 'therapist', 3, 'Leave Request Submitted', 'Your leave request has been submitted and is waiting for admin approval.', 'unread', '2026-06-13 14:10:41'),
(104, 'parent', 1, 'Appointment Rescheduling Required', 'Your appointment on Jun 15, 2026 with therapist Maryam has been affected by an approved leave request. Please reschedule your appointment.', 'unread', '2026-06-13 14:11:37'),
(105, 'therapist', 3, 'Leave Request Approved', 'Your leave request for Jun 15, 2026 has been approved.', 'unread', '2026-06-13 14:11:37'),
(106, 'admin', 1, 'New Therapist Leave Request', 'Maryam submitted a Full Day leave request for Jun 17, 2026.', 'unread', '2026-06-13 14:40:46'),
(107, 'therapist', 3, 'Leave Request Submitted', 'Your leave request has been submitted and is waiting for admin approval.', 'unread', '2026-06-13 14:40:46'),
(108, 'therapist', 3, 'Leave Request Rejected', 'Your leave request for Jun 17, 2026 was rejected. Reason: no', 'unread', '2026-06-13 14:41:44'),
(109, 'admin', 1, 'New Therapist Leave Request', 'Maryam submitted a Full Day leave request for Jun 17, 2026.', 'unread', '2026-06-13 14:43:52'),
(110, 'therapist', 3, 'Leave Request Submitted', 'Your leave request has been submitted and is waiting for admin approval.', 'unread', '2026-06-13 14:43:52'),
(111, 'therapist', 3, 'Leave Request Rejected', 'Your leave request for Jun 17, 2026 was rejected. Reason: no', 'unread', '2026-06-13 14:44:19'),
(112, 'parent', 1, 'Appointment Rescheduled', 'Your appointment has been rescheduled successfully.', 'unread', '2026-06-13 14:56:57'),
(113, 'therapist', 1, 'Appointment Rescheduled', 'Amara rescheduled the appointment for Malini.', 'unread', '2026-06-13 14:56:57'),
(114, 'admin', 1, 'Appointment Rescheduled', 'Amara rescheduled an appointment for Malini.', 'unread', '2026-06-13 14:56:57'),
(115, 'admin', 1, 'New Appointment Request', 'Amara submitted a new appointment request for Malini.', 'unread', '2026-06-13 14:57:38'),
(116, 'parent', 1, 'Appointment Request Submitted', 'Your appointment request has been submitted and is waiting for admin approval.', 'unread', '2026-06-13 14:57:38'),
(117, 'parent', 1, 'Appointment Approved', 'Your appointment with Maryam has been approved.', 'unread', '2026-06-13 14:58:21'),
(118, 'therapist', 3, 'New Approved Appointment', 'You have a new approved appointment with Malini.', 'unread', '2026-06-13 14:58:22'),
(119, 'admin', 1, 'New Therapist Leave Request', 'Maryam submitted a Full Day leave request for Jun 22, 2026.', 'unread', '2026-06-13 14:58:56'),
(120, 'therapist', 3, 'Leave Request Submitted', 'Your leave request has been submitted and is waiting for admin approval.', 'unread', '2026-06-13 14:58:56'),
(121, 'therapist', 3, 'Leave Request Approved', 'Your leave request for Jun 22, 2026 has been approved.', 'unread', '2026-06-13 14:59:37'),
(122, 'parent', 1, 'Appointment Needs Rescheduling', 'Your appointment on Jun 22, 2026 needs to be rescheduled as the therapist will be on leave.', 'unread', '2026-06-13 14:59:37'),
(123, 'parent', 1, 'Appointment Rescheduled', 'Your appointment has been rescheduled successfully.', 'unread', '2026-06-13 15:02:16'),
(124, 'therapist', 3, 'Appointment Rescheduled', 'Amara rescheduled the appointment for Malini.', 'unread', '2026-06-13 15:02:16'),
(125, 'admin', 1, 'Appointment Rescheduled', 'Amara rescheduled an appointment for Malini.', 'unread', '2026-06-13 15:02:17'),
(126, 'parent', 1, 'Appointment Rescheduled', 'Your appointment has been rescheduled successfully.', 'unread', '2026-06-13 15:13:00'),
(127, 'therapist', 3, 'Appointment Rescheduled', 'Amara rescheduled the appointment for Malini.', 'unread', '2026-06-13 15:13:00'),
(128, 'admin', 1, 'Appointment Rescheduled', 'Amara rescheduled an appointment for Malini.', 'unread', '2026-06-13 15:13:00'),
(129, 'parent', 1, 'Appointment Rescheduled', 'Your appointment has been rescheduled successfully.', 'unread', '2026-06-13 15:18:19'),
(130, 'therapist', 3, 'Appointment Rescheduled', 'Amara rescheduled the appointment for Malini.', 'unread', '2026-06-13 15:18:19'),
(131, 'admin', 1, 'Appointment Rescheduled', 'Amara rescheduled an appointment for Malini.', 'unread', '2026-06-13 15:18:19'),
(132, 'admin', 1, 'Appointment Cancelled by Parent', 'Amara cancelled an appointment for Malini.', 'unread', '2026-06-14 11:40:03'),
(133, 'therapist', 3, 'Appointment Cancelled', 'Amara cancelled an appointment for Malini.', 'unread', '2026-06-14 11:40:03'),
(134, 'parent', 1, 'Appointment Cancelled', 'Your appointment has been cancelled successfully.', 'unread', '2026-06-14 11:40:03'),
(141, 'admin', 1, 'Appointment Cancelled by Parent', 'Amara cancelled an appointment for Malini.', 'unread', '2026-06-14 11:53:14'),
(142, 'therapist', 3, 'Appointment Cancelled', 'Amara cancelled an appointment for Malini.', 'unread', '2026-06-14 11:53:14'),
(143, 'parent', 1, 'Appointment Cancelled', 'Your appointment has been cancelled successfully.', 'unread', '2026-06-14 11:53:14'),
(144, 'parent', 1, 'Appointment Cancelled', 'Your appointment was cancelled. Reason: change another slot', 'unread', '2026-06-14 11:54:41'),
(145, 'therapist', 3, 'Appointment Cancelled', 'An appointment with Malini was cancelled.', 'unread', '2026-06-14 11:54:41'),
(146, 'parent', 1, 'Appointment Rescheduled', 'Your appointment has been rescheduled successfully.', 'unread', '2026-06-14 12:34:51'),
(147, 'therapist', 3, 'Appointment Rescheduled', 'Amara rescheduled the appointment for Malini.', 'unread', '2026-06-14 12:34:51'),
(148, 'admin', 1, 'Appointment Rescheduled', 'Amara rescheduled an appointment for Malini.', 'unread', '2026-06-14 12:34:51'),
(149, 'admin', 1, 'New Appointment Request', 'Amara submitted a new appointment request for Malini.', 'unread', '2026-06-14 13:36:09'),
(150, 'parent', 1, 'Appointment Request Submitted', 'Your appointment request has been submitted and is waiting for admin approval.', 'unread', '2026-06-14 13:36:09'),
(151, 'parent', 1, 'Appointment Approved', 'Your appointment with Maryam has been approved.', 'unread', '2026-06-14 13:38:23'),
(152, 'therapist', 3, 'New Approved Appointment', 'You have a new approved appointment with Malini.', 'unread', '2026-06-14 13:38:23'),
(153, 'admin', 1, 'New Appointment Request', 'Amara submitted a new appointment request for Malini.', 'unread', '2026-06-14 13:47:22'),
(154, 'parent', 1, 'Appointment Request Submitted', 'Your appointment request has been submitted and is waiting for admin approval.', 'unread', '2026-06-14 13:47:22'),
(155, 'parent', 1, 'Appointment Rejected', 'Your appointment request was rejected. Reason: no', 'unread', '2026-06-14 13:48:05'),
(156, 'admin', 1, 'New Appointment Request', 'Amara submitted a new appointment request for Malini.', 'unread', '2026-06-14 14:43:28'),
(157, 'parent', 1, 'Appointment Request Submitted', 'Your appointment request has been submitted and is waiting for admin approval.', 'unread', '2026-06-14 14:43:28'),
(160, 'parent', 1, 'Appointment Rejected', 'Your appointment request was rejected. Reason: no', 'unread', '2026-06-14 15:08:22'),
(161, 'admin', 1, 'New Appointment Request', 'Amara submitted a new appointment request for Malini.', 'unread', '2026-06-14 15:08:57'),
(162, 'parent', 1, 'Appointment Request Submitted', 'Your appointment request has been submitted and is waiting for admin approval.', 'unread', '2026-06-14 15:08:57'),
(163, 'parent', 1, 'Appointment Approved', 'Your appointment with Maryam has been approved.', 'unread', '2026-06-14 15:09:21'),
(164, 'therapist', 3, 'New Approved Appointment', 'You have a new approved appointment with Malini.', 'unread', '2026-06-14 15:09:21'),
(165, 'parent', 1, 'Appointment Cancelled', 'Your appointment was cancelled. Reason: book again', 'unread', '2026-06-14 15:18:19'),
(166, 'therapist', 3, 'Appointment Cancelled', 'An appointment with Malini was cancelled.', 'unread', '2026-06-14 15:18:19'),
(167, 'admin', 1, 'New Appointment Request', 'Amara submitted a new appointment request for Malini.', 'unread', '2026-06-14 15:23:51'),
(168, 'parent', 1, 'Appointment Request Submitted', 'Your appointment request has been submitted and is waiting for admin approval.', 'unread', '2026-06-14 15:23:51'),
(169, 'parent', 1, 'Appointment Approved', 'Your appointment with Maryam has been approved.', 'unread', '2026-06-14 15:24:22'),
(170, 'therapist', 3, 'New Approved Appointment', 'You have a new approved appointment with Malini.', 'unread', '2026-06-14 15:24:22'),
(171, 'parent', 1, 'Appointment Cancelled', 'Your appointment was cancelled. Reason: book again', 'unread', '2026-06-14 15:49:54'),
(172, 'therapist', 3, 'Appointment Cancelled', 'An appointment with Malini was cancelled.', 'unread', '2026-06-14 15:49:54'),
(173, 'admin', 1, 'New Appointment Request', 'Amara submitted a new appointment request for Malini.', 'unread', '2026-06-14 15:53:49'),
(174, 'parent', 1, 'Appointment Request Submitted', 'Your appointment request has been submitted and is waiting for admin approval.', 'unread', '2026-06-14 15:53:49'),
(175, 'parent', 1, 'Appointment Approved', 'Your appointment with Maryam has been approved.', 'unread', '2026-06-14 15:54:17'),
(176, 'therapist', 3, 'New Approved Appointment', 'You have a new approved appointment with Malini.', 'unread', '2026-06-14 15:54:17'),
(177, 'parent', 1, 'Appointment Cancelled', 'Your appointment was cancelled. Reason: no', 'unread', '2026-06-14 17:03:59'),
(178, 'therapist', 3, 'Appointment Cancelled', 'An appointment with Malini was cancelled.', 'unread', '2026-06-14 17:03:59'),
(179, 'admin', 1, 'New Appointment Request', 'Amara submitted a new appointment request for Malini.', 'unread', '2026-06-14 17:04:43'),
(180, 'parent', 1, 'Appointment Request Submitted', 'Your appointment request has been submitted and is waiting for admin approval.', 'unread', '2026-06-14 17:04:43'),
(181, 'parent', 1, 'Appointment Approved', 'Your appointment with Maryam has been approved.', 'unread', '2026-06-14 17:05:09'),
(182, 'therapist', 3, 'New Approved Appointment', 'You have a new approved appointment with Malini.', 'unread', '2026-06-14 17:05:09'),
(183, 'parent', 1, 'Appointment Cancelled', 'Your appointment was cancelled. Reason: book again', 'unread', '2026-06-14 18:12:43'),
(184, 'therapist', 3, 'Appointment Cancelled', 'An appointment with Malini was cancelled.', 'unread', '2026-06-14 18:12:43'),
(185, 'admin', 1, 'New Appointment Request', 'Amara submitted a new appointment request for Malini.', 'unread', '2026-06-14 18:19:02'),
(186, 'parent', 1, 'Appointment Request Submitted', 'Your appointment request has been submitted and is waiting for admin approval.', 'unread', '2026-06-14 18:19:02'),
(187, 'parent', 1, 'Appointment Approved', 'Your appointment with Maryam has been approved.', 'unread', '2026-06-14 18:20:05'),
(188, 'therapist', 3, 'New Approved Appointment', 'You have a new approved appointment with Malini.', 'unread', '2026-06-14 18:20:05'),
(189, 'admin', 1, 'New Appointment Request', 'Amara submitted a new appointment request for Malini.', 'unread', '2026-06-15 09:13:18'),
(190, 'parent', 1, 'Appointment Request Submitted', 'Your appointment request has been submitted and is waiting for admin approval.', 'unread', '2026-06-15 09:13:18'),
(191, 'parent', 1, 'Appointment Approved', 'Your appointment with Maryam has been approved.', 'unread', '2026-06-15 09:14:59'),
(192, 'therapist', 3, 'New Approved Appointment', 'You have a new approved appointment with Malini.', 'unread', '2026-06-15 09:14:59'),
(193, 'parent', 1, 'Session Completed', 'Your child\'s therapy session has been completed. Session notes are now available.', 'unread', '2026-06-15 12:52:16'),
(194, 'parent', 1, 'Session Completed', 'Your child\'s therapy session has been completed. Session notes are now available.', 'unread', '2026-06-15 14:00:15'),
(195, 'parent', 1, 'Appointment Cancelled', 'Your appointment was cancelled. Reason: book another slot', 'unread', '2026-06-15 18:02:49'),
(196, 'therapist', 3, 'Appointment Cancelled', 'An appointment with Malini was cancelled.', 'unread', '2026-06-15 18:02:49'),
(197, 'admin', 1, 'New Appointment Request', 'Amara submitted a new appointment request for Malini.', 'unread', '2026-06-15 18:54:32'),
(198, 'parent', 1, 'Appointment Request Submitted', 'Your appointment request has been submitted and is waiting for admin approval.', 'unread', '2026-06-15 18:54:32'),
(199, 'parent', 1, 'Appointment Approved', 'Your appointment with Maryam has been approved.', 'unread', '2026-06-15 18:55:07'),
(200, 'therapist', 3, 'New Approved Appointment', 'You have a new approved appointment with Malini.', 'unread', '2026-06-15 18:55:07'),
(201, 'admin', 1, 'New Therapist Leave Request', 'Maryam submitted a Full Day leave request for Jun 29, 2026.', 'unread', '2026-06-21 06:41:54'),
(202, 'therapist', 3, 'Leave Request Submitted', 'Your leave request has been submitted and is waiting for admin approval.', 'unread', '2026-06-21 06:41:54'),
(203, 'therapist', 3, 'Leave Request Rejected', 'Your leave request for Jun 29, 2026 was rejected. Reason: swed', 'unread', '2026-06-21 06:42:38'),
(204, 'parent', 1, 'Appointment Cancelled', 'Your appointment was cancelled. Reason: rrr', 'unread', '2026-06-21 19:53:59'),
(205, 'therapist', 1, 'Appointment Cancelled', 'An appointment with Malini was cancelled.', 'unread', '2026-06-21 19:53:59'),
(206, 'admin', 1, 'New Appointment Request', 'Amara submitted a new appointment request for Malini.', 'unread', '2026-06-21 20:25:22'),
(207, 'parent', 1, 'Appointment Request Submitted', 'Your appointment request has been submitted and is waiting for admin approval.', 'unread', '2026-06-21 20:25:22'),
(208, 'parent', 1, 'Appointment Approved', 'Your appointment with Maryam has been approved.', 'unread', '2026-06-21 20:26:08'),
(209, 'therapist', 3, 'New Approved Appointment', 'You have a new approved appointment with Malini.', 'unread', '2026-06-21 20:26:08'),
(210, 'admin', 1, 'New Appointment Request', 'Amara submitted a new appointment request for Malini.', 'unread', '2026-06-21 20:31:21'),
(211, 'parent', 1, 'Appointment Request Submitted', 'Your appointment request has been submitted and is waiting for admin approval.', 'unread', '2026-06-21 20:31:21'),
(212, 'parent', 1, 'Appointment Approved', 'Your appointment with Maryam has been approved.', 'unread', '2026-06-21 20:31:51'),
(213, 'therapist', 3, 'New Approved Appointment', 'You have a new approved appointment with Malini.', 'unread', '2026-06-21 20:31:51'),
(214, 'admin', 1, 'New Appointment Request', 'Amara submitted a new appointment request for Malini.', 'unread', '2026-06-22 07:24:37'),
(215, 'parent', 1, 'Appointment Request Submitted', 'Your appointment request has been submitted and is waiting for admin approval.', 'unread', '2026-06-22 07:24:37'),
(216, 'parent', 1, 'Appointment Approved', 'Your appointment with Maryam has been approved.', 'unread', '2026-06-22 07:25:21'),
(217, 'therapist', 3, 'New Approved Appointment', 'You have a new approved appointment with Malini.', 'unread', '2026-06-22 07:25:21'),
(218, 'parent', 1, 'Session Completed', 'Your child\'s therapy session has been completed. Session notes are now available.', 'unread', '2026-06-22 07:26:47'),
(219, 'parent', 1, 'Appointment Rescheduled', 'Your appointment has been rescheduled successfully.', 'unread', '2026-07-11 04:44:09'),
(220, 'therapist', 3, 'Appointment Rescheduled', 'Amara rescheduled the appointment for Malini.', 'unread', '2026-07-11 04:44:09'),
(221, 'admin', 1, 'Appointment Rescheduled', 'Amara rescheduled an appointment for Malini.', 'unread', '2026-07-11 04:44:09');

-- --------------------------------------------------------

--
-- Table structure for table `parents`
--

CREATE TABLE `parents` (
  `parent_id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `address` text DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('active','blocked') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parents`
--

INSERT INTO `parents` (`parent_id`, `full_name`, `email`, `phone`, `address`, `password`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Amara', 'reesman1429@gmail.com', '0771234567', 'Kalmunai', '$2y$10$OieUCoyYdmA5zGJtomx2sewXtXsJPxvcDvkEI.aYCYW3BT6w2B28W', 'active', '2026-05-20 13:52:11', '2026-06-14 07:29:41'),
(2, 'Afshin', 'afshin123@gmail.com', '0771234567', 'Kalmunai', '$2y$10$my.QqA4O2xD/mOwx2Zopw.7rne9GRct6fsCjNquBL2q3GRmRR6OhO', 'active', '2026-05-23 08:19:40', '2026-05-23 08:19:40');

-- --------------------------------------------------------

--
-- Table structure for table `password_otps`
--

CREATE TABLE `password_otps` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL,
  `otp_code` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `role` enum('admin','therapist','parent') NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `used` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `role`, `token_hash`, `expires_at`, `created_at`, `used`) VALUES
(1, 'reesman1429@gmail.com', 'parent', '522bdd4da156be4aa7c8507fb1f452f2982822f53bfbb855b6033591969e0213', '2026-06-14 12:23:54', '2026-06-14 05:53:54', 0),
(2, 'reesman1429@gmail.com', 'parent', '3ea207962df838fe478da061a062af557ec750c7f6991de93eb454f37623dda1', '2026-06-14 12:25:36', '2026-06-14 05:55:36', 0),
(3, 'reesman1429@gmail.com', 'parent', '69319120125e25a6f610806d89f25326044c011ebfd0c8a35cf02d410ac94675', '2026-06-14 12:31:16', '2026-06-14 06:01:16', 0),
(4, 'reesman1429@gmail.com', 'parent', 'd78759e68901d464cecab03c82f8f931b9b4d4e996657398fa352da730a1adb4', '2026-06-14 12:36:55', '2026-06-14 06:06:55', 0);

-- --------------------------------------------------------

--
-- Table structure for table `session_notes`
--

CREATE TABLE `session_notes` (
  `note_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `therapist_id` int(11) NOT NULL,
  `child_id` int(11) NOT NULL,
  `session_activities` text NOT NULL,
  `child_performance` text DEFAULT NULL,
  `progress_notes` text NOT NULL,
  `home_practice` text DEFAULT NULL,
  `next_session_recommendation` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `performance_rating` tinyint(4) DEFAULT NULL COMMENT '1-5 scale',
  `parent_summary` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `session_notes`
--

INSERT INTO `session_notes` (`note_id`, `appointment_id`, `therapist_id`, `child_id`, `session_activities`, `child_performance`, `progress_notes`, `home_practice`, `next_session_recommendation`, `created_at`, `updated_at`, `performance_rating`, `parent_summary`) VALUES
(1, 1, 1, 1, 'done', '', 'done', 'okay', 'sure', '2026-05-20 14:38:05', '2026-06-14 18:31:10', NULL, NULL),
(2, 18, 3, 1, 'afasfe', 'sea', 'afaes', 'aesra', 'are', '2026-06-15 12:52:16', '2026-06-15 13:40:30', 3, NULL),
(3, 17, 3, 1, 'csd', 'xs', 'dwed', 'dwe', 'dwe', '2026-06-15 14:00:15', '2026-06-15 14:02:34', 2, NULL),
(4, 21, 3, 1, 'ddffd', 'ddsd', 'ghh', 'ff', 'dff', '2026-06-22 07:26:47', '2026-06-22 07:26:47', 3, '');

-- --------------------------------------------------------

--
-- Table structure for table `therapists`
--

CREATE TABLE `therapists` (
  `therapist_id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `qualification` varchar(150) NOT NULL,
  `specialization` varchar(150) NOT NULL,
  `experience` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reset_token` varchar(255) DEFAULT NULL,
  `token_expiry` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `therapists`
--

INSERT INTO `therapists` (`therapist_id`, `full_name`, `email`, `phone`, `qualification`, `specialization`, `experience`, `password`, `status`, `created_at`, `updated_at`, `reset_token`, `token_expiry`) VALUES
(1, 'Ms. Amali', 'therapist@gmail.com', '0771234567', 'M.Sc. in Speech-Language Pathology', 'Pediatric Speech Therapy', '5 years', '$2y$10$Bt1p/BYrZnLH2P0udMI8QuqLR0fVZoiJzg4ADFnwrOXGYhV5cXib6', 'inactive', '2026-05-20 13:35:03', '2026-06-21 19:56:05', NULL, NULL),
(3, 'Maryam', 'reesmank14@gmail.com', '0766045285', 'BSc', 'child speech therapy', '4 years', '$2y$10$Ka/55axtE1Ewpkj5goyg9.pZ1YoHgvglJ6VLPG.08ozItQCNAeFw6', 'active', '2026-06-09 17:40:18', '2026-06-13 07:19:23', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `therapist_availability`
--

CREATE TABLE `therapist_availability` (
  `availability_id` int(11) NOT NULL,
  `therapist_id` int(11) NOT NULL,
  `day_of_week` enum('monday','tuesday','wednesday','thursday','friday','saturday','sunday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `session_duration` int(11) NOT NULL DEFAULT 30,
  `break_duration` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `therapist_availability`
--

INSERT INTO `therapist_availability` (`availability_id`, `therapist_id`, `day_of_week`, `start_time`, `end_time`, `session_duration`, `break_duration`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'monday', '09:00:00', '11:00:00', 60, 0, 'active', '2026-05-20 13:42:30', '2026-05-20 13:42:30'),
(2, 3, 'tuesday', '09:00:00', '11:00:00', 30, 0, 'active', '2026-06-09 18:06:55', '2026-06-09 18:06:55'),
(3, 3, 'monday', '15:00:00', '18:00:00', 60, 0, 'active', '2026-06-13 14:09:03', '2026-06-13 14:09:03'),
(4, 3, 'monday', '09:00:00', '11:00:00', 60, 0, 'active', '2026-06-14 13:35:45', '2026-06-14 13:35:45'),
(5, 3, 'tuesday', '11:00:00', '12:00:00', 30, 0, 'active', '2026-06-15 18:53:56', '2026-06-15 18:53:56'),
(6, 3, 'monday', '12:00:00', '14:00:00', 60, 0, 'active', '2026-06-21 20:29:47', '2026-06-21 20:29:47');

-- --------------------------------------------------------

--
-- Table structure for table `therapist_leave_requests`
--

CREATE TABLE `therapist_leave_requests` (
  `leave_id` int(11) NOT NULL,
  `therapist_id` int(11) NOT NULL,
  `leave_date` date NOT NULL,
  `leave_type` enum('full_day','partial_day') NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `admin_note` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `therapist_leave_requests`
--

INSERT INTO `therapist_leave_requests` (`leave_id`, `therapist_id`, `leave_date`, `leave_type`, `start_time`, `end_time`, `reason`, `status`, `admin_note`, `approved_by`, `created_at`, `updated_at`) VALUES
(1, 1, '2026-06-01', 'full_day', NULL, NULL, '', 'approved', '', 1, '2026-05-20 14:48:00', '2026-05-20 14:51:39'),
(2, 1, '2026-05-25', 'full_day', NULL, NULL, '', 'approved', '', 1, '2026-05-20 14:54:21', '2026-05-20 14:54:40'),
(3, 1, '2026-06-08', 'full_day', NULL, NULL, '', 'approved', '', 1, '2026-05-20 14:59:06', '2026-05-20 15:00:18'),
(4, 3, '2026-06-16', 'full_day', NULL, NULL, 'family function', 'approved', '', 1, '2026-06-13 07:20:22', '2026-06-13 07:22:46'),
(5, 3, '2026-06-23', 'full_day', NULL, NULL, '', 'rejected', 'nooo', 1, '2026-06-13 07:29:45', '2026-06-13 07:31:14'),
(6, 3, '2026-06-23', 'full_day', NULL, NULL, '', 'rejected', 'noo', 1, '2026-06-13 07:46:09', '2026-06-13 07:46:49'),
(7, 3, '2026-06-23', 'full_day', NULL, NULL, 'function', 'rejected', 'no', 1, '2026-06-13 07:51:09', '2026-06-13 07:51:50'),
(8, 3, '2026-06-23', 'full_day', NULL, NULL, '', 'rejected', 'bjuu', 1, '2026-06-13 07:55:51', '2026-06-13 07:56:19'),
(9, 3, '2026-06-23', 'full_day', NULL, NULL, '', 'rejected', 'no', 1, '2026-06-13 08:09:48', '2026-06-13 08:10:17'),
(10, 3, '2026-06-23', 'full_day', NULL, NULL, '', 'rejected', 'no', 1, '2026-06-13 08:18:20', '2026-06-13 08:18:43'),
(11, 3, '2026-06-23', 'full_day', NULL, NULL, '', 'approved', '', 1, '2026-06-13 08:19:20', '2026-06-13 08:19:39'),
(12, 3, '2026-07-07', 'full_day', NULL, NULL, '', 'approved', '', 1, '2026-06-13 11:48:37', '2026-06-13 11:49:57'),
(13, 3, '2026-06-30', 'partial_day', '09:00:00', '09:30:00', '', 'approved', '', 1, '2026-06-13 12:27:32', '2026-06-13 12:28:45'),
(14, 3, '2026-06-30', 'partial_day', '09:30:00', '10:00:00', '', 'rejected', 'no', 1, '2026-06-13 12:40:02', '2026-06-13 12:40:28'),
(15, 3, '2026-06-30', 'partial_day', '10:00:00', '10:30:00', '', 'approved', '', 1, '2026-06-13 13:10:40', '2026-06-13 13:17:12'),
(16, 3, '2026-06-15', 'full_day', NULL, NULL, '', 'approved', '', 1, '2026-06-13 14:10:41', '2026-06-13 14:11:37'),
(17, 3, '2026-06-17', 'full_day', NULL, NULL, '', 'rejected', 'no', 1, '2026-06-13 14:40:46', '2026-06-13 14:41:44'),
(18, 3, '2026-06-17', 'full_day', NULL, NULL, '', 'rejected', 'no', 1, '2026-06-13 14:43:52', '2026-06-13 14:44:19'),
(19, 3, '2026-06-22', 'full_day', NULL, NULL, '', 'approved', '', 1, '2026-06-13 14:58:56', '2026-06-13 14:59:37'),
(20, 3, '2026-06-29', 'full_day', NULL, NULL, 'ds', 'rejected', 'swed', 1, '2026-06-21 06:41:54', '2026-06-21 06:42:38');

-- --------------------------------------------------------

--
-- Table structure for table `time_slots`
--

CREATE TABLE `time_slots` (
  `slot_id` int(11) NOT NULL,
  `therapist_id` int(11) NOT NULL,
  `slot_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` enum('available','pending','booked','unavailable','blocked_for_leave','completed','cancelled') DEFAULT 'available',
  `generated_by` enum('system','admin','therapist') DEFAULT 'system',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `time_slots`
--

INSERT INTO `time_slots` (`slot_id`, `therapist_id`, `slot_date`, `start_time`, `end_time`, `status`, `generated_by`, `created_at`, `updated_at`) VALUES
(1, 1, '2026-05-25', '09:00:00', '10:00:00', 'completed', 'system', '2026-05-20 13:42:30', '2026-05-20 14:38:05'),
(2, 1, '2026-05-25', '10:00:00', '11:00:00', 'blocked_for_leave', 'system', '2026-05-20 13:42:30', '2026-05-20 14:54:40'),
(3, 1, '2026-06-01', '09:00:00', '10:00:00', 'blocked_for_leave', 'system', '2026-05-20 13:42:30', '2026-05-20 14:51:39'),
(4, 1, '2026-06-01', '10:00:00', '11:00:00', 'blocked_for_leave', 'system', '2026-05-20 13:42:30', '2026-05-20 14:51:39'),
(5, 1, '2026-06-08', '09:00:00', '10:00:00', 'blocked_for_leave', 'system', '2026-05-20 13:42:30', '2026-05-20 15:00:18'),
(6, 1, '2026-06-08', '10:00:00', '11:00:00', 'blocked_for_leave', 'system', '2026-05-20 13:42:30', '2026-05-20 15:00:18'),
(7, 1, '2026-06-15', '09:00:00', '10:00:00', 'available', 'system', '2026-05-20 13:42:30', '2026-06-21 19:53:59'),
(8, 1, '2026-06-15', '10:00:00', '11:00:00', 'available', 'system', '2026-05-20 13:42:30', '2026-05-20 13:42:30'),
(9, 3, '2026-06-09', '09:00:00', '09:30:00', 'available', 'system', '2026-06-09 18:06:55', '2026-06-09 18:06:55'),
(10, 3, '2026-06-09', '09:30:00', '10:00:00', 'available', 'system', '2026-06-09 18:06:55', '2026-06-09 18:06:55'),
(11, 3, '2026-06-09', '10:00:00', '10:30:00', 'available', 'system', '2026-06-09 18:06:55', '2026-06-09 18:06:55'),
(12, 3, '2026-06-09', '10:30:00', '11:00:00', 'available', 'system', '2026-06-09 18:06:55', '2026-06-09 18:06:55'),
(13, 3, '2026-06-16', '09:00:00', '09:30:00', 'blocked_for_leave', 'system', '2026-06-09 18:06:55', '2026-06-13 07:22:46'),
(14, 3, '2026-06-16', '09:30:00', '10:00:00', 'blocked_for_leave', 'system', '2026-06-09 18:06:55', '2026-06-13 07:22:46'),
(15, 3, '2026-06-16', '10:00:00', '10:30:00', 'blocked_for_leave', 'system', '2026-06-09 18:06:55', '2026-06-13 07:22:46'),
(16, 3, '2026-06-16', '10:30:00', '11:00:00', 'blocked_for_leave', 'system', '2026-06-09 18:06:55', '2026-06-13 07:22:46'),
(17, 3, '2026-06-23', '09:00:00', '09:30:00', 'blocked_for_leave', 'system', '2026-06-09 18:06:55', '2026-06-13 08:19:46'),
(18, 3, '2026-06-23', '09:30:00', '10:00:00', 'blocked_for_leave', 'system', '2026-06-09 18:06:55', '2026-06-13 08:19:46'),
(19, 3, '2026-06-23', '10:00:00', '10:30:00', 'blocked_for_leave', 'system', '2026-06-09 18:06:55', '2026-06-13 08:19:46'),
(20, 3, '2026-06-23', '10:30:00', '11:00:00', 'blocked_for_leave', 'system', '2026-06-09 18:06:55', '2026-06-13 08:19:46'),
(21, 3, '2026-06-30', '09:00:00', '09:30:00', 'blocked_for_leave', 'system', '2026-06-09 18:06:55', '2026-06-13 12:28:45'),
(22, 3, '2026-06-30', '09:30:00', '10:00:00', 'booked', 'system', '2026-06-09 18:06:55', '2026-06-13 13:11:19'),
(23, 3, '2026-06-30', '10:00:00', '10:30:00', 'blocked_for_leave', 'system', '2026-06-09 18:06:55', '2026-06-13 13:17:12'),
(24, 3, '2026-06-30', '10:30:00', '11:00:00', 'available', 'system', '2026-06-09 18:06:55', '2026-06-09 18:06:55'),
(25, 3, '2026-07-07', '09:00:00', '09:30:00', 'blocked_for_leave', 'system', '2026-06-09 18:06:55', '2026-06-13 11:50:04'),
(26, 3, '2026-07-07', '09:30:00', '10:00:00', 'blocked_for_leave', 'system', '2026-06-09 18:06:55', '2026-06-13 11:50:04'),
(27, 3, '2026-07-07', '10:00:00', '10:30:00', 'blocked_for_leave', 'system', '2026-06-09 18:06:55', '2026-06-13 11:50:04'),
(28, 3, '2026-07-07', '10:30:00', '11:00:00', 'blocked_for_leave', 'system', '2026-06-09 18:06:55', '2026-06-13 11:50:04'),
(29, 3, '2026-06-15', '15:00:00', '16:00:00', 'blocked_for_leave', 'system', '2026-06-13 14:09:03', '2026-06-13 14:11:37'),
(30, 3, '2026-06-15', '16:00:00', '17:00:00', 'blocked_for_leave', 'system', '2026-06-13 14:09:03', '2026-06-13 14:11:37'),
(31, 3, '2026-06-15', '17:00:00', '18:00:00', 'blocked_for_leave', 'system', '2026-06-13 14:09:03', '2026-06-13 14:11:37'),
(32, 3, '2026-06-22', '15:00:00', '16:00:00', 'blocked_for_leave', 'system', '2026-06-13 14:09:03', '2026-06-13 14:59:37'),
(33, 3, '2026-06-22', '16:00:00', '17:00:00', 'blocked_for_leave', 'system', '2026-06-13 14:09:03', '2026-06-13 14:59:37'),
(34, 3, '2026-06-22', '17:00:00', '18:00:00', 'blocked_for_leave', 'system', '2026-06-13 14:09:03', '2026-06-13 14:59:37'),
(35, 3, '2026-06-29', '15:00:00', '16:00:00', 'booked', 'system', '2026-06-13 14:09:03', '2026-06-14 12:34:51'),
(36, 3, '2026-06-29', '16:00:00', '17:00:00', 'available', 'system', '2026-06-13 14:09:03', '2026-06-14 11:53:14'),
(37, 3, '2026-06-29', '17:00:00', '18:00:00', 'available', 'system', '2026-06-13 14:09:03', '2026-06-14 11:54:41'),
(38, 3, '2026-07-06', '15:00:00', '16:00:00', 'available', 'system', '2026-06-13 14:09:03', '2026-06-13 14:09:03'),
(39, 3, '2026-07-06', '16:00:00', '17:00:00', 'available', 'system', '2026-06-13 14:09:03', '2026-06-13 14:09:03'),
(40, 3, '2026-07-06', '17:00:00', '18:00:00', 'available', 'system', '2026-06-13 14:09:03', '2026-06-13 14:09:03'),
(41, 3, '2026-07-13', '15:00:00', '16:00:00', 'available', 'system', '2026-06-13 14:09:03', '2026-06-13 14:09:03'),
(42, 3, '2026-07-13', '16:00:00', '17:00:00', 'available', 'system', '2026-06-13 14:09:03', '2026-06-13 14:09:03'),
(43, 3, '2026-07-13', '17:00:00', '18:00:00', 'available', 'system', '2026-06-13 14:09:03', '2026-06-13 14:09:03'),
(44, 3, '2026-06-15', '09:00:00', '10:00:00', 'completed', 'system', '2026-06-14 13:35:45', '2026-06-15 12:52:16'),
(45, 3, '2026-06-15', '10:00:00', '11:00:00', 'completed', 'system', '2026-06-14 13:35:45', '2026-06-15 14:00:15'),
(46, 3, '2026-06-22', '09:00:00', '10:00:00', 'completed', 'system', '2026-06-14 13:35:45', '2026-06-22 07:26:47'),
(47, 3, '2026-06-22', '10:00:00', '11:00:00', 'available', 'system', '2026-06-14 13:35:45', '2026-06-14 13:35:45'),
(48, 3, '2026-06-29', '09:00:00', '10:00:00', 'available', 'system', '2026-06-14 13:35:45', '2026-06-14 13:35:45'),
(49, 3, '2026-06-29', '10:00:00', '11:00:00', 'available', 'system', '2026-06-14 13:35:45', '2026-06-14 13:35:45'),
(50, 3, '2026-07-06', '09:00:00', '10:00:00', 'available', 'system', '2026-06-14 13:35:45', '2026-06-14 13:35:45'),
(51, 3, '2026-07-06', '10:00:00', '11:00:00', 'available', 'system', '2026-06-14 13:35:45', '2026-06-14 13:35:45'),
(52, 3, '2026-07-13', '09:00:00', '10:00:00', 'available', 'system', '2026-06-14 13:35:45', '2026-06-14 13:35:45'),
(53, 3, '2026-07-13', '10:00:00', '11:00:00', 'available', 'system', '2026-06-14 13:35:45', '2026-06-14 13:35:45'),
(54, 3, '2026-06-16', '11:00:00', '11:30:00', 'booked', 'system', '2026-06-15 18:53:56', '2026-06-15 18:55:07'),
(55, 3, '2026-06-16', '11:30:00', '12:00:00', 'available', 'system', '2026-06-15 18:53:56', '2026-06-15 18:53:56'),
(56, 3, '2026-06-23', '11:00:00', '11:30:00', 'available', 'system', '2026-06-15 18:53:56', '2026-06-15 18:53:56'),
(57, 3, '2026-06-23', '11:30:00', '12:00:00', 'available', 'system', '2026-06-15 18:53:56', '2026-06-15 18:53:56'),
(58, 3, '2026-06-30', '11:00:00', '11:30:00', 'available', 'system', '2026-06-15 18:53:56', '2026-06-15 18:53:56'),
(59, 3, '2026-06-30', '11:30:00', '12:00:00', 'available', 'system', '2026-06-15 18:53:56', '2026-06-15 18:53:56'),
(60, 3, '2026-07-07', '11:00:00', '11:30:00', 'available', 'system', '2026-06-15 18:53:56', '2026-06-15 18:53:56'),
(61, 3, '2026-07-07', '11:30:00', '12:00:00', 'available', 'system', '2026-06-15 18:53:56', '2026-06-15 18:53:56'),
(62, 3, '2026-07-14', '11:00:00', '11:30:00', 'booked', 'system', '2026-06-15 18:53:56', '2026-07-11 04:44:09'),
(63, 3, '2026-07-14', '11:30:00', '12:00:00', 'available', 'system', '2026-06-15 18:53:56', '2026-06-15 18:53:56'),
(64, 3, '2026-06-22', '12:00:00', '13:00:00', 'booked', 'system', '2026-06-21 20:29:47', '2026-06-21 20:31:51'),
(65, 3, '2026-06-22', '13:00:00', '14:00:00', 'booked', 'system', '2026-06-21 20:29:47', '2026-06-22 07:25:21'),
(66, 3, '2026-06-29', '12:00:00', '13:00:00', 'available', 'system', '2026-06-21 20:29:47', '2026-06-21 20:29:47'),
(67, 3, '2026-06-29', '13:00:00', '14:00:00', 'available', 'system', '2026-06-21 20:29:47', '2026-06-21 20:29:47'),
(68, 3, '2026-07-06', '12:00:00', '13:00:00', 'available', 'system', '2026-06-21 20:29:47', '2026-06-21 20:29:47'),
(69, 3, '2026-07-06', '13:00:00', '14:00:00', 'available', 'system', '2026-06-21 20:29:47', '2026-06-21 20:29:47'),
(70, 3, '2026-07-13', '12:00:00', '13:00:00', 'available', 'system', '2026-06-21 20:29:47', '2026-06-21 20:29:47'),
(71, 3, '2026-07-13', '13:00:00', '14:00:00', 'available', 'system', '2026-06-21 20:29:47', '2026-06-21 20:29:47'),
(72, 3, '2026-07-20', '12:00:00', '13:00:00', 'available', 'system', '2026-06-21 20:29:47', '2026-06-21 20:29:47'),
(73, 3, '2026-07-20', '13:00:00', '14:00:00', 'available', 'system', '2026-06-21 20:29:47', '2026-06-21 20:29:47');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_admin_email` (`email`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `fk_appointments_slot` (`slot_id`),
  ADD KEY `idx_appointments_parent` (`parent_id`),
  ADD KEY `idx_appointments_child` (`child_id`),
  ADD KEY `idx_appointments_therapist` (`therapist_id`),
  ADD KEY `idx_appointments_status` (`status`),
  ADD KEY `idx_appointments_date` (`appointment_date`);

--
-- Indexes for table `appointment_reschedule_history`
--
ALTER TABLE `appointment_reschedule_history`
  ADD PRIMARY KEY (`reschedule_id`),
  ADD KEY `fk_reschedule_appointment` (`appointment_id`),
  ADD KEY `fk_reschedule_parent` (`parent_id`),
  ADD KEY `fk_reschedule_therapist` (`therapist_id`),
  ADD KEY `fk_reschedule_old_slot` (`old_slot_id`),
  ADD KEY `fk_reschedule_new_slot` (`new_slot_id`);

--
-- Indexes for table `children`
--
ALTER TABLE `children`
  ADD PRIMARY KEY (`child_id`),
  ADD KEY `fk_children_parent` (`parent_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_notifications_user` (`user_type`,`user_id`,`status`);

--
-- Indexes for table `parents`
--
ALTER TABLE `parents`
  ADD PRIMARY KEY (`parent_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_parent_email` (`email`);

--
-- Indexes for table `password_otps`
--
ALTER TABLE `password_otps`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `session_notes`
--
ALTER TABLE `session_notes`
  ADD PRIMARY KEY (`note_id`),
  ADD KEY `fk_notes_appointment` (`appointment_id`),
  ADD KEY `fk_notes_therapist` (`therapist_id`),
  ADD KEY `fk_notes_child` (`child_id`);

--
-- Indexes for table `therapists`
--
ALTER TABLE `therapists`
  ADD PRIMARY KEY (`therapist_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_therapist_email` (`email`);

--
-- Indexes for table `therapist_availability`
--
ALTER TABLE `therapist_availability`
  ADD PRIMARY KEY (`availability_id`),
  ADD KEY `fk_availability_therapist` (`therapist_id`);

--
-- Indexes for table `therapist_leave_requests`
--
ALTER TABLE `therapist_leave_requests`
  ADD PRIMARY KEY (`leave_id`),
  ADD KEY `fk_leave_admin` (`approved_by`),
  ADD KEY `idx_leave_therapist_date` (`therapist_id`,`leave_date`);

--
-- Indexes for table `time_slots`
--
ALTER TABLE `time_slots`
  ADD PRIMARY KEY (`slot_id`),
  ADD UNIQUE KEY `unique_therapist_slot` (`therapist_id`,`slot_date`,`start_time`,`end_time`),
  ADD KEY `idx_slots_therapist_date` (`therapist_id`,`slot_date`),
  ADD KEY `idx_slots_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `appointment_reschedule_history`
--
ALTER TABLE `appointment_reschedule_history`
  MODIFY `reschedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `children`
--
ALTER TABLE `children`
  MODIFY `child_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=222;

--
-- AUTO_INCREMENT for table `parents`
--
ALTER TABLE `parents`
  MODIFY `parent_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `password_otps`
--
ALTER TABLE `password_otps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `session_notes`
--
ALTER TABLE `session_notes`
  MODIFY `note_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `therapists`
--
ALTER TABLE `therapists`
  MODIFY `therapist_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `therapist_availability`
--
ALTER TABLE `therapist_availability`
  MODIFY `availability_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `therapist_leave_requests`
--
ALTER TABLE `therapist_leave_requests`
  MODIFY `leave_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `time_slots`
--
ALTER TABLE `time_slots`
  MODIFY `slot_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appointments_child` FOREIGN KEY (`child_id`) REFERENCES `children` (`child_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_appointments_parent` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`parent_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_appointments_slot` FOREIGN KEY (`slot_id`) REFERENCES `time_slots` (`slot_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_appointments_therapist` FOREIGN KEY (`therapist_id`) REFERENCES `therapists` (`therapist_id`) ON DELETE CASCADE;

--
-- Constraints for table `appointment_reschedule_history`
--
ALTER TABLE `appointment_reschedule_history`
  ADD CONSTRAINT `fk_reschedule_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reschedule_new_slot` FOREIGN KEY (`new_slot_id`) REFERENCES `time_slots` (`slot_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reschedule_old_slot` FOREIGN KEY (`old_slot_id`) REFERENCES `time_slots` (`slot_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reschedule_parent` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`parent_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reschedule_therapist` FOREIGN KEY (`therapist_id`) REFERENCES `therapists` (`therapist_id`) ON DELETE CASCADE;

--
-- Constraints for table `children`
--
ALTER TABLE `children`
  ADD CONSTRAINT `fk_children_parent` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`parent_id`) ON DELETE CASCADE;

--
-- Constraints for table `session_notes`
--
ALTER TABLE `session_notes`
  ADD CONSTRAINT `fk_notes_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_notes_child` FOREIGN KEY (`child_id`) REFERENCES `children` (`child_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_notes_therapist` FOREIGN KEY (`therapist_id`) REFERENCES `therapists` (`therapist_id`) ON DELETE CASCADE;

--
-- Constraints for table `therapist_availability`
--
ALTER TABLE `therapist_availability`
  ADD CONSTRAINT `fk_availability_therapist` FOREIGN KEY (`therapist_id`) REFERENCES `therapists` (`therapist_id`) ON DELETE CASCADE;

--
-- Constraints for table `therapist_leave_requests`
--
ALTER TABLE `therapist_leave_requests`
  ADD CONSTRAINT `fk_leave_admin` FOREIGN KEY (`approved_by`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_leave_therapist` FOREIGN KEY (`therapist_id`) REFERENCES `therapists` (`therapist_id`) ON DELETE CASCADE;

--
-- Constraints for table `time_slots`
--
ALTER TABLE `time_slots`
  ADD CONSTRAINT `fk_slots_therapist` FOREIGN KEY (`therapist_id`) REFERENCES `therapists` (`therapist_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
