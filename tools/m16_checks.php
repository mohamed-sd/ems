<?php
/**
 * m16_checks.php — حزام قبول إدارة المخاطر M-16 (update0011 · بوابة G3)
 * 12 فحصًا: البنية والبذر والثلاثية والقاموس والحراس والمنظر القديم
 * وشاهد صفر التكرار (ورقة 32 — يعاد تشغيله بعد كل ترحيل) وفحص حي.
 * الخروج 0 عند الاكتمال.
 */
error_reporting(E_ALL);
$root = dirname(__DIR__);
$db = new mysqli('127.0.0.1', 'root', '', 'equipation_manage', 3307);
$db->set_charset('utf8mb4');
$fail = 0;
function chk($no, $name, $ok, $evidence) {
    global $fail;
    if (!$ok) { $fail++; }
    echo ($ok ? '✔' : '✘') . " م16-" . str_pad($no, 2, '0', STR_PAD_LEFT) . " · $name\n    ↳ $evidence\n";
}

/* ① البنية: الجداول الاثنا عشر */
$tables = array('risk_units','risk_register','risk_assessments','risk_controls','risk_control_links',
    'risk_control_evidence','risk_appetite','risk_kris','risk_signals','risk_treatments',
    'risk_acceptances','risk_escalations');
$have = 0;
foreach ($tables as $t) {
    $r = $db->query("SHOW TABLES LIKE '" . $t . "'");
    if ($r && $r->num_rows > 0) { $have++; }
}
chk(1, 'الجداول الاثنا عشر قائمة', $have === 12, "$have/12");

/* ② TenantRegistry يصنفها كلها */
$reg = (string) file_get_contents($root . '/app/Core/TenantRegistry.php');
$cls = 0;
foreach ($tables as $t) { if (strpos($reg, "'" . $t . "'") !== false) { $cls++; } }
chk(2, 'تصنيف العزل في TenantRegistry', $cls === 12, "$cls/12 T_TENANT");

/* ③ R1: الوحدة 21 حية بتبعية الرئيس */
$r = $db->query("SELECT company_id, active, parent_unit_id, layer FROM org_units WHERE unit_id = 21");
$u = $r->fetch_assoc();
chk(3, 'وحدة المخاطر بالهيكل (DEC-G)', $u && (int) $u['active'] === 1 && (int) $u['company_id'] === 4 && $u['parent_unit_id'] === null,
    'active=' . ($u['active'] ?? '?') . ' co=' . ($u['company_id'] ?? '?') . ' parent=NULL (الرئيس مباشرة)');

/* ④ البذر المرجعي: 11 وحدة · 8 شهية (3 أرضيات) · 36 مؤشرًا يدويًّا من ورقة 26
   المؤشراتُ تُقاس بخطِّ أساسِ الورقةِ لا بإجماليٍّ مجمَّد: إكمالُ 2026-08-08 أضاف
   مؤشراتٍ آليةً للمرحلة ١٢ (source_key + read_mode='آلي')، فالمعيارُ بقاءُ الـ36
   الموثَّقةِ لا منعُ الزيادةِ المشروعة. وحمايةُ الفحصِ باقيةٌ: نقصُ الأساسِ يُرصد. */
$n1 = (int) $db->query("SELECT COUNT(*) c FROM risk_units WHERE company_id=4 AND active=1")->fetch_assoc()['c'];
$n2 = (int) $db->query("SELECT COUNT(*) c FROM risk_appetite WHERE company_id=4 AND (plan_mode IS NULL OR plan_mode='')")->fetch_assoc()['c'];
$n2f = (int) $db->query("SELECT COUNT(*) c FROM risk_appetite WHERE company_id=4 AND immutable_floor=1")->fetch_assoc()['c'];
$n3 = (int) $db->query("SELECT COUNT(*) c FROM risk_kris WHERE company_id=4 AND active=1 AND read_mode='يدوي'")->fetch_assoc()['c'];
$n3a = (int) $db->query("SELECT COUNT(*) c FROM risk_kris WHERE company_id=4 AND active=1 AND read_mode='آلي'")->fetch_assoc()['c'];
chk(4, 'البذر: الوحدات والشهية والمؤشرات', $n1 === 11 && $n2 === 8 && $n2f === 3 && $n3 === 36,
    "وحدات $n1/11 · شهية $n2/8 (أرضيات $n2f/3) · مؤشرات الورقة 26 يدويًّا $n3/36 · آلية مضافة $n3a");

