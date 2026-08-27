<?php
/**
 * tools/repair01_edc_classify.php — الدَّينُ كلُّه في أصنافٍ مُدارة
 * ═══════════════════════════════════════════════════════════════════════════
 * **حكمُ المالك 2026-08-27**: «**دَينٌ غيرُ مصنَّفٍ = دَينٌ غيرُ مُدار**».
 * فلكلِّ صنفٍ خمسةُ حقول: `Count` · `Blocking Level` · `Assigned Wave` ·
 * `Owner` · `Exit Criteria`.
 *
 * ⛔ **والعددُ يُقاس في كلِّ جولةٍ ولا يُكتب**: كلُّ صفٍّ يحمل استعلامَ قياسِه
 *   والأداةُ تشغّله. **ورقمٌ مكتوبٌ يدويًّا يتقادم صامتًا** — وهو نمطٌ تكرّر في
 *   هذه الحملةِ ثلاثَ مرّات: كاشفٌ لا يعيد القراءة · دفترٌ لا يتبع نطاقَه ·
 *   وأداةٌ أُصلح مخرَجُها لا هي.
 *
 * ◆ **ومستوى الحجبِ ليس رأيًا**: `BLOCKING` ما يمنع `Closure` **بنصِّ المالك**
 *   (`UNKNOWN_WRITE_OWNER` · `DUPLICATE_CANONICAL_SOURCE` ·
 *   `CRITICAL_DOMAIN_BOUNDARY_VIOLATION`) · و`MAJOR` ما يمنع موجةً · و`MINOR`
 *   ما يُجدوَل · و`INFORMATIONAL` **ما يُعلَن ولا يُحكم به**.
 *
 * ◆ والخمسةَ عشرَ من البند 50 بأسمائها، **وثلاثةٌ زائدةٌ رصدَتها الحملةُ ولم
 *   يسمِّها الأمرُ** — تُعلَن مفصولةً ⛔ **ولا تُدسُّ في الخمسةَ عشرَ فتُشوّش
 *   مقامَ حكمٍ منصوص**.
 *
 * التشغيل: php tools/repair01_edc_classify.php [--seed] [--md]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');
$SEED = in_array('--seed', $argv, true);
$MD   = in_array('--md', $argv, true);
$e = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$LIVE = "on_disk = 1 AND ownership_verdict NOT IN ('RETIRE')";

/* ═══ الأصنافُ — أسماءُ الخمسةَ عشرَ من البند 50 ثمَّ ثلاثةٌ مُعلَنةٌ زائدة ═══ */
$C = array(
 array('DC-01', 'معرّفٌ معياريٌّ ناقص',
  "SELECT COUNT(*) FROM repair01_screen_registry WHERE COALESCE(screen_id,'') = '' AND $LIVE",
  'BLOCKING', 'W13.5', 'DEP-08', 'CANONICAL_ID_MISSING = 0',
  'سطح بلا معرف لا يمكن الاستشهاد به في اي قرار'),
 array('DC-02', 'مسمًّى عربيٌّ معياريٌّ ناقص',
  "SELECT COUNT(*) FROM repair01_screen_registry WHERE COALESCE(canonical_label_ar,'') = '' AND $LIVE",
  'MAJOR', 'W13.5', 'DEP-08', 'CANONICAL_LABEL_MISSING = 0',
  'المستخدم يقرا الاسم لا اسم الملف'),
 array('DC-03', 'حارسُ عرضٍ ناقص',
  "SELECT COUNT(*) FROM repair01_screen_registry WHERE COALESCE(guard_kind,'') = '' AND $LIVE",
  'BLOCKING', 'W13.5', 'DEP-08', 'VIEW_GUARD_MISSING = 0',
  'سطح بلا حارس مفتوح افتراضا'),
 array('DC-04', 'سياسةُ صلاحيةٍ ناقصة',
  "SELECT COUNT(*) FROM repair01_screen_registry WHERE COALESCE(permission_policy,'') = '' AND $LIVE",
  'MAJOR', 'W13.5', 'DEP-08', 'PERMISSION_POLICY_MISSING = 0',
  'لا يعرف من يراه'),
 array('DC-05', 'مالكٌ مجهول',
  "SELECT COUNT(*) FROM repair01_screen_registry WHERE COALESCE(owner_code,'') = '' AND $LIVE",
  'BLOCKING', 'W13.5', 'DEP-08', 'UNKNOWN_WRITE_OWNER = 0',
  'شرط اغلاق منصوص في حكم المالك'),
 array('DC-06', 'مصدرٌ مكرَّرٌ لحقيقةٍ واحدة',
  "SELECT COUNT(*) FROM (SELECT LOWER(COALESCE(NULLIF(route,''), screen_file)) f
     FROM repair01_screen_registry WHERE surface_kind = 'SOURCE'
    GROUP BY f HAVING COUNT(DISTINCT owner_code) > 1) z",
  'BLOCKING', 'W13.5', 'DEP-08', 'DUPLICATE_CANONICAL_SOURCE = 0',
  'شرط اغلاق منصوص في حكم المالك'),
 array('DC-07', 'شاشةٌ يتيمةٌ بلا حكمِ ملكيّة',
  "SELECT COUNT(*) FROM repair01_screen_registry WHERE COALESCE(ownership_verdict,'') = '' AND $LIVE",
  'BLOCKING', 'W13.5', 'DEP-08', 'OWNERSHIP_VERDICT_MISSING = 0',
  'بلا حكم لا تدخل اي موجة'),
 /* ⚠ **وصفرٌ من مفردةٍ لا وجودَ لها ليس إغلاقًا**: كتبتُ أوّلًا
      `finance_debt_class = 'LEGACY_READ_ONLY'` — **ومفرداتُ العمودِ الحيّةُ**
      `SOURCE/RETIRE/TARGET/MERGE/PROJECTION` لا غير. فأعطى العدّادُ صفرًا
      **وهو أخضرُ كاذب**. ⇒ **ورابعُ ظهورٍ لهذا النمطِ اليومَ**، فحُرس بـ`vocabOk`
      أسفلَه: كلُّ مفردةٍ في الاستعلامِ تُطابَق بقيمِ العمودِ الحيّة. */
 array('DC-08', 'موروثٌ ماليٌّ حيٌّ مُعلَنٌ للتقاعدِ أو الدمج',
  "SELECT COUNT(*) FROM repair01_screen_registry WHERE owner_code IN ('DEP-05','DEP-06')
     AND finance_debt_class IN ('RETIRE','MERGE') AND $LIVE",
  'INFORMATIONAL', 'W11', 'DEP-05', 'يعلن ولا يحجب — مصنف ومسيج سلفا',
  'سيج في الحادية عشرة · والمعلن للتقاعد يبقى مرئيا حتى ينفذ'),
 array('DC-09', 'تكرارُ الخزينةِ — سطحانِ لمعنًى واحد',
  "SELECT COUNT(*) FROM (SELECT canonical_label_ar l FROM repair01_screen_registry
     WHERE owner_code IN ('DEP-05','DEP-06') AND canonical_label_ar <> '' AND $LIVE
     GROUP BY l HAVING COUNT(*) > 1) z",
  'MAJOR', 'W11', 'DEP-06', 'TREASURY_LABEL_DUP = 0',
  'اسم واحد لسطحين يربك المستخدم'),
 array('DC-10', 'تداخلُ الموارد والقوى',
  "SELECT COUNT(*) FROM repair01_screen_registry
    WHERE owner_code IN ('DEP-07','DEP-13') AND surface_kind = '' AND $LIVE",
  'MAJOR', 'W13', 'DEP-07', 'CROSS_DOMAIN_UNRESOLVED = 0',
  'الموارد البشرية تملك سجل الانسان'),
 array('DC-11', 'تداخلُ المشترياتِ والمخازن',
  "SELECT COUNT(*) FROM repair01_screen_registry
    WHERE owner_code IN ('DEP-16','DEP-17') AND surface_kind = '' AND $LIVE",
  'MAJOR', 'W09', 'DEP-17', 'CROSS_DOMAIN_UNRESOLVED = 0',
  'المخازن تملك الرصيد والمشتريات تملك الطلب'),
 array('DC-12', 'تداخلُ التمويلِ والخزينة',
  "SELECT COUNT(*) FROM repair01_screen_registry
    WHERE owner_code IN ('DEP-03','DEP-06') AND surface_kind = '' AND $LIVE",
  'MAJOR', 'W12', 'DEP-06', 'CROSS_DOMAIN_UNRESOLVED = 0',
  'الخزينة تنفذ الدفع والتمويل يقرر'),
 array('DC-13', 'تبويباتٌ لم تُدمَج في أبيها',
  "SELECT COUNT(*) FROM repair01_screen_registry WHERE ownership_verdict = 'TAB_CHILD' AND on_disk = 1",
  'MINOR', 'W16', 'DEP-08', 'كل تبويب يحكم على حدة بقرار مسجل',
  'حكم المالك: لا نضحي بالصلاحية من اجل سايدبار انظف — والثمانية والخمسون تحكم فرادى'),
 array('DC-14', 'أشباحٌ بلا قرارٍ من الستّة',
  "SELECT COUNT(*) FROM repair01_target_gaps WHERE ghost_disposition = ''",
  'MINOR', 'W13.5', 'DEP-08', 'GHOST_WITHOUT_DISPOSITION = 0',
  'هدف مسجل بلا سطح يحتاج قرارا من الستة'),
 array('DC-15', 'مسارٌ متقاعدٌ ما زال مسجَّلًا حيًّا',
  "SELECT COUNT(*) FROM repair01_screen_registry WHERE ownership_verdict = 'RETIRE' AND on_disk = 1",
  'MINOR', 'W13.5', 'DEP-08', 'RETIRED_STILL_LIVE = 0',
  'متقاعد حي يشوش كل مقام يعده'),
 /* ── ثلاثةٌ رصدَتها الحملةُ ولم يسمِّها البندُ 50 — مفصولةٌ لا مدسوسة ── */
 array('DC-16', 'اسمٌ معروضٌ غيرُ معتمَد',
  "SELECT COUNT(*) FROM nav_canonical WHERE status <> 'APPROVED'",
  'MINOR', 'W13.5', 'DEP-08', 'NON_APPROVED_RENDERED_NAME = 0',
  'القرار الرابع — مصالحة الاسماء'),
 array('DC-17', 'سطحٌ موروثٌ ينتظر التحليلَ الجنائيّ',
  "SELECT COUNT(*) FROM repair01_screen_registry WHERE ownership_verdict = 'LEGACY' AND on_disk = 1",
  'MINOR', 'W13.5', 'DEP-08', 'LEGACY_PENDING_FORENSIC = 0',
  'القرار الخامس — التحليل الجنائي بار بعة عشر نقطة'),
 array('DC-18', 'سطحُ نموٍّ بلا صندوقِ فلترة',
  '',   /* ⛔ يُقاس ملفّيًّا لا باستعلام — والفراغُ إعلانُ ذلك لا إهمالُه */
  'MINOR', 'W16', 'DEP-08', 'ELIGIBLE_LIST_SURFACES_WITHOUT_CANONICAL_FILTER = 0',
  'حكم المالك: مكون فلترة معياري واحد يبنى مرة لا مئة وسبع نسخ'),
);

