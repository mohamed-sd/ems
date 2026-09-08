<?php
/**
 * 2028_06_08 — استعادةُ «إدارة الحركة والتشغيل» · الدور 5 · المساحة DEP-18
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الأمرُ**: الدورُ 5 — المكتوبُ عليه «إدارة الموقع (قديم — مدمج في 6)» —
 *   يُحوَّل إلى **«إدارة الحركة والتشغيل»** ويحمل **صفحاتِها الاثنتين
 *   والعشرين** كما كانت قبلَ هجرةِ الهيكلةِ `2026_11_04_org_v4_restructure`.
 *
 * ◆ **ومصدرُ القائمةِ قياسٌ لا ذاكرة**: هي صفوفُ `nav_items` للدورِ 6 التي
 *   `created_at <= 2026-08-03` — أي القائمةُ بعدَ دمجِ NAV-10 وقبلَ أن تحقن
 *   الحملاتُ اللاحقةُ 94 صفًّا آخر. وكلُّ ملفّاتِها **موجودةٌ على القرص**.
 *
 * ⛔ **ولا يُمَسُّ الدورُ 6 ولا DEP-12**: أحدَ عشرَ مستخدمًا يعملون عليها،
 *   وتسعةَ عشرَ موضعًا حاكمًا تبقى حرفًا. القيدُ `uq_one_primary` يمنع دورًا
 *   من مساحتين، فالمساحةُ الجديدةُ للدورِ 5 وحدَه.
 *
 * ◆ **والتجميعُ تأليفٌ مُعلَن**: مجموعاتُ الإدارةِ الأصليّةَ بعثرتها إعادةُ
 *   توزيعِ `NAV-12` (‏18 مجموعةً لـ22 بندًا بترتيبٍ متضارب)، فأُلِّفت دورةُ
 *   حياةٍ من ثمانِ مجموعاتٍ على نمطِ بقيّةِ الإدارات. التسمياتُ والأيقوناتُ
 *   والمسارات **منقولةٌ حرفًا** من صفوفِ الدورِ 6 — المؤلَّفُ التجميعُ وحدَه.
 *
 * ◆ **والسجلّاتُ التي يقرؤها المُصيِّرُ الحاكم** (`navarch_render`):
 *     `nav_workspace_placements` (‏`placement_type='PRIMARY'` · `status='ACTIVE'`)
 *   ⋈ `nav_lifecycle_groups` ⋈ `nav_ws_roles`(‏PRIMARY) ∩ `navarch_authorized_routes`
 *   والأخيرةُ من `nav_items` ⋈ `role_permissions` (‏أو قالبِ المستخدم).
 *   وتُكتب `nav_placements` + `nav_targets` كذلك — قارئُ `unified_nav` الآخر.
 *
 * ⚠ **مسارُ الموضعِ مُسوًّى**: صغيرٌ وبلا `.php` في `nav_workspace_placements`،
 *   وصغيرٌ **مع** `.php` في `nav_placements` — وهو عُرفُ الجدولين المقيسُ من
 *   `operations/site_register`. ومعاملُ الاستعلامِ (`?focus=`) يسقط بالتسوية.
 *
 * ◆ **مُعاوَدة**: كلُّ خطوةٍ تفحص حالتَها قبلَ الفعل — تشغيلٌ ثانٍ لا يكرِّر.
 * التشغيل: php database/migrate.php up
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                   ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "اتصال المرحِّل فشل\n"); exit(1); }
$conn->set_charset('utf8mb4');
$log = function ($m) { echo "  $m\n"; };

const WS   = 'DEP-18';
const ROLE = 5;
const SRC  = 'استعادةُ إدارة الحركة والتشغيل · 2028_06_08 · قائمةُ الدور 6 حتى 2026-08-03';

/* ═══ دورةُ الحياةِ المؤلَّفة — ثمانِ مجموعات ═══════════════════════════════ */
$GROUPS = array(
    1 => array('key' => 'اللوحه — خارج الدوره',  'label' => 'اللوحة — خارج الدورة'),
    2 => array('key' => 'التعاقد والنطاق',        'label' => 'التعاقد ونطاق التشغيل'),
    3 => array('key' => 'الموارد والتكليف',       'label' => 'الموارد والتكليف'),
    4 => array('key' => 'التشغيل اليومي',         'label' => 'التشغيل اليومي'),
    5 => array('key' => 'الاعتماد والمتابعه',     'label' => 'الاعتماد والمتابعة'),
    6 => array('key' => 'الطلبات والمال',         'label' => 'الطلبات والمال'),
    7 => array('key' => 'التقارير والتحليلات',    'label' => 'التقارير والتحليلات'),
    8 => array('key' => 'الاعدادات ومساحه العمل', 'label' => 'الإعدادات ومساحة العمل'),
);

