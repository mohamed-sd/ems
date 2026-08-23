<?php
/**
 * tools/injfrd66_evidence_belt.php — حزامُ شواهدِ INJ-FRD-01: دعوى بلا دليلٍ تُرصَد
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الدفترُ يُصدَّق على نفسِه — وهذا ما يجب ألّا يبقى**: كلُّ «مُختبَر: نعم»
 *   في `injfrd66_tasks.json` **دعوى**، ولا شيءَ في الأداةِ يربطها بدليل.
 *   فيمكن أن يُوسَم متطلبٌ مُختبَرًا **ولا شاهدَ في الشجرةِ يذكره**.
 *
 * ◆ **والحكمُ ثنائيُّ الاتجاه** — كما في حزامِ الحزمةِ الأخرى:
 *   ① **دعوى بلا دليل**: `tested = نعم` ولا بوابةَ ولا شاهدَ يسمّي رمزَه ⇐ خلل.
 *   ② **دليلٌ بلا دعوى**: رمزٌ يذكره شاهدٌ ولا وجودَ له في الدفترِ ⇐ خلل
 *      (رمزٌ مخترَعٌ أو مطبعةٌ في اسمٍ — وكلاهما يُفسد العدّ).
 *
 * ◆ **والذكرُ يُقاس في الشفرةِ لا في المخرَج**: مخرَجُ البوابةِ يتغيّر بالبيانات،
 *   والشفرةُ ثابتة. فيُكنَس نصُّ `tools/injfrd66_*.php` و`tests/injfrd66_*.php`
 *   بحثًا عن الرمزِ **بحدِّه** (`SAL-04` لا `SAL-0`).
 *
 * ◆ **و«مذكورٌ» ليس «مقيسًا»** — وهذا الحزامُ لا يدَّعي غيرَ ما يقيس: يقيس
 *   **وجودَ دليلٍ يسمّي المتطلب**، لا صحّةَ ما يقوله عنه. وصحّةُ القياسِ
 *   مسؤوليةُ البوابةِ نفسِها وشاهدِها السالب.
 *
 * ◆ قراءةٌ خالصة — لا قاعدةَ ولا كتابة.
 *
 * التشغيل:
 *   php tools/injfrd66_evidence_belt.php          التقرير
 *   php tools/injfrd66_evidence_belt.php --gate   رمزُ خروجٍ 1 عند خلل
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$GATE = in_array('--gate', $argv, true);

$tasks = json_decode((string) @file_get_contents($ROOT . '/tools/injfrd66_tasks.json'), true);
if (!is_array($tasks) || !$tasks) { exit("تعذّرت قراءةُ الدفتر\n"); }

/* ── ① الأدلّةُ: بواباتٌ وشواهدُ العائلة ───────────────────────────────── */
$EVID = array();
foreach (array('tools/injfrd66_*.php', 'tests/injfrd66_*.php') as $g) {
    foreach ((array) glob($ROOT . '/' . $g) as $f) {
        $rel = str_replace($ROOT . '/', '', str_replace('\\', '/', $f));
        /* الدفترُ وأداتُه ليسا دليلًا على أنفسِهما */
        if (strpos($rel, 'injfrd66_tasks') !== false
            || strpos($rel, 'injfrd66_task_progress') !== false
            || strpos($rel, 'injfrd66_evidence_belt') !== false) { continue; }
        $EVID[$rel] = (string) @file_get_contents($f);
    }
}

/* ── ② من يذكر مَن ─────────────────────────────────────────────────────── */
$mentions = array();          /* code => [file,…] */
$known    = array();          /* code => 1 */
foreach ($tasks as $t) { $known[$t['id']] = 1; }

foreach ($EVID as $rel => $src) {
    /* بحدِّ الرمزِ لا باحتوائه: `SAL-04` لا تُطابق `SAL-0` ولا `XSAL-04` */
    if (!preg_match_all('~(?<![A-Z0-9-])([A-Z]{2,3}-\d{2})(?![0-9])~', $src, $mm)) { continue; }
    foreach (array_unique($mm[1]) as $code) {
        if (!isset($mentions[$code])) { $mentions[$code] = array(); }
        $mentions[$code][] = $rel;
    }
}

