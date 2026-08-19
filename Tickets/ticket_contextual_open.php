<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Tickets/ticket_contextual_open.php — نقطة الفتح السياقي (TKT-01 §2 · TKT-15)
 * ───────────────────────────────────────────────────────────────────────────
 * يستقبل السياق المحمول من زر «أبلغ عن مشكلة» في أي شاشة، ويعرض نموذج
 * التصنيف الثلاثي (الفئة · النوع · الوصف) — والسياق ظاهر للقراءة لا للإدخال.
 * الحفظ عبر TicketRouter بحرّاسه، مع عرض المفتوح المشابه قبل الحفظ (T16).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/tenant_scope.php';   // نطاقُ الكيانِ من السياقِ لا من رقمٍ صلب
require_once __DIR__ . '/../app/Services/Tickets/TicketRouter.php';
require_once __DIR__ . '/../app/Services/Tickets/DuplicateDetector.php';
require_once __DIR__ . '/../includes/screen_contract.php';

use App\Services\Tickets\TicketRouter as TR;
use App\Services\Tickets\DuplicateDetector as DD;

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid = intval($_SESSION['user']['id'] ?? 0);
$company_id = ems_scope_company($conn);

// السياق المحمول — من POST الزر أو من نموذج الحفظ
$ctx = array();
foreach (array('screen', 'site_id', 'equipment_id', 'contract_id', 'project_id',
               'shift_no', 'period_no', 'entity_type', 'entity_id') as $k) {
    if (isset($_POST['ctx_' . $k]) && $_POST['ctx_' . $k] !== '') { $ctx[$k] = $_POST['ctx_' . $k]; }
}

$msg = '';
$dupFound = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tk_save'])) {
    $typeRow = null;
    $st = $conn->prepare("SELECT id FROM ticket_types WHERE code = ? LIMIT 1");
    $tc = strval($_POST['type_code'] ?? '');
    $st->bind_param('s', $tc);
    $st->execute();
    $typeRow = $st->get_result()->fetch_assoc();
    $st->close();
    // T16: كشف التكرار قبل الحفظ — يُعرض الأصل وخيار المتابعة
    if ($typeRow && empty($_POST['skip_dup_check'])) {
        $dupFound = DD::findOpen($conn, $company_id, intval($typeRow['id']),
            intval($ctx['site_id'] ?? 0), isset($ctx['equipment_id']) ? intval($ctx['equipment_id']) : null);
    }
    if (!empty($_POST['follow_ticket_id'])) {
        $r = DD::linkDuplicate($conn, intval($_POST['follow_ticket_id']), $uid);
        ems_gov_flash_redirect('tickets_list.php', 'أُضفت متابعًا للبلاغ الأصل — ولا بلاغ ثانٍ ✅', 'GOV-OK-200', '');
        exit();
    }
    if (!$dupFound) {
        $r = TR::create($conn, array(
            'company_id' => $company_id,
            'type_code' => $tc,
            'description' => strval($_POST['description'] ?? ''),
            'reporter_person_id' => $uid,
            'priority' => strval($_POST['priority'] ?? '') ?: null,
            'is_anonymous' => intval($_POST['is_anonymous'] ?? 0),
            'context' => $ctx,
        ));
        if ($r['ok']) {
            ems_gov_flash_redirect('tickets_list.php', 'بلاغ #' . $r['tk_id'] . ' — ' . $r['reason'] . ' ✅', 'GOV-OK-200', '');
            exit();
        }
        $msg = $r['reason'] . ' ❌';
    }
}

$types = array();
$r = $conn->query("SELECT code, name, category, nature FROM ticket_types WHERE active = 1 ORDER BY category, name");
while ($r && ($x = $r->fetch_assoc())) { $types[] = $x; }

