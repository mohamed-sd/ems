<?php
/**
 * tools/u13_screens_build.php — توليدُ شاشاتِ update0013 وتسجيلُها
 * ═══════════════════════════════════════════════════════════════════════════
 * يقرأ `u13_screens_manifest.php` — المصبَّ الواحدَ — ويفعل ثلاثةً:
 *   ① يكتب ملفَّ كلِّ شاشةٍ تصريحًا فوقَ العُدّة (`includes/u13_screen_kit.php`).
 *   ② يسجّلها في `modules` — وهو مفتاحُ حارسِ الصلاحياتِ في المنصة.
 *   ③ يمنح الدورَ المالكَ والسوبرَ قراءتَها، ويضعها في `nav_items`.
 *
 * ◆ الملفُّ المولَّدُ **لا يُحرَّر يدويًّا**: أعِد التوليدَ بعد تغييرِ البيان.
 *   وما يحتاج منطقًا خاصًّا يُوضع في `panel` داخلَ العُدّةِ لا في الملف.
 *
 * ◆ لا يُكتب ملفٌّ قائمٌ لم يولّده هذا المولِّد (يُفحص بالتوقيع) — فلا يُدهَس
 *   عملُ يدٍ سابقةٍ صامتًا.
 *
 * التشغيل: php tools/u13_screens_build.php [--apply] [--force]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT  = dirname(__DIR__);
$apply = in_array('--apply', $argv, true);
$force = in_array('--force', $argv, true);
require_once $ROOT . '/tools/u13_screens_manifest.php';
$MAN = u13_screens_manifest();

/** توقيعٌ يميّز الملفَّ المولَّد — ولا يُدهَس ما لا يحمله. */
const SIGNATURE = 'u13:generated';

$cfg = array('host' => 'localhost', 'port' => 3307, 'user' => 'root', 'pass' => '', 'db' => 'equipation_manage');
if (is_file($ROOT . '/.env')) {
    foreach (file($ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
        if ($ln === '' || $ln[0] === '#' || strpos($ln, '=') === false) { continue; }
        list($k, $v) = explode('=', $ln, 2); $k = trim($k); $v = trim($v);
        if ($k === 'DB_HOST') { $hp = explode(':', $v); $cfg['host'] = $hp[0]; if (isset($hp[1])) { $cfg['port'] = (int) $hp[1]; } }
        if ($k === 'DB_PORT') { $cfg['port'] = (int) $v; }
        if ($k === 'DB_USER') { $cfg['user'] = $v; }
        if ($k === 'DB_PASS') { $cfg['pass'] = $v; }
        if ($k === 'DB_NAME') { $cfg['db']   = $v; }
    }
}
$db = @new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['db'], $cfg['port']);
if ($db->connect_errno) { exit("تعذّر الاتصال: {$db->connect_error}\n"); }
$db->set_charset('utf8mb4');

$FAIL = 0;
function bad($s) { global $FAIL; $FAIL++; echo "  ✗ $s\n"; }
function ok($s)  { echo "  ✔ $s\n"; }
function skip($s){ echo "  · $s\n"; }

/** نصٌّ آمنٌ داخلَ سلسلةٍ مفردةٍ في PHP المولَّد. */
function q($s) { return "'" . str_replace(array('\\', "'"), array('\\\\', "\\'"), (string) $s) . "'"; }

