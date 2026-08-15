<?php
/**
 * tests/screen_columns_declared_test.php — العمودُ المعلَنُ له مصدرٌ وخليةٌ ورأس
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0416 · INJ-0417 · INJ-0418 · INJ-0419 · INJ-0317 · INJ-0129
 *
 * · 0416/0417/0418/0419: «عددُ أعمدة الجدول في الشاشة = ٢٣/٣٠/٣١/١٩ ويطابق
 *   أسماءَ الوثيقة عمودًا عمودًا».
 * · 0317: «لكل `<th>` في الجدول `<td>` مقابلٌ **بقيمةٍ من الخادم** أو لا يظهر
 *   الرأسُ أصلًا؛ وبوابةٌ تعيد صفرَ عمودٍ بلا مصدر».
 * · 0129: «بعد تسجيلِ خطرٍ جديدٍ في `risk_register` تُظهر لوحةُ الرئيسِ الرقمَ
 *   الجديدَ **بلا أيِّ إدخالٍ يدوي**، ولا يوجد في الصفحةِ حقلُ إدخالٍ باسم `f12`».
 *
 * ── ولماذا يُقاس على HTML الحيِّ لا على المصدر ────────────────────────────
 * «عددُ أعمدة الجدول **في الشاشة**» حكمٌ على ما يراه القارئُ لا على مصفوفةٍ في
 * ملفّ. فالمقياسُ طلبُ HTTP بفاعلٍ حقيقيٍّ، وعدُّ `<th>` مقابلَ `<td>` في صفٍّ
 * حقيقيّ. ولو رُفع رأسٌ وبقيت خليتُه لَانزاح الجدولُ كلُّه — والعدُّ يكشفه.
 *
 * ◆ **والاختبارُ السلبيُّ (GT-01)** على مؤشرِ الخطر: يُسجَّل خطرٌ حقيقيٌّ بوسمٍ
 *   عائليٍّ ثابتٍ فيجب أن **يزيد الرقمُ في اللوحةِ بلا إدخال**، ثم يُكنس ويعود.
 *   ولو كان الرقمُ مخزَّنًا لَما تحرّك — فالحركةُ نفسُها برهانُ الاشتقاق.
 * ◆ ويُفحص مُرجَعُ كلِّ حذفٍ لأنَّ FK يردُّ صامتًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
ob_start(); require_once $ROOT . '/config.php'; ob_end_clean();
while (ob_get_level() > 0) { ob_end_clean(); }

$conn = $GLOBALS['conn'];
$CO   = 4;
$TAG  = 'COLTEST-FAMILY';
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$say('══ العمودُ المعلَنُ له مصدرٌ وخليةٌ ورأس');

/* ── عدّةُ HTTP ──────────────────────────────────────────────────────────── */
$BASE = 'http://localhost/ems';
$req = function ($url, $jar, $post = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 45));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $raw = curl_exec($ch);
    $hs  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $c   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array($c, substr((string) $raw, 0, $hs), substr((string) $raw, $hs));
};
$login = function ($user) use ($BASE, $req) {
    $jar = sys_get_temp_dir() . '/coltest_' . md5($user) . '.cookie';
    @unlink($jar);
    list(, , $b) = $req($BASE . '/login.php', $jar);
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
    $req($BASE . '/login.php', $jar, array('username' => $user, 'password' => '12345678',
        'csrf_token' => isset($m[1]) ? $m[1] : ''));
    return $jar;
};
list($up) = $req($BASE . '/login.php', sys_get_temp_dir() . '/coltest_ping.cookie');
if ($up === 0) { $say('  ⓘ Apache متوقفٌ — لا يُدّعى نجاحٌ ولا يُخفى تخطٍّ'); $say("PASS=0 · FAIL=1"); exit(1); }

/* ── ① عددُ الأعمدةِ في الشاشةِ = ما تعلنه الوثيقة، ولكلِّ رأسٍ خلية ─────── */
$say("\n── ① عددُ الأعمدةِ = ما تعلنه الوثيقة · ولكلِّ رأسٍ خليةٌ من الخادم");
$EXPECT = array(
    array('Portal/ceo_approvals.php',          'تنفيذ',    23, 'INJ-0416'),
    array('Portal/ceo_contracts.php',          'تنفيذ',    30, 'INJ-0417'),
    array('Portal/project_charter.php',        'تنفيذ',    31, 'INJ-0418'),
    array('Portal/ceo_risk.php',               'تنفيذ',    19, 'INJ-0419'),
    array('Transport/transfer_close_cost.php', 'مديرمالي', null, 'INJ-0317'),
);
$jars = array();
foreach ($EXPECT as $e) { if (!isset($jars[$e[1]])) { $jars[$e[1]] = $login($e[1]); } }

