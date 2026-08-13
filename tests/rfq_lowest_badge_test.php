<?php
/**
 * tests/rfq_lowest_badge_test.php — شاهدُ شارةِ «الأدنى» في مقارنةِ العروض
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0341
 *
 * **اختبارُ القبولِ نصًّا**: «عرضان بعملتين مختلفتين يُرتَّبان بالمعادلِ
 * الموحَّد، وشارةُ «الأدنى» تظهر على صفٍّ واحدٍ فقط هو الأقلُّ معادلًا.»
 *
 * ── العطلُ المقيسُ قبل الإصلاح ─────────────────────────────────────────────
 * `Procurement/rfq_compare_award.php` كان يفرز بـ`ORDER BY q.unit_price`
 * ويقارن `unit_price <= $best` — **والسعرُ خامٌّ بعملتِه**. فـ:
 *   · عرضٌ بعملةٍ منخفضةِ القيمةِ يُوسَم «الأدنى» وهو الأغلى فعلًا،
 *   · و`<=` تمنح الشارةَ لكلِّ المتساوين فتظهر على صفوفٍ عدّة.
 * والقرارُ الأهمُّ في وحدةِ المشترياتِ (اختيارُ الأرخص) يُبنى على ذلك.
 *
 * ── حالةُ الاختبارِ **حاسمةٌ بأرقامِ القاعدةِ الحيّة** ──────────────────────
 * سعرُ SDG في `fin_fx_rates` = 0.000185 من الأساس (USD). فـ:
 *   · عرضُ **1000 SDG** ⇒ معادلُه **0.185$**  (الأرخصُ حقًّا)
 *   · عرضُ **100 USD**  ⇒ معادلُه **100$**
 * والفرزُ الخامُّ يضع 100 قبل 1000 فيمنح الشارةَ **للأغلى**. فالحالةُ تُفرّق
 * بين الصوابِ والخطأِ ولا تمرُّ بالحظّ.
 *
 * ◆ والوسمُ **عائليٌّ ثابتٌ** (`RFQBADGE`) لا `getmypid` — فجولةٌ ماتت لا تُعمي
 *   التالية. والكنسُ بالعائلةِ ويُفحص **مُرجَعُ كلِّ حذف** (FK يردُّ صامتًا).
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
ob_start();
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { @ob_end_clean(); }
/* ◆ **سياقُ المستأجرِ قبلَ أيِّ نداءٍ لـfx**: بوابةُ `ems_tenant_db()` مغلقةٌ
     افتراضًا (ADR-02) وتُنهي العمليةَ في CLI بلا جلسة — وقد قِيس: الاختبارُ
     كان يموت بـ255 بعد أوّلِ سطرٍ بلا رسالة. */
if (!isset($_SESSION)) { $_SESSION = array(); }
$_SESSION['user'] = array('id' => 1, 'role' => '1', 'company_id' => 4, 'name' => 'rfq badge test');

$conn = $GLOBALS['conn'];
$CO = 4;
$FAMILY = 'RFQBADGE';
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };

/* كنسٌ بعائلةِ الوسمِ — قبلَ الجولةِ وبعدَها */
$sweep = function () use ($conn, $CO, $FAMILY) {
    $n = 0;
    $q = $conn->query("DELETE FROM rfq_quotes WHERE company_id = {$CO} AND note LIKE '%{$FAMILY}%'");
    if ($q !== false) { $n += max(0, $conn->affected_rows); }
    return $n;
};
$say('══ INJ-0341 · شارةُ «الأدنى» بالمعادلِ لا بالسعرِ الخام  (كُنس '
     . $sweep() . ' من عائلةِ ' . $FAMILY . ')');

/* ── ① سعرُ الصرفِ الذي تقوم عليه الحالةُ موجودٌ فعلًا ─────────────────────── */
require_once $ROOT . '/includes/fx.php';
$base = function_exists('ems_fx_base_currency') ? ems_fx_base_currency() : '';
$ok($base !== '', 'عملةُ الدفاترِ مُعرَّفةٌ (' . ($base !== '' ? $base : '—') . ')');
$conv = function_exists('ems_fx_to_base') ? ems_fx_to_base(1000, 'SDG') : null;
$ok(is_array($conv) && !empty($conv['ok']),
    'وسعرُ صرفِ SDG مسجَّلٌ — فالحالةُ تقوم على رقمٍ حيٍّ لا مفترَض',
    'بلا سعرِ صرفٍ لا يُفرّق الاختبارُ بين الصوابِ والخطأ');
