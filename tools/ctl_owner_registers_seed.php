<?php
/**
 * tools/ctl_owner_registers_seed.php — أمرُ الضبطِ §١٠+§١١ · بذرُ سجلَّي المالكِ والمنصّة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **كلُّ بندٍ هنا قرارٌ حقيقيٌّ رُفع في جولاتٍ موثَّقةٍ** — يُجمع الآن في
 *   سجلٍّ واحدٍ مصنَّفٍ بحقولِه السبعة. ⛔ **ولا بندَ فنّيًّا محسومًا** (‏خوارزميةُ
 *   بصمةٍ أو إيقاعُ كرونٍ لا يرتبط بسياسة) — ذاك قرارُ منفِّذ.
 * ◆ وسجلُّ المنصّةِ يُبذر من قياسِ `rpr02_platform_justify` الحيِّ — ⛔ ولا
 *   يُغلق سطحٌ `JUSTIFIED` و`tech_owner` فارغٌ (قيدُ المخطَّطِ يمنعه).
 * التشغيل: php tools/ctl_owner_registers_seed.php [--apply] [--md]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$e = function ($x) use ($conn) { return $conn->real_escape_string((string) $x); };
$one = function ($sql) use ($conn) {
    $r = @$conn->query($sql); if (!$r) { return null; }
    $x = $r->fetch_row(); return $x === null ? null : $x[0];
};
$APPLY = in_array('--apply', $argv, true);
$MD    = in_array('--md', $argv, true);
$snap = (string) $one("SELECT snapshot_id FROM repair01_freeze_snapshot WHERE released_at IS NULL ORDER BY frozen_at DESC LIMIT 1");

/* ═══ البنودُ — كلٌّ بمرجعِ جولتِه التي رفعته ═══════════════════════════ */
$ACTIONS = array(
    array('OA-PERM-2829', 'BUSINESS_DECISION',
        'منحُ can_view للدورَين ٢٨ و٢٩ على الوحدة ٢٤٦ (Portal/my_tasks.php) أو تأكيدُ منعِهما',
        'س٦ من السايدبار (موضعان) — تعارضُ §٦ مع ف٤ مرفوعٌ في SIDEBAR_FINISH_CLOSURE §١·٢',
        'RPR-02 §6 + بوابة الحفظ ف٤',
        'منحٌ فيظهر البندُ مفحوصًا · أو منعٌ صريحٌ فيُسدُّ الرمزُ ويُصرَّح الفقدُ في بوابة الحفظ',
        'إغلاقُ س٦ في دقيقةٍ بأيِّ الخيارَين',
        'المنحُ — الدوران يريان البندَ اليومَ بلا فحصٍ أصلًا فسدُّه بمنعٍ يغيّر سلوكًا معتادًا'),
    array('OA-NAME-16', 'BUSINESS_DECISION',
        'اعتمادُ تسمية Reports/daily_units_report.php (حالتُها PENDING_OWNER) وتسميةُ Timesheet/view_timesheet.php (بلا صفٍّ ألبتّة)',
        'RPR-02 #16 (3 مواضع) · س٢ (3) · س٧ (1)',
        'RPR-02 §12 #16',
        'اعتمادُ المقترحَين القائمَين · أو تسميةٌ جديدة',
        'إغلاقُ #16 وس٢ وس٧ إلى الصفر',
        'اعتمادُ القائمِ — الاسمان مستقرّان في العرضِ أصلًا'),
    array('OA-REPLAY-18', 'BUSINESS_DECISION',
        'الإذنُ بتصريفِ المتراكمِ الماليِّ (REPLAY_REQUIRED = 20,281 حدثًا) بدفعاتِ Canary',
        'تصريفُ المتراكمِ كلِّه — RPR-03 #18 المرفوعُ سلفًا',
        'RPR-03 §8 + BACKLOG_DISPOSITION',
        'إذنٌ بدفعاتٍ بعد PRE_BUILD_PRE_REPLAY_BASELINE · أو إبقاءُ الوقفِ',
        'كلُّ دفعةٍ تُنشئ أثرًا ماليًّا حقيقيًّا — والعطالةُ مثبتةٌ بقيدِ uq_idem',
        'الإذنُ بدفعةِ Canary أولى ≤ 100 حدثٍ غيرِ ماليٍّ ثمَّ مراجعةُ أثرِها'),
    array('OA-SCHED-19', 'TECHNICAL_DECISION',
        'جدولةُ العاملِ الواحدِ cron_jobs.php في مهامِّ Windows (تغييرُ بيئةٍ خارجَ المستودع) وإصلاحُ مهمّةِ EMS_cron_events المتقاعدة',
        'RPR-03 #19 (9 إخفاقاتِ مدخل: 8 عمّالٍ بلا يدٍ + مدخلٌ متقاعد)',
        'RPR-03 §8·8',
        'جدولةٌ كلَّ دقيقة/خمس · مع تعطيلِ EMS_cron_events أو توجيهِها للعامل',
        'تشغيلُ العاملِ يبدأ تصريفَ الطوابيرِ — فيُقيَّد بإيقافِ المستهلكِ الماليِّ حتى OA-REPLAY-18',
        'الجدولةُ كلَّ خمسِ دقائقَ مع بقاءِ مستهلكي المالِ موقوفين حتى الإذن'),
    array('OA-PERIODS-HIST', 'POLICY_DECISION',
        'سياسةُ الفتراتِ الماليّةِ التاريخيّة 2020-2025 (الدفترُ يبدأ 2026-01-01)',
        'حوكمةُ 1,103 قيدٍ يدويٍّ PRE_LEDGER — RPR-03 #11',
        'RPR-03 §8·1 + CTL §9',
        'إنشاءُ فتراتٍ تاريخيّةٍ مقفلةٍ منذ الولادة · أو وسمُ القيودِ PRE_GOVERNANCE نهائيًّا',
        'بلا فترةٍ حاويةٍ يبقى period_code فارغًا في 1,103 قيدٍ إلى الأبد',
        'فتراتٌ مقفلةٌ منذ الولادة — تحفظ التسلسلَ ولا تفتح تعديلًا'),
    array('OA-RETRO-APPROVE', 'POLICY_DECISION',
        'سياسةُ الاعتمادِ الرجعيِّ للقيودِ اليدويّةِ المرحَّلةِ قبل الحوكمة',
        'approval_ref في 1,644 قيدًا — RPR-03 #11',
        'CTL §9',
        'اعتمادٌ جماعيٌّ بمرجعِ قرارٍ لكلِّ فئةٍ بعد عيّنةِ مراجعة · أو مراجعةٌ شاملة',
        'بدونها يبقى المعتمِدُ صفرًا من 1,644 وقيدُ chk_manual_journal_governed يمنع قيودًا جديدةً ناقصة',
        'اعتمادٌ جماعيٌّ بعد عيّنةِ 30 قيدًا لكلِّ فئة'),
    array('OA-M3-14', 'BUSINESS_DECISION',
        'حسمُ الأهدافِ الأربعةَ عشرَ المحجوبةِ (4 ملتبسةٌ بمرشَّحَين فأكثر · 7 مرشَّحُها تحت مالكٍ آخر · 6 حبّتُها تخالف)',
        'RPR-02 #1 (97.5% ⇒ 100%) · AMD-01 م٣ (419 ⇒ 433) · VALIDATION_STATUS للنطاقات',
        'RPR-02 §4·2',
        'لكلِّ هدفٍ: تسميةُ المطابقِ أو حكمُ NOT_BUILT أو نقلُ ملكيّة — والأسبابُ مطبوعةٌ في rpr02_target_adjudicate --list',
        'يفتح 14 هدفًا و12 نطاقًا جزئيَّ المصادقة',
        'جلسةُ حسمٍ واحدةٌ على القائمةِ المطبوعةِ بأسبابِها'),
    array('OA-DEC-44', 'BUSINESS_DECISION',
        'حسمُ إدارةِ 44 قرارًا DOMAIN_NAME_MISMATCH في OWNER_DECISIONS_MASTER',
        'إسقاطُ أثرِ 44 قرارًا · وسقفُ VALIDATED لكلِّ النطاقات · و220 هدفًا AFFECTED_PENDING',
        'AMD-01 م٤',
        'تصحيحُ عمودِ المجالِ في المصدرِ الحاكم · أو جدولُ ترجمةٍ معتمَد',
        'بدونها لا يبلغ نطاقٌ VALIDATED (البرهانُ موجب)',
        'جدولُ ترجمةٍ معتمَدٌ بتوقيعٍ — أسرعُ من تعديلِ المصدر'),
    array('OA-AXES-7', 'BUSINESS_DECISION',
        'إضافةُ سبعةِ محاورِ أثرٍ غائبةٍ من OWNER_DECISIONS_MASTER (875 خليّة)',
        'AMD-01 م٤ — الإسقاطُ الكاملُ 0 من 125',
        'AMD-01 §8',
        'إضافةُ الأعمدةِ وملؤها · أو إعلانُ عدمِ انطباقِها بقرار',
        'سجلُّ أثرٍ ناقصٌ معلَنٌ يبقى القرارَ مفتوحًا صادقًا',
        'ورشةُ ملءٍ على دفعاتٍ بأولويّةِ القراراتِ ذاتِ الأهدافِ النشطة'),
    array('OA-CAPS-56', 'CONFIG_VALUE',
        'قيمُ حدودِ الاعتمادِ ونافذةِ التجميع (RPR-03 #5 #6)',
        'Approval Shadow Window — ⛔ ولا تمنع بناءَ المحرِّكِ نفسِه',
        'RPR-03 §5',
        'قيمٌ ابتدائيّةٌ قابلةٌ للمراجعة · أو جدولُ حدودٍ بالأدوار',
        'النافذةُ تقيس بلا حدودٍ فلا تحجب فعلًا',
        'قيمٌ ابتدائيّةٌ من أعلى مبالغِ الجولاتِ التاريخيّةِ +20%'),
    array('OA-UAT-HUMANS', 'UAT_INPUT',
        'جدولةُ الرحلاتِ البشريّةِ الستِّ والمراجعةِ اليدويّةِ للذهبيّاتِ العشر (81 محطّةً مجهَّزة)',
        'RPR-03 #9 #10 #16 · GOLDEN_SCREENS_ACCEPTED 0/10',
        'RPR-03 §7',
        'جدولةُ جلساتِ UAT بالحساباتِ المجهَّزةِ في gov_golden_approvals',
        'جدولةٌ لا قرار — والتجهيزُ تامّ',
        'جلستان أسبوعيًّا حتى اكتمالِ العشر'),
);