/* ═══ الاثنتان والعشرون — منقولةٌ حرفًا من صفوفِ الدورِ 6 الأصليّة ═════════
   g=المجموعة · o=ترتيبُها داخلَها · نوعُ الموضعِ LANDING_PAGE للوحةِ وحدَها */
$ITEMS = array(
 array('g'=>1,'o'=>1,'route'=>'movement/map_page.php',              'label'=>'خريطة التشغيل اليومية',           'icon'=>'fas fa-map-marked-alt','mod'=>34, 'perm'=>'movement/map_page.php',              'door'=>'DAILY','quick'=>1,'cnt'=>null,'land'=>1),
 array('g'=>2,'o'=>1,'route'=>'Contracts/contract_sites.php',        'label'=>'نطاقات العقد التشغيلية',          'icon'=>'fa fa-map-location-dot','mod'=>172,'perm'=>'Contracts/contract_sites.php',        'door'=>'DAILY','quick'=>0,'cnt'=>null,'land'=>0),
 array('g'=>2,'o'=>2,'route'=>'Contracts/contract_monthly_plan.php', 'label'=>'الجدول الشهري للعقد',             'icon'=>'fa fa-calendar-days','mod'=>174,'perm'=>'Contracts/contract_monthly_plan.php', 'door'=>'DAILY','quick'=>0,'cnt'=>null,'land'=>0),
 array('g'=>2,'o'=>3,'route'=>'Contracts/contract_resource_plan.php','label'=>'خطة موارد العقد',                 'icon'=>'fa fa-truck-ramp-box','mod'=>175,'perm'=>'Contracts/contract_resource_plan.php','door'=>'DAILY','quick'=>0,'cnt'=>null,'land'=>0),
 array('g'=>2,'o'=>4,'route'=>'Operations/containers.php',           'label'=>'حاويات العقود',                   'icon'=>'fa fa-boxes-stacked','mod'=>149,'perm'=>'Operations/containers.php',           'door'=>'REC',  'quick'=>0,'cnt'=>null,'land'=>0),
 array('g'=>2,'o'=>5,'route'=>'Projects/sites.php',                  'label'=>'مواقع التنفيذ',                   'icon'=>'fa fa-map-location-dot','mod'=>150,'perm'=>'Projects/sites.php',                'door'=>'DAILY','quick'=>0,'cnt'=>null,'land'=>0),
 array('g'=>3,'o'=>1,'route'=>'main/project_users.php',              'label'=>'إدارة المعاونين',                 'icon'=>'fa fa-users-gear','mod'=>14, 'perm'=>'main/project_users.php',              'door'=>'REC',  'quick'=>1,'cnt'=>null,'land'=>0),
 array('g'=>3,'o'=>2,'route'=>'main/org_assignments.php',            'label'=>'التكليفات التنظيمية',             'icon'=>'fa fa-id-badge','mod'=>216,'perm'=>'admin/org_assignments.php',            'door'=>'DAILY','quick'=>0,'cnt'=>null,'land'=>0),
 array('g'=>4,'o'=>1,'route'=>'movement/movement_operations.php',    'label'=>'الورديات',                        'icon'=>'fa fa-tractor','mod'=>30, 'perm'=>'movement/movement_operations.php',    'door'=>'DAILY','quick'=>1,'cnt'=>null,'land'=>0),
 array('g'=>4,'o'=>2,'route'=>'Timesheet/timesheet_type.php',        'label'=>'حوكمة تسجيل التايم شيت والإنتاج', 'icon'=>'fa fa-business-time','mod'=>10, 'perm'=>'Timesheet/timesheet_type.php',        'door'=>'DAILY','quick'=>0,'cnt'=>null,'land'=>0),
 array('g'=>5,'o'=>1,'route'=>'Approvals/hours_approval.php',        'label'=>'اعتماد الوحدات',                  'icon'=>'fa fa-check-double','mod'=>247,'perm'=>'Approvals/hours_approval.php',        'door'=>'DAILY','quick'=>0,'cnt'=>null,'land'=>0),
 array('g'=>5,'o'=>2,'route'=>'Reports/exceptions_report.php',       'label'=>'التوقفات بلا مسؤول',              'icon'=>'fa fa-link','mod'=>203,'perm'=>null,                                  'door'=>'REC',  'quick'=>0,'cnt'=>null,'land'=>0),
 array('g'=>5,'o'=>3,'route'=>'FinRequests/dept_inbox.php',          'label'=>'موافقات إدارتي',                  'icon'=>'fa fa-inbox','mod'=>116,'perm'=>'FinRequests/dept_inbox.php',          'door'=>'APPR', 'quick'=>0,'cnt'=>'finreq_dept_inbox','land'=>0),
 array('g'=>6,'o'=>1,'route'=>'FinRequests/request_form.php',        'label'=>'الطلبات المقدَّمة',               'icon'=>'fa fa-paper-plane','mod'=>114,'perm'=>'FinRequests/request_form.php',        'door'=>'DAILY','quick'=>0,'cnt'=>null,'land'=>0),
 array('g'=>6,'o'=>2,'route'=>'Finance/events_list_fin.php',         'label'=>'سجل الأحداث المالية',             'icon'=>'fa fa-file-invoice-dollar','mod'=>86, 'perm'=>'Finance/events_list_fin.php',         'door'=>'DAILY','quick'=>0,'cnt'=>null,'land'=>0),
 array('g'=>6,'o'=>3,'route'=>'Finance/unit_records_fin.php',        'label'=>'أحكام العميل والمورد والمشغل',    'icon'=>'fa fa-cubes','mod'=>105,'perm'=>'Finance/unit_records_fin.php',        'door'=>'DAILY','quick'=>0,'cnt'=>null,'land'=>0),
 array('g'=>6,'o'=>4,'route'=>'Finance/budget_form_fin.php',         'label'=>'ميزانية الإدارة المالية',         'icon'=>'fa fa-chart-pie','mod'=>89, 'perm'=>'Finance/budget_form_fin.php',         'door'=>'DAILY','quick'=>0,'cnt'=>null,'land'=>0),
 array('g'=>6,'o'=>5,'route'=>'Finance/cost_report_fin.php',         'label'=>'التكاليف والربحية بالمركز',       'icon'=>'fa fa-coins','mod'=>92, 'perm'=>'Finance/cost_report_fin.php',         'door'=>'DAILY','quick'=>0,'cnt'=>null,'land'=>0),
 array('g'=>7,'o'=>1,'route'=>'Reports/reports.php',                 'label'=>'مركز التقارير التنفيذية',         'icon'=>'fas fa-chart-pie','mod'=>4,  'perm'=>'Reports/reports.php',                 'door'=>'DAILY','quick'=>0,'cnt'=>null,'land'=>0),
 array('g'=>7,'o'=>2,'route'=>'main/role_board.php',                 'label'=>'التقارير والتحليلات الشخصية',     'icon'=>'fa fa-house','mod'=>139,'perm'=>'main/role_board.php',                 'door'=>'DAILY','quick'=>0,'cnt'=>null,'land'=>0),
 array('g'=>8,'o'=>1,'route'=>'Settings/settings.php',               'label'=>'الإعدادات',                       'icon'=>'fa fa-cog','mod'=>11, 'perm'=>'Settings/settings.php',               'door'=>'DAILY','quick'=>0,'cnt'=>null,'land'=>0),
 array('g'=>8,'o'=>2,'route'=>'main/my_workspace.php',               'label'=>'مساحة عملي',                      'icon'=>'fa fa-user-circle','mod'=>228,'perm'=>'main/my_workspace.php',               'door'=>'HOME', 'quick'=>0,'cnt'=>null,'land'=>0),
);

