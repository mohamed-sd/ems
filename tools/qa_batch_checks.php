<?php
/**
 * tools/qa_batch_checks.php — حزامُ الدفعة الآمنة (QA-BATCH) · v1
 * ═══════════════════════════════════════════════════════════════════════════
 * خمسةُ فحوصٍ تحرس ما أُصلح في 2026-08-06 بعد جولة مدير المبيعات:
 *
 *   ①  AC-QA-01 · لا ملفَ يُضمّن config.php بـ include عارٍ **بعد** insidebar —
 *       فـ insidebar حمّله بـ require_once، والتكرارُ يُعيد تعريفَ الدوال
 *       ويُسقط الصفحة بـ«Cannot redeclare».                            [ح-04]
 *
 *   ②  AC-QA-02 · لا سمةَ pattern فيها شرطةٌ غير مهرَّبةٍ داخل فئةٍ محرفية.
 *       المتصفحاتُ تُصرّف pattern بعلم v، و«-» عاريةٌ تُبطل النمطَ كلَّه
 *       صامتًا فيُقبل كلُّ شيء. الصالحُ: \- مهرَّبة.                    [ح-05]
 *
 *   ③  AC-QA-03 · كلُّ حقلٍ بسمة pattern له نظيرٌ خادميٌّ يتحقق — فالواجهةُ
 *       وحدَها لا تحرس.                                                [ح-05]
 *
 *   ④  AC-QA-04 · حرّاسُ الفرص الخادميون قائمون: مالٌ غيرُ سالبٍ وإقفالٌ
 *       بسبب.                                                          [ح-10]
 *
 *   ⑤  AC-QA-05 · لا رابطَ حيٍّ في القائمة الجانبية إلى مركز التقارير لدورٍ
 *       بلا صفٍّ في report_role_permissions — الرابطُ الذي لا يفتح عطل. [ح-11]
 *
 * php tools/qa_batch_checks.php [--verbose]
 * الخروج: 0 نظيف · 1 خرقٌ قائم.
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

$VERBOSE = in_array('--verbose', $argv, true);
$o = function ($s) { fwrite(STDOUT, $s . "\n"); };
$ROOT = str_replace('\\', '/', dirname(__DIR__));
$SKIP = array('/node_modules/', '/vendor/', '/.git/', '/.claude/', '/database/backups/', '/storage/', '/tests/');

/** كلُّ ملفات php في المستودع عدا المستثنى. */
$phpFiles = function () use ($ROOT, $SKIP) {
    $out = array();
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $p = str_replace('\\', '/', $f->getPathname());
        if (substr($p, -4) !== '.php') { continue; }
        foreach ($SKIP as $s) { if (strpos($p, $s) !== false) { continue 2; } }
        $out[] = $p;
    }
    return $out;
};
$rel = function ($p) use ($ROOT) { return substr($p, strlen($ROOT) + 1); };

$o('══ حزامُ الدفعة الآمنة QA-BATCH ══');
$fail = 0;

// ─────────────────────────────────────────────────────────────────────────────
// ① config.php بـ include عارٍ بعد insidebar  ⇐  Fatal: Cannot redeclare
// ─────────────────────────────────────────────────────────────────────────────
$v1 = array();
foreach ($phpFiles() as $p) {
    $src = file_get_contents($p);
    if (strpos($src, 'insidebar.php') === false) { continue; }
    $lines = explode("\n", $src);
    $ins = null;
    foreach ($lines as $i => $ln) {
        if ($ins === null && strpos($ln, 'insidebar.php') !== false
            && preg_match('/\b(include|require)(_once)?\b/', $ln)) { $ins = $i + 1; }
        if ($ins !== null && preg_match('/^\s*(include|require)\s*\(?\s*[\'"][^\'"]*config\.php/', $ln)
            && ($i + 1) > $ins) {
            $v1[] = $rel($p) . ':' . ($i + 1);
        }
    }
}
if (count($v1)) { $fail++; }
$o('  ① AC-QA-01 · config مكرَّرٌ بعد insidebar ' . (count($v1) ? '✗ ' . count($v1) . ' خرقًا' : '✓ نظيف'));
foreach ($v1 as $x) { $o('        · ' . $x); }

