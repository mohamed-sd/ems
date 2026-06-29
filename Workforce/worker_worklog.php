<?php
/** EQUIP-OPE-S04 — 8.9 السجل التشغيلي المجمَّع (للقراءة فقط) + مؤشّرات.
 * يقرأ Views محسوبةً (v_worker_worklog + v_worker_presence). صفر كتابة، صفر لمسٍ للقائم. */
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../app/Services/Workforce/EventService.php';
require_once __DIR__ . '/../app/Services/Workforce/RotationService.php';
require_once __DIR__ . '/../app/Services/Workforce/ViewModal.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php?msg=بيئة+شركة+غير+صالحة+❌"); exit(); }

$pp = check_page_permissions($conn, 'Workforce/worker_worklog.php');
if (!$pp['can_view']) { header("Location: ../login.php?msg=لا+توجد+صلاحية+❌"); exit(); }
$scope_sql = $is_super_admin ? "" : " AND wp.company_id = ".intval($company_id)." ";

// محرّكا الأحداث والتناوب (نقطة الحقيقة الواحدة): حوافز/جزاءات معتمدة + من اقترب تدويره.
$company_scope = $is_super_admin ? null : $company_id;
$events_map    = ems_events_map($conn, $company_scope);          // [worker_id => incentive/penalty معتمد]
$rotation_due  = ems_rotation_due_soon($conn, $company_scope, 14); // العقود التي اقترب تدويرها (14 يوماً)

