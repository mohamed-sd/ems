<?php
/**
 * tools/rpr02_s2_label_align.php — `RPR-02` §٦ · **س٢** الاسمُ المخزَّنُ على المعتمَد
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — §٦ الخطوةُ الثانية: *«صحِّحِ الأسماءَ المعروضةَ على
 *   التسميةِ المعتمدة»*. والمقيسُ **١٥٤** موضعًا على **٢٥** مسارًا.
 *
 * ◆ **والرقمُ يُفكَّك بسببِه لا يُجمَع** — ولكلٍّ يدٌ غيرُ يدِ الآخر:
 *   اسمٌ يخالف معتمَدًا `APPROVED` ⇒ **يُحاذى** · تسميةٌ `PENDING_OWNER` ⇒
 *   **فعلُ اعتمادٍ** · بلا صفٍّ ألبتّة ⇒ **فعلُ تسمية**. والأخيرانِ **مرفوعانِ
 *   سلفًا** في `RPR-02` **#١٦**، ⛔ ولا يُعاد رفعُهما.
 *
 * ⛔ **ولا تُرقّى تسميةٌ معلَّقةٌ إلى معتمَدةٍ من عندنا** — `PENDING_OWNER`
 *   حالةٌ تنتظر **فعلَ اعتمادٍ من مالك**، وقلبُها في المخزنِ **تلفيقُ قرار**.
 *
 * ⚠ **وحدُّ العمودِ يُحترم**: `label_ar` **varchar(64)** و`canonical_ar`
 *   **varchar(190)** — فما جاوز الأربعةَ والستِّين **يُوقَف ويُسمّى**،
 *   ⛔ **ولا يُبتَر اسمٌ معتمَدٌ ليدخل عمودًا** ([[data-mismatch-campaign]]).
 *
 * ⚠ **والأثرُ يُقاس لا يُظَنّ**: المُصيِّرُ يأخذ `canonical_ar` سلفًا لكلِّ صفٍّ
 *   `APPROVED` — **فالمتوقَّعُ صفرُ فرقٍ في العضويّةِ المُصيَّرة**، ويُقاس
 *   بـ`rpr02_nav_render_census.php` قبلَ التطبيقِ وبعدَه.
 *
 * التشغيل:
 *   php tools/rpr02_s2_label_align.php [--apply] [--md] [--selftest]
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

/** التطبيعُ — **نسخةُ المقياسِ حرفًا** ولا نسختان تتفرّقان */
function s2_norm($s)
{
    $s = preg_replace('~[\x{064B}-\x{0652}\x{0670}\x{0640}]~u', '', (string) $s);
    $s = preg_replace('~[\x{0622}\x{0623}\x{0625}]~u', "\u{0627}", $s);
    $s = preg_replace('~\x{0649}~u', "\u{064A}", $s);
    $s = preg_replace('~\x{0629}~u', "\u{0647}", $s);
    $s = preg_replace('~[«»"\'\[\]\-–—/·،,\.]~u', ' ', $s);
    return trim(preg_replace('~\s+~u', ' ', $s));
}
define('S2_LABEL_MAX', 64);   /* حدُّ العمودِ في المخطَّطِ — لا رقمٌ من عندنا */

$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
$sid = $snap ? $snap['snapshot_id'] : '';
if ($APPLY && $sid === '') { exit("⛔ **لا نافذةَ قياسٍ مفتوحة** — ولا تُطبَّق محاذاةٌ بلا لقطة\n"); }

$canon = array();
$r = $conn->query("SELECT route, canonical_ar, status FROM nav_canonical");
while ($r && ($x = $r->fetch_assoc())) { $canon[strtolower(trim((string) $x['route'], '/'))] = $x; }

$plan = array(); $why = array('OK' => 0, 'LABEL_DIFF' => 0, 'PENDING_NAME' => 0, 'NO_CANON_ROW' => 0,
                              'TOO_LONG' => 0, 'VARIANT' => 0);
$rt = array('LABEL_DIFF' => array(), 'PENDING_NAME' => array(), 'NO_CANON_ROW' => array(),
            'TOO_LONG' => array(), 'VARIANT' => array());
