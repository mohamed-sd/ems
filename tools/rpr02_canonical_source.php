<?php
/**
 * tools/rpr02_canonical_source.php — `RPR-02` §٥·٩ · المصدرُ القانونيُّ للحقيقةِ المكرَّرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — §٥·٩: *«لكلِّ حقيقةِ أعمالٍ **مالكٌ قانونيٌّ واحد** ·
 *   وتظهر إسقاطًا في أيِّ عددٍ من الإدارات · ⛔ **ولا تُنشأ ولا تُعدَّل من
 *   مصدرَين مستقلَّين**»*. والقبول: `Duplicate Canonical Source = 0`.
 *
 * ◆ **والمقامُ `OWN_FACT` وحدَه** — فالكيانُ المكتوبُ من **كِيتٍ مشتركٍ** أو
 *   **بنيةٍ صِرفةٍ** ليس حقيقةَ أعمالٍ يملكها سطح، ونسبتُه إليه خرقٌ كاذب.
 *
 * ◆ **أربعُ قواعدَ بترتيبٍ حتميّ — والمقياسُ يُعلن أيَّتَها حكمت**:
 *   **N1 · `ARTIFACT_NAME_IDENTITY`** — اسمُ ملفِّ السطحِ **هو اسمُ الكيان**.
 *        `Audit/iaf_findings.php` ⇐ `iaf_findings` ⇒ **هويّةٌ لا مصادفة**.
 *   **N2 · `DECLARED_OVER_INFERRED`** — كاتبٌ واحدٌ **يُعلن الجدولَ في ملفِّه**
 *        (`G1_CMP03_DECLARED`/`G1B_SELF_DECLARED`) وسائرُهم **مُستنتَجون
 *        بالقياس** (`G2`/`G3`) ⇒ المُعلِنُ هو المصدر. **وهذه مرتبةُ الشواهدِ
 *        التي يستعملها قياسُ الحبّةِ نفسُه**: *«أقوى الشواهدِ لأنَّه تصريحٌ لا
 *        استنتاج»* — ⛔ **ولا يُخترع ترجيحٌ جديدٌ لسؤالٍ له ترجيحٌ مسجَّل**.
 *   **N3 · `CROSS_OWNER_NEEDS_CONTRACT`** — الكتّابُ تحت **إدارتين فأكثر**:
 *        ⛔ **لا يُحسم بقياسٍ ألبتّة**. فاختيارُ إدارةٍ مالكةً دون أخرى **قرارُ
 *        أعمالٍ لا قياسُ أداة**، وهو **خرقُ §٥·٩ نفسُه** الذي يعدّه المقياسُ
 *        **#١١** («كتابةٌ تعبر حدودَ إدارةٍ بلا عقد»). ⇒ يُعلَن ولا يُرجَّح.
 *   **N4 · `TIE_DECLARED`** — إدارةٌ واحدةٌ ولا مُرجِّحَ بين كتّابِها ⇒ يُعلَن
 *        مفتوحًا **بأسمائهم**. ⛔ **والغموضُ يُعلَن ولا يُحسم بأوّلِ المصادفات**.
 *
 * ⛔ **ولا يَعِدُ هذا المقياسُ بصفر** — و`N3` و`N4` **ليستا عجزَ أداةٍ بل حدَّها**:
 *   الأولى تحتاج **عقدًا مُعلَنًا** والثانيةُ **قرارَ مالكٍ داخلَ إدارةٍ واحدة**.
 *   ⛔ **وترجيحُ أحدِ الكاتبَين لتنخفض #١٠ يُخفي #١١ ولا يحلُّها**.
 *
 * التشغيل:
 *   php tools/rpr02_canonical_source.php [--apply] [--md] [--selftest]
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
$SELF  = in_array('--selftest', $argv, true);

/* ═══ ① القواعدُ مفصولةً — كي تُختبر وحدَها ═══════════════════════════════ */
function cs_name_identity($route, $entity)
{
    $b = strtolower(preg_replace('~\.php$~i', '', basename((string) $route)));
    return ($b !== '' && $b === strtolower((string) $entity));
}
function cs_is_declared($rule)
{
    return in_array((string) $rule, array('G1_CMP03_DECLARED', 'G1B_SELF_DECLARED'), true);
}

