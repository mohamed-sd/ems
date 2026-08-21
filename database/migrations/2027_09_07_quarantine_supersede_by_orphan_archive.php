<?php
/**
 * 2027_09_07_quarantine_supersede_by_orphan_archive.php
 *   وصلُ حجرِ الحقولِ بأرشيفِ الأيتام — INJ-FIX-01 · GAP-09 × GAP-08
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **هذا تصحيحُ أثرٍ متبادلٍ أحدثتُه أنا** — ويُسجَّل ولا يُملَّس:
 *   حَجَرَت الموجةُ أ عشرَ قيمٍ ملوَّثةٍ في `ems_event_deliveries.consumer`
 *   (`2027_08_23`)، واستبدلتها بـ`legacy_uat_cons_*` وحفظت أصلَها ليُردَّ.
 *   ثم كنست `2027_09_05` (GAP-08) **الصفوفَ نفسَها** بوصفها تسليماتٍ يتيمةً،
 *   فنقلتها إلى `ems_event_delivery_orphans`.
 *   ⇒ فصار الحجرُ يشير إلى **صفٍّ لا وجودَ له في الجدولِ الحيّ**، ورسب الشاهدُ
 *     `injfix01_key_field_purity_proof` بعشرِ حالاتٍ غيرِ مطابقة. **والشاهدُ صادق.**
 *
 * ◆ **ولا بيانَ فُقد**: الصفوفُ كاملةً في `ems_event_delivery_orphans`، وأصلُها
 *   الملوَّثُ في `gov_key_pollution_archive.original_value` ولقطتُه في `row_snapshot`.
 *   **والمفقودُ مسارُ الردِّ لا البيان** — إذ لا موضعَ حيًّا يُردُّ إليه.
 *
 * ◆ **فيُعلَن الحجرُ منسوخًا بموضعِه الجديد** في عمودٍ مُسمًّى، ولا يُحذف ولا
 *   يُوسَم `restored_at` (فذلك يعني «رُدَّ» وهو لم يُردّ).
 *
 * ◆ **والشاهدُ يُضيَّق لا يُوسَّع**: يستثني المنسوخَ **الذي يحمل سببًا مكتوبًا
 *   وموضعًا موجودًا فيه صفُّه** — فيبقى يرسُب لأيِّ انفصالٍ جديدٍ غيرِ مُعلَن.
 *
 * التشغيل:  php database/migrations/2027_09_07_quarantine_supersede_by_orphan_archive.php
 * الرجوع :  php database/migrations/2027_09_07_quarantine_supersede_by_orphan_archive.php --revert
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

if (in_array('--revert', $argv, true)) {
    $r = $conn->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='gov_key_pollution_archive'
                          AND COLUMN_NAME='superseded_to'");
    if ($r && (int) $r->fetch_row()[0] > 0) {
        $conn->query("ALTER TABLE `gov_key_pollution_archive`
                      DROP COLUMN `superseded_to`, DROP COLUMN `superseded_reason`");
        echo "↺ أُسقط عمودا النسخ\n";
    }
    exit(0);
}

/* ══ ① عمودا النسخِ ═══════════════════════════════════════════════════════ */
$r = $conn->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='gov_key_pollution_archive'
                      AND COLUMN_NAME='superseded_to'");
