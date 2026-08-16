<?php
/**
 * ra11_uncalled_engines.php — كاشفُ «مبنيٌّ بلا مستدعٍ» (قراءةٌ فقط)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ لماذا: هذا نمطُ عيبٍ متكرِّرٌ في هذا النظام لا حادثةٌ مفردة —
 *     · B8: انتقالُ `UnderReview` مُعرَّفٌ في آلةِ الحالاتِ **بصفرِ موضعِ نداء**
 *       فوقفت 5,207 واقعةً عن الدفتر.
 *     · MD-05 (حزمة FIX): «13 حارسًا بصفر مستهلك».
 *     · وناقلُ الأحداث: أربعةُ مستهلكين لـ21 ألفَ واقعة.
 *   والجامعُ بينها: **الكودُ موجودٌ فيُقرأ اكتمالًا، ولا يناديه أحدٌ فلا أثرَ له.**
 *   فحصُ الوجودِ يخضرُّ وفحصُ الأثرِ يحمرُّ — ولا أحدَ يشغّل الثاني.
 *
 * ◆ ما يفعله: يجرد الدوالَّ العامةَ في خدماتِ `app/Services/` ثم يبحث عن نداءٍ
 *   لها خارجَ ملفِّها وخارجَ الاختبارات. وما لا نداءَ له يُبلَّغ بحجمِه —
 *   فالكبيرُ غيرُ المنادى خسارةٌ أكبر.
 *
 * ◆ وما لا يُدَّعى: غيابُ النداءِ النصيِّ لا يعني موتًا يقينًا — قد يُنادى
 *   ديناميكيًّا أو من قالبٍ أو من كرونٍ مجدوَلٍ خارجَ الشجرة. ولذلك المخرَجُ
 *   **قائمةُ اشتباهٍ مرتَّبةٌ بالحجم** لا حكمٌ نهائيّ، ولكلِّ بندٍ سببُ اشتباهِه.
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');
$ROOT = 'C:/wamp64/www/ems';

/* ── جمعُ ملفاتِ الشجرة ─────────────────────────────────────────── */
$all = [];
$walk = function (string $dir) use (&$walk, &$all) {
    foreach (scandir($dir) ?: [] as $f) {
        if ($f === '.' || $f === '..') { continue; }
        /* ◆ `storage/backups/` فيه **نسخٌ كاملةٌ من التطبيق** — لو مُسحت ظهر كلُّ
           صنفٍ مرتين أو ثلاثًا وبدا العددُ أضعافَ حقيقتِه. تُستبعد مع الأرشيف. */
        if (in_array($f, ['node_modules', '.git', 'vendor', '.claude', 'backups', 'archive'], true)) { continue; }
        $p = $dir . '/' . $f;
        if (is_dir($p)) { $walk($p); }
        elseif (substr($f, -4) === '.php') { $all[] = str_replace('\\', '/', $p); }
    }
};
$walk($ROOT);

/* نصوصُ الملفاتِ مرةً واحدة */
$src = [];
foreach ($all as $f) { $src[$f] = (string) file_get_contents($f); }
/* ◆ `tools/` **ليست اختبارات**: فيها كروناتٌ إنتاجيةٌ حقيقية (m16_risk_cron
   مثلًا يشغّل ثلاثَ عشرةَ قاعدةَ إشارةٍ دوريًّا). واستبعادُها أنتج بلاغًا كاذبًا
   عن RiskSignalEngine — فلا يُستبعد إلا `tests/`. */
$isTest = fn(string $f): bool => (strpos($f, '/tests/') !== false);

/* ── الخدماتُ ودوالُّها العامة ──────────────────────────────────── */
$services = array_values(array_filter($all, fn($f) => strpos($f, '/app/Services/') !== false));
echo "══════════════════════════════════════════════════════════════════════\n";
echo "  كاشفُ «مبنيٌّ بلا مستدعٍ» — " . count($services) . " ملفَّ خدمةٍ في app/Services\n";
echo "══════════════════════════════════════════════════════════════════════\n";

