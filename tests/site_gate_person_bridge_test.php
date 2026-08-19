<?php
/**
 * tests/site_gate_person_bridge_test.php — جسرُ إذنِ دخولِ الأشخاص
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ يثبتُ بالسلوكِ لا بالكود:
 *   ① مرجعٌ مجهولٌ (شخصٌ أو موقعٌ) ⇒ **رفضٌ برمزٍ محكوم** لا كتابةٌ بصفر.
 *   ② مرجعٌ صحيحٌ ⇒ صفٌّ **يحمل مفاتيحَه الأربعةَ محلولةً**.
 *   ③ المعتمِدُ **من الجلسةِ** — ولو كتبَ المُدخِلُ اسمًا آخرَ في الحقلِ أُهمل.
 *   ④ المورِّدُ **وصفٌ لا شرط**: غيابُه لا يمنع الإذنَ (والمعدةُ عكسُه).
 *   ⑤ الشاشةُ مسجَّلةٌ في `cmp03_bridged_screens` — وإلا فالجسرُ لا يُنادى أصلًا.
 *
 * ◆ **وكلُّ صفٍّ يكتبه هذا الفاحصُ يُمحى في نهايتِه** بوسمٍ فريدٍ لهذه العملية —
 *   ودرسُ «وسمُ getmypid يُعمي الجولةَ عن سابقتِها» محفوظ: الكنسُ **بالعائلة**
 *   (`SGP-TEST-%`) لا بوسمِ هذه العمليةِ وحدَه.
 *
 * التشغيل: php tests/site_gate_person_bridge_test.php
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
require_once $ROOT . '/includes/cmp03_domain_bridge.php';

$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$CO   = 4;
$MARK = 'SGP-TEST-' . getmypid();
$pass = 0; $fail = 0;

function ok(string $t, bool $c, string $note = '') {
    global $pass, $fail;
    if ($c) { $pass++; echo "  ✔ {$t}" . ($note ? " — {$note}" : '') . "\n"; }
    else    { $fail++; echo "  ✘ {$t}" . ($note ? " — {$note}" : '') . "\n"; }
}

/* ══ الكنسُ بالعائلةِ قبلًا: بقايا جولةٍ سابقةٍ تُفسدُ الحكم ══════════════ */
mysqli_query($conn, "DELETE FROM scr_site_gate_person WHERE no_permit LIKE 'SGP-TEST-%'");

/* ══ مراجعُ حقيقيةٌ من القاعدة ══════════════════════════════════════════ */
$r = mysqli_query($conn, "SELECT id, employee_code, name FROM employees WHERE company_id = {$CO} AND employee_code IS NOT NULL AND TRIM(employee_code) <> '' LIMIT 1");
$emp = $r ? mysqli_fetch_assoc($r) : null;
$r = mysqli_query($conn, "SELECT id, name FROM project WHERE company_id = {$CO} LIMIT 1");
$prj = $r ? mysqli_fetch_assoc($r) : null;
$r = mysqli_query($conn, "SELECT id, name FROM users WHERE company_id = {$CO} LIMIT 1");
$usr = $r ? mysqli_fetch_assoc($r) : null;

if (!$emp || !$prj || !$usr) {
    echo "⊘ NOT_MEASURED — لا موظفَ أو موقعَ أو مستخدمَ في الكيان {$CO}\n";
    exit(2);
}

echo "══ جسرُ إذنِ دخولِ الأشخاص ══\n";
echo "  المراجعُ المقيسة: موظف #{$emp['id']} · موقع #{$prj['id']} · معتمِد #{$usr['id']}\n";

/* ══ ⑤ التسجيلُ في المصدرِ الواحد ══════════════════════════════════════ */
$map = cmp03_bridged_screens();
ok('الشاشةُ مسجَّلةٌ في مصدرِ الجسور', isset($map['site_gate_person.php']));

