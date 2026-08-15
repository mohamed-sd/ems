<?php
/**
 * tests/canonical_map_test.php — رمزُ الوثيقةِ ⇄ مسارٌ موجودٌ ⇄ مالكٌ واحد
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0457 · INJ-0476
 * · «لكل رمزِ شاشةٍ في **§٨-٤** صفٌّ يشير إلى **مسارٍ موجودٍ على القرص**؛
 *    صفرُ رمزٍ بلا مطابقة».
 * · «ولكلِّ رمزٍ في **§١١-٤** صفٌّ يشير إلى مسارٍ موجودٍ **ومالكٍ واحد**؛
 *    وصفرُ رمزٍ بلا مطابقة».
 *
 * ── ويقيس أوسعَ من البندين عمدًا ─────────────────────────────────────────
 * الحكمُ نُصَّ على قسمين، والعلّةُ في **ثمانيةَ عشرَ**. فالشاهدُ يُلزم القسمين
 * نصًّا ويحرس البقيةَ بالفاحصِ العامّ — فلا يعود النمطُ من بابٍ آخر.
 *
 * ◆ **والاختبارُ السلبيُّ شرطُ صحته (GT-01)**: يُفسَد صفٌّ (مسارٌ ميتٌ ثم مالكٌ
 *   محذوف) فيجب أن **يرسبَ** الفاحصُ ويسمّيَ الرمزَ — ثم يُستعاد الصفُّ
 *   بقيمتِه الأصليةِ المحفوظةِ قبل الإفساد. والاستعادةُ من لقطةٍ لا من ذاكرة.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
ob_start(); require_once $ROOT . '/config.php'; ob_end_clean();
while (ob_get_level() > 0) { ob_end_clean(); }

$conn = $GLOBALS['conn'];
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$scan = function () use ($ROOT) {
    $o = array(); $rc = 0;
    @exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($ROOT . '/tools/fix_canonical_map_scan.php') . ' 2>&1', $o, $rc);
    return array($rc, implode("\n", $o));
};
$say('══ رمزُ الوثيقةِ ⇄ مسارٌ موجودٌ ⇄ مالكٌ واحد (INJ-0457 · INJ-0476)');

/* ── ① نصُّ الحكمِ حرفًا: §٨-٤ و§١١-٤ ────────────────────────────────────── */
$say("\n── ① القسمانِ المنصوصُ عليهما، رمزًا رمزًا");
$FLEET = array('equipments.php', 'equip_models.php', 'equip_docs.php', 'equip_meters.php',
    'asset_recon.php', 'depreciation.php', 'fleet_owners.php', 'equip_types.php',
    'dep_profiles.php', 'equipment_sourcing.php', 'code_bridge.php',
    'risk_dept_flt.php', 'gov_dept_flt.php');
$HR = array('employees.php', 'emp_contracts.php', 'recruitment.php', 'attendance.php',
    'leaves.php', 'deductions.php', 'advances.php', 'payroll.php', 'worker_evaluation.php',
    'final_settlement.php', 'project_contracts.php', 'risk_dept_hrm.php', 'gov_dept_hrm.php');

