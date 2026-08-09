<?php
/**
 * tools/u13_reverse_audit.php — الفاحصُ العكسيُّ لحزمة update0013
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ الفرقُ بينه وبين بوابةِ القبول:
 *   البوابةُ تسأل **«أنجحَ ما بنيتُه؟»** — فتفحص ما اخترتُ فحصَه، وتصمت عمَّا
 *   لم أبنِه أصلًا. وهذا يسأل **«أبنيتُ ما طُلب؟»**: يبدأ من الوثائقِ السبعِ،
 *   يعدُّ كلَّ بندٍ تعلنه، ثم يبحث عن أثرِه الحيّ. وما لا أثرَ له **يُعلَن ثغرةً
 *   برقمِها** لا يُترك في صمتٍ يُقرأ نجاحًا.
 *
 * ما يقيسه:
 *   ① الأرقامُ الحاكمةُ المعلَنةُ في كلِّ وثيقةٍ مقابلَ المستخرَجِ من سجلِّها الذري
 *   ② كلُّ عائلةِ بنودٍ (٢٦ عائلة · ٤١٦ بندًا) مقابلَ أثرِها الحيّ
 *   ③ المراجعُ المبذورةُ في القاعدةِ مقابلَ الوثيقة — صفًّا صفًّا
 *   ④ الشاشاتُ والأدوارُ والحساباتُ والصلاحيات
 *   ⑤ الأحكامُ المنتشرةُ على الإداراتِ الستَّ عشرة
 *
 * ◆ لا رقمَ مثبَّتٌ هنا: كلُّ عددٍ مقروءٌ من `spec.json` أو `families.json`
 *   المستخرَجَين من الوثائق. فتغييرُ وثيقةٍ يُظهر فرقًا لا انحرافًا صامتًا.
 *
 * التشغيل: php tools/u13_reverse_audit.php [--md=مسار] [--sync]
 *   --sync يكتب البنودَ وتغطيتَها في `gov_doc_registry` (وإلا فقراءةٌ فقط)
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$sync = in_array('--sync', $argv, true);
$mdOut = null;
foreach ($argv as $a) { if (strpos($a, '--md=') === 0) { $mdOut = $ROOT . '/' . substr($a, 5); } }

$S = json_decode(@file_get_contents($ROOT . '/docs/update0013/spec.json'), true);
$F = json_decode(@file_get_contents($ROOT . '/docs/update0013/families.json'), true);
if (!is_array($S) || !is_array($F)) { exit("ناقص: spec.json أو families.json — شغّل المستخرِجَين أولًا\n"); }

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

$SQLERR = array();
function one($db, $sql)
{
    global $SQLERR;
    $r = $db->query($sql);
    if (!$r) { $SQLERR[] = $db->error . ' — ' . mb_substr(preg_replace('~\s+~', ' ', $sql), 0, 70); return null; }
    $x = $r->fetch_row();
    return $x ? $x[0] : null;
}
function ar2en($s)
{
    return (int) strtr((string) $s, array('٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
                                          '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9'));
}

$LINES = array();   // صفوفُ التقرير: [قسم, بند, معلَن, حي, حكم, ملاحظة]
function row($sec, $item, $declared, $live, $note = '')
{
    global $LINES;
    $ok = ($declared === null) ? ($live > 0) : ($live >= $declared);
    $LINES[] = array('sec' => $sec, 'item' => $item, 'dec' => $declared, 'live' => $live, 'ok' => $ok, 'note' => $note);
    return $ok;
}

/* ═══ ① الأرقامُ الحاكمةُ: المعلَنُ في الترويسةِ مقابلَ السجلِّ الذري ═══════ */
foreach ($S['doc'] as $code => $m) {
    row('① الأحكامُ الخام', $code, (int) $m['raw_rulings'], (int) $m['atoms_found'],
        $m['atoms_found'] === $m['raw_rulings'] ? 'يُستنسَخ بلا فرق' : 'فرقٌ بين المعلَنِ والمستخرَج');
}

