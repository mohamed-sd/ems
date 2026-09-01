<?php
/**
 * 2028_03_07_govui_field_ledger_settle.php — تسويةُ دفترِ الحقولِ على الحزمةِ الجديدة
 * ═══════════════════════════════════════════════════════════════════════════
 * @migration-objects: rebuild:repair01_fields + update design columns of repair01_requirements
 *                     + backup:govui_fields_pre_settle
 *
 * ◆ **لِمَ قبلَ أيِّ قياسِ حقول** (البرومت §4): `09 › 02_تتبع_الحقول` تغيّر في
 *   **441 صفًّا** و`09 › 01_سجل_المتطلبات` في **45**. والمقياسُ القائمُ
 *   `2546/5420` مبنيٌّ على الدفترِ **القديم** — فاستئنافُ الحملةِ عليه
 *   **قياسٌ على مقامٍ متقادم**، وهو أخطرُ من غيابِ القياس.
 *
 * ◆ **وحدُّ المسِّ مرسومٌ بدقّة** [[registry-rule-vs-value]] — «الاستيعابُ يدهس
 *   أحكامَ الموجات»:
 *   · `repair01_fields` **يُعاد بناؤه كاملًا**: أعمدتُه كلُّها **تصميمٌ** لا
 *     حكمَ فيه (لا حالةَ ولا شاهدَ ولا لقطة) — فإعادةُ البناءِ لا تمحو حكمًا.
 *   · `repair01_requirements` **يُحدَّث ولا يُحذف**: تُكتب أعمدةُ التصميمِ
 *     السبعةُ وحدَها (`wave`·`unit`·`dependency`·`seq`·`group_name`·`surface`
 *     ·`grain`·`source_of_truth`·`src_ref`)، **وتُترك أعمدةُ الحكمِ كما هي**:
 *     `amd01_state` · `state_evidence` · `state_snapshot` · `stage_no` ·
 *     `identity_status` · `sm_model_ref` · `grain_entity` وشواهدُها.
 *   ⛔ **ولا صفَّ متطلَّبٍ يُحذف هنا**: صفٌّ في المخزنِ لا مقابلَ له في الورقةِ
 *     يُعلَن ولا يُمحى — فالمحوُ يُيتِّم أحكامًا مُغلَقةً بالدليل.
 *
 * ◆ **والفرقُ يُعلَن بمقامِه**: يُطبع عددُ الحقولِ قبلَ وبعدَ، وعددُ المتطلَّباتِ
 *   المحدَّثةِ والجديدةِ والمهجورة — فارتفاعُ المقامِ يُقرأ صعودًا لا هبوطًا.
 *
 * التشغيل: php database/migrations/2028_03_07_govui_field_ledger_settle.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once $ROOT . '/tools/lib/xlsx_io.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);

$cell = function ($r, $i) { return isset($r[$i]) ? trim((string) $r[$i]) : ''; };

/* ═══ ⓪ نسخةُ الرجوعِ — الدفترُ كما كان ═══ */
$conn->query("DROP TABLE IF EXISTS `govui_fields_pre_settle`");
if (!$conn->query("CREATE TABLE `govui_fields_pre_settle` LIKE `repair01_fields`")) {
    exit("⛔ نسخةُ الرجوع: {$conn->error}\n");
}
$conn->query("INSERT INTO `govui_fields_pre_settle` SELECT * FROM `repair01_fields`");
$before = (int) $conn->query("SELECT COUNT(*) FROM repair01_fields")->fetch_row()[0];
echo "  ⟲ نسخةُ رجوعٍ: {$before} حقلًا في govui_fields_pre_settle\n";

$WB = xlsx_read($ROOT . '/docs/REPAIR01_20260823/09 · السجلات المؤسسية والقرارات.xlsx');

