<?php
/**
 * 2027_03_24_register_gov_dept_and_audit_screens.php
 * ═══════════════════════════════════════════════════════════════════════════
 * تسجيلُ ما بقي من الشاشاتِ المفقودة: اثنتا عشرةَ «حوكمةَ إدارة» + مخاطرُ
 * المكتبِ التنفيذيِّ + سجلُّ الاطّلاعِ الحساس + طابورُ إغلاقِ البلاغات.
 * ⇐ INJ-0123 · INJ-0201 · INJ-0211 · INJ-0230 · INJ-0247 · INJ-0266 ·
 *   INJ-0267 · INJ-0281 · INJ-0304 · INJ-0337 · INJ-0355 · INJ-0372 ·
 *   INJ-0412 · INJ-0485 · INJ-0532
 *
 * ◆ **التسجيلُ حراسةٌ لا توثيق**: شاشةٌ غيرُ مسجَّلةٍ في `modules` تمرُّ
 *   بـfail-open فتُفتح للجميع. فالملفُّ والصفُّ والمنحُ ثلاثةٌ لا تنفصل.
 * ◆ وأدوارُ كلِّ إدارةٍ **تُقرأ من `risk_role_org_unit()`** في الشيفرةِ
 *   الحاكمة مقلوبةً — لا تُكتب هنا بيدٍ فتتفرّق عن الزاوية.
 * ◆ ولوحاتُ الحوكمةِ تُمنح **عرضًا + تعديلًا** لأدوارِ الإدارةِ (فالتصديقُ
 *   على قائمةِ الفريقِ فعلُها الكاتبُ الوحيد) و**عرضًا** للحوكمةِ (15)
 *   وللرئيسِ (9). وسجلُّ الاطّلاعِ للحوكمةِ والمراجعِ الداخليِّ عرضًا فقط —
 *   فسجلُّ تدقيقٍ يُحرَّر من شاشتِه ليس سجلَّ تدقيق.
 * ◆ وتتحقّق من نفسِها: مديرُ إدارةٍ يصل لوحةَ حوكمتِه ولا يصل لوحةَ غيرِه.
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

echo "══ تسجيلُ شاشاتِ الحوكمةِ والتدقيقِ الجديدة ══\n\n";

/* ── أدوارُ الوحداتِ من الشيفرةِ الحاكمة ─────────────────────────────────── */
$common = (string) file_get_contents($ROOT . '/Risk/_risk_common.php');
$s = strpos($common, 'function risk_role_org_unit');
$e = $s !== false ? strpos($common, 'return isset($map', $s) : false;
if ($s === false || $e === false) { fwrite(STDERR, "✘ تعذّر قراءةُ خريطةِ الأدوار\n"); exit(2); }
preg_match_all("~'(\d+)'\s*=>\s*(\d+)~", substr($common, $s, $e - $s), $mm, PREG_SET_ORDER);
$roleUnit = array();
foreach ($mm as $x) { $roleUnit[(int) $x[1]] = (int) $x[2]; }
if (count($roleUnit) < 10) { fwrite(STDERR, "✘ خريطةُ الأدوارِ ناقصة\n"); exit(2); }

$units = array();
$r = $conn->query("SELECT unit_id, unit_code, name_ar FROM org_units WHERE company_id = {$CO} AND active = 1");
while ($r && ($x = $r->fetch_assoc())) { $units[$x['unit_code']] = $x; }

/* لاحقة ⇒ [رمزُ الوحدة, مجلدُ الغلاف] */
$GOV = array(
    'cap' => array('financing',       'Financing'),
    'crp' => array('tickets',         'Tickets'),
    'flt' => array('fleet',           'Equipments'),
    'hrm' => array('hr',              'Workforce'),
    'inv' => array('warehouse',       'Procurement'),
    'mnt' => array('maintenance',     'Maintenance'),
    'ops' => array('ops',             'Operations'),
    'prc' => array('procurement_ops', 'Procurement'),
    'sit' => array('movement',        'Operations'),
    'trp' => array('transport',       'Transport'),
    'wrk' => array('operators',       'Workforce'),
);

$GOV_ROLE  = 15;   // الحوكمةُ والالتزام
$EXEC_ROLE = 9;    // مكتبُ الرئيس
$AUDIT_ROLE = 33;  // المراجعُ الداخليُّ المستقل

$madeMod = 0; $madeGrant = 0; $existed = 0;

/** يسجّل شاشةً ويمنحها — ويرسب على أيِّ فشلٍ بدل أن يمضي صامتًا */
$register = function ($file, $label, $icon, $owner, array $grants, $order)
    use ($conn, $ROOT, &$madeMod, &$madeGrant, &$existed) {
    if (!is_file($ROOT . '/' . $file)) {
        fwrite(STDERR, "✘ الملفُّ غيرُ موجود: {$file} — لا صفَّ لشاشةٍ لا ملفَّ لها\n");
        exit(2);
    }
    $mid = null;
    $st = $conn->prepare('SELECT id FROM modules WHERE code = ? LIMIT 1');
    $st->bind_param('s', $file);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) { $mid = (int) $row['id']; }
    $st->close();
    if ($mid === null) {
        $st = $conn->prepare('INSERT INTO modules (name, code, owner_role_id, is_link, is_quick, icon, display_order)
                              VALUES (?, ?, ?, 1, 0, ?, ?)');
        $st->bind_param('ssisi', $label, $file, $owner, $icon, $order);
        if (!$st->execute()) { fwrite(STDERR, "✘ تعذّر تسجيلُ {$file}: " . $st->error . "\n"); exit(2); }
        $mid = (int) $conn->insert_id;
        $st->close();
        $madeMod++;
    } else { $existed++; }
    $added = 0;
    foreach ($grants as $role => $rw) {
        $st = $conn->prepare('SELECT id FROM role_permissions WHERE role_id = ? AND module_id = ? LIMIT 1');
        $st->bind_param('ii', $role, $mid);
        $st->execute();
        $has = (bool) $st->get_result()->fetch_row();
        $st->close();
        if ($has) { continue; }
        $ed = $rw ? 1 : 0;
        $st = $conn->prepare('INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                              VALUES (?, ?, 1, 0, ?, 0)');
        $st->bind_param('iii', $role, $mid, $ed);
        if (!$st->execute()) { fwrite(STDERR, "✘ تعذّر منحُ الدور {$role} على {$file}: " . $st->error . "\n"); exit(2); }
        $st->close();
        $added++; $madeGrant++;
    }
    printf("  ✔ %-36s #%-4s %-30s منحٌ جديد: %d\n", $file, $mid, mb_substr($label, 0, 28), $added);
    return $mid;
};

