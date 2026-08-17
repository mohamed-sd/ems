<?php
/**
 * Operations/shift_entry.php — قيدُ الوردية اليومي
 * ═══════════════════════════════════════════════════════════════════════════
 * «سطرٌ واحدٌ لكلِّ آليةٍ في كلِّ ورديةٍ في كلِّ يوم» — المواصفة 70 · TSP-0013.
 *
 * ── الترتيبُ الإلزاميُّ في هذا الملف (TS-08) ────────────────────────────
 *   ① جلسة  ② إعداد  ③ حارسُ شاشة  ④ حارسُ فعل  ⑤ رمزُ حماية
 *   ⑥ معالجُ POST  ⑦ الاستعلامات  ⑧ العرض
 *   ولا كتابةَ قبلَ الحارسِ البتة — وفاحصٌ يقارن رقمَ سطرِ الحارسِ بأولِ كتابة.
 *
 * ── الخدمةُ تكتب لا الشاشة (TS-09) ──────────────────────────────────────
 * صفرُ INSERT/UPDATE في هذا الملف. كلُّ كتابةٍ عبر
 * `App\Services\Unit\TimesheetEntryService::submit()` — وهي صاحبةُ آلةِ
 * الحالاتِ والعطالةِ وحارسِ الطاقة.
 *
 * ── خريطةُ الأسماء: المواصفة ⇐ المخططُ الحيّ ───────────────────────────
 *   work_date ⇐ entry_date · shift_no ⇐ shift · machine_code ⇐ equipment_id
 *   operator_id ⇐ operator_employee_id · supplier_id ⇐ supplier_entity_id
 *   field_notes ⇐ note · entry_state ⇐ state · created_by ⇐ entered_by
 * وساعاتُ التشغيلِ والاستعدادِ والتعطلِ **ليست أعمدةً هنا**: موضعُها
 * `unit_time_log` مطبَّعةً بـ`ops_state` (عشرُ حالات) و`resp_party` — فهي
 * تحتمل أكثرَ من سببِ توقفٍ في الورديةِ الواحدةِ لكلٍّ ساعاتُه وطرفُه.
 *
 * ◆ ولا قيمةَ محسوبةً تُكتب: وسيطُ ساعةِ اليومِ وأيامُ العملِ والسعةُ
 *   استعلاماتٌ من القيدِ لا أعمدةٌ فيه.
 * ═══════════════════════════════════════════════════════════════════════════
 */

/* ═══ ① الجلسة ═══════════════════════════════════════════════════════ */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit();
}

/* ═══ ② الإعداد ══════════════════════════════════════════════════════ */
include '../config.php';
require_once '../includes/permissions_helper.php';
require_once '../includes/security.php';

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$uid            = intval($_SESSION['user']['id'] ?? 0);
$role_id        = intval($_SESSION['user']['role'] ?? 0);

/* ═══ ③ حارسُ الشاشة — قبلَ أيِّ قراءةٍ أو كتابة ══════════════════════ */
enforce_current_page_view_permission($conn, '../main/dashboard.php');
$PERM = check_page_permissions($conn, 'Operations/shift_entry.php');
if (empty($PERM['can_view'])) {
    http_response_code(403);
    echo '<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>'
       . '<div dir="rtl" class="se-deny">'
       . 'لا تملك صلاحيةَ فتحِ شاشةِ قيدِ الوردية — راجِعْ مديرَك.</div>';
    exit();
}
if (!$is_super_admin && $company_id <= 0) {
    http_response_code(403);
    echo ''
       . '<div dir="rtl" class="se-deny">'
       . 'جلستُك بلا كيانٍ محدَّد — سجّلْ خروجًا ثم دخولًا، فإن تكرّر راجِعْ مديرَك.</div>';
    exit();
}

/* ═══ ④ حارسُ الفعل ══════════════════════════════════════════════════ */
$CAN_RECORD = !empty($PERM['can_add']);   // shift.entry.record
$CAN_VOID   = !empty($PERM['can_edit']);  // shift.entry.void

