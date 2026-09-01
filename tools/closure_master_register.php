<?php
/**
 * tools/closure_master_register.php — السجلُّ الجامعُ للإغلاقِ النهائيّ
 * ═══════════════════════════════════════════════════════════════════════════
 * WORK-00 من أمرِ CLOSURE_SYSTEM — «MASTER FINAL CLOSURE REGISTER».
 *
 * ◆ **المخزنُ حكمٌ والإسقاطُ مرآة**: المخزنُ الحاكمُ ملفُّ
 *   `registers/MASTER_FINAL_CLOSURE_REGISTER.json` — والبذرُ **لا يدهس صفًّا
 *   موجودًا** (درسُ `repair01_ingest يمحو أحكامَ W01`): الصفُّ الموجودُ تُحدَّث
 *   لقطتُه الحيّةُ فقط، **ولا تتغيّر حالتُه آليًّا** — الحالةُ تتغيّر بدليلٍ
 *   يُسجَّل بـ`--set` مع `--evidence`.
 *
 * ◆ **ويُجيب عن الثمانية**: ما بقي؟ لماذا؟ مصدرُه؟ صنفُه (خطأ/دَين/مالك/UAT)؟
 *   يعتمد على؟ يحجب؟ دليلُ إغلاقِه؟ حاجبُ إصدارٍ أم مؤجَّل؟
 *
 * التشغيل:
 *   php tools/closure_master_register.php --bootstrap   ← يبذر الغائبَ فقط
 *   php tools/closure_master_register.php --report      ← يُسقِط الوثائقَ الثلاث MD
 *   php tools/closure_master_register.php --set=CL-... --status=... --evidence=... [--note=...]
 *   php tools/closure_master_register.php --sprint      ← يستخرج أعلى 5–8 أعمالٍ بالخوارزمية
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
$REG  = $ROOT . '/docs/REPAIR01_20260823/registers';
$STORE = $REG . '/MASTER_FINAL_CLOSURE_REGISTER.json';
$SNAP = trim((string) shell_exec('git -C "' . $ROOT . '" rev-parse --short HEAD 2>NUL')) ?: 'UNKNOWN_SNAPSHOT';
$NOW  = date('Y-m-d H:i');

$VALID_STATUS = array('OPEN','IN_PROGRESS','IMPLEMENTED_NOT_VERIFIED','PARTIALLY_IMPLEMENTED',
    'BLOCKED_OWNER','BLOCKED_GOVERNING_SOURCE','BLOCKED_UAT','BLOCKED_ENVIRONMENT',
    'REGRESSION','EVIDENCE_CLOSED');
$VALID_FINAL = array('', 'EVIDENCE_CLOSED','NOT_APPLICABLE','SUPERSEDED','GOVERNED_DEFERRED');

/* ═══ المخزن ═══════════════════════════════════════════════════════════════ */
$db = is_file($STORE)
    ? json_decode((string) file_get_contents($STORE), true)
    : array('meta' => array(), 'items' => array(), 'owner' => array(), 'legacy' => array());
if (!is_array($db)) { exit("⛔ المخزنُ تالفٌ — لا يُداس: $STORE\n"); }

function row_defaults() {
    return array(
        'Closure_ID' => '', 'Source_Document' => '', 'Source_ID' => '', 'Domain' => '',
        'Target_ID' => '—', 'Requirement_ID' => '—', 'Current_Status' => 'OPEN',
        'Priority' => 'P2', 'Execution_Rank' => 500, 'Severity' => 'MEDIUM',
        'Release_Impact' => 'NON_BLOCKING', 'Applicability' => 'APPLICABLE',
        'Business_Owner' => 'المالك', 'Technical_Owner' => 'المنفِّذ',
        'Blocker_Class' => '—', 'Required_By_Gate' => '—', 'Depends_On' => array(),
        'Blocks' => array(), 'Dependency_Type' => '—', 'Dependency_Fanout' => 0,
        'Unlock_Value' => 'LOW', 'Estimated_Effort' => 'M',
        'Evidence_Contract' => '', 'Next_Action' => '', 'Current_Snapshot' => '',
        'Last_Updated_Snapshot' => '', 'Last_Evidence_Ref' => '',
        'Status_Changed_At' => '', 'Final_Disposition' => '',
        'Levels' => array('Decision' => '?', 'Implementation' => '?', 'Wiring' => '?',
                          'Exercise' => '?', 'Evidence' => '?', 'Closure' => '?'),
    );
}

/** بذرُ صفٍّ: الجديدُ يُكتب كاملًا · الموجودُ تُحدَّث لقطتُه الحيّةُ فقط. */
function seed(&$db, $id, $fields, $snap, $now, &$added, &$touched) {
    if (isset($db['items'][$id])) {
        if (isset($fields['Current_Snapshot'])) {
            $db['items'][$id]['Current_Snapshot'] = $fields['Current_Snapshot'];
            $db['items'][$id]['Last_Updated_Snapshot'] = $snap;
        }
        $touched++;
        return;
    }
    $r = array_merge(row_defaults(), $fields);
    $r['Closure_ID'] = $id;
    $r['Last_Updated_Snapshot'] = $snap;
    $r['Status_Changed_At'] = $now . ' @ ' . $snap;
    $db['items'][$id] = $r;
    $added++;
}

