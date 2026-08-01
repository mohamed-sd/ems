<?php
/**
 * Maintenance/breakdowns.php — تحويلٌ إلى شاشة البلاغات.
 *
 * البلاغاتُ صارت وحدةً مستقلّةً في Tickets/ بدورة حياةٍ كاملة وسجلِّ انتقالِ
 * ملكيّةٍ وخطِّ زمنٍ ومواعيدِ استحقاقٍ وتصعيدٍ ولوحة متابعة، ونُقلت إليها كلُّ
 * السجلّات المسجَّلة هنا.
 *
 * ويبقى هذا الملف ليصل أيُّ رابطٍ أو إشارةٍ مرجعيّةٍ محفوظةٍ إلى وجهته
 * الصحيحة بدل صفحة خطأ — ولا منطقَ فيه سوى التحويل.
 *
 * ملاحظة للمطوّر: جدول mnt_breakdown يبقى للقراءة فقط (لا كاتبَ له)، لأنّ
 * mnt_order.breakdown_id يشير إليه كمصدرٍ لأوامر الصيانة السابقة.
 * وإصدارُ أمر صيانةٍ من بلاغٍ متاحٌ الآن من شاشة التذكرة نفسها.
 */
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

// E-15/E-23 (SPEC-00 §3-②): كلُّ نقرةٍ على الميت تُعَدّ في nav_redirects —
// والحذفُ النهائيُّ قرارُ مالكٍ بعد أن يثبت العدّادُ صفرَه مدةً كافية.
include '../config.php';
$conn->query("UPDATE nav_redirects SET hits = hits + 1, last_hit_at = NOW()
               WHERE old_route = 'Maintenance/breakdowns.php' AND active = 1");
header("Location: ../Tickets/tickets_list.php");
exit();
