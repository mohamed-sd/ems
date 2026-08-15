<?php
/**
 * tests/contract_lifecycle_actions_test.php — دورةُ الحياةِ كاملةً بأفعالِها وعكسِها
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0026 (عقودُ العملاء) · INJ-0152 (عقودُ الموردين)
 *
 * نصُّ القبولِ الأول: «**كلُّ فعلٍ من الثمانية** ينقل `contract_status` إلى حالةٍ
 * مشروعةٍ فقط، وينشر حدثًا واحدًا، ويكتب صفَّ تدقيقٍ بقيمةٍ قبل وبعد، **وله فعلُ
 * عكسٍ** يعيد الحالةَ السابقة».
 * ونصُّ الثاني: «**إنهاءُ عقدِ موردٍ ينقل حالتَه في آلة الحالة**، وينشر حدثًا
 * واحدًا **يُقفل حاوياتِه**، ويكتب صفَّ تدقيقٍ بقيمةٍ قبل وبعد، **وله فعلُ نقضٍ**».
 *
 * ◆ **وقائمةُ السماحِ تُختبر بما تمنعه لا بما تُجيزه**: لكلِّ آلةٍ هنا محاولةُ
 *   انتقالٍ غيرِ مشروعٍ وإثباتُ الرفضِ برمزٍ — فآلةٌ تقبل كلَّ شيءٍ تمرُّ في
 *   اختبارٍ يفحص المسموحَ وحدَه.
 * ◆ **والوسمُ عائليٌّ ثابتٌ** (لا `getmypid`) والكنسُ بالعائلة: جولةٌ تموت
 *   تترك صفوفَها، والجولةُ التالية عمياءُ عمّا تركته سابقتُها.
 * ◆ ويُفحص مُرجَعُ كلِّ حذفٍ — فالمفتاحُ الأجنبيُّ يردُّ صامتًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
ob_start(); require_once $ROOT . '/config.php'; ob_end_clean();
while (ob_get_level() > 0) { ob_end_clean(); }

require_once $ROOT . '/app/Services/Contract/ContractStateMachine.php';
require_once $ROOT . '/app/Services/Contract/ContractLifecycleActions.php';
require_once $ROOT . '/app/Services/Contract/SupplierContractService.php';

use App\Services\Contract\ContractStateMachine as CSM;
use App\Services\Contract\ContractLifecycleActions as CLA;
use App\Services\Contract\SupplierContractService as SCS;

$conn = $GLOBALS['conn'];
$CO   = 4;
$TAG  = 'CLA-TEST-FAMILY';          /* وسمٌ عائليٌّ ثابتٌ — الكنسُ به لا بالعملية */
$PASS = 0; $FAIL = 0; $NOTES = array();
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$say('══ دورةُ الحياةِ كاملةً بأفعالِها وعكسِها');

