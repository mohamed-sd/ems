<?php
/**
 * tools/uat_autofill_pollution_cleanup.php
 * ═══════════════════════════════════════════════════════════════════════════
 * كنسُ ما أدخله **المِلءُ العامُّ** (`database/seeds/uat0001/10_autofill.php`) في
 * دفاترَ يُحرِّم المانفستُ بذرَها.
 *
 * **الجذرُ** (أُغلق في 10_autofill.php): المانفست
 * `docs/uat/UAT_DATA_POPULATION_MANIFEST_ar.md` قسمُ **ج** يُدرج 84 جدولًا تحت
 * «هذه **لا تُبذر**: تمتلئ حين تمرّ الدورةُ من الشاشات، **وامتلاؤها هو الشاهد**».
 * والمِلءُ العامُّ لم يكن يقرأ المانفستَ، فكتب **خامًا** في دفاترَ محكومةٍ بخدماتٍ
 * فتجاوز حرّاسَها.
 *
 * **وبصمةُ التلفيقِ قاطعةٌ ولا تُخطئ صفًّا حقيقيًّا**: المِلءُ يضع نصَّ ملاحظةٍ في
 * أيِّ عمودِ نصٍّ لا يعرف اسمَه، من ثمانِ عباراتٍ ثابتةٍ يتبعها ` · UAT-2026<seq>`.
 * فصار في `supplier_contract_lines.unit` **جملةٌ** بدل وحدةٍ (سطرُ عقدٍ لا
 * يُسعَّر)، وفي `contract_price_revisions.period_key` جملةٌ بدل مفتاحِ فترة.
 * ولا صفَّ إنتاجٍ يحمل هذه العباراتَ في عمودٍ مُصنَّف.
 *
 * **قرارُ المالك** (2026-08-12): «حذفُ الملفَّق وإضافةُ داتا غيرِه غيرِ ملفَّقة».
 * فهذه الأداةُ **تحذف** وحدَها؛ والتعويضُ بداتا شبهِ حقيقيةٍ في
 * `database/seeds/uat0002_realistic/` (تمرُّ بالخدماتِ لا خامًا).
 *
 * الاستعمال:
 *   php tools/uat_autofill_pollution_cleanup.php           # جولةٌ جافّة (افتراضيًّا)
 *   php tools/uat_autofill_pollution_cleanup.php --apply   # تنفيذٌ + سجلُّ تراجعٍ JSON
 *   php tools/uat_autofill_pollution_cleanup.php --apply --claims  # ومعه المستخلصاتُ اليتيمة
 *
 * الأمانُ المدمَج:
 *   · جولةٌ جافّةٌ افتراضيًّا — لا كتابةَ بلا `--apply`.
 *   · **مُرجَعُ كلِّ حذفٍ يُفحَص** (config يمنع الرمي فالإخفاقُ صامت).
 *   · **الأبناءُ قبلَ الآباء** بمحاولاتٍ متكرِّرةٍ حتى يتوقّف التقدُّم — فلا
 *     يُفترض ترتيبٌ طوبولوجيٌّ مكتوبٌ بيدٍ.
 *   · سجلُّ تراجعٍ JSON بمعرِّفاتِ كلِّ صفٍّ حُذف قبل حذفِه.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
define('EMS_CLI', true);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once __DIR__ . '/../includes/env.php';

$APPLY  = in_array('--apply', $argv, true);
$CLAIMS = in_array('--claims', $argv, true);

$h = ems_env('DB_HOST'); $p = 3306;
if (strpos($h, ':') !== false) { list($h, $p) = explode(':', $h); $p = (int) $p; }
$conn = new mysqli($h, ems_env('DB_MIGRATOR_USER', ems_env('DB_USER')),
                   ems_env('DB_MIGRATOR_PASS', ems_env('DB_PASS')), ems_env('DB_NAME'), $p);
if ($conn->connect_errno) { fwrite(STDERR, 'اتصال: ' . $conn->connect_error . "\n"); exit(1); }
$conn->set_charset('utf8mb4');
$o = function ($s) { fwrite(STDOUT, $s . "\n"); };

/** عباراتُ المِلءِ العامِّ الثمانُ — بادئةً، فالأعمدةُ القصيرةُ بترتها. */
$PHRASES = array(
    'أُثبت من واقع التشغيل', 'بانتظار اعتماد الإدارة', 'بناءً على طلب الموقع',
    'مراجَعٌ من المالية', 'مطابقٌ للمستند المرفق', 'مُدرجٌ ضمن الدورة الشهرية',
    'وفق المعتمد في محضر', 'يخضع لمراجعة الربع',
);

