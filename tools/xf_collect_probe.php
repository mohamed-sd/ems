<?php
/**
 * tools/xf_collect_probe.php — مسبارُ `ems_xf_collect()` (XF-01)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ يقيس **قواعدَ الجمعِ الأربعَ** التي يقوم عليها العقدُ كلُّه — ولو انكسرت
 *   واحدةٌ منها لضاعت بياناتٌ أو دخلت قيمةٌ ملفَّقةٌ بلا صوتٍ في أيِّ سجلّ:
 *     ① **الغيابُ ليس محوًا** — مفتاحٌ لم يُرسَل لا يدخل المصفوفةَ فلا يُكتب.
 *     ② **الفراغُ محوٌ إراديّ** — أُرسِل فارغًا ⇒ NULL (والفرقُ بينه وبين ① هو
 *        الفرقُ بين «لم يُذكَر» و«امحُه»).
 *     ③ **قائمةُ السماحِ صلبة** — قيمةٌ خارجَ خياراتِ `select` تُردُّ NULL،
 *        فقيدُ الواجهةِ يُتجاوَز بطلبٍ مُلفَّقٍ والقيدُ الحقيقيُّ هنا.
 *     ④ **القصُّ بطولِ العمودِ لا بطولِ الحمولة** — وبمحارفَ لا ببايتات، وإلّا
 *        بترَ العربيةَ في منتصفِ حرفٍ (`sql_mode` خالٍ فالبترُ صامت).
 *   وزيادةً: **لا يُقبل مفتاحٌ ليس في السجلّ** — المصدرُ السجلُّ لا الطلب.
 *
 * التشغيل: php tools/xf_collect_probe.php   · يخرج 1 عند أيِّ إخفاق
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
mb_internal_encoding('UTF-8');
require_once dirname(__DIR__) . '/includes/extra_fields.php';

$S = 'Clients/clients.php';
$pass = 0; $fail = array();
function t(&$pass, &$fail, $name, $cond, $got = '') {
    if ($cond) { $pass++; echo "  ✔ {$name}\n"; }
    else { $fail[] = $name . ($got !== '' ? " — المُرجَع: {$got}" : ''); echo "  ✗ {$name}" . ($got !== '' ? " — {$got}" : '') . "\n"; }
}

echo "════ مسبارُ `ems_xf_collect()` ════\n";

/* ① الغيابُ ليس محوًا */
$r = ems_xf_collect($S, array('client_name' => 'س'));
t($pass, $fail, '① حمولةٌ بلا حقولٍ إضافيةٍ ⇒ مصفوفةٌ فارغةٌ (لا يُكتب شيء)',
   $r === array(), json_encode($r, JSON_UNESCAPED_UNICODE));

/* ② الفراغُ محوٌ إراديّ */
$r = ems_xf_collect($S, array('legal_name' => '   '));
t($pass, $fail, '② حقلٌ أُرسِل فارغًا ⇒ NULL صريحةٌ (تفريغٌ إراديّ)',
   array_key_exists('legal_name', $r) && $r['legal_name'] === null, json_encode($r, JSON_UNESCAPED_UNICODE));

/* ③ قائمةُ السماحِ الصلبة */
$r = ems_xf_collect($S, array('legal_form' => 'قيمةٌ ملفَّقةٌ ليست في القائمة'));
t($pass, $fail, '③ قيمةُ `select` خارجَ الخياراتِ ⇒ NULL لا تُكتب',
   $r['legal_form'] === null, json_encode($r, JSON_UNESCAPED_UNICODE));

$r = ems_xf_collect($S, array('legal_form' => 'شركة تضامن'));
t($pass, $fail, '③ب قيمةٌ داخلَ الخياراتِ ⇒ تمرُّ كما هي',
   $r['legal_form'] === 'شركة تضامن', json_encode($r, JSON_UNESCAPED_UNICODE));

/* ④ القصُّ بالمحارفِ لا بالبايتات */
$long = str_repeat('م', 400);                 /* 400 محرفًا = 800 بايتًا */
$r = ems_xf_collect($S, array('legal_name' => $long));
$len = mb_strlen($r['legal_name'], 'UTF-8');
t($pass, $fail, '④ نصٌّ أطولُ من العمودِ يُقَصُّ إلى 255 **محرفًا**',
   $len === 255, "الطول={$len}");
t($pass, $fail, '④ب المقصوصُ يبقى UTF-8 سليمًا (لا بترَ في منتصفِ حرف)',
   mb_check_encoding($r['legal_name'], 'UTF-8'));

/* ⑤ مفتاحٌ ليس في السجلّ */
$r = ems_xf_collect($S, array('is_deleted' => 1, 'created_by' => 99, 'email' => 'x@y.z', 'legal_name' => 'اسم'));
t($pass, $fail, '⑤ مفاتيحُ خارجَ السجلِّ (`is_deleted`) لا تمرُّ',
   !array_key_exists('is_deleted', $r), json_encode(array_keys($r)));
t($pass, $fail, '⑤ب المشتقُّ (`created_by`) والقائمُ (`email`) لا يُكتبان من هنا',
   !array_key_exists('created_by', $r) && !array_key_exists('email', $r), json_encode(array_keys($r)));
t($pass, $fail, '⑤ج والخاصُّ (`legal_name`) يمرُّ',
   isset($r['legal_name']) && $r['legal_name'] === 'اسم');

/* ⑥ شاشةٌ غيرُ مسجَّلةٍ ⇒ لا شيء (لا خطأ) */
$r = ems_xf_collect('Some/unregistered.php', array('legal_name' => 'س'));
t($pass, $fail, '⑥ شاشةٌ غيرُ مسجَّلةٍ ⇒ مصفوفةٌ فارغةٌ بلا خطأ', $r === array());

/* ⑦ تطابقُ السجلِّ مع القاعدةِ — العمودُ الخاصُّ يجب أن يكون موجودًا وNULLable */
require_once dirname(__DIR__) . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) {
    echo "  ⚠ تعذّر الاتصال — تخطّي ⑦\n";
} else {
    $conn->set_charset('utf8mb4');
    $def = ems_xf_screen($S);
    $cols = array();
    $q = $conn->query("SHOW COLUMNS FROM `" . $def['table'] . "`");
    while ($q && ($x = $q->fetch_assoc())) { $cols[$x['Field']] = $x; }
    $missing = array(); $notNull = array();
    foreach (ems_xf_own_columns($S) as $c) {
        if (!isset($cols[$c['key']])) { $missing[] = $c['key']; continue; }
        if ($cols[$c['key']]['Null'] !== 'YES') { $notNull[] = $c['key']; }
    }
    t($pass, $fail, '⑦ كلُّ عمودٍ خاصٍّ في السجلِّ له عمودٌ في القاعدة',
       !$missing, implode(',', $missing));
    t($pass, $fail, '⑦ب وكلُّها NULLable — والاختياريُّ لا يكون إلزاميًّا',
       !$notNull, implode(',', $notNull));
}

echo "──────────────────────────────────────\n";
echo "  مرَّ: {$pass} · أخفق: " . count($fail) . "\n";
foreach ($fail as $f) { echo "  ✗ {$f}\n"; }
if ($fail) { exit(1); }
echo "✔ عقدُ الجمعِ سليمٌ — الغيابُ ليس محوًا · والملفَّقُ لا يُكتب\n";
