<?php
/**
 * tools/cmp03_build_soon.php — مطبّق CMP-03 الموجة ⑥ (بناء شاشات «قريبًا») — v2
 * ───────────────────────────────────────────────────────────────────────────
 * v1: بنى الـ51 هيكلًا (أعمدة المستند حرفيًّا + حوكمة بشرائحها + فائض منهار).
 * v2: شاشات CRUD حية — زرُّ إضافةٍ في الرأس الموحد يفتح فورم `.allforms`
 * الموحد، ومعالجُ حفظٍ يودع الصف في مخزن `cmp03_screen_rows` (سجلٌّ بينيٌّ
 * حقيقيٌّ معزول بالكيان حتى يولد جدول الشاشة الأصلي — هجرة 2026_11_12)،
 * والعرض من المخزن بأعمدة الحوكمة الآلية حية (الكيان · المُنشئ · تاريخ
 * الإنشاء · الحالة) وسائرها من الحمولة أو «—».
 *
 * التشغيل: php tools/cmp03_build_soon.php [--apply] [--rebuild] [--screen=x.php]
 *   --rebuild: يعيد توليد ما بناه v1 (يتحقق من بصمة التوليد قبل الكتابة فوقه).
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
$REBUILD = in_array('--rebuild', $argv, true);
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

/* أعمدةُ حوكمةٍ آليةٌ لا يُدخلها المستخدم — تُملأ من الجلسة والمخزن */
$AUTO_GOV = array('الكيان','المُنشئ — الاسم والصفة','تاريخ الإنشاء','مفتاح منع التكرار',
    'سجل الاطّلاع','معكوس بـ','عكس عن','الجهة المُنشئة','العملة الأساسية');

$screens = cmp03_doc_screens($ROOT);
$map = cmp03_file_map($conn);
$registry = ems_gov_registry();
$govByNorm = array();
foreach ($registry as $k => $def) { $govByNorm[cmp03_norm($def[0])] = array($k, $def[1], $def[2]); }
$autoNorm = array();
foreach ($AUTO_GOV as $g) { $autoNorm[cmp03_norm($g)] = 1; }

/** نوع حقل الإدخال من تسمية العمود */
function cmp03_input_type($label) {
    if (mb_strpos($label, 'تاريخ') !== false || mb_strpos($label, 'بداية') !== false
        || mb_strpos($label, 'نهاية') !== false || mb_strpos($label, 'السريان') !== false) { return 'date'; }
    foreach (array('نسبة','قيمة','مبلغ','عدد','ساعات','كمية','رصيد','إجمالي','سعر','مدة','رأس المال','قسط','معدل','هامش','وزن','حمولة','طاقة','تكلفة','أجر','راتب') as $w) {
        if (mb_strpos($label, $w) !== false) { return 'decimal'; }
    }
    return 'text';
}

$built = 0; $mapped = 0; $manual = array();

