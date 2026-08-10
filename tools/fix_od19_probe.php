<?php
/**
 * tools/fix_od19_probe.php — قياسُ البنودِ التسعةَ عشرَ «التي تحتاج مراجعة»
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ الوثيقةُ FIX-03 (FIXC-0060) تصفها بأنها «تسعةَ عشرَ بندًا إصلاحُها قرارُ
 *   مالكٍ لا كودًا · تُرفع في سجلٍّ منفصلٍ ولا تُدرَج في طابورِ البرمجة».
 * ◆ والقراءةُ في السجلِّ الجامعِ نفسِه تقول غيرَ ذلك: العمودُ المقصود «الإصلاحُ
 *   قابلٌ للتنفيذ» موسومٌ فيها بـ«لا — يحتاج مراجعة»، ونصُّ الإصلاحِ في كلٍّ
 *   منها **شيفرةٌ محدَّدة** (هجرةٌ · صفُّ تنقّلٍ · عمودٌ · حارس) لا سؤالُ سياسة.
 *   بل إن أولَها (INJ-0061) نُفِّذ في هذه الحزمةِ بهجرةٍ ومقيَّدٍ في القاعدة.
 * ◆ فلا يُبنى سجلُّ قراراتٍ على وصفٍ لم تؤيّده القراءة. يُقاس كلُّ بندٍ
 *   **باختبارِ قبولِه هو** على النظامِ الحيّ، ثم يُصنَّف بما ظهر:
 *     مُنفَّذ · قابلٌ للتنفيذ (شيفرة) · يحتاج قرارَ مالكٍ حقًّا.
 *
 * التشغيل: php tools/fix_od19_probe.php [--md=مسار]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once __DIR__ . '/fix_lib.php';
$db = fix_db();

$mdOut = null;
foreach ($argv as $a) { if (strpos($a, '--md=') === 0) { $mdOut = substr($a, 5); } }

/** عددٌ من استعلامٍ، أو null إن فشل — الفشلُ لا يُقرأ صفرًا (گوتشا مسجَّلة). */
function q1($db, $sql)
{
    $rs = $db->query($sql);
    if (!$rs) { return null; }
    $row = $rs->fetch_row();
    return $row ? (int) $row[0] : 0;
}
function src($ROOT, $rel) { return (string) @file_get_contents($ROOT . '/' . $rel); }
function has($ROOT, $rel, $needle) { return strpos(src($ROOT, $rel), $needle) !== false; }

/* كلُّ مِسبارٍ يعود: [مُنفَّذ؟, شاهدٌ نصّيّ] */
$P = array();