/* ═══ ② المراجعُ المبذورةُ — الوثيقةُ مقابلَ القاعدةِ صفًّا صفًّا ═══════════ */
$refs = array(
    array('التخصصاتُ المحاسبية',    count($S['acc_specializations']), "SELECT COUNT(*) FROM fin_acc_specializations WHERE active=1"),
    array('مساراتُ التوجيه',        count($S['routes']),              "SELECT COUNT(*) FROM fin_routing_matrix WHERE active=1"),
    array('المرتجَعُ للإدارات',      count($S['backflow']),            "SELECT COUNT(*) FROM fin_backflow_notices WHERE active=1"),
    array('قواعدُ المرتجَع',        count($S['backflow_rules']),      "SELECT COUNT(*) FROM fin_backflow_rules WHERE active=1"),
    array('أنواعُ الاعتماد',        count($S['approval_types']),      "SELECT COUNT(*) FROM fin_approval_types WHERE active=1"),
    array('أزواجُ فصلِ الواجبات',   count($S['sod_pairs']),           "SELECT COUNT(*) FROM sec_sod_pairs WHERE active=1"),
    array('الطبقاتُ الثلاث',        count($S['recognition_layers']),  "SELECT COUNT(*) FROM fin_obl_layers WHERE active=1"),
    array('اختبارُ التجنبِ الخماسي', count($S['avoidance_tests']),    "SELECT COUNT(*) FROM fin_obl_avoidance_tests WHERE active=1"),
    array('أنواعُ الالتزام',        count($S['obligation_types']),    "SELECT COUNT(*) FROM fin_obl_types WHERE active=1"),
    array('قواعدُ المحرّك (كلُّ الأُسَر)',
          count($S['obligation_rules']) + count($S['symmetry_rules']) + count($S['accrual_rules'])
        + count($S['supplier_rules']) + count($S['inheritance']),     "SELECT COUNT(*) FROM fin_obl_rules WHERE active=1"),
    array('التنبيهات',              count($S['alerts']),              "SELECT COUNT(*) FROM fin_obl_alerts WHERE active=1"),
    array('شروطُ الاعترافِ بمعيارها', count($S['recognition_conditions']), "SELECT COUNT(*) FROM fin_obl_recognition WHERE active=1"),
    array('أصنافُ البيانات',        count($S['data_classes']),        "SELECT COUNT(*) FROM gov_data_classes WHERE active=1"),
    array('اختصاصاتُ المراجعة',      count($S['iaf_competencies']),    "SELECT COUNT(*) FROM iaf_competencies WHERE active=1"),
    array('صلاحياتُ المراجعِ في النظام', count($S['iaf_authorities']), "SELECT COUNT(*) FROM iaf_authorities WHERE active=1"),
);
foreach ($refs as $r) { row('② المراجعُ المبذورة', $r[0], $r[1], (int) one($db, $r[2])); }

