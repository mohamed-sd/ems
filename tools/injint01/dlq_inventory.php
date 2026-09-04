<?php
/**
 * tools/injint01/dlq_inventory.php — جردُ كتّابِ الرسائلِ الميتةِ وقرّائها (EXE-01 §3·1)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لا يُنزَع مسارُ كتابةٍ قبل معرفةِ من يعتمد عليه.** والأمرُ يسمّي خمسةَ
 *   اعتماداتٍ يجب إثباتُ خلوِّ النظامِ منها قبل نزعِ الحذف.
 *
 * ⛔ **والحذفُ ليس تنظيفًا**: `uq_legacy_consumer_event (consumer,event_id)` فريد،
 *   وعدّادُ المحاولاتِ يُزاد بـ`ON DUPLICATE KEY UPDATE` على القيدِ نفسِه.
 *   فالحذفُ **هو ما يُصفِّر العدّاد**. ⇐ ونزعُه وحدَه يجعل الصفَّ يُلتقَط في
 *   الدورةِ التاليةِ فيقفز فوقَ السقفِ ويدخل فرعَ العزلِ أبدًا.
 *
 * ⛔ **ولا يُقاس الاعتمادُ بغيابِ الصفوفِ اليوم**: الجدولُ فارغٌ لأنَّ المسارَ لم
 *   يُطلق قطُّ — لا لأنَّه مهجور. فالجردُ **بقراءةِ الشيفرةِ والمخطَّط** لا بالعدّ.
 *
 * التشغيل: php tools/injint01/dlq_inventory.php
 * الخروج : 0 خلوٌّ مُثبَت · 1 ثمّةَ اعتمادٌ يمنع النزع
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8'); mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$h = ems_env('DB_HOST'); $p = 3306;
if (strpos($h, ':') !== false) { list($h, $p) = explode(':', $h); $p = (int) $p; }
$c = new mysqli($h, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $p);
if ($c->connect_errno) { exit('تعذّر الاتصال: ' . $c->connect_error . "\n"); }
$c->set_charset('utf8mb4'); $DB = ems_env('DB_NAME');
$rows = function ($q) use ($c) { $r = $c->query($q); $o = array(); if (!$r) { return $o; } while ($x = $r->fetch_assoc()) { $o[] = $x; } return $o; };
$one  = function ($q) use ($c) { $r = $c->query($q); if (!$r) { return null; } $x = $r->fetch_row(); return $x ? $x[0] : null; };

/** مسحُ الشيفرةِ الحيّةِ — والأدواتُ والوثائقُ خارجَ المدى: لا تُشغَّل في الإنتاج. */
$scan = function ($pattern) use ($ROOT) {
    $out = array();
    $dirs = array('App', 'app', 'includes', 'Operations', 'Finance', 'main', 'Suppliers', 'Clients', 'Contracts');
    $files = array();
    foreach ($dirs as $d) {
        if (!is_dir("$ROOT/$d")) { continue; }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$ROOT/$d", FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) { if (substr($f, -4) === '.php') { $files[] = (string) $f; } }
    }
    foreach (glob("$ROOT/*.php") as $f) { $files[] = $f; }
    $seen = array();
    foreach ($files as $f) {
        $real = str_replace('\\', '/', $f);
        if (isset($seen[strtolower($real)])) { continue; }
        $seen[strtolower($real)] = 1;
        $src = (string) @file_get_contents($f);
        foreach (explode("\n", $src) as $n => $ln) {
            if (preg_match('~^\s*(\*|//|#)~', $ln)) { continue; }
            if (preg_match($pattern, $ln)) {
                $out[] = array('file' => str_replace($ROOT . '/', '', $real), 'line' => $n + 1, 'text' => trim(mb_substr(trim($ln), 0, 110)));
            }
        }
    }
    return $out;
};

$verdicts = array(); $blockers = 0;
$check = function ($no, $title, $hits, $ruling, $note) use (&$verdicts, &$blockers) {
    $bad = ($ruling === 'BLOCKER');
    if ($bad) { $blockers++; }
    $verdicts[] = array('no' => $no, 'title' => $title, 'count' => count($hits), 'ruling' => $ruling, 'note' => $note, 'hits' => $hits);
};

