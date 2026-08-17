<?php
/**
 * tools/uxui_pollution_isolate.php — عزلُ الصفوفِ المؤكَّدةِ **بتسجيلٍ لا بتعديل**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ قرارُ المالك (2026-08-19 · سادسًا) — خمسُ خطواتٍ لكلِّ قيمة:
 *   ① أثبتْ أنها في غيرِ موضعِها ② حدّدِ القيمةَ الصحيحةَ من مصدرٍ حاكم
 *   ③ **فإن لم توجد يقينًا فلا تخمّن ولا تكتب NULL تلقائيًّا** — اعزلِ السجلَّ
 *      أو امنعْ أثرَه **وفقَ قيدِ الحقل** ④ احفظِ النصَّ الأصليَّ ومرجعَه
 *   ⑤ ثم قيدٌ يمنع عودتَه.
 *
 * ◆ والحالُ المقيسُ يمنع القفزَ إلى ⑤: الأعمدةُ المصابةُ **NOT NULL** فلا يُكتب
 *   فيها NULL، ولا مصدرَ حاكمًا يقول ما القيمةُ الصحيحةُ لـ`entity_type` من
 *   نصِّ ملاحظةٍ لا يدلُّ عليها. وجُرِّب القيدُ المانعُ فعلًا فرُفض — **لأن
 *   الصفوفَ الملوَّثةَ قائمة**، وهو تأكيدٌ إضافيٌّ لا عائق.
 *
 * ◆ فهذه الأداةُ تُنجز ① و④: **تُعدِّد الصفوفَ المصابةَ بمفاتيحِها ونصِّها
 *   الأصليّ** في سجلِّ عزلٍ — **بصفرِ تعديلٍ في بياناتِ العمل**. فيصير العملُ
 *   المتبقّي مقيسًا ومحصورًا بدل أن يكون تقديرًا.
 *
 * التشغيل:
 *   php tools/uxui_pollution_isolate.php            جردٌ بلا كتابة
 *   php tools/uxui_pollution_isolate.php --apply    يسجّل الصفوفَ في سجلِّ العزل
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال\n"); }
$conn->set_charset('utf8mb4');
$APPLY = in_array('--apply', $argv, true);

/* الجدولُ يُنشأ بهجرة 2027_07_13 — والأداةُ تملأ لا تُنشئ (ems_app بلا DDL). */

/**
 * مجالُ السياسةِ للجدول — يُشتقُّ من اسمِه لا من إقرارِ الكاتب.
 * وقاعدةُ المالك: «لا تُطبَّق بلا اعتراضٍ أبدًا في: الصلاحياتِ · سلاليمِ
 * الاعتمادِ · السقوفِ الماليةِ · فصلِ الواجباتِ · الالتزاماتِ القانونيةِ ·
 * القراراتِ المالية». فما وقع فيها يُسجَّل ولا يُمَسُّ بلا قرارٍ صريح.
 */
function ems_domain_of($tbl)
{
    $t = strtolower($tbl);
    $map = array(
        'LEGAL_OBLIGATIONS'   => array('obl_', 'obligation', 'compliance', 'legal', 'permit'),
        'APPROVAL_LADDERS'    => array('approval', 'ladder', 'chain'),
        'PERMISSIONS'         => array('permission', 'role_', 'auth_', 'grant'),
        'FINANCIAL_CAPS'      => array('cap', 'limit', 'authority'),
        'SEGREGATION_OF_DUTIES' => array('sod', 'segregation'),
        'FINANCIAL_DECISIONS' => array('journal', 'payment', 'settlement', 'invoice',
                                       'treasury', 'budget', 'fin_'),
    );
    foreach ($map as $domain => $needles) {
        foreach ($needles as $n) {
            if (strpos($t, $n) !== false) { return $domain; }
        }
    }
    return 'OPERATIONAL_DATA';
}

/* المؤكَّدُ وحدَه */
$targets = array();
$r = $conn->query("SELECT table_name, column_name FROM gov_pollution_findings WHERE verdict = 'TEST_POLLUTION'");
while ($r && ($x = $r->fetch_assoc())) { $targets[] = $x; }

