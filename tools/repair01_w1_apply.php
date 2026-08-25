<?php
/**
 * tools/repair01_w1_apply.php
 *   W01 — إغلاقُ الملكيّة: الرمزُ المعياريُّ · الدورُ المسؤولُ · حكمُ الظهورِ المحرَّم
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ثلاثةُ أعمالٍ في أداةٍ واحدةٍ لأنّها حكمٌ واحد**: مَن يملك السطحَ يحدّد
 *   رمزَه ودورَه ومَن يُمنَع من رؤيتِه. وتفريقُها في ثلاثِ أدواتٍ يخلق ثلاثةَ
 *   مصادرِ حقيقةٍ لسؤالٍ واحد.
 *
 * ◆ **ولا حكمَ بلا مصدرٍ في الدفتر**: كلُّ قيمةٍ تُكتب تحمل `rule` يسمّي
 *   القاعدةَ و`why` يسمّي دليلَها. وما لا مصدرَ له **يُسجَّل قرارًا** في
 *   `repair01_w1_decisions` ويحمل `source = W1_DECISION:<id>` — لا قيمةً
 *   يتيمةً لا يعرف أحدٌ من أين جاءت.
 *
 * ◆ **الشقُّ وظيفيٌّ لا اسميّ** (W00 §الترقيم): «المالية والخزينة» تُشقُّ
 *   سطحًا سطحًا بقاعدةِ `split_rule` — الاعترافُ والقيدُ إلى `DEP-05`،
 *   والقبضُ والصرفُ والتنفيذُ إلى `DEP-06`. وما يخدم الشقَّين معًا يُوسَم
 *   `SPLIT_FIN_SHARED` صراحةً ويُسنَد إلى الشقِّ الأمّ — **لا يُخفى الاشتراك**.
 *
 * ◆ **ولا `UPDATE` مدمِّر**: لا يُمسُّ مفتاحٌ تقنيٌّ ولا أجنبيٌّ ولا سجلُّ تدقيقٍ
 *   ولا حدث. المكتوبُ أعمدةُ وصفٍ في دفترِ الحملةِ وحدَها.
 *
 * ⚠ **`repair01_ingest.php` يمسح ويعيد الإدخال** — فإعادةُ تشغيلِه تمحو ما
 *   تكتبه هذه الأداة. أعِدْ تشغيلَ هذه بعدَه. والبوّابةُ `W1-01..03` ترصد المحوَ.
 *
 * التشغيل: php tools/repair01_w1_apply.php [--dry]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$DRY = in_array('--dry', $argv, true);
$NOW = date('Y-m-d H:i:s');
function E(mysqli $c, $s) { return "'" . $c->real_escape_string((string) $s) . "'"; }

echo "═══ W01 · إغلاقُ الملكيّة ═══" . ($DRY ? "   [تجربةٌ بلا كتابة]" : '') . "\n\n";

/* ═══════════════════════════════════════════════════════════════════════════
 * ① الرمزُ المعياريُّ لكلِّ سطح
 * ═══════════════════════════════════════════════════════════════════════════ */

/* الجسرُ غيرُ المشقوق — مسمًّى حيٌّ واحدٌ ⇐ رمزٌ واحد */
$MAP = array(); $SPLIT = array();
$q = $conn->query("SELECT legacy_name, canonical_code, verdict, split_rule FROM repair01_dept_crosswalk");
while ($r = $q->fetch_assoc()) {
    if ($r['verdict'] === 'SPLIT') { $SPLIT[$r['legacy_name']][$r['canonical_code']] = $r['split_rule']; }
    else { $MAP[$r['legacy_name']] = $r['canonical_code']; }
}

/**
 * شقُّ «المالية والخزينة» — حُسم سطحًا سطحًا على `split_rule`.
 *
 * `DEP-06` (الخزينة) = الحسابات البنكية · مركز النقد · تنفيذ التحصيل والصرف ·
 *   التحويلات · الأدوات · إعداد المطابقة البنكية · توقّع السيولة · النثرية.
 * وما عداه في هذه الوحدةِ اعترافٌ وقيدٌ وإقفالٌ وقوائم ⇒ `DEP-05`.
 */
