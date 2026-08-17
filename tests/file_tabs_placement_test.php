<?php
/**
 * tests/file_tabs_placement_test.php — موضعُ شريطَي الملفِّ والرحلةِ في المُصيَّر
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ◆ **العطبُ الذي يحرسه** (قرارُ المالك 2026-08-19)
 *   شريطُ تبويباتِ الملفِّ («الملف · بنودُ العقد · الوثائق …») كان يُطبع **قبل**
 *   `<div class="main">` في 24 شاشة، وشريطُ رحلةِ الكِيان في 25 شاشةً أخرى —
 *   فيخرجان من غلافِ الشاشةِ ويظهران **بجانبِها** لا داخلَها.
 *   والموضعُ المُلزِم: تحتَ الشريطِ الأصفرِ (`.main_head` حاملِ زرِّ «عن الشاشة»)
 *   ← تبويباتُ الملفِّ ← رحلةُ الكِيان ← المحتوى.
 *
 * ◆ **ولِمَ يُقاس بالمُصيَّرِ لا بالمصدر**: الترتيبُ ينشأ من **لحظةِ النداء**
 *   لا من ترتيبِ الأسطر — شاشةٌ تنادي المكوّنَ قبلَ الرأسِ تُصَفُّ، وأخرى
 *   بعدَه تُطبع مكانَها. ولا يُرى الفرقُ إلا في مخرَجِ الخادم.
 *
 * ◆ **وما لم يُقَس يُعلَن**: شاشةٌ حُوِّل عنها بحسابِها أو بلا عيّنةٍ تُذكر
 *   صراحةً — فلا يُقرأ «صفرُ رسوبٍ» على أنّ الكلَّ مقيسٌ سليم.
 *
 * يتطلّب Apache حيًّا:  php tests/file_tabs_placement_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$BASE = 'http://localhost/ems';
$PASS = 0; $FAIL = 0; $SKIP = array();
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  \xE2\x9C\x94 {$m}\n"); }
function bad($m, $d = '') { global $FAIL; $FAIL++; fwrite(STDOUT, "  \xE2\x9C\x96 {$m}" . ($d !== '' ? " — {$d}" : '') . "\n"); }

$JARS = array();
function req($url, $user)
{
    global $JARS, $BASE;
    if (!isset($JARS[$user])) {
        $jar = sys_get_temp_dir() . '/ftp_' . md5($user) . '.jar';
        @unlink($jar);
        $JARS[$user] = $jar;
        $ch = curl_init("$BASE/login.php");
        curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => 1, CURLOPT_COOKIEJAR => $jar,
            CURLOPT_COOKIEFILE => $jar, CURLOPT_FOLLOWLOCATION => 1, CURLOPT_TIMEOUT => 40));
        $lg = curl_exec($ch); curl_close($ch);
        preg_match('~name="csrf_token"\s+value="([^"]+)"~', $lg, $m);
        $ch = curl_init("$BASE/login.php");
        curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => 1, CURLOPT_COOKIEJAR => $jar,
            CURLOPT_COOKIEFILE => $jar, CURLOPT_FOLLOWLOCATION => 1, CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => http_build_query(array('username' => $user,
                'password' => '12345678', 'csrf_token' => isset($m[1]) ? $m[1] : '')),
            CURLOPT_TIMEOUT => 40));
        curl_exec($ch); curl_close($ch);
    }
    $ch = curl_init("$BASE/$url");
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => 1, CURLOPT_COOKIEJAR => $JARS[$user],
        CURLOPT_COOKIEFILE => $JARS[$user], CURLOPT_FOLLOWLOCATION => 1, CURLOPT_TIMEOUT => 40));
    $b = curl_exec($ch);
    $eff = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    return array((string) $b, $eff);
}

echo "\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90 موضعُ شريطَي الملفِّ والرحلة \xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\n\n";

$ping = @file_get_contents("$BASE/login.php");
if ($ping === false) { exit("\xE2\x9C\x96 Apache لا يستجيب على {$BASE}\n"); }

/* عيّنةٌ من كلِّ عائلةٍ بحسابِها المخوَّل — الخريطةُ مرآةُ tools/uxw_accounts.txt */
$SCREENS = array(
    array('مصعب', 'Suppliers/supplier_profile.php?id=%SUP%'),
    array('مصعب', 'Suppliers/supplier_contract_lines.php?supplier_id=%SUP%'),
    array('مصعب', 'Suppliers/supplier_capacity.php?supplier_id=%SUP%'),
    array('مصعب', 'Suppliers/supplier_documents.php?supplier_id=%SUP%'),
    array('مصعب', 'Suppliers/supplier_rules.php?supplier_id=%SUP%'),
    array('مصعب', 'Suppliers/supplier_evaluation.php?supplier_id=%SUP%'),
    array('مصعب', 'Suppliers/supplier_closure.php?supplier_id=%SUP%'),
    array('مشرف المالية', 'Finance/supplier_statement_fin.php?supplier_id=%SUP%'),
    array('مبيعات', 'Contracts/contracts_details.php?id=%CON%'),
    array('مبيعات', 'Contracts/contract_lines.php?contract=%CON%'),
    array('مبيعات', 'Contracts/contract_obligations.php?contract=%CON%'),
    array('مبيعات', 'Contracts/contract_sites.php?contract=%CON%'),
    array('مبيعات', 'Contracts/price_terms.php?contract=%CON%'),
    array('مبيعات', 'Contracts/penalties.php?contract=%CON%'),
    array('مبيعات', 'Contracts/contract_baseline.php?contract=%CON%'),
    array('مبيعات', 'Contracts/contract_guarantees.php?contract=%CON%'),
    array('مبيعات', 'Contracts/contract_monthly_plan.php?contract=%CON%'),
    array('مبيعات', 'Contracts/contract_payment_schedule.php?contract=%CON%'),
    array('مبيعات', 'Contracts/contract_resource_plan.php?contract=%CON%'),
    array('مبيعات', 'Contracts/contract_lifecycle.php?contract=%CON%'),
    array('مبيعات', 'Contracts/plan_actual_link.php?contract=%CON%'),
    array('مبيعات', 'Clients/contract_amendments.php?contract=%CON%'),
    array('مبيعات', 'Clients/contract_events.php?contract=%CON%'),
    array('مبيعات', 'Clients/contract_commitments.php?contract=%CON%'),
    array('تمويل', 'Financing/operation_profile.php?id=%OPR%'),
    array('تمويل', 'Financing/installments.php?op=%OPR%'),
);

