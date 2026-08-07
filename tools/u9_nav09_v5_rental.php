<?php
/**
 * tools/u9_nav09_v5_rental.php — NAV-09 v5: ضمُّ نواة التأجير للوثيقة (DEC-B)
 * ═══════════════════════════════════════════════════════════════════════════
 * يغلق DEF-002/DEF-012: الشاشاتُ الثلاثُ الحيةُ (تقويم الأسطول والحجز · دفتر
 * الأسعار بالشرائح · استغلال الأسطول) تدخل وثيقةَ NAV-09 مرحلةً تاسعةً في ورقة
 * «المبيعات والعقود» + مصفوفةَ العرض + الفهرس — فيعود «التوليدُ من الوثيقة» صادقًا.
 *   ① نسخةُ v4 تُحفظ احتياطًا بجانب الملف قبل أي مساس (خطة الرجوع RL-03).
 *   ② الفهرس يُحدَّث مع المتن معًا (Nav09Reader::check يرفض غير المتطابق).
 *   ③ خرائط الملفات الثلاث تُسجَّل في nav09_file_map (state=mapped).
 * بعده: nav09_read --check ثم nav09_import --diff ثم --apply ثم تعطيل الروابط
 * اليدوية المكررة ثم nav09_verify.
 *
 * php tools/u9_nav09_v5_rental.php [--apply]
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
require_once dirname(__DIR__) . '/vendor/autoload.php';
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$ROOT = dirname(__DIR__);
$APPLY = in_array('--apply', $argv, true);
$o = function ($s) { fwrite(STDOUT, $s . "\n"); };

$DOC = $ROOT . '/docs/files/NAV-09-current.xlsx';
$BAK = $ROOT . '/docs/files/NAV-09-v4-backup-20260806.xlsx';

/* الشاشات الثلاث: canonical → [العنوان، real_path، النطاق، الزاوية، المسموح، المحجوب] */
$SCREENS = array(
    'fleet_calendar.php' => array('تقويمُ الأسطول والحجز', 'Operations/fleet_calendar.php',
        'عقودُه وحجوزاتُه', 'الإتاحةُ والحجزُ باليوم', 'حجزٌ · تعديلُ حجزٍ · إلغاءٌ بسبب', 'كرتَ المعدةِ والملكية'),
    'rate_books.php' => array('دفترُ الأسعار بالشرائح', 'Clients/rate_books.php',
        'دفاترُه السارية', 'التسعيرُ بالشريحةِ والمدة', 'إنشاءُ دفترٍ · بندٌ · اعتمادٌ', 'تكلفةَ التملّك'),
    'fleet_utilization.php' => array('استغلالُ الأسطول ومردودُه', 'Operations/fleet_utilization.php',
        'أسطولُ نطاقِه', 'الاستغلالُ مقابل الإتاحة', 'قراءةٌ · تصدير', 'الإهلاكَ والقيمةَ الدفترية'),
);
$STAGE_TITLE = '9 · تاسعًا: التأجير قصير الأمد';
$GROUP_TITLE = '▸ التأجير والحجز';
$SHEET_SALES = '08 · المبيعات والعقود';

$o('══ NAV-09 v5 — ' . ($APPLY ? 'APPLY' : 'DRY-RUN') . ' ══');

/* ⓪ فحوصُ الجاهزية */
foreach ($SCREENS as $cf => $s) {
    if (!is_file($ROOT . '/' . $s[1])) { $o("✘ الوجهةُ غيرُ موجودة: {$s[1]}"); exit(1); }
}
$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($DOC);
$wb = $reader->load($DOC);
$sales = $wb->getSheetByName($SHEET_SALES);
if (!$sales) { $o("✘ ورقةُ المبيعات غيرُ موجودة: $SHEET_SALES"); exit(1); }

/* أسبق تشغيلٌ؟ (idempotent) */
$already = false;
foreach ($sales->toArray(null, true, false, false) as $r) {
    foreach ($r as $c) { if (is_string($c) && mb_strpos($c, 'fleet_calendar.php') !== false) { $already = true; break 2; } }
}
if ($already) { $o('= الوثيقةُ تحمل التأجيرَ سلفًا — لا مساس'); }

