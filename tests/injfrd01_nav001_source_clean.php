<?php
/**
 * tests/injfrd01_nav001_source_clean.php
 *   شاهدُ FR-NAV-001 — **الفحصُ على المصدرِ لا المخرَج**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **معيارُ القبولِ بنصِّ الدفتر**: «فحصٌ على المصدرِ لا المخرَجِ يُخرج صفرَ
 *   صيغة» · والاختبارُ السالب: «صيغةٌ ممنوعةٌ باقيةٌ ← يُرسِّب التفعيل».
 *
 * ◆ **والقائمةُ المحظورةُ تُقرأ من نصِّ الوثيقةِ الحاكمةِ وقتَ التشغيل** — لا
 *   تُكتب حرفًا هنا. فلو زِيدت صيغةٌ في الوثيقةِ قاسها الشاهدُ بلا تعديل.
 *
 * ◆ **والاستثناءُ يُعلَن لا يُخمَّن**: ثلاثُ مجموعاتٍ تبدأ بالنونِ وهي **أسماءٌ
 *   لا أفعال** (ناقلُ الأحداث · نماذجُ العمل · نماذجُ التمويل). فتُدرَج في
 *   قائمةٍ بيضاءَ **معلَنةٍ باسمِها** — والنمطُ وحدَه يتّهم البريء.
 *
 * التشغيل: php tests/injfrd01_nav001_source_clean.php [--negative]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$db = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($db->connect_errno) { exit("تعذّر الاتصال: {$db->connect_error}\n"); }
$db->set_charset('utf8mb4');

/** أسماءٌ تبدأ بالنونِ وليست أفعالًا — استثناءٌ **معلَنٌ باسمِه** لا بالنمط.
 *  ◆ ولكلِّ اسمٍ سببُ استثنائِه مكتوبًا: كلُّها **مصادرُ لا أفعالُ متكلِّم**
 *    (ناقل · نموذج/نماذج · نطاقات) — فالنمطُ `^ن` وحدَه لا يفرّق بينهما،
 *    والفارقُ يُعلَن بالاسمِ لا يُخمَّن بالحرف. **ولا يُضاف إلى هذه القائمةِ
 *    فعلُ متكلِّمٍ لتخضرَّ البوابةُ** — فذلك تبييضٌ لا استثناء. */
$NOUN_WHITELIST = array(
    'ناقلُ الأحداث',                  /* اسمُ فاعلٍ — مكوّنُ الأحداثِ المركزيّ */
    'نماذج العمل ووحدات القياس',      /* جمعُ «نموذج» */
    'نماذج العمل ووحداتها',           /* جمعُ «نموذج» — صيغةٌ أقصر */
    'نماذج التمويل',                  /* جمعُ «نموذج» */
    'نماذج التمويل ومعالجتها',        /* جمعُ «نموذج» — صيغةُ الكنسيّ */
    'نموذج التعاقد ووحدته',           /* مفردُ «نموذج» */
    'نطاقات العقد التشغيلية',         /* جمعُ «نطاق» */
);

/* القائمةُ المحظورةُ من نصِّ الوثيقةِ الحاكمة — لا من ذاكرةِ الكاتب */
function docx_text($path)
{
    $z = new ZipArchive();
    if ($z->open($path) !== true) { return ''; }
    $xml = $z->getFromName('word/document.xml');
    $z->close();
    if ($xml === false) { return ''; }
    $xml = preg_replace('~</w:p>~', "\n", $xml);
    return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
}
$banned = array();
foreach (glob($ROOT . '/docs/sources/*/*.docx') as $f) {
    $t = docx_text($f);
    if (preg_match('~ممنوعةٌ منعًا باتًّا في التنقّل[^\n]*\n([^\n]+)~u', $t, $m)) {
        foreach (explode('·', $m[1]) as $x) { $x = trim($x); if ($x !== '') { $banned[$x] = true; } }
    }
}
/* المصدرُ الثاني: وثيقتا المواءمةِ في مجلدِ النصوصِ إن كانتا محفوظتين */
$banned = array_keys($banned);