/* المُعرِّفاتُ تُلتقط من القاعدةِ لا تُثبَّت في فاحص */
define('EMS_CLI', true);
require_once dirname(__DIR__) . '/includes/session_bootstrap.php';
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$pick = function ($sql) use ($conn) {
    $r = @$conn->query($sql);
    $row = $r ? $r->fetch_row() : null;
    return $row ? (string) $row[0] : '';
};
$IDS = array(
    '%SUP%' => $pick("SELECT id FROM suppliers WHERE company_id=4 AND COALESCE(is_deleted,0)=0 ORDER BY id LIMIT 1"),
    '%CON%' => $pick("SELECT id FROM contracts WHERE company_id=4 ORDER BY id LIMIT 1"),
    '%OPR%' => $pick("SELECT op_id FROM financing_operations WHERE company_id=4 ORDER BY op_id LIMIT 1"),
);
foreach ($IDS as $k => $v) {
    if ($v === '') { $SKIP[] = "لا عيّنةَ لـ{$k} في الشركة 4"; }
}

foreach ($SCREENS as $sc) {
    list($user, $path) = $sc;
    $miss = false;
    foreach ($IDS as $k => $v) {
        if (strpos($path, $k) !== false) {
            if ($v === '') { $miss = true; break; }
            $path = str_replace($k, $v, $path);
        }
    }
    if ($miss) { $SKIP[] = "{$sc[1]}: بلا عيّنة"; continue; }

    list($body, $eff) = req($path, $user);
    $want = basename(explode('?', $path)[0]);
    if (strpos($eff, $want) === false) {
        $SKIP[] = "{$path}: حُوِّل عنها بحساب «{$user}» ⇐ " . basename(parse_url($eff, PHP_URL_PATH));
        continue;
    }

    $main = strpos($body, '<div class="main');
    $head = strpos($body, 'main_head');
    $ftab = strpos($body, 'ems-file-tabs');
    $etab = strpos($body, 'ems-entity-tabs');
    $short = basename(dirname($path)) . '/' . $want;

    if ($ftab === false) { bad("{$short}: شريطُ الملفِّ مُصيَّر", 'غائبٌ كليًّا'); continue; }
    if ($main === false || $head === false) { bad("{$short}: قشرةُ الشاشةِ مُصيَّرة", 'لا .main أو .main_head'); continue; }

    /* ① داخلَ الشاشةِ وتحتَ الشريطِ الأصفر */
    if ($head < $ftab) { ok("{$short}: شريطُ الملفِّ تحتَ الشريطِ الأصفر"); }
    else { bad("{$short}: شريطُ الملفِّ تحتَ الشريطِ الأصفر", "رأس={$head} ملف={$ftab}"); }

    /* ② وفوقَ رحلةِ الكِيانِ إن وُجدت */
    if ($etab !== false) {
        if ($ftab < $etab) { ok("{$short}: رحلةُ الكِيانِ تحتَ شريطِ الملف"); }
        else { bad("{$short}: رحلةُ الكِيانِ تحتَ شريطِ الملف", "ملف={$ftab} رحلة={$etab}"); }
    }

    /* ③ صفرُ بقايا من الشرائطِ الثلاثةِ القديمة */
    $legacy = substr_count($body, 'sf-tabs') + substr_count($body, 'cf-tabs') + substr_count($body, 'ff-tabs');
    if ($legacy === 0) { ok("{$short}: صفرُ بقايا من الشريطِ القديم"); }
    else { bad("{$short}: صفرُ بقايا من الشريطِ القديم", "المقيس={$legacy}"); }
}

echo "\n" . str_repeat("\xE2\x94\x80", 60) . "\n";
if ($SKIP) {
    echo "  \xE2\x9A\xA0 لم تُقَس (" . count($SKIP) . "):\n";
    foreach ($SKIP as $s) { echo "      \xC2\xB7 {$s}\n"; }
}
echo "  نجح: {$PASS}   \xC2\xB7   رسب: {$FAIL}\n";
exit($FAIL > 0 ? 1 : 0);
