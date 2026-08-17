<?php
/**
 * 2027_06_07_uxw01_live_corrections.php
 * ═══════════════════════════════════════════════════════════════════════════
 * تصحيحاتُ المالكِ الأربعةُ على النظامِ الحيِّ مع موجاتِ UXW-01:
 *
 * ① قيدُ `chk_step_accountant` يُعدَّل ليقبل موضعَ FIN_FIELD_VERIFY صراحةً:
 *    محاسبُ الموقعِ يدٌ ثانيةٌ في اعتمادِ التايم شيتِ مع مديرِ الموقع
 *    (التسلسل الوظيفي 18-ب · 24-أ LAD-0009-ب/ج)، وعند غيابِه مُنابٌ من
 *    الماليةِ بجلسةِ إنابةٍ مسبَّبة — وما عداه من المحاسبينَ قبلَ بوابةِ
 *    الماليةِ ممنوعٌ كما هو.
 * ② تبعيةُ مركزِ البلاغات: parent_role_id → إدارةُ التشغيل (1) · وخطُّ
 *    اطلاعٍ مباشرٍ (oversight_role_id=9) للرئيسِ ونوابِه على إداراتِ
 *    الطبقةِ الثالثةِ كلِّها — الخطُّ الرقابيُّ لا الإداريّ.
 * ③ اسمُ جهةِ Break Glass البديلةِ يوحَّد على نصِّ GOV-AUTH-01:
 *    «لجنةُ الحوكمةِ والتدقيقِ — عضوان مستقلّان» لكلِّ جهةٍ من نوعِ لجنة.
 * ④ رابطا سايدبارٍ بلا مالكٍ (بوابةُ المنعِ ٧) يُسندانِ إلى شاشتَيهما
 *    المسجَّلتَين في `modules` — ربطٌ مقيسٌ لا تخمين.
 *
 * ولا يُحذف صفٌّ ولا يُعدَّل ما طُبِّق — القيدُ يُسقَط ويُعاد بنصِّه الجديد.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$one = function (string $s) use ($conn) { $r = $conn->query($s); return $r ? ($r->fetch_row()[0] ?? null) : null; };
$run = function (string $s, string $label) use ($conn) {
    if ($conn->query($s) === false) { echo "   ✗ {$label}: {$conn->error}\n"; return false; }
    return true;
};

/* ═══ ① FIN_FIELD_VERIFY — القيدُ ثم السلّم ═══════════════════════════════ */
echo "\n▐ ① محاسبُ الموقعِ — موضعُ FIN_FIELD_VERIFY\n";
printf("   · خطواتُ LD-01 قبلَ التعديل: %s\n",
    $one("SELECT GROUP_CONCAT(CONCAT(step_no,':',actor_code) ORDER BY step_no SEPARATOR ' → ')
            FROM gov_ladder_steps WHERE ladder_code='LD-01'"));

$run("ALTER TABLE `gov_ladder_steps` DROP CONSTRAINT `chk_step_accountant`", 'إسقاطُ القيدِ القديم');
$run("ALTER TABLE `gov_ladder_steps` ADD CONSTRAINT `chk_step_accountant`
        CHECK (`is_accountant` = 0 OR `is_finance_gate` = 1 OR `actor_code` = 'FIN_FIELD_VERIFY')",
    'القيدُ الجديد');

$run("DELETE FROM `gov_ladder_steps` WHERE `ladder_code`='LD-01'", 'تفريغُ خطواتِ LD-01 لإعادةِ بذرِها');
$si = $conn->prepare(
    "INSERT INTO `gov_ladder_steps`
        (`company_id`,`ladder_code`,`step_no`,`actor_code`,`actor_name_ar`,`step_kind`,
         `is_accountant`,`is_finance_gate`,`may_approve`,`forbid_note`)
     VALUES (0,'LD-01',?,?,?,?,?,0,?,?)"
);
$LD01 = array(
    array(1, 'unit_entry_clerk', 'مدخل الوحدات', 'entry', 0, 0,
        '◆ مدخلُ الوحداتِ ممنوعٌ من الاعتماد — chk_step_entry_no_approve'),
    array(2, 'FIN_FIELD_VERIFY', 'محاسب الموقع', 'review', 1, 0,
        'اليدُ الثانيةُ — يراجع اكتمالَ الحقولِ والوقودِ والعدّاد (LAD-0009-ب) · وعند غيابِه مُنابٌ من الماليةِ بجلسةِ إنابةٍ مسبَّبة'),
    array(3, 'site_manager', 'مدير الموقع', 'approve', 0, 1,
        'يعتمد الواقعةَ اليوميةَ مع اليدِ الثانيةِ (LAD-0009-ج) — ولا يعتمد ما أدخله بنفسِه'),
);
$n = 0;
foreach ($LD01 as $S) {
    list($no, $ac, $an, $k, $isAcc, $may, $note) = $S;
    $si->bind_param('isssiis', $no, $ac, $an, $k, $isAcc, $may, $note);
    if ($si->execute()) { $n++; } else { echo "   ✗ LD-01/{$no}: {$si->error}\n"; }
}
$si->close();
printf("   ✔ %d خطواتٍ — بعدَ التعديل: %s\n", $n,
    $one("SELECT GROUP_CONCAT(CONCAT(step_no,':',actor_code) ORDER BY step_no SEPARATOR ' → ')
            FROM gov_ladder_steps WHERE ladder_code='LD-01'"));
printf("   · محاسبونَ قبلَ البوابةِ خارجَ FIN_FIELD_VERIFY: %s   [المتوقَّع 0]\n",
    $one("SELECT COUNT(*) FROM gov_ladder_steps
           WHERE is_accountant=1 AND is_finance_gate=0 AND actor_code<>'FIN_FIELD_VERIFY'"));

/* اختبارٌ سلبيّ: محاسبٌ آخرُ قبلَ البوابةِ يُرفَض */
$neg = $conn->query("INSERT INTO gov_ladder_steps
    (company_id,ladder_code,step_no,actor_code,actor_name_ar,step_kind,is_accountant,is_finance_gate,may_approve)
    VALUES (0,'LD-01',99,'rogue_accountant','محاسبٌ دخيل','review',1,0,0)");
if ($neg === false) {
    echo "   ✔ السلبيّ: محاسبٌ دخيلٌ قبلَ البوابةِ رُفض ({$conn->errno})\n";
} else {
    $conn->query("DELETE FROM gov_ladder_steps WHERE ladder_code='LD-01' AND step_no=99");
    echo "   ✗ السلبيّ: القيدُ لم يرفض المحاسبَ الدخيل!\n";
}

/* ═══ ② تبعيةُ مركزِ البلاغاتِ وخطُّ الاطلاعِ على الطبقةِ الثالثة ═════════ */
echo "\n▐ ② مركزُ البلاغاتِ والطبقةُ الثالثة\n";
printf("   · قبل: parent=%s · oversight=%s\n",
    $one("SELECT COALESCE(parent_role_id,'NULL') FROM roles WHERE id=24"),
    $one("SELECT COALESCE(oversight_role_id,'NULL') FROM roles WHERE id=24"));
$run("UPDATE roles SET parent_role_id=1 WHERE id=24", 'تبعيةُ البلاغاتِ لإدارةِ التشغيل');

/* الطبقةُ الثالثةُ بنصِّ «التسلسل الوظيفي»: الصيانةُ · المشترياتُ التشغيلية ·
   المخازنُ · النقلُ والترحيل · القوى التشغيلية · مركزُ البلاغات */
$LAYER3 = array(13 => 'إدارة الصيانة', 16 => 'إدارة المشتريات', 23 => 'النقل والترحيل',
                24 => 'مركز البلاغات', 25 => 'المخازن', 27 => 'القوى التشغيلية');
foreach ($LAYER3 as $rid => $nm) {
    $run("UPDATE roles SET oversight_role_id=9,
            oversight_note='خطُّ اطلاعٍ مباشرٍ للرئيسِ التنفيذيِّ ونوابِه — الطبقةُ الثالثةُ (التسلسل الوظيفي · UXW-01)'
          WHERE id={$rid} AND (oversight_role_id IS NULL OR oversight_role_id=9)", "اطلاعٌ: {$nm}");
}
printf("   ✔ طبقةٌ ثالثةٌ بخطِّ اطلاعٍ للرئاسة: %s من 6\n",
    $one("SELECT COUNT(*) FROM roles WHERE id IN (13,16,23,24,25,27) AND oversight_role_id=9"));

/* ═══ ③ توحيدُ اسمِ جهةِ Break Glass البديلة ══════════════════════════════ */
echo "\n▐ ③ جهةُ Break Glass البديلة\n";
printf("   · قبل: %s\n", $one("SELECT GROUP_CONCAT(DISTINCT alternate_label SEPARATOR ' | ')
                                 FROM gov_alternate_authority WHERE alternate_kind='committee'"));
$run("UPDATE gov_alternate_authority
         SET alternate_label='لجنةُ الحوكمةِ والتدقيقِ — عضوان مستقلّان'
       WHERE alternate_kind='committee'", 'توحيدُ اسمِ اللجنة');
printf("   ✔ بعد: %s\n", $one("SELECT GROUP_CONCAT(DISTINCT alternate_label SEPARATOR ' | ')
                                 FROM gov_alternate_authority WHERE alternate_kind='committee'"));

/* ═══ ④ الرابطانِ اليتيمانِ — إسنادٌ مقيسٌ إلى شاشتَيهما المسجَّلتَين ═════ */
echo "\n▐ ④ رابطا السايدبارِ بلا مالك (بوابةُ المنعِ ٧)\n";
$run("UPDATE nav_items SET module_id=307, permission_code='Tickets/dept_inbox.php'
       WHERE id=921 AND module_id IS NULL", 'بلاغاتُ إدارتي → module 307');
$run("UPDATE nav_items SET module_id=429, permission_code='Tickets/intake_classify.php'
       WHERE id=1334 AND module_id IS NULL", 'الاستقبالُ والتصنيف → module 429');
$run("UPDATE modules SET owner_role_id=24 WHERE id=429 AND owner_role_id IS NULL",
    'مالكُ شاشةِ الاستقبال: مركزُ البلاغات (موضعُها الحيُّ في قائمتِه)');
printf("   ✔ روابطُ بلا مالكٍ الآن: %s   [المتوقَّع 0]\n",
    $one("SELECT COUNT(*) FROM nav_items WHERE active=1 AND module_id IS NULL AND permission_code IS NULL"));

echo "\n✔ اكتملت التصحيحاتُ الأربعة\n";
