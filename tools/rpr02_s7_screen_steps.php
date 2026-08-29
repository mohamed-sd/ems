<?php
/**
 * tools/rpr02_s7_screen_steps.php — `RPR-02` §٧ · الشاشةُ بثمانيَ عشرةَ خطوة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — §٧: ثمانيَ عشرةَ خطوةً لكلِّ شاشة. والخطوةُ الأولى
 *   (الحبّة) نُفِّذت بـ`rpr02_grain_measure.php`. **وهذا السجلُّ يقيس البقيّةَ
 *   على المقامِ نفسِه** — سطحًا سطحًا لا عيّنةً.
 *
 * ◆ **وعشرُ خطواتٍ تُقاس اليومَ بالعُدّةِ القائمة · وثمانٍ محجوبةٌ بمرحلةٍ
 *   مسمّاة** — ⛔ **ولا تُعرض المحجوبةُ صفرًا**: صفرٌ يُقرأ نجاحًا و«محجوب»
 *   يُقرأ دَينًا (‏درسُ §١٢ نفسِه).
 *
 * ◆ **وحاجزان بنيويّان يُسمَّيان بدقّةٍ لأنَّهما يفسّران ستًّا من الثماني**:
 *   ① **الجانبُ المبنيُّ من الحقولِ غيرُ مسجَّلٍ إلّا في ٤٤ سطحًا من ٦٢١**:
 *      `repair01_fields` يحمل ٧٬٥٩١ حقلًا تصميميًّا على ٤٣٣ هدفًا، و`gov_field_class`
 *      المبنيُّ ٦٠٥ حقولٍ على ٤٤ سطحًا بمفتاحٍ سبيكةٍ لا مسار. ⛔ **و`gov_field_trace`
 *      ليس الجانبَ المبنيَّ** بل تتبُّعُ مصنَّفاتٍ تصميميّة. ⇒ الخطواتُ ٣ و٥ و٦ و٧
 *      و١٥ كلُّها **حقليّةٌ** فتقف عليه، ومخرَجُها **قياسُ الحقولِ من الأثرِ نفسِه**.
 *   ② **`gov_screen_cycle` بلا عمودِ `screen_id` أصلًا** — والخطوةُ ١٣ تشترط
 *      الوصلَ **«بمعرِّفِ الشاشةِ صراحةً لا بالمسارِ ولا بالاسم»**. فالوصلُ
 *      القائمُ بالمسارِ **لا يستوفيها ولو غطّى كلَّ سطح**، وهذا عيبُ مخطَّطٍ
 *      لا عيبُ ملء.
 *
 * ◆ **وقياسُ الوجودِ ليس قياسَ المطابقة** — ويُقال في كلِّ خطوةٍ يقاس فيها
 *   حملُ حقلٍ لا صحّةُ محتواه (١٠ و١٢ مثلًا). ⛔ فلا يُقرأ «يحمل مرجعًا» على
 *   أنّه «آلةُ حالةٍ مطابقةٌ للملفّ».
 *
 * التشغيل:
 *   php tools/rpr02_s7_screen_steps.php [--md] [--list] [--selftest]
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
$LIST = in_array('--list', $argv, true);
$SELF = in_array('--selftest', $argv, true);

$q = function ($sql) use ($conn) {
    $r = $conn->query($sql);
    if (!$r) { fwrite(STDERR, "✘ استعلامٌ سقط: {$conn->error}\n   $sql\n"); exit(2); }
    return $r;
};
$one = function ($sql) use ($q) { $x = $q($sql)->fetch_row(); return $x ? $x[0] : null; };
$col = function ($t) use ($conn) {
    $out = array();
    $r = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '"
                      . $conn->real_escape_string($t) . "'");
    if ($r) { while ($x = $r->fetch_row()) { $out[strtolower($x[0])] = 1; } }
    return $out;
};

$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
$sid = $snap ? $snap['snapshot_id'] : 'DRY';

/* ═══ ① المقامُ الحيّ ═══════════════════════════════════════════════════ */
$rows = array();
$r = $q("SELECT screen_id, screen_file, route, owner_code, canonical_label_ar,
                surface_kind, grain_entity, grain_cardinality, grain_multi,
                guard_kind, action_guard, permission_policy, state_model_ref, source_of_truth
           FROM repair01_screen_registry
          WHERE on_disk = 1 AND ownership_verdict <> 'RETIRE'
          ORDER BY screen_id");
while ($x = $r->fetch_assoc()) { $rows[] = $x; }
$N = count($rows);

/* كياناتٌ تكتبها إدارتان فأكثر — الخطوةُ ٤ */
$foreign = array();
$r = $q("SELECT grain_entity FROM repair01_screen_registry
          WHERE on_disk = 1 AND ownership_verdict <> 'RETIRE'
            AND grain_entity <> '' AND grain_cardinality IN ('ROW','LINE')
          GROUP BY grain_entity HAVING COUNT(DISTINCT owner_code) > 1");
while ($x = $r->fetch_row()) { $foreign[$x[0]] = 1; }

/* مالكو الكياناتِ — لبنوّةِ الخطوةِ ٢ */
$entityOwned = array();
$r = $q("SELECT DISTINCT grain_entity FROM repair01_screen_registry
          WHERE on_disk = 1 AND ownership_verdict <> 'RETIRE' AND grain_entity <> ''");
while ($x = $r->fetch_row()) { $entityOwned[$x[0]] = 1; }

/* مستندُ المرحلةِ من مصفوفةِ الدورة — الخطوةُ ٩ */
$cycleDoc = array();
$r = $q("SELECT screen_file, MAX(COALESCE(output_doc,'') <> '') d FROM gov_screen_cycle GROUP BY screen_file");
while ($x = $r->fetch_assoc()) { $cycleDoc[$x['screen_file']] = (int) $x['d']; }

/* القارئون المستقلّون للصلاحية — الخطوةُ ١٤ */
$PERM_TABLES = 'role_permissions|report_role_permissions|permission_templates|role_permission_templates';
$directFile = array();
foreach ($rows as $s) {
    $p = $ROOT . '/' . ltrim(str_replace('\\', '/', (string) $s['route']), '/');
    if (!is_file($p)) { continue; }
    $src = (string) @file_get_contents($p);
    if ($src === '') { continue; }
    $reads  = (bool) preg_match('~\b(FROM|JOIN)\s+`?(' . $PERM_TABLES . ')`?~i', $src);
    $writes = (bool) preg_match('~\b(INSERT\s+INTO|UPDATE|DELETE\s+FROM|REPLACE\s+INTO)\s+`?('
            . $PERM_TABLES . ')`?~i', $src);
    if ($reads && !$writes) { $directFile[$s['screen_id']] = 1; }
}

/* كتلةُ التدقيقِ في جدولِ الحبّة — الخطوةُ ١١ */
$auditOf = array();
foreach ($rows as $s) {
    $e = (string) $s['grain_entity'];
    if ($e === '' || isset($auditOf[$e])) { continue; }
    $c = $col($e);
    $who  = isset($c['created_by']) || isset($c['posted_by']) || isset($c['recorded_by']) || isset($c['user_id']);
    $when = isset($c['created_at']) || isset($c['posted_at']) || isset($c['recorded_at']);
    $stat = isset($c['state']) || isset($c['status']) || isset($c['status_label']);
    $auditOf[$e] = ($who && $when && $stat) ? 1 : 0;
}

/* ═══ ② الخطواتُ الثمانيَ عشرة ═══════════════════════════════════════════ */
$STEP = array(
    1  => 'تصحيح الحبّة — ولا سطح يجمع حبّتين',
    2  => 'فصل الأم عن البنود',
    3  => 'ترتيب الحقول على الدورة المستندية',
    4  => 'نزع الملكية الغريبة',
    5  => 'إضافة الحقل الناقص بحكمه',
    6  => 'تحويل المستورد إلى قراءة',
    7  => 'تحويل المشتق إلى محسوب',
    8  => 'مرجع المصدر لكل إسقاط',
    9  => 'ربط المستند — لا اعتماد بلا مستند',
    10 => 'ربط الاعتماد بمحرك السلطة',
    11 => 'كتلة التدقيق — المنشئ والوقت والحالة',
    12 => 'آلة الحالة — لا حقل حالة حرّ',
    13 => 'جسر دورة العمل بمعرّف الشاشة',
    14 => 'الصلاحيات وإظهار الحقول',
    15 => 'التحقّق والقوائم المحكومة',
    16 => 'الأحداث والآثار بعقد مسجَّل',
    17 => 'الاستثناء والاختبار السالب',
    18 => 'نقاء اللغة والتحقّق البشري',
);
/* ⛔ **وسببُ الحجبِ صُحِّح بعدَ قياسِه**: `repair01_fields.requirement_id`
   و`gov_field_trace.req_id` **مفتاحان متطابقان**، فالعلّةُ ليست مفتاحًا مفقودًا.
   والعلّةُ الحقيقيّةُ: `gov_field_trace` **تتبُّعُ مصنَّفاتٍ تصميميّةٍ لا جانبٌ
   مبنيّ**، والجانبُ المبنيُّ `gov_field_class` **لا يغطّي إلّا ٤٤ سطحًا من ٦٢١**
   ومفتاحُه سبيكةٌ (`acc_my_day`) لا تطابق مسارًا ولا ملفًّا.
   ⇒ فالمخرَجُ **قياسُ حقولِ المبنيِّ من الأثرِ نفسِه** كما قيست الحبّة. */
$FIELD_KEY = 'blocked at stage: قياسُ حقولِ المبنيِّ من الأثرِ نفسِه — والمسجَّلُ ٤٤ سطحًا من ٦٢١';
$BLOCK = array(
    3  => $FIELD_KEY, 5 => $FIELD_KEY, 6 => $FIELD_KEY, 7 => $FIELD_KEY, 15 => $FIELD_KEY,
    13 => 'blocked at stage: `gov_screen_cycle` بلا عمودِ `screen_id` — عيبُ مخطَّطٍ لا عيبُ ملء',
    16 => 'blocked at stage: مصالحةُ مفرداتِ المُنتِجِ والمستهلك (`RPR-03` §٤)',
    17 => 'blocked at stage: سجلٌّ يربط فاحصًا سالبًا بسطحٍ بعينِه',
);
$S = array();
foreach ($STEP as $i => $t) { $S[$i] = array('ok' => 0, 'need' => 0, 'na' => 0, 'ex' => array()); }

$mark = function ($i, $v, $label) use (&$S) {
    $S[$i][$v]++;
    if ($v === 'need' && count($S[$i]['ex']) < 8) { $S[$i]['ex'][] = $label; }
};

foreach ($rows as $s) {
    $id = $s['screen_id'];
    $lb = $id . ' ' . mb_substr((string) $s['canonical_label_ar'], 0, 30);
    $ent = (string) $s['grain_entity'];
    $writes = in_array($s['grain_cardinality'], array('ROW', 'LINE'), true) && $ent !== '';

    /* ١ — الحبّة */
    if ($ent === '') { $mark(1, 'need', $lb . ' — بلا حبّةٍ مقيسة'); }
    elseif ((int) $s['grain_multi'] === 1) { $mark(1, 'need', $lb . ' — يجمع حبّتين'); }
    else { $mark(1, 'ok', $lb); }

    /* ٢ — الأمُّ والبنود: حبّةُ بندٍ بلا سطحٍ يملك أمَّها */
    if ($s['grain_cardinality'] !== 'LINE') { $mark(2, 'na', $lb); }
    else {
        $stem = preg_replace('~(_lines|_items|_details|_rows|_entries|_line|_item)$~', '', $ent);
        $has = isset($entityOwned[$stem]) || isset($entityOwned[$stem . 's']) || isset($entityOwned[rtrim($stem, 's')]);
        $has ? $mark(2, 'ok', $lb) : $mark(2, 'need', $lb . ' — بنودٌ بلا سطحٍ يملك أمَّها «' . $stem . '»');
    }

    /* ٤ — الملكيةُ الغريبة */
    if (!$writes) { $mark(4, 'na', $lb); }
    elseif (isset($foreign[$ent])) { $mark(4, 'need', $lb . ' — «' . $ent . '» تكتبه إدارتان فأكثر'); }
    else { $mark(4, 'ok', $lb); }

    /* ٨ — مرجعُ المصدرِ للإسقاط */
    if ($s['surface_kind'] !== 'PROJECTION') { $mark(8, 'na', $lb); }
    elseif (trim((string) $s['source_of_truth']) !== '') { $mark(8, 'ok', $lb); }
    else { $mark(8, 'need', $lb . ' — إسقاطٌ بلا مرجعِ مصدر'); }

    /* ٩ — مستندُ المرحلة */
    $f = (string) $s['screen_file'];
    if (!isset($cycleDoc[$f])) { $mark(9, 'need', $lb . ' — لا صفَّ دورةٍ لملفِّه'); }
    elseif ($cycleDoc[$f] === 1) { $mark(9, 'ok', $lb); }
    else { $mark(9, 'need', $lb . ' — صفُّ دورةٍ بلا مستندِ مخرَج'); }

    /* ١٠ — ربطُ الاعتمادِ بمحرِّكِ السلطة */
    trim((string) $s['action_guard']) !== ''
        ? $mark(10, 'ok', $lb) : $mark(10, 'need', $lb . ' — بلا حارسِ فعلٍ مُعلَن');

    /* ١١ — كتلةُ التدقيقِ في جدولِ الحبّة */
    if (!$writes) { $mark(11, 'na', $lb); }
    elseif (!empty($auditOf[$ent])) { $mark(11, 'ok', $lb); }
    else { $mark(11, 'need', $lb . ' — «' . $ent . '» بلا مُنشئٍ ووقتٍ وحالةٍ معًا'); }

    /* ١٢ — آلةُ الحالة */
    if (!$writes) { $mark(12, 'na', $lb); }
    elseif (trim((string) $s['state_model_ref']) !== '') { $mark(12, 'ok', $lb); }
    else { $mark(12, 'need', $lb . ' — معاملةٌ بلا مرجعِ آلةِ حالة'); }

    /* ١٤ — الصلاحياتُ: حارسٌ خادميٌّ ولا قراءةَ قرارٍ مستقلّة */
    $g = trim((string) $s['guard_kind']) !== '';
    if ($g && !isset($directFile[$id])) { $mark(14, 'ok', $lb); }
    else {
        $mark(14, 'need', $lb . ($g ? ' — يقرأ قرارَ الصلاحيةِ بنفسِه' : ' — بلا حارسٍ خادميّ'));
    }

    /* ١٨ — نقاءُ اللغة: رمزٌ تقنيٌّ في المسمَّى المعروض */
    $L = (string) $s['canonical_label_ar'];
    if ($L === '') { $mark(18, 'need', $lb . ' — بلا مسمًّى معروض'); }
    elseif (preg_match('~[A-Za-z]{3,}|\.php|_|/~', $L)) { $mark(18, 'need', $lb . ' — رمزٌ تقنيٌّ في المسمَّى'); }
    else { $mark(18, 'ok', $lb); }
}

/* ═══ ③ الفحصُ الذاتيّ ═══════════════════════════════════════════════════ */
if ($SELF) {
    $fail = 0;
    if ($N < 100) { echo "  X المقامُ $N — قراءةٌ عمياء\n"; $fail++; }
    foreach ($STEP as $i => $t) {
        if (isset($BLOCK[$i])) { continue; }
        $sum = $S[$i]['ok'] + $S[$i]['need'] + $S[$i]['na'];
        if ($sum !== $N) { echo "  X الخطوةُ $i مجموعُها $sum والمقامُ $N\n"; $fail++; }
    }
    foreach ($BLOCK as $i => $w) {
        if (mb_strpos($w, 'blocked at stage') === false) { echo "  X الخطوةُ $i محجوبةٌ بلا مرحلة\n"; $fail++; }
    }
    /* **الكاسرُ**: جدولٌ لا وجودَ له يجب أن يعود بلا أعمدة */
    if ($col('zzq_unique_probe_table')) { echo "  X جدولٌ وهميٌّ أعاد أعمدةً\n"; $fail++; }
    if (!$col('repair01_screen_registry')) { echo "  X جدولٌ قائمٌ لم يُعِد أعمدةً — القارئُ أعمى\n"; $fail++; }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n"
               : "\n🟢 الفحصُ الذاتيُّ تامٌّ — كلُّ خطوةٍ مقيسةٍ مجموعُها المقام، ولكلِّ محجوبةٍ مرحلتُها\n";
    exit($fail ? 1 : 0);
}

/* ═══ ④ العرض ═══════════════════════════════════════════════════════════ */
echo "\n═══ `RPR-02` §٧ — الشاشةُ بثمانيَ عشرةَ خطوة ═══\n";
printf("  اللقطة %s · **الأسطحُ الحيّةُ %d** — سطحًا سطحًا لا عيّنةً\n\n", $sid, $N);
printf("  %-3s %-42s %8s %8s %8s\n", '#', 'الخطوة', 'مستقيم', 'يحتاج', 'لا ينطبق');
echo '  ' . str_repeat('─', 76) . "\n";
$measured = 0; $blocked = 0; $needTot = 0;
foreach ($STEP as $i => $t) {
    if (isset($BLOCK[$i])) {
        $blocked++;
        printf("  %-3d %-42s %s\n", $i, mb_substr($t, 0, 42), '⛔ محجوبة');
        continue;
    }
    $measured++; $needTot += $S[$i]['need'];
    printf("  %-3d %-42s %8d %8d %8d\n", $i, mb_substr($t, 0, 42),
           $S[$i]['ok'], $S[$i]['need'], $S[$i]['na']);
}
printf("\n  **مقيسةٌ %d خطوةً · محجوبةٌ %d · ومواضعُ تحتاج تصحيحًا في المقيسةِ مجتمعةً %d**\n",
       $measured, $blocked, $needTot);

echo "\n  ── المحجوباتُ بمراحلِها — ⛔ ولا تُعرض صفرًا ──\n";
foreach ($BLOCK as $i => $w) { printf("     %-3d %-40s `%s`\n", $i, mb_substr($STEP[$i], 0, 40), $w); }

if ($LIST) {
    foreach ($STEP as $i => $t) {
        if (isset($BLOCK[$i]) || !$S[$i]['ex']) { continue; }
        echo "\n  ── خطوة $i · شواهدُ ──\n";
        foreach ($S[$i]['ex'] as $x) { echo "     · $x\n"; }
    }
}

echo "\n────────────────────────────────────────────────────────────\n";
echo "⛔ **وقياسُ الوجودِ ليس قياسَ المطابقة**: الخطوتان ١٠ و١٢ تقيسان **حملَ**\n";
echo "  مرجعٍ لا صحّةَ محتواه — فلا يُقرأ «يحمل مرجعًا» أنّه «مطابقٌ للملفّ».\n";

if ($MD) {
    $o  = "# `RPR-02` §٧ — الشاشةُ بثمانيَ عشرةَ خطوة\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `" . $sid . "`\n\n";
    $o .= "المقامُ **" . $N . "** سطحًا حيًّا — سطحًا سطحًا لا عيّنةً. **مقيسةٌ " . $measured
        . " خطوةً · محجوبةٌ " . $blocked . "**.\n\n";
    $o .= "| # | الخطوة | مستقيم | يحتاج | لا ينطبق |\n|---|---|---:|---:|---:|\n";
    foreach ($STEP as $i => $t) {
        $o .= '| ' . $i . ' | ' . $t . ' | ';
        $o .= isset($BLOCK[$i]) ? '⛔ | محجوبة | — |' . "\n"
            : $S[$i]['ok'] . ' | **' . $S[$i]['need'] . '** | ' . $S[$i]['na'] . " |\n";
    }
    $o .= "\n## المحجوباتُ بمراحلِها\n\n| # | الخطوة | المرحلة |\n|---|---|---|\n";
    foreach ($BLOCK as $i => $w) { $o .= '| ' . $i . ' | ' . $STEP[$i] . ' | `' . $w . "` |\n"; }
    $o .= "\n⛔ **وحاجزان بنيويّان يفسّران ستًّا من الثماني**: **الجانبُ المبنيُّ من الحقولِ\n";
    $o .= "غيرُ مسجَّلٍ إلّا في ٤٤ سطحًا من ٦٢١** (‏و`gov_field_trace` تتبُّعُ مصنَّفاتٍ لا\n";
    $o .= "جانبٌ مبنيّ) · و`gov_screen_cycle`\n";
    $o .= "**بلا عمودِ `screen_id` أصلًا** والخطوةُ ١٣ تشترط الوصلَ بالمعرِّفِ صراحةً.\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/RPR02_S7_STEPS.md', $o);
    echo "\n✔ كُتب docs/REPAIR01_20260823/RPR02_S7_STEPS.md\n";
}
