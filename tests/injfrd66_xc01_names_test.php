<?php
/**
 * tests/injfrd66_xc01_names_test.php — شاهدُ XC-01: اسمُ البندِ من الجدولِ المستهدف
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **إيجابيٌّ** ①: كلُّ بندٍ **مملوكٍ لإدارةِ الوثيقة** يُصيَّر باسمِه المستهدفِ
 *   في جلسةِ مستخدمٍ حقيقيّ — لا بدورٍ مجرَّد.
 * ◆ **إيجابيٌّ** ②: الطبقاتُ الثلاثُ متفقةٌ على الاسمِ الواحد
 *   (`nav_canonical` · `nav_canonical_current` · `nav_items`) — فجولةٌ سابقةٌ
 *   حدَّثت الأدنى وحدَه في تسعةِ مساراتٍ فبدا العملُ منجزًا وهو غيرُ ظاهر.
 * ◆ **سالبٌ** ③: كلُّ اسمٍ أُعيد **مسجَّلٌ في `old_names`** لصفٍّ حالتُه
 *   APPROVED — وبدونِه تُرسِّب بوابةُ `uxui_preserve_check` كلَّ التزامٍ يمسُّ
 *   `.php`/`.css` **في كلِّ الجلسات**. وهذا الشاهدُ يمنع تلك الكارثةَ قبلَ
 *   وقوعِها لا بعدَها.
 * ◆ **سالبٌ** ④: بوابةُ الحفظِ نفسُها تعبر وتُصادق على الأسماءِ المُعادة.
 * ◆ **سالبٌ** ⑤: صفٌّ غيرُ معتمَدٍ (PENDING) **لم** يُعَد اسمُه — فالحجزُ
 *   محفوظٌ ولم يُنتحَل قرارُ مالك.
 *
 * التشغيل: php tests/injfrd66_xc01_names_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$_SERVER['SCRIPT_NAME']    = '/ems/main/dashboard.php';
$_SERVER['REQUEST_URI']    = $_SERVER['SCRIPT_NAME'];
$_SERVER['REQUEST_METHOD'] = 'GET';
require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/unified_nav.php';
require_once $ROOT . '/includes/uxui_nav_probe.php';
while (ob_get_level() > 0) { ob_end_clean(); }
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

$pass = 0; $fail = 0;

/* ── ① البوابةُ نفسُها: محورُ الاسمِ صفرُ خرق ───────────────────────────── */
echo "① إيجابيٌّ — محورُ الاسمِ في السايدبارِ المُصيَّرِ بجلسةٍ حقيقية:\n";
$out = array(); $rc = 0;
exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/injfrd66_nav_gate.php') . ' --gate 2>&1', $out, $rc);
$txt = implode("\n", $out);
preg_match('/محورُ الاسم: (\d+) خرقًا/u', $txt, $m);
$breaches = isset($m[1]) ? (int) $m[1] : -1;
if ($rc === 0 && $breaches === 0) { $pass++; echo "   ✔ صفرُ خرقٍ في محورِ الاسم\n"; }
else { $fail++; printf("   ✘ %d خرقًا (rc=%d)\n", $breaches, $rc); }

/* ── ② الطبقاتُ الثلاثُ متفقة ──────────────────────────────────────────── */
echo "\n② إيجابيٌّ — اتفاقُ الطبقاتِ الثلاثِ على الاسمِ الواحد:\n";
$sql = "SELECT t.item_ar, t.route, k.canonical_ar,
               (SELECT COUNT(*) FROM nav_canonical_current c
                 WHERE LOWER(c.route)=LOWER(t.route) AND c.cur_label <> t.item_ar) AS cur_bad,
               (SELECT COUNT(*) FROM nav_items n
                 WHERE LOWER(n.route)=LOWER(t.route) AND n.label_ar <> t.item_ar) AS itm_bad
          FROM gov_target_nav t
          JOIN nav_canonical k ON LOWER(k.route)=LOWER(t.route)
         WHERE k.canonical_ar = t.item_ar";
$bad = array();
$res = @mysqli_query($conn, $sql);
while ($res && ($x = mysqli_fetch_assoc($res))) {
    if ((int) $x['cur_bad'] > 0 || (int) $x['itm_bad'] > 0) {
        $bad[] = "{$x['route']} — current:{$x['cur_bad']} · items:{$x['itm_bad']}";
    }
}
if ($bad) { $fail++; printf("   ✘ %d مسارًا طبقاتُه متفرّقة:\n", count($bad)); foreach ($bad as $b) { echo "      {$b}\n"; } }
else { $pass++; echo "   ✔ لا مسارَ اسمُه متفرّقٌ بين الطبقاتِ الثلاث\n"; }

