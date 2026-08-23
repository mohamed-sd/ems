<?php
/**
 * tests/injfrd66_contacts_http_proof.php — شاهدُ SAL-02 · SUP-02
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ولماذا HTTP لا قراءةَ ملف**: الشاشةُ خلفَ **أربعةِ أقفال** — قالبُ حوكمةٍ
 *   وصلاحيةُ دورٍ ولقطةُ مساحةٍ وحارسُ جلسة. وملفٌّ سليمٌ على القرصِ يُردُّ 302
 *   إن نقص قفلٌ منها، **ولا شيءَ في الشفرةِ يقول ذلك**.
 *
 * ◆ **إيجابيٌّ ①**: التبويبُ يُصيَّر 200 من ملفِّ العميلِ بجلسةِ مبيعاتٍ حقيقية.
 * ◆ **إيجابيٌّ ②**: وشريطُ الملفِّ يحمله — فهو **تبويبٌ مبلوغٌ لا شاشةٌ يتيمة**.
 * ◆ **سالبٌ ③**: و**صفرُ بندِ تنقّلٍ** له — وهو نصُّ المعيارِ حرفًا.
 * ◆ **إيجابيٌّ ④**: والإضافةُ تكتب صفًّا حقيقيًّا يُقرأ في الصفحةِ التالية.
 * ◆ **سالبٌ ⑤**: و**مفوَّضٌ بلا مدًى مرفوضٌ** — والقاعدةُ هي التي ترفض، فلو
 *   عُطِّلت الشاشةُ لبقي الرفض.
 * ◆ **سالبٌ ⑥**: وبلا رمزِ حمايةٍ لا تُكتب — نموذجٌ خامٌّ يُردُّ.
 * ◆ **إيجابيٌّ ⑦**: والحذفُ **ناعمٌ** — الصفُّ يبقى في القاعدةِ ويغيب عن العرض.
 *
 * التشغيل: php tests/injfrd66_contacts_http_proof.php
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
$PROBE = 'مسبارُ INJ-FRD-66 — يُزال فورَ القياس';
$wipe = static function () use ($conn, $PROBE): void {
    @mysqli_query($conn, "DELETE FROM party_contacts WHERE contact_name LIKE 'مسبارُ INJ-FRD-66%'");
};
$wipe();

echo "① الحسابُ والطرفُ المقيسان:\n";
$r = @mysqli_query($conn, "SELECT id, username FROM users
                            WHERE COALESCE(role_id, NULLIF(CAST(role AS UNSIGNED),0)) = 12
                              AND is_deleted = 0 AND status IN ('active','1') ORDER BY id LIMIT 1");
$acct = $r ? mysqli_fetch_assoc($r) : null;
$r = @mysqli_query($conn, "SELECT id, client_name FROM clients WHERE is_deleted = 0 ORDER BY id LIMIT 1");
$cl = $r ? mysqli_fetch_assoc($r) : null;
if (!$acct || !$cl) { echo "   ✘ لا حسابَ مبيعاتٍ أو لا عميل\n\n✘ SAL-02  ناجح 0 · راسب 1\n"; exit(1); }
$nOk++;
printf("   ✔ «%s» · عميل #%s\n", $acct['username'], $cl['id']);
$CID = (int) $cl['id'];
$URL = $BASE . '/Clients/client_contacts.php?client=' . $CID;

$jar = tempnam(sys_get_temp_dir(), 'pcp');
list(, , $form) = $req($BASE . '/login.php', $jar);
$tok = preg_match('~name="csrf_token"\s+value="([^"]+)"~', $form, $m) ? $m[1] : '';
list(, $h, ) = $req($BASE . '/login.php', $jar,
    array('username' => $acct['username'], 'password' => $PW, 'csrf_token' => $tok));
$loc = preg_match('~^Location:\s*(.+)$~mi', $h, $mm) ? trim($mm[1]) : '';
$check($loc !== '' && mb_strpos($loc, 'login') === false, 'الدخولُ فتح جلسةً');

echo "\n② إيجابيٌّ — التبويبُ يُصيَّر وشريطُ الملفِّ يحمله:\n";
list($c2, , $b2) = $req($URL, $jar);
$check($c2 === 200, "يُصيَّر برمزِ 200 (جاء {$c2}) — والأقفالُ الأربعةُ مفتوحةٌ فعلًا");
$check(mb_strpos($b2, 'ems-entity-tabs') !== false, 'وشريطُ ملفِّ العميلِ حاضر');
$check(preg_match('~ems-entity-tab[^"]*is-active[^>]*>\s*جهات الاتصال~u', $b2) === 1,
    'وتبويبُ «جهات الاتصال» موسومٌ نشِطًا فيه');
$check(mb_strpos($b2, 'pc-table') !== false, 'وجدولُ جهاتِ الاتصالِ مُصيَّر');
$check(mb_strpos($b2, 'pc-form') !== false, 'ونموذجُ الإضافةِ مُصيَّرٌ للمالك (الدور 12)');

echo "\n③ سالبٌ — صفرُ بندِ تنقّل (نصُّ المعيارِ حرفًا):\n";
$navN = $num("SELECT COUNT(*) FROM nav_items WHERE route LIKE '%client_contacts.php%'
                 OR route LIKE '%supplier_contacts.php%'");
$check($navN === 0, "صفرُ صفٍّ في `nav_items` للسطحَين (جاء {$navN})");
$check(mb_strpos($b2, 'تبويبٌ في الملفِّ لا شاشةٌ مستقلة') !== false, 'والشاشةُ تُعلن ذلك في متنِها');

echo "\n④ إيجابيٌّ — الإضافةُ تكتب صفًّا يُقرأ:\n";
$pTok = preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b2, $tm) ? $tm[1] : '';
$check($pTok !== '', 'رمزُ الحمايةِ مقروءٌ من النموذج');
$before = $num("SELECT COUNT(*) FROM party_contacts WHERE party_type='client' AND party_ref={$CID}");
list($c4, $h4, ) = $req($URL, $jar, array(
    'csrf_token' => $pTok, 'pc_action' => 'add',
    'contact_name' => $PROBE, 'job_title' => 'مسبار', 'phone' => '0000',
    'state' => 'نشط',
));
$after = $num("SELECT COUNT(*) FROM party_contacts WHERE party_type='client' AND party_ref={$CID}");
$check($after === $before + 1, "الصفوفُ {$before} ← {$after}");
list(, , $b4b) = $req($URL, $jar);
$check(mb_strpos($b4b, htmlspecialchars($PROBE, ENT_QUOTES, 'UTF-8')) !== false,
    'والصفُّ يُقرأ في الصفحةِ التالية');

echo "\n⑤ سالبٌ — مفوَّضٌ بلا مدًى مرفوض:\n";
/* ◆ **والقاعدةُ هي التي ترفض**: `chk_pc_authority`. فالشاشةُ تشرح ولا تخترع —
     ولو عُطِّل شرطُها لبقي الرفضُ حيث يجب أن يكون. */
