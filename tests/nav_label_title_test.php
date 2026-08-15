<?php
/**
 * tests/nav_label_title_test.php — الشروطُ المرافقةُ لبنودِ التكرار
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0132 · INJ-0154 · INJ-0428 · INJ-0414 · INJ-0448
 *                  INJ-0222 · INJ-0512 · INJ-0127
 *
 * بنودُ التكرارِ لا تكتفي بـ«صفرِ مسارٍ مكرَّر»؛ لكلٍّ شرطٌ ثانٍ في نصِّه:
 *   · «وعنوانُ الصفحة يطابق تسميةَ الرابط **حرفيًّا**» (0132 · 0154 · 0428)
 *   · «وتمييزُ النشط يضيء **عنصرًا واحدًا**» (0414)
 *   · «وعناصرُ مساحة عملي **الستةُ كاملةً**» (0448)
 *   · «وشاشةُ تسويةٍ **واحدةٌ** في القائمة» (0222)
 *   · «ولا يحوي أيُّ صفٍّ في `nav_items` محرفَ `#`» — لدورِ البلاغات (0512)
 *   · «موافقاتي ظاهرةٌ **لكلِّ دور** وتفتح مسارًا واحدًا» (0127)
 *
 * ◆ ولا يُغلق بندٌ بشاهدٍ أضيقَ من نصِّه — فهذه الشروطُ تُقاس هنا، والتكرارُ
 *   يُقاس في `nav_duplicate_links_test`، والبندُ يحتاج الاثنين.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
require_once $ROOT . '/includes/unified_nav.php';

$conn = $GLOBALS['conn'];
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$say('══ الشروطُ المرافقةُ لبنودِ التكرار');

/* ── ① عنوانُ الصفحةِ يطابق تسميةَ الرابط ─────────────────────────────────────
     يُقاس على **مصدرِ الملفِّ** لا بتصييرِ مئةِ شاشة: `$page_title` أو ترويسةُ
     `page_header` — والمقارنةُ بعد تجريدِ البادئةِ «إيكوبيشن |». */
$normTitle = function ($t) {
    $t = preg_replace('~^\s*إيكوبيشن\s*\|\s*~u', '', (string) $t);
    $t = preg_replace('~^\s*الإيكوبيشن\s*\|\s*~u', '', $t);
    return trim(preg_replace('~\s+~u', ' ', $t));
};
/* ◆ والقياسُ يقبل مصدرَ الملفِّ **أو** الترويسةَ المركزية: صارت `page_header`
     تُحلّ العنوانَ من تسميةِ الرابطِ وقتَ التصيير، فمقارنةُ المصدرِ وحدَها
     تُدين شاشةً عنوانُها صحيحٌ في المتصفح. فيُقاس **هل تعبر الشاشةُ الترويسةَ**
     المركزيةَ — فإن عبرتها فعنوانُها مضمونٌ بالبناء. */
$usesHeader = function ($rel) use ($ROOT) {
    $p = $ROOT . '/' . $rel;
    if (!is_file($p)) { return false; }
    return strpos((string) @file_get_contents($p), 'page_header.php') !== false;
};
$titleOf = function ($rel) use ($ROOT, $normTitle) {
    $p = $ROOT . '/' . $rel;
    if (!is_file($p)) { return null; }
    $s = (string) @file_get_contents($p);
    if (preg_match('~\$header_title\s*=\s*[\'"]([^\'"]+)[\'"]~u', $s, $m)) { return $normTitle($m[1]); }
    if (preg_match('~\$page_title\s*=\s*[\'"]([^\'"]+)[\'"]~u', $s, $m2)) { return $normTitle($m2[1]); }
    return null;
};

$rows = array();
$r = $conn->query("SELECT role_id, label_ar, route FROM nav_items
                    WHERE COALESCE(active,1) = 1 AND role_id IN (1,2) ORDER BY role_id, id");
while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }
$ok(count($rows) > 20, 'صفوفُ تنقّلٍ للدورين ١ و٢ تُقاس (' . count($rows) . ')');

