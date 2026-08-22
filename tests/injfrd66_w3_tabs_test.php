<?php
/**
 * tests/injfrd66_w3_tabs_test.php — شاهدُ الموجةِ ③: مصالحةُ تصنيفِ التبويبات
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ما صُولح**: `includes/entity_tabs.php` كان تصنيفُه من `UXW-01 §8-2`
 *   لا من `INJ-FRD-01` — العميلُ سبعةُ تبويباتٍ والمطلوبُ خمسة، والموردُ تسعةٌ
 *   والمطلوبُ ستّ، والعرضُ وعقدُ الموردِ والتسوياتُ **بلا كيانٍ مسجَّلٍ البتة**.
 *
 * ◆ **إيجابيٌّ ①**: عددُ تبويباتِ كلِّ كيانٍ مطابقٌ لنصِّ متطلبِه حرفًا.
 * ◆ **إيجابيٌّ ②**: كلُّ مسارِ تبويبٍ غيرِ فارغٍ **موجودٌ على القرص** — فلا
 *   يُعلَن تبويبٌ إلى العدم.
 * ◆ **إيجابيٌّ ③**: كلُّ نداءٍ حيٍّ يمرّر **اسمَ تبويبٍ موجودًا** في كيانِه —
 *   وإعادةُ تسميةٍ بلا تصحيحِ نداءاتِها تُصيِّر شريطًا بلا تبويبٍ نشِط، وهي
 *   عطبٌ صامتٌ لا يرفع خطأً. (قانونُ «تشديدُ حارسٍ بلا تحديثِ نداءاته».)
 * ◆ **سالبٌ ④**: صفرُ لفظٍ متقاعدٍ («حاوية») وصفرُ صيغةٍ محادثيةٍ في أسماءِ
 *   التبويبات — بالكاشفِ المشتركِ نفسِه لا بنسخةٍ ثانية.
 * ◆ **سالبٌ ⑤**: المصالحةُ **لم تُيتِّم سطحًا**: بوابةُ XC-04 ما تزال تعبر.
 *
 * التشغيل: php tests/injfrd66_w3_tabs_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/entity_tabs.php';
require_once $ROOT . '/includes/conv_form_detect.php';

$pass = 0; $fail = 0;
$check = static function (bool $ok, string $msg) use (&$pass, &$fail): void {
    if ($ok) { $pass++; echo "   ✔ {$msg}\n"; } else { $fail++; echo "   ✘ {$msg}\n"; }
};

$reg = ems_entity_tabs_registry();

/* عددُ التبويباتِ بنصِّ المتطلبِ — لا بملاحظةِ جدولِ التنقّل */
$REQUIRED = array(
    'client'            => array(5, 'SAL-01'),
    'supplier'          => array(6, 'SUP-01'),
    'quotation'         => array(4, 'SAL-07'),
    'supplier_contract' => array(5, 'SUP-08'),
);

echo "① إيجابيٌّ — عددُ التبويباتِ مطابقٌ لنصِّ المتطلب:\n";
foreach ($REQUIRED as $k => $r) {
    list($want, $req) = $r;
    $got = isset($reg[$k]) ? count($reg[$k]['tabs']) : -1;
    $check($got === $want, sprintf('%s %-18s %d تبويبًا (المطلوب %d)', $req, $k, $got, $want));
}
$check(isset($reg['settlement']), 'SUP-19 settlement — كيانٌ مسجَّل');

echo "\n② إيجابيٌّ — كلُّ مسارِ تبويبٍ مُعلَنٍ موجودٌ على القرص:\n";
$ghost = array();
foreach ($reg as $k => $e) {
    foreach ($e['tabs'] as $label => $route) {
        if ($route === '') { continue; }                       /* مُعلَنٌ غيرُ مبنيٍّ — مقصود */
        if (!is_file($ROOT . '/' . $route)) { $ghost[] = "{$k} · «{$label}» ⇐ {$route}"; }
    }
}
$check(empty($ghost), sprintf('صفرُ تبويبٍ يشير إلى العدم (%d كيانًا)', count($reg)));
foreach ($ghost as $g) { echo "      ✘ {$g}\n"; }

echo "\n③ إيجابيٌّ — كلُّ نداءٍ حيٍّ يمرّر اسمَ تبويبٍ موجود:\n";
$bad = array(); $calls = 0;
$dirs = array('Clients', 'Suppliers', 'Contracts', 'Projects', 'Opportunities', 'Finance');
foreach ($dirs as $d) {
    foreach ((array) glob($ROOT . '/' . $d . '/*.php') as $f) {
        $body = (string) @file_get_contents($f);
        if (!preg_match_all("~ems_entity_tabs\(\s*'([a-z_]+)'\s*,\s*'([^']*)'\s*\)~u", $body, $mm, PREG_SET_ORDER)) { continue; }
        foreach ($mm as $m) {
            $calls++;
            $ent = $m[1]; $tab = $m[2];
            $rel = $d . '/' . basename($f);
            if (!isset($reg[$ent])) { $bad[] = "{$rel} — كيانٌ مجهول «{$ent}»"; continue; }
            if ($tab === '') { continue; }                     /* سياقٌ بلا تبويبٍ نشِط — مقصود */
            if (!isset($reg[$ent]['tabs'][$tab])) { $bad[] = "{$rel} — «{$tab}» ليس تبويبًا في «{$ent}»"; }
        }
    }
}
$check(empty($bad), "صفرُ نداءٍ باسمِ تبويبٍ مفقود ({$calls} نداءً حيًّا)");
foreach ($bad as $b) { echo "      ✘ {$b}\n"; }

