<?php
/**
 * 2027_01_24_fix_inj0059_nav_orphan_doors.php
 * ═══════════════════════════════════════════════════════════════════════════
 * INJ-0059 (P1) — «صفوفُ تنقُّلٍ نشطةٌ لا تُصيَّر إطلاقًا».
 *
 * ◆ العطل: حلقةُ `renderUnifiedNavigationV2` تمرُّ على **الأبوابِ المعرَّفةِ في
 *   `unifiedNavDoors()` وحدَها**، فصفٌّ بابُه خارجَها يسقط بلا خطأٍ ولا أثر.
 *   وينجو منه ما له `stage_no` لأنه يُصيَّر بالمسارِ المرحليِّ قبلَ حلقةِ
 *   الأبواب — فالنجاةُ صدفةُ إعدادٍ لا قاعدة.
 *   القياس: 14 صفًّا في باب `main` (الأدوار 9 · 17 · 18 · 21) شاشاتُها قائمةٌ
 *   على القرصِ وغيرُ قابلةٍ للوصولِ من أيِّ قائمة.
 *
 * ◆ القرار (مرجعيتُه **النظامُ القائم** لا الاجتهاد): للنظامِ عرفٌ مستقرٌّ
 *   لهذه الحالةِ بعينها — مجموعةُ «المرحلة 99 · خارج الوثيقة — بانتظار قرار
 *   المالك» بالرمز `n9s99_others_rNN`، قائمةٌ في **24 دورًا** وتحمل 34 صفًّا.
 *   فتُلحَق الأربعةَ عشرَ بها: تظهر للمستخدمِ فورًا، ويبقى إعلانُ «بانتظار
 *   قرارٍ» صادقًا — ولا يُدَّعى لها موضعٌ نهائيٌّ لم يقرّره أحد.
 *   ◆ ولا حذفَ: كلُّ تصحيحٍ هنا تحديثٌ أو إضافة.
 *
 * ◆ وباب `RISK` (80 صفًّا في 18 دورًا) يُسجَّل تاسعًا في `unifiedNavDoors()`
 *   — تعديلٌ في الكود لا في القاعدة. كلُّ صفوفِه لها مرحلةٌ فتُصيَّر اليوم،
 *   والتسجيلُ يجعل عقدَ المُصيِّرِ مطابقًا للواقعِ فلا يسقط صفٌّ منها صامتًا
 *   إن فقد مرحلتَه يومًا.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال المرحِّل فشل: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');
$fail = function ($m) { fwrite(STDERR, "✘ {$m}\n"); exit(1); };

/* ── ① مجموعةُ المرحلة 99 لكلِّ دورٍ معنيٍّ — تُنشأ إن غابت ─────────────
   بالعرفِ الحرفيِّ للأربعةِ والعشرين القائمة: الاسمُ والرمزُ والأيقونةُ
   والترتيبُ وعنوانُ المرحلة. الانحرافُ عن العرفِ يُخرج المجموعةَ من مسحِ
   `nav09_verify` أو يجعل `sweep_others` يكنسها. */
$roles = array(9, 17, 18, 21);
$gid   = array();
foreach ($roles as $rid) {
    $code = 'n9s99_others_r' . $rid;
    $row  = $db->query("SELECT id FROM link_groups WHERE group_code='{$code}'")->fetch_assoc();
    if ($row) { $gid[$rid] = (int) $row['id']; continue; }
    $ok = $db->query("INSERT INTO link_groups
        (name, group_code, owner_role_id, icon, display_order, stage_no, stage_title, is_active)
        VALUES ('أخرى — للمراجعة', '{$code}', {$rid}, 'fa fa-box-archive', 9900, 99,
                'خارج الوثيقة — بانتظار قرار المالك', 1)");
    if (!$ok) { $fail("إنشاءُ مجموعةِ الدور {$rid} فشل: " . $db->error); }
    $gid[$rid] = (int) $db->insert_id;
    echo "① أُنشئت مجموعةُ الدور {$rid}: #{$gid[$rid]} ({$code})\n";
}
foreach ($roles as $rid) { echo "   الدور {$rid} → مجموعة #{$gid[$rid]}\n"; }

/* ── ② إلحاقُ الصفوفِ اليتيمة — تحديثٌ لا حذف ─────────────────────────── */
$db->query('START TRANSACTION');
$moved = 0;
foreach ($roles as $rid) {
    $st = $db->prepare("UPDATE nav_items SET door='DAILY', group_id=?, updated_at=NOW()
                         WHERE role_id=? AND door='main'");
    if (!$st) { $db->query('ROLLBACK'); $fail('تحضيرُ التحديثِ فشل: ' . $db->error); }
    $st->bind_param('ii', $gid[$rid], $rid);
    if (!$st->execute()) { $st->close(); $db->query('ROLLBACK'); $fail('التحديثُ فشل: ' . $st->error); }
    $moved += $st->affected_rows;
    $st->close();
}
$db->query('COMMIT');
echo "② أُلحق {$moved} صفًّا بمجموعاتِ «بانتظار قرار المالك»\n";

/* ── ③ لا يبقى بابٌ خارجَ المعرَّف — والاستثناءُ الوحيدُ RISK يُسجَّل بالكود ─ */
$left = $db->query("SELECT door, COUNT(*) n FROM nav_items
                     WHERE active=1
                       AND door NOT IN ('HOME','DAILY','APPR','REC','REP','GOV','FIN','SET','RISK')
                     GROUP BY door");
$bad = array();
while ($x = $left->fetch_assoc()) { $bad[] = $x['door'] . '×' . $x['n']; }
if ($bad) { $fail('أبوابٌ باقيةٌ خارجَ المعرَّف: ' . implode(' · ', $bad)); }
echo "③ صفرُ صفٍّ نشطٍ ببابٍ خارجَ التسعةِ المعرَّفة\n";

/* ── ④ إثباتُ التسجيلِ في الكود — الترحيلُ يفحص شريكَه ─────────────────
   بابٌ في القاعدةِ بلا تسجيلٍ في `unifiedNavDoors()` يسقط صامتًا. فيُتحقَّق
   من الشريكِ هنا لا يُفترَض: الترحيلُ الذي يعتمد على تعديلٍ برمجيٍّ ولا يفحصه
   يُعلن نجاحًا كاذبًا. */
$navSrc = (string) @file_get_contents(dirname(__DIR__, 2) . '/includes/unified_nav.php');
if (strpos($navSrc, "'RISK'") === false) {
    $fail("باب RISK غيرُ مسجَّلٍ في unifiedNavDoors() — سجّلْه قبلَ هذا الترحيل");
}
echo "④ باب RISK مسجَّلٌ في unifiedNavDoors()\n";

echo "\n✔ 2027_01_24 تمّ. الشاهد: php tools/fix_nav_href_probe.php\n";
