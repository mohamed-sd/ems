-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 13, 2025 at 11:18 AM
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
-- Database: `equipation_manage`
--

-- --------------------------------------------------------

--
-- Table structure for table `timesheet`
--

CREATE TABLE `timesheet` (
  `id` int(11) NOT NULL,
  `operator` varchar(20) NOT NULL,
  `driver` varchar(20) NOT NULL,
  `shift` varchar(100) NOT NULL,
  `date` varchar(30) NOT NULL,
  `shift_hours` float DEFAULT 0,
  `executed_hours` float DEFAULT 0,
  `bucket_hours` float DEFAULT 0,
  `jackhammer_hours` float DEFAULT 0,
  `extra_hours` float DEFAULT 0,
  `extra_hours_total` float DEFAULT 0,
  `standby_hours` float DEFAULT 0,
  `dependence_hours` float DEFAULT 0,
  `total_work_hours` float DEFAULT 0,
  `work_notes` text DEFAULT NULL,
  `hr_fault` float DEFAULT 0,
  `maintenance_fault` float DEFAULT 0,
  `marketing_fault` float DEFAULT 0,
  `approval_fault` float DEFAULT 0,
  `other_fault_hours` float DEFAULT 0,
  `total_fault_hours` float DEFAULT 0,
  `fault_notes` text DEFAULT NULL,
  `start_seconds` int(11) DEFAULT 0,
  `start_minutes` int(11) DEFAULT 0,
  `start_hours` int(11) DEFAULT 0,
  `end_seconds` int(11) DEFAULT 0,
  `end_minutes` int(11) DEFAULT 0,
  `end_hours` int(11) DEFAULT 0,
  `counter_diff` varchar(255) DEFAULT '0',
  `fault_type` varchar(255) DEFAULT NULL,
  `fault_department` varchar(255) DEFAULT NULL,
  `fault_part` varchar(255) DEFAULT NULL,
  `fault_details` text DEFAULT NULL,
  `general_notes` text DEFAULT NULL,
  `operator_hours` float DEFAULT 0,
  `machine_standby_hours` float DEFAULT 0,
  `jackhammer_standby_hours` float DEFAULT 0,
  `bucket_standby_hours` float DEFAULT 0,
  `extra_operator_hours` float DEFAULT 0,
  `operator_standby_hours` float DEFAULT 0,
  `operator_notes` text DEFAULT NULL,
  `type` varchar(20) NOT NULL,
  `user_id` int(50) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `timesheet`
--

INSERT INTO `timesheet` (`id`, `operator`, `driver`, `shift`, `date`, `shift_hours`, `executed_hours`, `bucket_hours`, `jackhammer_hours`, `extra_hours`, `extra_hours_total`, `standby_hours`, `dependence_hours`, `total_work_hours`, `work_notes`, `hr_fault`, `maintenance_fault`, `marketing_fault`, `approval_fault`, `other_fault_hours`, `total_fault_hours`, `fault_notes`, `start_seconds`, `start_minutes`, `start_hours`, `end_seconds`, `end_minutes`, `end_hours`, `counter_diff`, `fault_type`, `fault_department`, `fault_part`, `fault_details`, `general_notes`, `operator_hours`, `machine_standby_hours`, `jackhammer_standby_hours`, `bucket_standby_hours`, `extra_operator_hours`, `operator_standby_hours`, `operator_notes`, `type`, `user_id`) VALUES
(1, '5', '9', 'D', '2025-09-09', 10, 9, 0, 0, 0, 0, 0, 0, 9, '', 0, 1, 0, 0, 0, 1, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 0, 0, 0, 0, 0, 1, '', '1', 6),
(2, '5', '10', 'D', '2025-09-09', 10, 8, 0, 0, 0, 0, 1, 0, 9, '', 1, 0, 0, 0, 0, 1, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 0, 1, 0, 0, 0, 0, '', '1', 6),
(3, '5', '10', 'D', '2025-09-08', 10, 7, 0, 0, 0, 0, 1, 0, 8, '', 0, 1, 1, 0, 0, 2, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 0, 1, 0, 0, 0, 2, '', '1', 6);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `timesheet`
--
ALTER TABLE `timesheet`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `timesheet`
--
ALTER TABLE `timesheet`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
