<?php
/**
 * تحصينُ الحوكمة — إغلاقُ الثغرات الحرجة الثلاث (SEC-GOV · 2026-08-06)
 * ═══════════════════════════════════════════════════════════════════════════
 * منشؤها جولةُ فحصٍ بدور المبيعات (12) كشفت:
 *   ح-01  Settings/modules.php غيرُ مسجَّلةٍ في modules ⇒ الحارسُ المركزي شفافٌ
 *         عليها ⇒ سجلُّ الشاشات (237 موديولًا) مفتوحٌ لكل مستخدمٍ مسجَّل.
 *   ح-02  عشرةُ أدوارٍ تملك add/edit/delete على main/users.php (موديول 3).
 *   ح-03  صلاحياتُ كتابةٍ خارج القائمة الجانبية في كل الأدوار الموروثة.
 *
 * الطبقةُ الكوديّة أُنجزت في includes/governance_guard.php (fail-closed بالبناء)؛
 * وهذه الهجرةُ تُصلح **البيانات**:
 *
 *   ① تسجيلُ Settings/modules.php موديولًا (مالكُه 15) ومنحُ 1 و15 عليه —
 *      فتظهر الشاشةُ في شاشة صلاحيات الأدوار وتصير قابلةً للحوكمة لا خفيّة.
 *
 *   ② ق-أ · الحوكمةُ لمالكها: شاشاتُ منحِ الصلاحية التي لا حارسَ تصعيدٍ فيها
 *      تُصفَّر لكل دورٍ ليس مالكَها ولا من أدوار إدارة الحسابات (1 · 15).
 *      main/project_users.php مستثناةٌ عمدًا — فيها حارسُ «الدورُ ابنُ دورك».
 *
 *   ③ ق-ب · الكتابةُ تتبع الباب: add/edit/delete تُنزع عن كل موديولٍ خارج
 *      القائمة الجانبية للدور، **ويبقى can_view** (الرؤيةُ العابرة للإدارات
 *      نمطٌ مقصودٌ في هذا النظام). الاستثناءات المحسوبة:
 *        • المالكُ لا يُنزع من شاشته (owner_role_id = role)
 *        • الشاشاتُ المفتوحةُ للجميع بنصِّ الحارس المركزي (بلاغات · مراسلات …)
 *        • الشاشاتُ الشخصية (Portal/ · my_* · تغيير كلمة المرور)
 *        • الشاشاتُ الفرعيةُ لبابٍ قائم (contracts_details ⊂ contracts) ووجهةُ
 *          تحويلٍ معلنة (timesheet.php ⇒ timesheet_type.php)
 *
 * التراجعُ الدقيق: كلُّ صفٍّ يُمَسّ يُنسخ أولًا إلى sec_perm_backup_20260806.
 *   UPDATE role_permissions rp JOIN sec_perm_backup_20260806 b
 *     ON b.role_id=rp.role_id AND b.module_id=rp.module_id
 *   SET rp.can_view=b.can_view, rp.can_add=b.can_add,
 *       rp.can_edit=b.can_edit, rp.can_delete=b.can_delete;
 *
 * idempotent: إعادةُ التشغيل لا تُغيّر شيئًا (الصفوفُ صارت مطابقةً للقاعدة)
 * ولا تُكرّر النسخَ الاحتياطي (INSERT IGNORE على مفتاحٍ مركّب).
 */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__, 2) . '/includes/env.php';
require_once dirname(__DIR__, 2) . '/includes/roles.php';

$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "اتصال المرحِّل فشل\n"); exit(1); }
$conn->set_charset('utf8mb4');

$DRY = in_array('--dry', $argv, true);
$say = function ($s) { echo $s . "\n"; };
$fail = function ($s) { fwrite(STDERR, $s . "\n"); exit(1); };

// ═══ سياسةٌ واحدةٌ مشتركةٌ مع includes/governance_guard.php وtools/sec_perm_checks.php ═══
/**
 * شاشاتُ الحوكمة التي يقرّر role_permissions وصولَها — وحدَها تخضع لق-أ.
 * Settings/guard_classification.php مستثناةٌ عمدًا: حارسُها قائمةُ أدوارٍ صلبةٌ
 * في الكود (1 · 19 · سوبر) ولا تقرأ role_permissions، فنزعُ صفِّها لا يحجب شيئًا
 * ويُحدث تناقضًا بين البيانات والكود.
 * main/project_users.php مستثناةٌ أيضًا: فيها حارسُ «الدورُ ابنُ دورك» خادميًّا.
 */
$GOV_SCREENS = array(
    'main/users.php', 'main/all_assistants.php', 'Settings/roles.php',
    'Settings/role_permissions.php', 'Settings/modules.php',
);
/** شاشاتُ حوكمةٍ حارسُها في الكود لا في role_permissions — لا ق-أ ولا ق-ب. */
$GOV_CODE_GUARDED = array('Settings/guard_classification.php');
$ADMIN_ROLES = array(EMS_ROLE_OPERATIONS_MGR, EMS_ROLE_PERMISSIONS_MGR); // '1' · '15'
$OPEN_SCREENS = array(
    'Tickets/ticket_contextual_open.php', 'Tickets/ticket_form.php', 'Tickets/tickets_list.php',
    'Maintenance/breakdowns.php', 'chats/index.php', 'main/dashboard.php', 'main/role_board.php',
);
$PERSONAL_PREFIX = array('Portal/', 'main/my_', 'main/profile.php', 'Settings/change_password.php', 'user_capacities.php');
$REDIRECT_ALIAS = array('Timesheet/timesheet_type.php' => 'Timesheet/timesheet.php');

