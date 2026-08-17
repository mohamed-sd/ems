<?php
/**
 * tests/uxw_dup_form_probe.php — قياسُ أثرِ **التكرارِ البنيويِّ** على الحفظ (UXW-01)
 * ═══════════════════════════════════════════════════════════════════════════
 * أربعةُ عيوبِ بنيةٍ رصدها ترحيلُ UXW-01 (2026-08-17) وتُركت عمدًا:
 *   ① Clients/clients.php   — حقلُ «كود العميل» مكرَّرٌ بنفسِ name/id
 *   ② Opportunities/…       — لوحةُ opp-req-panel مكرَّرةٌ بمعرِّفاتٍ مكرَّرة (ids فقط)
 *   ③ Settings/modules.php  — منتقي الأيقوناتِ مكرَّرٌ بنفسِ name="icon"
 *   ④ Projects/projects.php — background: fff بلا # (CSS ساقطٌ أصلًا — لا POST)
 *
 * **قانونُ القياس**: المتصفّح يُرسِل الحقولَ بترتيبِ DOM، وPHP تُبقي **الأخيرَ**
 * من كلِّ اسمٍ مكرَّرٍ في $_POST — بينما جافاسكربت (jQuery/getElementById)
 * تكتب في **الأوَّلِ** فقط. فما يحرّره المستخدمُ يُداس بقيمةِ التوأمِ الراكد.
 *
 * الوضعان:
 *   --before  يقيس أيَّ القيمتين تفوز فعلًا (يُثبت العطب قبل الإصلاح)
 *   --after   يقيس أن الحقلَ صار مفردًا وأن الحفظَ ذهابًا وإيابًا سليم
 *
 * بياناتُ الفحص بعائلةِ الوسم UXWDUP وتُكنَس بالعائلةِ لا بالجولة
 * (درسُ [[test-mark-family-sweep]]) — ويُفحَص مُرجَعُ كلِّ حذف.
 * ═══════════════════════════════════════════════════════════════════════════
 */

$MODE = in_array('--after', $argv, true) ? 'after' : 'before';

$BASE = 'http://localhost/ems';
$TMP  = sys_get_temp_dir();
$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }
function info($m) { fwrite(STDOUT, "     · {$m}\n"); }

/**
 * طلبٌ خام. $post مصفوفةٌ عاديةٌ **أو** سلسلةُ استعلامٍ جاهزة — لأن
 * http_build_query لا تستطيع تمثيلَ اسمٍ مكرَّرٍ (client_code=A&client_code=B)
 * وهو جوهرُ هذا القياس.
 */
function up_req($url, $jar, $post = null) {
    $GLOBALS['__ems_last_url'] = $url;
    $GLOBALS['__ems_last_jar'] = $jar;
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 40,
    ));
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($post) ? http_build_query($post) : $post);
    }
    $raw  = curl_exec($ch);
    $hs   = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array($code, substr((string)$raw, 0, $hs), substr((string)$raw, $hs));
}
function up_login($user, $jar) {
    global $BASE;
    @unlink($jar);
    list($c, $h, $b) = up_req($BASE . '/login.php', $jar);
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
    return up_req($BASE . '/login.php', $jar, array('username' => $user, 'password' => '12345678',
        'csrf_token' => isset($m[1]) ? $m[1] : ''));
}
function up_msg($headers) {
    require_once __DIR__ . '/_http_flash.php';
    $dir = preg_replace('~/[^/]*(\?.*)?$~', '', (string) $GLOBALS['__ems_last_url']);
    return ems_flash_or_msg($headers, $dir, function ($u) {
        $r = up_req($u, $GLOBALS['__ems_last_jar'], null);
        return (string) $r[2];
    });
}
/** رمزُ CSRF من صفحةِ الشاشةِ نفسِها (واحدٌ للجلسة). */
function up_csrf($url, $jar) {
    list($c, $h, $b) = up_req($url, $jar);
    return preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m) ? $m[1] : '';
}

require_once dirname(__DIR__) . '/includes/env.php';
$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_error) { fwrite(STDERR, "DB: {$db->connect_error}\n"); exit(1); }
$db->set_charset('utf8mb4');

