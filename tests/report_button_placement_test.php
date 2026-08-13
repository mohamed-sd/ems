<?php
/**
 * tests/report_button_placement_test.php — شاهدُ موضعِ زرِّ الإبلاغِ الاحتياطي
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0518
 *
 * **العيب**: الزرُّ الاحتياطيُّ العالميُّ كان يُبثُّ بـ`register_shutdown_function`،
 * أي **بعد** انتهاءِ تنفيذِ السكربتِ كلِّه — فيقع نصُّه (وعاءٌ ثابتُ الموضعِ فيه
 * نموذجٌ وزر) **بعد وسمِ `</html>` الختامي** في كلِّ شاشةٍ تُغلق وسومَها. وهو
 * مسجَّلٌ في `insidebar.php` فيسري على النظامِ كلِّه.
 *
 * **الإصلاح**: القرارُ («هل استدعت الشاشةُ زرًّا سياقيًّا أغنى؟») لا يُعرف إلا
 * بعد تصييرِ الشاشةِ كلِّها — فلا يصلح نقلُ النداءِ إلى أعلاها. عوضًا عن ذلك
 * يُحجَز المُخرَجُ ويُحقن الزرُّ **قبل `</body>`** عند الإفراغ: القرارُ متأخرٌ
 * كما كان، والموضعُ داخلَ الجسد.
 *
 * ── وثلاثةٌ تُقاس معًا، وإلا فالإصلاحُ يكسر أكثرَ مما يُصلح ─────────────────
 *   ① لا شيءَ بعد `</html>` — وهو نصُّ القبول.
 *   ② والزرُّ **موجودٌ فعلًا** قبل `</body>` — فحذفُه ليس إصلاحًا.
 *   ③ ولا يزدوج: شاشةٌ لها زرُّها السياقيُّ الغنيُّ لا تنال الاحتياطيَّ.
 *   ④ والصفحةُ **غيرُ فارغة** — فأوّلُ صياغةٍ فتحت `ob_start()` داخلَ مُعالِجِ
 *      الحجزِ (وPHP تمنعه) فخرجت الصفحاتُ **صفرَ بايت** كاملةً وهي «تجتاز» ①.
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
$say('══ INJ-0518 · زرُّ الإبلاغِ داخلَ الجسدِ لا بعد `</html>`');

$jar = sys_get_temp_dir() . '/rbplace_' . getmypid() . '.txt';
$http = function ($url, $fields = null) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 120));
    if ($fields !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $fields); }
    $b = (string) curl_exec($ch);
    $c = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array('body' => $b, 'code' => $c);
};
$st = $conn->prepare("SELECT username FROM users WHERE role = '1' AND company_id = ? AND username <> '' ORDER BY id LIMIT 1");
$st->bind_param('i', $CO); $st->execute();
$x = $st->get_result()->fetch_row(); $st->close();
$user = $x ? (string) $x[0] : '';
$ok($user !== '', "وُجد حسابُ الدورِ الأولِ ({$user})");
$b = $http($BASE . '/login.php');
preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $b['body'], $t);
$lb = $http($BASE . '/login.php', http_build_query(array(
    'username' => $user, 'password' => '12345678', 'csrf_token' => isset($t[1]) ? $t[1] : '')));
$ok(mb_strpos($lb['body'], 'name="password"') === false, 'ودخل');

/* ── شاشاتٌ بلا زرٍّ سياقيٍّ (فتنال الاحتياطيَّ) وأخرى لها زرُّها الغني ────── */
$plain = array('main/dashboard.php', 'Equipments/equipments.php', 'Contracts/contracts.php',
               'Finance/payments_fin.php', 'Employees/employees.php');
$rich  = array('Maintenance/orders.php', 'Contracts/claims.php');

$tail = array(); $noBtn = array(); $outside = array(); $tiny = array(); $seen = 0;
foreach (array_merge($plain, $rich) as $rel) {
    $res = $http($BASE . '/' . $rel);
    if ($res['code'] !== 200) { continue; }
    $h = $res['body'];
    /* ◆ حارسُ «هل هذه الشاشةُ أصلًا؟»: صفحةُ الدخولِ تعود بـ200 وبعشرةِ آلافِ
         بايتٍ — فحدُّ الضمورِ وحدَه يبتلعها ويحسبها شاشةً مُصيَّرة. وأوّلُ صياغةٍ
         سقطت في هذا: أعلنت «سبعُ شاشاتٍ قيست» وكلُّها صفحةُ دخولٍ بلا زر. */
    if (mb_strpos($h, 'name="password"') !== false) { $tiny[] = $rel . ' (صفحةُ دخول)'; continue; }
    if (strlen($h) < 2000) { $tiny[] = $rel . ' (' . strlen($h) . ' بايت)'; continue; }
    $seen++;
    $p = strripos($h, '</html>');
    if ($p !== false && trim(substr($h, $p + 7)) !== '') { $tail[] = $rel; }
    $fb = strpos($h, 'ems-report-fallback');
    $bd = strripos($h, '</body>');
    if (in_array($rel, $plain, true)) {
        if ($fb === false) { $noBtn[] = $rel; }
        elseif ($bd === false || $fb > $bd) { $outside[] = $rel; }
    }
}
@unlink($jar);

$ok($seen >= 5, "صُيِّرت {$seen} شاشاتٍ كاملةً يُقاس عليها",
    'صفحاتٌ ضامرةٌ: ' . implode(' · ', $tiny));
$ok(empty($tiny), '**ولا صفحةَ خرجت ضامرةً** — فحجزُ المُخرَجِ لم يبتلع التصيير',
    implode(' · ', $tiny));
$ok(empty($tail), '**ولا عنصرَ بعد `</html>` في أيٍّ منها**', 'بعدها نصٌّ: ' . implode(' · ', $tail));
$ok(empty($noBtn), 'والزرُّ الاحتياطيُّ حاضرٌ في كلِّ شاشةٍ بلا زرٍّ سياقيّ',
    'غائبٌ عن: ' . implode(' · ', $noBtn));
$ok(empty($outside), '**وموضعُه قبل `</body>` — داخلَ الجسد**', 'خارجَه في: ' . implode(' · ', $outside));

/* ── ولا يزدوج مع الزرِّ السياقيِّ الغني ──────────────────────────────────── */
$src = (string) file_get_contents($ROOT . '/includes/report_button.php');
$ok(strpos($src, "if (!empty(\$GLOBALS['__ems_rb_rendered'])) { return ''; }") !== false,
    'وشرطُ عدمِ الازدواجِ قائمٌ: شاشةٌ لها زرُّها الغنيُّ لا تنال الاحتياطي');
$ok(strpos($src, "if (\$pos === false) { return \$html; }") !== false,
    'ومُخرَجٌ بلا `</body>` (JSON · تنزيلٌ · تحويل) **لا يُحقن فيه شيء**');

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
