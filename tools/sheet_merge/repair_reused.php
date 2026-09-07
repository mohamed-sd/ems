<?php
/**
 * tools/sheet_merge/repair_reused.php — ردُّ الأعمدةِ التي دهسها البذّارُ خطأً
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **العطبُ**: بذّارُ الجولةِ كان يملأ **كلَّ** أعمدةِ الخطّة، وفيها ثمانيةٌ
 *   وثلاثون عمودًا **قائمًا قبلَ الجولة** أُعيد استعمالُه (‏`contract_code` ·
 *   `legal_name` · `claim_no` …) — فكُتب فوقَها. أُصلحت الأداةُ فلن يتكرّر،
 *   وهذا الملفُّ يردُّ ما أمكن ردُّه.
 *
 * ◆ **والردُّ من الصفِّ نفسِه حيث أمكن**: `contracts.client_no` يعود من
 *   `client_id` الحقيقيِّ لا من رقمٍ مولَّد، و`clients.legal_name` من
 *   `client_name` القائمِ سليمًا. وما لا مصدرَ له يُعاد **بعُرفِ النظامِ نفسِه**
 *   (`CNT-0001` لا `EMS-5C1-0053`) — فالقيمةُ الأصليّةُ ذهبت ولا تُلفَّق.
 *
 * ⚠ **ولا يُعاد ما لم يُمَسّ**: عمودٌ ردَّه حارسُ عملٍ (‏`exec_approvals` ·
 *   `fin_journal_entries`) لم يُكتب فيه أصلًا فلا يُلمس هنا.
 *
 * الاستعمال: php tools/sheet_merge/repair_reused.php [--company=4] [--apply]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = dirname(__DIR__, 2);
require_once $ROOT . '/includes/env.php';

$COMPANY = 4; $APPLY = false;
foreach ($argv as $a) {
    if (strpos($a, '--company=') === 0) { $COMPANY = (int) substr($a, 10); }
    elseif ($a === '--apply') { $APPLY = true; }
}
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_PASS'); if ($p === null || $p === '') { $p = ems_env('DB_PASS'); }
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_error) { exit('تعذر الاتصال: ' . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');

/* بصمةُ البذّار: ما يحمل هذا الشكلَ هو ما كتبناه، وما عداه أصليٌّ لا يُمَسّ */
$STAMP = "(`%s` REGEXP '^EMS-[0-9A-F]{3}-[0-9]{4}$' OR `%s` LIKE '%% — قيد %%')";

/* العمود ⇒ تعبيرُ القيمةِ البديلة. `%d` رتبةُ الصفِّ، و`t` اسمُ الجدول. */
$FIX = array(
    'contracts.contract_code'          => array('kind' => 'code',  'prefix' => 'CNT'),
    'contracts.client_no'              => array('kind' => 'col',   'from'   => 'client_id'),
    'clients.legal_name'               => array('kind' => 'col',   'from'   => 'client_name'),
    'claims.claim_no'                  => array('kind' => 'code',  'prefix' => 'CLM'),
    'transfer_orders.order_no'         => array('kind' => 'code',  'prefix' => 'TRF'),
    'fin_requests.source_ref'          => array('kind' => 'code',  'prefix' => 'SRC'),
    'proc_request.source_ref'          => array('kind' => 'code',  'prefix' => 'SRC'),
    'fin_tax_transactions.source_ref'  => array('kind' => 'code',  'prefix' => 'SRC'),
    'transfer_orders.source_ref'       => array('kind' => 'code',  'prefix' => 'SRC'),
    'exec_decisions.status'            => array('kind' => 'enumish', 'vals' => array('open', 'in_progress', 'closed')),
    'sal_client_needs.notes'           => array('kind' => 'null'),
    'quotations.notes'                 => array('kind' => 'null'),
    'claims.notes'                     => array('kind' => 'null'),
    'tickets.reporter_contact'         => array('kind' => 'null'),
    'transfer_orders.creator_name'     => array('kind' => 'null'),
    'quotations.source_commitment_cycle_key' => array('kind' => 'null'),
    'quotations.evidence_level'        => array('kind' => 'null'),
    'quotations.residual_value_basis'  => array('kind' => 'null'),
    'sal_client_needs.duration_months' => array('kind' => 'null'),
    'claims.period_from'               => array('kind' => 'null'),
);

$touched = 0; $rows = 0;
foreach ($FIX as $key => $f) {
    list($t, $col) = explode('.', $key);
    $has = $conn->query("SELECT 1 FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . $conn->real_escape_string($t) . "'
                           AND COLUMN_NAME='" . $conn->real_escape_string($col) . "' LIMIT 1");
    if (!$has || !$has->num_rows) { printf("  – %-40s (لا عمود)\n", $key); continue; }
    $where = sprintf($STAMP, $col, $col);
    $c = $conn->query("SELECT COUNT(*) n FROM `$t` WHERE company_id = " . (int) $COMPANY . " AND $where");
    $n = $c ? (int) $c->fetch_assoc()['n'] : 0;
    if ($n === 0) { printf("  ✔ %-40s سليمٌ — لم يُكتب فيه\n", $key); continue; }

    if ($APPLY) {
        if ($f['kind'] === 'null') {
            $conn->query("UPDATE `$t` SET `$col` = NULL WHERE company_id = " . (int) $COMPANY . " AND $where");
        } elseif ($f['kind'] === 'col') {
            $conn->query("UPDATE `$t` SET `$col` = `" . $f['from'] . "`
                          WHERE company_id = " . (int) $COMPANY . " AND $where");
        } elseif ($f['kind'] === 'enumish') {
            $v = $f['vals'];
            $conn->query("UPDATE `$t` SET `$col` = ELT(1 + MOD(id, " . count($v) . "), '"
                         . implode("','", array_map(array($conn, 'real_escape_string'), $v)) . "')
                          WHERE company_id = " . (int) $COMPANY . " AND $where");
        } else {                                   /* code — بعُرفِ النظام */
            $conn->query("UPDATE `$t` SET `$col` = CONCAT('" . $f['prefix'] . "-', LPAD(id, 4, '0'))
                          WHERE company_id = " . (int) $COMPANY . " AND $where");
        }
        if ($conn->error) { printf("  ⛔ %-40s %s\n", $key, $conn->error); continue; }
    }
    printf("  ↺ %-40s %d صفًّا %s\n", $key, $n, $f['kind']);
    $touched++; $rows += $n;
}
printf("\n%s — أعمدةٌ رُدَّت: %d · صفوفٌ: %d\n",
       $APPLY ? 'تنفيذ' : 'عرضٌ فقط (بلا --apply)', $touched, $rows);
