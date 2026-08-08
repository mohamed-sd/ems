<?php
/**
 * Governance/ownership_grants.php — منح المجال المقيَّد (FIN-26 · الشاشة 213)
 * ───────────────────────────────────────────────────────────────────────────
 * الفجوة التي سدّتها: ownership_access_grants كان بلا شاشة فباب التمويل محجوب
 * عن الجميع. هنا تُعرض المنح النافذة ويُمنح/يُلغى **عبر OwnershipDomainGuard
 * حصرًا** (FIN-01 §1.1): الصلاحية فردية بأكوادها الثلاثة لا بالعضوية في إدارة ·
 * **قيمة الشراء لا تُمنح إلا بسبب ومدة** (القاعدة 422 قائمة في الخدمة وCHECK
 * بنيوي يسندها) · وكل منح أو إلغاء بسطر توقيع (AuthorityGuard) وسطر اطّلاع.
 * خلف الأدوار 1 و19 والسوبر — نمط Settings/guard_classification.php حرفيًّا.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/screen_contract.php';
require_once dirname(__DIR__) . '/app/Core/AuthorityGuard.php';
require_once dirname(__DIR__) . '/app/Core/OwnershipDomainGuard.php';

use App\Core\OwnershipDomainGuard as ODG;

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$role = strval($_SESSION['user']['role'] ?? '');
$is_super = ($role === '-1');
// خلف الصلاحية: الإدارة العليا والمالية العليا حصرًا (1 · 19 · -1)
if (!$is_super && !in_array($role, array('1', '19'), true)) {
    http_response_code(403);
    exit('403 — منح المجال المقيَّد خلف صلاحية مقيَّدة');
}
$co = $company_id ?: 4;
$uid = intval($_SESSION['user']['id'] ?? 0);

$PERM_AR = array(
    ODG::PERM_OWNER => 'رؤية المالك وعلاقته',
    ODG::PERM_TERMS => 'رؤية شروط التمويل',
    ODG::PERM_VALUE => 'رؤية قيمة الشراء والعائد (الأشد — بسبب ومدة)',
);

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $op = strval($_POST['op'] ?? '');
    if ($op === 'grant') {
        $pid = intval($_POST['person_id'] ?? 0);
        $code = strval($_POST['permission_code'] ?? '');
        $reason = trim(strval($_POST['reason'] ?? ''));
        $from = ($_POST['valid_from'] ?? '') !== '' ? strval($_POST['valid_from']) : null;
        $to = ($_POST['valid_to'] ?? '') !== '' ? strval($_POST['valid_to']) : null;
        if ($pid <= 0 || !isset($PERM_AR[$code])) {
            $err = 'المستخدم والكود من القائمة — لا منح مبهمًا';
        } else {
            // المنح عبر الخدمة حصرًا — قيمة الشراء بلا سبب ومدة تُرفض 422 فيها
            $r = ODG::grant($conn, $co, $pid, $code, $uid, $reason !== '' ? $reason : null, $from, $to);
            if ($r['ok']) {
                // الاعتماد توقيع (LEG-01) — سطر بمرجع المنحة
                \App\Core\AuthorityGuard::sign($conn, array(
                    'company_id' => $co,
                    'document_type' => 'ownership_grant', 'document_id' => intval($r['grant_id']),
                    'step' => 'grant:' . $code, 'person_id' => $uid,
                ));
                $msg = $r['reason'] . ' للمستخدم #' . $pid . ' — والمنحة #' . intval($r['grant_id']) . ' بسطر توقيعها';
            } else {
                $err = $r['reason'];
            }
        }
    } elseif ($op === 'revoke') {
        $gid = intval($_POST['grant_id'] ?? 0);
        $why = trim(strval($_POST['revoke_reason'] ?? ''));
        if ($gid <= 0) {
            $err = 'منحة غير معروفة';
        } elseif ($why === '') {
            $err = 'سبب الإلغاء إلزامي — المنح والإلغاء قراران موثَّقان';
        } else {
            $st = $conn->prepare("UPDATE ownership_access_grants
                                     SET state = 'revoked', revoked_by = ?, revoked_at = NOW(),
                                         reason = CONCAT(COALESCE(reason, ''), ' | أُلغيت: ', ?)
                                   WHERE grant_id = ? AND company_id = ? AND state = 'active'");
            $st->bind_param('isii', $uid, $why, $gid, $co);
            $st->execute();
            if ($st->affected_rows > 0) {
                \App\Core\AuthorityGuard::sign($conn, array(
                    'company_id' => $co,
                    'document_type' => 'ownership_grant', 'document_id' => $gid,
                    'step' => 'revoke:' . date('YmdHis'), 'person_id' => $uid,
                ));
                $msg = 'أُلغيت المنحة #' . $gid . ' بسببها وسطر توقيعها — الصف باقٍ للتدقيق لا يُحذف';
            } else {
                $err = 'لا منحة نافذة بهذا الرقم لهذه الشركة';
            }
            $st->close();
        }
    }
}

// المنح النافذة (وغير النافذة الأخيرة للتدقيق) بأسماء أصحابها
$grants = $conn->query(
    "SELECT g.*, u.name user_name, u.username, u.role user_role,
            gb.name granted_by_name
       FROM ownership_access_grants g
       LEFT JOIN users u ON u.id = g.person_id
       LEFT JOIN users gb ON gb.id = g.granted_by
      WHERE g.company_id = {$co}
      ORDER BY (g.state = 'active') DESC, g.grant_id DESC LIMIT 200"
)->fetch_all(MYSQLI_ASSOC);

$users = $conn->query(
    "SELECT id, name, username, role FROM users
      WHERE company_id = {$co} AND status = 'active' AND COALESCE(is_deleted, 0) = 0
      ORDER BY name"
)->fetch_all(MYSQLI_ASSOC);

$page_title = 'إيكوبيشن | منح المجال المقيَّد';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'منح المجال المقيَّد'; $header_icon = 'fa fa-user-lock';
    $header_actions = array();
    include('../includes/page_header.php');
    ems_screen_about('بيانات ملّاك المعدات وشروط تمويلها ليست بيانًا تشغيليًّا (FIN-01 §1.1): الاطّلاع '
        . 'بصلاحية فردية بأكوادها الثلاثة لا بالعضوية في إدارة — تُمنح وتُلغى هنا بقرار موثَّق '
        . 'بتوقيعه، وقيمة الشراء (الأشد) لا تُمنح إلا بسبب ومدة، وكل قراءة لاحقة بسطر اطّلاع.',
        array('المنح فردي لا جماعي', 'الإلغاء بسبب — والصف باقٍ للتدقيق'));
    if ($msg !== '') { echo '<div class="alert alert-success">' . htmlspecialchars($msg) . '</div>'; }
    if ($err !== '') { echo '<div class="alert alert-danger">' . htmlspecialchars($err) . '</div>'; }
    ?>

    <div class="card"><div class="card-header"><h5><i class="fa fa-plus"></i> منح صلاحية فردية</h5></div>
    <div class="card-body">
        <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end"
              onsubmit="return confirm('المنح قرار حوكمة موثَّق بتوقيعه — أتؤكد؟');">
            <input type="hidden" name="op" value="grant">
            <div><label>المستخدم</label><br>
                <select name="person_id" required>
                    <option value="">— اختر —</option>
                    <?php foreach ($users as $u): ?>
                    <option value="<?php echo intval($u['id']); ?>">
                        <?php echo htmlspecialchars($u['name'] . ' (' . $u['username'] . ' · دور ' . $u['role'] . ')'); ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div><label>الكود</label><br>
                <select name="permission_code" required>
                    <?php foreach ($PERM_AR as $ck => $cl): ?>
                    <option value="<?php echo htmlspecialchars($ck); ?>"><?php echo htmlspecialchars($cl); ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div><label>السبب (إلزامي لقيمة الشراء)</label><br>
                <input type="text" name="reason" style="width:220px" placeholder="قرار المنح وسنده"></div>
            <div><label>من</label><br><input type="date" name="valid_from"></div>
            <div><label>إلى</label><br><input type="date" name="valid_to"></div>
            <button class="btn-save" type="submit">منح</button>
        </form>
    </div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-user-lock"></i> المنح — النافذة أولًا</h5></div>
    <div class="card-body">
        <?php if (empty($grants)): ems_state_empty('لا منح بعد — باب التمويل محجوب عن الجميع حتى أول منحة فردية'); else: ?>
        <div class="table-container"><table class="alltables display" data-no-dt="1" style="width:100%">
        <thead><tr><th>#</th><th>المستخدم</th><th>الكود</th><th>المدة</th><th>السبب</th><th>مانحها</th><th>الحالة</th><th>إلغاء (بسبب)</th>
              <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              </tr></thead><tbody>
        <?php foreach ($grants as $g): ?>
        <tr>
            <td><?php echo intval($g['grant_id']); ?></td>
            <td><strong><?php echo htmlspecialchars((string) $g['user_name']); ?></strong>
                <br><small>#<?php echo intval($g['person_id']); ?> · دور <?php echo htmlspecialchars((string) $g['user_role']); ?></small></td>
            <td><code><?php echo htmlspecialchars($g['permission_code']); ?></code></td>
            <td><small><?php echo $g['valid_from'] || $g['valid_to']
                ? htmlspecialchars(($g['valid_from'] ?: '…') . ' → ' . ($g['valid_to'] ?: '…'))
                : 'بلا مدة'; ?></small></td>
            <td><small><?php echo htmlspecialchars(mb_substr((string) $g['reason'], 0, 70)); ?></small></td>
            <td><small><?php echo htmlspecialchars((string) $g['granted_by_name']); ?></small></td>
            <td><span class="badge badge-<?php echo $g['state'] === 'active' ? 'success' : 'danger'; ?>">
                <?php echo $g['state'] === 'active' ? 'نافذة' : 'ملغاة'; ?></span></td>
            <td>
                <?php if ($g['state'] === 'active'): ?>
                <form method="post" style="display:flex;gap:6px"
                      onsubmit="return confirm('إلغاء المنحة يحجب الباب عن صاحبها فورًا — أتؤكد بقرار موثَّق؟');">
                    <input type="hidden" name="op" value="revoke">
                    <input type="hidden" name="grant_id" value="<?php echo intval($g['grant_id']); ?>">
                    <input type="text" name="revoke_reason" placeholder="سبب الإلغاء — إلزامي" style="width:150px">
                    <button class="btn-save" type="submit">إلغاء</button>
                </form>
                <?php else: ?>—<?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php endif; ?>
    </div></div>
</div>
<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
