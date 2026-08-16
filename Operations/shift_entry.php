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
    echo '<div dir="rtl" style="font-family:Tahoma;padding:24px">'
       . 'لا تملك صلاحيةَ فتحِ شاشةِ قيدِ الوردية — راجِعْ مديرَك.</div>';
    exit();
}
if (!$is_super_admin && $company_id <= 0) {
    http_response_code(403);
    echo '<div dir="rtl" style="font-family:Tahoma;padding:24px">'
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
$STATE_AR = ['draft' => ['مسوَّدة', '#6c757d'], 'submitted' => ['بانتظار اعتماد الموقع', '#b8860b'],
             'site_approved' => ['اعتمدها الموقع', '#0b6b3a'], 'parties_review' => ['مراجعةُ الأطراف', '#b8860b'],
             'parties_approved' => ['اعتمدها الأطراف', '#0b6b3a'], 'sales_approved' => ['اعتمدتها المبيعات', '#0b6b3a'],
             'returned' => ['أُعيد للموقع', '#a12622'], 'converted' => ['حُوِّل', '#0b6b3a']];

$old = function (string $k, $d = '') use ($formOld) {
    return isset($formOld[$k]) ? htmlspecialchars((string) $formOld[$k], ENT_QUOTES, 'UTF-8') : $d;
};

/* ═══ ⑧ العرض ════════════════════════════════════════════════════════ */
require_once __DIR__ . '/../includes/screen_contract.php';
include '../inheader.php';
include '../insidebar.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<style>
.se-wrap{max-width:1180px;margin:0 auto;padding:14px}
.se-card{background:#fff;border:1px solid #e3e6ea;border-radius:10px;padding:16px;margin-bottom:14px}
.se-card h2{font-size:17px;margin:0 0 4px}
.se-hint{color:#5b6572;font-size:13px;margin:0 0 14px}
.se-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px}
.se-f label{display:block;font-size:13px;font-weight:600;margin-bottom:4px}
.se-f input,.se-f select{width:100%;padding:8px 10px;border:1px solid #cfd6dd;border-radius:7px;font-size:14px;font-family:inherit}
.se-req{color:#a12622;font-weight:700}
.se-unit{color:#5b6572;font-weight:400;font-size:12px}
.se-flash{padding:11px 14px;border-radius:8px;margin-bottom:14px;font-size:14px;border:1px solid}
.se-ok{background:#e8f5ee;border-color:#0b6b3a;color:#0b4a28}
.se-warn{background:#fdf6e3;border-color:#b8860b;color:#7a5a06}
.se-err{background:#fdeaea;border-color:#a12622;color:#7a1a17}
.se-btn{background:#0b6b3a;color:#fff;border:0;border-radius:8px;padding:10px 20px;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit}
.se-btn[disabled]{background:#adb5bd;cursor:not-allowed}
.se-btn2{background:#fff;color:#33405a;border:1px solid #cfd6dd;border-radius:8px;padding:10px 18px;font-size:14px;cursor:pointer;font-family:inherit}
.se-tbl{width:100%;border-collapse:collapse;font-size:13px}
.se-tbl th,.se-tbl td{border:1px solid #e3e6ea;padding:7px 9px;text-align:right}
.se-tbl th{background:#f5f7f9;font-weight:700}
.se-badge{display:inline-block;padding:2px 9px;border-radius:11px;font-size:12px;font-weight:700;color:#fff}
.se-hrow{display:grid;grid-template-columns:1.5fr .7fr 1fr 1.6fr auto;gap:8px;margin-bottom:8px;align-items:end}
.se-empty{text-align:center;color:#5b6572;padding:26px}
@media(max-width:760px){.se-hrow{grid-template-columns:1fr}}
</style>

<div class="se-wrap" dir="rtl">

  <div class="se-card">
    <h2>قيدُ الوردية اليومي</h2>
    <p class="se-hint">
      سجّلْ سطرًا واحدًا لكلِّ آليةٍ في كلِّ ورديةٍ في كلِّ يوم: ساعاتُها ووقودُها وعدّادُها ومشغّلُها.
      الحقولُ الموسومةُ <span class="se-req">*</span> إلزاميةٌ قبلَ الحفظ.
    </p>

<?php if ($flash): ?>
    <div class="se-flash se-<?= $flash['kind'] === 'ok' ? 'ok' : ($flash['kind'] === 'warn' ? 'warn' : 'err') ?>">
      <?= htmlspecialchars($flash['text'], ENT_QUOTES, 'UTF-8') ?>
      <?php if (!empty($flash['ref'])): ?>
        — رقمُ القيد: <strong><?= htmlspecialchars((string) $flash['ref'], ENT_QUOTES, 'UTF-8') ?></strong>
      <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (!$CAN_RECORD): ?>
    <div class="se-flash se-warn">
      تستطيع الاطّلاعَ ولا تستطيع التسجيل. لا تملك صلاحيةَ تسجيلِ قيدِ الوردية — راجِعْ مديرَك.
    </div>
<?php endif; ?>

    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="record">

      <div class="se-grid">
        <div class="se-f">
          <label>التاريخ <span class="se-req">*</span></label>
          <input type="date" name="entry_date" value="<?= $old('entry_date', $today) ?>" required>
        </div>
        <div class="se-f">
          <label>الآلية <span class="se-req">*</span></label>
          <select name="equipment_id" required>
            <option value="">— اختر الآلية —</option>
<?php foreach ($equipments as $e): ?>
            <option value="<?= (int) $e['id'] ?>" <?= $old('equipment_id') == $e['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($e['code'], ENT_QUOTES, 'UTF-8') ?>
            </option>
<?php endforeach; ?>
          </select>
        </div>
        <div class="se-f">
          <label>الوردية <span class="se-req">*</span></label>
          <select name="shift" required>
            <option value="day"   <?= $old('shift') === 'day' ? 'selected' : '' ?>>نهار</option>
            <option value="night" <?= $old('shift') === 'night' ? 'selected' : '' ?>>ليل</option>
          </select>
        </div>
        <div class="se-f">
          <label>الكمية المنفَّذة <span class="se-req">*</span></label>
          <input type="number" step="0.01" min="0" name="qty" value="<?= $old('qty') ?>" required>
        </div>
        <div class="se-f">
          <label>وحدة القياس <span class="se-req">*</span></label>
          <select name="unit_type" required>
            <option value="hour"  <?= $old('unit_type', 'hour') === 'hour'  ? 'selected' : '' ?>>ساعة</option>
            <option value="ton"   <?= $old('unit_type') === 'ton'   ? 'selected' : '' ?>>طن</option>
            <option value="meter" <?= $old('unit_type') === 'meter' ? 'selected' : '' ?>>متر</option>
            <option value="trip"  <?= $old('unit_type') === 'trip'  ? 'selected' : '' ?>>رحلة</option>
          </select>
        </div>
        <div class="se-f">
          <label>قراءةُ العدّادِ قبل <span class="se-unit">(ساعة عدّاد)</span></label>
          <input type="number" step="0.01" min="0" name="meter_before" value="<?= $old('meter_before') ?>">
        </div>
        <div class="se-f">
          <label>قراءةُ العدّادِ بعد <span class="se-unit">(ساعة عدّاد)</span></label>
          <input type="number" step="0.01" min="0" name="meter_after" value="<?= $old('meter_after') ?>">
        </div>
        <div class="se-f">
          <label>وقودٌ مستلَم <span class="se-unit">(لتر)</span></label>
          <input type="number" step="0.01" min="0" name="fuel_received_qty" value="<?= $old('fuel_received_qty', '0') ?>">
        </div>
        <div class="se-f">
          <label>وقودٌ مصروف <span class="se-unit">(لتر)</span></label>
          <input type="number" step="0.01" min="0" name="fuel_issued_qty" value="<?= $old('fuel_issued_qty', '0') ?>">
        </div>
        <div class="se-f">
          <label>مفتاحُ الحاوية <span class="se-unit">(اختياري)</span></label>
          <input type="text" name="container_key" maxlength="32" value="<?= $old('container_key') ?>">
        </div>
      </div>

      <h2 style="margin-top:20px;font-size:15px">توزيعُ ساعاتِ الوردية <span class="se-req">*</span></h2>
      <p class="se-hint">
        كلُّ سطرٍ حالةٌ واحدةٌ بساعاتِها وطرفِها المسؤول. يمكنك إضافةُ أكثرَ من سببِ توقفٍ في الورديةِ نفسِها.
        ومجموعُ الساعاتِ لا يتجاوز <strong>٢٤ ساعة</strong>.
      </p>
      <div id="se-hours">
        <div class="se-hrow">
          <div class="se-f">
            <label>الحالة</label>
            <select name="h_state[]">
              <option value="">— اختر —</option>
<?php foreach ($OPS_STATES as $s): ?>
              <option value="<?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?>" <?= $s === 'actual_work' ? 'selected' : '' ?>>
                <?= htmlspecialchars($OPS_AR[$s] ?? $s, ENT_QUOTES, 'UTF-8') ?>
              </option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="se-f">
            <label>الساعات <span class="se-unit">(ساعة)</span></label>
            <input type="number" step="0.25" min="0" max="24" name="h_hours[]">
          </div>
          <div class="se-f">
            <label>الطرفُ المسؤول</label>
            <select name="h_party[]">
<?php foreach ($PARTY_AR as $k => $v): ?>
              <option value="<?= $k ?>" <?= $k === 'company' ? 'selected' : '' ?>><?= $v ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="se-f">
            <label>سببُ التوقف <span class="se-unit">(عند التوقف)</span></label>
            <input type="text" name="h_note[]" maxlength="190">
          </div>
          <div><button type="button" class="se-btn2" onclick="seAddRow()">+ سطر</button></div>
        </div>
      </div>

      <div class="se-f" style="margin-top:12px">
        <label>ملاحظاتٌ ميدانية</label>
        <input type="text" name="note" maxlength="500" value="<?= $old('note') ?>">
      </div>

      <div style="margin-top:18px">
        <button type="submit" class="se-btn" <?= $CAN_RECORD ? '' : 'disabled' ?>>احفظ القيدَ وأرسِلْه للمراجعة</button>
      </div>
    </form>
  </div>

  <div class="se-card">
    <h2>ما سُجِّل اليوم — <?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?></h2>
    <p class="se-hint">قيودُ هذا اليومِ في كيانِك. الحالةُ بلونٍ ونصٍّ معًا.</p>
<?php if (!$todayRows): ?>
    <div class="se-empty">لم يُسجَّل قيدٌ اليومَ بعد. ابدأْ بالنموذجِ أعلاه.</div>
<?php else: ?>
    <table class="se-tbl">
      <thead><tr>
        <th>رقمُ القيد</th><th>الآلية</th><th>الوردية</th><th>الكمية</th>
        <th>ساعاتُ التشغيل</th><th>مجموعُ الساعات</th><th>العدّاد</th><th>وقودٌ مصروف</th><th>الحالة</th>
      </tr></thead>
      <tbody>
<?php foreach ($todayRows as $r):
        $sk = (string) $r['state'];
        $sv = $STATE_AR[$sk] ?? [$sk, '#6c757d']; ?>
        <tr>
          <td><?= htmlspecialchars((string) $r['entry_no'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) $r['machine'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= $r['shift'] === 'night' ? 'ليل' : 'نهار' ?></td>
          <td><?= number_format((float) $r['qty'], 2) ?> <span class="se-unit"><?= htmlspecialchars((string) $r['unit_type'], ENT_QUOTES, 'UTF-8') ?></span></td>
          <td><?= $r['run_h'] !== null ? number_format((float) $r['run_h'], 2) . ' <span class="se-unit">ساعة</span>' : '—' ?></td>
          <td><?= $r['total_hours'] !== null ? number_format((float) $r['total_hours'], 2) . ' <span class="se-unit">ساعة</span>' : '—' ?></td>
          <td><?= ($r['meter_before'] !== null && $r['meter_after'] !== null)
                    ? number_format((float) $r['meter_after'] - (float) $r['meter_before'], 2) . ' <span class="se-unit">ساعة عدّاد</span>'
                    : '—' ?></td>
          <td><?= number_format((float) $r['fuel_issued_qty'], 2) ?> <span class="se-unit">لتر</span></td>
          <td><span class="se-badge" style="background:<?= $sv[1] ?>"><?= htmlspecialchars($sv[0], ENT_QUOTES, 'UTF-8') ?></span></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
<?php endif; ?>
  </div>
</div>

<script>
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

