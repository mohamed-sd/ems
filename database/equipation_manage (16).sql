-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 05, 2026 at 04:25 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

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
-- Table structure for table `approval_requests`
--

CREATE TABLE `approval_requests` (
  `id` int(11) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `payload` longtext NOT NULL,
  `requested_by` int(11) NOT NULL,
  `current_step` int(11) DEFAULT 1,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `executed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `approval_requests`
--

INSERT INTO `approval_requests` (`id`, `entity_type`, `entity_id`, `action`, `payload`, `requested_by`, `current_step`, `status`, `rejection_reason`, `approved_at`, `rejected_at`, `executed_at`, `created_at`, `updated_at`) VALUES
(1, 'project', 1, 'deactivate', '{\"summary\":{\"table\":\"project\",\"operation\":\"update\",\"old_values\":{\"id\":1,\"company_client_id\":1,\"name\":\"مشروع الروسيه جديد\",\"client\":\"وزارة البنية التحتية\",\"location\":\"الخرطوم2\",\"project_code\":\"PRJ-2026-001\",\"category\":\"\",\"sub_sector\":\"التعدين\",\"state\":\"الخرطوم\",\"region\":\"الكويت\",\"nearest_market\":\"سوق بحري\",\"latitude\":\"15.5527\",\"longitude\":\"32.5599\",\"total\":\"0\",\"status\":1,\"created_by\":1,\"create_at\":\"2026-02-16 23:13:41\",\"updated_at\":null},\"new_values\":{\"company_client_id\":1,\"name\":\"مشروع الروسيه جديد\",\"client\":\"وزارة البنية التحتية\",\"location\":\"الخرطوم2\",\"project_code\":\"PRJ-2026-001\",\"category\":\"\",\"sub_sector\":\"التعدين\",\"state\":\"الخرطوم\",\"region\":\"الكويت\",\"nearest_market\":\"سوق بحري\",\"latitude\":\"15.5527\",\"longitude\":\"32.5599\",\"total\":0,\"status\":\"0\",\"updated_at\":\"2026-03-03 14:28:27\"}},\"operations\":[{\"db_action\":\"update\",\"table\":\"project\",\"where\":{\"id\":1},\"data\":{\"company_client_id\":1,\"name\":\"مشروع الروسيه جديد\",\"client\":\"وزارة البنية التحتية\",\"location\":\"الخرطوم2\",\"project_code\":\"PRJ-2026-001\",\"category\":\"\",\"sub_sector\":\"التعدين\",\"state\":\"الخرطوم\",\"region\":\"الكويت\",\"nearest_market\":\"سوق بحري\",\"latitude\":\"15.5527\",\"longitude\":\"32.5599\",\"total\":0,\"status\":\"0\",\"updated_at\":\"2026-03-03 14:28:27\"}}]}', 1, NULL, 'approved', NULL, '2026-03-03 15:28:27', NULL, '2026-03-03 15:28:27', '2026-03-03 15:28:27', '2026-03-03 15:28:27'),
(2, 'project', 1, 'update', '{\"summary\":{\"table\":\"project\",\"operation\":\"update\",\"old_values\":{\"id\":1,\"company_client_id\":1,\"name\":\"مشروع الروسيه جديد\",\"client\":\"وزارة البنية التحتية\",\"location\":\"الخرطوم2\",\"project_code\":\"PRJ-2026-001\",\"category\":\"\",\"sub_sector\":\"التعدين\",\"state\":\"الخرطوم\",\"region\":\"الكويت\",\"nearest_market\":\"سوق بحري\",\"latitude\":\"15.5527\",\"longitude\":\"32.5599\",\"total\":\"0\",\"status\":0,\"created_by\":1,\"create_at\":\"2026-02-16 23:13:41\",\"updated_at\":\"2026-03-03 14:28:27\"},\"new_values\":{\"company_client_id\":1,\"name\":\"مشروع الروسيه جديد\",\"client\":\"وزارة البنية التحتية\",\"location\":\"الخرطوم2\",\"project_code\":\"PRJ-2026-001\",\"category\":\"\",\"sub_sector\":\"التعدين\",\"state\":\"الخرطوم\",\"region\":\"الكويت\",\"nearest_market\":\"سوق بحري\",\"latitude\":\"15.5527\",\"longitude\":\"32.5599\",\"total\":0,\"status\":\"1\",\"updated_at\":\"2026-03-03 14:29:34\"}},\"operations\":[{\"db_action\":\"update\",\"table\":\"project\",\"where\":{\"id\":1},\"data\":{\"company_client_id\":1,\"name\":\"مشروع الروسيه جديد\",\"client\":\"وزارة البنية التحتية\",\"location\":\"الخرطوم2\",\"project_code\":\"PRJ-2026-001\",\"category\":\"\",\"sub_sector\":\"التعدين\",\"state\":\"الخرطوم\",\"region\":\"الكويت\",\"nearest_market\":\"سوق بحري\",\"latitude\":\"15.5527\",\"longitude\":\"32.5599\",\"total\":0,\"status\":\"1\",\"updated_at\":\"2026-03-03 14:29:34\"}}]}', 1, NULL, 'approved', NULL, '2026-03-03 15:29:34', NULL, '2026-03-03 15:29:34', '2026-03-03 15:29:34', '2026-03-03 15:29:34'),
(3, 'contract', 1, 'update_services', '{\"summary\":{\"old_values\":{\"transportation\":\"مالك المعدة\",\"accommodation\":\"مالك المعدة\",\"place_for_living\":\"مالك المعدة\",\"workshop\":\"مالك المعدة\"},\"new_values\":{\"transportation\":\"مالك المشروع\",\"accommodation\":\"مالك المعدة\",\"place_for_living\":\"مالك المعدة\",\"workshop\":\"مالك المعدة\",\"updated_at\":\"2026-03-03 14:32:25\"}},\"operations\":[{\"db_action\":\"update\",\"table\":\"contracts\",\"where\":{\"id\":1},\"data\":{\"transportation\":\"مالك المشروع\",\"accommodation\":\"مالك المعدة\",\"place_for_living\":\"مالك المعدة\",\"workshop\":\"مالك المعدة\",\"updated_at\":\"2026-03-03 14:32:25\"}},{\"db_action\":\"insert\",\"table\":\"contract_notes\",\"data\":{\"contract_id\":1,\"note\":\"طلب تحديث الخدمات بالعقد\",\"user_id\":1,\"created_at\":\"2026-03-03 14:32:25\"}}]}', 1, NULL, 'approved', NULL, '2026-03-03 15:32:25', NULL, '2026-03-03 15:32:25', '2026-03-03 15:32:25', '2026-03-03 15:32:25'),
(4, 'equipment', 2, 'deactivate_equipment', '{\"summary\":{\"operation_id\":4,\"equipment_id\":2,\"equipment_code\":\"Wq1\",\"equipment_name\":\"Wq1\",\"requested_by_role\":\"10\",\"reason\":\"طلب إيقاف آلية من جدول التشغيل\",\"current_availability_status\":\"متاحة للعمل\",\"new_availability_status\":\"موقوفة للصيانة\"},\"operations\":[{\"db_action\":\"update\",\"table\":\"equipments\",\"where\":{\"id\":2},\"data\":{\"availability_status\":\"موقوفة للصيانة\"}},{\"db_action\":\"update\",\"table\":\"operations\",\"where\":{\"id\":4},\"data\":{\"status\":3}}]}', 7, NULL, 'approved', NULL, '2026-03-03 16:23:22', NULL, '2026-03-03 16:23:22', '2026-03-03 16:03:44', '2026-03-03 16:23:22'),
(5, 'equipment', 2, 'deactivate_equipment', '{\"summary\":{\"operation_id\":4,\"equipment_id\":2,\"equipment_code\":\"Wq1\",\"equipment_name\":\"Wq1\",\"requested_by_role\":\"10\",\"reason\":\"طلب إيقاف آلية من جدول التشغيل\",\"current_availability_status\":\"موقوفة للصيانة\",\"new_availability_status\":\"موقوفة للصيانة\"},\"operations\":[{\"db_action\":\"update\",\"table\":\"equipments\",\"where\":{\"id\":2},\"data\":{\"availability_status\":\"موقوفة للصيانة\"}},{\"db_action\":\"update\",\"table\":\"operations\",\"where\":{\"id\":4},\"data\":{\"status\":3}}]}', 7, NULL, 'approved', NULL, '2026-03-03 16:25:20', NULL, '2026-03-03 16:25:20', '2026-03-03 16:24:10', '2026-03-03 16:25:20'),
(6, 'equipment', 2, 'deactivate_equipment', '{\"summary\":{\"operation_id\":4,\"equipment_id\":2,\"equipment_code\":\"Wq1\",\"equipment_name\":\"Wq1\",\"requested_by_role\":\"10\",\"reason\":\"طلب إيقاف آلية من جدول التشغيل\",\"current_availability_status\":\"موقوفة للصيانة\",\"new_availability_status\":\"موقوفة للصيانة\"},\"operations\":[{\"db_action\":\"update\",\"table\":\"equipments\",\"where\":{\"id\":2},\"data\":{\"availability_status\":\"موقوفة للصيانة\"}},{\"db_action\":\"update\",\"table\":\"operations\",\"where\":{\"id\":4},\"data\":{\"status\":3}}]}', 7, NULL, 'approved', NULL, '2026-03-03 17:30:54', NULL, '2026-03-03 17:30:54', '2026-03-03 17:17:08', '2026-03-03 17:30:54'),
(7, 'driver', 3, 'deactivate_driver', '{\"summary\":{\"equipment_driver_id\":25,\"driver_id\":3,\"driver_name\":\"محمد أحمد علي\",\"equipment_id\":7,\"equipment_code\":\"TQ-002\",\"equipment_name\":\"هيونداي\",\"current_status\":1,\"new_status\":0,\"action\":\"إيقاف\",\"requested_by_role\":\"10\",\"reason\":\"طلب إيقاف مشغل من شاشة إدارة المشغلين\"},\"operations\":[{\"db_action\":\"update\",\"table\":\"equipment_drivers\",\"where\":{\"id\":25},\"data\":{\"status\":0}}]}', 7, NULL, 'approved', NULL, '2026-03-03 17:29:50', NULL, '2026-03-03 17:29:50', '2026-03-03 17:22:51', '2026-03-03 17:29:50'),
(8, 'equipment', 2, 'deactivate_equipment', '{\"summary\":{\"operation_id\":4,\"equipment_id\":2,\"equipment_code\":\"Wq1\",\"equipment_name\":\"Wq1\",\"requested_by_role\":\"10\",\"reason\":\"طلب إيقاف آلية من جدول التشغيل\",\"current_availability_status\":\"موقوفة للصيانة\",\"new_availability_status\":\"موقوفة للصيانة\"},\"operations\":[{\"db_action\":\"update\",\"table\":\"equipments\",\"where\":{\"id\":2},\"data\":{\"availability_status\":\"موقوفة للصيانة\"}},{\"db_action\":\"update\",\"table\":\"operations\",\"where\":{\"id\":4},\"data\":{\"status\":3}}]}', 7, NULL, 'approved', NULL, '2026-03-04 01:45:57', NULL, '2026-03-04 01:45:57', '2026-03-04 01:45:02', '2026-03-04 01:45:57');

-- --------------------------------------------------------

--
-- Table structure for table `approval_steps`
--

CREATE TABLE `approval_steps` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `role_required` varchar(100) NOT NULL,
  `step_order` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `approval_steps`
--

INSERT INTO `approval_steps` (`id`, `request_id`, `role_required`, `step_order`, `approved_by`, `approved_at`, `status`, `note`, `created_at`) VALUES
(1, 1, '1,-1', 1, 1, '2026-03-03 15:28:27', 'approved', 'اعتماد تلقائي (منشئ الطلب يملك صلاحية المرحلة)', '2026-03-03 15:28:27'),
(2, 2, '1,-1', 1, 1, '2026-03-03 15:29:34', 'approved', 'اعتماد تلقائي (منشئ الطلب يملك صلاحية المرحلة)', '2026-03-03 15:29:34'),
(3, 3, '1,-1', 1, 1, '2026-03-03 15:32:25', 'approved', 'اعتماد تلقائي (منشئ الطلب يملك صلاحية المرحلة)', '2026-03-03 15:32:25'),
(4, 4, '4,-1', 1, 2, '2026-03-03 16:23:22', 'approved', 'تم الاعتماد', '2026-03-03 16:03:44'),
(5, 5, '4,-1', 1, 2, '2026-03-03 16:25:20', 'approved', 'تم اعتماد التعطيل', '2026-03-03 16:24:10'),
(6, 6, '4,-1', 1, 2, '2026-03-03 17:30:54', 'approved', '', '2026-03-03 17:17:08'),
(7, 7, '3,-1', 1, 4, '2026-03-03 17:29:50', 'approved', 'تم الاعتماد', '2026-03-03 17:22:51'),
(8, 8, '4,-1', 1, 2, '2026-03-04 01:45:57', 'approved', 'تم', '2026-03-04 01:45:02');

-- --------------------------------------------------------

--
-- Table structure for table `approval_workflow_rules`
--

CREATE TABLE `approval_workflow_rules` (
  `id` int(11) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `action` varchar(100) NOT NULL,
  `role_required` varchar(100) NOT NULL,
  `step_order` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `approval_workflow_rules`
--

INSERT INTO `approval_workflow_rules` (`id`, `entity_type`, `action`, `role_required`, `step_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'project', 'update', '1,-1', 1, 1, '2026-03-03 15:27:50', NULL),
(2, 'project', 'deactivate', '1,-1', 1, 1, '2026-03-03 15:27:50', NULL),
(3, 'project', 'delete', '-1', 1, 1, '2026-03-03 15:27:50', NULL),
(4, 'contract', 'renewal', '1,-1', 1, 1, '2026-03-03 15:27:50', NULL),
(5, 'contract', 'settlement', '1,-1', 1, 1, '2026-03-03 15:27:50', NULL),
(6, 'contract', 'pause', '1,-1', 1, 1, '2026-03-03 15:27:50', NULL),
(7, 'contract', 'resume', '1,-1', 1, 1, '2026-03-03 15:27:50', NULL),
(8, 'contract', 'terminate', '1,-1', 1, 1, '2026-03-03 15:27:50', NULL),
(9, 'contract', 'merge', '-1', 1, 1, '2026-03-03 15:27:50', NULL),
(10, 'contract', 'update_project_info', '1,-1', 1, 1, '2026-03-03 15:27:50', NULL),
(11, 'contract', 'update_services', '1,-1', 1, 1, '2026-03-03 15:27:50', NULL),
(12, 'contract', 'update_parties', '1,-1', 1, 1, '2026-03-03 15:27:50', NULL),
(13, 'contract', 'update_payment', '1,-1', 1, 1, '2026-03-03 15:27:50', NULL),
(14, 'contract', 'complete', '1,-1', 1, 1, '2026-03-03 15:27:50', NULL),
(15, 'timesheet', 'approve', '7,8,-1', 1, 1, '2026-03-03 15:27:50', NULL),
(16, 'timesheet', 'reject', '7,8,-1', 1, 1, '2026-03-03 15:27:50', NULL),
(32, 'equipment', 'deactivate_equipment', '4,-1', 1, 1, '2026-03-03 17:08:59', NULL),
(35, 'driver', 'activate_driver', '3,-1', 1, 1, '2026-03-03 17:08:59', NULL),
(36, 'driver', 'deactivate_driver', '3,-1', 1, 1, '2026-03-03 17:08:59', NULL),
(37, 'driver', 'reactivate_driver', '3,-1', 1, 1, '2026-03-03 17:08:59', NULL),
(38, 'equipment', 'reactivate_equipment', '4,-1', 1, 1, '2026-03-03 17:09:00', NULL);

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
(1, 'C002', 'وزارة البنية التحتية', 'خاص', 'بنية تحتية', '76887534', 'infotelecomwasla@gmail.com', '', 'نشط', 1, '2026-02-16 22:12:46', '2026-03-03 13:29:25'),
(2, 'CL-0015', 'شركة المستقبل للمقاولات', 'حكومي', 'بنية تحتية', '249123456789', 'info@future-co.com', '249123456789', 'نشط', 1, '2026-02-25 18:16:23', '2026-02-25 18:16:23');

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
  `equip_count_basic` int(11) DEFAULT 0 COMMENT 'عدد المعدات الأساسية',
  `equip_count_backup` int(11) DEFAULT 0 COMMENT 'عدد المعدات الاحتياطية',
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

