<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';

$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../login.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

$supplier_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($supplier_id <= 0) {
    ems_gov_flash_redirect('suppliers.php', 'معرف المورد غير صحيح ❌', 'GOV-REF-404', '');
    exit();
}

// H-20: جلسةُ مشرف المورد تُقصر على موردها — 403 مسجَّلةٌ لغيره
require_once __DIR__ . '/../app/Services/Portal/SupplierPortalGuard.php';
\App\Services\Portal\SupplierPortalGuard::enforce($conn, $_SESSION['user'], $supplier_id, 'Suppliers/supplier_profile.php');

// العزل عبر بوابة المستأجر (K9 · هجرة 2026-07-15): كشف الأعمدة أُسقط (مضمونة
// بالسجل)، والسوبر عبر forAllTenants المسجَّل (سلوك الأصل: بلا تنطيق شركة).
$spf_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('supplier profile super') : ems_tenant_db();

try {
    $supplier_rows = $spf_gate->scopedQuery(array(
        'scope' => array('s' => 'suppliers'),
    ), "SELECT s.* FROM suppliers s
        WHERE {TENANT_SCOPE} AND s.id = ? AND COALESCE(s.is_deleted,0)=0
        LIMIT 1", array($supplier_id));
} catch (\Throwable $t) { $supplier_rows = array(); }
$supplier = !empty($supplier_rows) ? $supplier_rows[0] : null;

if (!$supplier) {
    ems_gov_flash_redirect('suppliers.php', 'المورد غير موجود او خارج نطاق الشركة ❌', 'GOV-SCOPE-403', '');
    exit();
}

$equipments_count = 0;
$contracts_count = 0;
$active_contracts = 0;
$projects_count = 0;
$total_hours = 0;
$timesheet_hours = 0;

$spf_agg = function (array $decl, $sql, array $params) use ($spf_gate) {
    try {
        $rows = $spf_gate->scopedQuery($decl, $sql, $params);
        return !empty($rows) ? $rows[0]['c'] : 0;
    } catch (\Throwable $t) {
        return 0;
    }
};

$equipments_count = intval($spf_agg(array('scope' => array('e' => 'equipments')),
    "SELECT COUNT(*) AS c FROM equipments e WHERE {TENANT_SCOPE} AND e.suppliers = ?", array($supplier_id)));

$contracts_count = intval($spf_agg(array('scope' => array('sc' => 'supplierscontracts')),
    "SELECT COUNT(*) AS c FROM supplierscontracts sc WHERE {TENANT_SCOPE} AND sc.supplier_id = ?", array($supplier_id)));

$active_contracts = intval($spf_agg(array('scope' => array('sc' => 'supplierscontracts')),
    "SELECT COUNT(*) AS c FROM supplierscontracts sc WHERE {TENANT_SCOPE} AND sc.supplier_id = ? AND sc.status = 1", array($supplier_id)));

$projects_count = intval($spf_agg(array('scope' => array('sc' => 'supplierscontracts')),
    "SELECT COUNT(DISTINCT sc.project_id) AS c FROM supplierscontracts sc WHERE {TENANT_SCOPE} AND sc.supplier_id = ?", array($supplier_id)));

$total_hours = floatval($spf_agg(array('scope' => array('sc' => 'supplierscontracts')),
    "SELECT IFNULL(SUM(sc.forecasted_contracted_hours),0) AS c FROM supplierscontracts sc WHERE {TENANT_SCOPE} AND sc.supplier_id = ?", array($supplier_id)));

$timesheet_hours = floatval($spf_agg(array('scope' => array('t' => 'timesheet', 'o' => 'operations', 'e' => 'equipments')),
    "SELECT IFNULL(SUM(t.operator_hours + t.operator_standby_hours),0) AS c
     FROM timesheet t
     INNER JOIN operations o ON o.id = t.operator
     INNER JOIN equipments e ON e.id = o.equipment
     WHERE {TENANT_SCOPE} AND e.suppliers = ? AND t.status = 1", array($supplier_id)));

try {
    $equipments_breakdown = $spf_gate->scopedQuery(array(
        'scope'  => array('e' => 'equipments'),
        'enrich' => array('o' => 'operations', 't' => 'timesheet'),
    ), "SELECT
            e.id,
            e.name,
            e.code,
            IFNULL(SUM(t.operator_hours + t.operator_standby_hours),0) AS hours_sum,
            COUNT(DISTINCT o.project_id) AS projects_count
        FROM equipments e
        LEFT JOIN operations o ON o.equipment = e.id
        LEFT JOIN timesheet t ON t.operator = o.id AND t.status = 1
        WHERE {TENANT_SCOPE} AND e.suppliers = ?
        GROUP BY e.id, e.name, e.code
        ORDER BY hours_sum DESC
        LIMIT 10", array($supplier_id));
} catch (\Throwable $t) { $equipments_breakdown = array(); }

try {
    $contracts_list = $spf_gate->scopedQuery(array(
        'scope'  => array('sc' => 'supplierscontracts'),
        'enrich' => array('p' => 'project'), // اسم المشروع — LEFT بلا تنطيق (سلوك الأصل)
    ), "SELECT sc.id, sc.contract_signing_date, sc.actual_end, sc.status, sc.hours_monthly_target, sc.forecasted_contracted_hours,
            p.name AS project_name
        FROM supplierscontracts sc
        LEFT JOIN project p ON p.id = sc.project_id
        WHERE {TENANT_SCOPE} AND sc.supplier_id = ?
        ORDER BY sc.id DESC
        LIMIT 10", array($supplier_id));
} catch (\Throwable $t) { $contracts_list = array(); }

$page_title = 'إيكوبيشن | بطاقة المورد';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
require_once __DIR__ . '/../includes/profile_kit.php';   // عُدّةُ بطاقةِ الكِيان — التأليفُ بديلُ النسخ
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
$sf_supplier_id = intval($_GET['id'] ?? 0); $sf_active = 'profile';
if ($sf_supplier_id > 0) include __DIR__ . '/../includes/supplier_file_tabs.php';
?>

<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ)
        وبطاقتُها الآن على مكوّنِ «بطاقةِ الكِيان» الواحد — `assets/css/ems-profile.css`
        عبر `includes/profile_kit.php`، كبطاقتَي العميلِ والموظف. */ ?>

<div class="main supplier-profile-page ems-profile ems-unified-page-shell">
    <?php
    // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
    $header_title   = 'بطاقة المورد';
    $header_icon    = 'fas fa-id-card-alt';
    $header_actions = array(
        array('href' => 'supplierscontracts.php?id=' . intval($supplier_id), 'class' => 'add-btn', 'icon' => 'fas fa-file-contract', 'label' => 'عقود المورد'),
    );
    $header_back = array('href' => 'suppliers.php', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>
<?php require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('supplier', 'نظرةٌ عامة'); ?>

    <?php echo ems_states_bundle('لا بياناتَ مسجَّلةً لهذا المورد بعد', 'ستظهر معداتُه وعقودُه هنا فورَ تسجيلِها'); ?>

    <?php
    /* ══ لوحُ الهوية ═══════════════════════════════════════════════════════
       كان سطرًا واحدًا تفصلُه شُرَطٌ رأسية: «الكود | النوع | الهاتف | الحالة»
       — يُقرأ بالبحثِ لا بالنظر، والحالةُ فيه **نصٌّ بلا لون** فلا يُميَّز
       الموقوفُ من النشطِ إلا بالقراءة. صار: الحالةُ شارةً ملوَّنةً بجانبِ
       الاسم، والهويةُ رقائقَ، والاتصالُ حقائقَ معنونةً — والغائبُ يُعلَن «—». */
    echo ems_profile_hero(array(
        'name'   => $supplier['name'],
        'icon'   => 'fas fa-truck-field',
        'status' => array(
            'text' => (intval($supplier['status']) === 1) ? 'نشط' : 'معلق',
            'tone' => (intval($supplier['status']) === 1) ? 'ok' : 'danger',
            'icon' => (intval($supplier['status']) === 1) ? 'fas fa-circle-check' : 'fas fa-circle-pause',
        ),
        'chips'  => array(
            array('text' => $supplier['supplier_code'], 'icon' => 'fas fa-hashtag', 'mono' => true),
            array('text' => $supplier['supplier_type'] ?: 'نوعٌ غيرُ محدد', 'icon' => 'fas fa-tag'),
        ),
        /* الحقولُ من أعمدةِ `suppliers` المقيسةِ حرفًا — لا عمودَ يُفترض:
           العنوانُ فيها `full_address` لا `address`. */
        'facts'  => array(
            array('label' => 'الهاتف',        'value' => $supplier['phone']),
            array('label' => 'البريد',        'value' => $supplier['email']),
            array('label' => 'جهةُ الاتصال',  'value' => $supplier['contact_person_name']),
            array('label' => 'هاتفُ الاتصال', 'value' => $supplier['contact_person_phone']),
            array('label' => 'السجلُّ التجاري', 'value' => $supplier['commercial_registration']),
            array('label' => 'العنوان',       'value' => $supplier['full_address']),
        ),
    ));
    ?>

    <?php
    /* ── INJ-0158 · بطاقاتُ المؤشرِ بعقدِها السباعيِّ ────────────────────────────
         كانت ستُّ بطاقاتٍ تعرض **رقمًا عاريًا وتسميةً** فقط: بلا وحدةٍ ولا فترةٍ
         ولا مقارنةٍ ولا حالةٍ ولا مصدرٍ ولا رابطِ تعمّق. ورقمٌ لا يُتعمَّق فيه
         لا يُقرَّر عليه. والمكوّنُ `ems_kpi_card` قائمٌ ويرفض التصييرَ بأقلَّ من
         السبعةِ — فالحكمُ يصير بالبناءِ لا بالمراجعة. */
    require_once __DIR__ . '/../includes/kpi_card.php';
    $__sid = (int) $supplier['id'];
    $__now = 'لحظي (' . date('Y-m-d H:i') . ')';
    /* ── والمخوَّلُ جزئيًّا لا يجد الحقولَ الحساسةَ في **استجابةِ الخادم** ────────
         إخفاءٌ بـCSS ليس منعًا: الرقمُ يبقى في المصدرِ يقرؤه كلُّ من فتح
         «عرضَ المصدر». فبطاقتا الساعاتِ (المتعاقَدُ عليه والمُشغَّلُ فعلًا) —
         وهما أساسُ الفوترة — **لا تُصيَّران أصلًا** لمن لا يملك عرضَ مصدرِهما. */
    $__mayOpen = function ($code) use ($conn) {
        $role = isset($_SESSION['user']['role']) ? (string) $_SESSION['user']['role'] : '';
        if ($role === '-1') { return true; }
        $st = $conn->prepare('SELECT 1 FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                               WHERE m.code = ? AND rp.role_id = ? AND rp.can_view = 1 LIMIT 1');
        $rid = (int) $role;
        $st->bind_param('si', $code, $rid);
        $st->execute();
        $found = (bool) $st->get_result()->fetch_row();
        $st->close();
        return $found;
    };
    $__mayHours     = $__mayOpen('Timesheet/view_timesheet.php');
    $__mayContracts = $__mayOpen('Suppliers/supplierscontracts.php');
    $__cards = array(
        array('title' => 'عدد المعدات', 'value' => $equipments_count, 'unit' => 'معدة',
              'period' => $__now, 'status' => $equipments_count > 0 ? 'ok' : 'warn',
              'drill' => '../Equipments/equipments.php?supplier=' . $__sid,
              'icon' => 'fa-truck', 'scope' => 'المعداتُ المرتبطةُ بهذا المورّد'),
        array('title' => 'عدد العقود', 'value' => $contracts_count, 'unit' => 'عقد',
              'period' => $__now, 'status' => $contracts_count > 0 ? 'ok' : 'warn',
              'drill' => 'supplierscontracts.php?supplier=' . $__sid,
              'icon' => 'fa-file-contract', 'scope' => 'كلُّ العقودِ المسجَّلةِ له'),
        array('title' => 'العقود النشطة', 'value' => $active_contracts, 'unit' => 'عقد',
              'period' => $__now, 'status' => $active_contracts > 0 ? 'ok' : 'warn',
              'comparison' => 'من ' . (int) $contracts_count . ' عقدًا مسجَّلًا',
              'drill' => 'supplierscontracts.php?supplier=' . $__sid . '&state=active',
              'icon' => 'fa-circle-check', 'scope' => 'العقودُ السارية'),
        array('title' => 'المشاريع المرتبطة', 'value' => $projects_count, 'unit' => 'مشروع',
              'period' => $__now, 'status' => $projects_count > 0 ? 'ok' : 'neutral',
              'drill' => '../Projects/sites.php?supplier=' . $__sid,
              'icon' => 'fa-diagram-project', 'scope' => 'المشاريعُ التي يعمل فيها'),
        array('title' => 'إجمالي ساعات العقود', 'value' => number_format($total_hours, 0),
              'unit' => 'ساعة', 'period' => $__now, 'status' => 'neutral',
              'drill' => 'supplierscontracts.php?supplier=' . $__sid,
              'icon' => 'fa-hourglass-half', 'scope' => 'المُتعاقَدُ عليه'),
        array('title' => 'ساعات التشغيل الفعلية', 'value' => number_format($timesheet_hours, 0),
              'unit' => 'ساعة', 'period' => $__now,
              'status' => ($total_hours > 0 && $timesheet_hours < $total_hours * 0.5) ? 'warn' : 'ok',
              'comparison' => $total_hours > 0
                    ? ('من ' . number_format($total_hours, 0) . ' متعاقَدًا ('
                       . round($timesheet_hours * 100 / max(1, $total_hours)) . '٪)')
                    : '',
              'drill' => '../Timesheet/view_timesheet.php?supplier=' . $__sid,
              'icon' => 'fa-clock', 'scope' => 'المُسجَّلُ في التايم شيت'),
    );
    ?>
    <?php
    /* الحقولُ الحساسةُ تُنزع من المصفوفةِ قبل التصيير — لا تُخفى بعده */
    if (!$__mayContracts) {
        $__cards = array_values(array_filter($__cards, function ($c) {
            return $c['title'] !== 'إجمالي ساعات العقود';
        }));
    }
    if (!$__mayHours) {
        $__cards = array_values(array_filter($__cards, function ($c) {
            return $c['title'] !== 'ساعات التشغيل الفعلية';
        }));
    }
    ?>
    <div class="profile-grid">
        <?php foreach ($__cards as $__c) { echo ems_kpi_card($__c); } ?>
        <?php if (!$__mayHours || !$__mayContracts): ?>
        <div class="ems-kpi-card ems-kpi-warn" role="note">
            <div class="ems-kpi-title">بطاقاتٌ محجوبةٌ عن دورك</div>
            <div class="ems-kpi-value"><small>ساعاتُ العقودِ والتشغيلِ تُعرض لمن يملك
                عرضَ مصدرِها — ولا تُرسَل في استجابةِ الخادمِ لغيرِه.</small></div>
            <div class="ems-kpi-meta"><span>GOV-PERM-403</span><span>اطلبِ المنحَ من مدير الصلاحيات</span></div>
        </div>
        <?php endif; ?>
    </div>

    <?php
    /* ══ محطّتا المورد ══════════════════════════════════════════════════════
       كانت بطاقتانِ بوتستراب (`.card` + `.card-header` + `.card-body`) —
       لغةٌ ثالثةٌ بجانبِ لغةِ العميلِ ولغةِ الموظف. صارتا قسمَين في المكوّنِ
       الواحد: أعلى عشرِ معداتٍ بالساعات، ثم آخرُ عشرةِ عقود. */
    echo ems_profile_group_open(array(
        'title' => 'ما يقدّمه المورد',
        'icon'  => 'fas fa-handshake',
        'meta'  => 'معدّاتٌ ← عقودٌ',
    ));
    echo ems_profile_section_open(array(
        'title' => 'المعدات المرتبطة بالمورد',
        'icon'  => 'fas fa-truck',
        'meta'  => 'أعلى عشرٍ بالساعات',
    ));
    ?>
            <table id="supplierEquipmentsTable" class="display spf-table">
                <thead><tr><th>المعدة</th><th>الكود</th><th>عدد المشاريع</th><th>الساعات</th></tr></thead>
                <tbody>
                    <?php foreach ($equipments_breakdown as $row): ?>
                        <tr>
                            <td><a href="../Equipments/equipment_profile.php?id=<?php echo intval($row['id']); ?>"><?php echo htmlspecialchars($row['name']); ?></a></td>
                            <td><?php echo htmlspecialchars($row['code']); ?></td>
                            <td><?php echo intval($row['projects_count']); ?></td>
                            <td><?php echo number_format($row['hours_sum'], 0); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
    <?php echo ems_profile_section_close(); ?>

    <?php echo ems_profile_section_open(array(
        'title' => 'آخر عقود المورد',
        'icon'  => 'fas fa-file-contract',
        'meta'  => 'آخرُ عشرة',
    )); ?>
            <table id="supplierContractsTable" class="display spf-table">
                <thead><tr><th>المشروع</th><th>تاريخ التوقيع</th><th>مستهدف شهري</th><th>إجمالي ساعات</th><th>الحالة</th>
              <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              </tr></thead>
                <tbody>
                    <?php foreach ($contracts_list as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['project_name'] ?: 'غير محدد'); ?></td>
                            <td><?php echo htmlspecialchars($row['contract_signing_date']); ?></td>
                            <td><?php echo number_format($row['hours_monthly_target']); ?></td>
                            <td><?php echo number_format($row['forecasted_contracted_hours']); ?></td>
                            <td><?php echo (intval($row['status']) === 1) ? 'ساري' : 'منتهي'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
    <?php echo ems_profile_section_close(); ?>

    <?php /* NAV-01 §5-④ (update0006 B-03): البلاغاتُ المتصلة — كانت تُضمَّن
             **بعد** إغلاقِ الغلافِ فتظهر بجانبِ الشاشة. صارت قسمًا في محطّةِ
             المورد، فتاريخُ بلاغاتِه جزءٌ من ملفِّه لا ملحقٌ خارجَه. */
    $rt_kind = 'supplier'; $rt_ref = $supplier_id;
    include __DIR__ . '/../includes/related_tickets_tab.php'; ?>

    <?php echo ems_profile_group_close(); ?>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<!-- تهيئة الجدولين انتقلت إلى المكوّن المركزي (assets/js/ui-unification.js —
     initializeMissingDataTables): لغةٌ عربية وضبطُ أعمدةٍ وزرُّ إكسل موحَّد. -->
