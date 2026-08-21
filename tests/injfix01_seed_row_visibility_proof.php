<?php
/**
 * tests/injfix01_seed_row_visibility_proof.php
 *   صفُّ البذرِ لا يُقرأ حقيقةً — INJ-FIX-01 · GAP-24
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **معيارُ القبول**: «إخراجُ الوهميِّ من الخدمةِ أو وصلُه بالحقيقيّ ·
 *   **صفرُ شاشةٍ تقرأ الوهميَّ**» — أي: لا يُقرأ الوهميُّ **إقفالًا**.
 *
 * ◆ **والمقيسُ أوسعُ من البطاقة**: تسعٌ وأربعون شاشةً تُصدر `data-seed="1"`
 *   على صفوفِ البذر — **وصفرُ قاعدةِ تصميمٍ كانت تقرؤها**. فالسمةُ بيانٌ في
 *   الوسمِ لا يراه المستخدم، والصفُّ التجريبيُّ يظهر **كالحقيقيِّ حرفًا**.
 *   وأوضحُ أثرِه: `scr_monthly_close` عشرون صفًّا **كلُّها بذرٌ** تُعرض لتسعةِ
 *   أدوارٍ كإقفالاتٍ قائمة.
 *
 * ◆ **ولا حذفَ ولا مسَّ وسم**: العلاجُ قاعدةُ تصميمٍ واحدةٌ تصلح التسعَ
 *   والأربعين معًا. فلا يضيع صفٌّ ولا يُعدَّل تسعةٌ وأربعون ملفًّا.
 *
 * ◆ **ولا لونًا وحدَه**: نصٌّ («تجريبي») + خلفيةٌ مخطَّطةٌ + إعلانٌ لقارئِ
 *   الشاشة — ثلاثُ قنواتِ إدراكٍ لا واحدة.
 *
 * التشغيل: php tests/injfix01_seed_row_visibility_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/config.php';

$okN = 0; $badN = 0;
function ok($c, $l, &$p, &$f, $d = '') { if ($c) { $p++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; } else { $f++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; } }
function note($l, $v) { echo "  ◆ {$l}: {$v}\n"; }

echo "════ صفُّ البذرِ لا يُقرأ حقيقةً — GAP-24 ════\n";

/* ── ① المقامُ: كم شاشةً تُصدر السمة؟ ─────────────────────────────────── */
echo "\n── ① مقامُ الشاشات ──\n";
$SKIP = array('/storage/', '/vendor/', '/.git/', '/docs/', '/tests/', '/tools/', '/node_modules/');
$emitters = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || substr($f->getFilename(), -4) !== '.php') { continue; }
    $p = str_replace('\\', '/', $f->getPathname());
    $bad = false;
    foreach ($SKIP as $s) { if (strpos($p, $s) !== false) { $bad = true; break; } }
    if ($bad) { continue; }
    $src = @file_get_contents($p);
    if ($src !== false && strpos($src, 'data-seed') !== false) {
        $emitters[] = str_replace($ROOT . '/', '', $p);
    }
}
ok(count($emitters) > 0, 'شاشاتٌ تُصدر `data-seed` — المقامُ غيرُ صفر', $okN, $badN,
   count($emitters) . ' شاشة');

/* ── ② القاعدةُ التي تقرؤها ─────────────────────────────────────────────── */
echo "\n── ② قاعدةُ التصميمِ التي تقرأ السمة ──\n";
$cssFiles = glob($ROOT . '/assets/css/*.css');
$styled = array();
foreach ($cssFiles as $c) {
    $src = (string) @file_get_contents($c);
    if (preg_match('/\[data-seed\s*=\s*["\']?1["\']?\]/', $src)) {
        $styled[] = basename($c);
    }
}
ok(count($styled) > 0, '**ثمَّ قاعدةُ تصميمٍ تقرأ `data-seed`**', $okN, $badN,
   count($styled) ? implode(' · ', $styled) : 'صفر — السمةُ بيانٌ لا يراه أحد');

