<?php
/**
 * tools/u13_action_probe.php — فحصُ أفعالِ الشاشاتِ الحيَّةِ حكمًا حكمًا
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ التصييرُ يُثبت أن الشاشةَ **تُعرض**. وهذا يُثبت أن أفعالَها **تحكم**:
 *   لكلِّ فعلٍ حالاتُ منعٍ متوقَّعةٌ وحالةُ سماحٍ واحدة — والحكمُ على النصِّ
 *   الذي تُعيده اللافتةُ لا على «لم ينفجر».
 *
 * ◆ والمنعُ أهمُّ من السماح: فعلٌ يسمح دائمًا ليس حارسًا. فأكثرُ ما يلي
 *   حالاتُ منعٍ — بلا رمزِ حماية · برمزٍ غيرِ مسجَّل · بدورٍ لا يملك ·
 *   بحقلٍ ناقص · بحالةٍ لا تسمح.
 *
 * ◆ ولا يُترك أثرٌ: ما يكتب يُنفَّذ على مُعرِّفاتٍ لا وجودَ لها فيرتدُّ 404/409،
 *   وحالةُ السماحِ الوحيدةُ (قبولُ الدليلِ ثم الإغلاق) تُهيَّأ وتُنظَّف.
 *
 * التشغيل: php tools/u13_action_probe.php [--company=4]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$CO   = 4;
foreach ($argv as $a) { if (strpos($a, '--company=') === 0) { $CO = (int) substr($a, 10); } }

require_once $ROOT . '/includes/env.php';
$db = new mysqli(ems_env('DB_HOST', '127.0.0.1'), ems_env('DB_USER', 'root'),
                 ems_env('DB_PASS', ''), ems_env('DB_NAME', 'ems'), (int) ems_env('DB_PORT', '3306'));
$db->set_charset('utf8mb4');

/** أولُ مستخدمٍ يحمل دورًا — والفحصُ لا يخترع مستخدمًا. */
function holder(\mysqli $db, $co, $role)
{
    $r = $db->query("SELECT id FROM users WHERE company_id = " . (int) $co
                  . " AND (role = '" . $db->real_escape_string((string) $role) . "'"
                  . "   OR role_id = " . (int) $role . ") ORDER BY id LIMIT 1");
    if ($r === false) { throw new RuntimeException('استعلامٌ فاشل: ' . $db->error); }
    $x = $r->fetch_row();
    return $x ? (int) $x[0] : 0;
}

/* ◆ گوتشا: الرئيسُ التنفيذيُّ في هذه المنصةِ الدورُ **9** «الإدارة التنفيذية»
     لا الدورُ 1 «ادارة التشغيل». وفحصٌ بالدورِ الخطأ يُنتج «ارتدادًا حوكميًّا»
     يُقرأ نجاحَ حارسٍ وهو في الحقيقةِ فحصٌ لم يبلغ هدفَه أصلًا. */
$R_CEO = 9;
$U_CEO       = holder($db, $CO, $R_CEO);
$U_AUDITOR   = holder($db, $CO, 33);
$U_FIN       = holder($db, $CO, 17);
$U_ASSETACC  = holder($db, $CO, 18);
if ($U_CEO === 0 || $U_AUDITOR === 0) { exit("لا حاملَ للرئيسِ أو للمراجع — لا يُفحص بالتخمين\n"); }

$tmp = sys_get_temp_dir() . '/u13_probe_' . getmypid();
@mkdir($tmp, 0777, true);
$seq = 0;

/**
 * يشغّل فعلًا في عمليةٍ مستقلةٍ بجلسةٍ محقونة، ويُعيد array(ok, msg).
 */
