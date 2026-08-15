<?php
/**
 * tools/fix_gov_spine_screens.php — إتمامُ العمودِ الحاكمِ في شاشاتِ المكتبِ التنفيذيّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ INJ-0416 · INJ-0417 · INJ-0418 · INJ-0419
 *
 * نصُّ القبولِ في الأربعةِ واحدٌ: «عددُ أعمدة الجدول في الشاشة = N ويطابق
 * أسماءَ الوثيقة عمودًا عمودًا».
 *
 * ── لماذا أداةٌ لا أربعُ تعديلاتٍ يدوية ──────────────────────────────────
 * التغييرُ في كلِّ شاشةٍ **أربعةُ مواضعَ يجب أن تتطابق**: `$COLS` و`$COLDB`
 * ورؤوسُ `<thead>` و`colspan` صفِّ الخلوّ. ويدٌ تنسى واحدًا منها تُنتج جدولًا
 * تنزاح خلاياه عن رؤوسِه — وهو عطبٌ أسوأُ من النقصِ لأنَّه **يُقرأ صحيحًا**.
 * فالتحويلُ واحدٌ مكتوبٌ مرةً ويُطبَّق أربعًا، ويُفحص نحويًّا قبل الكتابة.
 *
 * ◆ ولا يُضاف عمودٌ بلا مصدرٍ مخزَّن: تسعةٌ من الخمسةَ عشرَ من أعمدةٍ قائمةٍ في
 *   الجداول، وسبعةٌ أضافتها هجرةُ `2027_04_11_exec_gov_spine_columns`.
 * ◆ والموضعُ **قبل «الحالة»**: الحالةُ آخرُ الجدولِ في تصميمِ CMP-03 كلِّه.
 * ◆ وعاطلةٌ: تُتخطّى الشاشةُ إن كان العمودُ معلَنًا فيها سلفًا.
 *
 * التشغيل: php tools/fix_gov_spine_screens.php [--dry]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
$DRY  = in_array('--dry', $argv, true);

/* عنوانُ العمود · مصدرُه في القاعدة · مفتاحُ data-gov · شرحُ الرأس */
$GOV = array(
    'creator'      => array('المُنشئ — الاسم والصفة', 'من أنشأ المستند وبأي صفة — لا اسم مجرد'),
    'approver'     => array('المعتمِد — الاسم والصفة', 'من اعتمده وبأي صفة'),
    'authority_ref' => array('مرجع التفويض', 'سند صلاحية المعتمِد — تفويض أو سلطة أصلية'),
    'approved_at'  => array('تاريخ الاعتماد', 'لحظة الاعتماد — وبها يقاس زمن الدورة'),
    'created_at'   => array('تاريخ الإنشاء', 'لحظة الإنشاء بالتاريخ والوقت'),
    'parent_ref'   => array('المرجع الأب', 'المستند الذي تولَّد عنه — خيط التتبع'),
);

/* الشاشة ⇒ [ [مفتاحُ الحوكمة, مصدرُ القاعدة], … ] بالترتيبِ المطلوبِ إدراجُه */
$PLAN = array(
    'Portal/ceo_approvals.php' => array(
        array('created_at',  'created_at'),
        array('approved_at', 'approved_at'),
        array('parent_ref',  'source_request_id'),
    ),
    'Portal/ceo_contracts.php' => array(
        array('creator',       '__creator'),
        array('approver',      'approver_name'),
        array('authority_ref', 'approver_authority_ref'),
        array('approved_at',   'approved_at'),
        array('created_at',    'created_at'),
        array('parent_ref',    'contract_id'),
    ),
    'Portal/project_charter.php' => array(
        array('creator',       '__creator'),
        array('authority_ref', 'authority_ref'),
        array('created_at',    'created_at'),
        array('parent_ref',    'contract_ref'),
    ),
    'Portal/ceo_risk.php' => array(
        array('authority_ref', 'authority_ref'),
        array('parent_ref',    'parent_ref'),
    ),
);

/** يكتب بعد فحصٍ نحويٍّ على نسخةٍ مؤقتةٍ — ولا يكتب فارغًا ولا منكمشًا. */
function spine_write($file, $txt, $orig)
{
    if ($txt === '' || strlen($txt) < strlen($orig) * 0.9) { return 'ناتجٌ مشبوهٌ — لا كتابة'; }
    $tmp = sys_get_temp_dir() . '/spine_' . md5($file) . '.php';
    file_put_contents($tmp, $txt);
    $o = array(); $rc = 1;
    @exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($tmp) . ' 2>&1', $o, $rc);
    @unlink($tmp);
    if ($rc !== 0) { return 'رسب الفحصُ النحويّ: ' . implode(' ', $o); }
    return file_put_contents($file, $txt) === false ? 'تعذّرت الكتابة' : '';
}

