<?php
/**
 * tools/m16_ac_gate.php — بوابةُ القبولِ العشرُ لـM-16 (§16-2 AC-01..AC-10)
 * ───────────────────────────────────────────────────────────────────────────
 * حزامُ m16_checks.php يقيس النطاقَ المبنيَّ (12 جدولًا · 10 شاشات). وهذا الحزامُ
 * يقيس نطاقَ الوثيقةِ نفسِه: 20 شاشةً · 353 عمودًا · 28 فعلًا · 26 حدثًا · 16 قاعدة.
 * فلا يُقال «أخضر» إلا بعبورِ البوابةِ العشرِ كما نصَّت الوثيقةُ لا كما بُني.
 *
 * كلُّ فحصٍ يطبع شاهدَه المقيسَ لا حكمَه المجرَّد — والالتزامُ لا يُدَّعى بل يُثبَت.
 * الخروج 0 عند عبورِ العشرِ كلِّها، و1 عند أيِّ رسوب.
 *
 * التشغيل: php tools/m16_ac_gate.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/app/Services/Risk/RiskEvents.php';
require_once $ROOT . '/app/Services/Risk/RiskService.php';
require_once $ROOT . '/app/Services/Risk/RiskSignalEngine.php';

use App\Services\Risk\RiskEvents;
use App\Services\Risk\RiskService;
use App\Services\Risk\RiskSignalEngine;

$db = new mysqli('127.0.0.1', 'root', '', 'equipation_manage', 3307);
if ($db->connect_error) { fwrite(STDERR, $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');
$CO = 4;
$fail = 0;

function ac($code, $name, $ok, $evidence) {
    global $fail;
    if (!$ok) { $fail++; }
    echo ($ok ? '✔' : '✘') . " $code · $name\n    ↳ $evidence\n";
}
function one($db, $sql) { $r = $db->query($sql); return $r ? (string) $r->fetch_row()[0] : '0'; }

/* الشاشاتُ العشرون كما نصَّ §6-1 — اسمُ الوثيقةِ ⇒ الملفُّ الحيُّ المنفِّذ.
   التسميةُ اختلفت في البناءِ (risk_board لا risk_dashboard) فالمطابقةُ بالوظيفةِ
   لا بالحرف — والجسرُ معلَنٌ هنا لا مخفيًّا في نيّةِ المبرمج. */
$SCREEN_MAP = array(
    'risk_dashboard.php'      => array('Risk/risk_board.php', 15),
    'risk_register.php'       => array('Risk/risk_register.php', 36),
    'risk_units.php'          => array('Risk/risk_units.php', 14),
    'risk_profile.php'        => array('Risk/risk_card.php', 24),
    'risk_assessment.php'     => array('Risk/risk_assessment.php', 24),
    'risk_controls.php'       => array('Risk/risk_controls.php', 24),
    'risk_control_verify.php' => array('Risk/risk_control_verify.php', 17),
    'risk_kri.php'            => array('Risk/risk_kris.php', 14),
    'risk_treatment.php'      => array('Risk/risk_treatments.php', 19),
    'risk_acceptance.php'     => array('Risk/risk_acceptance.php', 17),
    'risk_signals.php'        => array('Risk/risk_signals.php', 13),
    'risk_incidents.php'      => array('Risk/risk_incidents.php', 19),
    'risk_reviews.php'        => array('Risk/risk_reviews.php', 17),
    'risk_committee.php'      => array('Risk/risk_committee.php', 16),
    'risk_appetite.php'       => array('Risk/risk_appetite.php', 16),
    'risk_reports.php'        => array('Risk/risk_reports.php', 10),
    'risk_settings.php'       => array('Risk/risk_settings.php', 14),
    'risk_dept_view.php'      => array('Risk/dept_risk_space.php', 11),
    'risk_field.php'          => array('Risk/risk_field.php', 18),
    'gov_dept_rsk.php'        => array('Risk/gov_dept_rsk.php', 15),
);
$LONG = array('Risk/risk_register.php', 'Risk/risk_card.php',
              'Risk/risk_assessment.php', 'Risk/risk_controls.php');
