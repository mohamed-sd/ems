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
/* ⛔ **وسببُ الحجبِ صُحِّح بعدَ قياسِه**: كُتب أوّلًا «لا مفتاحَ يجمع الدفترَين»
   وذاك **غيرُ دقيق** — `repair01_fields.requirement_id` و`gov_field_trace.req_id`
   مفتاحان متطابقان. **والعلّةُ الحقيقيّةُ أدقُّ وأثقل**: `gov_field_trace`
   **تتبُّعُ مصنَّفاتٍ تصميميّةٍ لا جانبٌ مبنيّ**، و**الجانبُ المبنيُّ الحقيقيُّ**
   `gov_field_class` **لا يغطّي إلّا ٤٤ سطحًا من ٦٢١** ومفتاحُه سبيكةٌ
   (`acc_my_day`) لا تطابق مسارًا ولا ملفًّا. ⇒ فالمطابقةُ محجوبةٌ على **قياسِ
   حقولِ المبنيِّ من الأثرِ نفسِه** كما قيست الحبّة، لا على مفتاحٍ مفقود. */
/* ✔ **والحاجزُ ارتفع** بـ`rpr02_field_measure.php` (§٧ الخطوة ٥): الطرفُ المبنيُّ
   صار **مقيسًا من الأثرِ نفسِه** بخمسةِ روافدَ — `<label>` و`<th>` و`name=`
   و`gov_field_class` للأسطحِ المولَّدةِ (بسبيكتِها المصرَّحةِ في ملفِّها) وحقولِ
   الفعلِ في عقدِ `u13`. ⛔ **ولا يُترك نصُّ حاجزٍ مرفوعٍ في تقرير**. */
$fDes = $tbl('repair01_fields')  ? (int) $one("SELECT COUNT(*) FROM repair01_fields")  : 0;
if ($tbl('repair01_field_measure')
    && (int) $one("SELECT COUNT(*) FROM repair01_field_measure") > 0) {
    $fmN   = (int) $one("SELECT COUNT(*) FROM repair01_field_measure");
    $fmApp = (int) $one("SELECT COALESCE(SUM(design_applicable),0) FROM repair01_field_measure");
    $fmHit = (int) $one("SELECT COALESCE(SUM(matched),0) FROM repair01_field_measure");
    $fmAud = (int) $one("SELECT COALESCE(SUM(design_audit),0) FROM repair01_field_measure");
    $fmNv  = (int) $one("SELECT COUNT(*) FROM repair01_field_measure WHERE vocab_terms = 0");
    $add('مطابقة حقول كل سطح مطابَق لملفه', '100٪', 'MEASURED',
         ($fmApp ? round($fmHit * 100 / $fmApp, 1) : 0) . '٪',
         "$fmHit من $fmApp حقلٍ منطبقٍ على **$fmN** سطحًا مطابَقًا — مقيسًا من الأثرِ بـ`rpr02_field_measure.php` · "
       . "و`AUDIT` **$fmAud** خارجَ المقامِ بنصِّ §٧ الخطوة ١١ (إلحاقية) · "
       . "و**$fmNv** سطحًا خلا أثرُه من مفردةٍ فحمل شاهدَ عجزِه (`NO_VOCAB`/`REDIRECTOR`) ولم يُكتب صفرًا. "
       . "⛔ **وهذا قياسُ حضورٍ لا قياسُ نوعٍ ولا ترتيب**");
} else {
    $fCls = $tbl('gov_field_class') ? (int) $one("SELECT COUNT(DISTINCT screen_code) FROM gov_field_class WHERE active = 1") : 0;
    $add('مطابقة حقول كل سطح مطابَق لملفه', '100٪', 'BLOCKED', '—',
         "دفترُ الحقولِ التصميميُّ **$fDes** حقلًا على ٤٣٣ هدفًا · والجانبُ المبنيُّ "
       . "`gov_field_class` **لا يغطّي إلّا $fCls سطحًا من $liveN** ومفتاحُه سبيكةٌ لا مسار. "
       . '⇒ `blocked at stage: قياسُ حقولِ المبنيِّ من الأثرِ نفسِه (§٧ الخطوة ٥)` '
       . '— **شغِّلْ `php tools/rpr02_field_measure.php --apply`**');
}

/* ═══ ٤ · مطابقةُ آلةِ الحالةِ لكلِّ معاملة ══════════════════════════════ */
/* ⛔ **والمقامُ والبسطُ كلاهما كان خطأً** — والمقيسُ يشهد:
   · **المقام**: `ROW`/`LINE` بلا `grain_fact_scope` يضمُّ **٧٧** سطحًا كيانُها من
     كِيتٍ مشتركٍ أو بنيةٍ صِرفة — **وتلك ليست معاملات**.
   · **والبسط**: الثمانيةَ عشرَ التي تحمل `state_model_ref` **كلُّها** أسطحُ
     بوّابةٍ تنفيذيّةٍ كيانُها المقيسُ `guard_denials` **من كِيتٍ مشترك**
     (`SHARED_KIT`) ⇒ **ولا واحدةَ منها معاملةٌ بحبّتِها**.
   ⇒ فالمقيسُ على المعاملاتِ الحقيقيّةِ **صفرٌ من ١٣١**، والثمانيةَ عشرَ **إعلانُ
     موجةٍ على أسطحٍ لم تُقَسْ معاملاتٍ**. ⛔ **وثمانيةٌ وعشرون بالعشرِ كانت
     تُقرأ تقدُّمًا وهي نسبةُ غيرِ المعاملاتِ إلى غيرِها.**
   ◆ **و§٧ الخطوة ١٢ تقول «لا حقلَ حالةٍ حرّ»** — وذاك **يُقاس من المخطَّط**:
     عمودُ الحالةِ `ENUM` محكومٌ، و`VARCHAR`/`TINYINT` حرّ. */
