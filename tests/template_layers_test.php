<?php
/**
 * tests/template_layers_test.php — شاهدُ طبقاتِ القوالبِ الثلاث
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0499
 *
 * **العيب**: فوقَ القشرةِ الرئيسةِ طبقتا قوالبَ متنافستان — `fin_analysis_shell`
 * (١٧ مستهلكًا) و`u13_screen_kit` (٤٤ مستهلكًا) — ولكلٍّ منهما نمطُ رأسٍ وشرحٍ
 * وفراغٍ ورسالة. **القبول**: الشاشاتُ الثلاثُ (يدويةٌ · مولَّدةٌ · تحليلٌ ماليّ)
 * تعرض الأربعةَ **بالمكوّناتِ نفسِها وبمظهرٍ متطابق**.
 *
 * **المقيسُ**: الطبقتانِ كانتا تُضمّنان القشرةَ والرأسَ والشرحَ من مصدرِها
 * الواحدِ أصلًا — فالخلافُ كان في **الأنماطِ الموضعية**: كتلتانِ داخلَ
 * `u13_screen_kit` تمنحان شاشاتِها مظهرًا خاصًّا. نُقلتا إلى ورقةِ أنماطِ
 * الشاشاتِ الواحدةِ ورُمِّزت ألوانُهما — فصار المظهرُ من المصدرِ نفسِه.
 * والفراغُ والرسالةُ صارا مركزيَّين في العُدَّةِ والقشرة.
 *
 * ── ويُقاس على **مُخرَجِ ثلاثِ شاشاتٍ حيةٍ** من الطبقاتِ الثلاث ─────────────
 * لا على المصدرِ وحدَه: طبقةٌ تُضمّن الرأسَ ثم تكتب رأسًا ثانيًا تمرُّ فحصَ
 * المصدرِ وتسقط في التصيير.
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
$say('══ INJ-0499 · الطبقاتُ الثلاثُ بمكوّناتٍ واحدةٍ ومظهرٍ واحد');

/* ── ① الطبقتانِ تُضمّنان القشرةَ والرأسَ والشرحَ من مصدرِها الواحد ──────────── */
$layers = array(
    'includes/u13_screen_kit.php'    => 'العُدَّةُ المولَّدة',
    'includes/fin_analysis_shell.php' => 'قالبُ التحليلِ المالي',
);
foreach ($layers as $rel => $name) {
    $s = (string) file_get_contents($ROOT . '/' . $rel);
    $ok(strpos($s, 'inheader.php') !== false, "«{$name}» تُضمّن القشرةَ الرئيسة");
    $ok(strpos($s, 'page_header.php') !== false, "  وتستعمل مكوّنَ الرأسِ الموحَّد");
    $ok(strpos($s, 'ems_screen_about') !== false, "  ومكوّنَ الشرح");
    $ok(preg_match_all('~<style\b~i', $s) === 0, "  **وصفرُ كتلةِ `<style>` خاصةٍ بها**",
        'كتل: ' . preg_match_all('~<style\b~i', $s));
}

/* ── ② الفراغُ والرسالةُ مركزيّان — لا نسخةَ في طبقة ────────────────────────── */
$kit = (string) file_get_contents($ROOT . '/assets/js/ui-unification.js');
$ok(strpos($kit, 'window.EmsUI.emptyState({') !== false,
    'والفراغُ من المكوّنِ المركزيِّ في العُدَّة — يسري على الطبقاتِ كلِّها');
$head = (string) file_get_contents($ROOT . '/inheader.php');
$ok(strpos($head, "\$_SESSION['ems_flash_gov']") !== false,
    'والرسالةُ من الحاملِ المركزيِّ في القشرة');
$ok(strpos($head, 'ems-screens.css') !== false,
    'وورقةُ أنماطِ الشاشاتِ محمَّلةٌ من القشرةِ — فالمنقولُ يبلغ الطبقاتِ الثلاث');

