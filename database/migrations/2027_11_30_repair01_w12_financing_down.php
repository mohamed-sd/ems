<?php
/**
 * 2027_11_30_repair01_w12_financing_down.php — تراجعُ المرحلةِ الثانيةَ عشرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **التراجعُ ينزع ما بنَته الهجرةُ ولا يمسُّ حيًّا سابقًا**: جداولُ W12 تُسقَط،
 *   وأعمدتُها على الجداولِ الحيّةِ تُنزَع، ومستهلكو أحداثِها **يُعطَّلون بسببٍ
 *   مكتوبٍ لا يُحذفون** — فسجلُّ المشتركين تاريخٌ لا قائمةُ تشغيل.
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
function w12d_col(mysqli $c, $t, $col)
{
    $r = $c->query("SHOW TABLES LIKE '" . $c->real_escape_string($t) . "'");
    if (!$r || $r->num_rows === 0) { return false; }
    $r2 = $c->query("SHOW COLUMNS FROM `$t` LIKE '" . $c->real_escape_string($col) . "'");
    return $r2 && $r2->num_rows > 0;
}

echo "══ REPAIR01 · W12 — تراجعُ التمويلِ والممولين ══\n\n";

/* الابنُ قبل الأب */
$DROP = array(
    'fin_payment_allocation', 'fin_legacy_payment_aggregate', 'fin_payment_order',
    'fin_close_consumption', 'fin_close_link',
    'fin_final_close', 'fin_monthly_close', 'fin_contract_close',
    'fin_contract_covenant', 'fin_contract_term', 'fin_finance_contract',
    'fin_precontract_review', 'fin_funding_offer', 'fin_funding_need',
    'fin_ref_list', 'fin_financier_document', 'fin_financier_contact',
    'repair01_w12_nav_moves', 'repair01_w12_layers', 'repair01_w12_fixes', 'repair01_w12_thresholds',
    'repair01_w12_sod', 'repair01_w12_states', 'repair01_w12_journey',
    'repair01_w12_decisions', 'repair01_w12_sidebar', 'repair01_w12_scope',
);
foreach ($DROP as $t) { $run("DROP TABLE IF EXISTS `$t`", "إسقاط $t"); }

$COLS = array(
    array('financing_operations',   'contract_id'),
    array('financing_operations',   'final_close_id'),
    array('financing_installments', 'contract_close_id'),
    array('financing_installments', 'allocated_amount'),
    array('financing_deviations',   'final_close_block'),
);
foreach ($COLS as $c) {
    if (w12d_col($conn, $c[0], $c[1])) {
        $run("ALTER TABLE `{$c[0]}` DROP COLUMN `{$c[1]}`", "نزع {$c[0]}.{$c[1]}");
    } else { echo "  ↷ {$c[0]}.{$c[1]} غير موجود\n"; }
}

/* إرجاعُ تشديدِ حبّةِ الكيان — العمودُ يعود كما كان قبلَ الهجرة */
foreach (array('financing_installments', 'financed_assets') as $t) {
    if (w12d_col($conn, $t, 'company_id')) {
        $run("ALTER TABLE `$t` MODIFY COLUMN `company_id` INT(11) NULL", "إرخاء $t.company_id");
    }
}

$run("UPDATE `event_consumers`
         SET `active` = 0,
             `inactive_reason` = 'RPR-W12 down — تراجعت هجرة المرحلة الثانية عشرة',
             `inactive_at` = NOW()
       WHERE `consumer_key` LIKE 'w12\\_%'", 'تعطيلُ مستهلكي W12 بسببٍ مكتوب');

echo "\n───────────────────────────────────────────────────────────────\n";
echo "الخلاصة: نُفِّذ $done · أخطاء $err\n";
exit($err > 0 ? 1 : 0);
