<?php
/**
 * tests/budget_cycle_http_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 * برهانُ HTTP لدورة الموازنة من الشاشة الحقيقية بحسابين:
 *   ① مديرُ الصيانة يرى موازنةَ قسمه **وحدها** ويرفعها
 *   ② المدير الماليُّ يراها في قائمته ويعيدها بسبب
 *   ③ الصيانةُ تقرأ السبب وترفعها ثانيةً بالرقم نفسه
 *   ④ المدير الماليُّ يُجيزها
 *   ⑤ ولا يستطيع الدورُ 17/18/21 إجازتَها
 * ثم يكنس ما بذر — لا يمسّ موازنتك القائمة.
 *
 * التشغيل: php tests/budget_cycle_http_proof.php   (يتطلب Apache حيًّا)
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

function bg_req($url, $jar, $post = null) {
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
function bg_login($user, $jar) {
    global $BASE;
    @unlink($jar);
    list($c, $h, $b) = bg_req($BASE . '/login.php', $jar);
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
    return bg_req($BASE . '/login.php', $jar, array('username' => $user, 'password' => '12345678',
        'csrf_token' => isset($m[1]) ? $m[1] : ''));
}
/* ══ **الرسالةُ وميضُ جلسةٍ لا مُعامَلُ عنوان.** `ems_gov_flash_redirect`
     (`includes/permissions_helper.php:139`) يخزّن النصَّ في
     `$_SESSION['ems_flash_gov']` ويوجّه **بعنوانٍ مجرَّد** — فقراءةُ `?msg=`
     تعود فارغةً **دائمًا** فيُقرأ صمتُها فشلًا، والمنتجُ نفَّذ الفعلَ وكتب
     رسالتَه حيث ينبغي. ويبقى المُعامَلُ مقروءًا **أوّلًا** لأن شاشاتٍ أخرى
     (المشتريات · الفتراتُ الماليةُ · تسوياتُ الموردين) تضعها فيه فعلًا. */
function bg_msg($h) {
    require_once __DIR__ . '/_http_flash.php';
    $__dir = isset($GLOBALS['__ems_last_url'])
        ? preg_replace('~/[^/]*(\?.*)?$~', '', (string) $GLOBALS['__ems_last_url'])
        : '';
    return ems_flash_or_msg($h, $__dir, function ($u) {
        $r = bg_req($u, $GLOBALS['__ems_last_jar'], null);
        return is_array($r) ? (string) $r[count($r) - 1] : (string) $r;
    });
}
/** رمزُ الحماية المركزي يُحقن في الصفحة — يُقرأ منها ويُرسل مع كل POST. */
function bg_token($jar) {
    global $BASE;
    list($c, $h, $b) = bg_req($BASE . '/Finance/budget_form_fin.php', $jar);
    return preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m) ? $m[1] : '';
}
/** POST على شاشة الموازنة برمزه. */
function bg_post($jar, array $fields) {
    global $BASE;
    $fields['csrf_token'] = bg_token($jar);
    return bg_req($BASE . '/Finance/budget_form_fin.php', $jar, $fields);
}

$env = array();
foreach (file(dirname(__DIR__) . '/.env') as $l) {
    if (preg_match('/^\s*([A-Z_]+)=(.*)$/', $l, $m)) { $env[$m[1]] = trim($m[2]); }
}
mysqli_report(MYSQLI_REPORT_OFF);
$db = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
if ($db->connect_errno) { fwrite(STDERR, "FATAL: db connect\n"); exit(1); }
$db->set_charset('utf8mb4');

$CO = 4; $MARK = 'BGH' . getmypid();
$MNT_NO = 'TST-BG-' . $MARK . '-M';
$HR_NO  = 'TST-BG-' . $MARK . '-H';
$db->query("INSERT INTO fin_budgets (company_id, budget_no, dept_module, period_type, fiscal_year,
            total_revenue, total_expense, state, created_by, created_at)
            VALUES ({$CO}, '{$MNT_NO}', 'maintenance', 'annual', 2091, 0, 500000, 'draft', 1, NOW())");
$MNT = $db->insert_id;
$db->query("INSERT INTO fin_budgets (company_id, budget_no, dept_module, period_type, fiscal_year,
            total_revenue, total_expense, state, created_by, created_at)
            VALUES ({$CO}, '{$HR_NO}', 'workforce', 'annual', 2091, 0, 900000, 'draft', 1, NOW())");
$HR = $db->insert_id;

$cleanup = function () use ($db, $MNT, $HR) {
    $db->query("DELETE FROM fin_budget_lines WHERE budget_id IN (" . intval($MNT) . "," . intval($HR) . ")");
    $db->query("DELETE FROM fin_budgets WHERE id IN (" . intval($MNT) . "," . intval($HR) . ")");
};
register_shutdown_function($cleanup);
fwrite(STDOUT, "  (بُذرت موازنتان لسنة 2091: الصيانة #{$MNT} · الموارد البشرية #{$HR})\n");

