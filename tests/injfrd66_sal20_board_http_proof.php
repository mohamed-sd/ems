<?php
/**
 * tests/injfrd66_sal20_board_http_proof.php — شاهدُ SAL-20: المؤشرُ بابٌ لا لافتة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ولماذا HTTP لا قراءةُ ملف**: بوابةُ السطحِ تقرأ الشفرةَ فترى `<a href>`
 *   مكتوبًا — **ولا ترى هل صُيِّر**. وسطرٌ داخلَ `if` لا يتحقَّق، أو خطأٌ
 *   قاتلٌ بعدَه، يجعل المِرساةَ **موجودةً في الملفِّ غائبةً عن الصفحة**.
 *   فالشاهدُ يفتح اللوحةَ بجلسةِ مبيعاتٍ حقيقيةٍ ويقرأ **ما وصل للمتصفّح**.
 *
 * ◆ **إيجابيٌّ ①**: اللوحةُ تُصيَّر 200 لدورِ المبيعات.
 * ◆ **إيجابيٌّ ②**: وكلُّ سطرِ عقدٍ يحمل مِرساةً إلى ملفِّه — **عددُ المراسي
 *   = عددُ الأسطر**، لا «مِرساةٌ واحدةٌ تكفي».
 * ◆ **إيجابيٌّ ③**: وبطاقةُ كلِّ عملةٍ مِرساةُ تصفية.
 * ◆ **سالبٌ ④**: والتصفيةُ تعمل فعلًا — عددُ الأسطرِ ينقص، **وبطاقاتُ العملاتِ
 *   الأخرى تبقى**. (لو حُسبت المجاميعُ بعدَ التصفيةِ لمحا المؤشرُ جيرانَه.)
 * ◆ **سالبٌ ⑤**: وعملةٌ غيرُ موجودةٍ لا تُصفّي شيئًا ولا تُفرغ اللوحة.
 * ◆ **سالبٌ ⑥**: وبلا جلسةٍ تُردُّ اللوحةُ إلى الدخول.
 *
 * التشغيل: php tests/injfrd66_sal20_board_http_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$BASE = 'http://localhost/ems';
$PW   = '12345678';

$nOk = 0; $nBad = 0;
$check = static function (bool $ok, string $msg) use (&$nOk, &$nBad): void {
    if ($ok) { $nOk++; echo "   ✔ {$msg}\n"; } else { $nBad++; echo "   ✘ {$msg}\n"; }
};
$req = static function (string $url, string $jar, array $post = null): array {
    $ch = curl_init($url);
    $opt = array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 30,
    );
    if ($post !== null) { $opt[CURLOPT_POST] = true; $opt[CURLOPT_POSTFIELDS] = http_build_query($post); }
    curl_setopt_array($ch, $opt);
    $raw  = (string) curl_exec($ch);
    $hs   = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array($code, substr($raw, 0, $hs), substr($raw, $hs));
};

$_SERVER['SCRIPT_NAME'] = '/ems/main/dashboard.php';
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

/* ── الحسابُ يُقرأ من القاعدةِ لا يُكتب في الشاهد ────────────────────────
   ◆ و`role` هو العمودُ المملوءُ — و`role_id` فارغٌ في ثلثِ الصفوف. */
