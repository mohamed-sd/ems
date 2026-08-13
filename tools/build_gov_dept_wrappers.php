<?php
/**
 * tools/build_gov_dept_wrappers.php — مُولِّدُ أغلفةِ «حوكمة الإدارة» (مرّةً)
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ INJ-0123 · INJ-0171 · INJ-0201 · INJ-0211 · INJ-0230 · INJ-0266 ·
 *   INJ-0267 · INJ-0281 · INJ-0304 · INJ-0337 · INJ-0355 · INJ-0372 · INJ-0485
 *
 * المكوّنُ `includes/dept_gov_space.php` واحدٌ، والشاشةُ الحاملةُ تضبط عقدَ
 * `$GOV_DEPT` ثم تضمّنه (INJAZ-CORE-01 §12-1 الباب ١١).
 *
 * ── وكلُّ حقلٍ في العقدِ **مقيسٌ من الحيِّ** ─────────────────────────────────
 * ◆ `team_roles` — تُقرأ من `risk_role_org_unit()` في `Risk/_risk_common.php`
 *   مقلوبةً؛ فهي المصدرُ الذي يحكم الزاويةَ فعلًا، ولو كُتبت بيدٍ لتفرّقت.
 * ◆ `module_like` — بادئةُ مجلدِ الإدارةِ **بعد استبعادِ الأغلفةِ المشتركة**
 *   (`main/` · `Portal/` · `Reports/`)؛ فإدراجُها يجعل لوحةَ الحوكمةِ تسرد
 *   كلَّ شاشةٍ مشتركةٍ فتغرق الإدارةُ في ضوضاءِ غيرِها.
 * ◆ `events_module` — **رمزُ الإدارةِ نفسُه**. ولم أُسنِد إدارةً إلى وحدةِ
 *   أحداثٍ أخرى لتظهر أرقامٌ كبيرة: عدّادٌ يعرض أحداثَ غيرِها أسوأُ من صفرٍ
 *   صادقٍ يقول «لا حدثَ منشورٌ باسمِ هذه الإدارة».
 * ◆ `sod_queries` — **خاويةٌ حيث لا قياسَ معرَّف**. والمكوّنُ عُدِّل فصار
 *   يُعلن «لا قياسَ معلَن» بدل «✔ صفر خرق» — فبراءةٌ عمّا لم يُقَس أخطرُ من
 *   غيابِ اللوحة. ومَن تملك عقودُها قياسًا حقيقيًّا تأخذه.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$CO = 4;
$force = in_array('--force', $argv, true);
$dry   = in_array('--dry', $argv, true);

/* لاحقة ⇒ [رمزُ الوحدة, مجلدُ الشاشات, بادئةُ الجداولِ الحساسة, الأيقونة] */
$MAP = array(
    'cap' => array('financing',       array('Financing/'),             'fin%',      'fa fa-scale-balanced'),
    'crp' => array('tickets',         array('Tickets/'),               'ticket%',   'fa fa-scale-balanced'),
    'flt' => array('fleet',           array('Equipments/', 'Fleet/'),  'equipment%','fa fa-scale-balanced'),
    'hrm' => array('hr',              array('Workforce/'),             'employee%', 'fa fa-scale-balanced'),
    'inv' => array('warehouse',       array('Procurement/'),           'stock%',    'fa fa-scale-balanced'),
    'mnt' => array('maintenance',     array('Maintenance/'),           'mnt%',      'fa fa-scale-balanced'),
    'ops' => array('ops',             array('Operations/'),            'op%',       'fa fa-scale-balanced'),
    'prc' => array('procurement_ops', array('Procurement/'),           'proc%',     'fa fa-scale-balanced'),
    'sit' => array('movement',        array('Operations/'),            'site%',     'fa fa-scale-balanced'),
    'trp' => array('transport',       array('Transport/'),             'trs%',      'fa fa-scale-balanced'),
    'wrk' => array('operators',       array('Workforce/', 'Timesheet/'), 'worker%', 'fa fa-scale-balanced'),
);
/* مجلدُ الوجهةِ لكلِّ غلافٍ — بجوارِ شاشاتِ إدارتِه كما فعلت الأربعُ القائمة */
$DIR = array(
    'cap' => 'Financing', 'crp' => 'Tickets',     'flt' => 'Equipments',
    'hrm' => 'Workforce', 'inv' => 'Procurement', 'mnt' => 'Maintenance',
    'ops' => 'Operations','prc' => 'Procurement', 'sit' => 'Operations',
    'trp' => 'Transport', 'wrk' => 'Workforce',
);

/* أدوارُ كلِّ وحدةٍ — من الشيفرةِ الحاكمة */
$common = (string) file_get_contents($ROOT . '/Risk/_risk_common.php');
$s = strpos($common, 'function risk_role_org_unit');
$e = $s !== false ? strpos($common, 'return isset($map', $s) : false;
if ($s === false || $e === false) { exit("✘ تعذّر قراءةُ خريطةِ الأدوار\n"); }
preg_match_all("~'(\d+)'\s*=>\s*(\d+)~", substr($common, $s, $e - $s), $mm, PREG_SET_ORDER);
$roleUnit = array();
foreach ($mm as $x) { $roleUnit[(int) $x[1]] = (int) $x[2]; }

