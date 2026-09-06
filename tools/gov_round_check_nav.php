<?php
/**
 * tools/gov_round_check_nav.php — إظهارُ شاشةِ الفحصِ في سايدبار دور 15
 * ═══════════════════════════════════════════════════════════════════════════
 * **البندُ الظاهرُ سلسلةٌ لا صفّ** ([[perm-screens-role15-build]]): التسجيلُ في
 * السجلّاتِ الأربعةِ يُرضي الحرّاسَ **ولا يُظهر رابطًا**. والظهورُ يحتاج ثلاثةً:
 *   ① صفٌّ في `modules` — هويّةُ الشاشةِ ورمزُ صلاحيتِها.
 *   ② صفٌّ في `nav_items` للدورِ والمجموعة — وهو ما يُصيَّر.
 *   ③ بندٌ في `gov_profile_items` لقالبِ الدور — **وهو ما يقرؤه الحارس**،
 *      فبندٌ بلا منحٍ يظهر ثمَّ يُردُّ 403.
 *
 * التشغيل: php tools/gov_round_check_nav.php [--apply]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT  = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
$APPLY = in_array('--apply', $argv, true);
require_once $ROOT . '/includes/env.php';
$h = ems_env('DB_HOST'); $prt = 3306;
if (strpos($h, ':') !== false) { list($h, $prt) = explode(':', $h); $prt = (int) $prt; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($h, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $prt);
if ($conn->connect_errno) { exit("⛔ اتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$e = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };

$CODE  = 'Governance/round_check.php';
$LABEL = 'فحص جاهزية الالتزام';
$ICON  = 'fa fa-clipboard-check';
$ROLE  = 15;
$GRP   = 6505;      /* «إدارة الصلاحيات والأدوار» — المجموعةُ التي يقرؤها nav_items */
$done  = 0;

/* ═══ ① الوحدة ═══ */
$q = $conn->query("SELECT id FROM modules WHERE code='{$e($CODE)}'");
if ($q && $q->num_rows) { $mid = (int) $q->fetch_row()[0]; echo "① الوحدة: قائمةٌ {$mid}\n"; }
else {
    $q = $conn->query('SELECT COALESCE(MAX(id),0)+1 FROM modules');
    $mid = (int) $q->fetch_row()[0];
    echo "① الوحدة: تُنشأ {$mid}\n";
    if ($APPLY) {
        $ok = $conn->query("INSERT INTO modules (id, name, code, owner_role_id, is_link, is_quick, icon, display_order, owner_dept_note)
            VALUES ({$mid}, '{$e($LABEL)}', '{$e($CODE)}', {$ROLE}, 0, 0, '{$e($ICON)}', 82, 'DEP-08')");
        if (!$ok) { exit("⛔ ①: {$conn->error}\n"); }
        $done++;
    }
}

/* ═══ ② بندُ الملاحة ═══ */
$q = $conn->query("SELECT id FROM nav_items WHERE role_id={$ROLE} AND route='{$e($CODE)}'");
if ($q && $q->num_rows) { echo "② بندُ الملاحة: قائم\n"; }
else {
    echo "② بندُ الملاحة: يُنشأ (دور {$ROLE} · مجموعة {$GRP})\n";
    if ($APPLY) {
        $q = $conn->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM nav_items WHERE role_id={$ROLE} AND group_id={$GRP}");
        $so = (int) $q->fetch_row()[0];
        $ok = $conn->query("INSERT INTO nav_items
            (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, permission_code, active, is_quick, created_at, updated_at)
            VALUES ({$ROLE}, 'SET', {$GRP}, {$mid}, '{$e($LABEL)}', '{$e($CODE)}', '{$e($ICON)}', {$so}, '{$e($CODE)}', 1, 0, NOW(), NOW())");
        if (!$ok) { exit("⛔ ②: {$conn->error}\n"); }
        $done++;
    }
}

/* ═══ ③ المنحُ في قالبِ الدور — وهو ما يقرؤه الحارس ═══ */
$q = $conn->query("SELECT DISTINCT profile_id FROM gov_profile_items
                    WHERE item_kind='screen' AND item_ref='Governance/perm_system_status.php'");
$profiles = array();
while ($q && $r = $q->fetch_row()) { $profiles[] = (int) $r[0]; }
if (!$profiles) { echo "③ المنح: ⚠ لا قالبَ نظيرًا يُحتذى — يُمنح يدويًّا\n"; }
else {
    $add = array();
    foreach ($profiles as $pid) {
        $q = $conn->query("SELECT item_id FROM gov_profile_items
                            WHERE profile_id={$pid} AND item_kind='screen' AND item_ref='{$e($CODE)}'");
        if (!$q || !$q->num_rows) { $add[] = $pid; }
    }
    printf("③ المنح: قوالبُ النظيرِ %d · يُضاف إلى %d\n", count($profiles), count($add));
    if ($APPLY) {
        foreach ($add as $pid) {
            $ok = $conn->query("INSERT INTO gov_profile_items
                (company_id, profile_id, item_kind, item_ref, allow, can_add, can_edit, can_delete, seeded_from)
                VALUES (0, {$pid}, 'screen', '{$e($CODE)}', 1, 0, 0, 0, 'GOV-CHK-01')");
            if (!$ok) { exit("⛔ ③ (قالب {$pid}): {$conn->error}\n"); }
            $done++;
        }
    }
}

printf("\nخطواتٌ نُفِّذت: %d\n", $done);
if (!$APPLY) { echo "\nقياسٌ فقط — أعِد بـ`--apply`\n"; exit(0); }

/* ═══ التحقّقُ بإعادةِ القراءة ═══ */
$ok = 0;
$q = $conn->query("SELECT id FROM modules WHERE code='{$e($CODE)}'");                       if ($q && $q->num_rows) { $ok++; }
$q = $conn->query("SELECT id FROM nav_items WHERE role_id={$ROLE} AND route='{$e($CODE)}' AND active=1"); if ($q && $q->num_rows) { $ok++; }
$q = $conn->query("SELECT item_id FROM gov_profile_items WHERE item_kind='screen' AND item_ref='{$e($CODE)}' AND allow=1"); if ($q && $q->num_rows) { $ok++; }
printf("✔ أُعيدت القراءةُ: %d من 3 (وحدة · بند · منح)\n", $ok);
exit($ok === 3 ? 0 : 1);
