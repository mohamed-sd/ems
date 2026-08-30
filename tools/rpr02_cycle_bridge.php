<?php
/**
 * tools/rpr02_cycle_bridge.php — `RPR-02` #٧ · جسرُ دورةِ العملِ إلى معرِّفِ الشاشة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — §٥·١٠: *«كلُّ شاشةٍ ينطبق عليها سيرُ عملٍ تحمل
 *   **`Screen ID → Workflow Stage` صراحةً** — ⛔ لا بالاسمِ ولا بالمسارِ ولا
 *   بالاستنتاج. **وما لا ينطبق عليه يحمل `Applicability Reason`** بدل اختراعِ
 *   مرحلةٍ له»*.
 *
 * ◆ **والوصلُ كان باسمِ ملفٍّ مجرَّد** — لا مسارٍ ولا معرِّف. و`index.php`
 *   يطابق **ثلاثةَ** أسطحٍ حيّة، و`modules.php` و`roles.php` و`employees.php`
 *   و`timesheet.php` كلٌّ **سطحان**. ⇒ **فالوصلُ ملتبسٌ بطبعِه**.
 *
 * ◆ **ثلاثُ قواعدَ — والمقياسُ يُعلن أيَّتَها حكمت**:
 *   **C1 · `BASENAME_UNIQUE`** — الاسمُ يُحلُّ إلى **سطحٍ حيٍّ واحدٍ لا غير** ⇒
 *        يُكتب المعرِّف. والشاهد: الاسمُ وتعدادُ مرشَّحيه = ١.
 *   **C2 · `AMBIGUOUS_DECLARED`** — مرشَّحان فأكثر ⇒ ⛔ **يبقى فارغًا بمرشَّحيه**.
 *        **فاختيارُ أحدِ ثلاثةِ `index.php` يربط مرحلةَ دورةٍ بشاشةٍ ليست هي.**
 *   **C3 · `NO_LIVE_SURFACE`** — لا سطحَ حيًّا لهذا الاسم ⇒ صفُّ دورةٍ لسطحٍ
 *        غيرِ قائم، **يُعلَن ولا يُحذف** (§٨ يفرز ولا يحذف).
 *
 * ◆ **والمقامُ «المنطبقُ» لا «الكلّ»** — §٥·١٠ تقول «كلُّ شاشةٍ **ينطبق عليها
 *   سيرُ عمل**»: **المعاملةُ** (حبّةُ `ROW`/`LINE` بمدى `OWN_FACT`) ينطبق عليها،
 *   **والقراءةُ الصِّرفةُ لا تُنشئ حالةً** فتحمل سببَ عدمِ الانطباق.
 *   ⛔ **ومرحلةٌ لسطحِ قراءةٍ ليست خطأً** — `stage_kind='contextual'` قائمٌ في
 *      الجدولِ لذلك — **لكنّها لا تُحتسب في مقامِ المنطبق**.
 *
 * ⚠ **وقيسَ الأثرُ قبل البناء**: تضييقُ المقامِ إلى المعاملاتِ **لا يغيّر النسبةَ
 *   كثيرًا** (٥٥٫٦٪ للكلِّ · ٥٧٫٣٪ للمعاملات) — **فالنقصُ حقيقيٌّ موزَّعٌ لا
 *   أثرَ مقامٍ**. ⛔ **ويُقال ذلك كي لا يُقرأ التضييقُ تحسينًا.**
 *
 * التشغيل:
 *   php tools/rpr02_cycle_bridge.php [--apply] [--md] [--selftest]
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

function cb_rule($nCand)
{
    if ($nCand === 1) { return 'BASENAME_UNIQUE'; }
    if ($nCand > 1)   { return 'AMBIGUOUS_DECLARED'; }
    return 'NO_LIVE_SURFACE';
}

/* ═══ ① الاختبارُ السالبُ — يُصيب الأطرافَ الثلاثةَ ولا يمرُّ بمفردةٍ فريدة ═══ */
if ($SELF) {
    $fail = 0;
    if (cb_rule(1) !== 'BASENAME_UNIQUE')     { echo "  X الواحدُ لم يُحلّ\n"; $fail++; }
    if (cb_rule(3) !== 'AMBIGUOUS_DECLARED')  { echo "  X الملتبسُ لم يُعلَن\n"; $fail++; }
    if (cb_rule(0) !== 'NO_LIVE_SURFACE')     { echo "  X العدمُ لم يُميَّز\n"; $fail++; }
    /* ⛔ **الكاسر**: لو عُدَّ الملتبسُ محلولًا لَرُبطت مرحلةٌ بشاشةٍ ليست هي */
    if (cb_rule(2) === 'BASENAME_UNIQUE')     { echo "  X مرشَّحان عُدّا حلًّا\n"; $fail++; }
    /* والقاعدةُ الصلبةُ لازمةٌ — فلولاها لتسلَّل معرِّفٌ إلى صفٍّ ملتبس */
    $r = $conn->query("SHOW CREATE TABLE `gov_screen_cycle`");
    $ddl = $r ? $r->fetch_row()[1] : '';
    if (strpos($ddl, 'chk_cyc_bridge') === false) {
        echo "  X `chk_cyc_bridge` غائبةٌ — والقاعدةُ تقبل معرِّفًا لملتبس\n"; $fail++;
    }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n"
               : "\n🟢 الفحصُ الذاتيُّ تامٌّ — والقاعدةُ تفرّق الواحدَ من الملتبسِ من العدم\n";
    exit($fail ? 1 : 0);
}

