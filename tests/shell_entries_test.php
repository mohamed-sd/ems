<?php
/**
 * tests/shell_entries_test.php — شاهدُ مداخلِ القشرةِ المعتمدة
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0236
 *
 * **العيب**: مساراتٌ مستقلةٌ تُصدر `<html>` و`<head>` وهيكلَها الخاص — فلكلِّ
 * مسارٍ هويةٌ وسلوكٌ وتحديثٌ منفصل. **القبول**: عددُ الملفاتِ التي تُصدر
 * `<!DOCTYPE` **خارجَ مداخلِ القشرةِ المعتمدة = 0**، وصفحةُ الحجبِ تعرض المكوّنَ
 * الموحَّدَ نفسَه في كلِّ المسارات.
 *
 * ── والقياسُ بالرموزِ لا بالنص ──────────────────────────────────────────────
 * أوّلُ مسحٍ أعطى **٢٩ ملفًّا** — وكان يعدُّ **تعليقاتٍ** تصف الحالةَ القديمة
 * («كان هنا رأسٌ محليٌّ كاملٌ بـ`<!DOCTYPE>`»). فصفحاتُ التقاريرِ الإحدى عشرةَ
 * كانت **موحَّدةً سلفًا** وأُدينت بنصِّ تعليقٍ يشرح توحيدَها. القياسُ الآن على
 * `token_get_all`: ما يُصدَر فعلًا (`T_INLINE_HTML` وسلاسلُ الشفرة) لا ما يُذكر.
 *
 * ── والمداخلُ المعتمدةُ خمسةٌ لكلٍّ سببُه ────────────────────────────────────
 *   · `inheader.php` — القشرةُ المصادَقة (٣٤٦ شاشة)
 *   · `admin/includes/layout_head.php` — لوحةُ الإدارةِ العليا
 *   · `includes/public_shell.php` — **ما قبلَ الدخول** (١٢ شاشةً حُوِّلت هنا):
 *     القشرةُ المصادَقةُ تفترض جلسةً ودورًا وقائمةً، ولا شيءَ منها قبلَ الدخول.
 *   · `includes/deny_page.php` — صفحةُ الحجبِ الموحَّدة (الشقُّ الثاني من القبول)
 *   · `emsreports/reports/_report_template.php` — قالبُ الطباعة
 *
 * ── وما يبقى خارجَها **لا يمكن** أن يدخلها ─────────────────────────────────
 * المثبِّتُ وصفحةُ فشلِ الإعدادِ يعملان **قبل وجودِ النظام**: لا `config` ولا
 * قاعدةَ ولا جلسة — فتضمينُ قشرةٍ تعتمد عليها يقتل الصفحةَ التي تشرح العطل.
 * وأدواتُ سطرِ الأوامرِ والفواحصُ ليست شاشاتِ منتجٍ أصلًا. تُعلَن ولا تُخفى.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
/* ◆ الشرطةُ الخلفيةُ في ويندوز: بلا تطبيعٍ يفشل نزعُ الجذرِ فتبقى المساراتُ
     مطلقةً ولا تطابق قائمةَ المداخلِ المعتمدة — گوتشا مسجَّلةٌ من قبل. */
$ROOT = str_replace('\\', '/', dirname(__DIR__));

$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$say('══ INJ-0236 · مدخلُ القشرةِ واحدٌ لكلِّ عائلةِ شاشات');

$APPROVED = array(
    'inheader.php', 'admin/includes/layout_head.php', 'includes/public_shell.php',
    'includes/deny_page.php', 'emsreports/reports/_report_template.php',
);
/* ما لا يمكن أن يدخل قشرةً — ويُعلَن بسببِه لا يُخفى */
$EXEMPT = array(
    'install/index.php'                => 'المثبِّتُ يعمل قبل وجودِ النظام',
    'admin/setup_once.php'             => 'تهيئةٌ أوّليةٌ قبلَ وجودِ حسابٍ',
    'emsreports/setup_permissions.php' => 'سكربتُ تهيئةِ صلاحياتٍ لا شاشة',
    'config.php'                       => 'صفحةُ فشلِ الإعدادِ — لا config يُضمَّن',
    'scripts/md_to_pdf_html.php'       => 'مُحوِّلُ وثائقَ على سطرِ الأوامر',
);

