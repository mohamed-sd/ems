<?php
/**
 * 2027_03_14 — رابطان بعنوانٍ واحدٍ «الرئيسية» يقودان إلى صفحتَين مختلفتَين
 * ═══════════════════════════════════════════════════════════════════════════
 * **المقيسُ**: سايدبارُ كلِّ مستخدمٍ يعرض **رابطَين متطابقَي العنوان**:
 *   ① `main/role_board.php`   بأيقونةِ بيتٍ  — يطبعه **المُصيِّرُ** نفسُه
 *      (`includes/unified_nav.php:240`) بقرارِ المالك 2026-08-03: «الرئيسيةُ تؤدي
 *      دائمًا إلى لوحةِ الإدارة»، على مستوى المُصيِّر ليصمد أمام إعادةِ التوليد.
 *   ② `main/my_workspace.php` بأيقونةِ مستخدمٍ — **صفُّ بياناتٍ** في `nav_items`
 *      عنوانُه «الرئيسية» أيضًا، في مجموعةِ «لوحة الإدارة».
 *
 * فالمستخدمُ يرى «الرئيسية» مرتين ولا يستطيع التمييزَ بينهما، وإحداهما تأخذه
 * إلى لوحةِ إدارتِه والأخرى إلى مساحتِه الشخصية. و**32 صفًّا** كذلك — أي أن
 * العطبَ يمسُّ كلَّ دورٍ في النظام. وهو ما يفضحه `tests/nav_home_http_proof.php`
 * (يشترط رابطًا واحدًا فيجد اثنين، فتسقط أدوارُه الثلاثةُ كلُّها).
 *
 * ⇒ **لا يُمَسُّ رابطُ المُصيِّرِ** (قرارُ مالكٍ صريح)، بل يُسمَّى صفُّ البياناتِ بما
 *   **تُسمّي الشاشةُ نفسَها**: `main/my_workspace.php` عنوانُها في رأسِها
 *   `$page_title = 'إيكوبيشن | مساحة عملي'` — فالتسميةُ **مشتقّةٌ من المصدرِ لا
 *   مخترَعة**. ولا يُحذف صفٌّ ولا يُخفى: العنوانُ وحدَه يُصحَّح.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');
$one = function ($sql) use ($db) { $r = $db->query($sql); return $r ? $r->fetch_row()[0] : null; };

const WS_ROUTE = 'main/my_workspace.php';
const WS_LABEL = 'مساحة عملي';

/* ── ① الاسمُ يُقرأ من رأسِ الشاشةِ نفسِها لا يُكتب بيد ─────────────────────── */
$screen = dirname(__DIR__, 2) . '/' . WS_ROUTE;
$fromScreen = '';
if (is_file($screen) && preg_match('~\$page_title\s*=\s*[\'"][^\'"|]*\|\s*([^\'"]+)[\'"]~u',
        (string) file_get_contents($screen), $m)) {
    $fromScreen = trim($m[1]);
}
echo '── ① الشاشةُ تُسمّي نفسَها: «' . ($fromScreen !== '' ? $fromScreen : '—') . "»\n";
if ($fromScreen !== '' && $fromScreen !== WS_LABEL) {
    fwrite(STDERR, "الاسمُ في الشاشةِ «{$fromScreen}» يخالف المكتوبَ هنا «" . WS_LABEL . "» — يُراجَع قبل الكتابة\n");
    exit(1);
}
if ($fromScreen === '') {
    fwrite(STDERR, "لم يُقرأ عنوانُ الشاشة — لا يُخترع اسمٌ\n");
    exit(1);
}

/* ── ② حجمُ التصادم ──────────────────────────────────────────────────────── */
$clash = (int) $one("SELECT COUNT(*) FROM nav_items
                      WHERE active = 1 AND route = '" . WS_ROUTE . "' AND label_ar = 'الرئيسية'");
$roles = (int) $one("SELECT COUNT(DISTINCT role_id) FROM nav_items
                      WHERE active = 1 AND route = '" . WS_ROUTE . "' AND label_ar = 'الرئيسية'");
echo "── ② صفوفٌ عنوانُها «الرئيسية» وتقصد مساحةَ العمل: {$clash} في {$roles} دورًا\n";
if ($clash === 0) { echo "\n✅ لا تصادم — لا عمل.\n"; exit(0); }

/* ── ③ التسمية ───────────────────────────────────────────────────────────── */
$ok = $db->query("UPDATE nav_items SET label_ar = '" . $db->real_escape_string(WS_LABEL) . "'
                   WHERE active = 1 AND route = '" . WS_ROUTE . "' AND label_ar = 'الرئيسية'");
if ($ok === false) { fwrite(STDERR, 'تسميةٌ فشلت: ' . $db->error . "\n"); exit(1); }
echo '── ③ سُمّيت ' . $db->affected_rows . " صفًّا ⇐ «" . WS_LABEL . "»\n";

/* ── ④ الشاهد: لا دورَ فيه رابطان بعنوانِ «الرئيسية» ───────────────────────── */
$left = (int) $one("SELECT COUNT(*) FROM nav_items
                     WHERE active = 1 AND route = '" . WS_ROUTE . "' AND label_ar = 'الرئيسية'");
$dupRoles = (int) $one("SELECT COUNT(*) FROM (
                          SELECT role_id FROM nav_items
                           WHERE active = 1 AND label_ar = 'الرئيسية'
                           GROUP BY role_id HAVING COUNT(*) > 1) x");
echo "── ④ باقٍ بالتصادم: {$left} · وأدوارٌ فيها «الرئيسية» مكرَّرةٌ في البيانات: {$dupRoles}\n";
if ($left !== 0) { fwrite(STDERR, "بقي تصادمٌ — لم يكتمل\n"); exit(1); }

echo "\n✅ العنوانُ الواحدُ لمقصدٍ واحد: «الرئيسية» للوحةِ الإدارة (من المُصيِّر) و«"
   . WS_LABEL . "» لمساحةِ العمل.\n";
exit(0);
