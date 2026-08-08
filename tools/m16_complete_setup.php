<?php
/**
 * tools/m16_complete_setup.php — إكمالُ تسجيلِ M-16 إلى نطاقِ الوثيقة
 * ───────────────────────────────────────────────────────────────────────────
 * ما تسجّله (كلُّه idempotent — يُعاد تشغيلُه بلا أثرٍ مزدوج):
 *   ① الدورُ ٣٠ «مشرف المخاطر» — قراءةٌ فقط على كلِّ شاشاتِ المخاطرِ العشرين.
 *      لماذا دورٌ مستقلٌّ لا منحةٌ على ٢٩: §9-3 فصلُ الواجبات — المشرفُ يرى
 *      ولا يكتب، ومنحُه can_add على دورِ المحللِ يجمع الواجبين في حسابٍ واحد.
 *   ② الحسابُ والموظفُ والمسمى للدورِ ٣٠ (الثلاثية — عرفُ FIN-26: لا حساب
 *      بلا employee_id، ولا موظفَ بلا مسمًّى وظيفيٍّ حقيقيٍّ · UI-DEF-01).
 *   ③ الشاشاتُ العشرُ الجديدة: وحداتُ صلاحياتٍ + منحٌ + روابطُ تنقلٍ مرحلية
 *      ⇒ العشرون كاملةً (AC-10: صفرُ وجهةٍ بلا وحدةِ صلاحياتٍ مسجَّلة).
 *   ④ الأفعالُ العشرُ الناقصةُ بعقودها السداسيةِ في قاموس NAV-09 (AC-03).
 *   ⑤ الحقولُ الحساسةُ الستُّ في scr_sensitive_fields (AC-06).
 *
 * حسابُ «مخاطر» قائمٌ سلفًا بالكلمةِ الثابتة — لا تُلمَس كلمتُه: تغييرُ كلمةِ
 * حسابِ اختبارٍ يكسر trial_readiness كاذبًا. تُطبَع حالتُه للتأكيد فقط.
 *
 * التشغيل: php tools/m16_complete_setup.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
$m = new mysqli('127.0.0.1', 'root', '', 'equipation_manage', 3307);
if ($m->connect_error) { fwrite(STDERR, $m->connect_error . "\n"); exit(1); }
$m->set_charset('utf8mb4');
$CO = 4;
$out = array();
function say(&$out, $s) { $out[] = $s; }

/* ══ ① الدورُ ٣٠ «مشرف المخاطر» ═══════════════════════════════════════════ */
$st = $m->prepare("INSERT INTO roles (id, name, parent_role_id, level, role_scope, status)
                   SELECT 30, 'مشرف المخاطر', 28, 1, 'gloable', 1 FROM DUAL
                    WHERE NOT EXISTS (SELECT 1 FROM roles WHERE id = 30)");
$st->execute();
say($out, 'الدور 30 «مشرف المخاطر»: ' . ($m->affected_rows ? 'أُنشئ' : 'قائم'));
$st->close();

/* ══ ② الثلاثية: مسمًّى + موظف + حساب ════════════════════════════════════ */
$titleName = 'مشرف مخاطر';
$st = $m->prepare("INSERT INTO job_titles (title_code, company_id, name, org_unit_id, active, status, sort_order)
                   SELECT 'JT_RISK_SUP', 4, ?, 21, 1, 'active', 92 FROM DUAL
                    WHERE NOT EXISTS (SELECT 1 FROM job_titles WHERE company_id = 4 AND name = ?)");
$st->bind_param('ss', $titleName, $titleName);
$st->execute();
$st->close();
$titleId = (int) $m->query("SELECT id FROM job_titles WHERE company_id = 4 AND name = '"
    . $m->real_escape_string($titleName) . "'")->fetch_assoc()['id'];
say($out, "المسمى «{$titleName}»: #$titleId");

$username = 'مشرف مخاطر';
$r = $m->query("SELECT id, employee_id FROM users WHERE username = '" . $m->real_escape_string($username) . "' AND company_id = 4");
if ($u = $r->fetch_assoc()) {
    say($out, "الحساب «{$username}»: قائم #{$u['id']} (لا تُلمس كلمتُه)");
} else {
    $empName = 'مشرف المخاطر';
    $st = $m->prepare("INSERT INTO employees (employee_type, company_id, name, employee_code, job_title_id, status)
                       VALUES ('موظف', 4, ?, CONCAT('RSK-', FLOOR(RAND()*9000)+1000), ?, 1)");
    $st->bind_param('si', $empName, $titleId);
    $st->execute();
    $eid = (int) $m->insert_id;
    $st->close();
    $hash = password_hash('12345678', PASSWORD_BCRYPT);
    $st = $m->prepare("INSERT INTO users (name, username, password, role, company_id, employee_id, status, parent_id)
                       VALUES (?, ?, ?, 30, 4, ?, 'active', 0)");
    $st->bind_param('sssi', $empName, $username, $hash, $eid);
    $st->execute();
    say($out, "الحساب «{$username}»: أُنشئ #" . $m->insert_id . " (موظف $eid · كلمة 12345678)");
    $st->close();
}
/* تأكيدُ حسابَي 28/29 بلا مساسٍ بكلمتيهما */
$r = $m->query("SELECT username, role, status FROM users WHERE username IN ('مخاطر','محلل مخاطر') AND company_id = 4");
while ($x = $r->fetch_assoc()) {
    say($out, "  تأكيد: «{$x['username']}» على الدور {$x['role']} — {$x['status']}");
}

/* ══ ③ الشاشاتُ العشرُ الجديدةُ ⇒ العشرون كاملةً ═════════════════════════ */
$newScreens = array(
    array('وحدات المخاطر والتصنيف', 'Risk/risk_units.php', 'fa fa-sitemap'),
    array('تقييم الخطر ونسخه التاريخية', 'Risk/risk_assessment.php', 'fa fa-chart-simple'),
    array('التحقق من الضوابط الحرجة', 'Risk/risk_control_verify.php', 'fa fa-clipboard-check'),
    array('القبول والاستثناءات', 'Risk/risk_acceptance.php', 'fa fa-file-signature'),
    array('الحوادث والوقائع', 'Risk/risk_incidents.php', 'fa fa-car-burst'),
    array('لجنة المخاطر', 'Risk/risk_committee.php', 'fa fa-users-rectangle'),
    array('تقارير المخاطر والتحليلات', 'Risk/risk_reports.php', 'fa fa-chart-pie'),
    array('إعدادات المخاطر والتصنيف', 'Risk/risk_settings.php', 'fa fa-sliders'),
    array('مخاطر الميدان', 'Risk/risk_field.php', 'fa fa-mobile-screen'),
    array('حوكمة إدارة المخاطر', 'Risk/gov_dept_rsk.php', 'fa fa-scale-unbalanced'),
);
$modIds = array();
$r = $m->query("SELECT id, code FROM modules WHERE code LIKE 'Risk/%'");
while ($x = $r->fetch_assoc()) { $modIds[$x['code']] = (int) $x['id']; }
foreach ($newScreens as $s) {
    if (isset($modIds[$s[1]])) { continue; }
    $st = $m->prepare("INSERT INTO modules (name, code, owner_role_id, is_link, is_quick, icon, display_order)
                       VALUES (?, ?, 28, 1, 0, ?, 510)");
    $st->bind_param('sss', $s[0], $s[1], $s[2]);
    $st->execute();
    $modIds[$s[1]] = (int) $m->insert_id;
    $st->close();
    say($out, "وحدة صلاحيات {$s[1]}: #{$modIds[$s[1]]}");
}
say($out, 'وحدات المخاطر المسجَّلة: ' . count($modIds) . ' (المطلوب 20)');

function grant($m, $roleId, $moduleId, $v, $a, $e, $d) {
    $r = $m->query("SELECT COUNT(*) c FROM role_permissions WHERE role_id = $roleId AND module_id = $moduleId");
    if ((int) $r->fetch_assoc()['c'] > 0) { return false; }
    $m->query("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
               VALUES ($roleId, $moduleId, $v, $a, $e, $d)");
    return true;
}
$g = 0;
foreach ($modIds as $code => $mid) {
    // ٢٨ مديرُ المخاطر: كاملٌ بلا حذف (لا حذفَ في المخاطرِ أصلًا)
    $g += grant($m, 28, $mid, 1, 1, 1, 0) ? 1 : 0;
    // ٢٩ محللُ المخاطر: كاملٌ بلا حذف
    $g += grant($m, 29, $mid, 1, 1, 1, 0) ? 1 : 0;
    // ◆ ٣٠ مشرفُ المخاطر: قراءةٌ فقط على العشرين — لا إضافةَ ولا تعديلَ ولا حذف
    $g += grant($m, 30, $mid, 1, 0, 0, 0) ? 1 : 0;
}
// الرئيسُ التنفيذيُّ (٩): ما يعنيه عرضًا — اللجنةُ والتقاريرُ والحوادثُ والقبول
foreach (array('Risk/risk_committee.php', 'Risk/risk_reports.php', 'Risk/risk_incidents.php',
               'Risk/risk_acceptance.php', 'Risk/risk_assessment.php') as $c) {
    if (isset($modIds[$c])) { $g += grant($m, 9, $modIds[$c], 1, 0, 0, 0) ? 1 : 0; }
}
// الحوكمةُ والالتزامُ (١٥ مديرُ الصلاحيات): شاشةُ حوكمةِ الإدارةِ وسجلُّ التصدير
foreach (array('Risk/gov_dept_rsk.php', 'Risk/risk_reports.php') as $c) {
    if (isset($modIds[$c])) { $g += grant($m, 15, $modIds[$c], 1, 0, 0, 0) ? 1 : 0; }
}
// الإداراتُ الميدانيةُ (الموقعُ ٥/٦ · التشغيلُ ١ · البلاغاتُ ٢٤): الميدانُ والوقائع
foreach (array(1, 5, 6, 24) as $dr) {
    foreach (array('Risk/risk_field.php', 'Risk/risk_incidents.php') as $c) {
        if (isset($modIds[$c])) { $g += grant($m, $dr, $modIds[$c], 1, 1, 0, 0) ? 1 : 0; }
    }
}
say($out, "منحٌ أُضيفت: $g");

/* روابطُ التنقلِ للأدوار ٢٨/٢٩/٣٠ — مجموعاتٌ مرحليةٌ على نمط n9s القائم */
$navPlan = array(
    array(1, 'أولًا: الرصد والفرز', 'الإشارات والوقائع', array(
        array('الحوادث والوقائع', 'Risk/risk_incidents.php'),
        array('مخاطر الميدان', 'Risk/risk_field.php'),
    )),
    array(2, 'ثانيًا: السجل والتصنيف', 'المنهج والتصنيف', array(
        array('وحدات المخاطر والتصنيف', 'Risk/risk_units.php'),
    )),
    array(3, 'ثالثًا: التقييم والتحدي', 'التقييم', array(
        array('تقييم الخطر ونسخه التاريخية', 'Risk/risk_assessment.php'),
    )),
    array(4, 'رابعًا: الضوابط والتحقق', 'التحقق', array(
        array('التحقق من الضوابط الحرجة', 'Risk/risk_control_verify.php'),
    )),
    array(7, 'سابعًا: القبول والتصعيد', 'القبول', array(
        array('القبول والاستثناءات', 'Risk/risk_acceptance.php'),
    )),
    array(8, 'ثامنًا: الحوكمة والإطار', 'الإطار والحوكمة', array(
        array('لجنة المخاطر', 'Risk/risk_committee.php'),
        array('تقارير المخاطر والتحليلات', 'Risk/risk_reports.php'),
        array('إعدادات المخاطر والتصنيف', 'Risk/risk_settings.php'),
        array('حوكمة إدارة المخاطر', 'Risk/gov_dept_rsk.php'),
    )),
);
foreach (array(28, 29, 30) as $roleId) {
    foreach ($navPlan as $i => $stage) {
        list($stageNo, $stageTitle, $gName, $links) = $stage;
        $gCode = "n9s15_{$stageNo}_" . (10 + $i) . "_r{$roleId}";
        $r = $m->query("SELECT id FROM link_groups WHERE group_code = '" . $m->real_escape_string($gCode) . "'");
        if ($x = $r->fetch_assoc()) {
            $gid = (int) $x['id'];
        } else {
            $st = $m->prepare("INSERT INTO link_groups (name, group_code, owner_role_id, icon, display_order, stage_no, stage_title, is_active)
                               VALUES (?, ?, ?, 'fa fa-layer-group', ?, ?, ?, 1)");
            $ord = 10 + $i;
            $st->bind_param('ssiiis', $gName, $gCode, $roleId, $ord, $stageNo, $stageTitle);
            $st->execute();
            $gid = (int) $m->insert_id;
            $st->close();
        }
        $so = 0;
        foreach ($links as $lk) {
            $so++;
            $r = $m->query("SELECT id FROM nav_items WHERE role_id = $roleId AND route = '" . $m->real_escape_string($lk[1]) . "'");
            if ($r->num_rows > 0) { continue; }
            $mid = isset($modIds[$lk[1]]) ? $modIds[$lk[1]] : null;
            $st = $m->prepare("INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, active)
                               VALUES (?, 'RISK', ?, ?, ?, ?, 'fa fa-circle-dot', ?, 1)");
            $st->bind_param('iiissi', $roleId, $gid, $mid, $lk[0], $lk[1], $so);
            $st->execute();
            $st->close();
        }
    }
}
/* الدورُ ٣٠ يحتاج روابطَ العشرِ القديمةِ أيضًا — يرى كلَّ شيءٍ ولا يكتب */
$oldRoutes = array(
    array('لوحة المخاطر العليا', 'Risk/risk_board.php', 0, 'لوحة الإدارة'),
    array('إشارات الخطر والفرز', 'Risk/risk_signals.php', 1, 'أولًا: الرصد والفرز'),
    array('سجل المخاطر المركزي', 'Risk/risk_register.php', 2, 'ثانيًا: السجل والتصنيف'),
    array('الضوابط والضوابط الحرجة', 'Risk/risk_controls.php', 4, 'رابعًا: الضوابط والتحقق'),
    array('مؤشرات الخطر الرئيسة', 'Risk/risk_kris.php', 5, 'خامسًا: المراقبة والمؤشرات'),
    array('إجراءات معالجة المخاطر', 'Risk/risk_treatments.php', 6, 'سادسًا: المعالجة'),
    array('مراجعات المخاطر وقراراتها', 'Risk/risk_reviews.php', 7, 'سابعًا: القبول والتصعيد'),
    array('شهية المخاطر وحدودها', 'Risk/risk_appetite.php', 8, 'ثامنًا: الحوكمة والإطار'),
    array('مساحة مخاطر الإدارة', 'Risk/dept_risk_space.php', 0, 'لوحة الإدارة'),
);
foreach ($oldRoutes as $j => $lk) {
    $r = $m->query("SELECT id FROM nav_items WHERE role_id = 30 AND route = '" . $m->real_escape_string($lk[1]) . "'");
    if ($r->num_rows > 0) { continue; }
    $gCode = "n9s15_{$lk[2]}_sup_r30";
    $r = $m->query("SELECT id FROM link_groups WHERE group_code = '" . $m->real_escape_string($gCode) . "'");
    if ($x = $r->fetch_assoc()) { $gid = (int) $x['id']; }
    else {
        $st = $m->prepare("INSERT INTO link_groups (name, group_code, owner_role_id, icon, display_order, stage_no, stage_title, is_active)
                           VALUES (?, ?, 30, 'fa fa-layer-group', ?, ?, ?, 1)");
        $nm = $lk[3]; $ord = $lk[2] + 1; $sn = $lk[2]; $stt = $lk[3];
        $st->bind_param('ssiis', $nm, $gCode, $ord, $sn, $stt);
        $st->execute();
        $gid = (int) $m->insert_id;
        $st->close();
    }
    $mid = isset($modIds[$lk[1]]) ? $modIds[$lk[1]] : null;
    $st = $m->prepare("INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, active)
                       VALUES (30, 'RISK', ?, ?, ?, ?, 'fa fa-circle-dot', ?, 1)");
    $so = $j + 1;
    $st->bind_param('iissi', $gid, $mid, $lk[0], $lk[1], $so);
    $st->execute();
    $st->close();
}
$n30 = (int) $m->query("SELECT COUNT(DISTINCT route) c FROM nav_items WHERE role_id = 30 AND route LIKE 'Risk/%'")->fetch_assoc()['c'];
say($out, "روابط الدور 30 (قراءة فقط): $n30 وجهة");

/* ══ ④ الأفعالُ العشرُ الناقصةُ بعقودها السداسية ═════════════════════════ */
$actions = array(
    array('RSK-CTL-FAIL', 'تسجيل فشل ضابط حرج', 'الضوابط والضوابط الحرجة', 'Risk/risk_actions.php',
        'المتحقق أو النظام', 'risk_controls,risk_escalations,risk_signals', 'CriticalControlFailed',
        'الرئيس التنفيذي والمخاطر والإدارة المالكة', 'تصعيد فوري في اليوم نفسه ورفع الخطر المتبقي',
        'لا يُعكس — يُغلق بإجراء تصحيحي متحقَّق منه', 'domain_write'),
    array('RSK-REVIEW', 'مراجعة دورية للخطر', 'مراجعات المخاطر وقراراتها', 'Risk/risk_actions.php',
        'محلل المخاطر', 'risk_reviews,risk_register', 'RiskReviewed', 'الإدارة المالكة',
        'قرار استمرار أو إغلاق أو تصعيد بشاهده', 'مراجعة جديدة تحفظ السابقة', 'domain_write'),
    array('RSK-INCIDENT-LOG', 'تسجيل واقعة', 'الحوادث والوقائع', 'Risk/risk_actions.php',
        'الموقع أو مركز البلاغات', 'risk_incidents,risk_signals', 'IncidentLogged',
        'المخاطر والسلامة والإدارة المعنية', 'واقعة مسجَّلة — وإن حققت خطرًا قائمًا أُعيد تقييمه',
        'تصحيح التسجيل بمرجع لا حذفًا', 'domain_write'),
    array('RSK-APPETITE-SET', 'اعتماد شهية المخاطر', 'شهية المخاطر وحدودها', 'Risk/risk_actions.php',
        'الرئيس التنفيذي حصرًا', 'risk_appetite', 'RiskAppetiteSet', 'المنصة كلها',
        'الشهية المعتمدة تغيّر حدود التصعيد في المجالات كلها', 'اعتماد شهية جديدة يحفظ السابقة', 'domain_write'),
    array('RSK-TAXONOMY-DEFINE', 'تعريف تصنيف المخاطر', 'إعدادات المخاطر والتصنيف', 'Risk/risk_actions.php',
        'مدير المخاطر', 'risk_units', 'RiskTaxonomyDefined', 'المنصة كلها',
        'التصنيف المعتمد يحكم تسجيل المخاطر كلها', 'تعديل التصنيف بترحيل لا بحذف', 'domain_write'),
    array('RSK-KRI-THRESHOLD', 'ضبط حد مؤشر خطر', 'مؤشرات الخطر الرئيسة', 'Risk/risk_actions.php',
        'مدير المخاطر', 'risk_kris', 'KRIThresholdSet', 'الإدارات المعنية',
        'الحدود الجديدة تسري على القراءات القادمة لا الماضية', 'إعادة الحد السابق بقرار', 'domain_write'),
    array('RSK-REPORT-EXPORT', 'تصدير تقرير مخاطر', 'تقارير المخاطر والتحليلات', 'Risk/risk_actions.php',
        'مدير المخاطر ومن يخوّله', 'risk_export_log', 'RiskReportExported', 'الحوكمة',
        'سجل تصدير بتسعة بنود — والحقول الحساسة محجوبة بالصلاحية', 'لا عكس له بطبيعته', 'governance_write'),
    array('RSK-FIELD-SYNC', 'مزامنة الإشارات المعلَّقة', 'مخاطر الميدان', 'Risk/risk_actions.php',
        'المُبلِّغ الميداني المخوَّل', 'risk_signals', 'RiskSignalsSynced', 'إدارة المخاطر',
        'المعلَّق يُرفع بمفتاحه — والإعادة ترجع مرجع الأول ولا تُنشئ ثانيًا', 'لا عكس له بطبيعته', 'domain_write'),
    array('RSK-VIEW-FIELD', 'عرض مخاطر الميدان', 'مخاطر الميدان', 'Risk/risk_field.php',
        'المُبلِّغ الميداني المخوَّل', '-', '-', '-', 'قراءة ميدانية بنطاق موقعه', '-', 'read_only'),
    array('GOV-RSK-ATTEST', 'تصديق مراجعة وصول في إدارة المخاطر', 'حوكمة إدارة المخاطر', 'Risk/risk_actions.php',
        'مدير الإدارة', 'ems_business_events', 'AccessReviewAttested', 'الحوكمة والالتزام',
        'المدير يصدّق على قائمة فريقه — والتصديق لا يمنح صلاحية بل يشهد بصحتها',
        'سحب التصديق بسبب', 'governance_write'),
    array('GOV-RSK-VIEW', 'عرض حوكمة إدارة المخاطر', 'حوكمة إدارة المخاطر', 'Risk/gov_dept_rsk.php',
        'مدير الإدارة', '-', '-', '-', 'عرض حوكمة الإدارة بنطاقها — قراءة لا كتابة', '-', 'read_only'),
    array('RSK-VIEW-COMMITTEE', 'عرض محاضر لجنة المخاطر', 'لجنة المخاطر', 'Risk/risk_committee.php',
        'الرئيس ومدير المخاطر', '-', '-', '-', 'قراءة محاضر واعتماداتها', '-', 'read_only'),
);
$insA = $m->prepare("INSERT INTO nav09_action_map
    (canonical_code, label_ar, screen_title, canonical_file, actor_ar, writes_text, event_name,
     consumers_text, effect_text, reverse_text, live_code, state, write_class)
    SELECT ?,?,?,?,?,?,?,?,?,?,?, 'bound', ? FROM DUAL
     WHERE NOT EXISTS (SELECT 1 FROM nav09_action_map WHERE canonical_code = ?)");
$an = 0;
foreach ($actions as $a) {
    $insA->bind_param('sssssssssssss', $a[0], $a[1], $a[2], $a[3], $a[4], $a[5], $a[6],
        $a[7], $a[8], $a[9], $a[0], $a[10], $a[0]);
    $insA->execute();
    $an += $m->affected_rows > 0 ? 1 : 0;
}
$insA->close();
$totalA = (int) $m->query("SELECT COUNT(*) c FROM nav09_action_map
                            WHERE canonical_code LIKE 'RSK-%' OR canonical_code LIKE 'GOV-RSK-%'")->fetch_assoc()['c'];
say($out, "أفعال سُجِّلت الآن: $an · إجمالي أفعال M-16 في القاموس: $totalA");

/* ══ ⑤ الحقولُ الحساسةُ الستُّ (AC-06) ═══════════════════════════════════ */
$sens = array(
    array('risk_register', 'owner_unit_id', 'مقيّد — الإدارة المالكة',
        'كشفُ الإدارةِ المالكةِ لخطرٍ حرجٍ لغيرِ المخوَّلِ يوجّه اللومَ قبل التحقق',
        'إدارة المخاطر والرئيس التنفيذي والإدارة المالكة نفسها'),
    array('risk_register', 'risk_owner_user_id', 'مقيّد — مالك الخطر',
        'ربطُ شخصٍ بعينِه بخطرٍ حرجٍ قبل اعتمادِ التقييمِ يضرُّ بلا مسوِّغ',
        'إدارة المخاطر والرئيس التنفيذي'),
    array('risk_controls', 'owner_user_id', 'مقيّد — مالك الضابط',
        'مالكُ الضابطِ الفاشلِ يُستهدَف قبل تحققِ المتحقِّقِ المستقل',
        'إدارة المخاطر والمتحقق المستقل'),
    array('risk_kris', 'current_value', 'مقيّد — قيمة مؤشر',
        'قيمُ المؤشراتِ تكشف موقفَ الشركةِ التشغيليَّ والماليَّ مجموعةً',
        'إدارة المخاطر والرئيس التنفيذي والإدارة المعنية'),
    array('risk_treatments', 'plan_ar', 'مقيّد — تكلفة المعالجة المقدَّرة',
        'خطةُ المعالجةِ تحمل تقديرَ تكلفةٍ يكشف موقفَ التفاوض',
        'إدارة المخاطر ومالك الخطر ومسؤول المعالجة'),
    array('risk_export_log', 'blocked_text', 'مقيّد — الحسابات التابعة',
        'سجلُّ ما حُجب يكشف خريطةَ الحقولِ الحساسةِ لمن لا يملكها',
        'الحوكمة والالتزام ومدير الصلاحيات'),
);
$maxNo = (int) preg_replace('~\D~', '', (string) $m->query("SELECT COALESCE(MAX(no_policy),'SEN-000') n FROM scr_sensitive_fields")->fetch_assoc()['n']);
$sn = 0;
foreach ($sens as $s) {
    $r = $m->query("SELECT id FROM scr_sensitive_fields WHERE company_id = 4
                     AND table_name = '" . $m->real_escape_string($s[0]) . "'
                     AND field_name = '" . $m->real_escape_string($s[1]) . "'");
    if ($r->num_rows > 0) { continue; }
    $maxNo++;
    $no = 'SEN-' . str_pad((string) $maxNo, 3, '0', STR_PAD_LEFT);
    $st = $m->prepare("INSERT INTO scr_sensitive_fields
        (company_id, no_policy, table_name, field_name, classification_sensitivity, reason_classification,
         from_visible_to, policy_masking, log_views_flag, exportable_flag, basis_statutory, date_effective,
         approver_name, status_label, status, is_seed, created_by, created_by_name)
        VALUES (4, ?, ?, ?, ?, ?, ?, 'يظهر «•••» لغير المخول', 'نعم', 'لا',
                'M-16 §6-3 · §9-4 سجل الاطّلاع', CURDATE(), 'إكمال M-16 — 2026-08-08',
                'معتمد', 'معتمد', 0, 0, 'مكتب هندسة النظم')");
    $st->bind_param('ssssss', $no, $s[0], $s[1], $s[2], $s[3], $s[4]);
    $st->execute();
    $st->close();
    $sn++;
}
$totalS = (int) $m->query("SELECT COUNT(*) c FROM scr_sensitive_fields WHERE table_name LIKE 'risk%'")->fetch_assoc()['c'];
say($out, "حقول حساسة سُجِّلت الآن: $sn · إجمالي حقول المخاطر الحساسة: $totalS (المطلوب 6)");

/* ══ ⑥ مفاتيحُ القراءةِ الآليةِ على المؤشراتِ العشرة الأولى ═════════════ */
$readers = array(
    'المخاطر الحرجة المفتوحة' => array('open_critical_risks', 1, 3, 'تصاعدي'),
    'الإشارات المعلقة بلا فرز' => array('pending_signals', 10, 25, 'تصاعدي'),
    'إجراءات معالجة متأخرة' => array('overdue_treatments', 3, 8, 'تصاعدي'),
    'ضوابط غير فعالة أو غير مثبتة' => array('ineffective_controls', 5, 12, 'تصاعدي'),
    'ضوابط حرجة تجاوزت موعد التحقق' => array('critical_ctl_overdue', 1, 3, 'تصاعدي'),
    'مخاطر فوق الشهية' => array('risks_over_appetite', 2, 5, 'تصاعدي'),
    'مراجعات مستحقة متأخرة' => array('reviews_overdue', 3, 8, 'تصاعدي'),
    'أعطال المعدات في 90 يومًا' => array('equip_breakdowns_90d', 20, 50, 'تصاعدي'),
    'ذمم متأخرة عن استحقاقها' => array('overdue_receivables', 20, 50, 'تصاعدي'),
    'وثائق تنتهي في 30 يومًا' => array('expiring_docs_30d', 5, 15, 'تصاعدي'),
);
$kn = 0;
foreach ($readers as $name => $cfg) {
    // المطابقةُ بالاسمِ الجزئيِّ على المؤشراتِ المبذورةِ الستةِ والثلاثين؛ وما لم
    // يُطابق يُضاف مؤشرًا آليًّا جديدًا (المرحلة ١٢ تلزم قراءةً آليةً لا اسمًا).
    $r = $m->query("SELECT id FROM risk_kris WHERE company_id = 4 AND source_key = '"
        . $m->real_escape_string($cfg[0]) . "'");
    if ($r->num_rows > 0) { continue; }
    $ruId = (int) $m->query("SELECT id FROM risk_units WHERE company_id = 4 AND ru_code = 'RU-01'")->fetch_assoc()['id'];
    $st = $m->prepare("INSERT INTO risk_kris
        (company_id, ru_id, dept_ar, name_ar, warn_threshold_ar, critical_threshold_ar, source_ar,
         source_key, read_mode, warn_num, critical_num, direction, kri_state, active)
        VALUES (4, ?, 'إدارة المخاطر', ?, ?, ?, 'محرّك المؤشرات — قراءة آلية من النظام',
                ?, 'آلي', ?, ?, ?, 'ok', 1)");
    $wt = 'إنذار عند ' . $cfg[1]; $ct = 'حرج عند ' . $cfg[2];
    $st->bind_param('issssdds', $ruId, $name, $wt, $ct, $cfg[0], $cfg[1], $cfg[2], $cfg[3]);
    $st->execute();
    $st->close();
    $kn++;
}
$totalK = (int) $m->query("SELECT COUNT(*) c FROM risk_kris WHERE company_id = 4 AND read_mode = 'آلي'")->fetch_assoc()['c'];
say($out, "مؤشرات آلية أُضيفت: $kn · إجمالي المؤشرات الآلية: $totalK");

foreach ($out as $l) { echo $l, "\n"; }
