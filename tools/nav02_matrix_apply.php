<?php
/**
 * C-02/C-03/C-05 · تطبيقُ مصفوفة NAV-02 على الحي — update0006
 * ─────────────────────────────────────────────────────────────
 * لكل صفٍّ معلَّقٍ في الدلتا: إطفاءُ الرابط + تحويلُ مسارٍ بعدّاد (SCR-01 §5)
 * — «لا يُحذف مسارٌ قبل هبوط hits صفرًا فترةً موثَّقة».
 * والغريبُ يُطفأ من قوائم غير مالكه وحدَها ويبقى عند مالكه.
 * idempotent — يُعاد تشغيلُه بأمان. التشغيل: php tools/nav02_matrix_apply.php
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean(); // config.php يبتلع مخرجَ CLI
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

function deact($conn, $route, $roles = null) {
    $sql = "UPDATE nav_items SET active=0 WHERE active=1 AND route='" . mysqli_real_escape_string($conn, $route) . "'";
    if ($roles) $sql .= " AND role_id IN (" . implode(',', array_map('intval', $roles)) . ")";
    mysqli_query($conn, $sql);
    return mysqli_affected_rows($conn);
}
function redirect_add($conn, $old, $new) {
    if ($old === $new) return 0;
    $o = mysqli_real_escape_string($conn, $old); $n = mysqli_real_escape_string($conn, $new);
    $r = mysqli_query($conn, "SELECT id FROM nav_redirects WHERE old_route='$o' LIMIT 1");
    if ($r && mysqli_fetch_row($r)) return 0;
    mysqli_query($conn, "INSERT INTO nav_redirects (old_route, new_route, active, hits) VALUES ('$o','$n',1,0)");
    return mysqli_affected_rows($conn);
}

$done = array('deact' => 0, 'redir' => 0);

/* ── ① الدمجُ في ملف العقد (15) — البطاقةُ تُفتح من قائمتها ─────────────── */
$toContract = array('Clients/contract_amendments.php','Clients/contract_commitments.php','Clients/contract_events.php',
 'Contracts/contract_baseline.php','Contracts/contract_guarantees.php','Contracts/contract_lifecycle.php',
 'Contracts/contract_lines.php','Contracts/contract_monthly_plan.php','Contracts/contract_obligations.php',
 'Contracts/contract_payment_schedule.php','Contracts/contract_resource_plan.php','Contracts/contract_sites.php',
 'Contracts/penalties.php','Contracts/plan_actual_link.php','Contracts/price_terms.php');
foreach ($toContract as $r) { $done['deact'] += deact($conn, $r); $done['redir'] += redirect_add($conn, $r, 'Contracts/contracts.php'); }

/* ── ② الدمجُ في ملف المورد (7) ──────────────────────────────────────────── */
$toSupplier = array('Finance/supplier_statement_fin.php','Suppliers/supplier_capacity.php','Suppliers/supplier_closure.php',
 'Suppliers/supplier_contract_lines.php','Suppliers/supplier_documents.php','Suppliers/supplier_evaluation.php',
 'Suppliers/supplier_rules.php');
foreach ($toSupplier as $r) { $done['deact'] += deact($conn, $r); $done['redir'] += redirect_add($conn, $r, 'Suppliers/suppliers.php'); }

/* ── ③ الأسماءُ المكررة — «يُحدَّد بعد الفحص» فُحص وحُسم (اجتهادٌ مسجَّل) ── */
$dups = array(
    'Employees/employee_card.php'    => 'Employees/employee_profile.php', // بطاقتان لموظف — الملفُّ الأحدث يبقى
    'Equipments/equipments_fleet.php' => 'Equipments/equipments.php',     // «إدارة المعدات (نسخة قديمة)»
    'Contracts/collections.php'      => 'Finance/dues_fin.php',           // «الذمم والتحصيل» ×2 — المالكُ المالية
    'emsreports/index.php'           => 'Reports/reports.php',            // «مركز التقارير» ×2
);
foreach ($dups as $old => $new) { $done['deact'] += deact($conn, $old); $done['redir'] += redirect_add($conn, $old, $new); }

