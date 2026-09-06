<?php
/**
 * tests/perm01_assign_list_repair.php — عطبُ قائمةِ الإسناد (م-ح-0.1)
 * ═══════════════════════════════════════════════════════════════════════════
 * PERM-01-CUTOVER-REPAIR-20260905 · م-ح-0.1: «خدمةُ الكتابةِ تمرّر ترتيبًا فيه
 * أقواس. الفاحصُ يرفض الأقواس. الاستثناءُ يُلتقَط. قائمةُ الموظفين ترجع فارغة.
 * الشاشةُ تبدو سليمة.»
 *
 * ◆ **والقبولُ ثلاثيٌّ بنصِّ الأمر**: قائمةُ إسنادِ المستخدمِ فيها الأحياء ·
 *   صفرُ خطأِ ترتيبٍ في سجلِّ يومِ التنفيذ · الشاشةُ لا تبتلع الخطأَ صامتًا.
 *
 * ⛔ **والفراغُ عن قصدٍ ليس الفراغَ عن عطب**: إن كان لكلِّ حيٍّ منحةٌ نافذةٌ
 *   رجعت القائمةُ فارغةً **وهي سليمة** — فيُقاس المصدرُ قبلَ الطرحِ لا بعدَه،
 *   وإلّا صار الأخضرُ كاذبًا في الحالَين.
 * ⛔ **وضابطٌ سالبٌ يُثبت أنَّ الفاحصَ ما يزال يرفض**: بلا رفضِ القيمةِ القديمةِ
 *   يكون الأخضرُ شهادةً على فاحصٍ عُطِّل لا على عطبٍ زال.
 *
 * التشغيل: php tests/perm01_assign_list_repair.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Services/Security/PolicyWriteService.php';
while (ob_get_level() > 0) { ob_end_clean(); }

use App\Services\Security\PolicyWriteService as PW;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function chk($c, $m, $d = '') { $c ? ok($m . ($d !== '' ? " — {$d}" : '')) : bad($m . ($d !== '' ? " — {$d}" : '')); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$ROOT = dirname(__DIR__);
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$one = function ($s) use ($conn) { $r = @$conn->query($s); return $r ? (int) ($r->fetch_row()[0] ?? 0) : -1; };

/* جلسةُ فاعلٍ من إدارةِ الصلاحيات — القراءةُ بالبوّابةِ تحتاج نطاقًا. */
$admin = $conn->query("SELECT id FROM users
                        WHERE role='15' AND is_deleted=0 AND status='active' AND company_id=4
                        LIMIT 1")->fetch_assoc();
$_SESSION['user'] = array('id' => $admin ? (int) $admin['id'] : 0,
                          'role' => '15', 'company_id' => 4, 'name' => 'assign list probe');

fwrite(STDOUT, "\n══ م-ح-0.1 — عطبُ ترتيبِ قائمةِ الإسناد ══\n");

/* ═══ ① الفاحصُ ما يزال يرفض القيمةَ المعطوبة — ضابطٌ سالب ═══════════════ */
head('① ضابطٌ سالب — الفاحصُ يردُّ الترتيبَ ذا الأقواس');
$rejected = false; $why = '';
try {
    ems_tenant_db()->select('users', array(
        'columns' => array('id'),
        'where'   => array('status' => 'active'),
        'orderBy' => 'CAST' . '(role AS UNSIGNED), name',
        'limit'   => 1,
    ));
} catch (\Throwable $t) { $rejected = true; $why = $t->getMessage(); }
chk($rejected, '★ الترتيبُ ذو الأقواسِ ما يزال مردودًا', $why !== '' ? mb_substr($why, 0, 46) : '');

/* ═══ ② والترتيبُ الجديدُ يمرُّ ═══════════════════════════════════════════ */
head('② الترتيبُ الجديدُ يقبله الفاحص');
$passed = false; $n = 0;
try {
    $rows = ems_tenant_db()->select('users', array(
        'columns' => array('id', 'name', 'role'),
        'where'   => array('status' => 'active'),
        'orderBy' => 'name',
    ));
    $passed = true; $n = count((array) $rows);
} catch (\Throwable $t) { $why = $t->getMessage(); }
chk($passed, '★ القراءةُ تمرُّ بلا رمية');
chk($n > 0, '★ المصدرُ قبلَ الطرحِ فيه أحياء', $n . ' مستخدمًا حيًّا في النطاق');

/* ═══ ③ الخدمةُ لا ترمي ولا تُخفي ═══════════════════════════════════════ */
head('③ الخدمةُ — لا رميةَ ولا عَلَمُ تعذُّر');
PW::$lastReadError = 'sentinel';
$free = PW::assignableUsers();
chk(is_array($free), '★ ترجع مصفوفةً لا رمية');
chk(PW::$lastReadError === '', '★ عَلَمُ التعذُّرِ نُظِّف ولم يُرفَع',
    PW::$lastReadError === '' ? 'فارغ' : PW::$lastReadError);
$profiles = PW::activeProfiles();
chk(count($profiles) > 0, '★ قائمةُ القوالبِ النافذةِ غيرُ فارغة', count($profiles) . ' قالبًا');

/* ═══ ④ والفراغُ مفسَّرٌ لا مجهول ════════════════════════════════════════ */
head('④ تفسيرُ عددِ القائمة — عن قصدٍ أم عن عطب');
$liveUsers  = $one("SELECT COUNT(*) FROM users WHERE company_id=4 AND status='active' AND is_deleted=0");
$withGrant  = $one("SELECT COUNT(DISTINCT g.user_id) FROM gov_authority_grants g
                     JOIN users u ON u.id=g.user_id
                    WHERE g.revoked_at IS NULL AND (g.valid_to IS NULL OR g.valid_to > NOW())
                      AND u.company_id=4 AND u.status='active' AND u.is_deleted=0");
$expected = $liveUsers - $withGrant;
fwrite(STDOUT, "     أحياء {$liveUsers} · لهم منحةٌ نافذةٌ {$withGrant} ⇒ المتوقَّع " . $expected . "\n");
chk(count($free) === $expected, '★ عددُ القائمةِ يطابق الحسابَ المستقل',
    'القائمة ' . count($free) . ' والمتوقَّع ' . $expected);

/* ═══ ⑤ والشاشةُ تقرأ العَلَمَ ولا تبتلع ═════════════════════════════════ */
head('⑤ الشاشةُ لا تبتلع التعذُّرَ صامتًا');
$scr = (string) @file_get_contents($ROOT . '/Governance/auth_grants.php');
chk(strpos($scr, 'lastReadError') !== false, '★ الشاشةُ تقرأ عَلَمَ التعذُّر');
chk(strpos($scr, 'عن قصد لا عن عطب') !== false, '★ وتُميِّز الفراغَ عن قصدٍ من الفراغِ عن عطب');

/* ═══ ⑥ ولا بقيّةَ للترتيبِ المعطوبِ في الإنتاج ══════════════════════════ */
head('⑥ مسحُ الإنتاجِ — صفرُ ترتيبٍ ذي أقواس');
$skip = array('/tests/', '/tools/', '/docs/', '/vendor/', '/storage/', '/.git/', '/node_modules/');
$hits = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT,
        FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS));
