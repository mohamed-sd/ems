<?php
/**
 * tests/equipments_suppliers_mgr_http_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 * برهانُ HTTP: «سجلُّ المعدات» (Equipments/equipments.php) **مخفيٌّ عن سايدبارِ
 * إدارةِ الموردين** (الدور 2) — **والقراءةُ باقيةٌ مقصدًا لا بابًا**.
 *
 * ◆ **قرارانِ متتاليان — وهذا الملفُّ يحرس الثاني**:
 *   2026-08-20 · فُتح الرابطُ لمديرِ الموردين (هجرة `2027_08_13_…`).
 *   2026-08-21 · **أمرَ المالكُ بإخفائه** (هجرة `2027_08_18_…`) — إخفاءُ ظهورٍ
 *   لا سحبَ صلاحية. فبقيت `role_permissions.can_view=1` و`gov_profile_items`،
 *   وأُطفئ صفُّ التبعيةِ وصار صنفُ المساحةِ `FORBIDDEN`.
 *
 * ◆ **ولا يُغلق إخفاءٌ بإثباتِ ما يُرى بل بإثباتِ ما لا يُرى** — ولذلك:
 *   ① الأقفالُ الأربعةُ تُقاس كلُّها: **اثنان مغلقان واثنان مفتوحان قصدًا**،
 *      فمَن قاس المغلقَين وسكتَ عن المفتوحَين أعلن سحبًا لم يقع.
 *   ② القياسُ **على المُصيَّرِ الحيِّ بمرساةِ `href`** لا بالنصِّ العربيّ.
 *   ③ **والتعليقاتُ تُنزَع قبلَ العدّ**: في `insidebar.php` مِلكيةٌ موروثةٌ من
 *      روابطَ صلبةٍ لهذا المسارِ داخلَ `<!-- … -->` (للدورَين 2 و3) — يطبعها
 *      PHP ولا يعرضها المتصفح. فعدٌّ على الخامِ يرى ثلاثةً حيثُ يرى المستخدمُ
 *      واحدًا، **ويستحيل عندئذٍ أن يخضرَّ الإخفاءُ أبدًا**.
 *   ④ **وضابطٌ موجبٌ في كلِّ جولة**: المساحةُ المالكةُ («ادارة الاسطول» · الدور
 *      3 · الحساب «يسن») يجب أن **يظهر** فيها الرابطُ. فبوابةٌ تعدُّ صفرًا في
 *      سايدبارٍ معطوبٍ خضراءُ كاذبةٌ — والضابطُ يكشف ذلك في الجولةِ نفسِها.
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
$OWNER_USER  = 'يسن';            /* الدور 3 — المساحةُ المالكةُ «ادارة الاسطول» */
$KEEP_ROUTE  = 'Suppliers/suppliers.php';  /* رابطٌ من روابطِ الدورِ 2 يجب أن يبقى */

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

/**
 * سايدبارُ الصفحةِ **كما يراه المتصفحُ**: قصٌّ على حدَّي القائمة ثم **نزعُ
 * التعليقات** — فالمِلكيةُ الموروثةُ الميتةُ داخلَ `<!-- … -->` ليست رابطًا.
 * @return string|null null إن لم يُعثر على حدَّي القائمة (فلا يُقاس على وهم).
 */
function sidebar_html($body) {
    $s = strpos($body, '<ul id="sidebarNavList">');
    $e = strpos($body, '<!-- زر تسجيل الخروج -->');
    if ($s === false || $e === false || $e <= $s) { return null; }
    return preg_replace('~<!--.*?-->~s', '', substr($body, $s, $e - $s));
}
/** عددُ مرساتِ `href` لمسارٍ بعينِه — بحدِّ اسمِ الملفِّ فلا يبتلع `equipment_documents.php` */
function href_hits($html, $route) {
    return preg_match_all('~href="[^"]*' . preg_quote($route, '~') . '(?:[?#"])~', (string) $html);
}
function link_count($html) { return preg_match_all('~<a\s[^>]*href="~', (string) $html); }

fwrite(STDOUT, "═══ «سجلُّ المعدات» مخفيٌّ عن «{$SPACE}» — برهانٌ حيٌّ ═══\n");

/* ══ ① الأقفالُ الأربعةُ — اثنان أُغلقا واثنان تُركا قصدًا ══════════════ */
head('① الأقفالُ الأربعةُ — قياسٌ في القاعدة');

