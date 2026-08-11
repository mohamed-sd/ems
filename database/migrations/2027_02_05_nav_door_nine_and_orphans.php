<?php
/**
 * 2027_02_05 — بابٌ تاسعٌ في القيد · وعشرةُ عناصرَ بلا باب
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مسألتان مقيستان، لا قرارَ اجتهاديٍّ في أيٍّ منهما:**
 *
 * ① **`chk_nav_door` متعفِّنٌ لا القاعدةُ مخالفة.** القيدُ المفقودُ (فُقد
 *    2026-08-03) يحصر البابَ في **ثمانية**. وقاموسُ الشيفرةِ
 *    (`includes/unified_nav.php::unifiedNavDoors`) يحمل **تسعةً**: أُضيف
 *    `RISK` يومَ 2026-08-10 تحت `INJ-0059`، وفي تعليقِه رفضٌ صريحٌ لطيِّه تحت
 *    `GOV` («بابُ الحوكمةِ مزدحمٌ ودفنُ المخاطرِ فيه يُخفي مجالًا أولَ الدرجة»).
 *    والواقعُ يوافق الشيفرةَ: **80 صفًّا في 18 دورًا** بباب `RISK`.
 *    ⇒ فالقيدُ يُرمَّم **بتسعةٍ**. وترميمُه بثمانيةٍ كان سيرفض 80 صفًّا صحيحًا،
 *      أو يُغري بمحوِ بابٍ حيٍّ لإرضاءِ نصٍّ متجاوَز.
 *
 * ② **عشرةُ عناصرَ بـ`door = ''`** — وعنصرٌ بلا بابٍ لا موضعَ له في القائمة.
 *    والبابُ الصحيحُ **مقيسٌ من سابقةٍ لا مُختار**: القاعدةُ في هذا النظامِ أن
 *    العنصرَ **يورث بابَ مجموعتِه** (مجموعتا 3856-3859 و3536 فيهما أشقّاءُ
 *    `DAILY` صريحون).
 *    · ستةٌ منها `Approvals/requests.php` — **من صنعي** في هجرةِ `INJ-0219`:
 *      أدرجتُ بابَ الشاشةِ ونسيتُ عمودَ البابِ نفسَه.
 *    · وواحدٌ `Governance/gov_dept.php` للدور 3 في مجموعةٍ `DAILY`.
 *    · وأربعةٌ في مجموعةِ الدور 9 (3936) **بلا شقيقٍ ذي باب** — وهي بعينها
 *      `INJ-0128` في تسليمِ 2026-08-10: «4 شاشاتِ تقاريرَ **ممنوحةٌ بلا باب**».
 *      فالمنحةُ وقعت والموضعُ لم يوضع — ذيلٌ لبندٍ أُعلن مُغلقًا.
 *      وبابُها `REP` («التقارير والتحليلات») — اسمُ البابِ يطابق ما هي عليه،
 *      وسابقتا المسارِ (`GOV` لـiaf · `RISK` لـrisk) تخصّان أدوارًا أخرى
 *      تنتمي فيها الشاشةُ إلى مجالِها لا إلى تقاريرِ مكتبِ الرئيس.
 *
 * ◆ ولا يُلمَس صفٌّ صحيح: التحديثُ مشروطٌ بـ`door = '' OR door IS NULL` حصرًا.
 * ◆ مُتحمِّلٌ للتكرار · ويُجَسُّ القيدُ في الاتجاهين قبل إعلانِ النجاح.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

$DOORS = array('HOME', 'DAILY', 'APPR', 'REC', 'REP', 'SET', 'GOV', 'FIN', 'RISK');
$LIST  = "'" . implode("','", $DOORS) . "'";

echo "══ nav_items: بابٌ تاسعٌ · وعناصرُ بلا باب ══\n";

/* ── ① العناصرُ بلا باب: كلٌّ ببابِ سابقتِه ─────────────────────────────── */
echo "\n── ① إسنادُ العناصرِ بلا باب\n";
$orphans = (int) $db->query("SELECT COUNT(*) FROM nav_items WHERE door IS NULL OR door = ''")
                    ->fetch_row()[0];
echo "  عناصرُ بلا باب: {$orphans}\n";