$eqLow = (is_array($conv) && !empty($conv['ok'])) ? floatval($conv['base']) : null;
$conv2 = function_exists('ems_fx_to_base') ? ems_fx_to_base(100, (string) $base) : null;
$eqHigh = (is_array($conv2) && !empty($conv2['ok'])) ? floatval($conv2['base']) : 100.0;
$ok($eqLow !== null && $eqLow < $eqHigh,
    'والحالةُ **حاسمة**: 1000 SDG ≈ ' . ($eqLow === null ? '—' : round($eqLow, 4))
    . ' أرخصُ من 100 ' . $base . ' ≈ ' . round($eqHigh, 2)
    . ' — بينما الفرزُ الخامُّ يعكسها',
    'لو لم تنعكس لما فرّق الاختبارُ بين الإصلاحِ وعدمِه');

/* ── ② زرعُ الحالة ────────────────────────────────────────────────────────── */
/* ◆ **الطلبُ يجب أن يكون له سطرٌ**: `rfq_quotes.line_id` بمفتاحٍ أجنبيٍّ على
     `rfq_lines`، فطلبٌ بلا أسطرٍ يردُّ الزرعَ بـ1452 صامتًا في نظرِ الفاحصِ
     («زُرع 0») ويُقرأ عطلًا في المفحوصِ وهو عطلٌ في اختياري. فيُختار الطلبُ
     **بوجودِ سطرِه** لا بأحدثِ معرِّف. */
