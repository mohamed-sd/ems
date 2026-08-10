<?php
/**
 * «فريق العمل» للأدوار خارجَ علَمِ التنقّل الموحَّد — إتمامُ ترحيل 2027_01_03
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ العيبُ الذي يُصلحه (بلاغُ المالك: «لم تُضف المجموعةُ في إدارة المخاطر»):
 *   السايدبار له **مسارا تصييرٍ لا واحد**، والفاصلُ بينهما علَمٌ في `.env`:
 *
 *     EMS_NAV_UNIFIED_ROLES = 1..26
 *       ├─ دورٌ داخلَ العلَم → `renderUnifiedNavigationV2` يقرأ **`nav_items`**
 *       └─ دورٌ خارجَه      → `getDynamicNavLinks` يقرأ **`modules.group_id`**
 *
 *   وترحيلُ 2027_01_03 كتب في `nav_items` وحدَه — فأصابَ خمسةَ عشرَ دورًا
 *   وأخطأ **أربعة**: 27 (القوى التشغيلية) · **28 (إدارة المخاطر)** ·
 *   32 (المدير المالي) · 33 (المراجع الداخلي المستقل). صفوفُها كُتبت صحيحةً
 *   ولا قارئَ لها.
 *
 * ◆ الدرسُ المُرّ: تحقّقتُ من ثلاثةِ أدوارٍ كلُّها داخلَ العلَم وعمّمت. وقاعدةُ
 *   البيت نفسُها تقول: **لا يُعمَّم حكمٌ من عيّنةٍ متجانسة** — وشاهدُ العلَمِ
 *   كان سطرًا واحدًا في `.env` يفصل الأدوارَ صنفين.
 *
 * ◆ لماذا لا يُوسَّع العلَمُ بدلَ ذلك: قلبُ دورٍ إلى المصدر الموحَّد يُبدّل
 *   **قائمتَه كلَّها** لا سطرًا فيها — وهو قرارُ طرحٍ لا أثرٌ جانبيٌّ لإضافةِ
 *   رابط. (والدوران 32 و33 صفوفُهما في `nav_items` معطوبةٌ أصلًا: `door='main'`
 *   وهو ليس بابًا معروفًا، و`group_id` فارغ — فقلبُهما يمحو قائمتَهما.)
 *
 * ◆ أثرٌ مُعلَن لا مطويّ: المسارُ القديم يورّث الابنَ وحداتِ أبيه
 *   (`owner_role_id IN (role, parent_role)`) **بلا فحصِ صلاحية**. فالدوران
 *   29 و30 (محلل/مشرف المخاطر) سيريان الرابطَ حتمًا. ولئلا يكون رابطًا ميتًا
 *   يرتدّ بـ403، يُمنحان **عرضًا فقط** — يريان مَن يتبعهم ولا يضيفون؛ وحكمُ
 *   المالك محفوظٌ («الرئيسيُّ هو الذي يضيف») بمنعٍ مزدوج: صفرُ صلاحيةِ إضافة،
 *   وحارسُ الخادم يرفض إسنادَ دورٍ ليس ابنًا لدورهما (ولا أبناءَ لهما).
 *
 * idempotent — كلُّ خطوةٍ تفحص حالتَها قبل الفعل.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "اتصال المرحِّل فشل: " . $conn->connect_error . "\n"); exit(1); }
$conn->set_charset('utf8mb4');
$log = function ($m) { echo "  $m\n"; };

const TEAM_LABEL = 'إدارة المعاونين';
const TEAM_ICON  = 'fa fa-users-gear';
const TEAM_CODE  = 'main/project_users.php';
const TEAM_ORDER = 9800;

/* الأدوارُ التي لها مجموعةُ «فريق العمل» ولا يقرأ سايدبارُها `nav_items` —
   تُشتق من العلَم نفسِه لا تُثبَّت رقمًا، فتوسيعُ العلَمِ لاحقًا يجعل إعادةَ
   التشغيل بلا أثر بدل أن تُبقيَ صفوفًا لا يقرؤها أحد. */
$flag = (string) ems_env('EMS_NAV_UNIFIED_ROLES', '');
$unified = array_filter(array_map('trim', explode(',', $flag)), function ($s) { return $s !== ''; });
$log('علَمُ التنقّل الموحَّد يغطي ' . count($unified) . ' دورًا');

$targets = array();
$r = $conn->query("SELECT owner_role_id, id FROM link_groups WHERE group_code LIKE 'n9o_team_r%' ORDER BY owner_role_id");
while ($x = $r->fetch_assoc()) {
    if (in_array((string) intval($x['owner_role_id']), $unified, true)) { continue; }
    $targets[intval($x['owner_role_id'])] = intval($x['id']);
}
if (!$targets) { $log('لا دورَ خارجَ العلَم يحمل المجموعة — لا عمل'); exit(0); }
$log('الأدوارُ على المسار القديم: [' . implode(', ', array_keys($targets)) . ']');

$madeMods = 0; $madePerms = 0; $heirs = 0;