/* ── ③ ومُخرَجُ ثلاثِ شاشاتٍ حيةٍ من الطبقاتِ الثلاث ─────────────────────────── */
$jar = sys_get_temp_dir() . '/layers_' . getmypid() . '.txt';
$http = function ($url, $f = null) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 120));
    if ($f !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $f); }
    $b = (string) curl_exec($ch); curl_close($ch);
    return $b;
};
$login = function ($user) use ($jar, $BASE, $http) {
    @unlink($jar);
    $b = $http($BASE . '/login.php');
    preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $b, $t);
    $r = $http($BASE . '/login.php', http_build_query(array(
        'username' => $user, 'password' => '12345678', 'csrf_token' => isset($t[1]) ? $t[1] : '')));
    return mb_strpos($r, 'name="password"') === false;
};
$anyUser = function ($roles) use ($conn, $CO, $login) {
    foreach ($roles as $rid) {
        $st = $conn->prepare("SELECT username FROM users WHERE role = ? AND company_id = ? AND username <> '' ORDER BY id");
        $r = (string) $rid; $st->bind_param('si', $r, $CO); $st->execute();
        $res = $st->get_result();
        while ($res && ($x = $res->fetch_row())) { if ($login((string) $x[0])) { $st->close(); return (string) $x[0]; } }
        $st->close();
    }
    return '';
};

/* المكوّناتُ التي يجب أن تظهر في الثلاثِ جميعًا */
/* ◆ وسمُ الرأسِ هو ما يُصيّره `page_header.php` فعلًا (`main_head` · `head-title`)
     لا صنفَ غلافٍ تضيفه بعضُ الشاشاتِ (`ems-header-shell`) — أوّلُ صياغةٍ بحثت
     عن الغلافِ فأعلنت غيابَ الرأسِ في الطبقاتِ الثلاثِ وهو حاضرٌ فيها كلِّها. */
$MARKERS = array(
    'class="main_head"'  => 'الرأسُ الموحَّد',
    'ems-about-tpl'      => 'قالبُ الشرح',
    'ems-screens.css'    => 'ورقةُ أنماطِ الشاشات',
    'ui-unification.js'  => 'عُدَّةُ الفراغِ والجداول',
);
$SCREENS = array(
    'يدوية'        => array('Contracts/contracts.php', array(1, 2)),
    'مولَّدة'       => array('Finance/acc_my_day.php', array(18, 17, 19)),
    'تحليلٌ ماليّ'  => array('Finance/fin_cashflow_stmt.php', array(17, 18, 19)),
);
$seen = 0; $missing = array();
foreach ($SCREENS as $kind => $cfg) {
    $u = $anyUser($cfg[1]);
    if ($u === '') { $missing[] = $kind . ' (لا حساب)'; continue; }
    $h = $http($BASE . '/' . $cfg[0]);
    if (mb_strpos($h, 'name="password"') !== false) { $missing[] = $kind . ' (محجوبة)'; continue; }
    $seen++;
    foreach ($MARKERS as $needle => $what) {
        if (strpos($h, $needle) === false) { $missing[] = $kind . ' — بلا ' . $what; }
    }
    /* ولا كتلةَ أنماطٍ موضعيةٍ في المُخرَجِ تنقض التوحيد */
    $blocks = preg_match_all('~<style\b~i', $h);
    if ($blocks > 2) { $missing[] = $kind . ' — ' . $blocks . ' كتلةَ أنماطٍ في المُخرَج'; }
}
@unlink($jar);
$ok($seen === count($SCREENS), "صُيِّرت الطبقاتُ الثلاثُ ({$seen}/" . count($SCREENS) . ')',
    implode(' · ', $missing));
$ok(empty($missing), '**والمكوّناتُ الأربعةُ حاضرةٌ في الثلاثِ جميعًا** بمصدرٍ واحد',
    implode(' · ', array_slice($missing, 0, 5)));

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
