<?php
/**
 * tools/govauth_diff_report.php — تقريرُ الفروق: القالبُ مقابلَ الصلاحيةِ القائمة
 * ───────────────────────────────────────────────────────────────────────────
 * GOV-AUTH-01 §8-3 ③: «يُلحق القالبُ بكلِّ موظفٍ حاليٍّ بحسبِ مسمَّاه ويُقارن
 * بصلاحيتِه الفعليةِ القائمةِ فيُخرَج تقريرُ فروقٍ يُعتمد قبلَ التبديل —
 * ولا يُبدَّل نظامُ الصلاحياتِ القائمُ قبلَ اعتمادِ تقريرِ الفروق».
 *
 * ثلاثةُ فروقٍ تُقاس:
 *   ① القالبُ مقابلَ هدفِ الورقة: بنودُه المزروعةُ من الحيِّ مقابلَ عددِ
 *     الشاشاتِ المستهدَفِ في «قوالب المسميات».
 *   ② القالبُ مقابلَ منحِ الدورِ الحيِّ الآن (يكشف ما تغيّر بعدَ البذر).
 *   ③ المستخدمون النشطون بلا قالبٍ — بأسمائِهم وأدوارِهم.
 *
 *   php tools/govauth_diff_report.php  ⇒ storage/reports/govauth01_diff_report.csv
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$ROOT = dirname(__DIR__);

$out = array();
$out[] = "النوع\tالمرجع\tالبيان\tالقيمة أ\tالقيمة ب\tالفرق\tالحكم";

/* ① القالبُ مقابلَ هدفِ الورقة */
$r = $conn->query(
    "SELECT p.profile_code, p.dept_code, p.grade, p.screens_target,
            COUNT(i.item_id) AS seeded,
            MAX(i.seeded_from) AS src
       FROM gov_role_profiles p
       LEFT JOIN gov_profile_items i ON i.profile_id = p.profile_id AND i.item_kind = 'screen'
      GROUP BY p.profile_id
      ORDER BY p.dept_code, p.grade");
$mapped = 0; $unmapped = 0; $overTarget = 0; $underTarget = 0;
while ($x = $r->fetch_assoc()) {
    $t = (int) $x['screens_target']; $s = (int) $x['seeded'];
    if ($s === 0) { $unmapped++; $verdict = 'بلا نظيرٍ حيٍّ — بذرُه قرارُ تكوينٍ بيدِ المالك'; }
    elseif ($s > $t) { $overTarget++; $mapped++; $verdict = 'الحيُّ أوسعُ من هدفِ الورقة — يُقلَّم بقرارٍ أو يُعدَّل الهدف'; }
    elseif ($s < $t) { $underTarget++; $mapped++; $verdict = 'الحيُّ أضيقُ من الهدف — النقصُ يُبذَر بقرارٍ'; }
    else { $mapped++; $verdict = 'مطابق'; }
    $out[] = "قالب↔ورقة\t{$x['profile_code']}\t{$x['dept_code']} · {$x['grade']}\t"
           . "هدف {$t}\tمزروع {$s}\t" . ($s - $t) . "\t{$verdict}";
}

/* ② القالبُ مقابلَ منحِ الدورِ الحيِّ الآن */
$r = $conn->query(
    "SELECT p.profile_code,
            SUBSTRING_INDEX(i.seeded_from, ':', -1) AS role_id,
            COUNT(*) AS in_template,
            (SELECT COUNT(*) FROM role_permissions rp
              WHERE rp.role_id = SUBSTRING_INDEX(i.seeded_from, ':', -1) AND rp.can_view = 1) AS live_now
       FROM gov_profile_items i
       JOIN gov_role_profiles p ON p.profile_id = i.profile_id
      WHERE i.seeded_from LIKE 'role_permissions:%'
      GROUP BY p.profile_code, role_id");
$drift = 0;
while ($x = $r->fetch_assoc()) {
    $d = (int) $x['live_now'] - (int) $x['in_template'];
    if ($d !== 0) { $drift++; }
    $out[] = "قالب↔حي\t{$x['profile_code']}\tدور {$x['role_id']}\t"
           . "قالب {$x['in_template']}\tحي {$x['live_now']}\t{$d}\t"
           . ($d === 0 ? 'مطابق' : 'انجرافٌ بعد البذر — يُعاد البذرُ أو يُعتمد الفرق');
}

/* ③ المستخدمون النشطون بلا قالب */
$r = $conn->query(
    "SELECT u.id, u.username, u.role, COALESCE(ro.name,'—') role_name, u.company_id
       FROM users u LEFT JOIN roles ro ON ro.id = u.role
      WHERE u.status = 1
        AND NOT EXISTS (SELECT 1 FROM gov_authority_grants g
                         WHERE g.user_id = u.id AND g.revoked_at IS NULL)
      ORDER BY u.role");
$orphanUsers = 0;
while ($x = $r->fetch_assoc()) {
    $orphanUsers++;
    $out[] = "مستخدم بلا قالب\t#{$x['id']}\t{$x['username']} · {$x['role_name']} (دور {$x['role']}) · كيان {$x['company_id']}\t—\t—\t—\tإسنادُه قرارُ تكوينٍ — لا يُخمَّن";
}

/* الخلاصة */
$totProfiles = (int) $conn->query("SELECT COUNT(*) c FROM gov_role_profiles")->fetch_assoc()['c'];
$totUsers = (int) $conn->query("SELECT COUNT(*) c FROM users WHERE status=1")->fetch_assoc()['c'];
$granted = $totUsers - $orphanUsers;
$summary = array(
    "القوالب: {$totProfiles} (رؤوسُ الورقةِ حرفًا) — منها {$mapped} بنظيرٍ حيٍّ و{$unmapped} بلا نظيرٍ (معلَنة)",
    "الحيُّ أوسعُ من هدفِ الورقة: {$overTarget} قالبًا · أضيقُ: {$underTarget}",
    "انجرافٌ قالب↔حي بعدَ البذر: {$drift}",
    "المستخدمون النشطون: {$totUsers} — أُلحق قالبٌ بـ{$granted} وبقي {$orphanUsers} بلا قالبٍ معلَنين",
    "◆ ولا تبديلَ لنظامِ الصلاحياتِ القائمِ قبلَ اعتمادِ هذا التقرير (GOV-AUTH-01 §8-3)",
);

$dir = $ROOT . '/storage/reports';
if (!is_dir($dir)) { mkdir($dir, 0777, true); }
$csv = $dir . '/govauth01_diff_report.csv';
file_put_contents($csv, "\xEF\xBB\xBF" . implode("\n", array_map(function ($l) {
    return str_replace(array(',', "\t"), array('،', ','), $l);
}, $out)));

echo "════ تقريرُ فروقِ GOV-AUTH-01 — " . date('Y-m-d H:i') . " ════\n";
foreach ($summary as $s) { echo "  · {$s}\n"; }
echo "  ⇒ التفصيلُ الصفّي (" . (count($out) - 1) . " صفًّا): storage/reports/govauth01_diff_report.csv\n";