/* ═══ ⑤ رمزُ الحماية + ⑥ معالجُ POST ═════════════════════════════════ */
$flash = null;      // ['kind'=>ok|warn|err, 'text'=>..., 'ref'=>...]
$formOld = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = isset($_POST['action']) ? trim((string) $_POST['action']) : '';

    if (!verify_csrf_token(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
        $flash = ['kind' => 'err', 'text' => 'انتهت صلاحيةُ الصفحة. أعِدْ تحميلَها ثم احفظْ من جديد.'];
    } elseif ($act === 'record') {
        if (!$CAN_RECORD) {
            http_response_code(403);
            $flash = ['kind' => 'err', 'text' => 'لا تملك صلاحيةَ تسجيلِ قيدِ الوردية — راجِعْ مديرَك.'];
        } else {
            $formOld = $_POST;
            /* الحقولُ الإلزاميةُ تُفحص هنا بأسمائِها العربيةِ قبلَ نداءِ الخدمة،
               فيرى المبتدئُ ما نقص لا رمزَ حقلٍ إنجليزيّ. */
            $need = [
                'entry_date'   => 'التاريخ',
                'equipment_id' => 'الآلية',
                'shift'        => 'الوردية',
                'qty'          => 'الكمية المنفَّذة',
                'unit_type'    => 'وحدة القياس',
            ];
            $missingAr = [];
            foreach ($need as $k => $ar) {
                if (!isset($_POST[$k]) || trim((string) $_POST[$k]) === '') { $missingAr[] = $ar; }
            }
            $hoursRows = [];
            $hs = isset($_POST['h_state']) && is_array($_POST['h_state']) ? $_POST['h_state'] : [];
            $hh = isset($_POST['h_hours']) && is_array($_POST['h_hours']) ? $_POST['h_hours'] : [];
            $hp = isset($_POST['h_party']) && is_array($_POST['h_party']) ? $_POST['h_party'] : [];
            $hn = isset($_POST['h_note'])  && is_array($_POST['h_note'])  ? $_POST['h_note']  : [];
            foreach ($hs as $i => $st) {
                $hv = isset($hh[$i]) ? (float) $hh[$i] : 0;
                if ($st === '' || $hv <= 0) { continue; }
                $hoursRows[] = [
                    'ops_state'  => (string) $st,
                    'hours'      => $hv,
                    'resp_party' => isset($hp[$i]) ? (string) $hp[$i] : 'none',
                    'cause_note' => isset($hn[$i]) ? trim((string) $hn[$i]) : '',
                ];
            }
            if (!$hoursRows) { $missingAr[] = 'توزيعُ ساعاتِ الوردية (سطرٌ واحدٌ على الأقل)'; }

            $sumH = 0.0;
            foreach ($hoursRows as $r) { $sumH += $r['hours']; }
            if ($sumH > 24) {
                $missingAr[] = 'مجموعُ الساعات ' . number_format($sumH, 2) . ' ساعة — واليومُ ٢٤ ساعة';
            }

            if ($missingAr) {
                $flash = ['kind' => 'warn',
                          'text' => 'لم يُحفظ. أكمِلْ أولًا: ' . implode(' · ', $missingAr)];
            } else {
                require_once __DIR__ . '/../app/Services/Unit/TimesheetEntryService.php';
                $gate = ems_tenant_db();
                $input = [
                    'company_id'   => $company_id,
                    'equipment_id' => (int) $_POST['equipment_id'],
                    'date'         => trim((string) $_POST['entry_date']),
                    'shift'        => trim((string) $_POST['shift']),
                    'qty'          => (float) $_POST['qty'],
                    'unit_type'    => trim((string) $_POST['unit_type']),
                    'source_ref'   => trim((string) ($_POST['source_ref'] ?? '')) !== ''
                                        ? trim((string) $_POST['source_ref'])
                                        : ('SHIFT-' . trim((string) $_POST['entry_date']) . '-' . (int) $_POST['equipment_id']),
                    'time_lines'   => $hoursRows,
                    'note'         => trim((string) ($_POST['note'] ?? '')),
                    /* التسعةُ الجديدةُ — مدخلاتٌ خامٌّ لا محسوبات */
                    'meter_before'      => $_POST['meter_before'] !== '' ? (float) $_POST['meter_before'] : null,
                    'meter_after'       => $_POST['meter_after']  !== '' ? (float) $_POST['meter_after']  : null,
                    'fuel_received_qty' => (float) ($_POST['fuel_received_qty'] ?? 0),
                    'fuel_issued_qty'   => (float) ($_POST['fuel_issued_qty'] ?? 0),
                    'container_key'     => trim((string) ($_POST['container_key'] ?? '')),
                    'created_by_role'   => $role_id,
                ];
                $res = \App\Services\Unit\TimesheetEntryService::submit($conn, $gate, $input, $uid);

                if (!empty($res['ok']) && !empty($res['existing'])) {
                    $flash = ['kind' => 'warn',
                              'text' => 'هذا القيدُ مسجَّلٌ سلفًا لهذه الآليةِ في هذه الورديةِ من هذا اليوم — لم يُضَفْ قيدٌ ثانٍ.',
                              'ref'  => $res['entry']['entry_no'] ?? null];
                } elseif (!empty($res['ok'])) {
                    $flash = ['kind' => 'ok', 'text' => 'حُفظ القيدُ وأُرسل للمراجعة.',
                              'ref'  => $res['entry']['entry_no'] ?? null];
                    $formOld = [];
                } else {
                    $why = !empty($res['missing']) ? implode(' · ', (array) $res['missing']) : 'سببٌ غيرُ معلوم';
                    $flash = ['kind' => 'err', 'text' => 'لم يُحفظ — ' . $why];
                }
            }
        }
    } elseif ($act === 'void') {
        if (!$CAN_VOID) {
            http_response_code(403);
            $flash = ['kind' => 'err', 'text' => 'لا تملك صلاحيةَ إلغاءِ القيود — راجِعْ مديرَك.'];
        } else {
            $vid    = (int) ($_POST['entry_id'] ?? 0);
            $reason = trim((string) ($_POST['void_reason'] ?? ''));
            if ($vid <= 0 || $reason === '') {
                $flash = ['kind' => 'warn', 'text' => 'لم يُلغَ. اختَرِ القيدَ واكتبْ سببَ الإلغاء.'];
            } else {
                require_once __DIR__ . '/../app/Services/Unit/TimesheetEntryService.php';
                $vres = \App\Services\Unit\TimesheetEntryService::voidEntry(
                    $conn, ems_tenant_db(), $company_id, $vid, $reason, $uid);
                if (!empty($vres['ok'])) {
                    $flash = ['kind' => 'ok',
                              'text' => 'أُلغي القيدُ بحركةٍ عاكسة — ولم يُحذف. والخانةُ صارت متاحةً لقيدٍ بديل.',
                              'ref'  => 'REV-' . str_pad((string) $vres['void_entry_id'], 6, '0', STR_PAD_LEFT)];
                } else {
                    $flash = ['kind' => 'err',
                              'text' => 'لم يُلغَ — ' . implode(' · ', (array) ($vres['reasons'] ?? []))];
                }
            }
        }
    }
}

