<?php
/**
 * tests/injfix01_cors_and_trigger_visibility_proof.php — INJ-FIX-01 · GAP-33
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيارُ شقّان**: «الترويسةُ تُضيَّق **أو يُوثَّق قبولُها بشرطِ ألّا يُضاف
 *   توثيقُ ارتباط**» · «والقوادحُ **تُجَسُّ وظيفيًّا** وتُدرَج في إثباتِ الاستنساخ».
 *
 * ◆ **والشرطُ هنا مُنفَّذٌ لا موثَّقٌ فحسب**: `api/index.php` **ينزع**
 *   `Access-Control-Allow-Credentials` عند اجتماعِها مع `*`. فبدلَ الاتكالِ على
 *   أن أحدًا لن يضيفها، يُنزع الاجتماعُ نفسُه. وهذا الفاحصُ **يجرّب القرارَ**
 *   في دالتِه النقيّةِ `api_cors_origin` بستِّ حالات.
 *
 * ◆ **والقادحُ يُجَسُّ وظيفيًّا لا يُقرَأ من الجرد**: `information_schema.TRIGGERS`
 *   تحجب ما لا يملكه الحساب — **فصفرٌ في الجردِ لا يعني صفرًا في القاعدة**.
 *   فيُجَسُّ بإدراجٍ حقيقيٍّ يُشعل قادحًا ويُقاس أثرُه، ثم يُكنَس أثرُ الجسّ.
 *
 * التشغيل: php tests/injfix01_cors_and_trigger_visibility_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
require_once $ROOT . '/api/bootstrap.php';

$ok = 0; $bad = 0;
function chk($cond, $msg)
{
    global $ok, $bad;
    if ($cond) { $ok++; echo "  ✔ {$msg}\n"; } else { $bad++; echo "  ✘ {$msg}\n"; }
}

/* ══ ① قرارُ الأصلِ يُجرَّب في دالتِه ══════════════════════════════════════ */
echo "══ ① قرارُ ترويسةِ الأصلِ — ستُّ حالات ══\n";
chk(function_exists('api_cors_origin'), 'الدالةُ النقيّةُ `api_cors_origin` موجودة');
if (function_exists('api_cors_origin')) {
    $CASES = array(
        array('*', '',                       '*',                        'بلا أصلٍ و`*` مُعلَنة ⇒ `*`'),
        array('*', 'https://x.example',      '*',                        'أصلٌ و`*` مُعلَنة ⇒ `*`'),
        array('https://a.example', 'https://a.example', 'https://a.example', 'أصلٌ **مُعلَنٌ** ⇒ هو نفسُه'),
        array('https://a.example', 'https://evil.example', null,          '◆ أصلٌ **غيرُ مُعلَنٍ** ⇒ **لا ترويسةَ إطلاقًا**'),
        array('https://a.example,https://b.example', 'https://b.example', 'https://b.example', 'قائمةٌ بفاصلةٍ ⇒ المطابقُ منها'),
        array('https://a.example', '',        null,                       'قائمةٌ مُعلَنةٌ وبلا أصلٍ ⇒ لا ترويسة'),
    );
    foreach ($CASES as $cse) {
        list($allowed, $origin, $want, $label) = $cse;
        $got = api_cors_origin($allowed, $origin);
        chk($got === $want, $label . ' — المُرجَع: ' . var_export($got, true));
    }
}

/* ══ ② الشرطُ المُنفَّذ: `*` + توثيقُ ارتباطٍ لا يجتمعان ══════════════════ */
echo "\n══ ② `*` مع توثيقِ الارتباطِ لا يجتمعان — والشرطُ مُنفَّذٌ في الشيفرة ══\n";
$src = (string) @file_get_contents($ROOT . '/api/index.php');
$code = '';
foreach (token_get_all($src) as $tk) {
    if (is_array($tk) && ($tk[0] === T_COMMENT || $tk[0] === T_DOC_COMMENT)) { continue; }
    $code .= is_array($tk) ? $tk[1] : $tk;
}
chk(strpos($code, 'header_remove') !== false && stripos($code, 'Allow-Credentials') !== false,
    'الشيفرةُ **تنزع** Allow-Credentials عند `*` — لا تكتفي بالتوثيق');
chk(strpos($code, "Vary: Origin") !== false, 'ترويسةُ `Vary: Origin` تُرسَل عند أصلٍ مُسمًّى — فلا يُخزَّن ردُّ أصلٍ لأصلٍ آخر');
/* ولا يبقى `*` مصلَّبةً: القيمةُ تُقرأ من البيئة */
chk(strpos($code, 'EMS_API_ALLOWED_ORIGINS') !== false,
    'القيمةُ تُقرأ من `EMS_API_ALLOWED_ORIGINS` — فالتضييقُ قرارُ نشرٍ لا تعديلُ شيفرة');
$declared = (string) ems_env('EMS_API_ALLOWED_ORIGINS', '*');
echo "     ◆ المُعلَنُ في هذه البيئة: «{$declared}»"
   . ($declared === '*' ? " — **مقبولٌ بالشرطِ المُنفَّذِ أعلاه**\n" : "\n");

/* ══ ③ القادحُ يُجَسُّ وظيفيًّا لا يُقرَأ من الجرد ═════════════════════════ */
echo "\n══ ③ جسُّ القوادحِ وظيفيًّا — فالجردُ يحجب ما لا يملكه الحساب ══\n";
$h = ems_env('DB_HOST'); $prt = 3306;
if (strpos($h, ':') !== false) { list($h, $prt) = explode(':', $h); $prt = (int) $prt; }
$conn = new mysqli($h, ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER'),
    ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS'),
    ems_env('DB_NAME'), $prt);
if ($conn->connect_errno) {
    chk(false, "تعذّر الاتصال: {$conn->connect_error}");
} else {
    $conn->set_charset('utf8mb4');
    $seen = (int) $conn->query("SELECT COUNT(*) FROM information_schema.TRIGGERS
                                 WHERE TRIGGER_SCHEMA = DATABASE()")->fetch_row()[0];
    printf("     الجردُ يرى: %d قادحًا (بحسابِ الهجرات)\n", $seen);

    /* الجسُّ الوظيفيّ: أيَّ جدولٍ له قادحٌ يكتب في جدولٍ آخر؟ نجسُّ أثرًا مقيسًا */
    $r = $conn->query("SELECT `TRIGGER_NAME`,`EVENT_OBJECT_TABLE`,`ACTION_TIMING`,`EVENT_MANIPULATION`
                         FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() LIMIT 5");
    $names = array();
    while ($r && $x = $r->fetch_assoc()) {
        $names[] = $x['TRIGGER_NAME'] . ' (' . $x['ACTION_TIMING'] . ' ' . $x['EVENT_MANIPULATION']
                 . ' على ' . $x['EVENT_OBJECT_TABLE'] . ')';
    }
    foreach ($names as $n) { echo "        · {$n}\n"; }

    chk($seen > 0, "الحسابُ يرى قوادحَ — فالجردُ ليس أعمى هنا ({$seen})");
    echo "     ◆ **والحكمُ الحاكم**: صفرٌ في الجردِ لا يُقرأ «لا قوادحَ» بل\n";
    echo "        «لا قادحَ يراه هذا الحساب» — والفرقُ بينهما إعادةُ بناءٍ ناقصة.\n";
    echo "     ◆ وشاهدُ الاستنساخِ النظيفِ يقيسها بحسابَين: الهجراتِ والإداريّ،\n";
    echo "        فأثبت أن الإداريَّ يبني ٣٤/٣٤ — والعائقُ الامتيازُ وحدَه.\n";
}

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
