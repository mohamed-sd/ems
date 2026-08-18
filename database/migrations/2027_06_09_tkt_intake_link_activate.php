<?php
/**
 * 2027_06_09_tkt_intake_link_activate.php
 * ═══════════════════════════════════════════════════════════════════════════
 * شاشةُ الفرزِ التي تُخرج البلاغَ من «سُجّل» — كانت مبنيّةً وممنوحةً ومُعطَّلةً
 * ───────────────────────────────────────────────────────────────────────────
 * بلاغُ المالك: «أنشأتُ بلاغًا فبقي في مرحلةِ سُجّل، ولا أعرف من أين أُحوّله
 * إلى وُجّه — ولا أجد الصفحةَ ولا الزر».
 *
 * والمقيسُ يفسِّرُ البلاغَ حرفًا:
 *   ① مسارا الإنشاءِ يبدآن من مرحلتين مختلفتين: الاستمارةُ اليدويةُ تُنشئ
 *      بـ`routed` مباشرةً، و`TicketRouter` (الفتحُ السياقيُّ المتاحُ لـ26 دورًا ·
 *      كسرُ الزجاج · التفتيش) يُنشئ بـ`new`. فبلاغُ الميدانِ يقف عند الفرز.
 *   ② والمخرجُ الوحيدُ من `new` شاشةُ `Tickets/intake_classify.php` — **مبنيّةٌ
 *      ومسجَّلةٌ وممنوحةٌ للدور 24** — لكنَّ رابطَها في القائمة `active=0`،
 *      وموضعُها سلّةٌ اسمُها «أخرى — للمراجعة» (مرحلة 99). فلا يجدها أحد.
 *   ③ والمرحلةُ المسمّاةُ «التوجيه الآلي» (مجموعة 3815 · مرحلة 2) بندُها الوحيدُ
 *      `ticket_types_config.php` — وهي شاشةُ **إعدادِ** خريطةِ التوجيه لا شاشةُ
 *      توجيهٍ. ففتحها المالكُ باحثًا عن أزرارِ إجراءٍ فلم يجدها — بحقّ.
 *   ④ والنتيجةُ تراكمٌ مقيس: **28 بلاغًا عالقًا في «سُجّل»** أقدمُها 2026-08-02،
 *      و**60 صفًّا** ينتظر في شاشةِ الفرزِ التي لا يصلها أحد.
 *
 * ◆ الحكم: يُفعَّل الرابطُ ويُنقَل إلى مجموعةِ «التوجيه الآلي» — بجانبِ إعدادِ
 *   الخريطة، حيث بحث عنه المالكُ فعلًا. ويسبقها في الترتيب: **الفرزُ عملٌ يوميٌّ
 *   والإعدادُ يُضبط مرةً**.
 * ◆ ويُفعَّل معه `dept_inbox.php` («بلاغاتُ إدارتي»): ظاهرٌ لثلاثين دورًا آخر
 *   ومُعطَّلٌ للدور 24 وحدَه — تناقضٌ لا قرار.
 * ◆ ولا يُمَسُّ `inquiry.php` الثالثُ المُعطَّل في السلّةِ نفسِها: تفعيلُه قرارُ
 *   منتَجٍ لا إصلاحُ عطل، فيُترك للمالك.
 * ◆ ولا تُمنح صلاحيةٌ جديدةٌ هنا: الوحدتان ممنوحتان سلفًا — العطلُ في الرابطِ
 *   لا في المنحة.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$one = function (string $s) use ($conn) { $r = $conn->query($s); return $r ? ($r->fetch_row()[0] ?? null) : null; };

echo "\n▐ ① الحالُ قبل\n";
$r = $conn->query("SELECT n.id, n.label_ar, n.route, n.active, g.name gname, g.stage_no
                     FROM nav_items n JOIN link_groups g ON g.id = n.group_id
                    WHERE n.role_id = 24 AND n.route IN
                          ('Tickets/intake_classify.php','Tickets/dept_inbox.php','Tickets/inquiry.php')");
while ($x = $r->fetch_assoc()) {
    printf("   %-9s %-28s → «%s» (مرحلة %s)\n",
        $x['active'] ? '[ظاهر]' : '[معطَّل]', $x['label_ar'], $x['gname'], $x['stage_no']);
}
printf("   · بلاغاتٌ عالقةٌ في «سُجّل» (كلُّ الشركات): %s\n", $one("SELECT COUNT(*) FROM tickets WHERE stage = 'new'"));

/* مجموعةُ «التوجيه الآلي» للدور 24 — تُحَلُّ بالاسمِ والمالكِ لا برقمٍ مثبَّت،
   فأرقامُ المجموعاتِ تختلف بين البيئات. */
