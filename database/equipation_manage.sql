-- phpMyAdmin SQL Dump
-- version 4.5.1
-- http://www.phpmyadmin.net
--
-- Host: 127.0.0.1
-- Generation Time: Jan 24, 2026 at 09:47 AM
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
-- Table structure for table `contractequipments`
--

CREATE TABLE `contractequipments` (
  `id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `equip_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'نوع المعدة',
  `equip_size` int(11) DEFAULT NULL COMMENT 'حجم المعدة',
  `equip_count` int(11) DEFAULT NULL COMMENT 'عدد المعدات',
  `equip_target_per_month` int(11) DEFAULT NULL COMMENT 'ساعات العمل المستهدفة شهريا',
  `equip_total_month` int(11) DEFAULT NULL COMMENT 'إجمالي الساعات شهريا',
  `equip_total_contract` int(11) DEFAULT NULL COMMENT 'إجمالي ساعات العقد',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contractequipments`
--

INSERT INTO `contractequipments` (`id`, `contract_id`, `equip_type`, `equip_size`, `equip_count`, `equip_target_per_month`, `equip_total_month`, `equip_total_contract`, `created_at`) VALUES
(1, 1, 'Ù‚Ù„Ø§Ø¨', 200, 3, 250, 750, 2250, '2026-01-21 08:52:05'),
(2, 2, 'Ù‚Ù„Ø§Ø¨', 200, 3, 250, 750, 2250, '2026-01-21 09:11:41');

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
  `hours_monthly_target` int(11) DEFAULT '0',
  `forecasted_contracted_hours` int(11) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `daily_work_hours` varchar(20) DEFAULT NULL,
  `daily_operators` varchar(20) DEFAULT NULL,
  `first_party` varchar(255) DEFAULT NULL,
  `second_party` varchar(255) DEFAULT NULL,
  `witness_one` varchar(255) DEFAULT NULL,
  `witness_two` varchar(255) DEFAULT NULL,
  `contract_status` text NOT NULL,
  `pause_reason` text NOT NULL,
  `termination_type` text NOT NULL,
  `termination_reason` text NOT NULL,
  `merged_with` text NOT NULL,
  `status` varchar(10) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `contracts`
--

INSERT INTO `contracts` (`id`, `project`, `contract_signing_date`, `grace_period_days`, `contract_duration_months`, `actual_start`, `actual_end`, `transportation`, `accommodation`, `place_for_living`, `workshop`, `hours_monthly_target`, `forecasted_contracted_hours`, `created_at`, `updated_at`, `daily_work_hours`, `daily_operators`, `first_party`, `second_party`, `witness_one`, `witness_two`, `contract_status`, `pause_reason`, `termination_type`, `termination_reason`, `merged_with`, `status`) VALUES
(1, 1, '2026-01-01', 10, 3, '2026-01-10', '2026-03-10', 'Ù…Ø´Ù…ÙˆÙ„Ø©', 'Ù…Ø´Ù…ÙˆÙ„Ø©', 'Ù…Ø´Ù…ÙˆÙ„Ø©', 'Ù…Ø´Ù…ÙˆÙ„Ø©', 750, 2350, '2026-01-21 08:52:05', '2026-01-21 08:54:49', '20', '2', 'Ù…Ø­Ù…Ø¯ Ø³ÙŠØ¯', 'Ù…Ø¨Ø§Ø±Ùƒ Ø¹ÙˆØ¶', 'Ø³Ù…ÙŠØ± Ø§Ù„Ù„ÙŠÙ„', 'Ù…Ø¨Ø§Ø±Ùƒ Ù…Ø­Ù…ÙˆØ¯', '', '', 'amicable', 'ØªÙ… Ø§Ù†Ù‡Ø§Ø¡ Ø§Ù„Ø¹Ù…Ù„\n', '', '0'),
(2, 2, '2026-01-01', 0, 3, '2026-01-01', '2026-03-01', 'Ù…Ø´Ù…ÙˆÙ„Ø©', '', '', '', 750, 2250, '2026-01-21 09:11:41', '2026-01-21 09:11:41', '20', '2', 'Ù…Ø­Ù…Ø¯ Ø³ÙŠØ¯', 'Ù…Ø¨Ø§Ø±Ùƒ Ø¹ÙˆØ¶', 'Ø³Ù…ÙŠØ± Ø§Ù„Ù„ÙŠÙ„', 'Ù…Ø¨Ø§Ø±Ùƒ Ù…Ø­Ù…ÙˆØ¯', '', '', '', '', '', '1');

-- --------------------------------------------------------

--
-- Table structure for table `contract_notes`
--

CREATE TABLE `contract_notes` (
  `id` int(11) NOT NULL,
  `contract_id` varchar(10) NOT NULL,
  `note` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `contract_notes`
--

INSERT INTO `contract_notes` (`id`, `contract_id`, `note`, `created_at`) VALUES
(1, '1', 'ØªÙ… ØªØ³ÙˆÙŠØ© Ø§Ù„Ø¹Ù‚Ø¯: Ø²ÙŠØ§Ø¯Ø© 100 Ø³Ø§Ø¹Ø© - Ø§Ù„Ø³Ø¨Ø¨: Ø¨Ø³Ø¨Ø¨ Ø¹Ø·Ù„ Ø§Ù„Ù…ÙƒÙŠÙ†Ø©', '2026-01-21 08:52:41'),
(2, '1', 'ØªÙ… Ø¥Ù†Ù‡Ø§Ø¡ Ø§Ù„Ø¹Ù‚Ø¯ (Ø±Ø¶Ø§Ø¦ÙŠ) - Ø§Ù„Ø³Ø¨Ø¨: ØªÙ… Ø§Ù†Ù‡Ø§Ø¡ Ø§Ù„Ø¹Ù…Ù„\\\\n', '2026-01-21 08:54:49');

-- --------------------------------------------------------

--
-- Table structure for table `drivercontracts`
--

CREATE TABLE `drivercontracts` (
  `id` int(11) NOT NULL,
  `driver_id` int(250) NOT NULL,
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
  `witness_two` varchar(255) DEFAULT NULL,
  `project_id` int(255) NOT NULL DEFAULT '0',
  `status` varchar(10) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `drivercontracts`
--

INSERT INTO `drivercontracts` (`id`, `driver_id`, `contract_signing_date`, `grace_period_days`, `contract_duration_months`, `actual_start`, `actual_end`, `transportation`, `accommodation`, `place_for_living`, `workshop`, `equip_type`, `equip_size`, `equip_count`, `equip_target_per_month`, `equip_total_month`, `equip_total_contract`, `mach_type`, `mach_size`, `mach_count`, `mach_target_per_month`, `mach_total_month`, `mach_total_contract`, `hours_monthly_target`, `forecasted_contracted_hours`, `created_at`, `updated_at`, `daily_work_hours`, `daily_operators`, `first_party`, `second_party`, `witness_one`, `witness_two`, `project_id`, `status`) VALUES
(1, 16, '2025-09-01', 10, 90, '2025-09-01', '2025-09-30', 'Ù…Ø´Ù…ÙˆÙ„Ø©', 'Ù…Ø´Ù…ÙˆÙ„Ø©', 'Ù…Ø´Ù…ÙˆÙ„Ø©', 'Ù…Ø´Ù…ÙˆÙ„Ø©', 'Ø­ÙØ§Ø±', 340, 2, 600, 1200, 108000, 'Ù‚Ù„Ø§Ø¨', 340, 8, 600, 4800, 432000, 6000, 540000, '2025-09-18 22:31:55', '2025-09-18 22:31:55', '20', '5', 'Ù…Ø­Ù…Ø¯ Ø³ÙŠØ¯', 'Ù…Ø¨Ø§Ø±Ùƒ Ø¹ÙˆØ¶', 'Ø³Ù…ÙŠØ± Ø§Ù„Ù„ÙŠÙ„', 'Ù…Ø¨Ø§Ø±Ùƒ Ù…Ø­Ù…ÙˆØ¯', 1, '1'),
(2, 12, '2025-09-16', 10, 90, '2025-09-01', '2025-09-30', 'Ù…Ø´Ù…ÙˆÙ„Ø©', 'Ù…Ø´Ù…ÙˆÙ„Ø©', 'Ù…Ø´Ù…ÙˆÙ„Ø©', 'Ù…Ø´Ù…ÙˆÙ„Ø©', 'Ø­ÙØ§Ø±', 340, 2, 600, 1200, 108000, 'Ù‚Ù„Ø§Ø¨', 340, 8, 600, 4800, 432000, 6000, 540000, '2025-09-18 22:37:23', '2025-09-18 22:37:23', '20', '4', 'Ù…Ø­Ù…Ø¯ Ø³ÙŠØ¯', 'Ù…Ø¨Ø§Ø±Ùƒ Ø¹ÙˆØ¶', 'Ø³Ù…ÙŠØ± Ø§Ù„Ù„ÙŠÙ„', 'Ù…Ø¨Ø§Ø±Ùƒ Ù…Ø­Ù…ÙˆØ¯', 2, '1');

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--

CREATE TABLE `drivers` (
  `id` int(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(255) NOT NULL,
  `status` varchar(10) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `drivers`
--

INSERT INTO `drivers` (`id`, `name`, `phone`, `status`) VALUES
(12, 'Ù…Ø­Ù…Ø¯ Ø³ÙŠØ¯ Ø­Ø³Ù†', '095000909', '1'),
(13, 'Ø­Ø³Ù† Ø³ÙŠØ¯ Ø­Ø³Ù†', '099000909', '1'),
(14, 'Ø§Ø³Ø­Ø§Ù‚ Ø³ÙŠØ¯ Ø­Ø³Ù†', '0999990009', '1'),
(15, 'Ø§Ø­Ù…Ø¯ Ø³ÙŠØ¯ Ø­Ø³Ù†', '01123475758', '0'),
(16, 'Ø­Ø³Ø§Ù… Ø³ÙŠØ¯ Ø­Ø³Ù† ØºÙ†ÙŠÙ…', '01123475758', '0');

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

--
-- Dumping data for table `equipments`
--

INSERT INTO `equipments` (`id`, `suppliers`, `code`, `type`, `name`, `status`) VALUES
(13, '1', 'EQ001', '1', '01', '0'),
(14, '1', 'EQ002', '1', '02', '1'),
(15, '1', 'EQ003', '1', '03', '1'),
(16, '2', 'EM001', '1', '04', '1'),
(17, '2', 'EM002', '1', '05', '1'),
(18, '2', 'EM003', '1', '06', '1'),
(19, '1', 'TQ001', '2', '07', '1'),
(20, '2', 'TM002', '2', '08', '1'),
(21, '3', 'ETO01', '1', '09', '1'),
(22, '3', 'TTO02', '', '10', '1');

-- --------------------------------------------------------

--
-- Table structure for table `equipment_drivers`
--

CREATE TABLE `equipment_drivers` (
  `id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `status` varchar(10) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `equipment_drivers`
--

INSERT INTO `equipment_drivers` (`id`, `equipment_id`, `driver_id`, `status`) VALUES
(2, 13, 12, '1'),
(3, 14, 12, '0'),
(4, 15, 13, '0'),
(5, 15, 14, '0'),
(7, 22, 12, '1'),
(8, 21, 12, '1');

-- --------------------------------------------------------

--
-- Table structure for table `operations`
--

CREATE TABLE `operations` (
  `id` int(11) NOT NULL,
  `equipment` varchar(100) NOT NULL,
  `equipment_type` varchar(100) NOT NULL DEFAULT '0',
  `project` varchar(20) NOT NULL,
  `start` varchar(50) NOT NULL,
  `end` varchar(50) NOT NULL,
  `hours` varchar(20) NOT NULL,
  `status` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `operations`
--

INSERT INTO `operations` (`id`, `equipment`, `equipment_type`, `project`, `start`, `end`, `hours`, `status`) VALUES
(1, '14', '0', '1', '2025-09-01', '2025-09-30', '0', '0'),
(2, '15', '0', '2', '2025-09-01', '2025-09-30', '0', '0'),
(3, '16', '0', '1', '2025-09-01', '2025-09-30', '0', '0'),
(4, '14', '0', '1', '2025-09-01', '2025-09-30', '0', '0'),
(5, '14', '0', '1', '2025-09-01', '2025-09-30', '0', '1'),
(6, '15', '0', '1', '2025-09-01', '2025-09-30', '0', '1'),
(7, '16', '0', '3', '2025-09-01', '2025-09-30', '0', '1'),
(8, '17', '0', '3', '2025-09-01', '2025-09-30', '0', '1');

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

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `name`, `client`, `location`, `total`, `status`, `create_at`) VALUES
(1, 'Ø­ÙØ±ÙŠØ§Øª Ø§Ù„Ø±ÙˆØ³ÙŠØ©', 'Ø´Ø±ÙƒØ© Ø¨Ø§ÙŠÙ†Ø§Ø³ Ø§Ù„Ø±ÙˆØ³ÙŠØ©', 'Ø´Ù…Ø§Ù„ ØºØ±Ø¨ Ø³Ù†Ø§Ø±', '0', '1', '2025-09-18 10:38:50'),
(2, 'Ù…Ù†Ø¬Ù… Ø§Ù„Ø³Ù‡Ù… Ø§Ù„Ø°Ù‡Ø¨ÙŠ', 'Ø²Ùˆ Ø§Ù„ÙÙ‚Ø§Ø± Ø¹Ù„ÙŠ', 'Ø´Ù…Ø§Ù„ ØºØ±Ø¨ Ø§Ù„Ù…Ù†Ø§Ù‚Ù„', '0', '1', '2025-09-18 11:21:39'),
(3, 'Ù…Ø®Ø·Ø· Ø§Ù„ØªÙˆÙƒÙ„ Ø§Ù„Ø³ÙƒÙ†Ù‰', 'Ù…Ø­Ù…Ø¯ Ù…ØªÙˆÙ„ÙŠ', 'Ø´Ù…Ø§Ù„ ÙƒØ±Ø¯ÙØ§Ù†', '0', '1', '2025-09-18 11:22:16');

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

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `name`, `phone`, `status`) VALUES
(1, 'Ø¥ÙŠÙƒÙˆØ¨ÙŠØ´Ù†', '01226577900', '1'),
(2, 'Ù…Ø­Ù…Ø¯ Ø¹Ù„ÙŠ Ø§Ø­Ù…Ø¯', '09012334689', '1'),
(3, 'Ø´Ø±ÙƒØ© ØªÙˆÙŠÙˆØªØ§', '095656568', '1'),
(4, 'Ø´Ø±ÙƒØ© Ø¯Ø§Ù„ ', '0998779889', '0'),
(5, 'Ø´Ø±ÙƒØ© Ù…Ø­Ù…Ø¯ Ø­Ù…Ø§Ø¯', '098787879', '0'),
(6, 'Ù…Ø¬Ù…ÙˆØ¹Ø© Ø§Ù„Ø¹Ø±Ø¨ÙŠ', '0987878790', '0');

