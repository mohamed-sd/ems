<?php
/**
 * tools/u13_coverage_seed.php — بذرُ سجلاتِ التغطيةِ من عوائلِ الوثائق
 * ═══════════════════════════════════════════════════════════════════════════
 * يقرأ `families.json` (المستخرَجَ من الوثائقِ السبع) ويكتبه في السجلاتِ التي
 * تُعطي كلَّ بندٍ معلَنٍ أثرًا حيًّا يُقاس:
 *   gov_authority_limits · fin_cycle_stages · fin_quality_kpis ·
 *   fin_treasury_roles · gov_dept_propagation
 *
 * ◆ `enforced_by` ليس تزيينًا: هو الفرقُ بين **حدٍّ مُنفَذ** و**حدٍّ مُعلَن**.
 *   وكلُّ سطرٍ هنا يسمّي المُنفِذَ الحقيقيَّ في الكودِ أو المخطط — وما لا مُنفِذَ
 *   له يُترك فارغًا **صراحةً** ليظهر في الفاحصِ العكسيِّ ثغرةً، لا يُملأ بادعاء.
 *
 * التشغيل: php tools/u13_coverage_seed.php [--apply]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT  = dirname(__DIR__);
$apply = in_array('--apply', $argv, true);
$F = json_decode(@file_get_contents($ROOT . '/docs/update0013/families.json'), true);
if (!is_array($F)) { exit("ناقص: families.json\n"); }

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
$db->set_charset('utf8mb4');

$FAIL = 0;
function run($db, $sql, $types, array $vals, $label)
{
    global $FAIL;
    $st = $db->prepare($sql);
    if (!$st) { $FAIL++; echo "  ✗ $label — prepare: " . $db->error . "\n"; return 0; }
    if (strlen($types) !== count($vals)) {
        $st->close(); $FAIL++; echo "  ✗ $label — انزياحُ وسائط: أنواع " . strlen($types) . " · قيم " . count($vals) . "\n"; return 0;
    }
    $st->bind_param($types, ...$vals);
    if (!$st->execute()) { $e = $st->error; $st->close(); $FAIL++; echo "  ✗ $label — $e\n"; return 0; }
    $st->close();
    return 1;
}
function items($F, $doc, $family)
{
    $o = array();
    foreach ($F['items'] as $x) { if ($x['doc'] === $doc && $x['family'] === $family) { $o[] = $x; } }
    return $o;
}

$stats = array();

/* ══ ① الحدودُ الصريحة — لكلِّ حدٍّ مُنفِذُه الحقيقي ═══════════════════════ */
/* الدورُ صاحبُ الحدِّ · وأدوارُه · والمُنفِذُ الفعليُّ في الكودِ أو المخطط.
   ◆ ولا يُدَّعى مُنفِذٌ لا يوجد: ما يُنفَّذ بمنحةِ صلاحيةٍ يُسمّى `permission`،
     وما يُنفَّذ بخدمةٍ يُسمّى باسمِها، وما لم يُنفَّذ بعدُ يُترك فارغًا. */
$limitOwner = array(
    'FIN-ACC-01'  => array('محاسبُ الإدارة والتخصص', '18'),
    'FIN-CTRL-01' => array('رئيسُ الحسابات', '31'),
    'FIN-MGR-01'  => array('القيادةُ المالية', '19,32'),
    'FIN-TRE-01'  => array('الخزينةُ والبنوك', '21,34,35'),
    'IAF-01'      => array('المراجعُ الداخليُّ المستقل', '33'),
);
/* المُنفِذُ يُشتقُّ من نصِّ الحدِّ نفسِه: ما يمسُّ الاعتمادَ تحرسه بوابةُ الاعتماد،
   وما يمسّ التكليفَ تحرسه بوابةُ التكليف، وما يمسّ الرؤيةَ تحرسه الصلاحيات. */
