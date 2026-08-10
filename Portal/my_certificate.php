<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
// شواهد المتطلبات (AC-E06-03 · موجة ٣): SCN-001 · SCN-002 · SCN-003 · SCN-004 · SCN-005 · SCN-006 · SCN-007 · SCN-008 · SCN-009 · SCN-010 · SCN-011 · SCN-012 · SCN-013 · SCN-014 · SCN-015 · SCN-016 · SCN-017 · SCN-018
/**
 * Portal/my_certificate.php — شهادةُ الإنجاز (H-18 · الشاشة 190)
 * ───────────────────────────────────────────────────────────────────────────
 * USR-01 §8-⑦: «طباعة: شهادةٌ رسميةٌ بترويستها ورقمِها ورمزِ تحققها — بلا
 * أزرارِ تفاعلٍ داخلها» · §7: «تُولَّد من الأرقام المقاسة ولا تُصدَر لفترةٍ
 * لم تُقفل أو لتقييمٍ لم يُعتمد».
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../app/Services/Portal/EvaluationService.php';
require_once __DIR__ . '/../app/Services/Portal/CapacityService.php';

use App\Services\Portal\EvaluationService as EVS;
use App\Services\Portal\CapacityService as CAP;

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid        = intval($_SESSION['user']['id'] ?? 0);
$is_super   = (strval($_SESSION['user']['role'] ?? '') === '-1');
if (!$is_super && $company_id <= 0) { header("Location: ../login.php"); exit(); }

$gate = $is_super ? ems_tenant_db()->forAllTenants('my certificate super') : ems_tenant_db();
$redirect = function ($msg) { ems_gov_flash_redirect('my_certificate.php', $msg, 'GOV-INFO-200', ''); exit(); };

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ct_action'] ?? '') === 'issue') {
    $evalId = intval($_POST['eval_id'] ?? 0);
    $ev = EVS::head($gate, $evalId);
    $snapId = 0;
    if ($ev) {
        try {
            $s = $gate->selectOne('achievement_snapshots', array('where' => array(
                'capacity_id' => intval($ev['capacity_id']),
                'period_from' => strval($ev['period_from']), 'period_to' => strval($ev['period_to']))));
            $snapId = $s ? intval($s['id']) : 0;
        } catch (\Throwable $t) { $snapId = 0; }
    }
    $r = EVS::issueCertificate($conn, $gate, $company_id, $snapId, $evalId, $uid);
    $redirect($r['ok'] ? ('صدرت الشهادة ' . $r['serial_no'] . ' برمز تحقق ' . $r['verify_code'] . ' ✅')
                       : ($r['code'] . ' — ' . $r['reason'] . ' ❌'));
}

// شهاداتي — عبر صفاتي
$myCapIds = array();
foreach (CAP::activeOf($conn, $gate, $uid) as $c) { $myCapIds[] = intval($c['id']); }
$certs = array();
if ($myCapIds) {
    $in = implode(',', $myCapIds);
    $r = $conn->query("SELECT c.*, s.period_from, s.period_to, s.metrics_json, s.capacity_id
                         FROM achievement_certificates c
                         JOIN achievement_snapshots s ON s.id = c.snap_id
                        WHERE c.company_id={$company_id} AND s.capacity_id IN ({$in})
                        ORDER BY c.id DESC");
    while ($r && ($row = $r->fetch_assoc())) { $certs[] = $row; }
}

// تقييماتٌ معتمدةٌ بلا شهادة — مرشحةٌ للإصدار
$approved = array();
if ($myCapIds) {
    $in = implode(',', $myCapIds);
    $r = $conn->query("SELECT e.* FROM evaluations e
                        WHERE e.company_id={$company_id} AND e.capacity_id IN ({$in})
                          AND e.state='Approved'
                          AND NOT EXISTS (SELECT 1 FROM achievement_certificates c WHERE c.eval_id = e.id)");
    while ($r && ($row = $r->fetch_assoc())) { $approved[] = $row; }
}

$verify = strval($_GET['verify'] ?? '');
$verified = $verify !== '' ? EVS::verify($gate, $verify) : null;

$open = intval($_GET['open'] ?? 0);
$openCert = null;
foreach ($certs as $c) { if (intval($c['id']) === $open) { $openCert = $c; } }

$page_title = 'إيكوبيشن | شهادة الإنجاز';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'شهادة الإنجاز'; $header_icon = 'fa fa-certificate';
    $header_actions = array();
    include('../includes/page_header.php');
    if (isset($_GET['msg'])) { echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>'; }
    ?>

    <div class="card"><div class="card-body">
        <p style="color:#666"><strong>الشهادةُ تُولَّد من الأرقام المقاسة لا من كتابةٍ حرة</strong> —
            ولا تُصدَر لفترةٍ لم تُقفل بلقطةٍ أو لتقييمٍ لم يُعتمد، ولا تُصدَر مرتين،
            ولكلِّ شهادةٍ رقمٌ تسلسليٌّ ورمزُ تحققٍ يمنع التزوير.</p>
        <form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <strong>تحقّق من شهادة:</strong>
            <input type="text" name="verify" placeholder="رمز التحقق" value="<?php echo htmlspecialchars($verify); ?>" aria-label="رمز التحقق">
            <button type="submit" class="btn-save">تحقّق</button>
            <?php if ($verify !== ''): ?>
                <?php if ($verified): ?>
                    <span class="badge badge-success">صحيحة — <?php echo htmlspecialchars((string)$verified['serial_no']); ?>
                        · <?php echo htmlspecialchars($verified['period_from'] . ' → ' . $verified['period_to']); ?></span>
                <?php else: ?>
                    <span class="badge badge-danger">لا شهادةَ بهذا الرمز</span>
                <?php endif; ?>
            <?php endif; ?>
        </form>
    </div></div>

    <?php if ($approved): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-stamp"></i> معتمدٌ بلا شهادة</h5></div>
    <div class="card-body">
        <?php foreach ($approved as $e): ?>
            <form method="post" style="display:flex;gap:10px;align-items:center;margin-bottom:8px">
                <input type="hidden" name="ct_action" value="issue">
                <input type="hidden" name="eval_id" value="<?php echo intval($e['id']); ?>">
                <span>تقييمُ <?php echo htmlspecialchars($e['period_from'] . ' → ' . $e['period_to']); ?>
                    بدرجة <strong><?php echo htmlspecialchars((string)$e['final_score']); ?></strong></span>
                <button type="submit" class="btn-save"><i class="fa fa-certificate"></i> أصدر الشهادة</button>
            </form>
        <?php endforeach; ?>
    </div></div>
    <?php endif; ?>

    <div class="card"><div class="card-header"><h5><i class="fa fa-list"></i> شهاداتي (<?php echo count($certs); ?>)</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap" style="width:100%" data-no-dt="1">
            <thead><tr><th>رقم الشهادة</th><th>الفترة</th><th>رمز التحقق</th><th>أُصدرت</th><th></th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">الموظف</th>
              <th class="ems-fn-th" data-fn="1">الإدارة</th>
              <th class="ems-fn-th" data-fn="1">المهام المنجزة</th>
              <th class="ems-fn-th" data-fn="1">نسبة الالتزام بالمهل</th>
              <th class="ems-fn-th" data-fn="1">ساعات العمل</th>
              <th class="ems-fn-th" data-fn="1">الإنتاج المنسوب</th>
              <th class="ems-fn-th" data-fn="1">نسبة الإنجاز</th>
              <th class="ems-fn-th" data-fn="1">الترتيب</th>
              <th class="ems-fn-th" data-fn="1">أصدرها</th>
              <th class="ems-fn-th" data-fn="1">تاريخ الإصدار</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              </tr></thead>
            <tbody>
            <?php foreach ($certs as $c): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars((string)$c['serial_no']); ?></strong></td>
                    <td><?php echo htmlspecialchars($c['period_from'] . ' → ' . $c['period_to']); ?></td>
                    <td><code><?php echo htmlspecialchars((string)$c['verify_code']); ?></code></td>
                    <td><small><?php echo htmlspecialchars((string)$c['issued_at']); ?></small></td>
                    <td><a class="btn-save" href="?open=<?php echo intval($c['id']); ?>">اعرضها للطباعة</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>

    <?php if ($openCert):
        $m = json_decode((string)$openCert['metrics_json'], true) ?: array();
    ?>
    <div class="card" id="printable"><div class="card-body" style="text-align:center;padding:40px">
        <h3>شهادةُ إنجاز</h3>
        <p>رقم <strong><?php echo htmlspecialchars((string)$openCert['serial_no']); ?></strong>
            · رمزُ التحقق <code><?php echo htmlspecialchars((string)$openCert['verify_code']); ?></code></p>
        <p>عن الفترة <strong><?php echo htmlspecialchars($openCert['period_from'] . ' → ' . $openCert['period_to']); ?></strong></p>
        <div style="text-align:right;max-width:560px;margin:16px auto">
            <p><strong>الإنجازُ بالأرقام المقاسة:</strong></p>
            <ul>
                <li>الطلبات: أُنشئ <?php echo intval($m['requests']['created'] ?? 0); ?> · اكتمل <?php echo intval($m['requests']['completed'] ?? 0); ?></li>
                <li>الاعتمادات: بُتّ في <?php echo intval($m['approvals']['decided'] ?? 0); ?></li>
                <li>الالتزام بالمهل: <?php echo isset($m['sla']['rate']) && $m['sla']['rate'] !== null
                    ? (round($m['sla']['rate'] * 100, 1) . '٪') : 'لا ينطبق'; ?></li>
                <li>الإنتاج: <?php echo !empty($m['production']['not_applicable'])
                    ? 'لا ينطبق' : (($m['production']['qty_due'] ?? 0) . ' كميةً مستحقة'); ?></li>
            </ul>
        </div>
        <p><small>أُصدرت في <?php echo htmlspecialchars((string)$openCert['issued_at']); ?> —
            وتُتحقَّق برمزها في هذه الشاشة</small></p>
    </div></div>
    <div style="margin:10px 0"><button type="button" class="btn-save" onclick="window.print()">
        <i class="fa fa-print"></i> الطباعة الرسمية</button></div>
    <?php endif; ?>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
