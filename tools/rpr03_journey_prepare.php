<?php
/**
 * tools/rpr03_journey_prepare.php — `RPR-03` §٦·٦ · تجهيزُ الرحلاتِ الستِّ قبلَ الأشخاص
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — `CONTINUE` §٦: *«⛔ **وجهِّزْ ما يمكن تجهيزُه من ٩
 *   و١٦ الآن**: سيناريوهاتُ الرحلاتِ الستِّ · والحساباتُ والأدوارُ · وقائمةُ
 *   العشرِ الذهبيّات — **فيبقى التنفيذُ ساعةً حين يتوفّر الأشخاص**، لا أسبوعًا»*.
 *   و§٦·٦ يسمّي الستَّ: **الإيراد · المورّد · المشتريات · التوقّف · القوى
 *   العاملة · القرار التنفيذيّ**.
 *
 * ◆ **والمقيسُ: اثنتان من ستٍّ مجهَّزتان** — `MNT_CYCLE` (‏التوقّف · ٤ محطّات)
 *   و`REQ_TO_EFFECT` (‏القوى العاملة · ٩ محطّات) في `repair01_w16_uat`.
 *   ⇒ **والأربعُ الباقياتُ تُجهَّز الآن**.
 *
 * ◆ **والسيناريو لا يُؤلَّف — يُنتزع من مصدرَين مُعلَنَين**:
 *   **① `gov_screen_cycle`** — تحمل لكلِّ إدارةٍ **مراحلَ مرتَّبةً** (`stage_order`)
 *      بأسمائها (`stage_name`) **وبالدورِ المسؤولِ عن كلٍّ** (`resp_role`).
 *      ⇒ **فهي تعريفُ رحلةٍ مُعلَنٌ سلفًا**، والمحطّاتُ ترتيبُها لا اختراعُها.
 *   **② `repair01_w{N}_sod`** — تحمل **التركيبةَ الممنوعةَ** لكلِّ عمليةٍ حرِجة.
 *      ⇒ **فالمحطّةُ السالبةُ منصوصةٌ**: «يحاول من فعل كذا أن يفعل كذا فيُردّ».
 *
 * ⛔ **ولا تُكتب حالةُ نجاحٍ ألبتّة** — كلُّ محطّةٍ تولد `PENDING`، والقاعدةُ
 *   الصلبةُ `chk_w16_uat_real` تردُّ `PASSED` بلا **فاعلٍ حقيقيٍّ وزمنٍ ودليلٍ
 *   واسم**، و`chk_w16_uat_negative` تردُّ سالبًا ناجحًا بلا قيدٍ في سجلِّ
 *   المحاولات. ⇒ **فالتجهيزُ يقرّب الساعةَ ولا يزعم اجتيازًا**.
 *
 * ⛔ **وخانةُ الشخصِ تُشتقّ من تمايزِ الدور** — لا من عددٍ مختار: كلُّ دورٍ
 *   متمايزٍ في الرحلةِ يأخذ خانةً (`P1`, `P2`…)، **فيصير فصلُ الواجباتِ مقيسًا
 *   في التجهيزِ نفسِه** لا مؤجَّلًا إلى التنفيذ (§٦·٦: «أشخاصٌ مختلفون لا
 *   أدوارٌ متعدّدةٌ في حسابٍ واحد»).
 *
 * ⚠ **وربطُ الرحلةِ بإدارتِها يُقال ولا يُخفى**: ثلاثٌ بمطابقةِ اسمٍ حرفيّة
 *   (المورّد · المشتريات · القرار التنفيذيّ)، **و«الإيراد» رُبط بـ«المبيعات
 *   والعقود»** لأنَّ دورةَ الإيرادِ تبدأ من عقدِ العميلِ وتنتهي بمطالبتِه —
 *   **وهو ربطُ نطاقٍ لا ربطُ اسم**، فيُعلَن هنا كي يُراجَع.
 *
 * التشغيل:
 *   php tools/rpr03_journey_prepare.php [--apply] [--md] [--selftest]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$e = function ($x) use ($conn) { return $conn->real_escape_string((string) $x); };

$APPLY = in_array('--apply', $argv, true);
$MD    = in_array('--md', $argv, true);
$SELF  = in_array('--selftest', $argv, true);

/* ═══ ① الرحلاتُ الستُّ ومصادرُها المُعلَنة ═════════════════════════════════ */
$JOURNEY = array(
    'REVENUE_CYCLE'     => array('الإيراد', 'المبيعات والعقود', array(8), 'sal.',
        'دورةُ الإيرادِ تبدأ من عقدِ العميلِ وتنتهي بمطالبتِه ⇒ **ربطُ نطاقٍ لا ربطُ اسم**'),
    'SUPPLIER_CYCLE'    => array('المورّد', 'إدارة الموردين', array(8), 'sup.',
        'مطابقةُ اسمٍ حرفيّة'),
    'PROCUREMENT_CYCLE' => array('المشتريات', 'إدارة المشتريات التشغيلية', array(9), 'prc.',
        'مطابقةُ اسمٍ حرفيّة'),
    'EXEC_DECISION'     => array('القرار التنفيذيّ', 'مكتب الرئيس التنفيذي والنواب', array(14), '',
        'مطابقةُ اسمٍ حرفيّة'),
);

