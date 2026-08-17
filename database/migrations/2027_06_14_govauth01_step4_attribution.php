<?php
/**
 * 2027_06_14_govauth01_step4_attribution.php
 * ═══════════════════════════════════════════════════════════════════════════
 * GOV-AUTH-01 §8-3 ④ — أعمدةُ النسبةِ الثلاثةُ في دفترِ الأفعالِ (activity_logs
 * — يكتبه includes/audit_trail.php) وقيدُ chk_act_attribution:
 *   «كلُّ فعلٍ يُكتب بالفاعلِ الحقيقيِّ ومعه من نُفِّذ عنه ومرجعُ جلسةِ النيابة —
 *    ولا فعلَ مجهولَ النسبة» (A5).
 * إضافةٌ خالصة: الأعمدةُ NULL لما ليس في جلسةِ نيابةٍ — والقيدُ يرفض فعلَ
 * جلسةٍ ناقصَ النسبة. لا تبديلَ ولا مساسَ بما يُكتب اليوم.
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
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$one = function (string $s) use ($conn) { $r = $conn->query($s); return $r ? ($r->fetch_row()[0] ?? null) : null; };

echo "\n▐ أعمدةُ النسبةِ الثلاثة\n";
$cols = array(
    'acted_by'         => "BIGINT UNSIGNED NULL COMMENT 'الفاعلُ الحقيقيُّ في جلسةِ النيابة (A5)'",
    'acted_for'        => "BIGINT UNSIGNED NULL COMMENT 'من نُفِّذ عنه'",
    'impersonation_id' => "INT UNSIGNED NULL COMMENT 'مرجعُ جلسةِ impersonation_sessions'",
);
foreach ($cols as $c => $def) {
    $exists = $one("SELECT COUNT(*) FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activity_logs' AND COLUMN_NAME = '{$c}'");
    if ((int) $exists === 0) {
        echo $conn->query("ALTER TABLE `activity_logs` ADD COLUMN `{$c}` {$def}") === false
            ? "   ✗ {$c}: {$conn->error}\n" : "   ✔ {$c}\n";
    } else { echo "   · {$c} قائمٌ\n"; }
}
$conn->query("ALTER TABLE `activity_logs` ADD KEY `ix_impersonation` (`impersonation_id`)");

echo "\n▐ القيدُ chk_act_attribution\n";
$conn->query("ALTER TABLE `activity_logs` DROP CONSTRAINT `chk_act_attribution`");
echo $conn->query("ALTER TABLE `activity_logs` ADD CONSTRAINT `chk_act_attribution`
        CHECK (`impersonation_id` IS NULL OR (`acted_by` IS NOT NULL AND `acted_for` IS NOT NULL))") === false
    ? "   ✗ {$conn->error}\n" : "   ✔ لا فعلَ في جلسةِ نيابةٍ بلا نسبةٍ مزدوجة\n";

$neg = $conn->query("INSERT INTO activity_logs (user_id, action_type, impersonation_id)
                     VALUES (1, 'neg_test', 999)");
if ($neg === false) {
    echo "   ✔ السلبيّ: فعلُ جلسةٍ بلا acted_by/acted_for رُفض ({$conn->errno})\n";
} else {
    $conn->query("DELETE FROM activity_logs WHERE action_type='neg_test'");
    echo "   ✗ السلبيّ: مرَّ ولم يُرفَض!\n";
}
printf("   · صفوفُ الدفترِ الحالية: %s — كلُّها خارجَ جلساتِ نيابةٍ فالأعمدةُ NULL بحق\n",
    $one("SELECT COUNT(*) FROM activity_logs"));
echo "\n✔ الخطوةُ الرابعةُ — والكتابةُ الفعليةُ للنسبةِ تبدأ مع تفعيلِ جلساتِ النيابةِ (التبديل)\n";