foreach ($EXPECT as $e) {
    list($rel, $user, $want, $inj) = $e;
    list($c, , $body) = $req($BASE . '/' . $rel, $jars[$user]);
    if ($c !== 200) { $ok(false, $inj . ' · ' . $rel, 'رمز=' . $c); continue; }
    $nth = 0;
    if (preg_match_all('~<thead\b.*?</thead>~is', $body, $hm)) {
        foreach ($hm[0] as $h) { $nth = max($nth, preg_match_all('~<th\b~i', $h)); }
    }
    $ntd = 0; $rows = 0;
    if (preg_match('~<tbody\b.*?</tbody>~is', $body, $bm)) {
        $rows = preg_match_all('~<tr\b~i', $bm[0]);
        if (preg_match('~<tr\b[^>]*>(.*?)</tr>~is', $bm[0], $rm)) { $ntd = preg_match_all('~<td\b~i', $rm[1]); }
    }
    if ($want !== null) {
        $ok($nth === $want, $inj . ' · ' . basename($rel) . ' — ' . $nth . ' عمودًا (الوثيقةُ تعلن ' . $want . ')');
    }
    $ok($rows > 0, $inj . ' · وثمَّ صفوفٌ حقيقيةٌ يُقاس عليها (' . $rows . ')');
    $ok($ntd === $nth,
        $inj . ' · **لكلِّ رأسٍ خليةٌ من الخادم**: رؤوس=' . $nth . ' خلايا=' . $ntd,
        'الفرق=' . ($nth - $ntd));
}

/* والتوثيقُ: الرؤوسُ الثمانيةُ التي رُفعت من شاشةِ الإقفالِ لا تعود */
$trs = (string) @file_get_contents($ROOT . '/Transport/transfer_close_cost.php');
$goneOk = true; $back = array();
foreach (array('اعتمده مدير النقل', 'اعتمدته المالية', 'رقم القيد', 'مرجع التفويض',
               'تاريخ الاعتماد', 'معكوس بـ', 'عكس عن', 'درجة الأثر') as $g) {
    if (preg_match('~<th[^>]*>\s*' . preg_quote($g, '~') . '\s*</th>~u', $trs)) { $goneOk = false; $back[] = $g; }
}
$ok($goneOk, 'INJ-0317 · والرؤوسُ الثمانيةُ بلا مصدرٍ لم تعد — «أو لا يظهر الرأسُ أصلًا»',
    implode(' · ', $back));

/* ── ② الحارسُ العامُّ على المستودع ───────────────────────────────────────── */
$say("\n── ② الحارسُ العامُّ: الإعلانُ والمصدرُ والرأسُ ثلاثتُها متطابقة");
$o = array(); $rc = 1;
@exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($ROOT . '/tools/fix_screen_columns_scan.php') . ' 2>&1', $o, $rc);
$txt = implode("\n", $o);
$ok($rc === 0, 'يمرُّ على الشجرةِ النظيفة (خروج=' . $rc . ')',
    mb_substr(preg_replace('~\s+~', ' ', $txt), 0, 200));
$ok(preg_match('~شاشاتٌ مقيسة: (\d+) · سليمةٌ: (\d+)~u', $txt, $sm)
    && (int) $sm[1] >= 40 && $sm[1] === $sm[2],
    'ويقيس ' . (isset($sm[1]) ? $sm[1] : '؟') . ' شاشةً كلُّها سليمة');

/* ── ③ INJ-0129: المؤشرُ يُشتقُّ — والاختبارُ السلبيُّ يُحرّكه ──────────────── */
$say("\n── ③ «المخاطر المفتوحة» مشتقٌّ لا مكتوب (INJ-0129)");
$board = (string) @file_get_contents($ROOT . '/Portal/ceo_board.php');
$ok(!preg_match('~name="f12"~', $board), '«ولا يوجد في الصفحةِ حقلُ إدخالٍ باسم `f12`» — نصُّ القبولِ حرفًا');
$ok(!preg_match('~name="f13"~', $board), 'ولا `f13` — توأمُ العيبِ رُفع معه');
$ok(strpos($board, "'open_risks', 'pending_approvals'") !== false,
    'والمعالجُ يرفضهما لو أُرسلا بيدٍ أخرى — الحارسُ في الطبقةِ لا في الفورم (CS-11)');

