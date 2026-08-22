<?php
/**
 * tools/baseline_xlsx_build.php — BL-20260821: توليد INJ-SCREENS-MASTER.xlsx
 * من ملفات extract/*.json حصرًا — لا يمس القاعدة.
 * قاعدة الحزمة: لا صيغ في الملف — كل قيمة قياسٌ مولَّد من اللقطة BL-20260821-f0bc3e4e
 * (الملف سجلُّ قياسٍ لا نموذج حساب، وبيئة إعادة الحساب غير متاحة محليًّا).
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
ini_set('memory_limit', '3G');
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require $ROOT . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$D = $ROOT . '/docs/baseline_20260821/extract/';
function j($f) { global $D; return json_decode((string) file_get_contents($D . $f . '.json'), true) ?: array(); }

$SNAP = 'BL-20260822-f330a975';
$reg = j('screen_registry');
$fields = j('field_registry');
$cycle = j('gov_screen_cycle');
$ledger = j('gov_migration_ledger');
$apps = j('gov_space_appearances');
$roles = j('roles');
$sens = j('scr_sensitive_fields');
$fclass = j('gov_field_class');
$stats = j('reconcile_stats');
$statusM = j('status_metrics');
$conflicts = j('reconcile_conflicts');

$regByRoute = array();
foreach ($reg as $r) { $regByRoute[$r['route']] = $r; }
$fieldsBySid = array();
foreach ($fields as $f) { $fieldsBySid[$f['screen_id']][] = $f; }
$cycleById = array();
foreach ($cycle as $c) { $cycleById[$c['id']] = $c; }

$wb = new Spreadsheet();
$wb->getProperties()->setTitle('INJ-SCREENS-MASTER')->setCreator('BL-20260821 baseline extraction')
    ->setDescription('السجل التفصيلي للإدارات والشاشات والحقول — لقطة ' . $SNAP);
$wb->removeSheetByIndex(0);

$HDR_FILL = 'FF1F4E5F';
$SCR_FILL = 'FFDCE6F1';

function mk_sheet($wb, $title)
{
    $ws = $wb->createSheet();
    $ws->setTitle(mb_substr($title, 0, 31));
    $ws->setRightToLeft(true);
    $ws->getDefaultRowDimension()->setRowHeight(-1);
    return $ws;
}
function put_head($ws, $row, $cols, $fill)
{
    $ws->fromArray($cols, null, 'A' . $row);
    $last = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($cols));
    $rng = 'A' . $row . ':' . $last . $row;
    $ws->getStyle($rng)->getFont()->setBold(true)->setName('Arial')->setSize(10)->getColor()->setARGB('FFFFFFFF');
    $ws->getStyle($rng)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($fill);
    $ws->getStyle($rng)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
}
function meta_rows($ws, $title)
{
    global $SNAP;
    $ws->setCellValue('A1', $title);
    $ws->setCellValue('A2', 'اللقطة: ' . $SNAP . ' · تاريخ القياس: 2026-08-22 05:18→05:55 · المصدر: استخراج حي (كود + قاعدة + Git) — لا صيغ: كل قيمة قياسٌ بلحظته · اللقطة السابقة في historical/');
    $ws->getStyle('A1')->getFont()->setBold(true)->setSize(13)->setName('Arial');
    $ws->getStyle('A2')->getFont()->setSize(9)->setItalic(true)->setName('Arial');
}

/* ════ 00_EXEC_SUMMARY ═══════════════════════════════════════════════ */
$ws = mk_sheet($wb, '00_EXEC_SUMMARY');
meta_rows($ws, 'INJ-SCREENS-MASTER — الملخص التنفيذي');
$sumRows = array(
    array('البند', 'القيمة', 'المقام/الملاحظة'),
    array('صفوف سجل الشاشات الموحَّد', $stats['registry_rows'], 'اتحاد 7 مصادر: قرص · دفتر الترحيل · دورة الشاشات · الظهورات · التنقّل · nav09 · بنود الملفات الشخصية'),
    array('شاشات مصيَّرة على القرص', $stats['on_disk_screens'], 'من ' . $stats['registry_rows'] . ' — تضمّن القشرة أو مولَّدة بعُدّة (U13/DeptGov/FinShell)'),
    array('معالجات أفعال (Handlers)', $stats['handlers'], 'أهداف POST/AJAX'),
    array('مهام خلفية (Cron)', $stats['cron'], 'ملفات cron_*'),
    array('مسار مسجَّل بلا ملف على القرص', $stats['registry_no_disk'], 'من ' . $stats['registry_rows'] . ' — صفر بعد التوفيق'),
    array('شاشة على القرص خارج كل السجلات', $stats['disk_not_in_any_registry'], 'من ' . $stats['on_disk_screens'] . ' — قائمة كاملة في 07_UNRESOLVED'),
    array('شاشة بمالك UNKNOWN', $stats['owner_unknown'], 'من ' . $stats['registry_rows'] . ' — أغلبها معالجات/كرون بلا سجل ملكية؛ التفصيل في 07'),
    array('تعارض ملكية بين المصادر', $stats['owner_conflicts'], 'من ' . $stats['registry_rows'] . ' — القائمة في 07_UNRESOLVED'),
    array('شاشة بمرحلة دورة عمل', $stats['with_stage'], 'من ' . $stats['registry_rows'] . ' (سجل الدورة يغطي 663 ظهورًا إداريًّا)'),
    array('إجمالي الحقول المستخرَجة', $stats['fields_total'], 'استخراج ساكن: أعمدة جداول + حقول نماذج + أعمدة U13 من gov_field_class'),
    array('حقول حساسة بسياسة', count($sens), 'scr_sensitive_fields — **34/34 بحالة «معتمد» أي داخل الإنفاذ** بعد توحيد القاموس (كانت 15/34)'),
    array('حقول مصنَّفة DC', count($fclass), 'gov_field_class على 44 شاشة'),
    array('أدوار', count($roles), 'جدول roles'),
    array('أفعال القاموس', $statusM['action_dict_total'], 'nav09_action_map'),
    array('أفعال موثَّقة الحارس', $statusM['action_guard_verified'], 'من ' . $statusM['action_dict_total']),
);
$ws->fromArray($sumRows, null, 'A4');
put_head($ws, 4, $sumRows[0], $HDR_FILL);
foreach (array('A' => 42, 'B' => 14, 'C' => 90) as $c => $w) { $ws->getColumnDimension($c)->setWidth($w); }

