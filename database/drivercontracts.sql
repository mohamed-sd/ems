-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 15, 2025 at 04:22 PM
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
-- Table structure for table `drivercontracts`
--

CREATE TABLE `drivercontracts` (
  `id` int(11) NOT NULL,
  `driver_id` int(250) NOT NULL,
  `contract_signing_date` date NOT NULL,
  `grace_period_days` int(11) DEFAULT 0,
  `contract_duration_months` int(11) DEFAULT 0,
  `actual_start` date DEFAULT NULL,
  `actual_end` date DEFAULT NULL,
  `transportation` text DEFAULT NULL,
  `accommodation` text DEFAULT NULL,
  `place_for_living` text DEFAULT NULL,
  `workshop` text DEFAULT NULL,
  `equip_type` varchar(100) DEFAULT NULL,
  `equip_size` int(11) DEFAULT NULL,
  `equip_count` int(11) DEFAULT 0,
  `equip_target_per_month` int(11) DEFAULT 0,
  `equip_total_month` int(11) DEFAULT 0,
  `equip_total_contract` int(11) DEFAULT 0,
  `mach_type` varchar(100) DEFAULT NULL,
  `mach_size` int(11) DEFAULT NULL,
  `mach_count` int(11) DEFAULT 0,
  `mach_target_per_month` int(11) DEFAULT 0,
  `mach_total_month` int(11) DEFAULT 0,
  `mach_total_contract` int(11) DEFAULT 0,
  `hours_monthly_target` int(11) DEFAULT 0,
  `forecasted_contracted_hours` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `daily_work_hours` varchar(20) DEFAULT NULL,
  `daily_operators` varchar(20) DEFAULT NULL,
  `first_party` varchar(255) DEFAULT NULL,
  `second_party` varchar(255) DEFAULT NULL,
  `witness_one` varchar(255) DEFAULT NULL,
  `witness_two` varchar(255) DEFAULT NULL,
  `project_id` int(255) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `drivercontracts`
--

INSERT INTO `drivercontracts` (`id`, `driver_id`, `contract_signing_date`, `grace_period_days`, `contract_duration_months`, `actual_start`, `actual_end`, `transportation`, `accommodation`, `place_for_living`, `workshop`, `equip_type`, `equip_size`, `equip_count`, `equip_target_per_month`, `equip_total_month`, `equip_total_contract`, `mach_type`, `mach_size`, `mach_count`, `mach_target_per_month`, `mach_total_month`, `mach_total_contract`, `hours_monthly_target`, `forecasted_contracted_hours`, `created_at`, `updated_at`, `daily_work_hours`, `daily_operators`, `first_party`, `second_party`, `witness_one`, `witness_two`, `project_id`) VALUES
(4, 10, '2025-09-08', 33, 80, '2025-09-13', '2025-09-25', 'مشمولة', 'مشمولة', 'مشمولة', 'مشمولة', 'حفار', 340, 4, 600, 2400, 192000, 'قلاب', 340, 8, 600, 4800, 384000, 7200, 576000, '2025-09-13 09:52:49', '2025-09-13 09:52:49', '', '5', '', '', '', '', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `drivercontracts`
--
ALTER TABLE `drivercontracts`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `drivercontracts`
--
ALTER TABLE `drivercontracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