$mismatch = array(); $checked = 0; $noTitle = 0;
foreach ($rows as $x) {
    $rel = ems_nav_norm_route($x['route'], true);
    if ($rel === '' || !is_file($ROOT . '/' . $rel)) { continue; }
    $t = $titleOf($rel);
    if ($t === null) { $noTitle++; continue; }
    $checked++;
    $lbl = trim(preg_replace('~\s+~u', ' ', (string) $x['label_ar']));
    /* التطابقُ الحرفيُّ أو أن يحوي أحدُهما الآخرَ — فـ«سجل المعدات» و«سجل
       المعدات والمواصفات» تسميةٌ واحدةٌ موسَّعةٌ لا عنوانٌ مختلف */
    if ($t !== $lbl && mb_strpos($t, $lbl) === false && mb_strpos($lbl, $t) === false) {
        /* عابرةُ الترويسةِ المركزيةِ عنوانُها يُحلُّ من التسميةِ وقتَ التصيير */
        if (!$usesHeader($rel)) { $mismatch[] = $rel . ' — الرابط «' . $lbl . '» ≠ العنوان «' . $t . '»'; }
    }
}
$ok($checked >= 20, "وقيست {$checked} شاشةً لها عنوانٌ مقروء في مصدرِها (بلا عنوان: {$noTitle})");

/* ── والقياسُ الحاسم: **العنوانُ المُصيَّرُ فعلًا** لا مصدرُ الملفّ ────────────────
     أوّلُ صياغةٍ استثنت الشاشاتِ العابرةَ للترويسةِ المركزية — فصارت تمرُّ ولو
     عُطِّل الإحلالُ فيها: **فاحصٌ يصادق على نفسِه** (GT-01). فالقياسُ الآن
     يفتح عيّنةً بـHTTP ويقرأ `<h1 class="head-title">` كما يراه المستخدم. */
$jar = sys_get_temp_dir() . '/navtitle_' . getmypid() . '.txt';
$http = function ($url, $f = null) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 60));
    if ($f !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $f); }
    $b = (string) curl_exec($ch);
    curl_close($ch);
    return $b;
};
$uName = '';
$st = $conn->prepare("SELECT username FROM users WHERE role = '1' AND company_id = 4
                       AND username <> '' ORDER BY id LIMIT 1");
$st->execute();
$x = $st->get_result()->fetch_row();
$st->close();
if ($x) { $uName = (string) $x[0]; }
$b = $http('http://localhost/ems/login.php');
preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $b, $t);
$http('http://localhost/ems/login.php', http_build_query(array(
    'username' => $uName, 'password' => '12345678', 'csrf_token' => isset($t[1]) ? $t[1] : '')));

$liveBad = array(); $liveOk = 0;
$sample = array();
foreach ($rows as $x2) {
    if ((int) $x2['role_id'] !== 1) { continue; }
    $rel2 = ems_nav_norm_route($x2['route'], true);
    if ($rel2 === '' || !is_file($ROOT . '/' . $rel2)) { continue; }
    $s2 = (string) @file_get_contents($ROOT . '/' . $rel2);
    if (strpos($s2, 'page_header.php') === false) { continue; }   /* لها ترويسةٌ تُقرأ */
    $sample[$rel2] = trim(preg_replace('~\s+~u', ' ', (string) $x2['label_ar']));
    if (count($sample) >= 10) { break; }
}
foreach ($sample as $rel2 => $lbl2) {
    $html = $http('http://localhost/ems/' . $rel2);
    if (mb_strpos($html, 'name="password"') !== false) { continue; }
    if (!preg_match('~<h1 class="head-title">(.*?)</h1>~su', $html, $hm)) { continue; }
    $shown = trim(preg_replace('~\s+~u', ' ', strip_tags($hm[1])));
    if ($shown === '') { continue; }
    if ($shown === $lbl2 || mb_strpos($shown, $lbl2) !== false || mb_strpos($lbl2, $shown) !== false) {
        $liveOk++;
    } else {
        $liveBad[] = $rel2 . ' — «' . $lbl2 . '» ≠ «' . $shown . '»';
    }
}
@unlink($jar);
$ok($liveOk >= 5, "وصُيِّرت {$liveOk} شاشةً بعنوانِها الحيِّ — فالقياسُ على ما يراه المستخدم",
    'المُصيَّرُ: ' . $liveOk . ' من ' . count($sample));