$N = 0;
$r = $conn->query("SELECT id, role_id, route, label_ar FROM nav_items WHERE active = 1 ORDER BY role_id, id");
while ($r && ($it = $r->fetch_assoc())) {
    $N++;
    $raw = (string) $it['route'];
    /* ⛔ **وصيغةُ العرضِ اسمُها لها — بقرارِ المُصيِّرِ نفسِه** (‏بند ٧): المدخلُ
       الثاني (`?view=`/`#`) **مدخلٌ مقصودٌ وله تسميتُه**، و`unified_nav.php`
       يعطيه `label_ar` صراحةً ولا يفرض عليه المعتمَدَ.
       ⚠ **وقِيس الضررُ لا ظُنّ**: طُبِّقت المحاذاةُ على المتغايرِ مرّةً فسقط
         **أحدَ عشرَ رابطًا حيًّا** من التصييرِ (١٬٧٧٦ ⇒ ١٬٧٦٥ موضعًا في تسعةِ
         أدوار) — لأنَّ الأصلَ والمتغايرَ صارا باسمٍ واحدٍ **فابتلع حارسُ
         التكرارِ ثانيَهما**، وهو العطبُ نفسُه الذي يحذّر منه المُصيِّرُ في
         تعليقِه (‏قِيس سابقًا في الدور ٢٤). ⇒ **رُدَّت الدفعةُ ثمَّ استُثني**.
       ◆ **ومقياسُ اللوحةِ #١٦ يستثنيها سلفًا** بالنصِّ نفسِه — فالاستثناءُ
         **مصالحةُ مقياسَين لا إرخاءُ أحدِهما** ([[counter-parity-two-readers]]). */
    if (strpbrk($raw, '#?') !== false) {
        $why['VARIANT']++;
        $rt['VARIANT'][strtolower(trim(preg_replace('~[?#].*$~', '', $raw), '/'))] = 1;
        continue;
    }
    $b = strtolower(trim($raw, '/'));
    if (!isset($canon[$b])) {
        $p = preg_replace('~[?#].*$~', '', $b);
        if (isset($canon[$p])) { $b = $p; }
    }
    $cn = isset($canon[$b]) ? $canon[$b] : null;
    if (!$cn) { $why['NO_CANON_ROW']++; $rt['NO_CANON_ROW'][$b] = (string) $it['label_ar']; continue; }
    if ($cn['status'] !== 'APPROVED') { $why['PENDING_NAME']++; $rt['PENDING_NAME'][$b] = $cn['status']; continue; }
    if (s2_norm($cn['canonical_ar']) === s2_norm($it['label_ar'])) { $why['OK']++; continue; }
    if (mb_strlen($cn['canonical_ar']) > S2_LABEL_MAX) {
        $why['TOO_LONG']++; $rt['TOO_LONG'][$b] = mb_strlen($cn['canonical_ar']); continue;
    }
    $why['LABEL_DIFF']++; $rt['LABEL_DIFF'][$b] = 1;
    $plan[] = array('it' => $it, 'canon_route' => $b, 'after' => (string) $cn['canonical_ar']);
}

/* ═══ الاختبارُ السالب ═══════════════════════════════════════════════════ */
if ($SELF) {
    $fail = 0;
    if ($N < 100) { echo "  X المقامُ الحيُّ $N — القراءةُ لم تتمّ\n"; $fail++; }
    if (count($canon) < 100) { echo '  X السجلُّ المعتمَدُ ' . count($canon) . " صفًّا — مصفاةٌ عمياء\n"; $fail++; }
    $sum = 0; foreach ($why as $v) { $sum += $v; }
    if ($sum !== $N) { echo "  X مجموعُ الأسبابِ $sum والمقامُ $N\n"; $fail++; }
    /* **الكاسرُ ①**: ⛔ لا خطّةَ على صفٍّ غيرِ معتمَد — وإلّا فترقيةُ قرارٍ */
    foreach ($plan as $p) {
        if ($canon[$p['canon_route']]['status'] !== 'APPROVED') { echo "  X خطّةٌ على صفٍّ غيرِ معتمَد\n"; $fail++; break; }
    }
    /* **الكاسرُ ②**: ⛔ ولا اسمٌ يجاوز حدَّ العمود */
    foreach ($plan as $p) {
        if (mb_strlen($p['after']) > S2_LABEL_MAX) { echo "  X خطّةٌ باسمٍ يجاوز حدَّ العمود\n"; $fail++; break; }
    }
    /* **الكاسرُ ③**: التطبيعُ يوحّد الهمزةَ ولا يبتلع معنيَين */
    if (s2_norm('الرئيسيّة') !== s2_norm('الرئيسيه')) { echo "  X التطبيعُ لا يوحّد التاءَ المربوطة\n"; $fail++; }
    if (s2_norm('البلاغات') === s2_norm('صندوق بلاغات الإدارة')) { echo "  X التطبيعُ يبتلع فرقًا حقيقيًّا\n"; $fail++; }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n"
               : "\n🟢 الفحصُ الذاتيُّ تامٌّ — الخطّةُ على المعتمَدِ وحدَه، وداخلَ حدِّ العمود\n";
    exit($fail ? 1 : 0);
}

