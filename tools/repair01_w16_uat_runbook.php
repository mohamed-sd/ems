<?php
/**
 * tools/repair01_w16_uat_runbook.php — دليلُ مشيِ المحطّاتِ الثلاثَ عشرة
 * ═══════════════════════════════════════════════════════════════════════════
 * **البندُ ٦٣**: «موظّفٌ فعليٌّ أو `UAT User` يمثّل دورَه الحقيقيَّ يمرُّ الرحلةَ
 * من بدايتها لنهايتها ⛔ **ولا `Seed Data` فقط**».
 *
 * ◆ **ووثيقةُ الرحلةِ تقول «ماذا» ولا تقول «أين» ولا «بأيِّ حساب»** — وتلك هي
 *   الفجوةُ التي تمنع الإنسانَ من المشي. فهذا الدليلُ **يربط كلَّ محطّةٍ
 *   بشاشتِها المقيسةِ وبدورِها المحلول** ⛔ ولا يخترع خطوة.
 *
 * ⛔ **ولا يكتب هذا الدليلُ `PASSED`**: القيدُ `chk_w16_uat_real` يردُّ في
 *   القاعدةِ إعلانَ نجاحٍ بلا فاعلٍ حقيقيٍّ وزمنٍ ودليل. **فالدليلُ يُمهّد
 *   ولا يمرّ** — والمرورُ فعلُ إنسانٍ لا مخرَجُ أداة.
 *
 * ⚠ **وحلُّ الدورِ من الوصفِ العربيِّ فاشل**: `required_role` **وصفٌ نثريٌّ**
 *   («فني الصيانة» · «صاحب الحساب») **لا مفتاحٌ في `roles`** — صفرُ مطابقةٍ من
 *   سبع. **والمفتاحُ الصلبُ `domain_code`**، ومنه إلى الدورِ باسمِ الإدارةِ
 *   المعياريّ. ◆ ونمطُ العطبِ نفسُه: **مقارنةُ تمثيلَين لشيءٍ واحد**.
 *
 * التشغيل: php tools/repair01_w16_uat_runbook.php [--md]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');
$MD = in_array('--md', $argv, true);
$e = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };

/* ── تطبيعٌ عربيٌّ خفيف — ولا حذفَ كلمات ─────────────────────────────────── */
$nz = function ($s) {
    $s = str_replace(array('أ', 'إ', 'آ'), 'ا', (string) $s);
    $s = str_replace('ة', 'ه', $s);
    $s = str_replace(array('ـ', "\xC2\xA0"), array('', ' '), $s);
    return preg_replace('~\s+~u', ' ', trim($s));
};

/* ── ① النطاق ⇐ الدور: من الاسمِ المعياريِّ لا من الوصفِ النثريّ ─────────── */
$roles = array();
$r = $conn->query("SELECT id, name FROM roles");
while ($r && ($x = $r->fetch_assoc())) { $roles[$nz($x['name'])] = array((int) $x['id'], $x['name']); }
$depRole = array();
$r = $conn->query("SELECT canonical_code, name_ar FROM repair01_departments");
while ($r && ($x = $r->fetch_assoc())) {
    $k = $nz($x['name_ar']);
    if (isset($roles[$k])) { $depRole[$x['canonical_code']] = $roles[$k] + array(2 => 'مطابقة'); continue; }
    foreach ($roles as $rk => $rv) {
        if (mb_strpos($rk, $k) !== false || mb_strpos($k, $rk) !== false) {
            $depRole[$x['canonical_code']] = $rv + array(2 => 'احتواء'); break;
        }
    }
}