$P['INJ-0061'] = function () use ($db) {
    $rel = q1($db, "SELECT COUNT(*) FROM nav_items WHERE route LIKE '../%'");
    $chk = q1($db, "SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
                     WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'chk_nav_route_not_relative'");
    return array($rel === 0 && $chk > 0, "مساراتٌ نسبية: {$rel} · قيدٌ مانع: " . ($chk ? 'نعم' : 'لا'));
};

/* ◆ `role_nav_visibility` لا وجودَ له في هذه القاعدة — والرؤيةُ في `nav_items.role_id`.
     والمِسبارُ الأولُ استعلم عن جدولٍ غيرِ قائمٍ فعاد null، وnull ليس قياسًا. */
$P['INJ-0128'] = function () use ($db) {
    $n = q1($db, "SELECT COUNT(*) FROM nav_items
                   WHERE role_id = 9 AND (route LIKE '%report%' OR route LIKE '%Reports%')");
    return array($n !== null && $n >= 3, "روابطُ تقاريرَ للدور 9: " . var_export($n, true) . ' (المطلوب 3)');
};

$P['INJ-0131'] = function () use ($ROOT) {
    $ok = has($ROOT, 'Portal/project_charter.php', 'ems_audit');
    return array($ok, 'نداءُ التدقيقِ في ميثاقِ المشروع: ' . ($ok ? 'موجود' : 'غائب'));
};

$P['INJ-0171'] = function () use ($ROOT) {
    $ok = is_file($ROOT . '/Governance/gov_dept.php');
    return array($ok, 'مكوّنُ حوكمةِ الإدارةِ الموحَّد: ' . ($ok ? 'موجود' : 'غير موجود'));
};

/* ◆ لا جدولَ باسم `act_action_contracts` في هذه القاعدة؛ وسجلُّ عقودِ الأفعالِ
     القائمُ هو `actions` (رمزٌ · فاعلٌ · أثر). فيُقاس عليه لا على اسمٍ مفترَض. */
$P['INJ-0193'] = function () use ($db) {
    $n = q1($db, "SELECT COUNT(*) FROM actions");
    return array($n !== null && $n > 0, 'أفعالٌ مسجَّلةٌ في `actions`: ' . var_export($n, true));
};

$P['INJ-0219'] = function () use ($db) {
    $n = q1($db, "SELECT COUNT(*) FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'payroll_deduction%'
                     AND COLUMN_NAME IN ('state','status','approval_state')");
    return array($n !== null && $n > 0, 'عمودُ حالةٍ في جدولِ الخصومات: ' . var_export($n, true));
};

$P['INJ-0224'] = function () use ($ROOT) {
    $s = src($ROOT, 'includes/sod_guard.php');
    $ok = (strpos($s, 'sod_payroll_cycle') !== false) && preg_match("/sod_payroll_cycle[^\\n]{0,120}exact/u", $s);
    return array((bool) $ok, 'زوجُ دورةِ الرواتبِ بدرجةِ exact: ' . ($ok ? 'نعم' : 'لا'));
};

$P['INJ-0279'] = function () use ($db) {
    $n = q1($db, "SELECT COUNT(*) FROM nav_items WHERE route LIKE '%readiness_board%'");
    return array($n !== null && $n > 0, 'صفُّ تنقّلٍ لجاهزيةِ المعدات: ' . var_export($n, true));
};

$P['INJ-0280'] = function () use ($db) {
    $n = q1($db, "SELECT COUNT(*) FROM link_groups WHERE stage_title LIKE '%القطع والمواد%'");
    return array($n !== null && $n > 0, 'مجموعةُ القطعِ والمواد: ' . var_export($n, true));
};

$P['INJ-0282'] = function () use ($db) {
    $n = q1($db, "SELECT COUNT(*) FROM nav_items WHERE route LIKE '%equipment_hours%'");
    return array($n !== null && $n > 0, 'صفُّ تنقّلٍ لساعاتِ الوقائية: ' . var_export($n, true));
};

$P['INJ-0323'] = function () use ($ROOT) {
    $ok = has($ROOT, 'Transport/transfer_order_form.php', 'ems_enforce_write_permission')
       || has($ROOT, 'Transport/transfer_order_form.php', 'ems_guard');
    return array($ok, 'حارسُ انتقالاتِ أمرِ الترحيل: ' . ($ok ? 'موصول' : 'غائب'));
};

$P['INJ-0331'] = function () use ($db) {
    $n = q1($db, "SELECT COUNT(*) FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proc_supplier' AND COLUMN_NAME = 'supplier_id'");
    return array($n !== null && $n > 0, 'خريطةُ proc_supplier.supplier_id: ' . ($n ? 'موجودة' : 'غير موجودة'));
};

$P['INJ-0416'] = function () use ($ROOT) {
    $s = src($ROOT, 'Portal/ceo_approvals.php');
    $cnt = 0;
    if (preg_match('/\$COLS\s*=\s*array\(([\s\S]*?)\);/u', $s, $m)) { $cnt = substr_count($m[1], "'") / 2; }
    return array($cnt >= 23, 'أعمدةُ شاشةِ اعتماداتِ الرئيس: ' . (int) $cnt . ' (المطلوب 23)');
};

$P['INJ-0422'] = function () use ($ROOT) {
    $s = src($ROOT, '.env.example');
    $n = preg_match_all('/^\s*EMS_[A-Z0-9_]+\s*=/m', $s);
    return array($n >= 16, 'مفاتيحُ EMS_ في .env.example: ' . (int) $n . ' (المطلوب 16)');
};

$P['INJ-0443'] = function () use ($db) {
    $n = q1($db, "SELECT COUNT(*) FROM nav_items WHERE label_ar LIKE '%مخاطر إدارة الموردين%'");
    return array($n !== null && $n > 0, 'عنصرُ مخاطرِ المورّدين: ' . var_export($n, true));
};

/* ◆ `link_groups` لا عمودَ `role_id` فيه بل `owner_role_id`، والرؤيةُ تُشتقُّ
     من مجموعاتِ عناصرِ التنقّلِ نفسِها. فيُعدُّ المتمايزُ منها للدور 26. */
$P['INJ-0471'] = function () use ($db) {
    $n = q1($db, "SELECT COUNT(DISTINCT group_id) FROM nav_items
                   WHERE role_id = 26 AND group_id IS NOT NULL");
    return array($n !== null && $n >= 8, 'مجموعاتُ الدور 26: ' . var_export($n, true) . ' (المطلوب 8)');
};

$P['INJ-0535'] = function () use ($db) {
    $n = q1($db, "SELECT COUNT(*) FROM nav_items WHERE route LIKE 'Portal/my_%'");
    return array($n !== null && $n >= 2, 'روابطُ المساحةِ الشخصية: ' . var_export($n, true));
};

$P['INJ-0544'] = function () use ($db) {
    $n = q1($db, "SELECT COUNT(*) FROM nav_items WHERE label_ar LIKE '%مخاطر النقل والترحيل%'");
    return array($n !== null && $n > 0, 'عنوانُ مخاطرِ النقلِ والترحيل: ' . var_export($n, true));
};

$P['INJ-0589'] = function () use ($db) {
    $n = q1($db, "SELECT COUNT(*) FROM role_permissions
                   WHERE role_id = 9 AND can_view = 1 AND module_id BETWEEN 377 AND 392");
    return array($n !== null && $n >= 16, 'منحُ قراءةِ المراجعةِ للدور 9: ' . var_export($n, true) . ' (المطلوب 16)');
};

/* ══ التشغيل ══════════════════════════════════════════════════════════════ */
$json = $ROOT . '/docs/fix_2026-08/od19.json';
$items = is_file($json) ? json_decode((string) file_get_contents($json), true) : null;
if (!$items) {
    // يُعاد الاستخراجُ من السجلِّ الجامعِ إن لم تُحفَظ نسخةٌ بعد
    $items = array();
    foreach (file($ROOT . '/docs/fix_2026-08/master_register.tsv') as $i => $line) {
        if ($i < 2) { continue; }
        $r = explode("\t", rtrim($line, "\n"));
        if (count($r) < 26 || trim($r[25]) !== 'لا — يحتاج مراجعة') { continue; }
        $items[] = array('id' => trim($r[0]), 'doc' => trim($r[1]), 'screen' => trim($r[4]),
                         'sev' => trim($r[10]), 'fix' => trim($r[16]), 'test' => trim($r[20]));
    }
}

echo "══════════════════════════════════════════════════════════════════════\n";
echo " البنودُ التسعةَ عشرَ «التي تحتاج مراجعة» — مقيسةً على النظامِ الحيّ\n";
echo ' ' . date('Y-m-d H:i') . "\n";
echo "══════════════════════════════════════════════════════════════════════\n\n";

$done = 0; $open = 0; $unknown = 0; $rows = array();
foreach ($items as $it) {
    $id = $it['id'];
    if (!isset($P[$id])) { $unknown++; $rows[] = array($id, '؟', 'لا مِسبارَ له', $it); continue; }
    list($ok, $ev) = $P[$id]();
    if ($ok) { $done++; } else { $open++; }
    $rows[] = array($id, $ok ? 'مُنفَّذ' : 'مفتوح', $ev, $it);
    printf("%s %-10s %-4s %s\n     %s\n", $ok ? '✔' : '✘', $id, $it['sev'], mb_substr($it['screen'], 0, 46), $ev);
}

echo "\n" . str_repeat('═', 70) . "\n";
printf("مُنفَّذٌ: %d · مفتوحٌ: %d · بلا مِسبار: %d — من %d\n", $done, $open, $unknown, count($items));
echo str_repeat('═', 70) . "\n";

if ($mdOut) {
    $md = "# البنودُ التسعةَ عشرَ — قياسٌ حيّ · " . date('Y-m-d H:i') . "\n\n";
    $md .= "| البند | الخطورة | الحالة | الشاهد | الشاشة |\n|---|---|---|---|---|\n";
    foreach ($rows as $r) {
        $md .= "| `{$r[0]}` | {$r[3]['sev']} | " . ($r[1] === 'مُنفَّذ' ? '**مُنفَّذ** ✔' : $r[1])
             . " | {$r[2]} | " . mb_substr($r[3]['screen'], 0, 40) . " |\n";
    }
    $md .= "\n**مُنفَّذ: {$done} · مفتوح: {$open} · بلا مِسبار: {$unknown}** من " . count($items) . "\n";
    file_put_contents($mdOut, $md);
    echo "كُتب: {$mdOut}\n";
}
exit(0);
