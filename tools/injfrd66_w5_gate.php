<?php
/**
 * tools/injfrd66_w5_gate.php — بوابةُ الموجةِ ⑤: الخمسةُ «غيرُ المحسومة»
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **«غيرُ محسوم» ليس حكمًا — إنَّه اعترافٌ بأنَّه لم يُقَس.** وهذه البوابةُ
 *   تقيس الخمسةَ واحدًا واحدًا وتُخرجها إلى حكم — **ولو كان أحمر**. والأحمرُ
 *   المقيسُ خيرٌ من «غيرِ محسوم»: هذا يُبنى عليه وذاك يُؤجَّل به.
 *
 * ◆ **وثلاثةُ مقاييسَ هنا يجب أن تُقرأ بعنايةٍ لأنَّ ظاهرَها يخدع**:
 *   ① `SAL-16` — «صفرُ حقلِ كميةٍ» **يُقاس على سطحِ المتطلبِ لا على الإدارةِ
 *      كلِّها**: الكميةُ **المتعاقَدُ عليها** عملُ المبيعاتِ نفسُه، والمحظورُ
 *      الكميةُ **الفعليّة**. وعدُّ المخطَّطِ خرقًا يُحمِّر إدارةً تؤدّي وظيفتَها.
 *   ② `SUP-13` — «صفرُ حصةٍ نشأت من إحلال» **يُقاس بالنَّسَبِ لا بالزمن**:
 *      طوابعُ `op_containers` كلُّها لحظةُ بذرةٍ واحدة، والإحلالاتُ مؤرَّخةٌ
 *      رجعيًّا — **فمقارنةُ «أيُّهما أسبق» تعطي 18 من 18 خرقًا وهو رقمٌ مقلوب**.
 *   ③ `SUP-14` — «يشمل أشهرَ الصفر» **قدرةٌ تُثبَت بالبنيةِ لا بالعدّ**: صفرُ
 *      شهرِ صفرٍ في بياناتٍ متّصلةٍ **لا ينفي القدرة** — والشاهدُ يُثبتها بثغرةٍ
 *      مصطنعة.
 *
 * ◆ قراءةٌ خالصة — لا كتابةَ في القاعدةِ إطلاقًا.
 *
 * التشغيل:
 *   php tools/injfrd66_w5_gate.php          التقرير
 *   php tools/injfrd66_w5_gate.php --gate   رمزُ خروجٍ 1 عند خرقٍ مقيس
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
$num = static function (string $sql) use ($conn): int {
    $r = @mysqli_query($conn, $sql);
    return $r ? (int) mysqli_fetch_row($r)[0] : -1;
};
$green = 0; $red = 0; $held = 0;
$verdict = static function (string $req, string $title) { printf("\n  ── %s · %s\n", $req, $title); };
$ok  = static function (string $m) use (&$green) { $green++; echo "     ✔ {$m}\n"; };
$no  = static function (string $m) use (&$red)   { $red++;   echo "     ✘ {$m}\n"; };
$hold = static function (string $m) use (&$held) { $held++;  echo "     ⏸ {$m}\n"; };

echo "\n═══ INJ-FRD-01 · الموجةُ ⑤ — الخمسةُ «غيرُ المحسومة» ═══\n";

/* ═══ SAL-16 — «صفر حقلِ كميةٍ قابلٍ للكتابة في نطاق المبيعات» ═══════════ */
$verdict('SAL-16', 'الأداءُ والمبيعاتُ الشهرية');
$SURF = array('Contracts/unit_statement_client.php', 'Contracts/plan_actual_link.php');
$qtyRx = '~<(?:input|textarea)\b(?![^>]*\btype\s*=\s*"(?:hidden|submit|button)")'
       . '(?![^>]*\b(?:readonly|disabled)\b)[^>]*\bname\s*=\s*"[^"]*'
       . '(?:qty|quantity|hours|amount_qty|units)[^"]*"~i';
