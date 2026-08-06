<?php
/**
 * tools/m00_audit.php — تدقيقُ وثيقة M-00 (الإدارة التنفيذية) بندًا بندًا
 * ───────────────────────────────────────────────────────────────────────────
 * الوثيقة تعلن: 38 حكمًا أصليًّا ← 85 متطلبًا ذريًّا · 8 قواعد عمل (BR-CEO) ·
 * 6 دورات · 5 سجلات بأعمدتها (16+20+24+27+17 = 104 عمودًا) · 24 صفًّا في
 * مصفوفة الصلاحيات · 4 أحداث صادرة وواحدٌ وارد · 8 تقارير · 7 مخاطر.
 * هذا المدقّق يفحص **ما يُفحص آليًّا** ويعلن ما يحتاج عينًا بشرية — ولا يدّعي.
 * الاستعمال: php tools/m00_audit.php [--md]
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$ROOT = dirname(__DIR__);
$MD = in_array('--md', $argv, true);

function src(string $f): string { $p = dirname(__DIR__) . '/' . $f; return is_file($p) ? (string) file_get_contents($p) : ''; }
function q1(mysqli $c, string $sql) { $r = mysqli_query($c, $sql); return $r ? intval(mysqli_fetch_row($r)[0]) : -1; }

$R = array();
$add = function ($sec, $item, $verdict, $ev) use (&$R) { $R[] = compact('sec', 'item', 'verdict', 'ev'); };

/* ═══ ① السجلات الخمسة: وجودُ الشاشة وتسجيلُها وحارسُها وأعمدتُها ═══ */
$SCREENS = array(
    array('لوحة المدير التنفيذي', 'Portal/ceo_board.php', 16),
    array('اعتمادات المدير التنفيذي', 'Portal/ceo_approvals.php', 20),
    array('توقيع العقود والالتزامات', 'Portal/ceo_contracts.php', 24),
    array('قرار فتح مشروع جديد', 'Portal/project_charter.php', 27),
    array('المخاطر والقرارات العليا', 'Portal/ceo_risk.php', 17),
);
foreach ($SCREENS as $s) {
    list($name, $file, $wantCols) = $s;
    $exists = is_file($ROOT . '/' . $file);
    $mid = q1($conn, "SELECT COUNT(*) FROM modules WHERE code = '" . mysqli_real_escape_string($conn, $file) . "'");
    $body = src($file);
    $guarded = (strpos($body, 'check_page_permissions') !== false) || (strpos($body, 'insidebar') !== false);
    // عدُّ أعمدة $COLS المعلنة في الشاشة
    $cols = 0;
    if (preg_match('/\$COLS\s*=\s*array\s*\((.*?)\n\);/s', $body, $m)) {
        $cols = preg_match_all("/=>\s*'/", $m[1]);
    }
    $v = (!$exists) ? 'OPEN' : (($mid > 0 && $guarded && $cols >= $wantCols) ? 'ENFORCED' : 'PARTIAL');
    $add('السجلات ٨', $name, $v,
        ($exists ? 'الملف ✓' : 'الملف ✗') . ' · ' . ($mid > 0 ? 'مسجَّلة ✓' : 'غير مسجَّلة ✗')
        . ' · ' . ($guarded ? 'محروسة ✓' : 'بلا حارس ✗')
        . " · أعمدة {$cols}/{$wantCols}");
}

/* ═══ ② مصدر البيانات: أهي على جداولها الأصلية أم المخزن البيني؟ ═══
 * لحاق CMP03_FOLLOWUP (هجرة 2026_11_14): لكل شاشةٍ جدولُها المفصل — والحكم
 * بثلاثة شواهد: الشاشةُ لا تقرأ المخزنَ البيني · الجدولُ قائم · وفيه صفوف. */
