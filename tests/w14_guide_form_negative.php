<?php
/**
 * tests/w14_guide_form_negative.php — اختبارُ مسارِ كتابةِ نموذجِ الدليلِ السالب
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ولكلِّ قفلٍ ضِلعان**: يرفض الممنوعَ **ويقبل المسموح** — فحارسٌ يرفض كلَّ
 *   شيءٍ أخضرُ كاذبٌ لا يُثبت شيئًا [[negative-test-needs-unique-token]].
 * ◆ **والمفردةُ فريدةٌ بالمعرِّفِ والزمن**: `GF-NEGTEST-<pid>-<ts>` — فلا يُحرِّك
 *   الاختبارُ عدّادًا بكسرِ مفردةٍ متكرِّرة، **ويُكنَس أثرُه كلُّه**.
 * ⛔ **ولم يُمَسَّ صفٌّ حيّ**: الصفوفُ المُنشأةُ تُحذَف، ونطاقُ الترقيمِ المؤقّتُ
 *   يُزال — والمقامُ يُقاس صفريًّا قبل البدءِ وبعدَ الكنس.
 *
 * ◆ **العطبانِ اللذانِ كشفهما هذا الاختبارُ حيًّا** (‏SILENT_DROP_FIX):
 *   ① **الحارسُ كان في التصييرِ لا في المعالجة**: قائمةٌ منسدلةٌ لا تمنع إرسالًا
 *      مصنوعًا — ومفردةٌ خارجَ الدليلِ **كُتبت فعلًا** حتى حُرست خادميًّا.
 *   ② **`PK_GENERATED` بلا مولِّد**: العمودُ يخرج فارغًا فيتصادم المفتاحُ
 *      الفريدُ عند **ثاني** إدخالٍ ويسقط بلا سببٍ ظاهر.
 *
 * التشغيل: php tests/w14_guide_form_negative.php   (‏0 = نجاح)
 * ═══════════════════════════════════════════════════════════════════════════
 */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$R = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__)) . '/';
ob_start();
require_once $R . 'includes/session_bootstrap.php';
require_once $R . 'config.php';
ob_get_clean();
$_SESSION['user'] = array('id' => 1, 'role' => '-1', 'company_id' => 1, 'username' => 'cli');
require_once $R . 'includes/w14_guide_form.php';
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$src = file_get_contents($R . 'Governance/policies.php');
$map = array();
if (preg_match('~\$GUIDE_COLS\s*=\s*array\s*\((.*?)\)\s*;~su', $src, $mm)) {
    if (preg_match_all("~'([^']+)'\s*=>\s*'([^']*)'~u", $mm[1], $ps, PREG_SET_ORDER)) {
        foreach ($ps as $p) { $map[$p[1]] = $p[2]; }
    }
}
$MARK = 'GF-NEGTEST-' . getmypid() . '-' . time();   /* مفردةٌ فريدةٌ تُكنَس */
$base = array('surfaces' => array('سجل السياسات'), 'table' => 'gov_policy',
              'cols' => $map, 'screen' => 'Governance/policies.php');

$n = 0; $pass = 0;
function ok($c, $t, $e = '') { global $n, $pass; $n++; if ($c) { $pass++; } printf("  %s %s%s\n", $c ? '✔' : '✘', $t, $e !== '' ? ' — ' . $e : ''); }

function countMark($conn, $m) {
    $st = $conn->prepare('SELECT COUNT(*) c FROM gov_policy WHERE title_ar = ?');
    $st->bind_param('s', $m); $st->execute();
    $r = $st->get_result()->fetch_assoc(); $st->close();
    return (int) $r['c'];
}
function post($o, $extra) {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = $extra;
    ob_start(); ems_w14_guide_form($o); $h = ob_get_clean();
    $_SERVER['REQUEST_METHOD'] = 'GET'; $_POST = array();
    return $h;
}

echo "══ اختبارُ مسارِ كتابةِ نموذجِ الدليل ══\n";
/* ⭐ ونمطُ الترقيمِ يُسجَّل مؤقّتًا للاختبارِ ثمَّ يُكنَس — فالحاجزُ الحقيقيُّ
   أنَّ الحوكمةَ تملكه، والاختبارُ يقيس المسارَ لا يقرِّر النمط. */
