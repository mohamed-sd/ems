<?php
/**
 * tools/release_gate.php — بوابات التسليم الخمس (E-06 · TS-04 · AC-E06-04)
 * ───────────────────────────────────────────────────────────────────────────
 * «خمسُ بواباتٍ متتالية لا تُقفز: الوحدة · التكامل · الأمان والعزل · قبول
 * المستخدم · الانحدار» — هذا المُشغِّل يعبرها بالترتيب ويكتب شهادةَ العبور
 * (docs/RELEASE_GATE_LAST_ar.md) بشاهدِ كلِّ بوابة. قفزُ بوابةٍ غيرُ ممكنٍ
 * بنيويًّا: الفشلُ يوقف ما بعده ويسمّي الناقص.
 * قبولُ المستخدم لا يُؤتمت («العرضُ ليس قبولًا») — بوابتُه تفحص وجودَ محضرِ
 * قبولٍ أحدثَ من آخر تغييرٍ ملتزَم، وإلا أعلنت النقصَ ولم تدَّعِ.
 *
 * الاستعمال: php tools/release_gate.php [--skip-uat-doc]
 * الخروج: 0 = الشهادة كاملة · 1 = بوابةٌ لم تُعبر (الدمج/النشر ممنوع)
 */
define('EMS_CLI', true);
$ROOT = dirname(__DIR__);
$PHP = PHP_BINARY;
$SKIP_UAT_DOC = in_array('--skip-uat-doc', $argv, true);

/* اتصال القاعدة لبصمة الإصدار الآلية (BR-GOV-08) — config يبتلع مخرج CLI */
ob_start();
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

$report = array();
$say = function ($s) use (&$report) { fwrite(STDOUT, $s . "\n"); $report[] = $s; };

function run_step($PHP, $ROOT, $script, $args = '')
{
    $cmd = escapeshellarg($PHP) . ' ' . escapeshellarg($ROOT . '/' . $script) . ($args ? ' ' . $args : '') . ' 2>&1';
    $out = array(); $code = 0;
    exec($cmd, $out, $code);
    return array('code' => $code, 'tail' => implode("\n", array_slice($out, -2)));
}

$say('════ بوابات التسليم الخمس — ' . date('Y-m-d H:i') . ' ════');
$gateFailed = null;

/* ═ البوابة ① الوحدة: المنطق في عزلة — صفر إخفاق حرج ═ */
$say('');
$say('▌ البوابة ① — اختبار الوحدة (يشهد: المطوّر)');
$unitTests = array('tests/wfm_engine_test.php', 'tests/leg01_patterns34_test.php');
foreach ($unitTests as $t) {
    if (!is_file($ROOT . '/' . $t)) { $say("  ⚪ {$t} غير موجود"); continue; }
    $r = run_step($PHP, $ROOT, $t);
    $say(($r['code'] === 0 ? '  ✔ ' : '  ✘ ') . $t . ' — ' . trim($r['tail']));
    if ($r['code'] !== 0 && $gateFailed === null) { $gateFailed = '① الوحدة: ' . $t; }
}

/* ═ البوابة ② التكامل: الأحداث والمحرّكات بين الوحدات ═ */
if ($gateFailed === null) {
    $say('');
    $say('▌ البوابة ② — التكامل (يشهد: قائد التطوير)');
    /* AC-T12: الفحوصُ العشرةُ (CK-01..10) وفاحصُ سلامةِ الترحيلِ بوابةُ دمجٍ
       ترسب افتراضًا — رسوبُ أيِّ فحصٍ يوقف العبورَ هنا لا في تقريرٍ لاحق. */
    /* e05 بلا --enforce كان يطبع خرقًا حاكمًا ويخرج 0 — فتعبره البوابةُ وهو أحمر */
    foreach (array('tools/act_checks.php --enforce', 'tools/e02_checks.php --enforce',
                   'tools/e05_checks.php --enforce', 'tools/wfm_checks.php --enforce',
                   'tools/se03_ten_checks.php', 'tools/fin01_posting_verify.php',
                   'tools/uxw_visual_baseline.php', 'tools/uxw_gates.php',
                   'tools/govauth_checks.php', 'tools/govauth_parity_probe.php') as $t) {
        list($f, $a) = array_pad(explode(' ', $t, 2), 2, '');
        $r = run_step($PHP, $ROOT, $f, $a);
        $say(($r['code'] === 0 ? '  ✔ ' : '  ✘ ') . $f . ' — ' . trim($r['tail']));
        if ($r['code'] !== 0 && $gateFailed === null) { $gateFailed = '② التكامل: ' . $f; }
    }
} else { $say(''); $say('▌ البوابة ② — لم تُفتح (السابقة لم تُعبر)'); }

