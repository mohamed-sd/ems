<?php
/**
 * tests/injfix01_default_open_scope_proof.php
 *   الانفتاحُ الافتراضيُّ مقلوبٌ — INJ-FIX-01 · GAP-22 · GAP-30
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **عيبان من جنسٍ واحد**: «ما لم يُصنَّف يُفتح» — والقاعدةُ منعٌ ما لم يُصرَّح.
 *   ① `fin_project_scope`: دورٌ ليس 5 ولا 6 ⇒ `null` ⇒ **يرى صفوفَ الشركةِ كلَّها**.
 *   ② `gs_can_open`: شاشةٌ بلا صفٍّ في `modules` ⇒ **تُفتح للجميع** — وتعليقُها
 *      يزعم أنها «مرآةُ حارسِ التنفيذ»، وحارسُ التنفيذِ شُدِّد فصار يمنعها
 *      بـ`GOV-PERM-404-MODULE`. **فالمرآةُ كانت تصف حارسًا تغيَّر.**
 *
 * ◆ **وطرفان يُقاسان**: أن الجديدَ يُمنَع، **وأن القائمَ لم يفقد**. فإصلاحُ
 *   انفتاحٍ يُفرِغ شاشةً لعشرةِ أدوارٍ تملكها بحقٍّ ليس إصلاحًا بل فقدًا.
 *
 * التشغيل: php tests/injfix01_default_open_scope_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$_SERVER['SCRIPT_NAME'] = '/ems/main/dashboard.php';
require_once $ROOT . '/config.php';
require_once $ROOT . '/Finance/fin_helpers.php';

$pass = 0; $fail = 0;
function ok($c, $l, &$p, &$f, $d = '') { if ($c) { $p++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; } else { $f++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; } }

echo "════ الانفتاحُ الافتراضيُّ — GAP-22 · GAP-30 ════\n";

/* ══════════ ① نطاقُ المشروع — القائمةُ الصريحة ═══════════════════════════ */
echo "\n── ① نطاقُ المشروعِ المالي (GAP-22) ──\n";
ok(defined('FIN_SCOPE_PROJECT_ROLES') && defined('FIN_SCOPE_COMPANY_ROLES'),
   'القائمتان مُعلَنتان صراحةً في الكود', $pass, $fail,
   'مشروع=[' . FIN_SCOPE_PROJECT_ROLES . '] · شركة=[' . FIN_SCOPE_COMPANY_ROLES . ']');

$ctx = function ($role, $super = false, $uid = 0) {
    return array('role' => (string) $role, 'is_super' => $super, 'user_id' => $uid);
};

/* ◆ الدورُ غيرُ المصنَّفِ — الجوهرُ المقيس */
$NEW_ROLE = '9999';
$v = fin_project_scope($conn, $ctx($NEW_ROLE));
ok($v === -1, '**دورٌ جديدٌ غيرُ مصنَّفٍ يُرجع صفرَ صفّ**', $pass, $fail,
   'النطاق=' . var_export($v, true) . ' (كان `null` = كلُّ الصفوف)');

/* ◆ ولا فقدَ لمن كان يرى — الطرفُ الآخر */
$companyRoles = explode(',', FIN_SCOPE_COMPANY_ROLES);
$lost = array();
foreach ($companyRoles as $r) {
    if (fin_project_scope($conn, $ctx($r)) !== null) { $lost[] = $r; }
}
ok(count($lost) === 0, 'كلُّ دورٍ مصنَّفٍ «شركة» ما يزال بلا تصفية — صفرُ فقد', $pass, $fail,
   count($lost) ? 'فقد: ' . implode(' · ', $lost) : count($companyRoles) . ' دورًا');

/* ◆ والمقيَّدُ بمشروعِه ما يزال مقيَّدًا (لا انفتاح) */
foreach (explode(',', FIN_SCOPE_PROJECT_ROLES) as $r) {
    $v = fin_project_scope($conn, $ctx($r, false, 0));
    ok($v !== null, "الدور {$r} ما يزال مقيَّدًا بمشروعِه لا مفتوحًا", $pass, $fail,
       'النطاق=' . var_export($v, true));
}

/* ◆ والمديرُ الأعلى بلا نطاق — سلوكٌ لم يتغيّر */
ok(fin_project_scope($conn, $ctx('1', true)) === null,
   'المديرُ الأعلى بلا تصفية — لم يتغيّر', $pass, $fail);