$conn->query("INSERT IGNORE INTO ems_sequences (scope, next_val) VALUES ('gov_policy:NEGTEST-:6', 1)");
$before = countMark($conn, $MARK);
ok($before === 0, 'المقامُ صفريٌّ قبل البدء — والمفردةُ فريدةٌ لا تتكرَّر', $MARK);

/* ① CSRF — يرفض بلا رمزٍ ثمَّ يقبل به */
$h = post($base + array('perms' => array('can_add' => true)),
          array('__gf' => 'gf_gov_policy', 'csrf_token' => 'bad', 'title_ar' => $MARK));
ok(countMark($conn, $MARK) === 0 && strpos($h, 'انتهت صلاحيةُ الجلسة') !== false,
   '① رمزٌ فاسدٌ ⇒ **لا كتابة** وبرسالةٍ مُسمّاة');

/* ② الصلاحية — `can_add = false` يرفض ولو صحَّ الرمز */
$tok = generate_csrf_token();
$h = post($base + array('perms' => array('can_add' => false)),
          array('__gf' => 'gf_gov_policy', 'csrf_token' => $tok, 'title_ar' => $MARK));
ok(countMark($conn, $MARK) === 0 && strpos($h, 'لا صلاحيةَ إضافة') !== false,
   '② بلا `can_add` ⇒ **لا كتابة** وبرسالةٍ مُسمّاة');

/* ③ مفردةٌ خارجَ القائمةِ المحكومة تُسقَط ولا تُكتب فراغًا */
$h = post($base + array('perms' => array('can_add' => true)),
          array('__gf' => 'gf_gov_policy', 'csrf_token' => $tok,
                'title_ar' => $MARK, 'domain_ar' => 'مفردةٌ ليست من الدليل'));
$wrote = countMark($conn, $MARK);
$dom = '';
if ($wrote) {
    $st = $conn->prepare('SELECT domain_ar FROM gov_policy WHERE title_ar = ? LIMIT 1');
    $st->bind_param('s', $MARK); $st->execute();
    $rr = $st->get_result()->fetch_assoc(); $st->close();
    $dom = (string) $rr['domain_ar'];
}
ok($wrote === 1, '③-أ المسموحُ **يُكتب فعلًا** — والقفلُ لا يرفض كلَّ شيء', 'صفوف=' . $wrote);
ok($dom === '', '③-ب ومفردةٌ خارجَ القائمةِ **تُسقَط ولا تُكتب**', 'domain_ar=«' . $dom . '»');

/* ④ ومفردةٌ من القائمةِ تُكتب كما هي */
$M2 = $MARK . '-B';
$h = post(array('surfaces' => array('سجل السياسات'), 'table' => 'gov_policy', 'cols' => $map,
                'screen' => 'Governance/policies.php', 'perms' => array('can_add' => true)),
          array('__gf' => 'gf_gov_policy', 'csrf_token' => $tok,
                'title_ar' => $M2, 'domain_ar' => 'حوكمة والتزام'));
$st = $conn->prepare('SELECT domain_ar FROM gov_policy WHERE title_ar = ? LIMIT 1');
$st->bind_param('s', $M2); $st->execute();
$rr = $st->get_result()->fetch_assoc(); $st->close();
ok($rr && $rr['domain_ar'] === 'حوكمة والتزام', '④ مفردةٌ من الدليل **تُكتب حرفًا**',
   'domain_ar=«' . ($rr ? $rr['domain_ar'] : '—') . '»');

/* ⑤ الكنس — ⛔ ولا يُترك أثرُ اختبارٍ في شجرةٍ حيّة */
$st = $conn->prepare('DELETE FROM gov_policy WHERE title_ar IN (?,?)');
$st->bind_param('ss', $MARK, $M2); $st->execute();
$del = $conn->affected_rows; $st->close();
$conn->query("DELETE FROM ems_sequences WHERE scope = 'gov_policy:NEGTEST-:6'");
ok(countMark($conn, $MARK) === 0 && $del >= 1, '⑤ كُنس أثرُ الاختبار — صفرُ بقايا', 'حُذف=' . $del);

printf("\n  النتيجة: %d نجاح · %d رسوب من %d\n", $pass, $n - $pass, $n);
exit($pass === $n ? 0 : 1);