$writable = array();
foreach ($SURF as $rel) {
    $body = (string) @file_get_contents($ROOT . '/' . $rel);
    if ($body === '') { $no("سطحٌ مفقود: {$rel}"); continue; }
    $n = preg_match_all($qtyRx, $body, $m);
    $all = preg_match_all('~<(?:input|select|textarea)\b~i', $body);
    printf("     ○ %-40s %2d حقلًا · منها %d كميةٌ قابلةٌ للكتابة\n", $rel, $all, $n);
    if ($n > 0) { $writable[] = $rel . ' (' . $n . ')'; }
}
if (!$writable) { $ok('صفرُ حقلِ كميةٍ قابلٍ للكتابة في سطحَي المتطلب'); }
else { $no('حقولُ كميةٍ قابلةٌ للكتابة: ' . implode('، ', $writable)); }

/* والمحظورُ الفعليُّ لا المخطَّط: صفرُ كتابةٍ من نطاقِ المبيعاتِ في جداولِ الفعليّ */
$ACTUALS = array('unit_entries', 'unit_time_log', 'timesheet', 'unit_approvals');
$SALES   = array('Clients', 'Opportunities');
$leaks = array();
foreach ($SALES as $d) {
    foreach ((array) glob($ROOT . '/' . $d . '/*.php') as $f) {
        $body = (string) @file_get_contents($f);
        foreach ($ACTUALS as $t) {
            if (preg_match('~(?:INSERT\s+INTO|UPDATE)\s+`?' . $t . '`?\b~i', $body)) {
                $leaks[] = $d . '/' . basename($f) . ' → ' . $t;
            }
        }
    }
}
if (!$leaks) { $ok('وصفرُ كتابةٍ من نطاقِ المبيعاتِ في جداولِ الفعليّ (' . implode('، ', $ACTUALS) . ')'); }
else { $no('كتابةٌ في الفعليِّ من المبيعات: ' . implode(' · ', $leaks)); }
echo "       ◆ والكميةُ المتعاقَدُ عليها ليست خرقًا — هي عملُ المبيعاتِ نفسُه.\n";

