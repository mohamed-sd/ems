<?php
/**
 * tools/uxui_ten_binding_gate.php — البنودُ العشرةُ الملزمةُ (ف١٥-١) مقيسةً
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ نصُّ المواصفة: «لكلِّ بندٍ بوابتُه — والمخالفةُ **تُرسِّب البناءَ** لا تُنبِّه».
 *   فهذه الأداةُ تقيس العشرةَ على **النظامِ الحيِّ** لا على نيّةٍ مكتوبة،
 *   وتُرجع 1 عند أيِّ إخفاقٍ فتصلح خطّافًا.
 *
 * ◆ **ولا يُقاس بندٌ من الجداولِ وحدَها حيث يمكن قياسُه من المُصيَّر**: البندُ ٦
 *   يفحص وجودَ ترميزِ قائمةٍ مستقلٍّ في الملفات، والبندُ ١ يفحص ما يُصيَّر فعلًا.
 *
 * ◆ وكلُّ بندٍ يُعلن **مقامَه** ومصدرَ رقمِه — فلا رقمَ بلا مصدر.
 *
 * التشغيل: php tools/uxui_ten_binding_gate.php [--md=<path>]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال\n"); }
$conn->set_charset('utf8mb4');

/* ═══════════════════════════════════════════════════════════════════════════
 * حارسُ المقامِ الصفريّ — **بندٌ بمقامٍ صفرٍ لا يجتاز، بل يُعلَن غيرَ مقيس**
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ كشفه أولُ تشغيل: البندان ① و⑨ أعلنا «✔» ومقامُهما **صفر** — لأن
 *   الاستعلامَ كان يقرأ أعمدةً غيرَ موجودةٍ (`link`/`title`/`group_code`)
 *   بينما الجدولُ يحمل (`route`/`label_ar`/`group_id`). فصفرُ مخالفةٍ على
 *   صفرِ صفوفٍ **نجاحٌ كاذب** — وهو أخطرُ من الإخفاق لأنه يُسكِت البوابة.
 * ◆ فالحكمُ ثلاثيٌّ لا ثنائيّ: `PASS` · `FAIL` · `NOT_MEASURED`. والأخيرةُ
 *   **لا تُحسب مارّةً ولا راسبة** — تُعلَن ويُرفض إعلانُ الاكتمالِ معها.
 * ═══════════════════════════════════════════════════════════════════════════ */
$R = array();   /* البند => array(verdict, num, den, note, sample[]) */
function item(&$R, $no, $title, $ok, $num, $den, $note, $sample = array()) {
    $verdict = ($den <= 0) ? 'NOT_MEASURED' : ($ok ? 'PASS' : 'FAIL');
    $R[$no] = array('title' => $title, 'verdict' => $verdict, 'ok' => ($verdict === 'PASS'),
                    'num' => $num, 'den' => $den, 'note' => $note, 'sample' => $sample);
}