/* ═══ ② الاختبارُ السالبُ — يُصيب الطرفَين ولا يمرُّ بمفردةٍ فريدة ═══════ */
if ($SELF) {
    $fail = 0;
    if (!cs_name_identity('Audit/iaf_findings.php', 'iaf_findings')) { echo "  X الهويّةُ لم تُرصَد\n"; $fail++; }
    if (cs_name_identity('Audit/iaf_action_plans.php', 'iaf_findings')) { echo "  X هويّةٌ كاذبةٌ أُقرَّت\n"; $fail++; }
    /* ⛔ **والاحتواءُ ليس هويّة**: `tickets_list.php` ليس `tickets` */
    if (cs_name_identity('Tickets/tickets_list.php', 'tickets')) { echo "  X الاحتواءُ عُدَّ هويّةً\n"; $fail++; }
    if (!cs_is_declared('G1B_SELF_DECLARED')) { echo "  X المُعلِنُ لم يُعرَف\n"; $fail++; }
    if (cs_is_declared('G2_SINGLE_WRITE'))    { echo "  X المُستنتَجُ عُدَّ مُعلِنًا\n"; $fail++; }
    /* **الكاسر**: مفردةٌ فريدةٌ لا ترد إلّا هنا */
    if (cs_name_identity('x/zzq_unique_probe.php', 'iaf_findings')) { echo "  X المفردةُ الفريدةُ طوبقت\n"; $fail++; }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n"
               : "\n🟢 الفحصُ الذاتيُّ تامٌّ — والهويّةُ تميّز والاحتواءُ لا يمرُّ هويّةً\n";
    exit($fail ? 1 : 0);
}

/* ═══ ③ نافذةُ القياس ════════════════════════════════════════════════════ */
$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
if (!$snap && $APPLY) { exit("⛔ **لا نافذةَ قياسٍ مفتوحة** — جمِّدْ أوّلًا.\n"); }
$sid = $snap ? $snap['snapshot_id'] : 'DRY';

/* ═══ ④ الكيانُ المكرَّرُ وكتّابُه ════════════════════════════════════════ */
$LIVE = "repair01_screen_registry WHERE on_disk = 1 AND ownership_verdict <> 'RETIRE'
         AND grain_entity <> '' AND grain_cardinality IN ('ROW','LINE')
         AND grain_fact_scope = 'OWN_FACT'";
$ents = array();
$r = $conn->query("SELECT grain_entity e, COUNT(*) n FROM $LIVE GROUP BY e HAVING n > 1 ORDER BY n DESC, e");
while ($x = $r->fetch_row()) { $ents[$x[0]] = (int) $x[1]; }

