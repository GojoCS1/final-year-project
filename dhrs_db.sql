-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: May 14, 2026 at 09:19 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dhrs_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_commands`
--

CREATE TABLE `admin_commands` (
  `id` int(11) NOT NULL,
  `sender_role` varchar(50) DEFAULT NULL,
  `command_type` varchar(100) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `reply` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'New',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `target_staff_id` varchar(50) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_commands`
--

INSERT INTO `admin_commands` (`id`, `sender_role`, `command_type`, `message`, `reply`, `status`, `created_at`, `target_staff_id`, `is_read`) VALUES
(65, 'staff_admin', 'Reset Username', 'wew', 'Username Reset Successfully', 'New', '2026-05-13 21:18:09', 'ADH_0003', 1),
(67, 'staff_admin', 'Reset Username and Password', 'cccccc', 'Credentials Reset Successfully', 'New', '2026-05-14 16:03:23', 'ADH_0002', 1);

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `mrn` varchar(50) DEFAULT NULL,
  `staff_id` varchar(50) DEFAULT NULL,
  `appointment_date` datetime DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `discharge_summaries`
--

CREATE TABLE `discharge_summaries` (
  `id` int(11) NOT NULL,
  `mrn` varchar(50) DEFAULT NULL,
  `staff_id` varchar(50) DEFAULT NULL,
  `ward` varchar(50) DEFAULT NULL,
  `bed` varchar(50) DEFAULT NULL,
  `adm_date` date DEFAULT curdate(),
  `history` text DEFAULT NULL,
  `pe` text DEFAULT NULL,
  `lab_investigation` text DEFAULT NULL,
  `final_diagnosis` text DEFAULT NULL,
  `treatment_course` text DEFAULT NULL,
  `discharge_plan` text DEFAULT NULL,
  `condition_status` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lab_requests`
--

CREATE TABLE `lab_requests` (
  `id` int(11) NOT NULL,
  `mrn` varchar(50) DEFAULT NULL,
  `ordered_by` varchar(50) DEFAULT NULL,
  `test_type` varchar(100) DEFAULT NULL,
  `result` text DEFAULT NULL,
  `result_image` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('Pending','Completed') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `assigned_to_lab_tech` varchar(50) DEFAULT NULL,
  `is_read_lab` tinyint(1) DEFAULT 0,
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `medication_followup`
--

CREATE TABLE `medication_followup` (
  `id` int(11) NOT NULL,
  `mrn` varchar(50) DEFAULT NULL,
  `staff_id` varchar(50) DEFAULT NULL,
  `ward` varchar(100) DEFAULT NULL,
  `bed_number` varchar(50) DEFAULT NULL,
  `row_index` int(11) DEFAULT NULL,
  `item_description` text DEFAULT NULL,
  `hrs` varchar(50) DEFAULT NULL,
  `days_checked` text DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_sheets`
--

CREATE TABLE `order_sheets` (
  `id` int(11) NOT NULL,
  `mrn` varchar(50) DEFAULT NULL,
  `staff_id` varchar(50) DEFAULT NULL,
  `ward` varchar(50) DEFAULT NULL,
  `bed` varchar(50) DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `patient_condition` text DEFAULT NULL,
  `vital_signs` text DEFAULT NULL,
  `nursing_care` text DEFAULT NULL,
  `diet` text DEFAULT NULL,
  `investigation` text DEFAULT NULL,
  `management` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `mrn` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `age` varchar(12) DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `reg_date` date DEFAULT curdate(),
  `initial_complaint` text DEFAULT NULL,
  `assigned_to_dept` varchar(100) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `nationality` varchar(50) DEFAULT NULL,
  `region` varchar(50) DEFAULT NULL,
  `wereda` varchar(50) DEFAULT NULL,
  `kebele` varchar(50) DEFAULT NULL,
  `ketena` varchar(50) DEFAULT NULL,
  `house_number` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` varchar(50) DEFAULT 'Waiting',
  `is_read` tinyint(1) DEFAULT 0,
  `assigned_by` varchar(20) DEFAULT 'receptionist'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`mrn`, `full_name`, `age`, `gender`, `reg_date`, `initial_complaint`, `assigned_to_dept`, `dob`, `nationality`, `region`, `wereda`, `kebele`, `ketena`, `house_number`, `phone`, `assigned_at`, `status`, `is_read`, `assigned_by`) VALUES
('MRN_000001', 'መረሳ ሓጎስ መረሳ', '126', 'Male', '2026-05-13', NULL, NULL, '1899-12-31', 'Ethiopian', 'Tigray', '', '', '', '', '', '2026-05-13 19:30:46', 'Waiting', 0, 'receptionist'),
('MRN_000003', 'Welgabr Hagos Mehar', '27', 'Male', '2026-05-14', 'cccc', 'ADH_0006', '1999-02-01', 'Ethiopian', 'Tigray', '', '', '', '', '', '2026-05-14 16:54:13', 'Seen', 1, 'nurse'),
('MRN_000004', 'welay welday', '36', 'Male', '2026-05-13', 'jf', 'ADH_0006', '1990-05-05', 'Ethiopian', 'Tigray', 'Adigrat', 'ሽከት', 'Gologota', '', '+251914123312', '2026-05-14 16:54:13', 'Waiting', 1, 'receptionist'),
('MRN_000005', 'Birkit abadi', '0/12', 'Male', '2026-05-13', NULL, NULL, '2026-05-03', 'Ethiopian', 'Tigray', 'Gd', 'YEye', 'Hdh', '', '0978674643', '2026-05-14 08:24:38', 'Waiting', 0, 'receptionist'),
('MRN_000006', 'ሓጎስ ሞከነን መርስማስ', '48', 'Male', '2026-05-14', NULL, NULL, '1978-02-02', 'Ethiopian', 'Tigray', '', '', '', '', '', '2026-05-14 15:53:53', 'Waiting', 0, 'receptionist');

-- --------------------------------------------------------

--
-- Table structure for table `patient_updates`
--

CREATE TABLE `patient_updates` (
  `id` int(11) NOT NULL,
  `mrn` varchar(50) DEFAULT NULL,
  `staff_id` varchar(50) DEFAULT NULL,
  `ward` varchar(50) DEFAULT NULL,
  `bed` varchar(50) DEFAULT NULL,
  `adm_date` date DEFAULT curdate(),
  `dept` varchar(100) DEFAULT NULL,
  `cc` text DEFAULT NULL,
  `hpi` text DEFAULT NULL,
  `pe` text DEFAULT NULL,
  `assessment` text DEFAULT NULL,
  `plan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL,
  `mrn` varchar(50) DEFAULT NULL,
  `medication_name` varchar(255) DEFAULT NULL,
  `dosage_instruction` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `assigned_to_pharmacist` varchar(50) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prescriptions`
--

INSERT INTO `prescriptions` (`id`, `mrn`, `medication_name`, `dosage_instruction`, `status`, `created_at`, `assigned_to_pharmacist`, `is_read`) VALUES
(30, 'MRN_000003', 'Amoxa', '500ml', 'Dispensed', '2026-05-14 08:14:36', 'ADH_0003', 1),
(31, 'MRN_000003', 'Afs', '300ml', 'Pending', '2026-05-14 08:16:25', 'ADH_0003', 1);

-- --------------------------------------------------------

--
-- Table structure for table `progress_notes`
--

CREATE TABLE `progress_notes` (
  `id` int(11) NOT NULL,
  `mrn` varchar(50) DEFAULT NULL,
  `staff_id` varchar(50) DEFAULT NULL,
  `ward` varchar(50) DEFAULT NULL,
  `bed` varchar(50) DEFAULT NULL,
  `adm_date` date DEFAULT curdate(),
  `problem_list` text DEFAULT NULL,
  `mgmt_summary` text DEFAULT NULL,
  `history_upd` text DEFAULT NULL,
  `pe_upd` text DEFAULT NULL,
  `assessment` text DEFAULT NULL,
  `plan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `referrals`
--

CREATE TABLE `referrals` (
  `id` int(11) NOT NULL,
  `mrn` varchar(50) DEFAULT NULL,
  `staff_id` varchar(50) DEFAULT NULL,
  `referral_date` date DEFAULT NULL,
  `patient_name` varchar(255) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `chief_complaint` text DEFAULT NULL,
  `hpi_findings` text DEFAULT NULL,
  `provisional_diagnosis` text DEFAULT NULL,
  `treatment_given` text DEFAULT NULL,
  `reason_for_referral` text DEFAULT NULL,
  `receiving_hospital` varchar(255) DEFAULT NULL,
  `doctor_name` varchar(255) DEFAULT NULL,
  `profession` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `id` int(11) NOT NULL,
  `staff_name_id` varchar(100) DEFAULT NULL,
  `shift_day` varchar(20) NOT NULL,
  `shift_time` varchar(100) DEFAULT NULL,
  `assigned_area` varchar(100) DEFAULT NULL,
  `room` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`id`, `staff_name_id`, `shift_day`, `shift_time`, `assigned_area`, `room`) VALUES
(147, 'Haftom Goitom (doctor)', 'Monday', 'Morning (8AM-12PM)', 'Surgical (Operation)', '7'),
(148, 'Netsanet H/zgi (nurse)', 'Monday', 'Morning (8AM-12PM)', 'Surgical (Operation)', '7'),
(149, 'Senayt Misgna (receptionist)', 'Monday', 'Morning (8AM-12PM)', '', '12'),
(150, 'Daniel Geberemedhn (lab_technician)', 'Monday', 'Morning (8AM-12PM)', '', '11'),
(151, 'JNBG (lab_technician)', 'Monday', 'Morning (8AM-12PM)', '', '11');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('backup_status', '0'),
('last_backup_checksum', '12664582200'),
('last_backup_date', '2026-05-14'),
('system_lock', '0');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `staff_id` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('doctor','nurse','system_admin','staff_admin','receptionist','lab_technician','pharmacist') NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL DEFAULT '1234',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_patient_check` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `staff_id`, `full_name`, `gender`, `age`, `phone`, `email`, `role`, `username`, `password`, `created_at`, `last_patient_check`) VALUES
(1, 'ADH_0001', 'Shambel Goitom', 'male', 23, '0919044401', 'shambelgoitom1221@gmail.com', 'system_admin', 'ss', '$2y$10$z3XFjC7PRH2qT1BT0xcP8OmKmizt7AQo5/ruJdHD4ayN1ZaIE02Vq', '2026-02-10 06:25:11', '2020-01-01 00:00:00'),
(2, 'ADH_0002', 'Goytom eyasu', 'male', 25, '0919085664', 'goytom@gmail.com', 'staff_admin', 'gs', '$2y$10$minrTW6rQOuw.Tw5q1uN/OkhTkxduPZFRhb3kxCM5PZmsn7QMJQLy', '2026-05-05 06:24:56', '2026-05-05 09:24:56'),
(3, 'ADH_0003', 'Kibra Grmay', 'female', 24, '0923091387', 'kib@gmail.com', 'pharmacist', 'kp', '$2y$10$pqzfk46CUdLJHyo5LVIIG.2EW//M2M1jCBC0dDuIFuCrH0Oe0JgRi', '2026-05-07 13:22:40', '2026-05-07 16:22:40'),
(4, 'ADH_0004', 'Netsanet H/zgi', 'female', 21, '0684852222', 'netሲ@gmail.com', 'nurse', 'nn', '$2y$10$XSVghsDfgAnZ2cmITK0h3ev0M8C/jAE5NdSxOONAxHvHSGWTE4xgK', '2026-05-05 06:28:30', '2026-05-05 09:28:30'),
(5, 'ADH_0005', 'Senayt Misgna', 'female', 25, '0923091387', 'seni@gmail.com', 'receptionist', 'sr', '$2y$10$.LBmhJlw48ND1OQAT5gVI.Z1wtr4Nj.xv.OLCXbjkpv6xpBTLDYLm', '2026-05-05 06:29:49', '2026-05-05 09:29:49'),
(6, 'ADH_0006', 'Haftom Goitom', 'male', 32, '0919040004', 'haf@gmail.com', 'doctor', 'hd', '$2y$10$O4AyGS94EnRFZkuaZ1jGaOCnye1w1fey6VOHG47gZzD2OZWkH8uCK', '2026-05-05 06:31:24', '2026-05-05 09:31:24'),
(7, 'ADH_0007', 'Daniel Geberemedhn', 'male', 26, '0962574930', 'danielgebremedhin477@gmail.com', 'lab_technician', 'dl', '$2y$10$Gj5UHxlDckpKbhkZQX4OQu1byMBBuMaJ2zRDSNb67fceUZ5cmHxrG', '2026-05-05 06:32:36', '2026-05-05 09:32:36'),
(8, 'ADH_0008', 'JNBG', 'female', 24, '0684852211', 'jerry@gmail.com', 'lab_technician', 'el', '$2y$10$0tkX1HXhhVyVBvyk3Q44lechlzWAyJyZk43VBlQuzACl52zaoPpmy', '2026-05-14 12:46:11', '2026-05-14 15:46:11'),
(9, 'ADH_0009', 'Kalay Hafte', 'male', 28, '0919085664', 'kal@gmail.com', 'doctor', 'kd', '$2y$10$fl258uomq0rW527Yi/OWeODlfv.JkUVrz/sRLO8cUKZ.UO3v3ZYy2', '2026-05-14 19:14:50', '2026-05-14 22:14:50');

--
-- Triggers `users`
--
DELIMITER $$
CREATE TRIGGER `after_user_delete` AFTER DELETE ON `users` FOR EACH ROW BEGIN
    -- ሰራተኛው ሲጠፋ ስሙን የያዘው ሼድዩል በሙሉ ይጠፋል
    DELETE FROM schedules WHERE staff_name_id LIKE CONCAT(OLD.full_name, ' (', OLD.role, ')%');
END
$$
DELIMITER ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_commands`
--
ALTER TABLE `admin_commands`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `discharge_summaries`
--
ALTER TABLE `discharge_summaries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lab_requests`
--
ALTER TABLE `lab_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mrn` (`mrn`);

--
-- Indexes for table `medication_followup`
--
ALTER TABLE `medication_followup`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `order_sheets`
--
ALTER TABLE `order_sheets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`mrn`);

--
-- Indexes for table `patient_updates`
--
ALTER TABLE `patient_updates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mrn` (`mrn`);

--
-- Indexes for table `progress_notes`
--
ALTER TABLE `progress_notes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `referrals`
--
ALTER TABLE `referrals`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
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
  ADD UNIQUE KEY `staff_id` (`staff_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_commands`
--
ALTER TABLE `admin_commands`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `discharge_summaries`
--
ALTER TABLE `discharge_summaries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `lab_requests`
--
ALTER TABLE `lab_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT for table `medication_followup`
--
ALTER TABLE `medication_followup`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;

--
-- AUTO_INCREMENT for table `order_sheets`
--
ALTER TABLE `order_sheets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `patient_updates`
--
ALTER TABLE `patient_updates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `progress_notes`
--
ALTER TABLE `progress_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `referrals`
--
ALTER TABLE `referrals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=152;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `lab_requests`
--
ALTER TABLE `lab_requests`
  ADD CONSTRAINT `lab_requests_ibfk_1` FOREIGN KEY (`mrn`) REFERENCES `patients` (`mrn`);

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`mrn`) REFERENCES `patients` (`mrn`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
