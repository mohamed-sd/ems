<?php
/**
 * Settings/guard_classification.php — تصنيف الحمايات (GOV-01 §9-④ · الشاشة 205)
 * ───────────────────────────────────────────────────────────────────────────
 * **الشاشة الجديدة الوحيدة في GOV-01** (باب الإعدادات والتدقيق خلف الصلاحية):
 * صنف كل حماية ودرجتها وعلم بيئتها · **تحذير الأثر قبل الحفظ** · **وسبب
 * إلزامي لأي تغيير صنف** — والصنف يتغير بقرار حوكمة لا بتعديل إعداد.
 * أسئلة المراجع السبعة: كل تغيير بسطر توقيع (AuthorityGuard) وقيمتي قبل/بعد.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/screen_contract.php';
require_once dirname(__DIR__) . '/app/Core/AuthorityGuard.php';

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$role = strval($_SESSION['user']['role'] ?? '');
$is_super = ($role === '-1');
// خلف الصلاحية: الإدارة العليا والمالية العليا حصرًا (1 · 19 · -1)
if (!$is_super && !in_array($role, array('1', '19'), true)) {
    http_response_code(403);
    exit('403 — شاشة التصنيف خلف صلاحية مقيَّدة');
}

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = strval($_POST['guard_code'] ?? '');
    $newClass = strval($_POST['guard_class'] ?? '');
    $reason = trim(strval($_POST['reason'] ?? ''));
    if (!in_array($newClass, array('absolute', 'exception_allowed', 'advisory'), true)) {
        $err = 'صنف غير معروف';
    } elseif ($reason === '') {
        $err = 'سبب التغيير إلزامي — الصنف يتغير بقرار حوكمة موثَّق لا بتعديل إعداد';
    } else {
        $cur = null;
        $st = $conn->prepare('SELECT guard_class FROM guard_policies WHERE guard_code = ?');
        $st->bind_param('s', $code);
        $st->execute();
        $cur = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$cur) {
            $err = 'حماية غير موجودة';
        } elseif ($cur['guard_class'] === $newClass) {
            $msg = 'الصنف كما هو — لا تغيير';
        } else {
            $uid = intval($_SESSION['user']['id']);
            // الاعتماد توقيع (LEG-01): سطر بمرجعه وقيمتي قبل/بعد في السبب المسجَّل
            \App\Core\AuthorityGuard::sign($conn, array(
                'company_id' => $company_id ?: 1,
                'document_type' => 'guard_reclass', 'document_id' => crc32($code),
                'step' => 'reclass:' . date('YmdHis'), 'person_id' => $uid,
            ));
            $st = $conn->prepare('UPDATE guard_policies SET guard_class = ?, classified_by = ?, classified_at = NOW(), reason = ? WHERE guard_code = ?');
            $full = 'من «' . $cur['guard_class'] . '» إلى «' . $newClass . '»: ' . $reason;
            $st->bind_param('siss', $newClass, $uid, $full, $code);
            $st->execute();
            $st->close();
            $msg = 'غُيّر الصنف بقراره الموثق وتوقيعه — وقيمتا قبل/بعد في السبب';
        }
    }
}

$guards = array();
$r = $conn->query('SELECT * FROM guard_policies ORDER BY guard_code');
while ($r && ($x = $r->fetch_assoc())) { $guards[] = $x; }

$CLASS_AR = array('absolute' => 'منع مطلق — لا استثناء', 'exception_allowed' => 'منع باستثناء محكوم', 'advisory' => 'تنبيه مسجَّل');
$page_title = 'إيكوبيشن | تصنيف الحمايات';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'تصنيف الحمايات'; $header_icon = 'fa fa-shield';
    $header_actions = array();
    include('../includes/page_header.php');
    ems_screen_about('صنف كل حماية ودرجتها وموافقوها وعلم بيئتها — لا حماية بلا صنف معلن، '
        . 'ولا يُقلب علم حماية إلى الإنفاذ قبل تصنيفها، والصنف يتغير بقرار موثَّق بسببه.',
        array('راجع الصنف والدرجة', 'أي تغيير صنف يلزمه سبب'));
    if ($msg !== '') { echo '<div class="alert alert-success">' . htmlspecialchars($msg) . '</div>'; }
    if ($err !== '') { echo '<div class="alert alert-danger">' . htmlspecialchars($err) . '</div>'; }
    ?>
    <div class="card"><div class="card-body">
        <div class="table-container"><table class="alltables display" data-no-dt="1" style="width:100%">
        <thead><tr><th>الحماية</th><th>البيت</th><th>الصنف</th><th>الدرجة</th><th>علم البيئة</th><th>آخر سبب</th><th>تغيير الصنف (بقرار)</th>
              <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              </tr></thead><tbody>
        <?php foreach ($guards as $g): ?>
        <tr>
            <td><strong><?php echo htmlspecialchars($g['name_ar']); ?></strong><br><small><?php echo htmlspecialchars($g['guard_code']); ?></small></td>
            <td><?php echo htmlspecialchars((string) $g['owner_doc']); ?></td>
            <td><span class="badge badge-<?php echo $g['guard_class'] === 'absolute' ? 'danger' : ($g['guard_class'] === 'exception_allowed' ? 'warning' : 'info'); ?>">
                <?php echo htmlspecialchars($CLASS_AR[$g['guard_class']] ?? $g['guard_class']); ?></span></td>
            <td><?php echo htmlspecialchars($g['default_risk']); ?></td>
            <td><code><?php echo htmlspecialchars((string) $g['env_flag_name']); ?></code></td>
            <td><small><?php echo htmlspecialchars(mb_substr((string) $g['reason'], 0, 60)); ?></small></td>
            <td>
                <form method="post" style="display:flex;gap:6px;align-items:center"
                      onsubmit="return confirm('تحذير الأثر: تغيير صنف «<?php echo htmlspecialchars($g['name_ar']); ?>» يغيّر سلوك الحارس الحي (منع/استثناء/تنبيه). أتؤكد بقرار موثَّق؟');">
                    <input type="hidden" name="guard_code" value="<?php echo htmlspecialchars($g['guard_code']); ?>">
                    <select name="guard_class">
                        <?php foreach ($CLASS_AR as $ck => $cl): ?>
                        <option value="<?php echo $ck; ?>" <?php echo $ck === $g['guard_class'] ? 'selected' : ''; ?>><?php echo $cl; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="reason" placeholder="سبب التغيير — إلزامي" style="width:180px">
                    <button class="btn-save" type="submit">حفظ</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </div></div>
</div>
<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