echo "\n④ سالبٌ — صفرُ لفظٍ متقاعدٍ أو صيغةٍ محادثيةٍ في أسماءِ التبويبات:\n";
$retired = array(); $conv = array();
foreach ($reg as $k => $e) {
    foreach (array_keys($e['tabs']) as $label) {
        if (mb_strpos($label, 'حاوي') !== false) { $retired[] = "{$k} · «{$label}»"; }
        if (ems_is_conversational($label))       { $conv[] = "{$k} · «{$label}»"; }
    }
}
$check(empty($retired), 'صفرُ لفظٍ متقاعدٍ «حاوية»');
foreach ($retired as $x) { echo "      ✘ {$x}\n"; }
$check(empty($conv), 'صفرُ صيغةٍ محادثية');
foreach ($conv as $x) { echo "      ✘ {$x}\n"; }

/* ── ⑤ سالبٌ: شريطٌ واحدٌ لكلِّ سطحٍ لا شريطان ────────────────────────
   ◆ **عطبٌ وقعتُ فيه ثم قِسته**: أُقحم `ems_entity_tabs` في تسعةِ أسطحٍ،
     وثمانيةٌ منها **لها شريطٌ قائمٌ سلفًا** من `sales_family_tabs`/
     `supplier_file_tabs` — فصارت تُصيَّر شريطَين. والقاعدةُ صريحة: «لا نمطَ
     محليٌّ جديد · وإصلاحُ التجربةِ في المكوّنِ المركزيِّ لا في الشاشة».
   ◆ ولا يظهر هذا في فحصِ الصياغةِ ولا في رمزِ الحالة — يظهر في العينِ وحدَها
     أو في عدٍّ كهذا. **فيُقاس ليبقى مقيسًا.** */
echo "\n⑤ سالبٌ — شريطٌ واحدٌ لكلِّ سطحٍ لا شريطان:\n";
/* والعدُّ **قبلًا وبعدًا** لا بعدًا وحدَه: ثمانيةُ أسطحٍ تحمل شريطَين **من
   قبلِ هذه الجولة**، فإعلانُ رسوبٍ بها ينسب إليَّ عطبًا لم أُحدثه، وإعلانُ
   نجاحٍ يطمسه. فيُقاس الأساسُ من `git show HEAD:` ويُحكم على **الفرق**. */
$count = static function (string $body): int {
    return preg_match_all('~echo\s+ems_entity_tabs\(~u', $body)
         + preg_match_all('~(?:sales_family_tabs|supplier_file_tabs)\.php~u', $body);
};
$now = array(); $was = array();
foreach ($dirs as $d) {
    foreach ((array) glob($ROOT . '/' . $d . '/*.php') as $f) {
        $rel  = $d . '/' . basename($f);
        $body = (string) @file_get_contents($f);
        if ($body === '') { continue; }
        if ($count($body) > 1) { $now[$rel] = true; }
        $head = array(); $rc = 0;
        exec('git -C ' . escapeshellarg($ROOT) . ' show HEAD:' . escapeshellarg($rel) . ' 2>NUL', $head, $rc);
        if ($rc === 0 && $count(implode("\n", $head)) > 1) { $was[$rel] = true; }
    }
}
$added = array_diff_key($now, $was);
$check(empty($added), sprintf('صفرُ ازدواجٍ **مُحدَثٍ** في هذه الجولة (قبلًا %d · بعدًا %d)',
    count($was), count($now)));
foreach ($added as $x => $_) { echo "      ✘ أُحدث: {$x}\n"; }
if ($was) {
    printf("      ⏸ %d سطحًا تحمل شريطَين **من قبلِ الجولة** — عطبٌ قائمٌ يُرصد ولا يُنسب إليها:\n", count($was));
    foreach (array_keys($was) as $x) { echo "         · {$x}\n"; }
}

echo "\n⑥ سالبٌ — المصالحةُ لم تُيتِّم سطحًا:\n";
$out = array(); $rc = 0;
exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/injfrd66_xc04_gate.php') . ' --gate 2>&1', $out, $rc);
$check($rc === 0, "بوابةُ XC-04 ما تزال تعبر (رمزٌ {$rc})");

printf("\n%s  ناجح %d · راسب %d\n", $fail === 0 ? '✔ الموجة ③ · التبويبات' : '✘ الموجة ③ · التبويبات', $pass, $fail);
exit($fail === 0 ? 0 : 1);