/** كنسُ عائلةِ الوسمِ كلِّها — قبل الجولةِ وبعدَها، ويُفحَص مُرجَعُ كلِّ حذف. */
function up_sweep($db) {
    foreach (array(
        "DELETE FROM opportunities WHERE opp_code LIKE 'UXWDUP%'",
        "DELETE FROM clients WHERE client_code LIKE 'UXWDUP%'",
        "DELETE FROM modules WHERE code LIKE 'uxwdup%'",
    ) as $sql) {
        if (!$db->query($sql)) { bad("كنس: {$db->error} — {$sql}"); }
        elseif ($db->affected_rows > 0) { info("كُنس {$db->affected_rows} صف: " . substr($sql, 12, 30)); }
    }
}

$jar  = $TMP . DIRECTORY_SEPARATOR . 'uxwdup_' . getmypid() . '.jar';        // «محمد» — الصفحات والمشاريع
$jarS = $TMP . DIRECTORY_SEPARATOR . 'uxwdup_sales_' . getmypid() . '.jar';  // «مشرف المبيعات» (دور 12) — العملاء والفرص
$T   = 'UXWDUP' . substr((string) getmypid(), -4);

fwrite(STDOUT, "═══ مسبار التكرار البنيوي UXW — الوضع: {$MODE} ═══\n");
up_sweep($db);

/* بوابةُ المبيعات (TS-04): كتابةُ العملاءِ والفرصِ حكرٌ على الدور 12 —
   «محمد» (دور 1) يُرَدُّ عنها إلى الداشبورد، فلكلِّ نطاقٍ جرَّتُه. */
list($lc, $lh, $lb) = up_login('محمد', $jar);
check($lc === 302 || $lc === 200, "تسجيل دخول «محمد» (HTTP {$lc})");
list($lc2, $lh2, $lb2) = up_login('مشرف المبيعات', $jarS);
check($lc2 === 302 || $lc2 === 200, "تسجيل دخول «مشرف المبيعات» (HTTP {$lc2})");

/* ═══════════ ① Clients — name="client_code" مكرَّر ═══════════ */
head('① Clients/clients.php — كود العميل');
$CL = $BASE . '/Clients/clients.php';
list($c, $h, $b) = up_req($CL, $jarS);
$dupN = preg_match_all('~name="client_code"~', $b, $x);
$dupI = preg_match_all('~id="client_code"~', $b, $x);
info("name=\"client_code\" يظهر {$dupN} مرة · id=\"client_code\" يظهر {$dupI} مرة");
if ($MODE === 'before') {
    check($dupN === 2 && $dupI === 2, 'البنية المكرَّرة قائمة (2×name + 2×id) — كما رصدها الترحيل');
} else {
    check($dupN === 1 && $dupI === 1, 'الحقل صار مفردًا (1×name + 1×id)');
}
$tok = up_csrf($CL, $jarS);
check($tok !== '', 'رمز CSRF مقروء من الشاشة');

/* الإضافة: الحقلُ الأولُ (الذي يحرّره المستخدم) ثم التوأمُ الثاني بقيمةِ الكودِ
   المولَّدِ الراكدة — بترتيبِ DOM نفسِه. */
$edit = "{$T}-EDIT"; $gen = "{$T}-GEN";
$q = 'csrf_token=' . urlencode($tok) . '&client_id='
   . '&client_code=' . urlencode($edit)
   . ($MODE === 'before' ? '&client_code=' . urlencode($gen) : '')
   . '&client_name=' . urlencode("عميل قياس {$T}")
   . '&entity_type=&sector_category=&phone=&email=&whatsapp='
   . '&status=' . urlencode('نشط');
list($c, $h, $b) = up_req($CL, $jarS, $q);
$msg = up_msg($h);
check(mb_strpos($msg, '✅') !== false, "حفظ الإضافة نفذ (الرسالة: " . mb_substr(trim($msg), 0, 60) . ")");
$row = $db->query("SELECT id, client_code FROM clients WHERE client_name LIKE '%{$T}%' AND is_deleted = 0")->fetch_assoc();
check($row !== null, 'صف العميل كُتب في القاعدة');
$cid = $row ? (int) $row['id'] : 0;
if ($MODE === 'before') {
    check($row && $row['client_code'] === $gen,
        "**قيمة التوأم الأخير فازت**: حُفظ «{$row['client_code']}» لا ما حرّره المستخدم «{$edit}»");
} else {
    check($row && $row['client_code'] === $edit, "حُفظ ما أدخله المستخدم حرفًا: «{$edit}»");
}