/* ═══ العرض ═════════════════════════════════════════════════════════════ */
$need = $N - $why['OK'];
echo "\n═══ `RPR-02` §٦ · **س٢** — الاسمُ المخزَّنُ على المعتمَد ═══\n";
printf("  اللقطة %s · بنودٌ حيّةٌ %d · مستقيمٌ %d · **يحتاج تصحيحًا %d**\n",
       ($sid !== '' ? $sid : 'DRY'), $N, $why['OK'], $need);
echo "\n  ⛔ **والرقمُ يُفكَّك بسببِه** — ولكلٍّ يدٌ غيرُ يدِ الآخر:\n";
printf("    · اسمٌ يخالف معتمَدًا `APPROVED`   %4d موضعًا · %3d مسارًا  ⇒ **يُحاذى**\n",
       $why['LABEL_DIFF'], count($rt['LABEL_DIFF']));
printf("    · تسميتُه `PENDING_OWNER`         %4d موضعًا · %3d مسارًا  ⇒ ⛔ **فعلُ اعتمادٍ — #١٦**\n",
       $why['PENDING_NAME'], count($rt['PENDING_NAME']));
printf("    · بلا صفِّ تسميةٍ ألبتّة            %4d موضعًا · %3d مسارًا  ⇒ ⛔ **فعلُ تسميةٍ — #١٦**\n",
       $why['NO_CANON_ROW'], count($rt['NO_CANON_ROW']));
printf("    · معتمَدٌ يجاوز حدَّ العمود (%d حرفًا) %4d موضعًا · %3d مسارًا  ⇒ ⛔ **لا يُبتَر**\n",
       S2_LABEL_MAX, $why['TOO_LONG'], count($rt['TOO_LONG']));
printf("    · صيغةُ عرضٍ (`?view=`/`#`)        %4d موضعًا · %3d أساسًا  ⇒ ⛔ **اسمُها لها — بند ٧ · وقِيس أنَّ فرضَ المعتمَدِ يُسقِط روابطَ**\n",
       $why['VARIANT'], count($rt['VARIANT']));
foreach ($rt['PENDING_NAME'] as $k => $v) { echo "      ⛔ موقوفٌ: $k — حالةُ تسميتِه `$v`\n"; }
foreach ($rt['NO_CANON_ROW'] as $k => $v) { echo "      ⛔ موقوفٌ: $k — «$v» بلا صفِّ تسمية\n"; }
foreach ($rt['TOO_LONG'] as $k => $v) { echo "      ⛔ موقوفٌ: $k — المعتمَدُ $v حرفًا\n"; }
printf("\n  ⇒ **خطّةٌ: %d موضعًا على %d مسارًا**\n", count($plan), count($rt['LABEL_DIFF']));
$i = 0;
foreach ($rt['LABEL_DIFF'] as $k => $_) { echo "     · $k\n"; if (++$i >= 8) { break; } }
if (!$APPLY) { echo "\n  ⛔ **معاينةٌ — لم يُكتب شيء.** والتطبيقُ بـ`--apply`.\n"; }