$page_title = 'إيكوبيشن | بلاغ جديد — من موضع المشكلة';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
?>
<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'بلاغ جديد — السياق محمول من ' . htmlspecialchars($ctx['screen'] ?? 'الشاشة');
    $header_icon = 'fa fa-bullhorn';
    $header_actions = array();
    include('../includes/page_header.php');
    ems_screen_about(
        'البلاغ يُفتح من موضع المشكلة ويحمل سياقه كاملًا — فلا تُدخل حرفًا مما يعرفه النظام. '
        . 'ثلاث نقرات (الفئة والنوع والأولوية) والوصف الحر، والنظام يوجّه ويصعّد آليًّا.',
        array('السياق أدناه محمول للقراءة — ومن أدخل رقم معدة يدويًّا فقد فُقد السياق',
              'إن وُجد بلاغ مفتوح مشابه يُعرض قبل الحفظ: تابِعه ولا تفتح ثانيًا'));
    if ($msg !== '') { echo '<div class="alert alert-danger">' . htmlspecialchars($msg) . '</div>'; }
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا أنواعَ بلاغاتٍ مفعَّلةً للاختيار', 'راجع إعدادَ أنواعِ البلاغاتِ مع مركزِ البلاغاتِ قبلَ الرفع');
    ?>

    <?php if ($dupFound): ?>
    <div class="card"><div class="card-header"><h5>يوجد بلاغ مفتوح مشابه — أتتابعه أم تفتح جديدًا؟ (T16)</h5></div>
    <div class="card-body">
        <?php foreach ($dupFound as $d): ?>
        <form method="post" class="tkt-tco-dup-form">
        <?php echo csrf_field(); ?>
            <?php foreach ($ctx as $k => $v) { echo '<input type="hidden" name="ctx_' . htmlspecialchars($k) . '" value="' . htmlspecialchars((string) $v) . '">'; } ?>
            <input type="hidden" name="tk_save" value="1">
            <input type="hidden" name="type_code" value="<?php echo htmlspecialchars($_POST['type_code'] ?? ''); ?>">
            <input type="hidden" name="follow_ticket_id" value="<?php echo intval($d['id']); ?>">
            <span><strong><?php echo htmlspecialchars($d['ticket_no']); ?></strong> — <?php echo htmlspecialchars(mb_substr((string) $d['complaint'], 0, 80)); ?></span>
            <button type="submit" class="btn-primary">أتابعه</button>
        </form>
        <?php endforeach; ?>
        <form method="post">
        <?php echo csrf_field(); ?>
            <?php foreach ($ctx as $k => $v) { echo '<input type="hidden" name="ctx_' . htmlspecialchars($k) . '" value="' . htmlspecialchars((string) $v) . '">'; } ?>
            <input type="hidden" name="tk_save" value="1">
            <input type="hidden" name="skip_dup_check" value="1">
            <input type="hidden" name="type_code" value="<?php echo htmlspecialchars($_POST['type_code'] ?? ''); ?>">
            <input type="hidden" name="description" value="<?php echo htmlspecialchars($_POST['description'] ?? ''); ?>">
            <input type="hidden" name="priority" value="<?php echo htmlspecialchars($_POST['priority'] ?? ''); ?>">
            <button type="submit" class="action-btn delete">بل أفتح بلاغًا جديدًا</button>
        </form>
    </div></div>
    <?php endif; ?>

    <div class="card"><div class="card-header"><h5>السياق المحمول (للقراءة)</h5></div>
    <div class="card-body tkt-tco-ctx">
        <?php if (!$ctx) { echo '<span class="tkt-tco-noctx">فُتح من القائمة — بلا سياق شاشة (النوع «نظام» متاح)</span>'; }
        foreach ($ctx as $k => $v) {
            echo '<span class="badge badge-secondary tkt-tco-chip">' . htmlspecialchars($k) . ': ' . htmlspecialchars((string) $v) . '</span>';
        } ?>
    </div></div>

    <form method="post" class="allforms allforms-visible">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="tk_save" value="1">
        <?php foreach ($ctx as $k => $v) { echo '<input type="hidden" name="ctx_' . htmlspecialchars($k) . '" value="' . htmlspecialchars((string) $v) . '">'; } ?>
        <div class="tkt-tco-grid3">
            <div class="form-group"><label for="tkoTypeCode">النوع * (الفئة ← النوع)</label>
                <select name="type_code" id="tkoTypeCode" required><option value="">— اختر —</option>
                <?php foreach ($types as $t) {
                    echo '<option value="' . htmlspecialchars($t['code']) . '">'
                        . htmlspecialchars($t['category'] . ' / ' . $t['name']) . '</option>';
                } ?></select></div>
            <div class="form-group"><label for="tkoPriority">الأولوية (ترفعها ولا تخفضها)</label>
                <select name="priority" id="tkoPriority"><option value="">اقتراح النظام</option>
                    <option value="high">عالٍ</option><option value="critical">حرج</option></select></div>
            <div class="form-group"><label><input type="checkbox" name="is_anonymous" aria-label="رفعُ البلاغِ بلا كشفِ هوية" value="1"> بلا كشف هوية (للأنواع التي تقبله)</label></div>
        </div>
        <div class="form-group"><label for="tkoDescription">الوصف * — الحقل الحر الوحيد</label>
            <textarea name="description" id="tkoDescription" rows="3" required></textarea></div>
        <button type="submit" class="btn-primary">رفع البلاغ — يوجَّه آليًّا خلال ثانية</button>
    </form>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
