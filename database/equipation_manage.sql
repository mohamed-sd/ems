-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 03, 2026 at 08:17 PM
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
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `client_code` varchar(50) NOT NULL COMMENT 'كود العميل',
  `client_name` varchar(255) NOT NULL COMMENT 'اسم العميل',
  `entity_type` varchar(100) DEFAULT NULL COMMENT 'نوع الكيان',
  `sector_category` varchar(100) DEFAULT NULL COMMENT 'تصنيف القطاع',
  `phone` varchar(50) DEFAULT NULL COMMENT 'رقم الهاتف',
  `email` varchar(100) DEFAULT NULL COMMENT 'البريد الإلكتروني',
  `whatsapp` varchar(50) DEFAULT NULL COMMENT 'رقم الواتساب',
  `status` enum('نشط','متوقف') NOT NULL DEFAULT 'نشط' COMMENT 'حالة العميل',
  `created_by` int(11) DEFAULT NULL COMMENT 'معرف المستخدم الذي أضاف العميل',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'تاريخ الإضافة',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'تاريخ آخر تحديث'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول العملاء';

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `client_code`, `client_name`, `entity_type`, `sector_category`, `phone`, `email`, `whatsapp`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'C001', 'شركة النفط الوطنية', 'شركة حكومية', 'النفط والغاز', '0912345678', 'oil@example.com', '0912345678', 'نشط', 1, '2026-01-29 01:49:27', '2026-01-29 01:49:27'),
(2, 'C002', 'وزارة البنية التحتية', 'جهة حكومية', 'البنية التحتية', '0923456789', 'infrastructure@gov.sd', '0923456789', 'نشط', 1, '2026-01-29 01:49:27', '2026-01-29 01:49:27'),
(3, 'C003', 'شركة الطرق السريعة', 'شركة خاصة', 'الطرق والجسور', '0934567890', 'highways@example.com', '0934567890', 'نشط', 1, '2026-01-29 01:49:27', '2026-01-29 01:49:27'),
(4, 'CL-2301', 'شركة  نور', 'شركة', 'التعدين', '9909090', 'info@noor-co.com', '88888', 'نشط', 1, '2026-01-31 12:26:19', '2026-01-31 12:26:19');

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
  `shift1_start` time DEFAULT NULL COMMENT 'وقت بداية الوردية الأولى',
  `shift1_end` time DEFAULT NULL COMMENT 'وقت نهاية الوردية الأولى',
  `shift2_start` time DEFAULT NULL COMMENT 'وقت بداية الوردية الثانية',
  `shift2_end` time DEFAULT NULL COMMENT 'وقت نهاية الوردية الثانية',
  `shift_hours` int(11) DEFAULT 0 COMMENT 'إجمالي ساعات الوردية',
  `equip_total_month` int(11) DEFAULT NULL COMMENT 'إجمالي الساعات اليومية ',
  `equip_monthly_target` int(11) DEFAULT 0 COMMENT 'وحدات العمل في الشهر',
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

