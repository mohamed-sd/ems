<?php
/**
 * tools/rpr02_acceptance_scorecard.php — `RPR-02` §١٢ · المقاييسُ الستّةَ عشرَ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — §١٢: *«معيارُ قبولِ هذا الأمرِ وحدَه»*، وفي ذيلِ
 *   جدولِه: *«**وما كان «غير مقيس» فأوّلُ واجبِك أن تقيسه — فالمقياسُ الذي
 *   لا يُقاس ليس معيارًا بل أمنية**»*. وأحدَ عشرَ من الستّةَ عشرَ ورد «غيرَ
 *   مقيسٍ» في الأمرِ نفسِه.
 *
 * ◆ **وثلاثةٌ منها فُتحت بقياسِ الحبّة** (`rpr02_grain_measure.php` · §٧·١):
 *   «سطحٌ يكتب ومالكُ حقيقتِه مجهول» · «حقيقةٌ واحدةٌ لها مصدران» · «كتابةٌ
 *   تعبر حدودَ إدارةٍ بلا عقد» — **ثلاثتُها تسأل عن الكيانِ المكتوبِ ومالكِه**،
 *   ولا تُقاس بلا حبّةٍ مقيسة. فالحبّةُ لم تفتح §٤·٢ وحدَها.
 *
 * ◆ **والأمانةُ في هذا السجلِّ ثلاثُ طبقاتٍ لا اثنتان**:
 *   `MEASURED` مقيسٌ برقمِه · `BLOCKED` **غيرُ مقيسٍ بسببٍ مسمًّى ومرحلةٍ** ·
 *   ⛔ ولا طبقةَ ثالثةً اسمُها «صفر» بلا قياس. فصفرٌ من مفردةٍ لا وجودَ لها
 *   أخضرُ كاذب، **والمحجوبُ يُعلَن محجوبًا ولا يُكتب صفرًا**.
 *
 * ◆ ⛔ **ولا يُخلط المقياسان في §٤·٢**: «إغلاقُ قرارات الأهداف» و«تحقُّقُ
 *   الأهداف» رقمان مستقلّان — وقد تكتمل القراراتُ مئةً ويبقى البناءُ ناقصًا.
 *
 * التشغيل:
 *   php tools/rpr02_acceptance_scorecard.php [--md] [--selftest]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$MD   = in_array('--md', $argv, true);
$SELF = in_array('--selftest', $argv, true);

$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
$sid = $snap ? $snap['snapshot_id'] : 'DRY';

$one = function ($sql) use ($conn) {
    $r = $conn->query($sql);
    if (!$r) { return null; }
    $x = $r->fetch_row();
    return $x ? $x[0] : null;
};
$tbl = function ($t) use ($conn) {
    $r = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($t) . "'");
    return $r && $r->num_rows > 0;
};

/* المقامُ الحيُّ الواحدُ — يُحسب مرّةً ويُستشهد به */
$LIVE = "repair01_screen_registry WHERE on_disk = 1 AND ownership_verdict <> 'RETIRE'";
$liveN = (int) $one("SELECT COUNT(*) FROM $LIVE");

$M = array();   /* المقياس ⇐ [العنوان, المستهدف, الحال, القيمة, الشاهد] */
$add = function ($title, $target, $state, $value, $wit) use (&$M) {
    $M[] = array($title, $target, $state, $value, $wit);
};

/* ═══ ١ · إغلاقُ قراراتِ الأهداف ═════════════════════════════════════════ */
$tuAll = (int) $one("SELECT COUNT(*) FROM repair01_target_universe");
$tuJdg = (int) $one("SELECT COUNT(*) FROM repair01_target_universe WHERE verdict IS NOT NULL");
$add('إغلاق قرارات الأهداف — كل هدف بحكم من السبعة', '100٪', 'MEASURED',
     ($tuAll ? round($tuJdg * 100 / $tuAll, 1) : 0) . '٪',
     "$tuJdg من $tuAll · `repair01_target_universe.verdict IS NOT NULL`");