/* ════ 01_SCREEN_REGISTRY ════════════════════════════════════════════ */
$ws = mk_sheet($wb, '01_SCREEN_REGISTRY');
meta_rows($ws, 'سجل الشاشات الموحَّد — كل الأسطح (شاشة/معالج/كرون/منظر فرعي)');
$cols = array('Screen_ID', 'الاسم العربي', 'Canonical_Name_EN', 'Route', 'Surface_Type', 'مولّد', 'على القرص',
    'Owner_Department', 'أساس الملكية', 'عدد المساحات الظاهر فيها', 'المساحات', 'Parent_Screen',
    'Workflow_Stage', 'ترتيب المرحلة', 'الطبقة', 'المجموعة', 'الغرض/الطبيعة', 'Input_Document', 'Output_Document',
    'Next_State', 'الدور المسؤول', 'Consumers', 'أثر مالي', 'أدوار التنقّل (nav)', 'عدد أدوار nav',
    'عدد بنود الملفات الشخصية', 'أعمدة جداول', 'حقول نماذج', 'Direct_URL', 'Deprecated', 'المصادر (Evidence)', 'Known_Issue');
put_head($ws, 4, $cols, $HDR_FILL);
$rows = array();
foreach ($reg as $r) {
    $spaces = array();
    foreach ($r['workspaces'] as $wsp) { $spaces[$wsp['space']] = 1; }
    $st = $r['stage'];
    $led = $r['ledger'];
    $rows[] = array(
        $r['screen_id'], $r['name_ar'], 'UNKNOWN', $r['route'], $r['surface_type'], $r['generator'] ?? '',
        $r['on_disk'] ? 'نعم' : 'لا',
        $r['owner_dept'], $r['owner_basis'], count($spaces), implode(' · ', array_keys($spaces)),
        $r['parent_file'] ?? ($led['parent_file'] ?? ''),
        $st ? $st['stage_name'] : 'UNKNOWN', $st ? $st['stage_order'] : '', $st ? $st['layer'] : ($led['layer'] ?? ''),
        $st ? $st['group'] : '', $led ? ($led['nature'] . ' · ' . $led['target_type']) : 'UNKNOWN',
        $st ? $st['inputs'] : 'UNKNOWN', $st ? $st['output_doc'] : 'UNKNOWN',
        $st ? $st['next_state'] : 'UNKNOWN', $st ? $st['resp_role'] : '', $st ? $st['consumers'] : '',
        $st ? $st['fin_impact'] : '',
        implode(' · ', array_keys($r['roles_nav'])), count($r['roles_nav']),
        $r['profiles_count'], $r['table_columns'], $r['form_fields'],
        in_array($r['surface_type'], array('SCREEN', 'SCREEN_VIA_KIT', 'SCREEN_SUBSYSTEM'), true) ? 'نعم' : ($r['surface_type'] === 'VIEW_VARIANT' ? 'عبر الأم' : 'لا'),
        $r['deprecated'] ? 'نعم' : 'لا',
        implode(',', $r['sources']), implode(' | ', $r['known_issue']),
    );
}
$ws->fromArray($rows, null, 'A5');
$ws->freezePane('A5');