/* ═══ --bootstrap ═════════════════════════════════════════════════════════ */
if (in_array('--bootstrap', $argv, true)) {
    $added = 0; $touched = 0;

    /* ── ① فجواتُ INJ-FIX-01 المقيسةُ (المقام 33) من مقياسِها الحيّ ─────── */
    $gc = json_decode((string) @file_get_contents($REG . '/_gap_coverage.json'), true);
    if (!$gc) { exit("⛔ شغِّل أوّلًا: php tools/injfix01_gap_coverage.php --json=" . $REG . "/_gap_coverage.json\n"); }
    $gapPrio = array();
    foreach (array('open', 'unverified') as $k) {
        foreach ((array) ($gc[$k] ?? array()) as $g) {
            if (preg_match('~^(GAP-\d+)\s*\((P\d)\)~', $g, $m)) { $gapPrio[$m[1]] = $m[2]; }
        }
    }
    foreach ((array) $gc['closed'] as $g) {
        seed($db, 'CL-' . $g, array(
            'Source_Document' => 'tools/injfix01_gap_coverage.php', 'Source_ID' => $g,
            'Domain' => 'INJ-FIX-01', 'Current_Status' => 'EVIDENCE_CLOSED',
            'Priority' => 'P1', 'Severity' => 'HIGH', 'Release_Impact' => 'BLOCKING',
            'Evidence_Contract' => 'شاهدُ الفجوةِ أخضرُ في حزامِ الشواهد',
            'Current_Snapshot' => 'شاهدُها أخضرُ @ ' . $SNAP,
            'Last_Evidence_Ref' => 'tools/injfix01_gap_coverage.php @ ' . $SNAP,
            'Final_Disposition' => 'EVIDENCE_CLOSED',
            'Levels' => array('Decision' => '✔', 'Implementation' => '✔', 'Wiring' => '✔',
                              'Exercise' => '✔', 'Evidence' => '✔', 'Closure' => '✔'),
        ), $SNAP, $NOW, $added, $touched);
    }
    foreach ((array) $gc['open'] as $g) {
        $gid = preg_replace('~\s*\(P\d\)$~', '', $g);
        $p = $gapPrio[$gid] ?? 'P2';
        seed($db, 'CL-' . $gid, array(
            'Source_Document' => 'tools/injfix01_gap_coverage.php', 'Source_ID' => $gid,
            'Domain' => 'INJ-FIX-01', 'Current_Status' => 'OPEN', 'Priority' => $p,
            'Severity' => $p === 'P0' ? 'CRITICAL' : ($p === 'P1' ? 'HIGH' : 'MEDIUM'),
            'Release_Impact' => in_array($p, array('P0', 'P1'), true) ? 'BLOCKING' : 'NON_BLOCKING',
            'Evidence_Contract' => 'شاهدُ الفجوةِ يخضرُّ في الحزام',
            'Next_Action' => 'إغلاقُ سببِ حمرةِ الشاهد — والقراءةُ من نصِّ رسوبِه لا رقمِه',
            'Current_Snapshot' => 'شاهدُها أحمرُ @ ' . $SNAP,
        ), $SNAP, $NOW, $added, $touched);
    }
    foreach ((array) ($gc['unverified'] ?? array()) as $g) {
        $gid = preg_replace('~\s*\(P\d\)$~', '', trim((string) $g));
        if (!preg_match('~^GAP-\d+$~', $gid)) { continue; }
        seed($db, 'CL-' . $gid, array(
            'Source_Document' => 'tools/injfix01_gap_coverage.php', 'Source_ID' => $gid,
            'Domain' => 'INJ-FIX-01', 'Current_Status' => 'IMPLEMENTED_NOT_VERIFIED',
            'Priority' => $gapPrio[$gid] ?? 'P2',
            'Evidence_Contract' => 'تصريحُ شاهدٍ ثمَّ خضرتُه',
            'Next_Action' => 'كتابةُ شاهدٍ مصرَّحٍ للفجوة',
            'Current_Snapshot' => 'بلا شاهدٍ مصرَّح @ ' . $SNAP,
        ), $SNAP, $NOW, $added, $touched);
    }

    /* ── ② دفترُ المتطلباتِ الرسميُّ FR-* (‏الدفترُ حَكَمُ الحالة) ─────────── */
    require_once $ROOT . '/tools/lib/xlsx_io.php';
    $wb = xlsx_read($ROOT . '/docs/sources/INJ-FRD-REM-01/workbook.xlsx');
    $wr = $wb['سجل المتطلبات'];
    $ix = array();
    foreach ($wr[3] as $i => $h) { $ix[trim(str_replace('◆ ', '', (string) $h))] = $i; }
    $clMap = array(
        'EVIDENCE_CLOSED' => 'EVIDENCE_CLOSED', 'IMPLEMENTED_NOT_CLOSED' => 'IMPLEMENTED_NOT_VERIFIED',
        'BLOCKED_OWNER_DECISION' => 'BLOCKED_OWNER', 'BLOCKED_GOVERNING_SOURCE' => 'BLOCKED_GOVERNING_SOURCE',
        'REGRESSION_CONSTRAINT' => 'REGRESSION', 'OPEN' => 'OPEN', '' => 'OPEN',
    );
    foreach ($wr as $i => $r) {
        if ($i < 4) { continue; }
        $rid = trim((string) ($r[$ix['المعرِّف']] ?? ''));
        if (!preg_match('~^[A-Z]{2,4}-[A-Z]{2,4}-\d{3}$~', $rid)) { continue; }
        $cl  = trim((string) ($r[$ix['Closure_State']] ?? ''));
        $st  = $clMap[$cl] ?? 'OPEN';
        $gap = trim((string) ($r[$ix['الفجوة']] ?? '')) ?: '—';
        $pr  = trim((string) ($r[$ix['الأولوية']] ?? '')) ?: 'P2';
        seed($db, 'CL-' . $rid, array(
            'Source_Document' => 'docs/sources/INJ-FRD-REM-01/workbook.xlsx',
            'Source_ID' => $rid, 'Requirement_ID' => $rid,
            'Domain' => trim((string) ($r[$ix['المجال']] ?? '')) ?: '—',
            'Target_ID' => $gap, 'Current_Status' => $st, 'Priority' => $pr,
            'Severity' => $pr === 'P0' ? 'CRITICAL' : ($pr === 'P1' ? 'HIGH' : 'MEDIUM'),
            'Release_Impact' => in_array($pr, array('P0', 'P1'), true) ? 'BLOCKING' : 'NON_BLOCKING',
            'Evidence_Contract' => 'شاهدٌ في حزامِ tests/injfrd01_belt.php',
            'Current_Snapshot' => 'Closure_State=' . ($cl ?: 'OPEN') . ' @ ' . $SNAP,
            'Final_Disposition' => $st === 'EVIDENCE_CLOSED' ? 'EVIDENCE_CLOSED' : '',
            'Blocker_Class' => $st === 'BLOCKED_OWNER' ? 'OWNER_DECISION'
                              : ($st === 'BLOCKED_GOVERNING_SOURCE' ? 'GOVERNING_SOURCE' : '—'),
        ), $SNAP, $NOW, $added, $touched);
    }

    /* ── ③ أهدافُ RPR-02 الستةَ عشرَ — الحالةُ من آخرِ تشغيلٍ حيٍّ للوحة ── */
    $r2 = array(
        1  => array('سجل شاشات جامع مقيس بالمقام', 'EVIDENCE_CLOSED'),
        2  => array('ملكية كل سطح محسومة بشاهد', 'EVIDENCE_CLOSED'),
        3  => array('دورة عمل معلنة لكل معاملة', 'EVIDENCE_CLOSED'),
        4  => array('آلة حالة مسندة لكل سطح معاملة', 'EVIDENCE_CLOSED'),
        5  => array('اختبار سالب لفصل الواجبات الحرج', 'EVIDENCE_CLOSED'),
        6  => array('مرجع مصدر صريح لكل إسقاط منطبق', 'EVIDENCE_CLOSED'),
        7  => array('جسر معرف الشاشة بمرحلة دورة العمل', 'EVIDENCE_CLOSED'),
        8  => array('تطابق ترتيب السايدبار مع الملف', 'EVIDENCE_CLOSED'),
        9  => array('أسطح تكتب ومالك حقيقتها مجهول = 0', 'EVIDENCE_CLOSED'),
        10 => array('حقيقة واحدة لها مصدران = 0', 'EVIDENCE_CLOSED'),
        11 => array('كتابة تعبر حدود إدارة بلا عقد = 0', 'EVIDENCE_CLOSED'),
        12 => array('هجرات غير مصالحة مع الدفتر = 0', 'EVIDENCE_CLOSED'),
        13 => array('شاشة PLATFORM بلا تبرير منصي معتمد = 0 (المقيس 12)', 'BLOCKED_OWNER'),
        14 => array('ملف مكتبة مسجل شاشة = 0 وقاعدة مانعة', 'EVIDENCE_CLOSED'),
        15 => array('خروج من المقام بلا مرجع قرار مالك = 0', 'EVIDENCE_CLOSED'),
        16 => array('اسم معروض غير معتمد = 0 (المقيس 3 منها 2 PENDING_OWNER)', 'BLOCKED_OWNER'),
    );
    foreach ($r2 as $n => $t) {
        seed($db, sprintf('CL-R2-T%02d', $n), array(
            'Source_Document' => 'docs/REPAIR01_20260823/RPR02_TARGET_UNIVERSE.md',
            'Source_ID' => sprintf('RPR-02 هدف %d', $n), 'Domain' => 'RPR-02',
            'Target_ID' => sprintf('T%02d', $n), 'Current_Status' => $t[1],
            'Priority' => $t[1] === 'EVIDENCE_CLOSED' ? 'P1' : 'P1',
            'Blocker_Class' => $t[1] === 'BLOCKED_OWNER' ? 'OWNER_DECISION' : '—',
            'Evidence_Contract' => 'tools/rpr02_acceptance_scorecard.php يبلغ المستهدف',
            'Current_Snapshot' => $t[0] . ' @ ' . $SNAP,
            'Last_Evidence_Ref' => 'tools/rpr02_acceptance_scorecard.php @ ' . $SNAP,
            'Final_Disposition' => $t[1] === 'EVIDENCE_CLOSED' ? 'EVIDENCE_CLOSED' : '',
        ), $SNAP, $NOW, $added, $touched);
    }

    /* ── ④ مقاييسُ RPR-03 التسعةَ عشرَ — من آخرِ تشغيلٍ حيٍّ ─────────────── */
    $r3 = array(
        1  => array('هجرات غير مصالحة = 0', 'EVIDENCE_CLOSED'),
        2  => array('بوابة ترسب على هجرة لا تسجل نفسها', 'EVIDENCE_CLOSED'),
        3  => array('أحداث بلا عقد مستهلك فعال = 0', 'EVIDENCE_CLOSED'),
        4  => array('مستهلك متوقف بلا إنذار = 0', 'EVIDENCE_CLOSED'),
        5  => array('نافذة ظل الاعتماد مقيسة (تقييمات 0 — تحتاج وقائع)', 'IMPLEMENTED_NOT_VERIFIED'),
        6  => array('رحلات اعتماد تحجب فعلا 14/14 — وضع monitor', 'IMPLEMENTED_NOT_VERIFIED'),
        7  => array('مسار قرار الصلاحية واحد', 'EVIDENCE_CLOSED'),
        8  => array('أسطح PLATFORM بلا تبرير = 0 (المقيس 12)', 'BLOCKED_OWNER'),
        9  => array('رحلات بشرية كاملة بمسارها السالب 6/6', 'BLOCKED_UAT'),
        10 => array('محطات مبنية بصفر صف = 0', 'EVIDENCE_CLOSED'),
        11 => array('قيود يدوية بلا مصدر = 0 (المقيس 1644)', 'OPEN'),
        12 => array('تمرين استعادة على المخطط الحالي', 'EVIDENCE_CLOSED'),
        13 => array('تثبيت من الصفر على المخطط الحالي', 'EVIDENCE_CLOSED'),
        14 => array('شاشات ذهبية معتمدة 10/10', 'EVIDENCE_CLOSED'),
        15 => array('مسح بنيوي آلي منفذ', 'EVIDENCE_CLOSED'),
        16 => array('مراجعة يدوية عميقة للذهبيات العشر', 'BLOCKED_UAT'),
        17 => array('رسائل ميتة بلا حكم = 0', 'EVIDENCE_CLOSED'),
        18 => array('مستهلكون حرجون متوقفون = 0', 'EVIDENCE_CLOSED'),
        19 => array('إخفاقات حرجة في جدولة المهام = 0', 'EVIDENCE_CLOSED'),
    );
    foreach ($r3 as $n => $t) {
        seed($db, sprintf('CL-R3-M%02d', $n), array(
            'Source_Document' => 'docs/REPAIR01_20260823/RPR03_SCORECARD.md',
            'Source_ID' => sprintf('RPR-03 مقياس %d', $n), 'Domain' => 'RPR-03',
            'Target_ID' => sprintf('M%02d', $n), 'Current_Status' => $t[1],
            'Priority' => in_array($n, array(8, 11), true) ? 'P1' : 'P2',
            'Blocker_Class' => $t[1] === 'BLOCKED_OWNER' ? 'OWNER_DECISION'
                              : ($t[1] === 'BLOCKED_UAT' ? 'UAT' : '—'),
            'Evidence_Contract' => 'tools/rpr03_scorecard.php يبلغ المستهدف',
            'Current_Snapshot' => $t[0] . ' @ ' . $SNAP,
            'Last_Evidence_Ref' => 'tools/rpr03_scorecard.php @ ' . $SNAP,
            'Final_Disposition' => $t[1] === 'EVIDENCE_CLOSED' ? 'EVIDENCE_CLOSED' : '',
        ), $SNAP, $NOW, $added, $touched);
    }

    /* ── ⑤ أعمالُ Sprint-01 الخمسة ────────────────────────────────────── */
    $works = array(
        'CL-WORK-01' => array('حماية مصدر العمل — REMOTE_HEAD=LOCAL_HEAD والوسوم والأسس', 'P0', 'CRITICAL', 900, 'CRITICAL_UNLOCK'),
        'CL-WORK-02' => array('إغلاق GAP-10 — قناة المناظر المحفوظة نمطا والقنوات التسع', 'P0', 'CRITICAL', 890, 'HIGH'),
        'CL-WORK-03' => array('جدولة cron_events — من Wired إلى Exercised بنبض ومؤشر', 'P0', 'HIGH', 880, 'CRITICAL_UNLOCK'),
        'CL-WORK-04' => array('EFFECT_MISSING نمطا — عقد الأثر والاختبارات الستة', 'P1', 'HIGH', 870, 'HIGH'),
        'CL-WORK-05' => array('شد السقاطات الست خارج نافذة القياس بأساس مقترح ولقطة', 'P1', 'MEDIUM', 860, 'MEDIUM'),
    );
    foreach ($works as $id => $w) {
        seed($db, $id, array(
            'Source_Document' => 'docs/REPAIR01_20260823/orders/CLOSURE_SYSTEM.txt',
            'Source_ID' => str_replace('CL-', '', $id), 'Domain' => 'SPRINT-01',
            'Current_Status' => 'OPEN', 'Priority' => $w[1], 'Severity' => $w[2],
            'Execution_Rank' => $w[3], 'Unlock_Value' => $w[4], 'Release_Impact' => 'BLOCKING',
            'Evidence_Contract' => 'معيار القبول المنصوص في الأمر حرفا',
            'Current_Snapshot' => $w[0] . ' @ ' . $SNAP,
        ), $SNAP, $NOW, $added, $touched);
    }

    /* ── ⑤ب جولةُ `GOV_UI_EXEC` — بنودُها بمقاماتِها المقيسة ─────────────
       ◆ **بذرٌ لا يدهس**: كلُّ بندٍ يُكتب مرّةً بحالتِه المقيسةِ يومَ الإغلاق،
         وتحديثُه بعدَ ذلك بـ`--set` بشاهدِه — لا بإعادةِ البذر. */
    $govui = array(
        'CL-GOVUI-01' => array('جبهة التسمية — LABEL_CONFORMANCE 306/306 بالتصيير الحي', 'EVIDENCE_CLOSED',
            'P0', 'HIGH', 700, 'tools/govui_label_measure.php — 306/306 · وصفر رابط نقص في 34 دورا'),
        'CL-GOVUI-02' => array('جبهة الحقول — FIELD_CONFORMANCE على الدفتر المسوى', 'IN_PROGRESS',
            'P1', 'HIGH', 690, 'tools/rpr02_field_measure.php — 2541/5420 · 101 سطحا مطابقا كاملا من 359'),
        'CL-GOVUI-03' => array('ارثي يصير بندا بلا قيد مصالحة فردي — حكم CL-NAVR-LEG626 صنفي', 'OPEN',
            'P2', 'MEDIUM', 520, 'govui_outputs/08_UNEXPLAINED_EXTRAS.md — 306 مسارا من 377 صنفا ارثيا'),
        'CL-GOVUI-04' => array('ستة اسطح تجمع حبتين — منها ثلاثة تحمل حكم GRAIN_MISMATCH', 'OPEN',
            'P2', 'MEDIUM', 510, 'tools/govui_metrics.php — GRAIN_CONFORMANCE 374/412 (grain_multi=1 في ستة) · ومصفوفة 18 تحكم على ثلاثة والباقي N/A بسبقه'),
        'CL-GOVUI-05' => array('خطة القوى العاملة سطح تخطيط لم يبن — والموصول تقرير', 'OPEN',
            'P2', 'MEDIUM', 505, 'govui_outputs/07_TARGETS_NOT_BUILT.md — TARGET_BUILD_COVERAGE 412/413'),
    );
    foreach ($govui as $id => $g) {
        seed($db, $id, array(
            'Source_Document' => 'docs/REPAIR01_20260823/orders/GOV_UI_EXEC.txt',
            'Source_ID' => str_replace('CL-', '', $id), 'Domain' => 'GOV-UI-EXEC',
            'Current_Status' => $g[1], 'Priority' => $g[2], 'Severity' => $g[3],
            'Execution_Rank' => $g[4], 'Unlock_Value' => 'MEDIUM', 'Release_Impact' => 'BLOCKING',
            'Evidence_Contract' => $g[5],
            'Current_Snapshot' => $g[0] . ' @ ' . $SNAP,
        ), $SNAP, $NOW, $added, $touched);
    }

    /* ── ⑥ سجلُّ المالك — البنودُ المعروفةُ الآن ──────────────────────── */
    $owner = array(
        'OA-01' => array('q' => 'قوائم تتبع الأصناف الثلاث: Lot · Serial · Expiry', 'type' => 'BUSINESS_CONFIG',
            'src' => 'docs/REPAIR01_20260823/open/DEC-OPEN-15.md', 'gate' => 'بناء المرحلة التاسعة (المشتريات والمخازن DEP-16/17)',
            'blocks' => 'شكل نموذج المخزون (تتبع بالقطعة/الدفعة)'),
        'OA-02' => array('q' => 'من يملك التحقيق؟ (الحوكمة أم المراجعة الداخلية)', 'type' => 'BUSINESS_OWNERSHIP',
            'src' => 'docs/REPAIR01_20260823/open/DEC-OPEN-16.md', 'gate' => 'إغلاق المرحلة 14 (61 متطلبا · 913 حقلا)',
            'blocks' => 'ملكية شاشات التحقيق ودورته'),
        'OA-03' => array('q' => 'من يملك Entity Routing Registry وكتالوج أنواع الطلب؟', 'type' => 'BUSINESS_OWNERSHIP',
            'src' => 'docs/REPAIR01_20260823/open/W135_OWNER_DECISIONS.md §②', 'gate' => 'المرحلة الخامسة عشرة',
            'blocks' => 'بناء سجل التوجيه المؤسسي'),
        'OA-04' => array('q' => 'اعتماد الأسماء المعروضة PENDING_OWNER (63 اسما + 2 مصيرة)', 'type' => 'BUSINESS_NAMING',
            'src' => 'docs/REPAIR01_20260823/open/W135_OWNER_DECISIONS.md §③ · RPR-02 هدف 16', 'gate' => 'RPR-02 هدف 16 = صفر',
            'blocks' => 'CL-R2-T16'),
        'OA-05' => array('q' => 'قرار مقام التايم شيت (يعرض بالتسعة المنصوصة في الأمر §4)', 'type' => 'BUSINESS_POLICY',
            'src' => 'orders/CLOSURE_SYSTEM.txt §الوثيقة الرابعة', 'gate' => 'تسويات الموارد/الرواتب على البيانات الإرثية',
            'blocks' => 'تغيير أي بيانات إرثية للتايم شيت'),
        'OA-06' => array('q' => 'قيم الاعتماد (حدود السلم) عند نافذة الظل', 'type' => 'BUSINESS_CONFIG',
            'src' => 'orders/CLOSURE_SYSTEM.txt §الوثيقة الرابعة', 'gate' => 'Approval Shadow Window',
            'blocks' => 'إنفاذ الاعتماد (لا بناء محركه)'),
        'OA-09' => array('q' => 'انشاء دور نواب الرئيس وربطه بمساحة EX-DVP', 'type' => 'BUSINESS_OWNERSHIP',
            'src' => 'docs/REPAIR01_20260823/GOV_UI_EXEC_CLOSURE.md §④',
            'gate' => 'HUMAN_DEPARTMENT_PASS لمساحة النواب · و§21 اغلاق القيادة',
            'blocks' => 'اثنا عشر هدفا موصولا لا يصير — ومنها ثلاثة اسطح vp_ مبنية وثلاثة اسقاطات'),
        'OA-10' => array('q' => 'انشاء دور الحوكمة والالتزام وربطه بمساحة DEP-08', 'type' => 'BUSINESS_OWNERSHIP',
            'src' => 'docs/REPAIR01_20260823/GOV_UI_EXEC_CLOSURE.md §④',
            'gate' => 'STRUCTURAL_DEPARTMENT_PASS لادارة الحوكمة',
            'blocks' => 'اثنان وثلاثون هدفا لا يقاس ظهورها ولا اسمها'),
        'OA-11' => array('q' => 'اينزع السجل التابع ذو المسار المستقل من السايدبار؟ (§8)', 'type' => 'BUSINESS_POLICY',
            'src' => 'docs/REPAIR01_20260823/govui_outputs/10_OPEN_GOVERNING_CONFLICTS.md §②',
            'gate' => 'UNEXPLAINED_EXTRA_MENU_ITEM ومعيار §8 «لا يظهر الا ما يجب»',
            'blocks' => 'تسعة وثلاثون رابطا حيا — ونزعها تغيير وصول لا يقرره فاحص'),
    );
    foreach ($owner as $id => $o) {
        if (isset($db['owner'][$id])) { continue; }
        $db['owner'][$id] = array(
            'Owner_Action_ID' => $id, 'Decision_Question' => $o['q'], 'Decision_Type' => $o['type'],
            'Affected_Targets' => '—', 'Affected_Domains' => '—', 'What_It_Blocks' => $o['blocks'],
            'Required_By_Gate' => $o['gate'], 'Depends_On' => '—',
            'Options' => 'في وثيقة المصدر', 'Impact_Of_Each_Option' => 'في وثيقة المصدر',
            'Technical_Recommendation' => 'في وثيقة المصدر', 'Owner_Decision' => '',
            'Decision_Date' => '', 'Propagation_Status' => 'PENDING', 'Source' => $o['src'],
        );
        seed($db, 'CL-' . $id, array(
            'Source_Document' => $o['src'], 'Source_ID' => $id, 'Domain' => 'OWNER',
            'Current_Status' => 'BLOCKED_OWNER', 'Blocker_Class' => 'OWNER_DECISION',
            'Priority' => 'P1', 'Required_By_Gate' => $o['gate'],
            'Evidence_Contract' => 'قرار مالك مؤرخ + Propagation_Status=DONE',
            'Next_Action' => 'ينتظر بوابته — ولا يحجب إلا نطاقه',
            'Current_Snapshot' => $o['q'] . ' @ ' . $SNAP,
        ), $SNAP, $NOW, $added, $touched);
    }

    /* ── ⑦ سجلُّ الإرثِ — كونُ المراجعةِ الخمسةُ من نصِّ الأمر ─────────── */
    $legacy = array(
        'LG-OFFLINE'  => array('u' => 'Offline/Field: PWA · Service Worker · IndexedDB · Delta Sync · Conflict handling · Offline write idempotency',
                               'hooks' => 'version numbers · idempotency keys · delta-sync-friendly transactions · conflict resolution slots'),
        'LG-INTEGRATION' => array('u' => 'Integration: Outbox · Retry · DLQ · Compensation · Idempotency · External API contracts',
                               'hooks' => 'ems_business_events جذرا محايدا · مروحة أثر ذرية · مؤشرات مستهلكين'),
        'LG-MULTIENTITY' => array('u' => 'Multi-Entity: دفاتر الكيان · حساباته البنكية · intercompany tagging · consolidation readiness',
                               'hooks' => 'entity_id في الجداول المالية · فصل الرؤية عن الملكية'),
        'LG-REVENUE'  => array('u' => 'Customer Revenue: ENT-03 · Billing · Collection · Debit/Credit Notes · AR aging · Customer statement · الحد الأدنى التعاقدي',
                               'hooks' => 'دورة المطالبات والإيراد القائمة (المروحة تعترف والمستخلص يفوتر)'),
        'LG-OPARCH'   => array('u' => 'Operational Architecture: multi-entity assignment/lending · shared platform capabilities · field/mobile execution · minimum guarantee logic',
                               'hooks' => 'سجل المنصة المبرر · بوابة المستأجر ADR-02'),
    );
    foreach ($legacy as $id => $l) {
        if (isset($db['legacy'][$id])) { continue; }
        $db['legacy'][$id] = array(
            'Requirement_ID' => $id, 'Original_Source' => 'orders/CLOSURE_SYSTEM.txt §الوثيقة الثالثة',
            'Original_Intent' => $l['u'], 'Current_Applicability' => 'UNDER_REVIEW',
            'Current_Architecture_Support' => 'جزئي — يقاس عند المراجعة',
            'Disposition' => '', 'Owner' => 'المالك', 'Target_Release' => '',
            'Architectural_Hooks_Needed_Now' => $l['hooks'], 'Dependencies' => '—',
            'Risk_of_Deferral' => 'يقيم عند الحكم', 'Decision_Reference' => '',
        );
        seed($db, 'CL-' . $id, array(
            'Source_Document' => 'orders/CLOSURE_SYSTEM.txt §الوثيقة الثالثة', 'Source_ID' => $id,
            'Domain' => 'LEGACY', 'Current_Status' => 'OPEN', 'Priority' => 'P2',
            'Evidence_Contract' => 'صف في سجل الإرث بمصير من الأربعة وخطافاته',
            'Next_Action' => 'مراجعة الكون وتسجيل المصير — ولا شيء يختفي بالصمت',
            'Current_Snapshot' => 'بلا مصير بعد @ ' . $SNAP,
        ), $SNAP, $NOW, $added, $touched);
    }

    $db['meta'] = array('snapshot' => $SNAP, 'updated_at' => $NOW,
                        'governing_order' => 'docs/REPAIR01_20260823/orders/CLOSURE_SYSTEM.txt');
    file_put_contents($STORE, json_encode($db, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    printf("✔ بُذر: جديدٌ %d · موجودٌ حُدِّثت لقطتُه %d · الجملة %d بندًا · مالك %d · إرث %d\n",
        $added, $touched, count($db['items']), count($db['owner']), count($db['legacy']));
    exit(0);
}

/* ═══ --set ═══════════════════════════════════════════════════════════════ */
$setId = null; $setStatus = null; $setEv = null; $setNote = null; $setFinal = null;
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('~^--set=(.+)$~', $a, $m))      { $setId = $m[1]; }
    if (preg_match('~^--status=(.+)$~', $a, $m))   { $setStatus = $m[1]; }
    if (preg_match('~^--evidence=(.+)$~', $a, $m)) { $setEv = $m[1]; }
    if (preg_match('~^--note=(.+)$~', $a, $m))     { $setNote = $m[1]; }
    if (preg_match('~^--final=(.+)$~', $a, $m))    { $setFinal = $m[1]; }
}
if ($setId !== null) {
    if (!isset($db['items'][$setId])) { exit("⛔ لا بندَ بهذا المعرِّف: $setId\n"); }
    if ($setStatus !== null) {
        if (!in_array($setStatus, $VALID_STATUS, true)) { exit("⛔ حالةٌ خارجَ العشرِ المسموحة: $setStatus\n"); }
        /* ⛔ لا تتغيّر حالةٌ بلا Evidence_Snapshot_ID */
        if ($setEv === null) { exit("⛔ لا تتغيّر حالةُ بندٍ بلا --evidence=<المرجع> (قاعدة §٢·٤)\n"); }
        $db['items'][$setId]['Current_Status'] = $setStatus;
        $db['items'][$setId]['Status_Changed_At'] = $NOW . ' @ ' . $SNAP;
        if ($setStatus === 'EVIDENCE_CLOSED') { $db['items'][$setId]['Final_Disposition'] = 'EVIDENCE_CLOSED'; }
    }
    if ($setFinal !== null) {
        if (!in_array($setFinal, $VALID_FINAL, true)) { exit("⛔ مصيرٌ خارجَ الأربعة: $setFinal\n"); }
        $db['items'][$setId]['Final_Disposition'] = $setFinal;
    }
    if ($setEv !== null)   { $db['items'][$setId]['Last_Evidence_Ref'] = $setEv . ' @ ' . $SNAP; }
    if ($setNote !== null) { $db['items'][$setId]['Current_Snapshot'] = $setNote . ' @ ' . $SNAP; }
    $db['items'][$setId]['Last_Updated_Snapshot'] = $SNAP;
    $db['meta']['snapshot'] = $SNAP; $db['meta']['updated_at'] = $NOW;
    file_put_contents($STORE, json_encode($db, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    printf("✔ %s ⇐ %s%s\n", $setId, $setStatus ?? '(لقطة)', $setEv ? ' · دليل: ' . $setEv : '');
    exit(0);
}

/* ═══ --sprint — خوارزميّةُ الاستخراج §٢·٥ ═══════════════════════════════ */
if (in_array('--sprint', $argv, true)) {
    $score = array();
    $sevW = array('CRITICAL' => 40, 'HIGH' => 25, 'MEDIUM' => 10, 'LOW' => 3);
    $unlW = array('CRITICAL_UNLOCK' => 30, 'HIGH' => 18, 'MEDIUM' => 8, 'LOW' => 2);
    $effW = array('S' => 15, 'M' => 8, 'L' => 3, 'XL' => 1);
    foreach ($db['items'] as $id => $r) {
        if (in_array($r['Current_Status'], array('EVIDENCE_CLOSED'), true)) { continue; }
        if ($r['Final_Disposition'] !== '' && $r['Final_Disposition'] !== null) { continue; }
        if (strpos($r['Current_Status'], 'BLOCKED_') === 0) { continue; } /* المحجوبُ لا يدخل — بحاجزِه المسمّى */
        $s  = $sevW[$r['Severity']] ?? 5;
        $s += $r['Release_Impact'] === 'BLOCKING' ? 25 : 0;
        $s += $unlW[$r['Unlock_Value']] ?? 2;
        $s += (int) $r['Dependency_Fanout'] * 3;
        $s += $effW[$r['Estimated_Effort']] ?? 5;
        $s += ((int) $r['Execution_Rank']) / 100;
        $score[$id] = $s;
    }
    arsort($score);
    echo "═══ ترشيحُ Sprint التالي — أعلى 8 بالخوارزمية (Severity·Release·Unlock·Fanout·Effort) ═══\n";
    $n = 0;
    foreach ($score as $id => $s) {
        if (++$n > 8) { break; }
        $r = $db['items'][$id];
        printf("  %d. %-18s %5.1f  [%s·%s] %s\n", $n, $id, $s, $r['Priority'], $r['Current_Status'],
            mb_substr((string) $r['Current_Snapshot'], 0, 70));
    }
    exit(0);
}

/* ═══ --report — الإسقاطاتُ الثلاثة ═══════════════════════════════════════ */
$counts = array();
foreach ($db['items'] as $r) { $counts[$r['Current_Status']] = ($counts[$r['Current_Status']] ?? 0) + 1; }
ksort($counts);

if (in_array('--report', $argv, true)) {
    /* ① السجلُّ الجامع */
    $md   = array();
    $md[] = '# MASTER FINAL CLOSURE REGISTER — السجلُّ الجامعُ للإغلاقِ النهائيّ';
    $md[] = '';
    $md[] = '> **المخزنُ الحاكم:** `registers/MASTER_FINAL_CLOSURE_REGISTER.json` — وهذا إسقاطُه.';
    $md[] = '> **اللقطة:** `' . $db['meta']['snapshot'] . '` · **حُدِّث:** ' . $db['meta']['updated_at'];
    $md[] = '> **المصمَّمُ الحاكم:** `' . $db['meta']['governing_order'] . '`';
    $md[] = '';
    $md[] = '## مقامُ الحالات — ⛔ ولا تُجمَع في نسبةٍ واحدة';
    $md[] = '';
    $md[] = '| الحالة | العدد |';
    $md[] = '|---|---|';
    foreach ($counts as $st => $n) { $md[] = "| `$st` | $n |"; }
    $md[] = '| **الجملة** | **' . count($db['items']) . '** |';
    $md[] = '';
    $md[] = '## البنودُ غيرُ المغلقة — بحاجزِها وفعلِها التالي';
    $md[] = '';
    $md[] = '| Closure_ID | الحالة | الأولوية | الحاجز | المصدر | اللقطةُ الحالية | الفعلُ التالي |';
    $md[] = '|---|---|---|---|---|---|---|';
    $rows = $db['items'];
    uasort($rows, function ($a, $b) {
        $p = strcmp($a['Priority'], $b['Priority']);
        return $p !== 0 ? $p : ($b['Execution_Rank'] <=> $a['Execution_Rank']);
    });
    foreach ($rows as $id => $r) {
        if ($r['Current_Status'] === 'EVIDENCE_CLOSED') { continue; }
        $md[] = sprintf('| `%s` | `%s` | %s | %s | %s | %s | %s |',
            $id, $r['Current_Status'], $r['Priority'], $r['Blocker_Class'],
            str_replace('|', '·', (string) $r['Source_Document']),
            str_replace('|', '·', mb_substr((string) $r['Current_Snapshot'], 0, 90)),
            str_replace('|', '·', mb_substr((string) $r['Next_Action'], 0, 70)));
    }
    $md[] = '';
    $md[] = '## المغلقُ بالدليل';
    $md[] = '';
    $md[] = '| Closure_ID | الدليل |';
    $md[] = '|---|---|';
    foreach ($rows as $id => $r) {
        if ($r['Current_Status'] !== 'EVIDENCE_CLOSED') { continue; }
        $md[] = sprintf('| `%s` | %s |', $id, str_replace('|', '·', (string) $r['Last_Evidence_Ref']));
    }
    file_put_contents($REG . '/MASTER_FINAL_CLOSURE_REGISTER.md', implode("\n", $md) . "\n");

    /* ② سجلُّ المالك */
    $md   = array('# OWNER ACTION REGISTER — سجلُّ أعمالِ المالك', '',
        '> ⛔ لا يدخله إلّا قرارُ أعمالٍ/سياسةٍ/Config حقيقيٌّ لا تحسمه الوثائقُ ولا الهندسة.',
        '> **اللقطة:** `' . $db['meta']['snapshot'] . '`', '',
        '| ID | السؤال | النوع | يحجب | بوّابتُه | القرار | الحال |', '|---|---|---|---|---|---|---|');
    foreach ($db['owner'] as $id => $o) {
        $md[] = sprintf('| `%s` | %s | %s | %s | %s | %s | %s |', $id,
            str_replace('|', '·', $o['Decision_Question']), $o['Decision_Type'],
            str_replace('|', '·', $o['What_It_Blocks']), str_replace('|', '·', $o['Required_By_Gate']),
            $o['Owner_Decision'] ?: '⏳', $o['Propagation_Status']);
    }
    $md[] = '';
    $md[] = '**Required_By_Gate لا تاريخٌ مخترَع** — والقرارُ يحجب نطاقَه فقط.';
    file_put_contents($REG . '/OWNER_ACTION_REGISTER.md', implode("\n", $md) . "\n");

    /* ③ سجلُّ الإرث */
    $md   = array('# LEGACY REQUIREMENT DISPOSITION REGISTER — سجلُّ مصيرِ الالتزاماتِ القديمة', '',
        '> القبول: `LEGACY_REQUIREMENT_WITHOUT_DISPOSITION = 0` — ولا شيءَ يختفي بالصمت.',
        '> **اللقطة:** `' . $db['meta']['snapshot'] . '`', '',
        '| ID | الكون | المصير | الخطّافاتُ المعماريّةُ الآن | مرجعُ القرار |', '|---|---|---|---|---|');
    $noDisp = 0;
    foreach ($db['legacy'] as $id => $l) {
        if ($l['Disposition'] === '') { $noDisp++; }
        $md[] = sprintf('| `%s` | %s | %s | %s | %s |', $id,
            str_replace('|', '·', mb_substr($l['Original_Intent'], 0, 100)),
            $l['Disposition'] ?: '⏳ بلا مصير', str_replace('|', '·', $l['Architectural_Hooks_Needed_Now']),
            $l['Decision_Reference'] ?: '—');
    }
    $md[] = '';
    $md[] = 'LEGACY_REQUIREMENT_WITHOUT_DISPOSITION = **' . $noDisp . '**';
    file_put_contents($REG . '/LEGACY_DISPOSITION_REGISTER.md', implode("\n", $md) . "\n");

    echo "✔ أُسقطت الوثائقُ الثلاثُ في $REG\n";
}

/* ═══ الموجز (دائمًا) ═════════════════════════════════════════════════════ */
printf("السجلُّ الجامع @ %s — %d بندًا:\n", $db['meta']['snapshot'] ?? '؟', count($db['items']));
foreach ($counts as $st => $n) { printf("  %-28s %d\n", $st, $n); }
printf("مالك: %d (بلا قرار: %d) · إرث: %d (بلا مصير: %d)\n",
    count($db['owner']),
    count(array_filter($db['owner'], fn($o) => $o['Owner_Decision'] === '')),
    count($db['legacy']),
    count(array_filter($db['legacy'], fn($l) => $l['Disposition'] === '')));
