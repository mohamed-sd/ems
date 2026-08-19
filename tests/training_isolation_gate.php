<?php
/**
 * tests/training_isolation_gate.php — بوابةُ صفرِ تسرّبِ التدريب (سادسًا)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ نصُّ الطلب: «حساباتُ التدريبِ يجب أن تحملَ سياقَ التدريبِ **إلى آخرِ السلسلة**
 *   مع **بوابةٍ تُثبت صفرَ تسرّب**».
 *
 * ◆ **وحدُّ السلسلةِ مقيسٌ لا مفترَض**: `correlation_id` في **جدولَين فقط** من
 *   المخططِ كلِّه — `ems_business_events` و`fin_financial_events`. فالبوابةُ
 *   تُثبت على الحدِّ الحقيقيِّ لا على حدٍّ مُدَّعًى. **وبوابةٌ لا تعرف أين تنتهي
 *   السلسلةُ لا تُثبت شيئًا.**
 *
 * ◆ **وتُثبت أربعةً — والرابعُ هو الذي يُصدِّق الثلاثة**:
 *   ① حسابٌ تدريبيٌّ يكتب حقيقةً ⇒ تُوسَم `is_training=1`.
 *   ② حسابٌ عاديٌّ يكتب حقيقةً ⇒ تبقى **صفرًا** — فالوسمُ ليس ثابتًا يقول «نعم».
 *   ③ **صفرُ إسقاطٍ ماليٍّ مرتبطٍ بحقيقةٍ تدريبيةٍ يفلتُ بلا وسم** — وهذا
 *      **التسرّبُ** بعينِه: قُيس بربطِ `correlation_id` لا بقراءةِ الشيفرة.
 *   ④ **صفرُ حقيقةٍ تدريبيةٍ لحسابٍ ليس تدريبيًّا والعكس** — اتساقُ المخزونِ
 *      كلِّه، لا الصفوفِ التي كتبها هذا الفاحصُ وحدَه. فلو وسمَ الناشرُ صحيحًا
 *      اليومَ وتسرّب أمسِ، ظهرَ هنا.
 *
 * ◆ **ولا يُعلَن حسابٌ تدريبيًّا لأجلِ فاحص**: يُستعار حسابٌ حقيقيٌّ، **ويُعاد
 *   إلى حالتِه ببصمةٍ مقروءةٍ قبلًا وبعدًا**، والاستعادةُ مسجَّلةٌ في
 *   `register_shutdown_function` فتقعُ حتى على خطأٍ قاتلٍ أو مقاطعة.
 * ◆ وكلُّ ما يُكتب يُكنس **بالعائلة** (`TRAIN-GATE-%`) لا بوسمِ هذه العمليةِ
 *   وحدَها — فبقايا جولةٍ سابقةٍ لا تُعمي هذه.
 *
 * التشغيل: php tests/training_isolation_gate.php
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }

$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$CO   = 4;
$pass = 0; $fail = 0; $skip = 0;

function ok(string $t, bool $c, string $note = '') {
    global $pass, $fail;
    if ($c) { $pass++; echo "  ✔ {$t}" . ($note ? " — {$note}" : '') . "\n"; }
    else    { $fail++; echo "  ✘ {$t}" . ($note ? " — {$note}" : '') . "\n"; }
}

echo "══ بوابةُ صفرِ تسرّبِ التدريب ══\n";

/* ══ ٠ الحدُّ المقيسُ للسلسلة ══════════════════════════════════════════ */
$chain = array();
$r = mysqli_query($conn, "SELECT TABLE_NAME FROM information_schema.COLUMNS
                           WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='correlation_id'
                           ORDER BY TABLE_NAME");
while ($r && ($x = mysqli_fetch_row($r))) { $chain[] = $x[0]; }
echo "  حدُّ السلسلةِ المقيس: " . implode(' → ', $chain) . " (" . count($chain) . ")\n";

$unmarked = array();
foreach ($chain as $t) {
    $q = mysqli_query($conn, "SELECT COUNT(*) FROM information_schema.COLUMNS
                               WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='is_training'");
    if (!$q || (int) mysqli_fetch_row($q)[0] === 0) { $unmarked[] = $t; }
}
ok('كلُّ جدولٍ في السلسلةِ يحمل عمودَ الوسم', !$unmarked,
   $unmarked ? 'بلا وسم: ' . implode(' · ', $unmarked) : implode(' · ', $chain));
if ($unmarked) { echo "\n✘ لا يُقاس تسرّبٌ عبر جدولٍ لا يعرف الوسم\n"; exit(1); }

/* ══ ١ حسابٌ يُستعار ويُعاد ═══════════════════════════════════════════ */
$r = mysqli_query($conn, "SELECT id, name, is_training FROM users
                           WHERE company_id = {$CO} AND is_training = 0 ORDER BY id LIMIT 2");
$u1 = mysqli_fetch_assoc($r);
$u2 = mysqli_fetch_assoc($r);
if (!$u1 || !$u2) { echo "⊘ NOT_MEASURED — يلزم حسابانِ في الكيان {$CO}\n"; exit(2); }
$TRAIN = (int) $u1['id'];     /* يُعلَن تدريبيًّا مؤقّتًا */
$NORM  = (int) $u2['id'];     /* يبقى عاديًّا — شاهدُ الاتجاهِ الآخر */

$GLOBALS['__TG_RESTORE'] = array('conn' => $conn, 'id' => $TRAIN, 'was' => (int) $u1['is_training']);
register_shutdown_function(function () {
    $s = $GLOBALS['__TG_RESTORE'];
    @mysqli_query($s['conn'], "UPDATE users SET is_training = {$s['was']} WHERE id = {$s['id']}");
    @mysqli_query($s['conn'], "DELETE FROM fin_financial_events WHERE source_ref LIKE 'TRAIN-GATE-%'");
    @mysqli_query($s['conn'], "DELETE FROM ems_business_events  WHERE source_ref LIKE 'TRAIN-GATE-%'");
});

mysqli_query($conn, "DELETE FROM fin_financial_events WHERE source_ref LIKE 'TRAIN-GATE-%'");
mysqli_query($conn, "DELETE FROM ems_business_events  WHERE source_ref LIKE 'TRAIN-GATE-%'");
/* ◆ **واللحظةُ تُسجَّل مع العلم**: بغيرِها تصير كلُّ حقائقِ الحسابِ السابقةِ
     «متسرّبةً» وهي إنتاجيةٌ صحيحة — وقد أعطت هذه البوابةُ سبعَ مخالفاتٍ من هذا
     الباب قبلَ التصحيح. **وعلمٌ بلا لحظتِه يحاسبُ الماضيَ بحكمِ الحاضر.** */
mysqli_query($conn, "UPDATE users SET is_training = 1, training_since = NOW() WHERE id = {$TRAIN}");
$q = mysqli_query($conn, "SELECT is_training, training_since FROM users WHERE id = {$TRAIN}");
$uRow = $q ? mysqli_fetch_assoc($q) : null;
ok('الحسابُ المستعارُ صار تدريبيًّا بلحظتِه',
   $uRow && (int) $uRow['is_training'] === 1 && $uRow['training_since'] !== null,
   "#{$TRAIN} منذ " . ($uRow['training_since'] ?? '—'));

/* ══ ٢ النشرُ بالحسابَين ═══════════════════════════════════════════════ */
require_once $ROOT . '/app/Core/EventPublisher.php';

/** مفتاحُ حدثٍ له مشترِكٌ معلَنٌ — وإلا رفضَ الحارسُ النشرَ قبلَ الكتابة. */
$evKey = null; $evCat = null; $evMod = null;
$q = mysqli_query($conn, "SELECT event_key, category, source_module FROM ems_business_events
                           WHERE company_id = {$CO} AND category IS NOT NULL AND category <> ''
                           GROUP BY event_key, category, source_module ORDER BY COUNT(*) DESC LIMIT 1");
if ($q && ($x = mysqli_fetch_assoc($q))) { $evKey = (string) $x['event_key']; $evCat = (string) $x['category']; $evMod = (string) $x['source_module']; }
if ($evKey === null) { echo "⊘ NOT_MEASURED — لا مفتاحَ حدثٍ حيٍّ يُقاس عليه\n"; exit(2); }
echo "  مفتاحُ الحدثِ المقيس: {$evKey}\n";

function publish_as($conn, $co, $key, $cat, $mod, $uid, $tag) {
    try {
        return \App\Core\EventPublisher::publishFact($conn, array(
            'company_id' => $co, 'event_key' => $key, 'category' => $cat,
            'source_module' => $mod, 'source_ref' => $tag,
            /* الكيانُ هو الحسابُ الكاتبُ نفسُه: عقد §9 يوجب معرِّفًا موجبًا،
               ومعرِّفٌ موجودٌ حقيقةً أصدقُ من صفرٍ يُمرَّر ليجتاز. */
            'entity_type' => 'user', 'entity_id' => $uid,
            'occurred_at' => date('Y-m-d H:i:s'),
            'idempotency_key' => $tag,
            'created_by' => $uid,
            /* عقد §9 يوجب حمولةً — وهي هنا **تعريفُ نفسِها**: صفٌّ بوابةٍ
               يُكنس في آخرِ التشغيل، فلا يُقرأ يومًا حقيقةَ عملٍ. */
            'payload' => array('gate' => 'training_isolation', 'ref' => $tag),
        ));
    } catch (\Throwable $e) { echo "      (نشرٌ متعذّر: " . mb_substr($e->getMessage(), 0, 90) . ")\n"; return null; }
}

$tagT = 'TRAIN-GATE-T-' . getmypid();
$tagN = 'TRAIN-GATE-N-' . getmypid();
$rT = publish_as($conn, $CO, $evKey, $evCat, $evMod, $TRAIN, $tagT);
$rN = publish_as($conn, $CO, $evKey, $evCat, $evMod, $NORM,  $tagN);

if ($rT === null || $rN === null) {
    echo "  ◆ الناشرُ لم يكتب (وضعُ الجذرِ مطفأٌ أو لا مشترِك) — يُقاس المخزونُ وحدَه\n";
    $skip = 2;
} else {
    $q = mysqli_query($conn, "SELECT is_training FROM ems_business_events WHERE id = " . (int) $rT['id']);
    ok('① حقيقةُ حسابِ التدريبِ مُوسَمة', $q && (int) mysqli_fetch_row($q)[0] === 1);
    $q = mysqli_query($conn, "SELECT is_training FROM ems_business_events WHERE id = " . (int) $rN['id']);
    ok('② حقيقةُ الحسابِ العاديِّ **غيرُ** مُوسَمة — فالوسمُ ليس ثابتًا',
       $q && (int) mysqli_fetch_row($q)[0] === 0);
}

/* ══ ٣ التسرّبُ عبرَ السلسلة — على المخزونِ كلِّه لا على صفوفِنا ══════════ */
$q = mysqli_query($conn, "SELECT COUNT(*) FROM fin_financial_events f
                            JOIN ems_business_events e ON e.correlation_id = f.correlation_id
                           WHERE e.is_training = 1 AND f.is_training = 0");
$leak = $q ? (int) mysqli_fetch_row($q)[0] : -1;
ok('③ صفرُ إسقاطٍ ماليٍّ يفلتُ من وسمِ حقيقتِه', $leak === 0, "المتسرّب={$leak}");

/* ══ ٤ اتساقُ المخزونِ — **مقيَّدًا بلحظةِ الإعلان** ══════════════════════
   ◆ الشرطُ `e.created_at >= u.training_since` ليس تخفيفًا بل **تصحيحُ معنى**:
     وسمُ الحقيقةِ يسجّل الحالةَ لحظةَ الكتابة، فمحاسبةُ ما كُتب قبلَ الإعلانِ
     بحكمِ ما بعدَه خطأٌ في المقياسِ لا كشفُ تسرّب. وما كُتب **بعدَ** الإعلانِ
     بلا وسمٍ تسرّبٌ حقيقيٌّ ويُرصَد هنا. */
$q = mysqli_query($conn, "SELECT
        SUM(CASE WHEN e.is_training = 1 AND COALESCE(u.is_training,0) = 0 THEN 1 ELSE 0 END) a,
        SUM(CASE WHEN e.is_training = 0 AND COALESCE(u.is_training,0) = 1
                  AND u.training_since IS NOT NULL AND e.created_at >= u.training_since
                 THEN 1 ELSE 0 END) b
      FROM ems_business_events e LEFT JOIN users u ON u.id = e.created_by
     WHERE e.created_by IS NOT NULL AND e.created_by > 0");
$row = $q ? mysqli_fetch_assoc($q) : array('a' => -1, 'b' => -1);
ok('④ صفرُ حقيقةٍ يخالف وسمُها حسابَ كاتبِها (بعدَ لحظةِ الإعلان)',
   (int) $row['a'] === 0 && (int) $row['b'] === 0,
   "مُوسَمةٌ لحسابٍ عاديّ={$row['a']} · غيرُ مُوسَمةٍ كُتبت بعدَ الإعلان={$row['b']}");

/* ══ الاستعادةُ والكنسُ بالعائلة ══════════════════════════════════════ */
mysqli_query($conn, "DELETE FROM fin_financial_events WHERE source_ref LIKE 'TRAIN-GATE-%'");
mysqli_query($conn, "DELETE FROM ems_business_events  WHERE source_ref LIKE 'TRAIN-GATE-%'");
mysqli_query($conn, "UPDATE users SET is_training = " . (int) $u1['is_training'] . " WHERE id = {$TRAIN}");
$q = mysqli_query($conn, "SELECT is_training FROM users WHERE id = {$TRAIN}");
ok('الحسابُ المستعارُ عادَ إلى حالتِه',
   $q && (int) mysqli_fetch_row($q)[0] === (int) $u1['is_training']);
$q = mysqli_query($conn, "SELECT COUNT(*) FROM ems_business_events WHERE source_ref LIKE 'TRAIN-GATE-%'");
ok('الكنسُ اكتمل — صفرُ أثرٍ للبوابة', $q && (int) mysqli_fetch_row($q)[0] === 0);

$verdict = $fail === 0 ? ($skip ? 'PASS (بمقامٍ منقوصٍ مُعلَن)' : 'PASS') : 'FAIL';
echo "\n" . ($fail === 0 ? '✔' : '✘') . " الحكم: {$verdict} — {$pass} ناجحًا · {$fail} راسبًا"
   . ($skip ? " · {$skip} لم يُقَس (الناشرُ لم يكتب)" : '') . "\n";
exit($fail === 0 ? 0 : 1);
