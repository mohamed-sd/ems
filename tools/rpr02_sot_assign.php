<?php
/**
 * tools/rpr02_sot_assign.php — `RPR-02` §٥·٩ · مصدرُ الحقيقةِ بقاعدةِ الكاتبِ الوحيد
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — §٥·٩: *«لكلِّ حقيقةِ أعمالٍ **مالكٌ قانونيٌّ واحد** ·
 *   وتظهر إسقاطًا في أيِّ عددٍ من الإدارات · ⛔ **ولا تُنشأ ولا تُعدَّل من
 *   مصدرَين مستقلَّين**»*. والقبولُ قبلَ الإغلاق: `Unknown Write Owner = 0`.
 *
 * ◆ **والحاجزُ ارتفع بقياسِ الحبّةِ لا بقرارٍ جديد**: §٧ الخطوة ١ قاست
 *   `grain_entity` على **٥٩٨ من ٦٢١** سطحًا — **فصار معروفًا أيُّ كيانٍ يكتبه
 *   أيُّ سطح**. وهذا هو بعينِه ما ينقص `source_of_truth`.
 *
 * ◆ **قاعدةٌ واحدةٌ تُطبَّق — ولا ثانيةَ لها**:
 *   **S1 · `SOLE_WRITER`**: كيانٌ يكتبه **سطحٌ حيٌّ واحدٌ لا غير** ⇒ ذلك السطحُ
 *        **هو** مصدرُ حقيقتِه. والشاهد: اسمُ الكيانِ وتعدادُ كتّابِه = ١.
 *        ⇒ وهذا **ليس اجتهادًا**: §٥·٩ تعرّف المالكَ القانونيَّ بأنّه **مَن
 *        يُنشئ ويعدّل**، والكاتبُ الوحيدُ هو هو بالتعريفِ لا بالترجيح.
 *
 * ⛔ **وما يكتبه كاتبان فأكثرُ لا يُعيَّن له مصدرٌ ألبتّة**: اختيارُ أحدِهما
 *   **ترجيحٌ بلا مُرجِّح**، وكتابتُه تُخفي العطبَ بدل أن تكشفه. ⇒ يُوسَم
 *   `DUPLICATE_SOURCE` **ويبقى `source_of_truth` فارغًا**، ويُعرض بعددِه.
 *   ◆ **وهؤلاء ليسوا عطبًا جديدًا** — هم **بعينِهم مقامُ المقياسِ #١٠**
 *     («حقيقةٌ لها مصدران») بأسطحِه لا بكياناتِه. ⇒ فبقيّةُ #٩ و#١٠ **عطبٌ
 *     واحدٌ يُعدُّ مرّتَين**، وإغلاقُ #١٠ يُغلقهما معًا.
 *
 * ⛔ **ولا تُكتب قيمةٌ بلا شاهد** — والقاعدةُ الصلبةُ `chk_sot_witness` تردُّ
 *   ذلك في القاعدةِ نفسِها لا في مراجعةٍ لاحقة (‏هجرة `2028_01_03`).
 *
 * التشغيل:
 *   php tools/rpr02_sot_assign.php [--apply] [--md] [--list] [--selftest]
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
$e = function ($x) use ($conn) { return $conn->real_escape_string((string) $x); };

$APPLY = in_array('--apply', $argv, true);
$MD    = in_array('--md', $argv, true);
$LIST  = in_array('--list', $argv, true);
$SELF  = in_array('--selftest', $argv, true);

/* ═══ ① القاعدةُ مفصولةً عن القاعدة — كي تُختبر وحدَها ═══════════════════ */
function sot_rule_for($writersOfEntity)
{
    if ($writersOfEntity <= 0) { return ''; }
    return ($writersOfEntity === 1) ? 'SOLE_WRITER' : 'DUPLICATE_SOURCE';
}

