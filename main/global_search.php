<?php
/**
 * main/global_search.php — البحثُ الموحَّد (NAV-01 v6 §13-⑤)
 * ───────────────────────────────────────────────────────────────────────────
 * «بحثٌ موحَّدٌ واحدٌ يجد الكيانَ أيًّا كان نوعُه بالكود أو الاسم —
 * فلا يُسأل المتدربُ عن الشاشة.» يبحث تسعةَ كياناتٍ ويصل كلَّ نتيجةٍ بملفّها.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/screen_contract.php';
/* عزلُ الإدارات — حارسُ المساحةِ يُحمَّل هنا ليُفحَص قبلَ كلِّ استعلامِ كيان */
require_once __DIR__ . '/../includes/space_scope.php';

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$q = mb_substr(trim($_GET['q'] ?? ''), 0, 80);
$eq = mysqli_real_escape_string($conn, $q);
$like = "'%$eq%'";

// عددٌ صرفٌ في الاستعلام؟ فالبحثُ «بالكود» يشمل المعرّفَ الرقمي أيضًا (§13-⑤).
$q_is_num = ($q !== '' && ctype_digit($q));
$or_id   = $q_is_num ? ' OR id = ' . intval($q) : '';
$or_opid = $q_is_num ? ' OR op_id = ' . intval($q) : '';

/**
 * الكياناتُ التسعة — لكلٍّ تسميتُه وأيقونتُه **وشاشةُ وجهته**.
 * الوجهةُ شاشةٌ قائمةٌ تعرض الكيانَ فعلًا (تحقَّق وجودُها ملفًّا وقبولُها للمعرّف):
 * بطاقتا الأمّ للعقد والموظف، وملفاتُ المعدة والمورد والعميل والمشروع وعملية
 * التمويل، وتذكرةُ البلاغ، وواقعةُ السجل القانوني في «وحدات الأطراف» برقمها.
 *
 * العنصرُ الثالث هو **شاشةُ الوجهة نفسُها** لا قائمتُها الأمّ: §13-③ «لا يُعرض
 * ما لا يُملك» تُقاس بما يستطيع فتحَه فعلًا — فالحكمُ على الوجهة يطابق حارسَ
 * التنفيذ حرفًا (`enforce_current_page_view_permission`)، والحكمُ على القائمة
 * الأمّ أشدُّ منه فيحجب نتائجَ يملكها المستخدم (قيست: البلاغاتُ معفاةٌ في
 * الحارس ومحجوبةٌ في القائمة).
 */
$entities = array(
    'equipments' => array('المعدات', 'fa-solid fa-tractor', 'Equipments/equipment_profile.php'),
    'employees'  => array('الموظفون', 'fa-solid fa-id-card', 'Employees/employee_card.php'),
    'suppliers'  => array('الموردون', 'fa-solid fa-truck-loading', 'Suppliers/supplier_profile.php'),
    'clients'    => array('العملاء', 'fa-solid fa-users', 'Clients/client_profile.php'),
    'projects'   => array('المشاريع', 'fa-solid fa-folder-open', 'Projects/project_profile.php'),
    'contracts'  => array('العقود', 'fa-solid fa-file-signature', 'Contracts/contract_card.php'),
    'tickets'    => array('البلاغات', 'fa-solid fa-bullhorn', 'Tickets/ticket_form.php'),
    'units'      => array('الوحدات (التايم شيت)', 'fa-solid fa-business-time', 'Finance/unit_records_fin.php'),
    'financing'  => array('عمليات التمويل', 'fa-solid fa-file-invoice-dollar', 'Financing/operation_profile.php'),
    // كيانا المشتريات (أُضيفا 2026-08-06 — كانت التسعةُ لا تشملها فمديرُ المشتريات
    // لا يجد صنفَه ولا أمرَه): الوجهةُ شاشةُ الكيان الحقيقية بحارسها الخادمي
    'proc_items'  => array('أصناف المشتريات', 'fa-solid fa-boxes-stacked', 'Procurement/items_proc.php'),
    'proc_orders' => array('أوامر الشراء', 'fa-solid fa-file-invoice-dollar', 'Procurement/orders_proc.php'),
);