$dead = '~/(storage/backups|\.claude|vendor|node_modules|docs)/~';
$emitters = array(); $approvedSeen = 0; $exemptSeen = 0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $p) {
    $path = str_replace('\\', '/', $p->getPathname());
    if (substr($path, -4) !== '.php' || preg_match($dead, $path)) { continue; }
    $rel = str_replace($ROOT . '/', '', $path);
    /* الفواحصُ والأدواتُ ليست شاشاتِ منتج */
    if (strpos($rel, 'tests/') === 0 || strpos($rel, 'tools/') === 0) { continue; }
    $src = (string) @file_get_contents($path);
    if (stripos($src, '<!DOCTYPE') === false) { continue; }
    $n = 0;
    foreach (token_get_all($src) as $tk) {
        if (!is_array($tk)) { continue; }
        if ($tk[0] === T_COMMENT || $tk[0] === T_DOC_COMMENT) { continue; }
        if ($tk[0] === T_INLINE_HTML || $tk[0] === T_CONSTANT_ENCAPSED_STRING
            || $tk[0] === T_ENCAPSED_AND_WHITESPACE) {
            $n += preg_match_all('~<!DOCTYPE~i', $tk[1]);
        }
    }
    if ($n === 0) { continue; }
    if (in_array($rel, $APPROVED, true)) { $approvedSeen++; continue; }
    if (isset($EXEMPT[$rel])) { $exemptSeen++; continue; }
    $emitters[$rel] = $n;
}

$ok($approvedSeen >= 4, "مداخلُ القشرةِ المعتمدةُ تُصدر المستندَ ({$approvedSeen} من "
    . count($APPROVED) . ')');
$ok(empty($emitters),
    '**صفرُ شاشةِ منتجٍ تُصدر `<!DOCTYPE` خارجَ مدخلٍ معتمد** (' . count($emitters) . ')',
    implode(' · ', array_keys($emitters)));
$ok($exemptSeen === count($EXEMPT),
    "وما بقي مُعلَنٌ بسببِه: {$exemptSeen} ملفًّا لا يمكن أن يُضمِّن قشرةً");

/* ── المدخلُ الجديدُ مُتبنًّى فعلًا لا مبنيًّا فقط ─────────────────────────── */
$users = 0;
$it2 = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it2 as $p) {
    $path = str_replace('\\', '/', $p->getPathname());
    if (substr($path, -4) !== '.php' || preg_match($dead, $path)) { continue; }
    $src = (string) @file_get_contents($path);
    if (strpos($src, 'ems_public_head(') !== false
        && strpos($path, 'includes/public_shell.php') === false) { $users++; }
}
$ok($users >= 10, "ومدخلُ ما قبلَ الدخولِ مُتبنًّى في {$users} شاشةً — لا مبنيًّا بلا مستهلك");

/* ── والشقُّ الثاني: صفحةُ الحجبِ مكوّنٌ واحدٌ في كلِّ المسارات ─────────────── */
$deny = (string) @file_get_contents($ROOT . '/includes/deny_page.php');
$ok(strpos($deny, 'function ems_deny_page') !== false, 'وصفحةُ الحجبِ مكوّنٌ واحد');
$calls = 0;
foreach (array('includes/security.php', 'app/Services/Portal/SupplierPortalGuard.php') as $f) {
    $calls += preg_match_all('~ems_deny_page\(~', (string) @file_get_contents($ROOT . '/' . $f));
}
$ok($calls >= 6, "**ومُنادًى في {$calls} مسارِ حجبٍ** — لا نصَّ حجبٍ مبنيًّا بيدٍ");

/* ── ومُخرَجُ الشاشاتِ المحوَّلةِ سليمٌ ──────────────────────────────────────── */
$http = function ($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 60));
    $b = (string) curl_exec($ch);
    $c = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array('body' => $b, 'code' => $c);
};
$BASE = 'http://localhost/ems';
$bad = array(); $seen = 0;
foreach (array('index.php', 'login.php', 'company/login.php', 'admin/login.php',
               'company/forgot_password.php') as $rel) {
    $r = $http($BASE . '/' . $rel);
    if ($r['code'] !== 200) { $bad[] = $rel . ' (HTTP ' . $r['code'] . ')'; continue; }
    $seen++;
    if (stripos($r['body'], '<!DOCTYPE') === false) { $bad[] = $rel . ' (بلا مستند)'; }
    if (!preg_match('~<title>\s*\S~u', $r['body'])) { $bad[] = $rel . ' (بلا عنوان)'; }
    if (strpos($r['body'], 'dir="rtl"') === false) { $bad[] = $rel . ' (بلا اتجاه)'; }
    if (strpos($r['body'], 'name="viewport"') === false) { $bad[] = $rel . ' (بلا viewport)'; }
}
$ok($seen >= 4, "وصُيِّرت {$seen} شاشاتٍ محوَّلةً");
$ok(empty($bad), '**وكلُّها بمستندٍ وعنوانٍ واتجاهٍ و`viewport`** من المدخلِ الواحد',
    implode(' · ', $bad));

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
