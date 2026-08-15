<?php
/**
 * tests/workspace_counters_tenant_test.php — رقمٌ واحدٌ في موضعين · ولا كيانَ مفترَض
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0407 · INJ-0581 · INJ-0579 · INJ-0408 · INJ-0425 · INJ-0203
 *
 * · 0407: «حسابان في الكيانِ نفسِه يريان **رقمين مختلفين** مطابقين لمجموعِ
 *   بلاطاتِ موافقاتي + مهامي لكلٍّ منهما».
 * · 0581: «رقمُ شارةِ طلباتي = عددُ الصفوفِ في الشاشةِ التي تفتحها البلاطةُ
 *   **بالضبط**، لكلِّ مستخدم».
 * · 0579: «`COUNT` سجلِّ الاطّلاعِ يساوي عددَ صفوفِ **الكيانِ الحالي فقط**».
 * · 0408/0425: «بحسابِ سوبر بلا كيان: **تُطلب اختيارُ الكيانِ صراحةً** — ولا
 *   تُعرض بياناتُ الكيان 4 ضمنًا؛ ولا يوجد في الملف رقمُ شركةٍ صلب».
 * · 0203: «حسابٌ بلا شركةٍ صالحةٍ **لا يستطيع فتحَ الشاشة** ولا إنشاءَ عملية».
 *
 * ◆ **والشاهدُ على HTML الحيِّ**: «الرقمُ على البلاطة» حكمٌ على ما يراه القارئُ.
 *   فتُقرأ الشارةُ والبلاطاتُ من الصفحةِ نفسِها بحسابين مختلفين.
 * ◆ والاختبارُ السلبيُّ للعزل: يُزرع صفٌّ في كيانٍ آخرَ فيجب ألا يتحرّك العدُّ.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
ob_start(); require_once $ROOT . '/config.php'; ob_end_clean();
while (ob_get_level() > 0) { ob_end_clean(); }
require_once $ROOT . '/includes/my_workspace_counts.php';

$conn = $GLOBALS['conn'];
$CO   = 4;
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$say('══ رقمٌ واحدٌ في موضعين · ولا كيانَ مفترَض');

/* ── ① لا رقمَ شركةٍ صلبٌ في أيِّ سطح (0408 · 0425 · 0203) ──────────────── */
$say("\n── ① «ولا يوجد في الملف رقمُ شركةٍ صلب»");
$o = array(); $rc = 1;
@exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($ROOT . '/tools/fix_hardcoded_tenant.php') . ' 2>&1', $o, $rc);
$txt = implode("\n", $o);
$ok($rc === 0, 'الفاحصُ العامُّ يمرُّ — صفرُ رقمِ شركةٍ صلبٍ في الأسطح (خروج=' . $rc . ')',
    mb_substr(preg_replace('~\s+~', ' ', $txt), 0, 200));
foreach (array('main/my_workspace.php', 'Portal/approvals_inbox.php',
               'Tickets/ticket_contextual_open.php', 'Financing/financing_operation_new.php') as $f) {
    $s = (string) @file_get_contents($ROOT . '/' . $f);
    $ok(strpos($s, 'ems_scope_company') !== false || strpos($s, 'ems_require_company') !== false,
        $f . ' · النطاقُ من السياقِ لا من رقم');
}
$fin = (string) @file_get_contents($ROOT . '/Financing/financing_operation_new.php');
$ok(strpos($fin, 'ems_require_company') !== false,
    'INJ-0203 · شاشةُ الإنشاءِ **تُغلق مغلقًا** بلا كيان — لا تفترضه');
$ws = (string) @file_get_contents($ROOT . '/main/my_workspace.php');
$ok(strpos($ws, 'ems_company_picker') !== false,
    'INJ-0425 · «السوبر بلا كيانٍ يرى **منتقيَ كيانٍ** لا أرقامًا»');

/* ── ② عزلُ استعلامَي وحدةِ المخاطر (0579) ──────────────────────────────── */
$say("\n── ② عزلُ الكيانِ في استعلامَي وحدةِ المخاطر");
$d = (string) @file_get_contents($ROOT . '/Risk/dept_risk_space.php');
$ok(preg_match('~FROM org_units\s+WHERE unit_id = \{\$unit\} AND company_id~u', $d)
    || preg_match('~org_units[\s\S]{0,120}company_id~u', $d),
    'اسمُ الوحدةِ يُقرأ بشرطِ الكيان');
