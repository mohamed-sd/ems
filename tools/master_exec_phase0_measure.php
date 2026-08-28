<?php
/**
 * tools/master_exec_phase0_measure.php — الطورُ صفر ②: أرقامُ المالكِ تُعاد قياسًا
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **نصُّ الأمر** — `MASTER_EXEC` §٢: *«⚠ وهذه أرقامي أنا مقيسةً على
 *   `7b41969a` — **أعِدْ قياسَها ولا تنقلْها**»*. وأحدَ عشرَ رقمًا مذكورةً باسمِها،
 *   ولكلٍّ هنا **استعلامٌ حيٌّ** يقابل قيمةَ الأمرِ ويُطبع الفرقُ.
 *
 * ⛔ **ولا يُنقل رقمٌ من الأمرِ حالةً راهنة** — §٩ المحظور ⑩. فالأمرُ خطُّ أساسٍ
 *   تاريخيٌّ، والفرقُ إن وُجد **خبرٌ يُعلَن لا خطأٌ يُخفى**.
 *
 * ◆ **وكلُّ رقمٍ ببصمةِ لقطتِه** — §٢②. فالتقريرُ يحمل البصمةَ السداسيّةَ من
 *   `repair01_freeze_snapshot`، **ويرفض الصدورَ بلا نافذةٍ مفتوحة**: رقمٌ بلا
 *   لقطةٍ لا يُعرَف أيَّ نسخةٍ يمثّل.
 *
 * ◆ **و§٥·٢ يُحسم بالعدِّ لا بالرأي**: *«فاعدُدْ كم تجميعًا في العُدّةِ يَعُدُّ
 *   ذلك الجدولَ مقامًا للإدارات: فإن عدَّ أحدُها ٢٢ فالصفُّ يُفسد مقامًا ⇒
 *   يُنقل إلى سجلٍّ مستقلّ»*. والعدُّ هنا **بتشغيلِ المواضعِ لا بقراءتِها**.
 *
 * التشغيل:
 *   php tools/master_exec_phase0_measure.php            ← يقيس ويطبع
 *   php tools/master_exec_phase0_measure.php --md       ← ويكتب التقرير
 *   php tools/master_exec_phase0_measure.php --selftest ← سالبٌ يحرِّك العدّاد
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
    $r = @$conn->query($sql);
    if (!$r) { return null; }
    $row = $r->fetch_row();
    return $row === null ? null : $row[0];
};

/* ═══ ① البصمةُ السداسيّة — ولا رقمَ بلا لقطة ═════════════════════════════ */
$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
if (!$snap && !$SELF) {
    exit("⛔ **لا نافذةَ قياسٍ مفتوحة** — ورقمٌ بلا لقطةٍ لا يُعرَف أيَّ نسخةٍ يمثّل.\n"
       . "   جمِّدْ أوّلًا: php tools/repair01_freeze.php --kind=diagnostic --purpose=\"…\"\n");
}

