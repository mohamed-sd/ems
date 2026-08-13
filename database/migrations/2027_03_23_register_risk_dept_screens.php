<?php
/**
 * 2027_03_23_register_risk_dept_screens.php
 * ═══════════════════════════════════════════════════════════════════════════
 * تسجيلُ إحدى عشرةَ شاشةَ «مخاطر الإدارة» في سجلِّ الشاشاتِ ومنحُ صلاحياتِها.
 * ⇐ INJ-0133 · INJ-0208 · INJ-0217 · INJ-0281 · INJ-0304 · INJ-0337 ·
 *   INJ-0355 · INJ-0372 · INJ-0532 · INJ-0575
 *
 * ── لماذا التسجيلُ جزءٌ من الإصلاحِ لا تكملةٌ له ────────────────────────────
 * شاشةٌ **غيرُ مسجَّلةٍ** في `modules` تمرُّ من حارسِ الصلاحياتِ بـ**fail-open**
 * (`permissions_helper.php`) — فبناءُ الملفِّ وحدَه يفتح شاشةً للجميع. فالملفُّ
 * والصفُّ والمنحُ ثلاثةٌ لا ينفصل بعضُها.
 *
 * ── والخريطةُ مشتقّةٌ لا مخترَعة ────────────────────────────────────────────
 * ◆ `unit_code` لكلِّ إدارةٍ من `org_units` الحيِّ (تُرفض الهجرةُ إن غاب رمز).
 * ◆ وأدوارُ الإدارةِ **مقروءةٌ من `risk_role_org_unit()`** في
 *   `Risk/_risk_common.php` مقلوبةً — فهي المصدرُ الذي يحكم الزاويةَ فعلًا
 *   وقتَ التصيير. ولو كُتبت هنا بيدٍ لتفرّق المنحُ عن الزاوية.
 * ◆ ونمطُ المنحِ منسوخٌ من الشاشةِ القائمةِ `Risk/risk_dept_fin.php` (الوحدة
 *   345): أدوارُ الإدارةِ + الرئيس (9) + أدوارُ المخاطر (28·29·30) — **عرضًا
 *   فقط**، فالسجلُّ مركزيٌّ والتعديلُ يمرُّ بإدارة المخاطر (RK-02).
 *
 * ── وتتحقّق من نفسِها ───────────────────────────────────────────────────────
 * تُثبت بعد التسجيلِ أنَّ دورَ الإدارةِ يرى شاشتَه وأنَّ دورًا أجنبيًّا **لا** —
 * فمنحٌ للجميعِ ليس منحًا. وترسب إن اختلَّ أيُّهما.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_USER') : ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_PASS') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$CO = 4;

echo "══ تسجيلُ شاشاتِ «مخاطر الإدارة» ══\n\n";

/* ── ① خريطةُ اللاحقةِ ⇒ وحدةِ الهيكل ─────────────────────────────────────── */
$MAP = array(
    'cap' => 'financing',  'crp' => 'tickets',        'flt' => 'fleet',
    'hrm' => 'hr',         'inv' => 'warehouse',      'mnt' => 'maintenance',
    'ops' => 'ops',        'prc' => 'procurement_ops', 'sit' => 'movement',
    'trp' => 'transport',  'wrk' => 'operators',
);

/* ── ② أدوارُ كلِّ وحدةٍ — **تُقرأ من الشيفرةِ الحاكمةِ** لا تُكتب هنا ────────── */
$common = (string) file_get_contents($ROOT . '/Risk/_risk_common.php');
$s = strpos($common, 'function risk_role_org_unit');
$e = $s !== false ? strpos($common, 'return isset($map', $s) : false;
if ($s === false || $e === false) {
    fwrite(STDERR, "✘ تعذّر قراءةُ خريطةِ الأدوارِ من Risk/_risk_common.php — لا تُخمَّن\n");
    exit(2);
}
preg_match_all("~'(\d+)'\s*=>\s*(\d+)~", substr($common, $s, $e - $s), $mm, PREG_SET_ORDER);
$roleUnit = array();
foreach ($mm as $x) { $roleUnit[(int) $x[1]] = (int) $x[2]; }
if (count($roleUnit) < 10) {
    fwrite(STDERR, "✘ خريطةُ الأدوارِ قُرئت ناقصةً (" . count($roleUnit) . ") — يُوقَف\n");
    exit(2);
}
echo "  خريطةُ الأدوارِ مقروءةٌ من الشيفرة: " . count($roleUnit) . " دورًا\n";

/* ── ③ وحداتُ الهيكلِ الحيّة ──────────────────────────────────────────────── */
$units = array();
$r = $conn->query("SELECT unit_id, unit_code, name_ar FROM org_units WHERE company_id = {$CO} AND active = 1");
while ($r && ($x = $r->fetch_assoc())) { $units[$x['unit_code']] = $x; }
foreach ($MAP as $sfx => $code) {
    if (!isset($units[$code])) { fwrite(STDERR, "✘ وحدةُ «{$code}» غيرُ موجودة — لا تسجيلَ لشاشةٍ بلا زاوية\n"); exit(2); }
}

$RISK_ROLES = array(28, 29, 30);
$EXEC_ROLE  = 9;
$ord = 523;
$madeMod = 0; $madeGrant = 0; $existed = 0;

