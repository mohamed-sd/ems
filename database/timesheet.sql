-- phpMyAdmin SQL Dump
-- version 4.5.1
-- http://www.phpmyadmin.net
--
-- Host: 127.0.0.1
-- Generation Time: Sep 03, 2025 at 02:42 AM
-- Server version: 10.1.13-MariaDB
-- PHP Version: 5.6.21

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
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
  `shift_hours` float DEFAULT '0',
  `executed_hours` float DEFAULT '0',
  `bucket_hours` float DEFAULT '0',
  `jackhammer_hours` float DEFAULT '0',
  `extra_hours` float DEFAULT '0',
  `extra_hours_total` float DEFAULT '0',
  `standby_hours` float DEFAULT '0',
  `dependence_hours` float DEFAULT '0',
  `total_work_hours` float DEFAULT '0',
  `work_notes` text,
  `hr_fault` float DEFAULT '0',
  `maintenance_fault` float DEFAULT '0',
  `marketing_fault` float DEFAULT '0',
  `approval_fault` float DEFAULT '0',
  `other_fault_hours` float DEFAULT '0',
  `total_fault_hours` float DEFAULT '0',
  `fault_notes` text,
  `start_seconds` int(11) DEFAULT '0',
  `start_minutes` int(11) DEFAULT '0',
  `start_hours` int(11) DEFAULT '0',
  `end_seconds` int(11) DEFAULT '0',
  `end_minutes` int(11) DEFAULT '0',
  `end_hours` int(11) DEFAULT '0',
  `counter_diff` int(11) DEFAULT '0',
  `fault_type` varchar(255) DEFAULT NULL,
  `fault_department` varchar(255) DEFAULT NULL,
  `fault_part` varchar(255) DEFAULT NULL,
  `fault_details` text,
  `general_notes` text,
  `operator_hours` float DEFAULT '0',
  `machine_standby_hours` float DEFAULT '0',
  `jackhammer_standby_hours` float DEFAULT '0',
  `bucket_standby_hours` float DEFAULT '0',
  `extra_operator_hours` float DEFAULT '0',
  `operator_standby_hours` float DEFAULT '0',
  `operator_notes` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `timesheet`
--

INSERT INTO `timesheet` (`id`, `operator`, `driver`, `shift`, `date`, `shift_hours`, `executed_hours`, `bucket_hours`, `jackhammer_hours`, `extra_hours`, `extra_hours_total`, `standby_hours`, `dependence_hours`, `total_work_hours`, `work_notes`, `hr_fault`, `maintenance_fault`, `marketing_fault`, `approval_fault`, `other_fault_hours`, `total_fault_hours`, `fault_notes`, `start_seconds`, `start_minutes`, `start_hours`, `end_seconds`, `end_minutes`, `end_hours`, `counter_diff`, `fault_type`, `fault_department`, `fault_part`, `fault_details`, `general_notes`, `operator_hours`, `machine_standby_hours`, `jackhammer_standby_hours`, `bucket_standby_hours`, `extra_operator_hours`, `operator_standby_hours`, `operator_notes`) VALUES
(1, '1', '1', 'D', '2025-09-10', 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 0, 0, 0, 0, 0, 0, NULL, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, NULL),
(2, '5', '1', 'N', '2025-09-10', 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 0, 0, 0, 0, 0, 0, NULL, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, NULL),
(3, '4', '4', 'D', '2025-09-10', 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 0, 0, 0, 0, 0, 0, NULL, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, NULL),
(4, '5', '4', 'D', '2025-09-30', 10, 8, 3, 2, 3, 0, 0, 1, 8, '', 0, 0, 1, 0, 0, 1, '', 10, 50, 1000, 20, 50, 1010, -2, 'ØµÙŠØ§Ù†Ø©', 'ÙƒÙ‡Ø±Ø¨Ø§Ø¡', 'Ø§Ù„Ø¬ÙŠØ±Ø¨ÙˆÙƒØ³', '', '', 10, 0, 0, 0, 0, 2, ''),
(5, '4', '4', 'D', '2025-09-10', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 10, '', 10, 50, 1000, 20, 50, 1010, -10, 'ØµÙŠØ§Ù†Ø©', 'ÙƒÙ‡Ø±Ø¨Ø§Ø¡', 'Ø§Ù„Ø¬ÙŠØ±Ø¨ÙˆÙƒØ³', '', '', 0, 0, 0, 0, 0, 0, '');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
