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
/* ◆ **العددُ المجمَّدُ يتعفَّن، والسقّاطةُ لا**: كانت التوقّعاتُ حروفًا مكتوبةً
 *   هنا (roles=25 · modules=208 · nav_items=613)، ونمت البذرةُ نموًّا مشروعًا
 *   فصار الشاهدُ يرسُب على **نجاحِ التثبيتِ نفسِه** — أحدَ عشرَ رسوبًا كلُّها في
 *   المقياسِ لا في المُثبِّت. ⇒ يُقاس بأساسٍ مُسجَّلٍ يتحرّك **بقرارٍ صريح**
 *   (`--set`) لا صمتًا، كسقّاطةِ الدَّين: **النقصُ رسوبٌ دائمًا** فبذرةٌ ناقصةٌ
 *   تُمسَك، والنموُّ يُعلَن ويُعتمَد بيدٍ لا بتعفُّن. */
$seedBasePath = dirname(__DIR__) . '/docs/install_seed_baseline.json';
$seedBase = is_file($seedBasePath)
    ? (array) json_decode((string) file_get_contents($seedBasePath), true) : array();
$seedSet = in_array('--set', $argv, true);
$seedNow = array();
foreach (array('roles', 'modules', 'role_permissions', 'link_groups',
               'nav_items', 'equipments_types', 'failure_codes') as $tbl) {
    $got = scalarOf($c, "SELECT COUNT(*) FROM `{$tbl}`");
    $seedNow[$tbl] = $got;
    if ($seedSet) { echo "  · {$tbl} = {$got}\n"; continue; }
    if (!array_key_exists($tbl, $seedBase)) {
        t(false, "الجدول {$tbl}", "{$got} صفًّا · **بلا أساسٍ مسجَّل** — يُسجَّل بـ--set بقرار");
        continue;
    }
    $exp = (int) $seedBase[$tbl];
    t($got >= $exp, "الجدول {$tbl}", $got === $exp ? "{$got}"
        : ($got > $exp ? "{$got} · نما عن الأساس {$exp} (+" . ($got - $exp) . ") — يُعتمَد بـ--set"
                       : "**{$got} < الأساس {$exp}** — بذرةٌ ناقصة"));
}
if ($seedSet) {
    file_put_contents($seedBasePath, json_encode($seedNow, JSON_PRETTY_PRINT));
    echo "  ✔ سُجِّل أساسُ البذرةِ في docs/install_seed_baseline.json\n";
}

echo "\n═══ ③ البذرةُ المستأجَرة — company_id مُحقَن ═══\n";
$cid = intval($user['company_id']);
/* ◆ **والحكمُ هنا على الإسنادِ لا على العدد**: الخطرُ الذي يقيسه هذا القسمُ
 *   هو **تسرُّبُ صفٍّ لا يحمل شركةَ التثبيت** — صفرُ صفٍّ يتيمٍ وصفرُ صفٍّ
 *   لشركةٍ أخرى. أما كم صفًّا بذرَ دليلُ الحساباتِ فينمو مشروعًا، وتجميدُه
 *   جعل الشاهدَ يرسُب على بذرةٍ أغنى — **والغنى ليس عطبًا**. */
foreach (array('fin_chart_of_accounts', 'fin_approval_matrix', 'fin_effect_map',
               'job_titles', 'employee_roles', 'transfer_types') as $tbl) {
    $got  = scalarOf($c, "SELECT COUNT(*) FROM `{$tbl}`");
    $mine = scalarOf($c, "SELECT COUNT(*) FROM `{$tbl}` WHERE company_id = {$cid}");
    $seedNow['tenant:' . $tbl] = $got;
    $exp  = isset($seedBase['tenant:' . $tbl]) ? (int) $seedBase['tenant:' . $tbl] : null;
    $ok   = ($got > 0 && $mine === $got && ($exp === null || $got >= $exp));
    t($ok, "الجدول {$tbl}", "{$got} صفًّا · {$mine} بـcompany_id={$cid}"
        . ($exp !== null && $got < $exp ? " · **دون الأساس {$exp}**" : '')
        . ($mine !== $got ? ' · **صفوفٌ لا تحمل شركةَ التثبيت: ' . ($got - $mine) . '**' : ''));
}
if ($seedSet) {
    file_put_contents($seedBasePath, json_encode($seedNow, JSON_PRETTY_PRINT));
}

/* ═══ NF-30 · حياةُ الأعمدةِ المُعلَنة — لا وجودُ الصفِّ وحدَه ═══════════════
 * ◆ **الكشفُ الذي أوجبه**: «فحصُ التسليم يمرّر السجلَّ الموحدَ بمئةٍ بالمئة
 *   بينما **عمودا الاسمِ القانونيِّ والتوليدِ فيه ميتان بالكامل** — الفحصُ
 *   يغطي وجودَ الصفِّ لا حياةَ أعمدتِه».
 * ◆ **وحياةُ العمودِ تُقاس بالمعمور لا بالوجود**: عمودٌ موجودٌ وكلُّ قيمِه
 *   فارغةٌ **عمودٌ ميت** — والصفُّ يمرُّ وهو أجوف. */
echo "\n═══ NF-30 · حياةُ الأعمدةِ المُعلَنة ═══\n";
/* ◆ **والعمودُ المُعلَنُ غيرُ الموجودِ رسوبٌ لا انفجار**: أوّلُ كتابةٍ سألت عن
 *   عمودٍ افترضتُه (`admin_companies.legal_name`) فلم يوجد **فانفجر الفاحصُ
 *   ولم يحكم** — وفاحصٌ ينفجر لا يقول شيئًا. ⇒ يُتحقَّق من وجودِ العمودِ أوّلًا،
 *   وغيابُه يُعلَن رسوبًا باسمِه. */
$colExists = function ($c, $tbl, $col) {
    $st = $c->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    if (!$st) { return false; }
    $st->bind_param('ss', $tbl, $col);
    $st->execute(); $st->bind_result($n); $st->fetch(); $st->close();
    return (int) $n === 1;
};
foreach (array(
    'admin_companies' => array('company_name'),
    'roles'           => array('name'),
    'modules'         => array('code', 'name'),
    'nav_items'       => array('label_ar', 'route'),
) as $tbl => $columns) {
    foreach ($columns as $col) {
        if (!$colExists($c, $tbl, $col)) {
            t(false, "عمودٌ حيٌّ {$tbl}.{$col}", '**عمودٌ مُعلَنٌ لا وجودَ له**');
            continue;
        }
        $tot  = scalarOf($c, "SELECT COUNT(*) FROM `{$tbl}`");
        $live = scalarOf($c, "SELECT COUNT(*) FROM `{$tbl}`
                               WHERE `{$col}` IS NOT NULL AND TRIM(`{$col}`) <> ''");
        t($tot === 0 || $live > 0, "عمودٌ حيٌّ {$tbl}.{$col}",
          $tot === 0 ? 'الجدولُ فارغٌ — خارجَ الحكم'
                     : "{$live}/{$tot} معمورًا" . ($live === 0 ? ' — **عمودٌ ميت**' : ''));
    }
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