$g = (string) @file_get_contents($ROOT . '/Risk/gov_dept_rsk.php');
$ok(preg_match('~sensitive_read_log\s*\n?\s*WHERE company_id~u', $g),
    '«`COUNT` سجلِّ الاطّلاعِ يساوي صفوفَ الكيانِ الحالي فقط»');
/* والفرقُ حقيقيٌّ لا نظريّ: يُقاس العدُّ بالشرطِ وبدونه */
$a = $conn->query('SELECT COUNT(*) FROM sensitive_read_log');
$b = $conn->query("SELECT COUNT(*) FROM sensitive_read_log WHERE company_id = {$CO}");
$all  = ($a && ($x = $a->fetch_row())) ? (int) $x[0] : -1;
$mine = ($b && ($x = $b->fetch_row())) ? (int) $x[0] : -1;
$ok($mine >= 0 && $all >= $mine, 'ومقيسٌ حيًّا: الكلُّ ' . $all . ' · كيانُنا ' . $mine);

/* ── ③ عدَّادُ «طلباتي» = صفوفُ شاشتِه بالضبط (0581) ────────────────────── */
$say("\n── ③ «رقمُ الشارةِ = عددُ صفوفِ الشاشةِ بالضبط، لكلِّ مستخدم»");
/* ثلاثةُ حساباتٍ حقيقيةٍ في الكيان — والحكمُ لكلٍّ منها */
$users = array();
$r = $conn->query("SELECT id, username, role FROM users WHERE company_id={$CO} ORDER BY id LIMIT 12");
while ($r && ($x = $r->fetch_assoc())) { $users[] = $x; }
$ok(count($users) >= 2, 'حساباتٌ حقيقيةٌ للقياس: ' . count($users));

$mismatch = array(); $checked = 0;
foreach ($users as $u) {
    $uid = (int) $u['id'];
    $cnt = ems_my_requests_count($conn, $CO, $uid);
    /* صفوفُ الشاشةِ نفسِها: استعلامُ `Portal/my_requests.php` مقصورًا على «طلباتي» */
    $sr = $conn->query("SELECT COUNT(*) n FROM (
            SELECT rq.id FROM requests rq
              JOIN request_types rt ON rt.code = rq.request_type_code
             WHERE rq.company_id = {$CO}
               AND (rq.requester_user_id = {$uid} OR rq.current_holder_user_id = {$uid})
               AND rq.requester_user_id = {$uid}
             ORDER BY rq.id DESC LIMIT 300) x");
    $screen = ($sr && ($x = $sr->fetch_row())) ? (int) $x[0] : -1;
    $checked++;
    if ($cnt !== $screen) { $mismatch[] = $u['username'] . ': عدّاد=' . $cnt . ' شاشة=' . $screen; }
}
$ok(!$mismatch, 'العدَّادُ = صفوفُ الشاشةِ في ' . $checked . ' حسابًا', implode(' · ', $mismatch));
/* وما كان يُجمع خطأً صار له بلاطتُه وشاشتُه */
$ok(strpos($ws, 'ems_my_fin_requests_count') !== false && strpos($ws, 'FinRequests/my_requests.php') !== false,
    'والماليةُ بلاطتُها وشاشتُها — لا تُجمع في رقمٍ لا شاشةَ له ولا تُخفى');

/* ── ④ شارةُ الشريطِ = موافقاتي + مهامي **لهذا المستخدم** (0407) ────────── */
$say("\n── ④ الشارةُ شخصيةٌ لا شركة");
$tb = (string) @file_get_contents($ROOT . '/includes/topbar.php');
$ok(strpos($tb, 'ems_workspace_badge') !== false,
    'الشارةُ من التعريفِ الواحدِ لا من خدمةِ صندوقِ الكيان');
$ok(strpos($tb, 'ApprovalsInboxService::inbox($GLOBALS[\'conn\'], intval($_SESSION[\'user\'][\'company_id\']') === false,
    'ولم تعد تُنادى بوسيطِ الكيانِ وحدَه');