/* التعديل: جافاسكربت تملأ الحقلَ **الأولَ** بكودِ العميلِ الفعليّ، والتوأمُ الثاني
   يظلُّ على كودٍ مولَّدٍ جديد — فحفظُ أيِّ تعديلٍ يستبدل كودَ العميلِ كلَّه. */
if ($cid > 0) {
    $kept = ($MODE === 'before') ? $gen : $edit;
    $regen = "{$T}-REGEN";
    $q = 'csrf_token=' . urlencode($tok) . '&client_id=' . $cid
       . '&client_code=' . urlencode($kept)
       . ($MODE === 'before' ? '&client_code=' . urlencode($regen) : '')
       . '&client_name=' . urlencode("عميل قياس {$T} معدل")
       . '&entity_type=' . urlencode('خاص') . '&sector_category=&phone=0912345678&email=&whatsapp='
       . '&status=' . urlencode('نشط');
    list($c, $h, $b) = up_req($CL, $jarS, $q);
    $msg = up_msg($h);
    check(mb_strpos($msg, '✅') !== false, 'حفظ التعديل نفذ');
    $row2 = $db->query("SELECT client_code, client_name, entity_type FROM clients WHERE id = {$cid}")->fetch_assoc();
    if ($MODE === 'before') {
        check($row2 && $row2['client_code'] === $regen,
            "**التعديل أفسد الكود**: صار «{$row2['client_code']}» — كود العميل يُستبدل بمولَّد جديد مع كل حفظ تعديل");
    } else {
        check($row2 && $row2['client_code'] === $kept, "كود العميل صمد بعد التعديل: «{$kept}»");
        check($row2 && $row2['entity_type'] === 'خاص', 'بقية حقول التعديل وصلت (entity_type=خاص)');
    }
}

/* ═══════════ ② Opportunities — لوحة opp-req-panel مكرَّرة (ids فقط) ═══════════ */
head('② Opportunities/opportunities.php — لوحة المتطلبات');
$OP = $BASE . '/Opportunities/opportunities.php';
list($c, $h, $b) = up_req($OP, $jarS);
$panels  = preg_match_all('~class="opp-req-panel"~', $b, $x);
$sumIds  = preg_match_all('~id="reqSumEquip"~', $b, $x);
$rowsIds = preg_match_all('~id="reqEquipRows"~', $b, $x);
$opsName = preg_match_all('~name="req_operators"~', $b, $x);
$supName = preg_match_all('~name="req_suppliers"~', $b, $x);
info("opp-req-panel×{$panels} · id reqSumEquip×{$sumIds} · id reqEquipRows×{$rowsIds} · name req_operators×{$opsName} · name req_suppliers×{$supName}");
if ($MODE === 'before') {
    check($panels === 2 && $sumIds === 2 && $rowsIds === 2, 'اللوحة مكرَّرة بمعرِّفات مكرَّرة — كما رصدها الترحيل');
    check($opsName === 1 && $supName === 1,
        '**لا اسم POST مكرَّر هنا** — الأثر على DOM فقط (العدّادات والصفوف تُكتب في النسخة الأولى والثانية جثة ظاهرة)، والحفظ نفسه غير متأثر');
} else {
    check($panels === 1 && $sumIds === 1 && $rowsIds === 1, 'اللوحة صارت مفردة والمعرِّفات فريدة');
    check($opsName === 1 && $supName === 1, 'حقلا المتطلبات مفردان');
}
$tok = up_csrf($OP, $jarS);
$q = 'csrf_token=' . urlencode($tok) . '&opp_id='
   . '&opp_code=' . urlencode("{$T}-OPP")
   . '&title=' . urlencode("فرصة قياس {$T}")
   . '&client_id=0&source=&sector_category=&state_region=&revenue_model=&expected_revenue=1000'
   . '&currency=USD&probability=&stage=' . urlencode('جديدة')
   . '&attractiveness=&strategy_fit=&study_decision=&funding_needed='
   . '&req_operators=3&req_suppliers=2'
   . '&expected_close_date=&win_reason=&lost_reason=&review_notes=&notes=';
list($c, $h, $b) = up_req($OP, $jarS, $q);
$msg = up_msg($h);
check(mb_strpos($msg, '✅') !== false, "حفظ الفرصة نفذ (الرسالة: " . mb_substr(trim($msg), 0, 60) . ")");
$orow = $db->query("SELECT requirements_json, capacity_summary FROM opportunities WHERE opp_code = '{$T}-OPP' AND is_deleted = 0")->fetch_assoc();
check($orow !== null, 'صف الفرصة كُتب');
$rj = $orow ? json_decode((string) $orow['requirements_json'], true) : null;
check(is_array($rj) && (int)($rj['operators'] ?? -1) === 3 && (int)($rj['suppliers'] ?? -1) === 2,
    'المتطلبات المهيكلة وصلت سليمة (operators=3 · suppliers=2) — الحفظ غير متأثر بالتكرار');

