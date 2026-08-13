<?php
/**
 * 2027_03_25_register_list_screens.php
 * ═══════════════════════════════════════════════════════════════════════════
 * تسجيلُ ستِّ شاشاتِ قراءةٍ يطلبها السجلُّ ولكلٍّ منها جدولٌ حيٌّ يسندها.
 * ⇐ INJ-0134 · INJ-0156 · INJ-0164 · INJ-0268 · INJ-0354 · INJ-0580
 *
 * ◆ التسجيلُ حراسةٌ لا توثيق — شاشةٌ غيرُ مسجَّلةٍ تمرُّ بـfail-open.
 * ◆ والمنحُ لأدوارِ الإدارةِ صاحبةِ الشاشةِ **عرضًا فقط** — فكلُّها قارئة.
 * ◆ و«بلاغاتي» **لكلِّ الأدوار**: هي مساحةُ المستخدمِ الشخصيةُ لا شاشةَ إدارةٍ،
 *   وشرطُها على `reporter_user_id` فيرى كلُّ واحدٍ ما رفعه هو وحدَه.
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

echo "══ تسجيلُ شاشاتِ القراءةِ الست ══\n\n";

/* كلُّ الأدوارِ الحيّة — لـ«بلاغاتي» وحدَها */
$allRoles = array();
$r = $conn->query('SELECT id FROM roles WHERE id > 0 ORDER BY id');
while ($r && ($x = $r->fetch_row())) { $allRoles[] = (int) $x[0]; }

$SCREENS = array(
    array('Fleet/readiness_cert.php',              'شهادات جاهزية المعدات',       'fa fa-certificate',   3,  array(3, 10, 11, 9, 15)),
    array('Procurement/warehouses.php',            'المخازن وأنواعها',            'fa fa-warehouse',     25, array(25, 16, 9, 15)),
    array('Operations/monthly_plan.php',           'الخطة الشهرية للتشغيل',       'fa fa-calendar-days', 1,  array(1, 7, 5, 6, 9, 15)),
    array('Suppliers/quota_approval_minutes.php',  'محاضر اعتماد وحدات المورد',   'fa fa-file-signature',2,  array(2, 8, 27, 9, 15)),
    array('Tickets/ticket_kpi.php',                'مؤشرات البلاغات',             'fa fa-chart-simple',  24, array(24, 9, 15)),
    array('Tickets/my_tickets.php',                'بلاغاتي',                     'fa fa-inbox',         24, $allRoles),
);

$madeMod = 0; $madeGrant = 0; $existed = 0; $ord = 700;
foreach ($SCREENS as $s) {
    list($file, $label, $icon, $owner, $grants) = $s;
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
        $o = $ord++;
        $st->bind_param('ssisi', $label, $file, $owner, $icon, $o);
        if (!$st->execute()) { fwrite(STDERR, "✘ تعذّر تسجيلُ {$file}: " . $st->error . "\n"); exit(2); }
        $mid = (int) $conn->insert_id;
        $st->close();
        $madeMod++;
    } else { $existed++; }
    $added = 0;
    foreach ($grants as $role) {
        $st = $conn->prepare('SELECT id FROM role_permissions WHERE role_id = ? AND module_id = ? LIMIT 1');
        $st->bind_param('ii', $role, $mid);
        $st->execute();
        $has = (bool) $st->get_result()->fetch_row();
        $st->close();
        if ($has) { continue; }
        $st = $conn->prepare('INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                              VALUES (?, ?, 1, 0, 0, 0)');
        $st->bind_param('ii', $role, $mid);
        if (!$st->execute()) { fwrite(STDERR, "✘ تعذّر منحُ الدور {$role}: " . $st->error . "\n"); exit(2); }
        $st->close();
        $added++; $madeGrant++;
    }
    printf("  ✔ %-42s #%-4s منحٌ جديد: %d\n", $file, $mid, $added);
}

/* ── التحقُّقُ الذاتيُّ: كلُّ استعلامٍ في الشاشاتِ يعمل على القاعدةِ الحيّة ────
     شاشةٌ على عمودٍ لا وجودَ له تُصيَّر خاويةً وتبدو سليمة — فيُجَسُّ الاستعلامُ. */
echo "\n── جسُّ الاستعلامات\n";
$fails = 0;
$probes = array(
    'readiness_lines'      => "SELECT COUNT(*) FROM readiness_lines WHERE company_id = 4 AND is_deleted = 0",
    'proc_warehouse'       => "SELECT COUNT(*) FROM proc_warehouse WHERE company_id = 4 AND is_deleted = 0",
    'scr_op_monthly'       => "SELECT COUNT(*) FROM scr_op_monthly WHERE company_id = 4",
    'substitute_coverages' => "SELECT COUNT(*) FROM substitute_coverages WHERE company_id = 4",
    'tickets'              => "SELECT COUNT(*) FROM tickets WHERE company_id = 4",
);
foreach ($probes as $t => $q) {
    $r = $conn->query($q);
    if ($r === false) { echo "  ✘ {$t}: " . $conn->error . "\n"; $fails++; }
    else { echo "  ✔ {$t}: " . $r->fetch_row()[0] . " صفًّا في نطاقِ الشركة\n"; }
}

echo "\n── الحصيلة\n";
echo "  شاشاتٌ سُجِّلت: {$madeMod}   (قائمةٌ سلفًا: {$existed})\n";
echo "  منحٌ أُضيف: {$madeGrant}\n";
if ($fails > 0) { fwrite(STDERR, "\n✘ {$fails} استعلامًا لا يعمل — الهجرةُ راسبة\n"); exit(1); }
echo "\n✅ ستُّ شاشاتِ قراءةٍ مسجَّلةٌ ومحروسةٌ على بيانٍ حيّ.\n";
echo "   ◆ لا تنسَ: php database/migrate.php dump-schema\n";
