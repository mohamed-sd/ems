<?php
/**
 * Suppliers/supplier_inference_rules.php — قاموسُ قواعدِ الاستنتاج (SUP-33)
 * ───────────────────────────────────────────────────────────────────────────
 * Grain: **قاعدةُ استنتاجٍ واحدة** — بنيويٌّ (STRUCTURAL).
 *
 * ◆ القواعدُ تُقرأ من مخزنِها الحاكمِ لا تُعاد صياغتُها: كلُّ صفِّ انتقالٍ
 *   في مخزنِ آلاتِ موجةِ الموردين يحمل قواعدَه المؤلَّفةَ (الشرطُ المسبقُ ·
 *   المستندُ الرسميُّ · بوّابةُ الاعتمادِ · قاعدتا إعادةِ الفتحِ والتصحيحِ ·
 *   وعلّةُ المنعِ للممنوع) — فتُعرَض قاعدةً قاعدةً بمرجعِ صفِّها.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/w14_grid.php';
include '../includes/permissions_helper.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Suppliers/supplier_inference_rules.php');
$can_view = $pp['can_view'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$rows = array(); $entSet = array(); $nForbid = 0;
$r = @$conn->query("SELECT entity, from_state, to_state, allowed, owner_role, precondition, official_doc,
                           approval_gate, reopen_rule, correct_rule, forbid_reason, src_ref
                      FROM repair01_w8_states ORDER BY entity, allowed DESC, from_state");
while ($r && ($x = $r->fetch_assoc())) {
    $entSet[(string) $x['entity']] = 1;
    if ((int) $x['allowed'] !== 1) { $nForbid++; }
    $rules = array();
    foreach (array('precondition' => 'شرط مسبق', 'official_doc' => 'مستند رسمي', 'approval_gate' => 'بوابة اعتماد',
                   'reopen_rule' => 'اعادة فتح', 'correct_rule' => 'تصحيح', 'forbid_reason' => 'علة منع') as $k0 => $lbl) {
        $v0 = trim((string) $x[$k0]);
        if ($v0 !== '') { $rules[] = $lbl . ': ' . $v0; }
    }
    $rows[] = array(
        'ent'   => (string) $x['entity'],
        'move'  => (string) $x['from_state'] . ' الى ' . (string) $x['to_state'],
        'judg'  => (int) $x['allowed'] === 1 ? 'مسموح' : 'ممنوع صراحة',
        'owner' => trim((string) $x['owner_role']) !== '' ? (string) $x['owner_role'] : 'غير منطبق',
        'rules' => $rules ? implode(' · ', $rules) : 'بلا قواعد مدونة للصف',
        'src'   => (string) $x['src_ref'],
    );
}

$page_title = 'إيكوبيشن | قاموس قواعد الاستنتاج للموردين';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'قاموس قواعد الاستنتاج: قاعدة واحدة بمرجع صفها من المخزن الحاكم'; $header_icon = 'fa fa-book'; $header_actions = array();
    $header_back = array('href' => 'supplier_ref_lists.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'القوائم المرجعية');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(count($rows)) ?></div><div class="ems-stat-label">صفوف قواعد مؤلفة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(count($entSet)) ?></div><div class="ems-stat-label">كيانات مشمولة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nForbid) ?></div><div class="ems-stat-label">ممنوعات صريحة بعللها</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value">1</div><div class="ems-stat-label">مخزن حاكم واحد</div></div>
    </div>

    <div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close:emsList_sup_rules
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            '#' => 'id',
            'معرف القاعدة' => 'rule_uid',
            'الشيت' => 'sheet_name',
            'الحقل/السجل' => 'field_or_record',
            'ملف المصدر' => 'source_file',
            'شيت المصدر' => 'source_sheet',
            'مفتاح المصدر' => 'source_key',
            'قاعدة الاستنتاج' => 'inference_rule',
            'مستويات الحجية الفعلية (محسوبة من سجل التتبع 24)' => 'effective_authority_levels',
            'قاعدة المالك' => 'owner_rule',
            'عدد أسطر التتبع' => 'trace_line_count',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('sup_dictionary_rule_derivation');
        echo ems_w14_grid('emsList_sup_rules', $GUIDE_COLS, $__gridRows, $D, 'لا قاعدة استنتاج مسجلة بعد'); /* /GUIDE_COLS */ ?>
    </div>

    <div class="ems-note-box">
        القاموس يعرض قواعد المخزن الحاكم حرفا بمرجع كل صف ولا يعيد صياغتها،
        والممنوع الصريح يعرض بعلته. قراءة صرف ولا ادخال.
    </div>
</div>
<?php include '../infooter.php'; ?>