$page_title="إيكوبيشن | السجل التشغيلي"; include '../inheader.php'; include '../insidebar.php';
?>
<div class="main">
    <?php $header_title='السجل التشغيلي المجمَّع'; $header_icon='fas fa-clock-rotate-left'; $header_actions=array();
    $header_back=array('href'=>'worker_register.php','class'=>'','icon'=>'fas fa-arrow-right','label'=>'سجل العامل');
    include('../includes/page_header.php'); ?>

    <?php
    // مؤشّرات سريعة
    $kpi = ['workers'=>0,'allocated'=>0,'on_leave'=>0,'expired_critical'=>0];
    $q1=mysqli_query($conn,"SELECT COUNT(*) c FROM employees wp WHERE 1=1 $scope_sql"); if($q1){$kpi['workers']=intval(mysqli_fetch_assoc($q1)['c']);}
    $q2=mysqli_query($conn,"SELECT COUNT(DISTINCT a.employee_id) c FROM equipment_drivers a JOIN employees wp ON wp.id=a.employee_id WHERE a.status=1 $scope_sql"); if($q2){$kpi['allocated']=intval(mysqli_fetch_assoc($q2)['c']);}
    $q3=mysqli_query($conn,"SELECT COUNT(DISTINCT la.employee_id) c FROM worker_leave_absence la JOIN employees wp ON wp.id=la.employee_id WHERE la.state IN ('معتمد','مفتوح','مُغطًّى') $scope_sql"); if($q3){$kpi['on_leave']=intval(mysqli_fetch_assoc($q3)['c']);}
    $q4=mysqli_query($conn,"SELECT COUNT(DISTINCT q.employee_id) c FROM worker_qualification q JOIN employees wp ON wp.id=q.employee_id WHERE q.is_critical=1 AND q.expiry_date IS NOT NULL AND q.expiry_date < CURDATE() $scope_sql"); if($q4){$kpi['expired_critical']=intval(mysqli_fetch_assoc($q4)['c']);}
    ?>
    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin:14px 0;">
        <div class="allforms" style="padding:14px;text-align:center;"><div style="font-size:1.6rem;font-weight:700;"><?= $kpi['workers'] ?></div><div>الموظفون</div></div>
        <div class="allforms" style="padding:14px;text-align:center;"><div style="font-size:1.6rem;font-weight:700;"><?= $kpi['allocated'] ?></div><div>مخصَّصون (نشط)</div></div>
        <div class="allforms" style="padding:14px;text-align:center;"><div style="font-size:1.6rem;font-weight:700;"><?= $kpi['on_leave'] ?></div><div>في إجازة/غياب</div></div>
        <div class="allforms" style="padding:14px;text-align:center;"><div style="font-size:1.6rem;font-weight:700;color:#c0392b;"><?= $kpi['expired_critical'] ?></div><div>اعتماد حرج منتهٍ</div></div>
        <div class="allforms" style="padding:14px;text-align:center;"><div style="font-size:1.6rem;font-weight:700;color:#b9770e;"><?= count($rotation_due) ?></div><div>اقترب تدويرهم (14 يوم)</div></div>
    </div>

    <?php if (!empty($rotation_due)): ?>
    <div class="allforms" style="margin:0 0 14px;">
        <div class="card-header"><h5><i class="fas fa-rotate"></i> عقودٌ اقترب موعد تدويرها (محرّك التناوب)</h5></div>
        <table class="data-table" style="width:100%;">
            <thead><tr><th>العامل</th><th>كود العقد</th><th>النمط</th><th>الاستحقاق القادم</th><th>المتبقّي (يوم)</th></tr></thead><tbody>
            <?php foreach ($rotation_due as $rd): $dl = intval($rd['days_left']); ?>
                <tr><td><strong><?= htmlspecialchars($rd['worker_name'] ?: '-') ?></strong></td>
                    <td><code><?= htmlspecialchars($rd['code'] ?: ('C-'.$rd['contract_id'])) ?></code></td>
                    <td><?= htmlspecialchars($rd['rotation_pattern']) ?></td>
                    <td><?= htmlspecialchars($rd['next_rotation_date']) ?></td>
                    <td><span class="status-pill <?= $dl < 0 ? 'status-inactive' : 'status-warning' ?>"><?= $dl ?></span></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div class="table-wrap"><table class="data-table" style="width:100%;">
        <thead><tr><th>عرض</th><th>#</th><th>الموظف</th><th>الفئة</th><th>الحالة</th><th>الحالة الميدانية</th><th>العمليات</th><th>ساعات مؤهَّلة</th><th>إجازات/غياب</th><th>تحرّكات</th><th>تقييمات</th><th>حوافز (معتمدة)</th><th>جزاءات (معتمدة)</th></tr></thead><tbody>
        <?php
        $sql="SELECT wl.*, pr.presence_state
              FROM v_worker_worklog wl
              JOIN employees wp ON wp.id = wl.employee_id
              LEFT JOIN v_worker_presence pr ON pr.employee_id = wl.employee_id
              WHERE 1=1 $scope_sql ORDER BY wl.employee_id DESC";
        $list=mysqli_query($conn,$sql); $i=1; $WF_VIEW = [];
        if($list){ while($r=mysqli_fetch_assoc($list)):
            $ev0 = $events_map[intval($r['employee_id'])] ?? ['incentive'=>0,'penalty'=>0];
            $WF_VIEW[$r['employee_id']] = ems_wf_view_payload('سجل الموظف التشغيلي المجمّع', 'fas fa-clock-rotate-left', [
                ems_wf_field('الموظف', $r['worker_name'] ?: '-', 'fas fa-user', ['size' => 'lg']),
                ems_wf_field('الفئة', $r['worker_category'] ?: '-', 'fas fa-layer-group'),
                ems_wf_field('الحالة', $r['worker_state'] ?: '-', 'fas fa-diagram-project', ['type' => 'status']),
                ems_wf_field('الحالة الميدانية', $r['presence_state'] ?: '-', 'fas fa-location-dot'),
                ems_wf_field('عدد العمليات', intval($r['operations_count']), 'fas fa-gears'),
                ems_wf_field('ساعات مؤهَّلة', number_format(floatval($r['total_billable_hours']), 1), 'fas fa-clock'),
                ems_wf_field('إجازات/غياب', intval($r['leave_absence_count']), 'fas fa-plane-departure'),
                ems_wf_field('تحرّكات', intval($r['movement_count']), 'fas fa-route'),
                ems_wf_field('تقييمات', intval($r['evaluation_count']), 'fas fa-star-half-stroke'),
                ems_wf_field('حوافز (معتمدة)', number_format(floatval($ev0['incentive']), 2), 'fas fa-gift'),
                ems_wf_field('جزاءات (معتمدة)', number_format(floatval($ev0['penalty']), 2), 'fas fa-gavel'),
            ]); ?>
            <tr><td><?= ems_wf_view_button($r['employee_id']) ?></td><td><?= $i++ ?></td><td><strong><?= htmlspecialchars($r['worker_name'] ?: '-') ?></strong></td>
            <td><?php if(!empty($r['worker_category'])): ?><span class="badge badge-info"><?= htmlspecialchars($r['worker_category']) ?></span><?php else: ?>-<?php endif; ?></td>
            <td><?= htmlspecialchars($r['worker_state'] ?: '-') ?></td>
            <td><span class="status-pill status-warning"><?= htmlspecialchars($r['presence_state'] ?: '-') ?></span></td>
            <td><?= intval($r['operations_count']) ?></td><td><?= number_format(floatval($r['total_billable_hours']),1) ?></td>
            <td><?= intval($r['leave_absence_count']) ?></td><td><?= intval($r['movement_count']) ?></td>
            <?php // محرّك الأحداث: حوافز/جزاءات معتمدةٌ فقط (state معتمد/مرحّل) بدل إجماليّات كل الحالات.
            $ev = $events_map[intval($r['employee_id'])] ?? ['incentive'=>0,'penalty'=>0]; ?>
            <td><?= intval($r['evaluation_count']) ?></td><td><?= number_format(floatval($ev['incentive']),2) ?></td>
            <td><?= number_format(floatval($ev['penalty']),2) ?></td></tr>
        <?php endwhile; } if(!$list||$i===1): ?><tr><td colspan="13" style="text-align:center;color:#888;padding:18px;">لا توجد بياناتٌ بعد (طبّق التهجيرات وأضف موظفين).</td></tr><?php endif; ?>
        </tbody></table></div>
</div>
<?php ems_wf_view_modal($WF_VIEW); ?>
</body></html>
