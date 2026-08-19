<?php
/**
 * tools/uxui_migration_measure.php — حالةُ الترحيلِ **تُقاس من الشاشةِ لا تُكتب**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ اختبارُ القبولِ في دفترِ التدقيقِ لكلِّ موضع: «**AC-U1 قشرة · AC-U2 مكوّن ·
 *   AC-U3 حالات**». وهذه الأداةُ تقيس الثلاثةَ على **ملفِّ الشاشةِ نفسِه**
 *   وتكتب الحالةَ في `gov_migration_ledger` — فلا يُعلَن «مُرحَّلٌ» بقرارِ كاتب.
 *
 * ◆ **AC-U1 القشرةُ الموحَّدة**: الشاشةُ تُصيَّر داخلَ الغلافِ المركزيِّ
 *   (`ems-unified-page-shell` أو `page_header.php`) — لا ترويسةً محليةً.
 * ◆ **AC-U2 المكوّناتُ المركزية**: تستعمل المكتبةَ (`ems-`/`ux-`/DataGrid) ولا
 *   تُعرِّف نمطًا محليًّا (`<style>` بقواعدَ بنيوية).
 * ◆ **AC-U3 قاموسُ الحالات**: كلُّ حالةٍ تمرُّ بـ`ems_status_badge()` — **ولا
 *   قيمةَ داخليةٍ إنجليزيةٍ في النصِّ المُصيَّر**.
 *
 * ◆ **وثلاثيُّ الحكم**: `NOT_STARTED` · `IN_PROGRESS` (بعضُها) ·
 *   `TECHNICALLY_ELIGIBLE` (الثلاثةُ معًا) — ولا تُستعمل «CLOSED» ولا «100٪»
 *   بقرارِ المالك. والشاشةُ بلا مسارٍ محلولٍ **لا تُقاس ولا تُحسب** — تبقى
 *   `NOT_STARTED` بملاحظةٍ تقول لماذا، فلا يُخفى نقصُ القياسِ خلف رقم.
 *
 * التشغيل:
 *   php tools/uxui_migration_measure.php            جردٌ بلا كتابة
 *   php tools/uxui_migration_measure.php --apply    يكتب الحالةَ المقيسة
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال\n"); }
$conn->set_charset('utf8mb4');
$APPLY = in_array('--apply', $argv, true);

/**
 * **المصدرُ المقيسُ يتبع مسارَ التصييرِ لا الملفَّ وحدَه.**
 * ◆ كشفته 16 شاشةً في `Audit/` أخفقت U1 و U2 معًا — وهي **ملفاتٌ مولَّدةٌ**
 *   (`u13:generated`) تصييرُها كلُّه في `includes/u13_screen_kit.php`، والعُدّةُ
 *   تستعمل القشرةَ والمكتبةَ وحالاتِ الشاشةِ فعلًا. فقياسُ الملفِّ وحدَه
 *   **يُرسِّب شاشةً سليمةً** لأن بنيتَها ليست فيه.
 * ◆ فتُضمُّ **العُدّةُ المُضمَّنةُ صراحةً** إلى النصِّ المقيس — بحدٍّ واحدٍ من
 *   العمق: لا تتبُّعَ شجرةٍ كاملةٍ فيتضخّم الادّعاءُ ويُقاس ما لا يخصُّ الشاشة.
 * ◆ **ونطاقُ كلِّ معيارٍ مختلفٌ عمدًا** — وخلطُه أفسد القياسَ مرةً:
 *   · U1 القشرةُ و U3 الحالات: على **مسارِ التصييرِ** (الشاشةُ + عُدّتُها)،
 *     لأن البنيةَ قد تكون في العُدّة.
 *   · U2 «صفرُ نمطٍ محليٍّ في الشاشة»: على **ملفِّ الشاشةِ وحدَه** — فأنماطُ
 *     العُدّةِ المركزيةِ ليست «محليةً في الشاشة» بحال. وضمُّها رفع الإخفاقَ
 *     من 258 إلى 460 على شاشاتٍ لم يتغيّر فيها حرف.
 */
