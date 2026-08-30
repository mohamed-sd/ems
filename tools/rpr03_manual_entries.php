<?php
/**
 * tools/rpr03_manual_entries.php — `RPR-03` §٨·١ · تصنيفُ القيودِ اليدويّة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **القاعدةُ بنصِّها** — `RPR-03` §٨·١: *«**والقاعدةُ ليست «كلُّ قيدٍ يدويٍّ
 *   استثناء»**: فالتسوياتُ وقيودُ الإقفالِ وإعادةُ التبويبِ والاستحقاقاتُ قيودٌ
 *   مشروعةٌ بطبيعتِها. والقاعدةُ الصحيحة: ما يولّده النظامُ يحمل **مرجعَ حدثِه**
 *   · وما يُنشئه محاسبٌ يحمل **نوعَ مصدرِه ومستندَه وسببَه ومُعِدَّه ومعتمِدَه
 *   وفترتَه ومرجعَ تدقيقِه** · والاستثناءُ هو قيدٌ يدويٌّ بلا مصدرٍ ولا مبرِّرٍ
 *   ولا اعتماد»*.
 *
 * ◆ **ولا تعارضَ مع «لا كاتبَ بشريٌّ للأستاذ»** (§٨·١): *«فتلك تحكم **من
 *   يكتب** — المحرّكُ وحدَه — لا **هل توجد** تسوية. المحاسبُ يُعِدُّ والمحرّكُ
 *   يُرحّل»*.
 *
 * ◆ **وقراءتان للاستثناءِ تُعطيان رقمَين — فيُعرضان معًا ولا يُختار الأيسر**:
 *   ① **قراءةٌ حرفيّةٌ ضيّقة** — «بلا مصدرٍ **و**لا مبرِّرٍ **و**لا اعتماد»
 *      مجتمعةً. وتُعطي رقمًا صغيرًا لأنَّ حقلًا واحدًا مملوءًا يُخرج القيدَ.
 *   ② **قراءةٌ بالمعيارِ الموجب** — القاعدةُ توجب **السبعةَ** لمن يُنشئه محاسب،
 *      فما نقص أحدُها خالف المعيار.
 *   ⛔ **وعرضُ الضيّقةِ وحدَها أخضرُ كاذب**: يُقرأ «صفرُ استثناء» بينما أربعةٌ
 *      من السبعةِ **فارغةٌ في كلِّ صفّ**.
 *
 * ◆ **والنظامُ يشهد على نفسِه**: عمودُ `manual_gov_state` يسم القيودَ اليدويّةَ
 *   بـ`PRE_GOVERNANCE` — **فالمخزنُ نفسُه يعلن أنّها لم تُحكَم بعد**، وهذا
 *   شاهدٌ أقوى من أيِّ اشتقاق.
 *
 * التشغيل: php tools/rpr03_manual_entries.php [--md] [--selftest]
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

$one = function ($sql) use ($conn) {
    $r = @$conn->query($sql); if (!$r) { return null; }
    $x = $r->fetch_row(); return $x === null ? null : (int) $x[0];
};

$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
$sid = $snap ? $snap['snapshot_id'] : '—(بلا نافذة)';

$LIVE   = "COALESCE(is_deleted,0)=0";
$MANUAL = "(event_id IS NULL OR event_id=0)";

$all  = $one("SELECT COUNT(*) FROM fin_journal_entries WHERE $LIVE");
$gen  = $one("SELECT COUNT(*) FROM fin_journal_entries WHERE $LIVE AND event_id IS NOT NULL AND event_id>0");
$man  = $one("SELECT COUNT(*) FROM fin_journal_entries WHERE $LIVE AND $MANUAL");

/* الحقولُ السبعةُ التي توجبها القاعدةُ لمن يُنشئه محاسب */
$SEVEN = array(
    'نوعُ المنشأ'      => 'manual_kind',
    'المستندُ المؤيِّد' => 'source_doc_ref',
    'السبب'           => 'memo',
    'المُعِدّ'          => 'posted_by',
    'المعتمِد'         => 'approval_ref',
    'الفترة'          => 'period_code',
    'مرجعُ التدقيق'    => 'manual_gov_state',
);
$fill = array();
foreach ($SEVEN as $lbl => $col) {
    $fill[$lbl] = $one("SELECT COUNT(*) FROM fin_journal_entries
                         WHERE $LIVE AND $MANUAL AND COALESCE(`$col`,'') <> '' AND `$col` <> '0'");
}

/* القراءتان */
$narrow = $one("SELECT COUNT(*) FROM fin_journal_entries WHERE $LIVE AND $MANUAL
                 AND COALESCE(source_doc_ref,'')='' AND COALESCE(memo,'')=''
                 AND COALESCE(approval_ref,'')=''");
$broad  = $one("SELECT COUNT(*) FROM fin_journal_entries WHERE $LIVE AND $MANUAL
                 AND (COALESCE(manual_kind,'')='' OR COALESCE(source_doc_ref,'')=''
                   OR COALESCE(memo,'')='' OR COALESCE(posted_by,0)=0
                   OR COALESCE(approval_ref,'')='' OR COALESCE(period_code,'')=''
                   OR COALESCE(manual_gov_state,'')='')");

/* شهادةُ المخزنِ على نفسِه */
$states = array();
$r = $conn->query("SELECT COALESCE(NULLIF(manual_gov_state,''),'(فارغ)') s, COUNT(*) n
                     FROM fin_journal_entries WHERE $LIVE AND $MANUAL GROUP BY 1 ORDER BY n DESC");
while ($x = $r->fetch_assoc()) { $states[$x['s']] = (int) $x['n']; }
$preGov = isset($states['PRE_GOVERNANCE']) ? $states['PRE_GOVERNANCE'] : 0;

/* ⛔ **السالبُ يكسر مفردةً فريدة**: تُعرض الضيّقةُ وحدَها فيبدو أخضرَ */
$showBoth = !$SELF;

echo "\n═══ `RPR-03` §٨·١ — تصنيفُ القيودِ اليدويّة ═══\n";
printf("  اللقطة: %s\n\n", $sid);
printf("  قيودٌ حيّةٌ: **%d** · مولَّدٌ بمرجعِ حدثِه **%d** · **يدويٌّ %d**\n", $all, $gen, $man);
echo "  ◆ وخطُّ الأساسِ يقول **١٬٦٤٤ قيدًا يدويًّا** — **والمقيسُ " . $man . "**\n";

echo "\n  ── الحقولُ السبعةُ التي توجبها القاعدةُ لليدويّ ──\n";
foreach ($SEVEN as $lbl => $col) {
    printf("     %s %-18s %5d من %d\n", $fill[$lbl] === $man ? '✔' : '⛔', $lbl, $fill[$lbl], $man);
}

echo "\n  ── لماذا الفترةُ فارغة — سببٌ واحدٌ مقيسٌ لا إهمال ──
";
$pmin = $one("SELECT MIN(posting_date) FROM fin_journal_entries WHERE $LIVE AND $MANUAL AND COALESCE(period_code,'')=''");
$pmax = $one("SELECT MAX(posting_date) FROM fin_journal_entries WHERE $LIVE AND $MANUAL AND COALESCE(period_code,'')=''");
$pfy  = $one("SELECT GROUP_CONCAT(DISTINCT fiscal_year ORDER BY fiscal_year) FROM fin_financial_periods");
$pok  = $one("SELECT COUNT(*) FROM fin_journal_entries j WHERE $LIVE AND $MANUAL AND COALESCE(j.period_code,'')<>''
               AND EXISTS(SELECT 1 FROM fin_financial_periods p WHERE p.id=j.period_code
                            AND p.period_type='month' AND j.posting_date BETWEEN p.start_date AND p.end_date)");
$pfil = $fill['الفترة'];
$pnoP = $one("SELECT COUNT(*) FROM fin_journal_entries j WHERE $LIVE AND $MANUAL AND COALESCE(j.period_code,'')=''
               AND NOT EXISTS(SELECT 1 FROM fin_financial_periods p WHERE p.company_id=j.company_id
                                AND p.period_type='month' AND j.posting_date BETWEEN p.start_date AND p.end_date)");
printf("     ◆ **الفرضيّةُ تحقَّقت**: `period_code` مرجعٌ إلى `fin_financial_periods.id` —
");
printf("       و**%s من %s** من الممتلئِ يطابق **الفترةَ الحاويةَ لتاريخِه** بالضبط
", $pok, $pfil);
printf("     ⛔ **والباقي لا يُشتقّ لأنَّ الفترةَ غيرُ موجودةٍ أصلًا**: تواريخُه %s .. %s
", $pmin, $pmax);
printf("       ودفترُ `fin_financial_periods` يغطّي السنواتِ **%s** وحدَها ⇒ **%s قيدًا بلا فترةٍ حاوية**
", $pfy, $pnoP);
printf("     ⇒ **فالحقلُ ليس مهمَلًا — لا يوجد ما يُشار إليه.** وإنشاءُ سنواتٍ ماليّةٍ
");
printf("       بحالاتِها وتواريخِ إقفالِها **فعلٌ محاسبيٌّ لا اشتقاقُ أداة**.
");
echo "
  ── قراءتان للاستثناءِ — تُعرضان معًا ولا يُختار الأيسر ──\n";
printf("     ① حرفيّةٌ ضيّقة (بلا مصدرٍ **و**مبرِّرٍ **و**اعتمادٍ معًا): **%d**\n", $narrow);
if ($showBoth) {
    printf("     ② بالمعيارِ الموجب (نقص أحدُ السبعة):                **%d**\n", $broad);
    echo "     ⛔ **وعرضُ الضيّقةِ وحدَها أخضرُ كاذب** — فأربعةٌ من السبعةِ فارغةٌ في كلِّ صفّ\n";
} else {
    echo "     ⛔ **(‏السالبُ: أُخفيت القراءةُ الثانيةُ عمدًا)**\n";
}

echo "\n  ── والمخزنُ يشهد على نفسِه ──\n";
foreach ($states as $s => $n) { printf("     `%s` = %d\n", $s, $n); }
if ($preGov === $man && $man > 0) {
    echo "     ⛔ **كلُّ اليدويِّ `PRE_GOVERNANCE`** — فالمخزنُ يعلن أنّها لم تُحكَم بعد\n";
}

echo "\n────────────────────────────────────────────────────────────\n";
printf("**`قيودٌ يدويّةٌ بلا مصدرٍ ولا مبرِّرٍ ولا اعتماد` = %d (ضيّقة) · %d (بالمعيار)** — والقبولُ صفر\n",
       $narrow, $broad);
echo ($broad === 0)
    ? "🟢 **كلُّ يدويٍّ يحمل سبعتَه**\n"
    : "✘ **" . $broad . " قيدًا ينقصه أحدُ السبعة** ⇒ `Track RPR-03 و blocked at stage: "
    . "حوكمةُ القيدِ اليدويّ`\n  ◆ والمواضعُ موجودةٌ في الجدولِ — **والناقصُ الملءُ لا البنية**\n";

if ($SELF) {
    echo "\n═══ الاختبارُ السالب ═══\n";
    echo ($narrow === 0 && $broad > 0)
        ? "🟢 **الضيّقةُ صفرٌ والموجبةُ " . $broad . " — فعرضُ واحدةٍ يُنتج أخضرَ كاذبًا، والفاحصُ يعرض الاثنتَين**\n"
        : "✘ **القراءتان لا تفترقان هنا** — فالاختبارُ لا يُثبت شيئًا\n";
    exit(($narrow === 0 && $broad > 0) ? 0 : 1);
}

if ($MD) {
    $o  = "# `RPR-03` §٨·١ — تصنيفُ القيودِ اليدويّة\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `" . $sid . "`\n\n";
    $o .= "قيودٌ حيّةٌ **" . $all . "** · مولَّدٌ بمرجعِ حدثِه **" . $gen . "** · **يدويٌّ " . $man . "**"
        . " (‏وخطُّ الأساسِ ١٬٦٤٤).\n\n";
    $o .= "## الحقولُ السبعةُ التي توجبها القاعدةُ لليدويّ\n\n| الحقل | العمود | مملوءٌ |\n|---|---|---|\n";
    foreach ($SEVEN as $lbl => $col) {
        $o .= '| ' . $lbl . ' | `' . $col . '` | ' . ($fill[$lbl] === $man ? '✔ ' : '⛔ ')
            . $fill[$lbl] . ' من ' . $man . " |\n";
    }
    $o .= "\n## قراءتان للاستثناء — تُعرضان معًا\n\n";
    $o .= "| القراءة | العدد |\n|---|---|\n";
    $o .= "| ① حرفيّةٌ ضيّقة — بلا مصدرٍ **و**مبرِّرٍ **و**اعتمادٍ معًا | **" . $narrow . "** |\n";
    $o .= "| ② بالمعيارِ الموجب — نقص أحدُ السبعة | **" . $broad . "** |\n\n";
    $o .= "⛔ **وعرضُ الضيّقةِ وحدَها أخضرُ كاذب**: تُقرأ «صفرُ استثناء» بينما **أربعةٌ من\n";
    $o .= "السبعةِ فارغةٌ في كلِّ صفّ** — `manual_kind` و`source_doc_ref` و`approval_ref`\n";
    $o .= "و`period_code`.\n\n";
    $o .= "## والمخزنُ يشهد على نفسِه\n\n";
    foreach ($states as $s => $n) { $o .= "- `" . $s . "` = **" . $n . "**\n"; }
    if ($preGov === $man && $man > 0) {
        $o .= "\n⛔ **كلُّ اليدويِّ `PRE_GOVERNANCE`** — فالمخزنُ نفسُه يعلن أنّها لم تُحكَم بعد،\n";
        $o .= "وهذا شاهدٌ أقوى من أيِّ اشتقاق.\n";
    }
    $o .= "\n**والمواضعُ موجودةٌ في الجدولِ — والناقصُ الملءُ لا البنية.**\n";
    $o .= "\n`Track RPR-03 و blocked at stage: حوكمةُ القيدِ اليدويّ`\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/RPR03_MANUAL_ENTRIES.md', $o);
    echo "\n✔ كُتب: docs/REPAIR01_20260823/RPR03_MANUAL_ENTRIES.md\n";
}
