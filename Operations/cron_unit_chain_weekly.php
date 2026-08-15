<?php
/**
 * Operations/cron_unit_chain_weekly.php — مؤشر DEC-01 ⑦ الأسبوعي
 * ───────────────────────────────────────────────────────────────────────────
 * أسبوعيًّا (الاثنين 07:00): لكل شركةٍ الرقمان — غيرُ المعتمد وأقدمُه بالأيام —
 * ونسبةُ المعتمد إلى المسجَّل في الأسبوع (المستهدف ≥95٪ وصفرُ وحدةٍ فوق 7 أيام).
 * يُلحق سطرًا في storage/reports/unit_chain_weekly.csv ويُشعر الأدوار 1و6،
 * وعند الخرق يُرفع لحساب «تنفيذ» (الإدارة العامة) نصًّا بقرار DEC-01 ⑦.
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/cron_guard.php';
ems_cron_guard('cron_unit_chain_weekly.php'); // INJ-0025: لا تُشغَّل من المتصفّح
require_once __DIR__ . '/../includes/unit_chain_helpers.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

const EXEC_ACCOUNT_UID = 881;

$dir = dirname(__DIR__) . '/storage/reports';
if (!is_dir($dir)) { mkdir($dir, 0775, true); }
$csv = $dir . '/unit_chain_weekly.csv';
$new = !file_exists($csv);
$f = fopen($csv, 'a');
if ($new) { fwrite($f, "\xEF\xBB\xBF"); fputcsv($f, array('التاريخ','الشركة','غير المعتمد','أقدمه بالأيام','مسجَّل الأسبوع','معتمَد الأسبوع','النسبة٪','خرق')); }

$r = mysqli_query($conn, "SELECT id FROM admin_companies WHERE LOWER(COALESCE(status,'active'))='active'");
$companies = array();
while ($r && ($x = mysqli_fetch_assoc($r))) { $companies[] = (int) $x['id']; }
if (!$companies) { $companies = array(4); }

foreach ($companies as $cid) {
    $m = ems_uc_lag_metrics($conn, $cid);
    $breach = ($m['oldest_days'] > 7) || ($m['ratio'] !== null && $m['ratio'] < 95.0);
    fputcsv($f, array(date('Y-m-d'), $cid, $m['pending'], $m['oldest_days'],
                      $m['week_registered'], $m['week_converted'],
                      $m['ratio'] === null ? '—' : $m['ratio'], $breach ? 'نعم' : 'لا'));

    $title = "مؤشر DEC-01 ⑦ الأسبوعي: عالق {$m['pending']} · أقدمه {$m['oldest_days']} يومًا"
           . ($m['ratio'] !== null ? " · النسبة {$m['ratio']}٪" : '');
    $link = '../Approvals/hours_approval.php#dec01-7-weekly';
    foreach (array(1, 6) as $roleId) {
        $ur = mysqli_query($conn, "SELECT id FROM users WHERE company_id={$cid} AND role='{$roleId}' AND status='active'");
        while ($ur && ($u = mysqli_fetch_assoc($ur))) {
            ems_uc_notify_once($conn, $cid, (int) $u['id'], $title, $link);
        }
    }
    if ($breach) {
        ems_uc_notify_once($conn, $cid, EXEC_ACCOUNT_UID,
            'خرقُ مستهدف DEC-01 ⑦ (شركة ' . $cid . '): ' . $title, $link);
    }
    echo "[co{$cid}] عالق={$m['pending']} أقدم={$m['oldest_days']}ي أسبوع={$m['week_converted']}/{$m['week_registered']}"
       . ($m['ratio'] !== null ? " ({$m['ratio']}٪)" : '') . ($breach ? ' ⚠ خرق' : '') . "\n";
}
fclose($f);
echo "أُلحق بالتقرير: storage/reports/unit_chain_weekly.csv\n";
