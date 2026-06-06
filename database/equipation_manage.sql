-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 06, 2026 at 11:33 AM
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
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT NULL,
  `project_id` bigint(20) UNSIGNED DEFAULT NULL,
  `contract_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `role_id` bigint(20) UNSIGNED DEFAULT NULL,
  `role_name` varchar(255) DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `screen_name` varchar(255) DEFAULT NULL,
  `module_name` varchar(255) DEFAULT NULL,
  `action_type` varchar(50) DEFAULT NULL,
  `button_name` varchar(255) DEFAULT NULL,
  `field_name` varchar(255) DEFAULT NULL,
  `record_id` bigint(20) UNSIGNED DEFAULT NULL,
  `old_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_value`)),
  `new_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_value`)),
  `url` text DEFAULT NULL,
  `http_method` varchar(10) DEFAULT NULL,
  `request_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_payload`)),
  `response_status` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='سجل نشاطات المستخدمين — Activity Tracking Log';

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `company_id`, `project_id`, `contract_id`, `user_id`, `role_id`, `role_name`, `session_id`, `ip_address`, `user_agent`, `screen_name`, `module_name`, `action_type`, `button_name`, `field_name`, `record_id`, `old_value`, `new_value`, `url`, `http_method`, `request_payload`, `response_status`, `created_at`) VALUES
(1, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'cXr5ERv1CzY,UZEOyVeGgHUjL7FHvJZjZ4haq6TktKhWHRcL', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'movement_operations', 'movement', 'save_single_operation', 'save_single_operation', NULL, NULL, NULL, NULL, 'http://localhost/ems/movement/movement_operations.php?project_id=4', 'POST', '{\"action\":\"save_single_operation\",\"op_id\":\"16\",\"equipment_category\":\"أساسي\",\"shift_type\":\"D\",\"status\":\"1\",\"start\":\"2026-05-11\",\"end\":\"2026-07-01\",\"json\":\"1\"}', 200, '2026-05-19 17:27:40'),
(2, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'cXr5ERv1CzY,UZEOyVeGgHUjL7FHvJZjZ4haq6TktKhWHRcL', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-19 17:28:41'),
(3, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'bGyYyWR5OFM9jJZgC,WqVHbu6wvRbYlj7ncCEPTxuiAHgmsW', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"يسن\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-19 17:28:48'),
(4, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'bGyYyWR5OFM9jJZgC,WqVHbu6wvRbYlj7ncCEPTxuiAHgmsW', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-19 17:29:10'),
(5, 4, 0, 0, 7, 4, 'ادارة الموارد البشرية', 'bxfoVKB-3027iTLn9yEq04Ws7PghCpQrCjDR-kFGAG6GHFc5', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"اروينا\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-19 17:29:17'),
(6, 4, 0, 0, 7, 4, 'ادارة الموارد البشرية', 'bxfoVKB-3027iTLn9yEq04Ws7PghCpQrCjDR-kFGAG6GHFc5', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'drivers', 'drivers', 'update', 'update', NULL, 15, NULL, NULL, 'http://localhost/ems/Drivers/drivers.php', 'POST', '{\"id\":\"15\",\"name\":\"كرم جبارة\",\"driver_code\":\"DR33\",\"nickname\":\"Howard Fleming\",\"identity_type\":\"بطاقة أخرى\",\"identity_number\":\"440\",\"identity_expiry_date\":\"1978-10-16\",\"driver_photo\":\"\",\"identity_photo\":\"\",\"license_number\":\"876\",\"license_type\":\"متعددة الفئات\",\"license_expiry_date\":\"2012-10-17\",\"license_issuer\":\"Vitae consequat Lab\",\"specialized_equipment\":[\"حفارة (Excavator)\",\"مثقاب/مكنة تخريم (Drill Machine)\",\"شاحنة قلابة (Dump Truck)\",\"شاحنة تناكر/صهريج (Tanker Truck)\",\"ممهدة (Grader)\",\"معدات أخرى\"],\"years_in_field\":\"5\",\"years_on_equipment\":\"1\",\"skill_level\":\"مبتدئ (أقل من سنة)\",\"certificates\":\"Facere fugiat irure\",\"owner_supervisor\":\"Ut fugit quos alias\",\"supplier_id\":\"3\",\"project_id\":\"4\",\"employment_affiliation\":\"تابع لشركة متخصصة في التشغيل\",\"salary_type\":\"أسبوعي\",\"monthly_salary\":\"11.00\",\"email\":\"nyxe@mailinator.com\",\"phone\":\"+1 (329) 275-8061\",\"phone_alternative\":\"+1 (135) 308-6085\",\"address\":\"Dolor et consequatur\",\"performance_rating\":\"غير محدد\",\"behavior_record\":\"مقبول (بعض الشكاوى)\",\"accident_record\":\"حادثان (متوسط)\",\"health_status\":\"بحالة جيدة\",\"vaccinations_status\":\"قديمة\",\"health_issues\":\"Delectus ea et repe\",\"previous_employer\":\"Aliqua Molestiae qu\",\"employment_duration\":\"Enim tempore do sun\",\"reference_contact\":\"Id cum deserunt veli\",\"general_notes\":\"Vero sed in molestia\",\"driver_status\":\"نشط\",\"start_date\":\"1970-02-16\",\"status\":\"1\"}', 302, '2026-05-19 17:29:33'),
(7, 4, 0, 0, 7, 4, 'ادارة الموارد البشرية', 'bxfoVKB-3027iTLn9yEq04Ws7PghCpQrCjDR-kFGAG6GHFc5', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-19 17:30:35'),
(8, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'i024U1lpkEwXkpq1Ds2YiKkbQ7Dm8uobQgm93r0kb1edWHmm', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"مبيعات\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-19 17:30:51'),
(9, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'i024U1lpkEwXkpq1Ds2YiKkbQ7Dm8uobQgm93r0kb1edWHmm', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'clients', 'clients', 'update', NULL, NULL, 2, NULL, '{\"client_code\":\"58\",\"client_name\":\"شركة محمد\"}', 'http://localhost/ems/Clients/clients.php', 'POST', '{\"client_id\":\"2\",\"csrf_token\":\"[REDACTED]\",\"client_code\":\"58\",\"client_name\":\"شركة محمد\",\"entity_type\":\"دولي\",\"sector_category\":\"تعدين\",\"phone\":\"249912345678\",\"email\":\"sudan@gmail.com\",\"whatsapp\":\"249912345678\",\"status\":\"نشط\"}', 200, '2026-05-19 17:31:39'),
(10, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'i024U1lpkEwXkpq1Ds2YiKkbQ7Dm8uobQgm93r0kb1edWHmm', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'clients', 'clients', 'update', 'update', NULL, 2, NULL, NULL, 'http://localhost/ems/Clients/clients.php', 'POST', '{\"client_id\":\"2\",\"csrf_token\":\"[REDACTED]\",\"client_code\":\"58\",\"client_name\":\"شركة محمد\",\"entity_type\":\"دولي\",\"sector_category\":\"تعدين\",\"phone\":\"249912345678\",\"email\":\"sudan@gmail.com\",\"whatsapp\":\"249912345678\",\"status\":\"نشط\"}', 302, '2026-05-19 17:31:39'),
(11, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'i024U1lpkEwXkpq1Ds2YiKkbQ7Dm8uobQgm93r0kb1edWHmm', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-19 18:37:47'),
(12, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'N0Dca78y5mK5sWA0YDW,aidGWQEUyFNwSRLGiXaLaEKmPHDD', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"يسن\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-19 18:37:55'),
(13, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'N0Dca78y5mK5sWA0YDW,aidGWQEUyFNwSRLGiXaLaEKmPHDD', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-19 18:39:25'),
(14, 4, 0, 0, 5, 2, 'ادارة الموردين', 'zNkYxqtBWpb0ro616cuHvhu2HfGpya6IuRn-sObmHVa-0DRy', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"مصعب\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-19 18:39:36'),
(15, 4, 0, 0, 5, 2, 'ادارة الموردين', 'zNkYxqtBWpb0ro616cuHvhu2HfGpya6IuRn-sObmHVa-0DRy', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'supplierscontracts', 'suppliers', 'create', 'create', NULL, NULL, NULL, NULL, 'http://localhost/ems/Suppliers/supplierscontracts.php?id=4', 'POST', '{\"contract_id\":\"3\"}', 200, '2026-05-19 18:40:27'),
(16, 4, 0, 0, 5, 2, 'ادارة الموردين', 'zNkYxqtBWpb0ro616cuHvhu2HfGpya6IuRn-sObmHVa-0DRy', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'supplierscontracts', 'suppliers', 'create', 'create', NULL, NULL, NULL, NULL, 'http://localhost/ems/Suppliers/supplierscontracts.php?id=4', 'POST', '{\"project_id\":\"4\"}', 200, '2026-05-19 18:40:27'),
(17, 4, 0, 0, 5, 2, 'ادارة الموردين', 'zNkYxqtBWpb0ro616cuHvhu2HfGpya6IuRn-sObmHVa-0DRy', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'supplierscontracts', 'suppliers', 'create', 'create', NULL, NULL, NULL, NULL, 'http://localhost/ems/Suppliers/supplierscontracts.php?id=4', 'POST', '{\"project_contract_id\":\"4\",\"supplier_contract_id\":\"3\"}', 200, '2026-05-19 18:40:27'),
(18, 4, 0, 0, 5, 2, 'ادارة الموردين', 'zNkYxqtBWpb0ro616cuHvhu2HfGpya6IuRn-sObmHVa-0DRy', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'supplierscontracts', 'suppliers', 'create', 'create', NULL, NULL, NULL, NULL, 'http://localhost/ems/Suppliers/supplierscontracts.php?id=4', 'POST', '{\"project_contract_id\":\"5\",\"supplier_contract_id\":\"3\"}', 200, '2026-05-19 18:40:38'),
(19, 4, 0, 0, 5, 2, 'ادارة الموردين', 'zNkYxqtBWpb0ro616cuHvhu2HfGpya6IuRn-sObmHVa-0DRy', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'supplierscontracts', 'suppliers', 'create', 'create', NULL, NULL, NULL, NULL, 'http://localhost/ems/Suppliers/supplierscontracts.php?id=4', 'POST', '{\"project_contract_id\":\"4\",\"supplier_contract_id\":\"3\"}', 200, '2026-05-19 18:40:52'),
(20, 4, 0, 0, 5, 2, 'ادارة الموردين', 'zNkYxqtBWpb0ro616cuHvhu2HfGpya6IuRn-sObmHVa-0DRy', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'supplierscontracts', 'suppliers', 'create', 'create', NULL, NULL, NULL, NULL, 'http://localhost/ems/Suppliers/supplierscontracts.php?id=4', 'POST', '{\"project_contract_id\":\"5\",\"supplier_contract_id\":\"3\"}', 200, '2026-05-19 18:40:59'),
(21, 4, 0, 0, 5, 2, 'ادارة الموردين', 'zNkYxqtBWpb0ro616cuHvhu2HfGpya6IuRn-sObmHVa-0DRy', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'supplierscontracts', 'suppliers', 'create', 'create', NULL, NULL, NULL, NULL, 'http://localhost/ems/Suppliers/supplierscontracts.php?id=4', 'POST', '{\"project_contract_id\":\"4\",\"supplier_contract_id\":\"3\"}', 200, '2026-05-19 18:41:28'),
(22, 4, 0, 0, 5, 2, 'ادارة الموردين', 'zNkYxqtBWpb0ro616cuHvhu2HfGpya6IuRn-sObmHVa-0DRy', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'supplierscontracts', 'suppliers', 'create', 'create', NULL, NULL, NULL, NULL, 'http://localhost/ems/Suppliers/supplierscontracts.php', 'POST', '{\"project_id\":\"4\"}', 200, '2026-05-19 18:42:06'),
(23, 4, 0, 0, 5, 2, 'ادارة الموردين', 'zNkYxqtBWpb0ro616cuHvhu2HfGpya6IuRn-sObmHVa-0DRy', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'supplierscontracts', 'suppliers', 'create', 'create', NULL, NULL, NULL, NULL, 'http://localhost/ems/Suppliers/supplierscontracts.php', 'POST', '{\"contract_id\":\"3\"}', 200, '2026-05-19 18:42:06'),
(24, 4, 0, 0, 5, 2, 'ادارة الموردين', 'zNkYxqtBWpb0ro616cuHvhu2HfGpya6IuRn-sObmHVa-0DRy', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'supplierscontracts', 'suppliers', 'create', 'create', NULL, NULL, NULL, NULL, 'http://localhost/ems/Suppliers/supplierscontracts.php', 'POST', '{\"project_contract_id\":\"4\",\"supplier_contract_id\":\"3\"}', 200, '2026-05-19 18:42:06'),
(25, 4, 0, 0, 5, 2, 'ادارة الموردين', 'zNkYxqtBWpb0ro616cuHvhu2HfGpya6IuRn-sObmHVa-0DRy', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'supplierscontracts', 'suppliers', 'update', 'update', NULL, 3, NULL, NULL, 'http://localhost/ems/Suppliers/supplierscontracts.php', 'POST', '{\"id\":\"3\",\"supplier_id\":\"4\",\"project_id\":\"4\",\"project_contract_id\":\"4\",\"hours_monthly_target\":\"120\",\"forecasted_contracted_hours\":\"43920\",\"contract_signing_date\":\"2025-06-25\",\"grace_period_days\":\"5\",\"actual_start\":\"2025-07-01\",\"actual_end\":\"2026-07-01\",\"contract_duration_days\":\"366\",\"price_currency_contract\":\"دولار\",\"paid_contract\":\"0\",\"payment_time\":\"\",\"guarantees\":\"\",\"payment_date\":\"\",\"equip_shifts_contract\":\"2\",\"shift_contract\":\"10\",\"equip_total_contract\":\"20\",\"total_contract_permonth\":\"18000\",\"total_contract\":\"216000\",\"daily_operators\":\"3\",\"transportation\":\"مالك المعدة\",\"place_for_living\":\"مالك المشروع\",\"accommodation\":\"مالك المشروع\",\"workshop\":\"مالك المعدة\",\"equip_type_1\":\"1\",\"equip_size_1\":\"340\",\"equip_count_1\":\"2\",\"equip_count_basic_1\":\"2\",\"equip_count_backup_1\":\"4\",\"equip_operators_1\":\"\",\"equip_assistants_1\":\"4\",\"equip_shifts_1\":\"2\",\"shift1_start_1\":\"18:00:00\",\"shift1_end_1\":\"04:00:00\",\"shift2_start_1\":\"06:00:00\",\"shift2_end_1\":\"16:00:00\",\"equip_unit_1\":\"ساعة\",\"shift_hours_1\":\"10.00\",\"equip_total_month_1\":\"20\",\"equip_target_per_month_1\":\"\",\"equip_total_contract_1\":\"7320\",\"equip_price_currency_1\":\"دولار\",\"equip_price_1\":\"20.00\",\"equip_supervisors_1\":\"3\",\"equip_technicians_1\":\"2\",\"equip_type_2\":\"2\",\"equip_size_2\":\"35\",\"equip_count_2\":\"10\",\"equip_count_basic_2\":\"10\",\"equip_count_backup_2\":\"0\",\"equip_assistants_2\":\"8\",\"equip_shifts_2\":\"2\",\"shift1_start_2\":\"18:00:00\",\"shift1_end_2\":\"04:00:00\",\"shift2_start_2\":\"06:00:00\",\"shift2_end_2\":\"16:00:00\",\"equip_unit_2\":\"ساعة\",\"shift_hours_2\":\"10.00\",\"equip_total_month_2\":\"100\",\"equip_target_per_month_2\":\"14400.00\",\"equip_total_contract_2\":\"36600\",\"equip_price_currency_2\":\"\",\"equip_price_2\":\"8.00\",\"equip_supervisors_2\":\"3\",\"equip_technicians_2\":\"3\",\"daily_work_hours\":\"20\",\"first_party\":\"شركة إكوبيشن للإستثمار المحدودة\",\"second_party\":\"شركة إليانس لتعدين الذهب المحدودة\",\"witness_one\":\"محمد فيصل محمد صابر\",\"witness_two\":\"يس سيدأحمد محمدالأمين الحسن\"}', 200, '2026-05-19 18:42:29'),
(26, 4, 0, 0, 5, 2, 'ادارة الموردين', 'zNkYxqtBWpb0ro616cuHvhu2HfGpya6IuRn-sObmHVa-0DRy', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-19 18:42:35'),
(27, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'qJonGPJOwNze7G07NNudpIj4tlLMIn14ipcczZ0D7-68W9Ql', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"حركة - الروسية\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-19 18:42:41'),
(28, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'qJonGPJOwNze7G07NNudpIj4tlLMIn14ipcczZ0D7-68W9Ql', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-19 18:42:45'),
(29, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'Sa6UI,CHHp5Eb8BRfXbZBdPOFNjy3i7CVTcwZPoN,,Ls5hZx', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"يسن\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-19 18:42:50'),
(30, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'Sa6UI,CHHp5Eb8BRfXbZBdPOFNjy3i7CVTcwZPoN,,Ls5hZx', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-19 18:43:28'),
(31, 4, 0, 0, 5, 2, 'ادارة الموردين', 'urYLdJFBOJE5ALpPCGJxqHArFGbnILGMXQ10z3wzzewF,jMh', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"مصعب\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-19 18:43:35'),
(32, 4, 0, 0, 5, 2, 'ادارة الموردين', 'urYLdJFBOJE5ALpPCGJxqHArFGbnILGMXQ10z3wzzewF,jMh', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'supplierscontracts', 'suppliers', 'create', 'create', NULL, NULL, NULL, NULL, 'http://localhost/ems/Suppliers/supplierscontracts.php', 'POST', '{\"project_id\":\"4\"}', 200, '2026-05-19 18:43:43'),
(33, 4, 0, 0, 5, 2, 'ادارة الموردين', 'urYLdJFBOJE5ALpPCGJxqHArFGbnILGMXQ10z3wzzewF,jMh', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'supplierscontracts', 'suppliers', 'create', 'create', NULL, NULL, NULL, NULL, 'http://localhost/ems/Suppliers/supplierscontracts.php', 'POST', '{\"contract_id\":\"3\"}', 200, '2026-05-19 18:43:43'),
(34, 4, 0, 0, 5, 2, 'ادارة الموردين', 'urYLdJFBOJE5ALpPCGJxqHArFGbnILGMXQ10z3wzzewF,jMh', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'supplierscontracts', 'suppliers', 'create', 'create', NULL, NULL, NULL, NULL, 'http://localhost/ems/Suppliers/supplierscontracts.php', 'POST', '{\"project_contract_id\":\"4\",\"supplier_contract_id\":\"3\"}', 200, '2026-05-19 18:43:43'),
(35, 4, 0, 0, 5, 2, 'ادارة الموردين', 'urYLdJFBOJE5ALpPCGJxqHArFGbnILGMXQ10z3wzzewF,jMh', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'supplierscontracts', 'suppliers', 'update', 'update', NULL, 3, NULL, NULL, 'http://localhost/ems/Suppliers/supplierscontracts.php', 'POST', '{\"id\":\"3\",\"supplier_id\":\"4\",\"project_id\":\"4\",\"project_contract_id\":\"4\",\"hours_monthly_target\":\"20\",\"forecasted_contracted_hours\":\"7320\",\"contract_signing_date\":\"2025-06-25\",\"grace_period_days\":\"5\",\"actual_start\":\"2025-07-01\",\"actual_end\":\"2026-07-01\",\"contract_duration_days\":\"366\",\"price_currency_contract\":\"دولار\",\"paid_contract\":\"0\",\"payment_time\":\"\",\"guarantees\":\"\",\"payment_date\":\"\",\"equip_shifts_contract\":\"2\",\"shift_contract\":\"10\",\"equip_total_contract\":\"20\",\"total_contract_permonth\":\"18000\",\"total_contract\":\"216000\",\"daily_operators\":\"3\",\"transportation\":\"مالك المعدة\",\"place_for_living\":\"مالك المشروع\",\"accommodation\":\"مالك المشروع\",\"workshop\":\"مالك المعدة\",\"equip_type_1\":\"1\",\"equip_size_1\":\"340\",\"equip_count_1\":\"10\",\"equip_count_basic_1\":\"2\",\"equip_count_backup_1\":\"4\",\"equip_operators_1\":\"\",\"equip_assistants_1\":\"4\",\"equip_shifts_1\":\"2\",\"shift1_start_1\":\"18:00:00\",\"shift1_end_1\":\"04:00:00\",\"shift2_start_1\":\"06:00:00\",\"shift2_end_1\":\"16:00:00\",\"equip_unit_1\":\"ساعة\",\"shift_hours_1\":\"10.00\",\"equip_total_month_1\":\"20\",\"equip_target_per_month_1\":\"\",\"equip_total_contract_1\":\"7320\",\"equip_price_currency_1\":\"دولار\",\"equip_price_1\":\"20.00\",\"equip_supervisors_1\":\"3\",\"equip_technicians_1\":\"2\",\"daily_work_hours\":\"20\",\"first_party\":\"شركة إكوبيشن للإستثمار المحدودة\",\"second_party\":\"شركة إليانس لتعدين الذهب المحدودة\",\"witness_one\":\"محمد فيصل محمد صابر\",\"witness_two\":\"يس سيدأحمد محمدالأمين الحسن\"}', 200, '2026-05-19 18:45:03'),
(36, 4, 0, 0, 5, 2, 'ادارة الموردين', 'urYLdJFBOJE5ALpPCGJxqHArFGbnILGMXQ10z3wzzewF,jMh', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'supplierscontracts', 'suppliers', 'create', 'create', NULL, NULL, NULL, NULL, 'http://localhost/ems/Suppliers/supplierscontracts.php?id=4', 'POST', '{\"project_id\":\"4\"}', 200, '2026-05-19 18:45:06'),
(37, 4, 0, 0, 5, 2, 'ادارة الموردين', 'urYLdJFBOJE5ALpPCGJxqHArFGbnILGMXQ10z3wzzewF,jMh', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'supplierscontracts', 'suppliers', 'create', 'create', NULL, NULL, NULL, NULL, 'http://localhost/ems/Suppliers/supplierscontracts.php?id=4', 'POST', '{\"contract_id\":\"3\"}', 200, '2026-05-19 18:45:06'),
(38, 4, 0, 0, 5, 2, 'ادارة الموردين', 'urYLdJFBOJE5ALpPCGJxqHArFGbnILGMXQ10z3wzzewF,jMh', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'supplierscontracts', 'suppliers', 'create', 'create', NULL, NULL, NULL, NULL, 'http://localhost/ems/Suppliers/supplierscontracts.php?id=4', 'POST', '{\"project_contract_id\":\"4\",\"supplier_contract_id\":\"3\"}', 200, '2026-05-19 18:45:06'),
(39, 4, 0, 0, 5, 2, 'ادارة الموردين', 'urYLdJFBOJE5ALpPCGJxqHArFGbnILGMXQ10z3wzzewF,jMh', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'supplierscontracts', 'suppliers', 'create', 'create', NULL, NULL, NULL, NULL, 'http://localhost/ems/Suppliers/supplierscontracts.php?id=9', 'POST', '{\"project_id\":\"4\"}', 200, '2026-05-19 18:45:41'),
(40, 4, 0, 0, 5, 2, 'ادارة الموردين', 'urYLdJFBOJE5ALpPCGJxqHArFGbnILGMXQ10z3wzzewF,jMh', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'supplierscontracts', 'suppliers', 'create', 'create', NULL, NULL, NULL, NULL, 'http://localhost/ems/Suppliers/supplierscontracts.php?id=9', 'POST', '{\"project_contract_id\":\"4\",\"supplier_contract_id\":\"0\"}', 200, '2026-05-19 18:45:44'),
(41, 4, 0, 0, 5, 2, 'ادارة الموردين', 'urYLdJFBOJE5ALpPCGJxqHArFGbnILGMXQ10z3wzzewF,jMh', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'supplierscontracts', 'suppliers', 'create', 'create', NULL, NULL, NULL, NULL, 'http://localhost/ems/Suppliers/supplierscontracts.php?id=9', 'POST', '{\"project_id\":\"4\"}', 200, '2026-05-19 18:47:58'),
(42, 4, 0, 0, 5, 2, 'ادارة الموردين', 'urYLdJFBOJE5ALpPCGJxqHArFGbnILGMXQ10z3wzzewF,jMh', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'supplierscontracts', 'suppliers', 'create', 'create', NULL, NULL, NULL, NULL, 'http://localhost/ems/Suppliers/supplierscontracts.php?id=9', 'POST', '{\"project_contract_id\":\"4\",\"supplier_contract_id\":\"0\"}', 200, '2026-05-19 18:47:59'),
(43, 4, 0, 0, 5, 2, 'ادارة الموردين', 'urYLdJFBOJE5ALpPCGJxqHArFGbnILGMXQ10z3wzzewF,jMh', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-20 11:25:49'),
(44, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'PbPctGJ0qCIxpEdOjCUB90j0LSkCryMcofrDV4Bu2u1J5DuS', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"حركة - الروسية\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-20 11:25:57'),
(45, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'PbPctGJ0qCIxpEdOjCUB90j0LSkCryMcofrDV4Bu2u1J5DuS', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'move_oprators', 'movement', 'update', 'update', NULL, 14, NULL, NULL, 'http://localhost/ems/movement/move_oprators.php', 'POST', '{\"operation_id\":\"14\",\"shift_type\":\"D\"}', 200, '2026-05-20 11:26:15'),
(46, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'PbPctGJ0qCIxpEdOjCUB90j0LSkCryMcofrDV4Bu2u1J5DuS', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-20 20:52:01'),
(47, 4, 4, 4, 12, 5, 'مدير الموقع', 'TBJnJpM8,hyD2Ii6VXppD0au3HcGbx5QLnb,aS6ksgsmd7-H', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"مدير موقع الروسية\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-20 20:52:07'),
(48, 4, 4, 4, 12, 5, 'مدير الموقع', 'TBJnJpM8,hyD2Ii6VXppD0au3HcGbx5QLnb,aS6ksgsmd7-H', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"3\"}', 200, '2026-05-20 20:52:10'),
(49, 4, 4, 4, 12, 5, 'مدير الموقع', 'TBJnJpM8,hyD2Ii6VXppD0au3HcGbx5QLnb,aS6ksgsmd7-H', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"1\"}', 200, '2026-05-20 20:52:10'),
(50, 4, 4, 4, 12, 5, 'مدير الموقع', 'TBJnJpM8,hyD2Ii6VXppD0au3HcGbx5QLnb,aS6ksgsmd7-H', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"1\"}', 200, '2026-05-20 20:52:10'),
(51, 4, 4, 4, 12, 5, 'مدير الموقع', 'TBJnJpM8,hyD2Ii6VXppD0au3HcGbx5QLnb,aS6ksgsmd7-H', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'timesheet', 'timesheet', 'create', 'create', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'POST', '{\"shift\":\"D\",\"operator\":\"12\",\"id\":\"\",\"user_id\":\"12\",\"driver\":\"4\",\"date\":\"2026-05-20\",\"shift_hours\":\"10\",\"start_hours\":\"0\",\"start_minutes\":\"0\",\"start_seconds\":\"0\",\"executed_hours\":\"10\",\"bucket_hours\":\"5\",\"jackhammer_hours\":\"5\",\"extra_hours\":\"0\",\"extra_hours_total\":\"0\",\"standby_hours\":\"0\",\"dependence_hours\":\"0\",\"total_work_hours\":\"10\",\"work_notes\":\"\",\"hr_fault\":\"0\",\"maintenance_fault\":\"0\",\"marketing_fault\":\"0\",\"approval_fault\":\"0\",\"other_fault_hours\":\"0\",\"total_fault_hours\":\"0\",\"fault_notes\":\"\",\"end_hours\":\"0\",\"end_minutes\":\"0\",\"end_seconds\":\"0\",\"counter_diff\":\"0 ساعة 0 دقيقة 0 ثانية\",\"fault_type\":\"\",\"fault_department\":\"\",\"fault_part\":\"\",\"fault_details\":\"\",\"fault_items_json\":\"[]\",\"general_notes\":\"\",\"operator_hours\":\"0\",\"machine_standby_hours\":\"0\",\"jackhammer_standby_hours\":\"0\",\"bucket_standby_hours\":\"0\",\"extra_operator_hours\":\"0\",\"operator_standby_hours\":\"0\",\"operator_notes\":\"\",\"type\":\"1\"}', 200, '2026-05-20 20:52:29'),
(52, 4, 4, 4, 12, 5, 'مدير الموقع', 'TBJnJpM8,hyD2Ii6VXppD0au3HcGbx5QLnb,aS6ksgsmd7-H', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"1\"}', 200, '2026-05-20 20:52:31'),
(53, 4, 4, 4, 12, 5, 'مدير الموقع', 'TBJnJpM8,hyD2Ii6VXppD0au3HcGbx5QLnb,aS6ksgsmd7-H', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"3\"}', 200, '2026-05-20 20:52:31'),
(54, 4, 4, 4, 12, 5, 'مدير الموقع', 'TBJnJpM8,hyD2Ii6VXppD0au3HcGbx5QLnb,aS6ksgsmd7-H', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"1\"}', 200, '2026-05-20 20:52:31'),
(55, 4, 4, 4, 12, 5, 'مدير الموقع', 'TBJnJpM8,hyD2Ii6VXppD0au3HcGbx5QLnb,aS6ksgsmd7-H', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"1\"}', 200, '2026-05-20 20:53:04'),
(56, 4, 4, 4, 12, 5, 'مدير الموقع', 'TBJnJpM8,hyD2Ii6VXppD0au3HcGbx5QLnb,aS6ksgsmd7-H', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"3\"}', 200, '2026-05-20 20:53:04'),
(57, 4, 4, 4, 12, 5, 'مدير الموقع', 'TBJnJpM8,hyD2Ii6VXppD0au3HcGbx5QLnb,aS6ksgsmd7-H', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"1\"}', 200, '2026-05-20 20:53:05'),
(58, 4, 4, 4, 12, 5, 'مدير الموقع', 'TBJnJpM8,hyD2Ii6VXppD0au3HcGbx5QLnb,aS6ksgsmd7-H', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"3\"}', 200, '2026-05-20 20:54:10'),
(59, 4, 4, 4, 12, 5, 'مدير الموقع', 'TBJnJpM8,hyD2Ii6VXppD0au3HcGbx5QLnb,aS6ksgsmd7-H', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"1\"}', 200, '2026-05-20 20:54:10'),
(60, 4, 4, 4, 12, 5, 'مدير الموقع', 'TBJnJpM8,hyD2Ii6VXppD0au3HcGbx5QLnb,aS6ksgsmd7-H', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"1\"}', 200, '2026-05-20 20:54:10'),
(61, 4, 4, 4, 12, 5, 'مدير الموقع', 'TBJnJpM8,hyD2Ii6VXppD0au3HcGbx5QLnb,aS6ksgsmd7-H', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'send_message', 'chats', 'send', 'إرسال', NULL, 24, NULL, '{\"receiver_id\":5,\"message_id\":24}', 'http://localhost/ems/chats/send_message.php', 'POST', '{\"receiver_id\":\"5\",\"message\":\"و\"}', 200, '2026-05-20 20:54:51'),
(62, 4, 4, 4, 12, 5, 'مدير الموقع', 'TBJnJpM8,hyD2Ii6VXppD0au3HcGbx5QLnb,aS6ksgsmd7-H', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-20 20:55:03'),
(63, 4, 0, 0, 13, 12, 'ادارة المبيعات', '5gj6YWB0YUTfAV66tbg,w9PiBFXlrKFDaIKLMm-ES3bsc-8J', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"مبيعات\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-20 20:56:07'),
(64, 4, 4, 4, 12, 5, 'مدير الموقع', 'gD0iWUHYRuasDmh1FjNVpmvTEF8RTYzgBBkKOYMNeYvYtPIW', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"مدير موقع الروسية\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-21 08:51:38'),
(65, 4, 4, 4, 12, 5, 'مدير الموقع', 'gD0iWUHYRuasDmh1FjNVpmvTEF8RTYzgBBkKOYMNeYvYtPIW', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"1\"}', 200, '2026-05-21 08:52:40'),
(66, 4, 4, 4, 12, 5, 'مدير الموقع', 'gD0iWUHYRuasDmh1FjNVpmvTEF8RTYzgBBkKOYMNeYvYtPIW', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"3\"}', 200, '2026-05-21 08:52:40'),
(67, 4, 4, 4, 12, 5, 'مدير الموقع', 'gD0iWUHYRuasDmh1FjNVpmvTEF8RTYzgBBkKOYMNeYvYtPIW', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"1\"}', 200, '2026-05-21 08:52:40'),
(68, 4, 4, 4, 12, 5, 'مدير الموقع', 'gD0iWUHYRuasDmh1FjNVpmvTEF8RTYzgBBkKOYMNeYvYtPIW', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"1\"}', 200, '2026-05-21 08:53:47'),
(69, 4, 4, 4, 12, 5, 'مدير الموقع', 'gD0iWUHYRuasDmh1FjNVpmvTEF8RTYzgBBkKOYMNeYvYtPIW', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"3\"}', 200, '2026-05-21 08:53:47'),
(70, 4, 4, 4, 12, 5, 'مدير الموقع', 'gD0iWUHYRuasDmh1FjNVpmvTEF8RTYzgBBkKOYMNeYvYtPIW', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"1\"}', 200, '2026-05-21 08:53:47'),
(71, 4, 4, 4, 12, 5, 'مدير الموقع', 'gD0iWUHYRuasDmh1FjNVpmvTEF8RTYzgBBkKOYMNeYvYtPIW', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-21 08:55:16'),
(72, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'Cc,CKW1SwX8Rf52my7dp4eruZujDm9eVzh9HCvVHOcs1zjcQ', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"يسن\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-21 08:55:24'),
(73, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'DRVvvyq9fzI,ZfTI5luXxKt-7mLvcH7o5lib4-EoBkLGsxRk', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-23 15:45:06'),
(74, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'DRVvvyq9fzI,ZfTI5luXxKt-7mLvcH7o5lib4-EoBkLGsxRk', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-23 15:47:25'),
(75, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'xHb5A,SSiTGG0ECNCbSx2kn48EOwNiCS2jBfVnGpvpofx,yW', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"يسن\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-23 15:47:31'),
(76, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'uQO71ppJ67A6PtmZQZkz7OBGGw4TNxqMNqHfod37r3ZDXsKw', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-23 15:49:53'),
(77, 4, 0, 0, 7, 4, 'ادارة الموارد البشرية', 'pLxrccD-J3PfVOFcTneAOAVU0xpCV5s2seAPvy85CK2BoAPg', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"اروينا\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-23 15:49:57'),
(78, 4, 0, 0, 7, 4, 'ادارة الموارد البشرية', 'pLxrccD-J3PfVOFcTneAOAVU0xpCV5s2seAPvy85CK2BoAPg', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-23 15:50:28'),
(79, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'B3WL-KeNuf7gdUm-rQink9d2oMpMbpiRgI0LiUo0O68LS360', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"حركة - الروسية\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-23 15:50:32'),
(80, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'B3WL-KeNuf7gdUm-rQink9d2oMpMbpiRgI0LiUo0O68LS360', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-23 15:51:50'),
(81, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'QbjPIvbWkizbtFBKba8oyN4AU-uFrKVvQnvG0T7KZaIMW4o7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"يسن\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-23 15:51:55'),
(82, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'QbjPIvbWkizbtFBKba8oyN4AU-uFrKVvQnvG0T7KZaIMW4o7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-23 15:52:10'),
(83, 4, 4, 4, 12, 5, 'مدير الموقع', 'Wc8T75WGRmOrz-dCHMgw6Iy8cuXlbB6-7GHfrQG0JMlBS-HX', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"مدير موقع الروسية\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-23 15:52:16'),
(84, 4, 4, 4, 12, 5, 'مدير الموقع', 'Wc8T75WGRmOrz-dCHMgw6Iy8cuXlbB6-7GHfrQG0JMlBS-HX', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-23 15:53:21'),
(85, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'R,OCwC3zuMb87EaconByCbhgWRKOHN3XbAmdDe0xASecHWLB', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"يسن\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-23 15:53:27'),
(86, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'R,OCwC3zuMb87EaconByCbhgWRKOHN3XbAmdDe0xASecHWLB', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'hours_approval', 'approvals', 'get_notes', 'get_notes', NULL, 217, NULL, NULL, 'http://localhost/ems/Approvals/hours_approval.php', 'POST', '{\"action\":\"get_notes\",\"timesheet_id\":\"217\"}', 200, '2026-05-23 15:54:13'),
(87, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'R,OCwC3zuMb87EaconByCbhgWRKOHN3XbAmdDe0xASecHWLB', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-23 15:55:54'),
(88, 4, 0, 0, 7, 4, 'ادارة الموارد البشرية', 'yucJWv9EqO,SVJJHzy20rsnMj3,kF9eAW-BIuIFhBUgehK0Y', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"اروينا\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-23 15:55:58'),
(89, 4, 0, 0, 7, 4, 'ادارة الموارد البشرية', 'yucJWv9EqO,SVJJHzy20rsnMj3,kF9eAW-BIuIFhBUgehK0Y', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-23 15:56:49'),
(90, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'NcFxEc3Ae5tYiWZmXVZqAWZVDiUFdRBY8,sJhqQhF2WxqIth', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"مبيعات\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-23 15:56:55'),
(91, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'NcFxEc3Ae5tYiWZmXVZqAWZVDiUFdRBY8,sJhqQhF2WxqIth', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'send_message', 'chats', 'send', 'إرسال', NULL, 25, NULL, '{\"receiver_id\":4,\"message_id\":25}', 'http://localhost/ems/chats/send_message.php', 'POST', '{\"receiver_id\":\"4\",\"message\":\"tgl gt\"}', 200, '2026-05-23 15:57:51'),
(92, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'NcFxEc3Ae5tYiWZmXVZqAWZVDiUFdRBY8,sJhqQhF2WxqIth', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'send_message', 'chats', 'send', 'إرسال', NULL, 26, NULL, '{\"receiver_id\":14,\"message_id\":26}', 'http://localhost/ems/chats/send_message.php', 'POST', '{\"receiver_id\":\"14\",\"message\":\"tm gl\"}', 200, '2026-05-23 15:57:55'),
(93, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'NcFxEc3Ae5tYiWZmXVZqAWZVDiUFdRBY8,sJhqQhF2WxqIth', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'send_message', 'chats', 'send', 'إرسال', NULL, 27, NULL, '{\"receiver_id\":10,\"message_id\":27}', 'http://localhost/ems/chats/send_message.php', 'POST', '{\"receiver_id\":\"10\",\"message\":\"tgm\"}', 200, '2026-05-23 15:58:02'),
(94, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'NcFxEc3Ae5tYiWZmXVZqAWZVDiUFdRBY8,sJhqQhF2WxqIth', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'send_message', 'chats', 'send', 'إرسال', NULL, 28, NULL, '{\"receiver_id\":6,\"message_id\":28}', 'http://localhost/ems/chats/send_message.php', 'POST', '{\"receiver_id\":\"6\",\"message\":\"tmglt\"}', 200, '2026-05-23 15:58:07'),
(95, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'NcFxEc3Ae5tYiWZmXVZqAWZVDiUFdRBY8,sJhqQhF2WxqIth', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-23 15:58:09'),
(96, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'rNcRMho7HT68ee79,-KyqEYTbfa1y0n-ahVbASTlucoF3lun', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"يسن\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-23 15:58:14'),
(97, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'rNcRMho7HT68ee79,-KyqEYTbfa1y0n-ahVbASTlucoF3lun', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-23 15:59:10'),
(98, 4, 4, 4, 12, 5, 'مدير الموقع', '5mZabL-zXnvJJXi7zP2Ok3AxgwhpykgwKxQ,BT-VHfmU,FyU', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"مدير موقع الروسية\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-23 15:59:22'),
(99, 4, 4, 4, 12, 5, 'مدير الموقع', '5mZabL-zXnvJJXi7zP2Ok3AxgwhpykgwKxQ,BT-VHfmU,FyU', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"1\"}', 200, '2026-05-23 15:59:34'),
(100, 4, 4, 4, 12, 5, 'مدير الموقع', '5mZabL-zXnvJJXi7zP2Ok3AxgwhpykgwKxQ,BT-VHfmU,FyU', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"3\"}', 200, '2026-05-23 15:59:34'),
(101, 4, 4, 4, 12, 5, 'مدير الموقع', '5mZabL-zXnvJJXi7zP2Ok3AxgwhpykgwKxQ,BT-VHfmU,FyU', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"1\"}', 200, '2026-05-23 15:59:34'),
(102, 4, 4, 4, 12, 5, 'مدير الموقع', '5mZabL-zXnvJJXi7zP2Ok3AxgwhpykgwKxQ,BT-VHfmU,FyU', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"1\"}', 200, '2026-05-23 15:59:58'),
(103, 4, 4, 4, 12, 5, 'مدير الموقع', '5mZabL-zXnvJJXi7zP2Ok3AxgwhpykgwKxQ,BT-VHfmU,FyU', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"3\"}', 200, '2026-05-23 15:59:58'),
(104, 4, 4, 4, 12, 5, 'مدير الموقع', '5mZabL-zXnvJJXi7zP2Ok3AxgwhpykgwKxQ,BT-VHfmU,FyU', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"1\"}', 200, '2026-05-23 15:59:58'),
(105, 4, 4, 4, 12, 5, 'مدير الموقع', '5mZabL-zXnvJJXi7zP2Ok3AxgwhpykgwKxQ,BT-VHfmU,FyU', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_main_cats', 'get_main_cats', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_main_cats\",\"equipment_type\":\"1\",\"event_type_code\":\"OPR\"}', 200, '2026-05-23 16:00:30'),
(106, 4, 4, 4, 12, 5, 'مدير الموقع', '5mZabL-zXnvJJXi7zP2Ok3AxgwhpykgwKxQ,BT-VHfmU,FyU', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_sub_cats', 'get_sub_cats', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_sub_cats\",\"equipment_type\":\"1\",\"event_type_code\":\"OPR\",\"main_cat_code\":\"OPP\"}', 200, '2026-05-23 16:00:32');
INSERT INTO `activity_logs` (`id`, `company_id`, `project_id`, `contract_id`, `user_id`, `role_id`, `role_name`, `session_id`, `ip_address`, `user_agent`, `screen_name`, `module_name`, `action_type`, `button_name`, `field_name`, `record_id`, `old_value`, `new_value`, `url`, `http_method`, `request_payload`, `response_status`, `created_at`) VALUES
(107, 4, 4, 4, 12, 5, 'مدير الموقع', '5mZabL-zXnvJJXi7zP2Ok3AxgwhpykgwKxQ,BT-VHfmU,FyU', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_details', 'get_details', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_details\",\"equipment_type\":\"1\",\"event_type_code\":\"OPR\",\"main_cat_code\":\"OPP\",\"sub_cat\":\"ساعات إنتاج\"}', 200, '2026-05-23 16:00:34'),
(108, 4, 4, 4, 12, 5, 'مدير الموقع', '5mZabL-zXnvJJXi7zP2Ok3AxgwhpykgwKxQ,BT-VHfmU,FyU', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=2', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"2\"}', 200, '2026-05-23 16:00:56'),
(109, 4, 4, 4, 12, 5, 'مدير الموقع', '5mZabL-zXnvJJXi7zP2Ok3AxgwhpykgwKxQ,BT-VHfmU,FyU', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=2', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"3\"}', 200, '2026-05-23 16:00:56'),
(110, 4, 4, 4, 12, 5, 'مدير الموقع', '5mZabL-zXnvJJXi7zP2Ok3AxgwhpykgwKxQ,BT-VHfmU,FyU', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=2', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"2\"}', 200, '2026-05-23 16:00:56'),
(111, 4, 4, 4, 12, 5, 'مدير الموقع', '5mZabL-zXnvJJXi7zP2Ok3AxgwhpykgwKxQ,BT-VHfmU,FyU', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'hours_approval_followup', 'approvals', 'get_notes', 'get_notes', NULL, 245, NULL, NULL, 'http://localhost/ems/Approvals/hours_approval_followup.php', 'POST', '{\"action\":\"get_notes\",\"timesheet_id\":\"245\"}', 200, '2026-05-23 16:01:33'),
(112, 4, 4, 4, 12, 5, 'مدير الموقع', '5mZabL-zXnvJJXi7zP2Ok3AxgwhpykgwKxQ,BT-VHfmU,FyU', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'project_users', 'main', 'create', 'create', NULL, NULL, NULL, NULL, 'http://localhost/ems/main/project_users.php', 'POST', '{\"username\":\"مدير موقع الروسية\",\"uid\":\"0\"}', 200, '2026-05-23 16:03:00'),
(113, 4, 4, 4, 12, 5, 'مدير الموقع', '5mZabL-zXnvJJXi7zP2Ok3AxgwhpykgwKxQ,BT-VHfmU,FyU', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-23 16:03:04'),
(114, 4, 4, 4, 12, 5, 'مدير الموقع', 'EqJQqI5gMCODZ5AkhijZI3KVRbQEKwnGwP2p0CbEoMMr41K3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"مدير موقع الروسية\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-23 16:06:32'),
(115, 4, 4, 4, 12, 5, 'مدير الموقع', 'EqJQqI5gMCODZ5AkhijZI3KVRbQEKwnGwP2p0CbEoMMr41K3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=3', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"1\"}', 200, '2026-05-23 16:18:06'),
(116, 4, 4, 4, 12, 5, 'مدير الموقع', 'EqJQqI5gMCODZ5AkhijZI3KVRbQEKwnGwP2p0CbEoMMr41K3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=3', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"3\"}', 200, '2026-05-23 16:18:06'),
(117, 4, 4, 4, 12, 5, 'مدير الموقع', 'EqJQqI5gMCODZ5AkhijZI3KVRbQEKwnGwP2p0CbEoMMr41K3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=3', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"3\"}', 200, '2026-05-23 16:18:06'),
(118, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'WQO2kO8TDe3VUSYYq4t0qPn,ozXBZZ3ghGMnLzU7iVHIwojU', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php?timeout=1', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-25 06:00:40'),
(119, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'WQO2kO8TDe3VUSYYq4t0qPn,ozXBZZ3ghGMnLzU7iVHIwojU', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-25 06:05:33'),
(120, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'VocVJBZ-v6KPDIDSNyFRAyMSm2128,W5SCAxwijdVQJJw8AC', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"يسن\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-25 06:05:40'),
(121, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'NL-xP6z9-fPeGndKtWr2Hv6WP1kDyE50ABOK1JgMWQdkAo-f', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php?timeout=1', 'POST', '{\"username\":\"مبيعات\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-25 08:32:12'),
(122, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'NL-xP6z9-fPeGndKtWr2Hv6WP1kDyE50ABOK1JgMWQdkAo-f', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'contracts', 'contracts', 'create', 'create', NULL, NULL, NULL, NULL, 'http://localhost/ems/Contracts/contracts.php', 'POST', '{\"contract_id\":\"5\"}', 200, '2026-05-25 08:33:13'),
(123, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'NL-xP6z9-fPeGndKtWr2Hv6WP1kDyE50ABOK1JgMWQdkAo-f', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-25 08:34:51'),
(124, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'Kvp3HCckOGZ5Zmj4ZPCD,D90F68-9jNdMVIUnO4p3CN-UYde', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-25 08:34:59'),
(125, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'Kvp3HCckOGZ5Zmj4ZPCD,D90F68-9jNdMVIUnO4p3CN-UYde', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-25 08:35:33'),
(126, 4, 4, 4, 12, 5, 'مدير الموقع', '9XqPvfN-HHEkJi8VZ2o-v,WIiswqXD3GO2o8iY,HNxStMHHt', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"مدير موقع الروسية\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-25 08:35:41'),
(127, 4, 4, 4, 12, 5, 'مدير الموقع', '9XqPvfN-HHEkJi8VZ2o-v,WIiswqXD3GO2o8iY,HNxStMHHt', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"1\"}', 200, '2026-05-25 08:36:00'),
(128, 4, 4, 4, 12, 5, 'مدير الموقع', '9XqPvfN-HHEkJi8VZ2o-v,WIiswqXD3GO2o8iY,HNxStMHHt', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"3\"}', 200, '2026-05-25 08:36:00'),
(129, 4, 4, 4, 12, 5, 'مدير الموقع', '9XqPvfN-HHEkJi8VZ2o-v,WIiswqXD3GO2o8iY,HNxStMHHt', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"1\"}', 200, '2026-05-25 08:36:00'),
(130, 4, 4, 4, 12, 5, 'مدير الموقع', '9XqPvfN-HHEkJi8VZ2o-v,WIiswqXD3GO2o8iY,HNxStMHHt', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-25 08:36:26'),
(131, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'vTcjjiJGQTGkE,AtJiFDBkOYyWXTJe6RwRWAGzgrSfAWvp-L', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-25 08:36:46'),
(132, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'vTcjjiJGQTGkE,AtJiFDBkOYyWXTJe6RwRWAGzgrSfAWvp-L', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-25 08:37:10'),
(133, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'gigcUjQm-SJxk5RJJpI,E4DUHWTFTnbUlce,j60VrV2b2nuy', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"يسن\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-25 08:37:17'),
(134, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'gigcUjQm-SJxk5RJJpI,E4DUHWTFTnbUlce,j60VrV2b2nuy', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-26 11:01:41'),
(135, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'gEFjskrNhEz7xDI2PNzTpFuzQRHDERGgDuSVFnHUjrCYZ2X5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-26 11:01:54'),
(136, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'gEFjskrNhEz7xDI2PNzTpFuzQRHDERGgDuSVFnHUjrCYZ2X5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-26 11:05:10'),
(137, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'siOX7pAJYwMW6JCohwdukTpQ1ihFepKGqoQj-2,HXsl2jKUA', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-26 11:06:31'),
(138, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'siOX7pAJYwMW6JCohwdukTpQ1ihFepKGqoQj-2,HXsl2jKUA', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-26 11:08:39'),
(139, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'fM31J1Bz-3rhlHLaO06fDTCPIC7Bt94aq8qroTojzLIb47,1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"مبيعات\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-26 11:08:46'),
(140, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'fM31J1Bz-3rhlHLaO06fDTCPIC7Bt94aq8qroTojzLIb47,1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-26 11:11:23'),
(141, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'O7-hTEUuH49fsEJZBlP1qAqaBf7ScgWoGWDFuJSk78pHRN4k', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"يسن\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-26 11:11:35'),
(142, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'O7-hTEUuH49fsEJZBlP1qAqaBf7ScgWoGWDFuJSk78pHRN4k', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-26 11:19:30'),
(143, 4, 4, 4, 12, 5, 'مدير الموقع', 'A8j,DHB5Qw9zmWK-jVCrXrf05KCRYz,1nxEn2mddypgWplTt', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"مدير موقع الروسية\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-26 11:19:36'),
(144, 4, 4, 4, 12, 5, 'مدير الموقع', 'A8j,DHB5Qw9zmWK-jVCrXrf05KCRYz,1nxEn2mddypgWplTt', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"3\"}', 200, '2026-05-26 11:19:41'),
(145, 4, 4, 4, 12, 5, 'مدير الموقع', 'A8j,DHB5Qw9zmWK-jVCrXrf05KCRYz,1nxEn2mddypgWplTt', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"1\"}', 200, '2026-05-26 11:19:41'),
(146, 4, 4, 4, 12, 5, 'مدير الموقع', 'A8j,DHB5Qw9zmWK-jVCrXrf05KCRYz,1nxEn2mddypgWplTt', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"1\"}', 200, '2026-05-26 11:19:41'),
(147, 4, 4, 4, 12, 5, 'مدير الموقع', 'A8j,DHB5Qw9zmWK-jVCrXrf05KCRYz,1nxEn2mddypgWplTt', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-26 11:30:46'),
(148, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'Pu3,e2HDOXdDzTyETjdlKRU0iVq0dIlpRc0-7hHj16IgrbSV', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"حركة - الروسية\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-26 11:30:50'),
(149, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'Pu3,e2HDOXdDzTyETjdlKRU0iVq0dIlpRc0-7hHj16IgrbSV', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-26 11:47:55'),
(150, 4, 0, 0, 7, 4, 'ادارة الموارد البشرية', 'e49VcPmojGJ23IxP3y2IIXusMf3yGKdsj-7b2FibgFsypVL2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"اروينا\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-26 11:48:00'),
(151, 4, 0, 0, 7, 4, 'ادارة الموارد البشرية', 'e49VcPmojGJ23IxP3y2IIXusMf3yGKdsj-7b2FibgFsypVL2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-26 11:49:29'),
(152, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'z9DUOvZV8r6czaYk6coQrkSTfoJ7Ns1LTabNcvtRW0NgguaD', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"حركة - الروسية\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-26 11:49:34'),
(153, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'z9DUOvZV8r6czaYk6coQrkSTfoJ7Ns1LTabNcvtRW0NgguaD', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-26 11:51:56'),
(154, 4, 0, 0, 7, 4, 'ادارة الموارد البشرية', 'Td8KJFiC2CZIYCQ00GMXickDqeZzGOAF4v8imG,-mOfxPCx1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"اروينا\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-26 11:52:00'),
(155, 4, 0, 0, 7, 4, 'ادارة الموارد البشرية', 'Td8KJFiC2CZIYCQ00GMXickDqeZzGOAF4v8imG,-mOfxPCx1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-26 11:53:20'),
(156, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'XcYi1UJdBLW39QvDLU8GBiYErQ7fduTi-2RB3FCY4gKrp3Yo', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-26 11:53:26'),
(157, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'XcYi1UJdBLW39QvDLU8GBiYErQ7fduTi-2RB3FCY4gKrp3Yo', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-26 12:03:34'),
(158, 4, 0, 0, 7, 4, 'ادارة الموارد البشرية', 'NRxdj6XLn6J0FQL2wQU,NryHKTTZDjDPnunl0QReg35438Z-', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"اروينا\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-26 12:03:38'),
(159, 4, 0, 0, 4, 1, 'ادارة التشغيل', '2JYcsrFZhyJLJQRsje2Avo-USJYPugjhp6UnwGdT0E5ZxZm9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-30 09:43:44'),
(160, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'ZHOE9VsX-x3fPcBfUZ-nAgaGupK-F1DlfaLBrsrBDtHVM1sS', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"حركة - الروسية\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-30 21:15:23'),
(161, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'ZHOE9VsX-x3fPcBfUZ-nAgaGupK-F1DlfaLBrsrBDtHVM1sS', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'movement_operations', 'movement', 'save_single_operation', 'save_single_operation', NULL, NULL, NULL, NULL, 'http://localhost/ems/movement/movement_operations.php?project_id=4', 'POST', '{\"action\":\"save_single_operation\",\"op_id\":\"16\",\"equipment_category\":\"أساسي\",\"shift_type\":\"B\",\"status\":\"1\",\"start\":\"2026-05-11\",\"end\":\"2026-07-01\",\"json\":\"1\"}', 200, '2026-05-30 21:15:48'),
(162, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'ZHOE9VsX-x3fPcBfUZ-nAgaGupK-F1DlfaLBrsrBDtHVM1sS', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'move_oprators', 'movement', 'update', 'update', NULL, 14, NULL, NULL, 'http://localhost/ems/movement/move_oprators.php', 'POST', '{\"operation_id\":\"14\",\"shift_type\":\"N\"}', 200, '2026-05-30 21:16:28'),
(163, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'FezqYXIatBIOmlC,qJ4AXYYKA,G4gLrVogpQX6aTYzZTEJw1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php?timeout=1', 'POST', '{\"username\":\"حركة - الروسية\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-30 22:43:48'),
(164, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'FezqYXIatBIOmlC,qJ4AXYYKA,G4gLrVogpQX6aTYzZTEJw1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'movement_operations', 'movement', 'save_single_operation', 'save_single_operation', NULL, NULL, NULL, NULL, 'http://localhost/ems/movement/movement_operations.php?project_id=4', 'POST', '{\"action\":\"save_single_operation\",\"op_id\":\"16\",\"equipment_category\":\"أساسي\",\"shift_type\":\"D\",\"status\":\"1\",\"start\":\"2026-05-11\",\"end\":\"2026-07-01\",\"json\":\"1\"}', 200, '2026-05-30 22:56:39'),
(165, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'FezqYXIatBIOmlC,qJ4AXYYKA,G4gLrVogpQX6aTYzZTEJw1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'movement_operations', 'movement', 'create', 'add_new_driver', NULL, 22, NULL, NULL, 'http://localhost/ems/movement/movement_operations.php?project_id=4', 'POST', '{\"action\":\"add_new_driver\",\"driver_id\":\"22\",\"equipment_id\":\"8\",\"shift_type\":\"D\",\"start_date\":\"2026-05-31\",\"end_date\":\"\",\"json\":\"1\"}', 200, '2026-05-30 22:58:40'),
(166, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'FezqYXIatBIOmlC,qJ4AXYYKA,G4gLrVogpQX6aTYzZTEJw1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'movement_operations', 'movement', 'save_single_driver', 'save_single_driver', NULL, NULL, NULL, NULL, 'http://localhost/ems/movement/movement_operations.php?project_id=4', 'POST', '{\"action\":\"save_single_driver\",\"rel_id\":\"23\",\"shift_type\":\"D\",\"status\":\"0\",\"end_date\":\"2026-05-30\",\"json\":\"1\"}', 200, '2026-05-30 23:04:41'),
(167, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'FezqYXIatBIOmlC,qJ4AXYYKA,G4gLrVogpQX6aTYzZTEJw1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'movement_operations', 'movement', 'save_single_driver', 'save_single_driver', NULL, NULL, NULL, NULL, 'http://localhost/ems/movement/movement_operations.php?project_id=4', 'POST', '{\"action\":\"save_single_driver\",\"rel_id\":\"7\",\"shift_type\":\"D\",\"status\":\"1\",\"start_date\":\"2026-04-01\",\"end_date\":\"\",\"json\":\"1\"}', 200, '2026-05-30 23:08:25'),
(168, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'FezqYXIatBIOmlC,qJ4AXYYKA,G4gLrVogpQX6aTYzZTEJw1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-30 23:09:44'),
(169, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'SrYC1z6XsgNbM5oVjhSBNmO9lnnAqBdn2ZYRhxWqpR8RCKoj', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"يسن\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-30 23:09:49'),
(170, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'SrYC1z6XsgNbM5oVjhSBNmO9lnnAqBdn2ZYRhxWqpR8RCKoj', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-30 23:10:14'),
(171, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'Eg9OxQD64C3cSZi2KTzLazJXlVHwFkCH3yyuY2nz6hjRbpuK', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"يسن\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-30 23:10:17'),
(172, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'Eg9OxQD64C3cSZi2KTzLazJXlVHwFkCH3yyuY2nz6hjRbpuK', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'equipments_fleet', 'equipments', 'create', 'create', NULL, NULL, NULL, NULL, 'http://localhost/ems/Equipments/equipments_fleet.php', 'POST', '{\"suppliers\":\"7\",\"code\":\"itx\",\"type\":\"1\",\"name\":\"itx\",\"serial_number\":\"1212389\",\"chassis_number\":\"\",\"machine_number\":\"\",\"manufacturer\":\"\",\"model\":\"\",\"manufacturing_year\":\"\",\"import_year\":\"\",\"equipment_condition\":\"في حالة جيدة\",\"operating_hours\":\"\",\"engine_condition\":\"جيدة\",\"tires_condition\":\"N/A\",\"actual_owner_name\":\"\",\"owner_type\":\"\",\"owner_phone\":\"\",\"owner_supplier_relation\":\"\",\"license_number\":\"\",\"license_authority\":\"\",\"document_type\":\"\",\"license_expiry_date\":\"\",\"inspection_certificate_number\":\"\",\"last_inspection_date\":\"\",\"current_location\":\"\",\"availability_state\":\"متوفرة\",\"site_supervisor_name\":\"\",\"site_supervisor_contact\":\"\",\"estimated_value\":\"\",\"daily_rental_price\":\"\",\"monthly_rental_price\":\"\",\"insurance_status\":\"\",\"general_notes\":\"\",\"last_maintenance_date\":\"\",\"status\":\"0\"}', 302, '2026-05-30 23:11:35'),
(173, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'Eg9OxQD64C3cSZi2KTzLazJXlVHwFkCH3yyuY2nz6hjRbpuK', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'oprators', 'oprators', 'update', 'save_operation', NULL, 7, NULL, NULL, 'http://localhost/ems/Oprators/oprators.php?project_id=4', 'POST', '{\"operation_id\":\"\",\"project_id\":\"4\",\"contract_id\":\"4\",\"supplier_id\":\"7\",\"type\":\"1\",\"equipment\":\"15\",\"equipment_category\":\"أساسي\",\"start\":\"2026-05-31\",\"end\":\"2027-02-19\",\"hours\":\"0\",\"total_equipment_hours\":\"200\",\"shift_hours\":\"10\",\"shift_type\":\"B\",\"status\":\"1\",\"action\":\"save_operation\",\"save_operation_submit\":\"\"}', 200, '2026-05-30 23:12:14'),
(174, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'Eg9OxQD64C3cSZi2KTzLazJXlVHwFkCH3yyuY2nz6hjRbpuK', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-30 23:12:24'),
(175, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'LQSWN1lDrYbq,wHEY1QalewfbLoCbHBY-e7B48-SUog2MhnK', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"حركة - الروسية\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-30 23:12:28'),
(176, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'LQSWN1lDrYbq,wHEY1QalewfbLoCbHBY-e7B48-SUog2MhnK', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'movement_operations', 'movement', 'create', 'add_new_driver', NULL, 19, NULL, NULL, 'http://localhost/ems/movement/movement_operations.php?project_id=4', 'POST', '{\"action\":\"add_new_driver\",\"driver_id\":\"19\",\"equipment_id\":\"15\",\"shift_type\":\"B\",\"start_date\":\"2026-05-31\",\"end_date\":\"\",\"json\":\"1\"}', 200, '2026-05-30 23:14:06'),
(177, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'LQSWN1lDrYbq,wHEY1QalewfbLoCbHBY-e7B48-SUog2MhnK', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-30 23:14:12'),
(178, 4, 4, 4, 12, 5, 'مدير الموقع', 'V,Bw-CxTQvGQp9S8ap4seuLNqocB39w8B4OforJIEwoJSl3w', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"مدير موقع الروسية\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-30 23:14:24'),
(179, 4, 4, 4, 12, 5, 'مدير الموقع', 'V,Bw-CxTQvGQp9S8ap4seuLNqocB39w8B4OforJIEwoJSl3w', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"1\"}', 200, '2026-05-30 23:14:28'),
(180, 4, 4, 4, 12, 5, 'مدير الموقع', 'V,Bw-CxTQvGQp9S8ap4seuLNqocB39w8B4OforJIEwoJSl3w', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"3\"}', 200, '2026-05-30 23:14:28'),
(181, 4, 4, 4, 12, 5, 'مدير الموقع', 'V,Bw-CxTQvGQp9S8ap4seuLNqocB39w8B4OforJIEwoJSl3w', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"1\"}', 200, '2026-05-30 23:14:28'),
(182, 4, 4, 4, 12, 5, 'مدير الموقع', 'V,Bw-CxTQvGQp9S8ap4seuLNqocB39w8B4OforJIEwoJSl3w', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_main_cats', 'get_main_cats', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_main_cats\",\"equipment_type\":\"1\",\"event_type_code\":\"MNT\"}', 200, '2026-05-30 23:14:42'),
(183, 4, 4, 4, 12, 5, 'مدير الموقع', 'V,Bw-CxTQvGQp9S8ap4seuLNqocB39w8B4OforJIEwoJSl3w', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_sub_cats', 'get_sub_cats', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_sub_cats\",\"equipment_type\":\"1\",\"event_type_code\":\"MNT\",\"main_cat_code\":\"PMC\"}', 200, '2026-05-30 23:14:43'),
(184, 4, 4, 4, 12, 5, 'مدير الموقع', 'V,Bw-CxTQvGQp9S8ap4seuLNqocB39w8B4OforJIEwoJSl3w', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_details', 'get_details', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_details\",\"equipment_type\":\"1\",\"event_type_code\":\"MNT\",\"main_cat_code\":\"PMC\",\"sub_cat\":\"صيانة تصحيحية\"}', 200, '2026-05-30 23:14:45'),
(185, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'CiuVs54hMgi33kBwDClpfCDWAnu,HL58Z0P6jVAtZl9VqSoJ', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php?timeout=1', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-31 08:39:37'),
(186, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'CiuVs54hMgi33kBwDClpfCDWAnu,HL58Z0P6jVAtZl9VqSoJ', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-31 09:01:42'),
(187, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', '-AYFiavqvPZ0NiBHnayWw808xQ,VzHNGu7j0O57hMLvXpO11', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"حركة - الروسية\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-31 09:01:48'),
(188, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', '-AYFiavqvPZ0NiBHnayWw808xQ,VzHNGu7j0O57hMLvXpO11', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-31 09:07:42'),
(189, 4, 0, 0, 4, 1, 'ادارة التشغيل', '5kfl-yFO6u798XlUJ016,ECyQVEueEkj91vpW43tE,COY6zE', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-31 09:07:49'),
(190, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'UOP98HwfljRLWgEBjP0iUqCFlWrQVKlsoejZNYWjVgeVg757', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-31 09:50:19'),
(191, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'BJ,35m47Aan57-bZWK7CKsecA0vI5LRtWG0K5YwhhMZ4vXJ7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"حركة - الروسية\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-31 09:50:24'),
(192, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'BJ,35m47Aan57-bZWK7CKsecA0vI5LRtWG0K5YwhhMZ4vXJ7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-31 09:50:47'),
(193, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'm-LRrfPEs9NjRuIn1K-Zh6tDbHnbP5p-YSBqkijP3TOrq7TB', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-31 09:57:24'),
(194, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'm-LRrfPEs9NjRuIn1K-Zh6tDbHnbP5p-YSBqkijP3TOrq7TB', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-31 10:07:41'),
(195, 4, 0, 0, 4, 1, 'ادارة التشغيل', '3MEAIZhlj65N1Eomo-ai-34mC-t1VKz1O7W1my6DnouaEmBW', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-31 10:07:44'),
(196, 4, 0, 0, 4, 1, 'ادارة التشغيل', '3MEAIZhlj65N1Eomo-ai-34mC-t1VKz1O7W1my6DnouaEmBW', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-31 14:37:33'),
(197, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'Y0luAKjXTkiWqj50g5mCII63lgLcmIjV8wkpybKM8qsoYArj', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-31 14:37:36'),
(198, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'Y0luAKjXTkiWqj50g5mCII63lgLcmIjV8wkpybKM8qsoYArj', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-31 14:37:41'),
(199, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'SkWssbypUPT0-JHzYJoCIl-gSh,ZDi31S4TGZ7H6uLHmMccl', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"يسن\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-31 14:37:53'),
(200, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'SkWssbypUPT0-JHzYJoCIl-gSh,ZDi31S4TGZ7H6uLHmMccl', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-31 14:40:20'),
(201, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'y3QN1Yf5MZ8iYIVHIT79wvgLebmB,Im8BNuSZgI05W3XYGdM', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-31 14:40:26'),
(202, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'P19sZXSfg6q0VhPCGP2eVbQRlR,PiBnetui9fYitDt3Q,rmr', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php?timeout=1', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-31 20:00:08'),
(203, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'P19sZXSfg6q0VhPCGP2eVbQRlR,PiBnetui9fYitDt3Q,rmr', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-31 20:01:01'),
(204, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'mZvcSUHS,yembC1BjV3J,bN6rU8N6v2UKbON-7QZJbN0XL9Y', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"يسن\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-31 20:01:06'),
(205, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'mZvcSUHS,yembC1BjV3J,bN6rU8N6v2UKbON-7QZJbN0XL9Y', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-31 20:01:23'),
(206, 4, 4, 4, 12, 5, 'مدير الموقع', 'AXoe0KhQlrERgH-CZGP9LbCY0vOv0CIgnVFHEQkDBch4iLKR', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"مدير موقع الروسية\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-31 20:01:27'),
(207, 4, 4, 4, 12, 5, 'مدير الموقع', 'AXoe0KhQlrERgH-CZGP9LbCY0vOv0CIgnVFHEQkDBch4iLKR', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-31 20:02:13'),
(208, 4, 0, 0, 7, 4, 'ادارة الموارد البشرية', 'UThMEyDJwaJL7,2l-5q2aQ37j29Y5DKszcPmbNS8YuWzykIM', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"اروينا\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-31 20:02:18'),
(209, 4, 0, 0, 7, 4, 'ادارة الموارد البشرية', 'UThMEyDJwaJL7,2l-5q2aQ37j29Y5DKszcPmbNS8YuWzykIM', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-31 20:04:11'),
(210, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'Mg2aMekOffXq33JAcaPIWc9c1unL,5F8oPIPwi1mfPJ,VxvT', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-31 20:04:16'),
(211, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'Mg2aMekOffXq33JAcaPIWc9c1unL,5F8oPIPwi1mfPJ,VxvT', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-31 20:06:55'),
(212, 4, 0, 0, 6, 3, 'ادارة الاسطول', '0zvshu-pbOuMsNOMVfecqDQqAy2Q5ifk,3BGuw77WIldLPh7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"يسن\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-31 20:07:02'),
(213, 4, 0, 0, 6, 3, 'ادارة الاسطول', '0zvshu-pbOuMsNOMVfecqDQqAy2Q5ifk,3BGuw77WIldLPh7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-31 20:07:28'),
(214, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'xrVYvucV4kw59sRsZpzSsFO7B2WhAIobGXBc6Mtv,n84RkOG', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"حركة - الروسية\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-31 20:07:33'),
(215, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'xrVYvucV4kw59sRsZpzSsFO7B2WhAIobGXBc6Mtv,n84RkOG', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-31 20:08:14'),
(216, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'lCVBeNjHkbiDTsuCYgf,iasXD4Eh2b76ArOXwQ8rhqZbqjCk', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"حركة - الروسية\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-31 20:09:21'),
(217, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'lCVBeNjHkbiDTsuCYgf,iasXD4Eh2b76ArOXwQ8rhqZbqjCk', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-31 20:09:56'),
(218, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'm9v7PDOEEllA047ZSVnx,JUZa3KKsSKKvGskYlZ6ElAKXCs3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"حركة - الروسية\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-05-31 20:10:32'),
(219, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'm9v7PDOEEllA047ZSVnx,JUZa3KKsSKKvGskYlZ6ElAKXCs3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-05-31 20:17:39'),
(220, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'fK79XiqA-Y0Kr0WBFxRrWw7C8nTGZIQj1I9yB043PMah5jcc', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-01 11:10:24'),
(221, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'fK79XiqA-Y0Kr0WBFxRrWw7C8nTGZIQj1I9yB043PMah5jcc', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-01 11:19:03'),
(222, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'hPhEmWLIiIJYZMZ7wCDqBE9jvAbBfsmvjTSQ2vPlPummr0w1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"مبيعات\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-01 11:19:21'),
(223, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'hPhEmWLIiIJYZMZ7wCDqBE9jvAbBfsmvjTSQ2vPlPummr0w1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-01 11:19:48');
INSERT INTO `activity_logs` (`id`, `company_id`, `project_id`, `contract_id`, `user_id`, `role_id`, `role_name`, `session_id`, `ip_address`, `user_agent`, `screen_name`, `module_name`, `action_type`, `button_name`, `field_name`, `record_id`, `old_value`, `new_value`, `url`, `http_method`, `request_payload`, `response_status`, `created_at`) VALUES
(224, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'LvKrL1V,zhsMj,GCk3qotgo7DyYHmnk6GLu1b7zhdcIMfeF5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"حركة - الروسية\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-01 11:19:56'),
(225, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'LvKrL1V,zhsMj,GCk3qotgo7DyYHmnk6GLu1b7zhdcIMfeF5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-01 11:24:07'),
(226, 4, 0, 0, 4, 1, 'ادارة التشغيل', '69L8QW9JGzZn-hfGtv0kGzybQfnRVvDmyc,Ruby1FLvRKabS', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-01 11:24:18'),
(227, 4, 0, 0, 4, 1, 'ادارة التشغيل', '69L8QW9JGzZn-hfGtv0kGzybQfnRVvDmyc,Ruby1FLvRKabS', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-01 11:24:43'),
(228, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'pEHVX4sGXWTKHKHfZF1LNzrBeMDLwjvr00HZjtxv6Dxy7est', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"مبيعات\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-01 11:24:49'),
(229, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'pEHVX4sGXWTKHKHfZF1LNzrBeMDLwjvr00HZjtxv6Dxy7est', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-01 11:26:41'),
(230, 4, 0, 0, 13, 12, 'ادارة المبيعات', '0F-jijvnSyavNYKDP5oL9ijG,UO7Sx7JO4zztcOvWkNHFu6t', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"مبيعات\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-01 11:27:12'),
(231, 4, 0, 0, 13, 12, 'ادارة المبيعات', '0F-jijvnSyavNYKDP5oL9ijG,UO7Sx7JO4zztcOvWkNHFu6t', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-01 11:27:26'),
(232, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'R753O2K972ymr6dk8jO4,s0XGlwtVqB,jky7mMfz8Ity39f0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"مبيعات\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-01 11:28:52'),
(233, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'esKLBm3CDuZqOysYLEfuRbMCIxxKCA9hHkmph1FQad59mcmg', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-01 11:36:58'),
(234, 4, 0, 0, 7, 4, 'ادارة الموارد البشرية', 'NUR,1C6i3ZVICyXEUaZ,07AJa,Z1ZKuW5C9UNoJBs0DtAttR', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"اروينا\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-01 11:37:34'),
(235, 4, 0, 0, 7, 4, 'ادارة الموارد البشرية', 'NUR,1C6i3ZVICyXEUaZ,07AJa,Z1ZKuW5C9UNoJBs0DtAttR', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-01 11:37:41'),
(236, 4, 0, 0, 7, 4, 'ادارة الموارد البشرية', '2grsiSMrH7dTNiQQzDaUuhjbnfYFeeCU8F2Dr23jAx-BgHCN', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"اروينا\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-01 11:37:59'),
(237, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'QEhaE1R82lQL2Fmmg1MEr1aHb3F93hwMTmuelnbAocleXBS0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-01 14:03:02'),
(238, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'QEhaE1R82lQL2Fmmg1MEr1aHb3F93hwMTmuelnbAocleXBS0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-01 14:04:53'),
(239, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'IXJOg7WkMAUeBopY,-oxqOnMjOD3lmWuUkX540wTX4-UNTBQ', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-01 14:05:11'),
(240, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'IXJOg7WkMAUeBopY,-oxqOnMjOD3lmWuUkX540wTX4-UNTBQ', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-01 14:10:50'),
(241, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'AjxYzwMCi5XEdgjh593uXF58nMgPcjpi35hlTqZjfM6ZdGiw', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-01 14:12:42'),
(242, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'AjxYzwMCi5XEdgjh593uXF58nMgPcjpi35hlTqZjfM6ZdGiw', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-01 14:26:26'),
(243, 4, 0, 0, 7, 4, 'ادارة الموارد البشرية', 'mHCdEZ8RGMEf32VWSKtj2KjgrVdU1B8H8bfvkIfLxLXiTHwJ', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"اروينا\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-01 14:26:32'),
(244, 4, 0, 0, 7, 4, 'ادارة الموارد البشرية', 'mHCdEZ8RGMEf32VWSKtj2KjgrVdU1B8H8bfvkIfLxLXiTHwJ', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-01 14:30:16'),
(245, 4, 0, 0, 7, 4, 'ادارة الموارد البشرية', '9,2qg1xWG92,A08SQhxvwK25uNkYAL9p83JX1PoRUfwAyfpe', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"اروينا\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-01 14:30:21'),
(246, 4, 0, 0, 7, 4, 'ادارة الموارد البشرية', '9,2qg1xWG92,A08SQhxvwK25uNkYAL9p83JX1PoRUfwAyfpe', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-01 14:31:56'),
(247, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'VJVB,XCRAKxgAWyB4EI70g3P4UTiDyqgi-2tYSaBexg7cT,W', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-01 14:32:04'),
(248, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'VJVB,XCRAKxgAWyB4EI70g3P4UTiDyqgi-2tYSaBexg7cT,W', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-01 14:33:50'),
(249, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'hJYtvrIIAtIlLYyl,F9VTAle4Wm4xR4Y7p4P2Uhe-WaJrrae', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"حركة - الروسية\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-01 14:33:58'),
(250, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'hJYtvrIIAtIlLYyl,F9VTAle4Wm4xR4Y7p4P2Uhe-WaJrrae', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'movement_operations', 'movement', 'save_single_operation', 'save_single_operation', NULL, NULL, NULL, NULL, 'http://localhost/ems/movement/movement_operations.php?project_id=4', 'POST', '{\"action\":\"save_single_operation\",\"op_id\":\"17\",\"equipment_category\":\"أساسي\",\"shift_type\":\"N\",\"status\":\"1\",\"start\":\"2026-05-31\",\"end\":\"2027-02-19\",\"json\":\"1\"}', 200, '2026-06-01 14:38:03'),
(251, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'hJYtvrIIAtIlLYyl,F9VTAle4Wm4xR4Y7p4P2Uhe-WaJrrae', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-02 03:21:14'),
(252, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', '5-18N54RAzWDstdwVTq-8hTp2LsoikX,xWZfWreu,mpa,Hb9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"حركة - الروسية\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-02 03:21:17'),
(253, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'dWtNTBVPH47QIkcqGv-vTIrqHj4M5axGpALSq0spAE1Tljqj', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-02 08:42:20'),
(254, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'dWtNTBVPH47QIkcqGv-vTIrqHj4M5axGpALSq0spAE1Tljqj', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-02 08:43:40'),
(255, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'MqSog85Y79jdgIePXFY5Bvc8ZMv24gtcN3EyAwI5o4ieGFXq', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"حركة - الروسية\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-02 08:43:45'),
(256, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'MqSog85Y79jdgIePXFY5Bvc8ZMv24gtcN3EyAwI5o4ieGFXq', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-02 08:57:09'),
(257, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'lKJjYwWFOaZ1xsY1YkSk1vKE3B9qfc6iZWvZ7C1WZbBj3yTz', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-02 08:57:18'),
(258, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'lKJjYwWFOaZ1xsY1YkSk1vKE3B9qfc6iZWvZ7C1WZbBj3yTz', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-02 08:58:58'),
(259, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'cTIA4HRQwTyIxeWXMuPGLjHIeSPgxoHqnq2ADiE3IPYNIdRJ', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"حركة - الروسية\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-02 09:00:01'),
(260, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'cTIA4HRQwTyIxeWXMuPGLjHIeSPgxoHqnq2ADiE3IPYNIdRJ', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-02 09:00:41'),
(261, 4, 0, 0, 7, 4, 'ادارة الموارد البشرية', 'LCmetGbW1Qd17QIEf8t-bSRiWUVK0V-,ouYyWCxtVxz,saAM', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"اروينا\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-02 09:00:49'),
(262, 4, 0, 0, 7, 4, 'ادارة الموارد البشرية', 'LCmetGbW1Qd17QIEf8t-bSRiWUVK0V-,ouYyWCxtVxz,saAM', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'send_broadcast', 'chats', 'send', 'إرسال', NULL, NULL, NULL, '{\"recipients_count\":12,\"failed_count\":0}', 'http://localhost/ems/chats/send_broadcast.php', 'POST', '{\"message\":\"vfmlk\"}', 200, '2026-06-02 09:01:55'),
(263, 4, 0, 0, 7, 4, 'ادارة الموارد البشرية', 'LCmetGbW1Qd17QIEf8t-bSRiWUVK0V-,ouYyWCxtVxz,saAM', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-02 09:03:13'),
(264, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'wRSmxBEPc9yAbTd8NRqfcHXd1-glJFsEa0CibWzj6efYowNG', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"مبيعات\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-02 09:03:19'),
(265, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'wRSmxBEPc9yAbTd8NRqfcHXd1-glJFsEa0CibWzj6efYowNG', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-02 09:06:10'),
(266, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'WJZPsA-DvfpHvKKR4hkrOMyVySL3fpT77dGKQqubQZSJdZaM', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-02 09:06:36'),
(267, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'WJZPsA-DvfpHvKKR4hkrOMyVySL3fpT77dGKQqubQZSJdZaM', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'hours_approval', 'approvals', 'get_notes', 'get_notes', NULL, 184, NULL, NULL, 'http://localhost/ems/Approvals/hours_approval.php', 'POST', '{\"action\":\"get_notes\",\"timesheet_id\":\"184\"}', 200, '2026-06-02 09:13:32'),
(268, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'WJZPsA-DvfpHvKKR4hkrOMyVySL3fpT77dGKQqubQZSJdZaM', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-02 09:15:48'),
(269, 4, 0, 0, 5, 2, 'ادارة الموردين', 'MPIC06qmquIFJeKSQmwrAqHDFKPp636wl9tDAc3qQeE2s2t4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"مصعب\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-02 09:15:54'),
(270, 4, 0, 0, 5, 2, 'ادارة الموردين', 'MPIC06qmquIFJeKSQmwrAqHDFKPp636wl9tDAc3qQeE2s2t4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'supplierscontracts', 'suppliers', 'create', 'create', NULL, NULL, NULL, NULL, 'http://localhost/ems/Suppliers/supplierscontracts.php?id=9', 'POST', '{\"project_id\":\"4\"}', 200, '2026-06-02 09:16:57'),
(271, 4, 0, 0, 5, 2, 'ادارة الموردين', 'MPIC06qmquIFJeKSQmwrAqHDFKPp636wl9tDAc3qQeE2s2t4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'supplierscontracts', 'suppliers', 'create', 'create', NULL, NULL, NULL, NULL, 'http://localhost/ems/Suppliers/supplierscontracts.php?id=9', 'POST', '{\"project_contract_id\":\"4\",\"supplier_contract_id\":\"0\"}', 200, '2026-06-02 09:17:01'),
(272, 4, 0, 0, 5, 2, 'ادارة الموردين', 'MPIC06qmquIFJeKSQmwrAqHDFKPp636wl9tDAc3qQeE2s2t4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-02 09:18:13'),
(273, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'QVwzgptwrZKjBc258FcE7Vhymte0sgkdRsYn0lYSCDQCoj74', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"يسن\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-02 09:18:24'),
(274, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'QVwzgptwrZKjBc258FcE7Vhymte0sgkdRsYn0lYSCDQCoj74', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-02 09:24:16'),
(275, 4, 0, 0, 7, 4, 'ادارة الموارد البشرية', 'Fb7HuLXVPLh,nQdC4woRR2al-k6mqh8rBqRoWxW3CgwW2XId', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"اروينا\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-02 09:24:27'),
(276, 4, 0, 0, 7, 4, 'ادارة الموارد البشرية', 'Fb7HuLXVPLh,nQdC4woRR2al-k6mqh8rBqRoWxW3CgwW2XId', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-02 09:26:11'),
(277, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'LTL9SJwuiXCJSQKri2WI9zfflJ82OTmAvWy1bEPhNsLLP8-p', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"حركة - الروسية\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-02 09:26:19'),
(278, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'LTL9SJwuiXCJSQKri2WI9zfflJ82OTmAvWy1bEPhNsLLP8-p', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'movement_operations', 'movement', 'create', 'add_new_driver', NULL, 18, NULL, NULL, 'http://localhost/ems/movement/movement_operations.php?project_id=4', 'POST', '{\"action\":\"add_new_driver\",\"driver_id\":\"18\",\"equipment_id\":\"8\",\"shift_type\":\"N\",\"start_date\":\"2026-06-02\",\"end_date\":\"\",\"json\":\"1\"}', 200, '2026-06-02 09:27:32'),
(279, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'LTL9SJwuiXCJSQKri2WI9zfflJ82OTmAvWy1bEPhNsLLP8-p', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'movement_operations', 'movement', 'save_single_operation', 'save_single_operation', NULL, NULL, NULL, NULL, 'http://localhost/ems/movement/movement_operations.php?project_id=4', 'POST', '{\"action\":\"save_single_operation\",\"op_id\":\"16\",\"equipment_category\":\"أساسي\",\"shift_type\":\"N\",\"status\":\"1\",\"start\":\"2026-05-11\",\"end\":\"2026-07-01\",\"json\":\"1\"}', 200, '2026-06-02 09:28:28'),
(280, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'LTL9SJwuiXCJSQKri2WI9zfflJ82OTmAvWy1bEPhNsLLP8-p', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-02 09:28:57'),
(281, 4, 4, 4, 12, 5, 'مدير الموقع', 'ksF7YG79NVjnZxIal1s7vfLLyCQgF3FA8Yvn,66F9v6D,wCs', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"مدير موقع الروسية\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-02 09:29:04'),
(282, 4, 4, 4, 12, 5, 'مدير الموقع', 'ksF7YG79NVjnZxIal1s7vfLLyCQgF3FA8Yvn,66F9v6D,wCs', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"1\"}', 200, '2026-06-02 09:29:48'),
(283, 4, 4, 4, 12, 5, 'مدير الموقع', 'ksF7YG79NVjnZxIal1s7vfLLyCQgF3FA8Yvn,66F9v6D,wCs', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"3\"}', 200, '2026-06-02 09:29:48'),
(284, 4, 4, 4, 12, 5, 'مدير الموقع', 'ksF7YG79NVjnZxIal1s7vfLLyCQgF3FA8Yvn,66F9v6D,wCs', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"1\"}', 200, '2026-06-02 09:29:48'),
(285, 4, 4, 4, 12, 5, 'مدير الموقع', 'ksF7YG79NVjnZxIal1s7vfLLyCQgF3FA8Yvn,66F9v6D,wCs', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_main_cats', 'get_main_cats', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_main_cats\",\"equipment_type\":\"1\",\"event_type_code\":\"EQF\"}', 200, '2026-06-02 09:32:03'),
(286, 4, 4, 4, 12, 5, 'مدير الموقع', 'ksF7YG79NVjnZxIal1s7vfLLyCQgF3FA8Yvn,66F9v6D,wCs', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_sub_cats', 'get_sub_cats', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_sub_cats\",\"equipment_type\":\"1\",\"event_type_code\":\"EQF\",\"main_cat_code\":\"COL\"}', 200, '2026-06-02 09:32:05'),
(287, 4, 4, 4, 12, 5, 'مدير الموقع', 'ksF7YG79NVjnZxIal1s7vfLLyCQgF3FA8Yvn,66F9v6D,wCs', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_details', 'get_details', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_details\",\"equipment_type\":\"1\",\"event_type_code\":\"EQF\",\"main_cat_code\":\"COL\",\"sub_cat\":\"الحساسات\"}', 200, '2026-06-02 09:32:07'),
(288, 4, 4, 4, 12, 5, 'مدير الموقع', 'ksF7YG79NVjnZxIal1s7vfLLyCQgF3FA8Yvn,66F9v6D,wCs', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_main_cats', 'get_main_cats', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_main_cats\",\"equipment_type\":\"1\",\"event_type_code\":\"MNT\"}', 200, '2026-06-02 09:32:16'),
(289, 4, 4, 4, 12, 5, 'مدير الموقع', 'ksF7YG79NVjnZxIal1s7vfLLyCQgF3FA8Yvn,66F9v6D,wCs', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_sub_cats', 'get_sub_cats', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_sub_cats\",\"equipment_type\":\"1\",\"event_type_code\":\"MNT\",\"main_cat_code\":\"PME\"}', 200, '2026-06-02 09:32:18'),
(290, 4, 4, 4, 12, 5, 'مدير الموقع', 'ksF7YG79NVjnZxIal1s7vfLLyCQgF3FA8Yvn,66F9v6D,wCs', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_details', 'get_details', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_details\",\"equipment_type\":\"1\",\"event_type_code\":\"MNT\",\"main_cat_code\":\"PME\",\"sub_cat\":\"صيانة طارئة\"}', 200, '2026-06-02 09:32:20'),
(291, 4, 4, 4, 12, 5, 'مدير الموقع', 'ksF7YG79NVjnZxIal1s7vfLLyCQgF3FA8Yvn,66F9v6D,wCs', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'create', 'create', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'POST', '{\"shift\":\"D\",\"operator\":\"12\",\"id\":\"\",\"user_id\":\"12\",\"driver\":\"\",\"date\":\"2026-06-02\",\"shift_hours\":\"10\",\"start_hours\":\"0\",\"start_minutes\":\"0\",\"start_seconds\":\"0\",\"executed_hours\":\"8\",\"bucket_hours\":\"6\",\"jackhammer_hours\":\"2\",\"extra_hours\":\"0\",\"extra_hours_total\":\"0\",\"standby_hours\":\"0\",\"dependence_hours\":\"0\",\"total_work_hours\":\"8\",\"work_notes\":\"\",\"hr_fault\":\"1\",\"maintenance_fault\":\"1\",\"marketing_fault\":\"0\",\"approval_fault\":\"0\",\"other_fault_hours\":\"0\",\"total_fault_hours\":\"2\",\"fault_notes\":\"\",\"end_hours\":\"0\",\"end_minutes\":\"0\",\"end_seconds\":\"0\",\"counter_diff\":\"0 ساعة 0 دقيقة 0 ثانية\",\"fault_type\":\"عطل معدة\",\"fault_department\":\"أعطال التبريد والتكيف\",\"fault_part\":\"الحساسات\",\"fault_details\":\"EX-EQF-COL-08-01 | حساس حرارة\",\"fault_items_json\":\"[{\\\"failure_code_id\\\":94,\\\"event_type_code\\\":\\\"EQF\\\",\\\"event_type_name\\\":\\\"عطل معدة\\\",\\\"main_category_code\\\":\\\"COL\\\",\\\"main_category_name\\\":\\\"أعطال التبريد والتكيف\\\",\\\"sub_category\\\":\\\"الحساسات\\\",\\\"failure_detail\\\":\\\"حساس حرارة\\\",\\\"full_code\\\":\\\"EX-EQF-COL-08-01\\\"},{\\\"failure_code_id\\\":147,\\\"event_type_code\\\":\\\"MNT\\\",\\\"event_type_name\\\":\\\"توقف صيانة\\\",\\\"main_category_code\\\":\\\"PME\\\",\\\"main_category_name\\\":\\\"صيانة طارئة\\\",\\\"sub_category\\\":\\\"صيانة طارئة\\\",\\\"failure_detail\\\":\\\"انقلاب\\\",\\\"full_code\\\":\\\"EX-MNT-PME-00-02\\\"}]\",\"general_notes\":\"\",\"operator_hours\":\"0\",\"machine_standby_hours\":\"0\",\"jackhammer_standby_hours\":\"0\",\"bucket_standby_hours\":\"0\",\"extra_operator_hours\":\"0\",\"operator_standby_hours\":\"1\",\"operator_notes\":\"\",\"type\":\"1\"}', 200, '2026-06-02 09:32:38'),
(292, 4, 4, 4, 12, 5, 'مدير الموقع', 'ksF7YG79NVjnZxIal1s7vfLLyCQgF3FA8Yvn,66F9v6D,wCs', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"1\"}', 200, '2026-06-02 09:32:40'),
(293, 4, 4, 4, 12, 5, 'مدير الموقع', 'ksF7YG79NVjnZxIal1s7vfLLyCQgF3FA8Yvn,66F9v6D,wCs', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"3\"}', 200, '2026-06-02 09:32:40'),
(294, 4, 4, 4, 12, 5, 'مدير الموقع', 'ksF7YG79NVjnZxIal1s7vfLLyCQgF3FA8Yvn,66F9v6D,wCs', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"1\"}', 200, '2026-06-02 09:32:40'),
(295, 4, 4, 4, 12, 5, 'مدير الموقع', 'ksF7YG79NVjnZxIal1s7vfLLyCQgF3FA8Yvn,66F9v6D,wCs', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'hours_approval', 'approvals', 'get_notes', 'get_notes', NULL, 245, NULL, NULL, 'http://localhost/ems/Approvals/hours_approval.php', 'POST', '{\"action\":\"get_notes\",\"timesheet_id\":\"245\"}', 200, '2026-06-02 09:34:00'),
(296, 4, 4, 4, 12, 5, 'مدير الموقع', 'ksF7YG79NVjnZxIal1s7vfLLyCQgF3FA8Yvn,66F9v6D,wCs', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'hours_approval', 'approvals', 'get_notes', 'get_notes', NULL, 245, NULL, NULL, 'http://localhost/ems/Approvals/hours_approval.php', 'POST', '{\"action\":\"get_notes\",\"timesheet_id\":\"245\"}', 200, '2026-06-02 09:34:23'),
(297, 4, 4, 4, 12, 5, 'مدير الموقع', 'ksF7YG79NVjnZxIal1s7vfLLyCQgF3FA8Yvn,66F9v6D,wCs', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-02 09:36:26'),
(298, 4, 0, 0, 4, 1, 'ادارة التشغيل', '33I5TI6eSa6ZbETV8mAeSGQiPK5CqPrZoae4AGvOgymPLrGk', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-02 09:36:31'),
(299, 4, 0, 0, 4, 1, 'ادارة التشغيل', '33I5TI6eSa6ZbETV8mAeSGQiPK5CqPrZoae4AGvOgymPLrGk', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'hours_approval', 'approvals', 'approve', 'approve', NULL, NULL, NULL, NULL, 'http://localhost/ems/Approvals/hours_approval.php', 'POST', '{\"action\":\"approve\",\"ids\":\"184\"}', 200, '2026-06-02 09:38:32'),
(300, 4, 0, 0, 4, 1, 'ادارة التشغيل', '33I5TI6eSa6ZbETV8mAeSGQiPK5CqPrZoae4AGvOgymPLrGk', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'hours_approval_followup', 'approvals', 'get_notes', 'get_notes', NULL, 184, NULL, NULL, 'http://localhost/ems/Approvals/hours_approval_followup.php', 'POST', '{\"action\":\"get_notes\",\"timesheet_id\":\"184\"}', 200, '2026-06-02 09:39:03'),
(301, 4, 0, 0, 4, 1, 'ادارة التشغيل', '33I5TI6eSa6ZbETV8mAeSGQiPK5CqPrZoae4AGvOgymPLrGk', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'hours_approval_followup', 'approvals', 'create', 'add_note', NULL, 184, NULL, NULL, 'http://localhost/ems/Approvals/hours_approval_followup.php', 'POST', '{\"action\":\"add_note\",\"timesheet_id\":\"184\",\"column_name\":\"marketing_fault\",\"column_label\":\"عطل تسويق\",\"note_text\":\"w;lmgfrw;l\"}', 200, '2026-06-02 09:39:10'),
(302, 4, 0, 0, 4, 1, 'ادارة التشغيل', '33I5TI6eSa6ZbETV8mAeSGQiPK5CqPrZoae4AGvOgymPLrGk', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'hours_approval_followup', 'approvals', 'get_notes', 'get_notes', NULL, 184, NULL, NULL, 'http://localhost/ems/Approvals/hours_approval_followup.php', 'POST', '{\"action\":\"get_notes\",\"timesheet_id\":\"184\"}', 200, '2026-06-02 09:39:10'),
(303, 4, 0, 0, 4, 1, 'ادارة التشغيل', '5F5VSPktG3uiNuBecUyw9yRzl4VVUZh-Wt3tBVrNI4Fp2ybs', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-02 09:42:29'),
(304, 4, 0, 0, 4, 1, 'ادارة التشغيل', '1aTG,CkLotxml-0B-HzDrKPF54o5DFu1QJMjYjK2NJdhMSPg', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-02 16:32:03'),
(305, 4, 0, 0, 4, 1, 'ادارة التشغيل', '1aTG,CkLotxml-0B-HzDrKPF54o5DFu1QJMjYjK2NJdhMSPg', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-02 16:32:53'),
(306, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'EmOSXuCv4YbpSbMZtRZaM5baIjNcT0Q7n3U5BqrljKzFNM9C', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"حركة - الروسية\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-02 16:33:02'),
(307, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'EmOSXuCv4YbpSbMZtRZaM5baIjNcT0Q7n3U5BqrljKzFNM9C', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-02 16:33:33'),
(308, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'ezPv3gf56BBlsW9aJTNqdiZdONl-qUgmAXLwNWXW3MLfa4d8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"حركة - الروسية\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-02 16:36:07'),
(309, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'ezPv3gf56BBlsW9aJTNqdiZdONl-qUgmAXLwNWXW3MLfa4d8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-02 16:36:35'),
(310, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'oxyELIgMHDFy0XO3xu9IweLTsQ4xp4uw1SiNM1p40zgChsc0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-02 16:36:41'),
(311, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'oxyELIgMHDFy0XO3xu9IweLTsQ4xp4uw1SiNM1p40zgChsc0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'send_message', 'chats', 'send', 'إرسال', NULL, 41, NULL, '{\"receiver_id\":7,\"message_id\":41}', 'http://localhost/ems/chats/send_message.php', 'POST', '{\"receiver_id\":\"7\",\"message\":\"نة\"}', 200, '2026-06-02 16:39:00'),
(312, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'YWyGk99nKFqwbgJ5tydKM39k9V9xZkk2fTLdoqs33fHNglgo', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-03 16:41:17'),
(313, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'YWyGk99nKFqwbgJ5tydKM39k9V9xZkk2fTLdoqs33fHNglgo', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-03 16:43:04'),
(314, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'EBv8Pib9TAKBTmDysDi4RV9Xmyy5zgDVZYzNu7UkQ36DSo,N', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"يسن\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-03 16:43:15'),
(315, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'EBv8Pib9TAKBTmDysDi4RV9Xmyy5zgDVZYzNu7UkQ36DSo,N', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-03 16:43:37'),
(316, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'BstT79ly31tsR3eAmM5o457W0zqOzrPYS6yjcLbluADvOSCf', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-03 16:43:43'),
(317, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'BstT79ly31tsR3eAmM5o457W0zqOzrPYS6yjcLbluADvOSCf', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-03 16:43:52'),
(318, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'Z6xaPI8OLX8Y6lczHnUZDNazNVu8avW2RS1Z2-9R,gVgV075', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"يسن\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-03 16:43:59'),
(319, 4, 0, 0, 6, 3, 'ادارة الاسطول', 'Z6xaPI8OLX8Y6lczHnUZDNazNVu8avW2RS1Z2-9R,gVgV075', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-03 16:44:55'),
(320, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'H5PhoqS,oDCrcFMFwjfQAwq3c49iaWG2rm8vHmjuSKjM8PoA', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-03 16:45:16'),
(321, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'atwuxspDhizqmjFBSCusjqIKPTvaITbpaJYPj4rbZvC7T660', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"مبيعات\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-04 16:38:36'),
(322, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'atwuxspDhizqmjFBSCusjqIKPTvaITbpaJYPj4rbZvC7T660', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'clients', 'clients', 'export', 'export', NULL, NULL, NULL, NULL, 'http://localhost/ems/Clients/clients.php', 'GET', '{\"entity\":\"clients\",\"action\":\"export\"}', 200, '2026-06-04 16:39:04'),
(323, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'atwuxspDhizqmjFBSCusjqIKPTvaITbpaJYPj4rbZvC7T660', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'clients', 'clients', 'import_preview', 'import_preview', NULL, NULL, NULL, NULL, 'http://localhost/ems/Clients/clients.php', 'POST', '{\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-04 16:39:34'),
(324, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'atwuxspDhizqmjFBSCusjqIKPTvaITbpaJYPj4rbZvC7T660', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'clients', 'clients', 'import_preview', 'import_preview', NULL, NULL, NULL, NULL, 'http://localhost/ems/Clients/clients.php', 'POST', '{\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-04 16:40:09'),
(325, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'atwuxspDhizqmjFBSCusjqIKPTvaITbpaJYPj4rbZvC7T660', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'clients', 'clients', 'import_preview', 'import_preview', NULL, NULL, NULL, NULL, 'http://localhost/ems/Clients/clients.php', 'POST', '{\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-04 16:40:09'),
(326, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'atwuxspDhizqmjFBSCusjqIKPTvaITbpaJYPj4rbZvC7T660', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'clients', 'clients', 'template', 'template', NULL, NULL, NULL, NULL, 'http://localhost/ems/Clients/clients.php', 'GET', '{\"entity\":\"clients\",\"action\":\"template\"}', 200, '2026-06-04 16:40:26'),
(327, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'atwuxspDhizqmjFBSCusjqIKPTvaITbpaJYPj4rbZvC7T660', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'clients', 'clients', 'import_preview', 'import_preview', NULL, NULL, NULL, NULL, 'http://localhost/ems/Clients/clients.php', 'POST', '{\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-04 16:41:00'),
(328, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'atwuxspDhizqmjFBSCusjqIKPTvaITbpaJYPj4rbZvC7T660', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'projects', 'projects', 'template', 'template', NULL, NULL, NULL, NULL, 'http://localhost/ems/Projects/projects.php?client_id=2', 'GET', '{\"entity\":\"projects\",\"action\":\"template\"}', 200, '2026-06-04 16:41:44'),
(329, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'atwuxspDhizqmjFBSCusjqIKPTvaITbpaJYPj4rbZvC7T660', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'projects', 'projects', 'import_preview', 'import_preview', NULL, NULL, NULL, NULL, 'http://localhost/ems/Projects/projects.php?client_id=2', 'POST', '{\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-04 16:42:10'),
(330, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'atwuxspDhizqmjFBSCusjqIKPTvaITbpaJYPj4rbZvC7T660', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-04 16:43:22'),
(331, 4, 0, 0, 7, 4, 'ادارة الموارد البشرية', 'clWs6JiOGqY7kxQ8hdnHKdf3dK39EJnmYJ1onK5EbxL-XEkg', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"اروينا\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-04 16:43:30'),
(332, 4, 0, 0, 7, 4, 'ادارة الموارد البشرية', 'clWs6JiOGqY7kxQ8hdnHKdf3dK39EJnmYJ1onK5EbxL-XEkg', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'drivers', 'drivers', 'template', 'template', NULL, NULL, NULL, NULL, 'http://localhost/ems/Drivers/drivers.php', 'GET', '{\"entity\":\"drivers\",\"action\":\"template\"}', 200, '2026-06-04 16:43:39'),
(333, 4, 0, 0, 7, 4, 'ادارة الموارد البشرية', 'clWs6JiOGqY7kxQ8hdnHKdf3dK39EJnmYJ1onK5EbxL-XEkg', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'drivers', 'drivers', 'import_preview', 'import_preview', NULL, NULL, NULL, NULL, 'http://localhost/ems/Drivers/drivers.php', 'POST', '{\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-04 16:44:45'),
(334, 4, 0, 0, 7, 4, 'ادارة الموارد البشرية', 'clWs6JiOGqY7kxQ8hdnHKdf3dK39EJnmYJ1onK5EbxL-XEkg', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-04 16:45:31'),
(335, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'sLGDY4ldzsWJMblEvo13DnV9DAKXkDHqPDsz5lCJu-k-PT84', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-04 16:45:36'),
(336, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'sLGDY4ldzsWJMblEvo13DnV9DAKXkDHqPDsz5lCJu-k-PT84', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-04 16:46:45'),
(337, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'iFk-oNAeT1zoR2SXwWRP0C636SgNVEEOX21SaxwOzKVC9JNQ', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"حركة - الروسية\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-04 16:46:53'),
(338, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'iFk-oNAeT1zoR2SXwWRP0C636SgNVEEOX21SaxwOzKVC9JNQ', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-04 16:47:29'),
(339, 4, 4, 4, 12, 5, 'مدير الموقع', '497-BxHBp7p4rwi0yYJdgX5BZ5H1fS,f27k7Ss70-9v7Qlhr', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"مدير موقع الروسية\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-04 16:47:37');
INSERT INTO `activity_logs` (`id`, `company_id`, `project_id`, `contract_id`, `user_id`, `role_id`, `role_name`, `session_id`, `ip_address`, `user_agent`, `screen_name`, `module_name`, `action_type`, `button_name`, `field_name`, `record_id`, `old_value`, `new_value`, `url`, `http_method`, `request_payload`, `response_status`, `created_at`) VALUES
(340, 4, 4, 4, 12, 5, 'مدير الموقع', '497-BxHBp7p4rwi0yYJdgX5BZ5H1fS,f27k7Ss70-9v7Qlhr', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"1\"}', 200, '2026-06-04 16:47:41'),
(341, 4, 4, 4, 12, 5, 'مدير الموقع', '497-BxHBp7p4rwi0yYJdgX5BZ5H1fS,f27k7Ss70-9v7Qlhr', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"3\"}', 200, '2026-06-04 16:47:41'),
(342, 4, 4, 4, 12, 5, 'مدير الموقع', '497-BxHBp7p4rwi0yYJdgX5BZ5H1fS,f27k7Ss70-9v7Qlhr', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"1\"}', 200, '2026-06-04 16:47:41'),
(343, 4, 4, 4, 12, 5, 'مدير الموقع', '497-BxHBp7p4rwi0yYJdgX5BZ5H1fS,f27k7Ss70-9v7Qlhr', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'template', 'template', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"entity\":\"timesheet\",\"action\":\"template\"}', 200, '2026-06-04 16:47:54'),
(344, 4, 4, 4, 12, 5, 'مدير الموقع', '497-BxHBp7p4rwi0yYJdgX5BZ5H1fS,f27k7Ss70-9v7Qlhr', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'export', 'export', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"entity\":\"timesheet\",\"action\":\"export\"}', 200, '2026-06-04 16:48:53'),
(345, 4, 4, 4, 12, 5, 'مدير الموقع', '497-BxHBp7p4rwi0yYJdgX5BZ5H1fS,f27k7Ss70-9v7Qlhr', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'import_preview', 'import_preview', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'POST', '{\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-04 16:49:18'),
(346, 4, 4, 4, 12, 5, 'مدير الموقع', '497-BxHBp7p4rwi0yYJdgX5BZ5H1fS,f27k7Ss70-9v7Qlhr', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-04 16:50:41'),
(347, 4, 0, 0, 4, 1, 'ادارة التشغيل', ',pW81o9PojASZ,MeB6CEVDrShMeEdKZvvwqcYu1tQ,GhavCU', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-04 16:50:49'),
(348, 4, 0, 0, 4, 1, 'ادارة التشغيل', ',pW81o9PojASZ,MeB6CEVDrShMeEdKZvvwqcYu1tQ,GhavCU', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-04 17:01:07'),
(349, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'cXdhK1gfCqCOu,Ekn23Cu23WyQ,GAiW9ea,Yy733nNXz0ApL', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"مبيعات\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-04 17:01:14'),
(350, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'cXdhK1gfCqCOu,Ekn23Cu23WyQ,GAiW9ea,Yy733nNXz0ApL', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'contracts', 'contracts', 'template', 'template', NULL, NULL, NULL, NULL, 'http://localhost/ems/Contracts/contracts.php?filter_project_id=5', 'GET', '{\"entity\":\"contracts\",\"action\":\"template\"}', 200, '2026-06-04 17:01:32'),
(351, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'cXdhK1gfCqCOu,Ekn23Cu23WyQ,GAiW9ea,Yy733nNXz0ApL', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'contracts', 'contracts', 'create', 'create', NULL, NULL, NULL, NULL, 'http://localhost/ems/Contracts/contracts.php?filter_project_id=2', 'POST', '{\"contract_id\":\"2\"}', 200, '2026-06-04 17:02:09'),
(352, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'cXdhK1gfCqCOu,Ekn23Cu23WyQ,GAiW9ea,Yy733nNXz0ApL', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'projects', 'projects', 'template', 'template', NULL, NULL, NULL, NULL, 'http://localhost/ems/Projects/projects.php', 'GET', '{\"entity\":\"projects\",\"action\":\"template\"}', 200, '2026-06-04 17:02:47'),
(353, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'cXdhK1gfCqCOu,Ekn23Cu23WyQ,GAiW9ea,Yy733nNXz0ApL', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'projects', 'projects', 'import_preview', 'import_preview', NULL, NULL, NULL, NULL, 'http://localhost/ems/Projects/projects.php', 'POST', '{\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-04 17:03:22'),
(354, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'cXdhK1gfCqCOu,Ekn23Cu23WyQ,GAiW9ea,Yy733nNXz0ApL', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'projects', 'projects', 'import_commit', 'import_commit', NULL, NULL, NULL, NULL, 'http://localhost/ems/Projects/projects.php', 'POST', '{\"token\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-04 17:05:17'),
(355, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'cXdhK1gfCqCOu,Ekn23Cu23WyQ,GAiW9ea,Yy733nNXz0ApL', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'projects', 'projects', 'import_preview', 'import_preview', NULL, NULL, NULL, NULL, 'http://localhost/ems/Projects/projects.php', 'POST', '{\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-04 17:05:47'),
(356, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'cXdhK1gfCqCOu,Ekn23Cu23WyQ,GAiW9ea,Yy733nNXz0ApL', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-04 17:12:53'),
(357, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'xI0rDhySWHiF9Ferx2GBLQYoJJV8Ah-EnC1jdrmW,E1CeP,x', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-04 17:12:59'),
(358, 4, 0, 0, 4, 1, 'ادارة التشغيل', 'xI0rDhySWHiF9Ferx2GBLQYoJJV8Ah-EnC1jdrmW,E1CeP,x', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-04 19:05:43'),
(359, 4, 0, 0, 4, 1, 'ادارة التشغيل', '0pupET20HNleDF6z8z0-4bFv7QDCNG58DbXqY,GtasL,JnIz', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"محمد\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-04 19:11:08'),
(360, 4, 0, 0, 4, 1, 'ادارة التشغيل', '0pupET20HNleDF6z8z0-4bFv7QDCNG58DbXqY,GtasL,JnIz', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-04 19:11:51'),
(361, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'FsRMQj0q1GkVsBJKJrTBrpHt-gA5eHzEMtcqwMxd24Ec4EZI', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"مبيعات\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-04 19:11:57'),
(362, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'FsRMQj0q1GkVsBJKJrTBrpHt-gA5eHzEMtcqwMxd24Ec4EZI', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-04 19:13:26'),
(363, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'kw5z,eZ16YrXi9jlSwVSkZpUeZbMamRza3VfhUVoN4fpyTH9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"حركة - الروسية\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-04 19:13:37'),
(364, 4, 4, 4, 11, 6, 'مدير حركة وتشغيل', 'kw5z,eZ16YrXi9jlSwVSkZpUeZbMamRza3VfhUVoN4fpyTH9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-04 19:16:35'),
(365, 4, 4, 4, 12, 5, 'مدير الموقع', 'aSH6Z5Jmz3rvzVWYhlrhGoTkgCN7cAA5LPFUHUSOwQVxmOGV', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"مدير موقع الروسية\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-04 19:17:06'),
(366, 4, 4, 4, 12, 5, 'مدير الموقع', 'aSH6Z5Jmz3rvzVWYhlrhGoTkgCN7cAA5LPFUHUSOwQVxmOGV', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"1\"}', 200, '2026-06-04 19:17:10'),
(367, 4, 4, 4, 12, 5, 'مدير الموقع', 'aSH6Z5Jmz3rvzVWYhlrhGoTkgCN7cAA5LPFUHUSOwQVxmOGV', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"3\"}', 200, '2026-06-04 19:17:10'),
(368, 4, 4, 4, 12, 5, 'مدير الموقع', 'aSH6Z5Jmz3rvzVWYhlrhGoTkgCN7cAA5LPFUHUSOwQVxmOGV', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'timesheet', 'timesheet', 'get_event_types', 'get_event_types', NULL, NULL, NULL, NULL, 'http://localhost/ems/Timesheet/timesheet.php?type=1', 'GET', '{\"action\":\"get_event_types\",\"equipment_type\":\"1\"}', 200, '2026-06-04 19:17:10'),
(369, 4, 4, 4, 12, 5, 'مدير الموقع', 'aSH6Z5Jmz3rvzVWYhlrhGoTkgCN7cAA5LPFUHUSOwQVxmOGV', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'logout', 'auth', 'logout', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/logout.php', 'GET', NULL, 200, '2026-06-04 19:17:35'),
(370, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'AITT19GPl8,plMZqSqO4qsQg60I1bRIJHU0LFVqzkH8VKLt2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php', 'POST', '{\"username\":\"مبيعات\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-04 19:17:46'),
(371, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'AITT19GPl8,plMZqSqO4qsQg60I1bRIJHU0LFVqzkH8VKLt2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'clients', 'clients', 'export', 'export', NULL, NULL, NULL, NULL, 'http://localhost/ems/Clients/clients.php', 'GET', '{\"entity\":\"clients\",\"action\":\"export\"}', 200, '2026-06-04 19:19:09'),
(372, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'AITT19GPl8,plMZqSqO4qsQg60I1bRIJHU0LFVqzkH8VKLt2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'clients', 'clients', 'import_preview', 'import_preview', NULL, NULL, NULL, NULL, 'http://localhost/ems/Clients/clients.php', 'POST', '{\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-04 19:20:04'),
(373, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'AITT19GPl8,plMZqSqO4qsQg60I1bRIJHU0LFVqzkH8VKLt2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'clients', 'clients', 'template', 'template', NULL, NULL, NULL, NULL, 'http://localhost/ems/Clients/clients.php', 'GET', '{\"entity\":\"clients\",\"action\":\"template\"}', 200, '2026-06-04 19:20:18'),
(374, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'AITT19GPl8,plMZqSqO4qsQg60I1bRIJHU0LFVqzkH8VKLt2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'clients', 'clients', 'import_preview', 'import_preview', NULL, NULL, NULL, NULL, 'http://localhost/ems/Clients/clients.php', 'POST', '{\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-04 19:21:03'),
(375, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'aTmL9OS61jfylVSVfJC,PCObEnGZmDOiSpWJsZ0MtelCthMB', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 'auth', 'login', NULL, NULL, NULL, NULL, NULL, 'http://localhost/ems/login.php?timeout=1', 'POST', '{\"username\":\"مبيعات\",\"password\":\"[REDACTED]\",\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-06 09:04:53'),
(376, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'aTmL9OS61jfylVSVfJC,PCObEnGZmDOiSpWJsZ0MtelCthMB', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'projects', 'projects', 'import_preview', 'import_preview', NULL, NULL, NULL, NULL, 'http://localhost/ems/Projects/projects.php', 'POST', '{\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-06 09:05:08'),
(377, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'aTmL9OS61jfylVSVfJC,PCObEnGZmDOiSpWJsZ0MtelCthMB', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'projects', 'projects', 'import_preview', 'import_preview', NULL, NULL, NULL, NULL, 'http://localhost/ems/Projects/projects.php', 'POST', '{\"csrf_token\":\"[REDACTED]\"}', 200, '2026-06-06 09:05:42'),
(378, 4, 0, 0, 13, 12, 'ادارة المبيعات', 'aTmL9OS61jfylVSVfJC,PCObEnGZmDOiSpWJsZ0MtelCthMB', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'projects', 'projects', 'template', 'template', NULL, NULL, NULL, NULL, 'http://localhost/ems/Projects/projects.php', 'GET', '{\"entity\":\"projects\",\"action\":\"template\"}', 200, '2026-06-06 09:06:18');

-- --------------------------------------------------------

--
-- Table structure for table `admin_audit_log`
--

CREATE TABLE `admin_audit_log` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL COMMENT 'super_admins.id',
  `action_type` varchar(50) NOT NULL COMMENT 'create|update|delete|approve|reject|suspend|activate|login|logout',
  `target_name` varchar(200) DEFAULT NULL COMMENT 'human-readable target (company name, plan name, etc.)',
  `target_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(300) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_audit_log`
--

INSERT INTO `admin_audit_log` (`id`, `admin_id`, `action_type`, `target_name`, `target_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'update', 'شركة', 1, 'تحديث بيانات الشركة: ايكوبيشن مجاني', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-04-07 10:55:13'),
(2, 1, 'approve', 'طلب اشتراك', 1, 'قبول الطلب وإنشاء الشركة والمدير العام', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-04-07 10:57:22'),
(3, 1, 'suspend', 'شركة', 1, 'تعليق الشركة رقم #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-04-07 11:03:40'),
(4, 1, 'activate', 'شركة', 1, 'تفعيل الشركة رقم #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-04-07 11:04:26'),
(5, 1, 'update_password', 'شركة', 2, 'تحديث كلمة مرور مستخدم الشركة: #2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-04-07 11:05:06'),
(6, 1, 'delete', 'شركة', 2, 'حذف شركة رقم #2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-04-07 11:09:15'),
(7, 1, 'approve', 'طلب اشتراك', 2, 'قبول الطلب وإنشاء الشركة والمدير العام', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-04-07 11:10:58'),
(8, 1, 'approve', 'طلب اشتراك', 3, 'قبول الطلب وإنشاء الشركة والمدير العام', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-04-07 11:19:26'),
(9, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-04-07 11:36:12'),
(10, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-04-09 15:12:11'),
(11, 1, 'logout', 'جلسة الإدارة العليا', 1, 'تسجيل خروج يدوي من المستخدم', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-04-09 15:18:29'),
(12, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-04-09 15:18:31'),
(13, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-04-11 08:49:16'),
(14, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '156.193.245.166', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-04-18 10:28:53'),
(15, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '196.155.87.250', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-18 11:14:12'),
(16, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '196.155.87.250', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-18 13:48:35'),
(17, 1, 'logout', 'جلسة الإدارة العليا', 1, 'انتهت الجلسة بسبب عدم النشاط', '196.155.87.250', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-18 15:01:05'),
(18, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '196.155.87.250', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-18 15:01:10'),
(19, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '156.193.245.166', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-04-20 05:32:01'),
(20, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '196.155.87.250', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-04-20 18:54:40'),
(21, 1, 'logout', 'جلسة الإدارة العليا', 1, 'انتهت الجلسة بسبب عدم النشاط', '196.155.87.250', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-04-20 20:26:13'),
(22, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '196.155.87.250', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-04-20 20:26:21'),
(23, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '156.193.245.166', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-04-22 16:24:45'),
(24, 1, 'logout', 'جلسة الإدارة العليا', 1, 'انتهت الجلسة بسبب عدم النشاط', '156.193.245.166', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-04-22 17:36:55'),
(25, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '197.48.122.54', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-04-25 10:55:56'),
(26, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '156.193.243.194', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-25 11:05:49'),
(27, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '196.155.69.189', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-04-25 12:27:52'),
(28, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '156.193.243.194', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-04-26 11:40:12'),
(29, 1, 'logout', 'جلسة الإدارة العليا', 1, 'تسجيل خروج يدوي من المستخدم', '156.193.243.194', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-04-26 11:41:29'),
(30, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '41.43.131.168', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-04-26 15:12:17'),
(31, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '41.43.131.168', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-04-26 15:15:36'),
(32, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '41.238.31.33', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-26 20:07:48'),
(33, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '41.238.31.33', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-04-27 14:44:11'),
(34, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '41.36.69.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-04-27 19:23:14'),
(35, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '41.36.69.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-04-27 19:24:57'),
(36, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '41.36.69.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-04-27 19:26:12'),
(37, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '41.35.139.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-04-28 09:15:07'),
(38, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '41.35.139.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 09:18:36'),
(39, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '41.35.139.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-04-28 09:19:25'),
(40, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '41.35.139.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-04-28 13:43:18'),
(41, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '41.37.156.129', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-05-01 08:14:21'),
(42, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '41.37.156.129', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-01 11:12:13'),
(43, 1, 'logout', 'جلسة الإدارة العليا', 1, 'تسجيل خروج يدوي من المستخدم', '41.37.156.129', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-01 11:16:30'),
(44, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '41.37.156.129', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-05-01 11:18:47'),
(45, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '41.37.156.129', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-05-01 11:57:47'),
(46, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '41.37.156.129', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-01 12:09:19'),
(47, 1, 'logout', 'جلسة الإدارة العليا', 1, 'تسجيل خروج يدوي من المستخدم', '41.37.156.129', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-05-01 12:51:12'),
(48, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '197.48.140.79', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-05-02 12:27:03'),
(49, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '41.238.31.33', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-05-02 13:47:30'),
(50, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '197.48.140.79', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-05-05 07:12:04'),
(51, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '197.48.140.79', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-05-05 07:14:23'),
(52, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '197.48.140.79', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-05-06 10:40:44'),
(53, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-18 14:30:04'),
(54, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-23 15:48:14'),
(55, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-30 09:41:38'),
(56, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-01 11:29:41'),
(57, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-06-02 09:41:43');

-- --------------------------------------------------------

--
-- Table structure for table `admin_companies`
--

CREATE TABLE `admin_companies` (
  `id` int(11) NOT NULL COMMENT 'معرف فريد',
  `company_name` varchar(200) NOT NULL COMMENT 'اسم الشركة',
  `commercial_registration` varchar(120) DEFAULT NULL COMMENT 'السجل التجاري',
  `sector` varchar(100) DEFAULT NULL COMMENT 'القطاع',
  `country` varchar(100) DEFAULT NULL COMMENT 'البلد',
  `city` varchar(100) DEFAULT NULL COMMENT 'المدينة',
  `tax_number` varchar(120) DEFAULT NULL COMMENT 'الرقم الضريبي',
  `email` varchar(150) NOT NULL COMMENT 'البريد',
  `phone` varchar(30) DEFAULT NULL COMMENT 'رقم الهاتف',
  `address` text DEFAULT NULL COMMENT 'العنوان',
  `postal_address` text DEFAULT NULL COMMENT 'العنوان البريدي',
  `logo_path` varchar(255) DEFAULT NULL COMMENT 'الشعار',
  `plan_id` int(11) DEFAULT NULL COMMENT 'خطة الاشتراك',
  `modules_enabled` text DEFAULT NULL,
  `name` varchar(200) DEFAULT NULL COMMENT 'الاسم',
  `company_name_ar` varchar(200) DEFAULT NULL COMMENT 'اسم الشركة عربي',
  `company_name_en` varchar(200) DEFAULT NULL COMMENT 'اسم الشركة انحليزي',
  `status` enum('pending','active','suspended','cancelled') NOT NULL DEFAULT 'pending' COMMENT 'الحالة',
  `subscription_start` date DEFAULT NULL COMMENT 'بداية الاشتراك',
  `subscription_end` date DEFAULT NULL COMMENT 'نهاية الاشتراك',
  `users_count` int(11) NOT NULL DEFAULT 0 COMMENT 'عدد المستخدمين',
  `max_users` int(11) NOT NULL DEFAULT 0 COMMENT 'المستخدمين',
  `max_equipments` int(11) NOT NULL DEFAULT 0 COMMENT 'المعدات',
  `max_projects` int(11) NOT NULL DEFAULT 0 COMMENT 'المشاريع',
  `currency` varchar(20) NOT NULL DEFAULT 'SAR' COMMENT 'العملة',
  `timezone` varchar(64) NOT NULL DEFAULT 'Asia/Riyadh' COMMENT 'المنطقة الزمنية',
  `notes` text DEFAULT NULL COMMENT 'الملاحظات',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'الانشاء',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'التعديل'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_companies`
--

INSERT INTO `admin_companies` (`id`, `company_name`, `commercial_registration`, `sector`, `country`, `city`, `tax_number`, `email`, `phone`, `address`, `postal_address`, `logo_path`, `plan_id`, `modules_enabled`, `name`, `company_name_ar`, `company_name_en`, `status`, `subscription_start`, `subscription_end`, `users_count`, `max_users`, `max_equipments`, `max_projects`, `currency`, `timezone`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'ايكوبيشن مجاني', '17687687', 'تعدين', 'السودان', 'عطبرة', '5675867698', 'info@freeequipation.com', '+249915657576', '', 'عطبرة السوق الكبير', '', 1, '', 'ايكوبيشن مجاني', 'ايكوبيشن مجاني', 'Free Equipation', 'active', '2026-04-07', NULL, 1, 10, 50, 2, 'USD', 'Asia/Riyadh', NULL, '2026-04-07 10:53:47', '2026-04-07 11:04:26'),
(4, 'ايكوبيشن', '0989897987987', 'تعدين', 'السودان', 'عطبرة', '6576576576567', 'info@equipation.com', '+249915657576', 'عطبرة الطايق الاول', 'عطبرة الطايق الاول', NULL, 2, '', 'ايكوبيشن', 'ايكوبيشن', 'Equipation', 'active', NULL, NULL, 1, 0, 100, 10, 'USD', 'Asia/Riyadh', NULL, '2026-04-07 11:19:26', '2026-04-07 11:19:26');

-- --------------------------------------------------------

--
-- Table structure for table `admin_subscription_plans`
--

CREATE TABLE `admin_subscription_plans` (
  `id` int(11) NOT NULL,
  `plan_name` varchar(100) NOT NULL COMMENT 'اسم الخطة',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'السعر',
  `max_users` int(11) NOT NULL DEFAULT 0 COMMENT '0 = unlimited المستخدمين',
  `max_projects` int(11) NOT NULL DEFAULT 0 COMMENT 'المشاريع',
  `max_equipments` int(11) NOT NULL DEFAULT 0 COMMENT 'المعدات',
  `features` text DEFAULT NULL COMMENT 'المميزات',
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT 'الترتيب',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'نشط',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'الانشاء',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'التعديل'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_subscription_plans`
--

INSERT INTO `admin_subscription_plans` (`id`, `plan_name`, `price`, `max_users`, `max_projects`, `max_equipments`, `features`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'مجاني', 0.00, 10, 2, 50, '', 0, 1, '2026-04-07 10:25:23', '2026-04-07 10:31:06'),
(2, 'مدفوع', 10.00, 0, 10, 100, '', 1, 1, '2026-04-07 10:26:08', '2026-04-07 10:31:14');

-- --------------------------------------------------------

--
-- Table structure for table `admin_subscription_requests`
--

CREATE TABLE `admin_subscription_requests` (
  `id` int(11) NOT NULL COMMENT 'معرف فريد',
  `company_id` int(11) DEFAULT NULL COMMENT 'null if company not  created yet رقم الشركة',
  `company_name` varchar(200) NOT NULL COMMENT 'اسم الشركة',
  `email` varchar(150) NOT NULL COMMENT 'البريد',
  `phone` varchar(30) DEFAULT NULL COMMENT 'الهاتف',
  `plan_id` int(11) DEFAULT NULL COMMENT 'خطة الاشتراك',
  `message` text DEFAULT NULL COMMENT 'message from the requesting company جميع بيانات الشركة ',
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending' COMMENT 'الحالة',
  `reviewed_by` int(11) DEFAULT NULL COMMENT 'super_admins.id المراجع',
  `reviewed_at` timestamp NULL DEFAULT NULL COMMENT 'زمن المراجعه',
  `review_note` text DEFAULT NULL COMMENT 'الملاحظات',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'الانشاء'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_subscription_requests`
--

INSERT INTO `admin_subscription_requests` (`id`, `company_id`, `company_name`, `email`, `phone`, `plan_id`, `message`, `status`, `reviewed_by`, `reviewed_at`, `review_note`, `created_at`) VALUES
(3, 4, 'ايكوبيشن', 'info@equipation.com', '+249915657576', 2, '{\"company_name_en\":\"Equipation\",\"commercial_registration\":\"0989897987987\",\"sector\":\"تعدين\",\"country\":\"السودان\",\"city\":\"عطبرة\",\"tax_number\":\"6576576576567\",\"postal_address\":\"عطبرة الطايق الاول\",\"modules_enabled\":\"\",\"currency\":\"USD\",\"timezone\":\"Asia\\/Riyadh\",\"max_users\":0,\"max_equipments\":100,\"max_projects\":10,\"manager_name\":\"مستر محمد ادريس\",\"manager_email\":\"admin@gmail.com\",\"manager_phone\":\"+249915657576\",\"manager_password_hash\":\"$2y$10$8FcRlrkxuIOUr6kWAwy6Z.lh1rYmAzAA\\/8zSH7sxhgPAc69eQNLTG\",\"source\":\"company_register\"}', 'approved', 1, '2026-04-07 11:19:26', 'تم الدفع', '2026-04-07 11:19:08');

-- --------------------------------------------------------

--
-- Table structure for table `admin_subscription_requests_test_probe`
--

CREATE TABLE `admin_subscription_requests_test_probe` (
  `id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `company_name` varchar(200) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `plan_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(1, 'timesheet', 1, 'approve', '{\"summary\":{\"table\":\"timesheet\",\"operation\":\"update\",\"old_values\":{\"id\":\"1\",\"status\":\"1\",\"time_notes\":\"لاتوجد ملاحظات\"},\"new_values\":{\"status\":2,\"time_notes\":\"تم\"}},\"operations\":[{\"db_action\":\"update\",\"table\":\"timesheet\",\"where\":{\"id\":1},\"data\":{\"status\":2,\"time_notes\":\"تم\"}}]}', 8, 1, 'pending', NULL, NULL, NULL, NULL, '2026-04-07 15:11:42', '2026-04-07 15:11:42'),
(2, 'project', 1, 'update', '{\"summary\":{\"table\":\"project\",\"operation\":\"update\",\"old_values\":{\"id\":\"1\",\"company_id\":\"4\",\"client_id\":\"1\",\"name\":\"مشروع الروسية\",\"client\":\"شركة بايناتس\",\"location\":\"شمال غرب المناقل\",\"project_code\":\"PR1\",\"category\":\"فئة المشروع\",\"sub_sector\":\"فرعي\",\"state\":\"نهر النيل\",\"region\":\"حفر الباطن\",\"nearest_market\":\"سوق اللفة\",\"latitude\":\"12\",\"longitude\":\"31\",\"total\":\"0\",\"status\":\"1\",\"created_by\":\"4\",\"create_at\":\"2026-04-07 11:46:05\",\"updated_at\":null,\"is_deleted\":\"0\",\"deleted_at\":null,\"deleted_by\":null},\"new_values\":{\"client_id\":1,\"name\":\"مشروع الروسية\",\"client\":\"شركة إليانس للتعدين المحدودة\",\"location\":\"وادي العشار\",\"project_code\":\"PR1\",\"category\":\"مناجم\",\"sub_sector\":\"المنجم الأول\",\"state\":\"البحر الأحمر\",\"region\":\"وادي العشار\",\"nearest_market\":\"سوق وادي العشار\",\"latitude\":\"000\",\"longitude\":\"31\",\"total\":0,\"status\":\"1\",\"updated_at\":\"2026-04-25 09:59:54\",\"company_id\":4}},\"operations\":[{\"db_action\":\"update\",\"table\":\"project\",\"where\":{\"id\":1},\"data\":{\"client_id\":1,\"name\":\"مشروع الروسية\",\"client\":\"شركة إليانس للتعدين المحدودة\",\"location\":\"وادي العشار\",\"project_code\":\"PR1\",\"category\":\"مناجم\",\"sub_sector\":\"المنجم الأول\",\"state\":\"البحر الأحمر\",\"region\":\"وادي العشار\",\"nearest_market\":\"سوق وادي العشار\",\"latitude\":\"000\",\"longitude\":\"31\",\"total\":0,\"status\":\"1\",\"updated_at\":\"2026-04-25 09:59:54\",\"company_id\":4}}]}', 4, 1, 'pending', NULL, NULL, NULL, NULL, '2026-04-25 09:59:54', '2026-04-25 09:59:54'),
(3, 'project', 2, 'update', '{\"summary\":{\"table\":\"project\",\"operation\":\"update\",\"old_values\":{\"id\":\"2\",\"company_id\":\"4\",\"client_id\":\"1\",\"name\":\"مشروع اليانس\",\"client\":\"شركة بايناتس\",\"location\":\"موقع اليانس\",\"project_code\":\"PR2\",\"category\":\"تعدين\",\"sub_sector\":\"تعدين\",\"state\":\"نهر النيل\",\"region\":\"حفر الباطن\",\"nearest_market\":\"سوق اللفة\",\"latitude\":\"12\",\"longitude\":\"31\",\"total\":\"0\",\"status\":\"1\",\"created_by\":\"4\",\"create_at\":\"2026-04-13 11:22:49\",\"updated_at\":null,\"is_deleted\":\"0\",\"deleted_at\":null,\"deleted_by\":null},\"new_values\":{\"client_id\":1,\"name\":\"مشروع اليانس\",\"client\":\"شركة إليانس للتعدين المحدودة\",\"location\":\"عطبره\",\"project_code\":\"PR2\",\"category\":\"تعدين\",\"sub_sector\":\"تعدين\",\"state\":\"نهر النيل\",\"region\":\"حفر الباطن\",\"nearest_market\":\"سوق اللفة\",\"latitude\":\"12\",\"longitude\":\"31\",\"total\":0,\"status\":\"1\",\"updated_at\":\"2026-04-25 11:36:16\",\"company_id\":4}},\"operations\":[{\"db_action\":\"update\",\"table\":\"project\",\"where\":{\"id\":2},\"data\":{\"client_id\":1,\"name\":\"مشروع اليانس\",\"client\":\"شركة إليانس للتعدين المحدودة\",\"location\":\"عطبره\",\"project_code\":\"PR2\",\"category\":\"تعدين\",\"sub_sector\":\"تعدين\",\"state\":\"نهر النيل\",\"region\":\"حفر الباطن\",\"nearest_market\":\"سوق اللفة\",\"latitude\":\"12\",\"longitude\":\"31\",\"total\":0,\"status\":\"1\",\"updated_at\":\"2026-04-25 11:36:16\",\"company_id\":4}}]}', 4, 1, 'pending', NULL, NULL, NULL, NULL, '2026-04-25 11:36:16', '2026-04-25 11:36:16'),
(4, 'contract', 4, 'renewal', '{\"summary\":{\"old_values\":{\"actual_start\":\"2025-07-01\",\"actual_end\":\"2026-07-01\",\"status\":\"1\"},\"new_values\":{\"actual_start\":\"2026-07-01\",\"actual_end\":\"2027-01-15\",\"contract_duration_months\":6,\"contract_duration_days\":198,\"status\":1,\"updated_at\":\"2026-05-11 01:27:48\"}},\"operations\":[{\"db_action\":\"update\",\"table\":\"contracts\",\"where\":{\"id\":4},\"data\":{\"actual_start\":\"2026-07-01\",\"actual_end\":\"2027-01-15\",\"contract_duration_months\":6,\"contract_duration_days\":198,\"status\":1,\"updated_at\":\"2026-05-11 01:27:48\"}},{\"db_action\":\"insert\",\"table\":\"contract_notes\",\"data\":{\"contract_id\":4,\"note\":\"تم تجديد العقد من 2026-07-01 إلى 2027-01-15 (مدة: 6 شهور \\/ 198 يوم)\",\"user_id\":13,\"created_at\":\"2026-05-11 01:27:48\"}}]}', 13, 1, 'pending', NULL, NULL, NULL, NULL, '2026-05-11 02:27:48', '2026-05-11 02:27:48'),
(5, 'contract', 2, 'renewal', '{\"summary\":{\"old_values\":{\"actual_start\":\"2025-10-01\",\"actual_end\":\"2026-10-01\",\"status\":\"1\"},\"new_values\":{\"actual_start\":\"2026-10-01\",\"actual_end\":\"2027-03-12\",\"contract_duration_months\":5,\"contract_duration_days\":162,\"status\":1,\"updated_at\":\"2026-05-11 02:08:35\"}},\"operations\":[{\"db_action\":\"update\",\"table\":\"contracts\",\"where\":{\"id\":2},\"data\":{\"actual_start\":\"2026-10-01\",\"actual_end\":\"2027-03-12\",\"contract_duration_months\":5,\"contract_duration_days\":162,\"status\":1,\"updated_at\":\"2026-05-11 02:08:35\"}},{\"db_action\":\"insert\",\"table\":\"contract_notes\",\"data\":{\"contract_id\":2,\"note\":\"تم تجديد العقد من 2026-10-01 إلى 2027-03-12 (مدة: 5 شهور \\/ 162 يوم)\",\"user_id\":13,\"created_at\":\"2026-05-11 02:08:35\"}}]}', 13, 1, 'pending', NULL, NULL, NULL, NULL, '2026-05-11 03:08:35', '2026-05-11 03:08:35');

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
(1, 1, '-1', 1, NULL, NULL, 'pending', NULL, '2026-04-07 15:11:42'),
(2, 2, '-1', 1, NULL, NULL, 'pending', NULL, '2026-04-25 09:59:54'),
(3, 3, '-1', 1, NULL, NULL, 'pending', NULL, '2026-04-25 11:36:16'),
(4, 4, '-1', 1, NULL, NULL, 'pending', NULL, '2026-05-11 02:27:48'),
(5, 5, '-1', 1, NULL, NULL, 'pending', NULL, '2026-05-11 03:08:35');

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

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL,
  `action_type` varchar(80) NOT NULL,
  `target_name` varchar(200) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(300) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `company_id`, `action_type`, `target_name`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 0, 1, 'login', 'بوابة الشركة', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-07 10:53:47'),
(2, 1, 1, 'login', 'بوابة الشركة', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-07 10:54:04'),
(3, 1, 1, 'logout', 'بوابة الشركة', 'تسجيل خروج يدوي', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-07 10:54:10'),
(4, 4, 4, 'login', 'بوابة الشركة', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-04-07 11:24:49'),
(5, 4, 4, 'login', 'بوابة الشركة', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-04-07 11:33:56'),
(6, 4, 4, 'login', 'بوابة الشركة', 'تسجيل دخول ناجح', '2001:1a10:18f1:8e00:ad8e:a6d2:3dec:38d1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-30 06:47:38'),
(7, 6, 4, 'login', 'بوابة الشركة', 'تسجيل دخول ناجح', '212.70.114.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-01 07:18:33');

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'تاريخ آخر تحديث',
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول العملاء';

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `company_id`, `client_code`, `client_name`, `entity_type`, `sector_category`, `phone`, `email`, `whatsapp`, `status`, `created_by`, `created_at`, `updated_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES
(1, 4, 'C001', 'شركة إليانس للتعدين المحدودة', 'دولي', 'تعدين', '249912345678', 'sudan@gmail.com', '249912345678', 'نشط', 4, '2026-04-07 11:45:22', '2026-04-25 09:57:42', 0, NULL, NULL),
(2, 4, '58', 'شركة محمد', 'دولي', 'تعدين', '249912345678', 'sudan@gmail.com', '249912345678', 'نشط', 4, '2026-04-26 08:30:27', '2026-05-19 17:31:39', 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `company_user_password_resets`
--

CREATE TABLE `company_user_password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contractequipments`
--

CREATE TABLE `contractequipments` (
  `id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
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

INSERT INTO `contractequipments` (`id`, `company_id`, `contract_id`, `equip_type`, `equip_size`, `equip_count`, `equip_count_basic`, `equip_count_backup`, `equip_shifts`, `equip_unit`, `shift1_start`, `shift1_end`, `shift2_start`, `shift2_end`, `shift_hours`, `equip_total_month`, `equip_monthly_target`, `equip_total_contract`, `equip_price`, `equip_operators`, `equip_supervisors`, `equip_technicians`, `equip_assistants`, `equip_price_currency`, `created_at`) VALUES
(1, NULL, 1, '1', 100, 5, 3, 2, 2, 'ساعة', '13:51:00', '13:51:00', '13:51:00', '13:52:00', 10, 50, 6000, 1000, 10.00, 3, 3, 3, 3, '', '2026-04-07 11:52:10'),
(2, NULL, 1, '2', 300, 5, 3, 2, 3, 'ساعة', '13:52:00', '13:53:00', '13:54:00', '13:55:00', 10, 50, 6000, 1000, 10.00, 3, 3, 3, 3, 'دولار', '2026-04-07 11:52:10'),
(8, NULL, 2, '1', 28, 5, 0, 0, 2, 'متر طولي', '00:57:00', '02:14:00', '15:30:00', '22:05:00', 20, 100, 600, 36600, 40.00, 88, 2, 2, 48, 'دولار', '2026-04-20 09:42:26'),
(23, NULL, 5, '1', 340, 6, 6, 0, 2, 'ساعة', '00:00:00', '12:00:00', '12:00:00', '00:00:00', 10, 60, 300, 11100, 20.00, 15, 1, 3, 6, 'دولار', '2026-04-27 10:59:44'),
(24, NULL, 5, '2', 25, 24, 24, 0, 2, 'ساعة', '00:00:00', '12:00:00', '12:00:00', '00:00:00', 10, 240, 300, 44400, 8.00, 30, 1, 10, 10, 'دولار', '2026-04-27 10:59:44'),
(25, NULL, 4, '1', 340, 6, 6, 4, 2, 'ساعة', '18:00:00', '04:00:00', '06:00:00', '16:00:00', 10, 100, 300, 36600, 20.00, 15, 0, 0, 2, 'دولار', '2026-04-28 10:31:11'),
(26, NULL, 4, '2', 35, 10, 10, 0, 2, 'ساعة', '18:00:00', '04:00:00', '18:00:00', '04:00:00', 10, 100, 300, 36600, 8.00, 56, 3, 6, 8, '', '2026-04-28 10:31:11');

-- --------------------------------------------------------

--
-- Table structure for table `contracts`
--

CREATE TABLE `contracts` (
  `id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
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
  `status` tinyint(1) DEFAULT 1 COMMENT '1=نشط, 0=موقوف',
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `contracts`
--

INSERT INTO `contracts` (`id`, `company_id`, `contract_signing_date`, `grace_period_days`, `contract_duration_months`, `contract_duration_days`, `equip_shifts_contract`, `shift_contract`, `equip_total_contract_daily`, `total_contract_permonth`, `total_contract_units`, `actual_start`, `actual_end`, `transportation`, `accommodation`, `place_for_living`, `workshop`, `hours_monthly_target`, `forecasted_contracted_hours`, `created_at`, `updated_at`, `daily_work_hours`, `daily_operators`, `first_party`, `second_party`, `witness_one`, `witness_two`, `price_currency_contract`, `paid_contract`, `payment_time`, `guarantees`, `payment_date`, `contract_status`, `pause_reason`, `pause_date`, `resume_date`, `termination_type`, `termination_reason`, `merged_with`, `status`, `is_deleted`, `deleted_at`, `deleted_by`, `project_id`) VALUES
(1, 4, '2026-04-01', 10, 0, 21, 2, 10, 40, 6000, 6000, '2026-04-10', '2026-04-30', 'مالك المعدة', 'مالك المعدة', 'مالك المعدة', 'مالك المعدة', 100, 2000, '2026-04-07 11:52:10', '2026-05-14 15:09:35', '20', '3', 'محمد سيد', 'مبارك عوض', 'سمير الليل', 'مبارك محمود', 'دولار', '1000', 'مقدم', 'رهن سيارة', '2026-04-20', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, 1),
(2, 4, '2025-10-01', 10, 0, 366, 2, 10, 100, 600, 2, '2025-10-01', '2026-10-01', 'بدون', 'مالك المشروع', 'مالك المشروع', 'بدون', 600, 36600, '2026-04-13 12:04:08', '2026-05-14 15:09:35', '20', '2', 'Voluptatem et tempor', 'Consequatur eveniet', 'Reiciendis voluptas ', 'Nisi sint sit alias ', 'جنيه', 'Qui et aspernatur qu', ' مؤخر', 'Odit quis repellendu', '2011-10-28', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, 2),
(3, 4, '2026-04-24', 5, 0, 365, 2, 10, 20, 600, 7000, '2026-05-01', '2027-04-30', '', '', '', '', 0, 0, '2026-04-25 11:57:29', '2026-05-14 15:09:35', '20', '', '', '', '', '', 'دولار', '100000', 'مقدم', 'تأمين المشروع', '2026-04-27', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, 3),
(4, 4, '2025-07-01', 0, 7, 233, 2, 10, 20, 600, 216000, '2026-07-01', '2027-02-19', 'مالك المعدة', 'مالك المشروع', 'مالك المشروع', 'مالك المعدة', 600, 73200, '2026-04-26 09:45:07', '2026-05-14 15:09:35', '20', '1', 'شركة إكوبيشن للإستثمار المحدودة', 'شركة إليانس لتعدين الذهب المحدودة', 'محمد فيصل محمد صابر', 'يس سيدأحمد محمدالأمين الحسن', 'دولار', '', '', '', '0000-00-00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, 4),
(5, 4, '2026-04-01', 30, 0, 185, 2, 12, 24, 3000, 43000, '2026-05-01', '2026-11-01', 'مالك المعدة', 'مالك المشروع', 'مالك المشروع', 'مالك المعدة', 600, 55500, '2026-04-27 09:52:44', '2026-05-14 15:09:35', '20', '3', 'شركة إكوبيشن للإستثمار المحدودة', 'شركة إليانس لتعدين الذهب المحدودة', 'محمد فيصل محمد صابر', 'يس سيدأحمد محمدالأمين الحسن', 'دولار', '150000', 'مقدم', 'تأمين المشروع', '2026-04-15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, 4);

-- --------------------------------------------------------

--
-- Table structure for table `contract_notes`
--

CREATE TABLE `contract_notes` (
  `id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `contract_id` int(11) NOT NULL,
  `note` text NOT NULL,
  `user_id` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `contract_notes`
--

INSERT INTO `contract_notes` (`id`, `company_id`, `contract_id`, `note`, `user_id`, `created_at`, `created_by`) VALUES
(1, NULL, 4, 'تم تجديد العقد من 2026-07-01 إلى 2027-02-19 (مدة: 7 شهور / 233 يوم)', 13, '2026-05-11 00:19:30', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `drivercontractequipments`
--

CREATE TABLE `drivercontractequipments` (
  `id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
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
  `company_id` int(11) DEFAULT NULL,
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
  `project_contract_id` int(11) DEFAULT NULL COMMENT 'معرف عقد المشروع',
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--

CREATE TABLE `drivers` (
  `id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `driver_code` varchar(50) DEFAULT NULL COMMENT 'الرمز/الكود الفريد للمشغل',
  `nickname` varchar(255) DEFAULT NULL COMMENT 'اسم الشهرة/الكنية',
  `identity_type` varchar(50) DEFAULT NULL COMMENT 'نوع الهوية',
  `identity_number` varchar(100) DEFAULT NULL COMMENT 'رقم الهوية',
  `identity_expiry_date` date DEFAULT NULL COMMENT 'تاريخ انتهاء الهوية',
  `driver_photo` varchar(255) DEFAULT NULL,
  `identity_photo` varchar(255) DEFAULT NULL,
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

INSERT INTO `drivers` (`id`, `company_id`, `project_id`, `name`, `driver_code`, `nickname`, `identity_type`, `identity_number`, `identity_expiry_date`, `driver_photo`, `identity_photo`, `license_number`, `license_type`, `license_expiry_date`, `license_issuer`, `specialized_equipment`, `years_in_field`, `years_on_equipment`, `skill_level`, `certificates`, `owner_supervisor`, `supplier_id`, `employment_affiliation`, `salary_type`, `monthly_salary`, `email`, `address`, `performance_rating`, `behavior_record`, `accident_record`, `health_status`, `health_issues`, `vaccinations_status`, `previous_employer`, `employment_duration`, `reference_contact`, `general_notes`, `driver_status`, `start_date`, `created_at`, `phone`, `phone_alternative`, `status`) VALUES
(1, 4, 5, 'محمد سيد', 'DR1', 'Linda Workman', 'بطاقة هوية وطنية', '939', '1976-08-07', '', '', '661', 'فئة د (شاحنات ثقيلة)', '2007-11-30', 'Saepe nisi hic et se', 'حفارة (Excavator), دوزر (Dozer), شاحنة تناكر/صهريج (Tanker Truck), معدات أخرى', 4, 3, 'سيد حرفة (أكثر من 10 سنوات)', 'Dolorem ad maxime qu', 'Duis non autem paria', 1, 'تابع للمورد/الوسيط', 'يومي', 12.00, 'hefade@mailinator.com', 'Rerum dignissimos mo', 'جيد جداً', 'مقبول (بعض الشكاوى)', 'غير محدد', 'محتاج متابعة طبية', 'Mollitia corporis oc', 'قديمة', 'Non optio est et ma', 'Quisquam laudantium', 'Dolorem et laboriosa', 'Facere aliquid obcae', 'نشط', '1994-05-07', '2026-04-07 12:39:24', '+1 (165) 293-2817', '+1 (433) 539-7514', 1),
(2, 4, 4, 'حسن سيد حسن', 'DR2', 'Halee Hancock', 'رخصة قيادة', '590', '1985-01-22', NULL, NULL, '79', 'فئة د (شاحنات ثقيلة)', '2002-05-10', 'Sed id delectus mol', 'حفارة (Excavator), دوزر (Dozer), شاحنة قلابة (Dump Truck), جرافة (Loader), ممهدة (Grader)', 3, 2, 'خبير (5-10 سنوات)', 'Cumque consequatur', 'Fugit anim minus id', 1, 'تابع لشركة متخصصة في التشغيل', 'حسب المشروع', 8.00, 'zusatipyx@mailinator.com', 'Ad eveniet aut volu', 'ممتاز', 'غير محدد', 'غير محدد', 'سليم تماماً', 'Pariatur A est eius', 'محدثة', 'Eos similique ea sus', 'Ea quis in excepturi', 'Qui ut voluptas poss', 'Aut laborum Ut sequ', 'نشط', '1990-05-29', '2026-04-07 12:40:06', '+1 (743) 975-1092', '+1 (834) 162-7231', 1),
(3, 4, 4, 'ابو بكر محمود', 'DR21', 'بكري', 'رخصة قيادة', '405', '1974-12-22', NULL, NULL, '135', 'فئة ج (شاحنات خفيفة)', '1981-02-24', 'Error accusamus fugi', 'حفارة (Excavator), مثقاب/مكنة تخريم (Drill Machine), دوزر (Dozer), شاحنة تناكر/صهريج (Tanker Truck), معدات أخرى', 2, 1, 'مبتدئ (أقل من سنة)', 'Est est doloremque d', 'Voluptas doloribus v', 3, 'تابع لمالك المعدة مباشرة', 'أسبوعي', 12.00, 'xytemyke@mailinator.com', 'Quae aliquip aut eum', 'مقبول', 'غير محدد', 'نظيف (لا توجد حوادث)', 'غير محدد', 'Occaecat et dolor ir', 'محدثة', 'Nihil molestias nesc', 'Similique voluptatum', 'Voluptatem sint est', 'Delectus repellendu', 'نشط', '1994-05-11', '2026-04-13 12:21:46', '+1 (285) 112-7987', '+1 (304) 799-2794', 1),
(4, 4, 4, 'احمد الريح', 'DR22', 'حمادة', 'بطاقة أخرى', '601', '2017-04-20', NULL, NULL, '578', 'فئة ج (شاحنات خفيفة)', '2021-11-25', 'Dolores nihil numqua', 'شاحنة قلابة (Dump Truck), ممهدة (Grader)', 5, 1, 'كفء (3-5 سنوات)', 'Non irure tempora el', 'Aut adipisicing numq', 3, 'تابع لشركة متخصصة في التشغيل', 'شهري', 6.00, 'guben@mailinator.com', 'Qui ut ut sequi simi', 'مقبول', 'مقبول (بعض الشكاوى)', 'نظيف (لا توجد حوادث)', 'غير محدد', 'Iusto numquam mollit', 'قديمة', 'Quia minima atque ev', 'Rerum cum voluptates', 'Cumque voluptate dol', 'Est debitis fugit e', 'نشط', '1970-07-13', '2026-04-13 12:23:21', '+1 (361) 805-8787', '+1 (381) 487-3832', 1),
(5, 4, 4, 'ايوب عبد الواحد', 'DR23', 'ايوب', 'بطاقة أخرى', '150', '1987-03-24', NULL, NULL, '993', 'فئة ج (شاحنات خفيفة)', '2000-06-04', 'Est officia laboris', 'مثقاب/مكنة تخريم (Drill Machine), شاحنة تناكر/صهريج (Tanker Truck), معدات أخرى', 5, 1, 'كفء (3-5 سنوات)', 'Fugit qui veniam e', 'Sed quaerat pariatur', 3, 'تابع للمورد/الوسيط', 'شهري', 1.00, 'dyzy@mailinator.com', 'Inventore vero aut a', 'ممتاز', 'ضعيف (شكاوى متكررة)', 'غير محدد', 'محتاج متابعة طبية', 'Aut sed veniam ipsu', 'قيد الفحص', 'Rerum dolorem conseq', 'Dolor quasi assumend', 'Nostrud quia eiusmod', 'Qui voluptate quae s', 'نشط', '1983-01-09', '2026-04-13 12:25:09', '+1 (349) 884-9487', '+1 (781) 369-9738', 1),
(6, 4, 4, 'بشير عبد الله', 'DR24', 'Jin Petersen', 'بطاقة لاجئ', '245', '2007-12-25', NULL, NULL, '563', 'فئة ب (سيارات خصوصية)', '1989-04-14', 'Voluptas saepe recus', 'شاحنة تناكر/صهريج (Tanker Truck), جرافة (Loader), معدات أخرى', 5, 3, 'كفء (3-5 سنوات)', 'Sint ipsa voluptate', 'Omnis sed sint et di', 3, 'تابع لشركة متخصصة في التشغيل', 'أسبوعي', 9.00, 'jupe@mailinator.com', 'Cum possimus sunt a', 'ممتاز', 'مقبول (بعض الشكاوى)', 'حادث واحد (طفيف)', 'محتاج متابعة طبية', 'Aspernatur culpa eli', 'قيد الفحص', 'Aute ut similique ad', 'Ea est asperiores vo', 'Ut deserunt error du', 'Repellendus Suscipi', 'نشط', '1978-05-02', '2026-04-13 12:26:27', '+1 (696) 738-8861', '+1 (274) 962-9936', 1),
(7, 4, 4, 'تاي الله', 'DR25', 'Barbara Hill', 'جواز سفر', '919', '1978-03-08', NULL, NULL, '286', 'فئة د (شاحنات ثقيلة)', '1978-05-27', 'Optio quia consequa', 'شاحنة قلابة (Dump Truck)', 5, 2, 'مبتدئ (أقل من سنة)', 'Culpa mollit earum e', 'Quis ex delectus di', 3, 'تابع لشركة متخصصة في التشغيل', 'حسب المشروع', 10.00, 'feko@mailinator.com', 'Incididunt dolorum s', 'مقبول', 'ضعيف (شكاوى متكررة)', 'نظيف (لا توجد حوادث)', 'غير محدد', 'Maiores facere est i', 'قديمة', 'Quia do laborum Eni', 'Do maxime nisi volup', 'Neque sit dolor sun', 'Dolor rem et harum r', 'نشط', '1973-07-05', '2026-04-13 12:27:40', '+1 (801) 524-8878', '+1 (736) 791-9272', 1),
(8, 4, 4, 'حسين موسى', 'DR26', 'Kiayada Nielsen', 'جواز سفر', '992', '2025-06-01', NULL, NULL, '339', 'فئة أ (دراجات نارية)', '2010-11-17', 'Laborum Laborum vol', 'مثقاب/مكنة تخريم (Drill Machine), شاحنة قلابة (Dump Truck), شاحنة تناكر/صهريج (Tanker Truck)', 5, 1, 'سيد حرفة (أكثر من 10 سنوات)', 'Nostrud irure elit', 'Distinctio Dicta ni', 3, 'تابع لشركة متخصصة في التشغيل', 'شهري', 11.00, 'fujetike@mailinator.com', 'Obcaecati ea dolore', 'جيد جداً', 'ضعيف (شكاوى متكررة)', 'نظيف (لا توجد حوادث)', 'محتاج متابعة طبية', 'Reprehenderit duis', 'لا يوجد فحص', 'Laudantium dolorem', 'Cillum ea hic rerum', 'Eaque est quas lauda', 'Voluptas enim et quo', 'نشط', '2012-07-11', '2026-04-13 12:49:18', '+1 (951) 455-3516', '+1 (978) 573-6838', 1),
(9, 4, 4, 'سعيد محمد', 'DR27', 'Moses Chan', 'بطاقة هوية وطنية', '222', '1982-06-25', NULL, NULL, '853', 'فئة د (شاحنات ثقيلة)', '2009-10-06', 'Quidem eos repudiand', 'حفارة (Excavator), شاحنة تناكر/صهريج (Tanker Truck), جرافة (Loader), ممهدة (Grader)', 5, 1, 'كفء (3-5 سنوات)', 'Ea harum facere quid', 'Est dolorum delectu', 3, 'تابع لشركة متخصصة في التشغيل', 'شهري', 2.00, 'misirenir@mailinator.com', 'Obcaecati exercitati', 'مقبول', 'مقبول (بعض الشكاوى)', 'ثلاثة حوادث فأكثر (خطير)', 'بحالة مقبولة', 'Voluptatem enim mini', 'محدثة', 'Repudiandae sint aut', 'Fuga Reiciendis eni', 'Nisi nostrum cumque', 'Eos fugiat aliquam n', 'نشط', '2002-10-21', '2026-04-13 12:50:46', '+1 (438) 926-8717', '+1 (592) 116-7516', 1),
(10, 4, 4, 'سليمان علي', 'DR28', 'Ray Lara', 'جواز سفر', '808', '1999-08-17', NULL, NULL, '266', 'فئة د (شاحنات ثقيلة)', '1996-09-28', 'Modi exercitationem', 'مثقاب/مكنة تخريم (Drill Machine), دوزر (Dozer), شاحنة قلابة (Dump Truck), جرافة (Loader), معدات أخرى', 5, 1, 'متدرب (1-2 سنة)', 'Est saepe est simili', 'Id adipisicing volup', 3, 'تابع لمالك المعدة مباشرة', 'أسبوعي', 3.00, 'giwomag@mailinator.com', 'Nostrum cum consequu', 'جيد', 'ضعيف (شكاوى متكررة)', 'نظيف (لا توجد حوادث)', 'بحالة مقبولة', 'Facilis occaecat ali', 'محدثة', 'Dolor id hic impedit', 'Aliquam reprehenderi', 'Possimus enim proid', 'Earum alias alias eu', 'نشط', '1992-09-14', '2026-04-13 12:53:00', '+1 (329) 526-6034', '+1 (861) 471-6545', 1),
(11, 4, 4, 'عبد الغني الدود', 'DR29', 'Lucy Shaffer', 'بطاقة لاجئ', '230', '2021-04-19', NULL, NULL, '290', 'غير محدد', '2018-04-20', 'Eum dolor beatae ver', 'دوزر (Dozer), شاحنة قلابة (Dump Truck), شاحنة تناكر/صهريج (Tanker Truck), معدات أخرى', 5, 1, 'سيد حرفة (أكثر من 10 سنوات)', 'Corporis voluptas co', 'Doloribus dolor sed', 3, 'تابع للمورد/الوسيط', 'شهري', 9.00, 'tyvexo@mailinator.com', 'Consectetur qui sol', 'جيد', 'ضعيف (شكاوى متكررة)', 'غير محدد', 'بحالة جيدة', 'Distinctio Officia', 'لا يوجد فحص', 'Iste error temporibu', 'Praesentium sed earu', 'Eius praesentium lib', 'Optio quia ut quibu', 'نشط', '1971-01-05', '2026-04-13 12:55:12', '+1 (245) 136-4421', '+1 (511) 457-6755', 1),
(12, 4, 4, 'عبد القادر يحي', 'DR30', 'Callie Morris', 'بطاقة لاجئ', '861', '2008-09-12', NULL, NULL, '595', 'فئة د (شاحنات ثقيلة)', '1972-09-05', 'Eveniet tempore di', 'مثقاب/مكنة تخريم (Drill Machine), دوزر (Dozer), شاحنة قلابة (Dump Truck), ممهدة (Grader), معدات أخرى', 5, 1, 'خبير (5-10 سنوات)', 'Eaque sed dicta et d', 'Nemo enim omnis adip', 3, 'تابع للمورد/الوسيط', 'حسب المشروع', 12.00, 'byzylakeza@mailinator.com', 'Et veniam vel tempo', 'ضعيف', 'ممتاز (لا توجد شكاوى)', 'ثلاثة حوادث فأكثر (خطير)', 'غير محدد', 'Vero voluptas dolore', 'قديمة', 'Perferendis sequi re', 'Aut in commodi enim', 'Facilis deserunt par', 'Dolore dolor enim si', 'نشط', '2005-05-05', '2026-04-13 12:57:09', '+1 (989) 798-8604', '+1 (926) 976-8039', 1),
(13, 4, 4, 'عبد المنعم عبد الرحيم', 'DR31', 'Erin Nieves', 'بطاقة لاجئ', '285', '2001-12-13', NULL, NULL, '214', 'فئة د (شاحنات ثقيلة)', '2008-01-15', 'Dolor tempore dolor', 'دوزر (Dozer), شاحنة قلابة (Dump Truck), جرافة (Loader), معدات أخرى', 5, 1, 'متدرب (1-2 سنة)', 'Doloribus doloribus', 'Modi accusantium bla', 3, 'تابع للمورد/الوسيط', 'أسبوعي', 3.00, 'xypinebuqo@mailinator.com', 'Qui ea aliqua Sit', 'جيد', 'مقبول (بعض الشكاوى)', 'ثلاثة حوادث فأكثر (خطير)', 'بحالة مقبولة', 'Impedit irure exped', 'محدثة', 'Ipsum consequatur', 'Reiciendis nostrum l', 'Laboris sunt volupt', 'Anim aut aut asperio', 'نشط', '2013-09-10', '2026-04-13 12:58:57', '+1 (616) 127-4378', '+1 (646) 665-6012', 1),
(14, 4, 4, 'عثمان عبد الكريم', 'DR32', 'Anastasia Hewitt', 'بطاقة هوية وطنية', '761', '1994-12-18', NULL, NULL, '378', 'فئة أ (دراجات نارية)', '2013-09-28', 'Cillum ducimus anim', 'حفارة (Excavator), شاحنة تناكر/صهريج (Tanker Truck)', 5, 1, 'سيد حرفة (أكثر من 10 سنوات)', 'Ipsum voluptas temp', 'Architecto est exer', 3, 'مقاول مستقل', 'حسب الإنتاجية', 3.00, 'byvysur@mailinator.com', 'Excepturi impedit e', 'غير محدد', 'مقبول (بعض الشكاوى)', 'حادثان (متوسط)', 'غير محدد', 'Reiciendis aliqua O', 'قديمة', 'Delectus impedit m', 'Dignissimos nihil ea', 'Aperiam et ut itaque', 'Amet sunt rem ipsum', 'نشط', '2013-02-24', '2026-04-13 13:00:28', '+1 (613) 133-3777', '+1 (761) 824-4263', 1),
(15, 4, 4, 'كرم جبارة', 'DR33', 'Howard Fleming', 'بطاقة أخرى', '440', '1978-10-16', '', '', '876', 'متعددة الفئات', '2012-10-17', 'Vitae consequat Lab', 'حفارة (Excavator), مثقاب/مكنة تخريم (Drill Machine), شاحنة قلابة (Dump Truck), شاحنة تناكر/صهريج (Tanker Truck), ممهدة (Grader), معدات أخرى', 5, 1, 'مبتدئ (أقل من سنة)', 'Facere fugiat irure', 'Ut fugit quos alias', 3, 'تابع لشركة متخصصة في التشغيل', 'أسبوعي', 11.00, 'nyxe@mailinator.com', 'Dolor et consequatur', 'غير محدد', 'مقبول (بعض الشكاوى)', 'حادثان (متوسط)', 'بحالة جيدة', 'Delectus ea et repe', 'قديمة', 'Aliqua Molestiae qu', 'Enim tempore do sun', 'Id cum deserunt veli', 'Vero sed in molestia', 'نشط', '1970-02-16', '2026-04-13 13:01:41', '+1 (329) 275-8061', '+1 (135) 308-6085', 1),
(16, 4, 5, 'مجتبى إدريس', 'DR34', 'Lars Yates', 'رخصة قيادة', '147', '2025-08-19', '', '', '373', 'فئة د (شاحنات ثقيلة)', '1983-04-25', 'Enim sit dolorem do', 'مثقاب/مكنة تخريم (Drill Machine), دوزر (Dozer)', 5, 1, 'كفء (3-5 سنوات)', 'Sed sed nostrud eum', 'Quis vero molestias', 3, 'تابع للمورد/الوسيط', 'حسب الإنتاجية', 4.00, 'pojovogifi@mailinator.com', 'Aliqua Unde rerum d', 'ضعيف', 'ضعيف (شكاوى متكررة)', 'غير محدد', 'محتاج متابعة طبية', 'Ut quasi incididunt', 'قيد الفحص', 'Minim animi dolor l', 'Nulla quaerat cillum', 'Explicabo Tempore', 'Ullamco dolor quae m', 'نشط', '1984-12-30', '2026-04-13 13:03:16', '+1 (512) 131-4609', '+1 (797) 293-6917', 1),
(17, 4, 5, 'محمد بخيت', 'DR35', 'Bevis Holden', 'بطاقة هوية وطنية', '314', '1972-08-28', '', '', '402', 'فئة أ (دراجات نارية)', '1977-11-21', 'Id illo amet sed q', 'مثقاب/مكنة تخريم (Drill Machine), معدات أخرى', 5, 1, 'كفء (3-5 سنوات)', 'Non culpa autem elit', 'Possimus soluta sin', 3, 'تابع للمورد/الوسيط', 'حسب المشروع', 11.00, 'hyjok@mailinator.com', 'Aut harum velit dolo', 'ضعيف', 'ضعيف (شكاوى متكررة)', 'غير محدد', 'سليم تماماً', 'Velit consequatur V', 'قيد الفحص', 'Magnam eum quia inve', 'Similique elit volu', 'Exercitation animi', 'Laborum ut non nostr', 'نشط', '1986-04-29', '2026-04-13 13:04:47', '+1 (866) 829-4461', '+1 (177) 809-3388', 1),
(18, 4, 4, 'محمد فارس', 'DR36', 'Adam Buckner', 'جواز سفر', '977', '1987-08-08', '', '', '470', 'فئة ج (شاحنات خفيفة)', '2024-01-06', 'Nisi vitae eum quae', 'حفارة (Excavator), مثقاب/مكنة تخريم (Drill Machine), شاحنة قلابة (Dump Truck), معدات أخرى', 5, 1, 'سيد حرفة (أكثر من 10 سنوات)', 'Deserunt dolor conse', 'Asperiores eius atqu', 2, 'مقاول مستقل', 'حسب الإنتاجية', 3.00, 'risyzosy@mailinator.com', 'Aut ipsam vel unde o', 'مقبول', 'مقبول (بعض الشكاوى)', 'حادثان (متوسط)', 'سليم تماماً', 'Illum labore volupt', 'قديمة', 'Illo eos praesentiu', 'Et voluptatem magnam', 'Sed commodo non aper', 'Nobis ad qui duis ut', 'نشط', '1983-03-25', '2026-04-13 13:05:41', '+1 (591) 549-5872', '+1 (809) 954-7496', 1),
(19, 4, 4, 'محمد مضوي', 'DR37', 'Risa Holmes', 'رخصة قيادة', '334', '1995-02-28', NULL, NULL, '745', 'متعددة الفئات', '2021-01-18', 'Cupidatat assumenda', 'حفارة (Excavator), معدات أخرى', 5, 1, 'مبتدئ (أقل من سنة)', 'Reiciendis est saepe', 'Exercitationem conse', 3, 'تابع لشركة متخصصة في التشغيل', 'شهري', 7.00, 'kyjecu@mailinator.com', 'Cupiditate quidem co', 'غير محدد', 'مقبول (بعض الشكاوى)', 'حادثان (متوسط)', 'بحالة جيدة', 'Quos nemo quod hic o', 'لا يوجد فحص', 'Nihil nemo vel iste', 'In ut labore sint at', 'Aut eos at quo rerum', 'Et necessitatibus om', 'نشط', '2019-01-24', '2026-04-13 13:07:26', '+1 (296) 512-4095', '+1 (702) 423-8092', 1),
(20, 4, 4, 'محمود النيل', 'DR38', 'Kylynn Franklin', 'جواز سفر', '777', '2021-06-29', NULL, NULL, '221', 'فئة د (شاحنات ثقيلة)', '1987-07-27', 'Voluptatem Dolore a', 'جرافة (Loader), ممهدة (Grader), معدات أخرى', 5, 1, 'متدرب (1-2 سنة)', 'Doloribus officiis d', 'Laboris praesentium', 3, 'تابع لمالك المعدة مباشرة', 'حسب المشروع', 9.00, 'jyqozujyc@mailinator.com', 'Id iste explicabo U', 'ممتاز', 'غير محدد', 'ثلاثة حوادث فأكثر (خطير)', 'محتاج متابعة طبية', 'Et animi repudianda', 'محدثة', 'Praesentium natus no', 'In sunt enim vel est', 'Sit rerum dignissimo', 'Quis alias veritatis', 'نشط', '2004-04-20', '2026-04-13 13:08:49', '+1 (749) 684-7125', '+1 (362) 986-7752', 1),
(21, 4, 4, 'نصر الدين يحيا', 'DR39', 'Eden Hogan', 'بطاقة أخرى', '994', '1976-11-11', NULL, NULL, '680', 'فئة د (شاحنات ثقيلة)', '1982-06-13', 'Accusantium in Nam m', 'دوزر (Dozer), جرافة (Loader), ممهدة (Grader)', 5, 1, 'متدرب (1-2 سنة)', 'Omnis eum nisi odit', 'Vero ut illo deserun', 3, 'مقاول مستقل', 'شهري', 6.00, 'hymog@mailinator.com', 'Eum reprehenderit a', 'غير محدد', 'ممتاز (لا توجد شكاوى)', 'غير محدد', 'غير محدد', 'Eveniet rerum nobis', 'محدثة', 'Nostrum ea maiores d', 'Nobis voluptatem Ex', 'Perspiciatis incidu', 'Dolore eiusmod culpa', 'نشط', '2011-08-15', '2026-04-13 13:09:57', '+1 (352) 765-2112', '+1 (449) 191-6255', 1),
(22, 4, 4, 'ياسر جبارة', 'DR40', 'Theodore Wyatt', 'بطاقة لاجئ', '309', '1990-08-20', NULL, NULL, '190', 'فئة أ (دراجات نارية)', '2002-07-04', 'Inventore vero tenet', 'شاحنة قلابة (Dump Truck), ممهدة (Grader), معدات أخرى', 5, 1, 'كفء (3-5 سنوات)', 'Voluptate velit repr', 'Dolor eveniet dolor', 3, 'تابع للمورد/الوسيط', 'حسب الإنتاجية', 7.00, 'xyfiwota@mailinator.com', 'Quis repellendus Qu', 'جيد', 'غير محدد', 'حادثان (متوسط)', 'سليم تماماً', 'Aut sunt velit molli', 'محدثة', 'Id possimus autem i', 'Id modi aspernatur', 'Ab cumque nostrum la', 'Minus necessitatibus', 'نشط', '1983-02-10', '2026-04-13 13:10:50', '+1 (877) 981-3484', '+1 (416) 454-2556', 1),
(23, 4, 2, 'جديد فضل جديد', '131355', 'جديد', 'بطاقة هوية وطنية', '3132131331', '2026-05-08', '', '', '61313', 'فئة د (شاحنات ثقيلة)', '2026-05-03', 'مرور عطبرة', 'حفارة (Excavator), دوزر (Dozer), جرافة (Loader)', 8, 5, 'كفء (3-5 سنوات)', '', 'شركة صابركو', 5, 'تابع لمالك المعدة مباشرة', 'شهري', 1000.00, 'sudan@gmail.com', 'الخناق', 'جيد جداً', 'ممتاز (لا توجد شكاوى)', 'نظيف (لا توجد حوادث)', 'سليم تماماً', '', 'محدثة', 'دال للتعدين', '4', '', '', 'نشط', '2025-09-08', '2026-04-28 11:34:00', '6531533161', '532121235855', 1),
(24, 4, 5, 'على فضل الله', '946513', 'على', '', '3132131331', '2026-05-08', '', '', '61313', '', '2026-05-03', 'مرور عطبرة', 'شاحنة قلابة (Dump Truck), شاحنة تناكر/صهريج (Tanker Truck)', 8, 5, '', '', 'عمر هشام', 6, 'تابع لمالك المعدة مباشرة', 'شهري', 700.00, 'sudan@gmail.com', '', 'ممتاز', 'ممتاز (لا توجد شكاوى)', 'نظيف (لا توجد حوادث)', 'سليم تماماً', '', 'قديمة', 'دال للتعدين', '2', '', '', 'نشط', '2025-09-08', '2026-04-28 11:37:12', '6531533161', '5664+5+', 1),
(25, 4, 5, 'حيدر محمد أحمد', '89465132', 'حيدر', 'بطاقة هوية وطنية', '3132131331', '2026-05-08', '', '', '61313', '', '2026-05-03', 'مرور عطبرة', 'شاحنة قلابة (Dump Truck), شاحنة تناكر/صهريج (Tanker Truck)', 8, 5, 'خبير (5-10 سنوات)', '', 'عمر هشام', 6, 'تابع لمالك المعدة مباشرة', 'شهري', 700.00, 'sudan@gmail.com', '', 'ممتاز', 'ممتاز (لا توجد شكاوى)', 'حادث واحد (طفيف)', 'سليم تماماً', '', 'لا يوجد فحص', 'دال للتعدين', '3', '', '', 'نشط', '2025-09-08', '2026-04-28 11:39:31', '6531533161', '', 1);

-- --------------------------------------------------------

--
-- Table structure for table `driver_contract_notes`
--

CREATE TABLE `driver_contract_notes` (
  `id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
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
  `company_id` int(11) DEFAULT NULL,
  `suppliers` varchar(10) NOT NULL,
  `code` varchar(100) NOT NULL,
  `type` varchar(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `serial_number` varchar(100) DEFAULT NULL COMMENT 'رقم المعدة/الرقم التسلسلي',
  `chassis_number` varchar(100) DEFAULT NULL COMMENT 'رقم الهيكل/الهيكل الأساسي',
  `machine_number` varchar(100) DEFAULT NULL COMMENT 'رقم الماكينة أو المحرك',
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
  `document_type` varchar(100) DEFAULT NULL COMMENT 'نوع الوثيقة',
  `license_expiry_date` date DEFAULT NULL COMMENT 'تاريخ انتهاء الترخيص',
  `inspection_certificate_number` varchar(100) DEFAULT NULL COMMENT 'رقم شهادة الفحص',
  `last_inspection_date` date DEFAULT NULL COMMENT 'تاريخ آخر فحص',
  `current_location` varchar(255) DEFAULT NULL COMMENT 'الموقع الحالي',
  `site_supervisor_name` varchar(200) DEFAULT NULL COMMENT 'اسم المهندس أو المشرف في الموقع',
  `site_supervisor_contact` varchar(200) DEFAULT NULL COMMENT 'بيانات الاتصال بالمشرف في الموقع',
  `availability_state` varchar(20) NOT NULL DEFAULT 'متوفرة' COMMENT 'التوفر: متوفرة أو غير متوفرة',
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

INSERT INTO `equipments` (`id`, `company_id`, `suppliers`, `code`, `type`, `name`, `serial_number`, `chassis_number`, `machine_number`, `manufacturer`, `model`, `manufacturing_year`, `import_year`, `equipment_condition`, `operating_hours`, `engine_condition`, `tires_condition`, `actual_owner_name`, `owner_type`, `owner_phone`, `owner_supplier_relation`, `license_number`, `license_authority`, `document_type`, `license_expiry_date`, `inspection_certificate_number`, `last_inspection_date`, `current_location`, `site_supervisor_name`, `site_supervisor_contact`, `availability_state`, `availability_status`, `estimated_value`, `daily_rental_price`, `monthly_rental_price`, `insurance_status`, `general_notes`, `last_maintenance_date`, `status`) VALUES
(1, 4, '1', 'EQTQ1', '1', 'Plato Roberson', '883', '751', NULL, 'Aut officia aut exce', 'Alias ducimus eius', 2001, 2008, 'في حالة ضعيفة', 40, 'جيدة', 'متوسطة', 'Keegan Blanchard', 'مؤسسة', '+1 (835) 262-4394', 'تابع للمورد (مملوكة للمورد نفسه)', '435', 'Eum alias nisi et si', NULL, '1980-03-25', '72', '2001-10-21', 'Non natus in qui exp', NULL, NULL, 'متوفرة', 'قيد الاستخدام', 81.00, 457.00, 596.00, 'مؤمن بالكامل', 'Eum nihil eveniet c', '1975-01-22', 1),
(2, 4, '1', 'EQTQ2', '1', 'Orson Holcomb', '195', '78', '', 'Nobis ipsum eum dolo', 'Eaque ut veniam et', 1978, 2004, 'في حالة جيدة', 98, 'محتاجة صيانة', 'محتاجة تبديل', 'Rama Delaney', 'شركة متخصصة', '+1 (338) 629-2108', 'غير محدد', '93', 'Consequatur non recu', '', '1989-06-19', '36', '1985-06-18', 'Et culpa corporis au', '', '', 'متوفرة', 'قيد الاستخدام', 93.00, 259.00, 576.00, 'مؤمن بالكامل', 'Et sunt laboris volu', '1992-04-28', 0),
(3, 4, '3', 'EX29', '1', 'HMK220LC-2', '76197', '1250048', NULL, 'HIDROMEK', 'HMK220LC-2', 2021, 2021, 'في حالة جيدة', 9050, 'جيدة', 'N/A', 'EQUIPATION', 'أخرى', '249912345678', 'مالك مباشر (يتعاقد معنا مباشرة)', '4094', 'الإدارة العامة للمرور', NULL, '2025-09-11', '0000', '2025-09-11', 'الشركة الروسية', NULL, NULL, 'متوفرة', 'قيد الاستخدام', 100000.00, 800.00, 24000.00, 'مؤمن بالكامل', 'Consequatur id prov', '2026-04-12', 0),
(4, 4, '3', 'EX28', '1', 'HX 340 SL', '82230204', 'HHKHE944LM0000028', NULL, 'HYUNDAI', 'HX340SL', 2021, 2021, 'معطلة مؤقتاً', 11500, 'محتاجة صيانة', 'N/A', 'EQUIPATION', 'أخرى', '249912345678', 'مالك مباشر (يتعاقد معنا مباشرة)', '2224', 'الإدارة العامة للمرور', NULL, '2022-06-13', '21260051', '2021-06-13', 'الشركة الروسية', NULL, NULL, 'غير متوفرة', 'معطلة', 70000.00, 800.00, 24000.00, 'غير مؤمن', 'Corporis in doloribu', '2026-02-10', 1),
(5, 4, '4', 'EX26', '1', 'HMK220LC-2', '6D16-A73071', 'HMKH2520V0J125053', '', 'HIDROMEK', 'HMK220LC-2', 2019, 2019, 'في حالة جيدة', 12000, 'جيدة', 'N/A', 'EQUIPATION', '', '249912345678', '', 'PZUS02019024755', 'الجمارك', '', '2019-11-23', '1319126', '2021-10-15', 'الشركة الروسية', '', '', 'متوفرة', 'قيد الاستخدام', 95000.01, 800.00, 24000.00, '', '', '2026-04-10', 0),
(6, 4, '3', 'EX24', '1', 'HMK220LC-2', '6D16-A76592', 'HMKH2520KM125006', NULL, 'HIDROMEK', 'HMK220LC-2', 2021, 2021, 'في حالة جيدة', 10450, 'جيدة', 'N/A', 'EQUIPATION', '', '249912345678', '', '7/2374ن', 'الإدارة العامة للمرور', NULL, '2022-10-14', '21449471', '2021-10-14', 'الشركة الروسية', NULL, NULL, 'متوفرة', 'قيد الاستخدام', 150000.00, 800.00, 24000.00, '', '', '2026-04-10', 0),
(7, 4, '3', 'EX23', '1', 'HX 340 SL', '82447788', 'HHKHE944CN0000477', NULL, 'HYUNDAI', 'HX340SL', 2022, 2022, 'في حالة جيدة', 9800, 'جيدة', 'N/A', 'EQUIPATION', '', '249912345678', '', 'PZUN02022001191', 'الجمارك', NULL, '0001-01-01', '', NULL, 'نورايا', NULL, NULL, 'غير متوفرة', 'مسحوبة', 185000.00, 800.00, 24000.00, '', '', '2026-05-04', 0),
(8, 4, '4', 'EX22', '1', 'HX 340 SL', '82463818', 'HHKHE944CN0000360', '', 'HYUNDAI', 'HX340SL', 2022, 2022, 'في حالة جيدة', 10000, 'جيدة', 'N/A', 'EQUIPATION', 'أخرى', '249912345678', '', 'PZUN02021014933', 'الجمارك', '', '0001-01-01', '', NULL, 'نورايا', '', '', 'متوفرة', 'قيد الاستخدام', 180000.00, 800.00, 24000.00, 'مؤمن بالكامل', '', '2026-05-04', 0),
(9, 4, '3', 'EX21', '1', 'HX 340 SL', '8221373793', 'HHKH944HM0000144', NULL, 'HYUNDAI', 'HX340SL', 2021, 2021, 'في حالة جيدة', 9947, 'جيدة', 'N/A', 'EQUIPATION', 'أخرى', '249912345678', 'غير محدد', 'PZUN02021011333', 'الجمارك', NULL, '0001-01-01', '2020286', '2026-04-22', 'الشركة الروسية', NULL, NULL, 'متوفرة', 'قيد الاستخدام', 180000.00, 800.00, 24000.00, 'مؤمن بالكامل', 'معدة موثوقة تحتاج فقط للصيانة الدورية', '2026-04-01', 0),
(10, 4, '5', 'EQ1001', '1', 'HX 340 SL', '82463818', 'HHKHE944CN0000418', '64533131', 'HYUNDAI', 'HX340SL', 2020, 2020, 'في حالة جيدة', 5000, 'جيدة', 'N/A', 'شركة صابركو', 'شركة متخصصة', '249912345678', 'مالك مباشر (يتعاقد معنا مباشرة)', 'PZUN02021011445', 'الجمارك', 'شهادة وارد', NULL, '', NULL, 'الشركة الروسية', '', '', 'متوفرة', 'قيد الاستخدام', 180000.00, 400.00, 12000.00, 'مؤمن بالكامل', '', '2025-11-29', 0),
(11, 4, '6', 'TQ1006', '2', 'DAWEOO', '82463818', '5464654113', '64533131', 'DAWEOO', 'K6', 2021, 2022, 'في حالة جيدة', 100000, 'جيدة', 'جديدة', 'عمر هشام فضل المولى', 'مالك فردي', '89651', 'مالك مباشر (يتعاقد معنا مباشرة)', '7/1540ن', 'الإدارة العامة للمرور', 'ترخيص ( شهادة بحث)', '2026-05-01', '', NULL, 'الشركة الروسية', '', '', 'متوفرة', 'قيد الاستخدام', 70000.00, 80.00, 4800.00, 'مؤمن جزئياً', '', '2025-11-29', 0),
(12, 4, '4', 'DM01', '3', 'Drill Machine', '20251101', '20251101', 'DTH Drilling RIG ADET D3', 'ADET', 'DTH Drilling RIG ADET D3', 2025, 2024, 'جديدة (لم تستخدم)', NULL, 'ممتازة', 'N/A', 'شركة إكوبيشن للإستثمار المحدودة', 'شركة متخصصة', '249912345678', 'مالك مباشر (يتعاقد معنا مباشرة)', 'PZUN02021016811', 'الجمارك', 'شهادة وارد', '2026-05-07', '', '2026-05-07', 'الشركة الروسية', '', '', 'متوفرة', 'قيد الاستخدام', 100000.00, NULL, NULL, '', '', NULL, 0),
(13, 4, '4', 'H1', '3', 'h1', '', '', '', '', '', NULL, NULL, 'في حالة جيدة', NULL, 'جيدة', 'N/A', '', '', '', '', '', '', '', NULL, '', NULL, '', '', '', 'متوفرة', 'قيد الاستخدام', NULL, NULL, NULL, '', '', NULL, 0),
(14, 4, '4', 'tq10', '2', 'tq10', '', '', '', '', '', NULL, NULL, 'في حالة جيدة', NULL, 'جيدة', 'N/A', '', '', '', '', '', '', '', NULL, '', NULL, '', '', '', 'متوفرة', 'قيد الاستخدام', NULL, NULL, NULL, '', '', NULL, 0),
(15, 4, '7', 'itx', '1', 'itx', '1212389', '', '', '', '', NULL, NULL, 'في حالة جيدة', NULL, 'جيدة', 'N/A', '', '', '', '', '', '', '', NULL, '', NULL, '', '', '', 'متوفرة', 'قيد الاستخدام', NULL, NULL, NULL, '', '', NULL, 0);

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
(1, '1', 'حفار', 'active', '2026-04-07 11:49:34', '2026-04-07 11:49:34'),
(2, '2', 'قلاب', 'active', '2026-04-07 11:49:43', '2026-04-07 11:49:43'),
(3, '3', 'خرامة', 'active', '2026-05-01 07:56:22', '2026-05-01 07:56:22');

-- --------------------------------------------------------

--
-- Table structure for table `equipment_drivers`
--

CREATE TABLE `equipment_drivers` (
  `id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `equipment_id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `start_date` varchar(50) NOT NULL,
  `end_date` varchar(50) DEFAULT NULL,
  `shift_type` enum('D','N','B') NOT NULL DEFAULT 'B',
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `equipment_drivers`
--

INSERT INTO `equipment_drivers` (`id`, `company_id`, `equipment_id`, `driver_id`, `start_date`, `end_date`, `shift_type`, `status`) VALUES
(1, 4, 1, 1, '2026-04-01', '2026-04-30', 'B', 1),
(2, 4, 3, 2, '2026-04-01', '2026-04-30', 'B', 0),
(3, 4, 5, 2, '2026-04-01', '2026-04-30', 'B', 1),
(4, 4, 6, 16, '2026-04-01', '2026-04-30', 'B', 1),
(5, 4, 7, 8, '2026-04-01', '2026-04-30', 'B', 1),
(6, 4, 8, 3, '2026-04-01', '2026-05-16', 'B', 0),
(7, 4, 8, 4, '2026-04-01', '2099-12-31', 'D', 1),
(8, 4, 8, 5, '2026-04-01', '2026-04-18', 'B', 0),
(9, 4, 7, 5, '2026-04-18', '2099-12-31', 'B', 1),
(10, 4, 7, 15, '2026-04-18', '2099-12-31', 'B', 1),
(11, 4, 4, 17, '2026-04-25', '2099-12-31', 'B', 1),
(12, 4, 9, 6, '2025-07-01', '2026-07-01', 'B', 1),
(13, 4, 9, 7, '2025-07-01', '2026-07-01', 'B', 1),
(14, 4, 10, 23, '2025-12-01', '2026-04-30', 'B', 1),
(15, 4, 11, 25, '2025-12-01', '2026-04-30', 'B', 1),
(16, 4, 11, 24, '2025-12-01', '2026-04-30', 'B', 1),
(17, 4, 13, 21, '2026-05-11', '2099-12-31', 'D', 1),
(18, 4, 5, 6, '2026-05-18', '2099-12-31', 'B', 1),
(19, 4, 13, 10, '2026-05-18', '2099-12-31', 'N', 0),
(20, 4, 10, 9, '2026-05-19', '2099-12-31', 'B', 1),
(21, 4, 10, 19, '2026-05-19', '2099-12-31', 'B', 0),
(22, 4, 13, 10, '2026-05-01', '2099-12-31', 'B', 1),
(23, 4, 8, 22, '2026-05-31', '2026-05-30', 'D', 0),
(24, 4, 15, 19, '2026-05-31', '', 'B', 1),
(25, 4, 8, 18, '2026-06-02', '', 'N', 1);

-- --------------------------------------------------------

--
-- Table structure for table `failure_codes`
--

CREATE TABLE `failure_codes` (
  `id` int(11) NOT NULL,
  `equipment_type` tinyint(1) NOT NULL COMMENT '1=حفار, 2=قلاب, 3=خرامة',
  `event_type_code` varchar(10) NOT NULL COMMENT 'كود نوع الحدث: EQF,MNT,DEP,CST,MST,HRF,MKF',
  `event_type_name` varchar(100) NOT NULL COMMENT 'اسم نوع الحدث بالعربي',
  `main_category_code` varchar(10) NOT NULL COMMENT 'كود الفئة الرئيسية: MEC,HYD,ELE,COL...',
  `main_category_name` varchar(100) NOT NULL COMMENT 'اسم الفئة الرئيسية',
  `sub_category` varchar(100) NOT NULL COMMENT 'الفئة الفرعية (الجزء المعطل)',
  `failure_detail` varchar(200) NOT NULL COMMENT 'تفصيل العطل',
  `full_code` varchar(30) NOT NULL COMMENT 'الكود الكامل مثل EX-EQF-MEC-01-01',
  `status` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='تصنيفات أعطال المعدات - مرجع موحد';

--
-- Dumping data for table `failure_codes`
--

INSERT INTO `failure_codes` (`id`, `equipment_type`, `event_type_code`, `event_type_name`, `main_category_code`, `main_category_name`, `sub_category`, `failure_detail`, `full_code`, `status`) VALUES
(1, 1, 'OPR', 'تشغيل فعلي', 'OPP', 'إنتاج فعلي', 'ساعات إنتاج', 'إنتاج عادي', 'EX-OPR-OPP-01-01', 1),
(2, 1, 'OPR', 'تشغيل فعلي', 'OPP', 'إنتاج فعلي', 'ساعات إنتاج', 'إنتاج مكثف', 'EX-OPR-OPP-01-02', 1),
(3, 1, 'OPR', 'تشغيل فعلي', 'OPP', 'إنتاج فعلي', 'ساعات إنتاج', 'إنتاج خاص', 'EX-OPR-OPP-01-03', 1),
(4, 1, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'المحرك', 'منظومة الهواء', 'EX-EQF-MEC-01-01', 1),
(5, 1, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'المحرك', 'منظومة الوقود', 'EX-EQF-MEC-01-02', 1),
(6, 1, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'المحرك', 'منظومة التزييت', 'EX-EQF-MEC-01-03', 1),
(7, 1, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'المحرك', 'منظومة التبريد', 'EX-EQF-MEC-01-04', 1),
(8, 1, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'المحرك', 'رأس المحرك', 'EX-EQF-MEC-01-05', 1),
(9, 1, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'المحرك', 'السلندرات', 'EX-EQF-MEC-01-06', 1),
(10, 1, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'المحرك', 'الصدرية', 'EX-EQF-MEC-01-07', 1),
(11, 1, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'المحرك', 'الشيالات', 'EX-EQF-MEC-01-08', 1),
(12, 1, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'المحرك', 'سير المكنة', 'EX-EQF-MEC-01-09', 1),
(13, 1, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'المحرك', 'ارتفاع حرارة المحرك', 'EX-EQF-MEC-01-10', 1),
(14, 1, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'المحرك', 'إصلاح عام', 'EX-EQF-MEC-01-11', 1),
(15, 1, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'المحرك', 'أخرى', 'EX-EQF-MEC-01-12', 1),
(16, 1, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'نقل القدرة', 'عمود الطوالي (المحور)', 'EX-EQF-MEC-02-01', 1),
(17, 1, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'نقل القدرة', 'الكرونات', 'EX-EQF-MEC-02-02', 1),
(18, 1, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'نقل القدرة', 'الهوبات', 'EX-EQF-MEC-02-03', 1),
(19, 1, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'نقل القدرة', 'شيالات الجيربوكس', 'EX-EQF-MEC-02-04', 1),
(20, 1, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'نقل القدرة', 'الصلايب', 'EX-EQF-MEC-02-05', 1),
(21, 1, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'نقل القدرة', 'أنظمة التحريك', 'EX-EQF-MEC-02-06', 1),
(22, 1, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'نقل القدرة', 'تهريب الزيوت', 'EX-EQF-MEC-02-07', 1),
(23, 1, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'النظام الهيدروليكي', 'الطرمبات الهيدروليكية', 'EX-EQF-MEC-03-01', 1),
(24, 1, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'النظام الهيدروليكي', 'الخراطيم', 'EX-EQF-MEC-03-03', 1),
(25, 1, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'النظام الهيدروليكي', 'الفالفات (الصمامات)', 'EX-EQF-MEC-03-04', 1),
(26, 1, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'النظام الهيدروليكي', 'الهيدروليك تانك', 'EX-EQF-MEC-03-05', 1),
(27, 1, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'النظام الهيدروليكي', 'تهريب الزيت', 'EX-EQF-MEC-03-06', 1),
(28, 1, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'نظام السوينغ', 'موتور السوينغ', 'EX-EQF-MEC-04-01', 1),
(29, 1, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'نظام السوينغ', 'جير السوينغ', 'EX-EQF-MEC-04-02', 1),
(30, 1, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'نظام السوينغ', 'رولمان الدوران', 'EX-EQF-MEC-04-03', 1),
(31, 1, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'نظام السوينغ', 'زيت السوينغ', 'EX-EQF-MEC-04-04', 1),
(32, 1, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'الجنزير والحركة', 'الجنزير', 'EX-EQF-MEC-05-01', 1),
(33, 1, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'الجنزير والحركة', 'انقطاع الجنزير', 'EX-EQF-MEC-05-02', 1),
(34, 1, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'الجنزير والحركة', 'الرولرات', 'EX-EQF-MEC-05-03', 1),
(35, 1, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'الجنزير والحركة', 'الآيدلر', 'EX-EQF-MEC-05-04', 1),
(36, 1, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'الجنزير والحركة', 'السبركات', 'EX-EQF-MEC-05-05', 1),
(37, 1, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'الجنزير والحركة', 'شداد الجنزير', 'EX-EQF-MEC-05-06', 1),
(38, 1, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'نظام الزيت والفلاتر', 'زيت هيدروليك', 'EX-EQF-HYD-01-01', 1),
(39, 1, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'نظام الزيت والفلاتر', 'فلتر زيت', 'EX-EQF-HYD-01-02', 1),
(40, 1, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'نظام الزيت والفلاتر', 'تسريب زيت', 'EX-EQF-HYD-01-03', 1),
(41, 1, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'الطرمبة', 'طرمبة رئيسية', 'EX-EQF-HYD-02-01', 1),
(42, 1, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'الطرمبة', 'طرمبة مساعدة', 'EX-EQF-HYD-02-02', 1),
(43, 1, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'الطرمبة', 'تالفة', 'EX-EQF-HYD-02-03', 1),
(44, 1, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'الكنترول', 'لوحة كنترول', 'EX-EQF-HYD-03-01', 1),
(45, 1, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'الكنترول', 'صمامات تحكم', 'EX-EQF-HYD-03-02', 1),
(46, 1, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'الوصلات والخراطيم', 'تسريب', 'EX-EQF-HYD-04-01', 1),
(47, 1, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'الوصلات والخراطيم', 'تلف خرطوم', 'EX-EQF-HYD-04-02', 1),
(48, 1, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'الوصلات والخراطيم', 'وصلة مكسورة', 'EX-EQF-HYD-04-03', 1),
(49, 1, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'البساتم (السلندرات)', 'تسريب بستم', 'EX-EQF-HYD-05-01', 1),
(50, 1, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'البساتم (السلندرات)', 'تالف', 'EX-EQF-HYD-05-02', 1),
(51, 1, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'الصمامات', 'صمام لا يعمل', 'EX-EQF-HYD-06-01', 1),
(52, 1, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'الصمامات', 'صمام مسدود', 'EX-EQF-HYD-06-02', 1),
(53, 1, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'الموتورات والجيربوكسات', 'موتور هيدروليك', 'EX-EQF-HYD-07-01', 1),
(54, 1, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'الموتورات والجيربوكسات', 'جيربوكس', 'EX-EQF-HYD-07-02', 1),
(55, 1, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'خزان الزيت', 'تلوث الزيت', 'EX-EQF-HYD-08-02', 1),
(56, 1, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'البطارية', 'بطارية ضعيفة', 'EX-EQF-ELE-01-01', 1),
(57, 1, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'البطارية', 'بطارية تالفة', 'EX-EQF-ELE-01-02', 1),
(58, 1, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'البطارية', 'أطراف البطارية', 'EX-EQF-ELE-01-03', 1),
(59, 1, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'الضفيرة', 'قطع في الضفيرة', 'EX-EQF-ELE-02-01', 1),
(60, 1, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'الضفيرة', 'تماس كهربائي', 'EX-EQF-ELE-02-02', 1),
(61, 1, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'الضفيرة', 'تآكل', 'EX-EQF-ELE-02-03', 1),
(62, 1, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'الفيوزات', 'فيوز محروق', 'EX-EQF-ELE-03-01', 1),
(63, 1, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'الفيوزات', 'علبة الفيوزات', 'EX-EQF-ELE-03-02', 1),
(64, 1, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'الاستارتر', 'موتور الاستارتر', 'EX-EQF-ELE-04-01', 1),
(65, 1, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'الاستارتر', 'السلف', 'EX-EQF-ELE-04-02', 1),
(66, 1, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'الاستارتر', 'ريليه الاستارتر', 'EX-EQF-ELE-04-03', 1),
(67, 1, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'المقنيتة (الدينمو)', 'الدينمو لا يشحن', 'EX-EQF-ELE-05-01', 1),
(68, 1, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'المقنيتة (الدينمو)', 'حزام الدينمو', 'EX-EQF-ELE-05-02', 1),
(69, 1, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'الإضاءة', 'إضاءة أمامية', 'EX-EQF-ELE-06-01', 1),
(70, 1, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'الإضاءة', 'إضاءة خلفية', 'EX-EQF-ELE-06-02', 1),
(71, 1, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'الإضاءة', 'إشارات', 'EX-EQF-ELE-06-03', 1),
(72, 1, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'الإضاءة', 'إضاءة داخلية', 'EX-EQF-ELE-06-04', 1),
(73, 1, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'الإضاءة', 'تعطل الكشاف الدوار', 'EX-EQF-ELE-06-05', 1),
(74, 1, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'الطبلون (لوحة العدادات)', 'عدادات لا تعمل', 'EX-EQF-ELE-07-01', 1),
(75, 1, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'الطبلون (لوحة العدادات)', 'إنذارات لا تعمل', 'EX-EQF-ELE-07-02', 1),
(76, 1, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'المفتاح الرئيسي', 'مفتاح تالف', 'EX-EQF-ELE-08-01', 1),
(77, 1, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'حساسات الكمبيوتر (ECU)', 'حساس تالف', 'EX-EQF-ELE-09-01', 1),
(78, 1, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'حساسات الكمبيوتر (ECU)', 'وحدة تحكم تالفة', 'EX-EQF-ELE-09-02', 1),
(79, 1, 'EQF', 'عطل معدة', 'COL', 'أعطال التبريد والتكيف', 'كنترول التكيف', 'لوحة تحكم', 'EX-EQF-COL-01-01', 1),
(80, 1, 'EQF', 'عطل معدة', 'COL', 'أعطال التبريد والتكيف', 'كنترول التكيف', 'حساسات', 'EX-EQF-COL-01-02', 1),
(81, 1, 'EQF', 'عطل معدة', 'COL', 'أعطال التبريد والتكيف', 'كنترول التكيف', 'ريموت', 'EX-EQF-COL-01-03', 1),
(82, 1, 'EQF', 'عطل معدة', 'COL', 'أعطال التبريد والتكيف', 'الكمبرسور', 'كهرباء الكمبرسور', 'EX-EQF-COL-02-01', 1),
(83, 1, 'EQF', 'عطل معدة', 'COL', 'أعطال التبريد والتكيف', 'الكمبرسور', 'ميكانيكا الكمبرسور', 'EX-EQF-COL-02-02', 1),
(84, 1, 'EQF', 'عطل معدة', 'COL', 'أعطال التبريد والتكيف', 'الكمبرسور', 'السير', 'EX-EQF-COL-02-03', 1),
(85, 1, 'EQF', 'عطل معدة', 'COL', 'أعطال التبريد والتكيف', 'المروحة والهوايات', 'موتور المروحة', 'EX-EQF-COL-03-01', 1),
(86, 1, 'EQF', 'عطل معدة', 'COL', 'أعطال التبريد والتكيف', 'المروحة والهوايات', 'ريش المروحة', 'EX-EQF-COL-03-02', 1),
(87, 1, 'EQF', 'عطل معدة', 'COL', 'أعطال التبريد والتكيف', 'المروحة والهوايات', 'الهوايات', 'EX-EQF-COL-03-03', 1),
(88, 1, 'EQF', 'عطل معدة', 'COL', 'أعطال التبريد والتكيف', 'الفلاتر', 'فلتر داخلي', 'EX-EQF-COL-04-01', 1),
(89, 1, 'EQF', 'عطل معدة', 'COL', 'أعطال التبريد والتكيف', 'الفلاتر', 'فلتر خارجي', 'EX-EQF-COL-04-02', 1),
(90, 1, 'EQF', 'عطل معدة', 'COL', 'أعطال التبريد والتكيف', 'الغاز', 'تعبئة غاز', 'EX-EQF-COL-05-01', 1),
(91, 1, 'EQF', 'عطل معدة', 'COL', 'أعطال التبريد والتكيف', 'الغاز', 'تسريب غاز', 'EX-EQF-COL-05-02', 1),
(92, 1, 'EQF', 'عطل معدة', 'COL', 'أعطال التبريد والتكيف', 'بلف التمدد', 'انسداد', 'EX-EQF-COL-06-01', 1),
(93, 1, 'EQF', 'عطل معدة', 'COL', 'أعطال التبريد والتكيف', 'بلف التمدد', 'تلف', 'EX-EQF-COL-06-02', 1),
(94, 1, 'EQF', 'عطل معدة', 'COL', 'أعطال التبريد والتكيف', 'الحساسات', 'حساس حرارة', 'EX-EQF-COL-08-01', 1),
(95, 1, 'EQF', 'عطل معدة', 'COL', 'أعطال التبريد والتكيف', 'الحساسات', 'حساس ضغط', 'EX-EQF-COL-08-02', 1),
(96, 1, 'EQF', 'عطل معدة', 'COL', 'أعطال التبريد والتكيف', 'الثلاجة', 'تبريد ضعيف', 'EX-EQF-COL-10-01', 1),
(97, 1, 'EQF', 'عطل معدة', 'COL', 'أعطال التبريد والتكيف', 'الثلاجة', 'عدم تبريد', 'EX-EQF-COL-10-02', 1),
(98, 1, 'EQF', 'عطل معدة', 'ACC', 'الملحقات', 'الجردل', 'البستم', 'EX-EQF-ACC-01-01', 1),
(99, 1, 'EQF', 'عطل معدة', 'ACC', 'الملحقات', 'الجردل', 'الجلب', 'EX-EQF-ACC-01-02', 1),
(100, 1, 'EQF', 'عطل معدة', 'ACC', 'الملحقات', 'الجردل', 'البنوزة', 'EX-EQF-ACC-01-03', 1),
(101, 1, 'EQF', 'عطل معدة', 'ACC', 'الملحقات', 'الجردل', 'أعمال الحدادة', 'EX-EQF-ACC-01-04', 1),
(102, 1, 'EQF', 'عطل معدة', 'ACC', 'الملحقات', 'أسنان الجردل', 'تكسر أسنان الجردل', 'EX-EQF-ACC-02-01', 1),
(103, 1, 'EQF', 'عطل معدة', 'ACC', 'الملحقات', 'أسنان الجردل', 'تآكل الأسنان', 'EX-EQF-ACC-02-02', 1),
(104, 1, 'EQF', 'عطل معدة', 'ACC', 'الملحقات', 'أسنان الجردل', 'المسامير', 'EX-EQF-ACC-02-04', 1),
(105, 1, 'EQF', 'عطل معدة', 'ACC', 'الملحقات', 'الجاكهمر', 'موتور الجاكهمر', 'EX-EQF-ACC-03-01', 1),
(106, 1, 'EQF', 'عطل معدة', 'ACC', 'الملحقات', 'الجاكهمر', 'وصلات الجاكهمر', 'EX-EQF-ACC-03-02', 1),
(107, 1, 'EQF', 'عطل معدة', 'ACC', 'الملحقات', 'الجاكهمر', 'زيت الجاكهمر', 'EX-EQF-ACC-03-03', 1),
(108, 1, 'EQF', 'عطل معدة', 'ACC', 'الملحقات', 'الذراع (الإستيك)', 'تشققات', 'EX-EQF-ACC-04-01', 1),
(109, 1, 'EQF', 'عطل معدة', 'ACC', 'الملحقات', 'الذراع (الإستيك)', 'أعمال حدادة', 'EX-EQF-ACC-04-02', 1),
(110, 1, 'EQF', 'عطل معدة', 'BDY', 'الهيكل والقبين', 'أعمال الحدادة والسمكرة', 'إصلاح هيكل', 'EX-EQF-BDY-01-01', 1),
(111, 1, 'EQF', 'عطل معدة', 'BDY', 'الهيكل والقبين', 'أعمال الحدادة والسمكرة', 'تعديل', 'EX-EQF-BDY-01-02', 1),
(112, 1, 'EQF', 'عطل معدة', 'BDY', 'الهيكل والقبين', 'أعمال الحدادة والسمكرة', 'صبغ', 'EX-EQF-BDY-01-03', 1),
(113, 1, 'EQF', 'عطل معدة', 'BDY', 'الهيكل والقبين', 'أعمال الزجاج', 'كسر الزجاج الأمامي', 'EX-EQF-BDY-02-01', 1),
(114, 1, 'EQF', 'عطل معدة', 'BDY', 'الهيكل والقبين', 'أعمال الزجاج', 'كسر زجاج جانبي', 'EX-EQF-BDY-02-02', 1),
(115, 1, 'EQF', 'عطل معدة', 'BDY', 'الهيكل والقبين', 'أعمال الزجاج', 'كسر زجاج خلفي', 'EX-EQF-BDY-02-03', 1),
(116, 1, 'EQF', 'عطل معدة', 'BDY', 'الهيكل والقبين', 'القبين', 'مقعد السائق', 'EX-EQF-BDY-03-01', 1),
(117, 1, 'EQF', 'عطل معدة', 'BDY', 'الهيكل والقبين', 'القبين', 'أبواب القبين', 'EX-EQF-BDY-03-02', 1),
(118, 1, 'EQF', 'عطل معدة', 'BDY', 'الهيكل والقبين', 'القبين', 'تكييف القبين', 'EX-EQF-BDY-03-03', 1),
(119, 1, 'EQF', 'عطل معدة', 'BDY', 'الهيكل والقبين', 'الديكورات الداخلية', 'تلف ديكور', 'EX-EQF-BDY-04-01', 1),
(120, 1, 'EQF', 'عطل معدة', 'BDY', 'الهيكل والقبين', 'المرايا', 'تعطل المرايا', 'EX-EQF-BDY-06-01', 1),
(121, 1, 'EQF', 'عطل معدة', 'BDY', 'الهيكل والقبين', 'المرايا', 'كسر', 'EX-EQF-BDY-06-02', 1),
(122, 1, 'EQF', 'عطل معدة', 'BDY', 'الهيكل والقبين', 'حزام الأمان', 'تعطل حزام الأمان', 'EX-EQF-BDY-07-01', 1),
(123, 1, 'EQF', 'عطل معدة', 'TRK', 'الإطارات والجنزير', 'الفك والتركيب للساتك', 'فك ساتك', 'EX-EQF-TRK-01-01', 1),
(124, 1, 'EQF', 'عطل معدة', 'TRK', 'الإطارات والجنزير', 'الفك والتركيب للساتك', 'تركيب ساتك', 'EX-EQF-TRK-01-02', 1),
(125, 1, 'EQF', 'عطل معدة', 'TRK', 'الإطارات والجنزير', 'مراجعة اللساتك', 'فحص الضغط', 'EX-EQF-TRK-02-01', 1),
(126, 1, 'EQF', 'عطل معدة', 'TRK', 'الإطارات والجنزير', 'مراجعة اللساتك', 'فحص التآكل', 'EX-EQF-TRK-02-02', 1),
(127, 1, 'EQF', 'عطل معدة', 'TRK', 'الإطارات والجنزير', 'إصلاح الثقوب', 'ترقيع', 'EX-EQF-TRK-03-01', 1),
(128, 1, 'EQF', 'عطل معدة', 'TRK', 'الإطارات والجنزير', 'إصلاح الثقوب', 'استبدال', 'EX-EQF-TRK-03-02', 1),
(129, 1, 'MNT', 'توقف صيانة', 'PMP', 'صيانة وقائية مخططة', 'صيانة وقائية', 'غيار زيت الماكينة', 'EX-MNT-PMP-00-01', 1),
(130, 1, 'MNT', 'توقف صيانة', 'PMP', 'صيانة وقائية مخططة', 'صيانة وقائية', 'غيار زيت الهيدروليك', 'EX-MNT-PMP-00-02', 1),
(131, 1, 'MNT', 'توقف صيانة', 'PMP', 'صيانة وقائية مخططة', 'صيانة وقائية', 'غيار فلاتر الهواء', 'EX-MNT-PMP-00-03', 1),
(132, 1, 'MNT', 'توقف صيانة', 'PMP', 'صيانة وقائية مخططة', 'صيانة وقائية', 'غيار فلاتر الهيدروليك', 'EX-MNT-PMP-00-04', 1),
(133, 1, 'MNT', 'توقف صيانة', 'PMP', 'صيانة وقائية مخططة', 'صيانة وقائية', 'غيار فلاتر الزيت', 'EX-MNT-PMP-00-05', 1),
(134, 1, 'MNT', 'توقف صيانة', 'PMP', 'صيانة وقائية مخططة', 'صيانة وقائية', 'غيار فلاتر الوقود', 'EX-MNT-PMP-00-06', 1),
(135, 1, 'MNT', 'توقف صيانة', 'PMP', 'صيانة وقائية مخططة', 'صيانة وقائية', 'التشحيم اليومي', 'EX-MNT-PMP-00-07', 1),
(136, 1, 'MNT', 'توقف صيانة', 'PMP', 'صيانة وقائية مخططة', 'صيانة وقائية', 'الفحص الصباحي', 'EX-MNT-PMP-00-08', 1),
(137, 1, 'MNT', 'توقف صيانة', 'PMP', 'صيانة وقائية مخططة', 'صيانة وقائية', 'غسيل المعدة', 'EX-MNT-PMP-00-09', 1),
(138, 1, 'MNT', 'توقف صيانة', 'PMP', 'صيانة وقائية مخططة', 'صيانة وقائية', 'معايرة الحساسات', 'EX-MNT-PMP-00-10', 1),
(139, 1, 'MNT', 'توقف صيانة', 'PMC', 'صيانة تصحيحية', 'صيانة تصحيحية', 'إصلاح عطل ميكانيكي', 'EX-MNT-PMC-00-01', 1),
(140, 1, 'MNT', 'توقف صيانة', 'PMC', 'صيانة تصحيحية', 'صيانة تصحيحية', 'إصلاح عطل هيدروليكي', 'EX-MNT-PMC-00-02', 1),
(141, 1, 'MNT', 'توقف صيانة', 'PMC', 'صيانة تصحيحية', 'صيانة تصحيحية', 'إصلاح عطل كهربائي', 'EX-MNT-PMC-00-03', 1),
(142, 1, 'MNT', 'توقف صيانة', 'PMC', 'صيانة تصحيحية', 'صيانة تصحيحية', 'إصلاح إطارات', 'EX-MNT-PMC-00-04', 1),
(143, 1, 'MNT', 'توقف صيانة', 'PMC', 'صيانة تصحيحية', 'صيانة تصحيحية', 'إصلاح تبريد وتكيف', 'EX-MNT-PMC-00-05', 1),
(144, 1, 'MNT', 'توقف صيانة', 'PMC', 'صيانة تصحيحية', 'صيانة تصحيحية', 'إصلاح ملحقات', 'EX-MNT-PMC-00-06', 1),
(145, 1, 'MNT', 'توقف صيانة', 'PMC', 'صيانة تصحيحية', 'صيانة تصحيحية', 'إصلاح هيكل وقبين', 'EX-MNT-PMC-00-07', 1),
(146, 1, 'MNT', 'توقف صيانة', 'PME', 'صيانة طارئة', 'صيانة طارئة', 'حادث تصادم', 'EX-MNT-PME-00-01', 1),
(147, 1, 'MNT', 'توقف صيانة', 'PME', 'صيانة طارئة', 'صيانة طارئة', 'انقلاب', 'EX-MNT-PME-00-02', 1),
(148, 1, 'MNT', 'توقف صيانة', 'PME', 'صيانة طارئة', 'صيانة طارئة', 'كسر هيكلي', 'EX-MNT-PME-00-03', 1),
(149, 1, 'MNT', 'توقف صيانة', 'PME', 'صيانة طارئة', 'صيانة طارئة', 'أعطال بسبب التشغيل الخاطئ', 'EX-MNT-PME-00-04', 1),
(150, 1, 'DEP', 'توقف اعتماد', 'DPF', 'اعتماد كامل', 'اعتماد كامل', 'توقف المعدة الأم — عطل ميكانيكي', 'EX-DEP-DPF-00-01', 1),
(151, 1, 'DEP', 'توقف اعتماد', 'DPF', 'اعتماد كامل', 'اعتماد كامل', 'توقف المعدة الأم — عطل هيدروليكي', 'EX-DEP-DPF-00-02', 1),
(152, 1, 'DEP', 'توقف اعتماد', 'DPF', 'اعتماد كامل', 'اعتماد كامل', 'توقف المعدة الأم — عطل كهربائي', 'EX-DEP-DPF-00-03', 1),
(153, 1, 'DEP', 'توقف اعتماد', 'DPF', 'اعتماد كامل', 'اعتماد كامل', 'توقف المعدة الأم — عطل آخر', 'EX-DEP-DPF-00-04', 1),
(154, 1, 'DEP', 'توقف اعتماد', 'DPP', 'اعتماد جزئي', 'اعتماد جزئي', 'تشغيل بطاقة منخفضة', 'EX-DEP-DPP-00-01', 1),
(155, 1, 'DEP', 'توقف اعتماد', 'DPP', 'اعتماد جزئي', 'اعتماد جزئي', 'توقف جزئي عن الإنتاج', 'EX-DEP-DPP-00-02', 1),
(156, 1, 'DEP', 'توقف اعتماد', 'DPM', 'اعتماد متبادل', 'اعتماد متبادل', 'توقف معدة شريكة', 'EX-DEP-DPM-00-01', 1),
(157, 1, 'DEP', 'توقف اعتماد', 'DPM', 'اعتماد متبادل', 'اعتماد متبادل', 'توقف منظومة مرتبطة', 'EX-DEP-DPM-00-02', 1),
(158, 1, 'CST', 'استعداد عميل', 'CSS', 'عدم جهوزية الموقع', 'عدم جهوزية الموقع', 'عدم جهوزية جبهة العمل', 'EX-CST-CSS-00-01', 1),
(159, 1, 'CST', 'استعداد عميل', 'CSS', 'عدم جهوزية الموقع', 'عدم جهوزية الموقع', 'ممتلئة منطقة التفريغ', 'EX-CST-CSS-00-02', 1),
(160, 1, 'CST', 'استعداد عميل', 'CSS', 'عدم جهوزية الموقع', 'عدم جهوزية الموقع', 'إغلاق مؤقت للموقع', 'EX-CST-CSS-00-03', 1),
(161, 1, 'CST', 'استعداد عميل', 'CSP', 'عدم جهوزية الإنتاج', 'عدم جهوزية الإنتاج', 'انتظار التفجير', 'EX-CST-CSP-00-01', 1),
(162, 1, 'CST', 'استعداد عميل', 'CSP', 'عدم جهوزية الإنتاج', 'عدم جهوزية الإنتاج', 'انتظار المسّاح', 'EX-CST-CSP-00-02', 1),
(163, 1, 'CST', 'استعداد عميل', 'CSP', 'عدم جهوزية الإنتاج', 'عدم جهوزية الإنتاج', 'عدم توفر معدة التحميل المساندة', 'EX-CST-CSP-00-03', 1),
(164, 1, 'CST', 'استعداد عميل', 'CSP', 'عدم جهوزية الإنتاج', 'عدم جهوزية الإنتاج', 'عدم توفر معدات الترحيل', 'EX-CST-CSP-00-04', 1),
(165, 1, 'CST', 'استعداد عميل', 'CSL', 'عدم توفر مستلزمات التشغيل', 'عدم توفر مستلزمات', 'عدم وصول الوقود من جهة العميل', 'EX-CST-CSL-00-01', 1),
(166, 1, 'CST', 'استعداد عميل', 'CSL', 'عدم توفر مستلزمات التشغيل', 'عدم توفر مستلزمات', 'عدم توفر مياه الرش', 'EX-CST-CSL-00-02', 1),
(167, 1, 'CST', 'استعداد عميل', 'CSM', 'عدم جهوزية الإدارة من جانب العميل', 'إدارة العميل', 'عدم حضور مشرف العميل', 'EX-CST-CSM-00-01', 1),
(168, 1, 'CST', 'استعداد عميل', 'CSM', 'عدم جهوزية الإدارة من جانب العميل', 'إدارة العميل', 'عدم صدور تصريح التشغيل اليومي', 'EX-CST-CSM-00-02', 1),
(169, 1, 'CST', 'استعداد عميل', 'CSM', 'عدم جهوزية الإدارة من جانب العميل', 'إدارة العميل', 'التفتيش الأمني عند البوابة', 'EX-CST-CSM-00-03', 1),
(170, 1, 'MST', 'استعداد تسويق', 'MSC', 'ظروف مناخية', 'ظروف مناخية', 'موسم الأمطار', 'EX-MST-MSC-00-01', 1),
(171, 1, 'MST', 'استعداد تسويق', 'MSC', 'ظروف مناخية', 'ظروف مناخية', 'العواصف الرملية', 'EX-MST-MSC-00-02', 1),
(172, 1, 'MST', 'استعداد تسويق', 'MSC', 'ظروف مناخية', 'ظروف مناخية', 'السيول وانقطاع الطرق', 'EX-MST-MSC-00-03', 1),
(173, 1, 'MST', 'استعداد تسويق', 'MSC', 'ظروف مناخية', 'ظروف مناخية', 'الحرارة الشديدة', 'EX-MST-MSC-00-04', 1),
(174, 1, 'MST', 'استعداد تسويق', 'MSS', 'ظروف أمنية', 'ظروف أمنية', 'الوضع الأمني في المنطقة', 'EX-MST-MSS-00-01', 1),
(175, 1, 'MST', 'استعداد تسويق', 'MSS', 'ظروف أمنية', 'ظروف أمنية', 'إغلاق الطرق', 'EX-MST-MSS-00-02', 1),
(176, 1, 'MST', 'استعداد تسويق', 'MSS', 'ظروف أمنية', 'ظروف أمنية', 'التوترات مع المجتمعات المحلية', 'EX-MST-MSS-00-03', 1),
(177, 1, 'MST', 'استعداد تسويق', 'MSZ', 'ظروف زمنية', 'ظروف زمنية', 'الإجازات الرسمية غير المتفق عليها', 'EX-MST-MSZ-00-01', 1),
(178, 1, 'MST', 'استعداد تسويق', 'MSZ', 'ظروف زمنية', 'ظروف زمنية', 'تخفيض ساعات رمضان', 'EX-MST-MSZ-00-02', 1),
(179, 1, 'HRF', 'عطل HR', 'HRA', 'عدم توفر المشغل', 'عدم توفر المشغل', 'عدم توفر مشغل في الموقع', 'EX-HRF-HRA-00-01', 1),
(180, 1, 'HRF', 'عطل HR', 'HRA', 'عدم توفر المشغل', 'عدم توفر المشغل', 'تأخر مشغل الوردية البديلة', 'EX-HRF-HRA-00-02', 1),
(181, 1, 'HRF', 'عطل HR', 'HRB', 'غياب المشغل', 'غياب المشغل', 'مرض المشغل دون بديل', 'EX-HRF-HRB-00-01', 1),
(182, 1, 'HRF', 'عطل HR', 'HRB', 'غياب المشغل', 'غياب المشغل', 'إجازة مشغل دون بديل', 'EX-HRF-HRB-00-02', 1),
(183, 1, 'HRF', 'عطل HR', 'HRB', 'غياب المشغل', 'غياب المشغل', 'غياب المشغل دون عذر', 'EX-HRF-HRB-00-03', 1),
(184, 1, 'HRF', 'عطل HR', 'HRC', 'عدم كفاءة المشغل', 'عدم كفاءة المشغل', 'نقص خبرة المشغل في نوع المعدة', 'EX-HRF-HRC-00-01', 1),
(185, 1, 'HRF', 'عطل HR', 'HRC', 'عدم كفاءة المشغل', 'عدم كفاءة المشغل', 'انتهاء رخصة التشغيل', 'EX-HRF-HRC-00-02', 1),
(186, 1, 'HRF', 'عطل HR', 'HRG', 'أحداث جماعية', 'أحداث جماعية', 'إضراب المشغلين', 'EX-HRF-HRG-00-01', 1),
(187, 1, 'HRF', 'عطل HR', 'HRT', 'تطوير وتدريب', 'تطوير وتدريب', 'ساعات التدريب', 'EX-HRF-HRT-00-01', 1),
(188, 1, 'MKF', 'عطل تسويق', 'MFC', 'نقص الطاقة التعاقدية', 'نقص الطاقة التعاقدية', 'عقد بساعات أقل من الطاقة الإنتاجية للمعدة', 'EX-MKF-MFC-00-01', 1),
(189, 1, 'MKF', 'عطل تسويق', 'MFN', 'غياب العقد', 'غياب العقد', 'معدة بدون عقد', 'EX-MKF-MFN-00-01', 1),
(190, 1, 'MKF', 'عطل تسويق', 'MFN', 'غياب العقد', 'غياب العقد', 'فترة تجديد العقد', 'EX-MKF-MFN-00-02', 1),
(191, 1, 'MKF', 'عطل تسويق', 'MFI', 'إشكاليات تعاقدية', 'إشكاليات تعاقدية', 'توقف العقد لخلاف تعاقدي', 'EX-MKF-MFI-00-01', 1),
(192, 1, 'MKF', 'عطل تسويق', 'MFI', 'إشكاليات تعاقدية', 'إشكاليات تعاقدية', 'خسارة العقد بسبب التسعير', 'EX-MKF-MFI-00-02', 1),
(193, 1, 'MKF', 'عطل تسويق', 'MFI', 'إشكاليات تعاقدية', 'إشكاليات تعاقدية', 'توقف بسبب تأخر مدفوعات العميل', 'EX-MKF-MFI-00-03', 1),
(194, 2, 'OPR', 'تشغيل فعلي', 'OPP', 'إنتاج فعلي', 'ساعات إنتاج', 'إنتاج عادي', 'DT-OPR-OPP-01-01', 1),
(195, 2, 'OPR', 'تشغيل فعلي', 'OPP', 'إنتاج فعلي', 'ساعات إنتاج', 'إنتاج مكثف', 'DT-OPR-OPP-01-02', 1),
(196, 2, 'OPR', 'تشغيل فعلي', 'OPP', 'إنتاج فعلي', 'ساعات إنتاج', 'إنتاج خاص', 'DT-OPR-OPP-01-03', 1),
(197, 2, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'المحرك', 'منظومة الهواء', 'DT-EQF-MEC-01-01', 1),
(198, 2, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'المحرك', 'منظومة الوقود', 'DT-EQF-MEC-01-02', 1),
(199, 2, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'المحرك', 'منظومة التزييت', 'DT-EQF-MEC-01-03', 1),
(200, 2, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'المحرك', 'منظومة التبريد', 'DT-EQF-MEC-01-04', 1),
(201, 2, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'المحرك', 'رأس المحرك', 'DT-EQF-MEC-01-05', 1),
(202, 2, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'المحرك', 'السلندرات', 'DT-EQF-MEC-01-06', 1),
(203, 2, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'المحرك', 'الصدرية', 'DT-EQF-MEC-01-07', 1),
(204, 2, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'المحرك', 'الشيالات', 'DT-EQF-MEC-01-08', 1),
(205, 2, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'المحرك', 'سير المكنة', 'DT-EQF-MEC-01-09', 1),
(206, 2, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'المحرك', 'ارتفاع حرارة المحرك', 'DT-EQF-MEC-01-10', 1),
(207, 2, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'المحرك', 'إصلاح عام', 'DT-EQF-MEC-01-11', 1),
(208, 2, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'المحرك', 'أخرى', 'DT-EQF-MEC-01-12', 1),
(209, 2, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'الكلتش', 'الكلتش', 'DT-EQF-MEC-02-01', 1),
(210, 2, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'الجيربوكس', 'الجيربوكس', 'DT-EQF-MEC-02-02', 1),
(211, 2, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'عمود الكردان', 'عمود الكردان (الطوالي)', 'DT-EQF-MEC-02-03', 1),
(212, 2, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'الكرونات', 'الكرونات (الدفرنس)', 'DT-EQF-MEC-02-05', 1),
(213, 2, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'نظام التعليق', 'المساعدات', 'DT-EQF-MEC-03-01', 1),
(214, 2, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'نظام التعليق', 'الريش', 'DT-EQF-MEC-03-02', 1),
(215, 2, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'نظام التعليق', 'القواعد', 'DT-EQF-MEC-03-03', 1),
(216, 2, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'نظام التعليق', 'الشدادات', 'DT-EQF-MEC-03-04', 1),
(217, 2, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'نظام التعليق', 'الأكسات (المحاور)', 'DT-EQF-MEC-03-05', 1),
(218, 2, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'نظام التوجيه', 'طرمبة الباور', 'DT-EQF-MEC-04-01', 1),
(219, 2, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'نظام التوجيه', 'علبة الدركسون', 'DT-EQF-MEC-04-02', 1),
(220, 2, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'نظام التوجيه', 'الطارة', 'DT-EQF-MEC-04-03', 1),
(221, 2, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'نظام التوجيه', 'الأذرعة والوصلات', 'DT-EQF-MEC-04-04', 1),
(222, 2, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'نظام التوجيه', 'زيت الباور', 'DT-EQF-MEC-04-05', 1),
(223, 2, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'الفرامل', 'القماشات', 'DT-EQF-MEC-05-01', 1),
(224, 2, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'الفرامل', 'اللقم', 'DT-EQF-MEC-05-02', 1),
(225, 2, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'الفرامل', 'درامات / ديسكات', 'DT-EQF-MEC-05-03', 1),
(226, 2, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'الفرامل', 'نظام الهواء', 'DT-EQF-MEC-05-04', 1),
(227, 2, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'الفرامل', 'كمبروسر الهواء', 'DT-EQF-MEC-05-05', 1),
(228, 2, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'الفرامل', 'الخراطيم والوصلات', 'DT-EQF-MEC-05-06', 1),
(229, 2, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'الإطارات', 'انفجار الإطار في الموقع', 'DT-EQF-MEC-06-01', 1),
(230, 2, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'الإطارات', 'تآكل الإطارات', 'DT-EQF-MEC-06-02', 1),
(231, 2, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'الإطارات', 'البنشر', 'DT-EQF-MEC-06-03', 1),
(232, 2, 'EQF', 'عطل معدة', 'TIP', 'نظام القلاب', 'نظام القلاب الهيدروليكي', 'طرمبة الهيدروليك', 'DT-EQF-TIP-01-01', 1),
(233, 2, 'EQF', 'عطل معدة', 'TIP', 'نظام القلاب', 'نظام القلاب الهيدروليكي', 'سلندر الرفع', 'DT-EQF-TIP-01-02', 1),
(234, 2, 'EQF', 'عطل معدة', 'TIP', 'نظام القلاب', 'نظام القلاب الهيدروليكي', 'الفالفات', 'DT-EQF-TIP-01-04', 1),
(235, 2, 'EQF', 'عطل معدة', 'TIP', 'نظام القلاب', 'نظام القلاب الهيدروليكي', 'عدم رفع القلاب', 'DT-EQF-TIP-01-07', 1),
(236, 2, 'EQF', 'عطل معدة', 'TIP', 'نظام القلاب', 'صندوق القلاب', 'تشققات في الصندوق', 'DT-EQF-TIP-02-01', 1),
(237, 2, 'EQF', 'عطل معدة', 'TIP', 'نظام القلاب', 'صندوق القلاب', 'بطانة الصندوق', 'DT-EQF-TIP-02-03', 1),
(238, 2, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'نظام الزيت والفلاتر', 'زيت هيدروليك', 'DT-EQF-HYD-01-01', 1),
(239, 2, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'نظام الزيت والفلاتر', 'فلتر زيت', 'DT-EQF-HYD-01-02', 1),
(240, 2, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'نظام الزيت والفلاتر', 'تسريب زيت', 'DT-EQF-HYD-01-03', 1),
(241, 2, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'الطرمبة', 'طرمبة رئيسية', 'DT-EQF-HYD-02-01', 1),
(242, 2, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'الطرمبة', 'طرمبة مساعدة', 'DT-EQF-HYD-02-02', 1),
(243, 2, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'الطرمبة', 'تالفة', 'DT-EQF-HYD-02-03', 1),
(244, 2, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'الكنترول', 'لوحة كنترول', 'DT-EQF-HYD-03-01', 1),
(245, 2, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'الكنترول', 'صمامات تحكم', 'DT-EQF-HYD-03-02', 1),
(246, 2, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'الوصلات والخراطيم', 'تسريب', 'DT-EQF-HYD-04-01', 1),
(247, 2, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'الوصلات والخراطيم', 'تلف خرطوم', 'DT-EQF-HYD-04-02', 1),
(248, 2, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'الوصلات والخراطيم', 'وصلة مكسورة', 'DT-EQF-HYD-04-03', 1),
(249, 2, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'البساتم (السلندرات)', 'تسريب بستم', 'DT-EQF-HYD-05-01', 1),
(250, 2, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'البساتم (السلندرات)', 'تالف', 'DT-EQF-HYD-05-02', 1),
(251, 2, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'الصمامات', 'صمام لا يعمل', 'DT-EQF-HYD-06-01', 1),
(252, 2, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'الصمامات', 'صمام مسدود', 'DT-EQF-HYD-06-02', 1),
(253, 2, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'الموتورات والجيربوكسات', 'موتور هيدروليك', 'DT-EQF-HYD-07-01', 1),
(254, 2, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'الموتورات والجيربوكسات', 'جيربوكس', 'DT-EQF-HYD-07-02', 1),
(255, 2, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'البطارية', 'بطارية ضعيفة', 'DT-EQF-ELE-01-01', 1),
(256, 2, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'البطارية', 'بطارية تالفة', 'DT-EQF-ELE-01-02', 1),
(257, 2, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'البطارية', 'أطراف البطارية', 'DT-EQF-ELE-01-03', 1),
(258, 2, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'الضفيرة', 'قطع في الضفيرة', 'DT-EQF-ELE-02-01', 1),
(259, 2, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'الضفيرة', 'تماس كهربائي', 'DT-EQF-ELE-02-02', 1),
(260, 2, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'الضفيرة', 'تآكل', 'DT-EQF-ELE-02-03', 1),
(261, 2, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'الفيوزات', 'فيوز محروق', 'DT-EQF-ELE-03-01', 1),
(262, 2, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'الاستارتر', 'موتور الاستارتر', 'DT-EQF-ELE-04-01', 1),
(263, 2, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'الاستارتر', 'السلف', 'DT-EQF-ELE-04-02', 1),
(264, 2, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'المقنيتة (الدينمو)', 'الدينمو لا يشحن', 'DT-EQF-ELE-05-01', 1),
(265, 2, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'المقنيتة (الدينمو)', 'حزام الدينمو', 'DT-EQF-ELE-05-02', 1),
(266, 2, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'الإضاءة', 'إضاءة أمامية', 'DT-EQF-ELE-06-01', 1),
(267, 2, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'الإضاءة', 'إضاءة خلفية', 'DT-EQF-ELE-06-02', 1),
(268, 2, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'الإضاءة', 'إشارات', 'DT-EQF-ELE-06-03', 1),
(269, 2, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'الطبلون (لوحة العدادات)', 'عدادات لا تعمل', 'DT-EQF-ELE-07-01', 1),
(270, 2, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'حساسات الكمبيوتر (ECU)', 'حساس تالف', 'DT-EQF-ELE-09-01', 1),
(271, 2, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'حساسات الكمبيوتر (ECU)', 'وحدة تحكم تالفة', 'DT-EQF-ELE-09-02', 1),
(272, 2, 'EQF', 'عطل معدة', 'COL', 'أعطال التبريد والتكيف', 'كنترول التكيف', 'لوحة تحكم', 'DT-EQF-COL-01-01', 1),
(273, 2, 'EQF', 'عطل معدة', 'COL', 'أعطال التبريد والتكيف', 'الكمبرسور', 'كهرباء الكمبرسور', 'DT-EQF-COL-02-01', 1),
(274, 2, 'EQF', 'عطل معدة', 'COL', 'أعطال التبريد والتكيف', 'المروحة والهوايات', 'موتور المروحة', 'DT-EQF-COL-03-01', 1),
(275, 2, 'EQF', 'عطل معدة', 'COL', 'أعطال التبريد والتكيف', 'الغاز', 'تعبئة غاز', 'DT-EQF-COL-05-01', 1),
(276, 2, 'EQF', 'عطل معدة', 'COL', 'أعطال التبريد والتكيف', 'الغاز', 'تسريب غاز', 'DT-EQF-COL-05-02', 1),
(277, 2, 'EQF', 'عطل معدة', 'COL', 'أعطال التبريد والتكيف', 'الثلاجة', 'تبريد ضعيف', 'DT-EQF-COL-10-01', 1),
(278, 2, 'EQF', 'عطل معدة', 'BDY', 'الهيكل والقبين', 'أعمال الحدادة والسمكرة', 'إصلاح هيكل', 'DT-EQF-BDY-01-01', 1),
(279, 2, 'EQF', 'عطل معدة', 'BDY', 'الهيكل والقبين', 'أعمال الزجاج', 'كسر الزجاج الأمامي', 'DT-EQF-BDY-02-01', 1),
(280, 2, 'EQF', 'عطل معدة', 'BDY', 'الهيكل والقبين', 'القبين', 'مقعد السائق', 'DT-EQF-BDY-03-01', 1),
(281, 2, 'EQF', 'عطل معدة', 'BDY', 'الهيكل والقبين', 'المرايا', 'تعطل المرايا', 'DT-EQF-BDY-06-01', 1),
(282, 2, 'EQF', 'عطل معدة', 'TIR', 'الإطارات', 'الإطارات', 'انفجار الإطار', 'DT-EQF-TIR-01-01', 1),
(283, 2, 'EQF', 'عطل معدة', 'TIR', 'الإطارات', 'الإطارات', 'البنشر', 'DT-EQF-TIR-01-02', 1),
(284, 2, 'EQF', 'عطل معدة', 'TIR', 'الإطارات', 'الإطارات', 'تآكل الإطار', 'DT-EQF-TIR-01-03', 1),
(285, 2, 'MNT', 'توقف صيانة', 'PMP', 'صيانة وقائية مخططة', 'صيانة وقائية', 'غيار زيت الماكينة', 'DT-MNT-PMP-00-01', 1),
(286, 2, 'MNT', 'توقف صيانة', 'PMP', 'صيانة وقائية مخططة', 'صيانة وقائية', 'غيار زيت الهيدروليك', 'DT-MNT-PMP-00-02', 1),
(287, 2, 'MNT', 'توقف صيانة', 'PMP', 'صيانة وقائية مخططة', 'صيانة وقائية', 'غيار فلاتر الهواء', 'DT-MNT-PMP-00-03', 1),
(288, 2, 'MNT', 'توقف صيانة', 'PMP', 'صيانة وقائية مخططة', 'صيانة وقائية', 'التشحيم اليومي', 'DT-MNT-PMP-00-07', 1),
(289, 2, 'MNT', 'توقف صيانة', 'PMP', 'صيانة وقائية مخططة', 'صيانة وقائية', 'الفحص الصباحي', 'DT-MNT-PMP-00-08', 1),
(290, 2, 'MNT', 'توقف صيانة', 'PMC', 'صيانة تصحيحية', 'صيانة تصحيحية', 'إصلاح عطل ميكانيكي', 'DT-MNT-PMC-00-01', 1),
(291, 2, 'MNT', 'توقف صيانة', 'PMC', 'صيانة تصحيحية', 'صيانة تصحيحية', 'إصلاح عطل كهربائي', 'DT-MNT-PMC-00-03', 1),
(292, 2, 'MNT', 'توقف صيانة', 'PMC', 'صيانة تصحيحية', 'صيانة تصحيحية', 'إصلاح إطارات', 'DT-MNT-PMC-00-04', 1),
(293, 2, 'MNT', 'توقف صيانة', 'PME', 'صيانة طارئة', 'صيانة طارئة', 'حادث تصادم', 'DT-MNT-PME-00-01', 1),
(294, 2, 'MNT', 'توقف صيانة', 'PME', 'صيانة طارئة', 'صيانة طارئة', 'انقلاب', 'DT-MNT-PME-00-02', 1),
(295, 2, 'DEP', 'توقف اعتماد', 'DPF', 'اعتماد كامل', 'اعتماد كامل', 'توقف المعدة الأم — عطل ميكانيكي', 'DT-DEP-DPF-00-01', 1),
(296, 2, 'DEP', 'توقف اعتماد', 'DPF', 'اعتماد كامل', 'اعتماد كامل', 'توقف المعدة الأم — عطل هيدروليكي', 'DT-DEP-DPF-00-02', 1),
(297, 2, 'DEP', 'توقف اعتماد', 'DPP', 'اعتماد جزئي', 'اعتماد جزئي', 'تشغيل بطاقة منخفضة', 'DT-DEP-DPP-00-01', 1),
(298, 2, 'DEP', 'توقف اعتماد', 'DPM', 'اعتماد متبادل', 'اعتماد متبادل', 'توقف معدة شريكة', 'DT-DEP-DPM-00-01', 1),
(299, 2, 'CST', 'استعداد عميل', 'CSS', 'عدم جهوزية الموقع', 'عدم جهوزية الموقع', 'عدم جهوزية جبهة العمل', 'DT-CST-CSS-00-01', 1),
(300, 2, 'CST', 'استعداد عميل', 'CSS', 'عدم جهوزية الموقع', 'عدم جهوزية الموقع', 'ممتلئة منطقة التفريغ', 'DT-CST-CSS-00-02', 1),
(301, 2, 'CST', 'استعداد عميل', 'CSP', 'عدم جهوزية الإنتاج', 'عدم جهوزية الإنتاج', 'انتظار التفجير', 'DT-CST-CSP-00-01', 1),
(302, 2, 'CST', 'استعداد عميل', 'CSP', 'عدم جهوزية الإنتاج', 'عدم جهوزية الإنتاج', 'عدم توفر معدة التحميل المساندة', 'DT-CST-CSP-00-03', 1),
(303, 2, 'CST', 'استعداد عميل', 'CSL', 'عدم توفر مستلزمات', 'عدم توفر مستلزمات', 'عدم وصول الوقود من جهة العميل', 'DT-CST-CSL-00-01', 1),
(304, 2, 'CST', 'استعداد عميل', 'CSM', 'إدارة العميل', 'إدارة العميل', 'عدم حضور مشرف العميل', 'DT-CST-CSM-00-01', 1),
(305, 2, 'CST', 'استعداد عميل', 'CSM', 'إدارة العميل', 'إدارة العميل', 'عدم صدور تصريح التشغيل اليومي', 'DT-CST-CSM-00-02', 1),
(306, 2, 'MST', 'استعداد تسويق', 'MSC', 'ظروف مناخية', 'ظروف مناخية', 'موسم الأمطار', 'DT-MST-MSC-00-01', 1),
(307, 2, 'MST', 'استعداد تسويق', 'MSC', 'ظروف مناخية', 'ظروف مناخية', 'العواصف الرملية', 'DT-MST-MSC-00-02', 1),
(308, 2, 'MST', 'استعداد تسويق', 'MSS', 'ظروف أمنية', 'ظروف أمنية', 'الوضع الأمني في المنطقة', 'DT-MST-MSS-00-01', 1),
(309, 2, 'MST', 'استعداد تسويق', 'MSZ', 'ظروف زمنية', 'ظروف زمنية', 'الإجازات الرسمية غير المتفق عليها', 'DT-MST-MSZ-00-01', 1),
(310, 2, 'HRF', 'عطل HR', 'HRA', 'عدم توفر المشغل', 'عدم توفر المشغل', 'عدم توفر مشغل في الموقع', 'DT-HRF-HRA-00-01', 1),
(311, 2, 'HRF', 'عطل HR', 'HRA', 'عدم توفر المشغل', 'عدم توفر المشغل', 'تأخر مشغل الوردية البديلة', 'DT-HRF-HRA-00-02', 1),
(312, 2, 'HRF', 'عطل HR', 'HRB', 'غياب المشغل', 'غياب المشغل', 'مرض المشغل دون بديل', 'DT-HRF-HRB-00-01', 1),
(313, 2, 'HRF', 'عطل HR', 'HRB', 'غياب المشغل', 'غياب المشغل', 'إجازة مشغل دون بديل', 'DT-HRF-HRB-00-02', 1),
(314, 2, 'HRF', 'عطل HR', 'HRB', 'غياب المشغل', 'غياب المشغل', 'غياب المشغل دون عذر', 'DT-HRF-HRB-00-03', 1),
(315, 2, 'HRF', 'عطل HR', 'HRC', 'عدم كفاءة المشغل', 'عدم كفاءة المشغل', 'نقص خبرة المشغل في نوع المعدة', 'DT-HRF-HRC-00-01', 1),
(316, 2, 'HRF', 'عطل HR', 'HRT', 'تطوير وتدريب', 'تطوير وتدريب', 'ساعات التدريب', 'DT-HRF-HRT-00-01', 1),
(317, 2, 'MKF', 'عطل تسويق', 'MFN', 'غياب العقد', 'غياب العقد', 'معدة بدون عقد', 'DT-MKF-MFN-00-01', 1),
(318, 2, 'MKF', 'عطل تسويق', 'MFN', 'غياب العقد', 'غياب العقد', 'فترة تجديد العقد', 'DT-MKF-MFN-00-02', 1),
(319, 2, 'MKF', 'عطل تسويق', 'MFI', 'إشكاليات تعاقدية', 'إشكاليات تعاقدية', 'توقف العقد لخلاف تعاقدي', 'DT-MKF-MFI-00-01', 1),
(320, 2, 'MKF', 'عطل تسويق', 'MFI', 'إشكاليات تعاقدية', 'إشكاليات تعاقدية', 'توقف بسبب تأخر مدفوعات العميل', 'DT-MKF-MFI-00-03', 1),
(321, 3, 'OPR', 'تشغيل فعلي', 'OPP', 'إنتاج فعلي', 'ساعات إنتاج', 'إنتاج عادي', 'DR-OPR-OPP-01-01', 1),
(322, 3, 'OPR', 'تشغيل فعلي', 'OPP', 'إنتاج فعلي', 'ساعات إنتاج', 'إنتاج مكثف', 'DR-OPR-OPP-01-02', 1),
(323, 3, 'OPR', 'تشغيل فعلي', 'OPP', 'إنتاج فعلي', 'ساعات إنتاج', 'إنتاج خاص', 'DR-OPR-OPP-01-03', 1),
(324, 3, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'المحرك', 'منظومة الهواء', 'DR-EQF-MEC-01-01', 1),
(325, 3, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'المحرك', 'منظومة الوقود', 'DR-EQF-MEC-01-02', 1),
(326, 3, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'المحرك', 'منظومة التزييت', 'DR-EQF-MEC-01-03', 1),
(327, 3, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'المحرك', 'منظومة التبريد', 'DR-EQF-MEC-01-04', 1),
(328, 3, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'المحرك', 'رأس المحرك', 'DR-EQF-MEC-01-05', 1),
(329, 3, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'المحرك', 'السلندرات', 'DR-EQF-MEC-01-06', 1),
(330, 3, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'المحرك', 'الصدرية', 'DR-EQF-MEC-01-07', 1),
(331, 3, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'المحرك', 'ارتفاع حرارة المحرك', 'DR-EQF-MEC-01-10', 1),
(332, 3, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'المحرك', 'إصلاح عام', 'DR-EQF-MEC-01-11', 1),
(333, 3, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'نقل القدرة', 'عمود الطوالي (المحور)', 'DR-EQF-MEC-02-01', 1),
(334, 3, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'نقل القدرة', 'الكرونات', 'DR-EQF-MEC-02-02', 1),
(335, 3, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'الطرمبات', 'الطرمبات', 'DR-EQF-MEC-03-01', 1),
(336, 3, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'الطرمبات', 'تانك الهيدروليك', 'DR-EQF-MEC-03-05', 1),
(337, 3, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'موتور الحركة', 'موتور الحركة', 'DR-EQF-MEC-04-06', 1),
(338, 3, 'EQF', 'عطل معدة', 'DRL', 'نظام الحفر', 'الكمبروسر', 'الكمبروسر', 'DR-EQF-DRL-01-01', 1),
(339, 3, 'EQF', 'عطل معدة', 'DRL', 'نظام الحفر', 'الكمبروسر', 'خزان الهواء', 'DR-EQF-DRL-01-02', 1),
(340, 3, 'EQF', 'عطل معدة', 'DRL', 'نظام الحفر', 'الكمبروسر', 'فلاتر الهواء', 'DR-EQF-DRL-01-03', 1),
(341, 3, 'EQF', 'عطل معدة', 'DRL', 'نظام الحفر', 'الكمبروسر', 'منظمات الضغط', 'DR-EQF-DRL-01-04', 1),
(342, 3, 'EQF', 'عطل معدة', 'DRL', 'نظام الحفر', 'الكمبروسر', 'تسريب الهواء', 'DR-EQF-DRL-01-06', 1),
(343, 3, 'EQF', 'عطل معدة', 'DRL', 'نظام الحفر', 'المطرقة وأدوات الحفر', 'المطرقة', 'DR-EQF-DRL-02-01', 1),
(344, 3, 'EQF', 'عطل معدة', 'DRL', 'نظام الحفر', 'المطرقة وأدوات الحفر', 'رأس الحفر', 'DR-EQF-DRL-02-02', 1),
(345, 3, 'EQF', 'عطل معدة', 'DRL', 'نظام الحفر', 'المطرقة وأدوات الحفر', 'القضبان', 'DR-EQF-DRL-02-03', 1),
(346, 3, 'EQF', 'عطل معدة', 'DRL', 'نظام الحفر', 'المطرقة وأدوات الحفر', 'نظام الدوران', 'DR-EQF-DRL-02-04', 1),
(347, 3, 'EQF', 'عطل معدة', 'DRL', 'نظام الحفر', 'المطرقة وأدوات الحفر', 'تآكل أدوات الحفر', 'DR-EQF-DRL-02-05', 1),
(348, 3, 'EQF', 'عطل معدة', 'DRL', 'نظام الحفر', 'المطرقة وأدوات الحفر', 'كسر القضبان', 'DR-EQF-DRL-02-06', 1),
(349, 3, 'EQF', 'عطل معدة', 'DRL', 'نظام الحفر', 'نظام الدفع', 'مسار الحفر', 'DR-EQF-DRL-03-01', 1),
(350, 3, 'EQF', 'عطل معدة', 'DRL', 'نظام الحفر', 'نظام الدفع', 'سلسلة الدفع', 'DR-EQF-DRL-03-02', 1),
(351, 3, 'EQF', 'عطل معدة', 'DRL', 'نظام الحفر', 'نظام الدفع', 'موتور الدفع', 'DR-EQF-DRL-03-03', 1),
(352, 3, 'EQF', 'عطل معدة', 'DRL', 'نظام الحفر', 'نظام الدفع', 'شداد السلسلة', 'DR-EQF-DRL-03-04', 1),
(353, 3, 'EQF', 'عطل معدة', 'DRL', 'نظام الحفر', 'نظام الدفع', 'انحراف المسار', 'DR-EQF-DRL-03-05', 1),
(354, 3, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'نظام الزيت والفلاتر', 'زيت هيدروليك', 'DR-EQF-HYD-01-01', 1),
(355, 3, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'نظام الزيت والفلاتر', 'تسريب زيت', 'DR-EQF-HYD-01-03', 1),
(356, 3, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'الطرمبة', 'طرمبة رئيسية', 'DR-EQF-HYD-02-01', 1),
(357, 3, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'الوصلات والخراطيم', 'تلف خرطوم', 'DR-EQF-HYD-04-02', 1),
(358, 3, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'البطارية', 'بطارية ضعيفة', 'DR-EQF-ELE-01-01', 1),
(359, 3, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'البطارية', 'بطارية تالفة', 'DR-EQF-ELE-01-02', 1),
(360, 3, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'الضفيرة', 'قطع في الضفيرة', 'DR-EQF-ELE-02-01', 1),
(361, 3, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'الاستارتر', 'موتور الاستارتر', 'DR-EQF-ELE-04-01', 1),
(362, 3, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'حساسات الكمبيوتر (ECU)', 'حساس تالف', 'DR-EQF-ELE-09-01', 1),
(363, 3, 'EQF', 'عطل معدة', 'COL', 'أعطال التبريد والتكيف', 'كنترول التكيف', 'لوحة تحكم', 'DR-EQF-COL-01-01', 1),
(364, 3, 'EQF', 'عطل معدة', 'COL', 'أعطال التبريد والتكيف', 'الغاز', 'تعبئة غاز', 'DR-EQF-COL-05-01', 1),
(365, 3, 'EQF', 'عطل معدة', 'COL', 'أعطال التبريد والتكيف', 'الثلاجة', 'تبريد ضعيف', 'DR-EQF-COL-10-01', 1),
(366, 3, 'EQF', 'عطل معدة', 'BDY', 'الهيكل والقبين', 'أعمال الحدادة والسمكرة', 'إصلاح هيكل', 'DR-EQF-BDY-01-01', 1),
(367, 3, 'EQF', 'عطل معدة', 'BDY', 'الهيكل والقبين', 'أعمال الزجاج', 'كسر الزجاج الأمامي', 'DR-EQF-BDY-02-01', 1),
(368, 3, 'EQF', 'عطل معدة', 'BDY', 'الهيكل والقبين', 'القبين', 'مقعد السائق', 'DR-EQF-BDY-03-01', 1),
(369, 3, 'EQF', 'عطل معدة', 'TRK', 'الإطارات والجنزير', 'الفك والتركيب للساتك', 'فك ساتك', 'DR-EQF-TRK-01-01', 1),
(370, 3, 'EQF', 'عطل معدة', 'TRK', 'الإطارات والجنزير', 'إصلاح الثقوب', 'ترقيع', 'DR-EQF-TRK-03-01', 1),
(371, 3, 'MNT', 'توقف صيانة', 'PMP', 'صيانة وقائية مخططة', 'صيانة وقائية', 'غيار زيت الماكينة', 'DR-MNT-PMP-00-01', 1),
(372, 3, 'MNT', 'توقف صيانة', 'PMP', 'صيانة وقائية مخططة', 'صيانة وقائية', 'غيار فلاتر الهواء', 'DR-MNT-PMP-00-03', 1),
(373, 3, 'MNT', 'توقف صيانة', 'PMP', 'صيانة وقائية مخططة', 'صيانة وقائية', 'التشحيم اليومي', 'DR-MNT-PMP-00-07', 1),
(374, 3, 'MNT', 'توقف صيانة', 'PMP', 'صيانة وقائية مخططة', 'صيانة وقائية', 'الفحص الصباحي', 'DR-MNT-PMP-00-08', 1),
(375, 3, 'MNT', 'توقف صيانة', 'PMC', 'صيانة تصحيحية', 'صيانة تصحيحية', 'إصلاح عطل ميكانيكي', 'DR-MNT-PMC-00-01', 1),
(376, 3, 'MNT', 'توقف صيانة', 'PMC', 'صيانة تصحيحية', 'صيانة تصحيحية', 'إصلاح نظام الحفر', 'DR-MNT-PMC-00-06', 1),
(377, 3, 'MNT', 'توقف صيانة', 'PME', 'صيانة طارئة', 'صيانة طارئة', 'حادث تصادم', 'DR-MNT-PME-00-01', 1),
(378, 3, 'MNT', 'توقف صيانة', 'PME', 'صيانة طارئة', 'صيانة طارئة', 'كسر هيكلي', 'DR-MNT-PME-00-03', 1),
(379, 3, 'DEP', 'توقف اعتماد', 'DPF', 'اعتماد كامل', 'اعتماد كامل', 'توقف المعدة الأم — عطل ميكانيكي', 'DR-DEP-DPF-00-01', 1),
(380, 3, 'DEP', 'توقف اعتماد', 'DPP', 'اعتماد جزئي', 'اعتماد جزئي', 'تشغيل بطاقة منخفضة', 'DR-DEP-DPP-00-01', 1),
(381, 3, 'DEP', 'توقف اعتماد', 'DPM', 'اعتماد متبادل', 'اعتماد متبادل', 'توقف معدة شريكة', 'DR-DEP-DPM-00-01', 1),
(382, 3, 'CST', 'استعداد عميل', 'CSS', 'عدم جهوزية الموقع', 'عدم جهوزية الموقع', 'عدم جهوزية جبهة العمل', 'DR-CST-CSS-00-01', 1),
(383, 3, 'CST', 'استعداد عميل', 'CSP', 'عدم جهوزية الإنتاج', 'عدم جهوزية الإنتاج', 'انتظار التفجير', 'DR-CST-CSP-00-01', 1),
(384, 3, 'CST', 'استعداد عميل', 'CSP', 'عدم جهوزية الإنتاج', 'عدم جهوزية الإنتاج', 'انتظار المسّاح', 'DR-CST-CSP-00-02', 1),
(385, 3, 'CST', 'استعداد عميل', 'CSL', 'عدم توفر مستلزمات', 'عدم توفر مستلزمات', 'عدم وصول الوقود من جهة العميل', 'DR-CST-CSL-00-01', 1),
(386, 3, 'CST', 'استعداد عميل', 'CSM', 'إدارة العميل', 'إدارة العميل', 'عدم حضور مشرف العميل', 'DR-CST-CSM-00-01', 1),
(387, 3, 'CST', 'استعداد عميل', 'CSM', 'إدارة العميل', 'إدارة العميل', 'عدم صدور تصريح التشغيل اليومي', 'DR-CST-CSM-00-02', 1),
(388, 3, 'MST', 'استعداد تسويق', 'MSC', 'ظروف مناخية', 'ظروف مناخية', 'موسم الأمطار', 'DR-MST-MSC-00-01', 1),
(389, 3, 'MST', 'استعداد تسويق', 'MSC', 'ظروف مناخية', 'ظروف مناخية', 'العواصف الرملية', 'DR-MST-MSC-00-02', 1),
(390, 3, 'MST', 'استعداد تسويق', 'MSS', 'ظروف أمنية', 'ظروف أمنية', 'الوضع الأمني في المنطقة', 'DR-MST-MSS-00-01', 1),
(391, 3, 'MST', 'استعداد تسويق', 'MSZ', 'ظروف زمنية', 'ظروف زمنية', 'تخفيض ساعات رمضان', 'DR-MST-MSZ-00-02', 1),
(392, 3, 'HRF', 'عطل HR', 'HRA', 'عدم توفر المشغل', 'عدم توفر المشغل', 'عدم توفر مشغل في الموقع', 'DR-HRF-HRA-00-01', 1),
(393, 3, 'HRF', 'عطل HR', 'HRA', 'عدم توفر المشغل', 'عدم توفر المشغل', 'تأخر مشغل الوردية البديلة', 'DR-HRF-HRA-00-02', 1),
(394, 3, 'HRF', 'عطل HR', 'HRB', 'غياب المشغل', 'غياب المشغل', 'مرض المشغل دون بديل', 'DR-HRF-HRB-00-01', 1),
(395, 3, 'HRF', 'عطل HR', 'HRB', 'غياب المشغل', 'غياب المشغل', 'إجازة مشغل دون بديل', 'DR-HRF-HRB-00-02', 1),
(396, 3, 'HRF', 'عطل HR', 'HRB', 'غياب المشغل', 'غياب المشغل', 'غياب المشغل دون عذر', 'DR-HRF-HRB-00-03', 1),
(397, 3, 'HRF', 'عطل HR', 'HRC', 'عدم كفاءة المشغل', 'عدم كفاءة المشغل', 'نقص خبرة المشغل في نوع المعدة', 'DR-HRF-HRC-00-01', 1),
(398, 3, 'HRF', 'عطل HR', 'HRT', 'تطوير وتدريب', 'تطوير وتدريب', 'ساعات التدريب', 'DR-HRF-HRT-00-01', 1),
(399, 3, 'MKF', 'عطل تسويق', 'MFN', 'غياب العقد', 'غياب العقد', 'معدة بدون عقد', 'DR-MKF-MFN-00-01', 1),
(400, 3, 'MKF', 'عطل تسويق', 'MFN', 'غياب العقد', 'غياب العقد', 'فترة تجديد العقد', 'DR-MKF-MFN-00-02', 1),
(401, 3, 'MKF', 'عطل تسويق', 'MFI', 'إشكاليات تعاقدية', 'إشكاليات تعاقدية', 'توقف العقد لخلاف تعاقدي', 'DR-MKF-MFI-00-01', 1),
(402, 3, 'MKF', 'عطل تسويق', 'MFI', 'إشكاليات تعاقدية', 'إشكاليات تعاقدية', 'توقف بسبب تأخر مدفوعات العميل', 'DR-MKF-MFI-00-03', 1);

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL COMMENT 'المعرف الفريد',
  `company_id` int(11) NOT NULL COMMENT 'رقم الشركة - لعزل الرسائل بين الشركات',
  `sender_id` int(11) NOT NULL COMMENT 'رقم المرسل (users.id)',
  `receiver_id` int(11) NOT NULL COMMENT 'رقم المستلم (users.id)',
  `message` text NOT NULL COMMENT 'نص الرسالة',
  `is_read` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=غير مقروءة، 1=مقروءة',
  `read_at` datetime DEFAULT NULL COMMENT 'وقت القراءة',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'وقت الإرسال',
  `is_deleted_sender` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'حُذفت من قِبل المرسل',
  `is_deleted_receiver` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'حُذفت من قِبل المستلم'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='الرسائل الداخلية بين مستخدمي الشركة';

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `company_id`, `sender_id`, `receiver_id`, `message`, `is_read`, `read_at`, `created_at`, `is_deleted_sender`, `is_deleted_receiver`) VALUES
(1, 4, 13, 4, 'مرحبا', 1, '2026-04-29 11:30:03', '2026-04-29 06:37:16', 0, 0),
(2, 4, 12, 14, 'hi', 1, '2026-05-12 14:50:32', '2026-04-30 08:28:56', 0, 0),
(3, 4, 4, 6, 'مرحبا', 1, '2026-05-10 18:37:23', '2026-05-10 18:24:57', 0, 0),
(4, 4, 4, 6, 'ةسينم', 1, '2026-05-10 18:37:23', '2026-05-10 18:36:49', 0, 0),
(5, 4, 6, 4, 'مرحبا', 1, '2026-05-10 18:38:09', '2026-05-10 18:37:27', 0, 0),
(6, 4, 6, 11, 'مرحبا', 1, '2026-05-10 18:37:57', '2026-05-10 18:37:39', 0, 0),
(7, 4, 4, 5, 'ممم', 1, '2026-05-10 18:41:14', '2026-05-10 18:40:56', 0, 0),
(8, 4, 5, 4, 'ىننن', 1, '2026-05-11 02:54:47', '2026-05-10 18:41:38', 0, 0),
(9, 4, 5, 6, 'ىننن', 1, '2026-05-11 02:36:50', '2026-05-10 18:41:38', 0, 0),
(10, 4, 5, 7, 'ىننن', 1, '2026-05-12 22:40:27', '2026-05-10 18:41:38', 0, 0),
(11, 4, 5, 8, 'ىننن', 0, NULL, '2026-05-10 18:41:38', 0, 0),
(12, 4, 5, 9, 'ىننن', 0, NULL, '2026-05-10 18:41:38', 0, 0),
(13, 4, 5, 10, 'ىننن', 0, NULL, '2026-05-10 18:41:38', 0, 0),
(14, 4, 5, 11, 'ىننن', 1, '2026-05-11 02:52:43', '2026-05-10 18:41:38', 0, 0),
(15, 4, 5, 12, 'ىننن', 1, '2026-05-12 14:33:58', '2026-05-10 18:41:38', 0, 0),
(16, 4, 5, 13, 'ىننن', 1, '2026-05-10 18:41:55', '2026-05-10 18:41:38', 0, 0),
(17, 4, 5, 14, 'ىننن', 1, '2026-05-12 14:50:33', '2026-05-10 18:41:38', 0, 0),
(18, 4, 5, 15, 'ىننن', 0, NULL, '2026-05-10 18:41:38', 0, 0),
(19, 4, 5, 16, 'ىننن', 0, NULL, '2026-05-10 18:41:38', 0, 0),
(20, 4, 5, 17, 'ىننن', 0, NULL, '2026-05-10 18:41:38', 0, 0),
(21, 4, 5, 8, 'تمىم', 0, NULL, '2026-05-11 01:45:13', 0, 0),
(22, 4, 6, 5, 'و', 0, NULL, '2026-05-11 02:36:55', 0, 0),
(23, 4, 4, 5, 'mrk', 0, NULL, '2026-05-11 02:54:51', 0, 0),
(24, 4, 12, 5, 'و', 0, NULL, '2026-05-20 23:54:51', 0, 0),
(25, 4, 13, 4, 'tgl gt', 1, '2026-05-31 12:40:03', '2026-05-23 18:57:51', 0, 0),
(26, 4, 13, 14, 'tm gl', 0, NULL, '2026-05-23 18:57:55', 0, 0),
(27, 4, 13, 10, 'tgm', 0, NULL, '2026-05-23 18:58:02', 0, 0),
(28, 4, 13, 6, 'tmglt', 1, '2026-05-23 18:58:18', '2026-05-23 18:58:07', 0, 0),
(29, 4, 7, 4, 'vfmlk', 1, '2026-06-02 19:38:58', '2026-06-02 12:01:55', 0, 0),
(30, 4, 7, 5, 'vfmlk', 0, NULL, '2026-06-02 12:01:55', 0, 0),
(31, 4, 7, 6, 'vfmlk', 0, NULL, '2026-06-02 12:01:55', 0, 0),
(32, 4, 7, 8, 'vfmlk', 0, NULL, '2026-06-02 12:01:55', 0, 0),
(33, 4, 7, 9, 'vfmlk', 0, NULL, '2026-06-02 12:01:55', 0, 0),
(34, 4, 7, 10, 'vfmlk', 0, NULL, '2026-06-02 12:01:55', 0, 0),
(35, 4, 7, 11, 'vfmlk', 0, NULL, '2026-06-02 12:01:55', 0, 0),
(36, 4, 7, 12, 'vfmlk', 0, NULL, '2026-06-02 12:01:55', 0, 0),
(37, 4, 7, 13, 'vfmlk', 0, NULL, '2026-06-02 12:01:55', 0, 0),
(38, 4, 7, 14, 'vfmlk', 0, NULL, '2026-06-02 12:01:55', 0, 0),
(39, 4, 7, 17, 'vfmlk', 0, NULL, '2026-06-02 12:01:55', 0, 0),
(40, 4, 7, 18, 'vfmlk', 0, NULL, '2026-06-02 12:01:55', 0, 0),
(41, 4, 4, 7, 'نة', 0, NULL, '2026-06-02 19:39:00', 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `modules`
--

CREATE TABLE `modules` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `owner_role_id` int(11) DEFAULT NULL,
  `is_link` varchar(10) NOT NULL DEFAULT '0',
  `icon` varchar(50) NOT NULL,
  `display_order` int(11) DEFAULT 0 COMMENT 'ترتيب العرض في القوائم'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `modules`
--

INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `icon`, `display_order`) VALUES
(1, 'حالة العملاءjtgtj', 'Clients/clients.php', 1, '1', 'fa fa-users', 10),
(2, 'حالة المشاريع', 'Projects/projects.php', 1, '1', 'fa fa-folder-open', 20),
(3, 'إدارة الصلاحيات', 'main/users.php', 1, '1', 'fa fa-users-cog', 30),
(4, 'شاشة التقارير', 'Reports/reports.php', 1, '1', 'fa fa-chart-pie', 90),
(5, 'شاشة انواع المعدات', 'Equipments/equipments_types.php', 1, '1', 'fa fa-tractor', 50),
(6, 'شاشة التقارير', 'Reports/reports.php', 2, '1', 'fa fa-chart-pie', 60),
(7, 'علاقات المشغلين', 'Drivers/drivers.php', 4, '1', 'fa fa-id-card', 10),
(8, 'إدارة المعدات', 'Equipments/equipments_fleet.php', 3, '1', 'fa fa-tractor', 10),
(9, 'شاشة التشغيل', 'Oprators/oprators.php', 6, '0', 'fa fa-truck-moving', 90),
(10, 'تسجيل الوحدات', 'Timesheet/timesheet_type.php', 5, '1', 'fa fa-business-time', 100),
(11, 'الإعدادات', 'Settings/settings.php', 1, '1', 'fa fa-gear', 110),
(12, 'سجل النشاطات', 'ActivityLogs/activity_logs.php', 1, '1', 'fa fa-chart-line', 60),
(14, 'إدارة مشرفي الموردين', 'main/project_users.php', 2, '1', 'fa fa-users-cog', 20),
(15, 'المشرفين', 'main/project_users.php', 3, '1', 'fa fa-users-cog', 30),
(16, 'المشرفين', 'main/project_users.php', 4, '1', 'fa fa-users-cog', 30),
(17, 'إدارة المعاونين', 'main/project_users.php', 5, '1', 'fa fa-users-cog', 170),
(18, 'المشرفين', 'main/project_users.php', 6, '1', 'fa fa-users-cog', 180),
(20, 'إدارة العقود', 'Contracts/contracts.php', 1, '1', 'fa-file-signature fa', 200),
(21, 'تفاصيل عقد المشاريع', 'Contracts/contracts_details.php', 1, '0', '', 210),
(22, 'علاقات الموردين', 'Suppliers/suppliers.php', 2, '1', 'fa fa-truck-loading', 10),
(23, 'التشغيل', 'Oprators/select_project.php', 3, '1', 'fa fa-cogs', 20),
(24, 'الاعدادات', 'Settings/settings.php', 2, '0', 'fa fa-gear', 240),
(25, 'عقود الموردين', 'Suppliers/supplierscontracts.php', 2, '1', 'fa-file-signature fa', 250),
(26, 'تفاصيل عقد المورد', 'Suppliers/supplierscontracts_details.php', 2, '0', 'fa fa-link', 260),
(27, 'المعدات', 'Equipments/equipments_drivers.php', 4, '0', 'fa fa-tractor', 20),
(28, 'التقارير', 'Reports/reports.php', 5, '1', 'fa fa-link', 280),
(29, 'توزيع المشغلين', 'movement/project_drivers.php', 6, '1', 'fa fa-id-card', 80),
(30, 'تفعيل المعدات', 'movement/move_oprators.php', 6, '1', 'fa fa-tractor', 30),
(31, 'التقارير', 'Reports/reports.php', 4, '1', 'fa fa-chart-pie', 300),
(32, 'التقارير', 'Reports/reports.php', 3, '1', 'fa fa-chart-pie', 50),
(34, 'حالة المنجم', 'movement/map_page.php', 6, '1', 'fas fa-map-marked-alt', 20),
(35, 'إدارة العملاء', 'Clients/clients.php', 12, '1', 'fa fa-users', 1),
(36, 'إدارة المبيعات', 'Projects/projects.php', 12, '1', 'fa fa-folder-open', 2),
(37, 'إدارة العقود', 'Contracts/contracts.php', 12, '1', 'fa-file-signature fa', 3),
(38, 'تصنيف الاعطال', 'Equipments/manage_failure_codes.php', 3, '1', 'fa fa-screwdriver-wrench', 15);

-- --------------------------------------------------------

--
-- Table structure for table `operations`
--

CREATE TABLE `operations` (
  `id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `equipment` varchar(100) NOT NULL,
  `equipment_type` varchar(100) NOT NULL DEFAULT '0',
  `equipment_category` varchar(20) NOT NULL,
  `project_id` varchar(20) NOT NULL,
  `contract_id` varchar(10) NOT NULL,
  `supplier_id` varchar(10) NOT NULL,
  `start` varchar(50) NOT NULL,
  `end` varchar(50) NOT NULL,
  `reason` text NOT NULL,
  `days` varchar(20) NOT NULL,
  `total_equipment_hours` decimal(10,2) DEFAULT 0.00 COMMENT 'إجمالي ساعات العمل الكلية للآلية',
  `shift_hours` decimal(10,2) DEFAULT 0.00 COMMENT 'عدد ساعات الوردية للمعدة',
  `shift_type` enum('D','N','B') NOT NULL DEFAULT 'B',
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `operations`
--

INSERT INTO `operations` (`id`, `company_id`, `equipment`, `equipment_type`, `equipment_category`, `project_id`, `contract_id`, `supplier_id`, `start`, `end`, `reason`, `days`, `total_equipment_hours`, `shift_hours`, `shift_type`, `status`) VALUES
(1, 4, '1', '1', 'أساسي', '1', '1', '1', '2026-04-01', '2026-04-30', '', '0', 20.00, 10.00, 'B', 1),
(2, 4, '3', '2', 'أساسي', '1', '1', '1', '2026-04-01', '2026-04-30', '', '0', 20.00, 10.00, 'B', 1),
(3, 4, '5', '1', 'أساسي', '2', '2', '3', '2026-04-01', '2026-04-27', '', '26', 10.00, 10.00, 'B', 0),
(4, 4, '6', '1', 'أساسي', '2', '2', '3', '2026-04-01', '2006-04-30', '', '0', 10.00, 10.00, 'B', 1),
(5, 4, '7', '1', 'أساسي', '2', '2', '3', '2026-04-01', '2006-04-30', '', '0', 10.00, 10.00, 'B', 1),
(6, 4, '8', '1', 'أساسي', '2', '2', '3', '2026-04-01', '2026-04-28', '', '27', 10.00, 10.00, 'B', 0),
(7, 4, '9', '1', 'أساسي', '2', '2', '3', '2026-04-01', '2006-04-30', '', '0', 10.00, 10.00, 'B', 1),
(8, 4, '4', '1', 'أساسي', '2', '2', '3', '2026-04-25', '2026-10-01', '', '0', 600.00, 10.00, 'B', 1),
(9, 4, '4', '1', 'أساسي', '4', '4', '3', '2025-07-01', '2026-04-27', 'اكملت عملها\r\n', '300', 20.00, 10.00, 'B', 0),
(10, 4, '2', '1', 'أساسي', '4', '4', '1', '2025-07-01', '2026-07-01', '', '0', 0.00, 0.00, 'B', 0),
(11, 4, '8', '1', 'أساسي', '4', '4', '3', '2025-07-01', '2026-04-27', '', '300', 20.00, 10.00, 'B', 0),
(12, 4, '8', '1', 'أساسي', '4', '4', '4', '2026-04-01', '2026-07-01', '', '0', 20.00, 10.00, 'B', 1),
(13, 4, '10', '1', 'أساسي', '4', '5', '5', '2025-12-01', '2026-11-01', '', '0', 20.00, 10.00, 'B', 1),
(14, 4, '11', '2', 'أساسي', '4', '5', '6', '2025-12-01', '2026-11-01', '', '0', 20.00, 10.00, 'N', 1),
(15, 4, '5', '1', 'أساسي', '4', '4', '4', '2026-05-11', '2026-07-01', '', '0', 666.00, 10.00, 'N', 1),
(16, 4, '13', '3', 'أساسي', '4', '4', '4', '2026-05-11', '2026-07-01', '', '0', 555.00, 10.00, 'N', 1),
(17, 4, '15', '1', 'أساسي', '4', '4', '7', '2026-05-31', '2027-02-19', '', '0', 200.00, 10.00, 'N', 1);

-- --------------------------------------------------------

--
-- Table structure for table `project`
--

CREATE TABLE `project` (
  `id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL COMMENT '┘àÏ╣Ï▒┘ü Ïº┘äÏ╣┘à┘è┘ä ┘à┘å Ï¼Ï»┘ê┘ä clients',
  `name` varchar(150) NOT NULL,
  `client` varchar(150) NOT NULL,
  `location` varchar(200) NOT NULL,
  `project_code` varchar(50) DEFAULT NULL COMMENT 'كود المشروع',
  `mine_code` varchar(100) DEFAULT NULL COMMENT 'كود المنجم',
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
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'تاريخ آخر تحديث',
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `project`
--

INSERT INTO `project` (`id`, `company_id`, `client_id`, `name`, `client`, `location`, `project_code`, `mine_code`, `category`, `sub_sector`, `state`, `region`, `nearest_market`, `latitude`, `longitude`, `total`, `status`, `created_by`, `create_at`, `updated_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES
(1, 4, 1, 'مشروع الروسية', 'شركة إليانس للتعدين المحدودة', 'شمال', 'PR1', NULL, 'فئة المشروع', 'فرعي', 'نهر النيل', 'حفر الباطن', 'سوق اللفة', '12', '31', '0', 1, 4, '2026-04-07 11:46:05', '2026-04-25 12:04:52', 0, NULL, NULL),
(2, 4, 1, 'مشروع اليانس', 'شركة بايناتس', 'موقع اليانس', 'PR2', NULL, 'تعدين', 'تعدين', 'نهر النيل', 'حفر الباطن', 'سوق اللفة', '12', '31', '0', 1, 4, '2026-04-13 11:22:49', NULL, 0, NULL, NULL),
(3, 4, 1, 'مشروع الروسية', 'شركة إليانس للتعدين المحدودة', 'وادي العشار', 'PR2', NULL, 'مناجم', 'المنجم الأول', 'البحر الأحمر', 'وادي العشار', 'سوق وادي العشار', '12', '31', '0', 1, 4, '2026-04-25 11:36:48', NULL, 0, NULL, NULL),
(4, 4, 2, 'مشروع شركة إليانس للتعدين المحدودة', 'شركة إليانس للتعدين المحدودة', 'وادي العشار', 'CTR-094', 'a23a22', 'ساعات', 'المنجم الأول', 'البحر الأحمر', 'وادي العشار', 'سوق وادي العشار', '12', '31', '0', 1, 4, '2026-04-26 08:32:14', '2026-05-16 13:25:03', 0, NULL, NULL),
(5, 4, 2, 'مشروع المستر', 'شركة إليانس للتعدين المحدودة', 'الخرطوم2', 'Eq1', NULL, '', '', '', '', '', '', '', '0', 1, 13, '2026-05-01 11:50:47', NULL, 0, NULL, NULL),
(6, 4, NULL, 'مشروع طريق الإنقاذ الغربي', 'jjj', 'ولاية الخرطوم', 'PRJ-0001', NULL, 'بنية تحتية', NULL, 'الخرطوم', 'أمدرمان', NULL, NULL, NULL, '1000000', 1, 13, '2026-06-04 17:05:17', NULL, 0, NULL, NULL),
(7, 4, NULL, 'مشروع طريق الإنقاذ الغربي', 'jjj', 'ولاية الخرطوم', 'PRJ-0001-2', NULL, 'بنية تحتية', NULL, 'الخرطوم', 'أمدرمان', NULL, NULL, NULL, '1000000', 1, 13, '2026-06-04 17:05:17', NULL, 0, NULL, NULL),
(8, 4, NULL, 'مشروع طريق الإنقاذ الغربي', 'kk', 'ولاية الخرطوم', 'PRJ-0001-3', NULL, 'بنية تحتية', NULL, 'الخرطوم', 'أمدرمان', NULL, NULL, NULL, '1000000', 1, 13, '2026-06-04 17:05:17', NULL, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `report_role_permissions`
--

CREATE TABLE `report_role_permissions` (
  `id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `report_code` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `report_role_permissions`
--

INSERT INTO `report_role_permissions` (`id`, `role_id`, `report_code`) VALUES
(9, 1, 'contracts_detailed'),
(8, 1, 'contracts_summary'),
(21, 1, 'drivers_contracts'),
(19, 1, 'drivers_detailed'),
(18, 1, 'drivers_summary'),
(20, 1, 'drivers_timesheet'),
(15, 1, 'fleet_equipment_detailed'),
(14, 1, 'fleet_equipment_summary'),
(16, 1, 'fleet_operations'),
(17, 1, 'fleet_timesheet'),
(23, 1, 'operations_detailed'),
(22, 1, 'operations_summary'),
(7, 1, 'project_detailed'),
(6, 1, 'project_summary'),
(11, 1, 'supplier_contracts_detailed'),
(10, 1, 'supplier_contracts_summary'),
(13, 1, 'supplier_equipment_performance'),
(12, 1, 'supplier_timesheet'),
(5, 1, 'timesheet_by_driver'),
(4, 1, 'timesheet_by_equipment'),
(3, 1, 'timesheet_by_project'),
(2, 1, 'timesheet_detailed'),
(1, 1, 'timesheet_summary');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `parent_role_id` int(11) DEFAULT NULL,
  `level` int(11) DEFAULT 1,
  `role_scope` enum('gloable','mine') NOT NULL DEFAULT 'gloable',
  `status` varchar(10) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `parent_role_id`, `level`, `role_scope`, `status`, `created_at`) VALUES
(1, 'ادارة التشغيل', NULL, 1, 'gloable', '1', '2026-03-04 10:46:56'),
(2, 'ادارة الموردين', NULL, 1, 'gloable', '1', '2026-03-04 10:47:22'),
(3, 'ادارة الاسطول', NULL, 1, 'gloable', '1', '2026-03-04 10:47:41'),
(4, 'ادارة الموارد البشرية', NULL, 1, 'gloable', '1', '2026-03-04 10:50:24'),
(5, 'مدير الموقع', NULL, 1, 'mine', '1', '2026-03-04 10:52:29'),
(6, 'مدير حركة وتشغيل', NULL, 1, 'mine', '1', '2026-03-04 10:52:47'),
(7, 'مشرف - مشاريع', 1, 2, 'gloable', '1', '2026-03-04 13:18:15'),
(8, 'مشرف موردين', 2, 2, 'gloable', '1', '2026-03-04 13:34:07'),
(10, 'مشرف اسطول', 3, 2, 'gloable', '1', '2026-03-07 08:37:24'),
(11, 'مشغل اسطول', 3, 2, 'gloable', '1', '2026-03-09 09:45:51'),
(12, 'ادارة المبيعات', NULL, 1, 'gloable', '1', '2026-04-28 09:16:39');

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
(3, 7, 2, 1, 0, 0, 0),
(6, 7, 1, 1, 0, 0, 0),
(7, 7, 3, 1, 0, 0, 0),
(8, 7, 12, 1, 0, 0, 0),
(9, 7, 4, 1, 0, 0, 0),
(11, 7, 5, 1, 0, 0, 0),
(16, 7, 11, 1, 0, 0, 0),
(20, 1, 1, 1, 0, 0, 0),
(21, 1, 2, 1, 0, 0, 0),
(22, 1, 3, 1, 1, 1, 1),
(23, 1, 4, 1, 1, 1, 1),
(24, 1, 5, 1, 1, 1, 1),
(25, 1, 11, 1, 1, 1, 1),
(26, 1, 12, 1, 1, 1, 1),
(28, 1, 20, 1, 0, 0, 0),
(29, 1, 21, 1, 0, 0, 0),
(30, 1, 6, 1, 1, 1, 1),
(31, 1, 14, 1, 1, 1, 1),
(32, 1, 8, 1, 1, 1, 1),
(33, 1, 15, 1, 1, 1, 1),
(34, 1, 7, 1, 1, 1, 1),
(35, 1, 16, 1, 1, 1, 1),
(36, 1, 10, 1, 1, 1, 1),
(37, 1, 17, 1, 1, 1, 1),
(38, 1, 9, 1, 1, 1, 1),
(39, 1, 18, 1, 1, 1, 1),
(40, 7, 21, 1, 0, 0, 0),
(42, 7, 20, 1, 0, 0, 0),
(49, 2, 1, 1, 1, 1, 1),
(50, 2, 2, 1, 1, 1, 1),
(51, 2, 3, 1, 1, 1, 1),
(52, 2, 4, 1, 1, 1, 1),
(53, 2, 5, 1, 1, 1, 1),
(54, 2, 11, 1, 1, 1, 1),
(55, 2, 12, 1, 1, 1, 1),
(57, 2, 20, 1, 1, 1, 1),
(58, 2, 21, 1, 1, 1, 1),
(59, 2, 6, 1, 1, 1, 1),
(60, 2, 14, 1, 1, 0, 1),
(61, 2, 22, 1, 1, 1, 1),
(62, 2, 8, 1, 1, 1, 1),
(63, 2, 15, 1, 1, 1, 1),
(64, 2, 7, 1, 1, 1, 1),
(65, 2, 16, 1, 1, 1, 1),
(66, 2, 10, 1, 1, 1, 1),
(67, 2, 17, 1, 1, 1, 1),
(68, 2, 9, 1, 1, 1, 1),
(69, 2, 18, 1, 1, 1, 1),
(70, 8, 1, 1, 1, 1, 1),
(71, 8, 2, 1, 1, 1, 1),
(72, 8, 3, 1, 1, 1, 1),
(73, 8, 4, 1, 1, 1, 1),
(74, 8, 5, 1, 1, 1, 1),
(75, 8, 11, 1, 1, 1, 1),
(76, 8, 12, 1, 1, 1, 1),
(78, 8, 20, 1, 1, 1, 1),
(79, 8, 21, 1, 1, 1, 1),
(80, 8, 6, 1, 0, 1, 1),
(81, 8, 14, 0, 0, 0, 0),
(82, 8, 22, 1, 0, 1, 0),
(83, 8, 8, 1, 1, 1, 1),
(84, 8, 15, 1, 1, 1, 1),
(85, 8, 7, 1, 1, 1, 1),
(86, 8, 16, 1, 1, 1, 1),
(87, 8, 10, 1, 1, 1, 1),
(88, 8, 17, 1, 1, 1, 1),
(89, 8, 9, 1, 1, 1, 1),
(90, 8, 18, 1, 1, 1, 1),
(114, 3, 1, 1, 1, 1, 1),
(115, 3, 2, 1, 1, 1, 1),
(116, 3, 3, 1, 1, 1, 1),
(117, 3, 4, 1, 1, 1, 1),
(118, 3, 5, 1, 1, 1, 1),
(119, 3, 11, 1, 1, 1, 1),
(120, 3, 12, 1, 1, 1, 1),
(122, 3, 20, 1, 1, 1, 1),
(123, 3, 21, 1, 1, 1, 1),
(124, 3, 6, 1, 1, 1, 1),
(125, 3, 14, 1, 1, 1, 1),
(126, 3, 22, 1, 1, 1, 1),
(127, 3, 8, 1, 1, 1, 1),
(128, 3, 15, 1, 1, 0, 1),
(129, 3, 7, 1, 1, 1, 1),
(130, 3, 16, 1, 1, 1, 1),
(131, 3, 10, 1, 1, 1, 1),
(132, 3, 17, 1, 1, 1, 1),
(133, 3, 9, 1, 1, 1, 1),
(134, 3, 18, 1, 1, 1, 1),
(135, 10, 15, 1, 0, 1, 1),
(136, 10, 8, 1, 0, 0, 0),
(137, 3, 23, 1, 1, 1, 0),
(141, 11, 1, 1, 1, 1, 1),
(142, 11, 2, 1, 1, 1, 1),
(143, 11, 3, 1, 1, 1, 1),
(144, 11, 4, 1, 1, 1, 1),
(145, 11, 5, 1, 1, 1, 1),
(146, 11, 11, 1, 1, 1, 1),
(147, 11, 12, 1, 1, 1, 1),
(149, 11, 20, 1, 1, 1, 1),
(150, 11, 21, 1, 1, 1, 1),
(151, 11, 6, 1, 1, 1, 1),
(152, 11, 14, 1, 1, 1, 1),
(153, 11, 22, 1, 1, 1, 1),
(154, 11, 8, 1, 1, 1, 0),
(155, 11, 15, 1, 1, 1, 1),
(156, 11, 23, 1, 1, 1, 1),
(157, 11, 7, 1, 1, 1, 1),
(158, 11, 16, 1, 1, 1, 1),
(159, 11, 10, 1, 1, 1, 1),
(160, 11, 17, 1, 1, 1, 1),
(161, 11, 9, 1, 1, 1, 1),
(162, 11, 18, 1, 1, 1, 1),
(163, 2, 24, 1, 1, 1, 0),
(164, 8, 24, 1, 0, 0, 0),
(165, 2, 25, 1, 1, 1, 1),
(166, 8, 25, 1, 1, 1, 0),
(167, 2, 26, 1, 1, 1, 1),
(168, 8, 26, 1, 0, 0, 0),
(169, 6, 1, 1, 1, 1, 1),
(170, 6, 2, 1, 1, 1, 1),
(171, 6, 3, 1, 1, 1, 1),
(172, 6, 4, 1, 1, 1, 1),
(173, 6, 5, 1, 1, 1, 1),
(174, 6, 11, 1, 1, 1, 1),
(175, 6, 12, 1, 1, 1, 1),
(177, 6, 20, 1, 1, 1, 1),
(178, 6, 21, 1, 1, 1, 1),
(179, 6, 6, 1, 1, 1, 1),
(180, 6, 14, 1, 1, 1, 1),
(181, 6, 22, 1, 1, 1, 1),
(182, 6, 24, 1, 1, 1, 1),
(183, 6, 25, 1, 1, 1, 1),
(184, 6, 26, 1, 1, 1, 1),
(185, 6, 8, 1, 1, 1, 1),
(186, 6, 15, 1, 1, 1, 1),
(187, 6, 23, 1, 1, 1, 1),
(188, 6, 7, 1, 1, 1, 1),
(189, 6, 16, 1, 1, 1, 1),
(190, 6, 10, 1, 1, 1, 1),
(191, 6, 17, 1, 1, 1, 1),
(192, 6, 9, 1, 1, 1, 1),
(193, 6, 18, 1, 1, 1, 1),
(194, 4, 1, 1, 1, 1, 1),
(195, 4, 2, 1, 1, 1, 1),
(196, 4, 3, 1, 1, 1, 1),
(197, 4, 4, 1, 1, 1, 1),
(198, 4, 5, 1, 1, 1, 1),
(199, 4, 11, 1, 1, 1, 1),
(200, 4, 12, 1, 1, 1, 1),
(202, 4, 20, 1, 1, 1, 1),
(203, 4, 21, 1, 1, 1, 1),
(204, 4, 6, 1, 1, 1, 1),
(205, 4, 14, 1, 1, 1, 1),
(206, 4, 22, 1, 1, 1, 1),
(207, 4, 24, 1, 1, 1, 1),
(208, 4, 25, 1, 1, 1, 1),
(209, 4, 26, 1, 1, 1, 1),
(210, 4, 8, 1, 1, 1, 1),
(211, 4, 15, 1, 1, 1, 1),
(212, 4, 23, 1, 1, 1, 1),
(213, 4, 7, 1, 1, 1, 1),
(214, 4, 16, 1, 1, 1, 1),
(215, 4, 10, 1, 1, 1, 1),
(216, 4, 17, 1, 1, 1, 1),
(217, 4, 9, 1, 1, 1, 1),
(218, 4, 18, 1, 1, 1, 1),
(219, 4, 27, 1, 1, 1, 1),
(221, 5, 1, 1, 1, 1, 1),
(222, 5, 2, 1, 1, 1, 1),
(223, 5, 3, 1, 1, 1, 1),
(224, 5, 4, 1, 1, 1, 1),
(225, 5, 5, 1, 1, 1, 1),
(226, 5, 11, 1, 1, 1, 1),
(227, 5, 12, 1, 1, 1, 1),
(229, 5, 20, 1, 1, 1, 1),
(230, 5, 21, 1, 1, 1, 1),
(231, 5, 6, 1, 1, 1, 1),
(232, 5, 14, 1, 1, 1, 1),
(233, 5, 22, 1, 1, 1, 1),
(234, 5, 24, 1, 1, 1, 1),
(235, 5, 25, 1, 1, 1, 1),
(236, 5, 26, 1, 1, 1, 1),
(237, 5, 8, 1, 1, 1, 1),
(238, 5, 15, 1, 1, 1, 1),
(239, 5, 23, 1, 1, 1, 1),
(240, 5, 7, 1, 1, 1, 1),
(241, 5, 16, 1, 1, 1, 1),
(242, 5, 27, 1, 1, 1, 1),
(243, 5, 10, 1, 1, 1, 1),
(244, 5, 17, 1, 1, 1, 1),
(245, 5, 28, 1, 1, 1, 1),
(246, 5, 9, 1, 1, 1, 1),
(247, 5, 18, 1, 1, 1, 1),
(248, 6, 30, 1, 0, 1, 1),
(249, 6, 29, 1, 1, 1, 1),
(250, 6, 34, 1, 1, 1, 1),
(251, 12, 1, 1, 1, 1, 1),
(252, 12, 2, 1, 1, 1, 1),
(253, 12, 3, 1, 1, 1, 1),
(254, 12, 4, 1, 1, 1, 1),
(255, 12, 5, 1, 1, 1, 1),
(256, 12, 11, 1, 1, 1, 1),
(257, 12, 12, 1, 1, 1, 1),
(259, 12, 20, 1, 1, 1, 1),
(260, 12, 21, 1, 1, 1, 1),
(261, 12, 6, 1, 1, 1, 1),
(262, 12, 14, 1, 1, 1, 1),
(263, 12, 22, 1, 1, 1, 1),
(264, 12, 24, 1, 1, 1, 1),
(265, 12, 25, 1, 1, 1, 1),
(266, 12, 26, 1, 1, 1, 1),
(267, 12, 8, 1, 1, 1, 1),
(268, 12, 15, 1, 1, 1, 1),
(269, 12, 23, 1, 1, 1, 1),
(270, 12, 32, 1, 1, 1, 1),
(271, 12, 7, 1, 1, 1, 1),
(272, 12, 16, 1, 1, 1, 1),
(273, 12, 27, 1, 1, 1, 1),
(274, 12, 31, 1, 1, 1, 1),
(275, 12, 10, 1, 1, 1, 1),
(276, 12, 17, 1, 1, 1, 1),
(277, 12, 28, 1, 1, 1, 1),
(278, 12, 9, 1, 1, 1, 1),
(279, 12, 18, 1, 1, 1, 1),
(280, 12, 29, 1, 1, 1, 1),
(281, 12, 30, 1, 1, 1, 1),
(282, 12, 34, 1, 1, 1, 1),
(283, 12, 35, 1, 1, 1, 1),
(284, 12, 36, 1, 1, 1, 1),
(285, 12, 37, 1, 1, 1, 1),
(286, 3, 38, 1, 1, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `super_admins`
--

CREATE TABLE `super_admins` (
  `id` int(11) NOT NULL COMMENT 'معرف فريد',
  `name` varchar(100) NOT NULL COMMENT 'الإسم',
  `email` varchar(150) NOT NULL COMMENT 'البريد ',
  `password` varchar(255) NOT NULL COMMENT 'كلمة المرور',
  `is_active` tinyint(4) NOT NULL DEFAULT 1 COMMENT 'نشط',
  `last_login_at` timestamp NULL DEFAULT NULL COMMENT 'آخر دخول',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'انشاء في',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'تعديل في'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `super_admins`
--

INSERT INTO `super_admins` (`id`, `name`, `email`, `password`, `is_active`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'super', 'enjaz@gmail.com', '$2y$10$auVJYb4WXFejEfthvqjpSOtyZlfdzJxM18TH6NBhPvPMyNMPq0B8K', 1, '2026-06-02 09:41:43', '2026-03-18 11:49:17', '2026-06-02 09:41:43');

-- --------------------------------------------------------

--
-- Table structure for table `suppliercontractequipments`
--

CREATE TABLE `suppliercontractequipments` (
  `id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
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

INSERT INTO `suppliercontractequipments` (`id`, `company_id`, `contract_id`, `equip_type`, `equip_size`, `equip_count`, `equip_count_basic`, `equip_count_backup`, `equip_shifts`, `equip_unit`, `shift1_start`, `shift1_end`, `shift2_start`, `shift2_end`, `shift_hours`, `equip_total_month`, `equip_monthly_target`, `equip_total_contract`, `equip_price`, `equip_price_currency`, `equip_operators`, `equip_supervisors`, `equip_technicians`, `equip_assistants`, `created_at`) VALUES
(1, NULL, 1, '1', 200, 5, 3, 2, 2, 'ساعة', '14:21:00', '14:21:00', '14:22:00', '14:22:00', 10.00, 50.00, 6000.00, 1000.00, 10.00, '', 3, 3, 3, 3, '2026-04-07 12:22:54'),
(2, NULL, 1, '2', 300, 5, 3, 2, 2, 'ساعة', '14:22:00', '14:23:00', '14:24:00', '14:25:00', 10.00, 50.00, 6000.00, 1000.00, 10.00, 'دولار', 0, 3, 3, 3, '2026-04-07 12:22:54'),
(3, NULL, 2, '1', 54, 5, 3, 2, 4, 'ساعة', '23:13:00', '21:36:00', '16:00:00', '17:47:00', 10.00, 50.00, 1.00, 2300.00, 858.00, 'دولار', 87, 37, 66, 59, '2026-04-13 13:19:02'),
(8, NULL, 4, '1', 340, 1, 1, 0, 2, 'ساعة', '05:00:00', '15:00:00', '17:00:00', '03:00:00', 10.00, 10.00, 600.00, 1510.00, 20.00, 'دولار', 2, 1, 1, 1, '2026-04-28 10:46:57'),
(9, NULL, 5, '2', 25, 1, 1, 1, 2, 'ساعة', '05:00:00', '15:00:00', '17:00:00', '03:00:00', 20.00, 20.00, 600.00, 3000.00, 8.00, '', 2, 1, 1, 1, '2026-04-28 10:52:36'),
(11, NULL, 6, '3', 340, 1, 1, 0, 2, 'متر طولي', '18:00:00', '04:00:00', '06:00:00', '16:00:00', 10.00, 10.00, 0.00, 900.00, 50.00, 'دولار', 2, 0, 0, 1, '2026-05-07 06:40:28'),
(14, NULL, 7, '1', 56, 5, 3, 0, 2, '', '00:00:00', '00:00:00', '00:00:00', '00:00:00', 20.00, 60.00, 0.00, 3480.00, 0.00, '', 0, 0, 0, 0, '2026-05-12 12:08:27');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
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
  `status` tinyint(1) DEFAULT 1,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `company_id`, `name`, `supplier_code`, `supplier_type`, `dealing_nature`, `equipment_types`, `commercial_registration`, `identity_type`, `identity_number`, `identity_expiry_date`, `email`, `phone_alternative`, `full_address`, `contact_person_name`, `contact_person_phone`, `financial_registration_status`, `created_at`, `updated_at`, `phone`, `status`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES
(1, 4, 'مورد 1', 'MOR1', 'فرد', 'متعاقد مباشر', 'حفارات, مكنات تخريم', '12345678', 'بطاقة هوية وطنية', '839', '2026-04-30', 'sudanit2015@gmail.com', '+1 (454) 678-6091', 'عنوان كامل', 'Naomi Wilcox', '+1 (628) 682-7512', '', '2026-04-07 11:53:32', '2026-04-07 12:18:47', '0115667710', 1, 0, NULL, NULL),
(2, 4, 'مورد 2', 'MOR2', 'شركة', 'وسيط', 'حفارات', '123', 'جواز سفر', 'P98909', '2026-04-30', 'equipation@gmail.com', '+1 (161) 121-1423', 'عنوان', 'Mary Washington', '+1 (584) 739-3927', '', '2026-04-07 11:54:24', '2026-05-12 12:04:31', '09144760109', 0, 1, '2026-05-12 15:04:31', 5),
(3, 4, 'إيكوبيشن', 'MOR3', 'فرد', 'متعاقد مباشر', 'حفارات', '', '', '', NULL, 'sudan@gmail.com', '', '', '', '', '', '2026-04-13 11:24:53', '2026-04-13 11:24:53', '0915657576', 1, 0, NULL, NULL),
(4, 4, 'HASAN KEHYRI', 'MOR001', 'شركة', 'متعاقد مباشر', 'حفارات, مكنات تخريم, دوازر, شاحنات قلابة, شاحنات تناكر, جرافات, معدات معالجة', '65562', 'رقم تسجيل تجاري', '24522', '2026-12-25', 'infotelecomwasla@gmail.com', '249912345678', '', 'خيري كمال خيري', '123456789', '', '2026-04-26 10:22:48', '2026-05-10 22:47:03', '249912345678', 1, 0, NULL, NULL),
(5, 4, 'شركة صابركو للإنشاءات الهندسية والمقاولات المحدودة', 'MOR002', 'شركة', 'متعاقد مباشر', 'حفارات, شاحنات قلابة', '12345', 'رقم تسجيل تجاري', '12345', '2025-11-25', 'sudan@gmail.com', '564987123', '', 'محمد صابر طه محمد', '123654789', '', '2026-04-28 10:36:42', '2026-04-28 10:36:42', '987654321', 1, 0, NULL, NULL),
(6, 4, 'عمر هاشم فضل المولى احمد', 'MOR003', 'فرد', 'متعاقد مباشر', 'شاحنات قلابة', '44444', 'رخصة عمل', '564646', '2026-05-09', 'sudan@gmail.com', '8765312632', '', 'عمر هاشم فضل المولى احمد', '85623151313', '', '2026-04-28 10:49:08', '2026-04-28 10:49:08', '5453533513', 1, 0, NULL, NULL),
(7, 4, 'شوقي عبدالعظيم أحمد الخضر', 'MOR004', 'فرد', 'متعاقد مباشر', 'مكنات تخريم', '44444', 'بطاقة هوية وطنية', '564646', '2026-05-09', 'sudan@gmail.com', '546546513', '', 'شوقي عبدالعظيم أحمد الخضر', '85623151313', '', '2026-04-28 10:53:55', '2026-04-28 10:53:55', '31232133', 1, 0, NULL, NULL),
(8, 4, 'شركة إكوبيشن للإستثمار المحدودة', 'MOR006', 'شركة', 'متعاقد مباشر', 'حفارات, مكنات تخريم, دوازر, شاحنات قلابة, شاحنات تناكر, جرافات, معدات معالجة, السيارات والكرفانات', '65562', 'رقم تسجيل تجاري', '24522', '2026-05-21', 'sudan@gmail.com', '8765312632', '', 'خيري كمال خيري', '85623151313', '', '2026-05-07 06:18:16', '2026-05-11 12:40:45', '249912345678', 1, 0, NULL, NULL),
(9, 4, 'احمد', 'ro', 'فرد', 'متعاقد مباشر', 'حفارات', '', '', '', NULL, '', '', '', '', '', '', '2026-05-14 15:28:58', '2026-05-14 15:28:58', '898', 1, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `supplierscontracts`
--

CREATE TABLE `supplierscontracts` (
  `id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
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

INSERT INTO `supplierscontracts` (`id`, `company_id`, `supplier_id`, `contract_signing_date`, `grace_period_days`, `contract_duration_months`, `contract_duration_days`, `equip_shifts_contract`, `shift_contract`, `equip_total_contract_daily`, `total_contract_permonth`, `total_contract_units`, `actual_start`, `actual_end`, `transportation`, `accommodation`, `place_for_living`, `workshop`, `equip_type`, `equip_size`, `equip_count`, `equip_target_per_month`, `equip_total_month`, `equip_total_contract`, `mach_type`, `mach_size`, `mach_count`, `mach_target_per_month`, `mach_total_month`, `mach_total_contract`, `hours_monthly_target`, `forecasted_contracted_hours`, `created_at`, `updated_at`, `daily_work_hours`, `daily_operators`, `first_party`, `second_party`, `witness_one`, `witness_two`, `price_currency_contract`, `paid_contract`, `payment_time`, `guarantees`, `payment_date`, `project_id`, `project_contract_id`, `status`, `pause_reason`, `pause_date`, `resume_date`, `termination_type`, `termination_reason`, `merged_with`) VALUES
(1, 4, 1, '2026-04-01', 10, 0, 21, 2, 10, 100, 6000, 6000, '2026-04-10', '2026-05-01', 'مالك المعدة', 'مالك المعدة', 'مالك المعدة', 'مالك المعدة', NULL, NULL, 0, 0, 0, 0, NULL, NULL, 0, 0, 0, 0, 100, 2000, '2026-04-07 12:22:54', '2026-04-25 10:58:15', '20', '3', 'محمد سيد', 'سمير الو الليل', 'سمر الهاني', 'هاني المحامي', 'دولار', '1000', 'مقدم', 'رهن سيارة', '2026-04-20', 1, 1, 1, NULL, '2026-04-25', '2026-04-26', NULL, NULL, NULL),
(2, 4, 3, '2026-04-01', 10, 0, 21, 70, 88, 87, 6, 3, '2026-04-10', '2026-04-30', 'مالك المشروع', 'مالك المعدة', 'مالك المشروع', 'مالك المعدة', NULL, NULL, 0, 0, 0, 0, NULL, NULL, 0, 0, 0, 0, 50, 2300, '2026-04-13 13:19:02', NULL, '20', '36', 'Pariatur Debitis ex', 'Nemo debitis eveniet', 'Non maiores inventor', 'Culpa nemo nisi nih', 'دولار', 'Deleniti est qui nih', 'مقدم', 'Magna quam id delen', '1995-05-14', 2, 2, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 4, 5, '2025-11-25', 5, 0, 151, 2, 10, 20, 600, 3000, '2025-12-01', '2026-04-30', 'مالك المعدة', 'مالك المشروع', 'مالك المشروع', 'مالك المعدة', NULL, NULL, 0, 0, 0, 0, NULL, NULL, 0, 0, 0, 0, 10, 1510, '2026-04-28 10:45:08', '2026-04-28 10:46:57', '20', '1', 'شركة إكوبيشن للإستثمار المحدودة', 'شركة صابركو للإنشاءات الهندسية والمقاولات المحدودة ', 'محمد فيصل محمد صابر', 'يس سيدأحمد محمدالأمين الحسن', 'دولار', '', '', '', '0000-00-00', 4, 5, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 4, 6, '2025-11-26', 4, 0, 151, 2, 10, 20, 600, 3000, '2025-12-01', '2026-04-30', 'مالك المعدة', 'مالك المشروع', 'مالك المشروع', 'مالك المعدة', NULL, NULL, 0, 0, 0, 0, NULL, NULL, 0, 0, 0, 0, 20, 3000, '2026-04-28 10:52:36', NULL, '20', '1', 'شركة إكوبيشن للإستثمار المحدودة', 'عمر هشام فضل المولى أحمد', 'محمد فيصل محمد صابر', 'يس سيدأحمد محمدالأمين الحسن', 'دولار', '', '', '', '0000-00-00', 4, 5, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(6, 4, 8, '2026-04-26', 5, 0, 90, 2, 10, 20, 600, 1800, '2026-05-04', '2026-08-01', 'مالك المعدة', 'مالك المشروع', 'مالك المشروع', 'مالك المعدة', NULL, NULL, 0, 0, 0, 0, NULL, NULL, 0, 0, 0, 0, 10, 900, '2026-05-07 06:22:38', '2026-05-07 06:40:28', '20', '1', '', '', '', '', 'دولار', '', ' مؤخر', '', '2026-08-08', 4, 5, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(7, 4, 7, '2026-05-01', 2, 1, 36, 0, 0, 0, 0, 0, '2026-07-09', '2026-08-14', '', '', '', '', NULL, NULL, 0, 0, 0, 0, NULL, NULL, 0, 0, 0, 0, 60, 3480, '2026-05-12 12:08:27', '2026-05-13 08:56:16', '20', '0', '', '', '', '', 'دولار', '2000', 'مقدم', '', '0000-00-00', 4, 4, 1, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `supplier_contract_notes`
--

CREATE TABLE `supplier_contract_notes` (
  `id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `contract_id` int(11) NOT NULL,
  `note` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supplier_contract_notes`
--

INSERT INTO `supplier_contract_notes` (`id`, `company_id`, `contract_id`, `note`, `created_at`, `created_by`) VALUES
(1, NULL, 1, 'تم إيقاف العقد بتاريخ 2026-04-25 - السبب: تعذر سداد', '2026-04-25 10:57:56', NULL),
(2, NULL, 1, 'تم استئناف العقد بتاريخ 2026-04-26 - مدة الإيقاف: 1 يوم (تم تمديد العقد بإضافة 1 يوم إلى تاريخ الانتهاء)', '2026-04-25 10:58:15', NULL),
(3, NULL, 7, 'تم تجديد العقد من 2026-07-09 إلى 2026-08-14 (مدة: 1 شهور / 36 يوم)', '2026-05-13 11:56:16', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `timesheet`
--

CREATE TABLE `timesheet` (
  `id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
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
  `tons_count` decimal(10,2) DEFAULT 0.00 COMMENT 'عدد الأطنان - للنوع 2 (القلاب)',
  `trips_count` int(11) DEFAULT 0 COMMENT 'عدد النقلات - للنوع 2 (القلاب)',
  `transport_type` varchar(50) DEFAULT NULL,
  `meters_type` varchar(50) DEFAULT NULL COMMENT 'نوع الأمتار - للنوع 3 (الخرمات)',
  `meters_count` decimal(10,2) DEFAULT 0.00 COMMENT 'عدد الأمتار - للنوع 3 (الخرمات)',
  `drilling_holes_count` int(11) DEFAULT 0,
  `drilling_depth` decimal(10,2) DEFAULT 0.00,
  `type` varchar(20) NOT NULL,
  `user_id` int(50) NOT NULL DEFAULT 0,
  `time_notes` text NOT NULL DEFAULT 'لاتوجد ملاحظات',
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `timesheet`
--

INSERT INTO `timesheet` (`id`, `company_id`, `operator`, `driver`, `shift`, `date`, `shift_hours`, `executed_hours`, `bucket_hours`, `jackhammer_hours`, `extra_hours`, `extra_hours_total`, `standby_hours`, `dependence_hours`, `total_work_hours`, `work_notes`, `hr_fault`, `maintenance_fault`, `marketing_fault`, `approval_fault`, `other_fault_hours`, `total_fault_hours`, `fault_notes`, `start_seconds`, `start_minutes`, `start_hours`, `end_seconds`, `end_minutes`, `end_hours`, `counter_diff`, `fault_type`, `fault_department`, `fault_part`, `fault_details`, `general_notes`, `operator_hours`, `machine_standby_hours`, `jackhammer_standby_hours`, `bucket_standby_hours`, `extra_operator_hours`, `operator_standby_hours`, `operator_notes`, `tons_count`, `trips_count`, `transport_type`, `meters_type`, `meters_count`, `drilling_holes_count`, `drilling_depth`, `type`, `user_id`, `time_notes`, `status`) VALUES
(34, 4, '5', '8', 'D', '2026-10-01', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(35, 4, '5', '9', 'N', '2026-10-01', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(36, 4, '5', '8', 'D', '2026-10-02', 10, 7, 0, 0, 0, 0, 3, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '7 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 7, 3, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(37, 4, '5', '12', 'N', '2026-10-02', 10, 9, 0, 0, 0, 0, 1, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '9 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 9, 1, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(38, 4, '5', '8', 'D', '2026-10-03', 10, 7, 0, 0, 0, 0, 3, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '7 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 7, 3, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(39, 4, '5', '8', 'N', '2026-10-03', 10, 8, 0, 0, 0, 0, 1, 0, 9, '', 1, 0, 0, 0, 0, 1, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 8, 1, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(40, 4, '5', '8', 'D', '2026-10-04', 10, 6, 0, 0, 0, 0, 4, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '6 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 6, 4, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(41, 4, '5', '12', 'N', '2026-10-04', 10, 9, 0, 0, 0, 0, 1, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '9 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 9, 1, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(42, 4, '5', '8', 'D', '2026-10-05', 10, 7, 0, 0, 0, 0, 3, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '7 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 7, 3, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(43, 4, '5', '12', 'N', '2026-10-05', 10, 9, 0, 0, 0, 0, 1, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '9 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 9, 1, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(44, 4, '5', '11', 'D', '2026-10-06', 10, 7, 0, 0, 0, 0, 3, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '7 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 7, 3, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(45, 4, '5', '8', 'N', '2026-10-06', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '17 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(46, 4, '5', '9', 'D', '2026-10-07', 10, 7, 0, 0, 0, 0, 3, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '7 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 7, 3, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(47, 4, '5', '10', 'N', '2026-10-07', 10, 9, 0, 0, 0, 0, 1, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '9 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 9, 1, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(48, 4, '5', '9', 'D', '2026-10-08', 10, 6, 0, 0, 0, 0, 4, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '6 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 6, 4, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(49, 4, '5', '10', 'N', '2026-10-08', 10, 9, 0, 0, 0, 0, 1, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '9 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 9, 1, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(50, 4, '5', '11', 'D', '2026-10-09', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(51, 4, '5', '12', 'N', '2026-10-09', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(52, 4, '5', '12', 'D', '2026-10-10', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(53, 4, '5', '8', 'N', '2026-10-10', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(54, 4, '5', '12', 'D', '2026-10-11', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(55, 4, '5', '11', 'N', '2026-10-11', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(56, 4, '5', '', 'D', '2026-10-12', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(57, 4, '5', '12', 'N', '2026-10-12', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(58, 4, '5', '10', 'D', '2026-10-13', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(59, 4, '5', '8', 'N', '2026-10-13', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(60, 4, '5', '8', 'D', '2026-10-14', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(61, 4, '5', '10', 'N', '2026-10-14', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(62, 4, '5', '9', 'D', '2026-10-15', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(63, 4, '5', '11', 'N', '2026-10-15', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(64, 4, '5', '10', 'D', '2026-10-16', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(65, 4, '5', '10', 'N', '2026-10-16', 10, 2, 0, 0, 0, 0, 0.5, 0, 2.5, '', 0, 7.5, 0, 0, 0, 7.5, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 2, 0.5, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(66, 4, '5', '10', 'D', '2026-10-17', 10, 6, 0, 0, 0, 0, 3, 1, 9, '', 0, 0, 0, 0, 0, 1, 'لعدم توفر قلابات', 0, 0, 0, 0, 0, 0, '6 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 6, 3, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(67, 4, '5', '8', 'N', '2026-10-17', 10, 5, 0, 0, 0, 0, 0, 0, 5, '', 5, 0, 0, 0, 0, 5, '', 0, 0, 0, 0, 0, 0, '5 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 5, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(68, 4, '5', '9', 'D', '2026-10-18', 10, 7, 0, 0, 0, 0, 3, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '7 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 7, 3, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(69, 4, '5', '9', 'N', '2026-10-18', 10, 9, 0, 0, 0, 0, 1, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '9 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 9, 1, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(70, 4, '5', '11', 'D', '2026-10-19', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(71, 4, '5', '12', 'N', '2026-10-19', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(72, 4, '5', '10', 'D', '2026-10-20', 10, 8, 0, 0, 0, 0, 1.5, 0, 9.5, '', 0.5, 0, 0, 0, 0, 0.5, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 8, 1.5, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(73, 4, '5', '8', 'N', '2026-10-20', 10, 9, 0, 0, 0, 0, 1, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '9 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 9, 1, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(74, 4, '5', '8', 'D', '2026-10-21', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(75, 4, '5', '11', 'N', '2026-10-21', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(76, 4, '5', '9', 'D', '2026-10-22', 10, 4, 0, 0, 0, 0, 1, 0, 5, '', 0, 5, 0, 0, 0, 5, '', 0, 0, 0, 0, 0, 0, '4 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 4, 1, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(77, 4, '5', '12', 'N', '2026-10-22', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(78, 4, '5', '10', 'D', '2026-10-23', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(79, 4, '5', '11', 'N', '2026-10-23', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(80, 4, '5', '11', 'D', '2026-10-24', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(81, 4, '5', '9', 'N', '2026-10-24', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(82, 4, '5', '12', 'D', '2026-10-25', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(83, 4, '5', '12', 'N', '2026-10-25', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(84, 4, '5', '12', 'D', '2026-10-26', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(85, 4, '5', '9', 'N', '2026-10-26', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(86, 4, '5', '9', 'D', '2026-10-27', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(87, 4, '5', '8', 'N', '2026-10-27', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(88, 4, '5', '8', 'D', '2026-10-28', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(89, 4, '5', '9', 'N', '2026-10-28', 10, 5, 0, 0, 0, 0, 1.5, 0, 6.5, '', 0, 3.5, 0, 0, 0, 3.5, '', 0, 0, 0, 0, 0, 0, '9 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 5, 1.5, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(90, 4, '5', '11', 'D', '2026-10-29', 10, 7, 0, 0, 0, 0, 3, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '7 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 7, 3, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(91, 4, '5', '11', 'N', '2026-10-29', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(92, 4, '5', '11', 'D', '2026-10-30', 10, 7, 0, 0, 0, 0, 2.5, 0, 9.5, '', 0.5, 0, 0, 0, 0, 0.5, '', 0, 0, 0, 0, 0, 0, '7 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 7, 2.5, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(93, 4, '5', '12', 'N', '2026-10-30', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(94, 4, '5', '8', 'D', '2026-10-31', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(95, 4, '5', '8', 'N', '2026-10-31', 10, 8, 0, 0, 0, 0, 1, 0, 9, '', 1, 0, 0, 0, 0, 1, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 8, 1, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(96, 4, '5', '12', 'D', '2026-11-01', 10, 7, 0, 0, 0, 0, 3, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '7 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 7, 3, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(97, 4, '5', '10', 'N', '2026-11-01', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(98, 4, '5', '11', 'D', '2026-11-02', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(99, 4, '5', '8', 'N', '2026-11-02', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(100, 4, '5', '10', 'D', '2026-11-03', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(101, 4, '5', '9', 'N', '2026-11-03', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(102, 4, '5', '8', 'D', '2026-11-04', 10, 7, 0, 0, 0, 0, 3, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '7 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 7, 3, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(103, 4, '5', '10', 'N', '2026-11-04', 10, 9, 0, 0, 0, 0, 1, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '9 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 9, 1, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(104, 4, '5', '12', 'D', '2026-11-05', 10, 7, 0, 0, 0, 0, 3, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '7 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 7, 3, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(105, 4, '5', '9', 'N', '2026-11-05', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(106, 4, '5', '9', 'D', '2026-11-06', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(107, 4, '5', '12', 'N', '2026-11-06', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(108, 4, '5', '12', 'D', '2026-11-07', 10, 8, 0, 0, 0, 0, 1, 0, 9, '', 1, 0, 0, 0, 0, 1, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 8, 1, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(109, 4, '5', '8', 'N', '2026-11-07', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(110, 4, '5', '10', 'D', '2026-11-08', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(111, 4, '5', '10', 'N', '2026-11-08', 10, 9, 0, 0, 0, 0, 1, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '9 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 9, 1, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(112, 4, '5', '9', 'D', '2026-11-09', 10, 7, 0, 0, 0, 0, 3, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '7 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 7, 3, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(113, 4, '5', '8', 'N', '2026-11-09', 10, 9, 0, 0, 0, 0, 1, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '9 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 9, 1, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(114, 4, '5', '10', 'D', '2026-11-10', 10, 7, 0, 0, 0, 0, 3, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '7 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 7, 3, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(115, 4, '5', '8', 'N', '2026-11-10', 10, 9, 0, 0, 0, 0, 1, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '9 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 9, 1, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(116, 4, '5', '12', 'D', '2026-11-11', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 10, 0, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(117, 4, '5', '9', 'N', '2026-11-11', 10, 7, 0, 0, 0, 0, 1.5, 0, 8.5, '', 0, 1.5, 0, 0, 0, 1.5, '', 0, 0, 0, 0, 0, 0, '7 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 7, 1.5, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(118, 4, '5', '12', 'D', '2026-11-12', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(119, 4, '5', '12', 'N', '2026-11-12', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(120, 4, '5', '11', 'D', '2026-11-13', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(121, 4, '5', '12', 'N', '2026-11-13', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(122, 4, '5', '9', 'D', '2026-11-14', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(123, 4, '5', '11', 'N', '2026-11-14', 10, 7, 0, 0, 0, 0, 2, 0, 9, '', 1, 0, 0, 0, 0, 1, '', 0, 0, 0, 0, 0, 0, '7 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 7, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(124, 4, '5', '10', 'D', '2026-11-15', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(125, 4, '5', '12', 'N', '2026-11-15', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(126, 4, '5', '9', 'D', '2026-11-16', 10, 9, 0, 0, 0, 0, 1, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '9 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 9, 1, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(127, 4, '5', '10', 'N', '2026-11-16', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(128, 4, '5', '', 'D', '2026-11-17', 10, 8, 0, 0, 0, 0, 1.5, 0, 9.5, '', 0, 0.5, 0, 0, 0, 0.5, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 8, 1.5, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(129, 4, '5', '12', 'N', '2026-11-17', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(130, 4, '5', '8', 'D', '2026-11-18', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '9 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(131, 4, '5', '8', 'N', '2026-11-18', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '7 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(132, 4, '5', '8', 'D', '2026-11-19', 10, 9, 0, 0, 0, 0, 1, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '9 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 9, 1, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(133, 4, '5', '8', 'N', '2026-11-19', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(134, 4, '5', '10', 'D', '2026-11-20', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(135, 4, '5', '9', 'N', '2026-11-20', 10, 9, 0, 0, 0, 0, 1, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '9 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 9, 1, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(136, 4, '5', '12', 'D', '2026-11-21', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(137, 4, '5', '9', 'N', '2026-11-21', 10, 9, 0, 0, 0, 0, 0, 0, 9, '', 1, 0, 0, 0, 0, 1, '', 0, 0, 0, 0, 0, 0, '9 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 9, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(138, 4, '5', '11', 'D', '2026-11-22', 10, 7, 0, 0, 0, 0, 3, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '7 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 7, 3, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(139, 4, '5', '8', 'N', '2026-11-22', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(140, 4, '5', '10', 'D', '2026-11-23', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(141, 4, '5', '11', 'N', '2026-11-23', 10, 7, 0, 0, 0, 0, 3, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '7 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 7, 3, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(142, 4, '5', '8', 'D', '2026-11-24', 10, 4, 0, 0, 0, 0, 0, 0, 4, '', 0, 6, 0, 0, 0, 6, '', 0, 0, 0, 0, 0, 0, '6 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 4, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(143, 4, '5', '8', 'N', '2026-11-24', 10, 7, 0, 0, 0, 0, 1, 0, 8, '', 0, 2, 0, 0, 0, 2, '', 0, 0, 0, 0, 0, 0, '7 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 7, 1, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(144, 4, '5', '', 'D', '2026-11-25', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(145, 4, '5', '8', 'N', '2026-11-25', 10, 7, 0, 0, 0, 0, 3, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '7 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 7, 3, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(146, 4, '5', '9', 'D', '2026-11-26', 10, 9, 0, 0, 0, 0, 1, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '9 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 9, 1, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(147, 4, '5', '9', 'N', '2026-11-26', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(148, 4, '5', '10', 'D', '2026-11-27', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(149, 4, '5', '8', 'N', '2026-11-27', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(150, 4, '5', '12', 'D', '2026-11-28', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(151, 4, '5', '', 'N', '2026-11-28', 10, 7, 0, 0, 0, 0, 2, 0, 9, '', 1, 0, 0, 0, 0, 1, '', 0, 0, 0, 0, 0, 0, '7 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 7, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(152, 4, '5', '12', 'D', '2026-11-29', 10, 9, 0, 0, 0, 0, 1, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '9 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 9, 1, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(153, 4, '5', '8', 'N', '2026-11-29', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(154, 4, '5', '9', 'D', '2026-11-30', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(155, 4, '5', '11', 'N', '2026-11-30', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(156, 4, '5', '11', 'D', '2026-12-01', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(157, 4, '5', '9', 'N', '2026-12-01', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(158, 4, '5', '11', 'D', '2026-12-02', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(159, 4, '5', '11', 'N', '2026-12-02', 10, 8, 0, 0, 0, 0, 1.5, 0, 9.5, '', 0.5, 0, 0, 0, 0, 0.5, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 8, 1.5, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(160, 4, '5', '12', 'D', '2026-12-03', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(161, 4, '5', '11', 'N', '2026-12-03', 10, 9, 0, 0, 0, 0, 1, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '9 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 9, 1, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(162, 4, '5', '10', 'D', '2026-12-04', 10, 7, 0, 0, 0, 0, 3, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '7 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 7, 3, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(163, 4, '5', '8', 'N', '2026-12-04', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(164, 4, '5', '12', 'D', '2026-12-05', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(165, 4, '5', '', 'N', '2026-12-05', 10, 8, 0, 0, 0, 0, 1, 0, 9, '', 1, 0, 0, 0, 0, 1, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 8, 1, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(166, 4, '5', '8', 'D', '2026-12-06', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(167, 4, '5', '12', 'N', '2026-12-06', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(168, 4, '5', '12', 'D', '2026-12-07', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(169, 4, '5', '12', 'N', '2026-12-07', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(170, 4, '5', '', 'D', '2026-12-08', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(171, 4, '5', '12', 'N', '2026-12-08', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(172, 4, '5', '', 'D', '2026-12-09', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(173, 4, '5', '11', 'N', '2026-12-09', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(174, 4, '5', '12', 'D', '2026-12-10', 10, 6, 0, 0, 0, 0, 4, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '6 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 6, 4, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(175, 4, '5', '12', 'N', '2026-12-10', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(176, 4, '5', '', 'D', '2026-12-11', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(177, 4, '5', '', 'N', '2026-12-11', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(178, 4, '5', '12', 'D', '2026-12-12', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(179, 4, '5', '10', 'N', '2026-12-12', 10, 7, 0, 0, 0, 0, 2, 0, 9, '', 1, 0, 0, 0, 0, 1, '', 0, 0, 0, 0, 0, 0, '7 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 7, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(180, 4, '5', '10', 'D', '2026-12-13', 10, 7, 0, 0, 0, 0, 3, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '7 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 7, 3, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(181, 4, '5', '11', 'N', '2026-12-13', 10, 9, 0, 0, 0, 0, 1, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '9 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 9, 1, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(182, 4, '5', '10', 'D', '2026-12-14', 10, 7, 0, 0, 0, 0, 3, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '7 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 7, 3, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(183, 4, '5', '8', 'N', '2026-12-14', 10, 7, 0, 0, 0, 0, 3, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '7 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 7, 3, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(184, 4, '5', '', 'D', '2026-12-15', 10, 7, 0, 0, 0, 0, 3, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '7 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 7, 3, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(185, 4, '5', '12', 'N', '2026-12-15', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(186, 4, '5', '8', 'D', '2026-12-16', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(187, 4, '5', '12', 'N', '2026-12-16', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(188, 4, '5', '10', 'D', '2026-12-17', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(189, 4, '5', '9', 'N', '2026-12-17', 10, 4, 0, 0, 0, 0, 1, 0, 5, '', 0, 5, 0, 0, 0, 5, '', 0, 0, 0, 0, 0, 0, '4 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 4, 1, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(190, 4, '5', '9', 'D', '2026-12-18', 10, 9, 0, 0, 0, 0, 1, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '9 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 9, 1, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(191, 4, '5', '12', 'N', '2026-12-18', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(192, 4, '5', '12', 'D', '2026-12-19', 10, 5, 0, 0, 0, 0, 5, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '5 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 5, 5, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(193, 4, '5', '12', 'N', '2026-12-19', 10, 8, 0, 0, 0, 0, 1, 0, 9, '', 1, 0, 0, 0, 0, 1, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 8, 1, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(194, 4, '5', '', 'D', '2026-12-20', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(195, 4, '5', '10', 'N', '2026-12-20', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(196, 4, '5', '8', 'D', '2026-12-21', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(197, 4, '5', '9', 'N', '2026-12-21', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(198, 4, '5', '8', 'D', '2026-12-22', 10, 7, 0, 0, 0, 0, 3, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '7 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 7, 3, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(199, 4, '5', '9', 'N', '2026-12-22', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(200, 4, '5', '12', 'D', '2026-12-23', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(201, 4, '5', '10', 'N', '2026-12-23', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(202, 4, '5', '10', 'D', '2026-12-24', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(203, 4, '5', '10', 'N', '2026-12-24', 10, 8, 0, 0, 0, 0, 2, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 8, 2, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(204, 4, '5', '8', 'D', '2026-12-25', 10, 7, 0, 0, 0, 0, 3, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '7 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 7, 3, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(205, 4, '5', '', 'N', '2026-12-25', 10, 8, 0, 0, 0, 0, 1.5, 0, 9.5, '', 0, 0.5, 0, 0, 0, 0.5, '', 0, 0, 0, 0, 0, 0, '8 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 8, 1.5, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(206, 4, '5', '10', 'D', '2026-12-26', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(207, 4, '5', '', 'N', '2026-12-26', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(208, 4, '5', '9', 'D', '2026-12-27', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(209, 4, '5', '10', 'N', '2026-12-27', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(210, 4, '5', '11', 'D', '2026-12-28', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(211, 4, '5', '11', 'N', '2026-12-28', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(212, 4, '5', '8', 'D', '2026-12-29', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(213, 4, '5', '', 'N', '2026-12-29', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(214, 4, '5', '9', 'D', '2026-12-30', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(215, 4, '5', '10', 'N', '2026-12-30', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(216, 4, '5', '9', 'D', '2026-12-31', 10, 7, 0, 0, 0, 0, 0, 0, 7, '', 0, 3, 0, 0, 0, 3, '', 0, 0, 0, 0, 0, 0, '7 ساعة 0 دقيقة 0 ثانية', 'عطل', '', '', '', '', 7, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(217, 4, '5', '8', 'N', '2026-12-31', 10, 9, 0, 0, 0, 0, 1, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 9, 1, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 9, 'لاتوجد ملاحظات', 1),
(222, 4, '12', '3', 'D', '2026-04-27', 10, 8, 4, 4, 0, 0, 0, 0, 8, '', 0, 0, 0, 0, 0, 2, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', '', '', '', '', 'اضراب ساق', 8, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 12, 'لاتوجد ملاحظات', 1),
(223, 4, '12', '4', 'N', '2026-04-27', 10, 10, 0, 0, 0, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 12, 'لاتوجد ملاحظات', 1),
(224, 4, '12', '3', 'N', '2026-04-27', 10, 10, 0, 0, 0, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 12, 'لاتوجد ملاحظات', 1),
(225, 4, '12', '3', 'D', '2026-04-28', 10, 8, 4, 4, 0, 0, 2, 2, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 9000, 0, 0, 9008, '0 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 0, 2, 0, 0, 0, 2, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 12, 'لاتوجد ملاحظات', 1),
(226, 4, '13', '23', 'D', '2025-12-01', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل هيدروليك', 'الدوران', 'جهاز الدوران', '', '', 0, 0, 0, 0, 0, 10, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 14, 'لاتوجد ملاحظات', 1),
(227, 4, '13', '23', 'N', '2025-12-01', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, 'مشكلة دوران', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل صيانة', 'أعطال الميكانيكيا ', 'جهاز الدوران ', '', '', 0, 0, 0, 0, 0, 10, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 14, 'لاتوجد ملاحظات', 1),
(228, 4, '13', '23', 'N', '2025-12-01', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل هيدروليك', 'الدوران', 'جهاز الدوران', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 14, 'لاتوجد ملاحظات', 1),
(229, 4, '13', '23', 'D', '2025-12-02', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل هيدروليك', 'الدوران', 'جهاز الدوران', '', '', 0, 0, 0, 0, 0, 10, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 14, 'لاتوجد ملاحظات', 1),
(230, 4, '13', '23', 'N', '2025-12-02', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 10, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'غير مذكور', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 14, 'لاتوجد ملاحظات', 1),
(231, 4, '13', '23', 'D', '2025-12-03', 10, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 10, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 0, 0, 0, 0, 0, 10, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '1', 14, 'لاتوجد ملاحظات', 1),
(232, 4, '14', '24', 'D', '2025-12-01', 10, 9, 0, 0, 0, 0, 0, 0, 9, '', 0, 0, 0, 0, 0, 1, '', 0, 0, 0, 0, 0, 0, '0', '', '', '', '', '', 10, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '2', 14, 'لاتوجد ملاحظات', 1),
(233, 4, '14', '25', 'N', '2025-12-01', 10, 10, 0, 0, 0, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '0', '', '', '', '', '', 10, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, NULL, 0.00, 0, 0.00, '2', 14, 'لاتوجد ملاحظات', 1),
(234, 4, '13', '23', 'D', '2026-05-02', 10, 5, 0, 0, 0, 0, 0, 0, 5, '', 0, 0, 0, 0, 0, 5, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, NULL, '', 0.00, 0, 0.00, '1', 14, 'لاتوجد ملاحظات', 1),
(235, 4, '12', '3', 'D', '2026-05-05', 10, 5, 5, 0, 0, 0, 0, 0, 5, '', 0, 5, 0, 0, 0, 5, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'أعطال إدارة التشغيل', 'غيار زيت', 'غيار زيت الماكينة', '', '', 0, 0, 0, 0, 0, 5, '', 0.00, 0, '', '', 0.00, 0, 0.00, '1', 14, 'لاتوجد ملاحظات', 1),
(236, 4, '12', '3', 'D', '2026-05-05', 10, 9, 9, 0, 0, 0, 0, 0, 9, '', 0, 1, 0, 0, 0, 1, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل معدة', 'أعطال الميكانيكا', 'المحرك', 'EX-EQF-MEC-01-10 | ارتفاع حرارة المحرك', '', 9, 0, 0, 0, 0, 1, '', 0.00, 0, '', '', 0.00, 0, 0.00, '1', 14, 'لاتوجد ملاحظات', 1);
INSERT INTO `timesheet` (`id`, `company_id`, `operator`, `driver`, `shift`, `date`, `shift_hours`, `executed_hours`, `bucket_hours`, `jackhammer_hours`, `extra_hours`, `extra_hours_total`, `standby_hours`, `dependence_hours`, `total_work_hours`, `work_notes`, `hr_fault`, `maintenance_fault`, `marketing_fault`, `approval_fault`, `other_fault_hours`, `total_fault_hours`, `fault_notes`, `start_seconds`, `start_minutes`, `start_hours`, `end_seconds`, `end_minutes`, `end_hours`, `counter_diff`, `fault_type`, `fault_department`, `fault_part`, `fault_details`, `general_notes`, `operator_hours`, `machine_standby_hours`, `jackhammer_standby_hours`, `bucket_standby_hours`, `extra_operator_hours`, `operator_standby_hours`, `operator_notes`, `tons_count`, `trips_count`, `transport_type`, `meters_type`, `meters_count`, `drilling_holes_count`, `drilling_depth`, `type`, `user_id`, `time_notes`, `status`) VALUES
(237, 4, '12', '4', 'D', '2026-05-13', 10, 6, 6, 0, 0, 0, 0, 0, 6, '', 4, 0, 0, 0, 0, 4, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل HR', 'عدم توفر المشغل', 'عدم توفر المشغل', 'EX-HRF-HRA-00-02 | تأخر مشغل الوردية البديلة', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, '', '', 0.00, 0, 0.00, '1', 12, 'لاتوجد ملاحظات', 1),
(238, 4, '13', '4', 'D', '2026-05-05', 10, 8, 5, 3, 0, 0, 0, 0, 8, '', 0, 2, 0, 0, 0, 2, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل معدة', 'أعطال الكهرباء', 'البطارية', 'EX-EQF-ELE-01-02 | بطارية تالفة', '', 0, 0, 0, 0, 0, 2, '', 0.00, 0, '', '', 0.00, 0, 0.00, '1', 14, 'لاتوجد ملاحظات', 1),
(239, 4, '13', '23', 'N', '2026-05-05', 10, 8, 4, 4, 0, 0, 0, 0, 8, '', 1, 1, 0, 0, 0, 2, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل HR', 'غياب المشغل', 'غياب المشغل', 'EX-HRF-HRB-00-02 | إجازة مشغل دون بديل', '', 0, 0, 0, 0, 0, 1, '', 0.00, 0, '', '', 0.00, 0, 0.00, '1', 12, 'لاتوجد ملاحظات', 1),
(240, 4, '14', '25', 'N', '2026-09-05', 10, 10, 0, 0, 0, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '0', '', '', '', '', '', 10, 0, 0, 0, 0, 0, '', 0.00, 0, '', NULL, 0.00, 0, 0.00, '2', 12, 'لاتوجد ملاحظات', 1),
(241, 4, '14', '25', 'D', '2026-05-09', 10, 8, 0, 0, 0, 0, 0, 0, 8, '', 1, 1, 0, 0, 0, 2, '', 0, 0, 0, 0, 0, 0, '0', '', '', '', '', '', 10, 0, 0, 0, 0, 0, '', 10.00, 2, '', NULL, 0.00, 0, 0.00, '2', 12, 'لاتوجد ملاحظات', 1),
(242, 4, '12', '4', 'D', '2026-05-11', 10, 9, 5, 4, 0, 0, 0, 0, 9, '', 1, 0, 0, 0, 0, 1, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل معدة', 'أعطال الكهرباء', 'الإضاءة', 'EX-EQF-ELE-06-01 | إضاءة أمامية', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, '', '', 0.00, 0, 0.00, '1', 12, 'لاتوجد ملاحظات', 1),
(243, 4, '12', '5', 'D', '2026-05-11', 10, 9, 7, 2, 0, 0, 0, 0, 9, '', 1, 0, 0, 0, 0, 1, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'توقف صيانة', 'صيانة طارئة', 'صيانة طارئة', 'EX-MNT-PME-00-02 | انقلاب', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, '', '', 0.00, 0, 0.00, '1', 12, 'لاتوجد ملاحظات', 1),
(244, 4, '15', '2', 'N', '2026-05-11', 10, 10, 5, 5, 0, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, '', '', 0.00, 0, 0.00, '1', 12, 'لاتوجد ملاحظات', 1),
(245, 4, '16', '21', 'N', '2026-05-11', 10, 9, 0, 0, 0, 0, 0, 0, 9, '', 1, 0, 0, 0, 0, 1, '', 0, 0, 0, 0, 0, 0, '0', 'توقف اعتماد', 'اعتماد جزئي', 'اعتماد جزئي', 'DR-DEP-DPP-00-01 | تشغيل بطاقة منخفضة', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, '', 'امتار اخذ العينات', 16.00, 4, 4.00, '3', 12, 'لاتوجد ملاحظات', 1),
(246, 4, '12', '4', 'D', '2026-05-16', 10, 9, 5, 3, 1, 1, 0, 0, 10, '', 1, 0, 0, 0, 0, 1, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل معدة', 'أعطال الهيدروليك', 'الطرمبة', 'EX-EQF-HYD-02-01 | طرمبة رئيسية', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, '', '', 0.00, 0, 0.00, '1', 12, 'لاتوجد ملاحظات', 1),
(247, 4, '15', '2', 'D', '2026-05-16', 10, 10, 5, 5, 0, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, '', '', 0.00, 0, 0.00, '1', 12, 'لاتوجد ملاحظات', 1),
(248, 4, '12', '4', 'D', '2026-05-20', 10, 10, 5, 5, 0, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 0, 0, 0, 0, 0, 0, '', 0.00, 0, '', '', 0.00, 0, 0.00, '1', 12, 'لاتوجد ملاحظات', 1),
(249, 4, '12', '', 'D', '2026-06-02', 10, 8, 6, 2, 0, 0, 0, 0, 8, '', 1, 1, 0, 0, 0, 2, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', 'عطل معدة', 'أعطال التبريد والتكيف', 'الحساسات', 'EX-EQF-COL-08-01 | حساس حرارة', '', 0, 0, 0, 0, 0, 1, '', 0.00, 0, '', '', 0.00, 0, 0.00, '1', 12, 'لاتوجد ملاحظات', 1);

-- --------------------------------------------------------

--
-- Table structure for table `timesheet_approvals`
--

CREATE TABLE `timesheet_approvals` (
  `id` int(11) NOT NULL,
  `timesheet_id` int(11) NOT NULL COMMENT 'FK → timesheet.id',
  `company_id` int(11) DEFAULT NULL,
  `approval_level` tinyint(1) NOT NULL COMMENT '1..4',
  `approved_by` int(11) NOT NULL COMMENT 'FK → users.id',
  `approved_by_name` varchar(255) NOT NULL,
  `approved_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=اعتمد, 0=رُفض'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci COMMENT='اعتمادات ساعات العمل الهرمية';

--
-- Dumping data for table `timesheet_approvals`
--

INSERT INTO `timesheet_approvals` (`id`, `timesheet_id`, `company_id`, `approval_level`, `approved_by`, `approved_by_name`, `approved_at`, `status`) VALUES
(1, 217, 4, 1, 4, 'مسؤول التشغيل', '2026-05-01 08:12:59', 1),
(2, 216, 4, 1, 4, 'مسؤول التشغيل', '2026-05-01 12:48:15', 1),
(3, 215, 4, 1, 4, 'مسؤول التشغيل', '2026-05-02 07:33:10', 1),
(4, 214, 4, 1, 4, 'مسؤول التشغيل', '2026-05-02 07:33:58', 1),
(5, 213, 4, 1, 4, 'مسؤول التشغيل', '2026-05-02 07:33:58', 1),
(6, 212, 4, 1, 4, 'مسؤول التشغيل', '2026-05-02 07:33:58', 1),
(7, 211, 4, 1, 4, 'مسؤول التشغيل', '2026-05-02 07:33:58', 1),
(8, 210, 4, 1, 4, 'مسؤول التشغيل', '2026-05-02 07:33:58', 1),
(9, 209, 4, 1, 4, 'مسؤول التشغيل', '2026-05-02 07:33:58', 1),
(10, 208, 4, 1, 4, 'مسؤول التشغيل', '2026-05-02 07:33:58', 1),
(11, 207, 4, 1, 4, 'مسؤول التشغيل', '2026-05-02 07:33:58', 1),
(12, 206, 4, 1, 4, 'مسؤول التشغيل', '2026-05-02 07:33:58', 1),
(13, 205, 4, 1, 4, 'مسؤول التشغيل', '2026-05-02 07:33:58', 1),
(14, 204, 4, 1, 4, 'مسؤول التشغيل', '2026-05-02 07:33:58', 1),
(15, 203, 4, 1, 4, 'مسؤول التشغيل', '2026-05-02 07:33:58', 1),
(16, 202, 4, 1, 4, 'مسؤول التشغيل', '2026-05-02 07:33:58', 1),
(17, 201, 4, 1, 4, 'مسؤول التشغيل', '2026-05-02 07:33:58', 1),
(18, 200, 4, 1, 4, 'مسؤول التشغيل', '2026-05-02 07:33:58', 1),
(19, 199, 4, 1, 4, 'مسؤول التشغيل', '2026-05-02 07:33:58', 1),
(20, 198, 4, 1, 4, 'مسؤول التشغيل', '2026-05-02 07:33:58', 1),
(21, 197, 4, 1, 4, 'مسؤول التشغيل', '2026-05-02 07:33:58', 1),
(22, 196, 4, 1, 4, 'مسؤول التشغيل', '2026-05-02 07:33:58', 1),
(23, 195, 4, 1, 4, 'مسؤول التشغيل', '2026-05-02 07:33:58', 1),
(24, 194, 4, 1, 4, 'مسؤول التشغيل', '2026-05-02 07:33:58', 1),
(25, 193, 4, 1, 4, 'مسؤول التشغيل', '2026-05-02 07:33:58', 1),
(26, 192, 4, 1, 4, 'مسؤول التشغيل', '2026-05-02 07:33:58', 1),
(27, 191, 4, 1, 4, 'مسؤول التشغيل', '2026-05-02 07:33:58', 1),
(28, 190, 4, 1, 4, 'مسؤول التشغيل', '2026-05-02 07:33:58', 1),
(29, 217, 4, 2, 5, 'مسؤول الموردين', '2026-05-02 07:50:39', 1),
(30, 216, 4, 2, 5, 'مسؤول الموردين', '2026-05-02 07:51:04', 1),
(31, 215, 4, 2, 5, 'مسؤول الموردين', '2026-05-02 07:51:04', 1),
(32, 214, 4, 2, 5, 'مسؤول الموردين', '2026-05-02 07:51:04', 1),
(33, 213, 4, 2, 5, 'مسؤول الموردين', '2026-05-02 07:51:04', 1),
(34, 212, 4, 2, 5, 'مسؤول الموردين', '2026-05-02 07:51:04', 1),
(35, 211, 4, 2, 5, 'مسؤول الموردين', '2026-05-02 07:51:04', 1),
(36, 210, 4, 2, 5, 'مسؤول الموردين', '2026-05-02 07:51:04', 1),
(37, 209, 4, 2, 5, 'مسؤول الموردين', '2026-05-02 07:51:04', 1),
(38, 208, 4, 2, 5, 'مسؤول الموردين', '2026-05-02 08:30:08', 1),
(39, 207, 4, 2, 5, 'مسؤول الموردين', '2026-05-02 08:30:26', 1),
(40, 206, 4, 2, 5, 'مسؤول الموردين', '2026-05-02 08:30:26', 1),
(41, 205, 4, 2, 5, 'مسؤول الموردين', '2026-05-02 08:30:26', 1),
(42, 189, 4, 1, 4, 'مسؤول التشغيل', '2026-05-02 08:31:01', 1),
(43, 163, 4, 1, 4, 'مسؤول التشغيل', '2026-05-09 00:13:58', 1),
(44, 244, 4, 1, 4, 'مسؤول التشغيل', '2026-05-11 01:50:52', 1),
(45, 188, 4, 1, 4, 'مسؤول التشغيل', '2026-05-11 01:51:49', 1),
(46, 187, 4, 1, 4, 'مسؤول التشغيل', '2026-05-11 01:56:08', 1),
(47, 185, 4, 1, 4, 'مسؤول التشغيل', '2026-05-11 01:56:20', 1),
(48, 204, 4, 2, 5, 'مسؤول الموردين', '2026-05-11 02:12:24', 1),
(49, 245, 4, 1, 4, 'مسؤول التشغيل', '2026-05-11 02:33:59', 1),
(50, 245, 4, 2, 5, 'مسؤول الموردين', '2026-05-11 02:34:43', 1),
(51, 245, 4, 3, 6, 'يسن سيد احمد', '2026-05-11 02:35:50', 1),
(52, 245, 4, 4, 7, 'المشغلين', '2026-05-11 02:38:11', 1),
(53, 186, 4, 1, 4, 'مسؤول التشغيل', '2026-05-11 02:55:05', 1),
(54, 240, 4, 1, 4, 'مسؤول التشغيل', '2026-05-14 18:54:51', 1),
(55, 184, 4, 1, 4, 'مسؤول التشغيل', '2026-06-02 12:38:32', 1);

-- --------------------------------------------------------

--
-- Table structure for table `timesheet_approval_notes`
--

CREATE TABLE `timesheet_approval_notes` (
  `id` int(11) NOT NULL,
  `timesheet_id` int(11) NOT NULL COMMENT 'FK → timesheet.id',
  `company_id` int(11) DEFAULT NULL,
  `column_name` varchar(100) NOT NULL COMMENT 'اسم العمود التقني',
  `column_label` varchar(255) NOT NULL COMMENT 'عنوان العمود بالعربية',
  `note_text` text NOT NULL,
  `created_by` int(11) NOT NULL COMMENT 'FK → users.id',
  `created_by_name` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci COMMENT='ملاحظات اعتماد ساعات العمل';

--
-- Dumping data for table `timesheet_approval_notes`
--

INSERT INTO `timesheet_approval_notes` (`id`, `timesheet_id`, `company_id`, `column_name`, `column_label`, `note_text`, `created_by`, `created_by_name`, `created_at`, `status`) VALUES
(1, 217, 4, 'shift', 'الوردية', 'لالا', 4, 'مسؤول التشغيل', '2026-05-01 08:12:49', 1),
(2, 39, 4, 'shift_hours', 'ساعات الوردية', 'راجع الساعات', 4, 'مسؤول التشغيل', '2026-05-02 07:34:56', 1),
(3, 224, 4, 'shift', 'الوردية', 'mdkl', 12, 'حسن ', '2026-05-02 12:38:55', 1),
(4, 236, 4, 'executed_hours', 'الساعات المنفذة', 'نمثينمؤ', 12, 'حسن ', '2026-05-09 15:58:25', 1),
(5, 245, 4, 'shift_hours', 'ساعات الوردية', 'v mvk m', 12, 'حسن ', '2026-05-11 02:17:20', 1),
(6, 184, 4, 'marketing_fault', 'عطل تسويق', 'w;lmgfrw;l', 4, 'مسؤول التشغيل', '2026-06-02 12:39:10', 1);

-- --------------------------------------------------------

--
-- Table structure for table `timesheet_failure_hours`
--

CREATE TABLE `timesheet_failure_hours` (
  `id` int(11) NOT NULL,
  `timesheet_id` int(11) NOT NULL,
  `operation_id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL DEFAULT 0,
  `failure_code_id` int(11) NOT NULL,
  `equipment_type` tinyint(1) NOT NULL COMMENT '1=حفار,2=قلاب,3=خرامة',
  `event_type_code` varchar(20) NOT NULL,
  `event_type_name` varchar(150) NOT NULL,
  `main_category_code` varchar(20) NOT NULL,
  `main_category_name` varchar(200) NOT NULL,
  `sub_category` varchar(200) NOT NULL,
  `failure_detail` varchar(255) NOT NULL,
  `full_code` varchar(50) NOT NULL,
  `timesheet_date` date NOT NULL,
  `company_id` int(11) NOT NULL DEFAULT 0,
  `created_by` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `timesheet_failure_hours`
--

INSERT INTO `timesheet_failure_hours` (`id`, `timesheet_id`, `operation_id`, `equipment_id`, `failure_code_id`, `equipment_type`, `event_type_code`, `event_type_name`, `main_category_code`, `main_category_name`, `sub_category`, `failure_detail`, `full_code`, `timesheet_date`, `company_id`, `created_by`, `status`, `created_at`, `updated_at`) VALUES
(1, 236, 12, 8, 13, 1, 'EQF', 'عطل معدة', 'MEC', 'أعطال الميكانيكا', 'المحرك', 'ارتفاع حرارة المحرك', 'EX-EQF-MEC-01-10', '2026-05-05', 4, 14, 1, '2026-05-05 07:34:25', '2026-05-05 07:34:25'),
(2, 237, 12, 8, 180, 1, 'HRF', 'عطل HR', 'HRA', 'عدم توفر المشغل', 'عدم توفر المشغل', 'تأخر مشغل الوردية البديلة', 'EX-HRF-HRA-00-02', '2026-05-13', 4, 12, 1, '2026-05-05 10:23:06', '2026-05-05 10:23:06'),
(3, 238, 13, 10, 57, 1, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'البطارية', 'بطارية تالفة', 'EX-EQF-ELE-01-02', '2026-05-05', 4, 14, 1, '2026-05-05 10:41:37', '2026-05-05 10:41:37'),
(4, 239, 13, 10, 182, 1, 'HRF', 'عطل HR', 'HRB', 'غياب المشغل', 'غياب المشغل', 'إجازة مشغل دون بديل', 'EX-HRF-HRB-00-02', '2026-05-05', 4, 12, 1, '2026-05-05 11:48:55', '2026-05-05 11:48:55'),
(5, 239, 13, 10, 147, 1, 'MNT', 'توقف صيانة', 'PME', 'صيانة طارئة', 'صيانة طارئة', 'انقلاب', 'EX-MNT-PME-00-02', '2026-05-05', 4, 12, 1, '2026-05-05 11:48:55', '2026-05-05 11:48:55'),
(6, 242, 12, 8, 69, 1, 'EQF', 'عطل معدة', 'ELE', 'أعطال الكهرباء', 'الإضاءة', 'إضاءة أمامية', 'EX-EQF-ELE-06-01', '2026-05-11', 4, 12, 1, '2026-05-10 22:36:08', '2026-05-10 22:36:08'),
(7, 242, 12, 8, 185, 1, 'HRF', 'عطل HR', 'HRC', 'عدم كفاءة المشغل', 'عدم كفاءة المشغل', 'انتهاء رخصة التشغيل', 'EX-HRF-HRC-00-02', '2026-05-11', 4, 12, 1, '2026-05-10 22:36:08', '2026-05-10 22:36:08'),
(8, 243, 12, 8, 147, 1, 'MNT', 'توقف صيانة', 'PME', 'صيانة طارئة', 'صيانة طارئة', 'انقلاب', 'EX-MNT-PME-00-02', '2026-05-11', 4, 12, 1, '2026-05-10 22:37:07', '2026-05-10 22:37:07'),
(9, 245, 16, 13, 380, 3, 'DEP', 'توقف اعتماد', 'DPP', 'اعتماد جزئي', 'اعتماد جزئي', 'تشغيل بطاقة منخفضة', 'DR-DEP-DPP-00-01', '2026-05-11', 4, 12, 1, '2026-05-10 23:15:48', '2026-05-10 23:15:48'),
(10, 246, 12, 8, 41, 1, 'EQF', 'عطل معدة', 'HYD', 'أعطال الهيدروليك', 'الطرمبة', 'طرمبة رئيسية', 'EX-EQF-HYD-02-01', '2026-05-16', 4, 12, 1, '2026-05-16 09:21:00', '2026-05-16 09:21:00'),
(11, 246, 12, 8, 177, 1, 'MST', 'استعداد تسويق', 'MSZ', 'ظروف زمنية', 'ظروف زمنية', 'الإجازات الرسمية غير المتفق عليها', 'EX-MST-MSZ-00-01', '2026-05-16', 4, 12, 1, '2026-05-16 09:21:00', '2026-05-16 09:21:00'),
(12, 249, 12, 8, 94, 1, 'EQF', 'عطل معدة', 'COL', 'أعطال التبريد والتكيف', 'الحساسات', 'حساس حرارة', 'EX-EQF-COL-08-01', '2026-06-02', 4, 12, 1, '2026-06-02 09:32:38', '2026-06-02 09:32:38'),
(13, 249, 12, 8, 147, 1, 'MNT', 'توقف صيانة', 'PME', 'صيانة طارئة', 'صيانة طارئة', 'انقلاب', 'EX-MNT-PME-00-02', '2026-06-02', 4, 12, 1, '2026-06-02 09:32:38', '2026-06-02 09:32:38');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL COMMENT 'معرف فريد',
  `name` varchar(100) NOT NULL COMMENT 'الاسم الثلاثي',
  `username` varchar(150) NOT NULL COMMENT 'اسم المستخدم',
  `email` varchar(150) DEFAULT NULL COMMENT 'البريد',
  `password` varchar(255) NOT NULL COMMENT 'كلمة المرور',
  `phone` varchar(20) DEFAULT NULL COMMENT 'رقم الهاتف',
  `role` varchar(30) NOT NULL COMMENT 'رقم الصلاحية',
  `company_id` int(11) DEFAULT NULL COMMENT 'رقم الشركة',
  `role_id` int(11) DEFAULT NULL COMMENT 'رقم الصلاحية',
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active' COMMENT 'الحالة',
  `force_password_change` tinyint(1) NOT NULL DEFAULT 0,
  `temp_password_set_at` timestamp NULL DEFAULT NULL,
  `project_id` varchar(20) NOT NULL DEFAULT '0' COMMENT 'المشروع',
  `contract_id` int(11) DEFAULT 0 COMMENT 'العقد',
  `parent_id` varchar(20) NOT NULL DEFAULT '0' COMMENT 'المستخدم الاب',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'انشئ في',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'عدل في',
  `last_login_at` timestamp NULL DEFAULT NULL COMMENT 'اخر دخول',
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'محذوف',
  `deleted_at` datetime DEFAULT NULL COMMENT 'وقت الحذف',
  `deleted_by` int(11) DEFAULT NULL COMMENT 'الحاذف'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `email`, `password`, `phone`, `role`, `company_id`, `role_id`, `status`, `force_password_change`, `temp_password_set_at`, `project_id`, `contract_id`, `parent_id`, `created_at`, `updated_at`, `last_login_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES
(1, 'محمد ادريس', 'adminfree@gmail.com', 'adminfree@gmail.com', '$2y$10$3I7ZYbnPjX9BEWzLR4HHz.FHcMPMzUBMnQNI3vBYLnBzIGUYI.UMG', '+249915657576', '1', 1, 1, 'active', 0, NULL, '0', 0, '0', '2026-04-07 10:53:47', '2026-04-07 10:54:04', '2026-04-07 10:54:04', 0, NULL, NULL),
(4, 'مسؤول التشغيل', 'محمد', 'admin@gmail.com', '$2y$10$8FcRlrkxuIOUr6kWAwy6Z.lh1rYmAzAA/8zSH7sxhgPAc69eQNLTG', '+249915657576', '1', 4, 1, 'active', 0, NULL, '0', 0, '0', '2026-04-07 11:19:26', '2026-04-30 06:47:38', '2026-04-30 06:47:38', 0, NULL, NULL),
(5, 'مسؤول الموردين', 'مصعب', NULL, '$2y$10$WA9lipyyjBky7B1zieAXPur.sdLhy.UlHy5Jj4q1IZYzP6B3tGTeq', '09209303903', '2', 4, NULL, 'active', 0, NULL, '0', 0, '0', '2026-04-07 11:33:09', '2026-04-29 11:12:49', NULL, 0, NULL, NULL),
(6, 'يسن سيد احمد', 'يسن', NULL, '$2y$10$dpgJiR7LQuaJVDgQIO/F4.ze3HXdjjT6OiflD/RS0C0VgxjmiBh4W', '09209303903', '3', 4, NULL, 'active', 0, NULL, '0', 0, '0', '2026-04-07 11:34:52', '2026-05-01 07:18:33', '2026-05-01 07:18:33', 0, NULL, NULL),
(7, 'المشغلين', 'اروينا', NULL, '$2y$10$Jk5vHPG/HMIwfhP6x1mFC.t4mUs524htfwDDrCYWmKW/lv9tJvEzS', '09209303903', '4', 4, NULL, 'active', 0, NULL, '0', 0, '0', '2026-04-07 11:35:27', '2026-04-20 20:49:04', NULL, 0, NULL, NULL),
(8, 'المواقع', 'موقع', NULL, '$2y$10$5ln6ocflqV231lrG01s9LOV4DZR.dHrEci8RJ7XeA/RbFpadupmY2', '09209303903', '5', 4, NULL, 'active', 0, NULL, '1', 1, '0', '2026-04-07 12:45:25', '2026-04-07 12:45:25', NULL, 0, NULL, NULL),
(9, 'احمد المرتضى', 'احمد', NULL, '$2y$10$jjb4J0aoplTtyccnMx2Z4eqTOY6GmJ2tWDfJlLiKMZ4dBgGgCyc.u', '09209303903', '5', 4, NULL, 'active', 0, NULL, '2', 2, '0', '2026-04-13 12:05:54', '2026-04-20 20:28:33', NULL, 0, NULL, NULL),
(10, 'يس سيدأحمد', 'مشغل الروسية', NULL, '$2y$10$ALTgaql33QNBq7TUG/kh.e5sc2PnCfD3.jeWlzKFXZlYpnQSGhFy6', '09209303903', '6', 4, NULL, 'active', 0, NULL, '2', 2, '0', '2026-04-14 12:45:21', '2026-04-26 11:46:57', NULL, 0, NULL, NULL),
(11, 'مدير حركة', 'حركة - الروسية', NULL, '$2y$10$2.NmkMgWWcRAVmUhmOjY/O9tAQd2O1pMWxijQ1L2cxgpGBDLVUwBm', '09209303903', '6', 4, NULL, 'active', 0, NULL, '4', 4, '0', '2026-04-26 11:51:35', '2026-04-26 11:51:35', NULL, 0, NULL, NULL),
(12, 'حسن ', 'مدير موقع الروسية', NULL, '$2y$10$IZN1RsjzNMM1l.TbLtyUyOQUVTn5PhVg4189RQFSwaCpGVp/h00CS', '09209303903', '5', 4, NULL, 'active', 0, NULL, '4', 4, '0', '2026-04-26 12:24:45', '2026-04-26 12:24:45', NULL, 0, NULL, NULL),
(13, 'مسؤول المبيعات', 'مبيعات', NULL, '$2y$10$Ft68E8j.vFpkLSBkq/TRb.ooMmXDF/qCBoRUUcaxwH7IDoaXTtn3e', '09209303903', '12', 4, NULL, 'active', 0, NULL, '0', 0, '0', '2026-04-28 09:24:31', '2026-04-28 09:24:31', NULL, 0, NULL, NULL),
(14, 'مدير المنجم MB1', 'مدير المنجم MB1', NULL, '$2y$10$RERFPCS3XJutiG/OFZSItemoB.hcCrNLllPIQM8CXN00ni.RBpTRG', '09209303903', '5', 4, NULL, 'active', 0, NULL, '4', 5, '0', '2026-04-28 11:13:14', '2026-05-12 11:48:50', NULL, 0, NULL, NULL),
(15, 'مشغل المجم MB1', 'مشغل المجم MB1', NULL, '$2y$10$80.Mfn.j1CDnOGVHOBukgOvDXBDzFhX9b5JryxMM5E/8JDWNVU36m', '09209303903', '6', 4, NULL, 'active', 0, NULL, '2', 5, '0', '2026-04-28 11:22:05', '2026-05-11 17:09:55', NULL, 1, '2026-05-11 20:09:55', 4),
(16, 'مشرف المنجم MB1', 'مشرف المنجم MB1', NULL, '$2y$10$lktDGTePBGXvdWy6h9Be3u/yMsPI4Y80Q0khOsAt5bl7AxSYmjVli', '09209303903', '1', 4, NULL, 'active', 0, NULL, '0', 0, '0', '2026-04-28 12:52:52', '2026-05-11 17:08:02', NULL, 1, '2026-05-11 20:08:02', 4),
(17, 'موقعmb1', 'موقعmb1', NULL, '$2y$10$h.Tz8Xkf4/rAkOJkrp63LezRtUfnEPgqP74m7cJTAVUFnYQpkb4h2', '09209303903', '5', 4, NULL, 'active', 0, NULL, '2', 2, '0', '2026-05-05 12:20:45', '2026-05-14 16:21:03', NULL, 0, NULL, NULL),
(18, 'حركة - جديد', 'حركة - جديد', NULL, '$2y$10$7iLiNi.E7oRRYii6ou0vXetpO9NI4t94Njiir/JI1mO3dFB90zwNa', '09209303903', '6', 4, NULL, 'active', 0, NULL, '4', 5, '0', '2026-05-12 11:46:20', '2026-05-12 11:46:20', NULL, 0, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_company_created` (`company_id`,`created_at`),
  ADD KEY `idx_user_created` (`user_id`,`created_at`),
  ADD KEY `idx_role_created` (`role_id`,`created_at`),
  ADD KEY `idx_action_created` (`action_type`,`created_at`),
  ADD KEY `idx_module_screen_created` (`module_name`,`screen_name`,`created_at`),
  ADD KEY `idx_record_module` (`record_id`,`module_name`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_screen_name` (`screen_name`),
  ADD KEY `idx_module_name` (`module_name`),
  ADD KEY `idx_action_type` (`action_type`),
  ADD KEY `idx_record_id` (`record_id`);

--
-- Indexes for table `admin_audit_log`
--
ALTER TABLE `admin_audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_audit_admin` (`admin_id`),
  ADD KEY `idx_admin_audit_action` (`action_type`),
  ADD KEY `idx_admin_audit_date` (`created_at`);

--
-- Indexes for table `admin_companies`
--
ALTER TABLE `admin_companies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_admin_companies_email` (`email`),
  ADD UNIQUE KEY `uq_admin_companies_commercial_registration` (`commercial_registration`),
  ADD KEY `idx_admin_companies_plan` (`plan_id`),
  ADD KEY `idx_admin_companies_status` (`status`);

--
-- Indexes for table `admin_subscription_plans`
--
ALTER TABLE `admin_subscription_plans`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admin_subscription_requests`
--
ALTER TABLE `admin_subscription_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_sub_req_status` (`status`),
  ADD KEY `idx_admin_sub_req_plan` (`plan_id`),
  ADD KEY `fk_admin_sub_req_reviewer` (`reviewed_by`);

--
-- Indexes for table `admin_subscription_requests_test_probe`
--
ALTER TABLE `admin_subscription_requests_test_probe`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_sub_req_status` (`status`),
  ADD KEY `idx_admin_sub_req_plan` (`plan_id`);

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
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_logs_user_id` (`user_id`),
  ADD KEY `idx_audit_logs_company_id` (`company_id`),
  ADD KEY `idx_audit_logs_action_type` (`action_type`),
  ADD KEY `idx_audit_logs_created_at` (`created_at`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_client_name` (`client_name`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `company_user_password_resets`
--
ALTER TABLE `company_user_password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_company_user_password_resets_token_hash` (`token_hash`),
  ADD KEY `idx_company_user_password_resets_user_id` (`user_id`);

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
  ADD KEY `fk_contracts_merged` (`merged_with`),
  ADD KEY `idx_contracts_project_id` (`project_id`);

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
  ADD KEY `idx_supplier_id` (`supplier_id`),
  ADD KEY `idx_drivers_project_id` (`project_id`);

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
-- Indexes for table `failure_codes`
--
ALTER TABLE `failure_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_equipment_type` (`equipment_type`),
  ADD KEY `idx_event_type` (`equipment_type`,`event_type_code`),
  ADD KEY `idx_main_cat` (`equipment_type`,`event_type_code`,`main_category_code`),
  ADD KEY `idx_sub_cat` (`equipment_type`,`event_type_code`,`main_category_code`,`sub_category`(50));

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_msg_sender` (`sender_id`),
  ADD KEY `idx_msg_receiver` (`receiver_id`),
  ADD KEY `idx_msg_company` (`company_id`),
  ADD KEY `idx_msg_read` (`is_read`),
  ADD KEY `idx_msg_created` (`created_at`),
  ADD KEY `idx_msg_conversation` (`sender_id`,`receiver_id`,`company_id`);

--
-- Indexes for table `modules`
--
ALTER TABLE `modules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `owner_role_id` (`owner_role_id`),
  ADD KEY `idx_display_order` (`display_order`);

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
  ADD KEY `fk_project_created_by` (`created_by`),
  ADD KEY `idx_client_id` (`client_id`),
  ADD KEY `idx_mine_code` (`mine_code`);

--
-- Indexes for table `report_role_permissions`
--
ALTER TABLE `report_role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_role_report` (`role_id`,`report_code`);

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
-- Indexes for table `super_admins`
--
ALTER TABLE `super_admins`
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
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_suppliers_is_deleted` (`is_deleted`);

--
-- Indexes for table `supplierscontracts`
--
ALTER TABLE `supplierscontracts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project_contract` (`project_contract_id`),
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
-- Indexes for table `timesheet_approvals`
--
ALTER TABLE `timesheet_approvals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ts_level` (`timesheet_id`,`approval_level`),
  ADD KEY `idx_ts_id` (`timesheet_id`),
  ADD KEY `idx_company` (`company_id`),
  ADD KEY `idx_level` (`approval_level`);

--
-- Indexes for table `timesheet_approval_notes`
--
ALTER TABLE `timesheet_approval_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ts_id` (`timesheet_id`),
  ADD KEY `idx_company` (`company_id`);

--
-- Indexes for table `timesheet_failure_hours`
--
ALTER TABLE `timesheet_failure_hours`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_timesheet_id` (`timesheet_id`),
  ADD KEY `idx_operation_id` (`operation_id`),
  ADD KEY `idx_equipment_id` (`equipment_id`),
  ADD KEY `idx_failure_code_id` (`failure_code_id`),
  ADD KEY `idx_full_code` (`full_code`),
  ADD KEY `idx_timesheet_date` (`timesheet_date`),
  ADD KEY `idx_company_id` (`company_id`),
  ADD KEY `idx_lookup_report` (`company_id`,`timesheet_date`,`equipment_id`,`failure_code_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `idx_contract_id` (`contract_id`),
  ADD KEY `idx_users_company_id` (`company_id`),
  ADD KEY `idx_users_status` (`status`),
  ADD KEY `idx_users_is_deleted` (`is_deleted`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=379;

--
-- AUTO_INCREMENT for table `admin_audit_log`
--
ALTER TABLE `admin_audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `admin_companies`
--
ALTER TABLE `admin_companies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'معرف فريد', AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `admin_subscription_plans`
--
ALTER TABLE `admin_subscription_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `admin_subscription_requests`
--
ALTER TABLE `admin_subscription_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'معرف فريد', AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `admin_subscription_requests_test_probe`
--
ALTER TABLE `admin_subscription_requests_test_probe`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `approval_requests`
--
ALTER TABLE `approval_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `approval_steps`
--
ALTER TABLE `approval_steps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `approval_workflow_rules`
--
ALTER TABLE `approval_workflow_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `company_user_password_resets`
--
ALTER TABLE `company_user_password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contractequipments`
--
ALTER TABLE `contractequipments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `driver_contract_notes`
--
ALTER TABLE `driver_contract_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `equipments`
--
ALTER TABLE `equipments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `equipments_types`
--
ALTER TABLE `equipments_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `equipment_drivers`
--
ALTER TABLE `equipment_drivers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `failure_codes`
--
ALTER TABLE `failure_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=403;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'المعرف الفريد', AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `modules`
--
ALTER TABLE `modules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `operations`
--
ALTER TABLE `operations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `project`
--
ALTER TABLE `project`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `report_role_permissions`
--
ALTER TABLE `report_role_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=287;

--
-- AUTO_INCREMENT for table `super_admins`
--
ALTER TABLE `super_admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'معرف فريد', AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `suppliercontractequipments`
--
ALTER TABLE `suppliercontractequipments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `supplierscontracts`
--
ALTER TABLE `supplierscontracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `supplier_contract_notes`
--
ALTER TABLE `supplier_contract_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `timesheet`
--
ALTER TABLE `timesheet`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=250;

--
-- AUTO_INCREMENT for table `timesheet_approvals`
--
ALTER TABLE `timesheet_approvals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `timesheet_approval_notes`
--
ALTER TABLE `timesheet_approval_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `timesheet_failure_hours`
--
ALTER TABLE `timesheet_failure_hours`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'معرف فريد', AUTO_INCREMENT=19;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_audit_log`
--
ALTER TABLE `admin_audit_log`
  ADD CONSTRAINT `fk_admin_audit_admin` FOREIGN KEY (`admin_id`) REFERENCES `super_admins` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `admin_companies`
--
ALTER TABLE `admin_companies`
  ADD CONSTRAINT `fk_admin_companies_plan` FOREIGN KEY (`plan_id`) REFERENCES `admin_subscription_plans` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `admin_subscription_requests`
--
ALTER TABLE `admin_subscription_requests`
  ADD CONSTRAINT `fk_admin_sub_req_plan` FOREIGN KEY (`plan_id`) REFERENCES `admin_subscription_plans` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_admin_sub_req_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `super_admins` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `approval_steps`
--
ALTER TABLE `approval_steps`
  ADD CONSTRAINT `fk_approval_steps_request` FOREIGN KEY (`request_id`) REFERENCES `approval_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `company_user_password_resets`
--
ALTER TABLE `company_user_password_resets`
  ADD CONSTRAINT `fk_company_user_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `contractequipments`
--
ALTER TABLE `contractequipments`
  ADD CONSTRAINT `fk_contractequipments_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `contracts`
--
ALTER TABLE `contracts`
  ADD CONSTRAINT `fk_contracts_merged` FOREIGN KEY (`merged_with`) REFERENCES `contracts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

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
-- Constraints for table `modules`
--
ALTER TABLE `modules`
  ADD CONSTRAINT `modules_ibfk_1` FOREIGN KEY (`owner_role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `project`
--
ALTER TABLE `project`
  ADD CONSTRAINT `fk_project_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
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