$st = function ($id) use ($db) {
    $r = $db->query("SELECT * FROM fin_budgets WHERE id=" . intval($id))->fetch_assoc();
    return $r ? $r : array();
};

// ═══ ① الصيانة ترى قسمَها وحده وترفع ══════════════════════════════════════
head('① مديرُ الصيانة: نطاقُه وحده ثم الرفع');

$jarM = $TMP . '/bgm_' . $MARK . '.txt';
bg_login('صيانة', $jarM);
list($code, $h, $page) = bg_req($BASE . '/Finance/budget_form_fin.php', $jarM);
check($code === 200, "شاشةُ الموازنات تُفتح بحساب الصيانة (HTTP {$code})");
check(strpos($page, $MNT_NO) !== false, '★ ويرى موازنةَ الصيانة');
check(strpos($page, $HR_NO) === false, '★★ ولا يرى موازنةَ الموارد البشرية (النطاقُ يعمل)');
check(strpos($page, 'الموارد البشرية') === false || strpos($page, 'value=\'workforce\'') === false,
    'ولا يجد قسمَ الموارد البشرية خيارًا في نموذج الإنشاء');

list($code, $h, $b) = bg_post($jarM,
    array('bg_action' => 'submit', 'id' => $MNT));
check($code === 302 && strpos(bg_msg($h), 'رُفعت') !== false, 'ويرفع موازنتَه للمالية');
check($st($MNT)['state'] === 'submitted', 'فتصير «مقدَّمة»');

// محاولةُ رفع موازنة قسمٍ آخر
list($code, $h, $b) = bg_post($jarM,
    array('bg_action' => 'submit', 'id' => $HR));
check(strpos(bg_msg($h), 'مديره') !== false, '★ ومحاولةُ رفع موازنة قسمٍ آخر تُرفض');
check($st($HR)['state'] === 'draft', 'وتبقى مسودةً كما هي');

// ومحاولةُ إجازة موازنته
list($code, $h, $b) = bg_post($jarM,
    array('bg_action' => 'approve', 'id' => $MNT));
// الرسالةُ تغيّرت باتّساع الباب («للإدارة المالية» بعد «لمدير الإدارة المالية
// وحده») — والتأكيدُ يقيس **الأثر** لا نصَّ الرسالة، فلا ينكسر بتحرير عبارة.
check($st($MNT)['state'] === 'submitted' && strpos(bg_msg($h), 'المالية') !== false,
    '★ ولا يُجيز موازنتَه (لا اعتمادَ ذات) — تبقى مرفوعة');

// ═══ ② المالية تُعيد بسبب ═════════════════════════════════════════════════
head('② المدير المالي: يراها ويعيدها بسبب');

$jarF = $TMP . '/bgf_' . $MARK . '.txt';
// المُجيزُ هو **مدير الإدارة المالية (دور 19)** — وحسابُ «مديرمالي» دورُه 17
// (مديرُ أقسام الإيرادات والخزينة والعام) فلا يُجيز موازنةَ الصيانة. گوتشا مقيسة.
bg_login('fin.deptmgr@equipation.sd', $jarF);
list($code, $h, $pageF) = bg_req($BASE . '/Finance/budget_form_fin.php', $jarF);
check($code === 200, 'شاشةُ الموازنات تُفتح بحساب المدير المالي');
check(strpos($pageF, $MNT_NO) !== false && strpos($pageF, $HR_NO) !== false,
    '★ ويرى موازناتِ كلِّ الأقسام (بوصفه المُجيز)');

$REASON = 'بند قطع الغيار مبالغٌ فيه — راجع متوسط الصرف';
list($code, $h, $b) = bg_post($jarF,
    array('bg_action' => 'return', 'id' => $MNT, 'reason' => $REASON));
check($code === 302 && strpos(bg_msg($h), 'أُعيدت') !== false, 'ويعيدها بسببها');
$row = $st($MNT);
check($row['state'] === 'returned', 'فتصير «معادة»');
check((string)$row['return_reason'] === $REASON, 'والسببُ محفوظٌ بحرفه');

list($code, $h, $b) = bg_post($jarF,
    array('bg_action' => 'return', 'id' => $MNT, 'reason' => ''));
check(strpos(bg_msg($h), 'سبب') !== false || $st($MNT)['state'] === 'returned',
    'وإعادةٌ بلا سببٍ مرفوضة');

// ═══ ③ الصيانة تقرأ السبب وترفع ثانيةً ════════════════════════════════════
head('③ الإدارة تقرأ السبب وترفع بالرقم نفسه');

list($code, $h, $page) = bg_req($BASE . '/Finance/budget_form_fin.php', $jarM);
check(strpos($page, 'أُعيدت لاستكمال') !== false, '★ سببُ الإعادة بارزٌ في شاشة الإدارة');
check(strpos($page, 'قطع الغيار') !== false, 'ونصُّه كما كتبته المالية');