/* ══════════ ② البحثُ العام — الشاشةُ غيرُ المسجَّلة (GAP-30) ═══════════════ */
echo "\n── ② البحثُ العامّ (GAP-30) ──\n";

/* ◆ **ولا يُستدعى الملفُّ كلُّه**: `main/global_search.php` شاشةٌ تُصيَّر وتُعيد
     التوجيه. فتُقرأ الدالةُ من مصدرِها وتُقاس بنيتُها — والقياسُ الوظيفيُّ
     يليه على مقامٍ حيٍّ من `modules`. */
$src = (string) @file_get_contents($ROOT . '/main/global_search.php');

/** كودٌ بلا تعليقات — فالتعليقُ يصف ولا ينفّذ.
 *  ◆ **فخٌّ وقع في أولِ تشغيل**: البندُ «لا أثرَ للحكمِ المنفتحِ القديم» رسب
 *    لأن **التعليقَ التوثيقيَّ يقتبس الحكمَ القديمَ نصًّا** ليشرح ما كان.
 *    فرصد الفاحصُ اقتباسَه هو. والقياسُ على الكودِ المنفَّذِ وحدَه — وإلا
 *    عُوقب التوثيقُ وكوفئ الصمت. */
$exec = '';
foreach (token_get_all($src) as $tk) {
    if (is_array($tk)) {
        if ($tk[0] === T_COMMENT || $tk[0] === T_DOC_COMMENT) { continue; }
        $exec .= $tk[1];
    } else { $exec .= $tk; }
}

ok(strpos($exec, "if (\$p['id'] === null) { return false; }") !== false,
   '**الشاشةُ غيرُ المسجَّلةِ لا تُفتح** — الحكمُ مقلوبٌ إلى منع', $pass, $fail);
ok(strpos($exec, "return (\$p['id'] === null) || !empty(\$p['can_view']);") === false,
   'ولا أثرَ للحكمِ المنفتحِ القديم **في الكودِ المنفَّذ**', $pass, $fail,
   'قِيس على ' . number_format(strlen($exec)) . ' حرفًا بلا تعليقات (الأصل '
   . number_format(strlen($src)) . ')');

/* ◆ والإعفاءُ صار من `can_view` لا من التسجيل */
$exemptAfterCheck = preg_match(
    '/\$p\s*=\s*check_page_permissions.*?\$p\[.id.\]\s*===\s*null.*?in_array\(\$screen,\s*\$exempt/s', $src);
ok($exemptAfterCheck === 1,
   'الإعفاءُ يقع **بعدَ** فحصِ التسجيل — فلا يصير بابًا خلفيًّا', $pass, $fail);

/* ◆ ولا فقدَ: كلُّ أوجهةِ البحثِ مسجَّلةٌ فعلًا */
$dest = array();
if (preg_match_all("/=>\s*array\('[^']*',\s*'[^']*',\s*'([^']+\.php)'\)/", $src, $m)) {
    $dest = array_unique($m[1]);
}
$unreg = array();
foreach ($dest as $d) {
    $st = $conn->prepare("SELECT MIN(id) FROM modules WHERE code = ?");
    $st->bind_param('s', $d); $st->execute(); $st->bind_result($id); $st->fetch(); $st->close();
    if ($id === null) { $unreg[] = $d; }
}
ok(count($dest) > 0, 'قُرئت أوجهةُ البحثِ من المصدر', $pass, $fail, count($dest) . ' وجهة');
ok(count($unreg) === 0, 'كلُّ وجهةٍ مسجَّلةٌ في `modules` — فالتشديدُ بلا فقد', $pass, $fail,
   count($unreg) ? 'غيرُ مسجَّل: ' . implode(' · ', $unreg) : '—');

/* ◆ وحارسُ التنفيذِ الذي تزعم الدالةُ أنها مرآتُه — يُقاس أنه مغلقٌ فعلًا */
$guard = (string) @file_get_contents($ROOT . '/includes/permissions_helper.php');
ok(strpos($guard, 'GOV-PERM-404-MODULE') !== false,
   'وحارسُ التنفيذِ يمنع غيرَ المسجَّلةِ فعلًا — فالمرآةُ تطابقه الآن', $pass, $fail);

echo "───────────────────────────────────────────────────────────────\n";
echo ($fail === 0 ? "✔" : "✘") . " النتيجة: نجح {$pass} · رسب {$fail}\n";
exit($fail === 0 ? 0 : 1);
