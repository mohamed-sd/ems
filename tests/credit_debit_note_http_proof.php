<?php
/**
 * tests/credit_debit_note_http_proof.php — M-02
 * ═══════════════════════════════════════════════════════════════════════════
 * برهانُ HTTP للإشعار الدائن/المدين من **الشاشة الحقيقية** بدورَين:
 *   ① المبيعاتُ (12) ترى لوحَ الإشعارات على مستخلصٍ مفوترٍ وتنشئ إشعارًا.
 *   ② السببُ والمستندُ إلزامان من الشاشة — لا من الخدمة وحدَها.
 *   ③ **مَن أنشأ لا يعتمد**: المبيعاتُ لا ترى زرَّ الإجازة ولا يمرّ فعلُها.
 *   ④ الماليةُ (19) تُجيز — والذمّةُ تتحرك، **والفاتورةُ الأصليةُ كما صدرت**.
 *   ⑤ الصافي المشتقُّ معروضٌ في الشاشة بمعادلته.
 *   ⑥ ضغطتان متطابقتان لا تفتحان ذمّتين (عطالةُ الشاشة).
 *
 * يبذر مستخلصَه وذمّتَه ويكنس الكلَّ.
 * التشغيل: php tests/credit_debit_note_http_proof.php  (يتطلب Apache حيًّا)
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

$JARS = array('sales' => sys_get_temp_dir() . '/ems_cdn_sales.txt',
              'fin'   => sys_get_temp_dir() . '/ems_cdn_fin.txt');
$CURJ = null;
function n_req($url, $post = null) {
    global $CURJ;
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $CURJ, CURLOPT_COOKIEFILE => $CURJ,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 40));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true);
                          curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $raw = curl_exec($ch);
    $hs  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $c   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array($c, substr($raw, 0, $hs), substr($raw, $hs));
}
function n_login($who, $user) {
    global $JARS, $CURJ, $BASE;
    $CURJ = $JARS[$who]; @unlink($CURJ);
    list(, , $b) = n_req($BASE . '/login.php');
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
    list($c) = n_req($BASE . '/login.php', array('username' => $user, 'password' => '12345678',
        'csrf_token' => $m[1] ?? ''));
    return ($c === 200 || $c === 302);
}
function n_csrf($body) {
    return preg_match('~name="clm_csrf"\s+value="([^"]+)"~', $body, $m) ? $m[1] : '';
}
/* ◆ **رمزان لا رمز**: الشاشةُ تحقن رمزَها `clm_csrf` **والرمزَ المركزيَّ**
     `csrf_token`، والحارسُ المركزيُّ (ADR-05) يقيس المركزيَّ حصرًا — فطلبٌ برمزِ
     الشاشةِ وحدَه يُردُّ **403 بلا وميضٍ ولا جسم**، فيُقرأ صمتُه فشلًا في الفعل. */
function n_gcsrf($body) {
    return preg_match('~name="csrf_token"\s+value="([^"]+)"~', $body, $m) ? $m[1] : '';
}
/* ══ **الرسالةُ وميضُ جلسةٍ لا مُعامَلُ عنوان.** `Contracts/*` توجّه عبر
     `ems_gov_flash_redirect` الذي يخزّن النصَّ في `$_SESSION['ems_flash_gov']`
     ويوجّه **بعنوانٍ مجرَّد** — فقراءةُ `?msg=` تعود فارغةً **دائمًا** ويُقرأ
     صمتُها فشلًا. ويبقى المُعامَلُ مقروءًا أوّلًا لأن شاشاتٍ أخرى (المشتريات ·
     الفتراتُ الماليةُ · تسوياتُ الموردين) تضع الرسالةَ فيه فعلًا. */
function n_msg($h) {
    require_once __DIR__ . '/_http_flash.php';
    global $BASE;
    return ems_flash_or_msg($h, $BASE . '/Contracts', function ($u) {
        list(, , $b) = n_req($u);
        return $b;
    });
}

$conn = $GLOBALS['conn'];
$CO = 4;
$MARK = 'CDNH' . getmypid();

