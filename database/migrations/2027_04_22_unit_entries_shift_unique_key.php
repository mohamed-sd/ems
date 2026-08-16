<?php
/**
 * 2027_04_22_unit_entries_shift_unique_key.php
 * ═══════════════════════════════════════════════════════════════════════════
 * القفلُ الذي يمنع قيدَين لآليةٍ واحدةٍ في ورديةٍ واحدةٍ من يومٍ واحد
 *
 * نصُّ الحكم (المواصفة 70 · TSP-0043): UNIQUE (company_id, work_date, shift_no, slot_id)
 * «وهو ما يمنع ازدواجَ الساعات».
 *
 * ── لماذا لم يُفرَض مباشرةً ─────────────────────────────────────────────
 * القياسُ الحيّ: 120 مجموعةً متصادمةً على (company_id, entry_date, shift, equipment_id)،
 * والزائدُ فيها **9,880 صفًّا من 10,142** — أي 97.4٪ من الجدول. وحذفُها يجرّ معه
 * **23,500 من 23,513 سجلَّ اعتمادٍ** في `unit_approvals` و9,880 سطرَ ساعاتٍ في
 * `unit_time_log`. فالحذفُ ليس تنظيفَ مكرراتٍ بل محوُ مجموعةِ البياناتِ التي
 * تُشغَّل عليها سلسلةُ الاعتمادِ كلُّها.
 *
 * ── الطريقُ المسلوك: وسمٌ ثم قفلٌ يتجاهل الموسوم ───────────────────────
 * الزائدُ كلُّه من **دفعةِ بذرٍ واحدة** (`entry_no LIKE 'UE-2608%'`) وصفرٌ منه له
 * واقعةٌ مالية. فيُوسَم `seed_tag` — وهذا ما توجبه المواصفةُ نفسُها في TS-05
 * («وسمُ البيانِ المبذورِ إلزاميٌّ · فيُعزل أو يُمحى بلا لمسِ بيانٍ حقيقيّ») —
 * ثم يُفرض القفلُ على عمودٍ محسوبٍ يحمل المفتاحَ للصفوفِ **غيرِ الموسومة** و NULL
 * للموسومة. وUNIQUE في MariaDB يعدّ كلَّ NULL متمايزًا، فالبذورُ تتعايش والحقيقيُّ
 * مقفول.
 *
 * ◆ النتيجة: صفرُ صفٍّ محذوف · و262 صفًّا تحت القفلِ فورًا · وكلُّ قيدٍ جديدٍ
 *   يُرفض بنيويًّا إن كرّر آليةً في ورديةٍ من يوم — لا خدميًّا فحسب.
 * ◆ ومتى أُفرغت البذورُ لاحقًا: يُحذف العمودُ المحسوبُ ويُفرض المفتاحُ المباشرُ
 *   كما في نصِّ المواصفة. (خطوةٌ سطرانِ — مُدوَّنةٌ هنا كي لا تُنسى.)
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

const SEED = 'legacy-seed-20260812';

echo "══ قفلُ «قيدٌ واحدٌ لكلِّ آليةٍ في كلِّ ورديةٍ في كلِّ يوم» ══\n\n";

$one = function (string $sql) use ($conn) { $r = $conn->query($sql); return $r ? $r->fetch_row()[0] : null; };

/* ── ① وسمُ النسخِ الزائدةِ — تحديثٌ لا حذف ──────────────────────── */
$dupExtras = "SELECT u.id FROM unit_entries u
              JOIN (SELECT company_id, entry_date, shift, equipment_id, MIN(id) keep
                    FROM unit_entries GROUP BY 1,2,3,4 HAVING COUNT(*) > 1) g
                ON g.company_id = u.company_id AND g.entry_date = u.entry_date
               AND g.shift <=> u.shift AND g.equipment_id <=> u.equipment_id
             WHERE u.id <> g.keep";

$before   = (int) $one("SELECT COUNT(*) FROM unit_entries");
$toTag    = (int) $one("SELECT COUNT(*) FROM ($dupExtras) d");
$withFin  = (int) $one("SELECT COUNT(*) FROM unit_entries WHERE id IN ($dupExtras) AND event_id IS NOT NULL AND event_id > 0");

echo "  الصفوفُ الآن: " . number_format($before) . " · المرشَّحُ للوسم: " . number_format($toTag) . "\n";
if ($withFin > 0) {
    exit("  ✘ توقّف: $withFin منها له واقعةٌ مالية — لا يُوسَم بيانٌ ماليٌّ بذرًا. يلزم قرارُ مالك.\n");
}
echo "  ✔ صفرٌ منها له واقعةٌ مالية — الوسمُ آمن\n";

