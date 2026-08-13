<?php
/**
 * tests/nav_stage_label_test.php — شاهدُ عناوينِ مراحلِ السايدبار
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0570
 *
 * **العيب**: عناوينُ المجموعاتِ مُرقَّمةٌ لفظيًّا («أولًا: …» حتى «سابعًا: …»).
 * والرقمُ اللفظيُّ **يَعِد** بتسلسلٍ متصل — فحين لا يملك دورٌ مرحلةً بعينها
 * يقرأ المستخدمُ «رابعًا ثمّ سادسًا» فيظنُّ نقصًا في دورةِ عملِه. ودورُ «إدارة
 * الموقع» (٦) مثالُه الحيّ: ١·٢·٣·٤ ثمّ ٦·٧ — والخامسةُ لا وجودَ لها في دورتِه.
 *
 * **الإصلاح**: العنوانُ يصف الخطوةَ ولا يَعُدُّها. والتسلسلُ محفوظٌ في
 * `stage_no` **للترتيبِ لا للعرض**. ويُقشَّر عند العرضِ لا في البيانات: صفٌّ
 * جديدٌ بترقيمٍ لفظيٍّ يُقشَّر هو أيضًا فلا يعود العيبُ بإدخالِ بيانٍ جديد.
 *
 * ── وحارسُ اللاجدوى ────────────────────────────────────────────────────────
 * لو خلت البياناتُ من الترقيمِ اللفظيِّ أصلًا لمرَّ الفاحصُ بلا مفحوص. فيُشترط
 * هنا أن **يبقى في البياناتِ الخامِّ ترقيمٌ لفظيّ** — فالقشرُ يفعل شيئًا فعلًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
require_once $ROOT . '/includes/unified_nav.php';

$conn = $GLOBALS['conn'];
$CO = 4;
$BASE = 'http://localhost/ems';
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$say('══ INJ-0570 · عنوانُ المرحلةِ يصف الخطوةَ ولا يَعُدُّها');

$ORD = '~(أولًا|أولاً|ثانيًا|ثانياً|ثالثًا|ثالثاً|رابعًا|رابعاً|خامسًا|خامساً|سادسًا|سادساً|سابعًا|سابعاً|ثامنًا|ثامناً|تاسعًا|تاسعاً|عاشرًا|عاشراً)\s*[:：]~u';

/* ── ① البياناتُ الخامُّ ما زالت تحمل الترقيمَ — فالقشرُ يفعل شيئًا ─────────── */
$rawNumbered = 0; $rawTotal = 0;
$r = $conn->query('SELECT DISTINCT stage_title FROM link_groups WHERE is_active = 1 AND stage_title IS NOT NULL');
while ($r && ($x = $r->fetch_row())) {
    $rawTotal++;
    if (preg_match($ORD, (string) $x[0])) { $rawNumbered++; }
}
$ok($rawTotal > 0, "عناوينُ المراحلِ في البياناتِ: {$rawTotal}");
$ok($rawNumbered > 0, "منها {$rawNumbered} بترقيمٍ لفظيٍّ خامٍّ — فللقشرِ عملٌ يُقاس",
    'صفرٌ — فالفاحصُ يمرُّ بلا مفحوص');

/* ── ② والمعروضُ لكلِّ دورٍ بلا ترقيمٍ لفظيٍّ ولا عنوانٍ فارغ ───────────────── */
$roles = array();
$r = $conn->query('SELECT DISTINCT role_id FROM nav_items WHERE active = 1 ORDER BY role_id');
while ($r && ($x = $r->fetch_row())) { $roles[] = (int) $x[0]; }
$numbered = array(); $empty = array(); $stages = 0;
foreach ($roles as $rid) {
    $items = getUnifiedNavItems($conn, $rid);
    $seen = array();
    foreach ($items as $it) {
        if ($it['stage_no'] === null) { continue; }
        $sn = (int) $it['stage_no'];
        if (isset($seen[$sn])) { continue; }
        $seen[$sn] = true;
        $stages++;
        $lbl = ems_nav_stage_label((string) $it['stage_title'], $sn);
        if (preg_match($ORD, $lbl)) { $numbered[] = "دور {$rid}/{$sn}: «{$lbl}»"; }
        if (trim($lbl) === '') { $empty[] = "دور {$rid}/{$sn}"; }
    }
}
$ok($stages > 100, "قيست {$stages} مرحلةً معروضةً عبرَ " . count($roles) . ' دورًا');
$ok(empty($numbered), '**ولا عنوانَ معروضًا بترقيمٍ لفظيّ** (' . count($numbered) . ')',
    implode(' · ', array_slice($numbered, 0, 4)));
$ok(empty($empty), 'ولا عنوانَ فرغَ بالقشر — فالوصفُ باقٍ', implode(' · ', $empty));

/* ── ③ ودورُ «إدارة الموقع» على مُخرَجِ HTTP الحيِّ ────────────────────────── */
$jar = sys_get_temp_dir() . '/navstage_' . getmypid() . '.txt';
$http = function ($url, $f = null) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 90));
    if ($f !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $f); }
    $b = (string) curl_exec($ch); curl_close($ch);
    return $b;
};
$st = $conn->prepare("SELECT username FROM users WHERE role = '6' AND company_id = ? AND username <> '' ORDER BY id LIMIT 1");
$st->bind_param('i', $CO); $st->execute();
$x = $st->get_result()->fetch_row(); $st->close();
$u6 = $x ? (string) $x[0] : '';
$ok($u6 !== '', "وُجد حسابُ «إدارة الموقع» ({$u6})");
$titles = array(); $live = '';
if ($u6 !== '') {
    $b = $http($BASE . '/login.php');
    preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $b, $t);
    $http($BASE . '/login.php', http_build_query(array(
        'username' => $u6, 'password' => '12345678', 'csrf_token' => isset($t[1]) ? $t[1] : '')));
    $live = $http($BASE . '/main/role_board.php');
    if (mb_strpos($live, 'name="password"') === false) {
        preg_match_all('~<span class="nav-group-name">([^<]*)</span>~u', $live, $mm);
        $titles = array_map('trim', $mm[1]);
    }
}
@unlink($jar);
$ok(count($titles) >= 6, 'وصُيِّرت مجموعاتُ سايدبارِه (' . count($titles) . ')');
$liveNumbered = array();
foreach ($titles as $tt) { if (preg_match($ORD, $tt)) { $liveNumbered[] = $tt; } }
$ok(empty($liveNumbered), '**وصفرُ ترقيمٍ لفظيٍّ في سايدبارِه الحيّ**',
    implode(' · ', $liveNumbered));
$ok(count($titles) === count(array_unique($titles)),
    'ولا عنوانَ تكرَّر بعد القشر — فالقشرُ لم يدمج مرحلتين في اسمٍ واحد',
    'مكرَّرٌ: ' . implode(' · ', array_diff_assoc($titles, array_unique($titles))));

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
