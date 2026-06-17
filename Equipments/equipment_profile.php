<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';
require_once '../includes/driver_contract_dates.php';

$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    header('Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌');
    exit();
}

$equipment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($equipment_id <= 0) {
    header('Location: equipments.php?msg=معرف+المعدة+غير+صحيح+❌');
    exit();
}

$equipments_has_company = db_table_has_column($conn, 'equipments', 'company_id');
$operations_has_company = db_table_has_column($conn, 'operations', 'company_id');
$equipment_drivers_has_company = db_table_has_column($conn, 'equipment_drivers', 'company_id');

// صلاحية اعتماد الكرت = صلاحية تعديل المعدات (دور الأسطول)
$__pp = function_exists('check_page_permissions') ? check_page_permissions($conn, 'equipments_fleet') : ['can_edit' => true];
$can_edit = !empty($__pp['can_edit']);

$scope = "e.id = $equipment_id";
if (!$is_super_admin && $equipments_has_company) {
    $scope .= " AND e.company_id = $company_id";
}

$equipment_query = "SELECT e.*, s.name AS supplier_name, et.type AS equipment_type_name
                    FROM equipments e
                    LEFT JOIN suppliers s ON s.id = e.suppliers
                    LEFT JOIN equipments_types et ON et.id = e.type
                    WHERE $scope
                    LIMIT 1";
$equipment_result = mysqli_query($conn, $equipment_query);
$equipment = ($equipment_result && mysqli_num_rows($equipment_result) > 0) ? mysqli_fetch_assoc($equipment_result) : null;

if (!$equipment) {
    header('Location: equipments.php?msg=المعدة+غير+موجودة+او+خارج+نطاق+الشركة+❌');
    exit();
}

$ops_scope = "o.equipment = $equipment_id";
if (!$is_super_admin && $operations_has_company) {
    $ops_scope .= " AND o.company_id = $company_id";
}
$ed_scope = "ed.equipment_id = $equipment_id";
if (!$is_super_admin && $equipment_drivers_has_company) {
    $ed_scope .= " AND ed.company_id = $company_id";
}

$operations_count = 0;
$active_operations = 0;
$projects_count = 0;
$drivers_count = 0;
$hours_sum = 0;
$standby_sum = 0;