/**
 * أيستطيع المستخدمُ فتحَ شاشةِ الوجهة؟ — مرآةُ حارسِ التنفيذ لا حكمٌ ثانٍ:
 * الشاشاتُ المعفاةُ فيه (البلاغاتُ كالمراسلات: «أيُّ مستخدمٍ مسجَّلٍ يصلها»)
 * معفاةٌ هنا، وما لا صفَّ له في modules يبقى مفتوحًا كما يبقى هناك.
 */
function gs_can_open($conn, $screen)
{
    static $exempt = array('Tickets/tickets_list.php', 'Tickets/ticket_form.php',
                           'Maintenance/breakdowns.php', 'main/dashboard.php');
    if (in_array($screen, $exempt, true)) { return true; }
    $p = check_page_permissions($conn, $screen);
    return ($p['id'] === null) || !empty($p['can_view']);
}

$sections = array();
$total_hits = 0;
$hidden_sections = 0;
if (mb_strlen($q) >= 2) {
    $is_super = (strval($_SESSION['user']['role'] ?? '') === '-1');
    $probe = function ($key, $sql, $urlFn) use ($conn, &$sections, &$total_hits, &$hidden_sections, $entities, $is_super) {
        // §13-③: الصلاحيةُ تُفحص قبل الاستعلام — فما لا يُملك لا يُصيَّر أصلًا
        // (ولا يُستعلَم عنه). السوبر خارج الفحص كسائر شاشات النظام.
        if (!$is_super && !gs_can_open($conn, $entities[$key][2])) { $hidden_sections++; return; }

        /* ══ عزلُ الإدارات (سابعًا-③) — البحثُ العامُّ قناةٌ من الثمان ══════════
           ◆ **الصلاحيةُ لا تكفي هنا**: من يملك الشاشةَ في إدارتِها المالكةِ يبلغها
             بتبديلِ المساحة، **ويُمنع منها في المساحةِ الأجنبية**. فبحثٌ يفحص
             الصلاحيةَ وحدَها يُعيد للمستخدمِ في مساحةِ التشغيلِ نتائجَ الشاشةِ
             الماليةِ الممنوعةِ عليه هنا — **ويصير البحثُ بابًا خلفيًّا لما أُزيل
             من السايدبار**.
           ◆ **والفحصُ قبلَ الاستعلامِ لا بعدَه**، على نسقِ سطرِ الصلاحيةِ أعلاه:
             فما يُمنع في هذه المساحةِ **لا يُستعلَم عنه أصلًا** ولا تمرُّ صفوفُه
             في الحمولة. */
        $__gsRoute = isset($entities[$key][3]) ? (string) $entities[$key][3] : '';
        if ($__gsRoute === '' && isset($entities[$key][2])) { $__gsRoute = (string) $entities[$key][2]; }
        if (!$is_super && $__gsRoute !== '' && function_exists('ems_scope_forbids')
            && ems_scope_forbids($__gsRoute)) {
            $hidden_sections++; return;
        }
        $r = mysqli_query($conn, $sql);
        // config.php يُطفئ رمي mysqli — فاستعلامٌ خاطئُ العمود يعود false صامتًا
        // ويبدو «لا نتيجة». يُعلَن العطبُ ولا يُصمت عليه (كان يخفي كيانين).
        if ($r === false) {
            $sections[] = array($entities[$key][0], $entities[$key][1], false, $urlFn);
            return;
        }
        $rows = array();
        while ($x = mysqli_fetch_assoc($r)) $rows[] = $x;
        if ($rows) {
            $sections[] = array($entities[$key][0], $entities[$key][1], $rows, $urlFn);
            $total_hits += count($rows);
        }
    };
    // الأعمدةُ أدناه مقيسةٌ على القاعدة الحية لا مفترضة: العميلُ `client_name`
    // و`client_code` (لا `name`)، والعقدُ بلا رقمٍ ولا اسمٍ أصلًا فيُعرَّف كما
    // تُعرِّفه بطاقتُه «عقد #id — الطرف الثاني». وكلُّ كيانٍ يُبحث **بكوده**
    // كما بالاسم (§13-⑤)، والمحذوفُ ناعمًا يُستبعد حيث للجدول عمودُ حذف.
    $probe('equipments', "SELECT id, name, code extra FROM equipments WHERE company_id = $company_id
            AND (name LIKE $like OR code LIKE $like$or_id) LIMIT 8",
        function ($x) { return '../Equipments/equipment_profile.php?id=' . intval($x['id']); });
    $probe('employees', "SELECT id, name, employee_code extra FROM employees WHERE company_id = $company_id
            AND (name LIKE $like OR employee_code LIKE $like$or_id) LIMIT 8",
        function ($x) { return '../Employees/employee_card.php?id=' . intval($x['id']); });
    $probe('suppliers', "SELECT id, name, supplier_code extra FROM suppliers WHERE company_id = $company_id
            AND COALESCE(is_deleted,0) = 0 AND (name LIKE $like OR supplier_code LIKE $like$or_id) LIMIT 8",
        function ($x) { return '../Suppliers/supplier_profile.php?id=' . intval($x['id']); });
    $probe('clients', "SELECT id, client_name name, client_code extra FROM clients WHERE company_id = $company_id
            AND COALESCE(is_deleted,0) = 0 AND (client_name LIKE $like OR client_code LIKE $like$or_id) LIMIT 8",
        function ($x) { return '../Clients/client_profile.php?id=' . intval($x['id']); });
    $probe('projects', "SELECT id, name, project_code extra FROM project WHERE company_id = $company_id
            AND COALESCE(is_deleted,0) = 0 AND (name LIKE $like OR project_code LIKE $like OR mine_code LIKE $like$or_id) LIMIT 8",
        function ($x) { return '../Projects/project_profile.php?id=' . intval($x['id']); });
    $probe('contracts', "SELECT id, CONCAT('عقد #', id, ' — ', COALESCE(second_party,'')) name,
                   contract_status extra FROM contracts WHERE company_id = $company_id
            AND COALESCE(is_deleted,0) = 0
            AND (second_party LIKE $like OR first_party LIKE $like$or_id) LIMIT 8",
        function ($x) { return '../Contracts/contract_card.php?id=' . intval($x['id']); });
    // البلاغُ محكومٌ بنطاقٍ **على مستوى الصف** لا الشاشة (tkt_can_view_ticket):
    // المُبلِّغُ ومنشئُه يريانه، وسواهما بحسب دورِ مالكِه. فلولا هذا الشرطُ لظهر
    // في النتائج بلاغٌ ينتهي نقرُه إلى «خارج نطاقك» — وهو عينُ ما يمنعه §13-③.
    $tk_where = '1=1';
    if (!$is_super) {
        require_once __DIR__ . '/../Tickets/tkt_helpers.php';
        $uid  = intval($_SESSION['user']['id'] ?? 0);
        $rid  = intval($_SESSION['user']['role'] ?? 0);
        if (strval($_SESSION['user']['role'] ?? '') !== EMS_ROLE_TICKETS_MGR) {
            $owners = array_map('intval', tkt_visible_owner_role_ids($rid));
            $tk_where = "(reporter_user_id = $uid OR created_by = $uid"
                      . ($owners ? " OR owner_role_id IN (" . implode(',', $owners) . ")" : '') . ")";
        }
    }
    $probe('tickets', "SELECT id, ticket_no name, stage extra FROM tickets WHERE company_id = $company_id
            AND $tk_where AND (ticket_no LIKE $like OR complaint LIKE $like$or_id) LIMIT 8",
        function ($x) { return '../Tickets/ticket_form.php?id=' . intval($x['id']); });
    // الواقعةُ تُفتح برقمها (UNT-…) في «وحدات الأطراف» — بطاقاتُ أحكامِ أطرافها
    // الثلاثة هي ملفُّها، ولا شاشةَ أخرى تعرض الواقعةَ مفردةً.
    $probe('units', "SELECT id, entry_no name, state extra FROM unit_entries WHERE company_id = $company_id
            AND (entry_no LIKE $like$or_id) ORDER BY entry_date DESC, id DESC LIMIT 8",
        function ($x) { return '../Finance/unit_records_fin.php?entry=' . rawurlencode($x['name']); });
    $probe('financing', "SELECT op_id id, op_code name, state extra FROM financing_operations
            WHERE company_id = $company_id AND (op_code LIKE $like$or_opid) LIMIT 8",
        function ($x) { return '../Financing/operation_profile.php?id=' . intval($x['id']); });
    $probe('proc_items', "SELECT id, name, code extra FROM proc_item WHERE company_id = $company_id
            AND COALESCE(is_deleted,0) = 0 AND (name LIKE $like OR code LIKE $like$or_id) LIMIT 8",
        function ($x) { return '../Procurement/items_proc.php'; });
    $probe('proc_orders', "SELECT id, CONCAT(code, ' — ', state) name, invoice_no extra FROM proc_order
            WHERE company_id = $company_id AND COALESCE(is_deleted,0) = 0
            AND (code LIKE $like OR invoice_no LIKE $like OR fin_approval_ref LIKE $like$or_id) LIMIT 8",
        function ($x) { return '../Procurement/orders_proc.php?edit_id=' . intval($x['id']); });
}