$already = (int) $one("SELECT COUNT(*) FROM unit_entries WHERE seed_tag = '" . SEED . "'");
if ($already >= $toTag && $toTag > 0) {
    echo "  · موسومةٌ سلفًا ($already)\n";
} else {
    if (!$conn->query("UPDATE unit_entries SET seed_tag = '" . SEED . "'
                       WHERE seed_tag IS NULL AND id IN ($dupExtras)")) {
        exit("  ✘ تعذّر الوسم: {$conn->error}\n");
    }
    echo '  ✔ وُسم ' . number_format($conn->affected_rows) . " صفًّا بـ" . SEED . " (لم يُحذف صفٌّ واحد)\n";
}

/* ── ② العمودُ المحسوب: مفتاحٌ للحقيقيِّ و NULL للمبذور ────────────── */
$hasCol = function (string $c) use ($conn): bool {
    $r = $conn->query("SHOW COLUMNS FROM unit_entries LIKE '" . $conn->real_escape_string($c) . "'");
    return $r && $r->num_rows > 0;
};
if ($hasCol('shift_slot_key')) {
    echo "  · العمودُ المحسوبُ قائمٌ سلفًا\n";
} else {
    $sql = "ALTER TABLE unit_entries ADD COLUMN `shift_slot_key` VARCHAR(96)
            AS (CASE WHEN `seed_tag` IS NULL
                     THEN CONCAT_WS('|', `company_id`, `entry_date`, `shift`, `equipment_id`)
                     ELSE NULL END) STORED
            COMMENT 'مفتاحُ القفل: يحمل (كيان|تاريخ|وردية|آلية) للصفِّ الحقيقيِّ و NULL للمبذور'";
    if (!$conn->query($sql)) { exit("  ✘ تعذّر العمودُ المحسوب: {$conn->error}\n"); }
    echo "  ✔ عمودٌ محسوبٌ shift_slot_key\n";
}

/* ── ③ إثباتُ خلوِّ غيرِ الموسومِ من التصادمِ قبلَ فرضِ القفل ───────── */
$clash = (int) $one("SELECT COUNT(*) FROM (SELECT shift_slot_key FROM unit_entries
                     WHERE shift_slot_key IS NOT NULL GROUP BY 1 HAVING COUNT(*) > 1) d");
$guarded = (int) $one("SELECT COUNT(*) FROM unit_entries WHERE shift_slot_key IS NOT NULL");
echo "  الصفوفُ تحتَ القفل: " . number_format($guarded) . " · تصادمٌ بينها: $clash\n";
if ($clash > 0) { exit("  ✘ توقّف: ما زال بينها تصادم — لا يُفرض القفل\n"); }

/* ── ④ فرضُ القفل ─────────────────────────────────────────────────── */
$hasIdx = function (string $i) use ($conn): bool {
    $r = $conn->query("SHOW INDEX FROM unit_entries WHERE Key_name = '" . $conn->real_escape_string($i) . "'");
    return $r && $r->num_rows > 0;
};
if ($hasIdx('uq_shift_ue')) {
    echo "  · القفلُ مركَّبٌ سلفًا\n";
} elseif ($conn->query("ALTER TABLE unit_entries ADD UNIQUE KEY `uq_shift_ue` (`shift_slot_key`)")) {
    echo "  ✔ القفلُ uq_shift_ue مُفرَضٌ بنيويًّا\n";
} else {
    exit("  ✘ تعذّر القفل: {$conn->error}\n");
}

/* ── ⑤ الحصيلة ────────────────────────────────────────────────────── */
$after  = (int) $one("SELECT COUNT(*) FROM unit_entries");
$seeded = (int) $one("SELECT COUNT(*) FROM unit_entries WHERE seed_tag IS NOT NULL");
$appr   = (int) $one("SELECT COUNT(*) FROM unit_approvals");
$log    = (int) $one("SELECT COUNT(*) FROM unit_time_log");
echo "\n── الحصيلة ──\n";
echo '  unit_entries: ' . number_format($after) . ' (كانت ' . number_format($before) . " — صفرُ حذف)\n";
echo '  موسومٌ بذرًا: ' . number_format($seeded) . ' · تحتَ القفل: ' . number_format($guarded) . "\n";
echo '  unit_approvals: ' . number_format($appr) . ' · unit_time_log: ' . number_format($log) . " — لم تُمسّا\n";
echo "\n✔ تمّت\n";
