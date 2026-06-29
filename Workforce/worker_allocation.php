<?php
/**
 * شاشة «تخصيص العامل» (worker_allocation) — أُلغيت / متقاعدة.
 * تم توحيد الإسناد على جدول equipment_drivers (العامل↔الآلية)، ويُدار إسناد السائقين
 * للآليات من وحدة الحركة (movement). جدول worker_allocation حُذف من النظام.
 * انظر: database/migrations/2026_06_28_retire_worker_allocation.sql
 */
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
header("Location: ../movement/movement_operations.php?msg=" . urlencode('شاشة تخصيص العامل أُلغيت — يُدار إسناد السائقين للآليات من وحدة الحركة'));
exit();
