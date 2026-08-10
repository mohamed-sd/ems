<?php
/**
 * مجموعةُ «فريق العمل» في قوائم الأدوار الرئيسية — رابطُ «إدارة المعاونين»
 * ═══════════════════════════════════════════════════════════════════════════
 * قرارُ المالك (2026-08-09): «أضف رابطَ main/project_users.php في كلِّ الأدوار
 * الرئيسية — تحت مجموعةٍ جديدةٍ اسمُها فريق العمل، موضعُها قبلَ الأخيرة مباشرةً
 * (والأخيرةُ: خارج الوثيقة — بانتظار قرار المالك). وحيثما وُجد الرابطُ باسمٍ
 * مختلف — يُنقل إلى هذه المجموعةِ باسم إدارة المعاونين.»
 *
 * ◆ النطاق = الأدوارُ الرئيسيةُ وحدَها (`parent_role_id` فارغ) بحكمِ المالك:
 *   «الدورُ الرئيسيُّ هو الذي يضيف معاونين، لا الأدوارُ المشرفةُ التابعةُ له» —
 *   وهو عينُ ما يفرضه الخادمُ أصلًا في الشاشة (الدورُ المسنَدُ يجب أن يكون
 *   ابنًا لدورِ المُسنِد)، فالقائمةُ صارت تطابق الحكمَ بدل أن تخالفه.
 *   يُستثنى الدور 5 («إدارة الموقع — قديم · مُدمج في 6»): قائمتُه مطفأةٌ
 *   بكاملها، وإحياءُ سطرٍ واحدٍ فيها يصنع قائمةً كاذبةً لدورٍ متقاعد.
 *
 * ◆ لماذا البادئة `n9o_` وليست `n9s_` (وهذا جوهرُ الأمان البنيوي هنا):
 *   `tools/nav09_verify.php` يقارن المولَّدَ بوثيقة NAV-09 **صفًّا صفًّا بلا
 *   زيادةٍ ولا نقصان** — لكنه يقصر المقارنةَ على `group_code LIKE 'n9s%'`.
 *   و`tools/nav09_sweep_others.php` يكنس كلَّ رابطٍ خارجَ `n9s%`/`n9o%` إلى
 *   مجموعة «أخرى — للمراجعة». فالبادئةُ `n9o_` (سابقةُ «— بقرار المالك»
 *   القائمة) هي الموضعُ الوحيدُ الذي: لا يكذب على الوثيقة، ولا يُكنس لاحقًا.
 *   أيُّ بادئةٍ أخرى تُسقط بوابةَ التسليم أو تبتلع المجموعةَ بعد أولِ كنس.
 *
 * ◆ المرحلة 98: `printStageNav` يرتّب بـ`ksort` على رقمِ المرحلة — فـ98 تقع
 *   قبلَ 99 («خارج الوثيقة») مباشرةً، وتصير الأخيرةَ عند من لا مرحلةَ 99 له.
 *
 * ◆ الصلاحية: الشاشةُ محروسةٌ بالوحدة 14 (`main/project_users.php`) — ولا
 *   يُلمس صفُّ صلاحيةٍ قائمٌ (قرارُ مالكٍ سابقٌ لا يُنقض آليًّا)؛ يُنشأ الناقصُ
 *   فقط. والصفوفُ الشاذّةُ القائمةُ تُطبع في التقرير لينظر فيها المالك.
 *
 * idempotent — كلُّ خطوةٍ تفحص حالتَها قبل الفعل، وإعادةُ التشغيل بلا أثر.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "اتصال المرحِّل فشل: " . $conn->connect_error . "\n"); exit(1); }
$conn->set_charset('utf8mb4');
$log = function ($m) { echo "  $m\n"; };

/* الأدوارُ الرئيسيةُ الحيّةُ (parent فارغ · status نشط) — تُقرأ من القاعدة لا
   تُثبَّت رقمًا، فدورٌ رئيسيٌّ جديدٌ يلتحق بإعادةِ التشغيل بلا تعديلِ ملف. */
