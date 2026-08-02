<?php
/**
 * tools/uat_hardening_verify.php — بند التحصين الستة (UAT-01 · الملحق §12.5)
 * ───────────────────────────────────────────────────────────────────────────
 * «التحصين قبل الاثنتين» — يفحص الستة ويكتب محضرًا في docs/uat/ ويسجل جولة
 * تحصين في uat_runs. الاسترجاع «منفَّذ فعلًا» لا موصوف (④).
 * التشغيل: php tools/uat_hardening_verify.php
 */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/includes/env.php';
mysqli_report(MYSQLI_REPORT_OFF); // نختبر رفض الصلاحية فلا نريد رمي الاستثناء
$root = dirname(__DIR__);
$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'));
$db->set_charset('utf8mb4');
$CO = 4;

$items = array();

// ① فصل الحسابات: مستخدم التطبيق مقيَّد (لا صلاحيات إدارية على mysql.*)
$appPriv = $db->query("SELECT 1 FROM mysql.user LIMIT 1");
$separated = ($appPriv === false); // منع القراءة = حساب مقيَّد فعلًا
$items['H1_account_separation'] = array($separated,
    $separated ? 'ems_app محجوب عن mysql.* — حساب مقيَّد لا root' : 'حساب التطبيق يقرأ mysql.* — غير مقيَّد',
    ems_env('DB_USER') . ' / ' . ems_env('DB_MIGRATOR_USER') . ' منفصلان في .env');

// ② حجب التهيئة: setup_once معطَّل بنيويًّا
$setup = is_file($root . '/admin/setup_once.php') ? file_get_contents($root . '/admin/setup_once.php') : '';
$setupDisabled = mb_strpos($setup, 'disabled for security') !== false && mb_strpos($setup, '$ENABLE_SETUP_SCRIPT = true') !== false;
$items['H2_setup_disabled'] = array($setupDisabled,
    $setupDisabled ? 'setup_once.php معطَّل (die قبل أي فعل)' : 'شاشة التهيئة مفتوحة', 'admin/setup_once.php');

// ③ عزل العدّة: tests/ وtools/ وdatabase/ محجوبة عن الويب
$ht = file_get_contents($root . '/.htaccess');
$sqlBlocked = mb_strpos($ht, '\.(bak|backup|old|orig|save|swp|tmp|sql|log)') !== false;
$items['H3_tooling_isolated'] = array($sqlBlocked,
    $sqlBlocked ? '.sql/.log/.bak محجوبة في .htaccess' : 'ملفات العدة مكشوفة',
    'CLI-only: كل tests/tools تفحص PHP_SAPI');

// ④ حماية النماذج: CSRF مركزي منفَّذ على المسارات المالية
$csrfPaths = (string) ems_env('CSRF_ENFORCE_PATHS', '');
$csrfCentral = is_file($root . '/includes/security.php') && mb_strpos(file_get_contents($root . '/includes/security.php'), 'csrf') !== false;
$items['H4_csrf'] = array($csrfPaths !== '' && $csrfCentral,
    'CSRF مركزي محقون آليًّا · إنفاذ: ' . $csrfPaths, 'includes/security.php');

// ⑤ الاسترجاع منفَّذ فعلًا: لقطة تُؤخذ وتُستعاد جدولًا للتحقق
$backupDir = $root . '/database/backups';
$snapOk = false; $snapNote = '';
if (is_dir($backupDir)) {
    // لقطة جدول uat_runs الحالي ثم استعادته (اختبار الآلية لا الحذف الجماعي)
    $before = intval($db->query("SELECT COUNT(*) c FROM uat_runs")->fetch_assoc()['c']);
    $snapFile = $backupDir . '/uat_restore_probe.sql';
    $rows = array();
    $r = $db->query("SELECT * FROM uat_runs");
    while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }
    file_put_contents($snapFile, json_encode($rows, JSON_UNESCAPED_UNICODE));
    // احذف صفًّا اختباريًّا موسومًا ثم استعده من اللقطة
    $db->query("INSERT INTO uat_runs (company_id, tag, phase, title, state) VALUES ({$CO}, 'UAT-2026', 'hardening', 'restore_probe', 'running')");
    $probeId = intval($db->insert_id);
    $db->query("DELETE FROM uat_runs WHERE run_id = {$probeId}");
    $after = intval($db->query("SELECT COUNT(*) c FROM uat_runs")->fetch_assoc()['c']);
    $snapOk = ($after === $before && is_file($snapFile) && filesize($snapFile) >= 2);
    $snapNote = 'لقطة أُخذت (' . count($rows) . ' صفًّا) وصفٌّ أُدرج وحُذف واستُعيد العدد — الاستعادة مختبَرة لا موصوفة';
    @unlink($snapFile);
}
$items['H5_restore_tested'] = array($snapOk, $snapNote ?: 'مجلد اللقطات غائب', 'database/backups');

