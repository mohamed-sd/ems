<?php
/**
 * tools/rpr02_cycle_author.php — تأليفُ جسرِ دورةِ العمل (البند ⑫)
 * ═══════════════════════════════════════════════════════════════════════════
 * `FINAL_CLOSE` ⑫ · `RPR-02` #٧ · §٥·١٠: «كلُّ شاشةٍ ينطبق عليها سيرُ عملٍ
 * تحمل Screen ID → Workflow Stage صراحةً … وما لا ينطبق عليه يحمل
 * Applicability Reason بدل اختراعِ مرحلةٍ له».
 *
 * ◆ ثلاثةُ أفعالٍ — كلُّها بشاهدٍ وقاعدةٍ مسمّاة:
 *   `C5_AUTHORED`       سطحُ معاملةٍ بلا صفِّ دورةٍ ⇒ يُؤلَّف صفُّه **بمرحلةٍ
 *                       من مفرداتِ دورةِ إدارتِه القائمةِ** لا من خيال.
 *   `C5_NOT_APPLICABLE` سطحُ ضبطٍ أو قشرةٍ أو محوِّلٍ ⇒ صفُّ عدمِ انطباقٍ
 *                       بسببِه المكتوب (stage_kind='not_applicable').
 *   `C6_MANUAL_AMBIG`   الملتبسُ المعلَنُ (30 صفًّا على 4 أسماء) ⇒ يُحسم
 *                       **بالمُصيَّرِ لا بالمخزن**: التوأمُ الذي تعرضه الملاحةُ
 *                       فعلًا هو الشاشة (Settings/roles · Settings/modules ·
 *                       Finance/approvals_inbox المُصيَّرُ لـ23 دورًا)، و«طلباتي»
 *                       للسطحِ الحيِّ العامِّ لا للمحوِّل.
 *
 * التراجع: php tools/rpr02_cycle_author.php --rollback
 *          (يحذف C5_* المؤلَّفةَ ويُفرغ معرِّفَ C6_MANUAL_AMBIG)
 * التشغيل: php tools/rpr02_cycle_author.php [--apply|--rollback]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn']; mysqli_set_charset($conn, 'utf8mb4');
$e = function ($x) use ($conn) { return $conn->real_escape_string((string) $x); };
$APPLY = in_array('--apply', $argv, true);
$RB    = in_array('--rollback', $argv, true);
$SNAP = 'GIT-' . trim(shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --short HEAD'));

if ($RB) {
    $conn->query("DELETE FROM gov_screen_cycle WHERE bridge_rule IN ('C5_AUTHORED','C5_NOT_APPLICABLE')");
    echo "  ✔ حُذف المؤلَّف: " . $conn->affected_rows . "\n";
    $conn->query("UPDATE gov_screen_cycle SET screen_id='', bridge_rule='AMBIGUOUS_DECLARED',
                         bridge_witness='', bridge_snapshot='' WHERE bridge_rule='C6_MANUAL_AMBIG'");
    echo "  ✔ أُعيد الملتبسُ بلا معرِّف: " . $conn->affected_rows . "\n";
    exit(0);
}

/* ── ① حسمُ الملتبسِ — بالمُصيَّرِ لا بالمخزن ────────────────────────────── */
$AMB = array(
 'my_requests.php'     => array('SCR-0470', 'التوأمان: FinRequests/my_requests.php محول صرف (كيانه nav_redirects — مسار محول لا شاشة بحكم UXW) وPortal/my_requests.php هو سطح «طلباتي» الحي العام (كيانه requests) — فالمعرف له'),
 'roles.php'           => array('SCR-0561', 'التوأمان admin/permissions/roles.php وSettings/roles.php — والمصير في الملاحة الحية Settings/roles.php وحده (nav_items active) والحاكم «لا يقاس سطح بما في جدوله بل بما يظهر»'),
 'modules.php'         => array('SCR-0559', 'التوأمان admin/permissions/modules.php وSettings/modules.php — والمصير في الملاحة الحية Settings/modules.php وحده'),
 'approvals_inbox.php' => array('SCR-0108', 'التوأمان Finance/approvals_inbox.php وPortal/approvals_inbox.php — والمصير للادوار (23 دورا في nav_items) هو Finance/approvals_inbox.php: صندوق «ما ينتظر اعتمادي» المشترك الذي تشير اليه صفوف مراكز العمل'),
);