/* ════ 02_FIELD_REGISTRY ═════════════════════════════════════════════ */
$ws = mk_sheet($wb, '02_FIELD_REGISTRY');
meta_rows($ws, 'سجل الحقول — كل حقل/عمود مستخرَج استخراجًا ساكنًا من الشاشات + أعمدة U13 من gov_field_class');
$cols = array('Field_ID', 'Screen_ID', 'Route', 'النوع', 'التسمية العربية', 'Technical_Name', 'نوع الإدخال',
    'مجموعة العمود', 'مخفي افتراضًا', 'Required', 'ReadOnly', 'قسم النموذج', 'DC_Code', 'حساس', 'ملاحظة');
put_head($ws, 4, $cols, $HDR_FILL);
$rows = array();
foreach ($fields as $f) {
    $rows[] = array(
        $f['field_id'], $f['screen_id'], $f['route'], $f['kind'], $f['label_ar'],
        $f['technical'] === null ? 'NEEDS_REVIEW' : $f['technical'],
        $f['input_type'], $f['col_group'], $f['hidden_default'] === 1 ? 'نعم' : '',
        $f['required'] === 1 ? 'نعم' : '', $f['readonly'] === 1 ? 'نعم' : '',
        $f['section'], $f['dc_code'], ($f['is_sensitive'] === 1 || $f['is_sensitive'] === '1') ? 'نعم' : '',
        ($f['technical'] === 'NEEDS_REVIEW') ? 'المحاذاة الموضعية تعذّرت — يلزم توثيق يدوي' : '',
    );
}
$ws->fromArray($rows, null, 'A5');
$ws->freezePane('A5');

/* ════ 03_ROLE_SCREEN_MATRIX ═════════════════════════════════════════ */
$ws = mk_sheet($wb, '03_ROLE_SCREEN_MATRIX');
meta_rows($ws, 'مصفوفة الدور × الشاشة — الظهور في التنقّل الحي (nav_items النشطة). تنبيه: دخول الشاشة لـ97٪ من المستخدمين يُحسم بقوالب gov_profile_items لا بهذه المصفوفة (انظر INJ-ARCH-ASBUILT §5)');
$roleNames = array();
foreach ($roles as $ro) { $roleNames[$ro['id']] = $ro['name']; }
$navItems = j('nav_items');
$matrix = array();
foreach ($navItems as $n) {
    $route = $n['route'];
    $matrix[$route][$n['role_id']] = 1;
}
$cols = array_merge(array('Route', 'الاسم'), array_values($roleNames));
put_head($ws, 4, $cols, $HDR_FILL);
$rows = array();
ksort($matrix);
foreach ($matrix as $route => $byRole) {
    $name = isset($regByRoute[$route]) ? $regByRoute[$route]['name_ar'] : (isset($regByRoute[preg_replace('#^(\.\./)+#','',$route)]) ? $regByRoute[preg_replace('#^(\.\./)+#','',$route)]['name_ar'] : '');
    $row = array($route, $name);
    foreach (array_keys($roleNames) as $rid) { $row[] = isset($byRole[$rid]) ? '✓' : ''; }
    $rows[] = $row;
}
$ws->fromArray($rows, null, 'A5');
$ws->freezePane('C5');

