<?php
/**
 * 2027_08_15_nav_ten_groups_v2.php — التبويبُ يُعاد تصنيفُه بالمعنى لا بالمجلَّد
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ما أوجبها** (قياسُ v1 بعدَ تطبيقِها، لا رأيٌ): صنَّفت v1 **٦٢٪ من
 *   المساراتِ باسمِ المجلَّد**، فظهرت أربعةُ أعراضٍ مقيسة:
 *     ① «العمل اليومي» **رابطٌ واحدٌ** لادارةِ الأسطولِ وللموارد البشرية —
 *        وعملُهما الأساسيُّ (٨ و٩ روابط) في مجموعةٍ اسمُها «الموارد والأصول»
 *        تخلط **الناسَ بالحديد** فلا تصف أيًّا منهما.
 *     ② **٣٣٪ من الرؤوس** (٨٢ من ٢٤٩) برابطٍ أو رابطين — نقرةُ طيٍّ لسطر.
 *        وأنحلُها «المتابعة والاعتمادات»: وسطيُّها **٢٫٢** رابط.
 *     ③ بياناتٌ مرجعيةٌ **تشغيليةٌ يومية** (تعرفةُ الترحيل · اللوابد · الورشُ
 *        والفنيون · كتالوجُ القطع) مدفونةٌ في آخرِ مجموعةٍ مطويّة.
 *     ④ حكمٌ خاطئٌ مؤكَّد: `Maintenance/inspections.php` «الفحصُ الفنيُّ اليوميّ»
 *        في **الحوكمة** لأن اسمَ مجموعتِه المسجَّلِ «استقبالُ البلاغ».
 *
 * ◆ **ما تغيَّر**:
 *     · «الموارد والأصول» ⇐ **«المعدات والأسطول»** و**«الموظفون والمشغّلون»**.
 *     · «المتابعة والاعتمادات» ⇐ قسمٌ **«ما ينتظرني»** في صدرِ «مساحتي»
 *       (المجموعةُ الأولى المفتوحةُ وشارتُها على رأسِها — أقربُ لا أبعد).
 *     · «البيانات والإعدادات» ⇐ **«الإعدادات»**، والمرجعيُّ التشغيليُّ يهبط
 *       في مجالِه (`GROUPDIR`).
 *     · المحورُ الأولُ صار **اسمَ مجموعةِ الدورةِ المستندية** في `nav_canonical`.
 *
 * ◆ **وما لم يتغيَّر**: الآليةُ · التثبيتُ · الطبقتان · حدُّ التسعةِ للقسمِ
 *   المقروء · صفرُ الفقدِ · بابا الرجوع. **ولا يُمسُّ اسمٌ ولا مسارٌ ولا
 *   صلاحيةٌ ولا صفٌّ في `nav_items`.**
 *
 * التشغيل:  php database/migrations/2027_08_15_nav_ten_groups_v2.php [--revert]
 *   و`--revert` هنا يعني **العودةَ إلى تصنيفِ v1**، لا إسقاطَ الجدولين
 *   (إسقاطُهما في هجرةِ 2027_08_14 نفسِها).
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once $ROOT . '/includes/nav_groups.php';

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$chk = @$conn->query("SELECT 1 FROM nav_group_taxonomy LIMIT 1");
if ($chk === false) { exit("جدولا التبويبِ غيرُ موجودين — شغّل 2027_08_14_nav_ten_groups.php أولًا\n"); }

if (in_array('--revert', $argv, true)) {
    echo "الرجوعُ إلى v1: أعِد تشغيلَ 2027_08_14_nav_ten_groups.php بعدَ استرجاعِ نسختِه\n"
       . "من includes/nav_groups.php — فالتصنيفُ يُشتقُّ من الدالةِ الحيّةِ لا من نسخةٍ مخزَّنة.\n"
       . "وللتعطيلِ الفوريِّ بلا قاعدة: EMS_NAV_TEN=off\n";
    exit(0);
}

/* ── ① أثرٌ يُقرأ: لقطةُ التصنيفِ قبلَ التغيير ─────────────────────────────── */
$before = array();
$res = $conn->query("SELECT route, group_code FROM nav_route_group");
while ($res && ($r = $res->fetch_assoc())) { $before[$r['route']] = $r['group_code']; }
echo "لقطةُ ما قبل: " . count($before) . " مسارًا\n";

