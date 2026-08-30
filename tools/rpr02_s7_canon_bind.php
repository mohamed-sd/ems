<?php
/**
 * tools/rpr02_s7_canon_bind.php — `RPR-02` §٦ · **س٧** الربطُ بسجلِّ الملاحةِ المعياريّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — §٦ الخطوةُ السابعة: *«اربطِ الكلَّ بسجلِّ الملاحةِ
 *   المعياريِّ — **ولا ترتيبَ يدويٌّ موازٍ**»*. والمقيسُ **٧٤٤** موضعًا على
 *   **١٦٤** مسارًا فريدًا.
 *
 * ◆ **والرقمُ المجموعُ لا يُصلَح** — يُفكَّك بسببِه أوّلًا ([[evidence-one-join-away]]):
 *   صفٌّ معتمَدٌ **بلا `screen_id`** · أو **لا صفَّ معتمَدَ ألبتّة** · أو لا سطحَ
 *   بالمسار · أو معرِّفان يختلفان. **ولكلٍّ يدٌ غيرُ يدِ الآخر.**
 *
 * ◆ **والجسرُ بالمسارِ نفسِه لا بالاسم**: `nav_canonical.route` ⟷
 *   `repair01_screen_registry.route` — و`uq_route` في الطرفين يجعلها واحدًا
 *   لواحد. ⛔ **ولا اشتقاقَ باسمِ ملفٍّ**: اسمان متطابقان قد يكونان شاشتَين
 *   ([[repair01-ops-sidebar-guide11]]).
 *
 * ⛔ **وما لا صفَّ معتمَدَ له لا يُخترع له صفّ** — إنشاءُ صفٍّ في السجلِّ
 *   المعتمَدِ **فعلُ اعتمادِ تسميةٍ وموضع**، وهو قرارُ مالك. والحالةُ الوحيدةُ
 *   القائمةُ **مرفوعةٌ سلفًا** في `RPR-02` #١٦ ⇒ تُوقَف بعددِها وتُسمّى،
 *   ⛔ ولا يُعاد رفعُها (‏أمرُ الإنهاءِ §٤).
 *
 * التشغيل:
 *   php tools/rpr02_s7_canon_bind.php [--apply] [--md] [--selftest]
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

$APPLY = in_array('--apply', $argv, true);
$MD    = in_array('--md', $argv, true);
$SELF  = in_array('--selftest', $argv, true);

$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
$sid = $snap ? $snap['snapshot_id'] : '';
if ($APPLY && $sid === '') { exit("⛔ **لا نافذةَ قياسٍ مفتوحة** — ولا يُطبَّق ربطٌ بلا لقطة\n"); }

/* ═══ ① الطرفان ═════════════════════════════════════════════════════════ */
$canon = array();
$r = $conn->query("SELECT route, canonical_ar, screen_id, status FROM nav_canonical");
while ($r && ($x = $r->fetch_assoc())) { $canon[strtolower(trim((string) $x['route'], '/'))] = $x; }

$reg = array();
$r = $conn->query("SELECT screen_id, route, canonical_label_ar, on_disk FROM repair01_screen_registry WHERE route <> ''");
while ($r && ($x = $r->fetch_assoc())) { $reg[strtolower(trim((string) $x['route'], '/'))] = $x; }

/* ═══ ② المواضعُ الحيّةُ — والحكمُ بسببِه ══════════════════════════════════ */
$live = array();
$r = $conn->query("SELECT id, role_id, route, label_ar FROM nav_items WHERE active = 1");
while ($r && ($x = $r->fetch_assoc())) { $live[] = $x; }

