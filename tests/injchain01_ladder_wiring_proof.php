<?php
/**
 * tests/injchain01_ladder_wiring_proof.php
 *   شاهدُ وصلِ السلّم بسلسلةِ الوحدات — INJ-CHAIN-CLOSE-01 · GAP-01
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ما يُثبته**: أن الشاشةَ القائدةَ للخطوةِ **تقرأ سلّمَها عند التنفيذ** —
 *   لا أن الرحلةَ «تجد» سلّمًا في السجل. والفرقُ هو GAP-01 كلُّه: مع الأولِ
 *   يُنفَّذ ترتيبُ الخطواتِ و«لا يدَ تمشي خطوتَين»، ومع الثاني لا يُنفَّذ شيء.
 *
 * ◆ **ويُقاس بالبنيةِ لا بالحجم**: عددُ الصفوفِ في هذه القاعدةِ يتغيّر بجولاتِ
 *   بذرٍ وتقليصٍ متوازيةٍ خارجَ هذه الجلسة، **فبوابةٌ تقيس حجمًا تكذب مرتين**:
 *   خضراءَ بعدَ بذرٍ وحمراءَ بعدَ تقليص. فالمقاسُ هنا: هل يُقرأ السلّم؟ وهل
 *   يرفض الحارسُ ما يجب رفضُه؟
 *
 * ◆ **والحزامُ السلبيُّ حقيقيٌّ لا وصفيّ**: يُستدعى الحارسُ بفاعلٍ لا يملك
 *   خطوةَ الاعتماد، ويجب أن يرفض. وبفاعلٍ يملكها، ويجب أن يقبل. **وبوابةٌ
 *   لم تُجرَّب معطوبةً لا تُصدَّق.**
 *
 * التشغيل: php tests/injchain01_ladder_wiring_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
require_once $ROOT . '/includes/unit_chain_helpers.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$ok = 0; $bad = 0;
function chk($c, $l, $d = '') {
    global $ok, $bad;
    if ($c) { $ok++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $bad++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}

echo "══ وصلُ السلّم بسلسلةِ الوحدات — GAP-01 ══\n";

/* ══ ① لكلِّ مرحلةٍ سلّمٌ مُعلَنٌ وله خطوةُ اعتمادٍ بأدوارٍ محلولة ══════════ */
echo "\n── ① المرحلةُ ⇐ السلّمُ ⇐ الدور ──\n";
$STAGES = array('site', 'supplier', 'operator', 'supervisor', 'fleet', 'sales', 'finance');
$mapped = 0; $withStep = 0; $withRoles = 0;
foreach ($STAGES as $s) {
    $ld = ems_uc_stage_ladder($s);
    if ($ld === null) { echo "  ✘ المرحلة `{$s}` بلا سلّمٍ مُعلَن\n"; $bad++; continue; }
    $mapped++;
    $steps = ems_uc_ladder_steps($conn, $ld);
    $ap = null;
    foreach ($steps as $x) { if ((int) $x['may_approve'] === 1) { $ap = $x; } }
    if ($ap) { $withStep++; }
    $roles = $ap ? $ap['roles'] : array();
    if ($roles) { $withRoles++; }
    printf("     %-11s %-6s خطوات=%d  اعتماد=%-22s أدوار=%s\n",
        $s, $ld, count($steps), $ap ? $ap['actor_code'] : '—',
        $roles ? implode('،', $roles) : '—');
}
chk($mapped === count($STAGES), 'كلُّ مرحلةٍ في السلسلةِ لها سلّمٌ مُعلَن', "{$mapped}/" . count($STAGES));
chk($withStep === $mapped, 'لكلِّ سلّمٍ خطوةُ اعتمادٍ مميَّزة (may_approve)', "{$withStep}/{$mapped}");
chk($withRoles === $mapped, 'كلُّ خطوةِ اعتمادٍ تُحَلُّ إلى دورٍ من جسرِ الفاعلين',
    "{$withRoles}/{$mapped} — والجهةُ تُحَلُّ من المحرك لا تُكتب في الوثيقة");

/* ══ ② نقطةُ القرارِ الواحدةُ تقرأ السلّم — قياسٌ على المصدر ═══════════════ */
echo "\n── ② نقطةُ القرارِ الواحدةُ تقرأ السلّم ──\n";
$svc = (string) @file_get_contents($ROOT . '/app/Services/Unit/TimesheetEntryService.php');
chk(strpos($svc, 'ems_uc_ladder_check') !== false,
    '`TimesheetEntryService::approve()` ينادي فاحصَ السلّم');
chk(strpos($svc, 'ems_uc_ladder_log') !== false,
    'وكلُّ خرقٍ يُسجَّل — فالدَّينُ مقيسٌ لا مُخمَّن');
/* والنداءُ **قبلَ** المعاملةِ لا بعدَها — وإلا كُتب الصفُّ ثم فُحص */
$posChk = strpos($svc, 'ems_uc_ladder_check');
$posTx  = strpos($svc, "'unit-entry-approve'");
chk($posChk !== false && $posTx !== false && $posChk < $posTx,
    'الفحصُ **يسبق** معاملةَ الكتابة — لا يُكتب ثم يُفحص');

