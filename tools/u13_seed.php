<?php
/**
 * tools/u13_seed.php — بذرُ مراجعِ update0013 من عقدِ الوثائقِ لا من اليد
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ يقرأ `docs/update0013/spec.json` (مُخرَجَ `u13_spec_extract.php`) ويكتبه
 *   في الجداولِ المرجعية. فلا رقمَ ولا نصَّ حكمٍ يُنسَخ بيدٍ إلى SQL — وكلُّ
 *   صفٍّ يحمل `doc_ref` إلى المتطلبِ الذريِّ الذي وُلد منه.
 *
 * ما يُبذر (البند ①):
 *   fin_acc_specializations  ← ACC-01..ACC-10
 *   fin_routing_matrix       ← RT-01..RT-35
 *   fin_backflow_notices     ← BF-01..BF-15
 *   fin_backflow_rules       ← BR-01..BR-06
 *   fin_reason_codes         ← رموزُ الأسبابِ المحكومة (BR-03)
 *
 * idempotent: UPSERT على المفتاحِ الفريد — فإعادةُ التشغيلِ تُحدِّث ولا تُكرِّر.
 * التشغيل: php tools/u13_seed.php [--apply]   (بلا --apply = عرضٌ فقط)
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT  = dirname(__DIR__);
$apply = in_array('--apply', $argv, true);

$specFile = $ROOT . '/docs/update0013/spec.json';
if (!is_file($specFile)) { exit("ناقص: docs/update0013/spec.json — شغّل u13_spec_extract.php أولًا\n"); }
$S = json_decode(file_get_contents($specFile), true);
if (!is_array($S)) { exit("spec.json تالف\n"); }

$cfg = array('host' => 'localhost', 'port' => 3307, 'user' => 'root', 'pass' => '', 'db' => 'equipation_manage');
if (is_file($ROOT . '/.env')) {
    foreach (file($ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
        if ($ln === '' || $ln[0] === '#' || strpos($ln, '=') === false) { continue; }
        list($k, $v) = explode('=', $ln, 2); $k = trim($k); $v = trim($v);
        if ($k === 'DB_HOST') { $hp = explode(':', $v); $cfg['host'] = $hp[0]; if (isset($hp[1])) { $cfg['port'] = (int) $hp[1]; } }
        if ($k === 'DB_PORT') { $cfg['port'] = (int) $v; }
        if ($k === 'DB_USER') { $cfg['user'] = $v; }
        if ($k === 'DB_PASS') { $cfg['pass'] = $v; }
        if ($k === 'DB_NAME') { $cfg['db']   = $v; }
    }
}
$db = @new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['db'], $cfg['port']);
if ($db->connect_errno) { exit("تعذّر الاتصال: {$db->connect_error}\n"); }
/* ◆ عميلُ utf8mb4 إلزامٌ — وإلا ابتلعت القيمُ العربيةُ صامتةً. */
$db->set_charset('utf8mb4');

$stats = array();

/**
 * تنفيذٌ محروس: يرمي عند الفشلِ بدل أن يمضيَ صامتًا.
 * ◆ گوتشا موثَّقة: `config.php` يضبط mysqli على عدمِ الرمي — فالمُرجَعُ يُفحص.
 */
function run($db, $sql, $types, $vals, $label)
{
    $st = $db->prepare($sql);
    if (!$st) { throw new RuntimeException("$label — prepare: " . $db->error); }
    $st->bind_param($types, ...$vals);
    if (!$st->execute()) { $e = $st->error; $st->close(); throw new RuntimeException("$label — execute: $e"); }
    /* ◆ گوتشا: `$db->affected_rows` بعد عبارةٍ مُعدَّةٍ يعود −1 — العدُّ من
         `$st->affected_rows` وحدَه. */
    $n = $st->affected_rows;
    $st->close();
    return max(0, (int) $n);
}

/** التحقق: عددُ الوسائط يطابق طولَ سلسلةِ الأنواع وعددَ علاماتِ الاستفهام. */
function assertArity($sql, $types, $vals, $label)
{
    $q = substr_count($sql, '?');
    if ($q !== strlen($types) || $q !== count($vals)) {
        throw new RuntimeException(sprintf('%s — انزياحُ وسائط: ? = %d · أنواع = %d · قيم = %d',
            $label, $q, strlen($types), count($vals)));
    }
}

