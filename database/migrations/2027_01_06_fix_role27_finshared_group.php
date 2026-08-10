<?php
/**
 * تصحيح: نقلُ «فحص شروط الاستحقاق» من مجموعةِ الوثيقة إلى مجموعةِ المالك (دور 27)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ الخطأ: ترحيلُ 2027_01_05 أدرج `Finance/entitlement_gate.php` للدور 27 في
 *   المجموعة `n9s10_5_18_r27` — **وهي مجموعةٌ مولَّدةٌ من وثيقة NAV-09**.
 *   و`tools/nav09_verify.php` يقارن كلَّ مجموعةٍ ببادئة `n9s` بالوثيقةِ **صفًّا
 *   صفًّا بلا زيادةٍ ولا نقصان**، فأعطى فورًا:
 *       ✘ «القوى التشغيلية» دور 27: العدد 37 ≠ المتوقع 36
 *
 * ◆ وهو نقضٌ للقاعدة التي كتبتُها في الترحيلين قبله: **ما ليس في الوثيقة لا
 *   يُدسّ في مجموعةِ الوثيقة**. الشاشةُ منحةٌ ماليةٌ (`role_permissions`) لا
 *   بندٌ في ورقةِ الإدارة — فبيتُها مجموعةُ مالكٍ ببادئة `n9o_`.
 *
 * ◆ والعلاجُ هو نفسُه الذي طُبِّق على الدور 28: مجموعةُ «شاشات مالية»
 *   (المرحلة 97 — قبلَ «فريق العمل» 98 مباشرةً فتبقى الأخيرةَ كما قرّر المالك).
 *
 * ◆ الدرسُ المُثبَت: البوابةُ التي تقارن بالوثيقةِ صفًّا صفًّا هي التي أمسكت
 *   هذا — لا مراجعةُ الكود ولا تشغيلُ الشاشة. وكِلا الشاشتين كانت تُصيَّر
 *   سليمةً والعدُّ وحدَه يكذب.
 *
 * idempotent.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "اتصال المرحِّل فشل: " . $conn->connect_error . "\n"); exit(1); }
$conn->set_charset('utf8mb4');
$log = function ($m) { echo "  $m\n"; };

/* مجموعةُ «شاشات مالية» للدور 27 — نظيرةُ n9o_finshared_r28 */
$gcode = 'n9o_finshared_r27';
$gid = 0;
$st = $conn->prepare("SELECT id FROM link_groups WHERE group_code = ? LIMIT 1");
$st->bind_param('s', $gcode); $st->execute();
if ($x = $st->get_result()->fetch_assoc()) { $gid = intval($x['id']); }
$st->close();

if ($gid === 0) {
    $conn->query("INSERT INTO link_groups (name, group_code, owner_role_id, icon, display_order, stage_no, stage_title, is_active)
                  VALUES ('شاشات مالية', '$gcode', 27, 'fa fa-coins', 9700, 97, 'شاشات مالية', 1)");
    $gid = intval($conn->insert_id);
    $log("مجموعةُ «شاشات مالية» للدور 27 أُنشئت #$gid (مرحلة 97)");
} else {
    $log("مجموعةُ «شاشات مالية» للدور 27 قائمة #$gid");
}

/* نقلُ الصفِّ من مجموعةِ الوثيقة إلى مجموعةِ المالك */
$st = $conn->prepare("UPDATE nav_items SET group_id = ?, sort_order = 10, updated_at = NOW()
                       WHERE role_id = 27 AND route = 'Finance/entitlement_gate.php'");
$st->bind_param('i', $gid);
$st->execute();
$moved = $st->affected_rows;
$st->close();
$log("صفوفٌ نُقلت: $moved");

/* شاهدُ الإغلاق: عددُ صفوفِ الدور 27 داخلَ مجموعات الوثيقة (n9s غير «أخرى») */
$r = $conn->query("SELECT COUNT(*) c FROM nav_items ni JOIN link_groups lg ON lg.id = ni.group_id
                    WHERE ni.role_id = 27 AND ni.active = 1
                      AND lg.group_code LIKE 'n9s%' AND lg.group_code NOT LIKE 'n9s99_others%'");
$c = intval($r->fetch_assoc()['c']);
$log("صفوفُ الدور 27 داخلَ مجموعات الوثيقة الآن: $c (المتوقَّع 36)");

echo "\n";
$log('شغّل tools/nav09_verify.php للتأكيد');
echo "\n";
