<?php
/**
 * update0004 · الموجة ⑯ · NAV-04/05/06 — (يُنفَّذ عملية منفصلة عبر migrate.php)
 * ───────────────────────────────────────────────────────────────────────────
 * NAV-04 يضمن قبول chk_nav_door للأبواب الثمانية — يقفل انحراف الهجرات القديمة
 *        بنيويًّا في مسار الهجرات (DEC-01 ② نُفِّذ حيًّا خارج المسار).
 * NAV-05 تنزيل مجموعات NAV-01 الثماني إلى link_groups داخل الأبواب (DEC-NAV-J):
 *        group_code + بذر الثماني لكل دور + إسناد group_id لغير المجمَّع
 *        (كلمات المتابعة/الإغلاق/التقارير/الإعدادات أولًا ثم الباب).
 * NAV-06 حارس السبعة يقرأ الناتج (tools/nav_seven_guard.php).
 * idempotent بالكامل.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
// اتصال المرحِّل (DDL) لا ems_app — نمط مسار الهجرات المنفصل
require_once dirname(__DIR__, 2) . '/includes/env.php';
$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "اتصال المرحِّل فشل\n"); exit(1); }
$conn->set_charset('utf8mb4');

// ── NAV-04 ──
$clause = '';
$r = $conn->query("SELECT CHECK_CLAUSE c FROM information_schema.check_constraints
                    WHERE constraint_schema = DATABASE() AND constraint_name = 'chk_nav_door'");
if ($r && ($x = $r->fetch_assoc())) { $clause = (string) $x['c']; }
if ($clause !== '' && (strpos($clause, 'GOV') === false || strpos($clause, 'FIN') === false)) {
    if (!$conn->query("ALTER TABLE nav_items DROP CHECK chk_nav_door")) {
        fwrite(STDERR, 'تعذر إسقاط chk_nav_door: ' . $conn->error . "\n");
        exit(1);
    }
    if (!$conn->query("ALTER TABLE nav_items ADD CONSTRAINT chk_nav_door
            CHECK (door IN ('HOME','DAILY','APPR','REC','REP','SET','GOV','FIN'))")) {
        fwrite(STDERR, 'تعذر إعادة chk_nav_door بالثمانية: ' . $conn->error . "\n");
        exit(1);
    }
    echo "chk_nav_door أعيد بالثمانية (كان ستة)\n";
} else {
    echo "chk_nav_door يقبل الثمانية أصلًا — الانحراف مقفل\n";
}

// ── NAV-05 · group_code ──
$r = $conn->query("SELECT COUNT(*) c FROM information_schema.columns
                    WHERE table_schema = DATABASE() AND table_name = 'link_groups' AND column_name = 'group_code'");
if (intval($r->fetch_assoc()['c']) === 0) {
    if (!$conn->query("ALTER TABLE link_groups
            ADD COLUMN group_code VARCHAR(10) NULL COMMENT 'NAV-01 §4: g1..g8 — المجموعات القياسية' AFTER name,
            ADD KEY idx_lg_code (owner_role_id, group_code)")) {
        fwrite(STDERR, 'تعذر إضافة group_code: ' . $conn->error . "\n");
        exit(1);
    }
    echo "أضيف link_groups.group_code\n";
}

$G8 = array(
    'g1' => array('لوحة الإدارة', 'fa fa-tachometer-alt', 10),
    'g2' => array('العمل اليومي', 'fa fa-sun', 20),
    'g3' => array('السجلات والملفات', 'fa fa-folder-open', 30),
    'g4' => array('المراجعة والاعتماد', 'fa fa-check-double', 40),
    'g5' => array('المتابعة والاستثناءات', 'fa fa-exclamation-triangle', 50),
    'g6' => array('الإغلاق والتسوية', 'fa fa-lock', 60),
    'g7' => array('التقارير', 'fa fa-chart-pie', 70),
    'g8' => array('الإعدادات', 'fa fa-cog', 80),
);

$roles = array();
$r = $conn->query("SELECT DISTINCT role_id FROM nav_items");
while ($r && ($x = $r->fetch_assoc())) { $roles[] = intval($x['role_id']); }
$seeded = 0;
foreach ($roles as $rid) {
    foreach ($G8 as $code => $def) {
        $chk = $conn->query("SELECT id FROM link_groups WHERE owner_role_id = {$rid} AND group_code = '{$code}' LIMIT 1");
        if ($chk && $chk->num_rows > 0) { continue; }
        $stmt = $conn->prepare("INSERT INTO link_groups (name, group_code, owner_role_id, icon, display_order, is_active)
                                VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->bind_param('ssisi', $def[0], $code, $rid, $def[1], $def[2]);
        $stmt->execute();
        $stmt->close();
        $seeded++;
    }
}
echo "بُذرت {$seeded} مجموعة قياسية (" . count($roles) . " دورًا × 8)\n";

$doorMap = array('HOME' => 'g1', 'DAILY' => 'g2', 'REC' => 'g3', 'APPR' => 'g4',
    'REP' => 'g7', 'SET' => 'g8', 'GOV' => 'g3', 'FIN' => 'g3');
$rules = array(
    array('/لوحة|داشبورد/u', 'g1'),
    array('/متابعة|مراقبة|انحراف|متأخر|منتهية|استثناء|تنبيه/u', 'g5'),
    array('/إقفال|اقفال|إغلاق|اغلاق|تسوية|تسويات|جرد|مسيّر|مسير/u', 'g6'),
    array('/تقرير|تقارير|كشف|ميزان|قوائم/u', 'g7'),
    array('/إعداد|اعداد|قواعد|أنواع|انواع|نماذج|سياسات|تصنيفات|حدود/u', 'g8'),
    array('/صندوق الاعتماد|اعتماد|موافقات/u', 'g4'),
);
$items = $conn->query("SELECT id, role_id, door, label_ar FROM nav_items WHERE group_id IS NULL")->fetch_all(MYSQLI_ASSOC);
$lg = array();
$r = $conn->query("SELECT id, owner_role_id, group_code FROM link_groups WHERE group_code IS NOT NULL");
while ($r && ($x = $r->fetch_assoc())) { $lg[$x['owner_role_id'] . ':' . $x['group_code']] = intval($x['id']); }
$assigned = 0;
foreach ($items as $it) {
    $code = null;
    foreach ($rules as $rule) {
        if (preg_match($rule[0], (string) $it['label_ar'])) { $code = $rule[1]; break; }
    }
    if ($code === null) { $code = isset($doorMap[$it['door']]) ? $doorMap[$it['door']] : 'g3'; }
    $key = $it['role_id'] . ':' . $code;
    if (!isset($lg[$key])) { continue; }
    $conn->query("UPDATE nav_items SET group_id = " . $lg[$key] . " WHERE id = " . intval($it['id']));
    $assigned++;
}
echo "أُسند {$assigned} رابطًا إلى مجموعاته القياسية\n";
exit(0);
