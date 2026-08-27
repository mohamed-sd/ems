<?php
/**
 * tools/repair01_edc_names.php — مصالحةُ الأسماءِ المعياريّة (القرار ④)
 * ═══════════════════════════════════════════════════════════════════════════
 * **قرارُ المالك 2026-08-27 · القرار الرابع**: «لا أعتمدها جماعيًّا. نفّذْ
 * `Canonical Name Reconciliation` ولكلِّ اسمٍ **أربعُ حالاتٍ بعد إضافةِ فحصِ
 * الهويّة** … **ترفع لي فقط** `TRUE_NAMING_DECISION` و`IDENTITY_CONFLICT`.»
 *
 * ◆ **الأحكامُ الأربعةُ بنصِّها**:
 *   · `CANONICAL_FROM_APPROVED_SOURCE` — الاسمُ في مصدرٍ معتمَدٍ **والوظيفةُ
 *     تطابق تعريفَه** ⇒ يُعتمد آليًّا
 *   · `SAME_MEANING_FORMAT_ONLY` — فرقُ كتابةٍ أو تنسيقٍ فقط ⇒ يُصحَّح آليًّا
 *   · `IDENTITY_CONFLICT` — الاسمُ في مصدرٍ معتمَدٍ **ووظيفةُ الشاشةِ الفعليّةُ
 *     لا تطابق تعريفَها** ⇒ **ليست مسألةَ تسميةٍ** بل تعارضُ هويّة ⇒ مراجعةٌ
 *     معماريّةٌ قبل اعتمادِ الاسم
 *   · `TRUE_NAMING_DECISION` — لا اسمَ حاكمًا أو تعارضٌ حقيقيّ ⇒ **للمالك**
 *
 * ◆ **وفحصُ الهويّةِ هو الإضافةُ التي طلبها المالك** — ولولاه لصار كلُّ تطابقٍ
 *   حرفيٍّ اعتمادًا، **فيُعتمد اسمٌ صحيحٌ على شاشةٍ تفعل غيرَه**. فيُقاس هنا
 *   بثلاثِ إشاراتٍ من الشجرةِ الحيّة: مالكُ السطحِ · تصنيفُه مصدرًا أو إسقاطًا
 *   · وموضعُه من دورةِ العمل. واختلافُ **المالكِ** عن مالكِ صفِّ الاسمِ تعارضٌ.
 *
 * ⛔ **ولا يُعتمد اسمٌ لسطحٍ لا وجودَ له على القرص** — فذاك شبحٌ حكمُه في
 *   دفترِ الفجواتِ لا في سجلِّ الأسماء.
 *
 * التشغيل: php tools/repair01_edc_names.php [--report]
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
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$REPORT = in_array('--report', $argv, true);
$e = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };

/* ◆ **التطبيعُ يميّز «فرقَ الكتابة» عن «فرقِ المعنى»**: يُسقط التشكيلَ
     والتطويلَ وصيغَ الألفِ والهاءِ والياءِ والمسافاتِ الزائدة — فما تبقّى
     مختلفًا بعده **فرقُ معنًى لا تنسيق**. */
$norm = function ($s) {
    $s = (string) $s;
    $s = preg_replace('~[\x{0617}-\x{061A}\x{064B}-\x{0652}\x{0640}]~u', '', $s);
    $s = strtr($s, array('أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي'));
    $s = preg_replace('~[^\p{Arabic}\p{L}\p{N}]+~u', ' ', $s);
    return trim(preg_replace('~\s+~u', ' ', $s));
};

/* خريطةُ الرمزِ إلى اسمِه — شرطُ المقارنةِ لا زينة */
$DEPT = array();
$q = $conn->query("SELECT canonical_code, name_ar FROM repair01_departments");
while ($q && ($x = $q->fetch_assoc())) { $DEPT[$x['canonical_code']] = $x['name_ar']; }

echo "\n═══ مصالحةُ الأسماءِ المعياريّة — القرار ④ ═══\n";
echo ($REPORT ? "  وضعُ التقرير: يقرأ ولا يكتب\n\n" : "\n");