-- --------------------------------------------------------

--
-- Table structure for table `supplierscontracts`
--

CREATE TABLE `supplierscontracts` (
  `id` int(11) NOT NULL,
  `supplier_id` int(250) NOT NULL,
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
  `witness_two` varchar(255) DEFAULT NULL,
  `project_id` int(255) NOT NULL DEFAULT '0',
  `status` varchar(10) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `supplierscontracts`
--

INSERT INTO `supplierscontracts` (`id`, `supplier_id`, `contract_signing_date`, `grace_period_days`, `contract_duration_months`, `actual_start`, `actual_end`, `transportation`, `accommodation`, `place_for_living`, `workshop`, `equip_type`, `equip_size`, `equip_count`, `equip_target_per_month`, `equip_total_month`, `equip_total_contract`, `mach_type`, `mach_size`, `mach_count`, `mach_target_per_month`, `mach_total_month`, `mach_total_contract`, `hours_monthly_target`, `forecasted_contracted_hours`, `created_at`, `updated_at`, `daily_work_hours`, `daily_operators`, `first_party`, `second_party`, `witness_one`, `witness_two`, `project_id`, `status`) VALUES
(1, 6, '2025-09-01', 10, 90, '2025-09-01', '2025-09-30', 'Ù…Ø´Ù…ÙˆÙ„Ø©', 'Ù…Ø´Ù…ÙˆÙ„Ø©', 'Ù…Ø´Ù…ÙˆÙ„Ø©', 'Ù…Ø´Ù…ÙˆÙ„Ø©', 'Ø­ÙØ§Ø±', 340, 2, 600, 1200, 108000, 'Ù‚Ù„Ø§Ø¨', 340, 8, 600, 4800, 432000, 6000, 540000, '2025-09-18 17:48:21', '2025-09-18 17:48:21', '20', '5', 'Ù…Ø­Ù…Ø¯ Ø³ÙŠØ¯', 'Ù…Ø¨Ø§Ø±Ùƒ Ø¹ÙˆØ¶', 'Ø³Ù…ÙŠØ± Ø§Ù„Ù„ÙŠÙ„', 'Ù…Ø¨Ø§Ø±Ùƒ Ù…Ø­Ù…ÙˆØ¯', 1, '1');

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
  `project_id` varchar(20) NOT NULL DEFAULT '0',
  `parent_id` varchar(20) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `password`, `phone`, `role`, `project_id`, `parent_id`, `created_at`, `updated_at`) VALUES