/* ═══ التطبيق ═══════════════════════════════════════════════════════════ */
if ($APPLY) {
    $conn->query('START TRANSACTION');
    $n = 0;
    foreach ($plan as $p) {
        $wit = 'س٢ · التسميةُ المعتمَدةُ `APPROVED` لِـ' . $p['canon_route'] . ': «' . $p['after']
             . '» والمخزنُ «' . $p['it']['label_ar'] . '» — والمُصيِّرُ يأخذ المعتمَدَ سلفًا فهذا يُطابق المخزنَ به';
        $sql = "INSERT INTO `repair01_nav_label_align`
                (nav_item_id, role_id, route, before_label, after_label, canon_status, witness, snapshot_id, applied_at)
                VALUES (" . (int) $p['it']['id'] . ", " . (int) $p['it']['role_id'] . ", '" . $e($p['it']['route']) . "', '"
              . $e($p['it']['label_ar']) . "', '" . $e($p['after']) . "', 'APPROVED', '" . $e($wit) . "', '" . $e($sid) . "', NOW())
                ON DUPLICATE KEY UPDATE after_label = VALUES(after_label), witness = VALUES(witness), applied_at = NOW()";
        if (!$conn->query($sql)) { $conn->query('ROLLBACK'); exit("✘ تعذّر تسجيلُ المحاذاة: {$conn->error}\n"); }
        if (!$conn->query("UPDATE `nav_items` SET `label_ar` = '" . $e($p['after']) . "' WHERE `id` = " . (int) $p['it']['id'])) {
            $conn->query('ROLLBACK'); exit("✘ تعذّرت المحاذاة: {$conn->error}\n");
        }
        $n++;
    }
    $conn->query('COMMIT');
    printf("\n  ✔ **حوذي %d موضعًا** على %d مسارًا\n", $n, count($rt['LABEL_DIFF']));
    printf("  ⛔ **وأُوقف %d** بقرارِ تسميةٍ أو اعتمادٍ مرفوعٍ سلفًا في `RPR-02` #١٦\n",
           $why['PENDING_NAME'] + $why['NO_CANON_ROW']);
}

/* ═══ المعاينةُ المكتوبة ════════════════════════════════════════════════ */
if ($MD) {
    $o  = "# `RPR-02` §٦ · **س٢** — الاسمُ المخزَّنُ على المعتمَد\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `"
        . ($sid !== '' ? $sid : 'DRY') . "`\n\n";
    $o .= "المقامُ **$N** · مستقيمٌ **" . $why['OK'] . "** · يحتاج **$need**.\n\n";
    $o .= "## ⛔ الرقمُ مفكَّكًا بسببِه\n\n| السبب | مواضع | مسارٌ فريد | اليد |\n|---|---:|---:|---|\n";
    $o .= '| اسمٌ يخالف معتمَدًا `APPROVED` | **' . $why['LABEL_DIFF'] . '** | **' . count($rt['LABEL_DIFF']) . "** | يُحاذى |\n";
    $o .= '| تسميتُه `PENDING_OWNER` | ' . $why['PENDING_NAME'] . ' | ' . count($rt['PENDING_NAME']) . " | ⛔ **فعلُ اعتمادٍ — #١٦** |\n";
    $o .= '| بلا صفِّ تسميةٍ ألبتّة | ' . $why['NO_CANON_ROW'] . ' | ' . count($rt['NO_CANON_ROW']) . " | ⛔ **فعلُ تسميةٍ — #١٦** |\n";
    $o .= '| معتمَدٌ يجاوز حدَّ العمود | ' . $why['TOO_LONG'] . ' | ' . count($rt['TOO_LONG']) . " | ⛔ **لا يُبتَر** |\n";
    $o .= '| صيغةُ عرضٍ (`?view=`/`#`) | ' . $why['VARIANT'] . ' | ' . count($rt['VARIANT'])
        . " | ⛔ **اسمُها لها — بند ٧** |\n\n";
    $o .= "⚠ **والاستثناءُ مقيسٌ لا مفترَض**: طُبِّقت المحاذاةُ على صيغِ العرضِ مرّةً فسقط **أحدَ عشرَ رابطًا حيًّا**\n";
    $o .= "من التصيير (‏١٬٧٧٦ ⇒ ١٬٧٦٥ موضعًا في تسعةِ أدوار) لأنَّ الأصلَ والمتغايرَ صارا باسمًا واحدًا فابتلع حارسُ\n";
    $o .= "التكرارِ ثانيَهما — **فرُدَّت الدفعةُ ثمَّ استُثنيت**. ومقياسُ اللوحةِ **#١٦ يستثنيها سلفًا بالنصِّ نفسِه**.\n\n";
    $o .= "## المسارات — من الاسمِ المخزَّنِ إلى المعتمَد\n\n| المسار | مخزَّنٌ | معتمَدٌ |\n|---|---|---|\n";
    $seen = array();
    foreach ($plan as $p) {
        if (isset($seen[$p['canon_route']])) { continue; }
        $seen[$p['canon_route']] = 1;
        $o .= '| `' . $p['canon_route'] . '` | ' . $p['it']['label_ar'] . ' | **' . $p['after'] . "** |\n";
    }
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/SIDEBAR_S2_LABEL.md', $o);
    echo "\n✔ كُتب docs/REPAIR01_20260823/SIDEBAR_S2_LABEL.md\n";
}
