<?php
/**
 * tests/nav_doors_integrity_test.php — شاهدُ سلامةِ أبوابِ المصيّر
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0491
 *
 * **العيب**: سؤالٌ واحدٌ («ما أبوابُ القائمةِ وبأيِّ ترتيب؟») له **مصدران**:
 *   ① `unifiedNavDoors()` في PHP — تسعةُ أبواب.
 *   ② سلسلةٌ مكتوبةٌ داخلَ `ORDER BY FIELD(n.door, …)` — ثمانيةٌ فقط، بلا `RISK`.
 * و`FIELD` تُعيد **صفرًا** لما لا تجده، والصفرُ يسبق الواحد — فأيُّ صفِّ `RISK`
 * يخرج من مجموعةٍ مرحليةٍ يقفز **قبل الرئيسية** في السايدبار.
 *
 * ── ولماذا لم يظهر الأثرُ حتى الآن ─────────────────────────────────────────
 * لأنَّ صفوفَ RISK الثمانين كلَّها داخلَ مجموعاتٍ مرحلية (`stage_no` غيرُ فارغ)
 * فتُطبع في وضعِ المراحلِ ولا تبلغ حلقةَ الأبواب. عيبٌ **نائمٌ** لا معدوم —
 * ولذلك لا يكفي أن يقيسَ الفاحصُ الواقعَ الراهن: **يزرع الحالةَ التي توقظه**.
 *
 * ── الحالةُ المزروعة ────────────────────────────────────────────────────────
 * صفُّ `RISK` واحدٌ **بلا مجموعةٍ مرحلية** لدورٍ حيٍّ، ثم يُسأل المصيّر: أيَّهما
 * أوّلًا — الرئيسيةُ أم المخاطر؟ بالمصدرِ الواحدِ تأتي المخاطرُ آخرًا؛ وبالسلسلةِ
 * المكتوبةِ تقفز أوّلًا. ويُكنس الصفُّ بعائلةِ وسمِه مهما انتهت الجولة.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
require_once $ROOT . '/includes/unified_nav.php';

$conn = $GLOBALS['conn'];
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };

/* ── كنسٌ بعائلةِ الوسمِ **قبل** الجولةِ وبعدها ───────────────────────────── */
$TAG = 'NAVDOOR-PROBE';
$sweep = function () use ($conn, $TAG) {
    $n = 0;
    $st = $conn->prepare('DELETE FROM nav_items WHERE label_ar LIKE ?');
    $like = '%' . $TAG . '%';
    $st->bind_param('s', $like);
    if ($st->execute()) { $n = $st->affected_rows; }
    $st->close();
    return $n;
};
$pre = $sweep();
$say('══ INJ-0491 · بابُ المصيّرِ: مصدرٌ واحدٌ للترتيبِ لا اثنان' . ($pre ? "  (كُنس {$pre} من جولةٍ سابقة)" : ''));

$doors = array_keys(unifiedNavDoors());
$ok(count($doors) > 0, 'الأبوابُ معرَّفةٌ في `unifiedNavDoors()` (' . count($doors) . ')');

/* ── ① قيمُ door الحيّةُ مجموعةٌ فرعيةٌ من المعرَّف ────────────────────────── */
$live = array();
$r = $conn->query('SELECT DISTINCT door FROM nav_items WHERE active = 1');
while ($r && ($x = $r->fetch_row())) { $live[] = (string) $x[0]; }
$orphan = array_diff($live, $doors);
$ok(empty($orphan), 'كلُّ قيمةِ `door` حيّةٍ لها بابٌ معرَّف',
    'بلا تعريف: ' . implode(' · ', $orphan));

/* ── ② ولا بابَ معرَّفًا بلا صفوف ──────────────────────────────────────────── */
$empty = array_diff($doors, $live);
$ok(empty($empty), 'ولا بابَ معرَّفًا بلا صفٍّ واحد', 'فارغةٌ: ' . implode(' · ', $empty));

/* ── ③ الحالةُ المزروعة: صفُّ RISK بلا مرحلة ───────────────────────────────── */
$roleId = 0;
$r = $conn->query("SELECT role_id FROM nav_items WHERE active = 1 AND door = 'HOME'
                   GROUP BY role_id ORDER BY role_id LIMIT 1");
if ($r && ($x = $r->fetch_row())) { $roleId = (int) $x[0]; }
$ok($roleId > 0, "وُجد دورٌ حيٌّ له بابُ رئيسيةٍ يُقاس عليه (دور {$roleId})");

$modId = 0;
$r = $conn->query('SELECT id FROM modules ORDER BY id LIMIT 1');
if ($r && ($x = $r->fetch_row())) { $modId = (int) $x[0]; }

$seeded = false;
if ($roleId > 0) {
    $st = $conn->prepare("INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar,
                             route, icon, sort_order, active)
                          VALUES (?, 'RISK', NULL, ?, ?, 'Risk/risk_register.php', 'fa fa-shield', 1, 1)");
    $lbl = 'خطرٌ مزروعٌ · ' . $TAG;
    $st->bind_param('iis', $roleId, $modId, $lbl);
    $seeded = (bool) $st->execute();
    $st->close();
}
$ok($seeded, 'زُرع صفُّ `RISK` **خارجَ المجموعاتِ المرحلية** — فيبلغ حلقةَ الأبواب',
    $conn->error);

if ($seeded) {
    $items = getUnifiedNavItems($conn, $roleId);
    $posRisk = null; $posHome = null;
    foreach ($items as $i => $it) {
        if ($posRisk === null && $it['door'] === 'RISK' && strpos((string) $it['label_ar'], $TAG) !== false) { $posRisk = $i; }
        if ($posHome === null && $it['door'] === 'HOME') { $posHome = $i; }
    }
    $ok($posRisk !== null && $posHome !== null,
        'ويظهر البابانِ معًا في مُخرَجِ المصيّر (RISK@' . var_export($posRisk, true)
        . ' · HOME@' . var_export($posHome, true) . ')');
    $ok($posRisk !== null && $posHome !== null && $posRisk > $posHome,
        '**وبابُ المخاطرِ بعد الرئيسيةِ لا قبلَها** — فترتيبُ `FIELD` يعرف الأبوابَ التسعةَ كلَّها',
        'قفز RISK إلى الموضع ' . var_export($posRisk, true) . ' قبل HOME@' . var_export($posHome, true)
        . ' — سلسلةُ `FIELD` لا تعرف البابَ فأعطته صفرًا');
}

$post = $sweep();
$say("   كُنس ختامًا: {$post} صفًّا");
$left = 0;
$r = $conn->query("SELECT COUNT(*) FROM nav_items WHERE label_ar LIKE '%" . $TAG . "%'");
if ($r && ($x = $r->fetch_row())) { $left = (int) $x[0]; }
$ok($left === 0, "صفرُ ثغرةٍ من عائلةِ الوسمِ بعد الجولة ({$left})");

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
