<?php
/**
 * tools/uxui_pollution_scan.php — مسحةُ التلوثِ · **جردُ قراءةٍ فقط**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ قرارُ المالك (ثامنًا ②): «مسحةُ التلوث: UAT · TEST · SEED · DEMO · SAMPLE ·
 *   والملاحظاتُ المزروعةُ داخلَ حقولِ التعدادِ والرموز. **والجولةُ الأولى جردُ
 *   قراءةٍ فقط بلا أيِّ كتابة** · ثم تصنيفٌ ثلاثيّ: إنتاجٌ مشروعٌ · تلوثُ
 *   اختبارٍ · مشتبَه · ثم حجرٌ أو إصلاح. **ولا يتحول البحثُ الشاملُ إلى منظّفٍ
 *   آليٍّ واسع**».
 *
 * ◆ ولذلك هذه الأداةُ **لا تكتب في بياناتٍ أبدًا**: لا UPDATE ولا DELETE ولا
 *   NULL. تقرأ وتُحصي وتُسجّل النتيجةَ في `gov_pollution_findings` (سجلُّ جردٍ
 *   لا بيانات) بحكمٍ افتراضيٍّ `UNCLASSIFIED` — فالحكمُ بشريٌّ لاحقًا.
 *
 * ◆ وتمييزٌ جوهريٌّ في التصنيف: **خانةُ الرمزِ/التعدادِ** غيرُ **الحقلِ النصيِّ
 *   المشروع**. فـ«UAT-2026» في `notes` قد تكون ملاحظةً حقيقيةً من اختبارِ قبولٍ
 *   جرى فعلًا، وهي في `maint_type` عيبٌ قطعًا. فتُوسَم رتبةُ العمودِ ليُحكَم
 *   عليها بعلمٍ لا بالكلمةِ وحدَها.
 *
 * التشغيل:
 *   php tools/uxui_pollution_scan.php              الجردُ وتقريرُه (لا كتابةَ بيانات)
 *   php tools/uxui_pollution_scan.php --save       يسجّل الجردَ في سجلِّ الجرد
 *   php tools/uxui_pollution_scan.php --md=<path>  تقريرُ Markdown
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال\n"); }
$conn->set_charset('utf8mb4');
$args = array();
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z]+)(?:=(.*))?$/', $a, $m)) { $args[$m[1]] = isset($m[2]) ? $m[2] : '1'; }
}

/* ── العلاماتُ بنصِّ القرار ── */
$MARKERS = array(
    'UAT'    => 'UAT',
    'TEST'   => 'TEST',
    'SEED'   => 'SEED',
    'DEMO'   => 'DEMO',
    'SAMPLE' => 'SAMPLE',
);
/* رتبةُ العمود: خانةُ رمزٍ/تعدادٍ لا تحتمل جملةً بشرية */
function pol_role($colName, $dataType, $colType) {
    $c = strtolower($colName);
    if (strpos($colType, 'enum') === 0) { return 'تعداد'; }
    if (preg_match('/(^|_)(code|type|kind|status|state|priority|source|category|level|mode|flag)$/', $c)) { return 'خانةُ رمز'; }
    if (preg_match('/(^|_)(note|notes|remark|remarks|comment|memo|description|desc|reason|justification)$/', $c)) { return 'حقلٌ نصيٌّ مشروع'; }
    if (preg_match('/(^|_)(name|title|label|subject)$/', $c)) { return 'اسمٌ ظاهر'; }
    return 'نصٌّ عام';
}

/* ── الأعمدةُ النصيةُ كلُّها — والجداولُ الحوكميةُ للجردِ مستثناة ── */
$SKIP_TABLES = array('gov_pollution_findings', 'uat_field_quarantine', 'approval_rules_quarantine',
                     'gov_policy_changes', 'gov_cap_proposals', 'nav_pending_closure');
