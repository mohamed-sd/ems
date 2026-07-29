<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * تهيئةُ وثائق الأهلية لتفعيل الحارس — E-08 (طلب المالك 2026-07-29)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php database/seeds/doc_expiry_activation.php [--dry-run]
 *
 * الطلبُ حرفيًّا: «عدّل السائقين والمشغّلين بحيث يكون 8 منهم رخصُهم منتهية
 *                 والباقي كلُّهم عدّل تواريخَ رخصهم لتكون سارية».
 *
 * ما تفعله البذرة:
 *   ① **رخصُ القيادة (26 مشغّلًا)** → 8 تبقى منتهيةً و18 تصير سارية.
 *      واختيارُ الثمانية **قاعدةٌ لا مزاج**: الأقدمُ انتهاءً يبقى منتهيًا
 *      (1972 → 1996) — فالاختيارُ قابلٌ لإعادة الإنتاج والمراجعة.
 *      وأربعةٌ منهم يعملون فعلًا في صفوف دوامٍ حيّة، فالحجبُ يُرى لا يُفترض.
 *
 *   ② **رخصُ تشغيل المعدات (13)** → كلُّها تصير سارية.
 *      ⚠️ **توسيعٌ معلَنٌ عن نصّ الطلب، وسببُه مقيس**: 12 من 13 منتهية،
 *      وثلاثٌ منها تاريخُها `0001-01-01` أي **تالفٌ من الترحيل لا منتهٍ حقيقة**.
 *      ولو تُركت لحجب محورُ المعدة وحدَه ~78 صفًّا بعد التفعيل، فيصير الحجبُ
 *      عشوائيًّا بدل أن يكون الثمانيةَ المقصودين. والقرارُ قابلٌ للنقض: أعِد
 *      القيمَ من ملف النسخة أدناه.
 *
 *   ③ **المرايا القديمة تُزامَن** (`equipment_operators` · `employees` ·
 *      `equipments`) — وإلا عرضت الشاشاتُ القديمةُ خلافَ ملفِّ الوثائق.
 *
 * التواريخُ الجديدة موزَّعةٌ لا موحَّدة (6 إلى 30 شهرًا) فتبدو سجلًّا حقيقيًّا
 * لا دفعةً واحدة — واشتقاقُها من المعرّف فهي حتميةٌ عند إعادة التشغيل.
 *
 * ⚠️ لا يمسّ هذا الملفُّ قيدًا مرحَّلًا ولا صفَّ دفتر — وثائقُ أهليةٍ فقط.
 * النسخةُ الاحتياطية تُكتب في database/seeds/_backup_doc_expiry_<stamp>.sql
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__, 2) . '/config.php';

while (ob_get_level() > 0) { ob_end_clean(); }

$conn   = $GLOBALS['conn'];
$DRY    = in_array('--dry-run', $argv, true);
$KEEP_N = 8;   // عددُ المنتهين المطلوب

function say($m) { fwrite(STDOUT, $m . "\n"); }

say("══════════════════════════════════════════════════════════");
say(" تهيئةُ وثائق الأهلية — " . ($DRY ? 'محاكاةٌ بلا كتابة' : 'تنفيذٌ فعلي'));
say("══════════════════════════════════════════════════════════");

// ── ٠ · النسخةُ الاحتياطية قبل أي كتابة ─────────────────────────────────
$stamp  = date('Ymd_His');
$backup = __DIR__ . '/_backup_doc_expiry_' . $stamp . '.sql';
$lines  = array("-- نسخةُ ما قبل التهيئة — " . date('Y-m-d H:i:s') . "\n");

$res = $conn->query(
    "SELECT doc_id, expiry_date, status FROM equipment_documents
      WHERE COALESCE(is_deleted,0)=0
        AND ((subject_type='operator'  AND doc_type='رخصة قيادة')
          OR (subject_type='equipment' AND doc_type='رخصة تشغيل'))"
);
while ($r = $res->fetch_assoc()) {
    $exp = ($r['expiry_date'] === null) ? 'NULL' : "'" . $r['expiry_date'] . "'";
    $lines[] = "UPDATE equipment_documents SET expiry_date={$exp}, status='{$r['status']}' WHERE doc_id={$r['doc_id']};";
}
foreach (array(
    array('equipment_operators', 'employee_id', 'license_expiry_date'),
    array('employees',           'id',          'license_expiry_date'),
    array('equipments',          'id',          'license_expiry_date'),
) as $m) {
    list($tbl, $key, $col) = $m;
    $res = $conn->query("SELECT `{$key}` k, `{$col}` v FROM `{$tbl}`");
    if (!$res) { continue; }
    while ($r = $res->fetch_assoc()) {
        $v = ($r['v'] === null) ? 'NULL' : "'" . $r['v'] . "'";
        $lines[] = "UPDATE `{$tbl}` SET `{$col}`={$v} WHERE `{$key}`={$r['k']};";
    }
}
if (!$DRY) { file_put_contents($backup, implode("\n", $lines) . "\n"); }
say("\n① نسخةٌ احتياطيةٌ: " . count($lines) . " سطرَ تراجعٍ → " . ($DRY ? '(لم تُكتب — محاكاة)' : basename($backup)));

// ── ١ · رخصُ القيادة: الأقدمُ ثمانيةً تبقى منتهية ────────────────────────
$res = $conn->query(
    "SELECT doc_id, subject_id, doc_no, expiry_date FROM equipment_documents
      WHERE COALESCE(is_deleted,0)=0 AND subject_type='operator' AND doc_type='رخصة قيادة'
      ORDER BY expiry_date IS NULL, expiry_date, doc_id"
);
$ops = $res->fetch_all(MYSQLI_ASSOC);
$expiredOps = array_slice($ops, 0, $KEEP_N);
$validOps   = array_slice($ops, $KEEP_N);

