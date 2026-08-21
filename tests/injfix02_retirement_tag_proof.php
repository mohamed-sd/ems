<?php
/**
 * tests/injfix02_retirement_tag_proof.php — INJ-FIX-02 · NF-06 (الموجةُ أ)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيار**: «صفرُ سطحٍ موسومٍ متقاعدًا وله تشغيلٌ ناجحٌ في نافذةِ القياس».
 *
 * ◆ **ولماذا فحصٌ لا جولةُ تصحيح**: الوسمُ الخاطئُ لم يكن بياناتٍ غلطًا بل
 *   **كاشفًا يرصد مفرداتِه هو** — فجولةُ تصحيحٍ تُصلح ١٣ صفًّا واليومَ التالي
 *   يعيد المولِّدُ وسمَها. فالمُصلَحُ هو القاعدةُ، والفحصُ يمنع عودتَها.
 *
 * ◆ **والحزامُ سلبيٌّ أولًا**: يُجرَّب الكاشفُ على خمسِ عيّناتٍ مُصطنَعةٍ — ثلاثٌ
 *   يجب أن يمسكها واثنتان يجب ألّا يمسكهما. **فبوابةٌ لا تعرف كيف ترسُب لا
 *   يعني مرورُها شيئًا.**
 *
 * التشغيل: php tests/injfix02_retirement_tag_proof.php
 * الخروج : 0 نجاح · 1 رسوب
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/deprecated_mark.php';
require_once $ROOT . '/includes/env.php';

$ok = 0; $bad = 0;
function chk($cond, $msg)
{
    global $ok, $bad;
    if ($cond) { $ok++; echo "  ✔ {$msg}\n"; }
    else       { $bad++; echo "  ✘ {$msg}\n"; }
}

echo "══ ① الحزامُ السلبيُّ — أيعرف الكاشفُ كيف يرسُب؟ ══\n";

/* — ما يجب ألّا يُمسَك: مفرداتُ الكاشفِ في مواضعَ ليست إعلانًا — */
chk(!ems_deprecated_mark("<?php\nerror_reporting(E_ALL & ~E_DEPRECATED);\necho 1;\n"),
    'E_DEPRECATED في error_reporting **ليست** إعلانَ تقاعد');
chk(!ems_deprecated_mark("<?php\n\$s = \$r['state']==='retired' ? 'متقاعد' : 'نافذ';\necho \$s;\n"),
    'كلمةُ «متقاعد» **مطبوعةً لافتةً** ليست إعلانَ تقاعد');
chk(!ems_deprecated_mark("<?php\n// يستبدل ملفات E_DEPRECATED القديمة\n\$x=1;\n"),
    'ذكرُ E_DEPRECATED داخلَ تعليقٍ ليس إعلانًا');

/* — ما يجب أن يُمسَك: إعلانٌ مقصودٌ في موضعِه — */
chk(ems_deprecated_mark("<?php\n/**\n * @deprecated استُبدل بـcron_jobs.php\n */\n\$x=1;\n"),
    'وسمُ @deprecated في تعليقِ توثيقٍ **يُمسَك**');
chk(ems_deprecated_mark("<?php\nconst EMS_DEPRECATED = true;\n"),
    'ثابتُ EMS_DEPRECATED المُعلَنُ **يُمسَك**');
chk(ems_deprecated_mark("<?php\n/* هذا الملفُّ متقاعدٌ منذ 2026 */\n\$x=1;\n"),
    'إعلانٌ عربيٌّ داخلَ تعليقٍ **يُمسَك**');

echo "\n══ ② الأسطحُ الثلاثةَ عشرَ التي وسَمها الكاشفُ القديم ══\n";
$WAS = array('Contracts/cron_price_adjustment.php', 'Finance/cron_depreciation_fin.php',
    'Finance/cron_periodic_fin.php', 'Governance/auth_profiles.php', 'Governance/cron_permissions.php',
    'Operations/cron_asset_reconciliation.php', 'Operations/cron_capacity_rollup.php',
    'Operations/cron_container_gate_report.php', 'Operations/cron_fin_posting.php',
    'Operations/cron_job_worker.php', 'Operations/cron_org_assignments.php',
    'Operations/cron_rotation_transfer.php', 'cron_jobs.php');
$still = array();
foreach ($WAS as $rel) {
    $p = $ROOT . '/' . $rel;
    if (!is_file($p)) { continue; }
    if (ems_deprecated_mark((string) file_get_contents($p))) { $still[] = $rel; }
}
chk(count($still) === 0, 'صفرٌ منها يُعلن تقاعدَه بعدَ إصلاحِ القاعدة (' . count($still) . ')'
    . (count($still) ? ' — ' . implode(' · ', $still) : ''));

echo "\n══ ③ المعيارُ نفسُه — لا موسومٌ متقاعدًا وله تشغيلٌ ناجح ══\n";
$h = ems_env('DB_HOST'); $prt = 3306;
if (strpos($h, ':') !== false) { list($h, $prt) = explode(':', $h); $prt = (int) $prt; }
$conn = @new mysqli($h, ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER'),
    ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS'),
    ems_env('DB_NAME'), $prt);
if ($conn->connect_errno) {
    echo "  ⛔ تعذّر الاتصالُ بالقاعدة — القسمُ ③ لا يُقاس\n";
    $bad++;
} else {
    $conn->set_charset('utf8mb4');

    /* المجدولُ حيٌّ؟ — آخرُ نجاحٍ في نافذةِ ٢٤ ساعة */
    $r = $conn->query("SELECT COUNT(*) FROM `ems_job_schedule`
                        WHERE is_active = 1 AND last_success_at >= (NOW() - INTERVAL 24 HOUR)");
    $liveSched = $r ? (int) $r->fetch_row()[0] : 0;
    chk($liveSched > 0, "المجدولُ حيٌّ: {$liveSched} جدولةً نجحت خلالَ ٢٤ ساعة");

    /* والعاملُ الذي ينفّذها — cron_jobs.php — لا يُعلن تقاعدًا */
    chk(!ems_deprecated_mark((string) file_get_contents($ROOT . '/cron_jobs.php')),
        '◆ `cron_jobs.php` — العاملُ الذي يشغّل المجدولَ — لا يُوسَم متقاعدًا');

    /* شاشةُ القوالبِ تحكم فعلًا؟ */
    $r = $conn->query("SELECT COUNT(*) FROM `gov_profile_items`");
    $items = $r ? (int) $r->fetch_row()[0] : 0;
    $r = $conn->query("SELECT COUNT(*) FROM `nav_items`
                        WHERE active = 1 AND route LIKE '%auth_profiles.php%'");
    $navLive = $r ? (int) $r->fetch_row()[0] : 0;
    chk($items > 0 && $navLive > 0,
        "◆ شاشةُ قوالبِ الصلاحياتِ حاكمةٌ حيّةٌ: {$items} بندَ منحٍ · {$navLive} رابطًا نشطًا");
    chk(!ems_deprecated_mark((string) file_get_contents($ROOT . '/Governance/auth_profiles.php')),
        '◆ `Governance/auth_profiles.php` لا يُوسَم متقاعدًا');
}

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
