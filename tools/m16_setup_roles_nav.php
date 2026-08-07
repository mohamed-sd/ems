<?php
/**
 * m16_setup_roles_nav.php — R2/R7/R8: الأدوار بثلاثيتها + الموديولات
 * والصلاحيات + NAV-09 v6 (مجموعات مرحلية للدور 28) + الأفعال الـ28 بعقودها.
 * idempotent — يعاد تشغيله بلا أثر مزدوج.
 */
error_reporting(E_ALL);
$m = new mysqli('127.0.0.1', 'root', '', 'equipation_manage', 3307);
$m->set_charset('utf8mb4');
$out = array();

/* ── ① الدوران 28/29 ─────────────────────────────────────────────────── */
$roles = array(
    28 => array('إدارة المخاطر', null),
    29 => array('محلل المخاطر', 28),
);
foreach ($roles as $rid => $r) {
    $st = $m->prepare("INSERT INTO roles (id, name, parent_role_id, level, role_scope, status)
                       SELECT ?, ?, ?, 1, 'gloable', 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM roles WHERE id = ?)");
    $st->bind_param('isii', $rid, $r[0], $r[1], $rid);
    $st->execute();
    $out[] = "role $rid: " . ($m->affected_rows ? 'created' : 'exists');
}

/* ── ② المسميات والموظفون والحسابات (الثلاثية) ───────────────────────── */
$titles = array('مدير إدارة المخاطر' => 21, 'محلل مخاطر' => 21);
$titleIds = array();
foreach ($titles as $tn => $unit) {
    $st = $m->prepare("INSERT INTO job_titles (title_code, company_id, name, org_unit_id, active, status, sort_order)
                       SELECT CONCAT('JT_RISK_', ?), 4, ?, ?, 1, 'active', 90 FROM DUAL
                       WHERE NOT EXISTS (SELECT 1 FROM job_titles WHERE company_id = 4 AND name = ?)");
    $suffix = $tn === 'مدير إدارة المخاطر' ? 'MGR' : 'ANL';
    $st->bind_param('ssis', $suffix, $tn, $unit, $tn);
    $st->execute();
    $r = $m->query("SELECT id FROM job_titles WHERE company_id = 4 AND name = '" . $m->real_escape_string($tn) . "'");
    $titleIds[$tn] = (int) $r->fetch_assoc()['id'];
}
$accounts = array(
    array('مخاطر', 'مدير المخاطر', 28, 'مدير إدارة المخاطر'),
    array('محلل مخاطر', 'محلل المخاطر', 29, 'محلل مخاطر'),
);
foreach ($accounts as $a) {
    list($username, $empName, $rid, $title) = $a;
    $r = $m->query("SELECT id FROM users WHERE username = '" . $m->real_escape_string($username) . "' AND company_id = 4");
    if ($r->num_rows > 0) { $out[] = "user $username: exists"; continue; }
    // موظف أولًا (لا حساب بلا employee_id — عرف FIN-26)
    $tid = $titleIds[$title];
    $st = $m->prepare("INSERT INTO employees (employee_type, company_id, name, employee_code, job_title_id, status)
                       VALUES ('موظف', 4, ?, CONCAT('RSK-', FLOOR(RAND()*9000)+1000), ?, 1)");
    $st->bind_param('si', $empName, $tid);
    $st->execute();
    $eid = (int) $m->insert_id;
    $hash = password_hash('12345678', PASSWORD_BCRYPT);
    $st = $m->prepare("INSERT INTO users (name, username, password, role, company_id, employee_id, status, parent_id)
                       VALUES (?, ?, ?, ?, 4, ?, 'active', 0)");
    $st->bind_param('sssii', $empName, $username, $hash, $rid, $eid);
    $st->execute();
    $out[] = "user $username: created (emp $eid)";
}

/* ── ③ الموديولات (سجل الشاشات) ─────────────────────────────────────── */
$screens = array(
    array('لوحة المخاطر العليا', 'Risk/risk_board.php', 'fa fa-tower-observation'),
    array('سجل المخاطر المركزي', 'Risk/risk_register.php', 'fa fa-triangle-exclamation'),
    array('ملف الخطر', 'Risk/risk_card.php', 'fa fa-file-shield'),
    array('إشارات الخطر والفرز', 'Risk/risk_signals.php', 'fa fa-satellite-dish'),
    array('الضوابط والضوابط الحرجة', 'Risk/risk_controls.php', 'fa fa-shield-halved'),
    array('مؤشرات الخطر الرئيسة', 'Risk/risk_kris.php', 'fa fa-gauge-high'),
    array('شهية المخاطر وحدودها', 'Risk/risk_appetite.php', 'fa fa-scale-balanced'),
    array('إجراءات معالجة المخاطر', 'Risk/risk_treatments.php', 'fa fa-list-check'),
    array('مراجعات المخاطر وقراراتها', 'Risk/risk_reviews.php', 'fa fa-stamp'),
    array('مساحة مخاطر الإدارة', 'Risk/dept_risk_space.php', 'fa fa-building-shield'),
);
$modIds = array();
foreach ($screens as $s) {
    $r = $m->query("SELECT id FROM modules WHERE code = '" . $m->real_escape_string($s[1]) . "'");
    if ($x = $r->fetch_assoc()) { $modIds[$s[1]] = (int) $x['id']; continue; }
    $st = $m->prepare("INSERT INTO modules (name, code, owner_role_id, is_link, is_quick, icon, display_order) VALUES (?, ?, 28, 1, 0, ?, 500)");
    $st->bind_param('sss', $s[0], $s[1], $s[2]);
    $st->execute();
    $modIds[$s[1]] = (int) $m->insert_id;
    $out[] = "module {$s[1]}: #{$modIds[$s[1]]}";
}

/* ── ④ الصلاحيات ────────────────────────────────────────────────────── */
$permTable = 'role_permissions'; // (id, role_id, module_id, can_view, can_add, can_edit, can_delete)

function grant($m, $permTable, $roleId, $moduleId, $v, $a, $e, $d) {
    $r = $m->query("SELECT COUNT(*) c FROM `$permTable` WHERE role_id = $roleId AND module_id = $moduleId");
    if ((int) $r->fetch_assoc()['c'] > 0) { return false; }
    $m->query("INSERT INTO `$permTable` (role_id, module_id, can_view, can_add, can_edit, can_delete)
               VALUES ($roleId, $moduleId, $v, $a, $e, $d)");
    return true;
}
$granted = 0;
foreach ($modIds as $code => $mid) {
    // 28 مدير المخاطر: كامل بلا حذف (لا حذف في المخاطر أصلًا)
    $granted += grant($m, $permTable, 28, $mid, 1, 1, 1, 0) ? 1 : 0;
    // 29 محلل: عرض/إضافة/تعديل بلا حذف
    $granted += grant($m, $permTable, 29, $mid, 1, 1, 1, 0) ? 1 : 0;
}
// الرئيس (9): اللوحة والسجل والمراجعات والشهية عرضًا
foreach (array('Risk/risk_board.php', 'Risk/risk_register.php', 'Risk/risk_card.php', 'Risk/risk_reviews.php', 'Risk/risk_appetite.php') as $c) {
    $granted += grant($m, $permTable, 9, $modIds[$c], 1, 0, 0, 0) ? 1 : 0;
}
// مديرو الإدارات: مساحة الإدارة النطاقية عرضًا (الزاوية لا السجل الكامل)
$deptRoles = array(1, 2, 3, 4, 6, 12, 13, 16, 17, 19, 23, 24, 25, 26, 27);
foreach ($deptRoles as $dr) {
    $granted += grant($m, $permTable, $dr, $modIds['Risk/dept_risk_space.php'], 1, 0, 0, 0) ? 1 : 0;
}
$out[] = "grants added: $granted";

/* ── ⑤ NAV-09 v6: مجموعات مرحلية للدورين 28/29 (نمط n9s للوحدة 15) ───── */
$navPlan = array(
    // stage_no, stage_title, group name, [ [label, route], ... ]
    array(0, 'لوحة الإدارة', 'مساحتي الشخصية', array(
        array('مهامي', 'Portal/my_tasks.php'),
        array('لوحة المخاطر العليا', 'Risk/risk_board.php'),
    )),
    array(1, 'أولًا: الإشارة والفرز', 'الإشارات', array(
        array('إشارات الخطر والفرز', 'Risk/risk_signals.php'),
    )),
    array(2, 'ثانيًا: السجل والتقييم', 'السجل المركزي', array(
        array('سجل المخاطر المركزي', 'Risk/risk_register.php'),
    )),
    array(3, 'ثالثًا: الضوابط والمؤشرات', 'الضوابط', array(
        array('الضوابط والضوابط الحرجة', 'Risk/risk_controls.php'),
        array('مؤشرات الخطر الرئيسة', 'Risk/risk_kris.php'),
    )),
    array(4, 'رابعًا: المعالجة والقبول', 'المعالجة والقرار', array(
        array('إجراءات معالجة المخاطر', 'Risk/risk_treatments.php'),
        array('مراجعات المخاطر وقراراتها', 'Risk/risk_reviews.php'),
    )),
    array(5, 'خامسًا: الشهية والمنهج', 'الإطار الحاكم', array(
        array('شهية المخاطر وحدودها', 'Risk/risk_appetite.php'),
        array('مساحة مخاطر الإدارة', 'Risk/dept_risk_space.php'),
    )),
);
foreach (array(28, 29) as $roleId) {
    $gi = 0;
    foreach ($navPlan as $stage) {
        $gi++;
        list($stageNo, $stageTitle, $gName, $links) = $stage;
        $gCode = "n9s15_{$stageNo}_{$gi}_r{$roleId}";
        $r = $m->query("SELECT id FROM link_groups WHERE group_code = '" . $m->real_escape_string($gCode) . "'");
        if ($x = $r->fetch_assoc()) {
            $gid = (int) $x['id'];
        } else {
            $st = $m->prepare("INSERT INTO link_groups (name, group_code, owner_role_id, icon, display_order, stage_no, stage_title, is_active)
                               VALUES (?, ?, ?, 'fa fa-layer-group', ?, ?, ?, 1)");
            $st->bind_param('ssiiis', $gName, $gCode, $roleId, $gi, $stageNo, $stageTitle);
            $st->execute();
            $gid = (int) $m->insert_id;
            $out[] = "group $gCode: #$gid";
        }
        $so = 0;
        foreach ($links as $lk) {
            $so++;
            $r = $m->query("SELECT id FROM nav_items WHERE role_id = $roleId AND group_id = $gid AND route = '" . $m->real_escape_string($lk[1]) . "'");
            if ($r->num_rows > 0) { continue; }
            $mid = isset($modIds[$lk[1]]) ? $modIds[$lk[1]] : null;
            $st = $m->prepare("INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, active)
                               VALUES (?, 'RISK', ?, ?, ?, ?, 'fa fa-circle-dot', ?, 1)");
            $st->bind_param('iiissi', $roleId, $gid, $mid, $lk[0], $lk[1], $so);
            $st->execute();
        }
    }
    $out[] = "nav role $roleId: seeded";
}
// رابط مساحة الإدارة لمديري الإدارات (داخل مجموعة «أخرى» أو مجموعتهم الأولى؟
// قرار: رابط واحد في مجموعة موجودة أولى لكل دور — بلا إغراق القوائم):
foreach ($deptRoles as $dr) {
    $r = $m->query("SELECT id FROM nav_items WHERE role_id = $dr AND route = 'Risk/dept_risk_space.php'");
    if ($r->num_rows > 0) { continue; }
    $g = $m->query("SELECT lg.id FROM link_groups lg WHERE lg.owner_role_id = $dr AND lg.is_active = 1 ORDER BY lg.stage_no DESC, lg.display_order DESC LIMIT 1");
    $gx = $g->fetch_assoc();
    if (!$gx) { continue; }
    $gid = (int) $gx['id'];
    $mid = $modIds['Risk/dept_risk_space.php'];
    $m->query("INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, active)
               VALUES ($dr, 'RISK', $gid, $mid, 'مساحة مخاطر الإدارة', 'Risk/dept_risk_space.php', 'fa fa-building-shield', 99, 1)");
    $out[] = "dept nav r$dr: linked";
}

/* ── ⑥ الأفعال الـ28 بعقودها السداسية في القاموس ─────────────────────── */
$actions = array(
    // code, label, screen, file, actor, writes, event, consumers, effect, reverse, write_class
    array('RSK-SIG-CREATE', 'إنشاء إشارة خطر', 'إشارات الخطر والفرز', 'Risk/risk_actions.php', 'أي إدارة', 'risk_signals', 'risk.signal.created', 'محلل المخاطر', 'إشارة تنتظر الفرز', 'الإهمال بوسم في الفرز', 'domain_write'),
    array('RSK-SIG-DISMISS', 'فرز: إهمال بوسم', 'إشارات الخطر والفرز', 'Risk/risk_actions.php', 'محلل المخاطر', 'risk_signals', 'risk.signal.dismissed', 'السجل', 'إشارة موسومة لا محذوفة', 'لا يلزم — الوسم أثر دائم', 'domain_write'),
    array('RSK-SIG-LINK', 'فرز: ربط بخطر قائم', 'إشارات الخطر والفرز', 'Risk/risk_actions.php', 'محلل المخاطر', 'risk_signals,risk_register', 'risk.signal.linked', 'مالك الخطر', 'إعادة تقييم للخطر القائم', 'فك الربط بقرار مسبب', 'domain_write'),
    array('RSK-SIG-CONVERT', 'فرز: إنشاء خطر', 'إشارات الخطر والفرز', 'Risk/risk_actions.php', 'محلل المخاطر', 'risk_signals,risk_register', 'risk.created', 'مالك الخطر ومدير المخاطر', 'خطر مصنف في السجل المركزي', 'الدمج أو الإغلاق بدليل', 'domain_write'),
    array('RSK-SIG-ESCALATE', 'فرز: تصعيد فوري', 'إشارات الخطر والفرز', 'Risk/risk_actions.php', 'محلل المخاطر', 'risk_signals,risk_escalations', 'risk.escalated', 'الرئيس التنفيذي', 'تصعيد يصل الرئيس في اليوم نفسه', 'استلام التصعيد', 'domain_write'),
    array('RSK-CREATE', 'تسجيل خطر', 'سجل المخاطر المركزي', 'Risk/risk_actions.php', 'محلل/مدير المخاطر', 'risk_register', 'risk.created', 'الإدارة المالكة', 'خطر بمفتاح تكرار محسوب', 'الدمج بقرار مسبب', 'domain_write'),
    array('RSK-CLASSIFY', 'تصنيف الخطر ووحدته', 'سجل المخاطر المركزي', 'Risk/risk_actions.php', 'محلل المخاطر', 'risk_register', 'risk.classified', 'السجل', 'وحدة ونطاق معتمدان', 'إعادة تصنيف بطلب', 'domain_write'),
    array('RSK-ASSIGN-OWNER', 'تعيين مالك الخطر', 'سجل المخاطر المركزي', 'Risk/risk_actions.php', 'مدير المخاطر', 'risk_register', 'risk.owner_assigned', 'مالك الخطر', 'مالك معين بمسؤولية المعالجة', 'إعادة تعيين', 'domain_write'),
    array('RSK-ASSESS-INHERENT', 'تقييم متأصل', 'ملف الخطر', 'Risk/risk_actions.php', 'مالك الخطر بمراجعة المخاطر', 'risk_assessments', 'risk.assessed', 'مدير المخاطر', 'نسخة تقييم مؤرخة لا تمحى', 'نسخة أحدث — السابق يبقى', 'domain_write'),
    array('RSK-ASSESS-RESIDUAL', 'تقييم متبقٍّ', 'ملف الخطر', 'Risk/risk_actions.php', 'مالك الخطر بتحدي المخاطر', 'risk_assessments,risk_register', 'risk.assessed', 'صاحب سلطة القبول', 'مستوى جارٍ عليه القبول', 'نسخة أحدث', 'domain_write'),
    array('RSK-ASSESS-TARGET', 'تقييم مستهدف', 'ملف الخطر', 'Risk/risk_actions.php', 'محلل المخاطر', 'risk_assessments', 'risk.assessed', 'الإغلاق', 'درجة بعد خطة المعالجة', 'نسخة أحدث', 'domain_write'),
    array('RSK-CTL-CREATE', 'تسجيل ضابط', 'الضوابط والضوابط الحرجة', 'Risk/risk_actions.php', 'مدير/محلل المخاطر', 'risk_controls', 'risk.control.created', 'مالك الضابط', 'ضابط بحقوله (الحرج بخمسته)', 'تعطيل لا حذف', 'domain_write'),
    array('RSK-CTL-LINK', 'ربط ضابط بخطر', 'ملف الخطر', 'Risk/risk_actions.php', 'مالك الضابط/المحلل', 'risk_control_links', 'risk.control.linked', 'السجل', 'خريطة ضوابط الخطر', 'فك الربط', 'domain_write'),
    array('RSK-CTL-EVIDENCE', 'دليل تنفيذ ضابط', 'مساحة مخاطر الإدارة', 'Risk/risk_actions.php', 'مالك الضابط', 'risk_control_evidence', 'risk.control.evidence', 'المتحقق', 'دليل مؤرخ — لا احتساب بدونه', 'لا يلزم — الدليل أثر', 'domain_write'),
    array('RSK-CTL-VERIFY', 'تحقق من ضابط', 'الضوابط والضوابط الحرجة', 'Risk/risk_actions.php', 'متحقق مستقل ≠ المالك', 'risk_controls,risk_control_evidence', 'risk.control.verified', 'السجل والمؤشرات', 'فعالية محكومة — والفشل الحرج يصعد فورًا', 'تحقق أحدث', 'domain_write'),
    array('RSK-TREAT-CREATE', 'إسناد خطة معالجة', 'ملف الخطر', 'Risk/risk_actions.php', 'مالك الخطر', 'risk_treatments', 'risk.treatment.planned', 'مسؤول المعالجة', 'إجراء بمهلة يظهر في المهام', 'تعديل الخطة', 'domain_write'),
    array('RSK-TREAT-PROGRESS', 'تنفيذ معالجة بدليل', 'إجراءات معالجة المخاطر', 'Risk/risk_actions.php', 'مسؤول المعالجة', 'risk_treatments', 'risk.treatment.done', 'المتحقق', 'دليل إنجاز ينتظر القبول', 'إعادة للتنفيذ', 'domain_write'),
    array('RSK-TREAT-VERIFY', 'قبول دليل المعالجة', 'إجراءات معالجة المخاطر', 'Risk/risk_actions.php', 'المتحقق', 'risk_treatments', 'risk.treatment.verified', 'حارس الإغلاق', 'معالجة مثبتة تفتح الإغلاق', 'لا يلزم', 'domain_write'),
    array('RSK-ACCEPT', 'قبول رسمي للخطر', 'ملف الخطر', 'Risk/risk_actions.php', 'صاحب السلطة بالمصفوفة', 'risk_acceptances,risk_register', 'risk.accepted', 'السجل والمراجعات', 'قرار موقع بمهلة مراجعة', 'مراجعة دورية تعيد الفتح', 'domain_write'),
    array('RSK-CLOSE', 'إغلاق بحارس الدليل', 'ملف الخطر', 'Risk/risk_actions.php', 'صاحب السلطة', 'risk_register,risk_acceptances', 'risk.closed', 'السجل', 'إغلاق بثلاثة شواهد', 'إعادة فتح', 'domain_write'),
    array('RSK-REOPEN', 'إعادة فتح خطر', 'ملف الخطر', 'Risk/risk_actions.php', 'محلل/مدير المخاطر', 'risk_register', 'risk.reopened', 'مالك الخطر', 'الخطر يعود للدورة', 'إغلاق جديد بدليل', 'domain_write'),
    array('RSK-MERGE', 'دمج خطرين بقرار', 'سجل المخاطر المركزي', 'Risk/risk_actions.php', 'محلل المخاطر', 'risk_register', 'risk.merged', 'السجل', 'خطر واحد بزاويتين — الأثر يبقى', 'لا يلزم — الدمج قرار موثق', 'domain_write'),
    array('RSK-KRI-UPDATE', 'قراءة مؤشر خطر', 'مؤشرات الخطر الرئيسة', 'Risk/risk_actions.php', 'محلل المخاطر', 'risk_kris', 'risk.kri.read', 'اللوحة والإشارات', 'قيمة وحالة — والحرج يولد SG-15', 'قراءة أحدث', 'domain_write'),
    array('RSK-ESC-ACK', 'استلام تصعيد', 'لوحة المخاطر العليا', 'Risk/risk_actions.php', 'الرئيس/مدير المخاطر', 'risk_escalations', 'risk.escalation.acked', 'السجل', 'تصعيد مستلم بوقته', 'لا يلزم', 'domain_write'),
    array('RSK-VIEW-BOARD', 'عرض لوحة المخاطر', 'لوحة المخاطر العليا', 'Risk/risk_board.php', 'الرئيس ومدير المخاطر', '-', '-', '-', 'قراءة محفظة', '-', 'read_only'),
    array('RSK-VIEW-REGISTER', 'عرض السجل المركزي', 'سجل المخاطر المركزي', 'Risk/risk_register.php', 'إدارة المخاطر', '-', '-', '-', 'قراءة', '-', 'read_only'),
    array('RSK-VIEW-DEPT', 'عرض زاوية الإدارة', 'مساحة مخاطر الإدارة', 'Risk/dept_risk_space.php', 'مدير الإدارة', '-', '-', '-', 'قراءة نطاقية من محرك الصلاحيات', '-', 'read_only'),
    array('RSK-VIEW-APPETITE', 'عرض الشهية وحدودها', 'شهية المخاطر وحدودها', 'Risk/risk_appetite.php', 'الجميع بمنحة', '-', '-', '-', 'قراءة — والتغيير قرار مالك', '-', 'read_only'),
);
$insA = $m->prepare("INSERT INTO nav09_action_map
    (canonical_code, label_ar, screen_title, canonical_file, actor_ar, writes_text, event_name, consumers_text, effect_text, reverse_text, live_code, state, write_class)
    SELECT ?,?,?,?,?,?,?,?,?,?,?, 'bound', ? FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM nav09_action_map WHERE canonical_code = ?)");
$an = 0;
foreach ($actions as $a) {
    $live = $a[0];
    $insA->bind_param('sssssssssssss', $a[0], $a[1], $a[2], $a[3], $a[4], $a[5], $a[6], $a[7], $a[8], $a[9], $live, $a[10], $a[0]);
    $insA->execute();
    $an += $m->affected_rows > 0 ? 1 : 0;
}
$out[] = "actions registered now: $an / " . count($actions);

foreach ($out as $l) { echo $l, "\n"; }
$r = $m->query("SELECT COUNT(*) c FROM nav09_action_map WHERE canonical_code LIKE 'RSK-%'");
echo 'total RSK actions in dictionary: ', $r->fetch_assoc()['c'], "\n";
