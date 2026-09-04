<?php
/**
 * tools/permission_patterns_measure.php — نمطا الصلاحياتِ: أيُّهما يحكم وكم نُفِّذ؟
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **النمطُ يُعرَّف بمسارِ القرارِ لا بوجودِ جدول**: في القاعدةِ أكثرُ من عشرين
 *   جدولَ صلاحيّاتٍ، لكنّ الحاكمَ ما يقرأه `get_module_permissions()` عند كلِّ
 *   فتحِ شاشة — وهما اثنان: `gov_authority_grants` أوّلًا ثمّ `role_permissions`.
 *
 * ◆ **والقياسُ بالمستخدمِ لا بالدور**: النمطُ الثاني يُمنَح للمستخدمِ فردًا
 *   (`gov_authority_grants.user_id`)، فحسابُ التغطيةِ بالأدوارِ يُعطي رقمًا ظلًّا.
 *
 * ⛔ **وجدولٌ مملوءٌ لا يعني نمطًا نافذًا**: قالبٌ حالتُه ليست `active`، أو منحةٌ
 *   مسحوبةٌ أو منتهيةٌ، لا تحكم شيئًا — فتُقاس **النافذةُ** لا الصفوف.
 *
 * التشغيل: php tools/permission_patterns_measure.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$db = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($db->connect_errno) { exit('تعذّر الاتصال: ' . $db->connect_error . "\n"); }
$db->set_charset('utf8mb4');

function one($db, $sql) { $q = @$db->query($sql); return $q ? (int) $q->fetch_row()[0] : -1; }
function line($k, $v, $note = '') { printf("   %-46s %-8s %s\n", $k, $v === -1 ? 'تعذّر' : $v, $note); }

$CO = 4;   /* النظامُ مجمَّدٌ على الشركةِ ٤ */

echo "══ النمط ① — الصلاحيّةُ بالدورِ والشاشة (القائم) ══════════════════════\n";
line('الأدوارُ المسجَّلة', one($db, 'SELECT COUNT(*) FROM roles'));
line('الشاشاتُ المسجَّلةُ في `modules`', one($db, 'SELECT COUNT(*) FROM modules'));
line('صفوفُ `role_permissions` (دور × شاشة)', one($db, 'SELECT COUNT(*) FROM role_permissions'));
line('أدوارٌ لها صفٌّ واحدٌ فأكثر', one($db, 'SELECT COUNT(DISTINCT role_id) FROM role_permissions'));
line('شاشاتٌ لها صفٌّ واحدٌ فأكثر', one($db, 'SELECT COUNT(DISTINCT module_id) FROM role_permissions'));
line('منها تمنح العرضَ فعلًا (can_view=1)', one($db, 'SELECT COUNT(*) FROM role_permissions WHERE can_view=1'));
$rp_cov = one($db, 'SELECT COUNT(DISTINCT module_id) FROM role_permissions WHERE can_view=1');
$mods   = one($db, 'SELECT COUNT(*) FROM modules');
line('تغطيةُ الشاشات', sprintf('%.1f%%', 100 * $rp_cov / max(1, $mods)),
     "($rp_cov من $mods شاشةً يراها دورٌ واحدٌ على الأقلّ)");

echo "\n══ النمط ② — الصلاحيّةُ بقالبِ المسمَّى ومنحتِه (GOV-AUTH-01) ═══════════\n";
line('القوالبُ المؤلَّفة', one($db, 'SELECT COUNT(*) FROM gov_role_profiles'));
line('منها **نافذةٌ** (state=active)', one($db, "SELECT COUNT(*) FROM gov_role_profiles WHERE state='active'"));
line('بنودُ القوالب', one($db, 'SELECT COUNT(*) FROM gov_profile_items'));
line('المنحُ المسجَّلة', one($db, 'SELECT COUNT(*) FROM gov_authority_grants'));
/* ⛔ **المقامُ والبسطُ من مِصفاةٍ واحدة**: كان البسطُ يعُدُّ كلَّ ممنوحٍ بلا
   فلترٍ والمقامُ يعُدُّ `status='active'` بلا `is_deleted` — فدخل في البسطِ
   أربعةٌ غيرُ أحياءٍ (شركةٌ أخرى + ثلاثةٌ محذوفون) وفي المقامِ ثلاثةٌ محذوفون،
   فخرجت نسبةٌ (96.2٪) لا يقابلها مستخدمٌ واحدٌ صحيح. الحياةُ تُعرَّف مرّةً. */
