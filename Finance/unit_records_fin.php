<?php
/**
 * Finance/unit_records_fin.php — «وحدات الأطراف» (UX-02 §8.1)
 * ───────────────────────────────────────────────────────────────────────────
 * أُعيد بناء الشاشة 2026-07-26 على المفهوم المعتمد: **واقعةٌ واحدة وأحكامُ
 * أطرافٍ مستقلة** — بدل «مطابقة الوحدات» المبنية على مفهومٍ أُبطل (عمودُ
 * كميةٍ واحد واعتمادٌ مشروطٌ بالتساوي).
 *
 * البنية (§8.1 نصًّا):
 *   • رأس الواقعة: التاريخ · المشروع · المعدة · المشغّل · نموذج العمل ·
 *     الكمية المسجّلة · الزمن (فعلي/استعداد/توقف بمسؤوله) — تقرأ الشاشة من
 *     **سجلّ الوحدات القانوني** بوصفه طبقةَ الحقيقة، وبياناتُه مشتقةٌ من
 *     إدخال التايم شيت اليومي **دون إعادة إدخال** (فزال الإدخالُ اليدوي).
 *   • ثلاثُ بطاقات حكم (عميل · مورد · مشغّل) لكل واقعةٍ محوَّلة — كلٌّ بوحدته
 *     وكميته وحالته واستحقاقه وفق عقده أو سياسته، والمتعذّرُ بسببه المعلن.
 *   • زر «اعتماد الأحكام» لا «اعتماد التطابق» — والتساوي يُعلَّم تلقائيًّا
 *     حين يقع طبيعيًّا وفق العقود **ولا يُشترط أبدًا**.
 *
 * طابور التحويل المالي (D02 §5) باقٍ كما هو: لا مالَ إلا بختم المالية —
 * ولحظةُ التحويل هي لحظةُ كتابة الأحكام الثلاثة وتوليد الأثر معًا.
 *
 * السجل اليدوي القديم (fin_unit_records — 10 صفوف، 8 منها بمالٍ مربوط):
 * **قسمٌ تاريخيٌّ مجمّدٌ للقراءة** — لا إضافةَ ولا تعديلَ ولا اعتماد. مالُه
 * المولَّد باقٍ بروابطه (لا يُمسّ)، والصفان غير المعتمدَين (variance/pending)
 * بقيا كما هما شاهدَين على المفهوم القديم.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/fin_helpers.php';

$ctx = fin_ctx();
$is_super_admin = $ctx['is_super']; $company_id = $ctx['company_id']; $current_user_id = $ctx['user_id'];
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة ❌', 'GOV-SCOPE-403', ''); exit(); }

$perms = fin_page_perms($conn, 'Finance/unit_records_fin.php', $is_super_admin);
$can_view = $perms['can_view']; $can_edit = $perms['can_edit'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض وحدات الأطراف ❌', 'GOV-PERM-403', ''); exit(); }

$work_models = fin_work_models(); $match_states = fin_match_states(); $downtime_causes = fin_downtime_causes();

// ═══════════════════════════════════════════════════════════════════════════
// D02 §5/§6: التحويل المالي — لحظةُ صيرورة يوم الدوام استحقاقًا ماليًّا
// ───────────────────────────────────────────────────────────────────────────
// «اعتماد المالية لا يعني إنشاء الوحدة؛ بل تحويلَها إلى أثرٍ مالي» — فالمروحة
// تُنشأ هنا حصرًا حين تكون البوابة نافذة. الحارس صلاحيةُ التعديل على هذه
// الشاشة وحدها (تُضبط من شاشة الصلاحيات لأي دور) — لا دورَ محظوظٌ في الكود.
// دفعةٌ واحدة: سجلٌّ أو مجموعةٌ أو الكل، بنمط شاشة الاعتمادات نفسه.
// ولحظةَ التحويل تُكتب أحكامُ الأطراف الثلاثة (party_award) مع المال ذرّيًّا.
// ═══════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'convert_units') {
    if (!$can_edit) { ems_gov_flash_redirect(ems_flash_to('unit_records_fin.php', "+❌"), 'لا تملك صلاحية اعتماد الأحكام', 'GOV-PERM-403', ''); exit(); }
    if (!fin_verify_action_token()) { ems_gov_flash_redirect('unit_records_fin.php', 'رمز الحماية غير صالح ❌', 'GOV-FAIL-409', ''); exit(); }

    $ids = array();
    foreach (explode(',', (string) ($_POST['ids'] ?? '')) as $one) {
        $one = intval(trim($one));
        if ($one > 0) { $ids[] = $one; }
    }
    $ids = array_slice(array_unique($ids), 0, 500); // سقفٌ معلَنٌ للدفعة الواحدة
    if (!$ids) { ems_gov_flash_redirect('unit_records_fin.php', 'لم تحدد أي يوم للاعتماد ❌', 'GOV-FAIL-409', ''); exit(); }

    require_once __DIR__ . '/../app/Services/Finance/UnitConversionService.php';
    $okCount = 0; $effCount = 0; $failed = array(); $eligibleIds = array();
    foreach ($ids as $tsId) {
        // ① أهليةٌ خادمية: اليوم ضمن نطاق الشركة وعلى السلسلة sales_approved —
        //    لا يُحوَّل يومٌ بمجرد إرسال معرّفه (الطابور عرضٌ لا تفويض).
        $eligible = fin_conversion_queue($conn, $is_super_admin, array('limit' => 1, 'only_id' => $tsId));
        if (!$eligible) { $failed[] = 'TS-' . $tsId . ': غير مؤهل للتحويل (ليس sales_approved على السلسلة أو محول سلفا)'; continue; }
        $eligibleIds[] = $tsId;
    }
    // ② الخدمة الواحدة (E-01 §6-1): المروحة + ختم السلسلة ذرّيًّا لكل يوم
    if ($eligibleIds) {
        $batch = \App\Services\Finance\UnitConversionService::convertBatch($conn, $eligibleIds, $current_user_id);
        $okCount = $batch['converted'];
        $effCount = $batch['effects'];
        foreach ($batch['failed'] as $f) { $failed[] = $f; }
        foreach ($batch['rows'] as $bTs => $bRes) {
            if ($bRes['ok'] && !$bRes['converted']) { $failed[] = 'TS-' . $bTs . ': ' . $bRes['reason']; }
        }
    }

    if ($okCount > 0) {
        fin_log_approval($conn, $company_id, 0, 'operational_approved', 'converted', 'advance', 'finance_conversion',
            $current_user_id, 'اعتماد أحكام وتحويل مالي ل' . $okCount . ' يوما · ' . $effCount . ' أثرا مولدا');
        fin_notify($conn, $company_id, 'dept_accountant',
            'تحولت ' . $okCount . ' يوما إلى استحقاقات مالية (' . $effCount . ' أثرا) — الإيراد بانتظار رفعه', 'events_list_fin.php?fstate=draft');
    }
    $msg = 'اعتمدت أحكام ' . $okCount . ' يوما (' . $effCount . ' أثرا)';
    if ($failed) { $msg .= ' — تعذّر ' . count($failed) . ': ' . implode(' | ', array_slice($failed, 0, 3)); }
    ems_gov_flash_redirect(ems_flash_to('unit_records_fin.php', ($okCount > 0 ? '+✅' : '+⚠️')), $msg, 'GOV-INFO-200', ''); exit();
}

$page_title = 'إيكوبيشن | وحدات الأطراف';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main fin-units-main ems-unified-page-shell">
    <?php
    $header_title = 'وحدات الأطراف'; $header_icon = 'fa fa-cubes';
    $header_actions = array();
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا وقائع في السجل القانوني ضمن هذا النطاق', 'أدخل يوم عمل في التايم شيت اليومي فتنشأ الواقعة وأحكام أطرافها');
    ?>
    <style>
        /* UXW-01 ②: أصنافُ الصفحةِ بدلَ الأنماطِ الموضعية — ألوانُها رموزٌ حصرًا */
        .fin-units-tight { padding-bottom: 6px; }
        .fin-units-m0 { margin: 0; }
        .fin-units-queue-card { margin-bottom: 14px; border-right: 4px solid var(--c-d4b06a, #d4b06a); }
        .fin-units-queue-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
        .fin-units-btns { display: flex; gap: 8px; }
        .fin-units-note { margin: 0 0 10px; }
        .fin-units-filter { display: flex; gap: 10px; flex-wrap: wrap; align-items: end; margin-bottom: 12px; }
        .fin-units-flabel { font-size: .85rem; }
        .fin-units-pad10 { padding: 10px; }
        .fin-units-tbl { width: 100%; }
        .fin-units-chk-col { width: 34px; }
        .fin-units-rev { color: var(--c-1a7a3a, #1a7a3a); }
        .fin-units-reason { color: var(--c-9a6a00, #9a6a00); font-size: .82rem; max-width: 340px; white-space: normal; }
        .fin-units-focus { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .fin-units-h5 { margin: 0 0 6px; }
        .fin-units-legend { margin: 0 0 10px; font-size: .9rem; }
        .fin-units-natural { border: 1px solid var(--c-d4b06a, #d4b06a); }
        .fin-units-dim { font-size: .82rem; }
        .fin-units-emptynote { text-align: center; padding: 14px; }
        .fin-units-legacy { opacity: .92; }
        .fin-units-legacy-note { margin: 0 0 10px; font-size: .88rem; }
        .fin-units-src { font-size: .85rem; }
        .fin-units-hidden-form { display: none; }
    </style>
    <?php fin_msg_banner(); ?>

    <div class="card"><div class="card-body fin-units-tight">
        <p class="text-muted fin-units-m0"><i class="fas fa-shield-halved"></i>
        <strong>واقعة واحدة وأحكام أطراف مستقلة.</strong>
        كل يوم عمل واقعة في السجل القانوني، ولكل طرف (العميل · المورد · المشغل)
        <strong>حكمه المستقل</strong> بوحدة عقده وكميته وحالته واستحقاقه — وقد يفوتر العميل بالمتر
        والمورد بالساعة والمشغل بسياسته في اليوم نفسه.
        التساوي بين الأطراف <strong>يعلم تلقائيا حين يقع طبيعيا ولا يشترط أبدا</strong>.
        البيانات مشتقة من إدخال التايم شيت اليومي دون إعادة إدخال.</p>
    </div></div>

    <?php
    // ═══ D02: طابور التحويل المالي — أيامٌ اكتمل اعتمادها التشغيلي وتنتظر الختم ═══
    $q_project = intval($_GET['q_project'] ?? 0);
    $q_period  = trim((string) ($_GET['q_period'] ?? ''));
    $queue = fin_conversion_queue($conn, $is_super_admin, array(
        'project_id' => $q_project ?: null, 'period' => $q_period ?: null, 'limit' => 200));
    // التسعير للصفحة المعروضة وحدها (استعلامان لكل صف — لا يُسعَّر ما لا يُعرض)
    $pricing = $queue ? fin_queue_pricing($conn, $queue) : array();
    $readyCount = 0;
    foreach ($pricing as $pr) { if (!empty($pr['ready'])) { $readyCount++; } }
    ?>
    <div class="card fin-units-queue-card">
        <div class="card-header fin-units-queue-head">
            <h5 class="fin-units-m0"><i class="fas fa-money-check-dollar"></i> طابور اعتماد الأحكام والتحويل المالي
                <span class="badge bg-warning"><?php echo count($queue); ?> يوما بانتظار الختم</span>
                <?php if (!fin_convert_gate_on()): ?>
                    <span class="badge bg-secondary" title="العلم EMS_UNIT_CONVERT_GATE=off">البوابة غير مفعلة — الأثر يتولد تلقائيا عند الاعتماد الرابع</span>
                <?php endif; ?>
            </h5>
            <?php if ($can_edit && $queue): ?>
            <div class="fin-units-btns">
                <button type="button" class="btn btn-sm btn-secondary" onclick="qSelectAll(true)"><i class="fas fa-check-double"></i> حدد الكل</button>
                <button type="button" class="btn btn-sm btn-secondary" onclick="qSelectAll(false)">إلغاء التحديد</button>
                <button type="button" class="btn btn-sm btn-primary" onclick="qConvert()"><i class="fas fa-gavel"></i> اعتماد أحكام المحدد (<span id="qCount">0</span>)</button>
            </div>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <p class="text-muted fin-units-note">
                <i class="fas fa-shield-halved"></i> <strong>مصدر الحقيقة سجل الدوام والسجل القانوني مرآته.</strong>
                هذه الأيام اكتمل اعتمادها التشغيلي (المستويات الأربعة) وتنتظر اعتماد الأحكام —
                <strong>ولحظة الاعتماد تكتب بطاقات الأطراف الثلاث ويتولد الأثر دفعة واحدة</strong>:
                إيراد العميل ومستحق المورد ومستحق المشغل بسياسته وتكلفة المعدة والمشروع.
                <?php echo $can_edit ? 'راجع الأرقام أدناه <strong>قبل</strong> الاعتماد — فالأثر بعد توليده محصن لا يحرر.'
                    : '<strong>لديك صلاحية العرض فقط</strong> — الاعتماد يحتاج صلاحية التعديل على هذه الشاشة.'; ?>
            </p>

                        <!-- صندوقُ الفلاترِ الموحَّد — التصميمُ في assets/css/ems-filters.css -->
            <div class="filter">
                <div class="filter-title"><span class="filter-title-icon"><i class="fa-solid fa-sliders"></i></span> فلاتر البحث</div>
                <div class="filter-body">
            <form method="get" class="fin-units-filter">
                <div><label class="fin-units-flabel" for="fin_units_q_project">المشروع</label><br>
                    <select id="fin_units_q_project" name="q_project" onchange="this.form.submit()"><?php echo fin_project_options($conn, $is_super_admin, $company_id, $q_project); ?></select></div>
                <div><label class="fin-units-flabel" for="fin_units_q_period">الشهر</label><br>
                    <input type="month" id="fin_units_q_period" name="q_period" onchange="this.form.submit()" value="<?php echo htmlspecialchars($q_period); ?>"></div>
                <?php if ($q_project || $q_period !== ''): ?>
                    <a href="unit_records_fin.php" class="btn btn-sm btn-secondary">مسح المرشحات</a>
                <?php endif; ?>
            </form>
                </div>
            </div>

            <?php if (!empty($GLOBALS['fin_queue_error'])): ?>
                <div class="alert alert-danger fin-units-pad10"><i class="fas fa-triangle-exclamation"></i>
                    <strong>تعذر بناء الطابور</strong> — لا تعتبر هذه الشاشة فارغة بحق:
                    <code><?php echo htmlspecialchars((string) $GLOBALS['fin_queue_error']); ?></code></div>
            <?php elseif (!$queue): ?>
                <div class="text-muted fin-units-pad10"><i class="fas fa-check-circle"></i> لا أيام بانتظار الاعتماد ضمن هذا النطاق.</div>
            <?php else: ?>
            <div class="table-container">
                <table id="queueTable" class="display nowrap alltables no-datatable fin-units-tbl" data-no-dt="hard">
                    <thead><tr>
                        <?php if ($can_edit): ?><th class="fin-units-chk-col"><input type="checkbox" id="qAll" aria-label="تحديد كل أيام الطابور" onchange="qSelectAll(this.checked)"></th><?php endif; ?>
                        <th>اليوم</th><th>المرجع</th><th>المشروع</th><th>المعدة</th><th>الكمية</th>
                        <th>الإيراد المتوقع</th><th>مستحق المورد</th><th>الحالة</th><th>اعتمده</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($queue as $row):
                        $tid = intval($row['id']);
                        $pr = isset($pricing[$tid]) ? $pricing[$tid] : array('ready' => false, 'reason' => 'بلا تسعير', 'qty' => null, 'unit' => null, 'revenue' => null, 'due' => null);
                        $ready = !empty($pr['ready']);
                    ?>
                        <tr class="<?php echo $ready ? '' : 'table-warning'; ?>">
                            <?php if ($can_edit): ?>
                            <td><?php if ($ready): ?>
                                <input type="checkbox" class="q-chk" aria-label="تحديد يوم العمل لاعتماد أحكامه" onchange="qCount()" value="<?php echo $tid; ?>">
                            <?php else: ?><span title="غير قابل للتحويل">—</span><?php endif; ?></td>
                            <?php endif; ?>
                            <td><?php echo htmlspecialchars((string) $row['work_date']); ?></td>
                            <td><code><?php echo htmlspecialchars((string) ($row['entry_no'] ?? '')); ?></code>
                                <?php if ($tid > 0): ?><small class="text-muted">TS-<?php echo $tid; ?></small>
                                <?php else: ?><span class="badge bg-danger" title="صف سلسلة بلا مرآة دوام">بلا جسر</span><?php endif; ?></td>
                            <td><?php echo htmlspecialchars((string) ($row['project_name'] ?? '—')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($row['equipment_name'] ?? '—')); ?></td>
                            <td><?php echo $pr['qty'] !== null ? number_format((float) $pr['qty'], 2) . ' ' . fin_unit_label_ar($pr['unit']) : '—'; ?></td>
                            <td><?php echo $pr['revenue'] !== null
                                ? '<strong class="fin-units-rev">' . number_format((float) $pr['revenue'], 2) . '</strong> <small>' . htmlspecialchars((string) $pr['revenue_cur']) . '</small>'
                                : '<span class="text-muted">—</span>'; ?></td>
                            <td><?php echo $pr['due'] !== null
                                ? number_format((float) $pr['due'], 2) . ' <small>' . htmlspecialchars((string) $pr['due_cur']) . '</small>'
                                : '<span class="text-muted">—</span>'; ?></td>
                            <td><?php if ($ready): ?><span class="badge bg-success">جاهز للاعتماد</span>
                                <?php else: ?><span class="badge bg-secondary" title="<?php echo htmlspecialchars((string) $pr['reason']); ?>">متعذر</span><?php endif; ?>
                                <?php if ((string) $pr['reason'] !== ''): ?>
                                    <div class="fin-units-reason"><?php echo htmlspecialchars((string) $pr['reason']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars((string) ($row['approved_by_name'] ?? '—')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($can_edit): ?>
            <form id="qForm" method="post" class="fin-units-hidden-form">
                <input type="hidden" name="action" value="convert_units">
                <input type="hidden" name="csrf_token" value="<?php echo fin_action_token(); ?>">
                <input type="hidden" name="ids" id="qIds" value="">
            </form>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php
    // ═══════════════════════════════════════════════════════════════════════
    // §8.1: وقائع السجل القانوني — رأسُ الواقعة وبطاقاتُ الأحكام الثلاث
    // ───────────────────────────────────────────────────────────────────────
    // القراءة من unit_entries (طبقة الحقيقة) بزمنها المجمَّع من unit_time_log؛
    // والأحكامُ من unit_party_awards حيث حُوِّلت الواقعة — وما لم تُحوَّل فلا
    // أحكامَ بعد (تُكتب لحظة الاعتماد أعلاه) ويُعلَن ذلك لا يُخفى.
    // ═══════════════════════════════════════════════════════════════════════
    $uw_params = array();
    // unit_entries سجلٌّ قانونيٌّ بلا أعمدة حذفٍ أصلًا — التصحيح بالمراجعات لا بالحذف
    $uw_where = "1=1";
    // NAV-01 v6 §13-⑤: البحثُ الموحَّد «يجد الكيانَ أيًّا كان نوعُه — فلا يُسأل
    // المتدربُ عن الشاشة»؛ فالواقعةُ تُفتح برقمها هنا مباشرةً. يقبل رقمَ الواقعة
    // (UNT-…) أو معرّفَها الرقمي — ويُعلَن أن العرضَ محصورٌ بها لا أنه القائمةُ كلُّها.
    $q_entry = trim((string) ($_GET['entry'] ?? ''));
    $entry_focus = null;
    if ($q_entry !== '') {
        if (ctype_digit($q_entry)) { $uw_where .= " AND u.id = ?"; $uw_params[] = intval($q_entry); }
        else { $uw_where .= " AND u.entry_no = ?"; $uw_params[] = $q_entry; }
        $entry_focus = $q_entry;
    }
    if ($q_project > 0) { $uw_where .= " AND u.project_id = ?"; $uw_params[] = $q_project; }
    if ($q_period !== '' && preg_match('/^\d{4}-\d{2}$/', $q_period)) {
        $uw_where .= " AND DATE_FORMAT(u.entry_date, '%Y-%m') = ?"; $uw_params[] = $q_period;
    }
    $proj_scope = fin_project_scope($conn, $ctx);
    if ($proj_scope !== null) { $uw_where .= " AND u.project_id = ?"; $uw_params[] = $proj_scope; }

    $entries = fin_gate($is_super_admin)->scopedQuery(
        array('scope' => array('u' => 'unit_entries'),
              'enrich' => array('p' => 'project', 'e' => 'equipments', 'emp' => 'employees')),
        "SELECT u.id, u.entry_no, u.entry_date, u.shift, u.unit_type, u.qty, u.state,
                u.capacity_flag, u.sync_uuid, u.project_id,
                p.name AS project_name, e.name AS equipment_name, emp.name AS operator_name
           FROM unit_entries u
           LEFT JOIN project p ON p.id = u.project_id
           LEFT JOIN equipments e ON e.id = u.equipment_id
           LEFT JOIN employees emp ON emp.id = u.operator_employee_id
          WHERE {TENANT_SCOPE} AND " . $uw_where . "
          ORDER BY u.entry_date DESC, u.id DESC LIMIT 300",
        $uw_params);

    // الزمن المجمَّع (فعلي/استعداد/توقف) دفعةً واحدة
    $timeAgg = array();
    if ($entries) {
        $eids = array();
        foreach ($entries as $en) { $eids[] = intval($en['id']); }
        $in = implode(',', $eids);
        $tr = fin_gate($is_super_admin)->scopedQuery(
            array('scope' => array('l' => 'unit_time_log')),
            "SELECT l.entry_id,
                    ROUND(SUM(CASE WHEN l.ops_state = 'actual_work' THEN l.hours ELSE 0 END), 2) AS actual_h,
                    ROUND(SUM(CASE WHEN l.ops_state = 'standby' THEN l.hours ELSE 0 END), 2) AS standby_h,
                    ROUND(SUM(CASE WHEN l.ops_state NOT IN ('actual_work','standby') THEN l.hours ELSE 0 END), 2) AS stop_h
               FROM unit_time_log l
              WHERE {TENANT_SCOPE} AND l.entry_id IN ({$in})
              GROUP BY l.entry_id", array());
        foreach ($tr as $t) { $timeAgg[intval($t['entry_id'])] = $t; }
    }

    // أحكامُ الأطراف للوقائع المعروضة (المفتاح: sync_uuid = ts:<id>)
    $awards = array();
    if ($entries) {
        $tsRefs = array();
        foreach ($entries as $en) {
            if (strpos((string) $en['sync_uuid'], 'ts:') === 0) { $tsRefs[] = intval(substr($en['sync_uuid'], 3)); }
        }
        if ($tsRefs) {
            $in = implode(',', $tsRefs);
            $ar = fin_gate($is_super_admin)->scopedQuery(
                array('scope' => array('a' => 'unit_party_awards')),
                "SELECT a.source_ref, a.party, a.award_unit_type, a.award_qty, a.entitlement_state,
                        a.entitlement_pct, a.qty_due, a.unit_price, a.currency, a.policy_rule, a.unavailable_reason
                   FROM unit_party_awards a
                  WHERE {TENANT_SCOPE} AND a.source_kind = 'timesheet' AND a.source_ref IN ({$in})
                    AND a.deleted_at IS NULL
                  ORDER BY a.id", array());
            foreach ($ar as $a) { $awards[intval($a['source_ref'])][$a['party']] = $a; }
        }
    }

    $UNIT_AR = array('hour' => 'ساعة', 'ton' => 'طن', 'meter' => 'متر', 'cbm' => 'م³', 'day' => 'يوم', 'shift' => 'وردية', 'trip' => 'نقلة');
    $STATE_AR = array('due' => array('مستحقة', 'success'), 'partial' => array('جزئية', 'warning'),
                      'not_due' => array('غير مستحقة', 'secondary'), 'pending' => array('معلقة', 'info'),
                      'rejected' => array('مرفوضة', 'danger'), 'settlement' => array('تسوية', 'warning'));
    $PARTY_AR = array('client' => 'العميل', 'supplier' => 'المورد', 'operator' => 'المشغل');
    $ENTRY_STATE_AR = array('draft' => 'مسودة', 'submitted' => 'مرسلة', 'site_approved' => 'اعتماد الموقع',
                            'parties_approved' => 'اعتماد الأطراف', 'sales_approved' => 'اعتماد المبيعات',
                            'chain_completed' => 'مكتملة', 'returned_to_site' => 'معادة');

    function upa_card($a, $UNIT_AR, $STATE_AR) {
        if ($a === null) { return "<span class='text-muted'>—</span>"; }
        if ($a['unavailable_reason'] !== null && $a['unavailable_reason'] !== '') {
            return "<span class='badge badge-secondary' title='" . htmlspecialchars((string) $a['unavailable_reason'], ENT_QUOTES) . "'>متعذر بسببه</span>";
        }
        $st = isset($STATE_AR[$a['entitlement_state']]) ? $STATE_AR[$a['entitlement_state']] : array($a['entitlement_state'], 'secondary');
        $unit = isset($UNIT_AR[$a['award_unit_type']]) ? $UNIT_AR[$a['award_unit_type']] : $a['award_unit_type'];
        $price = $a['unit_price'] !== null ? ' × ' . number_format((float) $a['unit_price'], 2) . ' ' . htmlspecialchars((string) $a['currency']) : '';
        return "<span class='badge badge-" . $st[1] . "'>" . $st[0] . "</span> "
             . number_format((float) $a['award_qty'], 2) . ' ' . $unit . $price;
    }
    ?>
    <div class="card"><div class="card-body">
        <?php if ($entry_focus !== null): ?>
            <div class="alert alert-info fin-units-focus">
                <i class="fas fa-crosshairs"></i>
                <span>العرض محصور بالواقعة <code><?php echo htmlspecialchars($entry_focus, ENT_QUOTES, 'UTF-8'); ?></code>
                    <?php if (empty($entries)): ?>
                        — <strong>لا واقعة بهذا الرقم ضمن نطاقك</strong> (قد تكون في مشروع خارج صلاحيتك).
                    <?php endif; ?></span>
                <a class="btn btn-sm btn-secondary" href="unit_records_fin.php">اعرض كل الوقائع</a>
            </div>
        <?php endif; ?>
        <h5 class="fin-units-h5"><i class="fas fa-scale-balanced"></i> وقائع السجل القانوني وأحكام أطرافها
            <span class="badge badge-info"><?php echo count($entries); ?> واقعة</span></h5>
        <p class="text-muted fin-units-legend">
            لكل واقعة ثلاث بطاقات حكم مستقلة — والواقعة غير المحولة أحكامها
            <strong>لم تكتب بعد</strong> (تكتب لحظة الاعتماد من الطابور أعلاه).
            علامة <span class="badge badge-light fin-units-natural">⚖ تساو طبيعي</span>
            تظهر حين تتفق وحدتا العميل والمورد وكميتاهما وفق العقود — إعلاما لا شرطا.</p>
        <div class="table-container">
            <table id="entriesTable" class="display nowrap alltables fin-units-tbl" data-page-length="25" data-order='[]' data-state-save="false">
                <thead><tr>
                    <th>الواقعة</th><th>التاريخ</th><th>الوردية</th><th>المشروع</th><th>المعدة</th><th>المشغل</th>
                    <th>الكمية المسجلة</th><th>الزمن (فعلي·استعداد·توقف)</th><th>حالة السلسلة</th>
                    <th>حكم العميل</th><th>حكم المورد</th><th>حكم المشغل</th><th></th>
                    <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                    <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
                    <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المنشئ — الاسم والصفة</th>
                    <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمد — الاسم والصفة</th>
                    <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                    <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                    <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                    <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                    <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                    </tr></thead>
                <tbody>
                <?php foreach ($entries as $en):
                    $eid = intval($en['id']);
                    $tsRef = (strpos((string) $en['sync_uuid'], 'ts:') === 0) ? intval(substr($en['sync_uuid'], 3)) : 0;
                    $aw = isset($awards[$tsRef]) ? $awards[$tsRef] : array();
                    $tg = isset($timeAgg[$eid]) ? $timeAgg[$eid] : null;
                    $unit = isset($UNIT_AR[$en['unit_type']]) ? $UNIT_AR[$en['unit_type']] : $en['unit_type'];
                    // التساوي الطبيعي: يُعلَّم حين يقع — ولا يُشترط أبدًا (§8.1)
                    $natural = isset($aw['client'], $aw['supplier'])
                        && $aw['client']['unavailable_reason'] === null && $aw['supplier']['unavailable_reason'] === null
                        && $aw['client']['award_unit_type'] === $aw['supplier']['award_unit_type']
                        && abs((float) $aw['client']['award_qty'] - (float) $aw['supplier']['award_qty']) < 0.005;
                ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars((string) $en['entry_no']); ?></code>
                            <?php if (intval($en['capacity_flag']) === 1): ?>
                                <span class="badge badge-danger" title="تجاوز طاقة بانتظار المراجعة">⚠ طاقة</span>
                            <?php endif; ?></td>
                        <td><?php echo htmlspecialchars((string) $en['entry_date']); ?></td>
                        <td><?php echo $en['shift'] === 'night' ? 'ليلية' : 'نهارية'; ?></td>
                        <td><?php echo htmlspecialchars((string) ($en['project_name'] ?? '#' . $en['project_id'])); ?></td>
                        <td><?php echo htmlspecialchars((string) ($en['equipment_name'] ?? '—')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($en['operator_name'] ?? '—')); ?></td>
                        <td><strong><?php echo number_format((float) $en['qty'], 2); ?></strong> <?php echo $unit; ?></td>
                        <td><?php echo $tg
                            ? number_format((float) $tg['actual_h'], 1) . ' · ' . number_format((float) $tg['standby_h'], 1) . ' · ' . number_format((float) $tg['stop_h'], 1)
                            : '<span class="text-muted">—</span>'; ?></td>
                        <td><span class="badge badge-<?php echo $en['state'] === 'chain_completed' ? 'success' : 'info'; ?>">
                            <?php echo $ENTRY_STATE_AR[$en['state']] ?? $en['state']; ?></span></td>
                        <td><?php echo upa_card(isset($aw['client']) ? $aw['client'] : null, $UNIT_AR, $STATE_AR); ?></td>
                        <td><?php echo upa_card(isset($aw['supplier']) ? $aw['supplier'] : null, $UNIT_AR, $STATE_AR); ?></td>
                        <td><?php echo upa_card(isset($aw['operator']) ? $aw['operator'] : null, $UNIT_AR, $STATE_AR); ?></td>
                        <td><?php if ($natural): ?>
                            <span class="badge badge-light fin-units-natural" title="اتفقت وحدتا العميل والمورد وكميتاهما وفق العقود — إعلام لا شرط">⚖ تساو طبيعي</span>
                        <?php elseif (!$aw): ?>
                            <span class="text-muted fin-units-dim" title="الأحكام تكتب لحظة الاعتماد من الطابور">لم تحكم بعد</span>
                        <?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (empty($entries)): ?>
            <p class="text-muted fin-units-emptynote"><i class="fas fa-circle-info"></i>
                لا وقائع في السجل القانوني ضمن هذا النطاق — تنشأ الوقائع من إدخال التايم شيت اليومي (الكتابة المزدوجة).</p>
        <?php endif; ?>
    </div></div>

    <?php
    // ═══ السجل اليدوي القديم — قسمٌ تاريخيٌّ مجمّد (قراءةً حصرًا) ═══
    // كان قبل السجل القانوني؛ 8 من صفوفه العشرة بمالٍ مولَّدٍ مربوطٍ بروابطه
    // فلا يُمسّ. لا إضافةَ ولا تعديلَ ولا اعتمادَ بعد اليوم — والصفان غير
    // المعتمدَين شاهدان على المفهوم القديم لا عملٌ معلَّق.
    $legacy_rows = fin_gate($is_super_admin)->scopedQuery(
        array('scope' => array('u' => 'fin_unit_records'), 'enrich' => array('p' => 'project')),
        "SELECT u.*, p.name AS project_name FROM fin_unit_records u
         LEFT JOIN project p ON p.id = u.project_id
         WHERE {TENANT_SCOPE} AND COALESCE(u.is_deleted,0)=0
         ORDER BY u.record_date DESC, u.id DESC", array());
    ?>
    <?php if ($legacy_rows): ?>
    <div class="card fin-units-legacy">
        <div class="card-header"><h5 class="fin-units-m0"><i class="fas fa-box-archive"></i> السجل اليدوي القديم
            <span class="badge badge-secondary">مجمد — قراءة فقط</span></h5></div>
        <div class="card-body">
        <p class="text-muted fin-units-legacy-note">
            سجلات أدخلت يدويا قبل قيام السجل القانوني — مالها المولد باق بروابطه ولا يعاد توليده،
            ولا إدخال يدويا بعد اليوم: كل يوم عمل يبدأ من التايم شيت.</p>
        <div class="table-container">
            <table id="legacyTable" class="display nowrap alltables fin-units-tbl" data-page-length="10" data-order='[]' data-state-save="false">
                <thead><tr>
                    <th>الرقم</th><th>التاريخ</th><th>المشروع</th><th>النموذج</th>
                    <th>تشغيل</th><th>عميل</th><th>مورد</th><th>الحالة</th><th>هامش</th><th>المرجع</th>
                </tr></thead>
                <tbody>
                <?php foreach ($legacy_rows as $row) {
                    $ms = (string) $row['match_state'];
                    $tone = $ms === 'approved' ? 'success' : ($ms === 'matched' ? 'primary' : ($ms === 'variance' ? 'danger' : 'secondary'));
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars((string) $row['record_no']) . "</td>";
                    echo "<td>" . htmlspecialchars((string) $row['record_date']) . "</td>";
                    echo "<td>" . htmlspecialchars((string) ($row['project_name'] ?? '#' . $row['project_id'])) . "</td>";
                    echo "<td>" . htmlspecialchars($work_models[$row['work_model']] ?? $row['work_model']) . "</td>";
                    echo "<td>" . number_format((float) $row['ops_qty'], 2) . "</td>";
                    echo "<td>" . ($row['client_qty'] !== null ? number_format((float) $row['client_qty'], 2) : '—') . "</td>";
                    echo "<td>" . ($row['supplier_qty'] !== null ? number_format((float) $row['supplier_qty'], 2) : '—') . "</td>";
                    echo "<td><span class='badge badge-" . $tone . "'>" . htmlspecialchars($match_states[$ms] ?? $ms) . "</span></td>";
                    echo "<td>" . ($ms === 'approved' ? number_format((float) $row['unit_margin'], 2) : '—') . "</td>";
                    echo "<td class='fin-units-src'>" . htmlspecialchars((string) ($row['source_ref'] ?? '')) . "</td>";
                    echo "</tr>";
                } ?>
                </tbody>
            </table>
        </div>
    </div></div>
    <?php endif; ?>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function () {
    /* UXW-01 ⑤: التهيئتان المحليتان أُزيلتا — المكوّنُ المركزيُّ في assets/js/ui-unification.js
       يلتقط #entriesTable و#legacyTable آليًّا، والسلوكُ معلَنٌ بسماتِ <table>.
       و#queueTable يبقى ساكنًا بسمةِ خروجٍ صريحة: مربعاتُ التحديد تلزمها الصفحةُ كاملة. */
});

// ═══ طابور اعتماد الأحكام — نمط شاشة الاعتمادات نفسه (سجل/مجموعة/الكل) ═══
// الجدول بلا DataTables (no-datatable) فمربعات التحديد تبقى في الصفحة كلها.
function qSelectAll(on) {
    document.querySelectorAll('.q-chk').forEach(function (c) { c.checked = !!on; });
    var all = document.getElementById('qAll'); if (all) { all.checked = !!on; }
    qCount();
}
function qSelected() {
    return Array.prototype.slice.call(document.querySelectorAll('.q-chk:checked')).map(function (c) { return c.value; });
}
function qCount() {
    var n = qSelected().length;
    var el = document.getElementById('qCount'); if (el) { el.textContent = n; }
    return n;
}
function qConvert() {
    var ids = qSelected();
    if (!ids.length) { alert('حدد يوما واحدا على الأقل.'); return; }
    // تأكيدٌ يعرض الأثر قبل وقوعه — الأثر بعد توليده محصَّنٌ لا يُحرَّر
    if (!confirm('اعتماد أحكام ' + ids.length + ' يوما وتحويلها ماليا؟\n\n'
        + 'ستكتب لكل يوم بطاقات الأطراف الثلاث ويتولد أثره:\n'
        + 'إيراد العميل + مستحق المورد + مستحق المشغل بسياسته + تكلفة المعدة والمشروع.\n'
        + 'لا يمكن التراجع عن الأثر بعد توليده.')) { return; }
    document.getElementById('qIds').value = ids.join(',');
    document.getElementById('qForm').submit();
}
</script>
</body>
</html>
