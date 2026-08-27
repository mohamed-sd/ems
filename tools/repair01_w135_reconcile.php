<?php
/**
 * tools/repair01_w135_reconcile.php — مصالحةُ الملكيّةِ والتصنيف (W13.5)
 * ═══════════════════════════════════════════════════════════════════════════
 * **أمرُ المالك · البند 3**: «أعِدْ تشغيلَ `Ownership Reconciliation` بحيث تصبح
 * نتيجةُ كلِّ سطحٍ واحدةً من تسعة … ولا يُسمح ببقاءِ نفسِ الحقيقةِ `Source` في
 * إدارتَين.» **والبنود 4–8** تحسم التقاطعاتِ الخمسةَ بقوائمَ صريحة.
 *
 * ◆ **كلُّ حكمٍ بقاعدتِه** — و`chk_w135_why` في القاعدةِ يردُّ حكمًا بلا قاعدة.
 *   فلا يُملأ العمودُ بالجملةِ ثمَّ يُقال «صُنِّف».
 *
 * ◆ **والترتيبُ يحسم**: القواعدُ تُجرَّب من الأضيقِ إلى الأعمّ، وأوّلُ ما يطابق
 *   يحكم. ⛔ ولو عُكس الترتيبُ لابتلع `LEGACY` العامُّ ما هو `AUDIT_ASSURANCE`
 *   خاصّ — وهو عطبُ «الشقِّ يُحسَم بترتيبِ الصفوف» الذي وقع في W10.
 *
 * ◆ **و`UNKNOWN` حكمٌ صريحٌ لا فراغ**: ما لا تحسمه قاعدةٌ **يُعلَن مجهولًا
 *   ويُرفع للمالك** — فحاجبٌ يعدُّ المجهولَ يرى ما لا يراه الفراغ. ⛔ ولا
 *   يُخترَع له حكمٌ ليخضرَّ العدّاد.
 *
 * ◆ **والتقاطعُ مفرداتُه من نصِّ الأمرِ لا من اجتهاد**: قوائمُ البنود 4–8
 *   مكتوبةٌ أدناه كما كتبها المالك، وكلُّ مفردةٍ تُقيَّد في `verdict_rule`
 *   فيُراجَع الحكمُ ويُصحَّح بمفردةٍ لا بإعادةِ بناء.
 *
 * التشغيل: php tools/repair01_w135_reconcile.php [--report]
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
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$REPORT = in_array('--report', $argv, true);
$e = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };

/* ═══ ① التقاطعاتُ الخمسةُ — بمفرداتِ الأمرِ حرفًا ═══════════════════════════
     مفتاحُ كلِّ عنصر: الإدارةُ التي **لا تملك** الحقيقة · ومفرداتٌ إن ظهرت في
     مسارِ السطحِ أو مسمّاه فهو **إسقاطٌ** لا مصدر · واسمُ المالكِ الحقيقيّ. */
$CROSS = array(
    array('dept' => 'DEP-13', 'owner' => 'DEP-07', 'why' => 'البند 4 — الموارد البشرية تملك التوظيف والمسير والاجازات وبيانات الموظف',
          'kw' => array('rec_', 'payroll', 'leave', 'employee', 'hr_', 'contract_emp', 'benefit')),
    array('dept' => 'DEP-16', 'owner' => 'DEP-17', 'why' => 'البند 5 — المخازن تملك الرصيد والصرف والاستلام والجرد',
          'kw' => array('stock', 'wh_', 'warehouse', 'issue', 'receipt', 'lot', 'serial', 'bin', 'count', 'custody')),
    array('dept' => 'DEP-03', 'owner' => 'DEP-06', 'why' => 'البند 6 — الخزينة تملك التنفيذ النقدي الفعلي',
          'kw' => array('payment_order', 'payment_alloc', 'tre_', 'bank', 'cash')),
    array('dept' => 'DEP-04', 'owner' => 'DEP-05', 'why' => 'البند 7 — المالية تملك سياسة الاهلاك وحسابه واثره',
          'kw' => array('depr', 'carrying', 'fin_assets')),
    array('dept' => '*',      'owner' => 'DEP-09', 'why' => 'البند 8 — ادارة المخاطر مصدر الحقيقة وما في الادارات اسقاط',
          'kw' => array('risk_dept', 'risk_mnt', 'risk_site', 'risk_ops')),
    array('dept' => '*',      'owner' => 'DEP-08', 'why' => 'البند 8 — الحوكمة والالتزام مصدر الحقيقة وما في الادارات اسقاط',
          'kw' => array('gov_dept', 'gov_mnt', 'gov_site', 'gov_trp')),
);

