<?php
/**
 * tests/injfrd01_sec001_party_scope_failclosed.php
 *   شاهدُ FR-SEC-001 · FR-SEC-002 — نطاقُ الأطرافِ يفشل **مغلقًا**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **معيارُ القبولِ بنصِّ الدفتر**: «اختبارٌ سالبٌ بدورٍ غيرِ مصنَّفٍ يُرجع صفرًا»
 *   و«صفرُ دورٍ بلا نطاقٍ معلَن» — والأخيرُ يعني: **لا دورَ يعتمد على الفراغ**.
 *
 * ◆ **ويُقاس السلوكُ لا الشيفرة**: تُبنى ثلاثةُ سياقاتٍ (مصنَّفٌ · غيرُ مصنَّفٍ ·
 *   منفتحٌ مُعلَن) ويُسأل المحلِّلُ عن كلٍّ، ثم **يُبنى شرطُ الاستعلامِ الفعليُّ**
 *   كما تبنيه الشاشتان — فلا يُقاس أن الدالةَ تعود بقيمةٍ بل أن الصفوفَ تُقيَّد.
 *
 * التشغيل: php tests/injfrd01_sec001_party_scope_failclosed.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$GLOBALS['conn'] = $conn;                 /* الدالةُ تقرأ السجلَّ عبرَ الاتصالِ العام */
require_once $ROOT . '/Finance/fin_helpers.php';