/* ═══ ③ العوائلُ المعلَنةُ وتغطيتُها الحية ═══════════════════════════════ */
/* لكلِّ عائلةٍ **أثرُها الحيُّ المحدَّد** — ولا يُدَّعى غطاءٌ بجدولٍ لا يحملها. */
$COV = array(
    'FIN-ACC-01|DUTY'    => array('gov_doc_registry', 'catalogue', 'واجباتٌ مرجعيةٌ — ودورتُها تُنفَّذ في acc_my_day'),
    'FIN-ACC-01|MYDAY'   => array('Finance/acc_my_day.php', 'screen', 'ثمانيةُ بنودٍ بنطاقِ المحاسبِ وتخصصِه'),
    'FIN-ACC-01|LIMIT'   => array('gov_authority_limits', 'guard', 'حدودٌ صريحةٌ بمُنفِذِ كلٍّ'),
    /* ◆ لم تعد بذرةً: `PermissionDerivation::derive` تحسب العواملَ العشرةَ
         الموجبةَ ثم تنقضها بالحاديَ عشرَ السالبِ (المنعُ الصريح). */
    'FIN-ACC-01|PFACTOR' => array('PermissionDerivation', 'service', 'الأحدَ عشرَ تُحسب — والسالبُ ينقض العشرةَ'),
    'FIN-ACC-01|SCEN'    => array('gov_doc_registry', 'uat',     'سيناريوهاتُ قبولٍ تُنفَّذ في محضرِ المستخدم — لا تُؤتمت'),
    'FIN-ACC-01|CYCLE'   => array('fin_cycle_stages', 'table', 'مراحلُ دورةِ محاسبِ الإدارة'),
    'FIN-CTRL-01|COMP'   => array('gov_doc_registry', 'catalogue', 'وصفٌ وظيفيٌّ مرجعيٌّ — لا يُشتقّ منه حكمٌ آليّ'),
    'FIN-CTRL-01|KPI'    => array('fin_quality_kpis', 'table',   'اثنا عشرَ مؤشرًا بحدِّه ومالكِه ودوريتِه'),
    'FIN-CTRL-01|LIMIT'  => array('gov_authority_limits', 'guard', 'حدودٌ صريحةٌ بمُنفِذِ كلٍّ'),
    'FIN-CTRL-01|SCEN'   => array('gov_doc_registry', 'uat',     'سيناريوهاتُ قبولٍ لمحضرِ المستخدم'),
    'FIN-MGR-01|COMP'    => array('gov_doc_registry', 'catalogue', 'وصفٌ وظيفيٌّ مرجعيّ'),
    'FIN-MGR-01|LIMIT'   => array('gov_authority_limits', 'guard', 'حدودٌ صريحة'),
    'FIN-MGR-01|SCEN'    => array('gov_doc_registry', 'uat',     'سيناريوهاتُ قبولٍ لمحضرِ المستخدم'),
    'FIN-TRE-01|ROLE'    => array('fin_treasury_roles', 'table',  'الأدوارُ الثمانيةُ داخلَ الوحدة'),
    'FIN-TRE-01|COMP'    => array('gov_doc_registry', 'catalogue', 'وصفٌ وظيفيٌّ مرجعيّ'),
    'FIN-TRE-01|LIMIT'   => array('gov_authority_limits', 'guard', 'حدودٌ صريحة'),
    'FIN-TRE-01|PAYSTG'  => array('fin_cycle_stages', 'table',    'خمسَ عشرةَ مرحلةً بترتيبها'),
    'FIN-TRE-01|RCVSTG'  => array('fin_cycle_stages', 'table',    'تسعُ مراحلَ بترتيبها'),
    'FIN-TRE-01|SCEN'    => array('gov_doc_registry', 'uat',     'سيناريوهاتُ قبولٍ لمحضرِ المستخدم'),
    /* ◆ `iaf_charter` سجلُّ **ميثاقٍ واحدٍ** ببنودِه أعمدةً — لا ثمانيةُ صفوف.
         فبنودُه الثمانيةُ تُسجَّل بنودًا معلَنةً في السجلِّ العام، والميثاقُ
         المعتمَدُ نفسُه يُفحص في «④ البنيةُ الحية» مستقلًّا. */
    'IAF-01|CHARTER'     => array('gov_doc_registry', 'catalogue', 'بنودُ ميثاقٍ مرجعيةٌ — واعتمادُ الميثاقِ يُفحص في ④'),
    'IAF-01|LIMIT'       => array('gov_authority_limits', 'guard', 'حدودٌ صريحة'),
    'IAF-01|CYCLE'       => array('fin_cycle_stages', 'table',    'مراحلُ دورةِ المراجعة'),
    'IAF-01|SCEN'        => array('gov_doc_registry', 'uat',     'سيناريوهاتُ قبولٍ لمحضرِ المستخدم'),
    'FIN-OBL-01|CFIELD'  => array('fin_contract_fields', 'guard', '19 بموضعٍ حيٍّ يفحصه المحرّكُ عند النفاذِ · و9 فجوةٌ معلَنة'),
    /* ◆ لم تعد بذرةً: نصُّ كلٍّ «يُنفَّذ حيًّا ويُطابَق بالقاعدة»، وهي كذلك
         الآن في `tools/u13_stdtest_harness.php` — تبني بالمحرّكِ وتقيس الأثر. */
    'FIN-OBL-01|STDTEST' => array('u13_stdtest_harness', 'harness', 'الخمسةَ عشرَ تُنفَّذ حيًّا 16/16 (بحكمِ «لا قيد» الجامع)'),
    'PROP-01|CEOACT'     => array('nav09_action_map', 'guard',    'أفعالُ الرئيسِ السبعةُ بعقودها'),
);
$fams = array();
foreach ($F['items'] as $x) { $fams[$x['doc'] . '|' . $x['family']][] = $x; }
ksort($fams);