/* مسارُ الموضعِ المُسوَّى — عُرفُ الجدولين المقيسُ من operations/site_register */
$norm = function ($r) { return strtolower(preg_replace('~\.php$~i', '', preg_replace('~[?#].*$~', '', $r))); };

$q = function ($sql) use ($conn) {
    if (!$conn->query($sql)) { fwrite(STDERR, "فشل: {$conn->error}\n  {$sql}\n"); exit(1); }
    return $conn->affected_rows;
};
$one = function ($sql) use ($conn) { $r = $conn->query($sql); $x = $r ? $r->fetch_row() : null; return $x ? $x[0] : null; };
$esc = function ($v) use ($conn) { return $v === null ? 'NULL' : "'" . $conn->real_escape_string($v) . "'"; };

echo "── استعادةُ «إدارة الحركة والتشغيل» ──\n";

/* ═══ S1 · اسمُ الدور ═════════════════════════════════════════════════════ */
$n = $q("UPDATE roles SET name='إدارة الحركة والتشغيل'
          WHERE id=" . ROLE . " AND name<>'إدارة الحركة والتشغيل'");
$log("S1 اسمُ الدور 5: {$n}");

/* ⛔ حارسٌ: لا تُنتزَع مساحةٌ من دورٍ يعمل عليها مستخدمون */
$busy = (int) $one("SELECT COUNT(*) FROM users WHERE role=" . ROLE);
if ($busy > 0) { fwrite(STDERR, "الدورُ 5 عليه {$busy} مستخدمًا — أوقفتُ الهجرةَ\n"); exit(1); }

/* ═══ S2 · المساحة ════════════════════════════════════════════════════════ */
if (!$one("SELECT workspace_id FROM nav_workspaces WHERE workspace_id='" . WS . "'")) {
    $q("INSERT INTO nav_workspaces
        (workspace_id, kind, name_ar, dept_code, ruling, source_ref, active,
         workspace_code, canonical_name, workspace_type, owner_domain, governing_source, version)
        VALUES ('" . WS . "','DEPARTMENT','إدارة الحركة والتشغيل','" . WS . "',
         'إدارةٌ مستعادةٌ بأمرِ المالك — قائمتُها المقيسةُ من الدورِ 6 قبلَ هجرةِ org_v4',
         " . $esc(SRC) . ",1,'" . WS . "','إدارة الحركة والتشغيل','DEPARTMENT','" . WS . "',
         'استعادةُ 2028_06_08 · nav_workspaces@" . WS . "',1)");
    $log('S2 المساحة ' . WS . ' أُنشئت');
} else { $log('S2 المساحة قائمة'); }

/* ═══ S3 · ربطُ الدور — PRIMARY هنا، ونزعُ SECONDARY المتقادم من DEP-12 ══ */
if (!$one("SELECT role_id FROM nav_ws_roles WHERE workspace_id='" . WS . "' AND role_id=" . ROLE)) {
    $q("INSERT INTO nav_ws_roles (workspace_id, role_id, binding, source_ref, parent_role_id, ruling)
        VALUES ('" . WS . "'," . ROLE . ",'PRIMARY'," . $esc(SRC) . ",NULL,
        'الدورُ يقود المساحةَ " . WS . " — استعادةُ إدارةِ الحركةِ والتشغيلِ بأمرِ المالك · PRIMARY واحدةٌ (uq_one_primary)')");
    $log('S3 ربطُ PRIMARY أُنشئ');
} else { $log('S3 الربطُ قائم'); }
/* حكمُ الصفِّ القديمِ نصَّ على «مدمج في 6» — وقد بطل، فيُنزَع الصفُّ لا يُترَك كاذبًا */
$n = $q("DELETE FROM nav_ws_roles WHERE workspace_id='DEP-12' AND role_id=" . ROLE . " AND binding='SECONDARY'");
$log("S3 نزعُ SECONDARY المتقادم من DEP-12: {$n}");

/* ═══ S4 · مجموعاتُ دورةِ الحياة ═════════════════════════════════════════ */
$gid = array();
foreach ($GROUPS as $sort => $G) {
    $have = $one("SELECT id FROM nav_lifecycle_groups
                   WHERE workspace_id='" . WS . "' AND group_key=" . $esc($G['key']));
    if (!$have) {
        $q("INSERT INTO nav_lifecycle_groups (workspace_id, group_key, label_ar, sort_no, source_ref, active)
            VALUES ('" . WS . "'," . $esc($G['key']) . "," . $esc($G['label']) . ",{$sort}," . $esc(SRC) . ",1)");
        $have = $conn->insert_id;
    }
    $gid[$sort] = (int) $have;
}
$log('S4 مجموعاتُ الدورة: ' . count($gid));

/* ═══ S5 · مجموعاتُ القائمةِ الإرثيّةِ للدور 5 (‏link_groups) ═════════════ */
$lg = array();
foreach ($GROUPS as $sort => $G) {
    $have = $one("SELECT id FROM link_groups
                   WHERE owner_role_id=" . ROLE . " AND name=" . $esc($G['label']));
    if (!$have) {
        $q("INSERT INTO link_groups (name, group_code, owner_role_id, icon, display_order, is_active)
            VALUES (" . $esc($G['label']) . ",NULL," . ROLE . ",'fa fa-folder'," . ($sort * 100) . ",1)");
        $have = $conn->insert_id;
    } else {
        $q("UPDATE link_groups SET is_active=1, display_order=" . ($sort * 100) . " WHERE id={$have}");
    }
    $lg[$sort] = (int) $have;
}
$log('S5 مجموعاتُ link_groups: ' . count($lg));

/* ═══ S6..S9 · الأهدافُ والمواضعُ وصفوفُ التفويض ══════════════════════════ */
$cT = $cP = $cW = $cN = $cR = 0;
$row = 0;
foreach ($ITEMS as $it) {
    $row++;
    $G      = $GROUPS[$it['g']];
    $target = sprintf('NT-%s-%03d', WS, $row);
    $nrm    = $norm($it['route']);                   /* صغيرٌ بلا لاحقة  */
    $lower  = strtolower($it['route']);              /* صغيرٌ مع لاحقة   */
    $type   = $it['land'] ? 'LANDING_PAGE' : 'MENU_ITEM';

    /* S6 · nav_targets */
    if (!$one("SELECT target_id FROM nav_targets WHERE target_id=" . $esc($target))) {
        $q("INSERT INTO nav_targets
            (target_id, source_doc, sheet_code, row_no, canonical_title, workspace_id,
             group_key, target_order, visibility_class, active)
            VALUES (" . $esc($target) . "," . $esc(SRC) . ",'" . WS . "',{$row},"
            . $esc($it['label']) . ",'" . WS . "'," . $esc($G['key']) . ",{$row},'BUILT',1)");
        $cT++;
    }

    /* S7 · nav_placements — قارئُ unified_nav */
    if (!$one("SELECT id FROM nav_placements WHERE workspace_id='" . WS . "' AND route=" . $esc($lower))) {
        $q("INSERT INTO nav_placements
            (workspace_id, screen_id, route, target_ref, target_id, group_id, sort_no,
             placement_type, source_ref, active)
            VALUES ('" . WS . "',NULL," . $esc($lower) . ","
            . $esc(WS . '·' . $it['g'] . '·' . $it['label']) . "," . $esc($target) . ","
            . $gid[$it['g']] . "," . $it['o'] . "," . $esc($type) . "," . $esc(SRC) . ",1)");
        $cP++;
    }

    /* S8 · nav_workspace_placements — السجلُّ الذي يقرؤه المُصيِّرُ الحاكم.
       ⚠ placement_type='PRIMARY' هنا صنفُ ظهورٍ لا صنفُ بند (‏§9). */
    if (!$one("SELECT placement_id FROM nav_workspace_placements
                WHERE workspace_id='" . WS . "' AND route=" . $esc($nrm))) {
        $pid = 'WP-' . strtoupper(substr(md5(WS . '|' . $nrm), 0, 16));
        $q("INSERT INTO nav_workspace_placements
            (placement_id, screen_id, workspace_id, group_id, placement_type, sort_no, route,
             canonical_label, governing_source, source_ref, reason_code, effective_from,
             effective_to, status, version, created_by, approved_by, legacy_ref)
            VALUES (" . $esc($pid) . ",NULL,'" . WS . "'," . $gid[$it['g']] . ",'PRIMARY',"
            . $it['o'] . "," . $esc($nrm) . "," . $esc($it['label']) . ",
            'استعادةُ إدارةِ الحركةِ والتشغيلِ — الشاشةُ جزءٌ أصيلٌ من دورةِ المساحة',"
            . $esc(SRC) . ",'OWNER_RESTORE_2028_06_08',CURDATE(),NULL,'ACTIVE',1,
            'database/migrations/2028_06_08_dep18_movement_ops_restore.php',NULL,NULL)");
        $cW++;
    }

    /* S9 · nav_items للدور 5 — **مصدرُ تفويضٍ لا مصدرُ ظهور** */
    if (!$one("SELECT id FROM nav_items WHERE role_id=" . ROLE . " AND route=" . $esc($it['route']))) {
        $q("INSERT INTO nav_items
            (role_id, door, group_id, module_id, label_ar, route, icon, sort_order,
             counter_source, permission_code, active, is_quick, created_at, updated_at)
            VALUES (" . ROLE . "," . $esc($it['door']) . "," . $lg[$it['g']] . ","
            . ($it['mod'] === null ? 'NULL' : (int) $it['mod']) . "," . $esc($it['label']) . ","
            . $esc($it['route']) . "," . $esc($it['icon']) . "," . ($it['o'] * 10) . ","
            . $esc($it['cnt']) . "," . $esc($it['perm']) . ",1," . (int) $it['quick'] . ",NOW(),NOW())");
        $cN++;
    } else {
        $q("UPDATE nav_items SET active=1, group_id=" . $lg[$it['g']] . ", sort_order=" . ($it['o'] * 10)
            . " WHERE role_id=" . ROLE . " AND route=" . $esc($it['route']));
    }

    /* S10 · role_permissions — الرؤيةُ للدورِ على وحدةِ الشاشة */
    if ($it['mod'] !== null) {
        $m = (int) $it['mod'];
        if (!$one("SELECT id FROM role_permissions WHERE role_id=" . ROLE . " AND module_id={$m}")) {
            $q("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                VALUES (" . ROLE . ",{$m},1,0,0,0)");
            $cR++;
        } else {
            $q("UPDATE role_permissions SET can_view=1 WHERE role_id=" . ROLE . " AND module_id={$m} AND can_view<>1");
        }
    }
}
$log("S6 أهداف: {$cT} · S7 مواضع: {$cP} · S8 مواضعُ المساحة: {$cW} · S9 بنودُ تفويض: {$cN} · S10 منحٌ جديد: {$cR}");

/* ═══ S11 · حرفُ المسارِ المعياريُّ — سدُّ ثغرةِ حساسيّةِ الحالة ═══════════
   ⛔ **العطبُ المقيس**: `navarch_proper_route` تأخذ حرفَ المسارِ من
     `nav_canonical`، وما لا صفَّ له ترجعه **بالحرفِ الصغيرِ المُسوَّى** بعدَ
     فحصِ وجودِ الملفّ. وويندوز يقبل `reports/exceptions_report.php` بينما
     **لينكس (‏Hostinger) لا يقبله** — والمجلَّدُ `Reports/`. فالرابطُ يعمل
     محليًّا ويسقط 404 على الخادم.
   ◆ و`Reports/exceptions_report.php` **غائبةٌ عن السجلِّ** وحدَها من بين
     أشقّائها (`approval_lag_report` · `daily_units_report` · `margin_report`
     · `reports` كلُّها مسجَّلة) — ثغرةٌ سابقةٌ كشفها الموضعُ لا أحدثها.
   ⚠ **ولا يُصطنع حكمُ مالكٍ**: `status='PENDING_OWNER'` و`space_class=''`
     حالتانِ قائمتانِ في السجلِّ (294 صفًّا بـ`space_class` فارغ) — تسجيلُ
     حرفٍ لا اعتمادُ موضع. */
$hasCanon = $one("SELECT id FROM nav_canonical WHERE LOWER(route)='reports/exceptions_report.php'");
if (!$hasCanon) {
    $sid = $one("SELECT CONCAT('SCR-', LPAD(MAX(CAST(SUBSTRING(screen_id,5) AS UNSIGNED))+1,4,'0'))
                   FROM nav_canonical WHERE screen_id LIKE 'SCR-%'");
    $q("INSERT INTO nav_canonical
        (route, canonical_ar, canonical_en, level_no, level_name, group_name, sort_no,
         owner_dept, status, decision_state, application_state, decision_source,
         policy_domain, current_label, placement_kind, space_class, screen_id)
        VALUES ('Reports/exceptions_report.php','تقرير الاستثناءات','Exceptions Report',
         4,'التقارير والتحليلات','التقارير والتحليلات',999,
         'إدارة الحركة والتشغيل','PENDING_OWNER','PENDING_OWNER','CURRENT',
         'استعادةُ 2028_06_08 — تسجيلُ حرفِ المسارِ المعياريِّ لشاشةٍ قائمةٍ غيرِ مسجَّلة',
         'NAVIGATION_NAMING_POSITION','التوقفات بلا مسؤول','SINGLE','', " . $esc($sid) . ")");
    $log('S11 حرفُ nav_canonical سُجِّل: ' . $sid);
} else { $log('S11 حرفُ nav_canonical قائم'); }

/* ═══ S12 · أحكامُ ما لم يُستعَد — تُنقَل ولا تُخترَع ══════════════════════
   ◆ **السؤالُ الذي تطرحه بوّابةُ «صفرِ الفقد»**: ثلاثةُ مساراتٍ كانت في
     القائمةِ الورقيّةِ للدورِ 5 (‏وهو **صفرُ مستخدمٍ وصفرُ رابطٍ حيّ** — نصُّ
     حكمِ ربطِه القديم) وليست من الاثنتين والعشرين، فتسأل البوّابةُ عن حكمِها
     **في مساحةِ هذا الدورِ بعينِها** (`current_workspace = DEP-18`).
   ◆ **ولها أحكامٌ مسجَّلةٌ سلفًا** في مساحاتِ مالكيها — فالنقصُ **في مدى
     الحكمِ لا في الحكمِ نفسِه**، وهي عينُ سابقةِ `2028_06_07`.
   ⛔ **فتُنقَل حرفًا**: `disposition` و`action` و`reason` و`evidence` و
     `decided_level` و`domain_owner` كما هي — والمُعدَّلُ **المساحةُ وبديلُ
     الوصولِ وحدَهما**، لأنَّ «سايدبارُ هذه المساحة» يصدق على DEP-11 ولا يصدق
     على DEP-18. ولا يُخترَع حكمٌ لم يقلْه مالكُ المجال. */
$cD = 0;
foreach (array(
    'operations/operations_room'    => 'DEP-11',
    'operations/distribution_space' => 'DEP-11',
    'operations/daily_plan'         => 'DEP-12',
) as $rt => $src) {
    if ($one("SELECT legacy_item_id FROM nav_legacy_disposition
               WHERE current_workspace='" . WS . "' AND current_route=" . $esc($rt))) { continue; }
    $lid = 'LG-D18' . strtoupper(substr(md5(WS . '|' . $rt), 0, 13));
    $q("INSERT INTO nav_legacy_disposition
        (legacy_item_id, screen_id, current_workspace, current_label, current_route, usage_count,
         target_match, replacement_screen_id, disposition, action, reason, domain_owner,
         decision_ref, effective_date, retirement_date, evidence, access_replacement,
         decided_level, retire_stage, created_at)
        SELECT " . $esc($lid) . ", screen_id, '" . WS . "', current_label, current_route, usage_count,
               target_match, replacement_screen_id, disposition, action, reason, domain_owner,
               decision_ref, effective_date, retirement_date, evidence,
               'مبدِّلُ المساحاتِ إلى إدارةِ المجالِ المالكةِ · والرابطُ المباشرُ نافذٌ لمن يملك الصلاحيّة — الحكمُ منقولٌ بمداه من "
        . $conn->real_escape_string($src) . "',
               decided_level, retire_stage, NOW()
          FROM nav_legacy_disposition
         WHERE current_workspace=" . $esc($src) . " AND current_route=" . $esc($rt) . " LIMIT 1");
    $cD++;
}
$log("S12 أحكامٌ نُقل مداها إلى " . WS . ": {$cD}");

/* ═══ S13 · الإدارةُ المالكةُ لشاشةِ الاستثناءات — سقّاطةُ RP-02 ═══════════
   ◆ `RP-02` تقيس «مسارَ تنقّلٍ حيٍّ بلا إدارةٍ مالكة» من `gov_screen_cycle`
     **باسمِ الملفِّ لا بمسارِه**. و`exceptions_report.php` صار حيًّا بهذه
     الاستعادةِ ولا صفَّ له — فتزيد السقّاطةُ واحدًا.
   ◆ **والمرحلةُ من مفرداتٍ قائمةٍ لا من خيال**: «الانحراف والاستثناء» مرحلةٌ
     مسجَّلةٌ في الجدولِ نفسِه · و`bridge_rule='C5_AUTHORED'` من مفرداتِ
     `chk_cyc_bridge` المغلقة. */
if (!$one("SELECT id FROM gov_screen_cycle WHERE screen_file='exceptions_report.php'")) {
    $q("INSERT INTO gov_screen_cycle
        (company_id, dept_name, layer_name, stage_order, stage_name, group_name, screen_title,
         screen_file, inputs_note, output_doc, resp_role, next_state, consumers, fin_impact,
         stage_kind, screen_id, bridge_rule, bridge_witness, bridge_snapshot)
        VALUES (0,'إدارة الحركة والتشغيل','دورة الإدارة','5','الانحراف والاستثناء',
         'الاعتماد والمتابعة','تقرير الاستثناءات (exceptions_report.php)',
         'exceptions_report.php','stops · timesheet','',
         'إدارة الحركة والتشغيل','قرارُ تحميلِ التوقُّفِ على متحمِّلِه','الإدارة المعنية · المالية','لا',
         'canonical','SCR-0923','C5_AUTHORED',
         'C5 · شاشةُ استثناءاتٍ حيّةٌ بلا إدارةٍ مالكةٍ كشفها موضعُ DEP-18 — والمرحلةُ من مفرداتِ الجدولِ القائمة · استعادةُ 2028_06_08',
         'RESTORE-2028-06-08')");
    $log('S13 سجلُّ الإدارةِ المالكةِ لـexceptions_report أُنشئ');
} else { $log('S13 سجلُّ الإدارةِ المالكةِ قائم'); }

/* ═══ S14 · تحقُّقٌ ذاتيّ — الصفُّ ليس ظهورًا ═════════════════════════════ */
$built = (int) $one("SELECT COUNT(*) FROM nav_workspace_placements
                      WHERE workspace_id='" . WS . "' AND status='ACTIVE'");
$grpOk = (int) $one("SELECT COUNT(*) FROM nav_workspace_placements p
                      JOIN nav_lifecycle_groups g ON g.id=p.group_id AND g.active=1
                     WHERE p.workspace_id='" . WS . "' AND p.status='ACTIVE'");
$authd = (int) $one("SELECT COUNT(*) FROM nav_items n
                      LEFT JOIN role_permissions rp ON rp.role_id=n.role_id AND rp.module_id=n.module_id
                     WHERE n.role_id=" . ROLE . " AND n.active=1
                       AND (n.permission_code IS NULL OR rp.can_view=1)");
$log("تحقُّق: مواضعُ نشطة={$built} · منها بمجموعةٍ حيّة={$grpOk} · بنودٌ مُصرَّحٌ بها={$authd}");
if ($built !== 22 || $grpOk !== 22) { fwrite(STDERR, "عددُ المواضعِ ليس 22 — راجِعْ\n"); exit(1); }

echo "اكتملت استعادةُ إدارةِ الحركةِ والتشغيلِ (" . WS . " · الدور " . ROLE . ")\n";
exit(0);
