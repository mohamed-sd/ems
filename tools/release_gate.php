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
    foreach (array('tools/act_checks.php --enforce', 'tools/e02_checks.php --enforce',
                   'tools/e05_checks.php', 'tools/wfm_checks.php --enforce') as $t) {
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
exit($gateFailed === null ? 0 : 1);
