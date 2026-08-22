<?php
/**
 * tests/injfrd66_w2_positions_test.php — شاهدُ الموجةِ ② (SAL-03 · SAL-21 · SUP-05 · SUP-11)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **القياسُ على المُصيَّرِ بجلسةِ مستخدمٍ حقيقيّ** — لا على صفِّ القاعدةِ
 *   وحدَه: فالموضعُ يُحسم في أربعِ طبقاتٍ، وصفٌّ صحيحٌ في القاعدةِ قد لا يظهر.
 *
 * ◆ **إيجابيٌّ**: كلُّ متطلبٍ في مجموعتِه المستهدفةِ ورأسِه الصحيح.
 * ◆ **سالبٌ**  : كلُّ متطلبٍ **خرج** من الموضعِ الذي يمنعه المرجعُ نصًّا —
 *   ولا يكفي «صار هنا» بل يجب «لم يعد هناك». وهذا هو الفرقُ بين نقلٍ ونسخ.
 * ◆ **سالبٌ بنيويّ**: القسمُ المُعلَنُ لم يتسرَّب إلى دورٍ لا إعلانَ له —
 *   فالطبقةُ الجديدةُ لا تمسُّ من لم تُعلَن له.
 *
 * التشغيل: php tests/injfrd66_w2_positions_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$_SERVER['SCRIPT_NAME']    = '/ems/main/dashboard.php';
$_SERVER['REQUEST_URI']    = $_SERVER['SCRIPT_NAME'];
$_SERVER['REQUEST_METHOD'] = 'GET';
require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/unified_nav.php';
require_once $ROOT . '/includes/uxui_nav_probe.php';
while (ob_get_level() > 0) { ob_end_clean(); }
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

$pass = 0; $fail = 0; $held = 0;
$users = uxp_role_users($conn);

/* المُصيَّرُ لكلِّ دورٍ مفهرسًا بالمسار */
$byRole = array();
foreach (array(12, 2) as $rid) {
    foreach (uxp_render_role($conn, $rid, $users[$rid] ?? null) as $p) {
        $byRole[$rid][strtolower(uxp_norm($p['href']))] = $p;
    }
}

/* req => [role, route, القسمُ المستهدف, الرأسُ المتوقَّع, الرأسُ الممنوع] */
$CASES = array(
    array('SAL-03', 12, 'projects/projects.php',           'إدارة العملاء والفرص',      'العقود والعملاء',     'التشغيل اليومي'),
    array('SUP-05',  2, 'contracts/contract_coverage.php', 'الاحتياج والتعاقد',          'الموردون والمشتريات', 'التقارير والتحليلات'),
    array('SUP-11',  2, 'suppliers/shares_coverage.php',   'الحصص والتغطية والأداء',    'الموردون والمشتريات', 'التقارير والتحليلات'),
    array('SAL-21', 12, 'clients/products.php',            'البيانات المرجعية والتقارير', 'التقارير والتحليلات', 'العقود والعملاء'),
);

echo "① إيجابيٌّ — القسمُ المستهدفُ والرأسُ الصحيح:\n";
foreach ($CASES as $c) {
    list($req, $rid, $route, $wantSec, $wantHead, ) = $c;
    $p = $byRole[$rid][$route] ?? null;
    if (!$p) { $fail++; printf("   ✘ %s — «%s» غيرُ مُصيَّرٍ للدورِ %d\n", $req, $route, $rid); continue; }
    $okSec  = ($p['section'] === $wantSec);
    $okHead = ($p['group']   === $wantHead);
    if ($okSec && $okHead) { $pass++; printf("   ✔ %s  «%s» ⇐ رأسُ «%s» · قسمُ «%s»\n", $req, $p['label'], $p['group'], $p['section']); }
    else {
        $fail++;
        printf("   ✘ %s  رأسٌ «%s» (المتوقَّع «%s») · قسمٌ «%s» (المتوقَّع «%s»)\n",
            $req, $p['group'], $wantHead, $p['section'], $wantSec);
    }
}

