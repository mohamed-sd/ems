<?php
/**
 * 2027_04_25_open_period_2026_06_for_trial.php
 * ═══════════════════════════════════════════════════════════════════════════
 * فتحُ فترةِ 2026-06 للترحيلِ — قرارُ تجربةٍ مُعلَنٌ لا صامت
 *
 * القياس: 894 واقعةً معتمَدةً و~2,600 منشورةً تنتظر، وكلُّها في 2026-06 وما
 * حولَه، والفترةُ `posting_allowed=0` فتقف على البابِ بلا سبيلٍ للتقدُّم.
 *
 * ◆ والبياناتُ كلُّها تجريبيةٌ بإقرارِ المالك، والغرضُ تشغيلُ الدورةِ كاملةً
 *   ومتابعتُها. ففتحُ الفترةِ هنا **قرارُ تجربةٍ** لا إعادةَ فتحِ فترةٍ محاسبيةٍ
 *   مقفلةٍ على بيانٍ حقيقي.
 * ◆ ويُسجَّل السببُ في `reopen_reason` فيبقى الأثرُ مقروءًا: من فتح ولماذا ومتى.
 * ◆ ولا تُمسُّ فترةٌ أخرى: الشرطُ على السنةِ والشهرِ صراحةً.
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

echo "══ فتحُ الفتراتِ المطلوبةِ للتجربة ══\n\n";
$reason = 'فتحُ تجربةٍ 2026-08: تشغيلُ دورةِ الترحيلِ كاملةً على بيانٍ تجريبيٍّ بإقرارِ المالك';

/* الفتراتُ التي تحتجز وقائعَ معتمَدةً — تُكتشف ولا تُفترض */
$rs = $conn->query("SELECT p.id, p.fiscal_year, p.period_no, p.start_date, p.end_date, p.state,
                           COUNT(e.id) waiting
                    FROM fin_financial_periods p
                    JOIN fin_financial_events e
                      ON e.company_id = p.company_id
                     AND DATE(e.occurred_at) BETWEEN p.start_date AND p.end_date
                     AND e.fes_status IN ('Approved','UnderReview','Published')
                     AND e.amount > 0
                    WHERE p.period_type = 'month' AND p.posting_allowed = 0
                    GROUP BY p.id
                    HAVING waiting > 0
                    ORDER BY waiting DESC");
$targets = array();
while ($x = $rs->fetch_assoc()) { $targets[] = $x; }
if (!$targets) { echo "  · لا فترةَ مغلقةٌ تحتجز وقائع — لا تغيير\n\n✔ تمّت\n"; exit(0); }

foreach ($targets as $t) {
    printf("  %s-%02d (%s ← %s) حالتُها %s · تحتجز %s واقعة\n",
        $t['fiscal_year'], (int) $t['period_no'], $t['start_date'], $t['end_date'],
        $t['state'], number_format((int) $t['waiting']));
}

$st = $conn->prepare("UPDATE fin_financial_periods
                      SET posting_allowed = 1, state = 'open', reopen_reason = ?, reopened_by = 0
                      WHERE id = ?");
$n = 0;
foreach ($targets as $t) {
    $id = (int) $t['id'];
    $st->bind_param('si', $reason, $id);
    if ($st->execute()) { $n++; }
}
$st->close();
echo "\n  ✔ فُتحت $n فترة · والسببُ مسجَّلٌ في reopen_reason\n";

$r = $conn->query("SELECT COUNT(*) FROM fin_financial_periods WHERE period_type='month' AND posting_allowed=1");
echo '  فتراتٌ مسموحُ الترحيلُ فيها الآن: ' . ($r ? $r->fetch_row()[0] : '?') . "\n";
echo "\n✔ تمّت\n";
