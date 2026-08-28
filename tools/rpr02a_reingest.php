<?php
/**
 * tools/rpr02a_reingest.php — الطورُ صفر: إعادةُ استيعابِ الحزمةِ بعدَّادٍ قبلَ وبعد
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المخزنُ حكمٌ والتقريرُ إسقاطٌ منه** — فلا يُحرَّر `RPR02A_REINGEST.md`
 *   يدويًّا: يُولَّد من هذا التشغيلِ وحدَه، وسطرُ أمرِه في رأسِه.
 * ◆ **وكلُّ جدولٍ يمسُّه الاستيعابُ يُعَدُّ قبلَ وبعد** — والصفرُ المقيسُ يُكتب
 *   صفرًا (م 111) ولا يُترك فراغًا.
 * ◆ **ولا يُصحَّح المخرَج**: إن نقص عددٌ بعد التشغيل كُتب النقصُ كما هو،
 *   وأُصلحت الأداةُ ثمَّ أُعيد التشغيل — لا تُحرَّر الأرقام.
 *
 * التشغيل: php tools/rpr02a_reingest.php [--dry] [--scope=design|all]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI only\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$DRY   = in_array('--dry', $argv, true);
$SCOPE = 'design';
foreach ($argv as $a) { if (strpos($a, '--scope=') === 0) { $SCOPE = substr($a, 8); } }

/* الرمزُ المعياريُّ — اثنانِ وعشرون بعد تسجيلِ PLATFORM */
$CODE_RE = '^(DEP-[0-9]{2}|EX-CEO|EX-DVP|IAF|WS-MY|PLATFORM)$';
$SEP     = json_decode('"›"');                    /* › */
$WB_LIKE = "'10 " . $SEP . " 04\\_%'";

$MEASURES = array(
    'repair01_source_files'    => "SELECT COUNT(*) FROM repair01_source_files",
    'repair01_decisions'       => "SELECT COUNT(*) FROM repair01_decisions",
    'dec_needs_owner'          => "SELECT COUNT(*) FROM repair01_decisions WHERE status='NEEDS_OWNER_DECISION'",
    'repair01_departments'     => "SELECT COUNT(*) FROM repair01_departments",
    'dept_platform'            => "SELECT COUNT(*) FROM repair01_departments WHERE canonical_code='PLATFORM'",
    'repair01_dept_crosswalk'  => "SELECT COUNT(*) FROM repair01_dept_crosswalk",
    'repair01_surfaces'        => "SELECT COUNT(*) FROM repair01_surfaces",
    'srf_canon'                => "SELECT COUNT(*) FROM repair01_surfaces WHERE canonical_code IS NOT NULL AND canonical_code<>''",
    'srf_screen_id'            => "SELECT COUNT(*) FROM repair01_surfaces WHERE screen_id<>''",
    'repair01_target_gaps'     => "SELECT COUNT(*) FROM repair01_target_gaps",
    'gap_from_workbook'        => "SELECT COUNT(*) FROM repair01_target_gaps WHERE src_ref LIKE $WB_LIKE",
    'gap_from_stages'          => "SELECT COUNT(*) FROM repair01_target_gaps WHERE src_ref NOT LIKE $WB_LIKE",
    'gap_distinct_unit'        => "SELECT COUNT(DISTINCT unit) FROM repair01_target_gaps",
    'gap_unit_code'            => "SELECT COUNT(*) FROM repair01_target_gaps WHERE unit REGEXP '$CODE_RE'",
    'gap_unit_name'            => "SELECT COUNT(*) FROM repair01_target_gaps WHERE unit NOT REGEXP '$CODE_RE'",
    'gap_split_code'           => "SELECT COUNT(*) FROM repair01_target_gaps WHERE split_code<>''",
    'gap_ghost_disp'           => "SELECT COUNT(*) FROM repair01_target_gaps WHERE ghost_disposition<>''",
    'gap_built_ctp'            => "SELECT COUNT(*) FROM repair01_target_gaps WHERE built_counterpart<>''",
    'repair01_requirements'    => "SELECT COUNT(*) FROM repair01_requirements",
    'req_distinct_unit'        => "SELECT COUNT(DISTINCT unit) FROM repair01_requirements",
    'req_stage_no'             => "SELECT COUNT(*) FROM repair01_requirements WHERE stage_no IS NOT NULL",
    'repair01_fields'          => "SELECT COUNT(*) FROM repair01_fields",
    'repair01_events'          => "SELECT COUNT(*) FROM repair01_events",
    'repair01_ownership'       => "SELECT COUNT(*) FROM repair01_ownership",
    'own_w1'                   => "SELECT COUNT(*) FROM repair01_ownership WHERE w1_verdict IS NOT NULL",
    'own_forbidden'            => "SELECT COUNT(*) FROM repair01_ownership WHERE classification='FORBIDDEN'",
    'gov_screen_cycle'         => "SELECT COUNT(*) FROM gov_screen_cycle",
);