$enforcerFor = function ($doc, $text) {
    $t = (string) $text;
    $has = function ($needle) use ($t) { return mb_strpos($t, $needle) !== false; };
    /* الترتيبُ مقصود: الأخصُّ أولًا — فـ«إنشاءُ موردٍ واعتمادُ حسابِه» زوجُ
       فصلِ واجباتٍ لا حكمَ اعتمادٍ عام. */
    if (($has('موردًا') || $has('موردٍ')) && ($has('البنكي') || $has('بياناتِه') || $has('حسابِه'))) {
        return array('sec_sod_pairs SOD-03 → AssignmentGate::checkConflicts', 'guard');
    }
    if ($has('حيازةُ النقد')) {
        return array('sec_sod_pairs SOD-04/SOD-06 — الحيازةُ والتنفيذُ للخزينةِ لا لمالكِ الدفاتر', 'guard');
    }
    if ($has('نيابةً عن رئيسِ الحسابات') || $has('اعتمادٌ موازنيّ')) {
        return array('fin_approval_types.allowed_roles APR-2 = 18,31 → ApprovalGate (FMGR-0004)', 'service');
    }
    if ($has('حجبُ تقرير')) {
        return array('exec_audit_reports.delivery_path — direct وحدَها مقبولة (CEO-Y0119)', 'schema');
    }
    if ($has('إقفالُ فترة') || $has('إعادةُ فتحِ فترة')) {
        return array('fin_financial_periods — الجاهزيةُ من رئيسِ الحساباتِ والإقفالُ بقرارِ المخوَّل', 'schema');
    }
    if ($has('تعيينُ مسمًّى') || $has('بلا موافقةِ الرئيس')) {
        return array('App\Services\Exec\AssignmentGate::isEffective — CEO-Y0121', 'service');
    }
    if ($has('تعديلُ مبلغِ أمرٍ معتمد') || $has('تعديلُ مستندٍ ماليٍّ معتمد') || $has('تعديلُ دفتر')) {
        return array('gov_field_inheritance.readonly + عدمُ الرجعية — التصحيحُ حركةٌ جديدةٌ بمرجعها', 'schema');
    }
    if ($has('غيرِ ممولة') || $has('غيرِ معتمدة')) {
        return array('App\Services\Finance\ApprovalGate::assertComplete — APR-1..3 قبلَ التنفيذ', 'service');
    }
    if ($has('قيدٍ يدويٍّ بلا حدث')) {
        return array('FCTRL-0009 — منعُ القيدِ اليدويِّ بلا واقعةٍ (fin_journal_entries.event_id)', 'schema');
    }
    if ($has('إنشاءُ موردٍ أو عميل') || $has('إعدادُ الموازنة') || $has('امتلاكُ الخطر')
        || $has('قبولُ المخاطر') || $has('نيابةً عن الإدارة') || $has('بدلَ الإدارة')
        || $has('بدلَ صاحبِ السلطة')) {
        return array('role_permissions — المراجعُ لا يملك كتابةً خارجَ Audit/ (IAF-0043 · SOD-13)', 'permission');
    }
    if ($has('إغلاقُ ملاحظاتِه') || $has('ملاحظاتِه بلا دليل')) {
        return array('iaf_findings — الإغلاقُ بدليلٍ يقبله مراجعٌ غيرُ رافعِها', 'service');
    }
    if ($has('اعتمادُ شراءٍ أو عقد')) {
        return array('fin_approval_types APR-3 — صاحبُ السقفِ لا المحاسب', 'service');
    }
    if ($has('تنفيذُ التحويلِ البنكيِّ الذي اعتمده') || $has('تنفيذُ دفعٍ اعتمده')) {
        return array('fin_approval_conflicts APR-3 × APR-4 → ApprovalGate::record', 'service');
    }
    if ($has('اعتماد') && ($has('دفع') || $has('صرف') || $has('التزام') || $has('طلب'))) {
        return array('App\Services\Finance\ApprovalGate::record — حارسُ الدورِ والتعارضِ والسقف', 'service');
    }
    if ($has('تنفيذُ الدفع') || $has('تحويلٍ بنكي') || $has('تنفيذُ تحويل')) {
        return array('App\Services\Finance\ApprovalGate::record — APR-4 للخزينةِ حصرًا', 'service');
    }
    if ($has('المطابقة')) {
        return array('sec_sod_pairs SOD-06/SOD-07 → AssignmentGate::checkConflicts', 'guard');
    }
    if ($has('موردًا') || $has('حسابٍ بنكي') || $has('المستفيد')) {
        return array('sec_sod_pairs SOD-03/SOD-04 → AssignmentGate::checkConflicts', 'guard');
    }
    if ($has('قيدٍ أعده') || $has('اعتمادُ القيد') || $has('إعدادُ القيد')) {
        return array('sec_sod_pairs SOD-08 → ApprovalGate (على المستندِ الواحد)', 'guard');
    }
    if ($has('الرواتب')) {
        return array('sec_sod_pairs SOD-10 → AssignmentGate::checkConflicts', 'guard');
    }
    if ($has('التخلص') || $has('الأصل')) {
        return array('sec_sod_pairs SOD-11 → AssignmentGate::checkConflicts', 'guard');
    }
    if ($has('إقفالُ الفترة') || $has('الفترة')) {
        return array('fin_financial_periods + period_guard — الكتابةُ في المُقفلةِ تُمنع', 'schema');
    }
    if ($has('يرى') || $has('بياناتِ الرواتب') || $has('حساباتِ العملاء')) {
        return array('gov_field_class — الصنفُ يحدد من يقرأ (OBL-0057)', 'permission');
    }
    if ($has('تعديلُ مستندٍ معتمد') || $has('بأثرٍ رجعي') || $has('لا رجعية')) {
        return array('gov_field_inheritance.readonly + عدمُ الرجعية — التصحيحُ حركةٌ جديدة', 'schema');
    }
    if ($has('حذف')) {
        return array('is_deleted soft-delete — ولا حذفَ صلبٌ لحدثٍ مالي', 'schema');
    }
    if ($has('ملاحظةِ مراجعة') || $has('ملاحظةَ مراجعة')) {
        return array('iaf_findings — لا إغلاقَ بلا دليلٍ يقبله المراجعُ ولا من الإدارةِ نفسِها', 'service');
    }
    if ($has('كتابةً على السجلات') || $has('يملك كتابة')) {
        return array('role_permissions — المراجعُ قراءةٌ خارجَ Audit/ (iaf_authorities IAF-A12)', 'permission');
    }
    if ($has('تكليف')) {
        return array('App\Services\Exec\AssignmentGate::isEffective — CEO-Y0121', 'service');
    }
    if ($has('سقف')) {
        return array('fin_authority_caps + ApprovalGate — CEO-Y0120', 'service');
    }
    /* لا مُنفِذَ معرَّفًا — يُترك فارغًا ليظهر ثغرةً لا ادعاءً. */
    return array('', 'none');
};

