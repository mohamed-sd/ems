<?php
/**
 * 2027_12_02_repair01_w135_stabilization_down.php
 * ═══════════════════════════════════════════════════════════════════════════
 * تراجعُ W13.5 — يُسقط ما أضافته الهجرةُ الصاعدةُ ولا يمسُّ غيرَه.
 *
 * ⛔ **والقيودُ تُسقَط قبلَ أعمدتِها** — فعمودٌ يحرسه قيدٌ لا يُحذَف.
 * ⚠ **والتراجعُ يمحو أحكامًا مقيسة**: كلُّ ما كتبته المصالحةُ في
 *   `ownership_verdict` و`surface_kind` يذهب معه. فلا يُشغَّل إلّا بقصدٍ.
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

if (!in_array('--yes', $argv, true)) {
    echo "⚠ التراجعُ يمحو أحكامَ الملكيّةِ والتصنيفِ المقيسةَ كلَّها.\n";
    echo "  أعِدْ بـ--yes إن كان هذا قصدَك.\n";
    exit(1);
}
$n = 0;
$drop = function ($label, $sql) use ($conn, &$n) {
    if ($conn->query($sql) === true) { echo "  ✔ $label\n"; $n++; }
    else { echo "  ◆ $label — " . $conn->error . "\n"; }
};

echo "\n═══ تراجعُ W13.5 ═══\n\n① القيود\n";
foreach (array(
    'repair01_screen_registry' => array('chk_w135_ownv','chk_w135_kind','chk_w135_fin','chk_w135_why'),
    'repair01_decisions'       => array('chk_w135_appr_ref'),
    'repair01_target_gaps'     => array('chk_w135_ghost'),
) as $t => $cs) {
    foreach ($cs as $c) { $drop("قيد $c", "ALTER TABLE `$t` DROP CONSTRAINT `$c`"); }
}

echo "\n② الأعمدة\n";
$COLS = array(
    'repair01_screen_registry' => array('canonical_label_ar','surface_kind','ownership_verdict','action_guard',
        'permission_policy','grain_ar','source_of_truth','state_model_ref','finance_debt_class',
        'debt_owner','debt_wave','verdict_rule','verdict_at'),
    'repair01_decisions'   => array('decision_source','owner_decision_reference','recorded_by','evidence_ref','effective_from'),
    'repair01_target_gaps' => array('ghost_disposition','disposition_why'),
);
foreach ($COLS as $t => $cs) {
    foreach ($cs as $c) { $drop("عمود $t.$c", "ALTER TABLE `$t` DROP COLUMN `$c`"); }
}

echo "\n③ الجدول\n";
$drop('جدول repair01_decision_audit', "DROP TABLE IF EXISTS `repair01_decision_audit`");

echo "\n────────────────────────────────────────────────────────────\n";
printf("أُسقط %d عنصرًا · الحكم: رجع ✔\n", $n);
