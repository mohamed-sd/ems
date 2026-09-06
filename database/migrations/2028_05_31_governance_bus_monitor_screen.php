<?php
/**
 * 2028_05_31_governance_bus_monitor_screen.php — تسجيلُ شاشةِ مراقبةِ الناقل
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **آخرُ قدرةٍ يتيمةٍ في `admin/`**: `admin/bus_monitor.php` كانت الشاشةَ
 *   الوحيدةَ التي تقرأ `ems_event_dead_letter` و`ems_event_consumers` و
 *   `ems_processed_events` — لا قارئَ لها سواها خارجَ شيفرةِ المحرّك. نُقلت
 *   إلى `Governance/bus_monitor.php` قرائيّةً بحتة.
 *
 * ⛔ **والوصفةُ سبعةُ سجلّاتٍ والحاكمُ آخرُها**: `nav_workspace_placements`
 *   يقرؤه `navarch_renderer` مصدرًا للتصيير، و`nav_items` تُرشِّح فقط. ومسارُه
 *   يُخزَّن **مطبَّعًا** (صغيرًا وبلا `.php`) فبحثٌ بالمسارِ كما هو يُعمي.
 * ⛔ **ولا يُمنح تعديلٌ ولا حذف**: الشاشةُ قرائيّةٌ، فـ`can_add`/`can_edit`/
 *   `can_delete` أصفارٌ — منحُ كتابةٍ لشاشةٍ لا تكتب توسيعٌ بلا سبب.
 *
 * التشغيل: php database/migrations/2028_05_31_governance_bus_monitor_screen.php
 * العكس:   php database/migrations/2028_05_31_governance_bus_monitor_screen_down.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit('connect fail: ' . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');

$CODE   = 'Governance/bus_monitor.php';
$LABEL  = 'مراقبة ناقل الأحداث';
$ROLE   = 15;
$GCODE  = 'bus_monitor_r15';
$GNAME  = 'مراقبة ناقل الأحداث';
$ICON   = 'fa fa-satellite-dish';

$one = function ($sql) use ($conn) { $r = $conn->query($sql); return $r ? ($r->fetch_row()[0] ?? null) : null; };
$esc = function ($s) use ($conn) { return $conn->real_escape_string($s); };

echo "══ تسجيلُ {$CODE}\n";

/* ── ⓪ link_groups — المجموعةُ الحاضنة ─────────────────────────────────────
   ⛔ **مجموعةٌ بلا `group_code` لا تُصيَّر**: قيسَ على دور 15 أنّ 48 بندًا من 96
      تقع في 26 مجموعةً كودُها NULL فتسقط من السايدبار **صامتةً** — لا خطأَ ولا
      أثر. وكانت هذه الشاشةُ ستنضمُّ إليها لو وُضعت في «إعدادات النظام وبصمة
      الإصدار» (5992، كودُها NULL) كما بدا أنّه موضعُها الطبيعيّ. فتُنشأ لها
      مجموعةٌ ذاتُ كودٍ يُقاس تصييرُها. */