/* ═══════════ ③ Settings/modules.php — name="icon" مكرَّر ═══════════ */
head('③ Settings/modules.php — منتقي الأيقونات');
$MD = $BASE . '/Settings/modules.php';
list($c, $h, $b) = up_req($MD, $jar);
$iconN = preg_match_all('~name="icon"~', $b, $x);
$gridI = preg_match_all('~id="iconGrid"~', $b, $x);
info("name=\"icon\" يظهر {$iconN} مرة · id=\"iconGrid\" يظهر {$gridI} مرة");
if ($MODE === 'before') {
    check($iconN === 2 && $gridI === 2, 'منتقي الأيقونات مكرَّر (2×name + 2×iconGrid) — كما رصده الترحيل');
} else {
    check($iconN === 1 && $gridI === 1, 'منتقي الأيقونات صار مفردًا');
}
$tok = up_csrf($MD, $jar);
/* المستخدم يختار أيقونةً — جافاسكربت تكتبها في الحقلِ **الأول**، والتوأمُ الثاني
   يظلُّ على الافتراضية. أيُّهما يصل القاعدة؟ */
$chosen = 'fas fa-flask'; $default = 'fas fa-cube';
$q = 'csrf_token=' . urlencode($tok) . '&edit_id='
   . '&name=' . urlencode("صفحة قياس {$T}")
   . '&code=' . urlencode('uxwdup_' . strtolower(substr($T, 6)))
   . '&owner_role_id=1'
   . '&icon=' . urlencode($chosen)
   . ($MODE === 'before' ? '&icon=' . urlencode($default) : '');
list($c, $h, $b) = up_req($MD, $jar, $q);
$msg = up_msg($h);
check(mb_strpos($msg, '✅') !== false, "حفظ الصفحة نفذ (الرسالة: " . mb_substr(trim($msg), 0, 60) . ")");
$mrow = $db->query("SELECT icon FROM modules WHERE code LIKE 'uxwdup%'")->fetch_assoc();
check($mrow !== null, 'صف الصفحة كُتب في modules');
if ($MODE === 'before') {
    check($mrow && $mrow['icon'] === $default,
        "**اختيار المستخدم ضاع**: حُفظت «{$mrow['icon']}» الافتراضية لا «{$chosen}» المختارة — كل اختيار أيقونة لا يُحفظ أبدًا");
} else {
    check($mrow && $mrow['icon'] === $chosen, "الأيقونة المختارة حُفظت حرفًا: «{$chosen}»");
}

/* ═══════════ ④ Projects/projects.php — background: fff (CSS فقط) ═══════════ */
head('④ Projects/projects.php — background: fff');
$PR = $BASE . '/Projects/projects.php';
list($c, $h, $b) = up_req($PR, $jar);
$badBg  = preg_match_all('~background:\s*fff\s*;~', $b, $x);
/* الإصلاح لم يكتفِ بـ#fff: بوابةُ «لون مثبَّت» ترفض hex عاريًا خارجَ الرموز،
   فالصيغةُ النهائيةُ رمزُ الشاشةِ نفسِها للأبيض: var(--c-surface). */
$goodBg = preg_match_all('~\.stats-\w+\s+\.stats-icon\s*\{\s*background:\s*var\(--c-surface\);~u', $b, $x);
info("background: fff (بلا #) ×{$badBg} · background: var(--c-surface) على stats-icon ×{$goodBg}");
if ($MODE === 'before') {
    check($badBg === 5, '**قيمة CSS غير صالحة تصل المتصفح 5 مرات** — المتصفح يسقطها فتبقى خلفية الأيقونة شفافة؛ لا علاقة للعيب بـPOST');
} else {
    check($badBg === 0 && $goodBg === 5, 'القيم الخمس صارت رمز var(--c-surface) الصالح');
}

up_sweep($db);
$db->close();
fwrite(STDOUT, "\n═══ النتيجة ({$MODE}): {$PASS} نجح · {$FAIL} فشل ═══\n");
exit($FAIL > 0 ? 1 : 0);
