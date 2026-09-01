<?php
/**
 * Fleet/asset_readiness.php — الجاهزيّةُ والملخّصُ الشهريّ (RPR-W05 · FLEET-19/20)
 * ───────────────────────────────────────────────────────────────────────────
 * **مشتقّةٌ بالكامل — لا إدخال** (‏`FLEET-20` نصًّا)، و«لا تُدخَل الساعاتُ يدويًّا
 * مرّتين» (‏`FLEET-19`). فالسطحُ **بلا نموذجِ إدخالٍ واحد**: يعرض الصفَّ المشتقَّ
 * ومعه **قاعدةُ اشتقاقِه ومصادرُه بالاسم** — فلا رقمَ بلا قاعدة.
 *
 * ◆ **والتوقّفُ يُخصَم بالأكبرِ لا بالمجموع**: الواقعةُ الواحدةُ في سجلَّين
 *   (‏W04 §٢ — `unit_time_log` و`timesheet`)، وجمعُهما يحتسب الساعةَ مرّتين
 *   فيضاعف الخصمَ من الجاهزيّة.
 * ◆ **والصيغةُ دالّةٌ واحدة** (`AssetLifecycleService::readinessFormula`) تستدعيها
 *   الأداةُ والبوّابةُ والشاشةُ معًا — فلا يتفرّق عدّادٌ وعارضٌ في ملفَّين.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/guide_label.php';
require_once __DIR__ . '/../app/Services/Fleet/AssetLifecycleService.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة ❌', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Fleet/asset_readiness.php');
$can_view = $pp['can_view'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية ❌', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('asset readiness super') : ems_tenant_db();
$period = isset($_GET['period']) ? preg_replace('/[^0-9\-]/', '', (string) $_GET['period']) : '';

$rows = array(); $equip = array(); $periods = array();
try {
    $opts = array('orderBy' => 'period DESC, readiness_pct ASC', 'limit' => 400);
    if ($period !== '') { $opts['where'] = array('period' => $period); }
    $rows = $gate->select('asset_readiness', $opts);
} catch (\Throwable $t) { error_log('asset_readiness.php list: ' . $t->getMessage()); }
try { foreach ($gate->select('asset_readiness', array('columns' => array('period'), 'orderBy' => 'period DESC', 'limit' => 400)) as $p) { $periods[(string) $p['period']] = true; } }
catch (\Throwable $t) { error_log('asset_readiness.php periods: ' . $t->getMessage()); }
try { foreach ($gate->select('equipments', array('columns' => array('id', 'code', 'name'), 'orderBy' => 'id ASC', 'limit' => 400)) as $e) { $equip[(int) $e['id']] = trim(($e['code'] ?? '') . ' — ' . ($e['name'] ?? '')); } }
catch (\Throwable $t) { error_log('asset_readiness.php equip: ' . $t->getMessage()); }

$avg = 0.0; $util = 0.0; $n = count($rows);
foreach ($rows as $r) { $avg += (float) $r['readiness_pct']; $util += (float) $r['utilization_pct']; }
if ($n > 0) { $avg = round($avg / $n, 2); $util = round($util / $n, 2); }

$page_title = 'إيكوبيشن | الجاهزية الشهرية';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'الجاهزية والملخص الشهري — مشتقة بالكامل لا إدخال'; $header_icon = 'fa fa-gauge-high'; $header_actions = array();
    $header_back = array('href' => 'readiness_board.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'لوحة الجاهزية');
    include('../includes/page_header.php'); ?>
    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $n ?></div><div class="ems-stat-label">صفوف مشتقة (أصل × شهر)</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $avg ?>٪</div><div class="ems-stat-label">متوسط الجاهزية</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $util ?>٪</div><div class="ems-stat-label">متوسط الاستغلال</div></div>
    </div>
    <form method="get" action="" class="ems-filters">
        <div class="field"><label for="w5_rd_p">الفترة</label><select name="period" id="w5_rd_p" onchange="this.form.submit()">
            <option value="">كل الفترات</option>
            <?php foreach (array_keys($periods) as $p): ?>
                <option value="<?= htmlspecialchars($p) ?>" <?= $period === $p ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
            <?php endforeach; ?>
        </select></div>
    </form>
    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا صفوف جاهزية مشتقة بعد', 'الاشتقاق يجري من التايم شيت وسجل التوقف — ولا يدخل من هنا'); ?>

    <?php
    /* ◆ **أعمدةُ الورقةِ حرفًا** (GOV_EXEC §12): السطحُ يخدم هدفَين —
         «الملخص التشغيلي الشهري» (`FLEET-19`) و«الجاهزية الشهرية» (`FLEET-20`) —
         فرؤوسُه اتحادُ حقولِهما بأسمائِها في «02 · تتبع الحقول» **بلا إعادةِ صياغة**.
       ◆ وكلُّها **مشتقّةٌ يكتبها محرّكُ الاشتقاق** ولا نموذجَ إدخالٍ في الشاشة —
         فالفارغُ يُعرض «—» ولا يُملأ بيدٍ (رأسُ الملفِّ أعلاه بنصِّه).
       ◆ والاسمُ ⇒ العمود في مصفوفةٍ واحدةٍ: الرأسُ والخليّةُ لا يتفرّقان. */
    $GUIDE_COLS = array(
        'كود السجل' => 'record_code',
        'كود الأصل' => '__equip',
        'الشهر' => 'period',
        'مفتاح الوحدة' => 'unit_key',
        'المشروع' => 'project_ref',
        'نموذج العمل' => 'business_model',
        'العداد أول الشهر' => 'meter_start',
        'العداد آخر الشهر' => 'meter_end',
        'ساعات العداد' => 'meter_hours',
        'الساعات المنفذة' => 'executed_hours',
        'ساعات الجاك همر' => 'jackhammer_hours',
        'الساعات الإضافية' => 'extra_hours',
        'ساعات الاستعداد' => 'standby_hours',
        /* ◆ الورقتان تسمّيان العمودَ الواحدَ بلفظَين: `FLEET-19` «تعطل …» و`FLEET-20`
             «توقف …». والعمودُ واحدٌ فيُعرَض باللفظَين معًا — فلا عمودان لمعنًى
             واحد ولا اسمٌ يُهمَل. */
        'تعطل الصيانة / توقف الصيانة' => 'maint_down_hours',
        'تعطل اعتمادية / توقف اعتمادية' => 'reliab_down_hours',
        'تعطل تشغيلي / توقف تشغيلي' => 'oper_down_hours',
        'إجمالي الساعات' => 'total_hours',
        'الساعات المتراكمة' => 'accumulated_hours',
        'الأطنان المنقولة' => 'tons_moved',
        'الأمتار المنجزة' => 'meters_done',
        'الوقود المستهلك' => 'fuel_consumed',
        'معدل الوقود للساعة' => 'fuel_rate_hour',
        'نسبة الاستغلال' => 'utilization_pct',
        'فرق العداد عن التايم شيت' => 'meter_vs_timesheet',
        'مصدر البيان' => 'statement_source',
        'درجة الثقة' => 'confidence_grade',
        'المراجع' => 'reviewer',
        'تاريخ الاعتماد' => 'approved_at',
        'الساعات المتاحة' => 'available_hours',
        'ساعات التشغيل' => 'operating_hours',
        'توقف غير مخطط' => 'unplanned_down',
        'نسبة الجاهزية' => 'readiness_pct',
        'نسبة الأداء' => 'performance_pct',
        'الكفاءة الكلية' => 'oee_pct',
        'عدد أيام التوقف' => 'down_days',
        'حالة الجاهزية' => 'readiness_state',
        'ساعات الوردية' => 'shift_hours',
        'حالة الأصل' => 'lifecycle_state',
        'قاعدة الاشتقاق' => 'derivation_rule',
        'المصادر' => 'derived_from',
    );
    ?>
    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>#</th>
            <?php foreach ($GUIDE_COLS as $__lbl => $__k): ?><th><?= htmlspecialchars(ems_guide_label($__lbl), ENT_QUOTES, 'UTF-8') ?></th><?php endforeach; ?>
        </tr></thead>
        <tbody>
        <?php if ($rows): $i = 0; foreach ($rows as $r): $i++; ?>
            <tr><td><?= $i ?></td>
                <?php foreach ($GUIDE_COLS as $__lbl => $__k):
                    if ($__k === '__equip') {
                        $__v = isset($equip[(int) $r['equipment_id']]) ? $equip[(int) $r['equipment_id']] : ('#' . (int) $r['equipment_id']);
                    } else {
                        $__v = isset($r[$__k]) ? (string) $r[$__k] : '';
                    }
                    if (trim((string) $__v) === '') { $__v = '—'; } ?>
                <td<?= $__v === '—' ? ' class="ems-gov-empty"' : '' ?>><?php
                    echo ($__k === 'readiness_pct')
                        ? '<strong>' . htmlspecialchars((string) $__v, ENT_QUOTES, 'UTF-8') . '</strong>'
                        : htmlspecialchars((string) $__v, ENT_QUOTES, 'UTF-8'); ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="<?= count($GUIDE_COLS) + 1 ?>">لا صفوف جاهزية مشتقة بعد.</td></tr>
        <?php endif; ?>
        </tbody></table></div>
</div>
</body></html>
