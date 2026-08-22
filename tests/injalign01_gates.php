<?php
/**
 * tests/injalign01_gates.php
 *   بواباتُ قبولِ مواءمةِ المبيعاتِ والموردين — INJ-SAL-ALIGN-01 · INJ-SUP-ALIGN-01
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **كلُّ بوابةٍ ببسطِها ومقامِها لا بالانطباع** — بنصِّ الوثيقتين. وكلُّ واحدةٍ
 *   إمّا **خضراءُ بقياس**، أو **محجوبةٌ بمدخلٍ خارجيٍّ مُعلَن**، أو **مفتوحةٌ
 *   بسببِها**. ولا بوابةَ خضراءُ بلا مقام.
 *
 * ◆ **والمحجوبُ لا يُدَّعى نجاحُه ولا يُعَدُّ رسوبًا**: بوابتا الأعمدة
 *   (589/589 و 717/717) تلزمهما المصنَّفان الحاكمان، وهما **ليسا في الحزمة
 *   المرفقة**. فتُعلَن `BLOCKED_EXTERNAL_INPUT` بمالكِها ومطلوبِها.
 *
 * التشغيل: php tests/injalign01_gates.php [--json=<ملف>]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

function one(mysqli $c, $sql) { $r = @$c->query($sql); return $r ? (int) $r->fetch_row()[0] : -1; }

$G = array();
function gate(&$G, $doc, $code, $state, $title, $measure, $note = '')
{
    $G[] = array('doc' => $doc, 'code' => $code, 'state' => $state,
                 'title' => $title, 'measure' => $measure, 'note' => $note);
}
$SAL = 'SAL'; $SUP = 'SUP';
$ANCH = "'main/role_board.php','chats/index.php'";

echo "══ بواباتُ قبولِ مواءمةِ المبيعاتِ والموردين ══\n";

foreach (array(
    array($SAL, 'INJ-SAL-ALIGN-01', 12, 26, 589, 13),
    array($SUP, 'INJ-SUP-ALIGN-01', 2,  29, 717, 14),
) as $D) {
    list($k, $doc, $role, $sheets, $cols, $items) = $D;

    /* ── ① التغطية: قرارُ سطحٍ لكلِّ ورقة ─────────────────────────────── */
    $n = one($conn, "SELECT COUNT(*) FROM `gov_sheet_decisions`
                      WHERE `doc_code` = '{$doc}' AND `is_index` = 0");
    $blank = one($conn, "SELECT COUNT(*) FROM `gov_sheet_decisions`
                          WHERE `doc_code` = '{$doc}' AND (`decision` = '' OR `target` = '')");
    gate($G, $k, 'A1', ($n === $sheets && $blank === 0) ? 'PASS' : 'OPEN',
         'التغطية — قرارُ سطحٍ لكلِّ ورقة',
         "**{$n}/{$sheets}** · بلا قرارٍ أو هدف: {$blank}",
         'وورقةُ الفهرسِ لها قرارٌ ولا تُعَدُّ في المقام');

    /* ── ② الأعمدة — محجوبةٌ بمدخلٍ خارجيّ ────────────────────────────── */
    gate($G, $k, 'A2', 'BLOCKED',
         'الأعمدة — حكمُ مصدرٍ لكلِّ عمود',
         "0/{$cols} — **لا يُقاس**",
         'المصنَّفُ الحاكمُ ليس في الحزمة · المطلوب: المصنَّفُ نفسُه ليُقيَّد لكلِّ '
       . 'عمودٍ حكمُ مصدرِه أو سببُ غيابِه · المالك: مالكُ الوثيقة');

    /* ── ③ التنقّل بعد الدمج ──────────────────────────────────────────── */
    $live = one($conn, "SELECT COUNT(*) FROM `nav_items`
                         WHERE `role_id` = {$role} AND `active` = 1 AND `route` NOT IN ({$ANCH})");
    $grp  = one($conn, "SELECT COUNT(DISTINCT `group_id`) FROM `nav_items`
                         WHERE `role_id` = {$role} AND `active` = 1 AND `route` NOT IN ({$ANCH})");
    $empty = one($conn, "SELECT COUNT(*) FROM `link_groups` g
                          WHERE g.`group_code` LIKE 'n9t_align_r{$role}_%'
                            AND NOT EXISTS (SELECT 1 FROM `nav_items` n
                                             WHERE n.`group_id` = g.`id` AND n.`active` = 1)");
    gate($G, $k, 'A3', ($live === $items && $grp === 6 && $empty === 0) ? 'PASS' : 'OPEN',
         'التنقّل بعد الدمج',
         "**{$grp} مجموعات / {$live} بندًا** — المستهدَف 6/{$items} · مجموعةٌ فارغة: {$empty}",
         'والمرساتانِ خارجَ المقامِ بقرارِ مالكٍ سابق');

    /* ── ④ التبويبات — كلُّ مُخفًى له منفذٌ مُثبَت ───────────────────────── */
    $hid  = one($conn, "SELECT COUNT(*) FROM `gov_nav_hidden_log` WHERE `role_id` = {$role}");
    $noWay = one($conn, "SELECT COUNT(*) FROM `gov_nav_hidden_log`
                          WHERE `role_id` = {$role} AND (`reachable` IS NULL OR `reachable` = '')");
    gate($G, $k, 'A4', ($noWay === 0) ? 'PASS' : 'OPEN',
         'التبويبات — صفرُ تبويبٍ مكسور',
         "أُخفي {$hid} بندًا · **بلا منفذٍ مُثبَت: {$noWay}**",
         'والمنفذُ أحدُ ثلاثة: تبويبٌ في ملفٍّ أمّ · نشطٌ لدورٍ آخر · مملوكٌ بحكمٍ مكتوب');

    /* ── ⑤ الملكية — لا شاشةَ إدارةٍ أخرى أصليةً في **مساحةِ هذا الدور** ─────
     * ◆ **فخُّ القياسِ الذي وقعتُ فيه أوّلًا**: `gov_space_appearances` صفٌّ لكلِّ
     *   **ظهورٍ في مساحة**، فسؤالُه عن «أيِّ صفٍّ ممنوعٍ لهذا المسار» يلتقط
     *   منعَه في مساحةِ إدارةٍ أخرى ويحسبه تسريبًا هنا. فـ`clients.php` ممنوعٌ
     *   في مساحةِ التشغيلِ **ومملوكٌ في مساحةِ المبيعات** — والقياسُ الأوّلُ
     *   أعلنه تسريبًا. ⇒ **يُقيَّد بمساحةِ الدورِ نفسِه**. */
    $spaceOf = array(12 => 'ادارة المبيعات', 2 => 'ادارة الموردين');
    $sp = $conn->real_escape_string($spaceOf[$role]);
    $leak = one($conn, "
      SELECT COUNT(*) FROM `nav_items` n
       WHERE n.`role_id` = {$role} AND n.`active` = 1 AND n.`route` NOT IN ({$ANCH})
         AND EXISTS (SELECT 1 FROM `gov_space_appearances` s
                      WHERE s.`route` = n.`route` AND s.`space_ar` = '{$sp}'
                        AND s.`cls` = 'FORBIDDEN'
                        AND s.`owner_kind` <> 'PLATFORM_SHARED')");
    gate($G, $k, 'A5', ($leak === 0) ? 'PASS' : 'OPEN',
         'الملكية — صفرُ تسريبِ شاشةِ إدارةٍ أخرى',
         "بنودٌ ظاهرةٌ حكمُها ممنوعٌ في هذه المساحة: **{$leak}**",
         'والمكوّنُ المركزيُّ المشتركُ (المهامُّ والاعتمادات) ليس تسريبًا — `PLATFORM_SHARED`');
}

/* ── ⑥ الازدواج — لا وظيفةٌ بسطحَين كنسيَّين ولا مسارٌ باسمَين ────────────── */
/* ◆ **والمقامُ هو مقامُ الوثيقة**: «لا مسارَ باسمَين **معياريَّين**» — أي مسارٌ
 *   حالتُه `APPROVED` في السجلِّ الكنسيِّ ويُصيَّر باسمَين. وقياسُ **كلِّ** مسارٍ
 *   بأيِّ اسمَين يخلط الاسمَ المعياريَّ بالاسمِ التاريخيِّ لدورٍ لم يُوحَّد بعد،
 *   فيصنع ثلاثةً وخمسين مخالفةً لا وجودَ لها في حكمِ الوثيقة. */
$dupName = one($conn, "SELECT COUNT(*) FROM (
      SELECT n.`route` FROM `nav_items` n
        JOIN `nav_canonical` c ON c.`route` = n.`route` AND c.`status` = 'APPROVED'
       WHERE n.`active` = 1 AND n.`route` NOT LIKE '%#%'
       GROUP BY n.`route` HAVING COUNT(DISTINCT n.`label_ar`) > 1) x");
$dupCanon = one($conn, "SELECT COUNT(*) FROM (
      SELECT `canonical_ar` FROM `nav_canonical` WHERE `status` = 'APPROVED'
       GROUP BY `canonical_ar` HAVING COUNT(DISTINCT `route`) > 1) x");
gate($G, 'ALL', 'A6', ($dupName === 0 && $dupCanon === 0) ? 'PASS' : 'OPEN',
     'الازدواج — لا مسارٌ باسمَين ولا اسمٌ لمسارَين',
     "مسارٌ بأكثرَ من اسمٍ ظاهر: **{$dupName}** · اسمٌ كنسيٌّ لأكثرَ من مسار: **{$dupCanon}**",
     'ومسارُ المناقصاتِ صار تبويبَ الفرصةِ — والفرصةُ هي الكنسية');

/* ── ⑦ الحدود — لا إدخالَ وحدةٍ داخلَ المبيعاتِ أو الموردين ──────────────── */
$entryScreens = array('Timesheet/timesheet.php', 'Timesheet/timesheet_type.php',
                      'Timesheet/view_timesheet.php', 'Operations/shift_entry.php');
$in = "'" . implode("','", $entryScreens) . "'";
$manual = one($conn, "SELECT COUNT(*) FROM `nav_items`
                       WHERE `role_id` IN (12,2) AND `active` = 1 AND `route` IN ({$in})");
gate($G, 'ALL', 'A7', ($manual === 0) ? 'PASS' : 'OPEN',
     'الحدود — لا إدخالَ ساعةٍ أو طنٍّ في المبيعاتِ أو الموردين',
     "أسطحُ إدخالِ وحداتٍ ظاهرةٌ لهما: **{$manual}**",
     'فالواقعةُ بيتُها التايم شيت — وتُقرأ هنا لا تُدخَل');

/* ── ⑧ الهرم — لا حصةَ بلا التزامٍ ولا معدةَ تُنشئ حصة ──────────────────── */
$guard = 0;
foreach (array('app/Services/Capacity/CapacityContextResolver.php',
               'app/Services/Unit/CapacityGuard.php') as $f) {
    if (is_file($ROOT . '/' . $f)) { $guard++; }
}
gate($G, $SUP, 'A8', ($guard === 2) ? 'PASS' : 'OPEN',
     'الهرم — الحصةُ من الالتزامِ لا من المعدة',
     "حرّاسُ الطاقةِ والسياقِ المبنيّون: **{$guard}/2**",
     'والإسنادُ الإضافيُّ لا يرفع الحصةَ ولا المستهدف — محروسٌ في `CapacityGuard`');

/* ── ⑨ السلامةُ والبيانات — صفرُ ارتدادٍ وصفرُ فقد ─────────────────────── */
$lost = one($conn, "SELECT COUNT(*) FROM `gov_nav_hidden_log` h
                     WHERE NOT EXISTS (SELECT 1 FROM `nav_items` n WHERE n.`id` = h.`nav_id`)");
gate($G, 'ALL', 'A9', ($lost === 0) ? 'PASS' : 'OPEN',
     'البيانات — صفرُ فقدٍ ولا حذفَ صفّ',
     "صفوفُ تنقّلٍ أُخفيت ثم اختفت من الجدول: **{$lost}**",
     'الإخفاءُ `active = 0` — والصفُّ باقٍ بموضعِه السابقِ في سجلِّ الرجوع');

/* ══ العرضُ والحكم ══════════════════════════════════════════════════ */
echo str_repeat('─', 108) . "\n";
$pass = 0; $open = 0; $blocked = 0;
foreach ($G as $g) {
    $m = $g['state'] === 'PASS' ? '✔' : ($g['state'] === 'BLOCKED' ? '⛔' : '✘');
    if ($g['state'] === 'PASS') { $pass++; } elseif ($g['state'] === 'BLOCKED') { $blocked++; } else { $open++; }
    printf("  %-4s %-4s %s %-38s %s\n", $g['doc'], $g['code'], $m, mb_substr($g['title'], 0, 36), $g['measure']);
    if ($g['note'] !== '') { echo "          ◆ " . $g['note'] . "\n"; }
}
echo str_repeat('─', 108) . "\n";
printf("◆ **خضراء: %d · محجوبة: %d · مفتوحة: %d** — المقام %d\n", $pass, $blocked, $open, count($G));
if ($blocked) {
    echo "◆ **والمحجوبُ لا يُدَّعى ولا يُحسب رسوبًا** — يلزمه مدخلٌ خارجيٌّ مُسمًّى.\n";
}
if ($open) { echo "◆ **ولا تُعلَن الجولةُ مغلقةً** ما دامت بوابةٌ مفتوحةً بلا سبب.\n"; }

foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--json=(.*)$/', $a, $m2)) {
        file_put_contents($m2[1], json_encode(array('pass' => $pass, 'blocked' => $blocked,
            'open' => $open, 'gates' => $G), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo "◆ كُتب: {$m2[1]}\n";
    }
}
exit($open === 0 ? 0 : 1);
