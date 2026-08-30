<?php
/**
 * tools/rpr03_perm_path_unify.php — `RPR-03` §٦ · توحيدُ مسارِ قرارِ الصلاحية
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — §٦: *«وحّدْ قرارَ الصلاحيةِ في **مصدرٍ واحدٍ يُستدعى
 *   من الخادم** — والقائمةُ تُشتقّ منه لا تُبنى موازيةً له»* · و§١٠:
 *   `مساراتُ قرارِ الصلاحية = واحد`، والمقيسُ **مساران و٨٨ قارئًا مستقلًّا**.
 *
 * ◆ **والفرقُ بين المسارَين مقيسٌ لا مظنون** — وهو **طبقةُ القوالب**:
 *   `get_module_permissions()` تُطبِّق **GOV-AUTH-01** (‏`gov_authority_grants`
 *   ⟵ `gov_role_profiles` ⟵ `gov_profile_items`): *«المستخدمُ المغطًّى بقالبٍ
 *   نافذٍ يُحكَم بقالبِه حصرًا — لا شاشةَ خارجَ القالب»*. **والقارئُ المستقلُّ
 *   يقرأ `role_permissions` خامًّا فلا يرى القالبَ ألبتّة.**
 *   ⇒ فـ**الشاشةُ تُخفى من السايدبار** (‏المُصيِّرُ على المسارِ المعياريّ)
 *   **وتُفتح بالرابطِ المباشر** — وهو حرفُ ما يحذّر منه §٦.
 *
 * ◆ **والاستبدالُ جراحيٌّ ويحفظ ما ليس محلَّ الخلاف**:
 *   ⛔ **فرعُ السوبر أدمن لا يُمَسّ**: الملفّاتُ تمنحه الثلاثةَ صراحةً،
 *      و`get_module_permissions()` **لا تستثنيه** ⇒ فاستبدالُ الفرعَين معًا
 *      **يسلبه شاشاتٍ يملكها** ([[module-perm-no-super-bypass]]). فيُستبدَل
 *      **جسمُ `else` وحدَه** — أي القرارُ محلُّ الخلافِ لا غيرُه.
 *   ⛔ **وأسماءُ المتغيّراتِ تبقى كما هي**: `$can_decide` · `$can_assess` …
 *      تُشتقُّ من مفتاحِها نفسِه (`can_add`/`can_edit`) — **فلا يتغيّر سطرٌ
 *      واحدٌ بعدَ الكتلة**.
 *
 * ⛔ **وشروطٌ صلبةٌ يرفض دونها** — ولا يُلمَس ملفٌّ لا يستوفيها:
 *   ① يشتمل `includes/permissions_helper.php` · ② فيه `$MODULE_CODE = '…'`
 *   · ③ فيه **كتلةٌ واحدةٌ** بالشكلِ المعياريِّ · ④ ربطُها `$MODULE_CODE`
 *   · ⑤ كلُّ إسنادٍ فيها من `$row['can_*']` — **ولا سطرَ لا يُفهَم**.
 *   · ⑥ والملفُّ يمرُّ `php -l` بعدَ الكتابةِ وإلّا **رُدَّ فورًا**.
 *
 * التشغيل:
 *   php tools/rpr03_perm_path_unify.php [--apply] [--md] [--selftest]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));

$APPLY = in_array('--apply', $argv, true);
$MD    = in_array('--md', $argv, true);
$SELF  = in_array('--selftest', $argv, true);

/** الكتلةُ المعياريّةُ — من `prepare` إلى `close()` · ⛔ ولا كتلةَ ثانيةٌ تُقبل */
define('PU_BLOCK', '~\$(\w+)\s*=\s*\$conn->prepare\(\s*"SELECT\s+rp\.can_view.*?"\s*\)\s*;'
                 . '.*?\$\1->close\(\)\s*;~s');

/**
 * إسناداتُ الكتلةِ — اسمُ المتغيّرِ ⇐ مفتاحُ الصفِّ · والباقي يُردُّ سببًا.
 * @return array{ok:bool, map:array, why:string}
 */