/* ═══ ⑦ الاستعلامات — قراءةٌ فقط ═════════════════════════════════════ */
$today = date('Y-m-d');

/* آلياتُ الكيان — `code` هو رقمُ الآليةِ في هذا المخطط (لا equipment_number)،
   و`equipments` بلا عمودِ حذفٍ ناعم. و`prepare` يعيد false ولا يرمي (درس H-01)
   فيُفحص المُرجَعُ قبل النداء وإلا سقطت الشاشةُ 500. */
$equipments = [];
$st = $conn->prepare("SELECT id, TRIM(CONCAT(COALESCE(NULLIF(code,''), CONCAT('#', id)),
                                            ' — ', COALESCE(NULLIF(name,''), ''))) AS code
                      FROM equipments
                      WHERE (company_id = ? OR ? = 1)
                      ORDER BY code LIMIT 500");
if ($st) {
    $sa = $is_super_admin ? 1 : 0;
    $st->bind_param('ii', $company_id, $sa);
    $st->execute();
    $rs = $st->get_result();
    while ($x = $rs->fetch_assoc()) { $equipments[] = $x; }
    $st->close();
} else {
    error_log('shift_entry: تعذّر تحضيرُ استعلامِ الآليات — ' . $conn->error);
}

/* قيودُ اليومِ الجاريةِ — لعرضِ ما سُجِّل حتى الآن */
$todayRows = [];
$st = $conn->prepare(
    "SELECT ue.id, ue.entry_no, ue.entry_date, ue.shift, ue.equipment_id, ue.qty, ue.unit_type,
            ue.state, ue.meter_before, ue.meter_after, ue.fuel_issued_qty,
            COALESCE(NULLIF(e.code,''), CONCAT('#', ue.equipment_id)) AS machine,
            (SELECT ROUND(SUM(l.hours),2) FROM unit_time_log l WHERE l.entry_id = ue.id) AS total_hours,
            (SELECT ROUND(SUM(l.hours),2) FROM unit_time_log l WHERE l.entry_id = ue.id AND l.ops_state='actual_work') AS run_h
     FROM unit_entries ue
     LEFT JOIN equipments e ON e.id = ue.equipment_id
     WHERE ue.company_id = ? AND ue.entry_date = ?
     ORDER BY ue.id DESC LIMIT 100");
if ($st) {
    $st->bind_param('is', $company_id, $today);
    $st->execute();
    $rs = $st->get_result();
    while ($x = $rs->fetch_assoc()) { $todayRows[] = $x; }
    $st->close();
} else {
    error_log('shift_entry: تعذّر تحضيرُ استعلامِ قيودِ اليوم — ' . $conn->error);
}

/* الحالاتُ العشرُ من التعدادِ الحيِّ لا من قائمةٍ مكتوبةٍ يدويًّا */
$OPS_STATES = [];
$rs = $conn->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'unit_time_log'
                      AND COLUMN_NAME = 'ops_state'");
if ($rs && ($ct = $rs->fetch_row())) {
    if (preg_match_all("/'([^']+)'/", $ct[0], $m)) { $OPS_STATES = $m[1]; }
}
$OPS_AR = [
    'actual_work' => 'تشغيلٌ فعلي', 'standby' => 'استعداد', 'tech_breakdown' => 'تعطلٌ فني',
    'supplier_stop' => 'توقفٌ بسبب المورد', 'operator_stop' => 'توقفٌ بسبب المشغّل',
    'client_stop' => 'توقفٌ بسبب العميل', 'fuel_logistics_stop' => 'توقفٌ وقودٍ أو نقل',
    'planned_stop' => 'توقفٌ مخطَّط', 'force_majeure' => 'قوةٌ قاهرة', 'unlogged' => 'غيرُ مسجَّل',
];
$PARTY_AR = ['company' => 'الشركة', 'client' => 'العميل', 'supplier' => 'المورد',
             'operator' => 'المشغّل', 'planned' => 'مخطَّط', 'force_majeure' => 'قوةٌ قاهرة', 'none' => 'لا طرف'];
/* لونُ الشارةِ صنفُ CSS من كتلةِ الشاشة (UXW-01 بوابة ١) — لا لونَ حرفيًّا في PHP */
$STATE_AR = ['draft' => ['مسوَّدة', 'se-b-muted'], 'submitted' => ['بانتظار اعتماد الموقع', 'se-b-warn'],
             'site_approved' => ['اعتمدها الموقع', 'se-b-ok'], 'parties_review' => ['مراجعةُ الأطراف', 'se-b-warn'],
             'parties_approved' => ['اعتمدها الأطراف', 'se-b-ok'], 'sales_approved' => ['اعتمدتها المبيعات', 'se-b-ok'],
             'returned' => ['أُعيد للموقع', 'se-b-err'], 'converted' => ['حُوِّل', 'se-b-ok']];

$old = function (string $k, $d = '') use ($formOld) {
    return isset($formOld[$k]) ? htmlspecialchars((string) $formOld[$k], ENT_QUOTES, 'UTF-8') : $d;
};

/* ═══ ⑧ العرض — الغلافُ الموحَّد (توحيدُ التصميم 2026-08-17) ═══════════════
   كانت الشاشةُ تُصيَّر بغلافٍ خاصٍّ (`se-wrap` · `se-card` · `se-grid` · `se-f`)
   لا يشبه بقيةَ النظام: بلا `.main.ems-unified-page-shell` وبلا رأسٍ موحَّد.
   ولم يكن الفارقُ شكلًا فحسب — الرأسُ الموحَّدُ يحمل زرَّ «عن الشاشة» وسطرَ
   السياقِ ومرساةَ شريطِ الرحلة، وثلاثتُها تسقط بغيابِه.
   والأنماطُ الخاصةُ رُفعت من `ems-screens.css`: ما تحمله العُدَّةُ لا يُكرَّر. */
/* `$page_title` قبلَ القشرةِ لا بعدَها: `inheader.php` يقرؤه في السطر 39، وكان
   غائبًا فيُسجَّل تحذيرُ «متغيّرٌ غيرُ معرَّف» في **كلِّ** فتحةِ صفحة (مقيسٌ في
   السجلِّ منذ 06:45)، ويخرج عنوانُ التبويبِ فارغًا. */
$page_title = 'إيكوبيشن | قيدُ الوردية اليومي';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell ems-doc-cycle" dir="rtl">
<?php
$header_title   = 'قيدُ الوردية اليومي';
$header_icon    = 'fa fa-clipboard-check';
$header_actions = array();
$header_back    = array('href' => '../main/dashboard.php', 'class' => '',
                        'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
include __DIR__ . '/../includes/page_header.php';

/* بوابة ١٢: قيدُ الورديةِ دورةٌ مستندية — خطوتُها التالية مُعلَنة */
echo ems_next_step('مراجعةُ الموقعِ واعتمادُ القيدِ المُرسَل');
/* حزمةُ الحالاتِ الدنيا (بوابة ٩) — مخفيةٌ افتراضًا ويُظهرها منطقُ الشاشة */
echo ems_states_bundle('لم يُسجَّل قيدُ ورديةٍ بعدُ', 'ابدأ بنموذجِ القيدِ أعلى الشاشة');
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['kind'] === 'ok' ? 'success' : ($flash['kind'] === 'warn' ? 'warning' : 'danger') ?>">
      <span><?= htmlspecialchars($flash['text'], ENT_QUOTES, 'UTF-8') ?><?php
        if (!empty($flash['ref'])): ?> — رقمُ القيد: <strong><?= htmlspecialchars((string) $flash['ref'], ENT_QUOTES, 'UTF-8') ?></strong><?php endif; ?></span>
    </div>
<?php endif; ?>

<?php if (!$CAN_RECORD): ?>
    <div class="alert alert-warning">
      <span>تستطيع الاطّلاعَ ولا تستطيع التسجيل. لا تملك صلاحيةَ تسجيلِ قيدِ الوردية.</span>
    </div>
<?php endif; ?>

  <?php /* `ems-form` لا `allforms`: هذه شاشةُ إدخالٍ يوميٍّ نموذجُها هو العمل،
           فيبقى مفتوحًا. والصنفُ يعطي جلدَ الحقولِ الموحَّدَ بلا سلوكِ الطيّ. */ ?>
  <form method="post" autocomplete="off" class="ems-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="record">
    <div class="card">
      <div class="card-header"><h5><i class="fa fa-clipboard-check"></i> قيدُ الوردية اليومي</h5></div>
      <div class="card-body">
        <div class="alert alert-info">
          <i class="fa fa-circle-info"></i>
          <span>سجّلْ سطرًا واحدًا لكلِّ آليةٍ في كلِّ ورديةٍ في كلِّ يوم.
            الحقولُ الموسومةُ <span class="se-req">*</span> إلزاميةٌ قبلَ الحفظ.</span>
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label for="se_date">التاريخ <span class="se-req">*</span></label>
            <input type="date" name="entry_date" id="se_date" aria-label="التاريخ" value="<?= $old('entry_date', $today) ?>" required>
          </div>
          <div class="form-group">
            <label for="se_equip">الآلية <span class="se-req">*</span></label>
            <select name="equipment_id" id="se_equip" aria-label="الآلية" required>
              <option value="">— اختر الآلية —</option>
<?php foreach ($equipments as $e): ?>
              <option value="<?= (int) $e['id'] ?>" <?= $old('equipment_id') == $e['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($e['code'], ENT_QUOTES, 'UTF-8') ?>
              </option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="se_shift">الوردية <span class="se-req">*</span></label>
            <select name="shift" id="se_shift" aria-label="الوردية" required>
              <option value="day"   <?= $old('shift') === 'day' ? 'selected' : '' ?>>نهار</option>
              <option value="night" <?= $old('shift') === 'night' ? 'selected' : '' ?>>ليل</option>
            </select>
          </div>
          <div class="form-group">
            <label for="se_qty">الكمية المنفَّذة <span class="se-req">*</span></label>
            <input type="number" step="0.01" min="0" name="qty" id="se_qty" aria-label="الكمية المنفَّذة" value="<?= $old('qty') ?>" required>
          </div>
          <div class="form-group">
            <label for="se_unit">وحدة القياس <span class="se-req">*</span></label>
            <select name="unit_type" id="se_unit" aria-label="وحدة القياس" required>
              <option value="hour"  <?= $old('unit_type', 'hour') === 'hour'  ? 'selected' : '' ?>>ساعة</option>
              <option value="ton"   <?= $old('unit_type') === 'ton'   ? 'selected' : '' ?>>طن</option>
              <option value="meter" <?= $old('unit_type') === 'meter' ? 'selected' : '' ?>>متر</option>
              <option value="trip"  <?= $old('unit_type') === 'trip'  ? 'selected' : '' ?>>رحلة</option>
            </select>
          </div>
          <div class="form-group">
            <label for="se_mb">قراءةُ العدّادِ قبل <span class="se-unit">(ساعة عدّاد)</span></label>
            <input type="number" step="0.01" min="0" name="meter_before" id="se_mb" aria-label="قراءةُ العدّادِ قبل" value="<?= $old('meter_before') ?>">
          </div>
          <div class="form-group">
            <label for="se_ma">قراءةُ العدّادِ بعد <span class="se-unit">(ساعة عدّاد)</span></label>
            <input type="number" step="0.01" min="0" name="meter_after" id="se_ma" aria-label="قراءةُ العدّادِ بعد" value="<?= $old('meter_after') ?>">
          </div>
          <div class="form-group">
            <label for="se_fr">وقودٌ مستلَم <span class="se-unit">(لتر)</span></label>
            <input type="number" step="0.01" min="0" name="fuel_received_qty" id="se_fr" aria-label="وقودٌ مستلَم باللتر" value="<?= $old('fuel_received_qty') ?>">
          </div>
          <div class="form-group">
            <label for="se_fi">وقودٌ مصروف <span class="se-unit">(لتر)</span></label>
            <input type="number" step="0.01" min="0" name="fuel_issued_qty" id="se_fi" aria-label="وقودٌ مصروف باللتر" value="<?= $old('fuel_issued_qty') ?>">
          </div>
          <div class="form-group">
            <label for="se_ck">مفتاحُ الحاوية <span class="se-unit">(اختياري)</span></label>
            <input type="text" name="container_key" id="se_ck" aria-label="مفتاحُ الحاوية" maxlength="32" value="<?= $old('container_key') ?>">
          </div>
        </div>

        <h3 class="se-h2b">توزيعُ ساعاتِ الوردية <span class="se-req">*</span></h3>
        <div class="alert alert-info">
          <i class="fa fa-circle-info"></i>
          <span>كلُّ سطرٍ حالةٌ واحدةٌ بساعاتِها وطرفِها المسؤول. يمكنك إضافةُ أسطر،
            ومجموعُ الساعاتِ لا يتجاوز <strong>٢٤ ساعة</strong>.</span>
        </div>
        <?php /* `se-hrow` و`se-hours` يبقيان: الجافاسكربت يستنسخ الصفَّ بهما.
                 و`form-grid` معهما فيأخذ الصفُّ تخطيطَ الشبكةِ الموحَّدة. */ ?>
        <div id="se-hours">
          <div class="se-hrow form-grid">
            <div class="form-group">
              <label>الحالة</label>
              <select name="h_state[]" aria-label="حالةُ سطرِ الساعات">
                <option value="">— اختر —</option>
<?php foreach ($OPS_STATES as $s): ?>
                <option value="<?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?>" <?= $s === 'actual_work' ? 'selected' : '' ?>>
                  <?= htmlspecialchars($OPS_AR[$s] ?? $s, ENT_QUOTES, 'UTF-8') ?>
                </option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>الساعات <span class="se-unit">(ساعة)</span></label>
              <input type="number" step="0.25" min="0" max="24" name="h_hours[]" aria-label="ساعاتُ السطر">
            </div>
            <div class="form-group">
              <label>الطرفُ المسؤول</label>
              <select name="h_party[]" aria-label="الطرفُ المسؤول">
<?php foreach ($PARTY_AR as $k => $v): ?>
                <option value="<?= $k ?>" <?= $k === 'company' ? 'selected' : '' ?>><?= $v ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>سببُ التوقف <span class="se-unit">(عند التوقف)</span></label>
              <input type="text" name="h_note[]" aria-label="سببُ التوقف" maxlength="190">
            </div>
            <div class="se-hrow-act"><button type="button" class="btn-secondary se-btn2" onclick="seAddRow()">+ سطر</button></div>
          </div>
        </div>

        <div class="form-grid se-mt12">
          <div class="form-group form-grid-full">
            <label for="se_note">ملاحظاتٌ ميدانية</label>
            <input type="text" name="note" id="se_note" aria-label="ملاحظاتٌ ميدانية" maxlength="500" value="<?= $old('note') ?>">
          </div>
        </div>

        <div class="pu-form-actions">
          <button type="submit" class="btn-primary" <?= $CAN_RECORD ? '' : 'disabled' ?>><i class="fas fa-save"></i> احفظ القيدَ وأرسِلْه</button>
        </div>
      </div>
    </div>
  </form>

  <div class="card">
    <div class="card-header"><h5><i class="fa fa-list"></i> ما سُجِّل اليوم — <?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?></h5></div>
    <div class="card-body">
      <div class="alert alert-info">
        <i class="fa fa-circle-info"></i>
        <span>قيودُ هذا اليومِ في كيانِك. الحالةُ بلونٍ ونصٍّ معًا.</span>
      </div>
      <div class="table-container">
        <?php /* الجدولُ يُصيَّر دائمًا — والفراغُ تحمله رسالةُ العُدَّةِ المعرَّبة،
                 فلا فقرةُ فراغٍ محليّةٌ تخصُّ شاشةً واحدة. */ ?>
        <table class="alltables display nowrap">
          <thead><tr>
            <th>رقمُ القيد</th><th>الآلية</th><th>الوردية</th><th>الكمية</th>
            <th>ساعاتُ التشغيل</th><th>مجموعُ الساعات</th><th>العدّاد</th><th>وقودٌ مصروف</th><th>الحالة</th>
            <?= $CAN_VOID ? '<th>إلغاء</th>' : '' ?>
          </tr></thead>
          <tbody>
<?php foreach ($todayRows as $r):
        $sk = (string) $r['state'];
        $sv = $STATE_AR[$sk] ?? [$sk, 'se-b-muted']; ?>
            <tr>
              <td><?= htmlspecialchars((string) $r['entry_no'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string) $r['machine'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= $r['shift'] === 'night' ? 'ليل' : 'نهار' ?></td>
              <td><?= number_format((float) $r['qty'], 2) ?> <span class="se-unit"><?= htmlspecialchars((string) $r['unit_type'], ENT_QUOTES, 'UTF-8') ?></span></td>
              <td><?= $r['run_h'] !== null ? number_format((float) $r['run_h'], 2) . ' <span class="se-unit">ساعة</span>' : '—' ?></td>
              <td><?= $r['total_hours'] !== null ? number_format((float) $r['total_hours'], 2) . ' <span class="se-unit">ساعة</span>' : '—' ?></td>
              <td><?= ($r['meter_before'] !== null && $r['meter_after'] !== null)
                        ? number_format((float) $r['meter_after'] - (float) $r['meter_before'], 2) . ' <span class="se-unit">ساعة</span>'
                        : '—' ?></td>
              <td><?= number_format((float) $r['fuel_issued_qty'], 2) ?> <span class="se-unit">لتر</span></td>
              <td><span class="se-badge <?= htmlspecialchars($sv[1], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($sv[0], ENT_QUOTES, 'UTF-8') ?></span></td>
<?php if ($CAN_VOID): ?>
              <td>
<?php if (in_array($sk, ['reversed', 'cancelled', 'superseded'], true)): ?>
                <span class="se-unit">—</span>
<?php else: ?>
                <form method="post" onsubmit="return seConfirmVoid(this)" class="se-void-form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="void">
                  <input type="hidden" name="entry_id" value="<?= (int) $r['id'] ?>">
                  <input type="text" name="void_reason" placeholder="سببُ الإلغاء" required maxlength="190"
                         class="se-void-input">
                  <button type="submit" class="btn-secondary se-btn2 se-btn2-sm">ألغِ</button>
                </form>
<?php endif; ?>
              </td>
<?php endif; ?>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
function seConfirmVoid(f){
  var r = f.void_reason.value.trim();
  if (!r) { alert('اكتبْ سببَ الإلغاء أولًا.'); return false; }
  return confirm('سيُنشأ قيدٌ عاكسٌ ولن يُحذف شيء، وتتحرّر الخانةُ لقيدٍ بديل.\n\nالسبب: ' + r);
}
function seAddRow(){
  var box = document.getElementById('se-hours');
  var first = box.querySelector('.se-hrow');
  var row = first.cloneNode(true);
  row.querySelectorAll('input').forEach(function(i){ i.value = ''; });
  var btn = row.querySelector('button');
  btn.textContent = '− حذف';
  btn.setAttribute('onclick', 'this.closest(".se-hrow").remove()');
  box.appendChild(row);
}
</script>