try {
    if ($apply) { $db->begin_transaction(); }

    /* ── ① التخصصاتُ العشرة ─────────────────────────────────────────────── */
    $sql = "INSERT INTO fin_acc_specializations
              (company_id, code, name_ar, name_en, accounts, scope, dims, limit_rule, doc_ref, active)
            VALUES (0,?,?,?,?,?,?,?,?,1)
            ON DUPLICATE KEY UPDATE name_ar=VALUES(name_ar), name_en=VALUES(name_en),
              accounts=VALUES(accounts), scope=VALUES(scope), dims=VALUES(dims),
              limit_rule=VALUES(limit_rule), doc_ref=VALUES(doc_ref), active=1";
    $n = 0;
    foreach ($S['acc_specializations'] as $x) {
        $vals = array($x['code'], $x['name_ar'], $x['name_en'], mb_substr($x['accounts'], 0, 255),
                      mb_substr($x['scope'], 0, 255), $x['dims'], mb_substr($x['limit'], 0, 300), $x['src']);
        assertArity($sql, 'ssssssss', $vals, 'التخصص ' . $x['code']);
        if ($apply) { run($db, $sql, 'ssssssss', $vals, 'التخصص ' . $x['code']); }
        $n++;
    }
    $stats['fin_acc_specializations'] = $n;

    /* ── ② مصفوفةُ التوجيه ──────────────────────────────────────────────── */
    /* مفتاحُ الحدثِ يُشتقُّ من رمزِ المسارِ نفسِه — ثابتٌ ومتتبَّعٌ ولا يُخترع. */
    $sql = "INSERT INTO fin_routing_matrix
              (company_id, code, kind, trigger_ar, trigger_key, source_dept, launch_cond,
               target_spec, target_label, accounts, dims, chain, guard_rule, accept_test, doc_ref, active)
            VALUES (0,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)
            ON DUPLICATE KEY UPDATE kind=VALUES(kind), trigger_ar=VALUES(trigger_ar),
              trigger_key=VALUES(trigger_key), source_dept=VALUES(source_dept),
              launch_cond=VALUES(launch_cond), target_spec=VALUES(target_spec),
              target_label=VALUES(target_label), accounts=VALUES(accounts), dims=VALUES(dims),
              chain=VALUES(chain), guard_rule=VALUES(guard_rule), accept_test=VALUES(accept_test),
              doc_ref=VALUES(doc_ref), active=1";
    $n = 0;
    foreach ($S['routes'] as $x) {
        $key  = 'fin.route.' . strtolower(str_replace('-', '', $x['code']));
        $vals = array($x['code'], $x['kind'], mb_substr($x['trigger'], 0, 200), $key,
                      mb_substr($x['source'], 0, 160), mb_substr($x['condition'], 0, 300),
                      $x['target_spec'], mb_substr($x['target'], 0, 200), mb_substr($x['accounts'], 0, 255),
                      mb_substr($x['dims'], 0, 64), mb_substr($x['chain'], 0, 500),
                      mb_substr($x['guard'], 0, 500), mb_substr($x['test'], 0, 400), $x['src']);
        assertArity($sql, str_repeat('s', 14), $vals, 'المسار ' . $x['code']);
        if ($apply) { run($db, $sql, str_repeat('s', 14), $vals, 'المسار ' . $x['code']); }
        $n++;
    }
    $stats['fin_routing_matrix'] = $n;

    /* ── ③ المرتجَعُ الخمسةَ عشرَ ───────────────────────────────────────── */
    /* BR-02: «الإشعارُ الذي يستوجب فعلًا يولّد مهمة» — وما لا يستوجب إخبارٌ.
       الإقفالُ والميزانيةُ إخبارٌ جماعيٌّ · وما عداها يستوجب فعلًا. */
    $noAction = array('BF-09', 'BF-10', 'BF-12');
    $sql = "INSERT INTO fin_backflow_notices
              (company_id, code, title, fires_when, destination, rule_text, needs_action, doc_ref, active)
            VALUES (0,?,?,?,?,?,?,?,1)
            ON DUPLICATE KEY UPDATE title=VALUES(title), fires_when=VALUES(fires_when),
              destination=VALUES(destination), rule_text=VALUES(rule_text),
              needs_action=VALUES(needs_action), doc_ref=VALUES(doc_ref), active=1";
    $n = 0;
    foreach ($S['backflow'] as $x) {
        $vals = array($x['code'], mb_substr($x['title'], 0, 200), mb_substr($x['trigger'], 0, 300),
                      mb_substr($x['to'], 0, 300), mb_substr($x['rule'], 0, 500),
                      in_array($x['code'], $noAction, true) ? 0 : 1, $x['src']);
        assertArity($sql, 'sssssis', $vals, 'المرتجَع ' . $x['code']);
        if ($apply) { run($db, $sql, 'sssssis', $vals, 'المرتجَع ' . $x['code']); }
        $n++;
    }
    $stats['fin_backflow_notices'] = $n;

    /* ── ④ قواعدُ المرتجَعِ الست ────────────────────────────────────────── */
    $sql = "INSERT INTO fin_backflow_rules (company_id, code, rule_text, accept_test, doc_ref, active)
            VALUES (0,?,?,?,?,1)
            ON DUPLICATE KEY UPDATE rule_text=VALUES(rule_text), accept_test=VALUES(accept_test),
              doc_ref=VALUES(doc_ref), active=1";
    $n = 0;
    foreach ($S['backflow_rules'] as $x) {
        $vals = array($x['code'], mb_substr($x['rule'], 0, 600), mb_substr($x['test'], 0, 400), $x['src']);
        assertArity($sql, 'ssss', $vals, 'قاعدةُ المرتجَع ' . $x['code']);
        if ($apply) { run($db, $sql, 'ssss', $vals, 'قاعدةُ المرتجَع ' . $x['code']); }
        $n++;
    }
    $stats['fin_backflow_rules'] = $n;

    /* ── ⑤ رموزُ الأسبابِ المحكومة (BR-03) ─────────────────────────────── */
    /* هذه الرموزُ ليست في الوثيقةِ قائمةً مغلقة — بل الوثيقةُ توجب «رمزًا محكومًا
       لا نصًّا حرًّا». فالمجموعةُ الابتدائيةُ مشتقةٌ من أسبابِ الرفضِ التي تسميها
       واجباتُ محاسبِ الإدارةِ الستةَ عشرَ (FIN-ACC-01 §4-2) وأسبابِ المرتجَع. */
    $reasons = array(
        array('DOC_MISSING',      'مستندٌ إلزاميٌّ ناقص',                        'missing_doc', 1),
        array('DOC_UNREADABLE',   'مرفقٌ غيرُ مقروءٍ أو غيرُ مطابقٍ للطلب',      'missing_doc', 1),
        array('REF_INVALID',      'المرجعُ الأبُ غيرُ موجودٍ أو غيرُ نافذ',       'reject',      0),
        array('REF_TYPE_MISMATCH', 'المرجعُ لا يطابق نوعَ المعاملة',             'reject',      0),
        array('OWNER_APPROVAL_MISSING', 'اعتمادُ الإدارةِ المالكةِ غيرُ موجود',  'reject',      0),
        array('AUTHORITY_INVALID', 'الصفةُ أو مرجعُ التفويضِ غيرُ صالح',         'reject',      1),
        array('BUDGET_LINE_MISSING', 'بندُ الموازنةِ غيرُ قائم',                 'budget',      0),
        array('BUDGET_EXHAUSTED', 'المتاحُ في الموازنةِ لا يكفي',                'budget',      0),
        array('BUDGET_OVER_CAP',  'تجاوزُ الموازنةِ فوقَ سقفِ المراجع',          'budget',      0),
        array('COST_CENTER_INVALID', 'مركزُ التكلفةِ غيرُ صحيحٍ أو غيرُ نافذ',   'reject',      0),
        array('DIMS_INCONSISTENT', 'تعارضٌ بين المشروعِ والعقدِ والموقع',         'reject',      0),
        array('TREATMENT_WRONG',  'المعالجةُ المحاسبيةُ غيرُ صحيحةٍ للنوع',      'reject',      0),
        array('ACCOUNT_WRONG',    'الحسابُ لا يطابق مصفوفةَ الترحيل',            'reject',      0),
        array('FX_SOURCE_MISSING', 'سعرُ الصرفِ بلا مصدرٍ معلَنٍ ومؤرَّخ',        'reject',      0),
        array('TAX_WRONG',        'الضريبةُ أو الاستقطاعُ بغيرِ نسبتِه النافذة', 'reject',      0),
        array('CREDIT_LIMIT',     'تجاوزُ حدِّ ائتمانِ العميلِ بلا تصعيد',        'credit',      0),
        array('VARIANCE_UNEXPLAINED', 'انحرافٌ فوقَ الحدِّ بلا تفسير',            'variance',    1),
        array('PERIOD_CLOSED',    'الفترةُ مُقفلةٌ — لا كتابةَ فيها',            'reject',      0),
        array('DUPLICATE',        'تكرارٌ لمعاملةٍ قائمةٍ بالمرجعِ نفسِه',       'reject',      0),
        array('AUDIT_FINDING',    'ملاحظةُ مراجعةٍ داخليةٍ قائمةٌ على النطاق',   'audit',       1),
    );
    $sql = "INSERT INTO fin_reason_codes (company_id, code, text_ar, kind, needs_doc, active)
            VALUES (0,?,?,?,?,1)
            ON DUPLICATE KEY UPDATE text_ar=VALUES(text_ar), kind=VALUES(kind),
              needs_doc=VALUES(needs_doc), active=1";
    $n = 0;
    foreach ($reasons as $r) {
        $vals = array($r[0], $r[1], $r[2], $r[3]);
        assertArity($sql, 'sssi', $vals, 'سبب ' . $r[0]);
        if ($apply) { run($db, $sql, 'sssi', $vals, 'سبب ' . $r[0]); }
        $n++;
    }
    $stats['fin_reason_codes'] = $n;

    /* ══ البند ② — أنواعُ الاعتمادِ الأربعة ═══════════════════════════════ */

    /* ⑦ الأنواعُ الأربعة: النصُّ من الوثيقةِ · والأدوارُ المسموحةُ مشتقةٌ من
       «صاحبِه» كما تسميه الوثيقةُ ومن FMGR-0004 «ولا يعتمد المديرُ الماليُّ
       اعتمادًا موازنيًّا نيابةً عن رئيسِ الحساباتِ ولا عن محاسبِ الإدارة». */
    $aprRoles = array(
        /* APR-1 صاحبُه «الإدارةُ المالكة» — أيُّ إدارةٍ مالكةٍ للطلب، فلا حصرَ بدور. */
        'APR-1' => array('', 0),
        /* APR-2 صاحبُه «محاسبُ الإدارةِ ورئيسُ الحساباتِ ضمنَ اختصاصهما» حصرًا. */
        'APR-2' => array('18,31', 0),
        /* APR-3 صاحبُه «صاحبُ السلطةِ الماليةِ أو الإداريةِ بالسقف» — ويشترط سقفًا. */
        'APR-3' => array('', 1),
        /* APR-4 صاحبُه «الخزينةُ والبنوك» — أمينُ الخزينةِ ومنفِّذُ المدفوعات. */
        'APR-4' => array('21,34', 0),
    );
    $sql = "INSERT INTO fin_approval_types
              (company_id, code, seq, title, owner_label, question, rule_text, allowed_roles, needs_cap, doc_ref, active)
            VALUES (0,?,?,?,?,?,?,?,?,?,1)
            ON DUPLICATE KEY UPDATE seq=VALUES(seq), title=VALUES(title), owner_label=VALUES(owner_label),
              question=VALUES(question), rule_text=VALUES(rule_text), allowed_roles=VALUES(allowed_roles),
              needs_cap=VALUES(needs_cap), doc_ref=VALUES(doc_ref), active=1";
    $n = 0;
    foreach ($S['approval_types'] as $i => $x) {
        $conf = isset($aprRoles[$x['code']]) ? $aprRoles[$x['code']] : array('', 0);
        $vals = array($x['code'], $i + 1, mb_substr($x['title'], 0, 120), mb_substr($x['owner'], 0, 200),
                      mb_substr($x['question'], 0, 200), mb_substr($x['rule'], 0, 400), $conf[0], $conf[1], $x['src']);
        assertArity($sql, 'sisssssis', $vals, 'نوعُ الاعتماد ' . $x['code']);
        if ($apply) { run($db, $sql, 'sisssssis', $vals, 'نوعُ الاعتماد ' . $x['code']); }
        $n++;
    }
    $stats['fin_approval_types'] = $n;

    /* ⑧ الأزواجُ المتعارضة — FACC-0044 «ولا يُجمع اثنان في شخصٍ واحدٍ حيث
       يتعارضان». وكلُّ زوجٍ مسنَدٌ إلى الحكمِ الذي يمنعه في §٤-٨ و§٤-٩. */
    $conflicts = array(
        array('APR-1', 'APR-3', 'طالبُ المعاملةِ لا يشهد على حاجتِه ثم يأذن بالتزامِ المالِ لها', 'FACC-0058'),
        array('APR-2', 'APR-3', 'المراجعةُ الموازنيةُ مراجعةٌ لا ترخيصٌ بالدفع — ولا يملك محاسبُ الإدارةِ اعتمادَ صرفِ الأموال', 'FACC-0046'),
        array('APR-2', 'APR-4', 'ولا يملك محاسبُ الإدارةِ تنفيذَ الدفع', 'FACC-0047'),
        array('APR-3', 'APR-4', 'مُعِدُّ أمرِ الدفعِ لا يُجمع مع منفِّذِه — والخزينةُ تنفّذ ما اعتُمد ولا تعتمد ما تنفّذ', 'FACC-0062'),
    );
    $sql = "INSERT INTO fin_approval_conflicts (company_id, apr_a, apr_b, rule_text, doc_ref, active)
            VALUES (0,?,?,?,?,1)
            ON DUPLICATE KEY UPDATE rule_text=VALUES(rule_text), doc_ref=VALUES(doc_ref), active=1";
    $n = 0;
    foreach ($conflicts as $c) {
        $vals = array($c[0], $c[1], mb_substr($c[2], 0, 400), $c[3]);
        assertArity($sql, 'ssss', $vals, 'تعارض ' . $c[0] . '/' . $c[1]);
        if ($apply) { run($db, $sql, 'ssss', $vals, 'تعارض ' . $c[0] . '/' . $c[1]); }
        $n++;
    }
    $stats['fin_approval_conflicts'] = $n;

    /* ══ البند ④ — أزواجُ فصلِ الواجباتِ الثلاثةَ عشر ════════════════════ */
    /* النصُّ من الوثيقةِ حرفًا · والأدوارُ والموضعُ إسنادٌ صريحٌ لكلِّ زوجٍ:
       role = طرفاه مسمّيان قائمان فيُفحص عند التكليف ·
       document = طرفاه صفتان في معاملةٍ بعينِها فيُفحص على المستند.
       ولا زوجَ بلا مُنفِذٍ مسمًّى — والزوجُ بلا مُنفِذٍ ادعاءٌ لا قيد. */
    $sodMap = array(
        'SOD-01' => array('', '', 'document', 'ApprovalGate::record (APR-1 × APR-3)'),
        'SOD-02' => array('', '', 'document', 'ApprovalGate::record (APR-2 على المستندِ نفسِه)'),
        'SOD-03' => array('2,8', '21,34', 'role', 'AssignmentGate::checkConflicts'),
        'SOD-04' => array('21', '34',    'role', 'AssignmentGate::checkConflicts'),
        'SOD-05' => array('', '',        'document', 'ApprovalGate::record (APR-3 × APR-4)'),
        'SOD-06' => array('34', '35',    'role', 'AssignmentGate::checkConflicts'),
        'SOD-07' => array('35', '31',    'role', 'AssignmentGate::checkConflicts'),
        'SOD-08' => array('', '',        'document', 'ApprovalGate::record (مُعِدُّ القيدِ ومعتمِدُه)'),
        'SOD-09' => array('', '',        'document', 'ApprovalGate::record (القبضُ وإلغاؤه)'),
        'SOD-10' => array('4', '21,34',  'role', 'AssignmentGate::checkConflicts'),
        'SOD-11' => array('3', '9,32',   'role', 'AssignmentGate::checkConflicts'),
        'SOD-12' => array('31', '33',    'role', 'AssignmentGate::checkConflicts'),
        'SOD-13' => array('33', '*',     'role', 'AssignmentGate::checkConflicts (استقلالٌ مطلق)'),
    );
    $sql = "INSERT INTO sec_sod_pairs
              (company_id, code, func_a, func_b, roles_a, roles_b, why, severity, scope, enforced_by, doc_ref, active)
            VALUES (0,?,?,?,?,?,?,'block',?,?,?,1)
            ON DUPLICATE KEY UPDATE func_a=VALUES(func_a), func_b=VALUES(func_b),
              roles_a=VALUES(roles_a), roles_b=VALUES(roles_b), why=VALUES(why),
              scope=VALUES(scope), enforced_by=VALUES(enforced_by), doc_ref=VALUES(doc_ref), active=1";
    $n = 0;
    foreach ($S['sod_pairs'] as $x) {
        $m = isset($sodMap[$x['code']]) ? $sodMap[$x['code']] : array('', '', 'document', '');
        $vals = array($x['code'], mb_substr($x['func_a'], 0, 160), mb_substr($x['func_b'], 0, 160),
                      $m[0], $m[1], mb_substr($x['why'], 0, 400), $m[2], mb_substr($m[3], 0, 120), $x['src']);
        assertArity($sql, 'sssssssss', $vals, 'زوج ' . $x['code']);
        if ($apply) { run($db, $sql, 'sssssssss', $vals, 'زوج ' . $x['code']); }
        $n++;
    }
    $stats['sec_sod_pairs'] = $n;

    /* ══ البند ⑤ — مراجعُ محرّكِ الالتزامات ══════════════════════════════ */

    /* ⑨ الطبقاتُ الثلاث. */
    $sql = "INSERT INTO fin_obl_layers (company_id, code, seq, title, birth, rule_text, sides, doc_ref, active)
            VALUES (0,?,?,?,?,?,?,?,1)
            ON DUPLICATE KEY UPDATE seq=VALUES(seq), title=VALUES(title), birth=VALUES(birth),
              rule_text=VALUES(rule_text), sides=VALUES(sides), doc_ref=VALUES(doc_ref), active=1";
    $n = 0;
    foreach ($S['recognition_layers'] as $i => $x) {
        $vals = array($x['code'], $i + 1, mb_substr($x['title'], 0, 120), mb_substr($x['birth'], 0, 300),
                      mb_substr($x['rule'], 0, 400), mb_substr($x['sides'], 0, 500), $x['src']);
        assertArity($sql, 'sisssss', $vals, 'طبقة ' . $x['code']);
        if ($apply) { run($db, $sql, 'sisssss', $vals, 'طبقة ' . $x['code']); }
        $n++;
    }
    $stats['fin_obl_layers'] = $n;

    /* ⑩ اختبارُ التجنبِ الخماسي — بترتيبِه ولا يُقفز. */
    $sql = "INSERT INTO fin_obl_avoidance_tests (company_id, code, seq, question, outcome, doc_ref, active)
            VALUES (0,?,?,?,?,?,1)
            ON DUPLICATE KEY UPDATE seq=VALUES(seq), question=VALUES(question),
              outcome=VALUES(outcome), doc_ref=VALUES(doc_ref), active=1";
    $n = 0;
    foreach ($S['avoidance_tests'] as $i => $x) {
        $vals = array($x['code'], $i + 1, mb_substr($x['question'], 0, 300), mb_substr($x['outcome'], 0, 600), $x['src']);
        assertArity($sql, 'sisss', $vals, 'تجنب ' . $x['code']);
        if ($apply) { run($db, $sql, 'sisss', $vals, 'تجنب ' . $x['code']); }
        $n++;
    }
    $stats['fin_obl_avoidance_tests'] = $n;

    /* ⑪ أنواعُ الالتزامِ الثمانية — و`posts_entry` صفرٌ دائمًا (OR-10). */
    $sql = "INSERT INTO fin_obl_types
              (company_id, code, title, born_when, accounts, formula, term_rule, posts_entry, doc_ref, active)
            VALUES (0,?,?,?,?,?,?,0,?,1)
            ON DUPLICATE KEY UPDATE title=VALUES(title), born_when=VALUES(born_when),
              accounts=VALUES(accounts), formula=VALUES(formula), term_rule=VALUES(term_rule),
              posts_entry=0, doc_ref=VALUES(doc_ref), active=1";
    $n = 0;
    foreach ($S['obligation_types'] as $x) {
        $vals = array($x['code'], mb_substr($x['title'], 0, 160), mb_substr($x['created_at_event'], 0, 200),
                      mb_substr($x['accounts'], 0, 200), mb_substr($x['formula'], 0, 400),
                      mb_substr($x['term_rule'], 0, 400), $x['src']);
        assertArity($sql, 'sssssss', $vals, 'نوعُ التزام ' . $x['code']);
        if ($apply) { run($db, $sql, 'sssssss', $vals, 'نوعُ التزام ' . $x['code']); }
        $n++;
    }
    $stats['fin_obl_types'] = $n;

    /* ⑫ القواعدُ الخمسُ الأُسَر: OR · SY · AR · SR · IN. */
    $sql = "INSERT INTO fin_obl_rules (company_id, family, code, rule_text, accept_test, doc_ref, active)
            VALUES (0,?,?,?,?,?,1)
            ON DUPLICATE KEY UPDATE family=VALUES(family), rule_text=VALUES(rule_text),
              accept_test=VALUES(accept_test), doc_ref=VALUES(doc_ref), active=1";
    $n = 0;
    foreach (array('OR' => 'obligation_rules', 'SY' => 'symmetry_rules', 'AR' => 'accrual_rules',
                   'SR' => 'supplier_rules', 'IN' => 'inheritance') as $fam => $key) {
        foreach ($S[$key] as $x) {
            $vals = array($fam, $x['code'], mb_substr($x['rule'], 0, 700), mb_substr($x['test'], 0, 400), $x['src']);
            assertArity($sql, 'sssss', $vals, 'قاعدة ' . $x['code']);
            if ($apply) { run($db, $sql, 'sssss', $vals, 'قاعدة ' . $x['code']); }
            $n++;
        }
    }
    $stats['fin_obl_rules'] = $n;

    /* ⑬ التنبيهاتُ الاثنا عشر. */
    $sql = "INSERT INTO fin_obl_alerts
              (company_id, code, title, fires_when, destination, risk_if_ignored, lead_days, doc_ref, active)
            VALUES (0,?,?,?,?,?,?,?,1)
            ON DUPLICATE KEY UPDATE title=VALUES(title), fires_when=VALUES(fires_when),
              destination=VALUES(destination), risk_if_ignored=VALUES(risk_if_ignored),
              lead_days=VALUES(lead_days), doc_ref=VALUES(doc_ref), active=1";
    $n = 0;
    foreach ($S['alerts'] as $x) {
        /* ما يُطلق «قبلَ» الحدثِ له مهلةٌ · وما يُطلق «عند» وقوعِه مهلتُه صفر. */
        $lead = (mb_strpos($x['trigger'], 'قبل') !== false) ? 7 : 0;
        $vals = array($x['code'], mb_substr($x['title'], 0, 200), mb_substr($x['trigger'], 0, 300),
                      mb_substr($x['to'], 0, 300), mb_substr($x['risk'], 0, 400), $lead, $x['src']);
        assertArity($sql, 'sssssis', $vals, 'تنبيه ' . $x['code']);
        if ($apply) { run($db, $sql, 'sssssis', $vals, 'تنبيه ' . $x['code']); }
        $n++;
    }
    $stats['fin_obl_alerts'] = $n;

    /* ⑭ شروطُ الاعترافِ بمعيارِ كلِّ نوعِ عقد. */
    $sql = "INSERT INTO fin_obl_recognition
              (company_id, contract_kind, standard, trigger_text, layers_text, guard_text, doc_ref, active)
            VALUES (0,?,?,?,?,?,?,1)
            ON DUPLICATE KEY UPDATE standard=VALUES(standard), trigger_text=VALUES(trigger_text),
              layers_text=VALUES(layers_text), guard_text=VALUES(guard_text), doc_ref=VALUES(doc_ref), active=1";
    $n = 0;
    foreach ($S['recognition_conditions'] as $x) {
        $vals = array(mb_substr($x['kind'], 0, 120), mb_substr($x['standard'], 0, 200),
                      mb_substr($x['trigger'], 0, 300), mb_substr($x['layers'], 0, 700),
                      mb_substr($x['guard'], 0, 400), $x['src']);
        assertArity($sql, 'ssssss', $vals, 'اعتراف ' . mb_substr($x['kind'], 0, 20));
        if ($apply) { run($db, $sql, 'ssssss', $vals, 'اعتراف ' . mb_substr($x['kind'], 0, 20)); }
        $n++;
    }
    $stats['fin_obl_recognition'] = $n;

    /* ══ البندان ⑥ و⑦ — التصنيفُ الرباعيُّ والتوريث ══════════════════════ */

    /* ⑮ الأصنافُ الأربعة — والنصُّ من الوثيقةِ · وطريقةُ التعديلِ من حكمِ كلٍّ. */
    $dcMode = array(
        /* DC-1 «تُنشئه وتعدّله قبلَ الاعتماد» — الإدارةُ المالكةُ مباشرةً. */
        'DC-1' => array('', '', 'direct'),
        /* DC-2 «الإدارةُ لا تعدّله · تقترحه والماليةُ تحسمه». */
        'DC-2' => array('18,31', '18,31', 'proposal'),
        /* DC-3 «قراءةٌ للمراجعِ · والتعديلُ بملحقٍ موقَّعٍ لا بتحريرِ حقل». */
        'DC-3' => array('', '', 'amendment_only'),
        /* DC-4 «قراءةٌ · ولا يُعدَّل حدُّ ائتمانٍ إلا بقرارٍ ماليٍّ معتمد». */
        'DC-4' => array('', '32', 'decision_only'),
    );
    $sql = "INSERT INTO gov_data_classes
              (company_id, code, title, name_en, meaning, examples, owner_label,
               create_roles, edit_roles, read_roles, edit_mode, doc_ref, active)
            VALUES (0,?,?,?,?,?,?,?,?,'',?,?,1)
            ON DUPLICATE KEY UPDATE title=VALUES(title), name_en=VALUES(name_en),
              meaning=VALUES(meaning), examples=VALUES(examples), owner_label=VALUES(owner_label),
              create_roles=VALUES(create_roles), edit_roles=VALUES(edit_roles),
              edit_mode=VALUES(edit_mode), doc_ref=VALUES(doc_ref), active=1";
    $n = 0;
    foreach ($S['data_classes'] as $x) {
        $m = isset($dcMode[$x['code']]) ? $dcMode[$x['code']] : array('', '', 'direct');
        $vals = array($x['code'], mb_substr($x['title'], 0, 120), mb_substr($x['name_en'], 0, 120),
                      mb_substr($x['meaning'], 0, 400), mb_substr($x['examples'], 0, 700),
                      mb_substr($x['owner'], 0, 200), $m[0], $m[1], $m[2], $x['src']);
        assertArity($sql, 'ssssssssss', $vals, 'صنف ' . $x['code']);
        if ($apply) { run($db, $sql, 'ssssssssss', $vals, 'صنف ' . $x['code']); }
        $n++;
    }
    $stats['gov_data_classes'] = $n;

    /* ⑯ التوريث: الحقولُ التي تسميها الوثيقةُ بالاسمِ في IN-04 وIN-05.
       IN-04 «الاستحقاقُ يُوَرَّث من عقدِ العميلِ **ثلاثةَ عشرَ** حقلًا»
       IN-05 «الالتزامُ يُوَرَّث من عقدِ الموردِ **أحدَ عشرَ** حقلًا» */
    $inherit = array(
        // child_entity, child_field, parent_entity, parent_field, label
        array('accrual', 'customer_id',    'client_contract', 'customer_id',    'العميل'),
        array('accrual', 'contract_id',    'client_contract', 'id',             'العقد'),
        array('accrual', 'work_model',     'client_contract', 'work_model',     'نموذجُ العمل'),
        array('accrual', 'unit_price',     'client_contract', 'unit_price',     'سعرُ الوحدة'),
        array('accrual', 'currency',       'client_contract', 'currency',       'العملة'),
        array('accrual', 'fx_rate',        'client_contract', 'fx_rate',        'سعرُ الصرف'),
        array('accrual', 'project_id',     'client_contract', 'project_id',     'المشروع'),
        array('accrual', 'site_id',        'client_contract', 'site_id',        'الموقع'),
        array('accrual', 'equipment_id',   'client_contract', 'equipment_id',   'المعدة'),
        array('accrual', 'cost_center',    'client_contract', 'cost_center',    'مركزُ التكلفة'),
        array('accrual', 'payment_terms',  'client_contract', 'payment_terms',  'شروطُ الدفع'),
        array('accrual', 'credit_limit',   'client_contract', 'credit_limit',   'حدُّ الائتمان'),
        array('accrual', 'penalties',      'client_contract', 'penalties',      'الجزاءات'),

        array('obligation', 'supplier_id',        'supplier_contract', 'supplier_id',        'المورد'),
        array('obligation', 'supplier_contract_id', 'supplier_contract', 'id',               'عقدُ المورد'),
        array('obligation', 'served_client_contract_id', 'supplier_contract', 'client_contract_id', 'عقدُ العميلِ المخدوم'),
        array('obligation', 'container_id',       'supplier_contract', 'container_id',       'الحاوية'),
        array('obligation', 'work_model',         'supplier_contract', 'work_model',         'نموذجُ العمل'),
        array('obligation', 'unit_price',         'supplier_contract', 'unit_price',         'سعرُ الوحدة'),
        array('obligation', 'currency',           'supplier_contract', 'currency',           'العملة'),
        array('obligation', 'payment_terms',      'supplier_contract', 'payment_terms',      'شروطُ الدفع'),
        array('obligation', 'equipment_id',       'supplier_contract', 'equipment_id',       'المعدة'),
        array('obligation', 'site_id',            'supplier_contract', 'site_id',            'الموقع'),
        array('obligation', 'financier_contract_type', 'supplier_contract', 'financier_contract_type', 'نوعُ عقدِ الممول'),

        /* IN-07: «التايم شيتُ يُوَرَّث الحصةَ المتعاقدةَ ويُدخِل المنفَّذَ وحدَه». */
        array('timesheet', 'contracted_quota', 'client_contract', 'monthly_quota', 'الحصةُ المتعاقدة'),
        array('timesheet', 'unit_price',       'client_contract', 'unit_price',    'سعرُ الوحدة'),
        array('timesheet', 'work_model',       'client_contract', 'work_model',    'نموذجُ العمل'),

        /* IN-06: «الفاتورةُ تُوَرَّث من الاستحقاقِ والاستحقاقُ من العقد — سلسلةٌ ثلاثيةٌ لا تُقطع». */
        array('invoice', 'accrual_id',   'accrual', 'id',           'الاستحقاق'),
        array('invoice', 'amount',       'accrual', 'amount',       'قيمةُ الاستحقاق'),
        array('invoice', 'customer_id',  'accrual', 'customer_id',  'العميل'),
        array('invoice', 'contract_id',  'accrual', 'contract_id',  'العقد'),
    );
    $inhDoc = array('accrual' => 'OBL-0108', 'obligation' => 'OBL-0109',
                    'timesheet' => 'OBL-0111', 'invoice' => 'OBL-0110');
    $sql = "INSERT INTO gov_field_inheritance
              (company_id, child_entity, child_field, parent_entity, parent_field, label_ar,
               readonly, on_parent_change, doc_ref, active)
            VALUES (0,?,?,?,?,?,1,'cascade_if_draft',?,1)
            ON DUPLICATE KEY UPDATE parent_entity=VALUES(parent_entity),
              parent_field=VALUES(parent_field), label_ar=VALUES(label_ar),
              readonly=1, doc_ref=VALUES(doc_ref), active=1";
    $n = 0;
    foreach ($inherit as $r) {
        $vals = array($r[0], $r[1], $r[2], $r[3], $r[4], isset($inhDoc[$r[0]]) ? $inhDoc[$r[0]] : '');
        assertArity($sql, 'ssssss', $vals, 'توريث ' . $r[0] . '.' . $r[1]);
        if ($apply) { run($db, $sql, 'ssssss', $vals, 'توريث ' . $r[0] . '.' . $r[1]); }
        $n++;
    }
    $stats['gov_field_inheritance'] = $n;

    /* ⑯-ب اختصاصاتُ المراجعةِ العشرون وصلاحياتُها الاثنتا عشرة. */
    $sql = "INSERT INTO iaf_competencies (company_id, code, seq, title, accept_test, doc_ref, active)
            VALUES (0,?,?,?,?,?,1)
            ON DUPLICATE KEY UPDATE seq=VALUES(seq), title=VALUES(title),
              accept_test=VALUES(accept_test), doc_ref=VALUES(doc_ref), active=1";
    $n = 0;
    foreach ($S['iaf_competencies'] as $x) {
        $vals = array($x['code'], $x['seq'], mb_substr($x['title'], 0, 300), mb_substr($x['test'], 0, 400), $x['src']);
        assertArity($sql, 'sisss', $vals, 'اختصاص ' . $x['code']);
        if ($apply) { run($db, $sql, 'sisss', $vals, 'اختصاص ' . $x['code']); }
        $n++;
    }
    $stats['iaf_competencies'] = $n;

    $sql = "INSERT INTO iaf_authorities (company_id, code, seq, title, mode, accept_test, doc_ref, active)
            VALUES (0,?,?,?,?,?,?,1)
            ON DUPLICATE KEY UPDATE seq=VALUES(seq), title=VALUES(title), mode=VALUES(mode),
              accept_test=VALUES(accept_test), doc_ref=VALUES(doc_ref), active=1";
    $n = 0;
    foreach ($S['iaf_authorities'] as $x) {
        $vals = array($x['code'], $x['seq'], mb_substr($x['title'], 0, 300), $x['mode'],
                      mb_substr($x['test'], 0, 400), $x['src']);
        assertArity($sql, 'sissss', $vals, 'صلاحية ' . $x['code']);
        if ($apply) { run($db, $sql, 'sissss', $vals, 'صلاحية ' . $x['code']); }
        $n++;
    }
    $stats['iaf_authorities'] = $n;

    /* ⑰ الشاشاتُ الحاكمةُ — من بيانِ الشاشاتِ الواحدِ لا من قائمةٍ هنا.
       «الحاكمة» = ما يحمل عقدًا أو التزامًا أو استحقاقًا أو اعتمادًا — أي ما
       يُنتج أثرًا ماليًّا أو قانونيًّا أو ائتمانيًّا. وكلُّ شاشةٍ في البيانِ
       حاكمةٌ بحكمِ مصدرِها، ولها `owner_doc` و`doc_ref` يُثبتان أساسَها. */
    require_once $ROOT . '/tools/u13_screens_manifest.php';
    $MAN = u13_screens_manifest();
    $sql = "INSERT INTO gov_governing_screens
              (company_id, screen_code, title_ar, file_path, why_governing, owner_doc, active)
            VALUES (0,?,?,?,?,?,1)
            ON DUPLICATE KEY UPDATE title_ar=VALUES(title_ar), file_path=VALUES(file_path),
              why_governing=VALUES(why_governing), owner_doc=VALUES(owner_doc), active=1";
    $whyFor = array(
        'registered' => 'مسجَّلةٌ بالاسمِ والملفِّ والأعمدةِ في السجلِّ الذريِّ للوثيقة',
        'named'      => 'مسمّاةٌ نصًّا في الوثيقةِ بلا ملفٍّ — والملفُّ اجتهادٌ موثَّق',
        'derived'    => 'مشتقةٌ من اختصاصٍ ذريٍّ بعينِه لأن الوثيقةَ تعلن عددًا بلا سجلّ',
    );
    $n = 0;
    foreach ($MAN as $sc) {
        $why = ($whyFor[$sc['basis']] ?? '') . ' — ' . $sc['doc_ref'];
        $vals = array($sc['code'], mb_substr($sc['title'], 0, 200),
                      mb_substr($sc['dir'] . '/' . $sc['file'], 0, 160),
                      mb_substr($why, 0, 300), mb_substr($sc['doc'], 0, 40));
        assertArity($sql, 'sssss', $vals, 'شاشةٌ حاكمة ' . $sc['code']);
        if ($apply) { run($db, $sql, 'sssss', $vals, 'شاشةٌ حاكمة ' . $sc['code']); }
        $n++;
    }
    /* ◆ البيانُ يملك المجموعةَ كاملةً: ما خرج منه يُعطَّل ولا يُحذف — فالسجلُّ
         يبقى للتدقيقِ ويُعرف أن شاشةً كانت حاكمةً ثم لم تعد. */
    if ($apply) {
        $keep = array();
        foreach ($MAN as $sc) { $keep[] = "'" . $db->real_escape_string($sc['code']) . "'"; }
        $db->query("UPDATE gov_governing_screens SET active = 0
                     WHERE company_id = 0 AND screen_code NOT IN (" . implode(',', $keep) . ")");
        $db->query("UPDATE gov_field_class SET active = 0
                     WHERE company_id = 0 AND screen_code NOT IN (" . implode(',', $keep) . ")");
    }
    $stats['gov_governing_screens'] = $n;

    /* ⑱ وسمُ حقولِ الشاشاتِ الحاكمة. الأعمدةُ الإلزاميةُ التي تسميها الوثيقةُ
       بالاسمِ تُصنَّف بصنفِها المستحق: أعمدةُ التجنبِ الاثنا عشرَ قانونيةٌ لأنها
       «ما يحتاجه المراجعُ القانونيُّ لإثباتِ صحةِ الالتزامِ ونفاذِه» (DC-3)،
       وأعمدةُ الجدولِ الثلاثةَ عشرَ ماليةُ الأثرِ لأنها «ما يُنتج قيدًا أو
       التزامًا أو ذمة» (DC-2). */
    $fieldClass = array();
    foreach (array(
        'cancellable' => 'أالعقدُ قابلٌ للإلغاءِ من طرفنا؟',
        'cancel_cost' => 'تكلفةُ الإلغاءِ أو الشرطُ الجزائي',
        'unavoidable' => 'المبلغُ غيرُ القابلِ للتجنب',
        'unavoidable_pct' => 'نسبتُه من قيمةِ العقد',
        'volume_obligation' => 'التزامُ الحجمِ — يسقط بالعجز',
        'penalty_obligation' => 'التزامُ الجزاءِ — لا يسقط',
        'recognition_candidate' => 'أمرشَّحٌ للاعتراف؟',
        'onerous' => 'أعقدٌ مُثقِلٌ؟',
        'special_standard' => 'المعيارُ الخاصُّ الموجِبُ للاعتراف',
        'verdict' => 'نتيجةُ اختبارِ التجنب',
        'decided_at' => 'تاريخُ نتيجةِ الاختبار',
        'next_review_at' => 'المراجعةُ القادمةُ للنتيجة',
    ) as $f => $lbl) { $fieldClass[] = array('fin_obl_avoidance', $f, $lbl, 'DC-3', 0); }

    foreach (array(
        'l1_commitment' => 'الارتباطُ التعاقديُّ — القيمةُ الكلية',
        'l1_remaining'  => 'الارتباطُ المتبقي غيرُ المنفَّذ',
        'l2_recognized' => 'المعترَفُ به في الفترة',
        'l2_cumulative' => 'المعترَفُ به تراكميًّا',
        'l3_open'       => 'الذمةُ القائمةُ — مدينةٌ أو دائنة',
        'settled'       => 'المسدَّدُ أو المحصَّل',
        'gap_l1_l2'     => 'الفرقُ بين الارتباطِ والمعترَفِ به',
        'recognition_rule' => 'شرطُ الاعترافِ المطبَّقُ ومعيارُه',
        'is_partial'    => 'أفترةٌ كسرية؟',
        'proration_basis' => 'أساسُ حسابِ الكسر',
        'term_class'    => 'التصنيفُ قصيرٌ أو طويل',
        'due_date'      => 'تاريخُ الاستحقاقِ بيومه',
        'period_no'     => 'تسلسلُ الفترة',
    ) as $f => $lbl) { $fieldClass[] = array('ob_schedule', $f, $lbl, 'DC-2', 0); }

    /* سلسلةُ الاعتمادِ ماليةُ الأثرِ — والسقفُ ائتمانيٌّ يقرأه الممول. */
    foreach (array(
        array('apr_code', 'نوعُ الاعتماد', 'DC-2', 0),
        array('decision', 'القرار', 'DC-2', 0),
        array('actor_user_id', 'الفاعل', 'DC-2', 0),
        array('actor_capacity', 'الصفةُ التي اعتُمد بها', 'DC-3', 0),
        array('amount', 'المبلغ', 'DC-2', 0),
        array('cap_at_decision', 'السقفُ لحظةَ القرار', 'DC-4', 1),
        array('reason_code', 'رمزُ سببِ الرفض', 'DC-2', 0),
        array('decided_at', 'تاريخُ القرار', 'DC-2', 0),
    ) as $f) { $fieldClass[] = array('fin_approval_chain', $f[0], $f[1], $f[2], $f[3]); }

    /* موافقاتُ التكليفِ رقابيةٌ — والتعارضُ حقلٌ قانونيٌّ يقرؤه المراجع. */
    foreach (array(
        array('subject_user_id', 'المكلَّف', 'DC-1', 0),
        array('role_id', 'المسمّى المكلَّفُ به', 'DC-1', 0),
        array('assignment_kind', 'نوعُ التكليف', 'DC-1', 0),
        array('conflict_state', 'حالةُ تعارضِ الواجبات', 'DC-3', 0),
        array('conflict_detail', 'تفصيلُ التعارض', 'DC-3', 1),
        array('state', 'حالةُ السريان', 'DC-3', 0),
        array('decided_by', 'من قرر', 'DC-3', 0),
        array('authority_ref', 'مرجعُ الموافقة', 'DC-3', 0),
    ) as $f) { $fieldClass[] = array('exec_assignments', $f[0], $f[1], $f[2], $f[3]); }

    /* ⑱-ب استكمالُ الوسمِ لبقيةِ أعمدةِ الشاشاتِ الحاكمة.
       OBL-0052 يوجب صنفًا لكلِّ حقلٍ **لا للمسمّى في الوثيقةِ وحدَه** — فالباقي
       يُصنَّف باشتقاقٍ من تعريفِ كلِّ صنفٍ في §٤-١٧ لا بالتخمين:

         DC-3 قانونيّ  — «ما يحتاجه المراجعُ القانونيُّ لإثباتِ صحةِ الالتزامِ
                          ونفاذِه وحجيتِه»: أسماءُ الطرفين وصفتاهما · الإنهاءُ
                          والتجديدُ · الملاحقُ ومراجعُها · التوقيعاتُ وتواريخُها.
         DC-4 ائتمانيّ — «ما يحتاجه المراجعُ الائتمانيُّ أو الممولُ لتقديرِ
                          الجدارةِ والتعرض»: الحدودُ والتقديراتُ والتغطية.
         DC-1 تشغيليّ  — «ما تُنشئه الإدارةُ وتحتاجه لعملها اليومي»: المشروعُ
                          والموقعُ والمعدةُ والملاحظاتُ الميدانية.
         DC-2 ماليُّ الأثر — «ما يُنتج قيدًا أو التزامًا أو ذمةً أو أثرًا في
                          القوائم» — وهو الأصلُ لما لا ينطبق عليه ما سبق.

       ◆ وحدُّ هذا الحاكم: يحرس **تحريرَ المستخدمِ** للحقل لا كتابةَ الخدمةِ له.
         فالخدمةُ تكتب `state` عبرَ بابِها المحروسِ (decide/record)، والمستخدمُ
         لا يحرّره يدويًّا — وهذا بعينُه معنى «التعديلُ بملحقٍ لا بتحريرِ حقل». */
    $derive = function ($table, $col) {
        /* قانونيّ (DC-3): هويةُ الطرفِ · مدةُ الالتزامِ · ملاحقُه · التوقيعاتُ
           وتواريخُها · الإنهاءُ والتجديد · وكلُّ ما يُثبت الحجيةَ والاستقلال. */
        $legal = array('contract_kind', 'contract_ref', 'counterparty', 'party_type', 'party_id',
                       'start_date', 'end_date', 'terminated_at', 'supersedes_id', 'amendment_ref',
                       'assignment_no', 'checked_at', 'decided_at', 'decided_by', 'decision_reason',
                       'effective_from', 'effective_to', 'revoked_at', 'revoke_reason',
                       'actor_role_id', 'steps_json', 'state', 'authority_ref',
                       /* المراجعةُ الداخلية: الاستقلالُ والدليلُ والإغلاقُ حجيةٌ قانونية */
                       'functional_line', 'admin_line', 'independence', 'not_following', 'purpose',
                       'authority', 'approved_by', 'approved_at', 'version',
                       'has_conflict', 'conflict_note', 'conflict_state', 'conflict_detail',
                       'valid_until', 'declared_at', 'scope_ref', 'auditor_id',
                       'evidence_ref', 'evidence_accepted', 'accepted_by', 'closed_by', 'closed_at',
                       'escalated_at', 'escalated_to', 'raised_by', 'raised_at', 'responded_by',
                       'responded_at', 'lead_auditor', 'issued_by', 'issued_at', 'delivery_path',
                       'received_at', 'read_at', 'frozen', 'evidence_hash', 'captured_by', 'captured_at',
                       'wp_ref', 'finding_no', 'engagement_no', 'report_no', 'review_no', 'charter_id',
                       'reviewed_by', 'reviewed_at', 'conformance', 'mode', 'severity',
                       'old_role_id', 'old_role_name', 'new_role_id', 'new_spec_code',
                       'variance_code', 'doc_code', 'declared_where', 'declared_value',
                       'registered_where', 'registered_value', 'resolution', 'resolved_value',
                       'basis', 'owner_action', 'why', 'func_a', 'func_b', 'roles_a', 'roles_b',
                       'severity_level', 'enforced_by', 'scope', 'source_shown', 'attempted_by',
                       'denied_at', 'accessed_at', 'purpose_note', 'limit_rule');
        /* ائتمانيّ (DC-4): التقديرُ والتعرضُ والسقفُ والتغطية. */
        $credit = array('expected_benefit', 'max_amount', 'cap_amount', 'escalates_to_role',
                        'closure_rate', 'risk_score', 'overdue_escalated', 'review_limit_usd');
        /* تشغيليّ (DC-1): ما تُنشئه الإدارةُ وتحتاجه لعملها اليومي. */
        $oper   = array('project_id', 'site_id', 'equipment_id', 'subject_name', 'role_name',
                        'scope_note', 'requested_by', 'requested_at', 'subject_user_id', 'role_id',
                        'assignment_kind', 'area_code', 'area_name', 'owner_dept', 'auditee_dept',
                        'auditee_user_id', 'title', 'detail', 'summary', 'scope_label', 'period_label',
                        'plan_year', 'plan_id', 'engagement_id', 'audit_kind', 'started_at', 'ended_at',
                        'employee_id', 'admin_module', 'specialization', 'spec_code', 'finance_unit_id',
                        'item_type', 'source_type', 'source_screen', 'org_unit_id', 'assigned_user_id',
                        'assigned_role_id', 'owner_user_id', 'due_at', 'priority', 'deliverable',
                        'details', 'last_audited', 'response_text', 'action_plan', 'action_owner',
                        'action_due', 'response_due', 'name_ar', 'name_en', 'scope_note2');
        if (in_array($col, $credit, true)) { return 'DC-4'; }
        if (in_array($col, $legal, true))  { return 'DC-3'; }
        if (in_array($col, $oper, true))   { return 'DC-1'; }
        return 'DC-2';   // الأصل: ماليُّ الأثر
    };
    /* الشاشةُ الحاكمةُ ↔ جدولُها الحاملُ للحقول — من البيانِ الواحدِ لا بيدٍ.
       وشاشتان تقرآن الجدولَ نفسَه (مثل الملاحظاتِ وردودِها) تُوسَمان كلتاهما،
       فالمنظرُ يختلف والحقلُ واحد. */
    $tableFor = array();
    foreach ($MAN as $sc) { $tableFor[$sc['code']] = $sc['table']; }
    $tech = array('id', 'company_id', 'created_at', 'updated_at', 'created_by', 'updated_by',
                  'is_deleted', 'deleted_at', 'deleted_by', 'active', 'doc_ref', 'sort_order');
    /* ◆ العنوانُ المعروض: تعليقُ العمودِ صالحٌ عنوانًا **إن كان عنوانًا** — وكثيرٌ
         منه تلميحُ قيمةٍ («ACC-01..ACC-10») أو مرجعُ حكمٍ («OR-10 — …») لا اسمٌ
         يُقرأ في ترويسةِ جدول. فالقاموسُ أولًا، ثم التعليقُ إن صلح، ثم الاسم. */
    $lex = array(
        'code' => 'الرمز', 'seq' => 'الترتيب', 'name_ar' => 'الاسم', 'name_en' => 'الاسم بالإنجليزية',
        'title' => 'العنوان', 'title_ar' => 'العنوان', 'label_ar' => 'العنوان', 'state' => 'الحالة',
        'status' => 'الحالة', 'kind' => 'النوع', 'mode' => 'الوضع', 'family' => 'الأسرة',
        'rule_text' => 'نصُّ الحكم', 'accept_test' => 'اختبارُ القبول', 'why' => 'لماذا',
        'basis' => 'أساسُ الحسم', 'impact' => 'الأثر', 'summary' => 'الخلاصة', 'detail' => 'التفصيل',
        'note' => 'ملاحظة', 'notes' => 'ملاحظات', 'purpose' => 'الغرض', 'scope' => 'النطاق',
        'scope_note' => 'النطاقُ المعلَن', 'scope_label' => 'النطاق', 'scope_ref' => 'مرجعُ النطاق',
        'currency' => 'العملة', 'amount' => 'المبلغ', 'total_value' => 'القيمةُ الكلية',
        'contract_ref' => 'مرجعُ العقد', 'contract_kind' => 'نوعُ العقد', 'counterparty' => 'الطرفُ الآخر',
        'start_date' => 'تاريخُ البدء', 'end_date' => 'تاريخُ الانتهاء', 'due_date' => 'تاريخُ الاستحقاق',
        'decided_at' => 'تاريخُ القرار', 'decided_by' => 'من قرر', 'decision' => 'القرار',
        'created_at2' => 'أُنشئ في', 'reviewed_at' => 'تاريخُ التقييم', 'reviewed_by' => 'الجهةُ المقيِّمة',
        'raised_at' => 'تاريخُ الرفع', 'raised_by' => 'من رفعها', 'closed_at' => 'تاريخُ الإغلاق',
        'closed_by' => 'من أغلقها', 'severity' => 'الخطورة', 'risk_score' => 'درجةُ الخطر',
        'plan_year' => 'سنةُ الخطة', 'area_code' => 'رمزُ النطاق', 'area_name' => 'اسمُ النطاق',
        'owner_dept' => 'الإدارةُ المالكة', 'auditee_dept' => 'الإدارةُ المُراجَعة',
        'employee_id' => 'الموظف', 'role_id' => 'الدور', 'role_name' => 'المسمّى',
        'subject_name' => 'المكلَّف', 'requested_at' => 'تاريخُ الطلب', 'requested_by' => 'من طلب',
        'issued_at' => 'تاريخُ الإصدار', 'issued_by' => 'من أصدره', 'version' => 'الإصدار',
        'spec_code' => 'التخصص', 'admin_module' => 'الإدارةُ المخدومة', 'specialization' => 'التخصصُ النصي',
        'finance_unit_id' => 'وحدةُ المالية', 'period_no' => 'رقمُ الفترة',
        'period_start' => 'بدايةُ الفترة', 'period_end' => 'نهايةُ الفترة',
        'accessed_at' => 'وقتُ الاطّلاع', 'auditor_id' => 'المراجع', 'declared_at' => 'تاريخُ الإقرار',
        'valid_until' => 'سارٍ حتى', 'next_due' => 'الاستحقاقُ القادم', 'conformance' => 'درجةُ المطابقة',
        'findings_count' => 'عددُ الملاحظات', 'findings_total' => 'إجماليُّ الملاحظات',
        'findings_critical' => 'الحرجةُ منها', 'closure_rate' => 'نسبةُ الإغلاق',
        'wp_ref' => 'مرجعُ الورقة', 'evidence_hash' => 'بصمةُ الدليل', 'captured_at' => 'وقتُ الالتقاط',
        'captured_by' => 'من التقطها', 'frozen' => 'مجمَّدة', 'engagement_no' => 'رقمُ المهمة',
        'finding_no' => 'رقمُ الملاحظة', 'report_no' => 'رقمُ التقرير', 'review_no' => 'رقمُ التقييم',
        'obligation_no' => 'رقمُ الالتزام', 'assignment_no' => 'رقمُ التكليف',
        'variance_code' => 'رمزُ المخالفة', 'doc_code' => 'الوثيقة', 'subject' => 'الموضوع',
        'resolution' => 'الحسم', 'resolved_value' => 'المعتمَد', 'owner_action' => 'ما يلزم المالك',
        'old_role_name' => 'الدورُ القديم', 'new_spec_code' => 'التخصصُ الجديد',
        'holders_before' => 'حاملون قبل', 'holders_moved' => 'المرحَّلون',
        'func_a' => 'الوظيفةُ الأولى', 'func_b' => 'لا تُجمع مع', 'roles_a' => 'أدوارُ الأولى',
        'roles_b' => 'أدوارُ الثانية', 'severity_level' => 'الشدة', 'enforced_by' => 'من يُنفذه',
        'max_amount' => 'السقف', 'scope_kind' => 'نوعُ النطاق', 'apr_code' => 'نوعُ الاعتماد',
        'authority_ref' => 'مرجعُ التفويض', 'effective_from' => 'سارٍ من', 'effective_to' => 'سارٍ إلى',
        'source_kind' => 'نوعُ المستند', 'source_ref' => 'مرجعُ المستند', 'reason_code' => 'رمزُ السبب',
        'reason_note' => 'بيانُ السبب', 'fired_at' => 'وقتُ الإطلاق', 'to_label' => 'الوجهة',
        'notice_code' => 'رمزُ المرتجَع', 'trigger_ar' => 'المُطلِق', 'trigger_key' => 'مفتاحُ الحدث',
        'source_dept' => 'الإدارةُ المصدر', 'launch_cond' => 'شرطُ الإطلاق',
        'target_spec' => 'التخصصُ الوجهة', 'target_label' => 'الوجهة', 'accounts' => 'الحسابات',
        'dims' => 'الأبعاد', 'chain' => 'السلسلة', 'guard_rule' => 'الحكمُ الحارس',
        'limit_rule' => 'حدُّه — ما لا يملكه', 'proration_basis' => 'أساسُ حسابِ الكسر',
        // ── تتمةُ القاموس: كلُّ عمودٍ يظهر للمراجعِ يحتاج عنوانًا يُقرأ ──────
        'accepted_at' => 'وقتُ قبولِ الدليل', 'action_due' => 'مهلةُ المعالجة',
        'action_owner' => 'مالكُ المعالجة', 'action_plan' => 'خطةُ المعالجة',
        'actor_role_id' => 'دورُ الفاعل', 'actor_user_id' => 'الفاعل',
        'admin_line' => 'الارتباطُ الإداري', 'amendment_ref' => 'مرجعُ التعديل',
        'approved_at' => 'تاريخُ الاعتماد', 'approved_by' => 'من اعتمده',
        'assigned_role_id' => 'الدورُ المستلِم', 'audit_kind' => 'نوعُ المراجعة',
        'auditee_user_id' => 'مسؤولُ الإدارةِ المُراجَعة', 'authority' => 'السلطة',
        'charter_id' => 'الميثاق', 'checked_at' => 'وقتُ الفحصِ الآلي',
        'close_reason' => 'سببُ الإغلاق', 'completed_at' => 'وقتُ الإنجاز',
        'conflict_detail' => 'تفصيلُ التعارض', 'conflict_note' => 'بيانُ التعارض',
        'conflict_state' => 'حالةُ التعارض', 'contract_value' => 'قيمةُ العقد',
        'cost_center' => 'مركزُ التكلفة', 'decision_reason' => 'سببُ القرار',
        'declared_value' => 'المعلَن', 'details' => 'التفصيل', 'due_at' => 'الموعد',
        'ended_at' => 'تاريخُ الانتهاء', 'engagement_id' => 'المهمة',
        'equipment_id' => 'المعدة', 'escalated_at' => 'وقتُ التصعيد',
        'escalated_to' => 'صُعِّد إلى', 'escalation_level' => 'درجةُ التصعيد',
        'event_ref' => 'مرجعُ الحدث', 'evidence_ref' => 'مرجعُ الدليل',
        'functional_line' => 'الارتباطُ الوظيفي', 'generated_at' => 'وقتُ التوليد',
        'generated_by' => 'من ولّده', 'has_conflict' => 'أيوجد تعارض؟',
        'independence' => 'الاستقلال', 'last_audited' => 'آخرُ مراجعة',
        'lead_auditor' => 'المراجعُ المسؤول', 'month_days' => 'أيامُ الشهر',
        'moved_at' => 'وقتُ الترحيل', 'not_following' => 'ما لا يتبعه',
        'ob_type' => 'نوعُ الالتزام', 'obligation_id' => 'الالتزام',
        'old_role_id' => 'رقمُ الدورِ القديم', 'org_unit_id' => 'الوحدةُ التنظيمية',
        'overall_opinion' => 'الرأيُ العام', 'overdue_escalated' => 'المتأخرُ المصعَّد',
        'partial_days' => 'أيامُ الكسر', 'party_id' => 'الطرف', 'party_type' => 'نوعُ الطرف',
        'period_label' => 'الفترة', 'plan_id' => 'الخطة', 'priority' => 'الأولوية',
        'project_id' => 'المشروع', 'read_at' => 'وقتُ القراءة',
        'reclassified_at' => 'وقتُ إعادةِ التصنيف', 'registered_value' => 'المسجَّل',
        'responded_at' => 'تاريخُ الرد', 'responded_by' => 'من ردّ',
        'response_due' => 'مهلةُ الرد', 'response_text' => 'نصُّ الرد',
        'revoke_reason' => 'سببُ السحب', 'revoked_at' => 'تاريخُ السحب',
        'side' => 'الجانب', 'site_id' => 'الموقع', 'sla_paused_at' => 'وقتُ إيقافِ المهلة',
        'source_type' => 'نوعُ المصدر', 'started_at' => 'تاريخُ البدء',
        'supersedes_id' => 'يحلُّ محلَّ', 'terminated_at' => 'تاريخُ الإنهاء',
        'to_role_id' => 'الدورُ الوجهة', 'to_user_id' => 'المستلِم',
        'work_item_id' => 'المهمةُ المولَّدة',
        // ── سجلاتُ التغطيةِ التي أضافها الفاحصُ العكسي ──────────────────────
        'coverage_kind' => 'نوعُ الأثر', 'coverage_note' => 'بيانُ التغطية',
        'covered_by' => 'الأثرُ الحيُّ المنفِّذ', 'cycle_kind' => 'الدورة',
        'stage_ar' => 'المرحلة', 'owner_hint' => 'مالكُ المرحلة',
        'dept_name' => 'الإدارة', 'propagated' => 'أحكامٌ منتشرة',
        'dept_total' => 'إجماليُّ أحكامِها', 'doors_note' => 'الأبوابُ الماسّة',
        'enforce_kind' => 'نوعُ الإنفاذ', 'enforced_by' => 'المُنفِذُ الحي',
        'forbidden' => 'الفعلُ الممنوع', 'subject_role' => 'من لا يملكه',
        'role_ids' => 'أدوارُه', 'source_sql' => 'مصدرُ الحساب',
        'threshold' => 'الحد', 'owner_role' => 'المالك', 'cadence' => 'دوريةُ القياس',
        'item_code' => 'رمزُ البند', 'family' => 'العائلة',
    );
    /* التعليقُ يصلح عنوانًا ما لم يبدأ برمزٍ أو يحمل «..» (مدى قيم) أو «—» تفسيريًّا. */
    $commentIsLabel = function ($m) {
        $m = trim(str_replace('◆', '', (string) $m));
        if ($m === '') { return false; }
        if (preg_match('~^(?:[A-Z]{2,6}-\d|\d)~', $m)) { return false; }
        if (mb_strpos($m, '..') !== false) { return false; }
        if (mb_strlen($m) > 60) { return false; }
        return true;
    };
    $already = array();
    foreach ($fieldClass as $f) { $already[$f[0] . '.' . $f[1]] = 1; }
    foreach ($tableFor as $screen => $table) {
        $r = $db->query("SELECT COLUMN_NAME c, COLUMN_COMMENT m FROM information_schema.COLUMNS
                          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '"
                        . $db->real_escape_string($table) . "' ORDER BY ORDINAL_POSITION");
        while ($r && $x = $r->fetch_assoc()) {
            $col = $x['c'];
            if (in_array($col, $tech, true)) { continue; }
            if (isset($already[$screen . '.' . $col])) { continue; }
            if (isset($lex[$col]))                 { $lbl = $lex[$col]; }
            elseif ($commentIsLabel($x['m']))      { $lbl = trim(str_replace('◆', '', (string) $x['m'])); }
            else                                   { $lbl = $col; }
            $fieldClass[] = array($screen, $col, mb_substr($lbl, 0, 160), $derive($table, $col), 0);
        }
    }

    $sql = "INSERT INTO gov_field_class
              (company_id, screen_code, field_key, label_ar, dc_code, is_sensitive, doc_ref, active)
            VALUES (0,?,?,?,?,?,'OBL-0052',1)
            ON DUPLICATE KEY UPDATE label_ar=VALUES(label_ar), dc_code=VALUES(dc_code),
              is_sensitive=VALUES(is_sensitive), active=1";
    $n = 0;
    foreach ($fieldClass as $f) {
        $vals = array($f[0], $f[1], mb_substr($f[2], 0, 160), $f[3], (int) $f[4]);
        assertArity($sql, 'ssssi', $vals, 'حقل ' . $f[0] . '.' . $f[1]);
        if ($apply) { run($db, $sql, 'ssssi', $vals, 'حقل ' . $f[0] . '.' . $f[1]); }
        $n++;
    }
    $stats['gov_field_class'] = $n;

    /* ══ حسمُ مخالفاتِ الوثائقِ — بأساسٍ مكتوبٍ لا بصمت ═══════════════════ */
    /* ◆ المبدأُ الحاكمُ للحسمِ كلِّه:
         **السجلُّ الذريُّ يغلب الترويسة** — لأن الترويسةَ رقمٌ يُقرأ والسجلَّ نصٌّ
         يُختبَر. وشرطُ القبولِ في كلِّ وثيقةٍ «لكلٍّ شاهدُ قبولٍ مكتوب»، والرقمُ
         بلا نصٍّ لا شاهدَ له فلا يُبنى عليه.
         وحيث لا سجلَّ أصلًا (خمسُ وثائق) فالاشتقاقُ من الاختصاصاتِ الذريةِ
         نفسِها — وهي نصٌّ مُختبَر — لا من الرقمِ المعلَن. */
    $resolutions = array(
        'شاشاتٌ معلَنةٌ بلا سجلٍّ ذريّ' => array(
            'derive',
            'الرقمُ المعلَنُ في الترويسةِ بلا سجلٍّ ذريٍّ يسمّي الشاشاتِ ولا يحدد أعمدتَها — '
          . 'ولا يُبنى على رقمٍ بلا نصّ. فالشاشاتُ تُشتقُّ من **الاختصاصاتِ الذريةِ** '
          . 'للوثيقةِ نفسِها ومن دورتِها المسجَّلة، وتُسجَّل كلٌّ بمرجعِ المتطلبِ الذي '
          . 'وُلدت منه — فيصير لها شاهدُ قبولٍ مكتوبٌ لم يكن لها.',
            'بُنيت الشاشاتُ باشتقاقٍ موثَّقٍ وسُجِّلت في gov_governing_screens بمرجعِ اشتقاقِ كلٍّ',
            'يُعتمد سجلُّ الشاشاتِ المشتقُّ أو يُصحَّح الرقمُ في الترويسة',
        ),
        'عددُ الشاشاتِ المملوكة' => array(
            'follow_register',
            'السجلُّ الذريُّ §٤-٢٣ يسمّي الشاشاتِ بملفاتِها وأعمدتِها ولكلٍّ اختبارُ قبول؛ '
          . 'والترويسةُ رقمٌ مجرَّدٌ لا يسمّي الثامنةَ ولا يصفها. وتعدادُ §٢-١ نفسُه '
          . 'يذكر سبعةَ أسماءٍ لعددٍ ثمانية — فالسبعةُ هي المسمّاةُ والثامنةُ لا وجودَ '
          . 'لها في نصٍّ. ولا تُخترع شاشةٌ لتوفيةِ رقم.',
            'بُنيت الشاشاتُ السبعُ المسجَّلةُ بأسمائها وملفاتِها وأعمدتِها',
            'يُسمّى الثامنُ في السجلِّ الذريِّ أو يُصحَّح الرقمُ إلى سبعة',
        ),
        'مجموعُ أعمدةِ الشاشاتِ المملوكة' => array(
            'follow_register',
            'أعمدةُ كلِّ شاشةٍ مسجَّلةٌ فرديًّا في §٤-٢٣ (17+14+10+10+10+10+10 = 81)؛ '
          . 'والرقمُ 292 في الترويسةِ لا يُفصَّل على شاشاتٍ ولا يقابله نصّ. '
          . 'والأعمدةُ الإلزاميةُ التي تسميها الوثيقةُ (12 للتجنبِ و13 للجدول) مبنيةٌ '
          . 'كلُّها فوقَ ذلك — فالعبرةُ بالمسمّى لا بالمجموعِ المعلَن.',
            'بُنيت أعمدةُ كلِّ شاشةٍ بعددِها المسجَّلِ فرديًّا + الأعمدةُ الإلزاميةُ الخمسةُ والعشرون',
            'يُفصَّل الـ292 على شاشاتِه أو يُصحَّح إلى مجموعِ المسجَّل',
        ),
        'أزواجُ فصلِ الواجباتِ المنتشرة' => array(
            'follow_register',
            'جدولُ §٤-٢ في PROP-01 عرضٌ للأزواجِ «التي تمسّ كلَّ إدارةٍ لا الماليةَ '
          . 'وحدَها» — وهو مقتطَفٌ لا سجلّ. والسجلُّ الكاملُ في وثائقِ الوظائفِ الخمسِ '
          . 'وكلُّها تحمل **ثلاثةَ عشرَ زوجًا** بلا اختلاف. فالثلاثةَ عشرَ هي المعتمَدةُ '
          . 'والفرقُ في PROP-01 فرقُ نطاقٍ لا تناقضُ حكم.',
            'بُذرت الأزواجُ الثلاثةَ عشرَ كاملةً في sec_sod_pairs بمرجعِ كلٍّ',
            'يُوحَّد عرضُ §٤-٢ مع سجلِّه أو يُعلَن أنه مقتطَفٌ صراحةً',
        ),
    );
    $sql = "INSERT INTO gov_doc_variance
              (company_id, variance_code, doc_code, subject, declared_where, declared_value,
               registered_where, registered_value, resolution, resolved_value, basis, impact,
               decided_by, decided_at, owner_action, state)
            VALUES (0,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,'resolved')
            ON DUPLICATE KEY UPDATE doc_code=VALUES(doc_code), subject=VALUES(subject),
              declared_where=VALUES(declared_where), declared_value=VALUES(declared_value),
              registered_where=VALUES(registered_where), registered_value=VALUES(registered_value),
              resolution=VALUES(resolution), resolved_value=VALUES(resolved_value),
              basis=VALUES(basis), impact=VALUES(impact), owner_action=VALUES(owner_action)";
    /* التعارضاتُ التي كشفتها العوائلُ — تتمةُ ما كشفه `u13_spec_extract`. */
    $VARS = $S['variances'];
    $FAM = json_decode(@file_get_contents($ROOT . '/docs/update0013/families.json'), true);
    if (is_array($FAM) && !empty($FAM['variances'])) {
        $i = count($VARS);
        foreach ($FAM['variances'] as $v) {
            $VARS[] = array(
                'code' => sprintf('V-%02d', ++$i), 'doc' => $v['doc'],
                'subject' => $v['subject'],
                'declared_where' => 'جدولُ الأرقامِ الحاكمة', 'declared_value' => (string) $v['declared'],
                'registered_where' => 'السجلُّ الذريّ ' . $v['range'], 'registered_value' => (string) $v['registered'],
                'kind' => 'declared_vs_register',
            );
        }
    }
    /* حسمُ الثلاثةِ الجديدةِ بالمبدأِ نفسِه: السجلُّ الذريُّ يغلب الترويسة. */
    $resolutions['البنودُ التفصيليةُ للاختصاص'] = array('follow_register',
        'السجلُّ الذريُّ FCTRL-0003..0046 يسجّل أربعةً وأربعينَ بندًا لكلٍّ اختبارُ قبولٍ مكتوب؛ '
      . 'والرقمُ 45 في جدولِ الأرقامِ الحاكمةِ لا يقابله بندٌ خامسٌ وأربعون في النص. '
      . 'ولا يُخترع بندٌ لتوفيةِ رقم.',
        'سُجِّلت الأربعةُ والأربعون في gov_doc_registry بمرجعِ كلٍّ',
        'يُسمّى البندُ الخامسُ والأربعون أو يُصحَّح الرقمُ إلى 44');
    /* ◆ نصُّ الحسمِ السابقُ ادّعى أنها «وُسمت في gov_field_class» ولم يكن فيها
         منها صفٌّ واحد — ودعوى الحسمِ الكاذبةُ أسوأُ من الفجوةِ المعلَنة.
         فالنصُّ يقول الآن ما وقع فعلًا: مصفوفةُ موضعٍ وإلزامٍ وحارسٌ عند
         النفاذ، وتسعةُ حقولٍ بلا موضعٍ تُعرض فجوةً لا تُخفى. */
    $resolutions['حقولُ العقدِ الحاكمة'] = array('follow_register',
        'السجلُّ الذريُّ OBL-0058..0085 يسجّل ثمانيةً وعشرين حقلًا بصنفِ كلٍّ ومصدرِه؛ '
      . 'والرقمُ 29 لا يقابله حقلٌ تاسعٌ وعشرون مسمًّى. وحُوِّلت الثمانيةُ والعشرون '
      . 'من سجلٍّ إلى قيد: `fin_contract_fields` تحمل لكلِّ حقلٍ إلزامَه وموضعَه '
      . 'في القاعدةِ (يُحسم من information_schema لا بالإعلان)، '
      . 'و`ObligationEngine::assertContractFields` يفحص الإلزاميَّ منها عند نفاذِ '
      . 'العقدِ بوضعٍ متدرِّج (EMS_U13_CFIELD_GATE).',
        '28 حقلًا: 19 بموضعٍ حيٍّ يُفحص · 9 فجوةٌ معلَنةٌ بما يلزم المالكَ لسدِّها',
        'يُسمّى الحقلُ التاسعُ والعشرون أو يُصحَّح الرقمُ إلى 28 · ويُحسم موضعُ التسعة');
    $resolutions['الأفعالُ بعقودها'] = array('derive',
        'خمسُ وثائقَ تعلن في ترويساتها 81 فعلًا «بعقودها» ولا يسمّي سجلُّها الذريُّ '
      . 'فعلًا واحدًا ولا عقدَه. والحكمُ نفسُه المطبَّقُ على الشاشات: لا يُخترع فعلٌ '
      . 'لتوفيةِ رقم. فسُجِّلت **الأفعالُ التي تقع فعلًا** في خدماتِ الحزمةِ ولكلٍّ '
      . 'عقدُه (من يفعله · ما يكتبه · تصنيفُ كتابته) في قاموسِ الأفعال — وهي '
      . 'قابلةٌ للفحصِ بحارسِ الأفعال، والرقمُ المعلَنُ ليس كذلك.',
        'سُجِّل 37 فعلًا حقيقيًّا في nav09_action_map بتصنيفِ كتابةِ كلٍّ',
        'تُسمّى الأفعالُ الـ81 في سجلٍّ ذريٍّ بعقدِ كلٍّ أو يُصحَّح الرقمُ إلى المبنيّ');
    $resolutions['مراحلُ دورةِ المراجعة'] = array('follow_register',
        'ثلاثةُ أرقامٍ في الوثيقةِ نفسِها: الترويسةُ تقول ثمانيَ مراحل · والتعدادُ في §٢-١ '
      . 'يسمّي سبعًا · وسلسلةُ IAF-0044 الذريةُ تحمل إحدى عشرةَ مرحلةً بأسمائها. '
      . 'والسلسلةُ المسمّاةُ هي القابلةُ للاختبارِ («لا مهمةَ بلا خطةٍ ولا خطةَ بلا كون»).',
        'سُجِّلت الإحدى عشرةَ مرحلةً في fin_cycle_stages بترتيبها',
        'يُوحَّد العددُ بين الترويسةِ والتعدادِ والسلسلة');

    $n = 0;
    foreach ($VARS as $v) {
        $r = isset($resolutions[$v['subject']]) ? $resolutions[$v['subject']]
           : array('defer', 'لم يُحسم بعدُ', '', 'يُحسم مع مالكِ الوثيقة');
        /* الرقمُ المبنيُّ عليه: المسجَّلُ حين يُتبع السجل · والمشتقُّ حين لا سجلَّ. */
        $resolved = ($r[0] === 'follow_register') ? $v['registered_value']
                  : (($r[0] === 'derive') ? 'يُشتقّ' : '');
        $vals = array($v['code'], $v['doc'], mb_substr($v['subject'], 0, 200),
                      mb_substr($v['declared_where'], 0, 120), mb_substr($v['declared_value'], 0, 120),
                      mb_substr($v['registered_where'], 0, 120), mb_substr($v['registered_value'], 0, 120),
                      $r[0], mb_substr($resolved, 0, 120), mb_substr($r[1], 0, 600),
                      mb_substr($r[2], 0, 400), 'مكتب هندسة النظم — تنفيذ update0013',
                      mb_substr($r[3], 0, 300));
        assertArity($sql, str_repeat('s', 13), $vals, 'مخالفة ' . $v['code']);
        if ($apply) { run($db, $sql, str_repeat('s', 13), $vals, 'مخالفة ' . $v['code']); }
        $n++;
    }
    $stats['gov_doc_variance'] = $n;

    /* ── ⑥ نسبُ المحاسبينَ القائمينَ إلى تخصصاتِهم ─────────────────────── */
    /* FMGR-0018: «يُصنَّف إلى تخصصٍ من العشرةِ بنطاقٍ محدَّد · **لا يُحذف** بل
       يُخصَّص» — وFMGR-0022: «ولا يُحذف دورٌ قديمٌ قبل ترحيلِ حاملِه».
       الجدولُ القائمُ يحمل `admin_module` (الإدارةُ التي يخدمها المحاسب) —
       وكلُّ سطرٍ هنا مُسنَدٌ إلى المسارِ الذي يُبرِّره في مصفوفةِ التوجيه، فلا
       إسنادَ بالتخمين. */
    $moduleSpec = array(
        'sales'       => array('ACC-02', 'RT-03 · RT-11 — عقدُ العميلِ والمستخلصُ إلى محاسبِ العملاءِ والإيرادات'),
        'revenue'     => array('ACC-02', 'RT-11 — المستخلصُ المعتمدُ إلى محاسبِ العملاءِ والإيرادات'),
        'suppliers'   => array('ACC-03', 'RT-04 · RT-12 — عقدُ الموردِ ومستحقُّه إلى محاسبِ الموردينَ والمستحقات'),
        'assets'      => array('ACC-04', 'RT-13 · RT-34 — رسملةُ الأصلِ واستبعادُه إلى محاسبِ الأصولِ الثابتة'),
        'warehouse'   => array('ACC-06', 'RT-10 — استلامُ المخزنِ وصرفُه وجردُه إلى محاسبِ المخزونِ والتكاليف'),
        'maintenance' => array('ACC-06', 'RT-09 — أمرُ الصيانةِ المُقفل إلى محاسبِ المخزونِ والتكاليف'),
        'projects'    => array('ACC-07', 'FACC-0008 — تحميلُ المشروعِ ومراكزُ التكلفةِ نطاقُ ACC-07'),
        'workforce'   => array('ACC-08', 'RT-05 · RT-08 — عقدُ الموظفِ والمسيّرُ إلى محاسبِ الرواتب'),
        'procurement' => array('ACC-01', 'RT-01 — طلبُ الشراءِ التشغيليِّ إلى محاسبِ الإداراتِ والموازنات'),
        'treasury'    => array('ACC-10', 'RT-30 · RT-31 — فرقُ الجردِ والتحويلُ بين الخزائنِ إلى محاسبِ التسويات'),
    );
    $sql = "UPDATE fin_accountants SET spec_code = ?, scope_note = ?
             WHERE admin_module = ? AND (spec_code IS NULL OR spec_code = '')";
    $n = 0;
    foreach ($moduleSpec as $mod => $pair) {
        $vals = array($pair[0], mb_substr($pair[1], 0, 200), $mod);
        assertArity($sql, 'sss', $vals, "نسبُ $mod");
        if ($apply) { $n += run($db, $sql, 'sss', $vals, "نسبُ $mod"); }
    }
    $stats['fin_accountants.spec_code'] = $apply ? $n : count($moduleSpec) . ' وحدةً';

    if ($apply) { $db->commit(); }
} catch (Throwable $e) {
    if ($apply) { $db->rollback(); }
    exit('✗ ' . $e->getMessage() . "\n");
}

echo $apply ? "✔ بُذر (بمعاملةٍ واحدة)\n" : "معاينةٌ فقط — أضف --apply للكتابة\n";
foreach ($stats as $t => $c) { printf("  %-28s %d صفًّا\n", $t, $c); }

if ($apply) {
    echo "\nالمقروءُ من القاعدةِ بعدَ البذر:\n";
    foreach (array_keys($stats) as $t) {
        if (strpos($t, '.') !== false) { continue; }
        $r = $db->query("SELECT COUNT(*) FROM `$t` WHERE active = 1");
        printf("  %-28s %s\n", $t, $r ? $r->fetch_row()[0] : '؟');
    }
    $r = $db->query("SELECT spec_code, COUNT(*) c FROM fin_accountants
                      WHERE spec_code <> '' GROUP BY spec_code ORDER BY spec_code");
    echo "\n  نسبُ المحاسبينَ إلى تخصصاتِهم:\n";
    while ($r && $x = $r->fetch_assoc()) { printf("    %-8s %d\n", $x['spec_code'], $x['c']); }
    $r = $db->query("SELECT COUNT(*) FROM fin_accountants WHERE spec_code = '' OR spec_code IS NULL");
    printf("    %-8s %s\n", 'بلا تخصص', $r ? $r->fetch_row()[0] : '؟');
}
$db->close();