echo "══ إتمامُ العمودِ الحاكمِ في شاشاتِ المكتبِ التنفيذيّ ══\n\n";
$touched = 0;
foreach ($PLAN as $rel => $adds) {
    $abs = $ROOT . '/' . $rel;
    $src = (string) @file_get_contents($abs);
    if ($src === '') { echo "  ✘ {$rel}: تعذّرت القراءة\n"; continue; }
    $orig = $src;

    /* ① $COLS الحاليّ */
    if (!preg_match('~\$COLS\s*=\s*array\s*\((.*?)\n\);~s', $src, $m)) {
        echo "  ✘ {$rel}: لا مصفوفةَ \$COLS\n"; continue;
    }
    preg_match_all("~=>\s*'([^']*)'~u", $m[1], $cm);
    $cols = $cm[1];
    $before = count($cols);

    /* الجديدُ فقط — والعاطلةُ تتخطّى المعلَنَ سلفًا */
    $new = array();
    foreach ($adds as $a) {
        $label = $GOV[$a[0]][0];
        if (in_array($label, $cols, true)) { continue; }
        $new[] = array('key' => $a[0], 'label' => $label, 'db' => $a[1]);
    }
    if (!$new) { echo "  · {$rel}: لا جديد ({$before} عمودًا)\n"; continue; }

    /* ② القائمةُ النهائية: الجديدُ قبل «الحالة» */
    $statusAt = array_search('الحالة', $cols, true);
    if ($statusAt === false) { $statusAt = count($cols); }
    $final = array_merge(
        array_slice($cols, 0, $statusAt),
        array_map(function ($x) { return $x['label']; }, $new),
        array_slice($cols, $statusAt)
    );

    /* ③ إعادةُ كتابةِ $COLS بمفاتيحَ متتابعة */
    $lines = array();
    foreach ($final as $i => $l) { $lines[] = '  ' . $i . " => '" . $l . "',"; }
    $src = preg_replace('~\$COLS\s*=\s*array\s*\(.*?\n\);~s',
        "\$COLS   = array (\n" . implode("\n", $lines) . "\n);", $src, 1);

    /* ④ $COLDB — صيغتان: مصفوفةٌ حرفيةٌ أو array_merge بـ$DB_FIELDS */
    $dbList = "array('" . implode("', '", array_map(function ($x) { return $x['db']; }, $new)) . "')";
    if (preg_match('~\$COLDB\s*=\s*array_merge\(array\(null\),\s*\$DB_FIELDS,\s*array\(\'__status\'\)\);~', $src)) {
        $src = preg_replace('~\$COLDB\s*=\s*array_merge\(array\(null\),\s*\$DB_FIELDS,\s*array\(\'__status\'\)\);~',
            "\$COLDB = array_merge(array(null), \$DB_FIELDS,\n"
            . "    /* أعمدةُ الحوكمةِ الناقصةُ — عرضٌ فقط، مصادرُها مخزَّنةٌ ولا تُدخَل يدويًّا */\n"
            . '    ' . $dbList . ", array('__status'));", $src, 1);
    } elseif (preg_match('~(\$COLDB\s*=\s*array\(.*?)(\'__status\'\);)~s', $src)) {
        $src = preg_replace('~(\$COLDB\s*=\s*array\(.*?)(\'__status\'\);)~s',
            '$1' . "\n    /* أعمدةُ الحوكمةِ الناقصة — مصادرُها مخزَّنةٌ ولا تُدخَل يدويًّا */\n"
            . "    '" . implode("', '", array_map(function ($x) { return $x['db']; }, $new)) . "',\n    " . '$2',
            $src, 1);
    } else {
        echo "  ✘ {$rel}: لم تُعرَف صيغةُ \$COLDB\n"; continue;
    }

    /* ⑤ رؤوسُ الجدول: تُدرَج قبل رأسِ «الحالة» */
    $ths = '';
    foreach ($new as $x) {
        $ths .= '            <th class="ems-gov-th" data-gov="' . $x['key'] . '" data-slice="1" title="'
              . $GOV[$x['key']][1] . '">' . $x['label'] . "</th>\n";
    }
    $done = false;
    $src = preg_replace_callback('~([ \t]*<th[^>]*>\s*الحالة\s*</th>\n)~u',
        function ($mm) use ($ths, &$done) {
            if ($done) { return $mm[0]; }
            $done = true;
            return $ths . $mm[1];
        }, $src, 1);
    if (!$done) { echo "  ✘ {$rel}: لم يُعثر على رأسِ «الحالة»\n"; continue; }

    /* ⑥ colspan صفِّ الخلوّ يتبع العدَّ الجديد */
    $src = preg_replace('~colspan="' . $before . '"~', 'colspan="' . count($final) . '"', $src);

    if ($DRY) {
        echo "  ⓘ {$rel}: {$before} ⇒ " . count($final) . ' (+' . count($new) . ") — تجربةٌ بلا كتابة\n";
        continue;
    }
    $err = spine_write($abs, $src, $orig);
    if ($err !== '') { echo "  ✘ {$rel}: {$err}\n"; continue; }
    $touched++;
    echo '  ✔ ' . str_pad($rel, 30) . $before . ' ⇒ ' . count($final) . ' (+' . count($new) . ') · '
       . implode(' · ', array_map(function ($x) { return $x['label']; }, $new)) . "\n";
}
echo "\n  شاشاتٌ مُعدَّلة: {$touched}\n";
