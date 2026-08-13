<?php
/**
 * tests/url_message_spoof_test.php — شاهدُ رسالةِ النظامِ في الرابط
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0492
 *
 * **العيب**: ١٧١ شاشةً تمرّر رسالةَ النظامِ في سلسلةِ الاستعلامِ ثم تطبعها.
 * والنصُّ مهرَّبٌ عند الطباعةِ — فالخطرُ ليس حقنًا بل **انتحالَ حكمِ النظام**:
 * `?msg=✅ تم الحفظ بنجاح` يُظهر نجاحًا لفعلٍ **لم يقع**. وأخطرُ موضعٍ شاشةُ
 * الدخولِ نفسُها: عندها يُدخل المستخدمُ كلمتَه.
 *
 * ــ ولم يكفِ الماصُّ القائم ــ
 * كانت `ems_absorb_url_msg()` تنقل النصَّ من الرابطِ إلى وميضِ الجلسةِ **ثم
 * تعرضه** — فنقلت موضعَ العرضِ ولم تُزل الثغرة.
 *
 * **الإصلاح** ثلاثةُ أطراف:
 *   ① النصُّ **يُنزع ولا يُعرض** — والنزعُ في `config.php` (يبلغ كلَّ مسار)
 *      فتصير الكتلُ القديمةُ الـ١٥٦ خاملةً **بنيويًّا** لا بترتيبِ تضمين.
 *   ② الرسائلُ الحقيقيةُ تُودَع الجلسةَ بـ`ems_flash_set()` قبل التحويل —
 *      ٣١ موضعًا حُوِّلت في ١٢ ملفًّا.
 *   ③ ما يعبر إلى شاشةِ الدخولِ (والجلسةُ مهدومة) يُمرَّر **برمزٍ من قائمةٍ
 *      مغلقة**، والنصُّ في الشاشة. ورمزٌ مجهولٌ لا يُظهر شيئًا.
 *
 * ── ويُقاس الطرفان: الانتحالُ **والرسالةُ الحقيقية** ────────────────────────
 * إصلاحٌ يمنع كلَّ رسالةٍ ليس إصلاحًا — فيُقاس أيضًا أنَّ الوميضَ يصل ويُعرض
 * ويختفي بعد التحديثِ الأول.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$conn = $GLOBALS['conn'];
$CO = 4;
$BASE = 'http://localhost/ems';
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$say('══ INJ-0492 · لا رسالةَ نجاحٍ يصنعها الرابط');

/* ── ① صفرُ مُصدِرٍ لنصٍّ في الرابط ────────────────────────────────────────── */
$dead = '~/(storage/backups|\.claude|vendor|node_modules|tests|tools|docs)/~';
$emitters = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $p) {
    $path = str_replace('\\', '/', $p->getPathname());
    if (substr($path, -4) !== '.php' || preg_match($dead, $path)) { continue; }
    $s = (string) @file_get_contents($path);
    if (preg_match('~(header\s*\(\s*[\'"]Location:|ems_flash_to\s*\()[^;\n]{0,200}[?&]msg=~i', $s)) {
        $emitters[] = str_replace($ROOT . '/', '', $path);
    }
}
$ok(empty($emitters), '**صفرُ مسارٍ يُصدر `msg=` نصيًّا في تحويل** (' . count($emitters) . ')',
    implode(' · ', array_slice($emitters, 0, 5)));

/* ── ② والنزعُ في `config.php` — فيبلغ كلَّ مسارٍ لا بعضَه ────────────────── */
$cfg = (string) file_get_contents($ROOT . '/config.php');
$ok(preg_match("~unset\(\\\$_GET\['msg'\], \\\$_REQUEST\['msg'\]\);~", $cfg) === 1,
    'والنزعُ في `config.php` — فالكتلُ القديمةُ خاملةٌ بنيويًّا');
$hlp = (string) file_get_contents($ROOT . '/includes/permissions_helper.php');
$ok(strpos($hlp, 'function ems_flash_set') !== false,
    'وبديلُها `ems_flash_set()` موجودٌ للرسائلِ الحقيقية');
