<?php
/**
 * tools/guard_adoption_gate.php — بوابةُ **تبنّي** الحرّاس (INJ-0062)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ العيبُ المقياسيُّ الذي تُغلقه (MD-05): «عيبٌ أُعلن مغلقًا **ببناءِ الحارسِ
 *   لا بتطبيقِه**». والسلّمُ رباعيّ:
 *     L1 مبنيٌّ ومحقَّق · L2 موصولٌ بمسارٍ واحد · L3 **مُتبنًّى في الإنتاج**
 *     · L4 محروسٌ بفاحصٍ آليٍّ يمنع الردّة.
 *   ولا إغلاقَ قبلَ L3 — وهذه البوابةُ هي L4.
 *
 * ◆ ما تقيسه: لكلِّ صنفِ حارسٍ في مجلداتِ الحراسة — **كم ملفَّ إنتاجٍ يستهلكه
 *   فعلًا**. والاستهلاكُ **نداءٌ ثابتٌ أو إنشاءُ كائنٍ أو تضمينٌ صريح** يُلتقط
 *   بالتوسيمِ النحويِّ لا بمطابقةِ نصّ — فذكرُ الاسمِ في تعليقٍ ليس تبنّيًا،
 *   وهذا بالضبط ما جعل أربعةَ حرّاسٍ تبدو «حيّة» وهي بصفرِ نداء.
 *
 * ◆ ما لا تقيسه: **صوابَ موضعِ النداء** (أنُودي قبلَ الأثرِ أم بعدَه؟) — ذاك
 *   لفاحصِ الترتيب AC-F2؛ ولا **تغطيةَ المسارات** (أنُودي في كلِّ مسارٍ يلزمه؟).
 *   فالبوابةُ تُثبت الحياةَ لا الكفاية.
 *
 * التشغيل:
 *   php tools/guard_adoption_gate.php            → التقرير
 *   php tools/guard_adoption_gate.php --md=مسار  → تقريرُ Markdown
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
require_once __DIR__ . '/fix_lib.php';

$mdOut = null;
foreach ($argv as $a) { if (strpos($a, '--md=') === 0) { $mdOut = substr($a, 5); } }

/** مجلداتُ الحراسةِ — كلُّ صنفٍ فيها حارسٌ يجب أن يُتبنّى. */
$GUARD_DIRS = array(
    'app/Services/Security',
    'app/Services/Governance',
    'app/Services/Audit',
    'app/Core',
);

/**
 * أصنافٌ ليست حرّاسًا: بنيةٌ تحتيةٌ يستهلكها الإطارُ لا كودُ الشاشات، أو
 * استثناءاتٌ وقيمٌ. **تُعلَن بالاسمِ ولا تُستثنى بنمطٍ فضفاض.**
 */
$NOT_GUARDS = array(
    'TenantContext', 'TenantGateException', 'TenantRegistry',
);

/* ── ① جمعُ أصنافِ الحراسة ─────────────────────────────────────────────── */
$guards = array();
foreach ($GUARD_DIRS as $d) {
    foreach (glob($ROOT . '/' . $d . '/*.php') as $abs) {
        $src = (string) @file_get_contents($abs);
        if ($src === '' || !preg_match('/^\s*(final\s+|abstract\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)/m', $src, $m)) { continue; }
        $cls = $m[2];
        if (in_array($cls, $NOT_GUARDS, true)) { continue; }
        $rel = str_replace('\\', '/', substr($abs, strlen($ROOT) + 1));
        $guards[$cls] = array('file' => $rel, 'consumers' => array(), 'methods' => array());
        // الدوالُّ العامةُ — لقياسِ «صنفٌ حيٌّ ودالةٌ ميتة»
        if (preg_match_all('/public\s+(?:static\s+)?function\s+([A-Za-z_][A-Za-z0-9_]*)/', $src, $fm)) {
            foreach ($fm[1] as $fn) {
                if ($fn === '__construct') { continue; }
                $guards[$cls]['methods'][$fn] = 0;
            }
        }
    }
}

