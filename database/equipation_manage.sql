-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 24, 2026 at 03:52 PM
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
(1, 1, 'logout', 'جلسة الإدارة العليا', 1, 'جلسة غير مكتملة', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 17:30:53'),
(2, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 17:31:02'),
(3, 1, 'create', 'مدير أعلى', 2, 'إنشاء حساب مدير أعلى جديد: medo@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 17:32:54'),
(4, 1, 'logout', 'جلسة الإدارة العليا', 1, 'تسجيل خروج يدوي من المستخدم', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 17:33:03'),
(5, NULL, 'login', 'جلسة الإدارة العليا', 2, 'تسجيل الدخول بنجاح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 17:33:24'),
(6, NULL, 'update', 'مدير أعلى', 2, 'تحديث بيانات مدير أعلى: medo@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 17:34:07'),
(7, NULL, 'update', 'مدير أعلى', 2, 'تحديث بيانات مدير أعلى: medo@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 17:34:20'),
(8, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-23 15:18:24'),
(9, 1, 'logout', 'جلسة الإدارة العليا', 1, 'تسجيل خروج يدوي من المستخدم', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-23 15:36:35'),
(10, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-23 15:36:51'),
(11, 1, 'create', 'شركة', 1, 'إضافة شركة جديدة: الدهاب', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-23 15:37:59'),
(12, 1, 'logout', 'جلسة الإدارة العليا', 1, 'تسجيل خروج يدوي من المستخدم', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-23 15:39:23'),
(13, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-23 15:39:41'),
(14, 1, 'create', 'شركة', 2, 'إضافة شركة جديدة: الحمار', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-23 15:46:36'),
(15, 1, 'create', 'شركة', 3, 'إضافة شركة جديدة: ايكوبيشن', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-23 15:47:28'),
(16, 1, 'update', 'شركة', 3, 'تحديث بيانات الشركة: ايكوبيشن', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-23 16:25:46'),
(17, 1, 'logout', 'جلسة الإدارة العليا', 1, 'انتهت الجلسة بسبب عدم النشاط', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-23 17:22:45'),
(18, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-23 17:22:48'),
(19, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-23 17:27:16'),
(20, 1, 'approve', 'طلب اشتراك', 2, 'قبول الطلب وإنشاء الشركة والمدير العام', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-23 17:29:08'),
(21, 1, 'logout', 'جلسة الإدارة العليا', 1, 'تسجيل خروج يدوي من المستخدم', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-23 17:32:15'),
(22, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-23 17:32:57'),
(23, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-23 19:41:59'),
(24, 1, 'logout', 'جلسة الإدارة العليا', 1, 'تسجيل خروج يدوي من المستخدم', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-23 19:42:21'),
(25, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-23 19:44:02'),
(26, 1, 'update', 'مدير أعلى', 1, 'تحديث بيانات مدير أعلى: enjaz@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-23 20:00:18'),
(27, 1, 'logout', 'جلسة الإدارة العليا', 1, 'تسجيل خروج يدوي من المستخدم', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-23 20:00:34'),
(28, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-23 20:01:01'),
(29, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-23 20:04:16'),
(30, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-23 20:45:45'),
(31, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-23 21:19:44'),
(32, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-23 21:55:19'),
(33, 1, 'logout', 'جلسة الإدارة العليا', 1, 'تسجيل خروج يدوي من المستخدم', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-23 21:55:24'),
(34, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-23 21:57:27'),
(35, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-24 06:03:40'),
(36, 1, 'logout', 'جلسة الإدارة العليا', 1, 'تسجيل خروج يدوي من المستخدم', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-24 06:17:15'),
(37, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-24 06:17:53'),
(38, 1, 'logout', 'جلسة الإدارة العليا', 1, 'تسجيل خروج يدوي من المستخدم', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-24 06:19:39'),
(39, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-24 06:20:12'),
(40, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-24 06:20:29'),
(41, 1, 'approve', 'طلب اشتراك', 2, 'قبول الطلب وإنشاء الشركة والمدير العام', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-24 06:26:55'),
(42, 1, 'update', 'شركة', 2, 'تحديث بيانات الشركة: إيكوبيشن', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-24 06:27:25'),
(43, 1, 'update_password', 'شركة', 2, 'تحديث كلمة مرور مستخدم الشركة: #15', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-24 06:35:38'),
(44, 1, 'update_password', 'شركة', 2, 'تحديث كلمة مرور مستخدم الشركة: #15', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-24 06:44:42'),
(45, 1, 'update_password', 'شركة', 2, 'تحديث كلمة مرور مستخدم الشركة: #15', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-24 06:45:05'),
(46, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-24 08:23:48'),
(47, 1, 'suspend', 'شركة', 2, 'تعليق الشركة رقم #2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-24 08:31:10'),
(48, 1, 'activate', 'شركة', 2, 'تفعيل الشركة رقم #2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-24 08:36:50'),
(49, 1, 'update', 'شركة', 2, 'تحديث بيانات الشركة: إيكوبيشن', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-24 08:43:08'),
(50, 1, 'activate', 'شركة', 2, 'تفعيل الشركة رقم #2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-24 08:43:59'),
(51, 1, 'logout', 'جلسة الإدارة العليا', 1, 'تسجيل خروج يدوي من المستخدم', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-24 09:22:41'),
(52, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-24 09:30:32'),
(53, 1, 'logout', 'جلسة الإدارة العليا', 1, 'تسجيل خروج يدوي من المستخدم', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-24 09:30:40'),
(54, 1, 'login', 'جلسة الإدارة العليا', 1, 'تسجيل الدخول بنجاح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2026-03-24 09:30:45');

-- --------------------------------------------------------

--
-- Table structure for table `admin_companies`
--

CREATE TABLE `admin_companies` (
  `id` int(11) NOT NULL,
  `company_name` varchar(200) NOT NULL,
  `commercial_registration` varchar(120) DEFAULT NULL,
  `sector` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `tax_number` varchar(120) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `postal_address` text DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `plan_id` int(11) DEFAULT NULL,
  `modules_enabled` text DEFAULT NULL,
  `name` varchar(200) DEFAULT NULL,
  `company_name_ar` varchar(200) DEFAULT NULL,
  `company_name_en` varchar(200) DEFAULT NULL,
  `status` enum('pending','active','suspended','cancelled') NOT NULL DEFAULT 'pending',
  `subscription_start` date DEFAULT NULL,
  `subscription_end` date DEFAULT NULL,
  `users_count` int(11) NOT NULL DEFAULT 0,
  `max_users` int(11) NOT NULL DEFAULT 0,
  `max_equipments` int(11) NOT NULL DEFAULT 0,
  `max_projects` int(11) NOT NULL DEFAULT 0,
  `currency` varchar(20) NOT NULL DEFAULT 'SAR',
  `timezone` varchar(64) NOT NULL DEFAULT 'Asia/Riyadh',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_companies`
--

INSERT INTO `admin_companies` (`id`, `company_name`, `commercial_registration`, `sector`, `country`, `city`, `tax_number`, `email`, `phone`, `address`, `postal_address`, `logo_path`, `plan_id`, `modules_enabled`, `name`, `company_name_ar`, `company_name_en`, `status`, `subscription_start`, `subscription_end`, `users_count`, `max_users`, `max_equipments`, `max_projects`, `currency`, `timezone`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'الجلابي', '123123', 'مقاولات', 'السودان', 'عطبرة', '7878378738', 'info@galaby.com', '0938938933993', 'عطبرة', 'عطبرة', NULL, 1, 'projects', 'الجلابي', 'الجلابي', 'Elgalaby', 'active', '2026-03-23', NULL, 1, 3, 1, 1, 'SAR', 'Asia/Riyadh', NULL, '2026-03-23 20:48:22', '2026-03-23 20:48:22'),
(2, 'إيكوبيشن', '12345678', 'تعدين', 'السودان', 'عطبرة', '54321', 'info@equipation.com', '094898498494', '', 'عطبرة الطابق ال 3', '', 2, 'projects', 'إيكوبيشن', 'إيكوبيشن', 'Equipation', 'active', NULL, NULL, 1, 1000, 500, 100, 'SAR', 'Asia/Riyadh', NULL, '2026-03-24 06:26:55', '2026-03-24 08:43:59');

-- --------------------------------------------------------

--
-- Table structure for table `admin_subscription_plans`
--

CREATE TABLE `admin_subscription_plans` (
  `id` int(11) NOT NULL,
  `plan_name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `max_users` int(11) NOT NULL DEFAULT 0 COMMENT '0 = unlimited',
  `max_projects` int(11) NOT NULL DEFAULT 0,
  `max_equipments` int(11) NOT NULL DEFAULT 0,
  `features` text DEFAULT NULL COMMENT 'newline-separated feature list',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_subscription_plans`
--

INSERT INTO `admin_subscription_plans` (`id`, `plan_name`, `price`, `max_users`, `max_projects`, `max_equipments`, `features`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'مجاني', 0.00, 3, 1, 1, 'تجربة ذاتية فورية\n1 مشروع\n1 معدة\n3 مستخدمين', 1, 1, '2026-03-23 20:02:14', '2026-03-23 20:45:53'),
(2, 'pro', 100.00, 1000, 100, 500, 'تقارير قوية', 1, 1, '2026-03-23 20:02:55', '2026-03-23 20:02:55');

-- --------------------------------------------------------

--
-- Table structure for table `admin_subscription_requests`
--

CREATE TABLE `admin_subscription_requests` (
  `id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL COMMENT 'null if company not created yet',
  `company_name` varchar(200) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `plan_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL COMMENT 'message from the requesting company',
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL COMMENT 'super_admins.id',
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_subscription_requests`
--

INSERT INTO `admin_subscription_requests` (`id`, `company_id`, `company_name`, `email`, `phone`, `plan_id`, `message`, `status`, `reviewed_by`, `reviewed_at`, `review_note`, `created_at`) VALUES
(1, NULL, 'إيكوبيشن', 'info@equipation.com', '+20123456789', 1, '{\"company_name_en\":\"Equipation\",\"commercial_registration\":\"123456789\",\"sector\":\"تعدين\",\"country\":\"السودان\",\"city\":\"عطبرة\",\"tax_number\":\"9876543210\",\"postal_address\":\"عطبرة سوق عطبرة الطابق التالت\",\"modules_enabled\":\"projects\",\"currency\":\"SAR\",\"timezone\":\"Asia\\/Riyadh\",\"max_users\":0,\"max_equipments\":0,\"max_projects\":0,\"manager_name\":\"مستر محمد ادريس\",\"manager_email\":\"info@mah.com\",\"manager_phone\":\"+20123456789\",\"source\":\"company_register\"}', 'rejected', 1, '2026-03-24 06:26:43', 'تم الاشتراك في الباقة المدفوعة', '2026-03-23 20:10:45'),
(2, 2, 'إيكوبيشن', 'info@equipation2.com', '094898498494', 2, '{\"company_name_en\":\"Equipation\",\"commercial_registration\":\"12345678\",\"sector\":\"تعدين\",\"country\":\"السودان\",\"city\":\"عطبرة\",\"tax_number\":\"54321\",\"postal_address\":\"عطبرة الطابق ال 3\",\"modules_enabled\":\"projects\",\"currency\":\"SAR\",\"timezone\":\"Asia\\/Riyadh\",\"max_users\":1000,\"max_equipments\":500,\"max_projects\":100,\"manager_name\":\"مستر محمد ادريس\",\"manager_email\":\"medoit@gmail.com\",\"manager_phone\":\"098398393303\",\"source\":\"company_register\"}', 'approved', 1, '2026-03-24 06:26:55', 'تم الدفع بنجاح\nتم إنشاء حساب المدير العام. كلمة المرور المؤقتة: XK%L@kb4gYLL', '2026-03-24 06:25:57');

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'تاريخ آخر تحديث'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول العملاء';

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `company_id`, `client_code`, `client_name`, `entity_type`, `sector_category`, `phone`, `email`, `whatsapp`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 2, 'C002', 'وزارة البنية التحتية', 'خاص', 'بنية تحتية', '76887534', 'infotelecomwasla@gmail.com', '', 'نشط', 1, '2026-02-16 22:12:46', '2026-03-24 08:30:28'),
(2, 2, 'CL-0015', 'شركة المستقبل للمقاولات', 'حكومي', 'بنية تحتية', '249123456789', 'info@future-co.com', '249123456789', 'نشط', 1, '2026-02-25 18:16:23', '2026-03-24 08:45:11'),
(3, 2, 'C001', 'إيكوبيشن', 'حكومي', 'بنية تحتية', '0928999999', 'sudan@gmail.com', '0912345678', 'نشط', 15, '2026-03-24 08:35:44', '2026-03-24 08:35:44');

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
(3, NULL, 2, '1', 120, 2, 2, 2, 0, '', NULL, NULL, NULL, NULL, 10, 20, 0, 600, 0.00, 0, 0, 0, 0, '', '2026-02-27 23:44:25'),
(4, NULL, 2, '2', 340, 2, 1, 1, 0, '', NULL, NULL, NULL, NULL, 10, 20, 0, 600, 0.00, 0, 0, 0, 0, '', '2026-02-27 23:44:25'),
(5, NULL, 2, '3', 120, 3, 1, 1, 0, '', NULL, NULL, NULL, NULL, 10, 30, 0, 900, 0.00, 0, 0, 0, 0, '', '2026-02-27 23:44:25'),
(6, NULL, 1, '1', 240, 4, 0, 0, 0, '', NULL, NULL, NULL, NULL, 10, 40, 0, 1240, 0.00, 0, 0, 0, 0, '', '2026-03-03 13:32:09'),
(7, NULL, 1, '2', 240, 2, 0, 0, 0, '', NULL, NULL, NULL, NULL, 10, 20, 0, 620, 0.00, 1, 0, 0, 1, '', '2026-03-03 13:32:09');

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
  `status` tinyint(1) DEFAULT 1 COMMENT '1=نشط, 0=موقوف'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `contracts`
--

INSERT INTO `contracts` (`id`, `company_id`, `mine_id`, `contract_signing_date`, `grace_period_days`, `contract_duration_months`, `contract_duration_days`, `equip_shifts_contract`, `shift_contract`, `equip_total_contract_daily`, `total_contract_permonth`, `total_contract_units`, `actual_start`, `actual_end`, `transportation`, `accommodation`, `place_for_living`, `workshop`, `hours_monthly_target`, `forecasted_contracted_hours`, `created_at`, `updated_at`, `daily_work_hours`, `daily_operators`, `first_party`, `second_party`, `witness_one`, `witness_two`, `price_currency_contract`, `paid_contract`, `payment_time`, `guarantees`, `payment_date`, `contract_status`, `pause_reason`, `pause_date`, `resume_date`, `termination_type`, `termination_reason`, `merged_with`, `status`) VALUES
(1, NULL, 1, '2026-02-01', 3, 0, 31, 1, 1, 1, 1, 1, '2026-02-01', '2026-03-03', 'مالك المشروع', 'مالك المعدة', 'مالك المعدة', 'مالك المعدة', 60, 1860, '2026-02-16 22:17:51', '2026-03-03 12:32:25', '20', '2', '', '', '', '', 'دولار', '2000', 'مقدم', 'شيك', '2026-02-03', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1),
(2, NULL, 2, '2026-02-01', 5, 0, 31, 2, 10, 10, 0, 0, '2026-02-01', '2026-03-03', '', '', '', '', 70, 2100, '2026-02-27 23:44:25', NULL, '20', '', '', '', '', '', 'دولار', '2000', ' مؤخر', 'شيك', '2026-02-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1);

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
(1, NULL, 1, 'طلب تحديث الخدمات بالعقد', 1, '2026-03-03 12:32:25', NULL);

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
(1, NULL, 'محمد سيد', '', '', '', '', NULL, '', '', NULL, '', '', NULL, NULL, '', '', '', NULL, '', '', NULL, '', '', '', '', '', '', '', '', '', '', '', '', 'نشط', NULL, '2026-02-25 20:54:22', '01923329', '', 1),
(2, NULL, 'ahmed', 'ad', '', '', '', NULL, '', '', NULL, '', '', NULL, NULL, '', '', '', NULL, '', '', NULL, '', '', '', '', '', '', '', '', '', '', '', '', 'نشط', NULL, '2026-02-25 20:54:22', '98', '', 1),
(3, NULL, 'محمد أحمد علي', 'OPR-001-2026', 'أبو محمد', 'بطاقة هوية وطنية', '123456789123', '2028-12-31', 'DL-2024-456789', 'فئة د (شاحنات ثقيلة)', '2027-06-30', 'إدارة المرور - الخرطوم', 'حفارة (Excavator), شاحنة قلابة (Dump Truck)', 8, 5, 'خبير (5-10 سنوات)', 'شهادة تشغيل حفارات من معهد التعدين', 'محمد علي', 3, 'تابع لمالك المعدة مباشرة', 'شهري', 3500.00, 'mohammed@example.com', 'شارع النيل، الخرطوم', 'ممتاز', 'ممتاز (لا توجد شكاوى)', 'نظيف (لا توجد حوادث)', 'سليم تماماً', '', 'محدثة', 'شركة الذهب للتعدين', '3 سنوات', 'محمود أحمد - مدير الأسطول (09-123-4567)', 'مشغل موثوق وذو كفاءة عالية', 'نشط', '2024-01-15', '2026-02-25 20:58:52', '+249-9-123-4567', '+249-9-765-4321', 0);

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
(1, NULL, '1', 'fr-FR', '1', 'tq1', '1212389', '', '', '', NULL, NULL, 'في حالة جيدة', NULL, 'جيدة', 'N/A', '', '', '', '', '', '', NULL, '', NULL, '', 'متاحة للعمل', NULL, NULL, NULL, '', '', NULL, 1),
(2, NULL, '2', 'Wq1', '1', 'Wq1', '1111', '', '', '', NULL, NULL, 'في حالة جيدة', NULL, 'جيدة', 'N/A', '', '', '', '', '', '', NULL, '', NULL, '', 'موقوفة للصيانة', NULL, NULL, NULL, '', '', NULL, 1),
(3, NULL, '2', 'swd', '1', 'swd', '', '', '', '', NULL, NULL, 'في حالة جيدة', NULL, 'جيدة', 'N/A', '', '', '', '', '', '', NULL, '', NULL, '', 'متاحة للعمل', NULL, NULL, NULL, '', '', NULL, 1),
(4, NULL, '2', 'EQ-001', '1', 'حفار كاتربيلر 320', 'EXC-2024-001', 'CAT320-ABC123456', 'كاتربيلر', '320D', 2018, 2020, 'في حالة جيدة', 5400, 'جيدة', 'N/A', 'محمد علي أحمد', 'مالك فردي', '+249-912345678', 'تابع للمورد (مملوكة للمورد نفسه)', 'VEH-2024-12345', 'المرور', '2025-12-31', 'INS-2024-001', '2024-06-15', 'منجم الذهب الشرقي', 'متاحة للعمل', 150000.00, 500.00, 10000.00, 'مؤمن بالكامل', 'معدة موثوقة، تحتاج صيانة دورية كل 3 أشهر', '2024-05-10', 1),
(5, NULL, '2', 'EQ-002', '2', 'شاحنة قلاب هيونداي', 'TRK-2024-002', 'HYN-DEF789012', 'هيونداي', 'HD270', 2019, 2021, 'جديدة نسبياً (أقل من سنة استخدام)', 2800, 'ممتازة', 'جيدة', 'أحمد محمد علي', 'شركة متخصصة', '+249-923456789', 'مالك مباشر (يتعاقد معنا مباشرة)', 'VEH-2024-67890', 'وزارة النقل', '2026-03-15', 'INS-2024-002', '2024-07-20', 'مستودع الخرطوم', 'قيد الاستخدام', 80000.00, 300.00, 7000.00, 'مؤمن جزئياً', 'شاحنة جديدة بحالة ممتازة', '2024-06-01', 1),
(6, NULL, '1', 'TQ-001', '1', 'حفار كاتر', 'EXC-2024-001', 'CAT320-ABC123456', 'كاتربيلر', '320D', 2018, 2020, 'في حالة جيدة', 5400, 'جيدة', 'N/A', 'محمد علي أحمد', 'مالك فردي', '+249-912345678', 'تابع للمورد (مملوكة للمورد نفسه)', 'VEH-2024-12345', 'المرور', '2025-12-31', 'INS-2024-001', '2024-06-15', 'منجم الذهب الشرقي', 'متاحة للعمل', 150000.00, 500.00, 10000.00, 'مؤمن بالكامل', 'معدة موثوقة، تحتاج صيانة دورية كل 3 أشهر', '2024-05-10', 1),
(7, NULL, '1', 'TQ-002', '1', 'هيونداي', 'TRK-2024-002', 'HYN-DEF789012', 'هيونداي', 'HD270', 2019, 2021, 'جديدة نسبياً (أقل من سنة استخدام)', 2800, 'ممتازة', 'جيدة', 'أحمد محمد علي', 'شركة متخصصة', '+249-923456789', 'مالك مباشر (يتعاقد معنا مباشرة)', 'VEH-2024-67890', 'وزارة النقل', '2026-03-15', 'INS-2024-002', '2024-07-20', 'مستودع الخرطوم', 'قيد الاستخدام', 80000.00, 300.00, 7000.00, 'مؤمن جزئياً', 'شاحنة جديدة بحالة ممتازة', '2024-06-01', 1),
(8, NULL, '4', 'm1', '1', 'm1', '', '', '', '', NULL, NULL, 'في حالة جيدة', NULL, 'جيدة', 'N/A', '', '', '', '', '', '', NULL, '', NULL, '', 'متاحة للعمل', NULL, NULL, NULL, '', '', NULL, 1),
(9, NULL, '4', 'm130', '2', 'm130', '', '', '', '', NULL, NULL, 'في حالة جيدة', NULL, 'جيدة', 'N/A', '', '', '', '', '', '', NULL, '', NULL, '', 'متاحة للعمل', NULL, NULL, NULL, '', '', NULL, 1),
(10, NULL, '4', 'mg120', '3', 'mg120', '', '', '', '', NULL, NULL, 'في حالة جيدة', NULL, 'جيدة', 'N/A', '', '', '', '', '', '', NULL, '', NULL, '', 'متاحة للعمل', NULL, NULL, NULL, '', '', NULL, 1);

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
(23, NULL, 2, 2, '2026-02-01', '2026-02-28', 1),
(24, NULL, 7, 1, '2026-02-04', '2026-02-06', 0),
(25, NULL, 7, 3, '2026-02-10', '2099-12-31', 0),
(26, NULL, 7, 1, '2026-02-10', '2099-12-31', 1);

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'تاريخ آخر تحديث'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول المناجم المرتبطة بالمشاريع';

--
-- Dumping data for table `mines`
--

INSERT INTO `mines` (`id`, `company_id`, `project_id`, `mine_name`, `mine_code`, `manager_name`, `mineral_type`, `mine_type`, `mine_type_other`, `ownership_type`, `ownership_type_other`, `mine_area`, `mine_area_unit`, `mining_depth`, `contract_nature`, `status`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, NULL, 1, 'منجم احمد', 'منجم', 'سامبا', 'ذهب', 'حفرة مفتوحة', '', 'تعدين أهلي/تقليدي', '', NULL, 'هكتار', NULL, '', 1, '', 1, '2026-02-16 22:14:04', '2026-02-16 22:14:04'),
(2, NULL, 2, 'منجم اليونان', 'rox230', 'سامبا', 'ذهب', 'تحت أرضي', '', 'تعدين أهلي/تقليدي', '', NULL, 'هكتار', NULL, 'موظف مباشر لدى المالك', 1, '', 1, '2026-02-27 23:42:13', '2026-02-27 23:42:13'),
(3, NULL, 3, 'منجم حمد', 'MI192', 'محمد سيد', 'ذهب', 'حفرة مفتوحة', '', 'شركة حكومية/قطاع عام', '', 500.00, 'هكتار', 588.00, 'موظف مباشر لدى المالك', 1, '', 15, '2026-03-24 09:16:28', '2026-03-24 09:16:28');

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
(7, 'شاشاة المشغلين', 'Drivers/drivers.php', 4, '0', 'fa fa-id-card'),
(8, 'معدات الاسطول', 'Equipments/equipments_fleet.php', 3, '1', 'fa fa-tractor'),
(9, 'شاشة التشغيل', 'Oprators/oprators.php', 6, '', ''),
(10, 'صفحة الساعات', 'Timesheet/timesheet_type.php', 5, '1', 'fa fa-business-time'),
(11, 'الإعدادات', 'Settings/settings.php', 1, '1', 'fa fa-gear'),
(12, 'شاشة المشرفين', 'main/project_users.php', 1, '1', 'fa fa-users-cog'),
(14, 'شاشة المشرفين', 'main/project_users.php', 2, '1', 'fa fa-users-cog'),
(15, 'شاشة المشرفين', 'main/project_users.php', 3, '1', 'fa fa-users-cog'),
(16, 'شاشة المشرفين', 'main/project_users.php', 4, '0', 'fa fa-users-cog'),
(17, 'شاشة المشرفين', 'main/project_users.php', 5, '1', 'fa fa-users-cog'),
(18, 'شاشة المشرفين', 'main/project_users.php', 6, '', ''),
(19, 'شاشة المناجم', 'Projects/project_mines.php', 1, '0', ''),
(20, 'شاشة عقود المشاريع', 'Contracts/contracts.php', 1, '0', ''),
(21, 'تفاصيل عقد المشاريع', 'Contracts/contracts_details.php', 1, '0', ''),
(22, 'شاشة الموردين', 'Suppliers/suppliers.php', 2, '1', 'fa fa-truck-loading'),
(23, 'شاشة التشغيل', 'Oprators/oprators.php', 3, '1', 'fa fa-cogs'),
(24, 'الاعدادات', 'Settings/settings.php', 2, '1', 'fa fa-gear');

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
(4, NULL, '2', '1', 'أساسي', '1', '1', '1', '2', '2026-02-02', '2026-03-03', '', '0', 100.00, 10.00, 3),
(5, NULL, '8', '1', 'أساسي', '2', '2', '2', '4', '2026-02-01', '2026-03-03', '', '0', 200.00, 10.00, 1),
(6, NULL, '9', '2', 'أساسي', '2', '2', '2', '4', '2026-02-01', '2026-03-03', '', '0', 200.00, 10.00, 1),
(7, NULL, '10', '3', 'أساسي', '2', '2', '2', '4', '2026-02-01', '2026-03-30', 'sff', '57', 100.00, 10.00, 0),
(8, NULL, '2', '1', 'أساسي', '2', '2', '2', '2', '2026-03-01', '2026-03-30', '', '0', 10.00, 10.00, 1);

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
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'تاريخ آخر تحديث'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `project`
--

INSERT INTO `project` (`id`, `company_id`, `client_id`, `name`, `client`, `location`, `project_code`, `category`, `sub_sector`, `state`, `region`, `nearest_market`, `latitude`, `longitude`, `total`, `status`, `created_by`, `create_at`, `updated_at`) VALUES
(1, 2, 1, 'مشروع الروسيه جديد', 'وزارة البنية التحتية', 'الخرطوم2', 'PRJ-2026-001', '', 'التعدين', 'الخرطوم', 'الكويت', 'سوق بحري', '15.5527', '32.5599', '0', 1, 1, '2026-02-16 21:13:41', '2026-03-24 10:46:13'),
(2, 1, 2, 'مشروع اليونان', 'شركة المستقبل للمقاولات', 'الخرطوم2', 'PRJ-2026-071', '', 'التعدين', 'الخرطوم', '', '', '', '', '0', 1, 1, '2026-02-27 22:41:15', '2026-03-24 11:13:50'),
(3, 2, 3, 'مشروع الجزيرة', 'إيكوبيشن', 'نهر النيل', 'PRG4', 'فئة المشروع', 'قطاع غرب', 'نهر النيل', 'حفر الباطن', '', '', '', '0', 1, 15, '2026-03-24 09:15:07', NULL);

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
(8, 'مشرف موردين', 2, 2, '1', '2026-03-04 15:34:07'),
(10, 'مشرف اسطول', 3, 2, '1', '2026-03-07 10:37:24'),
(11, 'مشغل اسطول', 3, 2, '1', '2026-03-09 11:45:51');

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
(24, 1, 5, 1, 0, 0, 0),
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
(163, 2, 24, 1, 1, 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `super_admins`
--

CREATE TABLE `super_admins` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `super_admins`
--

INSERT INTO `super_admins` (`id`, `name`, `email`, `password`, `is_active`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'super', 'enjaz@gmail.com', '$2y$10$auVJYb4WXFejEfthvqjpSOtyZlfdzJxM18TH6NBhPvPMyNMPq0B8K', 1, '2026-03-24 09:30:45', '2026-03-18 13:49:17', '2026-03-24 09:30:45');

-- --------------------------------------------------------

--
-- Table structure for table `super_admin_password_resets`
--

CREATE TABLE `super_admin_password_resets` (
  `id` int(11) NOT NULL,
  `super_admin_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `super_admin_password_resets`
--

INSERT INTO `super_admin_password_resets` (`id`, `super_admin_id`, `token_hash`, `expires_at`, `used_at`, `created_at`) VALUES
(2, 1, 'c61c2b19674eed38b0382d55985d995ddfab638fdc898200872b016f0d515a9a', '2026-03-18 14:50:59', NULL, '2026-03-18 13:50:59');

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
(1, NULL, 1, '1', 340, 2, 0, 0, 0, '', '00:00:00', '00:00:00', '00:00:00', '00:00:00', 10.00, 20.00, 0.00, 600.00, 0.00, '', 0, 0, 0, 0, '2026-02-16 22:22:09'),
(2, NULL, 2, '1', 122, 1, 1, 0, 0, '', '00:00:00', '00:00:00', '00:00:00', '00:00:00', 5.00, 5.00, 0.00, 260.00, 0.00, '', 0, 0, 0, 0, '2026-02-27 23:57:27'),
(3, NULL, 2, '2', 130, 1, 1, 0, 0, '', '00:00:00', '00:00:00', '00:00:00', '00:00:00', 5.00, 5.00, 0.00, 260.00, 0.00, '', 0, 0, 0, 0, '2026-02-27 23:57:27'),
(4, NULL, 2, '3', 120, 1, 0, 0, 0, '', '00:00:00', '00:00:00', '00:00:00', '00:00:00', 5.00, 5.00, 0.00, 260.00, 0.00, '', 0, 0, 0, 0, '2026-02-27 23:57:27');

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
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `company_id`, `name`, `supplier_code`, `supplier_type`, `dealing_nature`, `equipment_types`, `commercial_registration`, `identity_type`, `identity_number`, `identity_expiry_date`, `email`, `phone_alternative`, `full_address`, `contact_person_name`, `contact_person_phone`, `financial_registration_status`, `created_at`, `updated_at`, `phone`, `status`) VALUES
(1, NULL, 'اكوبيشن', '123', 'شركة', 'متعاقد مباشر', 'حفارات, مكنات تخريم, دوازر', '234', 'جواز سفر', '1234', NULL, 'infotelecomwasla@gmail.com', '', '', '', '', 'مسجل رسميا', '2026-02-16 22:20:14', '2026-02-16 22:20:14', '76887534', 1),
(2, NULL, 'احمد', '1234', 'فرد', 'مورد معدات مباشر (مالك)', 'حفارات, معدات معالجة', '765', 'بطاقة هوية وطنية', '', NULL, 'a.samba12@gmail.com', '', '', '', '', '', '2026-02-16 22:20:56', '2026-02-16 22:20:56', '0920045986', 1),
(3, NULL, 'شركة النيل للمعدات الثقيلة', 'SUP-001', '', 'مباشر', 'حفار, قلاب, لودر', 'CR-123456', 'بطاقة شخصية', '123456789', '2027-12-31', 'info@nile-equip.com', '249987654321', 'الخرطوم - شارع النيل', 'أحمد محمد', '249123456789', '', '2026-02-25 19:37:15', '2026-02-25 19:37:15', '249123456789', 1),
(4, NULL, 'مؤسسة المستقبل للآليات', 'SUP-002', 'فرد', 'وسيط', '', 'CR-789012', 'جواز سفر', 'P987654321', '2028-06-30', 'contact@mustaqbal.sd', '249444555666', 'أم درمان - الموردة', 'محمد أحمد', '249111222333', 'غير مسجل', '2026-02-25 19:37:15', '2026-02-25 19:38:38', '249111222333', 1);

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
(1, NULL, 2, '2026-02-01', 5, 0, 31, 0, 0, 0, 0, 0, '2026-03-01', '2026-03-31', '', '', '', '', NULL, NULL, 0, 0, 0, 0, NULL, NULL, 0, 0, 0, 0, 20, 600, '2026-02-16 22:22:09', NULL, '20', '0', '', '', '', '', 'جنيه', '', '', '', '0000-00-00', 1, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(2, NULL, 4, '2026-02-01', 4, 0, 53, 0, 0, 0, 0, 0, '2026-02-01', '2026-03-25', '', '', '', '', NULL, NULL, 0, 0, 0, 0, NULL, NULL, 0, 0, 0, 0, 15, 780, '2026-02-27 23:57:27', NULL, '20', '0', '', '', '', '', 'دولار', '', '', '', '0000-00-00', 2, 2, 2, 1, NULL, NULL, NULL, NULL, NULL, NULL);

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
(1, NULL, '1', '2', 'D', '2026-02-17', 10, 8, 3, 5, 0, 0, 0, 0, 8, '', 0, 0, 0, 0, 0, 2, '', 0, 0, 0, 0, 0, 0, '0 ساعة 0 دقيقة 0 ثانية', '', '', '', '', '', 0, 0, 0, 0, 0, 0, '', '1', 5, 'لاتوجد ملاحظات', 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL COMMENT 'الاسم الثلاثي',
  `username` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` varchar(30) NOT NULL COMMENT 'رقم الصلاحية',
  `company_id` int(11) DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `force_password_change` tinyint(1) NOT NULL DEFAULT 0,
  `temp_password_set_at` timestamp NULL DEFAULT NULL,
  `project_id` varchar(20) NOT NULL DEFAULT '0',
  `mine_id` int(11) DEFAULT 0 COMMENT 'معرف المنجم لمدير الموقع',
  `contract_id` int(11) DEFAULT 0 COMMENT 'معرف العقد لمدير الموقع',
  `parent_id` varchar(20) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `email`, `password`, `phone`, `role`, `company_id`, `role_id`, `status`, `force_password_change`, `temp_password_set_at`, `project_id`, `mine_id`, `contract_id`, `parent_id`, `created_at`, `updated_at`, `last_login_at`) VALUES
(1, 'admin', 'admin', NULL, '2025', '0', '1', NULL, 1, 'active', 0, NULL, '0', 0, 0, '0', '2026-02-16 22:06:44', '2026-03-23 16:24:10', NULL),
(2, 'o', 'o', NULL, 'o', '0', '3', NULL, 3, 'active', 0, NULL, '0', 0, 0, '0', '2026-02-16 22:07:07', '2026-03-23 16:24:10', NULL),
(3, 'r', 'r', NULL, 'r', '5', '2', NULL, 2, 'active', 0, NULL, '0', 0, 0, '0', '2026-02-16 22:19:20', '2026-03-23 16:24:10', NULL),
(4, 'm', 'm', NULL, 'm', '0', '4', NULL, 4, 'active', 0, NULL, '0', 0, 0, '0', '2026-02-16 22:37:21', '2026-03-23 16:24:10', NULL),
(5, 'q', 'q', NULL, 'q', '5', '5', NULL, 5, 'active', 0, NULL, '1', 1, 1, '0', '2026-02-16 22:40:08', '2026-03-23 16:24:10', NULL),
(6, 'x', 'x', NULL, 'x', '6', '5', NULL, 5, 'active', 0, NULL, '1', 1, 1, '0', '2026-02-17 14:07:51', '2026-03-23 16:24:10', NULL),
(7, 't', 't', NULL, 't', '0', '10', NULL, 10, 'active', 0, NULL, '1', 1, 1, '0', '2026-02-22 15:06:10', '2026-03-23 16:24:10', NULL),
(8, 'موقع يونان', 'yyy', NULL, 'y', '989', '5', NULL, 5, 'active', 0, NULL, '2', 2, 2, '0', '2026-02-28 00:29:07', '2026-03-23 16:24:10', NULL),
(9, 'اواب', 'aw', NULL, 'aw', '09999', '7', NULL, 7, 'active', 0, NULL, '0', 0, 0, '1', '2026-03-04 15:44:28', '2026-03-23 16:24:10', NULL),
(10, 'حمادة', 'hm', NULL, 'hm', '09', '8', NULL, 8, 'active', 0, NULL, '0', 0, 0, '3', '2026-03-04 15:56:15', '2026-03-23 16:24:10', NULL),
(11, 'شيماء', 'sh', NULL, 'sh', '09', '10', NULL, 10, 'active', 0, NULL, '0', 0, 0, '2', '2026-03-07 11:30:17', '2026-03-23 16:24:10', NULL),
(12, 'علي', 'd', NULL, 'd', '09', '11', NULL, 11, 'active', 0, NULL, '0', 0, 0, '2', '2026-03-09 11:48:46', '2026-03-23 16:24:10', NULL),
(13, 'محمد سيد حسن غنيم', 'medo@gmail.com', 'medo@gmail.com', '$2y$10$Bkdijez09hoW2VPWEIvbDuFFUhq7xEV3WOlAuPPftgprCur2eg0pe', '0928999999', '1', 4, 1, 'active', 1, '2026-03-23 17:29:08', '0', 0, 0, '0', '2026-03-23 17:29:08', '2026-03-23 18:06:41', '2026-03-23 17:57:25'),
(14, 'محمد سيد حسن', 'medo2@gmail.com', 'medo2@gmail.com', '$2y$10$uam9DROqIaU3UGzkOMzwp.d1tNMT9.avvYBCBwNHv/zTOemw61OW2', '093888883', '1', 1, 1, 'active', 0, NULL, '0', 0, 0, '0', '2026-03-23 20:48:23', '2026-03-23 20:48:37', '2026-03-23 20:48:37'),
(15, 'مستر محمد ادريس', 'medoit@gmail.com', 'medoit@gmail.com', '$2y$10$DSgFmtlrinEiZc/SEYs3nelRtfduPrCupP53cweX..h/nwPpz7h1e', '098398393303', '1', 2, 1, 'active', 1, '2026-03-24 06:45:05', '0', 0, 0, '0', '2026-03-24 06:26:55', '2026-03-24 06:45:31', '2026-03-24 06:45:31');

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
  ADD UNIQUE KEY `unique_client_code` (`client_code`),
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
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_super_admins_email` (`email`);

--
-- Indexes for table `super_admin_password_resets`
--
ALTER TABLE `super_admin_password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_super_admin_password_resets_token_hash` (`token_hash`),
  ADD KEY `idx_super_admin_password_resets_admin_id` (`super_admin_id`);

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
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `idx_mine_id` (`mine_id`),
  ADD KEY `idx_contract_id` (`contract_id`),
  ADD KEY `idx_users_company_id` (`company_id`),
  ADD KEY `idx_users_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_audit_log`
--
ALTER TABLE `admin_audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `admin_companies`
--
ALTER TABLE `admin_companies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `admin_subscription_plans`
--
ALTER TABLE `admin_subscription_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `admin_subscription_requests`
--
ALTER TABLE `admin_subscription_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `admin_subscription_requests_test_probe`
--
ALTER TABLE `admin_subscription_requests_test_probe`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `company_user_password_resets`
--
ALTER TABLE `company_user_password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `modules`
--
ALTER TABLE `modules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `operations`
--
ALTER TABLE `operations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `project`
--
ALTER TABLE `project`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=164;

--
-- AUTO_INCREMENT for table `super_admins`
--
ALTER TABLE `super_admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `super_admin_password_resets`
--
ALTER TABLE `super_admin_password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

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
-- Constraints for table `super_admin_password_resets`
--
ALTER TABLE `super_admin_password_resets`
  ADD CONSTRAINT `fk_super_admin_password_resets_admin` FOREIGN KEY (`super_admin_id`) REFERENCES `super_admins` (`id`) ON DELETE CASCADE;

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