echo "══ جردُ الاعتماداتِ الخمسة — EXE-01 §3·1 ══\n\n";

/* ── ① عاملٌ يعتمد على اختفاءِ الصفِّ بعد الموت ── */
/* ⛔ **وحذفانِ لا حذفٌ واحد**: `EventDispatcher` يحذف الصفَّ **عند النجاحِ أيضًا**
      (:165) لا عند الموتِ وحدَه (:139). فالجدولُ عنده **عدّادُ محاولاتٍ عابرٌ**
      لا دفترَ حالات — والأمرُ يعالج حذفَ الموتِ ولا يذكر حذفَ النجاح.
      ⇐ فنزعُ أحدِهما وحدَه يترك الكاتبَ في نصفِ حال. */
$h1 = $scan('~DELETE\s+FROM\s+`?ems_event_deliveries~i');
$check(1, 'عاملٌ يعتمد على اختفاءِ الصفّ', $h1,
    count($h1) > 1 ? 'BLOCKER' : 'CLEARABLE',
    count($h1) === 1 ? 'موضعُ حذفٍ واحدٌ فقط — وهو الذي يُنزَع.'
                     : (count($h1) === 0 ? 'لا حذفَ إطلاقًا'
                     : 'حذفانِ في الكاتبِ نفسِه: عند **الموت** وعند **النجاح**. '
                     . 'فالصفُّ عنده حالةٌ عابرةٌ بالتصميم — ونزعُ حذفِ الموتِ وحدَه يوقف تصفيرَ العدّاد.'));

/* ── ② تقريرٌ يحسب من الجدولِ القديم ── */
$h2 = $scan('~ems_event_dead_letter~i');
$live2 = array();
foreach ($h2 as $x) { if (!preg_match('~INSERT~i', $x['text'])) { $live2[] = $x; } }
$check(2, 'قارئٌ يحسب من جدولِ الرسائلِ الميتة', $live2,
    count($live2) > 0 ? 'REVIEW' : 'CLEARABLE',
    count($live2) === 0 ? 'لا قارئَ في الشيفرةِ الحيّة — الجدولُ يُكتَب ولا يُقرَأ.'
                        : 'ثمّةَ قارئٌ — يُحوَّل إلى سجلِّ التسليم.');

/* ── ③ مهمّةُ تنظيفٍ تعتمد على غيابِه ── */
$h3 = $scan('~(purge|cleanup|prune|تنظيف|كنس).{0,80}deliver~i');
$check(3, 'مهمّةُ تنظيفٍ تعتمد على غيابِ الصفّ', $h3,
    count($h3) > 0 ? 'REVIEW' : 'CLEARABLE',
    count($h3) === 0 ? 'لا مهمّةَ تنظيفٍ تمسُّ التسليمات.' : 'مهمّةٌ تحتاج قراءة.');

/* ── ④ استعلامُ إعادةٍ قد يلتقط صفًّا ميتًا أبدًا — أخطرُها ──
   ⛔ **ولا يُقاس الحارسُ في المستودعِ كلِّه بل في الكاتبِ الذي يُعدَّل**: للنظامِ
      ناقلانِ من جيلَين — `EventDeliveryWorker` فيه حارسُ `state='dlq'`،
      و`EventDispatcher` **لا حارسَ فيه**. فمن مسح الاثنينِ معًا أخرج أخضرَ
      كاذبًا: وجد الحارسَ عند مَن لا يُعدَّل، وأعفى مَن يُعدَّل. */
