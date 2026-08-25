<?php
/**
 * tools/repair01_w5_journey.php — رحلةُ الأصل (‏W05 §٦-أ)
 * ═══════════════════════════════════════════════════════════════════════════
 * **طلبُ إدخالِ أصل ← تحقُّقٌ من المصدر ← أمرُ تفتيشٍ وبطاقتُه ← كرتُ الأصل ←
 *   حقُّ استخدامٍ تشغيليّ ← إسنادٌ لموقع ← حركةٌ واستخدام ← رقابةٌ فنيّة ← خروج**
 * — **والجاهزيّةُ تتغيّر عند كلِّ محطّةٍ اشتقاقًا لا إدخالًا**.
 *
 * ◆ **والقبولُ يقيس الأثرَ التجاريَّ لا صفَّ الحدثِ المُنشَأ** (§46): عند كلِّ
 *   مستهلكٍ يُقاس رقمٌ يعنيه — كرتٌ يُمنَع قبلَ سندِه · ساعةٌ تُنسَب إلى أصلٍ
 *   قائم · حقُّ استخدامٍ يقرّر مَن يفوتر · جاهزيّةٌ تنخفض بواقعةِ توقُّفٍ
 *   حقيقيّة · أصلٌ خارجٌ لا يُسنَد.
 *
 * ◆ **والمحطّاتُ السالبةُ محطّاتٌ**: «لا كرتَ قبلَ التحقُّق» و«لا إسنادَ قبلَ
 *   التفعيل» و«لا إسنادَ بعدَ الخروجِ الدائم» و«لا عودةَ من خروجٍ دائم» تُقاس
 *   بالاستدعاءِ الفعليِّ ورمزِ الرفضِ — لا بقراءةِ شيفرةٍ ولا بدعوى.
 *
 * ◆ **والبياناتُ لا تبقى**: كلُّ ما تكتبه الرحلةُ داخلَ معاملةٍ تُرجَع؛ ودليلُها
 *   وحدَه يُكتب بعدَ الإرجاعِ في `repair01_w5_journey`.
 *
 * التشغيل: php tools/repair01_w5_journey.php
 * الخروج : 0 عبرت كلُّ المحطّات · 1 محطّةٌ لم تعبر
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/app/Services/Fleet/AssetLifecycleService.php';
require_once $ROOT . '/tools/lib/repair01_w5_scan.php';
require_once $ROOT . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("تعذّر الاتصال بالقاعدة\n"); }
$conn->set_charset('utf8mb4');
while (ob_get_level()) { ob_end_clean(); }
require_once $ROOT . '/app/Core/TenantRegistry.php';
require_once $ROOT . '/app/Core/TenantContext.php';
require_once $ROOT . '/app/Core/TenantDb.php';
use App\Services\Fleet\AssetLifecycleService as ALS;

$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) { return repair01_w5_one($conn, $sql); };

/* مُعرِّفُ الجولةِ من ساعةِ القاعدة **بدقّةِ الميكروثانية** — جولتانِ في الثانيةِ
   نفسِها تتقاسمان المُعرِّفَ فتقرأ البوّابةُ صفوفَهما جولةً واحدةً وتسقط (W04). */
$RUN  = 'W5J-' . (string) $one("SELECT DATE_FORMAT(NOW(6), '%Y%m%d%H%i%s%f')");
$MARK = '__w5_journey_' . $RUN . '__';

echo "═══════════ رحلةُ الأصل — REPAIR01 · W05 ═══════════\n";
/* ⚠ **مُعرِّفُ الجولةِ يُطبَع سطرًا مُرمَّزًا** لتقرأه البوّابةُ من المخرَجِ لا من
     «آخرِ صفٍّ في الجدول». وقراءةُ الآخِرِ ثغرةٌ مقيسة: رحلةٌ لم تنعقد أصلًا
     تترك دليلَ الجولةِ السابقةِ قائمًا، فتقرأ البوّابةُ عبورًا لم يقع. */
echo "RUN=$RUN\n";
echo "الجولة: $RUN\n\n";

$ST = array();
$add = function ($no, $station, $entity, $consumer, $expected, $measured, $effect, $readiness, $passed) use (&$ST) {
    $ST[] = array($no, $station, $entity, $consumer, $expected, $measured, $effect, $readiness, $passed ? 1 : 0);
};

/* أرضيّةُ الرحلة: كيانٌ ذو أصولٍ حيّة، وموقعٌ ومشروعٌ وثلاثةُ أشخاصٍ مختلفين */
$company = (int) $one("SELECT company_id FROM equipments GROUP BY company_id ORDER BY COUNT(*) DESC LIMIT 1");
/* ⚠ **الخروجُ برمزٍ غيرِ صفرٍ عند نقصِ الأرضيّة**: `exit("نصّ")` يطبع ويخرج
     **بصفر** — فتقرأ البوّابةُ نجاحًا لم يقع. والرحلةُ التي لم تنعقد ليست رحلةً عابرة. */
