<?php
/**
 * tests/injfix02_sensitive_target_integrity_proof.php
 *   INJ-FIX-02 · NF-09 و NF-19 — توسيعُ GAP-12 و GAP-09
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **سقّاطةٌ لا تقريرٌ**: تُرسِّب إذا ظهرت **سياسةٌ جديدةٌ** تُعلن حمايةً لعمودٍ
 *   لا وجودَ له خارجَ الدَّينِ المُعلَن. فالسياسةُ بهدفٍ وهميٍّ **تبدو حمايةً ولا
 *   تحمي** — وهي أخطرُ من غيابِ السياسةِ لأنها تُسكِت السؤال.
 *
 * ◆ **والحزامُ السلبيُّ أولًا**: تُجرَّب السقّاطةُ على هدفَين مُصطنَعَين — واحدٌ
 *   حقيقيٌّ يجب أن يمرَّ وواحدٌ وهميٌّ يجب أن يُمسَك. فبوابةٌ لا تعرف كيف ترسُب
 *   لا يعني مرورُها شيئًا.
 *
 * ◆ **وخبرٌ لا حكم**: انكشافُ `employees.monthly_salary` يُطبع ولا يُرسِّب —
 *   لأن سترَه **تغييرُ وصولٍ حيٍّ** لا يملكه فاحص.
 *
 * التشغيل: php tests/injfix02_sensitive_target_integrity_proof.php
 * الخروج : 0 نجاح · 1 رسوب
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$h = ems_env('DB_HOST'); $prt = 3306;
if (strpos($h, ':') !== false) { list($h, $prt) = explode(':', $h); $prt = (int) $prt; }
$conn = new mysqli($h, ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER'),
    ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS'),
    ems_env('DB_NAME'), $prt);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$ok = 0; $bad = 0;
function chk($cond, $msg)
{
    global $ok, $bad;
    if ($cond) { $ok++; echo "  ✔ {$msg}\n"; } else { $bad++; echo "  ✘ {$msg}\n"; }
}
function col_exists($conn, $t, $f)
{
    $r = $conn->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA=DATABASE()
                          AND TABLE_NAME='" . $conn->real_escape_string($t) . "'
                          AND COLUMN_NAME='" . $conn->real_escape_string($f) . "'");
    return $r && (int) $r->fetch_row()[0] > 0;
}
/** الحكمُ الذي تقوم عليه السقّاطة — يُنادى في الفحصِ وفي حزامِه السلبيِّ سواءً */
function target_resolves($conn, $code)
{
    if (strpos($code, '.') === false) { return false; }
    list($t, $f) = explode('.', $code, 2);
    return col_exists($conn, $t, $f);
}

echo "══ ① الحزامُ السلبيُّ — أتعرف السقّاطةُ كيف ترسُب؟ ══\n";
chk(target_resolves($conn, 'employees.phone'),
    'هدفٌ حقيقيٌّ (`employees.phone`) **يمرّ**');
chk(!target_resolves($conn, 'employees.zzz_no_such_column'),
    'هدفٌ وهميٌّ (`employees.zzz_no_such_column`) **يُمسَك**');
chk(!target_resolves($conn, 'no_such_table.anything'),
    'جدولٌ لا وجودَ له **يُمسَك**');
chk(!target_resolves($conn, 'bare_code_without_dot'),
    'رمزٌ بلا نقطةٍ **يُمسَك**');

echo "\n══ ② NF-19 · صفرُ مستهلكٍ مكرَّرٍ داخلَ الخانة ══\n";
$dup = 0;
$q = $conn->query("SELECT `consumers` FROM `gov_screen_cycle` WHERE COALESCE(`consumers`,'') <> ''");
while ($q && $x = $q->fetch_row()) {
    $parts = preg_split('/\s*·\s*/u', trim((string) $x[0]), -1, PREG_SPLIT_NO_EMPTY);
    $norm = array_map(function ($s) { return preg_replace('/\s+/u', ' ', trim($s)); }, $parts);
    if (count($norm) !== count(array_unique($norm))) { $dup++; }
}
chk($dup === 0, "صفوفٌ بمستهلكٍ مكرَّر: {$dup} (كانت ٥٥)");