$ok = 0; $bad = 0;
function chk($c, $l, $d = '') {
    global $ok, $bad;
    if ($c) { $ok++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $bad++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}
function n(mysqli $d, $q, $params = array()) {
    if (!$params) { $r = @$d->query($q); return $r ? (int) $r->fetch_row()[0] : -1; }
    $st = $d->prepare($q);
    if (!$st) { return -1; }
    $st->bind_param(str_repeat('s', count($params)), ...$params);
    $st->execute(); $st->bind_result($v); $st->fetch(); $st->close();
    return (int) $v;
}

echo "══ FR-SEC-001 · FR-SEC-002 — نطاقُ الأطرافِ يفشل مغلقًا ══\n";

/* ① المصدرُ الواحدُ موجودٌ ومعمور */
$reg = n($conn, "SELECT COUNT(*) FROM `fin_party_scope_registry`");
chk($reg > 0, 'FR-SEC-002 · نطاقُ الأطرافِ في **مصدرٍ واحدٍ مُعلَن**', "{$reg} دورًا مسجَّلًا");

/* ② دورٌ مصنَّفٌ ← نطاقُه هو، لا الكل */
$s4 = fin_party_scope(array('role' => 4, 'is_super' => false));
chk($s4 === 'employee', 'دورُ الموارد البشرية ← الموظفون حصرًا', "المُرجَع: {$s4}");
$s2 = fin_party_scope(array('role' => 2, 'is_super' => false));
chk($s2 === 'supplier', 'ودورُ الموردين ← الموردون حصرًا', "المُرجَع: {$s2}");

/* ③ **الاختبارُ السالبُ الحاكم**: دورٌ غيرُ مصنَّفٍ ← صفرُ صفٍّ لا كلُّ الصفوف */
$unclassified = 0;
$q = $conn->query("SELECT r.`id` FROM `roles` r
                    LEFT JOIN `fin_party_scope_registry` s ON s.`role_id` = r.`id`
                   WHERE s.`role_id` IS NULL ORDER BY r.`id` LIMIT 1");
if ($q && $x = $q->fetch_row()) { $unclassified = (int) $x[0]; }
chk($unclassified > 0, 'وُجد دورٌ غيرُ مصنَّفٍ للاختبارِ السالب', "الدور {$unclassified}");

$sNone = fin_party_scope(array('role' => $unclassified, 'is_super' => false));
chk($sNone === PARTY_SCOPE_NONE, '**دورٌ غيرُ مصنَّفٍ ⇐ إغلاقٌ لا انفتاح**', "المُرجَع: {$sNone}");

/* ④ والسلوكُ يُقاس بالصفوفِ لا بالقيمة — يُبنى الشرطُ كما تبنيه الشاشة */
$total = n($conn, "SELECT COUNT(*) FROM `fin_dues` WHERE COALESCE(`is_deleted`,0)=0");
$where = "COALESCE(is_deleted,0)=0";
if ($sNone === PARTY_SCOPE_NONE)      { $where .= " AND 1=0"; }
elseif ($sNone !== PARTY_SCOPE_ALL)   { $where .= " AND party_type = '" . $conn->real_escape_string($sNone) . "'"; }
$seen = n($conn, "SELECT COUNT(*) FROM `fin_dues` WHERE {$where}");
chk($seen === 0 && $total >= 0,
    '**وصفوفُ الذممِ التي يراها: صفر** لا كلُّ الصفوف',
    "يرى {$seen} من {$total}");

/* ⑤ والانفتاحُ المشروعُ يبقى مفتوحًا — فالإغلاقُ لم يكسر المالية */
$s17 = fin_party_scope(array('role' => 17, 'is_super' => false));
$w2 = "COALESCE(is_deleted,0)=0";
if ($s17 === PARTY_SCOPE_NONE)    { $w2 .= " AND 1=0"; }
elseif ($s17 !== PARTY_SCOPE_ALL) { $w2 .= " AND party_type = '" . $conn->real_escape_string($s17) . "'"; }
$seen17 = n($conn, "SELECT COUNT(*) FROM `fin_dues` WHERE {$w2}");
chk($s17 === PARTY_SCOPE_ALL && $seen17 === $total,
    'وإدارةُ الماليةِ ترى الكلَّ بانفتاحٍ **مُعلَنٍ** لا بفراغ',
    "المُرجَع: {$s17} · ترى {$seen17} من {$total}");

/* ⑥ والسوبر أدمن يعبر — استثناءٌ قائمٌ قبلَ هذا المطلبِ ولا يُمَسّ */
$sSuper = fin_party_scope(array('role' => 0, 'is_super' => true));
chk($sSuper === PARTY_SCOPE_ALL, 'والسوبر أدمن يعبر كما كان', "المُرجَع: {$sSuper}");

/* ⑦ **تعذُّرُ قراءةِ السجلِّ يفشل مغلقًا** — لا يُقرأ عطبُ الاتصالِ إذنًا */
$saved = $GLOBALS['conn'];
$GLOBALS['conn'] = null;
$sBroken = fin_party_scope(array('role' => 999001, 'is_super' => false));
$GLOBALS['conn'] = $saved;
chk($sBroken === PARTY_SCOPE_NONE, 'وتعذُّرُ قراءةِ السجلِّ **يفشل مغلقًا**', "المُرجَع: {$sBroken}");

/* ⑧ ولا **قرارَ نطاقٍ بالدور** مكتوبٌ في كودِ الشاشة — FR-SEC-002
 * ◆ **أوّلُ قياسٍ لي كان أوسعَ من حكمِه**: بحث عن أيِّ `party_type='supplier'`
 *   فالتقط **مُميِّزَ JOIN** (`LEFT JOIN suppliers ON d.party_type='supplier'`)
 *   وكشفَ حسابِ مورّدٍ بعينِه — وليس فيهما قرارُ «مَن يرى ماذا بدورِه».
 *   والمطلبُ نصُّه: «لكلِّ دورٍ نطاقُ أطرافٍ مُعلَنٌ في مصدرٍ واحد **لا في كودِ
 *   الشاشة**» — أي **خريطةُ الدورِ ⇐ النطاق**. ⇒ يُقاس ما يُحكَم عليه:
 *   شرطٌ على رقمِ الدورِ يُنتج نطاقَ طرفٍ في الملفِّ نفسِه. */
$inScreens = 0; $names = array();
foreach (array_merge(glob($ROOT . '/Finance/*.php'), glob($ROOT . '/Suppliers/*.php')) as $f) {
    if (basename($f) === 'fin_helpers.php') { continue; }
    $src = (string) file_get_contents($f);
    /* شرطٌ على الدورِ في السطرِ نفسِه أو الذي يليه ثم إسنادُ نطاقِ طرف */
    if (preg_match('~\$[a-z_]*role[a-z_]*\s*(?:===?|!==?|==)\s*[\'"]?\d+[\'"]?[^;\n]{0,80}\n?[^;\n]{0,80}'
                 . '(?:party_scope|party_type)\s*=\s*[\'"](?:employee|supplier|client)[\'"]~i', $src)
        || preg_match('~in_array\s*\(\s*\$[a-z_]*role[^)]*\)[^;\n]{0,60}\n?[^;\n]{0,60}'
                 . '(?:party_scope|party_type)\s*=\s*[\'"](?:employee|supplier|client)[\'"]~i', $src)) {
        $inScreens++; $names[] = basename($f);
    }
}
chk($inScreens === 0, 'وصفرُ **قرارِ نطاقٍ بالدورِ** في كودِ الشاشة',
    $inScreens === 0 ? 'صفر — والخريطةُ في السجلِّ وحدَه' : implode(' · ', $names));

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
