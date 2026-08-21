<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Reports/approval_lag_report.php — مؤشرا DEC-01 ⑦ و⑧ (الشاشة 212)
 * ───────────────────────────────────────────────────────────────────────────
 * ⑦ الاعتمادات المتأخرة — **أسبوعي برقمين**: عدد الوحدات غير المعتمدة
 *   وأقدمها بالأيام · تصعيد للإدارة العامة إن تجاوز الأقدم سبعة أيام ·
 *   المستهدف: صفر > 7 أيام ونسبة اعتماد ≥ 95٪. مالكه: مدير الحركة والتشغيل.
 * ⑧ الوثائق المنتهية — عددها أسبوعيًّا (يجب أن ينخفض لا أن يثبت) ·
 *   **وصفر وثيقة منتهية بلا استثناء نافذ أو تجديد**.
 * كل رقم ينقر لمصدره (أسئلة المراجع السبعة).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';

require_once __DIR__ . '/../includes/tenant_scope.php';   // نطاقُ الكيانِ من السياقِ لا من رقمٍ صلب
// ── RF-02 · CS-01 — حارسُ الشاشةِ فوقَ أيِّ معالجٍ يكتب ────────────────────
// كان هذا السطحُ يعتمد على insidebar.php وحدَه في الحجب، وinsidebar يقع
// **بعدَ** معالجِ الكتابة — فيُرحَّل الأثرُ ثم يُعاد التوجيهُ برسالةِ «لا صلاحية».
// الدالةُ نفسُها ولا تغييرَ في مَن يُمنع — التغييرُ في **متى**: قبلَ الكتابة.
if (function_exists('enforce_current_page_view_permission') && isset($conn)) {
    enforce_current_page_view_permission($conn, '../main/dashboard.php');
}
require_once __DIR__ . '/../includes/screen_contract.php';

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$role = strval($_SESSION['user']['role'] ?? '');
// FIN-26: إدارة التمويل (26) تطالع المؤشر عرضًا — الشاشة قراءة كلها فلا حجب POST يلزم
if ($role !== '-1' && !in_array($role, array('1', '17', '19', EMS_ROLE_FINANCING_MGR), true)) {
    ems_gov_flash_redirect('../main/dashboard.php', 'المؤشر لمدير الحركة والإدارة العليا والمالية والتمويل ❌', 'GOV-PERM-403', 'اطلب المنحةَ من مدير الصلاحيات إن كانت ضمن عملك');
}
$co = ems_scope_company($conn);

// ── ⑦ الاعتمادات المتأخرة: فترات بلا توقيع سلسلة (unit_chain) ──
$lag = $conn->query(
    "SELECT COUNT(*) pending, COALESCE(MAX(DATEDIFF(CURDATE(), l.work_date)), 0) oldest_days
       FROM shift_period_logs l
      WHERE NOT EXISTS (SELECT 1 FROM approval_signatures s
                         WHERE s.document_type = 'unit_chain' AND s.document_id = l.log_id AND s.result = 'signed')"
)->fetch_assoc();
$pending = intval($lag['pending']); $oldest = intval($lag['oldest_days']);

$tot = $conn->query(
    "SELECT COUNT(*) t,
            SUM(EXISTS (SELECT 1 FROM approval_signatures s
                         WHERE s.document_type = 'unit_chain' AND s.document_id = l.log_id AND s.result = 'signed')) a
       FROM shift_period_logs l
      WHERE l.work_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
)->fetch_assoc();
$weekTotal = intval($tot['t']); $weekApproved = intval($tot['a']);
$weekRate = $weekTotal > 0 ? round($weekApproved * 100.0 / $weekTotal, 1) : 100.0;

// التصعيد: الأقدم > 7 أيام → إشعار الإدارة العامة (مرة يوميًّا — بمفتاح العنوان)
if ($oldest > 7) {
    $title = 'DEC-01 ⑦: أقدم وحدة غير معتمدة بلغت ' . $oldest . ' يومًا (المستهدف: صفر فوق 7) — ' . date('Y-m-d');
    $dup = $conn->query("SELECT id FROM fin_notifications WHERE title = '" . $conn->real_escape_string($title) . "' LIMIT 1");
    if (!$dup || $dup->num_rows === 0) {
        $st = $conn->prepare("INSERT INTO fin_notifications (company_id, target_level, title, link) VALUES (?, 'general_management', ?, 'Reports/approval_lag_report.php')");
        $st->bind_param('is', $co, $title);
        $st->execute();
        $st->close();
    }
}