/* ── ② مسحُ كودِ الإنتاج عن مستهلكين حقيقيين ────────────────────────── */
$isProduction = function ($rel) {
    foreach (array('tools/', 'tests/', 'docs/', 'database/') as $p) {
        if (strpos($rel, $p) === 0) { return false; }
    }
    return true;
};

foreach (fix_php_files($ROOT) as $rel) {
    if (!$isProduction($rel)) { continue; }
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    if ($src === '') { continue; }
    // ◆ التعليقاتُ تُفرَّغ — فذكرُ الحارسِ في شرحٍ ليس تبنّيًا.
    $code = fix_strip_comments($src);
    foreach ($guards as $cls => $info) {
        if ($info['file'] === $rel) {
            /* ◆ گوتشا التقطها الفحصُ العكسيُّ لهذه البوابةِ نفسِها: الصنفُ لا
               يُحسب مستهلكَ نفسِه (وهذا صحيح) — لكنّ **دوالَّه** قد تُنادى داخلَه
               بـ`self::`، فعُدَّت ميتةً وهي حية (`FieldGovernor::sensitiveFieldsOf`
               ينادِيها `exportableColumns` في الملفِّ نفسِه). فالنداءُ الداخليُّ
               يُحتسب للدالةِ ولا يُحتسب للصنف. */
            if (preg_match_all('/\b(?:self|static)\s*::\s*([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $code, $sm)) {
                foreach ($sm[1] as $fn) {
                    if (isset($guards[$cls]['methods'][$fn])) { $guards[$cls]['methods'][$fn]++; }
                }
            }
            continue;
        }
        /* ══ الأسماءُ المستعارةُ تُحلُّ قبل العدّ ══════════════════════════════
           `use App\…\GovernanceM14Service as M14;` ثم `M14::decideApproval(…)`
           تبنٍّ كامل، والعدُّ بالاسمِ الصريحِ وحدَه يراه صفرًا. أعلنت البوابةُ
           صنفَين «بلا مستهلك» وكلاهما مُتبنًّى في شاشتِه منذ زمن — والفرقُ بين
           عيبٍ حقيقيٍّ وعيبٍ في المقياسِ هو ما يُهدر عليه العمل.
           ◆ ويُستخرج الاسمُ المستعارُ من الكودِ المنزوعِ التعليقِ لا من النصِّ
             الخام، فسطرُ `use` في شرحٍ ليس استيرادًا. */
        $names = array($cls);
        if (preg_match_all('/\buse\s+[A-Za-z0-9_\\\\]*\\\\' . preg_quote($cls, '/')
                           . '\s+as\s+([A-Za-z_][A-Za-z0-9_]*)\s*;/i', $code, $am)) {
            foreach ($am[1] as $alias) { $names[] = $alias; }
        }

        // نداءٌ ثابتٌ  Cls::method(  ·  إنشاءُ كائنٍ  new Cls(
        $used = false;
        foreach (array_unique($names) as $nm) {
            if (preg_match_all('/\b' . preg_quote($nm, '/') . '\s*::\s*([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $code, $cm)) {
                $used = true;
                foreach ($cm[1] as $fn) {
                    if (isset($guards[$cls]['methods'][$fn])) { $guards[$cls]['methods'][$fn]++; }
                }
            }
            if (preg_match('/\bnew\s+(?:\\\\[A-Za-z0-9_\\\\]*)?' . preg_quote($nm, '/') . '\s*\(/', $code)) { $used = true; }
        }
        if ($used) { $guards[$cls]['consumers'][] = $rel; }
    }
}

/* ── ③ الحكم ──────────────────────────────────────────────────────────── */
$dead = array(); $live = array(); $deadMethods = array();
foreach ($guards as $cls => $info) {
    $n = count($info['consumers']);
    if ($n === 0) { $dead[$cls] = $info; } else { $live[$cls] = $info; }
    foreach ($info['methods'] as $fn => $calls) {
        if ($calls === 0 && $n > 0) { $deadMethods[] = $cls . '::' . $fn; }
    }
}

echo "══════════════════════════════════════════════════════════════════════\n";
echo " بوابةُ تبنّي الحرّاس — «المبنيُّ الذي لا يُنادى كالمعدوم» (INJ-0062)\n";
echo " " . date('Y-m-d H:i') . " · مجلداتُ الحراسة: " . implode(' · ', $GUARD_DIRS) . "\n";
echo "══════════════════════════════════════════════════════════════════════\n\n";

printf("حرّاسٌ مفحوصون ......... %d\n", count($guards));
printf("مُتبنَّون (L3) ........... %d\n", count($live));
printf("◆ بصفرِ مستهلك (L1) .... %d\n\n", count($dead));

if ($dead) {
    echo "◆ حرّاسٌ مبنيّون ولا يُنادَون — **لا يُعلَن إغلاقُ عيبٍ يحرسه أيٌّ منها**:\n";
    foreach ($dead as $cls => $info) {
        echo '  ✘ ' . $cls . '  (' . $info['file'] . ')  دوالُّه: ' . count($info['methods']) . "\n";
    }
    echo "\n";
}

/* ═══════════════════════════════════════════════════════════════════════════
 * سقّاطةُ الدوالِّ الميتة — INJ-0239
 * ═══════════════════════════════════════════════════════════════════════════
 * **العطبُ المقيس**: كانت البوابةُ تسرد أربعينَ دالةَ حراسةٍ ميتةً ثم تختم
 * بـ«✔ صفرُ حارسٍ بصفرِ مستهلك — كلُّ حارسٍ مبنيٍّ مُتبنًّى في الإنتاج (L3)»
 * وتخرج بصفر. فالجسمُ صادقٌ **والختمُ يُضلِّل**: العدُّ على مستوى **الصنفِ**
 * (30/30 متبنًّى) والموتُ على مستوى **الدالة** (40 دالةً لا يناديها إنتاجٌ).
 * وMD-05 يقول «البناءُ ليس تبنّيًا» — ودالةُ حراسةٍ بصفرِ مستهلكٍ **لا تحرس أحدًا**.
 *
 * ◆ **ولا يُحجَب أربعونَ فجأةً**: ذلك يُوقف خطَّ التسليمِ بلا خطةِ تبنٍّ، فيُلتفُّ
 *   عليه بتعطيلِ البوابةِ — وهو أسوأُ من العطب. فالعلاجُ **سقّاطةٌ**: يُثبَّت
 *   الخطُّ القائمُ في ملفٍّ مُعلَنٍ، وتُحجَب البوابةُ **إن نما العددُ** أو ظهر
 *   اسمٌ جديدٌ. فالنزيفُ يتوقّف اليومَ، والتبنّي يمضي بخطة.
 * ◆ والختمُ صار يقول الحقيقتين معًا: الأصنافُ والدوالُّ.
 * ═══════════════════════════════════════════════════════════════════════════ */
/* ◆ **مخرَجٌ صريحٌ بدلَ تحليلِ نصٍّ**: أوّلُ توليدٍ للخطِّ قرأ مخرَجَ البوابةِ
     بتعبيرٍ نمطيٍّ فأخرجَ **صفرَ اسمٍ** من تسعةٍ وأربعين — والسردُ محدودٌ بعشرين
     أصلًا. فمن يُولّد الخطَّ يقرأ القائمةَ من مصدرِها لا من زخرفةِ العرض. */
if (in_array('--list-dead', $argv, true)) {
    foreach ($deadMethods as $m) { echo $m . "\n"; }
    exit(0);
}
$RATCHET = dirname(__DIR__) . '/docs/fix_2026-08/guard_dead_methods_baseline.txt';
$baseNames = array();
if (is_file($RATCHET)) {
    foreach (file($RATCHET, FILE_IGNORE_NEW_LINES) as $l) {
        $l = trim($l);
        if ($l !== '' && strpos($l, '#') !== 0) { $baseNames[$l] = true; }
    }
}
$newDead = array();
foreach ($deadMethods as $m) { if (!isset($baseNames[$m])) { $newDead[] = $m; } }
$revived = array();
foreach (array_keys($baseNames) as $b) { if (!in_array($b, $deadMethods, true)) { $revived[] = $b; } }

if ($deadMethods) {
    echo '◆ دوالُّ حراسةٍ ميتةٌ داخلَ أصنافٍ حية — ' . count($deadMethods)
       . ' (خطُّ السقّاطةِ ' . count($baseNames) . "):\n";
    foreach (array_slice($deadMethods, 0, 20) as $m) { echo '    · ' . $m . "\n"; }
    if (count($deadMethods) > 20) { echo '    … و' . (count($deadMethods) - 20) . " أخرى\n"; }
    echo "\n";
}
if ($revived) {
    echo '✔ تُبنِّيت بعد أن كانت ميتةً: ' . count($revived) . ' — '
       . implode(' · ', array_slice($revived, 0, 6)) . "\n";
    echo "   (أزِلها من خطِّ السقّاطةِ ليُحكِم الخطُّ على ما بقي)\n\n";
}
if ($newDead) {
    echo '✘ **دوالُّ حراسةٍ ميتةٌ جديدةٌ فوقَ خطِّ السقّاطة**: ' . count($newDead) . "\n";
    foreach ($newDead as $m) { echo '    · ' . $m . "\n"; }
    echo "   دالةُ حراسةٍ بصفرِ مستهلكٍ لا تحرس أحدًا — نادِها من الإنتاجِ أو أزِلها.\n\n";
}

if ($mdOut) {
    $md = "# بوابةُ تبنّي الحرّاس — " . date('Y-m-d H:i') . "\n\n"
        . '| الحارس | الملف | مستهلكوه | الحكم |' . "\n|---|---|---|---|\n";
    foreach ($guards as $cls => $info) {
        $n = count($info['consumers']);
        $md .= '| ' . $cls . ' | ' . $info['file'] . ' | ' . $n . ' | ' . ($n > 0 ? 'L3 ✔' : '**L1 ✘**') . " |\n";
    }
    @file_put_contents($mdOut, $md);
    echo "تقرير: {$mdOut}\n";
}

echo str_repeat('═', 70) . "\n";
if (!$dead && !$newDead) {
    /* ◆ الختمُ يقول **الحقيقتين معًا**: كان يقول «كلُّ حارسٍ متبنًّى» وأربعون
         دالةً ميتةٌ تحته — فصار يُعلن مستوى الصنفِ ومستوى الدالةِ صراحةً. */
    echo '✔ صفرُ **صنفِ** حراسةٍ بصفرِ مستهلك — كلُّ صنفٍ مبنيٍّ مُتبنًّى (L3)' . "\n";
    if ($deadMethods) {
        echo '○ وعلى مستوى **الدالة**: ' . count($deadMethods)
           . ' دالةَ حراسةٍ ميتةٌ ضمنَ خطِّ السقّاطةِ — لا نموَّ، والتبنّي بخطة.' . "\n";
    } else {
        echo '✔ وعلى مستوى الدالةِ كذلك: صفرُ دالةِ حراسةٍ ميتة.' . "\n";
    }
    echo str_repeat('═', 70) . "\n";
    exit(0);
}
if ($newDead && !$dead) {
    echo '✘ ' . count($newDead) . " دالةَ حراسةٍ ميتةً **جديدةً** فوقَ خطِّ السقّاطة — والبناءُ ليس تبنّيًا\n";
    echo str_repeat('═', 70) . "\n";
    exit(1);
}
echo '✘ ' . count($dead) . " حارسًا مبنيًّا بلا مستهلكٍ واحد — والبناءُ ليس تبنّيًا\n";
echo str_repeat('═', 70) . "\n";
exit(1);