echo "\n② سالبٌ — خرج من الموضعِ الذي يمنعه المرجعُ نصًّا:\n";
foreach ($CASES as $c) {
    list($req, $rid, $route, , , $forbidHead) = $c;
    $p = $byRole[$rid][$route] ?? null;
    if (!$p) { continue; }
    if ($p['group'] !== $forbidHead) { $pass++; printf("   ✔ %s  لم يعد تحت «%s»\n", $req, $forbidHead); }
    else { $fail++; printf("   ✘ %s  ما يزال تحت «%s»\n", $req, $forbidHead); }
}

echo "\n③ إيجابيٌّ — شروطُ البياناتِ المكتوبة:\n";
$one = static function (string $req, string $title, string $sql, int $expect) use ($conn, &$pass, &$fail): void {
    $r = @mysqli_query($conn, $sql);
    $n = $r ? (int) mysqli_fetch_row($r)[0] : -1;
    if ($n === $expect) { $pass++; printf("   ✔ %s  %-38s = %d\n", $req, $title, $n); }
    else { $fail++; printf("   ✘ %s  %-38s = %d (توقّعتُ %d)\n", $req, $title, $n, $expect); }
};
$one('SAL-03', 'مشروعٌ بلا عميل', "SELECT COUNT(*) FROM project WHERE client_id IS NULL OR client_id = 0", 0);
$one('SAL-21', 'بندُ تنقّلٍ مكرَّرٌ للبيانات المرجعية',
    "SELECT COUNT(*)-COUNT(DISTINCT LOWER(route)) FROM nav_items
      WHERE active = 1 AND role_id = 12
        AND LOWER(route) IN ('clients/products.php','clients/pricelists.php','clients/units_of_measure.php')", 0);

/* ── ④ محجوزٌ بسببِه المكتوب ─────────────────────────────────────────── */
echo "\n④ محجوزٌ بسببٍ مكتوب:\n";
$r = @mysqli_query($conn, "SELECT COUNT(*) FROM op_containers WHERE is_deleted = 0 AND (obl_id IS NULL OR obl_id = 0)");
$noObl = $r ? (int) mysqli_fetch_row($r)[0] : -1;
if ($noObl > 0) {
    $held++;
    printf("   ⏸ SUP-11 «صفر حصةٍ بلا التزامٍ مرجعي» — %d حصةً بلا obl_id.\n", $noObl);
    echo "      الوصلُ إسنادُ التزامٍ لكلِّ حصة، وهو متطلبُ SAL-13 «مصفوفة الالتزامات»\n";
    echo "      في الموجةِ ③ — لا تعديلُ موضعٍ في هذه. فـSUP-11 «جزئي» لا «نعم».\n";
} else { $pass++; echo "   ✔ صفرُ حصةٍ بلا التزامٍ مرجعي\n"; }

/* ── ⑤ سالبٌ بنيويّ: القسمُ المُعلَنُ لا يتسرَّب لدورٍ بلا إعلان ────────── */
echo "\n⑤ سالبٌ بنيويّ — الطبقةُ الجديدةُ لا تمسُّ من لا إعلانَ له:\n";
$declRoles = array();
$q = @mysqli_query($conn, "SELECT DISTINCT role_id FROM gov_target_nav");
while ($q && ($x = mysqli_fetch_assoc($q))) { $declRoles[(int) $x['role_id']] = true; }
$probe = 17;                       /* دورٌ جذريٌّ لا إعلانَ له — أكبرُ سايدبارٍ مقيس */
if (isset($declRoles[$probe])) { $fail++; printf("   ✘ الدورُ %d مُعلَنٌ فلا يصلح شاهدًا سالبًا\n", $probe); }
else {
    $decl = uxuiDeclaredSections($conn, $probe);
    if (empty($decl)) { $pass++; printf("   ✔ الدورُ %d بلا إعلانٍ — صفرُ قسمٍ مفروضٍ عليه\n", $probe); }
    else { $fail++; printf("   ✘ الدورُ %d بلا صفٍّ في الجدولِ ومع ذلك أُعطي %d قسمًا\n", $probe, count($decl)); }
}

printf("\n%s  ناجح %d · راسب %d · محجوز %d\n",
    $fail === 0 ? '✔ الموجة ②' : '✘ الموجة ②', $pass, $fail, $held);
exit($fail === 0 ? 0 : 1);
