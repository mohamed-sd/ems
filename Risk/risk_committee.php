<?php
/**
 * Risk/risk_committee.php — لجنة المخاطر (M-16 المرحلة ٨ · الشاشة ١٤)
 * ─────────────────────────────────────────────────────────────────────────
 * مخرَجُ المرحلةِ الثامنة: «محضرُ لجنةٍ وشهيةٌ معتمدة» بمعتمِدها الرئيسِ التنفيذي.
 * والمحضرُ مستندٌ يُعتمد فيحمل الأعمدةَ الحاكمةَ السبعةَ — والمعتمدُ منه لا يُعدَّل
 * ولا يُحذف: التصحيحُ محضرٌ جديدٌ يشير إلى الأصلِ بالمرجعِ الأب (RK · §9-4).
 */
require_once __DIR__ . '/_risk_common.php';
$__pp = risk_guard_screen($conn, $is_super_admin);

require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes($__pp);

$canAdd = $is_super_admin || !empty($__pp['can_add']);
$isCeo = $is_super_admin || $RISK_AUTHORITY === 'ceo';

/* إنشاءُ محضرٍ واعتمادُه — كتابةٌ مستنديةٌ لا فعلَ مجالٍ في القاموس، فتُنفَّذ
   هنا بحارسِها: الإنشاءُ لمن يملك can_add والاعتمادُ للرئيسِ حصرًا. */
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cmt_do'])) {
    if (function_exists('verify_csrf_token') && !verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $flash = '✘ RSK-CSRF: رمز الجلسة غير صالح — حدّث الصفحة';
    } elseif ($_POST['cmt_do'] === 'create' && $canAdd) {
        require_once __DIR__ . '/../app/Services/Risk/RiskService.php';
        $code = \App\Services\Risk\RiskService::nextCode($conn, $company_id, 'risk_committee', 'minute_code', 'CMT-', 5);
        $st = $conn->prepare('INSERT INTO risk_committee
            (company_id, minute_code, meeting_date, cycle_ar, attendees_ar, agenda_ar, resolutions_ar,
             risks_reviewed, parent_ref, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?)');
        $md = (string) $_POST['meeting_date']; $cy = (string) ($_POST['cycle_ar'] ?? 'ربع سنوي');
        $at = (string) ($_POST['attendees_ar'] ?? ''); $ag = (string) ($_POST['agenda_ar'] ?? '');
        $rs = (string) ($_POST['resolutions_ar'] ?? ''); $rv = (int) ($_POST['risks_reviewed'] ?? 0);
        $prev = (string) ($conn->query("SELECT COALESCE(MAX(minute_code),'') c FROM risk_committee WHERE company_id = {$company_id}")->fetch_assoc()['c']);
        $st->bind_param('issssssisi', $company_id, $code, $md, $cy, $at, $ag, $rs, $rv, $prev, $uid);
        $st->execute(); $st->close();
        $flash = '✔ أُنشئ المحضر ' . $code . ' مسوَّدةً — والاعتمادُ للرئيس التنفيذي';
    } elseif ($_POST['cmt_do'] === 'approve') {
        if (!$isCeo) {
            $flash = '✘ RSK-403: اعتمادُ محضرِ اللجنةِ للرئيسِ التنفيذيِّ حصرًا (المرحلة ٨)';
        } else {
            $mid = (int) $_POST['minute_id'];
            $st = $conn->prepare("UPDATE risk_committee
                                     SET state = 'approved', approved_by = ?, approved_at = NOW(),
                                         authority_ref = 'الرئيس التنفيذي — المرحلة ٨'
                                   WHERE id = ? AND company_id = ? AND state = 'draft'");
            $st->bind_param('iii', $uid, $mid, $company_id);
            $st->execute();
            $flash = $conn->affected_rows > 0
                ? '✔ اعتُمد المحضر — والمعتمدُ لا يُعدَّل بعدها'
                : '✘ RSK-409: المعتمدُ لا يُعتمد مرتين';
            $st->close();
        }
    } else {
        $flash = '✘ RSK-403: لا صلاحية';
    }
}

$rows = array();
$res = $conn->query("SELECT c.*, u.name creator, ap.name approver,
                            (SELECT COUNT(*) FROM risk_committee_items i
                              WHERE i.company_id = c.company_id AND i.minute_id = c.id) items
                       FROM risk_committee c
                       LEFT JOIN users u ON u.id = c.created_by
                       LEFT JOIN users ap ON ap.id = c.approved_by
                      WHERE c.company_id = {$company_id}
                      ORDER BY c.meeting_date DESC, c.id DESC LIMIT 200");
while ($x = $res->fetch_assoc()) { $rows[] = $x; }

$draft = 0;
foreach ($rows as $x) { if ($x['state'] === 'draft') { $draft++; } }

$page_title = 'إيكوبيشن | لجنة المخاطر';
include '../inheader.php';
include '../insidebar.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'لجنة المخاطر';
    $header_icon = 'fas fa-users-rectangle';
    $header_actions = $canAdd
        ? array(array('id' => 'cmtNewBtn', 'class' => 'add-btn', 'icon' => 'fas fa-plus', 'label' => 'محضر جديد'))
        : array();
    $header_back = array();
    $header_context = array(
        'المقام' => 'محاضر اللجنة (المرحلة ٨)',
        'المحاضر' => count($rows),
        'مسوَّدات بانتظار الاعتماد' => $draft,
        'صفتك' => $isCeo ? 'الرئيس التنفيذي — تعتمد' : 'قراءة وإنشاء مسوَّدة',
    );
    include('../includes/page_header.php');
    ems_screen_about(
        'محضرُ اللجنةِ مخرَجُ المرحلةِ الثامنة — يحمل الحاضرينَ بصفاتهم وجدولَ الأعمالِ والقرارات. '
        . 'ويعتمده الرئيسُ التنفيذيُّ، والمعتمدُ لا يُعدَّل ولا يُحذف.',
        array('التصحيحُ محضرٌ جديدٌ يشير إلى الأصلِ بالمرجعِ الأب — لا تعديلٌ للمعتمد',
              'الشهيةُ المعتمدةُ في المحضرِ تُغيّر حدودَ التصعيدِ في المجالاتِ كلِّها'));
    if ($flash !== '') {
        echo '<div class="ems-card" style="padding:10px;margin:8px 0;font-size:.85rem">' . htmlspecialchars($flash) . '</div>';
    }
    ?>

    <?php if (empty($rows)): ?>
    <div class="ems-card" id="cmtEmpty"></div>
    <script>document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('cmtEmpty').appendChild(EmsUI.emptyState({
            reason: 'لا محاضر لجنة بعد — والمرحلةُ الثامنةُ لا تكتمل بلا محضرٍ ومعتمِدٍ يشهد'
        }));
    });</script>
    <?php else: ?>
    <div class="card"><div class="card-body table-responsive">
        <table class="table table-striped" style="width:100%">
            <thead><tr>
                <th>الرمز</th><th>تاريخ الاجتماع</th><th>الدورية</th><th>الحاضرون</th>
                <th>مخاطر روجعت</th><th>البنود</th><th>القرارات</th>
                <th>المُنشئ</th><th>تاريخ الإنشاء</th><th>المعتمِد</th><th>تاريخ الاعتماد</th>
                <th>مرجع التفويض</th><th>المرجع الأب</th><th>الحالة</th>
                <?php if ($isCeo): ?><th>الفعل</th><?php endif; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $x): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($x['minute_code']); ?></strong></td>
                    <td><?php echo htmlspecialchars((string) $x['meeting_date']); ?></td>
                    <td><?php echo htmlspecialchars((string) $x['cycle_ar']); ?></td>
                    <td style="font-size:.76rem;max-width:200px"><?php echo htmlspecialchars(mb_substr((string) $x['attendees_ar'], 0, 90) ?: '—'); ?></td>
                    <td><?php echo (int) $x['risks_reviewed']; ?></td>
                    <td><?php echo (int) $x['items']; ?></td>
                    <td style="font-size:.76rem;max-width:240px"><?php echo htmlspecialchars(mb_substr((string) $x['resolutions_ar'], 0, 110) ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars((string) $x['creator'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars((string) $x['created_at']); ?></td>
                    <td><?php echo htmlspecialchars((string) $x['approver'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars((string) $x['approved_at'] ?: '—'); ?></td>
                    <td style="font-size:.74rem"><?php echo htmlspecialchars((string) $x['authority_ref'] ?: '—'); ?></td>
                    <td style="font-size:.74rem"><?php echo htmlspecialchars((string) $x['parent_ref'] ?: '—'); ?></td>
                    <td><span class="badge <?php echo $x['state'] === 'approved' ? 'badge-success' : 'badge-warning'; ?>">
                        <?php echo $x['state'] === 'approved' ? 'معتمد' : 'مسوَّدة'; ?></span></td>
                    <?php if ($isCeo): ?>
                    <td><?php if ($x['state'] === 'draft'): ?>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="cmt_do" value="approve">
                            <input type="hidden" name="minute_id" value="<?php echo (int) $x['id']; ?>">
                            <button class="btn btn-sm btn-outline-success" type="submit">اعتماد</button>
                        </form>
                        <?php else: ?>—<?php endif; ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
    <?php endif; ?>

    <?php if ($canAdd): ?>
    <div class="card" id="cmtNewCard" style="display:none;margin-top:16px"><div class="card-body">
        <h5>محضر لجنة جديد (مسوَّدة)</h5>
        <form method="post" class="allforms">
            <input type="hidden" name="cmt_do" value="create">
            <div class="row">
                <div class="col-md-3"><label>تاريخ الاجتماع *<input name="meeting_date" type="date" class="form-control" required></label></div>
                <div class="col-md-3"><label>الدورية<select name="cycle_ar" class="form-control">
                    <option>ربع سنوي</option><option>نصف سنوي</option><option>شهري</option><option>استثنائي</option>
                </select></label></div>
                <div class="col-md-3"><label>مخاطر روجعت<input name="risks_reviewed" type="number" min="0" value="0" class="form-control"></label></div>
                <div class="col-md-12"><label>الحاضرون بصفاتهم<textarea name="attendees_ar" class="form-control" rows="2"></textarea></label></div>
                <div class="col-md-6"><label>جدول الأعمال<textarea name="agenda_ar" class="form-control" rows="3"></textarea></label></div>
                <div class="col-md-6"><label>القرارات<textarea name="resolutions_ar" class="form-control" rows="3"></textarea></label></div>
            </div>
            <button type="submit" class="ems-btn-primary">إنشاء المسوَّدة</button>
            <span style="margin-inline-start:10px;font-size:.78rem;opacity:.75">الاعتمادُ للرئيسِ التنفيذيِّ حصرًا</span>
        </form>
    </div></div>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('cmtNewBtn');
        if (btn) { btn.addEventListener('click', function () {
            var c = document.getElementById('cmtNewCard');
            c.style.display = c.style.display === 'none' ? '' : 'none';
        }); }
    });
    </script>
    <?php endif; ?>
</div>
</body>
</html>