foreach ($targets as $roleId => $groupId) {
    $rn = '';
    $s = $conn->prepare("SELECT name FROM roles WHERE id = ?");
    $s->bind_param('i', $roleId); $s->execute();
    if ($x = $s->get_result()->fetch_assoc()) { $rn = $x['name']; }
    $s->close();

    /* ① صفُّ الوحدة — قارئُ المسار القديم. `group_id` يربطه بمجموعةِ الدور
          نفسِها التي أنشأها الترحيلُ السابق، فالمجموعةُ واحدةٌ يقرؤها المساران. */
    $modId = 0;
    $s = $conn->prepare("SELECT id FROM modules WHERE code = ? AND owner_role_id = ? LIMIT 1");
    $code = TEAM_CODE;
    $s->bind_param('si', $code, $roleId); $s->execute();
    if ($x = $s->get_result()->fetch_assoc()) { $modId = intval($x['id']); }
    $s->close();

    if ($modId === 0) {
        $s = $conn->prepare("INSERT INTO modules (name, code, owner_role_id, group_id, is_link, is_quick, icon, display_order)
                             VALUES (?, ?, ?, ?, '1', 0, ?, ?)");
        $nm = TEAM_LABEL; $ic = TEAM_ICON; $ord = TEAM_ORDER;
        $s->bind_param('ssiisi', $nm, $code, $roleId, $groupId, $ic, $ord);
        $s->execute();
        $modId = intval($conn->insert_id);
        $s->close();
        $madeMods++;
        $log("دور $roleId ($rn): وحدةٌ جديدة #$modId في المجموعة #$groupId");
    } else {
        $s = $conn->prepare("UPDATE modules SET name = ?, group_id = ?, is_link = '1', icon = ?, display_order = ?
                              WHERE id = ?");
        $nm = TEAM_LABEL; $ic = TEAM_ICON; $ord = TEAM_ORDER;
        $s->bind_param('sisii', $nm, $groupId, $ic, $ord, $modId);
        $s->execute(); $s->close();
        $log("دور $roleId ($rn): وحدةٌ قائمة #$modId — أُعيدت لعقدها");
    }

    /* ② صلاحيةُ الدورِ على وحدتِه الجديدة — وإلا رأى الشاشةَ حارسُها بلا صفٍّ
          فأغلقها (الارتدادُ fail-closed بعد إغلاقِ ثغرةِ «افترض كلَّ شيء»). */
    $s = $conn->prepare("SELECT id FROM role_permissions WHERE role_id = ? AND module_id = ? LIMIT 1");
    $s->bind_param('ii', $roleId, $modId); $s->execute();
    $has = (bool) $s->get_result()->fetch_assoc();
    $s->close();
    if (!$has) {
        $s = $conn->prepare("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                             VALUES (?, ?, 1, 1, 1, 1)");
        $s->bind_param('ii', $roleId, $modId); $s->execute(); $s->close();
        $madePerms++;
    }

    /* ③ الورثةُ الحتميون: المسارُ القديم يعطي الابنَ وحداتِ أبيه بلا فحص —
          فيُمنح **عرضًا فقط** على الوحدة الحاكمة (14، وهي التي يحلّها حارسُ
          الشاشة للدورِ بلا وحدةٍ خاصة). لا إضافةَ ولا تعديلَ ولا حذف. */
    $kids = array();
    $s = $conn->prepare("SELECT id, name FROM roles WHERE parent_role_id = ? AND (status = '1' OR status = 1)");
    $s->bind_param('i', $roleId); $s->execute();
    $rs = $s->get_result();
    while ($x = $rs->fetch_assoc()) { $kids[intval($x['id'])] = $x['name']; }
    $s->close();

    foreach ($kids as $kid => $kname) {
        if (in_array((string) $kid, $unified, true)) { continue; } // ابنٌ على المصدر الموحَّد لا يرث
        $govId = 0;
        $r2 = $conn->query("SELECT MIN(id) id FROM modules WHERE code = '" . TEAM_CODE . "'");
        if ($r2 && ($x = $r2->fetch_assoc())) { $govId = intval($x['id']); }
        if ($govId <= 0) { continue; }

        $s = $conn->prepare("SELECT id FROM role_permissions WHERE role_id = ? AND module_id = ? LIMIT 1");
        $s->bind_param('ii', $kid, $govId); $s->execute();
        $has = (bool) $s->get_result()->fetch_assoc();
        $s->close();
        if (!$has) {
            $s = $conn->prepare("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                                 VALUES (?, ?, 1, 0, 0, 0)");
            $s->bind_param('ii', $kid, $govId); $s->execute(); $s->close();
            $heirs++;
            $log("   ↳ الوارث $kid ($kname): عرضٌ فقط على الوحدة #$govId — الرابطُ يورَّث بنيويًّا فلا يُترك ميتًا");
        }
    }
}

echo "\n";
$log("وحداتٌ أُنشئت: $madeMods · منحُ الدورِ المالك: $madePerms · منحُ عرضٍ للورثة: $heirs");
echo "\n";
