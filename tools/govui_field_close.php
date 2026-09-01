<?php
/**
 * tools/govui_field_close.php — **إغلاقُ حقولِ الأسطحِ المكتوبةِ بيدٍ سابقة**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لماذا عُدّةٌ ثالثةٌ بجانبِ `gov_exec_dept_build.php`**: تلك تبني السطحَ
 *   **من الورقةِ فوقَ العُدّةِ المولِّدة** — ⛔ **وترفض بنصِّها أن تدهس ملفًّا
 *   بيدٍ سابقة** (*«ملفٌّ بيدٍ سابقةٍ فلا يُدهَس»*)، وهو رفضٌ في محلِّه. فبقيت
 *   **الأسطحُ المكتوبةُ يدًا** بلا طريقٍ إلى إغلاقِ حقولِها: تُضاف أعمدةٌ في
 *   المخزنِ وتُقيَّد في `gov_field_class` **ولا يُصيَّر منها حرفٌ** لأنَّ السطحَ
 *   لا يقرأ ذلك الدفتر ([[iaf-field-closure]]).
 *   ⇒ فهذه تُغلق حقولَها **في موضعِها**: خريطةٌ واحدةٌ في ملفِّ السطحِ
 *   (`$GUIDE_COLS`) يقرؤها **الرأسُ والخليّةُ معًا** عبرَ `ems_w14_grid`.
 *
 * ◆ **والاسمُ اسمُ الورقةِ حرفًا** (الأمرُ §7) — والمواصفةُ **تُردُّ ولا تُطبَّق**
 *   إن نقص منها حقلٌ من حقولِ الورقةِ المنطبقةِ أو اختلّ ترتيبُه.
 *   ⛔ **فليس للمواصفةِ أن تختار أيَّ الحقولِ تُغلق** — الورقةُ تختار.
 *
 * ◆ **وما لا نظيرَ له في المخزنِ يأخذ عمودًا** ([[iaf-field-closure]]) بصيغةِ
 *   `+col:TYPE` في المواصفة، **واسمُ الحقلِ في تعليقِ العمودِ حرفًا** — وهو
 *   اصطلاحُ `gov_exec_dept_build` نفسُه لا اصطلاحٌ ثانٍ.
 *   ⛔ **ولا يُخترع نظيرٌ**: عمودٌ قائمٌ معناه معنى الحقلِ يُربَط، ولا يُنشأ ثانٍ.
 *
 * ◆ **والرأسُ بلا مصدرِ خليّةٍ ممنوعٌ بالبنية** — فالخريطةُ زوجٌ لا اسمٌ مفرد،
 *   وهو الدرسُ المقيسُ في [[declared-column-not-built]].
 *
 * التشغيل:
 *   php tools/govui_field_close.php --spec=tools/specs/fields_dep08.php --plan
 *   php tools/govui_field_close.php --spec=… --emit=<migration_slug>
 *   php tools/govui_field_close.php --spec=… --apply
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once $ROOT . '/includes/guide_label.php';

$SPEC = null; $PLAN = false; $EMIT = null; $APPLY = false;
/* ◆ ختمُ الهجرةِ يُملى ولا يُقرأ من الساعة — [[staleness-by-fact-not-clock]]:
     ساعةُ الجهازِ ترجع، وترتيبُ الهجراتِ ترتيبُ أسمائها. */