/** تمييزُ الجزء المطابق داخل النص (أول مطابقة، بلا حساسيةٍ لحالة الأحرف) — آمنُ الإخراج. */
function gs_highlight($text, $q)
{
    $text = (string) $text;
    if ($q === '' || $text === '') return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $pos = mb_stripos($text, $q, 0, 'UTF-8');
    if ($pos === false) return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $len = mb_strlen($q, 'UTF-8');
    return htmlspecialchars(mb_substr($text, 0, $pos, 'UTF-8'), ENT_QUOTES, 'UTF-8')
        . '<mark class="gs-hl">' . htmlspecialchars(mb_substr($text, $pos, $len, 'UTF-8'), ENT_QUOTES, 'UTF-8') . '</mark>'
        . htmlspecialchars(mb_substr($text, $pos + $len, null, 'UTF-8'), ENT_QUOTES, 'UTF-8');
}

$has_query = (mb_strlen($q) >= 2);
$page_title = 'إيكوبيشن | البحث الموحد';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell gs-page" dir="rtl">
<?php
$header_title = 'البحثُ الموحَّد';
$header_icon = 'fas fa-magnifying-glass';
$header_actions = array();
$header_back = array();
include '../includes/page_header.php';
ems_screen_about(
    'بحثٌ واحدٌ يجد الكيانَ أيًّا كان نوعُه — بالكود أو الاسم — في تسعة كيانات، '
    . 'ويصل كلَّ نتيجةٍ بملفّها مباشرةً فلا يُسأل أحدٌ عن الشاشة.',
    array('اكتب حرفين فأكثر: اسمًا أو كودًا (معدة · موظف · عقد · بلاغ · وحدة …)',
          'انقر أيَّ نتيجةٍ تفتح ملفَّ الكيان نفسِه'));
// UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
echo ems_states_bundle('لا كيانَ يطابق ما بحثتَ عنه', 'اكتب حرفين فأكثر — كودًا أو اسمًا — ثم اضغط «ابحث»');
?>
<style>
/* ═══ البحث الموحّد — أنماطٌ خاصةٌ بالشاشة (الهوية من design-tokens) ═══ */
.gs-page .gs-hero {
  background: var(--white);
  border: var(--border-subtle);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  padding: 26px 20px 22px;
  text-align: center;
}
.gs-page .gs-input-wrap {
  display: flex;
  gap: 10px;
  max-width: 680px;
  margin: 0 auto;
}
.gs-page .gs-input-box { position: relative; flex: 1; }
.gs-page .gs-input-box > i {
  position: absolute;
  inset-inline-start: 16px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--gray-500);
  font-size: 16px;
  pointer-events: none;
}
/* الحقلُ والزرُّ يرثان طبقةَ الضوابط الموحّدة في ems.main.all.style.css
   (الحدُّ والاستدارةُ وحلقةُ التركيز الذهبية وتدرّجُ الزرِّ الذهبيِّ عبر
   `search-btn`) — فلا تُعاد كتابةُ الهوية هنا. وتلك الطبقةُ كلُّها `!important`،
   فما يخصُّ حجمَ حقل البحث الرئيس وحدَه يُثقَّل بجذر الصفحة + `!important`. */