$res = @mysqli_query($conn, "SELECT id, username, name FROM users
                              WHERE COALESCE(role_id, NULLIF(CAST(role AS UNSIGNED),0)) = 12
                                AND is_deleted = 0 AND status IN ('active','1')
                           ORDER BY id LIMIT 1");
$acct = $res ? mysqli_fetch_assoc($res) : null;
echo "① الحسابُ المقيسُ لدورِ المبيعات:\n";
if (!$acct) {
    echo "   ✘ صفرُ حسابٍ نشطٍ للدورِ 12 — لا رحلةَ تُقاس\n";
    echo "\n✘ SAL-20  ناجح 0 · راسب 1\n";
    exit(1);
}
$nOk++;
printf("   ✔ «%s» (%s · #%d)\n", $acct['name'], $acct['username'], $acct['id']);

$jar = tempnam(sys_get_temp_dir(), 'sal20');
list(, , $form) = $req($BASE . '/login.php', $jar);
/* نموذجٌ خامٌّ — الحقنُ الآليُّ لـfetch/XHR فقط، فبلا `csrf_token` يُردُّ 403 */
$tok = preg_match('~name="csrf_token"\s+value="([^"]+)"~', $form, $tm) ? $tm[1] : '';
list($c, $h, ) = $req($BASE . '/login.php', $jar,
    array('username' => $acct['username'], 'password' => $PW, 'csrf_token' => $tok));
$loc = preg_match('~^Location:\s*(.+)$~mi', $h, $m) ? trim($m[1]) : '';
$check($loc !== '' && mb_strpos($loc, 'login') === false, 'الدخولُ فتح جلسةً');

echo "\n② إيجابيٌّ — اللوحةُ تُصيَّر ومراسيها بعددِ أسطرِها:\n";
list($c2, , $b2) = $req($BASE . '/Contracts/commercial_board.php?all=1', $jar);
$check($c2 === 200, "اللوحةُ تُصيَّر برمزِ 200 (جاء {$c2})");
/* الإرساءُ على وسمٍ بنيويٍّ لا على عبارةٍ عربية */
$bodyRows = preg_match_all('~<tr[^>]*>\s*<td><a class="cb-drill"~', $b2);
$drills   = preg_match_all('~class="cb-drill"[^>]*href="contracts_details\.php\?id=\d+~', $b2);
printf("   ○ أسطرٌ بمِرساة: %d · مراسٍ إلى ملفِّ عقد: %d\n", $bodyRows, $drills);
$check($drills > 0, 'اللوحةُ تحمل مراسيَ إلى ملفاتِ العقود');
$check($drills === $bodyRows && $bodyRows > 0,
    'وعددُ المراسي = عددُ الأسطر — لا «مِرساةٌ واحدةٌ تكفي»');
/* ومعرِّفُ العقدِ في المِرساةِ هو معرِّفُ السطرِ نفسِه لا رقمٌ ثابت */
$ids = array();
if (preg_match_all('~cb-drill"[^>]*href="contracts_details\.php\?id=(\d+)"[^>]*>[\s\S]{0,120}?#(\d+)~', $b2, $mm, PREG_SET_ORDER)) {
    foreach ($mm as $x) { $ids[] = ($x[1] === $x[2]); }
}
$check($ids !== array() && !in_array(false, $ids, true),
    'ورقمُ العقدِ المعروضُ = الرقمُ الذي تفتحه المِرساة (' . count($ids) . ' سطرًا)');

echo "\n③ إيجابيٌّ — بطاقةُ كلِّ عملةٍ مِرساةُ تصفية:\n";
$cards = preg_match_all('~class="cb-total-card~', $b2);
$links = preg_match_all('~class="cb-total-link"~', $b2);
printf("   ○ بطاقاتٌ: %d · مراسٍ: %d\n", $cards, $links);
$check($cards > 0 && $cards === $links, 'كلُّ بطاقةِ مجموعٍ تحمل مِرساتَها');
$curs = array();
if (preg_match_all('~cb-total-link"\s+href="\?all=\d+&(?:amp;)?cur=([^"]+)"~', $b2, $cm)) {
    foreach ($cm[1] as $u) { $curs[] = rawurldecode($u); }
}
$check(count($curs) > 0, 'ومقاصدُها تحمل عملاتِها: ' . implode('، ', $curs));

echo "\n④ سالبٌ — التصفيةُ تعمل ولا تمحو جيرانَها:\n";
if (!$curs) { $nBad++; echo "   ✘ لا عملةَ تُجرَّب\n"; }
else {
    $one = $curs[0];
    list($c4, , $b4) = $req($BASE . '/Contracts/commercial_board.php?all=1&cur=' . rawurlencode($one), $jar);
    $rowsAfter  = preg_match_all('~class="cb-drill"~', $b4);
    $cardsAfter = preg_match_all('~class="cb-total-card~', $b4);
    $onCard     = preg_match_all('~cb-total-card cb-total-on~', $b4);
    $check($c4 === 200, "الصفحةُ المصفّاةُ تُصيَّر 200 (جاء {$c4})");
    $check($rowsAfter > 0 && $rowsAfter < $bodyRows,
        "الأسطرُ نقصت بالتصفية: {$bodyRows} ← {$rowsAfter} («{$one}»)");
    /* ◆ **وهذا هو الفخُّ الذي تجنَّبه البناء**: لو حُسبت المجاميعُ **بعدَ**
         التصفيةِ لاختفت بطاقاتُ العملاتِ الأخرى بأوَّلِ نقرة — فيصير مصدرُ
         المؤشرِ **يمحو المؤشراتِ المجاورة** ولا يبقى بابٌ للعودة. */
    $check($cardsAfter === $cards,
        "وبطاقاتُ العملاتِ كلُّها باقية: {$cards} ← {$cardsAfter} — المؤشرُ لا يمحو جيرانَه");
    $check($onCard === 1, 'وبطاقةٌ واحدةٌ موسومةٌ بأنَّها المصفّاة');
    /* ◆ **وهذا الفحصُ أضعفُ ممّا يبدو ببطاقةٍ واحدة**: «1 ← 1» يصدُق أيضًا لو
         حُسبت المجاميعُ بعدَ التصفية. فيُعلَن ضعفُه **ويُسنَد بفحصٍ بنيويٍّ
         على ترتيبِ الجُمَل** — عددٌ لا يفرِّق بين حالتَين ليس دليلًا عليهما. */
    if ($cards < 2) {
        echo "   ◆ وبعملةٍ واحدةٍ في البيانات لا يفرِّق هذا العددُ بين الحالتَين —\n";
        echo "     فيُسنَد بترتيبِ الجُمَل في المصدر:\n";
        $src   = (string) @file_get_contents($ROOT . '/Contracts/commercial_board.php');
        $pTot  = mb_strpos($src, '$tot  = CBD::totals($rows);');
        $pFilt = mb_strpos($src, "isset(\$_GET['cur'])");
        $check($pTot !== false && $pFilt !== false && $pTot < $pFilt,
            'المجاميعُ تُحسَب قبلَ التصفيةِ في المصدر — لا بعدَها');
    }
    /* ◆ **وعملةٌ فارغةٌ ليست عملةً**: المجاميعُ تتخطّى الصفَّ بلا عملةٍ عمدًا
         («ولا تُجمع عملتان»)، فعددُ البطاقاتِ أقلُّ من عددِ الأسطرِ بطبيعتِه —
         ولا يُقرأ ذلك نقصًا في المراسي. */
    $noCur = $bodyRows - $rowsAfter;
    printf("   ○ وخارجَ «%s» %d سطرًا — منها ما لا عملةَ له، ولا بطاقةَ لغيرِ ذي عملة.\n", $one, $noCur);
}

echo "\n⑤ سالبٌ — عملةٌ غيرُ موجودةٍ لا تُفرغ اللوحة:\n";
list($c5, , $b5) = $req($BASE . '/Contracts/commercial_board.php?all=1&cur=' . rawurlencode('عملةٌ-لا-وجودَ-لها'), $jar);
$rows5 = preg_match_all('~class="cb-drill"~', $b5);
$check($c5 === 200, "تُصيَّر 200 (جاء {$c5})");
$check($rows5 === $bodyRows,
    "واللوحةُ كاملةٌ لا فارغة: {$rows5} من {$bodyRows} — قيمةٌ مجهولةٌ تُهمَل لا تُطبَّق");
$check(preg_match('~cb-total-card cb-total-on~', $b5) === 0, 'ولا بطاقةَ تُوسَم مصفّاةً بالباطل');

echo "\n⑥ سالبٌ — بلا جلسةٍ تُردُّ اللوحة:\n";
$jarNone = tempnam(sys_get_temp_dir(), 'sal20n');
list($c6, $h6, ) = $req($BASE . '/Contracts/commercial_board.php', $jarNone);
$loc6 = preg_match('~^Location:\s*(.+)$~mi', $h6, $m6) ? trim($m6[1]) : '';
$check(($c6 >= 300 && $c6 < 400 && mb_strpos($loc6, 'login') !== false) || $c6 === 403,
    "الزائرُ بلا جلسةٍ يُردّ (رمزٌ {$c6}" . ($loc6 !== '' ? " → {$loc6}" : '') . ')');
@unlink($jarNone);
@unlink($jar);

printf("\n%s  ناجح %d · راسب %d\n", $nBad === 0 ? '✔ SAL-20' : '✘ SAL-20', $nOk, $nBad);
exit($nBad === 0 ? 0 : 1);
