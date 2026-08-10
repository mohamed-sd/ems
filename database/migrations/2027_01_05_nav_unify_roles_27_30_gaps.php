<?php
/**
 * سدُّ فجواتِ التنقّل قبلَ قلبِ الأدوار 27..30 إلى المصدر الموحَّد
 * ═══════════════════════════════════════════════════════════════════════════
 * قلبُ دورٍ في `EMS_NAV_UNIFIED_ROLES` يُبدّل **قائمتَه كلَّها**: قبلَه تُقرأ من
 * `modules`، وبعدَه من `nav_items`. فقاسَ `tools/nav_flag_flip_diff.php` الفرقَ
 * قبلَ القلب — فمنع القلبَ وكشف **16 مسارًا يُفقد**. هذا الترحيلُ يسدّها.
 *
 * ① **ملفُّ الخطر** (`Risk/risk_card.php` · الوحدة 323): شاشةٌ حقيقيةٌ مملوكةٌ
 *    للدور 28 ومُصرَّحٌ بها لـ29 و30 — **وبلا صفٍّ واحدٍ في `nav_items` لأيِّ
 *    دور**. أي أنها خارجُ البنية المولَّدة أصلًا، وكانت تُرى بالمسار القديم
 *    وحدَه. تُدرَج في مجموعةِ «السجل» لكلِّ دورٍ من الثلاثة.
 *
 * ② **فحصُ شروط الاستحقاق** (`Finance/entitlement_gate.php` · الوحدة 270)
 *    للدور 27: منحةٌ ماليةٌ ظاهرةٌ بالمسار القديم، وأختاها في `nav_items`
 *    سلفًا — فتُلحق بمجموعةِ أختِها `Finance/entitlement.php`.
 *
 * ③ **عشرُ شاشاتٍ ماليةٍ ممنوحةٍ للدور 28** (النسب · الإنذار المبكر · هوامش
 *    العقود · القوائم): تظهر اليومَ في مجموعةٍ **اصطناعيةٍ** يبنيها
 *    `insidebar.php` من `ems_finance_nav_links()` — وهي **حكرٌ على المسار
 *    القديم** (لذلك لا يراها اليومَ عشرون دورًا موحَّدًا لهم منحٌ مثلُها،
 *    ومنهم الدور 15 بستٍّ وثلاثين منحة). فتُثبَّت للدور 28 صفوفًا في
 *    `nav_items` تحت مجموعةٍ باسمِها نفسِه كي لا يخسر سطرًا واحدًا.
 *
 *    ◆ ولا يصير الثابتُ كذبًا إن سُحبت المنحة: `getUnifiedNavItems` يشترط
 *      `can_view = 1` على وحدةِ الصف — فالصفُّ **يختفي تلقائيًّا** بسحبِ
 *      المنحة. الفارقُ الباقي أن المنحةَ **الجديدة** لن تظهر وحدَها، وهو
 *      نقصٌ معلَنٌ لا مطويّ (وعلاجُه الجذريُّ توحيدُ المجموعتين الاصطناعيتين
 *      على المسارين — قرارٌ يمسّ 26 دورًا فلا يُدسّ في ترحيلِ أربعة).
 *
 * ◆ ما يُفقد **عمدًا** بعدَ القلب: `main/project_users.php` عن الدورين 29 و30.
 *   كانا يرثانه من أبيهما 28 بحكمِ المسار القديم (يورّث بلا فحصِ صلاحية)،
 *   وحكمُ المالك أن **الدورَ الرئيسيَّ وحدَه يضيف معاونين**. فالقلبُ يُطابق
 *   القائمةَ بالحكم. وتُترك لهما منحةُ العرض على الوحدة 14 (بلا إضافةٍ ولا
 *   تعديلٍ ولا حذف) شبكةَ أمانٍ لو رُجع عن العلَم يومًا فعاد الرابطُ يورَّث.
 *
 * ◆ العلَمُ نفسُه يُقلب في `.env` (و`.env.example`) لا هنا — الترحيلُ لا يمسّ
 *   ملفَّ بيئةٍ خارجَ القاعدة، والرجوعُ حذفُ الأرقام بلا نشرِ كود.
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

/** يُدرج صفَّ تنقّلٍ إن لم يكن — ويعيد ضبطَه إن كان (آمنُ الإعادة). */
$putNav = function ($roleId, $groupId, $moduleId, $label, $route, $icon, $sort) use ($conn, $log) {
    $st = $conn->prepare("SELECT id FROM nav_items WHERE role_id = ? AND route = ? LIMIT 1");
    $st->bind_param('is', $roleId, $route);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if ($row) {
        $st = $conn->prepare("UPDATE nav_items SET group_id=?, module_id=?, label_ar=?, icon=?, sort_order=?,
                                     permission_code=?, active=1, updated_at=NOW() WHERE id=?");
        $st->bind_param('iississ', $groupId, $moduleId, $label, $icon, $sort, $route, $row['id']);
        $st->execute(); $st->close();
        return 'ضُبط';
    }
    $st = $conn->prepare("INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route, icon,
                                 sort_order, permission_code, active, created_at, updated_at)
                          VALUES (?, 'REC', ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())");
    $st->bind_param('iiisssis', $roleId, $groupId, $moduleId, $label, $route, $icon, $sort, $route);
    $st->execute(); $st->close();
    return 'أُدرج';
};

/* ═══ ① ملفُّ الخطر لأدوار المخاطر الثلاثة ═══════════════════════════════ */
$RISK_CARD_MODULE = 323;
foreach (array(28, 29, 30) as $role) {
    // مجموعةُ «السجل» لهذا الدور = مجموعةُ سجلِّ المخاطر المركزي
    $gid = 0; $sort = 90;
    $r = $conn->query("SELECT n.group_id, MAX(n2.sort_order) mx
                         FROM nav_items n
                         JOIN nav_items n2 ON n2.group_id = n.group_id
                        WHERE n.role_id = $role AND n.route LIKE '%risk_register%'
                        GROUP BY n.group_id LIMIT 1");
    if ($r && ($x = $r->fetch_assoc())) { $gid = intval($x['group_id']); $sort = intval($x['mx']) + 1; }
    if ($gid === 0) { $log("دور $role: لا مجموعةَ سجلٍّ — تُخطّى ملفُّ الخطر"); continue; }
    $act = $putNav($role, $gid, $RISK_CARD_MODULE, 'ملف الخطر', 'Risk/risk_card.php', 'fa fa-file-shield', $sort);
    $log("دور $role: «ملف الخطر» $act في المجموعة #$gid");
}

/* ═══ ② فحصُ شروط الاستحقاق للدور 27 ══════════════════════════════════ */
$gid = 0; $sort = 90;
$r = $conn->query("SELECT n.group_id, MAX(n2.sort_order) mx
                     FROM nav_items n JOIN nav_items n2 ON n2.group_id = n.group_id
                    WHERE n.role_id = 27 AND n.route = 'Finance/entitlement.php'
                    GROUP BY n.group_id LIMIT 1");
if ($r && ($x = $r->fetch_assoc())) { $gid = intval($x['group_id']); $sort = intval($x['mx']) + 1; }
if ($gid > 0) {
    $act = $putNav(27, $gid, 270, 'فحص شروط الاستحقاق', 'Finance/entitlement_gate.php', 'fa fa-clipboard-check', $sort);
    $log("دور 27: «فحص شروط الاستحقاق» $act في المجموعة #$gid");
} else {
    $log('دور 27: لم تُحلّ مجموعةُ الاستحقاق — راجع يدويًّا');
}

/* ═══ ③ الشاشاتُ الماليةُ الممنوحةُ للدور 28 ═══════════════════════════ */
$gcode = 'n9o_finshared_r28';
$gid = 0;
$st = $conn->prepare("SELECT id FROM link_groups WHERE group_code = ? LIMIT 1");
$st->bind_param('s', $gcode); $st->execute();
if ($x = $st->get_result()->fetch_assoc()) { $gid = intval($x['id']); }
$st->close();
if ($gid === 0) {
    /* المرحلة 97 — قبلَ «فريق العمل» (98) مباشرةً فتبقى الأخيرةَ كما قرّر المالك */
    $conn->query("INSERT INTO link_groups (name, group_code, owner_role_id, icon, display_order, stage_no, stage_title, is_active)
                  VALUES ('شاشات مالية', '$gcode', 28, 'fa fa-coins', 9700, 97, 'شاشات مالية', 1)");
    $gid = intval($conn->insert_id);
    $log("دور 28: مجموعةُ «شاشات مالية» أُنشئت #$gid (مرحلة 97 — قبلَ فريق العمل)");
} else {
    $log("دور 28: مجموعةُ «شاشات مالية» قائمة #$gid");
}

$n = 0; $sort = 10;
$r = $conn->query("SELECT m.id, m.code, m.name, COALESCE(NULLIF(TRIM(m.icon),''),'fa fa-coins') icon
                     FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                    WHERE rp.role_id = 28 AND rp.can_view = 1
                      AND m.code LIKE 'Finance/%' AND m.is_link = '1'
                    ORDER BY m.display_order, m.id");
while ($m = $r->fetch_assoc()) {
    $putNav(28, $gid, intval($m['id']), (string) $m['name'], (string) $m['code'], (string) $m['icon'], $sort);
    $sort += 10; $n++;
}
$log("دور 28: $n شاشةً ماليةً ممنوحةً ثُبِّتت صفوفًا (وتختفي تلقائيًّا بسحبِ منحتِها)");

echo "\n";
$log('سُدَّت الفجوات — شغّل tools/nav_flag_flip_diff.php 27 28 29 30 قبلَ قلبِ العلَم');
echo "\n";
