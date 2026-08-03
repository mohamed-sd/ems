<?php
/**
 * Operations/operations_room.php — غرفةُ عمليات التشغيل (M-27 · الشاشة 193)
 * ───────────────────────────────────────────────────────────────────────────
 * SPEC-03 بطاقة 1: الشاشةُ الأم بتبويبات اليوم الأربعة — «قراءةٌ وقفز؛
 * لا أثرَ مباشر» — وتستوعب شاشةَ التشغيل القديمة بRedirect بعدّاد.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../app/Services/Operations/OperationsBoardService.php';
require_once __DIR__ . '/../includes/screen_contract.php';

use App\Services\Operations\OperationsBoardService as OBS;

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$is_super   = (strval($_SESSION['user']['role'] ?? '') === '-1');
if (!$is_super && $company_id <= 0) { header("Location: ../login.php"); exit(); }

$date = preg_match('/^\d{4}-\d{2}-\d{2}$/', strval($_GET['date'] ?? '')) ? $_GET['date'] : date('Y-m-d');
$tab  = in_array(strval($_GET['tab'] ?? '1'), array('1', '2', '3', '4'), true) ? strval($_GET['tab'] ?? '1') : '1';

$page_title = 'إيكوبيشن | غرفة عمليات التشغيل';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'غرفة عمليات التشغيل'; $header_icon = 'fa fa-tower-control';
    $header_actions = array(
        array('href' => 'distribution_space.php', 'icon' => 'fa fa-table-cells', 'label' => 'مساحة التوزيع'),
        array('href' => '../Fleet/readiness_board.php', 'icon' => 'fa fa-heart-pulse', 'label' => 'لوحة الجاهزية'),
    );
    include('../includes/page_header.php');
    ems_screen_about('الشاشةُ الأم للتشغيل بتبويبات اليوم الأربعة: من رفع ومن تأخر · '
        . 'تايم شيتات اليوم · صندوقُ الاعتماد بعدّاده · والالتزامُ بالوتيرة اللازمة. '
        . 'قراءةٌ وقفزٌ — لا أثرَ ماليًّا من هنا؛ الأثرُ يقع عند اكتمال سلسلة الوحدة.',
        array('اختر التاريخ', 'طالِب المواقعَ المتأخرة', 'اقفز للإدخال أو الاعتماد في بيته'));
    ?>

    <div class="card"><div class="card-body" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <form method="get" style="display:flex;gap:8px;align-items:center">
            <input type="hidden" name="tab" value="<?php echo $tab; ?>">
            <label>اليوم</label><input type="date" name="date" value="<?php echo htmlspecialchars($date); ?>">
            <button type="submit" class="btn-save">اعرض</button>
        </form>
        <span style="margin-inline-start:auto">
        <?php foreach (array('1' => '① الورديات واليوم', '2' => '② التايم شيت',
                             '3' => '③ الاعتماد', '4' => '④ الالتزام') as $t => $lbl): ?>
            <a class="btn btn-sm" style="border:1px solid #ddd;border-radius:6px;padding:4px 10px;margin:0 2px;<?php
                echo $t === $tab ? 'background:#e2b93b;font-weight:800' : ''; ?>"
               href="?tab=<?php echo $t; ?>&date=<?php echo htmlspecialchars($date); ?>"><?php echo $lbl; ?></a>
        <?php endforeach; ?></span>
    </div></div>

    <?php if ($tab === '1'):
        $sites = OBS::sitesToday($conn, $company_id, $date); ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-location-dot"></i>
        مواقعُ اليوم — مَن رفع ومَن تأخر</h5></div>
    <div class="card-body">
        <?php if (!$sites): ems_state_empty('لا مواقعَ نشطةً في آخر أسبوعين', 'افتح التايم شيت', '?tab=2'); else: ?>
        <div class="table-container"><table class="alltables display nowrap" style="width:100%" data-no-dt="1">
            <thead><tr><th>المشروع/الموقع</th><th>رفعُ اليوم</th><th>آخرُ تحديث</th><th></th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              </tr></thead>
            <tbody>
            <?php foreach ($sites as $s): ?>
                <tr<?php echo $s['late'] ? ' style="background:#fff3f0"' : ''; ?>>
                    <td><strong><?php echo htmlspecialchars($s['project']); ?></strong></td>
                    <td><?php echo $s['late']
                        ? "<span class='badge badge-danger'>⚠ لم يرفع اليوم</span>"
                        : ("<span class='badge badge-success'>" . intval($s['raised_today']) . " وحدة</span>"); ?></td>
                    <td><small><?php echo htmlspecialchars((string)($s['last_at'] ?? '—')); ?></small></td>
                    <td><?php if ($s['late']): ?>
                        <a class="btn-save" href="../Tickets/ticket_form.php?subject=<?php
                            echo rawurlencode('مطالبة رفع الوحدات — ' . $s['project']); ?>">مطالبةٌ فورية ▸</a>
                    <?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div></div>

    <?php elseif ($tab === '2'):
        $ts = OBS::timesheetToday($conn, $company_id, $date); ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i>
        تايم شيتات اليوم
        <?php foreach ($ts['counts'] as $st => $n): ?>
            <span class="badge badge-secondary"><?php echo htmlspecialchars($st . ': ' . $n); ?></span>
        <?php endforeach; ?></h5></div>
    <div class="card-body">
        <?php if (!$ts['rows']): ems_state_empty('لا تايم شيت لهذا اليوم بعدُ', 'افتح الإدخال', '../Operations/units.php'); else: ?>
        <div class="table-container"><table class="alltables display nowrap" style="width:100%" data-no-dt="1">
            <thead><tr><th>الرقم</th><th>المعدة</th><th>الوردية</th><th>الوحدة</th>
                <th>الكمية</th><th>الحالة</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($ts['rows'] as $x): ?>
                <tr><td><?php echo htmlspecialchars((string)$x['entry_no']); ?></td>
                    <td><?php echo htmlspecialchars((string)($x['equip_name'] ?? ('#' . $x['equipment_id']))); ?></td>
                    <td><?php echo htmlspecialchars((string)$x['shift']); ?></td>
                    <td><?php echo htmlspecialchars((string)$x['unit_type']); ?></td>
                    <td><?php echo htmlspecialchars((string)$x['qty']); ?></td>
                    <td><span class="badge badge-secondary"><?php echo htmlspecialchars((string)$x['state']); ?></span></td>
                    <td><a href="../Operations/units.php?open=<?php echo intval($x['id']); ?>">افتح ▸</a></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div></div>

    <?php elseif ($tab === '3'):
        $box = OBS::approvalBox($conn, $company_id); ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-stamp"></i>
        صندوقُ الاعتماد بعدّاده — والقرارُ في بيته</h5></div>
    <div class="card-body">
        <?php if (!$box): ems_state_empty('لا وحداتٍ تنتظر اعتمادًا — نظيف ✨'); else: ?>
        <div style="display:flex;gap:14px;flex-wrap:wrap">
            <?php foreach ($box as $st => $n): ?>
                <a class="badge badge-warning" style="font-size:15px;padding:10px 16px;text-decoration:none"
                   href="../Operations/units.php?state=<?php echo rawurlencode($st); ?>">
                    <?php echo htmlspecialchars($st); ?>: <strong><?php echo intval($n); ?></strong> ▸</a>
            <?php endforeach; ?>
        </div>
        <p style="color:#666;margin-top:10px">الثلاثيةُ الموحّدة وحارسُ الطاقة في شاشة الاعتماد نفسِها —
            هنا العدّادُ والقفز.</p>
        <?php endif; ?>
    </div></div>

    <?php else:
        $com = OBS::commitmentTab($conn, $company_id); ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-scale-balanced"></i>
        الالتزامُ هذا الشهر — الفجوةُ حتى اليوم والوتيرةُ اللازمة</h5></div>
    <div class="card-body">
        <?php if (!$com): ems_state_empty('لا خططَ شهريةً لهذا الشهر', 'افتح الخطة الشهرية', '../Contracts/contract_monthly_plan.php'); else: ?>
        <div class="table-container"><table class="alltables display nowrap" style="width:100%" data-no-dt="1">
            <thead><tr><th>العقد</th><th>الملتزَم (الشهر)</th><th>المنفَّذ</th>
                <th>الفجوة حتى اليوم</th><th>الوتيرة اللازمة/يوم</th><th>المسؤول</th></tr></thead>
            <tbody>
            <?php foreach ($com as $c): ?>
                <tr<?php echo $c['gap_to_date'] < 0 ? ' style="background:#fff3f0"' : ''; ?>>
                    <td><a href="../Contracts/commercial_board.php">عقد #<?php echo intval($c['contract_id']); ?> ▸</a></td>
                    <td><?php echo htmlspecialchars((string)$c['planned']); ?></td>
                    <td><?php echo htmlspecialchars((string)$c['executed']); ?></td>
                    <td><strong style="<?php echo $c['gap_to_date'] < 0 ? 'color:#c00' : 'color:#0a7'; ?>">
                        <?php echo htmlspecialchars((string)$c['gap_to_date']); ?></strong></td>
                    <td><?php echo htmlspecialchars((string)$c['required_pace']); ?></td>
                    <td><?php echo htmlspecialchars((string)$c['owner']); ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div></div>
    <?php endif; ?>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
