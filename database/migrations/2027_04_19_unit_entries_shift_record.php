<?php
/**
 * 2027_04_19_unit_entries_shift_record.php
 * ═══════════════════════════════════════════════════════════════════════════
 * القيدُ اليوميُّ للورديةِ يكتمل في `unit_entries` — ولا يُنشأ `shift_entries`
 *
 * نصُّ الحكم (المواصفة 70 · TSP-0013 وما بعده): «سطرٌ واحدٌ لكلِّ آليةٍ في كلِّ
 * ورديةٍ في كلِّ يوم، فيه ساعاتُ التشغيلِ والاستعدادِ والتعطلِ والطرفُ المسؤولُ
 * والوقودُ كميةً وقراءةُ العدّادِ والمشغّل».
 *
 * ── لماذا تعديلٌ لا إنشاء ────────────────────────────────────────────────
 * المواصفةُ نفسُها تمنع الإنشاء: TSP-0003-د «إنشاءُ جدولٍ ثالثٍ لواقعةٍ لها
 * جدولان هو بعينُه عيبُ المسارينِ الموازيين». والقياسُ (tools/se01_shift_entries_diff.php
 * @ 2026-08-15): **20 من 29 عمودًا لها نظائرُ حيّة** في `timesheet` (48,746 صفًّا)
 * و`unit_entries` (10,142)، و`TimesheetEntryService` (1,086 سطرًا) يحمل آلةَ
 * الحالاتِ نفسَها وهو في **طورِ كتابةٍ مزدوجةٍ مُعلَن**. فالثالثُ تفريعٌ لا بناء.
 *
 * ── ما يُضاف هنا: 16 عمودًا ينقصُ `unit_entries` وحدَه ──────────────────
 * سبعةٌ منها نظيرُها في `timesheet` فقط (تفصيلُ الساعاتِ والإنتاج)، وتسعةٌ لا
 * نظيرَ لها في المخططِ كلِّه (الحاويةُ والعميلُ والعدّادُ والوقودُ والوسوم).
 *
 * ◆ ولا يُعاد تسميةُ عمودٍ قائم: `entry_date` تبقى (= work_date)، و`shift` تبقى
 *   (= shift_no)، و`equipment_id` تبقى (= machine_code)، و`state` تبقى
 *   (= entry_state). خريطةُ الأسماءِ في رأسِ الشاشةِ وفي se01.
 *
 * ── المفتاحُ الفريدُ: يُحاوَل ولا يُفرَض ─────────────────────────────────
 * `uq_shift(company_id, entry_date, shift, equipment_id)` مطلوبٌ بالمواصفة، لكنَّ
 * القياسَ الحيَّ يجد **120 مجموعةً متصادمة** — كلُّها صفوفٌ **مبذورةٌ** بدفعةٍ
 * واحدةٍ (`entry_no` بالبادئة UE-260812…) لا مراجعاتٍ مشروعة: `revises_entry_id`
 * و`superseded_by_id` فارغانِ في الصفوفِ العشرةِ آلافٍ كلِّها.
 * فتُضاف الفهارسُ غيرُ الفريدةِ الآن، ويُحاوَل الفريدُ: إن نجح فبها، وإن رفضته
 * القاعدةُ **لا يُحذف صفٌّ ولا يُلمس بيان** — بل يُطبع عددُ المتصادمِ واستعلامُه
 * ويبقى منعُ الازدواجِ **خدميًّا** في `TimesheetEntryService` كما هو مُعلَنٌ في
 * ترويستِه منذ 2026-07. حذفُ البذورِ قرارُ مالكٍ لا قرارُ هجرة.
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

echo "══ القيدُ اليوميُّ للوردية — إتمامُ unit_entries ══\n\n";

$hasCol = function (string $t, string $c) use ($conn): bool {
    $r = $conn->query("SHOW COLUMNS FROM `$t` LIKE '" . $conn->real_escape_string($c) . "'");
    return $r && $r->num_rows > 0;
};
$hasIdx = function (string $t, string $i) use ($conn): bool {
    $r = $conn->query("SHOW INDEX FROM `$t` WHERE Key_name='" . $conn->real_escape_string($i) . "'");
    return $r && $r->num_rows > 0;
};
$hasChk = function (string $t, string $c) use ($conn): bool {
    $r = $conn->query("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='$t'
          AND CONSTRAINT_NAME='" . $conn->real_escape_string($c) . "' AND CONSTRAINT_TYPE='CHECK'");
    return $r && $r->num_rows > 0;
};

/* ── ① الأعمدةُ الستةَ عشرة ───────────────────────────────────────────── */
$COLS = [
    ['entity_layer',      "ENUM('operations','contracting','holding') NOT NULL DEFAULT 'operations'", 'TS-03: طبقةُ الكيان'],
    ['container_key',     'VARCHAR(32) NULL',                'المفتاحُ الثلاثيُّ client-contract-renewal'],
    ['client_id',         'INT UNSIGNED NULL',               'العميلُ — مشتقٌّ من العقدِ ويُثبَّت لحظةَ القيد'],
    ['run_hours',         'DECIMAL(6,2) NOT NULL DEFAULT 0', 'ساعاتُ التشغيلِ الفعلية'],
    ['standby_hours',     'DECIMAL(6,2) NOT NULL DEFAULT 0', 'ساعاتُ الاستعداد'],
    ['breakdown_hours',   'DECIMAL(6,2) NOT NULL DEFAULT 0', 'ساعاتُ التعطل'],
    ['stop_reason_code',  'VARCHAR(32) NULL',                'رمزُ سببِ التوقف'],
    ['liable_party',      "ENUM('client','company','supplier') NULL", 'الطرفُ المسؤولُ عن التعطل'],
    ['meter_before',      'DECIMAL(12,2) NULL',              'قراءةُ العدّادِ قبل'],
    ['meter_after',       'DECIMAL(12,2) NULL',              'قراءةُ العدّادِ بعد'],
    ['fuel_received_qty', 'DECIMAL(10,2) NOT NULL DEFAULT 0', 'وقودٌ مستلَمٌ كميةً'],
    ['fuel_issued_qty',   'DECIMAL(10,2) NOT NULL DEFAULT 0', 'وقودٌ مصروفٌ كميةً'],
    ['tons',              'DECIMAL(12,2) NULL',              'الأطنان — تفصيلٌ فوق qty/unit_type لا بديلٌ عنه'],
    ['meters',            'DECIMAL(12,2) NULL',              'الأمتار'],
    ['created_by_role',   'SMALLINT UNSIGNED NULL',          'TS-04: الدورُ يُخزَّن مع المعرِّف لأن دورَ الشخصِ يتغير والسجلُّ لا'],
    ['seed_tag',          'VARCHAR(32) NULL',                "TS-05: وسمُ البيانِ المبذور — 'test-seed'"],
];
$added = 0; $skipped = 0;
foreach ($COLS as [$c, $type, $why]) {
    if ($hasCol('unit_entries', $c)) { echo "  · $c قائمٌ سلفًا\n"; $skipped++; continue; }
    $sql = "ALTER TABLE unit_entries ADD COLUMN `$c` $type COMMENT " . "'" . $conn->real_escape_string($why) . "'";
    if ($conn->query($sql)) { echo "  ✔ $c — $why\n"; $added++; }
    else { exit("  ✘ تعذّرت إضافةُ $c: {$conn->error}\n"); }
}