$why = array('EMPTY_ID' => 0, 'NO_CANON_ROW' => 0, 'NO_REG_ROW' => 0, 'ID_MISMATCH' => 0, 'OK' => 0);
$whyRt = array('EMPTY_ID' => array(), 'NO_CANON_ROW' => array(), 'NO_REG_ROW' => array(), 'ID_MISMATCH' => array());
$hits = array();   /* canon route => مواضعُ حيّةٌ يفكّها ربطُه */
foreach ($live as $it) {
    $b = strtolower(trim((string) $it['route'], '/'));
    if (!isset($reg[$b])) {
        $p = preg_replace('~[?#].*$~', '', $b);
        if ($p !== $b && isset($reg[$p])) { $b = $p; }
    }
    $scr = isset($reg[$b]) ? $reg[$b] : null;
    $cn  = isset($canon[$b]) ? $canon[$b] : null;
    if (!$cn)                                   { $why['NO_CANON_ROW']++; $whyRt['NO_CANON_ROW'][$b] = 1; continue; }
    if (trim((string) $cn['screen_id']) === '') { $why['EMPTY_ID']++;     $whyRt['EMPTY_ID'][$b] = 1;
                                                  $hits[$b] = (isset($hits[$b]) ? $hits[$b] : 0) + 1; continue; }
    if (!$scr)                                  { $why['NO_REG_ROW']++;   $whyRt['NO_REG_ROW'][$b] = 1; continue; }
    if ($cn['screen_id'] !== $scr['screen_id']) { $why['ID_MISMATCH']++;  $whyRt['ID_MISMATCH'][$b] = 1; continue; }
    $why['OK']++;
}

/* ═══ ③ الخطّة — ما يُملأ من السجلِّ المبنيِّ بجسرِ المسار ════════════════ */
$plan = array(); $orphan = array();
foreach ($canon as $rt => $cn) {
    if (trim((string) $cn['screen_id']) !== '') { continue; }
    if (!isset($reg[$rt])) { $orphan[$rt] = $cn['status']; continue; }
    $plan[$rt] = array('canon' => $cn, 'reg' => $reg[$rt],
                       'live' => isset($hits[$rt]) ? $hits[$rt] : 0);
}

/* ═══ ④ الاختبارُ السالب ════════════════════════════════════════════════ */
if ($SELF) {
    $fail = 0;
    if (count($live) < 100)  { echo '  X المواضعُ الحيّةُ ' . count($live) . " — القراءةُ لم تتمّ\n"; $fail++; }
    if (count($canon) < 100) { echo '  X السجلُّ المعتمَدُ ' . count($canon) . " صفًّا — مصفاةٌ عمياء\n"; $fail++; }
    if (count($reg) < 100)   { echo '  X سجلُّ الأسطحِ ' . count($reg) . " صفًّا — مصفاةٌ عمياء\n"; $fail++; }
    /* **الكاسر**: مسارٌ لا وجودَ له يجب ألّا يجد جسرًا ([[measure-token-must-exist]]) */
    if (isset($reg['zzq/absent_route_probe.php'])) { echo "  X مسارٌ وهميٌّ وُجد في سجلِّ الأسطح\n"; $fail++; }
    if (isset($canon['zzq/absent_route_probe.php'])) { echo "  X مسارٌ وهميٌّ وُجد في السجلِّ المعتمَد\n"; $fail++; }
    /* **ومجموعُ الأسبابِ يساوي المقامَ** — وإلّا فحكمٌ يسقط من الشقوق */
    $sum = 0; foreach ($why as $v) { $sum += $v; }
    if ($sum !== count($live)) { echo "  X مجموعُ الأسبابِ $sum والمقامُ " . count($live) . "\n"; $fail++; }
    /* **وكلُّ خطّةٍ لها معرِّفٌ غيرُ فارغ** — ⛔ ولا يُكتب فراغٌ فوق فراغ */
    foreach ($plan as $rt => $p) {
        if (trim((string) $p['reg']['screen_id']) === '') { echo "  X خطّةٌ بمعرِّفٍ فارغٍ: $rt\n"; $fail++; break; }
    }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n"
               : "\n🟢 الفحصُ الذاتيُّ تامٌّ — ثلاثةُ سجلاتٍ مقروءة، ومجموعُ الأسبابِ هو المقامُ نفسُه\n";
    exit($fail ? 1 : 0);
}

