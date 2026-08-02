<?php
/**
 * includes/session_bootstrap.php — مخزن الجلسات المشترك (update0004 · NFR-13)
 * ───────────────────────────────────────────────────────────────────────────
 * يُحمَّل قبل أي session_start() عبر auto_prepend_file في .htaccess —
 * فيسجّل معالج جلسات القاعدة حين EMS_SESSION_STORE=db، ويبقى files افتراضًا
 * (قلب العلم = قلب المخزن، والرجوع فوري). قفل ملف الجلسة كان يجعل طلبات
 * المستخدم الواحد تنتظر بعضها — وصف القاعدة لا يفعل.
 */

if (PHP_SAPI === 'cli') { return; }
if (defined('EMS_SESSION_BOOTSTRAPPED')) { return; }
define('EMS_SESSION_BOOTSTRAPPED', 1);

require_once __DIR__ . '/env.php';
if (strtolower((string) ems_env('EMS_SESSION_STORE', 'files')) !== 'db') { return; }

require_once __DIR__ . '/EmsDbSessionHandler.php';


session_set_save_handler(new EmsDbSessionHandler(), true);