$ok = 0; $bad = 0;
function chk($c, $l, $d = '') {
    global $ok, $bad;
    if ($c) { $ok++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $bad++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}
function n(mysqli $d, $q) { $r = @$d->query($q); return $r ? (int) $r->fetch_row()[0] : -1; }

echo "══ FR-NAV-001 · الفحصُ على مصدرِ الدورةِ لا على المخرَج ══\n";

$neg = in_array('--negative', $argv, true);
$MARK = 'NAV001BELT';
if ($neg) {
    /* ◆ **الحزامُ السلبيُّ يدسُّ صيغةً ممنوعةً حيّةً ثم يكنسها** — فبوابةٌ لم
     *   تُجرَّب معطوبةً لا تُصدَّق. والدسُّ في صفٍّ جديدٍ لا في صفٍّ قائم. */
    $db->query("INSERT INTO `gov_screen_cycle`
        (`company_id`,`dept_name`,`layer_name`,`stage_order`,`stage_name`,`group_name`,
         `screen_title`,`screen_file`,`stage_kind`)
        VALUES (4,'{$MARK}','اختبار',999,'مرحلةُ حزام','نحاسبه ونصرف','شاشةُ حزام','belt/{$MARK}.php','عادي')");
    /* ◆ **وبذرُ تصريفٍ متقاعدٍ لا تمسكه السلسلةُ الحرفية** (البند ٠-٥):
     *   «وحاوياتها» — الجذرُ فيها `حاوي`، و`LIKE '%الحاويات%'` يعبرها لأن
     *   «ال» صارت «و…ها». */
    $db->query("INSERT INTO `gov_screen_cycle`
        (`company_id`,`dept_name`,`layer_name`,`stage_order`,`stage_name`,`group_name`,
         `screen_title`,`screen_file`,`stage_kind`)
        VALUES (4,'{$MARK}','اختبار',998,'مرحلةُ حزامٍ ثانية','حصصٌ وحاوياتها للحزام',
                'شاشةُ حزامٍ ثانية','belt/{$MARK}2.php','عادي')");
    /* ◆ **والبذرُ في الجدولِ الذي يُرسَم منه أيضًا** (البند ٠-٤): مجموعةٌ نشطةٌ
     *   في `link_groups` **ومعها بندُ تنقّلٍ نشط** — فمجموعةٌ بلا بندٍ لا تُصيَّر
     *   ولا تُقاس، وبذرٌ لا يُصيَّر لا يُجرّب الحارسَ في موضعِ عملِه.
     *   ◆ و`insert_id` يُلتقط **عقبَ إدراجِه مباشرةً** — فبذرٌ آخرُ بينهما
     *     يُزيح المعرِّفَ إلى صفٍّ آخرَ فيُربَط البندُ بمجموعةٍ ليست الهدف. */
    $db->query("INSERT INTO `link_groups` (`name`,`group_code`,`icon`,`display_order`,`is_active`)
                VALUES ('نحاسبه ونصرف','{$MARK}','fa fa-folder',9999,1)");
    $BELT_GID = (int) $db->insert_id;
    if ($BELT_GID > 0) {
        $d = @$db->query("SELECT `door` FROM `nav_items` WHERE `role_id` = 12 LIMIT 1");
        $door = ($d && ($x = $d->fetch_row())) ? $db->real_escape_string($x[0]) : '';
        $db->query("INSERT INTO `nav_items`
                     (`role_id`,`door`,`label_ar`,`route`,`group_id`,`sort_order`,`active`)
                     VALUES (12,'{$door}','بندُ حزام','__belt/{$MARK}.php',{$BELT_GID},9999,1)");
    }
    echo "  ◆ دُسَّت صيغةٌ ممنوعةٌ في المصدرِ والمُصيَّر، وتصريفٌ متقاعدٌ للحزامِ السلبيّ\n";
}

/* ① القائمةُ المحظورةُ قُرئت */
/* ◆ **قائمةٌ فارغةٌ تصنع خضرةً كاذبة**: لو لم تُقرأ الوثيقةُ لعاد الفحصُ
 *   التالي «صفرُ صيغةٍ محظورة» — وهو صفرٌ لأن لا شيءَ يُبحث عنه لا لأن
 *   المصدرَ نظيف. ⇒ القائمةُ الفارغةُ **توقف الشاهدَ** ولا تمرّ. */
chk(count($banned) > 0, 'القائمةُ المحظورةُ تُقرأ من نصِّ الوثيقة',
    count($banned) . ' صيغةً محظورة');
if (count($banned) === 0) {
    echo "
⛔ **لا قائمةَ محظورةً تُقرأ** — والفحصُ بلا قائمةٍ خضرةٌ كاذبة. أُوقِف.
";
    exit(1);
}

/* ② صفرُ صيغةٍ محظورةٍ في **المصدر** */
$hits = array(); $rows = 0;
foreach ($banned as $b) {
    $e = $db->real_escape_string($b);
    $c = n($db, "SELECT COUNT(*) FROM `gov_screen_cycle`
                  WHERE `group_name` = '{$e}' OR `stage_name` = '{$e}'");
    if ($c > 0) { $hits[] = "{$b}×{$c}"; $rows += $c; }
}
chk(empty($hits), '**صفرُ صيغةٍ محظورةٍ في مصدرِ الدورة**',
    empty($hits) ? '0 صفًّا' : "{$rows} صفًّا: " . implode(' · ', array_slice($hits, 0, 5)));

/* ③ صفرُ مجموعةٍ فعليةٍ خارجَ القائمةِ البيضاءِ المُعلَنة */
$verbRows = array();
$r = $db->query("SELECT `group_name`, COUNT(*) c FROM `gov_screen_cycle`
                  WHERE `group_name` REGEXP '^ن[^ ]+' OR `group_name` LIKE 'نحن%'
                  GROUP BY 1");
while ($r && $x = $r->fetch_assoc()) {
    if (in_array($x['group_name'], $NOUN_WHITELIST, true)) { continue; }
    $verbRows[] = $x['group_name'] . '×' . $x['c'];
}
chk(empty($verbRows), 'وصفرُ مجموعةٍ فعليةٍ خارجَ الاستثناءِ المُعلَن',
    empty($verbRows) ? 'صفر · والمُعلَنُ ' . count($NOUN_WHITELIST) . ' اسمًا'
                     : implode(' · ', $verbRows));

/* ④ صفرُ لفظٍ متقاعد — **مطابقةٌ بالجذرِ لا بالسلسلةِ الحرفية** (البند ٠-٥) ──
 * ◆ كان الفحصُ `LIKE '%الحاويات%'` — فلا يمسك التصريف: صفّانِ حيّانِ في
 *   `gov_screen_cycle` (420 · 421) يحملان «حصص الموردين **وحاوياتها**»
 *   ويعبران، لأن «ال» صارت «و…ها». **والسلسلةُ الحرفيةُ تمسك صيغةً وتترك أسرتَها.**
 * ◆ ومصدرُ التقاعدِ ليس ذاكرةَ الكاتب: `gov_cycle_name_log` يحمله مكتوبًا —
 *   «توزيع الحصص والحاويات» ⇐ «توزيع الحصص وإسناد المعدات» ·
 *   «نوزّع الحاويات»        ⇐ «إسناد المعدات للوحدات».
 *   ولذلك **يُفحص أن النمطَ ما يزال يمسك ما في السجل** (الفحص ⑧ أدناه) —
 *   فنمطٌ يتفرّق عن سجلِّ التقاعدِ يتعفّن صامتًا.
 * ◆ والمقامُ **مصدرانِ لا مصدر**: `gov_screen_cycle` الحاكمُ، و`link_groups`
 *   الذي يُرسَم منه — ويُفصَل المُصيَّرُ عن الراكدِ فلا يُخفى أحدُهما بالآخر.
 */
$RETIRED = array(
    'حاوي'      => 'حاوية · حاويات · وحاوياتها — متقاعدةٌ بسجلِّ `gov_cycle_name_log`',
    'خان[ةات]'  => 'خانة · خانات — متقاعدةٌ بالحارسِ السابقِ ولا قيدَ لها في السجل',
);
$retHits = array(); $retRend = 0; $retDorm = 0; $retCycle = 0;
foreach ($RETIRED as $rx => $why) {
    $e = $db->real_escape_string($rx);
    /* ① المصدرُ الحاكم */
    $r = $db->query("SELECT `group_name` AS v, COUNT(*) c FROM `gov_screen_cycle`
                      WHERE `group_name` REGEXP '{$e}' OR `stage_name` REGEXP '{$e}' GROUP BY 1");
    while ($r && $x = $r->fetch_assoc()) {
        $retHits[] = "دورة«{$x['v']}»×{$x['c']}"; $retCycle += (int) $x['c'];
    }
    /* ② الجدولُ الذي يُرسَم منه — مُصيَّرًا وراكدًا، كلٌّ بعدَده */
    $r = $db->query("SELECT g.`name` AS nm, COALESCE(g.`stage_title`,'') AS st,
                            (SELECT COUNT(*) FROM `nav_items` n
                              WHERE n.`group_id` = g.`id` AND n.`active` = 1) AS items
                       FROM `link_groups` g
                      WHERE g.`is_active` = 1
                        AND (g.`name` REGEXP '{$e}' OR g.`stage_title` REGEXP '{$e}')");
    while ($r && $x = $r->fetch_assoc()) {
        $live = ((int) $x['items'] > 0);
        if ($live) { $retRend++; } else { $retDorm++; }
        $lbl = $live ? 'مُصيَّر' : 'راكد';
        $retHits[] = "{$lbl}«" . ($x['st'] !== '' ? $x['st'] : $x['nm']) . '»';
    }
    /* ③ الكنسيّ */
    $r = $db->query("SELECT `canonical_ar` AS v, COUNT(*) c FROM `nav_canonical`
                      WHERE `canonical_ar` REGEXP '{$e}' GROUP BY 1");
    while ($r && $x = $r->fetch_assoc()) { $retHits[] = "كنسيّ«{$x['v']}»×{$x['c']}"; }
}
$retTot = $retCycle + $retRend + $retDorm;
chk($retTot === 0, 'وصفرُ لفظٍ متقاعدٍ **بالجذرِ** في المصادرِ الثلاثة',
    $retTot === 0 ? 'صفر · والجذورُ المُعلَنةُ ' . count($RETIRED)
                  : "دورة={$retCycle} · مُصيَّر={$retRend} · راكد={$retDorm} ⇒ **{$retTot}**: "
                    . implode(' · ', array_slice($retHits, 0, 6)));

/* ⑧ النمطُ ما يزال يمسك سجلَّ التقاعدِ نفسَه — **وإلا تعفّن صامتًا** */
$logOld = array(); $missPat = array();
$r = $db->query("SELECT DISTINCT `old_value` FROM `gov_cycle_name_log`
                  WHERE `old_value` IS NOT NULL AND `old_value` <> ''");
while ($r && $x = $r->fetch_row()) { $logOld[] = $x[0]; }
foreach ($logOld as $ov) {
    $isRetired = false;
    foreach ($RETIRED as $rx => $why) { if (preg_match('/' . $rx . '/u', $ov)) { $isRetired = true; } }
    /* قيدٌ يحمل لفظًا متقاعدًا في قيمتِه القديمةِ يجب أن يمسكه نمطٌ — والعكسُ لا يلزم */
    if (!$isRetired && preg_match('/حاوي|خان[ةات]/u', $ov)) { $missPat[] = $ov; }
}
chk(empty($missPat), 'وأنماطُ الجذرِ ما تزال تمسك ما في سجلِّ التقاعد',
    empty($missPat) ? count($logOld) . ' قيمةً قديمةً فُحصت · صفرُ لفظٍ متقاعدٍ بلا نمطٍ يمسكه'
                    : implode(' · ', $missPat));

/* ⑤ صفرُ فقد — السجلُّ يحمل ما تغيَّر ويُرجعه */
$logged = n($db, "SELECT COUNT(*) FROM `gov_cycle_name_log` WHERE `requirement_id` = 'FR-NAV-001'");
chk($logged > 0, 'وكلُّ تغييرٍ محفوظٌ بقيمتِه السابقةِ فالرجوعُ ممكن',
    "{$logged} قيدَ تغيير");

/* ═══ الجدولُ الذي يُرسَم منه — لا الجدولُ الذي نُظِّف (البند ٠-٤) ═══════════
 * ◆ **الحارسُ كان يفحص ما نظَّفه لا ما يُقرأ منه.** فحوصُ ①..⑤ أعلاه تقيس
 *   `gov_screen_cycle` — وهو **سجلُّ دورةٍ حاكم**، والسايدبارُ لا يُرسَم منه.
 *   المُصيَّرُ يأتي من `link_groups` (الاسمُ وعنوانُ المرحلة) عبر
 *   `includes/unified_nav.php` حيث `LEFT JOIN link_groups g ... g.is_active = 1`.
 *   فبقيت `gov_screen_cycle` خمسةَ صفوفٍ مشروعةً ⇒ أخضر، بينما `link_groups`
 *   تحمل أفعالَ متكلِّمٍ **مُصيَّرةً للمستخدم**. وخضرةٌ كهذه أسوأُ من حمرة.
 * ◆ والمقامُ هنا **ما يُصيَّر فعلًا**: مجموعةٌ نشطةٌ لها بندُ تنقّلٍ نشطٌ واحدٌ
 *   على الأقل — فمجموعةٌ بلا بندٍ لا يراها أحد، ولا تُحسب خرقًا ولا تُخفى.
 */
$RENDERED = "FROM `link_groups` g
              JOIN `nav_items` n ON n.`group_id` = g.`id` AND n.`active` = 1
             WHERE g.`is_active` = 1";
$rendTot = n($db, "SELECT COUNT(DISTINCT g.`id`) {$RENDERED}");

/* ⑥ صفرُ صيغةٍ محظورةٍ في **المُصيَّر** — الاسمُ وعنوانُ المرحلةِ والكنسيّ */
$hitsLG = array(); $rowsLG = 0;
foreach ($banned as $b) {
    $e = $db->real_escape_string($b);
    foreach (array('name' => 'الاسم', 'stage_title' => 'عنوانُ المرحلة') as $col => $lbl) {
        $c = n($db, "SELECT COUNT(DISTINCT g.`id`) {$RENDERED} AND g.`{$col}` = '{$e}'");
        if ($c > 0) { $hitsLG[] = "{$lbl}«{$b}»×{$c}"; $rowsLG += $c; }
    }
    $c = n($db, "SELECT COUNT(*) FROM `nav_canonical` WHERE `canonical_ar` = '{$e}'");
    if ($c > 0) { $hitsLG[] = "كنسيّ«{$b}»×{$c}"; $rowsLG += $c; }
}
chk(empty($hitsLG), '**صفرُ صيغةٍ محظورةٍ في المُصيَّرِ** (`link_groups` و`nav_canonical`)',
    empty($hitsLG) ? "0 من {$rendTot} مجموعةً مُصيَّرة"
                   : "{$rowsLG}: " . implode(' · ', array_slice($hitsLG, 0, 5)));

/* ⑦ صفرُ فعلِ متكلِّمٍ مُصيَّرٍ خارجَ الاستثناءِ المُعلَن */
$verbLG = array(); $verbGroups = 0;
foreach (array('name' => 'اسمُ مجموعة', 'stage_title' => 'عنوانُ مرحلة') as $col => $lbl) {
    $r = $db->query("SELECT g.`{$col}` AS v, COUNT(DISTINCT g.`id`) c {$RENDERED}
                       AND (g.`{$col}` REGEXP '^ن[^ ]+' OR g.`{$col}` LIKE 'نحن%'
                            OR g.`{$col}` LIKE 'دعنا%' OR g.`{$col}` LIKE 'هيا%')
                     GROUP BY 1");
    while ($r && $x = $r->fetch_assoc()) {
        if ($x['v'] === null || $x['v'] === '') { continue; }
        if (in_array($x['v'], $NOUN_WHITELIST, true)) { continue; }
        $verbLG[] = "{$lbl}«{$x['v']}»×{$x['c']}";
        $verbGroups += (int) $x['c'];
    }
}
chk(empty($verbLG), 'وصفرُ فعلِ متكلِّمٍ **مُصيَّرٍ** خارجَ الاستثناءِ المُعلَن',
    empty($verbLG) ? "صفر من {$rendTot} مجموعةً مُصيَّرة · والمُعلَنُ " . count($NOUN_WHITELIST) . ' اسمًا'
                   : "{$verbGroups} مجموعةً مُصيَّرة: " . implode(' · ', $verbLG));

if ($neg) {
    /* ◆ **الحكمُ مُصوَّبٌ على البذرِ بعينِه لا على «رسوبٍ ما»**: الشاهدُ اليومَ
     *   يرسُب لسببٍ قائمٍ (أفعالُ متكلِّمٍ مُصيَّرة)، فحزامٌ يكتفي بـ`bad > 0`
     *   يخضرُّ من غيرِ أن يُجرَّب. فيُشترط أن **يُسمّى البذرُ** في مخرَجِ الفحصَين. */
    $caughtSrc = false;
    foreach ($hits as $h)   { if (strpos($h, 'نحاسبه ونصرف') !== false) { $caughtSrc = true; } }
    $caughtRetired = false;
    foreach ($retHits as $h) { if (strpos($h, 'وحاوياتها للحزام') !== false) { $caughtRetired = true; } }
    $caughtRend = false;
    foreach ($hitsLG as $h) { if (strpos($h, 'نحاسبه ونصرف') !== false) { $caughtRend = true; } }
    echo "\n── حكمُ الحزامِ السلبيّ ──\n";
    chk($caughtSrc,  '**البذرُ في مصدرِ الدورةِ أُمسك باسمِه**',
        $caughtSrc ? 'نحاسبه ونصرف' : 'مرَّ — والحارسُ لا يراه');
    chk($caughtRend, '**والبذرُ في الجدولِ الذي يُرسَم منه أُمسك باسمِه**',
        $caughtRend ? 'نحاسبه ونصرف' : 'مرَّ — والحارسُ لا يراه');
    chk($caughtRetired, '**وتصريفُ اللفظِ المتقاعدِ أُمسك بالجذرِ**',
        $caughtRetired ? 'حصصٌ وحاوياتها للحزام — والسلسلةُ الحرفيةُ كانت تعبره'
                       : 'مرَّ — والمطابقةُ ما تزال حرفية');
    /* الكنسُ بالعائلةِ — الوسمُ ثابتٌ لا بـ`getmypid` فيُكنس أثرُ جولةٍ انهارت */
    $db->query("DELETE FROM `gov_screen_cycle` WHERE `dept_name` = '{$MARK}'");
    $db->query("DELETE FROM `nav_items` WHERE `route` = '__belt/{$MARK}.php'");
    $db->query("DELETE FROM `link_groups` WHERE `group_code` = '{$MARK}'");
    $left = n($db, "SELECT COUNT(*) FROM `gov_screen_cycle` WHERE `dept_name` = '{$MARK}'")
          + n($db, "SELECT COUNT(*) FROM `link_groups` WHERE `group_code` = '{$MARK}'")
          + n($db, "SELECT COUNT(*) FROM `nav_items` WHERE `route` = '__belt/{$MARK}.php'");
    chk($left === 0, 'وكُنس الحزامُ أثرَه من الجداولِ الثلاثة', "المتبقي: {$left}");
    echo "\n◆ والحزامُ **يُثبت الإمساكَ لا الرسوبَ المجرَّد** — فالشاهدُ يرسُب اليومَ\n";
    echo "  لسببٍ قائم، ورسوبٌ لا يُنسب إلى البذرِ لا يُثبت أن الحارسَ يراه.\n";
    exit(($caughtSrc && $caughtRend && $caughtRetired && $left === 0) ? 0 : 1);
}

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