function pu_parse_block($blk)
{
    $map = array();
    if (!preg_match_all('~\$(\w+)\s*=\s*\(?\s*intval\(\s*\$row\[\s*[\x27\x22](can_view|can_add|can_edit|can_delete)[\x27\x22]\s*\]\s*\)\s*===\s*1\s*\)?\s*;([^\n]*)~',
                        $blk, $m, PREG_SET_ORDER)) {
        return array('ok' => false, 'map' => array(), 'why' => 'لا إسنادَ مفهومًا من `$row[can_*]` في الكتلة');
    }
    /* ⚠ **وذيلُ السطرِ يُقبل تعليقًا وحدَه — لا قوسًا** (‏قِيس فسقط): تسعةُ ملفّاتٍ
       تكتب الإسنادَ في سطرِ الشرطِ نفسِه — `if ($row = …) { $can_view = …; }` —
       فيلتقط الذيلُ **`}` الخاتمةَ** فتُعاد معه فيصير القوسُ فائضًا، و`php -l`
       يردُّ «Unmatched '}'». ⇒ **يُقبل ما كان تعليقًا (`//` أو `/*`) ويُطرح ما عداه.**
       ⛔ **والحارسُ هو الذي أمسكها**: كُتبت التسعةُ ثمَّ رُدَّت فورًا بفحصِ الصيغة. */
    foreach ($m as $x) {
        $tail = rtrim($x[3]);
        if ($tail !== '' && !preg_match('~^\s*(//|/\*|\#)~', $tail)) { $tail = ''; }
        $map[$x[1]] = array('key' => $x[2], 'tail' => $tail);
    }
    /* ⛔ **ولا سطرَ إسنادٍ لا يُفهَم**: كلُّ `$x = …;` داخلَ `if ($row …)` يجب أن يكون منها */
    if (preg_match_all('~^\s*\$(\w+)\s*=~m', $blk, $all)) {
        foreach ($all[1] as $v) {
            if ($v === 'rid' || $v === 'row' || isset($map[$v])) { continue; }
            if (preg_match('~\$' . $v . '\s*=\s*\$conn->prepare~', $blk)) { continue; }
            return array('ok' => false, 'map' => $map, 'why' => 'إسنادٌ لا يُفهَم في الكتلة: `$' . $v . '`');
        }
    }
    return array('ok' => true, 'map' => $map, 'why' => '');
}

/* ═══ الاختبارُ السالب ═══════════════════════════════════════════════════ */
if ($SELF) {
    $fail = 0;
    $good = '$st = $conn->prepare("SELECT rp.can_view, rp.can_add FROM role_permissions rp '
          . 'JOIN modules m ON m.id = rp.module_id WHERE m.code = ? AND rp.role_id = ? LIMIT 1");' . "\n"
          . '    $rid = intval($current_role);' . "\n"
          . '    $st->bind_param(\'si\', $MODULE_CODE, $rid);' . "\n"
          . '    $st->execute();' . "\n"
          . '    if ($row = $st->get_result()->fetch_assoc()) {' . "\n"
          . '        $can_view   = (intval($row[\'can_view\']) === 1);' . "\n"
          . '        $can_decide = (intval($row[\'can_add\'])  === 1);  // تعليق' . "\n"
          . '    }' . "\n"
          . '    $st->close();';
    $p = pu_parse_block($good);
    if (!$p['ok'] || $p['map']['can_view']['key'] !== 'can_view'
        || $p['map']['can_decide']['key'] !== 'can_add') { echo "  X الكتلةُ المعياريّةُ لم تُفهَم\n"; $fail++; }
    if (strpos($p['map']['can_decide']['tail'], 'تعليق') === false) { echo "  X التعليقُ ضاع\n"; $fail++; }
    /* **الكاسر**: سطرٌ لا يُفهَم يجب أن يردَّ الكتلةَ كلَّها */
    $bad = str_replace('$can_decide = (intval($row[\'can_add\'])  === 1);  // تعليق',
                       '$zzq_unknown_probe = compute_something($row);', $good);
    if (pu_parse_block($bad)['ok']) { echo "  X إسنادٌ لا يُفهَم مرَّ\n"; $fail++; }
    if (!preg_match(PU_BLOCK, $good)) { echo "  X النمطُ لم يلتقط الكتلةَ\n"; $fail++; }
    /* ⛔ **ولا يلتقط ما ليس قرارَ صلاحية** */
    if (preg_match(PU_BLOCK, '$st = $conn->prepare("SELECT id FROM clients"); $st->close();')) {
        echo "  X استعلامٌ آخرُ التُقط\n"; $fail++;
    }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n"
               : "\n🟢 الفحصُ الذاتيُّ تامٌّ — الكتلةُ تُفهَم كاملةً أو تُردّ، والتعليقُ يبقى\n";
    exit($fail ? 1 : 0);
}

