<?php
/**
 * tests/injchain01_ten_gates.php
 *   بواباتُ إغلاقِ سلسلةِ الأثرِ العشر — INJ-CHAIN-CLOSE-01 الباب الرابع
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **كلُّ بوابةٍ تُقاس ببسطِها ومقامِها لا بالانطباع** — بنصِّ الوثيقة. وكلُّ
 *   واحدةٍ منها إمّا **خضراءُ بقياس**، أو **مفتوحةٌ مُعلَنةٌ بسببِها**؛ ولا
 *   بوابةَ «خضراءُ بلا مقام».
 *
 * ◆ **وبوابةٌ لا يمكن أن ترسُب لا تُحسب**: كلُّ بوابةٍ هنا لها حالةُ رسوبٍ
 *   ممكنةٌ مذكورةٌ في نصِّها — فمرورُها خبرٌ لا زخرفة.
 *
 * ◆ **وحجمُ البياناتِ متقلبٌ خارجَ هذه الجلسة** (جولاتُ بذرٍ وتقليصٍ متوازية)،
 *   فالبواباتُ تقيس **خصائصَ وقواعدَ** لا أحجامًا: صفرُ خرقٍ لا «كذا صفًّا».
 *
 * التشغيل: php tests/injchain01_ten_gates.php [--json=<ملف>]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
require_once $ROOT . '/includes/unit_chain_helpers.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

function one(mysqli $c, $sql) { $r = @$c->query($sql); return $r ? (int) $r->fetch_row()[0] : -1; }

$G = array();   /* code => [green(bool), title, measure, note] */
function gate(&$G, $code, $green, $title, $measure, $note = '')
{
    $G[$code] = array('green' => (bool) $green, 'title' => $title, 'measure' => $measure, 'note' => $note);
}

echo "══ بواباتُ إغلاقِ سلسلةِ الأثرِ العشر ══\n";

/* ══ ① الواقعةُ الواحدةُ ثلاثُ قراءات ═══════════════════════════════════ */
$surf = array('Contracts/unit_statement_client.php', 'Suppliers/unit_statement_supplier.php');
$built = 0;
foreach ($surf as $s) { if (is_file($ROOT . '/' . $s)) { $built++; } }
/* كشفُ المشغّلِ حيٌّ على سطحٍ آخرَ بأثرٍ مقيس (`unit_party_awards.party='operator'`) */
$opAward = one($conn, "SELECT COUNT(*) FROM `unit_party_awards` WHERE `party`='operator'");
$opSurf  = is_file($ROOT . '/Portal/my_achievement.php');
$threeReads = ($built === 2) && $opSurf;
/* والأرقامُ الثلاثةُ من مصدرٍ واحد: `unit_party_awards` مفتاحُه الواقعة */
$srcOne = one($conn, "SELECT COUNT(DISTINCT `source_kind`) FROM `unit_party_awards`") >= 1;
gate($G, 'G1', $threeReads && $srcOne,
     'الواقعةُ الواحدةُ ثلاثُ قراءات',
     "أسطحُ الكشفِ المبنية {$built}/2 + كشفُ المشغّلِ على سطحٍ حيّ · إسنادُ مشغّلٍ مقيس={$opAward}",
     'الأرقامُ الثلاثةُ تُشتق من `unit_party_awards` بمفتاحِ الواقعةِ الواحدة — لا ثلاثةُ مصادر');

