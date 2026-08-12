<?php
/**
 * tools/fix_person_names_in_catalogs.php — أسماءُ أشخاصٍ دخلت جداولَ القواميس
 * ═══════════════════════════════════════════════════════════════════════════
 * **النمطُ المقيسُ ثلاثَ مراتٍ في يومٍ واحد** (2026-08-12):
 *   · `job_titles` — أربعةُ صفوفٍ أسماءُ أشخاصٍ برموزٍ ذاتيةِ الإشارة (عُوملت).
 *   · `pay_models` — خمسةُ صفوفٍ `label_ar` فيها أسماءُ أشخاصٍ (`PAY_-000NN`).
 *   · `ticket_types` — صفٌّ `name` فيه «معتصم بابكر الريح» (`TICK-00020`)
 *     — **وهو الاسمُ نفسُه** الذي وُجد في `pay_models#20`.
 *
 * فمستوردُ UAT كتب **قائمةَ أشخاصٍ واحدةً** في أكثرِ من قاموس. وقاموسٌ ملوَّثٌ
 * يُفسد كلَّ منسدلةٍ تقرؤه، ويجعل حكمًا مثل «لا نوعَ بلا مسار» أو «نموذجٌ من
 * خارج الكتالوج» بلا معنى.
 *
 * **ما تفعله هذه الأداة**: تقيس فقط (ولا تكتب) — تُطابق قيمَ أعمدةِ الأسماءِ في
 * جداولِ القواميسِ بأسماءِ `employees` **مطابقةً تامّةً**، وتُعلن ما وجدت بجدولِه
 * وصفِّه ورمزِه وهل يستعمله أحد. فالقرارُ في كلِّ جدولٍ يختلف (تعطيلٌ · حجزٌ
 * بوسم · تركٌ معلَنٌ) ولا يُتَّخذ جملةً.
 *
 * التشغيل: php tools/fix_person_names_in_catalogs.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__) . '/tools/fix_lib.php';

$db = fix_db();
$db->set_charset('utf8mb4');

/* أسماءُ الأشخاصِ المرجعيةُ — من سجلِّ الموظفين */
$people = array();
$r = $db->query("SELECT DISTINCT TRIM(name) n FROM employees WHERE TRIM(COALESCE(name,'')) <> ''");
while ($r && ($x = $r->fetch_assoc())) { $people[$x['n']] = true; }
echo '══ أسماءُ الموظفين المرجعية: ' . count($people) . "\n\n";
if (empty($people)) { echo "لا أسماءَ مرجعيةً — لا قياس\n"; exit(0); }

/* جداولُ القواميسِ وعمودُ الاسمِ في كلٍّ — تُقاس من المخطَّطِ لا تُفترض */
$cands = array(
    'job_titles'          => array('name', 'title_code', 'active'),
    'pay_models'          => array('label_ar', 'code', 'is_active'),
    'ticket_types'        => array('name', 'code', 'active'),
    'positions'           => array('name', null, 'is_active'),
    'sla_policies'        => array('name', 'code', null),
    'ticket_sla_policies' => array('name', 'code', null),
    'employee_roles'      => array('name', 'code', null),
    'pay_components'      => array('name', 'code', null),
);

$found = array();
foreach ($cands as $tbl => $cfg) {
    list($nameCol, $codeCol, $activeCol) = $cfg;
    $has = $db->query("SELECT COUNT(*) c FROM information_schema.tables
                        WHERE table_schema = DATABASE() AND table_name = '{$tbl}'");
    if (!$has || (int) $has->fetch_row()[0] === 0) { continue; }
    $hasCol = $db->query("SELECT COUNT(*) c FROM information_schema.columns
                           WHERE table_schema = DATABASE() AND table_name = '{$tbl}'
                             AND column_name = '{$nameCol}'");
    if (!$hasCol || (int) $hasCol->fetch_row()[0] === 0) { continue; }

    $sel = "`{$nameCol}` AS nm";
    if ($codeCol !== null) { $sel .= ", `{$codeCol}` AS cd"; }
    if ($activeCol !== null) { $sel .= ", `{$activeCol}` AS act"; }
    $pk = $db->query("SELECT column_name FROM information_schema.columns
                       WHERE table_schema = DATABASE() AND table_name = '{$tbl}'
                         AND column_key = 'PRI' LIMIT 1");
    $pkCol = ($pk && ($p = $pk->fetch_row())) ? $p[0] : null;
    if ($pkCol === null) { continue; }

    $rows = array();
    $q = $db->query("SELECT `{$pkCol}` AS id, {$sel} FROM `{$tbl}`");
    while ($q && ($x = $q->fetch_assoc())) {
        $nm = trim((string) $x['nm']);
        if ($nm === '' || !isset($people[$nm])) { continue; }
        $rows[] = $x;
    }
    if (empty($rows)) { echo "  ✔ {$tbl}: نظيف\n"; continue; }

    echo "  ✘ {$tbl}: " . count($rows) . " صفًّا اسمُه اسمُ شخص\n";
    foreach ($rows as $x) {
        echo "        #{$x['id']} «{$x['nm']}»"
           . (isset($x['cd']) ? " · رمز={$x['cd']}" : '')
           . (isset($x['act']) ? ' · فعّال=' . $x['act'] : '') . "\n";
    }
    $found[$tbl] = count($rows);
}

echo "\n══ الحصيلة\n";
if (empty($found)) { echo "  لا قاموسَ ملوَّثًا — نظيف.\n"; exit(0); }
$tot = 0;
foreach ($found as $t => $n) { $tot += $n; echo "  {$t}: {$n}\n"; }
echo "  المجموع: {$tot} صفًّا في " . count($found) . " قاموسًا\n";
echo "\n○ قياسٌ فقط — القرارُ في كلِّ جدولٍ يختلف ولا يُتَّخذ جملةً.\n";
exit(0);
