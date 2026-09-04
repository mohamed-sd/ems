<?php
/**
 * tools/dept_addform_conformance.php — نموذجُ الإضافةِ في إدارةٍ: موحَّدٌ أم مختلف؟
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الأداةُ لا تعرف إدارةً بعينها**: المدى وسيطٌ (`--dept=DEP-11`) يُقرأ من
 *   `repair01_departments`، والعضويّةُ من `repair01_screen_registry` — فتعميمُها
 *   على إدارةٍ أخرى تغييرُ وسيطٍ لا نسخُ ملفّ.
 *
 * ◆ **المعيارُ من `ems-forms.css` لا من الذوق**: النموذجُ الموحَّدُ هو ما يحمل
 *   في **سمةِ `class` نفسِها** خطّافًا معلَنًا — `allforms` (جِلدٌ + طيٌّ
 *   افتراضيٌّ يُفتَح بـ`allforms-visible`) أو `ems-form` (جِلدٌ بلا طيٍّ لِما
 *   يدير ظهورَه بنفسه). والمرجعُ البصريُّ نموذجُ «إدارة العملاء».
 *
 * ⛔ **ولا يُطابَق النصُّ حيثما وقع**: كلُّ صفحةٍ تربط `assets/css/ems-forms.css`
 *   فتحوي الحروفَ «ems-form» — فمطابقةُ النصِّ تُعطي ٢٧ من ٥٥ وهي كذبٌ صريح.
 *   المطابقةُ هنا **داخلَ وسمِ `<form>` وفي سمةِ `class` بحدودِ كلمة**.
 *
 * ◆ **وثلاثةُ أحكامٍ لا حكمان**: سطحُ قراءةٍ بلا نموذجِ إضافةٍ أصلًا يُسجَّل
 *   `بلا نموذج` **لا «مختلف»** — وإلّا صار التقريرُ أحمرَ كذبًا (‏قاعدةُ
 *   مانيفستِ الإدارات: ما ليس عمليةً موجودةً يُسجَّل NA لا FAIL).
 *
 * ◆ **والعُدّةُ المشتركةُ تُحسَب حيث تُصيَّر**: أسطحُ `u13_screen_kit` نموذجُها
 *   في العُدّةِ لا في الملفّ — فحكمُها **يُقرأ من العُدّةِ لحظةَ القياس**، ولا
 *   يُثبَّت في الأداةِ نصًّا: رقمٌ صلبٌ هنا يُبقي التقريرَ أحمرَ بعد الإصلاح.
 *
 * ◆ **والشكلُ غيرُ السلوك**: يُقاسان عمودين مستقلَّين — الخطّافُ شكلٌ،
 *   و`allforms-visible` مع زرِّ `add-btn` سلوكُ الطيِّ والفتح.
 *
 * التشغيل: php tools/dept_addform_conformance.php [--dept=DEP-NN] [--csv]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$db = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($db->connect_errno) { exit('تعذّر الاتصال: ' . $db->connect_error . "\n"); }
$db->set_charset('utf8mb4');

/* ═══ عضويّةُ الإدارةِ من السجلِّ الحاكمِ لا باجتهاد ═════════════════════ */
$DEPT = 'DEP-01';
foreach ($argv as $a) {
    if (strpos($a, '--dept=') === 0) { $DEPT = strtoupper(substr($a, 7)); }
}
if (!preg_match('/^DEP-\d{2}$/', $DEPT)) { exit("⛔ مدًى غيرُ صالح: $DEPT (المتوقَّع DEP-NN)\n"); }
$dq = $db->query("SELECT name_ar FROM repair01_departments
                   WHERE canonical_code = '" . $db->real_escape_string($DEPT) . "'");
$DEPT_AR = ($dq && $dq->num_rows) ? $dq->fetch_row()[0] : '(‏غيرُ مسجَّلةٍ في دفترِ الإدارات)';

$screens = array();
$rs = $db->query("SELECT route, canonical_label_ar FROM repair01_screen_registry
                   WHERE owner_code = '" . $db->real_escape_string($DEPT) . "'
                     AND on_disk = 1 ORDER BY route");
while ($x = $rs->fetch_assoc()) { $screens[] = $x; }
printf("◆ %s (`owner_code=%s` · `on_disk=1`): %d سطحًا\n", $DEPT_AR, $DEPT, count($screens));
if (!$screens) { exit("⛔ لا سطحَ في هذا المدى — راجعْ الرمز\n"); }

/* ═══ فهرسُ العُدَدِ المشتركةِ — النموذجُ قد لا يكون في ملفِّ السطحِ أصلًا ═══
   ⛔ **ولا تُسمّى عُدّةٌ بعينها**: كان الكاشفُ يتتبّع `u13_screen_kit` وحدَها
   فصُنِّف `Clients/client_contacts.php` «بلا نموذج» وهو يُصيِّر نموذجَه من
   `party_contacts_view.php` — عمًى في القارئِ لا غيابٌ في المنتَج. فتُفهرَس
   عُدَدُ `includes/` كلُّها، ويُتبَع ما يضمُّه السطحُ منها. */
$KITS = array();
foreach (glob($ROOT . '/includes/*.php') as $kf) {
    $ks = file_get_contents($kf);
    if (!preg_match_all('/<form\b([^>]*)>(.*?)<\/form>/is', $ks, $mm, PREG_SET_ORDER)) { continue; }
    $hook = null;
    foreach ($mm as $m) {
        $tag = '<form' . $m[1] . '>';
        /* ⛔ **عتبةُ الحقلين لا تسري على العُدّة**: العُدّةُ تُصيِّر حقولَها في
           حلقةٍ فلا يظهر في ترميزِها إلّا `<input>` واحد — فاشتراطُ حقلين
           يُسقِطها من الفهرسِ ويُبقي أسطحَها «مختلفةً» بعد توحيدِها. */
        if (!sf_is_add_form($tag, $m[2], 1)) { continue; }
        $cls = preg_match('/class\s*=\s*["\']([^"\']*)["\']/i', $tag, $c) ? $c[1] : 'بلا صنف';
        if (sf_hooked($tag)) { $hook = array(true, $cls); break; }
        if ($hook === null) { $hook = array(false, $cls); }
    }
    if ($hook !== null) { $KITS[basename($kf)] = $hook; }
}
echo "◆ عُدَدُ `includes/` التي تُصيِّر نموذجَ إدخال:\n";
foreach ($KITS as $k => $h) {
    printf("   %-30s %-10s (%s)\n", $k, $h[0] ? 'موحَّد' : 'مختلف', $h[1]);
}

/**
 * أفي وسمِ `<form>` خطّافُ التوحيدِ في سمةِ `class`؟
 * ⛔ لا يُبحَث عن الحروفِ في الملفِّ كلِّه — رابطُ `ems-forms.css` يُطابقها.
 */
function sf_hooked($tag)
{
    return (bool) preg_match('/class\s*=\s*["\'][^"\']*(?:^|[\s"\'])(allforms|ems-form)(?:[\s"\']|$)/i', $tag)
        || (bool) preg_match('/class\s*=\s*["\'][^"\']*\ballforms\b/i', $tag)
        || (bool) preg_match('/class\s*=\s*["\'][^"\']*\bems-form\b(?!s)/i', $tag);
}

/**
 * أهو النموذجُ داخلَ خليّةِ جدولٍ (`<td>`)؟
 * ⛔ **فعلُ الصفِّ ليس نموذجَ إضافة**: «أخلفِ السعرَ» و«استلمْ دفعةً» و«غيّرْ
 *   حالَ الأداة» نماذجُ سطرٍ تُصيَّر مرّةً لكلِّ صفّ. وإلباسُها جِلدَ النموذجِ
 *   الموحَّدِ (بطاقةٌ بعرضِ الصفحةِ وشبكةُ خمسةِ أعمدةٍ وحبّةٌ عائمة) يهدم
 *   الجدولَ — فتُستثنى من المقام، ولا تُعَدُّ مخالفةً.
 */
function sf_in_cell($src, $pos)
{
    $td  = strripos(substr($src, 0, $pos), '<td');
    $etd = strripos(substr($src, 0, $pos), '</td>');
    return $td !== false && ($etd === false || $td > $etd);
}

/**
 * أهو نموذجُ إضافةٍ/تعديلٍ لا فلترةٍ ولا زرُّ فعلٍ مفرد؟
 * `$min` عتبةُ الحقولِ الحقيقيّة: اثنانِ في سطحٍ، وواحدٌ في عُدّةٍ تُصيِّر بحلقة.
 */
function sf_is_add_form($tag, $body, $min = 2)
{
    if (preg_match('/method\s*=\s*["\']get["\']/i', $tag)) { return false; }  /* فلترةٌ لا إضافة */
    $n = preg_match_all('/<(?:input|select|textarea)\b[^>]*/i', $body, $mm);
    if ($n === 0) { return false; }
    $real = 0;
    foreach ($mm[0] as $f) {
        if (preg_match('/type\s*=\s*["\'](hidden|submit|button|reset|search)["\']/i', $f)) { continue; }
        if (preg_match('/name\s*=\s*["\']csrf_token["\']/i', $f)) { continue; }
        $real++;
    }
    return $real >= $min;   /* بلوغُ العتبةِ = نموذجُ إدخال */
}

$U = array(); $D = array(); $N = array();
foreach ($screens as $s) {
    $p = $ROOT . '/' . $s['route'];
    if (!is_file($p)) { continue; }
    $src = file_get_contents($p);

    /* ⓪ أسطحُ `u13_screen_kit`: حقولُها **بياناتٌ في السطحِ** لا ترميزٌ في
       العُدّة — فعددُها يُقرأ من مصفوفةِ `$U13['actions'][…]['fields']`،
       **وطرازُها من العُدّةِ** لا من هذا الملفّ. */
    if (strpos($src, 'u13_screen_kit') !== false) {
        $nf = 0;
        if (preg_match_all("/'fields'\s*=>\s*array\s*\((.*?)\)\s*,/s", $src, $fm)) {
            foreach ($fm[1] as $blk) { $nf += preg_match_all("/'[^']+'\s*=>\s*'/", $blk); }
        }
        if ($nf >= 2) {
            /* ⛔ **ولا يُثبَّت حكمُ العُدّةِ في المقياس**: كان هذا الفرعُ يكتب
               «مختلف» صلبًا، فلو وُحِّدت العُدّةُ لبقي المقياسُ أحمرَ كذبًا.
               الحكمُ يُقرأ من العُدّةِ نفسِها لحظةَ القياس. */
            $kh = $KITS['u13_screen_kit.php'] ?? array(false, 'u13-act');
            $row = array($s['route'], $s['canonical_label_ar'], 'عُدّةٌ: u13_screen_kit',
                         $kh[1] . ' · ' . $nf . ' حقلًا', '—');
            if ($kh[0]) { $U[] = $row; } else { $D[] = $row; }
        } else {
            $N[] = array($s['route'], $s['canonical_label_ar'], 'u13 بلا فعلِ تسجيل', '—', '—');
        }
        continue;
    }

    /* ① نماذجُ الملفِّ نفسِه */
    $hooked = array(); $bare = array();
    if (preg_match_all('/<form\b([^>]*)>(.*?)<\/form>/is', $src, $mm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        foreach ($mm as $m) {
            $tag = '<form' . $m[1][0] . '>';
            if (!sf_is_add_form($tag, $m[2][0])) { continue; }
            if (sf_in_cell($src, $m[0][1])) { continue; }   /* فعلُ صفٍّ لا نموذجُ إضافة */
            if (sf_hooked($tag)) { $hooked[] = $tag; } else { $bare[] = $tag; }
        }
    }
    /* السلوك: الطيُّ والفتحُ بـ`allforms-visible` وزرُّ الإضافةِ في الترويسة */
    $beh = array();
    if (strpos($src, 'allforms-visible') !== false) { $beh[] = 'طيّ'; }
    if (strpos($src, 'add-btn') !== false) { $beh[] = 'زرّ'; }
    $behTxt = $beh ? implode('+', $beh) : '—';

    if ($hooked && !$bare) {
        $U[] = array($s['route'], $s['canonical_label_ar'], 'allforms/ems-form', count($hooked) . ' نموذج', $behTxt);
    } elseif ($hooked && $bare) {
        $D[] = array($s['route'], $s['canonical_label_ar'], 'مختلط',
                     count($hooked) . ' موحَّد + ' . count($bare) . ' خارجه', $behTxt);
    } elseif ($bare) {
        $cls = array();
        foreach ($bare as $t) {
            if (preg_match('/class\s*=\s*["\']([^"\']+)["\']/i', $t, $c)) { $cls[] = $c[1]; }
        }
        $D[] = array($s['route'], $s['canonical_label_ar'], 'خاصٌّ بالصفحة',
                     $cls ? implode(' · ', array_unique($cls)) : 'بلا صنف', $behTxt);
    } else {
        /* ② بلا نموذجٍ في الملفِّ — أفي عُدّةٍ مضمومةٍ نموذجُه؟ */
        $viaKit = null;
        foreach ($KITS as $kf => $h) {
            if (preg_match('/(?:require|include)(?:_once)?\s*[^;]*[\'"\/]'
                . preg_quote($kf, '/') . '[\'"]/i', $src)) {
                if ($viaKit === null || ($h[0] && !$viaKit[1][0])) { $viaKit = array($kf, $h); }
            }
        }
        if ($viaKit !== null) {
            $row = array($s['route'], $s['canonical_label_ar'], 'عُدّةٌ: ' . $viaKit[0],
                         $viaKit[1][1], $behTxt);
            if ($viaKit[1][0]) { $U[] = $row; } else { $D[] = $row; }
        } else {
            $N[] = array($s['route'], $s['canonical_label_ar'], 'قراءةٌ/لوحةٌ/تقرير', '—', '—');
        }
    }
}

function sf_table($title, $rows)
{
    printf("\n══ %s — %d سطحًا ══\n", $title, count($rows));
    if (!$rows) { echo "   (لا شيء)\n"; return; }
    printf("   %-44s %-30s %-20s %s\n", 'الملف', 'الاسم', 'الطراز', 'السلوك');
    echo '   ' . str_repeat('-', 118) . "\n";
    foreach ($rows as $r) {
        printf("   %-44s %-30s %-20s %s\n",
            mb_substr($r[0], 0, 42), mb_substr((string) $r[1], 0, 28),
            mb_substr($r[2] . ' (' . $r[3] . ')', 0, 40), $r[4]);
    }
}
sf_table('① بنموذجِ الإضافةِ الموحَّد', $U);
sf_table('② بنموذجٍ مختلف', $D);
sf_table('③ بلا نموذجِ إضافةٍ أصلًا (لا تُحسَب مخالفةً)', $N);

$withForm = count($U) + count($D);
echo "\n" . str_repeat('=', 76) . "\n";
printf("الحصيلة: %d سطحًا · منها %d بنموذجِ إضافةٍ و%d بلا نموذج\n",
       count($screens), $withForm, count($N));
printf("   موحَّدٌ  %d من %d بنموذج (%.1f%%)\n", count($U), $withForm, 100 * count($U) / max(1, $withForm));
printf("   مختلفٌ %d من %d بنموذج (%.1f%%)\n", count($D), $withForm, 100 * count($D) / max(1, $withForm));

/* ═══ الشاهدُ السالب — أيميّز المقياسُ أصلًا؟ ════════════════════════════ */
echo "\n◆ الشاهدُ السالب — المقياسُ نفسُه على مرجعٍ معلومِ الحال:\n";
/* ⛔ والشاهدُ يُعايَر على حالٍ مُتحقَّقٍ منه بالعينِ لا على ظنّ: `penalties.php`
   كان في الشاهدِ «مختلف» فخرج «بلا نموذج» — والصوابُ مع الكاشف: نموذجُه
   مودالُ تأكيدٍ بحقلٍ حقيقيٍّ واحد (سببُ الإعفاء) لا نموذجُ إضافة. */
$probe = array(
    'Clients/clients.php'             => 'موحَّد (مرجعُ التصميمِ الرسميّ · allforms)',
    'contracts/sales_activities.php'  => 'موحَّد عبر عُدّةِ u13_screen_kit',
    'Clients/client_contacts.php'     => 'موحَّد عبر عُدّةِ party_contacts_view',
    'Contracts/penalties.php'         => 'بلا نموذجِ إضافة (مودالُ تأكيدٍ بحقلٍ واحد)',
    'Contracts/contract_lines.php'    => 'موحَّد — والمخالفُ فيه فعلُ صفٍّ في <td>',
);
foreach ($probe as $f => $expect) {
    $in = 'خارجَ العيّنة';
    foreach ($U as $r) { if ($r[0] === $f) { $in = 'صُنِّف: موحَّد'; } }
    foreach ($D as $r) { if ($r[0] === $f) { $in = 'صُنِّف: مختلف'; } }
    foreach ($N as $r) { if ($r[0] === $f) { $in = 'صُنِّف: بلا نموذج'; } }
    printf("   %-26s المتوقَّع: %-32s %s\n", $f, $expect, $in);
}

/* ═══ شاهدٌ صناعيٌّ — العيّنةُ كلُّها خضراءُ فلا تُثبِت التمييزَ وحدَها ═════
   ⛔ **صفرُ مخالفٍ على مقياسٍ لا يُحمِّر أخضرُ كاذب**: حين تخلو العيّنةُ من
   حالةٍ مخالفةٍ يفقد الشاهدُ الحيُّ قدرتَه على البرهنة، فتُصنَع الحالةُ. */
echo "\n◆ الشاهدُ الصناعيّ — أيُحمِّر الكاشفُ على وسمٍ غيرِ مخطوف؟\n";
$synth = array(
    '<form method="post" class="row g-2 mb-3">'          => false,
    '<form method="post" class="u13-act">'               => false,
    '<form method="post" class="bl-form-inline">'        => false,
    '<link rel="stylesheet" href="css/ems-forms.css">'   => false,
    '<form method="post" class="allforms">'              => true,
    '<form method="post" class="ems-form pc-form">'      => true,
);
$bad = 0;
foreach ($synth as $tag => $want) {
    $got = sf_hooked($tag);
    if ($got !== $want) { $bad++; }
    printf("   %-50s المتوقَّع %-6s المقيس %-6s %s\n", $tag,
        $want ? 'مخطوف' : 'خامّ', $got ? 'مخطوف' : 'خامّ', $got === $want ? '✔' : '⛔');
}
echo $bad === 0
    ? "   ✔ الكاشفُ يميّز — فمئةُ بالمئةٍ حكمٌ مقيسٌ لا عمًى\n"
    : "   ⛔ الكاشفُ لا يميّز — لا يُعتمَد رقمُه\n";

if (in_array('--csv', $argv, true)) {
    $out = $ROOT . '/docs/addform_conformance_' . $DEPT . '.csv';
    $fh = fopen($out, 'w');
    fwrite($fh, "\xEF\xBB\xBF");
    fputcsv($fh, array('الحكم', 'الملف', 'الاسم', 'الطراز', 'التفصيل', 'السلوك'));
    foreach (array('موحَّد' => $U, 'مختلف' => $D, 'بلا نموذج' => $N) as $k => $set) {
        foreach ($set as $r) { fputcsv($fh, array_merge(array($k), $r)); }
    }
    fclose($fh);
    echo "\n◆ CSV: $out\n";
}