/* ── ② إعادةُ بناءِ التبويبِ (رمزٌ حُذف ورمزان أُضيفا — فالبناءُ من جديد) ──── */
$conn->query("SET FOREIGN_KEY_CHECKS=0");
if (!$conn->query("DELETE FROM nav_route_group")) { exit("تفريغُ nav_route_group فشل: {$conn->error}\n"); }
if (!$conn->query("DELETE FROM nav_group_taxonomy")) { exit("تفريغُ nav_group_taxonomy فشل: {$conn->error}\n"); }
$conn->query("SET FOREIGN_KEY_CHECKS=1");

$st = $conn->prepare("INSERT INTO nav_group_taxonomy (code,name_ar,icon,sort_no,open_default) VALUES (?,?,?,?,?)");
foreach (ems_nav_groups_def() as $code => $g) {
    $st->bind_param('sssii', $code, $g['name'], $g['icon'], $g['sort'], $g['open']);
    if (!$st->execute()) { exit("بذرُ «{$code}» فشل: {$st->error}\n"); }
}
$st->close();
echo "✔ العشرُ مجموعاتٍ (v2) بُذرت\n";

/* ── ③ جمعُ كلِّ مسارٍ يعرفه النظامُ من مصادرِه الأربعة ──────────────────── */
$norm = function ($r) {
    $r = preg_replace('~^(\.\./)+~', '', trim((string) $r));
    $r = preg_replace('/[?#].*$/u', '', $r);
    return mb_strtolower(trim($r, '/'));
};
$routes = array();
$res = $conn->query("SELECT route, level_no, group_name FROM nav_canonical");
while ($res && ($r = $res->fetch_assoc())) {
    $k = $norm($r['route']);
    if ($k !== '') { $routes[$k] = array((int) $r['level_no'], (string) $r['group_name']); }
}
foreach (array(
    "SELECT route FROM nav_canonical_current",
    "SELECT route FROM nav_items",
    "SELECT code AS route FROM modules WHERE code IS NOT NULL AND code <> ''",
) as $q) {
    $res = $conn->query($q);
    while ($res && ($r = $res->fetch_assoc())) {
        $k = $norm($r['route']);
        if ($k !== '' && !isset($routes[$k])) { $routes[$k] = array(0, ''); }
    }
}
ksort($routes);

/* ── ④ الحكمُ بالدالةِ الحيّةِ نفسِها التي يحتكم إليها المُصيِّر ────────────── */
$st = $conn->prepare("INSERT INTO nav_route_group (route,group_code,basis) VALUES (?,?,?)");
$tally = array(); $bas = array(); $moved = array(); $n = 0;
foreach ($routes as $route => $meta) {
    list($code, $basis) = ems_nav_group_for_route($route, $meta[0], $meta[1]);
    $st->bind_param('sss', $route, $code, $basis);
    if (!$st->execute()) { exit("نسبةُ «{$route}» فشلت: {$st->error}\n"); }
    $n++;
    $tally[$code] = (isset($tally[$code]) ? $tally[$code] : 0) + 1;
    $bk = preg_replace('/:.*$/', '', $basis);
    $bas[$bk] = (isset($bas[$bk]) ? $bas[$bk] : 0) + 1;
    if (isset($before[$route]) && $before[$route] !== $code) { $moved[] = $route . ': ' . $before[$route] . ' ⇐ ' . $code; }
}
$st->close();

echo "✔ نُسِب {$n} مسارًا · انتقل منها " . count($moved) . "\n\n";
foreach (ems_nav_groups_def() as $code => $g) {
    printf("  %2d  %-12s %-24s %d\n", $g['sort'], $code, $g['name'], isset($tally[$code]) ? $tally[$code] : 0);
}
echo "\nالسند: ";
foreach ($bas as $k => $c) { echo "{$k}={$c} "; }
$byMeaning = (isset($bas['PIN']) ? $bas['PIN'] : 0) + (isset($bas['GROUP']) ? $bas['GROUP'] : 0);
echo "\nبالمعنى (PIN+GROUP) = " . $byMeaning . " من {$n} = " . round($byMeaning / max(1, $n) * 100) . "٪\n";
echo "\nتمّ. التعطيلُ بـEMS_NAV_TEN=off.\n";