foreach ($screens as $cf => $sc) {
    if ($onlyScreen !== null && $cf !== $onlyScreen) { continue; }
    $st = isset($map[$cf]) ? $map[$cf]['state'] : null;
    $rp = isset($map[$cf]) ? $map[$cf]['real_path'] : null;
    $isOurBuild = ($st === 'live' && $rp !== null && strpos((string) ($map[$cf]['note'] ?? ''), 'CMP-03 ⑥') === 0);

    $fresh = ($st === 'soon' || $rp === null);
    if (!$fresh && !($REBUILD && $isOurBuild)) { continue; }

    $esCf = mysqli_real_escape_string($conn, $cf);

    /* ① المواءمات الخمس (وضع البناء الأول فقط) */
    if ($fresh && isset($REMAP[$cf])) {
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
    if (!$fresh && isset($REMAP[$cf])) { continue; }

    /* ② البناء / إعادة التوليد */
    $sheetNo = substr($sc['owner'], 0, 2);
    $dir = isset($DIRS[$sheetNo]) ? $DIRS[$sheetNo] : 'main';
    $real = $fresh ? ($dir . '/' . $cf) : $rp;
    $path = $ROOT . '/' . $real;
    if ($fresh && file_exists($path)) { $manual[] = "$real موجودٌ على القرص وليس في القاموس — يُحسم يدويًّا"; continue; }
    if (!$fresh) {
        $cur = @file_get_contents($path);
        if ($cur === false || strpos($cur, 'CMP-03 ⑥') === false) { $manual[] = "$real ليس من توليدنا — لا يُكتب فوقه"; continue; }
    }
    if (!$sc['cols']) { $manual[] = "$cf بلا أعمدةٍ في المستند"; continue; }

    $title = $sc['title'];
    $icon = ems_nav_icon_for($title, $cf);
    $tid = preg_replace('/[^a-z0-9_]/', '', str_replace('.php', '', $cf)) . 'Table';
    $colCount = count($sc['cols']);

    /* الحقول القابلة للإدخال (بترتيب المستند) والرؤوس */
    $fields = array(); // idx => label
    $ths = array(); $pos = 0;
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
        if (!isset($autoNorm[$n])) { $fields[] = $col; }
    }
    /* الخلايا مرسومة من PHP كاملةً فالحاشي المركزي يتخطاها (لا نقصان)، وسمات
       data-gov تبقي تمييز الشرائح والطيَّ المركزي للفائض حيثما لا تعمل Responsive. */

    $fieldsExport = var_export($fields, true);
    $colsExport = var_export($sc['cols'], true);

    $formGroups = '';
    foreach ($fields as $i => $lbl) {
        $t = cmp03_input_type($lbl);
        $req = $i === 0 ? ' required' : '';
        if (cmp03_norm($lbl) === cmp03_norm('الحالة')) {
            $formGroups .= "                <div class=\"form-group\"><label>{$lbl}</label>\n"
                . "                    <select name=\"f{$i}\"><option value=\"مسودة\">مسودة</option><option value=\"قيد المراجعة\">قيد المراجعة</option><option value=\"معتمد\">معتمد</option><option value=\"موقوف\">موقوف</option><option value=\"ملغي\">ملغي</option></select></div>\n";
        } elseif ($t === 'date') {
            $formGroups .= "                <div class=\"form-group\"><label>{$lbl}</label>\n"
                . "                    <input type=\"date\" name=\"f{$i}\"{$req}></div>\n";
        } elseif ($t === 'decimal') {
            /* نصّي لا رقمي: type=number يرفض الفاصلة العربية بصمت (درس currencies_fin) */
            $formGroups .= "                <div class=\"form-group\"><label>{$lbl}</label>\n"
                . "                    <input type=\"text\" inputmode=\"decimal\" name=\"f{$i}\"{$req} placeholder=\"0\"></div>\n";
        } else {
            $formGroups .= "                <div class=\"form-group\"><label>{$lbl}</label>\n"
                . "                    <input type=\"text\" name=\"f{$i}\"{$req} maxlength=\"190\"></div>\n";
        }
    }

    $php = <<<PHP
<?php
/**
 * {$real} — {$title} (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: {$sc['owner']} · الأعمدة {$colCount} بترتيب المستند وطبقة
 * الحوكمة بشرائحها. الصفوف في المخزن البيني `cmp03_screen_rows` (معزول
 * بالكيان) حتى يولد جدول الشاشة الأصلي — مهمة اللحاق في
 * docs/CMP03_FOLLOWUP_SOURCES_ar.md. الفائض فوق 22 عمودًا منهارٌ (توصية ①).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset(\$_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
require_once '../includes/permissions_helper.php';
require_once '../includes/gov_columns.php';

\$company_id     = isset(\$_SESSION['user']['company_id']) ? intval(\$_SESSION['user']['company_id']) : 0;
\$is_super_admin = (strval(\$_SESSION['user']['role'] ?? '') === '-1');
\$uid            = intval(\$_SESSION['user']['id'] ?? 0);
if (!\$is_super_admin && \$company_id <= 0) {
    header("Location: ../login.php?msg=غير+مصرح");
    exit();
}

\$CANONICAL = '{$cf}';
\$COLS   = {$colsExport};
\$FIELDS = {$fieldsExport};

/* ── الحفظ: فورم الإضافة الموحد → المخزن البيني ─────────────────────────── */
if (\$_SERVER['REQUEST_METHOD'] === 'POST' && (\$_POST['cmp03_action'] ?? '') === 'add') {
    \$payload = array();
    foreach (\$FIELDS as \$i => \$lbl) {
        \$v = trim((string) (\$_POST['f' . \$i] ?? ''));
        if (\$v !== '') { \$payload[\$lbl] = \$v; }
    }
    \$status = \$payload['الحالة'] ?? 'مسودة';
    \$creator = trim((string) (\$_SESSION['user']['name'] ?? '')) ?: ('مستخدم #' . \$uid);
    \$st = \$conn->prepare("INSERT INTO cmp03_screen_rows
        (company_id, canonical_file, payload, status, is_seed, created_by, created_by_name)
        VALUES (?, ?, ?, ?, 0, ?, ?)");
    \$json = json_encode(\$payload, JSON_UNESCAPED_UNICODE);
    \$st->bind_param('isssis', \$company_id, \$CANONICAL, \$json, \$status, \$uid, \$creator);
    \$ok = \$st->execute();
    \$st->close();
    header('Location: ' . basename(__FILE__) . '?msg=' . rawurlencode(\$ok ? 'حُفظ الصف ✅' : 'تعذر الحفظ ❌'));
    exit();
}

/* ── القراءة: صفوف الكيان لهذه الشاشة ───────────────────────────────────── */
\$rows = array();
\$sql = "SELECT id, payload, status, created_by_name, created_at, is_seed
          FROM cmp03_screen_rows
         WHERE canonical_file = ?" . (\$is_super_admin && \$company_id <= 0 ? '' : ' AND company_id = ?') . "
         ORDER BY id DESC LIMIT 500";
\$st = \$conn->prepare(\$sql);
if (\$is_super_admin && \$company_id <= 0) { \$st->bind_param('s', \$CANONICAL); }
else { \$st->bind_param('si', \$CANONICAL, \$company_id); }
\$st->execute();
\$rs = \$st->get_result();
while (\$x = \$rs->fetch_assoc()) {
    \$x['payload'] = json_decode((string) \$x['payload'], true) ?: array();
    \$rows[] = \$x;
}
\$st->close();

\$govCtx = ems_gov_ctx();
\$entityName = \$govCtx['values']['entity'] ?? '—';

/** قيمة خلية العمود من الصف — الحوكمة الآلية حية وسائرها من الحمولة أو «—» */
function cmp03_cell(\$col, \$row, \$entityName) {
    \$n = cmp03_screen_norm(\$col);
    if (\$n === cmp03_screen_norm('الكيان')) { return \$entityName; }
    if (\$n === cmp03_screen_norm('المُنشئ — الاسم والصفة') || \$n === cmp03_screen_norm('الجهة المُنشئة')) {
        return \$row['created_by_name'] ?: '—';
    }
    if (\$n === cmp03_screen_norm('تاريخ الإنشاء')) { return \$row['created_at']; }
    if (\$n === cmp03_screen_norm('الحالة')) { return \$row['status']; }
    if (\$n === cmp03_screen_norm('مفتاح منع التكرار')) { return 'CMP03-' . intval(\$row['id']); }
    if (isset(\$row['payload'][\$col]) && \$row['payload'][\$col] !== '') { return \$row['payload'][\$col]; }
    return '—';
}
/** تطبيع محلي خفيف (مرآة cmp03_norm دون جر مكتبة الأدوات للويب) */
function cmp03_screen_norm(\$s) {
    \$s = preg_replace('/\\s+/u', ' ', trim((string) \$s));
    \$s = str_replace(array('أ','إ','آ'), 'ا', \$s);
    \$s = str_replace('ة', 'ه', \$s);
    \$s = str_replace('ى', 'ي', \$s);
    return preg_replace('/[ًٌٍَُِّْ]/u', '', \$s);
}

\$page_title = 'إيكوبيشن | {$title}';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    \$header_title = '{$title}';
    \$header_icon = '{$icon}';
    \$header_actions = array(
        array('tag' => 'button', 'id' => 'cmp03AddBtn', 'class' => '', 'icon' => 'fa fa-plus',
              'label' => 'إضافة', 'title' => 'إضافة صف جديد', 'attrs' => 'type="button"'),
    );
    \$header_back = false;
    include '../includes/page_header.php';
    if (isset(\$_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars((string) \$_GET['msg'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    ?>

    <!-- فورم الإضافة الموحد (ems-forms) — مطويٌّ حتى زرِّ الرأس -->
    <form method="post" action="" class="allforms" id="cmp03AddForm">
        <input type="hidden" name="cmp03_action" value="add">
        <div class="card"><div class="card-header">
            <h5><i class="fa fa-plus"></i> إضافة — {$title}</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
{$formGroups}            </div></div>
            <div style="margin-top:12px;display:flex;gap:10px">
                <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-secondary" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="{$tid}">
            <thead><tr>
PHP;
    $php .= "\n" . implode("\n", $ths) . "\n";
    $php .= <<<PHP
            </tr></thead>
            <tbody>
            <?php if (!\$rows): ?>
                <tr><td colspan="{$colCount}" class="text-center text-muted">لا بياناتَ بعدُ — أضف أول صفٍّ بزر «إضافة»</td></tr>
            <?php else: foreach (\$rows as \$r): ?>
                <tr<?php echo \$r['is_seed'] ? ' data-seed="1"' : ''; ?>>
                    <?php foreach (\$COLS as \$c): \$v = cmp03_cell(\$c, \$r, \$entityName); ?>
                    <td<?php echo \$v === '—' ? ' class="ems-gov-empty"' : ''; ?>><?php echo htmlspecialchars((string) \$v, ENT_QUOTES, 'UTF-8'); ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div></div>
</div>

<script>
(function () {
    var btn = document.getElementById('cmp03AddBtn');
    var form = document.getElementById('cmp03AddForm');
    var cancel = document.getElementById('cmp03CancelBtn');
    if (btn && form) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            form.classList.toggle('allforms-visible');
            if (form.classList.contains('allforms-visible')) {
                form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
    }
    if (cancel && form) {
        cancel.addEventListener('click', function () { form.classList.remove('allforms-visible'); });
    }
})();
</script>

PHP;

    echo ($APPLY ? '✔ ' : '⏸ ') . ($fresh ? 'بناء' : 'إعادة توليد v2') . ": $cf ← $real ($colCount عمودًا · " . count($fields) . " حقلًا)\n";
    if ($APPLY) {
        if (!is_dir(dirname($path))) { @mkdir(dirname($path), 0777, true); }
        $prev = $fresh ? null : file_get_contents($path);
        file_put_contents($path, $php);
        $lint = shell_exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1');
        if (strpos((string) $lint, 'No syntax errors') === false) {
            echo "‼ خطأ صياغة في $real — تراجُع\n$lint\n";
            if ($prev !== null) { file_put_contents($path, $prev); } else { @unlink($path); }
            continue;
        }
        if ($fresh) {
            mysqli_query($conn, "UPDATE nav09_file_map
                SET state='live', real_path='" . mysqli_real_escape_string($conn, $real) . "',
                    note='CMP-03 ⑥: بُنيت بتصميم SCR-DES — مصدر البيانات مهمة لحاق'
                WHERE canonical_file='$esCf'");
        }
    }
    $built++;
}

if ($manual) {
    echo "\n── للمعالجة اليدوية:\n";
    foreach ($manual as $m) { echo "   ⚠ $m\n"; }
}
echo "\n" . ($APPLY ? 'وُلّد' : 'سيُولّد') . " $built شاشةً" . ($mapped ? " ووُوئمت $mapped" : '') . ".\n";
