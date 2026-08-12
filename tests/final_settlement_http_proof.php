<?php
/**
 * tests/final_settlement_http_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 * برهانُ HTTP للشاشة **167** — تصفيةُ إنهاء خدمة الموظف (M-22 · ENT-01 §6).
 * من الشاشة الحقيقية بدورَين مختلفين:
 *   ① شؤونُ الموظفين (4) ترى المعاينةَ المحسوبةَ **قبل** الفتح — و**لا ترى زرَّ الاعتماد**
 *   ② والبندُ الذي لا قاعدةَ له يظهر **موسومًا «بلا قاعدة»** لا بصفرٍ صامت
 *   ③ الفتحُ من الشاشة يكتب الأرقامَ المحسوبة
 *   ④ المديرُ المالي (17) يرى **نموذجَ الاعتماد بمرفق الإخلاء**
 *   ⑤ واعتمادُه يقع — واعتمادُ من أعدّ **يُرفض 403 من الخادم**
 * ثم يكنس كلَّ ما بذر.
 *
 * التشغيل: php tests/final_settlement_http_proof.php   (يتطلب Apache حيًّا)
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

$BASE = 'http://localhost/ems';
$TMP  = sys_get_temp_dir();
$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

function fs_req($url, $jar, $post = null) {
    /* الرمزُ المركزيُّ: الحارسُ يقبله في الحقولِ أو الترويسة، والمسبارُ
       يبني الحقولَ يدويًّا فلا يحمله ⇒ 403 صامتٌ يُقرأ فشلًا في المنتج. */
    if ($post !== null) {
        require_once __DIR__ . '/_http_flash.php';
        $post = ems_http_with_csrf($post, $url, $jar);
    }
    $GLOBALS['__ems_last_url'] = $url;   // لحلِّ Location النسبيّ
    $GLOBALS['__ems_last_jar'] = $jar;   // الجرَّةُ وسيطٌ صريحٌ لا حالةٌ عامّة
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
    return array($code, substr($raw, 0, $hs), substr($raw, $hs));
}
function fs_login($user, $jar) {
    global $BASE;
    @unlink($jar);
    list($c, $h, $b) = fs_req($BASE . '/login.php', $jar);
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
    return fs_req($BASE . '/login.php', $jar, array('username' => $user, 'password' => '12345678',
        'csrf_token' => isset($m[1]) ? $m[1] : ''));
}
/* ══ **الرسالةُ وميضُ جلسةٍ لا مُعامَلُ عنوان.** `ems_gov_flash_redirect`
     (`includes/permissions_helper.php:139`) يخزّن النصَّ في
     `$_SESSION['ems_flash_gov']` ويوجّه **بعنوانٍ مجرَّد** — فقراءةُ `?msg=`
     تعود فارغةً **دائمًا** فيُقرأ صمتُها فشلًا، والمنتجُ نفَّذ الفعلَ وكتب
     رسالتَه حيث ينبغي. ويبقى المُعامَلُ مقروءًا **أوّلًا** لأن شاشاتٍ أخرى
     (المشتريات · الفتراتُ الماليةُ · تسوياتُ الموردين) تضعها فيه فعلًا. */
function fs_msg($headers) {
    require_once __DIR__ . '/_http_flash.php';
    $__dir = isset($GLOBALS['__ems_last_url'])
        ? preg_replace('~/[^/]*(\?.*)?$~', '', (string) $GLOBALS['__ems_last_url'])
        : '';
    return ems_flash_or_msg($headers, $__dir, function ($u) {
        $r = fs_req($u, $GLOBALS['__ems_last_jar'], null);
        return is_array($r) ? (string) $r[count($r) - 1] : (string) $r;
    });
}

require_once dirname(__DIR__) . '/includes/env.php';
$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'));
if ($db->connect_error) { fwrite(STDERR, "DB: {$db->connect_error}\n"); exit(1); }
$db->set_charset('utf8mb4');

$CO   = 4;
$MARK = 'M22H' . getmypid();
$CUR  = 'USD';
$rc = $db->query("SELECT code FROM fin_currencies WHERE company_id={$CO} ORDER BY code LIMIT 1");
if ($rc && ($x = $rc->fetch_assoc())) { $CUR = (string) $x['code']; }

