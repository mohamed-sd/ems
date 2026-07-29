<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * UX-10 §8.2 · UX-03 §8.3 — اختبار قبول حارس الوثائق المنتهية
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/document_guard_test.php
 * رمز الخروج: 0 = أخضر · 1 = فشل.
 *
 * ما يُثبته:
 *   ① العَلَم ثلاثيٌّ ويقرأ من .env، والمجهولُ يسقط إلى off (فشلٌ آمن).
 *   ② قائمةُ الحجب هي قرارُ المالك حرفًا بحرف — لا زيادةَ ولا نقصان.
 *   ③ **المسارُ المرفوض**: وثيقةُ أهليةٍ منتهيةٌ يومَ العمل تُوقف الاعتماد
 *      بـ422 وبسببٍ يسمّي الوثيقةَ وصاحبَها وتاريخَها.
 *   ④ **المسارُ السليم**: وثيقةٌ ساريةٌ يومَ العمل تمرّ — ولو انتهت بعده.
 *   ⑤ قاعدةُ التاريخ في اتجاهيها: السريانُ **يومَ العمل** لا يومَ الاعتماد،
 *      فتجديدُ اليوم لا يُشرعن يومًا شُغِّل والوثيقةُ منتهية.
 *   ⑥ المنبِّهُ لا يحجب: «هوية» منتهيةٌ لا توقف شيئًا (قرارُ المالك ②).
 *   ⑦ `monitor` يرصد ويمرّ: ok=true والأسبابُ محسوبةٌ حاضرة.
 *   ⑧ **المسارُ الحيّ**: اعتمادُ المستوى الأول عبر الشاشة يُوقَف معلَنًا في
 *      `blocked` — وصفرُ صفِّ اعتمادٍ يُكتب. (وهو البندُ الذي بدونه يصير
 *      الحارسُ زخرفة: المرآةُ تمرّر enforce_capacity=false فلا تحجب حيًّا.)
 *
 * ⚠️ ③⑤⑧ تتطلب `enforce`، والعَلَمُ يُسلَّم `monitor` — فالحزمةُ **تقلب .env
 *    مؤقتًا وتعيده** (نسخةٌ احتياطيةٌ + استعادةٌ في shutdown، تقع حتى عند الفشل).
 *    لا تشغّلها على بيئةٍ يستعملها أحدٌ في اللحظة نفسها.
 *
 * كلُّ ما يُنشأ من وثائقَ اصطناعيةٍ يُحذف في النهاية — ولا تُمَسّ وثيقةٌ حقيقية.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

const BASE = 'http://localhost/ems';
$ROOT = dirname(__DIR__);

require_once $ROOT . '/includes/env.php';
require_once $ROOT . '/app/Services/Unit/DocumentGuard.php';
use App\Services\Unit\DocumentGuard;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

// ── .env: قراءةٌ ونسخةٌ احتياطيةٌ واستعادةٌ مضمونة ─────────────────────────
$ENV_FILE = $ROOT . '/.env';
$ENV_BAK  = file_get_contents($ENV_FILE);
$envVals = array();
foreach (explode("\n", $ENV_BAK) as $l) {
    if (preg_match('/^\s*([A-Za-z0-9_]+)=(.*)$/', $l, $m)) { $envVals[$m[1]] = trim($m[2]); }
}
$restoreEnv = function () use ($ENV_FILE, $ENV_BAK) { file_put_contents($ENV_FILE, $ENV_BAK); };
register_shutdown_function($restoreEnv);

/** يقلب قيمةَ الحارس في .env (وحدَه — بقيةُ الملف بايت-مطابقة). */
$setMode = function ($mode) use ($ENV_FILE, $ENV_BAK) {
    $out = preg_replace('/^EMS_DOC_EXPIRY_GUARD=.*$/m', 'EMS_DOC_EXPIRY_GUARD=' . $mode, $ENV_BAK);
    if (strpos($out, 'EMS_DOC_EXPIRY_GUARD=' . $mode) === false) {
        $out = rtrim($ENV_BAK) . "\nEMS_DOC_EXPIRY_GUARD=" . $mode . "\n";
    }
    file_put_contents($ENV_FILE, $out);
};

