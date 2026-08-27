<?php
/**
 * tools/repair01_edc_tabs.php — كلُّ تبويبٍ يُحكم على حدة ⛔ لا جملةً
 * ═══════════════════════════════════════════════════════════════════════════
 * **حكمُ المالك 2026-08-27 · ⑦**: الثمانيةُ والخمسون تبويبًا **تُحكم فرادى**،
 * و«**لا نضحّي بالصلاحيةِ من أجلِ سايدبارٍ أنظف**».
 *
 * ◆ **والمعيارُ ليس رأيًا بل قابلٌ للقياس**: دمجُ تبويبٍ في أبيه **يُخفيه من
 *   السايدبارِ ويجعل بلوغَه عبرَ الأب**. فمن كان يرى التبويبَ ولا يرى أباه
 *   **يفقد الوصولَ فعلًا**. ⇒ فالحكم:
 *     · `MERGE_SAFE`   — كلُّ من يرى التبويبَ يرى أباه ⇒ **لا فقدَ صلاحية**
 *     · `KEEP_ITEM`    — ثمَّ من يرى التبويبَ ولا يرى أباه ⇒ **الدمجُ يسلبه**
 *     · `HIDDEN_WITH_LIVE_GRANT` — مخفيٌّ سلفًا (`active=0`) والمنحُ حيّ ⇒
 *                        **فجوةُ وصولٍ سابقةٌ لهذه المرحلة** لا مسألةُ دمج
 *     · `NO_PARENT`    — لا أبَ مسجَّلٌ وصفرُ دورٍ يراه ⇒ يُصحَّح حكمُ ملكيّتِه
 *     · `NO_GRANT`     — صفرُ دورٍ يراه ⇒ **لا يُدمَج ولا يُحكم** حتى يُمنَح
 *
 * ⛔ **والدمجُ ليس إخفاءً**: `MERGE_SAFE` تعني «آمنٌ **متى استضافه أبوه تبويبًا**»
 *   — فإخفاءُ بندٍ قبل أن يستضيفه الأبُ **فقدُ وصولٍ لا تنظيمُ سايدبار**، وهو
 *   نفسُ ما نهى عنه الحكم. ⇒ **فهذه الأداةُ تحسم القابليّةَ ولا تُخفي شيئًا.**
 *
 * ⛔ **ورفعُ صلاحيةِ الأبِ ليصير الدمجُ آمنًا ممنوع**: هو **توسيعُ وصولٍ حيٍّ**
 *   يقرّره المالكُ لا المبرمج. فالتبويبُ يبقى بندًا مستقلًّا **ويُعلَن سببُه**.
 *
 * التشغيل: php tools/repair01_edc_tabs.php [--apply] [--md]
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
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');
$APPLY = in_array('--apply', $argv, true);
$MD    = in_array('--md', $argv, true);
$e = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };

/* ⚠ **وقياسُ الصلاحيةِ وحدَه لا يُثبت أنَّ الأبَ أبٌ**: أحكامي الأولى أخرجت
     «مشغّلون» تبويبًا في «اختيار مشروع» و«استبعادُ أصل» في «سجلِّ المُلّاك» —
     **وكلاهما آمنٌ صلاحيًّا ومستحيلٌ دلاليًّا**. ⇒ **فالتبويبُ الحقيقيُّ يعمل
     على كيانِ أبيه**، وذلك يُقاس: **أيتشاركان جدولًا؟** وصفرُ تشاركٍ ⇒ الأبوّةُ
     المسجَّلةُ مشكوكةٌ **تُرفع ولا يُبنى عليها**. */
$tables = function ($file) use ($ROOT) {
    static $idx = null, $memo = array();
    if ($idx === null) {
        $idx = array();
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $q) {
            if (substr($q->getFilename(), -4) !== '.php') { continue; }
            $sp = strtr($q->getPathname(), DIRECTORY_SEPARATOR, '/');
            if (strpos($sp, '/.git/') !== false || strpos($sp, '/vendor/') !== false) { continue; }
            if (!isset($idx[$q->getFilename()])) { $idx[$q->getFilename()] = $sp; }
        }
    }
    $b = basename($file);
    if (isset($memo[$b])) { return $memo[$b]; }
    $c = isset($idx[$b]) ? (string) @file_get_contents($idx[$b]) : '';
    preg_match_all('~\b(?:FROM|JOIN|INSERT\s+INTO|UPDATE)\s+`?([a-z_][a-z_0-9]{2,})~i', $c, $m);
    return $memo[$b] = array_unique(array_map('strtolower', $m[1]));
};

