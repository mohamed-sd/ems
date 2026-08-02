<?php
/**
 * Tickets/watchtower.php — برج المراقبة: المؤشرات وتقرير المتأخرين
 * (update0004 · TKT-17 · TKT-01 §10/§11)
 */
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../app/Services/Tickets/WatchTowerService.php';
require_once __DIR__ . '/../includes/screen_contract.php';

use App\Services\Tickets\WatchTowerService as WT;

$current_role = strval($_SESSION['user']['role'] ?? '');
$is_super_admin = ($current_role === '-1');
$company_id = intval($_SESSION['user']['company_id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php"); exit(); }
if ($is_super_admin && $company_id <= 0) { $company_id = 4; }

$MODULE_CODE = 'Tickets/watchtower.php';
$can_view = $is_super_admin;
if (!$is_super_admin) {
    $st = $conn->prepare("SELECT rp.can_view FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                          WHERE m.code = ? AND rp.role_id = ? LIMIT 1");
    $rid = intval($current_role);
    $st->bind_param('si', $MODULE_CODE, $rid);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) { $can_view = intval($row['can_view']) === 1; }
    $st->close();
}
if (!$can_view) { header("Location: ../main/dashboard.php?msg=" . rawurlencode('لا صلاحية لبرج المراقبة ❌')); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_report'])) {
    $r = WT::issuePeriodicReport($conn, $company_id, intval($_SESSION['user']['id'] ?? 0));
    header("Location: watchtower.php?msg=" . rawurlencode($r['summary'] . ' — أُصدر ✅'));
    exit();
}

$msg = strval($_GET['msg'] ?? '');
$ind = WT::indicators($conn, $company_id);
$late = WT::latenessReport($conn, $company_id);

$page_title = 'إيكوبيشن | برج المراقبة';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'برج المراقبة — يقيس المركز ولا يعمل'; $header_icon = 'fa fa-broadcast-tower';
    $header_actions = array();
    include('../includes/page_header.php');
    ems_screen_about(
        'المؤشرات الثمانية (TKT-01 §11) وتقرير «من يتأخر ومن لا يستجيب» بالاسم والإدارة — '
        . 'المركز يرصد ويتواصل ويسجّل، ولا يوجّه ولا يصعّد (النظام يفعل ذلك آليًّا).',
        array('التقرير الدوري يُرفع لمدير التشغيل والإدارة العامة',
              'عدم الاستجابة مؤشر إهمال لا تأخير — مستهدفه صفر'));
    if ($msg !== '') { echo '<div class="alert alert-info">' . htmlspecialchars($msg) . '</div>'; }
    ?>

    <div class="card"><div class="card-header"><h5>المؤشرات الثمانية — نافذة <?php echo intval($ind['window_days']); ?> يومًا
        (<?php echo intval($ind['workstreams_measured']); ?> مسارًا)</h5></div>
    <div class="card-body" style="display:flex;gap:12px;flex-wrap:wrap">
        <?php
        $tiles = array(
            array('① الاستجابة داخل المهلة', $ind['①_response_compliance_pct'] . '%', $ind['①_target']),
            array('② الإنجاز داخل المهلة', $ind['②_resolve_compliance_pct'] . '%', $ind['②_target']),
            array('③ لم يُستجب له إطلاقًا', $ind['③_never_responded'], $ind['③_target']),
            array('④ متوسط زمن التعليق', $ind['④_avg_hold_minutes'] . ' دقيقة', 'ارتفاعه مخبأ للتأخير'),
            array('⑤ المعاد فتحها', $ind['⑤_reopen_pct'] . '%', $ind['⑤_target']),
            array('⑥ بلاغات التكرار', $ind['⑥_recurrence_tickets'], 'تُرفع مشكلة لا حوادث'),
            array('⑦ مغلق بلا أثر', $ind['⑦_closed_without_effect'], $ind['⑦_target']),
            array('⑧ المتأخرون بالاسم', count($late), 'أساس التقرير الدوري'),
        );
        foreach ($tiles as $t) {
            echo '<div style="border:1px solid #ddd;border-radius:8px;padding:10px 14px;min-width:170px;flex:1">'
                . '<div style="color:#666;font-size:12px">' . htmlspecialchars($t[0]) . '</div>'
                . '<div style="font-size:22px;font-weight:bold">' . htmlspecialchars((string) $t[1]) . '</div>'
                . '<small style="color:#888">المستهدف: ' . htmlspecialchars((string) $t[2]) . '</small></div>';
        } ?>
    </div></div>

    <div class="card"><div class="card-header"><h5>⑧ من يتأخر ومن لا يستجيب — بالاسم والإدارة</h5>
        <form method="post" style="display:inline"><button type="submit" name="issue_report" value="1" class="btn-save">أصدر التقرير الدوري</button></form>
    </div>
    <div class="card-body">
        <?php if (!$late) { ems_state_empty('لا متأخر ولا غير مستجيب في النافذة — نظيف ✨'); } else { ?>
        <div class="table-container"><table class="alltables display nowrap" style="width:100%" data-no-dt="1">
            <thead><tr><th>المكلف</th><th>الإدارة</th><th>مسند إليه</th><th>لم يستجب</th><th>استجاب متأخرًا</th><th>متوسط التأخير (دقيقة)</th></tr></thead>
            <tbody>
            <?php foreach ($late as $x): ?>
                <tr><td><?php echo htmlspecialchars($x['person_name'] ?: ('#' . $x['assignee_person_id'])); ?></td>
                    <td><?php echo htmlspecialchars($x['unit_name'] ?: '—'); ?></td>
                    <td><?php echo intval($x['assigned']); ?></td>
                    <td style="color:#c0392b;font-weight:bold"><?php echo intval($x['no_response']); ?></td>
                    <td><?php echo intval($x['late_response']); ?></td>
                    <td><?php echo intval($x['avg_delay_min']); ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php } ?>
    </div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
