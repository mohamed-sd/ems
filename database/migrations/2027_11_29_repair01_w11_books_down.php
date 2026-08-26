<?php
/**
 * 2027_11_29_repair01_w11_books_down.php — تراجعُ المرحلةِ الحاديةَ عشرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **التراجعُ ينزع ما بنَته الهجرةُ ولا يمسُّ حيًّا سابقًا**: الجداولُ التي
 *   أنشأتها W11 تُسقَط، والأعمدةُ التي أضافتها إلى جداولَ حيّةٍ تُنزَع،
 *   ومستهلكو أحداثِها يُعطَّلون بسببٍ مكتوبٍ لا يُحذفون.
 *
 * ⛔ **ولا يُمَسُّ عمودٌ لم تُنشئه هذه الهجرة** — والابنُ يُسقَط قبل أبيه.
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

$done = 0; $err = 0;
$run = function ($sql, $label) use ($conn, &$done, &$err) {
    if ($conn->query($sql) === true) { echo "  ✔ $label\n"; $done++; return true; }
    echo "  ✘ $label — " . $conn->error . "\n"; $err++; return false;
};

echo "══ REPAIR01 · W11 — تراجعُ دفاترِ الكيانات ══\n\n";

/* الابنُ قبل الأب */
$DROP = array(
    'tre_cash_count_line', 'tre_cash_count', 'tre_petty_expense', 'tre_petty_custody',
    'tre_recon_difference', 'tre_guarantee', 'tre_instrument', 'tre_fx_deal',
    'tre_transfer', 'tre_cash_move', 'tre_cash_box',
    'acc_period_reopen_request', 'acc_trial_balance_line', 'acc_trial_balance_run',
    'acc_account_recon_line', 'acc_account_recon', 'acc_period_adjustment',
    'acc_credit_limit', 'acc_supplier_accrual_line', 'acc_invoice_line',
    'acc_recognition_request',
    'repair01_w11_consolidated', 'repair01_w11_fixes', 'repair01_w11_thresholds',
    'repair01_w11_sod', 'repair01_w11_states', 'repair01_w11_journey',
    'repair01_w11_decisions', 'repair01_w11_sidebar', 'repair01_w11_scope',
);
foreach ($DROP as $t) { $run("DROP TABLE IF EXISTS `$t`", "إسقاط $t"); }

$COLS = array(
    array('fin_closing_items', 'exception_reason'),
    array('fin_closing_items', 'exception_by'),
    array('fin_closing_items', 'exception_at'),
    array('fin_closing_items', 'blocks_close'),
    array('fin_payments', 'recognition_request_id'),
    array('fin_journal_entries', 'recognition_request_id'),
    array('fin_journal_entries', 'entity_scope'),
    array('bank_statements', 'diff_count'),
    array('tre_beneficiaries', 'locked_at'),
    array('tre_beneficiaries', 'verify_doc_ref'),
);
foreach ($COLS as $c) {
    $r = $conn->query("SHOW COLUMNS FROM `{$c[0]}` LIKE '{$c[1]}'");
    if ($r && $r->num_rows > 0) { $run("ALTER TABLE `{$c[0]}` DROP COLUMN `{$c[1]}`", "نزع {$c[0]}.{$c[1]}"); }
    else { echo "  ↷ {$c[0]}.{$c[1]} غير قائم\n"; }
}

$run("UPDATE `event_consumers` SET `active` = 0,
        `inactive_reason` = 'تراجع هجرة W11 - دفاتر الكيانات', `inactive_at` = NOW()
      WHERE `consumer_key` LIKE 'w11\\_%'", 'تعطيلُ مستهلكي W11');

echo "\n───────────────────────────────────────────────────────────────\n";
echo "الخلاصة: نُفِّذ $done · أخطاء $err\n";
exit($err > 0 ? 1 : 0);