function fire($rel, $role, $uid, $co, array $post)
{
    global $ROOT, $tmp, $seq;
    $pf = $tmp . '/p' . (++$seq) . '.json';
    file_put_contents($pf, json_encode($post, JSON_UNESCAPED_UNICODE));
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($ROOT . '/tools/u13_render_one.php')
         . ' ' . escapeshellarg($rel) . ' ' . escapeshellarg((string) $role)
         . ' ' . (int) $uid . ' ' . (int) $co . ' --post=' . escapeshellarg($pf) . ' 2>&1';
    $out = (string) shell_exec($cmd);
    @unlink($pf);
    foreach (explode("\n", $out) as $ln) {
        if (strpos($ln, 'ACT|') === 0) {
            $p = explode('|', trim($ln), 3);
            return array($p[1] === 'ok', isset($p[2]) ? trim($p[2]) : '');
        }
        if (strpos($ln, 'VERDICT|gov') === 0) { return array(false, '«ارتدادٌ حوكميٌّ — الشاشةُ لا تُفتح لهذا الدور»'); }
        if (strpos($ln, 'VERDICT|fatal') === 0 || strpos($ln, 'VERDICT|warn') === 0) {
            return array(false, 'عطبٌ: ' . mb_substr(trim(substr($ln, 8)), 0, 140));
        }
    }
    return array(false, 'لا لافتةَ حصيلةٍ — لم يُنفَّذ الفعلُ أصلًا');
}

$CASES = array();
$add = function ($id, $title, $expect, $rel, $role, $uid, array $post, $needle = '')
       use (&$CASES) {
    $CASES[] = compact('id', 'title', 'expect', 'rel', 'role', 'uid', 'post', 'needle');
};

/* ═══ ① الحارسان العامَّان — على كلِّ فعلٍ قبلَ أيِّ حكمِ مجال ══════════════ */
$add('G-01', 'فعلٌ بلا رمزِ حمايةٍ يُمنع', 'deny',
     'Finance/ob_register.php', 17, $U_FIN,
     array('u13_action' => 'terminate', 'csrf_token' => '', 'source_kind' => 'contract',
           'source_ref' => 'NOPE-0', 'on_date' => '2026-01-01', 'why' => 'فحص'),
     'رمزُ الحماية');

$add('G-02', 'فعلٌ غيرُ معرَّفٍ للشاشةِ يُمنع', 'deny',
     'Finance/ob_register.php', 17, $U_FIN,
     array('u13_action' => 'wipe_everything', 'csrf_token' => '__VALID__'),
     'غيرُ معرَّف');

$add('G-03', 'شاشةُ الرئيسِ لا تُفتح لدورٍ لا يملكها', 'deny',
     'Portal/ceo_assignments.php', 17, $U_FIN,
     array('u13_action' => 'approve', 'csrf_token' => '__VALID__', 'assignment_no' => 'X'),
     'ارتداد');

/* ◆ استقلالُ المراجعة: الرئيسُ **يرى** ملاحظاتِ المراجعِ (شفافية) ولا **يكتب**
     فيها. فالمنحةُ قراءةٌ بلا can_edit، والحارسُ يمنعه قبلَ بلوغِ الخدمة. */
$add('G-04', 'الرئيسُ يرى أوراقَ المراجعِ ولا يكتب فيها', 'deny',
     'Audit/iaf_findings.php', $R_CEO, $U_CEO,
     array('u13_action' => 'close', 'csrf_token' => '__VALID__', 'finding_no' => 'U13-PROBE-F1'),
     'لا صلاحيةَ كتابة');

/* ═══ ② أفعالُ الالتزامات — OR-08 ═════════════════════════════════════════ */
$add('OB-01', 'الإنهاءُ بلا تاريخٍ صالحٍ يُمنع', 'deny',
     'Finance/ob_register.php', 17, $U_FIN,
     array('u13_action' => 'terminate', 'csrf_token' => '__VALID__', 'source_kind' => 'contract',
           'source_ref' => 'NOPE-0', 'on_date' => 'غدًا', 'why' => 'فحص'),
     'YYYY-MM-DD');

$add('OB-02', 'إنهاءُ التزامٍ لا وجودَ له يُردُّ 404 لا يُخترع', 'deny',
     'Finance/ob_register.php', 17, $U_FIN,
     array('u13_action' => 'terminate', 'csrf_token' => '__VALID__', 'source_kind' => 'contract',
           'source_ref' => 'U13-PROBE-NONE', 'on_date' => '2026-08-09', 'why' => 'فحص'),
     'لا التزامَ نشط');