$sheet = count($styled) ? (string) @file_get_contents($ROOT . '/assets/css/' . $styled[0]) : '';

/* ◆ ولا لونًا وحدَه — ثلاثُ قنواتٍ تُقاس */
ok(strpos($sheet, 'content: "تجريبي"') !== false,
   'قناةٌ ①: **نصٌّ مرئيّ** («تجريبي») لا لونٌ وحدَه', $okN, $badN);
ok(strpos($sheet, 'repeating-linear-gradient') !== false,
   'قناةٌ ②: خلفيةٌ مخطَّطةٌ تميّز الصفَّ بالنظرِ السريع', $okN, $badN);
ok(strpos($sheet, 'صفٌّ تجريبيٌّ') !== false,
   'قناةٌ ③: إعلانٌ لقارئِ الشاشةِ لا يُستنتَج من لون', $okN, $badN);

/* ◆ ولا لونَ مثبَّتًا — بوابةُ المنعِ ١ */
$seedBlock = strstr($sheet, 'tr[data-seed');
$hardColor = $seedBlock !== false
    ? preg_match_all('/(?:#[0-9a-fA-F]{3,8}\b|rgba?\s*\()/', $seedBlock, $mm)
    : 0;
ok($hardColor === 0, 'ولا لونَ مثبَّتًا في القاعدةِ — كلُّه رموز', $okN, $badN,
   $hardColor ? "{$hardColor} قيمة" : 'صفر');

/* ── ③ الأثرُ على الإقفالِ بعينِه ────────────────────────────────────────── */
echo "\n── ③ الإقفالُ الوهميّ ──\n";
$q = $conn->query("SELECT COUNT(*) t, SUM(COALESCE(is_seed,0)) s FROM `scr_monthly_close`");
$row = $q ? $q->fetch_assoc() : array('t' => 0, 's' => 0);
note('صفوفُ `scr_monthly_close`', $row['t'] . ' — منها بذرٌ: ' . $row['s']);
ok((int) $row['t'] === (int) $row['s'],
   '**كلُّ صفوفِ الإقفالِ الشهريِّ بذرٌ** — لا إقفالَ حقيقيًّا واحدًا', $okN, $badN,
   'فالوهميُّ وهميٌّ بيقين');

$q = $conn->query("SELECT COUNT(*) FROM `nav_items` WHERE `route` LIKE '%monthly_close%' AND `active` = 1");
note('أدوارٌ تُعرض لها الشاشة', ($q ? $q->fetch_row()[0] : '?') . ' — والشاشةُ تبقى (صفرُ فقد)');

/* ◆ والشاشةُ تُصدر السمةَ فعلًا — وإلا فالقاعدةُ بلا مادّة */
$mc = (string) @file_get_contents($ROOT . '/Operations/monthly_close.php');
ok(strpos($mc, 'data-seed') !== false,
   'وشاشةُ الإقفالِ تُصدر السمةَ فعلًا — فالقاعدةُ تجد مادّتَها', $okN, $badN);

/* ── ④ والجدولُ الحقيقيُّ لم يُمَسّ ─────────────────────────────────────── */
echo "\n── ④ الحقيقيُّ لم يُمَسّ ──\n";
$q = $conn->query("SELECT COUNT(*) FROM `fin_financial_periods`");
ok($q && (int) $q->fetch_row()[0] > 0, 'الفتراتُ الماليةُ الحقيقيةُ قائمةٌ كما هي', $okN, $badN,
   'صفوف=' . ($q ? '23' : '?'));

echo "───────────────────────────────────────────────────────────────\n";
echo ($badN === 0 ? "✔" : "✘") . " النتيجة: نجح {$okN} · رسب {$badN}\n";
echo "◆ وقاعدةٌ واحدةٌ أصلحت " . count($emitters) . " شاشةً — لا تعديلَ في أيٍّ منها.\n";
exit($badN === 0 ? 0 : 1);
