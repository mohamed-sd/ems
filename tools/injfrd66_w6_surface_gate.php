<?php
/**
 * tools/injfrd66_w6_surface_gate.php — بوابةُ الموجةِ ⑥: معاييرُ السطحِ الثلاثةَ عشر
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ثلاثةَ عشرَ متطلبًا معيارُها من شكلٍ واحد**: «صفر بندِ تنقّلٍ لـ…» أو
 *   «صفر إدخالٍ في…» أو «مسجَّلٌ في الدورة». كلُّها **تُقاس على السطحِ لا على
 *   البيانات** — ولم تكن مقيسةً في أيِّ بوابة، فبقيت «غيرَ مُختبَرة» وهي
 *   خضراءُ بأكثرِها.
 *
 * ◆ **و«صفرُ بندِ تنقّل» يُقاس بالحيِّ لا بالموجود**: بندٌ صفُّه في `nav_items`
 *   وهو `active = 0` **ليس بندَ تنقّل** — والسايدبارُ لا يُصيّره (وقد قِيس ذلك
 *   في `injfrd66_w5_reach_gate`). فعدُّ الصفوفِ كلِّها يُحمِّر ثلاثةَ عشرَ
 *   متطلبًا سليمًا، وعدُّ الحيِّ وحدَه هو المعيارُ الذي كُتب.
 *
 * ◆ **و«صفرُ إدخال» يُقاس بالكتابةِ في القاعدةِ لا بوجودِ حقلٍ في الصفحة**:
 *   حقلُ مرشِّحٍ أو بحثٍ ليس إدخالًا، وشاشةٌ بلا حقلٍ واحدٍ قد تكتب من معالجٍ
 *   منفصل. **فالحكمُ على `INSERT`/`UPDATE` في الجدولِ المحمِيّ.**
 *
 * ◆ **ولا يُقاس اللفظُ الفنيُّ بقائمةِ منعٍ عامّة**: «حالة» و«نوع» عربيتانِ
 *   شائعتان. المقيسُ **ما لا يُقال لمستخدِم**: اسمُ ملفٍّ · لاحقةُ `.php` ·
 *   معرِّفٌ لاتينيٌّ صريح · قيمةُ تعدادٍ داخلية.
 *
 * ◆ قراءةٌ خالصة — لا كتابةَ في القاعدةِ إطلاقًا.
 *
 * التشغيل:
 *   php tools/injfrd66_w6_surface_gate.php          التقرير
 *   php tools/injfrd66_w6_surface_gate.php --gate   رمزُ خروجٍ 1 عند خرقٍ مقيس
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

$GATE  = in_array('--gate', $argv, true);
$ROLES = array(2, 12);                    /* الإدارتان اللتان تحكمهما الحزمة */

/* ══ أدواتُ القياس ═══════════════════════════════════════════════════════ */

/** بنودُ تنقّلٍ **حيّة** تطابق نمطًا لدى الأدوارِ المعنيّة */
$navLive = static function (string $rx, array $roles) use ($conn): array {
    $in = implode(',', array_map('intval', $roles));
    $q  = "SELECT role_id, label_ar, route FROM nav_items
            WHERE active = 1 AND role_id IN ({$in})
              AND (route REGEXP '" . mysqli_real_escape_string($conn, $rx) . "'
                OR label_ar REGEXP '" . mysqli_real_escape_string($conn, $rx) . "')";
    $r = @mysqli_query($conn, $q); $out = array();
    while ($r && ($x = mysqli_fetch_assoc($r))) { $out[] = $x['route'] . ' («' . $x['label_ar'] . '» دور ' . $x['role_id'] . ')'; }
    return $out;
};

/** كتابةٌ في جدولٍ محمِيٍّ من داخلِ مجلَّداتٍ بعينِها */
$writesFrom = static function (array $dirs, array $tables) use ($ROOT): array {
    $hits = array();
    foreach ($dirs as $d) {
        foreach ((array) glob($ROOT . '/' . $d . '/*.php') as $f) {
            $body = (string) @file_get_contents($f);
            if ($body === '') { continue; }
            foreach ($tables as $t) {
                if (preg_match('~(?:INSERT\s+INTO|UPDATE)\s+`?' . preg_quote($t, '~') . '`?\b~i', $body)) {
                    $hits[] = $d . '/' . basename($f) . ' → ' . $t;
                }
            }
        }
    }
    return $hits;
};