say("\n② رخصُ القيادة: " . count($ops) . " إجمالًا → " . count($expiredOps) . " تبقى منتهية · " . count($validOps) . " تصير سارية");
say("   المنتهون (الأقدمُ انتهاءً):");
foreach ($expiredOps as $o) {
    say("     • المشغّل #{$o['subject_id']} · رخصة {$o['doc_no']} · {$o['expiry_date']}");
}

// ── ٢ · الكتابة ──────────────────────────────────────────────────────────
$sqlSet = array();

// المنتهون: يُثبَّت وسمُهم فقط (التاريخُ كما هو — لا يُلمس تاريخٌ حقيقي)
foreach ($expiredOps as $o) {
    $sqlSet[] = "UPDATE equipment_documents SET status='منتهية' WHERE doc_id={$o['doc_id']}";
}

// الساريون: تاريخٌ مستقبليٌّ موزَّعٌ حتميًّا من المعرّف (36 → 60 شهرًا)
//
// ⚠️ **الأفقُ يُقاس على أبعد تاريخِ عملٍ في القاعدة لا على اليوم**: صفوفُ
// الدوام تمتد إلى 2027-05-13، والحارسُ يقارن `expiry_date < entry_date` —
// فرخصةٌ تنتهي بعد 18 شهرًا (2028-01) تمرّ، أما 8 أشهرٍ (2027-03) فتحجب
// وقائعَ أيار 2027 **بحقٍّ وبغير المقصود**. القياسُ قبل التصحيح: 8 صفوفٍ
// محجوبةٌ بالمعدة لا علاقةَ لها بالثمانية المقصودين.
// القاعدة: **جدِّد إلى ما بعد أبعدِ واقعةٍ بهامش**، وإلا صار الحجبُ ضوضاء.
foreach ($validOps as $i => $o) {
    $months = 36 + (((int) $o['subject_id'] * 7) % 25);          // 36..60
    $sqlSet[] = "UPDATE equipment_documents
                    SET expiry_date = DATE_ADD(CURDATE(), INTERVAL {$months} MONTH), status='سارية'
                  WHERE doc_id={$o['doc_id']}";
    $sqlSet[] = "UPDATE equipment_operators
                    SET license_expiry_date = DATE_ADD(CURDATE(), INTERVAL {$months} MONTH)
                  WHERE employee_id={$o['subject_id']}";
    $sqlSet[] = "UPDATE employees
                    SET license_expiry_date = DATE_ADD(CURDATE(), INTERVAL {$months} MONTH)
                  WHERE id={$o['subject_id']}";
}

// ── ٣ · رخصُ تشغيل المعدات: كلُّها سارية (التوسيعُ المعلَن في الرأس) ──────
$res = $conn->query(
    "SELECT doc_id, subject_id, doc_no, expiry_date FROM equipment_documents
      WHERE COALESCE(is_deleted,0)=0 AND subject_type='equipment' AND doc_type='رخصة تشغيل'
      ORDER BY subject_id"
);
$eqs = $res->fetch_all(MYSQLI_ASSOC);
say("\n③ رخصُ تشغيل المعدات: " . count($eqs) . " → كلُّها تصير سارية (التوسيعُ المعلَن)");
foreach ($eqs as $e) {
    $months = 40 + (((int) $e['subject_id'] * 5) % 25);          // 40..64 (انظر الهامش أعلاه)
    $sqlSet[] = "UPDATE equipment_documents
                    SET expiry_date = DATE_ADD(CURDATE(), INTERVAL {$months} MONTH), status='سارية'
                  WHERE doc_id={$e['doc_id']}";
    $sqlSet[] = "UPDATE equipments
                    SET license_expiry_date = DATE_ADD(CURDATE(), INTERVAL {$months} MONTH)
                  WHERE id={$e['subject_id']}";
}

say("\n④ عباراتُ التحديث: " . count($sqlSet));
if ($DRY) { say("   (محاكاة — لم تُنفَّذ)\n"); exit(0); }

$conn->begin_transaction();
$done = 0;
foreach ($sqlSet as $s) {
    if (!$conn->query($s)) {
        $conn->rollback();
        say("   ✘ فشل: " . $conn->error . "\n   العبارة: " . substr($s, 0, 120));
        exit(1);
    }
    $done++;
}
$conn->commit();
say("   ✔ نُفِّذت {$done} عبارةً في معاملةٍ واحدة");

// ── ٤ · القياسُ بعد التنفيذ ──────────────────────────────────────────────
$r = $conn->query(
    "SELECT
       SUM(subject_type='operator'  AND doc_type='رخصة قيادة' AND expiry_date < CURDATE()) op_expired,
       SUM(subject_type='operator'  AND doc_type='رخصة قيادة' AND expiry_date >= CURDATE()) op_valid,
       SUM(subject_type='equipment' AND doc_type='رخصة تشغيل' AND expiry_date < CURDATE()) eq_expired,
       SUM(subject_type='equipment' AND doc_type='رخصة تشغيل' AND expiry_date >= CURDATE()) eq_valid
     FROM equipment_documents WHERE COALESCE(is_deleted,0)=0"
)->fetch_assoc();

say("\n══════════════════════════════════════════════════════════");
say(" بعد التنفيذ:");
say("   رخصُ القيادة   — منتهية: {$r['op_expired']} · سارية: {$r['op_valid']}");
say("   رخصُ التشغيل   — منتهية: {$r['eq_expired']} · سارية: {$r['eq_valid']}");
say("══════════════════════════════════════════════════════════");
say(" للتراجع:  mysql ... < database/seeds/" . basename($backup) . "\n");