/* ═══ ③ أفعالُ المرتجَع — BR-06 ═══════════════════════════════════════════ */
$add('BF-01', 'إغلاقُ مرتجَعٍ بلا سببٍ يُمنع', 'deny',
     'Finance/acc_backflow.php', 18, ($U_ASSETACC ?: $U_FIN),
     array('u13_action' => 'resolve', 'csrf_token' => '__VALID__', 'backflow_id' => '999999',
           'close_reason' => ''),
     'بلا سببٍ مسجَّل');

$add('BF-02', 'إغلاقُ مرتجَعٍ لا وجودَ له يُردُّ ولا يُنشئ', 'deny',
     'Finance/acc_backflow.php', 18, ($U_ASSETACC ?: $U_FIN),
     array('u13_action' => 'resolve', 'csrf_token' => '__VALID__', 'backflow_id' => '999999',
           'close_reason' => 'فحصُ الحارس'),
     'لا مرتجَعَ مفتوح');

/* ═══ ④ أفعالُ التكليف — CEO-Y0121/Y0122 ══════════════════════════════════ */
/* ◆ الدورُ هنا **9** لا 1 — وإلا ارتدَّ الطلبُ عند حارسِ الشاشةِ فمرَّ الفحصُ
     أخضرَ على منعٍ ليس هو المقصود، والحكمُ الداخليُّ لم يُختبَر أصلًا. */
$add('AS-01', 'قرارُ الرئيسِ على طلبٍ لا وجودَ له يُردُّ ولا يُنشئ', 'deny',
     'Portal/ceo_assignments.php', $R_CEO, $U_CEO,
     array('u13_action' => 'approve', 'csrf_token' => '__VALID__',
           'assignment_no' => 'U13-PROBE-NONE', 'decision_reason' => 'فحص'),
     'غيرُ موجود');

$add('AS-02', 'ردُّ طلبٍ لا وجودَ له يُردُّ كذلك — ولا يُخترع سجلّ', 'deny',
     'Portal/ceo_assignments.php', $R_CEO, $U_CEO,
     array('u13_action' => 'reject', 'csrf_token' => '__VALID__',
           'assignment_no' => 'U13-PROBE-NONE', 'decision_reason' => 'سببُ الفحص'),
     'غيرُ موجود');

/* CEO-Y0121: طلبٌ نظيفٌ يُعرض ولا يسري — ثم يسري بموافقةِ الرئيسِ وحدَها. */
$add('AS-03', 'الرئيسُ يوافق على تكليفٍ نظيفٍ فيسري', 'allow',
     'Portal/ceo_assignments.php', $R_CEO, $U_CEO,
     array('u13_action' => 'approve', 'csrf_token' => '__VALID__',
           'assignment_no' => 'U13-PROBE-ASG', 'decision_reason' => 'فحصٌ آليٌّ — يُحذف'),
     'سرى التكليف');

$add('AS-04', 'ولا يُقرَّر مرتين — القرارُ لا يُعاد', 'deny',
     'Portal/ceo_assignments.php', $R_CEO, $U_CEO,
     array('u13_action' => 'reject', 'csrf_token' => '__VALID__',
           'assignment_no' => 'U13-PROBE-ASG', 'decision_reason' => 'محاولةُ نقض'),
     'مقرَّرٌ سلفًا');

/* ═══ ⑤ أفعالُ المراجعة — IAF §2-2 · CEO-Y0125 ════════════════════════════ */
$add('IA-03', 'المراجعُ لا يُغلق ملاحظةً بلا دليلٍ مقبول', 'deny',
     'Audit/iaf_findings.php', 33, $U_AUDITOR,
     array('u13_action' => 'close', 'csrf_token' => '__VALID__', 'finding_no' => 'U13-PROBE-F1'),
     'بلا دليل');

$add('IA-04', 'المراجعُ يقبل الدليل — والقبولُ فعلُه وحدَه', 'allow',
     'Audit/iaf_findings.php', 33, $U_AUDITOR,
     array('u13_action' => 'accept_evidence', 'csrf_token' => '__VALID__',
           'finding_no' => 'U13-PROBE-F1', 'evidence_ref' => 'أثرُ الفحص'),
     'قَبِل');

$add('IA-05', 'وبالدليلِ المقبولِ يُغلقها — وهذا هو المسارُ الوحيد', 'allow',
     'Audit/iaf_findings.php', 33, $U_AUDITOR,
     array('u13_action' => 'close', 'csrf_token' => '__VALID__', 'finding_no' => 'U13-PROBE-F1'),
     'أُغلقت');