// ═══ ⓪ جدولُ النسخ الاحتياطي ═══
$say("── ⓪ جدولُ التراجع");
$ok = $conn->query("CREATE TABLE IF NOT EXISTS `sec_perm_backup_20260806` (
    `role_id` INT NOT NULL,
    `module_id` INT NOT NULL,
    `can_view` TINYINT(1) NOT NULL DEFAULT 0,
    `can_add` TINYINT(1) NOT NULL DEFAULT 0,
    `can_edit` TINYINT(1) NOT NULL DEFAULT 0,
    `can_delete` TINYINT(1) NOT NULL DEFAULT 0,
    `rule_applied` VARCHAR(16) NOT NULL,
    `captured_at` DATETIME NOT NULL,
    PRIMARY KEY (`role_id`, `module_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
if (!$ok) { $fail("تعذّر إنشاء جدول التراجع: " . $conn->error); }
$say("  = sec_perm_backup_20260806 جاهز");

// ═══ ① تسجيلُ Settings/modules.php ═══
$say("── ① تسجيلُ سجلِّ الشاشات موديولًا");
$modulesModuleId = null;
$r = $conn->query("SELECT id FROM modules WHERE code = 'Settings/modules.php' LIMIT 1");
if ($r && $row = $r->fetch_assoc()) {
    $modulesModuleId = intval($row['id']);
    $say("  = مسجَّلةٌ سلفًا (#{$modulesModuleId})");
} elseif ($DRY) {
    $say("  + [جافّ] ستُسجَّل Settings/modules.php مالكُها 15");
} else {
    $st = $conn->prepare("INSERT INTO modules (name, code, owner_role_id, is_link, icon)
                          VALUES ('شاشات النظام والوحدات', 'Settings/modules.php', ?, 0, 'fa fa-layer-group')");
    $ownerId = intval(EMS_ROLE_PERMISSIONS_MGR);
    $st->bind_param('i', $ownerId);
    if (!$st->execute()) { $fail("تعذّر تسجيل الموديول: " . $st->error); }
    $modulesModuleId = intval($conn->insert_id);
    $say("  + سُجّلت (#{$modulesModuleId}) مالكُها " . EMS_ROLE_PERMISSIONS_MGR);
}
if ($modulesModuleId !== null && !$DRY) {
    foreach ($ADMIN_ROLES as $ar) {
        $arInt = intval($ar);
        $st = $conn->prepare("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                              VALUES (?, ?, 1, 1, 1, 1)
                              ON DUPLICATE KEY UPDATE can_view=1, can_add=1, can_edit=1, can_delete=1");
        $st->bind_param('ii', $arInt, $modulesModuleId);
        if (!$st->execute()) {
            // لا مفتاحَ فريدًا؟ ارجع إلى فحصٍ ثم إدراج
            $chk = $conn->query("SELECT id FROM role_permissions WHERE role_id=$arInt AND module_id=$modulesModuleId LIMIT 1");
            if ($chk && $chk->num_rows) {
                $conn->query("UPDATE role_permissions SET can_view=1, can_add=1, can_edit=1, can_delete=1
                              WHERE role_id=$arInt AND module_id=$modulesModuleId");
            } else {
                $conn->query("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                              VALUES ($arInt, $modulesModuleId, 1, 1, 1, 1)");
            }
        }
        $say("  + منحُ الدور {$ar} على #{$modulesModuleId}");
    }
}

// ═══ محرّكُ القاعدتين ═══
$modules = array();
$r = $conn->query("SELECT id, code, owner_role_id FROM modules");
while ($row = $r->fetch_assoc()) { $modules[intval($row['id'])] = $row; }

$navOf = function ($rid) use ($conn) {
    $set = array();
    $q = $conn->query("SELECT DISTINCT route FROM nav_items WHERE role_id = " . intval($rid));
    if ($q) {
        while ($row = $q->fetch_assoc()) {
            if (!empty($row['route'])) {
                $path = parse_url($row['route'], PHP_URL_PATH);
                if ($path) { $set[basename($path)] = 1; }
            }
        }
    }
    return $set;
};
$isPersonal = function ($code) use ($PERSONAL_PREFIX) {
    foreach ($PERSONAL_PREFIX as $p) {
        if (strpos($code, $p) === 0 || basename($code) === $p) { return true; }
    }
    return false;
};
$subOfNav = function ($code, $nav) use ($REDIRECT_ALIAS) {
    $bn = basename($code);
    if (isset($REDIRECT_ALIAS[$code]) && isset($nav[basename($REDIRECT_ALIAS[$code])])) { return true; }
    $stem = preg_replace('/\.php$/', '', $bn);
    foreach (array_keys($nav) as $navBn) {
        $navStem = preg_replace('/\.php$/', '', $navBn);
        if ($navStem !== '' && $stem !== $navStem && strpos($stem, $navStem) === 0) { return true; }
    }
    return false;
};

$plan = array(); // [role_id, module_id, code, rule, old(v,a,e,d), new(v,a,e,d)]
$roles = $conn->query("SELECT id FROM roles WHERE (status = '1' OR status = 1) ORDER BY id");
while ($ro = $roles->fetch_assoc()) {
    $rid = intval($ro['id']);
    $ridStr = (string) $rid;
    $nav = $navOf($rid);
    $q = $conn->query("SELECT module_id, can_view, can_add, can_edit, can_delete
                       FROM role_permissions WHERE role_id = $rid");
    while ($rp = $q->fetch_assoc()) {
        $mid = intval($rp['module_id']);
        if (!isset($modules[$mid])) { continue; }
        $code  = $modules[$mid]['code'];
        $owner = (string) intval($modules[$mid]['owner_role_id']);
        $old = array(intval($rp['can_view']), intval($rp['can_add']), intval($rp['can_edit']), intval($rp['can_delete']));

        // ق-أ · الحوكمةُ لمالكها ولإدارة الحسابات
        if (in_array($code, $GOV_SCREENS, true)) {
            if ($owner !== $ridStr && !in_array($ridStr, $ADMIN_ROLES, true)) {
                if (array_sum($old) > 0) {
                    $plan[] = array($rid, $mid, $code, 'gov', $old, array(0, 0, 0, 0));
                }
            }
            // مالكُها أو إدارةُ الحسابات: تبقى كما هي ولا تمرُّ على ق-ب — وإلا نُزعت
            // الكتابةُ لأن شاشاتِ الحوكمة ليست في قائمةٍ جانبية. قياسٌ من القاعدة
            // الحيّة: الشركةُ 1 بلا أيِّ حسابٍ بالدور 15 — فنزعُها عن الدور 1 يقفلها
            // خارج إدارة صلاحياتها إلى الأبد.
            continue;
        }

        // ق-ب · الكتابةُ تتبع الباب
        if (in_array($code, $GOV_CODE_GUARDED, true)) { continue; }
        if ($old[1] === 0 && $old[2] === 0 && $old[3] === 0) { continue; }
        if ($owner === $ridStr) { continue; }
        if (in_array($code, $OPEN_SCREENS, true)) { continue; }
        if ($isPersonal($code)) { continue; }
        if (isset($nav[basename($code)])) { continue; }
        if ($subOfNav($code, $nav)) { continue; }
        $plan[] = array($rid, $mid, $code, 'write', $old, array($old[0], 0, 0, 0));
    }
}

$govCount   = count(array_filter($plan, function ($p) { return $p[3] === 'gov'; }));
$writeCount = count($plan) - $govCount;
$say("── ② ق-أ الحوكمةُ لمالكها : {$govCount} صفًّا");
$say("── ③ ق-ب الكتابةُ تتبع الباب: {$writeCount} صفًّا");

if ($DRY) {
    foreach ($plan as $p) {
        printf("  [%s] دور %-3s #%-4s %-44s (%d%d%d%d) -> (%d%d%d%d)\n",
            $p[3], $p[0], $p[1], $p[2], $p[4][0], $p[4][1], $p[4][2], $p[4][3],
            $p[5][0], $p[5][1], $p[5][2], $p[5][3]);
    }
    $say("\n[جافّ] لم يُكتب شيء.");
    exit(0);
}

if (!count($plan)) { $say("  = لا شيءَ لتغييره — القاعدةُ مطبَّقةٌ سلفًا"); exit(0); }

$conn->begin_transaction();
$now = date('Y-m-d H:i:s');
$bk = $conn->prepare("INSERT IGNORE INTO sec_perm_backup_20260806
    (role_id, module_id, can_view, can_add, can_edit, can_delete, rule_applied, captured_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$up = $conn->prepare("UPDATE role_permissions SET can_view=?, can_add=?, can_edit=?, can_delete=?
                      WHERE role_id=? AND module_id=?");
foreach ($plan as $p) {
    list($rid, $mid, $code, $rule, $old, $new) = $p;
    $bk->bind_param('iiiiiiss', $rid, $mid, $old[0], $old[1], $old[2], $old[3], $rule, $now);
    if (!$bk->execute()) { $conn->rollback(); $fail("تعذّر النسخُ الاحتياطي لـ($rid,$mid): " . $bk->error); }
    $up->bind_param('iiiiii', $new[0], $new[1], $new[2], $new[3], $rid, $mid);
    if (!$up->execute()) { $conn->rollback(); $fail("تعذّر تحديث ($rid,$mid): " . $up->error); }
}
$conn->commit();

$say("");
$say("✓ طُبِّق " . count($plan) . " صفًّا · النسخةُ الاحتياطيةُ في sec_perm_backup_20260806");
$say("  تحقّق:  php tools/sec_perm_checks.php");