$rfqId = null; $lineId = null;
$r = $conn->query("SELECT l.rfq_id, l.id FROM rfq_lines l
                     JOIN supplier_rfqs r ON r.id = l.rfq_id
                    WHERE r.company_id = {$CO} AND COALESCE(r.is_deleted,0) = 0
                    ORDER BY l.id DESC LIMIT 1");
if ($r && $r->num_rows) { $x = $r->fetch_row(); $rfqId = (int) $x[0]; $lineId = (int) $x[1]; }
$ok($rfqId !== null, 'وُجد طلبُ عروضٍ **له سطرٌ** (#' . ($rfqId ?: '—')
    . ' · سطر #' . ($lineId ?: '—') . ') — لا رقمٌ مخترَع',
    'المفتاحُ الأجنبيُّ على rfq_lines يردُّ الزرعَ بلا سطر');

$sups = array();
$r = $conn->query("SELECT id, name FROM suppliers WHERE company_id = {$CO} ORDER BY id LIMIT 2");
while ($r && ($x = $r->fetch_assoc())) { $sups[] = $x; }
$ok(count($sups) >= 2, 'ووُجد مورّدان لعرضين');

$made = 0;
if ($rfqId !== null && count($sups) >= 2) {
    $rows = array(
        array($sups[0]['id'], 1000.0, 'SDG', 'الأرخصُ معادلًا — ' . $FAMILY),
        array($sups[1]['id'], 100.0,  (string) $base, 'الأرخصُ خامًّا والأغلى معادلًا — ' . $FAMILY),
    );
    $seedErr = '';
    foreach ($rows as $rw) {
        $st = $conn->prepare('INSERT INTO rfq_quotes (company_id, rfq_id, line_id, supplier_id, unit_price,
                                currency, qty_offered, readiness_days, note, submitted_at)
                              VALUES (?, ?, ?, ?, ?, ?, 1, 5, ?, NOW())');
        if (!$st) { $seedErr = $conn->error; continue; }
        $st->bind_param('iiiidss', $CO, $rfqId, $lineId, $rw[0], $rw[1], $rw[2], $rw[3]);
        if ($st->execute()) { $made++; } elseif ($seedErr === '') { $seedErr = $st->error; }
        $st->close();
    }
    if ($seedErr !== '') { $say('     ⟵ سببُ تعذُّرِ الزرع: ' . mb_substr($seedErr, 0, 120)); }
}
$ok($made === 2, 'زُرع عرضان بعملتين مختلفتين (' . $made . ')');

/* ── ③ القياسُ على التصييرِ الفعليّ ──────────────────────────────────────── */
$BASE = 'http://localhost/ems';
$jar = sys_get_temp_dir() . '/rfqb_' . $FAMILY . '.txt';
@unlink($jar);
$http = function ($url) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 90));
    $b = curl_exec($ch);
    curl_close($ch);
    return (string) $b;
};
$login = function ($u) use ($jar, $BASE) {
    @unlink($jar);
    $ch = curl_init($BASE . '/login.php');
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 60));
    $b = (string) curl_exec($ch); curl_close($ch);
    preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $b, $t);
    $ch = curl_init($BASE . '/login.php');
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 60,
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(array(
            'username' => $u, 'password' => '12345678', 'csrf_token' => isset($t[1]) ? $t[1] : ''))));
    $b = (string) curl_exec($ch); curl_close($ch);
    return mb_strpos($b, 'name="password"') === false;
};
/* حسابٌ يملك عرضَ الشاشة — يُقرأ من سجلِّ الشاشاتِ لا يُكتب بيد */
$user = '';
$st = $conn->prepare("SELECT u.username FROM role_permissions rp
                        JOIN modules m ON m.id = rp.module_id
                        JOIN users u ON u.role = rp.role_id AND u.company_id = ?
                       WHERE m.code = 'Procurement/rfq_compare_award.php' AND rp.can_view = 1
                         AND u.username <> '' ORDER BY rp.role_id, u.id LIMIT 1");
$st->bind_param('i', $CO);
$st->execute();
$x = $st->get_result()->fetch_row();
$st->close();
if ($x) { $user = (string) $x[0]; }
$ok($user !== '' && $login($user), 'دخلَ حسابٌ يملك مقارنةَ العروض (' . ($user ?: '—') . ')');

if ($user !== '' && $rfqId !== null && $made === 2) {
    $html = $http($BASE . '/Procurement/rfq_compare_award.php?rfq=' . $rfqId);
    $ok(mb_strpos($html, 'مقارنة') !== false || mb_strpos($html, 'العروض') !== false,
        'وصُيِّرت الشاشة');

    /* عددُ الشاراتِ — واحدةٌ فقط */
    $badges = preg_match_all('~>الأدنى<~u', $html);
    $ok($badges === 1, '**شارةُ «الأدنى» على صفٍّ واحدٍ فقط** (' . $badges . ')',
        'كانت `<=` تمنحها لكلِّ المتساوين');

    /* وعلى مَن؟ — الصفُّ الذي يحمل الشارةَ يجب أن يكون عرضَ SDG */
    $onSdg = (bool) preg_match('~SDG.{0,400}?>الأدنى<|>الأدنى<.{0,400}?SDG~su', $html);
    $ok($onSdg, '**وعلى العرضِ الأقلِّ معادلًا (SDG) لا الأقلِّ خامًّا**',
        'الفرزُ الخامُّ يضع 100 قبل 1000 فيمنحها للأغلى فعلًا');

    /* والمعادلُ معروضٌ فيرى المقرِّرُ على أيِّ أساسٍ قُورن */
    $ok(mb_strpos($html, 'ems-eq-base') !== false || mb_strpos($html, '≈') !== false,
        '   ويُعرض المعادلُ بعملةِ الدفاترِ إلى جانبِ السعرِ الخام');
}

/* ── ④ الكنس ─────────────────────────────────────────────────────────────── */
@unlink($jar);
$left = $sweep();
$say('   كُنس ختامًا: ' . $left . ' صفًّا');
$chk = $conn->query("SELECT COUNT(*) FROM rfq_quotes WHERE company_id = {$CO} AND note LIKE '%{$FAMILY}%'");
$rem = $chk ? (int) $chk->fetch_row()[0] : -1;
$ok($rem === 0, 'صفرُ ثغرةٍ من عائلةِ الوسمِ بعد الجولة (' . $rem . ')',
    'FK يردُّ صامتًا — فالمُرجَعُ مفحوصٌ والباقي مُعلَن');

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
