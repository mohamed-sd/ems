<?php
/**
 * 2027_09_04_topbar_routes_into_registry.php
 *   الشريطُ العلويُّ يدخل السجلَّ ويخضع للعزل — INJ-FIX-01 · GAP-28
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العيب**: تسعةُ مساراتٍ مصلَّبةٍ في `includes/topbar.php` تُصيَّر لكلِّ دورٍ
 *   **خارجَ سجلِّ عزلِ المساحة** — «ينقض إغلاقَ العزلِ الذي أُعلن».
 *
 * ◆ **والمقيسُ يضيّق العيبَ**: خمسةٌ من التسعةِ **داخلَ السجلِّ سلفًا**
 *   (`Settings/settings.php` · `Tickets/tickets_list.php` · `Portal/my_portal.php`
 *   · `chats/index.php` · `Approvals/hours_approval.php`). **والخارجُ أربعة.**
 *
 * ◆ **ولا يُصنَّف الأربعةُ صنفًا واحدًا** — فليست من جنسٍ واحد:
 *   ① `main/profile.php` و`user_capacities.php`: **شاشتان شخصيتان** يراهما كلُّ
 *      مستخدمٍ عن نفسِه ⇒ تدخلان بـ`PERSONAL_SPACE` كأخواتِهما، فتخضعان للعزل.
 *   ② `logout.php`: **ليست شاشةً** بل إنهاءُ جلسة — ولا معنى لعزلِ مساحةٍ فيها،
 *      وحجبُها يحبس المستخدمَ داخلَ النظام. ⇒ إعفاءٌ مُعلَنٌ بسببِه.
 *   ③ `chats/get_unread_count.php`: **نقطةُ عدٍّ** تُنادى بـfetch وتُرجع رقمًا،
 *      ولا تُصيَّر. ⇒ إعفاءٌ مُعلَنٌ بسببِه.
 *
 * ◆ **والإعفاءُ يُسجَّل ولا يُترك ضمنيًّا** — «الغيابُ ليس منعًا» هو العيبُ نفسُه
 *   الذي يعالجه NF-24. فيُسجَّل الإعفاءُ في `gov_topbar_exemptions` بسببٍ مكتوب.
 *
 * التشغيل:  php database/migrations/2027_09_04_topbar_routes_into_registry.php
 * الرجوع :  php database/migrations/2027_09_04_topbar_routes_into_registry.php --revert
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$MARK = 'GAP-28';

if (in_array('--revert', $argv, true)) {
    $conn->query("DELETE FROM `gov_space_appearances` WHERE `basis` LIKE '{$MARK}%'");
    echo "↺ حُذف {$conn->affected_rows} ظهورًا من جولةِ {$MARK}\n";
    $conn->query("DROP TABLE IF EXISTS `gov_topbar_exemptions`");
    echo "↺ أُسقط سجلُّ الإعفاءات\n";
    exit(0);
}

/* ══ ① الشاشتان الشخصيتان تدخلان سجلَّ العزل ══════════════════════════════
 * ◆ **البنيةُ تُقلَّد لا تُخترع**: الشاشةُ الشخصيةُ في هذا السجلِّ **صفٌّ لكلِّ
 *   مساحةٍ تظهر فيها** لا صفٌّ واحد — `Portal/my_portal.php` مسجَّلةٌ في ١٧ مساحة.
 *   والشريطُ العلويُّ يُصيَّر لكلِّ دورٍ ⇒ فالصفوفُ بعددِ المساحاتِ كلِّها.
 * ◆ و**لا مساحةَ اسمُها «مساحة العمل الشخصية»**: `PERSONAL_SPACE` تصنيفٌ يُطبَّق
 *   داخلَ كلِّ مساحةٍ لا مساحةٌ قائمةٌ بذاتِها — والمِلكيةُ وحدَها تحمل الاسم.
 * ◆ و`id` **بلا AUTO_INCREMENT** هنا — فيُحسب صراحةً من `MAX(id)`. */
/* ◆ **والعاشرُ الذي لم يكن في «التسعة»**: أخرج الفاحصُ — إذ يقرأ `topbar.php`
 *   نفسَه لا قائمةً منسوخة — `main/global_search.php` وهو خارجُ السجلِّ كذلك.
 *   **فالمقامُ عشرةٌ لا تسعة.** وتصنيفُه `PERSONAL_SPACE` كأختِه `chats/index.php`
 *   حرفًا: مُطلِقٌ يراه كلُّ مستخدم، **وعزلُه يُنفَّذ عند الوجهةِ لا عند القائمة**. */
$PERSONAL = array(
    array('main/profile.php',       'ملفي الشخصي'),
    array('user_capacities.php',    'قدراتي وصلاحياتي'),
    array('main/global_search.php', 'البحث الموحد'),
);
$spaces = array();
$q = $conn->query("SELECT DISTINCT `space_ar`,`space_kind` FROM `gov_space_appearances`");
while ($q && $x = $q->fetch_assoc()) { $spaces[] = $x; }
if (!$spaces) { exit("✘ لا مساحاتٍ في السجل — أُوقفت الهجرة\n"); }
echo "① المساحاتُ المتمايزة: " . count($spaces) . "\n";

$q = $conn->query("SELECT COALESCE(MAX(`id`),0) FROM `gov_space_appearances`");
$nextId = (int) $q->fetch_row()[0];

