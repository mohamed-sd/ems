<?php
/**
 * tools/govui_field_map_probe.php — **جسرُ حقلِ الورقةِ إلى عمودِه المسمَّى**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الحقيقةُ التي يستثمرها**: `tools/gov_exec_dept_build.php` حين بنى جداولَ
 *   الإداراتِ كتب **اسمَ حقلِ الورقةِ في تعليقِ العمودِ حرفًا** (‏«وأسماءُ الأعمدةِ
 *   تعليقُها اسمُ الحقلِ في ورقةِ الدليلِ حرفًا»). ⇒ **فالجسرُ موجودٌ في المخطَّطِ
 *   نفسِه** ولا يُخترع: يُقرأ من `information_schema.COLUMNS`.
 *
 * ◆ **والحاجزُ الذي يرفعه**: أسطحٌ مكتوبةٌ بيدٍ سابقةٍ **لها جدولُ دليلٍ قائمٌ
 *   بأعمدةٍ مسمّاةٍ بأسماءِ الورقة**، والمولِّدُ رفض أن يدهسها بحقٍّ — فبقيت
 *   الأعمدةُ في المخزنِ ولا يُصيَّر منها حرف ([[iaf-field-closure]]). وتأليفُ
 *   الخريطةِ يدًا لثلاثِ مئةِ سطحٍ عملٌ يُخطئ؛ **والمخطَّطُ يعرفها**.
 *
 * ◆ **والمطابقةُ بقاعدةِ القبولِ نفسِها** (`tools/lib/rpr02_field_lib.php`) —
 *   ⛔ ولا معيارَ ثانٍ لنفسِ السؤال ([[counter-parity-two-readers]]).
 *
 * ⛔ **ولا يكتب شيئًا** — يُخرج مواصفةً جاهزةً للفرزِ البشريّ، **والحكمُ الأخير
 *   للمؤلِّف**: عمودٌ اقتُرح بتطابقٍ جزئيٍّ يُراجَع، وحقلٌ بلا عمودٍ يُعلَن
 *   `NO_COLUMN` باسمِه ليُحسَم (‏عمودٌ جديدٌ أو اشتقاقٌ).
 *
 * التشغيل:
 *   php tools/govui_field_map_probe.php --req=SUP-31 [--table=sup_list_ref]
 *   php tools/govui_field_map_probe.php --unit=DEP-02        ← كلُّ ناقصٍ فيها
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/tools/lib/rpr02_field_lib.php';
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');

$REQ = null; $UNIT = null; $TBL = null;
foreach ($argv as $a) {
    if (strpos($a, '--req=') === 0)        { $REQ = substr($a, 6); }
    elseif (strpos($a, '--unit=') === 0)   { $UNIT = substr($a, 7); }
    elseif (strpos($a, '--table=') === 0)  { $TBL = substr($a, 8); }
}
if ($REQ === null && $UNIT === null) {
    exit("الاستعمال: php tools/govui_field_map_probe.php --req=<REQ> [--table=T] | --unit=<UNIT>\n");
}

/* ── دفترُ الأعمدةِ المسمّاة: تعليقُ العمودِ ⇐ (جدولٌ · عمودٌ) ───────────────── */
$byTable = array();
$q = $conn->query("SELECT TABLE_NAME t, COLUMN_NAME c, COLUMN_COMMENT k
                     FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_COMMENT <> ''
                    ORDER BY TABLE_NAME, ORDINAL_POSITION");
while ($q && $r = $q->fetch_assoc()) {
    $byTable[$r['t']][] = array('col' => $r['c'], 'label' => $r['k']);
}

/* ── الأسطحُ المطلوبة ─────────────────────────────────────────────────────── */
$targets = array();
if ($REQ !== null) { $targets[] = $REQ; }
else {
    $st = $conn->prepare("SELECT DISTINCT tu.requirement_id
                            FROM repair01_target_universe tu
                           WHERE tu.verdict = 'MATCHED' AND tu.unit = ? AND tu.requirement_id <> ''
                           ORDER BY tu.requirement_id");
    $st->bind_param('s', $UNIT); $st->execute();
    $rs = $st->get_result();
    while ($x = $rs->fetch_assoc()) { $targets[] = $x['requirement_id']; }
}

/* مسارُ السطحِ لكلِّ متطلب — للإخراجِ الجاهز */
$route = array();
$q = $conn->query("SELECT tu.requirement_id, sr.route, sr.canonical_label_ar
                     FROM repair01_target_universe tu
                     JOIN repair01_screen_registry sr ON sr.screen_id = tu.screen_id
                    WHERE tu.verdict = 'MATCHED'");
while ($q && $r = $q->fetch_assoc()) {
    if (!isset($route[$r['requirement_id']])) {
        $route[$r['requirement_id']] = array(str_replace(chr(92), '/', (string) $r['route']),
                                             (string) $r['canonical_label_ar']);
    }
}

foreach ($targets as $req) {
    $st = $conn->prepare("SELECT field_name, field_type FROM repair01_fields
                           WHERE requirement_id = ? ORDER BY id");
    $st->bind_param('s', $req); $st->execute();
    $rs = $st->get_result();
    $fields = array();
    while ($x = $rs->fetch_assoc()) { $fields[] = $x; }
    $st->close();
    if (!$fields) { continue; }

    /* ── أفضلُ جدولٍ: أكثرُ تعليقاتِ أعمدةٍ تطابق أسماءَ حقولِ الورقة ───────── */
    $best = $TBL; $bestN = -1; $scores = array();
    $cands = ($TBL !== null) ? array($TBL) : array_keys($byTable);
    foreach ($cands as $t) {
        if (!isset($byTable[$t])) { continue; }
        $bagStr = array(); $bagTok = array();
        foreach ($byTable[$t] as $c0) {
            $nv = fm_norm($c0['label']);
            if ($nv !== '') { $bagStr[$nv] = 1; }
            foreach (fm_tok($c0['label'], $FM_STOP) as $tk) { $bagTok[$tk] = 1; }
        }
        $n = 0;
        foreach ($fields as $f) {
            if ($f['field_type'] === 'AUDIT') { continue; }
            if (fm_hit(fm_tok($f['field_name'], $FM_STOP), $bagTok, $bagStr,
                       fm_norm($f['field_name'])) !== '') { $n++; }
        }
        $scores[$t] = $n;
        if ($n > $bestN) { $bestN = $n; $best = $t; }
    }
    arsort($scores);

    $rt = isset($route[$req]) ? $route[$req] : array('?', '?');
    printf("\n══ %s · %s · %s\n", $req, $rt[1], $rt[0]);
    $top = array_slice($scores, 0, 3, true);
    $line = array();
    foreach ($top as $t => $n) { $line[] = $t . ' (' . $n . ')'; }
    echo "   المرشَّحون: " . implode(' · ', $line) . "\n";
    if ($best === null || !isset($byTable[$best])) { echo "   ⛔ لا جدولَ مرشَّحًا\n"; continue; }

    /* ── الخريطةُ المقترَحة: حقلُ الورقةِ ⇐ عمودُه المسمَّى ─────────────────── */
    $used = array();
    echo "    'map' => array(\n";
    foreach ($fields as $f) {
        $isAudit = ($f['field_type'] === 'AUDIT');
        $dTok = fm_tok($f['field_name'], $FM_STOP);
        $dNorm = fm_norm($f['field_name']);
        $pick = ''; $how = '';
        foreach ($byTable[$best] as $c0) {
            if (isset($used[$c0['col']])) { continue; }
            $cNorm = fm_norm($c0['label']);
            if ($cNorm !== '' && $cNorm === $dNorm) { $pick = $c0['col']; $how = 'EXACT'; break; }
        }
        if ($pick === '') {
            foreach ($byTable[$best] as $c0) {
                if (isset($used[$c0['col']])) { continue; }
                $bagStr = array(fm_norm($c0['label']) => 1);
                $bagTok = array();
                foreach (fm_tok($c0['label'], $FM_STOP) as $tk) { $bagTok[$tk] = 1; }
                if (fm_hit($dTok, $bagTok, $bagStr, $dNorm) !== '') {
                    $pick = $c0['col']; $how = 'COVER'; break;
                }
            }
        }
        $clean = str_replace(array('◄', '▼'), '', $f['field_name']);
        $clean = trim(preg_replace('~\s+~u', ' ',
                 preg_replace('~[\x{0640}\x{064B}-\x{0652}\x{0670}]~u', '', $clean)));
        if ($pick !== '') {
            $used[$pick] = 1;
            printf("        '%s' => '%s',%s\n", $clean, $pick,
                   $how === 'COVER' ? '   // COVER — راجِعْ' : '');
        } else {
            printf("        '%s' => '',   // NO_COLUMN%s\n", $clean, $isAudit ? ' (AUDIT)' : '');
        }
    }
    echo "    ),\n";
    $left = array();
    foreach ($byTable[$best] as $c0) { if (!isset($used[$c0['col']])) { $left[] = $c0['col']; } }
    if ($left) { echo "   أعمدةٌ لم تُستعمَل: " . implode(' · ', array_slice($left, 0, 20)) . "\n"; }
}
echo "\n";
