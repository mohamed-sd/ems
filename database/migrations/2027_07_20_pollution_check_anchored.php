<?php
/**
 * 2027_07_20_pollution_check_anchored.php — الخطوةُ ⑤ بقيدٍ حقيقيٍّ لا بقادح
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ الخطوةُ الخامسةُ في قرارِ المالك: «**ثم قيدٌ يمنع عودتَه**».
 *
 * ◆ ومحاولتان سبقتا وفشلتا — وتُسجَّلان لأنهما تشرحان الحلّ:
 *   ① `CHECK` عادٍ: **رُفض** لأنه يُفحص على كلِّ الصفوفِ لحظةَ إضافتِه
 *      والصفوفُ الملوَّثةُ قائمة.
 *   ② `BEFORE INSERT` قادحًا: **رُفض** بـ«لا تملك SUPER والتسجيلُ الثنائيُّ
 *      مُفعَّل» — رغم وجودِ 34 قادحًا سابقًا في القاعدة (فالمنعُ طارئٌ بتغيُّرِ
 *      إعدادِ الخادمِ لا بعجزٍ في التصميم).
 *
 * ◆ **والحلُّ قيدٌ بمرساةٍ**: `CHECK (id <= @anchor OR NOT <شرطُ التلوث>)`.
 *   فالصفوفُ القائمةُ تُعفى **بمفتاحِها هي** — لا باستثناءٍ مكتوبٍ ولا بتعطيلِ
 *   الفحص — والصفُّ الجديدُ (id أكبر) يخضع للشرطِ كاملًا. فيمرُّ الفحصُ لحظةَ
 *   الإضافةِ ويمنع العودةَ فعلًا. وهو **قيدٌ في القاعدةِ لا حارسٌ في الشيفرة**.
 *
 * ◆ **والمرساةُ تُقرأ لحظةَ التنفيذِ من الجدولِ نفسِه** — لا تُكتب رقمًا، فلا
 *   تتقادم ولا تُخطئ جدولًا.
 *
 * ◆ والنطاقُ **المجالاتُ المحميةُ أولًا**: القراراتُ الماليةُ · الالتزاماتُ
 *   القانونيةُ · سلاليمُ الاعتمادِ · السقوفُ المالية — لأن جملةً بشريةً في
 *   `entity_type` هناك تُفسِد مسارَ اعتمادٍ أو قيدًا ماليًّا لا مجرَّدَ عرض.
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
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$targets = array();
$r = $conn->query("SELECT DISTINCT table_name, column_name FROM gov_test_data_isolation
                    WHERE policy_domain <> 'OPERATIONAL_DATA' ORDER BY table_name");
while ($r && ($x = $r->fetch_assoc())) { $targets[] = $x; }

$made = 0; $skipped = array(); $already = 0;
foreach ($targets as $t) {
    $tbl = $t['table_name']; $col = $t['column_name'];
    if (!preg_match('~^[A-Za-z0-9_]+$~', $tbl) || !preg_match('~^[A-Za-z0-9_]+$~', $col)) { continue; }

    $cname = 'chk_nopollute_' . substr(md5($tbl . '.' . $col), 0, 16);
    /* ◆ **القيدُ القائمُ يُسقَط ويُعاد بناؤه**: النسخةُ الأولى كانت **عاطلةً**
         — استعملت `[\u0600-\u06FF]` وMariaDB لا تفهم `\uXXXX` فقرأتها صنفَ
         محارفَ حرفيًّا، فمرَّ الملوَّثُ بلا اعتراض. وكشفه **اختبارٌ سلبيٌّ**
         لا قراءةُ الشيفرة: أُدرج صفٌّ ملوَّثٌ عمدًا فمرَّ. ولولاه لأُعلن
         «10 قيودٍ» وهي عشرةُ أصفار. */
    $q = $conn->query("SELECT 1 FROM information_schema.CHECK_CONSTRAINTS
                        WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='{$tbl}'
                          AND CONSTRAINT_NAME='{$cname}'");
    if ($q && $q->num_rows) { $conn->query("ALTER TABLE `{$tbl}` DROP CONSTRAINT `{$cname}`"); $already++; }

    /* ◆ **المرساةُ طابعٌ زمنيٌّ لا مفتاحٌ متزايد**: MariaDB ترفض ذكرَ عمودِ
         `AUTO_INCREMENT` داخلَ `CHECK` صراحةً — جُرِّب فرُفض في الأربعةَ عشرَ
         كلِّها. والطابعُ الزمنيُّ يؤدّي الغرضَ نفسَه: الصفُّ القائمُ أقدمُ من
         لحظةِ الإضافةِ فيُعفى، والوارِدُ بعدَها يخضع. */
    $tsCol = null;
    /* ◆ **أسماءُ المرساةِ تُكتشَف لا تُفترَض**: أربعةُ جداولٍ سقطت في أولِ
         تشغيلٍ بـ«لا عمودَ زمنٍ» وفيها `decided_at` و`fired_at` و`routed_at`.
         فقائمةٌ مكتوبةٌ تُسقط ما لم يخطر ببالِ كاتبِها — والاكتشافُ من
         `information_schema` يأخذ **أقدمَ عمودٍ زمنيٍّ في الجدول**. */
    $tsCands = array();
    $tq = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
                          WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$tbl}'
                            AND DATA_TYPE IN ('datetime','timestamp')
                          ORDER BY ORDINAL_POSITION");
    while ($tq && ($tx = $tq->fetch_assoc())) { $tsCands[] = $tx['COLUMN_NAME']; }
    foreach ($tsCands as $cand) {
        $cq = $conn->query("SELECT 1 FROM information_schema.COLUMNS
                             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$tbl}'
                               AND COLUMN_NAME='{$cand}'");
        if ($cq && $cq->num_rows) { $tsCol = $cand; break; }
    }
    if ($tsCol === null) { $skipped[] = "{$tbl}: لا عمودَ زمنٍ — لا مرساةَ فلا قيد"; continue; }
    $q = $conn->query("SELECT COALESCE(MAX(`{$tsCol}`), NOW()) m FROM `{$tbl}`");
    $anchor = $q ? (string) $q->fetch_assoc()['m'] : null;
    if ($anchor === null || $anchor === '') { $skipped[] = "{$tbl}: مرساةٌ فارغة"; continue; }
    $ae = $conn->real_escape_string($anchor);

    $sql = "ALTER TABLE `{$tbl}` ADD CONSTRAINT `{$cname}` CHECK (
                `{$tsCol}` <= '{$ae}'
                OR `{$col}` IS NULL
                OR NOT (`{$col}` LIKE '% %'
                        AND LENGTH(`{$col}`) > CHAR_LENGTH(`{$col}`)))";
    if ($conn->query($sql)) { $made++; }
    else { $skipped[] = "{$tbl}.{$col}: " . $conn->error; }
}

echo "════ الخطوةُ ⑤ — قيدٌ بمرساةٍ يمنع العودة ════\n";
echo "  أعمدةٌ في مجالاتٍ محمية: " . count($targets) . " · قيودٌ أُضيفت: {$made} · قائمةٌ سلفًا: {$already}\n";
foreach ($skipped as $s) { echo "  ⚠ {$s}\n"; }
$n = (int) $conn->query("SELECT COUNT(*) c FROM information_schema.CHECK_CONSTRAINTS
                          WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME LIKE 'chk_nopollute_%'")->fetch_assoc()['c'];
echo "  ▸ مجموعُ قيودِ المنع: {$n}\n";
echo "◆ القائمُ مُعفًى **بمفتاحِه** والجديدُ ملزَم — قيدٌ في القاعدةِ لا حارسٌ في الشيفرة\n";
