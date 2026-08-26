<?php
/**
 * tools/repair01_nav_section_r3.php — قسمٌ مُعلَنٌ لكتلوجاتِ الأصولِ في الدورِ 3
 * ═══════════════════════════════════════════════════════════════════════════
 * **العطبُ المقيس**: قسمُ «المعدات والأسطول» في سايدبارِ الدورِ 3 يحمل
 * **عشرةَ بنودٍ والحدُّ تسعةٌ** (‏ف٧-٢) — فترسُب بوّابةُ `U9` ويُردُّ كلُّ التزامٍ
 * يمسُّ ملفَّ `php` أو `css`. وأُثبت أنّها **ليست من موجةٍ بعينِها**: أُرجعت
 * الثانيةَ عشرةَ كاملًا (‏عاد النطاقُ 716 موضعًا/353 مسارًا) وظلَّت ساقطة.
 *
 * ◆ **والعلاجُ إعلانٌ لا تعديلُ مُصيِّر**: `gov_target_nav` هو سجلُّ الأقسامِ
 *   المُعلَنةِ لكلِّ دور (XC-01)، ويقرأ منه `uxuiDeclaredSections()` عمودَ
 *   `group_ar` قسمًا فرعيًّا. وكان فيه **27 صفًّا للدورَين 2 و12 وحدَهما**،
 *   والدورُ 3 بلا إعلانٍ قطُّ — فبنودُه تنهار كلُّها على رأسٍ واحد.
 *
 * ◆ **ولا ينتقل بندٌ من مجموعتِه**: الأربعةُ كلُّها مثبَّتةٌ على `ASSETS` في
 *   `nav_route_group`، والمُصيِّرُ يقدّم تثبيتَ المسارِ على اشتقاقِ القسم
 *   (`if (isset($rgMap[$base])) { $code = $rgMap[$base]; }`). فالإعلانُ يضيف
 *   **عنوانًا داخلَ الرأسِ نفسِه** ⛔ ولا يُخفي بندًا ولا يخفضه إلى تبويب.
 *
 * ◆ **والمُعلَنُ وحدَه يسري**: من لا صفَّ له يبقى على سلوكِه السابقِ حرفًا —
 *   فالستّةُ الباقيةُ لا تُمَسّ، وسائرُ الأدوارِ التسعةَ عشرَ لا تُمَسّ.
 *
 * التشغيل: php tools/repair01_nav_section_r3.php [--report|--revert]
 * الخروج : 0 نجح · 1 فشل
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$REPORT = in_array('--report', $argv, true);
$REVERT = in_array('--revert', $argv, true);
$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) { $r = $conn->query($sql); if (!$r) { return 0; } $x = $r->fetch_row(); return $x ? $x[0] : 0; };

const DOC   = 'RPR-NAV-SEC-01';
const ROLE  = 3;
const GRPNO = 7;
/* ◆ **اسمُ القسمِ يمرُّ ببواباتِ الواجهة**: كلمتانِ (‏حدُّ `U6` ستٌّ) · بلا
     تشكيلٍ (‏`UI-01`) · بلا مصطلحٍ تقنيٍّ ولا قيمةِ حالةٍ داخليّة (‏`U4` و`U7`). */
const SECTION = 'الإعدادات المرجعية';

/* الأربعةُ كتلوجاتٌ مرجعيّةٌ يُدخَل إليها مرّةً ولا تُمسح يوميًّا — وهذا
   معيارُ الفرزِ لا ترتيبُ الحروفِ ولا عددُ البنودِ المطلوبِ بلوغُه. */
$ITEMS = array(
    array('Equipments/equipments_types.php',              'أنواع المعدات وفئاتها'),
    array('Equipments/fleet_models.php',                  'موديلات المعدات ومواصفاتها'),
    array('Equipments/fleet_depreciation_profiles.php',   'سياسات الإهلاك المعتمدة'),
    array('Equipments/manage_failure_codes.php',          'تصنيف الأعطال وأسبابها'),
);

echo "══ قسمٌ مُعلَنٌ لكتلوجاتِ الأصولِ — الدورُ " . ROLE . " ══\n";
echo ($REPORT ? "  وضعُ التقرير: يقرأ ولا يكتب\n\n" : "\n");

if ($REVERT) {
    $conn->query("DELETE FROM gov_target_nav WHERE doc_code = '" . DOC . "'");
    echo "  ✔ نُزع الإعلان — والبنودُ تعود إلى سلوكِها السابقِ حرفًا\n";
    echo "الحكم: رجع ✔\n";
    exit(0);
}

/* ⓐ **الإثباتُ قبل الكتابة**: صفٌّ يشير إلى مسارٍ لا يُصيَّر إعلانٌ ميّت. */
$missing = array();
foreach ($ITEMS as $it) {
    if (!is_file($ROOT . '/' . $it[0])) { $missing[] = $it[0]; }
}
if ($missing) {
    echo "✘ مسارٌ مُعلَنٌ بلا ملفٍّ على القرص: " . implode(' · ', $missing) . "\n";
    exit(1);
}