$db = new mysqli($envVals['DB_HOST'], 'root', '', $envVals['DB_NAME']);
$db->set_charset('utf8mb4');

// ── معطياتٌ اصطناعية: مراجعُ خارج المدى الحقيقي فلا تُلامس بياناتٍ حية ──
$CO      = 4;
$EQ_BAD  = 999901;   // معدةٌ برخصة تشغيلٍ منتهية
$EQ_OK   = 999902;   // معدةٌ برخصة تشغيلٍ سارية
$OP_BAD  = 999903;   // مشغّلٌ برخصة قيادةٍ منتهية
$OP_HOYA = 999904;   // مشغّلٌ بهويةٍ منتهيةٍ فقط — منبِّهٌ لا حاجب
$MARK    = 'DGTEST_' . getmypid();

$cleanupDocs = function () use ($db, $MARK) {
    $db->query("DELETE FROM equipment_documents WHERE doc_no LIKE '{$MARK}%'");
};
$cleanupDocs();
register_shutdown_function($cleanupDocs);

$mkDoc = function ($subjectType, $subjectId, $docType, $expiry) use ($db, $CO, $MARK) {
    $st = $db->prepare(
        "INSERT INTO equipment_documents
            (company_id, subject_type, subject_id, doc_type, doc_no, expiry_date, alert_days, status)
         VALUES (?,?,?,?,?,?,30,'سارية')");
    $no = $MARK . '_' . $subjectId . '_' . substr(md5($docType), 0, 6);
    $st->bind_param('isisss', $CO, $subjectType, $subjectId, $docType, $no, $expiry);
    $st->execute(); $st->close();
};

fwrite(STDOUT, "\n══ UX-10 §8.2 — حارسُ الوثائق المنتهية ══\n");

// ═══ ② قائمةُ الحجب — قرارُ المالك حرفًا بحرف ═══
head('② قائمةُ الحجب — لا زيادةَ ولا نقصان');
check(DocumentGuard::BLOCKING['equipment'] === array('استمارة', 'تأمين', 'فحص دوري', 'رخصة تشغيل'),
    'المعدة: استمارة · تأمين · فحص دوري · رخصة تشغيل');
check(DocumentGuard::BLOCKING['operator'] === array('رخصة قيادة'),
    'المشغّل: رخصة قيادة وحدَها');
$allTypes = array('استمارة','تأمين','فحص دوري','رخصة قيادة','رخصة تشغيل','تصريح','هوية','جواز سفر','عقد عمل','أخرى');
$blocking = array_merge(DocumentGuard::BLOCKING['equipment'], DocumentGuard::BLOCKING['operator']);
$alerting = array_values(array_diff($allTypes, $blocking));
check($alerting === array('تصريح','هوية','جواز سفر','عقد عمل','أخرى'),
    'والباقيةُ الخمسُ تنبّه ولا تحجب: ' . implode(' · ', $alerting));
// وقيمُ القائمة كلُّها من ENUM المخطط — قيمةٌ خارجَه تُبتلع صامتةً فلا تحجب أبدًا
$enumRow = $db->query("SHOW COLUMNS FROM equipment_documents LIKE 'doc_type'")->fetch_assoc();
preg_match_all("/'([^']+)'/", $enumRow['Type'], $em);
check(count(array_diff($blocking, $em[1])) === 0,
    'وكلُّ قيمةٍ حاجبةٍ موجودةٌ في ENUM المخطط (لا حجبَ باسمٍ لا يطابق شيئًا)');

// ═══ ① العَلَم ثلاثيٌّ وفشلُه آمن ═══
head('① العَلَم ثلاثيٌّ — والمجهولُ يسقط إلى off');
$modeVia = function ($v) use ($setMode, $ROOT) {
    $setMode($v);
    // عمليةٌ منفصلة: ems_env يخزّن القيم static داخل العملية الواحدة
    $cmd = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg(
        'require ' . var_export($ROOT . '/includes/env.php', true) . ';'
        . 'require ' . var_export($ROOT . '/app/Services/Unit/DocumentGuard.php', true) . ';'
        . 'echo App\\Services\\Unit\\DocumentGuard::mode();');
    return trim(shell_exec($cmd));
};
check($modeVia('off') === 'off', 'off  → off');
check($modeVia('monitor') === 'monitor', 'monitor → monitor');
check($modeVia('enforce') === 'enforce', 'enforce → enforce');
check($modeVia('ENFORCE') === 'enforce', 'ENFORCE → enforce (لا حساسيةَ لحالة الأحرف)');
check($modeVia('نعم') === 'off', 'قيمةٌ مجهولة → off — فشلٌ آمن لا حجبٌ عشوائي');
$restoreEnv();

// ═══ البذرة ═══
head('البذرة — وثائقُ اختبارٍ اصطناعية');
$mkDoc('equipment', $EQ_BAD,  'رخصة تشغيل', '2026-01-31');
$mkDoc('equipment', $EQ_OK,   'رخصة تشغيل', '2027-12-31');
$mkDoc('operator',  $OP_BAD,  'رخصة قيادة', '2025-06-30');
$mkDoc('operator',  $OP_HOYA, 'هوية',       '2020-01-01');
$seeded = (int) $db->query("SELECT COUNT(*) n FROM equipment_documents WHERE doc_no LIKE '{$MARK}%'")->fetch_assoc()['n'];
check($seeded === 4, "بُذرت أربعُ وثائقَ اصطناعية: {$seeded}");

// ═══ الفحوصُ التي تحتاج enforce — في عمليةٍ منفصلة تقرأ العَلَمَ المقلوب ═══
// ⚠️ گوتشا ويندوز: `escapeshellarg` **يبتلع علامات التنصيص المزدوجة** فيفسد أي
//    كود `php -r` يحويها (يخرج بلا مخرَجٍ فيبدو الفحصُ فاشلًا كذبًا). فالمسبارُ
//    ملفٌّ مؤقتٌ يأخذ معطياته من argv — لا كودٌ في سطر الأوامر.
$PROBE = sys_get_temp_dir() . '/ems_docguard_probe_' . getmypid() . '.php';
file_put_contents($PROBE, "<?php\n"
    . "require " . var_export($ROOT . '/includes/env.php', true) . ";\n"
    . "require " . var_export($ROOT . '/app/Services/Unit/DocumentGuard.php', true) . ";\n"
    . "\$c = new mysqli(ems_env('DB_HOST'), 'root', '', ems_env('DB_NAME'));\n"
    . "\$c->set_charset('utf8mb4');\n"
    . "echo json_encode(App\\Services\\Unit\\DocumentGuard::assertForRefs(\n"
    . "    \$c, 4, (int) \$argv[1], (int) \$argv[2], \$argv[3], 'probe'), JSON_UNESCAPED_UNICODE);\n");
register_shutdown_function(function () use ($PROBE) { @unlink($PROBE); });

$setMode('enforce');
$probe = function ($eq, $op, $date) use ($PROBE) {
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($PROBE) . ' '
         . (int) $eq . ' ' . (int) $op . ' ' . escapeshellarg($date);
    return json_decode(trim((string) shell_exec($cmd)), true);
};

// ═══ ③ المسارُ المرفوض ═══
head('③ المسارُ المرفوض — الوثيقةُ المنتهيةُ توقف الاعتماد');
$r = $probe($EQ_BAD, 0, '2026-07-28');
check(is_array($r) && $r['ok'] === false && (int) $r['code'] === 422,
    'معدةٌ برخصة تشغيلٍ منتهية: مرفوضٌ بـ422');
check(!empty($r['reasons']) && strpos($r['reasons'][0], 'رخصة تشغيل') !== false
      && strpos($r['reasons'][0], '2026-01-31') !== false
      && strpos($r['reasons'][0], (string) $EQ_BAD) !== false,
    'والسببُ يسمّي الوثيقةَ وتاريخَها وصاحبَها: ' . ($r['reasons'][0] ?? '—'));
check(strpos($r['reasons'][0] ?? '', 'جدّدها من شاشة') !== false,
    'ويقول ماذا يُفعل — لغةُ المهمة لا لغةُ التحليل');

$r = $probe(0, $OP_BAD, '2026-07-28');
check(is_array($r) && $r['ok'] === false && strpos($r['reasons'][0] ?? '', 'رخصة قيادة') !== false,
    'ومشغّلٌ برخصة قيادةٍ منتهية: مرفوضٌ كذلك (المحوران مستقلّان)');

$r = $probe($EQ_BAD, $OP_BAD, '2026-07-28');
check(is_array($r) && count($r['reasons']) === 2,
    'والواقعةُ المعطوبةُ في المحورين تُعلن السببين معًا: ' . count($r['reasons']));

// ═══ ④ المسارُ السليم ═══
head('④ المسارُ السليم — الساري يمرّ بلا سؤال');
$r = $probe($EQ_OK, 0, '2026-07-28');
check(is_array($r) && $r['ok'] === true && empty($r['reasons']),
    'معدةٌ برخصةٍ ساريةٍ إلى 2027-12-31: تمرّ بلا سببٍ ولا أثر');
$r = $probe(999999, 888888, '2026-07-28');
check(is_array($r) && $r['ok'] === true,
    'ومرجعٌ بلا وثائقَ إطلاقًا: يمرّ — الحارسُ يحجب بوثيقةٍ منتهيةٍ لا بغيابها');
$r = $probe(0, 0, '2026-07-28');
check(is_array($r) && $r['ok'] === true, 'وواقعةٌ بلا محورين: تمرّ (لا شيءَ يُفحص)');

// ═══ ⑤ قاعدةُ التاريخ في اتجاهيها ═══
head('⑤ السريانُ يومَ العمل لا يومَ الاعتماد');
$r = $probe($EQ_BAD, 0, '2026-01-30');
check(is_array($r) && $r['ok'] === true,
    'يومُ عملٍ سابقٌ للانتهاء (2026-01-30 < 2026-01-31): يمرّ — الانتهاءُ اللاحق لا يُبطل ما مضى');
$r = $probe($EQ_BAD, 0, '2026-01-31');
check(is_array($r) && $r['ok'] === true,
    'ويومُ الانتهاء نفسُه ساري (الشرطُ < لا <=): الوثيقةُ صالحةٌ يومَها الأخير');
$r = $probe($EQ_BAD, 0, '2026-02-01');
check(is_array($r) && $r['ok'] === false,
    'واليومُ التالي محجوب — الحدُّ حادٌّ لا ضبابيّ');
// الاتجاهُ الآخر: تجديدٌ اليوم لا يُشرعن يومًا شُغِّل والوثيقةُ منتهية
$mkDoc('equipment', $EQ_BAD, 'تأمين', '2030-12-31');
$r = $probe($EQ_BAD, 0, '2026-07-28');
check(is_array($r) && $r['ok'] === false,
    'ووثيقةٌ ثانيةٌ ساريةٌ إلى 2030 لا تُغطّي على المنتهية — التجديدُ لا يمحو الماضي');

// ═══ ⑥ المنبِّهُ لا يحجب ═══
head('⑥ وثائقُ الصفة تنبّه ولا تحجب (قرارُ المالك ②)');
$r = $probe(0, $OP_HOYA, '2026-07-28');
check(is_array($r) && $r['ok'] === true && empty($r['reasons']),
    'هويةٌ منتهيةٌ منذ 2020: لا تحجب — وثيقةُ صفةٍ لا وثيقةُ أهلية');

// ═══ ⑦ monitor يرصد ويمرّ ═══
head('⑦ monitor — يقيس ويسجّل ويمرّ');
$setMode('monitor');
$r = $probe($EQ_BAD, $OP_BAD, '2026-07-28');
check(is_array($r) && $r['ok'] === true, 'الواقعةُ المعطوبةُ نفسُها تمرّ (ok=true)');
check(is_array($r) && !empty($r['reasons']) && !empty($r['monitored']),
    'والأسبابُ محسوبةٌ حاضرةٌ ومعلَّمةٌ monitored — رصدٌ لا عمًى: ' . count($r['reasons'] ?? array()) . ' سببًا');
$setMode('off');
$r = $probe($EQ_BAD, $OP_BAD, '2026-07-28');
check(is_array($r) && $r['ok'] === true && empty($r['reasons']),
    'وoff صفرُ أثرٍ: لا حكمَ ولا قياسَ ولا استعلام');

// ═══ ⑧ المسارُ الحيّ — الشاشةُ لا الخدمةُ وحدَها ═══
head('⑧ المسارُ الحيّ — اعتمادُ المستوى الأول يُوقَف فعلًا');
$setMode('enforce');

$JAR = sys_get_temp_dir() . '/ems_docguard_cookies.txt';
function req($url, $post = null, array $headers = array()) {
    global $JAR;
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $JAR, CURLOPT_COOKIEFILE => $JAR,
        CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 30));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $b = curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return array($c, $b);
}
function login($u) {
    global $JAR; @unlink($JAR);
    list(, $p) = req(BASE . '/login.php');
    preg_match('/name="csrf_token"\s+value="([^"]+)"/', $p, $m);
    list($c) = req(BASE . '/login.php', array('username' => $u, 'password' => '12345678',
                                              'csrf_token' => $m[1] ?? ''));
    return $c === 200;
}
function ajax($action, array $data) {
    list(, $pg) = req(BASE . '/Approvals/hours_approval.php');
    preg_match('/name="csrf_token"\s+value="([^"]+)"/', $pg, $m);
    $data['action'] = $action;
    $data['csrf_token'] = $m[1] ?? '';
    list(, $b) = req(BASE . '/Approvals/hours_approval_handler.php', $data,
        array('X-Requested-With: XMLHttpRequest'));
    return json_decode($b, true);
}

