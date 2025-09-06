-- phpMyAdmin SQL Dump
-- version 4.5.1
-- http://www.phpmyadmin.net
--
-- Host: 127.0.0.1
-- Generation Time: Sep 06, 2025 at 12:42 PM
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
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `daily_work_hours` varchar(20) DEFAULT NULL,
  `daily_operators` varchar(20) DEFAULT NULL,
  `first_party` varchar(255) DEFAULT NULL,
  `second_party` varchar(255) DEFAULT NULL,
  `witness_one` varchar(255) DEFAULT NULL,
  `witness_two` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `contracts`
--

INSERT INTO `contracts` (`id`, `project`, `contract_signing_date`, `grace_period_days`, `contract_duration_months`, `actual_start`, `actual_end`, `transportation`, `accommodation`, `place_for_living`, `workshop`, `equip_type`, `equip_size`, `equip_count`, `equip_target_per_month`, `equip_total_month`, `equip_total_contract`, `mach_type`, `mach_size`, `mach_count`, `mach_target_per_month`, `mach_total_month`, `mach_total_contract`, `hours_monthly_target`, `forecasted_contracted_hours`, `created_at`, `updated_at`, `daily_work_hours`, `daily_operators`, `first_party`, `second_party`, `witness_one`, `witness_two`) VALUES
(1, 1, '2025-09-01', 90, 3, '2025-09-30', '2025-10-30', 'Ù…Ø´Ù…ÙˆÙ„Ø©', 'Ù…Ø´Ù…ÙˆÙ„Ø©', 'Ù…Ø´Ù…ÙˆÙ„Ø©', 'Ù…Ø´Ù…ÙˆÙ„Ø©', 'Ø­ÙØ§Ø±', 340, 2, 600, 1200, 3600, 'Ù‚Ù„Ø§Ø¨', 340, 8, 600, 4800, 14400, 6000, 18000, '2025-09-04 09:28:36', '2025-09-04 09:28:36', NULL, NULL, NULL, NULL, NULL, NULL),
(2, 2, '2025-09-01', 10, 3, '2025-09-30', '2025-12-31', 'Ù…Ø´Ù…ÙˆÙ„Ø©', 'Ù…Ø´Ù…ÙˆÙ„Ø©', 'Ù…Ø´Ù…ÙˆÙ„Ø©', 'Ù…Ø´Ù…ÙˆÙ„Ø©', 'Ø­ÙØ§Ø±', 340, 2, 600, 1200, 3600, 'Ù‚Ù„Ø§Ø¨', 340, 8, 600, 4800, 14400, 6000, 18000, '2025-09-06 09:46:16', '2025-09-06 09:46:16', '10', '3', 'Ù…Ø­Ù…Ø¯ Ø³ÙŠØ¯', 'Ù…Ø¨Ø§Ø±Ùƒ Ø¹ÙˆØ¶', 'Ø³Ù…ÙŠØ± Ø§Ù„Ù„ÙŠÙ„', 'Ù…Ø¨Ø§Ø±Ùƒ Ù…Ø­Ù…ÙˆØ¯');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `contracts`
--
ALTER TABLE `contracts`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