$units = array();
$r = $conn->query("SELECT unit_id, unit_code, name_ar FROM org_units WHERE company_id = {$CO} AND active = 1");
while ($r && ($x = $r->fetch_assoc())) { $units[$x['unit_code']] = $x; }
foreach ($MAP as $sfx => $m) {
    if (!isset($units[$m[0]])) { exit("✘ وحدةُ «{$m[0]}» غيرُ موجودة — لا غلافَ بلا زاوية\n"); }
}

echo "══ مُولِّدُ أغلفةِ حوكمةِ الإدارة — " . count($MAP) . " إدارةً\n\n";
$made = 0; $skip = 0;

foreach ($MAP as $sfx => $m) {
    list($code, $modLike, $sens, $icon) = $m;
    $unit = $units[$code];
    $dept = $unit['name_ar'];
    $dir  = $DIR[$sfx];
    if (!is_dir($ROOT . '/' . $dir)) { exit("✘ مجلدُ «{$dir}» غيرُ موجود\n"); }
    $path = $ROOT . '/' . $dir . '/gov_dept_' . $sfx . '.php';
    if (is_file($path) && !$force) { echo "   ○ قائمٌ — {$dir}/gov_dept_{$sfx}.php\n"; $skip++; continue; }

    $roles = array();
    foreach ($roleUnit as $role => $u) { if ($u === (int) $unit['unit_id']) { $roles[] = $role; } }
    sort($roles);
    if (!$roles) { exit("✘ لا أدوارَ للوحدة «{$code}» — لوحةُ حوكمةٍ بلا فريقٍ خواء\n"); }

    $rolesPhp = 'array(' . implode(', ', $roles) . ')';
    $likePhp  = "array('" . implode("', '", $modLike) . "')";
    $rel      = $dir . '/gov_dept_' . $sfx . '.php';

    $src = <<<PHP
<?php
/**
 * {$rel} — حوكمة {$dept}
 * ─────────────────────────────────────────────────────────────────────────
 * ظهورٌ نطاقيٌّ لمكوّن «حوكمة الإدارة» الواحد على {$dept}:
 * حساباتُها وصلاحياتُ شاشاتِها وفصلُ واجباتِها وسجلاتُ تدقيقها
 * (INJAZ-CORE-01 §12-1 الباب ١١ — «مكوّنٌ واحدٌ لا نسخةٌ لكلٍّ»).
 * قراءةٌ لا كتابة، والفعلُ الكاتبُ الوحيدُ تصديقُ مراجعةِ الوصول — يشهد ولا
 * يمنح، ونطاقُه `gov_dept_{$sfx}` يُطابَق على سجلِّ الشاشاتِ في المعالج.
 *
 * ◆ عدّادُ الأحداثِ مقصورٌ على `source_module = '{$code}'` — فما ظهر صفرًا
 *   يعني «لا حدثَ منشورٌ باسمِ هذه الإدارة»، لا «لا أحداثَ في النظام».
 * ◆ ولا قياسَ فصلِ واجباتٍ معلَنًا بعدُ لهذه الإدارة — والمكوّنُ يُعلن ذلك
 *   صراحةً ولا يعرض «صفرَ خرق»، فبراءةٌ عمّا لم يُقَس أخطرُ من غيابِ اللوحة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset(\$_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/screen_contract.php';

\$current_role   = strval(\$_SESSION['user']['role'] ?? '');
\$is_super_admin = (\$current_role === '-1');
\$company_id     = intval(\$_SESSION['user']['company_id'] ?? 0);
if (!\$is_super_admin && \$company_id <= 0) { header('Location: ../login.php'); exit(); }

\$__pp = check_page_permissions(\$conn, '{$rel}');
if (!\$is_super_admin && empty(\$__pp['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا تملك صلاحية عرض حوكمة الإدارة', 'GOV-PERM-403', 'صلاحيات الحوكمة يمنحها مدير الصلاحيات');
}
ems_shell_axes(\$__pp);

\$GOV_DEPT = array(
    'title'           => 'حوكمة {$dept}',
    'icon'            => '{$icon}',
    'module_like'     => {$likePhp},
    'team_roles'      => {$rolesPhp},
    'sensitive_like'  => '{$sens}',
    'events_module'   => '{$code}',
    'attest_endpoint' => '../Governance/gov_m14_actions.php',
    'attest_code'     => 'gov.{$sfx}.attest',
    'attest_scope'    => 'gov_dept_{$sfx}',
    /* لا قياسَ فصلِ واجباتٍ معرَّفًا بعقودِ هذه الإدارة — يُعلَن ولا يُلفَّق */
    'sod_queries'     => array(),
);

require __DIR__ . '/../includes/dept_gov_space.php';

PHP;
    if (!$dry) { file_put_contents($path, $src); }
    printf("   ✔ %-34s أدوار: %-12s شاشات: %s\n", $rel, implode(',', $roles), implode(' ', $modLike));
    $made++;
}

echo "\n── المحصّلة: أُنشئ {$made} · قائمٌ سلفًا {$skip}\n";
echo ($dry ? "   (جسٌّ فقط)\n" : "   ◆ التسجيلُ بهجرةٍ منفصلة.\n");