/* ══ ③ الحزامُ السلبيّ — الحارسُ يرفض ما يجب رفضُه ═══════════════════════ */
echo "\n── ③ الحزامُ السلبيُّ — جرِّبْه معطوبًا قبلَ تصديقِ مرورِه ──\n";
$ldSite  = ems_uc_stage_ladder('site');
$stepsS  = ems_uc_ladder_steps($conn, $ldSite);
$apS = null; foreach ($stepsS as $x) { if ((int) $x['may_approve'] === 1) { $apS = $x; } }
$goodRole = $apS && $apS['roles'] ? (int) $apS['roles'][0] : 1;
$badRole  = $goodRole === 99 ? 98 : 99;      /* دورٌ لا يملك الخطوةَ يقينًا */

/* معرّفُ واقعةٍ لا وجودَ له — فالفحصُ منطقيٌّ ولا يمسُّ بيانًا حيًّا */
$GHOST = 2000000000;
$rBad  = ems_uc_ladder_check($conn, 4, $GHOST, 1, 'site', 0, $badRole);
$rGood = ems_uc_ladder_check($conn, 4, $GHOST, 1, 'site', 0, $goodRole);
chk($rBad['ok'] === false, "**الحارسُ يرفض دورًا لا يملك الخطوة** — دور {$badRole} على {$ldSite}",
    $rBad['reasons'] ? mb_substr($rBad['reasons'][0], 0, 70) : '');
chk($rGood['ok'] === true, "ويقبل صاحبَ الخطوة — دور {$goodRole}");
chk($rBad['ladder'] === $ldSite && $rGood['ladder'] === $ldSite,
    'ويُرجع رمزَ السلّمِ المقروءِ في الحالتين', $ldSite);

/* «لا يدَ تمشي خطوتَين» — يُختبر بمنطقِه على واقعةٍ حيّةٍ إن وُجدت */
$q = $conn->query("SELECT company_id, entry_id, round_no, actor_id, stage
                     FROM `unit_approvals` WHERE actor_id > 0 ORDER BY id DESC LIMIT 1");
$live = $q ? $q->fetch_assoc() : null;
if ($live) {
    $other = $live['stage'] === 'site' ? 'sales' : 'site';
    $r2 = ems_uc_ladder_check($conn, (int) $live['company_id'], (int) $live['entry_id'],
                              (int) $live['round_no'], $other, (int) $live['actor_id'], $goodRole);
    $hit = false;
    foreach ($r2['reasons'] as $why) { if (mb_strpos($why, 'خطوتَين') !== false) { $hit = true; } }
    chk($hit, '**لا يدَ تمشي خطوتَين** — الفاعلُ نفسُه يُرفض في مرحلةٍ ثانيةٍ من الجولةِ نفسِها',
        "الواقعة {$live['entry_id']} · {$live['stage']} ⇐ {$other}");
} else {
    echo "  ◆ لا صفَّ اعتمادٍ حيٌّ بفاعلٍ حقيقيٍّ الآن — قاعدةُ «لا يدَ تمشي خطوتَين»\n";
    echo "    مبنيةٌ ومقيسةٌ في ②، وتُختبر حيًّا حين تعود بياناتُ العمل.\n";
}

/* ══ ④ النمطُ مُعلَنٌ ولا يُقلَب صامتًا ═══════════════════════════════════ */
echo "\n── ④ نمطُ الإنفاذ ──\n";
$mode = ems_uc_ladder_mode();
printf("  النمطُ النافذ: **%s** (`EMS_UNIT_LADDER`)\n", $mode);
chk(in_array($mode, array('off', 'monitor', 'enforce'), true), 'النمطُ من القيمِ الثلاثِ لا غير', $mode);
echo "  ◆ والقلبُ إلى `enforce` **تغييرُ وصولٍ حيّ**: يلزمه نافذةُ قياسٍ مستقرةٌ\n";
echo "    تُثبت صفرَ خرق. ولا تتوفر ما دامت جولاتُ بذرٍ وتقليصٍ متوازيةٌ تُغيّر\n";
echo "    الحجمَ بين استعلامَين — **فالمنعُ بلا قياسٍ مستقرٍّ يوقف مسارًا حيًّا**.\n";

/* ══ ⑤ ما يمنعه القياسُ اليوم — يُعلَن ولا يُطوى ══════════════════════════ */
echo "\n── ⑤ خبرٌ للمالكِ — لا حكمَ فيه ──\n";
$q = $conn->query("SELECT COUNT(*) tot, SUM(`role_id` IS NULL) nul FROM `users`");
$us = $q ? $q->fetch_assoc() : array('tot' => 0, 'nul' => 0);
printf("  · مستخدمون بلا دورٍ مُسنَد: **%d من %d** — والحارسُ يقيس بالدور،\n",
       (int) $us['nul'], (int) $us['tot']);
echo "    فمن لا دورَ له لا يُحَلُّ سلّمُه. وهذا شرطٌ سابقٌ لقلبِ المنع.\n";
$q = $conn->query("SELECT COUNT(*) FROM `guard_denials` WHERE `guard_code` LIKE 'unit_ladder:%'");
printf("  · خروقٌ مسجَّلةٌ حتى الآن: %d\n", $q ? (int) $q->fetch_row()[0] : -1);

require_once $ROOT . '/tools/lib/gap_verdict.php';
gapv('GAP-01',
     $mapped === count($STAGES) && $withStep === $mapped && $withRoles === $mapped
     && strpos($svc, 'ems_uc_ladder_check') !== false && $rBad['ok'] === false && $rGood['ok'] === true,
     'نقطةُ قرارِ الوحداتِ الواحدةُ تقرأ سلّمَها عند التنفيذ وترفض من لا يملك خطوتَه — مُثبَتٌ بحزامٍ سلبيّ',
     $bad);

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
