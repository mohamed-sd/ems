<?php
/**
 * 2027_03_21 — إعادةُ إسقاطِ مرايا عقودِ الموردين الموروثة
 * ═══════════════════════════════════════════════════════════════════════════
 * `tests/supplier_contract_lines_test.php` يشترط في بندِه ① («الترحيلُ **قراءةً**
 * — المصدرُ يبقى الكاتب · N-04 مرحلة ①») أنَّ **لكلِّ رأسٍ موروثٍ مرآةً**:
 *   `COUNT(supplierscontracts)` = `COUNT(supplier_contracts WHERE source_table='supplierscontracts')`
 * والمقيسُ: **20 رأسًا و7 مرايا** — 13 بلا مرآة. وسببُ النقصِ أنَّ المرايا
 * الملفَّقةَ أُزيلت في حملةِ الـ958 صفًّا (كان المِلءُ العامُّ يكتب في
 * `supplier_contracts` خامًا لأنه غائبٌ عن القسم ج في المانفست — أُدرج اليومَ).
 *
 * ── والمرآةُ **إسقاطٌ حتميٌّ** لا بيانٌ يُخترع ────────────────────────────────
 * استُنبط التخطيطُ من المرايا السبعِ القائمةِ وأصولِها، لا من تخمين:
 *   `company_id`         ⇐ نفسُه
 *   `supplier_id`        ⇐ نفسُه
 *   `client_contract_id` ⇐ `project_contract_id`
 *   `project_id`         ⇐ نفسُه
 *   `start_date/end_date`⇐ `actual_start` / `actual_end`
 *   `currency`           ⇐ عملةُ العقدِ مُطبَّعةً («دولار» ⇒ USD · وإلا الأساسُ)
 *   `state`              ⇐ `status` (1 ⇒ «نافذ» · وإلا «مسودة»)
 *   `notes`              ⇐ «مرحَّلٌ قراءةً من supplierscontracts#N — …»
 * فما لا مصدرَ له **يُترك نُلًّا** ولا يُلفَّق له قيمة.
 *
 * ◆ ولا يُمَسُّ رأسٌ له مرآةٌ سلفًا: الإسقاطُ للناقصِ وحدَه، والهجرةُ عاطلةٌ.
 * ◆ ويُفحَص مُرجَعُ كلِّ إدراجٍ، والعدُّ قبلَ وبعدَ هو الحكم.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

$one = function ($sql) use ($db) {
    $r = $db->query($sql);
    if ($r === false) { fwrite(STDERR, 'استعلامٌ فشل: ' . $db->error . "\n"); return null; }
    $x = $r->fetch_row();
    return $x === null ? null : $x[0];
};

$heads   = $one('SELECT COUNT(*) FROM supplierscontracts');
$mirrors = $one("SELECT COUNT(*) FROM supplier_contracts WHERE source_table = 'supplierscontracts'");
if ($heads === null || $mirrors === null) { exit(1); }
echo "── ① رؤوسٌ موروثةٌ {$heads} · مرايا {$mirrors}\n";

$missing = $db->query("SELECT s.* FROM supplierscontracts s
                        WHERE NOT EXISTS (SELECT 1 FROM supplier_contracts m
                                           WHERE m.source_table = 'supplierscontracts'
                                             AND m.source_id = s.id)
                        ORDER BY s.id");
if ($missing === false) { fwrite(STDERR, 'قراءةُ الناقصِ فشلت: ' . $db->error . "\n"); exit(1); }
$rows = array();
while ($x = $missing->fetch_assoc()) { $rows[] = $x; }
echo '── ② بلا مرآةٍ: ' . count($rows) . "\n";
if (!$rows) { echo "── لا شيءَ يُسقَط — الهجرةُ عاطلة\n"; exit(0); }

/* عملةُ الأساسِ من مصدرِها الواحدِ — لا حرفٌ مغروز */
$base = $one("SELECT code FROM fin_currencies WHERE is_base = 1 LIMIT 1");
if ($base === null || $base === '') { $base = 'USD'; }
echo "── ③ عملةُ الأساسِ من fin_currencies: {$base}\n";