if ($company <= 0) { echo "✘ لا كيانَ ذا أصولٍ — الرحلةُ لا تُشغَّل على قاعدةٍ فارغة\n"; exit(1); }
$site    = (int) $one("SELECT id FROM sites WHERE company_id = $company AND is_deleted = 0 ORDER BY id LIMIT 1");
$project = (int) $one("SELECT id FROM project WHERE company_id = $company ORDER BY id DESC LIMIT 1");
$actors = array();
$r = $conn->query("SELECT id FROM employees WHERE company_id = $company ORDER BY id LIMIT 3");
while ($r && $x = $r->fetch_row()) { $actors[] = (int) $x[0]; }
if ($site <= 0 || $project <= 0 || count($actors) < 3) {
    echo "✘ أرضيّةٌ ناقصة (موقع $site · مشروع $project · أشخاص " . count($actors) . ") — الرحلةُ لا تُشغَّل\n";
    exit(1);
}
list($requester, $verifier, $approver) = $actors;
$DAY    = (string) $one("SELECT DATE_ADD(CURDATE(), INTERVAL 3660 DAY)");   /* يومٌ لا يزاحم بياناتٍ حيّة */
$PERIOD = substr($DAY, 0, 7);
$LATER  = (string) $one("SELECT DATE_ADD('" . $esc($DAY) . "', INTERVAL 20 DAY)");
$BACK   = (string) $one("SELECT DATE_ADD('" . $esc($DAY) . "', INTERVAL 40 DAY)");
$GONE   = (string) $one("SELECT DATE_ADD('" . $esc($DAY) . "', INTERVAL 60 DAY)");

ALS::setEventConnection($conn);
$GATE = new \App\Core\TenantDb($conn, \App\Core\TenantContext::forSystem($company, $requester, '', true));

$conn->query('SET autocommit = 0');
$conn->query('START TRANSACTION');
$ok = true;

/** الجاهزيّةُ المشتقّةُ الآن — حالةُ الدورةِ ونسبةُ الجاهزيّةِ من الساعاتِ المقيسة */
$readinessNow = function ($eqId, $asOf, $hours = null) use ($GATE) {
    list($state, ) = ALS::deriveLifecycle($GATE, $eqId, $asOf);
    if ($hours === null) { return $state; }
    $f = ALS::readinessFormula($hours['shift'], $hours['executed'], $hours['standby'], $hours['fault'], $hours['stop']);
    return $state . ' · ' . $f['readiness'] . '٪';
};

/* ═══ ① طلبُ إدخالِ أصل ══════════════════════════════════════════════════ */
$no = 'W5J-' . substr($RUN, 4);
$r1 = ALS::openIntake($GATE, array(
    'intake_no' => $no, 'requested_dept' => 'DEP-04', 'asset_kind' => 'معدة ثقيلة',
    'source_type' => 'financed', 'requested_by' => $requester, 'source_ref' => $MARK));