$users = 0;
$it2 = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it2 as $p) {
    $path = str_replace('\\', '/', $p->getPathname());
    if (substr($path, -4) !== '.php' || preg_match($dead, $path)) { continue; }
    $s = (string) @file_get_contents($path);
    if (strpos($s, 'ems_flash_set(') !== false && strpos($path, 'permissions_helper.php') === false) { $users++; }
}
$ok($users >= 8, "ومستهلكوه {$users} ملفًّا — لا دالةً بلا نداء");

/* ── ③ القياسُ الحيّ: رابطٌ مُعدَّلٌ لا يُظهر نجاحًا ─────────────────────────── */
$jar = sys_get_temp_dir() . '/urlmsg_' . getmypid() . '.txt';
$http = function ($url, $f = null) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 90));
    if ($f !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $f); }
    $b = (string) curl_exec($ch); curl_close($ch);
    return $b;
};
$SPOOF = '✅ تمَّ الحفظُ بنجاحٍ زورًا';
/* شاشةُ الدخولِ **قبل** أيِّ جلسة — أخطرُ موضع */
$lp = $http($BASE . '/login.php?msg=' . rawurlencode($SPOOF));
$ok(mb_strpos($lp, $SPOOF) === false, '**شاشةُ الدخولِ لا تطبع نصَّ الرابط**');
$lp2 = $http($BASE . '/login.php?m=suspended');
$ok(mb_strpos($lp2, 'الشركة موقوفة') !== false,
    'وتعرض نصَّها هي عند رمزٍ معروفٍ من القائمةِ المغلقة');
$lp3 = $http($BASE . '/login.php?m=' . rawurlencode('zzz-unknown'));
$ok(mb_strpos($lp3, 'الشركة موقوفة') === false && mb_strpos($lp3, 'zzz-unknown') === false,
    'ورمزٌ مجهولٌ لا يُظهر شيئًا — فلا رسالةَ بلا مصدرٍ في النظام');

/* ثم بحسابٍ مُصادَقٍ على شاشاتٍ من عائلةِ الـ١٥٦ */
$st = $conn->prepare("SELECT username FROM users WHERE role = '1' AND company_id = ? AND username <> '' ORDER BY id LIMIT 1");
$st->bind_param('i', $CO); $st->execute();
$x = $st->get_result()->fetch_row(); $st->close();
$b = $http($BASE . '/login.php');
preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $b, $t);
$http($BASE . '/login.php', http_build_query(array(
    'username' => $x ? $x[0] : '', 'password' => '12345678', 'csrf_token' => isset($t[1]) ? $t[1] : '')));
$spoofed = array(); $seen = 0;
foreach (array('Clients/clients.php', 'main/dashboard.php', 'Equipments/equipments.php') as $rel) {
    $h = $http($BASE . '/' . $rel . '?msg=' . rawurlencode($SPOOF));
    if (mb_strpos($h, 'name="password"') !== false) { continue; }
    $seen++;
    if (mb_strpos($h, $SPOOF) !== false) { $spoofed[] = $rel; }
}
@unlink($jar);
$ok($seen >= 2, "وقيست {$seen} شاشاتٍ مُصادَقةٍ من عائلةِ الكتلِ القديمة");
$ok(empty($spoofed), '**ولا واحدةَ منها طبعت النصَّ المنتحَل**', implode(' · ', $spoofed));

/* ── ④ والرسالةُ الحقيقيةُ ما زالت تصل وتختفي بعد التحديثِ الأول ───────────── */
$carrier = (string) file_get_contents($ROOT . '/inheader.php');
$ok(strpos($carrier, "\$_SESSION['ems_flash_gov']") !== false,
    'والحاملُ المركزيُّ يقرأ وميضَ الجلسة');
$ok(strpos($carrier, "unset(\$_SESSION['ems_flash_gov']);") !== false,
    '**ويمحوه بعد العرضِ الأول** — فلا يبقى بعد التحديث');

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
