<?php
/**
 * tools/rpr02_state_model_bind.php — `RPR-02` #٤ · ربطُ المعاملةِ بآلةِ حالتِها
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — `RPR-02` **#٤**: «مطابقةُ آلةِ الحالةِ لكلِّ معاملة»
 *   هدفُها **١٠٠٪**، والمقيسُ **صفرٌ من ١٢٧** سطحَ معاملةٍ حقيقيّةٍ يحمل
 *   `state_model_ref`.
 *
 * ◆ **والاكتشافُ الذي يفسّر الصفرَ**: آلاتُ الحالةِ **مؤلَّفةٌ فعلًا** — مئةٌ
 *   واثنتان في عشرةِ جداولِ موجات (`repair01_w6..w15_states`) بانتقالاتِها
 *   وشروطِها ومستنداتِها وبوّاباتِها. **لكنّها مؤلَّفةٌ باسمِ الكيانِ المفاهيميِّ
 *   المفرد** (`exec_approval`) **والسجلُّ يحمل اسمَ الجدولِ المقيسِ الجمعَ**
 *   (`exec_approvals`) ⇒ **فالجسرُ يسقط على اصطلاحِ تسميةٍ لا على غياب**.
 *   ⛔ **وصفرٌ سببُه جسرٌ مكسورٌ يُقرأ «لا آلاتِ حالةٍ» وهو كذبٌ على النفس.**
 *
 * ◆ **وثلاثُ قواعدَ للجسر — ⛔ ولا رابعةَ تُخترع**:
 *   **M1 · `EXACT`**    — اسمُ الكيانِ في جدولِ الحالاتِ **هو** اسمُ الجدولِ.
 *   **M2 · `PLURAL`**   — يطابقه بعدَ نزعِ `s`/`es` الأخيرةِ من اسمِ الجدول.
 *   **M3 · `SINGULAR`** — يطابقه بعدَ إلحاقِ `s` باسمِ الجدول.
 *   ⛔ **وشرطٌ صلبٌ فوقَ الثلاث**: الاسمُ المقابلُ **جدولٌ قائمٌ في المخطَّط**.
 *   ⇒ فلا يُربط سطحٌ بآلةِ حالةِ كيانٍ لا وجودَ له.
 *
 * ⛔ **وما لا آلةَ له لا يُلفَّق له مرجع**: `AUTHORING_BACKLOG` — ويُعلَن
 *   **باسمِه وعددِ أسطحِه**. فتأليفُ آلةِ حالةٍ (‏مالكُ الانتقالِ وشرطُه المسبقُ
 *   ومستندُه الرسميُّ وبوّابةُ اعتمادِه وقاعدتا إعادةِ الفتحِ والتصحيح) **حكمُ
 *   أعمالٍ يُؤلَّف ولا يُقاس** — و§٥ المحظور ④ يمنع تلفيقَه.
 *   ◆ **والمرجعُ الأجوفُ أسوأُ من غيابِه**: يُقرأ خُضرةً وهو فراغ.
 *
 * التشغيل:
 *   php tools/rpr02_state_model_bind.php [--apply] [--md] [--selftest]
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

/* ═══ ① القاعدةُ مفصولةً — كي تُختبر وحدَها ═══════════════════════════════ */
/**
 * @param string $table  اسمُ الجدولِ المقيسِ من الأثر
 * @param array  $models اسمُ الكيانِ في جداولِ الحالاتِ ⇒ الموجة
 * @return array{rule:string, key:string}  والخالي يعني: لا آلة
 */
function sm_bridge($table, array $models)
{
    $t = strtolower(trim((string) $table));
    if ($t === '') { return array('rule' => '', 'key' => ''); }
    if (isset($models[$t])) { return array('rule' => 'M1_EXACT', 'key' => $t); }
    if (substr($t, -2) === 'es' && isset($models[substr($t, 0, -2)])) {
        return array('rule' => 'M2_PLURAL', 'key' => substr($t, 0, -2));
    }
    if (substr($t, -1) === 's' && isset($models[substr($t, 0, -1)])) {
        return array('rule' => 'M2_PLURAL', 'key' => substr($t, 0, -1));
    }
    if (isset($models[$t . 's'])) { return array('rule' => 'M3_SINGULAR', 'key' => $t . 's'); }
    return array('rule' => '', 'key' => '');
}

