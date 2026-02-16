-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 16, 2026 at 05:08 PM
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
(1, 'C001', 'إكوبيشن', 'خاص', 'تعدين', '01123475758', 'equipationOfficial@gmail.com', '0912345678', 'نشط', 1, '2026-02-16 13:58:02', '2026-02-16 13:58:02'),
(2, 'C002', 'محمد علي', 'خاص', 'تجارة', '01123475758', 'equipationOfficial@gmail.com', '0912345678', 'نشط', 1, '2026-02-16 13:59:25', '2026-02-16 13:59:25');

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
(1, 1, '1', 320, 2, 2, 'ساعة', '16:34:00', '16:34:00', '16:34:00', '16:34:00', 10, 20, 100, 360, 10.00, 2, 2, 2, 2, 'دولار', '2026-02-16 14:35:30'),
(2, 2, '2', 320, 2, 2, 'ساعة', '16:38:00', '16:38:00', '16:38:00', '16:38:00', 10, 20, 100, 360, 10.00, 2, 2, 2, 2, 'دولار', '2026-02-16 14:37:37');

-- --------------------------------------------------------

--
-- Table structure for table `contracts`
--

CREATE TABLE `contracts` (
  `id` int(11) NOT NULL,
  `mine_id` int(250) NOT NULL COMMENT 'معرف المنجم من جدول mines',
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

INSERT INTO `contracts` (`id`, `mine_id`, `contract_signing_date`, `grace_period_days`, `contract_duration_months`, `contract_duration_days`, `equip_shifts_contract`, `shift_contract`, `equip_total_contract_daily`, `total_contract_permonth`, `total_contract_units`, `actual_start`, `actual_end`, `transportation`, `accommodation`, `place_for_living`, `workshop`, `hours_monthly_target`, `forecasted_contracted_hours`, `created_at`, `updated_at`, `daily_work_hours`, `daily_operators`, `first_party`, `second_party`, `witness_one`, `witness_two`, `price_currency_contract`, `paid_contract`, `payment_time`, `guarantees`, `payment_date`, `contract_status`, `pause_reason`, `pause_date`, `resume_date`, `termination_type`, `termination_reason`, `merged_with`, `status`) VALUES
(1, 1, '2026-02-01', 10, 0, 19, 2, 10, 100, 100, 100, '2026-02-10', '2026-02-28', 'مالك المعدة', 'مالك المعدة', 'مالك المعدة', 'مالك المعدة', 20, 360, '2026-02-16 14:35:30', NULL, '20', '3', 'محمد سيد', 'مبارك عوض', 'سمير الليل', 'مبارك محمود', 'دولار', '100', 'مقدم', 'رهن سيارة', '2026-02-20', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1),
(2, 3, '2026-02-01', 10, 0, 19, 2, 10, 100, 100, 100, '2026-02-10', '2026-02-28', 'مالك المعدة', 'مالك المعدة', 'مالك المعدة', 'مالك المعدة', 20, 360, '2026-02-16 14:37:37', NULL, '20', '3', 'محمد سيد', 'مبارك عوض', 'سمير الليل', 'مبارك محمود', 'دولار', '100', 'مقدم', 'رهن سيارة', '2026-02-20', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1);

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

-- --------------------------------------------------------

--
-- Table structure for table `drivercontractequipments`
--

CREATE TABLE `drivercontractequipments` (
  `id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL COMMENT 'معرف عقد السائق من جدول drivercontracts',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='معدات عقود السائقين';

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
  `equip_shifts_contract` int(11) DEFAULT 0 COMMENT 'عدد ورديات المعدات في العقد',
  `shift_contract` int(11) DEFAULT 0 COMMENT 'الوردية',
  `equip_total_contract_daily` decimal(10,2) DEFAULT 0.00 COMMENT 'إجمالي الوحدات اليومية للعقد',
  `total_contract_permonth` decimal(10,2) DEFAULT 0.00 COMMENT 'إجمالي وحدات العمل في الشهر',
  `total_contract_units` decimal(10,2) DEFAULT 0.00 COMMENT 'إجمالي وحدات العمل للعقد',
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
  `price_currency_contract` varchar(50) DEFAULT NULL COMMENT 'عملة العقد',
  `paid_contract` decimal(10,2) DEFAULT 0.00 COMMENT 'المبلغ المدفوع',
  `payment_time` varchar(50) DEFAULT NULL COMMENT 'وقت الدفع (مقدم/مؤخر)',
  `guarantees` text DEFAULT NULL COMMENT 'الضمانات',
  `payment_date` date DEFAULT NULL COMMENT 'تاريخ الدفع',
  `pause_reason` text DEFAULT NULL COMMENT 'سبب الإيقاف',
  `pause_date` date DEFAULT NULL COMMENT 'تاريخ الإيقاف',
  `resume_date` date DEFAULT NULL COMMENT 'تاريخ الاستئناف',
  `termination_type` varchar(50) DEFAULT NULL COMMENT 'نوع الإنهاء',
  `termination_reason` text DEFAULT NULL COMMENT 'سبب الإنهاء',
  `merged_with` int(11) DEFAULT NULL COMMENT 'دمج مع عقد آخر',
  `project_id` int(255) NOT NULL DEFAULT 0,
  `mine_id` int(11) DEFAULT NULL COMMENT 'معرف المنجم',
  `project_contract_id` int(11) DEFAULT NULL COMMENT 'معرف عقد المشروع',
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--

CREATE TABLE `drivers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(255) NOT NULL,
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `drivers`
--

INSERT INTO `drivers` (`id`, `name`, `phone`, `status`) VALUES
(1, 'اواب حسن محمد', '01123475758', 1),
(2, 'ابراهيم حسن محمد', '989879879', 1),
(3, 'امين حسن محمد', '09877687', 1),
(4, 'اسلام حسن محمد', '0915657579', 1);

-- --------------------------------------------------------

--
-- Table structure for table `driver_contract_notes`
--

CREATE TABLE `driver_contract_notes` (
  `id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL COMMENT 'معرف عقد السائق',
  `note` text NOT NULL COMMENT 'الملاحظة أو الإجراء المتخذ',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'تاريخ الإضافة'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='سجل التدقيق لإجراءات عقود السائقين';

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
(1, '1', 'EQTQ01', '1', '01', 1),
(2, '1', 'EQTQ02', '1', '02', 1),
(3, '2', 'YAEX01', '2', '03', 1),
(4, '2', 'YAEX02', '2', '04', 1);

-- --------------------------------------------------------

--
-- Table structure for table `equipments_types`
--

CREATE TABLE `equipments_types` (
  `id` int(11) NOT NULL,
  `type` varchar(100) NOT NULL,
  `status` enum('active','inactive','','') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `equipments_types`
--

INSERT INTO `equipments_types` (`id`, `type`, `status`, `created_at`, `updated_at`) VALUES
(1, 'حفار (320)', 'active', '2026-02-16 14:32:21', '2026-02-16 14:32:21'),
(2, 'قلاب', 'active', '2026-02-16 14:36:01', '2026-02-16 14:36:01');

-- --------------------------------------------------------

--
-- Table structure for table `equipment_drivers`
--

CREATE TABLE `equipment_drivers` (
  `id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `start_date` varchar(50) NOT NULL,
  `end_date` varchar(50) NOT NULL,
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `equipment_drivers`
--

INSERT INTO `equipment_drivers` (`id`, `equipment_id`, `driver_id`, `start_date`, `end_date`, `status`) VALUES
(1, 4, 1, '2026-02-01', '2026-02-28', 1),
(2, 3, 2, '2026-02-01', '2026-02-28', 1),
(3, 2, 3, '2026-02-01', '2026-02-28', 1),
(4, 1, 4, '2026-02-01', '2026-02-28', 1);

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
(1, 1, 'منجم 1', 'PR1M1', 'محمد سيد', 'ذهب', 'حفرة مفتوحة', '', 'شركة سودانية خاصة', '', 100.00, 'هكتار', 100.00, 'موظف مباشر لدى المالك', 1, 'الملاحظات', 1, '2026-02-16 14:12:05', '2026-02-16 14:12:05'),
(2, 1, 'منجم 2', 'PR1M2', 'محمد سيد', 'ذهب', 'تحت أرضي', '', 'شركة سودانية خاصة', '', 100.00, 'هكتار', 100.00, 'موظف مباشر لدى المالك', 1, 'ملاحظات', 1, '2026-02-16 14:12:43', '2026-02-16 14:12:43'),
(3, 2, 'منجم 1', 'PR2M1', 'حسن سيد', 'ذهب', 'تحت أرضي', '', 'شركة سودانية خاصة', '', 100.00, 'هكتار', 100.00, 'موظف مباشر لدى المالك', 1, 'ملاحظات', 1, '2026-02-16 14:14:38', '2026-02-16 14:14:38'),
(4, 2, 'منجم 2', 'PR2M2', 'حسن سيد', 'ذهب', 'مجمع معالجة/تركيز', '', 'شركة سودانية خاصة', '', 100.00, 'هكتار', 100.00, 'موظف مباشر لدى المالك', 1, 'ملاحظات', 1, '2026-02-16 14:15:07', '2026-02-16 14:15:07');

-- --------------------------------------------------------

--
-- Table structure for table `operations`
--

CREATE TABLE `operations` (
  `id` int(11) NOT NULL,
  `equipment` varchar(100) NOT NULL,
  `equipment_type` varchar(100) NOT NULL DEFAULT '0',
  `project_id` varchar(20) NOT NULL,
  `mine_id` varchar(10) NOT NULL,
  `contract_id` varchar(10) NOT NULL,
  `supplier_id` varchar(10) NOT NULL,
  `start` varchar(50) NOT NULL,
  `end` varchar(50) NOT NULL,
  `reason` text NOT NULL,
  `days` varchar(20) NOT NULL,
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `operations`
--

INSERT INTO `operations` (`id`, `equipment`, `equipment_type`, `project_id`, `mine_id`, `contract_id`, `supplier_id`, `start`, `end`, `reason`, `days`, `status`) VALUES
(1, '1', '1', '1', '1', '1', '1', '2026-02-01', '2026-02-28', '', '0', 1),
(2, '3', '2', '2', '3', '2', '2', '2026-02-01', '2026-02-28', '', '0', 1),
(3, '4', '2', '2', '3', '2', '2', '2026-02-01', '2026-02-28', '', '0', 1),
(4, '2', '1', '1', '1', '1', '1', '2026-02-01', '2026-02-28', '', '0', 1);

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
(1, 1, 'مشروع الروسية', 'إكوبيشن', 'شمال غرب المناقل', 'PR1', 'فئة المشروع', 'فرعي', 'نهر النيل', 'جيزان', 'سوق اللفة', '12', '31', '0', 1, 1, '2026-02-16 13:04:34', NULL),
(2, 2, 'مشروع سنار', 'محمد علي', 'غرب سنار', 'PR2', 'فئة المشروع', 'فرعي', 'سنار', 'سنار', 'سوق سنار', '14', '14', '0', 1, 1, '2026-02-16 13:07:38', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `status` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(1, 1, '1', 320, 2, 2, 'ساعة', '16:41:00', '16:41:00', '16:41:00', '16:41:00', 10.00, 20.00, 100.00, 360.00, 10.00, 'دولار', 2, 2, 2, 2, '2026-02-16 14:41:22'),
(2, 2, '2', 320, 2, 2, 'ساعة', '16:44:00', '16:44:00', '16:44:00', '16:44:00', 10.00, 20.00, 1000.00, 360.00, 10.00, 'دولار', 2, 2, 2, 2, '2026-02-16 14:44:03');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `supplier_code` varchar(100) DEFAULT NULL COMMENT 'الرمز/الكود للمورد',
  `supplier_type` enum('فرد','شركة','وسيط','مالك','جهة حكومية') DEFAULT NULL COMMENT 'نوع المورد',
  `dealing_nature` varchar(255) DEFAULT NULL COMMENT 'طبيعة التعامل',
  `equipment_types` text DEFAULT NULL COMMENT 'أنواع المعدات (مفصولة بفواصل)',
  `commercial_registration` varchar(100) DEFAULT NULL COMMENT 'رقم التسجيل التجاري/الرخصة',
  `identity_type` varchar(100) DEFAULT NULL COMMENT 'نوع الهوية',
  `identity_number` varchar(100) DEFAULT NULL COMMENT 'رقم الهوية/التسجيل',
  `identity_expiry_date` date DEFAULT NULL COMMENT 'تاريخ انتهاء الهوية',
  `email` varchar(255) DEFAULT NULL COMMENT 'البريد الإلكتروني',
  `phone_alternative` varchar(50) DEFAULT NULL COMMENT 'رقم هاتف بديل',
  `full_address` text DEFAULT NULL COMMENT 'العنوان الكامل',
  `contact_person_name` varchar(255) DEFAULT NULL COMMENT 'اسم جهة الاتصال الأساسية',
  `contact_person_phone` varchar(50) DEFAULT NULL COMMENT 'هاتف جهة الاتصال',
  `financial_registration_status` enum('مسجل رسميا','غير مسجل','تحت التسجيل','معفى من التسجيل') DEFAULT NULL COMMENT 'حالة التسجيل المالي',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `phone` varchar(15) NOT NULL,
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `name`, `supplier_code`, `supplier_type`, `dealing_nature`, `equipment_types`, `commercial_registration`, `identity_type`, `identity_number`, `identity_expiry_date`, `email`, `phone_alternative`, `full_address`, `contact_person_name`, `contact_person_phone`, `financial_registration_status`, `created_at`, `updated_at`, `phone`, `status`) VALUES
(1, 'اسماعيل محمد', 'SUP01', 'فرد', 'متعاقد مباشر', 'حفارات, مكنات تخريم, دوازر, شاحنات قلابة, شاحنات تناكر, جرافات, معدات معالجة', '878978798', 'بطاقة هوية وطنية', '99879879', '2026-10-16', 'sudan@gmail.com', '117776626', 'الكلاكلة اللفة', 'اسحاق سيد حسن غنيم', '117776626', 'تحت التسجيل', '2026-02-16 14:24:23', '2026-02-16 14:24:23', '0915657576', 1),
(2, 'يس محمد عبدالحافظ', 'SUP02', 'شركة', 'متعاقد مباشر', 'حفارات, دوازر, شاحنات تناكر, معدات معالجة', '79887979798', 'بطاقة هوية وطنية', '99879879', '2026-10-16', 'sudan@gmail.com', '117776626', 'العنوان', 'اسحاق سيد حسن غنيم', '117776626', 'مسجل رسميا', '2026-02-16 14:25:45', '2026-02-16 14:25:45', '0915657576', 1);

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
  `mine_id` int(11) DEFAULT NULL COMMENT 'معرف المنجم',
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

INSERT INTO `supplierscontracts` (`id`, `supplier_id`, `contract_signing_date`, `grace_period_days`, `contract_duration_months`, `contract_duration_days`, `equip_shifts_contract`, `shift_contract`, `equip_total_contract_daily`, `total_contract_permonth`, `total_contract_units`, `actual_start`, `actual_end`, `transportation`, `accommodation`, `place_for_living`, `workshop`, `equip_type`, `equip_size`, `equip_count`, `equip_target_per_month`, `equip_total_month`, `equip_total_contract`, `mach_type`, `mach_size`, `mach_count`, `mach_target_per_month`, `mach_total_month`, `mach_total_contract`, `hours_monthly_target`, `forecasted_contracted_hours`, `created_at`, `updated_at`, `daily_work_hours`, `daily_operators`, `first_party`, `second_party`, `witness_one`, `witness_two`, `price_currency_contract`, `paid_contract`, `payment_time`, `guarantees`, `payment_date`, `project_id`, `mine_id`, `project_contract_id`, `status`, `pause_reason`, `pause_date`, `resume_date`, `termination_type`, `termination_reason`, `merged_with`) VALUES
(1, 2, '2026-02-01', 10, 0, 19, 2, 10, 100, 100, 100, '2026-02-10', '2026-02-28', 'مالك المعدة', 'مالك المعدة', 'مالك المعدة', 'مالك المعدة', NULL, NULL, 0, 0, 0, 0, NULL, NULL, 0, 0, 0, 0, 20, 360, '2026-02-16 14:41:22', NULL, '20', '3', 'محمد سيد', 'مبارك عوض', 'سمير الليل', 'مبارك محمود', 'دولار', '150', 'مقدم', 'رهن سيارة', '2026-02-20', 1, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 1, '2026-02-01', 10, 0, 19, 2, 10, 100, 100, 1000, '2026-02-10', '2026-02-28', 'مالك المعدة', 'مالك المعدة', 'مالك المعدة', 'مالك المعدة', NULL, NULL, 0, 0, 0, 0, NULL, NULL, 0, 0, 0, 0, 20, 360, '2026-02-16 14:44:03', NULL, '20', '3', 'محمد سيد', 'مبارك عوض', 'سمير الليل', 'مبارك محمود', 'دولار', '100', 'مقدم', 'رهن سيارة', '2026-02-20', 2, 3, 2, 1, NULL, NULL, NULL, NULL, NULL, NULL);

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
(1, '1', '4', 'D', '2026-02-16', 10, 8, 2, 3, 3, 1, 1, 0, 10, '', 0, 0, 0, 0, 0, 1, '', 10, 20, 30, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 1, 0, 0, 0, 1, 'اليه ساعة واستعداد ساعة', '1', 5, 'تم التاكد', 2),
(2, '4', '3', 'D', '2026-02-16', 10, 10, 0, 0, 0, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 10, 20, 30, 10, 20, 20, '10 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 10, 0, 0, 0, 0, 0, '', '1', 5, 'تم', 2),
(3, '2', '2', 'D', '2026-02-16', 10, 8, 0, 0, 0, 1, 1, 1, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 1000, 0, 0, 1500, '500', '', '', '', '', '', 8, 1, 0, 0, 0, 1, '', '2', 6, 'لاتوجد ملاحظات', 1),
(4, '3', '1', 'D', '2026-02-16', 10, 10, 0, 0, 0, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 1000, 0, 0, 1500, '500', '', '', '', '', '', 10, 0, 0, 0, 0, 0, '', '2', 6, 'لاتوجد ملاحظات', 1);

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
(1, 'Super Admin', 'admin', '2026', '09', '1', '0', '0', '2026-02-16 13:56:31', '2026-02-16 13:56:31'),
(2, 'مورد', 'm', 'm', '09', '2', '0', '0', '2026-02-16 14:21:56', '2026-02-16 14:21:56'),
(3, 'اسطول', 'o', 'o', '09', '4', '0', '0', '2026-02-16 14:28:29', '2026-02-16 14:28:29'),
(4, 'مشغل', 'd', 'd', '09', '3', '0', '0', '2026-02-16 14:46:04', '2026-02-16 14:46:04'),
(5, 'روسية', 'r', 'r', '09', '5', '1', '0', '2026-02-16 15:29:15', '2026-02-16 15:29:15'),
(6, 'سنار', 's', 's', '09', '5', '2', '0', '2026-02-16 15:29:37', '2026-02-16 15:29:37'),
(7, 'يحيا محمد ', 'ry', 'ry', '01123475758', '6', '1', '5', '2026-02-16 15:58:55', '2026-02-16 15:58:55'),
(8, 'مراجع ساعات', 'ro', 'ro', '01123475758', '7', '1', '5', '2026-02-16 15:59:28', '2026-02-16 15:59:28');

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
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_contracts_mine` (`mine_id`),
  ADD KEY `fk_contracts_merged` (`merged_with`);

--
-- Indexes for table `contract_notes`
--
ALTER TABLE `contract_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `fk_contract_notes_contract` (`contract_id`),
  ADD KEY `fk_contract_notes_created_by` (`created_by`);

--
-- Indexes for table `drivercontractequipments`
--
ALTER TABLE `drivercontractequipments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contract_id` (`contract_id`);

--
-- Indexes for table `drivercontracts`
--
ALTER TABLE `drivercontracts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_drivercontracts_mine_id` (`mine_id`),
  ADD KEY `idx_drivercontracts_project_contract_id` (`project_contract_id`),
  ADD KEY `fk_drivercontracts_driver` (`driver_id`),
  ADD KEY `fk_drivercontracts_project` (`project_id`),
  ADD KEY `fk_drivercontracts_merged` (`merged_with`);

--
-- Indexes for table `drivers`
--
ALTER TABLE `drivers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `driver_contract_notes`
--
ALTER TABLE `driver_contract_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_driver_contract_notes_contract_id` (`contract_id`);

--
-- Indexes for table `equipments`
--
ALTER TABLE `equipments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `equipments_types`
--
ALTER TABLE `equipments_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `equipment_drivers`
--
ALTER TABLE `equipment_drivers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_equipment_drivers_equipment` (`equipment_id`),
  ADD KEY `fk_equipment_drivers_driver` (`driver_id`);

--
-- Indexes for table `mines`
--
ALTER TABLE `mines`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mine_code` (`mine_code`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `idx_mine_type` (`mine_type`),
  ADD KEY `idx_ownership_type` (`ownership_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `fk_mines_created_by` (`created_by`);

--
-- Indexes for table `operations`
--
ALTER TABLE `operations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `project`
--
ALTER TABLE `project`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_company_client_id` (`company_client_id`),
  ADD KEY `fk_project_created_by` (`created_by`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
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
  ADD KEY `idx_project_contract` (`project_contract_id`),
  ADD KEY `idx_supplierscontracts_mine_id` (`mine_id`),
  ADD KEY `fk_supplierscontracts_supplier` (`supplier_id`),
  ADD KEY `fk_supplierscontracts_project` (`project_id`),
  ADD KEY `fk_supplierscontracts_merged` (`merged_with`);

--
-- Indexes for table `supplier_contract_notes`
--
ALTER TABLE `supplier_contract_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contract_id` (`contract_id`),
  ADD KEY `fk_supplier_contract_notes_created_by` (`created_by`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `drivercontractequipments`
--
ALTER TABLE `drivercontractequipments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `drivercontracts`
--
ALTER TABLE `drivercontracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `drivers`
--
ALTER TABLE `drivers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `driver_contract_notes`
--
ALTER TABLE `driver_contract_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `equipments`
--
ALTER TABLE `equipments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `equipments_types`
--
ALTER TABLE `equipments_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `equipment_drivers`
--
ALTER TABLE `equipment_drivers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `mines`
--
ALTER TABLE `mines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `operations`
--
ALTER TABLE `operations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `project`
--
ALTER TABLE `project`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suppliercontractequipments`
--
ALTER TABLE `suppliercontractequipments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `supplierscontracts`
--
ALTER TABLE `supplierscontracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `supplier_contract_notes`
--
ALTER TABLE `supplier_contract_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `timesheet`
--
ALTER TABLE `timesheet`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `contractequipments`
--
ALTER TABLE `contractequipments`
  ADD CONSTRAINT `fk_contractequipments_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `contracts`
--
ALTER TABLE `contracts`
  ADD CONSTRAINT `fk_contracts_merged` FOREIGN KEY (`merged_with`) REFERENCES `contracts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_contracts_mine` FOREIGN KEY (`mine_id`) REFERENCES `mines` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `contract_notes`
--
ALTER TABLE `contract_notes`
  ADD CONSTRAINT `fk_contract_notes_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_contract_notes_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_contract_notes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `drivercontractequipments`
--
ALTER TABLE `drivercontractequipments`
  ADD CONSTRAINT `fk_drivercontractequipments_contract` FOREIGN KEY (`contract_id`) REFERENCES `drivercontracts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `drivercontracts`
--
ALTER TABLE `drivercontracts`
  ADD CONSTRAINT `fk_drivercontracts_driver` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_drivercontracts_merged` FOREIGN KEY (`merged_with`) REFERENCES `drivercontracts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_drivercontracts_mine` FOREIGN KEY (`mine_id`) REFERENCES `mines` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_drivercontracts_project` FOREIGN KEY (`project_id`) REFERENCES `project` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_drivercontracts_project_contract` FOREIGN KEY (`project_contract_id`) REFERENCES `contracts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `driver_contract_notes`
--
ALTER TABLE `driver_contract_notes`
  ADD CONSTRAINT `fk_driver_contract_notes_contract` FOREIGN KEY (`contract_id`) REFERENCES `drivercontracts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `equipment_drivers`
--
ALTER TABLE `equipment_drivers`
  ADD CONSTRAINT `fk_equipment_drivers_driver` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_equipment_drivers_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `mines`
--
ALTER TABLE `mines`
  ADD CONSTRAINT `fk_mines_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mines_project` FOREIGN KEY (`project_id`) REFERENCES `project` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `project`
--
ALTER TABLE `project`
  ADD CONSTRAINT `fk_project_client` FOREIGN KEY (`company_client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_project_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `suppliercontractequipments`
--
ALTER TABLE `suppliercontractequipments`
  ADD CONSTRAINT `fk_suppliercontractequipments_contract` FOREIGN KEY (`contract_id`) REFERENCES `supplierscontracts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `supplierscontracts`
--
ALTER TABLE `supplierscontracts`
  ADD CONSTRAINT `fk_supplierscontracts_merged` FOREIGN KEY (`merged_with`) REFERENCES `supplierscontracts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_supplierscontracts_mine` FOREIGN KEY (`mine_id`) REFERENCES `mines` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_supplierscontracts_project` FOREIGN KEY (`project_id`) REFERENCES `project` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_supplierscontracts_project_contract` FOREIGN KEY (`project_contract_id`) REFERENCES `contracts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_supplierscontracts_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `supplier_contract_notes`
--
ALTER TABLE `supplier_contract_notes`
  ADD CONSTRAINT `fk_supplier_contract_notes_contract` FOREIGN KEY (`contract_id`) REFERENCES `supplierscontracts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_supplier_contract_notes_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
