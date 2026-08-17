<?php
/**
 * tests/infooter_body_close_test.php — إغلاقُ الجسدِ في الشاشاتِ التسعِ اليتيمة
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ◆ **العطبُ الذي يحرسه** (2026-08-17)
 *   تسعُ شاشاتٍ كانت تُنهي نفسَها بـ`include '../infooter.php'` — وهو ملفٌّ
 *   **لا وجودَ له في الشجرةِ كلِّها**، ولا `set_include_path` في النظامِ يُنقذه.
 *   فكان لكلِّ طلبٍ أثران مقيسان:
 *     ① تحذيرانِ في `logs/php_errors.log` (فتحُ المجرى ثم فشلُ التضمين).
 *     ② **زرُّ «أبلغ عن مشكلة» الطافي لا يُحقن أبدًا**: `ems_report_button_capture()`
 *        في `includes/report_button.php` يرسو على `strripos($html, '</body>')`
 *        ويردُّ المُخرَجَ **كما هو** إن لم يجد جسدًا — وهذه التسعُ صفرُ `</body>`
 *        في مصدرِها، فالتضمينُ الغائبُ كان إغلاقَها الوحيد. فسقطت عنها كلِّها
 *        نقطةُ دخولِ الإبلاغِ المُلزِمةُ في TKT-01 §2-⑧ — صامتةً.
 *
 * ◆ **العلاجُ المُصطلَحُ عليه**: إغلاقٌ حرفيٌّ `</body></html>` كما في الشاشاتِ
 *   المحوَّلة (`Suppliers/supplier_advances.php` · و`Suppliers/sup_handover.php`
 *   التي عولجت هكذا في 2026-08-17 مرجعًا).
 *
 * ◆ **ولِمَ يُقاس بالمُصيَّرِ لا بالمصدر**: الزرُّ يُحقن في **مُعالِجِ حجزِ
 *   المُخرَج** لحظةَ الإفراغ، لا في نصِّ الشاشة. فوجودُ `</body>` في المصدرِ
 *   لا يُثبت الحقنَ، ولا يُرى إلا في ما يصل المتصفحَ فعلًا.
 *
 * ◆ **وأربعةُ أحكامٍ لكلِّ شاشةٍ — لا واحد**:
 *     ① HTTP 200 (لا تحويلَ عن الشاشة).
 *     ② `</body>` **مرةً واحدةً بالضبط** — لا صفرَ (بلا إغلاق) ولا اثنتين
 *        (إغلاقٌ مزدوجٌ لو أُضيف فوق تضمينٍ صار موجودًا لاحقًا).
 *     ③ «أبلغ عن مشكلة» حاضرٌ في المُصيَّر.
 *     ④ **لا سطرَ `infooter` جديدٌ في السجل** — يُقاس بإزاحةِ الملفِّ قبلَ
 *        الطلبِ وقراءةِ ذيلِه بعدَه، فلا تلتبس تحذيراتُ اليومِ بتحذيراتِ أمس.
 *
 * التشغيل:  /c/wamp64/bin/php/php8.2.30/php.exe tests/infooter_body_close_test.php
 * (يتطلّب Apache حيًّا)
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$BASE = 'http://localhost/ems';
$LOG  = dirname(__DIR__) . '/logs/php_errors.log';
$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "    \xE2\x9C\x94 {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "    \xE2\x9C\x96 FAIL: {$m}\n"); }
function chk($c, $m) { $c ? ok($m) : bad($m); }

/* خريطةُ الحساباتِ مرآةُ tools/uxw_accounts.txt — البادئةُ الأخصُّ تسبق */
$SCREENS = array(
    array('Finance/currencies_fin.php',            'مشرف المالية'),
    array('Finance/daily_pricing_fin.php',         'مشرف المالية'),
    array('Operations/containers.php',             'محمد'),
    array('Suppliers/settlements.php',             'مصعب'),
    array('Workforce/employee_settlements.php',    'مشرف الموارد'),
    array('Contracts/unit_client_match.php',       'مبيعات'),
    array('Contracts/unit_statement_client.php',   'مبيعات'),
    array('Suppliers/unit_statement_supplier.php', 'مصعب'),
    array('main/glossary.php',                     'محمد'),
);

