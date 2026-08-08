<?php
/**
 * Portal/ceo_contracts.php — توقيع العقود والالتزامات (M-00 §8-2 — على جدولها الأصلي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 00 · الإدارة التنفيذية · الأعمدة 24 بترتيب المستند وطبقة
 * الحوكمة بشرائحها. سجل التوقيع في الجدول الأصلي `exec_contract_signings`
 * (هجرة 2026_11_14 — أُنجز لحاق CMP03_FOLLOWUP) معزولًا بالكيان، والعقدُ
 * المرتبطُ الحقيقي يوقَّع عبر آلة الحالة (نقطة الخنق) فيقع أثرُه الرباعي
 * ④-٣، والموقَّعُ محصَّنٌ بقادح BR-CEO-08 في القاعدة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
require_once '../includes/permissions_helper.php';
require_once '../includes/gov_columns.php';
require_once '../includes/m00_exec_helpers.php';

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$uid            = intval($_SESSION['user']['id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=غير+مصرح");
    exit();
}

// حارس الشاشة (M-14 BR-GOV-01): can_view من modules — والسوبر يمر
$__pp = check_page_permissions($conn, 'Portal/ceo_contracts.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    require_once __DIR__ . '/../includes/perm_explain_live.php';
    $__why = ems_deny_message($conn, intval($_SESSION['user']['role'] ?? 0), 'Portal/ceo_contracts.php');
    header('Location: ../main/dashboard.php?msg=' . urlencode($__why));
    exit();
}
if (!$is_super_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && empty($__pp['can_add']) && empty($__pp['can_edit'])) {
    http_response_code(403);
    exit('غير مصرح بالكتابة في هذه الشاشة');
}
$COLS   = array (
  0 => 'الكيان',
  1 => 'رقم العقد',
  2 => 'نوع العقد',
  3 => 'الطرف الآخر',
  4 => 'نوع الطرف',
  5 => 'القيمة',
  6 => 'العملة',
  7 => 'المدة',
  8 => 'نموذج العمل',
  9 => 'وحدة التعاقد',
  10 => 'عدد الوحدات',
  11 => 'الكفالة المطلوبة',
  12 => 'قيمة الكفالة',
  13 => 'مراجعة قانونية',
  14 => 'مراجعة مالية',
  15 => 'الموقّع عنّا',
  16 => 'صفته',
  17 => 'مرجع سلطته',
  18 => 'تاريخ التوقيع',
  19 => 'الموقّع عن الطرف الآخر',
  20 => 'صفته',
  21 => 'مستند تخويله',
  22 => 'سُجّل في السجل الموحَّد؟',
  23 => 'الحالة',
);
/* أعمدة الجدول الأصلي بترتيب حقول الفورم f0..f22 (الأخير الحالة) —
 * «صفته» المزدوجة صارت عمودين مستقلين (signer_capacity/other_signer_capacity) */
$DB_FIELDS = array(
    'contract_no', 'contract_kind', 'other_party', 'party_type', 'amount',
    'currency', 'duration', 'work_model', 'contract_unit', 'units_count',
    'bond_required', 'bond_value', 'legal_review', 'financial_review',
    'signed_by_us', 'signer_capacity', 'authority_ref', 'signing_date',
    'other_signer', 'other_signer_capacity', 'other_authority_doc', 'registry_recorded',
);
/* خريطة عرض: فهرس عمود المستند → عمود القاعدة (null = الكيان) */
$COLDB = array_merge(array(null), $DB_FIELDS, array('__status'));

