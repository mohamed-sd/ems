<?php
/**
 * tools/repair01_owner_decision_audit.php — مراجعةٌ عكسيّةٌ لقراراتِ المالك
 * ═══════════════════════════════════════════════════════════════════════════
 * **أمرُ المالك 2026-08-26 · البند ١٢**: «افحصْ كلَّ سجلٍّ حالتُه `APPROVED`
 * وصنّفِ النتيجةَ … ولا يصلح الصفُّ فقط الذي كشف المشكلة. نريد معرفةَ هل هي
 * **حادثةٌ واحدةٌ أم نمط**.»
 *
 * ◆ **والمقياسُ الوحيدُ الذي لا يُخدَع: مقارنةُ المخزنِ بوثيقةِ المالكِ نفسِها.**
 *   `repair01_decisions` جدولٌ **تكتب فيه الأدواتُ**، فقراءتُه وحدَها تقول ما
 *   كُتب لا مَن كتبه. أمّا `09 · السجلات المؤسسية والقرارات.xlsx` فوثيقةُ المالكِ
 *   ومحميّةٌ بتجزئةٍ يفحصها `G0-01` — فهي **المرجعُ لا المخزن**.
 *
 * ◆ **خمسةُ أحكامٍ بنصِّ الأمر**:
 *   · `VALID_APPROVAL`             — نصُّ المخزنِ يطابق خانةَ المالكِ في الوثيقة
 *   · `SYSTEM_ASSUMED_APPROVAL`    — خانةُ المالكِ **خاليةٌ** والمخزنُ يحمل نصًّا
 *   · `CONFLICTING_APPROVAL`       — كلاهما مملوءٌ **ويختلفان**
 *   · `MISSING_APPROVAL_REFERENCE` — معتمَدٌ بلا نصٍّ أصلًا
 *   · `LEGACY_UNVERIFIED`          — لا صفَّ له في الوثيقة (‏قرارٌ خارجَ سجلِّها)
 *
 * ⛔ **ولا يُصلح هذا الفاحصُ شيئًا** — يقيس ويصنّف. والإصلاحُ قرارُ مالكٍ لكلِّ صفّ.
 *
 * التشغيل: php tools/repair01_owner_decision_audit.php [--write]
 * الخروج : 0 لا انتحالَ · 1 وُجد `SYSTEM_ASSUMED` أو `CONFLICTING`
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
$WRITE = in_array('--write', $argv, true);

/* ═══ ① فتحُ وثيقةِ المالكِ وقراءةُ ورقةِ القرارات ═══════════════════════════ */
$xlsx = $ROOT . '/docs/REPAIR01_20260823/09 · السجلات المؤسسية والقرارات.xlsx';
if (!is_file($xlsx)) { exit("✘ لا وثيقةَ مالكٍ في: $xlsx\n"); }

$zip = new ZipArchive();
if ($zip->open($xlsx) !== true) { exit("✘ تعذّر فتحُ الوثيقة\n"); }
/* ◆ **السلاسلُ المشتركةُ قد تغيب**: هذا المصنَّفُ يكتب النصَّ داخلَ الخليّةِ
     (`inlineStr`) لا في `sharedStrings` — فيُقرأ الاثنانِ ولا يُفترَض أحدُهما. */
