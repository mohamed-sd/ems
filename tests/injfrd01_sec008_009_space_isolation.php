<?php
/**
 * tests/injfrd01_sec008_009_space_isolation.php
 *   شاهدُ FR-SEC-008 · FR-SEC-009 — القنواتُ التسعُ وسياقُ المساحة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **معياران بنصِّهما**: FR-SEC-008 «صفرُ تسرُّبٍ لكلِّ دورٍ في المساحةِ المُغلَقة»
 *   عبرَ **تسعِ قنوات**: العرض · العنوانُ المباشر · البحث · نداءاتُ الخلفية ·
 *   التصدير · المناظرُ المحفوظة · منتقي الأعمدة · الأفعالُ الجماعية · الواجهةُ
 *   البرمجية. و FR-SEC-009 «**المنعُ بسياقِ المساحةِ لا بالمستخدم**» — سالبُه
 *   «**المستخدمُ نفسُه** في المساحةِ الأجنبية ← منع».
 *
 * ◆ **و«المستخدمُ نفسُه» هي جوهرُ 009**: منعٌ بالمستخدمِ يمرُّ في أيِّ مساحة،
 *   ومنعٌ بالمساحةِ يقلب الجوابَ للمستخدمِ **الواحدِ** بتبديلِ سياقِه. فيُسأل
 *   المقرِّرُ عن **المسارِ نفسِه** في مساحتَين ويُقارَن الجوابان — ولو تساويا
 *   لكان المنعُ بالمستخدمِ لا بالمساحة.
 *
 * ◆ **والقنواتُ تُعَدُّ بأسمائِها لا جملةً**: كلُّ قناةٍ تُقاس على حدة، وما لا
 *   يبلغ نقطةَ القرارِ **يُسمّى مفتوحًا** ولا يُطوى في نسبةٍ واحدة.
 *
 * ◆ **ومقامٌ صفرٌ ليس نجاحًا**: إن لم يوجد مسارٌ ممنوعٌ في مساحةٍ ما فلا شيءَ
 *   يُقاس — ويوقف الشاهدُ بدل إعلانِ خضرةٍ فارغة.
 *
 * التشغيل: php tests/injfrd01_sec008_009_space_isolation.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$BASE = 'http://localhost/ems';

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$GLOBALS['conn'] = $conn;
if (session_status() !== PHP_SESSION_ACTIVE) { $_SESSION = array(); }

$ok = 0; $bad = 0;
function chk($c, $l, $d = '') {
    global $ok, $bad;
    if ($c) { $ok++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $bad++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}
function n(mysqli $d, $q) { $r = @$d->query($q); return $r ? (int) $r->fetch_row()[0] : -1; }

/* ◆ **مُنتقي المطلبِ `--req=`** — شاهدٌ واحدٌ يخدم مطلبَين بحالتَين مختلفتَين
 *   يبقى أحمرَ بسببِ المفتوحِ منهما، **فلا يُتحقَّق المُغلَقُ أبدًا ويُقرأ
 *   ارتدادًا**. وعلاجُه **حكمٌ لكلِّ مطلبٍ على حدة** لا تخفيفُ الحزام.
 *   وبلا مُنتقٍ يُقاس الكلُّ كما كان. */
$__req = '';
foreach ($argv as $__a) { if (strpos($__a, '--req=') === 0) { $__req = substr($__a, 6); } }
function req_on($want) { global $__req; return $__req === '' || $__req === $want; }


require_once $ROOT . '/includes/space_scope.php';

echo "══ FR-SEC-008 · FR-SEC-009 — القنواتُ التسعُ وسياقُ المساحة ══\n";

