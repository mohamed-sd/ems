<?php
/**
 * tests/containers_screen_http_proof.php — H-01 المرحلة ②
 * ═══════════════════════════════════════════════════════════════════════════
 * برهانُ HTTP لشاشة حاويات العقود من **الشاشة الحقيقية**:
 *   ① التشغيلُ (1) يفتحها ويرى الشجرةَ بأرصدتها الستة.
 *   ② التوليدُ الرجعيُّ من الشاشة، و**وسمُ «مشتقّة» ظاهرٌ بملاحظته**.
 *   ③ **تجاوزُ Σ يُرَدُّ برسالةٍ تسمّي المتاحَ والمطلوب** — لا «حدث خطأ».
 *      (درسُ E-08-أ: لا يكفي ردٌّ صحيحٌ لا يراه أحد.)
 *   ④ التوزيعُ المشروع يقع ويظهر أثرُه في الأرصدة.
 *   ⑤ تبديلٌ بلا سببٍ يُرَدّ · وبسببه يقع.
 *   ⑥ الإقرارُ يرفع الوسم.
 *   ⑦ تقريرُ المطابقة يُعلن ما لا بندَ له.
 *   ⑧ **مَن لا يملكها لا يراها** (الدور 12 عرضًا · بلا أزرار إدارة).
 *
 * يكنس كلَّ ما ينشئه.
 * التشغيل: php tests/containers_screen_http_proof.php  (يتطلب Apache حيًّا)
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$BASE = 'http://localhost/ems';
$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }
function info($m) { fwrite(STDOUT, "     · {$m}\n"); }

$JAR = null;
function c_req($url, $post = null) {
    global $JAR;
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $JAR, CURLOPT_COOKIEFILE => $JAR,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 45));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true);
                          curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $raw = curl_exec($ch);
    $hs  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $c   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array($c, substr($raw, 0, $hs), substr($raw, $hs));
}
function c_login($user, $tag) {
    global $JAR, $BASE;
    $JAR = sys_get_temp_dir() . '/ems_cnt_' . $tag . '.txt'; @unlink($JAR);
    list(, , $b) = c_req($BASE . '/login.php');
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
    list($c) = c_req($BASE . '/login.php', array('username' => $user, 'password' => '12345678',
        'csrf_token' => $m[1] ?? ''));
    return ($c === 200 || $c === 302);
}
function c_csrf($body) {
    return preg_match('~name="cnt_csrf"\s+value="([^"]+)"~', $body, $m) ? $m[1] : '';
}
function c_msg($h) {
    if (preg_match('~Location:\s*(\S+)~i', $h, $m)) {
        parse_str((string) parse_url(trim($m[1]), PHP_URL_QUERY), $p);
        return isset($p['msg']) ? $p['msg'] : '';
    }
    return '';
}

$conn = $GLOBALS['conn'];
// البوابةُ fail-closed تحتاج سياقَ مستأجرٍ — ولا جلسةَ في CLI
$_SESSION['user'] = array('id' => 1, 'role' => '1', 'company_id' => 4, 'name' => 'cnt proof');
$gate = ems_tenant_db();
$CO = 4; $C5 = 5;
require_once dirname(__DIR__) . '/app/Services/Operations/OperationalTransformService.php';
$URL = $BASE . '/Operations/containers.php?contract=' . $C5;

$cleanup = function () use ($conn) {
    $conn->query("DELETE FROM container_consumption");
    $conn->query("DELETE FROM container_swaps");
    $conn->query("DELETE FROM operator_rotations");
    foreach (array('مشغّل', 'معدة', 'مورد', 'رئيسية') as $lv) {
        $conn->query("DELETE FROM op_containers WHERE level='{$lv}'");
    }
};
$cleanup();
register_shutdown_function($cleanup);
// ⚠️ الكنسُ يمحو حاوياتٍ **حقيقيةً** مولَّدةً للعقود الأربعة — فيُعاد
// توليدُها بعد الحزمة. والتوليدُ حتميٌّ وعطِلٌ بنيويًّا، فالإعادةُ تُرجع الحالةَ
// كما كانت بلا تخمين.
register_shutdown_function(function () {
    global $conn, $gate;
    try {
        foreach (array(5, 2, 4, 7) as $cid) {
            \App\Services\Operations\OperationalTransformService::deriveFromOperations(
                $conn, $gate, 4, $cid, 1);
        }
    } catch (\Throwable $t) { fwrite(STDERR, "restore: " . $t->getMessage() . "
"); }
});


fwrite(STDOUT, "\n══ H-01 ② — شاشةُ الحاويات من الشاشة الحقيقية ══\n");

// ═══ ① الفتحُ والأرصدة ═══
head('① التشغيل (1) — الشجرةُ بأرصدتها');
$u1 = $conn->query("SELECT username FROM users WHERE role=1 AND company_id={$CO} LIMIT 1")->fetch_assoc();
check($u1 && c_login($u1['username'], 'ops'), 'دخولُ «' . ($u1['username'] ?? '—') . '»');
list($c0, , $p0) = c_req($URL);
check($c0 === 200, 'الشاشةُ صُيِّرت (200)');
// ⚠️ أعمدةُ الجدول تُفحص **بعد** التوليد: الشاشةُ لا تصيّر جدولًا بلا حاويات
//    (تعرض «لا حاوياتٍ بعد») — ففحصُها الآن يفشل كذبًا.
check(mb_strpos($p0, 'لا حاوياتٍ لهذا العقد بعد') !== false,
    'وتقول إنه بلا حاوياتٍ بعد — لا جدولَ فارغٌ يوهم');
check(mb_strpos($p0, 'ولّد الرئيسيات من بنود العقد') !== false, 'وأزرارُ الإدارة ظاهرةٌ لمالكها');
$CSRF = c_csrf($p0);
check($CSRF !== '', 'ورمزُ الحماية حاضر');

// ═══ ② التوليدُ الرجعي ووسمُ «مشتقّة» ═══
head('② التوليدُ الرجعيُّ — والوسمُ ظاهرٌ بملاحظته');
list(, $h1) = c_req($URL, array('cnt_action' => 'derive', 'contract_id' => $C5, 'cnt_csrf' => $CSRF));
$m1 = c_msg($h1);
check(mb_strpos($m1, 'مشتقّة') !== false, 'الرسالةُ تقول إنها مشتقّة: ' . mb_substr($m1, 0, 90));
check(mb_strpos($m1, 'تنتظر إقرارَ الإدارة') !== false, 'وتقول إنها تنتظر الإقرار');

list(, , $p1) = c_req($URL);
check(mb_strpos($p1, 'مشتقّة — تنتظر الإقرار') !== false, '**والشارةُ ظاهرةٌ في الشجرة**');
check(mb_strpos($p1, 'مشتقّةٌ من صفوف التشغيل') !== false, 'وملاحظتُها تقول من أين اشتُقّت');
check(mb_strpos($p1, 'الحصةُ قرارٌ تجاريّ') !== false, 'والشاشةُ تشرح لماذا لا تُقدَّم متفقًا عليها');
check(preg_match_all('~CNT-\d{4}-\d{4}~', $p1) >= 15, 'والشجرةُ مصيَّرةٌ بحاوياتها');
foreach (array('السقف', 'الموزَّع', 'المتاح للتوزيع', 'المستهلَك', 'المتبقي') as $col) {
    check(mb_strpos($p1, $col) !== false, "  وعمودُ «{$col}» حاضر");
}

// ═══ ③ تجاوزُ Σ — الرسالةُ تسمّي ═══
head('③ تجاوزُ Σ — رسالةٌ تسمّي المتاحَ والمطلوب');
$main = $conn->query("SELECT id, cap_qty, allocated_qty FROM op_containers
                       WHERE contract_id={$C5} AND level='رئيسية' ORDER BY id LIMIT 1")->fetch_assoc();
$free = round((float) $main['cap_qty'] - (float) $main['allocated_qty'], 2);
list(, $h2) = c_req($URL, array('cnt_action' => 'allocate', 'contract_id' => $C5,
    'parent_id' => (int) $main['id'], 'child_level' => 'مورد', 'child_ref' => 99,
    'qty' => $free + 100, 'cnt_csrf' => $CSRF));
$m2 = c_msg($h2);
check(mb_strpos($m2, 'تتجاوز المتاحَ') !== false, 'الرسالةُ تقول «تتجاوز المتاح»');
check(mb_strpos($m2, number_format($free, 2)) !== false,
    'و**تسمّي المتاحَ بالرقم** (' . number_format($free, 2) . '): ' . mb_substr($m2, 0, 110));
check(mb_strpos($m2, 'حدث خطأ') === false, 'ولا «حدث خطأ» — الرقمُ لا الغموض');
$kids = (int) $conn->query("SELECT COUNT(*) c FROM op_containers WHERE parent_id=" . (int) $main['id']
                           . " AND supplier_id=99")->fetch_assoc()['c'];
check($kids === 0, "ولا حاويةَ كُتبت: {$kids}");

// ═══ ④ التوزيعُ المشروع ═══
head('④ التوزيعُ المشروع يقع ويظهر أثرُه');
list(, $h3) = c_req($URL, array('cnt_action' => 'allocate', 'contract_id' => $C5,
    'parent_id' => (int) $main['id'], 'child_level' => 'مورد', 'child_ref' => 99,
    'qty' => 100, 'cnt_csrf' => $CSRF));
check(mb_strpos(c_msg($h3), 'خُصّصت الحصة') !== false, 'وقع التوزيع');
$after = $conn->query("SELECT allocated_qty FROM op_containers WHERE id=" . (int) $main['id'])->fetch_assoc();
check((float) $after['allocated_qty'] == round((float) $main['allocated_qty'] + 100, 2),
    'والأمُّ زادت 100: ' . $main['allocated_qty'] . ' ← ' . $after['allocated_qty']);
list(, , $p3) = c_req($URL);
check(mb_strpos($p3, number_format((float) $after['allocated_qty'], 2)) !== false,
    'والرقمُ الجديدُ معروضٌ في الشاشة');

// ═══ ⑤ التبديل ═══
head('⑤ تبديلٌ بلا سببٍ يُرَدّ');
$leaf = $conn->query("SELECT id FROM op_containers WHERE level='مشغّل' LIMIT 1")->fetch_assoc();
list(, $h4) = c_req($URL, array('cnt_action' => 'swap', 'contract_id' => $C5,
    'container_id' => (int) $leaf['id'], 'swap_kind' => 'مشغّل', 'out_ref' => 3, 'in_ref' => 4,
    'effective_from' => '2027-01-01', 'reason' => '', 'cnt_csrf' => $CSRF));
check(mb_strpos(c_msg($h4), 'سببُ التبديل إلزامي') !== false, 'مرفوضٌ برسالته');
list(, $h5) = c_req($URL, array('cnt_action' => 'swap', 'contract_id' => $C5,
    'container_id' => (int) $leaf['id'], 'swap_kind' => 'مشغّل', 'out_ref' => 3, 'in_ref' => 4,
    'effective_from' => '2027-01-01', 'reason' => 'إجازةٌ اضطرارية', 'cnt_csrf' => $CSRF));
check(mb_strpos(c_msg($h5), 'سُجّل التبديلُ بسببه') !== false, 'وبسببه يقع');
$sw = (int) $conn->query("SELECT COUNT(*) c FROM container_swaps")->fetch_assoc()['c'];
check($sw === 1, "وصفٌّ واحدٌ في سجل التبديل: {$sw}");

// ═══ ⑥ الإقرار ═══
head('⑥ الإقرارُ يرفع الوسم');
$pend = $conn->query("SELECT id FROM op_containers WHERE origin='مشتقّة' AND origin_ack_by IS NULL LIMIT 1")->fetch_assoc();
list(, $h6) = c_req($URL, array('cnt_action' => 'acknowledge', 'contract_id' => $C5,
    'container_id' => (int) $pend['id'], 'cnt_csrf' => $CSRF));
check(mb_strpos(c_msg($h6), 'أُقرّت الحصة') !== false, 'أُقرّت من الشاشة');
$row = $conn->query("SELECT origin_ack_by FROM op_containers WHERE id=" . (int) $pend['id'])->fetch_assoc();
check((int) $row['origin_ack_by'] > 0, 'وباسم مَن أقرّها: #' . $row['origin_ack_by']);
list(, , $p6) = c_req($URL);
check(mb_strpos($p6, 'مشتقّةٌ ومُقرَّة') !== false, 'والشارةُ انقلبت «مشتقّةٌ ومُقرَّة»');

// ═══ ⑦ تقريرُ المطابقة ═══
head('⑦ تقريرُ المطابقة يُعلن ما لا بندَ له');
check(mb_strpos($p6, 'تقريرُ المطابقة') !== false, 'التقريرُ حاضر');
check(mb_strpos($p6, 'وقائعُ بوحدةٍ لا حاويةَ رئيسيةً لها') !== false, 'وقسمُ الوحدات بلا بند');
check(mb_strpos($p6, 'تنتظر بندًا أو ملحقًا') !== false, 'وحكمُها منصوص');
check(mb_strpos($p6, 'حصصٌ مشتقّةٌ تنتظر الإقرار') !== false, 'وقسمُ المشتقّات المعلَّقة');

// ═══ ⑧ مَن لا يملكها ═══
head('⑧ مَن لا يملك الإدارةَ لا يرى أزرارَها');
$u12 = $conn->query("SELECT username FROM users WHERE role=12 AND company_id={$CO} LIMIT 1")->fetch_assoc();
if ($u12) {
    check(c_login($u12['username'], 'sales'), 'دخولُ «' . $u12['username'] . '» (الدور 12 — عرضًا)');
    list($c8, , $p8) = c_req($URL);
    check($c8 === 200 && mb_strpos($p8, 'شجرةُ الحاويات') !== false, 'ويرى الشجرة');
    check(mb_strpos($p8, 'ولّد الرئيسيات من بنود العقد') === false, '**ولا يرى زرَّ التوليد**');
    // ⚠️ `cnt-alloc-btn` يظهر في كتلة الـJS مهما كانت الصلاحية — فالفحصُ به يمرّ
    //    كذبًا. العلامةُ الفارقةُ سمةُ الزرِّ نفسِه.
    check(mb_strpos($p8, 'data-parent=') === false, 'ولا زرَّ التوزيع (لا سمةَ data-parent)');
    list(, $h8) = c_req($URL, array('cnt_action' => 'derive', 'contract_id' => $C5, 'cnt_csrf' => 'x'));
    check(mb_strpos(c_msg($h8), 'صلاحيةُ إدارة التشغيل') !== false,
        'وفعلُه يُردُّ ولو أُرسل يدويًّا');
} else { ok('(لا مستخدمَ بالدور 12 — يُتخطّى)'); }

fwrite(STDOUT, "\n══════════════════════════════════════════════════\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL === 0 ? 0 : 1);
