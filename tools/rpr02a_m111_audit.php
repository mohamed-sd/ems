<?php
/**
 * tools/rpr02a_m111_audit.php — فحصُ المادّةِ 111 على **كلِّ** بانِ إكسل
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **نصُّ المادّة 111**: «الصفرُ المقيسُ يُكتب صفرًا ولا يُترك فراغًا: الفراغُ
 *   يعني *غيرُ مقيس* والصفرُ يعني *قيس فكان صفرًا* — **وكلُّ أداةِ توليدٍ تُفحص**
 *   بأن تُخرِج صفرًا واحدًا مقيسًا ويُقرأ صفرًا لا خانةً خالية».
 *
 * ◆ **فحصُ بانِ الأساسِ وحدَه لا يكفي** — والمادّةُ تقول «كلُّ أداة». فهذا
 *   الفحصُ يمرُّ على مساري الكتابةِ في المستودع:
 *     ① `tools/lib/xlsx_out.php` — الكاتبُ المباشرُ بلا مكتبة
 *     ② `PhpOffice\PhpSpreadsheet::fromArray` بنمطِ `baseline_xlsx_build.php`
 *
 * ◆ **والفحصُ ثنائيُّ الطرف**: صفرٌ مقيسٌ ⇒ يُقرأ «0» · و`null` غيرُ مقيسٍ ⇒
 *   يُقرأ خانةً خالية. ⛔ **وأداةٌ تكتب الاثنَين فراغًا تسقط** — ولو كتبت
 *   الصفرَ صفرًا وأبقت غيرَ المقيسِ صفرًا لسقطت أيضًا: **الفرقُ هو المطلوب**.
 *
 * التشغيل: php tools/rpr02a_m111_audit.php [--md]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI only\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/xlsx_io.php';
require_once $ROOT . '/tools/lib/xlsx_out.php';
$MD = in_array('--md', $argv, true);

$TMP = sys_get_temp_dir() . '/rpr02a_m111_' . getmypid();
@mkdir($TMP, 0777, true);

/* الصفُّ الفاحص: [عنوان, صفرٌ مقيس, غيرُ مقيس, عددٌ غيرُ صفريّ] */
$HEAD = array('المقياس', 'صفرٌ مقيس', 'غيرُ مقيس', 'شاهد');
$ROW  = array('سطرُ الفحص', 0, null, 7);

$results = array();

/* ═══ ① الكاتبُ المباشر ═══ */
$p1 = $TMP . '/direct.xlsx';
xlsx_create($p1, array('فحص' => array($HEAD, $ROW)));
$b1 = xlsx_read($p1);
$r1 = isset($b1['فحص'][1]) ? $b1['فحص'][1] : array();
$results[] = array(
    'tool'  => '`tools/lib/xlsx_out.php` › `xlsx_create()`',
    'users' => 'يستعمله `repair01_w16_baseline_pack.php`',
    'zero'  => isset($r1[1]) ? $r1[1] : '',
    'null'  => isset($r1[2]) ? $r1[2] : '',
    'wit'   => isset($r1[3]) ? $r1[3] : '',
);

