<?php
/**
 * tests/injfrd66_w7_integrity_test.php — شاهدُ الموجةِ ⑦: معاييرُ «صفر X بلا Y»
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **إيجابيٌّ ①**: البوابةُ تقيس ثمانيةَ عشرَ معيارًا وتُصنِّفها ثلاثةَ أصناف.
 * ◆ **سالبٌ ②**: و`SAL-17` **مضمونٌ بالبناءِ لا نظيفٌ بالانضباط**: أردتُ زرعَ
 *   مطالبةٍ مكرَّرةٍ لأُثبت أنَّ البوابةَ ترصدها — **فرفضتها القاعدةُ نفسُها**
 *   بفهرسٍ فريد. فانقلب الشاهدُ إلى ما هو أقوى: يُثبَت القيدُ ويُثبَت رفضُه.
 * ◆ **سالبٌ ③**: وجدولٌ فارغٌ **لا يُحسَب نجاحًا** — يُعلَن «لا صفوفَ تُقاس».
 *   وهذا هو الفصلُ الذي يمنع أخطرَ أخضرَ كاذبٍ في هذه البوابة.
 * ◆ **سالبٌ ④**: وأوَّلُ صفٍّ يُزرَع في ذلك الجدولِ **يُحوِّل ⏸ إلى ✘** — فالسكونُ
 *   لم يكن انضباطًا.
 * ◆ **سالبٌ ⑤**: و«صفرٌ بالغياب» يُعلَن غيابًا: `SAL-12` صفرُه لأنَّ الطرفَ
 *   الآخرَ من الشرطِ فارغٌ — والبوابةُ تطبع الرقمَ الخامَّ معه.
 * ◆ **سالبٌ ⑥**: والوسمُ لا يُبرِّئ وحدَه: `PRE_SOD` أُلصق بسبعةٍ وعشرين صفًّا
 *   **أُنشئت بعدَ بدءِ الإنفاذ** — والبوابةُ تُعلن العددَ ولا تبتلعه.
 * ◆ **محجوزٌ ⑦**: سبعةُ أحمرَ مقيسٍ كلُّها **عملُ مشغّلٍ أو دَينٌ مؤرَّخ** لا
 *   هجرةُ بيانات.
 *
 * التشغيل: php tests/injfrd66_w7_integrity_test.php
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

/* ◆ لا يُسمّى العدّادُ `$pass`: `config.php` يُسنِده كلمةَ مرورِ القاعدة */
$nOk = 0; $nBad = 0;
$check = static function (bool $ok, string $msg) use (&$nOk, &$nBad): void {
    if ($ok) { $nOk++; echo "   ✔ {$msg}\n"; } else { $nBad++; echo "   ✘ {$msg}\n"; }
};
$num = static function (string $sql) use ($conn): int {
    $r = @mysqli_query($conn, $sql);
    return $r ? (int) mysqli_fetch_row($r)[0] : -1;
};
$gate = static function () use ($ROOT): array {
    $o = array(); $rc = 0;
    exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/injfrd66_w7_integrity_gate.php')
         . ' --gate 2>&1', $o, $rc);
    return array($rc, implode("\n", $o));
};