$writerFile = $ROOT . '/App/Core/EventDispatcher.php';
$writerSrc  = (string) @file_get_contents($writerFile);
$h4 = $scan('~INSERT\s+INTO\s+`?ems_event_deliveries~i');
$reclaim = $scan('~ON\s+DUPLICATE\s+KEY\s+UPDATE\s+`?attempts~i');
$guardInWriter = preg_match("~state\s*(=|<>|!=|NOT\s+IN|IN)\s*.{0,24}dlq~i", $writerSrc) > 0;
$h4all = array_merge($h4, $reclaim);
$check(4, 'التقاطُ صفٍّ بحالةٍ نهائيّةٍ ثانيةً', $h4all,
    $guardInWriter ? 'CLEARABLE' : 'BLOCKER',
    $guardInWriter
        ? 'حارسُ الحالةِ النهائيّةِ موجودٌ في الكاتبِ المُعدَّل.'
        : '⛔ **لا حارسَ حالةٍ نهائيّةٍ في `EventDispatcher`**: عدّادُ المحاولاتِ يُزاد بـON DUPLICATE '
        . 'على القيدِ الفريد، فالصفُّ الباقي يُلتقَط ويقفز فوقَ السقفِ في كلِّ دورة. '
        . '(والحارسُ موجودٌ في `EventDeliveryWorker` — وهو ناقلٌ آخرُ لا يُعدَّل هنا.)');

/* ── ⑤ مفتاحٌ أجنبيٌّ أو قيدُ تفرّدٍ يتأثر ببقاءِ الصفّ ── */
$fk = $rows("SELECT CONSTRAINT_NAME cn, TABLE_NAME t, REFERENCED_TABLE_NAME rt
               FROM information_schema.KEY_COLUMN_USAGE
              WHERE TABLE_SCHEMA='$DB' AND REFERENCED_TABLE_NAME='ems_event_deliveries'");
$uq = $rows("SELECT INDEX_NAME i, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) cols
               FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA='$DB' AND TABLE_NAME='ems_event_deliveries' AND NON_UNIQUE=0 AND INDEX_NAME<>'PRIMARY'
              GROUP BY i");
$h5 = array();
foreach ($fk as $x) { $h5[] = array('file' => 'المخطَّط', 'line' => 0, 'text' => "مفتاحٌ أجنبيٌّ {$x['cn']} من {$x['t']}"); }
foreach ($uq as $x) { $h5[] = array('file' => 'المخطَّط', 'line' => 0, 'text' => "قيدُ فرادةٍ {$x['i']} ({$x['cols']})"); }
$check(5, 'مفتاحٌ أجنبيٌّ أو قيدُ تفرّدٍ يتأثر بالبقاء', $h5,
    'REVIEW',
    'قيدُ `uq_legacy_consumer_event(consumer,event_id)` **هو محورُ المسألة**: الحذفُ يُحرّر الخانةَ '
    . 'ويُصفِّر العدّاد. وبقاءُ الصفِّ يمنع إدراجًا جديدًا — وهذا مقصودٌ **متى وُجد الحارس**. '
    . (count($fk) ? 'وثمّةَ مفتاحٌ أجنبيٌّ يشير إليه.' : 'ولا مفتاحَ أجنبيًّا يشير إليه — فالبقاءُ لا يُيتِّم شيئًا.'));

/* ═══ العرض ══════════════════════════════════════════════════════════ */
foreach ($verdicts as $v) {
    $icon = $v['ruling'] === 'BLOCKER' ? '⛔' : ($v['ruling'] === 'REVIEW' ? '⚠' : '✔');
    printf("%s ⓘ%d %-42s [%s] %d موضعًا\n", $icon, $v['no'], $v['title'], $v['ruling'], $v['count']);
    echo '     ' . $v['note'] . "\n";
    foreach (array_slice($v['hits'], 0, 4) as $x) { printf("     · %-44s%-6s %s\n", $x['file'], $x['line'] ? ':' . $x['line'] : '', $x['text']); }
    if (count($v['hits']) > 4) { printf("     … و%d موضعًا آخر\n", count($v['hits']) - 4); }
    echo "\n";
}

echo "══ الحكم ══\n";
printf("  موانعُ نزعِ الحذف: %d\n", $blockers);
if ($blockers === 0) { echo "  ✔ الخلوُّ مُثبَت — يجوز نزعُ الحذفِ بعدَ إضافةِ حارسِ الحالةِ النهائيّة.\n"; }
else { echo "  ⛔ لا يُنزَع الحذفُ قبل رفعِ الموانع.\n"; }
file_put_contents($ROOT . '/docs/injint01/dlq_inventory.json', json_encode($verdicts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "  التفصيل: docs/injint01/dlq_inventory.json\n";
exit($blockers === 0 ? 0 : 1);