/* ═══ ⑤ العرض ═══════════════════════════════════════════════════════════ */
$need = $why['EMPTY_ID'] + $why['NO_CANON_ROW'] + $why['NO_REG_ROW'] + $why['ID_MISMATCH'];
echo "\n═══ `RPR-02` §٦ · **س٧** — الربطُ بسجلِّ الملاحةِ المعياريّ ═══\n";
printf("  اللقطة %s · مواضعُ حيّةٌ %d · مربوطٌ %d · **يحتاج ربطًا %d**\n",
       ($sid !== '' ? $sid : 'DRY'), count($live), $why['OK'], $need);
echo "\n  ⛔ **والرقمُ يُفكَّك بسببِه لا يُجمَع**:\n";
printf("    · صفُّه المعتمَدُ بلا `screen_id`      %5d موضعًا · %3d مسارًا  ⇒ **يُملأ من سجلِّ الأسطح**\n",
       $why['EMPTY_ID'], count($whyRt['EMPTY_ID']));
printf("    · لا صفَّ معتمَدَ له ألبتّة            %5d موضعًا · %3d مسارًا  ⇒ ⛔ **قرارُ مالكٍ — مرفوعٌ في #١٦**\n",
       $why['NO_CANON_ROW'], count($whyRt['NO_CANON_ROW']));
printf("    · لا سطحَ في السجلِّ بهذا المسار       %5d موضعًا · %3d مسارًا\n",
       $why['NO_REG_ROW'], count($whyRt['NO_REG_ROW']));
printf("    · معرِّفان مختلفان                    %5d موضعًا · %3d مسارًا\n",
       $why['ID_MISMATCH'], count($whyRt['ID_MISMATCH']));
foreach ($whyRt['NO_CANON_ROW'] as $rt => $_) {
    echo '      ⛔ موقوفٌ: ' . $rt . ' — سطحٌ مسجَّلٌ ('
       . (isset($reg[$rt]) ? $reg[$rt]['screen_id'] : 'بلا سطح')
       . ") **بلا صفِّ تسميةٍ ألبتّة**؛ إنشاءُ صفٍّ فعلُ اعتماد ⇒ `RPR-02` #١٦\n";
}
printf("\n  خطّةُ الملء: **%d مسارًا معتمَدًا** يُملأ معرِّفُه — منها %d يفكّ مواضعَ حيّةً\n",
       count($plan), count(array_filter($plan, function ($p) { return $p['live'] > 0; })));
if ($orphan) {
    printf("  ◆ خبرٌ (خارجَ الحكمِ الحيّ): %d صفًّا معتمَدًا بلا `screen_id` **ولا مسارَ له في سجلِّ الأسطح** — لا يُصيَّر فلا يُحاسَب:\n", count($orphan));
    foreach ($orphan as $rt => $st) { echo "      · $rt (حالة $st)\n"; }
}
if (!$APPLY) { echo "\n  ⛔ **معاينةٌ — لم يُكتب شيء.** والتطبيقُ بـ`--apply`.\n"; }