/** الأدوارُ التي ترى مسارًا — من المنحِ الحيِّ لا من افتراض. */
$viewers = function ($file) use ($conn, $e) {
    $r = $conn->query("SELECT DISTINCT rp.role_id FROM role_permissions rp
                         JOIN modules m ON m.id = rp.module_id
                        WHERE rp.can_view = 1 AND m.code LIKE CONCAT('%', '" . $e(basename($file)) . "')");
    $o = array();
    while ($r && ($x = $r->fetch_row())) { $o[(int) $x[0]] = true; }
    return $o;
};

$rows = array();
$q = $conn->query("SELECT t.screen_id, t.screen_file, t.owner_code, t.canonical_label_ar,
                          t.parent_screen_id, p.screen_file AS pfile, p.canonical_label_ar AS plabel
                     FROM repair01_screen_registry t
                     LEFT JOIN repair01_screen_registry p ON p.screen_id = t.parent_screen_id
                    WHERE t.ownership_verdict = 'TAB_CHILD' AND t.on_disk = 1
                    ORDER BY t.owner_code, t.screen_file");
while ($q && ($x = $q->fetch_assoc())) {
    $tv = $viewers($x['screen_file']);
    $pv = ($x['pfile'] !== null && $x['pfile'] !== '') ? $viewers($x['pfile']) : array();
    $lost = array_keys(array_diff_key($tv, $pv));
    sort($lost);

    /* ⚠ **الأبُ لم يُنسَخ إلى العمود**: اثنان وعشرون صفًّا وُسِمت `TAB_CHILD` من
         `nav_items.active = 0` — و«الخاملُ قرارُ دمجٍ في تبويباتٍ لا إهمال» —
         **لكنَّ `nav_items` بلا عمودِ أبٍ أصلًا**، فلم يُسجَّل الأبُ في أيِّ مكان.
       ⇒ **فهم مخفيّون من الملاحةِ والأدوارُ ما تزال تملك رؤيتَهم**، ولا تبويبَ
         يُبلغهم. والأبُ المرشَّحُ **يُشتقُّ بقياسٍ**: سطحٌ في المجلَّدِ نفسِه،
         حيٌّ في الملاحة، **ورؤيتُه تشمل كلَّ من يرى الابن**. ⛔ ومرشَّحان
         فأكثرُ = لا اشتقاق، فالغموضُ يُعلَن ولا يُحسم بالأغلبيّة. */
    if (((string) $x['parent_screen_id'] === '' || $x['pfile'] === null) && $tv) {
        /* ⚠ **`screen_file` اسمٌ مجرَّدٌ لا مسار** — فـ`dirname` عليه يعطي `.`
             ويُفرِّغ كلَّ مرشَّح. المجلَّدُ من `route`. */
        $dir = dirname((string) $x['route']);
        /* ⚠ **وإشارةُ مجموعةِ الملاحةِ جُرِّبت وسقطت**: المجموعةُ محسوبةٌ
             **لكلِّ دورٍ على حدة**، ومجموعةُ الابنِ (4026) **يتيمةٌ لا صفَّ فيها**.
             ⇒ **فلا اشتقاقَ للأبِ من بياناتٍ مسجَّلة** — والاختراعُ ممنوع. */
        $cand = array();
        $cq = $conn->query("SELECT screen_file FROM repair01_screen_registry
                             WHERE on_disk = 1 AND ownership_verdict <> 'TAB_CHILD'
                               AND surface_kind = 'SOURCE'
                               AND route LIKE '" . $e($dir) . "/%'
                               AND owner_code = '" . $e($x['owner_code']) . "'");
        while ($cq && ($cy = $cq->fetch_row())) {
            $cv = $viewers($cy[0]);
            if (array_diff_key($tv, $cv)) { continue; }          /* يسلب وصولًا */
            $cand[] = $cy[0];
        }
        if (count($cand) === 1) {
            $x['pfile'] = $cand[0]; $pv = $viewers($cand[0]);
            $lost = array();
            $v = 'MERGE_SAFE';
            $why = 'الاب اشتق بالقياس (' . basename($cand[0]) . ') — مرشح واحد في المجلد ورؤيته تشمل كل من يرى الابن';
        } else {
            /* ◆ **والحالُ يُسمَّى كما هو لا كما نتمنّاه**: هؤلاء **مخفيّون من
                 الملاحةِ سلفًا** (`active=0`) **والأدوارُ ما تزال تملك رؤيتَهم**.
                 فليست المسألةُ «أيَّ أبٍ يُدمجون فيه» بل **فجوةُ وصولٍ قائمةٌ
                 قبل هذه المرحلة**: سطحٌ لا يبلغه المخوَّلُ إلّا برابطٍ مباشر.
                 ⇒ ومعيارُ الخروجِ أحدُ أمرَين **بقرارِ مالكٍ لا بقرارِ مبرمج**:
                 يُسحب المنحُ أو يُعاد إظهارُه. ⛔ ولا يُخترَع له أبٌ ليُغلَق البند. */
            $v = 'HIDDEN_WITH_LIVE_GRANT';
            $why = 'مخفي من الملاحة (active=0) و' . count($tv) . ' دورا يملك رؤيته'
                 . ' — فجوة وصول سابقة لهذه المرحلة · لا اب مسجل ولا مشتق (' . count($cand) . ' مرشحا)'
                 . ' · الخروج بسحب المنح او اعادة الاظهار بقرار مالك';
        }
    } elseif ((string) $x['parent_screen_id'] === '' || $x['pfile'] === null) {
        $v = 'NO_PARENT';
        $why = 'لا اب مسجل وصفر دور يراه — فليس تبويبا وحكم الملكية يصحح';
    } elseif (!$tv) {
        $v = 'NO_GRANT';
        $why = 'صفر دور يراه — لا يدمج ولا يحكم حتى يمنح';
    } elseif ($lost) {
        $v = 'KEEP_ITEM';
        $why = 'يراه بلا اب: ادوار ' . implode(',', $lost)
             . ' — والدمج يسلبها الوصول · ورفع صلاحية الاب توسيع وصول حي يقرره المالك';
    } else {
        $sh = array_intersect($tables($x['screen_file']), $tables($x['pfile']));
        if (!$sh) {
            $v = 'PARENT_DOUBTFUL';
            $why = 'امن صلاحيا لكن صفر جدول مشترك مع ' . basename($x['pfile'])
                 . ' — والتبويب يعمل على كيان ابيه · الابوة المسجلة مشكوكة ترفع ولا يبنى عليها';
        } else {
            $v = 'MERGE_READY';
            $why = 'كل من يراه (' . count($tv) . ' دورا) يرى اباه ' . basename($x['pfile'])
                 . ' ويتشاركان ' . count($sh) . ' جدولا — لا فقد صلاحية وابوة مقيسة';
        }
    }
    $rows[] = $x + array('v' => $v, 'why' => $why, 'nt' => count($tv), 'np' => count($pv), 'lost' => $lost);
}

$tot = array();
foreach ($rows as $x) { $tot[$x['v']] = (isset($tot[$x['v']]) ? $tot[$x['v']] : 0) + 1; }

echo "\n═══ حكمُ التبويباتِ فرادى — المعيارُ الصلاحيةُ لا نظافةُ السايدبار ═══\n";
printf("  المحكومُ عليها: %d\n", count($rows));
foreach ($tot as $k => $n) { printf("     %-12s %d\n", $k, $n); }
echo "\n";
foreach ($rows as $x) {
    printf("  %-12s %-28s %-8s → %-24s %s\n", $x['v'], $x['screen_file'], $x['owner_code'],
        ($x['pfile'] !== null ? basename($x['pfile']) : '—'),
        ($x['lost'] ? 'يفقد ' . count($x['lost']) . ' دورًا' : ''));
}

if ($APPLY) {
    foreach ($rows as $x) {
        /* ⛔ **الحكمُ يُسجَّل ولا يُنفَّذ هنا**: الدمجُ تغييرُ ملاحةٍ حيّ، وهذه
             الأداةُ **تحسم القابليّةَ** وتكتب سببَها. والتنفيذُ خطوةٌ تالية. */
        $conn->query("UPDATE repair01_screen_registry
            SET parent_rule = '" . $e($x['v'] . ': ' . $x['why']) . "', verdict_at = NOW()
          WHERE screen_id = '" . $e($x['screen_id']) . "'");
    }
    printf("✔ سُجِّل حكمُ %d تبويبًا — لكلٍّ سببُه\n", count($rows));
}

/* ── قائمةُ البناءِ الحقيقيّة: عددُ **الآباء** لا عددُ الأبناء ────────────── */
$byParent = array();
foreach ($rows as $x) {
    if ($x['v'] !== 'MERGE_READY') { continue; }
    $k = basename((string) $x['pfile']);
    if (!isset($byParent[$k])) { $byParent[$k] = array(); }
    $byParent[$k][] = basename((string) $x['screen_file']);
}
echo "
── البناءُ المطلوب: تبويباتٌ تُستضاف في آبائها ──
";
printf("  آباءٌ يُبنى فيهم: **%d** · أبناءٌ يُستضافون: %d
", count($byParent), array_sum(array_map('count', $byParent)));
foreach ($byParent as $k => $v2) { printf("     %-24s ← %s
", $k, implode(' · ', $v2)); }

if ($MD) {
    $o  = "# حكمُ التبويباتِ فرادى — البندُ ⑦\n\n";
    $o .= "> ⛔ **مولَّدٌ من المخزن**: `php tools/repair01_edc_tabs.php --md`\n";
    $o .= "> **حكمُ المالك**: «لا نضحّي بالصلاحيةِ من أجلِ سايدبارٍ أنظف»\n";
    $o .= "> — فكلُّ تبويبٍ يُحكم على حدة، **والمعيارُ من المنحِ الحيّ**:\n";
    $o .= "> من يرى التبويبَ ولا يرى أباه **يفقد الوصولَ بالدمج**.\n\n";
    $o .= "| الحكم | العدد | معناه |\n|---|---:|---|\n";
    $D = array(
      'MERGE_READY' => 'آمنٌ صلاحيًّا **وأبوّتُه مقيسةٌ بجدولٍ مشترَك**',
      'PARENT_DOUBTFUL' => 'آمنٌ صلاحيًّا **وصفرُ جدولٍ مشترَك** — الأبوّةُ المسجَّلةُ مشكوكة',
      'KEEP_ITEM'  => '**يبقى بندًا مستقلًّا** — الدمجُ يسلب أدوارًا وصولَها',
      'NO_PARENT'  => 'لا أبَ مسجَّلٌ وصفرُ دورٍ يراه — فليس تبويبًا، ويُصحَّح حكمُ ملكيّتِه',
      'HIDDEN_WITH_LIVE_GRANT' => '**مخفيٌّ من الملاحةِ والمنحُ حيّ** — فجوةُ وصولٍ سابقةٌ لهذه المرحلة',
      'NO_GRANT'   => 'صفرُ دورٍ يراه — لا يُدمَج ولا يُحكم حتى يُمنَح');
    foreach ($tot as $k => $n) { $o .= sprintf("| `%s` | %d | %s |\n", $k, $n, isset($D[$k]) ? $D[$k] : ''); }
    $o .= "\n| التبويب | الإدارة | الأب | الحكم | السبب |\n|---|---|---|---|---|\n";
    foreach ($rows as $x) {
        $o .= sprintf("| `%s` | %s | `%s` | `%s` | %s |\n", $x['screen_file'], $x['owner_code'],
            ($x['pfile'] !== null ? basename($x['pfile']) : '—'), $x['v'], $x['why']);
    }
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/EDC_TABS_JUDGED.md', $o);
    echo "✔ كُتب docs/REPAIR01_20260823/EDC_TABS_JUDGED.md\n";
}
