<?php
/**
 * tools/rpr02_field_measure.php — `RPR-02` §٧ الخطوة ٥ · **حقولُ المبنيِّ مقيسةً**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الحاجزُ الذي يرفعه** — لوحةُ §١٢ المقياس **#٣** كانت محجوبةً بنصِّها:
 *   *«الجانبُ المبنيُّ `gov_field_class` لا يغطّي إلّا ٤٤ سطحًا من ٦٢١ ومفتاحُه
 *   سبيكةٌ لا مسار ⇒ blocked at stage: **قياسُ حقولِ المبنيِّ من الأثرِ نفسِه**»*.
 *   ⛔ **فالعلاجُ ليس توسيعَ `gov_field_class`** — تلك مصنِّفُ حساسيّةٍ حوكميٌّ
 *   لا دفترُ حقولٍ مبنيّ — بل **قياسُ الحقلِ من الأثرِ كما قيست الحبّة** (§٧ ①).
 *
 * ◆ **والمفتاحان مسمَّيان صراحةً** (‏شرطُ `CONTINUE` §٢ الأوّل):
 *   · **التصميميُّ**: `repair01_fields.requirement_id` — ٧٥٩١ حقلًا على ٤٣٣ متطلبًا.
 *   · **المبنيُّ**: `repair01_screen_registry.screen_id` ⇐ أثرُ السطحِ على القرص.
 *   · **والجسرُ بينهما مُعلَنٌ قائمٌ لا مخترَع**: `repair01_target_universe`
 *     تحمل `requirement_id` و`screen_id` معًا **بحكمٍ وشاهدٍ لكلِّ صف**.
 *     ⇒ فلا جدولَ مصالحةٍ جديدًا يُخترع، **والمعرِّفُ القانونيُّ الواحدُ موجود**.
 *     ⛔ **ولا يُقاس إلّا على `MATCHED`** — فسطحٌ لم يُطابَق لا ملفَّ له يُقاس عليه.
 *
 * ◆ **ما يُنتزع من الأثر** — خمسةُ روافدَ لا رافدٌ واحد، ولكلٍّ وجهُه:
 *   **F1 · `<label>`** — أقوى الشواهدِ: نصٌّ عربيٌّ مقرونٌ بحقلِ إدخالٍ صراحةً.
 *   **F2 · `<th>`** — رأسُ عمودٍ في جدولِ عرض: الحقلُ **معروضٌ** وإن لم يُدخَل.
 *   **F3 · `name=`** — مفتاحُ الحقلِ الإنجليزيُّ: يلتقط ما سمَّاه التصميمُ
 *        بقوسٍ لاتينيٍّ («القيمة القديمة (old_value)») وما لا وسمَ عربيَّ له.
 *   **F4 · `gov_field_class`** — ⛔ **والسطحُ المولَّدُ لا يحمل وسمًا واحدًا**:
 *        شاشاتُ `u13` تُصيَّر كلَّها من `includes/u13_screen_kit.php`، ورأسُ كلِّ
 *        ملفٍّ منها يقول بنصِّه *«الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا
 *        صنفٍ لا يُصيَّر أصلًا»*. ⇒ فـ`gov_field_class` **دفترُ حقولٍ مبنيٍّ
 *        حقيقيٌّ لهذه الأسطحِ وحدَها** — وهذا سببُ تغطيتِها ٤٤ سطحًا لا عجزًا فيها.
 *        **والجسرُ إلى معرِّفِ الشاشةِ منتزَعٌ من الأثرِ نفسِه**: `$U13['screen']`
 *        سبيكةٌ مصرَّحةٌ في ملفِّ السطح ⇒ `screen_code` ⇒ `screen_id`.
 *        ⛔ **ولا يُخمَّن الجسرُ باسمِ الملفّ** — يُقرأ من التصريح.
 *   **F5 · حقولُ الفعلِ في عقدِ `u13`** — `'fields' => array('k' => 'وسم')`
 *        داخلَ ملفِّ السطحِ نفسِه: حقولُ إدخالِ الفعلِ لا تمرُّ بـ`gov_field_class`.
 *
 * ◆ **والعُدّةُ المشتركةُ تُقصى** — درسُ `rpr02_grain_measure.php` نفسُه:
 *   ملفٌّ يبلغه سطحان فأكثرُ **عُدّةٌ مشتركةٌ لا حقلُ سطح**، ولو نُسبت حقولُه
 *   لكلِّ من يشتمله لَظهرت تغطيةٌ كاذبةٌ بالحقولِ نفسِها في عشراتِ الأسطح.
 *   ⛔ **لكنَّ إقصاءَ العُدّةِ لا يجوز أن يُصفِّر سطحًا تُصيَّر حقولُه منها** —
 *   ودرسُ «العُدّةُ المشتركة» بنصِّه: *«الكيانُ من الخاصِّ ثمَّ المشترك، والخرقُ
 *   من الخاصِّ وحدَه»*. فالإقصاءُ يمنع **نسبةَ حقولِ العُدّةِ العامّةِ** للسطح،
 *   و`F4`/`F5` تردّان **حقولَ السطحِ الخاصّةَ به** المصرَّحةَ باسمِه.
 *
 * ◆ **والمطابقةُ بالعربيّةِ لا بالإنجليزيّة** — فاسمُ الحقلِ التصميميُّ عربيٌّ
 *   واسمُ الإدخالِ المبنيِّ إنجليزيّ، **ولا قاموسَ بينهما**. فتُقاس المطابقةُ
 *   على النصِّ المعروضِ بمعيارِ الهويّةِ نفسِه المستعمَلِ في §٤·٢: **ثلثا
 *   مفرداتِ الاسمِ فأكثرُ** — ⛔ **ولا يُخترع معيارٌ ثانٍ لنفسِ السؤال**.
 *
 * ◆ **وأنواعُ الحقولِ لا تُطالَب بعقدٍ واحد** (`AMD-01` §٤·٤ بروحِه):
 *   `AUDIT` **إلحاقيّةٌ بنصِّ §٧ الخطوة ١١** («المُنشئ والوقت والحالة والمرجع
 *   — إلحاقية») ⇒ **تُعرض منفصلةً ولا تدخل مقامَ المطابقة**. وما عداها يدخل.
 *   ⛔ **وإخراجُها يُعلَن بعددِه** — فمن يملك إخراجَ حقلٍ من المقامِ يملك رفعَ
 *   النسبةِ بلا بناء.
 *
 * ⛔ **وما خلا أثرُه من مفردةٍ واحدةٍ يبقى `NO_VOCAB` بشاهدِ عجزِه** ولا يُملأ
 *   بتخمين — وصفرُ المقيسِ **حقيقةٌ تُعرض** لا فراغٌ يُسدّ.
 *
 * التشغيل:
 *   php tools/rpr02_field_measure.php [--apply] [--md] [--list] [--selftest]
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
$LIST  = in_array('--list', $argv, true);
$SELF  = in_array('--selftest', $argv, true);

$CHROME = array('config.php','session_bootstrap.php','inheader.php','insidebar.php',
                'page_header.php','permissions_helper.php','gov_columns.php',
                'screen_contract.php','env.php','db.php','footer.php','infooter.php',
                'csrf.php','csrf_helper.php','auth.php','ems_action_guard.php');

/* ═══ ① التطبيعُ ومعيارُ الهويّة — **المعيارُ نفسُه لا معيارٌ ثانٍ** ═════════ */
function fm_norm($s)
{
    $s = (string) $s;
    $s = preg_replace('~[\x{0640}\x{064B}-\x{0652}]~u', '', $s);
    $s = str_replace(array('أ','إ','آ','ى','ة','ؤ','ئ'), array('ا','ا','ا','ي','ه','و','ي'), $s);
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('~[^\p{Arabic}\p{L}\p{N}]+~u', ' ', $s);
    return trim(preg_replace('~\s+~u', ' ', $s));
}
/* ⛔ **والمفرداتُ الشائعةُ تُنزع** — «في» و«من» و«على» ترد في مئاتِ الحقولِ
   فتُنتج مطابقةً بلا معنًى، **وهي الحيلةُ نفسُها التي مُنعت في §٤·٢**. */