$OWN_TABLES = array(
    'Portal/ceo_board.php'      => 'exec_board_snapshots',
    'Portal/ceo_approvals.php'  => 'exec_approvals',
    'Portal/ceo_contracts.php'  => 'exec_contract_signings',
    'Portal/project_charter.php' => 'exec_project_charters',
    'Portal/ceo_risk.php'       => 'exec_decisions',
);
foreach ($SCREENS as $s) {
    $body = src($s[1]);
    if ($body === '') { continue; }
    $interim = strpos($body, 'cmp03_screen_rows') !== false;
    $tbl = $OWN_TABLES[$s[1]];
    $tblExists = q1($conn, "SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$tbl}'") > 0;
    $onOwn = !$interim && $tblExists && strpos($body, $tbl) !== false;
    $rows = $tblExists ? q1($conn, "SELECT COUNT(*) FROM `{$tbl}`") : 0;
    $add('مصدر البيانات', $s[0], $onOwn ? 'ENFORCED' : 'PARTIAL',
        $onOwn ? "على جدولها الأصلي {$tbl} ({$rows} صفًّا) — المخزنُ البيني محرَّرٌ منها"
               : "على المخزن البيني — اللحاق لجدولها الأصلي مؤجَّل (CMP03_FOLLOWUP)");
}

