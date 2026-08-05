<?php
/**
 * Operations/cron_unit_chain_sla.php — كانسُ مهل سلسلة الوحدة (E-02 POL-015-و · DEC-01 ⑦)
 * ───────────────────────────────────────────────────────────────────────────
 * دوريًّا (كل ساعة): الصفوف الوسيطة غير الموروثة المتجاوزة مهلةَ حلقتها
 * (unit_chain_helpers: 24/48/72 بحسب الحالة) → إشعارُ أصحاب المرحلة
 * (مرةً واحدةً يوميًّا لكل مستلم)، وإن تجاوز الأقدمُ سبعةَ أيامٍ صُعِّد
 * لحساب الإدارة العامة «تنفيذ» (DEC-01 ⑦ نصًّا).
 * التشغيل: php Operations/cron_unit_chain_sla.php
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/unit_chain_helpers.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

const EXEC_ACCOUNT_UID = 881; // «تنفيذ» — الدور 9

$companies = array();
$r = mysqli_query($conn, "SELECT id FROM admin_companies WHERE LOWER(COALESCE(status,'active'))='active'");
while ($r && ($x = mysqli_fetch_assoc($r))) { $companies[] = (int) $x['id']; }
if (!$companies) { $companies = array(4); }

$totalNotified = 0;
foreach ($companies as $cid) {
    // العالق المتجاوز مهلته — مجمَّعًا بالحالة
    $pend = "'" . implode("','", ems_uc_pending_states()) . "'";
    $rows = array();
    $r = mysqli_query($conn,
        "SELECT ue.state, COUNT(*) n, MAX(TIMESTAMPDIFF(HOUR, ue.created_at, NOW())) max_h
           FROM unit_entries ue
          WHERE ue.company_id = {$cid} AND ue.state IN ($pend)
            AND NOT " . ems_uc_prechain_sql('ue') . "
          GROUP BY ue.state");
    while ($r && ($x = mysqli_fetch_assoc($r))) { $rows[$x['state']] = $x; }

    foreach ($rows as $state => $x) {
        $sla = ems_uc_stage_sla_hours($state);
        if ((int) $x['max_h'] < $sla) { continue; }
        // عدُّ المتجاوز فعليًّا لهذه الحالة
        $rr = mysqli_query($conn,
            "SELECT COUNT(*) n FROM unit_entries ue
              WHERE ue.company_id={$cid} AND ue.state='" . mysqli_real_escape_string($conn, $state) . "'
                AND NOT " . ems_uc_prechain_sql('ue') . "
                AND ue.created_at < DATE_SUB(NOW(), INTERVAL {$sla} HOUR)");
        $n = ($rr && ($y = mysqli_fetch_assoc($rr))) ? (int) $y['n'] : 0;
        if ($n <= 0) { continue; }
        $title = "تجاوزُ مهلة اعتماد: {$n} وحدةً بحالة {$state} فوق {$sla} ساعة";
        $link  = '../Approvals/hours_approval.php#sla-' . $state;
        foreach (ems_uc_stage_owner_roles($state) as $roleId) {
            $ur = mysqli_query($conn,
                "SELECT id FROM users WHERE company_id={$cid} AND role='" . (int) $roleId . "' AND status='active'");
            while ($ur && ($u = mysqli_fetch_assoc($ur))) {
                if (ems_uc_notify_once($conn, $cid, (int) $u['id'], $title, $link)) { $totalNotified++; }
            }
        }
        echo "[co{$cid}] {$state}: {$n} متجاوزًا (مهلة {$sla}س)\n";
    }

    // تصعيد DEC-01 ⑦: الأقدم فوق سبعة أيام → الإدارة العامة
    $m = ems_uc_lag_metrics($conn, $cid);
    if ($m['oldest_days'] > 7 && $m['pending'] > 0) {
        $t = "DEC-01 ⑦: {$m['pending']} وحدةً غيرَ معتمدةٍ وأقدمُها {$m['oldest_days']} يومًا (شركة {$cid})";
        if (ems_uc_notify_once($conn, $cid, EXEC_ACCOUNT_UID, $t, '../Approvals/hours_approval.php#dec01-7')) {
            $totalNotified++;
        }
        echo "[co{$cid}] تصعيدٌ للإدارة العامة: أقدمُ العالق {$m['oldest_days']} يومًا\n";
    }
}
echo "تم — إشعاراتٌ جديدة: {$totalNotified}\n";
