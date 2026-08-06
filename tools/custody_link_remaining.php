<?php
/**
 * tools/custody_link_remaining.php — ربط حاملي العهدة الباقين (م-ج · SRC-13)
 * ───────────────────────────────────────────────────────────────────────────
 * كان «العهدة نص حر بلا حساب — الربط أولًا وإلا فتلفيق». الدفعة السابقة ربطت
 * 21/22 وبقي صفان (داتا تجريبية — ق-15): يُربطان بموظفيهما الحقيقيين
 * (holder_id → employees.id بعرف الصفوف المربوطة) موسومَين، فتكتمل تغطية
 * SRC-13 (العهدة المصروفة غير المسواة تولد مهمة تسوية لحاملها — النبضة ⑦).
 * idempotent. التشغيل: php tools/custody_link_remaining.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
define('EMS_CLI', true);
ob_start();
require_once __DIR__ . '/../config.php';
ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

/* الصفان المقيسان: #24 «آدم» ← الموظف آدم عمر إبراهيم (54) · #17 بلا حامل
   إطلاقًا ← أمين المستودع (868) حامل العهدة الافتراضي بعرف الصف #6 */
$plan = array(
    24 => array(54, 'آدم عمر إبراهيم — تحقق التكلفة الآلية [تجريبي — ق-15]'),
    17 => array(868, 'أمين المستودع — عهدة بلا حامل رُبطت افتراضًا [تجريبي — ق-15]'),
);
foreach ($plan as $cid => $p) {
    $row = $conn->query("SELECT holder_id FROM proc_custody WHERE id = " . intval($cid))->fetch_assoc();
    if (!$row) { fwrite(STDOUT, "⚠ #{$cid} غائب\n"); continue; }
    if (intval($row['holder_id']) > 0) { fwrite(STDOUT, "= #{$cid} مربوط سلفًا\n"); continue; }
    $emp = $conn->query("SELECT id, name FROM employees WHERE id = " . intval($p[0]))->fetch_assoc();
    if (!$emp) { fwrite(STDOUT, "⚠ الموظف {$p[0]} غائب — لا تلفيق\n"); continue; }
    $st = $conn->prepare("UPDATE proc_custody SET holder_id = ?, holder_name = ? WHERE id = ?");
    $eid = intval($p[0]);
    $st->bind_param('isi', $eid, $p[1], $cid);
    $st->execute();
    $st->close();
    fwrite(STDOUT, "✔ #{$cid} ← {$emp['name']} (#{$eid})\n");
}
$left = intval($conn->query("SELECT COUNT(*) FROM proc_custody WHERE COALESCE(holder_id,0)=0")->fetch_row()[0]);
fwrite(STDOUT, "بلا ربط بعد التشغيل: {$left}\n");
exit($left === 0 ? 0 : 1);
