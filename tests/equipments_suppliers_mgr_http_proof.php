<?php
/**
 * tests/equipments_suppliers_mgr_http_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 * برهانُ HTTP: «سجلُّ المعدات» (Equipments/equipments.php) يُفتح لمديرِ الموردين
 * (الدور 2) ويظهر رابطُه في سايدباره — قراءةً لا كتابة.
 *
 * ◆ **القفلُ أربعةٌ لا واحد** — ولذلك يُقاس كلُّ واحدٍ منها على حدة:
 *   ① `gov_profile_items` — قالبُ GOV-AUTH-01 النافذُ يحكم وحدَه: **الشاشةُ
 *      خارجَ القالبِ تُمنع ولو مُنحت في `role_permissions`** (t_view = -1).
 *   ② `role_permissions` — حارسُ الخادمِ في الشاشةِ نفسِها (`can_view`).
 *   ③ `nav_items` — التبعيةُ: بلا صفٍّ لا يظهر الرابطُ ولو صحّت الصلاحية.
 *   ④ `gov_space_appearances` — عزلُ الإدارات: صفٌّ بـ`cls='FORBIDDEN'`
 *      في مساحةِ الدورِ يُزيل الرابطَ من السايدبار بعدَ اجتيازِ الثلاثة.
 *   فمَن قاس واحدًا وأعلن النجاحَ أعلن ما لم يقع.
 *
 * ◆ **والقياسُ بهويةٍ حيّةٍ لا بدورٍ مجرَّد**: قالبُ الحوكمةِ يُقرأ بـ`user_id`،
 *   فلا يُقاس إلا بحسابٍ حقيقيٍّ يفتح الشاشةَ من المتصفح.
 *
 * التشغيل: php tests/equipments_suppliers_mgr_http_proof.php   (يتطلب Apache حيًّا)
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$ROOT  = dirname(__DIR__);
$BASE  = 'http://localhost/ems';
$ROUTE = 'Equipments/equipments.php';
$MODID = 256;   /* modules.code = Equipments/equipments.php */
$ROLE  = 2;     /* مدير الموردين — EMS_ROLE_SUPPLIERS_MGR */
$SPACE = 'ادارة الموردين';
$USER  = 'مصعب';   /* الحسابُ الحيُّ الوحيدُ بالدور 2 في شركةِ العرض */

$PASS_N = 0; $FAIL_N = 0;
function ok($m)  { global $PASS_N; $PASS_N++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL_N; $FAIL_N++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

require_once $ROOT . '/includes/env.php';
$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { exit("تعذّر الاتصال بالقاعدة\n"); }
$db->set_charset('utf8mb4');
function one($sql) { global $db; $r = $db->query($sql); $x = $r ? $r->fetch_row() : null; return $x ? $x[0] : null; }

function req($url, $jar, $post = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 60,
    ));
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw  = curl_exec($ch);
    $hs   = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array($code, substr($raw, 0, $hs), substr($raw, $hs));
}
function login($user, $jar) {
    global $BASE;
    @unlink($jar);
    list($c, $h, $b) = req($BASE . '/login.php', $jar);
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
    return req($BASE . '/login.php', $jar, array(
        'username' => $user, 'password' => '12345678',
        'csrf_token' => isset($m[1]) ? $m[1] : ''));
}

fwrite(STDOUT, "═══ سجلُّ المعدات لمديرِ الموردين — برهانٌ حيٌّ ═══\n");

/* ══ ① الأقفالُ الأربعةُ في القاعدة ══════════════════════════════════════ */
head('① الأقفالُ الأربعةُ — قياسٌ في القاعدة');

$rp = (int) one("SELECT COALESCE(can_view,0) FROM role_permissions WHERE role_id={$ROLE} AND module_id={$MODID}");
check($rp === 1, "role_permissions: can_view=1 للدور {$ROLE} على الموديل {$MODID} (المقيس: {$rp})");