/* ── ① قائمةُ «لا تُبذر» من المانفستِ نفسِه — مصدرُ الحقيقةِ الواحد ─────────── */
$manifest = dirname(__DIR__) . '/docs/uat/UAT_DATA_POPULATION_MANIFEST_ar.md';
$cycleOnly = array();
if (is_file($manifest)) {
    $md = (string) file_get_contents($manifest);
    $from = strpos($md, '## ج ·');
    if ($from !== false) {
        $to  = strpos($md, '## ', $from + 4);
        $sec = substr($md, $from, ($to === false ? strlen($md) : $to) - $from);
        if (preg_match_all('~`([a-z0-9_]+)`~', $sec, $mm)) {
            $cycleOnly = array_values(array_unique($mm[1]));
        }
    }
}
/* وثغرةُ المانفستِ المعلَنةُ: هذه محكومةٌ بخدمةٍ/مشتقّةٌ وغائبةٌ عن القسم ج.
   و`supplierscontracts` **جدولٌ موروثٌ** مصدرُ مرآةِ عقودِ الموردين — والمِلءُ
   العامُّ لوّثه أيضًا: 11 صفًّا من 20 يحمل عباراتَه، وفيها `first_party` =
   «شمعات إشعال» أي **اسمُ قطعةِ غيارٍ طرفًا متعاقدًا**. */
$cycleOnly = array_values(array_unique(array_merge($cycleOnly,
    array('supplier_contracts', 'supplier_contract_lines', 'supplierscontracts'))));
if (count($cycleOnly) < 50) {
    fwrite(STDERR, "✘ لم تُقرأ قائمةُ المانفست — أُوقفت الأداةُ صونًا للبيانات\n");
    exit(1);
}
$o('── قوائمُ «لا تُبذر»: ' . count($cycleOnly) . ' جدولًا (من المانفست + ثغرتُه المعلَنة)');