/* ── ④ نقلٌ إلى مركز التقارير (6) — تقريرٌ لا شاشةُ عمل ─────────────────── */
$toReports = array('FinRequests/cycle_time_board.php','FinRequests/effect_map.php','FinRequests/requests_reports.php',
 'Finance/budget_form_fin.php','Finance/cost_report_fin.php','Finance/events_list_fin.php');
foreach ($toReports as $r) { $done['deact'] += deact($conn, $r); $done['redir'] += redirect_add($conn, $r, 'Reports/reports.php'); }

/* ── ⑤ نقلٌ إلى مساحة عملي (5) — الشخصيُّ فوق الإدارات لا داخلَها ──────── */
// تُطفأ نسخُ القوائم وتبقى نسخةُ HOME (g1) — فمساحةُ عملي بابٌ فوق الإدارات
$g1 = array();
$r = mysqli_query($conn, "SELECT id FROM link_groups WHERE group_code='g1'");
while ($x = mysqli_fetch_assoc($r)) $g1[] = (int)$x['id'];
$g1in = implode(',', $g1);
foreach (array('FinRequests/dept_inbox.php','FinRequests/my_requests.php','FinRequests/request_form.php',
               'chats/index.php','main/my_workspace.php','main/role_board.php') as $rt) {
    $e = mysqli_real_escape_string($conn, $rt);
    mysqli_query($conn, "UPDATE nav_items SET active=0 WHERE active=1 AND route='$e' AND group_id NOT IN ($g1in)");
    $done['deact'] += mysqli_affected_rows($conn);
    $done['redir'] += redirect_add($conn, $rt === 'main/my_workspace.php' ? '' : $rt, 'main/my_workspace.php');
}

/* ── ⑥ إلغاءُ القوائم الوسيطة — الوجهةُ مباشرةً ─────────────────────────── */
$drops = array(
    'Equipments/select_project.php' => 'Equipments/equipments.php',
    'Oprators/select_project.php'   => 'Oprators/oprators.php',
    'Maintenance/breakdowns.php'    => 'Maintenance/orders.php',
    'Reports/new_reports.php'       => 'Reports/reports.php',
    'Timesheet/timesheet_type.php'  => 'Timesheet/timesheet.php',
);
foreach ($drops as $old => $new) { $done['deact'] += deact($conn, $old); $done['redir'] += redirect_add($conn, $old, $new); }

/* ── ⑦ الغريبُ الحيُّ في غير قائمة مالكه — يُطفأ من الأجنبي وحدَه ────────── */
$csv = dirname(__DIR__) . '/docs/nav02/matrix_delta.csv';
if (is_file($csv)) {
    foreach (array_slice(file($csv), 1) as $line) {
        $c = str_getcsv($line);
        if (($c[3] ?? '') !== 'strange_unresolved') continue;
        if (!preg_match('/غيرِ مالكه:\s*([0-9،,\s]+)/u', $c[4] ?? '', $m)) continue;
        $foreign = array_filter(array_map('intval', preg_split('/[،,\s]+/u', $m[1])));
        if ($foreign) $done['deact'] += deact($conn, trim($c[0]), $foreign);
    }
}