/* ═══ ① توليدُ الملفات ════════════════════════════════════════════════════ */
echo "① توليدُ ملفاتِ الشاشات (" . count($MAN) . ")\n";
$written = 0; $kept = 0;
foreach ($MAN as $s) {
    $dir  = $ROOT . '/' . $s['dir'];
    $path = $dir . '/' . $s['file'];
    $rel  = $s['dir'] . '/' . $s['file'];

    if (!is_dir($dir)) {
        if ($apply) { @mkdir($dir, 0777, true); ok('مجلَّدٌ جديد: ' . $s['dir'] . '/'); }
        else { skip('سيُنشأ مجلَّد: ' . $s['dir'] . '/'); }
    }
    if (is_file($path) && strpos((string) @file_get_contents($path), SIGNATURE) === false && !$force) {
        skip("قائمٌ بيدٍ سابقةٍ فلا يُدهَس: $rel");
        $kept++;
        continue;
    }

    $opt = array();
    foreach (array('where', 'order', 'limit', 'scope_user', 'panel', 'global_ref') as $k) {
        if (isset($s[$k]) && $s[$k] !== '') { $opt[$k] = $s[$k]; }
    }
    $optSrc = '';
    foreach ($opt as $k => $v) {
        $optSrc .= "\n    " . str_pad("'$k'", 14) . '=> ' . (is_int($v) ? $v : q($v)) . ',';
    }

    $body = "<?php\n"
      . "/**\n"
      . " * {$rel} — {$s['title']}\n"
      . " * " . str_repeat('─', 72) . "\n"
      . " * ◆ ملفٌّ مولَّد (" . SIGNATURE . ") — لا يُحرَّر يدويًّا.\n"
      . " *   عقدُه في `tools/u13_screens_manifest.php` وتصييرُه في\n"
      . " *   `includes/u13_screen_kit.php`. أعِد التوليدَ بعد تغييرِ البيان:\n"
      . " *     php tools/u13_screens_build.php --apply\n"
      . " *\n"
      . " * الأساس: {$s['basis']} · المصدر: {$s['doc']} · المتطلب: {$s['doc_ref']}\n"
      . " * الحكم: {$s['rule']}\n"
      . " *\n"
      . " * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر\n"
      . " *   أصلًا (OBL-0052). فالتصنيفُ شرطُ الظهورِ لا فحصٌ بعده.\n"
      . " *\n"
      . " * ◆ العُدّةُ تُضمَّن في **النطاقِ العامّ** لا داخلَ دالة: `config.php` داخلَ\n"
      . " *   دالةٍ يجعل \$conn محليًّا فتسقط الشاشة، والقشرةُ تعتمد متغيّراتٍ عامة.\n"
      . " */\n"
      . "\$U13 = array(\n"
      . "    'file'       => " . q($rel) . ",\n"
      . "    'screen'     => " . q($s['code']) . ",\n"
      . "    'table'      => " . q($s['table']) . ",\n"
      . "    'title'      => " . q($s['title']) . ",\n"
      . "    'icon'       => " . q($s['icon']) . ",\n"
      . "    'nature'     => " . q($s['nature']) . ",\n"
      . "    'doc'        => " . q($s['doc'] . ' · ' . $s['doc_ref']) . ",\n"
      . "    'intro'      => " . q($s['intro']) . ",\n"
      . "    'rule'       => " . q($s['rule']) . ",\n"
      . "    'empty_hint' => " . q($s['empty']) . ","
      . $optSrc . "\n"
      . (isset($s['actions_src']) ? $s['actions_src'] . "\n" : '')
      . ");\n"
      . "require __DIR__ . '/../includes/u13_screen_kit.php';\n";

    if ($apply) {
        if (@file_put_contents($path, $body) === false) { bad("تعذّرت الكتابة: $rel"); continue; }
        $written++;
        ok($rel);
    } else { skip("سيُكتب: $rel"); }
}