/* ═══ التهيئةُ: ملاحظةُ فحصٍ واحدةٌ تُنشأ وتُحذف ═══════════════════════════ */
$db->query("DELETE FROM iaf_findings WHERE company_id = $CO AND finding_no = 'U13-PROBE-F1'");
$cols = array();
$r = $db->query("SHOW COLUMNS FROM iaf_findings");
while ($x = $r->fetch_assoc()) { $cols[$x['Field']] = $x; }

$seed = array('company_id' => $CO, 'finding_no' => 'U13-PROBE-F1',
              'title' => 'ملاحظةُ فحصٍ آليٍّ — تُحذف بعد الفحص',
              'severity' => 'low', 'state' => 'open', 'evidence_accepted' => 0);
if (isset($cols['auditee_user_id'])) { $seed['auditee_user_id'] = $U_FIN ?: 1; }
if (isset($cols['raised_by']))       { $seed['raised_by'] = $U_AUDITOR; }
if (isset($cols['due_date']))        { $seed['due_date'] = date('Y-m-d', strtotime('+30 days')); }
if (isset($cols['raised_at']))       { $seed['raised_at'] = date('Y-m-d H:i:s'); }
$seed = array_intersect_key($seed, $cols);

/* ◆ enum الخطورةِ قد لا يعرف 'low' — يُقرأ من العمودِ نفسِه لا يُخمَّن. */
if (isset($cols['severity']['Type']) && preg_match_all("~'([^']+)'~", $cols['severity']['Type'], $m)) {
    if (!in_array($seed['severity'], $m[1], true)) { $seed['severity'] = $m[1][0]; }
}
if (isset($cols['state']['Type']) && preg_match_all("~'([^']+)'~", $cols['state']['Type'], $m)) {
    if (!in_array($seed['state'], $m[1], true)) { $seed['state'] = $m[1][0]; }
}

$f = array_keys($seed);
$sql = "INSERT INTO iaf_findings (`" . implode('`,`', $f) . "`) VALUES ("
     . implode(',', array_fill(0, count($f), '?')) . ")";
$st = $db->prepare($sql);
if (!$st) { exit("تعذّر تهيئةُ ملاحظةِ الفحص: " . $db->error . "\n"); }
$types = str_repeat('s', count($f));
$vals  = array_values($seed);
$st->bind_param($types, ...$vals);
if (!$st->execute()) { exit("تعذّر تهيئةُ ملاحظةِ الفحص: " . $st->error . "\n"); }
$st->close();

/* طلبُ تكليفٍ نظيفٍ للفحصِ — يُنشأ **بالبوابةِ نفسِها** لا بإدراجٍ يدويٍّ،
   فما لا يمرُّ ببوابتِه لا يُثبت أن بوابتَه تعمل. */
require_once $ROOT . '/app/Services/Exec/AssignmentGate.php';
$db->query("DELETE FROM exec_assignments WHERE company_id = $CO AND assignment_no = 'U13-PROBE-ASG'");
$subject = $U_ASSETACC ?: $U_FIN;
$req = \App\Services\Exec\AssignmentGate::request($db, array(
    'company_id' => $CO, 'assignment_no' => 'U13-PROBE-ASG',
    'subject_user_id' => $subject, 'role_id' => 35,   /* معدُّ المطابقة — بلا تعارضٍ مع محاسبِ الأصول */
    'requested_by' => $U_CEO, 'scope_note' => 'طلبُ فحصٍ آليٍّ — يُحذف بعد الفحص'));
if (empty($req['ok'])) { echo "  ◆ تعذّر تهيئةُ طلبِ التكليف: " . $req['reason'] . "\n"; }
elseif (($req['state'] ?? '') !== 'presented') {
    echo "  ◆ طلبُ الفحصِ خرج «" . $req['state'] . "» لا «presented» — AS-03 ستُظهر السبب\n";
}

/* ═══ التشغيلُ والحكم ═════════════════════════════════════════════════════ */
echo "فحصُ أفعالِ update0013 — " . (count($CASES) + 2) . " حالة · الكيان $CO\n";
echo "الرئيس #$U_CEO · المراجع #$U_AUDITOR · المالية #$U_FIN · الأصول #" . ($U_ASSETACC ?: 0) . "\n\n";