/* ═══ ② الاختبارُ السالبُ — يُصيب الطرفَين ولا يمرُّ بمفردةٍ فريدة ═══════ */
if ($SELF) {
    $fail = 0;
    /* خانةُ الشخصِ تُشتقّ من تمايزِ الدور — والدورُ نفسُه يأخذ الخانةَ نفسَها */
    $slot = function ($roles) {
        $m = array(); $n = 0;
        foreach ($roles as $r) { if (!isset($m[$r])) { $m[$r] = 'P' . (++$n); } }
        return $m;
    };
    $m = $slot(array('أ', 'ب', 'أ', 'ج'));
    if (count($m) !== 3)      { echo "  X الخاناتُ لم تُشتقّ من التمايز\n"; $fail++; }
    if ($m['أ'] !== 'P1' || $m['ج'] !== 'P3') { echo "  X ترتيبُ الخاناتِ خطأ\n"; $fail++; }
    /* ⛔ **الكاسر**: دورٌ واحدٌ لا يُنتج خانتَين — ولو أنتج لَبدا فصلُ واجباتٍ وهميّ */
    $m2 = $slot(array('أ', 'أ', 'أ'));
    if (count($m2) !== 1) { echo "  X دورٌ واحدٌ أنتج أكثرَ من خانة — فصلٌ وهميّ\n"; $fail++; }
    if (count($JOURNEY) !== 4) { echo "  X الرحلاتُ المجهَّزةُ ليست أربعًا\n"; $fail++; }
    /* والقيدان الصلبان لازمان — فلولاهما قُبِل نجاحٌ بلا إنسان */
    $r = $conn->query("SHOW CREATE TABLE `repair01_w16_uat`");
    $ddl = $r ? $r->fetch_row()[1] : '';
    if (strpos($ddl, 'chk_w16_uat_real') === false)     { echo "  X `chk_w16_uat_real` غائب\n"; $fail++; }
    if (strpos($ddl, 'chk_w16_uat_negative') === false) { echo "  X `chk_w16_uat_negative` غائب\n"; $fail++; }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n"
               : "\n🟢 الفحصُ الذاتيُّ تامٌّ — والخانةُ تتبع تمايزَ الدورِ ولا تُنتج فصلًا وهميًّا\n";
    exit($fail ? 1 : 0);
}

/* ═══ ③ نافذةُ القياس ════════════════════════════════════════════════════ */
$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
if (!$snap && $APPLY) { exit("⛔ **لا نافذةَ قياسٍ مفتوحة** — جمِّدْ أوّلًا.\n"); }
$sid = $snap ? $snap['snapshot_id'] : 'DRY';