/* ── ⑧ الصيانةُ: «بلاغاتُ إدارتي» في ② العمل اليومي لا ⑤ (مصفوفة المالك 248) ── */
$g2mnt = mysqli_query($conn, "SELECT id FROM link_groups WHERE group_code='g2' AND owner_role_id=7 LIMIT 1");
if ($g2mnt && ($g = mysqli_fetch_assoc($g2mnt))) {
    mysqli_query($conn, "UPDATE nav_items SET group_id=" . intval($g['id']) . ", door='DAILY', sort_order=2
                         WHERE role_id=7 AND route='Tickets/dept_tickets.php'");
}

/* ── ⑨ الإحياءُ عند المالك: «إبقاءٌ مع تجميع» يجب أن يعيش في قائمة مالكه ── */
// المصفوفةُ تُقرأ مباشرةً: صفوفُ الإبقاء الغائبةُ عن الحي تُحيا (أو تُنشأ)
// في مجموعة مالكها المسمّاة (العمود ⑧) لدوره الأول.
require_once __DIR__ . '/nav02_matrix_read.php'; // قارئُ المصفوفة المشترك
$groupNo = function ($g) { // «⑤ المتابعة والاستثناءات» → g5
    $map = array('①'=>1,'②'=>2,'③'=>3,'④'=>4,'⑤'=>5,'⑥'=>6,'⑦'=>7,'⑧'=>8);
    $ch = mb_substr(trim($g), 0, 1);
    return isset($map[$ch]) ? 'g' . $map[$ch] : 'g3';
};
$doorOf = array('g1'=>'HOME','g2'=>'DAILY','g3'=>'REC','g4'=>'APPR','g5'=>'APPR','g6'=>'APPR','g7'=>'REP','g8'=>'SET');
$live2 = array();
$r = mysqli_query($conn, "SELECT route, GROUP_CONCAT(role_id) roles FROM nav_items WHERE active=1 GROUP BY route");
while ($x = mysqli_fetch_assoc($r)) $live2[strtolower(trim($x['route']))] = $x['roles'];
$revived = 0;
foreach (nav02_matrix_rows() as $row) {
    $action = trim($row[11] ?? '');
    if (mb_strpos($action, 'إبقاء') === false) continue;
    if (trim($row[16] ?? '') !== 'نعم') continue;
    $route = trim($row[5] ?? '');
    if ($route === '' || isset($live2[strtolower($route)])) continue;
    $roles = dept_roles_apply(trim($row[3] ?? ''));
    if (!$roles) continue;
    $role = $roles[0];
    $gc = $groupNo($row[8] ?? '');
    $g = mysqli_query($conn, "SELECT id FROM link_groups WHERE group_code='" . $gc . "' AND owner_role_id=" . intval($role) . " LIMIT 1");
    if (!$g || !($gr = mysqli_fetch_assoc($g))) continue;
    $e = mysqli_real_escape_string($conn, $route);
    // إن وُجد صفٌّ مطفأٌ للمالك يُحيا؛ وإلا يُنشأ
    mysqli_query($conn, "UPDATE nav_items SET active=1, group_id=" . intval($gr['id']) . ", door='" . $doorOf[$gc] . "'
                         WHERE route='$e' AND role_id=" . intval($role) . " LIMIT 1");
    if (mysqli_affected_rows($conn) === 0) {
        $name = mysqli_real_escape_string($conn, trim($row[2] ?: $row[1] ?: basename($route, '.php')));
        mysqli_query($conn, "INSERT INTO nav_items (role_id, door, group_id, label_ar, route, icon, sort_order, active)
                             SELECT " . intval($role) . ", '" . $doorOf[$gc] . "', " . intval($gr['id']) . ", '$name', '$e', 'fa fa-link', 50, 1
                             WHERE NOT EXISTS (SELECT 1 FROM nav_items WHERE route='$e' AND role_id=" . intval($role) . ")");
    }
    if (mysqli_affected_rows($conn) > 0) $revived++;
}
echo "أُحيي عند مالكه: $revived\n";

echo "أُطفئ: {$done['deact']} رابطًا · أُضيف: {$done['redir']} تحويلًا\n";
$r = mysqli_query($conn, "SELECT COUNT(*) c FROM nav_items WHERE active=1");
echo "الروابطُ الحيةُ الآن: " . mysqli_fetch_assoc($r)['c'] . "\n";
