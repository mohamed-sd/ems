<?php
/**
 * 2027_03_28_financing_authority_ref.php
 * ═══════════════════════════════════════════════════════════════════════════
 * مرجعُ تفويضِ معتمِدِ عمليةِ التمويل — ⇐ INJ-0053
 *
 * **نصُّ القبول**: «إنشاءُ عمليةٍ فوق سقف الدور لا يجعلها نافذةً بل معلَّقةً
 * بتصعيدٍ لمن يعلوه؛ و**كلُّ عمليةٍ نافذةٍ تحمل مرجعَ تفويضِ معتمِدها**».
 *
 * والمقيسُ قبلَه: `FinancingService::createOperation` تُنشئ كلَّ عمليةٍ بحالةِ
 * `'active'` مهما بلغ رأسُ المال — ولا موضعَ في الجدولِ يحمل مرجعَ التفويض.
 *
 * ◆ عمودٌ واحدٌ لا جدولٌ جديد: المرجعُ خاصيةُ العمليةِ لا كيانٌ مستقلّ.
 * ◆ وقابلةٌ للإعادة: `information_schema` تُسأل قبل الإضافة.
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

echo "══ مرجعُ تفويضِ عملياتِ التمويل ══\n\n";

$has = function ($table, $col) use ($conn) {
    $st = $conn->prepare('SELECT 1 FROM information_schema.COLUMNS
                           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $st->bind_param('ss', $table, $col);
    $st->execute();
    $r = (bool) $st->get_result()->fetch_row();
    $st->close();
    return $r;
};

$added = 0;
$cols = array(
    'authority_ref' => "ADD COLUMN authority_ref VARCHAR(64) NULL
                        COMMENT 'مرجعُ تفويضِ من اعتمد — signing_authorities.auth_id' AFTER created_by",
    'escalated_to'  => "ADD COLUMN escalated_to VARCHAR(64) NULL
                        COMMENT 'مرجعُ صفِّ التصعيدِ في exec_approvals عند تجاوزِ السقف' AFTER authority_ref",
);
foreach ($cols as $col => $ddl) {
    if ($has('financing_operations', $col)) { echo "  · {$col} موجودٌ — لا تغيير\n"; continue; }
    if ($conn->query('ALTER TABLE financing_operations ' . $ddl)) {
        $added++;
        echo "  ✔ أُضيف {$col}\n";
    } else {
        echo "  ✘ تعذَّر {$col}: {$conn->error}\n";
        exit(1);
    }
}
echo "\n  المُضاف: {$added}\n";
exit(0);