$FM_STOP = array('و','في','من','على','عن','ال','مع','او','الي','the','and','for','id');
function fm_tok($s, $STOP)
{
    $out = array();
    foreach (explode(' ', fm_norm($s)) as $w) {
        $w = trim($w);
        if ($w === '' || mb_strlen($w) < 3 || in_array($w, $STOP, true)) { continue; }
        $out[$w] = 1;
    }
    return array_keys($out);
}
/* **الهويّةُ بثلثَي المفردات** — معيارُ §٤·٢ نفسُه، منقولًا لا مُبتكَرًا */
function fm_hit($dTok, $bagTok, $bagStr, $dNorm)
{
    if ($dNorm !== '' && isset($bagStr[$dNorm])) { return 'EXACT'; }
    if (!$dTok) { return ''; }
    if ($dNorm !== '') {
        foreach ($bagStr as $b => $_) {
            if ($b !== '' && mb_strpos($b, $dNorm) !== false) { return 'CONTAINED'; }
        }
    }
    $sh = 0;
    foreach ($dTok as $t) { if (isset($bagTok[$t])) { $sh++; } }
    return ($sh / count($dTok) >= 2 / 3) ? 'COVER' : '';
}

/* ═══ ② الانتزاعُ من الأثر ═══════════════════════════════════════════════ */
function fm_extract($src)
{
    $lab = array(); $th = array(); $nm = array();
    if (preg_match_all('~<label[^>]*>(.*?)</label>~is', $src, $m)) {
        foreach ($m[1] as $t) { $t = trim(strip_tags($t)); if ($t !== '') { $lab[] = $t; } }
    }
    if (preg_match_all('~<th[^>]*>(.*?)</th>~is', $src, $m)) {
        foreach ($m[1] as $t) { $t = trim(strip_tags($t)); if ($t !== '') { $th[] = $t; } }
    }
    /* [\x22\x27] = علامتا الاقتباس — بالرمزِ الست عشريِّ كي لا تُقطع السلسلة */
    if (preg_match_all('~<(?:input|select|textarea)[^>]*\bname\s*=\s*[\x22\x27]([^\x22\x27]+)[\x22\x27]~i', $src, $m)) {
        foreach ($m[1] as $t) { $t = trim($t); if ($t !== '') { $nm[] = $t; } }
    }
    /* ◆ **F6 · وسمٌ يراه المستخدمُ ولا يقع في `<label>`** — وقد كان يضيع:
         هذه الشجرةُ تسمّي كثيرًا من حقولِها بـ`aria-label` (‏لقارئِ الشاشة)
         وبـ`placeholder` (‏داخلَ الخانة) بلا `<label>` منفصل. **وهما وسمانِ
         مُصيَّرانِ للمستخدمِ لا تعليقانِ للمبرمج** ⇒ فإقصاؤهما يقيس الشكلَ لا
         الحقل، **ويُنتج نقصًا كاذبًا في سطحٍ حقلُه مبنيٌّ ومُسمًّى**.
       ⛔ **ولا يُوسَّع الادّعاءُ بتوسيعِ الشاهد**: المطابقةُ تبقى كما هي
         (‏هويّةٌ · احتواءٌ · ثلثا المفردات) — والمُوسَّعُ **مادّةُ الأثرِ** لا
         قاعدةُ القبول. */
    if (preg_match_all('~\b(?:aria-label|placeholder)\s*=\s*[\x22\x27]([^\x22\x27]+)[\x22\x27]~i', $src, $m)) {
        foreach ($m[1] as $t) { $t = trim(strip_tags($t)); if ($t !== '') { $lab[] = $t; } }
    }
    return array('F1' => $lab, 'F2' => $th, 'F3' => $nm);
}
/* **سبيكةُ `u13` مصرَّحةً في ملفِّ السطح** — الجسرُ إلى `gov_field_class`.
   ⛔ ولا تُشتقّ من اسمِ الملفّ: `Audit/iaf_charter.php` قد يعلن `iaf_charter`
   وقد يعلن غيرَه، **والتصريحُ أصدقُ من الشكل**. */
