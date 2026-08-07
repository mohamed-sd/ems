<?php
/**
 * tools/cmp03_gov_check.php — فحص «السبعة الحاكمة» (CMP-03 · الحزام)
 * ───────────────────────────────────────────────────────────────────────────
 * لا شاشةَ مستندٍ مقارنةً ينقصها عمودُ حوكمةٍ يطلبه تصميمُها — أي: لكل شاشةٍ
 * مقارنةٍ missGov = 0 بحكم منهج 99 نفسه. يخرج 1 عند أي مخالفة (يصلح خطافًا).
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
require_once __DIR__ . '/cmp03_lib.php';
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$ROOT = dirname(__DIR__);

/* ── النطاقُ المعلَن (DEF-005 · update0009): لا قفزَ صامتًا ──────────────────
   الشاشاتُ غيرُ الجدولية (لا رؤوسَ مقارنةٍ فيها) تُستثنى بقائمةٍ معلَنةٍ بسببها —
   وأيُّ شاشةٍ تسقط من الفحص وليست هنا تُحسب مخالفةَ نطاقٍ لا سكوتًا. */
$DECLARED_EXCLUDED = array(
    'messages.php'        => 'محادثات — لا جدولَ مستند',
    'org_structure.php'   => 'مخططُ هيكلٍ تفاعلي — لا جدولَ مستند',
    'dept_achievement.php' => 'لوحةُ مؤشرات — لا جدولَ مستند',
    'readiness.php'       => 'لوحةُ جاهزية — لا جدولَ مستند',
    'ticket_open.php'     => 'نموذجُ إبلاغٍ سياقي — لا جدولَ مستند',
    'reports_index.php'   => 'مركزُ تقارير — بوابةُ روابطَ لا جدول',
    'accountant_desk.php' => 'مكتبُ مهامَّ يومية — بطاقاتٌ لا جدول',
    'my_evaluation.php'   => 'عرضُ تقييمٍ شخصي — لا جدولَ مستند',
    'change_password.php' => 'نموذجُ إعداداتٍ — لا جدولَ مستند',
);

$screens = cmp03_doc_screens($ROOT);
$map = cmp03_file_map($conn);
$bad = 0; $checked = 0; $excluded = 0; $undeclared = array();
foreach ($screens as $cf => $sc) {
    if (!isset($map[$cf]) || $map[$cf]['state'] === 'soon' || !$map[$cf]['real_path']) {
        if (!isset($DECLARED_EXCLUDED[$cf])) { $undeclared[] = "$cf (بلا وجهة حية)"; } else { $excluded++; }
        continue;
    }
    $heads = cmp03_extract_heads($ROOT . '/' . $map[$cf]['real_path']);
    if ($heads === null || !$heads) { // لا رؤوسَ مقارنةٍ — يُقبل بإعلانٍ فقط
        if (!isset($DECLARED_EXCLUDED[$cf])) { $undeclared[] = "$cf ({$sc['title']} → {$map[$cf]['real_path']})"; } else { $excluded++; }
        continue;
    }
    $checked++;
    $j = cmp03_judge($sc['cols'], $heads);
    if ($j['missGov']) {
        $bad++;
        echo "✘ {$sc['title']} ($cf → {$map[$cf]['real_path']}): ناقصُ الحوكمة = " . implode(' · ', $j['missGov']) . "\n";
    }
}
foreach ($undeclared as $u) { echo "✘ خارجَ النطاقِ بلا إعلان: $u\n"; }
echo "────────────────────────────────────────\n";
echo 'النطاق: ' . count($screens) . " شاشةً قانونية = $checked فُحصت + $excluded مستثناةٌ معلَنةٌ بسببها"
   . (count($undeclared) ? ' + ' . count($undeclared) . ' سقطت بلا إعلان' : '') . "\n";
echo "شاشاتٌ فُحصت: $checked · مخالفة: $bad\n";
$fail = ($bad > 0 || count($undeclared) > 0);
echo !$fail ? "الحكم: ✔ لا شاشةَ مستندٍ بلا حوكمتها الحاكمة — والنطاقُ معلَنٌ كاملًا\n"
            : "الحكم: ✘ حوكمةٌ ناقصةٌ أو نطاقٌ غيرُ معلَن\n";
exit(!$fail ? 0 : 1);