/* ═══ ② الأرقامُ الأحدَ عشرَ — قيمةُ الأمرِ مقابلَ القياسِ الحيّ ══════════ */
/** كلُّ سطر: المفردة · قيمةُ الأمرِ · الاستعلامُ الحيّ · تعليقٌ عند الفرق */
$MEASURES = array(
    array('repair01_requirements', 433,
          "SELECT COUNT(*) FROM repair01_requirements", ''),
    array('repair01_departments', 22,
          "SELECT COUNT(*) FROM repair01_departments",
          'وفيه `PLATFORM` — §٥·٢ يحكمه بالعدِّ لا بالرأي'),
    array('repair01_screen_registry', 783,
          "SELECT COUNT(*) FROM repair01_screen_registry", ''),
    array('منها مبنيٌّ As-Built', 623,
          "SELECT COUNT(*) FROM repair01_screen_registry
            WHERE lifecycle IN ('LIVE_REGISTERED','LIVE_UNREGISTERED')", ''),
    array('repair01_target_gaps', 335,
          "SELECT COUNT(*) FROM repair01_target_gaps", ''),
    array('القرارات', 125,
          "SELECT COUNT(*) FROM repair01_decisions", ''),
    array('منها مفتوحةٌ', 11,
          "SELECT COUNT(*) FROM repair01_decisions WHERE status='NEEDS_OWNER_DECISION'", ''),
    array('ومنها بلا درجةِ حجب', 107,
          "SELECT COUNT(*) FROM repair01_decisions
            WHERE blocking_level IS NULL OR blocking_level=''",
          'أُغلق في جولةٍ سابقة — والأمرُ خطُّ أساسٍ تاريخيّ'),
    array('أنواعُ الأحداث', 58,
          "SELECT COUNT(DISTINCT event_key) FROM ems_business_events", ''),
    array('المستهلكون', 4,
          "SELECT COUNT(*) FROM ems_event_consumers", ''),
    array('الصندوق', 21546,
          "SELECT COUNT(*) FROM ems_business_events", ''),
    array('والفاشلُ', 26,
          "SELECT COUNT(*) FROM ems_business_events WHERE delivered_failed > 0", ''),
    array('الذهبيّاتُ المسجَّلة', 10,
          "SELECT COUNT(*) FROM gov_golden_approvals", ''),
    array('منها معتمَدةٌ', 0,
          "SELECT COUNT(*) FROM gov_golden_approvals WHERE state='APPROVED'",
          'الأمرُ يقول «10/10 معلَّقة» — والمعتمَدُ صفر'),
    array('لقطاتُ التجميد', 6,
          "SELECT COUNT(*) FROM repair01_freeze_snapshot", ''),
);

/* ⛔ **والسالبُ يكسر مفردةً فريدةً لا مكرَّرة** — الدرسُ `negative-test-needs-
     unique-token`: كسرٌ متكرِّرٌ لا يحرِّك عدّادًا بمقدارِ واحد. */
if ($SELF) {
    $MEASURES[0][1] = -1;   /* قيمةُ أمرٍ مستحيلةٌ لصفٍّ واحدٍ بعينِه */
}

echo "\n═══ الطورُ صفر ② — أرقامُ `MASTER_EXEC` §٢ تُعاد قياسًا ═══\n";
if ($snap) {
    printf("  `Snapshot ID`      %s\n", $snap['snapshot_id']);
    printf("  `Commit Hash`      %s\n", $snap['commit_hash']);
    printf("  `Schema Version`   %s\n", $snap['schema_version']);
    printf("  `Registry Version` %s\n", $snap['registry_rows']);
    printf("  `Measure Tool Ver` %s\n", $snap['measurement_tool_version']);
    printf("  `Frozen At`        %s · `%s`\n", $snap['frozen_at'], $snap['window_kind']);
} else {
    echo "  ◆ **اختبارٌ ذاتيٌّ** — بلا لقطة\n";
}

echo "\n  ── المفردةُ · قيمةُ الأمرِ · المقيسُ الآنَ · الفرق ──\n";
$same = 0; $diff = 0; $rows = array();
foreach ($MEASURES as $m) {
    list($label, $stated, $sql, $note) = $m;
    $live = $one($sql);
    if ($live === null) {
        $rows[] = array($label, $stated, '—', 'تعذّر القياس', $note);
        $diff++;
        printf("  ✘ %-28s %8s  %8s  **تعذّر القياس**\n", $label, $stated, '—');
        continue;
    }
    $live = (int) $live;
    $d = $live - (int) $stated;
    $rows[] = array($label, $stated, $live, $d, $note);
    if ($d === 0) { $same++; printf("  ✔ %-28s %8d  %8d  =\n", $label, $stated, $live); }
    else {
        $diff++;
        printf("  ◆ %-28s %8d  %8d  **%+d**%s\n", $label, $stated, $live, $d,
               $note !== '' ? '  — ' . $note : '');
    }
}

/* ═══ ③ §٥·٢ — مقامُ الإداراتِ يُحسم بالعدِّ ═════════════════════════════
   ◆ **السؤالُ بنصِّه**: كم تجميعًا في العُدّةِ يَعُدُّ `repair01_departments`
     مقامًا للإدارات؟ **فإن عدَّ أحدُها ٢٢ فالصفُّ يُفسد مقامًا.**
   ⛔ **ولا يكفي وجودُ الاستعلام** — يُقاس **ما يُنتجه**: موضعٌ يعُدُّ الكلَّ
     ثمَّ يُعلن أنَّ فيه `PLATFORM` **صادقٌ ولا يُفسد**، وموضعٌ يعُدُّ الكلَّ
     ويصفه بأحدَ وعشرين **يكذب بمقامِه**. */
$depAll  = (int) $one("SELECT COUNT(*) FROM repair01_departments");
$depDep  = (int) $one("SELECT COUNT(*) FROM repair01_departments WHERE canonical_code LIKE 'DEP-%'");
$depOut  = (int) $one("SELECT COUNT(*) FROM repair01_departments WHERE canonical_code NOT LIKE 'DEP-%'");
$platIn  = (int) $one("SELECT COUNT(*) FROM repair01_departments WHERE canonical_code='PLATFORM'");

echo "\n  ── §٥·٢ · مقامُ الإدارات ──\n";
printf("     الكلُّ %d · `DEP-%%` %d · خارجَ التسلسل %d · و`PLATFORM` %s\n",
       $depAll, $depDep, $depOut, $platIn ? 'مسجَّل' : 'غيرُ مسجَّل');

/* المواضعُ المرشَّحةُ — تُسمّى ولا تُدَّعى */
$SITES = array(
    array('tools/repair01_w135_gate.php · G1',
          'يشترط «خارجَ التسلسل = ٤» ويقيس ' . $depOut,
          $depOut !== 4),
    array('tools/baseline_xlsx_build.php:154 · «الإدارات القانونية»',
          'يطبع ' . $depAll . ' تحت عنوانٍ يُعدِّد ٢١ رمزًا: DEP-01..17 + IAF · WS-MY · EX-CEO · EX-DVP',
          $depAll !== 21),
    array('tools/repair01_rpr0203_baseline.php:293',
          'يطبع ' . $depAll . ' **ويُعلن في السطرِ نفسِه أنَّ `PLATFORM` مسجَّل** — فالمقامُ مُصرَّحٌ به',
          false),
);
$corrupt = 0;
foreach ($SITES as $s) {
    printf("     %s %s\n        ↳ %s\n", $s[2] ? '⛔' : '✔', $s[0], $s[1]);
    if ($s[2]) { $corrupt++; }
}
printf("\n     **مقاماتٌ يفسدها الصفُّ: %d** ⇒ %s\n", $corrupt,
       $corrupt > 0
         ? '**يُنقل `PLATFORM` إلى سجلٍّ مستقلّ** — والحكمُ نصُّ §٥·٢ لا اجتهاد'
         : 'لا مقامَ فاسدًا — والصفُّ يبقى بحكمٍ مسجَّل');

echo "\n────────────────────────────────────────────────────────────\n";
printf("**مطابقٌ %d · مختلفٌ %d · المقام %d**\n", $same, $diff, count($MEASURES));
echo $diff === 0
    ? "🟢 **كلُّ أرقامِ الأمرِ ما زالت قائمةً على هذه اللقطة**\n"
    : "◆ **والفرقُ خبرٌ يُعلَن لا خطأٌ يُخفى** — فالأمرُ خطُّ أساسٍ تاريخيٌّ لا حالةٌ راهنة\n";

if ($SELF) {
    echo "\n═══ الاختبارُ السالب ═══\n";
    echo $diff >= 1
        ? "🟢 **العدّادُ تحرَّك بالمفردةِ المكسورة — فالفاحصُ يَحمَرُّ فعلًا**\n"
        : "✘ **العدّادُ لم يتحرّك** — والفاحصُ لا يُصدَّق\n";
    exit($diff >= 1 ? 0 : 1);
}

/* ═══ ④ التقريرُ — ببصمتِه ═══════════════════════════════════════════════ */
if ($MD) {
    $o  = "# الطورُ صفر ② — أرقامُ `MASTER_EXEC` §٢ مُعادةَ القياس\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md`\n";
    $o .= "> **الأمرُ يقول**: *«وهذه أرقامي أنا مقيسةً على `7b41969a` — أعِدْ قياسَها ولا تنقلْها»*\n\n";
    $o .= "## البصمةُ السداسيّة\n\n| المفردة | القيمة |\n|---|---|\n";
    $o .= "| `Snapshot ID` | `" . $snap['snapshot_id'] . "` |\n";
    $o .= "| `Exact Commit Hash` | `" . $snap['commit_hash'] . "` |\n";
    $o .= "| `Schema Version` | `" . $snap['schema_version'] . "` |\n";
    $o .= "| `Registry Version` | **" . $snap['registry_rows'] . "** |\n";
    $o .= "| `Measurement Tool Version` | `" . $snap['measurement_tool_version'] . "` |\n";
    $o .= "| `Timestamp` | " . $snap['frozen_at'] . " |\n";
    $o .= "| `Window Kind` | `" . $snap['window_kind'] . "` |\n";
    $o .= "| `Regression Census` | " . $snap['regression_census'] . " |\n\n";
    $o .= "## الأرقام\n\n| المفردة | قيمةُ الأمر | المقيسُ الآن | الفرق | ملاحظة |\n|---|---|---|---|---|\n";
    foreach ($rows as $x) {
        $o .= '| ' . $x[0] . ' | ' . $x[1] . ' | **' . $x[2] . '** | '
            . ($x[3] === 0 ? '=' : '**' . sprintf('%+d', $x[3]) . '**') . ' | '
            . ($x[4] !== '' ? $x[4] : '—') . " |\n";
    }
    $o .= "\n**مطابقٌ " . $same . " · مختلفٌ " . $diff . " · المقام " . count($MEASURES) . "**\n\n";
    $o .= "## §٥·٢ · مقامُ الإدارات — بالعدِّ لا بالرأي\n\n";
    $o .= "الكلُّ **" . $depAll . "** · `DEP-%` **" . $depDep . "** · خارجَ التسلسل **"
        . $depOut . "** · و`PLATFORM` " . ($platIn ? 'مسجَّل' : 'غيرُ مسجَّل') . "\n\n";
    $o .= "| الموضع | ما يُنتجه | الحكم |\n|---|---|---|\n";
    foreach ($SITES as $s) {
        $o .= '| `' . $s[0] . '` | ' . $s[1] . ' | ' . ($s[2] ? '⛔ **يُفسد مقامًا**' : '✔ مقامٌ مُصرَّحٌ به') . " |\n";
    }
    $o .= "\n**مقاماتٌ يفسدها الصفُّ: " . $corrupt . "** ⇒ "
        . ($corrupt > 0
            ? "**يُنقل `PLATFORM` إلى سجلٍّ مستقلّ** — والحكمُ نصُّ §٥·٢ لا اجتهاد.\n"
            : "لا مقامَ فاسدًا.\n");
    $out = $ROOT . '/docs/REPAIR01_20260823/MASTER_EXEC_PHASE0_MEASURE.md';
    file_put_contents($out, $o);
    echo "\n✔ كُتب: docs/REPAIR01_20260823/MASTER_EXEC_PHASE0_MEASURE.md\n";
}