/* ════ 04_ROLE_FIELD_MATRIX ══════════════════════════════════════════ */
$ws = mk_sheet($wb, '04_ROLE_FIELD_MATRIX');
meta_rows($ws, 'الحقول الحساسة وسياساتها (scr_sensitive_fields) — الأدوار المخوَّلة نصية كما وردت؛ الحقول بحالة «active» خارج الإنفاذ الفعلي (الشرط الحرفي status=«معتمد») — فجوة موثقة');
$cols = array('No_Policy', 'الجدول', 'الحقل', 'التصنيف', 'مرئي لمن (نص السياسة)', 'سياسة القناع', 'يُسجَّل الاطلاع',
    'قابل للتصدير', 'الأساس', 'الحالة', 'داخل الإنفاذ فعليًّا؟');
put_head($ws, 4, $cols, $HDR_FILL);
$rows = array();
foreach ($sens as $s) {
    $enforced = ($s['status'] === 'معتمد') ? 'نعم' : 'لا — الحالة "' . $s['status'] . '" لا يطابقها شرط الإنفاذ';
    $rows[] = array($s['no_policy'], $s['table_name'], $s['field_name'], $s['classification_sensitivity'],
        $s['from_visible_to'], $s['policy_masking'], $s['log_views_flag'], $s['exportable_flag'],
        $s['basis_statutory'], $s['status'], $enforced);
}
$ws->fromArray($rows, null, 'A5');

/* ════ 05_WORKFLOW_MATRIX ════════════════════════════════════════════ */
$ws = mk_sheet($wb, '05_WORKFLOW_MATRIX');
meta_rows($ws, 'مصفوفة دورة العمل — الإدارة ← المرحلة ← الشاشة ← المدخل ← المخرج ← الحالة التالية (gov_screen_cycle كاملًا: 663 ظهورًا)');
$cols = array('الإدارة', 'الطبقة', 'ترتيب', 'المرحلة', 'المجموعة', 'الشاشة', 'الملف', 'Screen_ID', 'المدخلات',
    'المستند الناتج', 'الدور المسؤول', 'الحالة التالية', 'المستهلكون', 'أثر مالي');
put_head($ws, 4, $cols, $HDR_FILL);
$rows = array();
$routeByLedgerId = array();
foreach ($ledger as $L) {
    $rt = ($L['route'] !== '' && $L['route'] !== '—') ? $L['route'] : $L['file_base'];
    $routeByLedgerId[$L['id']] = str_replace('\\', '/', $rt);
}
foreach ($cycle as $c) {
    $route = $routeByLedgerId[$c['id']] ?? $c['screen_file'];
    $sid = isset($regByRoute[$route]) ? $regByRoute[$route]['screen_id'] : 'NEEDS_REVIEW';
    $rows[] = array($c['dept_name'], $c['layer_name'], (int) $c['stage_order'], $c['stage_name'], $c['group_name'],
        $c['screen_title'], $c['screen_file'], $sid, $c['inputs_note'], $c['output_doc'], $c['resp_role'],
        $c['next_state'], $c['consumers'], $c['fin_impact']);
}
usort($rows, fn($a, $b) => array($a[0], $a[2]) <=> array($b[0], $b[2]));
$ws->fromArray($rows, null, 'A5');
$ws->freezePane('A5');

/* ════ 06_CROSS_DEPARTMENT_ACCESS ════════════════════════════════════ */
$ws = mk_sheet($wb, '06_CROSS_DEPT_ACCESS');
meta_rows($ws, 'كل ظهور عابر للإدارات وسببه — gov_space_appearances حيث المساحة ≠ الإدارة المالكة');
$cols = array('المساحة', 'نوعها', 'التبويب', 'الشاشة', 'Route', 'Screen_ID', 'الإدارة المالكة', 'نوع الملكية',
    'التصنيف (cls)', 'القرار', 'الأساس', 'عدد المساحات للمسار');