$SKIP = array(5); // الدور المتقاعد (مُدمج في 6)
$roles = array();
$r = $conn->query("SELECT id, name FROM roles
                    WHERE (parent_role_id IS NULL OR parent_role_id = 0)
                      AND (status = '1' OR status = 1)
                    ORDER BY id");
while ($x = $r->fetch_assoc()) {
    if (in_array(intval($x['id']), $SKIP, true)) { continue; }
    $roles[intval($x['id'])] = $x['name'];
}
$log('الأدوارُ الرئيسيةُ المستهدفة: ' . count($roles) . ' — [' . implode(', ', array_keys($roles)) . ']');

/* الوحدةُ الحاكمةُ للشاشة: أدنى id لكودها (عرفُ الحارس في permissions_helper) */
$MODULE_ID = 0;
$r = $conn->query("SELECT MIN(id) id FROM modules WHERE code = 'main/project_users.php'");
if ($r && ($x = $r->fetch_assoc())) { $MODULE_ID = intval($x['id']); }
if ($MODULE_ID <= 0) { fwrite(STDERR, "وحدةُ main/project_users.php غير مسجَّلة — أوقفتُ الترحيل\n"); exit(1); }
$log("وحدةُ الصلاحيات: #$MODULE_ID");

const TEAM_LABEL = 'إدارة المعاونين';
const TEAM_GROUP = 'فريق العمل';
const TEAM_ICON  = 'fa fa-users-gear';
const TEAM_ROUTE = 'main/project_users.php';
const TEAM_STAGE = 98;
const TEAM_ORDER = 9800;

$madeGroups = 0; $movedNav = 0; $newNav = 0; $grants = 0; $oddPerms = array();

foreach ($roles as $roleId => $roleName) {

    /* ① مجموعةُ «فريق العمل» لهذا الدور — بمفتاحٍ فريدٍ لا يتكرر */
    $gcode = 'n9o_team_r' . $roleId;
    $gid = 0;
    $st = $conn->prepare("SELECT id FROM link_groups WHERE group_code = ? LIMIT 1");
    $st->bind_param('s', $gcode);
    $st->execute();
    if ($g = $st->get_result()->fetch_assoc()) { $gid = intval($g['id']); }
    $st->close();

    if ($gid === 0) {
        $st = $conn->prepare("INSERT INTO link_groups
                (name, group_code, owner_role_id, icon, display_order, stage_no, stage_title, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
        $nm = TEAM_GROUP; $ic = TEAM_ICON; $ord = TEAM_ORDER; $stg = TEAM_STAGE; $ttl = TEAM_GROUP;
        $st->bind_param('ssisiis', $nm, $gcode, $roleId, $ic, $ord, $stg, $ttl);
        $st->execute();
        $gid = intval($conn->insert_id);
        $st->close();
        $madeGroups++;
        $log("دور $roleId ($roleName): مجموعةٌ جديدة #$gid");
    } else {
        /* موجودةٌ — تُعاد إلى عقدها (إصلاحُ أيِّ انحرافٍ يدويٍّ لاحق) */
        $st = $conn->prepare("UPDATE link_groups SET name = ?, icon = ?, display_order = ?,
                                     stage_no = ?, stage_title = ?, is_active = 1
                               WHERE id = ?");
        $nm = TEAM_GROUP; $ic = TEAM_ICON; $ord = TEAM_ORDER; $stg = TEAM_STAGE; $ttl = TEAM_GROUP;
        $st->bind_param('ssiisi', $nm, $ic, $ord, $stg, $ttl, $gid);
        $st->execute();
        $st->close();
        $log("دور $roleId ($roleName): مجموعةٌ قائمة #$gid — أُعيدت لعقدها");
    }

    /* ② صلاحيةُ العرضِ شرطُ الظهور: `unified_nav` لا يُصيِّر رابطًا بلا can_view=1
          على وحدته. الناقصُ يُنشأ بصلاحيةٍ كاملةٍ (الدورُ الرئيسيُّ يدير فريقَه)،
          والقائمُ **لا يُمسّ** — تعديلُه قرارُ مالكٍ لا أثرٌ جانبيٌّ لترحيل. */
    $st = $conn->prepare("SELECT can_view, can_add, can_edit, can_delete
                            FROM role_permissions WHERE role_id = ? AND module_id = ? LIMIT 1");
    $st->bind_param('ii', $roleId, $MODULE_ID);
    $st->execute();
    $perm = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$perm) {
        $st = $conn->prepare("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                              VALUES (?, ?, 1, 1, 1, 1)");
        $st->bind_param('ii', $roleId, $MODULE_ID);
        $st->execute();
        $st->close();
        $grants++;
    } elseif (intval($perm['can_view']) !== 1 || intval($perm['can_add']) !== 1
              || intval($perm['can_edit']) !== 1 || intval($perm['can_delete']) !== 1) {
        /* صفٌّ قائمٌ أضيقُ من الكامل — يُعلَن ولا يُغيَّر */
        $oddPerms[] = sprintf('دور %d (%s): view=%d add=%d edit=%d delete=%d',
            $roleId, $roleName, $perm['can_view'], $perm['can_add'], $perm['can_edit'], $perm['can_delete']);
    }

    /* ③ الرابط: يُنقل إن وُجد بأيِّ اسم، ويُنشأ إن لم يوجد — لا نسختان أبدًا */
    $rows = array();
    $st = $conn->prepare("SELECT id FROM nav_items WHERE role_id = ? AND route LIKE '%project_users%' ORDER BY id");
    $st->bind_param('i', $roleId);
    $st->execute();
    $rs = $st->get_result();
    while ($x = $rs->fetch_assoc()) { $rows[] = intval($x['id']); }
    $st->close();

    if ($rows) {
        $keep = array_shift($rows);
        $st = $conn->prepare("UPDATE nav_items
                                 SET door = 'REC', group_id = ?, module_id = ?, label_ar = ?, route = ?,
                                     icon = ?, sort_order = 10, permission_code = ?, active = 1,
                                     updated_at = NOW()
                               WHERE id = ?");
        $lb = TEAM_LABEL; $rt = TEAM_ROUTE; $ic = TEAM_ICON; $pc = TEAM_ROUTE;
        $st->bind_param('iissssi', $gid, $MODULE_ID, $lb, $rt, $ic, $pc, $keep);
        $st->execute();
        $st->close();
        $movedNav++;
        /* أيُّ تكرارٍ زائدٍ يُطفأ لا يُحذف (أثرُ القرار يبقى مقروءًا) */
        foreach ($rows as $dup) {
            $conn->query("UPDATE nav_items SET active = 0, updated_at = NOW() WHERE id = " . intval($dup));
        }
    } else {
        $st = $conn->prepare("INSERT INTO nav_items
                (role_id, door, group_id, module_id, label_ar, route, icon, sort_order,
                 permission_code, active, created_at, updated_at)
                VALUES (?, 'REC', ?, ?, ?, ?, ?, 10, ?, 1, NOW(), NOW())");
        $lb = TEAM_LABEL; $rt = TEAM_ROUTE; $ic = TEAM_ICON; $pc = TEAM_ROUTE;
        $st->bind_param('iiissss', $roleId, $gid, $MODULE_ID, $lb, $rt, $ic, $pc);
        $st->execute();
        $st->close();
        $newNav++;
    }
}

echo "\n";
$log("مجموعاتٌ أُنشئت: $madeGroups · روابطُ نُقلت وسُمّيت: $movedNav · روابطُ جديدة: $newNav · منحُ صلاحيةٍ ناقصةٍ أُنشئت: $grants");
if ($oddPerms) {
    echo "\n  ◆ صفوفُ صلاحيةٍ قائمةٌ أضيقُ من الكامل — لم تُمسّ، وقرارُها للمالك:\n";
    foreach ($oddPerms as $o) { echo "     - $o\n"; }
}
echo "\n";