(1, 'Super Admin', 'admin', '2025', '09090909', '1', '0', '0', '2025-09-18 11:37:30', '2025-09-18 11:37:30'),
(2, 'smartdev', 'smart', '2025', '09', '-1', '0', '0', '2025-09-18 12:24:59', '2025-09-18 12:24:59'),
(3, 'Supplier', 'supplier', '2025', '09909009', '2', '0', '0', '2025-09-18 13:54:59', '2025-09-18 14:05:12'),
(4, 'oprator', 'oprator', '2025', '0989099090', '3', '0', '0', '2025-09-18 14:50:04', '2025-09-18 14:50:04'),
(5, 'fleet', 'fleet', '2025', '675557676', '4', '0', '0', '2025-09-18 22:56:59', '2025-09-18 22:56:59'),
(6, 'location', 'location', '2025', '09898980', '5', '1', '0', '2025-09-18 23:57:33', '2025-09-18 23:57:33'),
(7, 'location2', 'location2', '2025', '09898980', '5', '3', '0', '2025-09-21 08:44:29', '2025-09-21 08:44:29'),
(11, 'inter', 'inter', '2025', '09787878787', '6', '1', '6', '2025-09-21 10:00:14', '2025-09-21 10:00:14'),
(12, 'sreview', 'sreview', '2025', '879879798', '7', '1', '6', '2025-09-21 10:03:13', '2025-09-21 10:03:13'),
(13, 'oreviw', 'oreview', '2025', '09090909', '8', '1', '6', '2025-09-21 10:06:33', '2025-09-21 10:06:33'),
(14, 'ereview', 'ereview', '2025', '0989808', '9', '1', '6', '2025-09-21 10:08:05', '2025-09-21 10:08:05'),
(15, 'inter', 'inter1', '2025', '898989', '6', '3', '7', '2025-09-21 10:09:37', '2025-09-21 10:09:37'),
(19, 'inter', 'inter2', '2025', '898989', '6', '3', '7', '2025-09-21 10:19:28', '2025-09-21 10:19:28'),
(22, 'fleet', 'fleet1', '2025', '09898980', '4', '0', '0', '2025-09-21 10:22:53', '2025-09-21 10:22:53'),
(24, 'fleet', 'fleet2', '2025', '09898980', '4', '0', '0', '2025-09-21 10:23:51', '2025-09-21 10:23:51');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `contractequipments`
--
ALTER TABLE `contractequipments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contract_id` (`contract_id`);