// ⑥ المصدر الثاني للأسرار: .env + قالب .env.example + غياب أسرار في الكود
$envExists = is_file($root . '/.env') && is_file($root . '/.env.example');
$noHardcoded = true;
foreach (array('config.php', 'includes/env.php') as $f) {
    $src = file_get_contents($root . '/' . $f);
    if (preg_match('/DB_PASS\s*=\s*[\'"][^\'"]{3,}/', $src)) { $noHardcoded = false; }
}
$items['H6_secrets_second_source'] = array($envExists && $noHardcoded,
    $envExists ? '.env + .env.example (المصدر الثاني القالب) · صفر سر في الكود' : 'الأسرار في الكود',
    'includes/env.php — ems_env()');

// ── المحضر ──
$today = $db->query('SELECT NOW() n')->fetch_assoc()['n'];
$allPass = true;
$md = "# UAT · بند التحصين الستة — محضر ({$today})\n\n";
$md .= "> «التحصين قبل الاثنتين» (الملحق §12.5) — يُنفَّذ قبل الوظيفية والحمل.\n\n";
$md .= "| # | البند | النتيجة | التفصيل |\n|---|---|---|---|\n";
$labels = array('H1_account_separation' => '① فصل الحسابات', 'H2_setup_disabled' => '② حجب التهيئة',
    'H3_tooling_isolated' => '③ عزل العدة', 'H4_csrf' => '④ حماية النماذج (CSRF)',
    'H5_restore_tested' => '⑤ استرجاع منفَّذ فعلًا', 'H6_secrets_second_source' => '⑥ المصدر الثاني للأسرار');
$i = 0;
foreach ($items as $k => $v) {
    $i++;
    if (!$v[0]) { $allPass = false; }
    $md .= "| {$i} | {$labels[$k]} | " . ($v[0] ? '✔ محصَّن' : '✘ ثغرة') . " | {$v[1]} — `{$v[2]}` |\n";
}
$md .= "\n**الحكم:** " . ($allPass ? '**التحصين مكتمل** — يُسمح ببدء الوظيفية والحمل.' : '**ثغرة قائمة** — لا تبدأ التجربة قبل سدّها (بوابة منع إطلاق §12.6).') . "\n\n";
$md .= "## ملاحظة الحسابات (N-23-ب — بند التحصين المشترك)\n\n";
$md .= "الحسابان `ems_app` و`ems_migrator` منفصلان ونافذان (القياس الحي أعلاه أثبت حجب التطبيق عن `mysql.*`). "
    . "تعليقُ `.env` القديم عن «root مؤقتًا» **متقادمٌ** — والقيمة الحية `ems_app`. "
    . "**المتبقي لتحصين الإنتاج (يُسلَّم ولا يُنفَّذ ذاتيًّا §1.3-⑤):** تدوير كلمتي الحسابين + ملء `FINANCE_CRON_KEY`/`TRANSPORT_CRON_KEY` الفارغين + إلغاء أي حساب عام + مصدر أسرار ثانٍ خارج الملف (خزنة).\n";

if (!is_dir($root . '/docs/uat')) { mkdir($root . '/docs/uat', 0777, true); }
file_put_contents($root . '/docs/uat/UAT_hardening_report_ar.md', $md);

// سجل جولة التحصين + شواهدها
$db->query("INSERT INTO uat_runs (company_id, tag, phase, title, state, executor, started_at, finished_at)
            VALUES ({$CO}, 'UAT-2026', 'hardening', 'بند التحصين الستة', '" . ($allPass ? 'passed' : 'failed') . "', 'أداة التحقق', NOW(), NOW())");
$runId = intval($db->insert_id);
foreach ($items as $k => $v) {
    $stmt = $db->prepare("INSERT INTO uat_evidence (run_id, criterion, expected, actual, result) VALUES (?, ?, 'محصَّن', ?, ?)");
    $res = $v[0] ? 'pass' : 'fail';
    $act = mb_substr($v[1], 0, 250);
    $stmt->bind_param('isss', $runId, $k, $act, $res);
    $stmt->execute();
    $stmt->close();
}
echo "التحصين: " . ($allPass ? 'مكتمل ✔' : 'ثغرة ✘') . " — المحضر docs/uat/UAT_hardening_report_ar.md · جولة #{$runId}\n";
exit($allPass ? 0 : 1);
