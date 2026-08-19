<?php
/**
 * tests/tkt_dept_tab_http_proof.php — برهانٌ حيٌّ: التبويبُ يُصيَّر ويُفتح ويعدّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ لماذا برهانٌ حيٌّ لا فحصٌ ساكن: العطبُ السابقُ في هذا النظامِ نفسِه كان
 *   «تاباتٌ تُصيَّر وعدّاداتُها صحيحةٌ **ولا تفتح**» — لأنّ مُخنِقًا عامًّا
 *   يردُّ 302 قبل أن يصل الطلبُ إلى منطقِ الشاشة. فالفحصُ الساكنُ يشهد أنّ
 *   الشيفرةَ مكتوبةٌ صحيحًا ولا يشهد أنّ المستخدمَ يراها. **يُقاس رأسُ
 *   الاستجابةِ قبل الحكمِ على الاستعلام.**
 *
 * ◆ ويقيس أيضًا ما لا يُرى إلا بجلسةٍ حقيقيةٍ لدورٍ حقيقي: أنّ عدّادَ التبويبِ
 *   موجبٌ لكلِّ إدارةٍ وُجِّهت إليها بلاغات — فـ«مبنيّةٌ وممنوحة» ≠ «يصلها أحد».
 *
 * ◆ يتطلّب Apache حيًّا على http://localhost/ems
 *   php tests/tkt_dept_tab_http_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$BASE = 'http://localhost/ems';
$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m, $d = '') { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✖ {$m}" . ($d !== '' ? " — {$d}" : '') . "\n"); }
function ck($m, $c, $d = '') { $c ? ok($m) : bad($m, $d); }

/** الطلبُ بلا متابعةِ تحويل — الرأسُ نفسُه هو الشهادة. */
function req($url, $jar, $post = null)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 40,
    ));
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw  = curl_exec($ch);
    $hs   = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false) { return array(0, '', ''); }
    return array($code, substr($raw, 0, $hs), substr($raw, $hs));
}
/**
 * الدخولُ بالعربيةِ يمرُّ بـcURL في PHP لا بالصدفة — الترميزُ يُبتر هناك.
 * ورمزُ CSRF يُقرأ من صفحةِ النموذجِ أولًا: بدونه يُردُّ الطلبُ إلى login.php
 * فتُقرأ الجلسةُ الغائبةُ «شاشةً محجوبة» — فشلٌ كاذبٌ يُدين منتجًا سليمًا.
 */
function login($base, $user, $jar)
{
    if (file_exists($jar)) { @unlink($jar); }
    list(, , $b) = req($base . '/login.php', $jar);
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
    return req($base . '/login.php', $jar, array(
        'username'   => $user,
        'password'   => '12345678',
        'csrf_token' => isset($m[1]) ? $m[1] : '',
    ));
}

echo "═══ برهانٌ حيٌّ · تبويبُ «موجَّهة لإدارتي» ═══\n\n";

// حيويةُ الخادم قبل أيِّ حكم — وإلا قرأنا سكونَ Apache نجاحًا أو فشلًا كاذبًا
list($c0) = req($BASE . '/login.php', sys_get_temp_dir() . '/tkt_ping.jar');
if ($c0 === 0) { exit("✖ Apache لا يستجيب على {$BASE} — البرهانُ الحيُّ متعذِّر\n"); }

$ROLES = array(
    'مبيعات'          => 'ادارة المبيعات',
    'صيانة'           => 'ادارة الصيانة',
    'مشرف المشتريات' => 'إدارة المشتريات',
    'أمين المستودع'   => 'أمين المستودع',
    'مدير القوى'      => 'القوى التشغيلية',
    'مشرف النقل'    => 'إدارة النقل والترحيل',
);

foreach ($ROLES as $user => $roleName) {
    echo "── {$roleName} ({$user})\n";
    $jar = sys_get_temp_dir() . '/tkt_dept_' . md5($user) . '.jar';
    list($lc, $lh) = login($BASE, $user, $jar);
    if ($lc !== 200 && $lc !== 302) { bad("الدخول", "HTTP {$lc}"); continue; }

    // ① التبويبُ يُفتح — لا 302 إلى لوحةٍ ولا 403
    list($code, $head, $body) = req($BASE . '/Tickets/tickets_list.php?tab=dept', $jar);
    $loc = preg_match('~^Location:\s*(.+)$~mi', $head, $m) ? trim($m[1]) : '';
    ck('الشاشةُ تُفتح (200 لا تحويل)', $code === 200, "HTTP {$code}" . ($loc ? " → {$loc}" : ''));
    if ($code !== 200) { continue; }

    // ② التبويبُ مُصيَّرٌ ونشط
    ck('التبويبُ مُصيَّر', strpos($body, 'موجَّهة لإدارتي') !== false);
    ck('التبويبُ نشطٌ عند طلبِه',
       preg_match('~tab=dept"[^>]*class="[^"]*is-active~', $body) === 1);

    // ③ العدّادُ موجب — «مبنيّةٌ وممنوحة» ≠ «يصلها أحد»
    $n = null;
    if (preg_match('~tab=dept".*?موجَّهة لإدارتي\s*<span class="badge[^"]*">(\d+)</span>~s', $body, $mm)) {
        $n = (int) $mm[1];
    }
    ck('عدّادُ التبويبِ موجب' . ($n === null ? '' : " ({$n})"), $n !== null && $n > 0,
       $n === null ? 'تعذّر قراءةُ العدّاد' : "المقيس={$n}");

    // ④ صفوفٌ فعليةٌ في الجدولِ لا رأسٌ فارغ
    ck('الجدولُ يحمل صفوفًا', substr_count($body, "ticket_form.php?id=") > 0);
}

echo "\n── التحويلاتُ القديمة\n";
$jar = sys_get_temp_dir() . '/tkt_dept_redir.jar';
login($BASE, 'مبيعات', $jar);

// ⑤ الشاشةُ القديمةُ تُحوِّل ولا تعرض
list($code, $head, $body) = req($BASE . '/Tickets/dept_inbox.php', $jar);
$loc = preg_match('~^Location:\s*(.+)$~mi', $head, $m) ? trim($m[1]) : '';
ck('dept_inbox.php يُحوِّل 302 إلى التبويب',
   $code === 302 && strpos($loc, 'tickets_list.php?tab=dept') !== false,
   "HTTP {$code} → {$loc}");

// ⑥ المعامَلُ الميّتُ صار حيًّا
list($code, $head) = req($BASE . '/Tickets/tickets_list.php?open=1487', $jar);
$loc = preg_match('~^Location:\s*(.+)$~mi', $head, $m) ? trim($m[1]) : '';
ck('?open=1487 يفتح البلاغَ نفسَه',
   $code === 302 && strpos($loc, 'ticket_form.php?id=1487') !== false,
   "HTTP {$code} → {$loc}");

echo "\n═══ " . ($FAIL === 0 ? "نجح {$PASS}/{$PASS}" : "رسب {$FAIL} · نجح {$PASS}") . " ═══\n";
exit($FAIL === 0 ? 0 : 1);