$PROBE_VIOL = 'ZZ-W7-PROBE';
$PROBE_IDEM = 'zz_injfrd66_w7_probe';
$wipe = static function () use ($conn, $PROBE_VIOL, $PROBE_IDEM): void {
    @mysqli_query($conn, "DELETE FROM sup_violations WHERE violation_no = '{$PROBE_VIOL}'
                             OR idem_key = '{$PROBE_IDEM}'");
    @mysqli_query($conn, "DELETE FROM claims WHERE claim_no LIKE 'ZZ-W7-%'");
};
$wipe();

echo "① إيجابيٌّ — البوابةُ تقيس وتُصنِّف:\n";
list($rc, $txt) = $gate();
$check(preg_match('~أخضر\s*(\d+)\s*·\s*أحمر\s*(\d+)\s*·\s*بلا ممارسةٍ تُقاس\s*(\d+)~u', $txt, $m) === 1,
    'الحصيلةُ تُعلن الأصنافَ الثلاثةَ منفصلةً'
    . (isset($m[1]) ? " (أخضر {$m[1]} · أحمر {$m[2]} · بلا ممارسة {$m[3]})" : ''));
$check(isset($m[3]) && (int) $m[3] > 0,
    'وفيها جداولُ بلا ممارسةٍ — ولم تُحسَب خضراء');
$check($rc === 1, "ورمزُ الخروجِ 1 بالأحمرِ المقيس (جاء {$rc})");

echo "\n② سالبٌ — SAL-17: الخرقُ مستحيلٌ بالبناءِ لا نظيفٌ بالانضباط:\n";
/* ◆ **ومحاولةُ الزرعِ هي الشاهد**: أردتُ أن أزرع مطالبةً مكرَّرةً لأُثبت أنَّ
     البوابةَ ترصدها — **فرفضتها القاعدةُ نفسُها**. وهذا أقوى: المعيارُ
     «صفر مطالبتين لفترةٍ واحدة» **مضمونٌ بفهرسٍ فريدٍ** لا بيقظةِ كاتب.
     ⇐ فيُقلَب الشاهد: يُثبَت القيدُ ويُثبَت أنَّه **يرفض فعلًا**، لا أنَّ
       البوابةَ تعُدُّ صفرًا. */
$uq = $num("SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='claims'
               AND INDEX_NAME='uq_claim_period' AND NON_UNIQUE=0");
$check($uq > 0, "فهرسٌ فريدٌ `uq_claim_period` قائمٌ على (شركة · عقد · من · إلى) — {$uq} أعمدة");
$before = $num("SELECT COUNT(*) FROM (SELECT contract_id, period_from, period_to, COUNT(*) c
                                        FROM claims WHERE is_deleted = 0
                                       GROUP BY contract_id, period_from, period_to HAVING c > 1) x");
$check($before === 0, "وصفرُ تكرارٍ في البياناتِ الحيّة (جاء {$before})");
$src = @mysqli_query($conn, "SELECT company_id, contract_id, client_id, project_id, currency,
                                    period_from, period_to, state
                               FROM claims WHERE is_deleted = 0 AND contract_id IS NOT NULL LIMIT 1");
$row = $src ? mysqli_fetch_assoc($src) : null;
if (!$row) { $nBad++; echo "   ✘ لا مطالبةَ تُنسَخ\n"; }
else {
    $st = mysqli_prepare($conn, "INSERT INTO claims
            (company_id, claim_no, contract_id, client_id, project_id, currency,
             period_from, period_to, gross_amount, net_amount, state, is_deleted, created_at)
            VALUES (?, 'ZZ-W7-DUP', ?, ?, ?, ?, ?, ?, 0, 0, ?, 0, NOW())");
    mysqli_stmt_bind_param($st, 'iiiissss', $row['company_id'], $row['contract_id'], $row['client_id'],
        $row['project_id'], $row['currency'], $row['period_from'], $row['period_to'], $row['state']);
    $seeded = @mysqli_stmt_execute($st);
    $err    = mysqli_error($conn);
    @mysqli_query($conn, "DELETE FROM claims WHERE claim_no = 'ZZ-W7-DUP'");
    $check(!$seeded, 'ومحاولةُ زرعِ مطالبةٍ ثانيةٍ للفترةِ نفسِها **رُفضت في القاعدة**');
    $check(mb_strpos($err, 'uq_claim_period') !== false,
        'ورفضَها الفهرسُ المسمَّى نفسُه: ' . mb_substr($err, 0, 70));
    $check($num("SELECT COUNT(*) FROM claims WHERE claim_no='ZZ-W7-DUP'") === 0, 'ولا أثرَ للمسبار');
}

echo "\n③ سالبٌ — الجدولُ الفارغُ يُعلَن لا يُخضَّر:\n";
$violRows = $num("SELECT COUNT(*) FROM sup_violations");
$check($violRows === 0, "`sup_violations` فارغٌ فعلًا ({$violRows} صفًّا)");
$check(preg_match('~SUP-23\s+⏸~u', $txt) === 1, 'والبوابةُ تُعلنه «لا صفوفَ تُقاس» لا «✔ صفر»');
$check(preg_match('~SUP-23\s+✔~u', $txt) === 0, 'ولم تُخضِّره');

echo "\n④ سالبٌ — وأوَّلُ صفٍّ يُحوِّل ⏸ إلى ✘ (فالسكونُ لم يكن انضباطًا):\n";
$sup = @mysqli_query($conn, "SELECT id, company_id FROM suppliers WHERE is_deleted = 0 LIMIT 1");
$s = $sup ? mysqli_fetch_assoc($sup) : null;
if (!$s) { $nBad++; echo "   ✘ لا موردَ يُسنَد إليه المسبار\n"; }
else {
    /* جزاءٌ بلا تسوية — الخرقُ الذي يقيسه المعيارُ بعينِه */
    $st2 = mysqli_prepare($conn, "INSERT INTO sup_violations
            (company_id, violation_no, supplier_id, settlement_id, violation_kind,
             occurred_on, description, penalty_amount, currency, recorded_by, state, idem_key)
            VALUES (?, ?, ?, NULL, 'quality', CURDATE(), 'مسبارٌ — يُزال فورَ القياس', 1.00, 'USD', 1, 'recorded', ?)");
    /* ◆ والعملةُ لازمةٌ بقيدٍ قائم: `chk_sv_cur` يشترط عملةً بثلاثةِ أحرفٍ
         لكلِّ جزاءٍ غيرِ صفريّ — فمسبارٌ بلا عملةٍ يُرفَض قبلَ أن يُقاس. */
    mysqli_stmt_bind_param($st2, 'isis', $s['company_id'], $PROBE_VIOL, $s['id'], $PROBE_IDEM);
    if (!mysqli_stmt_execute($st2)) { $nBad++; echo "   ✘ تعذّر الزرع: " . mysqli_error($conn) . "\n"; }
    else {
        list($rc4, $txt4) = $gate();
        $wipe();
        $check(preg_match('~SUP-23\s+✘~u', $txt4) === 1,
            'بصفٍّ واحدٍ صارت SUP-23 حمراءَ — والمعيارُ كان صامتًا لا سليمًا');
        $check($rc4 === 1, "ورمزُ الخروج 1 (جاء {$rc4})");
        $check($num("SELECT COUNT(*) FROM sup_violations") === 0, 'وأُزيل المسبار');
    }
}

echo "\n⑤ سالبٌ — «صفرٌ بالغياب» يُعلَن غيابًا لا انضباطًا:\n";
$manual = $num("SELECT COUNT(*) FROM client_contract_lines
                 WHERE is_deleted = 0 AND (source_commitment_id IS NULL OR source_commitment_id = 0)");
$qLines = $num("SELECT COUNT(*) FROM sal_quotation_lines");
$check($manual > 0 && $qLines === 0,
    "الطرفانِ: {$manual} بندًا بلا مصدرٍ مُعلَن · و{$qLines} بندَ عرضٍ يقابله");
$check(mb_strpos($txt, 'صفرٌ **بالغياب** لا بالانضباط') !== false,
    'والبوابةُ تُعلن ذلك مع الصفرِ نفسِه — فلا يُقرأ انضباطًا');

echo "\n⑥ سالبٌ — الوسمُ لا يُبرِّئ وحدَه:\n";
$pre = $num("SELECT COUNT(*) FROM fin_payments WHERE sod_state='PRE_SOD'");
$preAfter = $num("SELECT COUNT(*) FROM fin_payments
                   WHERE sod_state='PRE_SOD' AND created_by = executed_by
                     AND created_at > (SELECT MIN(created_at) FROM fin_payments WHERE sod_state='ENFORCED')");
$check($preAfter > 0,
    "{$preAfter} من {$pre} صفًّا موسومًا «ما قبلَ الفصل» **أُنشئ بعدَ بدءِ الإنفاذ**");
$check(mb_strpos($txt, 'أُنشئ بعدَ بدءِ الإنفاذ') !== false,
    'والبوابةُ تُعلن العددَ ولا تبتلعه في الأخضر');
$check($num("SELECT COUNT(*) FROM fin_payments WHERE sod_state='ENFORCED' AND created_by=executed_by") === 0,
    'وصفرُ خرقٍ بينَ الصفوفِ الخاضعةِ للإنفاذِ فعلًا');

echo "\n⑦ محجوزٌ بسببٍ مكتوب — سبعةُ أحمرَ مقيسٍ ليست هجرةَ بيانات:\n";
$held = array(
    'SAL-04' => array($num("SELECT COUNT(*) FROM opportunities o WHERE o.is_deleted=0
                             AND o.stage NOT IN ('فوز','خسارة','مستبعدة')
                             AND NOT EXISTS(SELECT 1 FROM activities a WHERE a.entity_type='opportunity'
                                             AND a.entity_id=o.id AND a.is_deleted=0)"),
                      'فرصةً مفتوحةً بلا نشاطٍ تالٍ — **تسجيلُ النشاطِ فعلُ بائعٍ** لا استنتاج'),
    'SAL-10' => array($num("SELECT COUNT(*) FROM contracts WHERE is_deleted=0
                             AND (readiness_state IS NULL OR readiness_state <> 'مجتاز')"),
                      'عقدًا مراجعتُه غيرُ مكتملة — **إكمالُ المراجعةِ فعلُ مراجِع**'),
    'SUP-06' => array($num("SELECT COUNT(*) FROM supplier_contracts WHERE is_deleted=0"),
                      'عقدًا كلُّها مُرحَّلةٌ من سجلٍّ **سابقٍ لقدرةِ الترشيح** — دَينٌ مؤرَّخٌ لا خرقُ حارس'),
    'SUP-24' => array($num("SELECT COUNT(*) FROM (SELECT DISTINCT sc.supplier_id FROM supplier_contracts sc
                              WHERE sc.is_deleted=0 AND NOT EXISTS(SELECT 1 FROM supplier_evaluations e
                                WHERE e.supplier_id=sc.supplier_id AND e.is_deleted=0)) y"),
                      'موردًا بلا تقييمٍ دوريّ — **التقييمُ فعلُ مُقيِّمٍ بفترةٍ** لا صفٌّ يُولَّد'),
    /* ◆ **ونصفا معيارٍ كِدتُ أطويهما**: `SUP-19` و`SUP-20` نصفُهما الأولُ أخضرُ
         (إقفالٌ بلا اعتماد = 0 · وصرفٌ خاضعٌ للإنفاذِ = 0)، ولو اكتُفي به
         لقُرئ المتطلبانِ مقبولَين وفيهما ألفٌ وثلاثُمئةٍ وأربعةٌ وستون خرقًا
         مؤرَّخًا. **ونصفُ معيارٍ يُقاس ونصفٌ يُطوى أسوأُ من معيارٍ لم يُقَس** —
         لأنَّ الأولَ يُغلَق والثاني يبقى مفتوحًا يُطلَب. */
    'SUP-19' => array($num("SELECT COUNT(*) FROM settlements WHERE is_deleted=0
                             AND prepared_by IS NOT NULL AND prepared_by = approved_by"),
                      'تسويةً مُعِدُّها معتمِدُها — **دَينٌ مؤرَّخٌ يسبق الحارس**، والمعيارُ «مقبول = جزئي» لا «نعم»'),
    'SUP-20' => array($num("SELECT COUNT(*) FROM fin_payments WHERE created_by = executed_by"),
                      'صرفًا طالبُه صارفُه بأيِّ وسم — منها 27 موسومةٌ «ما قبلَ الفصل» **بعدَ بدءِ الإنفاذ**'),
);
$held['SUP-15'] = array(
    $num("SELECT COUNT(*) FROM fin_entitlements e
            LEFT JOIN fin_unit_records u ON u.id = e.unit_record_id AND u.is_deleted = 0
           WHERE u.id IS NULL OR u.match_state <> 'approved'"),
    'استحقاقًا من وحدةٍ غيرِ معتمدةٍ **أو مرجعُها معدوم** — والقدرةُ غيرُ مبنيّةٍ أصلًا');
foreach ($held as $req => $h) { printf("   ⏸ %s — %d %s\n", $req, $h[0], $h[1]); }
$check(count($held) === 7, 'وكلُّها معلَنةٌ بعددِها وسببِها');

echo "\n⑧ سالبٌ — الوصلةُ التي تُسقِط صفوفًا تُخفيها:\n";
/* ◆ **وأجملُ الرقمَين هو الخطأ**: وصلةٌ داخليةٌ على سجلِّ الوحدةِ تُبلغ واحدًا،
     والخارجيةُ تُبلغ ثلاثةَ عشر — والفرقُ اثنا عشرَ استحقاقًا **مرجعُها لا
     وجودَ له**. ولو اكتُفي بالداخليةِ لبدا المتطلبُ شبهَ مغلَق. */
$innerN = $num("SELECT COUNT(*) FROM fin_entitlements e
                  JOIN fin_unit_records u ON u.id = e.unit_record_id AND u.is_deleted = 0
                 WHERE u.match_state <> 'approved'");
$outerN = $num("SELECT COUNT(*) FROM fin_entitlements e
             LEFT JOIN fin_unit_records u ON u.id = e.unit_record_id AND u.is_deleted = 0
                 WHERE u.id IS NULL OR u.match_state <> 'approved'");
$dangl  = $num("SELECT COUNT(*) FROM fin_entitlements e
             LEFT JOIN fin_unit_records u ON u.id = e.unit_record_id AND u.is_deleted = 0
                 WHERE u.id IS NULL");
$check($innerN < $outerN, "الداخليةُ {$innerN} · والخارجيةُ {$outerN} — والفرقُ {$dangl} مرجعًا معدومًا");
$check(mb_strpos($txt, 'تُسقط المرجعَ المعدومَ وتُجمِّل الرقم') !== false,
    'والبوابةُ تُعلن الرقمَ الأجملَ لتفضحه لا لتستعمله');

echo "\n⑨ إيجابيٌّ — ستةُ متطلباتٍ قارئُها بوابةُ الموجةِ ③ (لا يُعاد عدُّها هنا):\n";
/* ◆ **قارئٌ واحدٌ لكلِّ معيار**: عدّادانِ في ملفَّين يتفرّقان بأوَّلِ تعديل.
     فما تقيسه `w3_gate` لا يُكرَّر في `w7` — ويُتحقَّق هنا أنَّه **مقيسٌ فعلًا**. */
$o3 = array(); $rc3 = 0;
exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/injfrd66_w3_gate.php') . ' 2>&1', $o3, $rc3);
$t3 = implode("\n", $o3);
foreach (array('SAL-11', 'SAL-13', 'SAL-14', 'SUP-10', 'SUP-18', 'SUP-25') as $code) {
    $check(preg_match('~' . $code . '\b[^
]*(✔|✘|ــ)~u', $t3) === 1, "{$code} له حكمٌ في بوابةِ الموجةِ ③");
}
/* ◆ **و«نافذ» حالتان لا حالة**: قصرُ `SAL-14` على «نافذ» وحدَها كان يُبلغ
     واحدًا وهي أربعة — وعقدٌ قيدَ التنفيذِ بلا خطِّ أساسٍ خرقٌ **أشدّ**. */
$wide = $num("SELECT COUNT(*) FROM contracts c WHERE c.is_deleted=0
                AND c.contract_status IN ('نافذ','قيد التنفيذ')
                AND NOT EXISTS(SELECT 1 FROM contract_baseline b WHERE b.contract_id=c.id AND b.is_deleted=0)");
$narrow = $num("SELECT COUNT(*) FROM contracts c WHERE c.is_deleted=0
                  AND c.contract_status = 'نافذ'
                  AND NOT EXISTS(SELECT 1 FROM contract_baseline b WHERE b.contract_id=c.id AND b.is_deleted=0)");
$check($wide > $narrow, "و«نافذ» وحدَها تُبلغ {$narrow} بينما الحالتانِ تُبلغان {$wide}");
$check(preg_match('~SAL-14\s+عقدٌ نافذٌ أو قيدَ التنفيذِ~u', $t3) === 1,
    'والبوابةُ صُحِّحت لتقيس الحالتَين');

printf("\n%s  ناجح %d · راسب %d · محجوز %d\n",
    $nBad === 0 ? '✔ الموجة ⑦' : '✘ الموجة ⑦', $nOk, $nBad, count($held));
exit($nBad === 0 ? 0 : 1);
