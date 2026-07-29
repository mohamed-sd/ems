<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * تعليمُ الوثائق تالفةِ التاريخ «موروثٌ بلا مرجع» — المهمة صفر
 * (EXECUTION_BRIEF_update0001 §4 · العقيدة ⑥: ما لا مرجعَ له يُعلَّم ولا يُمسح)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php database/seeds/doc_expiry_legacy_mark.php [--dry-run]
 *
 * الواقعُ المقيس (2026-07-30 · انظر docs/reports/DOC_EXPIRY_RECONCILIATION_20260730.md):
 *   26 وثيقةً بتاريخ انتهاءٍ قبل 2015 (8 رخص قيادة + 18 هوية) — كلُّها مرحَّلة،
 *   ومصادرُها الثلاثة (employees.identity_expiry_date · employees.license_expiry_date
 *   · equipment_operators.license_expiry_date) تحمل **التاريخَ التالفَ نفسَه** —
 *   فلا مرجعَ للتعبئة الرجعية، والتاريخُ الصحيح لا يُخترع (عقيدة ⑦: لا تلفيق).
 *
 * ما يفعله الباذر:
 *   ① يعلّق على `note` وسمَ «موروثٌ بلا مرجع (تاريخُ ترحيلٍ تالف)» — إلحاقًا
 *      لا استبدالًا، وبعطالةٍ: صفٌّ موسومٌ لا يوسم ثانية.
 *   ② **لا يمسّ `expiry_date` ولا `status` ولا أيَّ عمودٍ آخر** — التاريخُ
 *      التالف يبقى شاهدًا حتى يعيد المالكُ التقاطَ الوثائق الحقيقية.
 *
 * ⚠️ علَمُ `EMS_DOC_EXPIRY_GUARD` لا يعود إلى enforce قبل **صفرِ وثيقةٍ موسومةٍ
 *    بهذا الوسم** بين الأنواع الحاجبة (رخصة قيادة · رخصة تشغيل · استمارة ·
 *    تأمين · فحص دوري) — والهوية منبِّهةٌ لا حاجبة.
 *
 * النسخةُ الاحتياطية: database/seeds/_backup_doc_legacy_mark_<stamp>.sql
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__, 2) . '/config.php';

while (ob_get_level() > 0) { ob_end_clean(); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$DRY  = in_array('--dry-run', $argv, true);
$MARK = 'موروثٌ بلا مرجع (تاريخُ ترحيلٍ تالف)';

function say($m) { fwrite(STDOUT, $m . "\n"); }

say("══════════════════════════════════════════════════════════");
say(" تعليمُ التالف «موروثٌ بلا مرجع» — " . ($DRY ? 'محاكاةٌ بلا كتابة' : 'تنفيذٌ فعلي'));
say("══════════════════════════════════════════════════════════");

// ── ٠ · الصفوفُ المعنية: انتهاءٌ قبل 2015 + مرحَّلة + غيرُ موسومة ─────────
$st = $conn->prepare(
    "SELECT doc_id, subject_type, subject_id, doc_type, doc_no, expiry_date,
            migrated_from, COALESCE(note,'') note
       FROM equipment_documents
      WHERE COALESCE(is_deleted,0) = 0
        AND expiry_date IS NOT NULL AND expiry_date < '2015-01-01'
        AND COALESCE(note,'') NOT LIKE CONCAT('%', ?, '%')
      ORDER BY doc_id"
);
$needle = 'موروثٌ بلا مرجع';
$st->bind_param('s', $needle);
$st->execute();
$rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

say("\n① الصفوفُ غيرُ الموسومة بتاريخٍ قبل 2015: " . count($rows));
if (empty($rows)) {
    say("   لا شيءَ يُعلَّم — الباذرُ عاطلٌ بالعطالة (سبق وسمُها أو لا تالفَ).\n");
    exit(0);
}

// ── ١ · نسخةٌ احتياطيةٌ لقيم note قبل الكتابة ────────────────────────────
$stamp  = date('Ymd_His');
$backup = __DIR__ . '/_backup_doc_legacy_mark_' . $stamp . '.sql';
$lines  = array('-- ملاحظاتُ ما قبل الوسم — ' . date('Y-m-d H:i:s'));
foreach ($rows as $r) {
    $lines[] = "UPDATE equipment_documents SET note='"
             . $conn->real_escape_string($r['note'])
             . "' WHERE doc_id={$r['doc_id']};";
}
if (!$DRY) { file_put_contents($backup, implode("\n", $lines) . "\n"); }
say("② نسخةٌ احتياطية: " . (count($lines) - 1) . " سطرَ تراجعٍ → "
    . ($DRY ? '(محاكاة — لم تُكتب)' : basename($backup)));

// ── ٢ · الوسمُ إلحاقًا (سعةُ note = 200 محرفًا — تُفحص قبل الكتابة) ───────
say("\n③ الوسم:");
$upd = $conn->prepare("UPDATE equipment_documents SET note = ? WHERE doc_id = ?");
$done = 0;
$conn->begin_transaction();
foreach ($rows as $r) {
    $new = ($r['note'] === '') ? $MARK : ($r['note'] . ' · ' . $MARK);
    if (mb_strlen($new, 'UTF-8') > 200) {
        // لا يُبتر نصٌّ قائم: يُقدَّم الوسمُ ويُبتر القديمُ بعلامةٍ ظاهرة
        $new = mb_substr($r['note'], 0, 200 - mb_strlen(' … · ' . $MARK, 'UTF-8'), 'UTF-8')
             . ' … · ' . $MARK;
    }
    say("   • #{$r['doc_id']} {$r['subject_type']}/{$r['subject_id']} «{$r['doc_type']}» انتهاء={$r['expiry_date']}");
    if ($DRY) { continue; }
    $upd->bind_param('si', $new, $r['doc_id']);
    if (!$upd->execute()) {
        $conn->rollback();
        say("   ✘ فشل عند #{$r['doc_id']}: " . $conn->error);
        exit(1);
    }
    $done++;
}
$upd->close();
if ($DRY) { say("\n   (محاكاة — لم يُكتب شيء)\n"); exit(0); }
$conn->commit();
say("\n   ✔ وُسم {$done} صفًّا في معاملةٍ واحدة — التواريخُ لم تُمسّ");

// ── ٣ · القياسُ بعد التنفيذ ──────────────────────────────────────────────
$c = $conn->query(
    "SELECT COUNT(*) c FROM equipment_documents
      WHERE COALESCE(is_deleted,0)=0 AND note LIKE '%موروثٌ بلا مرجع%'"
)->fetch_assoc();
say("\n④ الموسومُ الآن في القاعدة: {$c['c']} وثيقة");
say("   للتراجع:  mysql ... < database/seeds/" . basename($backup) . "\n");
