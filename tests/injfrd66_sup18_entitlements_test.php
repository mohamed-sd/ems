<?php
/**
 * tests/injfrd66_sup18_entitlements_test.php — شاهدُ SUP-18: تبويبُ الاستحقاقات
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **إيجابيٌّ ①**: التبويبُ يُصيَّر 200 بجلسةِ إدارةِ الموردين.
 * ◆ **سالبٌ ②**: و**صفرُ حقلِ إدخالٍ في جسمِه** — «صفر استحقاقٍ مُدخَلٍ يدويًّا».
 *   ⚠ **وبحثُ القشرةِ يُستبعَد**: `navFilterInput` في كلِّ صفحة، وعدُّه يجعل
 *   «صفرَ إدخال» مستحيلًا أبدًا (عطبٌ مقيسٌ في `SUP-28` من قبل).
 * ◆ **سالبٌ ③**: و**صفرُ بندِ تنقّل** — «لا شاشةٌ مستقلة» نصُّ المتطلب.
 * ◆ **إيجابيٌّ ④**: والبلوغُ بخطوتَين مقيسٌ لا مُدَّعًى: سجلٌّ حيٌّ ⇐ شريطُ
 *   عائلةٍ ⇐ سلفيات ⇐ شريطُ ملفِّ التسويةِ ⇐ الاستحقاقات.
 * ◆ **سالبٌ ⑤**: و**المرجعُ المعدومُ يُعرَض لا يُخفى**: عددُ ما تُظهره الصفحةُ
 *   موسومًا = عددُ ما تعُدُّه الوصلةُ اليسرى. **ووصلٌ داخليٌّ كان سيُسقطه.**
 * ◆ **إيجابيٌّ ⑥**: و`fin_entitlements` مسجَّلٌ في سجلِّ المستأجِر — وكان
 *   **غائبًا عنه كلِّه**، فردَّت البوابةُ 500 لأوَّلِ قارئٍ له عبرَها.
 *
 * التشغيل: php tests/injfrd66_sup18_entitlements_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$BASE = 'http://localhost/ems';
$PW   = '12345678';
$ROUTE = 'Suppliers/supplier_entitlements.php';

/* ◆ لا يُسمّى العدّادُ `$pass`: `config.php` يُسنِده كلمةَ مرورِ القاعدة */
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
$num = static function (string $sql) use ($conn): int {
    $r = @mysqli_query($conn, $sql);
    return $r ? (int) mysqli_fetch_row($r)[0] : -1;
};

