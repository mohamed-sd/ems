<?php
/**
 * 2027_04_05_sensitive_money_elements.php
 * ═══════════════════════════════════════════════════════════════════════════
 * ثلاثةُ حقولٍ ماليةٍ تدخل قاموسَ الظهور — ⇐ INJ-0104 · INJ-0225 · INJ-0368
 *
 * ثلاثةُ بنودٍ تقول الجملةَ نفسَها: «حسابٌ بلا منحةٍ **لا يتلقّى القيمةَ في جسمِ
 * الاستجابةِ أصلًا**، ومن يملكها يراها **ويُسجَّل اطّلاعُه**».
 *
 * والفرقُ بين «مخفيٍّ» و«غيرِ موجود» هو البند كلُّه: قيمةٌ مخفيةٌ بـCSS تُقرأ
 * بـ«عرضِ المصدر»، فالحجبُ يقع في الخادمِ لا في المتصفّح.
 *
 * ◆ **ولا منحَ هنا**: التسجيلُ يُنشئ العنصرَ فقط. والمنحُ صفٌّ في
 *   `visibility_keys` وهو قرارُ مالكِ نطاقٍ لا قرارُ هجرة — والحاكمُ
 *   `VisibilityPolicyService` **مغلقٌ افتراضًا**، فالتسجيلُ وحدَه يحجب.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_USER') : ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_PASS') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

echo "══ حقولٌ ماليةٌ في قاموسِ الظهور ══\n\n";

$cols = array();
$r = $conn->query('SHOW COLUMNS FROM portal_elements');
while ($r && ($x = $r->fetch_row())) { $cols[$x[0]] = true; }

$ELEMENTS = array(
    'payroll.amount'   => 'مبلغُ سطرِ مسيّرِ الرواتب',
    'issue.cost'       => 'قيمةُ تكلفةِ الصرفِ من المخزن',
    'timesheet.rates'  => 'معدّلاتُ التايم شيت وقيمُها',
);
$added = 0; $exists = 0;
foreach ($ELEMENTS as $code => $label) {
    $st = $conn->prepare('SELECT 1 FROM portal_elements WHERE element_code = ? LIMIT 1');
    if (!$st) { continue; }
    $st->bind_param('s', $code);
    $st->execute();
    $has = (bool) $st->get_result()->fetch_row();
    $st->close();
    if ($has) { $exists++; echo "  · {$code} مسجَّلٌ — لا تغيير\n"; continue; }

    $fields = array('element_code'); $vals = array($code); $types = 's';
    foreach (array('label_ar' => $label, 'element_type' => 'field', 'default_mode' => 'closed',
                   'is_sensitive' => 1, 'created_at' => date('Y-m-d H:i:s')) as $f => $v) {
        if (isset($cols[$f])) { $fields[] = $f; $vals[] = $v; $types .= is_int($v) ? 'i' : 's'; }
    }
    $sql = 'INSERT INTO portal_elements (' . implode(', ', $fields) . ') VALUES ('
         . implode(', ', array_fill(0, count($fields), '?')) . ')';
    $st2 = $conn->prepare($sql);
    if (!$st2) { echo "  ⚠ تعذّر تحضيرُ الإدراج لـ{$code}: {$conn->error}\n"; continue; }
    $st2->bind_param($types, ...$vals);
    if ($st2->execute() && $conn->affected_rows > 0) { $added++; echo "  ✔ {$code} — {$label}\n"; }
    else { echo "  ⚠ لم يُدرَج {$code}: {$conn->error}\n"; }
    $st2->close();
}
echo "\n  المُسجَّل: {$added} · القائمُ سلفًا: {$exists}\n";
echo "  ◆ والحاكمُ مغلقٌ افتراضًا — فالتسجيلُ وحدَه يحجب، والمنحُ قرارُ مالكِ نطاق.\n";
