<?php
/**
 * tools/ctl_build_unblock.php — فكُّ حواجبِ البناءِ الآليّةِ (البند ⑯ · تمهيد)
 * ═══════════════════════════════════════════════════════════════════════════
 * بوّابةُ `BUILD_READY` قرأت **صفرًا من 292** — وحاجبان منها آليّان يُفكّان
 * من مصدرِهما القانونيِّ لا بتخمين:
 *
 * ① `REQUIREMENT_TYPE_MISSING` — المتطلبُ غيرُ المبنيِّ لا سطحَ له فامتنع
 *   اشتقاقُ نوعِه من `surface_kind` (بحقّ). **ومصدرُه القانونيُّ الدليلُ
 *   المعماريُّ نفسُه**: كلُّ كتلةِ شاشةٍ تنصُّ «نوع الشاشة: …» — والمتطلبُ
 *   وكتلتُه هويّةٌ واحدةٌ (كلاهما وليدُ الورقةِ نفسِها بعنوانِها) لا مطابقةُ
 *   اسمَين من كونَين.
 * ② `REALIZATION_TYPE_UNDECIDED` — هدفٌ غيرُ مبنيٍّ **كتلتُه قائمةٌ في
 *   الدليلِ بحقولِها** ⇒ `MENU_SCREEN_DESIGNED` بشاهدِ كتلتِه.
 *
 * ⛔ وما لا كتلةَ له في الدليلِ يبقى بحاجبِه مسمًّى — لا يُخمَّن نوعٌ ولا حسم.
 *
 * التشغيل: php tools/ctl_build_unblock.php [--apply]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn']; mysqli_set_charset($conn, 'utf8mb4');
$e = function ($x) use ($conn) { return $conn->real_escape_string((string) $x); };
$APPLY = in_array('--apply', $argv, true);

$nz = function ($v) {
    $v = str_replace(array('أ','إ','آ','ٱ'), 'ا', (string) $v);
    $v = str_replace('ة', 'ه', $v);
    $v = str_replace('ى', 'ي', $v);
    $v = preg_replace('~[\x{064B}-\x{0655}\x{0670}\x{0640}«»"\x27]~u', '', $v);
    $v = preg_replace('~[\s—·\-–/،,\(\)]+~u', ' ', $v);
    return trim($v);
};

/* ── كتلُ الدليلِ بورقةِ كلٍّ ─────────────────────────────────────────────── */
$blocks = array();
for ($s = 1; $s <= 17; $s++) {
    $sn = str_pad((string) $s, 2, '0', STR_PAD_LEFT);
    $j = json_decode((string) @file_get_contents($ROOT . '/docs/REPAIR01_20260823/GUIDE_SPEC_' . $sn . '.json'), true);
    if (!is_array($j)) { continue; }
    foreach ($j as $g) { $blocks[$sn][$nz($g['title'])] = $g; }
}
echo "═══ فكُّ حواجبِ البناءِ الآليّة" . ($APPLY ? '' : ' · DRY') . " ═══\n";
echo '  كتلُ الدليل: ' . array_sum(array_map('count', $blocks)) . " على " . count($blocks) . " ورقة\n";