// صفٌّ حقيقيٌّ غيرُ معتمَدٍ ووثيقةُ أهليةٍ منتهيةٌ يومَ عمله.
// ⚠️ عزلُ بذرٍ (أمرُ التنفيذ §3): حارسُ الطاقة يسبق حارسَ الوثائق في المعالج،
// فصفٌّ مرآتُه تحمل علمَ طاقةٍ غيرَ مخلَّص (بقايا بذرِ جولاتٍ سابقةٍ ترفع ساعاتِ
// اليوم) يُحجب بـcapacity لا بـdocument فتفشل ⑧⑨ كذبًا. يُستثنى صراحةً.
$victim = $db->query(
    "SELECT t.id ts_id, t.`date`, t.`operator` eq, t.employee_id op
       FROM timesheet t
      WHERE t.company_id = 4
        AND NOT EXISTS (SELECT 1 FROM timesheet_approvals a WHERE a.timesheet_id = t.id)
        AND NOT EXISTS (SELECT 1 FROM unit_entries ue
                          JOIN unit_capacity_flags cf
                            ON cf.entry_id = ue.id AND cf.cleared_at IS NULL
                         WHERE ue.sync_uuid = CONCAT('ts:', t.id))
        AND EXISTS (SELECT 1 FROM equipment_documents d
                     WHERE d.is_deleted = 0 AND d.expiry_date IS NOT NULL
                       AND d.expiry_date < t.`date`
                       AND ( (d.subject_type='equipment' AND d.subject_id = t.`operator`
                              AND d.doc_type IN ('استمارة','تأمين','فحص دوري','رخصة تشغيل'))
                          OR (d.subject_type='operator' AND d.subject_id = t.employee_id
                              AND d.doc_type IN ('رخصة قيادة')) ))
      ORDER BY t.id DESC LIMIT 1")->fetch_assoc();

if (!$victim) {
    bad('لا صفَّ اختبارٍ حيًّا (كلُّ الوثائق سارية أو كلُّ الصفوف معتمَدة)');
} else {
    $ts = (int) $victim['ts_id'];
    $undo = function () use ($db, $ts) { $db->query("DELETE FROM timesheet_approvals WHERE timesheet_id = {$ts}"); };
    $undo();
    register_shutdown_function($undo);

    check(login('محمد'), 'دخولُ «محمد» (الدور 1 — المستوى الأول: اعتمادُ الموقع)');
    $res = ajax('approve', array('ids' => (string) $ts));

    check(is_array($res) && !empty($res['blocked']),
        "الصفُّ #{$ts} (يومَ {$victim['date']}) أُوقف معلَنًا في blocked لا مبلوعًا في skipped");
    $why = $res['blocked'][0]['reasons'][0] ?? '';
    check(strpos($why, 'منتهية') !== false,
        'وسببُه يظهر للمستخدم بلغة المهمة: ' . mb_substr($why, 0, 90));
    check(is_array($res) && (int) ($res['approved'] ?? 0) === 0, 'وصفرُ اعتمادٍ أُحصي');

    $wrote = (int) $db->query("SELECT COUNT(*) n FROM timesheet_approvals WHERE timesheet_id = {$ts}")
                      ->fetch_assoc()['n'];
    check($wrote === 0, "وصفرُ صفِّ اعتمادٍ كُتب في القاعدة: {$wrote} — الحجبُ واقعٌ لا معروض");

    // ═══ ⑨ E-08-أ — السببُ الصحيحُ يصل المستخدم ═══
    head('⑨ E-08-أ — الرسالةُ تسمّي السببَ الحقيقي، والشاشةُ تعرض التفصيل');
    $msg = (string) ($res['message'] ?? '');
    check(strpos($msg, 'وثيقةِ أهليةٍ منتهية') !== false,
        'ملخّصُ الرد ينسب الحجبَ إلى الوثيقة: ' . mb_substr($msg, 0, 120));
    check(strpos($msg, 'تجاوز طاقة') === false,
        'ولا ينسبه إلى تجاوز طاقةٍ لم يقع — سببٌ خاطئٌ أسوأُ من لا سبب');
    check(strpos($msg, 'وثائق المعدات والمشغّلين') !== false,
        'ويقول العلاجَ الصحيح (تجديدُ الوثيقة) لا «تخليصًا» لا يفكّ الحجب');
    check(isset($res['blocked'][0]['kind']) && $res['blocked'][0]['kind'] === 'document',
        'وكلُّ حاجزٍ موسومٌ بنفسه في الرد: ' . ($res['blocked'][0]['kind'] ?? '—'));

    // الشاشة: الأسبابُ تُصيَّر فعلًا لا تُهمَل
    list(, $scr) = req(BASE . '/Approvals/hours_approval.php');
    check(strpos($scr, 'renderBlocked(res.blocked)') !== false,
        'والشاشةُ تُنادي عارضَ الأسباب على الرد');
    check(strpos($scr, 'id="blocked-panel"') !== false && strpos($scr, 'id="blocked-list"') !== false,
        'ولها موضعٌ تعرضه فيه');
    check(preg_match('/function renderBlocked\s*\(/', $scr) === 1, 'وعارضُها معرَّف');
    // الأسبابُ لا تُمحى بإعادة تحميلٍ تلقائيةٍ قبل أن تُقرأ
    check(preg_match('/blocked\.length\s*\)\s*\{[^}]*showToast/s', $scr) === 1,
        'ولا إعادةَ تحميلٍ تلقائيةً حين يقع حجبٌ — وإلا مُحيت الأسبابُ قبل قراءتها');

    // والسليمُ لا يُعاق: العَلَمُ إلى off والصفُّ نفسُه يمرّ
    $setMode('off');
    $res2 = ajax('approve', array('ids' => (string) $ts));
    check(is_array($res2) && (int) ($res2['approved'] ?? 0) === 1 && empty($res2['blocked']),
        'وبإطفاء العَلَم يمرّ الصفُّ نفسُه فورًا — فالحجبُ من الحارس وحدَه لا من غيره');
    $undo();
}

$restoreEnv();
$after = file_get_contents($ENV_FILE);
check($after === $ENV_BAK, '.env عاد بايت-مطابقًا لما كان قبل الحزمة');

fwrite(STDOUT, "\n══════════════════════════════════════════════════\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL === 0 ? 0 : 1);
