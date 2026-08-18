<?php
/**
 * 2027_06_08_tkt_shared_screens_grants.php
 * ═══════════════════════════════════════════════════════════════════════════
 * شاشتا البلاغاتِ المشترَكتان — الحارسُ قائمٌ والمنحةُ غائبة
 * ───────────────────────────────────────────────────────────────────────────
 * البلاغُ الحيّ: «دخلتُ مديرَ تشغيلٍ ففتحتُ البلاغاتِ فقيل: لا توجد صلاحيةُ
 * عرضِ قائمة البلاغات». والمقيسُ يطابق البلاغَ حرفًا: `Tickets/tickets_list.php`
 * (الوحدة 132) لها صفُّ منحةٍ **واحدٌ** — للدور 24 وحدَه. فأربعةٌ وثلاثون دورًا
 * من خمسةٍ وثلاثين تُرَدُّ ٤٠٣ عن شاشةٍ صُمِّمت لتصلَهم جميعًا.
 *
 * ◆ ولماذا هو تناقضٌ لا تشديد: الشاشةُ **معفاةٌ نصًّا** من الحارسِ المركزي
 *   (`enforce_current_page_view_permission` — «بنفس نمط المراسلات: أيُّ مستخدمٍ
 *   مسجّلٍ يصلهما ليُبلّغ ويتابع»)، وأيقونتُها في الشريطِ العلويِّ تُعرض لكلِّ
 *   مستخدمٍ بتعليقٍ صريح: «بلا فحصِ صلاحيةِ موديول»، وبطاقاتُ لوحةِ الدورِ
 *   تحيل إليها، و`main/global_search.php` يُدرجها في المعفاة. ثم جاء INJ-0521
 *   فوضع فحصَ المنحةِ **داخلَ** الشاشةِ ولم تُبذر المنحُ — فصار البابُ مقفولًا
 *   على من لا مفتاحَ له، وكلُّ اللافتاتِ تدلُّ عليه.
 *
 * ◆ والحكمُ المختار: **تُبذر المنحةُ ولا يُرفع الحارس.** رفعُه يعيد الحالَ إلى
 *   «لا فحصَ إطلاقًا»، وبذرُها يُبقي الفشلَ مغلقًا لأيِّ دورٍ يُستحدَث غدًا،
 *   ويُمكّن مديرَ الصلاحياتِ من سحبِ الشاشةِ من دورٍ بعينِه — وهو ما كان
 *   مستحيلًا حين لا صفَّ أصلًا.
 *
 * ◆ والنطاقُ داخلَ الشاشةِ لا يتغير: `tkt_visible_owner_role_ids()` يقصر كلَّ
 *   دورٍ على بلاغاتِ شجرتِه وما أبلغ عنه هو (tickets_list.php §58-63)، ومديرُ
 *   البلاغاتِ وحدَه يرى كلَّ بلاغاتِ شركتِه. فالمنحةُ تفتح البابَ لا الخزنة.
 *
 * ◆ والقدوةُ محسوبةٌ لا مخترَعة: الوحدةُ 476 (`my_tickets.php`) — الشاشةُ
 *   الأخرى التي يصلها كلُّ مستخدم — مبذورةٌ للأدوارِ الخمسةِ والثلاثين كلِّها.
 *   فتُطابَق حرفًا: `can_view` وحدَه · وصفرٌ في الكتابةِ والحذف.
 *
 * ◆ ولا أثرَ على السايدبار: رابطُ التنقّلِ الحيُّ الوحيدُ للوحدة 132 في مجموعةٍ
 *   يملكها الدورُ 24 (nav=10157 · group=3814)، فبذرُ المنحةِ لا يُضيف بندًا في
 *   قائمةِ أحد — الوصولُ يبقى من الشريطِ العلويِّ ولوحةِ الدورِ والبحثِ العام.
 *
 * ◆ ولا تُخفَض منحةٌ قائمة: الدورُ 24 يحتفظ بـ(1,1,1,1) — الإدراجُ للصفوفِ
 *   الغائبةِ حصرًا، فالتشغيلُ المكرَّرُ بلا أثر.
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

/* الشاشتان المعفاتان من الحارسِ المركزيِّ — وهما وحدَهما المقصودتان هنا.
   شاشاتُ الإعدادِ والتقاريرِ (الأنواعُ · المهلُ · التصعيدُ · برجُ المراقبةِ ·
   لوحةُ الأداء) تبقى خلفَ منحِها كما هي — ولا تُفتح بحجةِ هذا البلاغ. */