$b4c = $b4b;
$pTok2 = preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b4c, $t2) ? $t2[1] : $pTok;
list($c5, , ) = $req($URL, $jar, array(
    'csrf_token' => $pTok2, 'pc_action' => 'add',
    'contact_name' => $PROBE . ' — مفوَّضٌ مفتوح', 'is_signatory' => '1',
    'authority_kind' => '—', 'state' => 'نشط',
));
$openN = $num("SELECT COUNT(*) FROM party_contacts
                WHERE contact_name LIKE 'مسبارُ INJ-FRD-66 — مفوَّضٌ مفتوح%'");
$check($openN === 0, "لم يُكتَب مفوَّضٌ بلا صفةٍ ولا مدًى (جاء {$openN})");
/* والقيدُ نفسُه يُجَسُّ مباشرةً — فلا يُنسَب المنعُ إلى الشاشةِ وحدَها */
$direct = @mysqli_query($conn, "INSERT INTO party_contacts
        (company_id, party_type, party_ref, contact_name, is_signatory)
        VALUES (0, 'client', 0, 'مسبارُ INJ-FRD-66 — مباشر', 1)");
$check($direct === false, 'والقاعدةُ ترفضه مباشرةً أيضًا — القيدُ هو الحكمُ لا الشاشة');
$check($num("SELECT COUNT(*) FROM party_contacts WHERE contact_name LIKE '%مباشر%'") === 0,
    'ولا أثرَ للمحاولةِ المباشرة');

echo "\n⑥ سالبٌ — بلا رمزِ حمايةٍ لا تُكتب:\n";
$b4 = $before;
list($c6, $h6, ) = $req($URL, $jar, array(
    'pc_action' => 'add', 'contact_name' => $PROBE . ' — بلا رمز', 'state' => 'نشط',
));
$noTok = $num("SELECT COUNT(*) FROM party_contacts WHERE contact_name LIKE '%بلا رمز%'");
$check($noTok === 0, "صفرُ صفٍّ كُتب بلا رمزِ حماية (جاء {$noTok})");
$loc6 = preg_match('~^Location:\s*(.+)$~mi', $h6, $m6) ? trim($m6[1]) : '';
/* ◆ **والرسالةُ لا تُقرأ من الرابط**: `ems_gov_flash_redirect` يحملها في
     الجلسةِ لا في `?msg=` — فتُقرأ من **الصفحةِ التالية** لا من الترويسة.
     (فحصٌ على الرابطِ يرسُب وهو صادقٌ في الحكمِ كاذبٌ في الموضع.) */
/* ◆ **والرافضُ حارسُ المنصّةِ لا حارسي**: `ems_csrf_path_enforced` يردُّ
     **403** قبلَ أن يصل الطلبُ إلى `ems_pc_handle` أصلًا. وهذا أقوى:
     الحمايةُ مركزيةٌ لا مكرَّرةٌ في كلِّ سطح. فيُقاس الردُّ كما هو —
     و**فحصٌ ينتظر تحويلًا يرسُب وهو صادقٌ في الحكمِ كاذبٌ في الموضع.** */
$check($c6 === 403 || ($c6 >= 300 && $c6 < 400 && $loc6 !== ''),
    "ورُدَّ بحارسٍ لا بكتابةٍ (رمزٌ {$c6}) — وحارسُ المنصّةِ يسبق حارسَ السطح");
$check($c6 === 403, 'والردُّ 403 من الطبقةِ المركزية — لا نسخةَ حمايةٍ في كلِّ شاشة');

echo "\n⑦ إيجابيٌّ — الحذفُ ناعمٌ يُبقي الأثر:\n";
$row = @mysqli_query($conn, "SELECT id FROM party_contacts
                              WHERE party_type='client' AND party_ref={$CID}
                                AND contact_name = '" . mysqli_real_escape_string($conn, $PROBE) . "' LIMIT 1");
$pid = $row ? (int) mysqli_fetch_row($row)[0] : 0;
$check($pid > 0, "صفُّ المسبارِ #{$pid}");
list(, , $b7) = $req($URL, $jar);
$pTok3 = preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b7, $t3) ? $t3[1] : $pTok;
$req($URL, $jar, array('csrf_token' => $pTok3, 'pc_action' => 'delete', 'pc_id' => $pid));
$stillThere = $num("SELECT COUNT(*) FROM party_contacts WHERE id={$pid}");
$softed     = $num("SELECT COUNT(*) FROM party_contacts WHERE id={$pid} AND is_deleted=1");
$check($stillThere === 1 && $softed === 1, "الصفُّ باقٍ في القاعدةِ وموسومٌ محذوفًا — أثرٌ لا محو (باقٍ={$stillThere} · موسوم={$softed})");
list(, , $b7b) = $req($URL, $jar);
$check(mb_strpos($b7b, htmlspecialchars($PROBE, ENT_QUOTES, 'UTF-8')) === false,
    'وغاب عن العرض');

/* الكنسُ بالعائلةِ لا بالمعرّف — مسبارُ جولةٍ سابقةٍ يُعمي هذه */
$wipe();
$check($num("SELECT COUNT(*) FROM party_contacts WHERE contact_name LIKE 'مسبارُ INJ-FRD-66%'") === 0,
    'وأُزيلت مسابيرُ العائلةِ كلُّها');
@unlink($jar);

echo "\n⑧ إيجابيٌّ — وسطحُ الموردِ بجلسةِ إدارةِ الموردين (الدور 2):\n";
/* ◆ **والسطحانِ يُقاسانِ كلاهما**: عُدَّةٌ واحدةٌ لا تعني سطحًا واحدًا — والأقفالُ
     الأربعةُ تُفتح لكلِّ مسارٍ على حدة. فقياسُ أحدِهما وإعلانُ الاثنين
     **إعلانُ ما لم يُقَس**. */
$r = @mysqli_query($conn, "SELECT username FROM users
                            WHERE COALESCE(role_id, NULLIF(CAST(role AS UNSIGNED),0)) = 2
                              AND is_deleted = 0 AND status IN ('active','1') ORDER BY id LIMIT 1");
$sAcct = $r ? mysqli_fetch_assoc($r) : null;
$r = @mysqli_query($conn, "SELECT id, name FROM suppliers WHERE is_deleted = 0 ORDER BY id LIMIT 1");
$sp = $r ? mysqli_fetch_assoc($r) : null;
if (!$sAcct || !$sp) { $nBad++; echo "   ✘ لا حسابَ للدور 2 أو لا مورد\n"; }
else {
    $jar2 = tempnam(sys_get_temp_dir(), 'pcs');
    list(, , $f2) = $req($BASE . '/login.php', $jar2);
    $t2b = preg_match('~name="csrf_token"\s+value="([^"]+)"~', $f2, $mx) ? $mx[1] : '';
    $req($BASE . '/login.php', $jar2,
        array('username' => $sAcct['username'], 'password' => $PW, 'csrf_token' => $t2b));
    $SURL = $BASE . '/Suppliers/supplier_contacts.php?supplier_id=' . (int) $sp['id'];
    list($c8, , $b8) = $req($SURL, $jar2);
    $check($c8 === 200, "تبويبُ الموردِ يُصيَّر 200 (جاء {$c8}) — مورد #" . $sp['id']);
    $check(mb_strpos($b8, 'ems-entity-tabs') !== false, 'وشريطُ ملفِّ الموردِ حاضر');
    $check(mb_strpos($b8, 'pc-form') !== false, 'ونموذجُ الإضافةِ مُصيَّرٌ للمالك (الدور 2)');
    @unlink($jar2);
}

printf("\n%s  ناجح %d · راسب %d\n", $nBad === 0 ? '✔ SAL-02 · SUP-02' : '✘ SAL-02 · SUP-02', $nOk, $nBad);
exit($nBad === 0 ? 0 : 1);