$ok(strpos($tb, "'k' => \$ems_tb_wsKey") !== false,
    'والخبيئةُ بمفتاحِ المستخدمِ والكيان — فلا يُسرَّب رقمُ حسابٍ إلى آخر');

/* والأرقامُ تختلف فعلًا بين حسابين، وكلٌّ يساوي مجموعَ بلاطتيه */
$vals = array();
foreach (array_slice($users, 0, 6) as $u) {
    $uid   = (int) $u['id'];
    $role  = (string) $u['role'];
    $badge = ems_workspace_badge($conn, $CO, $uid, $role);
    $sum   = max(0, ems_my_approvals_count($conn, $CO, $uid, $role))
           + max(0, ems_my_tasks_count($conn, $CO, $uid));
    $vals[$u['username']] = $badge;
    if ($badge !== $sum) { $mismatch[] = $u['username'] . ': شارة=' . $badge . ' مجموع=' . $sum; }
}
$ok(!$mismatch, '«مطابقين لمجموعِ بلاطاتِ موافقاتي + مهامي لكلٍّ منهما» — ' . count($vals) . ' حسابًا',
    implode(' · ', $mismatch));
$ok(count(array_unique(array_values($vals))) > 1,
    '«**حسابان يريان رقمين مختلفين**»: ' . implode(' · ', array_map(
        function ($k, $v) { return $k . '=' . $v; }, array_keys($vals), array_values($vals))));

/* ── ⑤ الاختبارُ السلبيُّ للعزل: صفٌّ في كيانٍ آخرَ لا يُحرّك العدّ ──────── */
$say("\n── ⑤ الاختبارُ السلبيّ: كيانٌ آخرُ لا يُحرّك عدَّنا");
$other = 0;
$r = $conn->query("SELECT id FROM admin_companies WHERE id <> {$CO} ORDER BY id LIMIT 1");
if ($r && ($x = $r->fetch_row())) { $other = (int) $x[0]; }
if ($other <= 0) {
    $say('  ⓘ لا كيانَ ثانٍ في القاعدة — تُعلَن الطبقةُ ولا يُدّعى نجاحُها');
    $ok(false, 'كيانٌ ثانٍ للقياسِ السلبيّ', 'لا وجودَ له');
} else {
    $u0 = (int) $users[0]['id'];
    $before = ems_my_tasks_count($conn, $CO, $u0);
    $TAGW = 'WSC-TEST-FAMILY';
    $conn->query("DELETE FROM work_items WHERE deliverable LIKE '%{$TAGW}%'");
    $ins = $conn->query("INSERT INTO work_items
            (company_id, owner_user_id, assigned_user_id, deliverable, status,
             evidence_required, verifier_user_id, source_type, created_at)
          VALUES ({$other}, 1, {$u0}, 'شاهدُ عزلٍ {$TAGW}', 'open', 0, 1, 'SRC-01', NOW())");
    $ok($ins !== false, 'زُرع صفٌّ في الكيانِ الآخر #' . $other, $conn->error);
    $after = ems_my_tasks_count($conn, $CO, $u0);
    $ok($after === $before, 'ولم يتحرّك عدُّنا: ' . $before . ' ⇒ ' . $after . ' — العزلُ يعمل');
    $othC = $conn->query("SELECT COUNT(*) FROM work_items WHERE company_id={$other} AND deliverable LIKE '%{$TAGW}%'");
    $othN = ($othC && ($x = $othC->fetch_row())) ? (int) $x[0] : -1;
    $ok($othN === 1, 'والصفُّ موجودٌ فعلًا في كيانِه (' . $othN . ') — فالسكونُ عزلٌ لا فشلُ زرع');
    $del = $conn->query("DELETE FROM work_items WHERE deliverable LIKE '%{$TAGW}%'");
    $left = $conn->query("SELECT COUNT(*) FROM work_items WHERE deliverable LIKE '%{$TAGW}%'");
    $leftN = ($left && ($x = $left->fetch_row())) ? (int) $x[0] : -1;
    $ok($del !== false && $leftN === 0, 'وكُنست عائلةُ الوسم (مُرجَعُ الحذفِ مفحوص)', 'المتبقّي=' . $leftN);
}

$say("\n══ النتيجة: ناجحٌ {$PASS} · راسبٌ {$FAIL}");
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL > 0 ? 1 : 0);