body.ems-site .main.gs-page .gs-input {
  width: 100%;
  height: 52px !important;
  min-height: 52px !important;
  /* حشوٌ متساوٍ على الجانبين كي يقع النصُّ المتوسِّط في منتصف الحقل حقًّا
     لا مزاحًا بمقدار موضع الأيقونة. */
  padding-inline-start: 44px !important;
  padding-inline-end: 44px !important;
  text-align: center !important;
  font-size: 16px !important;
}
body.ems-site .main.gs-page .gs-btn {
  height: 52px !important;
  min-height: 52px !important;
  padding: 0 30px !important;
  font-size: 15px !important;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}
.gs-page .gs-chips {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 8px;
  margin-top: 16px;
}
.gs-page .gs-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  border-radius: var(--radius-pill);
  background: var(--gray-100);
  border: 1px solid var(--gray-200);
  color: var(--gray-700);
  font-size: var(--text-caption);
  font-weight: var(--weight-semi);
}
.gs-page .gs-chip i { color: var(--brand-orange-bright); font-size: 12px; }
.gs-page .gs-hint { margin-top: 12px; color: var(--gray-500); font-size: var(--text-caption); }

/* ─── شريط ملخّص النتائج ─── */
.gs-page .gs-summary {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  background: var(--brand-cream-light);
  border: 1px solid var(--brand-cream);
  border-inline-start: 4px solid var(--brand-amber);
  border-radius: var(--radius-md);
  color: var(--gray-900);
  font-size: var(--text-body);
}
.gs-page .gs-summary i { color: var(--brand-orange-bright); }
.gs-page .gs-summary b { color: var(--warning-deep); }

.gs-page .gs-note {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 9px 14px;
  border: 1px dashed var(--gray-300);
  border-radius: var(--radius-md);
  background: var(--gray-50);
  color: var(--gray-700);
  font-size: var(--text-caption);
}
.gs-page .gs-note i { color: var(--gray-500); }

/* ─── شبكة بطاقات الأقسام ─── */
.gs-page .gs-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(330px, 1fr));
  gap: 14px;
  align-items: start;
}
.gs-page .gs-card {
  background: var(--white);
  border: var(--border-subtle);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}