foreach ($it as $f) {
    $p = $f->getPathname();
    if (substr($p, -4) !== '.php') { continue; }
    foreach ($skip as $s) { if (strpos($p, $s) !== false) { continue 2; } }
    $src = (string) @file_get_contents($p);
    if (preg_match("~'orderBy'\\s*=>\\s*'[^']*\\(~", $src)) {
        $hits[] = substr($p, strlen($ROOT) + 1);
    }
}
chk(count($hits) === 0, '★ صفرُ ملفٍّ يمرّر ترتيبًا فيه أقواس',
    $hits ? implode(' · ', array_slice($hits, 0, 3)) : 'صفر');

/* ═══ ⑦ وسجلُّ اليومِ لا يزيد — بالفارقِ لا بالمجموع ═════════════════════
   ⛔ **والمجموعُ لا يصلح شاهدًا**: أخطاءُ ما قبلَ الإصلاحِ مسجَّلةٌ ولا تُمحى،
     فقياسُها يُرسِب أبدًا أو يُجبِر على محوِ شاهدِ الحادث. فالمقياسُ **ما
     يُضاف بنداءٍ جديد**: يُعَدُّ السجلُّ، ثمَّ تُنادى الخدمةُ، ثمَّ يُعَدُّ ثانيةً. */
head('⑦ سجلُّ الأخطاء — صفرُ خطأٍ يُضاف بنداءٍ جديد');
$log = $ROOT . '/logs/php_errors.log';
$countErr = function () use ($log) {
    if (!is_file($log)) { return 0; }
    $n = 0;
    $fh = @fopen($log, 'r');
    if (!$fh) { return -1; }
    while (($line = fgets($fh)) !== false) {
        if (strpos($line, 'invalid orderBy') !== false
            && strpos($line, 'assignableUsers') !== false) { $n++; }
    }
    fclose($fh);
    return $n;
};
$before = $countErr();
PW::assignableUsers();
PW::activeProfiles();
clearstatcache();
$afterN = $countErr();
fwrite(STDOUT, "     مسجَّلٌ قبلَ النداء {$before} · بعدَه {$afterN} (وما قبلَ الإصلاحِ شاهدُ الحادثِ ولا يُمحى)\n");
chk($before >= 0 && $afterN === $before, '★ صفرُ خطأِ ترتيبٍ يُضاف بالنداءِ الجديد',
    'الفارق ' . ($afterN - $before));

fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاحًا · {$FAIL} رسوبًا ══\n");
exit($FAIL === 0 ? 0 : 1);
