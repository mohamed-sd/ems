<?php
/**
 * 2027_03_26_risk_treatment_evidence_ref.php
 * ═══════════════════════════════════════════════════════════════════════════
 * مرجعُ المرفقِ ورقمُه لدليلِ إنجازِ المعالجة — ⇐ INJ-0576
 *
 * **العيب**: دليلُ التنفيذِ يُجمع بنافذةِ `prompt()` نصًّا حرًّا، والخادمُ يقبله
 * إن لم يكن فارغًا — فنقطةٌ واحدةٌ «.» تُغلق معالجةَ خطر. ولا موضعَ في الجدولِ
 * يحمل **مرفقًا** ولا **مرجعًا**، فشاشةُ التحققِ لا تجد ما تعرضه للمتحقِّق.
 *
 * ◆ لا جدولَ جديدًا: عمودان على `risk_treatments` — فالمرفقُ خاصيةُ سطرِ
 *   المعالجةِ لا كيانٌ مستقلّ، وجدولٌ جديدٌ يلزمه `company_id` وتصنيفٌ وحارس.
 * ◆ وقابلةٌ للإعادة: `information_schema` تُسأل قبل كلِّ إضافةٍ.
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

echo "══ مرجعُ مرفقِ دليلِ إنجازِ المعالجة ══\n\n";

$has = function ($table, $col) use ($conn) {
    $st = $conn->prepare('SELECT 1 FROM information_schema.COLUMNS
                           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $st->bind_param('ss', $table, $col);
    $st->execute();
    $r = (bool) $st->get_result()->fetch_row();
    $st->close();
    return $r;
};

$cols = array(
    'done_attachment' => "ADD COLUMN done_attachment VARCHAR(255) NULL COMMENT 'مسارُ مرفقِ دليلِ الإنجاز' AFTER done_evidence",
    'done_ref'        => "ADD COLUMN done_ref VARCHAR(120) NULL COMMENT 'مرجعُ الدليل: رقمُ مستندٍ أو أمرِ عمل' AFTER done_attachment",
);
$added = 0;
foreach ($cols as $col => $ddl) {
    if ($has('risk_treatments', $col)) { echo "  · {$col} موجودٌ — لا تغيير\n"; continue; }
    if ($conn->query('ALTER TABLE risk_treatments ' . $ddl)) {
        $added++;
        echo "  ✔ أُضيف {$col}\n";
    } else {
        echo "  ✘ تعذَّر {$col}: {$conn->error}\n";
        exit(1);
    }
}
echo "\n  المُضاف: {$added}\n";
exit(0);