function fm_u13_slug($src)
{
    if (strpos($src, 'u13_screen_kit.php') === false) { return ''; }
    if (preg_match('~[\x27\x22]screen[\x27\x22]\s*=>\s*[\x27\x22]([a-z0-9_]+)[\x27\x22]~i', $src, $m)) {
        return $m[1];
    }
    return '';
}
/* **حقولُ الفعلِ في عقدِ `u13`** — `'fields' => array('k' => 'وسم')` */
function fm_u13_action_fields($src)
{
    $out = array();
    if (preg_match_all('~[\x27\x22]fields[\x27\x22]\s*=>\s*array\s*\((.*?)\)~s', $src, $mm)) {
        foreach ($mm[1] as $blk) {
            if (preg_match_all('~=>\s*[\x27\x22]([^\x27\x22]+)[\x27\x22]~', $blk, $m2)) {
                foreach ($m2[1] as $v) { $v = trim($v); if ($v !== '') { $out[] = $v; } }
            }
        }
    }
    return $out;
}
/* **مسارٌ مُحوِّلٌ لا شاشة** — `route_redirect` يعني أنَّ الأثرَ ليس هنا */
function fm_is_redirector($src)
{
    return (strpos($src, 'route_redirect') !== false && mb_strlen($src) < 4000);
}
function fm_includes($rp, $ROOT, $CHROME)
{
    $out = array();
    $src = (string) @file_get_contents($rp);
    if ($src === '') { return $out; }
    if (preg_match_all('~(?:include|require)(?:_once)?\s*\(?\s*[^;]*?[\x22\x27]([^\x22\x27]+\.php)[\x22\x27]~', $src, $m)) {
        foreach ($m[1] as $inc) {
            if (in_array(basename($inc), $CHROME, true)) { continue; }
            $cand = array(dirname($rp) . '/' . $inc, $ROOT . '/' . ltrim($inc, './'),
                          $ROOT . '/includes/' . basename($inc));
            foreach ($cand as $cp) {
                if (is_file($cp)) { $rc = realpath($cp); if ($rc) { $out[$rc] = 1; } break; }
            }
        }
    }
    return array_keys($out);
}

/* ═══ ③ الاختبارُ السالبُ — بمفردةٍ فريدةٍ تكسر التمييزَ وحدَه ═══════════ */
if ($SELF) {
    $fail = 0;
    $x = fm_extract('<label for="a">اسمُ العميلِ الكامل</label><th>تاريخ القيد</th>'
                  . '<input name="zzq_unique_probe_field">');
    if (!in_array('اسمُ العميلِ الكامل', $x['F1'], true)) { echo "  X F1 لم يُنتزَع\n"; $fail++; }
    if (!in_array('تاريخ القيد', $x['F2'], true))        { echo "  X F2 لم يُنتزَع\n"; $fail++; }
    if (!in_array('zzq_unique_probe_field', $x['F3'], true)) { echo "  X F3 لم يُنتزَع\n"; $fail++; }
    $y = fm_extract('<p>اسمُ العميلِ الكامل</p>');
    if ($y['F1'] || $y['F2'] || $y['F3']) { echo "  X نصٌّ حرٌّ عُدَّ حقلًا\n"; $fail++; }
    /* **التمييزُ يُختبر بطرفَيه**: يُصيب المطابقَ ويردُّ غيرَه */
    $bagS = array(fm_norm('اسم العميل الكامل') => 1); $bagT = array();
    foreach (fm_tok('اسم العميل الكامل', $FM_STOP) as $t) { $bagT[$t] = 1; }
    if (fm_hit(fm_tok('اسم العميل', $FM_STOP), $bagT, $bagS, fm_norm('اسم العميل')) === '') {
        echo "  X المطابقُ لم يُصَب\n"; $fail++;
    }
    if (fm_hit(fm_tok('مبلغ الضريبة المضافة', $FM_STOP), $bagT, $bagS,
               fm_norm('مبلغ الضريبة المضافة')) !== '') { echo "  X غيرُ المطابقِ أُقرَّ\n"; $fail++; }
    /* **الكاسر**: مفردةٌ لا ترد إلّا هنا — فلو مرَّت كان الفحصُ أخضرَ كاذبًا */
    if (fm_hit(fm_tok('zzqfrid probe', $FM_STOP), $bagT, $bagS, 'zzqfrid probe') !== '') {
        echo "  X المفردةُ الفريدةُ طوبقت زورًا\n"; $fail++;
    }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n"
               : "\n🟢 الفحصُ الذاتيُّ تامٌّ — والانتزاعُ يميّز والمطابقةُ تردُّ غيرَ المطابق\n";
    exit($fail ? 1 : 0);
}