$sql = "INSERT INTO gov_authority_limits
          (company_id, doc_code, code, seq, subject_role, role_ids, forbidden,
           enforced_by, enforce_kind, accept_test, doc_ref, active)
        VALUES (0,?,?,?,?,?,?,?,?,?,?,1)
        ON DUPLICATE KEY UPDATE seq=VALUES(seq), subject_role=VALUES(subject_role),
          role_ids=VALUES(role_ids), forbidden=VALUES(forbidden), enforced_by=VALUES(enforced_by),
          enforce_kind=VALUES(enforce_kind), accept_test=VALUES(accept_test),
          doc_ref=VALUES(doc_ref), active=1";
$n = 0; $noEnf = 0;
foreach ($limitOwner as $doc => $own) {
    foreach (items($F, $doc, 'LIMIT') as $x) {
        list($enf, $kind) = $enforcerFor($doc, $x['title'] . ' ' . $x['detail']);
        if ($enf === '') { $noEnf++; }
        $vals = array($doc, $x['code'], $x['seq'], $own[0], $own[1],
                      mb_substr($x['title'], 0, 300), mb_substr($enf, 0, 200), $kind,
                      mb_substr($x['test'], 0, 300), $x['doc_ref']);
        if ($apply) { $n += run($db, $sql, 'ssisssssss', $vals, 'حد ' . $doc . '/' . $x['code']); }
        else { $n++; }
    }
}
$stats['gov_authority_limits'] = $n . ($noEnf ? " (بلا مُنفِذ: $noEnf)" : '');

/* ══ ② مراحلُ الدورات ═══════════════════════════════════════════════════ */
$cycles = array(
    array('payment',    'FIN-TRE-01', 'PAYSTG', 'الخزينةُ والبنوك'),
    array('receipt',    'FIN-TRE-01', 'RCVSTG', 'الخزينةُ والتحصيل'),
    array('audit',      'IAF-01',     'CYCLE',  'المراجعُ الداخليُّ المستقل'),
    array('accountant', 'FIN-ACC-01', 'CYCLE',  'محاسبُ التخصصِ ورئيسُ الحسابات'),
);
$sql = "INSERT INTO fin_cycle_stages (company_id, cycle_kind, seq, stage_ar, owner_hint, doc_code, doc_ref, active)
        VALUES (0,?,?,?,?,?,?,1)
        ON DUPLICATE KEY UPDATE stage_ar=VALUES(stage_ar), owner_hint=VALUES(owner_hint),
          doc_code=VALUES(doc_code), doc_ref=VALUES(doc_ref), active=1";
