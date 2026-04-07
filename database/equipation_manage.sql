-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 07, 2026 at 06:12 PM
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
(9, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-04-07 11:36:12');

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
(1, 'timesheet', 1, 'approve', '{\"summary\":{\"table\":\"timesheet\",\"operation\":\"update\",\"old_values\":{\"id\":\"1\",\"status\":\"1\",\"time_notes\":\"لاتوجد ملاحظات\"},\"new_values\":{\"status\":2,\"time_notes\":\"تم\"}},\"operations\":[{\"db_action\":\"update\",\"table\":\"timesheet\",\"where\":{\"id\":1},\"data\":{\"status\":2,\"time_notes\":\"تم\"}}]}', 8, 1, 'pending', NULL, NULL, NULL, NULL, '2026-04-07 15:11:42', '2026-04-07 15:11:42');

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
(1, 1, '-1', 1, NULL, NULL, 'pending', NULL, '2026-04-07 15:11:42');

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
(5, 4, 4, 'login', 'بوابة الشركة', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-04-07 11:33:56');

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
(1, 4, 'C001', 'شركة بايناتس', 'حكومي', 'بنية تحتية', '01123475758', 'sudan@gmail.com', '0912345678', 'نشط', 4, '2026-04-07 11:45:22', '2026-04-07 11:45:22', 0, NULL, NULL);

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
(2, NULL, 1, '2', 300, 5, 3, 2, 3, 'ساعة', '13:52:00', '13:53:00', '13:54:00', '13:55:00', 10, 50, 6000, 1000, 10.00, 3, 3, 3, 3, 'دولار', '2026-04-07 11:52:10');

-- --------------------------------------------------------

--
-- Table structure for table `contracts`
--

CREATE TABLE `contracts` (
  `id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
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
  `status` tinyint(1) DEFAULT 1 COMMENT '1=نشط, 0=موقوف',
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `contracts`
--

INSERT INTO `contracts` (`id`, `company_id`, `mine_id`, `contract_signing_date`, `grace_period_days`, `contract_duration_months`, `contract_duration_days`, `equip_shifts_contract`, `shift_contract`, `equip_total_contract_daily`, `total_contract_permonth`, `total_contract_units`, `actual_start`, `actual_end`, `transportation`, `accommodation`, `place_for_living`, `workshop`, `hours_monthly_target`, `forecasted_contracted_hours`, `created_at`, `updated_at`, `daily_work_hours`, `daily_operators`, `first_party`, `second_party`, `witness_one`, `witness_two`, `price_currency_contract`, `paid_contract`, `payment_time`, `guarantees`, `payment_date`, `contract_status`, `pause_reason`, `pause_date`, `resume_date`, `termination_type`, `termination_reason`, `merged_with`, `status`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES
(1, 4, 1, '2026-04-01', 10, 0, 21, 2, 10, 40, 6000, 6000, '2026-04-10', '2026-04-30', 'مالك المعدة', 'مالك المعدة', 'مالك المعدة', 'مالك المعدة', 100, 2000, '2026-04-07 11:52:10', NULL, '20', '3', 'محمد سيد', 'مبارك عوض', 'سمير الليل', 'مبارك محمود', 'دولار', '1000', 'مقدم', 'رهن سيارة', '2026-04-20', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL);

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
  `company_id` int(11) DEFAULT NULL,
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

INSERT INTO `drivers` (`id`, `company_id`, `name`, `driver_code`, `nickname`, `identity_type`, `identity_number`, `identity_expiry_date`, `license_number`, `license_type`, `license_expiry_date`, `license_issuer`, `specialized_equipment`, `years_in_field`, `years_on_equipment`, `skill_level`, `certificates`, `owner_supervisor`, `supplier_id`, `employment_affiliation`, `salary_type`, `monthly_salary`, `email`, `address`, `performance_rating`, `behavior_record`, `accident_record`, `health_status`, `health_issues`, `vaccinations_status`, `previous_employer`, `employment_duration`, `reference_contact`, `general_notes`, `driver_status`, `start_date`, `created_at`, `phone`, `phone_alternative`, `status`) VALUES
(1, 4, 'محمد سيد', 'DR1', 'Linda Workman', 'بطاقة هوية وطنية', '939', '1976-08-07', '661', 'فئة د (شاحنات ثقيلة)', '2007-11-30', 'Saepe nisi hic et se', 'حفارة (Excavator), دوزر (Dozer), شاحنة تناكر/صهريج (Tanker Truck), معدات أخرى', 4, 3, 'سيد حرفة (أكثر من 10 سنوات)', 'Dolorem ad maxime qu', 'Duis non autem paria', 1, 'تابع للمورد/الوسيط', 'يومي', 12.00, 'hefade@mailinator.com', 'Rerum dignissimos mo', 'جيد جداً', 'مقبول (بعض الشكاوى)', 'غير محدد', 'محتاج متابعة طبية', 'Mollitia corporis oc', 'قديمة', 'Non optio est et ma', 'Quisquam laudantium', 'Dolorem et laboriosa', 'Facere aliquid obcae', 'نشط', '1994-05-07', '2026-04-07 12:39:24', '+1 (165) 293-2817', '+1 (433) 539-7514', 1),
(2, 4, 'حسن سيد حسن', 'DR2', 'Halee Hancock', 'رخصة قيادة', '590', '1985-01-22', '79', 'فئة د (شاحنات ثقيلة)', '2002-05-10', 'Sed id delectus mol', 'حفارة (Excavator), دوزر (Dozer), شاحنة قلابة (Dump Truck), جرافة (Loader), ممهدة (Grader)', 3, 2, 'خبير (5-10 سنوات)', 'Cumque consequatur', 'Fugit anim minus id', 1, 'تابع لشركة متخصصة في التشغيل', 'حسب المشروع', 8.00, 'zusatipyx@mailinator.com', 'Ad eveniet aut volu', 'ممتاز', 'غير محدد', 'غير محدد', 'سليم تماماً', 'Pariatur A est eius', 'محدثة', 'Eos similique ea sus', 'Ea quis in excepturi', 'Qui ut voluptas poss', 'Aut laborum Ut sequ', 'نشط', '1990-05-29', '2026-04-07 12:40:06', '+1 (743) 975-1092', '+1 (834) 162-7231', 1);

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

INSERT INTO `equipments` (`id`, `company_id`, `suppliers`, `code`, `type`, `name`, `serial_number`, `chassis_number`, `manufacturer`, `model`, `manufacturing_year`, `import_year`, `equipment_condition`, `operating_hours`, `engine_condition`, `tires_condition`, `actual_owner_name`, `owner_type`, `owner_phone`, `owner_supplier_relation`, `license_number`, `license_authority`, `license_expiry_date`, `inspection_certificate_number`, `last_inspection_date`, `current_location`, `availability_status`, `estimated_value`, `daily_rental_price`, `monthly_rental_price`, `insurance_status`, `general_notes`, `last_maintenance_date`, `status`) VALUES
(1, 4, '1', 'EQTQ1', '1', 'Plato Roberson', '883', '751', 'Aut officia aut exce', 'Alias ducimus eius', 2001, 2008, 'في حالة ضعيفة', 40, 'جيدة', 'متوسطة', 'Keegan Blanchard', 'مؤسسة', '+1 (835) 262-4394', 'تابع للمورد (مملوكة للمورد نفسه)', '435', 'Eum alias nisi et si', '1980-03-25', '72', '2001-10-21', 'Non natus in qui exp', 'قيد الاستخدام', 81.00, 457.00, 596.00, 'مؤمن بالكامل', 'Eum nihil eveniet c', '1975-01-22', 1),
(2, 4, '1', 'EQTQ2', '1', 'Orson Holcomb', '195', '78', 'Nobis ipsum eum dolo', 'Eaque ut veniam et', 1978, 2004, 'في حالة جيدة', 98, 'محتاجة صيانة', 'محتاجة تبديل', 'Rama Delaney', 'شركة متخصصة', '+1 (338) 629-2108', 'غير محدد', '93', 'Consequatur non recu', '1989-06-19', '36', '1985-06-18', 'Et culpa corporis au', 'معطلة', 93.00, 259.00, 576.00, 'مؤمن بالكامل', 'Et sunt laboris volu', '1992-04-28', 1),
(3, 4, '1', 'EQEQ1', '2', 'Ashely Holloway', '740', '691', 'Non nisi ipsum nesc', 'Pariatur Ullamco fu', 1975, 1997, 'مستعملة بكثافة', 25, 'محتاجة صيانة', 'متوسطة', 'Kirk Burton', 'أخرى', '+1 (703) 267-4949', 'تحت وساطة المورد (المورد يدير المعدة نيابة عنه)', '387', 'Minima necessitatibu', '1998-11-26', '249', '2014-08-02', 'Deserunt occaecat id', 'مبيعة/مسحوبة', NULL, 31.00, 791.00, 'غير مؤمن', 'Consequatur id prov', '2008-05-12', 1),
(4, 4, '1', 'EQEQ2', '2', 'Amir Tate', '304', '962', 'Velit recusandae Vi', 'Nam aliquip consequa', 2000, 1977, 'معطلة مؤقتاً', 63, 'متوسطة', 'N/A', 'Keely Mckenzie', 'مؤسسة', '+1 (117) 553-1123', 'تابع للمورد (مملوكة للمورد نفسه)', '265', 'Modi corrupti dolor', '2020-01-22', '14', '2013-09-21', 'Fugiat ut possimus', 'قيد الاستخدام', 50.00, 356.00, 470.00, 'مؤمن جزئياً', 'Corporis in doloribu', '1998-08-19', 1);

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
(2, '2', 'قلاب', 'active', '2026-04-07 11:49:43', '2026-04-07 11:49:43');

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
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `equipment_drivers`
--

INSERT INTO `equipment_drivers` (`id`, `company_id`, `equipment_id`, `driver_id`, `start_date`, `end_date`, `status`) VALUES
(1, 4, 1, 1, '2026-04-01', '2026-04-30', 1),
(2, 4, 3, 2, '2026-04-01', '2026-04-30', 1);

-- --------------------------------------------------------

--
-- Table structure for table `mines`
--

CREATE TABLE `mines` (
  `id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'تاريخ آخر تحديث',
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول المناجم المرتبطة بالمشاريع';

--
-- Dumping data for table `mines`
--

INSERT INTO `mines` (`id`, `company_id`, `project_id`, `mine_name`, `mine_code`, `manager_name`, `mineral_type`, `mine_type`, `mine_type_other`, `ownership_type`, `ownership_type_other`, `mine_area`, `mine_area_unit`, `mining_depth`, `contract_nature`, `status`, `notes`, `created_by`, `created_at`, `updated_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES
(1, 4, 1, 'منجم 1', 'MIN01', 'محمد سيد', 'ذهب', 'حفرة مفتوحة', '', 'شركة سودانية خاصة', '', 100.00, 'هكتار', 100.00, 'موظف مباشر لدى المالك', 1, 'ملحوظات اضافية', 4, '2026-04-07 11:47:00', '2026-04-07 11:47:00', 0, NULL, NULL);

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
  `icon` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `modules`
--

INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `icon`) VALUES
(1, 'شاشة العملاء', 'Clients/clients.php', 1, '1', 'fa fa-users'),
(2, 'شاشة المشاريع', 'Projects/projects.php', 1, '1', 'fa fa-folder-open'),
(3, 'شاشة المستخدمين', 'main/users.php', 1, '', ''),
(4, 'شاشة التقارير', 'Reports/reports.php', 1, '', ''),
(5, 'شاشة انواع المعدات', 'Equipments/equipments_types.php', 1, '1', 'fa fa-tractor'),
(6, 'شاشة التقارير', 'Reports/reports.php', 2, '1', 'fa fa-chart-pie'),
(7, 'شاشاة المشغلين', 'Drivers/drivers.php', 4, '1', 'fa fa-link'),
(8, 'معدات الاسطول', 'Equipments/equipments_fleet.php', 3, '1', 'fa fa-tractor'),
(9, 'شاشة التشغيل', 'Oprators/oprators.php', 6, '1', 'fa fa-link'),
(10, 'صفحة الساعات', 'Timesheet/timesheet_type.php', 5, '1', 'fa fa-business-time'),
(11, 'الإعدادات', 'Settings/settings.php', 1, '1', 'fa fa-gear'),
(12, 'شاشة المشرفين', 'main/project_users.php', 1, '1', 'fa fa-users-cog'),
(14, 'شاشة المشرفين', 'main/project_users.php', 2, '1', 'fa fa-users-cog'),
(15, 'شاشة المشرفين', 'main/project_users.php', 3, '1', 'fa fa-users-cog'),
(16, 'شاشة المشرفين', 'main/project_users.php', 4, '1', 'fa fa-link'),
(17, 'شاشة المشرفين', 'main/project_users.php', 5, '1', 'fa fa-users-cog'),
(18, 'شاشة المشرفين', 'main/project_users.php', 6, '1', 'fa fa-link'),
(19, 'شاشة المناجم', 'Projects/project_mines.php', 1, '0', ''),
(20, 'شاشة عقود المشاريع', 'Contracts/contracts.php', 1, '0', ''),
(21, 'تفاصيل عقد المشاريع', 'Contracts/contracts_details.php', 1, '0', ''),
(22, 'شاشة الموردين', 'Suppliers/suppliers.php', 2, '1', 'fa fa-truck-loading'),
(23, 'شاشة التشغيل', 'Oprators/oprators.php', 3, '1', 'fa fa-cogs'),
(24, 'الاعدادات', 'Settings/settings.php', 2, '1', 'fa fa-gear'),
(25, 'عقود الموردين', 'Suppliers/supplierscontracts.php', 2, '0', 'fa fa-link'),
(26, 'تفاصيل عقد المورد', 'Suppliers/supplierscontracts_details.php', 2, '0', 'fa fa-link'),
(27, 'المعدات', 'Equipments/equipments_drivers.php', 4, '1', 'fa fa-link');

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

INSERT INTO `operations` (`id`, `company_id`, `equipment`, `equipment_type`, `equipment_category`, `project_id`, `mine_id`, `contract_id`, `supplier_id`, `start`, `end`, `reason`, `days`, `total_equipment_hours`, `shift_hours`, `status`) VALUES
(1, 4, '1', '1', 'أساسي', '1', '1', '1', '1', '2026-04-01', '2026-04-30', '', '0', 20.00, 10.00, 1),
(2, 4, '3', '2', 'أساسي', '1', '1', '1', '1', '2026-04-01', '2026-04-30', '', '0', 20.00, 10.00, 1);

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

INSERT INTO `project` (`id`, `company_id`, `client_id`, `name`, `client`, `location`, `project_code`, `category`, `sub_sector`, `state`, `region`, `nearest_market`, `latitude`, `longitude`, `total`, `status`, `created_by`, `create_at`, `updated_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES
(1, 4, 1, 'مشروع الروسية', 'شركة بايناتس', 'شمال غرب المناقل', 'PR1', 'فئة المشروع', 'فرعي', 'نهر النيل', 'حفر الباطن', 'سوق اللفة', '12', '31', '0', 1, 4, '2026-04-07 11:46:05', NULL, 0, NULL, NULL);

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
(1, 'مدير المشاريع', NULL, 1, '1', '2026-03-04 10:46:56'),
(2, 'مدير الموردين', NULL, 1, '1', '2026-03-04 10:47:22'),
(3, 'مدير الاسطول', NULL, 1, '1', '2026-03-04 10:47:41'),
(4, 'مدير المشغلين', NULL, 1, '1', '2026-03-04 10:50:24'),
(5, 'مدير الموقع', NULL, 1, '1', '2026-03-04 10:52:29'),
(6, 'مدير حركة وتشغيل', NULL, 1, '1', '2026-03-04 10:52:47'),
(7, 'مشرف - مشاريع', 1, 2, '1', '2026-03-04 13:18:15'),
(8, 'مشرف موردين', 2, 2, '1', '2026-03-04 13:34:07'),
(10, 'مشرف اسطول', 3, 2, '1', '2026-03-07 08:37:24'),
(11, 'مشغل اسطول', 3, 2, '1', '2026-03-09 09:45:51');

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
(20, 1, 1, 1, 1, 1, 1),
(21, 1, 2, 1, 1, 1, 1),
(22, 1, 3, 1, 1, 1, 1),
(23, 1, 4, 1, 1, 1, 1),
(24, 1, 5, 1, 1, 1, 1),
(25, 1, 11, 1, 1, 1, 1),
(26, 1, 12, 1, 1, 1, 1),
(27, 1, 19, 1, 1, 1, 1),
(28, 1, 20, 1, 1, 1, 1),
(29, 1, 21, 1, 1, 1, 1),
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
(41, 7, 19, 1, 0, 0, 0),
(42, 7, 20, 1, 0, 0, 0),
(49, 2, 1, 1, 1, 1, 1),
(50, 2, 2, 1, 1, 1, 1),
(51, 2, 3, 1, 1, 1, 1),
(52, 2, 4, 1, 1, 1, 1),
(53, 2, 5, 1, 1, 1, 1),
(54, 2, 11, 1, 1, 1, 1),
(55, 2, 12, 1, 1, 1, 1),
(56, 2, 19, 1, 1, 1, 1),
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
(77, 8, 19, 1, 1, 1, 1),
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
(121, 3, 19, 1, 1, 1, 1),
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
(138, 5, 17, 1, 1, 0, 0),
(139, 5, 10, 1, 1, 0, 0),
(141, 11, 1, 1, 1, 1, 1),
(142, 11, 2, 1, 1, 1, 1),
(143, 11, 3, 1, 1, 1, 1),
(144, 11, 4, 1, 1, 1, 1),
(145, 11, 5, 1, 1, 1, 1),
(146, 11, 11, 1, 1, 1, 1),
(147, 11, 12, 1, 1, 1, 1),
(148, 11, 19, 1, 1, 1, 1),
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
(176, 6, 19, 1, 1, 1, 1),
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
(201, 4, 19, 1, 1, 1, 1),
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
(219, 4, 27, 1, 1, 1, 1);

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
(1, 'super', 'enjaz@gmail.com', '$2y$10$auVJYb4WXFejEfthvqjpSOtyZlfdzJxM18TH6NBhPvPMyNMPq0B8K', 1, '2026-04-07 11:36:12', '2026-03-18 11:49:17', '2026-04-07 11:36:12');

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
(2, NULL, 1, '2', 300, 5, 3, 2, 2, 'ساعة', '14:22:00', '14:23:00', '14:24:00', '14:25:00', 10.00, 50.00, 6000.00, 1000.00, 10.00, 'دولار', 0, 3, 3, 3, '2026-04-07 12:22:54');

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
(2, 4, 'مورد 2', 'MOR2', 'شركة', 'وسيط', 'حفارات', '123', 'جواز سفر', 'P98909', '2026-04-30', 'equipation@gmail.com', '+1 (161) 121-1423', 'عنوان', 'Mary Washington', '+1 (584) 739-3927', '', '2026-04-07 11:54:24', '2026-04-07 12:18:42', '09144760109', 1, 0, NULL, NULL);

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

INSERT INTO `supplierscontracts` (`id`, `company_id`, `supplier_id`, `contract_signing_date`, `grace_period_days`, `contract_duration_months`, `contract_duration_days`, `equip_shifts_contract`, `shift_contract`, `equip_total_contract_daily`, `total_contract_permonth`, `total_contract_units`, `actual_start`, `actual_end`, `transportation`, `accommodation`, `place_for_living`, `workshop`, `equip_type`, `equip_size`, `equip_count`, `equip_target_per_month`, `equip_total_month`, `equip_total_contract`, `mach_type`, `mach_size`, `mach_count`, `mach_target_per_month`, `mach_total_month`, `mach_total_contract`, `hours_monthly_target`, `forecasted_contracted_hours`, `created_at`, `updated_at`, `daily_work_hours`, `daily_operators`, `first_party`, `second_party`, `witness_one`, `witness_two`, `price_currency_contract`, `paid_contract`, `payment_time`, `guarantees`, `payment_date`, `project_id`, `mine_id`, `project_contract_id`, `status`, `pause_reason`, `pause_date`, `resume_date`, `termination_type`, `termination_reason`, `merged_with`) VALUES
(1, 4, 1, '2026-04-01', 10, 0, 21, 2, 10, 100, 6000, 6000, '2026-04-10', '2026-04-30', 'مالك المعدة', 'مالك المعدة', 'مالك المعدة', 'مالك المعدة', NULL, NULL, 0, 0, 0, 0, NULL, NULL, 0, 0, 0, 0, 100, 2000, '2026-04-07 12:22:54', NULL, '20', '3', 'محمد سيد', 'سمير الو الليل', 'سمر الهاني', 'هاني المحامي', 'دولار', '1000', 'مقدم', 'رهن سيارة', '2026-04-20', 1, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL);

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
  `type` varchar(20) NOT NULL,
  `user_id` int(50) NOT NULL DEFAULT 0,
  `time_notes` text NOT NULL DEFAULT 'لاتوجد ملاحظات',
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `timesheet`
--

INSERT INTO `timesheet` (`id`, `company_id`, `operator`, `driver`, `shift`, `date`, `shift_hours`, `executed_hours`, `bucket_hours`, `jackhammer_hours`, `extra_hours`, `extra_hours_total`, `standby_hours`, `dependence_hours`, `total_work_hours`, `work_notes`, `hr_fault`, `maintenance_fault`, `marketing_fault`, `approval_fault`, `other_fault_hours`, `total_fault_hours`, `fault_notes`, `start_seconds`, `start_minutes`, `start_hours`, `end_seconds`, `end_minutes`, `end_hours`, `counter_diff`, `fault_type`, `fault_department`, `fault_part`, `fault_details`, `general_notes`, `operator_hours`, `machine_standby_hours`, `jackhammer_standby_hours`, `bucket_standby_hours`, `extra_operator_hours`, `operator_standby_hours`, `operator_notes`, `type`, `user_id`, `time_notes`, `status`) VALUES
(1, 4, '1', '1', 'D', '2026-04-07', 10, 10, 0, 0, 0, 0, 0, 0, 10, '', 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 10, 0, 0, 0, 0, 0, '', '1', 8, 'لاتوجد ملاحظات', 1),
(2, 4, '1', '', 'N', '2026-04-07', 10, 10, 77, 13, 22, 77, 59, 50, 146, 'Occaecat labore aliq', 18, 43, 4, 56, 96, 0, 'Est quia placeat qu', 19, 25, 99, 54, 49, 75, '100 ساعة 0 دقيقة 0 ثانية', 'Aliqua Voluptas nis', 'Sunt maiores commodo', 'Libero deserunt nost', 'Architecto quia nesc', 'Quaerat sint molliti', 10, 59, 0, 0, 0, 0, 'In consectetur molli', '1', 8, 'Voluptatum eaque dol', 1),
(3, 4, '1', '1', 'D', '2014-08-09', 10, 46, 43, 59, 10, 82, 1, 9, 129, 'Consequuntur quis id', 68, 90, 73, 25, 49, 0, 'Sapiente labore esse', 57, 21, 33, 0, 26, 83, '78 ساعة 4 دقيقة 3 ثانية', 'Optio exercitation ', 'Alias et quos omnis ', 'Id ut asperiores qua', 'Cum amet nisi volup', 'Numquam officia in v', 0, 1, 0, 0, 0, 0, 'Laboriosam ut ducim', '1', 8, 'Aut accusamus qui su', 1),
(4, 4, '2', '2', 'D', '2004-03-02', 10, 73, 0, 0, 0, 23, 65, 43, 161, 'Ad consequatur maio', 15, 97, 99, 26, 16, 0, 'Alias dolor aut qui ', 0, 0, 58, 0, 0, 15, '-43', 'Nostrud in cum reici', 'Cum tenetur consequa', 'Sunt unde velit do', 'Accusantium tempore', 'Natus necessitatibus', 0, 65, 0, 0, 0, 0, 'Et ut facilis quae i', '2', 8, 'Illo nesciunt ut re', 1),
(5, 4, '2', '2', 'N', '1989-12-07', 10, 35, 0, 0, 0, 85, 29, 12, 149, 'Quia et iure sed tem', 96, 42, 50, 99, 77, 0, 'Veniam eius volupta', 0, 0, 37, 0, 0, 58, '21', 'Aut pariatur Eum te', 'Numquam qui numquam ', 'Natus consequat Cil', 'Sed in mollit ea vol', 'Nihil maiores dicta ', 0, 29, 0, 0, 0, 0, 'Sed vel fuga Reicie', '2', 8, 'Et aute possimus vo', 1);

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
  `mine_id` int(11) DEFAULT 0 COMMENT 'المنجم',
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

INSERT INTO `users` (`id`, `name`, `username`, `email`, `password`, `phone`, `role`, `company_id`, `role_id`, `status`, `force_password_change`, `temp_password_set_at`, `project_id`, `mine_id`, `contract_id`, `parent_id`, `created_at`, `updated_at`, `last_login_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES
(1, 'محمد ادريس', 'adminfree@gmail.com', 'adminfree@gmail.com', '$2y$10$3I7ZYbnPjX9BEWzLR4HHz.FHcMPMzUBMnQNI3vBYLnBzIGUYI.UMG', '+249915657576', '1', 1, 1, 'active', 0, NULL, '0', 0, 0, '0', '2026-04-07 10:53:47', '2026-04-07 10:54:04', '2026-04-07 10:54:04', 0, NULL, NULL),
(4, 'مستر محمد ادريس', 'admin@gmail.com', 'admin@gmail.com', '$2y$10$8FcRlrkxuIOUr6kWAwy6Z.lh1rYmAzAA/8zSH7sxhgPAc69eQNLTG', '+249915657576', '1', 4, 1, 'active', 0, NULL, '0', 0, 0, '0', '2026-04-07 11:19:26', '2026-04-07 11:33:56', '2026-04-07 11:33:56', 0, NULL, NULL),
(5, 'موردين', 'مورد', NULL, '$2y$10$WA9lipyyjBky7B1zieAXPur.sdLhy.UlHy5Jj4q1IZYzP6B3tGTeq', '09209303903', '2', 4, NULL, 'active', 0, NULL, '0', 0, 0, '0', '2026-04-07 11:33:09', '2026-04-07 12:17:39', NULL, 0, NULL, NULL),
(6, 'الاسطول', 'اسطول', NULL, '$2y$10$dpgJiR7LQuaJVDgQIO/F4.ze3HXdjjT6OiflD/RS0C0VgxjmiBh4W', '09209303903', '3', 4, NULL, 'active', 0, NULL, '0', 0, 0, '0', '2026-04-07 11:34:52', '2026-04-07 12:17:42', NULL, 0, NULL, NULL),
(7, 'المشغلين', 'مشغل', NULL, '$2y$10$Jk5vHPG/HMIwfhP6x1mFC.t4mUs524htfwDDrCYWmKW/lv9tJvEzS', '09209303903', '4', 4, NULL, 'active', 0, NULL, '0', 0, 0, '0', '2026-04-07 11:35:27', '2026-04-07 12:17:46', NULL, 0, NULL, NULL),
(8, 'المواقع', 'موقع', NULL, '$2y$10$5ln6ocflqV231lrG01s9LOV4DZR.dHrEci8RJ7XeA/RbFpadupmY2', '09209303903', '5', 4, NULL, 'active', 0, NULL, '1', 1, 1, '0', '2026-04-07 12:45:25', '2026-04-07 12:45:25', NULL, 0, NULL, NULL);

--
-- Indexes for dumped tables
--

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
  ADD KEY `fk_project_created_by` (`created_by`),
  ADD KEY `idx_client_id` (`client_id`);

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
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `idx_mine_id` (`mine_id`),
  ADD KEY `idx_contract_id` (`contract_id`),
  ADD KEY `idx_users_company_id` (`company_id`),
  ADD KEY `idx_users_status` (`status`),
  ADD KEY `idx_users_is_deleted` (`is_deleted`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_audit_log`
--
ALTER TABLE `admin_audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `approval_steps`
--
ALTER TABLE `approval_steps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `approval_workflow_rules`
--
ALTER TABLE `approval_workflow_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `company_user_password_resets`
--
ALTER TABLE `company_user_password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contractequipments`
--
ALTER TABLE `contractequipments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `mines`
--
ALTER TABLE `mines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `modules`
--
ALTER TABLE `modules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `operations`
--
ALTER TABLE `operations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `project`
--
ALTER TABLE `project`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=220;

--
-- AUTO_INCREMENT for table `super_admins`
--
ALTER TABLE `super_admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'معرف فريد', AUTO_INCREMENT=2;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `supplier_contract_notes`
--
ALTER TABLE `supplier_contract_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `timesheet`
--
ALTER TABLE `timesheet`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'معرف فريد', AUTO_INCREMENT=9;

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