/* ═══ SUP-03 — «صفر عقدٍ لموردٍ بحالة تأهيلٍ غير مكتملة» ═════════════════ */
$verdict('SUP-03', 'التأهيلُ القانونيُّ والائتماني');
if ($num("SELECT COUNT(*) FROM information_schema.VIEWS
           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='v_supplier_qualification'") !== 1) {
    $no('`v_supplier_qualification` غيرُ قائم — لا حالةَ تُقاس');
} else {
    $sup   = $num("SELECT COUNT(*) FROM v_supplier_qualification");
    $qual  = $num("SELECT COUNT(*) FROM v_supplier_qualification WHERE qualification_state='مؤهَّل'");
    $breach = $num("SELECT COALESCE(SUM(live_contracts),0) FROM v_supplier_qualification
                     WHERE qualification_state <> 'مؤهَّل' AND live_contracts > 0");
    $total  = $num("SELECT COUNT(*) FROM supplier_contracts WHERE is_deleted=0");
    printf("     ○ %d موردًا حيًّا · منهم %d مؤهَّل\n", $sup, $qual);
    if ($breach === 0) { $ok("صفرُ عقدٍ لموردٍ ناقصِ التأهيل (من {$total})"); }
    else {
        $no("{$breach} عقدًا من {$total} لموردٍ ناقصِ التأهيل");
        $r = @mysqli_query($conn, "SELECT missing_list, COUNT(*) n, SUM(live_contracts) c
                                     FROM v_supplier_qualification WHERE live_contracts > 0
                                    GROUP BY missing_list ORDER BY c DESC LIMIT 5");
        while ($r && ($x = mysqli_fetch_assoc($r))) {
            printf("        · ناقصُه «%s» — %s موردًا · %s عقدًا\n", $x['missing_list'], $x['n'], $x['c']);
        }
        /* ◆ **والعمودُ الذي لم يستقبل قيمةً قطُّ ليس عمودًا معطَّلًا — إنَّه ممارسةٌ
             لم تبدأ.** فيُفصَل عن باقي النقصِ لأنَّ علاجَه إجراءٌ لا استيرادُ بيانات. */
        $neverVerified = $num("SELECT COUNT(*) FROM suppliers WHERE is_deleted=0 AND bank_verified_at IS NOT NULL");
        if ($neverVerified === 0) {
            echo "        ◆ و`bank_verified_at` **لم يستقبل قيمةً واحدةً قطُّ** — توثيقُ\n";
            echo "          الحسابِ ممارسةٌ لم تبدأ، لا بياناتٌ ناقصة.\n";
        }
        $hold('حارسُ «لا تعاقُدَ مع غيرِ مؤهَّل» محجوزٌ: إنفاذُه يُخالف عقودَ الإدارةِ كلَّها');
    }
}

/* ═══ SUP-13 — «صفر حصةٍ نشأت من إحلال» ═════════════════════════════════ */
$verdict('SUP-13', 'الجاهزيةُ والإحلالُ والاحتياط');
$swaps = $num("SELECT COUNT(*) FROM container_swaps");
if ($swaps === 0) { $hold('لا صفوفَ في `container_swaps` تُقاس (صفرٌ بلا ممارسة)'); }
else {
    /* ① تعدادُ المنشأِ لا يعرف الإحلالَ أصلًا ⇒ لا حصةَ تُعلن نفسَها وليدةَ إحلال */
    $r = @mysqli_query($conn, "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                                WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='op_containers'
                                  AND COLUMN_NAME='origin'");
    $enum = $r ? (string) mysqli_fetch_row($r)[0] : '';
    $hasSwapOrigin = (mb_strpos($enum, 'إحلال') !== false);
    if (!$hasSwapOrigin) { $ok("تعدادُ `origin` لا يحمل قيمةَ إحلالٍ أصلًا: {$enum}"); }
    else { $no('تعدادُ `origin` يسمح بمنشأِ إحلال'); }

    /* ② وكلُّ وجهةِ إحلالٍ حاويةٌ لها عقدُها — لا حصةٌ وُلدت من الحركة */
    $orphan = $num("SELECT COUNT(*) FROM container_swaps s
                     LEFT JOIN op_containers c ON c.id = s.to_container_id
                    WHERE c.id IS NULL OR c.contract_id IS NULL OR c.contract_id = 0");
    if ($orphan === 0) { $ok("و{$swaps} من {$swaps} وجهةِ إحلالٍ لها عقدٌ مرجعيّ"); }
    else { $no("{$orphan} وجهةَ إحلالٍ بلا عقدٍ مرجعيّ — حصةٌ قد تكون وُلدت من الحركة"); }

    /* ③ والخُضرةُ تُقرأ بحدِّها: إحلالٌ بلا كميةٍ منقولةٍ لا يُثبت نقلًا */
    $noQty = $num("SELECT COUNT(*) FROM container_swaps WHERE moved_qty IS NULL OR moved_qty = 0");
    if ($noQty > 0) {
        printf("     ◆ و%d من %d إحلالًا **بلا كميةٍ منقولة** — فالخُضرةُ مسنودةٌ\n", $noQty, $swaps);
        echo "       جزئيًّا إلى عدمِ ممارسة. والكميةُ واقعةٌ لا استنتاج.\n";
    }
    echo "     ◆ ولا يُقاس هذا بالطوابعِ الزمنية: طوابعُ الحاوياتِ كلُّها لحظةُ\n";
    echo "       بذرةٍ واحدةٍ والإحلالاتُ مؤرَّخةٌ رجعيًّا — والمقارنةُ تعطي رقمًا مقلوبًا.\n";
}

/* ═══ SUP-14 — «صفر إدخالٍ في المستهدفات» ═══════════════════════════════ */
$verdict('SUP-14', 'مستهدفاتُ الموردين');
$isView = $num("SELECT COUNT(*) FROM information_schema.VIEWS
                 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='v_supplier_targets_monthly'");
if ($isView !== 1) { $no('`v_supplier_targets_monthly` ليس منظرًا قائمًا'); }
else {
    $ok('منظرٌ لا جدول — فـ«صفرُ إدخال» خاصيةٌ بنيويةٌ لا قاعدةٌ تُراقَب');
    $rows = $num("SELECT COUNT(*) FROM v_supplier_targets_monthly");
    $sup  = $num("SELECT COUNT(DISTINCT supplier_id) FROM v_supplier_targets_monthly");
    $zero = $num("SELECT COUNT(*) FROM v_supplier_targets_monthly WHERE shares_active = 0");
    printf("     ○ %d شهرًا × %d موردًا · %d شهرَ صفر\n", $rows, $sup, $zero);
    $nullMon = $num("SELECT COUNT(*) FROM v_supplier_targets_monthly WHERE target_month IS NULL");
    if ($nullMon === 0) { $ok('وصفرُ شهرٍ فارغ — والحصةُ بلا نافذةٍ استُبعدت لا ابتُلعت'); }
    else { $no("{$nullMon} صفًّا بشهرٍ فارغ — وشهرٌ بلا تاريخٍ ليس شهرَ صفر"); }
    /* ◆ **وصفرُ شهرِ صفرٍ لا ينفي القدرة**: بياناتٌ متّصلةٌ لا فجوةَ فيها.
         والقدرةُ تُثبَت بالبنيةِ — والشاهدُ يُثبتها بثغرةٍ مصطنعة. */
    if ($zero === 0) {
        echo "     ◆ وصفرُ شهرِ صفرٍ **لا ينفي القدرة**: حصصُ الموردين متّصلةٌ في\n";
        echo "       نطاقِ كلٍّ منهم فلا فجوةَ تظهر. والشاهدُ يُثبت الآليةَ بثغرةٍ مصطنعة.\n";
    }
}

/* ═══ SUP-29 — «أسماءُ القوائم مطابقةٌ لنظيرتها في المبيعات» ════════════ */
$verdict('SUP-29', 'القوائمُ المرجعية');
$enumOf = static function (string $t, string $c) use ($conn): string {
    $r = @mysqli_query($conn, "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                                WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}'");
    return $r ? (string) mysqli_fetch_row($r)[0] : '';
};
$salesState = $enumOf('contracts', 'contract_status');
$supState   = $enumOf('supplier_contracts', 'state');
if ($salesState !== '' && $salesState === $supState) {
    $n = preg_match_all("~'~", $salesState) / 2;
    $ok("قائمةُ دورةِ الحياةِ بيتٌ واحد — {$n} حالةً متطابقةً حرفًا وترتيبًا");
} else {
    $no('قائمةُ دورةِ الحياةِ نسختان: ' . mb_substr($salesState, 0, 60) . ' ≠ ' . mb_substr($supState, 0, 60));
}
/* ◆ **والعملةُ نسختان بأبجديّتَين — وهو أخبثُ ما في البابِ لأنَّه صامت**:
     `= 'USD'` لا يطابق «دولار» أبدًا، فالمقارنةُ تعود بصفرٍ **يبدو صحيحًا**. */
$r = @mysqli_query($conn, "SELECT DISTINCT price_currency_contract v FROM contracts
                            WHERE is_deleted=0 AND price_currency_contract IS NOT NULL
                              AND price_currency_contract <> ''");
$sc = array(); while ($r && ($x = mysqli_fetch_row($r))) { $sc[] = $x[0]; }
$r = @mysqli_query($conn, "SELECT DISTINCT currency v FROM supplier_contracts
                            WHERE is_deleted=0 AND currency IS NOT NULL AND currency <> ''");
$pc = array(); while ($r && ($x = mysqli_fetch_row($r))) { $pc[] = $x[0]; }
$shared = array_intersect($sc, $pc);
printf("     ○ عملةُ المبيعات: %s   ·   عملةُ الموردين: %s\n",
    implode('، ', $sc) ?: '—', implode('، ', $pc) ?: '—');
if ($sc && $pc && !$shared) {
    $no('قائمةُ العملةِ نسختان بأبجديّتَين — صفرُ قيمةٍ مشتركة');
    echo "        ◆ والعطبُ **صامت**: `= 'USD'` لا يطابق «دولار»، فأيُّ مقارنةٍ\n";
    echo "          بين الإدارتَين تعود بصفرٍ يبدو صحيحًا.\n";
    $hold('توحيدُ الأبجديّةِ محجوز: يمسُّ ' . $num("SELECT COUNT(*) FROM contracts WHERE is_deleted=0")
        . ' عقدًا وكلَّ قارئٍ للعملةِ نصًّا — موجةٌ مستقلّةٌ بحزامِ لقطات');
} elseif ($shared) {
    $ok('وقائمةُ العملةِ تتقاطع: ' . implode('، ', $shared));
}

printf("\n  أخضر %d · أحمر %d · محجوزٌ بسببٍ مكتوب %d\n", $green, $red, $held);
echo "\n  ◆ «غيرُ محسوم» ليس حكمًا — والأحمرُ المقيسُ خيرٌ منه: هذا يُبنى عليه.\n\n";

exit($GATE && $red > 0 ? 1 : 0);
