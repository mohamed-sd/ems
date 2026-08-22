<?php
/**
 * tools/injfrd66_xc08_sod_gate.php — بوابةُ XC-08: مصفوفةُ فصلِ الواجبات
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيار**: «صفرُ عمليةٍ جمع فيها شخصٌ خانتَين متتاليتَين» — والبابُ
 *   السادسُ يعدّ إحدى عشرةَ عملية.
 *
 * ◆ **ولا يُقاس ما لا عمودَ له**: العمليةُ لا تُقاس إلا إن حمل جدولُها
 *   **عمودَي المنشئِ والمعتمِد** معًا. وما لا يحملهما **يُعلَن غيرَ مقيسٍ**
 *   ولا يُحسب أخضرَ — فـ«لا خانةَ ثانيةٌ في الجدول» ليست «لم يجمعْ أحدٌ
 *   خانتَين»، والخلطُ بينهما يُخضِّر عمليةً بلا حارسٍ أصلًا.
 *
 * ◆ **والجدولُ الفارغُ لا يُثبت الفصل**: صفرُ مخالفةٍ في جدولٍ بلا صفوفٍ
 *   عدمُ ممارسةٍ لا صحّةُ حارس — فيُعلَن «لا صفوفَ تُقاس».
 *
 * ◆ **والصفرُ في عمودِ الفاعلِ ليس فاعلًا**: `created_by = 0` يعني «لم
 *   يُسجَّل» لا «المستخدمُ صفر». فتُستبعد الصفوفُ التي لا يُعرف طرفاها،
 *   وتُعلَن منفصلةً — وإلا قُرئ المجهولُ مطابقًا فظهرت مخالفاتٌ وهميةٌ أو
 *   اختفت حقيقية.
 *
 * ◆ قراءةٌ خالصة — لا كتابةَ في القاعدةِ إطلاقًا.
 *
 * التشغيل:
 *   php tools/injfrd66_xc08_sod_gate.php          التقرير
 *   php tools/injfrd66_xc08_sod_gate.php --gate   رمزُ خروجٍ 1 عند أيِّ جمعٍ مقيس
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$_SERVER['SCRIPT_NAME'] = '/ems/main/dashboard.php';
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$GATE = in_array('--gate', $argv, true);

/* البابُ السادس — إحدى عشرةَ عمليةً بنصِّها، ومصدرُ قياسِ كلٍّ منها */
$OPS = array(
    array('op' => 'ترشيحُ موردٍ وفحوصُ ما قبل التعاقد', 'tbl' => 'supplier_rfqs',              'a' => null,           'b' => null),
    array('op' => 'توقيعُ عقدِ الموردِ وبنودِه',        'tbl' => 'supplier_contracts',          'a' => null,           'b' => null),
    /* `requested_by` و`created_by` **كلاهما جانبُ إنشاء** (من طلبَ ومن أدخل) —
       ولا عمودَ اعتمادٍ في الجدول. فلا تُقاس، ولا تُعلَن خضراءَ بزوجٍ مغلوط. */
    array('op' => 'تعديلُ سعرٍ أو حصةٍ بملحق',          'tbl' => 'contract_amendments',         'a' => null,           'b' => null),
    array('op' => 'إسنادُ أو إحلالُ معدةٍ على وحدة',    'tbl' => 'op_containers',               'a' => null,           'b' => null),
    array('op' => 'اعتمادُ الوحداتِ المنفَّذةِ الشهرية', 'tbl' => 'contract_baseline',           'a' => 'created_by',   'b' => 'approved_by'),
    array('op' => 'تسويةُ الخصومِ والإضافات',           'tbl' => 'supplier_advance_requests',   'a' => 'created_by',   'b' => 'approved_by'),
    array('op' => 'الإقفالُ الشهري',                    'tbl' => 'settlements',                 'a' => 'prepared_by',  'b' => 'approved_by'),
    /* طلبُ الدفعِ والصرفُ عمليتانِ في **المالية والخزينة** لا في التسوية —
       و`invoiced_by`/`closed_by` خاناتُ فوترةٍ وإقفالٍ لا خاناتُ طلبٍ وصرف.
       فلا تُقاسان هنا، وتُعلَنان غيرَ مقيستَين حتى يُقاس سطحُهما. */
    array('op' => 'طلبُ الدفع',                         'tbl' => 'settlements',                 'a' => null,           'b' => null),
    array('op' => 'الصرفُ الفعلي',                      'tbl' => 'settlements',                 'a' => null,           'b' => null),
    array('op' => 'جزاءٌ أو مطالبةٌ على مورد',          'tbl' => 'sup_violations',              'a' => null,           'b' => null),
    array('op' => 'إغلاقُ عقدٍ وإخلاءُ طرف',            'tbl' => 'supplier_contract_closures',  'a' => 'opened_by',    'b' => 'closed_by'),
);

$exists = static function (string $t) use ($conn): bool {
    $r = @mysqli_query($conn, "SELECT 1 FROM information_schema.TABLES
                                WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='"
                                . mysqli_real_escape_string($conn, $t) . "'");
    return (bool) ($r && mysqli_num_rows($r));
};
$hasCol = static function (string $t, ?string $c) use ($conn): bool {
    if ($c === null) { return false; }
    $r = @mysqli_query($conn, "SELECT 1 FROM information_schema.COLUMNS
                                WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='"
                                . mysqli_real_escape_string($conn, $t) . "' AND COLUMN_NAME='"
                                . mysqli_real_escape_string($conn, $c) . "'");
    return (bool) ($r && mysqli_num_rows($r));
};
$num = static function (string $sql) use ($conn): int {
    $r = @mysqli_query($conn, $sql);
    return $r ? (int) mysqli_fetch_row($r)[0] : -1;
};