// ─────────────────────────────────────────────────────────────────────────────
// ② سمةُ pattern بشرطةٍ غير مهرَّبةٍ داخل فئة  ⇐  النمطُ يسقط صامتًا تحت v
// ─────────────────────────────────────────────────────────────────────────────
$v2 = array();
foreach ($phpFiles() as $p) {
    $src = file_get_contents($p);
    if (strpos($src, 'pattern=') === false) { continue; }
    if (!preg_match_all('/pattern\s*=\s*"([^"]*)"/', $src, $m)) { continue; }
    foreach ($m[1] as $pat) {
        // كلُّ فئةٍ [...] داخل النمط: شرطةٌ غيرُ مهرَّبةٍ وليست طرفًا لمدًى؟
        if (!preg_match_all('/\[(?:[^\]\\\\]|\\\\.)*\]/', $pat, $cls)) { continue; }
        foreach ($cls[0] as $c) {
            $body = substr($c, 1, -1);
            // انزع المهرَّبات ثم المديات a-z / 0-9 — ما بقي من «-» فهو حرفٌ عارٍ
            $t = preg_replace('/\\\\./', '', $body);
            $t = preg_replace('/[A-Za-z0-9]-[A-Za-z0-9]/', '', $t);
            if (strpos($t, '-') !== false) { $v2[] = $rel($p) . '  ' . $pat; }
        }
    }
}
$v2 = array_values(array_unique($v2));
if (count($v2)) { $fail++; }
$o('  ② AC-QA-02 · شرطةٌ عاريةٌ في pattern     ' . (count($v2) ? '✗ ' . count($v2) . ' خرقًا' : '✓ نظيف'));
foreach ($v2 as $x) { $o('        · ' . $x); }

// ─────────────────────────────────────────────────────────────────────────────
// ③ سمةُ pattern بلا نظيرٍ خادمي
// ─────────────────────────────────────────────────────────────────────────────
$v3 = array();
foreach ($phpFiles() as $p) {
    $src = file_get_contents($p);
    if (!preg_match_all('/name\s*=\s*"([a-z_]*code)"[^>]*pattern\s*=/i', $src, $m)) { continue; }
    foreach (array_unique($m[1]) as $field) {
        // نظيرٌ خادمي: preg_match على فئةٍ محرفيةٍ في الملف نفسِه
        if (!preg_match('/preg_match\s*\(\s*[\'"]\S*\[A-Za-z0-9/', $src)) {
            $v3[] = $rel($p) . '  (' . $field . ')';
        }
    }
}
$v3 = array_values(array_unique($v3));
if (count($v3)) { $fail++; }
$o('  ③ AC-QA-03 · pattern بلا حارسٍ خادمي   ' . (count($v3) ? '✗ ' . count($v3) . ' خرقًا' : '✓ نظيف'));
foreach ($v3 as $x) { $o('        · ' . $x); }

// ─────────────────────────────────────────────────────────────────────────────
// ④ حرّاسُ الفرص الخادميون
// ─────────────────────────────────────────────────────────────────────────────
$v4 = array();
$opp = $ROOT . '/Opportunities/opportunities.php';
if (!file_exists($opp)) {
    $v4[] = 'Opportunities/opportunities.php مفقود';
} else {
    $s = file_get_contents($opp);
    $need = array(
        'حارسُ الإيراد السالب'   => '/\$expected_revenue_raw\s*<\s*0/',
        'حارسُ التمويل السالب'  => '/\$funding_needed_raw\s*<\s*0/',
        'حارسُ سبب الفوز'        => '/\$stage_raw\s*===\s*\'فوز\'\s*&&\s*\$win_reason_raw\s*===\s*\'\'/u',
        'حارسُ سبب الخسارة'    => '/\$lost_reason_raw\s*===\s*\'\'/u',
    );
    foreach ($need as $label => $re) {
        if (!preg_match($re, $s)) { $v4[] = $label . ' — غائب'; }
    }
}
if (count($v4)) { $fail++; }
$o('  ④ AC-QA-04 · حرّاسُ الفرص الخادميون    ' . (count($v4) ? '✗ ' . count($v4) . ' خرقًا' : '✓ نظيف'));
foreach ($v4 as $x) { $o('        · ' . $x); }