/* ── الحفظ: فورم الإضافة الموحد → الجدول الأصلي ─────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['cmp03_action'] ?? '') === 'add') {
    $in = array();
    foreach ($DB_FIELDS as $i => $col) { $in[$col] = trim((string) ($_POST['f' . $i] ?? '')); }
    $status = trim((string) ($_POST['f22'] ?? '')) ?: 'مسودة';
    $in['amount'] = str_replace(array(',', ' '), '', $in['amount']);
    if ($in['amount'] !== '' && !is_numeric($in['amount'])) { $in['amount'] = ''; }

    // ═══ BR-CEO-02: لا توقيعَ بملاحظةٍ حرجةٍ مفتوحة — الحجبُ بنيويٌّ لا سياسة ═══
    // صفٌّ يُدخل موقَّعًا (تاريخ توقيعٍ مملوء) يُفحص على ملاحظات «مراجعة
    // العقود»: الحاجبةُ المفتوحةُ تمنع ولا تُرفع بقرارٍ شفهي.
    if ($in['signing_date'] !== '' && $in['contract_no'] !== '') {
        $blockNote = m00_review_block($conn, $company_id, $in['contract_no']);
        if ($blockNote !== null) {
            header('Location: ' . basename(__FILE__) . '?msg=' . rawurlencode(
                'BR-CEO-02: التوقيع محجوبٌ — ملاحظةٌ حرجةٌ مفتوحة (' . $blockNote
                . ') على العقد ' . $in['contract_no'] . ' · تُقفل بمستند معالجةٍ أولًا ولا تُرفع شفهيًّا ❌'));
            exit();
        }
    }
    $creator = trim((string) ($_SESSION['user']['name'] ?? '')) ?: ('مستخدم #' . $uid);
    // الفارغ NULL من هنا لا NULLIF في SQL — خلطُ الترتيبات على اتصال الويب يرفضها
    if ($in['registry_recorded'] === '') { $in['registry_recorded'] = 'لا'; }
    foreach ($in as $k => $v) { if ($v === '') { $in[$k] = null; } }
    $st = $conn->prepare("INSERT INTO exec_contract_signings
        (company_id, contract_no, contract_kind, other_party, party_type, amount,
         currency, duration, work_model, contract_unit, units_count, bond_required,
         bond_value, legal_review, financial_review, signed_by_us, signer_capacity,
         authority_ref, signing_date, other_signer, other_signer_capacity,
         other_authority_doc, registry_recorded, status, is_seed, created_by, created_by_name)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)");
    $st->bind_param('isssssssssssssssssssssssis',
        $company_id, $in['contract_no'], $in['contract_kind'], $in['other_party'],
        $in['party_type'], $in['amount'], $in['currency'], $in['duration'],
        $in['work_model'], $in['contract_unit'], $in['units_count'], $in['bond_required'],
        $in['bond_value'], $in['legal_review'], $in['financial_review'], $in['signed_by_us'],
        $in['signer_capacity'], $in['authority_ref'], $in['signing_date'], $in['other_signer'],
        $in['other_signer_capacity'], $in['other_authority_doc'], $in['registry_recorded'],
        $status, $uid, $creator);
    $ok = $st->execute();
    $st->close();
    header('Location: ' . basename(__FILE__) . '?msg=' . rawurlencode($ok ? 'حُفظ الصف ✅' : 'تعذر الحفظ ❌'));
    exit();
}

/* ── التوقيع: فعل M-00 ④-٣ — للإدارة التنفيذية وحدها ─────────────────────
 * BR-CEO-01: السلطةُ الأصلية للدور 9 (أو مرجعُ تفويضٍ موثَّق) وتُسجَّل مع
 * التوقيع · BR-CEO-02: الملاحظةُ الحرجةُ المفتوحة تحجب بنيويًّا · والعقدُ
 * الحقيقيُّ المرتبط يوقَّع عبر آلة الحالة فيقع أثرُه الرباعي (نفاذ · سجل
 * موحَّد · حاوية · التزامٌ بالمروحة) ويُنشر ContractSigned من نقطة الخنق. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['cmp03_action'] ?? '') === 'sign') {
    $goBack = function ($m) { header('Location: ' . basename(__FILE__) . '?msg=' . rawurlencode($m)); exit(); };
    $actorRole = strval($_SESSION['user']['role'] ?? '');
    if (!$is_super_admin && $actorRole !== '9') {
        http_response_code(403);
        exit('التوقيعُ على العقود قرارُ الإدارة التنفيذية وحدها — BR-CEO-01');
    }
    $rowId = intval($_POST['row'] ?? 0);
    $authorityRef = trim((string) ($_POST['authority_ref'] ?? ''));
    $linkContract = intval($_POST['link_contract'] ?? 0);
    if ($rowId <= 0) { $goBack('اختر صفًّا للتوقيع ❌'); }
    if ($authorityRef === '') { $authorityRef = 'سلطة أصلية'; }

    $st = $conn->prepare("SELECT * FROM exec_contract_signings WHERE id = ?"
        . ($is_super_admin && $company_id <= 0 ? '' : ' AND company_id = ?'));
    if ($is_super_admin && $company_id <= 0) { $st->bind_param('i', $rowId); }
    else { $st->bind_param('ii', $rowId, $company_id); }
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$row) { $goBack('الصفُّ غير موجودٍ في نطاقك ❌'); }
    if ($row['signing_date'] !== null) {
        $goBack('العقدُ موقَّعٌ سلفًا (' . $row['signing_date'] . ') — BR-CEO-08: لا توقيعَ على توقيع ❌');
    }

    // BR-CEO-02: الملاحظة الحاجبة المفتوحة — من سجل المراجعة ومن حقل المراجعة نفسه
    $blockNote = m00_review_block($conn, $company_id, (string) $row['contract_no']);
    $selfOpen = mb_strpos((string) $row['legal_review'], 'حرجة') !== false
             && mb_strpos((string) $row['legal_review'], 'مفتوحة') !== false;
    if ($blockNote !== null || $selfOpen) {
        $goBack('BR-CEO-02: التوقيع محجوبٌ — ملاحظةٌ حرجةٌ مفتوحة'
            . ($blockNote !== null ? ' (' . $blockNote . ')' : ' (المراجعة القانونية)')
            . ' على العقد ' . $row['contract_no'] . ' · تُقفل بمستند معالجةٍ أولًا ❌');
    }

    // العقد الحقيقي المرتبط: التوقيع عبر آلة الحالة — نقطة الخنق وأثرها الرباعي
    if ($linkContract > 0) {
        require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
        require_once dirname(__DIR__) . '/app/Services/Contract/ContractStateMachine.php';
        $gate = new \App\Core\TenantDb($conn, $company_id, $uid);
        $r = \App\Services\Contract\ContractStateMachine::transition(
            $conn, $gate, $company_id, $linkContract,
            \App\Services\Contract\ContractStateMachine::SIGNED,
            'توقيعٌ من شاشة توقيع العقود والالتزامات (EXCS-' . $rowId . ')', $uid,
            array('authority_ref' => $authorityRef,
                  'amount' => (string) ($row['amount'] ?? ''), 'currency' => (string) ($row['currency'] ?? '')));
        if (!$r['ok']) {
            $goBack('آلة الحالة رفضت توقيع العقد #' . $linkContract . ': ' . $r['reason'] . ' ❌');
        }
    }

    $actorName = trim((string) ($_SESSION['user']['name'] ?? '')) ?: ('مستخدم #' . $uid);
    $signDate = date('Y-m-d');
    $st = $conn->prepare("UPDATE exec_contract_signings
        SET signed_by_us = ?, signer_capacity = 'المدير التنفيذي', authority_ref = ?,
            signing_date = ?, registry_recorded = 'نعم', status = 'نافذ',
            contract_id = NULLIF(?, 0)
        WHERE id = ?");
    $st->bind_param('sssii', $actorName, $authorityRef, $signDate, $linkContract, $rowId);
    $ok = $st->execute();
    $err = $st->error;
    $st->close();
    if (!$ok) { $goBack('تعذر قيد التوقيع: ' . ($err ?: '؟') . ' ❌'); }
    if (function_exists('log_security_event')) {
        log_security_event('CEO_CONTRACT_SIGNED', 'EXCS-' . $rowId . ($linkContract > 0 ? ' ↔ contract#' . $linkContract : ''));
    }

    // §11 ContractSigned لسجلٍ بلا عقدٍ مرتبط: التوقيعُ الواقع حقيقةٌ تُنشر —
    // والمرتبطُ نشرته آلةُ الحالة من نقطة الخنق فلا يُكرَّر
    if ($linkContract <= 0 && (int) $row['is_seed'] === 0) {
        try {
            require_once dirname(__DIR__) . '/app/Core/EventPublisher.php';
            \App\Core\EventPublisher::publishFact($conn, array(
                'event_key'       => 'contract.signed',
                'category'        => 'commercial',
                'source_module'   => 'system',
                'company_id'      => $company_id,
                'entity_type'     => 'exec_contract_signing',
                'entity_id'       => $rowId,
                'occurred_at'     => gmdate('Y-m-d H:i:s'),
                'created_by'      => $uid ?: 1,
                'idempotency_key' => 'exec_signing:EXCS-' . $rowId,
                'notes'           => 'توقيعُ ' . ((string) $row['contract_kind'] ?: 'عقد') . ' ' . (string) $row['contract_no']
                                     . ' — ' . (string) ($row['other_party'] ?? ''),
                'payload'         => array(
                    'signing_ref'   => 'EXCS-' . $rowId,
                    'contract_no'   => (string) $row['contract_no'],
                    'party'         => (string) ($row['other_party'] ?? ''),
                    'party_type'    => (string) ($row['party_type'] ?? ''),
                    'amount'        => (string) ($row['amount'] ?? ''),
                    'currency'      => (string) ($row['currency'] ?? ''),
                    'authority_ref' => $authorityRef,
                    'signed_by'     => $uid,
                ),
            ));
        } catch (\Throwable $t) { error_log('ceo_contracts sign fact #' . $rowId . ': ' . $t->getMessage()); }
    }
    $goBack('وُقّع العقد ' . $row['contract_no'] . ' بمرجع «' . $authorityRef . '» ✅'
        . ($linkContract > 0 ? ' — والأثرُ الرباعي وقع على العقد #' . $linkContract : ''));
}

/* ── القراءة: صفوف الكيان من الجدول الأصلي ──────────────────────────────── */
$rows = array();
$sql = "SELECT * FROM exec_contract_signings"
     . ($is_super_admin && $company_id <= 0 ? '' : ' WHERE company_id = ?')
     . ' ORDER BY id DESC LIMIT 500';