/* ══ ① مرجعٌ مجهول: شخصٌ لا وجودَ له ═══════════════════════════════════ */
$res = cmp03_bridge_write($conn, $CO, 'site_gate_person.php', array(
    'رقم الإذن' => $MARK . '-A', 'كود المشغل' => 'لا-وجود-له-٩٩٩٩',
    'الموقع' => $prj['name'],
), 'draft', (int) $usr['id']);
ok('شخصٌ مجهولٌ ⇒ رفضٌ برمزٍ محكوم',
   is_array($res) && !$res['ok'] && $res['code'] === 'SGP-422',
   is_array($res) ? $res['code'] : 'null');

/* ══ ① مرجعٌ مجهول: موقعٌ لا وجودَ له ══════════════════════════════════ */
$res = cmp03_bridge_write($conn, $CO, 'site_gate_person.php', array(
    'رقم الإذن' => $MARK . '-B', 'كود المشغل' => $emp['employee_code'],
    'الموقع' => 'موقعٌ-لا-وجودَ-له-٩٩٩٩',
), 'draft', (int) $usr['id']);
ok('موقعٌ مجهولٌ ⇒ رفضٌ برمزٍ محكوم',
   is_array($res) && !$res['ok'] && $res['code'] === 'SGP-422',
   is_array($res) ? $res['code'] : 'null');

/* ══ ④ بلا مورِّدٍ — وصفٌ لا شرط ═══════════════════════════════════════ */
$res = cmp03_bridge_write($conn, $CO, 'site_gate_person.php', array(
    'رقم الإذن' => $MARK . '-C', 'نوع الإذن' => 'دخول',
    'كود المشغل' => $emp['employee_code'], 'الموقع' => $prj['name'],
    'اعتماد مدير الموقع' => 'اسمٌ كتبه المُدخِلُ بيدِه — يجب أن يُهمَل',
), 'draft', (int) $usr['id']);
ok('بلا مورِّدٍ ⇒ يُقبل', is_array($res) && $res['ok'], is_array($res) ? $res['code'] : 'null');

/* ══ ② و③ الصفُّ يحمل مفاتيحَه والمعتمِدُ من الجلسة ═════════════════════ */
if (is_array($res) && $res['ok']) {
    $id = (int) $res['id'];
    $q  = mysqli_query($conn, "SELECT employee_id, site_project_id, supplier_entity_id,
                                      approved_by_user, approval_manager_site
                                 FROM scr_site_gate_person WHERE id = {$id}");
    $row = $q ? mysqli_fetch_assoc($q) : null;
    ok('المفتاحُ يحمل الشخصَ المحلول',
       $row && (int) $row['employee_id'] === (int) $emp['id'],
       $row ? "employee_id={$row['employee_id']}" : 'لا صف');
    ok('المفتاحُ يحمل الموقعَ المحلول',
       $row && (int) $row['site_project_id'] === (int) $prj['id'],
       $row ? "site_project_id={$row['site_project_id']}" : 'لا صف');
    ok('المورِّدُ NULL لا صفرٌ مخترَع',
       $row && $row['supplier_entity_id'] === null,
       $row ? var_export($row['supplier_entity_id'], true) : 'لا صف');
    ok('المعتمِدُ من الجلسةِ لا من الحقل',
       $row && (int) $row['approved_by_user'] === (int) $usr['id']
            && trim((string) $row['approval_manager_site']) === trim((string) $usr['name']),
       $row ? "approved_by_user={$row['approved_by_user']} · نصّ=«{$row['approval_manager_site']}»" : 'لا صف');
}

/* ══ الكنسُ بالعائلة ═══════════════════════════════════════════════════ */
mysqli_query($conn, "DELETE FROM scr_site_gate_person WHERE no_permit LIKE 'SGP-TEST-%'");
$left = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM scr_site_gate_person WHERE no_permit LIKE 'SGP-TEST-%'"))[0];
ok('الكنسُ اكتمل — صفرُ أثرٍ للفاحص', (int) $left === 0, "متبقٍّ={$left}");

echo "\n" . ($fail === 0 ? '✔' : '✘') . " النتيجة: {$pass} ناجحًا · {$fail} راسبًا\n";
exit($fail === 0 ? 0 : 1);
