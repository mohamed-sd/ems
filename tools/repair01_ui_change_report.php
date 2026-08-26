<?php
/**
 * tools/repair01_ui_change_report.php — تقريرُ تغييراتِ واجهةِ المستخدم W01..W13
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مولَّدٌ من المخزنِ لا مسرودٌ من الذاكرة**: كلُّ صفٍّ في كلِّ جدولٍ مقروءٌ
 *   من `repair01_*` و`gov_*` و`nav_*` — فلا سطرَ بلا مصدرٍ يُعاد قياسُه.
 *
 * ◆ **والمفرداتُ تختلف بالموجة**: الموجاتُ الأولى تحفظ **الفعلَ**
 *   (`CORRECTED_TO_CANONICAL` · `LABEL_DRIFT` · `GROUP_DRIFT`) والمتأخّرةُ
 *   تحفظ **الحالةَ بعد الفعل** (`LABEL_MATCH`). فتُترجَم كلُّ مفردةٍ بجدولِها
 *   هي ⛔ ولا تُسقَط مفردةُ موجةٍ على أخرى.
 *
 * ◆ **والمرحلةُ السادسةُ مستثناةٌ بطلبِ المالك**: نطاقُها تنقيةُ تشكيلٍ في
 *   أكثرَ من ثمانمئةِ ملفٍّ — تغييرٌ في **حروفِ النصِّ لا في بنيةِ الشاشة**،
 *   فسردُها صفًّا صفًّا يُغرق التقريرَ بما لا يفيد القارئ. وتُذكَر بعددِها.
 *
 * التشغيل: php tools/repair01_ui_change_report.php
 * المخرَج : docs/REPAIR01_20260823/UI_CHANGES_W01_W13.md
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$rows = function ($sql) use ($conn) {
    $r = $conn->query($sql);
    if (!$r) { fwrite(STDERR, "SQL: " . $conn->error . "\n  $sql\n"); return array(); }
    $o = array(); while ($x = $r->fetch_assoc()) { $o[] = $x; } return $o;
};
$one = function ($sql) use ($conn) { $r = $conn->query($sql); if (!$r) { return 0; } $x = $r->fetch_row(); return $x ? $x[0] : 0; };

/* ═══ ① الإداراتُ — الرمزُ إلى الاسمِ العربيّ ═══════════════════════════════ */
$DEPT = array();
foreach ($rows("SELECT canonical_code, name_ar FROM repair01_departments") as $d) {
    $DEPT[$d['canonical_code']] = $d['name_ar'];
}
$dept = function ($code) use ($DEPT) {
    $c = trim((string) $code);
    if ($c === '') { return '—'; }
    return isset($DEPT[$c]) ? $DEPT[$c] : $c;
};

/* ═══ ② المسمّى — بترجيحِ المصدرِ لا بموضعِه ═════════════════════════════════
     `nav_canonical` المعتمَدُ يغلب غيرَ المعتمَدِ يغلب دورةَ العملِ يغلب اسمَ
     الملفّ. (‏قاعدةُ «الاسمُ أربعةُ مصادرَ» المسجَّلةُ في الحملة) */
$LBL = array();
foreach ($rows("SELECT route, canonical_ar, status FROM nav_canonical WHERE canonical_ar <> ''") as $n) {
    $k = strtolower(basename($n['route']));
    if (!isset($LBL[$k]) || $n['status'] === 'APPROVED') { $LBL[$k] = $n['canonical_ar']; }
}
foreach ($rows("SELECT screen_file, screen_title FROM gov_screen_cycle WHERE screen_title <> ''") as $g) {
    $k = strtolower(basename($g['screen_file']));
    if (!isset($LBL[$k])) {
        /* ذيلٌ تقنيٌّ بين قوسَين يُنزَع — الاسمُ للقارئِ لا للمبرمج */
        $LBL[$k] = trim(preg_replace('~\s*\([^)]*\.php\)\s*$~u', '', $g['screen_title']));
    }
}
/* ◆ **وذيلانِ آخرانِ قبل اسمِ الملفّ**: بندُ القائمةِ الحيُّ ثمَّ اسمُ الموديول
     — فأربعَ عشرةَ شاشةً بلا اسمٍ معياريٍّ ولا صفِّ دورةٍ كانت تُطبَع بمسارِها
     وهو **معرِّفٌ تقنيٌّ لا يقرؤه صاحبُ العمل**. وبادئةُ العلامةِ التجاريّةِ
     في اسمِ الموديولِ تُنزَع — «إيكوبيشن ¦ بطاقة العميل» اسمُ صفحةٍ لا اسمُ بند. */