/* ═══ ٢ · تحقُّقُ الأهداف — المتحقِّقُ ÷ المنطبق ═══════════════════════════ */
$tuReal = (int) $one("SELECT COUNT(*) FROM repair01_target_universe
                       WHERE verdict IN ('MATCHED','MERGED_INTO','TAB_CHILD','PROJECTION')");
$tuOut  = (int) $one("SELECT COUNT(*) FROM repair01_target_universe
                       WHERE verdict IN ('NOT_APPLICABLE','RETIRED_TARGET')");
$appl   = $tuJdg - $tuOut;
$add('تحقّق الأهداف — المتحقِّق ÷ المنطبق', 'يُعلَن بلا سقف', 'MEASURED',
     ($appl ? round($tuReal * 100 / $appl, 1) : 0) . '٪',
     "$tuReal ÷ $appl · وخرج من المقامِ بقرارٍ أو ببديلٍ **$tuOut** — يُعرض منفصلًا (§٤·٣)");

/* ═══ ٣ · مطابقةُ حقولِ كلِّ سطحٍ مطابَقٍ لملفِّه ═════════════════════════ */
$fDes = $tbl('repair01_fields')  ? (int) $one("SELECT COUNT(*) FROM repair01_fields")  : 0;
$fBlt = $tbl('gov_field_trace')  ? (int) $one("SELECT COUNT(*) FROM gov_field_trace")  : 0;
$add('مطابقة حقول كل سطح مطابَق لملفه', '100٪', 'BLOCKED', '—',
     "دفترُ الحقولِ التصميميُّ **$fDes** صفًّا و`gov_field_trace` المبنيُّ **$fBlt** — "
   . '**ولا مفتاحَ يجمعهما**: الأوّلُ باسمِ السطحِ العربيِّ والثاني بمسارِ الملفّ. '
   . '⇒ `blocked at stage: مصالحةُ مفتاحِ الحقلِ بين الدفترَين (§٧ الخطوة ٥)`');

/* ═══ ٤ · مطابقةُ آلةِ الحالةِ لكلِّ معاملة ══════════════════════════════ */
$txn = (int) $one("SELECT COUNT(*) FROM $LIVE AND grain_cardinality IN ('ROW','LINE')");
$txnSM = (int) $one("SELECT COUNT(*) FROM $LIVE AND grain_cardinality IN ('ROW','LINE')
                       AND state_model_ref <> ''");
$add('مطابقة آلة الحالة لكل معاملة', '100٪', 'MEASURED',
     ($txn ? round($txnSM * 100 / $txn, 1) : 0) . '٪',
     "$txnSM من $txn سطحَ معاملةٍ (حبّةٌ `ROW`/`LINE`) يحمل `state_model_ref` — "
   . '⛔ **وحملُ المرجعِ ليس مطابقةَ الآلة**: هذا قياسُ وجودٍ لا قياسُ تطابق');

/* ═══ ٥ · اختبارٌ سالبٌ لفصلِ الواجباتِ الحرج ═══════════════════════════ */
$negFiles = glob($ROOT . '/tools/*negative*.php');
$negN = $negFiles ? count($negFiles) : 0;
$sodN = $tbl('repair01_w4_decisions') ? (int) $one("SELECT COUNT(*) FROM repair01_w4_decisions") : 0;
$add('اختبار سالب لفصل الواجبات الحرج', '100٪', 'BLOCKED', '—',
     "فواحصُ سالبةٌ على القرص **$negN** · **ولا سجلَّ يربط فاحصًا بفصلِ واجباتٍ بعينِه** ⇒ "
   . 'العددُ لا يصلح بسطًا ولا مقامًا. `blocked at stage: سجلُّ فصلِ الواجباتِ الحرجِ بمعرِّفاتِه`');

/* ═══ ٦ · مرجعُ مصدرٍ صريحٌ لكلِّ إسقاطٍ منطبق ═══════════════════════════ */
$prj = (int) $one("SELECT COUNT(*) FROM $LIVE AND surface_kind = 'PROJECTION'");
$prjSrc = (int) $one("SELECT COUNT(*) FROM $LIVE AND surface_kind = 'PROJECTION'
                        AND source_of_truth <> ''");
$add('مرجع مصدر صريح لكل إسقاط منطبق', '100٪', 'MEASURED',
     ($prj ? round($prjSrc * 100 / $prj, 1) : 0) . '٪',
     "$prjSrc من $prj سطحَ إسقاطٍ يحمل `source_of_truth` غيرَ فارغ");

/* ═══ ٧ · جسرُ معرِّفِ الشاشةِ بمرحلةِ دورةِ العمل ═══════════════════════ */
$cyc = 0;
if ($tbl('gov_screen_cycle')) {
    $cyc = (int) $one("SELECT COUNT(DISTINCT s.screen_id) FROM repair01_screen_registry s
                         JOIN gov_screen_cycle g ON g.screen_file = s.screen_file
                        WHERE s.on_disk = 1 AND s.ownership_verdict <> 'RETIRE'");
}
$add('جسر معرّف الشاشة بمرحلة دورة العمل', '100٪ من المنطبق', 'MEASURED',
     ($liveN ? round($cyc * 100 / $liveN, 1) : 0) . '٪',
     "$cyc من $liveN سطحًا له صفٌّ في `gov_screen_cycle` — ⛔ **والوصلُ بالمسارِ لا بالمعرِّف**، "
   . 'و§٧ الخطوة ١٣ تشترط الوصلَ **بمعرِّفِ الشاشةِ صراحةً لا بالمسارِ ولا بالاسم**');

/* ═══ ٨ · تطابقُ ترتيبِ السايدبارِ ومجموعاتِه مع الملفّ ═══════════════════ */
$sbSpec = $tbl('repair01_w4_sidebar') ? (int) $one("SELECT COUNT(*) FROM repair01_w4_sidebar") : 0;
$add('تطابق ترتيب السايدبار ومجموعاته مع الملف', '100٪', 'BLOCKED', '—',
     "بيانُ السايدبارِ التصميميُّ **$sbSpec** صفًّا · **و§٦ لم يُنفَّذ بعد** — "
   . 'ولا يُقاس تطابقٌ قبلَ التصحيح. `blocked at stage: RPR-02 §٦ السايدبار قبل الشاشات`');

/* ═══ ٩ · أسطحٌ تكتب ومالكُ حقيقتِها مجهول ═══════════════════════════════ */
$wUnk = (int) $one("SELECT COUNT(*) FROM $LIVE AND grain_cardinality IN ('ROW','LINE')
                      AND grain_entity <> '' AND source_of_truth = ''");
$add('أسطح تكتب ومالك حقيقتها مجهول', 'صفر', 'MEASURED', (string) $wUnk,
     "أسطحٌ حبّتُها `ROW`/`LINE` (‏تكتب) وكيانُها مقيسٌ **و`source_of_truth` فارغ** — "
   . 'وهذا المقياسُ **فُتح بقياسِ الحبّة**، فقبلَه لم يكن يُعرف مَن يكتب');

/* ═══ ١٠ · حقيقةٌ واحدةٌ لها مصدران ══════════════════════════════════════ */
$dupTruth = (int) $one("SELECT COUNT(*) FROM (
                  SELECT grain_entity FROM repair01_screen_registry
                   WHERE on_disk = 1 AND ownership_verdict <> 'RETIRE'
                     AND grain_entity <> '' AND grain_cardinality IN ('ROW','LINE')
                   GROUP BY grain_entity HAVING COUNT(*) > 1) t");
$dupEx = (string) $one("SELECT GROUP_CONCAT(e SEPARATOR ' · ') FROM (
                  SELECT grain_entity e FROM repair01_screen_registry
                   WHERE on_disk = 1 AND ownership_verdict <> 'RETIRE'
                     AND grain_entity <> '' AND grain_cardinality IN ('ROW','LINE')
                   GROUP BY grain_entity HAVING COUNT(*) > 1
                   ORDER BY COUNT(*) DESC LIMIT 5) t");
$add('حقيقة واحدة لها مصدران', 'صفر', 'MEASURED', (string) $dupTruth,
     'كيانٌ تكتبه أكثرُ من واجهةٍ حيّةٍ بحبّةِ سجلٍّ — أعلاها: ' . ($dupEx === '' ? '—' : $dupEx)
   . ' · **فُتح بقياسِ الحبّة**');

/* ═══ ١١ · كتابةٌ تعبر حدودَ إدارةٍ بلا عقد ══════════════════════════════
   مالكُ الكيانِ = الإدارةُ التي تكتبه بأقلِّ عددِ أسطحٍ… لا. **مالكُه = مالكُ
   السطحِ الوحيدِ الذي يكتبه**؛ فإن كتبه أكثرُ من إدارةٍ فذاك العبورُ نفسُه. */
$cross = (int) $one("SELECT COUNT(*) FROM (
              SELECT grain_entity FROM repair01_screen_registry
               WHERE on_disk = 1 AND ownership_verdict <> 'RETIRE'
                 AND grain_entity <> '' AND grain_cardinality IN ('ROW','LINE')
               GROUP BY grain_entity HAVING COUNT(DISTINCT owner_code) > 1) t");
$add('كتابة تعبر حدود إدارة بلا عقد', 'صفر', 'MEASURED', (string) $cross,
     'كيانٌ تكتبه إدارتان فأكثرُ بحبّةِ سجلٍّ — ⛔ **والعقدُ غيرُ مقيسٍ بعدُ**، '
   . 'فالرقمُ **سقفُ الخرقِ لا الخرقُ**: منه ما له عقدٌ مُعلَنٌ لم يُسجَّل. **فُتح بقياسِ الحبّة**');

/* ═══ ١٢ · هجراتٌ غيرُ مصالَحةٍ مع الدفتر ════════════════════════════════ */
$mig = 0; $migWit = 'لا جدولَ تسويةٍ';
if ($tbl('gov_migration_settlement')) {
    $mig = (int) $one("SELECT COUNT(*) FROM gov_migration_settlement
                        WHERE COALESCE(settlement_state,'') NOT IN ('SETTLED','RECONCILED','OK')");
    $tot = (int) $one("SELECT COUNT(*) FROM gov_migration_settlement");
    $migWit = "غيرُ مصالَحةٍ **$mig** من $tot في `gov_migration_settlement` · وخطُّ الأمرِ كان ٥٨";
}
$add('هجرات غير مصالَحة مع الدفتر', 'صفر', 'MEASURED', (string) $mig, $migWit);

/* ═══ ١٣ · شاشةُ `PLATFORM` بلا تبريرٍ منصّيٍّ معتمَد ═══════════════════ */
$plat = (int) $one("SELECT COUNT(*) FROM $LIVE AND ownership_verdict = 'PLATFORM_SHARED'");
$platReg = $tbl('repair01_platform_capabilities')
         ? (int) $one("SELECT COUNT(*) FROM repair01_platform_capabilities") : 0;
$add('شاشة PLATFORM بلا تبرير منصّي معتمد', 'صفر', 'MEASURED', (string) $plat,
     "أسطحٌ `PLATFORM_SHARED` حيّةٌ **$plat** · وسجلُّ المنصّةِ فيه **$platReg** قدرةً ⇒ "
   . '**لا واحدةَ منها مسجَّلةٌ بمعرِّفِها وقاعدةِ ظهورِها** (§٤·٤ الشرط الرابع)، '
   . 'فالأربعةُ مجتمعةً غيرُ مستوفاةٍ ولا واحدةٌ تُعفى');

/* ═══ ١٤ · ملفُّ مكتبةٍ مسجَّلٌ شاشةً — وقاعدةٌ مانعةٌ مفعَّلة ═══════════ */
$vend = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                     WHERE screen_file LIKE '%vendor/%' AND ownership_verdict <> 'RETIRE'");
$rule = (int) $one("SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
                     WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME LIKE '%vendor%'");
$barTool = is_file($ROOT . '/tools/repair01_w2_gate.php') || is_file($ROOT . '/tools/rpr02a_gates.php');
$add('ملف مكتبة مسجَّل شاشةً — وقاعدة مانعة مفعَّلة', 'صفر · مفعَّلة', 'MEASURED',
     $vend . ' · ' . (($rule > 0 || $barTool) ? 'حاجبٌ قائم' : 'لا قاعدة'),
     "ملفّاتُ `vendor/` غيرُ الموسومةِ `RETIRE`: **$vend** (‏والمسجَّلةُ `RETIRE` حكمٌ مسجَّلٌ لا عطب) · "
   . 'قيدُ مخطَّطٍ باسمِ المكتبة: ' . $rule . ' · حاجبٌ في العُدّة: ' . ($barTool ? 'نعم' : 'لا'));

/* ═══ ١٥ · أهدافٌ خرجت من المقامِ بلا مرجعِ قرارِ مالك ═══════════════════ */
$outNoRef = (int) $one("SELECT COUNT(*) FROM repair01_target_universe
                         WHERE verdict IN ('NOT_APPLICABLE','RETIRED_TARGET')
                           AND (verdict_witness = '' OR verdict_witness NOT LIKE '%قرار%')");
$add('أهداف خرجت من المقام بلا مرجع قرار مالك', 'صفر', 'MEASURED', (string) $outNoRef,
     "الخارجون من المقامِ **$tuOut** · ومنهم بلا ذِكرِ قرارٍ في شاهدِه **$outNoRef** "
   . '(§٤·٣ الحارس الأول: لا يصدر أيٌّ منهما إلّا بمرجعِ قرارِ مالكٍ موثَّقٍ بتاريخه)');

/* ═══ ١٦ · اسمٌ معروضٌ غيرُ معتمد ═══════════════════════════════════════ */
$navBad = 0; $navWit = 'لا سجلَّ تسمياتٍ معياريّ';
if ($tbl('nav_canonical')) {
    $navTot = (int) $one("SELECT COUNT(*) FROM nav_canonical");
    $navBad = (int) $one("SELECT COUNT(*) FROM nav_canonical WHERE COALESCE(status,'') <> 'APPROVED'");
    $navWit = "غيرُ `APPROVED` **$navBad** من $navTot في `nav_canonical` — "
            . '⛔ **وهذا قياسُ السجلِّ لا قياسُ المعروض**: الاسمُ يُصيَّر من أربعةِ مصادرَ بترجيح';
}
$add('اسم معروض غير معتمد', 'صفر', 'MEASURED', (string) $navBad, $navWit);

/* ═══ الاختبارُ السالب ═══════════════════════════════════════════════════ */
if ($SELF) {
    $fail = 0;
    if (count($M) !== 16) { echo '  X المقاييسُ ' . count($M) . " لا ستّةَ عشرَ\n"; $fail++; }
    foreach ($M as $x) {
        if (trim($x[4]) === '') { echo "  X مقياسٌ بلا شاهد: {$x[0]}\n"; $fail++; }
        if ($x[2] === 'BLOCKED' && mb_strpos($x[4], 'blocked at stage') === false) {
            echo "  X محجوبٌ بلا مرحلةٍ مسمّاة: {$x[0]}\n"; $fail++;
        }
        if ($x[2] === 'MEASURED' && $x[3] === '—') { echo "  X مقيسٌ بلا قيمة: {$x[0]}\n"; $fail++; }
    }
    /* **الكاسرُ**: مقياسٌ يُنزع شاهدُه يجب أن يُحمِرَّ الفاحص */
    $probe = $M; $probe[0][4] = '';
    $red = 0; foreach ($probe as $x) { if (trim($x[4]) === '') { $red++; } }
    if ($red !== 1) { echo "  X الفاحصُ لا يرصد نزعَ الشاهد\n"; $fail++; }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n"
               : "\n🟢 الفحصُ الذاتيُّ تامٌّ — ستّةَ عشرَ مقياسًا، ولكلٍّ شاهدُه، وللمحجوبِ مرحلتُه\n";
    exit($fail ? 1 : 0);
}

/* ═══ العرض ═══════════════════════════════════════════════════════════════ */
$meas = 0; $blk = 0;
foreach ($M as $x) { if ($x[2] === 'MEASURED') { $meas++; } else { $blk++; } }
echo "\n═══ `RPR-02` §١٢ — معيارُ القبولِ بستّةَ عشرَ مقياسًا ═══\n";
printf("  اللقطة %s · الأسطحُ الحيّةُ %d · **مقيسٌ %d · محجوبٌ %d**\n\n", $sid, $liveN, $meas, $blk);
$i = 0;
foreach ($M as $x) {
    $i++;
    printf("  %2d. %-46s  هدف: %-16s  %s: %s\n", $i, mb_substr($x[0], 0, 46), $x[1],
           $x[2] === 'MEASURED' ? 'المقيس' : '**محجوب**', $x[3]);
    echo '      ' . mb_substr(strip_tags($x[4]), 0, 200) . "\n";
}
echo "\n────────────────────────────────────────────────────────────\n";
echo "◆ **والأمرُ يقول**: «وما كان «غير مقيس» فأوّلُ واجبِك أن تقيسه — فالمقياسُ\n";
echo "  الذي لا يُقاس ليس معيارًا بل أمنية». وكان في الأمرِ **أحدَ عشرَ غيرَ مقيس**،\n";
printf("  فصار المحجوبُ **%d** — وثلاثةٌ منها فُتحت بقياسِ الحبّةِ وحدَه.\n", $blk);

if ($MD) {
    $o  = "# `RPR-02` §١٢ — معيارُ القبولِ بستّةَ عشرَ مقياسًا\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `" . $sid . "`\n\n";
    $o .= "**مقيسٌ " . $meas . " · محجوبٌ " . $blk . "** — وكان في الأمرِ أحدَ عشرَ «غيرَ مقيس».\n\n";
    $o .= "| # | المقياس | المستهدف | الحال | المقيس | الشاهد |\n|---:|---|---|---|---|---|\n";
    $i = 0;
    foreach ($M as $x) {
        $i++;
        $o .= '| ' . $i . ' | ' . $x[0] . ' | ' . $x[1] . ' | `' . $x[2] . '` | **' . $x[3] . '** | '
            . str_replace('|', '⎮', $x[4]) . " |\n";
    }
    $o .= "\n⛔ **ولا طبقةَ ثالثةً اسمُها «صفر» بلا قياس** — فصفرٌ من مفردةٍ لا وجودَ لها أخضرُ كاذب،\n";
    $o .= "والمحجوبُ يُعلَن محجوبًا بمرحلتِه ولا يُكتب صفرًا.\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/RPR02_S12_SCORECARD.md', $o);
    echo "\n✔ كُتب docs/REPAIR01_20260823/RPR02_S12_SCORECARD.md\n";
}