foreach ($MAP as $sfx => $code) {
    $unit  = $units[$code];
    $file  = 'Risk/risk_dept_' . $sfx . '.php';
    $label = 'مخاطر ' . $unit['name_ar'];

    if (!is_file($ROOT . '/' . $file)) {
        fwrite(STDERR, "✘ الملفُّ غيرُ موجود: {$file} — لا صفَّ لشاشةٍ لا ملفَّ لها\n");
        exit(2);
    }

    /* أدوارُ هذه الإدارة */
    $deptRoles = array();
    foreach ($roleUnit as $role => $uid2) { if ($uid2 === (int) $unit['unit_id']) { $deptRoles[] = $role; } }
    sort($deptRoles);
    $owner = $deptRoles ? $deptRoles[0] : $EXEC_ROLE;

    /* ── الصفُّ في سجلِّ الشاشات ── */
    $mid = null;
    $st = $conn->prepare('SELECT id FROM modules WHERE code = ? LIMIT 1');
    $st->bind_param('s', $file);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) { $mid = (int) $row['id']; }
    $st->close();

    if ($mid === null) {
        $st = $conn->prepare('INSERT INTO modules (name, code, owner_role_id, is_link, is_quick, icon, display_order)
                              VALUES (?, ?, ?, 1, 0, ?, ?)');
        $ic = 'fa fa-building-shield';
        $st->bind_param('ssisi', $label, $file, $owner, $ic, $ord);
        if (!$st->execute()) { fwrite(STDERR, "✘ تعذّر تسجيلُ {$file}: " . $st->error . "\n"); exit(2); }
        $mid = (int) $conn->insert_id;
        $st->close();
        $madeMod++;
    } else {
        $existed++;
    }
    $ord++;

    /* ── المنحُ: أدوارُ الإدارةِ + الرئيسُ + المخاطر — عرضًا فقط ── */
    $grantees = array_values(array_unique(array_merge($deptRoles, array($EXEC_ROLE), $RISK_ROLES)));
    sort($grantees);
    $added = 0;
    foreach ($grantees as $role) {
        $st = $conn->prepare('SELECT id FROM role_permissions WHERE role_id = ? AND module_id = ? LIMIT 1');
        $st->bind_param('ii', $role, $mid);
        $st->execute();
        $has = (bool) $st->get_result()->fetch_row();
        $st->close();
        if ($has) { continue; }
        $st = $conn->prepare('INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                              VALUES (?, ?, 1, 0, 0, 0)');
        $st->bind_param('ii', $role, $mid);
        if (!$st->execute()) { fwrite(STDERR, "✘ تعذّر منحُ الدور {$role} على {$file}: " . $st->error . "\n"); exit(2); }
        $st->close();
        $added++; $madeGrant++;
    }
    printf("  ✔ %-28s #%-4s %-26s أدوارُ الإدارة: %-14s منحٌ جديد: %d\n",
        $file, $mid, mb_substr($label, 0, 24),
        ($deptRoles ? implode(',', $deptRoles) : '—'), $added);
}

/* ── ④ التحقُّقُ الذاتيُّ: منحٌ يميّز — وإلا فليس منحًا ────────────────────── */
echo "\n── جسُّ التمييز\n";
$fails = 0;
/* دورُ التمويلِ (26) يرى مخاطرَ التمويلِ ولا يرى مخاطرَ المخازن */
$probe = function ($role, $file) use ($conn) {
    $st = $conn->prepare('SELECT rp.can_view FROM role_permissions rp
                            JOIN modules m ON m.id = rp.module_id
                           WHERE rp.role_id = ? AND m.code = ? LIMIT 1');
    $st->bind_param('is', $role, $file);
    $st->execute();
    $row = $st->get_result()->fetch_row();
    $st->close();
    return $row !== null && (int) $row[0] === 1;
};
$cases = array(
    array(26, 'Risk/risk_dept_cap.php', true,  'دورُ التمويلِ يرى مخاطرَ التمويل'),
    array(26, 'Risk/risk_dept_inv.php', false, 'ولا يرى مخاطرَ المخازن'),
    array(25, 'Risk/risk_dept_inv.php', true,  'وأمينُ المستودعِ يرى مخاطرَ المخازن'),
    array(25, 'Risk/risk_dept_cap.php', false, 'ولا يرى مخاطرَ التمويل'),
    array(28, 'Risk/risk_dept_cap.php', true,  'وإدارةُ المخاطرِ ترى الجميعَ (محفظةٌ كاملة)'),
    array(28, 'Risk/risk_dept_inv.php', true,  '   ومنها المخازن'),
);
foreach ($cases as $c) {
    list($role, $file, $want, $label) = $c;
    $got = $probe($role, $file);
    if ($got === $want) { echo "  ✔ {$label}\n"; }
    else { echo "  ✘ {$label} — المتوقَّع " . var_export($want, true) . " والواقع " . var_export($got, true) . "\n"; $fails++; }
}

echo "\n── الحصيلة\n";
echo "  شاشاتٌ سُجِّلت: {$madeMod}   (قائمةٌ سلفًا: {$existed})\n";
echo "  منحُ عرضٍ أُضيف: {$madeGrant}\n";

if ($fails > 0) {
    fwrite(STDERR, "\n✘ التمييزُ مختلٌّ في {$fails} حالةٍ — الهجرةُ راسبة\n");
    exit(1);
}
echo "\n✅ إحدى عشرةَ شاشةً مسجَّلةٌ ومحروسةٌ — والمنحُ يميّز الإدارةَ من غيرِها.\n";
echo "   ◆ لا تنسَ: php database/migrate.php dump-schema\n";