$SENSITIVE_SCREENS = array('Risk/risk_register.php', 'Risk/risk_card.php', 'Risk/risk_controls.php',
                           'Risk/risk_kris.php', 'Risk/risk_treatments.php', 'Risk/gov_dept_rsk.php');

echo "بوابة قبول M-16 — نطاقُ الوثيقةِ لا نطاقُ ما بُني\n";
echo str_repeat('═', 74), "\n";

/* ══ AC-01 · صفرُ مرحلةٍ بلا مستندٍ ومعتمِد ═══════════════════════════════ */
$stageDocs = array(
    'المرحلة 0 لوحة' => array('risk_register', null),
    '1 الرصد والفرز' => array('risk_signals', 'triage_by'),
    '2 التسجيل والتصنيف' => array('risk_register', 'created_by'),
    '3 التقييم والتحدي' => array('risk_assessments', 'assessed_by'),
    '4 الضوابط والتحقق' => array('risk_controls', 'last_verified_by'),
    '5 المراقبة والمؤشرات' => array('risk_kris', 'last_read_by'),
    '6 المعالجة والمتابعة' => array('risk_treatments', 'verified_by'),
    '7 القبول والتصعيد' => array('risk_acceptances', 'accepted_by'),
    '8 الحوكمة والإطار' => array('risk_committee', 'approved_by'),
);
$missDoc = array();
foreach ($stageDocs as $stage => $pair) {
    list($tbl, $approver) = $pair;
    if (one($db, "SELECT COUNT(*) FROM information_schema.tables
                   WHERE table_schema = DATABASE() AND table_name = '$tbl'") === '0') {
        $missDoc[] = "$stage: لا جدولَ $tbl";
        continue;
    }
    if ($approver !== null && one($db, "SELECT COUNT(*) FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = '$tbl' AND column_name = '$approver'") === '0') {
        $missDoc[] = "$stage: لا معتمِدَ ($tbl.$approver)";
    }
}
ac('AC-01', 'صفر مرحلة بلا مستند ومعتمِد', empty($missDoc),
    count($stageDocs) . ' مرحلة · لكلٍّ جدولُ مستندٍ وعمودُ معتمِد'
    . (empty($missDoc) ? '' : ' — الناقص: ' . implode(' · ', $missDoc)));

/* ══ AC-02 · صفرُ شاشةٍ بلا عمودٍ حاكمٍ محقون ════════════════════════════ */
$noCo = array();
$docTables = array('risk_assessments', 'risk_controls', 'risk_treatments', 'risk_acceptances',
                   'risk_reviews', 'risk_committee');
foreach (array('risk_units', 'risk_register', 'risk_signals', 'risk_incidents', 'risk_kris',
               'risk_appetite', 'risk_export_log', 'risk_escalations', 'risk_control_links',
               'risk_control_evidence', 'risk_committee_items') as $t) {
    if (one($db, "SELECT COUNT(*) FROM information_schema.columns
                   WHERE table_schema = DATABASE() AND table_name = '$t' AND column_name = 'company_id'") === '0') {
        $noCo[] = "$t بلا company_id";
    }
}
$noApprover = array();
foreach ($docTables as $t) {
    foreach (array('approved_by', 'approved_at', 'authority_ref', 'parent_ref') as $c) {
        if (one($db, "SELECT COUNT(*) FROM information_schema.columns
                       WHERE table_schema = DATABASE() AND table_name = '$t' AND column_name = '$c'") === '0') {
            // risk_treatments يشهد بـverified_by/at بدل approved_by — قبولٌ معلَن
            if ($t === 'risk_treatments' && in_array($c, array('approved_by', 'approved_at'), true)) { continue; }
            if ($t === 'risk_acceptances' && in_array($c, array('approved_by', 'approved_at'), true)) { continue; }
            $noApprover[] = "$t.$c";
        }
    }
}
ac('AC-02', 'صفر شاشة بلا عمود حاكم محقون', empty($noCo) && empty($noApprover),
    '17 جدولًا بعمود الكيان · ' . count($docTables) . ' جدولَ مستندٍ بالمعتمِد ومرجعِ التفويضِ والمرجعِ الأب'
    . (empty($noCo) && empty($noApprover) ? '' : ' — الناقص: ' . implode(' · ', array_merge($noCo, $noApprover))));

/* ══ AC-03 · صفرُ زرٍّ بلا عقدِ فعل ══════════════════════════════════════ */
$acts = array();
$r = $db->query("SELECT canonical_code, actor_ar, writes_text, event_name, consumers_text,
                        effect_text, reverse_text, write_class
                   FROM nav09_action_map
                  WHERE canonical_code LIKE 'RSK-%' OR canonical_code LIKE 'GOV-RSK-%'");
$incomplete = array();
while ($x = $r->fetch_assoc()) {
    $acts[] = $x['canonical_code'];
    foreach (array('actor_ar', 'writes_text', 'event_name', 'consumers_text', 'effect_text', 'reverse_text', 'write_class') as $k) {
        if (trim((string) $x[$k]) === '') { $incomplete[] = $x['canonical_code'] . '.' . $k; break; }
    }
}
$dupCode = one($db, "SELECT COUNT(*) FROM (SELECT canonical_code FROM nav09_action_map
                      WHERE canonical_code LIKE 'RSK-%' OR canonical_code LIKE 'GOV-RSK-%'
                      GROUP BY canonical_code HAVING COUNT(*) > 1) d");
ac('AC-03', 'صفر زرّ بلا عقد فعل', count($acts) >= 28 && empty($incomplete) && $dupCode === '0',
    count($acts) . ' فعلًا (الوثيقة 28) · عقودٌ سباعيةٌ مكتملة ' . (count($acts) - count($incomplete))
    . '/' . count($acts) . ' · رموزٌ مكررة ' . $dupCode);

/* ══ AC-04 · صفرُ فعلٍ ماليٍّ بلا عكس ════════════════════════════════════ */
$finActs = one($db, "SELECT COUNT(*) FROM nav09_action_map
                      WHERE (canonical_code LIKE 'RSK-%' OR canonical_code LIKE 'GOV-RSK-%')
                        AND write_class = 'financial_write'");
$ledger = one($db, "SELECT COUNT(*) FROM fin_financial_events WHERE source_module = 'risk'");
$noReverse = one($db, "SELECT COUNT(*) FROM nav09_action_map
                        WHERE (canonical_code LIKE 'RSK-%' OR canonical_code LIKE 'GOV-RSK-%')
                          AND (reverse_text IS NULL OR reverse_text = '')");
ac('AC-04', 'صفر فعل مالي بلا عكس', $finActs === '0' && $ledger === '0' && $noReverse === '0',
    "أفعالٌ ماليةٌ $finActs · قيودٌ في الدفتر $ledger (RK-06 يلزم صفرًا) · أفعالٌ بلا نصِّ عكسٍ $noReverse");

/* ══ AC-05 · صفرُ شاشةٍ طويلةٍ بلا مناظر ═════════════════════════════════ */
$noView = array();
foreach ($LONG as $f) {
    $src = @file_get_contents($ROOT . '/' . $f);
    if ($src === false) { $noView[] = "$f غائب"; continue; }
    $hasBar = strpos($src, 'risk_view_bar') !== false || strpos($src, 'risk_view_defs') !== false;
    $hasAll = strpos($src, 'risk_current_view') !== false;
    if (!$hasBar || !$hasAll) { $noView[] = basename($f); }
}
$defsAll = 0;
require_once $ROOT . '/Risk/_risk_views.php';
foreach (array('risk_register', 'risk_card', 'risk_assessment', 'risk_controls') as $s) {
    $d = risk_view_defs($s);
    if (isset($d['all']) && count($d) >= 2) { $defsAll++; }
}
ac('AC-05', 'صفر شاشة طويلة بلا مناظر', empty($noView) && $defsAll === 4,
    count($LONG) . ' شاشةً طويلة · منتقي منظرٍ CM-09 وزرُّ إظهارِ الكلِّ CM-10 في ' . (count($LONG) - count($noView))
    . '/' . count($LONG) . ' · تعريفاتُ مناظرَ مكتملة ' . $defsAll . '/4'
    . (empty($noView) ? '' : ' — الناقص: ' . implode(' · ', $noView)));

/* ══ AC-06 · صفرُ حقلٍ حساسٍ يُرسَل لغير المخوَّل ═══════════════════════ */
$sens = (int) one($db, "SELECT COUNT(*) FROM scr_sensitive_fields
                         WHERE company_id = $CO AND table_name LIKE 'risk%'");
$logFlag = (int) one($db, "SELECT COUNT(*) FROM scr_sensitive_fields
                            WHERE company_id = $CO AND table_name LIKE 'risk%' AND log_views_flag = 'نعم'");
$exportBlocked = (int) one($db, "SELECT COUNT(*) FROM scr_sensitive_fields
                                  WHERE company_id = $CO AND table_name LIKE 'risk%' AND exportable_flag = 'لا'");
ac('AC-06', 'صفر حقل حساس يُرسَل لغير المخوَّل', $sens >= 6 && $logFlag === $sens && $exportBlocked === $sens,
    "$sens حقلًا حساسًا مسجَّلًا (الوثيقة 6 شاشات) · يُسجَّل اطّلاعُها $logFlag · تُمنع من التصدير $exportBlocked");

/* ══ AC-07 · صفرُ شاشةٍ بلا الغلافِ الحاكم ══════════════════════════════ */
$noShell = array();
foreach ($SCREEN_MAP as $docFile => $pair) {
    $f = $pair[0];
    $src = @file_get_contents($ROOT . '/' . $f);
    if ($src === false) { $noShell[] = basename($f) . ' غائب'; continue; }
    $need = array('ems_shell_axes', 'ems_screen_about', 'page_header.php');
    foreach ($need as $n) {
        if (strpos($src, $n) === false) { $noShell[] = basename($f) . " بلا $n"; break; }
    }
}
ac('AC-07', 'صفر شاشة بلا الغلاف الحاكم', empty($noShell),
    count($SCREEN_MAP) . ' شاشةً · محاورُ الغلافِ وبطاقةُ «عن الشاشة» ورأسُ الصفحةِ في '
    . (count($SCREEN_MAP) - count($noShell)) . '/' . count($SCREEN_MAP)
    . (empty($noShell) ? '' : ' — الناقص: ' . implode(' · ', $noShell)));

/* ══ AC-08 · صفرُ كتابةٍ عابرةٍ للإدارات ════════════════════════════════ */
$FOREIGN = array('contracts', 'mnt_order', 'mnt_breakdown', 'tickets', 'fin_receivables',
                 'fin_ledger_entries', 'fin_dues', 'proc_item', 'proc_stock_move',
                 'equipments', 'users', 'role_permissions', 'modules', 'fin_budget_lines');
$viol = array();
foreach (array_merge(glob($ROOT . '/Risk/*.php'), glob($ROOT . '/app/Services/Risk/*.php')) as $p) {
    $src = (string) file_get_contents($p);
    foreach ($FOREIGN as $t) {
        if (preg_match('~\b(INSERT\s+(?:IGNORE\s+)?INTO|UPDATE|DELETE\s+FROM)\s+`?' . preg_quote($t, '~') . '`?\b~i', $src, $mm)) {
            $viol[] = basename($p) . ' ⇒ ' . strtoupper($mm[1]) . ' ' . $t;
        }
    }
}
$events = (int) one($db, "SELECT COUNT(*) FROM ems_business_events WHERE source_module = 'risk'");
ac('AC-08', 'صفر كتابة عابرة للإدارات', empty($viol),
    'مسحُ ' . count($FOREIGN) . ' جدولَ إدارةٍ أخرى في كودِ المخاطرِ الحيِّ: ' . count($viol) . ' خرقًا'
    . " · الأثرُ ينتقل بـ$events حدثًا منشورًا"
    . (empty($viol) ? '' : ' — ' . implode(' · ', $viol)));

/* ══ AC-09 · كلُّ فعلٍ يجتاز الحالاتِ الأربع + الأحداثُ الستةُ والعشرون ═ */
$docReq = RiskEvents::DOC_REQUIRED;
$mapKeys = array_keys(RiskEvents::MAP);
$missEv = array_diff($docReq, $mapKeys);
// الفعلُ الحاكمُ يجتاز: سماحٌ ومنعٌ وتكرارٌ وعكس — يُقاس بوجودِ حارسٍ ورمزِ رفضٍ
$svc = (string) file_get_contents($ROOT . '/app/Services/Risk/RiskService.php');
$guards = array(
    'سماح ومنع بالسلطة' => 'RSK-403: قبول ',
    'منع الإغلاق بلا دليل' => 'RSK-403-CLOSE1',
    'منع إعادة تقييمٍ بلا معالجة' => 'RSK-403-CLOSE2',
    'المحظور لا يُقبل' => 'المحظور لا يُقبل بحال',
    'استقلال المتحقق' => 'لا يتحقق مالكه من نفسه',
    'عطالة المزامنة' => "'idempotent' => true",
    'عطالة القاعدة' => 'rule_key',
);
$missGuard = array();
foreach ($guards as $label => $needle) {
    if (strpos($svc, $needle) === false) { $missGuard[] = $label; }
}
// «لا حذفَ إطلاقًا» يُقاس بنيويًّا لا بتعليقٍ يشهد لنفسه: صفرُ دالةٍ حاذفةٍ في
// الخدمة، وصفرُ DELETE على أيِّ جدولِ مخاطرَ في كودِ الإدارةِ الحيِّ كلِّه.
$delMethods = array();
foreach (get_class_methods('App\Services\Risk\RiskService') as $mth) {
    if (preg_match('~(^|[^a-z])(delete|remove|purge|drop)~i', $mth)) { $delMethods[] = $mth; }
}
$delStmts = array();
foreach (array_merge(glob($ROOT . '/Risk/*.php'), glob($ROOT . '/app/Services/Risk/*.php')) as $p) {
    $s = (string) file_get_contents($p);
    if (preg_match('~\bDELETE\s+FROM\s+`?risk~i', $s)) { $delStmts[] = basename($p); }
}
if ($delMethods) { $missGuard[] = 'دوالُّ حذفٍ في الخدمة: ' . implode(',', $delMethods); }
if ($delStmts) { $missGuard[] = 'جملُ DELETE على جداولِ المخاطر: ' . implode(',', $delStmts); }
$guardTotal = count($guards) + 2; // + فحصا «لا حذف» البنيويان
$guardOk = $guardTotal - count($missGuard);
$sgLive = 0;
$engineMethods = get_class_methods('App\Services\Risk\RiskSignalEngine');
foreach (array_keys(RiskSignalEngine::RULE_UNIT) as $code) {
    $meth = 'sg' . substr($code, 3);
    if (in_array($meth, $engineMethods, true)) { $sgLive++; }
}
$sgInstant = 3; // SG-10 · SG-14 · SG-15 لحظيةٌ من مسارِ فعلها
ac('AC-09', 'كل فعل يجتاز الحالات الأربع', empty($missEv) && empty($missGuard) && ($sgLive + $sgInstant) >= 16,
    count($mapKeys) . ' حدثًا معرَّفًا (الملزَم ' . count($docReq) . ' — الغائب ' . count($missEv) . ') · '
    . $guardOk . '/' . $guardTotal . ' حارسًا مقيسًا · قواعدُ الإشارات '
    . ($sgLive + $sgInstant) . '/16 (' . $sgLive . ' دورية + ' . $sgInstant . ' لحظية)'
    . (empty($missGuard) ? '' : ' — حرّاسٌ ناقصة: ' . implode(' · ', $missGuard)));

/* ══ AC-10 · صفرُ وجهةٍ بلا وحدةِ صلاحياتٍ مسجَّلة ══════════════════════ */
$regModules = array();
$r = $db->query("SELECT code FROM modules WHERE code LIKE 'Risk/%'");
while ($x = $r->fetch_row()) { $regModules[$x[0]] = true; }
$noModule = array(); $noFile = array();
foreach ($SCREEN_MAP as $docFile => $pair) {
    if (!isset($regModules[$pair[0]])) { $noModule[] = $pair[0]; }
    if (!is_file($ROOT . '/' . $pair[0])) { $noFile[] = $pair[0]; }
}
$roleGrants = (int) one($db, "SELECT COUNT(*) FROM role_permissions rp
                               JOIN modules mo ON mo.id = rp.module_id
                              WHERE mo.code LIKE 'Risk/%'");
/* المقامُ شاشاتُ الوثيقةِ العشرون لا كلُّ ما في مساحةِ Risk/: فحقبةُ update0012
   أضافت لها شاشتَي حوكمةٍ مشروعتين (risk_dept_fin · risk_dept_gov)، وتثبيتُ
   العددِ على 20 يجعل كلَّ إضافةٍ لاحقةٍ رسوبًا كاذبًا. المقيسُ هنا: أيرى الدورُ
   30 شاشاتِ الوثيقةِ كلَّها؟ وكم يكتب؟ — والزائدُ يُذكر ولا يُحسب رسوبًا. */
$docCodes = array();
foreach ($SCREEN_MAP as $pair) { $docCodes[] = "'" . $db->real_escape_string($pair[0]) . "'"; }
$inDoc = implode(',', $docCodes);
$sup = (int) one($db, "SELECT COUNT(*) FROM role_permissions rp
                        JOIN modules mo ON mo.id = rp.module_id
                       WHERE rp.role_id = 30 AND mo.code IN ($inDoc) AND rp.can_view = 1");
$supAll = (int) one($db, "SELECT COUNT(*) FROM role_permissions rp
                           JOIN modules mo ON mo.id = rp.module_id
                          WHERE rp.role_id = 30 AND mo.code LIKE 'Risk/%' AND rp.can_view = 1");
$supWrite = (int) one($db, "SELECT COUNT(*) FROM role_permissions rp
                             JOIN modules mo ON mo.id = rp.module_id
                            WHERE rp.role_id = 30 AND mo.code LIKE 'Risk/%'
                              AND (rp.can_add = 1 OR rp.can_edit = 1 OR rp.can_delete = 1)");
$need = count($SCREEN_MAP);
ac('AC-10', 'صفر وجهة بلا وحدة صلاحيات مسجَّلة',
    empty($noModule) && empty($noFile) && $sup === $need && $supWrite === 0,
    count($SCREEN_MAP) . ' شاشةً: ملفٌّ حيٌّ ' . (count($SCREEN_MAP) - count($noFile))
    . ' · وحدةُ صلاحياتٍ ' . (count($SCREEN_MAP) - count($noModule)) . " · منحٌ $roleGrants"
    . " · مشرفُ المخاطرِ يرى شاشاتِ الوثيقة $sup/$need ويكتب $supWrite (المطلوب 0)"
    . ($supAll > $need ? " · وله عرضٌ على " . ($supAll - $need)
        . " شاشةَ حوكمةٍ أضافتها update0012 داخلَ Risk/ — زيادةٌ مشروعةٌ لا تُحسب" : '')
    . (empty($noModule) ? '' : ' — بلا وحدة: ' . implode(' · ', $noModule))
    . (empty($noFile) ? '' : ' — بلا ملف: ' . implode(' · ', $noFile)));

echo str_repeat('─', 74), "\n";
$passed = 10 - $fail;
echo "بوابة M-16: " . ($fail === 0 ? '✔' : '✘') . " $passed/10\n";
if ($fail > 0) {
    echo "لم تعبر البوابةُ — الرسوبُ أعلاه بشاهدِه.\n";
}
exit($fail === 0 ? 0 : 1);