$intakeId = (int) $r1['intake_id'];
$iRows = (int) $one("SELECT COUNT(*) FROM asset_intake WHERE company_id = $company
                      AND intake_no = '" . $esc($no) . "' AND state = 'submitted' AND requested_by = $requester");
$p1 = ($r1['ok'] && $intakeId > 0 && $iRows === 1);
$add(1, 'طلبُ إدخالِ أصل', 'asset_intake', '04 إدارة الأسطول والأصول · asset_intake',
     'صفُّ طلبٍ واحدٌ بحالةِ submitted وبكيانٍ قانونيٍّ وطالبٍ معروف',
     $p1 ? "intake_id=$intakeId · صفوفٌ مطابقة=$iRows" : 'فشل: ' . $r1['reason'],
     $p1 ? 'دورةُ الأصلِ بدأت بسندٍ مكتوبٍ — لا كرتَ يُنشأ بلا طلبٍ يسبقه' : '—',
     'PRE_CARD · لا أصلَ بعد', $p1);
$ok = $ok && $p1;

/* ═══ ② عطالةُ الطلبِ بمفتاحِه ══════════════════════════════════════════ */
$r2 = ALS::openIntake($GATE, array(
    'intake_no' => $no, 'requested_dept' => 'DEP-04', 'requested_by' => $requester));
$iCount = (int) $one("SELECT COUNT(*) FROM asset_intake WHERE company_id = $company AND intake_no = '" . $esc($no) . "'");
$p2 = ($r2['ok'] && (int) $r2['intake_id'] === $intakeId && $iCount === 1 && $r2['created'] === false);
$add(2, 'عطالةُ الطلبِ بمفتاحِه', 'asset_intake', '04 إدارة الأسطول والأصول · uq_asset_intake',
     'النداءُ الثاني يعيد الطلبَ نفسَه ولا يُنشئ صفًّا ثانيًا',
     $p2 ? "أُعيد intake_id=$intakeId · صفوفُ الحبّة=$iCount" : 'created=' . var_export($r2['created'], true) . " · صفوف=$iCount",
     $p2 ? 'طلبٌ واحدٌ لكلِّ (كيان × رقمِ طلب) — لا ازدواجَ في الحبّة' : '—',
     'PRE_CARD · لا أصلَ بعد', $p2);
$ok = $ok && $p2;

/* ═══ ③ محطّةٌ سالبة: كرتٌ قبلَ التحقُّقِ من المصدرِ يُرفَض ════════════ */
$eqId = 0;
try {
    $eqId = (int) $GATE->insert('equipments', array(
        'code' => 'W5J' . substr($RUN, 4, 12), 'name' => 'أصلُ رحلةِ الإثبات',
        'type' => 'معدة ثقيلة', 'card_state' => 'draft', 'status' => 1,
        'general_notes' => $MARK, 'created_by' => $requester));
} catch (\Throwable $t) { $eqId = 0; }
$c3 = ALS::issueCard($GATE, $intakeId, $eqId, $approver);
$stAfter3 = (string) $one("SELECT state FROM asset_intake WHERE id = $intakeId");
$p3 = ($eqId > 0 && !$c3['ok'] && $c3['reason_code'] === 'SOURCE_NOT_VERIFIED' && $stAfter3 === 'submitted');
$add(3, 'كرتٌ قبلَ التحقُّقِ من المصدرِ يُرفَض', 'equipments', '04 إدارة الأسطول والأصول · issueCard',
     'الخدمةُ تُرجع 409 برمزِ SOURCE_NOT_VERIFIED وحالةُ الطلبِ لا تتقدَّم',
     $p3 ? 'الرمز=' . $c3['reason_code'] . " · الحالةُ بعده=$stAfter3 · asset_id=$eqId"
         : 'الرمز=' . $c3['reason_code'] . " · الحالة=$stAfter3 · asset_id=$eqId",
     $p3 ? 'لا يُنشأ كرتُ أصلٍ قبل اجتيازِ التحقُّق — والمنعُ فعلٌ لا نصح' : '—',
     'PRE_CARD · الكرتُ ممنوع', $p3);
$ok = $ok && $p3;

/* ═══ ④ التحقُّقُ من المصدرِ ═══════════════════════════════════════════ */
$r4 = ALS::verifySource($GATE, $intakeId, array(
    'doc_type' => 'شهادة ملكية', 'doc_ref' => 'DOC-' . substr($RUN, 4, 10),
    'owner_declared' => 'الشركة الممولة', 'owner_legal' => 'الشركة الممولة',
    'verify_result' => 'passed', 'verified_by' => $verifier));
$stAfter4 = (string) $one("SELECT state FROM asset_intake WHERE id = $intakeId");
$passN = (int) $one("SELECT COUNT(*) FROM asset_source_check WHERE intake_id = $intakeId AND verify_result = 'passed'");
$p4 = ($r4['ok'] && $stAfter4 === 'source_verified' && $passN === 1);
$add(4, 'التحقُّقُ من مصدرِ الأصل', 'asset_source_check', '04 إدارة الأسطول والأصول · asset_source_check',
     'واقعةُ تحقُّقٍ مجتازةٌ بمرجعِ مستندٍ مكتوب، وحالةُ الطلبِ تتقدَّم',
     $p4 ? "وقائعُ مجتازة=$passN · الحالة=$stAfter4" : 'فشل: ' . $r4['reason'] . " · الحالة=$stAfter4",
     $p4 ? 'سندُ الملكيّةِ مُثبَتٌ قبل أن يدخل الأصلُ السجلَّ — فلا أصلَ بلا مصدرٍ معروف' : '—',
     'SOURCE_VERIFIED · الكرتُ مأذون', $p4);
$ok = $ok && $p4;

/* ═══ ⑤ أمرُ التفتيشِ وبطاقتُه ═════════════════════════════════════════ */
$ordNo = 'W5JO-' . substr($RUN, 4, 12);
$r5 = ALS::orderInspection($GATE, array(
    'order_no' => $ordNo, 'intake_id' => $intakeId, 'reason' => 'intake',
    'due_date' => $DAY, 'ordered_by' => $approver));
$orderId = (int) $r5['order_id'];
$inspId = 0;
try {
    $inspId = (int) $GATE->insert('mnt_inspection', array(
        'code' => 'W5JI-' . substr($RUN, 4, 12), 'inspection_type' => 'فحص الاستلام الفني',
        'equipment_id' => $eqId, 'inspector_id' => $verifier, 'scheduled_date' => $DAY,
        'overall_result' => 'مقبول', 'state' => 'مكتمل', 'notes' => $MARK, 'created_by' => $verifier));
} catch (\Throwable $t) { $inspId = 0; }
$r5b = ALS::recordInspectionCard($GATE, $orderId, $inspId, 'مقبول');
$ordState = (string) $one("SELECT state FROM asset_inspection_order WHERE id = $orderId");
$ordCard  = (int) $one("SELECT COALESCE(inspection_id,0) FROM asset_inspection_order WHERE id = $orderId");
$stAfter5 = (string) $one("SELECT state FROM asset_intake WHERE id = $intakeId");
$p5 = ($r5['ok'] && $orderId > 0 && $inspId > 0 && $r5b['ok'] && $ordState === 'executed'
       && $ordCard === $inspId && $stAfter5 === 'inspected');
$add(5, 'أمرُ التفتيشِ وبطاقتُه', 'asset_inspection_order', '14 إدارة الصيانة · mnt_inspection',
     'أمرٌ يصدر بسببٍ من الخمسةِ ثمّ يُنفَّذ ببطاقةٍ محفوظةٍ — ولا تنفيذَ بلا بطاقة',
     $p5 ? "order_id=$orderId · بطاقة=$inspId · حالةُ الأمر=$ordState · حالةُ الطلب=$stAfter5"
         : "order=$orderId · بطاقة=$inspId · حالةُ الأمر=$ordState · حالةُ الطلب=$stAfter5",
     $p5 ? 'الصيانةُ تستلم أمرًا مرقَّمًا بسببٍ معياريّ — والحالةُ الفنيّةُ مُثبَتةٌ ببطاقةٍ تُقارَن بسابقتِها' : '—',
     'INSPECTED · الفحصُ مُثبَت', $p5);
$ok = $ok && $p5;

/* ═══ ⑥ كرتُ الأصل ═══════════════════════════════════════════════════ */
$c6 = ALS::issueCard($GATE, $intakeId, $eqId, $approver);
$lcAfter6 = (string) $one("SELECT lifecycle_state FROM equipments WHERE id = $eqId");
$lcRule6  = (string) $one("SELECT lifecycle_rule FROM equipments WHERE id = $eqId");
$linked6  = (int) $one("SELECT COALESCE(intake_id,0) FROM equipments WHERE id = $eqId");
$p6 = ($c6['ok'] && $lcAfter6 === 'card_issued' && $lcRule6 !== '' && $linked6 === $intakeId);
$add(6, 'كرتُ الأصل', 'equipments', '04 إدارة الأسطول والأصول · equipments',
     'الكرتُ يصدر بعدَ سندِه ويُوصَل بطلبِه، وحالتُه مشتقّةٌ بقاعدةٍ مكتوبةٍ في الصفّ',
     $p6 ? "الحالة=$lcAfter6 · القاعدة=$lcRule6 · موصولٌ بالطلب=$linked6"
         : 'فشل: ' . $c6['reason'] . " · الحالة=$lcAfter6",
     $p6 ? 'الأصلُ صار كيانًا في السجلِّ بهويّةٍ ومصدرٍ مُثبَتَين — أساسُ كلِّ نسبةِ ساعةٍ بعده' : '—',
     $readinessNow($eqId, $DAY), $p6);
$ok = $ok && $p6;

/* ═══ ⑦ محطّةٌ سالبة: إسنادٌ قبلَ التفعيلِ يُرفَض ═══════════════════════ */
$a7 = ALS::assignAsset($GATE, array(
    'equipment_id' => $eqId, 'site_id' => $site, 'project_id' => $project,
    'valid_from' => $DAY, 'assigned_by' => $approver, 'decision_ref' => $MARK));
$asg7 = (int) $one("SELECT COUNT(*) FROM asset_assignment WHERE equipment_id = $eqId");
$p7 = (!$a7['ok'] && $a7['reason_code'] === 'ASSET_NOT_ACTIVE' && $asg7 === 0);
$add(7, 'إسنادٌ قبلَ التفعيلِ يُرفَض', 'asset_assignment', '04 إدارة الأسطول والأصول · assignAsset',
     'الخدمةُ تُرجع 409 برمزِ ASSET_NOT_ACTIVE ولا صفَّ إسنادٍ يُكتب',
     $p7 ? 'الرمز=' . $a7['reason_code'] . " · صفوفُ الإسناد=$asg7"
         : 'الرمز=' . $a7['reason_code'] . " · صفوف=$asg7",
     $p7 ? 'التفعيلُ يسبق الإسنادَ — فلا تُنسَب ساعةُ موقعٍ إلى أصلٍ لم يدخل الخدمةَ بعد' : '—',
     $readinessNow($eqId, $DAY), $p7);
$ok = $ok && $p7;

/* ═══ ⑧ التفعيلُ — الجاهزيّةُ تتغيّر اشتقاقًا ═══════════════════════════ */
$r8 = ALS::activateAsset($GATE, $intakeId, $approver);
$lcAfter8 = (string) $one("SELECT lifecycle_state FROM equipments WHERE id = $eqId");
$stAfter8 = (string) $one("SELECT state FROM asset_intake WHERE id = $intakeId");
$p8 = ($r8['ok'] && $lcAfter8 === 'active' && $stAfter8 === 'activated');
$add(8, 'التفعيلُ وإعادةُ الخدمة', 'asset_intake', '04 إدارة الأسطول والأصول · equipments',
     'الحالةُ تنتقل إلى activated والكرتُ يصير نشِطًا — والانتقالُ مشتقٌّ لا مُدخَل',
     $p8 ? "حالةُ الطلب=$stAfter8 · حالةُ الكرت=$lcAfter8" : 'فشل: ' . $r8['reason'] . " · $lcAfter8",
     $p8 ? 'الأصلُ صار قابلًا للإسنادِ ولتسجيلِ ساعةٍ عليه — وهي أوّلُ لحظةٍ يصير فيها مصدرَ إيراد' : '—',
     $readinessNow($eqId, $DAY), $p8);
$ok = $ok && $p8;

/* ═══ ⑨ حقُّ الاستخدامِ التشغيليّ — والتزامنُ يُقاس ═══════════════════════ */
$g9a = ALS::grantUseRight($GATE, array(
    'equipment_id' => $eqId, 'holder_kind' => 'company', 'holder_ref_id' => $company,
    'holder_name' => 'الكيان المشغِّل', 'percent' => 100, 'valid_from' => $DAY,
    'doc_ref' => 'UR-' . substr($RUN, 4, 10), 'granted_by' => $approver));
$g9b = ALS::grantUseRight($GATE, array(
    'equipment_id' => $eqId, 'holder_kind' => 'financier', 'holder_ref_id' => 9999,
    'holder_name' => 'مموِّلٌ يدّعي النافذةَ نفسَها', 'percent' => 60, 'valid_from' => $DAY,
    'doc_ref' => 'UR2-' . substr($RUN, 4, 10), 'granted_by' => $approver));
$urN   = (int) $one("SELECT COUNT(*) FROM asset_use_right WHERE equipment_id = $eqId");
$urOpen = (int) $one("SELECT COUNT(*) FROM asset_use_right WHERE equipment_id = $eqId
                       AND concurrency_rule = 'W5_CONCURRENT_CLAIM_OPEN'");
$p9 = ($g9a['ok'] && $g9b['ok'] && $urN === 2 && $urOpen === 1
       && abs((float) $g9b['concurrency_pct'] - 160.0) < 0.01);
$add(9, 'حقُّ الاستخدامِ التشغيليِّ وقياسُ تزامنِه', 'asset_use_right', '03 إدارة التمويل والممولين · asset_use_right',
     'حقّانِ في النافذةِ نفسِها ⇒ المجموعُ ١٦٠٪ والثاني يُوسَم W5_CONCURRENT_CLAIM_OPEN ولا يُمنَع ولا يُدهَس',
     $p9 ? 'حقوق=' . $urN . ' · مفتوحٌ=' . $urOpen . ' · المجموعُ المقيس=' . $g9b['concurrency_pct'] . '٪'
         : 'حقوق=' . $urN . ' · مفتوح=' . $urOpen . ' · المجموع=' . $g9b['concurrency_pct'],
     $p9 ? 'الادّعاءُ المزدوجُ لحقِّ استخدامِ آلةٍ واحدةٍ يُرصَد قبل أن يصير فاتورتَين لساعةٍ واحدة' : '—',
     $readinessNow($eqId, $DAY), $p9);
$ok = $ok && $p9;

/* ═══ ⑩ الإسنادُ لموقع ═════════════════════════════════════════════════ */
$a10 = ALS::assignAsset($GATE, array(
    'equipment_id' => $eqId, 'site_id' => $site, 'project_id' => $project,
    'valid_from' => $DAY, 'assigned_by' => $approver, 'decision_ref' => $MARK));
$asgActive = (int) $one("SELECT COUNT(*) FROM asset_assignment WHERE equipment_id = $eqId AND state = 'active'");
$asgSite   = (int) $one("SELECT COALESCE(site_id,0) FROM asset_assignment WHERE equipment_id = $eqId ORDER BY id DESC LIMIT 1");
$p10 = ($a10['ok'] && $asgActive === 1 && $asgSite === $site);
$add(10, 'الإسنادُ لموقعٍ ومشروع', 'asset_assignment', '12 إدارة الموقع · asset_assignment',
     'إسنادٌ نشِطٌ واحدٌ للأصلِ بفترتِه وموقعِه — ولا موقعانِ في اليومِ نفسِه',
     $p10 ? "إسنادٌ نشِط=$asgActive · الموقع=$asgSite" : 'فشل: ' . $a10['reason'] . " · نشِط=$asgActive",
     $p10 ? 'ساعةُ الأصلِ صارت تُحمَّل على موقعٍ ومشروعٍ معلومَين — وجهةُ التكلفةِ لا تُخمَّن' : '—',
     $readinessNow($eqId, $DAY), $p10);
$ok = $ok && $p10;

/* ═══ ⑪ الحركةُ والاستخدام — ساعةٌ تُنسَب عبرَ جسرِ التشغيلة ══════════════ */
$opId = 0;
try {
    $opId = (int) $GATE->insert('operations', array(
        'equipment' => (string) $eqId, 'equipment_type' => 'معدة ثقيلة', 'project_id' => (string) $project,
        'shift_hours' => 12, 'target_daily_hours' => 10, 'shift_type' => 'D',
        'status' => 1, 'op_state' => 'تعمل', 'reason' => $MARK));
} catch (\Throwable $t) { $opId = 0; }
$tsId = 0;
try {
    $tsId = (int) $GATE->insert('timesheet', array(
        'operator' => (string) $opId, 'employee_id' => (string) $requester, 'shift' => 'D',
        'date' => $DAY, 'shift_hours' => 12, 'executed_hours' => 9, 'standby_hours' => 1,
        'maintenance_fault' => 2, 'total_fault_hours' => 2, 'type' => '1',
        'time_notes' => $MARK, 'user_id' => $requester, 'status' => 1));
} catch (\Throwable $t) { $tsId = 0; }
$bridge = repair01_w5_asset_bridge($conn);
$bridged = (isset($bridge[$opId]) && $bridge[$opId] === $eqId);
$h11 = array('shift' => 12.0, 'executed' => 9.0, 'standby' => 1.0, 'fault' => 2.0, 'stop' => 0.0);
$f11 = ALS::readinessFormula(12, 9, 1, 2, 0);
$p11 = ($opId > 0 && $tsId > 0 && $bridged && $f11['readiness'] > 0);
$add(11, 'الحركةُ والاستخدامُ — ساعةٌ تُنسَب لأصلِها', 'timesheet', '11 إدارة التشغيل · timesheet',
     'ساعةُ التايم شيتِ تُحَلُّ إلى أصلِها عبرَ جسرِ التشغيلةِ لا بقراءةِ العمودِ مباشرةً',
     $p11 ? "operation=$opId · timesheet=$tsId · الجسرُ يحلُّ إلى asset=$eqId · جاهزيّة=" . $f11['readiness'] . '٪'
          : "operation=$opId · timesheet=$tsId · الجسرُ يحلّ=" . var_export($bridged, true),
     $p11 ? 'تسعُ ساعاتِ تشغيلٍ منفَّذةٍ نُسبت إلى الأصلِ الصحيحِ — وعليها يُبنى الاستحقاقُ والإهلاكُ بالساعات' : '—',
     $readinessNow($eqId, $DAY, $h11), $p11);
$ok = $ok && $p11;

/* ═══ ⑫ الرقابةُ الفنيّة — واقعةُ توقُّفٍ تخصم من الجاهزيّة ═══════════════ */
$h12 = array('shift' => 12.0, 'executed' => 9.0, 'standby' => 1.0, 'fault' => 2.0, 'stop' => 5.0);
$f12 = ALS::readinessFormula(12, 9, 1, 2, 5);
$rdId = ALS::writeReadiness($GATE, $eqId, $PERIOD, $h12);
$rdPct = (string) $one("SELECT readiness_pct FROM asset_readiness WHERE equipment_id = $eqId AND period = '" . $esc($PERIOD) . "'");
$rdRule = (string) $one("SELECT derivation_rule FROM asset_readiness WHERE equipment_id = $eqId AND period = '" . $esc($PERIOD) . "'");
$p12 = ($rdId > 0 && abs((float) $rdPct - $f12['readiness']) < 0.01 && $rdRule !== ''
        && $f12['readiness'] < $f11['readiness']);
$add(12, 'الرقابةُ الفنيّةُ — التوقُّفُ يخصم من الجاهزيّة', 'asset_readiness', '04 إدارة الأسطول والأصول · asset_readiness',
     'خمسُ ساعاتِ توقُّفٍ تُخفض الجاهزيّةَ المشتقّةَ عن قيمتِها قبلَها — والقاعدةُ مكتوبةٌ في الصفّ',
     $p12 ? 'قبلَ التوقُّف=' . $f11['readiness'] . '٪ · بعدَه=' . $rdPct . '٪ · القاعدة=' . $rdRule
          : 'المخزَّن=' . $rdPct . ' · المقيس=' . $f12['readiness'] . ' · القاعدة=' . $rdRule,
     $p12 ? 'الجاهزيّةُ انخفضت بواقعةِ توقُّفٍ حقيقيّةٍ لا بإدخالٍ — والرقمُ يعود إلى ساعاتِه' : '—',
     $readinessNow($eqId, $DAY, $h12), $p12);
$ok = $ok && $p12;

/* ═══ ⑬ الخروجُ المؤقّت — الإسنادُ يُنهى والحالةُ تتغيّر ══════════════════ */
$x13 = ALS::exitAsset($GATE, array(
    'equipment_id' => $eqId, 'exit_kind' => 'temporary', 'reason_code' => 'ورشة خارجية',
    'exit_date' => $LATER, 'expected_return' => $BACK, 'decided_by' => $approver, 'doc_ref' => $MARK));
$exitId = (int) $x13['exit_id'];
$asgEnded = (int) $one("SELECT COUNT(*) FROM asset_assignment WHERE equipment_id = $eqId AND state = 'ended'");
$lc13 = (string) $one("SELECT lifecycle_state FROM equipments WHERE id = $eqId");
$p13 = ($x13['ok'] && $exitId > 0 && $asgEnded === 1 && $lc13 === 'out_temporary');
$add(13, 'الخروجُ المؤقّت', 'asset_exit', '04 إدارة الأسطول والأصول · asset_exit',
     'واقعةُ خروجٍ مؤقّتةٍ بعودةٍ متوقَّعة، وكلُّ إسنادٍ نشِطٍ يُنهى بسببِه',
     $p13 ? "exit_id=$exitId · إسنادٌ مُنهًى=$asgEnded · الحالة=$lc13"
          : 'فشل: ' . $x13['reason'] . " · مُنهًى=$asgEnded · الحالة=$lc13",
     $p13 ? 'أصلٌ خارجٌ لا يبقى محسوبًا في موقعٍ يعمل — فلا تُحمَّل عليه ساعةُ موقعٍ غادره' : '—',
     $readinessNow($eqId, $LATER), $p13);
$ok = $ok && $p13;

/* ═══ ⑭ العودةُ من الخروجِ المؤقّت ═══════════════════════════════════════ */
$r14 = ALS::returnAsset($GATE, $exitId, $BACK, $approver);
$exState = (string) $one("SELECT state FROM asset_exit WHERE id = $exitId");
$lc14 = (string) $one("SELECT lifecycle_state FROM equipments WHERE id = $eqId");
$p14 = ($r14['ok'] && $exState === 'returned' && $lc14 === 'active');
$add(14, 'العودةُ من الخروجِ المؤقّت', 'asset_exit', '04 إدارة الأسطول والأصول · equipments',
     'العودةُ تُسجَّل في واقعتِها لا في كرتٍ جديد، والحالةُ تعود active اشتقاقًا',
     $p14 ? "حالةُ الواقعة=$exState · حالةُ الكرت=$lc14" : 'فشل: ' . $r14['reason'] . " · $exState / $lc14",
     $p14 ? 'الأصلُ عاد بهويّتِه وتاريخِه كاملَين — ولم يُنشأ له كرتٌ ثانٍ يشطر تاريخَه' : '—',
     $readinessNow($eqId, $BACK), $p14);
$ok = $ok && $p14;

/* ═══ ⑮ الخروجُ الدائمُ — بمرجعٍ ماليٍّ لا بدونه ═══════════════════════ */
$x15bad = ALS::exitAsset($GATE, array(
    'equipment_id' => $eqId, 'exit_kind' => 'permanent', 'reason_code' => 'استبعاد',
    'exit_date' => $GONE, 'decided_by' => $approver));
$x15 = ALS::exitAsset($GATE, array(
    'equipment_id' => $eqId, 'exit_kind' => 'permanent', 'reason_code' => 'استبعاد',
    'exit_date' => $GONE, 'finance_ref' => 'FIN-' . substr($RUN, 4, 10),
    'disposal_kind' => 'بيع', 'decided_by' => $approver, 'doc_ref' => $MARK));
$lc15 = (string) $one("SELECT lifecycle_state FROM equipments WHERE id = $eqId");
$permN = (int) $one("SELECT COUNT(*) FROM asset_exit WHERE equipment_id = $eqId AND exit_kind = 'permanent'");
$p15 = (!$x15bad['ok'] && $x15['ok'] && $permN === 1 && $lc15 === 'retired');
$add(15, 'الخروجُ الدائمُ بمرجعِه الماليّ', 'asset_exit', '05 الإدارة المالية · asset_exit',
     'الخروجُ الدائمُ بلا مرجعٍ ماليٍّ يُردُّ 422، وبمرجعِه يُسجَّل مرّةً واحدةً والحالةُ تصير retired',
     $p15 ? 'بلا مرجعٍ: ' . $x15bad['code'] . " · بمرجعٍ: صفوف=$permN · الحالة=$lc15"
          : 'بلا مرجع=' . $x15bad['code'] . " · صفوف=$permN · الحالة=$lc15",
     $p15 ? 'الأصلُ خرج من الدفترِ بمستندٍ ماليٍّ — فيتوقّف إهلاكُه ويُثبَت استبعادُه ولا يبقى في مقامِ الجاهزيّة' : '—',
     $readinessNow($eqId, $GONE), $p15);
$ok = $ok && $p15;

/* ═══ ⑯ محطّتانِ سالبتان: لا إسنادَ بعدَ الخروجِ الدائمِ ولا عودةَ منه ═══ */
$a16 = ALS::assignAsset($GATE, array(
    'equipment_id' => $eqId, 'site_id' => $site, 'valid_from' => $GONE,
    'assigned_by' => $approver, 'decision_ref' => $MARK));
$permId = (int) $one("SELECT id FROM asset_exit WHERE equipment_id = $eqId AND exit_kind = 'permanent' LIMIT 1");
$r16 = ALS::returnAsset($GATE, $permId, $GONE, $approver);
$asgAfter = (int) $one("SELECT COUNT(*) FROM asset_assignment WHERE equipment_id = $eqId AND state = 'active'");
$p16 = (!$a16['ok'] && $a16['reason_code'] === 'ASSET_PERMANENTLY_EXITED'
        && !$r16['ok'] && $r16['reason_code'] === 'PERMANENT_EXIT_NO_RETURN' && $asgAfter === 0);
$add(16, 'أصلٌ خرج دائمًا لا يُسنَد ولا يعود', 'asset_assignment', '04 إدارة الأسطول والأصول · الحارس',
     'الإسنادُ يُردُّ ASSET_PERMANENTLY_EXITED والعودةُ تُردُّ PERMANENT_EXIT_NO_RETURN ولا صفَّ نشِطٌ يبقى',
     $p16 ? 'الإسناد=' . $a16['reason_code'] . ' · العودة=' . $r16['reason_code'] . " · نشِط=$asgAfter"
          : 'الإسناد=' . $a16['reason_code'] . ' · العودة=' . $r16['reason_code'] . " · نشِط=$asgAfter",
     $p16 ? 'الأصلُ المستبعَدُ لا يعود إلى الخدمةِ بصمت — والعائدُ بعده كرتٌ جديدٌ بطلبِ إدخالٍ جديد' : '—',
     $readinessNow($eqId, $GONE), $p16);
$ok = $ok && $p16;

/* ═══ الإرجاع — لا تبقى بياناتُ الرحلةِ في القاعدة ═════════════════════════ */
$conn->query('ROLLBACK');
$conn->query('SET autocommit = 1');

$left = (int) $one("SELECT COUNT(*) FROM asset_intake WHERE source_ref = '" . $esc($MARK) . "'")
      + (int) $one("SELECT COUNT(*) FROM equipments WHERE general_notes = '" . $esc($MARK) . "'")
      + (int) $one("SELECT COUNT(*) FROM asset_exit WHERE doc_ref = '" . $esc($MARK) . "'")
      + (int) $one("SELECT COUNT(*) FROM operations WHERE reason = '" . $esc($MARK) . "'")
      + (int) $one("SELECT COUNT(*) FROM timesheet WHERE time_notes = '" . $esc($MARK) . "'");
if ($left !== 0) { echo "⚠ بقيت $left صفًّا من الرحلةِ بعدَ الإرجاع — عالجْ قبلَ الاعتمادِ على النتيجة\n"; $ok = false; }

/* ═══ الدليلُ يُكتب بعدَ الإرجاع ═══════════════════════════════════════════ */
foreach ($ST as $s) {
    $conn->query("INSERT INTO repair01_w5_journey
        (run_id, station_no, station, entity, consumer, expected, measured, business_effect, readiness_after, passed)
        VALUES ('" . $esc($RUN) . "'," . (int) $s[0] . ",'" . $esc($s[1]) . "','" . $esc($s[2]) . "','" . $esc($s[3]) . "',
                '" . $esc(mb_substr($s[4], 0, 380)) . "','" . $esc(mb_substr($s[5], 0, 380)) . "',
                '" . $esc(mb_substr($s[6], 0, 380)) . "','" . $esc(mb_substr($s[7], 0, 78)) . "'," . (int) $s[8] . ")");
}

$pass = 0;
foreach ($ST as $s) { if ($s[8] === 1) { $pass++; } else { printf("  ✘ %2d %s — %s\n", $s[0], $s[1], $s[5]); } }
foreach ($ST as $s) { if ($s[8] === 1) { printf("  ✔ %2d %-46s %s\n", $s[0], $s[1], mb_substr($s[7], 0, 40)); } }
$states = array_unique(array_map(function ($s) { return $s[7]; }, $ST));
echo str_repeat('─', 100) . "\n";
printf("رحلةُ الأصل: %d/%d محطّةً · مستهلكونَ متمايزون %d · حالاتُ جاهزيّةٍ متمايزة %d · بلا أثرٍ تجاريٍّ مقيسٍ %d\n",
    $pass, count($ST), count(array_unique(array_map(function ($s) { return $s[3]; }, $ST))),
    count($states), count(array_filter($ST, function ($s) { return $s[6] === '—' || $s[6] === ''; })));
echo 'الحكم: ' . ($ok && $pass === count($ST) ? "عبرت ✔\n" : "لم تعبر ✘\n");
exit(($ok && $pass === count($ST)) ? 0 : 1);