/* ══ ① سجلٌّ معياريٌّ واحد — صفرُ مسارٍ ظاهرٍ بلا صفٍّ في السجل ══════════ */
$navRoutes = array();
$q = $conn->query("SELECT DISTINCT n.route FROM nav_items n WHERE n.active = 1 AND n.route IS NOT NULL AND n.route <> ''");
while ($q && ($x = $q->fetch_assoc())) {
    $l = trim($x['route']);
    if ($l === '' || $l === '#' || strpos($l, '#') === 0) { continue; }
    /* ◆ **مرساةُ التكرارِ `#N` تُطبَّع**: الشاشةُ الواحدةُ تظهر عمدًا في
         موضعَين، فتُميَّز بـ`#2`. والأساسُ مسجَّلٌ في المصفوفة — فعدُّ المرساةِ
         مسارًا مستقلًّا يُبلِّغ 24 «غائبًا» وكلُّها مسجَّلةٌ بأصلِها. */
    $l = preg_replace('~^\.\./~', '', $l);
    $l = preg_replace('~\?.*$~', '', $l);
    $l = preg_replace('~#\d+$~', '', $l);
    $l = ltrim($l, '/');
    if ($l !== '') { $navRoutes[$l] = true; }
}
$canon = array();
$q = $conn->query("SELECT route, canonical_ar, group_name, level_no, sort_no, decision_state,
                          derivation, current_label, current_parent, owner_dept, nature
                     FROM nav_canonical");
while ($q && ($x = $q->fetch_assoc())) { $canon[$x['route']] = $x; }
$missing = array();
foreach (array_keys($navRoutes) as $r) { if (!isset($canon[$r])) { $missing[] = $r; } }
item($R, 1, 'سجلٌّ معياريٌّ واحد — صفرُ مسارٍ ظاهرٍ بلا صفّ',
     count($missing) === 0, count($missing), count($navRoutes),
     'المقام: مساراتٌ فريدةٌ نشطةٌ في `nav_items`', array_slice($missing, 0, 5));

/* ═══════════════════════════════════════════════════════════════════════════
 * ②③ — **يُقاسانِ على النصِّ المُصيَّرِ لا على `nav_items` الخام**
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ كشفته الهندسةُ العكسية: قياسُ `nav_items` مباشرةً أعطى **33 اسمًا مزدوجًا
 *   و179 مجموعةً مزدوجة** — وكلُّها زائفة. لأن `nav_items` يحتفظ بالموضعِ
 *   الموروثِ بينما **المولِّدُ يستبدله لحظةَ التصييرِ بالمعياريِّ من السجل**
 *   (`includes/unified_nav.php`: APPROVED يأخذ الاسمَ والمجموعةَ المعياريةَ في
 *   كلِّ الأدوارِ سواءً). فالجدولُ الخامُّ ليس ما يراه المستخدم.
 * ◆ ونصُّ الوثيقةِ صريحٌ: البواباتُ **«على النصِّ المُصيَّرِ لا الجداول»**.
 * ◆ فلا يُعاد بناءُ المنطقِ هنا — تُقرأ نتيجةُ `tools/uxui_gates.php` الذي
 *   يُصيِّر كلَّ دورٍ فعلًا (`uxp_render_role`). **ومصدرُ الرقمِ واحدٌ لا اثنان**:
 *   ولو كُتب هنا ثانيًا لتفرَّقا — كما تفرَّقا فعلًا في أولِ تشغيل.
 * ═══════════════════════════════════════════════════════════════════════════ */
$gatesOut = (string) @shell_exec(escapeshellarg(PHP_BINARY) . ' '
          . escapeshellarg($ROOT . '/tools/uxui_gates.php') . ' 2>&1');
$u2 = null; $u3 = null;
if (preg_match('~U2 [^\n]*?إنفاذ=(\d+)~u', $gatesOut, $m)) { $u2 = (int) $m[1]; }
if (preg_match('~U3 [^\n]*?إنفاذ=(\d+)~u', $gatesOut, $m)) { $u3 = (int) $m[1]; }
$renderedRoutes = preg_match('~(\d+) مسارًا فريدًا~u', $gatesOut, $m) ? (int) $m[1] : 0;

item($R, 2, '`same_route ⇒ same_canonical_label` — على المُصيَّر',
     $u2 === 0, $u2 === null ? 1 : $u2, $u2 === null ? 0 : $renderedRoutes,
     $u2 === null ? '**تعذّر قراءةُ U2 من الفاحصِ المُصيِّر**'
                  : 'مصدرُه `tools/uxui_gates.php` — يُصيِّر كلَّ دورٍ فعلًا');
item($R, 3, '`same_route ⇒ same_parent_group` — على المُصيَّر',
     $u3 === 0, $u3 === null ? 1 : $u3, $u3 === null ? 0 : $renderedRoutes,
     $u3 === null ? '**تعذّر قراءةُ U3**' : 'المصدرُ نفسُه — ولا يُعاد بناءُ المنطق');
/* تُقرأ من الفاحصِ لا تُحسب هنا — والبندانِ ⑤ و⑦ يستندانِ إليهما */
$twoNames = ($u2 === null) ? array('غيرُ مقروء') : array_fill(0, $u2, 'مخالفةٌ مُصيَّرة');
$twoParents = ($u3 === null) ? array('غيرُ مقروء') : array_fill(0, $u3, 'مخالفةٌ مُصيَّرة');
/* ══ ④ الشيوعُ اقتراحٌ لا مصدرَ حقيقة — حقلُ مصدرِ الاشتقاقِ إلزاميّ ═════ */
$noDeriv = array(); $freqOnly = array();
foreach ($canon as $r => $c) {
    $d = trim((string) $c['derivation']);
    if ($d === '') { $noDeriv[] = $r; continue; }
    /* ما مصدرُه الشيوعُ وحدَه لا يُنفَّذ قبلَ مصادقةِ الإدارةِ المالكة */
    if (preg_match('~شيوع|الأكثر\s*شيوع~u', $d) && $c['decision_state'] === 'APPROVED') {
        $freqOnly[] = $r . ' ⇐ ' . mb_substr($d, 0, 40);
    }
}
item($R, 4, 'مصدرُ الاشتقاقِ إلزاميّ — ولا يُنفَّذ ما مصدرُه الشيوعُ وحدَه',
     count($noDeriv) === 0 && count($freqOnly) === 0,
     count($noDeriv) + count($freqOnly), count($canon),
     'بلا مصدر: ' . count($noDeriv) . ' · شيوعٌ معتمَدٌ بلا مصادقة: ' . count($freqOnly),
     array_slice(array_merge($noDeriv, $freqOnly), 0, 5));

/* ══ ⑤ الظهورُ بالدورِ لا الموضعُ — صفرُ parent_group مشروطٍ بالدور ══════ */
$roleParent = array();
$q = $conn->query("SELECT n.route, COUNT(DISTINCT n.group_id) c
                     FROM nav_items n WHERE n.active = 1 AND n.route IS NOT NULL AND n.route <> '' AND n.route <> '#'
                    GROUP BY n.route HAVING c > 1");
$roleParentCount = 0;
while ($q && ($x = $q->fetch_assoc())) { $roleParentCount++; }
/* والسجلُّ نفسُه: لا عمودَ يجعل المجموعةَ دالةً في الدور */
$hasRoleCol = $conn->query("SELECT 1 FROM information_schema.COLUMNS
                             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='nav_canonical'
                               AND COLUMN_NAME IN ('role_id','role_group','parent_by_role')");
$structOk = !($hasRoleCol && $hasRoleCol->num_rows > 0);
item($R, 5, 'الظهورُ بالدورِ لا الموضعُ — صفرُ `parent_group` مشروطٍ بالدور',
     $structOk && count($twoParents) === 0, count($twoParents), count($canon),
     'السجلُّ بلا عمودِ دورٍ يحكم المجموعة: ' . ($structOk ? 'نعم' : 'لا')
     . ' · ومسارٌ بمجموعتَين مختلفتَين بين دورَين: ' . $roleParentCount . ' (منها APPROVED: ' . count($twoParents) . ')');

/* ══ ⑥ السايدبارُ مكوّنٌ مركزيٌّ مولَّد — لا ترميزَ قائمةٍ مستقلٍّ لإدارة ═ */
$ownMenus = array();
$dirs = array_filter(glob($ROOT . '/*'), 'is_dir');
foreach ($dirs as $d) {
    $b = basename($d);
    if (in_array($b, array('.git', 'node_modules', 'vendor', 'assets', 'docs', 'database',
                           'tools', 'tests', 'storage', 'logs', 'uploads', '.claude', '.ssdiff'), true)) { continue; }
    foreach (glob($d . '/*sidebar*.php') as $f) { $ownMenus[] = str_replace($ROOT . '/', '', $f); }
    foreach (glob($d . '/*menu*.php') as $f) { $ownMenus[] = str_replace($ROOT . '/', '', $f); }
}
item($R, 6, 'السايدبارُ مركزيٌّ مولَّد — لا ملفَّ قائمةٍ خاصًّا بإدارة',
     count($ownMenus) === 0, count($ownMenus), count($dirs),
     'المقام: مجلداتُ الإداراتِ المفحوصة', array_slice($ownMenus, 0, 5));

/* ══ ⑦ اختلافُ السياقِ منظرٌ لا اسمٌ ثانٍ — 43 مسارًا تُغلق ══════════════ */
$viewOf = 0; $stillTwo = count($twoNames);
$q = $conn->query("SELECT COUNT(*) c FROM nav_canonical WHERE view_of IS NOT NULL AND view_of <> ''");
if ($q) { $viewOf = (int) $q->fetch_assoc()['c']; }
item($R, 7, 'اختلافُ السياقِ منظرٌ لا اسمٌ ثانٍ',
     $stillTwo === 0, $stillTwo, count($canon),
     'صفوفٌ معلَّمةٌ `view_of`: ' . $viewOf . ' · ومسارٌ APPROVED ما زال باسمَين: ' . $stillTwo);

/* ══ ⑧ ترتيبُ العملياتِ يطابق الدورةَ المستندية ══════════════════════════ */
/* ◆ **المصدرانِ المقبولانِ بنصِّ البند**: مستندٌ ناتجٌ معلومٌ **أو** وسمُ انتقالِ
     حالةٍ صريح. وكلاهما عمودٌ في السجلِّ الآن مع `ops_source` يقول من أين جاء —
     فما لا مصدرَ له يبقى NULL ويظهر رقمًا، ولا يُملأ بجملةٍ عامةٍ تُسكِت البوابة. */
$opsNoDoc = array(); $opsTotal = 0;
$hasDocCol = $conn->query("SELECT 1 FROM information_schema.COLUMNS
                            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='nav_canonical'
                              AND COLUMN_NAME='output_doc'");
$docColExists = ($hasDocCol && $hasDocCol->num_rows > 0);
if ($docColExists) {
    $q = $conn->query("SELECT route, output_doc, state_transition, ops_source FROM nav_canonical
                         WHERE group_name LIKE '%عمليات%' OR group_name LIKE '%العمليات%'");
    while ($q && ($x = $q->fetch_assoc())) {
        $opsTotal++;
        $hasDoc   = trim((string) $x['output_doc']) !== '';
        $hasTrans = trim((string) $x['state_transition']) !== '';
        $hasSrc   = trim((string) $x['ops_source']) !== '';
        if (!(($hasDoc || $hasTrans) && $hasSrc)) { $opsNoDoc[] = $x['route']; }
    }
}
item($R, 8, 'ترتيبُ العملياتِ يطابق الدورةَ المستندية',
     $docColExists && count($opsNoDoc) === 0, count($opsNoDoc), $opsTotal,
     $docColExists ? 'كلُّ صفٍّ يحمل مستندًا ناتجًا أو وسمَ انتقالٍ **ومصدرَه**'
                   : '**لا عمودَ `output_doc`** — البندُ غيرُ قابلٍ للقياس',
     array_slice($opsNoDoc, 0, 5));

/* ══ ⑨ لا مجموعةَ إداريةٍ فارغةٍ أو معطَّلة ══════════════════════════════ */
$emptyGroups = array();
$q = $conn->query("SELECT n.role_id, n.group_id, COUNT(*) c,
                          SUM(CASE WHEN n.route IS NOT NULL AND n.route <> '' AND n.route <> '#' THEN 1 ELSE 0 END) live
                     FROM nav_items n WHERE n.active = 1 AND n.group_id IS NOT NULL
                    GROUP BY n.role_id, n.group_id HAVING live = 0");
$grpTotal = 0;
$q2 = $conn->query("SELECT COUNT(*) c FROM (SELECT 1 FROM nav_items WHERE active=1
                     AND group_id IS NOT NULL GROUP BY role_id, group_id) g");
if ($q2) { $grpTotal = (int) $q2->fetch_assoc()['c']; }
while ($q && ($x = $q->fetch_assoc())) { $emptyGroups[] = "دور {$x['role_id']} · {$x["group_id"]}"; }
item($R, 9, 'لا مجموعةَ تُصيَّر بصفرِ عناصرَ فعّالة',
     count($emptyGroups) === 0, count($emptyGroups), $grpTotal,
     'المقام: (دور × مجموعة) نشطة', array_slice($emptyGroups, 0, 5));

/* ══ ⑩ مراجعةُ تغطيةِ الأدوار — الأدوارُ النحيفةُ لا تُعتمد قبلَ المراجعة ═ */
$thin = array();
$q = $conn->query("SELECT n.role_id, COUNT(*) c FROM nav_items n
                    WHERE n.active = 1 AND n.route IS NOT NULL AND n.route <> '' AND n.route <> '#'
                    GROUP BY n.role_id HAVING c < 10");
while ($q && ($x = $q->fetch_assoc())) { $thin[] = "دور {$x['role_id']} ({$x['c']} رابطًا)"; }
$roleTotal = 0;
$q2 = $conn->query("SELECT COUNT(DISTINCT role_id) c FROM nav_items WHERE active = 1");
if ($q2) { $roleTotal = (int) $q2->fetch_assoc()['c']; }
item($R, 10, 'مراجعةُ تغطيةِ الأدوار — لا دورَ نحيفًا بلا مراجعة',
     count($thin) === 0, count($thin), $roleTotal,
     'المقام: أدوارٌ لها بنودُ سايدبارٍ نشطة', array_slice($thin, 0, 5));

/* ══ الإخراج ═════════════════════════════════════════════════════════════ */
echo "════ البنودُ العشرةُ الملزمةُ (ف١٥-١) — مقيسةً على النظامِ الحيّ ════\n\n";
$pass = 0; $notMeasured = 0;
ksort($R);
$MARK = array('PASS' => '✔', 'FAIL' => '✗', 'NOT_MEASURED' => '⛔');
foreach ($R as $no => $x) {
    printf("  %s %2d· %-56s %s\n", $MARK[$x['verdict']], $no, $x['title'],
           $x['den'] > 0 ? sprintf('%d/%d', $x['num'], $x['den']) : '**مقامٌ صفريٌّ — غيرُ مقيس**');
    if ($x['note'] !== '') { echo "        ◆ {$x['note']}\n"; }
    foreach ($x['sample'] as $s) { echo "        · {$s}\n"; }
    if ($x['verdict'] === 'PASS') { $pass++; }
    if ($x['verdict'] === 'NOT_MEASURED') { $notMeasured++; }
}
echo "\n════════════════════════════════════════════════════════════\n";
printf("  اجتاز %d · أخفق %d · **غيرُ مقيسٍ %d**\n", $pass, 10 - $pass - $notMeasured, $notMeasured);
if ($notMeasured > 0) {
    echo "⛔ لا يُعلَن اكتمالٌ وفي البابِ بندٌ **غيرُ مقيس** — والمقامُ الصفريُّ نجاحٌ كاذب\n";
} else {
    echo $pass === 10 ? "✔ العشرةُ مجتازةٌ مقيسةً\n" : "✗ ما لم يجتزْ يُرسِّب البناءَ بنصِّ ف١٥-١\n";
}

if (!empty($args['md'])) { /* لا شيء — يُضاف عند الحاجة */ }
exit($pass === 10 ? 0 : 1);