$JARS = array();
/** دخولٌ مرةً واحدةً لكلِّ حسابٍ — الجرّةُ تُعاد لكلِّ شاشاتِه. */
function jar_for($user)
{
    global $JARS, $BASE;
    if (isset($JARS[$user])) { return $JARS[$user]; }
    $jar = sys_get_temp_dir() . '/ifb_' . md5($user) . '.jar';
    @unlink($jar);
    $ch = curl_init("{$BASE}/login.php");
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => 1, CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar, CURLOPT_FOLLOWLOCATION => 1, CURLOPT_TIMEOUT => 40));
    $lg = (string) curl_exec($ch); curl_close($ch);
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $lg, $m);
    $ch = curl_init("{$BASE}/login.php");
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => 1, CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar, CURLOPT_FOLLOWLOCATION => 1, CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => http_build_query(array('username' => $user,
            'password' => '12345678', 'csrf_token' => isset($m[1]) ? $m[1] : '')),
        CURLOPT_TIMEOUT => 40));
    curl_exec($ch); curl_close($ch);
    return $JARS[$user] = $jar;
}

/** طلبٌ **بلا اتّباعِ تحويل** — فرمزُ الحالةِ المقيسُ هو رمزُ الشاشةِ نفسِها. */
function fetch_raw($url, $jar)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => 1, CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar, CURLOPT_FOLLOWLOCATION => 0, CURLOPT_TIMEOUT => 45));
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array($code, $body);
}

fwrite(STDOUT, "\n\xE2\x95\x90\xE2\x95\x90 إغلاقُ الجسدِ · التسعُ الشاشاتُ اليتيمة \xE2\x95\x90\xE2\x95\x90\n");

if (@file_get_contents("{$BASE}/login.php") === false) {
    exit("\xE2\x9C\x96 Apache لا يستجيب على {$BASE}\n");
}

foreach ($SCREENS as $sc) {
    list($path, $user) = $sc;
    fwrite(STDOUT, "\n\xE2\x94\x80\xE2\x94\x80 {$path}  \xC2\xAB{$user}\xC2\xBB\n");
    $jar = jar_for($user);

    /* إزاحةُ السجلِّ **قبلَ** الطلبِ — وحدَها تفصل تحذيرَ هذا الطلبِ عن تاريخِه */
    clearstatcache(true, $LOG);
    $off = is_file($LOG) ? (int) filesize($LOG) : 0;

    list($code, $body) = fetch_raw("{$BASE}/{$path}", $jar);

    clearstatcache(true, $LOG);
    $tail = '';
    if (is_file($LOG) && filesize($LOG) > $off) {
        $fh = fopen($LOG, 'rb');
        if ($fh) { fseek($fh, $off); $tail = (string) stream_get_contents($fh); fclose($fh); }
    }
    $screen = basename($path);
    $hits = array();
    foreach (explode("\n", $tail) as $ln) {
        if (stripos($ln, 'infooter') !== false && stripos($ln, $screen) !== false) {
            $hits[] = trim($ln);
        }
    }

    $bodies = substr_count(strtolower($body), '</body>');

    chk($code === 200, "HTTP 200 (المقيس {$code})");
    chk($bodies === 1, "\xE2\x80\xB9/body\xE2\x80\xBA مرةً واحدةً بالضبط (المقيس {$bodies})");
    /* ◆ **لا يُقاس بالعبارةِ — العبارةُ تُطابِق كذبًا**: نصُّ حالةِ الخطأِ
       المشتركُ في `includes/ux_components.php` يقول «… وإن استمر الخللُ **أبلغ
       عن مشكلةٍ** من هذه الشاشة»، و«مشكلةٍ» تبدأ بـ«مشكلة» — فبحثُ العبارةِ
       يمرُّ في الشاشاتِ التسعِ **وهي بلا زرٍّ أصلًا** (مقيسٌ: ٩ من ٩ خُضْرٌ قبلَ
       أيِّ إصلاح). والمرساةُ الصادقةُ صنفُ الوعاءِ الذي يبنيه
       `ems_report_button_fallback()` وحدَه — وهي مرساةُ
       `tests/report_button_placement_test.php` نفسُها. */
    $fb = strpos($body, 'ems-report-fallback');
    $bd = strripos($body, '</body>');
    chk($fb !== false, 'زرُّ «أبلغ عن مشكلة» الطافي مُحقَنٌ (مرساةُ ems-report-fallback)');
    chk($fb !== false && $bd !== false && $fb < $bd, 'وموضعُه **قبل** ‹/body› — داخلَ الجسد');
    chk(count($hits) === 0, 'لا تحذيرَ infooter جديدًا في السجل'
        . (count($hits) ? ' — ' . count($hits) . ': ' . mb_substr($hits[0], 0, 120) : ''));
}

fwrite(STDOUT, "\n\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح \xC2\xB7 {$FAIL} فاشل  (" . count($SCREENS) . " شاشاتٍ \xC3\x97 5 أحكام)\n");
exit($FAIL === 0 ? 0 : 1);