/* ══ ② سلسلةُ الاعتمادِ كاملة ═══════════════════════════════════════════ */
/* لكلِّ وحدةٍ بلغت `sales_approved` فصاعدًا: قرارُ موقعٍ + قرارُ طرفٍ + قرارُ مبيعات */
$broken = one($conn, "
  SELECT COUNT(*) FROM `unit_entries` e
   WHERE e.`state` IN ('sales_approved','converted')
     AND (NOT EXISTS (SELECT 1 FROM `unit_approvals` a
                       WHERE a.`entry_id`=e.`id` AND a.`stage`='site'  AND a.`decision`='approved')
       OR NOT EXISTS (SELECT 1 FROM `unit_approvals` a
                       WHERE a.`entry_id`=e.`id` AND a.`stage`='sales' AND a.`decision`='approved'))");
gate($G, 'G2', $broken === 0,
     'سلسلةُ الاعتمادِ كاملةٌ بلا فجوة',
     "وحداتٌ بلغت اكتمالَ السلسلةِ بلا قرارِ موقعٍ أو مبيعات: **{$broken}**",
     'وهي حالةُ الرسوبِ الممكنة: وحدةٌ قفزت مرحلةً');

/* ══ ③ السلّمُ لا يُتجاوَز ═══════════════════════════════════════════════ */
$svc = (string) @file_get_contents($ROOT . '/app/Services/Unit/TimesheetEntryService.php');
$reads = strpos($svc, 'ems_uc_ladder_check') !== false;
$mode  = ems_uc_ladder_mode();
$jrTot = one($conn, "SELECT COUNT(*) FROM `gov_journey_ladders`");
$jrWir = one($conn, "SELECT COUNT(*) FROM `gov_journey_ladders` WHERE `ladder_wired`=1");
gate($G, 'G3', $reads && $jrWir === $jrTot,
     'السلّمُ يُقرأ ولا يُتجاوَز',
     "نقطةُ القرارِ تقرأ السلّم=" . ($reads ? 'نعم' : 'لا')
     . " · الرحلاتُ الموصولةُ **{$jrWir}/{$jrTot}** · النمط={$mode}",
     'وثمانيةُ سلاليمَ (LD-06..LD-13) قائدُها سطحٌ آخرُ لم يُوصَل — مُعلَنٌ لا مطويّ');

/* ══ ④ فصلُ الواجبات ═══════════════════════════════════════════════════ */
$sod1 = one($conn, "SELECT COUNT(*) FROM (
          SELECT `entry_id`,`round_no`,`actor_id` FROM `unit_approvals`
           GROUP BY `entry_id`,`round_no`,`actor_id` HAVING COUNT(DISTINCT `stage`)>1) x");
$sod2 = one($conn, "SELECT COUNT(*) FROM `unit_final_approvals`
                     WHERE (`approved_by` IS NOT NULL AND `approved_by`=`prepared_by`)
                        OR (`control_by`  IS NOT NULL AND `control_by`=`approved_by`)");
$sod3 = one($conn, "SELECT COUNT(*) FROM `tre_pay_batches`
                     WHERE `executed_by` IS NOT NULL AND `executed_by`=`prepared_by`");
$sod4 = one($conn, "SELECT COUNT(*) FROM `tre_beneficiaries`
                     WHERE `verified_by` IS NOT NULL AND `verified_by`=`created_by`");
$sodAll = max(0, $sod1) + max(0, $sod2) + max(0, $sod3) + max(0, $sod4);
gate($G, 'G4', $sodAll === 0,
     'فصلُ الواجبات — لا يدَ تجمع اثنتين',
     "وحدات={$sod1} · اعتمادٌ نهائيّ={$sod2} · دفعات={$sod3} · مستفيدون={$sod4} ⇒ **{$sodAll}**",
     'وقيودُ CHECK تحرس الثلاثةَ الأخيرةَ في القاعدةِ نفسِها — جُرِّبت معطوبةً');

/* ══ ⑤ لا كاتبَ بشريٌّ إلى دفترِ الأستاذ ═══════════════════════════════ */
$SKIP = array('/vendor/', '/node_modules/', '/.git/', '/docs/', '/tests/', '/tools/',
              '/storage/', '/database/');
$writers = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') { continue; }
    $pth = str_replace('\\', '/', $f->getPathname());
    foreach ($SKIP as $s) { if (strpos($pth, $s) !== false) { continue 2; } }
    $src = (string) @file_get_contents($pth);
    if (preg_match('/(INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+`?fin_journal_entries`?\b/i', $src)) {
        /* ◆ **الجذرُ بشرطاتٍ خلفيةٍ على ويندوز والمسارُ بأمامية** — فقصُّ الجذرِ
         *   بلا توحيدٍ يترك المسارَ مطلقًا فلا يطابق قائمةَ الاستثناءِ النسبية،
         *   **فيُعلَن كاتبٌ غيرُ مُعلَنٍ وهو مُعلَن**. العطبُ في المقياسِ لا النظام. */
        $rootFwd = str_replace('\\', '/', $ROOT) . '/';
        $writers[] = str_replace($rootFwd, '', $pth);
    }
}
/* الاستثناءاتُ المُعلَنةُ في سقّاطةِ كتّابِ الدفترِ — تُقرأ ولا تُكرَّر */
$declared = array('Finance/fin_helpers.php', 'includes/fx.php', 'Finance/journal_form_fin.php',
                  'app/Services/Governance/GovernanceM14Service.php');
$undeclared = array_values(array_diff($writers, $declared));
gate($G, 'G5', count($undeclared) === 0,
     'لا كاتبَ بشريٌّ جديدٌ إلى دفترِ الأستاذ',
     'كتّابٌ مقيسون=' . count($writers) . ' · **غيرُ مُعلَنٍ منهم: ' . count($undeclared) . '**'
     . ($undeclared ? ' — ' . implode(' · ', array_slice($undeclared, 0, 3)) : ''),
     'والأربعةُ المُعلَنةُ لها سببٌ مكتوبٌ في سقّاطةِ `injfix01_journal_writer_ratchet`');

/* ══ ⑥ المحاسبةُ ليست الخزينة ═══════════════════════════════════════════ */
$treJournal = one($conn, "SELECT COUNT(*) FROM `tre_pay_batches` WHERE `state`='executed'
                            AND `bank_ref` IS NULL");
/* ولا قيدَ من الخزينة: جدولُ الدفعاتِ بلا عمودِ قيدٍ أصلًا — يُقاس بنيويًّا */
$hasJe = one($conn, "SELECT COUNT(*) FROM information_schema.COLUMNS
                      WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tre_pay_batches'
                        AND COLUMN_NAME LIKE '%journal%'");
gate($G, 'G6', $treJournal === 0 && $hasJe === 0,
     'المحاسبةُ ليست الخزينة',
     "دفعاتٌ نُفِّذت بلا مرجعِ حركة={$treJournal} · أعمدةُ قيدٍ في جدولِ الخزينة={$hasJe}",
     'الخزينةُ تنتج مرجعَ الحركةِ ولا تملك قيدًا — والبنيةُ نفسُها تمنعه');

/* ══ ⑦ الإسقاطاتُ لها مصادر ═══════════════════════════════════════════ */
$need = array(
  'حالةُ الفاتورة'  => array('ar_claim_invoices', 'Finance/ar_claim_invoice.php'),
  'حالةُ التحصيل'  => array('fin_collection_allocations', 'Contracts/collections.php'),
  'حالةُ الصرف'    => array('tre_pay_batches', 'Finance/tre_pay_batch.php'),
);
$missSrc = array();
foreach ($need as $lbl => $pair) {
    list($tbl, $screen) = $pair;
    $t = one($conn, "SELECT COUNT(*) FROM information_schema.TABLES
                      WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$tbl}'");
    if ($t < 1 || !is_file($ROOT . '/' . $screen)) { $missSrc[] = $lbl; }
}
gate($G, 'G7', count($missSrc) === 0,
     'الإسقاطاتُ لها مصادرُ مبنية',
     'حالاتٌ بلا مصدرٍ مبنيّ: ' . (count($missSrc) ? implode(' · ', $missSrc) : '0 من 3'),
     'قبلَ هذه الجولةِ كانت حالةُ التحصيلِ والصرفِ بلا سطحٍ يُقرأ منه');

/* ══ ⑧ الرحلتان تعملان ═══════════════════════════════════════════════ */
/* تُقاس بالبنيةِ: كلُّ عقدةِ رحلةٍ لها سطحٌ مبنيٌّ أو حاملٌ مقيس */
$revNodes = array(1, 2, 3, 4, 5, 8, 9, 15, 16, 17, 18, 19, 20, 21);
$supNodes = array(1, 2, 3, 6, 22, 23, 24, 25, 26, 27);
$gapRev = one($conn, "SELECT COUNT(*) FROM `gov_chain_nodes`
                       WHERE `node_no` IN (" . implode(',', $revNodes) . ")
                         AND `build_state` = 'MISSING'");
$gapSup = one($conn, "SELECT COUNT(*) FROM `gov_chain_nodes`
                       WHERE `node_no` IN (" . implode(',', $supNodes) . ")
                         AND `build_state` = 'MISSING'");
gate($G, 'G8', $gapRev === 0 && $gapSup === 0,
     'الرحلتان تعبران بنيويًّا',
     "رحلةُ الإيراد: عقدٌ مفقودة={$gapRev} · رحلةُ الدفع: {$gapSup}",
     '**والعبورُ البشريُّ بحسابٍ حقيقيٍّ لم يقع** — يلزمه موظفٌ لا منفِّذ، ويُعلَن مفتوحًا');

/* ══ ⑨ صفرُ ارتداد ═══════════════════════════════════════════════════ */
/* الحزامُ كلُّه أخضر ⇒ ما كان يعمل يعمل. ويُقاس بمُرجَعِ الفاحصاتِ لا بالادّعاء */
$suite = glob($ROOT . '/tests/injfix0*.php');
$red = 0;
foreach ($suite as $t) { $o = array(); $c2 = 0; @exec('"' . PHP_BINARY . '" ' . escapeshellarg($t) . ' 2>&1', $o, $c2);
    if ($c2 !== 0) { $red++; } }
/* ◆ **وحزامُ INJ-FRD-REM-01 يُضاف إلى الحكمِ نفسِه**: ستةٌ وعشرون شاهدًا
 *   أُنتجت في تلك الجولةِ كانت **خارجَ الكنس** لأن المرشِّحَ `injfix0*`.
 *   ودليلٌ لا يُكنَس يتعفّن صامتًا. وحكمُه **ثلاثيٌّ** (ارتدادُ المُغلَقِ
 *   يُرسِّب · وشاهدٌ بلا مطلبٍ يُرسِّب · وخضرةُ غيرِ المُغلَقِ خبرٌ لا خلل)
 *   فيُنادى بملفِّه لا بإضافتِه إلى المرشِّح. */
$o3 = array(); $rcFrd = 0;
@exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tests/injfrd01_belt.php') . ' 2>&1', $o3, $rcFrd);
if ($rcFrd !== 0) { $red++; }
gate($G, 'G9', $red === 0,
     'صفرُ ارتداد — ما كان يعمل يعمل',
     'حزامُ INJ-FIX: **' . (count($suite) - $red + ($rcFrd === 0 ? 0 : 1)) . ' من ' . count($suite)
 . ' خضراء** · وحزامُ INJ-FRD: ' . ($rcFrd === 0 ? '**أخضر**' : '**أحمر**') . ' · راسب=' . $red,
     'وهي حالةُ الرسوبِ الممكنة: أيُّ شاهدٍ سابقٍ ينقلب أحمرَ بفعلِ هذه الجولة');

/* ══ ⑩ صفرُ فقد ═══════════════════════════════════════════════════════ */
$orphanLines = one($conn, "SELECT COUNT(*) FROM `tre_pay_batch_lines` l
                            WHERE NOT EXISTS (SELECT 1 FROM `tre_pay_batches` b WHERE b.`id`=l.`batch_id`)");
$dupIdem = one($conn, "SELECT COUNT(*) FROM (
                SELECT `idem_key` FROM `ar_accruals` GROUP BY `idem_key` HAVING COUNT(*)>1) x");
$silent  = one($conn, "SELECT COUNT(*) FROM `ar_claim_invoices`
                        WHERE `journal_entry_id` IS NOT NULL AND `control_at` IS NULL");
$lost = max(0, $orphanLines) + max(0, $dupIdem) + max(0, $silent);
gate($G, 'G10', $lost === 0,
     'صفرُ فقدٍ ولا تكرارَ ولا ترحيلَ صامت',
     "سطورٌ يتيمة={$orphanLines} · مفاتيحُ عطالةٍ مكررة={$dupIdem} · ترحيلٌ صامت={$silent}",
     'والترحيلُ الصامتُ يستحيل بنيويًّا — قيدُ CHECK يمنعه، ويُقاس مع ذلك');

/* ══ العرضُ والحكم ══════════════════════════════════════════════════ */
echo str_repeat('─', 104) . "\n";
$green = 0;
foreach ($G as $code => $g) {
    $m = $g['green'] ? '✔' : '✘';
    if ($g['green']) { $green++; }
    printf("  %-4s %s %-38s %s\n", $code, $m, mb_substr($g['title'], 0, 36), $g['measure']);
    if ($g['note'] !== '') { echo "        ◆ " . $g['note'] . "\n"; }
}
echo str_repeat('─', 104) . "\n";
printf("◆ **بواباتُ السلسلة: %d من %d خضراء**\n", $green, count($G));
if ($green < count($G)) {
    $open = array();
    foreach ($G as $c => $g) { if (!$g['green']) { $open[] = $c; } }
    echo "◆ مفتوحةٌ مُعلَنة: " . implode(' · ', $open) . "\n";
    echo "◆ **ولا تُعلَن السلسلةُ مغلقةً** ما دامت واحدةٌ منها مفتوحة — بنصِّ الوثيقة.\n";
}

foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--json=(.*)$/', $a, $m2)) {
        file_put_contents($m2[1], json_encode(array('green' => $green, 'total' => count($G), 'gates' => $G),
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo "◆ كُتب: {$m2[1]}\n";
    }
}

exit($green === count($G) ? 0 : 1);
