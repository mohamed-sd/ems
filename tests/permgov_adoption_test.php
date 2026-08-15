<?php
/**
 * tests/permgov_adoption_test.php — حرّاسٌ بُنيت فتُبنَّيت
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0025 · INJ-0104 · INJ-0177 · INJ-0183 · INJ-0202 ·
 *                  INJ-0225 · INJ-0249 · INJ-0368
 *
 * أربعُ علَلٍ من عائلةِ الصلاحياتِ والحوكمة، جذرُها واحد: **آليةٌ مبنيةٌ لا
 * تُنادى**. والفحصُ يقيس الأثرَ لا وجودَ الملف.
 *
 *  ① **ملفُّ الجدولةِ يُشغَّل من المتصفّح** — وشِبهُ الحارسِ يخدع:
 *       if (!defined('EMS_CLI')) { define('EMS_CLI', true); }
 *     فالملفُّ يُعرّف الشرطَ الذي يحرسه فيصدق دائمًا. **صفرٌ من ١٧ ملفًّا محروس.**
 *  ② **رقمٌ ماليٌّ بلا مستندِ مصدر** — قيدٌ يدويٌّ بلا حدثٍ، وبندُ إقفالٍ يُنجَز
 *     بنقرةِ رابطٍ بلا دليل.
 *  ③ **سطرُ اطّلاعٍ يُكتب ولا يُحجَب** — فالأثرُ يقع والقيمةُ تعبر لمن لا يملكها.
 *     والسجلُّ بلا حجبٍ يوثّق التسريبَ ولا يمنعه.
 *  ④ **المحجوبُ مخفيٌّ لا غائب** — عمودٌ بصنفِ `none` يُقرأ بـ«عرضِ المصدر».
 *
 * ◆ والقياسُ حيٌّ حيث يلزم: `CURLOPT_FOLLOWLOCATION` **مطفأٌ** عند قياسِ الردّ،
 *   وإلا صار ٤٠٣ يبدو ٢٠٠.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$conn = $GLOBALS['conn'];
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$say('══ حرّاسٌ بُنيت فتُبنَّيت');

/* ── ① حارسُ الجدولة ─────────────────────────────────────────────────────── */
$crons = array();
foreach (glob($ROOT . '/*/cron_*.php') as $f) {
    $b = str_replace('\\', '/', $f);
    if (strpos($b, '/includes/') !== false) { continue; }
    $crons[] = $b;
}
$noGuard = array();
foreach ($crons as $f) {
    if (strpos((string) @file_get_contents($f), 'ems_cron_guard(') === false) { $noGuard[] = basename($f); }
}
$ok(count($crons) >= 15, 'ملفاتُ الجدولةِ تُقاس (' . count($crons) . ')');
$ok(empty($noGuard), '**وكلُّها تحمل `ems_cron_guard()`** — لا ملفَّ جدولةٍ بلا حارس',
    implode(' · ', array_slice($noGuard, 0, 4)));
$cg = (string) @file_get_contents($ROOT . '/includes/cron_guard.php');
$ok(strpos($cg, "php_sapi_name() === 'cli'") !== false && strpos($cg, 'hash_equals') !== false,
    'والحارسُ يميّز سطرَ الأوامرِ ويقارن الرمزَ بـ`hash_equals` — لا بـ`===`');
$ok(strpos($cg, 'GOV-CRON-403') !== false,
    'والرفضُ **برمزٍ محكومٍ** يُسجَّل — فالمحاولةُ من المتصفّحِ خبرٌ أمنيٌّ لا صمت');

/* والقياسُ الحيّ: المتصفّحُ يُردُّ */
$hit = function ($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 60));
    $b = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array('code' => $code, 'body' => $b);
};
$r = $hit('http://localhost/ems/Operations/cron_wfm_engine.php');
$ok($r['code'] === 403, '**ومحرّكُ العملِ يُردُّ ٤٠٣ من المتصفّح** (' . $r['code'] . ')');
$ok(mb_strpos($r['body'], 'GOV-CRON-403') !== false, 'وبرمزِه في الجسم — لا صفحةً بيضاء');
$r2 = $hit('http://localhost/ems/Governance/cron_permissions.php');
$ok($r2['code'] === 403, 'وكذلك كانسُ الصلاحيات (' . $r2['code'] . ')');

/* ── ② حارسُ مستندِ المصدر ───────────────────────────────────────────────── */
require_once $ROOT . '/includes/source_doc_guard.php';
$reg = ems_source_doc_registry();
$ok(count($reg) >= 3, 'سجلُّ المصادرِ مبنيٌّ (' . count($reg) . ' نوعًا)');
$bad = ems_require_source_doc($conn, 4, 'journal_entry', array('event_id' => 0), 0, 'test');
$ok(empty($bad['ok']) && (int) $bad['code'] === 422,
    '**وقيدٌ بلا حدثٍ يُردُّ ٤٢٢** — لا رقمَ ماليًّا من العدم');