$cols = array();
$q = $conn->query("SELECT TABLE_NAME t, COLUMN_NAME c, DATA_TYPE dt, COLUMN_TYPE ct
                     FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND DATA_TYPE IN ('varchar','char','text','tinytext','mediumtext','longtext','enum')
                    ORDER BY TABLE_NAME, ORDINAL_POSITION");
while ($q && ($x = $q->fetch_assoc())) {
    if (in_array($x['t'], $SKIP_TABLES, true)) { continue; }
    $cols[] = $x;
}

/* ── المسحُ: قراءةٌ خالصة ── */
$findings = array();
$scanned = 0; $tablesSeen = array();
foreach ($cols as $col) {
    $t = $col['t']; $c = $col['c'];
    $tablesSeen[$t] = true;
    $scanned++;
    foreach ($MARKERS as $key => $needle) {
        $sql = "SELECT COUNT(*) n, MAX(`{$c}`) sample FROM `{$t}` WHERE `{$c}` LIKE '%{$needle}%'";
        $r = @$conn->query($sql);
        if (!$r) { continue; }
        $row = $r->fetch_assoc();
        $n = (int) $row['n'];
        if ($n === 0) { continue; }
        $findings[] = array(
            'table' => $t, 'column' => $c, 'marker' => $key, 'hits' => $n,
            'sample' => mb_substr((string) $row['sample'], 0, 120),
            'role' => pol_role($c, $col['dt'], $col['ct']),
        );
    }
}

/* ── التقرير ── */
usort($findings, function ($a, $b) {
    $rank = array('خانةُ رمز' => 0, 'تعداد' => 1, 'اسمٌ ظاهر' => 2, 'نصٌّ عام' => 3, 'حقلٌ نصيٌّ مشروع' => 4);
    $ra = $rank[$a['role']] ?? 5; $rb = $rank[$b['role']] ?? 5;
    return ($ra - $rb) ?: ($b['hits'] - $a['hits']);
});
$byRole = array();
foreach ($findings as $f) { $byRole[$f['role']] = ($byRole[$f['role']] ?? 0) + 1; }

echo "════ مسحةُ التلوثِ — جردُ قراءةٍ فقط (صفرُ كتابةٍ في البيانات) ════\n";
echo "  أعمدةٌ نصيةٌ مفحوصة: {$scanned} · في " . count($tablesSeen) . " جدولًا\n";
echo "  مواضعُ إصابةٍ موجودة: " . count($findings) . "\n";
foreach ($byRole as $role => $n) { printf("    · %-22s %d\n", $role, $n); }
echo "\n  ◆ الأخطرُ أولًا (خانةُ رمزٍ أو تعدادٍ تحمل نصَّ ملاحظة):\n";
$shown = 0;
foreach ($findings as $f) {
    if (!in_array($f['role'], array('خانةُ رمز', 'تعداد', 'اسمٌ ظاهر'), true)) { continue; }
    printf("    %-26s.%-22s [%s] ×%-4d «%s»\n", $f['table'], $f['column'], $f['marker'], $f['hits'], mb_substr($f['sample'], 0, 48));
    if (++$shown >= 20) { echo "    … والباقي في السجل\n"; break; }
}
if ($shown === 0) { echo "    (لا شيء)\n"; }

echo "\n  ◆ حقولٌ نصيةٌ مشروعةٌ فيها العلامة — **لا يُحكم عليها بالكلمةِ وحدَها**:\n";
$shown2 = 0;
foreach ($findings as $f) {
    if ($f['role'] !== 'حقلٌ نصيٌّ مشروع') { continue; }
    printf("    %-26s.%-22s [%s] ×%d\n", $f['table'], $f['column'], $f['marker'], $f['hits']);
    if (++$shown2 >= 8) { echo "    …\n"; break; }
}
if ($shown2 === 0) { echo "    (لا شيء)\n"; }

if (!empty($args['save'])) {
    $ins = $conn->prepare("INSERT INTO gov_pollution_findings (table_name, column_name, marker, hits, sample_value, column_role)
                           VALUES (?,?,?,?,?,?)
                           ON DUPLICATE KEY UPDATE hits=VALUES(hits), sample_value=VALUES(sample_value),
                                                   column_role=VALUES(column_role), scanned_at=NOW()");
    $n = 0;
    foreach ($findings as $f) {
        $ins->bind_param('sssiss', $f['table'], $f['column'], $f['marker'], $f['hits'], $f['sample'], $f['role']);
        if ($ins->execute()) { $n++; }
    }
    echo "\n  ▸ سُجِّل في سجلِّ الجرد: {$n} موضعًا · حكمُها UNCLASSIFIED حتى التصنيفِ البشريّ\n";
} else {
    echo "\n  ▸ جردٌ بلا تسجيل — أضِفْ --save لحفظِه في gov_pollution_findings\n";
}

if (!empty($args['md'])) {
    $L = array('# مسحةُ التلوثِ — جردُ قراءةٍ فقط', '',
        '· ' . date('Y-m-d H:i') . ' · أمرُ الإنتاج: `php tools/uxui_pollution_scan.php --md=<الملف>`',
        '· **لا كتابةَ في بياناتٍ في هذه الجولة** — بنصِّ قرارِ المالك (ثامنًا ②).', '',
        '| الجدول | العمود | رتبةُ العمود | العلامة | إصابات | عيّنة |', '|---|---|---|---|---|---|');
    foreach ($findings as $f) {
        $L[] = '| `' . $f['table'] . '` | `' . $f['column'] . '` | ' . $f['role'] . ' | ' . $f['marker']
             . ' | ' . $f['hits'] . ' | ' . str_replace('|', '·', mb_substr($f['sample'], 0, 40)) . ' |';
    }
    $L[] = '';
    $L[] = '**الحصيلة:** ' . count($findings) . ' موضعًا في ' . count($tablesSeen) . ' جدولًا · أعمدةٌ مفحوصة: ' . $scanned;
    $L[] = '';
    $L[] = '◆ التصنيفُ الثلاثيُّ (إنتاجٌ مشروع · تلوثُ اختبار · مشتبَه) **لم يُجرَ آليًّا** — والافتراضُ `UNCLASSIFIED`.';
    file_put_contents($args['md'], implode("\n", $L) . "\n");
    echo "  MD ⇐ {$args['md']}\n";
}
echo "\n✔ انتهى الجردُ — وصفرُ عبارةِ كتابةٍ في بياناتِ العمل\n";
