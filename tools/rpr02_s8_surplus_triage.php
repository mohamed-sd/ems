<?php
/**
 * tools/rpr02_s8_surplus_triage.php — `RPR-02` §٨ · فرزُ الفائضِ قبلَ بناءِ الناقص
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — §٨: *«الفائضُ مئتان وسبعةٌ وستّون سطحًا — **وليس كلُّه
 *   تراثًا حميدًا. وما فيه مصدرٌ مزدوجٌ أو كاتبٌ من إدارةٍ أخرى أساسٌ فاسدٌ
 *   يُبنى فوقه**»*. وصنفان: **حرجٌ يُعالَج قبلَ بناءِ أيِّ سطحٍ جديد** · وحميدٌ
 *   لموجةِ تصفيةٍ لاحقةٍ بحكمٍ موثَّق.
 *
 * ◆ **وخمسةُ معاييرَ للحرج — وكلُّها صارت مقيسةً بقياسِ الحبّة** (§٧·١):
 *   `DUAL_SOURCE`   — كيانٌ **تكتبه أكثرُ من واجهةٍ حيّة** ⇒ حقيقةٌ بمصدرَين.
 *   `FOREIGN_WRITER`— كيانٌ **تكتبه إدارتان فأكثر** ⇒ كتابةٌ تعبر الحدود.
 *   `GUARD_BYPASS`  — سطحٌ **يكتب وحارسُه الخادميُّ غائب**.
 *   `OWNER_UNKNOWN` — سطحٌ **يكتب ومصدرُ حقيقتِه غيرُ مُعلَن**.
 *   `CODE_NOT_DEPT` — ملكيّتُه **رمزُ منصّةٍ لا إدارةٌ من السبعَ عشرة**.
 *   ⛔ **وأربعةٌ منها لم تكن تُقاس قبلَ الحبّة أصلًا** — فلا يُقرأ صفرُها
 *      السابقُ نظافةً، بل **غيابَ مقياس**.
 *
 * ◆ **والمقامُ يُقاس ولا يُنقل**: «الفائضُ» = **سطحٌ حيٌّ لا يُطالِب به هدفٌ
 *   محكوم**. وخطُّ الأمرِ يقول ٢٦٧، ⛔ **والمقيسُ اليومَ غيرُه** لأنَّ الكونَ
 *   الهدفيَّ أُعيد فصلُه — والفرقُ خبرٌ يُعلَن لا يُسوّى.
 *
 * ◆ **ويُبدأ بأحكامِهم لا من الصفر** (§٨): سجلُّهم صنّف أسطحًا «متقاعدًا»
 *   و«تبويبًا في أبيه». **فتُقرأ أحكامُهم وتُعرض**، وما لم يُراجَع يُعلَن
 *   `INHERITED_UNREVIEWED` — ⛔ **ولا يُعدُّ مقبولًا بمجرَّدِ وجودِه**.
 *
 * ⛔ **وسطحٌ واحدٌ قد يحمل معيارَين** — فيُعدُّ **مرّةً واحدةً في المقامِ**
 *    وتُعرض معاييرُه كلُّها. وجمعُ المعاييرِ عددًا يضخّم الفائضَ الحرج.
 *
 * التشغيل:
 *   php tools/rpr02_s8_surplus_triage.php [--md] [--list] [--selftest]
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

$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
$sid = $snap ? $snap['snapshot_id'] : 'DRY';

$LIVE = "on_disk = 1 AND ownership_verdict <> 'RETIRE'";

/* ═══ ① المقامُ — الفائضُ مقيسًا ═══════════════════════════════════════ */
$liveN = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry WHERE $LIVE");
$rows = array();
$r = $q("SELECT screen_id, screen_file, route, owner_code, canonical_label_ar,
                grain_entity, grain_cardinality, grain_fact_scope, guard_kind, source_of_truth,
                ownership_verdict, ghost_verdict, ghost_why
           FROM repair01_screen_registry
          WHERE $LIVE
            AND screen_id NOT IN (SELECT screen_id FROM repair01_target_universe
                                   WHERE screen_id <> '')
          ORDER BY screen_id");
while ($x = $r->fetch_assoc()) { $rows[] = $x; }
$surplus = count($rows);

/* ═══ ② الكياناتُ ذاتُ المصدرَين وعابراتُ الحدود — من الحبّةِ المقيسة ═══ */
$dual = array(); $foreign = array();
$r = $q("SELECT grain_entity, COUNT(*) n, COUNT(DISTINCT owner_code) u
           FROM repair01_screen_registry
          WHERE $LIVE AND grain_entity <> '' AND grain_cardinality IN ('ROW','LINE')
            AND grain_fact_scope = 'OWN_FACT'
          GROUP BY grain_entity");
while ($x = $r->fetch_assoc()) {
    if ((int) $x['n'] > 1) { $dual[$x['grain_entity']] = (int) $x['n']; }
    if ((int) $x['u'] > 1) { $foreign[$x['grain_entity']] = (int) $x['u']; }
}

/* ═══ ③ الفحصُ الذاتيّ ═══════════════════════════════════════════════════ */
if ($SELF) {
    $fail = 0;
    if ($liveN < 100) { echo "  X المقامُ الحيُّ $liveN — قراءةٌ عمياء\n"; $fail++; }
    if ($surplus < 1) { echo "  X الفائضُ صفرٌ — إمّا القراءةُ عمياءُ وإمّا الكونُ فارغ\n"; $fail++; }
    if ($surplus > $liveN) { echo "  X الفائضُ $surplus أكبرُ من الحيِّ $liveN\n"; $fail++; }
    /* **الكاسرُ**: كيانٌ لا وجودَ له يجب ألّا يُحكَم بمصدرَين */
    if (isset($dual['zzq_unique_entity_probe'])) { echo "  X كيانٌ وهميٌّ حُكم بمصدرَين\n"; $fail++; }
    if (count($dual) < 1) { echo "  X صفرُ كيانٍ بمصدرَين — والمقياسُ لا يميّز\n"; $fail++; }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n"
               : "\n🟢 الفحصُ الذاتيُّ تامٌّ — المقامُ محصورٌ والمعاييرُ تميّز\n";
    exit($fail ? 1 : 0);
}

/* ═══ ④ الفرز ═══════════════════════════════════════════════════════════ */
$CRIT = array('DUAL_SOURCE' => 0, 'FOREIGN_WRITER' => 0, 'GUARD_BYPASS' => 0,
              'OWNER_UNKNOWN' => 0, 'CODE_NOT_DEPT' => 0);
$critical = array(); $benign = array(); $inherited = 0;
foreach ($rows as $s) {
    $why = array();
    /* ⛔ **والكاتبُ من يكتب حقيقةً يملكها** — `grain_fact_scope='OWN_FACT'`.
       فسطحٌ كيانُه من **كِيتٍ مشتركٍ** أو **بنيةٍ صِرفةٍ** ليس كاتبَ حقيقةِ أعمال،
       وعدُّه حرجًا **هو بعينِه الخرقُ الكاذبُ** الذي أزاله قياسُ الحبّةِ داخلَ نفسِه
       (‏٢٦٥ خرقًا كاذبًا) ثمَّ عاد في اللوحةِ وهنا. **والقاعدةُ واحدةٌ في المواضعِ كلِّها.** */
    $writes = in_array($s['grain_cardinality'], array('ROW', 'LINE'), true)
           && $s['grain_entity'] !== '' && $s['grain_fact_scope'] === 'OWN_FACT';
    if ($writes && isset($dual[$s['grain_entity']]))    { $why[] = 'DUAL_SOURCE';    $CRIT['DUAL_SOURCE']++; }
    if ($writes && isset($foreign[$s['grain_entity']])) { $why[] = 'FOREIGN_WRITER'; $CRIT['FOREIGN_WRITER']++; }
    if ($writes && trim((string) $s['guard_kind']) === '')      { $why[] = 'GUARD_BYPASS';  $CRIT['GUARD_BYPASS']++; }
    if ($writes && trim((string) $s['source_of_truth']) === '') { $why[] = 'OWNER_UNKNOWN'; $CRIT['OWNER_UNKNOWN']++; }
    if ($s['ownership_verdict'] === 'PLATFORM_SHARED')          { $why[] = 'CODE_NOT_DEPT'; $CRIT['CODE_NOT_DEPT']++; }
    if (trim((string) $s['ghost_verdict']) !== '') { $inherited++; }
    if ($why) { $s['why'] = $why; $critical[] = $s; } else { $benign[] = $s; }
}

/* ═══ ⑤ العرض ═══════════════════════════════════════════════════════════ */
echo "\n═══ `RPR-02` §٨ — فرزُ الفائضِ قبلَ بناءِ الناقص ═══\n";
printf("  اللقطة %s · الأسطحُ الحيّةُ **%d** · المُطالَبُ بها هدفًا **%d**\n",
       $sid, $liveN, $liveN - $surplus);
printf("  ⇒ **الفائضُ مقيسًا: %d** · وخطُّ الأمرِ يقول ٢٦٧ — **والفرقُ خبرٌ يُعلَن**\n", $surplus);
echo "     (‏لأنَّ الكونَ الهدفيَّ أُعيد فصلُه في §٤·٢، فالمقامُ تحرَّك بحقٍّ لا بسهو)\n";

printf("\n  ── الصنفان ──\n");
printf("     ⛔ **فائضٌ حرجٌ: %d** — يُعالَج **قبلَ بناءِ أيِّ سطحٍ جديد**\n", count($critical));
printf("     ◆ فائضٌ حميدٌ: %d — لموجةِ تصفيةٍ لاحقةٍ بحكمٍ موثَّق\n", count($benign));

echo "\n  ── معاييرُ الحرجِ الخمسة — والسطحُ قد يحمل أكثرَ من واحد ──\n";
$TXT = array(
    'DUAL_SOURCE'    => 'كيانٌ تكتبه أكثرُ من واجهةٍ حيّة — حقيقةٌ بمصدرَين',
    'FOREIGN_WRITER' => 'كيانٌ تكتبه إدارتان فأكثرُ — كتابةٌ تعبر الحدود',
    'GUARD_BYPASS'   => 'يكتب وحارسُه الخادميُّ غائب',
    'OWNER_UNKNOWN'  => 'يكتب ومصدرُ حقيقتِه غيرُ مُعلَن',
    'CODE_NOT_DEPT'  => 'ملكيّتُه رمزُ منصّةٍ لا إدارة',
);
foreach ($CRIT as $k => $v) { printf("     %-16s %4d — %s\n", $k, $v, $TXT[$k]); }
printf("\n  ◆ وأحكامٌ موروثةٌ في سجلِّهم على الفائضِ: **%d** — ⛔ **`INHERITED_UNREVIEWED`\n", $inherited);
echo "     ولا تُعدُّ مقبولةً بمجرَّدِ وجودِها** (§٨: «راجعْ أحكامَهم واقبلها أو ارفضها بمبرِّر»)\n";

if ($LIST) {
    echo "\n  ── الفائضُ الحرجُ بأسمائه ──\n";
    foreach (array_slice($critical, 0, 25) as $s) {
        printf("     %s %-34s [%s] %s\n", $s['screen_id'],
               mb_substr($s['canonical_label_ar'], 0, 32), $s['owner_code'], implode('+', $s['why']));
    }
    if (count($critical) > 25) { printf("     … و%d غيرُها\n", count($critical) - 25); }
}

echo "\n────────────────────────────────────────────────────────────\n";
echo "⛔ **وأربعةٌ من المعاييرِ الخمسةِ لم تكن تُقاس قبلَ قياسِ الحبّة** (§٧·١) —\n";
echo "  فلا يُقرأ صفرُها السابقُ نظافةً بل **غيابَ مقياس**.\n";
echo "⛔ **وهذا يفرز ولا يحذف** — و«التصفيةُ» موجةٌ لاحقةٌ بحكمٍ موثَّقٍ لكلِّ سطح.\n";

if ($MD) {
    $o  = "# `RPR-02` §٨ — فرزُ الفائضِ قبلَ بناءِ الناقص\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `" . $sid . "`\n\n";
    $o .= "الأسطحُ الحيّةُ **" . $liveN . "** · المُطالَبُ بها هدفًا **" . ($liveN - $surplus)
        . "** ⇒ **الفائضُ " . $surplus . "**.\n";
    $o .= "وخطُّ الأمرِ يقول ٢٦٧ — **والفرقُ خبرٌ يُعلَن**: الكونُ الهدفيُّ أُعيد فصلُه في §٤·٢.\n\n";
    $o .= "| الصنف | العدد | متى يُعالَج |\n|---|---:|---|\n";
    $o .= "| ⛔ **فائضٌ حرج** | **" . count($critical) . "** | قبلَ بناءِ أيِّ سطحٍ جديد |\n";
    $o .= "| فائضٌ حميد | " . count($benign) . " | موجةُ تصفيةٍ لاحقةٌ بحكمٍ موثَّق |\n\n";
    $o .= "## معاييرُ الحرجِ الخمسة\n\n| المعيار | العدد | ما يدخله |\n|---|---:|---|\n";
    foreach ($CRIT as $k => $v) { $o .= '| `' . $k . '` | **' . $v . '** | ' . $TXT[$k] . " |\n"; }
    $o .= "\n⛔ **والسطحُ قد يحمل أكثرَ من معيار** — فيُعدُّ مرّةً في المقامِ وتُعرض معاييرُه كلُّها.\n";
    $o .= "⛔ **وأربعةٌ من الخمسةِ لم تكن تُقاس قبلَ قياسِ الحبّة** (§٧·١) — فلا يُقرأ صفرُها\n";
    $o .= "السابقُ نظافةً بل **غيابَ مقياس**.\n\n";
    $o .= "## الفائضُ الحرجُ بأسمائه\n\n| المعرِّف | المسمَّى | الإدارة | المعايير |\n|---|---|---|---|\n";
    foreach ($critical as $s) {
        $o .= '| `' . $s['screen_id'] . '` | ' . $s['canonical_label_ar'] . ' | `' . $s['owner_code']
            . '` | `' . implode('` `', $s['why']) . "` |\n";
    }
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/RPR02_S8_SURPLUS.md', $o);
    echo "\n✔ كُتب docs/REPAIR01_20260823/RPR02_S8_SURPLUS.md\n";
}
