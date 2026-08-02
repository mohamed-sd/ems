<?php
/**
 * tools/sec01_deliverables.php — مولّد مخرَجات الموجة ⑤ (SEC-01→SEC-05)
 * ───────────────────────────────────────────────────────────────────────────
 * SEC-01 §9: «يُستخرجان من النظام الحي لا يُخترعان». يولّد إلى docs/sec01/:
 *   D1 قاموس المسميات (مسودة معلَّمة — DEC-SEC-K غائب فكل صف مستنتَج ★)
 *   D2 مصفوفة (208 موديل × 25 دورًا) بالأفعال الـ16 والنطاقات الـ9 (CSV + ملخص)
 *   D3 خريطة الأدوار الخمسة والعشرين (§11.4)
 *   D4 مصفوفة المصادر السبعة عشر (§11.3) بقياس حي
 *   D5 مصالحة الشاشات 229/208 صفًّا صفًّا (CSV + تقرير)
 * التشغيل: php tools/sec01_deliverables.php
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/includes/env.php';

$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, "connect failed\n"); exit(1); }
$db->set_charset('utf8mb4');
$OUT = dirname(__DIR__) . '/docs/sec01';
if (!is_dir($OUT)) { mkdir($OUT, 0777, true); }
$today = $db->query('SELECT CURDATE() d')->fetch_assoc()['d'];

// ═══ العائلات الثلاث عشرة (SEC-01 §2② · DEC-SEC-F) ═══
$FAMILIES = array(
    'ops' => 'التشغيل', 'maintenance' => 'الصيانة', 'operators' => 'المشغّلون',
    'procurement' => 'المشتريات', 'warehouse' => 'المخازن', 'transport' => 'النقل والترحيل',
    'finance' => 'المالية', 'hr' => 'الموارد البشرية', 'sales' => 'المبيعات',
    'financing' => 'التمويل', 'fleet' => 'الأسطول', 'governance' => 'الحوكمة والامتثال',
    'tickets' => 'الرصد والبلاغات',
);

// ═══ D1 · قاموس المسميات — من job_titles(16) وemployee_roles(9) والأدوار ═══
// كل استنتاج معلَّم ★ (حكم DEC-SEC-K: مسودة من النظام الحي تُعرض للاعتماد)
$titleInference = array(
    // id => [family, level, وحدة, ملاحظة]
    1 => array('ops', 'dept_mgr', 'ops', 'المسمى «مدير» عام — يُفصَّل عند الاعتماد'),
    2 => array('maintenance', 'executor', 'maintenance', 'مهندس — عائلته بحسب إدارته الفعلية'),
    3 => array('maintenance', 'executor', 'maintenance', 'فني'),
    4 => array('maintenance', 'executor', 'maintenance', 'كهربائي'),
    5 => array('ops', 'officer', 'movement', 'مراقب — مسؤول متابعة ميداني'),
    6 => array('operators', 'executor', 'operators', 'عامل مساندة'),
    7 => array('operators', 'executor', 'operators', 'سائق (مشغّل is_operator=1)'),
    8 => array('operators', 'executor', 'operators', 'مشغّل'),
    9 => array('operators', 'executor', 'operators', 'سائق/مشغّل'),
    10 => array('operators', 'executor', 'operators', 'مساعد'),
    11 => array('operators', 'executor', 'operators', 'مبنشر'),
    12 => array('ops', 'supervisor', 'ops', 'مشرف'),
    13 => array('hr', 'executor', 'hr', 'إداري'),
    14 => array('maintenance', 'executor', 'maintenance', 'فني ورشة'),
    15 => array('ops', 'executor', 'movement', 'أمن — يرتبط بضابط البوابة (ORG-01)'),
    16 => array('ops', 'executor', 'ops', '«أخرى» — صفٌّ يُصفّى عند الاعتماد'),
);
$LEVELS = array('executor' => 'منفِّذ', 'officer' => 'مسؤول', 'supervisor' => 'مشرف',
    'unit_head' => 'رئيس وحدة', 'section_mgr' => 'مدير قسم', 'dept_mgr' => 'مدير إدارة', 'executive' => 'مدير تنفيذي');

$md = "# SEC-D1 · قاموس المسميات الكامل — **مسودة مستخرجة من النظام الحي**\n\n";
$md .= "> **علامة ★ = صف/قيمة مستنتَجة تنتظر اعتماد المالك (`DEC-SEC-K`)** — الوصف الوظيفي المعتمد لم يصل، فبُنيت المسودة من `job_titles` (16) و`employee_roles` (9) و`employees` والأدوار الـ25 (SEC-01 §9 والبرومت §4).\n>\n> حُرِّر: {$today} · المصدر الحي: `equipation_manage`\n\n";
$md .= "## العائلات الثلاث عشرة (DEC-SEC-F — قاموس `hr_dictionaries` layer=family)\n\n|#|الكود|العائلة|\n|---|---|---|\n";
$i = 0;
foreach ($FAMILIES as $c => $n) { $i++; $md .= "|{$i}|`{$c}`|{$n}|\n"; }
$md .= "\n**العائلة ليست الإدارة** — قد تخدم عائلة أكثر من إدارة (الحركة والتشغيل من عائلة التشغيل)، **ولا موظف بلا عائلة**.\n\n";
$md .= "## المسميات الستة عشر القائمة (`job_titles` — تُوسَّع ولا تُنشأ باسم بديل · DEC-SEC-H)\n\n";
$md .= "| الكود | الاسم | العائلة ★ | المستوى ★ | الوحدة ★ | مشغّل؟ | عدد الموظفين | ملاحظة الاستنتاج |\n|---|---|---|---|---|---|---|---|\n";
$counts = array();
$r = $db->query("SELECT job_title_id, COUNT(*) c FROM employees GROUP BY job_title_id");
while ($r && ($x = $r->fetch_assoc())) { $counts[intval($x['job_title_id'])] = intval($x['c']); }
$r = $db->query("SELECT id, name, is_operator FROM job_titles ORDER BY id");
while ($r && ($x = $r->fetch_assoc())) {
    $id = intval($x['id']);
    $inf = isset($titleInference[$id]) ? $titleInference[$id] : array('ops', 'executor', 'ops', '');
    $md .= "| JT-" . str_pad((string) $id, 2, '0', STR_PAD_LEFT) . " | {$x['name']} | ★" . $FAMILIES[$inf[0]]
        . " | ★" . $LEVELS[$inf[1]] . " | ★{$inf[2]} | " . (intval($x['is_operator']) ? 'نعم' : 'لا')
        . " | " . (isset($counts[$id]) ? $counts[$id] : 0) . " | ★{$inf[3]} |\n";
}
$md .= "\n## مسميات مقترحة ناقصة ★ (تظهر في الأدوار الـ25 ولا صف لها في القاموس)\n\n";
$md .= "| المسمى المقترح | العائلة ★ | المستوى ★ | مصدر الاستنتاج |\n|---|---|---|---|\n";
foreach (array(
    array('محاسب', 'finance', 'executor', 'الدور 18'),
    array('مدير الإدارة المالية', 'finance', 'dept_mgr', 'الدور 19'),
    array('المدير المالي', 'finance', 'dept_mgr', 'الدور 17'),
    array('المراجع والمدقق المالي', 'finance', 'officer', 'الدور 20 — وظيفة رقابية مستقلة'),
    array('أمين الخزينة', 'finance', 'officer', 'الدور 21'),
    array('أمين مخزن', 'warehouse', 'officer', 'الدور 25 — إدارة عليا اليوم ودور داخل المخازن في التصميم (§11.6③)'),
    array('مسؤول مشتريات', 'procurement', 'officer', 'الدور 16'),
    array('مدير مبيعات', 'sales', 'dept_mgr', 'الدور 12'),
    array('مدير تمويل', 'financing', 'dept_mgr', 'الدور 26'),
    array('مدير نقل وترحيل', 'transport', 'dept_mgr', 'الدور 23'),
    array('مدير حركة وتشغيل', 'ops', 'section_mgr', 'الدور 6 — تركيب: مسمًّى × مستوى × نطاق موقع + تكليف (SEC-01 §3)'),
    array('مسؤول حوكمة وصلاحيات', 'governance', 'officer', 'الدور 15 — مجال رقابي'),
    array('مدير بلاغات', 'tickets', 'officer', 'الدور 24 — مركز رصد'),
) as $p) {
    $md .= "| ★{$p[0]} | " . $FAMILIES[$p[1]] . " | " . $LEVELS[$p[2]] . " | {$p[3]} |\n";
}
$md .= "\n> **حقول القاموس الكاملة** (الوصف · المهام · المدير المباشر · التبعيتان · القالب · الشاشات · السقوف · النطاقات · المحظورات · المؤهلات) **تُعبَّأ عند وصول الأوصاف المعتمدة** — والبنية جاهزة في `job_titles` الموسَّع (الموجة ⑥).\n";
file_put_contents($OUT . '/SEC-D1_dictionary_draft_ar.md', $md);
echo "D1 ✔\n";

// ═══ D2 · المصفوفة 208×25 — الرايات الأربع → الأفعال الـ16 والنطاقات ═══
$ACTIONS16 = array('screen_view', 'tab_view', 'field_view', 'create', 'update', 'submit',
    'return_for_fix', 'approve', 'reject', 'cancel', 'reverse', 'delete_draft',
    'export', 'print', 'grant_permission', 'override_cap');
$roles = array();
$r = $db->query("SELECT id, name, role_scope FROM roles ORDER BY id");
while ($r && ($x = $r->fetch_assoc())) { $roles[intval($x['id'])] = $x; }
$mods = array();
$r = $db->query("SELECT id, name, code FROM modules ORDER BY id");
while ($r && ($x = $r->fetch_assoc())) { $mods[intval($x['id'])] = $x; }
$perms = array();
$r = $db->query("SELECT role_id, module_id, can_view, can_add, can_edit, can_delete FROM role_permissions");
while ($r && ($x = $r->fetch_assoc())) { $perms[intval($x['role_id']) . ':' . intval($x['module_id'])] = $x; }

$csv = fopen($OUT . '/SEC-D2_matrix_208x25.csv', 'w');
fwrite($csv, "\xEF\xBB\xBF"); // BOM لبرامج الجداول
fputcsv($csv, array_merge(array('role_id', 'role_name', 'module_id', 'module_code', 'module_name'), $ACTIONS16, array('scope_proposed', 'derivation')));
$granted = 0;
foreach ($roles as $rid => $role) {
    // النطاق المقترح: mine → موقع/مشروع صاحبه · gloable → شركة (★ يُدقق في الاعتماد)
    $scope = $role['role_scope'] === 'mine' ? 'site' : 'company';
    foreach ($mods as $mid => $m) {
        $p = isset($perms[$rid . ':' . $mid]) ? $perms[$rid . ':' . $mid] : null;
        $v = $p ? intval($p['can_view']) : 0;
        $a = $p ? intval($p['can_add']) : 0;
        $e = $p ? intval($p['can_edit']) : 0;
        $d = $p ? intval($p['can_delete']) : 0;
        if ($v || $a || $e || $d) { $granted++; }
        // قاعدة التحويل المعلنة (SEC-01 §11.3-①): عرض→(شاشة+تبويب+حقل+تصدير+طباعة) ·
        // إضافة→(إنشاء+إرسال) · تعديل→(تعديل+إعادة للتصحيح) · حذف→حذف مسودة فقط ·
        // والاعتماد/الرفض/الإلغاء/العكس/المنح/تجاوز السقف = 0 حتى تُعتمد القوالب — لا تُستنتج من راية.
        $row = array($rid, $role['name'], $mid, $m['code'], $m['name'],
            $v, $v, $v, $a, $e, $a, $e, 0, 0, 0, 0, $d, $v, $v, 0, 0,
            ($v || $a || $e || $d) ? $scope : '',
            $p ? 'derived_from_4flags' : 'no_grant');
        fputcsv($csv, $row);
    }
}
fclose($csv);
$sum = "# SEC-D2 · مصفوفة الشاشات والأفعال (" . count($mods) . " × " . count($roles) . ") — مسودة التحويل\n\n"
    . "> CSV كامل: `SEC-D2_matrix_208x25.csv` — صف لكل (دور × موديول) = " . (count($mods) * count($roles)) . " صفًّا، منها **{$granted}** بمنح قائم (role_permissions=1008 بالرايات الأربع).\n\n"
    . "## قاعدة التحويل المعلنة (وكلها ★ تنتظر الاعتماد)\n\n"
    . "| الراية القديمة | الأفعال الستة عشر المشتقة |\n|---|---|\n"
    . "| can_view | screen_view · tab_view · field_view · export · print |\n"
    . "| can_add | create · submit |\n"
    . "| can_edit | update · return_for_fix |\n"
    . "| can_delete | **delete_draft فقط** — وحذف ذي الأثر ممنوع بحارس never (§11.5) |\n"
    . "| — | approve · reject · cancel · reverse · grant_permission · override_cap = **لا تُشتق من راية** — تُعبَّأ من القوالب المعتمدة |\n\n"
    . "## النطاقات التسعة\n\nشركة · إدارة · قسم · وحدة · مشروع · موقع · مجموعة مواقع · وردية · سجلاته هو —\n"
    . "الاقتراح الآلي: `role_scope=mine` → **site** و`gloable` → **company** (★ يُضبط دورًا دورًا عند الاعتماد).\n";
file_put_contents($OUT . '/SEC-D2_matrix_summary_ar.md', $sum);
echo "D2 ✔ (" . (count($mods) * count($roles)) . " صف)\n";

// ═══ D3 · خريطة الأدوار الخمسة والعشرين (§11.4) ═══
$roleMap = array(
    1 => array('التشغيل', 'مدير إدارة', 'مدير التشغيل', 'قالب مدير إدارة×تشغيل', 'ترحيل'),
    2 => array('المشتريات/الموردون', 'مدير إدارة', 'مدير الموردين', 'قالب مدير إدارة×مشتريات', 'ترحيل'),
    3 => array('الأسطول', 'مدير إدارة', 'مدير الأسطول', 'قالب مدير إدارة×أسطول', 'ترحيل'),
    4 => array('الموارد البشرية', 'مدير إدارة', 'مدير الموارد البشرية', 'قالب مدير إدارة×موارد', 'ترحيل — التبعية لمدير التشغيل إداريًّا باستقلال فني (DEC-ORG-A)'),
    5 => array('الحركة والتشغيل', '—', '—', '—', '★دمج مع الدور 6 — DEC-NAV-E يحسم أيهما يبقى (الموجة ⑮)'),
    6 => array('الحركة والتشغيل', 'مدير قسم', 'مدير حركة وتشغيل', 'قالب مدير قسم×تشغيل + تكليف site_manager', 'ترحيل — تركيبٌ لا درجة (SEC-01 §3)'),
    7 => array('التشغيل', 'مشرف', 'مشرف مشاريع', 'قالب مشرف×تشغيل', 'ترحيل'),
    8 => array('المشتريات/الموردون', 'مشرف', 'مشرف موردين', 'قالب مشرف×مشتريات', 'ترحيل — 40ع/22إ/24ت/20ح يُدقق (§11.5)'),
    10 => array('الأسطول', 'مشرف', 'مشرف أسطول', 'قالب مشرف×أسطول', 'ترحيل'),
    11 => array('الأسطول', 'منفِّذ', 'مشغل أسطول', 'قالب منفذ×أسطول', 'ترحيل — 39ع/26إ يُدقق (§11.5)'),
    12 => array('المبيعات', 'مدير إدارة', 'مدير المبيعات', 'قالب مدير إدارة×مبيعات', 'ترحيل — 49 صلاحية حذف تُصنَّف صفًّا صفًّا قبل النقل'),
    13 => array('الصيانة', 'مدير إدارة', 'مدير الصيانة', 'قالب مدير إدارة×صيانة', 'ترحيل'),
    14 => array('الصيانة', 'مشرف', 'مشرف صيانة', 'قالب مشرف×صيانة', 'ترحيل'),
    15 => array('الحوكمة', '—', 'مسؤول الحوكمة', 'قوالب الحوكمة', '★مجال رقابي لا إدارة تنفيذية (§11.6③)'),
    16 => array('المشتريات التشغيلية', 'مسؤول', 'مسؤول مشتريات', 'قالب مسؤول×مشتريات', 'ترحيل'),
    17 => array('المالية', 'مدير إدارة', 'المدير المالي', 'قالب مدير إدارة×مالية', 'ترحيل'),
    18 => array('المالية', 'منفِّذ', 'محاسب', 'قالب منفذ×مالية', 'ترحيل'),
    19 => array('المالية', 'مدير قسم', 'مدير الإدارة المالية', 'قالب مدير قسم×مالية', 'ترحيل'),
    20 => array('المالية', 'مسؤول', 'المراجع والمدقق', 'قالب رقابي×مالية', 'ترحيل — وظيفة رقابية مستقلة لا تُدمج'),
    21 => array('المالية', 'مسؤول', 'أمين الخزينة', 'قالب أمين خزينة', 'ترحيل — فصل واجبات عن القيد'),
    22 => array('المالية', 'منفِّذ', 'قارئ مالي', 'قالب قراءة×مالية', 'ترحيل'),
    23 => array('النقل والترحيل', 'مدير إدارة', 'مدير النقل', 'قالب مدير إدارة×نقل', 'ترحيل'),
    24 => array('مركز البلاغات', '—', 'مدير البلاغات', 'قوالب الرصد', '★مركز رصد لا إدارة تنفيذية'),
    25 => array('المخازن', 'مسؤول', 'أمين مخزن', 'قالب مسؤول×مخازن', '★تفكيك — إدارة عليا اليوم ودور داخل المخازن في التصميم (§11.6③)'),
    26 => array('التمويل والملكية', 'مدير إدارة', 'مدير التمويل', 'قالب مدير إدارة×تمويل + sensitive_access_grants', 'ترحيل — منح ownership.* تُحفظ وتُصنَّف (§11.3-⑤)'),
);
$md = "# SEC-D3 · خريطة الأدوار الخمسة والعشرين (SEC-01 §11.4) — **ولا صف بلا قرار**\n\n"
    . "> ★ = قرار مستنتَج ينتظر الاعتماد · حُرِّر {$today}\n\n"
    . "| # | الدور القديم | الإدارة الجديدة | المستوى | المسمّى | القالب | القرار |\n|---|---|---|---|---|---|---|\n";
foreach ($roles as $rid => $role) {
    $m = isset($roleMap[$rid]) ? $roleMap[$rid] : array('؟', '؟', '؟', '؟', '★يُحسم');
    $md .= "| {$rid} | {$role['name']} | {$m[0]} | {$m[1]} | {$m[2]} | {$m[3]} | {$m[4]} |\n";
}
$md .= "\n**ملاحظة:** الدور -1 (الأدمن الأعلى) خارج الخمسة والعشرين — حسابُ منصةٍ لا دورُ أعمال (§11.2-⑦).\n";
file_put_contents($OUT . '/SEC-D3_roles_map_ar.md', $md);
echo "D3 ✔\n";

// ═══ D4 · مصفوفة المصادر السبعة عشر (§11.3) بقياس حي ═══
$q1 = function ($sql) use ($db) { $r = $db->query($sql); if (!$r) { return 'n/a'; } $x = $r->fetch_row(); return $x ? $x[0] : 'n/a'; };
$sources = array(
    array('role_permissions', $q1("SELECT COUNT(*) FROM role_permissions") . ' صف', 'template_permissions بأبعادها الأربعة', 'تحويل الرايات الأربع إلى الستة عشر — قاعدة التحويل في SEC-D2'),
    array('report_role_permissions', $q1("SELECT COUNT(*) FROM report_role_permissions") . ' صف', 'مصفوفة الشاشات', 'التقرير شاشةٌ لها صلاحيتها لا قناة ثانية'),
    array('nav_items + link_groups', $q1("SELECT COUNT(*) FROM nav_items") . ' + ' . $q1("SELECT COUNT(*) FROM link_groups"), 'NAV-01 المجموعات الثماني', 'إعادة تصنيف لا إعادة بناء (البوابة ④)'),
    array('positions (خامل)', $q1("SELECT COUNT(*) FROM positions") . ' صف', 'جسر المركز الوظيفي', 'يطابق §11.3-④ حرفيًّا: جسرٌ لا جدولٌ موازٍ — person_positions تبنى فوقه'),
    array('منح ownership.* الفردية', $q1("SELECT COUNT(*) FROM role_permissions rp JOIN modules m ON m.id=rp.module_id WHERE m.code LIKE '%ownership%' OR m.code LIKE '%financing%'") . ' صف تقريبي', 'sensitive_access_grants', 'تُحفظ وتُصنَّف ولا تُسقَط (§1.1-② · قرار PLAN-05 DEC-01)'),
    array('قوائم السماح الصلبة داخل الشاشات', 'غير معدودة آليًّا', 'سياسات الحقول الحساسة والقوالب والحراس', 'تُنقل ولا تبقى داخل ملفات — مهمة البوابة ② المستمرة'),
    array('users.role', $q1("SELECT COUNT(DISTINCT role) FROM users") . ' قيمة مستعملة', 'طبقة توافق', 'تُقرأ ولا تُكتب ثم تُتقاعد بمراحل §13'),
    array('users.role_id', $q1("SELECT COUNT(*) FROM users WHERE role_id IS NOT NULL") . ' صف', 'تقاعد فوري', 'يُكتب ولا يُقرأ — عمود أثري'),
    array('users.parent_id', $q1("SELECT COUNT(*) FROM users WHERE parent_id IS NOT NULL") . ' صف', 'org_assignments + reporting_lines', 'علاقة ترحيل ثم تُتقاعد — البنية جاهزة (الموجة ①)'),
    array('supplier_entity_id', $q1("SELECT COUNT(*) FROM users WHERE supplier_entity_id IS NOT NULL") . ' صف', 'person_positions.scope', 'النطاق بُعدٌ لا عمودُ حساب'),
    array('project_id/contract_id على الحساب', 'أعمدة قائمة', 'scope_rule', 'يُرحَّل ولا يبقى عمود حساب'),
    array('ثوابت roles.php', '26 ثابتًا', 'ثوابت ربط منطقية', 'القيم تُدار من الجداول'),
    array('أعلام التهيئة', $q1("SELECT COUNT(*) FROM governance_flags") . ' علم حوكمة + أعلام .env', 'مراجعة وقلب أو تقاعد', 'لا يبقى علم بلا تاريخ — جرد الأعلام في التقرير الختامي'),
    array('إعفاءات حارس الصفحة', 'داخل الكود', 'guard_override_policies', 'تُوثَّق سياسة معلنة — التوسيع ٩→١٧ (الموجة ⑥)'),
    array('سجل action_guard', 'سجل مركزي حي', 'يبقى ويُوسَّع بالأفعال الـ16', 'لا يُستبدل'),
    array('employees.job_title_id', $q1("SELECT COUNT(*) FROM employees WHERE job_title_id IS NOT NULL") . ' صف', 'job_titles الموسَّع', 'وصف يصير مصدر اشتقاق بعد ربطه بقالب (DEC-SEC-H)'),
    array('employee_roles', $q1("SELECT COUNT(*) FROM employee_roles") . ' صف', 'وصف مهني لا يمنح وصولًا', 'مصدر تصنيف في بناء القاموس'),
);
$md = "# SEC-D4 · مصفوفة المصادر السبعة عشر (SEC-01 §11.3) — بقياس حي {$today}\n\n"
    . "| # | المصدر القائم | القياس الحي | الهدف الجديد | قرار الترحيل |\n|---|---|---|---|---|\n";
$i = 0;
foreach ($sources as $s) { $i++; $md .= "| {$i} | {$s[0]} | {$s[1]} | {$s[2]} | {$s[3]} |\n"; }
$md .= "\n**ولا يكتمل الترحيل حتى يُحسم كل واحد بقرار** — القرارات أعلاه منقولة من الوثيقة ومقيسة، وما علامته ★ في المخرجات الشقيقة ينتظر الاعتماد.\n";
file_put_contents($OUT . '/SEC-D4_sources_17_ar.md', $md);
echo "D4 ✔\n";

// ═══ D5 · مصالحة الشاشات — الملفات الحية × سجل modules ═══
$root = dirname(__DIR__);
$dirs = array('Approvals', 'Contracts', 'Equipments', 'Finance', 'FinRequests', 'Fleet', 'Governance',
    'Maintenance', 'Oprators', 'Operations', 'Procurement', 'Suppliers', 'Tickets', 'Timesheet',
    'Transport', 'Workforce', 'movement', 'admin', 'main', 'Financing', 'Sales', 'clients', 'projects',
    'Employees', 'reports', 'Portal');
$diskScreens = array(); // مفتاح lowercase للمطابقة (نظام ملفات وندوز غير حساس للحالة) وقيمته المسار الفعلي
foreach ($dirs as $d) {
    if (!is_dir($root . '/' . $d)) { continue; }
    foreach (glob($root . '/' . $d . '/*.php') as $f) {
        $rel = $d . '/' . basename($f);
        $diskScreens[strtolower($rel)] = $rel;
    }
}
$modCodes = array(); // مفتاح lowercase أيضًا
foreach ($mods as $m) { if ($m['code']) { $modCodes[strtolower($m['code'])] = $m; } }

$csv = fopen($OUT . '/SEC-D5_screens_reconciliation.csv', 'w');
fwrite($csv, "\xEF\xBB\xBF");
fputcsv($csv, array('path', 'registered_in_modules', 'classification', 'proposed_decision'));
$stats = array('registered' => 0, 'handler' => 0, 'ajax_get' => 0, 'include' => 0, 'unregistered_screen' => 0, 'ghost_module' => 0);
foreach ($diskScreens as $lowRel => $rel) {
    $base = strtolower(basename($rel));
    $registered = isset($modCodes[$lowRel]);
    if ($registered) { $cls = 'شاشة مسجَّلة'; $dec = 'تبقى'; $stats['registered']++; }
    elseif (strpos($base, 'get_') === 0 || strpos($base, 'ajax') !== false) { $cls = 'واجهة AJAX'; $dec = 'ليست شاشة — يغطيها action_guard'; $stats['ajax_get']++; }
    elseif (strpos($base, 'handler') !== false || strpos($base, 'cron') === 0 || strpos($base, 'helper') !== false || strpos($base, '_helpers') !== false) { $cls = 'معالج/كرون/مساعد'; $dec = 'ليست شاشة'; $stats['handler']++; }
    elseif (in_array($base, array('index.php', 'login.php', 'logout.php'), true)) { $cls = 'بنية'; $dec = 'ليست شاشة'; $stats['include']++; }
    else { $cls = '★شاشة غير مسجَّلة محتملة'; $dec = '★تسجيل أو تصنيف — يُحسم قبل قلب الحارس (§11.2-⑧)'; $stats['unregistered_screen']++; }
    fputcsv($csv, array($rel, $registered ? 'Y' : 'N', $cls, $dec));
}
foreach ($modCodes as $lowCode => $m) {
    if (!isset($diskScreens[$lowCode])) {
        fputcsv($csv, array($m['code'], 'Y', '★موديول بلا ملف على القرص', '★أرشفة أو تصحيح مسار'));
        $stats['ghost_module']++;
    }
}
fclose($csv);
$totalDisk = count($diskScreens);
$md = "# SEC-D5 · مصالحة الشاشات صفًّا صفًّا (SEC-01 §11.6-①) — {$today}\n\n"
    . "> «229 موثَّقة مقابل 208 مسجَّلة — الفارق لا يُفترض سببه». الجرد الحي أدناه من القرص ×`modules`، والحسم صفًّا صفًّا في `SEC-D5_screens_reconciliation.csv`.\n\n"
    . "| القياس | العدد |\n|---|---|\n"
    . "| موديولات مسجَّلة في `modules` | " . count($modCodes) . " |\n"
    . "| ملفات PHP في مجلدات الشاشات | {$totalDisk} |\n"
    . "| شاشة مسجَّلة (ملف↔موديول) | {$stats['registered']} |\n"
    . "| واجهات AJAX (get_*/ajax) — يغطيها action_guard | {$stats['ajax_get']} |\n"
    . "| معالجات/كرون/مساعدات — ليست شاشات | {$stats['handler']} |\n"
    . "| بنية (index/login/…) | {$stats['include']} |\n"
    . "| **★شاشات غير مسجَّلة محتملة — تُحسم قبل قلب الحارس** | **{$stats['unregistered_screen']}** |\n"
    . "| **★موديولات بلا ملف (أشباح)** | **{$stats['ghost_module']}** |\n\n"
    . "**قاعدة القبول (§11.2-⑧):** لا يُقلب حارس الفعل إلى الإنفاذ الكامل للشاشات إلا بعد حسم كل صف ★ بقرار.\n";
file_put_contents($OUT . '/SEC-D5_screens_reconciliation_ar.md', $md);
echo "D5 ✔ (قرص {$totalDisk} · مسجل " . count($modCodes) . " · غير مسجل محتمل {$stats['unregistered_screen']} · أشباح {$stats['ghost_module']})\n";

echo "اكتمل التوليد في docs/sec01/\n";