/* ══ **بعائلةِ الوسمِ وبالفترةِ المحجوزةِ معًا — وإلا شوطٌ واحدٌ يقتل كلَّ ما بعده** ══
   الكنسُ بـ`{$MARK}%` (وسمُ pid هذا الشوطِ) يجعل كلَّ شوطٍ أعمى عمّا تركه سابقُه.
   والمقيسُ: صفٌّ باقٍ `CDNH19528-C1` يحجز **عقدَ 5 وفترةَ 2095-01**، و
   `uq_claim_period (company, contract, period_from, period_to)` يردُّ كلَّ إدراجٍ
   بعده **صمتًا** (`insert_id = 0`) فتتساقط أربعةَ عشرَ فحصًا على منتجٍ سليم —
   ولذلك كان المسبارُ أخضرَ منفردًا وأحمرَ في المسحِ الشامل.
   ⇒ يُكنَس بالعائلةِ `CDNH` **وبالفترةِ المحجوزةِ لهذا المسبارِ حصرًا** (2095 على
     العقد 5) — فيشفي نفسَه بدل أن يحتاج يدًا. والفاتورةُ الضريبيةُ قبلَ
     المستخلصِ (`fk_tax_invoice_claim` بـRESTRICT). */
$FAMILY = 'CDNH';
$cleanup = function () use ($conn, $MARK, $FAMILY) {
    if ($FAMILY === '') { return; }
    $sel = "SELECT id FROM (SELECT id FROM claims
              WHERE claim_no LIKE '{$FAMILY}%'
                 OR (contract_id = 5 AND period_from LIKE '2095-%')) x";
    $conn->query("DELETE FROM credit_debit_notes WHERE claim_id IN ({$sel})");
    $conn->query("DELETE FROM claim_lines WHERE claim_id IN ({$sel})");
    $conn->query("DELETE FROM tax_invoices WHERE claim_id IN ({$sel})");
    $conn->query("DELETE FROM tax_invoices WHERE serial_no LIKE 'INV-{$FAMILY}%'");
    $conn->query("UPDATE claims SET receivable_id = NULL
                   WHERE claim_no LIKE '{$FAMILY}%'
                      OR (contract_id = 5 AND period_from LIKE '2095-%')");
    $conn->query("DELETE FROM fin_receivables WHERE doc_ref LIKE 'INV-{$FAMILY}%'");
    $conn->query("DELETE FROM claims WHERE claim_no LIKE '{$FAMILY}%'
                     OR (contract_id = 5 AND period_from LIKE '2095-%')");
    $orphan = "SELECT id FROM (SELECT id, entity_type, entity_id FROM ems_business_events) be
                WHERE be.entity_type='credit_debit_note'
                  AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM credit_debit_notes) n
                                   WHERE n.id = be.entity_id)";
    $conn->query("DELETE FROM fin_financial_events WHERE root_event_id IN ({$orphan})");
    $conn->query("DELETE FROM ems_business_events WHERE id IN ({$orphan})");
};
$cleanup();
register_shutdown_function($cleanup);

// ── البذر ────────────────────────────────────────────────────────────────
$cno = $MARK . '-C1'; $inv = 'INV-' . $MARK . '-C1';
$conn->query("INSERT INTO claims (company_id, claim_no, contract_id, client_id, project_id,
                period_from, period_to, currency, gross_amount, retention_amount, net_amount,
                tax_amount, invoice_no, invoice_date, state, submitted_by, version)
              VALUES ({$CO}, '{$cno}', 5, 1, 1, '2095-01-01','2095-01-31','USD',
                      2000.00, 0, 2000.00, 0, '{$inv}', '2095-02-01', 'invoiced', 999, 2)");
$CLAIM = (int) $conn->insert_id;
$conn->query("INSERT INTO claim_lines (company_id, claim_id, source_kind, source_ref,
                work_date, qty, unit_price, amount, dispute_flag)
              VALUES ({$CO}, {$CLAIM}, 'timesheet', 999002, '2095-01-05', 20, 100, 2000.00, 0)");
/* INJ-0036: `chk_recv_source_doc` يمنع ذمّةً بلا مستندٍ يقابلها — والبذرُ كان
   يفتحها عاريةً فيُردُّ الإدراجُ صامتًا (`insert_id = 0`) ثم يقرأ الفحصُ صفًّا لا
   وجودَ له. فتُبذَر فاتورةٌ ضريبيةٌ صادرةٌ أوّلًا كما يقع في الواقع. */
