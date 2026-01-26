-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 26, 2026 at 02:22 AM
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
-- Table structure for table `contractequipments`
--

CREATE TABLE `contractequipments` (
  `id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL COMMENT 'رقم العقد',
  `equip_type` varchar(255) NOT NULL COMMENT 'نوع المعدة',
  `equip_size` int(11) DEFAULT NULL COMMENT 'حجم المعدة',
  `equip_count` int(11) DEFAULT NULL COMMENT 'عدد المعدات',
  `equip_shifts` int(11) DEFAULT 0 COMMENT 'عدد الورديات',
  `equip_unit` varchar(50) DEFAULT 'ساعة' COMMENT 'الوحدة',
  `equip_target_per_month` int(11) DEFAULT NULL COMMENT 'ساعات العمل المستهدفة يوميا',
  `equip_total_month` int(11) DEFAULT NULL COMMENT 'إجمالي الساعات اليومية ',
  `equip_total_contract` int(11) DEFAULT NULL COMMENT 'إجمالي ساعات العقد',
  `equip_price` decimal(10,2) DEFAULT 0.00 COMMENT 'السعر',
  `equip_operators` int(11) DEFAULT 0 COMMENT 'المشغلين',
  `equip_supervisors` int(11) DEFAULT 0 COMMENT 'المشرفين',
  `equip_technicians` int(11) DEFAULT 0 COMMENT 'الفنيين',
  `equip_assistants` int(11) DEFAULT 0 COMMENT 'المساعدين',
  `equip_price_currency` varchar(20) DEFAULT NULL COMMENT 'تمييز السعر',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contractequipments`
--

INSERT INTO `contractequipments` (`id`, `contract_id`, `equip_type`, `equip_size`, `equip_count`, `equip_shifts`, `equip_unit`, `equip_target_per_month`, `equip_total_month`, `equip_total_contract`, `equip_price`, `equip_operators`, `equip_supervisors`, `equip_technicians`, `equip_assistants`, `equip_price_currency`, `created_at`) VALUES
(1, 1, 'حفار', 340, 5, 0, 'ساعة', 20, 100, 3100, 20.00, 3, 3, 3, 2, 'دولار', '2026-01-25 05:13:48'),
(2, 1, 'قلاب', 320, 3, 2, 'طن', 20, 60, 1860, 25.00, 3, 2, 1, 0, 'دولار', '2026-01-25 05:13:48'),
(5, 3, 'حفار', 590, 5, 2, 'ساعة', 20, 100, 2600, 20.00, 1, 1, 1, 1, 'دولار', '2026-01-25 20:03:59'),
(6, 3, 'حفار', 490, 2, 0, 'ساعة', 20, 40, 1200, 0.00, 0, 0, 0, 0, NULL, '2026-01-25 20:58:49'),
(7, 3, 'قلاب', 56, 5, 0, 'ساعة', 20, 100, 3000, 0.00, 0, 0, 0, 0, NULL, '2026-01-25 20:58:49'),
(8, 4, 'حفار', 989, 2, 2, 'ساعة', 10, 20, 280, 10.00, 0, 0, 0, 0, 'دولار', '2026-01-25 21:01:24'),
(12, 5, 'حفار', 350, 2, 3, 'ساعة', 200, 400, 17200, 15.00, 1, 1, 1, 1, 'دولار', '2026-01-25 22:11:33'),
(13, 5, 'قلاب', 120, 5, 0, 'طن', 10, 50, 2150, 15.00, 1, 1, 1, 1, 'دولار', '2026-01-25 22:11:33'),
(14, 2, 'قلاب', 56, 5, 3, 'طن', 20, 100, 3000, 40.00, 1, 1, 1, 1, 'دولار', '2026-01-25 22:23:48'),
(15, 2, 'حفار', 590, 5, 600, 'ساعة', 20, 100, 3000, 0.00, 0, 0, 0, 0, '', '2026-01-25 22:23:48'),
(16, 2, 'حفار', 490, 2, 0, 'ساعة', 20, 40, 1200, 0.00, 0, 0, 0, 0, '', '2026-01-25 22:23:48'),
(17, 2, 'قلاب', 56, 5, 0, 'ساعة', 20, 100, 3000, 0.00, 0, 0, 0, 0, '', '2026-01-25 22:23:48');

-- --------------------------------------------------------

--
-- Table structure for table `contracts`
--

CREATE TABLE `contracts` (
  `id` int(11) NOT NULL,
  `project` int(250) NOT NULL,
  `contract_signing_date` date NOT NULL,
  `grace_period_days` int(11) DEFAULT 0,
  `contract_duration_months` int(11) DEFAULT 0,
  `contract_duration_days` int(11) NOT NULL DEFAULT 0,
  `actual_start` date DEFAULT NULL,
  `actual_end` date DEFAULT NULL,
  `transportation` text DEFAULT NULL,
  `accommodation` text DEFAULT NULL,
  `place_for_living` text DEFAULT NULL,
  `workshop` text DEFAULT NULL,
  `hours_monthly_target` int(11) DEFAULT 0,
  `forecasted_contracted_hours` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `daily_work_hours` varchar(20) DEFAULT NULL,
  `daily_operators` varchar(20) DEFAULT NULL,
  `first_party` varchar(255) DEFAULT NULL,
  `second_party` varchar(255) DEFAULT NULL,
  `witness_one` varchar(255) DEFAULT NULL,
  `witness_two` varchar(255) DEFAULT NULL,
  `contract_status` text DEFAULT NULL,
  `pause_reason` text DEFAULT NULL,
  `pause_date` date DEFAULT NULL COMMENT 'تاريخ إيقاف العقد',
  `resume_date` date DEFAULT NULL COMMENT 'تاريخ استئناف العقد',
  `termination_type` varchar(50) DEFAULT NULL,
  `termination_reason` text DEFAULT NULL,
  `merged_with` int(11) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1 COMMENT '1=نشط, 0=موقوف'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `contracts`
--

INSERT INTO `contracts` (`id`, `project`, `contract_signing_date`, `grace_period_days`, `contract_duration_months`, `contract_duration_days`, `actual_start`, `actual_end`, `transportation`, `accommodation`, `place_for_living`, `workshop`, `hours_monthly_target`, `forecasted_contracted_hours`, `created_at`, `updated_at`, `daily_work_hours`, `daily_operators`, `first_party`, `second_party`, `witness_one`, `witness_two`, `contract_status`, `pause_reason`, `termination_type`, `termination_reason`, `merged_with`, `status`) VALUES
(1, 4, '2026-01-01', 10, 0, 31, '2026-01-10', '2026-02-10', 'مالك المعدة', 'مالك المشروع', 'مالك المعدة', 'مالك المعدة', 160, 4960, '2026-01-25 05:13:48', '2026-01-25 05:13:48', '20', '6', 'محمد سيد', 'مبارك عوض', 'سمير الليل', 'مبارك محمود', NULL, NULL, NULL, NULL, NULL, 1),
(2, 1, '2026-01-01', 5, 4, 144, '2026-02-28', '2026-07-22', 'مالك المعدة', 'مالك المعدة', 'مالك المعدة', 'مالك المعدة', 380, 11400, '2026-01-25 14:13:43', '2026-01-25 23:37:56', '20', '', '', '', '', '', NULL, NULL, NULL, NULL, 3, 1),
(3, 1, '2026-01-25', 3, 7, 236, '2026-03-27', '2026-11-18', 'مالك المعدة', 'مالك المشروع', 'مالك المعدة', 'بدون', 100, 6800, '2026-01-25 20:03:59', '2026-01-25 21:50:29', '20', '', '', '', '', '', NULL, NULL, NULL, NULL, 2, 1),
(4, 1, '2026-01-07', 2, 0, 14, '2026-01-01', '2026-01-15', 'مالك المعدة', 'مالك المعدة', 'بدون', 'مالك المشروع', 20, 280, '2026-01-25 21:01:24', '2026-01-25 21:01:24', '30', '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, 1),
(5, 1, '2026-01-01', 1, 0, 43, '2026-01-15', '2026-02-27', 'مالك المعدة', 'مالك المعدة', 'مالك المشروع', 'مالك المعدة', 450, 19350, '2026-01-25 22:11:33', '2026-01-25 22:11:33', '20', '5', 'احمد', 'محمد', 'علي', 'يوسف', NULL, NULL, NULL, NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `contract_notes`
--

CREATE TABLE `contract_notes` (
  `id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `note` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `contract_notes`
--

INSERT INTO `contract_notes` (`id`, `contract_id`, `note`, `created_at`) VALUES
(20, '3', 'تم تجديد العقد بمدة 1 شهور - تاريخ الانتهاء الجديد: 2026-04-30', '2026-01-23 06:05:58'),
(21, '3', 'تم تسوية العقد: نقصان 100 ساعة - السبب: عطل', '2026-01-23 06:06:43'),
(22, '3', 'تم إيقاف العقد - السبب: تعسر', '2026-01-23 06:07:07'),
(23, '3', 'تم استئناف العقد - الملاحظات: تم', '2026-01-23 06:07:22'),
(24, '3', 'تم دمج العقد مع العقد رقم 4 - إجمالي الساعات: 7400', '2026-01-23 06:09:03'),
(25, '4', 'تم دمج هذا العقد مع العقد رقم 3', '2026-01-23 06:09:03'),
(26, '3', 'تم استئناف العقد - الملاحظات: dfd', '2026-01-24 09:23:23'),
(27, '3', 'تم دمج العقد مع العقد رقم 4 - إجمالي الساعات: 10400', '2026-01-24 09:23:40'),
(28, '4', 'تم دمج هذا العقد مع العقد رقم 3', '2026-01-24 09:23:40'),
(29, '3', 'تم استئناف العقد', '2026-01-24 09:24:04'),
(30, '3', 'تم تجديد العقد من 2026-04-30 إلى 2026-10-31 (مدة: 6 شهور)', '2026-01-24 09:24:41'),
(31, '3', 'تم دمج العقد مع العقد رقم 4 - إجمالي الساعات: 13400', '2026-01-24 09:26:17'),
(32, '4', 'تم دمج هذا العقد مع العقد رقم 3', '2026-01-24 09:26:17'),
(33, '3', 'تم استئناف العقد', '2026-01-24 09:27:19'),
(34, '3', 'تم دمج العقد مع العقد رقم 4 - إجمالي الساعات: 16400', '2026-01-24 11:28:47'),
(35, '4', 'تم دمج هذا العقد مع العقد رقم 3', '2026-01-24 11:28:47'),
(36, '3', 'تم دمج العقد مع العقد رقم 5 - إجمالي الساعات: 17600', '2026-01-24 11:33:41'),
(37, '5', 'تم دمج هذا العقد مع العقد رقم 3', '2026-01-24 11:33:41'),
(38, '3', 'تم إيقاف العقد - السبب: t', '2026-01-24 13:59:20'),
(39, '3', 'تم استئناف العقد بتاريخ 2026-01-24 - الملاحظات: 4', '2026-01-24 13:59:29'),
(40, '3', 'تم دمج العقد مع العقد رقم 5 - إجمالي الساعات: 18800', '2026-01-24 14:03:50'),
(41, '5', 'تم دمج هذا العقد مع العقد رقم 3', '2026-01-24 14:03:50'),
(42, '3', 'تم تجديد العقد من 2026-11-04 إلى 2026-12-25 (مدة: 1 شهور)', '2026-01-24 14:04:58'),
(43, '3', 'تم تجديد العقد من 2026-02-25 إلى 2026-02-28 (مدة: 0 شهور)', '2026-01-25 20:58:29'),
(44, '3', 'تم دمج العقد مع العقد رقم 2 - إجمالي الساعات: 6800', '2026-01-25 20:58:49'),
(45, '2', 'تم دمج هذا العقد مع العقد رقم 3', '2026-01-25 20:58:49'),
(46, '2', 'تم دمج العقد مع العقد رقم 3 - إجمالي الساعات: 11000', '2026-01-25 21:26:00'),
(47, '3', 'تم دمج هذا العقد مع العقد رقم 2', '2026-01-25 21:26:00'),
(48, '2', 'تم تجديد العقد من 2026-01-31 إلى 2026-02-28 (مدة: 0 شهور)', '2026-01-25 21:26:55'),
(49, '2', 'تم تجديد العقد من 2026-02-28 إلى 2026-07-22 (مدة: 4 شهور)', '2026-01-25 21:27:52'),
(50, '3', 'تم تجديد العقد من 2026-02-28 إلى 2026-03-27 (مدة: 0 شهور / 27 يوم)', '2026-01-25 21:48:47'),
(51, '3', 'تم تجديد العقد من 2026-03-27 إلى 2026-11-18 (مدة: 7 شهور / 236 يوم)', '2026-01-25 21:50:29'),
(52, '2', 'تم إيقاف العقد - السبب: الال', '2026-01-25 23:37:49'),
(53, '2', 'تم استئناف العقد بتاريخ 2026-01-26 - الملاحظات: تات', '2026-01-25 23:37:56');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_contract_notes`
--

CREATE TABLE `supplier_contract_notes` (
  `id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `note` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `contract_duration_days` int(11) DEFAULT 0,
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
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `daily_work_hours` varchar(20) DEFAULT NULL,
  `daily_operators` varchar(20) DEFAULT NULL,
  `first_party` varchar(255) DEFAULT NULL,
  `second_party` varchar(255) DEFAULT NULL,
  `witness_one` varchar(255) DEFAULT NULL,
  `witness_two` varchar(255) DEFAULT NULL,
  `project_id` int(255) NOT NULL DEFAULT 0,
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--

CREATE TABLE `drivers` (
  `id` int(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(255) NOT NULL,
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `drivers`
--

INSERT INTO `drivers` (`id`, `name`, `phone`, `status`) VALUES
(9, 'ooo', '0000000000', '1'),
(10, 'omer', '0000000000000', '1'),
(11, 'ali', '00000000000', '1'),
(12, 'Ahmed', '09239', '1');

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
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `equipments`
--

INSERT INTO `equipments` (`id`, `suppliers`, `code`, `type`, `name`, `status`) VALUES
(10, '2', 'A23', '1', 'A23', '1'),
(11, '2', 'Ex11', '1', 'Ex11', '1'),
(12, '2', 'RE10', '2', 'RE10', '1'),
(13, '1', 'Qs11', '1', 'Qs11', '1'),
(14, '2', 'Qa1001', '1', 'Qa1001', '1'),
(15, '2', 'EQ10', '1', '01', '1'),
(16, '1', 'TM02', '2', '02', '1');

-- --------------------------------------------------------

--
-- Table structure for table `equipment_drivers`
--

CREATE TABLE `equipment_drivers` (
  `id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `equipment_drivers`
--

INSERT INTO `equipment_drivers` (`id`, `equipment_id`, `driver_id`, `status`) VALUES
(1, 16, 10, '0'),
(2, 11, 12, '0'),
(3, 14, 11, '1'),
(4, 13, 9, '1'),
(5, 10, 10, '1'),
(6, 12, 12, '1');

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
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `operations`
--

INSERT INTO `operations` (`id`, `equipment`, `equipment_type`, `project`, `start`, `end`, `hours`, `status`) VALUES
(1, '13', '1', '1', '2026-01-12', '2026-01-31', '0', '1'),
(2, '10', '1', '1', '2026-01-14', '2026-01-24', '0', '1'),
(3, '12', '2', '1', '2026-01-06', '2026-01-31', '0', '1');

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
  `status` tinyint(1) DEFAULT 1,
  `create_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `name`, `client`, `location`, `total`, `status`, `create_at`) VALUES
(1, 'مشروع الروسية', 'شركه الياس', 'الخرطوم2', '0', '1', '2026-01-23 05:02:37'),
(2, 'مشروع فاروس', 'احمد', 'الخرطوم', '0', '1', '2026-01-25 23:07:51');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `name`, `phone`, `status`) VALUES
(1, 'اكوبيشن', '8569', '1'),
(2, 'محمد علي', '1111111111', '1');

-- --------------------------------------------------------

--
-- Table structure for table `supplierscontracts`
--

CREATE TABLE `supplierscontracts` (
  `id` int(11) NOT NULL,
  `supplier_id` int(250) NOT NULL,
  `contract_signing_date` date NOT NULL,
  `grace_period_days` int(11) DEFAULT 0,
  `contract_duration_months` int(11) DEFAULT 0,
  `contract_duration_days` int(11) DEFAULT 0,
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
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `daily_work_hours` varchar(20) DEFAULT NULL,
  `daily_operators` varchar(20) DEFAULT NULL,
  `first_party` varchar(255) DEFAULT NULL,
  `second_party` varchar(255) DEFAULT NULL,
  `witness_one` varchar(255) DEFAULT NULL,
  `witness_two` varchar(255) DEFAULT NULL,
  `project_id` int(255) NOT NULL DEFAULT 0,
  `status` tinyint(1) DEFAULT 1 COMMENT '1=نشط, 0=موقوف',
  `pause_reason` text DEFAULT NULL,
  `pause_date` date DEFAULT NULL COMMENT 'تاريخ إيقاف العقد',
  `resume_date` date DEFAULT NULL COMMENT 'تاريخ استئناف العقد',
  `termination_type` varchar(50) DEFAULT NULL COMMENT 'amicable أو hardship',
  `termination_reason` text DEFAULT NULL,
  `merged_with` int(11) DEFAULT NULL COMMENT 'معرف العقد المدموج معه'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `supplierscontracts`
--

INSERT INTO `supplierscontracts` (`id`, `supplier_id`, `contract_signing_date`, `grace_period_days`, `contract_duration_months`, `contract_duration_days`, `actual_start`, `actual_end`, `transportation`, `accommodation`, `place_for_living`, `workshop`, `equip_type`, `equip_size`, `equip_count`, `equip_target_per_month`, `equip_total_month`, `equip_total_contract`, `mach_type`, `mach_size`, `mach_count`, `mach_target_per_month`, `mach_total_month`, `mach_total_contract`, `hours_monthly_target`, `forecasted_contracted_hours`, `created_at`, `updated_at`, `daily_work_hours`, `daily_operators`, `first_party`, `second_party`, `witness_one`, `witness_two`, `project_id`, `status`, `pause_reason`, `termination_type`, `termination_reason`, `merged_with`) VALUES
(1, 2, '2026-01-01', 1, 3, 0, '2026-01-31', '2026-01-11', 'مشمولة', 'مشمولة', 'مشمولة', 'مشمولة', 'حفار', 340, 3, 600, 1800, 5400, 'قلاب', 340, 5, 600, 3000, 9000, 4800, 14400, '2026-01-24 19:11:35', '2026-01-24 19:11:35', '9', '', '', '', '', '', 1, 1, NULL, NULL, NULL, NULL);

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
  `user_id` int(50) NOT NULL DEFAULT 0,
  `time_notes` text NOT NULL DEFAULT 'لاتوجد ملاحظات',
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `timesheet`
--

INSERT INTO `timesheet` (`id`, `operator`, `driver`, `shift`, `date`, `shift_hours`, `executed_hours`, `bucket_hours`, `jackhammer_hours`, `extra_hours`, `extra_hours_total`, `standby_hours`, `dependence_hours`, `total_work_hours`, `work_notes`, `hr_fault`, `maintenance_fault`, `marketing_fault`, `approval_fault`, `other_fault_hours`, `total_fault_hours`, `fault_notes`, `start_seconds`, `start_minutes`, `start_hours`, `end_seconds`, `end_minutes`, `end_hours`, `counter_diff`, `fault_type`, `fault_department`, `fault_part`, `fault_details`, `general_notes`, `operator_hours`, `machine_standby_hours`, `jackhammer_standby_hours`, `bucket_standby_hours`, `extra_operator_hours`, `operator_standby_hours`, `operator_notes`, `type`, `user_id`, `time_notes`, `status`) VALUES
(1, '2', '10', 'D', '2026-01-21', 10, 10, 5, 5, 0, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 40, 5, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 0, 0, 0, 0, 0, 0, '', '1', 5, 'لاتوجد ملاحظات', '1');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `password`, `phone`, `role`, `project_id`, `parent_id`, `created_at`, `updated_at`) VALUES
(1, 'super admin', 'admin', '2025', '09', '1', '0', '0', '2025-09-09 12:36:24', '2025-09-09 12:36:24'),
(2, 'm', 'm', 'm', '4859490', '2', '0', '0', '2025-09-09 16:46:51', '2025-09-09 16:46:51'),
(3, 'y', 'y', 'y', '9095049', '3', '0', '0', '2025-09-09 16:47:11', '2025-09-09 16:47:11'),
(4, 'o', 'o', 'o', '0686095', '4', '0', '0', '2025-09-09 16:47:27', '2025-09-09 16:47:27'),
(5, 'r', 'r', 'r', '8089', '5', '1', '0', '2025-09-09 16:48:05', '2025-09-18 17:54:40'),
(6, 'محمد', 'q', 'q', '098098', '6', '1', '5', '2025-09-09 16:53:20', '2025-09-09 17:30:33'),
(7, 'w', 'w', 'w', '909', '7', '0', '5', '2025-09-09 16:54:07', '2025-09-09 16:54:07'),
(8, 't', 't', 't', '09090', '6', '0', '5', '2025-09-09 17:27:25', '2025-09-09 17:27:25'),
(10, 'Ù…Ø¯Ø±ÙŠØ± Ù…ÙˆÙ‚Ø¹ Ø¬Ø¯ÙŠØ¯', 'n', 'n', '9999', '5', '3', '0', '2025-09-21 13:27:53', '2025-09-21 13:27:53');

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
-- Indexes for table `supplier_contract_notes`
--
ALTER TABLE `supplier_contract_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contract_id` (`contract_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `contract_notes`
--
ALTER TABLE `contract_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `drivercontracts`
--
ALTER TABLE `drivercontracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `drivers`
--
ALTER TABLE `drivers`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `equipments`
--
ALTER TABLE `equipments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `equipment_drivers`
--
ALTER TABLE `equipment_drivers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `operations`
--
ALTER TABLE `operations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `supplier_contract_notes`
--
ALTER TABLE `supplier_contract_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `supplierscontracts`
--
ALTER TABLE `supplierscontracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `timesheet`
--
ALTER TABLE `timesheet`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `equipment_drivers`
--
ALTER TABLE `equipment_drivers`
  ADD CONSTRAINT `equipment_drivers_ibfk_1` FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `equipment_drivers_ibfk_2` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
