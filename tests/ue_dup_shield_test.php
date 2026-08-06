<?php
/**
 * tests/ue_dup_shield_test.php — رباعية درع التكرار البنيوي (ق-18)
 * سماح: صف جديد على خانة حرة بعد العتبة · منع: تكرار الخانة نفسها من القاعدة ·
 * موروث: ما قبل العتبة لا يمسه الدرع · نقل: UPDATE إلى خانة مشغولة يُرفض.
 * التشغيل: php tests/ue_dup_shield_test.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
define('EMS_CLI', true);
ob_start();
require_once __DIR__ . '/../config.php';
ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$pass = 0; $fail = 0;
function ok($c, $l) { global $pass, $fail; $c ? $pass++ : $fail++; fwrite(STDOUT, ($c ? '  ✔ ' : '  ✘ ') . $l . "\n"); }

/* خانة اختبار حرة: معدة حقيقية + تاريخ مستقبلي بعيد لا يصادم شيئًا */
$eq = intval($conn->query("SELECT id FROM equipments ORDER BY id LIMIT 1")->fetch_row()[0]);
$d1 = '2027-11-11';
$conn->query("DELETE FROM unit_entries WHERE entry_no LIKE 'UEDUP-%'"); // تنظيف آثار اختبار سابقة فقط

$mk = function ($no, $date, $shift) use ($conn, $eq) {
    $st = $conn->prepare("INSERT INTO unit_entries (company_id, entry_no, equipment_id, entry_date, shift, unit_type, qty, state, entered_by)
                          VALUES (4, ?, ?, ?, ?, 'hour', 1, 'draft', 0)");
    $st->bind_param('siss', $no, $eq, $date, $shift);
    $ok = $st->execute();
    $err = $st->error;
    $st->close();
    return array($ok, $err);
};

/* ① سماح: خانة حرة بعد العتبة */
list($ok1, ) = $mk('UEDUP-A', $d1, 'day');
ok($ok1, 'سماح: صف جديد على خانة حرة بعد العتبة');

/* ② منع: الخانة نفسها — الرفض من القاعدة باسم ق-18 */
list($ok2, $err2) = $mk('UEDUP-B', $d1, 'day');
ok(!$ok2 && mb_strpos($err2, 'ق-18') !== false, 'منع: التكرار مرفوض من القاعدة (' . mb_substr($err2, 0, 40) . '…)');

/* ③ وردية أخرى في اليوم نفسه تمر */
list($ok3, ) = $mk('UEDUP-C', $d1, 'night');
ok($ok3, 'سماح: وردية مختلفة لليوم نفسه');

/* ④ الموروث قبل العتبة لا يمسه الدرع (خانة مكررة عمدًا قبل 2026-08-05) */
list($ok4a, ) = $mk('UEDUP-L1', '2026-01-15', 'day');
list($ok4b, ) = $mk('UEDUP-L2', '2026-01-15', 'day');
ok($ok4a && $ok4b, 'موروث: ما قبل العتبة خارج الدرع (معلَن لا مكسور)');

/* ⑤ نقل: UPDATE يزحف لخانة مشغولة يُرفض */
$id = intval($conn->query("SELECT id FROM unit_entries WHERE entry_no = 'UEDUP-C'")->fetch_row()[0]);
$r5 = $conn->query("UPDATE unit_entries SET shift = 'day' WHERE id = {$id}");
ok(!$r5 && mb_strpos($conn->error, 'ق-18') !== false, 'نقل: التحويل لخانة مشغولة مرفوض');

/* تنظيف صفوف الاختبار وحدها */
$conn->query("DELETE FROM unit_entries WHERE entry_no LIKE 'UEDUP-%'");
fwrite(STDOUT, "\n══ النتيجة: {$pass} ناجحة · {$fail} فاشلة ══\n");
exit($fail ? 1 : 0);