echo "\n══ ③ GAP-09 · صفرُ صفِّ تصنيفٍ ملوَّثٍ نشط ══\n";
$q = $conn->query("SELECT COUNT(*) FROM `gov_data_classes`
                    WHERE `active` = 1 AND `code` NOT REGEXP '^DC-[0-9]+$'");
$polluted = $q ? (int) $q->fetch_row()[0] : -1;
chk($polluted === 0, "صفوفُ تصنيفٍ نشطةٌ برمزٍ خارجَ نمطِ DC-n: {$polluted}");

echo "\n══ ④ NF-09 · السقّاطة — لا سياسةَ جديدةً بهدفٍ وهميّ ══\n";
$declared = array();
$r = $conn->query("SELECT COUNT(*) FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='gov_sensitive_policy_debt'");
if (!$r || (int) $r->fetch_row()[0] === 0) {
    chk(false, 'سجلُّ الدَّينِ `gov_sensitive_policy_debt` غيرُ موجود — تُشغَّل الهجرة 2027_08_31');
} else {
    $q = $conn->query("SELECT `source_register`, `declared_target` FROM `gov_sensitive_policy_debt`");
    while ($q && $x = $q->fetch_assoc()) { $declared[$x['source_register'] . '|' . $x['declared_target']] = 1; }

    $undeclared = array();
    $q = $conn->query("SELECT `field_code` FROM `sensitive_field_policies`");
    while ($q && $x = $q->fetch_row()) {
        $c = (string) $x[0];
        if (target_resolves($conn, $c)) { continue; }
        if (!isset($declared['sensitive_field_policies|' . $c])) { $undeclared[] = "sensitive_field_policies:{$c}"; }
    }
    $q = $conn->query("SELECT `table_name`, `field_name` FROM `scr_sensitive_fields`");
    while ($q && $x = $q->fetch_assoc()) {
        $c = $x['table_name'] . '.' . $x['field_name'];
        if (target_resolves($conn, $c)) { continue; }
        if (!isset($declared['scr_sensitive_fields|' . $c])) { $undeclared[] = "scr_sensitive_fields:{$c}"; }
    }
    chk(count($undeclared) === 0,
        '**صفرُ سياسةٍ بهدفٍ وهميٍّ غيرِ مُعلَن** — ' . count($undeclared) . ' جديدة'
        . (count($undeclared) ? ' — ' . implode(' · ', $undeclared) : ''));

    /* ولا يبقى الدَّينُ متقادمًا: كلُّ مُعلَنٍ ما يزال وهميًّا فعلًا */
    $stale = array();
    foreach (array_keys($declared) as $k) {
        list(, $code) = explode('|', $k, 2);
        if (target_resolves($conn, $code)) { $stale[] = $code; }
    }
    chk(count($stale) === 0,
        'لا دَينَ متقادمًا — كلُّ مُعلَنٍ ما يزال بلا هدفٍ حقيقيٍّ (' . count($stale) . ')'
        . (count($stale) ? ' — يُشطب: ' . implode(' · ', $stale) : ''));
    echo "  · الدَّينُ المُعلَنُ الآن: " . count($declared) . " سياسةً\n";
}

echo "\n══ ⑤ خبرٌ للمالكِ — لا حكمَ فيه ══\n";
$q = $conn->query("SELECT `declared_target`, `real_column`, `real_column_protected`
                     FROM `gov_sensitive_policy_debt` WHERE `real_column` IS NOT NULL");
$exposed = 0;
while ($q && $x = $q->fetch_assoc()) {
    if ((int) $x['real_column_protected'] === 0) {
        $exposed++;
        echo "  ◆ `{$x['declared_target']}` تحمي عمودًا وهميًّا — والحقيقيُّ "
           . "`{$x['real_column']}` **مكشوفٌ بلا سترٍ في أيِّ سجل**\n";
    }
}
if ($exposed === 0) { echo "  · لا عمودَ حقيقيًّا مكشوفًا في الدَّينِ المُعلَن\n"; }
echo "  ◆ وسترُه **تغييرُ وصولٍ حيّ** — يمرُّ ببروتوكولِ القلبِ بقرارِ مالك، ولا يقرّره فاحص.\n";

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
