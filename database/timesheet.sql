-- phpMyAdmin SQL Dump
-- version 4.5.1
-- http://www.phpmyadmin.net
--
-- Host: 127.0.0.1
-- Generation Time: Sep 14, 2025 at 05:03 PM
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
  `counter_diff` varchar(255) DEFAULT '0',
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
  `operator_notes` text,
  `type` varchar(20) NOT NULL,
  `user_id` int(50) NOT NULL DEFAULT '0',
  `time_notes` text,
  `status` varchar(10) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