$totalRows = 0; $registered = 0; $noPk = array(); $byTable = array();
$ins = $APPLY ? $conn->prepare("INSERT IGNORE INTO gov_test_data_isolation
        (table_name, pk_column, pk_value, column_name, original_value, source_ref, reason, column_nullable, policy_domain)
        VALUES (?,?,?,?,?,?,?,?,?)") : null;

foreach ($targets as $t) {
    $tbl = $t['table_name']; $col = $t['column_name'];
    /* المفتاحُ الأساسيُّ — ولا يُعزل صفٌّ لا يُعرَّف */
    $pk = null;
    $q = $conn->query("SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
                        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . $conn->real_escape_string($tbl) . "'
                          AND CONSTRAINT_NAME='PRIMARY' ORDER BY ORDINAL_POSITION LIMIT 1");
    if ($q && ($x = $q->fetch_assoc())) { $pk = $x['COLUMN_NAME']; }
    if (!$pk) { $noPk[] = "{$tbl}.{$col}"; continue; }

    $nl = 'NO';
    $q = $conn->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . $conn->real_escape_string($tbl) . "'
                          AND COLUMN_NAME='" . $conn->real_escape_string($col) . "'");
    if ($q && ($x = $q->fetch_assoc())) { $nl = $x['IS_NULLABLE']; }
    $nullable = ($nl === 'YES') ? 1 : 0;

    /* الصفوفُ التي فيها جملةٌ بشريةٌ في خانةِ رمز */
    $sql = "SELECT `{$pk}` pk, `{$col}` v FROM `{$tbl}`
             WHERE `{$col}` LIKE '% %' AND `{$col}` REGEXP '[\\u0600-\\u06FF]'";
    $rows = @$conn->query($sql);
    if (!$rows) {
        $sql = "SELECT `{$pk}` pk, `{$col}` v FROM `{$tbl}` WHERE `{$col}` LIKE '% %'";
        $rows = @$conn->query($sql);
        if (!$rows) { continue; }
    }
    $n = 0;
    while ($x = $rows->fetch_assoc()) {
        $val = (string) $x['v'];
        if (!preg_match('~[\x{0600}-\x{06FF}]~u', $val)) { continue; }
        $n++; $totalRows++;
        if ($APPLY) {
            $reason = 'جملةٌ بشريةٌ في خانةِ رمزٍ/تعداد — مؤكَّدةٌ في جردِ التلوث';
            /* مرجعُ النصِّ يُقرأ من النصِّ نفسِه — لا يُخترع */
            $sref = preg_match('~UAT-\d{4}(-\d+)?~u', $val, $m) ? $m[0] : 'UNMARKED';
            $dom  = ems_domain_of($tbl);
            $pkv  = (string) $x['pk'];
            $ins->bind_param('sssssssis', $tbl, $pk, $pkv, $col, $val, $sref, $reason, $nullable, $dom);
            if ($ins->execute()) { $registered++; }
        }
    }
    if ($n > 0) { $byTable[$tbl] = ($byTable[$tbl] ?? 0) + $n; }
}

echo "════ عزلُ الصفوفِ المؤكَّدةِ — بتسجيلٍ لا بتعديل ════\n";
echo "  مواضعُ مؤكَّدة: " . count($targets) . " · صفوفٌ مصابةٌ مُعدَّدة: {$totalRows} · في " . count($byTable) . " جدولًا\n";
if ($noPk) { echo "  بلا مفتاحٍ أساسيٍّ (لا يُعزل صفٌّ لا يُعرَّف): " . count($noPk) . "\n"; }
arsort($byTable);
$i = 0;
foreach ($byTable as $t => $n) { printf("    · %-34s %d صفًّا\n", $t, $n); if (++$i >= 10) { echo "    …\n"; break; } }

if ($APPLY) {
    echo "\n  ▸ سُجِّل في سجلِّ العزل: {$registered} صفًّا — **وصفرُ تعديلٍ في بياناتِ العمل**\n";
    $pend = (int) $conn->query("SELECT COUNT(*) c FROM gov_test_data_isolation WHERE resolution='PENDING_SOURCE'")->fetch_assoc()['c'];
    $nn = (int) $conn->query("SELECT COUNT(*) c FROM gov_test_data_isolation WHERE column_nullable=0")->fetch_assoc()['c'];
    echo "  بانتظارِ مصدرٍ حاكم: {$pend} · منها في أعمدةٍ **NOT NULL** (لا يُكتب فيها NULL): {$nn}\n";
} else {
    echo "\n  ▸ جردٌ بلا كتابة — أضِفْ --apply\n";
}
