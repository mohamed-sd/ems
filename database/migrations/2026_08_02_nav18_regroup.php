<?php
/**
 * update0004 · الموجة ⑱ · NAV-09→12 — التجميع (بيانات لا حذف شاشات)
 * ───────────────────────────────────────────────────────────────────────────
 * NAV-09 المالية: من مجموعاتها السبع المخصصة إلى الثماني القياسية (القديمة
 *        تُطفأ is_active=0 — رجوع بقلب علم صف).
 * NAV-10 دمج الموقع: ما ينقص «الحركة والتشغيل» (6) يُنقل إليها من «إدارة
 *        الموقع» (5) ثم تتوحد قائمة 5 على قائمة 6 (DEC-NAV-E ★ · SCR-01 §5).
 * NAV-11 قلب قائمة التشغيل (1): تخرج شاشات ما لا يملكه (العملاء · العقود ·
 *        الكيانات · التفويض · التراخيص · الصلاحيات) — active=0 لا حذف.
 * NAV-12 بقية الإدارات: إعادة توزيع بالكلمات لكل المجمَّع + حسم الأسماء المكررة.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__, 2) . '/includes/env.php';
$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "اتصال المرحِّل فشل\n"); exit(1); }
$conn->set_charset('utf8mb4');

// خريطة مجموعات كل دور القياسية
$lg = array();
$r = $conn->query("SELECT id, owner_role_id, group_code FROM link_groups WHERE group_code IS NOT NULL");
while ($r && ($x = $r->fetch_assoc())) { $lg[$x['owner_role_id'] . ':' . $x['group_code']] = intval($x['id']); }
$gid = function ($role, $code) use ($lg) { return isset($lg[$role . ':' . $code]) ? $lg[$role . ':' . $code] : null; };

// ── NAV-12(أ) · إعادة التوزيع بالكلمات لكل الروابط (تشمل مجموعات المالية المخصصة) ──
$rules = array(
    array('/لوحة|داشبورد/u', 'g1'),
    array('/سند|قيد يومي|إدخال|تسجيل|طلب شراء|أمر |مطابقة بنكية|استلام|صرف على/u', 'g2'),
    array('/متابعة|مراقبة|انحراف|متأخر|منتهية|استثناء|تنبيه|بلا معادل|أعمار|خامل/u', 'g5'),
    array('/إقفال|اقفال|إغلاق|اغلاق|تسوية|تسويات|جرد|مسيّر|مسير|إهلاك|اهلاك|دوريات/u', 'g6'),
    array('/تقرير|تقارير|كشف حساب|ميزان|قوائم|تحليل/u', 'g7'),
    array('/إعداد|اعداد|قواعد|أنواع|انواع|نماذج|سياسات|تصنيفات|حدود|أسعار الصرف|سقوف/u', 'g8'),
    array('/صندوق الاعتماد|اعتماد|موافقات/u', 'g4'),
);
$doorMap = array('HOME' => 'g1', 'DAILY' => 'g2', 'REC' => 'g3', 'APPR' => 'g4',
    'REP' => 'g7', 'SET' => 'g8', 'GOV' => 'g3', 'FIN' => 'g3');
$items = $conn->query(
    "SELECT n.id, n.role_id, n.door, n.label_ar, n.group_id, g.group_code
       FROM nav_items n LEFT JOIN link_groups g ON g.id = n.group_id")->fetch_all(MYSQLI_ASSOC);
$moved = 0;
foreach ($items as $it) {
    $code = null;
    foreach ($rules as $rule) {
        if (preg_match($rule[0], (string) $it['label_ar'])) { $code = $rule[1]; break; }
    }
    if ($code === null) {
        if ($it['group_code'] !== null && $it['group_code'] !== '') { continue; } // قياسي مستقر
        $code = isset($doorMap[$it['door']]) ? $doorMap[$it['door']] : 'g3';
    }
    $target = $gid($it['role_id'], $code);
    if ($target === null || intval($it['group_id']) === $target) { continue; }
    $conn->query("UPDATE nav_items SET group_id = {$target} WHERE id = " . intval($it['id']));
    $moved++;
}
echo "NAV-12/09: نُقل {$moved} رابطًا إلى مجموعته القياسية بالكلمات\n";

// المالية: ما بقي في مجموعاتها المخصصة يُسند قياسيًّا بحسب اسم المجموعة القديمة
$customMap = array('عملي اليوم' => 'g2', 'العملاء والتحصيل' => 'g3', 'الموردون والمدفوعات' => 'g3',
    'الخزينة والبنوك' => 'g2', 'المحاسبة العامة' => 'g3', 'التخطيط والتحليل' => 'g7', 'الإعدادات والتدقيق' => 'g8');
$r = $conn->query("SELECT n.id, n.role_id, g.name FROM nav_items n JOIN link_groups g ON g.id = n.group_id
                    WHERE g.group_code IS NULL");
$fin = 0;
while ($r && ($x = $r->fetch_assoc())) {
    $code = isset($customMap[$x['name']]) ? $customMap[$x['name']] : 'g3';
    $target = $gid($x['role_id'], $code);
    if ($target === null) { continue; }
    $conn->query("UPDATE nav_items SET group_id = {$target} WHERE id = " . intval($x['id']));
    $fin++;
}
$conn->query("UPDATE link_groups SET is_active = 0 WHERE group_code IS NULL AND owner_role_id IS NOT NULL");
echo "NAV-09: نُقل {$fin} رابطًا من المجموعات المخصصة — والقديمة أُطفئت (رجوعها قلب صف)\n";

// ── NAV-10 · دمج الموقع (5 ← 6) ──
$r5 = $conn->query("SELECT * FROM nav_items WHERE role_id = 5")->fetch_all(MYSQLI_ASSOC);
$r6routes = array();
$r = $conn->query("SELECT LOWER(route) rt FROM nav_items WHERE role_id = 6");
while ($r && ($x = $r->fetch_assoc())) { $r6routes[$x['rt']] = true; }
$copied = 0;
foreach ($r5 as $x) {
    if (isset($r6routes[strtolower($x['route'])])) { continue; }
    $g6t = $gid(6, 'g3');
    $stmt = $conn->prepare("INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, active)
                            VALUES (6, ?, ?, ?, ?, ?, ?, ?, 1)");
    $mid = $x['module_id'] !== null ? intval($x['module_id']) : null;
    $stmt->bind_param('siisssi', $x['door'], $g6t, $mid, $x['label_ar'], $x['route'], $x['icon'], $x['sort_order']);
    $stmt->execute();
    $stmt->close();
    $copied++;
}
// توحيد قائمة 5 على 6: تُطفأ روابط 5 وتُنسخ روابط 6 إليها (المستخدمون الستة يرون الموحدة)
$conn->query("UPDATE nav_items SET active = 0 WHERE role_id = 5 AND route <> 'main/my_workspace.php'");
$r6 = $conn->query("SELECT * FROM nav_items WHERE role_id = 6 AND active = 1")->fetch_all(MYSQLI_ASSOC);
$mirrored = 0;
foreach ($r6 as $x) {
    // مجموعة 5 المقابلة بذات الكود
    $gcode = null;
    if ($x['group_id'] !== null) {
        $g = $conn->query("SELECT group_code FROM link_groups WHERE id = " . intval($x['group_id']))->fetch_assoc();
        $gcode = $g ? $g['group_code'] : null;
    }
    $g5t = $gcode ? $gid(5, $gcode) : null;
    $chk = $conn->query("SELECT id, active FROM nav_items WHERE role_id = 5 AND route = '"
        . $conn->real_escape_string($x['route']) . "' LIMIT 1");
    $ex = $chk ? $chk->fetch_assoc() : null;
    if ($ex) {
        // UQ(role,route) يشمل المطفأ — يُحيا الصف القائم على مواصفة 6
        if (intval($ex['active']) === 1) { continue; }
        $conn->query("UPDATE nav_items SET active = 1, door = '" . $conn->real_escape_string($x['door'])
            . "', group_id = " . ($g5t !== null ? $g5t : 'NULL')
            . ", label_ar = '" . $conn->real_escape_string($x['label_ar'])
            . "', sort_order = " . intval($x['sort_order']) . " WHERE id = " . intval($ex['id']));
        $mirrored++;
        continue;
    }
    $stmt = $conn->prepare("INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, active)
                            VALUES (5, ?, ?, ?, ?, ?, ?, ?, 1)");
    $mid = $x['module_id'] !== null ? intval($x['module_id']) : null;
    $stmt->bind_param('siisssi', $x['door'], $g5t, $mid, $x['label_ar'], $x['route'], $x['icon'], $x['sort_order']);
    $stmt->execute();
    $stmt->close();
    $mirrored++;
}
echo "NAV-10: نُقل {$copied} إلى 6 · وتوحدت قائمة 5 على 6 ({$mirrored} نسخة والقديمة مطفأة)\n";

// ── NAV-11 · قلب قائمة التشغيل (الدور 1) ──
$out1 = array('%العملاء%', '%سجل الكيانات%', '%التفويض بالتوقيع%', '%التراخيص والكفالات%',
    '%أنماط التفعيل%', '%الصلاحيات%', '%سجل الممولين%', '%عملية تمويل%', '%منح المجال المقيَّد%');
$flipped = 0;
foreach ($out1 as $pat) {
    $conn->query("UPDATE nav_items SET active = 0 WHERE role_id = 1 AND active = 1 AND label_ar LIKE '"
        . $conn->real_escape_string($pat) . "'");
    $flipped += $conn->affected_rows;
}
echo "NAV-11: أُخرج {$flipped} رابطًا لا يملكه التشغيل من قائمته (active=0 — يطّلع على الأثر ولا يملك الشاشة)\n";

// ── NAV-12(ب) · الأسماء المكررة داخل الدور الواحد — تمييز لا لبس ──
$r = $conn->query(
    "SELECT role_id, label_ar, COUNT(*) c FROM nav_items
      WHERE active = 1 GROUP BY role_id, label_ar HAVING c > 1");
$dups = $r ? $r->fetch_all(MYSQLI_ASSOC) : array();
$renamed = 0;
foreach ($dups as $d) {
    // الصف الأول يبقى باسمه — والتوالي تُميَّز بديباجة بابها (الفرق في الاسم لا الشرح §6-③)
    $rows = $conn->query("SELECT id, door FROM nav_items WHERE role_id = " . intval($d['role_id'])
        . " AND label_ar = '" . $conn->real_escape_string($d['label_ar']) . "' AND active = 1 ORDER BY id")->fetch_all(MYSQLI_ASSOC);
    $doorNames = array('HOME' => 'الرئيسية', 'DAILY' => 'اليومي', 'APPR' => 'الاعتماد', 'REC' => 'السجلات',
        'REP' => 'التقارير', 'SET' => 'الإعدادات', 'GOV' => 'الحوكمة', 'FIN' => 'التمويل');
    for ($i = 1; $i < count($rows); $i++) {
        $suffix = isset($doorNames[$rows[$i]['door']]) ? $doorNames[$rows[$i]['door']] : $rows[$i]['door'];
        $newName = $d['label_ar'] . ' — ' . $suffix;
        $conn->query("UPDATE nav_items SET label_ar = '" . $conn->real_escape_string($newName)
            . "' WHERE id = " . intval($rows[$i]['id']));
        $renamed++;
    }
}
echo "NAV-12: مُيِّز {$renamed} اسمًا مكررًا داخل أدواره\n";
exit(0);
