<?php
/**
 * tests/contracts_layout_test.php — شاهدُ تخطيطِ شاشةِ العقود
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0441
 *
 * **العيب**: شاشةُ العقودِ (١٨٣٣ سطرًا) تعرض ثمانيةَ أزرارِ مجموعاتٍ متساويةِ
 * الوزنِ وزرَّي فلترٍ، وتحمل سبعَ عشرةَ كتلةَ `<script>`/`<style>` موضعيةً تعيد
 * تنسيقَ `.action-btn` فوقَ ورقةِ أنماطِ النظام.
 * **القبول**: الجدولُ يبدأ ضمن أوّلِ ٣٠٪ من ارتفاعِ الصفحة · وشريطُ الأفعالِ
 * فيه فعلٌ رئيسٌ **واحدٌ** مميَّز · وصفرُ أنماطٍ موضعيةٍ جديدة.
 *
 * ── ولماذا كان الجدولُ يبدأ عند 31.5٪ ─────────────────────────────────────
 * بطاقةُ «عن الشاشة» تُفتح تلقائيًّا في أوّلِ زيارةٍ لكلِّ شاشة — قاعدةٌ صحيحةٌ
 * في شاشةِ نموذجٍ أو لوحة، وخاطئةٌ في شاشةِ **جدول**: المستخدمُ جاءها ليقرأ
 * صفوفَها، والبطاقةُ تدفعها ١٤٦ بكسلًا لأسفل. فصارت تبقى مطويّةً حيث يوجد
 * جدولُ بيانات، وزرُّها في الرأسِ يُبرَز بنبضةٍ تنتهي.
 *
 * **المقيسُ في متصفحٍ حقيقيّ** (بعد مسحِ ذاكرةِ «رُئيت» فالقياسُ لأوّلِ زيارة):
 *   ارتفاعُ الصفحة 3,242 · مبدأُ الجدول 905 ⇒ **27.9٪** (كان 3,412 / 1,075 = 31.5٪).
 *
 * ── وما يحرسه هذا الفاحصُ آليًّا ────────────────────────────────────────────
 * ارتفاعُ الصفحةِ لا يُقاس من CLI. فيُحرَس **سببُه**: القاعدةُ في العُدَّةِ قائمةٌ
 * ومُنادًى بها، والشاشةُ بصفرِ أنماطٍ موضعية، وفعلُها الرئيسُ واحدٌ لا ثمانية.
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
$say('══ INJ-0441 · شاشةُ العقود: البياناتُ أولًا وفعلٌ رئيسٌ واحد');

/* ── ① صفرُ أنماطٍ موضعية ─────────────────────────────────────────────────── */
$src = (string) file_get_contents($ROOT . '/Contracts/contracts.php');
$ok(preg_match_all('~\sstyle\s*=\s*["\']~i', $src) === 0,
    '**صفرُ سمةِ `style=` في شاشةِ العقود**',
    'العدد: ' . preg_match_all('~\sstyle\s*=\s*["\']~i', $src));
$ok(preg_match_all('~<style\b~i', $src) === 0, 'وصفرُ كتلةِ `<style>` موضعية',
    'العدد: ' . preg_match_all('~<style\b~i', $src));

/* ── ② فعلٌ رئيسٌ **واحدٌ** في شريطِ الأفعال ────────────────────────────────── */
$primary = preg_match_all("~'class'\s*=>\s*'add-btn'~", $src);
$ok($primary === 1, "وفعلٌ رئيسٌ واحدٌ مميَّزٌ في شريطِ الأفعال ({$primary})",
    'العدد: ' . $primary);

/* ── ③ القاعدةُ في العُدَّة: الشرحُ لا يسبق البياناتِ في شاشةِ جدول ─────────── */
$ab = (string) file_get_contents($ROOT . '/assets/js/ems-screen-about.js');
$ok(strpos($ab, 'var hasDataTable = false;') !== false,
    'والعُدَّةُ تعرف شاشةَ الجدولِ من غيرِها');
$ok(preg_match('~if \(hasDataTable\) \{[\s\S]{0,200}ems-about-btn--hint~', $ab) === 1,
    '**وتُبقي البطاقةَ مطويّةً فيها** وتُبرز زرَّها بدلَ فتحِها');
$ok(preg_match('~\} else \{\s*\r?\n\s*show\(panel\);~', $ab) === 1,
    'وتفتحها في شاشةٍ بلا جدولٍ — فالقاعدةُ مشروطةٌ لا مُلغاة');
$scr = (string) file_get_contents($ROOT . '/assets/css/ems-screens.css');
$ok(strpos($scr, '.ems-about-btn--hint') !== false, 'ونبضةُ الزرِّ معرَّفةٌ في القشرة');
$ok(strpos($scr, 'prefers-reduced-motion') !== false,
    'ومَن يطلب تقليلَ الحركةِ يُعطى إبرازًا ساكنًا بدلَها');

/* ── ④ ومُخرَجُ HTTP: الشاشةُ تُصيَّر بجدولٍ وبالعُدَّة ──────────────────────── */
$jar = sys_get_temp_dir() . '/ctlay_' . getmypid() . '.txt';
$http = function ($url, $f = null) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 120));
    if ($f !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $f); }
    $b = (string) curl_exec($ch); curl_close($ch);
    return $b;
};
$st = $conn->prepare("SELECT username FROM users WHERE role = '1' AND company_id = ? AND username <> '' ORDER BY id LIMIT 1");
$st->bind_param('i', $CO); $st->execute();
$x = $st->get_result()->fetch_row(); $st->close();
$b = $http($BASE . '/login.php');
preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $b, $t);
$http($BASE . '/login.php', http_build_query(array(
    'username' => $x ? $x[0] : '', 'password' => '12345678', 'csrf_token' => isset($t[1]) ? $t[1] : '')));
$h = $http($BASE . '/Contracts/contracts.php');
@unlink($jar);
$ok(mb_strpos($h, 'name="password"') === false && preg_match('~<thead\b~i', $h) === 1,
    'وتُصيَّر الشاشةُ بجدولٍ ذي ترويسة');
$ok(strpos($h, 'ems-screen-about.js') !== false, 'وتُحمّل عُدَّةَ بطاقةِ الشرح');
$ok(preg_match_all('~\sstyle\s*=\s*["\']~i', $h) < 20,
    'ومُخرَجُها بلا حشوٍ من الأنماطِ الموضعية ('
    . preg_match_all('~\sstyle\s*=\s*["\']~i', $h) . ' من العُدَّةِ لا من المصدر)');

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