$n = 0;
foreach ($cycles as $c) {
    foreach (items($F, $c[1], $c[2]) as $x) {
        $vals = array($c[0], $x['seq'], mb_substr($x['title'], 0, 200), $c[3], $c[1], $x['doc_ref']);
        if ($apply) { $n += run($db, $sql, 'sissss', $vals, 'مرحلة ' . $c[0] . '/' . $x['seq']); }
        else { $n++; }
    }
}
$stats['fin_cycle_stages'] = $n;

/* ══ ③ مؤشراتُ جودةِ المحاسبةِ الاثنا عشر ═══════════════════════════════ */
/* FCTRL-0047: «محسوبٌ من القيودِ لا من إدخالٍ يدوي» — فلكلِّ مؤشرٍ مصدرُ حسابِه
   مكتوبًا. وما لا مصدرَ لهُ يُترك فارغًا ليظهر ثغرةً. */
$kpiSql = array(
    1  => "SELECT ROUND(100*SUM(event_id IS NOT NULL)/NULLIF(COUNT(*),0),2) FROM fin_journal_entries WHERE state='posted'",
    2  => "SELECT ROUND(100*SUM(event_id IS NULL)/NULLIF(COUNT(*),0),2) FROM fin_journal_entries WHERE state='posted'",
    3  => "SELECT COUNT(*) FROM fin_approvals WHERE action='reject'",
    4  => "SELECT COUNT(*) FROM fin_journal_entries WHERE state='draft' AND posting_date < CURDATE()",
    5  => "SELECT AVG(DATEDIFF(closed_at, period_end)) FROM fin_financial_periods WHERE closed_at IS NOT NULL",
    6  => "SELECT COUNT(*) FROM fin_bank_statement_lines WHERE matched_at IS NULL",
    7  => "SELECT COUNT(*) FROM fin_journal_lines l JOIN fin_chart_of_accounts a ON a.code=l.account_code WHERE a.code IN ('1108','2107')",
    8  => "SELECT COUNT(*) FROM fin_bank_statement_lines WHERE matched_at IS NULL",
    9  => "SELECT COUNT(*) FROM fin_financial_events WHERE source_ref='' OR source_ref IS NULL",
    10 => "SELECT COUNT(*) FROM fin_journal_entries WHERE state='reversed'",
    11 => "SELECT COUNT(*) FROM fin_backflow_log WHERE notice_code='BF-14'",
    12 => "SELECT ROUND(100*SUM(state='closed')/NULLIF(COUNT(*),0),2) FROM fin_closing_items",
);
$sql = "INSERT INTO fin_quality_kpis (company_id, code, seq, title, threshold, owner_role, cadence, source_sql, doc_ref, active)
        VALUES (0,?,?,?,?,?,?,?,?,1)
        ON DUPLICATE KEY UPDATE title=VALUES(title), threshold=VALUES(threshold),
          owner_role=VALUES(owner_role), cadence=VALUES(cadence), source_sql=VALUES(source_sql),
          doc_ref=VALUES(doc_ref), active=1";
$n = 0;
foreach (items($F, 'FIN-CTRL-01', 'KPI') as $x) {
    $code = sprintf('KPI-%02d', $x['seq']);
    $src  = isset($kpiSql[$x['seq']]) ? $kpiSql[$x['seq']] : '';
    $vals = array($code, $x['seq'], mb_substr($x['title'], 0, 200),
                  'بحدِّه المعتمَد', 'رئيسُ الحسابات', 'شهريًّا مع الإقفال',
                  mb_substr($src, 0, 500), $x['doc_ref']);
    if ($apply) { $n += run($db, $sql, 'sissssss', $vals, 'مؤشر ' . $code); }
    else { $n++; }
}
$stats['fin_quality_kpis'] = $n;

/* ══ ④ الأدوارُ الثمانيةُ داخلَ الخزينة ═════════════════════════════════ */
/* الدورُ المقابلُ في `roles` حيث يوجد — وإلا فالوظيفةُ داخلَ الوحدةِ بلا دورٍ
   مستقلٍّ بعدُ، وذلك يُصرَّح به لا يُخفى. */