/* ⑤ R2: الدوران بثلاثيتهما (دور + حساب بموظف ومسمى) */
$r = $db->query("SELECT u.role, COUNT(*) c FROM users u JOIN employees e ON e.id = u.employee_id
                  JOIN job_titles jt ON jt.id = e.job_title_id
                 WHERE u.role IN (28,29) AND u.status='active' GROUP BY u.role");
$triples = array();
while ($x = $r->fetch_assoc()) { $triples[$x['role']] = (int) $x['c']; }
chk(5, 'الأدوار بثلاثيتها (28 مدير · 29 محلل)', !empty($triples[28]) && !empty($triples[29]),
    'حسابات مكتملة الثلاثية: r28=' . ($triples[28] ?? 0) . ' r29=' . ($triples[29] ?? 0));

/* ⑥ الشاشات العشر مسجلة بصلاحياتها */
$mods = (int) $db->query("SELECT COUNT(*) c FROM modules WHERE code LIKE 'Risk/%'")->fetch_assoc()['c'];
$grants28 = (int) $db->query("SELECT COUNT(*) c FROM role_permissions rp JOIN modules m ON m.id=rp.module_id
                              WHERE rp.role_id=28 AND m.code LIKE 'Risk/%' AND rp.can_view=1")->fetch_assoc()['c'];
$deptGrants = (int) $db->query("SELECT COUNT(DISTINCT rp.role_id) c FROM role_permissions rp JOIN modules m ON m.id=rp.module_id
                                WHERE m.code='Risk/dept_risk_space.php' AND rp.can_view=1 AND rp.role_id NOT IN (28,29,9)")->fetch_assoc()['c'];
chk(6, 'سجل الشاشات والصلاحيات', $mods >= 10 && $grants28 >= 10 && $deptGrants >= 14,
    "شاشات $mods/10 · منح المدير $grants28 · إدارات ترى زاويتها $deptGrants/15");

/* ⑦ R7: الأفعال الـ28 الأصلية بعقودها وwrite_class — بأسمائها لا بعددها.
   إكمالُ 2026-08-08 أضاف 12 فعلًا لأفعالِ الوثيقةِ الناقصة (المراجعةُ والواقعةُ
   والشهيةُ والتصنيفُ والتصديرُ والميدانُ والحوكمة)، فالعدُّ المجمَّدُ يرسب زورًا.
   والمعيارُ هنا أدقُّ من العدِّ: كلُّ رمزٍ من الثمانيةِ والعشرينَ الأصليةِ حاضرٌ
   بعقدِه — فيُرصد الحذفُ والانحرافُ ولا يُعاقب الإكمالُ المشروع. */
$BASE28 = array('RSK-SIG-CREATE','RSK-SIG-DISMISS','RSK-SIG-LINK','RSK-SIG-CONVERT','RSK-SIG-ESCALATE',
    'RSK-CREATE','RSK-CLASSIFY','RSK-ASSIGN-OWNER','RSK-ASSESS-INHERENT','RSK-ASSESS-RESIDUAL',
    'RSK-ASSESS-TARGET','RSK-CTL-CREATE','RSK-CTL-LINK','RSK-CTL-EVIDENCE','RSK-CTL-VERIFY',
    'RSK-TREAT-CREATE','RSK-TREAT-PROGRESS','RSK-TREAT-VERIFY','RSK-ACCEPT','RSK-CLOSE','RSK-REOPEN',
    'RSK-MERGE','RSK-KRI-UPDATE','RSK-ESC-ACK','RSK-VIEW-BOARD','RSK-VIEW-REGISTER','RSK-VIEW-DEPT',
    'RSK-VIEW-APPETITE');
$have28 = array();
$r = $db->query("SELECT canonical_code FROM nav09_action_map
                  WHERE canonical_code LIKE 'RSK-%' AND write_class IS NOT NULL AND write_class <> ''");
while ($x = $r->fetch_row()) { $have28[$x[0]] = true; }
$missBase = array();
foreach ($BASE28 as $c) { if (!isset($have28[$c])) { $missBase[] = $c; } }
$actsAll = (int) $db->query("SELECT COUNT(*) c FROM nav09_action_map
                             WHERE (canonical_code LIKE 'RSK-%' OR canonical_code LIKE 'GOV-RSK-%')
                               AND write_class IS NOT NULL AND write_class <> ''")->fetch_assoc()['c'];
chk(7, 'الأفعال الـ28 في القاموس بعقودها', empty($missBase),
    (28 - count($missBase)) . "/28 من الأساس بعقودها · إجمالي أفعال M-16 اليوم $actsAll"
    . (empty($missBase) ? '' : ' — الغائب: ' . implode(', ', $missBase)));

/* ⑧ R8: NAV v6 — تنقل الدورين + رابط زاوية الإدارات + صفر تكرار */
$nav28 = (int) $db->query("SELECT COUNT(*) c FROM nav_items WHERE role_id=28 AND route LIKE 'Risk/%' AND active=1")->fetch_assoc()['c'];
$navDept = (int) $db->query("SELECT COUNT(DISTINCT role_id) c FROM nav_items WHERE route='Risk/dept_risk_space.php' AND role_id NOT IN (28,29) AND active=1")->fetch_assoc()['c'];
$dups = (int) $db->query("SELECT COUNT(*) c FROM (SELECT role_id, group_id, label_ar, COUNT(*) n FROM nav_items WHERE active=1 AND route LIKE 'Risk/%' GROUP BY 1,2,3 HAVING n>1) x")->fetch_assoc()['c'];
chk(8, 'NAV-09 v6: التنقل المرحلي', $nav28 >= 9 && $navDept >= 14 && $dups === 0,
    "روابط المدير $nav28 · إدارات $navDept · تكرار $dups");

/* ⑨ الحراس الحاكمة في الخدمة (بنيوي) */
$svc = (string) file_get_contents($root . '/app/Services/Risk/RiskService.php');
$guards = strpos($svc, 'AUTHORITY_MATRIX') !== false
    && strpos($svc, 'RSK-403-CLOSE1') !== false
    && strpos($svc, 'لا يتحقق مالكه من نفسه') !== false
    && strpos($svc, "'محظور'") !== false;
$noDelete = true;
foreach (glob($root . '/Risk/*.php') as $f) {
    if (preg_match('~DELETE\s+FROM\s+risk_~i', (string) file_get_contents($f))) { $noDelete = false; }
}
$noAssessUpdate = !preg_match('~UPDATE\s+risk_assessments~i', $svc);
chk(9, 'الحراس: السلطة والإغلاق والاستقلال ولا-حذف', $guards && $noDelete && $noAssessUpdate,
    'مصفوفة السلطة + حارس الإغلاق الثلاثي + استقلال المتحقق + صفر DELETE/UPDATE على التقييمات');

/* ⑩ R9: الشاشتان القديمتان منظران نطاقيان */
$ceo = (string) file_get_contents($root . '/Portal/ceo_risk.php');
$com = (string) file_get_contents($root . '/Clients/commercial_risks.php');
chk(10, 'المنظر النطاقي على القديمتين', strpos($ceo, '_legacy_view_panel.php') !== false && strpos($com, '_legacy_view_panel.php') !== false,
    'ceo_risk (RU-01) + commercial_risks (RU-07) يقرآن السجل المركزي ولا يملكانه');

/* ⑪ شاهد ورقة 32: صفر خطر مكرر بسبب تعدد الإدارات العارضة */
$r = $db->query("SELECT rr.dedup_key, ru.dedup_window_days,
                        COUNT(*) n, DATEDIFF(MAX(rr.created_at), MIN(rr.created_at)) span
                   FROM risk_register rr JOIN risk_units ru ON ru.id = rr.ru_id
                  WHERE rr.company_id = 4 AND rr.merged_into_id IS NULL AND rr.state <> 'closed'
                  GROUP BY rr.dedup_key, ru.dedup_window_days HAVING n > 1");
$dupRisks = 0;
while ($x = $r->fetch_assoc()) { if ((int) $x['span'] <= (int) $x['dedup_window_days']) { $dupRisks++; } }
chk(11, 'شاهد صفر التكرار (ورقة 32)', $dupRisks === 0, "مفاتيح مكررة داخل نافذتها: $dupRisks — يعاد التشغيل بعد كل ترحيل");

/* ⑫ حي: لوحة المخاطر بجلسة مدير المخاطر */
$jar = sys_get_temp_dir() . '/m16chk.txt';
@unlink($jar);
$req = function ($url, $post = null) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => 1, CURLOPT_FOLLOWLOCATION => 1,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 40));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, 1); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $b = curl_exec($ch); curl_close($ch); return (string) $b;
};
$login = $req('http://localhost/ems/login.php');
preg_match('~name=["\']csrf_token["\']\s+value=["\']([^"\']+)~', $login, $mm);
$req('http://localhost/ems/login.php', array('username' => 'مخاطر', 'password' => '12345678', 'csrf_token' => $mm[1] ?? ''));
$board = $req('http://localhost/ems/Risk/risk_board.php');
chk(12, 'حي: لوحة المخاطر بحساب مدير المخاطر', substr_count($board, 'ems-kpi-card') === 6 && strpos($board, 'لوحة المخاطر العليا') !== false,
    'KPI=' . substr_count($board, 'ems-kpi-card') . '/6 والعنوان حاضر');

echo str_repeat('─', 56) . "\n";
echo $fail === 0 ? "حزام M-16: ✔ 12/12 — بوابة G3 خضراء\n" : "حزام M-16: ✘ $fail إخفاقًا\n";
exit($fail === 0 ? 0 : 1);