if (!$r || (int) $r->fetch_row()[0] === 0) {
    $conn->query("ALTER TABLE `gov_key_pollution_archive`
        ADD COLUMN `superseded_to` VARCHAR(64) NULL
            COMMENT 'الجدولُ الذي انتقل إليه الصفُّ — فلا موضعَ حيًّا يُردُّ إليه',
        ADD COLUMN `superseded_reason` VARCHAR(300) NULL");
    echo "① أُضيف عمودا النسخ\n";
} else {
    echo "① عمودا النسخِ قائمان\n";
}

/* ══ ② الوصلُ — ولا يُعلَن منسوخًا إلا ما ثبت وجودُه في الموضعِ الجديد ═════ */
$r = $conn->query("SELECT COUNT(*) FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ems_event_delivery_orphans'");
if (!$r || (int) $r->fetch_row()[0] === 0) { exit("✘ أرشيفُ الأيتامِ غيرُ موجود — أُوقفت الهجرة\n"); }

$q = $conn->query("SELECT `id`,`src_table`,`src_column`,`src_row_id`,`replacement`
                     FROM `gov_key_pollution_archive`
                    WHERE `restored_at` IS NULL AND `superseded_to` IS NULL");
$cand = array();
while ($q && $x = $q->fetch_assoc()) { $cand[] = $x; }

$st = $conn->prepare("UPDATE `gov_key_pollution_archive`
                         SET `superseded_to` = ?, `superseded_reason` = ?
                       WHERE `id` = ?");
$done = 0; $skipped = 0;
foreach ($cand as $x) {
    /* أما زال صفُّه في الجدولِ الحيِّ ببديلِه؟ ⇒ سليمٌ ولا يُمَسّ */
    $r = $conn->query("SELECT `{$x['src_column']}` FROM `{$x['src_table']}`
                        WHERE `id` = " . (int) $x['src_row_id']);
    $cur = $r ? $r->fetch_row() : null;
    if ($cur && (string) $cur[0] === (string) $x['replacement']) { continue; }

    /* غائبٌ عن الحيّ — فأين هو؟ لا يُعلَن منسوخًا بالظنّ */
    if ($x['src_table'] !== 'ems_event_deliveries') { $skipped++; continue; }
    $r = $conn->query("SELECT COUNT(*) FROM `ems_event_delivery_orphans`
                        WHERE `id` = " . (int) $x['src_row_id']);
    if (!$r || (int) $r->fetch_row()[0] === 0) { $skipped++; continue; }

    $to = 'ems_event_delivery_orphans';
    $why = 'كُنس الصفُّ تسليمًا يتيمًا في GAP-08 (2027_09_05) بعدَ حجرِ حقلِه في '
         . '2027_08_23 — والبيانُ كاملٌ في الموضعِ الجديد، والمفقودُ مسارُ الردِّ لا البيان';
    $st->bind_param('ssi', $to, $why, $x['id']);
    if ($st->execute()) { $done++; }
}
$st->close();
echo "② أُعلن منسوخًا: {$done} · وتُرك بلا إعلانٍ (لم يثبت موضعُه): {$skipped}\n";

/* ══ ③ الحصيلة ════════════════════════════════════════════════════════════ */
$mis = 0; $chk = 0;
$q = $conn->query("SELECT `src_table`,`src_column`,`src_row_id`,`replacement`
                     FROM `gov_key_pollution_archive`
                    WHERE `restored_at` IS NULL AND `superseded_to` IS NULL");
while ($q && $x = $q->fetch_assoc()) {
    $r = $conn->query("SELECT `{$x['src_column']}` FROM `{$x['src_table']}`
                        WHERE `id` = " . (int) $x['src_row_id']);
    $cur = $r ? $r->fetch_row() : null;
    $chk++;
    if (!$cur || (string) $cur[0] !== (string) $x['replacement']) { $mis++; }
}
echo "───────────────────────────────────────────────────────────────\n";
printf("③ الحجرُ الحيُّ: %d · **غيرُ مطابقٍ بعدَ الوصل: %d**\n", $chk, $mis);
$tot = (int) $conn->query("SELECT COUNT(*) FROM `gov_key_pollution_archive`")->fetch_row()[0];
$sup = (int) $conn->query("SELECT COUNT(*) FROM `gov_key_pollution_archive`
                            WHERE `superseded_to` IS NOT NULL")->fetch_row()[0];
printf("   المقامُ %d = حيٌّ %d + منسوخٌ %d\n", $tot, $chk, $sup);
echo "◆ ولا يُعَدُّ المنسوخُ مردودًا: `restored_at` باقٍ فارغًا — فالردُّ لم يقع.\n";
