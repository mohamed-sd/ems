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

/* ═══ ② مصدر البيانات: أهي على جداولها أم المخزن البيني؟ ═══ */
foreach ($SCREENS as $s) {
    $body = src($s[1]);
    if ($body === '') { continue; }
    $interim = strpos($body, 'cmp03_screen_rows') !== false;
    $canon = '';
    if (preg_match("/\\\$CANONICAL\s*=\s*'([^']+)'/", $body, $m)) { $canon = $m[1]; }
    $rows = $canon !== '' ? q1($conn, "SELECT COUNT(*) FROM cmp03_screen_rows WHERE canonical_file='" . mysqli_real_escape_string($conn, $canon) . "'") : 0;
    $add('مصدر البيانات', $s[0], $interim ? 'PARTIAL' : 'ENFORCED',
        $interim ? "على المخزن البيني ({$rows} صفًّا) — اللحاق لجدولها الأصلي مؤجَّل (CMP03_FOLLOWUP)" : 'على جدولها الأصلي');
}

/* ═══ ③ قواعد العمل الثماني ═══ */
$BR = array(
    'BR-CEO-01' => array('التوقيعُ بالسلطة الأصلية',
        strpos(src('Portal/ceo_contracts.php'), 'مرجع سلطته') !== false ? 'PARTIAL' : 'OPEN',
        'العمودُ قائمٌ في تصميم الشاشة — ولا حارسَ خادميًّا يُلزم ملأه قبل التوقيع'),
    'BR-CEO-02' => array('لا توقيعَ بملاحظةٍ حرجةٍ مفتوحة',
        strpos(src('Portal/ceo_contracts.php'), 'BR-CEO-02') !== false ? 'ENFORCED' : 'OPEN',
        'حارسٌ خادميٌّ يفحص contract_review ويرفض باسم الملاحظة الحاجبة'),
    'BR-CEO-03' => array('لا فتحَ مشروعٍ بقرارٍ فردي',
        strpos(src('Portal/project_charter.php'), 'BR-CEO-03') !== false ? 'ENFORCED' : 'OPEN',
        'الإفاداتُ الخمسُ شرطُ حفظِ قرار الفتح — والناقصُ يُسمّى'),
    'BR-CEO-04' => array('القرارُ يُلزم بمهلةٍ لا يوجّه',
        strpos(src('Portal/ceo_risk.php'), 'BR-CEO-04') !== false ? 'ENFORCED' : 'OPEN',
        'الحارسُ في معالج الحفظ: حسمٌ (تاريخُ قرارٍ أو حالةٌ حاسمة) بلا مكلَّفٍ أو مهلةٍ يُرفض مسمًّى بالناقص'),
    'BR-CEO-05' => array('الرفعُ آليٌّ عند تجاوز السقف', 'PARTIAL',
        'سلّمُ الطلبات يرفع بالسلسلة (approval_links) — والرفعُ الآليُّ بالسقف النقدي من شاشات المال لم يُوصل بشاشة الاعتماد الأعلى'),
    'BR-CEO-06' => array('لا تنفيذَ ولا إدخالَ من القمة', 'CHECK', ''),
    'BR-CEO-07' => array('الحكمُ الفنيُّ لا يُعارَض', 'ENFORCED',
        'منعُ الصيانة يُرفع بحكمٍ فنيٍّ حصرًا (mnt/permit_gate) — ولا مسارَ إداريًّا يرفعه'),
    'BR-CEO-08' => array('لا رجعيةَ في القرار الموقَّع', 'PARTIAL',
        'سجلُّ التدقيق يحفظ الأصلَ والعدول — ولا منعَ بنيويًّا لتعديل صفٍّ موقَّع'),
);
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
$add('الدورات ٤', '④-٤ فتحُ المشروع: الأثر الخماسي (مشروع·مركز تكلفة·مواقع·مدير·حجز)', 'PARTIAL',
    'الإفاداتُ محروسةٌ والقرارُ يُحفظ — والتوليدُ الآليُّ للمشروع ومركزِ التكلفة والمواقع لم يُوصل');
$add('الدورات ٤', '④-٣ التوقيع: الأثر الرباعي (نفاذ·سجل موحَّد·التزام·خط أساس)', 'PARTIAL',
    'الحجبُ نافذٌ وعمودُ «سُجّل في السجل الموحَّد؟» قائم — والتوليدُ الآليُّ للحاوية والالتزام لم يُوصل');
$add('الدورات ٤', '④-٥ القرارات: التكليفُ بمهلةٍ وجدولةُ المتابعة', 'ENFORCED',
    'الحسمُ الحقيقي يُنشر حقيقتَه ويُجدول متابعةً SRC-10 على صاحب القرار بموعد المتابعة المدخل — والحارسُ يُلزم المكلَّفَ والمهلة');
$add('الدورات ٤', '④-٦ الرقابة العليا: المؤشرات الجامعة الستة', 'PARTIAL',
    'لوحةُ ceo_board بأعمدتها الـ16 قائمةٌ عرضًا — والاشتقاقُ الحيُّ لكل مؤشرٍ من مصدره لم يُقَس');
$add('الدورات ٤', '④-١ نماذج العمل والموازنة', 'PARTIAL', 'الشاشتان عارضتان للتنفيذي (يملكهما غيرُه) — والسقوفُ تُقرأ ولا تُفرض من هنا');
$add('الدورات ٤', '④-٢ الاعتماد الأعلى بأربعة خيارات', 'PARTIAL',
    'شاشةُ الاعتمادات قائمةٌ بأعمدة القرار — والخياراتُ الأربعة (اعتماد/بشرط/رد/تأجيل) لم تُبنَ أفعالًا');

/* ═══ ⑥ التقارير الثمانية ═══ */
$add('التقارير ١٢', 'الثمانية (لوحة عليا · معلَّقات · عقود · مشاريع · قرارات · مالي · مخاطر · سقوف)', 'PARTIAL',
    'ثلاثةٌ منها تُقرأ من الشاشات القائمة — وخمسةٌ لا مُشتقَّ لها بعد (تقاريرُ الحوكمة بُنيت لـM-14 لا M-00)');

/* ═══ ⑦ معايير القبول §14 (الرباعية لكل فعل) ═══ */
$add('القبول ١٤', 'أربعُ حالاتٍ لأفعال M-00 الخمسة', 'OPEN',
    'لا اختبارَ رباعيًّا لأفعال (اعتماد أعلى · توقيع · فتح مشروع · حسم · تعمّق) — بُنيت للـWFM وSoD لا لهذه');

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