$FIN_TRE = array(
    'payments.php'          => 'تنفيذُ الدفعِ والسداد — «تنفيذ التحصيل والصرف»',
    'treasury.php'          => 'الخزينةُ والصناديق — «مركز النقد»',
    'bank_recon.php'        => 'إعدادُ المطابقةِ البنكية — نصُّ القاعدةِ حرفًا',
    'cash_forecast.php'     => 'التدفقُ النقديُّ والتنبؤ — «توقّع السيولة»',
    'tre_board.php'         => 'لوحةُ الخزينةِ والبنوك — مساحةُ عملِ الشقِّ الثاني',
    'tre_pay_batch.php'     => 'دفعاتُ الدفعِ والتنفيذ — «تنفيذ الصرف»',
    'tre_beneficiary.php'   => 'المستفيدون والحساباتُ البنكية — «الحسابات البنكية»',
    'tre_receipts.php'      => 'سنداتُ القبضِ والتحصيل — «تنفيذ التحصيل»',
    'tre_cash_box.php'      => 'الخزائنُ والصناديق — «النثرية» و«مركز النقد»',
    'tre_bank_accounts.php' => 'سجلُّ الحساباتِ البنكيةِ والتوقيعات — «الحسابات البنكية»',
    'tre_exec_log.php'      => 'توثيقُ التنفيذِ والإشعارات — أثرُ التنفيذِ لا القيد',
    'tre_liquidity.php'     => 'السيولةُ والتدفقُ النقدي — «توقّع السيولة»',
);
/** أسطحٌ تخدم الشقَّين معًا — تُسنَد للشقِّ الأمِّ وتُوسَم مشتركةً صراحةً. */
$FIN_SHARED = array(
    'my_tasks.php', 'my_achievement.php', 'my_portal.php', 'my_requests.php', 'chats/index.php',
    'tickets_dept.php', 'ticket_open.php', 'approvals_inbox.php',
    'risk_dept_fin.php', 'gov_dept_fin.php', 'cfo_board.php',
    'dept_achievement.php', 'reports_index.php',
);

$nCanon = array(); $canonRows = 0;
$q = $conn->query("SELECT id, dept_legacy, screen_file FROM repair01_surfaces ORDER BY id");
while ($s = $q->fetch_assoc()) {
    $dept = $s['dept_legacy']; $f = $s['screen_file'];
    if (isset($MAP[$dept])) {
        $code = $MAP[$dept]; $rule = 'CROSSWALK_MAP';
        $why  = 'الجسرُ يربط المسمّى الحيَّ «' . $dept . '» برمزٍ واحدٍ بلا شقّ';
    } elseif ($dept === 'المالية والخزينة') {
        if (isset($FIN_TRE[$f]))                       { $code = 'DEP-06'; $rule = 'SPLIT_FIN_DEP06'; $why = $FIN_TRE[$f]; }
        elseif (in_array($f, $FIN_SHARED, true))       { $code = 'DEP-05'; $rule = 'SPLIT_FIN_SHARED'; $why = 'يخدم الشقَّين — يُسنَد للشقِّ الأمِّ DEP-05 ويُوسَم مشتركًا'; }
        else                                           { $code = 'DEP-05'; $rule = 'SPLIT_FIN_DEP05'; $why = 'اعترافٌ وقيدٌ وإقفالٌ وقوائم — خارجَ قاعدةِ الخزينة'; }
    } elseif ($dept === 'مكتب الرئيس التنفيذي والنواب') {
        $code = 'EX-CEO'; $rule = 'SPLIT_EXEC_CEO';
        $why  = 'أسطحُ النوّابِ التسعةَ عشرَ كلُّها في repair01_target_gaps بلا مقابلٍ مبنيّ — فالمبنيُّ كلُّه للرئيس';
    } else {
        $code = null; $rule = 'UNMAPPED'; $why = 'مسمًّى حيٌّ بلا جسر';
    }
    if ($code === null) { continue; }
    $nCanon[$code] = isset($nCanon[$code]) ? $nCanon[$code] + 1 : 1;
    if (!$DRY) {
        $conn->query("UPDATE repair01_surfaces SET canonical_code=" . E($conn, $code)
            . ", canon_rule=" . E($conn, $rule) . ", canon_why=" . E($conn, $why)
            . " WHERE id=" . (int) $s['id']);
    }
    $canonRows++;
}
echo "① الرمزُ المعياريّ — كُتب على {$canonRows} سطحًا\n";
ksort($nCanon);
$fin = array('DEP-05' => 0, 'DEP-06' => 0);
foreach ($nCanon as $k => $v) { if (isset($fin[$k])) { $fin[$k] = $v; } }
echo "   شقُّ المالية: DEP-05 = " . $fin['DEP-05'] . "  ·  DEP-06 = " . $fin['DEP-06']
   . "  ·  EX-CEO = " . (isset($nCanon['EX-CEO']) ? $nCanon['EX-CEO'] : 0) . "\n\n";

