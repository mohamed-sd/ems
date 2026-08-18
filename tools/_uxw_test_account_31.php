<?php
/**
 * tools/_uxw_test_account_31.php — حسابُ اختبارِ رئيسِ الحساباتِ (قرارُ المالكِ ⑤ 2026-08-18)
 * الشروطُ الأربعة: جديدٌ لا تعديلَ لقائمٍ · موسومٌ اختبارًا على بياناتِ تدريبٍ (co4) ·
 * بلا اعتمادٍ ماليٍّ نافذٍ على معاملةٍ حقيقية (عزلُ الكيانِ التجريبيِّ يضمنه بنيويًّا) ·
 * ويُعطَّل آليًّا بعدَ التحقق (--disable).
 *   php tools/_uxw_test_account_31.php --create   # الإنشاء
 *   php tools/_uxw_test_account_31.php --disable  # التعطيلُ بعدَ اكتمالِ الـ16
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$U = 'اختبار-رئيس-الحسابات';

if (in_array('--create', $argv, true)) {
    $ex = $conn->query("SELECT id, status FROM users WHERE username='{$U}'")->fetch_assoc();
    if ($ex) {
        $conn->query("UPDATE users SET status=1 WHERE id=" . (int) $ex['id']);
        echo "✔ قائمٌ سلفًا (#{$ex['id']}) — فُعِّل للتحقق\n";
        exit(0);
    }
    $hash = password_hash('12345678', PASSWORD_DEFAULT);
    $st = $conn->prepare(
        "INSERT INTO users (username, password, role, company_id, status, email)
         VALUES (?, ?, 31, 4, 1, 'test.chief.acc@equipation.sd')");
    $st->bind_param('ss', $U, $hash);
    if (!$st->execute()) { fwrite(STDERR, "✗ {$st->error}\n"); exit(1); }
    $id = (int) $conn->insert_id;
    echo "✔ أُنشئ حسابُ الاختبارِ #{$id}: «{$U}» / 12345678 · الدورُ 31 · الكيانُ التجريبيُّ co4\n";
    echo "◆ موسومٌ بالاسمِ اختبارًا · وعزلُ company_id=4 يمنعه بنيويًّا عن أيِّ معاملةٍ حقيقية\n";
    exit(0);
}
if (in_array('--disable', $argv, true)) {
    $conn->query("UPDATE users SET status=0 WHERE username='{$U}'");
    echo $conn->affected_rows > 0 ? "✔ عُطِّل حسابُ الاختبارِ بعدَ اكتمالِ التحقق\n" : "· كان معطَّلًا سلفًا\n";
    exit(0);
}
fwrite(STDERR, "الاستعمال: --create | --disable\n");
exit(2);