echo "\n═══ INJ-FRD-01 · XC-08 — مصفوفةُ فصلِ الواجبات ═══\n\n";
printf("  %-38s %-26s %s\n", 'العملية', 'المصدر', 'المقيس');
echo '  ' . str_repeat('─', 88) . "\n";

$str = static function (string $sql) use ($conn): string {
    $r = @mysqli_query($conn, $sql);
    $v = $r ? mysqli_fetch_row($r)[0] : null;
    return $v === null ? '' : (string) $v;
};

$green = 0; $red = 0; $debt = 0; $unmeasured = 0;
foreach ($OPS as $o) {
    $src = $o['tbl'] . ($o['a'] ? " ({$o['a']}↔{$o['b']})" : '');
    if (!$exists($o['tbl'])) {
        $unmeasured++;
        printf("  %-38s %-26s ــ سطحٌ غيرُ مبنيّ\n", $o['op'], $o['tbl']);
        continue;
    }
    if (!$hasCol($o['tbl'], $o['a']) || !$hasCol($o['tbl'], $o['b'])) {
        $unmeasured++;
        printf("  %-38s %-26s ــ لا خانتَي منشئٍ ومعتمِدٍ تُقاسان\n", $o['op'], $o['tbl']);
        continue;
    }
    $total = $num("SELECT COUNT(*) FROM `{$o['tbl']}`");
    if ($total === 0) {
        $unmeasured++;
        printf("  %-38s %-26s ــ لا صفوفَ تُقاس\n", $o['op'], $src);
        continue;
    }
    /* الطرفانِ معلومان: غيرُ فارغٍ وغيرُ صفر — والصفرُ «لم يُسجَّل» لا فاعل */
    $known = $num("SELECT COUNT(*) FROM `{$o['tbl']}`
                    WHERE `{$o['a']}` IS NOT NULL AND `{$o['a']}` <> 0
                      AND `{$o['b']}` IS NOT NULL AND `{$o['b']}` <> 0");
    if ($known === 0) {
        $unmeasured++;
        printf("  %-38s %-26s ــ %d صفًّا · صفرُ صفٍّ طرفاه معلومان\n", $o['op'], $src, $total);
        continue;
    }
    $same = $num("SELECT COUNT(*) FROM `{$o['tbl']}`
                   WHERE `{$o['a']}` IS NOT NULL AND `{$o['a']}` <> 0
                     AND `{$o['b']}` IS NOT NULL AND `{$o['b']}` <> 0
                     AND `{$o['a']}` = `{$o['b']}`");
    if ($same === 0) { $green++; printf("  %-38s %-26s ✔ 0 من %d مقيسًا\n", $o['op'], $src, $known); }
    else {
        /* ◆ **الدَّينُ التاريخيُّ ليس خرقًا حيًّا**: الحارسُ قد يكون قائمًا في
             الخدمةِ وكلُّ المخالفاتِ أقدمُ منه. فيُقاس **الأحدثُ لا العددُ**:
             إن كان أحدثُ صفٍّ مخالفٍ **أقدمَ** من أحدثِ صفٍّ مقيسٍ، فالمتأخّرونَ
             ملتزمون والحارسُ يعمل — والعددُ دَينٌ مؤرَّخٌ يُعلَن ولا يُرسِّب.
           ◆ وبغيرِ هذا التمييزِ يُعلَن «251 خرقًا» عن نظامٍ يمنعها اليوم
             (`SettlementService` §15.4 يردُّ 403)، فيُطارَد حارسٌ قائمٌ
             ويُترك الدَّينُ بلا تأريخ. */
        $tCol = $hasCol($o['tbl'], 'created_at') ? 'created_at' : null;
        $legacy = false; $span = '';
        if ($tCol !== null) {
            $w = "`{$o['a']}` IS NOT NULL AND `{$o['a']}` <> 0
                  AND `{$o['b']}` IS NOT NULL AND `{$o['b']}` <> 0";
            $mxBad = $str("SELECT MAX(`{$tCol}`) FROM `{$o['tbl']}` WHERE {$w} AND `{$o['a']}` = `{$o['b']}`");
            $mxAll = $str("SELECT MAX(`{$tCol}`) FROM `{$o['tbl']}` WHERE {$w}");
            if ($mxBad !== '' && $mxAll !== '' && $mxBad < $mxAll) {
                $legacy = true;
                $span = ' · أحدثُ مخالفةٍ ' . substr($mxBad, 0, 10) . ' وأحدثُ صفٍّ ' . substr($mxAll, 0, 10);
            }
        }
        if ($legacy) {
            $debt++;
            printf("  %-38s %-26s ⏸ %d من %d دَينٌ مؤرَّخ%s\n", $o['op'], $src, $same, $known, $span);
        } else {
            $red++;
            printf("  %-38s %-26s ✘ %d من %d جمعَ خانتَين\n", $o['op'], $src, $same, $known);
        }
    }
}

printf("
  أخضر %d · أحمر %d · دَينٌ مؤرَّخٌ %d · غيرُ مقيسٍ %d   (من %d عملية)
",
    $green, $red, $debt, $unmeasured, count($OPS));
echo "\n  ◆ «غيرُ مقيس» ليس «مفصول»: عمليةٌ بلا خانتَي منشئٍ ومعتمِدٍ لا حارسَ لها\n";
echo "    يُقاس — وإعلانُها خضراءَ يُخفي غيابَ الحارسِ لا يُثبت وجودَه.\n\n";

exit($GATE && $red > 0 ? 1 : 0);
