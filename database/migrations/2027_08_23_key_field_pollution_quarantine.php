<?php
/**
 * 2027_08_23_key_field_pollution_quarantine.php
 *   نصٌّ بشريٌّ في حقلِ مفتاح — حجرٌ ثم تحييدٌ ثم قيدٌ · INJ-FIX-01 · الموجة ب · GAP-09
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المقيسُ حيًّا (2026-08-21): ثمانيةٌ وستون قيمةً** — جملٌ عربيةٌ من بذرِ
 *   UAT حُشرت في حقولِ مفاتيح:
 *       approval_workflow_rules.entity_type   20   (والجدولُ **صفرُ صفٍّ نشط**)
 *       approval_workflow_rules.action        20
 *       ems_event_deliveries.consumer         20   (أحداث 1..20 — بذرٌ أوّليّ)
 *       approval_requests.entity_type          4
 *       approval_requests.action               4
 *
 * ◆ **والكشفُ بالمسافةِ لا بالحرفِ العربيّ**: مسحٌ أوّليٌّ بـ`REGEXP '[ء-ي]'`
 *   أعلن `scr_sensitive_fields.status` ملوَّثًا بأربعةٍ وثلاثين — **وقيمتُه
 *   «معتمد» مفردةٌ معجميةٌ معلَنةٌ لا جملة**. فالحدُّ الفاصلُ أن حقلَ المفتاحِ
 *   **رمزٌ بلا مسافةٍ ولا فاصلِ `·`**، لا أن يكون لاتينيًّا. وكشفٌ يخلط بينهما
 *   يُتلف قاموسًا سليمًا باسمِ التنظيف.
 *
 * ◆ **ولا حذفَ مدمِّر — حجرٌ ثم تحييد**: يُنسخ الصفُّ كاملًا إلى
 *   `gov_key_pollution_archive` بصيغةٍ آليةٍ **قبلَ أيِّ مساس**، ثم تُحيَّد
 *   خانةُ المفتاحِ وحدَها برمزٍ مقروءٍ فريدٍ لكلِّ صفّ. فلا يضيع صفٌّ ولا سطرُ
 *   تاريخ، والرجوعُ يعيد الأصلَ حرفًا من الحجر.
 *   ◆ **والرمزُ فريدٌ بالمعرِّفِ عمدًا**: `uq_legacy_consumer_event` و
 *     `uniq_workflow_rule` مفاتيحُ فريدةٌ على هذه الخاناتِ نفسِها — فرمزٌ
 *     موحَّدٌ لكلِّ الصفوفِ كان سيرتطم بها ويُفشل الهجرةَ في منتصفِها.
 *
 * ◆ **وقيدٌ يمنع العودة**: `CHECK` على كلِّ خانةٍ يرفض المسافةَ والفاصل. فلا
 *   تعود أداةُ بذرٍ تكتب جملةً في حقلِ مفتاحٍ ولو أراد كاتبُها.
 *
 * التشغيل:  php database/migrations/2027_08_23_key_field_pollution_quarantine.php
 * الرجوع :  php database/migrations/2027_08_23_key_field_pollution_quarantine.php --revert
 * الشاهد :  php tests/injfix01_key_field_purity_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$revert = in_array('--revert', $argv, true);

/** (جدول، خانةُ مفتاح، بادئةُ الرمزِ البديل) */
$TARGETS = array(
    array('approval_workflow_rules', 'entity_type', 'legacy_uat_et'),
    array('approval_workflow_rules', 'action',      'legacy_uat_ac'),
    array('ems_event_deliveries',    'consumer',    'legacy_uat_cons'),
    array('approval_requests',       'entity_type', 'legacy_uat_et'),
    array('approval_requests',       'action',      'legacy_uat_ac'),
);
/** شرطُ التلوث: مسافةٌ أو فاصلُ «·» — لا الحرفُ العربيُّ بذاتِه. */
$POLLUTED = "(`%s` LIKE '%% %%' OR `%s` LIKE '%%·%%')";