/* ═══════════════════════════════════════════════════════════════════════════
 * ② الدورُ المسؤول — سلسلةُ مصادرَ مرتَّبةٌ، وما بعدَها قرارٌ مسجَّل
 * ═══════════════════════════════════════════════════════════════════════════ */

/** ملفّاتُ المساحةِ الشخصية — مسؤولُها الموظّفُ نفسُه لا إدارة (WS-MY). */
$PERSONAL = array('my_tasks.php', 'my_achievement.php', 'my_portal.php', 'my_requests.php',
    'my_certificate.php', 'my_evaluation.php', 'profile.php', 'user_capacities.php',
    'change_password.php', 'chats/index.php');

$ownByFile = array(); $ownByName = array();
$q = $conn->query("SELECT screen, route, owner_dept, src_ref FROM repair01_ownership
                    WHERE owner_dept <> '' AND owner_dept <> 'غير محسوم'");
while ($r = $q->fetch_assoc()) {
    $b = strtolower(basename((string) $r['route']));
    if ($b !== '') { $ownByFile[$b][$r['owner_dept']] = $r['src_ref']; }
    $n = trim((string) $r['screen']);
    if ($n !== '') { $ownByName[$n][$r['owner_dept']] = $r['src_ref']; }
}
/* الشاشةُ التي تظهر في دورةِ إدارةٍ واحدةٍ لا غير — الدورةُ نفسُها دليلُ الملكيّة */
$soleCycle = array();
$q = $conn->query("SELECT screen_file, COUNT(DISTINCT dept_legacy) n, MIN(dept_legacy) d
                     FROM repair01_surfaces GROUP BY screen_file HAVING n = 1");
while ($r = $q->fetch_assoc()) { $soleCycle[$r['screen_file']] = $r['d']; }

$roleN = array(); $roleRows = 0; $pending = array();
$q = $conn->query("SELECT id, screen_file, screen_title FROM repair01_surfaces
                    WHERE resp_role IN ('', '—', '-') ORDER BY id");
while ($s = $q->fetch_assoc()) {
    $f = $s['screen_file']; $b = strtolower(basename($f));
    $bare = trim(preg_replace('~\s*\([^)]*\)\s*$~', '', (string) $s['screen_title']));
    $role = null; $src = ''; $why = '';

    if (in_array($f, $PERSONAL, true)) {
        $role = 'الموظف صاحب المساحة'; $src = 'ROLE_WS_MY';
        $why  = 'WS-MY ليست إدارةً — «Personal Workspace · إسقاطٌ ومُطلِقُ طلباتٍ لا مصدرَ حقيقة»';
    } elseif (isset($ownByFile[$b]) && count($ownByFile[$b]) === 1) {
        $role = key($ownByFile[$b]); $src = 'ROLE_OWNER_FILE';
        $why  = 'دفترُ الملكيّة يسمّي مالكًا واحدًا لهذا المسار · ' . reset($ownByFile[$b]);
    } elseif (isset($ownByName[$bare]) && count($ownByName[$bare]) === 1) {
        $role = key($ownByName[$bare]); $src = 'ROLE_OWNER_NAME';
        $why  = 'دفترُ الملكيّة يسمّي مالكًا واحدًا لهذه الشاشة · ' . reset($ownByName[$bare]);
    } elseif (isset($soleCycle[$f])) {
        $role = $soleCycle[$f]; $src = 'ROLE_SOLE_CYCLE';
        $why  = 'الشاشةُ تظهر في دورةِ إدارةٍ واحدةٍ فقط في سجلِّ الشاشات';
    } else {
        $pending[$f][] = (int) $s['id'];
        continue;
    }
    $roleN[$src] = isset($roleN[$src]) ? $roleN[$src] + 1 : 1;
    if (!$DRY) {
        $conn->query("UPDATE repair01_surfaces SET resp_role=" . E($conn, $role)
            . ", role_source=" . E($conn, $src) . ", role_why=" . E($conn, $why)
            . " WHERE id=" . (int) $s['id']);
    }
    $roleRows++;
}

/* ما بقي بلا مصدر ⇒ قرارٌ مسجَّلٌ لا تخمين */
$DECISIONS = array(
    'W1-D-01' => array(
        'topic'     => 'الدورُ المسؤولُ عن «توزيع المعدات والمشغّلين»',
        'question'  => 'distribution.php يظهر في ثلاثِ دوراتٍ (الأسطول · التشغيل · القوى) بلا مالكٍ في دفترِ الملكيّة — فمن يملكه؟',
        'ruling'    => 'إدارة التشغيل',
        'rationale' => 'ثلاثةُ ظهوراتٍ كلُّها canonical فلا يحسمها السجلّ. والتوزيعُ فعلُ إسنادٍ تشغيليٌّ '
                     . 'يقع في مركزِ عملِ التشغيل (غرفة العمليات)، بينما ظهورُه في الأسطولِ جاهزيةٌ وفي القوى تكليف. '
                     . '⚠ حكمٌ مسجَّلٌ ينتظر المالك — يُراجَع في W04 مع الحقيقةِ الميدانية.',
        'evidence'  => 'repair01_surfaces #2385 (الأسطول) · #2405 (التشغيل · مركز العمل) · #2580 (القوى)',
    ),
    'W1-D-02' => array(
        'topic'     => 'مالكُ «تسجيل التايم شيت والإنتاج» في دفترِ الملكيّة',
        'question'  => 'صفٌّ واحدٌ من ٢٦٥ يحمل owner_dept = «غير محسوم» — فأيُّ حكمٍ يُعطى لظهورِه عند إدارةِ الموردين؟',
        'ruling'    => 'ESCALATED_DECISION — لا يُحسم في W01',
        'rationale' => 'الحكمُ يحتاج مالكَ التايم شيت أوّلًا، وهو نطاقُ الموجةِ أ (W04 · الحقيقةُ الميدانية). '
                     . 'وإسنادُه هنا تخمينٌ يثبّت الخطأ. فالصفُّ محكومٌ بأنّه مُصعَّد — لا متروكٌ بلا حكم.',
        'evidence'  => 'repair01_ownership #3185 · src_ref: 10 › 05_التداخلات_والملكية › ص234',
    ),
);
foreach ($DECISIONS as $id => $d) {
    $scope = ($id === 'W1-D-01') ? array_sum(array_map('count', $pending)) : 1;
    if (!$DRY) {
        $conn->query("REPLACE INTO repair01_w1_decisions
            (decision_id,stage,topic,question,ruling,rationale,evidence,scope_rows,status,decided_at) VALUES ("
            . E($conn, $id) . ",'W01'," . E($conn, $d['topic']) . "," . E($conn, $d['question']) . ","
            . E($conn, $d['ruling']) . "," . E($conn, $d['rationale']) . "," . E($conn, $d['evidence']) . ","
            . (int) $scope . ",'RECORDED_PENDING_OWNER'," . E($conn, $NOW) . ")");
    }
}
foreach ($pending as $f => $ids) {
    foreach ($ids as $id) {
        $roleN['W1_DECISION'] = isset($roleN['W1_DECISION']) ? $roleN['W1_DECISION'] + 1 : 1;
        if (!$DRY) {
            $conn->query("UPDATE repair01_surfaces SET resp_role=" . E($conn, $DECISIONS['W1-D-01']['ruling'])
                . ", role_source='W1_DECISION:W1-D-01'"
                . ", role_why=" . E($conn, 'بلا مصدرٍ في الحزمة — قرارٌ مسجَّلٌ ينتظر المالك')
                . " WHERE id=" . (int) $id);
        }
        $roleRows++;
    }
}
echo "② الدورُ المسؤول — مُلئ {$roleRows} سطحًا\n";
foreach ($roleN as $k => $v) { printf("   %-18s %d\n", $k, $v); }
echo "\n";

/* ═══════════════════════════════════════════════════════════════════════════
 * ③ حكمُ الظهورِ المحرَّم — كلُّ صفٍّ يُنقل لمالكِه أو يُوسَم سياقيًّا بمبرَّر
 * ═══════════════════════════════════════════════════════════════════════════ */

/** مساحةُ الدورِ ⇐ المسمّى الحيُّ لإدارتِه (لا تُخمَّن — تُعلَن). */
$SPACE_DEPT = array(
    'إدارة المالية'         => 'المالية والخزينة',
    'المدير المالي'         => 'المالية والخزينة',
    'ادارة المبيعات'        => 'المبيعات والعقود',
    'ادارة الموردين'        => 'إدارة الموردين',
    'ادارة التشغيل'         => 'إدارة التشغيل',
    'ادارة الموارد البشرية' => 'الموارد البشرية',
    'إدارة الموقع'          => 'إدارة الموقع',
    'ادارة الاسطول'         => 'إدارة الأسطول',
    'إدارة التمويل'         => 'التمويل والملكية',
    'ادارة الصيانة'         => 'إدارة الصيانة',
    'إدارة المشتريات'       => 'إدارة المشتريات التشغيلية',
    'إدارة النقل والترحيل'  => 'النقل والترحيل',
    'القوى التشغيلية'       => 'القوى التشغيلية',
    'أمين المستودع'         => 'إدارة المخازن',
);

/* ما يظهر في دورةِ إدارةِ المساحةِ نفسِها ⇒ السجلُّ نفسُه يُقرُّ بظهورٍ سياقيّ */
$inCycle = array();
$q = $conn->query("SELECT dept_legacy, screen_file, screen_title, stage_kind, src_ref FROM repair01_surfaces");
while ($r = $q->fetch_assoc()) {
    $inCycle[$r['dept_legacy']][strtolower(basename((string) $r['screen_file']))] = $r;
}

$vN = array(); $ruled = 0; $unmapped = 0;
$q = $conn->query("SELECT id, space_role, screen, route, owner_dept, src_ref
                     FROM repair01_ownership WHERE classification = 'FORBIDDEN' ORDER BY id");
while ($o = $q->fetch_assoc()) {
    $sd = isset($SPACE_DEPT[$o['space_role']]) ? $SPACE_DEPT[$o['space_role']] : null;
    $bn = strtolower(basename((string) $o['route']));
    if ($sd === null) {
        $unmapped++; continue;
    }
    if ($o['owner_dept'] === 'غير محسوم') {
        $v = 'ESCALATED_DECISION'; $rule = 'FB_R5_ESCALATED';
        $why = $DECISIONS['W1-D-02']['rationale']; $ev = 'W1_DECISION:W1-D-02';
    } elseif ($o['owner_dept'] === $sd) {
        $v = 'RECLASSIFY_OWNED'; $rule = 'FB_R1_SELF_OWNED';
        $why = 'المالكُ هو الإدارةُ نفسُها — فالظهورُ ملكيّةٌ لا تعدٍّ. التصنيفُ الأصليُّ خطأُ تسمية.';
        $ev  = (string) $o['src_ref'];
    } elseif ($o['owner_dept'] === 'مساحة العمل الشخصية') {
        $v = 'RECLASSIFY_PERSONAL'; $rule = 'FB_R2_PERSONAL';
        $why = 'المالكُ WS-MY وليست إدارةً — ومساحةُ كلِّ موظّفٍ حقُّه لا تعدٍّ على إدارةٍ أخرى.';
        $ev  = 'repair01_departments.WS-MY';
    } elseif (isset($inCycle[$sd][$bn])) {
        $row = $inCycle[$sd][$bn];
        $v = 'CONTEXTUAL_READ_ONLY'; $rule = 'FB_R3_IN_OWN_CYCLE';
        $why = 'سجلُّ الشاشاتِ يُدرج هذه الشاشةَ في دورةِ «' . $sd . '» نفسِها ('
             . $row['stage_kind'] . ') — فالظهورُ سياقيٌّ مقروءٌ فقط، والكتابةُ تبقى للمالك «'
             . $o['owner_dept'] . '».';
        $ev  = (string) $row['src_ref'];
    } else {
        $v = 'REVOKED_TO_OWNER'; $rule = 'FB_R4_NO_BASIS';
        $why = 'لا أثرَ لهذه الشاشةِ في دورةِ «' . $sd . '» ولا في ملكيّتِها — فالظهورُ بلا سندٍ '
             . 'ويُسحَب إلى مالكِه «' . $o['owner_dept'] . '».';
        $ev  = (string) $o['src_ref'];
    }
    $vN[$v] = isset($vN[$v]) ? $vN[$v] + 1 : 1;
    if (!$DRY) {
        $conn->query("UPDATE repair01_ownership SET w1_verdict=" . E($conn, $v)
            . ", w1_rule=" . E($conn, $rule) . ", w1_reason=" . E($conn, mb_substr($why, 0, 390))
            . ", w1_evidence=" . E($conn, $ev) . ", w1_at=" . E($conn, $NOW)
            . " WHERE id=" . (int) $o['id']);
    }
    $ruled++;
}
echo "③ الظهورُ المحرَّم — حُكم على {$ruled} صفًّا" . ($unmapped ? "  ·  بلا جسرِ مساحة: {$unmapped}" : '') . "\n";
ksort($vN);
foreach ($vN as $k => $v) { printf("   %-22s %d\n", $k, $v); }

echo "\n" . ($DRY ? "تجربةٌ — لم يُكتب شيء.\n" : "تمّ. شغّلْ الآن: php tools/repair01_w1_gate.php\n");
exit(0);