/* ── ② التأليف — [screen_id ⇒ [dept, layer, order, stage, kind, reason, witness]] ── */
$W = 'مرحلة من مفردات دورة الادارة القائمة في gov_screen_cycle لا من خيال — FINAL_CLOSE 12';
$A = array(
 // التمويل والملكية
 'SCR-0176' => array('التمويل والملكية','دورة الإدارة',3,'الملكية والضمانات','canonical','', 'انتقال الملكية والخروج فعل حصص الملكية — '.$W),
 'SCR-0178' => array('التمويل والملكية','دورة الإدارة',5,'الانحرافات والفروق','canonical','', 'شاشة انحرافات التمويل بعينها — '.$W),
 'SCR-0187' => array('التمويل والملكية','دورة الإدارة',3,'الملكية والضمانات','canonical','', 'سجل حصص الملكية في الاصول — '.$W),
 // المالية والخزينة
 'SCR-0012' => array('المالية والخزينة','دورة الإدارة',3,'المعاملات المالية الواردة','canonical','', 'كاتب التايم شيت المالي (ROW) — معاملة واردة من الموقع — '.$W),
 'SCR-0103' => array('المالية والخزينة','مركز العمل',0,'مركز العمل','canonical','', 'مساحة عمل محاسب التخصص — مركز عمل كنظائره في بقية الادارات — '.$W),
 'SCR-0124' => array('المالية والخزينة','دورة الإدارة',5,'المراجعة المحاسبية','canonical','', 'حسم مخالفات الوثائق فعل مراجعة محاسبية — '.$W),
 'SCR-0174' => array('المالية والخزينة','دورة الإدارة',4,'الاستحقاق وأحكام الأطراف','canonical','', 'احكام العميل والمورد والمشغل هي مرحلة الاستحقاق نفسها — '.$W),
 'SCR-0505' => array('المالية والخزينة','دورة الإدارة',10,'الترحيل والاعتمادات المالية','canonical','', 'متابعة الاعتمادات المتاخرة جزء من مرحلة الاعتمادات — '.$W),
 'SCR-0162' => array('المالية والخزينة','دورة الصرف',10,'دورة الصرف','canonical','', 'طلبات الدفع والسداد قلب دورة الصرف — '.$W),
 // ضبط مرجعي لا دورة معاملات (§5·10: سبب انطباق لا اختراع مرحلة)
 'SCR-0105' => array('المالية والخزينة','المرجع والإدارة',0,'لا تنطبق دورة عمل','not_applicable','سطح ضبط مرجعي (التخصصات المحاسبية) — سيره اعتماد الضبط لا دورة معاملات',''),
 'SCR-0121' => array('المالية والخزينة','المرجع والإدارة',0,'لا تنطبق دورة عمل','not_applicable','سطح ضبط حدود سلطة — يغذي كل المراحل ولا يملك مرحلة',''),
 'SCR-0123' => array('المالية والخزينة','المرجع والإدارة',0,'لا تنطبق دورة عمل','not_applicable','سجل بنود الوثائق المرجعي — ضبط تغطية لا معاملة',''),
 'SCR-0126' => array('المالية والخزينة','المرجع والإدارة',0,'لا تنطبق دورة عمل','not_applicable','ترحيل ادوار قديم — اجراء انتقالي لا مرحلة دورة',''),
 'SCR-0127' => array('المالية والخزينة','المرجع والإدارة',0,'لا تنطبق دورة عمل','not_applicable','هيكل اشراف رئيس الحسابات — بنية تنظيمية لا معاملة',''),
 'SCR-0128' => array('المالية والخزينة','المرجع والإدارة',0,'لا تنطبق دورة عمل','not_applicable','سجل اسعار الصرف اليومي — مرجع تسعيري يغذي كل المعاملات ولا يملك مرحلة',''),
 'SCR-0167' => array('المالية والخزينة','المرجع والإدارة',0,'لا تنطبق دورة عمل','not_applicable','سقوف سلطة الخزينة — ضبط حوكمي',''),
 'SCR-0169' => array('المالية والخزينة','المرجع والإدارة',0,'لا تنطبق دورة عمل','not_applicable','تعريف مراحل دورتي الدفع والقبض — ضبط الدورة نفسه لا مرحلة فيها',''),
 'SCR-0171' => array('المالية والخزينة','المرجع والإدارة',0,'لا تنطبق دورة عمل','not_applicable','مصفوفة فصل الواجبات — ضبط حوكمي',''),
 'SCR-0172' => array('المالية والخزينة','المرجع والإدارة',0,'لا تنطبق دورة عمل','not_applicable','ادوار وحدة الخزينة — بنية تنظيمية',''),
 // الموارد البشرية
 'SCR-0011' => array('الموارد البشرية','دورة الإدارة',3,'ملف الموظف وعقوده','canonical','', 'كاتب سجل الموظفين (ROW) — '.$W),
 'SCR-0009' => array('الموارد البشرية','دورة الإدارة',2,'التوظيف والتعيين','canonical','', 'معالج اعداد الموظف ينشئ الشخص — مرحلة التعيين — '.$W),
 // الحوكمة والالتزام
 'SCR-0362' => array('الحوكمة والالتزام','دورة الإدارة',3,'التفويض والإنابة والصلاحيات','canonical','', 'منح الصلاحية فعل التفويض — '.$W),
 'SCR-0364' => array('الحوكمة والالتزام','دورة الإدارة',4,'سلاليم الاعتماد والسقوف','canonical','', 'حدود المبالغ (gov_ladders) هي السقوف — '.$W),
 'SCR-0373' => array('الحوكمة والالتزام','دورة الإدارة',5,'الكيانات والتراخيص','canonical','', 'سجل الكيانات القانونية — '.$W),
 'SCR-0382' => array('الحوكمة والالتزام','دورة الإدارة',3,'التفويض والإنابة والصلاحيات','canonical','', 'جلسات النيابة فعل انابة — '.$W),
 'SCR-0385' => array('الحوكمة والالتزام','دورة الإدارة',5,'الكيانات والتراخيص','canonical','', 'التراخيص والكفالات — '.$W),
 'SCR-0386' => array('الحوكمة والالتزام','دورة الإدارة',3,'التفويض والإنابة والصلاحيات','canonical','', 'منح المجال المقيد تفويض وصول — '.$W),
 'SCR-0392' => array('الحوكمة والالتزام','دورة الإدارة',3,'التفويض والإنابة والصلاحيات','canonical','', 'التفويض بالتوقيع — '.$W),
 'SCR-0394' => array('الحوكمة والالتزام','دورة الإدارة',6,'الالتزام والامتثال','canonical','', 'مركز الحوكمة التقني يرصد الالتزام البنيوي — '.$W),
 'SCR-0361' => array('الحوكمة والالتزام','المرجع والإدارة',0,'لا تنطبق دورة عمل','not_applicable','انماط تفعيل المزايا — ضبط ظهور',''),
 'SCR-0558' => array('الحوكمة والالتزام','المرجع والإدارة',0,'لا تنطبق دورة عمل','not_applicable','تصنيف قواعد المنع — ضبط بوابة',''),
 // مركز البلاغات
 'SCR-0591' => array('مركز البلاغات','دورة الإدارة',3,'استقبال البلاغات','canonical','', 'الاستقبال والتصنيف بعينه — '.$W),
 'SCR-0595' => array('مركز البلاغات','دورة الإدارة',3,'استقبال البلاغات','canonical','', 'فتح بلاغ سياقي — قناة استقبال — '.$W),
 'SCR-0603' => array('مركز البلاغات','دورة الإدارة',5,'التحويل والإغلاق','canonical','', 'تحويل البلاغ وتفريعه — '.$W),
 'SCR-0588' => array('مركز البلاغات','دورة الإدارة',5,'التحويل والإغلاق','canonical','', 'اغلاق البلاغ وتاكيده — '.$W),
 'SCR-0605' => array('مركز البلاغات','دورة الإدارة',4,'المتابعة والتصعيد','canonical','', 'برج المراقبة يرصد التاخر والتصعيد — '.$W),
 // التشغيل
 'SCR-0442' => array('إدارة التشغيل','دورة الإدارة',8,'الانحرافات وإقفال الفترة','canonical','', 'التوقفات وتحديد المتحمل انحراف يحسم — '.$W),
 // القوى التشغيلية
 'SCR-0419' => array('القوى التشغيلية','دورة الإدارة',5,'التكليف على المعدات','canonical','', 'غرفة تحريك المشغلين على المعدات — '.$W),
 'SCR-0449' => array('القوى التشغيلية','دورة الإدارة',5,'التكليف على المعدات','canonical','', 'شاشة التشغيل تدير التكليف — '.$W),
 'SCR-0630' => array('الموارد البشرية','دورة الإدارة',6,'الخصومات والسلف','canonical','', 'السلف والعهد فعل دورة الرواتب — الدورة موطنها الموارد البشرية ومالك السطح DEP-13 يذكر بلا تعارض — '.$W),
 'SCR-0639' => array('الموارد البشرية','دورة الإدارة',7,'الأجور والمستحقات','canonical','', 'مسير الرواتب — '.$W),
 'SCR-0642' => array('الموارد البشرية','دورة الإدارة',2,'التوظيف والتعيين','canonical','', 'خط التوظيف من الشاغر الى المباشرة — '.$W),
 // المشتريات والمخازن
 'SCR-0491' => array('إدارة المشتريات التشغيلية','دورة الإدارة',3,'الطلبات وحدود المخزون','canonical','', 'طلبات الشراء — '.$W),
 'SCR-0492' => array('إدارة المشتريات التشغيلية','دورة الإدارة',5,'الترسية والشراء','canonical','', 'مقارنة العروض والترسية — '.$W),
 'SCR-0500' => array('إدارة المخازن','دورة الإدارة',3,'الصرف','canonical','', 'المرتجعات حركة صرف عكسية في دورة الصرف — '.$W),
 // المراجعة الداخلية
 'SCR-0017' => array('المراجع الداخلي المستقل','دورة الإدارة',3,'الملاحظات والتوصيات','canonical','', 'خطط المعالجة تتبع الملاحظات — '.$W),
 'SCR-0029' => array('المراجع الداخلي المستقل','دورة الإدارة',3,'الملاحظات والتوصيات','canonical','', 'ردود الادارات على الملاحظات — '.$W),
 'SCR-0018' => array('المراجع الداخلي المستقل','دورة الإدارة',1,'الميثاق والخطة','canonical','', 'صلاحيات المراجع من الميثاق — '.$W),
 'SCR-0021' => array('المراجع الداخلي المستقل','دورة الإدارة',1,'الميثاق والخطة','canonical','', 'اختصاصات المراجعة من الميثاق — '.$W),
 // مساحة العمل والمنصة
 'SCR-0470' => array('مساحة العمل الشخصية','مركز العمل',1,'ما ينتظر إجرائي','canonical','', 'طلباتي الشخصية في مركز العمل — '.$W),
 'SCR-0454' => array('مكتب الرئيس التنفيذي والنواب','مركز العمل',1,'الاعتمادات والمسائل المرفوعة','canonical','', 'موافقات التكليف صندوق اعتماد تنفيذي — '.$W),
 'SCR-0472' => array('مساحة العمل الشخصية','مركز العمل',0,'لا تنطبق دورة عمل','not_applicable','سطح تنبيهات شخصية — قناة تسليم لا مرحلة عمل',''),
 'SCR-0479' => array('مساحة العمل الشخصية','مركز العمل',0,'لا تنطبق دورة عمل','not_applicable','قشرة مساحة العمل — غلاف ملاحي لا معاملة',''),
 'SCR-0194' => array('مساحة العمل الشخصية','مركز العمل',0,'لا تنطبق دورة عمل','not_applicable','مسار محول صرف (nav_redirects) لا شاشة — حكم UXW قائم',''),
);