$sweep = function () use ($conn, $TAG) {
    $r = $conn->query("DELETE FROM risk_register WHERE company_id = 4 AND title LIKE '%{$TAG}%'");
    if ($r === false) { return -1; }
    $q = $conn->query("SELECT COUNT(*) FROM risk_register WHERE company_id = 4 AND title LIKE '%{$TAG}%'");
    return ($q && ($x = $q->fetch_row())) ? (int) $x[0] : -1;
};
$ok($sweep() === 0, 'الكنسُ القبليُّ نظيفٌ بالعائلة');

$openNow = function () use ($conn) {
    $r = $conn->query("SELECT COUNT(*) FROM risk_register
                        WHERE company_id = 4 AND state <> 'closed' AND merged_into_id IS NULL");
    return ($r && ($x = $r->fetch_row())) ? (int) $x[0] : -1;
};
$readBoard = function () use ($BASE, $req, $jars) {
    list($c, , $b) = $req($BASE . '/Portal/ceo_board.php', $jars['تنفيذ']);
    if ($c !== 200) { return array(-1, $c); }
    /* «المخاطر المفتوحة» العمودُ الرابعَ عشرَ — تُقرأ خليتُه من أوّلِ صفّ */
    if (!preg_match('~<tbody\b.*?</tbody>~is', $b, $bm)) { return array(-1, $c); }
    if (!preg_match('~<tr\b[^>]*>(.*?)</tr>~is', $bm[0], $rm)) { return array(-1, $c); }
    preg_match_all('~<td[^>]*>(.*?)</td>~is', $rm[1], $cm);
    return array(isset($cm[1][13]) ? (int) trim(strip_tags($cm[1][13])) : -1, $c);
};
$before = $openNow();
list($shown, $code) = $readBoard();
$ok($code === 200, 'فُتحت لوحةُ الرئيسِ برمزِ ٢٠٠ (رمز=' . $code . ')');
$ok($shown === $before,
    'والرقمُ المعروضُ = عددُ المفتوحِ في `risk_register`: ' . $shown . ' = ' . $before
    . ' — **رقمانِ متطابقانِ في موضعين**');

/* يُسجَّل خطرٌ حقيقيٌّ فيجب أن يتحرّك الرقمُ بلا إدخالٍ في اللوحة */
$ru = 0;
$r = $conn->query('SELECT id FROM risk_units ORDER BY id LIMIT 1');
if ($r && ($x = $r->fetch_row())) { $ru = (int) $x[0]; }
$code9 = 'RSK-' . $TAG;
$ins = $conn->query("INSERT INTO risk_register
        (company_id, risk_code, ru_id, title, description, scope_type, state,
         current_level, dedup_key, created_by, created_at)
      VALUES (4, '{$code9}', " . ($ru ?: 'NULL') . ", 'خطرُ شاهدٍ {$TAG}', 'يُكنس بعد القياس',
              'مؤسسي', 'classified', 'متوسط', 'dedup-{$TAG}', 1, NOW())");
$ok($ins !== false && $conn->affected_rows === 1, 'سُجّل خطرٌ جديدٌ في `risk_register`', $conn->error);

$after = $openNow();
list($shown2, ) = $readBoard();
$ok($after === $before + 1, 'وارتفع عددُ المفتوحِ في القاعدة: ' . $before . ' ⇒ ' . $after);
$ok($shown2 === $after,
    '«**تُظهر لوحةُ الرئيسِ الرقمَ الجديدَ بلا أيِّ إدخالٍ يدوي**»: ' . $shown . ' ⇒ ' . $shown2,
    'المتوقَّع=' . $after);
$ok($shown2 !== $shown, 'وتحرَّك فعلًا — ولو كان مخزَّنًا لَجمد (وهذا هو الاختبارُ السلبيُّ للاشتقاق)');

$say("\n── الكنسُ البعديّ");
$left = $sweep();
$ok($left === 0, 'كُنست عائلةُ الوسمِ كاملةً', 'المتبقّي=' . $left);
list($shown3, ) = $readBoard();
$ok($shown3 === $before, 'وعاد الرقمُ إلى ما كان: ' . $shown3 . ' = ' . $before);
foreach ($jars as $j) { @unlink($j); }

$say("\n══ النتيجة: ناجحٌ {$PASS} · راسبٌ {$FAIL}");
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL > 0 ? 1 : 0);
