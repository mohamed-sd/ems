<?php
/**
 * tools/repair01_w16_baseline_pack.php — إعادةُ توليدِ المصنَّفاتِ الاثنَي عشرَ من المخزن
 * ═══════════════════════════════════════════════════════════════════════════
 * **مهمّةُ المرحلةِ §٤-١**: «أعِدْ توليدَ المصنَّفاتِ الاثنَي عشرَ من المخزن».
 *
 * ⛔ **وتُكتب في `baseline_v1/` لا فوق المُجمَّد** — والسببُ مقيسٌ لا مُتحفَّظٌ به:
 *   الثلاثةَ عشرَ ملفًّا مختومةٌ بتجزئةِ `sha256` في `repair01_source_files`،
 *   **والحاجبُ `G0-01` يعيد تجزئتَها في كلِّ تشغيل**. فالكتابةُ فوقها تُسقط
 *   أساسَ الحملةِ كلِّها **وتمحو دليلَ ما دخل**. والقرارُ مسجَّلٌ في `W16-D-02`،
 *   **والفحصُ السلبيُّ يُثبته بكسرٍ وإرجاع**.
 *
 * ◆ **والمُخرَجُ إسقاطٌ لا مصدرٌ ثانٍ**: الورقةُ الأولى في كلِّ مصنَّفٍ **بصمةُ
 *   اللقطةِ ونصُّ أنَّ المخزنَ هو المصدر** — فلا يُقرأ المصنَّفُ يومًا حقيقةً
 *   قائمةً بذاتها، وذلك عينُ `DUPLICATE_CANONICAL_SOURCE` الذي تُبقيه الحملةُ صفرًا.
 *
 * التشغيل: php tools/repair01_w16_baseline_pack.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/xlsx_out.php';
require_once $ROOT . '/tools/lib/repair01_w16_scan.php';
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$OUT = $ROOT . '/docs/REPAIR01_20260823/baseline_v1/';
$FROZEN = $ROOT . '/docs/REPAIR01_20260823/';

$one = function ($sql) use ($conn) { return repair01_w16_one($conn, $sql); };
/** يعيد ورقةً: صفُّ عناوينَ ثمَّ صفوفُ القيم. */
$sheet = function ($sql) use ($conn) {
    $r = @$conn->query($sql);
    if (!$r) { return array(array('تعذّر الاستعلام', $conn->error)); }
    $rows = array(); $head = null;
    while ($x = $r->fetch_assoc()) {
        if ($head === null) { $head = array_keys($x); $rows[] = $head; }
        $rows[] = array_values($x);
    }
    if ($head === null) { $rows[] = array('لا صفوف'); }
    return $rows;
};