echo "① إيجابيٌّ — التبويبُ يُصيَّر بجلسةِ إدارةِ الموردين:\n";
$r = @mysqli_query($conn, "SELECT username FROM users
                            WHERE COALESCE(role_id, NULLIF(CAST(role AS UNSIGNED),0)) = 2
                              AND is_deleted = 0 AND status IN ('active','1') ORDER BY id LIMIT 1");
$acct = $r ? mysqli_fetch_assoc($r) : null;
if (!$acct) { echo "   ✘ لا حسابَ للدور 2\n\n✘ SUP-18  ناجح 0 · راسب 1\n"; exit(1); }
$nOk++;
printf("   ✔ «%s»\n", $acct['username']);
$jar = tempnam(sys_get_temp_dir(), 'e18');
list(, , $f) = $req($BASE . '/login.php', $jar);
$tok = preg_match('~name="csrf_token"\s+value="([^"]+)"~', $f, $m) ? $m[1] : '';
$req($BASE . '/login.php', $jar,
    array('username' => $acct['username'], 'password' => $PW, 'csrf_token' => $tok));
list($c1, , $b1) = $req($BASE . '/' . $ROUTE, $jar);
$check($c1 === 200, "يُصيَّر برمزِ 200 (جاء {$c1})");
$check(mb_strpos($b1, 'ems-entity-tabs') !== false, 'وشريطُ ملفِّ التسويةِ حاضر');
$check(preg_match('~ems-entity-tab[^"]*is-active[^>]*>\s*الاستحقاقات~u', $b1) === 1,
    'وتبويبُ «الاستحقاقات» موسومٌ نشِطًا');

echo "\n② سالبٌ — صفرُ حقلِ إدخالٍ في الجسم:\n";
/* ◆ **بحثُ القشرةِ يُستبعَد بمعرِّفه**: `navFilterInput` في كلِّ صفحةٍ من
     السايدبار — وعدُّه يجعل «صفرَ إدخال» مستحيلًا أبدًا. */
$body = $b1;
$cut  = mb_strpos($body, 'ems-unified-page-shell');
$body = $cut !== false ? mb_substr($body, $cut) : $body;
$inputs = preg_match_all('~<(?:input|select|textarea)\b~i', $body, $im);
$shell  = preg_match_all('~id="navFilterInput"~', $body);
$check(($inputs - $shell) === 0,
    "حقولُ الإدخالِ في الجسم: " . ($inputs - $shell) . " (خام {$inputs} · قشرة {$shell})");
$check(mb_strpos($b1, 'التصفيةُ روابطُ لا نماذج') !== false,
    'والصفحةُ تُعلن أنَّ التصفيةَ روابطُ لا نماذج');
$check(preg_match('~<form~i', $body) === 0, 'وصفرُ نموذجٍ في الجسم');

echo "\n③ سالبٌ — صفرُ بندِ تنقّل:\n";
$navN = $num("SELECT COUNT(*) FROM nav_items WHERE route LIKE '%supplier_entitlements.php%'");
$check($navN === 0, "صفرُ صفٍّ في `nav_items` (جاء {$navN})");

echo "\n④ إيجابيٌّ — سلسلةُ البلوغِ مقيسةٌ لا مُدَّعاة:\n";
/* ◆ **وشريطُ ملفِّ التسويةِ لا يُصيَّر في `settlements.php`**: له شريطُ عائلةٍ
     آخر، **وإقحامُ ثانٍ يُصيِّر شريطَين** (عطبٌ مقيسٌ في ثمانيةِ أسطحٍ من قبل).
     فالسلسلةُ بخطوتَين — وتُقاس خطوةً خطوة. */
$liveSettle = $num("SELECT COUNT(*) FROM nav_items
                     WHERE role_id = 2 AND active = 1 AND route LIKE '%Suppliers/settlements.php%'");
$check($liveSettle > 0, "① `settlements.php` بندٌ حيٌّ للدور 2 ({$liveSettle})");
list($c4a, , $b4a) = $req($BASE . '/Suppliers/settlements.php', $jar);
$check($c4a === 200 && mb_strpos($b4a, 'supplier_advances.php') !== false,
    '② ويُصيَّر ويحمل رابطَ السلفيات');
list($c4b, , $b4b) = $req($BASE . '/Suppliers/supplier_advances.php', $jar);
$check($c4b === 200, "③ والسلفياتُ تُصيَّر (جاء {$c4b})");
$check(mb_strpos($b4b, 'supplier_entitlements.php') !== false,
    '④ وشريطُ ملفِّ التسويةِ فيها يحمل رابطَ الاستحقاقات');
/* ولا شريطَين في أيٍّ منهما — الفخُّ الذي كلَّف ثمانيةَ أسطحٍ من قبل */
$check(preg_match_all('~class="ems-entity-tabs"~', $b4b) <= 1,
    'وشريطٌ واحدٌ لا شريطان في السلفيات');
$check(preg_match_all('~class="ems-entity-tabs"~', $b1) <= 1,
    'وشريطٌ واحدٌ لا شريطان في الاستحقاقات');

echo "\n⑤ سالبٌ — المرجعُ المعدومُ يُعرَض لا يُخفى:\n";
$outer = $num("SELECT COUNT(*) FROM fin_entitlements e
            LEFT JOIN fin_unit_records u ON u.id = e.unit_record_id AND u.is_deleted = 0
                WHERE u.id IS NULL");
$inner = $num("SELECT COUNT(*) FROM fin_entitlements e
                 JOIN fin_unit_records u ON u.id = e.unit_record_id AND u.is_deleted = 0");
/* ◆ **والعبارةُ ترد مرَّتَين في الصفِّ الواحد**: في `title` وفي متنِ الوسم —
     فعدُّها نصًّا يُبلغ **أربعةً وعشرين وهي اثنا عشر**. والإرساءُ على
     **إغلاقِ الوسمِ** لا على العبارةِ الحرّة. */
$shown = preg_match_all('~— مرجعٌ معدوم</span>~u', $b1);
$check($outer > 0, "المرجعُ المعدومُ في القاعدة: {$outer} (والموصولُ {$inner})");
$check($shown === $outer, "وتُظهره الصفحةُ موسومًا: {$shown} من {$outer}");
$check($shown > 0 && mb_strpos($b1, 'أخطرُ من «غيرِ معتمَد»') !== false,
    'وتقول لماذا هو أخطرُ من «غيرِ معتمَد»');

echo "\n⑥ إيجابيٌّ — `fin_entitlements` مسجَّلٌ في سجلِّ المستأجِر:\n";
/* ◆ **وكان غائبًا عن السجلِّ كلِّه**: فردَّت البوابةُ 500 لأوَّلِ قارئٍ عبرَها.
     وجدولٌ يُقرأ بالاستعلامِ الخامِّ سنينَ **لا يُكتشَف غيابُه** حتى يُقرأ
     بالبوابةِ أوَّلَ مرة. */
$reg = (string) @file_get_contents($ROOT . '/app/Core/TenantRegistry.php');
$check(mb_strpos($reg, "'fin_entitlements'") !== false, 'مُعلَنٌ في `TenantRegistry`');
$check(mb_strpos($b1, 'ent-table') !== false, 'والجدولُ يُقرأ عبرَ البوابةِ ويُصيَّر');
$check(preg_match('~Fatal error|TenantGate~i', $b1) === 0, 'وصفرُ خطأِ بوابةٍ في الصفحة');
@unlink($jar);

echo "\n⑦ سالبٌ — «تبويبٌ لا شاشة» ليس بابًا خلفيًّا:\n";
/* ◆ **وهذا الاستثناءُ يُحرَس من جهتِنا**: `FR-JRN-003` في حزمةِ INJ-FRD-REM-01
     يشترط «صفرَ شاشةٍ أُنشئت»، ومُصنِّفُه كان يفحص **المجلَّدَ وحدَه** بينما
     رأسُه يقول «في مجلَّدِ إدارةٍ **ويُسجَّل في القوائم**». فنُفِّذ نصفُ التعريفِ
     الثاني — **والشرطانِ معًا لا أحدُهما**.
   ◆ **والاستثناءُ الذي لا يُجَسُّ يصير بابًا خلفيًّا**: يكفي أن يُعلَن مسارٌ
     تبويبًا ليمرَّ مهما كان. فيُزرَع بندُ تنقّلٍ لأحدِ التبويبات ويُنتظَر أن
     **يعودَ شاشةً** — وهو ما يحدث. */
$jrn = static function () use ($ROOT) {
    $o = array(); $rc = 0;
    exec('"' . PHP_BINARY . '" '
        . escapeshellarg($ROOT . '/tests/injfrd01_jrn003_006_no_new_screen_unit_chain.php')
        . ' 2>&1', $o, $rc);
    return array($rc, implode("\n", $o));
};
list($rj1, $tj1) = $jrn();
$check(preg_match('~تبويباتٌ في ملفٍّ أمّ: (\d+)~u', $tj1, $tc) === 1 && (int) $tc[1] > 0,
    'المُصنِّفُ يفرز التبويباتِ عن الشاشات (' . (isset($tc[1]) ? $tc[1] : '؟') . ')');
$check(preg_match('~شاشاتٌ: 0~u', $tj1) === 1, 'وصفرُ شاشةٍ أُنشئت');
$check(mb_strpos($tj1, 'Clients/client_contacts.php') !== false,
    'والمستثنى **مسرودٌ بالاسمِ** لا مبتلَع');

@mysqli_query($conn, "DELETE FROM nav_items WHERE route = 'Zz/zz_jrn_probe.php'");
$seed = @mysqli_query($conn, "INSERT INTO nav_items
        (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, active)
        VALUES (2, 'DAILY', 0, 0, 'مسبارٌ — يُزال', 'Clients/client_contacts.php#zzprobe',
                'fa fa-link', 999, 0)");
if (!$seed) { $nBad++; echo "   ✘ تعذّر الزرع: " . mysqli_error($conn) . "\n"; }
else {
    list($rj2, $tj2) = $jrn();
    @mysqli_query($conn, "DELETE FROM nav_items WHERE route LIKE '%#zzprobe'");
    $check(preg_match('~✘ شاشةٌ أُنشئت: Clients/client_contacts\.php~u', $tj2) === 1,
        'وبندُ تنقّلٍ مزروعٍ **يُعيده شاشةً** — فالشرطُ الأول ليس زينة');
    $check($rj2 === 1, "والشاهدُ يرسُب حينَها (رمزٌ {$rj2})");
    $check((int) $num("SELECT COUNT(*) FROM nav_items WHERE route LIKE '%#zzprobe'") === 0,
        'وأُزيل المسبار');
    list($rj3, ) = $jrn();
    $check($rj3 === 0, "وعاد أخضرَ بعدَ الإزالة (رمزٌ {$rj3})");
}
/* ومسارٌ غيرُ مُعلَنٍ في سجلِّ التبويباتِ يبقى شاشةً مهما كان */
$reg = (string) @file_get_contents($ROOT . '/includes/entity_tabs.php');
$check(mb_strpos($reg, 'Suppliers/zz_not_a_tab.php') === false,
    'ومسارٌ غيرُ مُعلَنٍ في سجلِّ التبويباتِ لا يُستثنى أصلًا');
printf("\n%s  ناجح %d · راسب %d\n", $nBad === 0 ? '✔ SUP-18' : '✘ SUP-18', $nOk, $nBad);
exit($nBad === 0 ? 0 : 1);