$treRole = array(1 => 21, 2 => 21, 3 => 34, 4 => null, 5 => null, 6 => null, 7 => null, 8 => 35);
$sql = "INSERT INTO fin_treasury_roles (company_id, code, seq, title, role_id, scope_note, doc_ref, active)
        VALUES (0,?,?,?,?,?,?,1)
        ON DUPLICATE KEY UPDATE title=VALUES(title), role_id=VALUES(role_id),
          scope_note=VALUES(scope_note), doc_ref=VALUES(doc_ref), active=1";
$n = 0;
foreach (items($F, 'FIN-TRE-01', 'ROLE') as $x) {
    $code = sprintf('TRE-R%02d', $x['seq']);
    $rid  = isset($treRole[$x['seq']]) ? $treRole[$x['seq']] : null;
    $vals = array($code, $x['seq'], mb_substr($x['title'], 0, 200), $rid,
                  mb_substr($x['detail'], 0, 300), $x['doc_ref']);
    if ($apply) { $n += run($db, $sql, 'sisiss', $vals, 'دورُ خزينة ' . $code); }
    else { $n++; }
}
$stats['fin_treasury_roles'] = $n;

/* ══ ⑤ الانتشارُ على الإداراتِ الستَّ عشرة ═════════════════════════════ */
$sql = "INSERT INTO gov_dept_propagation (company_id, dept_name, propagated, dept_total, doors_note)
        VALUES (0,?,?,?,?)
        ON DUPLICATE KEY UPDATE propagated=VALUES(propagated), dept_total=VALUES(dept_total),
          doors_note=VALUES(doors_note)";
$doors = 'الأبوابُ الثمانية: الاعتمادات · التوجيه · الطبقات · التجنب · التصنيف · التوريث · المراجعة · التكليف';
$n = 0;
foreach ($F['dept_propagation'] as $x) {
    $vals = array($x['dept'], $x['propagated'], $x['total'], $doors);
    if ($apply) { $n += run($db, $sql, 'siis', $vals, 'إدارة ' . $x['dept']); }
    else { $n++; }
}
$stats['gov_dept_propagation'] = $n;

/* ══ ⑤-ب ميثاقُ المراجعةِ الداخلية — بنودُه الثمانيةُ أعمدةً ═══════════ */
/* IAF-0007: «معتمدٌ من الجهةِ المشرفةِ ويحدد الغرضَ والسلطةَ والمسؤوليةَ
   والاستقلال». والميثاقُ يُبذر **مسودةً** لا معتمدًا: الاعتمادُ قرارُ الجهةِ
   المشرفةِ لا قرارُ أداة — وحالتُه `draft` تقول ذلك صراحةً.
   ◆ ولا كونَ رقابيٌّ بلا ميثاق (IAF-0044) — فوجودُه مسودةً يفتح الدورةَ ولا
     يزعم اعتمادًا لم يقع. */