--
-- Indexes for table `contracts`
--
ALTER TABLE `contracts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `contract_notes`
--
ALTER TABLE `contract_notes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `drivercontracts`
--
ALTER TABLE `drivercontracts`
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
-- Indexes for table `equipment_drivers`
--
ALTER TABLE `equipment_drivers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `equipment_id` (`equipment_id`,`driver_id`),
  ADD KEY `driver_id` (`driver_id`);

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
-- Indexes for table `supplierscontracts`
--
ALTER TABLE `supplierscontracts`
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
-- AUTO_INCREMENT for table `contractequipments`
--
ALTER TABLE `contractequipments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
--
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
--
-- AUTO_INCREMENT for table `contract_notes`
--
ALTER TABLE `contract_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
--
-- AUTO_INCREMENT for table `drivercontracts`
--
ALTER TABLE `drivercontracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
--
-- AUTO_INCREMENT for table `drivers`
--
ALTER TABLE `drivers`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;
--
-- AUTO_INCREMENT for table `equipments`
--
ALTER TABLE `equipments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;
--
-- AUTO_INCREMENT for table `equipment_drivers`
--
ALTER TABLE `equipment_drivers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
--
-- AUTO_INCREMENT for table `operations`
--
ALTER TABLE `operations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
--
-- AUTO_INCREMENT for table `supplierscontracts`
--
ALTER TABLE `supplierscontracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
--
-- AUTO_INCREMENT for table `timesheet`
--
ALTER TABLE `timesheet`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;
--
-- Constraints for dumped tables
--

--
-- Constraints for table `contractequipments`
--
ALTER TABLE `contractequipments`
  ADD CONSTRAINT `contractequipments_ibfk_1` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `equipment_drivers`
--
ALTER TABLE `equipment_drivers`
  ADD CONSTRAINT `equipment_drivers_ibfk_1` FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `equipment_drivers_ibfk_2` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE CASCADE;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