/* ── ② الشاشاتُ التي يراها الدورُ في نطاقِه — من المنحِ الحيّ ─────────────── */
$screensFor = function ($dep, $roleId) use ($conn, $e) {
    $out = array();
    $q = $conn->query("SELECT DISTINCT sr.route, sr.canonical_label_ar
                         FROM repair01_screen_registry sr
                         JOIN modules m ON m.code LIKE CONCAT('%', sr.screen_file)
                         JOIN role_permissions rp ON rp.module_id = m.id
                        WHERE sr.owner_code = '" . $e($dep) . "' AND sr.on_disk = 1
                          AND sr.ownership_verdict NOT IN ('RETIRE')
                          AND rp.role_id = " . (int) $roleId . " AND rp.can_view = 1
                        ORDER BY sr.canonical_label_ar LIMIT 40");
    while ($q && ($x = $q->fetch_assoc())) { $out[$x['route']] = $x['canonical_label_ar']; }
    return $out;
};

/* ── ③ اختيارُ شاشةِ المحطّةِ بكلماتِ وصفِها — والمرشَّحُ يُعرض لا يُفرَض ──── */
/* ⚠ **والتسجيلُ على اسمِ الإدارةِ يُصيب كلَّ شاشاتِها**: «الموقع» و«الصيانة»
     تردان في وصفِ المحطّةِ وفي اسمِ كلِّ شاشةٍ في الإدارة — **فصار كلُّ سطحٍ
     مرشَّحًا، وترشيحُ الكلِّ ليس ترشيحًا**. ⇒ تُستبعَد كلماتُ اسمِ الإدارةِ
     ومفرداتُ الحوكمةِ العامّةِ من الوزن، **ويبقى الفعلُ هو الدليل**. */
$pick = function ($desc, $screens, $depName = '') use ($nz) {
    $stop = array('اداره', 'ادارة', 'حوكمه', 'مخاطر', 'لوحه', 'اعدادات', 'مؤشرات', 'من', 'في', 'على');
    foreach (preg_split('~\s+~u', $nz($depName)) as $w) { if (mb_strlen($w) > 2) { $stop[] = $w; } }
    $d = $nz($desc);
    $best = array(); $bestScore = 0;
    foreach ($screens as $rt => $lab) {
        $score = 0;
        foreach (preg_split('~\s+~u', $nz($lab)) as $w) {
            if (mb_strlen($w) < 3 || in_array($w, $stop, true)) { continue; }
            if (mb_strpos($d, $w) !== false) { $score++; }
        }
        if ($score > $bestScore) { $bestScore = $score; $best = array($rt => $lab); }
        elseif ($score > 0 && $score === $bestScore) { $best[$rt] = $lab; }
    }
    return array($best, $bestScore);
};

$rows = array(); $bound = 0; $unbound = 0;
$q = $conn->query("SELECT * FROM repair01_w16_uat ORDER BY journey_key, station_no");
while ($q && ($x = $q->fetch_assoc())) {
    $dep = $x['domain_code'];
    $rl  = isset($depRole[$dep]) ? $depRole[$dep] : null;
    $sc  = $rl ? $screensFor($dep, $rl[0]) : array();
    list($cand, $score) = $pick($x['station_ar'], $sc, $rl ? $rl[1] : '');
    if ($cand && count($cand) === 1) { $bound++; } else { $unbound++; }
    $rows[] = $x + array('role' => $rl, 'cand' => $cand, 'score' => $score,
                          'pool' => count($sc), 'sc' => $sc);
}

echo "\n═══ دليلُ مشيِ المحطّات — البندُ ٦٣ ═══\n";
printf("  محطّاتٌ: %d · بشاشةٍ واحدةٍ مرشَّحة: %d · تحتاج اختيارَ إنسان: %d\n\n",
    count($rows), $bound, $unbound);
$j = '';
foreach ($rows as $x) {
    if ($x['journey_key'] !== $j) { $j = $x['journey_key']; echo "── رحلةُ $j ──\n"; }
    printf("  %s %-11s #%d %s\n", $x['is_negative'] ? '⊘' : '·', $x['station_id'], $x['station_no'],
        mb_substr($x['station_ar'], 0, 58));
    printf("      الدور: %s · النطاق %s · شاشاتٌ يراها %d\n",
        $x['role'] ? ($x['role'][0] . ' «' . $x['role'][1] . '»') : '**لم يُحَلّ**', $x['domain_code'], $x['pool']);
    if ($x['cand']) {
        foreach ($x['cand'] as $rt => $lab) { printf("      ⇒ %-42s «%s»\n", $rt, $lab); }
    } else {
        echo "      ⇒ **لا شاشةَ مرشَّحة** — يختارها الماشي ويكتبها في الدليل\n";
    }
}

if ($MD) {
    $snap = $conn->query("SELECT snapshot_id, commit_hash FROM repair01_freeze_snapshot
                           ORDER BY frozen_at DESC LIMIT 1")->fetch_assoc();
    $o  = "# دليلُ مشيِ محطّاتِ القبولِ البشريّ — البندُ ٦٣\n\n";
    $o .= "> ⛔ **مولَّدٌ من المخزن**: `php tools/repair01_w16_uat_runbook.php --md`\n";
    $o .= "> **ولا يكتب هذا الدليلُ `PASSED`** — القيدُ `chk_w16_uat_real` يردُّ في\n";
    $o .= "> القاعدةِ إعلانَ نجاحٍ بلا فاعلٍ حقيقيٍّ وزمنٍ ودليل. **فهو يُمهّد ولا يمرّ.**\n\n";
    $o .= "| الحقل | القيمة |\n|---|---|\n";
    $o .= "| `Commit Hash` | `" . substr((string) $snap['commit_hash'], 0, 12) . "` |\n";
    $o .= "| `Measured At` | " . date('Y-m-d H:i:s') . " |\n";
    $o .= sprintf("| المحطّات | %d · بمرشَّحٍ واحد %d · تحتاج اختيارًا %d |\n\n", count($rows), $bound, $unbound);
    $o .= "## قبل البدء\n\n";
    $o .= "1. **حسابُ كلِّ دورٍ** من `docs/UAT_PLAN_ar.md` ⛔ ولا تُنسَخ كلمةُ مرورٍ هنا.\n";
    $o .= "2. **المسارُ السالبُ (⊘) يجب أن يُردّ** — ونجاحُه رسوبٌ لا نجاح.\n";
    $o .= "3. بعد كلِّ محطّة: سجّل **الفاعلَ والزمنَ والدليل** (لقطة أو رقم سجلّ).\n\n";
    $j = '';
    foreach ($rows as $x) {
        if ($x['journey_key'] !== $j) {
            $j = $x['journey_key'];
            $o .= "\n## رحلةُ `$j`\n\n| # | المحطّة | ما يفعله الإنسان | الدور | الشاشةُ المرشَّحة | سالب |\n";
            $o .= "|---:|---|---|---|---|---|\n";
        }
        $src = $x['cand'] ? $x['cand'] : $x['sc'];
        $sc  = $src ? implode('<br>', array_map(
            function ($rt, $lab) { return "`$rt` «$lab»"; }, array_keys($src), $src))
            : '**لا شاشةَ يراها هذا الدورُ في نطاقِه**';
        if (!$x['cand'] && $src) { $sc = '**يختار الماشي من:**<br>' . $sc; }
        $o .= sprintf("| %d | `%s` | %s | %s | %s | %s |\n", $x['station_no'], $x['station_id'],
            $x['station_ar'], $x['role'] ? $x['role'][1] : '**لم يُحَلّ**', $sc, $x['is_negative'] ? '⊘ يجب أن يُردّ' : '—');
    }
    $o .= "\n## تسجيلُ المرور\n\n";
    $o .= "⛔ **لا تُكتب `PASSED` بأداة.** يُسجَّل المرورُ بفاعلٍ حقيقيٍّ وزمنٍ ودليلٍ في\n";
    $o .= "`repair01_w16_uat` — والقاعدةُ تردُّ ما نقص منها.\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/W16_UAT_RUNBOOK.md', $o);
    echo "\n✔ كُتب docs/REPAIR01_20260823/W16_UAT_RUNBOOK.md\n";
}