/* ═══ ① المسحُ ══════════════════════════════════════════════════════════ */
$SKIP = array('/vendor/', '/storage/', '/tools/', '/tests/', '/node_modules/', '/.git/',
              '/database/migrations/', '/docs/', '/includes/permissions_helper.php');
$plan = array(); $held = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') { continue; }
    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($ROOT)));
    $skip = false;
    foreach ($SKIP as $s) { if (strpos($rel, $s) !== false) { $skip = true; break; } }
    if ($skip) { continue; }
    $src = (string) @file_get_contents($f->getPathname());
    if ($src === '' || strpos($src, 'SELECT rp.can_view') === false) { continue; }

    if (!preg_match_all(PU_BLOCK, $src, $mb)) {
        $held[$rel] = 'الكتلةُ لا تطابق الشكلَ المعياريَّ (‏من `prepare` إلى `close`)';
        continue;
    }
    if (count($mb[0]) !== 1) { $held[$rel] = 'كتلتانِ فأكثرُ — ولا يُستبدَل ما لم يُفهَم واحدًا'; continue; }
    $blk = $mb[0][0];
    if (strpos($src, 'permissions_helper.php') === false) { $held[$rel] = 'لا يشتمل `permissions_helper.php`'; continue; }
    if (!preg_match('~\$MODULE_CODE\s*=\s*[\x27\x22]~', $src)) { $held[$rel] = 'بلا `$MODULE_CODE` مُعلَن'; continue; }
    if (!preg_match('~bind_param\(\s*[\x27\x22]si[\x27\x22]\s*,\s*\$MODULE_CODE\s*,~', $blk)) {
        $held[$rel] = 'ربطُ الكتلةِ ليس `$MODULE_CODE`'; continue;
    }
    $p = pu_parse_block($blk);
    if (!$p['ok']) { $held[$rel] = $p['why']; continue; }
    $plan[$rel] = array('path' => $f->getPathname(), 'blk' => $blk, 'map' => $p['map']);
}

/* ═══ ② العرض ═══════════════════════════════════════════════════════════ */
echo "\n═══ `RPR-03` §٦ — توحيدُ مسارِ قرارِ الصلاحية ═══\n";
printf("  ملفّاتٌ تحمل الكتلةَ المستقلّة: **%d** · ⇒ **يُوحَّد %d** · ⛔ موقوفٌ %d\n",
       count($plan) + count($held), count($plan), count($held));
$vars = array();
foreach ($plan as $rel => $x) { foreach (array_keys($x['map']) as $v) { $vars[$v] = (isset($vars[$v]) ? $vars[$v] : 0) + 1; } }
arsort($vars);
echo "\n  ── أسماءُ المتغيّراتِ المحفوظةُ كما هي ──\n     ";
$i = 0;
foreach ($vars as $v => $n) { echo "\$$v($n) "; if (++$i >= 14) { break; } }
echo "\n";
if ($held) {
    echo "\n  ⛔ **موقوفٌ — ولا يُلمَس ما لم يُفهَم**:\n";
    foreach ($held as $r => $w) { printf("     · %-46s %s\n", mb_substr($r, -46), $w); }
}
if (!$APPLY) { echo "\n  ⛔ **معاينةٌ — لم يُكتب شيء.** والتطبيقُ بـ`--apply`.\n"; }

