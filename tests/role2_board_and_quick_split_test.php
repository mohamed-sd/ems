<?php
/**
 * tests/role2_board_and_quick_split_test.php
 *   الدورُ 2 — عدّادُ الاعتمادِ الصادق · وفصلُ البلاطاتِ عن السايدبار
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **إيجابيٌّ ①**: البطاقةُ تُصيَّر بالرقمِ الذي يُنتجه تعريفُها **الأصليّ**
 *   (أُعيد بقرارِ المالك بعدَ تجربةِ مصدرَين آخرَين).
 * ◆ **خبرٌ ②**: ويُقيَّد ما قِيس أثناءَ التجربةِ — البذرةُ ودفترُ الاعتماداتِ
 *   ومستواه الثاني — **لئلّا يُعاد اكتشافُه** إن أُريد يومًا رقمٌ آخر.
 * ◆ **سالبٌ ③**: و**تعريفٌ واحدٌ لا ثلاثة** — البطاقةُ والمهمّةُ والتنبيهُ
 *   تنادي الدالةَ نفسَها، وصفرُ نسخةٍ حرفيةٍ باقية.
 * ◆ **سالبٌ ④**: والتعريفُ يقرأ الدفترَ ولا يبني وصلًا فرعيًّا — فالبوابةُ
 *   ترفض جدولًا غيرَ مُعلَنٍ **ويبتلع الحارسُ الخطأَ فيعود صفرًا**.
 * ◆ **إيجابيٌّ ⑤**: البلاطاتُ ستٌّ والسايدبارُ عشرةٌ — و**صفرُ رابطٍ في
 *   القائمتَين معًا**، وهو جوهرُ الطلب.
 * ◆ **سالبٌ ⑥**: والسايدبارُ عند حدِّ البوابةِ بالضبط (10) — فنقلُ رابطٍ سابعٍ
 *   يُرسِّب «سايدبارًا نحيفًا». يُقاس ويُعلَن ولا يُترك مفاجأةً.
 *
 * التشغيل: php tests/role2_board_and_quick_split_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$BASE = 'http://localhost/ems';
$PW   = '12345678';

/* ◆ لا يُسمّى العدّادُ `$pass`: `config.php` يُسنِده كلمةَ مرورِ القاعدة */
$nOk = 0; $nBad = 0;
$check = static function (bool $ok, string $msg) use (&$nOk, &$nBad): void {
    if ($ok) { $nOk++; echo "   ✔ {$msg}\n"; } else { $nBad++; echo "   ✘ {$msg}\n"; }
};
$req = static function (string $url, string $jar, array $post = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 60,
    ));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $raw = (string) curl_exec($ch);
    $hs  = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $c   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array($c, substr($raw, 0, $hs), substr($raw, $hs));
};

$_SERVER['SCRIPT_NAME'] = '/ems/main/dashboard.php';
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$num = static function (string $sql) use ($conn): int {
    $r = @mysqli_query($conn, $sql);
    return $r ? (int) mysqli_fetch_row($r)[0] : -1;
};

$MOVED = array(
    'Suppliers/suppliers.php', 'Suppliers/supplierscontracts.php',
    'Suppliers/unit_statement_supplier.php', 'Suppliers/settlements.php',
    'Suppliers/shares_coverage.php', 'Suppliers/rfq_requests.php',
);

