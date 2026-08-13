<?php
/**
 * tools/fix_bind_from_rationale.php — نقلُ الروابطِ من الحجّةِ المكتوبةِ إلى الوسم
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ البندُ الحاجبُ في تكليفِ حملةِ الأدلة (2026-08-13)
 *
 * ── ما تفعله ولا تفعله ─────────────────────────────────────────────────────
 * المصفوفةُ المثبَّتةُ `$CLOSED` في `tools/fix_status_report.php` ليست قائمةَ
 * أسماءٍ عاريةً: كلُّ معرِّفٍ فيها **مسبوقٌ بكتلةِ حجّةٍ مكتوبةٍ تُسمّي شاهدَه**
 * («الشاهد: `tests/x_test.php` 15/15»). فالربطُ موجودٌ بالفعلِ — لكنَّه في نثرٍ
 * لا يقرؤه قياس.
 *
 * فهذه الأداةُ **تنقله نقلًا** إلى الوسمِ الآليِّ في ترويسةِ الفاحصِ نفسِه:
 *     ⇐ شواهدُ أحكامٍ: INJ-####
 * ولا تخترع ربطًا واحدًا: ما لم تُسمِّ كتلتُه شاهدًا **يُترك بلا وسمٍ ويُعلَن**.
 *
 * ── والقيودُ التي تمنع الخضرةَ الكاذبة ──────────────────────────────────────
 * ◆ **الشاهدُ يجب أن يكون ملفًّا قائمًا** — مسارٌ في نثرٍ لملفٍّ محذوفٍ يُرفض.
 * ◆ **ولا يُقبل ملفُّ عُدَّةٍ** (المسبوقُ بشرطةٍ سفلية): `_dues_source_guard.php`
 *   مُقرِّرٌ مشترَكٌ **يُطوى** في فواحصَ ولا يُشغَّل بنفسِه، فلا يصلح شاهدًا
 *   مستقلًّا — ويُنقل الوسمُ إلى مُناديه.
 * ◆ **ولا يُلمَس سطرٌ برمجيّ**: الإضافةُ في ترويسةِ التعليقِ حصرًا، ويُفحص
 *   `php -l` بعد كلِّ ملفٍّ ويُستعاد الأصلُ عند أيِّ كسر.
 * ◆ **وعاطلةٌ**: معرِّفٌ موسومٌ سلفًا لا يُعاد وسمُه.
 *
 * التشغيل: php tools/fix_bind_from_rationale.php [--dry-run]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
require_once $ROOT . '/includes/fix_closure_source.php';
$DRY = in_array('--dry-run', $argv, true);

/* ── ① استخراجُ (كتلةُ حجّةٍ ⇒ معرِّفات) من المصفوفة ────────────────────────── */
$statusTool = $ROOT . '/tools/fix_status_report.php';
$src = (string) file_get_contents($statusTool);
$s = strpos($src, '$CLOSED = array(');
$e = strpos($src, "\n);", $s);
if ($s === false || $e === false) { exit("✘ تعذّر تحديدُ المصفوفة\n"); }
$blk = substr($src, $s, $e - $s);

/* تُقسَّم المصفوفةُ إلى مقاطعَ: كلُّ مقطعٍ نثرٌ ثم معرِّفاتُه المقتبَسة.
   والمقطعُ ينتهي عند أوّلِ سطرٍ يبدأ بتعليقٍ جديدٍ `/*` بعد معرِّفٍ. */
$lines = preg_split('~\r?\n~', $blk);
$segments = array();
$cur = array('prose' => '', 'ids' => array());
$sawId = false;
foreach ($lines as $ln) {
    /* الفصلُ على **كلا نوعَي التعليق**: كانت القسمةُ على `/*` وحدَه، فانضمّت
       أربعُ حجَجٍ مفصولةٍ بـ`//` في مقطعٍ واحدٍ — فورث تسعةُ معرِّفاتٍ **أُغلقت
       بحكمِ وثيقةٍ لا بمِسبار** شاهدًا لم يُعلَن لها. */
    $lt = ltrim($ln);
    $isComment = (strpos($lt, '/*') === 0 || strpos($lt, '//') === 0);
    if ($isComment && $sawId) {
        $segments[] = $cur;
        $cur = array('prose' => '', 'ids' => array());
        $sawId = false;
    }
    if (preg_match_all("~'(INJ-\\d{4})'~", $ln, $m)) {
        foreach ($m[1] as $id) { $cur['ids'][] = $id; }
        $sawId = true;
    } else {
        $cur['prose'] .= $ln . "\n";
    }
}
if ($cur['ids']) { $segments[] = $cur; }