/* ═══ ③ التطبيق ═════════════════════════════════════════════════════════ */
if ($APPLY) {
    $done = 0; $revert = array();
    foreach ($plan as $rel => $x) {
        $src = (string) file_get_contents($x['path']);
        $new = "/* `RPR-03` §٦ — **المسارُ الواحد**: القرارُ من `check_page_permissions()`\n"
             . "           لا من استعلامٍ خاصٍّ بهذا الملفّ. **والفرقُ طبقةُ القوالب**\n"
             . "           (`GOV-AUTH-01`): القراءةُ الخامّةُ لا ترى القالبَ النافذَ، فتُخفى\n"
             . "           الشاشةُ من السايدبارِ وتُفتح بالرابطِ المباشر.\n"
             . "        ⛔ **وفرعُ السوبر أدمن أعلاه لم يُمَسّ** — والأسماءُ كما كانت. */\n"
             . "    \$__perm = check_page_permissions(\$conn, \$MODULE_CODE);\n";
        foreach ($x['map'] as $v => $d) {
            $new .= '    $' . $v . ' = (bool) $__perm[\'' . $d['key'] . '\'];'
                  . ($d['tail'] !== '' ? '  ' . ltrim($d['tail']) : '') . "\n";
        }
        $new = rtrim($new, "\n");
        $out = str_replace($x['blk'], $new, $src);
        if ($out === $src) {
            $held[$rel] = 'الاستبدالُ لم يغيّر شيئًا';
            echo "     ⛔ $rel — الاستبدالُ لم يغيّر شيئًا\n";
            continue;
        }
        file_put_contents($x['path'], $out);
        /* ⑥ **والملفُّ يمرُّ `php -l` وإلّا رُدَّ فورًا** */
        $o = array(); $rc = 0;
        @exec('"' . PHP_BINARY . '" -l ' . escapeshellarg($x['path']) . ' 2>&1', $o, $rc);
        if ($rc !== 0) {
            file_put_contents($x['path'], $src);
            $held[$rel] = 'سقط في `php -l` بعدَ الكتابة — **ورُدَّ فورًا**';
            echo "     ⛔ $rel — سقط في `php -l` فرُدَّ: " . implode(' | ', array_slice($o, 0, 2)) . "\n";
            continue;
        }
        $revert[] = $rel; $done++;
    }
    printf("\n  ✔ **وُحِّد %d ملفًّا** — وكلٌّ مرَّ `php -l` بعدَ الكتابة\n", $done);
    printf("  ⛔ وموقوفٌ %d — ولا يُلمَس ما لم يُفهَم\n", count($held));
    echo "  ◆ **والتراجعُ بالالتزامِ الواحد**: `git revert` — فالتغييرُ شيفرةٌ لا مخزن.\n";
}

/* ═══ ④ المعاينةُ المكتوبة ══════════════════════════════════════════════ */
if ($MD) {
    $o  = "# `RPR-03` §٦ — توحيدُ مسارِ قرارِ الصلاحية\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md`\n\n";
    $o .= '| المفردة | العدد |' . "\n|---|---:|\n";
    $o .= '| ملفّاتٌ تحمل الكتلةَ المستقلّة | ' . (count($plan) + count($held)) . " |\n";
    $o .= '| **يُوحَّد** | **' . count($plan) . "** |\n";
    $o .= '| ⛔ موقوفٌ | ' . count($held) . " |\n\n";
    $o .= "## الملفّاتُ الموحَّدة\n\n| الملفّ | المتغيّراتُ المحفوظة |\n|---|---|\n";
    foreach ($plan as $rel => $x) {
        $o .= '| `' . ltrim($rel, '/') . '` | ' . implode(' · ', array_map(
            function ($v, $d) { return '`$' . $v . '` ⇐ `' . $d['key'] . '`'; },
            array_keys($x['map']), $x['map'])) . " |\n";
    }
    if ($held) {
        $o .= "\n## ⛔ موقوفٌ — ولا يُلمَس ما لم يُفهَم\n\n| الملفّ | السبب |\n|---|---|\n";
        foreach ($held as $r => $w) { $o .= '| `' . ltrim($r, '/') . '` | ' . $w . " |\n"; }
    }
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/RPR03_PERM_PATH_UNIFY.md', $o);
    echo "\n✔ كُتب docs/REPAIR01_20260823/RPR03_PERM_PATH_UNIFY.md\n";
}