$pass = 0; $fail = array();

/* ═══ حراسُ الخدمةِ نفسِها — تحتَ حارسِ الشاشة ═══════════════════════════
   ◆ الشاشةُ ترتدُّ بالرئيسِ قبلَ أن يبلغَ الخدمة (G-04). وهذا **لا يُثبت** أن
     الخدمةَ تمنعه — فلو نُودِيت من مسارٍ آخرَ (واجهةٌ برمجيةٌ · كرون · شاشةٌ
     تُبنى غدًا) لَما حماها ارتدادُ اليوم. فتُفحص الطبقةُ الداخليةُ مباشرةً. */
require_once $ROOT . '/app/Services/Audit/InternalAuditService.php';
$svc = array(
    array('IA-01', 'الخدمةُ ترفض قبولَ الرئيسِ لدليل — القبولُ للمراجعِ حصرًا', 'حصرًا',
          function () use ($db, $CO, $U_CEO) {
              return \App\Services\Audit\InternalAuditService::acceptEvidence($db, array(
                  'company_id' => $CO, 'finding_no' => 'U13-PROBE-F1',
                  'evidence_ref' => 'أثرٌ', 'accepted_by' => $U_CEO));
          }),
    array('IA-02', 'الخدمةُ ترفض إغلاقَ الرئيسِ — سلطتُه في القرارِ لا في إسقاطِ الدليل', 'الرئيس',
          function () use ($db, $CO, $U_CEO) {
              return \App\Services\Audit\InternalAuditService::closeFinding($db, array(
                  'company_id' => $CO, 'finding_no' => 'U13-PROBE-F1', 'closed_by' => $U_CEO));
          }),
);
foreach ($svc as $s2) {
    list($id, $title, $needle, $fn) = $s2;
    try { $res = $fn(); } catch (\Throwable $e) { $res = array('ok' => false, 'reason' => 'عطب: ' . $e->getMessage()); }
    $msg = (string) ($res['reason'] ?? '');
    $good = empty($res['ok']) && ($needle === '' || mb_strpos($msg, $needle) !== false);
    printf("  %s %-6s %-58s %s\n", $good ? '✔' : '✘', $id, mb_substr($title, 0, 58), mb_substr($msg, 0, 90));
    if ($good) { $pass++; } else { $fail[$id] = $title . ' → ' . $msg; }
}
foreach ($CASES as $c) {
    list($ok, $msg) = fire($c['rel'], $c['role'], $c['uid'], $CO, $c['post']);
    $wantOk  = ($c['expect'] === 'allow');
    $verdict = ($ok === $wantOk);
    if ($verdict && $c['needle'] !== '' && mb_strpos($msg, $c['needle']) === false) {
        $verdict = false;
        $msg .= '  ⟵ الحكمُ صحيحٌ ونصُّه ليس «' . $c['needle'] . '»';
    }
    printf("  %s %-6s %-58s %s\n", $verdict ? '✔' : '✘', $c['id'],
           mb_substr($c['title'], 0, 58), mb_substr($msg, 0, 90));
    if ($verdict) { $pass++; } else { $fail[$c['id']] = $c['title'] . ' → ' . $msg; }
}

/* ═══ التنظيف — لا يُترك أثرُ فحصٍ في قاعدةٍ حية ══════════════════════════ */
$db->query("DELETE FROM iaf_findings WHERE company_id = $CO AND finding_no = 'U13-PROBE-F1'");
$db->query("DELETE FROM exec_assignments WHERE company_id = $CO AND assignment_no = 'U13-PROBE-ASG'");
@array_map('unlink', (array) glob($tmp . '/*'));
@rmdir($tmp);

echo "\n" . str_repeat('═', 74) . "\n";
printf("  الأفعالُ المفحوصة: %d/%d\n", $pass, count($CASES) + count($svc));
if ($fail) { echo "\n  ✘ الفاشلة:\n"; foreach ($fail as $id => $w) { printf("     %-7s %s\n", $id, mb_substr($w, 0, 120)); } }
echo str_repeat('═', 74) . "\n";
exit($fail ? 1 : 0);