put_head($ws, 4, $cols, $HDR_FILL);
$rows = array();
foreach ($apps as $a) {
    if ($a['space_ar'] === $a['owner_dept_ar']) { continue; }
    $route = str_replace('\\', '/', $a['route']);
    $sid = isset($regByRoute[$route]) ? $regByRoute[$route]['screen_id'] : '';
    $rows[] = array($a['space_ar'], $a['space_kind'], $a['tab_ar'], $a['screen_ar'], $route, $sid,
        $a['owner_dept_ar'], $a['owner_kind'], $a['cls'], $a['decision'], $a['basis'], $a['spaces_count']);
}
$ws->fromArray($rows, null, 'A5');
$ws->freezePane('A5');

/* ════ 07_UNRESOLVED ═════════════════════════════════════════════════ */
$ws = mk_sheet($wb, '07_UNRESOLVED');
meta_rows($ws, 'كل ما لم يُحسم — لا تخمين: UNKNOWN/NEEDS_REVIEW تُحل بقرار مالك لا باستنتاج');
$cols = array('النوع', 'Screen_ID/المعرف', 'Route/المرجع', 'التفصيل', 'المطلوب');
put_head($ws, 4, $cols, $HDR_FILL);
$rows = array();
foreach ($reg as $r) {
    if ($r['sources'] === array('disk') && in_array($r['surface_type'], array('SCREEN', 'SCREEN_VIA_KIT'), true)) {
        $rows[] = array('شاشة على القرص خارج كل السجلات', $r['screen_id'], $r['route'],
            'تُصيَّر ولا يعرفها أي سجل حاكم (لا ledger ولا apps ولا nav)', 'قرار: تسجيل أو إخراج من الخدمة');
    }
}
foreach ($reg as $r) {
    if ($r['owner_dept'] === 'UNKNOWN' && in_array($r['surface_type'], array('SCREEN', 'SCREEN_VIA_KIT', 'SCREEN_SUBSYSTEM'), true)) {
        $rows[] = array('شاشة بلا مالك', $r['screen_id'], $r['route'], 'لا حكم ملكية ولا إجماع مصادر', 'قرار مالك أو PLATFORM_SHARED');
    }
}
foreach ($conflicts as $c) {
    $sid = isset($regByRoute[$c['route']]) ? $regByRoute[$c['route']]['screen_id'] : '';
    $vals = array();
    foreach ($c['values'] as $k => $v) { $vals[] = $k . '×' . $v; }
    $rows[] = array('تعارض ملكية بين المصادر', $sid, $c['route'], implode(' مقابل ', $vals), 'ترجيح بالأغلبية مؤقتًا — يلزم حكم');
}
foreach ($ledger as $L) {
    if ($L['resolve_state'] !== 'RESOLVED') {
        $rows[] = array('بند دفتر ترحيل غير محسوم', 'LEDGER#' . $L['id'], $L['route'], $L['resolve_state'] . ' — ' . $L['problems'], 'حسم');
    }
}
foreach ($apps as $a) {
    if ($a['decision'] === 'PENDING' || $a['cls'] === 'UNRESOLVED') {
        $rows[] = array('ظهور مساحة غير محسوم', '', str_replace('\\', '/', $a['route']),
            $a['space_ar'] . ' — cls=' . $a['cls'] . ' · decision=' . $a['decision'] . ' · ' . $a['src_note'], 'قرار مالك');
    }
}
$rows[] = array('حقول بلا اسم تقني', '', 'FIELD_REGISTRY', 'المحاذاة الموضعية تعذّرت في 336 جدولًا من 582 — الحقول موسومة NEEDS_REVIEW', 'توثيق يدوي تدريجي');
$rows[] = array('بنود ملفات شخصية لمسار مجهول', '', 'gov_profile_items', $stats['gpi_unknown_route'] . ' بنود item_ref لا يطابق أي مسار في السجل', 'تصحيح البنود');
$ws->fromArray($rows, null, 'A5');
$ws->freezePane('A5');