/* ── ② الفهارسُ الثلاثة ───────────────────────────────────────────────── */
echo "\n── الفهارس ──\n";
$IDX = [
    ['ix_container_ue', '(container_key)'],
    ['ix_machine_ue',   '(equipment_id, entry_date)'],
    ['ix_supplier_ue',  '(supplier_entity_id, entry_date)'],
];
foreach ($IDX as [$name, $cols]) {
    if ($hasIdx('unit_entries', $name)) { echo "  · $name قائمٌ سلفًا\n"; continue; }
    if ($conn->query("ALTER TABLE unit_entries ADD INDEX `$name` $cols")) { echo "  ✔ $name $cols\n"; }
    else { echo "  ✘ $name: {$conn->error}\n"; }
}

/* ── ③ قيودُ CHECK الثلاثة ────────────────────────────────────────────── */
echo "\n── قيودُ CHECK ──\n";
$CHK = [
    ['chk_ue_hours',  'run_hours + standby_hours + breakdown_hours <= 24',
        'مجموعُ ساعاتِ الورديةِ لا يتجاوز اليوم'],
    ['chk_ue_meter',  'meter_after IS NULL OR meter_before IS NULL OR meter_after >= meter_before',
        'العدّادُ لا يرجع إلى الوراء'],
    ['chk_ue_liable', 'breakdown_hours = 0 OR liable_party IS NOT NULL',
        'لا ساعةَ تعطلٍ بلا طرفٍ مسؤول'],
];
foreach ($CHK as [$name, $expr, $why]) {
    if ($hasChk('unit_entries', $name)) { echo "  · $name قائمٌ سلفًا\n"; continue; }
    /* أثبتْ أولًا أنَّ الصفوفَ القائمةَ تجتازه — وإلا لا يُفرَض */
    $r = $conn->query("SELECT COUNT(*) FROM unit_entries WHERE NOT ($expr)");
    $bad = $r ? (int) $r->fetch_row()[0] : -1;
    if ($bad > 0) { echo "  ⚠ $name: $bad صفًّا قائمًا يخالفه — لم يُفرَض (يلزم حسمُ البياناتِ أولًا)\n"; continue; }
    if ($conn->query("ALTER TABLE unit_entries ADD CONSTRAINT `$name` CHECK ($expr)")) {
        echo "  ✔ $name — $why (صفرُ مخالفٍ قائم)\n";
    } else { echo "  ✘ $name: {$conn->error}\n"; }
}