$GROUP = $one("SELECT id FROM link_groups WHERE group_code='" . $esc($GCODE) . "' LIMIT 1");
if ($GROUP === null) {
    $gord = (int) $one("SELECT COALESCE(MAX(display_order),0)+1 FROM link_groups WHERE owner_role_id={$ROLE}");
    $st = $conn->prepare("INSERT INTO link_groups (name, group_code, owner_role_id, icon, display_order, is_active)
                          VALUES (?, ?, ?, 'fa fa-satellite-dish', ?, 1)");
    $st->bind_param('ssii', $GNAME, $GCODE, $ROLE, $gord);
    if (!$st->execute()) { exit("✘ تعذّر إنشاءُ المجموعة: {$conn->error}\n"); }
    $GROUP = $conn->insert_id;
    echo "  ✔ link_groups — أُنشئت «{$GNAME}» (id={$GROUP}، الكود {$GCODE})\n";
} else {
    echo "  ○ link_groups — قائمةٌ سلفًا (id={$GROUP})\n";
}
$GROUP = (int) $GROUP;

/* ── ① modules — الهويّة ──────────────────────────────────────────────────── */
$moduleId = $one("SELECT id FROM modules WHERE code='" . $esc($CODE) . "' LIMIT 1");
if ($moduleId === null) {
    $ord = (int) $one("SELECT COALESCE(MAX(display_order),0)+1 FROM modules WHERE owner_role_id={$ROLE}");
    $st = $conn->prepare("INSERT INTO modules (name, code, owner_role_id, group_id, is_link, is_quick, icon, display_order)
                          VALUES (?, ?, ?, ?, 'yes', 0, ?, ?)");
    $st->bind_param('ssiisi', $LABEL, $CODE, $ROLE, $GROUP, $ICON, $ord);
    if (!$st->execute()) { exit("✘ تعذّر إنشاءُ الموديول: {$conn->error}\n"); }
    $moduleId = $conn->insert_id;
    echo "  ✔ modules — أُنشئ (id={$moduleId})\n";
} else {
    echo "  ○ modules — قائمٌ سلفًا (id={$moduleId})\n";
}
$moduleId = (int) $moduleId;

/* ── ② nav_items — الظهور ─────────────────────────────────────────────────── */
$navId = $one("SELECT id FROM nav_items WHERE role_id={$ROLE} AND route='" . $esc($CODE) . "' LIMIT 1");
if ($navId === null) {
    $sort = (int) $one("SELECT COALESCE(MAX(sort_order),0)+1 FROM nav_items WHERE role_id={$ROLE} AND group_id={$GROUP}");
    // `door` هو varchar(16) NOT NULL بلا قيمةٍ افتراضية — يُؤخذ من جارِ المجموعة،
    // فإن خلت المجموعةُ فمن أيِّ بندٍ للدور، وإلا فشل الإدراجُ صامتًا.
    $door = $one("SELECT door FROM nav_items WHERE role_id={$ROLE} AND group_id={$GROUP} LIMIT 1");
    if ($door === null) { $door = $one("SELECT door FROM nav_items WHERE role_id={$ROLE} LIMIT 1"); }
    if ($door === null) { exit("✘ لا يمكن اشتقاقُ door لدور {$ROLE} — أوقفتُ قبلَ إدراجٍ ناقص\n"); }
    $st = $conn->prepare("INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, permission_code, active)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
    $st->bind_param('isiisssis', $ROLE, $door, $GROUP, $moduleId, $LABEL, $CODE, $ICON, $sort, $CODE);
    if (!$st->execute()) { exit("✘ تعذّر إنشاءُ بندِ الملاحة: {$conn->error}\n"); }
    echo "  ✔ nav_items — أُنشئ (المجموعة {$GROUP}، الترتيب {$sort})\n";
} else {
    $conn->query("UPDATE nav_items SET active=1, module_id={$moduleId}, group_id={$GROUP} WHERE id=" . (int) $navId);
    echo "  ○ nav_items — قائمٌ سلفًا (id={$navId}) — فُعِّل ونُقل إلى المجموعة {$GROUP}\n";
}

/* ── ③ gov_profile_items — المنحُ النافذ (الحاكم) ─────────────────────────── */
$profiles = array();
$rs = $conn->query(
    "SELECT DISTINCT p.profile_id
       FROM gov_authority_grants g
       JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state = 'active'
       JOIN users u ON u.id = g.user_id
      WHERE u.role = '{$ROLE}'");
while ($rs && ($r = $rs->fetch_row())) { $profiles[] = (int) $r[0]; }

if (!$profiles) {
    echo "  ⚠ gov_profile_items — لا قالبَ نافذًا لدور {$ROLE}: المنحُ يقع على role_permissions وحدَه\n";
}
foreach ($profiles as $pid) {
    $have = $one("SELECT item_id FROM gov_profile_items
                   WHERE profile_id={$pid} AND item_kind='screen' AND item_ref='" . $esc($CODE) . "' LIMIT 1");
    if ($have !== null) { echo "  ○ gov_profile_items — قائمٌ في القالب {$pid}\n"; continue; }
    $st = $conn->prepare("INSERT INTO gov_profile_items (company_id, profile_id, item_kind, item_ref, allow, can_add, can_edit, can_delete, seeded_from)
                          VALUES (0, ?, 'screen', ?, 1, 0, 0, 0, 'bus_monitor_relocate')");
    $st->bind_param('is', $pid, $CODE);
    if (!$st->execute()) { exit("✘ تعذّر منحُ القالب {$pid}: {$conn->error}\n"); }
    echo "  ✔ gov_profile_items — مُنح في القالب {$pid} (عرضٌ فقط — الشاشةُ قرائيّة)\n";
}

/* ── ④ nav_route_group — رأسُ الطيِّ في السايدبار ──────────────────────────
   المُصيِّرُ يقرأ خريطةَ «مسار ⇒ مجموعة» من هذا الجدولِ **بالمسارِ وحدَه
   وبحروفٍ صغيرة** (`includes/unified_nav.php:1140`). وما لا رأسَ طيٍّ له
   يسقط من القائمةِ صامتًا — لا خطأَ ولا أثر. */
$rcode = 'SETUP';
$rroute = strtolower($CODE);
$have = $one("SELECT group_code FROM nav_route_group WHERE route='" . $esc($rroute) . "' LIMIT 1");
if ($have === null) {
    $st = $conn->prepare("INSERT INTO nav_route_group (route, group_code, basis) VALUES (?, ?, 'GROUP:مراقبة ناقل الأحداث')");
    $st->bind_param('ss', $rroute, $rcode);
    if (!$st->execute()) { exit("✘ تعذّر إنشاءُ رأسِ الطيّ: {$conn->error}\n"); }
    echo "  ✔ nav_route_group — أُنشئ ({$rcode})\n";
} else {
    echo "  ○ nav_route_group — قائمٌ سلفًا ({$have})\n";
}

/* ── ⑤ nav_workspace_placements — **الحاكمُ الفعليُّ للتصيير** ──────────────
   ⛔ **وهذا هو السجلُّ الذي بدونه لا يظهر شيء**: `navarch_renderer.php` يدور
      على **الإحلالات** ويستعمل `nav_items` **مرشِّحًا** لا مصدرًا
      (`navarch_authorized_routes` — «الصلاحيّةُ ترشِّح ولا تُنشئ موضعًا»).
      فمسارٌ بلا صفٍّ هنا **لا يُصيَّر أبدًا** مهما اكتملت سجلّاتُه الأربعة.
   ◆ **والمسارُ يُخزَّن مُطبَّعًا**: بحروفٍ صغيرةٍ وبلا `.php` (`navarch_norm_route`).
      وهذا ما أخفى العطبَ في أوّلِ قياسٍ: بحثٌ بالمسارِ كما هو يرجع صفرًا لكلِّ
      الشاشاتِ — المُصيَّرةِ منها والغائبة — فيبدو الجدولُ غيرَ ذي صلة. */
$WS      = 'DEP-08';   // مساحةُ عملِ دور 15 — من إحلالِ `governance/perm_matrix`
$WSGROUP = 54;         // "الأنظمة والقوائم المرجعية" — لا مجموعة الصلاحيات (حاجب U9: القسم ≤9)
$nroute  = strtolower(preg_replace('~\.php$~i', '', $CODE));
$have = $one("SELECT placement_id FROM nav_workspace_placements WHERE route='" . $esc($nroute) . "' LIMIT 1");
if ($have === null) {
    $pid  = 'WP-' . strtoupper(substr(hash('sha256', $nroute), 0, 16));
    $scr  = 'SCR-BM01';
    $sort = (int) $one("SELECT COALESCE(MAX(sort_no),0)+1 FROM nav_workspace_placements WHERE workspace_id='" . $esc($WS) . "'");
    $st = $conn->prepare(
        "INSERT INTO nav_workspace_placements
           (placement_id, screen_id, workspace_id, group_id, placement_type, sort_no, route,
            canonical_label, governing_source, source_ref, reason_code, effective_from, status, version, created_by)
         VALUES (?, ?, ?, ?, 'PRIMARY', ?, ?, ?,
            'مراقبةُ الناقلِ جزءٌ من دورةِ تشغيلِ النظامِ التي تملكها المساحة',
            'نقلُ عينِ المراقبةِ من بوّابةِ المزوّدِ المغلقةِ قبلَ حذفِ admin/',
            'GUIDE_OWNED_LIFECYCLE_S9', CURDATE(), 'ACTIVE', 1, 'bus_monitor_relocate')");
    $st->bind_param('sssisss', $pid, $scr, $WS, $WSGROUP, $sort, $nroute, $LABEL);
    if (!$st->execute()) { exit("✘ تعذّر إنشاءُ الإحلال: {$conn->error}\n"); }
    echo "  ✔ nav_workspace_placements — أُنشئ ({$pid} · {$WS} · ترتيب {$sort})\n";
} else {
    $conn->query("UPDATE nav_workspace_placements SET status='ACTIVE' WHERE placement_id='" . $esc($have) . "'");
    echo "  ○ nav_workspace_placements — قائمٌ سلفًا ({$have}) — فُعِّل\n";
}

/* ── ⑥ role_permissions — المسارُ القائم لغيرِ المغطَّى ───────────────────── */
$have = $one("SELECT id FROM role_permissions WHERE role_id={$ROLE} AND module_id={$moduleId} LIMIT 1");
if ($have === null) {
    $conn->query("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                  VALUES ({$ROLE}, {$moduleId}, 1, 0, 0, 0)");
    echo "  ✔ role_permissions — أُنشئ لدور {$ROLE}\n";
} else {
    echo "  ○ role_permissions — قائمٌ سلفًا\n";
}

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn);
echo "══ تمّ\n";