/* ═ البوابة ③ الأمان والعزل: صفر تسرب وصفر نداء محمي يمر ═ */
if ($gateFailed === null) {
    $say('');
    $say('▌ البوابة ③ — الأمان والعزل (يشهد: الحوكمة)');
    foreach (array('tools/nav09_verify.php', 'tools/nav_seven_guard.php', 'tools/sec_perm_checks.php') as $f) {
        $r = run_step($PHP, $ROOT, $f);
        $say(($r['code'] === 0 ? '  ✔ ' : '  ✘ ') . $f . ' — ' . trim($r['tail']));
        if ($r['code'] !== 0 && $gateFailed === null) { $gateFailed = '③ الأمان: ' . $f; }
    }
} else { $say(''); $say('▌ البوابة ③ — لم تُفتح'); }

/* ═ البوابة ④ قبول المستخدم: تنفيذٌ لا مشاهدة — محضرٌ لا ادعاء ═ */
if ($gateFailed === null) {
    $say('');
    $say('▌ البوابة ④ — قبول المستخدم (يشهد: المستخدمون)');
    $uat = $ROOT . '/docs/UAT_SIGNOFF_ar.md';
    if ($SKIP_UAT_DOC) {
        $say('  ⚪ أُعلن تخطي فحص المحضر (--skip-uat-doc) — البوابة تبقى بيد المستخدمين');
    } elseif (!is_file($uat)) {
        $say('  ✘ لا محضرَ قبولٍ (docs/UAT_SIGNOFF_ar.md) — «العرضُ ليس قبولًا» ولا يُدَّعى');
        $gateFailed = '④ قبول المستخدم: المحضر غائب';
    } else {
        // المحضر يجب أن يكون أحدث من آخر التزام كودي (وإلا فهو قبولُ نسخةٍ قديمة)
        $lastCommit = 0;
        exec('git -C ' . escapeshellarg($ROOT) . ' log -1 --format=%ct 2>&1', $o, $c);
        if ($c === 0 && isset($o[0])) { $lastCommit = intval($o[0]); }
        $ok = filemtime($uat) >= $lastCommit;
        $say(($ok ? '  ✔ ' : '  ✘ ') . 'محضر القبول ' . date('Y-m-d H:i', filemtime($uat))
            . ($ok ? ' — أحدثُ من آخر التزام' : ' — أقدمُ من آخر التزام: قبولُ نسخةٍ قديمةٍ لا يُعتد به'));
        if (!$ok) { $gateFailed = '④ قبول المستخدم: المحضر أقدم من التغيير'; }
    }
} else { $say(''); $say('▌ البوابة ④ — لم تُفتح'); }

/* ═ البوابة ⑤ الانحدار: ما كان يعمل ما زال — إعادةُ الحاكمة كلِّها ═ */
if ($gateFailed === null) {
    $say('');
    $say('▌ البوابة ⑤ — الانحدار (يشهد: ضمان الجودة)');
    $r1 = run_step($PHP, $ROOT, 'tests/wfm_engine_test.php');
    $r2 = run_step($PHP, $ROOT, 'tools/e02_checks.php', '--enforce');
    $ok = ($r1['code'] === 0 && $r2['code'] === 0);
    $say(($ok ? '  ✔ ' : '  ✘ ') . 'إعادةُ الوحدة والتكامل الحاكمة — صفرُ إخفاقٍ فيما كان ناجحًا');
    if (!$ok) { $gateFailed = '⑤ الانحدار'; }
} else { $say(''); $say('▌ البوابة ⑤ — لم تُفتح'); }