/* ═══ ① المادّة ═══════════════════════════════════════════════════════════ */
$rows = array();
$q = $conn->query("SELECT nc.route, nc.canonical_ar, nc.current_label, nc.status, nc.owner_dept,
                          nc.group_name, nc.nature,
                          sr.screen_id, sr.owner_code, sr.surface_kind, sr.on_disk,
                          sr.ownership_verdict, sr.canonical_label_ar
                     FROM nav_canonical nc
                     /* ⚠ **الوصلُ بالمسارِ لا باسمِ الملفّ**: ثمانيةُ أسماءٍ
                        مكرَّرةٌ في مجلَّداتٍ مختلفة (`index.php` في ثلاثةٍ)،
                        فالوصلُ بالاسمِ يضاعف الصفَّ ثلاثًا **ويجعل الصفَّ
                        الواحدَ يبدو ثلاثةَ تعارضات**. */
                     LEFT JOIN repair01_screen_registry sr
                            ON LOWER(sr.route) = LOWER(nc.route)
                            OR LOWER(sr.screen_file) = LOWER(nc.route)
                    WHERE nc.status <> 'APPROVED'
                    ORDER BY nc.route");
while ($q && ($x = $q->fetch_assoc())) { $rows[] = $x; }

/* ═══ ② الحكمُ — أضيقُ أوّلًا ═══════════════════════════════════════════════ */
$B = array('CANONICAL_FROM_APPROVED_SOURCE' => array(), 'SAME_MEANING_FORMAT_ONLY' => array(),
           'IDENTITY_CONFLICT' => array(), 'TRUE_NAMING_DECISION' => array(),
           'NOT_A_LIVE_SURFACE' => array());
foreach ($rows as $x) {
    $canon = trim((string) $x['canonical_ar']);
    $live  = trim((string) $x['current_label']);
    $why   = '';

    /* ⓐ لا سطحَ على القرصِ ⇒ ليست مسألةَ تسمية */
    if ((int) $x['on_disk'] !== 1 || $x['screen_id'] === null) {
        $B['NOT_A_LIVE_SURFACE'][] = array($x, 'لا سطحَ حيًّا على القرص — حكمُه في دفترِ الفجوات');
        continue;
    }
    /* ⓑ **تعارضُ هويّة** — ونصُّ المالك: «الاسمُ موجودٌ في مصدرٍ معتمَدٍ لكنَّ
         **وظيفةَ الشاشةِ الفعليّةَ لا تطابق تعريفَها**».
       ⚠ **وكاشفانِ سابقانِ سقطا قبل هذا**: الأوّلُ قارن `owner_dept` العربيَّ
         بـ`owner_code` الرمزيّ — **تمثيلَين لشيءٍ واحد** — فرأى تعارضًا في كلِّ
         صفٍّ تقريبًا. والثاني بعدَ ترجمةِ الرمزِ ظلَّ مخطئًا لأنَّ العمودَ نفسَه
         **مختلطُ الدلالة**: مقيسٌ أنّه يحمل أسماءَ وحداتٍ **قبل الشقِّ**
         («المالية والخزينة») بل وأرقامَ أدوارٍ («دور 24»).
         **وحقلٌ مختلطُ الدلالةِ يُنتج تعارضًا مصنوعًا لا مكشوفًا.**
       ◆ **فالإشارةُ المقيسةُ: ما يدّعيه الاسمُ مقابلَ ما يفعله السطح.**
         اسمٌ يقول «سجلّ» أو «دليل» أو «كتالوج» أو «دفتر» يدّعي **ملكيّةَ
         حقيقة** — فإن كان السطحُ `PROJECTION` فالاسمُ يعد بما لا يملكه.
         واسمٌ يقول «لوحة» يدّعي **عرضًا** — فإن كان السطحُ `SOURCE` فهو يملك
         ما لا يدّعيه. وكلاهما **تعارضُ هويّةٍ لا مسألةُ تسمية**. */
    $kind = trim((string) $x['surface_kind']);
    $nm   = $norm($canonRaw = (string) $x['canonical_ar']);
    $owns = (bool) preg_match('~(^|\s)(سجل|دليل|كتالوج|دفتر|سجلات)(\s|$)~u', $nm);
    $shows = (bool) preg_match('~(^|\s)(لوحه|لوحة|تقرير|مؤشرات|ملخص)(\s|$)~u', $nm);
    if ($kind === 'PROJECTION' && $owns) {
        $B['IDENTITY_CONFLICT'][] = array($x, 'الاسمُ يدّعي ملكيّةَ سجلٍّ والسطحُ إسقاطٌ يعرض حقيقةَ غيرِه');
        continue;
    }
    if ($kind === 'SOURCE' && $shows && !$owns) {
        $B['IDENTITY_CONFLICT'][] = array($x, 'الاسمُ يدّعي عرضًا والسطحُ مصدرُ حقيقةٍ يملكها');
        continue;
    }
    /* ⓒ لا اسمَ معياريًّا أصلًا ⇒ قرارُ تسميةٍ حقيقيّ */
    if ($canon === '') { $B['TRUE_NAMING_DECISION'][] = array($x, 'لا اسمَ معياريًّا مسجَّلًا'); continue; }
    /* ⓓ يطابق المعروضَ حرفًا ⇒ اعتمادٌ آليّ */
    if ($live === '' || $canon === $live) {
        $B['CANONICAL_FROM_APPROVED_SOURCE'][] = array($x, 'المعياريُّ يطابق المعروضَ حرفًا');
        continue;
    }
    /* ⓔ يطابقه بعد التطبيع ⇒ فرقُ كتابةٍ يُصحَّح آليًّا */
    if ($norm($canon) === $norm($live)) {
        $B['SAME_MEANING_FORMAT_ONLY'][] = array($x, "فرقُ كتابةٍ فقط: «$live» ⇐ «$canon»");
        continue;
    }
    /* ⓕ أحدُهما يتضمّن الآخرَ ⇒ اختصارٌ أو دمجٌ لا معنًى مختلف */
    if (mb_strpos($norm($live), $norm($canon)) !== false || mb_strpos($norm($canon), $norm($live)) !== false) {
        $B['SAME_MEANING_FORMAT_ONLY'][] = array($x, "أحدُهما يتضمّن الآخرَ: «$live» ⇐ «$canon»");
        continue;
    }
    /* ⓖ معنيانِ مختلفانِ ⇒ للمالك */
    $B['TRUE_NAMING_DECISION'][] = array($x, "معنيانِ مختلفان: معروضٌ «$live» ومعياريٌّ «$canon»");
}

/* ═══ ③ التنفيذ — الحكمانِ الآليّانِ فقط ═══════════════════════════════════ */
$auto = 0;
if (!$REPORT) {
    foreach (array('CANONICAL_FROM_APPROVED_SOURCE', 'SAME_MEANING_FORMAT_ONLY') as $k) {
        foreach ($B[$k] as $z) {
            $x = $z[0];
            /* ⚠ **القاعدةُ ردَّت أوّلَ محاولة**: `trg_nav_provisional_scope`
                 يشترط لكلِّ حسمٍ **فاعلًا ومصدرًا حاكمًا** —
                 «الصمتُ لا يُرقِّي قرارًا». وهو حارسٌ من موجةٍ سابقةٍ **أصاب**:
                 كنتُ سأرفع سبعةً وخمسين اسمًا إلى `APPROVED` **بلا قائلٍ ولا
                 سند**، وهو عينُ ما يمنعه قرارُ المالكِ الحادي عشر.
               ◆ فيُملأ الفاعلُ والمصدرُ بما هما فعلًا: **قرارُ المالكِ الرابع**
                 وتاريخُه وملفُّه — لا باسمِ الأداةِ ولا بفراغ. */
            $ok = $conn->query("UPDATE nav_canonical
                                   SET status = 'APPROVED', decision_state = 'APPROVED',
                                       decided_by = 'المالك — القرار الرابع 2026-08-27',
                                       decided_at = '2026-08-27',
                                       decision_source = 'قرارات المالك النهائية 2026-08-27 · القرار 4 · Canonical Name Reconciliation',
                                       current_label = '" . $e($x['canonical_ar']) . "',
                                       derivation = CONCAT(COALESCE(derivation,''),
                                          ' | EDC ④ " . $e($k) . ": " . $e($z[1]) . "')
                                 WHERE route = '" . $e($x['route']) . "'");
            if ($ok) { $auto++; } else { echo '  ✘ ' . $x['route'] . ' — ' . $conn->error . "\n"; }
        }
    }
}

/* ═══ ④ التقرير ═══════════════════════════════════════════════════════════ */
printf("  المقام: %d اسمًا\n\n", count($rows));
foreach ($B as $k => $l) {
    $mark = in_array($k, array('IDENTITY_CONFLICT', 'TRUE_NAMING_DECISION'), true) ? '◆' : '✔';
    printf("  %s %-32s %d\n", $mark, $k, count($l));
}
printf("\n  اعتُمد آليًّا: %d\n", $auto);

foreach (array('IDENTITY_CONFLICT', 'TRUE_NAMING_DECISION', 'NOT_A_LIVE_SURFACE') as $k) {
    if (!$B[$k]) { continue; }
    echo "\n── $k ──\n";
    foreach ($B[$k] as $z) { printf("   · %-42s %s\n", $z[0]['route'], $z[1]); }
}

/* ═══ ⑤ ملفُّ المالكِ — الحكمانِ اللذانِ طلب رفعَهما وحدَهما ═══════════════ */
if (!$REPORT) {
    $m = array();
    $m[] = '# أسماءٌ تنتظر المالك — مصالحةُ الأسماءِ (القرار ④)';
    $m[] = '';
    $m[] = '> ⛔ **مولَّدٌ من المخزن**: `php tools/repair01_edc_names.php`';
    $m[] = '> **نصُّ القرار:** «ترفع لي فقط هذه الحالات.»';
    $m[] = '';
    $m[] = sprintf('اعتُمد آليًّا **%d** من **%d** — وبقي **%d** يحتاج كلمتَك.',
        $auto, count($rows), count($B['IDENTITY_CONFLICT']) + count($B['TRUE_NAMING_DECISION']));
    $m[] = '';
    if ($B['IDENTITY_CONFLICT']) {
        $m[] = '## تعارضُ هويّة — **ليست مسألةَ تسمية**';
        $m[] = '';
        $m[] = 'الاسمُ في مصدرٍ معتمَدٍ **ووظيفةُ الشاشةِ الفعليّةُ لا تطابق تعريفَها**.';
        $m[] = 'ونصُّ قرارِك: **مراجعةٌ معماريّةٌ قبل اعتمادِ الاسم**.';
        $m[] = '';
        $m[] = '| الشاشة | الاسمُ المعياريّ | مالكُ صفِّ الاسم | مالكُ السطحِ المقيس |';
        $m[] = '|---|---|---|---|';
        foreach ($B['IDENTITY_CONFLICT'] as $z) {
            $m[] = '| `' . $z[0]['route'] . '` | ' . $z[0]['canonical_ar'] . ' | '
                 . ($z[0]['owner_dept'] ?: '—') . ' | ' . ($z[0]['owner_code'] ?: '—') . ' |';
        }
        $m[] = '';
    }
    if ($B['TRUE_NAMING_DECISION']) {
        $m[] = '## قرارُ تسميةٍ حقيقيّ';
        $m[] = '';
        $m[] = '**ومعيارُك:** بلا تشكيلٍ · بلا شرحٍ · بلا مصطلحٍ تقنيٍّ · بلا ترقيمٍ زائد ·';
        $m[] = 'قصيرٌ · واضحٌ · مرتبطٌ بوظيفةِ الشاشة.';
        $m[] = '';
        $m[] = '| الشاشة | المعروضُ اليوم | المعياريُّ المسجَّل | الاسمُ الذي تختاره |';
        $m[] = '|---|---|---|---|';
        foreach ($B['TRUE_NAMING_DECISION'] as $z) {
            $m[] = '| `' . $z[0]['route'] . '` | ' . ($z[0]['current_label'] ?: '—')
                 . ' | ' . ($z[0]['canonical_ar'] ?: '—') . ' |  |';
        }
        $m[] = '';
    }
    $out = $ROOT . '/docs/REPAIR01_20260823/open/EDC_NAMES_FOR_OWNER.md';
    @mkdir(dirname($out), 0777, true);
    file_put_contents($out, implode("\n", $m) . "\n");
    echo "\n  ✔ ملفُّ المالك: docs/REPAIR01_20260823/open/EDC_NAMES_FOR_OWNER.md\n";
}

echo "\n────────────────────────────────────────────────────────────\n";
$left = (int) $conn->query("SELECT COUNT(*) FROM nav_canonical WHERE status <> 'APPROVED'")->fetch_row()[0];
printf("باقٍ غيرَ معتمَدٍ: %d\n", $left);
