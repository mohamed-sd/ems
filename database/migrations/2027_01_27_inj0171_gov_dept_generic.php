<?php
/**
 * 2027_01_27 — INJ-0171: مدخلُ حوكمةِ الإدارةِ العامُّ + بابُ الأسطول
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ اختبارُ القبول: «بدور ٣ يفتح رابطُ ‹حوكمة إدارة الأسطول› شاشةً تعرض أعضاءَ
 *   الإدارةِ وأدوارَهم وتفويضاتِهم قراءةً فقط، وصفرَ سجلٍّ من إدارةٍ أخرى».
 * ◆ فيلزم ثلاثةٌ معًا: وحدةٌ مسجَّلةٌ (وإلا رفض الحارسُ الشاشةَ فشلًا مغلقًا) ·
 *   ومنحُ قراءةٍ للدور 3 · وصفُّ تنقّلٍ في مجموعةٍ قائمةٍ من مجموعاتِ الأسطول.
 * ◆ ولا مجموعةَ جديدة: «متابعة وتقارير الأسطول» (المرحلة 8) قائمةٌ للدور 3،
 *   وإقحامُ مجموعةٍ عاشرةٍ يزيد القائمةَ طولًا بلا معنًى.
 * ◆ ومُتحمِّلٌ للتكرار: كلُّ إدراجٍ مسبوقٌ بفحصِ وجودِه.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

$ROUTE = 'Governance/gov_dept.php';
$LABEL = 'حوكمة إدارة الأسطول';
$FLEET = 3;

/* ① الوحدةُ في سجلِّ الشاشات */
$rs = $db->query("SELECT id FROM modules WHERE code = '{$ROUTE}' LIMIT 1");
if (!$rs) { fwrite(STDERR, 'modules: ' . $db->error . "\n"); exit(1); }
$row = $rs->fetch_row();
if ($row) {
    $moduleId = (int) $row[0];
    echo "  ① الوحدةُ مسجَّلةٌ سلفًا (#{$moduleId})\n";
} else {
    $st = $db->prepare("INSERT INTO modules (code, name) VALUES (?, ?)");
    if (!$st) { fwrite(STDERR, 'prepare modules: ' . $db->error . "\n"); exit(1); }
    $nm = 'حوكمة الإدارة (عام)';
    $st->bind_param('ss', $ROUTE, $nm);
    if (!$st->execute()) { fwrite(STDERR, 'insert modules: ' . $st->error . "\n"); exit(1); }
    $moduleId = (int) $st->insert_id;
    $st->close();
    echo "  ① وحدةٌ جديدة (#{$moduleId})\n";
}
if ($moduleId <= 0) { fwrite(STDERR, "معرّفُ وحدةٍ غيرُ صالح\n"); exit(1); }

/* ② منحُ القراءةِ لدورِ الأسطول — قراءةً فقط كما نصَّ اختبارُ القبول */
$st = $db->prepare("SELECT COUNT(*) FROM role_permissions WHERE role_id = ? AND module_id = ?");
$st->bind_param('ii', $FLEET, $moduleId);
$st->execute();
$hasPerm = (int) $st->get_result()->fetch_row()[0];
$st->close();
if ($hasPerm === 0) {
    $st = $db->prepare("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                        VALUES (?, ?, 1, 0, 0, 0)");
    $st->bind_param('ii', $FLEET, $moduleId);
    if (!$st->execute()) { fwrite(STDERR, 'grant: ' . $st->error . "\n"); exit(1); }
    $st->close();
    echo "  ② منحُ قراءةٍ للدور {$FLEET}\n";
} else {
    $db->query("UPDATE role_permissions SET can_view = 1 WHERE role_id = {$FLEET} AND module_id = {$moduleId}");
    echo "  ② المنحُ قائمٌ — أُكِّدت القراءة\n";
}

/* ③ صفُّ التنقّلِ في مجموعةٍ قائمةٍ من مجموعاتِ الأسطول */
$st = $db->prepare("SELECT COUNT(*) FROM nav_items WHERE role_id = ? AND route = ?");
$st->bind_param('is', $FLEET, $ROUTE);
$st->execute();
$hasNav = (int) $st->get_result()->fetch_row()[0];
$st->close();
if ($hasNav === 0) {
    $rs = $db->query("SELECT lg.id FROM link_groups lg JOIN nav_items ni ON ni.group_id = lg.id
                       WHERE ni.role_id = {$FLEET} AND lg.stage_no < 90
                       ORDER BY lg.stage_no DESC LIMIT 1");
    if (!$rs) { fwrite(STDERR, 'group: ' . $db->error . "\n"); exit(1); }
    $g = $rs->fetch_row();
    if (!$g) { fwrite(STDERR, "لا مجموعةَ تنقّلٍ للدور {$FLEET} — لا يُخترع لها موضع\n"); exit(1); }
    $groupId = (int) $g[0];
    $st = $db->prepare("INSERT INTO nav_items (role_id, group_id, label_ar, route, module_id)
                        VALUES (?, ?, ?, ?, ?)");
    if (!$st) { fwrite(STDERR, 'prepare nav: ' . $db->error . "\n"); exit(1); }
    $st->bind_param('iissi', $FLEET, $groupId, $LABEL, $ROUTE, $moduleId);
    if (!$st->execute()) { fwrite(STDERR, 'insert nav: ' . $st->error . "\n"); exit(1); }
    $st->close();
    echo "  ③ صفُّ تنقّلٍ في المجموعة #{$groupId}\n";
} else {
    echo "  ③ صفُّ التنقّلِ قائمٌ سلفًا\n";
}

/* ══ إثباتٌ وظيفيّ: الثلاثةُ معًا وإلا لا يفتح الرابطُ شيئًا ═══════════════ */
$rs = $db->query("SELECT
      (SELECT COUNT(*) FROM modules WHERE code = '{$ROUTE}') m,
      (SELECT COUNT(*) FROM role_permissions rp JOIN modules mo ON mo.id = rp.module_id
        WHERE mo.code = '{$ROUTE}' AND rp.role_id = {$FLEET} AND rp.can_view = 1) p,
      (SELECT COUNT(*) FROM nav_items WHERE role_id = {$FLEET} AND route = '{$ROUTE}') n");
if (!$rs) { fwrite(STDERR, 'تحقق: ' . $db->error . "\n"); exit(1); }
$v = $rs->fetch_assoc();
if ((int) $v['m'] !== 1 || (int) $v['p'] !== 1 || (int) $v['n'] !== 1) {
    fwrite(STDERR, "إثباتٌ ناقص — وحدة={$v['m']} منح={$v['p']} تنقّل={$v['n']} والمطلوب 1/1/1\n");
    exit(1);
}
echo "  ✔ إثبات: وحدةٌ ومنحُ قراءةٍ وصفُّ تنقّلٍ — ثلاثتُها قائمة\n";
echo "  الشاهد: php tools/fix_od19_probe.php (INJ-0171)\n";
exit(0);