/* ═══ ② القراءة ═══════════════════════════════════════════════════════════ */
$rows = array();
$r = $conn->query("SELECT screen_id, screen_file, route, owner_code, on_disk, guard_kind,
                          lifecycle, ghost_verdict, origin
                     FROM repair01_screen_registry ORDER BY screen_id");
while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }

/* التبويباتُ المُعلَنةُ في دفاترِ الموجات — تُقرأ ولا تُفترَض */
$TAB = array();
foreach (array('w3','w4','w5','w7','w8','w9','w10','w11','w12','w13') as $w) {
    $q = @$conn->query("SELECT route FROM `repair01_{$w}_sidebar`
                         WHERE s5_verdict IN ('TAB_IN_PARENT','ALREADY_TAB')");
    while ($q && ($x = $q->fetch_row())) { $TAB[strtolower(basename($x[0]))] = $w; }
}
$q = $conn->query("SELECT route FROM gov_nav_hidden_log WHERE reachable = 'TAB_IN_PARENT'");
while ($q && ($x = $q->fetch_row())) { $TAB[strtolower(basename($x[0]))] = 'nav'; }

/* ═══ ③ الاشتقاقُ — من الأضيقِ إلى الأعمّ ═══════════════════════════════════ */
$decide = function ($s) use ($CROSS, $TAB) {
    $base  = strtolower(basename((string) $s['screen_file']));
    $route = strtolower((string) $s['route'] . ' ' . $s['screen_file']);
    $own   = trim((string) $s['owner_code']);

    /* ⓐ شبحٌ نُقل إلى دفترِ الفجوات — لا سطحَ له على القرص */
    if ((string) $s['ghost_verdict'] === 'MOVED_TO_TARGET_GAPS') {
        return array('RETIRE', '', 'شبح منقول الى دفتر الفجوات — لا ملف على القرص (W02)');
    }
    /* ⓑ تبويبٌ داخلَ أبيه — مُعلَنٌ في دفترِ موجتِه */
    if (isset($TAB[$base])) {
        return array('TAB_CHILD', 'PROJECTION', 'معلن تبويبا في شاشة ابيه — دفتر ' . $TAB[$base]);
    }
    /* ⓒ المراجعةُ الداخليةُ خطٌّ ثالثٌ مستقلّ (البندان 36 · 38) */
    if ($own === 'IAF') {
        return array('AUDIT_ASSURANCE', 'SOURCE', 'المراجعة الداخلية خط ثالث مستقل — البند 38');
    }
    /* ⓓ القيادةُ التنفيذيةُ ترى ولا تنفّذ (البند 27: الرؤية لا تساوي السلطة) */
    if ($own === 'EX-CEO' || $own === 'EX-DVP') {
        return array('EXECUTIVE_PROJECTION', 'PROJECTION', 'مساحة قيادة — الرؤية لا تساوي السلطة (البند 27)');
    }
    /* ⓔ مساحةُ عملي وما يشترك فيه الجميع */
    if ($own === 'WS-MY' || preg_match('~(^|/)(portal|chats|main|profile)/~', $route)) {
        return array('PLATFORM_SHARED', 'PROJECTION', 'مساحة شخصية او سطح منصة مشترك — خارج تسلسل الادارات');
    }
    /* ⓕ التقاطعاتُ الخمسةُ — إسقاطٌ بمفردةٍ من نصِّ الأمر */
    foreach ($CROSS as $c) {
        if ($c['dept'] !== '*' && $own !== $c['dept']) { continue; }
        if ($c['dept'] === '*' && ($own === $c['owner'] || $own === '')) { continue; }
        foreach ($c['kw'] as $k) {
            if (mb_strpos($route, $k) !== false) {
                return array('DOMAIN_PROJECTION', 'PROJECTION',
                             $c['why'] . ' — مفردة «' . $k . '» ومالك الحقيقة ' . $c['owner']);
            }
        }
    }
    /* ⓖ سطحٌ حيٌّ بإدارةٍ مالكةٍ وحارسٍ — مصدرُ نطاقِه */
    if (strpos($own, 'DEP-') === 0 && (int) $s['on_disk'] === 1 && trim((string) $s['guard_kind']) !== '') {
        return array('DOMAIN_SOURCE', 'SOURCE', 'سطح حي بادارة مالكة وحارس خادمي — مصدر نطاقه');
    }
    /* ⓗ حيٌّ بلا مالكٍ أو بلا حارس — دَينٌ معدودٌ لا مجهول */
    if ((int) $s['on_disk'] === 1) {
        return array('LEGACY', '', 'حي على القرص وينقصه مالك او حارس — دين معدود (البند 49)');
    }
    /* ⓘ ما بقي يُعلَن مجهولًا ⛔ ولا يُخترَع له حكم */
    return array('UNKNOWN', '', 'لم تحسمه قاعدة — يرفع للمالك (البند 18: لا سطح يتيم)');
};

/* ═══ ④ التطبيق ═══════════════════════════════════════════════════════════ */
$tally = array(); $kind = array('SOURCE' => 0, 'PROJECTION' => 0, '' => 0);
$wrote = 0; $unknown = array();
foreach ($rows as $s) {
    list($v, $k, $why) = $decide($s);
    $tally[$v] = isset($tally[$v]) ? $tally[$v] + 1 : 1;
    $kind[$k]  = isset($kind[$k]) ? $kind[$k] + 1 : 1;
    if ($v === 'UNKNOWN') { $unknown[] = $s['screen_file']; }
    if ($REPORT) { continue; }
    if ($conn->query("UPDATE repair01_screen_registry
                         SET ownership_verdict = '" . $e($v) . "',
                             surface_kind      = '" . $e($k) . "',
                             verdict_rule      = '" . $e($why) . "',
                             verdict_at        = NOW()
                       WHERE screen_id = '" . $e($s['screen_id']) . "'")) { $wrote++; }
}

/* ═══ ⑤ التقرير ═══════════════════════════════════════════════════════════ */
echo "\n═══ مصالحةُ الملكيّةِ — W13.5 · البند 3 ═══\n";
echo ($REPORT ? "  وضعُ التقرير: يقرأ ولا يكتب\n" : "  كُتب: $wrote سطحًا\n");
printf("  المقام: %d سطحًا\n\n", count($rows));
$ORDER = array('DOMAIN_SOURCE','DOMAIN_PROJECTION','PLATFORM_SHARED','EXECUTIVE_PROJECTION',
               'AUDIT_ASSURANCE','TAB_CHILD','LEGACY','RETIRE','UNKNOWN');
foreach ($ORDER as $v) {
    printf("  %-22s %4d\n", $v, isset($tally[$v]) ? $tally[$v] : 0);
}
printf("\n  التصنيف: SOURCE=%d · PROJECTION=%d · بلا تصنيف=%d\n",
    $kind['SOURCE'], $kind['PROJECTION'], $kind['']);

/* ⛔ **والقياسُ الحاكمُ في البند 3**: لا نفسَ الحقيقةِ `SOURCE` في إدارتَين. */
$dup = 0;
if (!$REPORT) {
    $q = $conn->query("SELECT COUNT(*) FROM (
        SELECT LOWER(COALESCE(NULLIF(route,''), screen_file)) f FROM repair01_screen_registry
         WHERE surface_kind = 'SOURCE' GROUP BY f HAVING COUNT(DISTINCT owner_code) > 1) z");
    $dup = $q ? (int) $q->fetch_row()[0] : -1;
    printf("  حقيقةٌ SOURCE في إدارتَين: %d (يجب 0)\n", $dup);
}
if ($unknown) {
    printf("\n  ◆ مجهولٌ يُرفع للمالك: %d\n", count($unknown));
    foreach (array_slice($unknown, 0, 8) as $u) { echo "     · $u\n"; }
    if (count($unknown) > 8) { printf("     … و%d غيرُها\n", count($unknown) - 8); }
}
echo "\n────────────────────────────────────────────────────────────\n";
echo "الخطوةُ التالية: php tools/repair01_w135_scan.php\n";
exit(($dup === 0 || $REPORT) ? 0 : 1);