/* ── ① النوعُ المنصوص ──────────────────────────────────────────────────── */
$MAP = array(
    'reference' => 'STRUCTURAL', 'مرجعي' => 'STRUCTURAL', 'مرجعيه' => 'STRUCTURAL',
    'transaction' => 'TRANSACTION', 'معامله' => 'TRANSACTION',
    'report' => 'PROJECTION_REPORT', 'derived' => 'PROJECTION_REPORT', 'تقرير' => 'PROJECTION_REPORT',
    'مشتق' => 'PROJECTION_REPORT', 'dashboard' => 'PROJECTION_REPORT', 'لوحه' => 'PROJECTION_REPORT',
    'event' => 'EVENT_INTEGRATION', 'حدث' => 'EVENT_INTEGRATION',
    'journey' => 'CROSS_JOURNEY', 'رحله' => 'CROSS_JOURNEY',
);
$typed = 0; $noBlock = array(); $noType = array();
$r = $conn->query("SELECT requirement_id, unit, surface FROM repair01_requirements
                    WHERE requirement_type IS NULL OR requirement_type = ''");
$rows = array();
while ($x = $r->fetch_assoc()) { $rows[] = $x; }
foreach ($rows as $x) {
    $sn = substr(trim($x['unit']), 0, 2);
    $key = $nz($x['surface']);
    $g = isset($blocks[$sn][$key]) ? $blocks[$sn][$key] : null;
    if ($g === null && isset($blocks[$sn])) {
        /* الهويّةُ نفسُها وقد يزيد أحدُ الوجهَين لاحقةً تفسيريّة — احتواءٌ تامٌّ من أوّلِ السطر */
        foreach ($blocks[$sn] as $bk => $bg) {
            if ($bk !== '' && (strpos($bk, $key) === 0 || strpos($key, $bk) === 0)) { $g = $bg; break; }
        }
    }
    if ($g === null) { $noBlock[] = $x['requirement_id']; continue; }
    $type = '';
    $src = $nz(mb_strtolower((string) $g['source']));
    $decl = '';
    if (preg_match('~نوع الشاشه[:\s]+([^·]+)~u', $src, $m)) { $decl = trim($m[1]); }
    $tok = strtok($decl, ' ');
    if ($tok !== false && isset($MAP[$tok])) { $type = $MAP[$tok]; }
    if ($type === '' && $decl !== '') {
        /* الانواع المركبة المنصوصة: Child of/Child Register = سجل ابناء بحبة سطر
           لمعاملة ابيه ⇒ معاملة بعقدها · Periodic (Budget/Forecast) = ادخال دوري
           ⇒ معاملة · Process = معاملة */
        if (preg_match('~child|periodic|process|exception~', $decl)) { $type = 'TRANSACTION'; }
        elseif (preg_match('~master~', $decl)) { $type = 'STRUCTURAL'; }
        elseif (preg_match('~board|kpi|analytic~', $decl)) { $type = 'PROJECTION_REPORT'; }
    }
    if ($type === '') { $noType[] = $x['requirement_id'] . '(' . mb_substr((string) $g['source'], -40) . ')'; continue; }
    $typed++;
    if ($APPLY) {
        $wit = 'FC16: النوع منصوص في الدليل المعماري ورقة ' . $sn . ' كتلة #' . $g['no']
             . ' («نوع الشاشة» في سطر المصدر) — هوية الكتلة عنوان الورقة نفسه لا مطابقة اسمين';
        $conn->query("UPDATE repair01_requirements
                         SET requirement_type='" . $e($type) . "', type_witness='" . $e($wit) . "'
                       WHERE requirement_id='" . $e($x['requirement_id']) . "'");
    }
}
echo "  ① نوعٌ منصوصٌ كُتب: $typed · بلا كتلةٍ: " . count($noBlock) . " · كتلةٌ بلا نوعٍ منصوص: " . count($noType) . "\n";
if ($noBlock) { echo '     بلا كتلة: ' . implode(' · ', array_slice($noBlock, 0, 12)) . "\n"; }
if ($noType) { echo '     بلا نوع: ' . implode(' · ', array_slice($noType, 0, 6)) . "\n"; }

/* ── ② حسمُ التحقيق للمصمَّم ─────────────────────────────────────────────── */
$colChk = $conn->query("SHOW COLUMNS FROM repair01_target_universe LIKE 'realization_type'");
if ($colChk && $colChk->num_rows) {
    $dec = 0; $left = 0;
    $r = $conn->query("SELECT u.target_uid, u.unit, u.name_ar, q.unit qunit, q.surface
                         FROM repair01_target_universe u
                         LEFT JOIN repair01_requirements q ON q.requirement_id = u.requirement_id
                        WHERE u.verdict = 'NOT_BUILT'
                          AND (u.realization_type IS NULL OR u.realization_type = '' OR u.realization_type = 'UNDECIDED')");
    $rows2 = array();
    while ($x = $r->fetch_assoc()) { $rows2[] = $x; }
    foreach ($rows2 as $x) {
        $sn = substr(trim((string) ($x['qunit'] !== null ? $x['qunit'] : $x['unit'])), 0, 2);
        $key = $nz((string) ($x['surface'] !== null && $x['surface'] !== '' ? $x['surface'] : $x['name_ar']));
        $g = isset($blocks[$sn][$key]) ? $blocks[$sn][$key] : null;
        if ($g === null && isset($blocks[$sn])) {
            foreach ($blocks[$sn] as $bk => $bg) {
                if ($bk !== '' && (strpos($bk, $key) === 0 || strpos($key, $bk) === 0)) { $g = $bg; break; }
            }
        }
        if ($g === null) { $left++; continue; }
        $dec++;
        if ($APPLY) {
            $conn->query("UPDATE repair01_target_universe
                             SET realization_type='MENU_SCREEN_DESIGNED'
                           WHERE target_uid='" . $e($x['target_uid']) . "'");
        }
    }
    echo "  ② حُسم تحقيقُه MENU_SCREEN_DESIGNED بكتلتِه: $dec · بلا كتلةٍ (يبقى بحاجبِه): $left\n";
} else {
    echo "  ② عمودُ realization_type ليس في الكون — يُقرأ من مصدرِ البوّابةِ الآخر\n";
}