$r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM operations o WHERE $ops_scope");
if ($r) {
    $operations_count = intval(mysqli_fetch_assoc($r)['c']);
}
$r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM operations o WHERE $ops_scope AND o.status = 1");
if ($r) {
    $active_operations = intval(mysqli_fetch_assoc($r)['c']);
}
$r = mysqli_query($conn, "SELECT COUNT(DISTINCT o.project_id) AS c FROM operations o WHERE $ops_scope");
if ($r) {
    $projects_count = intval(mysqli_fetch_assoc($r)['c']);
}
$r = mysqli_query($conn, "SELECT COUNT(DISTINCT ed.driver_id) AS c FROM equipment_drivers ed WHERE $ed_scope AND ed.status = 1");
if ($r) {
    $drivers_count = intval(mysqli_fetch_assoc($r)['c']);
}
$r = mysqli_query($conn, "SELECT IFNULL(SUM(t.operator_hours),0) AS op_hours,
                                IFNULL(SUM(t.operator_standby_hours),0) AS standby_hours
                         FROM timesheet t
                         INNER JOIN operations o ON o.id = t.operator
                         WHERE $ops_scope AND t.status = 1");
if ($r) {
    $hours_row = mysqli_fetch_assoc($r);
    $hours_sum = floatval($hours_row['op_hours']);
    $standby_sum = floatval($hours_row['standby_hours']);
}

$projects_list = mysqli_query($conn, "SELECT
                            p.id,
                            p.name,
                            p.project_code,
                            IFNULL(SUM(t.operator_hours + t.operator_standby_hours),0) AS total_hours,
                            COUNT(t.id) AS shifts_count
                        FROM operations o
                        LEFT JOIN project p ON p.id = o.project_id
                        LEFT JOIN timesheet t ON t.operator = o.id AND t.status = 1
                        WHERE $ops_scope
                        GROUP BY p.id, p.name, p.project_code
                        ORDER BY total_hours DESC
                        LIMIT 10");

$drivers_list = mysqli_query($conn, "SELECT
                           d.id,
                           d.name,
                           ed.start_date,
                           ed.end_date,
                           ed.status
                        FROM equipment_drivers ed
                        INNER JOIN drivers d ON d.id = ed.driver_id
                        WHERE $ed_scope
                        ORDER BY ed.id DESC
                        LIMIT 10");

// ═══════════════════════════════════════════════════════════════════
//  كرت المعدة — جداول الأبناء (وثائق · حماية · مكوّنات · تاريخ)
// ═══════════════════════════════════════════════════════════════════
$can_edit_card = !empty($can_edit);
$child_company_scope = (!$is_super_admin && $company_id > 0) ? " AND company_id = $company_id" : "";

// قوائم ثابتة
$DOC_TYPES        = ['تأمين', 'رخصة', 'شهادة فحص', 'شهادة سلامة', 'شهادة رفع', 'شهادة معايرة', 'أخرى'];
$PROTECTION_TYPES = ['تنجيد مقاعد', 'شبك حماية زجاج', 'حمايات معدنية', 'نظام إطفاء', 'نظام تتبّع', 'تجهيزات سلامة', 'حماية تشغيل', 'تأمين شامل', 'تأمين هندسي', 'أخرى'];
$PROTECTION_STATES = ['فعّال', 'يحتاج تجديداً', 'منتهٍ/مفكوك'];
$COMPONENT_TYPES  = ['محرك', 'هيدروليك', 'جيربوكس', 'دفرنس', 'مولّد', 'أخرى'];
$EVENT_TYPES      = ['دخول', 'تشغيل بمشروع', 'خروج', 'ترحيل', 'صيانة', 'عطل', 'حادث/ضرر', 'تفتيش', 'إيقاف', 'إعادة تشغيل', 'تغيير مصدر', 'خروج/بيع'];

// جلب السطور (مع تحقّق وجود الجداول للتوافق الرجعي)
$compliance_rows = $protection_rows = $component_rows = $history_rows = [];
$fc_exists = db_table_has_column($conn, 'fleet_equipment_compliance', 'id');
$fp_exists = db_table_has_column($conn, 'fleet_equipment_protection', 'id');
$fcmp_exists = db_table_has_column($conn, 'fleet_equipment_component', 'id');
$fh_exists = db_table_has_column($conn, 'fleet_equipment_history', 'id');

if ($fc_exists) {
    $q = mysqli_query($conn, "SELECT * FROM fleet_equipment_compliance WHERE equipment_id = $equipment_id AND is_deleted = 0$child_company_scope ORDER BY (expiry_date IS NULL), expiry_date ASC, id DESC");
    if ($q) while ($r = mysqli_fetch_assoc($q)) $compliance_rows[] = $r;
}
if ($fp_exists) {
    $q = mysqli_query($conn, "SELECT p.* FROM fleet_equipment_protection p WHERE p.equipment_id = $equipment_id AND p.is_deleted = 0" . (!$is_super_admin && $company_id > 0 ? " AND p.company_id = $company_id" : '') . " ORDER BY p.id DESC");
    if ($q) while ($r = mysqli_fetch_assoc($q)) $protection_rows[] = $r;
}
if ($fcmp_exists) {
    $q = mysqli_query($conn, "SELECT * FROM fleet_equipment_component WHERE equipment_id = $equipment_id AND is_deleted = 0$child_company_scope ORDER BY is_current DESC, id DESC");
    if ($q) while ($r = mysqli_fetch_assoc($q)) $component_rows[] = $r;
}
if ($fh_exists) {
    $q = mysqli_query($conn, "SELECT h.*, pr.name AS project_name FROM fleet_equipment_history h LEFT JOIN project pr ON pr.id = h.project_id WHERE h.equipment_id = $equipment_id" . (!$is_super_admin && $company_id > 0 ? " AND h.company_id = $company_id" : '') . " ORDER BY h.event_date DESC, h.id DESC LIMIT 100");
    if ($q) while ($r = mysqli_fetch_assoc($q)) $history_rows[] = $r;
}

// المنفّذ/المورد صار إدخالاً يدوياً حرّاً (غير مربوط بجدول الموردين) — لا حاجة لجلب الموردين.

// حساب حالة الوثيقة من تاريخ الانتهاء (سارية/قاربت/منتهية) + تنبيهات حرجة
$DOC_ALERT_DAYS = 30;
$today_ts = strtotime(date('Y-m-d'));
$critical_expired = 0; $docs_expired = 0; $docs_soon = 0;
function ems_doc_status($expiry, $today_ts, $days)
{
    if (empty($expiry) || $expiry === '0000-00-00') return ['code' => 'none', 'label' => '—', 'cls' => ''];
    $ts = strtotime($expiry);
    if (!$ts) return ['code' => 'none', 'label' => '—', 'cls' => ''];
    if ($ts < $today_ts) return ['code' => 'expired', 'label' => 'منتهية', 'cls' => 'status-inactive'];
    if ($ts <= $today_ts + ($days * 86400)) return ['code' => 'soon', 'label' => 'قاربت الانتهاء', 'cls' => 'badge-busy'];
    return ['code' => 'valid', 'label' => 'سارية', 'cls' => 'status-active'];
}
foreach ($compliance_rows as $cr) {
    $stt = ems_doc_status($cr['expiry_date'] ?? null, $today_ts, $DOC_ALERT_DAYS);
    if ($stt['code'] === 'expired') { $docs_expired++; if (!empty($cr['is_critical'])) $critical_expired++; }
    elseif ($stt['code'] === 'soon') { $docs_soon++; }
}

$ee = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };

$page_title = 'إيكوبيشن | بطاقة المعدة';
include '../inheader.php';
include '../insidebar.php';
?>

<style>
.equipment-profile-page .profile-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px; margin-bottom:14px; }
.equipment-profile-page .profile-card { background:#fff; border:1px solid #ece6d8; border-radius:12px; padding:12px; }
.equipment-profile-page .kpi { font-weight:800; font-size:1.4rem; color:#0f766e; }
.equipment-profile-page .label { color:#6b7280; font-size:.9rem; }
</style>

<div class="main equipment-profile-page ems-unified-page-shell">
    <?php
    // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
    $header_title   = 'بطاقة المعدة / الشاحنة';
    $header_icon    = 'fas fa-id-card';
    $header_actions = array(
        array('href' => 'add_drivers.php?equipment_id=' . intval($equipment_id), 'class' => 'add-btn', 'icon' => 'fas fa-user-cog', 'label' => 'إدارة المشغلين'),
        array('href' => 'equipments.php?edit=' . intval($equipment_id), 'class' => 'add-btn', 'icon' => 'fas fa-edit', 'label' => 'تعديل المعدة'),
    );
    $header_back = array('href' => 'equipments.php', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <div class="profile-card" style="margin-bottom:12px;">
        <h2 style="margin:0 0 8px 0;"><?php echo htmlspecialchars($equipment['name']); ?></h2>
        <div class="label">
            الكود: <?php echo htmlspecialchars($equipment['code']); ?> |
            النوع: <?php echo htmlspecialchars($equipment['equipment_type_name'] ?: $equipment['type']); ?> |
            المورد: <?php echo htmlspecialchars($equipment['supplier_name'] ?: '-'); ?> |
            الحالة: <?php echo intval($equipment['status']) === 1 ? 'متاحة' : 'مشغولة'; ?>
        </div>
        <div class="label" style="margin-top:6px;">
            الموديل: <?php echo htmlspecialchars($equipment['model'] ?: '-'); ?> |
            سنة الصنع: <?php echo htmlspecialchars($equipment['manufacturing_year'] ?: '-'); ?> |
            رقم الهيكل: <?php echo htmlspecialchars($equipment['chassis_number'] ?: '-'); ?>
        </div>
        <?php
        // حالة الكرت (حوكمة خفيفة)
        $card_state = isset($equipment['card_state']) ? $equipment['card_state'] : 'active';
        $card_is_active = ($card_state === 'active');
        ?>
        <div style="margin-top:10px;">
            <?php if ($card_is_active): ?>
                <span class="status-active" style="padding:4px 10px;border-radius:6px;background:#e7f7ee;color:#1f9d55;font-weight:700;">
                    <i class="fas fa-id-card"></i> كرت نشط (معتمد)
                </span>
            <?php else: ?>
                <span class="status-inactive" style="padding:4px 10px;border-radius:6px;background:#fdeaea;color:#c0392b;font-weight:700;">
                    <i class="fas fa-id-card"></i> كرت مسودة
                </span>
                <?php if (!empty($can_edit ?? true)): ?>
                    <form method="post" action="approve_card.php" class="d-inline" style="margin-inline-start:8px"
                          onsubmit="return confirm('اعتماد كرت هذه المعدة؟');">
                        <input type="hidden" name="equipment_id" value="<?php echo intval($equipment_id); ?>">
                        <input type="hidden" name="return" value="equipment_profile.php">
                        <input type="hidden" name="return_id" value="<?php echo intval($equipment_id); ?>">
                        <button type="submit" class="btn btn-success" style="padding:4px 12px;">
                            <i class="fas fa-circle-check"></i> اعتماد الكرت
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php
    // ── بطاقة: الهوية والمصدر + العدّاد (كرت المعدة) ──
    $pf = function ($k) use ($equipment) {
        return isset($equipment[$k]) && $equipment[$k] !== '' && $equipment[$k] !== null
            ? htmlspecialchars((string) $equipment[$k]) : '—';
    };
    $cap = (isset($equipment['capacity']) && $equipment['capacity'] !== '' && $equipment['capacity'] !== null)
        ? (htmlspecialchars((string) $equipment['capacity']) . ' ' . htmlspecialchars((string) ($equipment['capacity_uom'] ?? ''))) : '—';
    $acq = (isset($equipment['acquisition_cost']) && $equipment['acquisition_cost'] !== '' && $equipment['acquisition_cost'] !== null)
        ? (htmlspecialchars((string) $equipment['acquisition_cost']) . ' ' . htmlspecialchars((string) ($equipment['acquisition_currency'] ?? ''))) : '—';
    $meter = (isset($equipment['opening_meter']) && $equipment['opening_meter'] !== '' && $equipment['opening_meter'] !== null)
        ? (htmlspecialchars((string) $equipment['opening_meter']) . ' ' . htmlspecialchars((string) ($equipment['meter_uom'] ?? ''))) : '—';
    ?>
    <div class="card" style="margin-bottom:14px;">
        <div class="card-header"><h5><i class="fas fa-id-badge"></i> الهوية والمصدر والعدّاد</h5></div>
        <div class="card-body">
            <div class="profile-grid">
                <div class="profile-card"><div class="label">الفئة التشغيلية</div><div><?php echo $pf('operating_category'); ?></div></div>
                <div class="profile-card"><div class="label">بلد الصنع</div><div><?php echo $pf('origin_country'); ?></div></div>
                <div class="profile-card"><div class="label">رقم الموتور</div><div><?php echo $pf('engine_no'); ?></div></div>
                <div class="profile-card"><div class="label">رقم اللوحة</div><div><?php echo $pf('plate_no'); ?></div></div>
                <div class="profile-card"><div class="label">السعة/القدرة</div><div><?php echo $cap; ?></div></div>
                <div class="profile-card"><div class="label">المقاسات الفنية</div><div><?php echo $pf('dimensions'); ?></div></div>
                <div class="profile-card"><div class="label">نوع المصدر</div><div><?php echo $pf('source_type'); ?></div></div>
                <div class="profile-card"><div class="label">تاريخ الدخول</div><div><?php echo $pf('entry_date'); ?></div></div>
                <div class="profile-card"><div class="label">تكلفة الشراء</div><div><?php echo $acq; ?></div></div>
                <div class="profile-card"><div class="label">العدّاد الافتتاحي</div><div><?php echo $meter; ?></div></div>
                <div class="profile-card"><div class="label">مصدر العدّاد</div><div><?php echo $pf('meter_source'); ?></div></div>
            </div>
        </div>
    </div>

    <div class="profile-grid">
        <div class="profile-card"><div class="kpi"><?php echo $operations_count; ?></div><div class="label">إجمالي عمليات التشغيل</div></div>
        <div class="profile-card"><div class="kpi"><?php echo $active_operations; ?></div><div class="label">عمليات نشطة</div></div>
        <div class="profile-card"><div class="kpi"><?php echo $projects_count; ?></div><div class="label">المشاريع المرتبطة</div></div>
        <div class="profile-card"><div class="kpi"><?php echo $drivers_count; ?></div><div class="label">المشغلون النشطون</div></div>
        <div class="profile-card"><div class="kpi"><?php echo number_format($hours_sum, 0); ?></div><div class="label">ساعات التشغيل</div></div>
        <div class="profile-card"><div class="kpi"><?php echo number_format($standby_sum, 0); ?></div><div class="label">ساعات الاستعداد</div></div>
    </div>

    <div class="card" style="margin-bottom:14px;">
        <div class="card-header"><h5><i class="fas fa-project-diagram"></i> المشاريع المرتبطة بالمعدة</h5></div>
        <div class="card-body">
            <table id="equipmentProjectsTable" class="display" style="width:100%;">
                <thead><tr><th>المشروع</th><th>كود المشروع</th><th>الساعات</th><th>عدد الورديات</th></tr></thead>
                <tbody>
                    <?php if ($projects_list): while ($row = mysqli_fetch_assoc($projects_list)): ?>
                        <tr>
                            <td><?php if (!empty($row['id'])): ?><a href="../Projects/project_profile.php?id=<?php echo intval($row['id']); ?>"><?php echo htmlspecialchars($row['name']); ?></a><?php else: ?>غير محدد<?php endif; ?></td>
                            <td><?php echo htmlspecialchars($row['project_code'] ?: '-'); ?></td>
                            <td><?php echo number_format($row['total_hours'], 0); ?></td>
                            <td><?php echo intval($row['shifts_count']); ?></td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h5><i class="fas fa-users"></i> آخر المشغلين المرتبطين</h5></div>
        <div class="card-body">
            <table id="equipmentDriversTable" class="display" style="width:100%;">
                <thead><tr><th>المشغل</th><th>تاريخ البداية</th><th>تاريخ النهاية</th><th>الحالة</th></tr></thead>
                <tbody>
                    <?php if ($drivers_list): while ($row = mysqli_fetch_assoc($drivers_list)): ?>
                        <tr>
                            <td><a href="../Drivers/driver_profile.php?id=<?php echo intval($row['id']); ?>"><?php echo htmlspecialchars($row['name']); ?></a></td>
                            <td><?php echo htmlspecialchars($row['start_date']); ?></td>
                            <td><?php echo htmlspecialchars(ems_format_open_end($row['end_date'])); ?></td>
                            <td><?php echo intval($row['status']) === 1 ? 'نشط' : 'متوقف'; ?></td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ════════════════ كرت المعدة: جداول الأبناء ════════════════ -->
    <?php if ($critical_expired > 0): ?>
        <div class="success-message is-error" style="margin:12px 0;font-weight:700;">
            <i class="fa-solid fa-triangle-exclamation"></i>
            تحذير حرج: توجد <?= (int) $critical_expired; ?> وثيقة حرجة منتهية الصلاحية لهذه المعدة. (سيُربط لاحقاً بمنع التشغيل/التخصيص)
        </div>
    <?php endif; ?>

    <!-- (1) الوثائق الرسمية -->
    <div class="card" id="sec-docs">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
            <h5><i class="fas fa-file-contract"></i> الوثائق الرسمية
                <?php if ($docs_expired): ?><span class="status-inactive" style="margin-inline-start:6px;">منتهية: <?= (int) $docs_expired; ?></span><?php endif; ?>
                <?php if ($docs_soon): ?><span class="badge-busy" style="margin-inline-start:6px;">قاربت: <?= (int) $docs_soon; ?></span><?php endif; ?>
            </h5>
            <?php if ($can_edit_card): ?><button type="button" class="btn btn-primary btn-sm" onclick="emsToggle('add-docs')"><i class="fas fa-plus"></i> إضافة وثيقة</button><?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($can_edit_card): ?>
            <form id="add-docs" class="child-add-form ems-form" method="post" action="equipment_child_save.php" enctype="multipart/form-data" style="display:none;margin-bottom:14px;">
                <input type="hidden" name="entity" value="compliance"><input type="hidden" name="action" value="add"><input type="hidden" name="equipment_id" value="<?= (int) $equipment_id; ?>">
                <div class="form-grid">
                    <div><label>نوع الوثيقة *</label><select name="doc_type" required><option value="">-- اختر --</option><?php foreach ($DOC_TYPES as $o) echo '<option>' . $ee($o) . '</option>'; ?></select></div>
                    <div><label>الرقم/المرجع</label><input type="text" name="reference"></div>
                    <div><label>تاريخ الإصدار</label><input type="date" name="issue_date"></div>
                    <div><label>تاريخ الانتهاء</label><input type="date" name="expiry_date"></div>
                    <div><label>مرفق (صورة/PDF)</label><input type="file" name="attachment" accept="image/*,application/pdf"></div>
                    <div style="display:flex;align-items:center;gap:6px;margin-top:22px;"><input type="checkbox" name="is_critical" id="doc_crit" value="1"><label for="doc_crit" style="margin:0;">وثيقة حرجة</label></div>
                </div>
                <button type="submit" class="btn btn-success btn-sm" style="margin-top:10px;"><i class="fa-solid fa-save"></i> حفظ</button>
            </form>
            <?php endif; ?>
            <div class="table-container">
                <table class="display" style="width:100%;">
                    <thead><tr><th>النوع</th><th>المرجع</th><th>الإصدار</th><th>الانتهاء</th><th>حرجة</th><th>الحالة</th><th>مرفق</th><?php if ($can_edit_card): ?><th></th><?php endif; ?></tr></thead>
                    <tbody>
                        <?php if (empty($compliance_rows)): ?>
                            <tr><td colspan="<?= $can_edit_card ? 8 : 7; ?>" style="text-align:center;color:#888;">لا توجد وثائق مُسجّلة</td></tr>
                        <?php else: foreach ($compliance_rows as $cr): $st = ems_doc_status($cr['expiry_date'] ?? null, $today_ts, $DOC_ALERT_DAYS); ?>
                            <tr>
                                <td><?= $ee($cr['doc_type']); ?></td>
                                <td><?= $ee($cr['reference'] ?: '—'); ?></td>
                                <td><?= $ee($cr['issue_date'] ?: '—'); ?></td>
                                <td><?= $ee($cr['expiry_date'] ?: '—'); ?></td>
                                <td><?= !empty($cr['is_critical']) ? '<span class="status-inactive">حرجة</span>' : '—'; ?></td>
                                <td><?php echo $st['cls'] ? "<span class='{$st['cls']}'>" . $ee($st['label']) . "</span>" : $ee($st['label']); ?></td>
                                <td><?php if (!empty($cr['attachment_path'])): ?><a href="fleet_file.php?f=<?= $ee(basename($cr['attachment_path'])); ?>" target="_blank"><i class="fas fa-paperclip"></i> عرض</a><?php else: ?>—<?php endif; ?></td>
                                <?php if ($can_edit_card): ?><td><form method="post" action="equipment_child_save.php" onsubmit="return confirm('حذف هذه الوثيقة؟');" style="margin:0;"><input type="hidden" name="entity" value="compliance"><input type="hidden" name="action" value="delete"><input type="hidden" name="row_id" value="<?= (int) $cr['id']; ?>"><input type="hidden" name="equipment_id" value="<?= (int) $equipment_id; ?>"><button class="action-btn delete" title="حذف"><i class="fa-solid fa-trash"></i></button></form></td><?php endif; ?>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- (2) تجهيزات الحماية -->
    <div class="card" id="sec-protection">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
            <h5><i class="fas fa-shield-halved"></i> تجهيزات الحماية</h5>
            <?php if ($can_edit_card): ?><button type="button" class="btn btn-primary btn-sm" onclick="emsToggle('add-prot')"><i class="fas fa-plus"></i> إضافة تجهيز</button><?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($can_edit_card): ?>
            <form id="add-prot" class="child-add-form ems-form" method="post" action="equipment_child_save.php" enctype="multipart/form-data" style="display:none;margin-bottom:14px;">
                <input type="hidden" name="entity" value="protection"><input type="hidden" name="action" value="add"><input type="hidden" name="equipment_id" value="<?= (int) $equipment_id; ?>">
                <div class="form-grid">
                    <div><label>نوع الحماية *</label><select name="protection_type" required><option value="">-- اختر --</option><?php foreach ($PROTECTION_TYPES as $o) echo '<option>' . $ee($o) . '</option>'; ?></select></div>
                    <div><label>الوصف</label><input type="text" name="description"></div>
                    <div><label>تاريخ التركيب/البدء</label><input type="date" name="start_date"></div>
                    <div><label>التكلفة</label><input type="number" step="0.01" name="cost"></div>
                    <div><label>الحالة</label><select name="state"><option value="">-- اختر --</option><?php foreach ($PROTECTION_STATES as $o) echo '<option>' . $ee($o) . '</option>'; ?></select></div>
                    <div><label>تاريخ التجديد</label><input type="date" name="renewal_date"></div>
                    <div><label>المنفّذ/المورد</label><input type="text" name="partner_name" autocomplete="off" placeholder="اكتب اسم المنفّذ/المورد (إدخال يدوي)"></div>
                    <div><label>مرتبط بوثيقة (للتأمين)</label><select name="compliance_id"><option value="">-- بدون --</option><?php foreach ($compliance_rows as $cr) echo '<option value="' . (int) $cr['id'] . '">' . $ee($cr['doc_type'] . ($cr['reference'] ? ' — ' . $cr['reference'] : '')) . '</option>'; ?></select></div>
                    <div><label>مرفق</label><input type="file" name="attachment" accept="image/*,application/pdf"></div>
                </div>
                <button type="submit" class="btn btn-success btn-sm" style="margin-top:10px;"><i class="fa-solid fa-save"></i> حفظ</button>
            </form>
            <?php endif; ?>
            <div class="table-container">
                <table class="display" style="width:100%;">
                    <thead><tr><th>النوع</th><th>الوصف</th><th>البدء</th><th>التكلفة</th><th>الحالة</th><th>التجديد</th><th>المنفّذ</th><th>مرفق</th><?php if ($can_edit_card): ?><th></th><?php endif; ?></tr></thead>
                    <tbody>
                        <?php if (empty($protection_rows)): ?>
                            <tr><td colspan="<?= $can_edit_card ? 9 : 8; ?>" style="text-align:center;color:#888;">لا توجد تجهيزات مُسجّلة</td></tr>
                        <?php else: foreach ($protection_rows as $pr): $needs = ($pr['state'] ?? '') === 'يحتاج تجديداً'; ?>
                            <tr>
                                <td><?= $ee($pr['protection_type']); ?></td>
                                <td><?= $ee($pr['description'] ?: '—'); ?></td>
                                <td><?= $ee($pr['start_date'] ?: '—'); ?></td>
                                <td><?= $pr['cost'] !== null && $pr['cost'] !== '' ? $ee($pr['cost']) : '—'; ?></td>
                                <td><?php echo $needs ? '<span class="badge-busy">' . $ee($pr['state']) . '</span>' : $ee($pr['state'] ?: '—'); ?></td>
                                <td><?= $ee($pr['renewal_date'] ?: '—'); ?></td>
                                <td><?= $ee($pr['partner_name'] ?: '—'); ?></td>
                                <td><?php if (!empty($pr['attachment_path'])): ?><a href="fleet_file.php?f=<?= $ee(basename($pr['attachment_path'])); ?>" target="_blank"><i class="fas fa-paperclip"></i> عرض</a><?php else: ?>—<?php endif; ?></td>
                                <?php if ($can_edit_card): ?><td><form method="post" action="equipment_child_save.php" onsubmit="return confirm('حذف هذا التجهيز؟');" style="margin:0;"><input type="hidden" name="entity" value="protection"><input type="hidden" name="action" value="delete"><input type="hidden" name="row_id" value="<?= (int) $pr['id']; ?>"><input type="hidden" name="equipment_id" value="<?= (int) $equipment_id; ?>"><button class="action-btn delete" title="حذف"><i class="fa-solid fa-trash"></i></button></form></td><?php endif; ?>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- (3) المكوّنات الكبرى -->
    <div class="card" id="sec-components">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
            <h5><i class="fas fa-gears"></i> المكوّنات الكبرى</h5>
            <?php if ($can_edit_card): ?><button type="button" class="btn btn-primary btn-sm" onclick="emsToggle('add-comp')"><i class="fas fa-plus"></i> إضافة مكوّن</button><?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($can_edit_card): ?>
            <form id="add-comp" class="child-add-form ems-form" method="post" action="equipment_child_save.php" style="display:none;margin-bottom:14px;">
                <input type="hidden" name="entity" value="component"><input type="hidden" name="action" value="add"><input type="hidden" name="equipment_id" value="<?= (int) $equipment_id; ?>">
                <div class="form-grid">
                    <div><label>نوع المكوّن *</label><select name="component_type" required><option value="">-- اختر --</option><?php foreach ($COMPONENT_TYPES as $o) echo '<option>' . $ee($o) . '</option>'; ?></select></div>
                    <div><label>الرقم التسلسلي</label><input type="text" name="serial_no"></div>
                    <div><label>تاريخ التركيب</label><input type="date" name="install_date"></div>
                    <div style="display:flex;align-items:center;gap:6px;margin-top:22px;"><input type="checkbox" name="is_current" id="comp_cur" value="1" checked><label for="comp_cur" style="margin:0;">مُركَّب حالياً</label></div>
                </div>
                <button type="submit" class="btn btn-success btn-sm" style="margin-top:10px;"><i class="fa-solid fa-save"></i> حفظ</button>
            </form>
            <?php endif; ?>
            <div class="table-container">
                <table class="display" style="width:100%;">
                    <thead><tr><th>النوع</th><th>الرقم التسلسلي</th><th>التركيب</th><th>حالي؟</th><th>الاستبدال</th><th>ساعات المكوّن</th><th>مرّات الاستبدال</th><?php if ($can_edit_card): ?><th></th><?php endif; ?></tr></thead>
                    <tbody>
                        <?php if (empty($component_rows)): ?>
                            <tr><td colspan="<?= $can_edit_card ? 8 : 7; ?>" style="text-align:center;color:#888;">لا توجد مكوّنات مُسجّلة</td></tr>
                        <?php else: foreach ($component_rows as $cm): ?>
                            <tr>
                                <td><?= $ee($cm['component_type']); ?></td>
                                <td><?= $ee($cm['serial_no'] ?: '—'); ?></td>
                                <td><?= $ee($cm['install_date'] ?: '—'); ?></td>
                                <td><?= !empty($cm['is_current']) ? '<span class="status-active">نعم</span>' : 'لا'; ?></td>
                                <td style="color:#aaa;">لاحقاً</td>
                                <td style="color:#aaa;">لاحقاً</td>
                                <td style="color:#aaa;">لاحقاً</td>
                                <?php if ($can_edit_card): ?><td><form method="post" action="equipment_child_save.php" onsubmit="return confirm('حذف هذا المكوّن؟');" style="margin:0;"><input type="hidden" name="entity" value="component"><input type="hidden" name="action" value="delete"><input type="hidden" name="row_id" value="<?= (int) $cm['id']; ?>"><input type="hidden" name="equipment_id" value="<?= (int) $equipment_id; ?>"><button class="action-btn delete" title="حذف"><i class="fa-solid fa-trash"></i></button></form></td><?php endif; ?>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- (4) سجل تاريخ المعدة (إدراج فقط) -->
    <div class="card" id="sec-history">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
            <h5><i class="fas fa-timeline"></i> سجل تاريخ المعدة</h5>
            <?php if ($can_edit_card): ?><button type="button" class="btn btn-primary btn-sm" onclick="emsToggle('add-hist')"><i class="fas fa-plus"></i> إضافة حدث يدوي</button><?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($can_edit_card): ?>
            <form id="add-hist" class="child-add-form ems-form" method="post" action="equipment_child_save.php" style="display:none;margin-bottom:14px;">
                <input type="hidden" name="entity" value="history"><input type="hidden" name="action" value="add"><input type="hidden" name="equipment_id" value="<?= (int) $equipment_id; ?>">
                <div class="form-grid">
                    <div><label>نوع الحدث *</label><select name="event_type" required><option value="">-- اختر --</option><?php foreach ($EVENT_TYPES as $o) echo '<option>' . $ee($o) . '</option>'; ?></select></div>
                    <div><label>التاريخ والوقت *</label><input type="datetime-local" name="event_date" value="<?= date('Y-m-d\TH:i'); ?>" required></div>
                    <div><label>الموقع</label><input type="text" name="site_id"></div>
                    <div><label>تاريخ دخول/خروج</label><input type="date" name="in_out_date"></div>
                    <div style="grid-column:1/-1;"><label>ملاحظة</label><input type="text" name="note"></div>
                </div>
                <button type="submit" class="btn btn-success btn-sm" style="margin-top:10px;"><i class="fa-solid fa-save"></i> تسجيل</button>
            </form>
            <?php endif; ?>
            <?php if (empty($history_rows)): ?>
                <div style="text-align:center;color:#888;padding:14px;">لا توجد أحداث مُسجّلة</div>
            <?php else: ?>
                <ul class="ems-timeline">
                    <?php foreach ($history_rows as $h): ?>
                        <li>
                            <span class="ems-tl-dot"></span>
                            <div class="ems-tl-body">
                                <div><strong><?= $ee($h['event_type']); ?></strong>
                                    <span style="color:#888;font-size:12px;margin-inline-start:8px;"><?= $ee($h['event_date']); ?></span></div>
                                <div style="font-size:13px;color:#555;">
                                    <?php
                                    $bits = [];
                                    if (!empty($h['project_name'])) $bits[] = 'المشروع: ' . $ee($h['project_name']);
                                    if (!empty($h['site_id'])) $bits[] = 'الموقع: ' . $ee($h['site_id']);
                                    if (!empty($h['in_out_date'])) $bits[] = 'دخول/خروج: ' . $ee($h['in_out_date']);
                                    if (!empty($h['note'])) $bits[] = $ee($h['note']);
                                    echo implode(' · ', $bits) ?: '—';
                                    ?>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

</div>

<style>
    .ems-timeline { list-style:none; margin:0; padding:0; position:relative; }
    .ems-timeline:before { content:''; position:absolute; right:7px; top:4px; bottom:4px; width:2px; background:#e3e3e3; }
    .ems-timeline li { position:relative; padding:0 26px 16px 0; }
    .ems-tl-dot { position:absolute; right:1px; top:4px; width:14px; height:14px; border-radius:50%; background:#F3BE00; border:2px solid #fff; box-shadow:0 0 0 1px #e3e3e3; }
    .child-add-form { background:#fafafa; border:1px solid #ececec; border-radius:8px; padding:12px; }
</style>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script>
function emsToggle(id){ var el=document.getElementById(id); if(el){ el.style.display = (el.style.display==='none'||!el.style.display) ? 'block' : 'none'; } }
$(function () {
    $('#equipmentProjectsTable').DataTable({ language: { url: '/ems/assets/i18n/datatables/ar.json' } });
    $('#equipmentDriversTable').DataTable({ language: { url: '/ems/assets/i18n/datatables/ar.json' } });
});
</script>