foreach ($rows("SELECT route, label_ar FROM nav_items WHERE label_ar <> ''") as $n) {
    $k = strtolower(basename($n['route']));
    if (!isset($LBL[$k])) { $LBL[$k] = $n['label_ar']; }
}
foreach ($rows("SELECT code, name FROM modules WHERE name <> ''") as $n) {
    $k = strtolower(basename($n['code']));
    if (isset($LBL[$k])) { continue; }
    $nm = preg_replace('~^\s*[^|]*\|\s*~u', '', $n['name']);
    $LBL[$k] = trim($nm) !== '' ? trim($nm) : $n['name'];
}
$label = function ($route) use ($LBL) {
    $k = strtolower(basename($route));
    return isset($LBL[$k]) && $LBL[$k] !== '' ? $LBL[$k] : basename($route);
};

/* ═══ ③ أختامُ النموِّ — أيُّ موجةٍ أنشأت أيَّ سطح ═══════════════════════════ */
$BORN = array(); $BORN_DEPT = array();
foreach ($rows("SELECT screen_file, origin, owner_code FROM repair01_screen_registry
                 WHERE origin LIKE 'W%' AND origin NOT IN ('SURFACES','DISK','NAV')") as $s) {
    $w = (int) preg_replace('~\D~', '', $s['origin']);
    $BORN[$w][strtolower(basename($s['screen_file']))] = true;
    $BORN_DEPT[strtolower(basename($s['screen_file']))] = $s['owner_code'];
}

/* ═══ ④ سجلُّ الإخفاءِ المنسوبُ للحملة ═══════════════════════════════════════ */
$HID = array();
foreach ($rows("SELECT doc_code, route, label_ar, group_before, reachable
                  FROM gov_nav_hidden_log WHERE doc_code LIKE 'RPR-W%'") as $h) {
    $w = (int) preg_replace('~\D~', '', $h['doc_code']);
    $HID[$w][] = $h;
}

/* ═══ ⑤ الفجواتُ الموفّاةُ بملفٍّ قائمٍ باسمٍ آخر ══════════════════════════════ */
$GAPMET = array();
foreach ($rows("SELECT wave_stage, unit, surface_name, built_counterpart
                  FROM repair01_target_gaps WHERE built_counterpart <> '' AND wave_stage <> ''") as $g) {
    $w = (int) preg_replace('~\D~', '', $g['wave_stage']);
    $GAPMET[$w][strtolower(basename($g['built_counterpart']))] = $g['surface_name'];
}

/* ═══ ⑥ ترجمةُ الأحكامِ — لكلِّ عمودٍ قاموسُه ═══════════════════════════════
     ⛔ **ولا تُسقَط مفردةُ موجةٍ على أخرى**: `ALIGNED` في W03 تعني «طابق
        سلفًا» و`LABEL_MATCH` في W11 تعني الشيءَ نفسَه بعد الفعل — فكلتاهما
        «بلا تغييرِ اسمٍ»، بينما `CORRECTED_TO_CANONICAL` و`LABEL_DRIFT`
        فعلٌ وقع. */
$V = array(
    's1' => array(
        'NOT_A_MENU_ITEM'      => 'ليست بندَ قائمة',
        'NO_NAV_ITEM'          => 'ليست بندَ قائمة',
        'DISABLED_WITH_REASON' => '**معطَّلة بسببٍ مكتوب**',
    ),
    's2' => array(
        'CORRECTED_TO_CANONICAL' => '**صُحِّح اسمُها المعروض**',
        'LABEL_DRIFT'            => '**صُحِّح اسمُها المعروض**',
        'LABEL_FROM_REGISTRY'    => '**اسمُها صار من السجلِّ المعياريّ**',
        'PENDING_OWNER_NAME'     => 'اسمُها ينتظر تسميةَ المالك',
        'NO_CANONICAL_ROW'       => 'بلا اسمٍ معياريٍّ مسجَّل',
        'NO_CANONICAL'           => 'بلا اسمٍ معياريٍّ مسجَّل',
    ),
    's3' => array(
        'RENDERED_FROM_CANONICAL' => '**مجموعتُها صارت من السجلِّ المعياريّ**',
        'GROUP_DRIFT'             => '**نُقلت إلى مجموعتِها المعياريّة**',
        'GROUP_FROM_CYCLE'        => '**مجموعتُها صارت من دورةِ العمل**',
        'PENDING_OWNER_GROUP'     => 'مجموعتُها تنتظر قرارَ المالك',
    ),
    's4' => array(
        'CYCLE_ORDER'         => '**رُتِّبت على دورةِ العمل**',
        'ORDER_FROM_CYCLE'    => '**رُتِّبت على دورةِ العمل**',
        'ORDER_FROM_REGISTRY' => '**رُتِّبت من السجلِّ المعياريّ**',
        'CANONICAL_ORDER'     => 'ترتيبُها من السجلِّ المعياريّ',
        'NO_ORDER_SOURCE'     => 'بلا مصدرِ ترتيب',
    ),
    's5' => array(
        'TAB_IN_PARENT'            => '**تُعرَض تبويبًا داخلَ شاشةِ أبيها**',
        'ALREADY_TAB'              => 'تبويبٌ داخلَ أبيها سلفًا',
        'TAB_ELIGIBLE_NOT_DEMOTED' => 'تصلح تبويبًا ولم تُخفَض',
        'TAB_BAR_NOT_RENDERED'     => 'شريطُ تبويبِ أبيها لا يُصيَّر',
        'DEMOTION_LOSES_ROLES'     => 'خفضُها يُفقد أدوارًا فامتنع',
    ),
    's6' => array(
        'NO_SERVER_GUARD' => '**بلا حارسِ خادم**',
        'NO_GRANT'        => 'بلا منحِ صلاحيةٍ لأيِّ دور',
    ),
    's7' => array(
        'NOT_LINKED' => 'غيرُ مربوطةٍ بمُعرِّفِها المعياريّ',
    ),
);

/* ═══ ⑦ الموجاتُ — تعريفُها ومصدرُها ══════════════════════════════════════ */
$WAVES = array(
    1  => array('t' => 'الملكيّةُ والمساحات', 'tbl' => null,
                'p' => 'أسّست **مَن يملك ماذا**: كلُّ مسارٍ حيٍّ نُسب إلى إدارةٍ مالكةٍ بحكمٍ مكتوب. ⛔ ولم تُغيَّر شاشةٌ واحدةٌ ولا بندُ قائمة — الملكيّةُ شرطُ كلِّ ما بعدها.'),
    2  => array('t' => 'السجلُّ والملاحة', 'tbl' => null,
                'p' => 'بُني **سجلُّ الشاشات** مرجعًا واحدًا، وقيس لكلِّ شاشةٍ حارسُها الخادميُّ ومُعرِّفُها المعياريّ. والشاشةُ المُعلَنةُ بلا ملفٍّ على القرصِ نُقلت إلى **دفترِ الفجواتِ المستهدَفة** فلا تُعدُّ موجودةً وهي غائبة.'),
    3  => array('t' => 'المفاتيحُ والبيانات المرجعيّة', 'tbl' => 'repair01_w3_sidebar'),
    4  => array('t' => 'حقيقةُ الميدان', 'tbl' => 'repair01_w4_sidebar'),
    5  => array('t' => 'الأصلُ وأثرُه', 'tbl' => 'repair01_w5_sidebar'),
    6  => array('t' => 'نقاءُ لغةِ الواجهة', 'tbl' => null),
    7  => array('t' => 'الصيانةُ والنقل', 'tbl' => 'repair01_w7_sidebar'),
    8  => array('t' => 'المبيعاتُ والموردون', 'tbl' => 'repair01_w8_sidebar'),
    9  => array('t' => 'المشترياتُ والمخازن', 'tbl' => 'repair01_w9_sidebar'),
    10 => array('t' => 'شقُّ الماليةِ والخزينة', 'tbl' => 'repair01_w10_sidebar',
                'p' => 'موجةُ **ملكيّةٍ لا بناء**: وحدةٌ واحدةٌ في الوثائقِ القديمةِ كانت تجمع الماليةَ والخزينةَ معًا، فشُقَّت إلى إدارتَين وأُعيد نسبُ كلِّ سطحٍ إلى شقِّه. ⛔ **ولم يُنشأ سطحٌ واحدٌ ولم يُنقل بندٌ في السايدبار** — التغييرُ في **مَن يملك** لا في **ما يُرى**.'),
    11 => array('t' => 'دفاترُ الكيانات: الماليةُ والخزينة', 'tbl' => 'repair01_w11_sidebar'),
    12 => array('t' => 'التمويلُ والممولون', 'tbl' => 'repair01_w12_sidebar'),
    13 => array('t' => 'الموارد البشريّةُ والبلاغات', 'tbl' => 'repair01_w13_sidebar'),
);

/* ═══ ⑧ بناءُ صفوفِ كلِّ موجة ══════════════════════════════════════════════ */
$WROWS = array(); $STATS = array();
foreach ($WAVES as $w => $meta) {
    $WROWS[$w] = array();
    $STATS[$w] = array('new' => 0, 'wired' => 0, 'hidden' => 0, 'total' => 0);

    if ($meta['tbl'] !== null) {
        $t = $meta['tbl'];
        $cols = array(); $c = $conn->query("SHOW COLUMNS FROM `$t`");
        while ($y = $c->fetch_assoc()) { $cols[$y['Field']] = true; }
        $sel = 'route, owner_code' . (isset($cols['group_name']) ? ', group_name' : '');
        foreach (array('s1','s2','s3','s4','s5','s6','s7') as $s) {
            if (isset($cols[$s . '_verdict'])) { $sel .= ", {$s}_verdict"; }
        }
        foreach ($rows("SELECT $sel FROM `$t` ORDER BY owner_code, route") as $r) {
            $base = strtolower(basename($r['route']));
            $acts = array();
            $isNew = isset($BORN[$w][$base]);
            if ($isNew) {
                $acts[] = '**شاشةٌ أُنشئت في هذه المرحلة**';
                if (isset($GAPMET[$w][$base])) { $acts[] = 'وفَّت الفجوةَ المُعلَنة «' . $GAPMET[$w][$base] . '»'; }
                $STATS[$w]['new']++;
            } else {
                $acts[] = 'شاشةٌ قائمةٌ وُصلت بدورةِ هذه المرحلة';
                $STATS[$w]['wired']++;
            }
            foreach (array('s1','s2','s3','s4','s5','s6','s7') as $s) {
                $k = $s . '_verdict';
                if (!isset($r[$k])) { continue; }
                $v = (string) $r[$k];
                if (isset($V[$s][$v])) { $acts[] = $V[$s][$v]; }
            }
            $WROWS[$w][] = array($label($r['route']), $dept($r['owner_code']),
                                 implode(' · ', $acts), basename($r['route']));
            $STATS[$w]['total']++;
        }
    }

    /* أسطحُ نموٍّ لا صفَّ لها في جدولِ سايدبارِ موجتِها — تُذكَر ولا تُسقَط */
    if (isset($BORN[$w])) {
        $seen = array();
        foreach ($WROWS[$w] as $x) { $seen[strtolower($x[3])] = true; }
        foreach (array_keys($BORN[$w]) as $b) {
            if (isset($seen[$b])) { continue; }
            $a = array('**شاشةٌ أُنشئت في هذه المرحلة**');
            if (isset($GAPMET[$w][$b])) { $a[] = 'وفَّت الفجوةَ المُعلَنة «' . $GAPMET[$w][$b] . '»'; }
            $WROWS[$w][] = array($label($b), $dept(isset($BORN_DEPT[$b]) ? $BORN_DEPT[$b] : ''),
                                 implode(' · ', $a), $b);
            $STATS[$w]['new']++; $STATS[$w]['total']++;
        }
    }

    /* المخفيُّ المنسوبُ لهذه الموجة */
    if (isset($HID[$w])) {
        foreach ($HID[$w] as $h) {
            $why = ($h['reachable'] === 'TAB_IN_PARENT')
                ? '**نُقلت من السايدبار إلى تبويبٍ داخلَ شاشةِ أبيها**'
                : '**رُفعت من السايدبار** — لم تكن تُصيَّر أصلًا (بندٌ ميّت)';
            if ((string) $h['group_before'] !== '') { $why .= ' · كانت في «' . $h['group_before'] . '»'; }
            $WROWS[$w][] = array(($h['label_ar'] !== '' ? $h['label_ar'] : $label($h['route'])),
                                 $dept(''), $why, basename($h['route']));
            $STATS[$w]['hidden']++; $STATS[$w]['total']++;
        }
    }
    usort($WROWS[$w], function ($a, $b) { return $a[1] === $b[1] ? strcmp($a[0], $b[0]) : strcmp($a[1], $b[1]); });
}

/* ═══ ⑨ أرقامُ الموجتَين الأوليَين والسادسة — مقيسةٌ لا مسرودة ══════════════ */
$w1Rows  = (int) $one("SELECT COUNT(*) FROM repair01_ownership");
$w1Depts = (int) $one("SELECT COUNT(DISTINCT owner_dept) FROM repair01_ownership WHERE owner_dept <> ''");
/* ⚠ **مقامُ W02 هو الأساسُ لا الكلّ**: السجلُّ اليومَ 739 صفًّا فيه 88 سطحَ
     نموٍّ أنشأتها موجاتٌ لاحقة — ونسبتُها إلى الثانيةِ نسبةُ عملٍ لم تعمله. */
$w2Scr   = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                        WHERE origin IN ('SURFACES','DISK','NAV')");
$w2Ghost = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry WHERE ghost_verdict = 'MOVED_TO_TARGET_GAPS'");
$w2Guard = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry WHERE guard_kind IN ('SHELL','SELF_EARLY')");
$w6Rows  = (int) $one("SELECT COUNT(*) FROM repair01_w6_rewrite");
/* ⚠ **`source_key` مفتاحُ صفٍّ لا اسمُ ملفّ**: `repair01_w6_rewrite` يسجّل
     تصحيحاتِ **الجداول** (‏عناوينُ الدورةِ والمجموعاتُ والملاحة)، وتصحيحاتُ
     **الملفّاتِ** في سجلٍّ آخر. وخلطُهما يعطي رقمًا لا يقابل شيئًا. */
$w6Scan  = (int) $one("SELECT COUNT(*) FROM repair01_w6_file_log");
$w6Left  = (int) $one("SELECT COALESCE(SUM(ui_before),0) FROM repair01_w6_file_log");
/* ⚠ **وسجلُّ الملفّاتِ سجلُّ تحقّقٍ لا سجلُّ تحرير**: جولتُه الوحيدةُ الباقيةُ
     `W6F-*` مسحت 1016 ملفًّا ووجدت **صفرَ تشكيلٍ في نصٍّ مُصيَّر** وغيَّرت صفرَ
     ملفّ — فهي تُثبت **نظافةَ الشجرةِ بعدَ العمل** وقد كتبت على سجلِّ الجولةِ
     التي حرَّرت. فمصدرُ عددِ الملفّاتِ المحرَّرةِ **سجلُّ الالتزامِ نفسُه**. */
$w6Edit = 0;
$g = array(); $rc = 0;
@exec('git -C ' . escapeshellarg($ROOT) . ' log --format=%H --grep="^RPR-W06:" -1 2>&1', $g, $rc);
if ($rc === 0 && !empty($g[0]) && preg_match('~^[0-9a-f]{7,40}$~', trim($g[0]))) {
    $st = array();
    @exec('git -C ' . escapeshellarg($ROOT) . ' show --numstat --format= ' . escapeshellarg(trim($g[0])) . ' 2>&1', $st);
    foreach ($st as $l) { if (substr(trim($l), -4) === '.php') { $w6Edit++; } }
}

/* ═══ ⑩ الكتابة ═══════════════════════════════════════════════════════════ */
$OUT = $ROOT . '/docs/REPAIR01_20260823/UI_CHANGES_W01_W13.md';
$today = (string) $one('SELECT CURDATE()');
$m = array();
$m[] = '# تقريرُ تغييراتِ واجهةِ المستخدم — المراحلُ ١ إلى ١٣';
$m[] = '';
$m[] = '> **حملةُ REPAIR01** · تاريخُ القياس: **' . $today . '**';
$m[] = '> ⛔ **مولَّدٌ من المخزن — لا يُحرَّر يدويًّا**: `php tools/repair01_ui_change_report.php` يعيد كتابتَه.';
$m[] = '';
$m[] = '---';
$m[] = '';
$m[] = '## كيف يُقرأ هذا التقرير';
$m[] = '';
$m[] = 'كلُّ صفٍّ هنا **مقيسٌ من سجلِّ الحملةِ لا مسرودٌ من الذاكرة**. وعمودُ «التغيير» يجمع';
$m[] = 'كلَّ ما وقع على الشاشةِ في تلك المرحلةِ بعبارةٍ واحدة، ومعانيه:';
$m[] = '';
$m[] = '| العبارة | معناها بالضبط |';
$m[] = '|---|---|';
$m[] = '| **شاشةٌ أُنشئت في هذه المرحلة** | ملفٌّ جديدٌ لم يكن على القرصِ من قبل — بُني وسُجِّل وحُرس ووُصل بالسايدبار |';
$m[] = '| شاشةٌ قائمةٌ وُصلت بدورةِ هذه المرحلة | الملفُّ كان موجودًا، وأُدخل في دورةِ عملِ الإدارةِ وسُجِّل موضعُه وحارسُه |';
$m[] = '| **صُحِّح اسمُها المعروض** | الاسمُ الظاهرُ في السايدبار كان يخالف الاسمَ المعياريَّ المسجَّل فصُحِّح |';
$m[] = '| **نُقلت إلى مجموعتِها المعياريّة** | كانت تحت مجموعةٍ تخالف المسجَّلَ لها فنُقلت — والبندُ لا يختفي |';
$m[] = '| **مجموعتُها صارت من دورةِ العمل** | موضعُها في السايدبار صار يُشتقُّ من ترتيبِ الدورةِ لا من الأبجديّة |';
$m[] = '| **رُتِّبت على دورةِ العمل** | ترتيبُها داخلَ مجموعتِها صار على تسلسلِ العملِ الحقيقيّ |';
$m[] = '| **تُعرَض تبويبًا داخلَ شاشةِ أبيها** | لم تعد بندًا مستقلًّا في السايدبار — صارت تبويبًا في شاشةٍ أعلى منها |';
$m[] = '| **رُفعت من السايدبار** | بندٌ كان مسجَّلًا ولا يُصيَّر فعلًا — أُزيل بسببٍ مكتوبٍ في سجلِّ الإخفاء |';
$m[] = '| **معطَّلة بسببٍ مكتوب** | الشاشةُ قائمةٌ ورابطُها موقوفٌ عمدًا بعلّةٍ مسجَّلة |';
$m[] = '| اسمُها / مجموعتُها تنتظر قرارَ المالك | قيست المخالفةُ وسُجِّلت ⛔ ولم تُحسم بقرارِ مبرمج |';
$m[] = '| بلا حارسِ خادم · بلا منحِ صلاحية | نقصٌ مقيسٌ ومُعلَنٌ في هذه المرحلة |';
$m[] = '';
$m[] = '**والمرحلةُ السادسةُ مستثناةٌ بطلبِك** — انظرْ موضعَها أدناه.';
$m[] = '';
$m[] = '---';
$m[] = '';
$m[] = '## الملخّصُ التنفيذيّ';
$m[] = '';
$m[] = '| المرحلة | موضوعُها | شاشاتٌ جديدة | شاشاتٌ قائمةٌ تغيّرت | رُفعت من السايدبار | مجموعُ الأسطح |';
$m[] = '|---|---|---:|---:|---:|---:|';
$tN = 0; $tW = 0; $tH = 0; $tT = 0;
foreach ($WAVES as $w => $meta) {
    $s = $STATS[$w];
    $lab = str_pad((string) $w, 2, '0', STR_PAD_LEFT);
    if ($w === 6) {
        $m[] = "| **W06** | " . $meta['t'] . " | — | " . number_format($w6Edit) . ' ملفًّا | — | *مستثناة* |';
        continue;
    }
    if ($meta['tbl'] === null && $s['total'] === 0) {
        $note = ($w === 1) ? number_format($w1Rows) . ' نسبةَ ملكيّة' : number_format($w2Scr) . ' شاشةً مسجَّلة';
        $m[] = "| **W$lab** | " . $meta['t'] . " | — | *أساسٌ لا يُرى* | — | $note |";
        continue;
    }
    $m[] = "| **W$lab** | " . $meta['t'] . " | **{$s['new']}** | {$s['wired']} | {$s['hidden']} | {$s['total']} |";
    $tN += $s['new']; $tW += $s['wired']; $tH += $s['hidden']; $tT += $s['total'];
}
$m[] = "| | **الإجمالي** | **$tN** | **$tW** | **$tH** | **$tT** |";
$m[] = '';
$m[] = '◆ **«مجموعُ الأسطح» ليس عددَ شاشاتٍ فريدةً في النظام**: الشاشةُ الواحدةُ قد تظهر في';
$m[] = 'أكثرَ من مرحلةٍ إن مسَّتها كلٌّ منهما بفعلٍ مختلف — والجمعُ هنا **جمعُ أفعالٍ لا جمعُ ملفّات**.';
$m[] = '';
$m[] = '---';
$m[] = '';

foreach ($WAVES as $w => $meta) {
    $lab = str_pad((string) $w, 2, '0', STR_PAD_LEFT);
    $m[] = '## المرحلة ' . $w . ' · ' . $meta['t'];
    $m[] = '';

    if ($w === 1) {
        $m[] = $meta['p'];
        $m[] = '';
        $m[] = '**المقيس:** ' . number_format($w1Rows) . ' نسبةَ ملكيّةٍ على ' . $w1Depts . ' إدارةً.';
        $m[] = '';
        $m[] = '| الشاشة | الإدارة | التغيير |';
        $m[] = '|---|---|---|';
        $m[] = '| *(لا شاشة)* | كلُّ الإدارات | **لم تتغيّر واجهةٌ واحدة** — نُسبت الملكيّةُ في السجلِّ وحدَه، وهي الأساسُ الذي بُني عليه كلُّ نقلٍ وتسميةٍ في المراحلِ التالية |';
        $m[] = '';
        $m[] = '---';
        $m[] = '';
        continue;
    }
    if ($w === 2) {
        $m[] = $meta['p'];
        $m[] = '';
        $m[] = '**المقيس:** ' . number_format($w2Scr) . ' شاشةً في السجلّ · ' . $w2Guard . ' منها بحارسٍ خادميٍّ مُثبَتٍ من القرص · '
             . '**' . $w2Ghost . ' شاشةً مُعلَنةً بلا ملفٍّ** نُقلت إلى دفترِ الفجواتِ المستهدَفة.';
        $m[] = '';
        $m[] = '| الشاشة | الإدارة | التغيير |';
        $m[] = '|---|---|---|';
        $m[] = '| *(لا شاشة)* | كلُّ الإدارات | **لم يتغيّر مظهرُ شاشةٍ واحدة** — بُني السجلُّ وقيس الحارسُ والمُعرِّفُ لكلِّ شاشة. وأثرُه يظهر في المراحلِ التالية: **' . $w2Ghost . ' شاشةً كانت تُعدُّ موجودةً صارت فجوةً مُعلَنة** بدل أن تبقى وعدًا في وثيقة |';
        $m[] = '';
        $m[] = '---';
        $m[] = '';
        continue;
    }
    if ($w === 6) {
        $m[] = '> ⛔ **مستثناةٌ من التفصيلِ بطلبِ المالك.**';
        $m[] = '';
        $m[] = 'نطاقُها **تنقيةُ لغةِ الواجهة**: نزعُ التشكيلِ والزخرفةِ والمصطلحِ التقنيِّ من **النصِّ';
        $m[] = 'المعروضِ للمستخدم**. والمقيس على محورَين لا يُخلطان:';
        $m[] = '';
        $m[] = '| المحور | العدد | ما هو |';
        $m[] = '|---|---:|---|';
        $m[] = '| **ملفّاتُ `php` حُرِّرت** | **' . number_format($w6Edit) . '** | من سجلِّ الالتزامِ `RPR-W06` نفسِه |';
        $m[] = '| **تصحيحاتٌ في جداولِ القاعدة** | **' . number_format($w6Rows) . '** | عناوينُ الدورةِ والمجموعاتُ وبنودُ الملاحة |';
        $m[] = '| ملفّاتٌ أُعيد مسحُها للتحقّق | ' . number_format($w6Scan) . ' | جولةُ إثباتٍ بعدَ العمل |';
        $m[] = '| تشكيلٌ باقٍ في نصٍّ مُصيَّر | **' . number_format($w6Left) . '** | نتيجةُ جولةِ الإثبات |';
        $m[] = '';
        $m[] = '◆ **والنصُّ الذي يراه المستخدمُ مصدرُه القاعدةُ لا الملفُّ في أكثرِه** —';
        $m[] = 'عناوينُ الشاشاتِ وأسماءُ المجموعاتِ وبنودُ القائمةِ كلُّها صفوفُ جداول. ولذلك';
        $m[] = 'العددُ الأكبرُ هنا في الجداولِ لا في الملفّات.';
        $m[] = '';
        $m[] = '**ولم تُنشأ فيها شاشةٌ ولم يُنقل بندٌ ولم تُحذف قائمة** — التغييرُ في **حروفِ النصِّ';
        $m[] = 'لا في بنيةِ الشاشةِ ولا في موضعِها**. فسردُها صفًّا صفًّا يُغرق التقريرَ بما لا يفيد.';
        $m[] = '';
        $m[] = '---';
        $m[] = '';
        continue;
    }

    if (isset($meta['p'])) { $m[] = $meta['p']; $m[] = ''; }
    $s = $STATS[$w];
    $m[] = '**المقيس:** ' . $s['new'] . ' شاشةً جديدة · ' . $s['wired'] . ' شاشةً قائمةً مسَّها تغيير'
         . ($s['hidden'] > 0 ? ' · ' . $s['hidden'] . ' بندًا رُفع من السايدبار' : '') . '.';
    $m[] = '';

    /* التجميعُ بالإدارةِ — القارئُ يقرأ إدارتَه لا القائمةَ كلَّها */
    $byDept = array();
    foreach ($WROWS[$w] as $r) { $byDept[$r[1]][] = $r; }
    ksort($byDept);
    foreach ($byDept as $dp => $list) {
        $m[] = '### ' . ($dp === '—' ? 'بلا إدارةٍ مسجَّلة' : $dp) . '  ·  ' . count($list) . ' سطحًا';
        $m[] = '';
        $m[] = '| الشاشة | الإدارة | التغيير |';
        $m[] = '|---|---|---|';
        foreach ($list as $r) {
            $m[] = '| ' . str_replace('|', '¦', $r[0]) . ' | ' . $r[1] . ' | ' . str_replace('|', '¦', $r[2]) . ' |';
        }
        $m[] = '';
    }
    $m[] = '---';
    $m[] = '';
}

/* ═══ ⑪ المقطعُ العرضيّ — كلُّ إدارةٍ عبرَ المراحلِ كلِّها ═══════════════════ */
$m[] = '## المقطعُ العرضيّ — ماذا نال كلَّ إدارةٍ عبرَ المراحلِ كلِّها';
$m[] = '';
$cross = array();
foreach ($WAVES as $w => $meta) {
    if ($w === 6) { continue; }
    foreach ($WROWS[$w] as $r) {
        if (!isset($cross[$r[1]])) { $cross[$r[1]] = array('new' => 0, 'chg' => 0, 'w' => array()); }
        if (mb_strpos($r[2], 'أُنشئت') !== false) { $cross[$r[1]]['new']++; } else { $cross[$r[1]]['chg']++; }
        $cross[$r[1]]['w'][$w] = true;
    }
}
uasort($cross, function ($a, $b) { return ($b['new'] + $b['chg']) - ($a['new'] + $a['chg']); });
$m[] = '| الإدارة | شاشاتٌ جديدة | أسطحٌ قائمةٌ مسَّها تغيير | المراحلُ التي مسَّتها |';
$m[] = '|---|---:|---:|---|';
foreach ($cross as $dp => $c) {
    $ws = array_keys($c['w']); sort($ws);
    $m[] = '| ' . ($dp === '—' ? '*بلا إدارةٍ مسجَّلة*' : $dp) . ' | ' . ($c['new'] ?: '—') . ' | '
         . ($c['chg'] ?: '—') . ' | W' . implode(' · W', array_map(function ($x) {
               return str_pad((string) $x, 2, '0', STR_PAD_LEFT); }, $ws)) . ' |';
}
$m[] = '';
$m[] = '---';
$m[] = '';
$m[] = '## مصالحةُ الأرقام';
$m[] = '';
$m[] = '| المقياس | العدد | مصدرُه |';
$m[] = '|---|---:|---|';
$m[] = '| شاشاتٌ أُنشئت في الحملةِ كلِّها | **' . $tN . '** | `repair01_screen_registry.origin` |';
$m[] = '| أسطحٌ قائمةٌ مسَّها تغييرٌ (‏جمعُ أفعالٍ) | ' . $tW . ' | جداولُ سايدبارِ الموجات |';
$m[] = '| بنودٌ رُفعت من السايدبارِ بالحملة | ' . $tH . ' | `gov_nav_hidden_log` بوسمِ `RPR-*` |';
$m[] = '| تصحيحاتُ لغةِ الواجهة (W06 · مستثناة) | ' . number_format($w6Rows) . ' | `repair01_w6_rewrite` |';
$m[] = '| شاشاتٌ مُعلَنةٌ بلا ملفٍّ صارت فجوةً (W02) | ' . $w2Ghost . ' | `repair01_screen_registry.ghost_verdict` |';
$m[] = '';
$m[] = '◆ **وما ليس في هذا التقرير**: بنودٌ رُفعت قبلَ الحملةِ بوثائقَ سابقةٍ '
     . '(`INJ-SAL-ALIGN-01` و`INJ-SUP-ALIGN-01`) — **' . (int) $one("SELECT COUNT(*) FROM gov_nav_hidden_log WHERE doc_code LIKE 'INJ-%'")
     . ' بندًا** ليست من عملِ REPAIR01 فلا تُنسَب إليها.';
$m[] = '';

@mkdir(dirname($OUT), 0777, true);
file_put_contents($OUT, implode("\n", $m) . "\n");

printf("✔ كُتب التقرير: %s\n", str_replace($ROOT . DIRECTORY_SEPARATOR, '', $OUT));
printf("  موجاتٌ مُفصَّلة: %d · شاشاتٌ جديدة %d · أسطحٌ قائمة %d · مرفوعة %d · مجموعُ الأفعال %d\n",
    count($WAVES) - 3, $tN, $tW, $tH, $tT);
printf("  حجمُ الملفّ: %s ك.ب\n", number_format(filesize($OUT) / 1024, 1));