INSERT INTO `contractequipments` (`id`, `contract_id`, `equip_type`, `equip_size`, `equip_count`, `equip_shifts`, `equip_unit`, `shift1_start`, `shift1_end`, `shift2_start`, `shift2_end`, `shift_hours`, `equip_total_month`, `equip_monthly_target`, `equip_total_contract`, `equip_price`, `equip_operators`, `equip_supervisors`, `equip_technicians`, `equip_assistants`, `equip_price_currency`, `created_at`) VALUES
(1, 1, 'حفار', 340, 5, 0, 'ساعة', NULL, NULL, NULL, NULL, 20, 100, 0, 3100, 20.00, 3, 3, 3, 2, 'دولار', '2026-01-25 05:13:48'),
(2, 1, 'قلاب', 320, 3, 2, 'طن', NULL, NULL, NULL, NULL, 20, 60, 0, 1860, 25.00, 3, 2, 1, 0, 'دولار', '2026-01-25 05:13:48'),
(5, 3, 'حفار', 590, 5, 2, 'ساعة', NULL, NULL, NULL, NULL, 20, 100, 0, 2600, 20.00, 1, 1, 1, 1, 'دولار', '2026-01-25 20:03:59'),
(6, 3, 'حفار', 490, 2, 0, 'ساعة', NULL, NULL, NULL, NULL, 20, 40, 0, 1200, 0.00, 0, 0, 0, 0, NULL, '2026-01-25 20:58:49'),
(7, 3, 'قلاب', 56, 5, 0, 'ساعة', NULL, NULL, NULL, NULL, 20, 100, 0, 3000, 0.00, 0, 0, 0, 0, NULL, '2026-01-25 20:58:49'),
(12, 5, 'حفار', 350, 2, 3, 'ساعة', NULL, NULL, NULL, NULL, 200, 400, 0, 17200, 15.00, 1, 1, 1, 1, 'دولار', '2026-01-25 22:11:33'),
(13, 5, 'قلاب', 120, 5, 0, 'طن', NULL, NULL, NULL, NULL, 10, 50, 0, 2150, 15.00, 1, 1, 1, 1, 'دولار', '2026-01-25 22:11:33'),
(14, 2, 'قلاب', 56, 5, 3, 'طن', NULL, NULL, NULL, NULL, 20, 100, 0, 3000, 40.00, 1, 1, 1, 1, 'دولار', '2026-01-25 22:23:48'),
(15, 2, 'حفار', 590, 5, 600, 'ساعة', NULL, NULL, NULL, NULL, 20, 100, 0, 3000, 0.00, 0, 0, 0, 0, '', '2026-01-25 22:23:48'),
(16, 2, 'حفار', 490, 2, 0, 'ساعة', NULL, NULL, NULL, NULL, 20, 40, 0, 1200, 0.00, 0, 0, 0, 0, '', '2026-01-25 22:23:48'),
(17, 2, 'قلاب', 56, 5, 0, 'ساعة', NULL, NULL, NULL, NULL, 20, 100, 0, 3000, 0.00, 0, 0, 0, 0, '', '2026-01-25 22:23:48'),
(18, 8, 'حفار', 44, 2, 2, '', '14:35:00', '14:37:00', '14:37:00', '14:38:00', 20, 40, 2000, 1200, 30.00, 44, 44, 44, 44, 'دولار', '2026-01-29 12:37:05'),
(19, 9, 'حفار', 340, 2, 2, 'ساعة', '14:49:00', '14:50:00', '14:50:00', '14:50:00', 10, 20, 600, 600, 100.00, 1, 1, 1, 1, 'دولار', '2026-01-29 12:50:07'),
(20, 9, 'قلاب', 11, 1, 2, 'ساعة', '14:51:00', '18:49:00', '14:51:00', '14:52:00', 10, 10, 300, 300, 30.00, 1, 1, 1, 1, 'دولار', '2026-01-29 12:50:07'),
(26, 10, 'حفار', 33, 2, 0, '', '15:07:00', '15:08:00', '15:07:00', '15:03:00', 20, 40, 0, 3360, 20.00, 1, 0, 0, 0, 'دولار', '2026-01-29 13:36:07'),
(27, 10, 'حفار', 340, 2, 0, 'ساعة', NULL, NULL, NULL, NULL, 10, 20, 22, 1680, 0.00, 0, 0, 0, 0, '', '2026-01-29 13:36:07'),
(28, 10, 'قلاب', 11, 1, 0, 'ساعة', NULL, NULL, NULL, NULL, 10, 10, 0, 840, 0.00, 0, 0, 0, 0, '', '2026-01-29 13:36:07'),
(29, 10, 'حفار', 340, 2, 0, 'ساعة', NULL, NULL, NULL, NULL, 10, 20, 0, 1680, 0.00, 0, 0, 0, 0, '', '2026-01-29 13:36:07'),
(30, 10, 'قلاب', 11, 1, 0, 'ساعة', NULL, NULL, NULL, NULL, 10, 10, 0, 840, 0.00, 0, 0, 0, 0, '', '2026-01-29 13:36:07'),
(31, 4, 'حفار', 989, 3, 2, 'ساعة', NULL, NULL, NULL, NULL, 10, 30, 0, 420, 10.00, 0, 0, 0, 0, 'دولار', '2026-02-02 13:22:48');

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
  `equip_shifts_contract` int(11) DEFAULT 0 COMMENT 'عدد الورديات للعقد',
  `shift_contract` int(11) DEFAULT 0 COMMENT 'ساعات الوردية للعقد',
  `equip_total_contract_daily` int(11) DEFAULT 0 COMMENT 'إجمالي الوحدات يومياً للعقد',
  `total_contract_permonth` int(11) DEFAULT 0 COMMENT 'وحدات العمل في الشهر للعقد',
  `total_contract_units` int(11) DEFAULT 0 COMMENT 'إجمالي وحدات العقد',
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
  `price_currency_contract` varchar(20) DEFAULT NULL COMMENT 'عملة العقد',
  `paid_contract` varchar(100) DEFAULT NULL COMMENT 'المبلغ المدفوع',
  `payment_time` varchar(50) DEFAULT NULL COMMENT 'وقت الدفع (مقدم/مؤخر)',
  `guarantees` text DEFAULT NULL COMMENT 'الضمانات',
  `payment_date` date DEFAULT NULL COMMENT 'تاريخ الدفع',
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