$ok(mb_strpos($bad['reason'], 'FIN-SRC-422') !== false && mb_strpos($bad['reason'], 'حدثٌ') !== false,
    'والسببُ **يسمّي المطلوبَ** لا يكتفي بالرفض');
$ev = 0;
$q = $conn->query('SELECT id FROM fin_financial_events WHERE company_id = 4 ORDER BY id DESC LIMIT 1');
if ($q && ($x = $q->fetch_row())) { $ev = (int) $x[0]; }
$good = ems_require_source_doc($conn, 4, 'journal_entry', array('event_id' => $ev), 0, 'test');
$ok($ev > 0 && !empty($good['ok']), "وقيدٌ بحدثٍ قائمٍ (#{$ev}) يمرُّ — فالحارسُ يمنع ولا يشلّ");
$ghost = ems_require_source_doc($conn, 4, 'journal_entry', array('event_id' => 99999999), 0, 'test');
$ok(empty($ghost['ok']),
    '**ومفتاحٌ لا يقابله مستندٌ يُردُّ** — فالرقمُ لا يكفي، بل المستندُ يُحَلُّ من القاعدة');
$clo = ems_require_source_doc($conn, 4, 'closing_item', array('note' => ''), 0, 'test');
$ok(empty($clo['ok']) && (int) $clo['code'] === 422, 'وبندُ إقفالٍ بلا مرجعِ دليلٍ يُردُّ ٤٢٢');
$clo2 = ems_require_source_doc($conn, 4, 'closing_item', array('note' => 'محضر 12/2026'), 0, 'test');
$ok(!empty($clo2['ok']), 'وبمرجعِ دليلٍ يمرُّ');
$unk = ems_require_source_doc($conn, 4, 'not_declared_kind', array(), 0, 'test');
$ok(empty($unk['ok']), '**ونوعٌ غيرُ مُعلَنٍ يُرفض ولا يُخمَّن** — فالسكوتُ يفتح بابًا لا يُغلق');

$jf = (string) @file_get_contents($ROOT . '/Finance/journal_form_fin.php');
/* ◆ **والنداءُ يُقاس بمُرجَعِه لا باسمِه**: أوّلُ صياغةٍ بحثت عن السلسلة
     `ems_require_source_doc` فمرَّت على `..._DISABLED` في الاختبارِ السلبيّ —
     فحصٌ لا يرسب عند إفسادِ مفحوصِه. فالمقياسُ الآن **الإسنادُ الذي يُقرأ**. */
$ok(preg_match('~\$__src\s*=\s*ems_require_source_doc\(~', $jf) === 1
    && preg_match('~if\s*\(!\$__src\[\'ok\'\]\)~', $jf) === 1,
    'وشاشةُ القيدِ تنادي الحارس **وتقرأ مُرجَعَه فترفض**');
$ok(strpos($jf, "ems_require_source_doc") < strpos($jf, "fin_gen_code(\$conn, 'fin_journal_entries'"),
    '**والفحصُ قبل توليدِ الرقمِ المسلسل** — فلا فجوةَ في الدفترِ لقيدٍ رُفض');
$pf = (string) @file_get_contents($ROOT . '/Finance/periods_fin.php');
$ok(strpos($pf, "REQUEST_METHOD'] === 'POST' && isset(\$_POST['done_item'])") !== false,
    'وإنجازُ بندِ الإقفالِ صار **POST** — لا يقع بنقرةِ رابط');
$ok(strpos($pf, 'evidence_ref') !== false && strpos($pf, 'مَن أنجزه ومتى') !== false,
    'ويعرض دليلَه ومن أنجزه ومتى');

/* ── ③ الحجبُ في الخادم ──────────────────────────────────────────────────── */
$pr = (string) @file_get_contents($ROOT . '/Workforce/payroll_runs.php');
$ok(strpos($pr, "ems_may_see_field(\$conn, 'payroll.amount'") !== false,
    'ومسيّرُ الرواتبِ يستشير حاكمَ الظهورِ قبل طباعةِ مبلغ');
$rawMoney = preg_match_all('~number_format\(\(float\)\s*\$(?:rr|row|slip|l|d)\[~', $pr);
$ok($rawMoney === 0, "**وصفرُ مبلغٍ يُطبع خارجَ الحاجب** ({$rawMoney})");
$ok(substr_count($pr, '$__money(') >= 10,
    'و' . substr_count($pr, '$__money(') . ' موضعَ مبلغٍ يمرُّ بالحاجب');