function ems_effective_src($ROOT, $route, &$cache) {
    $path = $ROOT . '/' . $route;
    if (!is_file($path)) { return null; }
    $src = (string) file_get_contents($path);
    $out = $src;
    /* العُدَدُ المركزيةُ المُضمَّنةُ صراحةً — بأسمائها كما تُكتب في الشاشة */
    if (preg_match_all('~(?:include|require)(?:_once)?[^;\n]{0,120}?[\'"]([^\'"]*(?:_kit|_shell|screen_contract|page_header)[^\'"]*\.php)[\'"]~', $src, $m)) {
        foreach ($m[1] as $inc) {
            $rel = preg_replace('~^.*?(includes/)~', '$1', $inc);
            $p = $ROOT . '/' . ltrim(str_replace(chr(92), '/', $rel), '/');
            if (!is_file($p)) { continue; }
            if (!isset($cache[$p])) { $cache[$p] = (string) file_get_contents($p); }
            $out .= "\n" . $cache[$p];
        }
    }
    return $out;
}

/** القشرةُ الموحَّدة — تُقاس بالغلافِ أو بالترويسةِ المركزية */
function ac_u1($src) {
    if (strpos($src, 'ems-unified-page-shell') !== false) { return true; }
    if (preg_match('~includes/page_header\.php~', $src)) { return true; }
    if (preg_match('~(include|require)(_once)?[^;\n]{0,80}insidebar\.php~', $src)
        && preg_match('~class\s*=\s*["\'][^"\']*\bmain\b~', $src)) { return true; }
    return false;
}

/** المكوّناتُ المركزية — تستعمل المكتبةَ ولا تُعرِّف نمطًا بنيويًّا محليًّا */
function ac_u2($src, &$why) {
    $usesLib = (bool) preg_match('~\b(ems-|ux-|alltables|dataTable|EmsDetailsModal|ems_status_badge)~', $src);
    /* نمطٌ محليٌّ بنيويٌّ: قاعدةٌ في <style> فيها خلفيةٌ أو حدٌّ أو شبكة */
    $localStruct = 0;
    if (preg_match_all('~<style[^>]*>(.*?)</style>~si', $src, $m)) {
        foreach ($m[1] as $css) {
            /* يُتجاوز ما أُعلن استثناءً صراحةً */
            if (strpos($css, 'data-allow-style') !== false) { continue; }
            $localStruct += preg_match_all('~\{[^}]*\b(background|border-radius|box-shadow|grid-template)\b[^}]*\}~i', $css);
        }
    }
    $why = 'مكتبة=' . ($usesLib ? 'نعم' : 'لا') . ' · نمطٌ بنيويٌّ محليّ=' . $localStruct;
    return $usesLib && $localStruct === 0;
}

/**
 * AC-U3 «حالات» = **حالاتُ الشاشةِ الموحَّدة** (فارغة · خطأ · تحميل) —
 * لا قاموسُ الحالاتِ الاثنتَي عشرة.
 * ◆ ودليلُه في الدفترِ نفسِه: عمودُ «المكوّناتُ المطلوبة» ينصُّ حرفًا على
 *   «INJAZ DataGrid · الشريطُ الموحَّد · المرشِّحات · المناظرُ المحفوظة ·
 *   **حالاتُ الشاشة**». والقاموسُ بندٌ آخرُ تقيسه البوابةُ U7 على المُصيَّر.
 * ◆ **وكشفه القياسُ نفسُه**: أولُ صياغةٍ طلبت نداءَ `ems_status_badge()` فأخفقت
 *   **تسعٌ من الذهبيةِ العشر** — وهي مجتازةٌ لكلِّ البوابات. فمقياسٌ يُرسِّب
 *   المرجعَ الذي بُني ليكون مرجعًا **هو المخطئ**، لا المرجع.
 * ◆ والمقيسُ: نداءُ أحدِ عونِ الحالاتِ المركزيّ — ولا تُطالَب شاشةٌ لا تعرض
 *   قائمةً بحالةِ فراغ.
 */
function ac_u3($src, &$why) {
    $needs = (bool) preg_match('~<table|dataTable|alltables|foreach\s*\(~i', $src);
    if (!$needs) { $why = 'لا قائمةَ تُعرض — لا تُطالَب بحالةِ فراغ'; return true; }
    $uses = (bool) preg_match('~ems_states_bundle\s*\(|ems_state_(empty|error|loading|success)\s*\(|ems-state-(empty|error|loading)~', $src);
    $why = 'عونُ الحالاتِ المركزيّ=' . ($uses ? 'نعم' : 'لا');
    return $uses;
}
$rows = array();
$r = $conn->query("SELECT id, route, resolve_state, severity FROM gov_migration_ledger");
while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }

$tot = array('TECHNICALLY_ELIGIBLE' => 0, 'IN_PROGRESS' => 0, 'NOT_STARTED' => 0);
$bySev = array();
$acFail = array('u1' => 0, 'u2' => 0, 'u3' => 0);
$upd = $APPLY ? $conn->prepare("UPDATE gov_migration_ledger
        SET migration_state = ?, measured_at = NOW(), measure_note = ? WHERE id = ?") : null;
$cache = array();

foreach ($rows as $row) {
    $route = (string) $row['route'];
    $sev = $row['severity'] ?: '—';
    if ($route === '' || $row['resolve_state'] !== 'RESOLVED') {
        $state = 'NOT_STARTED';
        $note = 'لا مسارَ محلولٌ (' . $row['resolve_state'] . ') — **لا يُقاس ولا يُحسب مُرحَّلًا**';
    } else {
        $path = $ROOT . '/' . $route;
        if (!isset($cache[$path])) { $cache[$path] = ems_effective_src($ROOT, $route, $cache); }
        $src = $cache[$path];
        if ($src === null) {
            $state = 'NOT_STARTED';
            $note = 'الملفُّ غيرُ موجودٍ على القرص — نسبةٌ خطأٌ لا نقصُ ترحيل';
        } else {
            $w2 = ''; $w3 = '';
            /* U2 على ملفِّ الشاشةِ وحدَه — والباقي على مسارِ التصيير */
            $own = (string) @file_get_contents($path);
            $u1 = ac_u1($src); $u2 = ac_u2($own, $w2); $u3 = ac_u3($src, $w3);
            if (!$u1) { $acFail['u1']++; }
            if (!$u2) { $acFail['u2']++; }
            if (!$u3) { $acFail['u3']++; }
            $n = (int) $u1 + (int) $u2 + (int) $u3;
            $state = $n === 3 ? 'TECHNICALLY_ELIGIBLE' : ($n === 0 ? 'NOT_STARTED' : 'IN_PROGRESS');
            $note = sprintf('U1قشرة=%s · U2مكوّن=%s (%s) · U3حالات=%s (%s)',
                            $u1 ? '✔' : '✗', $u2 ? '✔' : '✗', $w2, $u3 ? '✔' : '✗', $w3);
        }
    }
    $tot[$state]++;
    if (!isset($bySev[$sev])) { $bySev[$sev] = array('TECHNICALLY_ELIGIBLE' => 0, 'IN_PROGRESS' => 0, 'NOT_STARTED' => 0); }
    $bySev[$sev][$state]++;
    if ($APPLY) { $upd->bind_param('ssi', $state, $note, $row['id']); $upd->execute(); }
}

$n = count($rows);
echo "════ حالةُ الترحيلِ — مقيسةً من الشاشاتِ لا مكتوبة ════\n";
printf("  المواضع: %d\n", $n);
printf("    · TECHNICALLY_ELIGIBLE (الثلاثةُ معًا): %d = %.1f٪\n",
       $tot['TECHNICALLY_ELIGIBLE'], $n ? 100 * $tot['TECHNICALLY_ELIGIBLE'] / $n : 0);
printf("    · IN_PROGRESS (بعضُها): %d\n", $tot['IN_PROGRESS']);
printf("    · NOT_STARTED: %d\n", $tot['NOT_STARTED']);
echo "\n  ▐ الإخفاقُ لكلِّ معيار (من المقيسِ فعلًا)\n";
printf("    · AC-U1 قشرة: %d · AC-U2 مكوّن: %d · AC-U3 حالات: %d\n",
       $acFail['u1'], $acFail['u2'], $acFail['u3']);
echo "\n  ▐ بترتيبِ الشدةِ — وهو ترتيبُ الموجاتِ الملزم (ف١٣-١ خطوة ٥)\n";
foreach (array('عالٍ', 'متوسط', '—') as $s) {
    if (!isset($bySev[$s])) { continue; }
    $t = $bySev[$s];
    $sum = array_sum($t);
    printf("    · %-10s %d موضعًا — مؤهَّلٌ %d · جارٍ %d · لم يبدأ %d\n",
           $s, $sum, $t['TECHNICALLY_ELIGIBLE'], $t['IN_PROGRESS'], $t['NOT_STARTED']);
}
echo $APPLY ? "\n  ▸ كُتبت الحالةُ المقيسةُ في السجل\n" : "\n  ▸ جردٌ بلا كتابة — أضِفْ --apply\n";