.gs-page .gs-card-head {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 11px 14px;
  background: var(--gray-50);
  border-bottom: 2px solid var(--brand-amber);
}
.gs-page .gs-card-head .gs-ico {
  width: 34px;
  height: 34px;
  border-radius: var(--radius-md);
  background: var(--brand-cream-light);
  color: var(--brand-orange-bright);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  flex: 0 0 auto;
}
.gs-page .gs-card-head h5 {
  margin: 0;
  font-size: var(--text-h3);
  font-weight: var(--weight-bold);
  color: var(--gray-900);
  flex: 1;
  min-width: 0;
}
.gs-page .gs-count {
  min-width: 26px;
  padding: 2px 9px;
  border-radius: var(--radius-pill);
  background: var(--brand-amber);
  color: #24180d;
  font-size: var(--text-caption);
  font-weight: var(--weight-bold);
  text-align: center;
}
.gs-page .gs-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  color: var(--gray-900);
  text-decoration: none;
  border-top: 1px solid var(--gray-100);
  transition: background var(--transition-fast);
}
.gs-page .gs-row:first-of-type { border-top: 0; }
.gs-page .gs-row:hover { background: var(--brand-cream-light); color: var(--gray-900); }
.gs-page .gs-row .gs-name {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-weight: var(--weight-medium);
}
.gs-page mark.gs-hl {
  background: var(--brand-cream);
  color: var(--warning-deep);
  font-weight: var(--weight-bold);
  border-radius: 3px;
  padding: 0 2px;
}
.gs-page .gs-extra {
  padding: 2px 9px;
  border-radius: var(--radius-pill);
  background: var(--info-100);
  color: var(--info-deep);
  font-size: 11px;
  font-weight: var(--weight-semi);
  white-space: nowrap;
}
.gs-page .gs-id {
  color: var(--gray-500);
  font-size: 11px;
  direction: ltr;
  unicode-bidi: isolate;
}
.gs-page .gs-row .fa-chevron-left {
  color: var(--gray-300);
  font-size: 11px;
  transition: color var(--transition-fast), transform var(--transition-fast);
}
.gs-page .gs-row:hover .fa-chevron-left {
  color: var(--brand-orange-bright);
  transform: translateX(-3px);
}
.gs-page .gs-card-foot {
  padding: 7px 14px;
  border-top: 1px dashed var(--gray-200);
  color: var(--gray-500);
  font-size: 11px;
  background: var(--gray-50);
}
.gs-page .gs-card-foot.gs-card-foot-error {
  color: var(--danger-deep);
  background: var(--danger-100);
}

/* ─── الحالة الفارغة ─── */
.gs-page .gs-empty {
  background: var(--white);
  border: var(--border-subtle);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  text-align: center;
  padding: 44px 20px;
}
.gs-page .gs-empty .gs-empty-ico {
  width: 72px;
  height: 72px;
  margin: 0 auto 14px;
  border-radius: 50%;
  background: var(--brand-cream-light);
  color: var(--brand-amber);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28px;
}
.gs-page .gs-empty h5 { font-weight: var(--weight-bold); color: var(--gray-900); margin: 0 0 6px; }
.gs-page .gs-empty p { color: var(--gray-500); margin: 0 0 4px; font-size: var(--text-body); }