/* ── ④ المفتاحُ الفريد: يُحاوَل ولا يُفرَض ولا يُحذف بيان ───────────────── */
echo "\n── المفتاحُ الفريدُ uq_shift(company_id, entry_date, shift, equipment_id) ──\n";
if ($hasIdx('unit_entries', 'uq_shift_ue')) {
    echo "  · قائمٌ سلفًا\n";
} else {
    $r = $conn->query("SELECT COUNT(*) FROM (SELECT company_id, entry_date, shift, equipment_id
                       FROM unit_entries GROUP BY 1,2,3,4 HAVING COUNT(*)>1) d");
    $dups = $r ? (int) $r->fetch_row()[0] : -1;
    if ($dups === 0) {
        if ($conn->query("ALTER TABLE unit_entries ADD UNIQUE KEY `uq_shift_ue` (company_id, entry_date, shift, equipment_id)")) {
            echo "  ✔ فُرض بنيويًّا — صفرُ تصادم\n";
        } else { echo "  ✘ {$conn->error}\n"; }
    } else {
        echo "  ⚠ لم يُفرَض: $dups مجموعةً متصادمة (صفوفٌ مبذورةٌ بدفعةٍ واحدة، لا مراجعات)\n";
        echo "     ولم يُحذف صفٌّ واحد — الحذفُ قرارُ مالك. لفحصِها:\n";
        echo "     SELECT company_id, entry_date, shift, equipment_id, COUNT(*) c, GROUP_CONCAT(entry_no)\n";
        echo "       FROM unit_entries GROUP BY 1,2,3,4 HAVING c>1 ORDER BY c DESC;\n";
        echo "     ومنعُ الازدواجِ يبقى خدميًّا في TimesheetEntryService (مُعلَنٌ في ترويستِه).\n";
    }
}

echo "\n── الحصيلة ──\n";
echo "  أعمدةٌ مضافة: $added · قائمةٌ سلفًا: $skipped\n";
$r = $conn->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='unit_entries'");
echo '  أعمدةُ unit_entries الآن: ' . ($r ? $r->fetch_row()[0] : '?') . "\n";
$r = $conn->query("SELECT COUNT(*) FROM unit_entries");
echo '  صفوفٌ حيّة: ' . ($r ? number_format((int) $r->fetch_row()[0]) : '?') . " (لم يُحذف ولم يُعدَّل صفٌّ واحد)\n";
echo "\n✔ تمّت\n";