// أقدم عشر وحدات معلَّقة — كل رقم ينقر لمصدره
$oldestRows = $conn->query(
    "SELECT l.log_id, l.work_date, l.equipment_id, l.shift_no, l.period_no, l.synced_late,
            DATEDIFF(CURDATE(), l.work_date) age_days
       FROM shift_period_logs l
      WHERE NOT EXISTS (SELECT 1 FROM approval_signatures s
                         WHERE s.document_type = 'unit_chain' AND s.document_id = l.log_id AND s.result = 'signed')
      ORDER BY l.work_date ASC LIMIT 10"
)->fetch_all(MYSQLI_ASSOC);

// ── ⑧ الوثائق المنتهية: العدد + المنتهية بلا استثناء نافذ ──
$docs = $conn->query(
    "SELECT COUNT(*) expired,
            SUM(NOT EXISTS (
                SELECT 1 FROM exception_requests x
                 WHERE x.guard_code = 'driver.doc.expiry' AND x.state IN ('Approved','Active')
                   AND x.valid_from <= CURDATE() AND x.valid_to >= CURDATE()
                   AND ((x.scope_type = 'person' AND x.scope_id = CAST(d.subject_id AS CHAR))
                        OR (x.scope_type = 'equipment' AND x.scope_id = CAST(d.subject_id AS CHAR)))
            )) uncovered
       FROM equipment_documents d
      WHERE COALESCE(d.is_deleted, 0) = 0 AND d.expiry_date IS NOT NULL AND d.expiry_date < CURDATE()"
)->fetch_assoc();
$expired = intval($docs['expired']); $uncovered = intval($docs['uncovered']);

// اتجاه أسبوعي: لقطات العدد من سجل الإشعارات الذاتية (تُكتب مرة أسبوعيًّا)
$snapTitle = 'DEC-01 ⑧ لقطة الوثائق المنتهية أسبوع ' . date('o-W') . ': ' . $expired;
$dup = $conn->query("SELECT id FROM fin_notifications WHERE title LIKE 'DEC-01 ⑧ لقطة الوثائق المنتهية أسبوع " . $conn->real_escape_string(date('o-W')) . "%' LIMIT 1");
if (!$dup || $dup->num_rows === 0) {
    $st = $conn->prepare("INSERT INTO fin_notifications (company_id, target_level, title, link) VALUES (?, 'hr_manager', ?, 'Reports/approval_lag_report.php')");
    $st->bind_param('is', $co, $snapTitle);
    $st->execute();
    $st->close();
}
$trend = $conn->query(
    "SELECT title FROM fin_notifications
      WHERE title LIKE 'DEC-01 ⑧ لقطة الوثائق المنتهية أسبوع %'
      ORDER BY id DESC LIMIT 8"
)->fetch_all(MYSQLI_ASSOC);

$page_title = 'إيكوبيشن | الاعتمادات المتأخرة والوثائق';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
?>
<style>
/* UXW-01 ①②: أنماطُ شاشةِ الاعتماداتِ المتأخرة — بادئةُ الشاشةِ rpt-lag
   ───────────────────────────────────────────────────────────────────────────
   ◆ بطاقاتُ الرأسِ صارت **البطاقةَ الموحَّدة** (`assets/css/ems-statcards.css`):
     `stats-section > stats-grid > stats-card` بترتيبِ الأصل — أيقونةٌ ⇐ قيمةٌ ⇐
     عنوانٌ ⇐ تابع. فالرقمُ يقع في `stats-value` وهو **أكبرُ خطٍّ في البطاقة**
     (35px · ثقل 900 · tabular-nums)، وكان قبلَها في صنفٍ محلّيٍّ (`rpt-lag-num`
     بـ34px) لا يعرفه المصدرُ الموحَّد فخرجت البطاقةُ بشكلٍ خاصٍّ بهذه الشاشة.
   ◆ **ولا لونَ محلّيًّا على القيمة**: المصدرُ الموحَّد يفرض لونَ النصِّ الداكنَ بـ`!important`،
     فمنازعتُه تكسر التوحيد. والحالةُ تُعلَن **نصًّا وأيقونةً** في السطرِ التابع
     (`ems-statcard__meta`) لا لونًا وحدَه — واللونُ وحدَه إشارةٌ لا يراها كلُّ قارئ.
   ◆ `rpt-lag-bad` باقيةٌ لعمودِ العمرِ في الجدولِ وحدَه. */