$judge = function (array $codes, $label) use ($conn, $ROOT, $ok) {
    $noRow = array(); $deadPath = array(); $noOwner = array(); $manyOwner = array();
    foreach ($codes as $code) {
        $e = $conn->real_escape_string($code);
        $r = $conn->query("SELECT canonical_file, real_path, owner_dept FROM nav09_file_map
                            WHERE canonical_file = '{$e}'");
        if ($r === false) { $noRow[] = $code . ' (استعلامٌ راسب)'; continue; }
        $rows = array();
        while ($x = $r->fetch_assoc()) { $rows[] = $x; }
        if (!$rows)              { $noRow[] = $code;     continue; }
        if (count($rows) > 1)    { $manyOwner[] = $code; continue; }
        $p = trim((string) $rows[0]['real_path']);
        if ($p === '' || !is_file($ROOT . '/' . $p)) { $deadPath[] = $code . '→' . ($p ?: '∅'); continue; }
        if (trim((string) $rows[0]['owner_dept']) === '') { $noOwner[] = $code; }
    }
    $ok(!$noRow,     $label . ' · لكلِّ رمزٍ صفٌّ في السجلّ (' . count($codes) . ' رمزًا)', implode(' · ', $noRow));
    $ok(!$deadPath,  $label . ' · وكلُّ مسارٍ **موجودٌ على القرص**', implode(' · ', $deadPath));
    $ok(!$manyOwner, $label . ' · ولا رمزَ بصفَّين', implode(' · ', $manyOwner));
    $ok(!$noOwner,   $label . ' · **ومالكٌ واحدٌ** لكلِّ رمز', implode(' · ', $noOwner));
};
$judge($FLEET, '§٨-٤ الأسطول');
$judge($HR, '§١١-٤ الموارد البشرية');

/* والمالكُ واحدٌ لا اثنان: الشاشتانِ المضافتانِ تتبعان إدارتَيهما لا الحوكمة */
$r = $conn->query("SELECT canonical_file, owner_dept FROM nav09_file_map
                    WHERE canonical_file IN ('risk_dept_flt.php','gov_dept_flt.php',
                                             'risk_dept_hrm.php','gov_dept_hrm.php')");
$owners = array();
while ($r && ($x = $r->fetch_assoc())) { $owners[$x['canonical_file']] = $x['owner_dept']; }
$ok(isset($owners['risk_dept_flt.php']) && $owners['risk_dept_flt.php'] === 'إدارة الأسطول'
 && isset($owners['gov_dept_flt.php'])  && $owners['gov_dept_flt.php']  === 'إدارة الأسطول',
    'ومالكُ شاشتَي الأسطولِ «إدارة الأسطول» — مشتقٌّ من أغلبيةِ قسمِه لا مُختَرَع');
$ok(isset($owners['risk_dept_hrm.php']) && $owners['risk_dept_hrm.php'] === 'الموارد البشرية'
 && isset($owners['gov_dept_hrm.php'])  && $owners['gov_dept_hrm.php']  === 'الموارد البشرية',
    'ومالكُ شاشتَي الموارد «الموارد البشرية»');

/* ── ② الحارسُ العامُّ على الإداراتِ الثمانيَ عشرةَ ───────────────────────── */
$say("\n── ② الفاحصُ العامُّ: ١٨ إدارةً لا قسمين");
list($rc, $txt) = $scan();
$ok($rc === 0, 'يمرُّ على السجلِّ الحيّ (خروج=' . $rc . ')',
    mb_substr(preg_replace('~\s+~', ' ', $txt), 0, 200));
$ok(preg_match('~الرموزُ المقيسة: (\d+) · مطابقٌ تامًّا: (\d+)~u', $txt, $m)
    && (int) $m[1] >= 200 && $m[1] === $m[2],
    'ويقيس ' . (isset($m[1]) ? $m[1] : '؟') . ' رمزًا كلُّها مطابقة — رقمانِ متطابقان');

/* ── ③ الاختبارُ السلبيّ: أفسِد وأثبِت الرسوب (GT-01) ────────────────────── */
$say("\n── ③ الاختبارُ السلبيّ: فاحصٌ لا يرسب عند الإفساد يصادق على نفسِه");
$VICTIM = 'risk_dept_hrm.php';
$snap = null;
$r = $conn->query("SELECT canonical_file, title_ar, owner_dept, state, real_path, note
                     FROM nav09_file_map WHERE canonical_file = '{$VICTIM}'");
if ($r && ($snap = $r->fetch_assoc())) { $ok(true, 'لقطةٌ للصفِّ قبل الإفساد: ' . $snap['real_path']); }
else { $ok(false, 'تعذّرت اللقطةُ — لا إفسادَ بلا استعادةٍ مضمونة'); }

if ($snap) {
    /* ⓐ مسارٌ ميت */
    $u = $conn->query("UPDATE nav09_file_map SET real_path = 'Risk/__no_such_file__.php'
                        WHERE canonical_file = '{$VICTIM}'");
    $ok($u !== false && $conn->affected_rows === 1, 'أُفسد المسارُ إلى ملفٍّ لا وجودَ له');
    list($rc1, $t1) = $scan();
    $ok($rc1 === 1, 'رسب الفاحصُ (خروج=' . $rc1 . ')');
    $ok(strpos($t1, $VICTIM) !== false, 'وسمّى الرمزَ المُفسَدَ بعينِه: ' . $VICTIM);
    $ok(mb_strpos($t1, 'مسارٌ لا وجودَ له على القرص') !== false, 'وسمّى سببَ الرسوبِ بدقّة');

    /* ⓑ مالكٌ محذوف */
    $conn->query("UPDATE nav09_file_map SET real_path = '" . $conn->real_escape_string($snap['real_path'])
        . "', owner_dept = '' WHERE canonical_file = '{$VICTIM}'");
    list($rc2, $t2) = $scan();
    $ok($rc2 === 1, 'ورسب أيضًا على مالكٍ فارغ (خروج=' . $rc2 . ')');
    $ok(mb_strpos($t2, 'صفٌّ بلا مالك') !== false, 'وسمّى السببَ الثانيَ لا الأولَ — فالتمييزُ قائم');

    /* الاستعادةُ من اللقطةِ لا من الذاكرة */
    $st = $conn->prepare("UPDATE nav09_file_map
                             SET title_ar = ?, owner_dept = ?, state = ?, real_path = ?, note = ?
                           WHERE canonical_file = ?");
    $st->bind_param('ssssss', $snap['title_ar'], $snap['owner_dept'], $snap['state'],
        $snap['real_path'], $snap['note'], $snap['canonical_file']);
    $st->execute();
    $st->close();
    $r = $conn->query("SELECT real_path, owner_dept FROM nav09_file_map WHERE canonical_file = '{$VICTIM}'");
    $back = $r ? $r->fetch_assoc() : null;
    $ok($back && $back['real_path'] === $snap['real_path'] && $back['owner_dept'] === $snap['owner_dept'],
        'واستُعيد الصفُّ إلى قيمتِه الأصليةِ حرفًا');
    list($rc3, ) = $scan();
    $ok($rc3 === 0, 'وعاد الفاحصُ أخضرَ — فالرسوبُ كان بالإفسادِ لا بعطبٍ فينا');
}

$say("\n══ النتيجة: ناجحٌ {$PASS} · راسبٌ {$FAIL}");
$say("PASS={$PASS} · FAIL={$FAIL}");   /* الصيغةُ التي يقرأها `tests/_regression.php` */
exit($FAIL > 0 ? 1 : 0);