$LBL = array(
    'repair01_source_files'   => '`repair01_source_files`',
    'repair01_decisions'      => '`repair01_decisions`',
    'dec_needs_owner'         => '— منها `NEEDS_OWNER_DECISION`',
    'repair01_departments'    => '`repair01_departments`',
    'dept_platform'           => '— منها `PLATFORM`',
    'repair01_dept_crosswalk' => '`repair01_dept_crosswalk`',
    'repair01_surfaces'       => '`repair01_surfaces` ⛔ خارجَ نطاقِ التصميم',
    'srf_canon'               => '— منها بالرمزِ المعياريّ (كتابةُ W01)',
    'srf_screen_id'           => '— منها بمعرِّفِ شاشة (كتابةُ W02)',
    'repair01_target_gaps'    => '`repair01_target_gaps`',
    'gap_from_workbook'       => '— منها من المصنَّف',
    'gap_from_stages'         => '— منها من نموِّ المراحل (W02)',
    'gap_distinct_unit'       => '— `COUNT(DISTINCT unit)`',
    'gap_unit_code'           => '— منها `unit` بالرمز',
    'gap_unit_name'           => '— منها `unit` بالاسمِ الحيِّ القديم',
    'gap_split_code'          => '— منها بـ`split_code` (كتابةُ W10)',
    'gap_ghost_disp'          => '— منها بـ`ghost_disposition` (كتابةُ W135)',
    'gap_built_ctp'           => '— منها بـ`built_counterpart` (كتابةُ W12)',
    'repair01_requirements'   => '`repair01_requirements` ◄ **المقامُ الحاكم**',
    'req_distinct_unit'       => '— `COUNT(DISTINCT unit)`',
    'req_stage_no'            => '— منها بمرحلةٍ مُسنَدة',
    'repair01_fields'         => '`repair01_fields`',
    'repair01_events'         => '`repair01_events`',
    'repair01_ownership'      => '`repair01_ownership`',
    'own_w1'                  => '— منها بحكمِ `W01` ◄ **شرطُ القبولِ ⑤**',
    'own_forbidden'           => '— منها `FORBIDDEN`',
    'gov_screen_cycle'        => '`gov_screen_cycle` — المصدرُ الحيُّ للأسطح',
);

/* الأربعةُ المسمّاةُ في §٤·٠ — حضورُها شرطُ قبول */
$NAMED = array(
    'سجل أنواع الطلبات والتوجيه'     => '`08` الحوكمة والالتزام · 31/32',
    'صندوق بلاغات الإدارة'           => '`10` البلاغات · 02/13',
    'الإبلاغ السياقي من داخل الشاشة' => '`10` البلاغات · 04/13',
    'تقطيع مسؤولية التوقف'           => '`14` الصيانة · 06/17',
);

function rpr_snap($conn, $MEASURES) {
    $o = array();
    foreach ($MEASURES as $k => $q) { $r = $conn->query($q); $o[$k] = $r ? (int) $r->fetch_row()[0] : null; }
    return $o;
}
function rpr_named($conn, $NAMED) {
    $o = array();
    foreach ($NAMED as $nm => $dep) {
        $r = $conn->query("SELECT COUNT(*) FROM repair01_requirements WHERE surface='" . $conn->real_escape_string($nm) . "'");
        $o[$nm] = $r ? (int) $r->fetch_row()[0] : 0;
    }
    return $o;
}

$before      = rpr_snap($conn, $MEASURES);
$beforeNamed = rpr_named($conn, $NAMED);

$cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($ROOT . '/tools/repair01_ingest.php')
     . ' --reset --scope=' . $SCOPE . ($DRY ? ' --dry' : '');
$out = array(); $rc = 0;
exec($cmd . ' 2>&1', $out, $rc);
$ingestOut = implode("\n", $out);

