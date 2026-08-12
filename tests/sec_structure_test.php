<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * update0004 · الموجة ⑥ — اختبار قبول: بنية SEC-01 (SEC-07→SEC-13)
 * التشغيل: php tests/sec_structure_test.php
 *
 * يثبت: الجداول الـ19 الجديدة بأسماء §15 حرفيًّا · توسيع job_titles (الأسلاف
 * لا تُهدم) · البذور (6 علاقات · 13 عائلة · 7 مستويات · 42 قالب هوية · 8 SoD ·
 * 17 حارسًا بسياساتها الثلاث · صفا التأسيس مطفآن) · والقيود البنيوية:
 * لا تأسيس بلا نهاية (S13 قاعدةً) · كسر الزجاج ≤ 24h · لا مركز بلا نطاق.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; $c ? $PASS++ : $FAIL++; fwrite(STDOUT, ($c ? '  ✔ ' : '  ✘ FAIL: ') . $m . "\n"); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$MARK = 'SEC6T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE FROM person_positions WHERE title_code='jt_3' AND person_id IN (SELECT person_id FROM persons WHERE full_name LIKE '%{$MARK}%')");
    $conn->query("DELETE FROM person_relationships WHERE person_id IN (SELECT person_id FROM persons WHERE full_name LIKE '%{$MARK}%')");
    $conn->query("DELETE FROM persons WHERE full_name LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM permission_exceptions WHERE reason LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ update0004 موجة ⑥ — بنية SEC-01 ══\n");

head('① الجداول التسعة عشر بأسماء §15 حرفيًّا');
$tables = array('persons','person_relationships','hr_dictionaries','person_positions',
    'permission_templates','permission_template_versions','template_permissions',
    'permission_exceptions','sensitive_access_grants','permission_change_requests',
    'permission_approval_steps','sod_conflicts','guard_override_policies',
    'sensitive_field_policies','effective_permissions','permission_audit_events',
    'permission_review_cycles','permission_review_lines','founding_mode');
foreach ($tables as $t) {
    $r = $conn->query("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='{$t}'");
    check($r && $r->num_rows === 1, $t);
}

/* ══ **عنوانُ القسمِ نفسُه يقول الحكم: «تُوسَّع ولا تُهدم».** وكانت الفحوصُ
     تشترط أعدادًا بعينها — إحصاءَ يومِ كُتب الفاحصُ لا حكمًا. والنظامُ نما
     بقرارِ مالكٍ وبعملٍ لاحق: `job_titles` 16 ← 23 (أدوارُ المخاطر) ·
     `user_capacities` 30 ← 76 · `positions` 0 ← 20 · القوالبُ 42 ← 68.
     فأربعةُ فحوصٍ ترسب **لأن الأسلافَ تُوسَّع** — وهو عينُ ما يُثبته القسم.
   ⇒ الحكمُ يُقاس والإحصاءُ يُعلَن:
     · **لا نقصانَ عن الموثَّق** — فهدمُ صفٍّ عطلٌ حقيقيٌّ يرسب.
     · و**كلُّ مسمًّى نشطٍ مرمَّزٌ كاملًا** = `مرمَّز === نشط` لا `=== 16`؛ وهو
       ثابتٌ يصدُق عند كلِّ عددٍ ويرسب عند أولِ مخالف.
       (وقد كشف هذا الحكمُ عطلَين حقيقيَّين أُصلحا في هجرة `2027_02_15`:
        ثلاثةُ أدوارِ مخاطرَ بلا عائلةٍ لأن `fam_risk` لم تكن في القاموس ·
        وأربعةُ صفوفٍ **ليست مسمّياتٍ أصلًا** — أسماءُ أشخاصٍ ورمزٌ يشير إلى
        ذاتِه — حُجرت بالإطفاءِ والإعلان.) */