/* ═══ ④ ما هو مجهَّزٌ سلفًا — ⛔ ولا يُدهَس ═══════════════════════════════ */
$have = array();
$r = $conn->query("SELECT journey_key, COUNT(*) n FROM repair01_w16_uat GROUP BY journey_key");
while ($r && $x = $r->fetch_row()) { $have[$x[0]] = (int) $x[1]; }

/* رمزُ الإدارة من اسمِها */
$dept = array();
$r = $conn->query("SELECT canonical_code, name_ar FROM repair01_departments");
while ($r && $x = $r->fetch_assoc()) { $dept[$x['name_ar']] = $x['canonical_code']; }
$deptCode = function ($name) use ($dept) {
    foreach ($dept as $n => $c) {
        $bare = trim(str_replace(array('إدارة', 'الإدارة'), '', $n));
        if ($bare !== '' && (mb_strpos($name, $bare) !== false || mb_strpos($bare, $name) !== false)) {
            return $c;
        }
    }
    return '';
};

/* أعلى رقمِ محطّةٍ قائم */
$maxNo = (int) $conn->query("SELECT COALESCE(MAX(CAST(SUBSTRING(station_id,7) AS UNSIGNED)),0)
                               FROM repair01_w16_uat")->fetch_row()[0];

$plan = array(); $skipped = array();
foreach ($JOURNEY as $jk => $J) {
    list($jAr, $deptName, $sodWaves, $sodPrefix, $why) = $J;
    if (isset($have[$jk])) { $skipped[] = $jk . ' (' . $have[$jk] . ' محطّة)'; continue; }
    $dc = $deptCode($deptName);
    /* المراحلُ المرتَّبةُ المُعلَنة */
    $st = array();
    $q = $conn->query("SELECT stage_order, stage_name, resp_role FROM gov_screen_cycle
                        WHERE dept_name = '" . $e($deptName) . "' AND screen_id <> ''
                          AND stage_name <> '' AND resp_role <> ''
                        GROUP BY stage_order, stage_name, resp_role
                        ORDER BY CAST(stage_order AS UNSIGNED), stage_name");
    $seen = array();
    while ($q && $x = $q->fetch_assoc()) {
        $k = $x['stage_order'] . '|' . $x['stage_name'];
        if (isset($seen[$k])) { continue; }
        $seen[$k] = 1;
        $st[] = $x;
    }
    /* التركيبةُ الممنوعةُ من فصلِ الواجبات */
    $neg = array();
    foreach ($sodWaves as $w) {
        $q = @$conn->query("SELECT process_key, forbidden_combo FROM repair01_w{$w}_sod
                             WHERE forbidden_combo <> ''"
                         . ($sodPrefix !== '' ? " AND process_key LIKE '" . $e($sodPrefix) . "%'" : '')
                         . " ORDER BY process_key LIMIT 3");
        while ($q && $x = $q->fetch_assoc()) { $neg[] = $x; }
    }
    /* خاناتُ الأشخاصِ من تمايزِ الدور */
    $slots = array(); $n = 0;
    foreach ($st as $x) { if (!isset($slots[$x['resp_role']])) { $slots[$x['resp_role']] = 'P' . (++$n); } }
    $no = 0;
    foreach ($st as $x) {
        $no++;
        $plan[] = array($jk, $no,
            'يمرُّ «' . $x['stage_name'] . '» بدورِ «' . $x['resp_role'] . '» في ' . $deptName,
            $dc, $x['resp_role'], $slots[$x['resp_role']], 0);
    }
    foreach ($neg as $x) {
        $no++;
        $role = $st ? $st[0]['resp_role'] : 'صاحبُ العملية';
        $plan[] = array($jk, $no,
            'يحاول «' . $x['process_key'] . '» بخرقِ: ' . $x['forbidden_combo'] . ' فيُردّ',
            $dc, $role, isset($slots[$role]) ? $slots[$role] : 'P1', 1);
    }
}

/* ═══ ⑤ العرض ════════════════════════════════════════════════════════════ */
echo "\n═══ `RPR-03` §٦·٦ — تجهيزُ الرحلاتِ قبلَ الأشخاص ═══\n";
printf("  اللقطة: %s\n\n", $sid);
echo "  ── المجهَّزُ سلفًا — ⛔ ولا يُدهَس ──\n";
foreach ($have as $k => $n) { printf("     %-20s %d محطّة\n", $k, $n); }
echo "\n  ── ما يُجهَّز الآن ──\n";
$byJ = array();
foreach ($plan as $p) { $byJ[$p[0]]['n'] = (isset($byJ[$p[0]]['n']) ? $byJ[$p[0]]['n'] : 0) + 1;
    if ($p[6]) { $byJ[$p[0]]['neg'] = (isset($byJ[$p[0]]['neg']) ? $byJ[$p[0]]['neg'] : 0) + 1; }
    $byJ[$p[0]]['slots'][$p[5]] = 1; }
foreach ($JOURNEY as $jk => $J) {
    if (!isset($byJ[$jk])) { printf("     %-20s — (مجهَّزةٌ سلفًا)\n", $jk); continue; }
    printf("     %-20s %2d محطّة (سالبةٌ %d) · أشخاصٌ %d · %s · ربطُها: %s\n",
           $jk, $byJ[$jk]['n'], isset($byJ[$jk]['neg']) ? $byJ[$jk]['neg'] : 0,
           count($byJ[$jk]['slots']), $J[0], $J[4]);
}
printf("\n  ⇒ **رحلاتٌ مجهَّزةٌ جملةً: %d من ٦** · ومحطّاتٌ تُضاف **%d**\n",
       count($have) + count($byJ), count($plan));
echo "  ⛔ **ولا تُكتب حالةُ نجاحٍ ألبتّة** — كلُّها `PENDING`، والقاعدةُ تردُّ `PASSED`\n";
echo "     بلا فاعلٍ حقيقيٍّ وزمنٍ ودليلٍ واسم. **فالتجهيزُ يقرّب الساعةَ ولا يزعم اجتيازًا.**\n";

/* ═══ ⑥ التثبيت ══════════════════════════════════════════════════════════ */
if ($APPLY) {
    $n = 0;
    foreach ($plan as $p) {
        $sidn = 'W16-U-' . str_pad((string) (++$maxNo), 2, '0', STR_PAD_LEFT);
        $ok = $conn->query("INSERT INTO repair01_w16_uat
              (station_id,journey_key,station_no,station_ar,domain_code,required_role,
               person_slot,is_negative,status)
            VALUES ('" . $e($sidn) . "','" . $e($p[0]) . "'," . (int) $p[1] . ",'"
             . $e(mb_substr($p[2], 0, 255)) . "','" . $e($p[3]) . "','" . $e(mb_substr($p[4], 0, 120))
             . "','" . $e($p[5]) . "'," . (int) $p[6] . ",'PENDING')");
        if (!$ok) { exit("✘ تعذّر تجهيزُ {$p[0]}/{$p[1]}: {$conn->error}\n"); }
        $n++;
    }
    $tot  = (int) $conn->query("SELECT COUNT(*) FROM repair01_w16_uat")->fetch_row()[0];
    $pass = (int) $conn->query("SELECT COUNT(*) FROM repair01_w16_uat WHERE status = 'PASSED'")->fetch_row()[0];
    $jn   = (int) $conn->query("SELECT COUNT(DISTINCT journey_key) FROM repair01_w16_uat")->fetch_row()[0];
    printf("\n  ✔ جُهِّزت **%d** محطّةً · والمجموع **%d** على **%d** رحلات · **ومجتازٌ %d**\n",
           $n, $tot, $jn, $pass);
    echo "  ◆ **والصفرُ هنا صوابٌ** — لم يمرَّ إنسانٌ بعد، والقاعدةُ لا تقبل غيرَ ذلك.\n";
}

if ($MD) {
    $o  = "# `RPR-03` §٦·٦ — تجهيزُ الرحلاتِ قبلَ الأشخاص\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `" . $sid . "`\n\n";
    $o .= "## السيناريو لا يُؤلَّف — يُنتزع من مصدرَين مُعلَنَين\n\n";
    $o .= "- **`gov_screen_cycle`** تحمل لكلِّ إدارةٍ **مراحلَ مرتَّبةً** بأسمائها **وبالدورِ\n";
    $o .= "  المسؤولِ عن كلٍّ** ⇒ **تعريفُ رحلةٍ مُعلَنٌ سلفًا**، والمحطّاتُ ترتيبُها لا اختراعُها.\n";
    $o .= "- **`repair01_w{N}_sod`** تحمل **التركيبةَ الممنوعةَ** ⇒ **فالمحطّةُ السالبةُ منصوصة**.\n\n";
    $o .= "## الرحلاتُ الستّ\n\n| الرحلة | المفتاح | محطّات | سالبة | أشخاص | ربطُها بإدارتِها |\n";
    $o .= "|---|---|---:|---:|---:|---|\n";
    foreach ($have as $k => $c2) { $o .= '| — | `' . $k . '` | ' . $c2 . " | — | — | مجهَّزةٌ سلفًا |\n"; }
    foreach ($JOURNEY as $jk => $J) {
        if (!isset($byJ[$jk])) { continue; }
        $o .= '| ' . $J[0] . ' | `' . $jk . '` | ' . $byJ[$jk]['n'] . ' | '
            . (isset($byJ[$jk]['neg']) ? $byJ[$jk]['neg'] : 0) . ' | ' . count($byJ[$jk]['slots'])
            . ' | ' . $J[4] . " |\n";
    }
    $o .= "\n⚠ **وربطُ «الإيراد» بـ«المبيعات والعقود» ربطُ نطاقٍ لا ربطُ اسم** — دورةُ الإيرادِ\n";
    $o .= "تبدأ من عقدِ العميلِ وتنتهي بمطالبتِه. **ويُعلَن هنا كي يُراجَع**، والثلاثُ الباقياتُ\n";
    $o .= "بمطابقةِ اسمٍ حرفيّة.\n\n";
    $o .= "## ⛔ والتجهيزُ ليس اجتيازًا\n\n";
    $o .= "كلُّ محطّةٍ تولد `PENDING`. و`chk_w16_uat_real` **تردُّ في القاعدةِ** إعلانَ `PASSED`\n";
    $o .= "بلا **فاعلٍ حقيقيٍّ وزمنٍ ودليلٍ واسم**، و`chk_w16_uat_negative` تردُّ سالبًا ناجحًا\n";
    $o .= "بلا قيدٍ في سجلِّ المحاولات. ⇒ **فالتجهيزُ يقرّب الساعةَ ولا يزعم اجتيازًا**.\n\n";
    $o .= "## وخانةُ الشخصِ مقيسةٌ لا مختارة\n\n";
    $o .= "كلُّ **دورٍ متمايزٍ** في الرحلةِ يأخذ خانةً (`P1`, `P2`…) ⇒ **ففصلُ الواجباتِ مقيسٌ\n";
    $o .= "في التجهيزِ نفسِه** لا مؤجَّلٌ إلى التنفيذ (§٦·٦: «أشخاصٌ مختلفون لا أدوارٌ متعدّدةٌ\n";
    $o .= "في حسابٍ واحد»). ⛔ **ودورٌ واحدٌ لا يُنتج خانتَين** — والفاحصُ السالبُ يختبر ذلك.\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/RPR03_JOURNEY_PREPARE.md', $o);
    echo "\n✔ كُتب: docs/REPAIR01_20260823/RPR03_JOURNEY_PREPARE.md\n";
}
