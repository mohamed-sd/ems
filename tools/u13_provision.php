<?php
/**
 * tools/u13_provision.php — تزويدُ حزمة update0013 بحامليها
 * ═══════════════════════════════════════════════════════════════════════════
 * ما تعالجه هذه الأداةُ ثغرتان **إداريتان** كشفتهما بوابةُ القبول، لا عطبٌ برمجي:
 *   ① تخصصان من العشرةِ بلا حامل: ACC-05 التمويل · ACC-09 الضرائب.
 *   ② سبعةَ عشرَ محاسبًا لهم صفوفٌ في `fin_accountants` بلا حسابِ دخول —
 *      فلا تبلغهم مهمةٌ باسمِهم وتُصعَّد إلى رئاسةِ الحسابات.
 *   ③ الأدوارُ الخمسةُ الجديدةُ (31..35) بلا حاملٍ واحد.
 *
 * ◆ حكمان يحكمان هذه الأداةَ ولا تخرقهما:
 *   • CEO-Y0121 «لا يسري تكليفٌ قياديٌّ أو رقابيٌّ قبلَ موافقةِ الرئيسِ الموثَّقة».
 *     فالأدوارُ الجديدةُ **تمرُّ ببوابةِ التكليفِ نفسِها** طلبًا وفحصَ تعارضٍ
 *     وقرارًا — ولا تُكتب في `users.role` وكفى. والبوابةُ التي بنيتُها لا
 *     أتحايل عليها لأزرع بياناتي.
 *   • FMGR-0022 «ولا يُحذف دورٌ قديمٌ قبل ترحيلِ حاملِه» — فلا صفَّ يُحذف هنا،
 *     والترحيلُ يُسجَّل في `fin_role_migration`.
 *
 * ◆ كلماتُ المرور: **لا تُنشأ هنا**. الحسابُ يُولَد بقيمةٍ ليست تجزئةً صالحةً
 *   (`password_verify` تردّها دائمًا) و`force_password_change = 1` — فالحسابُ
 *   موجودٌ ومربوطٌ ولا يُدخِل أحدٌ به حتى يضبط مديرُ الصلاحياتِ كلمتَه من داخلِ
 *   النظام. إنشاءُ بيانِ اعتمادٍ صالحٍ قرارُ مالكٍ لا قرارُ أداة.
 *
 * التشغيل: php tools/u13_provision.php [--apply] [--company=4]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT  = dirname(__DIR__);
$apply = in_array('--apply', $argv, true);
$onlyCo = 0;
foreach ($argv as $a) { if (strpos($a, '--company=') === 0) { $onlyCo = (int) substr($a, 10); } }

$cfg = array('host' => 'localhost', 'port' => 3307, 'user' => 'root', 'pass' => '', 'db' => 'equipation_manage');
if (is_file($ROOT . '/.env')) {
    foreach (file($ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
        if ($ln === '' || $ln[0] === '#' || strpos($ln, '=') === false) { continue; }
        list($k, $v) = explode('=', $ln, 2); $k = trim($k); $v = trim($v);
        if ($k === 'DB_HOST') { $hp = explode(':', $v); $cfg['host'] = $hp[0]; if (isset($hp[1])) { $cfg['port'] = (int) $hp[1]; } }
        if ($k === 'DB_PORT') { $cfg['port'] = (int) $v; }
        if ($k === 'DB_USER') { $cfg['user'] = $v; }
        if ($k === 'DB_PASS') { $cfg['pass'] = $v; }
        if ($k === 'DB_NAME') { $cfg['db']   = $v; }
    }
}
$db = @new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['db'], $cfg['port']);
if ($db->connect_errno) { exit("تعذّر الاتصال: {$db->connect_error}\n"); }
$db->set_charset('utf8mb4');

require_once $ROOT . '/app/Services/Exec/AssignmentGate.php';
use App\Services\Exec\AssignmentGate;

/** قيمةٌ ليست تجزئةً صالحةً — `password_verify` تردّها دائمًا. */
const NO_PASSWORD = '!';