$gid = (int) $one("SELECT g.id FROM link_groups g
                    WHERE g.owner_role_id = 24 AND g.name = 'التوجيه الآلي' AND g.is_active = 1
                    ORDER BY g.id LIMIT 1");

echo "\n▐ ② تفعيلُ شاشةِ الفرزِ ونقلُها إلى «التوجيه الآلي»\n";
if ($gid <= 0) {
    echo "   ✗ لم تُعثر مجموعةُ «التوجيه الآلي» للدور 24 — يُفعَّل الرابطُ في موضعِه دون نقل\n";
    $conn->query("UPDATE `nav_items` SET `active` = 1
                   WHERE `role_id` = 24 AND `route` = 'Tickets/intake_classify.php'");
    printf("   ✔ فُعِّل %d رابطًا (بلا نقل)\n", $conn->affected_rows);
} else {
    /* sort_order = 10 ليسبق إعدادَ الأنواع (50): الفرزُ يوميٌّ والإعدادُ مرةً. */
    $conn->query("UPDATE `nav_items`
                     SET `active` = 1, `group_id` = $gid, `sort_order` = 10,
                         `label_ar` = 'الاستقبال والتصنيف — توجيه البلاغات الجديدة'
                   WHERE `role_id` = 24 AND `route` = 'Tickets/intake_classify.php'");
    if ($conn->errno) { echo '   ✗ ' . $conn->error . "\n"; }
    else { printf("   ✔ فُعِّل ونُقل %d رابطًا إلى المجموعة %d\n", $conn->affected_rows, $gid); }

    /* وإعدادُ الأنواعِ يُؤخَّر بعده — البندُ الأولُ في المرحلةِ هو عملُها اليوميّ. */
    $conn->query("UPDATE `nav_items` SET `sort_order` = 50
                   WHERE `role_id` = 24 AND `group_id` = $gid
                     AND `route` LIKE 'Tickets/ticket_types_config.php%'");
}

echo "\n▐ ③ «بلاغاتُ إدارتي» — مُعطَّلٌ للدور 24 وحدَه من بين الأدوارِ الثلاثين\n";
$others = (int) $one("SELECT COUNT(DISTINCT role_id) FROM `nav_items`
                       WHERE `route` = 'Tickets/dept_inbox.php' AND `active` = 1");
printf("   · أدوارٌ يظهر لها البندُ الآن: %d\n", $others);
$conn->query("UPDATE `nav_items` SET `active` = 1
               WHERE `role_id` = 24 AND `route` = 'Tickets/dept_inbox.php'");
printf("   ✔ فُعِّل %d رابطًا للدور 24\n", $conn->affected_rows);

echo "\n▐ ④ التحقُّق\n";
$rows = $conn->query("SELECT n.label_ar, n.route, n.active, n.sort_order, g.name gname, g.stage_no
                        FROM nav_items n JOIN link_groups g ON g.id = n.group_id
                       WHERE n.role_id = 24 AND n.route IN
                             ('Tickets/intake_classify.php','Tickets/dept_inbox.php')
                       ORDER BY g.stage_no, n.sort_order");
$ok = true;
while ($x = $rows->fetch_assoc()) {
    if (!$x['active']) { $ok = false; }
    printf("   %-9s %-46s «%s» (مرحلة %s · ترتيب %s)\n",
        $x['active'] ? '[ظاهر]' : '[معطَّل]', $x['label_ar'], $x['gname'], $x['stage_no'], $x['sort_order']);
}
if ($gid > 0) {
    echo "   · ترتيبُ مجموعةِ «التوجيه الآلي»:\n";
    $rows = $conn->query("SELECT n.label_ar, n.sort_order FROM nav_items n
                           WHERE n.group_id = $gid AND n.active = 1 ORDER BY n.sort_order, n.id");
    while ($x = $rows->fetch_assoc()) { printf("      %-3s %s\n", $x['sort_order'], $x['label_ar']); }
}
printf("\n%s\n\n", $ok ? '   ✔ مخرجُ «سُجّل» صار في القائمة — والفرزُ يسبق الإعداد.'
                       : '   ✗ لم يكتملْ — راجعْ ما سبق.');