/* ═══ ② الاختبارُ السالبُ — يُصيب الطرفَين ولا يمرُّ بمفردةٍ فريدة ═══════ */
if ($SELF) {
    $fail = 0;
    $M = array('exec_approval' => 'W14', 'contracts' => 'W08', 'acc_account_recon' => 'W11');
    $a = sm_bridge('exec_approvals', $M);
    if ($a['rule'] !== 'M2_PLURAL' || $a['key'] !== 'exec_approval') { echo "  X الجمعُ لم يُجسَر\n"; $fail++; }
    $b = sm_bridge('acc_account_recon', $M);
    if ($b['rule'] !== 'M1_EXACT') { echo "  X المطابقُ حرفًا لم يُعرَف\n"; $fail++; }
    $c = sm_bridge('contract', $M);
    if ($c['rule'] !== 'M3_SINGULAR' || $c['key'] !== 'contracts') { echo "  X المفردُ لم يُجسَر\n"; $fail++; }
    /* **الكاسر**: مفردةٌ فريدةٌ لا ترد إلّا هنا — ولا آلةَ لها */
    if (sm_bridge('zzq_unique_no_model_probe', $M)['rule'] !== '') {
        echo "  X كيانٌ بلا آلةٍ رُبط\n"; $fail++;
    }
    /* ⛔ **والاحتواءُ ليس جسرًا**: `exec_approval_log` ليس `exec_approval` */
    if (sm_bridge('exec_approval_log', $M)['rule'] !== '') {
        echo "  X الاحتواءُ عُدَّ جسرًا\n"; $fail++;
    }
    /* ⛔ **ولا يُجسَر الفراغُ** */
    if (sm_bridge('', $M)['rule'] !== '') { echo "  X الفراغُ جُسِر\n"; $fail++; }
    /* ⛔ **ونزعُ `s` لا يُنتج اسمًا فارغًا** */
    if (sm_bridge('s', $M)['rule'] !== '') { echo "  X حرفٌ واحدٌ أنتج جسرًا\n"; $fail++; }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n"
               : "\n🟢 الفحصُ الذاتيُّ تامٌّ — الجمعُ يُجسَر والاحتواءُ لا يمرّ\n";
    exit($fail ? 1 : 0);
}

/* ═══ ③ نافذةُ القياس ════════════════════════════════════════════════════ */
$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
if (!$snap && $APPLY) { exit("⛔ **لا نافذةَ قياسٍ مفتوحة** — جمِّدْ أوّلًا.\n"); }
$sid = $snap ? $snap['snapshot_id'] : 'DRY';

/* ═══ ④ آلاتُ الحالةِ المؤلَّفةُ — من جداولِ الموجاتِ لا من نصِّ وثيقة ════ */
$WAVES = array('w6', 'w7', 'w8', 'w9', 'w10', 'w11', 'w12', 'w13', 'w14', 'w15');
$models = array(); $trans = array(); $forbid = array();
foreach ($WAVES as $w) {
    $t = 'repair01_' . $w . '_states';
    $q = @$conn->query("SELECT entity, allowed FROM `$t`");
    if (!$q) { continue; }
    while ($z = $q->fetch_assoc()) {
        $k = strtolower(trim((string) $z['entity']));
        if ($k === '') { continue; }
        if (!isset($models[$k])) { $models[$k] = strtoupper($w); $trans[$k] = 0; $forbid[$k] = 0; }
        if ((int) $z['allowed'] === 1) { $trans[$k]++; } else { $forbid[$k]++; }
    }
}