if ($SEED) {
    foreach ($C as $c) {
        list($code, $nm, $sql, $lvl, $wave, $own, $exit, $rule) = $c;
        $conn->query("INSERT INTO repair01_debt_register
            (class_code, class_name_ar, measure_sql, blocking_level, assigned_wave,
             debt_owner, exit_criteria, owner_ruling)
            VALUES ('" . $e($code) . "', '" . $e($nm) . "', '" . $e($sql) . "', '" . $e($lvl) . "',
                    '" . $e($wave) . "', '" . $e($own) . "', '" . $e($exit) . "', '" . $e($rule) . "')
            ON DUPLICATE KEY UPDATE measure_sql = VALUES(measure_sql),
                blocking_level = VALUES(blocking_level), assigned_wave = VALUES(assigned_wave),
                debt_owner = VALUES(debt_owner), exit_criteria = VALUES(exit_criteria),
                owner_ruling = VALUES(owner_ruling)");
    }
    echo "✔ بُذر " . count($C) . " صنفًا\n";
}

/* ── قياسُ الأصنافِ الملفّيّة ─────────────────────────────────────────────── */
$idx = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $p) {
    if (substr($p->getFilename(), -4) !== '.php') { continue; }
    $s = strtr($p->getPathname(), DIRECTORY_SEPARATOR, '/');
    if (strpos($s, '/.git/') !== false || strpos($s, '/vendor/') !== false) { continue; }
    if (!isset($idx[$p->getFilename()])) { $idx[$p->getFilename()] = $s; }
}
$filterDebt = function () use ($conn, $idx) {
    $n = 0;
    $r = $conn->query("SELECT screen_file FROM repair01_screen_registry
                        WHERE origin REGEXP '^W[0-9]+$' AND on_disk = 1");
    while ($r && ($x = $r->fetch_row())) {
        $b = basename($x[0]);
        if (isset($idx[$b]) && strpos((string) @file_get_contents($idx[$b]), 'ems-filters') === false) { $n++; }
    }
    return $n;
};

/* ═══ حارسُ المفردات — صفرٌ من مفردةٍ لا وجودَ لها ليس إغلاقًا ══════════════
 * **رابعُ ظهورٍ لهذا النمطِ في يومٍ واحد**: حكمٌ بمفردةٍ لم تُقرأ من مصدرِها
 * فيُعطي **أخضرَ كاذبًا**. وهنا يُستخرَج من كلِّ استعلامٍ ما يقارنه بعمود
 * (`col = 'X'` أو `col IN ('X','Y')`) **ثمَّ يُسأل العمودُ نفسُه**: أعندك
 * هذه المفردةُ أصلًا؟ ⇒ **فمقياسٌ بمفردةٍ ميتةٍ يُرسَّب ولا يُصفَّر.**
 * ⛔ ويُستثنى ما يقارن رمزَ إدارةٍ (`DEP-`) فهو مرجعٌ لا مفردةُ حالة. */
$vocabOk = function ($sql) use ($conn) {
    $bad = array();
    if (!preg_match_all("~`?(\\w+)`?\\s*(?:=|IN)\\s*\\(?\\s*((?:'[^']*'\\s*,?\\s*)+)\\)?~i", $sql, $m, PREG_SET_ORDER)) {
        return $bad;
    }
    foreach ($m as $x) {
        $col = $x[1];
        if (in_array(strtolower($col), array('owner_code', 'screen_file', 'route', 'class_code', 'origin'), true)) { continue; }
        preg_match_all("~'([^']*)'~", $x[2], $vs);
        foreach ($vs[1] as $v) {
            if ($v === '' || strpos($v, 'DEP-') === 0) { continue; }
            /* أموجودةٌ هذه المفردةُ في العمودِ فعلًا؟ */
            $r = @$conn->query("SELECT 1 FROM repair01_screen_registry
                                 WHERE `" . $conn->real_escape_string($col) . "` = '"
                                 . $conn->real_escape_string($v) . "' LIMIT 1");
            if ($r === false) { continue; }          /* عمودٌ في جدولٍ آخرَ — لا يُحكم عليه هنا */
            if ($r->num_rows === 0) { $bad[] = "$col='$v'"; }
        }
    }
    return $bad;
};

/* ── القياسُ الحيُّ لكلِّ صفٍّ من استعلامِه هو ─────────────────────────────── */
$rows = array(); $tot = 0; $blk = 0; $vac = array();
$q = $conn->query("SELECT * FROM repair01_debt_register ORDER BY
                     FIELD(blocking_level, 'BLOCKING', 'MAJOR', 'MINOR', 'INFORMATIONAL'), class_code");
while ($q && ($x = $q->fetch_assoc())) {
    if ((string) $x['measure_sql'] === '') { $n = $filterDebt(); }
    else { $r2 = @$conn->query($x['measure_sql']); $n = $r2 ? (int) $r2->fetch_row()[0] : -1; }
    $conn->query("UPDATE repair01_debt_register SET measured_count = $n, measured_at = NOW()
                   WHERE class_code = '" . $e($x['class_code']) . "'");
    /* والصفرُ يُمحَّص: أهو إغلاقٌ أم مفردةٌ ميتة؟ */
 if ($n === 0 && (string) $x['measure_sql'] !== '') {
 $b = $vocabOk($x['measure_sql']);
 if ($b) { $vac[] = array($x['class_code'], implode(' · ', $b)); }
 }
    $x['n'] = $n; $rows[] = $x;
    if ($n > 0) { $tot += $n; if ($x['blocking_level'] === 'BLOCKING') { $blk += $n; } }
}

$L = array('BLOCKING' => 'حاجب', 'MAJOR' => 'كبير', 'MINOR' => 'صغير', 'INFORMATIONAL' => 'خبر');
$closed = 0;
foreach ($rows as $x) { if ($x['n'] === 0) { $closed++; } }

if ($MD) {
    $o  = "# سجلُّ أصنافِ الدَّينِ — البندُ ⑥ من أمرِ المالك\n\n";
    $o .= "> ⛔ **مولَّدٌ من المخزن**: `php tools/repair01_edc_classify.php --md`\n";
    $o .= "> **حكمُ المالك 2026-08-27**: «دَينٌ غيرُ مصنَّفٍ = دَينٌ غيرُ مُدار».\n";
    $o .= "> **والعددُ مقيسٌ في هذه الجولةِ ولا يُكتب** — كلُّ صفٍّ يحمل استعلامَ قياسِه.\n\n";
    $o .= "| الصنف | الاسم | العدد | الحجب | الموجة | المالك | معيارُ الخروج |\n";
    $o .= "|---|---|---:|---|---|---|---|\n";
    foreach ($rows as $x) {
        $o .= sprintf("| `%s` | %s | %d | %s | %s | %s | `%s` |\n",
            $x['class_code'], $x['class_name_ar'], $x['n'], $L[$x['blocking_level']],
            $x['assigned_wave'], $x['debt_owner'], $x['exit_criteria']);
    }
    $o .= sprintf("\n**المجموع %d صفًّا** · **وما يحجب الإغلاقَ منها %d** · ", $tot, $blk);
    $o .= sprintf("أصنافٌ مُغلَقة **%d من %d**\n\n", $closed, count($rows));
    $o .= "## أحكامُ المالكِ المرتبطةُ بالأصناف\n\n";
    foreach ($rows as $x) {
        if ((string) $x['owner_ruling'] === '') { continue; }
        $o .= "- `{$x['class_code']}` — {$x['owner_ruling']}\n";
    }
    @mkdir($ROOT . '/docs/REPAIR01_20260823', 0777, true);
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/EDC_DEBT_REGISTER.md', $o);
    echo "✔ كُتب docs/REPAIR01_20260823/EDC_DEBT_REGISTER.md\n";
}

echo "\n═══ سجلُّ أصنافِ الدَّين — العددُ مقيسٌ الآن لا مكتوبٌ سابقًا ═══\n";
foreach ($rows as $x) {
    printf("  %s %-6s %-36s %5d  %-5s %-6s %s\n",
        ($x['n'] === 0 ? '✔' : ($x['n'] < 0 ? '⚠' : '◆')),
        $x['class_code'], $x['class_name_ar'], $x['n'], $L[$x['blocking_level']],
        $x['assigned_wave'], $x['debt_owner']);
}
echo "  ──────────────────────────────────────────────────────────────────\n";
printf("  **المجموع %d صفًّا · وما يحجب الإغلاقَ منها %d**\n", $tot, $blk);
printf("  أصنافٌ مُغلَقة: %d من %d · **وصفرُ صفٍّ خارجَ التصنيف**\n", $closed, count($rows));
/* ── الحكمُ الأخير: أَصفرٌ هو أم مفردةٌ ميتة؟ ─────────────────────────────── */
if ($vac) {
    echo "\n  ⚠ **صفرٌ من مفردةٍ لا وجودَ لها — أخضرُ كاذبٌ لا إغلاق**:\n";
    foreach ($vac as $v) { printf("     · %-8s %s\n", $v[0], $v[1]); }
    echo "  ⛔ ويُرسَّب المصنِّفُ — **فمقياسٌ بمفردةٍ ميتةٍ لا يُصفَّر بل يُصحَّح**.\n";
    exit(1);
}
echo "  ✔ **ولا صفرَ من مفردةٍ ميتة** — كلُّ مفردةٍ في كلِّ مقياسٍ حيّةٌ في عمودِها\n";