echo "\n═══ INJ-FRD-01 · حزامُ الشواهد — دعوى بلا دليلٍ تُرصَد ═══\n";
printf("\n  أدلّةٌ مكنوسة: %d ملفًّا · متطلباتٌ في الدفتر: %d\n", count($EVID), count($tasks));

/* ── ③ دعوى بلا دليل ──────────────────────────────────────────────────── */
$claimNoProof = array(); $proven = array(); $notTested = array();
foreach ($tasks as $t) {
    $id  = $t['id'];
    $tst = (string) ($t['gates']['tested'] ?? '—');
    $has = isset($mentions[$id]) ? count(array_unique($mentions[$id])) : 0;
    if ($tst === 'نعم') {
        if ($has === 0) { $claimNoProof[] = $id; }
        else { $proven[$id] = $has; }
    } else {
        $notTested[$id] = $has;
    }
}

echo "\n  ── ① دعوى بلا دليل («مُختبَر: نعم» ولا دليلَ يسمّيه)\n";
if ($claimNoProof) {
    foreach ($claimNoProof as $c) { echo "     ✘ {$c}\n"; }
} else {
    printf("     ✔ صفرُ دعوى بلا دليل — و%d متطلبًا مُختبَرًا لكلٍّ دليلٌ يسمّيه\n", count($proven));
}

/* ── ④ دليلٌ بلا دعوى ─────────────────────────────────────────────────── */
echo "\n  ── ② دليلٌ بلا دعوى (رمزٌ في شاهدٍ لا وجودَ له في الدفتر)\n";
$orphan = array();
/* بادئاتُ الحزمةِ وحدَها — ورموزُ الحزمِ الأخرى (FR-JRN · GAP · LD) ليست دعوانا */
$OURS = array('SAL', 'SUP', 'XC');
foreach ($mentions as $code => $files) {
    $pfx = explode('-', $code)[0];
    if (!in_array($pfx, $OURS, true)) { continue; }
    if (!isset($known[$code])) { $orphan[$code] = array_unique($files); }
}
if ($orphan) {
    foreach ($orphan as $c => $files) {
        printf("     ✘ %s — %s\n", $c, implode('، ', array_slice($files, 0, 3)));
    }
} else {
    echo "     ✔ صفرُ رمزٍ مخترَعٍ في الأدلّة\n";
}

/* ── ⑤ خبرٌ لا خلل: متطلبٌ غيرُ مُختبَرٍ وله دليلٌ يذكره ─────────────────── */
$mentionedNotTested = array_filter($notTested);
if ($mentionedNotTested) {
    echo "\n  ── ◆ خبرٌ لا خلل: متطلبٌ **غيرُ مُختبَرٍ** يذكره دليل\n";
    foreach ($mentionedNotTested as $c => $n) {
        printf("     ○ %s — %d دليلًا يذكره ولم يُوسَم مُختبَرًا\n", $c, $n);
    }
    echo "       (ذِكرٌ في سياقٍ أو تعليلٍ ليس قياسًا — والوسمُ يبقى كما هو.)\n";
}

/* ── ⑥ الأكثرُ استشهادًا — خبرٌ يُعين على قراءةِ التغطية ────────────────── */
arsort($proven);
$top = array_slice($proven, 0, 5, true);
if ($top) {
    echo "\n  ── ◆ الأكثرُ استشهادًا\n";
    foreach ($top as $c => $n) { printf("     ○ %-8s %d دليلًا\n", $c, $n); }
}

$flaws = count($claimNoProof) + count($orphan);
printf("\n  مُثبَتٌ %d · دعوى بلا دليل %d · رمزٌ مخترَعٌ %d\n",
    count($proven), count($claimNoProof), count($orphan));
echo "\n  ◆ و«مذكورٌ» ليس «مقيسًا»: هذا الحزامُ يقيس **وجودَ دليلٍ يسمّي المتطلب**\n";
echo "    لا صحّةَ ما يقوله عنه — وصحّةُ القياسِ في البوابةِ وشاهدِها السالب.\n\n";

exit($GATE && $flaws > 0 ? 1 : 0);