/* ◆ **ومصدرٌ ثانٍ للآلاتِ لا يُهمَل**: موجاتُ `W03`/`W04`/`W05` أُلِّفت آلاتُها
   **في وثيقتِها نصًّا** لا في جدولِ موجةٍ (‏فتلك الموجاتُ سبقت جدولَ الحالات).
   ⇒ تُقرأ رؤوسُها: `## …  ·  \`جدول.عمود\`` — **والكيانُ هو الجدولُ**.
   ⛔ **ولا يُقرأ التقريرُ بدلَ المخزنِ حيث المخزنُ موجود**: هذا لِما لا مخزنَ له. */
foreach (array('W03', 'W04', 'W05') as $w) {
    $f = $ROOT . '/docs/REPAIR01_20260823/plan/' . $w . '_STATE_MACHINES.md';
    $src = (string) @file_get_contents($f);
    if ($src === '') { continue; }
    if (preg_match_all('~^##[^\n]*?`([a-z_][a-z0-9_]*)\.[a-z_][a-z0-9_]*`~mu', $src, $m)) {
        foreach ($m[1] as $k) {
            $k = strtolower($k);
            if (isset($models[$k])) { continue; }
            $models[$k] = $w; $trans[$k] = 0; $forbid[$k] = 0;
        }
    }
    /* ⚠ **وعنوانٌ من الدرجةِ الثالثةِ قد يحمل آلةً كاملةً** — والقراءةُ كانت
       تلتقط `##` وحدَه بصيغةِ `` `جدول.عمود` ``، فسقط `persons` (‏W03 §٦-أ:
       «سجلُّ الهويةِ `persons`») **وله آلةٌ مؤلَّفةٌ تامّة**: حالاتٌ وانتقالاتٌ
       مسموحةٌ وممنوعةٌ صراحةً ومالكُ انتقالٍ وقاعدةُ تصحيح.
       ⇒ **صفرٌ سببُه جسرٌ لا يقرأ درجةَ عنوانٍ ليس غيابَ آلة** ([[finish-round-closure]]).
       ⛔ **ولا يُقبَل مجرَّدُ ذِكرٍ**: كتلةُ العنوانِ يجب أن تحمل **الأركانَ
          الثلاثةَ معًا** — «الحالات» و«الانتقالاتُ المسموحة» و«مالكُ الانتقال»
          — وإلّا فهي إحالةٌ أو خبرٌ لا تأليف. **والمرجعُ الأجوفُ أسوأُ من غيابِه**:
          فـ«مخازنُ CMP-03» في W04 §٩ تذكر ثمانيةَ جداولٍ **وتؤجّلها إلى W07**،
          ولو قُبل الذِّكرُ لارتفع المقياسُ بثمانيةِ مراجعَ جوفاء. */
    foreach (preg_split('~^(?=###?\s)~mu', $src) as $blk) {
        if (!preg_match('~^###\s[^\n]*?`([a-z_][a-z0-9_]{2,})`~u', $blk, $mm)) { continue; }
        $k = strtolower($mm[1]);
        if (isset($models[$k])) { continue; }
        if (strpos($blk, 'الحالات') === false
            || strpos($blk, 'الانتقالاتُ المسموحة') === false
            || strpos($blk, 'مالكُ الانتقال') === false) { continue; }
        $models[$k] = $w; $trans[$k] = 0; $forbid[$k] = 0;
        $trans[$k] += preg_match_all('~⇒|→~u', $blk);
        $forbid[$k] += (strpos($blk, 'الممنوعةُ صراحةً') !== false) ? 1 : 0;
    }
    /* عدُّ الانتقالاتِ من صفوفِ الجدولِ في الوثيقة — شاهدٌ لا زينة */
    foreach (explode("\n## ", $src) as $blk) {
        if (!preg_match('~`([a-z_][a-z0-9_]*)\.[a-z_][a-z0-9_]*`~u', $blk, $mm)) { continue; }
        $k = strtolower($mm[1]);
        if (!isset($trans[$k])) { continue; }
        $trans[$k] += preg_match_all('~^\|\s*`[^`]+`\s*\|\s*`[^`]+`\s*\|~mu', $blk);
        $forbid[$k] += preg_match_all('~^-\s+`[^`]+`\s*⇐~mu', $blk);
    }
}