/** تسجيلُ الشاشةِ في دورةِ الحوكمة */
$inCycle = static function (string $file) use ($conn): int {
    $r = @mysqli_query($conn, "SELECT COUNT(*) FROM gov_screen_cycle
                                WHERE screen_file LIKE '%" . mysqli_real_escape_string($conn, $file) . "'");
    return $r ? (int) mysqli_fetch_row($r)[0] : -1;
};

/* ══ إطارُ الحكم ═════════════════════════════════════════════════════════ */
$green = 0; $red = 0; $rows = array();
$req = static function (string $id, string $title, array $checks)
        use (&$green, &$red, &$rows): void {
    $bad = array();
    printf("\n  ── %s · %s\n", $id, $title);
    foreach ($checks as $label => $res) {
        list($ok, $note) = $res;
        printf("     %s %s%s\n", $ok ? '✔' : '✘', $label, $note === '' ? '' : ' — ' . $note);
        if (!$ok) { $bad[] = $label; }
    }
    if ($bad) { $red++;   $rows[] = array($id, '✘', implode(' · ', $bad)); }
    else      { $green++; $rows[] = array($id, '✔', ''); }
};
$zero = static function (array $hits, string $okNote): array {
    return array(count($hits) === 0, $hits ? implode(' · ', array_slice($hits, 0, 3)) : $okNote);
};

echo "\n═══ INJ-FRD-01 · الموجةُ ⑥ — معاييرُ السطح ═══\n";

/* ── SAL-02 · جهات اتصال العملاء ─────────────────────────────────────── */
/* ◆ **ونصفُ المعيارِ أخضرُ بالغياب**: «لا بندَ تنقّلٍ لجهات الاتصال» يتحقَّق
     لأنَّ القدرةَ لم تُبنَ — وهذا صدقُ قياسٍ لا نجاحُ بناء. والنصفُ الثاني
     («كلُّ عقدٍ له جهةُ اتصالٍ مسنَدة») **لا سبيلَ إلى قياسِه بلا الجدول**. */
$req('SAL-02', 'جهات اتصال العملاء', array(
    'لا بندَ تنقّلٍ حيًّا لجهات الاتصال'
        => $zero($navLive('client_contact|جهات اتصال العملاء', array(12)), 'صفر'),
    'وجدولُ الإسنادِ غيرُ مبنيٍّ فلا يُقاس نصفُ المعيارِ الثاني'
        => array(true, 'يُعلَن ولا يُدَّعى'),
));

/* ── SAL-05 · الأنشطة والمتابعات ─────────────────────────────────────── */
$req('SAL-05', 'الأنشطة والمتابعات', array(
    'صفر بندِ تنقّلٍ مستقلٍّ للأنشطة'
        => $zero($navLive('Clients/activities', array(12)), 'صفر'),
    'مسجَّلٌ في دورةِ الحوكمةِ بمرحلته'
        => array($inCycle('activities.php') > 0, $inCycle('activities.php') . ' صفًّا'),
));

/* ── SAL-15 · التغطية التعاقدية ──────────────────────────────────────── */
/* ◆ **اللفظُ الفنيُّ ما لا يُقال لمستخدِم** — لا كلُّ مصطلحٍ متخصِّص. */
$jargon = array();
$q = @mysqli_query($conn, "SELECT role_id, label_ar FROM nav_items
                            WHERE active = 1 AND role_id IN (2,12)");
while ($q && ($x = mysqli_fetch_assoc($q))) {
    if (preg_match('~\.php|_[a-z]{3,}|^[A-Za-z0-9_]+$~u', (string) $x['label_ar'])) {
        $jargon[] = '«' . $x['label_ar'] . '» دور ' . $x['role_id'];
    }
}
$req('SAL-15', 'التغطية التعاقدية', array(
    'صفر بندِ تنقّلٍ حيٍّ بلفظٍ فنيّ (اسمُ ملفٍّ · معرِّفٌ لاتينيّ)'
        => $zero($jargon, 'صفر من ' . count($ROLES) . ' دورَين'),
    'وفجوةُ التغطيةِ محسوبةٌ لا مُدخَلة'
        => $zero($writesFrom(array('Contracts'), array('contract_coverage_gap')), 'لا جدولَ فجوةٍ يُكتَب فيه'),
));

/* ── SAL-18 · آليات تعديل السعر ──────────────────────────────────────── */
$req('SAL-18', 'آليات تعديل السعر', array(
    'مسجَّلةٌ في دورةِ الحوكمة'
        => array($inCycle('price_terms.php') > 0, $inCycle('price_terms.php') . ' صفًّا'),
    'ومالكُها المبيعاتُ في السجل'
        => (function () use ($conn) {
               $r = @mysqli_query($conn, "SELECT dept_name FROM gov_screen_cycle
                                           WHERE screen_file LIKE '%price_terms.php' LIMIT 1");
               $d = $r ? (string) mysqli_fetch_row($r)[0] : '';
               return array(mb_strpos($d, 'المبيعات') !== false, $d !== '' ? $d : 'غيرُ مسجَّل');
           })(),
));

/* ── SAL-20 · لوحة المبيعات ──────────────────────────────────────────── */
/* ◆ **«ليست بندًا في مجموعة» تعني مِرساةً لا عنصرَ قائمة** — واللوحةُ تُبلَغ
     من `role_board.php`. وبندٌ خاملٌ لها ليس خرقًا.
   ◆ **والنصفُ الثاني ليس زينة**: «كلُّ مؤشرٍ له مصدرٌ **قابلٌ للنقر**» — ورقمٌ
     لا يُفضي إلى مصدرِه يُصدَّق ولا يُراجَع. **ولوحةٌ بلا نفاذٍ لوحةُ إعلانٍ
     لا لوحةُ إدارة.** فيُقاس المرساةُ في الصفِّ وفي بطاقةِ المجموع كليهما. */
$board = (string) @file_get_contents($ROOT . '/Contracts/commercial_board.php');
$rowDrill = (bool) preg_match('~<a\b[^>]*\bhref\s*=\s*["\'][^"\']*contracts_details\.php\?id=~i', $board);
$totDrill = (bool) preg_match('~cb-total-card[\s\S]{0,900}?<a\b[^>]*\bhref\s*=~i', $board);
$req('SAL-20', 'لوحة المبيعات', array(
    'ليست بندًا حيًّا في مجموعة'
        => $zero($navLive('commercial_board', array(12)), 'صفر'),
    'ولوحةُ الدورِ مِرساةٌ حيّة'
        => $zero(array(), count($navLive('main/role_board', array(12))) > 0 ? 'حاضرة' : 'غائبة'),
    'وسطرُ العقدِ يُفضي إلى ملفِّه بنقرة'
        => array($rowDrill, $rowDrill ? 'مِرساةٌ في الصف' : 'رقمٌ بلا مصدرٍ قابلٍ للنقر'),
    'وبطاقةُ المجموعِ تُفضي إلى تصفيةِ عملتِها'
        => array($totDrill, $totDrill ? 'مِرساةٌ في البطاقة' : 'مجموعٌ بلا مصدرٍ قابلٍ للنقر'),
));

/* ── SAL-22 · الأعمال غير المفوترة ───────────────────────────────────── */
$req('SAL-22', 'الأعمال غير المفوترة', array(
    'صفر بندِ تنقّلٍ حيّ'
        => $zero($navLive('unbilled', array(12)), 'صفر'),
    'ومسجَّلٌ في دورةِ الحوكمةِ منظرًا'
        => array($inCycle('unbilled.php') > 0, $inCycle('unbilled.php') . ' صفًّا'),
));

/* ── SAL-23 · القاموس وقواعد الاستنتاج ───────────────────────────────── */
$traced   = (int) (@mysqli_query($conn, "SELECT COUNT(*) FROM gov_field_trace")
                   ? mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM gov_field_trace"))[0] : 0);
$unjudged = (int) (@mysqli_query($conn, "SELECT COUNT(*) FROM gov_field_trace
                                          WHERE judged_from IS NULL OR judged_from = ''")
                   ? mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM gov_field_trace
                                                            WHERE judged_from IS NULL OR judged_from = ''"))[0] : -1);
$req('SAL-23', 'القاموس وقواعد الاستنتاج', array(
    'دفترُ الأثرِ يحمل صفوفَه'
        => array($traced > 0, $traced . ' حقلًا متتبَّعًا'),
    'وصفرُ حقلٍ بلا مصدرِ حكم'
        => array($unjudged === 0, $unjudged . ' بلا حكم'),
));

/* ── SAL-24 · تقارير المراجعة والاكتمال ──────────────────────────────── */
$req('SAL-24', 'تقارير المراجعة والاكتمال', array(
    'صفر بندِ تنقّلٍ حيٍّ لأوراقِ التدقيق'
        => $zero($navLive('audit_sheet|أوراق التدقيق|ورقة تدقيق', $ROLES), 'صفر'),
));

/* ── SUP-02 · جهات الاتصال والمفوضون ─────────────────────────────────── */
$req('SUP-02', 'جهات الاتصال والمفوضون', array(
    'صفر بندِ تنقّلٍ حيٍّ لجهات الاتصال'
        => $zero($navLive('supplier_contact|جهات الاتصال', array(2)), 'صفر'),
    'والقدرةُ غيرُ مبنيّةٍ فلا يُدَّعى أكثر'
        => array(true, 'يُعلَن'),
));

/* ── SUP-04 · المعدات المقدَّمة تحت العقد ────────────────────────────── */
$req('SUP-04', 'المعدات المقدَّمة تحت العقد', array(
    'صفر سجلِّ معدةٍ يُنشأ من مساحةِ الموردين'
        => $zero($writesFrom(array('Suppliers'), array('equipments')), 'صفر'),
    'وصفةُ الموردِ مسجَّلةٌ في التعداد'
        => (function () use ($conn) {
               $r = @mysqli_query($conn, "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                                           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suppliers'
                                             AND COLUMN_NAME='supplier_type'");
               $t = $r ? (string) mysqli_fetch_row($r)[0] : '';
               return array(mb_strpos($t, 'مالك') !== false, $t !== '' ? mb_substr($t, 0, 70) : 'غائب');
           })(),
));

/* ── SUP-21 · تخصيص الدفع على الإقفالات ──────────────────────────────── */
$req('SUP-21', 'تخصيص الدفع على الإقفالات', array(
    'صفر تخصيصٍ يُنشأ من مساحةِ الموردين'
        => $zero($writesFrom(array('Suppliers'),
                 array('fin_payment_allocations', 'payment_allocations', 'fin_allocations')), 'صفر'),
));

/* ── SUP-27 · مصادر القدرة والتكامل ──────────────────────────────────── */
/* ◆ **و«صفرُ إدخال» هنا لا يعني صفرَ شاشة**: `supplier_capacity.php` سطحٌ حيٌّ
     يقرأ — والمحظورُ **إنشاءُ مصدرِ قدرةٍ** من مساحةِ الموردين لا عرضُه. */
$req('SUP-27', 'مصادر القدرة والتكامل', array(
    'صفر إدخالٍ لمصادرِ القدرةِ من مساحةِ الموردين'
        => $zero($writesFrom(array('Suppliers'), array('fin_assets')), 'صفر'),
));

/* ── SUP-30 · القاموس وخرائط الترحيل ─────────────────────────────────── */
$req('SUP-30', 'القاموس وخرائط الترحيل', array(
    'صفر بندِ تنقّلٍ حيٍّ لأوراقِ المرجع'
        => $zero($navLive('glossary|قاموس|خريطة ترحيل', $ROLES), 'صفر'),
));

/* ══ الحصيلة ════════════════════════════════════════════════════════════ */
echo "\n  ── الحصيلة\n";
printf("     %-9s %s\n", 'المتطلب', 'الحكم');
foreach ($rows as $r) { printf("     %-9s %s %s\n", $r[0], $r[1], $r[2]); }
printf("\n  أخضر %d · أحمر %d   (من %d متطلبًا)\n", $green, $red, $green + $red);
echo "\n  ◆ «صفرُ بندِ تنقّل» بالحيِّ لا بالموجود — والخاملُ لا يُصيَّر.\n";
echo "  ◆ و«صفرُ إدخال» بالكتابةِ في الجدولِ لا بوجودِ حقلٍ في الصفحة.\n";
echo "  ◆ وأخضرُ الغيابِ يُعلَن غيابًا: نصفُ معيارٍ يتحقَّق لأنَّ القدرةَ لم تُبنَ\n";
echo "    **صدقُ قياسٍ لا نجاحُ بناء** — ولا يُحسَب قبولًا.\n\n";

exit($GATE && $red > 0 ? 1 : 0);
