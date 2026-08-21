<?php
/**
 * tests/injfix01_settlement_integrity_negative_proof.php
 *   سلامةُ التسويةِ عند الفشل — INJ-FIX-01 · الموجة ب · الحاجز ② · GAP-16
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **نصُّ المالك**: «لا يُبتلع فشلُ إنشاءِ الذمة. اختبارٌ سلبيٌّ متعمَّدٌ يثبت:
 *   failure → rollback → no approved downstream state.»
 *
 * ◆ **والعطبُ الذي يُثبَت انسدادُه**: كان إنشاءُ الذمّةِ المدينةِ (`fin_dues`)
 *   **خارجَ المعاملةِ وبـ`catch` يبتلع**. فإن فشل، يُسجَّل السطرُ في السجلِّ
 *   **وتمضي التسويةُ إلى `approved` بذمّةٍ فارغة** — فيسقط دَينٌ على الطرفِ
 *   صامتًا وتبدو التسويةُ مكتملة.
 *
 * ◆ **وكيف يُفشَل الإنشاءُ عمدًا بلا مساسٍ ببنيةِ الإنتاج**: `fin_dues` عليه
 *   مفتاحٌ أجنبيٌّ `(company_id, currency) → fin_currencies(company_id, code)`
 *   بينما `settlements` **بلا هذا المفتاح**. فتسويةٌ بعملةٍ غيرِ مسجَّلةٍ
 *   تُقبل في `settlements` **وتُردُّ في `fin_dues`** — فشلٌ حتميٌّ ونظيفٌ لا
 *   يحتاج إعادةَ تسميةِ جدولٍ ولا قادحًا مؤقتًا.
 *
 * ◆ **وطرفان يُقاسان لا طرف**: السلبيُّ يثبت الإرجاع، والإيجابيُّ يثبت أن
 *   المسارَ السليمَ ما يزال يمرّ — فمنعٌ يمنع كلَّ شيءٍ ليس إصلاحًا.
 *
 * ◆ **والكنسُ بالعائلةِ لا بالجولة** — وسمُ `INJFIX02` يُكنس كلُّه بدءًا وختامًا.
 *
 * التشغيل: php tests/injfix01_settlement_integrity_negative_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once $ROOT . '/config.php';
$_SESSION['user'] = array('id' => 1, 'role' => '2', 'company_id' => 4, 'name' => 'INJ-FIX-01 §ب②');
require_once $ROOT . '/app/Services/Settlement/SettlementService.php';
use App\Services\Settlement\SettlementService as SVC;

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate = ems_tenant_db();

$CO      = 4;
$FAMILY  = 'INJFIX02';
$BAD_CUR = 'ZZZ';                 // غيرُ مسجَّلةٍ في fin_currencies — والمفتاحُ الأجنبيُّ يردُّها
$GOOD_CUR = 'USD';
$PREP    = 999861;                // مُعِدّ
$APPR    = 999862;                // معتمِدٌ مختلفٌ — فصلُ اليدين بنيويّ

$pass = 0; $fail = 0;
function ok($c, $l, &$p, &$f, $d = '') { if ($c) { $p++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; } else { $f++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; } }

/** كنسُ العائلةِ كلِّها. */
function sweep(mysqli $c, $family)
{
    $like = $c->real_escape_string($family);
    $c->query("DELETE FROM `fin_dues` WHERE `settlement_id` IN
                 (SELECT `id` FROM `settlements` WHERE `settlement_no` LIKE '{$like}%')");
    $c->query("DELETE FROM `fin_requests` WHERE `source_ref` LIKE '{$like}%'");
    /* ◆ **الحدثُ يُكتب في مقامَين ويُكنس منهما معًا**: `EventPublisher` يُدرج في
         `ems_business_events` **و**`fin_financial_events`، والثاني هو مقامُ مؤشرِ
         المستهلكِ في `EventDispatcher::runConsumer`. وكنسُ الأولِ وحدَه يترك
         الثانيَ فيُخلّف **تأخُّرًا حقيقيًّا** في مستهلكَي الماليةِ يُشعل إنذارَ
         التعثُّر — أي أن جولةَ اختبارٍ تصنع العطبَ الذي يرصده فاحصٌ آخر. */
    $c->query("DELETE FROM `ems_business_events`  WHERE `source_ref` LIKE '{$like}%'");
    $c->query("DELETE FROM `fin_financial_events` WHERE `source_ref` LIKE '{$like}%'");
    $c->query("DELETE FROM `settlement_lines` WHERE `settlement_id` IN
                 (SELECT `id` FROM `settlements` WHERE `settlement_no` LIKE '{$like}%')");
    $c->query("DELETE FROM `settlements` WHERE `settlement_no` LIKE '{$like}%'");
}

echo "════ سلامةُ التسويةِ عند الفشل — GAP-16 ════\n";
sweep($conn, $FAMILY);

/** تُنشئ تسويةً في حالةِ مراجعةٍ بصافٍ سالبٍ (⇐ ذمّةٌ مدينة). */
function mkSettlement(mysqli $c, $co, $no, $cur, $prep, $from, $to)
{
    $st = $c->prepare(
        "INSERT INTO `settlements`
           (`company_id`,`settlement_no`,`party_type`,`party_ref`,`party_name`,
            `period_from`,`period_to`,`currency`,`gross_amount`,`charges_amount`,
            `net_amount`,`state`,`prepared_by`,`open_objections`,`created_by`)
         VALUES (?, ?, 'employee', 1, 'موظَّفُ اختبارٍ — INJ-FIX-01',
                 ?, ?, ?, 100.00, 400.00, -300.00, 'review', ?, 0, ?)");
    $st->bind_param('issssii', $co, $no, $from, $to, $cur, $prep, $prep);
    if (!$st->execute()) { echo "      ↳ تعذّر الإنشاء: " . $st->error . "\n"; return 0; }
    $id = $st->insert_id; $st->close();
    return (int) $id;
}

/* ══════════ ① الحالةُ السلبية — الذمّةُ تفشل عمدًا ═══════════════════════ */
echo "\n── ① الفشلُ المتعمَّد ──\n";
$noBad = $FAMILY . '-BAD-' . getmypid();
$sidBad = mkSettlement($conn, $CO, $noBad, $BAD_CUR, $PREP, '2096-05-01', '2096-05-31');
ok($sidBad > 0, 'أُنشئت تسويةٌ بصافٍ سالبٍ وعملةٍ غيرِ مسجَّلة', $pass, $fail,
   "id={$sidBad} · عملة={$BAD_CUR} · صافٍ=-300");
if ($sidBad <= 0) { sweep($conn, $FAMILY); exit(1); }

$res = SVC::approve($gate, $conn, $sidBad, $APPR);
ok(empty($res['ok']), '**الاعتمادُ رُدَّ صراحةً** ولم يُبتلع الفشل', $pass, $fail,
   'السبب: ' . mb_substr((string) ($res['reason'] ?? '—'), 0, 90));

/* ── الاستيثاقُ: صفرُ حالةٍ لاحقةٍ مضت ─────────────────────────────────── */
$q = $conn->query("SELECT `state`, `approved_by`, `approved_at`, `receivable_due_id`, `payment_request_id`
                     FROM `settlements` WHERE `id` = " . (int) $sidBad);
$row = $q ? $q->fetch_assoc() : null;
ok($row && (string) $row['state'] === 'review',
   'الحالةُ لم تتقدّم — ما تزال «مراجعة»', $pass, $fail, $row ? $row['state'] : '—');
ok($row && $row['approved_by'] === null && $row['approved_at'] === null,
   'لا معتمِدَ ولا وقتَ اعتماد', $pass, $fail);
ok($row && $row['receivable_due_id'] === null,
   'لا معرِّفَ ذمّةٍ مُعلَّقٍ على التسوية', $pass, $fail);

$q = $conn->query("SELECT COUNT(*) FROM `fin_dues` WHERE `settlement_id` = " . (int) $sidBad);
ok($q && (int) $q->fetch_row()[0] === 0, 'صفرُ صفٍّ في دفترِ الذمم', $pass, $fail);

$q = $conn->query("SELECT COUNT(*) FROM `ems_business_events`
                    WHERE `event_key` = 'settlement.approved' AND `entity_id` = " . (int) $sidBad);
ok($q && (int) $q->fetch_row()[0] === 0,
   '**صفرُ حدثِ اعتمادٍ منشور** — فلا يُعلَن ما لم يقع', $pass, $fail);

$q = $conn->query("SELECT COUNT(*) FROM `fin_requests` WHERE `settlement_id` = " . (int) $sidBad);
ok($q && (int) $q->fetch_row()[0] === 0, 'صفرُ طلبِ دفعٍ مولَّد', $pass, $fail);

/* ══════════ ② الحالةُ الإيجابية — المسارُ السليمُ ما يزال يمرّ ═══════════ */
echo "\n── ② المسارُ السليم ──\n";
$noGood = $FAMILY . '-OK-' . getmypid();
$sidGood = mkSettlement($conn, $CO, $noGood, $GOOD_CUR, $PREP, '2096-06-01', '2096-06-30');
ok($sidGood > 0, 'أُنشئت تسويةٌ بعملةٍ مسجَّلة', $pass, $fail, "id={$sidGood} · عملة={$GOOD_CUR}");

$res2 = SVC::approve($gate, $conn, $sidGood, $APPR);
ok(!empty($res2['ok']), 'الاعتمادُ مرَّ — فالمنعُ ليس منعًا لكلِّ شيء', $pass, $fail,
   !empty($res2['ok']) ? 'اتجاه=' . ($res2['net_direction'] ?? '—') : 'السبب: ' . mb_substr((string) ($res2['reason'] ?? '—'), 0, 80));

$q = $conn->query("SELECT `state`, `receivable_due_id` FROM `settlements` WHERE `id` = " . (int) $sidGood);
$row2 = $q ? $q->fetch_assoc() : null;
ok($row2 && (string) $row2['state'] === 'approved', 'الحالةُ تقدّمت إلى «معتمَدة»', $pass, $fail,
   $row2 ? $row2['state'] : '—');
ok($row2 && (int) $row2['receivable_due_id'] > 0, 'ومعها معرِّفُ ذمّةٍ حقيقيّ', $pass, $fail,
   $row2 ? (string) $row2['receivable_due_id'] : '—');

$q = $conn->query("SELECT COUNT(*) FROM `fin_dues` WHERE `settlement_id` = " . (int) $sidGood);
ok($q && (int) $q->fetch_row()[0] === 1, 'صفٌّ واحدٌ في دفترِ الذمم', $pass, $fail);

$q = $conn->query("SELECT COUNT(*) FROM `ems_business_events`
                    WHERE `event_key` = 'settlement.approved' AND `entity_id` = " . (int) $sidGood);
ok($q && (int) $q->fetch_row()[0] === 1, 'وحدثُ اعتمادٍ واحدٌ منشور', $pass, $fail);

/* ── الكنس ─────────────────────────────────────────────────────────────── */
sweep($conn, $FAMILY);
$q = $conn->query("SELECT COUNT(*) FROM `settlements` WHERE `settlement_no` LIKE '{$FAMILY}%'");
ok($q && (int) $q->fetch_row()[0] === 0, 'صفرُ أثرٍ باقٍ بعدَ الجولة', $pass, $fail);

echo "───────────────────────────────────────────────────────────────\n";
echo ($fail === 0 ? "✔" : "✘") . " النتيجة: نجح {$pass} · رسب {$fail}\n";

/* حكمُ الإغلاقِ — عقدُ GAP-56: يُصرَّح به بعدَ القياسِ لا يُستنتَج من الذِّكر */
require_once dirname(__DIR__) . '/tools/lib/gap_verdict.php';
gapv('GAP-16', true, 'فشلُ الذمةِ لا يُبتلع — الرفضُ يُرفع ويُسجَّل · مُثبَتٌ بحزامٍ سلبيّ', $fail);

exit($fail === 0 ? 0 : 1);
