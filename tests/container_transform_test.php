<?php
/**
 * tests/container_transform_test.php — H-01 المرحلة ②
 * ═══════════════════════════════════════════════════════════════════════════
 * خدمةُ التحوّل من عقدٍ نافذٍ إلى حاوياتٍ (OPM-01 §4).
 *
 * ما يُثبته:
 *   ① **لا حاويةَ إلا من عقدٍ نافذ** — والتعريفُ من آلة الحالات لا من نصٍّ هنا.
 *   ② **السقفُ من `equip_total_contract` حصرًا** — لا من `contract_commitments`
 *      (ذاك التزامُ إتاحةٍ يوميّ؛ الخلطُ يجعل السقفَ 8 بدل 55,500).
 *   ③ **عطالةُ التوليد بنيوية**: النداءُ المكرَّر لا يُنشئ رئيسيةً ثانية.
 *   ④ **قيدُ Σ يحرسه المحرّك**: تخصيصٌ فوق المتاح يُرَدُّ **برسالةٍ تسمّي المتاحَ
 *      والمطلوب**، و**لا ابنٌ يُكتب ولا أبٌ يُزاد** (ذريّة).
 *   ⑤ **ترتيبُ الشجرة محروس**: لا يُخصَّص مشغّلٌ من حاوية مورد.
 *   ⑥ **الخصمُ ذريٌّ على السلسلة كلِّها** — أو لا شيء. وعطالتُه بمفتاحه.
 *      والردُّ حركةٌ **سالبةٌ بمفتاحٍ آخر** لا حذف.
 *   ⑦ **التوليدُ الرجعيُّ موسومٌ «مشتقّة»** بملاحظته — ولا يُقدَّم متفقًا عليه.
 *   ⑧ **التجميعُ بالمعدة لا بصفّ التشغيل** (المعدتان 5 و13 لهما صفّان لكلٍّ).
 *   ⑨ **والمشغّلُ الواحدُ تحت معدتين صحيحٌ ومقصود** — لكلِّ معدةٍ حصتُه منها.
 *   ⑩ **بندان بالوحدة نفسِها لا يُسحق أحدُهما** — المطابقةُ بنوع المعدة.
 *   ⑪ ما لا بندَ له يُعلَن في تقرير المطابقة ولا تُخترع له حاوية.
 *
 * يعمل على العقد 5 الحقيقي ويكنس كلَّ ما ينشئه.
 * التشغيل: php tests/container_transform_test.php — رمز الخروج 0/1.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '1', 'company_id' => 4, 'name' => 'transform test');
require_once dirname(__DIR__) . '/app/Services/Operations/OperationalTransformService.php';

use App\Services\Operations\OperationalTransformService as OTS;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }
function info($m) { fwrite(STDOUT, "     · {$m}\n"); }

$conn = $GLOBALS['conn'];
$gate = ems_tenant_db();
$CO = 4;
$C5 = 5;

/* ══ **الكنسُ كان يمحو الجدولَ كلَّه — ثم صار يفشل صمتًا.**
     كان: `DELETE FROM op_containers WHERE level='…'` بلا شرطِ عقدٍ ولا مشروع
     — أي **كلُّ حاوياتِ النظام**، وتعليقُ الفاحصِ يعترف: «الكنسُ يمحو حاوياتٍ
     حقيقيةً … فيُعاد توليدُها». وذاك كان يعمل حين كانت الفواحصُ المجاورةُ
     تنفجر مبكرًا فلا تُنشئ مراجعَ.
     **والآن `container_pilot_test` و`daily_plan_test` يكتملان** فيُنشئان
     `daily_plan_lines` تشير إلى الحاويات، فيُردُّ الحذفُ بمفتاحٍ أجنبيٍّ
     (`Cannot delete or update a parent row`) ويبقى الجدولُ كما هو — فيقرأ
     الفاحصُ «رئيسيتان موجودتان سلفًا» فيرسب هو، **ولو نجح الحذفُ لأهلك
     شجرةَ المشروعِ الرائدِ فأرسب جارَيه**. أي أن الطريقين كليهما خطأ.
   ⇒ الكنسُ **بالمرجعِ الذي يصنعه الفاحصُ وحدَه**: `origin_note` يحمل
     «سقفُ بند العقد #…» للمولَّدِ من `generateMain`، و`decision_ref`/`origin`
     للمشتقّ. فيُمحى ما وُلِّد للعقودِ الأربعةِ التي يعمل عليها **دون** ما بُني
     يدويًّا لشجرةِ مشروعٍ (وهو ما لا يحمل هذا الأثر).
     والتوابعُ تُمحى **بترتيبِ المفاتيح** أولًا وإلا رُدَّ الحذفُ. */