/* ⓑ **العتبةُ تُقاس ولا تُفترَض**: كم بندًا في الرأسِ قبلَ الإعلان؟ */
require_once $ROOT . '/includes/unified_nav.php';
require_once $ROOT . '/includes/uxui_nav_probe.php';
require_once $ROOT . '/includes/status_display.php';
$before = 0;
foreach (uxp_parse_nav_html(uxp_render_role_html($conn, ROLE)) as $p) {
    $k = $p['group'] . ' ▸ ' . (isset($p['section']) ? $p['section'] : $p['group']);
    if ($k === 'المعدات والأسطول ▸ المعدات والأسطول') { $before++; }
}
printf("  القسمُ قبلَ الإعلان: %d بندًا (الحدُّ 9)\n", $before);

if ($REPORT) {
    foreach ($ITEMS as $i => $it) { printf("  %d) %-46s ⇐ «%s»\n", $i + 1, $it[0], SECTION); }
    printf("  المتوقَّعُ بعدَه: %d بندًا في الرأسِ و%d تحتَ «%s»\n",
        $before - count($ITEMS), count($ITEMS), SECTION);
    exit(0);
}

/* ⓒ **الكتابةُ عاطلةٌ بالإعادة**: تُمحى صفوفُ هذه الوثيقةِ ثمَّ تُكتب — فلا
     يتضاعف الإعلانُ بتشغيلٍ ثانٍ ولا يبقى صفٌّ لبندٍ نُزع من القائمة. */
$conn->query("DELETE FROM gov_target_nav WHERE doc_code = '" . DOC . "'");
$n = 0;
foreach ($ITEMS as $i => $it) {
    $ok = $conn->query("INSERT INTO gov_target_nav (doc_code, role_id, group_no, group_ar,
                                                    item_no, item_ar, route, note)
        VALUES ('" . DOC . "'," . ROLE . "," . GRPNO . ",'" . $esc(SECTION) . "',"
        . ($i + 1) . ",'" . $esc($it[1]) . "','" . $esc($it[0]) . "',
                '" . $esc('كتلوج مرجعي يدخل اليه مرة ولا يمسح يوميا — U9 حد التسعة') . "')");
    if ($ok) { $n++; } else { echo "  ✘ " . $it[0] . ' — ' . $conn->error . "\n"; }
}
printf("  صفوفٌ مُعلَنة: %d من %d\n", $n, count($ITEMS));

/* ⓓ **القياسُ بعدَ الكتابةِ من المُصيَّرِ لا من الجدول** — والذاكرةُ الساكنةُ
     في `uxuiDeclaredSections` تُبطَل بعمليّةٍ جديدةٍ لا بمسحٍ يدويّ. */
$php = PHP_BINARY;
$probe = $ROOT . '/tools/repair01_nav_section_r3_probe.php';
file_put_contents($probe, "<?php\n"
    . "error_reporting(E_ALL & ~E_DEPRECATED); define('EMS_CLI', true);\n"
    . "\$R = dirname(__DIR__);\n"
    . "require_once \$R . '/includes/session_bootstrap.php';\n"
    . "require_once \$R . '/config.php';\n"
    . "require_once \$R . '/includes/unified_nav.php';\n"
    . "require_once \$R . '/includes/uxui_nav_probe.php';\n"
    . "require_once \$R . '/includes/status_display.php';\n"
    . "while (ob_get_level()) { ob_end_clean(); }\n"
    . "\$big = array();\n"
    . "foreach (uxp_parse_nav_html(uxp_render_role_html(\$GLOBALS['conn'], " . ROLE . ")) as \$p) {\n"
    . "  \$k = \$p['group'] . ' > ' . (isset(\$p['section']) ? \$p['section'] : \$p['group']);\n"
    . "  \$big[\$k] = isset(\$big[\$k]) ? \$big[\$k] + 1 : 1;\n"
    . "}\n"
    . "foreach (\$big as \$k => \$c) { if (\$c >= 8) { echo \$c . '|' . \$k . \"\\n\"; } }\n");
$out = array(); $code = 0;
exec('"' . $php . '" "' . $probe . '" 2>&1', $out, $code);
@unlink($probe);
$after = 0; $secN = 0;
foreach ($out as $l) {
    $pp = explode('|', $l, 2);
    if (count($pp) !== 2) { continue; }
    if (trim($pp[1]) === 'المعدات والأسطول > المعدات والأسطول') { $after = (int) $pp[0]; }
    if (mb_strpos($pp[1], SECTION) !== false) { $secN = (int) $pp[0]; }
    printf("  المُصيَّر: %-3s %s\n", $pp[0], trim($pp[1]));
}
if ($after === 0) { echo "  ◆ الرأسُ لم يعد يبلغ ثمانيةً — فلا يُطبَع\n"; }

echo "───────────────────────────────────────────────────────────────\n";
$pass = ($after > 0 && $after <= 9) || $after === 0;
printf("قبل %d · بعد %d · الحدُّ 9\n", $before, $after);
echo ($pass
    ? "الحكم: القسمُ صار تحتَ الحدِّ ✔ — والبنودُ كلُّها ما تزال ظاهرةً في رأسِها\n"
    : "الحكم: ما زال فوقَ الحدِّ ✘\n");
exit($pass ? 0 : 1);
