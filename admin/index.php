<?php
/* ══ تجميدُ الشركةِ الواحدة (FREEZE · single-company) ══════════════════════
   ◆ **بابُ الإدارةِ العليا مُغلقٌ لا محذوف**: تحوَّل EMS من منصّةٍ متعددةِ
     المستأجرين إلى نظامِ شركةٍ واحدة، فبوابةُ دخولِ المزوّد لم يعد لها معنًى.
   ◆ **الإغلاقُ في الأعلى قبلَ أيِّ تحميلٍ أو جلسة** — لا مسارَ يُنشئ
     `$_SESSION['super_admin']` بعد اليوم، فـ`ems_platform_db()` و
     `TenantContext::fromSuperAdminSession()` يبقيان ميتَين بنيويًّا.
   ⛔ لا يُحذف الملفُّ ولا مُلحقاتُه — الإغلاقُ قرارُ تشغيلٍ قابلٌ للنقض. */
header('HTTP/1.1 403 Forbidden');
echo 'This portal has been permanently disabled.';
exit();

require_once __DIR__ . '/includes/auth.php';

if (super_admin_is_logged_in()) {
    super_admin_redirect('dashboard');
}

super_admin_redirect('login');