$SHARED = array(
    'Tickets/tickets_list.php' => 'قائمةُ البلاغات — أيقونةُ الشريطِ العلويِّ لكلِّ مستخدم',
    'Tickets/ticket_form.php'  => 'استمارةُ البلاغ — يُبلّغ بها كلُّ مستخدمٍ ويتابع',
);

echo "\n▐ ① قياسُ الحالِ قبلَ البذر\n";
$roles = (int) $one("SELECT COUNT(*) FROM `roles`");
printf("   · الأدوارُ في السجل : %d\n", $roles);
$ids = array();
foreach ($SHARED as $code => $why) {
    $st = $conn->prepare("SELECT `id` FROM `modules` WHERE `code` = ? LIMIT 1");
    $st->bind_param('s', $code);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$row) { printf("   ✗ وحدةٌ غيرُ مسجَّلة: %s — تخطٍّ\n", $code); continue; }
    $mid = (int) $row['id'];
    $ids[$code] = $mid;
    $have = (int) $one("SELECT COUNT(*) FROM `role_permissions` WHERE `module_id` = $mid AND `can_view` = 1");
    printf("   · %-30s وحدة=%-4d أدوارٌ لها العرض: %d من %d\n", basename($code), $mid, $have, $roles);
}

echo "\n▐ ② بذرُ العرضِ للأدوارِ التي لا صفَّ لها\n";
foreach ($ids as $code => $mid) {
    /* الإدراجُ للغائبِ حصرًا — فلا يُخفَض صفٌّ قائمٌ ولا تُمَسُّ منحُ الكتابة. */
    $conn->query(
        "INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
         SELECT r.`id`, $mid, 1, 0, 0, 0
           FROM `roles` r
          WHERE NOT EXISTS (SELECT 1 FROM `role_permissions` rp
                             WHERE rp.`role_id` = r.`id` AND rp.`module_id` = $mid)");
    if ($conn->errno) { printf("   ✗ %s — %s\n", basename($code), $conn->error); continue; }
    printf("   ✔ %-30s أُضيف %d صفًّا\n", basename($code), $conn->affected_rows);
}

echo "\n▐ ③ التحقُّق\n";
$ok = true;
foreach ($ids as $code => $mid) {
    $have = (int) $one("SELECT COUNT(*) FROM `role_permissions` WHERE `module_id` = $mid AND `can_view` = 1");
    $pass = ($have === $roles);
    $ok = $ok && $pass;
    printf("   · %-30s العرضُ لـ%d من %d   [%s]\n", basename($code), $have, $roles, $pass ? '✔' : '✗');
}
/* لا كتابةَ مُسرَّبةٌ للأدوارِ المبذورة: من عدا 24 بصفرٍ في add/edit/delete. */
$leak = 0;
foreach ($ids as $mid) {
    $leak += (int) $one("SELECT COUNT(*) FROM `role_permissions`
        WHERE `module_id` = $mid AND `role_id` <> 24
          AND (`can_add` = 1 OR `can_edit` = 1 OR `can_delete` = 1)");
}
printf("   · منحُ كتابةٍ مُسرَّبةٌ لغيرِ الدور 24 : %d   [المتوقَّع 0]\n", $leak);
/* والدورُ 24 كما كان — لم تُخفَض منحتُه. */
$m132 = isset($ids['Tickets/tickets_list.php']) ? $ids['Tickets/tickets_list.php'] : 0;
if ($m132) {
    printf("   · الدور 24 على القائمة              : %s   [المتوقَّع 1/1/1/1]\n",
        (string) $one("SELECT CONCAT(`can_view`,'/',`can_add`,'/',`can_edit`,'/',`can_delete`)
                         FROM `role_permissions` WHERE `module_id` = $m132 AND `role_id` = 24"));
}
printf("\n%s\n\n", ($ok && $leak === 0) ? '   ✔ الحاجبُ مرفوع — والحارسُ باقٍ مغلقًا لأيِّ دورٍ لا صفَّ له.'
                                        : '   ✗ لم يكتملْ — راجعْ ما سبق.');