/* أ) وريثُ بابِ مجموعتِه — القاعدةُ المقيسة */
$db->query("UPDATE nav_items n
             JOIN (SELECT group_id, MIN(door) AS d FROM nav_items
                    WHERE door IS NOT NULL AND door <> '' GROUP BY group_id) g
               ON g.group_id = n.group_id
              SET n.door = g.d
            WHERE (n.door IS NULL OR n.door = '') AND g.d IS NOT NULL AND g.d <> ''");
$byGroup = $db->affected_rows;
echo "  ✔ ورِث بابَ مجموعتِه: {$byGroup}\n";

/* ب) مجموعةُ تقاريرِ الدور 9 (INJ-0128) — بلا شقيقٍ ذي بابٍ فالبابُ REP */
$db->query("UPDATE nav_items SET door = 'REP'
             WHERE (door IS NULL OR door = '')
               AND route IN ('Portal/ceo_reports.php','Governance/gov_reports.php',
                             'Audit/iaf_reports.php','Risk/risk_reports.php')");
$byReports = $db->affected_rows;
echo "  ✔ تقاريرُ الدور 9 ⇒ `REP` (ذيلُ INJ-0128): {$byReports}\n";

/* ج) صندوقُ الموافقاتِ الباقي — شقيقُه في مجموعةٍ أخرى DAILY، فيُوحَّد معه */
$db->query("UPDATE nav_items SET door = 'DAILY'
             WHERE (door IS NULL OR door = '') AND route = 'Approvals/requests.php'");
$byInbox = $db->affected_rows;
echo "  ✔ صندوقُ الموافقاتِ الباقي ⇒ `DAILY`: {$byInbox}\n";

$left = (int) $db->query("SELECT COUNT(*) FROM nav_items WHERE door IS NULL OR door = ''")
                 ->fetch_row()[0];
echo '  ' . ($left === 0 ? '✔ لا عنصرَ بلا بابٍ بقي' : "⚠ بقي {$left} — يُعلَن") . "\n";
if ($left > 0) {
    $r = $db->query("SELECT id, role_id, route FROM nav_items WHERE door IS NULL OR door = ''");
    while ($x = $r->fetch_assoc()) { echo '    ⚠ #' . $x['id'] . ' د' . $x['role_id'] . ' ' . $x['route'] . "\n"; }
}

/* ── ② القيدُ بتسعةِ أبواب ───────────────────────────────────────────────── */
echo "\n── ② القيدُ بتسعةِ أبواب\n";
$has = (int) $db->query("SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
                          WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='nav_items'
                            AND CONSTRAINT_NAME='chk_nav_door'")->fetch_row()[0];
if ($has) {
    echo "  ○ قائمٌ سلفًا\n";
} else {
    $bad = (int) $db->query("SELECT COUNT(*) FROM nav_items WHERE NOT (door IN ({$LIST}))")
                    ->fetch_row()[0];
    echo "  مخالفٌ للتسعة: {$bad}\n";
    if ($bad > 0) {
        $r = $db->query("SELECT DISTINCT door FROM nav_items WHERE NOT (door IN ({$LIST}))");
        while ($x = $r->fetch_row()) { echo '    ⚠ بابٌ غيرُ معروف: «' . $x[0] . "»\n"; }
        echo "  ⚠ لا يُضاف القيدُ — والمخالفُ يُعلَن لا يُمحى\n";
    } else {
        if ($db->query("ALTER TABLE nav_items ADD CONSTRAINT chk_nav_door
                          CHECK (door IN ({$LIST}))") === false) {
            fwrite(STDERR, '✘ ' . $db->error . "\n"); exit(1);
        }
        echo "  ✔ أُضيف `chk_nav_door` بتسعةِ أبواب\n";
    }
}

/* ── ③ الجسُّ في الاتجاهين ───────────────────────────────────────────────── */
echo "\n── ③ جسٌّ (ثم تراجُع)\n";
$db->begin_transaction();
$id = (int) $db->query('SELECT id FROM nav_items LIMIT 1')->fetch_row()[0];
$b1 = $db->query("UPDATE nav_items SET door = 'ZZZZ' WHERE id = {$id}");
echo '  ' . ($b1 === false ? '✔ بابٌ مخترَعٌ مرفوض — ' . mb_substr($db->error, 0, 52)
                           : '✘ **مرَّ بابٌ مخترَع**') . "\n";
$b2 = $db->query("UPDATE nav_items SET door = 'RISK' WHERE id = {$id}");
echo '  ' . ($b2 !== false ? '✔ و`RISK` يمرُّ — البابُ التاسعُ مشروع'
                           : '✘ رُفض RISK — القيدُ ما زال ثمانيةً') . "\n";
$b3 = $db->query("UPDATE nav_items SET door = '' WHERE id = {$id}");
echo '  ' . ($b3 === false ? '✔ وبابٌ فارغٌ مرفوض — لا عنصرَ بلا موضع'
                           : '✘ **مرَّ بابٌ فارغ**') . "\n";
$db->rollback();
echo "  ○ تُراجِع الجسُّ\n";

/* ── الحصيلة ─────────────────────────────────────────────────────────────── */
$r = $db->query("SELECT door, COUNT(*) c FROM nav_items GROUP BY door ORDER BY c DESC");
echo "\n── توزيعُ الأبوابِ بعد الترميم\n";
while ($x = $r->fetch_assoc()) { echo '  ' . str_pad($x['door'], 10) . $x['c'] . "\n"; }

$ok = ($left === 0) && ($b1 === false) && ($b2 !== false) && ($b3 === false);
echo "\n" . ($ok ? "✅ تسعةُ أبوابٍ محروسةٌ · وصفرُ عنصرٍ بلا موضع.\n" : "⚠ راجِع أعلاه\n");
exit($ok ? 0 : 1);