$rpW = (int) one("SELECT COALESCE(can_add,0)+COALESCE(can_edit,0)+COALESCE(can_delete,0)
                    FROM role_permissions WHERE role_id={$ROLE} AND module_id={$MODID}");
check($rpW === 0, "قراءةٌ لا كتابة: add+edit+delete = 0 (المقيس: {$rpW})");

/* القالبُ النافذُ لكلِّ مستخدمٍ بالدور 2 — الغيابُ منعٌ لا سكوت */
$profs = array();
$r = $db->query("SELECT DISTINCT p.profile_id, p.profile_code
                   FROM gov_authority_grants g
                   JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state='active'
                   JOIN users u ON u.id = g.user_id
                  WHERE u.role = {$ROLE} AND g.revoked_at IS NULL
                    AND (g.valid_to IS NULL OR g.valid_to > NOW())");
while ($x = $r->fetch_assoc()) { $profs[(int) $x['profile_id']] = $x['profile_code']; }
check(!empty($profs), 'يوجد قالبُ حوكمةٍ نافذٌ واحدٌ على الأقل لمستخدمي الدور 2 (وإلا فالقياسُ لا يعني شيئًا)');
foreach ($profs as $pid => $pcode) {
    $has = (int) one("SELECT COUNT(*) FROM gov_profile_items
                       WHERE profile_id={$pid} AND item_kind='screen'
                         AND item_ref='{$ROUTE}' AND allow=1");
    check($has === 1, "gov_profile_items: القالبُ {$pcode} (#{$pid}) يشمل {$ROUTE} — allow=1");
}

$navAct = (int) one("SELECT COUNT(*) FROM nav_items
                      WHERE role_id={$ROLE} AND route='{$ROUTE}' AND active=1 AND module_id={$MODID}");
check($navAct === 1, "nav_items: صفُّ تبعيةٍ واحدٌ نشِطٌ للدور {$ROLE} (المقيس: {$navAct})");

$forb = (int) one("SELECT COUNT(*) FROM gov_space_appearances
                    WHERE space_ar='{$SPACE}' AND LOWER(route)=LOWER('{$ROUTE}') AND cls='FORBIDDEN'");
check($forb === 0, "عزلُ الإدارات: لا صفَّ FORBIDDEN في مساحةِ «{$SPACE}» (المقيس: {$forb})");

$seen = (int) one("SELECT COUNT(*) FROM gov_space_appearances
                    WHERE space_ar='{$SPACE}' AND LOWER(route)=LOWER('{$ROUTE}')");
check($seen === 1, "اللقطةُ كاملةٌ: للمسارِ صفٌّ مُصنَّفٌ في مساحةِ الدور — لا فراغَ صامت (المقيس: {$seen})");

/* ══ ② الفتحُ الحيُّ بالحسابِ الحقيقي ═════════════════════════════════════ */
head('② الشاشةُ تُفتح فعلًا — لا تحويلَ ولا 403');

$jar = sys_get_temp_dir() . '/ems_eq_sup_' . getmypid() . '.cookie';
list($c, $h, $b) = login($USER, $jar);
check($c === 302 || $c === 200, "دخولُ «{$USER}» (الدور {$ROLE}) — رمز {$c}");

list($c2, $h2, $b2) = req($BASE . '/' . $ROUTE, $jar);
$redirected = (bool) preg_match('~^Location:~mi', $h2);
check($c2 === 200 && !$redirected, "GET /{$ROUTE} ⇒ 200 بلا تحويل (المقيس: {$c2}" . ($redirected ? ' + Location' : '') . ')');
check(mb_strpos($b2, 'لا توجد صلاحية عرض المعدات') === false, 'لا رسالةَ منعِ صلاحيةٍ في الجسم');
check(mb_strpos($b2, 'إدارة المعدات') !== false || mb_strpos($b2, 'سجل المعدات') !== false,
      'جسمُ الصفحةِ يحمل عنوانَ الشاشة');

/* ══ ③ الرابطُ في السايدبار — لا شاشةً بلا بابٍ يُفتح منه ════════════════ */
head('③ الرابطُ مُصيَّرٌ في السايدبار');
$navHit = preg_match('~href="[^"]*' . preg_quote($ROUTE, '~') . '"~', $b2);
check($navHit === 1, 'مرساةُ href للمسارِ موجودةٌ في المُصيَّر (لا قياسَ بالنصِّ العربيِّ وحدَه)');

/* ══ ④ الكتابةُ تبقى مغلقة — المنحُ قراءةٌ لا أكثر ═══════════════════════ */
head('④ الحدُّ محفوظ: قراءةٌ لا كتابة');
$before = (int) one("SELECT COUNT(*) FROM equipments");
list($c4, $h4, $b4) = req($BASE . '/' . $ROUTE, $jar,
    array('action' => 'add', 'name' => 'ems_neg_probe_' . getmypid(), 'code' => 'NEGPROBE'));
$after = (int) one("SELECT COUNT(*) FROM equipments");
check($after === $before, "لا صفَّ مُدرَجًا من المسبارِ السلبيّ (قبل {$before} · بعد {$after})");
$rows = (int) one("SELECT COUNT(*) FROM equipments WHERE name LIKE 'ems\\_neg\\_probe%'");
check($rows === 0, "لا أثرَ باسمِ المسبارِ في الجدول (المقيس: {$rows})");

@unlink($jar);

fwrite(STDOUT, "\n═══ النتيجة: {$PASS_N} ✔ · {$FAIL_N} ✘ ═══\n");
exit($FAIL_N > 0 ? 1 : 0);