$st = $conn->prepare($sql);
if (!($is_super_admin && $company_id <= 0)) { $st->bind_param('i', $company_id); }
$st->execute();
$rs = $st->get_result();
while ($x = $rs->fetch_assoc()) { $rows[] = $x; }
$st->close();

/* العقود الحقيقية القابلة للتوقيع (حالة «معتمد» — ما قبل التوقيع في الآلة) */
$signableContracts = array();
if ($company_id > 0) {
    $rs = $conn->query("SELECT id, first_party, second_party, contract_status FROM contracts
        WHERE company_id = {$company_id} AND COALESCE(is_deleted,0)=0
          AND contract_status = 'معتمد' ORDER BY id DESC LIMIT 100");
    if ($rs) { while ($x = $rs->fetch_assoc()) { $signableContracts[] = $x; } }
}

$govCtx = ems_gov_ctx();
$entityName = $govCtx['values']['entity'] ?? '—';

/** قيمة خلية بفهرس عمود المستند — الحوكمة الآلية حية وسائرها من الجدول أو «—» */
function m00_cell_at($idx, $row, $entityName, $COLDB) {
    $col = $COLDB[$idx] ?? null;
    if ($col === null) { return $entityName; }
    if ($col === '__status') { return (string) $row['status']; }
    $v = isset($row[$col]) ? trim((string) $row[$col]) : '';
    if ($v !== '' && $col === 'amount' && is_numeric($v)) {
        $v = rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    }
    return $v !== '' ? $v : '—';
}

$page_title = 'إيكوبيشن | توقيع العقود والالتزامات';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($__pp) ? $__pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'توقيع العقود والالتزامات';
    $header_icon = 'fa fa-pen-fancy';
    $header_actions = array(
        array('tag' => 'button', 'id' => 'cmp03AddBtn', 'class' => '', 'icon' => 'fa fa-plus',
              'label' => 'إضافة', 'title' => 'إضافة صف جديد', 'attrs' => 'type="button"'),
    );
    $header_back = false;
    include '../includes/page_header.php';
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars((string) $_GET['msg'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    ?>

    <!-- فورم الإضافة الموحد (ems-forms) — مطويٌّ حتى زرِّ الرأس -->
    <form method="post" action="" class="allforms" id="cmp03AddForm">
        <input type="hidden" name="cmp03_action" value="add">
        <div class="card"><div class="card-header">
            <h5><i class="fa fa-plus"></i> إضافة — توقيع العقود والالتزامات</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label>رقم العقد</label>
                    <input type="text" name="f0" required maxlength="190"></div>
                <div class="form-group"><label>نوع العقد</label>
                    <input type="text" name="f1" maxlength="190"></div>
                <div class="form-group"><label>الطرف الآخر</label>
                    <input type="text" name="f2" maxlength="190"></div>
                <div class="form-group"><label>نوع الطرف</label>
                    <input type="text" name="f3" maxlength="190"></div>
                <div class="form-group"><label>القيمة</label>
                    <input type="text" inputmode="decimal" name="f4" placeholder="0"></div>
                <div class="form-group"><label>العملة</label>
                    <input type="text" name="f5" maxlength="190"></div>
                <div class="form-group"><label>المدة</label>
                    <input type="text" name="f6" maxlength="190"></div>
                <div class="form-group"><label>نموذج العمل</label>
                    <input type="text" name="f7" maxlength="190"></div>
                <div class="form-group"><label>وحدة التعاقد</label>
                    <input type="text" name="f8" maxlength="190"></div>
                <div class="form-group"><label>عدد الوحدات</label>
                    <input type="text" name="f9" maxlength="190"></div>
                <div class="form-group"><label>الكفالة المطلوبة</label>
                    <input type="text" name="f10" maxlength="190"></div>
                <div class="form-group"><label>قيمة الكفالة</label>
                    <input type="text" name="f11" maxlength="190"></div>
                <div class="form-group"><label>مراجعة قانونية</label>
                    <input type="text" name="f12" maxlength="190"></div>
                <div class="form-group"><label>مراجعة مالية</label>
                    <input type="text" name="f13" maxlength="190"></div>
                <div class="form-group"><label>الموقّع عنّا</label>
                    <input type="text" name="f14" maxlength="190"></div>
                <div class="form-group"><label>صفته (الموقّع عنّا)</label>
                    <input type="text" name="f15" maxlength="190"></div>
                <div class="form-group"><label>مرجع سلطته</label>
                    <input type="text" name="f16" maxlength="190"></div>
                <div class="form-group"><label>تاريخ التوقيع</label>
                    <input type="date" name="f17"></div>
                <div class="form-group"><label>الموقّع عن الطرف الآخر</label>
                    <input type="text" name="f18" maxlength="190"></div>
                <div class="form-group"><label>صفته (الطرف الآخر)</label>
                    <input type="text" name="f19" maxlength="190"></div>
                <div class="form-group"><label>مستند تخويله</label>
                    <input type="text" name="f20" maxlength="190"></div>
                <div class="form-group"><label>سُجّل في السجل الموحَّد؟</label>
                    <input type="text" name="f21" maxlength="190" placeholder="لا"></div>
                <div class="form-group"><label>الحالة</label>
                    <select name="f22"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="جاهز للتوقيع">جاهز للتوقيع</option><option value="محجوب بملاحظة حرجة">محجوب بملاحظة حرجة</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
            </div></div>
            <div style="margin-top:12px;display:flex;gap:10px">
                <button type="submit" class="btn-save"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-cancel" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <?php
    // لوحة التوقيع (M-00 ④-٣) — للإدارة التنفيذية وحدها وعلى غير الموقَّع
    $signable = array();
    foreach ($rows as $r) {
        if ($r['signing_date'] === null && !in_array((string) $r['status'], array('ملغي', 'موقوف'), true)) { $signable[] = $r; }
    }
    $canSign = $is_super_admin || strval($_SESSION['user']['role'] ?? '') === '9';
    if ($canSign && $signable): ?>
    <form method="post" action="" class="allforms allforms-visible" id="cmp03SignForm">
        <input type="hidden" name="cmp03_action" value="sign">
        <div class="card"><div class="card-header">
            <h5><i class="fa fa-pen-fancy"></i> التوقيع — بالسلطة الأصلية أو تفويض موثَّق (BR-CEO-01)</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label>العقد المعروض للتوقيع</label>
                    <select name="row" required>
                        <?php foreach ($signable as $d):
                            $lbl = 'EXCS-' . intval($d['id'])
                                 . ' — ' . (string) $d['contract_no']
                                 . ' · ' . (string) ($d['other_party'] ?? '—')
                                 . ' (' . (string) $d['status'] . ')'; ?>
                        <option value="<?php echo intval($d['id']); ?>"><?php echo htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label>مرجع السلطة (فارغ = سلطة أصلية)</label>
                    <input type="text" name="authority_ref" maxlength="120" placeholder="سلطة أصلية"></div>
                <div class="form-group"><label>ربط عقد حقيقي (اختياري — يوقَّع عبر آلة الحالة بأثره الرباعي)</label>
                    <select name="link_contract">
                        <option value="0">— بلا ربط</option>
                        <?php foreach ($signableContracts as $rc):
                            $lbl = '#' . intval($rc['id']) . ' — ' . (string) ($rc['second_party'] ?: $rc['first_party'])
                                 . ' (' . (string) $rc['contract_status'] . ')'; ?>
                        <option value="<?php echo intval($rc['id']); ?>"><?php echo htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select></div>
            </div></div>
            <div style="margin-top:12px;display:flex;gap:10px">
                <button type="submit" class="btn-save"><i class="fa fa-stamp"></i> توقيع</button>
            </div>
        </div></div>
    </form>
    <?php endif; ?>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="ceo_contractsTable">
            <thead><tr>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th>رقم العقد</th>
            <th>نوع العقد</th>
            <th>الطرف الآخر</th>
            <th>نوع الطرف</th>
            <th>القيمة</th>
            <th class="ems-gov-th" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
            <th>المدة</th>
            <th>نموذج العمل</th>
            <th>وحدة التعاقد</th>
            <th>عدد الوحدات</th>
            <th>الكفالة المطلوبة</th>
            <th>قيمة الكفالة</th>
            <th>مراجعة قانونية</th>
            <th>مراجعة مالية</th>
            <th>الموقّع عنّا</th>
            <th>صفته</th>
            <th>مرجع سلطته</th>
            <th>تاريخ التوقيع</th>
            <th>الموقّع عن الطرف الآخر</th>
            <th>صفته</th>
            <th>مستند تخويله</th>
            <th class="ems-fn-th none" data-fn="1">سُجّل في السجل الموحَّد؟</th>
            <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="24" class="text-center text-muted">لا بياناتَ بعدُ — أضف أول صفٍّ بزر «إضافة»</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr<?php echo $r['is_seed'] ? ' data-seed="1"' : ''; ?>>
                    <?php foreach (array_keys($COLS) as $i): $v = m00_cell_at($i, $r, $entityName, $COLDB); ?>
                    <td<?php echo $v === '—' ? ' class="ems-gov-empty"' : ''; ?>><?php echo htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div></div>
</div>

<script>
(function () {
    var btn = document.getElementById('cmp03AddBtn');
    var form = document.getElementById('cmp03AddForm');
    var cancel = document.getElementById('cmp03CancelBtn');
    if (btn && form) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            form.classList.toggle('allforms-visible');
            if (form.classList.contains('allforms-visible')) {
                form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
    }
    if (cancel && form) {
        cancel.addEventListener('click', function () { form.classList.remove('allforms-visible'); });
    }
})();
</script>
