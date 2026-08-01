<?php
/**
 * tests/install_proof.php — إثباتُ صلاحية نظامٍ مثبَّتٍ حديثًا
 * ═══════════════════════════════════════════════════════════════════════════
 * حارسُ الانحراف يُثبت أنّ **البنية** مطابقة. هذا يُثبت أنّ النظام **يعمل**:
 * شروطُ الدخول مستوفاة، والبذرةُ كاملةٌ بطبقتيها، وما يجب أن يبقى فارغًا فارغ.
 *
 * الاستخدام (بعد تشغيل المُثبِّت على قاعدة اختبار):
 *   php tests/install_proof.php --db=ems_installtest --user=installtest --pass=...
 *
 * دورةُ الإثبات الكاملة موثَّقة في docs/INSTALL_ar.md §٦.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('403 — CLI only.');
}

error_reporting(E_ALL & ~E_DEPRECATED);

$opt = array('host' => 'localhost', 'dbuser' => 'root', 'dbpass' => '', 'db' => '', 'user' => '', 'pass' => '');
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z]+)=(.*)$/is', $a, $m) && array_key_exists(strtolower($m[1]), $opt)) {
        $opt[strtolower($m[1])] = $m[2];
    }
}
if ($opt['db'] === '' || $opt['user'] === '' || $opt['pass'] === '') {
    fwrite(STDERR, "الاستخدام: php tests/install_proof.php --db=<db> --user=<username> --pass=<password>\n");
    fwrite(STDERR, "           [--host= --dbuser= --dbpass=]\n");
    exit(2);
}

$c = @new mysqli($opt['host'], $opt['dbuser'], $opt['dbpass'], $opt['db']);
if ($c->connect_error) {
    fwrite(STDERR, "فشل الاتصال: {$c->connect_error}\n");
    exit(2);
}
$c->set_charset('utf8mb4');

$fails = 0;
function t($cond, $label, $detail = '')
{
    global $fails;
    if (!$cond) {
        $fails++;
    }
    printf("  %s %-46s %s\n", $cond ? '✔' : '✘', $label, $detail);
}
function scalarOf(mysqli $c, $sql)
{
    $r = $c->query($sql);
    if (!$r) {
        return -1;
    }
    $row = $r->fetch_row();
    $r->free();
    return (int) $row[0];
}

echo "\n═══ ① شروطُ login.php ═══\n";
// الاستعلامُ نفسُه الذي يستعمله login.php — أيُّ انحرافٍ في الأعمدة يظهر هنا.
$stmt = $c->prepare(
    "SELECT id,name,username,password,phone,role,project_id,contract_id,company_id,
            parent_id,created_at,updated_at,employee_id
     FROM users WHERE username=? LIMIT 1"
);
$stmt->bind_param('s', $opt['user']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

t($user !== null, 'الحساب موجود', $user ? "id={$user['id']}" : 'مفقود');
if ($user === null) {
    echo "\n✘ لا حساب — بقيّةُ الإثباتات لا معنى لها.\n\n";
    exit(1);
}
t(password_verify($opt['pass'], $user['password']), 'password_verify يقبل كلمة المرور');
t(intval($user['employee_id']) > 0, '«لا حساب بلا موظّف»', "employee_id={$user['employee_id']}");
t(intval($user['company_id']) > 0, 'الحساب مربوطٌ بشركة', "company_id={$user['company_id']}");

$e = $c->query('SELECT id, company_id, name FROM employees WHERE id = ' . intval($user['employee_id']))->fetch_assoc();
t($e !== null, 'صفُّ الموظّف موجودٌ فعلًا', $e ? $e['name'] : '');
t($e && intval($e['company_id']) === intval($user['company_id']), 'الموظّف والحساب في الشركة نفسها');

$role = $c->query('SELECT id, name FROM roles WHERE id = ' . intval($user['role']))->fetch_assoc();
t($role !== null, 'الدورُ معرَّفٌ في roles', $role ? "{$role['id']} — {$role['name']}" : 'مفقود');

echo "\n═══ ② البذرةُ العالمية ═══\n";
foreach (array(
    'roles' => 25, 'modules' => 208, 'role_permissions' => 1008, 'link_groups' => 16,
    'nav_items' => 613, 'equipments_types' => 3, 'failure_codes' => 402,
) as $tbl => $n) {
    $got = scalarOf($c, "SELECT COUNT(*) FROM `{$tbl}`");
    t($got === $n, "الجدول {$tbl}", "{$got} / {$n}");
}

echo "\n═══ ③ البذرةُ المستأجَرة — company_id مُحقَن ═══\n";
$cid = intval($user['company_id']);
foreach (array(
    'fin_chart_of_accounts' => 16, 'fin_approval_matrix' => 4, 'fin_effect_map' => 11,
    'job_titles' => 16, 'employee_roles' => 9, 'transfer_types' => 6,
) as $tbl => $n) {
    $got  = scalarOf($c, "SELECT COUNT(*) FROM `{$tbl}`");
    $mine = scalarOf($c, "SELECT COUNT(*) FROM `{$tbl}` WHERE company_id = {$cid}");
    t($got === $n && $mine === $n, "الجدول {$tbl}", "{$got} صفًّا · {$mine} بـcompany_id={$cid}");
}

echo "\n═══ ④ ما يجب أن يبقى فارغًا ═══\n";
// تسرُّبُ بياناتِ المصدر إلى تنصيبٍ جديدٍ عطبٌ صامت — يُقاس صراحةً.
foreach (array('ems_sequences', 'contracts', 'equipments', 'timesheet',
               'fin_financial_events', 'ems_business_events') as $tbl) {
    $got = scalarOf($c, "SELECT COUNT(*) FROM `{$tbl}`");
    t($got === 0, "الجدول {$tbl} فارغ", "{$got} صفًّا");
}
t(scalarOf($c, 'SELECT COUNT(*) FROM admin_companies') === 1, 'شركةٌ واحدةٌ فقط (لا شركاتُ المصدر)');
t(scalarOf($c, 'SELECT COUNT(*) FROM users') === 1, 'حسابٌ واحدٌ فقط');

echo "\n═══ ⑤ التنقّلُ والصلاحيات لدور الحساب ═══\n";
$rid = intval($user['role']);
$nav = scalarOf($c, "SELECT COUNT(*) FROM nav_items WHERE role_id = {$rid}");
t($nav > 0, "nav_items للدور {$rid}", "{$nav} عنصرًا");
$perm = scalarOf($c, "SELECT COUNT(*) FROM role_permissions WHERE role_id = {$rid}");
t($perm > 0, "role_permissions للدور {$rid}", "{$perm} صفًّا");

echo "\n" . str_repeat('─', 70) . "\n";
if ($fails === 0) {
    echo "✔ كلُّ الإثباتات نجحت.\n\n";
    exit(0);
}
fwrite(STDERR, "✘ {$fails} إثباتًا فشل.\n\n");
exit(1);
