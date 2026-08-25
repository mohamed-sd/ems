<?php
/**
 * tools/repair01_w5_cycle_register.php — إدخالُ أسطحِ W05 في مصفوفةِ الدورة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لماذا**: W05 بنت ستَّ شاشاتٍ ولم تُدخلها `gov_screen_cycle`، فارتفع
 *   `RP-01` (شاشةٌ بلا سجلّ) و`RP-02` (مسارٌ بلا مالك) ستًّا كلٌّ ومنعت
 *   السقّاطةُ الالتزام. وW04 أجّلت هذا بحجّةٍ صحيحةٍ حينَها: «الصفُّ يحتاج
 *   موضعًا من دورةٍ: أيُّ مرحلةٍ وأيُّ مستندٍ ناتج».
 *
 * ◆ **والحجّةُ سقطت في W05**: الموضعُ صار معلومًا ومقيسًا —
 *     المرحلةُ والمجموعةُ من `repair01_w5_scope.group_name`
 *     والعنوانُ من `repair01_w5_scope.surface`
 *     والمستندُ الناتجُ والمستهلكونَ من **عقودِ الأثرِ** في `repair01_events`
 *     والمالكُ من `repair01_screen_registry.owner_code`
 *   فلا خانةَ تُملأ بالتخمين. وما لا مصدرَ له يبقى `—` مُعلَنًا لا مخترَعًا.
 *
 * ◆ **ولا يُحذف صفٌّ ولا يُمَسّ سطحٌ قائم** — إضافةٌ محضةٌ لأسطحِ `origin='W05'`.
 *
 * التشغيل: php tools/repair01_w5_cycle_register.php [--dry]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$DRY = in_array('--dry', $argv, true);
function q5($conn, $v) { return $conn->real_escape_string((string) $v); }

/* تسميةُ الإدارةِ كما هي حيّةً في `gov_screen_cycle` — لا كما في الدفتر */
$DEPT_LIVE = array('DEP-04' => 'إدارة الأسطول', 'DEP-13' => 'القوى التشغيلية');
/* الدورُ المسؤولُ لكلِّ إدارة — من الأدوارِ الحيّة */
$RESP = array('DEP-04' => 'مسؤول الأسطول', 'DEP-13' => 'مسؤول القوى التشغيلية');

/* ═══ ① الأسطحُ ومالكوها ═══ */
$screens = array();
$r = $conn->query("SELECT screen_id, route, screen_file, owner_code FROM repair01_screen_registry WHERE origin = 'W05'");
while ($r && $x = $r->fetch_assoc()) { $screens[$x['screen_id']] = $x; }