$MDATE = date('Y_m_d');
foreach ($argv as $a) {
    if (strpos($a, '--spec=') === 0)     { $SPEC = $a === '' ? null : substr($a, 7); }
    elseif ($a === '--plan')             { $PLAN = true; }
    elseif (strpos($a, '--emit=') === 0) { $EMIT = substr($a, 7); }
    elseif ($a === '--apply')            { $APPLY = true; }
    elseif (strpos($a, '--date=') === 0) { $MDATE = substr($a, 7); }
}
/* وسمُ الكتلةِ المولَّدة — بها تُعرَف عند إعادةِ التشغيلِ فتُستبدَل لا تُكرَّر */
define('GFC_MARK', '/* GUIDE_COLS:govui_field_close');
define('GFC_END', '/* /GUIDE_COLS */ ?>');
if ($SPEC === null || !is_file($SPEC)) { exit("⛔ --spec=<file.php> مطلوب\n"); }
$S = require $SPEC;
if (!isset($S['dept'], $S['screens'])) { exit("⛔ المواصفةُ تحتاج dept وscreens\n"); }

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("⛔ تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

/**
 * **تطبيعُ المقارنة** — الاسمُ في المواصفةِ نقيٌّ (بلا اصطلاحِ تصنيفٍ ولا تشكيل)
 * لأنَّ سقّاطةَ `UI-01`/`UI-02` تعُدُّ الاصطلاحَ والتشكيلَ في نصِّ شاشةٍ حيّة،
 * والاسمُ في الورقةِ يحملهما. **فالمقارنةُ على النقيِّ من الطرفَين** — ⛔ ولا
 * يُحذف حرفٌ من الكلمات: النقاءُ يطال الزينةَ وحدَها.
 */
function gfc_key($s)
{
    $s = (string) $s;
    /* اصطلاحُ الورقة: مشتقٌّ وقائمةٌ محكومة — وهما ممّا تمنعه سقّاطةُ UI-02 */
    $s = str_replace(array("\xE2\x97\x84", "\xE2\x96\xBC"), '', $s);
    /* والمرادفُ اللاتينيُّ بعدَ النقطةِ الوسطى يُسقَط — قاعدةُ ems_guide_label
       نفسُها لا قاعدةٌ ثانية، والنقطةُ الوسطى زخرفةٌ تعُدُّها السقّاطة. */
    if (preg_match('~^(.*?)\s*\x{00B7}\s*([^\x{0600}-\x{06FF}]+)$~u', $s, $m)) { $s = $m[1]; }
    $s = str_replace(array("\xC2\xB7", "\xE2\x80\x94", "\xE2\x80\x93", "\xE2\x80\xA2"), ' ', $s);
    $s = preg_replace('~[\x{0640}\x{064B}-\x{0652}\x{0670}]~u', '', $s);
    return trim(preg_replace('~\s+~u', ' ', $s));
}

/* ═══ ① حقولُ الورقةِ لكلِّ سطحٍ — المصدرُ الحاكم ════════════════════════ */
$fieldsOf = function ($req) use ($conn) {
    $out = array();
    $st = $conn->prepare("SELECT field_name, field_type FROM repair01_fields
                           WHERE requirement_id = ? ORDER BY id");
    $st->bind_param('s', $req); $st->execute();
    $rs = $st->get_result();
    while ($r = $rs->fetch_assoc()) { $out[] = $r; }
    $st->close();
    return $out;
};

$cols = function ($t) use ($conn) {
    $out = array();
    $q = @$conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($t) . "`");
    while ($q && $r = $q->fetch_assoc()) { $out[$r['Field']] = $r['Type']; }
    return $out;
};

/* ═══ ② الفحصُ — والمواصفةُ تُردُّ إن خالفت الورقةَ ═════════════════════ */
$FAIL = 0; $adds = array(); $creates = array(); $plans = array();
foreach ($S['screens'] as $sc) {
    foreach (array('req', 'route', 'table', 'grid_id', 'empty', 'map') as $k) {
        if (!isset($sc[$k])) { echo "  ✗ ناقصٌ في المواصفة: {$k}\n"; $FAIL++; continue 2; }
    }
    $guide = $fieldsOf($sc['req']);
    if (!$guide) { echo "  ✗ [{$sc['req']}] لا حقولَ في الورقة\n"; $FAIL++; continue; }
    $need = array();
    foreach ($guide as $g) { if ($g['field_type'] !== 'AUDIT') { $need[] = gfc_key($g['field_name']); } }
    $have = array();
    foreach ($sc['map'] as $lbl => $src) { $have[] = gfc_key($lbl); }
    /* الترتيبُ ترتيبُ دورةِ المستند: المنطبقُ يظهر بترتيبِ الورقةِ نفسِه،
       والإلحاقيُّ (AUDIT) مسموحٌ بينها كما في الورقة. */
    $seq = array();
    foreach ($have as $h) { if (in_array($h, $need, true)) { $seq[] = $h; } }
    $missing = array_values(array_diff($need, $have));
    $order   = ($seq === $need);
    if ($missing) {
        echo "  ✗ [{$sc['req']}] " . $sc['route'] . " — حقولٌ من الورقةِ خارجَ الخريطة: "
           . implode(' · ', $missing) . "\n";
        $FAIL++;
    }
    if (!$order && !$missing) {
        echo "  ✗ [{$sc['req']}] " . $sc['route'] . " — الترتيبُ يخالف دورةَ المستند\n";
        echo "     الورقة: " . implode(' | ', $need) . "\n";
        echo "     الخريطة: " . implode(' | ', $seq) . "\n";
        $FAIL++;
    }
    /* أعمدةٌ لا نظيرَ لها — تُجمَع للهجرة */
    $have_cols = $cols($sc['table']);
    $mkTable = !empty($sc['create']);
    if (!$have_cols && !$mkTable) { echo "  x جدولٌ غيرُ موجود: {$sc['table']}" . chr(10); $FAIL++; continue; }
    $need_add = array();
    foreach ($sc['map'] as $lbl => $src) {
        $src = (string) $src;
        if ($src === '' || $src[0] === '#') { continue; }
        if ($src[0] === '+') {
            $rest = substr($src, 1);
            $pos  = strpos($rest, ':');
            $col  = $pos === false ? $rest : substr($rest, 0, $pos);
            $type = $pos === false ? 'VARCHAR(255) NULL DEFAULT NULL' : substr($rest, $pos + 1);
            if (!isset($have_cols[$col])) {
                $need_add[] = array('table' => $sc['table'], 'col' => $col,
                                    'type' => $type, 'label' => gfc_key($lbl));
            }
            continue;
        }
        $col = ($src[0] === '@') ? substr($src, 1) : $src;
        if (!isset($have_cols[$col])) {
            echo "  ✗ [{$sc['req']}] عمودٌ غيرُ موجود: {$sc['table']}.{$col} (للحقل {$lbl})\n";
            $FAIL++;
        }
    }
    if ($mkTable && !$have_cols) {
        $creates[] = array('table' => $sc['table'], 'req' => $sc['req'], 'cols' => $need_add,
                           'grain' => isset($sc['grain']) ? $sc['grain'] : '');
    } else {
        foreach ($need_add as $a0) { $adds[] = $a0; }
    }
    $plans[] = array('sc' => $sc, 'need' => $need, 'adds' => $need_add);
    printf("  ✔ [%s] %-42s حقولُ الورقةِ %2d · خريطةٌ %2d · أعمدةٌ تُضاف %d\n",
           $sc['req'], $sc['route'], count($need), count($sc['map']), count($need_add));
}

if ($FAIL) { exit("\n⛔ رُدَّت المواصفةُ بـ{$FAIL} مخالفة — ولا يُبنى على مواصفةٍ تخالف الورقة.\n"); }
echo "\n  المجموع: " . count($plans) . " سطحًا · أعمدةٌ تُضاف " . count($adds) . "\n";

/* ═══ ③ الهجرةُ — عمودٌ لكلِّ حقلٍ لا نظيرَ له، واسمُ الحقلِ في تعليقِه ═══ */
if ($EMIT !== null) {
    if (!$adds) { echo "  · لا عمودَ يُضاف — لا هجرةَ تُكتب\n"; }
    else {
        $date = $MDATE;
        $up   = $ROOT . '/database/migrations/' . $date . '_' . $EMIT . '.php';
        $down = $ROOT . '/database/migrations/' . $date . '_' . $EMIT . '_down.php';
        $body = ''; $rev = '';
        /* جدولٌ يُنشأ حين لا حبّةَ قائمةً تملك السطحَ — والبنيةُ بنيةُ
           gov_exec_dept_build نفسُها: معرِّفٌ وعمودُ عزلٍ وأعمدةُ الورقةِ باسمِ
           الحقلِ في تعليقِ كلٍّ. ولا يُنشأ جدولٌ لحبّةٍ يملكها جدولٌ قائم —
           ذاك مصدرُ حقيقةٍ موازٍ يمنعه الدستور، والمواصفةُ تُصرِّح create عمدًا. */
        foreach ($creates as $c0) {
            $ddl = 'CREATE TABLE IF NOT EXISTS `' . $c0['table'] . '` ('
                 . '`id` INT NOT NULL AUTO_INCREMENT,'
                 . "`company_id` INT NOT NULL DEFAULT 0 COMMENT 'بوابة المستأجر',";
            foreach ($c0['cols'] as $cc) {
                $ddl .= '`' . $cc['col'] . '` ' . $cc['type']
                      . " COMMENT '" . str_replace("'", '', $cc['label']) . "',";
            }
            $ddl .= '`created_at` DATETIME NULL DEFAULT NULL,'
                 .  '`created_by` INT NULL DEFAULT NULL,'
                 .  '`updated_at` DATETIME NULL DEFAULT NULL,'
                 .  'PRIMARY KEY (`id`),'
                 .  'KEY `ix_' . substr(md5($c0['table']), 0, 8) . '_co` (`company_id`)'
                 .  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
                 .  " COMMENT '" . $c0['req'] . ' - ' . str_replace("'", '', $c0['grain']) . "'";
            $body .= '$sql = ' . var_export($ddl, true) . ';' . chr(10)
                  .  'if ($conn->query($sql)) { echo ' . var_export('+ جدول ' . $c0['table'] . chr(10), true) . '; }' . chr(10)
                  .  'else { echo ' . var_export('x ' . $c0['table'] . ': ', true) . ' . $conn->error . chr(10); }' . chr(10) . chr(10);
            $rev  = '$r = $conn->query(' . var_export('SELECT COUNT(*) FROM `' . $c0['table'] . '`', true) . ');' . chr(10)
                  . '$n = $r ? (int) $r->fetch_row()[0] : 0;' . chr(10)
                  . 'if ($n > 0) { echo ' . var_export('ابقي ' . $c0['table'] . ' لبياناته: ', true) . ' . $n . chr(10); }' . chr(10)
                  . 'elseif ($conn->query(' . var_export('DROP TABLE IF EXISTS `' . $c0['table'] . '`', true) . ')) { echo '
                  . var_export('- جدول ' . $c0['table'] . chr(10), true) . '; }' . chr(10) . chr(10) . $rev;
        }
        foreach ($adds as $a0) {
            $body .= "\$q = \$conn->query(\"SHOW COLUMNS FROM `{$a0['table']}` LIKE '{$a0['col']}'\");\n"
                  .  "if (\$q && \$q->num_rows) { echo \"= {$a0['table']}.{$a0['col']} قائمٌ سلفًا\\n\"; }\n"
                  .  "elseif (\$conn->query(\"ALTER TABLE `{$a0['table']}` ADD COLUMN `{$a0['col']}` {$a0['type']} COMMENT '" . str_replace("'", "", $a0['label']) . "'\")) {\n"
                  .  "    echo \"+ {$a0['table']}.{$a0['col']}\\n\";\n"
                  .  "} else { echo \"x {$a0['table']}.{$a0['col']}: \" . \$conn->error . \"\\n\"; }\n\n";
            $rev  = "\$q = \$conn->query(\"SHOW COLUMNS FROM `{$a0['table']}` LIKE '{$a0['col']}'\");\n"
                  .  "if (\$q && \$q->num_rows) {\n"
                  .  "    \$r = \$conn->query(\"SELECT COUNT(*) FROM `{$a0['table']}` WHERE `{$a0['col']}` IS NOT NULL\");\n"
                  .  "    \$n = \$r ? (int) \$r->fetch_row()[0] : 0;\n"
                  .  "    if (\$n > 0) { echo \"أُبقي {$a0['table']}.{$a0['col']} لبياناتِه (\$n)\\n\"; }\n"
                  .  "    elseif (\$conn->query(\"ALTER TABLE `{$a0['table']}` DROP COLUMN `{$a0['col']}`\")) { echo \"- {$a0['table']}.{$a0['col']}\\n\"; }\n"
                  .  "}\n\n" . $rev;
        }
        file_put_contents($up,   gfc_mig_src($EMIT, $S['dept'], $body, false, $date));
        file_put_contents($down, gfc_mig_src($EMIT, $S['dept'], $rev, true, $date));
        echo "  ✔ كُتبت الهجرةُ وعكسُها: " . basename($up) . "\n";
    }
}

/* ═══ ④ التطبيقُ — خريطةٌ في ملفِّ السطحِ وشبكةٌ تقرؤها ═══════════════════ */
if ($APPLY) {
    $n = 0;
    foreach ($plans as $pl) {
        $sc  = $pl['sc'];
        $rel = $sc['route'];
        $path = $ROOT . '/' . $rel;
        if (!is_file($path)) { echo "  ✗ ملفٌّ غيرُ موجود: {$rel}\n"; continue; }
        $src = (string) file_get_contents($path);
        $nl  = (strpos($src, "\r\n") !== false) ? "\r\n" : "\n";
        $s2  = str_replace("\r\n", "\n", $src);
        /* الأداةُ تُعاد تشغيلُها — فالخريطةُ تُصحَّح مرّاتٍ قبل أن تستقرّ.
           فالكتلةُ المكتوبةُ سلفًا تُعرَف بوسمِها وتُستبدَل كاملةً، ولا تُكدَّس
           خريطةٌ فوقَ خريطة. */
        $i = strpos($s2, GFC_MARK);
        if ($i !== false) {
            $ends = strpos($s2, GFC_END, $i);
            if ($ends === false) { echo "  x كتلةٌ مبتورةٌ في {$rel}
"; continue; }
            $j = $ends + strlen(GFC_END);
            $i = strrpos(substr($s2, 0, $i), '<?php');
        } else {
            /* ومرساةُ الكتلةِ تُصرَّح حين لا تكون <table id=: أسرةُ CMP-03 تكتب
               الصنفَ قبلَ المعرِّف. ولا يُخمَّن «أوّلُ جدولٍ» — فشاشةٌ فيها جدولان
               يُدهَس أوّلُهما وهو ليس السجلّ. */
            $anchor = isset($sc['anchor']) && $sc['anchor'] !== '' ? (string) $sc['anchor'] : '<table id="';
            $i   = strpos($s2, $anchor);
            $j   = ($i === false) ? false : strpos($s2, '</table>', $i);
            if ($i === false || $j === false) { echo "  ✗ لا كتلةَ جدولٍ في {$rel}\n"; continue; }
            $j += strlen('</table>');
        }
        $ls  = strrpos(substr($s2, 0, $i), "\n") + 1;
        $ind = substr($s2, $ls, $i - $ls);
        if (trim($ind) !== '') { $ind = '    '; }

        $blk  = $ind . "<?php " . GFC_MARK . "\n";
        $blk .= $ind . "     الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)\n";
        $blk .= $ind . "     والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،\n";
        $blk .= $ind . "     ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */\n";
        $blk .= $ind . "\$GUIDE_COLS = array(\n";
        foreach ($sc['map'] as $lbl => $s0) {
            $s0 = (string) $s0;
            if ($s0 !== '' && $s0[0] === '+') {
                $rest = substr($s0, 1); $pos = strpos($rest, ':');
                $s0 = $pos === false ? $rest : substr($rest, 0, $pos);
            }
            $blk .= $ind . "    '" . gfc_key($lbl) . "' => '" . $s0 . "',\n";
        }
        $blk .= $ind . ");\n";
        if (!empty($sc['derive'])) { $blk .= rtrim($sc['derive'], "\n") . "\n"; }
        else { $blk .= $ind . "\$D = array();\n"; }
        /* ◆ **ومصدرُ الصفوفِ يُصرَّح حين لا يكون `$rows`**: أسرةُ `CMP-03` تقرأ
             حمولةً مفاتيحُها تسمياتٌ قديمة، والخريطةُ تربط اسمَ الورقةِ
             **بالعمود** — فتُقرأ الصفوفُ بأعمدتِها (`cmp03_store_raw`).
             ⛔ ولا تُمَسُّ الكتابة: نموذجُ الإضافةِ يبقى على خريطةِ السجلّ. */
        $rowsExpr = isset($sc['rows']) && $sc['rows'] !== '' ? (string) $sc['rows'] : '$rows';
        if ($rowsExpr !== '$rows') {
            $blk .= $ind . '$__gridRows = ' . $rowsExpr . ";" . chr(10);
            $rowsExpr = '$__gridRows';
        }
        $blk .= $ind . "echo ems_w14_grid('" . $sc['grid_id'] . "', \$GUIDE_COLS, " . $rowsExpr . ", \$D, '"
              . str_replace("'", "", $sc['empty']) . "'); " . GFC_END;

        $s2 = substr($s2, 0, $ls) . $blk . substr($s2, $j);
        $inc = "require_once __DIR__ . '/../includes/w14_grid.php';";
        if (strpos($s2, 'w14_grid.php') === false) {
            $anchor = "require_once __DIR__ . '/../includes/w14_view.php';";
            if (strpos($s2, $anchor) !== false) {
                $s2 = str_replace($anchor, $anchor . "\n" . $inc, $s2);
            } else {
                /* ومرساةُ الاشتمالِ ثلاثُ صيغٍ لا واحدة: الشجرةُ تكتب
                   include '../config.php'; وrequire_once '../config.php';
                   وrequire_once __DIR__ . '/../config.php'. ومرساةٌ واحدةٌ تُسقط
                   الاشتمالَ صامتًا فتموت الشاشةُ بـundefined function — وهو ما
                   وقع في supplierscontracts.php مقيسًا. */
                $done = 0;
                $s2 = preg_replace(
                    "~^((?:include|require|require_once)\s+(?:__DIR__\s*\.\s*)?'[^']*config\.php';)$~m",
                    "$1" . chr(10) . $inc, $s2, 1, $done);
                if (!$done) { echo "  x لا مرساةَ اشتمالٍ في {$rel} — اضفها بيد" . chr(10); }
            }
        }
        file_put_contents($path, str_replace("\n", $nl, $s2));
        $n++;
        echo "  ✔ أُغلقت حقولُ {$rel}\n";
    }
    echo "\n  أسطحٌ كُتبت: {$n}\n";
}

if (!$PLAN && $EMIT === null && !$APPLY) { echo "\nاستعمل --plan أو --emit=<slug> أو --apply\n"; }

/** هيكلُ الهجرةِ — نسخةُ اصطلاحِ `gov_exec_dept_build` نفسِه لا اصطلاحٌ ثانٍ. */
function gfc_mig_src($slug, $dept, $body, $isDown, $date)
{
    $name = $date . '_' . $slug . ($isDown ? '_down' : '') . '.php';
    $h  = "<?php\n/**\n * {$name} — {$dept} · "
        . ($isDown ? "العكس" : "أعمدةُ حقولٍ لا نظيرَ لها في المخزن") . "\n"
        . " * @migration-objects: columns for {$dept}\n"
        . " * مولَّدةٌ من `tools/govui_field_close.php` على مواصفةِ الإدارة —\n"
        . " * واسمُ العمودِ تعليقُه اسمُ الحقلِ في ورقةِ الدليل.\n"
        . ($isDown ? " * ⛔ ولا يُسقَط عمودٌ فيه بياناتٌ صامتًا — يُسمَّى ويُترَك.\n" : "")
        . " */\n";
    $h .= "if (php_sapi_name() !== 'cli') { exit(\"CLI فقط\\n\"); }\n"
        . "error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);\n"
        . "mb_internal_encoding('UTF-8');\n"
        . "\$ROOT = dirname(dirname(__DIR__));\n"
        . "require_once \$ROOT . '/includes/env.php';\n"
        . "require_once __DIR__ . '/_ledger.php';\n"
        . "\$host = ems_env('DB_HOST'); \$port = 3306;\n"
        . "if (strpos(\$host, ':') !== false) { list(\$host, \$port) = explode(':', \$host); \$port = (int) \$port; }\n"
        . "mysqli_report(MYSQLI_REPORT_OFF);\n"
        . "\$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');\n"
        . "\$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');\n"
        . "\$conn = new mysqli(\$host, \$u, \$p, ems_env('DB_NAME'), \$port);\n"
        . "if (\$conn->connect_errno) { exit(\"connect fail\\n\"); }\n"
        . "\$conn->set_charset('utf8mb4');\n"
        . "\$t0 = microtime(true);\n\n";
    return $h . $body
         . "ems_migration_recorded(__FILE__, \$conn, (int) round((microtime(true) - \$t0) * 1000));\n";
}
