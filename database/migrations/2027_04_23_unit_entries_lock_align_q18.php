<?php
/**
 * 2027_04_23_unit_entries_lock_align_q18.php
 * ═══════════════════════════════════════════════════════════════════════════
 * مواءمةُ القفلِ مع قاعدةِ ق-18 القائمة — القفلُ كان أشدَّ من الحكم
 *
 * ── ما اكتُشف بعد فرضِ القفل ─────────────────────────────────────────────
 * القاعدةُ محميةٌ سلفًا بقادحَين: `trg_ue_dup_shield_ins` و`trg_ue_dup_shield_upd`
 * (قاعدةُ ق-18). واختبارٌ سلبيٌّ حيٌّ أظهر أنَّ الرفضَ يأتي منهما برمز 1644 لا من
 * القفلِ برمز 1062 — أي أنَّ الحكمَ كان مفروضًا قبلَ هجرةِ 2027_04_22.
 *
 * ── والفرقُ بينهما عيبٌ أدخلتُه ─────────────────────────────────────────
 * القادحُ **يستثني الحالاتِ المنتهيةَ**:
 *     state NOT IN ('rejected','cancelled','superseded','reversed')
 * فالقيدُ المرفوضُ أو الملغى **لا يشغل الخانة**، ويجوز إدخالُ بديلٍ لنفسِ
 * (المعدة × التاريخ × الوردية). وعمودي المحسوبُ لم يستثنِها — فكان يمنع
 * البديلَ المشروع. أي: **قفلٌ أشدُّ من الحكمِ يكسر مسارًا صحيحًا**.
 *
 * والقادحُ كذلك **مقيَّدٌ بتاريخ** (`entry_date >= '2026-08-05'`) — «السكةُ
 * الجديدة». فالقفلُ يبقى ذا قيمةٍ: هو الأرضيةُ البنيويةُ بلا بوابةِ تاريخ،
 * والقادحُ هو الحكمُ الأدقُّ بحالاتِه. ويتطابقان الآن في الدلالةِ لا يتناقضان.
 *
 * ◆ والعمودُ المحسوبُ STORED يُعاد حسابُه عند التحديث — فمتى صار القيدُ
 *   `rejected` أفرغ مفتاحَه وحرّر الخانةَ تلقائيًّا بلا كود.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_USER') : ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_PASS') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

echo "══ مواءمةُ القفلِ مع ق-18 ══\n\n";
$one = function (string $s) use ($conn) { $r = $conn->query($s); return $r ? $r->fetch_row()[0] : null; };

/* ما الحالاتُ المستثناةُ في القادحِ فعلًا؟ تُقرأ منه لا تُكتب حدسًا */
$stmtText = (string) $one("SELECT ACTION_STATEMENT FROM information_schema.TRIGGERS
                           WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME='trg_ue_dup_shield_ins'");
if ($stmtText === '') { exit("  ✘ القادحُ trg_ue_dup_shield_ins غيرُ موجود — لا مواءمةَ بلا مرجع\n"); }
if (!preg_match("/state\s+NOT\s+IN\s*\(([^)]*)\)/i", $stmtText, $m)) {
    exit("  ✘ تعذّر استخراجُ الحالاتِ المستثناةِ من نصِّ القادح\n");
}
$excluded = $m[1];
echo "  الحالاتُ المستثناةُ في ق-18: $excluded\n";

$before = (int) $one("SELECT COUNT(*) FROM unit_entries WHERE shift_slot_key IS NOT NULL");
echo "  تحتَ القفلِ قبلَ المواءمة: " . number_format($before) . "\n";

/* إسقاطُ القفلِ ثم إعادةُ تعريفِ العمودِ ثم إعادةُ القفل */
if ($conn->query("SHOW INDEX FROM unit_entries WHERE Key_name='uq_shift_ue'")->num_rows) {
    if (!$conn->query("ALTER TABLE unit_entries DROP INDEX `uq_shift_ue`")) {
        exit("  ✘ تعذّر إسقاطُ القفل: {$conn->error}\n");
    }
    echo "  · أُسقط القفلُ مؤقتًا لإعادةِ تعريفِ العمود\n";
}

$sql = "ALTER TABLE unit_entries MODIFY COLUMN `shift_slot_key` VARCHAR(96)
        AS (CASE WHEN `seed_tag` IS NULL
                  AND `state` NOT IN ($excluded)
                 THEN CONCAT_WS('|', `company_id`, `entry_date`, `shift`, `equipment_id`)
                 ELSE NULL END) STORED
        COMMENT 'مفتاحُ القفل — يوائم ق-18: NULL للمبذورِ وللحالاتِ المنتهيةِ فلا تشغل الخانة'";
if (!$conn->query($sql)) { exit("  ✘ تعذّرت إعادةُ التعريف: {$conn->error}\n"); }
echo "  ✔ أُعيد تعريفُ العمودِ باستثناءِ الحالاتِ المنتهية\n";

$clash = (int) $one("SELECT COUNT(*) FROM (SELECT shift_slot_key FROM unit_entries
                     WHERE shift_slot_key IS NOT NULL GROUP BY 1 HAVING COUNT(*) > 1) d");
if ($clash > 0) { exit("  ✘ تصادمٌ بعد إعادةِ التعريف: $clash — لا يُعاد القفل\n"); }

if (!$conn->query("ALTER TABLE unit_entries ADD UNIQUE KEY `uq_shift_ue` (`shift_slot_key`)")) {
    exit("  ✘ تعذّرت إعادةُ القفل: {$conn->error}\n");
}
echo "  ✔ أُعيد القفلُ uq_shift_ue\n";

$after = (int) $one("SELECT COUNT(*) FROM unit_entries WHERE shift_slot_key IS NOT NULL");
$total = (int) $one("SELECT COUNT(*) FROM unit_entries");
echo "\n── الحصيلة ──\n";
echo '  تحتَ القفلِ بعدَ المواءمة: ' . number_format($after) . ' (كانت ' . number_format($before) . ")\n";
echo '  إجمالي الصفوف: ' . number_format($total) . " — صفرُ حذف\n";
echo "  القادحُ ق-18 هو الحكمُ الأدقُّ (بحالاتِه)، والقفلُ أرضيةٌ بنيويةٌ بلا بوابةِ تاريخ.\n";
echo "\n✔ تمّت\n";