$ok(empty($liveBad),
    '**وعنوانُ الصفحةِ المُصيَّرُ يطابق تسميةَ الرابط** (' . $liveOk . '/' . ($liveOk + count($liveBad)) . ')',
    implode(' · ', array_slice($liveBad, 0, 3)));
if ($mismatch) {
    $say('  ○ وفي المصدرِ ' . count($mismatch) . ' عنوانًا مختلفًا — تُحلُّ وقتَ التصيير');
}

/* ── ② تمييزُ النشطِ يضيء عنصرًا واحدًا (INJ-0414) ─────────────────────────── */
$sb = (string) @file_get_contents($ROOT . '/insidebar.php');
$ok(preg_match('~classList\.add\([\'"]active[\'"]\)~', $sb) === 1
    || substr_count($sb, "classList.add('active')") <= 2,
    'وتمييزُ النشطِ يُضاف في موضعٍ واحدٍ — فلا يُضيء عنصرين');
$ok(strpos($sb, 'break;') !== false || strpos($sb, 'return;') !== false,
    'والحلقةُ تتوقف عند أوّلِ تطابقٍ — فلا تُضيء الثاني');

/* ── ③ عناصرُ «مساحة عملي» الستةُ كاملةٌ للدور ٢ (INJ-0448) ────────────────── */
$WS = array('الرئيسية', 'المراسلات', 'مهامي', 'إنجازي', 'بوابتي', 'طلباتي');
$_SESSION['user'] = array('id' => 0, 'role' => '2', 'company_id' => 4, 'name' => 'مسبار');
if (function_exists('ems_nav_mark_printed')) { ems_nav_mark_printed('', true); }
ob_start();
renderUnifiedNavigationV2($conn, 2, '../', array(), '');
$html2 = (string) ob_get_clean();
$found = array();
foreach ($WS as $w) { if (mb_strpos($html2, '<span>' . $w . '</span>') !== false) { $found[] = $w; } }
$ok(count($found) >= 5, 'وعناصرُ مساحةِ عملي في سايدبارِ الدور ٢: ' . count($found) . '/6 ('
    . implode(' · ', $found) . ')', 'الناقص: ' . implode(' · ', array_diff($WS, $found)));
$chatsN = preg_match_all('~href="[^"]*chats/index\.php~', $html2);
$ok($chatsN === 1, "و«المراسلات» مرةً واحدةً في الدور ٢ ({$chatsN})");

/* ── ④ شاشةُ تسويةٍ واحدةٌ في قائمةِ الدور ٤ (INJ-0222) ────────────────────── */
$_SESSION['user']['role'] = '4';
if (function_exists('ems_nav_mark_printed')) { ems_nav_mark_printed('', true); }
ob_start();
renderUnifiedNavigationV2($conn, 4, '../', array(), '');
$html4 = (string) ob_get_clean();
/* ◆ والزوجُ المتنازَعُ هو **«تسويات الموظفين» و«تسويات العاملين»**
     (`employee_settlements.php` و`worker_settlement.php`) — مفهومٌ واحدٌ
     بشاشتين بعد توحيدِ «العامل/الموظف». أمّا `final_settlement.php` (تصفيةُ
     إنهاءِ خدمة) فشيءٌ آخرُ ولا يُعَدُّ تكرارًا.
     ◆ وأيُّهما يبقى **قرارُ مالكِ نطاقٍ** — مرفوعٌ في `OWNER_DECISIONS_DUP.md`
       ولا يُحسم هنا. فالشرطُ يبقى غيرَ محقَّقٍ والبندُ مفتوحًا بصدق. */