/* جداولُ المخطَّطِ — ⛔ ولا يُربط سطحٌ بكيانٍ لا جدولَ له */
$TBL = array();
$q = $conn->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()");
while ($q && ($z = $q->fetch_row())) { $TBL[strtolower($z[0])] = 1; }

/* ═══ الجسرُ الرابع M4 — سلسلةُ المتطلبِ الصريحة (⛔ وليس اسمًا رابعًا يُخترع):
   المتطلبُ المطابقُ للسطحِ في الكونِ (`MATCHED`) رُبط بآلتِه بقناةِ مرجعٍ
   صريحٍ محكومةٍ (`repair01_requirements.sm_model_ref` — ستُّ قنواتٍ كلُّها
   استشهادٌ لا تسمية)، والأمرُ الحاكمُ يوجب التطبيقَ الرجعيَّ على المبنيّ.
   فالسطحُ يرث مرجعَ متطلبِه **بالسلسلةِ لا بالاسم** — وشاهدُه يذكرها. */
$reqChain = array();
$q = $conn->query("SELECT u.screen_id, r.requirement_id, r.sm_model_ref
                     FROM repair01_target_universe u
                     JOIN repair01_requirements r ON r.requirement_id = u.requirement_id
                    WHERE u.verdict = 'MATCHED' AND COALESCE(r.sm_model_ref,'') <> ''");
while ($q && ($z = $q->fetch_assoc())) {
    /* سطحٌ طابقه متطلبان مربوطان بآلتَين مختلفتَين = التباسٌ يُعلَن لا يُحسم */
    $sidk = (string) $z['screen_id'];
    if (isset($reqChain[$sidk]) && $reqChain[$sidk]['ref'] !== (string) $z['sm_model_ref']) {
        $reqChain[$sidk] = array('ref' => '', 'req' => $reqChain[$sidk]['req'] . '+' . $z['requirement_id']);
        continue;
    }
    $reqChain[$sidk] = array('ref' => (string) $z['sm_model_ref'], 'req' => (string) $z['requirement_id']);
}

/* ═══ ⑤ المدى — أسطحُ المعاملاتِ الحقيقيّة ══════════════════════════════ */
$LIVE = "repair01_screen_registry WHERE on_disk = 1 AND ownership_verdict <> 'RETIRE'
         AND grain_cardinality IN ('ROW','LINE') AND grain_fact_scope = 'OWN_FACT'";
$rows = array();
$q = $conn->query("SELECT screen_id, canonical_label_ar, owner_code, grain_entity, state_model_ref
                     FROM $LIVE ORDER BY grain_entity, screen_id");
while ($q && ($z = $q->fetch_assoc())) { $rows[] = $z; }

$stat = array('M1_EXACT' => 0, 'M2_PLURAL' => 0, 'M3_SINGULAR' => 0, 'M4_CHAIN' => 0, 'BACKLOG' => 0, 'NO_TABLE' => 0);
$plan = array(); $gapEnt = array(); $hitEnt = array();
/* غلافُ الجسرِ الرابع — يُجرَّب حيث سقطت جسورُ الاسمِ الثلاثة */
$m4 = function ($x) use (&$reqChain, &$stat, &$plan, $sid) {
    $k = (string) $x['screen_id'];
    if (!isset($reqChain[$k]) || $reqChain[$k]['ref'] === '') { return false; }
    $ch = $reqChain[$k];
    $stat['M4_CHAIN']++;
    $plan[] = array('id' => $x['screen_id'], 'ref' => $ch['ref'],
        'ent' => strtolower(trim((string) $x['grain_entity'])), 'rule' => 'M4_CHAIN',
        'wit' => 'M4_CHAIN · متطلبُ هذا السطحِ المطابقُ في الكونِ (`' . $ch['req'] . '` — حكمُ '
               . '`MATCHED` بشاهدِه) **مربوطٌ بآلتِه بقناةِ مرجعٍ صريحٍ محكومةٍ** '
               . '(`repair01_requirements.sm_model_ref` = `' . $ch['ref'] . '` وشاهدُ قناتِه في '
               . '`sm_witness`)، والأمرُ الحاكمُ يوجب التطبيقَ الرجعيَّ على المبنيِّ — '
               . '**فالسطحُ يرث المرجعَ بالسلسلةِ لا بالاسم** · لقطة ' . $sid);
    return true;
};
foreach ($rows as $x) {
    $ent = strtolower(trim((string) $x['grain_entity']));
    if ($ent === '' || !isset($TBL[$ent])) {
        if ($m4($x)) { continue; }
        $stat['NO_TABLE']++;
        continue;
    }
    $b = sm_bridge($ent, $models);
    if ($b['rule'] === '') {
        if ($m4($x)) { continue; }
        $stat['BACKLOG']++;
        $gapEnt[$ent] = isset($gapEnt[$ent]) ? $gapEnt[$ent] + 1 : 1;
        continue;
    }
    $stat[$b['rule']]++;
    $hitEnt[$ent] = $b;
    $ref = $models[$b['key']] . '_STATE_MACHINES#' . $b['key'];
    $plan[] = array('id' => $x['screen_id'], 'ref' => $ref, 'ent' => $ent, 'rule' => $b['rule'],
        'wit' => $b['rule'] . ' · كيانُ هذا السطحِ المقيسُ `' . $ent . '` **له آلةُ حالةٍ مؤلَّفةٌ** في '
               . (strncmp($models[$b['key']], 'W0', 2) === 0
                  ? '`' . $models[$b['key']] . '_STATE_MACHINES.md` **نصًّا** (‏موجةٌ سبقت جدولَ الحالات) باسمِ `'
                  : '`repair01_' . strtolower($models[$b['key']]) . '_states` باسمِ `') . $b['key'] . '`: '
               . '**' . $trans[$b['key']] . '** انتقالًا مسموحًا و**' . $forbid[$b['key']] . '** ممنوعًا '
               . 'مُسبَّبًا. ' . ($b['rule'] === 'M1_EXACT'
                   ? '**والاسمان متطابقان حرفًا.**'
                   : '⚠ **والاسمان تفرّقا باصطلاحِ الإفرادِ والجمعِ لا بالمعنى** — '
                     . 'والآلةُ مؤلَّفةٌ بالمفاهيميِّ والسجلُّ بالجدولِ المقيس، '
                     . '**فالصفرُ السابقُ كان جسرًا مكسورًا لا غيابَ آلة**.')
               . ' · لقطة ' . $sid);
}

/* ═══ ⑥ العرض ════════════════════════════════════════════════════════════ */
$N = count($rows);
$bound = $stat['M1_EXACT'] + $stat['M2_PLURAL'] + $stat['M3_SINGULAR'] + $stat['M4_CHAIN'];
echo "\n═══ `RPR-02` #٤ — ربطُ المعاملةِ بآلةِ حالتِها ═══\n";
printf("  اللقطة: %s · أسطحُ المعاملاتِ الحقيقيّة: **%d**\n", $sid, $N);
printf("  آلاتُ حالةٍ مؤلَّفةٌ في جداولِ الموجات: **%d** كيانًا\n\n", count($models));
echo "  ── قواعدُ الجسرِ الثلاث ──\n";
printf("     M1 `EXACT`     %4d سطحًا — الاسمان متطابقان حرفًا\n", $stat['M1_EXACT']);
printf("     M2 `PLURAL`    %4d سطحًا — الجدولُ جمعٌ والآلةُ مفردٌ\n", $stat['M2_PLURAL']);
printf("     M3 `SINGULAR`  %4d سطحًا — الجدولُ مفردٌ والآلةُ جمعٌ\n", $stat['M3_SINGULAR']);
printf("     M4 `CHAIN`     %4d سطحًا — يرث مرجعَ متطلبِه المطابقِ المربوطِ بقناةٍ صريحة\n", $stat['M4_CHAIN']);
printf("     ⛔ `BACKLOG`   %4d سطحًا على **%d** كيانًا — **لا آلةَ مؤلَّفة**\n",
       $stat['BACKLOG'], count($gapEnt));
printf("     ◆ كيانٌ لا جدولَ له %2d سطحًا — ⛔ **ولا يُربط بما لا وجودَ له**\n", $stat['NO_TABLE']);
printf("\n  ⇒ المقياسُ **#٤ %s٪** (‏%d من %d)\n",
       $N ? round($bound * 100 / $N, 1) : 0, $bound, $N);
echo "  ◆ **وتأليفُ آلةِ حالةٍ حكمُ أعمالٍ يُؤلَّف ولا يُقاس** — مالكُ الانتقالِ\n";
echo "    وشرطُه المسبقُ ومستندُه وبوّابتُه وقاعدتا إعادةِ الفتحِ والتصحيح.\n";
echo "    ⛔ **والمرجعُ الأجوفُ أسوأُ من غيابِه**: يُقرأ خُضرةً وهو فراغ.\n";

if ($hitEnt) {
    echo "\n  ── الكياناتُ المجسورة ──\n";
    foreach ($hitEnt as $en => $b) {
        printf("   %-34s %-12s ⇐ %-30s %s\n", $en, $b['rule'], $b['key'], $models[$b['key']]);
    }
}
if ($gapEnt) {
    arsort($gapEnt);
    echo "\n  ── كياناتٌ بلا آلةٍ مؤلَّفة (أعلاها أسطحًا) ──\n";
    $i = 0;
    foreach ($gapEnt as $en => $n) {
        printf("   %-40s %d سطحًا\n", $en, $n);
        if (++$i >= 15) { printf("   … و%d كيانًا غيرُها\n", count($gapEnt) - 15); break; }
    }
}

/* ═══ ⑦ التثبيت ══════════════════════════════════════════════════════════ */
if ($APPLY) {
    /* ⛔ **وتضييقُ المقامِ يوجب سحبَ ما كُتب خارجَه** — مرجعٌ كُتب بقاعدةٍ لم
       تعُدْ تنطبق أسوأُ من الفراغ: الفراغُ يُعلن نفسَه والمتقادمُ يُقرأ حقًّا. */
    $ret = 0;
    if ($conn->query("UPDATE repair01_screen_registry SET state_model_ref = ''
                       WHERE state_model_ref <> ''
                         AND state_model_ref NOT LIKE '%#%'
                         AND NOT (on_disk = 1 AND ownership_verdict <> 'RETIRE'
                                  AND grain_cardinality IN ('ROW','LINE')
                                  AND grain_fact_scope = 'OWN_FACT')")) {
        $ret = $conn->affected_rows;
    }
    $n = 0;
    foreach ($plan as $x) {
        $ok = $conn->query("UPDATE repair01_screen_registry
              SET state_model_ref = '" . $e($x['ref']) . "',
                  verdict_rule = CONCAT(COALESCE(NULLIF(verdict_rule,''),''), ' | SM:" . $e($x['rule']) . "')
            WHERE screen_id = '" . $e($x['id']) . "'");
        if (!$ok) { exit("✘ تعذّر ربطُ {$x['id']}: {$conn->error}\n"); }
        $n++;
    }
    $now = (int) $conn->query("SELECT COUNT(*) FROM $LIVE AND state_model_ref <> ''")->fetch_row()[0];
    if ($ret) { printf("\n  ⚠ **سُحب مرجعُ %d سطحٍ** خرج من مقامِ المعاملاتِ — ولا يُترك رقمٌ متقادم\n", $ret); }
    printf("\n  ✔ رُبط **%d** سطحًا بآلةِ حالتِه بشاهدِها · وأُعيدت القراءة: **%d**\n", $n, $now);
}

if ($MD) {
    $o  = "# `RPR-02` #٤ — ربطُ المعاملةِ بآلةِ حالتِها\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `" . $sid . "`\n\n";
    $o .= "## الصفرُ كان جسرًا مكسورًا لا غيابَ آلة\n\n";
    $o .= "آلاتُ الحالةِ **مؤلَّفةٌ فعلًا**: **" . count($models) . "** كيانًا في عشرةِ جداولِ موجاتٍ ";
    $o .= "بانتقالاتِها وشروطِها ومستنداتِها وبوّاباتِها. **لكنّها مؤلَّفةٌ باسمِ الكيانِ المفاهيميِّ ";
    $o .= "المفرد** والسجلُّ يحمل **اسمَ الجدولِ المقيسِ الجمعَ** ⇒ فالجسرُ يسقط على اصطلاحِ تسميةٍ ";
    $o .= "لا على غياب. ⛔ **وصفرٌ سببُه جسرٌ مكسورٌ يُقرأ «لا آلاتِ حالةٍ» وهو كذبٌ على النفس.**\n\n";
    $o .= "## قواعدُ الجسرِ الثلاث\n\n| القاعدة | الأسطح | المعنى |\n|---|---:|---|\n";
    $o .= "| `M1_EXACT` | **" . $stat['M1_EXACT'] . "** | الاسمان متطابقان حرفًا |\n";
    $o .= "| `M2_PLURAL` | **" . $stat['M2_PLURAL'] . "** | الجدولُ جمعٌ والآلةُ مفردٌ |\n";
    $o .= "| `M3_SINGULAR` | **" . $stat['M3_SINGULAR'] . "** | الجدولُ مفردٌ والآلةُ جمعٌ |\n";
    $o .= "| ⛔ `BACKLOG` | **" . $stat['BACKLOG'] . "** | لا آلةَ مؤلَّفةً — على " . count($gapEnt) . " كيانًا |\n\n";
    $o .= "⛔ **وشرطٌ صلبٌ فوقَ الثلاث**: الاسمُ المقابلُ **جدولٌ قائمٌ في المخطَّط** — ";
    $o .= "فلا يُربط سطحٌ بآلةِ حالةِ كيانٍ لا وجودَ له.\n\n";
    $o .= "⇒ المقياسُ **#٤ " . ($N ? round($bound * 100 / $N, 1) : 0) . "٪** (‏$bound من $N).\n\n";
    $o .= "## الباقي — **تأليفٌ لا قياس**\n\n";
    $o .= "تأليفُ آلةِ حالةٍ يعني لكلِّ انتقال: **مالكَه · شرطَه المسبقَ · مستندَه الرسميَّ · ";
    $o .= "بوّابةَ اعتمادِه · قاعدةَ إعادةِ الفتحِ · قاعدةَ التصحيح** — وممنوعَه الصريحَ مُسبَّبًا. ";
    $o .= "**وهذه أحكامُ أعمالٍ تُؤلَّف ولا تُقاس**، و§٥ المحظور ④ يمنع تلفيقَها. ";
    $o .= "◆ **والمرجعُ الأجوفُ أسوأُ من غيابِه**: يُقرأ خُضرةً وهو فراغ.\n\n";
    $o .= "| الكيان | أسطحُه |\n|---|---:|\n";
    foreach ($gapEnt as $en => $n2) { $o .= "| `" . $en . "` | " . $n2 . " |\n"; }
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/RPR02_S4_STATE_MODELS.md', $o);
    echo "\n✔ كُتب: docs/REPAIR01_20260823/RPR02_S4_STATE_MODELS.md\n";
}
