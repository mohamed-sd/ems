<?php
/**
 * tools/cmp03_build_soon.php — مطبّق CMP-03 الموجة ⑥ (بناء شاشات «قريبًا»)
 * ───────────────────────────────────────────────────────────────────────────
 * يبني كل شاشةٍ لم تُبنَ بتصميم SCR-DES حرفيًّا: الأعمدة بترتيب المستند،
 * وطبقة الحوكمة بشرائحها وسماتها، والفائض فوق 22 منهارٌ (توصية ①)، والجدول
 * فارغٌ بعنصرٍ نائبٍ حتى يُربط مصدر بياناته (توصية ③ — مهمة لحاق مسجلة).
 * ثم يقلب صف nav09_file_map (state=live · real_path) فيتحول رابط soon.php
 * تلقائيًّا (SCR-01 §5).
 *
 * مواءمات J-07: خمس شاشاتٍ لها ملفٌّ حيٌّ عنوانه عنوانُها وغيرُ مُطالَبٍ به —
 * تُربط ربطًا (state=mapped) ولا يُبنى لها مكرر.
 *
 * التشغيل: php tools/cmp03_build_soon.php [--apply] [--screen=x.php]
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
require_once __DIR__ . '/cmp03_lib.php';
require_once __DIR__ . '/../includes/gov_columns.php';
require_once __DIR__ . '/../includes/nav_icon_map.php';

$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$ROOT = dirname(__DIR__);
$APPLY = in_array('--apply', $argv, true);
$onlyScreen = null;
foreach ($argv as $a) { if (strpos($a, '--screen=') === 0) { $onlyScreen = substr($a, 9); } }

$MAX_VISIBLE = 22;

/* اجتهاد J-07: مواءماتٌ لا بناء — العنوان الحي = عنوان المستند والمسار غير مُطالَب به */
$REMAP = array(
    'licenses.php'    => 'Governance/licenses_guarantees.php',
    'entities.php'    => 'Governance/entities_registry.php',
    'activation.php'  => 'Governance/activation_patterns.php',
    'delegations.php' => 'Governance/signing_authority.php',
    'margin.php'      => 'Reports/margin_report.php',
);

/* الورقة المالكة → مجلد البناء (بعرف مجلدات الإدارات القائم) */
$DIRS = array(
    '00' => 'Portal',      '01' => 'Operations',  '02' => 'Operations',
    '03' => 'Maintenance', '04' => 'Transport',   '05' => 'Procurement',
    '06' => 'Procurement', '07' => 'Contracts',   '08' => 'Suppliers',
    '09' => 'Workforce',   '10' => 'Equipments',  '11' => 'Employees',
    '12' => 'Finance',     '13' => 'Financing',   '14' => 'Tickets',
    '15' => 'Governance',
);

$screens = cmp03_doc_screens($ROOT);
$map = cmp03_file_map($conn);
$registry = ems_gov_registry();
$govByNorm = array();
foreach ($registry as $k => $def) { $govByNorm[cmp03_norm($def[0])] = array($k, $def[1], $def[2]); }

$built = 0; $mapped = 0; $manual = array();