/* ═══ ② التسجيلُ في `modules` — مفتاحُ حارسِ الصلاحيات ═══════════════════ */
echo "\n② التسجيلُ في modules\n";
$order = (int) (@$db->query("SELECT COALESCE(MAX(display_order),0) FROM modules")->fetch_row()[0]);
$modIds = array();
foreach ($MAN as $s) {
    $rel = $s['dir'] . '/' . $s['file'];
    $id = (int) (@$db->query("SELECT id FROM modules WHERE code = '" . $db->real_escape_string($rel) . "' LIMIT 1")->fetch_row()[0] ?? 0);
    if ($id > 0) { $modIds[$rel] = $id; skip("مسجَّلٌ سلفًا #$id — $rel"); continue; }
    if (!$apply) { skip("سيُسجَّل — $rel"); continue; }
    $order++;
    $st = $db->prepare("INSERT INTO modules (name, code, owner_role_id, is_link, is_quick, icon, display_order)
                        VALUES (?,?,?,'1',0,?,?)");
    if (!$st) { bad("prepare modules: " . $db->error); continue; }
    $st->bind_param('ssisi', $s['title'], $rel, $s['role'], $s['icon'], $order);
    if (!$st->execute()) { bad("$rel — " . $st->error); $st->close(); continue; }
    $modIds[$rel] = $st->insert_id;
    $st->close();
    ok("#{$modIds[$rel]} $rel");
}

/* ═══ ③ الصلاحيات: المالكُ يقرأ ويكتب · والرقابيُّ يقرأ ═════════════════ */
echo "\n③ منحُ الصلاحياتِ للأدوارِ المالكة\n";
$granted = 0;
foreach ($MAN as $s) {
    $rel = $s['dir'] . '/' . $s['file'];
    if (!isset($modIds[$rel])) { continue; }
    $mid = $modIds[$rel];
    /* المالكُ بحسبِ طبيعتِها · والحوكمةُ والمراجعةُ قراءةٌ على الحاكمةِ كلِّها.
       ◆ والمراجعُ الداخليُّ **قراءةٌ فقط** خارجَ سجلِّه (IAF-0043) — فلا can_add
         ولا can_edit له إلا على شاشاتِ `Audit/`. */
    $grants = array();
    $canWrite = in_array($s['nature'], array('document', 'register'), true) ? 1 : 0;
    /* ◆ لا انحرافَ بين الإعلانِ والمنحة: شاشةٌ تُعلن فعلَ كتابةٍ ولا can_edit
         لها = زرٌّ يُعرض ولا يعمل. فالبناءُ يرفضها ولا يُخرجها صامتة. */
    if (isset($s['actions_src']) && $canWrite === 0) {
        bad("$rel — تُعلن أفعالَ كتابةٍ وطبيعتُها «{$s['nature']}» فلا can_edit لها");
        continue;
    }
    $grants[$s['role']] = array($canWrite, $canWrite);
    $grants[15] = array(0, 0);                       // مديرُ الصلاحيات — قراءة
    if ($s['dir'] === 'Audit') {
        $grants[33] = array($canWrite, $canWrite);   // المراجعُ في سجلِّه
        $grants[9]  = array(0, 0);                   // الرئيسُ يقرأ التقارير
    } else {
        $grants[33] = array(0, 0);                   // قراءةٌ بلا كتابةٍ على الأصول
    }
    if ($s['dir'] === 'Finance') { $grants[31] = isset($grants[31]) ? $grants[31] : array(0, 0); }

    foreach ($grants as $rid => $g) {
        if (!$apply) { continue; }
        $q = @$db->query("SELECT id, can_add, can_edit FROM role_permissions
                           WHERE role_id = " . (int) $rid . " AND module_id = $mid LIMIT 1");
        $row = $q ? $q->fetch_assoc() : null;
        if ($row) {
            /* ◆ گوتشا: «موجودٌ فيُتخطّى» يجعل البناءَ عاجزًا عن التصحيح — فمنحةٌ
                 كُتبت بطبيعةٍ قديمةٍ تبقى أبدًا وتُسقط أفعالَ الشاشةِ صامتةً.
                 فالمنحةُ تُصحَّح لتطابقَ الإعلانَ لا تُترك. */
            if ((int) $row['can_add'] === $g[0] && (int) $row['can_edit'] === $g[1]) { continue; }
            $up = $db->prepare("UPDATE role_permissions SET can_view = 1, can_add = ?, can_edit = ? WHERE id = ?");
            if (!$up) { bad('prepare perms-fix: ' . $db->error); continue; }
            $pid = (int) $row['id'];
            $up->bind_param('iii', $g[0], $g[1], $pid);
            if (!$up->execute()) { bad("$rel/دور $rid تصحيح — " . $up->error); }
            else { $granted++; ok("صُحّحت منحةُ الدورِ $rid على $rel → add={$g[0]} edit={$g[1]}"); }
            $up->close();
            continue;
        }
        $st = $db->prepare("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                            VALUES (?,?,1,?,?,0)");
        if (!$st) { bad('prepare perms: ' . $db->error); continue; }
        $rid2 = (int) $rid;
        $st->bind_param('iiii', $rid2, $mid, $g[0], $g[1]);
        if (!$st->execute()) { bad("$rel/دور $rid — " . $st->error); }
        else { $granted++; }
        $st->close();
    }
}
echo "  مِنَحٌ جديدة: $granted\n";

/* ═══ ④ القوائم: nav_items للدورِ المالك ══════════════════════════════════ */
echo "\n④ إدراجُ الشاشاتِ في قوائمِ أدوارِها\n";
$navAdded = 0;
foreach ($MAN as $s) {
    $rel = $s['dir'] . '/' . $s['file'];
    $route = '../' . $rel;
    $ex = (int) (@$db->query("SELECT id FROM nav_items WHERE route = '" . $db->real_escape_string($route)
        . "' AND role_id = " . (int) $s['role'] . " LIMIT 1")->fetch_row()[0] ?? 0);
    if ($ex > 0) { continue; }
    if (!$apply) { skip("سيُدرَج — $rel (دور {$s['role']})"); continue; }
    $so = (int) (@$db->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM nav_items
                               WHERE role_id = " . (int) $s['role'])->fetch_row()[0] ?? 1);
    /* ◆ FN-01/FN-02 (حزمة FIX) — صفُّ التنقلِ **يُوصَل بوحدتِه** وبابٍ معروف:
       ① `module_id` إلزاميٌّ متى وُجد `permission_code` — والقيدُ
          `chk_nav_items_module_or_code` في القاعدةِ يرفض غيرَه. فالصفُّ بوحدةٍ
          فارغةٍ ورمزٍ غيرِ فارغٍ **يسقط في الفحصِ صامتًا** (41 صفًّا ميتًا).
       ② والبابُ `main` ليس من الأبوابِ الثمانيةِ المعروفة، فلا يُصيَّر أصلًا —
          والبابُ الصحيحُ لشاشاتِ الحوكمةِ والمراجعةِ هو `GOV`.
       ③ والمسارُ بلا بادئةٍ نسبية: المُصيِّرُ يُلحقها بنفسه. */
    $mid = (int) (@$db->query("SELECT id FROM modules WHERE code = '" . $db->real_escape_string($rel) . "'
                                ORDER BY (owner_role_id = " . (int) $s['role'] . ") DESC, id ASC LIMIT 1")
                     ->fetch_row()[0] ?? 0);
    if ($mid <= 0) { bad("nav $rel — لا وحدةَ صلاحياتٍ مسجَّلةٌ لهذه الشاشة (الصفُّ يُترك ولا يُنشأ ميتًا)"); continue; }
    $door = 'GOV';
    $st = $db->prepare("INSERT INTO nav_items (role_id, door, module_id, label_ar, route, icon, sort_order, permission_code, active, created_at)
                        VALUES (?,?,?,?,?,?,?,?,1,NOW())");
    if (!$st) { bad('prepare nav: ' . $db->error); continue; }
    // الأنواعُ بترتيبِ الأعمدة: role(i) door(s) module(i) label(s) route(s) icon(s) sort(i) code(s)
    $st->bind_param('isisssis', $s['role'], $door, $mid, $s['title'], $route, $s['icon'], $so, $rel);
    if (!$st->execute()) { bad("nav $rel — " . $st->error); }
    else { $navAdded++; }
    $st->close();
}
echo "  عناصرُ قائمةٍ جديدة: $navAdded\n";

/* ═══ الحصيلة ═════════════════════════════════════════════════════════════ */
echo "\n" . str_repeat('─', 74) . "\n";
printf("الشاشات: %d · مكتوبة: %d · مُبقاةٌ بيدٍ سابقة: %d\n", count($MAN), $written, $kept);
printf("مسجَّلةٌ في modules: %d · مِنَح: %d · قوائم: %d\n", count($modIds), $granted, $navAdded);
if ($FAIL > 0) { printf("\n✗ إخفاقات: %d\n", $FAIL); }
if (!$apply) { echo "\nمعاينةٌ فقط — أضف --apply\n"; }
$db->close();
exit($FAIL > 0 ? 1 : 0);