/* ── ① أغلفةُ حوكمةِ الإداراتِ الإحدى عشرة ────────────────────────────────── */
$ord = 600;
foreach ($GOV as $sfx => $m) {
    list($code, $dir) = $m;
    if (!isset($units[$code])) { fwrite(STDERR, "✘ وحدةُ «{$code}» غيرُ موجودة\n"); exit(2); }
    $unit = $units[$code];
    $roles = array();
    foreach ($roleUnit as $role => $uu) { if ($uu === (int) $unit['unit_id']) { $roles[] = $role; } }
    sort($roles);
    if (!$roles) { fwrite(STDERR, "✘ لا أدوارَ للوحدة «{$code}»\n"); exit(2); }
    $grants = array();
    foreach ($roles as $role) { $grants[$role] = true; }   // التصديقُ فعلُهم
    $grants[$GOV_ROLE]  = false;
    $grants[$EXEC_ROLE] = false;
    $register($dir . '/gov_dept_' . $sfx . '.php', 'حوكمة ' . $unit['name_ar'],
              'fa fa-scale-balanced', $roles[0], $grants, $ord++);
}

/* ── ② حوكمةُ مكتبِ الرئيسِ ومخاطرُه ───────────────────────────────────────── */
$register('Portal/gov_dept_ceo.php', 'حوكمة مكتب الرئيس التنفيذي والنواب',
          'fa fa-scale-balanced', $EXEC_ROLE,
          array($EXEC_ROLE => true, $GOV_ROLE => false), $ord++);
$register('Risk/risk_dept_ceo.php', 'المخاطر المؤسسية',
          'fa fa-building-shield', $EXEC_ROLE,
          array($EXEC_ROLE => false, 28 => false, 29 => false, 30 => false, $GOV_ROLE => false), $ord++);

/* ── ③ سجلُّ الاطّلاعِ الحساس — للحوكمةِ والمراجعِ عرضًا فقط ────────────────── */
$register('Governance/read_log.php', 'سجل الاطّلاع على الحقول الحساسة',
          'fa fa-eye', $GOV_ROLE,
          array($GOV_ROLE => false, $AUDIT_ROLE => false, $EXEC_ROLE => false), $ord++);

/* ── ④ طابورُ إغلاقِ البلاغات — لمركزِ البلاغاتِ وللحوكمة ─────────────────── */
$register('Tickets/ticket_close.php', 'إغلاق البلاغات المنجَزة',
          'fa fa-lock', 24,
          array(24 => true, $GOV_ROLE => false), $ord++);

/* ── ⑤ التحقُّقُ الذاتيُّ: المنحُ يميّز ──────────────────────────────────────── */
echo "\n── جسُّ التمييز\n";
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
    array(26, 'Financing/gov_dept_cap.php',  true,  'مديرُ التمويلِ يصل حوكمةَ إدارتِه'),
    array(26, 'Transport/gov_dept_trp.php',  false, 'ولا يصل حوكمةَ النقل'),
    array(23, 'Transport/gov_dept_trp.php',  true,  'ومديرُ النقلِ يصل حوكمةَ إدارتِه'),
    array(15, 'Financing/gov_dept_cap.php',  true,  'والحوكمةُ ترى الجميعَ'),
    array(26, 'Governance/read_log.php',     false, 'وسجلُّ الاطّلاعِ محجوبٌ عن إدارةٍ تنفيذية'),
    array(33, 'Governance/read_log.php',     true,  'ومفتوحٌ للمراجعِ الداخليِّ المستقل'),
);
$fails = 0;
foreach ($cases as $c) {
    list($role, $file, $want, $label) = $c;
    $got = $probe($role, $file);
    if ($got === $want) { echo "  ✔ {$label}\n"; }
    else { echo "  ✘ {$label} — المتوقَّع " . var_export($want, true) . " والواقع " . var_export($got, true) . "\n"; $fails++; }
}

echo "\n── الحصيلة\n";
echo "  شاشاتٌ سُجِّلت: {$madeMod}   (قائمةٌ سلفًا: {$existed})\n";
echo "  منحٌ أُضيف: {$madeGrant}\n";
if ($fails > 0) { fwrite(STDERR, "\n✘ التمييزُ مختلٌّ في {$fails} حالةٍ — الهجرةُ راسبة\n"); exit(1); }
echo "\n✅ خمسَ عشرةَ شاشةً مسجَّلةٌ ومحروسةٌ — والمنحُ يميّز الإدارةَ من غيرِها.\n";
echo "   ◆ لا تنسَ: php database/migrate.php dump-schema\n";