/* ═══ ② موضعُها من الدورة — من دفترِ نطاقِ W05 ═══ */
$pos = array();
$r = $conn->query("SELECT anchor_screen_id, group_name, surface, requirement_id
                   FROM repair01_w5_scope WHERE anchor_screen_id <> '' ORDER BY requirement_id");
while ($r && $x = $r->fetch_assoc()) {
    $sid = $x['anchor_screen_id'];
    if (!isset($screens[$sid])) { continue; }
    if (!isset($pos[$sid])) { $pos[$sid] = array('group' => $x['group_name'], 'titles' => array(), 'reqs' => array()); }
    $pos[$sid]['titles'][] = $x['surface'];
    $pos[$sid]['reqs'][]   = $x['requirement_id'];
}

/* ═══ ③ المستندُ الناتجُ والمستهلكونَ — من عقودِ الأثرِ المقيسة ═══ */
$eff = array();
$r = $conn->query("SELECT source_screen, event_code, consumers FROM repair01_events WHERE contract_stage = 'W05'");
while ($r && $x = $r->fetch_assoc()) {
    if (!preg_match('~([A-Za-z]+/[A-Za-z0-9_]+\.php)~', (string) $x['source_screen'], $m)) { continue; }
    $eff[$m[1]]['events'][] = trim($x['event_code']);
    foreach (explode('·', (string) $x['consumers']) as $c) {
        $c = trim($c);
        if ($c !== '' && !in_array($c, isset($eff[$m[1]]['cons']) ? $eff[$m[1]]['cons'] : array(), true)) {
            $eff[$m[1]]['cons'][] = $c;
        }
    }
}

/* ترتيبُ المرحلةِ من حرفِ المجموعةِ العربيّ — كما رُتِّبت في الدليل */
$ORDER = array('أ' => 1, 'ب' => 2, 'ج' => 3, 'د' => 4, 'هـ' => 5, 'و' => 6, 'ز' => 7, 'ح' => 8, 'ط' => 9);

$ins = 0; $skip = 0; $rows = array();
foreach ($screens as $sid => $sc) {
    $bn    = basename($sc['route']);
    $exist = (int) $conn->query("SELECT COUNT(*) FROM gov_screen_cycle WHERE screen_file = '" . q5($conn, $bn) . "'")->fetch_row()[0];
    if ($exist) { $skip++; continue; }

    $dept  = isset($DEPT_LIVE[$sc['owner_code']]) ? $DEPT_LIVE[$sc['owner_code']] : '';
    $resp  = isset($RESP[$sc['owner_code']]) ? $RESP[$sc['owner_code']] : '—';
    $p     = isset($pos[$sid]) ? $pos[$sid] : array('group' => '—', 'titles' => array(), 'reqs' => array());
    $group = $p['group'];
    $title = $p['titles'] ? implode(' · ', $p['titles']) : $bn;
    $ord   = 0;
    if (preg_match('/^(\S+)\s*·/u', $group, $gm) && isset($ORDER[$gm[1]])) { $ord = $ORDER[$gm[1]]; }

    $e     = isset($eff[$sc['route']]) ? $eff[$sc['route']] : array();
    $evs   = isset($e['events']) ? $e['events'] : array();
    $cons  = isset($e['cons']) ? $e['cons'] : array();
    $out   = $evs ? ('واقعةٌ مسجَّلةٌ بعقدِ أثر: ' . implode(' · ', $evs)) : '◆ انتقالُ حالةٍ بلا مستندٍ رسمي';
    $next  = $cons ? ('أثرٌ عند: ' . mb_substr(implode(' · ', array_slice($cons, 0, 3)), 0, 240)) : '—';
    $consS = $cons ? mb_substr(implode(' · ', $cons), 0, 240) : '—';
    $inp   = $p['reqs'] ? ('متطلَّبات: ' . implode(' · ', $p['reqs'])) : '—';
    $fin   = (mb_strpos($consS, 'المالية') !== false || mb_strpos($consS, 'التمويل') !== false) ? 'نعم' : 'لا';

    $sql = "INSERT INTO gov_screen_cycle
       (company_id, dept_name, layer_name, stage_order, stage_name, group_name, screen_title, screen_file,
        inputs_note, output_doc, resp_role, next_state, consumers, fin_impact, stage_kind)
       VALUES (0, '" . q5($conn, $dept) . "', 'دورة الأصل', '" . (int) $ord . "', '" . q5($conn, $group) . "',
               '" . q5($conn, $group) . "', '" . q5($conn, $title . ' (' . $bn . ')') . "', '" . q5($conn, $bn) . "',
               '" . q5($conn, $inp) . "', '" . q5($conn, $out) . "', '" . q5($conn, $resp) . "',
               '" . q5($conn, $next) . "', '" . q5($conn, $consS) . "', '$fin', 'canonical')";
    $rows[] = array($bn, $dept, $group, $ord, count($evs), count($cons), $fin);
    if ($DRY) { continue; }
    if ($conn->query($sql) === false) { echo "  ✘ $bn: {$conn->error}\n"; continue; }
    $ins++;
}

echo "═══ إدخالُ أسطحِ W05 في مصفوفةِ الدورة ═══\n";
printf("%-24s %-18s %-28s %3s %3s %3s %s\n", 'الملفّ', 'الإدارة', 'المجموعة', 'ترت', 'حدث', 'مسته', 'مالي');
foreach ($rows as $x) { printf("%-24s %-18s %-28s %3d %3d %3d %s\n", $x[0], $x[1], mb_substr($x[2], 0, 26), $x[3], $x[4], $x[5], $x[6]); }
echo "\nأُدرج: $ins · قائمٌ سلفًا: $skip" . ($DRY ? '  (تجربةٌ جافّة)' : '') . "\n";