/** تطبيعُ عملةِ العقدِ العربيةِ إلى رمزٍ — وما لا يُعرف يأخذ الأساسَ ويُعلَن */
$cur = function ($txt) use ($base) {
    $t = trim((string) $txt);
    $map = array('دولار' => 'USD', 'جنيه' => 'SDG', 'يورو' => 'EUR',
                 'ريال' => 'SAR', 'درهم' => 'AED');
    foreach ($map as $ar => $code) {
        if ($t !== '' && mb_strpos($t, $ar) !== false) { return $code; }
    }
    if (preg_match('~^[A-Z]{3}$~', $t)) { return $t; }
    return $base;
};

$made = 0; $failed = 0;
foreach ($rows as $s) {
    $sid = (int) $s['id'];
    $st = $db->prepare("INSERT INTO supplier_contracts
        (company_id, supplier_id, client_contract_id, project_id,
         start_date, end_date, currency, state, version,
         source_table, source_id, notes, is_deleted, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 'supplierscontracts', ?, ?, 0, NOW())");
    if ($st === false) { fwrite(STDERR, 'تحضيرٌ فشل: ' . $db->error . "\n"); exit(1); }
    $co   = (int) $s['company_id'];
    $sup  = (int) $s['supplier_id'];
    $cli  = isset($s['project_contract_id']) ? (int) $s['project_contract_id'] : 0;
    $prj  = isset($s['project_id']) ? (int) $s['project_id'] : 0;
    $from = ($s['actual_start'] !== null && $s['actual_start'] !== '') ? (string) $s['actual_start'] : null;
    $to   = ($s['actual_end'] !== null && $s['actual_end'] !== '') ? (string) $s['actual_end'] : null;
    $cc   = $cur(isset($s['price_currency_contract']) ? $s['price_currency_contract'] : '');
    $state = ((string) (isset($s['status']) ? $s['status'] : '') === '1') ? 'نافذ' : 'مسودة';
    $note = 'مرحَّلٌ قراءةً من supplierscontracts#' . $sid
          . ' — أُعيد إسقاطُه بهجرةِ 2027_03_21 بعد إزالةِ المرايا الملفَّقة';
    $st->bind_param('iiiissssis', $co, $sup, $cli, $prj, $from, $to, $cc, $state, $sid, $note);
    $ok = $st->execute();
    if ($ok === false) {
        $failed++;
        if ($failed <= 3) { echo "   ✘ #{$sid}: " . substr($st->error, 0, 70) . "\n"; }
    } else { $made++; }
    $st->close();
}
echo "── ④ أُسقط {$made} · فشل {$failed}\n";

$after = $one("SELECT COUNT(*) FROM supplier_contracts WHERE source_table = 'supplierscontracts'");
$gap   = $one("SELECT COUNT(*) FROM supplierscontracts s
                WHERE NOT EXISTS (SELECT 1 FROM supplier_contracts m
                                   WHERE m.source_table = 'supplierscontracts' AND m.source_id = s.id)");
echo "── ⑤ مرايا الآن {$after} من {$heads} رأسًا · بلا مرآةٍ {$gap}\n";
if ((int) $gap !== 0 || (int) $after !== (int) $heads) {
    fwrite(STDERR, "بقي رأسٌ بلا مرآةٍ — لم يكتمل\n"); exit(1);
}
/* ولا مرآةَ مزدوجةٌ لرأسٍ واحد */
$dup = $one("SELECT COUNT(*) FROM (SELECT source_id FROM supplier_contracts
              WHERE source_table = 'supplierscontracts'
              GROUP BY source_id HAVING COUNT(*) > 1) x");
echo "── ⑥ رؤوسٌ بمرآتين: {$dup}\n";
if ((int) $dup !== 0) { fwrite(STDERR, "مرآةٌ مزدوجةٌ — لم يكتمل\n"); exit(1); }

echo "\n✅ لكلِّ رأسٍ موروثٍ مرآةٌ واحدةٌ — إسقاطًا من المصدرِ لا بيانًا مخترَعًا.\n";
exit(0);