$shared = array();
if (($ss = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
    if (preg_match_all('~<si>(.*?)</si>~su', $ss, $mm)) {
        foreach ($mm[1] as $si) { $shared[] = html_entity_decode(strip_tags($si), ENT_QUOTES | ENT_HTML5, 'UTF-8'); }
    }
}
$sheet = null;
for ($i = 1; $i <= 20; $i++) {
    $x = $zip->getFromName("xl/worksheets/sheet$i.xml");
    if ($x === false) { continue; }
    if (strpos($x, 'DEC-OPEN-16') !== false || strpos($x, 'DEC-CEO') !== false) { $sheet = $x; break; }
}
$zip->close();
if ($sheet === null) { exit("✘ لم تُوجد ورقةُ القرارات\n"); }

/* ═══ ② تفكيكُ الصفوفِ — خليّةً خليّةً بعمودِها ═══════════════════════════════ */
$colNo = function ($ref) {
    if (!preg_match('~^([A-Z]+)~', $ref, $m)) { return -1; }
    $n = 0;
    for ($i = 0; $i < strlen($m[1]); $i++) { $n = $n * 26 + (ord($m[1][$i]) - 64); }
    return $n - 1;
};
$cellVal = function ($c) use ($shared) {
    if (preg_match('~t="(?:inlineStr|str)"~', $c) || strpos($c, '<is>') !== false) {
        if (preg_match('~<is>(.*?)</is>~su', $c, $m)) { return html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'); }
    }
    if (!preg_match('~<v>(.*?)</v>~su', $c, $m)) { return ''; }
    $v = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (strpos($c, 't="s"') !== false) { return isset($shared[(int) $v]) ? $shared[(int) $v] : ''; }
    return $v;
};
$grid = array();
if (preg_match_all('~<row[^>]*r="(\d+)"[^>]*>(.*?)</row>~su', $sheet, $rr, PREG_SET_ORDER)) {
    foreach ($rr as $r) {
        $cells = array();
        if (preg_match_all('~<c[^>]*r="([A-Z]+\d+)"[^>]*>.*?</c>|<c[^>]*r="([A-Z]+\d+)"[^>]*/>~su', $r[2], $cc, PREG_SET_ORDER)) {
            foreach ($cc as $c) {
                $ref = $c[1] !== '' ? $c[1] : $c[2];
                $cells[$colNo($ref)] = trim($cellVal($c[0]));
            }
        }
        $grid[(int) $r[1]] = $cells;
    }
}
if (!$grid) { exit("✘ لم تُقرأ صفوفٌ من الورقة\n"); }

/* ═══ ③ العنوانُ — موضعُ خانةِ المالكِ يُقرأ ولا يُفترَض ═══════════════════════ */
$hdrRow = null; $cId = -1; $cOwn = -1; $cSt = -1; $cRec = -1;
foreach ($grid as $rn => $cells) {
    foreach ($cells as $ci => $v) {
        $k = str_replace(array(' ', '_'), '', mb_strtolower($v));
        if ($k === 'decisionid' || $k === 'decision') { $cId = $ci; $hdrRow = $rn; }
        if (strpos($k, 'ownerdecision') === 0) { $cOwn = $ci; }
        if ($k === 'status') { $cSt = $ci; }
        if (strpos($k, 'recommended') === 0) { $cRec = $ci; }
    }
    if ($hdrRow !== null && $cOwn >= 0) { break; }
    $hdrRow = null; $cId = -1;
}
if ($hdrRow === null || $cOwn < 0) {
    /* الرجوعُ إلى الترتيبِ المقيسِ إن غاب العنوان — ويُعلَن أنّه رجوع */
    echo "⚠ لم يُقرأ صفُّ عنوانٍ — يُستعمل الترتيبُ المقيسُ (0=المعرّف · 6=قرارُ المالك · 7=الحالة)\n";
    $cId = 0; $cOwn = 6; $cSt = 7; $cRec = 5; $hdrRow = 0;
}

$DOC = array();
foreach ($grid as $rn => $cells) {
    if ($rn <= $hdrRow) { continue; }
    $id = isset($cells[$cId]) ? trim($cells[$cId]) : '';
    if ($id === '' || strpos($id, 'DEC-') !== 0) { continue; }
    $DOC[$id] = array(
        'owner'  => isset($cells[$cOwn]) ? trim($cells[$cOwn]) : '',
        'status' => ($cSt >= 0 && isset($cells[$cSt])) ? trim($cells[$cSt]) : '',
        'rec'    => ($cRec >= 0 && isset($cells[$cRec])) ? trim($cells[$cRec]) : '',
    );
}
printf("وثيقةُ المالك: %d قرارًا مقروءًا · خانةُ القرارِ في العمودِ %d\n\n", count($DOC), $cOwn);

/* ═══ ④ المقارنة ══════════════════════════════════════════════════════════ */
$blank = function ($s) { $t = trim((string) $s); return ($t === '' || $t === '—' || $t === '-' || $t === 'لا' || $t === 'n/a'); };
$norm  = function ($s) { return preg_replace('~\s+~u', ' ', trim(preg_replace('~[*`«»_]~u', '', (string) $s))); };

$V = array();
$r = $conn->query("SELECT decision_id, status, blocker_type, blocking_level, owner_decision, src_ref, approved_at
                     FROM repair01_decisions ORDER BY decision_id");
while ($x = $r->fetch_assoc()) { $V[] = $x; }

$B = array('VALID_APPROVAL' => array(), 'SYSTEM_ASSUMED_APPROVAL' => array(),
           'CONFLICTING_APPROVAL' => array(), 'MISSING_APPROVAL_REFERENCE' => array(),
           'LEGACY_UNVERIFIED' => array());
$appr = 0;
foreach ($V as $d) {
    if ((string) $d['status'] !== 'APPROVED') { continue; }
    $appr++;
    $id = $d['decision_id'];
    $st = $blank($d['owner_decision']) ? '' : $norm($d['owner_decision']);
    if (!isset($DOC[$id])) { $B['LEGACY_UNVERIFIED'][] = array($id, 'لا صفَّ له في وثيقةِ المالك'); continue; }
    $doc = $blank($DOC[$id]['owner']) ? '' : $norm($DOC[$id]['owner']);
    if ($st === '' && $doc === '') { $B['MISSING_APPROVAL_REFERENCE'][] = array($id, 'معتمَدٌ والخانتانِ خاليتان'); continue; }
    if ($st !== '' && $doc === '') {
        /* ◆ **والخانةُ الخاليةُ في الوثيقةِ ليست إدانةً وحدَها**: المالكُ يجيب
             خارجَ المصنَّفِ أحيانًا — بمحادثةٍ أو أمرٍ مكتوب. والفارقُ **المرجع**:
             مرجعٌ يشير إلى موضعِ **السؤالِ** في المصنَّفِ (`NN › … › صNNN`) لا
             يُثبت جوابًا — فهو المكانُ الذي **لا** جوابَ فيه. ومرجعٌ يسمّي
             محادثةً أو ملفَّ جوابٍ يُثبته.
           ⛔ **ولا يُدَّعى انتحالٌ حيث يحتمل الأمرُ جوابًا في جلسةٍ سابقة** —
             فيُفصل «مُنتحَلٌ مُثبَت» عن «بلا مرجعٍ يُتحقَّق منه». */
        $len = mb_strlen((string) $d['owner_decision']);
        $ref = trim((string) $d['src_ref']);
        $isIngestPtr = (bool) preg_match('~^\d+\s*›~u', $ref);
        if ($ref !== '' && !$isIngestPtr) {
            $B['VALID_APPROVAL'][] = array($id, "جوابٌ خارجَ المصنَّفِ بمرجعٍ مسمًّى: $ref");
            continue;
        }
        $why = "الخانةُ خاليةٌ في المصنَّفِ والمخزنُ يحمل $len حرفًا · والمرجعُ يشير إلى موضعِ السؤالِ لا إلى جواب";
        if ($DOC[$id]['rec'] !== '' && mb_strpos($st, $norm($DOC[$id]['rec'])) !== false) { $why .= ' · ويتضمّن نصَّ عمودِ التوصية'; }
        $B['SYSTEM_ASSUMED_APPROVAL'][] = array($id, $why); continue;
    }
    if ($st === '' && $doc !== '') { $B['MISSING_APPROVAL_REFERENCE'][] = array($id, 'الوثيقةُ فيها قرارٌ والمخزنُ خالٍ'); continue; }
    if ($st === $doc || mb_strpos($st, $doc) !== false || mb_strpos($doc, $st) !== false) {
        $B['VALID_APPROVAL'][] = array($id, 'يطابق نصَّ المالك'); continue;
    }
    $B['CONFLICTING_APPROVAL'][] = array($id, 'كلاهما مملوءٌ ويختلفان');
}

/* ═══ ⑤ التقرير ═══════════════════════════════════════════════════════════ */
echo "══ المراجعةُ العكسيّةُ — البند ١٢ من أمرِ المالك ══\n";
printf("  قراراتٌ في المخزن: %d · منها معتمَدٌ: %d\n\n", count($V), $appr);
$bad = 0;
foreach ($B as $k => $list) {
    $n = count($list);
    $mark = (($k === 'SYSTEM_ASSUMED_APPROVAL' || $k === 'CONFLICTING_APPROVAL') && $n > 0) ? '✘' : (($n > 0) ? '◆' : '✔');
    printf("  %s %-28s %d\n", $mark, $k, $n);
    if ($k === 'SYSTEM_ASSUMED_APPROVAL' || $k === 'CONFLICTING_APPROVAL') { $bad += $n; }
    if ($n > 0 && $n <= 25) { foreach ($list as $z) { printf("       · %-14s %s\n", $z[0], $z[1]); } }
    elseif ($n > 25) { foreach (array_slice($list, 0, 6) as $z) { printf("       · %-14s %s\n", $z[0], $z[1]); } printf("       … و%d غيرُها\n", $n - 6); }
}

if ($WRITE) {
    $conn->query("CREATE TABLE IF NOT EXISTS `repair01_decision_audit` (
        `decision_id` VARCHAR(40) NOT NULL,
        `verdict`     VARCHAR(40) NOT NULL COMMENT 'الاحكام الخمسة بنص امر المالك البند 12',
        `why`         VARCHAR(400) NOT NULL,
        `audited_at`  DATETIME NOT NULL,
        PRIMARY KEY (`decision_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='مراجعة عكسية لقرارات المالك'");
    /* ⚠ **و`ems_app` لا يملك `CREATE`** — إنشاءُ الجدولِ يمرُّ بهجرةٍ بمستخدمِ
         الترحيل. فالفشلُ يُقال صراحةً ولا يُقرأ «صفرَ صفٍّ» نجاحًا. */
    $probe = $conn->query("SHOW TABLES LIKE 'repair01_decision_audit'");
    if (!$probe || $probe->num_rows === 0) {
        echo "\n  ⛔ لا جدولَ قيدٍ — و`CREATE` ممنوعٌ على `ems_app`.\n";
        echo "     أنشئْه بهجرةٍ ثمَّ أعِدْ `--write`. والحكمُ أعلاه مقيسٌ ولا يتوقّف عليه.\n";
        echo "\n───────────────────────────────────────────────────────────────\n";
        echo ($bad === 0 ? "الحكم: لا انتحالَ ✔\n" : "الحكم: $bad قرارًا اعتُمد بلا قرارِ مالكٍ حقيقيّ ✘\n");
        exit($bad === 0 ? 0 : 1);
    }
    $conn->query("DELETE FROM repair01_decision_audit");
    $n = 0;
    foreach ($B as $k => $list) {
        foreach ($list as $z) {
            if ($conn->query("INSERT INTO repair01_decision_audit (decision_id, verdict, why, audited_at)
                VALUES ('" . $conn->real_escape_string($z[0]) . "','" . $conn->real_escape_string($k) . "',
                        '" . $conn->real_escape_string($z[1]) . "', NOW())")) { $n++; }
        }
    }
    printf("\n  ✔ قُيِّد الحكمُ في `repair01_decision_audit` — %d صفًّا\n", $n);
}

echo "\n───────────────────────────────────────────────────────────────\n";
echo ($bad === 0
    ? "الحكم: لا انتحالَ ✔ — كلُّ معتمَدٍ يسنده نصُّ المالك\n"
    : "الحكم: $bad قرارًا اعتُمد بلا قرارِ مالكٍ حقيقيّ ✘\n");
exit($bad === 0 ? 0 : 1);
