<?php
/**
 * 2027_09_23_chain_screens_profile_items.php
 *   القفلُ الرابع — بنودُ القالبِ لشاشاتِ عقدِ السلسلة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المنحُ يفتح أربعةَ أقفالٍ لا قفلًا**: `modules` و`nav_items` و
 *   `role_permissions` — **و`gov_profile_items`**. والأخيرُ **يُلغي
 *   `role_permissions` كليًّا**: مستخدمٌ له منحُ قالبٍ نافذ، والشاشةُ خارجَ
 *   بنودِ قالبِه، يُردُّ `t_view = -1` ⇒ **منعٌ بالقالب** مهما قال جدولُ الأدوار.
 *
 * ◆ **وقد قِيس حيًّا قبلَ هذه الهجرة**: الشاشاتُ السبعُ الجديدةُ مُنحت في
 *   `role_permissions` (٦٠ منحًا) ومُدرجت في التنقّل (٥٨ بندًا) — **ومع ذلك
 *   رُدَّت كلُّها 302 لصاحبِ دورِها**، بينما أختُها `entitlement_gate.php`
 *   تُفتح 200. والفرقُ بندُ القالبِ لا غير.
 *
 * ◆ **ولا يُخترَع قالب**: كلُّ شاشةٍ تأخذ **قوالبَ أختِها** بالحقوقِ نفسِها —
 *   فمن يفتح الأختَ يفتح الجديدةَ، ومن لا يفتحها لا يفتحها.
 *
 * التشغيل:  php database/migrations/2027_09_23_chain_screens_profile_items.php
 * الرجوع :  php database/migrations/2027_09_23_chain_screens_profile_items.php --revert
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$MAP = array(
    'Finance/unit_fin_final.php'     => 'Finance/entitlement_gate.php',
    'Finance/ar_accrual_gen.php'     => 'Finance/entitlement_gate.php',
    'Finance/ar_completion_cert.php' => 'Finance/entitlement_gate.php',
    'Finance/ar_claim_invoice.php'   => 'Finance/entitlement_gate.php',
    'Finance/tre_beneficiary.php'    => 'Finance/payments_fin.php',
    'Finance/tre_pay_batch.php'      => 'Finance/payments_fin.php',
    'Operations/unit_correction.php' => 'Operations/unit_perf.php',
);
$SEED = 'INJ-CHAIN-CLOSE-01';

if (in_array('--revert', $argv, true)) {
    $st = $conn->prepare("DELETE FROM `gov_profile_items` WHERE `seeded_from` = ?");
    $st->bind_param('s', $SEED); $st->execute();
    echo "↺ حُذف {$st->affected_rows} بندَ قالبٍ من هذه الجولة\n";
    $st->close();
    exit(0);
}

$ins = $conn->prepare(
  "INSERT INTO `gov_profile_items`
     (`company_id`,`profile_id`,`item_kind`,`item_ref`,`allow`,`can_add`,`can_edit`,`can_delete`,`seeded_from`)
   VALUES (?,?, 'screen', ?,?,?,?,?,?)");
$sel = $conn->prepare(
  "SELECT `company_id`,`profile_id`,`allow`,`can_add`,`can_edit`,`can_delete`
     FROM `gov_profile_items` WHERE `item_kind` = 'screen' AND `item_ref` = ?");
$chk = $conn->prepare(
  "SELECT COUNT(*) FROM `gov_profile_items`
    WHERE `item_kind` = 'screen' AND `item_ref` = ? AND `profile_id` = ?");

$made = 0; $skipped = 0; $noAnchor = array();
foreach ($MAP as $route => $anchor) {
    $sel->bind_param('s', $anchor);
    $sel->execute();
    $res = $sel->get_result();
    $n = 0;
    while ($res && $r = $res->fetch_assoc()) {
        $pid = (int) $r['profile_id'];
        $chk->bind_param('si', $route, $pid);
        $chk->execute(); $chk->bind_result($have); $chk->fetch(); $chk->free_result();
        if ((int) $have > 0) { $skipped++; $n++; continue; }
        $co  = (int) $r['company_id'];
        $al  = (int) $r['allow'];
        $ad  = (int) $r['can_add'];
        $ed  = (int) $r['can_edit'];
        $de  = (int) $r['can_delete'];
        $ins->bind_param('iisiiiis', $co, $pid, $route, $al, $ad, $ed, $de, $SEED);
        if ($ins->execute()) { $made++; $n++; }
        else { echo "  ✘ {$route} / قالب {$pid}: {$ins->error}\n"; }
    }
    if ($n === 0) { $noAnchor[] = $route . ' (أختُه ' . $anchor . ' بلا بنودِ قوالب)'; }
    printf("  %-36s قوالبُ أختِه: %d\n", $route, $n);
}
$ins->close(); $sel->close(); $chk->close();

printf("① أُضيف %d بندَ قالبٍ · مُكرَّرٌ سلفًا %d\n", $made, $skipped);
if ($noAnchor) {
    echo "  ⚠ بلا بندِ قالب: " . implode(' · ', $noAnchor) . "\n";
    echo "     ◆ وهذا **لا يعني الانفتاح**: أختُها نفسُها خارجَ القوالب، فالمسارُ\n";
    echo "       القائمُ هو `role_permissions` — ويُقاس بالفتحِ الحيِّ لا بالافتراض.\n";
}

ems_migration_recorded(__FILE__, $conn, 0);