/* ═══ ⑥ التطبيق ═════════════════════════════════════════════════════════ */
if ($APPLY) {
    $conn->query('START TRANSACTION');
    $n = 0;
    foreach ($plan as $rt => $p) {
        $wit = 'س٧ · جسرُ المسارِ نفسِه: `nav_canonical.route` = `repair01_screen_registry.route` = '
             . $p['reg']['route'] . ' ⇒ ' . $p['reg']['screen_id']
             . ' · معتمَدٌ «' . $p['canon']['canonical_ar'] . '» ومبنيٌّ «' . $p['reg']['canonical_label_ar']
             . '» · مواضعُ حيّةٌ يفكّها ' . $p['live'];
        $sql = "INSERT INTO `repair01_canon_screen_bind`
                (route, before_screen_id, after_screen_id, registry_route, live_positions, witness, snapshot_id, applied_at)
                VALUES ('" . $e($p['canon']['route']) . "', '" . $e($p['canon']['screen_id']) . "', '"
              . $e($p['reg']['screen_id']) . "', '" . $e($p['reg']['route']) . "', " . (int) $p['live'] . ", '"
              . $e($wit) . "', '" . $e($sid) . "', NOW())
                ON DUPLICATE KEY UPDATE after_screen_id = VALUES(after_screen_id),
                                        witness = VALUES(witness), applied_at = NOW()";
        if (!$conn->query($sql)) { $conn->query('ROLLBACK'); exit("✘ تعذّر تسجيلُ الربط: {$conn->error}\n"); }
        if (!$conn->query("UPDATE `nav_canonical` SET `screen_id` = '" . $e($p['reg']['screen_id']) . "'
                            WHERE `route` = '" . $e($p['canon']['route']) . "'")) {
            $conn->query('ROLLBACK'); exit("✘ تعذّر الربط: {$conn->error}\n");
        }
        $n++;
    }
    $conn->query('COMMIT');
    printf("\n  ✔ **رُبط %d مسارًا معتمَدًا بمعرِّفِ سطحِه** — والجسرُ المسارُ نفسُه لا اسمٌ\n", $n);
    printf("  ⛔ **وأُوقف %d** بقرارِ تسميةٍ مرفوعٍ سلفًا في `RPR-02` #١٦\n", count($whyRt['NO_CANON_ROW']));
}

/* ═══ ⑦ المعاينةُ المكتوبة ══════════════════════════════════════════════ */
if ($MD) {
    $o  = "# `RPR-02` §٦ · **س٧** — الربطُ بسجلِّ الملاحةِ المعياريّ\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `"
        . ($sid !== '' ? $sid : 'DRY') . "`\n\n";
    $o .= 'المقامُ **مواضعُ حيّةٌ ' . count($live) . '** · مربوطٌ **' . $why['OK'] . '** · يحتاج ربطًا **' . $need . "**.\n\n";
    $o .= "## ⛔ الرقمُ مفكَّكًا بسببِه — ولكلٍّ يدٌ غيرُ يدِ الآخر\n\n";
    $o .= "| السبب | مواضع | مسارٌ فريد | اليد |\n|---|---:|---:|---|\n";
    $o .= '| صفُّه المعتمَدُ بلا `screen_id` | **' . $why['EMPTY_ID'] . '** | **' . count($whyRt['EMPTY_ID'])
        . "** | يُملأ من `repair01_screen_registry` بجسرِ المسار |\n";
    $o .= '| لا صفَّ معتمَدَ له ألبتّة | ' . $why['NO_CANON_ROW'] . ' | ' . count($whyRt['NO_CANON_ROW'])
        . " | ⛔ **قرارُ مالكٍ — مرفوعٌ سلفًا في `RPR-02` #١٦** |\n";
    $o .= '| لا سطحَ في السجلِّ بهذا المسار | ' . $why['NO_REG_ROW'] . ' | ' . count($whyRt['NO_REG_ROW']) . " | — |\n";
    $o .= '| معرِّفان مختلفان | ' . $why['ID_MISMATCH'] . ' | ' . count($whyRt['ID_MISMATCH']) . " | — |\n\n";
    $o .= '**خطّةُ الملء: ' . count($plan) . " مسارًا معتمَدًا.**\n\n";
    foreach ($whyRt['NO_CANON_ROW'] as $rt => $_) {
        $o .= '⛔ **موقوفٌ بعددِه**: `' . $rt . '` — سطحٌ مسجَّلٌ ('
            . (isset($reg[$rt]) ? '`' . $reg[$rt]['screen_id'] . '`' : 'بلا سطح')
            . ") **بلا صفِّ تسميةٍ ألبتّة**؛ وإنشاءُ صفٍّ في السجلِّ المعتمَدِ **فعلُ اعتمادِ تسميةٍ وموضع** ⇒ قرارُ مالكٍ مرفوعٌ سلفًا.\n";
    }
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/SIDEBAR_S7_BIND.md', $o);
    echo "\n✔ كُتب docs/REPAIR01_20260823/SIDEBAR_S7_BIND.md\n";
}