$after      = rpr_snap($conn, $MEASURES);
$afterNamed = rpr_named($conn, $NAMED);

$ts = date('Y-m-d H:i:s');
$md  = "# RPR-02-A · الطورُ صفر — إعادةُ استيعابِ الحزمةِ الجديدة\n\n";
$md .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/rpr02a_reingest.php --scope=" . $SCOPE . ($DRY ? ' --dry' : '') . "`\n";
$md .= "> **مولَّدٌ**: " . $ts . "\n";
$md .= "> **الاستدعاءُ الداخليّ**: `php tools/repair01_ingest.php --reset --scope=" . $SCOPE . ($DRY ? ' --dry' : '') . "` — رمزُ الخروجِ `" . $rc . "`\n";
if ($DRY) {
    $md .= ">\n> ⚠ **تشغيلٌ جافّ** — والفرقُ أدناه يجب أن يكون **صفرًا في كلِّ سطر**؛ وإلّا فالعلمُ يَعِد بلا كتابةٍ ثمَّ يكتب.\n";
}
$md .= "\n## ① العدُّ قبلَ وبعد — كلُّ جدولٍ يمسُّه الاستيعاب\n\n";
$md .= "| المقياس | قبل | بعد | Δ |\n|---|---:|---:|---:|\n";
foreach ($MEASURES as $k => $q) {
    $b = $before[$k]; $a = $after[$k];
    $lbl = isset($LBL[$k]) ? $LBL[$k] : '`' . $k . '`';
    if ($b === null || $a === null) { $ds = '—'; }
    else { $d = $a - $b; $ds = $d > 0 ? '**+' . $d . '**' : ($d < 0 ? '**' . $d . '**' : '0'); }
    $md .= '| ' . $lbl . ' | ' . ($b === null ? '—' : $b) . ' | ' . ($a === null ? '—' : $a) . ' | ' . $ds . " |\n";
}

/* ═══ ①-ب مقامُ الحزمتَين من المصنَّفَين أنفسِهما ═══
   ⚠ **جدولُ «قبل/بعد» أعلاه يصف هذا التشغيلَ وحدَه** — فإعادةُ التشغيلِ تجعله
     أصفارًا وتمحو دليلَ حركةِ الجولة (429 ⇒ 433). فالمقامُ هنا **يُشتقُّ من
     المصنَّفَين لا من ترتيبِ التشغيل**: النسخةُ المحفوظةُ قبلَ الاستبدالِ في
     `_pkg_backup_20260828/` × النسخةُ النافذة. ⛔ ولا رقمَ يُكتب بيد. */
require_once $ROOT . '/tools/lib/xlsx_io.php';
function rpr_req_count($xlsx) {
    if (!is_file($xlsx)) { return null; }
    $wb = xlsx_read($xlsx);
    if (!isset($wb['01_سجل_المتطلبات'])) { return null; }
    $n = 0; $names = array();
    foreach ($wb['01_سجل_المتطلبات'] as $ri => $r) {
        if ($ri <= 1) { continue; }
        ksort($r);
        $id = trim((string) ($r[0] ?? ''));
        if ($id === '' || strtolower($id) === 'requirement_id') { continue; }
        $n++; $names[trim((string) ($r[6] ?? ''))] = 1;
    }
    return array('rows' => $n, 'names' => $names);
}
$oldWb = rpr_req_count($ROOT . '/docs/REPAIR01_20260823/_pkg_backup_20260828/09 · السجلات المؤسسية والقرارات.xlsx');
$newWb = rpr_req_count($ROOT . '/docs/REPAIR01_20260823/09 · السجلات المؤسسية والقرارات.xlsx');
$md .= "\n## ①-ب مقامُ الحزمتَين — مشتقًّا من المصنَّفَين لا من ترتيبِ التشغيل\n\n";
$md .= "> جدولُ «قبل/بعد» أعلاه يصف **هذا التشغيلَ وحدَه**، فيصير أصفارًا عند إعادتِه. وهذا الجدولُ **لا يتغيّر بإعادةِ التشغيل**:\n";
$md .= "> يُقرأ `01_سجل_المتطلبات` من **النسخةِ المحفوظةِ قبلَ الاستبدال** ومن **النسخةِ النافذة**.\n\n";
$md .= "| المصدر | صفوفُ سجلِّ المتطلَّبات |\n|---|---:|\n";
$md .= "| الحزمةُ السابقة — `_pkg_backup_20260828/09 · …` | **" . ($oldWb === null ? '—' : $oldWb['rows']) . "** |\n";
$md .= "| الحزمةُ النافذة — `09 · …` | **" . ($newWb === null ? '—' : $newWb['rows']) . "** |\n";
if ($oldWb !== null && $newWb !== null) {
    $md .= "| **الفارق** | **+" . ($newWb['rows'] - $oldWb['rows']) . "** |\n";
    $added = array_diff_key($newWb['names'], $oldWb['names']);
    $md .= "\n**الأسطحُ التي استُجدَّت بالاسم (" . count($added) . "):**\n\n";
    foreach (array_keys($added) as $nm) { $md .= '- ' . $nm . "\n"; }
}

$md .= "\n## ② الأربعةُ المسمّاةُ — حضورُها في `repair01_requirements`\n\n";
$md .= "| السطحُ الغائبُ عن السجل | موضعُه في الدليل | قبل | بعد |\n|---|---|---:|---:|\n";
$allIn = true;
foreach ($NAMED as $nm => $dep) {
    $md .= '| ' . $nm . ' | ' . $dep . ' | ' . $beforeNamed[$nm] . ' | ' . $afterNamed[$nm] . " |\n";
    if ($afterNamed[$nm] < 1) { $allIn = false; }
}
$md .= "\n**الحكم:** " . ($allIn ? '✔ الأربعةُ حاضرةٌ في السجل.' : '⛔ ليست كلُّها حاضرةً — لا يُغلق الطور.') . "\n";

$md .= "\n## ③ توزيعُ `unit` في `repair01_target_gaps` بعد التوحيد\n\n";
$md .= "| `unit` | صفوف |\n|---|---:|\n";
$rg = $conn->query("SELECT unit, COUNT(*) n FROM repair01_target_gaps GROUP BY unit ORDER BY unit");
$rows = 0;
while ($x = $rg->fetch_assoc()) { $md .= '| `' . $x['unit'] . '` | ' . $x['n'] . " |\n"; $rows++; }
$md .= "\n**القيمُ المميَّزة: " . $rows . "** · العتبةُ `≤ 22` ⇒ **" . ($rows <= 22 ? '✔' : '⛔') . "**\n";

$md .= "\n## ④ توزيعُ `unit` في `repair01_requirements`\n\n";
$md .= "> ⚠ **يبقى بلغةِ الاسمِ عمدًا**: `repair01_w8_apply.php:631` و`repair01_w8_gate.php:318`\n";
$md .= "> يجسران الجدولَين بـ`r.unit LIKE CONCAT(SUBSTRING(g.unit,5),' %')` — أي يشتقّان\n";
$md .= "> `NN` من رمزِ الفجوةِ ويطابقانه باسمِ المتطلَّب. فإعادةُ كتابةِ هذا العمودِ رموزًا\n";
$md .= "> **تكسر حاجبَ W08** — و«حاجبٌ يوجب كسرَ حاجبٍ آخرَ ليس حاجبًا». واللغةُ هنا **واحدةٌ\n";
$md .= "> أصلًا** (21 قيمةً كلُّها بصيغةِ `NN اسم`)، فشرطُ «لغةٍ واحدة» مستوفًى بلا كسر.\n\n";
$md .= "| `unit` | صفوف |\n|---|---:|\n";
$rr = $conn->query("SELECT unit, COUNT(*) n FROM repair01_requirements GROUP BY unit ORDER BY unit");
$rrows = 0;
while ($x = $rr->fetch_assoc()) { $md .= '| ' . $x['unit'] . ' | ' . $x['n'] . " |\n"; $rrows++; }
$md .= "\n**القيمُ المميَّزة: " . $rrows . "** · العتبةُ `≤ 22` ⇒ **" . ($rrows <= 22 ? '✔' : '⛔') . "**\n";

$md .= "\n## ⑤ مخرَجُ الاستيعابِ حرفيًّا\n\n```\n" . trim($ingestOut) . "\n```\n";

$path = $ROOT . '/docs/REPAIR01_20260823/RPR02A_REINGEST.md';
if (!$DRY) { file_put_contents($path, $md); }
echo $md;
echo $DRY ? "\n[DRY - report not written]\n" : "\n=> " . $path . "\n";