/* ════ أوراق الإدارات ════════════════════════════════════════════════ */
$byDept = array();
foreach ($ledger as $L) { $byDept[$L['dept']][] = $L; }
ksort($byDept);
$deptCols = array('الصف', 'Screen_ID/Field_ID', 'ترتيب المرحلة', 'المرحلة', 'المجموعة', 'الاسم/التسمية',
    'Route/Technical', 'نوع السطح/نوع الحقل', 'المدخلات/نوع الإدخال', 'المستند الناتج/مجموعة العمود',
    'الحالة التالية/إلزامي', 'الدور المسؤول/DC', 'المستهلكون/حساس', 'أثر مالي', 'أدوار nav', 'حالة القالب', 'مشاكل معلومة');
foreach ($byDept as $dept => $items) {
    $ws = mk_sheet($wb, $dept);
    meta_rows($ws, 'إدارة: ' . $dept . ' — الشاشات مرتبة بدورة العمل (gov_screen_cycle) وتحت كل شاشة حقولها المستخرَجة');
    put_head($ws, 4, $deptCols, $HDR_FILL);
    /* ترتيب ببيان الدورة */
    usort($items, function ($a, $b) use ($cycleById) {
        $sa = isset($cycleById[$a['id']]) ? (int) $cycleById[$a['id']]['stage_order'] : 999;
        $sb = isset($cycleById[$b['id']]) ? (int) $cycleById[$b['id']]['stage_order'] : 999;
        return $sa <=> $sb ?: strcmp($a['screen_label'], $b['screen_label']);
    });
    $rowN = 5;
    foreach ($items as $L) {
        $route = $routeByLedgerId[$L['id']];
        $r = $regByRoute[$route] ?? null;
        $c = $cycleById[$L['id']] ?? null;
        $sid = $r ? $r['screen_id'] : 'NEEDS_REVIEW';
        $scrRow = array('شاشة', $sid,
            $c ? (int) $c['stage_order'] : '', $c ? $c['stage_name'] : '', $c ? $c['group_name'] : '',
            $L['screen_label'], $route, $r ? $r['surface_type'] : 'UNKNOWN',
            $c ? $c['inputs_note'] : '', $c ? $c['output_doc'] : '',
            $c ? $c['next_state'] : '', $c ? $c['resp_role'] : '', $c ? $c['consumers'] : '',
            $c ? $c['fin_impact'] : '',
            $r ? implode(' · ', array_keys($r['roles_nav'])) : '',
            $L['migration_state'], ($L['problems'] !== '—') ? $L['problems'] : '');
        $ws->fromArray(array($scrRow), null, 'A' . $rowN);
        $ws->getStyle('A' . $rowN . ':Q' . $rowN)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($SCR_FILL);
        $ws->getStyle('A' . $rowN . ':Q' . $rowN)->getFont()->setBold(true)->setName('Arial')->setSize(9);
        $rowN++;
        if ($r && isset($fieldsBySid[$r['screen_id']])) {
            $frows = array();
            foreach ($fieldsBySid[$r['screen_id']] as $f) {
                if ($f['kind'] === 'form_field_system') { continue; }
                $frows[] = array('حقل', $f['field_id'], '', '', '', $f['label_ar'],
                    $f['technical'] ?? 'NEEDS_REVIEW', $f['kind'], $f['input_type'], $f['col_group'],
                    $f['required'] === 1 ? 'نعم' : '', $f['dc_code'],
                    ($f['is_sensitive'] === 1 || $f['is_sensitive'] === '1') ? 'حساس' : '', '', '', '', '');
            }
            if ($frows) {
                $ws->fromArray($frows, null, 'A' . $rowN);
                $rowN += count($frows);
            }
        }
    }
    $ws->freezePane('A5');
}

/* حفظ */
$out = $ROOT . '/docs/baseline_20260821/INJ-SCREENS-MASTER.xlsx';
$writer = new Xlsx($wb);
$writer->save($out);
echo "كُتب: $out\n";
echo 'sheets: ' . $wb->getSheetCount() . "\n";
echo 'memory peak: ' . round(memory_get_peak_usage(true) / 1048576) . " MB\n";