/* ═ الشهادة ═ */
$say('');
$say('──────────────────────────────────────────────');
if ($gateFailed === null) {
    $say('الحكم: ✔ عُبرت البوابات الخمس — شهادةُ العبور مكتوبة');
} else {
    $say('الحكم: ✘ توقّف عند: ' . $gateFailed . ' — قفزُ البوابة يؤجّل العيبَ إلى الإنتاج');
}
$cert = "# شهادة بوابات التسليم — آخر عبور\n\n"
      . "**التاريخ:** " . date('Y-m-d H:i') . " · **الحكم:** "
      . ($gateFailed === null ? '✔ عبور كامل' : '✘ توقف عند ' . $gateFailed) . "\n\n```\n"
      . implode("\n", $report) . "\n```\n";
file_put_contents($ROOT . '/docs/RELEASE_GATE_LAST_ar.md', $cert);

/* BR-GOV-08 (م-هـ): «لا نشرَ بلا بصمةٍ وتقريرِ اكتمال» — البصمة تُختم آليًّا
   من الشهادة نفسها لا يدويًّا: sha1(الشهادة) + رأس git لحظة العبور، صفًّا
   في سجل الإصدارات (scr_release_stamp) بعطالة البصمة. العبور الكامل وحده
   يُختم — المتوقف يُسجَّل بشاهده متوقفًا. */
$head = trim((string) shell_exec('git -C "' . $ROOT . '" rev-parse --short HEAD 2>&1'));
$fp = 'RG-' . substr(sha1($cert), 0, 12) . '-' . preg_replace('/[^a-f0-9]/', '', $head);
$ex = mysqli_query($conn, "SELECT id FROM scr_release_stamp WHERE fingerprint_release = '"
    . mysqli_real_escape_string($conn, $fp) . "' LIMIT 1");
if (!($ex && mysqli_fetch_row($ex))) {
    $st = $conn->prepare("INSERT INTO scr_release_stamp
        (company_id, no_release, fingerprint_release, date_publish, type_release,
         migrations_executed, report_completeness, tests_passed, tests_failed, flag_rollback,
         publisher_name_capacity_role, status, status_label, is_seed, created_by, created_by_name)
        VALUES (4, ?, ?, CURDATE(), 'بوابات التسليم',
                'من مُشغِّل الترحيلات (status)', ?, ?, ?, 'جاهز — التراجع بلقطات ما قبل التنفيذ',
                'مُشغِّل البوابات — آليًّا (BR-GOV-08)', ?, ?, 0, 0, 'release_gate آليًّا')");
    $no = 'REL-' . date('Ymd-Hi');
    /* عبورٌ بتخطي فحصِ محضرِ القبولِ ليس نشرًا: البوابةُ الرابعةُ بيدِ المستخدمين
       نصًّا («العرضُ ليس قبولًا»)، وختمُ «منشور» على إصدارٍ لم يشهد له مستخدمٌ
       هو بعينِه الأخضرُ الكاذبُ الذي تمنعه هذه البوابات. فالحالةُ الثالثةُ تُقال
       صراحةً: مشروطٌ بقبولِ المستخدم. */
    $comp = $gateFailed === null
        ? 'docs/RELEASE_GATE_LAST_ar.md — عبور كامل' . ($SKIP_UAT_DOC ? ' (④ بتخطٍّ معلَن)' : '')
        : 'متوقف عند ' . $gateFailed;
    $tp = $gateFailed === null ? 'البوابات الخمس' : 'حتى ما قبل ' . $gateFailed;
    $tf = $gateFailed === null ? '0' : $gateFailed;
    $stt = $gateFailed !== null ? 'متوقف'
         : ($SKIP_UAT_DOC ? 'مشروط بقبول المستخدم' : 'منشور');
    $st->bind_param('sssssss', $no, $fp, $comp, $tp, $tf, $stt, $stt);
    $st->execute();
    $st->close();
    $say('✎ بصمة الإصدار خُتمت آليًّا: ' . $fp . ' (' . $stt . ')');
}
exit($gateFailed === null ? 0 : 1);
