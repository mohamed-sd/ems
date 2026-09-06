<?php
/**
 * 2028_05_30_settings_db_backup_screen.php — تسجيلُ شاشةِ النسخِ الاحتياطي
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **قدرةٌ كانت قائمةً ولا سبيلَ إليها**: واجهةُ النسخِ الاحتياطيِّ الوحيدةُ في
 *   المشروعِ كانت كتلةً داخلَ `admin/settings.php`، وبوّابةُ المزوّدِ مُغلقةٌ
 *   بـ403 منذ قرارِ الشركةِ الواحدة. فالمحرّكُ يعمل والزرُّ خلفَ بابٍ مردود.
 *   نُقلت الشاشةُ إلى `Settings/db_backup.php` بحارسِ المستأجر، وهذه الهجرةُ
 *   تُسجّلها كي تُحَلَّ إلى موديولٍ وتظهرَ لدور 15.
 *
 * ⛔ **وثلاثةُ سجلّاتٍ لا واحد** — والثالثُ هو الحاكمُ فعلًا:
 *   ① `modules`      — الهويّة: بلا صفٍّ هنا تردُّ `get_current_page_permissions`
 *                      منعًا كاملًا (`unresolved_script_path`).
 *   ② `nav_items`    — الظهورُ في السايدبار لدور 15.
 *   ③ `gov_profile_items` — **المنحُ النافذ**: مستخدما الدور 15 (56 · 889)
 *                      مغطَّيان بالقالبِ النافذِ 528، و«لا شاشةَ خارجَ القالب».
 *                      فصفٌّ في `role_permissions` وحدَه **لا يفتح شيئًا** لمن
 *                      كان مغطًّى — وهذا مصدرُ خضرةٍ كاذبةٍ إن اكتُفي به.
 *   ويُكتب `role_permissions` أيضًا لغيرِ المغطَّى (المسارُ القائم).
 *
 * ⛔ **ولا يُمنح حذفٌ**: `can_delete` صفرٌ في القالب — حذفُ نسخةٍ احتياطيّةٍ
 *   فعلٌ لا رجعةَ فيه، ويُمنح بقرارٍ صريحٍ لا ببذرِ تسجيل.
 *
 * التشغيل: php database/migrations/2028_05_30_settings_db_backup_screen.php
 * العكس:   php database/migrations/2028_05_30_settings_db_backup_screen_down.php
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

$CODE   = 'Settings/db_backup.php';
$LABEL  = 'النسخ الاحتياطي لقاعدة البيانات';
$ROLE   = 15;
$GCODE  = 'sys_backup_r15';
$GNAME  = 'النظام والنسخ الاحتياطي';
$ICON   = 'fa fa-database';

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
                          VALUES (?, ?, ?, 'fa fa-database', ?, 1)");
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
                          VALUES (0, ?, 'screen', ?, 1, 1, 1, 0, 'db_backup_relocate')");
    $st->bind_param('is', $pid, $CODE);
    if (!$st->execute()) { exit("✘ تعذّر منحُ القالب {$pid}: {$conn->error}\n"); }
    echo "  ✔ gov_profile_items — مُنح في القالب {$pid} (عرض · إنشاء · جدولة · بلا حذف)\n";
}

/* ── ④ nav_route_group — رأسُ الطيِّ في السايدبار ──────────────────────────
   المُصيِّرُ يقرأ خريطةَ «مسار ⇒ مجموعة» من هذا الجدولِ **بالمسارِ وحدَه
   وبحروفٍ صغيرة** (`includes/unified_nav.php:1140`). وما لا رأسَ طيٍّ له
   يسقط من القائمةِ صامتًا — لا خطأَ ولا أثر. */
$rcode = 'SETUP';
$rroute = strtolower($CODE);
$have = $one("SELECT group_code FROM nav_route_group WHERE route='" . $esc($rroute) . "' LIMIT 1");
if ($have === null) {
    $st = $conn->prepare("INSERT INTO nav_route_group (route, group_code, basis) VALUES (?, ?, 'GROUP:النظام والنسخ الاحتياطي')");
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
    $scr  = 'SCR-BK01';
    $sort = (int) $one("SELECT COALESCE(MAX(sort_no),0)+1 FROM nav_workspace_placements WHERE workspace_id='" . $esc($WS) . "'");
    $st = $conn->prepare(
        "INSERT INTO nav_workspace_placements
           (placement_id, screen_id, workspace_id, group_id, placement_type, sort_no, route,
            canonical_label, governing_source, source_ref, reason_code, effective_from, status, version, created_by)
         VALUES (?, ?, ?, ?, 'PRIMARY', ?, ?, ?,
            'النسخُ الاحتياطيُّ جزءٌ من دورةِ تشغيلِ النظامِ التي تملكها المساحة',
            'نقلُ واجهةِ النسخِ من بوّابةِ المزوّدِ المغلقةِ قبلَ حذفِ admin/',
            'GUIDE_OWNED_LIFECYCLE_S9', CURDATE(), 'ACTIVE', 1, 'db_backup_relocate')");
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
                  VALUES ({$ROLE}, {$moduleId}, 1, 1, 1, 0)");
    echo "  ✔ role_permissions — أُنشئ لدور {$ROLE}\n";
} else {
    echo "  ○ role_permissions — قائمٌ سلفًا\n";
}

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn);
echo "══ تمّ\n";