head('② الأسلاف تُوسَّع ولا تُهدم — لا نقصانَ عن الموثَّق');
$r = $conn->query("SELECT COUNT(*) c FROM job_titles")->fetch_assoc();
check(intval($r['c']) >= 16, "job_titles ≥ 16 الموثَّقة (DEC-SEC-H) — وُجد {$r['c']}");
$r = $conn->query("SELECT COUNT(*) t,
                     SUM(title_code IS NOT NULL AND family_code IS NOT NULL
                         AND level_code IS NOT NULL) k
                   FROM job_titles WHERE COALESCE(active,1) = 1")->fetch_assoc();
check(intval($r['k']) === intval($r['t']),
    'كلُّ مسمًّى **نشطٍ** مرمَّزٌ بالعائلة والمستوى (★مسودة D1) — '
    . intval($r['k']) . '/' . intval($r['t']));
foreach (array('user_capacities' => 30, 'exception_requests' => 0, 'positions' => 0) as $t => $n) {
    $r = $conn->query("SELECT COUNT(*) c FROM {$t}")->fetch_assoc();
    check(intval($r['c']) >= $n, "{$t} ≥ {$n} الموثَّقة (وُجد {$r['c']})");
}

head('③ البذور');
$r = $conn->query("SELECT SUM(layer='relation') r, SUM(layer='family') f, SUM(layer='level') l FROM hr_dictionaries")->fetch_assoc();
/* ◆ والقواميسُ كذلك تُوسَّع: عائلةُ «المخاطر» (`fam_risk`) أُضيفت في هجرة
     `2027_02_15` تطبيقًا لسابقةِ `INJ-0059` (المخاطرُ مجالٌ أولُ الدرجة لا
     دَفينةٌ تحت الحوكمة) — فصارت العوائلُ 14. والحكمُ **لا نقصان**. */
check(intval($r['r']) >= 6 && intval($r['f']) >= 13 && intval($r['l']) >= 7,
    "القواميس ≥ 6/13/7 الموثَّقة (وُجد {$r['r']}/{$r['f']}/{$r['l']})");
$r = $conn->query("SELECT COUNT(*) c FROM permission_templates")->fetch_assoc();
check(intval($r['c']) >= 42, "قوالب الهوية ≥ 42 الموثَّقة (وُجد {$r['c']})");
$r = $conn->query("SELECT COUNT(*) c FROM permission_templates WHERE tpl_kind='relation' AND is_ceiling=1")->fetch_assoc();
check(intval($r['c']) === 6, 'قوالب العلاقة الست سقوف (is_ceiling=1)');
$r = $conn->query("SELECT COUNT(*) c FROM sod_conflicts")->fetch_assoc();
check(intval($r['c']) === 8, 'فصل الواجبات: الثمانية (§5)');
$r = $conn->query("SELECT SUM(overridable='never') n, SUM(overridable='break_glass_only') b, SUM(overridable='with_compensating_control') w FROM guard_override_policies")->fetch_assoc();
check(intval($r['n']) === 8 && intval($r['b']) === 7 && intval($r['w']) === 2, "الحراس 17: never=8 · bg=7 · comp=2 (§7.2)");
$r = $conn->query("SELECT COUNT(*) c FROM founding_mode WHERE enabled=0")->fetch_assoc();
check(intval($r['c']) === 2, 'وضعا التأسيس مبذوران مطفأين');

head('④ القيود البنيوية');
$r = $conn->query("UPDATE founding_mode SET enabled=1, ends_at=NULL WHERE mode='discovery'");
check($r === false, 'لا تفعيل تأسيس بلا ends_at — CHECK يرفض (S13 قاعدةً)');
$r = $conn->query("INSERT INTO permission_exceptions (company_id, person_id, permission_code, scope_rule, reason, valid_from, valid_to, is_break_glass)
                   VALUES (4, 9401, 'x.y', 'site:1', '{$MARK}', NOW(), DATE_ADD(NOW(), INTERVAL 48 HOUR), 1)");
check($r === false, 'كسر زجاج بمدة 48 ساعة يُرفض — CHECK ≤ 24 (§1.1④)');
$r = $conn->query("INSERT INTO permission_exceptions (company_id, person_id, permission_code, scope_rule, reason, valid_from, valid_to, is_break_glass)
                   VALUES (4, 9401, 'x.y', 'site:1', '{$MARK}', NOW(), DATE_ADD(NOW(), INTERVAL 12 HOUR), 1)");
check($r === true, 'وكسر زجاج 12 ساعة يُقبل');
$r = $conn->query("INSERT INTO permission_exceptions (company_id, person_id, permission_code, scope_rule, reason, valid_from, valid_to)
                   VALUES (4, 9402, 'x.y', 'site:1', '{$MARK}', NOW(), NULL)");
check($r === false, 'لا استثناء مفتوح المدة — valid_to إلزامي');

head('⑤ الشخص والعلاقة والمركز');
$conn->query("INSERT INTO persons (full_name) VALUES ('شخص {$MARK}')");
$pid = intval($conn->insert_id);
check($pid > 0, "شخص أُنشئ (#{$pid})");
$r = $conn->query("INSERT INTO person_relationships (person_id, company_id, relation_code, valid_from)
                   VALUES ({$pid}, 4, 'rel_supplier_emp', CURDATE())");
check($r === true, 'علاقة «موظف مورد» بلا موظف داخلي وهمي (§14②)');
$r = $conn->query("INSERT INTO person_positions (person_id, company_id, relation_code, family_code, level_code,
                    title_code, scope_type, scope_id, valid_from)
                   VALUES ({$pid}, 4, 'rel_employee', 'fam_maintenance', 'lvl_executor', 'jt_3', 'site', 1, CURDATE())");
check($r === true, 'مركز بطبقاته والنطاق إلزامي');
$r = $conn->query("INSERT INTO person_positions (person_id, company_id, relation_code, family_code, level_code,
                    title_code, scope_type, scope_id, valid_from)
                   VALUES ({$pid}, 4, 'rel_employee', 'fam_x_none', 'lvl_executor', 'jt_3', 'site', 2, CURDATE())");
check($r === false, 'عائلة غير معجمية تُرفض FK — لا موظف بلا عائلة معتمدة');
$r = $conn->query("INSERT INTO person_positions (person_id, company_id, relation_code, family_code, level_code,
                    title_code, scope_type, scope_id, valid_from)
                   VALUES ({$pid}, 4, 'rel_employee', 'fam_maintenance', 'lvl_executor', 'jt_3', 'site', 1, CURDATE())");
check($r === false && $conn->errno === 1062, 'UQ(person,title,scope,from) يمنع الازدواج');

fwrite(STDOUT, "\n══ النتيجة: PASS={$PASS} · FAIL={$FAIL} ══\n");
exit($FAIL === 0 ? 0 : 1);
