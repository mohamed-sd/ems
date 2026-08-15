<?php
/**
 * 2027_03_29_sensitive_field_elements.php
 * ═══════════════════════════════════════════════════════════════════════════
 * تسجيلُ الحقولِ الحسّاسةِ في قاموسِ الظهور — ⇐ INJ-0159 · INJ-0150 · INJ-0347
 *
 * `VisibilityPolicyService::decide()` **مغلقٌ افتراضًا**: «ما ليس في القاموس
 * لا يُصيَّر». وهو الافتراضُ الصحيح — يحمي ولا يكشف. لكنَّه يعني أنَّ حقلًا
 * حسّاسًا غيرَ مسجَّلٍ **محجوبٌ عن الجميعِ بلا استثناء**، فلا يستطيع مالكُ
 * النطاقِ فتحَه لمن يستحقُّه.
 *
 * فهذه الهجرةُ تُسجّل العناصرَ الثلاثةَ في `portal_elements` — **ولا تمنح
 * أحدًا**. المنحُ صفٌّ في `visibility_keys` وهو **قرارُ مالكِ نطاقٍ** لا قرارُ
 * هجرة. فالتسجيلُ يجعل المنحَ **ممكنًا**، والحجبُ يبقى قائمًا حتى يُمنَح.
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

echo "══ تسجيلُ الحقولِ الحسّاسةِ في قاموسِ الظهور ══\n\n";

/* أعمدةُ القاموسِ تُسأل — لا تُفترض */
$cols = array();
$r = $conn->query('SHOW COLUMNS FROM portal_elements');
if (!$r) { exit("جدولُ `portal_elements` غيرُ موجود: {$conn->error}\n"); }
while ($x = $r->fetch_assoc()) { $cols[$x['Field']] = true; }

$elements = array(
    'supplier.bank_account' => 'رقمُ حسابِ المورّدِ وIBAN — يُمكّن من التحويلِ المالي',
    'contract.margin'       => 'سعرُ العقدِ وهامشُ ربحِه',
    'po.sensitive_cols'     => 'أعمدةُ أمرِ الشراءِ الحسّاسةُ (السعرُ والتكلفة)',
);

$added = 0; $seen = 0;
foreach ($elements as $code => $label) {
    $st = $conn->prepare('SELECT 1 FROM portal_elements WHERE element_code = ? LIMIT 1');
    $st->bind_param('s', $code);
    $st->execute();
    $exists = (bool) $st->get_result()->fetch_row();
    $st->close();
    if ($exists) { $seen++; echo "  · {$code} مسجَّلٌ — لا تغيير\n"; continue; }

    /* الإدراجُ بالأعمدةِ الموجودةِ وحدَها — فجدولٌ بأعمدةٍ مختلفةٍ لا يُسقط الهجرة */
    $fields = array('element_code'); $marks = array('?'); $vals = array($code); $types = 's';
    if (isset($cols['label_ar']))    { $fields[] = 'label_ar';    $marks[] = '?'; $vals[] = $label; $types .= 's'; }
    elseif (isset($cols['label']))   { $fields[] = 'label';       $marks[] = '?'; $vals[] = $label; $types .= 's'; }
    if (isset($cols['sensitivity'])) { $fields[] = 'sensitivity'; $marks[] = '?'; $vals[] = 'high'; $types .= 's'; }
    if (isset($cols['active']))      { $fields[] = 'active';      $marks[] = '?'; $vals[] = 1;      $types .= 'i'; }

    $sql = 'INSERT INTO portal_elements (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $marks) . ')';
    $st = $conn->prepare($sql);
    if (!$st) { echo "  ✘ {$code}: {$conn->error}\n"; exit(1); }
    $st->bind_param($types, ...$vals);
    if ($st->execute()) { $added++; echo "  ✔ سُجّل {$code}\n"; }
    else { echo "  ✘ {$code}: {$st->error}\n"; $st->close(); exit(1); }
    $st->close();
}
echo "\n  المُسجَّل: {$added} · القائمُ سلفًا: {$seen}\n";
echo "  ◆ ولا منحَ هنا — المنحُ صفٌّ في `visibility_keys` وهو قرارُ مالكِ نطاق.\n";
exit(0);