.rpt-lag-bad{color:var(--c-c0392b, #c0392b)}
.rpt-lag-table{width:100%}
</style>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'الاعتمادات المتأخرة والوثائق المنتهية (DEC-01)'; $header_icon = 'fa fa-hourglass-half';
    $header_actions = array();
    include('../includes/page_header.php');
    ems_screen_about('مؤشرا القرارين ⑦ و⑧: عدد الوحدات غير المعتمدة وأقدمها بالأيام (مالكه مدير الحركة · '
        . 'تصعيد فوق 7 أيام · مستهدف الاعتماد 95٪) — وعدد الوثائق المنتهية (ينخفض لا يثبت · '
        . 'وصفر منتهية بلا استثناء نافذ). المحرك الصحيح ببيانات غير معتمدة يُخرج أصفارًا.',
        array('راجع الأقدم أولًا', 'كل رقم ينقر لمصدره'));
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا وحداتٍ معلَّقةً ولا وثائقَ منتهيةً في هذه الفترة',
        'وسّع الفترةَ أو تحقق من ترحيلِ سجلاتِ الورديات وتواريخِ انتهاءِ الوثائق');
    ?>
    <div class="stats-section">
        <div class="stats-grid">
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-clipboard-check"></i></div>
                <div class="stats-value"><?php echo $pending; ?></div>
                <div class="stats-title">وحدة غير معتمدة</div>
                <div class="ems-statcard__meta">فتراتٌ بلا توقيعِ سلسلة (unit_chain)</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas <?php echo $oldest > 7 ? 'fa-hourglass-end' : 'fa-hourglass-half'; ?>"></i></div>
                <div class="stats-value"><?php echo $oldest; ?></div>
                <div class="stats-title">أقدمها بالأيام</div>
                <div class="ems-statcard__meta"><?php echo $oldest > 7 ? 'تجاوز 7: صُعّد للإدارة العامة' : 'ضمن المستهدف (≤7)'; ?></div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-percentage"></i></div>
                <div class="stats-value"><?php echo $weekRate; ?>٪</div>
                <div class="stats-title">نسبة اعتماد الأسبوع</div>
                <div class="ems-statcard__meta">مستهدف ≥95٪ · <?php echo $weekApproved; ?>/<?php echo $weekTotal; ?> — <?php echo $weekRate < 95 ? 'دون المستهدف' : 'ضمن المستهدف'; ?></div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas <?php echo $uncovered > 0 ? 'fa-file-circle-exclamation' : 'fa-file-shield'; ?>"></i></div>
                <div class="stats-value"><?php echo $expired; ?></div>
                <div class="stats-title">وثيقة منتهية</div>
                <div class="ems-statcard__meta">منها <?php echo $uncovered; ?> بلا استثناء نافذ (المستهدف: صفر)</div>
            </div>
        </div>
    </div>

    <div class="card"><div class="card-body">
        <h4>أقدم الوحدات المعلَّقة (⑦ — يبدأ التأخير عند مدير الحركة غالبًا)</h4>
        <div class="table-container"><table class="alltables display rpt-lag-table" data-no-dt="1">
        <thead><tr><th>#</th><th>تاريخ العمل</th><th>العمر (يوم)</th><th>المعدة</th><th>وردية/فترة</th><th>مزامَن متأخر؟</th></tr></thead><tbody>
        <?php foreach ($oldestRows as $r): ?>
        <tr>
            <td><a href="../Reports/units_daily_report.php?log_id=<?php echo intval($r['log_id']); ?>"><?php echo intval($r['log_id']); ?></a></td>
            <td><?php echo htmlspecialchars($r['work_date']); ?></td>
            <td class="<?php echo intval($r['age_days']) > 7 ? 'rpt-lag-bad' : ''; ?>"><?php echo intval($r['age_days']); ?></td>
            <td><?php echo intval($r['equipment_id']); ?></td>
            <td><?php echo intval($r['shift_no']) . '/' . intval($r['period_no']); ?></td>
            <td><?php echo intval($r['synced_late']) === 1 ? 'نعم (DEC-01 ⑨)' : '—'; ?></td>
        </tr>
        <?php endforeach; if (empty($oldestRows)): ?>
        <tr><td colspan="6">لا وحدات معلَّقة — المستهدف متحقق</td></tr>
        <?php endif; ?>
        </tbody></table></div>
    </div></div>

    <div class="card"><div class="card-body">
        <h4>اتجاه الوثائق المنتهية أسبوعيًّا (⑧ — يجب أن ينخفض لا أن يثبت)</h4>
        <ul>
        <?php foreach ($trend as $t): ?>
            <li><?php echo htmlspecialchars($t['title']); ?></li>
        <?php endforeach; ?>
        </ul>
        <p><a href="../Reports/exceptions_report.php">الاستثناءات النافذة ←</a> · مالك التنظيف: مسؤول شؤون الموظفين (15 يوم عمل من توقيع DEC-01)</p>
    </div></div>
</div>
<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
