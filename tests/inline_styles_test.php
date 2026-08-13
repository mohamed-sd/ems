<?php
/**
 * tests/inline_styles_test.php — شاهدُ الأنماطِ الموضعيةِ ومجموعةِ الرموز
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0442 · INJ-0571 · INJ-0237 · INJ-0501
 *
 * **العيب**: ٤٧٤٥ سمةَ `style=` في ٤٠٠ ملفّ، و١٠٩ كتلةَ `<style>` في ١٠٤.
 * وكتلةٌ داخلَ الصفحةِ لا تُخزَّن في ذاكرةِ المتصفحِ، ولا يغيّرها رمزٌ لونيٌّ،
 * ولا يراها تدقيقُ الأنماط. وأسوأُ ملفٍّ في النظامِ `Timesheet/timesheet.php`:
 * ٢٠٨ سمةً و١٩١ لونًا صلبًا.
 *
 * **الإصلاح**: مُحوِّلٌ **واعٍ ببناءِ PHP** (`token_get_all`) ينقل الساكنَ إلى
 * `assets/css/ems-screens.css` صنفًا صنفًا، وحارسُ بناءٍ يمنع كتابةَ ناتجٍ لا
 * يجتاز `php -l`. (أوّلُ صياغةٍ عالجت الملفَّ نصًّا واحدًا فكسرت ملفَّين —
 * واستُعيدا بايتًا ببايت، ثمَّ أُعيد بناءُ الأداةِ على الرموزِ لا على النص.)
 *
 * ── INJ-0501 · ومجموعةُ رموزٍ واحدة ────────────────────────────────────────
 * لوحةُ الإدارةِ العليا كانت تُعرّف `:root` خاصًّا بها ولا تعرف ملفَّ رموزِ
 * المنتجِ أصلًا — هويتان لنظامٍ واحد. صارت تُحمّله، وأسماؤها مشتقّةٌ منه.
 *
 * ── والقياسُ لا يُدَّعى فيه ما لا يُقاس ────────────────────────────────────
 * قيمةٌ **مشتقّةٌ من بيانٍ** (عرضُ شريطِ تقدُّمٍ بالنسبةِ المئوية) لا يمكن أن
 * تعيش في ورقةِ أنماط — فتبقى سمةً موضعيةً وتُعلَن، ولا يُعدُّ بقاؤها إخفاقًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);

$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$LIT = '~#[0-9a-fA-F]{3,8}\b|\brgba?\([0-9 ,.%/]+\)|\bhsla?\([0-9 ,.%/]+\)~';

$stat = function ($rel) use ($ROOT, $LIT) {
    $s = (string) @file_get_contents($ROOT . '/' . $rel);
    return array(
        'attr'  => preg_match_all('~\sstyle\s*=\s*["\']~i', $s),
        'block' => preg_match_all('~<style\b~i', $s),
        'color' => preg_match_all($LIT, $s),
        'ok'    => is_file($ROOT . '/' . $rel),
    );
};

/* ── ① INJ-0442 · شاشتا العقود: صفرُ كتلةٍ وصفرُ لونٍ صلب ───────────────── */
$say('══ INJ-0442 · شاشتا العقود');
foreach (array('Contracts/contracts.php', 'Contracts/contracts_details.php') as $rel) {
    $m = $stat($rel);
    $ok($m['ok'] && $m['block'] === 0, "«{$rel}» بصفرِ كتلةِ `<style>`", 'كتل: ' . $m['block']);
    $ok($m['ok'] && $m['color'] === 0, "  وصفرِ لونٍ صلبٍ خارج رموز النظام", 'ألوان: ' . $m['color']);
}

/* ── ② INJ-0571 · التايم شيت ───────────────────────────────────────────── */
$say('');
$say('══ INJ-0571 · التايم شيت — أسوأُ ملفٍّ قياسًا');
$ts = $stat('Timesheet/timesheet.php');
$ok($ts['block'] === 0, 'صفرُ كتلةِ `<style>` في التايم شيت', 'كتل: ' . $ts['block']);
$ok($ts['attr'] <= 6, 'وسماتُ `style=` هبطت من ٢٠٨ إلى ' . $ts['attr']
    . ' — والباقي مشتقٌّ من بيانٍ لا يعيش في ورقةِ أنماط', 'العدد: ' . $ts['attr']);
$ok($ts['color'] <= 12, 'والألوانُ الصلبةُ من ١٩١ إلى ' . $ts['color'], 'العدد: ' . $ts['color']);