$st = $conn->prepare("INSERT INTO `gov_space_appearances`
        (`id`,`space_ar`,`space_kind`,`tab_ar`,`screen_ar`,`route`,`owner_dept_ar`,`owner_kind`,
         `src_class`,`src_ownership`,`src_decision`,`src_note`,`spaces_count`,
         `cls`,`ownership`,`decision`,`basis`,`rule_step`)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
$added = 0;
foreach ($PERSONAL as $pr) {
    list($route, $label) = $pr;
    $b = mb_strtolower(basename($route));
    $chk = $conn->prepare("SELECT COUNT(*) FROM `gov_space_appearances`
                            WHERE LOWER(SUBSTRING_INDEX(SUBSTRING_INDEX(`route`,'?',1),'/',-1)) = ?");
    $chk->bind_param('s', $b); $chk->execute();
    $exists = (int) $chk->get_result()->fetch_row()[0]; $chk->close();
    if ($exists > 0) { echo "   · {$route}: مسجَّلٌ سلفًا ({$exists}) — لا يُكرَّر\n"; continue; }

    $tab   = 'مركز العمل';
    $owner = 'مساحة العمل الشخصية';
    $ok    = 'PLATFORM_SHARED';
    $sc    = 'PERSONAL_SPACE'; $so = 'VALID'; $sd = 'CONFIRMED';
    $note  = 'شاشةٌ شخصيةٌ في الشريطِ العلويّ — يراها كلُّ مستخدمٍ عن نفسِه';
    $cnt   = count($spaces);
    $cls   = 'PERSONAL_SPACE'; $own = 'VALID'; $dec = 'CONFIRMED';
    $basis = $MARK . ' · مسارٌ مصلَّبٌ في الشريطِ العلويِّ أُدخل السجلَّ ليخضع للعزلِ كغيرِه';
    $step  = 2;
    $mine  = 0;
    foreach ($spaces as $spx) {
        $nextId++;
        $st->bind_param('isssssssssssissssi', $nextId, $spx['space_ar'], $spx['space_kind'], $tab,
            $label, $route, $owner, $ok, $sc, $so, $sd, $note, $cnt, $cls, $own, $dec, $basis, $step);
        if ($st->execute()) { $mine++; }
        else { echo "   ✘ {$route}@{$spx['space_ar']}: {$st->error}\n"; break; }
    }
    $added += $mine;
    echo "   ✔ {$route} ⇐ PERSONAL_SPACE في {$mine} مساحة\n";
}
$st->close();
echo "① دخلت السجلَّ: {$added} ظهورًا\n";

/* ══ ② الإعفاءُ المُعلَنُ لِما ليس شاشة ═══════════════════════════════════ */
$conn->query("CREATE TABLE IF NOT EXISTS `gov_topbar_exemptions` (
    `id`     INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `route`  VARCHAR(190) NOT NULL,
    `kind`   VARCHAR(32)  NOT NULL COMMENT 'SESSION_ACTION | COUNT_ENDPOINT',
    `reason` VARCHAR(400) NOT NULL,
    `declared_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_route` (`route`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='GAP-28 — مساراتُ شريطٍ علويٍّ لا يسري عليها عزلُ المساحة، بسببٍ مكتوب'");

$EXEMPT = array(
    array('logout.php', 'SESSION_ACTION',
          'إنهاءُ جلسةٍ لا شاشة — ولا معنى لعزلِ مساحةٍ فيها، وحجبُها يحبس المستخدمَ داخلَ النظام'),
    array('chats/get_unread_count.php', 'COUNT_ENDPOINT',
          'نقطةُ عدٍّ تُنادى بـfetch وتُرجع رقمًا ولا تُصيَّر — وحارسُها حارسُ وجهتِها chats/index.php'),
);
$st = $conn->prepare("INSERT INTO `gov_topbar_exemptions` (`route`,`kind`,`reason`)
                      VALUES (?,?,?) ON DUPLICATE KEY UPDATE `kind`=VALUES(`kind`), `reason`=VALUES(`reason`)");
foreach ($EXEMPT as $e) {
    $st->bind_param('sss', $e[0], $e[1], $e[2]);
    if ($st->execute()) { echo "   ✔ إعفاءٌ مُعلَن: {$e[0]} ({$e[1]})\n"; }
}
$st->close();

/* ══ ③ الحصيلة ════════════════════════════════════════════════════════════ */
$TOP = array('logout.php', 'main/profile.php', 'Settings/settings.php', 'Tickets/tickets_list.php',
    'user_capacities.php', 'Portal/my_portal.php', 'chats/index.php',
    'chats/get_unread_count.php', 'Approvals/hours_approval.php');
echo "───────────────────────────────────────────────────────────────\n";
$out = 0;
foreach ($TOP as $t) {
    $b = mb_strtolower(basename(preg_replace('/[?\#].*$/', '', $t)));
    $q = $conn->prepare("SELECT COUNT(*) FROM `gov_space_appearances`
                          WHERE LOWER(SUBSTRING_INDEX(SUBSTRING_INDEX(`route`,'?',1),'/',-1)) = ?");
    $q->bind_param('s', $b); $q->execute();
    $inAp = (int) $q->get_result()->fetch_row()[0]; $q->close();
    $q = $conn->prepare("SELECT COUNT(*) FROM `gov_topbar_exemptions` WHERE `route` = ?");
    $q->bind_param('s', $t); $q->execute();
    $inEx = (int) $q->get_result()->fetch_row()[0]; $q->close();
    $state = $inAp > 0 ? "بالسجل ({$inAp})" : ($inEx > 0 ? 'إعفاءٌ مُعلَن' : '**خارجَ الاثنين**');
    if ($inAp === 0 && $inEx === 0) { $out++; }
    printf("  %-32s %s\n", $t, $state);
}
printf("③ **خارجَ السجلِّ والإعفاءِ معًا: %d** (المعيار: صفر)\n", $out);