require_once __DIR__ . '/_source_doc_seed.php';
$TI = seed_source_invoice($conn, $CO, $inv, 1, 2000.00, 'USD', $CLAIM);
$conn->query("INSERT INTO fin_receivables (company_id, customer_entity_id, doc_type, doc_ref,
                source_doc_id, project_id, amount, collected, state)
              VALUES ({$CO}, 1, 'invoice', '{$inv}', {$TI}, 1, 2000.00, 0, 'open')");
$RECV = (int) $conn->insert_id;
$conn->query("UPDATE claims SET receivable_id={$RECV} WHERE id={$CLAIM}");

fwrite(STDOUT, "\n══ M-02 — الإشعارُ من الشاشة ══\n");
info("المستخلص {$cno} #{$CLAIM} · فاتورة {$inv} · ذمّة 2000.00");

$claimBefore = $conn->query("SELECT * FROM claims WHERE id={$CLAIM}")->fetch_assoc();

// ═══ ① المبيعاتُ ترى وتنشئ ═══
head('① المبيعاتُ (12) — اللوحُ والإنشاء');
$u12 = $conn->query("SELECT username FROM users WHERE role=12 AND company_id={$CO} LIMIT 1")->fetch_assoc();
$u19 = $conn->query("SELECT username FROM users WHERE role=19 AND company_id={$CO} LIMIT 1")->fetch_assoc();
if (!$u12 || !$u19) { bad('لا مستخدمَ للدورين 12/19'); }
else {
    check(n_login('sales', $u12['username']), 'دخولُ «' . $u12['username'] . '»');
    list($c1, , $p1) = n_req($BASE . '/Contracts/claims.php?open=' . $CLAIM);
    check($c1 === 200, 'شاشةُ المستخلصات مفتوحةً على المستخلص (200)');
    check(mb_strpos($p1, 'الإشعاراتُ الدائنة/المدينة') !== false, 'ولوحُ الإشعارات ظاهر');
    check(mb_strpos($p1, 'الفاتورةُ الصادرة لا تُعدَّل') !== false, 'وقاعدتُه مكتوبةٌ للمستخدم');
    check(mb_strpos($p1, 'أنشئ الإشعار') !== false, 'ونموذجُ الإنشاء متاحٌ للمبيعات');
    $csrf = n_csrf($p1);

    // ═══ ② الإلزامات من الشاشة ═══
    head('② السببُ والمستندُ إلزامان من الشاشة');
    list(, $h2) = n_req($BASE . '/Contracts/claims.php', array(
        'action' => 'note_create', 'claim_id' => $CLAIM, 'note_kind' => 'credit',
        'amount' => '300', 'reason' => '', 'doc_ref' => 'D-1', 'clm_csrf' => $csrf, 'csrf_token' => n_gcsrf($p1)));
    check(mb_strpos(n_msg($h2), 'سببُ الإشعار إلزامي') !== false,
        'بلا سبب: يُردّ برسالته — ' . mb_substr(n_msg($h2), 0, 60));

    list(, $h3) = n_req($BASE . '/Contracts/claims.php', array(
        'action' => 'note_create', 'claim_id' => $CLAIM, 'note_kind' => 'credit',
        'amount' => '300', 'reason' => 'مبالغةٌ في الفوترة', 'doc_ref' => '', 'clm_csrf' => $csrf, 'csrf_token' => n_gcsrf($p1)));
    check(mb_strpos(n_msg($h3), 'المستند') !== false, 'وبلا مستند: يُردّ كذلك');

    // الإنشاءُ الصحيح
    $post = array('action' => 'note_create', 'claim_id' => $CLAIM, 'note_kind' => 'credit',
                  'amount' => '300', 'reason' => 'مبالغةٌ في الفوترة', 'doc_ref' => 'DOC-77',
                  'clm_csrf' => $csrf, 'csrf_token' => n_gcsrf($p1));
    list(, $h4) = n_req($BASE . '/Contracts/claims.php', $post);
    check(mb_strpos(n_msg($h4), 'أُنشئ الإشعار') !== false, 'وبهما معًا: أُنشئ — ' . mb_substr(n_msg($h4), 0, 60));

    // ═══ ⑥ العطالة من الشاشة ═══
    head('⑥ ضغطتان متطابقتان لا تفتحان ذمّتين');
    list(, $h5) = n_req($BASE . '/Contracts/claims.php', $post);
    check(mb_strpos(n_msg($h5), 'قائمٌ سلفًا') !== false, 'الضغطةُ الثانية تُرجع القائمَ — ' . mb_substr(n_msg($h5), 0, 60));
    $cnt = (int) $conn->query("SELECT COUNT(*) c FROM credit_debit_notes WHERE claim_id={$CLAIM}")
                      ->fetch_assoc()['c'];
    check($cnt === 1, "وصفٌّ واحدٌ لا اثنان: {$cnt}");

    $NOTE = (int) $conn->query("SELECT id FROM credit_debit_notes WHERE claim_id={$CLAIM} LIMIT 1")
                       ->fetch_assoc()['id'];
    list(, $h6) = n_req($BASE . '/Contracts/claims.php', array(
        'action' => 'note_submit', 'note_id' => $NOTE, 'clm_csrf' => $csrf, 'csrf_token' => n_gcsrf($p1)));
    check(mb_strpos(n_msg($h6), 'رُفع الإشعارُ للمالية') !== false, 'ورُفع للمالية');

    // ═══ ③ مَن أنشأ لا يعتمد ═══
    head('③ مَن أنشأ لا يعتمد');
    list($c7, , $p7) = n_req($BASE . '/Contracts/claims.php?open=' . $CLAIM);
    check(mb_strpos($p7, 'note_approve') === false, 'المبيعاتُ لا ترى زرَّ الإجازة في الشاشة');
    list(, $h7) = n_req($BASE . '/Contracts/claims.php', array(
        'action' => 'note_approve', 'note_id' => $NOTE, 'clm_csrf' => n_csrf($p7), 'csrf_token' => n_gcsrf($p7)));
    check(mb_strpos(n_msg($h7), 'صلاحيةُ المالية') !== false,
        'وفعلُها يُردّ ولو أُرسل يدويًّا — ' . mb_substr(n_msg($h7), 0, 60));
    $stNow = $conn->query("SELECT state FROM credit_debit_notes WHERE id={$NOTE}")->fetch_assoc();
    check((string) $stNow['state'] === 'review', 'والإشعارُ ما زال «قيد المراجعة»');

    // ═══ ④ الماليةُ تُجيز ═══
    head('④ الماليةُ (19) تُجيز — والذمّةُ تتحرك والأصلُ لا يُمسّ');
    check(n_login('fin', $u19['username']), 'دخولُ «' . $u19['username'] . '»');
    list($c8, , $p8) = n_req($BASE . '/Contracts/claims.php?open=' . $CLAIM);
    check($c8 === 200 && mb_strpos($p8, 'note_approve') !== false, 'والماليةُ ترى زرَّ الإجازة');
    list(, $h8) = n_req($BASE . '/Contracts/claims.php', array(
        'action' => 'note_approve', 'note_id' => $NOTE, 'clm_csrf' => n_csrf($p8), 'csrf_token' => n_gcsrf($p8)));
    check(mb_strpos(n_msg($h8), 'تحرّكت ذمّةُ العميل') !== false,
        'وأُجيز — ' . mb_substr(n_msg($h8), 0, 80));

    $rc = $conn->query("SELECT amount, outstanding FROM fin_receivables WHERE id={$RECV}")->fetch_assoc();
    check((float) $rc['amount'] == 1700.00, 'الذمّةُ 2000 ← ' . $rc['amount'] . ' (دائن 300)');

    $claimAfter = $conn->query("SELECT * FROM claims WHERE id={$CLAIM}")->fetch_assoc();
    $diff = array();
    foreach ($claimBefore as $k => $v) { if ((string) $claimAfter[$k] !== (string) $v) { $diff[] = $k; } }
    check(empty($diff), 'والفاتورةُ الأصليةُ كما صدرت' . ($diff ? ' (تغيّر: ' . implode(',', $diff) . ')' : ''));

    // ═══ ⑤ الصافي المشتق ═══
    head('⑤ الصافي المشتقُّ في الشاشة');
    list(, , $p9) = n_req($BASE . '/Contracts/claims.php?open=' . $CLAIM);
    check(mb_strpos($p9, 'صافي المطالبة') !== false, 'المعادلةُ معروضة');
    check(mb_strpos($p9, '1,700.00') !== false, 'وبقيمتها الصحيحة 1,700.00');
    check(mb_strpos($p9, 'DOC-77') !== false, 'ومستندُ الإشعار ظاهرٌ للمستخدم');
}

fwrite(STDOUT, "\n══════════════════════════════════════════════════\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL === 0 ? 0 : 1);