$teardown = function () use ($db, $MARK) {
    $db->query("DELETE b FROM ems_business_events b
                  JOIN employee_final_settlements f ON f.id = b.entity_id
                  JOIN employees e ON e.id = f.employee_id
                 WHERE b.entity_type='employee_final_settlement' AND e.name LIKE '%{$MARK}%'");
    $db->query("DELETE l FROM employee_final_settlement_lines l
                  JOIN employee_final_settlements f ON f.id = l.settlement_id
                  JOIN employees e ON e.id = f.employee_id WHERE e.name LIKE '%{$MARK}%'");
    $db->query("DELETE f FROM employee_final_settlements f
                  JOIN employees e ON e.id = f.employee_id WHERE e.name LIKE '%{$MARK}%'");
    $db->query("DELETE d FROM fin_dues d JOIN employees e ON e.id = d.party_ref
                 WHERE d.party_type='employee' AND e.name LIKE '%{$MARK}%'");
    $db->query("DELETE s FROM contract_snapshots s JOIN employee_contracts c ON c.id = s.contract_id
                  JOIN employees e ON e.id = c.employee_id WHERE e.name LIKE '%{$MARK}%'");
    $db->query("DELETE p FROM pay_components p JOIN employee_contracts c ON c.id = p.contract_id
                  JOIN employees e ON e.id = c.employee_id WHERE e.name LIKE '%{$MARK}%'");
    $db->query("DELETE c FROM employee_contracts c JOIN employees e ON e.id = c.employee_id
                 WHERE e.name LIKE '%{$MARK}%'");
    $db->query("DELETE FROM employees WHERE name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ برهانُ HTTP — الشاشة 167 · تصفيةُ إنهاء خدمة الموظف ══\n");

head('البذر — عقدٌ منتهٍ بأساسٍ 3000 وقاعدةِ نهاية خدمةٍ 30 يومًا (وبلا قاعدةِ إجازة)');
$db->query("INSERT INTO employees (employee_type, company_id, name, phone, is_workforce, created_at)
            VALUES ('عامل', {$CO}, 'موظفُ {$MARK}', '0100000000', 1, NOW())");
$EMP = intval($db->insert_id);
$db->query("INSERT INTO employee_contracts (company_id, employee_id, category, pay_model_id,
            start_date, end_date, currency, eos_days_per_year, state, version, created_by, created_at)
            VALUES ({$CO}, {$EMP}, 'permanent', 1, '2093-01-01', '2094-01-01', '{$CUR}',
                    30, 'terminated', 1, 1, NOW())");
$CID = intval($db->insert_id);
$db->query("INSERT INTO pay_components (company_id, contract_id, component_type, calc_method,
            value, in_eos, in_leave_pay, state, created_at)
            VALUES ({$CO}, {$CID}, 'basic', 'fixed_amount', 3000, 1, 0, 'active', NOW())");
check($EMP > 0 && $CID > 0, "موظفٌ #{$EMP} وعقدٌ منتهٍ #{$CID}");

// ═══ ① شؤونُ الموظفين ═══
head('① شؤونُ الموظفين (4) — المعاينةُ المحسوبةُ قبل الفتح');
$jarHR = $TMP . "/fs_hr_{$MARK}.txt";
fs_login('اروينا', $jarHR);
list($c, $h, $b) = fs_req($BASE . "/Workforce/final_settlement.php?preview={$CID}&as_of=2094-01-01", $jarHR);
check($c === 200 && mb_strpos($b, 'تصفية إنهاء خدمة الموظف') !== false, 'الشاشةُ 167 تُفتح بالدور 4 (200)');
check(mb_strpos($b, 'نهايةُ الخدمة') !== false && mb_strpos($b, '2997') !== false,
      '★★ والمعاينةُ تعرض **نهايةَ الخدمة 2997** محسوبةً من اللقطة — بلا إدخالِ رقم');
check(mb_strpos($b, 'ببصمة') !== false, 'واللقطةُ ببصمتها ظاهرةٌ في الشاشة');
check(mb_strpos($b, 'بلا قاعدة') !== false,
      '★ ورصيدُ الإجازة **موسومٌ «بلا قاعدة»** — يُعلَن ولا يُقدَّر');
check(mb_strpos($b, 'اعتمد التصفية') === false,
      '★ ولا يرى الدورُ 4 زرَّ **الاعتماد** — فصلُ اليدين في الظهور أيضًا');

// ═══ ② الفتحُ من الشاشة ═══
head('② الفتحُ من الشاشة يكتب الأرقامَ المحسوبة');
list($c, $h, $b) = fs_req($BASE . '/Workforce/final_settlement.php', $jarHR, array(
    'fs_action' => 'open', 'contract_id' => $CID, 'effective_date' => '2094-01-01'));
$msg = fs_msg($h);
check(mb_strpos($msg, 'فُتحت التصفيةُ') !== false, 'رسالةُ الفتح: ' . $msg);
$row = $db->query("SELECT id, net_amount, recognized_amount, snapshot_id, prepared_by
                     FROM employee_final_settlements WHERE contract_id={$CID}")->fetch_assoc();
check($row && abs((float) $row['net_amount'] - 2997.0) < 0.005 && (int) $row['snapshot_id'] > 0,
      '★★ والصفُّ المكتوب **2997** بلقطته — لا مُدخَل يدوي');
$SID = intval($row['id']);

// ═══ ③ اعتمادُ من أعدّ يُرفض من الخادم ═══
head('③ **اعتمادُ من أعدّ يُرفض في الخادم** لا بإخفاء زر');
list($c, $h, $b) = fs_req($BASE . '/Workforce/final_settlement.php', $jarHR, array(
    'fs_action' => 'approve', 'settlement_id' => $SID, 'clearance_doc' => 'محضر ' . $MARK));
$msg = fs_msg($h);
$st = $db->query("SELECT state FROM employee_final_settlements WHERE id={$SID}")->fetch_assoc();
check((string) $st['state'] === 'draft' && $msg !== '',
      '★ الطلبُ المزوَّرُ من يد الإعداد **لم يغيّر الحالة** — ' . $msg);

// ═══ ④⑤ المدير المالي ═══
head('④ المديرُ المالي (17) — نموذجُ الاعتماد بمرفق الإخلاء');
$jarFIN = $TMP . "/fs_fin_{$MARK}.txt";
fs_login('مديرمالي', $jarFIN);
list($c, $h, $b) = fs_req($BASE . '/Workforce/final_settlement.php', $jarFIN);
check($c === 200 && mb_strpos($b, 'اعتمد التصفية') !== false,
      '★ الدورُ 17 يرى **زرَّ الاعتماد ومرفقَ الإخلاء**');
check(mb_strpos($b, 'افتح التصفيةَ بهذه الأرقام') === false,
      'ولا يرى نموذجَ الفتح — «من أنشأ لا يعتمد» في الاتجاهين');

head('⑤ والاعتمادُ يقع — بذمّةٍ باسمها وحقيقةٍ واحدة');
list($c, $h, $b) = fs_req($BASE . '/Workforce/final_settlement.php', $jarFIN, array(
    'fs_action' => 'approve', 'settlement_id' => $SID, 'clearance_doc' => 'محضر إخلاء ' . $MARK));
$msg = fs_msg($h);
check(mb_strpos($msg, 'اعتُمدت التصفية') !== false, 'رسالةُ الاعتماد: ' . $msg);
$row = $db->query("SELECT state, net_due_ref, clearance_doc FROM employee_final_settlements
                    WHERE id={$SID}")->fetch_assoc();
check((string) $row['state'] === 'approved' && (int) $row['net_due_ref'] > 0,
      '★★ الحالةُ **معتمدة** وذمّتُها #' . intval($row['net_due_ref']));
$d = $db->query("SELECT due_type, source_doc_type, amount FROM fin_dues
                  WHERE id=" . intval($row['net_due_ref']))->fetch_assoc();
check((string) $d['due_type'] === 'end_of_service' && (string) $d['source_doc_type'] === 'employee_closure'
      && abs((float) $d['amount'] - 2997.0) < 0.005,
      'والذمّةُ **باسمها** بمصدرها المسمّى بمبلغ 2997');
$ev = $db->query("SELECT COUNT(*) c FROM ems_business_events
                   WHERE entity_type='employee_final_settlement' AND entity_id={$SID}")->fetch_assoc();
check((int) $ev['c'] === 1, '★ وحقيقةٌ واحدةٌ في الجذر');

fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