$ch = array();
foreach (items($F, 'IAF-01', 'CHARTER') as $x) { $ch[$x['seq']] = $x; }
$g = function ($i) use ($ch) {
    if (!isset($ch[$i])) { return ''; }
    $t = trim($ch[$i]['title'] . ' — ' . $ch[$i]['detail'], ' —');
    return mb_substr($t, 0, 590);
};
$co4 = (int) ($db->query("SELECT company_id FROM fin_accountants
                           WHERE (is_deleted IS NULL OR is_deleted=0)
                           GROUP BY company_id ORDER BY COUNT(*) DESC LIMIT 1")->fetch_row()[0] ?? 4);
$exists = (int) ($db->query("SELECT COUNT(*) FROM iaf_charter WHERE company_id={$co4}")->fetch_row()[0] ?? 0);
if ($apply && $exists === 0) {
    /* IAF-0002: مجلسٌ أو لجنةُ مراجعة — «وعند عدمهما الرئيسُ التنفيذيُّ بميثاقٍ
       مؤقتٍ يحمي الاستقلال». ولا مجلسَ ولا لجنةَ في الحيّ، فالارتباطُ `ceo`. */
    $ok = run($db, "INSERT INTO iaf_charter
            (company_id, version, functional_line, admin_line, purpose, authority,
             independence, not_following, state)
            VALUES (?, 'v1-draft', 'ceo', ?, ?, ?, ?, ?, 'draft')",
        'isssss', array($co4, mb_substr($g(3), 0, 120), $g(7), $g(5), $g(6), mb_substr($g(4), 0, 290)),
        'ميثاقُ المراجعة');
    $stats['iaf_charter'] = $ok . ' (مسودةٌ — الاعتمادُ قرارُ الجهةِ المشرفة)';
} else {
    $stats['iaf_charter'] = $exists > 0 ? 'قائمٌ سلفًا' : '1 (معاينة)';
}

/* ══ ⑤-ج ربطُ مفاتيحِ الناقلِ بمساراتِ التوجيه (OBL-0002) ═══════════════ */
/* كلُّ مفتاحٍ حيٍّ في `fin_financial_events` يُسنَد إلى مسارِه من المصفوفة.
   وما لا يُسنَد يلتقطه الحكمُ الجامعُ RT-17 — فالخريطةُ تُدقِّق ولا تَحجب. */
$evMap = array(
    /* مفتاحُ الحدث, الإدارة (فارغٌ = أيّ), المسار, البيان */
    array('revenue.unit.recognized',    '',            'RT-11', 'وحداتٌ منفَّذةٌ معترَفٌ بها ← مستخلصُ العميل'),
    array('contract.commitment',        'sales',       'RT-03', 'عقدُ عميلٍ جديدٌ أو ملحق'),
    array('expense.purchase.recorded',  'procurement', 'RT-01', 'طلبُ شراءٍ تشغيلي'),
    array('payable.purchase.accrued',   'procurement', 'RT-12', 'مستحقُّ موردٍ عن وحداتٍ معتمدة'),
    array('expense.landed_cost.recorded', 'procurement', 'RT-10', 'تكلفةُ استلامٍ مخزني'),
    array('expense.parts.issued',       'procurement', 'RT-10', 'صرفُ قطعٍ من المخزن'),
    array('expense.maintenance.recorded', 'maintenance', 'RT-09', 'أمرُ صيانةٍ مُقفل'),
    array('penalty.approved',           'sales',       'RT-15', 'غرامةٌ أو جزاءٌ أو مخالفة'),
    array('settlement.approved',        'finance',     'RT-02', 'تسويةُ سلفةٍ أو عهدة'),
    array('finance.hour_recognized',    'finance',     'RT-11', 'ساعاتٌ معترَفٌ بها ماليًّا'),
    array('equipment.hour_logged',      'movement',    'RT-11', 'ساعاتُ معدةٍ مسجَّلةٌ ميدانيًّا'),
    array('finance.request.forwarded',  '',            'RT-17', 'طلبٌ ماليٌّ محوَّلٌ — الحكمُ الجامع'),
    array('exec.approval.granted',      '',            'RT-17', 'اعتمادٌ أعلى — الحكمُ الجامع'),
);
$sql = "INSERT INTO fin_routing_event_map (company_id, event_key, source_module, route_code, priority, note, active)
        VALUES (0,?,?,?,?,?,1)
        ON DUPLICATE KEY UPDATE route_code=VALUES(route_code), priority=VALUES(priority),
          note=VALUES(note), active=1";
$n = 0;
foreach ($evMap as $i => $m) {
    /* الأدقُّ أولًا: ما قُيِّد بإدارةٍ أولى من المطلق. */
    $prio = ($m[1] !== '') ? 10 : 50;
    $vals = array($m[0], $m[1], $m[2], $prio, mb_substr($m[3], 0, 300));
    if ($apply) { $n += run($db, $sql, 'sssis', $vals, 'ربط ' . $m[0]); }
    else { $n++; }
}
$stats['fin_routing_event_map'] = $n;

/* ══ ⑤-د أفعالُ الحزمةِ الحقيقيةُ في قاموسِ الأفعال ════════════════════ */
/* ◆ الوثائقُ تعلن «الأفعالُ بعقودها» أعدادًا (23+21+14+12+11 = 81) **بلا سجلٍّ
     ذريٍّ يسمّيها** — كحالِ الشاشاتِ سواءً بسواء. والحكمُ نفسُه يُطبَّق:
     **لا يُخترع فعلٌ لتوفيةِ رقم.** فتُسجَّل الأفعالُ التي تقع فعلًا في خدماتِ
     الحزمة، ولكلٍّ عقدُه: من يفعله · ما يكتبه · أيقبل عكسًا.
     والفرقُ بين المعلَنِ والمسجَّلِ يُرفع مخالفةً بأساسٍ مكتوب. */
$acts = array(
    /* رمزٌ, عنوان, تصنيفُ الكتابة, الخدمةُ المنفِّذة */
    array('fin.route.event',        'توجيهُ واقعةٍ ماليةٍ إلى محاسبِ تخصصِها', 'domain_write', 'RoutingEngine::route'),
    array('fin.route.backflow',     'إطلاقُ مرتجَعٍ إلى مصدرِ الطلب',          'domain_write', 'RoutingEngine::backflow'),
    array('fin.route.backflow.close', 'إغلاقُ مرتجَعٍ بسببِ إلغاءِ الطلب',     'domain_write', 'RoutingEngine::closeOnCancel'),
    array('fin.route.assert',       'فحصُ مرورِ الواقعةِ بمحاسبِ تخصصِها',     'read_only',    'RoutingEngine::assertRouted'),
    array('fin.approve.need',       'اعتمادُ الحاجةِ أو الطلب (APR-1)',        'governance_write', 'ApprovalGate::record'),
    array('fin.approve.budget',     'الاعتمادُ الموازنيُّ والمحاسبي (APR-2)',  'governance_write', 'ApprovalGate::record'),
    array('fin.approve.commit',     'اعتمادُ الالتزامِ أو الدفع (APR-3)',      'governance_write', 'ApprovalGate::record'),
    array('fin.approve.execute',    'تنفيذُ الدفع (APR-4)',                     'governance_write', 'ApprovalGate::record'),
    array('fin.approve.assert',     'فحصُ اكتمالِ السلسلةِ الرباعية',          'read_only',    'ApprovalGate::assertComplete'),
    array('fin.obl.avoidance',      'إجراءُ اختبارِ التجنبِ الخماسي',          'domain_write', 'ObligationEngine::avoidanceTest'),
    array('fin.obl.generate',       'توليدُ جدولِ الاستحقاقاتِ عند النفاذ',    'domain_write', 'ObligationEngine::generateSchedule'),
    array('fin.obl.reclassify',     'إعادةُ التصنيفِ قصيرًا وطويلًا',          'domain_write', 'ObligationEngine::reclassify'),
    array('fin.obl.sweep',          'ترحيلُ المتأخرِ إلى الذممِ الدائنة',      'domain_write', 'ObligationEngine::sweepOverdue'),
    array('fin.obl.terminate',      'إنهاءُ العقدِ وإغلاقُ ما لم يستحق',       'domain_write', 'ObligationEngine::terminate'),
    array('fin.obl.horizons',       'عرضُ آفاقِ الالتزاماتِ الثلاثة',          'read_only',    'ObligationEngine::horizons'),
    array('fin.alert.fire',         'إطلاقُ تنبيهِ التزامٍ بمهلتِه',           'domain_write', 'cron_obligation_alerts'),
    array('fin.alert.escalate',     'تصعيدُ تنبيهٍ مُهمَلٍ إشارةَ خطر',        'governance_write', 'cron_obligation_alerts'),
    array('exec.assign.request',    'طلبُ تكليفٍ قياديٍّ أو رقابي',            'governance_write', 'AssignmentGate::request'),
    array('exec.assign.decide',     'قرارُ الرئيسِ على طلبِ التكليف',          'governance_write', 'AssignmentGate::decide'),
    array('exec.assign.effective',  'فحصُ سريانِ تكليفٍ قبلَ منحِ صلاحية',     'read_only',    'AssignmentGate::isEffective'),
    array('exec.assign.conflict',   'فحصُ تعارضِ الواجباتِ آليًّا',            'read_only',    'AssignmentGate::checkConflicts'),
    array('iaf.evidence.accept',    'قبولُ دليلٍ على ملاحظةِ مراجعة',          'governance_write', 'InternalAuditService::acceptEvidence'),
    array('iaf.finding.close',      'إغلاقُ ملاحظةٍ بدليلٍ يقبله المراجع',     'governance_write', 'InternalAuditService::closeFinding'),
    array('iaf.finding.escalate',   'تصعيدُ ملاحظةٍ تجاوزت مهلتَها',          'governance_write', 'InternalAuditService::escalateOverdue'),
    array('iaf.report.deliver',     'تسليمُ تقريرِ مراجعةٍ غيرَ مفلتر',        'governance_write', 'InternalAuditService::deliverReport'),
    array('iaf.access.log',         'تسجيلُ اطّلاعٍ حساسٍ للمراجع',            'governance_write', 'InternalAuditService::logAccess'),
    array('iaf.readonly.assert',    'رفضُ كتابةِ المراجعِ على السجلاتِ الأصلية', 'read_only',  'InternalAuditService::assertReadOnly'),
    array('gov.field.assert',       'رفضُ تعديلِ حقلٍ موروث',                   'read_only',    'FieldGovernor::assertNotInherited'),
    array('gov.field.classify',     'رفضُ حقلٍ بلا صنفٍ في شاشةٍ حاكمة',       'read_only',    'FieldGovernor::assertClassified'),
    array('gov.field.propagate',    'تحديثُ الموروثِ عند تغيّرِ الأصل',        'domain_write', 'FieldGovernor::onParentChange'),
);
$hasWrite = false;
$r = $db->query("SHOW COLUMNS FROM nav09_action_map LIKE 'write_class'");
if ($r && $r->num_rows > 0) { $hasWrite = true; }
$n = 0;
foreach ($acts as $a) {
    $ex = (int) ($db->query("SELECT COUNT(*) FROM nav09_action_map
                              WHERE canonical_code='" . $db->real_escape_string($a[0]) . "'")->fetch_row()[0] ?? 0);
    if ($ex > 0) { continue; }
    if (!$apply) { $n++; continue; }
    $cols = 'canonical_code, label_ar' . ($hasWrite ? ', write_class' : '');
    $ph   = '?, ?' . ($hasWrite ? ', ?' : '');
    $vals = array($a[0], mb_substr($a[1], 0, 190));
    $types = 'ss';
    if ($hasWrite) { $vals[] = $a[2]; $types .= 's'; }
    $n += run($db, "INSERT INTO nav09_action_map ($cols) VALUES ($ph)", $types, $vals, 'فعل ' . $a[0]);
}
$stats['nav09_action_map (u13)'] = $n . ' جديدًا من ' . count($acts) . ' فعلًا حقيقيًّا';

/* ══ ⑥ أفعالُ الرئيسِ السبعةُ في قاموسِ الأفعال ════════════════════════ */
/* PROP-01 §٥-٢ — والقاموسُ `nav09_action_map` هو حارسُ الأفعالِ في المنصة. */
$actCols = array();
$r = $db->query("SHOW COLUMNS FROM nav09_action_map");
while ($r && $x = $r->fetch_assoc()) { $actCols[$x['Field']] = 1; }
$n = 0;
if (isset($actCols['canonical_code'])) {
    $ceoActs = items($F, 'PROP-01', 'CEOACT');
    $slugs = array('receive.audit.report', 'decide.audit.finding', 'approve.over.cap',
                   'approve.assignment', 'check.sod.auto', 'decide.reserved.matter', 'view.assignment.log');
    $hasWrite = isset($actCols['write_class']);
    foreach ($ceoActs as $i => $x) {
        $code = 'ceo.' . (isset($slugs[$i]) ? $slugs[$i] : ('act' . ($i + 1)));
        $ex = (int) ($db->query("SELECT COUNT(*) FROM nav09_action_map
                                  WHERE canonical_code='" . $db->real_escape_string($code) . "'")->fetch_row()[0] ?? 0);
        if ($ex > 0) { continue; }
        /* فعلٌ يقرر ويكتب سجلَّ قرارٍ — والحوكمةُ تصنيفُه (CEO-Y0124: لا قيد). */
        $wc = (mb_strpos($x['title'], 'عرض') !== false || mb_strpos($x['title'], 'فحص') !== false)
            ? 'read_only' : 'governance_write';
        $cols = 'canonical_code, label_ar' . ($hasWrite ? ', write_class' : '');
        $ph   = '?, ?' . ($hasWrite ? ', ?' : '');
        $vals = array($code, mb_substr($x['title'], 0, 190));
        $types = 'ss';
        if ($hasWrite) { $vals[] = $wc; $types .= 's'; }
        if ($apply) { $n += run($db, "INSERT INTO nav09_action_map ($cols) VALUES ($ph)", $types, $vals, 'فعل ' . $code); }
        else { $n++; }
    }
}
$stats['nav09_action_map (ceo.*)'] = $n;

/* ── الحصيلة ─────────────────────────────────────────────────────────────── */
echo $apply ? "✔ بُذرت سجلاتُ التغطية\n" : "معاينةٌ فقط — أضف --apply\n";
foreach ($stats as $k => $v) { printf("  %-30s %s\n", $k, $v); }
if ($FAIL > 0) { printf("\n✗ إخفاقات: %d\n", $FAIL); }
$db->close();
exit($FAIL > 0 ? 1 : 0);