echo "\n═══ أمرُ الضبطِ §١٠+§١١ — سجلّا المالكِ والمنصّة ═══\n";
printf("  بنودُ المالك: %d · ", count($ACTIONS));
$cls = array();
foreach ($ACTIONS as $a) { $cls[$a[1]] = (isset($cls[$a[1]]) ? $cls[$a[1]] : 0) + 1; }
foreach ($cls as $k => $v) { echo "$k=$v "; }
echo "\n";

/* سجلُّ المنصّةِ من القياسِ الحيّ */
$plat = array();
$r = $conn->query("SELECT s.screen_id, s.canonical_label_ar
                     FROM repair01_screen_registry s
                    WHERE s.ownership_verdict = 'PLATFORM_SHARED' AND s.on_disk = 1 ORDER BY s.screen_id");
while ($r && ($x = $r->fetch_assoc())) { $plat[] = $x; }
printf("  أسطحُ المنصّة: %d — كلُّها بانتظارِ مالكٍ تقنيٍّ مسمًّى\n", count($plat));

if ($APPLY) {
    if ($snap === '') { exit("⛔ لا نافذةَ قياسٍ مفتوحة\n"); }
    $n = 0;
    foreach ($ACTIONS as $a) {
        $sql = "INSERT INTO repair01_owner_actions
                (action_key, class, decision, blocks, required_by, options, impact, recommendation, status, snapshot_id)
                VALUES ('" . $e($a[0]) . "','" . $e($a[1]) . "','" . $e($a[2]) . "','" . $e($a[3]) . "','"
              . $e($a[4]) . "','" . $e($a[5]) . "','" . $e($a[6]) . "','" . $e($a[7]) . "','PENDING','" . $e($snap) . "')
                ON DUPLICATE KEY UPDATE blocks = VALUES(blocks), options = VALUES(options),
                                        impact = VALUES(impact), recommendation = VALUES(recommendation)";
        if (!$conn->query($sql)) { exit("✘ {$a[0]}: {$conn->error}\n"); }
        $n++;
    }
    $np = 0;
    foreach ($plat as $x) {
        $wit = 'من rpr02_platform_justify الحيّ: J3 NO_SCOPE_TO_RETURN — أخلَّ بمعيارٍ ولا نطاقَ يعود إليه، '
             . 'والمعيارُ ③ (مالكٌ تقنيٌّ شخصًا) حاجزُ بيانٍ: tech_owner فارغٌ في مصدرِه الوحيد';
        $sql = "INSERT INTO repair01_platform_ownership
                (screen_id, label_ar, justify_state, criteria_met, tech_owner, status, witness, snapshot_id)
                VALUES ('" . $e($x['screen_id']) . "','" . $e($x['canonical_label_ar']) . "','J3','','',
                        'AWAITING_OWNER_NAME','" . $e($wit) . "','" . $e($snap) . "')
                ON DUPLICATE KEY UPDATE witness = VALUES(witness)";
        if (!$conn->query($sql)) { exit("✘ {$x['screen_id']}: {$conn->error}\n"); }
        $np++;
    }
    printf("\n  ✔ بُذر سجلُّ المالكِ (%d بندًا PENDING) وسجلُّ المنصّةِ (%d سطحًا AWAITING_OWNER_NAME)\n", $n, $np);
}

if ($MD) {
    $o  = "# أمرُ الضبطِ §١٠+§١١ — `OWNER_ACTION_REGISTER` و`PLATFORM_SHARED_OWNERSHIP_REGISTER`\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `$snap`\n\n";
    $o .= "⛔ **لا بندَ فنّيًّا محسومًا هنا** — خوارزميّةُ بصمةٍ وإيقاعُ كرونٍ غيرُ المرتبطِ بسياسةٍ قرارُ منفِّذ.\n\n";
    $o .= "## سجلُّ قراراتِ المالك — " . count($ACTIONS) . " بندًا\n\n";
    $o .= "| المفتاح | الصنف | القرار | ما يحجبه | البوّابة | التوصية |\n|---|---|---|---|---|---|\n";
    foreach ($ACTIONS as $a) {
        $o .= '| `' . $a[0] . '` | `' . $a[1] . '` | ' . $a[2] . ' | ' . $a[3] . ' | ' . $a[4] . ' | ' . $a[7] . " |\n";
    }
    $o .= "\n## سجلُّ ملكيّةِ المنصّة — " . count($plat) . " سطحًا\n\n";
    $o .= "⛔ **لا يُغلق سطحٌ دون تبريرٍ منصّيٍّ صحيحٍ ومالكٍ تقنيٍّ مسمًّى** — وقيدُ المخطَّطِ يمنع `JUSTIFIED` بمالكٍ فارغ.\n\n";
    $o .= "| السطح | الاسم | الحال |\n|---|---|---|\n";
    foreach ($plat as $x) { $o .= '| `' . $x['screen_id'] . '` | ' . $x['canonical_label_ar'] . " | `AWAITING_OWNER_NAME` |\n"; }
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/CTL_OWNER_REGISTERS.md', $o);
    echo "\n✔ كُتب docs/REPAIR01_20260823/CTL_OWNER_REGISTERS.md\n";
}