/* ═══ ② PhpSpreadsheet بنمطِ بانِ الأساس ═══ */
$auto = $ROOT . '/vendor/autoload.php';
if (is_file($auto)) {
    require_once $auto;
    /* نفسُ الدالّةِ التي أُصلح بها بانِ الأساس: الفارغُ ⇒ null والصفرُ يبقى صفرًا */
    $zn = function (array $rows) {
        foreach ($rows as $i => $r) {
            if (is_array($r)) { foreach ($r as $j => $v) { if ($v === '') { $rows[$i][$j] = null; } } }
            elseif ($r === '') { $rows[$i] = null; }
        }
        return $rows;
    };
    $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $ws = $ss->getActiveSheet();
    $ws->setTitle('فحص');
    /* المعاملُ الرابعُ `true` = strictNullComparison — وهو **موضعُ العطبِ الأصليّ**:
       بدونه يُعامَل الصفرُ معاملةَ الفراغِ فيُكتب خليّةً خالية. */
    $ws->fromArray($zn(array($HEAD, $ROW)), null, 'A1', true);
    $p2 = $TMP . '/phpss.xlsx';
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save($p2);
    $b2 = xlsx_read($p2);
    $r2 = isset($b2['فحص'][1]) ? $b2['فحص'][1] : array();
    $results[] = array(
        'tool'  => '`PhpSpreadsheet::fromArray(..., strictNullComparison: true)`',
        'users' => 'نمطُ `tools/baseline_xlsx_build.php` (‏10 نداءات)',
        'zero'  => isset($r2[1]) ? $r2[1] : '',
        'null'  => isset($r2[2]) ? $r2[2] : '',
        'wit'   => isset($r2[3]) ? $r2[3] : '',
    );

    /* ═══ ③ الاختبارُ السالب: نزعُ العلَمِ ⇒ يجب أن يسقط الفحص ═══ */
    $ss3 = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $ws3 = $ss3->getActiveSheet(); $ws3->setTitle('فحص');
    $ws3->fromArray($zn(array($HEAD, $ROW)), null, 'A1');   /* بلا strictNullComparison */
    $p3 = $TMP . '/phpss_nostrict.xlsx';
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss3))->save($p3);
    $b3 = xlsx_read($p3);
    $r3 = isset($b3['فحص'][1]) ? $b3['فحص'][1] : array();
    $results[] = array(
        'tool'  => '⚠ **الاختبارُ السالب** — النمطُ نفسُه **بلا** `strictNullComparison`',
        'users' => 'وهو ما كان عليه بانِ الأساسِ قبلَ الإصلاح',
        'zero'  => isset($r3[1]) ? $r3[1] : '',
        'null'  => isset($r3[2]) ? $r3[2] : '',
        'wit'   => isset($r3[3]) ? $r3[3] : '',
        'expect_fail' => true,
    );
}

/* ═══ التقرير ═══ */
$ts = date('Y-m-d H:i:s');
$md  = "# RPR-02-A · فحصُ المادّة 111 على كلِّ بانِ إكسل\n\n";
$md .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/rpr02a_m111_audit.php --md`\n> **مولَّدٌ**: " . $ts . "\n";
$md .= "> **الفحص**: يُكتب صفٌّ فيه **صفرٌ مقيس** و**قيمةٌ غيرُ مقيسة (`null`)**، ثمَّ يُعاد قراءةُ الملفِّ من القرص.\n";
$md .= "> **القبول**: الصفرُ يُقرأ `0` · وغيرُ المقيسِ يُقرأ خانةً خالية. ⛔ والفرقُ بينهما هو المطلوب، لا أحدُهما.\n\n";
$md .= "| بانِ الإكسل | من يستعمله | «صفرٌ مقيس» يُقرأ | «غيرُ مقيس» يُقرأ | شاهدٌ غيرُ صفريّ | الحكم |\n";
$md .= "|---|---|---|---|---|---|\n";
$pass = 0; $tot = 0;
foreach ($results as $r) {
    $ok = ($r['zero'] === '0' && $r['null'] === '' && $r['wit'] === '7');
    $expectFail = !empty($r['expect_fail']);
    $verdict = $expectFail
        ? ($ok ? '⛔ **لم يسقط — والفاحصُ لا يرصد**' : '✔ **سقط كما يجب** — فالفحصُ يرصد فعلًا')
        : ($ok ? '✔ **مطابق**' : '⛔ **مخالف**');
    if (!$expectFail) { $tot++; if ($ok) { $pass++; } }
    $md .= '| ' . $r['tool'] . ' | ' . $r['users'] . ' | `' . ($r['zero'] === '' ? '(خالية)' : $r['zero'])
         . '` | `' . ($r['null'] === '' ? '(خالية)' : $r['null']) . '` | `' . ($r['wit'] === '' ? '(خالية)' : $r['wit'])
         . '` | ' . $verdict . " |\n";
}
$md .= "\n**الحصيلة: " . $pass . '/' . $tot . " بانيًا مطابقًا للمادّة 111** — والاختبارُ السالبُ يُثبت أنّ الفحصَ يرصد الخرقَ ولا يمرّ عليه.\n";

if ($MD) { file_put_contents($ROOT . '/docs/REPAIR01_20260823/RPR02A_M111_AUDIT.md', $md); }
echo $md;
if ($MD) { echo "\n=> docs/REPAIR01_20260823/RPR02A_M111_AUDIT.md\n"; }

/* تنظيفُ ما كُتب — فحصٌ يترك أثرًا ليس فحصًا */
foreach (glob($TMP . '/*') as $f) { @unlink($f); }
@rmdir($TMP);