$txn   = (int) $one("SELECT COUNT(*) FROM $LIVE AND grain_cardinality IN ('ROW','LINE')
                       AND grain_fact_scope = 'OWN_FACT'");
$txnAll = (int) $one("SELECT COUNT(*) FROM $LIVE AND grain_cardinality IN ('ROW','LINE')");
$txnSM = (int) $one("SELECT COUNT(*) FROM $LIVE AND grain_cardinality IN ('ROW','LINE')
                       AND grain_fact_scope = 'OWN_FACT' AND state_model_ref <> ''");
$txnSMx = (int) $one("SELECT COUNT(*) FROM $LIVE AND grain_cardinality IN ('ROW','LINE')
                        AND grain_fact_scope <> 'OWN_FACT' AND state_model_ref <> ''");
/* حكمُ حقلِ الحالةِ في كياناتِ المعاملات — من `information_schema` */
$smEnum = 0; $smFree = 0; $smNone = 0;
$entL = array();
$rq = $conn->query("SELECT DISTINCT grain_entity FROM $LIVE AND grain_cardinality IN ('ROW','LINE')
                      AND grain_fact_scope = 'OWN_FACT' AND grain_entity <> ''");
while ($rq && $z = $rq->fetch_row()) { $entL[$z[0]] = array(); }
if ($entL) {
    $rq = $conn->query("SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE()");
    while ($rq && $z = $rq->fetch_assoc()) {
        if (isset($entL[$z['TABLE_NAME']])) { $entL[$z['TABLE_NAME']][] = $z; }
    }
    /* ⛔ **الاسمُ الدقيقُ لا الاحتواء**: `status_reason` سببٌ لا حالة */
    foreach ($entL as $tname => $cols) {
        $cand = array();
        foreach ($cols as $cc) {
            $nn = strtolower($cc['COLUMN_NAME']);
            if (in_array($nn, array('status','state','stage','phase'), true)
                || preg_match('~_(status|state|stage|phase)$~', $nn)) { $cand[] = $cc; }
        }
        if (!$cand) { $smNone++; continue; }
        $isEnum = false;
        foreach ($cand as $cc) { if (strtolower($cc['DATA_TYPE']) === 'enum') { $isEnum = true; break; } }
        if ($isEnum) { $smEnum++; } else { $smFree++; }
    }
}
$smDen = $smEnum + $smFree;
$add('مطابقة آلة الحالة لكل معاملة', '100٪', 'MEASURED',
     ($txn ? round($txnSM * 100 / $txn, 1) : 0) . '٪',
     "**$txnSM من $txn** سطحَ معاملةٍ حقيقيّةٍ (`OWN_FACT` بحبّةِ `ROW`/`LINE`) يحمل `state_model_ref`. "
   . "⛔ **و$txnSMx سطحًا تحمله وكيانُها من كِيتٍ مشتركٍ** (‏`guard_denials`) ⇒ **ليست معاملاتٍ بحبّتِها**، "
   . "وكانت تُقرأ بسطًا على مقامٍ $txnAll فتُعطي ٨٫٧٪. "
   . "◆ **وحكمُ الحقلِ مقيسٌ من المخطَّط** (§٧ الخطوة ١٢ «لا حقلَ حالةٍ حرّ»): "
   . "كياناتُ معاملاتٍ **" . count($entL) . "** — محكومٌ `ENUM` **$smEnum** · **حرٌّ $smFree** (‏المستهدَفُ صفر) · "
   . "وبلا حقلِ حالةٍ $smNone ⇒ المحكومُ " . ($smDen ? round($smEnum * 100 / $smDen, 1) : 0) . '٪ من ' . $smDen
   . '. ⛔ **وحملُ المرجعِ ليس مطابقةَ الآلة**');

/* ═══ ٥ · اختبارٌ سالبٌ لفصلِ الواجباتِ الحرج ═══════════════════════════ */
$negFiles = glob($ROOT . '/tools/*negative*.php');
$negN = $negFiles ? count($negFiles) : 0;
$sodN = $tbl('repair01_w4_decisions') ? (int) $one("SELECT COUNT(*) FROM repair01_w4_decisions") : 0;
/* ✔ **والحاجزُ ارتفع** بـ`rpr02_sod_test_registry.php`: المقامُ صار مقيسًا من
   جداولِ `repair01_w*_sod` العشرةِ بمفتاحِ `process_key` — **والعشرون كانت بسطًا
   موهومًا لمقامٍ غيرِ موجود**. ⛔ ولا يُترك نصُّ حاجزٍ مرفوعٍ في تقرير. */
if ($tbl('repair01_sod_test_registry')
    && (int) $one("SELECT COUNT(*) FROM repair01_sod_test_registry") > 0) {
    $sdN  = (int) $one("SELECT COUNT(*) FROM repair01_sod_test_registry");
    $sdB  = (int) $one("SELECT COUNT(*) FROM repair01_sod_test_registry WHERE bound = 1");
    $sdAb = (int) $one("SELECT COUNT(*) FROM repair01_sod_test_registry WHERE enforced_kind = 'ABSENT'");
    $sdPr = (int) $one("SELECT COUNT(*) FROM repair01_sod_test_registry WHERE enforced_kind = 'PROSE'");
    $add('اختبار سالب لفصل الواجبات الحرج', '100٪', 'MEASURED',
         ($sdN ? round($sdB * 100 / $sdN, 1) : 0) . '٪',
         "$sdB من **$sdN** فصلَ واجبٍ حرجٍ يذكره فاحصٌ سالبٌ أو حاجبٌ بمعرِّفِه — والفواحصُ على القرص **$negN** "
       . 'وكانت **بسطًا موهومًا لمقامٍ غيرِ موجود**. '
       . "و`enforced_by` **لا يصلح مفتاحًا**: `PROSE` $sdPr · `ABSENT` $sdAb (W6·W15 بلا عمودٍ أصلًا) "
       . '⇒ فالمفتاحُ `process_key`. ⛔ **وهذا ادّعاءُ حراسةٍ لا إثباتُ حمرة**');
} else {
    $add('اختبار سالب لفصل الواجبات الحرج', '100٪', 'BLOCKED', '—',
         "فواحصُ سالبةٌ على القرص **$negN** · **ولا سجلَّ يربط فاحصًا بفصلِ واجباتٍ بعينِه** ⇒ "
       . 'العددُ لا يصلح بسطًا ولا مقامًا. `blocked at stage: سجلُّ فصلِ الواجباتِ الحرجِ بمعرِّفاتِه` '
       . '— **شغِّلْ `php tools/rpr02_sod_test_registry.php --apply`**');
}

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
/* ✔ **والوصلُ صار بالمعرِّف** بـ`rpr02_cycle_bridge.php` (‏هجرة `2028_01_08`)،
   **والمقامُ صار «المنطبقَ»** بنصِّ §٥·١٠: المعاملةُ ينطبق عليها سيرُ عملٍ،
   والقراءةُ الصِّرفةُ لا تُنشئ حالةً. ⛔ **ولا يُترك نصُّ حاجزٍ مرفوعٍ في تقرير**. */
$cycBridged = 0; $cycAmb = 0; $cycOrphan = 0; $cycHasCol = false;
$ck = $conn->query("SHOW COLUMNS FROM `gov_screen_cycle` LIKE 'bridge_rule'");
if ($ck && $ck->num_rows) {
    $cycHasCol = (int) $one("SELECT COUNT(*) FROM gov_screen_cycle WHERE bridge_rule <> ''") > 0;
}
if ($cycHasCol) {
    $cycAmb    = (int) $one("SELECT COUNT(*) FROM gov_screen_cycle WHERE bridge_rule = 'AMBIGUOUS_DECLARED'");
    $cycOrphan = (int) $one("SELECT COUNT(*) FROM gov_screen_cycle WHERE bridge_rule = 'NO_LIVE_SURFACE'");
    $cycApp    = (int) $one("SELECT COUNT(*) FROM $LIVE AND grain_cardinality IN ('ROW','LINE')
                               AND grain_fact_scope = 'OWN_FACT'");
    $cycHit    = (int) $one("SELECT COUNT(DISTINCT s.screen_id) FROM repair01_screen_registry s
                               JOIN gov_screen_cycle g ON g.screen_id = s.screen_id
                              WHERE s.on_disk = 1 AND s.ownership_verdict <> 'RETIRE'
                                AND s.grain_cardinality IN ('ROW','LINE')
                                AND s.grain_fact_scope = 'OWN_FACT'");
    $cycAll    = (int) $one("SELECT COUNT(DISTINCT s.screen_id) FROM repair01_screen_registry s
                               JOIN gov_screen_cycle g ON g.screen_id = s.screen_id
                              WHERE s.on_disk = 1 AND s.ownership_verdict <> 'RETIRE'");
    $add('جسر معرّف الشاشة بمرحلة دورة العمل', '100٪ من المنطبق', 'MEASURED',
         ($cycApp ? round($cycHit * 100 / $cycApp, 1) : 0) . '٪',
         "**$cycHit من $cycApp** سطحَ **معاملةٍ منطبقة** (`OWN_FACT` بحبّةِ `ROW`/`LINE`) موصولٌ "
       . "**بمعرِّفِ الشاشة** لا باسمِ ملفّ. و§٥·١٠ تقول «كلُّ شاشةٍ **ينطبق عليها سيرُ عمل**» — "
       . "**والقراءةُ الصِّرفةُ لا تُنشئ حالةً** (‏ولها `stage_kind='contextual'` وليست في المقام). "
       . "⛔ **و$cycAmb صفَّ دورةٍ بقي بلا معرِّف**: اسمُ ملفِّه يطابق أكثرَ من سطحٍ حيٍّ "
       . "(`index.php` ثلاثةَ أسطح) — **واختيارُ أحدِها يربط مرحلةً بشاشةٍ ليست هي**. "
       . "و**$cycOrphan** صفًّا لسطحٍ غيرِ قائمٍ يُعلَن ولا يُحذف. "
       . "⚠ **والتضييقُ لا يُقرأ تحسينًا**: الكلُّ الحيُّ $cycAll من $liveN — **فالنقصُ موزَّعٌ حقيقيّ**");
} else {
    $add('جسر معرّف الشاشة بمرحلة دورة العمل', '100٪ من المنطبق', 'MEASURED',
         ($liveN ? round($cyc * 100 / $liveN, 1) : 0) . '٪',
         "$cyc من $liveN سطحًا له صفٌّ في `gov_screen_cycle` — ⛔ **والوصلُ بالمسارِ لا بالمعرِّف**، "
       . 'و§٧ الخطوة ١٣ تشترط الوصلَ **بمعرِّفِ الشاشةِ صراحةً** '
       . '— **شغِّلْ `php tools/rpr02_cycle_bridge.php --apply`**');
}

/* ═══ ٨ · تطابقُ ترتيبِ السايدبارِ ومجموعاتِه مع الملفّ ═══════════════════ */
/* **صار مقيسًا بـ`rpr02_s6_sidebar.php`** — والمقامُ البنودُ الحيّةُ المُصيَّرة.
   ⛔ ولا يُخلط «موضعٌ» بـ«شاشة»: البندُ يتكرَّر بعددِ الأدوارِ التي تراه. */
$navLive = (int) $one("SELECT COUNT(*) FROM nav_items WHERE active = 1");
$grpOk = 0; $ordOk = 0;
$rr = $conn->query("SELECT n.route, n.sort_order, n.role_id, n.group_id, g.name gname,
                           c.group_name cgroup, c.sort_no
                      FROM nav_items n
                      LEFT JOIN link_groups g ON g.id = n.group_id
                      LEFT JOIN nav_canonical c ON LOWER(TRIM(BOTH '/' FROM c.route)) = LOWER(TRIM(BOTH '/' FROM n.route))
                     WHERE n.active = 1");
$nrm = function ($s) {
    $s = preg_replace('~[\x{064B}-\x{0652}\x{0670}\x{0640}]~u', '', (string) $s);
    $s = preg_replace('~[\x{0622}\x{0623}\x{0625}]~u', "\u{0627}", $s);
    $s = preg_replace('~\x{0629}~u', "\u{0647}", $s);
    return trim(preg_replace('~\s+~u', ' ', $s));
};
$grpTot = 0; $bucket = array();
while ($x = $rr->fetch_assoc()) {
    if ($x['cgroup'] !== null && trim((string) $x['cgroup']) !== '') {
        $grpTot++;
        if ($nrm($x['gname']) === $nrm($x['cgroup'])) { $grpOk++; }
    }
    if ($x['sort_no'] !== null && (int) $x['sort_no'] > 0) {
        $bucket[$x['role_id'] . '|' . (int) $x['group_id']][] =
            array((int) $x['sort_order'], (int) $x['sort_no'], (string) $x['route']);
    }
}
$ordTot = 0;
foreach ($bucket as $rows2) {
    $a = $rows2; $b = $rows2;
    usort($a, function ($p, $q2) { return $p[0] === $q2[0] ? strcmp($p[2], $q2[2]) : $p[0] - $q2[0]; });
    usort($b, function ($p, $q2) { return $p[1] === $q2[1] ? strcmp($p[2], $q2[2]) : $p[1] - $q2[1]; });
    foreach ($a as $pos => $z) { $ordTot++; if ($b[$pos][2] === $z[2]) { $ordOk++; } }
}
$sbPct = ($grpTot + $ordTot) ? round(($grpOk + $ordOk) * 100 / ($grpTot + $ordTot), 1) : 0;
/* ⛔ **والمقياسُ كان يقيس المخزنَ لا المُصيَّر** — وعنوانُه «مع **الملف**».
   `nav_items.group_id ⟶ link_groups.name` **لا يُصيَّر أصلًا** لمن له صفٌّ في
   `nav_canonical` أو إعلانٌ في `gov_target_nav` (‏قيس: **إرثٌ مُصيَّرٌ صفرٌ من
   ٧٦٧ بندًا مقارَنًا**)، و`unified_nav.php` يأخذ المجموعةَ من الإعلانِ ثمَّ
   المعتمَد. ⇒ **ففرقُ المخزنِ لا يراه مستخدم**.
   ⛔ **ومقارنةُ المُصيَّرِ بـ`nav_canonical` دَورٌ لا قياس**: المُصيِّرُ يقرأ منه
   فتصير المطابقةُ حتميّةً بالبناء (‏قيست ٢٠٩٦/٢٠٩٦).
   ◆ **والسلطةُ «الملف»** — `repair01_requirements` — والجسرُ إليه **بالمعرِّف**
   عبرَ `repair01_target_universe` (`MATCHED`). وما لا جسرَ له **يُعلَن محجوبًا
   على المصالحةِ ولا يُحسب مطابقًا ولا مخالفًا**. */
$sbOk = 0; $sbBad = 0; $sbNo = 0;
if ($tbl('gov_target_nav') || true) {
    $s2r = array();
    $rq = $conn->query("SELECT screen_id, requirement_id FROM repair01_target_universe
                         WHERE verdict = 'MATCHED' AND screen_id <> '' AND requirement_id <> ''");
    while ($rq && $z = $rq->fetch_row()) { $s2r[$z[0]] = $z[1]; }
    $spg = array();
    $rq = $conn->query("SELECT requirement_id, group_name FROM repair01_requirements WHERE group_name <> ''");
    while ($rq && $z = $rq->fetch_row()) { $spg[$z[0]] = $z[1]; }
    $r2s = array();
    $rq = $conn->query("SELECT screen_id, route FROM repair01_screen_registry WHERE route <> ''");
    while ($rq && $z = $rq->fetch_row()) { $r2s[strtolower(trim($z[1], '/'))] = $z[0]; }
    $cg = array();
    $rq = $conn->query("SELECT route, group_name FROM nav_canonical WHERE group_name <> ''");
    while ($rq && $z = $rq->fetch_row()) { $cg[strtolower(trim((string) $z[0], '/'))] = $z[1]; }
    $dg = array();
    $rq = @$conn->query("SELECT role_id, route, group_ar FROM gov_target_nav");
    while ($rq && $z = $rq->fetch_assoc()) {
        if (strncmp((string) $z['route'], 'GAP:', 4) === 0) { continue; }
        $k = strtolower(trim(preg_replace('~[?#].*$~', '', (string) $z['route']), '/'));
        if ($k !== '') { $dg[(int) $z['role_id'] . '|' . $k] = (string) $z['group_ar']; }
    }
    $nz = function ($s) {
        $s = preg_replace('~[\x{064B}-\x{0652}\x{0670}\x{0640}]~u', '', (string) $s);
        $s = preg_replace('~[\x{0622}\x{0623}\x{0625}]~u', "\u{0627}", $s);
        $s = preg_replace('~\x{0649}~u', "\u{064A}", $s);
        $s = preg_replace('~\x{0629}~u', "\u{0647}", $s);
        $s = preg_replace('~[«»"\'\[\]\-–—/·،,\.]~u', ' ', $s);
        return trim(preg_replace('~\s+~u', ' ', $s));
    };
    $rq = $conn->query("SELECT n.role_id, n.route, g.name AS gname FROM nav_items n
                        LEFT JOIN link_groups g ON g.id = n.group_id WHERE n.active = 1");
    while ($rq && $z = $rq->fetch_assoc()) {
        $b = strtolower(trim(preg_replace('~[?#].*$~', '', (string) $z['route']), '/'));
        $sc = isset($r2s[$b]) ? $r2s[$b] : '';
        $rr = ($sc !== '' && isset($s2r[$sc])) ? $s2r[$sc] : '';
        if ($rr === '' || !isset($spg[$rr])) { $sbNo++; continue; }
        $k = (int) $z['role_id'] . '|' . $b;
        $shown = isset($dg[$k]) ? $dg[$k] : (isset($cg[$b]) ? $cg[$b] : (string) $z['gname']);
        if ($nz($shown) === $nz($spg[$rr])) { $sbOk++; } else { $sbBad++; }
    }
}
$sbDen = $sbOk + $sbBad;
$add('تطابق ترتيب السايدبار ومجموعاته مع الملف', '100٪', 'MEASURED',
     ($sbDen ? round($sbOk * 100 / $sbDen, 1) : 0) . '٪',
     "**المُصيَّرُ** مقابلَ **الملفِّ التصميميّ** (`repair01_requirements` بجسرِ المعرِّف): مطابقٌ **$sbOk** "
   . "من **$sbDen** · ومحجوبٌ على المصالحة `NO_BRIDGE` **$sbNo** — لا سطحَ مطابَقًا فلا متطلبَ يُقاس عليه. "
   . "⛔ **وكان يُقاس على المخزن** (`link_groups`) فأعطى **$sbPct%** بمقامٍ " . ($grpTot + $ordTot)
   . ' — **و`link_groups` لا تُصيَّر أصلًا لمن له معتمَدٌ أو إعلان**، ففرقُ المخزنِ لا يراه مستخدم. '
   . 'والتفصيلُ في `rpr02_s6_sidebar.php`');

/* ═══ ٩ · أسطحٌ تكتب ومالكُ حقيقتِها مجهول ═══════════════════════════════ */
/* ⛔ **والمقامُ `OWN_FACT` وحدَه**: `rpr02_grain_measure.php` يفرّق الخاصَّ من
   المشتركِ ويكتب الخلاصةَ في `grain_fact_scope`. وسطحٌ كيانُه من **كِيتٍ مشترك**
   لا يكتب حقيقةً يملكها، و`INFRA_ONLY` ليست حقيقةَ أعمالٍ أصلًا — و§٥·٩ تقول
   «لكلِّ **حقيقةِ أعمالٍ** مالكٌ قانونيٌّ واحد». **وعدُّها خرقًا هو بعينِه الخرقُ
   الكاذبُ الذي أزاله المقياسُ داخلَ نفسِه ثمَّ عاد هنا طبقةً أعلى.** */
$wAll  = (int) $one("SELECT COUNT(*) FROM $LIVE AND grain_cardinality IN ('ROW','LINE') AND grain_entity <> ''");
$wFact = (int) $one("SELECT COUNT(*) FROM $LIVE AND grain_cardinality IN ('ROW','LINE') AND grain_entity <> '' AND grain_fact_scope = 'OWN_FACT'");
$wUnk  = (int) $one("SELECT COUNT(*) FROM $LIVE AND grain_cardinality IN ('ROW','LINE')
                      AND grain_entity <> '' AND grain_fact_scope = 'OWN_FACT' AND source_of_truth = ''");
$add('أسطح تكتب ومالك حقيقتها مجهول', 'صفر', 'MEASURED', (string) $wUnk,
     "أسطحٌ تكتب **حقيقةَ أعمالٍ تملكها** (`grain_fact_scope='OWN_FACT'`) و`source_of_truth` فارغ — "
   . "والمقامُ **$wFact** من $wAll سطحَ كتابة؛ و" . ($wAll - $wFact) . " مستبعَدٌ **كيانُه من كِيتٍ مشتركٍ أو بنيةٍ صِرفة** "
   . '(‏`ems_post_idempotency` · `guard_denials` …) — ⛔ **ونسبتُه إلى السطحِ خرقٌ كاذب**. **فُتح بقياسِ الحبّة**');

/* ═══ ١٠ · حقيقةٌ واحدةٌ لها مصدران ══════════════════════════════════════ */
$dupTruth = (int) $one("SELECT COUNT(*) FROM (
                  SELECT grain_entity FROM repair01_screen_registry
                   WHERE on_disk = 1 AND ownership_verdict <> 'RETIRE'
                     AND grain_entity <> '' AND grain_cardinality IN ('ROW','LINE') AND grain_fact_scope = 'OWN_FACT'
                   GROUP BY grain_entity HAVING COUNT(*) > 1) t");
$dupEx = (string) $one("SELECT GROUP_CONCAT(e SEPARATOR ' · ') FROM (
                  SELECT grain_entity e FROM repair01_screen_registry
                   WHERE on_disk = 1 AND ownership_verdict <> 'RETIRE'
                     AND grain_entity <> '' AND grain_cardinality IN ('ROW','LINE') AND grain_fact_scope = 'OWN_FACT'
                   GROUP BY grain_entity HAVING COUNT(*) > 1
                   ORDER BY COUNT(*) DESC LIMIT 5) t");
/* ✔ **والمحسومُ بقياسٍ يخرج من المقام** — `rpr02_canonical_source.php` يحكم
   بأربعِ قواعدَ ويكتب المصدرَ القانونيَّ بشاهدِه. ⛔ **والخارجُ محسومٌ لا مُقصًى**:
   `resolved=1` يوجب `canonical_screen` بقاعدةٍ صلبةٍ في القاعدةِ نفسِها. */
$csRes = 0; $csWit = '';
if ($tbl('repair01_canonical_source')) {
    $csRes = (int) $one("SELECT COUNT(*) FROM repair01_canonical_source WHERE resolved = 1");
    $csX   = (int) $one("SELECT COUNT(*) FROM repair01_canonical_source WHERE rule_code = 'N3_CROSS_OWNER_NEEDS_CONTRACT'");
    $csT   = (int) $one("SELECT COUNT(*) FROM repair01_canonical_source WHERE rule_code = 'N4_TIE_DECLARED'");
    $csWit = " · وحُسم **$csRes** بمصدرٍ قانونيٍّ مسمًّى (`N1` هويّةُ اسمِ الأثر · `N2` المُعلِنُ يغلب المُستنتَج)"
           . " · والباقي **$csX** عبورُ إداراتٍ **يحتاج عقدًا لا ترجيحًا** (‏وهو بعينِه مقامُ **#١١**)"
           . " و**$csT** تعادلٌ داخلَ إدارةٍ واحدة. ⛔ **وترجيحُ أحدِ الكاتبَين هنا يُخفي #١١ ولا يحلُّها**";
}
/* ✔ **ومن ليس كاتبًا لا يُعدُّ مصدرًا**: `rpr02_cross_contract.php` يقيس جُملَ
   الكتابةِ الخامّةِ في مدى السطحِ الخاصّ، فسطحٌ **يُصرِّح بجدولِه ولا يكتب عليه**
   (`X0`) ليس طرفًا في ازدواجِ مصدر — ولولا هذا التمييزُ لَعُدَّ التصريحُ كتابةً. */
$cxNot = 0;
if ($tbl('repair01_cross_contract')) {
    $cxNot = (int) $one("SELECT COUNT(DISTINCT entity) FROM repair01_cross_contract
                           WHERE entity_verdict = 'NOT_A_DUPLICATE'");
}
$dupTruth = $dupTruth - $csRes - $cxNot;
$add('حقيقة واحدة لها مصدران', 'صفر', 'MEASURED', (string) $dupTruth,
     'كيانٌ **يملكه** أكثرُ من واجهةٍ حيّةٍ بحبّةِ سجلٍّ — أعلاها: ' . ($dupEx === '' ? '—' : $dupEx)
   . ' · والمقامُ `OWN_FACT` وحدَه ⇒ **الكيانُ المكتوبُ من كِيتٍ مشتركٍ لا يُعدُّ مصدرًا ثانيًا**' . $csWit . '. **فُتح بقياسِ الحبّة**');

/* ═══ ١١ · كتابةٌ تعبر حدودَ إدارةٍ بلا عقد ══════════════════════════════
   مالكُ الكيانِ = الإدارةُ التي تكتبه بأقلِّ عددِ أسطحٍ… لا. **مالكُه = مالكُ
   السطحِ الوحيدِ الذي يكتبه**؛ فإن كتبه أكثرُ من إدارةٍ فذاك العبورُ نفسُه. */
$cross = (int) $one("SELECT COUNT(*) FROM (
              SELECT grain_entity FROM repair01_screen_registry
               WHERE on_disk = 1 AND ownership_verdict <> 'RETIRE'
                 AND grain_entity <> '' AND grain_cardinality IN ('ROW','LINE') AND grain_fact_scope = 'OWN_FACT'
               GROUP BY grain_entity HAVING COUNT(DISTINCT owner_code) > 1) t");
/* ✔ **والعقدُ صار مقيسًا** — ولا يُترك نصُّ «غيرُ مقيسٍ» في تقرير. */
if ($tbl('repair01_cross_contract')
    && (int) $one("SELECT COUNT(*) FROM repair01_cross_contract") > 0) {
    $cxB = (int) $one("SELECT COUNT(DISTINCT entity) FROM repair01_cross_contract
                         WHERE entity_verdict = 'DIRECT_WRITE_BREACH' AND owner_code <> ''");
    $cxC = (int) $one("SELECT COUNT(DISTINCT entity) FROM repair01_cross_contract
                         WHERE entity_verdict = 'CONTRACTED'");
    $cxR = (int) $one("SELECT COUNT(*) FROM repair01_cross_contract WHERE writer_verdict = 'X2_RAW_DIRECT'");
    $cxM = (int) $one("SELECT COUNT(*) FROM repair01_cross_contract WHERE writer_verdict = 'X1_SERVICE_MEDIATED'");
    $cxZ = (int) $one("SELECT COUNT(*) FROM repair01_cross_contract WHERE writer_verdict = 'X0_NO_MEASURED_WRITE'");
    /* ⛔ **والمقياسُ اسمُه «بلا عقد»** — فكيانٌ حُكم عليه `CONTRACTED` **هو
       بعينِه العبورُ المتعاقَدُ عليه**، وإبقاؤه في العددِ يقيس العبورَ لا
       العبورَ-بلا-عقد. ⇒ يُطرح كما يُطرح `NOT_A_DUPLICATE`، **وشاهدُ كلٍّ في
       `repair01_cross_contract.witness`**. */
    $cross = $cross - $cxNot - $cxC;
    $add('كتابة تعبر حدود إدارة بلا عقد', 'صفر', 'MEASURED', (string) $cross,
         "كيانٌ تكتبه إدارتان فأكثرُ — **والعقدُ صار مقيسًا** بـ`rpr02_cross_contract.php`: "
       . "كاتبٌ بكتابةٍ خامّةٍ **$cxR** · بخدمةٍ أو ناشرٍ بلا خامٍّ **$cxM** · مُصرِّحٌ بلا كتابةٍ **$cxZ** (‏ليس كاتبًا). "
       . "وكياناتٌ **بعقدٍ مقيس $cxC** ⇒ ⛔ **صفرُ عبورٍ متعاقَدٍ عليه في الشجرةِ كلِّها**، "
       . "وكلُّ عبورٍ حقيقيٍّ يكتب خامًّا. **فالرقمُ صار خرقًا مقيسًا لا سقفَ خرق**");
} else {
    $add('كتابة تعبر حدود إدارة بلا عقد', 'صفر', 'MEASURED', (string) $cross,
         'كيانٌ تكتبه إدارتان فأكثرُ بحبّةِ سجلٍّ — ⛔ **والعقدُ غيرُ مقيسٍ بعدُ**، '
       . 'فالرقمُ **سقفُ الخرقِ لا الخرقُ** — **شغِّلْ `php tools/rpr02_cross_contract.php --apply`**');
}

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
/* ⛔ **والعددُ لا ينخفض بالتسجيل** — المقياسُ يشترط تبريرًا **معتمَدًا**،
   والتسجيلُ يرفع **جهلَ السبب** لا يُغني عن الاعتماد. **وما تغيّر هو طبيعةُ
   الباقي**: كان عملَ تحليلٍ فصار فعلَ اعتماد. */
if ($tbl('repair01_platform_surface')
    && (int) $one("SELECT COUNT(*) FROM repair01_platform_surface") > 0) {
    $psN  = (int) $one("SELECT COUNT(*) FROM repair01_platform_surface");
    $psB  = (int) $one("SELECT COUNT(*) FROM repair01_platform_surface
                         WHERE bind_rule <> 'P3_UNBOUND_DECLARED'");
    $psA  = (int) $one("SELECT COUNT(*) FROM repair01_platform_surface WHERE approval_state = 'APPROVED'");
    $psS  = (int) $one("SELECT COUNT(*) FROM repair01_platform_surface WHERE bind_rule = 'P1_DECLARED_SCOPE_OWNER'");
    $psC  = (int) $one("SELECT COUNT(DISTINCT capability_code) FROM repair01_platform_surface
                         WHERE capability_code <> ''");
    $add('شاشة PLATFORM بلا تبرير منصّي معتمد', 'صفر', 'MEASURED', (string) ($plat - $psA),
         "أسطحٌ `PLATFORM_SHARED` حيّةٌ **$plat** — **ومسجَّلةٌ الآن بمعرِّفِها وقاعدةِ ظهورِها $psB من $psN** "
       . "بـ`rpr02_platform_register.php`: منها **$psS** مالكُها **نطاقٌ مُعلَنٌ** (‏فـ`PLATFORM_SHARED` فيها "
       . "**صفةُ ظهورٍ عابرٍ للأدوارِ لا فراغُ ملكيّة**) والباقي مربوطٌ بـ**$psC** قدرةً من ثمانِ `AMD-01` §٤·٧. "
       . "⛔ **والتسجيلُ ليس اعتمادًا**: معتمَدٌ **$psA** — و§٤·٤ يشترط `معتمَدًا`. "
       . '⇒ **فالباقي فعلُ اعتمادٍ لا عملُ تحليل**');
} else {
    $add('شاشة PLATFORM بلا تبرير منصّي معتمد', 'صفر', 'MEASURED', (string) $plat,
         "أسطحٌ `PLATFORM_SHARED` حيّةٌ **$plat** · وسجلُّ المنصّةِ فيه **$platReg** قدرةً ⇒ "
       . '**لا واحدةَ منها مسجَّلةٌ بمعرِّفِها وقاعدةِ ظهورِها** (§٤·٤ الشرط الرابع)، '
       . 'فالأربعةُ مجتمعةً غيرُ مستوفاةٍ ولا واحدةٌ تُعفى '
       . '— **شغِّلْ `php tools/rpr02_platform_register.php --apply`**');
}

/* ═══ ١٤ · ملفُّ مكتبةٍ مسجَّلٌ شاشةً — وقاعدةٌ مانعةٌ مفعَّلة ═══════════ */
/* ⛔ **والمفتاحُ يُقاس قبلَ أن يُصدَّق صفرُه**: `screen_file` يحمل **اسمَ الملفِّ
   وحدَه** (`Payments.php`) لا مسارَه، فالبحثُ فيه عن `vendor/` **صفرٌ من مفردةٍ
   لا وجودَ لها** — أخضرُ كاذبٌ بامتياز. والمسارُ في `route`، وبه يظهر الصفّان
   ثمَّ يُصفّيهما حكمُ `RETIRE` المسجَّل ⇒ **صفرٌ صادقٌ عن مقامٍ حقيقيٍّ اثنين**. */
$vendAll = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry WHERE route LIKE 'vendor/%'");
$vend = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                     WHERE route LIKE 'vendor/%' AND ownership_verdict <> 'RETIRE'");
$rule = (int) $one("SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
                     WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME LIKE '%vendor%'");
$barTool = is_file($ROOT . '/tools/repair01_w2_gate.php') || is_file($ROOT . '/tools/rpr02a_gates.php');
$add('ملف مكتبة مسجَّل شاشةً — وقاعدة مانعة مفعَّلة', 'صفر · مفعَّلة', 'MEASURED',
     $vend . ' · ' . (($rule > 0 || $barTool) ? 'حاجبٌ قائم' : 'لا قاعدة'),
     "ملفّاتُ `vendor/` **بالمسارِ لا باسمِ الملفّ**: $vendAll · وغيرُ الموسومةِ `RETIRE`: **$vend** "
   . '(‏والموسومةُ حكمٌ مسجَّلٌ في §١١ لا عطبٌ مفتوح) · '
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
    /* ✔ **وصار قياسَ المعروضِ لا قياسَ السجلّ** — والفرقُ ليس في العددِ بل في
       **تركيبتِه**: صفُّ `MERGED` في السجلِّ **لا يُصيَّر أصلًا** فليس اسمًا معروضًا،
       ومسارٌ مُصيَّرٌ **بلا صفِّ تسميةٍ ألبتّة** لا يظهر في عدِّ السجلِّ وهو معروضٌ
       بلا اعتماد. ⇒ **فالعدُّ على المُصيَّرِ يُصيب مَن يراه المستخدم**.
       ◆ و**صيغُ العرضِ** (`?view=`/`?tab=`) تحتفظ بوسمِها بقرارِ المُصيِّرِ نفسِه
         (`$isVariant`) ⇒ **لا تُحاسَب على تسميةٍ معتمدةٍ لأساسٍ غيرِها**. */
    $navStore = (int) $one("SELECT COUNT(*) FROM nav_canonical WHERE COALESCE(status,'') <> 'APPROVED'");
    $cn2 = array();
    $rr2 = $conn->query("SELECT route, status FROM nav_canonical");
    while ($rr2 && $z = $rr2->fetch_assoc()) { $cn2[strtolower(trim((string) $z['route'], '/'))] = $z['status']; }
    $navBad = 0; $navPend = 0; $navNoRow = 0; $navVar = 0; $navRt = array();
    $rr2 = $conn->query("SELECT route FROM nav_items WHERE active = 1");
    while ($rr2 && $z = $rr2->fetch_row()) {
        $raw = (string) $z[0];
        if (strpbrk($raw, '#?') !== false) { $navVar++; continue; }
        $b = strtolower(trim(preg_replace('~[?#].*$~', '', $raw), '/'));
        if (!isset($cn2[$b])) { $navBad++; $navNoRow++; $navRt[$b] = 1; continue; }
        if ($cn2[$b] !== 'APPROVED') { $navBad++; $navPend++; $navRt[$b] = 1; }
    }
    $navWit = "**بنودٌ مُصيَّرةٌ** باسمٍ غيرِ معتمَد: **$navBad** على " . count($navRt) . " مسارًا — "
            . "منها **$navPend** تسميتُها `PENDING_OWNER` (‏**فعلُ اعتمادٍ**) و**$navNoRow** **بلا صفِّ تسميةٍ ألبتّة** (‏فجوةُ سجلّ). "
            . "و$navVar صيغةَ عرضٍ (`?view=`) تحتفظ بوسمِها **بقرارِ المُصيِّرِ نفسِه** فلا تُحاسَب. "
            . "⛔ **وكان يُقاس على السجلِّ فيعطي $navStore من $navTot** — والفرقُ في التركيبةِ لا العدد: "
            . 'صفُّ `MERGED` **لا يُصيَّر** ومسارٌ **بلا صفٍّ** لا يظهر في عدِّ السجلِّ وهو معروضٌ بلا اعتماد';
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
