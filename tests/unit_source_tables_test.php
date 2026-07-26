<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * D02 §15 المرحلة ١ — اختبار قبول جداول مصدر الحقيقة الثلاثة
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/unit_source_tables_test.php
 * رمز الخروج: 0 = أخضر · 1 = فشل.
 *
 * ما يُثبته (نصّ اختبار القبول في المهمّة):
 *   ① البنية منقولةٌ حرفيًّا عن الدستور: أعمدةٌ وأنواعٌ وقيمُ ENUM وفهارس
 *      — والحالات الثلاث عشرة موجودةٌ كلُّها في unit_entries.state.
 *   ② إدراجُ صفٍّ عبر البوابة لكلٍّ من الجداول الثلاثة ينجح.
 *   ③ استعلامٌ بشركةٍ أخرى **لا يراه** — العزلُ محقَّقٌ فعلًا لا مسجَّلٌ فقط.
 *   ④ الجداول مسجَّلةٌ في TenantRegistry (وإلا فشلت مغلقة).
 *   ⑤ FK السلسلة يمنع اعتمادًا لواقعةٍ لا وجود لها.
 *   ⑥ الوصلة المحجوزة source_kind='unit_record' تقبل مرجعَ واقعةٍ حقيقية
 *      — دون أن يُمسّ مسارُ timesheet القائم (تعايشٌ لا استبدال).
 *
 * كلُّ ما يُنشأ يُنظَّف في النهاية — حتى عند الفشل.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';

use App\Core\TenantContext;
use App\Core\TenantDb;
use App\Core\TenantRegistry;