/* ═══ ① الحقول — إعادةُ بناءٍ كاملة (لا حكمَ في أعمدتِها) ═══ */
$conn->query("DELETE FROM repair01_fields");
$ins = $conn->prepare("INSERT INTO repair01_fields
    (requirement_id, wave, unit, surface, seq, field_name, field_type, visibility_rule, src_ref)
    VALUES (?,?,?,?,?,?,?,?,?)");
if (!$ins) { exit("⛔ prepare: {$conn->error}\n"); }
$rows = $WB['02_تتبع_الحقول']; ksort($rows);
$nF = 0;
foreach ($rows as $ri => $r) {
    if ($ri <= 3) { continue; }
    ksort($r);
    $fn = $cell($r, 5);
    if ($fn === '' || $fn === 'اسم الحقل') { continue; }
    $rid = $cell($r, 0); $wv = $cell($r, 1); $un = $cell($r, 2); $sf = $cell($r, 3);
    $sq = $cell($r, 4); $ft = $cell($r, 6); $vr = $cell($r, 7);
    $sr = '09 › 02_تتبع_الحقول › ص' . ($ri + 1);
    $ins->bind_param('sssssssss', $rid, $wv, $un, $sf, $sq, $fn, $ft, $vr, $sr);
    if (!$ins->execute()) { exit("⛔ حقل ص" . ($ri + 1) . ": {$conn->error}\n"); }
    $nF++;
}
$ins->close();
printf("  ✔ الحقول: %d ⇒ %d (فرق %+d)\n", $before, $nF, $nF - $before);

/* ═══ ② المتطلَّبات — تحديثُ التصميمِ وحدَه، وأعمدةُ الحكمِ لا تُمَسّ ═══ */
$upd = $conn->prepare("UPDATE repair01_requirements
    SET wave = ?, unit = ?, dependency = ?, seq = ?, group_name = ?, surface = ?,
        grain = ?, source_of_truth = ?, src_ref = ?
  WHERE requirement_id = ?");
$insReq = $conn->prepare("INSERT INTO repair01_requirements
    (requirement_id, wave, unit, dependency, seq, group_name, surface, grain, source_of_truth, src_ref)
    VALUES (?,?,?,?,?,?,?,?,?,?)");
if (!$upd || !$insReq) { exit("⛔ prepare req: {$conn->error}\n"); }
$have = array();
$q = $conn->query("SELECT requirement_id FROM repair01_requirements");
while ($x = $q->fetch_row()) { $have[$x[0]] = 0; }
$rows = $WB['01_سجل_المتطلبات']; ksort($rows);
$nU = 0; $nN = 0; $seen = array();
foreach ($rows as $ri => $r) {
    if ($ri <= 1) { continue; }
    ksort($r);
    $id = $cell($r, 0);
    if ($id === '' || strtolower($id) === 'requirement_id') { continue; }
    $seen[$id] = 1;
    $v = array($cell($r, 1), $cell($r, 2), $cell($r, 3), $cell($r, 4), $cell($r, 5),
        $cell($r, 6), $cell($r, 7), $cell($r, 8), '09 › 01_سجل_المتطلبات › ص' . ($ri + 1));
    if (isset($have[$id])) {
        $upd->bind_param('ssssssssss', $v[0], $v[1], $v[2], $v[3], $v[4], $v[5], $v[6], $v[7], $v[8], $id);
        $upd->execute();
        if ($conn->affected_rows > 0) { $nU++; }
        $have[$id] = 1;
    } else {
        $insReq->bind_param('ssssssssss', $id, $v[0], $v[1], $v[2], $v[3], $v[4], $v[5], $v[6], $v[7], $v[8]);
        if ($insReq->execute()) { $nN++; }
    }
}
$orphan = array();
foreach ($have as $id => $hit) { if ($hit === 0) { $orphan[] = $id; } }
printf("  ✔ المتطلَّبات: محدَّثٌ %d · جديدٌ %d · في المخزنِ بلا صفٍّ في الورقة %d\n", $nU, $nN, count($orphan));
if ($orphan) {
    echo "     ⛔ ولا يُحذف واحدٌ منها — تُعلَن بأسمائها:\n";
    foreach (array_slice($orphan, 0, 12) as $o) { echo "        · {$o}\n"; }
    if (count($orphan) > 12) { echo "        · … و" . (count($orphan) - 12) . " غيرُها\n"; }
}
$tot = (int) $conn->query("SELECT COUNT(*) FROM repair01_requirements")->fetch_row()[0];
echo "  ◆ المقامُ بعدَ التسوية: متطلَّباتٌ {$tot} · حقولٌ {$nF}\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