$snap   = (string) $one("SELECT snapshot_id FROM repair01_freeze_snapshot ORDER BY frozen_at DESC LIMIT 1");
$commit = trim((string) shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse HEAD 2>&1'));
$stampSheet = function ($fileNo, $what) use ($snap, $commit) {
    return array(
        array('اسقاط من المخزن — لا مصدر حقيقة'),
        array('الملف', $fileNo),
        array('ماذا يحمل', $what),
        array('المصدر الحاكم', 'جداول repair01_ في قاعدة النظام — والاكسل صيغة دخول لا صيغة عمل'),
        array('Snapshot ID', $snap),
        array('Commit Hash', $commit),
        array('Generated At', date('Y-m-d H:i:s')),
        array('الاداة', 'tools/repair01_w16_baseline_pack.php'),
        array(''),
        array('لا يحرر هذا الملف يدويا — التعديل في المخزن ثم يعاد التوليد'),
        array('ولم يكتب فوق المصنفات المجمدة: تجزئتها مختومة والحاجب G0-01 يعيدها في كل تشغيل'),
    );
};

$WAVE_SQL = function ($w) {
    return "SELECT requirement_id, wave, stage_no, unit, group_name, surface, grain,
                   source_of_truth, src_ref
              FROM repair01_requirements WHERE wave = '$w' ORDER BY stage_no, requirement_id";
};

$BOOKS = array(

'01 · الدليل المعماري.xlsx' => array(
  'ما يحمل' => 'السجل المعياري للاسطح بمالكها وحكم ملكيتها ودورة حياتها',
  'اوراق' => array(
    'السجل المعياري' => "SELECT screen_id, screen_file, route, owner_code, ownership_verdict,
                                surface_kind, lifecycle, visibility_class, canonical_label_ar,
                                guard_kind, permission_policy, origin
                           FROM repair01_screen_registry ORDER BY owner_code, screen_id",
    'موجز الملكية' => "SELECT owner_code, COUNT(*) AS الاسطح,
                              SUM(lifecycle='LIVE_REGISTERED') AS حي_مسجل,
                              SUM(lifecycle='LIVE_UNREGISTERED') AS حي_غير_مسجل,
                              SUM(lifecycle='GHOST_TARGET') AS مستهدف_غير_مبني
                         FROM repair01_screen_registry GROUP BY owner_code ORDER BY owner_code",
    'المفاتيح' => "SELECT * FROM repair01_key_registry",
  )),

'02 · القيادة.xlsx' => array(
  'ما يحمل' => 'اسطح القيادة والنواب ومساحة العمل بعد اعادة الملكيات الى الادارات',
  'اوراق' => array(
    'اسطح القيادة' => "SELECT screen_id, screen_file, route, owner_code, ownership_verdict,
                              surface_kind, canonical_label_ar, lifecycle
                         FROM repair01_screen_registry
                        WHERE owner_code IN ('EX-CEO','EX-DVP','WS-MY')
                        ORDER BY owner_code, screen_id",
    'متطلبات المساحات' => "SELECT requirement_id, unit, group_name, surface, grain, src_ref
                             FROM repair01_requirements WHERE stage_no = 15
                            ORDER BY requirement_id",
    'مراجعة القيادة' => "SELECT layer_key, layer_name_ar, measured_num, measured_den, den_name, verdict
                           FROM repair01_w16_layers WHERE layer_key IN ('LEADERSHIP','LEADERSHIP_REVIEW')",
  )),

'03 · الدستور.xlsx' => array(
  'ما يحمل' => 'الادارات المعيارية وجسر المسميات الحية والقرارات الحاكمة',
  'اوراق' => array(
    'الادارات' => "SELECT canonical_code, display_order, name_ar, sector, parent_code, note
                     FROM repair01_departments ORDER BY display_order, canonical_code",
    'جسر المسميات' => "SELECT legacy_name, canonical_code, verdict, split_rule, note
                         FROM repair01_dept_crosswalk ORDER BY canonical_code",
    'اصناف الدين' => "SELECT class_code, class_name_ar, measured_count, blocking_level,
                             assigned_wave, debt_owner, exit_criteria, owner_ruling
                        FROM repair01_debt_register ORDER BY class_code",
  )),

'04 · الموجة أ — التشغيل والأصول.xlsx' => array(
  'ما يحمل' => 'متطلبات الموجة الاولى بمرساتها بعد اغلاق مراحلها',
  'اوراق' => array(
    'المتطلبات' => $WAVE_SQL('A'),
    'المراسي' => "SELECT requirement_id, unit, surface, anchor_screen_id, map_rule FROM repair01_w3_scope
                  UNION ALL SELECT requirement_id, unit, surface, anchor_screen_id, map_rule FROM repair01_w4_scope
                  UNION ALL SELECT requirement_id, unit, surface, anchor_screen_id, map_rule FROM repair01_w5_scope
                  UNION ALL SELECT requirement_id, unit, surface, anchor_screen_id, map_rule FROM repair01_w7_scope",
  )),

'05 · الموجة ب — التعاقد والتوريد.xlsx' => array(
  'ما يحمل' => 'متطلبات موجة التعاقد والتوريد بمرساتها',
  'اوراق' => array(
    'المتطلبات' => $WAVE_SQL('B'),
    'المراسي' => "SELECT requirement_id, unit, surface, anchor_screen_id, map_rule FROM repair01_w8_scope
                  UNION ALL SELECT requirement_id, unit, surface, anchor_screen_id, map_rule FROM repair01_w9_scope",
  )),

'06 · الموجة ج — المال.xlsx' => array(
  'ما يحمل' => 'متطلبات موجة المال بعد شق المالية والخزينة',
  'اوراق' => array(
    'المتطلبات' => $WAVE_SQL('C'),
    'المراسي' => "SELECT requirement_id, unit, surface, anchor_screen_id, map_rule FROM repair01_w11_scope
                  UNION ALL SELECT requirement_id, unit, surface, anchor_screen_id, map_rule FROM repair01_w12_scope",
    'الشق' => "SELECT * FROM repair01_w10_split ORDER BY 1",
  )),

'07 · الموجة د — الأشخاص والرقابة.xlsx' => array(
  'ما يحمل' => 'متطلبات موجة الاشخاص والرقابة بمرساتها',
  'اوراق' => array(
    'المتطلبات' => $WAVE_SQL('D'),
    'المراسي' => "SELECT requirement_id, unit, surface, anchor_screen_id, map_rule FROM repair01_w13_scope
                  UNION ALL SELECT requirement_id, unit, surface, anchor_screen_id, map_rule FROM repair01_w14_scope",
  )),

'08 · الموجة هـ — المساحات والتقارير.xlsx' => array(
  'ما يحمل' => 'متطلبات موجة المساحات والتقارير بمرساتها وصنف كل سطح',
  'اوراق' => array(
    'المتطلبات' => $WAVE_SQL('E'),
    'المراسي' => "SELECT requirement_id, unit, surface, space_code, anchor_screen_id,
                         surface_kind, read_mode, map_rule FROM repair01_w15_scope",
  )),

'09 · السجلات المؤسسية والقرارات.xlsx' => array(
  'ما يحمل' => 'قرارات المالك وحالتها ونوع حجبها وسجل تدقيق اعتمادها',
  'اوراق' => array(
    'القرارات' => "SELECT decision_id, domain, status, blocking_level, blocker_type,
                          blocking_reason, approved_by, approved_at, src_ref
                     FROM repair01_decisions ORDER BY decision_id",
    'تدقيق الاعتماد' => "SELECT decision_id, verdict, why, audited_at FROM repair01_decision_audit
                          ORDER BY decision_id",
    'الاحداث' => "SELECT event_code, name, wave, source_unit, contract_status, contract_stage
                    FROM repair01_events ORDER BY event_code",
  )),

'10 · المصالحة مع النظام.xlsx' => array(
  'ما يحمل' => 'مصالحة الاسطح المستهدفة مع القرص وحكم كل صف',
  'اوراق' => array(
    'المصالحة' => "SELECT screen_file, canonical_code, screen_title, layer_name, stage_name,
                          on_disk, disk_path, recon_verdict, screen_id
                     FROM repair01_surfaces ORDER BY canonical_code, screen_file",
    'الفجوات' => "SELECT * FROM repair01_target_gaps ORDER BY 1 LIMIT 400",
  )),

'11 · المراجعة الثانية.xlsx' => array(
  'ما يحمل' => 'المراجعة الثانية كتحد مستقل بمحرك مغاير ومقام كل قاعدة',
  'اوراق' => array(
    'قواعد التحدي' => "SELECT finding_id, rule_key, title, severity, measured, subject,
                              primary_source, evidence, raised_at
                         FROM repair01_w16_challenge ORDER BY finding_id",
  )),

'12 · مراجعة القيادة.xlsx' => array(
  'ما يحمل' => 'الطبقات الثمانية ولوحة المقامات التسعة وسجل الاصدار',
  'اوراق' => array(
    'الطبقات الثمانية' => "SELECT layer_no, layer_key, layer_name_ar, clause_ref,
                                  measured_num, measured_den, den_name, verdict, why
                             FROM repair01_w16_layers ORDER BY layer_no",
    'المحاور التسعة' => "SELECT axis_no, axis_key, axis_name_ar, num_rule, den_rule, instrument
                           FROM repair01_w16_axes ORDER BY axis_no",
    'لوحة المقامات' => "SELECT domain_code, axis_key, num, den, den_name, verdict, note
                          FROM repair01_w16_scorecard ORDER BY domain_code, axis_key",
    'رحلة القبول البشري' => "SELECT station_id, journey_key, station_no, station_ar, domain_code,
                                    required_role, person_slot, is_negative, status
                               FROM repair01_w16_uat ORDER BY journey_key, station_no",
    'سجل الاصدار' => "SELECT * FROM repair01_w16_baseline ORDER BY issued_at DESC",
  )),
);

/* ⛔ حزامٌ صلبٌ قبل الكتابة: لا يُكتب مسارٌ يطابق ملفًّا مجمَّدًا ═════════ */
$frozen = array();
$r = $conn->query("SELECT file_name FROM repair01_source_files");
while ($r && ($x = $r->fetch_row())) { $frozen[$x[0]] = true; }

if (!is_dir($OUT) && !@mkdir($OUT, 0777, true)) { exit("✘ تعذّر إنشاءُ $OUT\n"); }

$made = 0;
foreach ($BOOKS as $name => $def) {
    $target = $OUT . $name;
    /* الحزام: المسارُ المستهدَفُ يجب ألّا يكون ملفَّ الدخولِ المجمَّد */
    if (realpath(dirname($target)) === realpath($FROZEN) && isset($frozen[$name])) {
        exit("⛔ محاولةُ كتابةٍ فوق مصنَّفٍ مجمَّد: $name — وهذا يمحو دليلَ الدخول\n");
    }
    $sheets = array('بصمة الاسقاط' => $stampSheet($name, $def['ما يحمل']));
    foreach ($def['اوراق'] as $sn => $sql) { $sheets[$sn] = $sheet($sql); }
    xlsx_create($target, $sheets);
    $rowsN = 0;
    foreach ($sheets as $s) { $rowsN += count($s); }
    printf("  ✔ %-46s %d ورقة · %d صفًّا\n", $name, count($sheets), $rowsN);
    $made++;
}

/* ── تحقُّقٌ بعديٌّ: المصنَّفاتُ المجمَّدةُ لم تُمَسّ ─────────────────────── */
$drift = 0;
$r = $conn->query("SELECT file_name, sha256 FROM repair01_source_files");
while ($r && ($x = $r->fetch_assoc())) {
    $p = $FROZEN . $x['file_name'];
    if (!is_file($p) || hash_file('sha256', $p) !== $x['sha256']) { $drift++; }
}
printf("\n✔ وُلِّد %d مصنَّفًا في docs/REPAIR01_20260823/baseline_v1/\n", $made);
printf("✔ المصنَّفاتُ المجمَّدةُ بعد التوليد: منحرفةٌ %d من 13\n", $drift);
exit($drift === 0 ? 0 : 1);