$LIVE = "u.is_deleted = 0 AND u.status = 'active' AND u.company_id = $CO";
$live = "SELECT COUNT(*) FROM users u WHERE $LIVE AND EXISTS(
           SELECT 1 FROM gov_authority_grants g
             JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state='active'
            WHERE g.user_id = u.id AND g.revoked_at IS NULL
              AND (g.valid_to IS NULL OR g.valid_to > NOW()))";
$covered = one($db, $live);
line('مستخدمون **محكومون بقالبٍ نافذٍ** (أحياء)', $covered);
line('منحٌ نافذةٌ لغيرِ الأحياء', one($db, "SELECT COUNT(DISTINCT g.user_id) FROM gov_authority_grants g
           JOIN gov_role_profiles p ON p.profile_id=g.profile_id AND p.state='active'
           JOIN users u ON u.id=g.user_id
          WHERE g.revoked_at IS NULL AND (g.valid_to IS NULL OR g.valid_to>NOW())
            AND NOT($LIVE)"), 'المنحةُ تبقى بعدَ حاملِها — لا كنّاسةَ');
line('الحدودُ الصريحة `gov_authority_limits`', one($db, 'SELECT COUNT(*) FROM gov_authority_limits'));

echo "\n══ المقامُ الحاكم — مَن يحكمه أيُّ نمط؟ ════════════════════════════════\n";
$users = one($db, "SELECT COUNT(*) FROM users u WHERE $LIVE");
line("المستخدمون الأحياءُ (شركة $CO)", $users);
line('يحكمهم النمط ② (قالبٌ نافذ)', $covered,
     sprintf('%.1f%%', 100 * max(0, $covered) / max(1, $users)));
line('يحكمهم النمط ① (الارتدادُ للدور)', $users - max(0, $covered),
     sprintf('%.1f%%', 100 * ($users - max(0, $covered)) / max(1, $users)));

echo "\n══ طبقةُ الحوكمةِ (SEC-01) — تُخطِّط ولا تحكم ═══════════════════════════\n";
echo "   ⛔ لا يقرأها `permissions_helper.php` في قرارِ فتحِ الشاشة:\n";
foreach (array('permission_templates' => 'قوالبُ السياسة',
               'template_permissions' => 'بنودُ القوالب',
               'permission_audit_events' => 'سجلُّ التدقيق',
               'permission_change_requests' => 'طلباتُ التغيير',
               'permission_review_cycles' => 'دوراتُ المراجعة',
               'permission_review_lines' => 'أسطرُ المراجعة',
               'permission_approval_steps' => 'خطواتُ الاعتماد',
               'permission_exceptions' => 'الاستثناءات') as $t => $ar) {
    $n = one($db, "SELECT COUNT(*) FROM `$t`");
    line($ar . "  `$t`", $n, $n === 0 ? '⛔ فارغٌ — لم يُشغَّل' : '');
}

echo "\n══ الشاهد — أيقرأ **قرارُ فتحِ الشاشةِ** ما أدّعيه؟ ═════════════════════\n";
/* ⛔ **وذِكرُ الجدولِ في الملفِّ ليس قراءتَه في القرار**: كشف الشاهدُ أنّ
   `permission_templates` مذكورةٌ خمسَ مرّاتٍ في `permissions_helper.php` — كلُّها
   في دوالِّ **إحصاءٍ** للوحةِ الإدارة (`perm_system_counts` · `perm_template_*`)
   لا في `get_module_permissions()`. فيُقصَر المسحُ على جسمِ دالّةِ القرار. */
$h = file_get_contents($ROOT . '/includes/permissions_helper.php');
$body = '';
if (preg_match('/function\s+get_module_permissions\s*\(.*?\n\}/s', $h, $bm)) { $body = $bm[0]; }
printf("   جسمُ `get_module_permissions()`: %d سطرًا\n\n", substr_count($body, "\n"));
foreach (array('gov_authority_grants' => 'النمط ② — القالب', 'role_permissions' => 'النمط ① — الدور',
               'template_permissions' => 'SEC-01', 'permission_templates' => 'SEC-01') as $t => $ar) {
    $inDec = substr_count($body, $t);
    $inAll = substr_count($h, $t);
    printf("   %-24s %-18s في القرار: %-10s في الملفِّ كلِّه: %d\n", $t, $ar,
        $inDec > 0 ? $inDec . ' ✔' : 'لا شيء ⛔', $inAll);
}
echo "\n   ⇒ الحاكمُ نمطان فقط. وSEC-01 مذكورةٌ في الملفِّ لكن **خارجَ القرار**:\n"
   . "     دوالُّ إحصاءٍ تُغذّي لوحةَ «نظام الصلاحيات الجديد» لا بوّابةَ الشاشة.\n";