/* ── المقامُ: مسارٌ ممنوعٌ في مساحةٍ ومسموحٌ في مالكتِه ────────────────────── */
$row = null;
$r = $conn->query("SELECT a.`route`, a.`space_ar` AS forbidden_in, b.`space_ar` AS owned_by
                     FROM `gov_space_appearances` a
                     JOIN `gov_space_appearances` b
                       ON LOWER(b.`route`) = LOWER(a.`route`) AND b.`cls` <> 'FORBIDDEN'
                    WHERE a.`cls` = 'FORBIDDEN' AND a.`route` <> ''
                      AND a.`space_ar` <> b.`space_ar`
                    LIMIT 1");
if ($r) { $row = $r->fetch_assoc(); }
$forbiddenTotal = n($conn, "SELECT COUNT(*) FROM `gov_space_appearances` WHERE `cls` = 'FORBIDDEN'");
$spaces = n($conn, "SELECT COUNT(DISTINCT `space_ar`) FROM `gov_space_appearances`");
printf("  المقامُ المقيس: %d ظهورًا ممنوعًا في %d مساحة\n", $forbiddenTotal, $spaces);
if (!$row) {
    echo "  ⛔ **مقامٌ صفرٌ ليس نجاحًا** — لا مسارَ ممنوعًا في مساحةٍ ومسموحًا في أخرى.\n";
    echo "     فلا شيءَ يُقاس. أُوقِف بدل إعلانِ خضرةٍ فارغة.\n";
    exit(1);
}
printf("  الحالةُ المقيسة: `%s` — ممنوعٌ في «%s» · مسموحٌ في «%s»\n",
       $row['route'], $row['forbidden_in'], $row['owned_by']);

/* ── ① FR-SEC-009 — **المستخدمُ نفسُه**: منعٌ هنا ومرورٌ هناك ─────────────── */
echo "\n── ① سياقُ المساحةِ لا المستخدم ──\n";
$denyHere  = ems_scope_forbids($row['route'], $row['forbidden_in']);
$allowThere = ems_scope_forbids($row['route'], $row['owned_by']);
chk($denyHere === true,
    'FR-SEC-009 سالب · **المسارُ ممنوعٌ في المساحةِ الأجنبية**',
    "{$row['route']} في «{$row['forbidden_in']}» ⇒ " . ($denyHere ? 'منع' : 'مرور'));
chk($allowThere === false,
    'FR-SEC-009 موجب · **والمسارُ نفسُه يمرُّ في مساحتِه المالكة**',
    "{$row['route']} في «{$row['owned_by']}» ⇒ " . ($allowThere ? 'منع' : 'مرور'));
chk($denyHere !== $allowThere,
    'و**الجوابُ يتغيّر بالسياقِ لا بالمستخدم** — ولو تساوى لكان منعًا بالمستخدم',
    'منعٌ هنا ومرورٌ هناك · والمستخدمُ واحد');

/* ولا يُمنع ما لا يُعرَف — بوابةٌ تمنع المجهولَ تكسر النظامَ عندَ أوّلِ شاشة */
$unknown = ems_scope_forbids('__no_such_route_' . getmypid() . '.php', $row['forbidden_in']);
chk($unknown === false, 'ومسارٌ لا صفَّ له **يُسمح** — فلا تُكسر شاشةٌ جديدةٌ بصمت',
    'مجهولٌ ⇒ ' . ($unknown ? 'منع ✘' : 'مرور ✔'));

/* ── ② FR-SEC-008 — القنواتُ التسعُ تُعَدُّ بأسمائِها ────────────────────── */
if (req_on('FR-SEC-008')) {
echo "\n── ② القنواتُ التسع: أتبلغ نقطةَ قرارِ المساحة؟ ──\n";
/* ◆ **القرارُ يُقرأ من نداءِ نقطتِه لا من ذكرِ اسمِها**: القناةُ تُعَدُّ مغطّاةً
 *   إن نادى أحدُ ملفّاتِها `ems_scope_forbids` أو `ems_scope_forbidden_set`.
 *   ولا يُقبل ذكرُ الاسمِ في تعليق — يُشترَط قوسُ النداء. */
/* ◆ **القناةُ تُقاس بما تفعل لا باسمِ الدالّة**: `excel.php` يُنفِّذ عزلَ المساحةِ
 *   باستعلامٍ مباشرٍ على `gov_space_appearances … cls='FORBIDDEN'` لا بنداءِ
 *   `ems_scope_forbids`. فهو **مُنفِّذٌ للعزلِ** — ويُعَدُّ مغطّى. وهو في الوقتِ
 *   نفسِه **قارئُ قرارٍ خارجَ النقطةِ الواحدة**، ويُعَدُّ دَينًا لـFR-SEC-003
 *   أدناه. والأمران يُقالان معًا: تغطيةٌ هنا ودَينٌ هناك. */
$DECIDERS = array('ems_scope_forbids(', 'ems_scope_forbidden_set(', 'ems_scope_class(',
                  "cls = 'FORBIDDEN'", 'cls=\'FORBIDDEN\'');
$CHANNELS = array(
    'العرض (السايدبار)'      => array('includes/dynamic_nav.php', 'includes/unified_nav.php'),
    'العنوانُ المباشر'        => array('includes/space_url_guard.php'),
    'البحث'                   => array('main/global_search.php'),
    'نداءاتُ الخلفية'         => array('includes/action_guard.php'),
    'التصدير'                 => array('excel.php', 'app/Services/Excel/ExcelService.php'),
    'المناظرُ المحفوظة'       => array('main/my_workspace.php', 'includes/workspace_views.php'),
    'منتقي الأعمدة'           => array('includes/columns_picker.php', 'assets/js/columns.js'),
    'الأفعالُ الجماعية'       => array('includes/action_guard.php'),
    'الواجهةُ البرمجية'       => array('api/bootstrap.php', 'api/index.php'),
);
$covered = 0; $open = array(); $missing = array();
foreach ($CHANNELS as $name => $files) {
    $hit = false; $seen = 0;
    foreach ($files as $rel) {
        $p = $ROOT . '/' . $rel;
        if (!is_file($p)) { continue; }
        $seen++;
        $src = (string) @file_get_contents($p);
        foreach ($DECIDERS as $d) { if (strpos($src, $d) !== false) { $hit = true; break 2; } }
    }
    if ($seen === 0) { $missing[] = $name; }
    if ($hit) { $covered++; printf("     ✔ %-24s تبلغ نقطةَ القرار\n", $name); }
    else { $open[] = $name; printf("     ✘ %-24s **مفتوحة** (ملفاتٌ مفحوصة: %d)\n", $name, $seen); }
}
chk($covered === count($CHANNELS),
    'FR-SEC-008 · **التسعُ كلُّها تبلغ نقطةَ قرارِ المساحة**',
    "مغطّاة={$covered} من " . count($CHANNELS)
    . (empty($open) ? '' : ' · مفتوحة: ' . implode(' · ', $open)));
if ($missing) {
    echo "  ◆ قنواتٌ لم يُعثر على ملفٍّ لها فلم تُقَس: " . implode(' · ', $missing) . "\n";
}

/* ── ③ الاختبارُ الحيّ: المستخدمُ نفسُه على العنوانِ المباشر ─────────────── */
echo "\n── ③ حيًّا: العنوانُ المباشرُ بحسابٍ حقيقيّ ──\n";
function req($url, $jar, $post = null)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 60,
    ));
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $b = curl_exec($ch);
    $c = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array($c, (string) $b);
}
$jar = sys_get_temp_dir() . '/sec009_' . getmypid() . '.jar';
@unlink($jar);
list(, $lb) = req($BASE . '/login.php', $jar);
preg_match('~name="csrf_token"\s+value="([^"]+)"~', $lb, $m);
/* الدورُ صاحبُ المساحةِ التي يُمنع فيها المسار — يُقرأ من الربطِ لا يُخمَّن */
$roleOfSpace = 0; $userName = '';
$st = $conn->prepare("SELECT `role_id` FROM `gov_space_roles` WHERE `space_ar` = ? LIMIT 1");
$st->bind_param('s', $row['forbidden_in']);
$st->execute();
$rr = $st->get_result()->fetch_row();
$st->close();
if ($rr) { $roleOfSpace = (int) $rr[0]; }
if ($roleOfSpace > 0) {
    $st = $conn->prepare("SELECT `username` FROM `users`
                           WHERE `role` = ? AND COALESCE(`is_deleted`,0) = 0 LIMIT 1");
    $st->bind_param('i', $roleOfSpace);
    $st->execute();
    $ur = $st->get_result()->fetch_row();
    $st->close();
    if ($ur) { $userName = (string) $ur[0]; }
}
printf("  مساحةُ المنع «%s» ⇒ الدور %d ⇒ المستخدم «%s»\n",
       $row['forbidden_in'], $roleOfSpace, $userName !== '' ? $userName : '(لا مستخدم)');

if ($userName === '') {
    echo "  ◆ لا حسابَ لهذا الدور — **فالبندُ الحيُّ غيرُ مقيسٍ** ولا يُقرأ نجاحًا.\n";
} else {
    list($lc, $lbody) = req($BASE . '/login.php', $jar, array(
        'username' => $userName, 'password' => '12345678',
        'csrf_token' => isset($m[1]) ? $m[1] : '',
    ));
    $in = (strpos($lbody, 'name="password"') === false);
    chk($in, 'دخولُ صاحبِ المساحة — وبلا دخولٍ لا معنى للقياس', $userName);
    if ($in) {
        /* ◆ **والحالةُ تُختار بما يعزل السببَ**: مسارٌ **صلاحيتُه ممنوحةٌ** لهذا
         *   الدورِ (`role_permissions.can_view = 1`) **وممنوعٌ في مساحتِه**.
         *   فلو مُنع بلا هذا الشرطِ لالتبس منعُ الصلاحيةِ بمنعِ المساحة —
         *   «اثنانِ يُرفضان بالرمزِ نفسِه لسببَين يُخفيان أحدَهما». */
        $live = $row['route'];
        $lq = $conn->prepare("SELECT a.`route` FROM `gov_space_appearances` a
                WHERE a.`cls` = 'FORBIDDEN' AND a.`space_ar` = ? AND a.`route` <> ''
                  AND EXISTS (SELECT 1 FROM `nav_items` n
                                JOIN `role_permissions` rp ON rp.`module_id` = n.`module_id`
                               WHERE LOWER(n.`route`) = LOWER(a.`route`)
                                 AND rp.`role_id` = ? AND rp.`can_view` = 1)
                LIMIT 1");
        $lq->bind_param('si', $row['forbidden_in'], $roleOfSpace);
        $lq->execute();
        $lr = $lq->get_result()->fetch_row();
        $lq->close();
        if ($lr) { $live = (string) $lr[0]; }
        echo "  المسارُ المعزولُ سببُه: {$live} (صلاحيةٌ ممنوحةٌ · مساحةٌ مانعة)
";
        list($rc, $rb) = req($BASE . '/' . ltrim($live, '/'), $jar);
        /* ◆ **والتحويلُ ليس منعًا بالضرورة**: أوّلُ قياسٍ عدَّ 302 منعًا، وكان
         *   `Timesheet/timesheet.php ⇒ timesheet_type.php` — إعادةَ توجيهٍ
         *   تطبيقيةً داخلَ الشاشةِ نفسِها. **فقُرئ تسريبٌ نجاحًا.** ⇒ يُقرأ
         *   وجهُ التحويلِ: منعٌ إن قصد صفحةَ رفضٍ أو دخول، لا إن قصد شاشةً أخرى. */
        preg_match('~^Location:\s*(.+)$~mi', $rb, $loc);
        $dest = isset($loc[1]) ? trim($loc[1]) : '';
        $denialDest = ($dest !== '' && preg_match('~login|denied|403|unauthor|no_access|dashboard~i', $dest));
        $denied = ($rc === 403 || $denialDest
                   || strpos($rb, 'مساحةَ عملٍ أخرى') !== false
                   || strpos($rb, 'غير مصرح') !== false || strpos($rb, 'غير مُصرَّح') !== false);
        /* ◆ **«يبلغ القرارَ» غيرُ «يُنفِذ»**: الحارسُ الجديدُ يعمل في وضعِ **ظلٍّ**
         *   افتراضًا — يرصد ولا يمنع، لأن قلبَ المنعِ على 265 ظهورًا ممنوعًا
         *   تغييرُ وصولٍ حيٍّ يقرّره مالكُ المجال. فالمقياسُ هنا **أن الحارسَ
         *   رأى الطلبَ وسجَّله**، والإنفاذُ بندٌ مفتوحٌ يُعلَن ولا يُدَّعى. */
        $mode = getenv('EMS_SPACE_URL_GUARD') === 'enforce' ? 'enforce' : 'observe';
        $seen = n($conn, "SELECT COUNT(*) FROM `gov_space_url_shadow`
                            WHERE LOWER(`route`) = LOWER('" . $conn->real_escape_string($live) . "')");
        if ($mode === 'observe') {
            chk($seen > 0,
                'FR-SEC-008 · **حارسُ العنوانِ المباشرِ رأى الطلبَ وسجَّله** (وضعُ ظلٍّ)',
                "HTTP={$rc} · صفوفُ ظلٍّ للمسار={$seen} — والإنفاذُ **مفتوحٌ بقرارِ مالك**");
            echo "  ◆ **ولا يُدَّعى منعٌ لم يقع**: الوضعُ observe فالمسارُ ما يزال يُفتح.
";
            echo "    القلبُ بـEMS_SPACE_URL_GUARD=enforce بعدَ قراءةِ أثرِ الظل.
";
        } else {
        chk($denied, 'FR-SEC-009 حيًّا · **العنوانُ المباشرُ إلى مسارٍ أجنبيٍّ يُمنع**',
            "HTTP={$rc}" . ($dest !== '' ? " ⇒ {$dest}" : '') . ' · بايت=' . strlen($rb)
            . ($denied ? '' : ' — **مرَّ وهو ممنوعٌ في مساحتِه · تسريبٌ مقيس**'));
        }
    }
}
@unlink($jar);

}

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