list($code, $h, $b) = bg_post($jarM,
    array('bg_action' => 'submit', 'id' => $MNT));
check(strpos(bg_msg($h), 'رُفعت') !== false, 'وترفعها ثانيةً بعد الاستكمال');
check($st($MNT)['state'] === 'submitted' && (string)$st($MNT)['budget_no'] === $MNT_NO,
    'بالرقم نفسه لا برقمٍ جديد');

// ═══ ④ الإجازة ════════════════════════════════════════════════════════════
head('④ الإجازة');

list($code, $h, $b) = bg_post($jarF,
    array('bg_action' => 'approve', 'id' => $MNT));
check($code === 302 && strpos(bg_msg($h), 'أُجيزت') !== false, 'المدير الماليُّ يُجيزها');
$row = $st($MNT);
check($row['state'] === 'approved', 'فتصير «معتمدة» — مرجعًا حاكمًا');
check(!empty($row['approved_at']) && !empty($row['submitted_at']),
    'وسجلُّ الرفع والإجازة كاملٌ عليها');

// ═══ ⑤ الإدارةُ المالية كلُّها تُجيز — زرًّا ظاهرًا وفعلًا ماضيًا ═════════════
// (قرار المالك 2026-07-28: «أظهِر أزرارَ الإجراءات ما دمتُ مديرًا ماليًّا أو
//  أحدَ معاونيه» — بعد أن كان الزرُّ للدور 19 وحده.)
head('⑤ الإدارةُ المالية: الزرُّ يظهر والفعلُ يمضي');

// حساباتٌ حقيقيةٌ قائمة — لا أسماءٌ مخترعةٌ تُسقط الدخول فيمرّ التأكيدُ كذبًا
foreach (array('مديرمالي' => 17, 'fin.treasury@equipation.sd' => 21,
               'fin.accountant@equipation.sd' => 18) as $u => $rid) {
    $db->query("UPDATE fin_budgets SET state='submitted', approved_by=NULL, approved_at=NULL
                WHERE id=" . intval($HR));
    $jarX = $TMP . '/bgx_' . $MARK . '_' . $rid . '.txt';
    list($c, $hh, $bb) = bg_login($u, $jarX);

    // ① الزرُّ نفسُه في صفحة الحساب — لا في الكود وحده
    list($c2, $h2, $page) = bg_req($BASE . '/Finance/budget_form_fin.php', $jarX);
    $seesBtn = strpos($page, "value='approve'") !== false
            || strpos($page, 'value="approve"') !== false;
    check($seesBtn, "الحساب «{$u}» (دور {$rid}) يرى زرَّ الإجازة في الشاشة");

    // ② والفعلُ يمضي — فلا زرَّ يزيِّن بابًا مقفلًا
    list($code, $h, $b) = bg_post($jarX, array('bg_action' => 'approve', 'id' => $HR));
    check($st($HR)['state'] === 'approved', "ويُجيز به فعلًا (دور {$rid})");
}

// ★ وفصلُ اليدين باقٍ: المديرُ الماليُّ لا يُجيز موازنةَ قسمه هو
$db->query("INSERT INTO fin_budgets (company_id, budget_no, dept_module, period_type, fiscal_year,
            total_revenue, total_expense, state, created_by, created_at)
            VALUES ({$CO}, 'TST-BG-{$MARK}-R', 'revenue', 'annual', 2091, 700000, 0, 'submitted', 1, NOW())");
$REV = $db->insert_id;
$jarC = $TMP . '/bgc_' . $MARK . '.txt';
bg_login('مديرمالي', $jarC);
list($c, $h, $pageC) = bg_req($BASE . '/Finance/budget_form_fin.php', $jarC);
check(strpos($pageC, 'قسمُك — يُجيزه مديرُ الإدارة المالية') !== false,
    '★ وعلى موازنة قسمه يُستبدل الزرُّ بسببِ منعه — لا زرَّ ميّتًا');
bg_post($jarC, array('bg_action' => 'approve', 'id' => $REV));
$revRow = $db->query("SELECT state FROM fin_budgets WHERE id=" . intval($REV))->fetch_assoc();
check($revRow && $revRow['state'] === 'submitted',
    '★★ والمنعُ حقيقيٌّ لا تجميليّ — تبقى مرفوعةً ولو أُرسل الطلبُ يدويًّا');
$db->query("DELETE FROM fin_budgets WHERE id=" . intval($REV));

$cleanup();
check((int) $db->query("SELECT COUNT(*) FROM fin_budgets WHERE budget_no LIKE 'TST-BG-%'")->fetch_row()[0] === 0,
    'كُنس ما بُذر — لا أثرَ للاختبار');

fwrite(STDOUT, "\n" . str_repeat('═', 46) . "\nالنتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL > 0 ? 1 : 0);