/* ── ③ سالبٌ: كلُّ اسمٍ أُعيد مسجَّلٌ في old_names لصفٍّ APPROVED ────────── */
echo "\n③ سالبٌ — كلُّ اسمٍ أُعيد مسجَّلٌ في old_names (وبدونِه تُرسِّب كلَّ التزام):\n";
$miss = array();
/* الأعمدةُ تُنسَب صراحةً — `route` و`canonical_ar` في الجدولَين معًا،
   وغيرُ المنسوبِ يُفشل الاستعلامَ صامتًا فيُقرأ «لم تُنفَّذ الهجرة» وهي منفَّذة */
$res = @mysqli_query($conn, "SELECT b.route AS route, b.canonical_ar AS prev_name,
                                    k.old_names AS old_names, k.status AS status
                               FROM injfrd66_xc01_backup b
                               JOIN nav_canonical k ON LOWER(k.route)=LOWER(b.route)
                              WHERE b.canonical_ar IS NOT NULL AND b.canonical_ar <> k.canonical_ar");
if (!$res) {
    /* لا نسخةَ احتياطيةٍ ⇒ لم تُنفَّذ الهجرةُ بعد */
    $fail++; echo "   ✘ تعذّرت قراءةُ injfrd66_xc01_backup — لم تُنفَّذ الهجرة؟\n";
} else {
    $n = 0;
    while ($x = mysqli_fetch_assoc($res)) {
        $n++;
        $parts = array_filter(array_map('trim', preg_split('~[/·]+~u', (string) $x['old_names'])));
        $prev = (string) $x['prev_name'];
        if ($x['status'] !== 'APPROVED') { $miss[] = "{$x['route']} — حالتُه {$x['status']} لا APPROVED"; continue; }
        if ($prev !== '' && !in_array($prev, $parts, true)) { $miss[] = "{$x['route']} — «{$prev}» غائبٌ عن old_names"; }
    }
    if ($miss) { $fail++; printf("   ✘ %d من %d:\n", count($miss), $n); foreach ($miss as $b) { echo "      {$b}\n"; } }
    else { $pass++; printf("   ✔ %d اسمًا قديمًا كلُّها مسجَّلةٌ في صفٍّ APPROVED\n", $n); }
}

/* ── ④ سالبٌ بنيويّ: بوابةُ الحفظِ تعبر ─────────────────────────────────── */
echo "\n④ سالبٌ بنيويّ — بوابةُ الحفظِ بعدَ إعادةِ التسمية:\n";
$out2 = array(); $rc2 = 0;
exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/uxui_preserve_check.php') . ' --gate 2>&1', $out2, $rc2);
$t2 = implode("\n", $out2);
$ren = preg_match('/الإجمالي:.*?أُعيدت تسميتُه بالسجل=(\d+)/us', $t2, $mm) ? (int) $mm[1] : -1;
if ($rc2 === 0 && mb_strpos($t2, 'صفرُ فقدٍ غيرِ مصرَّح') !== false) {
    $pass++; printf("   ✔ البوابةُ تعبر · مُصادَقٌ على %d اسمًا مُعادًا\n", $ren);
} else { $fail++; printf("   ✘ البوابةُ رسبت (rc=%d)\n", $rc2); }

/* ── ⑤ سالبٌ: الصفُّ المعلَّقُ لم يُعَد اسمُه ───────────────────────────── */
echo "\n⑤ سالبٌ — صفٌّ غيرُ معتمَدٍ لم يُنتحَل قرارُ مالكِه:\n";
$held = 0; $violated = array();
$res = @mysqli_query($conn, "SELECT t.item_ar, t.route, k.canonical_ar, k.status
                               FROM gov_target_nav t JOIN nav_canonical k ON LOWER(k.route)=LOWER(t.route)
                              WHERE k.status <> 'APPROVED'");
while ($res && ($x = mysqli_fetch_assoc($res))) {
    $held++;
    if ((string) $x['canonical_ar'] === (string) $x['item_ar']) {
        $violated[] = "{$x['route']} — أُعيد اسمُه وحالتُه {$x['status']}";
    } else {
        printf("   ⏸ %s — «%s» باقٍ · حالتُه %s\n", $x['route'], $x['canonical_ar'], $x['status']);
    }
}
if ($violated) { $fail++; foreach ($violated as $v) { echo "   ✘ {$v}\n"; } }
else { $pass++; printf("   ✔ %d صفًّا معلَّقًا محفوظًا بلا انتحال\n", $held); }

printf("\n%s  ناجح %d · راسب %d\n", $fail === 0 ? '✔ XC-01' : '✘ XC-01', $pass, $fail);
exit($fail === 0 ? 0 : 1);