INSERT INTO `contracts` (`id`, `project`, `contract_signing_date`, `grace_period_days`, `contract_duration_months`, `contract_duration_days`, `equip_shifts_contract`, `shift_contract`, `equip_total_contract_daily`, `total_contract_permonth`, `total_contract_units`, `actual_start`, `actual_end`, `transportation`, `accommodation`, `place_for_living`, `workshop`, `hours_monthly_target`, `forecasted_contracted_hours`, `created_at`, `updated_at`, `daily_work_hours`, `daily_operators`, `first_party`, `second_party`, `witness_one`, `witness_two`, `price_currency_contract`, `paid_contract`, `payment_time`, `guarantees`, `payment_date`, `contract_status`, `pause_reason`, `pause_date`, `resume_date`, `termination_type`, `termination_reason`, `merged_with`, `status`) VALUES
(1, 4, '2026-01-01', 10, 0, 31, 0, 0, 0, 0, 0, '2026-01-10', '2026-02-10', 'مالك المعدة', 'مالك المشروع', 'مالك المعدة', 'مالك المعدة', 160, 4960, '2026-01-25 05:13:48', '2026-01-25 05:13:48', '20', '6', 'محمد سيد', 'مبارك عوض', 'سمير الليل', 'مبارك محمود', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1),
(2, 1, '2026-01-01', 5, 1, 47, 0, 0, 0, 0, 0, '2026-01-31', '2026-03-19', 'مالك المعدة', 'مالك المعدة', 'مالك المعدة', 'مالك المعدة', 380, 11400, '2026-01-25 14:13:43', '2026-01-31 09:35:30', '20', '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, 1),
(3, 1, '2026-01-25', 3, 7, 236, 0, 0, 0, 0, 0, '2026-03-27', '2026-11-18', 'مالك المعدة', 'مالك المشروع', 'مالك المعدة', 'بدون', 100, 6800, '2026-01-25 20:03:59', '2026-01-25 21:50:29', '20', '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2, 1),
(4, 1, '2026-01-07', 2, 0, 15, 0, 0, 0, 0, 0, '2026-01-01', '2026-01-15', 'مالك المعدة', 'مالك المعدة', 'بدون', 'مالك المشروع', 30, 420, '2026-01-25 21:01:24', '2026-02-02 13:22:48', '30', '', '', '', '', '', '', '', '', '', '0000-00-00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1),
(5, 1, '2026-01-01', 1, 0, 43, 0, 0, 0, 0, 0, '2026-01-15', '2026-02-27', 'مالك المعدة', 'مالك المعدة', 'مالك المشروع', 'مالك المعدة', 450, 19350, '2026-01-25 22:11:33', '2026-01-25 22:11:33', '20', '5', 'احمد', 'محمد', 'علي', 'يوسف', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1),
(6, 2, '0000-00-00', 0, 0, 31, 0, 0, 0, 0, 0, '2026-01-01', '2026-01-31', '', '', '', '', 40, 1200, '2026-01-29 12:31:40', NULL, '44', '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1),
(7, 2, '0000-00-00', 44, 0, 31, 44, 4444, 44, 44, 44, '2026-01-01', '2026-01-31', '', '', 'بدون', '', 40, 1200, '2026-01-29 12:35:46', NULL, '20', '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1),
(8, 2, '0000-00-00', 44, 0, 31, 44, 4444, 44, 44, 44, '2026-01-01', '2026-02-05', '', '', 'بدون', '', 40, 1200, '2026-01-29 12:37:05', '2026-01-29 12:38:11', '20', '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-15', '2026-01-20', NULL, NULL, NULL, 1),
(9, 3, '2026-01-01', 5, 0, 27, 2, 10, 20, 700, 1200, '2026-01-31', '2026-02-27', 'مالك المعدة', 'مالك المعدة', 'مالك المعدة', 'مالك المعدة', 30, 900, '2026-01-29 12:50:07', '2026-01-29 13:32:53', '11', '12', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(10, 3, '2025-12-10', 0, 0, 29, 1, 1, 1, 1, 1, '2026-04-02', '2026-05-01', '', '', '', '', 100, 8400, '2026-01-29 13:03:22', '2026-01-29 14:51:19', '1111', '', '', '', '', '', 'دولار', '', 'مؤخر', '', '0000-00-00', NULL, NULL, NULL, NULL, NULL, NULL, 9, 1);

-- --------------------------------------------------------

--
-- Table structure for table `contract_notes`
--

CREATE TABLE `contract_notes` (
  `id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `note` text NOT NULL,
  `user_id` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `contract_notes`
--

INSERT INTO `contract_notes` (`id`, `contract_id`, `note`, `user_id`, `created_at`, `created_by`) VALUES
(20, 3, 'تم تجديد العقد بمدة 1 شهور - تاريخ الانتهاء الجديد: 2026-04-30', 0, '2026-01-23 06:05:58', NULL),
(21, 3, 'تم تسوية العقد: نقصان 100 ساعة - السبب: عطل', 0, '2026-01-23 06:06:43', NULL),
(22, 3, 'تم إيقاف العقد - السبب: تعسر', 0, '2026-01-23 06:07:07', NULL),
(23, 3, 'تم استئناف العقد - الملاحظات: تم', 0, '2026-01-23 06:07:22', NULL),
(24, 3, 'تم دمج العقد مع العقد رقم 4 - إجمالي الساعات: 7400', 0, '2026-01-23 06:09:03', NULL),
(25, 4, 'تم دمج هذا العقد مع العقد رقم 3', 0, '2026-01-23 06:09:03', NULL),
(26, 3, 'تم استئناف العقد - الملاحظات: dfd', 0, '2026-01-24 09:23:23', NULL),
(27, 3, 'تم دمج العقد مع العقد رقم 4 - إجمالي الساعات: 10400', 0, '2026-01-24 09:23:40', NULL),
(28, 4, 'تم دمج هذا العقد مع العقد رقم 3', 0, '2026-01-24 09:23:40', NULL),
(29, 3, 'تم استئناف العقد', 0, '2026-01-24 09:24:04', NULL),
(30, 3, 'تم تجديد العقد من 2026-04-30 إلى 2026-10-31 (مدة: 6 شهور)', 0, '2026-01-24 09:24:41', NULL),
(31, 3, 'تم دمج العقد مع العقد رقم 4 - إجمالي الساعات: 13400', 0, '2026-01-24 09:26:17', NULL),
(32, 4, 'تم دمج هذا العقد مع العقد رقم 3', 0, '2026-01-24 09:26:17', NULL),
(33, 3, 'تم استئناف العقد', 0, '2026-01-24 09:27:19', NULL),
(34, 3, 'تم دمج العقد مع العقد رقم 4 - إجمالي الساعات: 16400', 0, '2026-01-24 11:28:47', NULL),
(35, 4, 'تم دمج هذا العقد مع العقد رقم 3', 0, '2026-01-24 11:28:47', NULL),
(36, 3, 'تم دمج العقد مع العقد رقم 5 - إجمالي الساعات: 17600', 0, '2026-01-24 11:33:41', NULL),
(37, 5, 'تم دمج هذا العقد مع العقد رقم 3', 0, '2026-01-24 11:33:41', NULL),
(38, 3, 'تم إيقاف العقد - السبب: t', 0, '2026-01-24 13:59:20', NULL),
(39, 3, 'تم استئناف العقد بتاريخ 2026-01-24 - الملاحظات: 4', 0, '2026-01-24 13:59:29', NULL),
(40, 3, 'تم دمج العقد مع العقد رقم 5 - إجمالي الساعات: 18800', 0, '2026-01-24 14:03:50', NULL),
(41, 5, 'تم دمج هذا العقد مع العقد رقم 3', 0, '2026-01-24 14:03:50', NULL),
(42, 3, 'تم تجديد العقد من 2026-11-04 إلى 2026-12-25 (مدة: 1 شهور)', 0, '2026-01-24 14:04:58', NULL),
(43, 3, 'تم تجديد العقد من 2026-02-25 إلى 2026-02-28 (مدة: 0 شهور)', 0, '2026-01-25 20:58:29', NULL),
(44, 3, 'تم دمج العقد مع العقد رقم 2 - إجمالي الساعات: 6800', 0, '2026-01-25 20:58:49', NULL),
(45, 2, 'تم دمج هذا العقد مع العقد رقم 3', 0, '2026-01-25 20:58:49', NULL),
(46, 2, 'تم دمج العقد مع العقد رقم 3 - إجمالي الساعات: 11000', 0, '2026-01-25 21:26:00', NULL),
(47, 3, 'تم دمج هذا العقد مع العقد رقم 2', 0, '2026-01-25 21:26:00', NULL),
(48, 2, 'تم تجديد العقد من 2026-01-31 إلى 2026-02-28 (مدة: 0 شهور)', 0, '2026-01-25 21:26:55', NULL),
(49, 2, 'تم تجديد العقد من 2026-02-28 إلى 2026-07-22 (مدة: 4 شهور)', 0, '2026-01-25 21:27:52', NULL),
(50, 3, 'تم تجديد العقد من 2026-02-28 إلى 2026-03-27 (مدة: 0 شهور / 27 يوم)', 0, '2026-01-25 21:48:47', NULL),
(51, 3, 'تم تجديد العقد من 2026-03-27 إلى 2026-11-18 (مدة: 7 شهور / 236 يوم)', 0, '2026-01-25 21:50:29', NULL),
(52, 2, 'تم إيقاف العقد - السبب: الال', 0, '2026-01-25 23:37:49', NULL),
(53, 2, 'تم استئناف العقد بتاريخ 2026-01-26 - الملاحظات: تات', 0, '2026-01-25 23:37:56', NULL),
(54, 2, 'تم تجديد العقد من 2026-07-22 إلى 2026-11-25 (مدة: 4 شهور / 126 يوم)', 1, '2026-01-29 12:24:30', NULL),
(55, 8, 'تم إيقاف العقد بتاريخ 2026-01-15 - السبب: ثيثي', 1, '2026-01-29 12:37:47', NULL),
(56, 8, 'تم استئناف العقد بتاريخ 2026-01-20 - مدة الإيقاف: 5 يوم (تم تمديد العقد بإضافة 5 يوم إلى تاريخ الانتهاء) - الملاحظات: يي', 1, '2026-01-29 12:38:11', NULL),
(57, 9, 'تم تجديد العقد من 2026-01-31 إلى 2026-02-27 (مدة: 0 شهور / 27 يوم)', 1, '2026-01-29 12:50:53', NULL),
(58, 10, 'تم دمج العقد مع العقد رقم 9 - إجمالي الساعات: 4220 (العقد الحالي: 3320 + العقد المدموج: 900) - تم نسخ 2 معدة', 1, '2026-01-29 13:18:01', NULL),
(59, 9, 'تم دمج هذا العقد مع العقد رقم 10', 1, '2026-01-29 13:18:01', NULL),
(60, 10, 'تم دمج العقد مع العقد رقم 9 - إجمالي الساعات: 5120 (العقد الحالي: 4220 + العقد المدموج: 900) - تم نسخ 2 معدة', 1, '2026-01-29 13:32:53', NULL),
(61, 9, 'تم دمج هذا العقد مع العقد رقم 10 - تم تحويل العقد إلى غير ساري', 1, '2026-01-29 13:32:53', NULL),
(62, 10, 'تم تجديد العقد من 2026-04-02 إلى 2026-05-01 (مدة: 0 شهور / 29 يوم)', 1, '2026-01-29 14:51:19', NULL),
(63, 2, 'تم تجديد العقد من 2026-01-31 إلى 2026-03-19 (مدة: 1 شهور / 47 يوم)', 2, '2026-01-31 09:35:31', NULL);

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
(9, 'ooo', '0000000000', 1),
(10, 'omer', '0000000000000', 1),
(11, 'ali', '00000000000', 1),
(12, 'Ahmed', '09239', 1);

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
(10, '2', 'A23', '1', 'A23', 1),
(11, '2', 'Ex11', '1', 'Ex11', 1),
(12, '2', 'RE10', '2', 'RE10', 1),
(13, '1', 'Qs11', '1', 'Qs11', 1),
(14, '2', 'Qa1001', '1', 'Qa1001', 1),
(15, '2', 'EQ10', '1', '01', 1),
(16, '1', 'TM02', '2', '02', 1);

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
(1, 16, 10, 0),
(2, 11, 12, 0),
(3, 14, 11, 1),
(4, 13, 9, 1),
(5, 10, 10, 1),
(6, 12, 12, 1);

-- --------------------------------------------------------

--
-- Table structure for table `mines`
--

CREATE TABLE `mines` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL COMMENT 'معرف المشروع من جدول project',
  `mine_name` varchar(255) NOT NULL COMMENT 'اسم المنجم',
  `mine_code` varchar(50) NOT NULL COMMENT 'كود/رمز المنجم (فريد)',
  `manager_name` varchar(255) DEFAULT NULL COMMENT 'اسم مدير المنجم',
  `mineral_type` varchar(100) DEFAULT NULL COMMENT 'نوع المعدن (ذهب، فضة، نحاس، إلخ)',
  `mine_type` enum('حفرة مفتوحة','تحت أرضي','آبار','مهجور','مجمع معالجة/تركيز','موقع تخزين/مستودع','أخرى') NOT NULL COMMENT 'نوع المنجم',
  `mine_type_other` varchar(100) DEFAULT NULL COMMENT 'تفاصيل إذا كان النوع "أخرى"',
  `ownership_type` enum('تعدين أهلي/تقليدي','شركة سودانية خاصة','شركة حكومية/قطاع عام','شركة أجنبية','مشروع مشترك (سوداني-أجنبي)','أخرى') NOT NULL COMMENT 'نوع الملكية',
  `ownership_type_other` varchar(100) DEFAULT NULL COMMENT 'تفاصيل إذا كانت الملكية "أخرى"',
  `mine_area` decimal(10,2) DEFAULT NULL COMMENT 'مساحة المنجم (هكتار)',
  `mine_area_unit` enum('هكتار','كم²') DEFAULT 'هكتار' COMMENT 'وحدة قياس المساحة',
  `mining_depth` decimal(10,2) DEFAULT NULL COMMENT 'عمق التعدين (متر)',
  `contract_nature` enum('موظف مباشر لدى المالك','مقاول/شركة مقاولات') DEFAULT NULL COMMENT 'طبيعة التعاقد',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'حالة المنجم: 1=نشط، 0=غير نشط',
  `notes` text DEFAULT NULL COMMENT 'ملاحظات إضافية',
  `created_by` int(11) DEFAULT NULL COMMENT 'معرف المستخدم الذي أضاف السجل',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'تاريخ الإضافة',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'تاريخ آخر تحديث'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول المناجم المرتبطة بالمشاريع';

--
-- Dumping data for table `mines`
--

INSERT INTO `mines` (`id`, `project_id`, `mine_name`, `mine_code`, `manager_name`, `mineral_type`, `mine_type`, `mine_type_other`, `ownership_type`, `ownership_type_other`, `mine_area`, `mine_area_unit`, `mining_depth`, `contract_nature`, `status`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'منجم احمد', 'eca', 'سامبا', 'ذهب', 'مهجور', '', 'تعدين أهلي/تقليدي', '', 20.00, 'كم²', 500.00, 'موظف مباشر لدى المالك', 1, '', 1, '2026-02-02 17:43:48', '2026-02-02 17:43:48'),
(2, 2, 'hh', 'hh', 'hh', 'ذهب', 'موقع تخزين/مستودع', '', 'تعدين أهلي/تقليدي', '', 20.00, 'كم²', 32.00, 'موظف مباشر لدى المالك', 1, '', 1, '2026-02-03 15:36:38', '2026-02-03 15:36:38');

-- --------------------------------------------------------

--
-- Table structure for table `project`
--

CREATE TABLE `project` (
  `id` int(11) NOT NULL,
  `company_client_id` int(11) DEFAULT NULL COMMENT 'معرف العميل من جدول clients',
  `name` varchar(150) NOT NULL,
  `client` varchar(150) NOT NULL,
  `location` varchar(200) NOT NULL,
  `project_code` varchar(50) DEFAULT NULL COMMENT 'كود المشروع',
  `category` varchar(100) DEFAULT NULL COMMENT 'الفئة',
  `sub_sector` varchar(100) DEFAULT NULL COMMENT 'القطاع الفرعي',
  `state` varchar(100) DEFAULT NULL COMMENT 'الولاية',
  `region` varchar(100) DEFAULT NULL COMMENT 'المنطقة',
  `nearest_market` varchar(100) DEFAULT NULL COMMENT 'أقرب سوق',
  `latitude` varchar(50) DEFAULT NULL COMMENT 'خط العرض',
  `longitude` varchar(50) DEFAULT NULL COMMENT 'خط الطول',
  `total` varchar(50) NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL COMMENT 'معرف المستخدم المنشئ',
  `create_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'تاريخ آخر تحديث'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `project`
--

INSERT INTO `project` (`id`, `company_client_id`, `name`, `client`, `location`, `project_code`, `category`, `sub_sector`, `state`, `region`, `nearest_market`, `latitude`, `longitude`, `total`, `status`, `created_by`, `create_at`, `updated_at`) VALUES
(1, 1, 'مشروع الروسية', 'شركه الياس', 'الخرطوم2', 'PRJ-2026-001', 'طرق وجسور', 'الطرق السريعة', 'الخرطوم2', 'الخرطوم بحري', 'سوق ليبيا', '15.5527', '32.5599', '0', 1, NULL, '2026-01-23 05:02:37', '2026-02-03 17:34:16'),
(2, 2, 'مشروع فاروس', 'احمد', 'الخرطوم', 'PRJ-2026-002', 'مياه', 'محطات المياه', 'النيل الأزرق', 'الدمازين', 'سوق الدمازين', '11.7891', '34.3592', '0', 1, NULL, '2026-01-25 23:07:51', '2026-02-03 17:34:16'),
(3, 3, 'مشروع الروسيه جديد', 'شركة الطرق السريعة', 'كم', 'PRJ-2026-071', 'تعدين', 'الطرق السريعة', 'جنوب الجزيره', 'الاسكندرية', 'سوق دلتا', '90', '18', '0', 1, NULL, '2026-01-29 11:46:42', '2026-02-03 17:34:16'),
(4, 2, 'مشروع طريق الخرطوم - بورتسودان', 'وزارة البنية التحتية', 'كم', 'PRJ-2026-001', 'طرق وجسور', 'الطرق السريعة', 'الخرطوم2', 'الخرطوم بحري', 'سوق ليبيا', '15.5527', '32.5599', '0', 1, NULL, '2026-01-29 13:47:24', '2026-02-03 17:34:16'),
(5, 4, 'مشروع الطريق الدائري', 'شركة  نور', 'نن', 'P4001', 'بنية تحتية', 'الطرق والجسور', 'القاهرة', 'مدينة نصر', 'سوق العبور', '30.0444', '31.2357', '0', 1, NULL, '2026-01-31 11:55:29', '2026-02-03 17:34:16');

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
(1, '13', '1', '1', '2026-01-12', '2026-01-31', '0', 1),
(2, '10', '1', '1', '2026-01-14', '2026-01-24', '0', 1),
(3, '12', '2', '1', '2026-01-06', '2026-01-31', '0', 1);

-- --------------------------------------------------------

--
-- Table structure for table `suppliercontractequipments`
--

CREATE TABLE `suppliercontractequipments` (
  `id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL COMMENT 'معرف عقد المورد من جدول supplierscontracts',
  `equip_type` varchar(100) DEFAULT NULL COMMENT 'نوع المعدة (حفار، قلاب، خرامة)',
  `equip_size` int(11) DEFAULT NULL COMMENT 'حجم المعدة',
  `equip_count` int(11) DEFAULT NULL COMMENT 'عدد المعدات',
  `equip_shifts` int(11) DEFAULT NULL COMMENT 'عدد الورديات',
  `equip_unit` varchar(50) DEFAULT NULL COMMENT 'وحدة القياس (ساعة، طن، متر)',
  `shift1_start` time DEFAULT NULL COMMENT 'بداية الوردية الأولى',
  `shift1_end` time DEFAULT NULL COMMENT 'نهاية الوردية الأولى',
  `shift2_start` time DEFAULT NULL COMMENT 'بداية الوردية الثانية',
  `shift2_end` time DEFAULT NULL COMMENT 'نهاية الوردية الثانية',
  `shift_hours` decimal(10,2) DEFAULT NULL COMMENT 'ساعات الوردية',
  `equip_total_month` decimal(10,2) DEFAULT NULL COMMENT 'إجمالي الوحدات يومياً',
  `equip_monthly_target` decimal(10,2) DEFAULT NULL COMMENT 'وحدات العمل في الشهر',
  `equip_total_contract` decimal(10,2) DEFAULT NULL COMMENT 'إجمالي وحدات العقد',
  `equip_price` decimal(10,2) DEFAULT NULL COMMENT 'السعر للوحدة',
  `equip_price_currency` varchar(20) DEFAULT NULL COMMENT 'العملة (دولار، جنيه)',
  `equip_operators` int(11) DEFAULT NULL COMMENT 'عدد المشغلين',
  `equip_supervisors` int(11) DEFAULT NULL COMMENT 'عدد المشرفين',
  `equip_technicians` int(11) DEFAULT NULL COMMENT 'عدد الفنيين',
  `equip_assistants` int(11) DEFAULT NULL COMMENT 'عدد المساعدين',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='معدات عقود الموردين';

--
-- Dumping data for table `suppliercontractequipments`
--

INSERT INTO `suppliercontractequipments` (`id`, `contract_id`, `equip_type`, `equip_size`, `equip_count`, `equip_shifts`, `equip_unit`, `shift1_start`, `shift1_end`, `shift2_start`, `shift2_end`, `shift_hours`, `equip_total_month`, `equip_monthly_target`, `equip_total_contract`, `equip_price`, `equip_price_currency`, `equip_operators`, `equip_supervisors`, `equip_technicians`, `equip_assistants`, `created_at`) VALUES
(2, 2, 'حفار', 340, 3, 2, 'ساعة', '00:00:00', '00:00:00', '00:00:00', '00:00:00', 10.00, 30.00, 0.00, 990.00, 0.00, '', 1, 0, 0, 0, '2026-01-31 09:45:22'),
(3, 3, 'حفار', 5, 1, 0, '', '00:00:00', '00:00:00', '00:00:00', '00:00:00', 10.00, 10.00, 0.00, 260.00, 0.00, '', 0, 0, 0, 0, '2026-02-02 13:00:35');

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
(1, 'اكوبيشن', '8569', 1),
(2, 'محمد علي', '1111111111', 1);

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
  `equip_shifts_contract` int(11) DEFAULT 0 COMMENT 'عدد الورديات في العقد',
  `shift_contract` int(11) DEFAULT 0 COMMENT 'ساعات الوردية للعقد',
  `equip_total_contract_daily` int(11) DEFAULT 0 COMMENT 'إجمالي العقد اليومي',
  `total_contract_permonth` int(11) DEFAULT 0 COMMENT 'إجمالي العقد شهرياً',
  `total_contract_units` int(11) DEFAULT 0 COMMENT 'إجمالي وحدات العقد',
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
  `price_currency_contract` varchar(50) DEFAULT NULL COMMENT 'عملة العقد (دولار/جنيه)',
  `paid_contract` varchar(100) DEFAULT NULL COMMENT 'المبلغ المدفوع',
  `payment_time` varchar(50) DEFAULT NULL COMMENT 'وقت الدفع (مقدم/مؤخر)',
  `guarantees` text DEFAULT NULL COMMENT 'الضمانات',
  `payment_date` date DEFAULT NULL COMMENT 'تاريخ الدفع',
  `project_id` int(255) NOT NULL DEFAULT 0,
  `project_contract_id` int(11) DEFAULT NULL COMMENT 'معرف عقد المشروع المرتبط',
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

INSERT INTO `supplierscontracts` (`id`, `supplier_id`, `contract_signing_date`, `grace_period_days`, `contract_duration_months`, `contract_duration_days`, `equip_shifts_contract`, `shift_contract`, `equip_total_contract_daily`, `total_contract_permonth`, `total_contract_units`, `actual_start`, `actual_end`, `transportation`, `accommodation`, `place_for_living`, `workshop`, `equip_type`, `equip_size`, `equip_count`, `equip_target_per_month`, `equip_total_month`, `equip_total_contract`, `mach_type`, `mach_size`, `mach_count`, `mach_target_per_month`, `mach_total_month`, `mach_total_contract`, `hours_monthly_target`, `forecasted_contracted_hours`, `created_at`, `updated_at`, `daily_work_hours`, `daily_operators`, `first_party`, `second_party`, `witness_one`, `witness_two`, `price_currency_contract`, `paid_contract`, `payment_time`, `guarantees`, `payment_date`, `project_id`, `project_contract_id`, `status`, `pause_reason`, `pause_date`, `resume_date`, `termination_type`, `termination_reason`, `merged_with`) VALUES
(3, 2, '2026-02-04', 2, 0, 27, 0, 0, 0, 0, 0, '2026-02-01', '2026-02-27', '', '', '', '', NULL, NULL, 0, 0, 0, 0, NULL, NULL, 0, 0, 0, 0, 10, 260, '2026-02-02 13:00:35', NULL, '20', '0', '', '', '', '', '', '', '', '', '0000-00-00', 1, 4, 1, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `supplier_contract_notes`
--

CREATE TABLE `supplier_contract_notes` (
  `id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `note` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supplier_contract_notes`
--

INSERT INTO `supplier_contract_notes` (`id`, `contract_id`, `note`, `created_at`, `created_by`) VALUES
(1, 2, 'تم إيقاف العقد بتاريخ 2026-01-31 - السبب: trgtr', '2026-01-31 11:40:21', NULL),
(2, 2, 'تم استئناف العقد بتاريخ 2026-02-18 - مدة الإيقاف: 18 يوم (تم تمديد العقد بإضافة 18 يوم إلى تاريخ الانتهاء)', '2026-01-31 11:40:51', NULL),
(3, 2, 'تم تسوية العقد: نقصان 100 ساعة', '2026-01-31 11:42:19', NULL),
(4, 2, 'تم تسوية العقد: زيادة 100 ساعة', '2026-01-31 11:43:08', NULL),
(5, 2, 'تم تسوية العقد: نقصان 50 ساعة', '2026-01-31 11:43:42', NULL);

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
(1, '2', '10', 'D', '2026-01-21', 10, 10, 5, 5, 0, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 40, 5, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 0, 0, 0, 0, 0, 0, '', '1', 5, 'لاتوجد ملاحظات', 1);

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
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_client_code` (`client_code`),
  ADD KEY `idx_client_name` (`client_name`),
  ADD KEY `idx_status` (`status`);

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
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`);

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
-- Indexes for table `mines`
--
ALTER TABLE `mines`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mine_code` (`mine_code`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `idx_mine_type` (`mine_type`),
  ADD KEY `idx_ownership_type` (`ownership_type`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `project`
--
ALTER TABLE `project`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_company_client_id` (`company_client_id`);

--
-- Indexes for table `operations`
--
ALTER TABLE `operations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `suppliercontractequipments`
--
ALTER TABLE `suppliercontractequipments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contract_id` (`contract_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `supplierscontracts`
--
ALTER TABLE `supplierscontracts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project_contract` (`project_contract_id`);

--
-- Indexes for table `supplier_contract_notes`
--
ALTER TABLE `supplier_contract_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contract_id` (`contract_id`);

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
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `contractequipments`
--
ALTER TABLE `contractequipments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `contract_notes`
--
ALTER TABLE `contract_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

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
-- AUTO_INCREMENT for table `mines`
--
ALTER TABLE `mines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `project`
--
ALTER TABLE `project`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `operations`
--
ALTER TABLE `operations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `suppliercontractequipments`
--
ALTER TABLE `suppliercontractequipments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `supplierscontracts`
--
ALTER TABLE `supplierscontracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `supplier_contract_notes`
--
ALTER TABLE `supplier_contract_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