$suspects = [];
foreach ($services as $f) {
    $code = $src[$f];
    if (!preg_match('/\bclass\s+(\w+)/', $code, $cm)) { continue; }
    $class = $cm[1];
    $lines = substr_count($code, "\n") + 1;

    preg_match_all('/public\s+(?:static\s+)?function\s+(\w+)\s*\(/', $code, $mm);
    $methods = array_values(array_unique(array_diff($mm[1] ?? [], ['__construct', '__toString', '__get', '__set'])));
    if (!$methods) { continue; }

    /* أيُّ ذكرٍ للصنفِ خارجَ ملفِّه (نداءٌ أو تهيئةٌ أو تسجيلٌ في موزِّع)؟ */
    $classRefs = [];
    foreach ($all as $g) {
        if ($g === $f || $isTest($g)) { continue; }
        if (strpos($src[$g], $class) !== false) { $classRefs[] = str_replace($ROOT . '/', '', $g); }
    }

    /* ◆ النداءُ الديناميكيُّ يُعمي البحثَ النصيّ: `get_class_methods` و
       `call_user_func` و`$m()` تنادي بلا ذكرِ الاسمِ حرفًا. فإن وُجد في ملفٍ
       يذكر الصنفَ، عُدَّ الصنفُ كلُّه منادى ولا يُبلَّغ عنه. (فخُّ risk_settings.) */
    $dynamic = false;
    foreach ($classRefs as $rel) {
        $g = $ROOT . '/' . $rel;
        if (isset($src[$g]) && preg_match('/get_class_methods|call_user_func|method_exists|\$\w+\s*\(\s*\)/', $src[$g])) {
            $dynamic = true; break;
        }
    }
    if ($dynamic) { continue; }

    /* دوالُّ بلا نداءٍ نصيٍّ خارجَ ملفِّها */
    $uncalled = [];
    foreach ($methods as $m) {
        $hit = false;
        foreach ($all as $g) {
            if ($g === $f || $isTest($g)) { continue; }
            if (preg_match('/(?:::|->)\s*' . preg_quote($m, '/') . '\s*\(/', $src[$g])) { $hit = true; break; }
        }
        if (!$hit) { $uncalled[] = $m; }
    }
    if (!$uncalled) { continue; }

    $suspects[] = [
        'file' => str_replace($ROOT . '/', '', $f), 'class' => $class, 'lines' => $lines,
        'methods' => count($methods), 'uncalled' => $uncalled,
        'class_refs' => count($classRefs),
        'severity' => (count($uncalled) === count($methods) && $classRefs === []) ? 'ميتٌ تمامًا'
                    : ((count($uncalled) === count($methods)) ? 'الصنفُ مذكورٌ ودوالُّه لا تُنادى' : 'دوالُّ مفردة'),
    ];
}

/* الأخطرُ أولًا: الميتُ تمامًا ثم الأكبرُ حجمًا */
usort($suspects, function ($a, $b) {
    $rank = ['ميتٌ تمامًا' => 0, 'الصنفُ مذكورٌ ودوالُّه لا تُنادى' => 1, 'دوالُّ مفردة' => 2];
    $d = $rank[$a['severity']] <=> $rank[$b['severity']];
    return $d !== 0 ? $d : ($b['lines'] <=> $a['lines']);
});

$totalDeadLines = 0;
foreach ($suspects as $s) { if ($s['severity'] !== 'دوالُّ مفردة') { $totalDeadLines += $s['lines']; } }

$shown = 0;
foreach ($suspects as $s) {
    if ($s['severity'] === 'دوالُّ مفردة' && $shown > 18) { continue; }
    printf("\n▐ %s  (%s سطرًا · %d دالةً عامة)\n", $s['file'], number_format($s['lines']), $s['methods']);
    printf("   الحكم: %s · ذكرُ الصنفِ خارجَ ملفِّه: %d ملفًّا\n", $s['severity'], $s['class_refs']);
    printf("   بلا نداء: %s%s\n", implode(' · ', array_slice($s['uncalled'], 0, 8)),
        count($s['uncalled']) > 8 ? ' … +' . (count($s['uncalled']) - 8) : '');
    $shown++;
}

echo "\n══════════════════════════════════════════════════════════════════════\n";
printf("  خدماتٌ فيها دوالُّ بلا نداء: %d\n", count($suspects));
printf("  منها كلُّ دوالِّها بلا نداء: %d (%s سطرًا)\n",
    count(array_filter($suspects, fn($s) => $s['severity'] !== 'دوالُّ مفردة')), number_format($totalDeadLines));
echo "  ◆ قائمةُ اشتباهٍ لا حكمٌ نهائيّ: النداءُ قد يكون ديناميكيًّا أو من كرونٍ\n";
echo "    مجدوَلٍ خارجَ الشجرة. راجِعْ قبلَ الحذفِ أو الوصل.\n";

file_put_contents($ROOT . '/docs/reverse_audit_2026-08/evidence/uncalled_engines.json',
    json_encode(['measured_at' => gmdate('c'), 'services_scanned' => count($services),
                 'suspects' => $suspects], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "\nكُتب: evidence/uncalled_engines.json\n";