/* ── ② لكلِّ مقطعٍ: الشاهدُ المسمّى فيه ────────────────────────────────────── */
$bind = array();          // ملفٌّ ⇒ معرِّفات
$unbound = array();       // معرِّفٌ ⇒ سببُ عدمِ الربط
foreach ($segments as $seg) {
    if (!$seg['ids']) { continue; }
    /* ── **«الشاهد:» صراحةً لا القربُ** ────────────────────────────────────
       أوّلُ صياغةٍ أخذت كلَّ مسارِ فاحصٍ يقع في الكتلة، فوسمت
       `tools/sec01_deliverables.php` بستةَ عشرَ معرِّفًا — وهو **مُولِّدُ
       مُخرَجاتٍ** ذُكر عرَضًا («أُعيد التوليدُ به») لا شاهدٌ لشيء. والقربُ في
       النثرِ ليس شهادة؛ فلو أُفسد ما يشترطه أيُّ حكمٍ منها لبقي المُولِّدُ
       أخضرَ. فصار المعيارُ **إعلانَ الكاتبِ**: كلمةُ «الشاهد» ثم المسار. */
    /* ◆ ويُقبل **لفظُ الإعلانِ** بصيغتيه المستعملتين في هذا الملف: «الشاهد: …»
         و«اختبارٌ في …». ويُسمح بعبورِ السطرِ (120 محرفًا) لأنَّ المسارَ قد يقع
         في السطرِ التالي داخلَ الكتلةِ نفسِها — والكتلُ مفصولةٌ بأسطرِ المعرِّفات
         فلا يتسرّب الوسمُ إلى كتلةٍ مجاورة. */
    preg_match_all('~(?:الشاهد|اختبارٌ في|اختبار في)[\s\S]{0,120}?((?:tests|tools)/[A-Za-z0-9_]+\.php)~u',
                   $seg['prose'], $m);
    $cands = array_values(array_unique($m[1]));
    $good = array();
    foreach ($cands as $rel) {
        $bn = basename($rel);
        if ($bn[0] === '_') { continue; }                    // عُدَّةٌ تُطوى لا تُشغَّل
        if (!is_file($ROOT . '/' . $rel)) { continue; }       // مسارٌ لملفٍّ غيرِ قائم
        $good[] = $rel;
    }
    foreach ($seg['ids'] as $id) {
        if (!$good) {
            $unbound[$id] = $cands
                ? ('الشواهدُ المسمّاةُ غيرُ صالحةٍ (' . implode(' · ', $cands) . ')')
                : 'كتلةُ الحجّةِ لا تُسمّي شاهدًا';
            continue;
        }
        foreach ($good as $rel) {
            if (!isset($bind[$rel])) { $bind[$rel] = array(); }
            if (!in_array($id, $bind[$rel], true)) { $bind[$rel][] = $id; }
        }
    }
}

echo "══ نقلُ الروابطِ من الحجّةِ المكتوبةِ إلى الوسم\n\n";
echo "  مقاطعُ حجّةٍ مقروءة : " . count($segments) . "\n";
echo "  فواحصُ ستُوسَم      : " . count($bind) . "\n";
echo "  معرِّفاتٌ بلا شاهدٍ مسمّى : " . count($unbound) . "\n\n";

/* ── ③ الوسمُ ─────────────────────────────────────────────────────────────── */
$already = ems_fix_mentions($ROOT);
$stamped = 0; $skipped = 0; $broken = array();
foreach ($bind as $rel => $ids) {
    $path = $ROOT . '/' . $rel;
    $orig = (string) file_get_contents($path);
    /* ما ليس موسومًا في هذا الملفِّ بعدُ */
    $add = array();
    foreach ($ids as $id) {
        if (isset($already[$id]) && in_array(str_replace('\\', '/', $path), $already[$id], true)) { continue; }
        $add[] = $id;
    }
    if (!$add) { $skipped++; continue; }

    if (preg_match('~شواهدُ أحكامٍ\s*:[^\r\n]*~u', $orig, $m, PREG_OFFSET_CAPTURE)) {
        /* ── وسمٌ قائمٌ: تُضاف المعرِّفاتُ في **سطرٍ تالٍ** لا داخلَ السطر ──────
             أوّلُ صياغةٍ ألحقتها داخلَ السطرِ الأوّلِ فأنتجت `· ·` وتركت سطرَ
             المتابعةِ يتيمًا في وسمٍ يمتدُّ سطرين. والإلحاقُ بسطرٍ جديدٍ يعمل
             في الحالين، والقارئُ يمسح 6000 بايتٍ فيلتقطه. */
        $endLine = strpos($orig, "\n", $m[0][1]);
        if ($endLine === false) { $endLine = strlen($orig); }
        $cont = " *                  " . implode(' · ', $add) . "\n";
        $new = substr($orig, 0, $endLine + 1) . $cont . substr($orig, $endLine + 1);
    } else {
        /* لا وسمَ — يُدرَج بعد أوّلِ سطرِ عنوانٍ في ترويسةِ التعليق */
        $pos = strpos($orig, "\n * ");
        if ($pos === false) { $unbound[implode(',', $add)] = 'لا ترويسةَ تعليقٍ في ' . $rel; continue; }
        $eol = strpos($orig, "\n", $pos + 1);
        $tag = " *\n * ⇐ شواهدُ أحكامٍ: " . implode(' · ', $add) . "\n";
        $new = substr($orig, 0, $eol + 1) . $tag . substr($orig, $eol + 1);
    }

    if ($DRY) { printf("   ○ %-46s + %s\n", $rel, implode(' · ', $add)); $stamped++; continue; }
    file_put_contents($path, $new);
    /* فحصُ الصياغةِ واستعادةٌ عند أيِّ كسر */
    $o = array(); $c = 1;
    @exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $o, $c);
    if ((int) $c !== 0) {
        file_put_contents($path, $orig);
        $broken[] = $rel;
        continue;
    }
    printf("   ✔ %-46s + %s\n", $rel, implode(' · ', $add));
    $stamped++;
}

echo "\n── الحصيلة\n";
echo "  فواحصُ وُسِمت : {$stamped}" . ($DRY ? '  (جسٌّ فقط)' : '') . "\n";
echo "  موسومةٌ سلفًا : {$skipped}\n";
if ($broken) { echo "  ✘ كُسِرت فاستُعيدت : " . implode(' · ', $broken) . "\n"; }

if ($unbound) {
    echo "\n── معرِّفاتٌ **لا تُوسَم** — حجّتُها لا تُسمّي شاهدًا صالحًا (" . count($unbound) . ")\n";
    foreach ($unbound as $id => $why) { printf("   %-11s %s\n", $id, $why); }
    echo "\n  ◆ هذه تنزل إلى «غيرُ مقيس» — فإعلانٌ بلا شاهدٍ ليس إغلاقًا (CL-01).\n";
}
exit($broken ? 1 : 0);
