<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * update0004 · الموجة ④ — مسبار HTTP: شاشات ORG الأربع (ORG-15→ORG-18)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل: php tests/org_screens_http_test.php   (يتطلب Apache حيًّا)
 *
 * ما يُثبته:
 *   ① التسجيل: الموديولات الأربعة وصلاحيات الدورين 1/6 وروابط التنقل.
 *   ② الدور 1 يفتح الشاشات الأربع 200 وبعلاماتها المميزة.
 *   ③ لوحة مدير التشغيل تعرض «ساعة» لا عدًّا فقط (ORG-01 §8).
 *   ④ دور بلا صلاحية يُعاد توجيهه (الإخفاء في الخادم).
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

$BASE = 'http://localhost/ems';
$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

function rq($url, $jar, $post = null) {
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
    $raw = curl_exec($ch);
    $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array($code, substr((string) $raw, 0, $hs), substr((string) $raw, $hs));
}
function login($user, $jar) {
    global $BASE;
    @unlink($jar);
    list(, , $b) = rq($BASE . '/login.php', $jar);
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
    return rq($BASE . '/login.php', $jar, array('username' => $user, 'password' => '12345678',
        'csrf_token' => isset($m[1]) ? $m[1] : ''));
}

require_once dirname(__DIR__) . '/includes/env.php';
$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'));
$db->set_charset('utf8mb4');

fwrite(STDOUT, "\n══ update0004 موجة ④ — شاشات ORG ══\n");

// Apache حي؟
list($c) = rq($BASE . '/login.php', sys_get_temp_dir() . '/org4_probe.jar');
if ($c !== 200) { fwrite(STDOUT, "Apache غير حي على {$BASE} — المسبار يتطلبه.\n"); exit(1); }

head('① التسجيل');
$r = $db->query("SELECT COUNT(*) c FROM modules WHERE code IN ('admin/ops_manager_board.php','admin/org_assignments.php','admin/org_structure.php','admin/org_permits.php')")->fetch_assoc();
check(intval($r['c']) === 4, 'الموديولات الأربعة مسجَّلة');
$r = $db->query("SELECT COUNT(*) c FROM role_permissions rp JOIN modules m ON m.id=rp.module_id
                 WHERE rp.role_id=1 AND m.code LIKE 'admin/org%' OR rp.role_id=1 AND m.code='admin/ops_manager_board.php'")->fetch_assoc();
check(intval($r['c']) >= 4, 'صلاحيات الدور 1 كاملة');
$r = $db->query("SELECT COUNT(*) c FROM nav_items WHERE role_id=1 AND route IN ('admin/ops_manager_board.php','admin/org_assignments.php','admin/org_structure.php','admin/org_permits.php')")->fetch_assoc();
check(intval($r['c']) === 4, 'روابط تنقل الدور 1 أربعة');
$r = $db->query("SELECT COUNT(*) c FROM nav_items WHERE role_id=6 AND route IN ('admin/org_permits.php','admin/org_assignments.php')")->fetch_assoc();
check(intval($r['c']) === 2, 'روابط تنقل الدور 6 اثنان');

head('② الدور 1 يفتح الأربع');
$jar = sys_get_temp_dir() . '/org4_r1.jar';
login('محمد', $jar);
$screens = array(
    'admin/ops_manager_board.php' => 'لوحة مدير التشغيل',
    'admin/org_assignments.php' => 'التكليفات التنظيمية',
    'admin/org_structure.php' => 'الهيكل التنظيمي',
    'admin/org_permits.php' => 'أذونات المواقع',
);
$bodies = array();
foreach ($screens as $path => $marker) {
    list($code, $h, $b) = rq($BASE . '/' . $path, $jar);
    $bodies[$path] = $b;
    check($code === 200 && mb_strpos($b, $marker) !== false, "{$path} → 200 + «{$marker}»");
}

head('③ اللوحة بالساعات لا بالعدد');
check(mb_strpos($bodies['admin/ops_manager_board.php'], 'ساعة') !== false
   && mb_strpos($bodies['admin/ops_manager_board.php'], 'بالساعات لا بالعدد') !== false,
   'مجموع ساعات الانتظار ظاهر نصًّا');
check(mb_strpos($bodies['admin/org_structure.php'], 'الموارد البشرية') !== false,
   'الهيكل يعرض الموارد البشرية (تحت التشغيل بالاستقلال الفني)');

head('④ دور بلا صلاحية يُحجب');
$jar2 = sys_get_temp_dir() . '/org4_r12.jar';
login('sales@equipation.sd', $jar2); // الدور 12 — لا صلاحية على شاشات ORG
list($code, $h) = rq($BASE . '/admin/org_assignments.php', $jar2);
check($code === 302 && stripos($h, 'Location') !== false, 'الدور 12: تحويل لا عرض — الإخفاء في الخادم');

fwrite(STDOUT, "\n══ النتيجة: PASS={$PASS} · FAIL={$FAIL} ══\n");
exit($FAIL === 0 ? 0 : 1);