foreach ($screens as $cf => $sc) {
    if ($onlyScreen !== null && $cf !== $onlyScreen) { continue; }
    $st = isset($map[$cf]) ? $map[$cf]['state'] : null;
    $rp = isset($map[$cf]) ? $map[$cf]['real_path'] : null;
    if ($st !== 'soon' && $rp !== null) { continue; } // مبنيةٌ سلفًا

    $esCf = mysqli_real_escape_string($conn, $cf);

    /* ① المواءمات الخمس */
    if (isset($REMAP[$cf])) {
        $target = $REMAP[$cf];
        echo ($APPLY ? '✔ ' : '⏸ ') . "مواءمة: $cf ← $target (J-07)\n";
        if ($APPLY) {
            mysqli_query($conn, "UPDATE nav09_file_map
                SET state='mapped', real_path='" . mysqli_real_escape_string($conn, $target) . "',
                    note='CMP-03 ⑥ J-07: مواءمة عنوانية — تُعرض على المالك'
                WHERE canonical_file='$esCf'");
        }
        $mapped++;
        continue;
    }

    /* ② البناء */
    $sheetNo = substr($sc['owner'], 0, 2);
    $dir = isset($DIRS[$sheetNo]) ? $DIRS[$sheetNo] : 'main';
    $real = $dir . '/' . $cf;
    $path = $ROOT . '/' . $real;
    if (file_exists($path)) { $manual[] = "$real موجودٌ على القرص وليس في القاموس — يُحسم يدويًّا"; continue; }
    if (!$sc['cols']) { $manual[] = "$cf بلا أعمدةٍ في المستند"; continue; }

    $title = $sc['title'];
    $icon = ems_nav_icon_for($title, $cf);
    $tid = preg_replace('/[^a-z0-9_]/', '', str_replace('.php', '', $cf)) . 'Table';

    $ths = array();
    $pos = 0;
    foreach ($sc['cols'] as $col) {
        $pos++;
        $n = cmp03_norm($col);
        $over = $pos > $MAX_VISIBLE;
        if (isset($govByNorm[$n])) {
            list($k, $slice, $why) = $govByNorm[$n];
            $cls = 'ems-gov-th' . ($over ? ' none' : '');
            $ths[] = '            <th class="' . $cls . '" data-gov="' . $k . '" data-slice="' . $slice . '" title="' . $why . '">' . $col . '</th>';
        } else {
            $cls = $over ? ' class="ems-fn-th none" data-fn="1"' : '';
            $ths[] = '            <th' . $cls . '>' . $col . '</th>';
        }
    }
    $colCount = count($sc['cols']);

    $php = <<<PHP
<?php
/**
 * {$real} — {$title} (CMP-03 ⑥ — بُنيت بتصميم SCR-DES حرفيًّا)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: {$sc['owner']} · الأعمدة {$colCount} بترتيب المستند وطبقة
 * الحوكمة بشرائحها. مصدرُ البيانات يُربط بمهمة لحاقٍ مسجلةٍ في
 * docs/CMP03_FOLLOWUP_SOURCES_ar.md — والفائض فوق 22 عمودًا منهارٌ
 * لسطرٍ تابعٍ أو زرِّ «الأعمدة المطوية» (توصيتا المالك ① و③).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset(\$_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
require_once '../includes/permissions_helper.php';

\$company_id     = isset(\$_SESSION['user']['company_id']) ? intval(\$_SESSION['user']['company_id']) : 0;
\$is_super_admin = (strval(\$_SESSION['user']['role'] ?? '') === '-1');
if (!\$is_super_admin && \$company_id <= 0) {
    header("Location: ../login.php?msg=غير+مصرح");
    exit();
}

\$page_title = 'إيكوبيشن | {$title}';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    \$header_title = '{$title}';
    \$header_icon = '{$icon}';
    \$header_actions = array();
    \$header_back = false;
    include '../includes/page_header.php';
    ?>

    <div class="alert alert-info" style="display:flex;gap:10px;align-items:center">
        <i class="fa fa-plug"></i>
        <span>شاشةٌ وليدةٌ ببنية تصميم SCR-DES الكاملة — مصدرُ بياناتها يُربط بمهمة لحاق (CMP-03 ⑥).</span>
    </div>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="{$tid}">
            <thead><tr>
PHP;
    $php .= "\n" . implode("\n", $ths) . "\n";
    $php .= <<<PHP
            </tr></thead>
            <tbody>
                <tr><td colspan="{$colCount}" class="text-center text-muted">لا بياناتَ بعدُ — تُعرض حين يُربط مصدرُها</td></tr>
            </tbody>
        </table>
        </div>
    </div></div>
</div>

PHP;

    echo ($APPLY ? '✔ ' : '⏸ ') . "بناء: $cf ← $real ($colCount عمودًا)\n";
    if ($APPLY) {
        if (!is_dir(dirname($path))) { @mkdir(dirname($path), 0777, true); }
        file_put_contents($path, $php);
        $lint = shell_exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1');
        if (strpos((string) $lint, 'No syntax errors') === false) {
            echo "‼ خطأ صياغة في $real — حُذف\n$lint\n";
            @unlink($path);
            continue;
        }
        mysqli_query($conn, "UPDATE nav09_file_map
            SET state='live', real_path='" . mysqli_real_escape_string($conn, $real) . "',
                note='CMP-03 ⑥: بُنيت بتصميم SCR-DES — مصدر البيانات مهمة لحاق'
            WHERE canonical_file='$esCf'");
        if (mysqli_affected_rows($conn) === 0) {
            echo "⚠ $cf ليست في القاموس — أُنشئ الملف بلا قلب خريطة\n";
        }
    }
    $built++;
}

if ($manual) {
    echo "\n── للمعالجة اليدوية:\n";
    foreach ($manual as $m) { echo "   ⚠ $m\n"; }
}
echo "\n" . ($APPLY ? 'بُني' : 'سيُبنى') . " $built شاشةً و" . ($APPLY ? 'وُوئمت' : 'ستُواءم') . " $mapped.\n";