/* ── ③ INJ-0237 · الشاشاتُ التمثيليةُ الثمان ───────────────────────────── */
$say('');
$say('══ INJ-0237 · الشاشاتُ التمثيليةُ الثمان');
$EIGHT = array(
    'main/dashboard.php', 'Employees/employees.php', 'Equipments/equipments.php',
    'Clients/clients.php', 'Suppliers/suppliers.php', 'Timesheet/timesheet.php',
    'Contracts/contracts.php', 'Contracts/contracts_details.php',
);
$totalAttr = 0; $totalBlock = 0; $missing = array();
foreach ($EIGHT as $rel) {
    $m = $stat($rel);
    if (!$m['ok']) { $missing[] = $rel; continue; }
    $totalAttr += $m['attr'];
    $totalBlock += $m['block'];
}
$ok(empty($missing), 'الثمانُ موجودةٌ (' . count($EIGHT) . ')', implode(' · ', $missing));
$ok($totalBlock === 0, '**وصفرُ كتلةِ `<style>` في الثمانِ مجتمعةً**', 'كتل: ' . $totalBlock);
$ok($totalAttr <= 10, '**ومجموعُ سماتِ `style=` فيها ' . $totalAttr . '** (كان ٢٨٢)',
    'العدد: ' . $totalAttr);
$scr = (string) @file_get_contents($ROOT . '/assets/css/ems-screens.css');
$ok(strlen($scr) > 4000, 'وورقةُ أنماطِ الشاشاتِ تحمل المنقولَ (' . strlen($scr) . ' بايت)');
$ok(preg_match($LIT, $scr) === 0, '**وصفرُ لونٍ صلبٍ فيها** — المنقولُ تحوَّل إلى رموز',
    preg_match($LIT, $scr, $g) ? $g[0] : '');
$head = (string) @file_get_contents($ROOT . '/inheader.php');
$ok(strpos($head, 'ems-screens.css') !== false,
    'والقشرةُ تُحمّلها — فالمنقولُ يصل إلى الشاشة');

/* ── ④ INJ-0501 · مجموعةُ رموزٍ واحدةٌ وورقةٌ رئيسةٌ واحدة ───────────────── */
$say('');
$say('══ INJ-0501 · مجموعةُ رموزٍ واحدة');
$adm = (string) @file_get_contents($ROOT . '/admin/includes/layout_head.php');
$ok(strpos($adm, 'design-tokens.css') !== false,
    '**لوحةُ الإدارةِ العليا تُحمّل ملفَّ رموزِ المنتجِ نفسَه**');
$rootBlock = '';
if (preg_match('~:root\s*\{(.*?)\}~su', $adm, $m)) { $rootBlock = $m[1]; }
$ok($rootBlock !== '', 'ولها كتلةُ أسماءٍ محلية');
$ok($rootBlock !== '' && preg_match($LIT, $rootBlock) === 0,
    '**وصفرُ لونٍ صلبٍ فيها** — كلُّها `var()` من الملفِّ الواحد',
    preg_match($LIT, $rootBlock, $g2) ? $g2[0] : '');
$tokens = (string) @file_get_contents($ROOT . '/assets/css/design-tokens.css');
$ok(preg_match_all('~--c-admin-[a-z0-9-]+\s*:~', $tokens) >= 15,
    'ورموزُ اللوحةِ صارت في ملفِّ الرموزِ الواحد ('
    . preg_match_all('~--c-admin-[a-z0-9-]+\s*:~', $tokens) . ')');
/* رمزٌ دلاليٌّ مشتركٌ يستعمله الطرفان — فتغييرُه يبلغ الاثنين */
$shared = array('--c-state-info', '--c-state-danger', '--c-surface');
$bothUse = 0;
foreach ($shared as $t) {
    if (strpos($adm, 'var(' . $t . ')') !== false && strpos($tokens, $t . ':') !== false) { $bothUse++; }
}
$ok($bothUse === count($shared),
    '**ورموزٌ دلاليةٌ مشتركةٌ يستعملها الطرفان** — فتغييرُ واحدٍ يبلغ القشرةَ واللوحةَ معًا ('
    . $bothUse . '/' . count($shared) . ')');
/* ورقةٌ رئيسةٌ واحدةٌ مُحمَّلةٌ في المنتج */
$dead = '~/(storage/backups|\.claude|vendor|node_modules|tests|tools|docs)/~';
$mains = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $p) {
    $path = str_replace('\\', '/', $p->getPathname());
    if (substr($path, -4) !== '.php' || preg_match($dead, $path)) { continue; }
    $s = (string) @file_get_contents($path);
    if (preg_match_all('~([a-z0-9_.-]*main\.all\.style\.css)~i', $s, $mm)) {
        foreach ($mm[1] as $name) { $mains[$name] = true; }
    }
}
$ok(count($mains) === 1, '**وورقةُ أنماطٍ رئيسةٌ واحدةٌ مُحمَّلةٌ في المنتج** ('
    . implode(' · ', array_keys($mains)) . ')', implode(' · ', array_keys($mains)));

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