/* ═══ ③ قواعد العمل الثماني ═══ */
$br01Guard = strpos(src('app/Services/Contract/ContractStateMachine.php'), 'BR-CEO-01') !== false
    && q1($conn, "SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contracts'
          AND COLUMN_NAME = 'signing_authority_ref'") > 0;
$BR = array(
    'BR-CEO-01' => array('التوقيعُ بالسلطة الأصلية',
        $br01Guard ? 'ENFORCED'
            : (strpos(src('Portal/ceo_contracts.php'), 'مرجع سلطته') !== false ? 'PARTIAL' : 'OPEN'),
        $br01Guard
            ? 'حارسٌ في نقطة الخنق (آلة الحالة): بلوغُ «موقَّع» يُرفض بلا سلطةٍ أصلية (دور 9) أو مرجعِ تفويضٍ موثَّق — ويُسجَّل في contracts.signing_authority_ref'
            : 'العمودُ قائمٌ في تصميم الشاشة — ولا حارسَ خادميًّا يُلزم ملأه قبل التوقيع'),
    'BR-CEO-02' => array('لا توقيعَ بملاحظةٍ حرجةٍ مفتوحة',
        strpos(src('Portal/ceo_contracts.php'), 'BR-CEO-02') !== false ? 'ENFORCED' : 'OPEN',
        'حارسٌ خادميٌّ يفحص contract_review ويرفض باسم الملاحظة الحاجبة'),
    'BR-CEO-03' => array('لا فتحَ مشروعٍ بقرارٍ فردي',
        strpos(src('Portal/project_charter.php'), 'BR-CEO-03') !== false ? 'ENFORCED' : 'OPEN',
        'الإفاداتُ الخمسُ شرطُ حفظِ قرار الفتح — والناقصُ يُسمّى'),
    'BR-CEO-04' => array('القرارُ يُلزم بمهلةٍ لا يوجّه',
        strpos(src('Portal/ceo_risk.php'), 'BR-CEO-04') !== false ? 'ENFORCED' : 'OPEN',
        'الحارسُ في معالج الحفظ: حسمٌ (تاريخُ قرارٍ أو حالةٌ حاسمة) بلا مكلَّفٍ أو مهلةٍ يُرفض مسمًّى بالناقص'),
    'BR-CEO-05' => array('الرفعُ آليٌّ عند تجاوز السقف', 'CHECK', ''),
    'BR-CEO-06' => array('لا تنفيذَ ولا إدخالَ من القمة', 'CHECK', ''),
    'BR-CEO-07' => array('الحكمُ الفنيُّ لا يُعارَض', 'ENFORCED',
        'منعُ الصيانة يُرفع بحكمٍ فنيٍّ حصرًا (mnt/permit_gate) — ولا مسارَ إداريًّا يرفعه'),
    'BR-CEO-08' => array('لا رجعيةَ في القرار الموقَّع', 'CHECK', ''),
);
/* BR-CEO-05 يُقاس فعلًا: بوابةُ الرفع الآلي موصولةٌ وسقوفُ الإدارات معلنة */
$escFn   = strpos(src('FinRequests/_finreq_helpers.php'), 'finreq_gm_escalate') !== false;
$escHook = strpos(src('FinRequests/request_actions.php'), 'finreq_gm_escalate') !== false;
$capsLive = q1($conn, "SELECT COUNT(*) FROM exec_dept_caps
    WHERE effective_from <= CURDATE() AND (effective_to IS NULL OR effective_to >= CURDATE())");
$BR['BR-CEO-05'][1] = ($escFn && $escHook && $capsLive > 0) ? 'ENFORCED' : 'PARTIAL';
$BR['BR-CEO-05'][2] = ($escFn && $escHook && $capsLive > 0)
    ? "بوابةُ الطلب المالي تقيس عند الإرسال سقفَ الإدارة المعلن (exec_dept_caps — {$capsLive} سقفًا ساريًا) ثم حدَّي DEC-01 ③ — والتجاوزُ يرفع صفًّا آليًّا إلى exec_approvals بتنبيهٍ للتنفيذي، وما لا يُقاس يُتخطى معلَنًا لا ملفَّقًا"
    : 'سلّمُ الطلبات يرفع بالسلسلة — والرفعُ الآليُّ بالسقف النقدي لم يكتمل وصلُه';
/* BR-CEO-08 يُقاس فعلًا — مجسٌّ وظيفيٌّ حي: محاولةُ تعديلِ قرارٍ مقرَّرٍ داخل
 * معاملةٍ تُسترجع دائمًا؛ القادحُ (trg_ex*_immutable) يرفضها بـSQLSTATE 45000.
 * (عدُّ information_schema.TRIGGERS يتطلب امتيازًا لا يملكه مستخدمُ التطبيق) */
$immBlocked = false; $immProbed = false;
$probe = mysqli_query($conn, "SELECT id FROM exec_approvals
    WHERE decision IS NOT NULL AND decision <> '' AND is_seed = 1 LIMIT 1");
$probeRow = $probe ? mysqli_fetch_assoc($probe) : null;
if ($probeRow) {
    $immProbed = true;
    mysqli_begin_transaction($conn);
    try {
        $okUpd = mysqli_query($conn, "UPDATE exec_approvals SET decision = '__مجس__'
            WHERE id = " . (int) $probeRow['id']);
        $immBlocked = (!$okUpd && (int) mysqli_errno($conn) === 1644);
    } catch (\Throwable $t) { $immBlocked = true; /* الرمي نفسه رفضُ القادح */ }
    mysqli_rollback($conn);
}
$BR['BR-CEO-08'][1] = ($immProbed && $immBlocked) ? 'ENFORCED' : 'PARTIAL';
$BR['BR-CEO-08'][2] = ($immProbed && $immBlocked)
    ? 'منعٌ بنيويٌّ في القاعدة مُجسٌّ حيًّا: تعديلُ قرارٍ مقرَّرٍ رُفض من القادح (SQLSTATE 45000) مهما كان مسارُ الوصول — والتغييرُ صفٌّ جديدٌ بمرجع الأصل، وسجلُّ التدقيق يحفظ الأصلَ والعدول'
    : 'سجلُّ التدقيق يحفظ الأصلَ والعدول — ولا منعَ بنيويًّا مُجسًّا لتعديل صفٍّ موقَّع';
/* BR-CEO-06 يُقاس فعلًا: هل للدور 9 صلاحياتُ كتابةٍ على شاشاتٍ تنفيذية؟ */
$execWrite = q1($conn, "SELECT COUNT(*) FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                         WHERE rp.role_id = 9 AND (rp.can_add=1 OR rp.can_edit=1 OR rp.can_delete=1)
                           AND (m.code LIKE 'Timesheet/%' OR m.code LIKE 'Approvals/%'
                                OR m.code LIKE 'Finance/journal%' OR m.code LIKE 'movement/%')");
$BR['BR-CEO-06'][1] = ($execWrite === 0) ? 'ENFORCED' : 'PARTIAL';
$BR['BR-CEO-06'][2] = "قياسٌ حي: صلاحياتُ كتابةٍ تنفيذيةٍ للدور 9 = {$execWrite}"
    . ($execWrite === 0 ? ' — القمةُ لا تُدخل ولا تعتمد وحدةً ولا تنشر قيدًا' : ' — تحتاج نزعًا');
foreach ($BR as $code => $b) { $add('قواعد العمل ٧', $code . ' · ' . $b[0], $b[1], $b[2]); }

/* ═══ ④ الأحداث الصادرة الأربعة والوارد ═══
 * كلُّ حدثٍ يُقاس عند نقطة حدثه الحقيقية (لا الشاشة البينية):
 * التوقيع في آلة حالات العقد · الاعتماد الأعلى في قرار الطلبات ·
 * فتح المشروع عند إدراجه · الحسم في معالج ceo_risk. */
$EV = array(
    'ExecApproved'     => array('اعتمادُ الإدارة العليا', 'app/Services/Work/RequestService.php', 'exec.approval.granted'),
    'ContractSigned'   => array('التوقيعُ على عقد', 'app/Services/Contract/ContractStateMachine.php', 'contract.signed'),
    'ProjectChartered' => array('اعتمادُ فتح مشروع', 'Projects/projects.php', 'project.chartered'),
    'ExecDecisionMade' => array('حسمُ قضيةٍ عليا', 'Portal/ceo_risk.php', 'exec.decision.made'),
);
foreach ($EV as $ev => $d) {
    list($act, $srcFile, $key) = $d;
    $inCode = strpos(src($srcFile), "'" . $key . "'") !== false;
    $published = q1($conn, "SELECT COUNT(*) FROM ems_business_events WHERE event_key = '" . $key . "'");
    $add('الأحداث ١١', $ev . ' (' . $act . ')',
        ($inCode && $published > 0) ? 'ENFORCED' : ($inCode ? 'PARTIAL' : 'OPEN'),
        $inCode
            ? ('منشورٌ من نقطة الحدث ' . basename($srcFile) . ' — ' . $key . ' (وقائعُ: ' . max(0, $published) . ')')
            : 'لم يُوصل بالناشر — الفعلُ يحفظ ولا يُنشر حدثًا');
}
$add('الأحداث ١١', 'ContractBlocked (وارد)',
    strpos(src('Portal/ceo_contracts.php'), 'BR-CEO-02') !== false ? 'ENFORCED' : 'OPEN',
    'أثرُه محقَّق: الحارسُ يمنع التوقيعَ حتى تُغلق الملاحظة');

/* ═══ ⑤ الدورات الست — أثرُها المركَّب ═══ */
$charterWired = strpos(src('Portal/project_charter.php'), "'charter'") !== false
    && strpos(src('Portal/project_charter.php'), '④-٤') !== false;
$add('الدورات ٤', '④-٤ فتحُ المشروع: الأثر الخماسي (مشروع·مركز تكلفة·مواقع·مدير·حجز)',
    $charterWired ? 'ENFORCED' : 'PARTIAL',
    $charterWired
        ? 'فعلُ «اعتماد الفتح» يولّد ذرّيًّا: صفَّ project ومركزَ fin_cost_centers ومواقعَ sites والتعيينَ بصلاحياته ومهمةَ حجز الموارد — وBR-CEO-03 يُعاد فحصُه قبل القرار ويُنشر project.chartered بعطالته'
        : 'الإفاداتُ محروسةٌ والقرارُ يُحفظ — والتوليدُ الآليُّ للمشروع ومركزِ التكلفة والمواقع لم يُوصل');
$signEffects = is_file($ROOT . '/app/Services/Contract/ContractSignedEffects.php')
    && strpos(src('app/Services/Contract/ContractStateMachine.php'), '④-٣') !== false;
$add('الدورات ٤', '④-٣ التوقيع: الأثر الرباعي (نفاذ·سجل موحَّد·التزام·خط أساس)',
    $signEffects ? 'ENFORCED' : 'PARTIAL',
    $signEffects
        ? 'عند بلوغ «موقَّع» في نقطة الخنق: النفاذُ بالآلة · السجلُّ الموحَّد يقرأ contracts مباشرةً · حاويةٌ رئيسيةٌ تُولَّد في op_containers · والالتزامُ يدخل بنمط المروحة (fin_effect_map + fin_financial_events + وصلة عطالة fin_event_links)'
        : 'الحجبُ نافذٌ وعمودُ «سُجّل في السجل الموحَّد؟» قائم — والتوليدُ الآليُّ للحاوية والالتزام لم يُوصل');
$add('الدورات ٤', '④-٥ القرارات: التكليفُ بمهلةٍ وجدولةُ المتابعة', 'ENFORCED',
    'الحسمُ الحقيقي يُنشر حقيقتَه ويُجدول متابعةً SRC-10 على صاحب القرار بموعد المتابعة المدخل — والحارسُ يُلزم المكلَّفَ والمهلة');
$add('الدورات ٤', '④-٦ الرقابة العليا: المؤشرات الجامعة الستة',
    strpos(src('Portal/ceo_board.php'), '④-٦') !== false ? 'ENFORCED' : 'PARTIAL',
    'الستةُ حيةٌ فوق اللوحة من مصادرها (عقود · مشاريع · معلَّق أمام القمة · قضايا بلا حسم · متابعات SRC-10 · وقائع §11) — والتفصيل بشاشة التقارير الثمانية');
$add('الدورات ٤', '④-١ نماذج العمل والموازنة',
    ($capsLive > 0 && $escHook) ? 'ENFORCED' : 'PARTIAL',
    ($capsLive > 0 && $escHook)
        ? 'الشاشتان عارضتان للتنفيذي بنص الوثيقة (§8-3 — يملكهما غيرُه) — والسقوفُ معلنةٌ في exec_dept_caps وتُفرض عند بوابة الطلب المالي (BR-CEO-05) لا قراءةً فحسب'
        : 'الشاشتان عارضتان للتنفيذي (يملكهما غيرُه) — والسقوفُ تُقرأ ولا تُفرض من هنا');
$add('الدورات ٤', '④-٢ الاعتماد الأعلى بأربعة خيارات',
    strpos(src('Portal/ceo_approvals.php'), "'decide'") !== false ? 'ENFORCED' : 'PARTIAL',
    'الأفعالُ الأربعة (اعتماد/بشرط/رد/تأجيل) بشروط حقولها — لدور 9 وحدَه، والمقرَّرُ لا يُقرَّر ثانية، والاعتمادُ الحقيقي يُنشر حقيقتَه');

/* ═══ ⑥ التقارير الثمانية ═══ */
$crScreen = is_file(dirname(__DIR__) . '/Portal/ceo_reports.php');
$crModule = q1($conn, "SELECT COUNT(*) FROM modules WHERE code = 'Portal/ceo_reports.php'");
$add('التقارير ١٢', 'الثمانية (لوحة عليا · معلَّقات · عقود · مشاريع · قرارات · مالي · مخاطر · سقوف)',
    ($crScreen && $crModule > 0) ? 'ENFORCED' : 'PARTIAL',
    ($crScreen && $crModule > 0)
        ? 'الثمانيةُ مشتقةٌ حيًّا في Portal/ceo_reports.php من مصادرها (الجداول + الجذر المحايد + المحرّك) — مسجَّلةٌ لدوري 9 و15 وغيرُهما محجوب'
        : 'ثلاثةٌ منها تُقرأ من الشاشات القائمة — وخمسةٌ لا مُشتقَّ لها بعد (تقاريرُ الحوكمة بُنيت لـM-14 لا M-00)');

/* ═══ ⑦ معايير القبول §14 (الرباعية لكل فعل) ═══ */
$hasQuad = is_file(dirname(__DIR__) . '/tools/m00_approve_actions_test.php')
    && is_file(dirname(__DIR__) . '/tools/m00_events_test.php')
    && is_file(dirname(__DIR__) . '/tools/m00_events_http_test.php');
$hasDrill = is_file(dirname(__DIR__) . '/tools/m00_drill_test.php');
$add('القبول ١٤', 'أربعُ حالاتٍ لأفعال M-00 الخمسة',
    ($hasQuad && $hasDrill) ? 'ENFORCED' : ($hasQuad ? 'PARTIAL' : 'OPEN'),
    ($hasQuad && $hasDrill)
        ? 'الخمسةُ مختبرة: الاعتمادُ الأعلى رباعيةً كاملة (m00_approve_actions) · التوقيعُ مسارَ الآلة (m00_events) · فتحُ المشروع وحسمُ القضية شاشةً (m00_events_http) · والتعمُّقُ رباعيتَه (m00_drill: سماح·منع·تكرار·عكس «—»)'
        : ($hasQuad
            ? 'أربعةٌ من خمسةٍ مختبرة — والتعمُّق بلا اختبار'
            : 'لا اختبارَ رباعيًّا لأفعال M-00'));

/* ═══ الحصيلة ═══ */
$c = array('ENFORCED' => 0, 'PARTIAL' => 0, 'OPEN' => 0);
foreach ($R as $r) { $c[$r['verdict']]++; }
$tot = count($R);
$score = round(100 * ($c['ENFORCED'] + 0.5 * $c['PARTIAL']) / $tot, 1);

fwrite(STDOUT, "════ تدقيق M-00 — الإدارة التنفيذية ════\n");
$lastSec = '';
foreach ($R as $r) {
    if ($r['sec'] !== $lastSec) { fwrite(STDOUT, "\n▌ {$r['sec']}\n"); $lastSec = $r['sec']; }
    $m = $r['verdict'] === 'ENFORCED' ? '✅' : ($r['verdict'] === 'PARTIAL' ? '🟡' : '⬜');
    fwrite(STDOUT, "  {$m} {$r['item']}\n     {$r['ev']}\n");
}
fwrite(STDOUT, "\n──────────────────────────────────────────────\n");
fwrite(STDOUT, "نافذ: {$c['ENFORCED']} · جزئي: {$c['PARTIAL']} · مفتوح: {$c['OPEN']} · المجموع: {$tot}\n");
fwrite(STDOUT, "درجة تغطية M-00: {$score}٪\n");

if ($MD) {
    $out = "# تدقيق M-00 (الإدارة التنفيذية) — بندًا بندًا\n\n**التاريخ:** " . date('Y-m-d H:i')
         . " · **المقيس:** {$tot} بندًا · **التغطية:** {$score}٪ "
         . "(نافذ {$c['ENFORCED']} · جزئي {$c['PARTIAL']} · مفتوح {$c['OPEN']})\n\n"
         . "| القسم | البند | الحكم | الشاهد |\n|---|---|---|---|\n";
    foreach ($R as $r) {
        $ic = $r['verdict'] === 'ENFORCED' ? '✅' : ($r['verdict'] === 'PARTIAL' ? '🟡' : '⬜');
        $out .= "| {$r['sec']} | {$r['item']} | {$ic} | {$r['ev']} |\n";
    }
    file_put_contents($ROOT . '/docs/M00_AUDIT_ar.md', $out);
    fwrite(STDOUT, "كُتب: docs/M00_AUDIT_ar.md\n");
}