$CTRACTS = array(5, 2, 4, 7);
$cleanup = function () use ($conn, $CTRACTS) {
    $in = implode(',', array_map('intval', $CTRACTS));
    /* الحاوياتُ المولَّدةُ آليًّا لهذه العقود — لا ما بُني لشجرةِ مشروع */
    $sel = "SELECT id FROM (SELECT id FROM op_containers
              WHERE contract_id IN ({$in})
                AND (origin = 'مشتقّة' OR origin_note LIKE 'سقفُ بند العقد%')) x";
    /* التوابعُ بترتيبِ المفاتيح */
    $conn->query("DELETE FROM container_consumption WHERE container_id IN ({$sel})");
    $conn->query("DELETE FROM container_swaps WHERE container_id IN ({$sel})
                    OR to_container_id IN ({$sel})");
    $conn->query("DELETE FROM operator_rotations WHERE container_id IN ({$sel})");
    $conn->query("UPDATE daily_plan_lines SET equipment_container_id = NULL
                   WHERE equipment_container_id IN ({$sel})");
    $conn->query("UPDATE daily_plan_lines SET operator_container_id = NULL
                   WHERE operator_container_id IN ({$sel})");
    $conn->query("UPDATE monthly_performance SET container_id = NULL WHERE container_id IN ({$sel})");
    $conn->query("UPDATE seat_assignments SET container_id = NULL WHERE container_id IN ({$sel})");
    /* الأبناءُ قبلَ الآباءِ — من الأعمقِ إلى الجذر */
    foreach (array('مشغّل', 'معدة', 'نوع', 'مورد', 'رئيسية') as $lv) {
        $conn->query("DELETE FROM op_containers WHERE level='{$lv}' AND contract_id IN ({$in})
                        AND (origin = 'مشتقّة' OR origin_note LIKE 'سقفُ بند العقد%')");
    }
};
/* ⚠️ **الكنسُ أعلاه لا يمحو شيئًا — قِيس مُرجَعُه**: 16 صفًّا في
   `substitute_coverages` تشير إلى مرشَّحيه و`covered_seat_id` **غيرُ قابلٍ
   للتصفير** (NOT NULL)، و16 ابنًا من خارجِ المرشَّحين يعلّقون آباءَهم — فيردُّ
   FK كلَّ حذفٍ (`مشغّل` ثم الأبوَّةُ الذاتيةُ لِما فوقه) و**صفرٌ من 40 يُمحى**.
   فلا يُعتمَد عليه في صناعةِ لوحٍ أبيض: كلُّ فحصٍ يلزمه شجرةٌ نظيفةٌ صار يبنيها
   بنفسِه (`$PROOF_ROOT`)، وما بقي يُحكَم **بالفروقِ** على الحالِ القائم. */
$cleanup();
register_shutdown_function($cleanup);

/** كنسُ شجرةِ البرهانِ وحدَها — أعمقُ أوّلًا، وبمُرجَعٍ مفحوصٍ لكلِّ خطوة. */
$PROOF_ROOT = null;
register_shutdown_function(function () use ($conn, &$PROOF_ROOT) {
    if (!$PROOF_ROOT) { return; }
    $ids = array((int) $PROOF_ROOT);
    /* الذريّةُ بالتنازل — الشجرةُ أربعةُ مستوياتٍ على الأكثر */
    for ($depth = 0; $depth < 5; $depth++) {
        $in = implode(',', $ids);
        $r  = $conn->query("SELECT id FROM op_containers WHERE parent_id IN ({$in}) AND id NOT IN ({$in})");
        $new = array();
        while ($r && $row = $r->fetch_assoc()) { $new[] = (int) $row['id']; }
        if (!$new) { break; }
        $ids = array_merge($ids, $new);
    }
    $in = implode(',', $ids);
    foreach (array(
        "DELETE FROM container_consumption WHERE container_id IN ({$in})",
        "DELETE FROM container_swaps WHERE container_id IN ({$in}) OR to_container_id IN ({$in})",
        "DELETE FROM operator_rotations WHERE container_id IN ({$in})",
    ) as $sql) {
        if ($conn->query($sql) === false) { fwrite(STDERR, '  ⚠️ كنسٌ فشل: ' . $conn->error . "\n"); }
    }
    /* الأبناءُ قبلَ الآباءِ — بعكسِ ترتيبِ الاكتشاف */
    foreach (array_reverse($ids) as $id) {
        if ($conn->query("DELETE FROM op_containers WHERE id = " . (int) $id) === false) {
            fwrite(STDERR, '  ⚠️ كنسُ حاويةٍ فشل (#' . $id . '): ' . $conn->error . "\n");
        }
    }
});
// ⚠️ الكنسُ يمحو حاوياتٍ **حقيقيةً** مولَّدةً للعقود الأربعة — فيُعاد
// توليدُها بعد الحزمة. والتوليدُ حتميٌّ وعطِلٌ بنيويًّا، فالإعادةُ تُرجع الحالةَ
// كما كانت بلا تخمين.
register_shutdown_function(function () {
    global $conn, $gate;
    try {
        foreach (array(5, 2, 4, 7) as $cid) {
            \App\Services\Operations\OperationalTransformService::deriveFromOperations(
                $conn, $gate, 4, $cid, 1);
        }
    } catch (\Throwable $t) { fwrite(STDERR, "restore: " . $t->getMessage() . "
"); }
});


fwrite(STDOUT, "\n══ H-01 ② — خدمةُ التحوّل ══\n");

// ═══ ① لا حاويةَ إلا من عقدٍ نافذ ═══
head('① لا حاويةَ إلا من عقدٍ نافذ');
$notEff = $conn->query("SELECT id, contract_status FROM contracts
                          WHERE company_id={$CO} AND contract_status IN ('معلَّق','منتهٍ') LIMIT 1")->fetch_assoc();
if ($notEff) {
    $r = OTS::generateMain($conn, $gate, $CO, (int) $notEff['id'], 1);
    check(empty($r['ok']) && $r['code'] === 423,
        "العقد #{$notEff['id']} («{$notEff['contract_status']}»): 423 — " . mb_substr($r['reason'], 0, 70));
    $n = (int) $conn->query("SELECT COUNT(*) c FROM op_containers
                              WHERE contract_id=" . (int) $notEff['id'])->fetch_assoc()['c'];
    check($n === 0, "ولا حاويةَ كُتبت: {$n}");
} else { bad('لا عقدَ غيرَ نافذٍ للاختبار'); }

// ═══ ② السقفُ من بند العقد ═══
head('② السقفُ من `equip_total_contract` حصرًا');
/* ◆ **الثابتُ لا العذرية.** كان الفحصُ يشترط `created === 2` — أي جدولًا
     عذراءَ على **عقدٍ حقيقيٍّ** (العقد 5 يحمل شجرةَ المشروع 4 بسبعٍ وعشرينَ
     ورقة، وجذورُها `origin='عقد'` لا تُكنس لأنها ليست من صنعِ الفاحص).
     والثابتُ المقصودُ هو أن **للعقدِ رئيسيتين بعددِ بنوده**، والتوليدُ **عطِلٌ**
     — وهو ما يفحصه الفاحصُ نفسُه بعد أسطر («التوليدُ الثاني صفرُ جديد»).
   ⇒ يُقاس `created + existing` — فيصدُق على جدولٍ عذراءَ وعلى قائمٍ معًا،
     ولا يشترط محوَ بياناتٍ حقيقيةٍ ليمرّ. */
$r = OTS::generateMain($conn, $gate, $CO, $C5, 1);
$__mains = (int) $r['created'] + (int) (isset($r['existing']) ? $r['existing'] : 0);
check(!empty($r['ok']) && $__mains === 2,
    'العقد 5: رئيسيتان بعددِ بنوده: ' . $__mains
    . ' (وُلِّد ' . (int) $r['created'] . ' · قائمٌ ' . (int) (isset($r['existing']) ? $r['existing'] : 0) . ')');
$mains = $conn->query("SELECT c.id, c.contract_item_id, c.cap_qty, c.unit_type, c.origin, i.equip_total_contract, i.equip_type
                         FROM op_containers c
                         JOIN contractequipments i ON i.id=c.contract_item_id
                        WHERE c.contract_id={$C5} AND c.level='رئيسية' ORDER BY c.id")->fetch_all(MYSQLI_ASSOC);
check(count($mains) === 2, 'ولكلِّ بندٍ حاويتُه');
$capOk = true;
foreach ($mains as $m) {
    if (round((float) $m['cap_qty'], 2) !== round((float) $m['equip_total_contract'], 2)) { $capOk = false; }
}
check($capOk, 'وسقفُ كلٍّ = سقفُ بنده بالضبط (11,100 · 44,400)');
$total = 0.0;
foreach ($mains as $m) { $total += (float) $m['cap_qty']; }
check(round($total, 2) == 55500.00, "والمجموعُ 55,500 لا 8: {$total}");
$origins = array();
foreach ($mains as $m) { $origins[(string) $m['origin']] = true; }
check(isset($origins['عقد']) && count($origins) === 1, 'ومنشؤها «عقد» — رقمٌ متفقٌ عليه لا مشتق');

// ═══ ③ عطالةُ التوليد ═══
head('③ عطالةُ التوليد بنيوية');
$r2 = OTS::generateMain($conn, $gate, $CO, $C5, 1);
check(!empty($r2['ok']) && $r2['created'] === 0 && $r2['existing'] === 2,
    'النداءُ المكرَّر: 0 جديدة · 2 قائمة');
$n = (int) $conn->query("SELECT COUNT(*) c FROM op_containers
                          WHERE contract_id={$C5} AND level='رئيسية'")->fetch_assoc()['c'];
check($n === 2, "ولا رئيسيةَ ثالثة: {$n}");

// ═══ ④ قيدُ Σ والذريّة ═══
head('④ قيدُ Σ — رسالةٌ تسمّي المتاحَ والمطلوب · ولا كتابةَ عند الرفض');
/* ══ حاويةٌ رئيسيةٌ **من صنعِ الفاحصِ** لا رئيسيةُ العقدِ الحقيقية ══════════════
   كانت ④⑤⑥ تعمل على `$mains[0]` — رئيسيةِ العقد 5 الحقيقيةِ التي تحمل شجرةَ
   المشروعِ بثمانيةَ عشرَ ابنًا. فـ«ولا ابنٌ كُتب: 0» كان يعدُّ **أبناءَ الإنتاجِ**
   فيسقط دائمًا، والتخصيصُ الناجحُ (1000) كان **يكتب في بياناتٍ حقيقيةٍ** كلَّ شوط
   ويُنفخ `allocated_qty` بلا رجعة. والفاحصُ يفحص خدمةَ التخصيصِ والخصمِ — لا
   يلزمه عقدٌ حقيقيٌّ، يلزمه **شجرةٌ يملكها**.
   ⇒ تُبنى رئيسيةٌ مستقلةٌ بسقفٍ معلومٍ، وعليها تُبرهن ④⑤⑥، وتُكنس بذاتها في
     النهايةِ (بلا مُعالٍ خارجيٍّ فلا مفتاحَ يردُّ حذفَها).                        */
$PROOF_NOTE = 'برهانُ التحوّل — شجرةٌ مؤقتةٌ تُكنس';
$MID = (int) $gate->insert('op_containers', array(
    'container_no'     => 'TRX-' . getmypid() . '-' . substr((string) microtime(true), -6),
    'level'            => 'رئيسية',
    'parent_id'        => null,
    'contract_id'      => $C5,
    /* **بلا بندٍ**: `uq_main_per_item` (company · item · level) يمنع رئيسيةً
       ثانيةً لبندٍ له رئيسيتُه — وهو قيدٌ سليمٌ يُصان لا يُحتال عليه. ورئيسيةُ
       البرهانِ لا تمثّل بندًا فتُترك بلا بند (الفريدُ يقبل تعدُّدَ الأصفار). */
    'contract_item_id' => null,
    'unit_type'        => (string) $mains[0]['unit_type'],
    'cap_qty'          => 5000.00,
    'valid_from'       => date('Y-m-d'),
    'state'            => 'نشطة',
    'origin'           => 'عقد',
    'origin_note'      => $PROOF_NOTE,
    'created_by'       => 1,
));
check($MID > 0, "ورئيسيةُ البرهانِ بُنيت #{$MID} بسقف 5000");
$PROOF_ROOT = $MID;
$cap = 5000.00;
$before = $conn->query("SELECT allocated_qty FROM op_containers WHERE id={$MID}")->fetch_assoc();
$r = OTS::allocate($conn, $gate, $CO, $MID, 'مورد', 8, $cap + 1);
check(empty($r['ok']) && $r['code'] === 422, 'تخصيصٌ فوق السقف: مرفوض');
check(mb_strpos($r['reason'], 'تتجاوز المتاحَ') !== false
      && mb_strpos($r['reason'], number_format($cap, 2)) !== false,
    '**والرسالةُ تسمّي المتاحَ والمطلوب**: ' . mb_substr($r['reason'], 0, 100));
$after = $conn->query("SELECT allocated_qty FROM op_containers WHERE id={$MID}")->fetch_assoc();
check((float) $after['allocated_qty'] == (float) $before['allocated_qty'],
    'ولا الأمُّ زادت: ' . $after['allocated_qty']);
$kids = (int) $conn->query("SELECT COUNT(*) c FROM op_containers WHERE parent_id={$MID}")->fetch_assoc()['c'];
check($kids === 0, "ولا ابنٌ كُتب: {$kids} — ذريّة");

$r = OTS::allocate($conn, $gate, $CO, $MID, 'مورد', 8, 1000.00, array('actor' => 1));
check(!empty($r['ok']) && $r['code'] === 201, 'وتخصيصُ 1000 ضمن السقف: مقبول');
$SUPC = (int) $r['container_id'];
$after2 = $conn->query("SELECT allocated_qty FROM op_containers WHERE id={$MID}")->fetch_assoc();
check((float) $after2['allocated_qty'] == 1000.00, 'والأمُّ زادت 1000 في المعاملة نفسِها');

// ═══ ⑤ ترتيبُ الشجرة ═══
head('⑤ ترتيبُ الشجرة محروس');
$r = OTS::allocate($conn, $gate, $CO, $SUPC, 'مشغّل', 3, 10);
check(empty($r['ok']) && mb_strpos($r['reason'], 'ترتيبُ الشجرة') !== false,
    'مشغّلٌ من حاوية مورد: مرفوض — ' . mb_substr($r['reason'], 0, 70));
$r = OTS::allocate($conn, $gate, $CO, $SUPC, 'معدة', 8, 600.00, array('role_kind' => 'أساسية', 'actor' => 1));
check(!empty($r['ok']), 'ومعدةٌ من مورد: مقبولة');
$EQC = (int) $r['container_id'];
$r = OTS::allocate($conn, $gate, $CO, $EQC, 'مشغّل', 6, 300.00, array('role_kind' => 'أساسي', 'actor' => 1));
check(!empty($r['ok']), 'ومشغّلٌ من معدة: مقبول');
$OPC = (int) $r['container_id'];

// ═══ ⑥ الخصمُ الذريّ ═══
head('⑥ الخصمُ ذريٌّ على السلسلة كلِّها — أو لا شيء');
$chain = OTS::chainOf($gate, $OPC);
check(count($chain) === 4, 'السلسلةُ أربعةُ مستويات: ' . count($chain));
$r = OTS::consume($conn, $gate, $CO, $OPC, 50.00, 'test:e1', array('source_ref' => 918, 'actor' => 1));
check(!empty($r['ok']) && $r['levels'] === 4, 'خصمُ 50 وقع على أربعة مستويات: ' . $r['levels']);
$row = $conn->query("SELECT consumed_qty FROM op_containers WHERE id IN ({$MID},{$SUPC},{$EQC},{$OPC})")->fetch_all(MYSQLI_ASSOC);
$allFifty = true;
foreach ($row as $x) { if ((float) $x['consumed_qty'] != 50.00) { $allFifty = false; } }
check($allFifty, 'وكلُّ مستوًى مستهلَكُه 50 — لا رصيدَ كاذبٌ في التقارير');

$r = OTS::consume($conn, $gate, $CO, $OPC, 50.00, 'test:e1', array('source_ref' => 918));
check(!empty($r['ok']) && !empty($r['existing']), 'والمفتاحُ نفسُه: يُرجَع ولا يخصم ثانيةً');
$row = $conn->query("SELECT consumed_qty FROM op_containers WHERE id={$OPC}")->fetch_assoc();
check((float) $row['consumed_qty'] == 50.00, 'والمستهلَكُ ما زال 50: ' . $row['consumed_qty']);

$r = OTS::consume($conn, $gate, $CO, $OPC, 9999.00, 'test:e2');
check(empty($r['ok']) && mb_strpos($r['reason'], 'يتجاوز متبقّي') !== false,
    'وخصمٌ فوق المتبقي: مرفوض — ' . mb_substr($r['reason'], 0, 70));
$row = $conn->query("SELECT consumed_qty FROM op_containers WHERE id={$MID}")->fetch_assoc();
check((float) $row['consumed_qty'] == 50.00, 'ولا مستوًى تغيّر — ذريّة');

$r = OTS::consume($conn, $gate, $CO, $OPC, -50.00, 'test:e1:rev', array('source_ref' => 918));
check(!empty($r['ok']), 'وردٌّ سالبٌ بمفتاحٍ آخر: مقبول — حركةٌ عاكسةٌ لا حذف');
$row = $conn->query("SELECT consumed_qty FROM op_containers WHERE id={$OPC}")->fetch_assoc();
check((float) $row['consumed_qty'] == 0.00, 'والمستهلَكُ عاد صفرًا: ' . $row['consumed_qty']);
/* وسجلُّ الاستهلاكِ **لشجرةِ البرهانِ وحدَها** — العددُ المطلقُ كان يعدُّ سجلَّ
   الإنتاجِ كلَّه فيسقط على أيِّ نظامٍ فيه استهلاكٌ حقيقيّ. */
$rows = (int) $conn->query("SELECT COUNT(*) c FROM container_consumption
                            WHERE container_id IN ({$MID},{$SUPC},{$EQC},{$OPC})
                              AND container_id = {$OPC}")->fetch_assoc()['c'];
check($rows === 2, "وسجلُّ استهلاكِ ورقةِ البرهانِ صفّان (خصمٌ وردّ) لا صفرٌ: {$rows}");

// ═══ ⑦⑧⑨⑩⑪ التوليدُ الرجعي ═══
head('⑦ التوليدُ الرجعيُّ — مشتقٌّ وموسومٌ بذلك');
/* المحكومُ عليه: **ما يُنشئه التوليدُ** — لا كلُّ فرعٍ في العقد. فالعقد 5 يحمل
   36 فرعًا `origin='عقد'` بُنيت يدويًّا لشجرةِ المشروع، ومطالبتُها بوسمِ «مشتقّة»
   خطأُ جمهورٍ لا عطبُ منتج (كان يُقرأ «17 من 53» فيُدين منتجًا سليمًا).
   ⇒ يُرصَد أقصى مُعرِّفٍ قبلَ النداء: كلُّ ما وُلد بعده **يجب** أن يكون مشتقًّا
     موسومًا. ويُضاف حكمٌ على الجمهورِ القائم: لا مشتقَّ بلا ملاحظة. */
$maxBefore = (int) $conn->query("SELECT COALESCE(MAX(id),0) m FROM op_containers")->fetch_assoc()['m'];
$d = OTS::deriveFromOperations($conn, $gate, $CO, $C5, 1);
check(!empty($d['ok']), 'وقع التوليدُ الرجعي');
info('المولَّد: ' . json_encode($d['created'], JSON_UNESCAPED_UNICODE));
$bornBad = (int) $conn->query("SELECT COUNT(*) c FROM op_containers
                               WHERE id > {$maxBefore} AND level<>'رئيسية'
                                 AND (origin <> 'مشتقّة' OR origin_note IS NULL OR origin_note='')")
                      ->fetch_assoc()['c'];
$bornAll = (int) $conn->query("SELECT COUNT(*) c FROM op_containers
                               WHERE id > {$maxBefore} AND level<>'رئيسية'")->fetch_assoc()['c'];
check($bornBad === 0,
    "**وكلُّ فرعٍ وُلد بهذا النداءِ موسومٌ «مشتقّة» بملاحظته**: {$bornAll} وُلد · {$bornBad} بلا وسم");
$derived = $conn->query("SELECT COUNT(*) c FROM op_containers
                          WHERE contract_id={$C5} AND level<>'رئيسية' AND origin='مشتقّة'")->fetch_assoc();
check((int) $derived['c'] > 0, 'ومشتقّاتُ العقد قائمةٌ وموسومةٌ: ' . $derived['c']);
/* الحكمُ على مشتقّاتِ **العقدِ المفحوصِ** — كان بلا نطاقٍ فعدَّ 46 صفًّا: 39 منها
   بـ`contract_id = 0` (بلا عقدٍ أصلًا) و7 في العقد 7، وكلُّها من عهدٍ سابق.
   ومشتقّاتُ العقد 5 كلُّها موسومةٌ بملاحظتها (17/17 مقيسٌ) — فالمنتجُ يسمُ ما
   يشتقُّه، والصفوفُ بلا عقدٍ بندٌ مفتوحٌ أُعلن للمالك لا عطبٌ في هذا المسار. */
$noNote = (int) $conn->query("SELECT COUNT(*) c FROM op_containers
                               WHERE contract_id={$C5} AND origin='مشتقّة'
                                 AND (origin_note IS NULL OR origin_note='')")
                     ->fetch_assoc()['c'];
check($noNote === 0, "ولكلٍّ ملاحظتُه («من أين اشتُقّ»): {$noNote} بلا ملاحظة");
$unack = (int) $conn->query("SELECT COUNT(*) c FROM op_containers
                              WHERE origin='مشتقّة' AND origin_ack_by IS NULL")->fetch_assoc()['c'];
check($unack > 0, "وكلُّها تنتظر إقرارَ الإدارة: {$unack}");

head('⑩ بندان بالوحدة نفسِها — لا يُسحق أحدُهما');
$m1 = $conn->query("SELECT c.id, c.cap_qty, c.allocated_qty, i.equip_type
                      FROM op_containers c JOIN contractequipments i ON i.id=c.contract_item_id
                     WHERE c.contract_id={$C5} AND c.level='رئيسية' ORDER BY i.equip_type")->fetch_all(MYSQLI_ASSOC);
check(count($m1) === 2 && (string) $m1[0]['equip_type'] === '1' && (string) $m1[1]['equip_type'] === '2',
    'الرئيسيتان بنوعَي معدةٍ مختلفين (1 · 2)');
check((float) $m1[0]['allocated_qty'] > 0,
    '**والساعاتُ ذهبت إلى بند نوعها (1)**: ' . $m1[0]['allocated_qty']);
check((float) $m1[1]['allocated_qty'] == 0.00,
    'وبندُ النوع 2 بلا تخصيصٍ — لا عملَ عليه: ' . $m1[1]['allocated_qty']);

head('⑧ التجميعُ بالمعدة لا بصفّ التشغيل');
$dupOps = (int) $conn->query("SELECT COUNT(*) c FROM (SELECT equipment FROM operations
                                WHERE contract_id={$C5} GROUP BY equipment HAVING COUNT(*)>1) x")->fetch_assoc()['c'];
info("معداتٌ لها أكثرُ من صفِّ تشغيل: {$dupOps}");
$dupCont = (int) $conn->query("SELECT COUNT(*) c FROM (SELECT parent_id, equipment_id FROM op_containers
                                 WHERE level='معدة' GROUP BY parent_id, equipment_id HAVING COUNT(*)>1) x")
                      ->fetch_assoc()['c'];
check($dupCont === 0, "وصفرُ معدةٍ لها حاويتان تحت الأم نفسِها: {$dupCont}");

head('⑨ المشغّلُ تحت معدتين — صحيحٌ ومقصود');
$multi = $conn->query("SELECT operator_employee_id, COUNT(*) n FROM op_containers
                        WHERE level='مشغّل' GROUP BY operator_employee_id HAVING n>1")->fetch_all(MYSQLI_ASSOC);
check(count($multi) > 0, 'مشغّلون لهم حاوياتٌ تحت معدتين: ' . count($multi));
$sameParent = (int) $conn->query("SELECT COUNT(*) c FROM (SELECT parent_id, operator_employee_id
                                    FROM op_containers WHERE level='مشغّل'
                                    GROUP BY parent_id, operator_employee_id HAVING COUNT(*)>1) x")
                         ->fetch_assoc()['c'];
check($sameParent === 0, "ولا مشغّلَ له حاويتان تحت **المعدة نفسِها**: {$sameParent} — لكلِّ معدةٍ حصتُه");

head('⑪ Σ متّسقٌ في كل مستوى · وما لا بندَ له يُعلَن');
/* ══ هذا الفحصُ **أحمرُ بحقٍّ** — ولا يُخضَّر بتضييقِ نطاقه ═════════════════════
   «الأبُ يحمل العدّاد»: Σ سقوفِ الأبناءِ = موزَّعُ الأم. والقياسُ يجد **75 عقدةً
   مختلَّةً في الشركة 4 وحدَها بفارقٍ Σ = 281,111.50 وحدة**، على نوعين:
     · **50 عقدةً موزَّعُها > 0 وبلا ابنٍ واحد** — عدّادٌ يقول «سُلِّم» ولا مُتسلِّم.
     · 25 عقدةً Σأبنائها ≠ موزَّعُها في الاتجاهين.
   وفرضيةُ «المشتقُّ زِيد بلا تحديثِ العدّاد» **قِيست فسقطت** (صفرُ تفسيرٍ من 25).
   فالجذرُ لم يُحدَّد بعد، وهذه أرقامُ قدرةٍ تشغيليةٍ تُبنى عليها القرارات —
   فإصلاحُها قرارُ بياناتٍ لا قرارُ فاحصٍ. أُعلن للمالك ويبقى الفحصُ ناطقًا. */
$bad = $conn->query("SELECT p.id, p.container_no, p.allocated_qty,
                            COALESCE(SUM(c.cap_qty),0) kids
                       FROM op_containers p LEFT JOIN op_containers c ON c.parent_id=p.id
                      GROUP BY p.id, p.container_no, p.allocated_qty
                     HAVING ABS(p.allocated_qty - COALESCE(SUM(c.cap_qty),0)) > 0.001")
                   ->fetch_all(MYSQLI_ASSOC);
$childless = 0; $diff = 0.0;
foreach ($bad as $b) {
    if ((float) $b['kids'] == 0.0) { $childless++; }
    $diff += (float) $b['kids'] - (float) $b['allocated_qty'];
}
check(empty($bad), 'Σ حصص الأبناء = موزَّعُ الأم في كل عقدة'
    . ($bad ? ' — ' . count($bad) . ' عقدةً مختلَّةً (' . $childless
              . ' منها بعدّادٍ موجبٍ وبلا ابنٍ) · فارقٌ Σ = ' . number_format($diff, 2)
              . ' ⟵ بندٌ مفتوحٌ أُعلن للمالك، لا خطأَ فاحصٍ' : ''));
$over = $conn->query("SELECT COUNT(*) c FROM op_containers WHERE allocated_qty > cap_qty
                        OR consumed_qty > cap_qty")->fetch_assoc();
check((int) $over['c'] === 0, 'ولا عقدةَ تجاوزت سقفَها: ' . $over['c']);

$rec = OTS::reconciliation($gate, $CO, $C5);
/* **يُقاس المعلَّقُ ثم يُطابَق التقريرُ عليه** — لا يُفترض وجودُ معلَّقٍ دائمًا.
   مشتقّاتُ العقد 5 تُقَرُّ في المسارِ الطبيعيِّ (وفواحصُ أخرى تُقرّها)، فالمطالبةُ
   بـ«معلَّقٍ > 0» تُدين تقريرًا صادقًا يقول «لا معلَّق». والمحكومُ عليه أن يُعلن
   التقريرُ **ما هو معلَّقٌ فعلًا** بالعدد نفسِه. */
$pendDb = (int) $conn->query("SELECT COUNT(*) c FROM op_containers
                              WHERE contract_id={$C5} AND origin='مشتقّة'
                                AND origin_ack_by IS NULL AND is_deleted=0")->fetch_assoc()['c'];
check(count($rec['derived_pending']) === $pendDb,
    "تقريرُ المطابقة يُعلن المشتقّاتِ المعلَّقةَ بعددها: {$pendDb} في القاعدة · "
    . count($rec['derived_pending']) . ' في التقرير');
check(count($rec['unmatched_units']) > 0,
    'ويُعلن الوحداتِ بلا بند (طن · متر): ' . count($rec['unmatched_units']));
$units = array();
foreach ($rec['unmatched_units'] as $u) { $units[(string) $u['unit_type']] = true; }
check(isset($units['ton']) && isset($units['meter']) && !isset($units['hour']),
    'وهما الطنُّ والمترُ وحدهما (والساعةُ لها بندُها) — ولم تُخترع لهما حاوية: ' . implode(' · ', array_keys($units)));
/* **بنطاقِ العقدِ المفحوص**: الحكمُ هو «لا تُخترع حاويةٌ لوحدةٍ لا بندَ لها **في
   هذا العقد**». وكان بلا نطاقٍ فعدَّ 97 حاويةً بالطنِّ والمترِ في عقودٍ أخرى
   (2111–2120) — وتلك **مشروعةٌ**: 12 رئيسيةً منها مولَّدةٌ من بنودٍ وحدتُها طنٌّ
   أو مترٌ فعلًا (`contractequipments.equip_unit`). فالنطاقُ هو الفرقُ بين حكمٍ
   على المنتجِ وحكمٍ على بياناتِ عقودٍ لا شأنَ للفحصِ بها. */
$phantom = (int) $conn->query("SELECT COUNT(*) c FROM op_containers
                                WHERE contract_id={$C5} AND unit_type IN ('ton','meter')")
                      ->fetch_assoc()['c'];
check($phantom === 0, "وصفرُ حاويةٍ بالطن أو المتر في العقد {$C5}: {$phantom} — لا تخترع ما لا بندَ له");

// الإقرار
head('إقرارُ حصةٍ مشتقّة');
$one = $conn->query("SELECT id FROM op_containers WHERE origin='مشتقّة' AND origin_ack_by IS NULL LIMIT 1")->fetch_assoc();
$r = OTS::acknowledge($gate, $CO, (int) $one['id'], 77);
check(!empty($r['ok']), 'أُقرّت');
$row = $conn->query("SELECT origin_ack_by, origin_ack_at FROM op_containers WHERE id=" . (int) $one['id'])->fetch_assoc();
check((int) $row['origin_ack_by'] === 77 && $row['origin_ack_at'] !== null, 'باسم مَن أقرّها ووقته');
$r = OTS::acknowledge($gate, $CO, (int) $one['id'], 88);
check(!empty($r['ok']) && mb_strpos($r['reason'], 'مُقرَّةٌ سلفًا') !== false, 'وإقرارٌ ثانٍ: عطالة');

fwrite(STDOUT, "\n══════════════════════════════════════════════════\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL === 0 ? 0 : 1);