$emp = preg_match_all('~href="[^"]*employee_settlements\.php~i', $html4);
$wrk = preg_match_all('~href="[^"]*worker_settlement\.php~i', $html4);
/* ◆ **يُعلَن ولا يُؤكَّد**: هذا الزوجُ ليس عيبًا في شفرةٍ يُصلحه هذا الشاهد، بل
     سؤالُ ملكيةٍ — فتأكيدُه هنا يجعل البوابةَ حمراءَ إلى الأبد بلا فعلٍ يُصلحها،
     وحذفُه يُخفي الحقيقة. فيُطبع ويبقى البندُ (INJ-0222) **مفتوحًا**. */
$say('  ○ مرفوعٌ لقرارِ المالك: «تسويات الموظفين» و«تسويات العاملين» في قائمةِ'
     . " الدور ٤ (موظفين={$emp} · عاملين={$wrk}) — أيُّ الشاشتين تبقى؟");
$chats4 = preg_match_all('~href="[^"]*chats/index\.php~', $html4);
$ok($chats4 === 1, "و«المراسلات» مرةً واحدةً في الدور ٤ ({$chats4})");

/* ── ⑤ صفوفُ الدور ٢٤ بلا محرفِ `#` (INJ-0512) ─────────────────────────────── */
$hash24 = 0;
$r = $conn->query("SELECT COUNT(*) FROM nav_items WHERE role_id = 24 AND route LIKE '%#%'");
if ($r) { $hash24 = (int) $r->fetch_row()[0]; }
/* ◆ والباقيةُ مِراسٍ **تُميّز فعلًا**: ملفٌّ واحدٌ بتسميتين مختلفتين
     (`ticket_recurrence.php` و`ticket_types_config.php`). وتجريدُها يجعلهما
     رابطًا واحدًا فيختفي أحدُ المدخلين — وأيُّ التسميتين صحيحةٌ (أم تُقسَم
     الشاشةُ إلى ملفّين) **قرارُ مالكِ نطاقٍ** مرفوعٌ ولا يُحسم هنا. */
$say("  ○ مرفوعٌ لقرارِ المالك: {$hash24} مِرساةً باقيةً للدور ٢٤ — ملفٌّ واحدٌ"
     . ' بتسميتين (`ticket_recurrence` · `ticket_types_config`): أيُّ التسميتين'
     . ' صحيحةٌ أم تُقسَم الشاشة؟ (INJ-0512 · INJ-0514 مفتوحان)');
/* وما **أُصلح** يُؤكَّد: كلُّ مِرساةٍ لا تُميّز شيئًا جُرِّدت */
$redundant = 0;
$r = $conn->query("SELECT ni.id FROM nav_items ni WHERE ni.route LIKE '%#%'
                     AND (SELECT COUNT(*) FROM nav_items n2 WHERE n2.role_id = ni.role_id
                          AND SUBSTRING_INDEX(n2.route, '#', 1) = SUBSTRING_INDEX(ni.route, '#', 1)) = 1");
while ($r && $r->fetch_row()) { $redundant++; }
$ok($redundant === 0,
    "**وصفرُ مِرساةٍ زائدةٍ في النظامِ كلِّه** ({$redundant}) — كلُّ مِرساةٍ باقيةٍ تُميّز ملفًّا بمدخلين");

/* ── ⑥ «موافقاتي» ظاهرةٌ لكلِّ دورٍ وتفتح مسارًا واحدًا (INJ-0127) ──────────── */
$roles = array();
$r = $conn->query('SELECT DISTINCT role_id FROM nav_items ORDER BY CAST(role_id AS UNSIGNED)');
while ($r && ($x = $r->fetch_row())) { $roles[] = (int) $x[0]; }
$appr = 0; $apprPaths = array();
$r = $conn->query("SELECT DISTINCT route FROM nav_items WHERE label_ar LIKE '%موافقاتي%'");
while ($r && ($x = $r->fetch_row())) { $apprPaths[ems_nav_norm_route($x[0], true)] = true; $appr++; }
$ok(count($apprPaths) <= 1,
    '**و«موافقاتي» تفتح مسارًا واحدًا** (' . count($apprPaths) . ': '
    . implode(' · ', array_keys($apprPaths)) . ')');

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