/* شاهدُ العقدِ المقيسِ — إن وُجد. ⛔ **ولا يُختلق حين يغيب**: بلا صفوفٍ لا `N5`. */
$ccBy = null;
$q = @$conn->query("SELECT entity, screen_id, owner_code, writer_verdict FROM repair01_cross_contract");
if ($q && $q->num_rows) {
    $ccBy = array();
    while ($x = $q->fetch_assoc()) {
        $ccBy[$x['entity']][] = array('sc' => $x['screen_id'], 'own' => $x['owner_code'],
                                      'v' => $x['writer_verdict']);
    }
}
$rows = array();
$stat = array('N1' => 0, 'N2' => 0, 'N5' => 0, 'N3' => 0, 'N4' => 0);
foreach ($ents as $ent => $n) {
    $w = array();
    $q = $conn->query("SELECT screen_id, canonical_label_ar, owner_code, grain_rule, route
                         FROM $LIVE AND grain_entity = '" . $e($ent) . "' ORDER BY screen_id");
    while ($x = $q->fetch_assoc()) { $w[] = $x; }
    $owners = array();
    foreach ($w as $x) { $owners[$x['owner_code']] = 1; }
    $nOwn = count($owners);

    $names = array();
    foreach ($w as $x) {
        $names[] = $x['screen_id'] . ' (' . ($x['owner_code'] === '' ? '—' : $x['owner_code'])
                 . ' · ' . $x['grain_rule'] . ')';
    }
    $list = implode(' · ', $names);

    /* N1 — هويّةُ اسمِ الأثر */
    $hit = array();
    foreach ($w as $x) { if (cs_name_identity($x['route'], $ent)) { $hit[] = $x; } }
    if (count($hit) === 1) {
        $rows[] = array($ent, $n, $nOwn, 'N1_ARTIFACT_NAME_IDENTITY', $hit[0]['screen_id'],
            $hit[0]['owner_code'], $list, 1,
            'N1 · اسمُ أثرِ `' . $hit[0]['screen_id'] . '` هو اسمُ الكيانِ نفسُه (`'
          . basename((string) $hit[0]['route']) . '` ⇐ `' . $ent . '`) **ولا يشاركه فيه كاتبٌ آخر** '
          . '⇒ فهو المصدرُ القانونيُّ وسائرُ الكتّابِ إسقاطاتٌ تُوجَّه إليه · الكتّاب: ' . $list
          . ' · لقطة ' . $sid);
        $stat['N1']++;
        continue;
    }
    /* N2 — المُعلِنُ يغلب المُستنتَج */
    $dec = array();
    foreach ($w as $x) { if (cs_is_declared($x['grain_rule'])) { $dec[] = $x; } }
    if (count($dec) === 1 && count($dec) < count($w)) {
        $rows[] = array($ent, $n, $nOwn, 'N2_DECLARED_OVER_INFERRED', $dec[0]['screen_id'],
            $dec[0]['owner_code'], $list, 1,
            'N2 · `' . $dec[0]['screen_id'] . '` **يُعلن الجدولَ في ملفِّه** (`' . $dec[0]['grain_rule']
          . '`) وسائرُ الكتّابِ **مُستنتَجون بالقياس** ⇒ والتصريحُ أقوى من الاستنتاجِ '
          . '**بمرتبةِ الشواهدِ التي يستعملها قياسُ الحبّةِ نفسُه** · الكتّاب: ' . $list
          . ' · لقطة ' . $sid);
        $stat['N2']++;
        continue;
    }
    /* ═══ N5 — الكاتبُ الحقيقيُّ الوحيدُ بعدَ قياسِ العقد ═══════════════════
       ◆ **وهذه ليست قاعدةً جديدةً بل `S1` نفسُها بعدَ أن صار الكاتبُ مقيسًا**:
         §٥·٩ تعرّف المالكَ القانونيَّ بأنّه **مَن يُنشئ ويعدّل**. وقبلَ قياسِ
         العقدِ كان «الكاتبُ» = مَن نُسب إليه الكيانُ في السجل — وفيهم مَن
         **يصرّح ولا يكتب** (`X0`) ومَن **يمرُّ ببابِ الخدمةِ المالكة** (`X1`).
         ⇒ فإذا بقي **واحدٌ** يُنشئ ويعدّل بجملةٍ خامّةٍ (`X2`) وسائرُهم `X0`/`X1`
         **فهو المالكُ بالتعريفِ لا بالترجيح**، وسائرُهم إسقاطاتٌ تُوجَّه إليه.
       ⛔ **ولا تُطبَّق إلّا على شاهدٍ مقيسٍ قائم**: صفوفُ `repair01_cross_contract`
         لهذا الكيان. وبلا شاهدٍ لا حسم. */
    if ($ccBy !== null && isset($ccBy[$ent])) {
        $x2 = array(); $x1 = 0; $x0 = 0;
        foreach ($ccBy[$ent] as $cw) {
            if ($cw['v'] === 'X2_RAW_DIRECT')           { $x2[] = $cw; }
            elseif ($cw['v'] === 'X1_SERVICE_MEDIATED') { $x1++; }
            else                                        { $x0++; }
        }
        if (count($x2) === 1 && ($x1 + $x0) > 0) {
            $rows[] = array($ent, $n, $nOwn, 'N5_SOLE_MEASURED_CREATOR', $x2[0]['sc'],
                $x2[0]['own'], $list, 1,
                'N5 · بعدَ قياسِ عقدِ الكتابة (`rpr02_cross_contract`): كاتبٌ خامٌّ **واحدٌ** '
              . '`' . $x2[0]['sc'] . '` (‏`' . ($x2[0]['own'] === '' ? '—' : $x2[0]['own']) . '`) '
              . 'و**' . $x1 . '** يمرُّ ببابٍ مسمًّى (`X1`) و**' . $x0 . '** يصرّح ولا يكتب (`X0`) ⇒ '
              . '**فهو مَن يُنشئ ويعدّل** بتعريفِ §٥·٩ لا بترجيحٍ · الكتّاب: ' . $list
              . ' · لقطة ' . $sid);
            $stat['N5']++;
            continue;
        }
    }
    /* N3 — عبورُ إدارات: لا يُحسم بقياس */
    if ($nOwn > 1) {
        $rows[] = array($ent, $n, $nOwn, 'N3_CROSS_OWNER_NEEDS_CONTRACT', '', '', $list, 0,
            'N3 · الكتّابُ تحت **' . $nOwn . '** إدارةً (' . implode(' · ', array_map(
                function ($o) { return $o === '' ? '—' : $o; }, array_keys($owners)))
          . ') ⇒ ⛔ **لا يُحسم بقياس**: اختيارُ إدارةٍ مالكةً دون أخرى **قرارُ أعمالٍ لا قياسُ أداة**. '
          . 'وهذا **خرقُ §٥·٩ نفسُه** ويحتاج **عقدًا مُعلَنًا** — وهو ما يعدّه المقياسُ **#١١** · '
          . 'الكتّاب: ' . $list . ' · لقطة ' . $sid);
        $stat['N3']++;
        continue;
    }
    /* N4 — إدارةٌ واحدةٌ ولا مُرجِّح */
    $rows[] = array($ent, $n, $nOwn, 'N4_TIE_DECLARED', '', '', $list, 0,
        'N4 · الكتّابُ **كلُّهم تحت `' . (string) array_keys($owners)[0] . '`** — فالمالكُ القانونيُّ '
      . 'غيرُ ملتبس، **والملتبسُ أيُّ سطحٍ منهم هو المصدرُ**: لا هويّةَ اسمٍ ولا مُعلِنَ واحدٌ يميّز '
      . '(' . implode(' · ', array_map(function ($x) { return $x['grain_rule']; }, $w)) . ') '
      . '⇒ **يُعلَن مفتوحًا ولا يُحسم بأوّلِ المصادفات** · الكتّاب: ' . $list . ' · لقطة ' . $sid);
    $stat['N4']++;
}

/* ═══ ⑤ العرض ════════════════════════════════════════════════════════════ */
$N = count($rows);
$res = $stat['N1'] + $stat['N2'] + $stat['N5'];
echo "\n═══ `RPR-02` §٥·٩ — المصدرُ القانونيُّ للحقيقةِ المكرَّرة ═══\n";
printf("  اللقطة: %s · كياناتٌ يكتبها أكثرُ من سطحٍ (`OWN_FACT`): **%d**\n\n", $sid, $N);
echo "  ── القواعدُ الخمس ──\n";
printf("     N1 `ARTIFACT_NAME_IDENTITY`     %2d — اسمُ الأثرِ هو اسمُ الكيان ⇒ **يُحسم**\n", $stat['N1']);
printf("     N2 `DECLARED_OVER_INFERRED`     %2d — المُعلِنُ يغلب المُستنتَج ⇒ **يُحسم**\n", $stat['N2']);
printf("     N5 `SOLE_MEASURED_CREATOR`      %2d — كاتبٌ خامٌّ واحدٌ بعدَ قياسِ العقد ⇒ **يُحسم**\n", $stat['N5']);
printf("     N3 `CROSS_OWNER_NEEDS_CONTRACT` %2d — إدارتان فأكثرُ ⇒ ⛔ **عقدٌ لا قياس** (‏= #١١)\n", $stat['N3']);
printf("     N4 `TIE_DECLARED`               %2d — إدارةٌ واحدةٌ بلا مُرجِّحٍ ⇒ **يُعلَن مفتوحًا**\n", $stat['N4']);
printf("\n  ⇒ **حُسم %d · بقي %d** — والمقياسُ **#١٠ %d ⇒ %d**\n", $res, $N - $res, $N, $N - $res);
echo "  ◆ و`N3` **ليست عجزَ أداةٍ بل حدَّها**: هي بعينِها مقامُ **#١١** — وإغلاقُها بعقدٍ يُغلقهما معًا\n";

echo "\n  ── الكياناتُ بأحكامِها ──\n";
foreach ($rows as $x) {
    printf("   %-30s %d كاتبًا · %d إدارة · %-30s %s\n",
           $x[0], $x[1], $x[2], substr($x[3], 0, 30), $x[4] === '' ? '⛔ مفتوح' : '⇒ ' . $x[4]);
}

/* ═══ ⑥ التثبيت ══════════════════════════════════════════════════════════ */
if ($APPLY) {
    $has = $conn->query("SHOW TABLES LIKE 'repair01_canonical_source'");
    if (!$has || !$has->num_rows) {
        exit("⛔ **`repair01_canonical_source` غيرُ موجود** — والعُدّةُ لا تُنشئ مخطَّطًا.\n"
           . "   شغِّلْ: php database/migrations/2028_01_05_rpr02_canonical_source.php\n");
    }
    $conn->query("DELETE FROM repair01_canonical_source");
    $n = 0;
    foreach ($rows as $x) {
        $ok = $conn->query("INSERT INTO repair01_canonical_source
              (entity,writers,owners,rule_code,canonical_screen,canonical_owner,
               writer_screens,resolved,witness,snapshot_id,measured_at)
            VALUES ('" . $e($x[0]) . "'," . (int) $x[1] . "," . (int) $x[2] . ",'" . $e($x[3]) . "','"
             . $e($x[4]) . "','" . $e($x[5]) . "','" . $e(mb_substr($x[6], 0, 500)) . "',"
             . (int) $x[7] . ",'" . $e(mb_substr($x[8], 0, 600)) . "','" . $e($sid) . "',NOW())");
        if (!$ok) { exit("✘ تعذّر تثبيتُ {$x[0]}: {$conn->error}\n"); }
        $n++;
    }
    $bad = (int) $conn->query("SELECT COUNT(*) FROM repair01_canonical_source WHERE witness = ''")->fetch_row()[0];
    printf("\n  ✔ ثُبِّت **%d** كيانًا · صفٌّ بلا شاهدٍ %d\n", $n, $bad);
}

if ($MD) {
    $o  = "# `RPR-02` §٥·٩ — المصدرُ القانونيُّ للحقيقةِ المكرَّرة\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `" . $sid . "`\n\n";
    $o .= "## ما بُحث عنه فلم يوجد\n\n";
    $o .= "`repair01_master_entities` **تسعةُ صفوفٍ** لبياناتِ الهويّةِ الرئيسةِ لا دفترُ ملكيّةٍ\n";
    $o .= "للحقائق · و`repair01_ownership` مفتاحُها **المسار** وتصف **ظهورَ المساحات** لا ملكيّةَ\n";
    $o .= "الكيانات. ⇒ **فالموضعُ فُتح ولم يُنسخ**.\n\n";
    $o .= "## القواعدُ الأربع\n\n| القاعدة | كيانات | الحكم |\n|---|---:|---|\n";
    $o .= "| `N1` هويّةُ اسمِ الأثر | **" . $stat['N1'] . "** | اسمُ ملفِّ السطحِ هو اسمُ الكيانِ نفسُه ⇒ **يُحسم** |\n";
    $o .= "| `N2` المُعلِنُ يغلب المُستنتَج | **" . $stat['N2'] . "** | تصريحُ السطحِ بجدولِه أقوى من الاستنتاجِ — **بمرتبةِ شواهدِ قياسِ الحبّةِ نفسِها** |\n";
    $o .= "| `N3` عبورُ إدارات | **" . $stat['N3'] . "** | ⛔ **لا يُحسم بقياس** — عقدٌ مُعلَنٌ لا ترجيحُ أداة (‏= مقامُ **#١١**) |\n";
    $o .= "| `N4` تعادلٌ داخلَ إدارة | **" . $stat['N4'] . "** | المالكُ غيرُ ملتبس، والملتبسُ **أيُّ سطحٍ منهم هو المصدر** |\n\n";
    $o .= "⇒ **#١٠ من " . $N . " إلى " . ($N - $res) . "**.\n\n";
    $o .= "⛔ **ولا يَعِدُ هذا المقياسُ بصفر**: `N3` تحتاج **عقدًا مُعلَنًا** و`N4` **قرارَ مالكٍ\n";
    $o .= "داخلَ إدارةٍ واحدة**. **وترجيحُ أحدِ الكاتبَين لتنخفض #١٠ يُخفي #١١ ولا يحلُّها.**\n\n";
    $o .= "## الكياناتُ بأحكامِها\n\n";
    $o .= "| الكيان | كتّاب | إدارات | القاعدة | المصدرُ القانونيّ |\n|---|---:|---:|---|---|\n";
    foreach ($rows as $x) {
        $o .= "| `" . $x[0] . "` | " . $x[1] . " | " . $x[2] . " | `" . $x[3] . "` | "
            . ($x[4] === '' ? '⛔ مفتوح' : '`' . $x[4] . '` (' . ($x[5] === '' ? '—' : $x[5]) . ')') . " |\n";
    }
    $o .= "\n## الشواهدُ كاملةً\n\n";
    foreach ($rows as $x) { $o .= "- **`" . $x[0] . "`** — " . $x[8] . "\n"; }
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/RPR02_S59_CANONICAL.md', $o);
    echo "\n✔ كُتب: docs/REPAIR01_20260823/RPR02_S59_CANONICAL.md\n";
}