$navAct = (int) one("SELECT COUNT(*) FROM nav_items
                      WHERE role_id={$ROLE} AND route='{$ROUTE}' AND active=1");
check($navAct === 0, "③ nav_items: لا صفَّ تبعيةٍ نشِطًا للدور {$ROLE} (المقيس: {$navAct})");

$navRow = (int) one("SELECT COUNT(*) FROM nav_items WHERE role_id={$ROLE} AND route='{$ROUTE}'");
check($navRow === 1, "③' الصفُّ أُطفئ ولم يُحذف — العكسُ سطرٌ واحد (المقيس: {$navRow})");

$forb = (int) one("SELECT COUNT(*) FROM gov_space_appearances
                    WHERE space_ar='{$SPACE}' AND LOWER(route)=LOWER('{$ROUTE}') AND cls='FORBIDDEN'");
check($forb === 1, "④ عزلُ الإدارات: صنفُ المسارِ في «{$SPACE}» = FORBIDDEN (المقيس: {$forb})");

$seen = (int) one("SELECT COUNT(*) FROM gov_space_appearances
                    WHERE space_ar='{$SPACE}' AND LOWER(route)=LOWER('{$ROUTE}')");
check($seen === 1, "④' اللقطةُ كاملةٌ: للمسارِ صفٌّ مُصنَّفٌ — لا فراغَ صامت (المقيس: {$seen})");

$owned = (string) one("SELECT cls FROM gov_space_appearances
                        WHERE space_ar='ادارة الاسطول' AND LOWER(route)=LOWER('{$ROUTE}')");
check($owned === 'OWNED', "④\" المساحةُ المالكةُ لم تُمَسّ: «ادارة الاسطول» = OWNED (المقيس: {$owned})");

/* والقفلانِ المتروكانِ قصدًا — الإخفاءُ ليس سحبًا */
$rp = (int) one("SELECT COALESCE(can_view,0) FROM role_permissions WHERE role_id={$ROLE} AND module_id={$MODID}");
check($rp === 1, "② role_permissions: can_view=1 **باقيةٌ** — المقصدُ لم يُغلق (المقيس: {$rp})");

$rpW = (int) one("SELECT COALESCE(can_add,0)+COALESCE(can_edit,0)+COALESCE(can_delete,0)
                    FROM role_permissions WHERE role_id={$ROLE} AND module_id={$MODID}");
check($rpW === 0, "②' قراءةٌ لا كتابة: add+edit+delete = 0 (المقيس: {$rpW})");

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
    check($has === 1, "① gov_profile_items: القالبُ {$pcode} (#{$pid}) **باقٍ** allow=1 — لم يُسحب");
}

/* ══ ② السايدبارُ الحيُّ: الرابطُ اختفى ولم يختفِ سواه ═══════════════════ */
head('② سايدبارُ «' . $USER . '» — إثباتُ ما لا يُرى');

$jar = sys_get_temp_dir() . '/ems_eq_sup_' . getmypid() . '.cookie';
list($c, $h, $b) = login($USER, $jar);
check($c === 302 || $c === 200, "دخولُ «{$USER}» (الدور {$ROLE}) — رمز {$c}");

list($cB, $hB, $bB) = req($BASE . '/main/role_board.php', $jar);
$side = sidebar_html($bB);
check($side !== null, "لوحةُ الدورِ فُتحت وحدّا القائمةِ موجودان — رمز {$cB} (وإلا فالقياسُ على وهم)");
$nLinks = link_count($side);
check($nLinks >= 20, "السايدبارُ مُصيَّرٌ فعلًا: {$nLinks} رابطًا حيًّا (سايدبارٌ فارغٌ يُخضِّر الصفرَ كذبًا)");

$hit = href_hits($side, $ROUTE);
check($hit === 0, "**لا مرساةَ href للمسارِ في سايدبارِ «{$SPACE}»** (المقيس: {$hit})");

$keep = href_hits($side, $KEEP_ROUTE);
check($keep >= 1, "ولم يُزَل سواه: «{$KEEP_ROUTE}» ما يزال في القائمة (المقيس: {$keep})");

/* ══ ③ الضابطُ الموجب: المساحةُ المالكةُ ما تزال تراه ════════════════════ */
head('③ الضابطُ الموجب — سايدبارُ «' . $OWNER_USER . '» (المساحةُ المالكة)');
$jar2 = sys_get_temp_dir() . '/ems_eq_own_' . getmypid() . '.cookie';
login($OWNER_USER, $jar2);
list($cO, $hO, $bO) = req($BASE . '/main/role_board.php', $jar2);
$sideO = sidebar_html($bO);
check($sideO !== null, "لوحةُ «{$OWNER_USER}» فُتحت وحدّا القائمةِ موجودان — رمز {$cO}");
$hitO = href_hits($sideO, $ROUTE);
check($hitO >= 1, "الرابطُ **يظهر** في المساحةِ المالكة (المقيس: {$hitO}) — فالمقياسُ يرى الروابطَ حين تكون");
@unlink($jar2);

/* ══ ④ المقصدُ باقٍ: إخفاءُ رابطٍ لا إغلاقُ باب ═══════════════════════════ */
head('④ المقصدُ باقٍ — الشاشةُ تُفتح بالعنوانِ المباشر');
list($c2, $h2, $b2) = req($BASE . '/' . $ROUTE, $jar);
$redirected = (bool) preg_match('~^Location:~mi', $h2);
check($c2 === 200 && !$redirected, "GET /{$ROUTE} ⇒ 200 بلا تحويل (المقيس: {$c2}" . ($redirected ? ' + Location' : '') . ')');
check(mb_strpos($b2, 'لا توجد صلاحية عرض المعدات') === false, 'لا رسالةَ منعِ صلاحيةٍ في الجسم');

/* ══ ⑤ الحدُّ محفوظ: قراءةٌ لا كتابة ═══════════════════════════════════ */
head('⑤ الحدُّ محفوظ: قراءةٌ لا كتابة');
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