/* ══════════════════════════ الرجوع ══════════════════════════════════════ */
if ($revert) {
    foreach ($TARGETS as $t) {
        list($tbl, $col) = $t;
        $conn->query("ALTER TABLE `{$tbl}` DROP CONSTRAINT `chk_keypure_{$tbl}_{$col}`");
    }
    echo "↺ القيود: أُزيلت\n";
    $n = 0;
    $r = $conn->query("SELECT `id`,`src_table`,`src_column`,`src_row_id`,`original_value`
                         FROM `gov_key_pollution_archive` WHERE `restored_at` IS NULL");
    $rows = array();
    while ($r && $x = $r->fetch_assoc()) { $rows[] = $x; }
    foreach ($rows as $x) {
        $st = $conn->prepare("UPDATE `{$x['src_table']}` SET `{$x['src_column']}` = ? WHERE `id` = ?");
        $st->bind_param('si', $x['original_value'], $x['src_row_id']);
        if ($st->execute() && $conn->affected_rows >= 0) { $n++; }
        $st->close();
        $conn->query("UPDATE `gov_key_pollution_archive` SET `restored_at` = NOW() WHERE `id` = " . (int) $x['id']);
    }
    echo "↺ أُعيد {$n} قيمةً إلى أصلِها من الحجر\n";
    exit(0);
}

/* ══ ① جدولُ الحجر — يُنشأ مرةً ولا يُمسّ محتواه ═══════════════════════════ */
$conn->query(
    "CREATE TABLE IF NOT EXISTS `gov_key_pollution_archive` (
       `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
       `src_table`      VARCHAR(64)  NOT NULL,
       `src_column`     VARCHAR(64)  NOT NULL,
       `src_row_id`     BIGINT       NOT NULL,
       `original_value` TEXT         NOT NULL,
       `replacement`    VARCHAR(191) NOT NULL,
       `row_snapshot`   LONGTEXT     NULL,
       `reason`         VARCHAR(191) NOT NULL,
       `quarantined_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
       `restored_at`    DATETIME     NULL,
       PRIMARY KEY (`id`),
       KEY `ix_kpa_src` (`src_table`, `src_column`, `src_row_id`)
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
if ($conn->errno) { exit("✘ تعذّر إنشاءُ جدولِ الحجر: {$conn->error}\n"); }
echo "① جدولُ الحجر: جاهز\n";

/* ══ ② الحجرُ ثم التحييد ══════════════════════════════════════════════════ */
$totalFound = 0; $totalFixed = 0;
foreach ($TARGETS as $t) {
    list($tbl, $col, $prefix) = $t;
    $where = sprintf($POLLUTED, $col, $col);

    $r = $conn->query("SELECT * FROM `{$tbl}` WHERE {$where}");
    $rows = array();
    while ($r && $x = $r->fetch_assoc()) { $rows[] = $x; }
    $found = count($rows);
    $totalFound += $found;
    if (!$found) { echo "② {$tbl}.{$col}: نظيفٌ سلفًا\n"; continue; }

    $fixed = 0;
    foreach ($rows as $row) {
        $rid  = (int) $row['id'];
        $orig = (string) $row[$col];
        $repl = $prefix . '_' . $rid;           // فريدٌ بالمعرِّف — فلا يرتطم بمفتاحٍ فريد

        $st = $conn->prepare(
            "INSERT INTO `gov_key_pollution_archive`
               (`src_table`,`src_column`,`src_row_id`,`original_value`,`replacement`,`row_snapshot`,`reason`)
             VALUES (?, ?, ?, ?, ?, ?, ?)");
        $snap = json_encode($row, JSON_UNESCAPED_UNICODE);
        $why  = 'INJ-FIX-01 §ب · GAP-09 — جملةُ بذرِ UAT في حقلِ مفتاح';
        $st->bind_param('ssissss', $tbl, $col, $rid, $orig, $repl, $snap, $why);
        if (!$st->execute()) { exit("✘ فشلَ الحجرُ {$tbl}#{$rid}: " . $st->error . "\n"); }
        $st->close();

        $st = $conn->prepare("UPDATE `{$tbl}` SET `{$col}` = ? WHERE `id` = ?");
        $st->bind_param('si', $repl, $rid);
        if (!$st->execute()) { exit("✘ فشلَ التحييدُ {$tbl}#{$rid}: " . $st->error . "\n"); }
        $st->close();
        $fixed++;
    }
    $totalFixed += $fixed;
    echo "② {$tbl}.{$col}: حُجر وحُيِّد {$fixed} من {$found}\n";
}

/* ══ ③ القيدُ الذي يمنع العودة ════════════════════════════════════════════ */
foreach ($TARGETS as $t) {
    list($tbl, $col) = $t;
    $name = "chk_keypure_{$tbl}_{$col}";
    $e = $conn->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                        WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = '{$tbl}'
                          AND CONSTRAINT_NAME = '{$name}'");
    if ($e && (int) $e->fetch_row()[0] > 0) { echo "③ {$name}: قائمٌ سلفًا\n"; continue; }
    $conn->query("ALTER TABLE `{$tbl}` ADD CONSTRAINT `{$name}`
                    CHECK (`{$col}` NOT LIKE '% %' AND `{$col}` NOT LIKE '%·%')");
    if ($conn->errno) { echo "③ {$name}: ✘ {$conn->error}\n"; continue; }
    echo "③ {$name}: أُضيف\n";
}

/* ══ ④ استيثاق ═══════════════════════════════════════════════════════════ */
echo "───────────────────────────────────────────────────────────────\n";
$left = 0;
foreach ($TARGETS as $t) {
    list($tbl, $col) = $t;
    $where = sprintf($POLLUTED, $col, $col);
    $q = $conn->query("SELECT COUNT(*) FROM `{$tbl}` WHERE {$where}");
    $left += $q ? (int) $q->fetch_row()[0] : 0;
}
echo "وُجد {$totalFound} · حُيِّد {$totalFixed} · الباقي الملوَّث: {$left}\n";
echo "الشاهد: php tests/injfix01_key_field_purity_proof.php\n";
