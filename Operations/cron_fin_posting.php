<?php
/**
 * cron_fin_posting.php — تحريكُ الوقائعِ المنشورةِ إلى الدفتر (CLI فقط)
 * ═══════════════════════════════════════════════════════════════════════════
 * التشغيل:
 *   php Operations/cron_fin_posting.php --company=4 --limit=1000
 *   php Operations/cron_fin_posting.php --company=4 --limit=50 --dry-run
 *
 * ◆ **سقفٌ إلزاميّ**: لا يعمل بلا `--limit`، وأقصاه 1000 في التشغيلةِ الواحدة.
 *   فترحيلُ آلافٍ بضربةٍ واحدةٍ يجعل خطأً واحدًا في الخريطةِ آلافَ قيدٍ خاطئ.
 * ◆ و`--dry-run` يقيس المؤهَّلَ ولا يكتب حرفًا.
 * ◆ والمراحلُ الثلاثُ بالترتيب، ولا تبدأ واحدةٌ قبل أن تنتهي سابقتُها.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
require_once $ROOT . '/app/Services/Finance/PostingService.php';
use App\Services\Finance\PostingService as PS;

/* ── الوسائط ─────────────────────────────────────────────────────── */
$args = array();
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z\-]+)(?:=(.*))?$/', $a, $m)) { $args[$m[1]] = $m[2] ?? '1'; }
}
$companyId = isset($args['company']) ? (int) $args['company'] : 0;
$limit     = isset($args['limit']) ? (int) $args['limit'] : 0;
$dry       = isset($args['dry-run']);
$actor     = isset($args['actor']) ? (int) $args['actor'] : 0;

if ($companyId <= 0) { exit("يلزم --company=<id>\n"); }
if ($limit <= 0)     { exit("يلزم --limit=<n> — لا ترحيلَ بلا سقفٍ مُعلَن\n"); }
if ($limit > 1000)   { exit("السقفُ الأقصى 1000 في التشغيلةِ الواحدة (طُلب $limit)\n"); }

/* هويةٌ صريحةٌ: بوابةُ العزلِ تُبنى من الجلسةِ وهي fail-closed في CLI */
if ($actor <= 0) {
    $r = $conn->query("SELECT id FROM users WHERE company_id = $companyId AND role IN ('17','19') LIMIT 1");
    $actor = $r && ($x = $r->fetch_row()) ? (int) $x[0] : 0;
    if ($actor <= 0) {
        $r = $conn->query("SELECT id FROM users WHERE company_id = $companyId LIMIT 1");
        $actor = $r && ($x = $r->fetch_row()) ? (int) $x[0] : 0;
    }
}
if ($actor <= 0) { exit("تعذّر تحديدُ فاعلٍ للكيان $companyId — مرّر --actor=<id>\n"); }
$_SESSION = array('user' => array('id' => $actor, 'company_id' => $companyId, 'role' => '17'));
$gate = ems_tenant_db();

$ts = date('Y-m-d H:i:s');
echo "══ ترحيلُ الوقائعِ — كيان $companyId · سقف $limit · فاعل $actor · $ts ══\n";
if ($dry) { echo "◆ قياسٌ فقط — لا كتابة\n"; }

$one = function (string $s) use ($conn) { $r = $conn->query($s); return $r ? (int) $r->fetch_row()[0] : 0; };
$snap = function () use ($one, $companyId) {
    return array(
        'Published'   => $one("SELECT COUNT(*) FROM fin_financial_events WHERE company_id=$companyId AND fes_status='Published'"),
        'UnderReview' => $one("SELECT COUNT(*) FROM fin_financial_events WHERE company_id=$companyId AND fes_status='UnderReview'"),
        'Approved'    => $one("SELECT COUNT(*) FROM fin_financial_events WHERE company_id=$companyId AND fes_status='Approved'"),
        'Posted'      => $one("SELECT COUNT(*) FROM fin_financial_events WHERE company_id=$companyId AND fes_status='Posted'"),
        'Failed'      => $one("SELECT COUNT(*) FROM fin_financial_events WHERE company_id=$companyId AND fes_status='PostingFailed'"),
        'entries'     => $one("SELECT COUNT(*) FROM fin_journal_entries WHERE company_id=$companyId"),
    );
};
$before = $snap();
echo "  قبل: " . json_encode($before, JSON_UNESCAPED_UNICODE) . "\n";

if ($dry) {
    $eligible = $one("SELECT COUNT(*) FROM fin_financial_events e
                      JOIN fin_financial_periods p ON p.company_id=e.company_id AND p.period_type='month'
                       AND p.posting_allowed=1 AND DATE(e.occurred_at) BETWEEN p.start_date AND p.end_date
                      WHERE e.company_id=$companyId AND e.fes_status='Published' AND e.amount>0");
    echo "  المؤهَّلُ (منشورٌ · مبلغٌ موجب · فترةٌ مفتوحة): " . number_format($eligible) . "\n";
    echo "  وسيُعالَج منها في هذه التشغيلة: " . number_format(min($eligible, $limit)) . "\n";
    exit(0);
}

$show = function (string $label, array $r) {
    $line = array();
    foreach ($r as $k => $v) { if (!is_array($v)) { $line[] = "$k=$v"; } }
    echo "  $label: " . implode(' · ', $line) . "\n";
    foreach (array('reasons', 'levels') as $k) {
        if (!empty($r[$k]) && is_array($r[$k])) {
            foreach ($r[$k] as $why => $n) { echo "      · $why × $n\n"; }
        }
    }
};

echo "\n① المراجعة (Published ⇐ UnderReview)\n";
$show('نتيجة', PS::reviewPublished($gate, $conn, $companyId, $actor, $limit));

echo "\n② الاعتماد (UnderReview ⇐ Approved)\n";
$show('نتيجة', PS::approveReviewed($gate, $conn, $companyId, $actor, $limit));

echo "\n③ الترحيل (Approved ⇐ Posted)\n";
$p = PS::postApproved($gate, $conn, $companyId, $actor, $limit);
$show('نتيجة', $p);

$after = $snap();
echo "\n══ الحصيلة ══\n";
foreach ($before as $k => $v) {
    $d = $after[$k] - $v;
    printf("  %-12s %8s ⇐ %-8s %s\n", $k, number_format($v), number_format($after[$k]),
        $d === 0 ? '' : ($d > 0 ? "(+$d)" : "($d)"));
}
$bal = $conn->query("SELECT COUNT(*) FROM fin_journal_entries
                     WHERE company_id=$companyId AND ABS(total_debit - total_credit) > 0.005");
echo '  قيودٌ غيرُ متوازنة: ' . ($bal ? $bal->fetch_row()[0] : '?') . " (المتوقَّع 0)\n";
printf("  مجموعُ ما رُحِّل: مدين %s · دائن %s\n",
    number_format($p['debit_total'], 2), number_format($p['credit_total'], 2));
echo "\n[fin-posting " . date('Y-m-d H:i:s') . "] posted={$p['posted']} skipped={$p['skipped']} failed={$p['failed']}\n";