/* ── ٠ الكنسُ القبليُّ بالعائلة ─────────────────────────────────────────────── */
$sweep = function () use ($conn, $TAG) {
    $left = 0;
    /* الأبناءُ قبل الآباء — والمفتاحُ الأجنبيُّ يردُّ صامتًا فيُفحص المُرجَع */
    $conn->query("DELETE l FROM supplier_contract_lines l
                    JOIN supplier_contracts h ON h.id = l.contract_id
                   WHERE h.notes LIKE '%{$TAG}%'");
    $conn->query("DELETE FROM op_containers WHERE origin_note LIKE '%{$TAG}%'");
    $conn->query("DELETE FROM supplier_contracts WHERE notes LIKE '%{$TAG}%'");
    $r = $conn->query("SELECT COUNT(*) FROM supplier_contracts WHERE notes LIKE '%{$TAG}%'");
    if ($r && ($x = $r->fetch_row())) { $left = (int) $x[0]; }
    return $left;
};
$leftBefore = $sweep();
$ok($leftBefore === 0, 'الكنسُ القبليُّ نظيفٌ بالعائلة', "بقي {$leftBefore}");

/* ── ① السجلُّ يُغطّي الأفعالَ الثمانيةَ ولكلٍّ جوابُ عكسٍ ────────────────── */
$prim = CLA::primary('customer');
$ok(count($prim) >= 8, 'سجلُّ العميلِ يحمل ثمانيةَ أفعالٍ فأكثر (' . count($prim) . ')');
$noAnswer = array();
foreach ($prim as $code => $a) {
    $rv = CLA::reverseOf('customer', $code);
    if (!$rv['has'] && trim((string) $rv['why']) === '') { $noAnswer[] = $code; }
}
$ok(empty($noAnswer),
    '**ولكلِّ فعلٍ جوابُ عكسٍ**: إمّا فعلٌ يعكسه وإمّا سببٌ مكتوبٌ لامتناعِه',
    implode(' · ', $noAnswer));
$withRev = 0;
foreach ($prim as $code => $a) { if (CLA::reverseOf('customer', $code)['has']) { $withRev++; } }
$ok($withRev >= 3, "وثلاثةٌ منها لها عكسٌ حقيقيٌّ ({$withRev}) — لا كلُّها ممتنعة");

/* ② وكلُّ فعلٍ في السجلِّ **مشروعٌ في جدولِ الانتقالات** — لا رأيَ للشاشة */
$illegal = array();
foreach (CLA::registry('customer') as $code => $a) {
    if (empty($a['to']) || !empty($a['door'])) { continue; }
    foreach ($a['from'] as $f) {
        if (!CSM::canTransition($f, $a['to'])) { $illegal[] = $code . ' (' . $f . '→' . $a['to'] . ')'; }
    }
}
$ok(empty($illegal),
    '**وكلُّ فعلٍ مشروعٌ في جدولِ الانتقالاتِ نفسِه** — فالسجلُّ لا يخترع حافةً',
    implode(' · ', array_slice($illegal, 0, 4)));

/* ── ② الانتقالُ الممنوع — قائمةُ السماحِ تُختبر بما تمنعه ─────────────────── */
$ok(CSM::canTransition(CSM::DRAFT, CSM::SIGNED) === false,
    '**ومسودةٌ لا تقفز إلى موقَّع** — لا حافةَ بينهما');
$ok(CSM::canTransition(CSM::SETTLED, CSM::DRAFT) === false,
    'ومصفّى لا يعود مسودةً — حالةٌ نهائية');
$ok(CSM::canTransition(CSM::ENDED, CSM::RUNNING) === false,
    'ومنتهٍ لا يعود قيدَ التنفيذ — والنقضُ حركةٌ معوِّضةٌ لا حافة');
$bad = CLA::run($conn, null, $CO, 'customer', 1, 'not_declared_action', '', 0, 0);
$ok(empty($bad['ok']) && (int) $bad['code'] === 422 && strpos($bad['reason'], 'CLA-422') !== false,
    '**وفعلٌ غيرُ مُعلَنٍ يُردُّ ٤٢٢** ولا يُخمَّن');

/* ── ③ الشاشتانِ تنادِيان السجلَّ فعلًا ────────────────────────────────────── */
$cs = (string) @file_get_contents($ROOT . '/Contracts/contracts.php');
$ok(strpos($cs, 'ContractLifecycleActions::run') !== false
    && strpos($cs, "'trigger' => 'clc_action'") !== false,
    'وشاشةُ العقودِ تُرسل إلى السجلِّ لا إلى الجدولِ مباشرةً');
$ok(strpos($cs, 'ContractLifecycleActions::availableFor') !== false,
    '**وتُصيّر الأفعالَ المشروعةَ من الحالةِ الراهنة** لا شرطين مكتوبَينِ يدويًّا');
$ok(substr_count($cs, 'csrf_field(); ?>"') === 0 && strpos($cs, '" . csrf_field()') !== false,
    'ونماذجُها تحمل رمزَ الحمايةِ **نداءً لا نصًّا** — والوسمُ داخلَ سلسلةٍ لا يُنفَّذ');
$sl = (string) @file_get_contents($ROOT . '/Suppliers/supplier_contract_lines.php');
$ok(strpos($sl, "ContractLifecycleActions::run") !== false
    && strpos($sl, "'sc_lifecycle'") !== false,
    'وشاشةُ عقدِ الموردِ صار لها بابٌ إلى آلةِ الحالة — بعد صفرِ نداء');
$ok(strpos($sl, 'revokeTermination') !== false,
    'وفيها **فعلُ النقضِ** لعقدٍ منتهٍ');

/* ── ④ عقدُ موردٍ حيٌّ: إنهاءٌ يُقفل حاوياتِه ثم نقضٌ يُعيدها ──────────────── */
require_once $ROOT . '/app/Core/TenantDb.php';
$_SESSION['user'] = array('id' => 1, 'role' => '1', 'company_id' => $CO, 'name' => 'شاهد');
$gate = ems_tenant_db();

$supId = 0;
$r = $conn->query("SELECT id FROM suppliers WHERE company_id = {$CO} AND COALESCE(is_deleted,0)=0 ORDER BY id LIMIT 1");
if ($r && ($x = $r->fetch_row())) { $supId = (int) $x[0]; }
$ok($supId > 0, "موردٌ حيٌّ للقياس (#{$supId})");

$scId = 0;
if ($supId > 0) {
    $st = $conn->prepare("INSERT INTO supplier_contracts
        (company_id, supplier_id, start_date, currency, state, version, notes, created_by, created_at)
        VALUES (?, ?, CURDATE(), 'SDG', ?, 1, ?, 1, NOW())");
    if ($st) {
        $run = CSM::RUNNING; $notes = 'شاهدُ دورةِ الحياة ' . $TAG;
        $st->bind_param('iiss', $CO, $supId, $run, $notes);
        if ($st->execute()) { $scId = (int) $conn->insert_id; }
        $st->close();
    }
}
$ok($scId > 0, "عقدُ موردٍ للقياس أُنشئ (#{$scId}) بحالة «قيد التنفيذ»");

/* حاويتانِ نشطتانِ لهذا المورد — بوسمِ العائلة */
$cnt = 0;
if ($scId > 0) {
    for ($i = 1; $i <= 2; $i++) {
        $st = $conn->prepare("INSERT INTO op_containers
            (company_id, container_no, level, supplier_id, state, origin, origin_note, created_by, created_at)
            VALUES (?, ?, 'رئيسية', ?, 'نشطة', 'عقد', ?, 1, NOW())");
        if ($st) {
            $no = $TAG . '-C' . $i; $note = 'حاويةُ شاهدٍ ' . $TAG;
            $st->bind_param('isis', $CO, $no, $supId, $note);
            if ($st->execute()) { $cnt++; }
            $st->close();
        }
    }
}
$ok($cnt === 2, "وحاويتانِ نشطتانِ لهذا المورد ({$cnt})");

if ($scId > 0) {
    /* ⓐ انتقالٌ غيرُ مشروعٍ يُردُّ */
    $illeg = SCS::transition($conn, $gate, $CO, $scId, CSM::DRAFT, 1, 1);
    $ok(empty($illeg['ok']) && (int) $illeg['code'] === 422,
        '**وانتقالٌ غيرُ مشروعٍ على عقدِ الموردِ يُردُّ ٤٢٢** (' . (int) $illeg['code'] . ')');

    /* ◆ لقطةُ حاوياتِ الإنتاجِ **قبل** الإنهاء: الإقفالُ يشمل حاوياتِ الموردِ
         كلَّها — وهو صوابُ الإنتاجِ وخطرُ الفحص. فتُلتقط لتُردَّ بعده. */
    $snapBefore = array();
    $q0 = $conn->prepare("SELECT id, state, close_reason FROM op_containers
                           WHERE company_id = ? AND supplier_id = ? AND state <> 'مقفلة'
                             AND COALESCE(is_deleted,0) = 0
                             AND (origin_note IS NULL OR origin_note NOT LIKE ?)");
    if ($q0) {
        $notLike = '%' . $TAG . '%';
        $q0->bind_param('iis', $CO, $supId, $notLike);
        $q0->execute();
        $rs0 = $q0->get_result();
        while ($rw0 = $rs0->fetch_assoc()) {
            $snapBefore[(int) $rw0['id']] = array((string) $rw0['state'], $rw0['close_reason']);
        }
        $q0->close();
    }

    /* ⓑ الإنهاءُ عبر السجل */
    $end = CLA::run($conn, $gate, $CO, 'supplier', $scId, 'end', 'شاهد', 1, 0, 1);
    $ok(!empty($end['ok']), 'والإنهاءُ يمرُّ عبر آلةِ الحالة (' . (int) $end['code'] . ')', $end['reason']);

    $st = $conn->prepare('SELECT state FROM supplier_contracts WHERE id = ?');
    $st->bind_param('i', $scId); $st->execute();
    $now = $st->get_result()->fetch_row(); $st->close();
    $ok($now && (string) $now[0] === CSM::ENDED, 'والحالةُ صارت «منتهٍ» فعلًا في الجدول');

    /* ⓒ الحاوياتُ أُقفلت */
    $closed = 0;
    $st = $conn->prepare("SELECT COUNT(*) FROM op_containers
                           WHERE supplier_id = ? AND company_id = ? AND state = 'مقفلة'
                             AND close_reason LIKE ?");
    if ($st) {
        $like = 'إقفالٌ تبعًا لعقدِ المورد #' . $scId . '%';
        $st->bind_param('iis', $supId, $CO, $like);
        $st->execute();
        $rr = $st->get_result()->fetch_row();
        $closed = $rr ? (int) $rr[0] : 0;
        $st->close();
    }
    $ok($closed >= 2, "**والحدثُ أقفل حاوياتِه** ({$closed}) — فلا تحميلَ على عقدٍ انتهى");

    /* ⓓ حدثٌ واحدٌ لا اثنان */
    $ev = 0;
    $st = $conn->prepare("SELECT COUNT(*) FROM ems_business_events
                           WHERE entity_type = 'supplier_contract' AND entity_id = ?
                             AND event_key = 'procurement.supplier_contract.state_changed'");
    if ($st) {
        $st->bind_param('i', $scId); $st->execute();
        $rr = $st->get_result()->fetch_row();
        $ev = $rr ? (int) $rr[0] : 0;
        $st->close();
    }
    $ok($ev === 1, "**وحدثٌ واحدٌ لانتقالٍ واحد** ({$ev}) — لا تضاعفَ في المروحة");

    /* ⓔ صفُّ تدقيقٍ بقيمةٍ قبل وبعد */
    $aud = 0;
    $st = $conn->prepare("SELECT COUNT(*) FROM activity_logs
                           WHERE screen_name = 'supplier_contracts' AND record_id = ?
                             AND action_type = 'transition'
                             AND old_value IS NOT NULL AND old_value <> ''
                             AND new_value IS NOT NULL AND new_value <> ''");
    if ($st) {
        $st->bind_param('i', $scId); $st->execute();
        $rr = $st->get_result()->fetch_row();
        $aud = $rr ? (int) $rr[0] : 0;
        $st->close();
    }
    $ok($aud >= 1, "وصفُّ تدقيقٍ بقيمةٍ قبل وبعد ({$aud})");

    /* ⓕ النقضُ يعيد الحالةَ والحاويات */
    $rev = SCS::revokeTermination($conn, $gate, $CO, $scId, 'خطأٌ في الإنهاء — شاهد', 1);
    $ok(!empty($rev['ok']), 'و**فعلُ النقضِ** يمرُّ (' . (int) $rev['code'] . ')', $rev['reason']);
    $ok(!empty($rev['ok']) && (string) $rev['state'] === CSM::RUNNING,
        'ويُعيد الحالةَ إلى **ما كانت قبلَ الإنهاءِ** لا إلى حالةٍ يختارها الناقض');
    $ok(!empty($rev['containers_reopened']) && (int) $rev['containers_reopened'] >= 2,
        'ويُعيد فتحَ حاوياتِه (' . (int) ($rev['containers_reopened'] ?? 0) . ')');

    /* ⓖ والنقضُ حركةٌ لا محو: حقيقةٌ جديدةٌ تشير إلى الأصل */
    $rv = 0;
    $st = $conn->prepare("SELECT COUNT(*) FROM ems_business_events
                           WHERE entity_type = 'supplier_contract' AND entity_id = ?
                             AND event_key = 'procurement.supplier_contract.termination_revoked'");
    if ($st) {
        $st->bind_param('i', $scId); $st->execute();
        $rr = $st->get_result()->fetch_row();
        $rv = $rr ? (int) $rr[0] : 0;
        $st->close();
    }
    $ok($rv === 1, "**والنقضُ حركةٌ جديدةٌ بمرجعِها لا محوٌ للأولى** ({$rv})");

    /* ⓗ ونقضٌ ثانٍ يُردُّ — العقدُ لم يعد منتهيًا */
    $rev2 = SCS::revokeTermination($conn, $gate, $CO, $scId, 'ثانٍ', 1);
    $ok(empty($rev2['ok']) && (int) $rev2['code'] === 422,
        'ونقضٌ ثانٍ يُردُّ — النقضُ للمنتهي وحدَه');
    $rev3 = SCS::revokeTermination($conn, $gate, $CO, $scId, '', 1);
    $ok(empty($rev3['ok']), 'ونقضٌ بلا سببٍ يُردُّ — لا نقضَ صامت');
}

/* ◆ **وما مسَّه الشاهدُ من بياناتٍ حقيقيةٍ يُعاد**: الإقفالُ يشمل حاوياتِ
     المورد كلَّها لا الموسومةَ وحدَها — وهو صوابُ الإنتاجِ وخطرُ الفحص.
     فتُلتقط حالتُها قبلَ الإنهاءِ وتُردُّ بعده، ويُعلَن ما تعذّر ردُّه. */
$restored = 0;
if ($scId > 0 && $snapBefore) {
    foreach ($snapBefore as $cid3 => $st3) {
        $q3 = $conn->prepare("UPDATE op_containers SET state = ?, close_reason = ? WHERE id = ?");
        if ($q3) {
            $s3 = (string) $st3[0]; $r3 = $st3[1];
            $q3->bind_param('ssi', $s3, $r3, $cid3);
            if ($q3->execute() && $conn->affected_rows >= 0) { $restored++; }
            $q3->close();
        }
    }
}
$ok($restored === count($snapBefore),
    'وأُعيدت حاوياتُ الإنتاجِ التي مسَّها الشاهدُ إلى حالتِها (' . $restored . '/' . count($snapBefore) . ')');

/* ── ⑤ الكنسُ البعديُّ وإعلانُ ما لم يُكنس ────────────────────────────────── */
$leftAfter = $sweep();
$ok($leftAfter === 0, 'والكنسُ البعديُّ نظيفٌ — لا صفَّ شاهدٍ يبقى', "بقي {$leftAfter}");
if ($leftAfter > 0) { $NOTES[] = "لم يُكنس {$leftAfter} صفًّا — يُعلَن ولا يُخفى"; }

$say('');
foreach ($NOTES as $n) { $say('  ◆ ' . $n); }
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