/* ═══ ② الاختبارُ السالبُ — يُصيب الطرفَين ولا يمرُّ بمفردةٍ فريدة ═══════ */
if ($SELF) {
    $fail = 0;
    if (sot_rule_for(1) !== 'SOLE_WRITER')      { echo "  X الكاتبُ الوحيدُ لم يُعرَف\n"; $fail++; }
    if (sot_rule_for(2) !== 'DUPLICATE_SOURCE') { echo "  X الكاتبان لم يُوسَما\n"; $fail++; }
    if (sot_rule_for(21) !== 'DUPLICATE_SOURCE'){ echo "  X الكثرةُ لم تُوسَم\n"; $fail++; }
    /* ⛔ **الكاسر**: صفرُ كتّابٍ لا يُنتج قاعدةً — ولو أنتج لَعُيِّن مصدرٌ لكيانٍ
       لا يكتبه أحدٌ، **وذاك أخضرُ كاذبٌ يُغلق #٩ بلا سطرٍ مبنيّ**. */
    if (sot_rule_for(0) !== '') { echo "  X صفرُ كتّابٍ أنتج قاعدةً\n"; $fail++; }
    /* والقاعدةُ الصلبةُ لازمةٌ — فلو غابت لَقُبِلت قيمةٌ بلا شاهد */
    $r = $conn->query("SHOW CREATE TABLE `repair01_screen_registry`");
    $ddl = $r ? $r->fetch_row()[1] : '';
    if (strpos($ddl, 'chk_sot_witness') === false) {
        echo "  X `chk_sot_witness` غائبةٌ — والقاعدةُ تقبل قيمةً بلا شاهد\n"; $fail++;
    }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n"
               : "\n🟢 الفحصُ الذاتيُّ تامٌّ — والقاعدةُ تميّز الوحيدَ من المتعدِّدِ من العدم\n";
    exit($fail ? 1 : 0);
}

/* ═══ ③ نافذةُ القياس ════════════════════════════════════════════════════ */
$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
if (!$snap && $APPLY) { exit("⛔ **لا نافذةَ قياسٍ مفتوحة** — جمِّدْ أوّلًا.\n"); }
$sid = $snap ? $snap['snapshot_id'] : 'DRY';

$col = $conn->query("SHOW COLUMNS FROM `repair01_screen_registry` LIKE 'sot_witness'");
if ((!$col || !$col->num_rows) && $APPLY) {
    exit("⛔ **عمودُ الشاهدِ غيرُ موجود** — والعُدّةُ لا تُنشئ مخطَّطًا.\n"
       . "   شغِّلْ: php database/migrations/2028_01_03_rpr02_sot_witness.php\n");
}

/* ═══ ④ الكتّابُ لكلِّ كيان — من الحبّةِ المقيسة ══════════════════════════ */
/* ⛔ **والكاتبُ لا يُعدُّ كاتبًا لمجرّدِ أنَّ `grain_entity` غيرُ فارغ**:
   `rpr02_grain_measure.php` يفرّق **الخاصَّ من المشترك** ويكتب الخلاصةَ في
   `grain_fact_scope`. وسطحٌ كيانُه من **كِيتٍ مشترك** (`ems_post_idempotency` ·
   `guard_denials`) **لا يكتب حقيقةَ أعمالٍ يملكها** — ونسبتُها إليه هي بعينِها
   الخرقُ الكاذبُ الذي أزاله المقياسُ داخلَ نفسِه ثمَّ عاد هنا طبقةً أعلى.
   ◆ و`INFRA_ONLY` **ليست حقيقةَ أعمالٍ أصلًا** — و§٥·٩ تقول «لكلِّ **حقيقةِ
     أعمالٍ** مالكٌ قانونيٌّ واحد». ⇒ فالمقامُ `OWN_FACT` وحدَه. */
$LIVE = "repair01_screen_registry WHERE on_disk = 1 AND ownership_verdict <> 'RETIRE'";
$WR   = "AND grain_cardinality IN ('ROW','LINE') AND grain_entity <> ''
         AND grain_fact_scope = 'OWN_FACT'";
$writers = array();
$r = $conn->query("SELECT grain_entity, COUNT(*) n FROM $LIVE $WR GROUP BY grain_entity");
while ($x = $r->fetch_row()) { $writers[$x[0]] = (int) $x[1]; }