/* ═══ ② نافذةُ القياس ════════════════════════════════════════════════════ */
$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
if (!$snap && $APPLY) { exit("⛔ **لا نافذةَ قياسٍ مفتوحة** — جمِّدْ أوّلًا.\n"); }
$sid = $snap ? $snap['snapshot_id'] : 'DRY';

$col = $conn->query("SHOW COLUMNS FROM `gov_screen_cycle` LIKE 'screen_id'");
if ((!$col || !$col->num_rows) && $APPLY) {
    exit("⛔ **عمودُ الجسرِ غيرُ موجود** — والعُدّةُ لا تُنشئ مخطَّطًا.\n"
       . "   شغِّلْ: php database/migrations/2028_01_08_rpr02_cycle_bridge.php\n");
}

/* ═══ ③ المرشَّحون لكلِّ اسمِ ملفّ ════════════════════════════════════════ */
$LIVE = "repair01_screen_registry WHERE on_disk = 1 AND ownership_verdict <> 'RETIRE'";
$byBase = array(); $meta = array();
$r = $conn->query("SELECT screen_id, screen_file, route, canonical_label_ar,
                          grain_cardinality, grain_fact_scope FROM $LIVE");
while ($x = $r->fetch_assoc()) {
    $b = strtolower(basename((string) $x['screen_file']));
    $byBase[$b][] = $x['screen_id'];
    $meta[$x['screen_id']] = $x;
}

$rows = array(); $stat = array('C1' => 0, 'C2' => 0, 'C3' => 0);
$bound = array(); $ambNames = array();
$r = $conn->query("SELECT id, screen_file, stage_name, stage_kind FROM gov_screen_cycle ORDER BY id");
while ($x = $r->fetch_assoc()) {
    $b = strtolower(basename((string) $x['screen_file']));
    $cand = isset($byBase[$b]) ? $byBase[$b] : array();
    $rule = cb_rule(count($cand));
    $sidv = ($rule === 'BASENAME_UNIQUE') ? $cand[0] : '';
    if ($rule === 'BASENAME_UNIQUE') { $stat['C1']++; $bound[$sidv] = 1; }
    elseif ($rule === 'AMBIGUOUS_DECLARED') { $stat['C2']++; $ambNames[$b] = count($cand); }
    else { $stat['C3']++; }
    $wit = ($rule === 'BASENAME_UNIQUE')
        ? 'C1 · اسمُ الملفِّ `' . $b . '` يُحلُّ إلى **سطحٍ حيٍّ واحدٍ لا غير** (`' . $sidv
          . '`) ⇒ الوصلُ بالمعرِّفِ لا بالاسم · لقطة ' . $sid
        : (($rule === 'AMBIGUOUS_DECLARED')
           ? 'C2 · اسمُ الملفِّ `' . $b . '` يطابق **' . count($cand) . '** أسطحٍ حيّةٍ ('
             . implode(' · ', array_slice($cand, 0, 5)) . ') ⇒ ⛔ **يبقى بلا معرِّف**: '
             . 'اختيارُ أحدِها يربط مرحلةَ دورةٍ بشاشةٍ ليست هي · لقطة ' . $sid
           : 'C3 · اسمُ الملفِّ `' . $b . '` **لا سطحَ حيًّا له** — صفُّ دورةٍ لسطحٍ غيرِ قائمٍ '
             . 'أو متقاعد. **يُعلَن ولا يُحذف** (§٨ يفرز ولا يحذف) · لقطة ' . $sid);
    $rows[] = array((int) $x['id'], $b, $rule, $sidv, $wit);
}

/* ═══ ④ التغطيةُ على المنطبق ═════════════════════════════════════════════ */
$cls = array(
    'المعاملاتُ الحقيقيّة (`OWN_FACT` · `ROW`/`LINE`)' =>
        "AND grain_cardinality IN ('ROW','LINE') AND grain_fact_scope = 'OWN_FACT'",
    'كلُّ أسطحِ الكتابة'   => "AND grain_cardinality IN ('ROW','LINE')",
    'القراءةُ الصِّرفةُ (لا ينطبق)' => "AND grain_cardinality NOT IN ('ROW','LINE')",
    'الكلُّ الحيّ'          => '',
);
$cov = array();
foreach ($cls as $lbl => $w) {
    $ids = array();
    $rr = $conn->query("SELECT screen_id FROM $LIVE $w");
    while ($rr && $z = $rr->fetch_row()) { $ids[] = $z[0]; }
    $h = 0;
    foreach ($ids as $i) { if (isset($bound[$i])) { $h++; } }
    $cov[$lbl] = array(count($ids), $h);
}

/* ═══ ⑤ العرض ════════════════════════════════════════════════════════════ */
echo "\n═══ `RPR-02` #٧ — جسرُ دورةِ العملِ إلى معرِّفِ الشاشة ═══\n";
printf("  اللقطة: %s · صفوفُ دورةِ العمل: **%d**\n\n", $sid, count($rows));
echo "  ── القواعدُ الثلاث ──\n";
printf("     C1 `BASENAME_UNIQUE`     %4d صفًّا — يُحلُّ إلى سطحٍ واحدٍ ⇒ **يُكتب المعرِّف**\n", $stat['C1']);
printf("     C2 `AMBIGUOUS_DECLARED`  %4d صفًّا على %d اسمًا — ⛔ **يبقى بلا معرِّف**\n",
       $stat['C2'], count($ambNames));
printf("     C3 `NO_LIVE_SURFACE`     %4d صفًّا — سطحٌ غيرُ قائمٍ · يُعلَن ولا يُحذف\n", $stat['C3']);
if ($ambNames) {
    echo "\n     ── الملتبسُ بأسمائه ──\n";
    foreach ($ambNames as $b => $n) { printf("       · %-28s %d أسطح\n", $b, $n); }
}
echo "\n  ── التغطيةُ بالمعرِّفِ لكلِّ صنف ──\n";
foreach ($cov as $lbl => $v) {
    printf("     %-46s %4d ⇒ له مرحلةٌ %4d · %s%%\n", $lbl, $v[0], $v[1],
           $v[0] ? round($v[1] * 100 / $v[0], 1) : 0);
}
echo "\n  ⚠ **وتضييقُ المقامِ لا يُقرأ تحسينًا**: النقصُ موزَّعٌ على الأصنافِ كلِّها\n";
echo "     (‏٥٥٫٦٪ للكلِّ · ٥٧٫٣٪ للمعاملات) ⇒ **فهو نقصٌ حقيقيٌّ لا أثرُ مقام.**\n";

/* ═══ ⑥ التثبيت ══════════════════════════════════════════════════════════ */
if ($APPLY) {
    $n = 0;
    foreach ($rows as $x) {
        $ok = $conn->query("UPDATE gov_screen_cycle
              SET screen_id = '" . $e($x[3]) . "',
                  bridge_rule = '" . $e($x[2]) . "',
                  bridge_witness = '" . $e(mb_substr($x[4], 0, 400)) . "',
                  bridge_snapshot = '" . $e($sid) . "'
            WHERE id = " . (int) $x[0]);
        if (!$ok) { exit("✘ تعذّر جسرُ الصفِّ {$x[0]}: {$conn->error}\n"); }
        $n++;
    }
    $bad = (int) $conn->query("SELECT COUNT(*) FROM gov_screen_cycle
                                WHERE bridge_rule <> '' AND bridge_witness = ''")->fetch_row()[0];
    $leak = (int) $conn->query("SELECT COUNT(*) FROM gov_screen_cycle
                                 WHERE bridge_rule <> 'BASENAME_UNIQUE' AND screen_id <> ''")->fetch_row()[0];
    printf("\n  ✔ جُسِر **%d** صفًّا · صفٌّ بلا شاهدٍ %d · **معرِّفٌ في صفٍّ ملتبسٍ %d**\n", $n, $bad, $leak);
}

if ($MD) {
    $o  = "# `RPR-02` #٧ — جسرُ دورةِ العملِ إلى معرِّفِ الشاشة\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `" . $sid . "`\n\n";
    $o .= "## الوصلُ كان باسمِ ملفٍّ مجرَّد\n\n";
    $o .= "§٥·١٠ تشترط `Screen ID → Workflow Stage` **صراحةً — لا بالاسمِ ولا بالمسارِ ولا\n";
    $o .= "بالاستنتاج**. و`gov_screen_cycle` تصل بـ`screen_file` وهو **اسمُ ملفٍّ مجرَّد**:\n";
    $o .= "`index.php` يطابق **ثلاثةَ** أسطحٍ حيّة.\n\n";
    $o .= "| القاعدة | صفوف | الحكم |\n|---|---:|---|\n";
    $o .= "| `C1_BASENAME_UNIQUE` | **" . $stat['C1'] . "** | سطحٌ واحدٌ لا غير ⇒ **يُكتب المعرِّف** |\n";
    $o .= "| `C2_AMBIGUOUS_DECLARED` | **" . $stat['C2'] . "** | على " . count($ambNames) . " اسمًا — ⛔ **يبقى بلا معرِّف** |\n";
    $o .= "| `C3_NO_LIVE_SURFACE` | **" . $stat['C3'] . "** | سطحٌ غيرُ قائمٍ — يُعلَن ولا يُحذف |\n\n";
    $o .= "## التغطيةُ بالمعرِّفِ لكلِّ صنف\n\n| الصنف | المقام | له مرحلة | النسبة |\n|---|---:|---:|---:|\n";
    foreach ($cov as $lbl => $v) {
        $o .= '| ' . $lbl . ' | ' . $v[0] . ' | ' . $v[1] . ' | '
            . ($v[0] ? round($v[1] * 100 / $v[0], 1) : 0) . "% |\n";
    }
    $o .= "\n⚠ **وتضييقُ المقامِ إلى «المنطبق» لا يُقرأ تحسينًا** — النقصُ موزَّعٌ على الأصنافِ\n";
    $o .= "كلِّها بنسبٍ متقاربة ⇒ **فهو نقصٌ حقيقيٌّ لا أثرُ مقام**.\n\n";
    $o .= "⛔ **ومرحلةٌ لسطحِ قراءةٍ ليست خطأً** — `stage_kind='contextual'` قائمٌ لذلك —\n";
    $o .= "**لكنّها لا تُحتسب في مقامِ المنطبق**.\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/RPR02_S7_CYCLE.md', $o);
    echo "\n✔ كُتب: docs/REPAIR01_20260823/RPR02_S7_CYCLE.md\n";
}