/* ── ② الجردُ: أيُّ صفوفٍ تحمل بصمةَ المِلءِ العام؟ ──────────────────────────── */
$targets = array();   // table => array of ids
$pkOf = array();
foreach ($cycleOnly as $tb) {
    $q = $conn->query("SELECT COUNT(*) n FROM information_schema.TABLES
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
                          AND TABLE_NAME = '" . $conn->real_escape_string($tb) . "'");
    if (!$q || !(int) $q->fetch_assoc()['n']) { continue; }

    /* المفتاحُ الأوّليُّ (عمودٌ واحدٌ فقط — وإلا يُتخطّى ويُعلَن) */
    $pk = null;
    $q = $conn->query("SELECT COLUMN_NAME c FROM information_schema.KEY_COLUMN_USAGE
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $conn->real_escape_string($tb) . "'
                          AND CONSTRAINT_NAME = 'PRIMARY' ORDER BY ORDINAL_POSITION");
    $pks = array();
    while ($q && ($x = $q->fetch_assoc())) { $pks[] = $x['c']; }
    if (count($pks) !== 1) { $o('   ○ ' . $tb . ' — مفتاحٌ مركَّبٌ أو بلا مفتاح، يُتخطّى'); continue; }
    $pk = $pks[0];
    $pkOf[$tb] = $pk;

    /* أعمدةُ النصِّ القصيرة والمتوسطة */
    $cols = array();
    $q = $conn->query("SHOW COLUMNS FROM `{$tb}`");
    while ($q && ($x = $q->fetch_assoc())) {
        if (preg_match('~char|text~i', $x['Type'])) { $cols[] = $x['Field']; }
    }
    if (!$cols) { continue; }

    /* ⚠️ **تُطابَق بجذعٍ قصيرٍ لا بالعبارةِ كاملةً.** الأعمدةُ الضيّقةُ **تبتر**
       العبارةَ: `contract_price_revisions.period_key` يحمل «أُثبت من واقع ال»
       فقط — و`LIKE 'أُثبت من واقع التشغيل%'` **لا يطابقه** لأن القيمةَ أقصرُ من
       النمط. (قِيس: أربعُ مراجعاتٍ ملفَّقةٍ نجت من الجولةِ الأولى لهذا السبب.)
       فيُقتطَع جذعٌ من أوّلِ كلِّ عبارةٍ يكفي للتمييزِ ويصمد للبتر. */
    $w = array();
    foreach ($cols as $c) {
        foreach ($PHRASES as $ph) {
            $stem = mb_substr($ph, 0, 12);
            $w[] = "`{$c}` LIKE '" . $conn->real_escape_string($stem) . "%'";
        }
        $w[] = "`{$c}` LIKE '%UAT-2026%'";
    }
    $q = $conn->query("SELECT `{$pk}` id FROM `{$tb}` WHERE " . implode(' OR ', $w));
    if (!$q) { $o('   ⚠ ' . $tb . ' — تعذّر الجرد: ' . $conn->error); continue; }
    $ids = array();
    while ($x = $q->fetch_assoc()) { $ids[] = $x['id']; }
    if ($ids) { $targets[$tb] = $ids; }
}

$total = 0;
$o('');
$o('── الجرد:');
foreach ($targets as $tb => $ids) {
    $all = (int) $conn->query("SELECT COUNT(*) n FROM `{$tb}`")->fetch_assoc()['n'];
    $total += count($ids);
    printf("   %-36s %4d ملفَّقًا من %4d\n", $tb, count($ids), $all);
}
$o('   ══ ' . $total . ' صفًّا في ' . count($targets) . ' جدولًا');

/* ── ③ المستخلصاتُ اليتيمةُ وفواتيرُها (قسمٌ منفصل — بإذنٍ صريح) ───────────── */
$orphanClaims = array(); $orphanInv = array();
if ($CLAIMS) {
    $q = $conn->query("SELECT cl.id FROM claims cl
                        LEFT JOIN contracts ct ON ct.id = cl.contract_id
                       WHERE ct.id IS NULL");
    while ($q && ($x = $q->fetch_assoc())) { $orphanClaims[] = (int) $x['id']; }
    if ($orphanClaims) {
        $in = implode(',', $orphanClaims);
        $q = $conn->query("SELECT id FROM tax_invoices WHERE claim_id IN ({$in})");
        while ($q && ($x = $q->fetch_assoc())) { $orphanInv[] = (int) $x['id']; }
        $sum = (float) $conn->query("SELECT COALESCE(SUM(gross_amount),0) s FROM claims
                                     WHERE id IN ({$in})")->fetch_assoc()['s'];
        $o('');
        $o('── مستخلصاتٌ يتيمةٌ (عقدُها محذوف): ' . count($orphanClaims)
           . ' · Σإجمالي=' . number_format($sum, 2) . ' · فواتيرُها الضريبية=' . count($orphanInv));
    }
}

/* ── ④-أ **قشورٌ فارغة**: عقدٌ بلا تاريخٍ ولا عملةٍ ولا بندٍ ليس عقدًا ──────────
   المِلءُ العامُّ يترك أعمدةً رقميةً وتاريخيةً خاويةً فلا يحمل الصفُّ بصمةَ عبارةٍ.
   وفي `supplierscontracts` صفّان (714 · 719) يشيران إلى مورّدٍ ومشروعٍ حقيقيَّين
   لكن **بلا `actual_start` ولا `actual_end` ولا عملةٍ ولا بندِ معدةٍ واحد** —
   فلا التزامَ فيهما ولا سعرَ ولا مدة. وإكمالُهما يعني **اختراعَ شروطٍ تجارية**،
   وهو عينُ ما تكنسه هذه الأداة. فيُحكَم عليهما بقاعدةٍ مقيسةٍ لا بمعرِّفٍ مكتوب. */
if (in_array('supplierscontracts', $cycleOnly, true) && isset($pkOf['supplierscontracts'])) {
    $q = $conn->query("SELECT sc.id FROM supplierscontracts sc
                       WHERE (sc.actual_start IS NULL OR sc.actual_start = '' OR sc.actual_start = '0000-00-00')
                         AND (sc.actual_end IS NULL OR sc.actual_end = '' OR sc.actual_end = '0000-00-00')
                         AND (sc.price_currency_contract IS NULL OR sc.price_currency_contract = '')
                         AND NOT EXISTS (SELECT 1 FROM suppliercontractequipments e
                                          WHERE e.contract_id = sc.id)");
    $shells = array();
    while ($q && ($x = $q->fetch_assoc())) { $shells[] = $x['id']; }
    if ($shells) {
        $before = isset($targets['supplierscontracts']) ? count($targets['supplierscontracts']) : 0;
        $targets['supplierscontracts'] = array_values(array_unique(
            array_merge($targets['supplierscontracts'] ?? array(), $shells)));
        $o('   + قشورٌ فارغةٌ في supplierscontracts (بلا تاريخٍ ولا عملةٍ ولا بند): '
           . (count($targets['supplierscontracts']) - $before));
    }
}

/* ── ④-ب **ذريّةُ الملفَّقِ ملفَّقةٌ** — تُضَمُّ قبل الحذف ────────────────────────
   بعضُ الأبناءِ لا يحمل بصمةَ العباراتِ (عمودُه رقميٌّ أو تاريخيٌّ) لكنه يشير إلى
   **أبٍ ملفَّقٍ مُثبَتٍ** — فهو من صُنعِ المِلءِ نفسِه. ولا يُترك: بقاؤه يمنع حذفَ
   أبيه (FK) ويُخلّف صفًّا يشير إلى لا شيء. فيُستنبَط من `information_schema`
   لا من قائمةٍ مكتوبةٍ بيد، بتنازلٍ حتى لا يبقى مُشير. */
for ($depth = 0; $depth < 5; $depth++) {
    $grew = false;
    foreach (array_keys($targets) as $tb) {
        if (!isset($pkOf[$tb]) || !$targets[$tb]) { continue; }
        $in = implode(',', array_map(function ($v) use ($conn) {
            return "'" . $conn->real_escape_string((string) $v) . "'"; }, $targets[$tb]));
        $r = $conn->query("SELECT TABLE_NAME tn, COLUMN_NAME cc FROM information_schema.KEY_COLUMN_USAGE
                            WHERE REFERENCED_TABLE_NAME = '" . $conn->real_escape_string($tb) . "'
                              AND REFERENCED_COLUMN_NAME = '" . $conn->real_escape_string($pkOf[$tb]) . "'
                              AND TABLE_SCHEMA = DATABASE()");
        while ($r && ($x = $r->fetch_assoc())) {
            $ct = $x['tn'];
            if ($ct === $tb) { continue; }                       // مفتاحٌ ذاتيٌّ — تكفيه جولاتُ الحذف
            if (!isset($pkOf[$ct])) {
                $pq = $conn->query("SELECT COLUMN_NAME c FROM information_schema.KEY_COLUMN_USAGE
                                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $conn->real_escape_string($ct) . "'
                                       AND CONSTRAINT_NAME = 'PRIMARY' ORDER BY ORDINAL_POSITION");
                $cp = array();
                while ($pq && ($y = $pq->fetch_assoc())) { $cp[] = $y['c']; }
                if (count($cp) !== 1) { continue; }
                $pkOf[$ct] = $cp[0];
            }
            $q2 = $conn->query("SELECT `{$pkOf[$ct]}` id FROM `{$ct}` WHERE `{$x['cc']}` IN ({$in})");
            $add = array();
            while ($q2 && ($y = $q2->fetch_assoc())) { $add[] = $y['id']; }
            if (!$add) { continue; }
            $before = isset($targets[$ct]) ? count($targets[$ct]) : 0;
            $targets[$ct] = array_values(array_unique(array_merge($targets[$ct] ?? array(), $add)));
            if (count($targets[$ct]) > $before) { $grew = true; }
        }
    }
    if (!$grew) { break; }
}
$totalWithKids = 0;
foreach ($targets as $ids) { $totalWithKids += count($ids); }
if ($totalWithKids > $total) {
    $o('   + ذريّةُ الملفَّقِ: ' . ($totalWithKids - $total) . ' صفًّا إضافيًّا (أبناءُ صفوفٍ ملفَّقةٍ مُثبَتة)');
}

/* ── جولةٌ جافّةٌ: تُطبع **كلُّ** ما سيُحذف (بعد ضمِّ القشورِ والذريّة) ثم تتوقّف.
     وكانت تخرج **قبل** هذين القسمين فتُبلّغ أقلَّ مما تحذف — وجولةٌ جافّةٌ تُخفي
     شيئًا أسوأُ من لا شيء، لأنها تُقرأ إذنًا لِما لم يُعرَض. */
$grand = 0;
foreach ($targets as $ids) { $grand += count($ids); }
$o('');
$o('── ما سيُحذف فعلًا (بالقشورِ والذريّة): ' . $grand . ' صفًّا في ' . count($targets) . ' جدولًا');
foreach ($targets as $tb => $ids) { printf("   %-36s %4d\n", $tb, count($ids)); }
if (!$APPLY) {
    $o('');
    $o('◆ جولةٌ جافّة — لا كتابةَ. للتنفيذ: --apply' . ($CLAIMS ? ' --claims' : ''));
    exit(0);
}

/* ── ④ سجلُّ التراجعِ قبل أيِّ حذف ─────────────────────────────────────────── */
$repDir = dirname(__DIR__) . '/storage/reports';
if (!is_dir($repDir)) { @mkdir($repDir, 0777, true); }
$stamp = date('Ymd_His');
$undo = array('at' => date('c'), 'tool' => basename(__FILE__),
              'tables' => array(), 'orphan_claims' => $orphanClaims, 'orphan_invoices' => $orphanInv);
foreach ($targets as $tb => $ids) {
    $in = implode(',', array_map(function ($v) use ($conn) { return "'" . $conn->real_escape_string((string) $v) . "'"; }, $ids));
    $rows = array();
    $q = $conn->query("SELECT * FROM `{$tb}` WHERE `{$pkOf[$tb]}` IN ({$in})");
    while ($q && ($x = $q->fetch_assoc())) { $rows[] = $x; }
    $undo['tables'][$tb] = array('pk' => $pkOf[$tb], 'rows' => $rows);
}
$undoFile = $repDir . '/uat_autofill_cleanup_' . $stamp . '.json';
file_put_contents($undoFile, json_encode($undo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
$o('');
$o('── سجلُّ التراجع: ' . $undoFile);

/* ── ⑤ الحذفُ: محاولاتٌ متكرِّرةٌ حتى يتوقّف التقدُّم (الأبناءُ يسقطون أوّلًا) ── */
$pending = array();
foreach ($targets as $tb => $ids) { $pending[$tb] = $ids; }
if ($CLAIMS && $orphanInv)    { $pending['tax_invoices'] = $orphanInv; $pkOf['tax_invoices'] = 'id'; }
if ($CLAIMS && $orphanClaims) { $pending['claims'] = $orphanClaims;    $pkOf['claims'] = 'id'; }

$deleted = array(); $blocked = array();
for ($pass = 1; $pass <= 8 && $pending; $pass++) {
    $progress = 0;
    foreach (array_keys($pending) as $tb) {
        $left = array();
        foreach ($pending[$tb] as $id) {
            $ok = $conn->query("DELETE FROM `{$tb}` WHERE `{$pkOf[$tb]}` = '"
                . $conn->real_escape_string((string) $id) . "' LIMIT 1");
            if ($ok === false || $conn->affected_rows < 1) {
                $left[] = $id;
                $blocked[$tb] = $conn->error !== '' ? $conn->error : 'صفرُ صفوفٍ متأثِّرة';
            } else {
                $deleted[$tb] = ($deleted[$tb] ?? 0) + 1;
                $progress++;
            }
        }
        if ($left) { $pending[$tb] = $left; } else { unset($pending[$tb], $blocked[$tb]); }
    }
    $o('   جولة ' . $pass . ': حُذف ' . $progress . ' صفًّا · باقٍ ' . count($pending) . ' جدولًا');
    if ($progress === 0) { break; }
}

$o('');
$o('── ما حُذف:');
$sumDel = 0;
foreach ($deleted as $tb => $n) { $sumDel += $n; printf("   %-36s %4d\n", $tb, $n); }
$o('   ══ ' . $sumDel . ' صفًّا');
if ($pending) {
    $o('');
    $o('── ما امتنع (مُشيرٌ لم يُكشف — يُعلَن ولا يُجبَر):');
    foreach ($pending as $tb => $ids) {
        printf("   %-36s %4d باقٍ · %s\n", $tb, count($ids), mb_substr((string) ($blocked[$tb] ?? '—'), 0, 90));
    }
}
$o('');
$o('✅ اكتمل. التراجع: ' . $undoFile);
exit(0);