$rows = array();
$r = $conn->query("SELECT screen_id, canonical_label_ar, owner_code, grain_entity,
                          grain_cardinality, source_of_truth
                     FROM $LIVE $WR ORDER BY grain_entity, screen_id");
while ($x = $r->fetch_assoc()) { $rows[] = $x; }

$stat = array('sole' => 0, 'dup' => 0, 'already' => 0, 'dupEnt' => 0);
$plan = array(); $dupBy = array();
foreach ($rows as $x) {
    $n = isset($writers[$x['grain_entity']]) ? $writers[$x['grain_entity']] : 0;
    $rule = sot_rule_for($n);
    if (trim((string) $x['source_of_truth']) !== '') { $stat['already']++; continue; }
    if ($rule === 'SOLE_WRITER') {
        $stat['sole']++;
        $plan[] = array('id' => $x['screen_id'], 'ent' => $x['grain_entity'], 'rule' => $rule,
            'wit' => 'S1 `SOLE_WRITER` · الكيانُ `' . $x['grain_entity'] . '` يكتبه **سطحٌ حيٌّ واحدٌ لا غير** '
                   . '(‏تعدادُ الكتّابِ المقيسُ = 1 على الأسطحِ الحيّةِ بحبّةِ `ROW`/`LINE`) ⇒ '
                   . 'فهذا السطحُ **هو** مصدرُ حقيقتِه بتعريفِ §٥·٩ (‏مَن يُنشئ ويعدّل) لا بترجيح · '
                   . 'مالكُه `' . ($x['owner_code'] === '' ? '—' : $x['owner_code']) . '` · لقطة ' . $sid);
    } else {
        $stat['dup']++;
        $dupBy[$x['grain_entity']][] = $x['screen_id'];
        $plan[] = array('id' => $x['screen_id'], 'ent' => $x['grain_entity'], 'rule' => 'DUPLICATE_SOURCE',
            'wit' => 'DUPLICATE_SOURCE · الكيانُ `' . $x['grain_entity'] . '` يكتبه **' . $n . '** سطحًا حيًّا ⇒ '
                   . '⛔ **ولا يُعيَّن مصدرٌ بالترجيحِ بلا مُرجِّح** — و`source_of_truth` يبقى فارغًا. '
                   . 'وهذا الصفُّ **من مقامِ المقياسِ #١٠ لا من عطبٍ جديد** · لقطة ' . $sid);
    }
}
$stat['dupEnt'] = count($dupBy);

/* ═══ ④·ب الإسقاطاتُ — مرجعُ المصدرِ الصريحُ (#٦ · §٧ الخطوة ٨) ════════════
   ◆ **والسؤالُ مختلفٌ عن سؤالِ الكاتب**: الكاتبُ يُسأل «أأنت مالكُ هذه الحقيقة؟»
     والإسقاطُ يُسأل «**من أين تستقي**؟». والعمودُ واحدٌ لأنَّ المعنى واحد:
     **مصدرُ الحقيقةِ التي يعرضها هذا السطح** — و`sot_rule` يفرّق الحالَين.
   ◆ **والجوابُ مقيسٌ سلفًا**: `grain_entity` للإسقاطِ **هو الجدولُ الذي يقرأ منه**.
   ⛔ **ولا يُؤخذ إلّا من `OWN_FACT`** — فكيانٌ جاء من **كِيتٍ مشتركٍ** هو جدولُ
     العُدّةِ لا مصدرُ هذا الإسقاط، **ونسبتُه إليه تُعطي مرجعًا كاذبًا**: وذاك
     أسوأُ من غيابِ المرجع، لأنَّ الغائبَ يُعلن نفسَه والكاذبَ يُقرأ صحيحًا. */
/* ⛔ **وسطحُ إسقاطٍ بحبّةِ `ROW`/`LINE` كاتبٌ لا قارئ** — و**قد أخطأتُ فيه**:
   كُتب له `PROJECTION_READ` («يستقي من E») وهو **يكتب E**، فخرج من مقامِ #٩
   وخفضه **٢٢ ⇒ ١٥ زورًا** بسبعةِ أسطح. ⇒ فالإسقاطُ هنا **قارئٌ بحبّتِه**
   (`LIVE_READ`/`LIST`) لا بصنفِ سطحِه، **والكاتبُ يُحكم عليه بقواعدِ الكتابة**.
   ◆ **والصنفُ لا يُغني عن الحبّة**: `surface_kind` إعلانٌ، و`grain_cardinality`
     **مقيسٌ من الأثر** — وحين يختلفان **فالمقيسُ أولى**. */
$PRJ = "repair01_screen_registry WHERE on_disk = 1 AND ownership_verdict <> 'RETIRE'
        AND surface_kind = 'PROJECTION' AND grain_entity <> ''
        AND grain_cardinality NOT IN ('ROW','LINE')";
$prjPlan = array(); $pst = array('ok' => 0, 'skip' => 0, 'already' => 0);
$r = $conn->query("SELECT screen_id, canonical_label_ar, owner_code, grain_entity,
                          grain_fact_scope, source_of_truth
                     FROM $PRJ ORDER BY screen_id");
while ($x = $r->fetch_assoc()) {
    if (trim((string) $x['source_of_truth']) !== '') { $pst['already']++; continue; }
    if ($x['grain_fact_scope'] !== 'OWN_FACT') { $pst['skip']++; continue; }
    $pst['ok']++;
    $prjPlan[] = array('id' => $x['screen_id'], 'ent' => $x['grain_entity'], 'rule' => 'PROJECTION_READ',
        'wit' => 'P1 `PROJECTION_READ` · سطحُ **إسقاطٍ** يقرأ `' . $x['grain_entity']
               . '` — والكيانُ مقيسٌ **من مصدرِ السطحِ الخاصِّ** (`OWN_FACT`) لا من كِيتٍ مشترك ⇒ '
               . '**فمرجعُ مصدرِه صريحٌ مقيسٌ لا مُعلَن**. ⛔ **وهذا يقول من أين يستقي لا أنّه يملك** · '
               . 'مالكُه `' . ($x['owner_code'] === '' ? '—' : $x['owner_code']) . '` · لقطة ' . $sid);
}

/* ═══ ⑤ العرض ════════════════════════════════════════════════════════════ */
echo "\n═══ `RPR-02` §٥·٩ — مصدرُ الحقيقةِ بقاعدةِ الكاتبِ الوحيد ═══\n";
printf("  اللقطة: %s · أسطحُ كتابةٍ بكيانٍ مقيس: **%d**\n\n", $sid, count($rows));
echo "  ── الحاجزُ الذي ارتفع ──\n";
printf("     `grain_entity` قيست في §٧ الخطوة ١ ⇒ **صار معروفًا أيُّ كيانٍ يكتبه أيُّ سطح**\n");
printf("     وكياناتٌ مكتوبةٌ متمايزة: %d\n\n", count($writers));
echo "  ── القاعدةُ الواحدة ──\n";
printf("     `SOLE_WRITER`      **%4d** سطحًا — كيانٌ بكاتبٍ واحدٍ ⇒ **يُكتب مصدرُ حقيقتِه**\n", $stat['sole']);
printf("     `DUPLICATE_SOURCE` **%4d** سطحًا على **%d** كيانًا — ⛔ **لا يُعيَّن ويبقى فارغًا**\n",
       $stat['dup'], $stat['dupEnt']);
printf("     قائمٌ سلفًا بقيمة        %4d — لا يُدهَس\n\n", $stat['already']);
$before = $stat['sole'] + $stat['dup'];
printf("  ⇒ المقياسُ **#٩ %d ⇒ %d** · والباقي **هو بعينِه مقامُ #١٠** بأسطحِه\n",
       $before, $stat['dup']);
echo "  ◆ **فبقيّةُ #٩ و#١٠ عطبٌ واحدٌ يُعدُّ مرّتَين** — وإغلاقُ #١٠ يُغلقهما معًا\n";

echo "\n  ── الإسقاطاتُ · مرجعُ المصدرِ الصريح (#٦ · §٧ الخطوة ٨) ──\n";
printf("     `PROJECTION_READ` **%4d** — يقرأ كيانًا مقيسًا **من مصدرِه الخاصّ** ⇒ **يُكتب**\n", $pst['ok']);
printf("     كيانُه من كِيتٍ مشتركٍ    %4d — ⛔ **لا يُكتب**: مرجعٌ كاذبٌ أسوأُ من غيابِه\n", $pst['skip']);
printf("     قائمٌ سلفًا بمرجعٍ        %4d — لا يُدهَس\n", $pst['already']);

if ($LIST) {
    echo "\n  ── الكياناتُ متعدّدةُ الكتّاب ──\n";
    uasort($dupBy, function ($a, $b) { return count($b) - count($a); });
    foreach ($dupBy as $ent => $ids) {
        printf("   %-30s %2d كاتبًا · %s\n", $ent, count($ids),
               mb_substr(implode(' ', array_slice($ids, 0, 6)), 0, 70));
    }
}

/* ═══ ⑥ التثبيت ══════════════════════════════════════════════════════════ */
if ($APPLY) {
    /* ⛔ **وتضييقُ المقامِ يوجب سحبَ ما كُتب خارجَه** — وإلّا بقيت قيمةٌ
       عُيِّنت بقاعدةٍ لم تعُدْ تنطبق، **وذاك أسوأُ من الفراغِ**: فراغٌ يُعلن
       نفسَه، وقيمةٌ متقادمةٌ تُقرأ حقًّا. ⇒ يُسحب ما وسمه هذا المقياسُ وحدَه
       (`SOLE_WRITER`/`DUPLICATE_SOURCE`) ولم يعُدْ في `OWN_FACT`.
       ⛔ **و`PRE_W17_DECLARED` لا يُمَسّ** — قيمةٌ من موجةٍ سابقةٍ بقرارِها. */
    $ret = (int) $conn->query("SELECT COUNT(*) FROM repair01_screen_registry
            WHERE ((sot_rule IN ('SOLE_WRITER','DUPLICATE_SOURCE')
                    AND (grain_fact_scope <> 'OWN_FACT' OR grain_cardinality NOT IN ('ROW','LINE')))
                OR (sot_rule = 'PROJECTION_READ'
                    AND (grain_fact_scope <> 'OWN_FACT' OR grain_cardinality IN ('ROW','LINE'))))")->fetch_row()[0];
    if ($ret > 0) {
        $conn->query("UPDATE repair01_screen_registry
            SET source_of_truth = '', sot_rule = '', sot_witness = '', sot_snapshot = ''
          WHERE ((sot_rule IN ('SOLE_WRITER','DUPLICATE_SOURCE')
                  AND (grain_fact_scope <> 'OWN_FACT' OR grain_cardinality NOT IN ('ROW','LINE')))
              OR (sot_rule = 'PROJECTION_READ'
                    AND (grain_fact_scope <> 'OWN_FACT' OR grain_cardinality IN ('ROW','LINE'))))");
        printf("\n  ⚠ **سُحب تعيينُ %d سطحٍ** خرج من المقامِ بتصحيحِ مدى الحقيقة — ولا يُترك رقمٌ متقادم\n", $ret);
    }
    $n = 0; $m = 0;
    foreach ($plan as $x) {
        $set = ($x['rule'] === 'SOLE_WRITER')
             ? "source_of_truth = '" . $e($x['ent']) . "', "
             : '';
        $ok = $conn->query("UPDATE repair01_screen_registry
              SET $set sot_rule = '" . $e($x['rule']) . "',
                  sot_witness = '" . $e(mb_substr($x['wit'], 0, 500)) . "',
                  sot_snapshot = '" . $e($sid) . "'
            WHERE screen_id = '" . $e($x['id']) . "'");
        if (!$ok) { exit("✘ تعذّر تعيينُ {$x['id']}: {$conn->error}\n"); }
        if ($x['rule'] === 'SOLE_WRITER') { $n++; } else { $m++; }
    }
    $pn = 0;
    foreach ($prjPlan as $x) {
        $ok2 = $conn->query("UPDATE repair01_screen_registry
              SET source_of_truth = '" . $e($x['ent']) . "',
                  sot_rule = '" . $e($x['rule']) . "',
                  sot_witness = '" . $e(mb_substr($x['wit'], 0, 500)) . "',
                  sot_snapshot = '" . $e($sid) . "'
            WHERE screen_id = '" . $e($x['id']) . "'");
        if (!$ok2) { exit("✘ تعذّر مرجعُ {$x['id']}: {$conn->error}
"); }
        $pn++;
    }
    if ($pn) { printf("  ✔ كُتب مرجعُ مصدرِ **%d** إسقاطٍ بشاهدِه
", $pn); }
    $bad = (int) $conn->query("SELECT COUNT(*) FROM repair01_screen_registry
                                WHERE source_of_truth <> '' AND sot_witness = ''")->fetch_row()[0];
    $now = (int) $conn->query("SELECT COUNT(*) FROM $LIVE $WR
                                AND (source_of_truth = '' OR source_of_truth IS NULL)")->fetch_row()[0];
    printf("\n  ✔ كُتب مصدرُ حقيقةِ **%d** سطحٍ بشاهدِه · ووُسم **%d** بـ`DUPLICATE_SOURCE` بلا قيمة\n", $n, $m);
    printf("  ✔ أُعيدت القراءة: **#٩ = %d** · وقيمةٌ بلا شاهدٍ **%d**\n", $now, $bad);
}

if ($MD) {
    $o  = "# `RPR-02` §٥·٩ — مصدرُ الحقيقةِ بقاعدةِ الكاتبِ الوحيد\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `" . $sid . "`\n\n";
    $o .= "## الحاجزُ ارتفع بقياسٍ سابقٍ لا بقرارٍ جديد\n\n";
    $o .= "`grain_entity` قيست في §٧ الخطوة ١ على الأسطحِ الحيّة، **فصار معروفًا أيُّ كيانٍ\n";
    $o .= "يكتبه أيُّ سطح** — وهذا بعينِه ما كان ينقص `source_of_truth`.\n\n";
    $o .= "## القاعدةُ الواحدة\n\n| القاعدة | الأسطح | الحكم |\n|---|---:|---|\n";
    $o .= "| `SOLE_WRITER` | **" . $stat['sole'] . "** | كيانٌ يكتبه **سطحٌ حيٌّ واحدٌ لا غير** ⇒ هو مصدرُ حقيقتِه **بتعريفِ §٥·٩ لا بترجيح** |\n";
    $o .= "| `DUPLICATE_SOURCE` | **" . $stat['dup'] . "** | على **" . $stat['dupEnt'] . "** كيانًا — ⛔ **لا يُعيَّن ويبقى فارغًا** |\n";
    $o .= "| قائمٌ سلفًا | " . $stat['already'] . " | قيمةٌ من موجةٍ سابقةٍ — لا تُدهَس |\n\n";
    $o .= "⇒ **#٩ من " . $before . " إلى " . $stat['dup'] . "**.\n\n";
    $o .= "## والباقي ليس عطبًا جديدًا\n\n";
    $o .= "الـ**" . $stat['dup'] . "** الباقيةُ تكتب **" . $stat['dupEnt'] . "** كيانًا لكلٍّ كاتبان فأكثر —\n";
    $o .= "**وهذا بعينِه مقامُ المقياسِ #١٠** («حقيقةٌ لها مصدران») معدودًا بالأسطحِ لا بالكيانات.\n";
    $o .= "⇒ **فبقيّةُ #٩ و#١٠ عطبٌ واحدٌ يُعدُّ مرّتَين**، وإغلاقُ #١٠ يُغلقهما معًا.\n";
    $o .= "⛔ **ولا يُعيَّن أحدُ الكاتبَين مصدرًا لتنخفض #٩** — فذلك يُخفي #١٠ ولا يحلُّها.\n\n";
    $o .= "## الكياناتُ متعدّدةُ الكتّاب\n\n| الكيان | كتّابُه |\n|---|---:|\n";
    uasort($dupBy, function ($a, $b) { return count($b) - count($a); });
    foreach ($dupBy as $ent => $ids) { $o .= "| `" . $ent . "` | " . count($ids) . " |\n"; }
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/RPR02_S59_SOT.md', $o);
    echo "\n✔ كُتب: docs/REPAIR01_20260823/RPR02_S59_SOT.md\n";
}