$co = $onlyCo > 0 ? $onlyCo : (int) scalar($db, "SELECT company_id FROM fin_accountants
                                                  WHERE (is_deleted IS NULL OR is_deleted=0)
                                                  GROUP BY company_id ORDER BY COUNT(*) DESC LIMIT 1");
if ($co <= 0) { exit("لا كيانَ يحمل محاسبين\n"); }

function scalar($db, $sql) { $r = $db->query($sql); if (!$r) { return null; } $x = $r->fetch_row(); return $x ? $x[0] : null; }
function say($s) { echo $s . "\n"; }
function ok($s)  { echo "  ✔ $s\n"; }
function skip($s){ echo "  · $s\n"; }
function bad($s) { global $FAILED; $FAILED++; echo "  ✗ $s\n"; }
$FAILED = 0;

/**
 * تنفيذٌ محروس. ◆ گوتشا كُشفت هنا: طباعةُ ✔ قبلَ فحصِ المُرجَع تجعل الأداةَ
 *   تُعلن نجاحًا وقعَ فشلُه — وقد أخفت قيدَ مفتاحٍ أجنبيٍّ صامتًا. فلا ✔ إلا بعد
 *   `execute()` صادقة.
 */
function must($db, $sql, $types, array $vals, $label)
{
    $st = $db->prepare($sql);
    if (!$st) { bad("$label — prepare: " . $db->error); return 0; }
    if (strlen($types) !== count($vals)) {
        $st->close(); bad("$label — انزياحُ وسائط: أنواع " . strlen($types) . " · قيم " . count($vals)); return 0;
    }
    $st->bind_param($types, ...$vals);
    if (!$st->execute()) { $e = $st->error; $st->close(); bad("$label — " . $e); return 0; }
    $id = $st->insert_id ?: -1;
    $st->close();
    return $id;
}

/**
 * وحدةُ الماليةِ التي ينتمي إليها التخصص. `fin_accountants.finance_unit_id`
 * NOT NULL بمفتاحٍ أجنبيٍّ على `fin_units` — فإغفالُه يكتب صفرًا فينفجر القيد.
 * وما لا وحدةَ له يُنشأ له واحدةٌ باسمِه: التخصصُ في الوثيقةِ نطاقٌ تنظيميٌّ
 * حقيقيٌّ لا وسمٌ على ورق.
 */
function unit_for($db, $co, $specCode, $specName, $apply)
{
    static $map = array(
        'ACC-01' => 'budgets', 'ACC-02' => 'revenue', 'ACC-03' => 'ap', 'ACC-04' => 'assets',
        'ACC-05' => 'financing', 'ACC-06' => 'cost_control', 'ACC-07' => 'cost_control',
        'ACC-08' => 'payroll', 'ACC-09' => 'tax', 'ACC-10' => 'gl',
    );
    $code = isset($map[$specCode]) ? $map[$specCode] : 'gl';
    $id = (int) scalar($db, "SELECT id FROM fin_units WHERE company_id={$co}
                              AND code='" . $db->real_escape_string($code) . "' LIMIT 1");
    if ($id > 0) { return $id; }
    if (!$apply) { return 0; }
    /* اسمُ الوحدةِ نطاقُ التخصصِ لا اسمُ شاغلِه — فالوحدةُ تبقى وإن تغيّر حاملُها. */
    $unitName = trim(preg_replace('~^محاسب\s+~u', '', (string) $specName));
    if ($unitName === '') { $unitName = $specCode; }
    /* ◆ گوتشا: `«$code»` — المحرفُ `»` يُبتلع في اسمِ المتغيّر. القوسان إلزام. */
    $newId = must($db, "INSERT INTO fin_units (company_id, code, name, active, is_deleted, created_by)
                        VALUES (?,?,?,1,0,1)", 'iss', array($co, $code, $unitName), "وحدةُ مالية «{$code}»");
    if ($newId > 0) { ok("وحدةُ ماليةٍ جديدة #{$newId} — {$code} « {$unitName} »"); }
    return $newId > 0 ? $newId : 0;
}

say("الكيان: $co · الوضع: " . ($apply ? 'تطبيق' : 'معاينة'));
say(str_repeat('─', 74));

/* ═══ ① الموظفونَ والحسابات للمناصبِ السبعةِ الجديدة ═══════════════════════ */
/* الأدوارُ الخمسةُ الجديدةُ + محاسبا التخصصين. وكلٌّ **شخصٌ مستقل**: مصفوفةُ
   فصلِ الواجباتِ تمنع جمعَ 31 مع 33 (SOD-12) و34 مع 35 (SOD-06) و35 مع 31
   (SOD-07) — فالجمعُ في شخصٍ واحدٍ يُرفض بنيويًّا ولو أردتُه. */
$positions = array(
    array('key' => 'chief_acc', 'name' => 'رئيس الحسابات',              'code' => 'FIN-CTRL', 'role' => 31, 'spec' => ''),
    array('key' => 'cfo',       'name' => 'المدير المالي',              'code' => 'FIN-CFO',  'role' => 32, 'spec' => ''),
    array('key' => 'auditor',   'name' => 'المراجع الداخلي المستقل',    'code' => 'IAF-AUD',  'role' => 33, 'spec' => ''),
    array('key' => 'payer',     'name' => 'منفذ المدفوعات البنكية',     'code' => 'FIN-PAY',  'role' => 34, 'spec' => ''),
    array('key' => 'reconciler','name' => 'معد المطابقة البنكية',       'code' => 'FIN-REC',  'role' => 35, 'spec' => ''),
    array('key' => 'acc05',     'name' => 'محاسب التمويل والقروض والأقساط', 'code' => 'ACC-05', 'role' => 18, 'spec' => 'ACC-05'),
    array('key' => 'acc09',     'name' => 'محاسب الضرائب والإقرارات',   'code' => 'ACC-09',   'role' => 18, 'spec' => 'ACC-09'),
);

say("\n① الموظفونَ والحساباتُ للمناصبِ السبعة");
$made = array();
foreach ($positions as $p) {
    $empId = (int) scalar($db, "SELECT id FROM employees WHERE company_id={$co}
                                 AND name='" . $db->real_escape_string($p['name']) . "' LIMIT 1");
    if ($empId <= 0) {
        if ($apply) {
            $st = $db->prepare("INSERT INTO employees (company_id, name, employee_code, phone, employee_type)
                                VALUES (?,?,?,'—','موظف')");
            $ec = $p['code'] . '-' . substr(sha1($p['key'] . $co), 0, 4);
            $st->bind_param('iss', $co, $p['name'], $ec);
            $st->execute();
            $empId = $st->insert_id;
            $st->close();
            ok("موظفٌ جديد #{$empId} — {$p['name']}");
        } else { skip("سيُنشأ موظف — {$p['name']}"); }
    } else { skip("موظفٌ قائم #{$empId} — {$p['name']}"); }

    $uid = (int) scalar($db, "SELECT id FROM users WHERE employee_id={$empId} LIMIT 1");
    if ($uid <= 0 && $empId > 0) {
        if ($apply) {
            /* ◆ الحسابُ بلا كلمةِ مرورٍ صالحة — يُنشأ مربوطًا ولا يُدخَل به. */
            $st = $db->prepare("INSERT INTO users
                    (name, username, password, phone, role, company_id, employee_id, status, force_password_change)
                    VALUES (?,?,?,'—',?,?,?,'active',1)");
            $un = $p['code'] . '@equipation.sd';
            $pw = NO_PASSWORD;
            $rl = (string) $p['role'];
            $st->bind_param('ssssii', $p['name'], $un, $pw, $rl, $co, $empId);
            if (!$st->execute()) { say('  ✗ تعذّر إنشاءُ الحساب: ' . $st->error); }
            $uid = $st->insert_id;
            $st->close();
            /* `role_id` رقمًا أيضًا — فالعمودان يُقرآن معًا في المنصة. */
            $db->query("UPDATE users SET role_id={$p['role']} WHERE id={$uid}");
            ok("حسابٌ جديد #{$uid} — {$un} · دور {$p['role']} · بلا كلمةِ مرورٍ صالحة");
        } else { skip("سيُنشأ حساب — {$p['code']}@equipation.sd"); }
    } elseif ($uid > 0) { skip("حسابٌ قائم #{$uid}"); }

    $made[$p['key']] = array('emp' => $empId, 'uid' => $uid, 'role' => $p['role'], 'spec' => $p['spec'], 'name' => $p['name']);
}

/* ═══ ② صفوفُ المحاسبةِ للتخصصين ═══════════════════════════════════════════ */
say("\n② نسبُ محاسبَي التمويلِ والضرائبِ إلى تخصصَيهما");
/* `admin_module` عمودٌ قائمٌ من M-10 لا يحمل قيمةً للتمويلِ ولا للضرائب — فيُملأ
   بأقربِ نطاقٍ تشغيليٍّ لهما، و**التخصصُ المحكومُ في `spec_code`** وهو المقروء. */
$modFor = array('ACC-05' => 'treasury', 'ACC-09' => 'treasury');
foreach (array('acc05', 'acc09') as $k) {
    $m = $made[$k];
    if ($m['emp'] <= 0) { continue; }
    $exists = (int) scalar($db, "SELECT COUNT(*) FROM fin_accountants
                                  WHERE company_id={$co} AND employee_id={$m['emp']}");
    if ($exists) { skip("قائمٌ سلفًا — {$m['spec']}"); continue; }
    if (!$apply) { skip("سيُنسب — {$m['spec']}"); continue; }
    $unit = unit_for($db, $co, $m['spec'], $m['name'], $apply);
    if ($unit <= 0) { bad("{$m['spec']} — تعذّر تحديدُ وحدةِ الماليةِ فلا يُنسب"); continue; }
    $note = 'أُسند بأداةِ التزويد — سدُّ تخصصٍ تصله مساراتٌ ولا حاملَ له';
    $id = must($db, "INSERT INTO fin_accountants
            (company_id, employee_id, admin_module, finance_unit_id, specialization, spec_code, scope_note, active)
            VALUES (?,?,?,?,?,?,?,1)",
        'iisisss', array($co, $m['emp'], $modFor[$m['spec']], $unit, $m['name'], $m['spec'], $note),
        "محاسبُ {$m['spec']}");
    if ($id > 0) { ok("محاسبٌ للتخصص {$m['spec']} — {$m['name']} · وحدة #{$unit}"); }
}

/* ═══ ③ حساباتُ الدخولِ للمحاسبينَ بلا حساب ════════════════════════════════ */
say("\n③ حساباتُ الدخولِ للمحاسبينَ الذين لا حسابَ لهم");
$rows = array();
$r = $db->query("SELECT a.id, a.employee_id, a.spec_code, e.name
                   FROM fin_accountants a
                   JOIN employees e ON e.id = a.employee_id
                   LEFT JOIN users u ON u.employee_id = a.employee_id
                  WHERE a.company_id = {$co} AND a.active = 1
                    AND (a.is_deleted IS NULL OR a.is_deleted = 0) AND u.id IS NULL
                  ORDER BY a.id");
while ($r && $x = $r->fetch_assoc()) { $rows[] = $x; }
say('  المحاسبونَ بلا حساب: ' . count($rows));
$seq = 0;
foreach ($rows as $x) {
    $seq++;
    if (!$apply) { skip("سيُنشأ حساب — {$x['name']} ({$x['spec_code']})"); continue; }
    $st = $db->prepare("INSERT INTO users
            (name, username, password, phone, role, company_id, employee_id, status, force_password_change)
            VALUES (?,?,?,'—','18',?,?,'active',1)");
    /* اسمُ الدخولِ مشتقٌّ من رقمِ الموظفِ فلا يتصادم ولا يحمل اسمًا عربيًّا. */
    $un = 'acc.' . $x['employee_id'] . '@equipation.sd';
    $pw = NO_PASSWORD;
    $st->bind_param('sssii', $x['name'], $un, $pw, $co, $x['employee_id']);
    if (!$st->execute()) { say('  ✗ ' . $x['name'] . ': ' . $st->error); $st->close(); continue; }
    $uid = $st->insert_id;
    $st->close();
    $db->query("UPDATE users SET role_id=18 WHERE id={$uid}");
    ok("#{$uid} {$un} — {$x['name']} ({$x['spec_code']})");
}

/* ═══ ④ التكليفُ عبرَ بوابتِه — لا حولَها ═════════════════════════════════ */
/* CEO-Y0121: المسمّى القياديُّ أو الرقابيُّ لا يسري بلا موافقةِ الرئيسِ الموثَّقة.
   فتُطلب التكليفاتُ وتُفحص تعارضًا ويقررها الرئيسُ — كما يقع في الحياة. */
say("\n④ تكليفُ حامليها عبرَ بوابةِ التكليف (CEO-Y0121)");
$ceo = (int) scalar($db, "SELECT id FROM users WHERE company_id={$co}
                            AND (role_id=9 OR role='9') AND status='active' ORDER BY id LIMIT 1");
if ($ceo <= 0) { say('  ✗ لا رئيسَ تنفيذيٌّ في هذا الكيان — التكليفاتُ تبقى معروضةً بلا قرار'); }
foreach ($positions as $p) {
    if ($p['role'] === 18) { continue; }              // المحاسبُ ليس قياديًّا ولا رقابيًّا
    $m = $made[$p['key']];
    if ($m['uid'] <= 0) { skip("{$p['name']} — لا حسابَ بعدُ"); continue; }
    $eff = AssignmentGate::isEffective($db, $co, $m['uid'], $p['role']);
    if (!empty($eff['ok']) && ($eff['kind'] ?? '') !== 'other') { skip("{$p['name']} — تكليفُه سارٍ سلفًا"); continue; }
    if (!$apply) { skip("سيُطلب تكليفٌ — {$p['name']}"); continue; }

    $no  = 'ASG-U13-' . $co . '-' . $p['role'];
    $req = AssignmentGate::request($db, array(
        'company_id' => $co, 'assignment_no' => $no,
        'subject_user_id' => $m['uid'], 'role_id' => $p['role'],
        'requested_by' => $ceo ?: $m['uid'],
        'scope_note' => 'تزويدُ حزمة update0013 — ' . $p['name'],
    ));
    if (empty($req['ok'])) { say('  ✗ ' . $p['name'] . ': ' . $req['reason']); continue; }
    if (($req['state'] ?? '') === 'blocked') {
        say('  ⚠ ' . $p['name'] . ' — محجوبٌ بتعارضٍ فلا يُعرض: ' . mb_substr($req['reason'], 0, 120));
        continue;
    }
    if ($ceo <= 0) { skip("{$p['name']} — عُرض وينتظر قرارَ الرئيس"); continue; }
    $dec = AssignmentGate::decide($db, array(
        'company_id' => $co, 'assignment_no' => $no, 'decided_by' => $ceo,
        'decision' => 'approved', 'decision_reason' => 'تزويدُ الحزمةِ بحامليها',
        'authority_ref' => 'update0013 · FMGR §4-3',
    ));
    if (empty($dec['ok'])) { say('  ✗ ' . $p['name'] . ': ' . $dec['reason']); continue; }
    ok("{$p['name']} — طُلب وفُحص وأقرَّه الرئيسُ فسرى");
}

/* ═══ ⑤ سجلُّ ترحيلِ الأدوارِ القديمة (FMGR §٤-٣) ═════════════════════════ */
say("\n⑤ سجلُّ ترحيلِ الأدوارِ القديمة — ولا يُحذف دورٌ قبلَ ترحيلِ حاملِه");
$mig = array(
    array(18, 'محاسب الإدارة المالية', null, 'ACC-01..ACC-10',
          'يُصنَّف إلى تخصصٍ من العشرةِ بنطاقٍ محدَّد · لا يُحذف بل يُخصَّص', 'FMGR-0018'),
    array(19, 'مدير الإدارة المالية', 32, '',
          'يُفصل عن رئيسِ الحساباتِ صريحًا · والمديرُ الماليُّ في الطبقةِ الثانية', 'FMGR-0019'),
    array(20, 'المراجع والمدقق المالي', 31, '',
          'يُفصل إلى اثنين: مراجعٌ ماليٌّ تشغيليٌّ داخلَ المالية …', 'FMGR-0020'),
    array(20, 'المراجع والمدقق المالي', 33, '',
          '… ومراجعٌ داخليٌّ مستقلٌّ لا يتبع الماليةَ بحال', 'FMGR-0020'),
    array(21, 'أمين الخزينة', 34, '',
          'يُفصل عن منفِّذِ المدفوعاتِ البنكية · بثلاثةِ أدوار', 'FMGR-0021'),
    array(21, 'أمين الخزينة', 35, '',
          '… وعن مُعِدِّ المطابقةِ البنكية — والثالثُ أمينُ الخزينةِ نفسُه', 'FMGR-0021'),
);
foreach ($mig as $m) {
    $before = (int) scalar($db, "SELECT COUNT(*) FROM users WHERE company_id={$co}
                                  AND (role_id={$m[0]} OR role='{$m[0]}')");
    $moved  = $m[2] !== null
            ? (int) scalar($db, "SELECT COUNT(*) FROM exec_assignments WHERE company_id={$co}
                                  AND role_id={$m[2]} AND state='approved'")
            : (int) scalar($db, "SELECT COUNT(*) FROM fin_accountants WHERE company_id={$co}
                                  AND spec_code<>'' AND active=1");
    $state = $moved > 0 ? 'done' : 'planned';
    if (!$apply) { skip("سيُسجَّل ترحيل {$m[0]} → " . ($m[2] ?: $m[3])); continue; }
    $st = $db->prepare("INSERT INTO fin_role_migration
            (company_id, old_role_id, old_role_name, new_role_id, new_spec_code, rule_text,
             doc_ref, holders_before, holders_moved, state)
            VALUES (?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE rule_text=VALUES(rule_text), holders_before=VALUES(holders_before),
              holders_moved=VALUES(holders_moved), state=VALUES(state)");
    $newRole = $m[2];
    $st->bind_param('iisisssiis', $co, $m[0], $m[1], $newRole, $m[3], $m[4], $m[5], $before, $moved, $state);
    $st->execute();
    $st->close();
    ok("ترحيل {$m[0]} « {$m[1]} » → " . ($m[2] ? "دور {$m[2]}" : $m[3]) . " · حاملون قبل {$before} · منقول {$moved} · {$state}");
}

/* ═══ الحصيلة ═════════════════════════════════════════════════════════════ */
say("\n" . str_repeat('─', 74));
$noSpec  = (int) scalar($db, "SELECT COUNT(*) FROM fin_acc_specializations s WHERE s.active=1
              AND NOT EXISTS (SELECT 1 FROM fin_accountants a WHERE a.spec_code=s.code
                              AND a.company_id={$co} AND a.active=1 AND (a.is_deleted IS NULL OR a.is_deleted=0))");
$noLogin = (int) scalar($db, "SELECT COUNT(*) FROM fin_accountants a
              LEFT JOIN users u ON u.employee_id=a.employee_id
             WHERE a.company_id={$co} AND a.active=1 AND (a.is_deleted IS NULL OR a.is_deleted=0) AND u.id IS NULL");
$roles   = (int) scalar($db, "SELECT COUNT(DISTINCT role_id) FROM users
             WHERE company_id={$co} AND role_id BETWEEN 31 AND 35");
$live    = (int) scalar($db, "SELECT COUNT(*) FROM exec_assignments
             WHERE company_id={$co} AND state='approved' AND role_id BETWEEN 31 AND 35");
say("تخصصاتٌ بلا حامل: $noSpec  ·  محاسبونَ بلا حساب: $noLogin");
say("أدوارٌ جديدةٌ لها حاملٌ: $roles/5  ·  تكليفاتٌ ساريةٌ بموافقةِ الرئيس: $live/5");
if ($FAILED > 0) { say("\n✗ إخفاقاتٌ لم تُبتلع: $FAILED — عالجها ثم أعد التشغيل (idempotent)"); }
if ($apply) {
    say("\n◆ الحساباتُ المُنشأةُ بلا كلمةِ مرورٍ صالحة — ولا يُدخَل بها حتى يضبطها");
    say("  مديرُ الصلاحياتِ من داخلِ النظام. إنشاءُ بيانِ اعتمادٍ قرارُ مالكٍ لا أداة.");
} else {
    say("\nمعاينةٌ فقط — أضف --apply للتنفيذ");
}
$db->close();