if (!$already) {
    /* ① ورقة المبيعات: مرحلةٌ تاسعةٌ + مجموعةٌ + ثلاثُ شاشات */
    $last = $sales->getHighestDataRow();
    $rows = array(
        array($STAGE_TITLE, 'أمرُ حجزٍ معتمدٌ · دفترُ أسعارٍ نافذ', 'مدير المبيعات'),
        array($GROUP_TITLE),
    );
    $seq = 1;
    foreach ($SCREENS as $cf => $s) {
        $rows[] = array((string) $seq, $s[0], $cf, 'مالك', $s[2], $s[3], $s[4], $s[5]);
        $seq++;
    }
    $rr = $last + 1;
    foreach ($rows as $row) {
        foreach ($row as $ci => $val) {
            $sales->setCellValue([$ci + 1, $rr], $val);
        }
        $rr++;
    }
    $o('① ورقةُ المبيعات: مرحلةٌ + مجموعةٌ + 3 شاشاتٍ بعد الصف ' . $last);

    /* ② مصفوفة العرض 98 */
    $mx = $wb->getSheetByName('98 — مصفوفة العرض');
    $last = $mx->getHighestDataRow();
    $rr = $last + 1;
    foreach ($SCREENS as $cf => $s) {
        $mx->fromArray(array($s[0], $cf, 'المبيعات والعقود', 'المبيعات والعقود ★', $s[2], $s[3], $s[4], $s[5]), null, 'A' . $rr, true);
        $rr++;
    }
    $o('② المصفوفة 98: 3 ظهوراتٍ بعد الصف ' . $last);

    /* ③ الفهرس 00: صفُّ المبيعات (مراحل+1 · مجموعات+1 · شاشات+3) والإجماليات */
    $ix = $wb->getSheetByName('00 — الفهرس');
    $grid = $ix->toArray(null, true, false, false);
    $fixed = array('sales' => false, 'totals' => false);
    foreach ($grid as $ri => $r) {
        $cells = array();
        foreach ($r as $ci => $c) { $c = trim((string) $c); if ($c !== '') { $cells[$ci] = $c; } }
        if (!$cells) { continue; }
        $isSales = in_array('المبيعات والعقود', $cells, true);
        $isTotals = (reset($cells) === '—');
        if (!$isSales && !$isTotals) { continue; }
        /* آخرُ ثلاثِ خلايا رقمية = مراحل · مجموعات · شاشات (منطقُ القارئ نفسُه) */
        $numIdx = array();
        foreach ($cells as $ci => $c) { if (ctype_digit($c)) { $numIdx[] = $ci; } }
        if (count($numIdx) < 3) { continue; }
        $tail = array_slice($numIdx, -3);
        $bump = array(1, 1, 3);
        foreach ($tail as $k => $ci) {
            $ix->setCellValue([$ci + 1, $ri + 1], intval($cells[$ci]) + $bump[$k]);
        }
        $fixed[$isSales ? 'sales' : 'totals'] = true;
    }
    if (!$fixed['sales'] || !$fixed['totals']) { $o('✘ لم يُعثر على صفَّي الفهرس (المبيعات/الإجماليات)'); exit(1); }
    $o('③ الفهرس: المبيعات 10·26·44 والإجماليات 146·346·556');

    if ($APPLY) {
        if (!is_file($BAK)) { copy($DOC, $BAK) or die("✘ فشل النسخ الاحتياطي\n"); $o('⓪ نسخةُ v4 محفوظة: ' . basename($BAK)); }
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($wb, 'Xlsx');
        $writer->save($DOC);
        $o('✔ حُفظت الوثيقةُ v5: ' . basename($DOC));
    } else {
        $o('— dry-run: لم يُكتب الملف');
    }
}

/* ④ خرائطُ الملفات */
foreach ($SCREENS as $cf => $s) {
    $st = $conn->prepare("SELECT canonical_file FROM nav09_file_map WHERE canonical_file = ?");
    $st->bind_param('s', $cf);
    $st->execute();
    $exists = (bool) $st->get_result()->fetch_assoc();
    $st->close();
    if ($exists) { $o("= خريطة $cf قائمة"); continue; }
    $o("+ خريطة $cf → {$s[1]}");
    if ($APPLY) {
        $st = $conn->prepare("INSERT INTO nav09_file_map (canonical_file, state, real_path) VALUES (?, 'mapped', ?)");
        $st->bind_param('ss', $cf, $s[1]);
        $st->execute() or die($st->error . "\n");
        $st->close();
    }
}
$o($APPLY ? '✔ اكتمل — التالي: nav09_read --check ثم nav09_import --diff/--apply' : '— dry-run تام');