// گوتشا CLI: config.php يفتح مخزنًا مؤقتًا يبتلع STDOUT والأخطاء القاتلة.
while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)   { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m)  { global $FAIL; $FAIL++; fwrite(STDOUT, "  �’ {$m}\n"); }
function check($cond, $m) { $cond ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$CO_A = 4;   // الشركة صاحبة البيانات الحيّة
$CO_B = 1;   // شركةٌ أخرى — لا يجوز أن ترى شيئًا
$MARK = 'D02P1_' . getmypid();

$conn = $GLOBALS['conn'];
$made = array('entries' => array(), 'logs' => array(), 'apps' => array(), 'awards' => array());

$teardown = function () use ($conn, $MARK) {
    // الترتيب عكسُ الإنشاء — والـFK بـCASCADE يكفل حذف الأبناء مع الأب.
    $conn->query("DELETE FROM unit_party_awards WHERE source_kind='unit_record' AND source_ref IN
                    (SELECT id FROM unit_entries WHERE entry_no LIKE '{$MARK}%')");
    $conn->query("DELETE FROM unit_time_log  WHERE sync_uuid LIKE '{$MARK}%'");
    $conn->query("DELETE FROM unit_approvals WHERE entry_id IN
                    (SELECT id FROM unit_entries WHERE entry_no LIKE '{$MARK}%')");
    $conn->query("DELETE FROM unit_entries   WHERE entry_no LIKE '{$MARK}%'");
};
register_shutdown_function($teardown);

fwrite(STDOUT, "\n══ D02 §15 المرحلة ١ — جداول مصدر الحقيقة ══\n");

// ═══ ① البنية منقولةٌ حرفيًّا عن الدستور ═══
head('① البنية — نقلٌ حرفيٌّ عن §3.1 · §3.3 · §4.2');

$colsOf = function ($t) use ($conn) {
    $out = array();
    $r = $conn->query("SHOW COLUMNS FROM `{$t}`");
    while ($x = $r->fetch_assoc()) { $out[$x['Field']] = $x['Type']; }
    return $out;
};

$e = $colsOf('unit_entries');
// + current_round بعد revision_no — ترحيل 2026_07_26 (قرار المالك: «الإعادة
//   بالرقم نفسه» UX-03 §8.2 تحتاج عدّادَ جولةٍ على الواقعة)
$spec31 = array('id','company_id','entry_no','entry_date','project_id','contract_id',
    'equipment_id','operator_employee_id','supplier_entity_id','supervisor_id','unit_type',
    'qty','record_basis','capacity_flag','shift','source_ref','txn_ref','note','state',
    'revision_no','current_round','revises_entry_id','revision_kind','superseded_by_id','converted_at',
    'event_id','entered_by','sync_uuid','created_at','updated_at');
check(array_keys($e) === $spec31, 'unit_entries: أعمدة §3.1 حرفًا بحرف + current_round (ترحيل UX-03 §8.2)');

// الحالات الثلاث عشرة — البند الذي كان «approval_level tinyint لا غير»
$states13 = array('draft','submitted','site_approved','parties_review','parties_approved',
    'sales_approved','on_hold','converted','superseded','reversed','returned','rejected','cancelled');
preg_match_all("/'([^']+)'/", $e['state'], $m13);
check($m13[1] === $states13, 'unit_entries.state: الحالات الثلاث عشرة كاملةً بترتيب §3.1');
check(count($m13[1]) === 13, 'عددها ثلاث عشرة — لا tinyint ولا مستوى اعتمادٍ رقمي');

$t = $colsOf('unit_time_log');
$spec33 = array('id','company_id','log_date','shift','project_id','equipment_id',
    'operator_employee_id','supplier_entity_id','time_from','time_to','hours','ops_state',
    'cause_note','resp_party','entry_id','entered_by','sync_uuid','created_at');
check(array_keys($t) === $spec33, 'unit_time_log: الأعمدة الثمانية عشر بترتيب §3.3 حرفًا بحرف');

preg_match_all("/'([^']+)'/", $t['ops_state'], $a1);
check($a1[1] === array('actual_work','standby','tech_breakdown','supplier_stop','operator_stop',
    'client_stop','fuel_logistics_stop','planned_stop','force_majeure','unlogged'),
    'unit_time_log.ops_state: العشر حالاتٍ كما في §3.3 حرفًا بحرف');

// ── تعارضٌ مرفوعٌ للمالك (لا يُصحَّح من عند المنفّذ) ──────────────────────────
// §3.3 و§3.8 في الدستور ينصّان على قاموسٍ واحدٍ من عشر قيمٍ آخرُها 'unlogged'.
// لكن contract_hour_policies المطبَّق فعلًا (22 صفًّا · يقرؤه EffectFanout ·
// محميٌّ بالقانون ٢) ينحرف: يُسقط 'unlogged' ويضيف 'pending_approval','other'.
// الأثر: محرّك الاشتقاق (§3.4) في المرحلة ③ سيبحث عن سياسةٍ لفترةٍ 'unlogged'
// فلا يجدها — وهو القطعُ الصامت بعينه. يُثبَّت الانحرافُ هنا صراحةً حتى لا
// يمرّ مجهولًا، ويُحسم بقرار المالك لا باجتهادٍ في الترميز.
$p = $colsOf('contract_hour_policies');
preg_match_all("/'([^']+)'/", $p['ops_state'], $a2);
$onlyLog = array_values(array_diff($a1[1], $a2[1]));
$onlyPol = array_values(array_diff($a2[1], $a1[1]));
check($onlyLog === array('unlogged') && $onlyPol === array('pending_approval', 'other'),
    'تعارضٌ موثَّقٌ معلومٌ: القاموسان يفترقان في unlogged ↔ pending_approval/other — مرفوعٌ للمالك');
check(count(array_intersect($a1[1], $a2[1])) === 9,
    'التسع المشتركة متطابقةٌ حرفًا بحرف (الاشتقاق يعمل عليها بلا قطع)');

$u = $colsOf('unit_approvals');
// + round_no بعد entry_id — ترحيل 2026_07_26 (حسمُ المسألة المرفوعة في تعليق
//   ترحيل الإنشاء: القيد كان يمنع اعتماد مرحلةٍ أعادت ثم استُكملت)
$spec42 = array('id','company_id','entry_id','round_no','stage','decision','actor_id','note','decided_at','sync_uuid');
check(array_keys($u) === $spec42, 'unit_approvals: أعمدة §4.2 حرفًا بحرف + round_no (قرار المالك 2026-07-26)');
preg_match_all("/'([^']+)'/", $u['stage'], $a3);
check($a3[1] === array('site','supplier','operator','supervisor','fleet','sales','finance'),
    'unit_approvals.stage: المراحل السبع كما في §4.2');

$idx = function ($t, $k) use ($conn) {
    $r = $conn->query("SHOW INDEX FROM `{$t}` WHERE Key_name='{$k}'");
    return $r && $r->num_rows > 0;
};
check($idx('unit_entries', 'uq_entry_no'), 'قيد تفرّد رقم الواقعة (company_id, entry_no)');
check($idx('unit_entries', 'uq_sync'), 'قيد تفرّد المزامنة sync_uuid — عطالةُ PWA بنيويةٌ مسبقًا');
check($idx('unit_time_log', 'uq_sync'), 'unit_time_log: قيد تفرّد المزامنة');
check($idx('unit_approvals', 'uq_stage_once_per_round'),
    'unit_approvals: قرارٌ واحدٌ لكل مرحلةٍ في الجولة (round_no — الإعادة بالرقم نفسه)');

// ═══ ④ التسجيل في بوابة العزل ═══
head('④ التسجيل في TenantRegistry — وإلا فشلت مغلقة');
foreach (array('unit_entries', 'unit_time_log', 'unit_approvals') as $tbl) {
    $def = TenantRegistry::get($tbl);
    check(is_array($def) && isset($def['type']) && $def['type'] === TenantRegistry::T_TENANT,
        "{$tbl}: مسجَّلٌ T_TENANT في البوابة");
}

// ═══ ② الإدراج عبر البوابة ═══
head('② الإدراج عبر البوابة — شركة ' . $CO_A);

$ctxA = TenantContext::forSystem($CO_A, 1, '', true);
$dbA  = new TenantDb($conn, $ctxA);

$entryId = $dbA->insert('unit_entries', array(
    'entry_no'     => $MARK . '-E1',
    'entry_date'   => '2026-07-18',
    'project_id'   => 1,
    'equipment_id' => 12,
    'unit_type'    => 'hour',
    'qty'          => 10.00,
    'state'        => 'draft',
));
check($entryId > 0, "unit_entries: أُدرجت الواقعة #{$entryId} (company_id مُحقَنٌ من البوابة)");

// گوتشا ENUM: الـENUM يبتلع غير المعرَّف صامتًا و@@sql_mode فارغ — نتحقق أن
// المخزَّن هو المُرسَل، لا نفترضه.
$r = $conn->query("SELECT company_id, unit_type, state, record_basis FROM unit_entries WHERE id={$entryId}");
$row = $r->fetch_assoc();
check((int)$row['company_id'] === $CO_A, 'company_id حُقن تلقائيًّا بالشركة الصحيحة');
check($row['unit_type'] === 'hour' && $row['state'] === 'draft' && $row['record_basis'] === 'contract',
    'المخزَّن = المُرسَل (ENUM لم يبتلع شيئًا صامتًا)');

$logId = $dbA->insert('unit_time_log', array(
    'log_date'     => '2026-07-18',
    'project_id'   => 1,
    'equipment_id' => 12,
    'hours'        => 6.00,
    'ops_state'    => 'actual_work',
    'resp_party'   => 'none',
    'entry_id'     => $entryId,
    'sync_uuid'    => $MARK . '-L1',
));
check($logId > 0, "unit_time_log: أُدرجت فترة الزمن #{$logId}");
$r = $conn->query("SELECT ops_state, resp_party FROM unit_time_log WHERE id={$logId}");
$row = $r->fetch_assoc();
check($row['ops_state'] === 'actual_work' && $row['resp_party'] === 'none',
    'unit_time_log: الحالة التشغيلية والجهة المسؤولة خُزّنتا كما أُرسلتا');

$appId = $dbA->insert('unit_approvals', array(
    'entry_id' => $entryId,
    'stage'    => 'site',
    'decision' => 'approved',
    'actor_id' => 1,
    'note'     => 'اختبار قبول المرحلة ١',
));
check($appId > 0, "unit_approvals: أُدرج قرار الموقع #{$appId}");

// ═══ ③ العزل — الشركة الأخرى لا ترى شيئًا ═══
head('③ العزل — استعلامُ شركةٍ أخرى لا يرى الصفوف');

$ctxB = TenantContext::forSystem($CO_B, 1, '', true);
$dbB  = new TenantDb($conn, $ctxB);

$n = count($dbB->scopedQuery(
    array('scope' => array('ue' => 'unit_entries')),
    "SELECT ue.id FROM unit_entries ue WHERE {TENANT_SCOPE} AND ue.entry_no LIKE ?",
    array($MARK . '%')
));
check($n === 0, "شركة {$CO_B}: لا ترى واقعة شركة {$CO_A} (رأت {$n} صفًّا)");

$n = count($dbB->scopedQuery(
    array('scope' => array('tl' => 'unit_time_log')),
    "SELECT tl.id FROM unit_time_log tl WHERE {TENANT_SCOPE} AND tl.sync_uuid LIKE ?",
    array($MARK . '%')
));
check($n === 0, "شركة {$CO_B}: لا ترى فترة زمن شركة {$CO_A} (رأت {$n} صفًّا)");

$n = count($dbB->scopedQuery(
    array('scope' => array('ua' => 'unit_approvals')),
    "SELECT ua.id FROM unit_approvals ua WHERE {TENANT_SCOPE} AND ua.entry_id = ?",
    array($entryId)
));
check($n === 0, "شركة {$CO_B}: لا ترى قرار اعتماد شركة {$CO_A} (رأت {$n} صفًّا)");

// والشركة المالكة تراها — وإلا كان «العزل» مجرّد عمًى شامل
$n = count($dbA->scopedQuery(
    array('scope' => array('ue' => 'unit_entries')),
    "SELECT ue.id FROM unit_entries ue WHERE {TENANT_SCOPE} AND ue.entry_no LIKE ?",
    array($MARK . '%')
));
check($n === 1, "شركة {$CO_A}: ترى واقعتها هي (رأت {$n})");

// ═══ ⑤ FK السلسلة ═══
head('⑤ سلامة السلسلة — لا اعتمادَ لواقعةٍ لا وجود لها');
$ghost = $conn->query("SELECT MAX(id)+9999 m FROM unit_entries")->fetch_assoc()['m'];
$conn->query("INSERT INTO unit_approvals (company_id, entry_id, stage, decision, actor_id)
              VALUES ({$CO_A}, {$ghost}, 'sales', 'approved', 1)");
check($conn->errno !== 0, 'FK رفض اعتمادًا لواقعةٍ غير موجودة (خطأ: ' . $conn->errno . ')');

// ═══ ⑥ الوصلة المحجوزة + تعايش المسار القديم ═══
head('⑥ الوصلة المصمَّمة سلفًا — source_kind=unit_record');

$tsBefore = (int)$conn->query("SELECT COUNT(*) c FROM timesheet")->fetch_assoc()['c'];
$awBefore = (int)$conn->query("SELECT COUNT(*) c FROM unit_party_awards WHERE source_kind='timesheet'")->fetch_assoc()['c'];

$awardId = $dbA->insert('unit_party_awards', array(
    'source_kind'       => 'unit_record',
    'source_ref'        => $entryId,
    'party'             => 'client',
    'party_ref'         => 1,
    'award_unit_type'   => 'hour',
    'award_qty'         => 8.00,
    'entitlement_state' => 'due',
    'entitlement_pct'   => 100.00,
));
check($awardId > 0, "حكمُ طرفٍ بمصدرٍ جديد #{$awardId} — الوصلة مفتوحة");
$row = $conn->query("SELECT source_kind, source_ref, qty_due FROM unit_party_awards WHERE id={$awardId}")->fetch_assoc();
check($row['source_kind'] === 'unit_record', "source_kind خُزّن 'unit_record' (لا سلسلةً فارغة)");
check((int)$row['source_ref'] === (int)$entryId, 'source_ref يشير إلى unit_entries.id');
check((float)$row['qty_due'] === 8.00, 'qty_due محسوبٌ خادميًّا = 8.00');

$tsAfter = (int)$conn->query("SELECT COUNT(*) c FROM timesheet")->fetch_assoc()['c'];
$awAfter = (int)$conn->query("SELECT COUNT(*) c FROM unit_party_awards WHERE source_kind='timesheet'")->fetch_assoc()['c'];
check($tsAfter === $tsBefore, "تعايش: timesheet لم يُمسّ ({$tsBefore} → {$tsAfter} صفًّا)");
check($awAfter === $awBefore, "تعايش: أحكامُ المسار القديم لم تُمسّ ({$awBefore} → {$awAfter})");

// ═══ التنظيف ═══
head('التنظيف');
$teardown();
$left = (int)$conn->query("SELECT COUNT(*) c FROM unit_entries WHERE entry_no LIKE '{$MARK}%'")->fetch_assoc()['c'];
check($left === 0, 'teardown: صفر بقايا');

fwrite(STDOUT, "\n" . str_repeat('═', 50) . "\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL > 0 ? 1 : 0);
