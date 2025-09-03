-- phpMyAdmin SQL Dump
-- version 4.5.1
-- http://www.phpmyadmin.net
--
-- Host: 127.0.0.1
-- Generation Time: Sep 03, 2025 at 09:56 PM
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
-- Table structure for table `contracts`
--

CREATE TABLE `contracts` (
  `id` int(11) NOT NULL,
  `project` int(250) NOT NULL,
  `contract_signing_date` date NOT NULL,
  `grace_period_days` int(11) DEFAULT '0',
  `contract_duration_months` int(11) DEFAULT '0',
  `actual_start` date DEFAULT NULL,
  `actual_end` date DEFAULT NULL,
  `transportation` text,
  `accommodation` text,
  `place_for_living` text,
  `workshop` text,
  `equip_type` varchar(100) DEFAULT NULL,
  `equip_size` int(11) DEFAULT NULL,
  `equip_count` int(11) DEFAULT '0',
  `equip_target_per_month` int(11) DEFAULT '0',
  `equip_total_month` int(11) DEFAULT '0',
  `equip_total_contract` int(11) DEFAULT '0',
  `mach_type` varchar(100) DEFAULT NULL,
  `mach_size` int(11) DEFAULT NULL,
  `mach_count` int(11) DEFAULT '0',
  `mach_target_per_month` int(11) DEFAULT '0',
  `mach_total_month` int(11) DEFAULT '0',
  `mach_total_contract` int(11) DEFAULT '0',
  `hours_monthly_target` int(11) DEFAULT '0',
  `forecasted_contracted_hours` int(11) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--

CREATE TABLE `drivers` (
  `id` int(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `equipments`
--

CREATE TABLE `equipments` (
  `id` int(11) NOT NULL,
  `suppliers` varchar(10) NOT NULL,
  `code` varchar(100) NOT NULL,
  `type` varchar(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `status` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `operations`
--

CREATE TABLE `operations` (
  `id` int(11) NOT NULL,
  `equipment` varchar(100) NOT NULL,
  `equipment_type` varchar(100) NOT NULL,
  `project` varchar(20) NOT NULL,
  `start` varchar(50) NOT NULL,
  `end` varchar(50) NOT NULL,
  `hours` varchar(20) NOT NULL,
  `status` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `client` varchar(150) NOT NULL,
  `location` varchar(200) NOT NULL,
  `total` varchar(50) NOT NULL,
  `status` varchar(10) NOT NULL,
  `create_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `status` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `username` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` varchar(30) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `contracts`
--
ALTER TABLE `contracts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `drivers`
--
ALTER TABLE `drivers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `equipments`
--
ALTER TABLE `equipments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `operations`
--
ALTER TABLE `operations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `timesheet`
--
ALTER TABLE `timesheet`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `drivers`
--
ALTER TABLE `drivers`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `equipments`
--
ALTER TABLE `equipments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `operations`
--
ALTER TABLE `operations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `timesheet`
--
ALTER TABLE `timesheet`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