/* ◆ **والحاجبُ يُقاس بفعلِه**: وجودُ النداءِ لا يكفي — لا بدَّ أن يردَّ
     «محجوب» لمن لا يملك. ونزعُ هذا السطرِ وحدَه كان يمرُّ في أوّلِ صياغة. */
$ok(preg_match('~if\s*\(!\$__maySeePay\)\s*\{\s*return\s+\'محجوب\'~u', $pr) === 1,
    '**والحاجبُ يردُّ «محجوب» فعلًا** لمن لا منحةَ له — لا يكتفي بوجودِ النداء');
$ip = (string) @file_get_contents($ROOT . '/Procurement/issue_proc.php');
$ok(strpos($ip, "ems_may_see_field(\$conn, 'issue.cost'") !== false, 'وشاشةُ الصرفِ تستشيره للتكلفة');
$ok(strpos($ip, '$__maySeeCost') !== false && strpos($ip, "\$cost = (\$line && \$__maySeeCost)") !== false,
    '**وحقلُ الإدخالِ نفسُه لا يحمل القيمةَ لمن لا يراها** — فالحجبُ في المصدرِ لا في العرض');
$els = array();
$q = $conn->query("SELECT element_code FROM portal_elements WHERE element_code IN ('payroll.amount','issue.cost','timesheet.rates')");
while ($q && ($x = $q->fetch_row())) { $els[] = $x[0]; }
$ok(count($els) === 3, 'والعناصرُ الثلاثةُ مسجَّلةٌ في قاموسِ الظهور (' . count($els) . '/3)');

/* ── ④ الشريحةُ تُنزع من المصدرِ لا تُخفى ────────────────────────────────── */
require_once $ROOT . '/includes/gov_columns.php';
$ok(function_exists('ems_gov_slice_filter'), 'ومرشِّحُ الشرائحِ مبنيّ');
$sample = '<tr><th class="ems-gov-th none" data-gov="entity" data-slice="1">الكيان</th>'
        . '<th class="ems-gov-th none" data-gov="idem_key" data-slice="2">مفتاح منع التكرار</th>'
        . '<th class="ems-gov-th none" data-gov="fx_rate" data-slice="3">سعر الصرف</th></tr>';
$_SESSION['user'] = array('id' => 0, 'role' => '29', 'company_id' => 4);   /* دورٌ بلا منفذٍ حاكم */
$filtered = ems_gov_slice_filter($sample);
$ok(mb_strpos($filtered, 'data-slice="1"') !== false,
    'والشريحةُ ① تبقى لكلِّ من يرى الشاشة — هويةُ المستندِ ليست سرًّا');
$ok(mb_strpos($filtered, 'data-slice="2"') === false && mb_strpos($filtered, 'data-slice="3"') === false,
    '**والشريحتان ②③ تُنزعان من المصدرِ** لمن لا منفذَ حاكمَ له');
$ok(mb_strpos($filtered, 'مفتاح منع التكرار') === false,
    'ونصُّ العمودِ يذهب معه — فلا يبقى أثرٌ يُقرأ بـ«عرضِ المصدر»');

/* ── ⑤ لا دورَ نشطٌ بلا منحةِ قراءةٍ ولا صفِّ تنقّل (INJ-0202) ────────────── */
$q = $conn->query(
    "SELECT COUNT(*) FROM roles r
      WHERE r.status = 1
        AND (NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.can_view = 1)
          OR NOT EXISTS (SELECT 1 FROM nav_items n WHERE n.role_id = r.id AND n.active = 1))");
$exc = ($q && ($x = $q->fetch_row())) ? (int) $x[0] : -1;
$ok($exc === 0, "**وكلُّ دورٍ نشطٍ له منحةُ قراءةٍ وصفُّ تنقّلٍ فعّال** — استثناءات: {$exc}");

/* ── ⑥ مسلكُ الاستثناءِ يُحسب (INJ-0249) ─────────────────────────────────── */
$hasUsage = false;
$q = $conn->query("SHOW COLUMNS FROM exception_requests LIKE 'usage_count'");
if ($q && $q->fetch_row()) { $hasUsage = true; }
$ok($hasUsage, 'وعدّادُ استعمالِ الاستثناءِ عمودٌ قائم');
$sd = (string) @file_get_contents($ROOT . '/includes/source_doc_guard.php');
$ok(strpos($sd, 'ems_source_doc_use_exception') !== false
    && strpos($sd, 'INSERT INTO exception_usages') !== false,
    '**والاستثناءُ يمرُّ ويُحسب** — عدّادًا وصفَّ استعمالٍ بفاعلِه');
$ok(strpos($sd, 'valid_to   IS NULL OR valid_to   >= NOW()') !== false,
    'و**المدةُ تُقاس بساعةِ القاعدة** — فاستثناءٌ انقضى لا يمرُّ ولو بقيت حالتُه `Active`');

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