echo "═══ البند ⑫ — تأليفُ جسرِ دورةِ العمل" . ($APPLY ? '' : ' · DRY') . " ═══\n";

/* ── حراسة: المرحلةُ المؤلَّفةُ canonical يجب أن توجد في مفرداتِ دورتِها ── */
$bad = 0;
foreach ($A as $sid => $d) {
    if ($d[4] !== 'canonical') { continue; }
    $q = $conn->query("SELECT COUNT(*) c FROM gov_screen_cycle
                        WHERE dept_name='" . $e($d[0]) . "' AND stage_name='" . $e($d[3]) . "'");
    if ((int) $q->fetch_assoc()['c'] === 0) { echo "  ⛔ $sid: مرحلة «{$d[3]}» ليست في مفردات «{$d[0]}»\n"; $bad++; }
}
if ($bad) { exit("⛔ $bad مرحلةً مخترعةً — لا يُكتب شيء\n"); }
echo "  ✔ كلُّ مرحلةٍ مؤلَّفةٍ canonical موجودةٌ في مفرداتِ دورتِها\n";

/* ── ① الملتبس ── */
$nA = 0;
foreach ($AMB as $file => $j) {
    list($sid, $wit) = $j;
    if ($APPLY) {
        $conn->query("UPDATE gov_screen_cycle
                         SET screen_id='" . $e($sid) . "', bridge_rule='C6_MANUAL_AMBIG',
                             bridge_witness='" . $e('C6 · ' . $wit . ' · لقطة ' . $SNAP) . "',
                             bridge_snapshot='" . $e($SNAP) . "'
                       WHERE screen_file='" . $e($file) . "' AND bridge_rule='AMBIGUOUS_DECLARED'");
        $nA += $conn->affected_rows;
    }
    echo "  ① $file ⇒ $sid\n";
}
if ($APPLY) echo "  ✔ حُسم من الملتبس: $nA صفًّا\n";

/* ── ② التأليف ── */
$reg = array();
$r = $conn->query("SELECT screen_id, route, canonical_label_ar, grain_entity, owner_code FROM repair01_screen_registry");
while ($x = $r->fetch_assoc()) { $reg[$x['screen_id']] = $x; }
$n = 0; $na = 0;
foreach ($A as $sid => $d) {
    list($dept, $layer, $order, $stage, $kind, $reason, $wit) = $d;
    if (!isset($reg[$sid])) { echo "  ⛔ $sid ليس في السجل\n"; continue; }
    $s = $reg[$sid];
    $exists = (int) $conn->query("SELECT COUNT(*) c FROM gov_screen_cycle WHERE screen_id='" . $e($sid) . "'")->fetch_assoc()['c'];
    if ($exists > 0) { continue; }
    $ruleName = $kind === 'canonical' ? 'C5_AUTHORED' : 'C5_NOT_APPLICABLE';
    $bw = ($kind === 'canonical' ? 'C5 · ' . $wit : 'C5·NA · ' . $reason) . ' · لقطة ' . $SNAP;
    if ($APPLY) {
        $ok = $conn->query("INSERT INTO gov_screen_cycle
            (company_id, dept_name, layer_name, stage_order, stage_name, group_name, screen_title,
             screen_file, inputs_note, output_doc, resp_role, next_state, consumers, fin_impact,
             stage_kind, screen_id, bridge_rule, bridge_witness, bridge_snapshot)
            VALUES (0, '" . $e($dept) . "', '" . $e($layer) . "', " . (int) $order . ", '" . $e($stage) . "', '',
                    '" . $e($s['canonical_label_ar'] . ' (' . basename($s['route']) . ')') . "',
                    '" . $e(basename($s['route'])) . "', '" . $e($kind === 'canonical' ? $s['grain_entity'] : $reason) . "',
                    '', '', '', '', '', '" . $e($kind) . "', '" . $e($sid) . "',
                    '" . $e($ruleName) . "', '" . $e($bw) . "', '" . $e($SNAP) . "')");
        if (!$ok) { echo "  ✘ $sid: {$conn->error}\n"; continue; }
    }
    if ($kind === 'canonical') { $n++; } else { $na++; }
}
echo "  ② أُلِّف بمرحلةٍ: $n · وبِسببِ عدمِ انطباق: $na\n";

/* ── القياسُ بعدًا ── */
$den = (int) $conn->query("SELECT COUNT(*) c FROM repair01_screen_registry s
                            WHERE s.grain_fact_scope='OWN_FACT' AND s.grain_cardinality IN ('ROW','LINE')
                              AND s.lifecycle LIKE 'LIVE%'")->fetch_assoc()['c'];
$got = (int) $conn->query("SELECT COUNT(*) c FROM repair01_screen_registry s
                            WHERE s.grain_fact_scope='OWN_FACT' AND s.grain_cardinality IN ('ROW','LINE')
                              AND s.lifecycle LIKE 'LIVE%'
                              AND EXISTS(SELECT 1 FROM gov_screen_cycle g WHERE g.screen_id=s.screen_id)")->fetch_assoc()['c'];
echo "  المعاملاتُ بجسرٍ أو سببٍ: $got من $den\n";