// ─────────────────────────────────────────────────────────────────────────────
// ⑤ روابطُ قائمةٍ حيّةٌ إلى مركزِ تقاريرَ لا يفتح
// ─────────────────────────────────────────────────────────────────────────────
$v5 = array();
$q = @mysqli_query($conn, "SELECT n.role_id, n.label_ar
        FROM nav_items n
        WHERE n.active = 1 AND n.route LIKE '%emsreports%'
          AND NOT EXISTS (SELECT 1 FROM report_role_permissions p WHERE p.role_id = n.role_id)
        ORDER BY n.role_id");
if ($q) { while ($r = mysqli_fetch_assoc($q)) { $v5[] = 'دور ' . $r['role_id'] . ' — ' . $r['label_ar']; } }
if (count($v5)) { $fail++; }
$o('  ⑤ AC-QA-05 · رابطُ تقاريرَ لا يفتح      ' . (count($v5) ? '✗ ' . count($v5) . ' دورًا' : '✓ نظيف'));
if ($VERBOSE) { foreach ($v5 as $x) { $o('        · ' . $x); } }
elseif (count($v5)) { $o('        (أعِد التشغيل بـ --verbose للقائمة · قرارُ المالك: امنح الصلاحية أو أخفِ الرابط)'); }

// ─────────────────────────────────────────────────────────────────────────────
// ⑥ واقعةُ إيرادٍ جديدةٌ بلا عقد — حارسُ انحدارٍ للكاتب (المروحة)
//    التاريخيُّ (بذرُ يوليو + FANOUT_TEST) مستثنًى بخطِّ أساسٍ معلَن: الكاتبُ
//    صحّ في 2026-07-28 (3 موصولة · 0 يتيمة)، فما جاء بعده يتيمًا انحدار. [ح-08]
// ─────────────────────────────────────────────────────────────────────────────
$QA08_BASELINE = '2026-07-31';
$v6 = array();
$q = @mysqli_query($conn, "SELECT id, source_ref, amount, currency, DATE(created_at) d
        FROM fin_financial_events
       WHERE event_type = 'revenue'
         AND (contract_id IS NULL OR contract_id = 0)
         AND source_ref NOT LIKE 'FANOUT\\_TEST%'
         AND created_at >= '$QA08_BASELINE'
       ORDER BY id");
if ($q) { while ($r = mysqli_fetch_assoc($q)) {
    $v6[] = '#' . $r['id'] . ' ' . $r['source_ref'] . ' ' . $r['amount'] . ' ' . $r['currency'] . ' · ' . $r['d'];
} }
if (count($v6)) { $fail++; }
$o('  ⑥ AC-QA-06 · واقعةُ إيرادٍ جديدةٌ بلا عقد ' . (count($v6) ? '✗ ' . count($v6) . ' واقعة' : '✓ نظيف'));
foreach ($v6 as $x) { $o('        · ' . $x); }
if ($VERBOSE) {
    $h = @mysqli_query($conn, "SELECT COUNT(*) c, COALESCE(SUM(amount),0) s FROM fin_financial_events
            WHERE event_type='revenue' AND (contract_id IS NULL OR contract_id=0) AND created_at < '$QA08_BASELINE'");
    if ($h && ($hr = mysqli_fetch_assoc($h))) {
        $o('        (تاريخيٌّ مستثنًى قبل ' . $QA08_BASELINE . ': ' . $hr['c'] . ' واقعة · ' . $hr['s'] . ')');
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// ⑦ شاشةُ مبيعاتٍ بلا مخرجٍ إلى Excel — الكيانُ مسجَّلٌ والأزرارُ موصولة. [ح-09]
// ─────────────────────────────────────────────────────────────────────────────
$QA09 = array(
    'Opportunities/opportunities.php' => 'opportunities',
    'Clients/quotations.php'          => 'quotations',
    'Clients/tenders.php'             => 'tenders',
    'Clients/products.php'            => 'products',
    'Clients/pricelists.php'          => 'pricelists',
    'Clients/commercial_risks.php'    => 'commercial_risks',
    'Clients/activities.php'          => 'activities',
);
$v7 = array();
$regSrc = @file_get_contents($ROOT . '/app/Services/Excel/ExcelRegistry.php');
foreach ($QA09 as $file => $key) {
    $p = $ROOT . '/' . $file;
    if (!file_exists($p)) { $v7[] = $file . ' — الملف مفقود'; continue; }
    $s = file_get_contents($p);
    if (strpos($s, "ems_excel_header_actions('$key'") === false) { $v7[] = $file . ' — أزرارٌ غير موصولة'; }
    elseif (strpos($s, 'ems_excel_render') === false)            { $v7[] = $file . ' — نافذةُ الاستيراد غائبة'; }
    elseif ($regSrc !== false && strpos($regSrc, "\$defs['$key']") === false) { $v7[] = $key . ' — غيرُ مسجَّلٍ في ExcelRegistry'; }
}
if (count($v7)) { $fail++; }
$o('  ⑦ AC-QA-07 · مخرجُ Excel لشاشات المبيعات ' . (count($v7) ? '✗ ' . count($v7) . ' خرقًا' : '✓ نظيف'));
foreach ($v7 as $x) { $o('        · ' . $x); }

// ─────────────────────────────────────────────────────────────────────────────
// ⑧ حلُّ المسار إلى موديول حتميٌّ ويُفضّل موديولَ الدور — لا «أدنى id». [ح-16]
//    الشاشةُ المشتركة لها صفٌّ لكلِّ مالك؛ فإن وُجد صفٌّ يملكه دورُ الجلسة وجب
//    أن يُحلَّ إليه، وإلا قِيست صلاحيتُه على موديولِ دورٍ آخر.
// ─────────────────────────────────────────────────────────────────────────────
$v8 = array();
require_once $ROOT . '/includes/permissions_helper.php';
$shared = array();
$q = @mysqli_query($conn, "SELECT code, COUNT(*) c FROM modules GROUP BY code HAVING c > 1");
if ($q) { while ($r = mysqli_fetch_assoc($q)) { $shared[] = $r['code']; } }
$savedSession = isset($_SESSION['user']) ? $_SESSION['user'] : null;
foreach ($shared as $code) {
    $owners = array();
    $q2 = @mysqli_query($conn, "SELECT id, owner_role_id FROM modules WHERE code = '"
        . mysqli_real_escape_string($conn, $code) . "' AND owner_role_id IS NOT NULL AND owner_role_id > 0");
    if ($q2) { while ($r = mysqli_fetch_assoc($q2)) { $owners[(int) $r['owner_role_id']] = (int) $r['id']; } }
    foreach ($owners as $rid => $mid) {
        $_SESSION['user'] = array('role' => $rid, 'company_id' => 4, 'id' => 0);
        $got = get_module_id_by_script_path($conn, '/ems/' . $code);
        if ((int) $got !== $mid) { $v8[] = $code . ' · دور ' . $rid . ' → ' . var_export($got, true) . ' (المتوقع ' . $mid . ')'; }
    }
}
if ($savedSession !== null) { $_SESSION['user'] = $savedSession; } else { unset($_SESSION['user']); }
if (count($v8)) { $fail++; }
$o('  ⑧ AC-QA-08 · حلُّ المسار يُفضّل موديولَ الدور ' . (count($v8) ? '✗ ' . count($v8) . ' خرقًا' : '✓ نظيف'));
foreach (array_slice($v8, 0, 8) as $x) { $o('        · ' . $x); }

$o('');
$o($fail === 0 ? 'النتيجة: 8/8 نظيف.' : 'النتيجة: ' . (8 - $fail) . '/8 — ' . $fail . ' فحصًا مكسورًا.');
exit($fail === 0 ? 0 : 1);