INSERT INTO `contractequipments` (`id`, `contract_id`, `equip_type`, `equip_size`, `equip_count`, `equip_count_basic`, `equip_count_backup`, `equip_shifts`, `equip_unit`, `shift1_start`, `shift1_end`, `shift2_start`, `shift2_end`, `shift_hours`, `equip_total_month`, `equip_monthly_target`, `equip_total_contract`, `equip_price`, `equip_operators`, `equip_supervisors`, `equip_technicians`, `equip_assistants`, `equip_price_currency`, `created_at`) VALUES
(3, 2, '1', 120, 2, 2, 2, 0, '', NULL, NULL, NULL, NULL, 10, 20, 0, 600, 0.00, 0, 0, 0, 0, '', '2026-02-27 23:44:25'),
(4, 2, '2', 340, 2, 1, 1, 0, '', NULL, NULL, NULL, NULL, 10, 20, 0, 600, 0.00, 0, 0, 0, 0, '', '2026-02-27 23:44:25'),
(5, 2, '3', 120, 3, 1, 1, 0, '', NULL, NULL, NULL, NULL, 10, 30, 0, 900, 0.00, 0, 0, 0, 0, '', '2026-02-27 23:44:25'),
(6, 1, '1', 240, 4, 0, 0, 0, '', NULL, NULL, NULL, NULL, 10, 40, 0, 1240, 0.00, 0, 0, 0, 0, '', '2026-03-03 13:32:09'),
(7, 1, '2', 240, 2, 0, 0, 0, '', NULL, NULL, NULL, NULL, 10, 20, 0, 620, 0.00, 1, 0, 0, 1, '', '2026-03-03 13:32:09');

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
(1, 1, '2026-02-01', 3, 0, 31, 1, 1, 1, 1, 1, '2026-02-01', '2026-03-03', 'مالك المشروع', 'مالك المعدة', 'مالك المعدة', 'مالك المعدة', 60, 1860, '2026-02-16 22:17:51', '2026-03-03 12:32:25', '20', '2', '', '', '', '', 'دولار', '2000', 'مقدم', 'شيك', '2026-02-03', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1),
(2, 2, '2026-02-01', 5, 0, 31, 2, 10, 10, 0, 0, '2026-02-01', '2026-03-03', '', '', '', '', 70, 2100, '2026-02-27 23:44:25', NULL, '20', '', '', '', '', '', 'دولار', '2000', ' مؤخر', 'شيك', '2026-02-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1);

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
(1, 1, 'طلب تحديث الخدمات بالعقد', 1, '2026-03-03 12:32:25', NULL);

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
  `equip_count_basic` int(11) DEFAULT 0 COMMENT 'عدد المعدات الأساسية',
  `equip_count_backup` int(11) DEFAULT 0 COMMENT 'عدد المعدات الاحتياطية',
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
  `driver_code` varchar(50) DEFAULT NULL COMMENT 'الرمز/الكود الفريد للمشغل',
  `nickname` varchar(255) DEFAULT NULL COMMENT 'اسم الشهرة/الكنية',
  `identity_type` varchar(50) DEFAULT NULL COMMENT 'نوع الهوية',
  `identity_number` varchar(100) DEFAULT NULL COMMENT 'رقم الهوية',
  `identity_expiry_date` date DEFAULT NULL COMMENT 'تاريخ انتهاء الهوية',
  `license_number` varchar(100) DEFAULT NULL COMMENT 'رقم رخصة القيادة',
  `license_type` varchar(100) DEFAULT NULL COMMENT 'نوع رخصة القيادة',
  `license_expiry_date` date DEFAULT NULL COMMENT 'تاريخ انتهاء رخصة القيادة',
  `license_issuer` varchar(255) DEFAULT NULL COMMENT 'جهة إصدار الرخصة',
  `specialized_equipment` text DEFAULT NULL COMMENT 'نوع المعدة المتخصص فيها (متعدد)',
  `years_in_field` int(11) DEFAULT NULL COMMENT 'سنوات العمل في المجال',
  `years_on_equipment` int(11) DEFAULT NULL COMMENT 'سنوات العمل على هذا النوع من المعدات',
  `skill_level` varchar(50) DEFAULT NULL COMMENT 'مستوى الكفاءة المهنية',
  `certificates` text DEFAULT NULL COMMENT 'الشهادات والتدريبات',
  `owner_supervisor` varchar(255) DEFAULT NULL COMMENT 'اسم المالك/المشرف المباشر',
  `supplier_id` int(11) DEFAULT NULL COMMENT 'المورد الذي يعمل معه',
  `employment_affiliation` varchar(100) DEFAULT NULL COMMENT 'تبعية المشغل',
  `salary_type` varchar(50) DEFAULT NULL COMMENT 'نوع الراتب/الأجر',
  `monthly_salary` decimal(10,2) DEFAULT NULL COMMENT 'المبلغ الشهري التقريبي',
  `email` varchar(255) DEFAULT NULL COMMENT 'البريد الإلكتروني',
  `address` text DEFAULT NULL COMMENT 'العنوان',
  `performance_rating` varchar(50) DEFAULT NULL COMMENT 'تقييم الكفاءة التشغيلية',
  `behavior_record` varchar(50) DEFAULT NULL COMMENT 'سجل السلوك والانضباط',
  `accident_record` varchar(50) DEFAULT NULL COMMENT 'سجل الحوادث والأعطال',
  `health_status` varchar(50) DEFAULT NULL COMMENT 'الحالة الصحية',
  `health_issues` text DEFAULT NULL COMMENT 'المشاكل الصحية المعروفة',
  `vaccinations_status` varchar(50) DEFAULT NULL COMMENT 'التطعيمات والفحوصات',
  `previous_employer` varchar(255) DEFAULT NULL COMMENT 'اسم جهة التوظيف السابقة',
  `employment_duration` varchar(100) DEFAULT NULL COMMENT 'مدة العمل معهم',
  `reference_contact` varchar(255) DEFAULT NULL COMMENT 'مرجع للاتصال',
  `general_notes` text DEFAULT NULL COMMENT 'ملاحظات عامة',
  `driver_status` varchar(50) DEFAULT 'نشط' COMMENT 'حالة المشغل',
  `start_date` date DEFAULT NULL COMMENT 'تاريخ البدء الفعلي',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'تاريخ التسجيل في النظام',
  `phone` varchar(255) NOT NULL,
  `phone_alternative` varchar(50) DEFAULT NULL COMMENT 'رقم هاتف بديل',
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `drivers`
--

INSERT INTO `drivers` (`id`, `name`, `driver_code`, `nickname`, `identity_type`, `identity_number`, `identity_expiry_date`, `license_number`, `license_type`, `license_expiry_date`, `license_issuer`, `specialized_equipment`, `years_in_field`, `years_on_equipment`, `skill_level`, `certificates`, `owner_supervisor`, `supplier_id`, `employment_affiliation`, `salary_type`, `monthly_salary`, `email`, `address`, `performance_rating`, `behavior_record`, `accident_record`, `health_status`, `health_issues`, `vaccinations_status`, `previous_employer`, `employment_duration`, `reference_contact`, `general_notes`, `driver_status`, `start_date`, `created_at`, `phone`, `phone_alternative`, `status`) VALUES
(1, 'محمد سيد', '', '', '', '', NULL, '', '', NULL, '', '', NULL, NULL, '', '', '', NULL, '', '', NULL, '', '', '', '', '', '', '', '', '', '', '', '', 'نشط', NULL, '2026-02-25 20:54:22', '01923329', '', 1),
(2, 'ahmed', 'ad', '', '', '', NULL, '', '', NULL, '', '', NULL, NULL, '', '', '', NULL, '', '', NULL, '', '', '', '', '', '', '', '', '', '', '', '', 'نشط', NULL, '2026-02-25 20:54:22', '98', '', 1),
(3, 'محمد أحمد علي', 'OPR-001-2026', 'أبو محمد', 'بطاقة هوية وطنية', '123456789123', '2028-12-31', 'DL-2024-456789', 'فئة د (شاحنات ثقيلة)', '2027-06-30', 'إدارة المرور - الخرطوم', 'حفارة (Excavator), شاحنة قلابة (Dump Truck)', 8, 5, 'خبير (5-10 سنوات)', 'شهادة تشغيل حفارات من معهد التعدين', 'محمد علي', 3, 'تابع لمالك المعدة مباشرة', 'شهري', 3500.00, 'mohammed@example.com', 'شارع النيل، الخرطوم', 'ممتاز', 'ممتاز (لا توجد شكاوى)', 'نظيف (لا توجد حوادث)', 'سليم تماماً', '', 'محدثة', 'شركة الذهب للتعدين', '3 سنوات', 'محمود أحمد - مدير الأسطول (09-123-4567)', 'مشغل موثوق وذو كفاءة عالية', 'نشط', '2024-01-15', '2026-02-25 20:58:52', '+249-9-123-4567', '+249-9-765-4321', 0);

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
  `serial_number` varchar(100) DEFAULT NULL COMMENT 'رقم المعدة/الرقم التسلسلي',
  `chassis_number` varchar(100) DEFAULT NULL COMMENT 'رقم الهيكل/الهيكل الأساسي',
  `manufacturer` varchar(100) DEFAULT NULL COMMENT 'الماركة/الشركة المصنعة',
  `model` varchar(100) DEFAULT NULL COMMENT 'الموديل/الطراز',
  `manufacturing_year` int(4) DEFAULT NULL COMMENT 'سنة الصنع',
  `import_year` int(4) DEFAULT NULL COMMENT 'سنة الاستيراد/البدء',
  `equipment_condition` varchar(50) DEFAULT 'في حالة جيدة' COMMENT 'حالة المعدة',
  `operating_hours` int(11) DEFAULT NULL COMMENT 'ساعات التشغيل',
  `engine_condition` varchar(50) DEFAULT 'جيدة' COMMENT 'حالة المحرك',
  `tires_condition` varchar(50) DEFAULT 'N/A' COMMENT 'حالة الإطارات',
  `actual_owner_name` varchar(200) DEFAULT NULL COMMENT 'اسم المالك الفعلي',
  `owner_type` varchar(50) DEFAULT NULL COMMENT 'نوع المالك',
  `owner_phone` varchar(50) DEFAULT NULL COMMENT 'رقم هاتف المالك',
  `owner_supplier_relation` varchar(100) DEFAULT NULL COMMENT 'علاقة المالك بالمورد',
  `license_number` varchar(100) DEFAULT NULL COMMENT 'رقم الترخيص/التسجيل',
  `license_authority` varchar(100) DEFAULT NULL COMMENT 'جهة الترخيص',
  `license_expiry_date` date DEFAULT NULL COMMENT 'تاريخ انتهاء الترخيص',
  `inspection_certificate_number` varchar(100) DEFAULT NULL COMMENT 'رقم شهادة الفحص',
  `last_inspection_date` date DEFAULT NULL COMMENT 'تاريخ آخر فحص',
  `current_location` varchar(255) DEFAULT NULL COMMENT 'الموقع الحالي',
  `availability_status` varchar(50) DEFAULT 'متاحة للعمل' COMMENT 'حالة التوفر',
  `estimated_value` decimal(15,2) DEFAULT NULL COMMENT 'القيمة المقدرة للمعدة',
  `daily_rental_price` decimal(10,2) DEFAULT NULL COMMENT 'سعر التأجير اليومي',
  `monthly_rental_price` decimal(10,2) DEFAULT NULL COMMENT 'سعر التأجير الشهري',
  `insurance_status` varchar(50) DEFAULT NULL COMMENT 'التأمين/الضمان',
  `general_notes` text DEFAULT NULL COMMENT 'ملاحظات عامة',
  `last_maintenance_date` date DEFAULT NULL COMMENT 'تاريخ آخر صيانة',
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `equipments`
--

INSERT INTO `equipments` (`id`, `suppliers`, `code`, `type`, `name`, `serial_number`, `chassis_number`, `manufacturer`, `model`, `manufacturing_year`, `import_year`, `equipment_condition`, `operating_hours`, `engine_condition`, `tires_condition`, `actual_owner_name`, `owner_type`, `owner_phone`, `owner_supplier_relation`, `license_number`, `license_authority`, `license_expiry_date`, `inspection_certificate_number`, `last_inspection_date`, `current_location`, `availability_status`, `estimated_value`, `daily_rental_price`, `monthly_rental_price`, `insurance_status`, `general_notes`, `last_maintenance_date`, `status`) VALUES
(1, '1', 'fr-FR', '1', 'tq1', '1212389', '', '', '', NULL, NULL, 'في حالة جيدة', NULL, 'جيدة', 'N/A', '', '', '', '', '', '', NULL, '', NULL, '', 'متاحة للعمل', NULL, NULL, NULL, '', '', NULL, 1),
(2, '2', 'Wq1', '1', 'Wq1', '1111', '', '', '', NULL, NULL, 'في حالة جيدة', NULL, 'جيدة', 'N/A', '', '', '', '', '', '', NULL, '', NULL, '', 'موقوفة للصيانة', NULL, NULL, NULL, '', '', NULL, 1),
(3, '2', 'swd', '1', 'swd', '', '', '', '', NULL, NULL, 'في حالة جيدة', NULL, 'جيدة', 'N/A', '', '', '', '', '', '', NULL, '', NULL, '', 'متاحة للعمل', NULL, NULL, NULL, '', '', NULL, 1),
(4, '2', 'EQ-001', '1', 'حفار كاتربيلر 320', 'EXC-2024-001', 'CAT320-ABC123456', 'كاتربيلر', '320D', 2018, 2020, 'في حالة جيدة', 5400, 'جيدة', 'N/A', 'محمد علي أحمد', 'مالك فردي', '+249-912345678', 'تابع للمورد (مملوكة للمورد نفسه)', 'VEH-2024-12345', 'المرور', '2025-12-31', 'INS-2024-001', '2024-06-15', 'منجم الذهب الشرقي', 'متاحة للعمل', 150000.00, 500.00, 10000.00, 'مؤمن بالكامل', 'معدة موثوقة، تحتاج صيانة دورية كل 3 أشهر', '2024-05-10', 1),
(5, '2', 'EQ-002', '2', 'شاحنة قلاب هيونداي', 'TRK-2024-002', 'HYN-DEF789012', 'هيونداي', 'HD270', 2019, 2021, 'جديدة نسبياً (أقل من سنة استخدام)', 2800, 'ممتازة', 'جيدة', 'أحمد محمد علي', 'شركة متخصصة', '+249-923456789', 'مالك مباشر (يتعاقد معنا مباشرة)', 'VEH-2024-67890', 'وزارة النقل', '2026-03-15', 'INS-2024-002', '2024-07-20', 'مستودع الخرطوم', 'قيد الاستخدام', 80000.00, 300.00, 7000.00, 'مؤمن جزئياً', 'شاحنة جديدة بحالة ممتازة', '2024-06-01', 1),
(6, '1', 'TQ-001', '1', 'حفار كاتر', 'EXC-2024-001', 'CAT320-ABC123456', 'كاتربيلر', '320D', 2018, 2020, 'في حالة جيدة', 5400, 'جيدة', 'N/A', 'محمد علي أحمد', 'مالك فردي', '+249-912345678', 'تابع للمورد (مملوكة للمورد نفسه)', 'VEH-2024-12345', 'المرور', '2025-12-31', 'INS-2024-001', '2024-06-15', 'منجم الذهب الشرقي', 'متاحة للعمل', 150000.00, 500.00, 10000.00, 'مؤمن بالكامل', 'معدة موثوقة، تحتاج صيانة دورية كل 3 أشهر', '2024-05-10', 1),
(7, '1', 'TQ-002', '1', 'هيونداي', 'TRK-2024-002', 'HYN-DEF789012', 'هيونداي', 'HD270', 2019, 2021, 'جديدة نسبياً (أقل من سنة استخدام)', 2800, 'ممتازة', 'جيدة', 'أحمد محمد علي', 'شركة متخصصة', '+249-923456789', 'مالك مباشر (يتعاقد معنا مباشرة)', 'VEH-2024-67890', 'وزارة النقل', '2026-03-15', 'INS-2024-002', '2024-07-20', 'مستودع الخرطوم', 'قيد الاستخدام', 80000.00, 300.00, 7000.00, 'مؤمن جزئياً', 'شاحنة جديدة بحالة ممتازة', '2024-06-01', 1),
(8, '4', 'm1', '1', 'm1', '', '', '', '', NULL, NULL, 'في حالة جيدة', NULL, 'جيدة', 'N/A', '', '', '', '', '', '', NULL, '', NULL, '', 'متاحة للعمل', NULL, NULL, NULL, '', '', NULL, 1),
(9, '4', 'm130', '2', 'm130', '', '', '', '', NULL, NULL, 'في حالة جيدة', NULL, 'جيدة', 'N/A', '', '', '', '', '', '', NULL, '', NULL, '', 'متاحة للعمل', NULL, NULL, NULL, '', '', NULL, 1),
(10, '4', 'mg120', '3', 'mg120', '', '', '', '', NULL, NULL, 'في حالة جيدة', NULL, 'جيدة', 'N/A', '', '', '', '', '', '', NULL, '', NULL, '', 'متاحة للعمل', NULL, NULL, NULL, '', '', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `equipments_types`
--

CREATE TABLE `equipments_types` (
  `id` int(11) NOT NULL,
  `form` varchar(20) NOT NULL,
  `type` varchar(100) NOT NULL,
  `status` enum('active','inactive','','') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `equipments_types`
--

INSERT INTO `equipments_types` (`id`, `form`, `type`, `status`, `created_at`, `updated_at`) VALUES
(1, '1', 'حفار120', 'active', '2026-02-28 08:00:26', '2026-02-27 23:39:11'),
(2, '1', 'حفار130', 'active', '2026-02-28 08:00:30', '2026-02-27 23:39:26'),
(3, '2', 'قلاب120', 'active', '2026-02-28 08:00:35', '2026-02-27 23:39:38'),
(4, '2', 'قلاب130', 'active', '2026-02-28 08:00:39', '2026-02-27 23:39:50');

-- --------------------------------------------------------

--
-- Table structure for table `equipment_drivers`
--

CREATE TABLE `equipment_drivers` (
  `id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `start_date` varchar(50) NOT NULL,
  `end_date` varchar(50) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `equipment_drivers`
--

INSERT INTO `equipment_drivers` (`id`, `equipment_id`, `driver_id`, `start_date`, `end_date`, `status`) VALUES
(23, 2, 2, '2026-02-01', '2026-02-28', 1),
(24, 7, 1, '2026-02-04', '2026-02-06', 0),
(25, 7, 3, '2026-02-10', '2099-12-31', 0),
(26, 7, 1, '2026-02-10', '2099-12-31', 1);

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
(1, 1, 'منجم احمد', 'منجم', 'سامبا', 'ذهب', 'حفرة مفتوحة', '', 'تعدين أهلي/تقليدي', '', NULL, 'هكتار', NULL, '', 1, '', 1, '2026-02-16 22:14:04', '2026-02-16 22:14:04'),
(2, 2, 'منجم اليونان', 'rox230', 'سامبا', 'ذهب', 'تحت أرضي', '', 'تعدين أهلي/تقليدي', '', NULL, 'هكتار', NULL, 'موظف مباشر لدى المالك', 1, '', 1, '2026-02-27 23:42:13', '2026-02-27 23:42:13');

-- --------------------------------------------------------

--
-- Table structure for table `modules`
--

CREATE TABLE `modules` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `owner_role_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `modules`
--

INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`) VALUES
(1, 'شاشة العملاء', 'Clients/clients.php', 1),
(2, 'شاشة المشاريع', 'Projects/oprationprojects.php', 1),
(3, 'شاشة المستخدمين', 'main/users.php', 1),
(4, 'شاشة التقارير', 'Reports/reports.php', 1),
(5, 'شاشة انواع المعدات', 'Equipments/equipments_types.php', 1),
(6, 'شاشة الموردين', 'Suppliers/suppliers.php', 2),
(7, 'شاشاة المشغلين', 'Drivers/drivers.php', 4),
(8, 'شاشة المعدات', 'Equipments/equipments.php', 3),
(9, 'شاشة التشغيل', 'Oprators/oprators.php', 6),
(10, 'صفحة الساعات', 'Timesheet/timesheet_type.php', 5),
(11, 'الإعدادات', 'Settings/settings.php', 1),
(12, 'شاشة المشرفين', 'main/project_users.php', 1),
(14, 'شاشة المشرفين', 'main/project_users.php', 2),
(15, 'شاشة المشرفين', 'main/project_users.php', 3),
(16, 'شاشة المشرفين', 'main/project_users.php', 4),
(17, 'شاشة المشرفين', 'main/project_users.php', 5),
(18, 'شاشة المشرفين', 'main/project_users.php', 6);

-- --------------------------------------------------------

--
-- Table structure for table `operations`
--

CREATE TABLE `operations` (
  `id` int(11) NOT NULL,
  `equipment` varchar(100) NOT NULL,
  `equipment_type` varchar(100) NOT NULL DEFAULT '0',
  `equipment_category` varchar(20) NOT NULL,
  `project_id` varchar(20) NOT NULL,
  `mine_id` varchar(10) NOT NULL,
  `contract_id` varchar(10) NOT NULL,
  `supplier_id` varchar(10) NOT NULL,
  `start` varchar(50) NOT NULL,
  `end` varchar(50) NOT NULL,
  `reason` text NOT NULL,
  `days` varchar(20) NOT NULL,
  `total_equipment_hours` decimal(10,2) DEFAULT 0.00 COMMENT 'إجمالي ساعات العمل الكلية للآلية',
  `shift_hours` decimal(10,2) DEFAULT 0.00 COMMENT 'عدد ساعات الوردية للمعدة',
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `operations`
--

INSERT INTO `operations` (`id`, `equipment`, `equipment_type`, `equipment_category`, `project_id`, `mine_id`, `contract_id`, `supplier_id`, `start`, `end`, `reason`, `days`, `total_equipment_hours`, `shift_hours`, `status`) VALUES
(4, '2', '1', 'أساسي', '1', '1', '1', '2', '2026-02-02', '2026-03-03', '', '0', 100.00, 10.00, 3),
(5, '8', '1', 'أساسي', '2', '2', '2', '4', '2026-02-01', '2026-03-03', '', '0', 200.00, 10.00, 1),
(6, '9', '2', 'أساسي', '2', '2', '2', '4', '2026-02-01', '2026-03-03', '', '0', 200.00, 10.00, 1),
(7, '10', '3', 'أساسي', '2', '2', '2', '4', '2026-02-01', '2026-03-03', '', '0', 100.00, 10.00, 1);

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
(1, 1, 'مشروع الروسيه جديد', 'وزارة البنية التحتية', 'الخرطوم2', 'PRJ-2026-001', '', 'التعدين', 'الخرطوم', 'الكويت', 'سوق بحري', '15.5527', '32.5599', '0', 1, 1, '2026-02-16 21:13:41', '2026-03-03 14:29:34'),
(2, 2, 'مشروع اليونان', 'شركة المستقبل للمقاولات', 'الخرطوم2', 'PRJ-2026-071', '', 'التعدين', 'الخرطوم', '', '', '', '', '0', 1, 1, '2026-02-27 22:41:15', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `parent_role_id` int(11) DEFAULT NULL,
  `level` int(11) DEFAULT 1,
  `status` varchar(10) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `parent_role_id`, `level`, `status`, `created_at`) VALUES
(1, 'مدير المشاريع', NULL, 1, '1', '2026-03-04 12:46:56'),
(2, 'مدير الموردين', NULL, 1, '1', '2026-03-04 12:47:22'),
(3, 'مدير الاسطول', NULL, 1, '1', '2026-03-04 12:47:41'),
(4, 'مدير المشغلين', NULL, 1, '1', '2026-03-04 12:50:24'),
(5, 'مدير الموقع', NULL, 1, '1', '2026-03-04 12:52:29'),
(6, 'مدير حركة وتشغيل', NULL, 1, '1', '2026-03-04 12:52:47'),
(7, 'مشرف - مشاريع', 1, 2, '1', '2026-03-04 15:18:15'),
(8, 'مشرف موردين', 2, 2, '1', '2026-03-04 15:34:07');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `can_view` tinyint(1) DEFAULT 0,
  `can_add` tinyint(1) DEFAULT 0,
  `can_edit` tinyint(1) DEFAULT 0,
  `can_delete` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`id`, `role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`) VALUES
(3, 7, 2, 1, 0, 1, 0),
(4, 1, 2, 1, 1, 1, 1),
(5, 1, 1, 1, 1, 1, 1),
(6, 7, 1, 1, 0, 1, 0),
(7, 7, 3, 1, 0, 1, 0),
(8, 7, 12, 1, 0, 1, 0),
(9, 7, 4, 1, 0, 1, 0),
(10, 1, 11, 1, 1, 1, 1),
(11, 7, 5, 1, 0, 1, 0),
(12, 1, 4, 1, 1, 1, 1),
(13, 1, 3, 1, 1, 1, 1),
(14, 1, 12, 1, 1, 1, 1),
(15, 1, 5, 1, 1, 1, 1),
(16, 7, 11, 1, 0, 1, 0);

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
  `equip_count_basic` int(11) DEFAULT 0 COMMENT 'عدد المعدات الأساسية',
  `equip_count_backup` int(11) DEFAULT 0 COMMENT 'عدد المعدات الاحتياطية',
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

INSERT INTO `suppliercontractequipments` (`id`, `contract_id`, `equip_type`, `equip_size`, `equip_count`, `equip_count_basic`, `equip_count_backup`, `equip_shifts`, `equip_unit`, `shift1_start`, `shift1_end`, `shift2_start`, `shift2_end`, `shift_hours`, `equip_total_month`, `equip_monthly_target`, `equip_total_contract`, `equip_price`, `equip_price_currency`, `equip_operators`, `equip_supervisors`, `equip_technicians`, `equip_assistants`, `created_at`) VALUES
(1, 1, '1', 340, 2, 0, 0, 0, '', '00:00:00', '00:00:00', '00:00:00', '00:00:00', 10.00, 20.00, 0.00, 600.00, 0.00, '', 0, 0, 0, 0, '2026-02-16 22:22:09'),
(2, 2, '1', 122, 1, 1, 0, 0, '', '00:00:00', '00:00:00', '00:00:00', '00:00:00', 5.00, 5.00, 0.00, 260.00, 0.00, '', 0, 0, 0, 0, '2026-02-27 23:57:27'),
(3, 2, '2', 130, 1, 1, 0, 0, '', '00:00:00', '00:00:00', '00:00:00', '00:00:00', 5.00, 5.00, 0.00, 260.00, 0.00, '', 0, 0, 0, 0, '2026-02-27 23:57:27'),
(4, 2, '3', 120, 1, 0, 0, 0, '', '00:00:00', '00:00:00', '00:00:00', '00:00:00', 5.00, 5.00, 0.00, 260.00, 0.00, '', 0, 0, 0, 0, '2026-02-27 23:57:27');

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
(1, 'اكوبيشن', '123', 'شركة', 'متعاقد مباشر', 'حفارات, مكنات تخريم, دوازر', '234', 'جواز سفر', '1234', NULL, 'infotelecomwasla@gmail.com', '', '', '', '', 'مسجل رسميا', '2026-02-16 22:20:14', '2026-02-16 22:20:14', '76887534', 1),
(2, 'احمد', '1234', 'فرد', 'مورد معدات مباشر (مالك)', 'حفارات, معدات معالجة', '765', 'بطاقة هوية وطنية', '', NULL, 'a.samba12@gmail.com', '', '', '', '', '', '2026-02-16 22:20:56', '2026-02-16 22:20:56', '0920045986', 1),
(3, 'شركة النيل للمعدات الثقيلة', 'SUP-001', '', 'مباشر', 'حفار, قلاب, لودر', 'CR-123456', 'بطاقة شخصية', '123456789', '2027-12-31', 'info@nile-equip.com', '249987654321', 'الخرطوم - شارع النيل', 'أحمد محمد', '249123456789', '', '2026-02-25 19:37:15', '2026-02-25 19:37:15', '249123456789', 1),
(4, 'مؤسسة المستقبل للآليات', 'SUP-002', 'فرد', 'وسيط', '', 'CR-789012', 'جواز سفر', 'P987654321', '2028-06-30', 'contact@mustaqbal.sd', '249444555666', 'أم درمان - الموردة', 'محمد أحمد', '249111222333', 'غير مسجل', '2026-02-25 19:37:15', '2026-02-25 19:38:38', '249111222333', 1);

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
(1, 2, '2026-02-01', 5, 0, 31, 0, 0, 0, 0, 0, '2026-03-01', '2026-03-31', '', '', '', '', NULL, NULL, 0, 0, 0, 0, NULL, NULL, 0, 0, 0, 0, 20, 600, '2026-02-16 22:22:09', NULL, '20', '0', '', '', '', '', 'جنيه', '', '', '', '0000-00-00', 1, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 4, '2026-02-01', 4, 0, 53, 0, 0, 0, 0, 0, '2026-02-01', '2026-03-25', '', '', '', '', NULL, NULL, 0, 0, 0, 0, NULL, NULL, 0, 0, 0, 0, 15, 780, '2026-02-27 23:57:27', NULL, '20', '0', '', '', '', '', 'دولار', '', '', '', '0000-00-00', 2, 2, 2, 1, NULL, NULL, NULL, NULL, NULL, NULL);

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
(1, '1', '2', 'D', '2026-02-17', 10, 8, 3, 5, 0, 0, 0, 0, 8, '', 0, 0, 0, 0, 0, 2, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 0, 0, 0, 0, 0, 0, '', '1', 5, 'لاتوجد ملاحظات', 1);

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
  `mine_id` int(11) DEFAULT 0 COMMENT 'معرف المنجم لمدير الموقع',
  `contract_id` int(11) DEFAULT 0 COMMENT 'معرف العقد لمدير الموقع',
  `parent_id` varchar(20) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `password`, `phone`, `role`, `project_id`, `mine_id`, `contract_id`, `parent_id`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin', '2025', '0', '1', '0', 0, 0, '0', '2026-02-16 22:06:44', '2026-02-16 22:06:44'),
(2, 'o', 'o', 'o', '0', '4', '0', 0, 0, '0', '2026-02-16 22:07:07', '2026-02-16 22:07:07'),
(3, 'r', 'r', 'r', '5', '2', '0', 0, 0, '0', '2026-02-16 22:19:20', '2026-02-16 22:19:20'),
(4, 'm', 'm', 'm', '0', '3', '0', 0, 0, '0', '2026-02-16 22:37:21', '2026-02-16 22:37:21'),
(5, 'q', 'q', 'q', '5', '5', '1', 1, 1, '0', '2026-02-16 22:40:08', '2026-02-17 14:13:01'),
(6, 'x', 'x', 'x', '6', '5', '1', 1, 1, '0', '2026-02-17 14:07:51', '2026-02-17 14:07:51'),
(7, 't', 't', 't', '0', '10', '1', 1, 1, '0', '2026-02-22 15:06:10', '2026-02-22 15:06:10'),
(8, 'موقع يونان', 'yyy', 'y', '989', '5', '2', 2, 2, '0', '2026-02-28 00:29:07', '2026-03-04 15:29:37'),
(9, 'اواب', 'aw', 'aw', '09999', '7', '0', 0, 0, '1', '2026-03-04 15:44:28', '2026-03-04 15:44:28'),
(10, 'حمادة', 'hm', 'hm', '09', '8', '0', 0, 0, '3', '2026-03-04 15:56:15', '2026-03-04 15:56:15');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `approval_requests`
--
ALTER TABLE `approval_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_approval_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_approval_status` (`status`),
  ADD KEY `idx_approval_user` (`requested_by`);

--
-- Indexes for table `approval_steps`
--
ALTER TABLE `approval_steps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_approval_steps_request` (`request_id`),
  ADD KEY `idx_approval_steps_status` (`status`),
  ADD KEY `idx_approval_steps_order` (`step_order`);

--
-- Indexes for table `approval_workflow_rules`
--
ALTER TABLE `approval_workflow_rules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_workflow_rule` (`entity_type`,`action`,`step_order`),
  ADD KEY `idx_workflow_rule_lookup` (`entity_type`,`action`,`is_active`);

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
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_driver_code` (`driver_code`),
  ADD KEY `idx_driver_name` (`name`),
  ADD KEY `idx_driver_status` (`driver_status`),
  ADD KEY `idx_supplier_id` (`supplier_id`);

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
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_serial_number` (`serial_number`),
  ADD KEY `idx_chassis_number` (`chassis_number`),
  ADD KEY `idx_manufacturer` (`manufacturer`),
  ADD KEY `idx_availability_status` (`availability_status`);

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
-- Indexes for table `modules`
--
ALTER TABLE `modules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `owner_role_id` (`owner_role_id`);

--
-- Indexes for table `operations`
--
ALTER TABLE `operations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_total_equipment_hours` (`total_equipment_hours`),
  ADD KEY `idx_shift_hours` (`shift_hours`);

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
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_role_id` (`parent_role_id`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_id` (`role_id`,`module_id`),
  ADD KEY `module_id` (`module_id`);

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
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_mine_id` (`mine_id`),
  ADD KEY `idx_contract_id` (`contract_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `approval_requests`
--
ALTER TABLE `approval_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `approval_steps`
--
ALTER TABLE `approval_steps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `approval_workflow_rules`
--
ALTER TABLE `approval_workflow_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `contractequipments`
--
ALTER TABLE `contractequipments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `contract_notes`
--
ALTER TABLE `contract_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `driver_contract_notes`
--
ALTER TABLE `driver_contract_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `equipments`
--
ALTER TABLE `equipments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `equipments_types`
--
ALTER TABLE `equipments_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `equipment_drivers`
--
ALTER TABLE `equipment_drivers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `mines`
--
ALTER TABLE `mines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `modules`
--
ALTER TABLE `modules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `operations`
--
ALTER TABLE `operations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `project`
--
ALTER TABLE `project`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `suppliercontractequipments`
--
ALTER TABLE `suppliercontractequipments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
-- Constraints for table `approval_steps`
--
ALTER TABLE `approval_steps`
  ADD CONSTRAINT `fk_approval_steps_request` FOREIGN KEY (`request_id`) REFERENCES `approval_requests` (`id`) ON DELETE CASCADE;

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
-- Constraints for table `drivers`
--
ALTER TABLE `drivers`
  ADD CONSTRAINT `fk_drivers_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

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
-- Constraints for table `modules`
--
ALTER TABLE `modules`
  ADD CONSTRAINT `modules_ibfk_1` FOREIGN KEY (`owner_role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `project`
--
ALTER TABLE `project`
  ADD CONSTRAINT `fk_project_client` FOREIGN KEY (`company_client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_project_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `roles`
--
ALTER TABLE `roles`
  ADD CONSTRAINT `roles_ibfk_1` FOREIGN KEY (`parent_role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  ADD CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`);

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