echo "① إيجابيٌّ — اللوحةُ تُصيَّر بجلسةِ إدارةِ الموردين:\n";
$r = @mysqli_query($conn, "SELECT username, company_id FROM users
                            WHERE COALESCE(role_id, NULLIF(CAST(role AS UNSIGNED),0)) = 2
                              AND is_deleted = 0 AND status IN ('active','1') ORDER BY id LIMIT 1");
$acct = $r ? mysqli_fetch_assoc($r) : null;
if (!$acct) { echo "   ✘ لا حسابَ للدور 2\n\n✘ راسب\n"; exit(1); }
$nOk++; printf("   ✔ «%s» · شركة %s\n", $acct['username'], $acct['company_id']);
$CO  = (int) $acct['company_id'];
$jar = tempnam(sys_get_temp_dir(), 'r2b');
list(, , $f) = $req($BASE . '/login.php', $jar);
$tok = preg_match('~name="csrf_token"\s+value="([^"]+)"~', $f, $m) ? $m[1] : '';
$req($BASE . '/login.php', $jar, array('username' => $acct['username'], 'password' => $PW, 'csrf_token' => $tok));
list($c1, , $b1) = $req($BASE . '/main/dashboard.php', $jar);
$check($c1 === 200, "لوحةُ التحكمِ تُصيَّر 200 (جاء {$c1})");

echo "\n② إيجابيٌّ — البطاقةُ تطابق تعريفَها الأصليّ:\n";
/* ◆ **الرقمُ أُعيد إلى ما كان بقرارِ المالك** بعدَ تجربةِ مصدرَين آخرَين.
     وهذا الشاهدُ لا يحكم على **اختيارِ** التعريفِ — يحكم على أنَّ المُصيَّرَ
     يطابق ما تقوله الجملةُ فعلًا، **ويُقيِّد ما قِيس** لئلّا يُعاد اكتشافُه. */
$card = $num("SELECT COUNT(*) FROM unit_entries WHERE company_id={$CO} AND state='site_approved'");
$vals = preg_match_all('~ems-kpi-value">([0-9.,]+)~', $b1, $vm) ? $vm[1] : array();
printf("   ○ التعريف: %d · القيمُ المُصيَّرة: %s\n", $card, implode(' · ', $vals));
$check($card > 0, 'المقامُ غيرُ صفريّ');
$check(in_array((string) $card, $vals, true), "واللوحةُ تعرض {$card} — لا رقمًا آخر");
$check(mb_strpos($b1, 'وحداتٌ تنتظر أطرافَ الاعتماد') !== false, 'وعنوانُها الأصليُّ قائم');

echo "\n   ── خبرٌ مقيَّدٌ لا حكم:\n";
/* ◆ **يُقيَّد ولا يُغيَّر**: هذان البابانِ قِيسا أثناءَ التجربة، وإبقاؤهما مكتوبَين
     يوفّر إعادةَ الاكتشافِ إن أُريد يومًا رقمٌ يقيس عملًا لا بذرة. */
$real = $num("SELECT COUNT(*) FROM unit_entries WHERE company_id={$CO}
               AND state='site_approved' AND seed_tag IS NULL");
printf("      · من %d صفًّا **%d بذرةُ تهيئة** والصادقُ %d\n", $card, $card - $real, $real);
$appr = $num("SELECT COUNT(*) FROM timesheet_approvals WHERE company_id={$CO}");
$lvl2 = $num("SELECT COUNT(*) FROM timesheet_approvals WHERE company_id={$CO} AND approval_level=2");
printf("      · ودفترُ الاعتماداتِ %d · **مستواه الثاني %d**\n", $appr, $lvl2);
$check($card > $real && $appr > 0 && $lvl2 > 0, 'والبابانِ ما يزالان مقيسَين — لا يُعادُ اكتشافُهما');
echo "\n③ سالبٌ — تعريفٌ واحدٌ لا ثلاث نسخ:\n";
$rb = (string) @file_get_contents($ROOT . '/includes/role_board.php');
$calls = preg_match_all('~roleBoardSupplierPendingSql\(\)~', $rb);
/* ◆ **والنسخةُ تُعَدُّ على الجملةِ لا على الجدول**: اسمُ الجدولِ يَرِد في
     إعلانِ النطاقِ وفي التعليلِ — والمكرَّرُ الذي يُخشى هو **جملةُ الاستعلام**. */
/* ◆ **والعدُّ على الجملةِ المميِّزةِ لا على اسمِ الجدول**: `unit_entries`
     يَرِد في بطاقاتِ أدوارٍ أخرى — والمكرَّرُ الذي يُخشى هو **شرطُ هذه
     البطاقةِ بعينِه**. (فحصٌ على اسمِ الجدولِ أعطى 14 وهو رقمُ أدوارٍ أخرى.) */
$copies = preg_match_all("~ue\.state='site_approved'~", $rb);
$check(preg_match('~function roleBoardSupplierPendingSql~', $rb) === 1, 'الدالةُ مُعرَّفةٌ مرَّةً واحدة');
$check($calls >= 3, "وتُنادى في {$calls} مواضع (البطاقة · المهمّة · التنبيه)");
$check($copies === 1, "وجملةُ الاستعلامِ مكتوبةٌ مرَّةً واحدة (مواضعُها: {$copies})");

echo "\n④ سالبٌ — التعريفُ يقرأ الدفترَ ولا يبني وصلًا فرعيًّا:\n";
$body = '';
if (preg_match('~function roleBoardSupplierPendingSql\(\)\s*\{(.*?)\n\}~s', $rb, $fm)) { $body = $fm[1]; }
$body = preg_replace('~/\*.*?\*/~s', '', $body);   /* التعليقاتُ تُنزَع قبلَ الفحص */
$check($body !== '' && mb_strpos($body, 'unit_entries') !== false,
    'جسدُ الدالةِ يقرأ `unit_entries` — التعريفُ الأصليّ');
$check($body !== '' && mb_strpos($body, 'timesheet_approvals') === false,
    'ولا مصدرَ ثانيًا فيه');
/* ◆ والبوابةُ ترفض الوصلَ الفرعيَّ على جدولٍ غيرِ مُعلَن — ولو مرَّ لعاد صفرًا
     صامتًا: **عدّادٌ يُظهر صفرًا لأنَّ استعلامَه رُفض لا لأنَّ العملَ انتهى.** */
$check($body !== '' && mb_strpos($body, 'NOT EXISTS') === false,
    'وصفرُ استعلامٍ فرعيٍّ فيه');
$check(mb_strpos($rb, "'t' => 'unit_entries', 'a' => 'ue'), roleBoardSupplierPendingSql()") !== false,
    'وإعلانُ النطاقِ يتبع الجدولَ نفسَه');
echo "\n⑤ إيجابيٌّ — البلاطاتُ والسايدبارُ لا يلتقيان:\n";
preg_match_all('~<a href="[^"]*?([A-Za-z_]+/[a-z_0-9]+\.php)" class="shot-hex-link">~', $b1, $tm);
$tiles = array_values(array_unique($tm[1]));
printf("   ○ بلاطات: %d — %s\n", count($tiles), implode('، ', $tiles));
$check(count($tiles) === 6, 'ستُّ بلاطاتٍ للعملِ اليوميّ');
foreach ($MOVED as $r0) {
    if (!in_array($r0, $tiles, true)) { $nBad++; echo "   ✘ بلاطةٌ ناقصة: {$r0}\n"; }
}
$live = $num("SELECT COUNT(*) FROM nav_items WHERE role_id=2 AND active=1");
$check($live === 10, "والسايدبارُ عشرةُ روابطَ حيّة (جاء {$live})");
$dup = $num("SELECT COUNT(*) FROM modules m
               JOIN nav_items n ON n.route = m.code AND n.role_id = 2 AND n.active = 1
              WHERE m.owner_role_id = 2 AND m.is_quick = 1 AND m.is_link = 1");
$check($dup === 0, "صفرُ رابطٍ في القائمتَين معًا (جاء {$dup})");
/* ويُقاس على المُصيَّرِ أيضًا لا على السجلِّ وحدَه */
$cut = mb_strpos($b1, 'sidebarNavList');
/* ◆ **وحدُّ القصِّ نهايةُ السايدبارِ لا طولٌ مقدَّر**: قصٌّ بستّينَ ألفَ حرفٍ
     يتجاوز القائمةَ إلى جسمِ الصفحةِ **حيث البلاطاتُ نفسُها** — فيجدها
     ويُبلغ أنَّها في السايدبار. رقمٌ يقيس موضعًا غيرَ الذي يسمّيه. */
$sbEnd = mb_strpos($b1, 'تسجيل الخروج', $cut === false ? 0 : $cut);
$sb  = ($cut !== false && $sbEnd !== false) ? mb_substr($b1, $cut, $sbEnd - $cut) : '';
$sb  = preg_replace('~<!--.*?-->~s', '', $sb);   /* التعليقاتُ تُنزَع قبلَ العدّ */
$hit = array();
foreach ($MOVED as $r0) { if (mb_strpos($sb, $r0) !== false) { $hit[] = $r0; } }
$check(!$hit, 'ولا واحدٌ منها يُصيَّر في السايدبار' . ($hit ? ': ' . implode('، ', $hit) : ''));

echo "\n⑥ سالبٌ — السايدبارُ عند حدِّ البوابةِ بالضبط:\n";
$check($live >= 10, "عشرةٌ وحدُّ «السايدبار النحيف» عشرةٌ — **فنقلُ رابطٍ سابعٍ يُرسِّب**");
$thin = $num("SELECT COUNT(*) FROM (SELECT n.role_id FROM nav_items n
                JOIN roles r ON r.id=n.role_id AND r.status=1
               WHERE n.active=1 GROUP BY n.role_id HAVING COUNT(*) < 10) x");
$check($thin === 0, "وصفرُ دورٍ تحتَ الحدِّ في النظامِ كلِّه (جاء {$thin})");
@unlink($jar);

printf("\n%s  ناجح %d · راسب %d\n", $nBad === 0 ? '✔ الدور 2' : '✘ الدور 2', $nOk, $nBad);
exit($nBad === 0 ? 0 : 1);