/* ═══ ④ نافذةُ القياس ════════════════════════════════════════════════════ */
$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
if (!$snap && $APPLY) { exit("⛔ **لا نافذةَ قياسٍ مفتوحة** — جمِّدْ أوّلًا.\n"); }
$sid = $snap ? $snap['snapshot_id'] : 'DRY';

/* ═══ ⑤ الجسرُ المُعلَنُ: المتطلبُ ⇄ الشاشة ═══════════════════════════════ */
$bridge = array();
$r = $conn->query("SELECT tu.target_uid, tu.requirement_id, tu.screen_id, tu.unit, tu.name_ar
                     FROM repair01_target_universe tu
                    WHERE tu.verdict = 'MATCHED' AND tu.screen_id <> '' AND tu.requirement_id <> ''
                    ORDER BY tu.screen_id");
while ($x = $r->fetch_assoc()) { $bridge[] = $x; }

/* ═══ ⑤·ب دفترُ `gov_field_class` — الجانبُ المبنيُّ للأسطحِ المولَّدة ═══ */
$GFC = array();
$r = @$conn->query("SELECT screen_code, field_key, label_ar FROM gov_field_class WHERE active = 1");
if ($r) { while ($x = $r->fetch_assoc()) { $GFC[$x['screen_code']][] = $x; } }

/* حقولُ التصميمِ لكلِّ متطلب */
$des = array();
$r = $conn->query("SELECT requirement_id, field_name, field_type FROM repair01_fields
                    ORDER BY requirement_id, id");
while ($x = $r->fetch_assoc()) { $des[$x['requirement_id']][] = $x; }

/* مسارُ كلِّ سطح */
$reg = array();
$r = $conn->query("SELECT screen_id, screen_file, route, canonical_label_ar, owner_code
                     FROM repair01_screen_registry
                    WHERE on_disk = 1 AND ownership_verdict <> 'RETIRE'");
while ($x = $r->fetch_assoc()) { $reg[$x['screen_id']] = $x; }
$PATH = array();
foreach ($reg as $sidk => $s) {
    $p = '';
    if (trim((string) $s['route']) !== '') {
        $q = $ROOT . '/' . ltrim(strtr((string) $s['route'], array(chr(92) => '/')), '/');
        if (is_file($q)) { $p = $q; }
    }
    if ($p === '') {
        $h = glob($ROOT . '/*/' . basename($s['screen_file']));
        if (!$h) { $h = glob($ROOT . '/' . basename($s['screen_file'])); }
        if ($h) { $p = $h[0]; }
    }
    $PATH[$sidk] = $p;
}

/* ═══ ⑥ العُدَدُ المشتركةُ تُقصى — مرورٌ أوّلٌ على الكونِ المبنيِّ كلِّه ═══ */
$fanin = array(); $own = array();
foreach ($PATH as $sidk => $p) {
    if ($p === '') { continue; }
    $rp = realpath($p); if (!$rp) { continue; }
    $own[$rp] = 1;
    $q = array(array($rp, 0)); $vis = array($rp => 1); $reach = array();
    while ($q) {
        list($cur, $d) = array_shift($q);
        if ($d >= 3) { continue; }
        foreach (fm_includes($cur, $ROOT, $CHROME) as $nx) {
            if (isset($vis[$nx])) { continue; }
            $vis[$nx] = 1; $reach[$nx] = 1; $q[] = array($nx, $d + 1);
        }
    }
    foreach (array_keys($reach) as $f) { $fanin[$f] = isset($fanin[$f]) ? $fanin[$f] + 1 : 1; }
}
$SHARED = array();
foreach ($fanin as $f => $n) { if ($n >= 2 && !isset($own[$f])) { $SHARED[$f] = $n; } }

/* ═══ ⑦ القياسُ سطحًا سطحًا ══════════════════════════════════════════════ */
$out = array(); $BYTYPE = array();
$tot = array('des' => 0, 'audit' => 0, 'appl' => 0, 'hit' => 0,
             'F1' => 0, 'F2' => 0, 'F3' => 0, 'F4' => 0, 'F5' => 0,
             'full' => 0, 'novocab' => 0, 'redir' => 0, 'u13' => 0);
foreach ($bridge as $b) {
    $sc = $b['screen_id'];
    $p  = isset($PATH[$sc]) ? $PATH[$sc] : '';
    $bagStr = array(); $bagTok = array(); $vocab = 0; $files = array();
    $slug = ''; $redir = false; $nGfc = 0; $nAct = 0; $kitFallback = 0;
    /* **الرافدُ الخاصُّ أوّلًا**: عقدُ السطحِ نفسِه — سبيكتُه وحقولُ أفعالِه.
       ⛔ ويُقرأ من **ملفِّ السطحِ وحدَه** لا من مداه، فالعقدُ خاصٌّ لا مشترك. */
    if ($p !== '') {
        $ownSrc = (string) @file_get_contents($p);
        $redir  = fm_is_redirector($ownSrc);
        $slug   = fm_u13_slug($ownSrc);
        $addBag = function ($v) use (&$bagStr, &$bagTok, &$vocab, $FM_STOP) {
            $vocab++;
            $nv = fm_norm($v);
            if ($nv !== '') { $bagStr[$nv] = 1; }
            foreach (fm_tok($v, $FM_STOP) as $t) { $bagTok[$t] = 1; }
        };
        if ($slug !== '' && isset($GFC[$slug])) {
            foreach ($GFC[$slug] as $g) {
                $nGfc++; $tot['F4']++;
                $addBag($g['label_ar']); $addBag($g['field_key']);
            }
        }
        foreach (fm_u13_action_fields($ownSrc) as $v) { $nAct++; $tot['F5']++; $addBag($v); }
    }
    if ($p !== '') {
        $rp = realpath($p);
        $q = array(array($rp, 0)); $vis = array($rp => 1); $set = array($rp);
        while ($q) {
            list($cur, $d) = array_shift($q);
            if ($d >= 3) { continue; }
            foreach (fm_includes($cur, $ROOT, $CHROME) as $nx) {
                if (isset($vis[$nx]) || isset($SHARED[$nx])) { continue; }
                $vis[$nx] = 1; $set[] = $nx; $q[] = array($nx, $d + 1);
            }
        }
        $harvest = function ($files0) use (&$files, &$tot, &$vocab, &$bagStr, &$bagTok, $FM_STOP) {
            foreach ($files0 as $f) {
                $src = (string) @file_get_contents($f);
                if ($src === '') { continue; }
                $files[] = basename($f);
                $x = fm_extract($src);
                foreach (array('F1','F2','F3') as $k) {
                    $tot[$k] += count($x[$k]);
                    foreach ($x[$k] as $v) {
                        $vocab++;
                        $nv = fm_norm($v);
                        if ($nv !== '') { $bagStr[$nv] = 1; }
                        foreach (fm_tok($v, $FM_STOP) as $t) { $bagTok[$t] = 1; }
                    }
                }
            }
        };
        $harvest($set);
        /* ══ **الرافدُ الاحتياطيُّ — للعاجزِ وحدَه** (RPR-02 §٧·٥ · 2026-08-30) ══
           ◆ **العطبُ المقيس**: خمسةُ أسطحٍ خرجت `NO_VOCAB` — **صفرُ مفردةٍ في
             أثرِها كلِّه** — وشاهدُها نفسُه يقول «عجزُ قياسٍ مُعلَنٌ لا صفرُ حقول».
             وسببُها واحد: تصييرُها كلُّه في **عُدَّةٍ مشتركة**
             (`Clients/client_contacts.php` ⇐ `includes/party_contacts_view.php`،
             وتُصرِّح بذلك في ترويستِها: «المنطقُ والتصييرُ في عُدَّةٍ مشتركةٍ مع
             سطحِ المورد»). **والعُدَّةُ مُقصاةٌ فيبقى السطحُ بلا مفردةٍ فيُقرأ
             صفرًا** — ⛔ **وصفرٌ من مفردةٍ لا وجودَ لها أخضرُ كاذبٌ مقلوب**
             ([[measure-token-must-exist]]).
           ◆ **والإقصاءُ نفسُه صحيحٌ في محلِّه**: عُدَّةٌ يشتملها سطحان **لا تُنشئ
             ملكيّةً ولا حبّة** ([[grain-measure-shared-kit-trap]]). **لكنَّ
             الحقلَ ليس الملكيّة**: حقلٌ تُصيِّره العُدَّةُ **يراه المستخدمُ على
             هذا السطحِ فعلًا** — فإقصاؤه يقيس الشكلَ لا المبنيّ.
           ⛔ **ولا يُوسَّع الرافدُ إلى القادرِ**: يُقرأ **فقط** حين خلا أثرُ
             السطحِ من كلِّ مفردة — فلا تُنتفخ مطابقةُ سطحٍ له مفرداتُه، ولا
             تُوزَّع مفرداتُ عُدَّةٍ على مئةِ سطحٍ يشتملونها.
           ⛔ **والقاعدةُ لا تُمَسّ**: المُوسَّعُ **مادّةُ الأثرِ** لا قاعدةُ القبول. */
        if ($vocab === 0) {
            $kits = array();
            foreach ($set as $f) {
                foreach (fm_includes($f, $ROOT, $CHROME) as $nx) {
                    if (isset($SHARED[$nx])) { $kits[$nx] = 1; }
                }
            }
            if ($kits) {
                $harvest(array_keys($kits));
                $kitFallback = count($kits);
            }
        }
    }
    $dl = isset($des[$b['requirement_id']]) ? $des[$b['requirement_id']] : array();
    $nAud = 0; $nApp = 0; $nHit = 0; $miss = array();
    foreach ($dl as $f) {
        $tot['des']++;
        /* ⛔ **والنوعُ يُعدُّ ولو خرج من المقام** — فمن يملك إخراجَ نوعٍ يملك رفعَ
           النسبة، **والتفكيكُ يكشف ما يُخفيه الجمع**: ستُّ خطواتٍ في §٧ تسأل
           عن ستّةِ أنواعٍ، ونسبةٌ واحدةٌ لها **تُخفي أيَّها يحتاج عملًا**. */
        $ft = (string) $f['field_type'];
        if (!isset($BYTYPE[$ft])) { $BYTYPE[$ft] = array('app' => 0, 'hit' => 0); }
        if ($f['field_type'] === 'AUDIT') { $nAud++; $tot['audit']++; $BYTYPE[$ft]['app']++; continue; }
        $nApp++; $BYTYPE[$ft]['app']++;
        $h = fm_hit(fm_tok($f['field_name'], $FM_STOP), $bagTok, $bagStr, fm_norm($f['field_name']));
        if ($h !== '') { $nHit++; $BYTYPE[$ft]['hit']++; } else { $miss[] = $f['field_name']; }
    }
    if ($slug !== '' && $nGfc > 0) { $tot['u13']++; }
    if ($redir) { $tot['redir']++; }
    $tot['appl'] += $nApp; $tot['hit'] += $nHit;
    if ($vocab === 0) { $tot['novocab']++; }
    if ($nApp > 0 && $nHit === $nApp) { $tot['full']++; }
    $out[] = array('screen_id' => $sc, 'target' => $b['target_uid'], 'req' => $b['requirement_id'],
                   'name' => isset($reg[$sc]) ? $reg[$sc]['canonical_label_ar'] : $b['name_ar'],
                   'unit' => $b['unit'], 'vocab' => $vocab, 'files' => count($files),
                   'audit' => $nAud, 'appl' => $nApp, 'hit' => $nHit, 'miss' => $miss,
                   'slug' => $slug, 'redir' => $redir, 'gfc' => $nGfc, 'act' => $nAct,
                   'path' => $p === '' ? '' : str_replace($ROOT . '/', '', $p));
}

/* ═══ ⑧ العرض ════════════════════════════════════════════════════════════ */
$pc = $tot['appl'] ? round($tot['hit'] * 100 / $tot['appl'], 1) : 0;
echo "\n═══ `RPR-02` §٧ الخطوة ٥ — حقولُ المبنيِّ مقيسةً من الأثر ═══\n";
printf("  اللقطة: %s · أسطحٌ مطابَقةٌ في الجسرِ المُعلَن: **%d**\n\n", $sid, count($out));
echo "  ── الجسرُ ──\n";
echo "     التصميميُّ `repair01_fields.requirement_id` · المبنيُّ `screen_id` · والجسرُ `repair01_target_universe` (`MATCHED`)\n";
printf("     ⇒ **صفرُ سطحٍ مطابَقٍ بلا طرفَين** — والمقامُ %d سطحًا لا 44\n\n", count($out));
echo "  ── مفرداتُ الأثرِ المنتزَعة ──\n";
printf("     F1 `<label>` %5d · F2 `<th>` %5d · F3 `name=` %5d\n", $tot['F1'], $tot['F2'], $tot['F3']);
printf("     F4 `gov_field_class` %5d (على %d سطحٍ مولَّدٍ بسبيكتِه المصرَّحة) · F5 حقولُ الفعل %4d\n",
       $tot['F4'], $tot['u13'], $tot['F5']);
printf("     عُدَدٌ مشتركةٌ أُقصيت %d ملفًّا · ومسارٌ مُحوِّلٌ لا شاشةَ فيه %d\n\n", count($SHARED), $tot['redir']);
echo "  ── المطابقة ──\n";
printf("     حقولٌ تصميميّةٌ على المطابَقين          %5d\n", $tot['des']);
printf("     منها `AUDIT` **إلحاقيّةٌ بنصِّ §٧ ١١**   %5d — تُعرض ولا تدخل المقام\n", $tot['audit']);
printf("     المقامُ المنطبق                        %5d\n", $tot['appl']);
printf("     **المطابَقُ في الأثر                   %5d ⇒ %s%%**\n", $tot['hit'], $pc);
printf("     أسطحٌ طوبقت حقولُها كاملةً              %5d من %d\n", $tot['full'], count($out));
printf("     أسطحٌ خلا أثرُها من مفردةٍ (`NO_VOCAB`)  %5d — بشاهدِ عجزِها لا بصفرٍ مسكوتٍ عنه\n", $tot['novocab']);

/* ⛔ **ستُّ خطواتٍ لا خطوةٌ واحدة** — و§٧ تسأل عن كلِّ نوعٍ في موضعِه، ونسبةٌ
 واحدةٌ لستَّةِ أسئلةٍ **تُخفي أيَّها يحتاج عملًا**. */
$FM_STEP = array(
    'BUSINESS_INPUT'    => 'الخطوة ٥ · الحقولُ الناقصة',
    'IMPORTED_READONLY' => 'الخطوة ٦ · الحقولُ المستوردة',
    'DERIVED'           => 'الخطوة ٧ · الحقولُ المشتقّة',
    'REFERENCE'         => 'الخطوة ٥ · مرجعٌ في نموذج',
    'FK_INHERITED'      => 'الخطوة ٢ · الأبُ والابن',
    'PARENT_INHERITED'  => 'الخطوة ٢ · الأبُ والابن',
    'PK_GENERATED'      => 'يولّده النظامُ ولا يُحرَّر — بنصِّ الملفّ',
    'AUDIT'             => 'الخطوة ١١ · إلحاقيّة — خارجَ المقام',
    'SNAPSHOT'          => 'لقطةُ قيمةٍ محفوظة',
);
echo "
  ── والمقامُ مفكَّكًا: ستُّ خطواتٍ لا خطوةٌ واحدة ──
";
uasort($BYTYPE, function ($a, $b) { return $b['app'] - $a['app']; });
foreach ($BYTYPE as $ft => $v) {
    printf("     %-20s %5d ⇒ %5d · %5s%%  %s
", $ft, $v['app'], $v['hit'],
           $v['app'] ? round($v['hit'] * 100 / $v['app'], 1) : 0,
           isset($FM_STEP[$ft]) ? $FM_STEP[$ft] : '—');
}
echo "  ⛔ **ولا يُخرَج نوعٌ من المقامِ هنا** — الإخراجُ قرارُ عرضٍ بنصٍّ يُذكر لا حذفٌ في المخزن
";

if ($LIST) {
    echo "\n  ── أدنى عشرةِ أسطحٍ تغطيةً ──\n";
    $srt = $out;
    usort($srt, function ($a, $b) {
        $x = $a['appl'] ? $a['hit'] / $a['appl'] : 0; $y = $b['appl'] ? $b['hit'] / $b['appl'] : 0;
        return $x == $y ? 0 : ($x < $y ? -1 : 1);
    });
    foreach (array_slice($srt, 0, 10) as $o) {
        printf("   %-10s %-30s %2d/%-3d مفرداتُ أثرٍ %4d · ناقص: %s\n", $o['screen_id'],
               mb_substr($o['name'], 0, 28), $o['hit'], $o['appl'], $o['vocab'],
               mb_substr(implode(' · ', array_slice($o['miss'], 0, 3)), 0, 70));
    }
}

/* ═══ ⑨ التثبيت ══════════════════════════════════════════════════════════ */
if ($APPLY) {
    /* ⛔ **والعُدّةُ لا تُنشئ جدولًا** — `ems_app` بلا `CREATE` **بحقٍّ لا بعطب**،
       والمخطَّطُ يتغيّر بهجرةٍ مسجَّلةٍ في الدفتر. وجدولٌ يُولد من أداةِ قياسٍ
       **يُرسِب المقياسَ #١٢** (`UNRECONCILED_MIGRATIONS = 0`) بمولودٍ خارجَ الدفتر. */
    $has = $conn->query("SHOW TABLES LIKE 'repair01_field_measure'");
    if (!$has || !$has->num_rows) {
        exit("⛔ **`repair01_field_measure` غيرُ موجود** — والعُدّةُ لا تُنشئ مخطَّطًا.\n"
           . "   شغِّلْ: php database/migrations/2028_01_01_rpr02_field_measure.php\n");
    }
    $conn->query("DELETE FROM repair01_field_measure");
    $n = 0;
    foreach ($out as $o) {
        $wit = $o['vocab'] === 0
            ? ($o['redir']
               ? 'REDIRECTOR · الأثرُ `' . $o['path'] . '` **مسارٌ مُحوِّلٌ لا شاشة** (`route_redirect`) '
                 . '⇒ حقولُ السطحِ في وجهتِه لا فيه — **والسجلُّ يشير إلى المُحوِّلِ لا إلى الوجهة** · لقطة ' . $sid
               : 'NO_VOCAB · الأثرُ `' . $o['path'] . '` لا يحمل `<label>` ولا `<th>` ولا `name=` ولا سبيكةَ `u13` '
                 . '⇒ الحقولُ مُصيَّرةٌ من جافاسكربت — **عجزُ قياسٍ مُعلَنٌ لا صفرُ حقول**. '
                 . '◆ **وعُدَّتُه المشتركةُ قُرئت احتياطًا** (‏الرافدُ الاحتياطيُّ للعاجزِ وحدَه) **فلم تُنتج مفردةً** · لقطة ' . $sid)
            : 'قيست من الأثرِ `' . $o['path'] . '` عبرَ ' . $o['files'] . ' ملفًّا · مفرداتٌ '
              . $o['vocab'] . ' · طوبق ' . $o['hit'] . ' من ' . $o['appl']
              . ' حقلٍ منطبقٍ (و`AUDIT` ' . $o['audit'] . ' خارجَ المقامِ بنصِّ §٧ ١١) · لقطة ' . $sid;
        $ok = $conn->query("INSERT INTO repair01_field_measure
              (screen_id,target_uid,requirement_id,unit,artifact_path,vocab_terms,
               design_total,design_audit,design_applicable,matched,missing_sample,witness,
               snapshot_id,measured_at)
            VALUES ('" . $e($o['screen_id']) . "','" . $e($o['target']) . "','" . $e($o['req']) . "','"
             . $e($o['unit']) . "','" . $e($o['path']) . "'," . (int) $o['vocab'] . ","
             . (int) ($o['audit'] + $o['appl']) . "," . (int) $o['audit'] . "," . (int) $o['appl'] . ","
             . (int) $o['hit'] . ",'" . $e(mb_substr(implode(' · ', array_slice($o['miss'], 0, 8)), 0, 500))
             . "','" . $e(mb_substr($wit, 0, 500)) . "','" . $e($sid) . "',NOW())");
        if (!$ok) { exit("✘ تعذّر تثبيتُ {$o['screen_id']}: {$conn->error}\n"); }
        $n++;
    }
    $hasT = $conn->query("SHOW TABLES LIKE 'repair01_field_measure_type'");
    if ($hasT && $hasT->num_rows) {
        $conn->query("DELETE FROM repair01_field_measure_type");
        foreach ($BYTYPE as $ft => $v) {
            $stp = isset($FM_STEP[$ft]) ? $FM_STEP[$ft] : '';
            $w = 'مقيسٌ من الأثرِ على الأسطحِ المطابَقةِ — ' . $v['hit'] . ' من ' . $v['app']
               . ' · وسؤالُه في §٧: ' . ($stp === '' ? 'غيرُ مسمًّى' : $stp) . ' · لقطة ' . $sid;
            $conn->query("INSERT INTO repair01_field_measure_type
                  (field_type,step_ref,applicable,matched,witness,snapshot_id,measured_at)
                VALUES ('" . $e($ft) . "','" . $e($stp) . "'," . (int) $v['app'] . ","
                 . (int) $v['hit'] . ",'" . $e(mb_substr($w, 0, 400)) . "','" . $e($sid) . "',NOW())");
        }
        printf("  ✔ فُكِّك المقامُ إلى **%d** نوعًا في `repair01_field_measure_type`
", count($BYTYPE));
    }
    $bad = (int) $conn->query("SELECT COUNT(*) FROM repair01_field_measure WHERE witness = ''")->fetch_row()[0];
    printf("\n  ✔ ثُبِّت **%d** سطحًا في `repair01_field_measure` · صفٌّ بلا شاهدٍ %d\n", $n, $bad);
}

if ($MD) {
    $o  = "# `RPR-02` §٧ الخطوة ٥ — حقولُ المبنيِّ مقيسةً من الأثر\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `" . $sid . "`\n\n";
    $o .= "## الحاجزُ الذي ارتفع\n\n";
    $o .= "لوحةُ §١٢ كانت تقول للمقياس **#٣**: *«`gov_field_class` لا يغطّي إلّا ٤٤ سطحًا من ٦٢١\n";
    $o .= "ومفتاحُه سبيكةٌ لا مسار»*. **والعلاجُ لم يكن توسيعَ ذلك الجدول** — فهو مصنِّفُ حساسيّةٍ\n";
    $o .= "حوكميٌّ لا دفترُ حقولٍ مبنيّ — بل **قياسُ الحقلِ من الأثرِ كما قيست الحبّةُ في §٧ ①**.\n\n";
    $o .= "## المفتاحان — مسمَّيان صراحةً\n\n";
    $o .= "| الطرف | المفتاح | المقام |\n|---|---|---:|\n";
    $o .= "| التصميميّ | `repair01_fields.requirement_id` | **"
        . (int) $conn->query("SELECT COUNT(*) FROM repair01_fields")->fetch_row()[0] . "** حقلًا |\n";
    $o .= "| المبنيّ | `repair01_screen_registry.screen_id` ⇐ الأثرُ على القرص | **" . count($PATH) . "** سطحًا |\n";
    $o .= "| **الجسر** | `repair01_target_universe` — تحمل الطرفَين بحكمٍ وشاهد | **" . count($out) . "** مطابَقًا |\n\n";
    $o .= "⛔ **ولا جدولَ مصالحةٍ جديدًا اختُرع** — المعرِّفُ القانونيُّ الواحدُ كان موجودًا،\n";
    $o .= "والناقصُ كان **الطرفَ المبنيَّ نفسَه**. ولا يُقاس إلّا على `MATCHED`: سطحٌ لم يُطابَق\n";
    $o .= "لا ملفَّ تصميميَّ له يُقاس عليه.\n\n";
    $o .= "## المقيس\n\n| البند | العدد |\n|---|---:|\n";
    $o .= "| مفرداتُ `<label>` | " . $tot['F1'] . " |\n| مفرداتُ `<th>` | " . $tot['F2'] . " |\n";
    $o .= "| مفرداتُ `name=` | " . $tot['F3'] . " |\n";
    $o .= "| مفرداتُ `gov_field_class` (`F4`) | " . $tot['F4'] . " على " . $tot['u13'] . " سطحًا مولَّدًا |\n";
    $o .= "| حقولُ الفعلِ في عقدِ `u13` (`F5`) | " . $tot['F5'] . " |\n";
    $o .= "| عُدَدٌ مشتركةٌ أُقصيت | " . count($SHARED) . " ملفًّا |\n";
    $o .= "| مساراتٌ مُحوِّلةٌ لا شاشات | " . $tot['redir'] . " |\n";
    $o .= "| حقولٌ تصميميّةٌ على المطابَقين | " . $tot['des'] . " |\n";
    $o .= "| منها `AUDIT` (إلحاقيّةٌ بنصِّ §٧ ١١) | " . $tot['audit'] . " |\n";
    $o .= "| **المقامُ المنطبق** | **" . $tot['appl'] . "** |\n";
    $o .= "| **المطابَقُ في الأثر** | **" . $tot['hit'] . "** |\n";
    $o .= "| **النسبة** | **" . $pc . "%** |\n";
    $o .= "| أسطحٌ طوبقت كاملةً | " . $tot['full'] . " من " . count($out) . " |\n";
    $o .= "| أسطحٌ بلا مفرداتٍ (`NO_VOCAB`) | " . $tot['novocab'] . " |\n\n";
    $o .= "⛔ **و`AUDIT` أُخرجت بنصٍّ لا برأي** — §٧ الخطوة ١١ تسمّيها «إلحاقية»، **وعددُها\n";
    $o .= "معروضٌ أعلاه** فمن شاء ردَّها إلى المقامِ ردَّها ورأى النسبةَ الأخرى.\n\n";
    $o .= "## ما لا يزعمه هذا القياس\n\n";
    $o .= "- **لا يزعم أنَّ الحقلَ المطابَقَ صحيحُ النوعِ ولا الترتيبِ ولا التحقّق** — يزعم\n";
    $o .= "  أنَّه **حاضرٌ في الأثر**. وترتيبُ الحقولِ ونوعُها خطوتان أخريان في §٧.\n";
    $o .= "- **ولا يزعم أنَّ `NO_VOCAB` سطحٌ بلا حقول** — بل أنَّ حقولَه لا تُنتزع من نصِّه\n";
    $o .= "  الساكن، **وذلك عجزُ قياسٍ مُعلَنٌ يُسمّى ولا يُكتب صفرًا**.\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/RPR02_S7_FIELDS.md', $o);
    echo "\n✔ كُتب: docs/REPAIR01_20260823/RPR02_S7_FIELDS.md\n";
}