@media (max-width: 768px) {
  .gs-page .gs-hero { padding: 18px 12px; }
  .gs-page .gs-input-wrap { flex-direction: column; }
  .gs-page .gs-btn { justify-content: center; }
  .gs-page .gs-grid { grid-template-columns: 1fr; }
}
</style>

  <!-- صندوق البحث الرئيس -->
  <div class="gs-hero">
    <form method="get" autocomplete="off">
      <div class="gs-input-wrap">
        <div class="gs-input-box">
          <i class="fas fa-magnifying-glass"></i>
          <input type="text" name="q" class="gs-input"
                 placeholder="اكتب اسمًا أو كودًا… معدةٌ · موظفٌ · عقدٌ · بلاغٌ · وحدة"
                 value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" autofocus aria-label="اكتب اسمًا أو كودًا… معدةٌ · موظفٌ · عقدٌ · بلاغٌ · وحدة">
        </div>
        <button type="submit" class="search-btn gs-btn"><i class="fas fa-magnifying-glass"></i> ابحث</button>
      </div>
    </form>
    <div class="gs-chips">
      <?php foreach ($entities as $e): ?>
        <span class="gs-chip"><i class="<?= htmlspecialchars($e[1], ENT_QUOTES, 'UTF-8') ?>"></i><?= htmlspecialchars($e[0], ENT_QUOTES, 'UTF-8') ?></span>
      <?php endforeach; ?>
    </div>
    <?php if (!$has_query): ?>
      <div class="gs-hint"><i class="fas fa-circle-info"></i> اكتب حرفين على الأقل ثم اضغط «ابحث» — البحث يشمل الكيانات التسعة أعلاه دفعةً واحدة.</div>
    <?php endif; ?>
  </div>

  <?php if ($has_query && empty($sections)): ?>
    <!-- لا نتائج -->
    <div class="gs-empty">
      <div class="gs-empty-ico"><i class="fas fa-box-open"></i></div>
      <h5>لا نتيجةَ لـ«<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>»</h5>
      <p>بحثنا في <?= count($entities) - intval($hidden_sections) ?> من الكيانات الـ<?= count($entities) ?> فلم نجد مطابقًا<?php
          if ($hidden_sections > 0) { echo ' — و' . intval($hidden_sections) . ' منها خارج صلاحيتك'; } ?>.</p>
      <p>جرّب جزءًا أقصر من الاسم، أو الكودَ كما هو مدوَّن في الملف.</p>
    </div>
  <?php elseif ($has_query): ?>
    <!-- ملخّص النتائج -->
    <div class="gs-summary">
      <i class="fas fa-list-check"></i>
      <span>وُجدت <b><?= intval($total_hits) ?></b> نتيجة في <b><?= count($sections) ?></b>
        <?= count($sections) >= 3 && count($sections) <= 10 ? 'أقسام' : 'قسم' ?>
        لـ«<b><?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?></b>» — انقر أيَّ نتيجةٍ تفتح ملفَّها.</span>
    </div>
    <?php if ($hidden_sections > 0): ?>
      <div class="gs-note"><i class="fas fa-lock"></i>
        <?= intval($hidden_sections) ?> من الكيانات الـ<?= count($entities) ?> خارج صلاحيتك فلم يُبحث فيها.</div>
    <?php endif; ?>

    <!-- بطاقات الأقسام -->
    <div class="gs-grid">
      <?php foreach ($sections as $sec): list($label, $icon, $rows, $urlFn) = $sec; ?>
        <div class="gs-card">
          <div class="gs-card-head">
            <span class="gs-ico"><i class="<?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>"></i></span>
            <h5><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></h5>
            <span class="gs-count"><?= $rows === false ? "!" : count($rows) ?></span>
          </div>
          <?php if ($rows === false): ?>
            <div class="gs-card-foot gs-card-foot-error">
              <i class="fas fa-triangle-exclamation"></i>
              تعذّر البحثُ في هذا الكيان (خطأُ استعلام) — لا تعتبره «بلا نتيجة».
            </div>
          <?php endif; ?>
          <?php foreach (($rows ?: array()) as $x): ?>
            <a class="gs-row" href="<?= htmlspecialchars($urlFn($x), ENT_QUOTES, 'UTF-8') ?>">
              <span class="gs-name"><?= gs_highlight($x['name'], $q) ?></span>
              <?php if (!empty($x['extra'])): ?>
                <span class="gs-extra"><?= htmlspecialchars($x['extra'], ENT_QUOTES, 'UTF-8') ?></span>
              <?php endif; ?>
              <span class="gs-id">#<?= intval($x['id']) ?></span>
              <i class="fas fa-chevron-left"></i>
            </a>
          <?php endforeach; ?>
          <?php if ($rows !== false && count($rows) === 8): ?>
            <div class="gs-card-foot"><i class="fas fa-circle-info"></i> تُعرض أول 8 نتائج — ضيّق البحث لمزيدٍ من الدقة.</div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