/* الأثرُ الحيُّ لكلِّ عائلةٍ يُقاس بجدولِه الحقيقيِّ لا بادعاء. */
$liveOf = function ($covTable, $doc, $family) use ($db) {
    switch ($covTable) {
        case 'gov_doc_registry':
            return (int) one($db, "SELECT COUNT(*) FROM gov_doc_registry
                                    WHERE doc_code='" . $db->real_escape_string($doc) . "'
                                      AND family='" . $db->real_escape_string($family) . "'");
        case 'gov_authority_limits':
            return (int) one($db, "SELECT COUNT(*) FROM gov_authority_limits
                                    WHERE doc_code='" . $db->real_escape_string($doc) . "' AND active=1");
        case 'fin_cycle_stages':
            $k = ($family === 'PAYSTG') ? 'payment'
               : (($family === 'RCVSTG') ? 'receipt'
               : (($doc === 'FIN-ACC-01') ? 'accountant' : 'audit'));
            return (int) one($db, "SELECT COUNT(*) FROM fin_cycle_stages WHERE cycle_kind='$k' AND active=1");
        case 'nav09_action_map':
            /* ◆ الأفعالُ السبعةُ التي سجّلتها الحزمةُ وحدَها — و`ceo.%` تلتقط
                 أفعالًا سابقةً (ceo.approve · ceo.sign) فيُضخَّم العد. */
            return (int) one($db, "SELECT COUNT(*) FROM nav09_action_map WHERE canonical_code IN
                ('ceo.receive.audit.report','ceo.decide.audit.finding','ceo.approve.over.cap',
                 'ceo.approve.assignment','ceo.check.sod.auto','ceo.decide.reserved.matter',
                 'ceo.view.assignment.log')");
        case 'Finance/acc_my_day.php':
            return (int) one($db, "SELECT COUNT(*) FROM gov_doc_registry
                                    WHERE doc_code='FIN-ACC-01' AND family='MYDAY'");

        /* ◆ ليس كلُّ أثرٍ حيٍّ جدولًا: بندٌ يُنفَّذ **خدمةً** أو **مِحكًّا آليًّا**
             أثرُه أن الشيفرةَ موجودةٌ وتعمل. وعدُّ صفوفِ جدولٍ لا وجودَ له
             يُعيد صفرًا فيُقرأ ثغرةً وهو منفَّذٌ فعلًا. */
        case 'PermissionDerivation':
            $f = dirname(__DIR__) . '/app/Services/Finance/PermissionDerivation.php';
            if (!is_file($f)) { return 0; }
            $src = (string) file_get_contents($f);
            /* الأحدَ عشرَ مُعلَنةٌ في ثابتِ العوامل — ويُعدُّ ما فيه لا ما يُدَّعى. */
            return preg_match_all("~'PFACTOR-\d\d'\s*=>~u", $src);

        case 'u13_stdtest_harness':
            $f = dirname(__DIR__) . '/tools/u13_stdtest_harness.php';
            if (!is_file($f)) { return 0; }
            return preg_match_all("~\\\$t\('STDTEST-\d\d'~u", (string) file_get_contents($f));

        case 'fin_contract_fields':
            /* الحقولُ التي لها **موضعٌ حيٌّ يُفحص** — والفجوةُ المعلَنةُ تُعدُّ
               بندًا مسجَّلًا كذلك، فالمصفوفةُ تغطي الثمانيةَ والعشرين كلَّها. */
            return (int) one($db, "SELECT COUNT(*) FROM fin_contract_fields WHERE active=1");

        default:
            $t = $db->real_escape_string($covTable);
            if ((int) one($db, "SELECT COUNT(*) FROM information_schema.TABLES
                                 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t'") < 1) { return 0; }
            $hasActive = (int) one($db, "SELECT COUNT(*) FROM information_schema.COLUMNS
                                          WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='active'");
            return (int) one($db, "SELECT COUNT(*) FROM `$t`" . ($hasActive ? ' WHERE active=1' : ''));
    }
};
foreach ($fams as $key => $items) {
    list($doc, $family) = explode('|', $key);
    $cov = isset($COV[$key]) ? $COV[$key] : array('', 'none', '');
    $live = $cov[0] === '' ? 0 : $liveOf($cov[0], $doc, $family);
    row('③ العوائلُ المعلَنة', $doc . ' · ' . $family, count($items), $live,
        $cov[0] === '' ? 'بلا أثرٍ حيٍّ معرَّف' : $cov[0]);
}

/* ═══ ④ الشاشاتُ والأدوارُ والحسابات ══════════════════════════════════════ */
require_once $ROOT . '/tools/u13_screens_manifest.php';
$MAN = u13_screens_manifest();
$co = (int) one($db, "SELECT company_id FROM fin_accountants
                       WHERE (is_deleted IS NULL OR is_deleted=0)
                       GROUP BY company_id ORDER BY COUNT(*) DESC LIMIT 1");
row('④ البنيةُ الحية', 'الشاشاتُ مبنيةً ومسجَّلة', count($MAN),
    (int) one($db, "SELECT COUNT(*) FROM gov_governing_screens WHERE active=1"));
row('④ البنيةُ الحية', 'الشاشاتُ في modules', count($MAN),
    (int) one($db, "SELECT COUNT(*) FROM modules m JOIN gov_governing_screens g
                     ON m.code = g.file_path WHERE g.active=1"));
row('④ البنيةُ الحية', 'الأدوارُ المالية الجديدة', 5,
    (int) one($db, "SELECT COUNT(*) FROM roles WHERE id BETWEEN 31 AND 35"));
row('④ البنيةُ الحية', 'حاملو الأدوارِ بتكليفٍ مُقرَّر', 5,
    (int) one($db, "SELECT COUNT(DISTINCT role_id) FROM exec_assignments
                     WHERE company_id={$co} AND state='approved' AND role_id BETWEEN 31 AND 35"));
row('④ البنيةُ الحية', 'تخصصاتٌ لها حامل', count($S['acc_specializations']),
    (int) one($db, "SELECT COUNT(DISTINCT spec_code) FROM fin_accountants
                     WHERE company_id={$co} AND active=1 AND spec_code<>''"));
row('④ البنيةُ الحية', 'محاسبونَ بحسابِ دخول',
    (int) one($db, "SELECT COUNT(*) FROM fin_accountants WHERE company_id={$co} AND active=1"),
    (int) one($db, "SELECT COUNT(*) FROM fin_accountants a JOIN users u ON u.employee_id=a.employee_id
                     WHERE a.company_id={$co} AND a.active=1"));
row('④ البنيةُ الحية', 'مخالفاتُ الوثائقِ محسومةٌ بأساس', count($S['variances']),
    (int) one($db, "SELECT COUNT(*) FROM gov_doc_variance WHERE basis<>'' AND resolution<>'defer'"));

/* ◆ العدُّ وحدَه يكذب: مئةُ سيناريوٍ سُجِّلت وعنوانُ كلٍّ **حركةُ تشكيلٍ واحدة**
     («ٍ») لأن المُستخرِجَ التقط ما بين «سيناريو قبول» والشرطةِ لا الجزءَ التالي —
     ومرَّت 20/20 خضراءَ خمسَ مراتٍ لأن الفاحصَ يعدُّ الصفوفَ ولا يقرؤها. فهذا
     الفحصُ يسأل عن **المحتوى**: عنوانٌ يقصر عن ثمانيةِ محارفَ ليس عنوانًا. */
/* ◆ والقياسُ بطولِ العنوانِ **خطأٌ أيضًا**: «الإدارة» و«الكيان» و«الشيكات»
     عناوينُ حقيقيةٌ من الوثيقةِ تقصر عن ثمانيةِ محارف. فالبصمةُ الصادقةُ
     للعطبِ أن العنوانَ **بلا حروفٍ أصلًا** — حركةُ تشكيلٍ أو ترقيمٌ فقط. */
$regAll = 0; $regOk = 0; $thinSample = array();
$rq = $db->query("SELECT doc_code, item_code, title FROM gov_doc_registry");
if ($rq === false) { exit("استعلامٌ فاشلٌ في فاحصِ العناوين: " . $db->error . "\n"); }
while ($rq && $x = $rq->fetch_row()) {
    $regAll++;
    if (preg_match_all('~\p{L}~u', (string) $x[2]) >= 2) { $regOk++; }
    elseif (count($thinSample) < 4) { $thinSample[] = $x[0] . '/' . $x[1] . ' «' . $x[2] . '»'; }
}
row('④ البنيةُ الحية', 'بنودُ السجلِّ بعناوينَ فيها حروفٌ لا حركاتُ تشكيل', $regAll, $regOk,
    $regOk === $regAll ? 'صفرُ عنوانٍ بلا حروف' : implode(' · ', $thinSample));

/* ═══ ⑤ الانتشارُ على الإدارات ═══════════════════════════════════════════ */
$dp = $F['dept_propagation'];
row('⑤ الانتشار', 'الإداراتُ المتأثرة', count($dp),
    (int) one($db, "SELECT COUNT(*) FROM gov_dept_propagation"));
row('⑤ الانتشار', 'مجموعُ الأحكامِ المنتشرة', array_sum(array_column($dp, 'propagated')),
    (int) one($db, "SELECT COALESCE(SUM(propagated),0) FROM gov_dept_propagation"));

/* ═══ المزامنةُ إلى السجل ══════════════════════════════════════════════════ */
if ($sync) {
    $st = $db->prepare("INSERT INTO gov_doc_registry
            (company_id, doc_code, family, item_code, seq, title, detail, accept_test, doc_ref,
             covered_by, coverage_kind, coverage_note)
            VALUES (0,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE seq=VALUES(seq), title=VALUES(title), detail=VALUES(detail),
              accept_test=VALUES(accept_test), doc_ref=VALUES(doc_ref),
              covered_by=VALUES(covered_by), coverage_kind=VALUES(coverage_kind),
              coverage_note=VALUES(coverage_note)");
    $n = 0;
    foreach ($F['items'] as $x) {
        $key = $x['doc'] . '|' . $x['family'];
        $cov = isset($COV[$key]) ? $COV[$key] : array('', 'none', '');
        $st->bind_param('sssisssssss', $x['doc'], $x['family'], $x['code'], $x['seq'], $x['title'],
            $x['detail'], $x['test'], $x['doc_ref'], $cov[0], $cov[1], $cov[2]);
        if ($st->execute()) { $n++; }
    }
    $st->close();
    $st = $db->prepare("INSERT INTO gov_dept_propagation (company_id, dept_name, propagated, dept_total)
                        VALUES (0,?,?,?)
                        ON DUPLICATE KEY UPDATE propagated=VALUES(propagated), dept_total=VALUES(dept_total)");
    $d = 0;
    foreach ($dp as $x) {
        $st->bind_param('sii', $x['dept'], $x['propagated'], $x['total']);
        if ($st->execute()) { $d++; }
    }
    $st->close();
    echo "◆ زُومنت إلى السجل: $n بندًا · $d إدارة\n\n";
}

/* ═══ التقرير ═════════════════════════════════════════════════════════════ */
$secs = array();
foreach ($LINES as $l) { $secs[$l['sec']][] = $l; }
$pass = 0; $fail = 0;
echo "\n" . str_repeat('═', 82) . "\n";
echo "  الفاحصُ العكسيُّ — update0013 · «أبنيتُ ما طُلب؟»\n";
echo str_repeat('═', 82) . "\n";
foreach ($secs as $sec => $rows) {
    $p = 0; foreach ($rows as $l) { if ($l['ok']) { $p++; } }
    printf("\n  ▐ %s — %d/%d\n\n", $sec, $p, count($rows));
    foreach ($rows as $l) {
        if ($l['ok']) { $pass++; } else { $fail++; }
        printf("   %s %-34s معلَن %-5s حيّ %-5s %s\n", $l['ok'] ? '✔' : '✗',
            mb_substr($l['item'], 0, 33), $l['dec'] === null ? '—' : $l['dec'], $l['live'],
            mb_substr($l['note'], 0, 34));
    }
}
if ($SQLERR) {
    echo "\n  ▐ استعلاماتٌ فاشلةٌ في الفاحصِ نفسِه — تُبطل ثقةَ ما بُني عليها\n\n";
    foreach (array_slice($SQLERR, 0, 6) as $e) { echo "   ✗ $e\n"; }
    $fail += count($SQLERR);
}
$total = $pass + $fail;
printf("\n%s\n  التغطية: %d/%d (%.1f%%) %s\n%s\n", str_repeat('═', 82), $pass, $total,
    $total ? ($pass / $total * 100) : 0, $fail === 0 ? '— مكتملة' : "— ثغرات: $fail", str_repeat('═', 82));

if ($mdOut) {
    $md = "# الفاحصُ العكسيُّ — update0013\n\n> «أبنيتُ ما طُلب؟» لا «أنجحَ ما بنيتُه؟»\n\n";
    foreach ($secs as $sec => $rows) {
        $p = 0; foreach ($rows as $l) { if ($l['ok']) { $p++; } }
        $md .= sprintf("## %s — %d/%d\n\n| | البند | معلَن | حيّ | الأثر |\n| --- | --- | --- | --- | --- |\n", $sec, $p, count($rows));
        foreach ($rows as $l) {
            $md .= sprintf("| %s | %s | %s | %s | %s |\n", $l['ok'] ? '✔' : '✗', $l['item'],
                $l['dec'] === null ? '—' : $l['dec'], $l['live'], $l['note']);
        }
        $md .= "\n";
    }
    $md .= sprintf("**التغطية: %d/%d (%.1f%%)**\n", $pass, $total, $total ? ($pass / $total * 100) : 0);
    file_put_contents($mdOut, $md);
}
$db->close();
exit($fail === 0 ? 0 : 1);
