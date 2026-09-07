<?php
/**
 * tests/perm01_render_vs_guard.php — الرابطُ المُصيَّرُ وحكمُ الحارس (PERM-01 §7-④)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلبُ نصًّا**: «فرقٌ بين الرابطِ المباشرِ وحكمِ الحارس = صفر». ومعناه:
 *   ما يراه المستخدمُ في قائمتِه يفتحه — ولا رابطَ يقود إلى 403.
 *
 * ⛔ **والقياسُ بالتصييرِ الحيِّ لا بجداولِه**: القائمةُ تُبنى بمُصيِّرِها نفسِه
 *   لا بقراءةِ `nav_items` مباشرةً — فصفٌّ قائمٌ في الجدولِ قد لا يُصيَّر، وحكمٌ
 *   على غيرِ المُصيَّرِ حكمٌ على غيرِ محلِّه.
 *
 * ⛔ **والأسطحُ تُعَدُّ ولا تُذكَر (§⓪)**: كان هذا الحاجبُ يمسح `navarch_render`
 *   **وحدَه** فأخرج «11 نجاح · 0 رسوب · PASS» بينما في الشجرةِ نفسِها **918
 *   رابطًا بلا حراسةٍ** في `getDynamicNavLinks` و**15 بلاطةً ميتةً** في
 *   `roleBoardQuickActions`. فصار §⓪ **يمسح المصدرَ** عن كلِّ منتِجِ روابطٍ
 *   ويُطابقه بسجلٍّ كلُّ بندٍ فيه بحكمٍ وسبب — و**سطحٌ جديدٌ خارجَ السجلِّ
 *   يُرسِّب ولو كان سليمًا** حتى يُصنَّف عمدًا.
 *
 * ⛔ **ولكلِّ سطحٍ حارسُ مفردةٍ على حدة**: مجموعٌ ضخمٌ قد يُخفي سطحًا مفحوصُه
 *   صفرٌ فيمرُّ حكمُه بلا قياس — فيُشترط أن يُخرِج كلُّ مكنوسٍ روابطَ فعلًا.
 *
 * ⛔ **وصفرٌ بلا ضابطٍ سالبٍ أخضرُ كاذب**: مسبارٌ لا يرى شيئًا يُخرج صفرًا
 *   كمسبارٍ لا عطبَ أمامه. فيُكسَر الحالُ عمدًا — يُطفأ بندُ قالبٍ لرابطٍ
 *   مُصيَّر — ويُتحقَّق أنَّ العدَّ **تحرَّك**، ثمَّ يُستعاد.
 *
 * ⛔ **والاستعادةُ في `register_shutdown_function`**: تُسجَّل قبلَ التغييرِ فلا
 *   تُعلَّق على نتيجةِ الفحصِ ولا على اكتمالِ السكربت.
 *
 * التشغيل: php tests/perm01_render_vs_guard.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/permissions_helper.php';
require_once dirname(__DIR__) . '/includes/navarch_renderer.php';
require_once dirname(__DIR__) . '/includes/unified_nav.php';
require_once dirname(__DIR__) . '/includes/dynamic_nav.php';
require_once dirname(__DIR__) . '/includes/role_board.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0; $HOLD = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function chk($c, $m, $d = '') { $c ? ok($m . ($d !== '' ? " — {$d}" : '')) : bad($m . ($d !== '' ? " — {$d}" : '')); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }
/** بندٌ لا يُقاس اليوم بسببٍ **مسمًّى** — لا يُمرَّر ولا يُرسَّب، ويبقى مرئيًّا. */
function hold($m, $why) { global $HOLD; $HOLD++; fwrite(STDOUT, "  ⏸ معلَّق: {$m}\n       السبب: {$why}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
fwrite(STDOUT, "\n══ PERM-01 §7-④ — لا رابطَ يُصيَّر ثمَّ يردُّه بابُه ══\n");

/** خريطةُ المسارِ المُطبَّعِ ⇐ معرِّفِ الوحدة. */
$modByRoute = array();
$modCodeById = array();
$r = $conn->query("SELECT id, code FROM modules WHERE code LIKE '%.php'");
while ($x = $r->fetch_row()) {
    $modByRoute[strtolower(navarch_norm_route($x[1]))] = (int) $x[0];
    $modCodeById[(int) $x[0]] = (string) $x[1];
}

/**
 * يمسح المستخدمين ويعيد [عددُ المفحوص, عددُ المردود, أمثلة, غيرُ المحلول].
 */
/* ── **قوّادُ الأسطح**: لكلِّ سطحٍ مكنوسٍ دالّةٌ تُخرِج مساراتِه لمستخدمٍ بعينِه.
     ◆ **ويُنادى السطحُ نفسُه لا يُعاد بناءُ استعلامِه** — فإعادةُ الكتابةِ تقيس
       ما كتبناه نحن لا ما يراه المستخدم. (وقد وقع: تطبيعٌ محلّيٌّ للمسارِ
       أعطى «صفرًا — تطابقٌ تامّ» وفي الشجرةِ 454 رابطًا ميتًا، لأنَّ مفاتيحَ
       المُصيِّرِ الحاكمِ بلا `.php` فلم يطابقها شيء.) */
$DRIVERS = array(
  'navarch_render' => function ($conn, $u) {
      $ws = navarch_role_workspace($conn, (int) $u['role']);
      $tree = navarch_render($conn, $ws, (int) $u['role'], array('include_shell' => false));
      $o = array();
      foreach ((isset($tree['groups']) ? $tree['groups'] : array()) as $g) {
          foreach ((isset($g['items']) ? $g['items'] : array()) as $it) { $o[] = $it['route']; }
      }
      return $o;
  },
  'navarch_authorized_routes' => function ($conn, $u) {
      return array_keys(navarch_authorized_routes($conn, (int) $u['role']));
  },
  'getUnifiedNavItems' => function ($conn, $u) {
      $o = array();
      foreach (getUnifiedNavItems($conn, (int) $u['role']) as $it) { $o[] = (string) ($it['route'] ?? ''); }
      return $o;
  },
  'getUnifiedQuickItems' => function ($conn, $u) {
      $o = array();
      foreach (getUnifiedQuickItems($conn, (int) $u['role']) as $it) { $o[] = (string) ($it['route'] ?? ''); }
      return $o;
  },
  'getDynamicNavLinks' => function ($conn, $u) {
      $o = array();
      foreach (getDynamicNavLinks($conn, (int) $u['role']) as $it) { $o[] = (string) ($it['code'] ?? ''); }
      return $o;
  },
  'roleBoardQuickActions' => function ($conn, $u) {
      $o = array();
      foreach (roleBoardQuickActions($conn, (int) $u['role'], (int) $u['id'], 3) as $it) { $o[] = (string) ($it['route'] ?? ''); }
      return $o;
  },
);

/**
 * يمسح المستخدمين عبر **كلِّ سطحٍ مكنوس**، ويعيد
 * [المفحوص, المردود, أمثلة, غيرُ المحلول, عددُ المستخدمين, تفصيلٌ لكلِّ سطح].
 */
$sweep = function ($conn, $modByRoute, $onlyUser = 0) use ($DRIVERS) {
    $users = array();
    $w = $onlyUser > 0 ? " AND id = " . (int) $onlyUser : '';
    $r = $conn->query("SELECT id, role, company_id FROM users
                        WHERE is_deleted=0 AND status='active' AND company_id=4{$w} ORDER BY id");
    while ($x = $r->fetch_assoc()) { $users[] = $x; }
    $checked = 0; $denied = 0; $unres = 0; $ex = array(); $per = array();
    $prev = isset($_SESSION['user']) ? $_SESSION['user'] : null;
    foreach ($users as $u) {
        $_SESSION['user'] = array('id' => (int) $u['id'], 'role' => (string) $u['role'],
                                  'company_id' => (int) $u['company_id'], 'name' => 'render probe');
        $GLOBALS['__uxui_cur_role'] = (int) $u['role'];
        foreach ($DRIVERS as $sfc => $drive) {
            if (!isset($per[$sfc])) { $per[$sfc] = array(0, 0); }
            $routes = array();
            try { $routes = $drive($conn, $u); }
            catch (\Throwable $t) { $routes = array(); }
            foreach ($routes as $rt) {
                $k = strtolower(navarch_norm_route($rt));
                if ($k === '') { continue; }
                if (!isset($modByRoute[$k])) { $unres++; continue; }
                $checked++; $per[$sfc][0]++;
                if (empty(get_module_permissions($conn, $modByRoute[$k])['can_view'])) {
                    $denied++; $per[$sfc][1]++;
                    if (count($ex) < 5) { $ex[] = '#' . $u['id'] . ' ⟵ ' . $sfc . ' ⟵ ' . $rt; }
                }
            }
        }
    }
    if ($prev === null) { unset($_SESSION['user']); } else { $_SESSION['user'] = $prev; }
    return array($checked, $denied, $ex, $unres, count($users), $per);
};

/* ═══════════════════════════════════════════════════════════════════════════
   ⓪ **الكاشفُ — يُعدِّد الأسطحَ ولا يستوردها قائمة**
   ═══════════════════════════════════════════════════════════════════════════
   ⛔ **العطبُ الذي يرفعه، مقيسًا في الشجرةِ نفسِها**: كان هذا الحاجبُ يمسح
     `navarch_render` **وحدَه**، فأخرج «11 نجاح · 0 رسوب · PASS» بينما في
     الشجرةِ **918 رابطًا بلا حراسةٍ** في `getDynamicNavLinks` و**15 بلاطةً
     ميتةً** في `roleBoardQuickActions`. حاجبٌ أخضرُ ونظامٌ ينزف.
   ◆ **والسببُ منهجيٌّ لا برمجيّ**: الأسطحُ كانت **تُذكَر** لا **تُعدَّ**. وستُّ
     نسخٍ من عطبٍ واحدٍ وُجدت اثنتانِ منها بالبحثِ عن أسماءِ دوالَّ معروفةٍ —
     والبحثُ عن الفحصِ الخطأ **لا يجد غيابَ الفحص** (وكانت الخامسةُ، وهي
     الأسوأ، لا تفحص شيئًا أصلًا فلم تحملْ أيَّ اسمٍ يُبحَث عنه).
   ⇒ **فيُمسَح المصدرُ عن كلِّ دالّةٍ تقرأ سجلَّ الروابطِ وتُخرج مسارًا وتُرجع
     مجموعة**، ويُطابَق المكشوفُ بسجلٍّ **كلُّ بندٍ فيه بسببٍ مكتوب**. ودالّةٌ
     جديدةٌ خارجَ السجلِّ **تُرسِّب فورًا ولو كانت سليمة** — حتى تُفحَص وتُصنَّف
     عمدًا. فالسابعُ لا يختبئ في الخُضرة.
   ⚠ **وحدُّ الكاشفِ معلَنٌ**: يرى ما يُبنى **من السجلِّ**؛ والقوائمُ **الصلبةُ**
     في الشيفرة (بلاطاتُ `my_workspace` الإحدى عشرة) لا يراها — فتُسجَّل يدويًّا
     في السجلِّ نفسِه بوسمِ `manual`، ولا يُترك الحدُّ بلا ذكر. */
head('⓪ الكاشفُ — أيُّ سطحٍ يُنتج روابطَ شاشاتٍ في الشجرة؟');

$ROOT = dirname(__DIR__);
$scan = function () use ($ROOT) {
    $files = array();
    foreach (array('/includes', '/app', '/main') as $d) {
        if (!is_dir($ROOT . $d)) { continue; }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($ROOT . $d, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && strtolower($f->getExtension()) === 'php') { $files[] = $f->getPathname(); }
        }
    }
    foreach (new DirectoryIterator($ROOT) as $f) {
        if ($f->isFile() && strtolower($f->getExtension()) === 'php') { $files[] = $f->getPathname(); }
    }
    sort($files);
    $found = array();
    foreach ($files as $path) {
        $src = @file_get_contents($path);
        if ($src === false) { continue; }
        if (!preg_match_all('~^\s*function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(~m', $src, $m, PREG_OFFSET_CAPTURE)) { continue; }
        $n = count($m[1]);
        for ($i = 0; $i < $n; $i++) {
            $s = $m[0][$i][1];
            $e = ($i + 1 < $n) ? $m[0][$i + 1][1] : strlen($src);
            $body = substr($src, $s, $e - $s);
            $reads = stripos($body, 'FROM nav_items') !== false
                  || stripos($body, 'FROM `nav_items`') !== false
                  || stripos($body, 'FROM modules') !== false
                  || stripos($body, "select('modules'") !== false
                  || stripos($body, 'nav_workspace_placements') !== false;
            if (!$reads) { continue; }
            if (!preg_match('~\broute\b|\bm\.code\b|[\'"]code[\'"]~i', $body)) { continue; }
            if (!preg_match('~return\s+\$[A-Za-z_]~', $body)
                && !preg_match('~\$out\[\]|\$links\[\]|\$items\[\]|\$rows\[\]~', $body)) { continue; }
            $found[$m[1][$i][0]] = str_replace('\\', '/', str_replace($ROOT . DIRECTORY_SEPARATOR, '', $path));
        }
    }
    return $found;
};

/* ── سجلُّ الأسطح: كلُّ بندٍ **بحكمٍ وسبب** ─────────────────────────────────
     swept    : يُقاد بجلسةِ مستخدمٍ حيٍّ ويُقارَن بالحارس (يُرسِّب على الفرق)
     indirect : مُصيِّرٌ روابطُه كلُّها من سطحٍ مكنوسٍ أعلاه — فلا مصدرَ مستقلًّا له
     not_nav  : ليس سطحَ روابطٍ للمستخدم (خريطةٌ · مفرداتٌ · لوحةُ حوكمة)
     manual   : سطحٌ حقيقيٌّ لا يراه الكاشفُ (قائمةٌ صلبةٌ في الشيفرة) */
$LEDGER = array(
  'navarch_render'            => array('swept',   'المُصيِّرُ الحاكم — شجرةُ السايدبارِ التي يراها كلُّ مستخدمٍ اليوم'),
  'navarch_authorized_routes' => array('swept',   'مجموعةُ التصريحِ التي يبني بها الحاكمُ — قِيس فيها 454 رابطًا ميتًا'),
  'getUnifiedNavItems'        => array('swept',   'مُصيِّرُ السايدبارِ القديم — يُبلَغ متى لم يُنتج الحاكمُ بندًا'),
  'getUnifiedQuickItems'      => array('swept',   'بلاطاتُ الوصولِ السريعِ في الرئيسية'),
  'getDynamicNavLinks'        => array('swept',   'سايدبارُ المسارِ الإرثيِّ (الدوران 34 و35) — قِيس فيها 78 رابطًا ميتًا'),
  'roleBoardQuickActions'     => array('swept',   'بلاطاتُ «الإنشاءِ السريعِ» في لوحةِ الدور — قِيس فيها 15 بلاطةً ميتة'),
  'printEmsTenGroupNav'       => array('indirect','طابعٌ لا مُنتِج: بنودُه تأتي من getUnifiedNavItems المكنوسةِ أعلاه، ووسمُ المنعِ فيه من طبقةِ القوالبِ نفسِها'),
  'render_header_action'      => array('not_nav', 'أزرارُ فعلٍ في ترويسةِ الشاشةِ الحالية — لا قائمةَ شاشاتٍ، وحارسُها فعلُها لا رابطُها'),
  'ems_route_workspace_map'   => array('not_nav', 'خريطةُ مسارٍ ⇐ مساحة — مُدخَلٌ للترشيحِ لا مخرَجٌ للمستخدم'),
  'ems_layer_vocabulary'      => array('not_nav', 'مفرداتُ طبقاتِ الصلاحيةِ — نصٌّ لا روابط'),
  'perm_visible_modules_like' => array('not_nav', 'قارئُ لوحاتِ الحوكمةِ للمقارنةِ بالسجلِّ القديم — تقريرٌ لا ملاحة'),
  'ems_sod_codes_of_role_with'=> array('not_nav', 'رموزُ فصلِ الواجباتِ لدورٍ — فحصُ تعارضٍ لا قائمةُ عرض'),
  'dashboard_gate_scalar'     => array('not_nav', 'مساعدُ استعلامٍ عدديٍّ في الرئيسية — يُرجِع رقمًا لا روابط'),
  'my_workspace_tiles'        => array('manual',  'إحدى عشرةَ بلاطةً **صلبةً** في main/my_workspace.php — لا يراها الكاشفُ فتُسجَّل هنا'),
);

$detected = $scan();
chk(count($detected) >= 10, 'الكاشفُ مسح ووجد مرشَّحين — وصفرُ مكشوفٍ عطبٌ في الأداةِ لا نظافةٌ في الشجرة',
    'مكشوف=' . count($detected));

$unregistered = array();
foreach ($detected as $fn => $file) { if (!isset($LEDGER[$fn])) { $unregistered[] = "{$fn} ({$file})"; } }
chk(count($unregistered) === 0,
    '★★★ **كلُّ سطحٍ مكشوفٍ مسجَّلٌ بحكمٍ وسبب** — والجديدُ يُرسِّب حتى يُصنَّف عمدًا',
    count($unregistered) === 0 ? ('مكشوف=' . count($detected) . ' · مسجَّل=' . count($LEDGER))
                               : ('غيرُ مسجَّل: ' . implode(' · ', $unregistered)));

$stale = array();
foreach ($LEDGER as $fn => $d) {
    if ($d[0] === 'manual') { continue; }
    if (!isset($detected[$fn])) { $stale[] = $fn; }
}
chk(count($stale) === 0, 'ولا بندَ في السجلِّ فقدَ دالّتَه — فالسجلُّ لا يتقادم بصمت',
    $stale ? implode(' · ', $stale) : 'صفر');

$sweptNames = array();
foreach ($LEDGER as $fn => $d) { if ($d[0] === 'swept') { $sweptNames[] = $fn; } }
fwrite(STDOUT, '     أسطحٌ تُكنَس: ' . count($sweptNames) . ' · غيرُ ملاحةٍ بسببٍ مكتوب: '
    . (count($LEDGER) - count($sweptNames)) . "\n");

head('① المسحُ الحيُّ — كلُّ مستخدمٍ حيٍّ × كلُّ سطحٍ مكنوس');
list($checked, $denied, $ex, $unres, $nUsers, $per) = $sweep($conn, $modByRoute, 0);
chk($nUsers >= 50, 'مستخدمون أحياءُ مُسحوا — وصفرُ ممسوحٍ ليس نتيجة', "عدد={$nUsers}");
chk($checked >= 500, 'روابطُ مُصيَّرةٌ فُحصت فعلًا', "عدد={$checked}");
chk($unres === 0, 'ولا مسارَ مُصيَّرٍ يعجز عن الحلِّ إلى وحدة', "غيرُ محلول={$unres}");

/* ⛔ **وحارسُ المفردةِ لكلِّ سطحٍ على حدة**: مجموعُ المفحوصِ قد يكون ضخمًا
     وسطحٌ فيه صفرٌ — فيمرُّ حكمُه بلا قياس. فيُشترط أن **يُخرِج كلُّ سطحٍ
     مكنوسٍ روابطَ فعلًا**، وإلّا فقوّادُه مكسورٌ لا الشجرةُ نظيفة. */
$mute = array();
foreach ($per as $sfc => $pr) { if ($pr[0] === 0) { $mute[] = $sfc; } }
chk(count($mute) === 0, 'وكلُّ سطحٍ مكنوسٍ أخرج روابطَ فعلًا — فلا سطحَ يمرُّ بصفرٍ مقيس',
    $mute ? ('صامتٌ: ' . implode(' · ', $mute)) : 'ستَّةٌ من ستّة');

foreach ($per as $sfc => $pr) {
    fwrite(STDOUT, sprintf("     %-30s مفحوص=%-6d مردود=%d\n", $sfc, $pr[0], $pr[1]));
}
chk($denied === 0, '★★ **لا رابطَ يُصيَّر ثمَّ يردُّه بابُه — في أيِّ سطح**',
    $denied === 0 ? "صفرٌ من {$checked}" : implode(' | ', $ex));

/* ── ①-ب السطحُ اليدويُّ: بلاطاتُ «مساحة عملي» ─────────────────────────────
     ◆ **تُقرأ من المصدرِ لا تُنسَخ**: القائمةُ صلبةٌ في `main/my_workspace.php`،
       فتُنتزَع منه بالمطابقةِ — ونسخُها هنا يجعل الحاجبَ يقيس ما كتبناه نحن.
     ⏸ **وحكمُها معلَّقٌ بقرارِ المالكِ لا برأيِ الحاجب**: الملفُّ يعلن هذه
       الشاشاتِ «إلزاميّةً لكلِّ حسابٍ بلا استثناء» (NAV-01 v6 §7)، والحارسُ
       يقول «لا شاشةَ خارجَ القالب». وهذا **تعارضُ قرارَين** لا عطبٌ برمجيّ —
       فيُقاس ويُعرَض ولا يُرسَّب حتى يُحسَم أيُّهما يعلو. */
head('①-ب السطحُ اليدويُّ — بلاطاتُ «مساحة عملي» (لا يراها الكاشف)');
$wsSrc = @file_get_contents($ROOT . '/main/my_workspace.php');
$wsTiles = array();
if ($wsSrc !== false && preg_match_all("~'href'\s*=>\s*'\.\./([^']+\.php)~", $wsSrc, $mm)) {
    $wsTiles = array_values(array_unique($mm[1]));
}
chk(count($wsTiles) >= 5, 'انتُزعت البلاطاتُ من المصدرِ فعلًا — وصفرُ منتزَعٍ عطبٌ في المطابقة',
    'بلاطات=' . count($wsTiles));

$wsChecked = 0; $wsDenied = 0; $wsUsers = array();
$__p3 = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$__uq = $conn->query("SELECT id, role, company_id FROM users
                       WHERE is_deleted=0 AND status='active' AND company_id=4 ORDER BY id");
while ($__u3 = $__uq->fetch_assoc()) {
    $_SESSION['user'] = array('id' => (int) $__u3['id'], 'role' => (string) $__u3['role'],
                              'company_id' => (int) $__u3['company_id'], 'name' => 'workspace probe');
    foreach ($wsTiles as $t) {
        $k = strtolower(navarch_norm_route($t));
        if (!isset($modByRoute[$k])) { continue; }
        $wsChecked++;
        if (empty(get_module_permissions($conn, $modByRoute[$k])['can_view'])) {
            $wsDenied++; $wsUsers[(int) $__u3['id']] = 1;
        }
    }
}
if ($__p3 === null) { unset($_SESSION['user']); } else { $_SESSION['user'] = $__p3; }

if ($wsDenied === 0) {
    ok('★ بلاطاتُ «مساحة عملي» كلُّها يفتحها الحارس — صفرٌ من ' . $wsChecked);
} else {
    hold('بلاطاتُ «مساحة عملي» يفتحها الحارسُ لكلِّ حساب — ' . $wsDenied . ' من ' . $wsChecked
         . ' بلاطةً مردودةٌ عند ' . count($wsUsers) . ' مستخدمًا',
         'تعارضُ قرارَين: الملفُّ يعلنها «إلزاميّةً بلا استثناء» والحارسُ يقول «لا شاشةَ خارجَ القالب» — '
       . 'والحسمُ للمالكِ: تُضاف إلى كلِّ قالبٍ · أو تُستثنى من حكمِ القالب · أو تُخفى لغيرِ المغطَّى');
}

/* ── ①-ج **الجلسةُ غيرُ المغطَّاة** — الحالةُ التي لا يُوجِدها أحدٌ اليوم ────
   ⛔ **العطبُ المقيسُ في هذا الحاجبِ نفسِه**: أُعيدت أعطابُ `navarch_renderer`
     و`unified_nav` عمدًا **فبقي أخضرَ (17·0)** — بينما أمسك عطبَي
     `dynamic_nav` و`role_board`. والسببُ أنَّ عطبَ الأوّلَين لا يظهر إلّا
     **لجلسةٍ بلا قالبٍ نافذ**، و**كلُّ مستخدمٍ حيٍّ اليوم مغطًّى** — فالحالةُ
     غيرُ موجودةٍ في البيانات، وما لا تُوجِده البياناتُ لا يقيسه مسحٌ عليها.
   ◆ **فتُصنَع الحالةُ صنعًا**: جلسةٌ بمعرِّفٍ لا منحةَ له. وهذا **عينُ ما تحت
     الفحص** (حكمُ غيرِ المغطَّى)، لا متغيِّرٌ مربكٌ كما كان في `fin26` — وثمَّةَ
     الفرق: هناك كانت التغطيةُ **شرطَ قياسٍ لشيءٍ آخر**، وهنا هي **المقيسُ نفسُه**.
   ◆ **والحكمُ الصريح**: غيرُ المغطَّى يردُّه `get_module_permissions` بـ
     `no_profile_no_fallback` — فكلُّ سطحٍ يجب أن يُخرِج له **صفرَ رابطٍ يحلُّ
     إلى وحدة**. ورابطٌ واحدٌ يعني سطحًا يبني من سجلٍّ لا يحكم. */
head('①-ج الجلسةُ غيرُ المغطَّاة — حالةٌ تُصنَع لأنَّ البيانات لا تُوجِدها');

$PROBE_UID = 990001;            /* معرِّفٌ لا منحةَ له — ولا يُكتب شيءٌ في القاعدة */
$probeCov = $conn->query("SELECT COUNT(*) c FROM gov_authority_grants g
    JOIN gov_role_profiles p ON p.profile_id=g.profile_id AND p.state='active'
   WHERE g.user_id = {$PROBE_UID} AND g.revoked_at IS NULL")->fetch_assoc();
chk((int) $probeCov['c'] === 0, 'المسبارُ بلا قالبٍ فعلًا — وبتغطيتِه لا معنى للفحص',
    'منحٌ=' . $probeCov['c']);

$roleForProbe = 0;
$rq = $conn->query("SELECT role, COUNT(*) n FROM users
                     WHERE is_deleted=0 AND status='active' AND company_id=4
                     GROUP BY role ORDER BY n DESC LIMIT 1");
if ($rx = $rq->fetch_assoc()) { $roleForProbe = (int) $rx['role']; }
chk($roleForProbe > 0, 'اختير دورٌ حيٌّ للمسبار — فدورٌ بلا روابطَ يُخرج صفرًا بلا معنى',
    'دور=' . $roleForProbe);

$__p4 = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$_SESSION['user'] = array('id' => $PROBE_UID, 'role' => (string) $roleForProbe,
                          'company_id' => 4, 'name' => 'uncovered probe');
$GLOBALS['__uxui_cur_role'] = $roleForProbe;

/* ◆ **وأوّلًا يُتحقَّق أنَّ الحارسَ يردُّه فعلًا** — فلو فتح له لانقلب الفحصُ كلُّه. */
$anyOpen = false;
$sampleQ = $conn->query("SELECT id FROM modules WHERE code LIKE '%.php' ORDER BY id LIMIT 40");
$sampleN = 0;
while ($sx = $sampleQ->fetch_row()) {
    $sampleN++;
    if (!empty(get_module_permissions($conn, (int) $sx[0])['can_view'])) { $anyOpen = true; break; }
}
chk($sampleN >= 20 && !$anyOpen,
    '★ الحارسُ يردُّ غيرَ المغطَّى عن كلِّ شاشةٍ — فهذا هو المعيارُ الذي تُقاس به الأسطح',
    'عيّنة=' . $sampleN . ($anyOpen ? ' · فُتحت واحدةٌ ⇐ المعيارُ منقوض' : ' · صفرُ مفتوحة'));

$leak = array(); $probeSeen = array();
foreach ($DRIVERS as $sfc => $drive) {
    $routes = array();
    try { $routes = $drive($conn, array('id' => $PROBE_UID, 'role' => $roleForProbe, 'company_id' => 4)); }
    catch (\Throwable $t) { $routes = array(); }
    $n = 0;
    foreach ($routes as $rt) {
        $k = strtolower(navarch_norm_route($rt));
        if ($k !== '' && isset($modByRoute[$k])) { $n++; }
    }
    $probeSeen[$sfc] = $n;
    if ($n > 0) { $leak[] = "{$sfc}={$n}"; }
}
if ($__p4 === null) { unset($_SESSION['user']); } else { $_SESSION['user'] = $__p4; }

foreach ($probeSeen as $sfc => $n) {
    fwrite(STDOUT, sprintf("     %-30s يُخرج لغيرِ المغطَّى: %d\n", $sfc, $n));
}
chk(count($leak) === 0,
    '★★★ **ولا سطحَ يبني رابطًا لجلسةٍ يردُّها الحارسُ كليًّا**',
    count($leak) === 0 ? 'صفرٌ في الأسطحِ الستّة' : ('مُسرِّب: ' . implode(' · ', $leak)));

/* ── ①-د **الاتجاهُ المعاكس**: «يفتحه الحارسُ ولا يراه» ────────────────────
   ◆ **والحاجبُ حتى الآن يحرس اتجاهًا واحدًا**: `الظاهرُ ⊆ المسموح`. وذاك يقبل
     سايدبارًا فارغًا تمامًا! فالمطلبُ **مساواةٌ لا احتواء**:
     `Rendered Sidebar = Active Workspace Placements ∩ User Authorized Screens`.
   ⛔ **العطبُ المقيس**: `navarch_authorized_routes` كانت تشترط صفًّا في
     `nav_items` قبلَ أن يمرَّ المسار — **مصدرُ تصريحٍ ثالثٌ** يضيّق ما أذِن به
     الحارس. فقِيس **119 حالةً في 11 دورًا**: شاشةٌ `PRIMARY` في مساحةِ صاحبِها ·
     يسمح بها قالبُه · يفتحها الحارسُ · ولا تُصيَّر له.
   ◆ **والمقامُ يُضيَّق بحقٍّ لا بتساهل**: لا يُحاسَب إلّا ما اجتمع فيه ثلاثةٌ —
     ① موضعٌ `PRIMARY` **نشطٌ في مساحةِ دورِه هو** (فما في مساحةِ إدارةٍ أخرى
     خارجُ سايدبارِه بنصِّ §9، ولا يُعَدُّ عطبًا) · ② يسمح به قالبُه · ③ يفتحه
     الحارسُ فعلًا. وبغيرِ هذا التضييقِ يُقرأ 1,639 «عطبًا» وأكثرُها بالتصميم. */
head('①-د الاتجاهُ المعاكس — «يفتحه الحارسُ ولا يراه»');

$WSP = array();
$wq = $conn->query("SELECT workspace_id, route, placement_type FROM nav_workspace_placements
                     WHERE status='ACTIVE' AND placement_type='PRIMARY'");
while ($w = $wq->fetch_assoc()) { $WSP[$w['workspace_id']][strtolower(navarch_norm_route($w['route']))] = 1; }
chk(count($WSP) > 0, 'مواضعُ PRIMARY نشطةٌ قُرئت — وصفرُ موضعٍ يجعل الفحصَ بلا معنى',
    'مساحات=' . count($WSP));

$orphan = 0; $orphChecked = 0; $orphEx = array();
$__p5 = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$uq2 = $conn->query("SELECT id, role, company_id FROM users
                      WHERE is_deleted=0 AND status='active' AND company_id=4 ORDER BY id");
while ($u5 = $uq2->fetch_assoc()) {
    $rid5 = (int) $u5['role'];
    $_SESSION['user'] = array('id' => (int) $u5['id'], 'role' => (string) $rid5,
                              'company_id' => (int) $u5['company_id'], 'name' => 'reverse probe');
    $GLOBALS['__uxui_cur_role'] = $rid5;
    $ws5 = navarch_role_workspace($conn, $rid5);
    if (!isset($WSP[$ws5])) { continue; }

    $shown5 = array();
    $tr5 = navarch_render($conn, $ws5, $rid5, array('include_shell' => false));
    foreach ((isset($tr5['groups']) ? $tr5['groups'] : array()) as $g5) {
        foreach ((isset($g5['items']) ? $g5['items'] : array()) as $i5) {
            $shown5[strtolower(navarch_norm_route($i5['route']))] = 1;
        }
    }
    $pq5 = $conn->query("SELECT DISTINCT i.item_ref FROM gov_authority_grants g
        JOIN gov_role_profiles p ON p.profile_id=g.profile_id AND p.state='active'
        JOIN gov_profile_items i ON i.profile_id=p.profile_id AND i.item_kind='screen' AND i.allow=1
       WHERE g.user_id=" . (int) $u5['id'] . " AND g.revoked_at IS NULL
         AND (g.valid_to IS NULL OR g.valid_to > NOW())");
    while ($x5 = $pq5->fetch_row()) {
        $k5 = strtolower(navarch_norm_route($x5[0]));
        if (!isset($WSP[$ws5][$k5])) { continue; }          /* ليس PRIMARY في مساحتِه ⇒ §9 */
        if (!isset($modByRoute[$k5])) { continue; }
        if (empty(get_module_permissions($conn, $modByRoute[$k5])['can_view'])) { continue; }
        $orphChecked++;
        if (!isset($shown5[$k5])) {
            $orphan++;
            if (count($orphEx) < 5) { $orphEx[] = '#' . $u5['id'] . ' ⟵ ' . $x5[0]; }
        }
    }
}
if ($__p5 === null) { unset($_SESSION['user']); } else { $_SESSION['user'] = $__p5; }

chk($orphChecked >= 200, 'أزواجٌ مؤهَّلةٌ فُحصت — ولا معنى لصفرِ مفحوص', "عدد={$orphChecked}");
chk($orphan === 0,
    '★★★ **ولا شاشةَ PRIMARY في مساحتِه يفتحها الحارسُ ولا يُصيَّر لها رابط**',
    $orphan === 0 ? "صفرٌ من {$orphChecked}" : implode(' · ', $orphEx));

head('② **الضابطُ السالب** — أيرى المسبارُ عطبًا لو وقع؟');
/* ⛔ **والضحيّةُ تُنتزَع من المُصيَّرِ نفسِه لا من `nav_items`**: صفٌّ في الجدولِ
     قد لا يُصيَّر — فكسرُه لا يُحرِّك المسبارَ ويُقرأ «المسبارُ أعمى» وهو سليم.
     (وقع مقيسًا: أُطفئ بندٌ مأخوذٌ من الجدولِ فبقي العدُّ صفرًا لأنَّ رابطَه
     أصلًا خارجَ الشجرةِ المُصيَّرة.) فتُقرأ الشجرةُ أوّلًا ثمَّ يُختار منها. */
$victim = null;
$__prev = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$__ur = $conn->query("SELECT id, role, company_id FROM users
                       WHERE is_deleted=0 AND status='active' AND company_id=4 ORDER BY id");
while ($victim === null && ($__u = $__ur->fetch_assoc())) {
    $_SESSION['user'] = array('id' => (int) $__u['id'], 'role' => (string) $__u['role'],
                              'company_id' => (int) $__u['company_id'], 'name' => 'victim pick');
    $__ws = navarch_role_workspace($conn, (int) $__u['role']);
    $__tree = navarch_render($conn, $__ws, (int) $__u['role'], array('include_shell' => false));
    $__routes = array();
    foreach ((isset($__tree['groups']) ? $__tree['groups'] : array()) as $__g) {
        foreach ((isset($__g['items']) ? $__g['items'] : array()) as $__it) {
            /* ◆ **المطابقةُ برمزِ الوحدةِ لا بنصِّ المسار**: `item_ref` رمزٌ في
                 `modules.code`، والمسارُ المُصيَّرُ قد يحمل بادئةً أو معاملًا —
                 فمطابقةُ النصِّ بالنصِّ لا تجد شيئًا وتُقرأ «لا ضحيّة». */
            $__k = strtolower(navarch_norm_route($__it['route']));
            if (isset($modByRoute[$__k])) { $__routes[] = $modCodeById[$modByRoute[$__k]]; }
        }
    }
    if (!$__routes) { continue; }
    $__in = array();
    foreach ($__routes as $__rt) { $__in[] = "'" . $conn->real_escape_string($__rt) . "'"; }
    $__q = $conn->query("
      SELECT i.item_id, i.item_ref
        FROM gov_authority_grants g
        JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state = 'active'
        JOIN gov_profile_items i ON i.profile_id = p.profile_id
             AND i.item_kind = 'screen' AND i.allow = 1
       WHERE g.user_id = " . (int) $__u['id'] . " AND g.revoked_at IS NULL
         AND (g.valid_to IS NULL OR g.valid_to > NOW())
         AND i.item_ref IN (" . implode(',', $__in) . ") LIMIT 1");
    if ($__q && ($__x = $__q->fetch_assoc())) {
        $victim = array('uid' => (int) $__u['id'], 'item_id' => (int) $__x['item_id'],
                        'item_ref' => $__x['item_ref'], 'role' => $__u['role']);
    }
}
if ($__prev === null) { unset($_SESSION['user']); } else { $_SESSION['user'] = $__prev; }
chk($victim !== null, 'وُجد زوجٌ مُصيَّرٌ يصلح للكسرِ المتعمَّد',
    $victim ? ('مستخدم #' . $victim['uid'] . ' · ' . $victim['item_ref']) : 'لا شيء');

if ($victim) {
    $ITEM = (int) $victim['item_id'];
    /* ⛔ الاستعادةُ تُسجَّل **قبلَ** التغيير. */
    register_shutdown_function(static function () use ($conn, $ITEM) {
        $conn->query("UPDATE gov_profile_items SET allow = 1 WHERE item_id = {$ITEM}");
    });

    $conn->query("UPDATE gov_profile_items SET allow = 0 WHERE item_id = {$ITEM}");
    $flipped = $conn->affected_rows;
    chk($flipped === 1, 'أُطفئ البندُ فعلًا — وبلا كسرٍ لا معنى للضابط', "صفوف={$flipped}");

    list($c2, $d2, $ex2, , , ) = $sweep($conn, $modByRoute, (int) $victim["uid"]);
    chk($d2 >= 1, '★★ **المسبارُ رصد العطبَ المصنوع** — فصفرُه أعلاه قياسٌ لا عمًى',
        "مردودٌ بعدَ الكسر={$d2} من {$c2}" . ($ex2 ? ' · ' . $ex2[0] : ''));

    $conn->query("UPDATE gov_profile_items SET allow = 1 WHERE item_id = {$ITEM}");
    list($c3, $d3, , , , ) = $sweep($conn, $modByRoute, (int) $victim["uid"]);
    chk($d3 === 0, '★ وبالاستعادةِ عاد الصفرُ — فالأثرُ من الكسرِ لا من المسبار',
        "مردودٌ بعدَ الاستعادة={$d3} من {$c3}");
}

head('③ **§2 — من لا يظهر له الرابطُ يستدعي المسارَ مباشرةً فيُمنع**');
/* ◆ **نصُّ PERM-01-DEC §2**: «لا يُقبل أن «لا يردَّ أحدٌ يرى الرابط»… اختبارٌ
     سالب: **من لا يظهر له الرابطُ يستدعي المسارَ مباشرةً فيُمنع**».
   ◆ **والحكمُ على من لا رابطَ له ولا بندَ في قالبِه**: شاشةٌ تُبلَغ بالنقرِ من
     غيرِها قد تكون في القالبِ بلا رابطٍ بحقّ (كرتُ المعدة) — فلا تُعَدُّ خرقًا.
     المقصودُ من **لا يملكها أصلًا** ثمَّ يكتب مسارَها في المتصفّح. */
$prev2 = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$dchecked = 0; $dopen = array();
$ur = $conn->query("SELECT id, role, company_id FROM users
                     WHERE is_deleted=0 AND status='active' AND company_id=4 ORDER BY id LIMIT 12");
while ($u2 = $ur->fetch_assoc()) {
    /* ما يظهر له فعلًا. */
    $_SESSION['user'] = array('id' => (int) $u2['id'], 'role' => (string) $u2['role'],
                              'company_id' => (int) $u2['company_id'], 'name' => 'direct url probe');
    $ws2 = navarch_role_workspace($conn, (int) $u2['role']);
    $tree2 = navarch_render($conn, $ws2, (int) $u2['role'], array('include_shell' => false));
    $seen = array();
    foreach ((isset($tree2['groups']) ? $tree2['groups'] : array()) as $g2) {
        foreach ((isset($g2['items']) ? $g2['items'] : array()) as $i2) {
            $k2 = strtolower(navarch_norm_route($i2['route']));
            if (isset($modByRoute[$k2])) { $seen[$modByRoute[$k2]] = 1; }
        }
    }
    /* وما في قالبِه (فبندُ قالبٍ بلا رابطٍ مشروعٌ ولا يُعَدُّ خرقًا). */
    $inProf = array();
    $pq2 = $conn->query("SELECT m.id FROM gov_authority_grants g
                           JOIN gov_role_profiles p ON p.profile_id=g.profile_id AND p.state='active'
                           JOIN gov_profile_items i ON i.profile_id=p.profile_id
                                AND i.item_kind='screen' AND i.allow=1
                           JOIN modules m ON m.code=i.item_ref
                          WHERE g.user_id=" . (int) $u2['id'] . " AND g.revoked_at IS NULL");
    while ($x2 = $pq2->fetch_row()) { $inProf[(int) $x2[0]] = 1; }

    /* عيّنةٌ ممّا لا يملكه ولا يراه — ومنها شاشةُ البلاغاتِ المدمجةُ نصًّا. */
    $cand = array();
    $cq = $conn->query("SELECT id, code FROM modules
                         WHERE code IN ('Tickets/dept_inbox.php','Tickets/tickets_list.php')
                            OR code LIKE 'Finance/%' ORDER BY id LIMIT 25");
    while ($c2 = $cq->fetch_assoc()) {
        $mid2 = (int) $c2['id'];
        if (isset($seen[$mid2]) || isset($inProf[$mid2])) { continue; }
        $cand[$mid2] = $c2['code'];
    }
    foreach ($cand as $mid2 => $code2) {
        $dchecked++;
        if (!empty(get_module_permissions($conn, $mid2)['can_view'])) {
            if (count($dopen) < 5) { $dopen[] = '#' . $u2['id'] . ' ⟵ ' . $code2; }
        }
    }
    unset($_SESSION['user']);
}
if ($prev2 === null) { unset($_SESSION['user']); } else { $_SESSION['user'] = $prev2; }
chk($dchecked >= 20, 'أزواجٌ فُحصت — ولا معنى لصفرِ مفحوص', "عدد={$dchecked}");
chk(count($dopen) === 0,
    '★★★ **من لا رابطَ له ولا بندَ في قالبِه يُمنع عند استدعاءِ المسارِ مباشرةً**',
    count($dopen) === 0 ? "صفرٌ من {$dchecked}" : implode(' · ', $dopen));

head('④ الجلسةُ لم تتسرَّب');
chk(!isset($_SESSION['user']), '★ لا جلسةَ مسبارٍ باقيةٌ بعدَ المسح');

fwrite(STDOUT, "\n──────────────────────────────────────────────────────────\n");
fwrite(STDOUT, "النتيجة: {$PASS} نجاح · {$FAIL} رسوب"
    . ($HOLD > 0 ? " · {$HOLD} معلَّق (قرارُ مالكٍ لا عطبٌ برمجيّ)" : '') . "\n");
fwrite(STDOUT, "المدى: {$checked} رابطًا مُصيَّرًا عبر " . count($per) . " أسطحٍ مكنوسةٍ × {$nUsers} مستخدمًا\n");
fwrite(STDOUT, $FAIL === 0
    ? "✔ RENDER_VS_GUARD = PASS\n"
    : "✘ رابطٌ يُصيَّر ويردُّه بابُه\n");
exit($FAIL === 0 ? 0 : 1);
