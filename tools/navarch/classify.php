<?php
/**
 * tools/navarch/classify.php — التصنيفُ الآليُّ قبلَ البشر (‏§18) وسجلَّاتُ §8·§12·§15
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **نصُّ §18**: «لا نرسل مئات البنود إلى المالك. يجب على النظام أولًا تصنيف
 *   ما يستطيع تصنيفه آليًا» — ثمَّ **غيرُ المحسومِ وحدَه** يصعد سلّمَ §34.
 *
 * ◆ **والمقامُ التصييرُ الحيُّ لا جدول** [[render-not-store-rule]]: يُقرأ من
 *   `NAV_ARCH_BASELINE.json` — **لقطةٌ واحدةٌ على التزامٍ واحد** (§5) — فلا
 *   يُخلط ظهورُ اليومِ بظهورِ الأمس.
 *
 * ◆ **وطبقةُ كلِّ ظهورٍ تُحسب بمنطقِ `tools/sidebar_layer_map.php` حرفًا**
 *   فتُطابَق مجاميعُه (342 · 36 · 88 · 323 · 400 من 1,189) — وإن اختلف رقمٌ
 *   **رسَب المصنِّف**: طبقةٌ تُحسب مرّتَين بحسابَين تكذب إحداهما.
 *
 * ◆ **ولكلِّ حكمٍ مصدرٌ مُسمًّى** — ⛔ ولا حكمَ من انطباع. مصادرُ الحكمِ السبعة:
 *   `nav_placements`+`nav_targets` (الدليل) · `nav_canonical.space_class`
 *   (تصنيفُ المساحةِ الحاكم) · `nav_redirects` (الاستبدال) ·
 *   `nav_canonical.retirement_status`/`merge_into`/`view_of` (التقاعدُ والدمج) ·
 *   `gov_legacy_nav_recon.verdict` (مصالحةُ الإرث) · `nav_dedup_verdicts`
 *   (التكرار) · `gov_nav_hidden_log.reachable` (بديلُ الوصولِ المُثبَت).
 *
 * ⛔ **والاستعمالُ غيرُ مقيسٍ في هذه الشجرة**: `nav_redirects.hits` صفرٌ كلُّها
 *   و`workspace_navigation_log` ليس مفتاحُه المسار. و§18 يشترط لـ`RETIRE_CANDIDATE`
 *   «بلا Usage ولا Dependency» — **فغيابُ القياسِ ليس غيابَ استعمال**، ولذلك
 *   `usage_count = -1` («لم يُقَس») و**لا يُحكَم بالتقاعدِ آليًّا أبدًا**؛ يهبط
 *   الحكمُ إلى `LEGACY_REQUIRES_DOMAIN_REVIEW` ويصعد لمالكِ المجال (§34 L2).
 *
 * التشغيل: php tools/navarch/classify.php [--ws=DEP-11] [--dry] [--explain]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__, 2));
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$onlyWs  = '';
$dry     = in_array('--dry', $argv, true);
$explain = in_array('--explain', $argv, true);
foreach ($argv as $a) { if (strpos($a, '--ws=') === 0) { $onlyWs = substr($a, 5); } }

$BLJSON = $ROOT . '/docs/REPAIR01_20260823/navarch/NAV_ARCH_BASELINE.json';
if (!is_file($BLJSON)) { fwrite(STDERR, "⛔ الأساسُ مفقود — شغِّل tools/navarch/baseline.php\n"); exit(1); }
$BL = json_decode(file_get_contents($BLJSON), true);
$BLID = $BL['baseline_id'];

$rt = function ($s) {
    $s = preg_replace('~^(\.\./)+~', '', (string) $s);
    $s = preg_replace('~[?#].*$~', '', $s);
    return strtolower(trim(preg_replace('~\.php$~i', '', $s), '/'));
};
$nz = function ($s) {
    $s = preg_replace('~[\x{064B}-\x{0652}\x{0640}]~u', '', (string) $s);
    $s = str_replace(array('أ','إ','آ','ى','ة','ؤ','ئ'), array('ا','ا','ا','ي','ه','و','ي'), $s);
    return trim(preg_replace('~\s+~u', ' ', $s));
};

/* ═══════════════════════════════════════════════════════════════════════════
   ① مادّةُ الحكمِ — سبعةُ مصادرَ مُسمّاة
   ═══════════════════════════════════════════════════════════════════════════ */

/* ①-أ مواضعُ الدليلِ: مساحة ⇒ مسار ⇒ [صنف, مجموعة, ترتيب] · ومالكُ كلِّ مسار */
$byWsRoute = array(); $byWsName = array(); $routeOwners = array();
$r = $conn->query("SELECT p.workspace_id, p.route, p.target_ref, p.sort_no, p.placement_type,
                          p.screen_id, g.label_ar, g.sort_no gno, g.id gid
                     FROM nav_placements p
                     LEFT JOIN nav_lifecycle_groups g ON g.id = p.group_id
                    WHERE p.active = 1");
while ($x = $r->fetch_assoc()) {
    $ws = (string) $x['workspace_id'];
    if ((string) $x['route'] !== '') {
        $k = $rt($x['route']);
        $byWsRoute[$ws][$k] = $x;
        $routeOwners[$k][$ws] = (string) $x['placement_type'];
    }
    $tr = (string) $x['target_ref'];
    if ($tr !== '' && preg_match('~·\s*(\d+)\s*·\s*(.+)$~u', $tr, $m)) {
        $byWsName[$ws][$nz($m[2])] = (int) $m[1];
    }
}

/* ①-ب هدفُ الدليلِ ونوعُ ظهورِه (`nav_targets`) */
$tgtByWsTitle = array(); $tgtTitleAnyWs = array();
$r = $conn->query("SELECT target_id, workspace_id, canonical_title, group_key, target_order,
                          visibility_class FROM nav_targets WHERE active = 1");
while ($x = $r->fetch_assoc()) {
    $tgtByWsTitle[$x['workspace_id']][$nz($x['canonical_title'])] = $x;
    $tgtTitleAnyWs[$nz($x['canonical_title'])][] = $x;
}

/* ①-ج تصنيفُ المساحةِ الحاكمُ + الاسمُ المعياريُّ + المالكُ + التقاعد */
$canon = array();
$r = $conn->query("SELECT route, canonical_ar, owner_dept, space_class, status, nature,
                          merge_into, view_of, retirement_status, screen_id
                     FROM nav_canonical");
while ($x = $r->fetch_assoc()) { $canon[$rt($x['route'])] = $x; }

/* ①-د الاستبدالُ المُثبَت */
$redir = array();
$r = $conn->query("SELECT old_route, new_route FROM nav_redirects WHERE active = 1");
while ($x = $r->fetch_assoc()) { $redir[$rt($x['old_route'])] = $rt($x['new_route']); }

/* ①-هـ مصالحةُ الإرثِ — حكمُ جولةٍ سابقةٍ بسندٍ مكتوب */
$recon = array();
$r = $conn->query("SELECT route, role_id, verdict, doc_code, basis FROM gov_legacy_nav_recon");
while ($x = $r->fetch_assoc()) { $recon[$rt($x['route'])][(int) $x['role_id']] = $x; }

/* ①-و التكرارُ المحكومُ زوجًا */
$dedup = array();
$r = $conn->query("SELECT route_a, route_b, verdict_class, verdict_text FROM nav_dedup_verdicts
                    WHERE verdict_class IN ('same_function','view_of_same_data')");
while ($x = $r->fetch_assoc()) {
    $a = $rt($x['route_a']); $b = $rt($x['route_b']);
    $dedup[$a] = array('other' => $b, 'cls' => $x['verdict_class'], 'txt' => $x['verdict_text']);
    $dedup[$b] = array('other' => $a, 'cls' => $x['verdict_class'], 'txt' => $x['verdict_text']);
}

/* ①-ز بديلُ الوصولِ المُثبَتُ في إخفاءٍ سابق */
$hidden = array();
$r = $conn->query("SELECT route, reachable, doc_code FROM gov_nav_hidden_log");
while ($x = $r->fetch_assoc()) { $hidden[$rt($x['route'])] = $x; }

/* المساحاتُ ونوعُها */
$wsType = array(); $wsName = array();
$r = $conn->query("SELECT workspace_id, workspace_type, name_ar FROM nav_workspaces");
while ($x = $r->fetch_assoc()) {
    $wsType[$x['workspace_id']] = (string) $x['workspace_type'];
    $wsName[$x['workspace_id']] = (string) $x['name_ar'];
}

/* ①-ح **مجموعةُ دورةِ العملِ لموضعٍ لا صفَّ له في ورقةِ الدليل** (§8 · §21-④)
   ───────────────────────────────────────────────────────────────────────────
   ◆ **المقيسُ قبلَ الحكم**: `group_id` كان يُؤخذ من صفِّ `nav_placements` في
     المساحةِ نفسِها وحدَه، **فواحدٌ وثلاثون موضعًا صالحًا للسايدبار خرج بلا
     مجموعة** — ومُصيِّرُ §23-① يحجب الموضعَ بلا رأسِ طيٍّ (‏ولا مجموعةَ
     افتراضيّة). ⇒ **شاشاتٌ مستعمَلةٌ تختفي بلا حكمٍ مكتوب** وهو ما يمنعه §4.
   ◆ **والمجموعةُ تُقرأ من مصدرٍ حاكمٍ ثانٍ لا تُخترَع**: `gov_target_nav` —
     جدولُ الإدارةِ المستهدَفُ المنشورُ بسندٍ (`REPAIR01-OPS-11` وأخواتِه) —
     يحمل `group_ar` لكلِّ مسار. فإن طابقت مجموعتُه **رأسَ طيٍّ في دورةِ هذه
     المساحةِ نفسِها** أُخذت، وإلّا **فلا تُؤخذ**.
   ⛔ **ولا تُقبل مجموعةُ مساحةٍ أخرى**: «دورة الترحيل» رأسُ طيٍّ في `DEP-15`،
     وحملُه إلى سايدبارِ `DEP-11` يجعل دورةَ إدارةٍ بندًا في دورةِ أخرى — وهو
     نصُّ ما يمنعه §13 (`Consumer reads; Owner writes`).
   ◆ **والمطابقةُ بالاسمِ المُسوّى**: `nav_lifecycle_groups.group_key` هو
     `group_ar` مُسوّى (‏الهمزةُ والتاءُ المربوطة) — والمقارنةُ بـ`$nz` نفسِها. */
$wsPrimaryRole = array();
$r = $conn->query("SELECT workspace_id, role_id FROM nav_ws_roles WHERE binding = 'PRIMARY'");
while ($x = $r->fetch_assoc()) { $wsPrimaryRole[$x['workspace_id']] = (int) $x['role_id']; }

$lcGroupByKey = array();          /* ws ⇒ اسمٌ مُسوًّى ⇒ id */
$r = $conn->query("SELECT id, workspace_id, group_key, label_ar FROM nav_lifecycle_groups WHERE active = 1");
while ($x = $r->fetch_assoc()) {
    $lcGroupByKey[$x['workspace_id']][$nz($x['group_key'])] = (int) $x['id'];
    $lcGroupByKey[$x['workspace_id']][$nz($x['label_ar'])]  = (int) $x['id'];
}

$declGroup = array();             /* role ⇒ مسارٌ مُسوًّى ⇒ اسمُ المجموعةِ المُعلَن */
$r = $conn->query("SELECT role_id, route, group_ar FROM gov_target_nav
                    WHERE route IS NOT NULL AND route <> '' AND group_ar IS NOT NULL");
while ($x = $r->fetch_assoc()) {
    if (strncmp((string) $x['route'], 'GAP:', 4) === 0) { continue; }   /* منصوصٌ بلا شاشةٍ حيّة */
    $k = $rt($x['route']);
    if ($k !== '' && !isset($declGroup[(int) $x['role_id']][$k])) {
        $declGroup[(int) $x['role_id']][$k] = (string) $x['group_ar'];
    }
}

/** مجموعةُ دورةِ هذه المساحةِ لهذا المسار — أو `null` إن لم يحكمها مصدر. */
$gidFor = function ($ws, $route) use ($byWsRoute, $wsPrimaryRole, $declGroup, $lcGroupByKey, $nz) {
    /* ① ورقةُ الدليلِ أوّلًا — الصفُّ المبنيُّ في `nav_placements` لهذه المساحة */
    if (isset($byWsRoute[$ws][$route]) && $byWsRoute[$ws][$route]['gid'] !== null) {
        return (int) $byWsRoute[$ws][$route]['gid'];
    }
    /* ②-أ **والشخصيُّ مجموعتُه من ورقةِ «مساحة عملي»** (§11): مواضعُ `WS-MY`
       تُصيَّر طبقةً فوقَ كلِّ مساحةٍ، ورؤوسُها الفرعيّةُ («الملف الشخصي» ·
       «العمل اليومي») **مجموعاتُ `WS-MY` نفسِها** لا مجموعاتُ الإدارةِ المضيفة.
       ⛔ **وبدونِها يُسطَّح الشخصيُّ كتلةً واحدةً** فيُقرأ المسارُ الواحدُ تحتَ
       رأسَين مختلفَين بين دورٍ مقلوبٍ وآخرَ لم يُقلَب — وهو ما ترصده بوّابةُ
       `U3` حرفًا، وتتجاوز الكتلةُ حدَّ العشرةِ في `U9`. */
    if (isset($byWsRoute['WS-MY'][$route]) && $byWsRoute['WS-MY'][$route]['gid'] !== null) {
        return (int) $byWsRoute['WS-MY'][$route]['gid'];
    }
    /* ② ثمَّ الجدولُ المستهدَفُ المنشورُ — **بشرطِ أن تكون المجموعةُ من هذه المساحة** */
    $rid = isset($wsPrimaryRole[$ws]) ? $wsPrimaryRole[$ws] : 0;
    if ($rid && isset($declGroup[$rid][$route])) {
        $key = $nz($declGroup[$rid][$route]);
        if (isset($lcGroupByKey[$ws][$key])) { return (int) $lcGroupByKey[$ws][$key]; }
    }
    return null;
};

$ANCHOR = array('main/role_board' => 1, 'chats/index' => 1);

/* ═══════════════════════════════════════════════════════════════════════════
   ② الطبقةُ — بمنطقِ sidebar_layer_map.php حرفًا (‏فتُطابَق مجاميعُه)
   ═══════════════════════════════════════════════════════════════════════════ */
$routeAnyWs = array();
foreach ($routeOwners as $k => $wss) { $routeAnyWs[$k] = array_keys($wss)[0]; }

$layerOf = function ($ws, $route, $label) use (&$byWsRoute, &$byWsName, $ANCHOR, &$routeAnyWs, $nz) {
    if (isset($byWsRoute[$ws][$route]) || isset($byWsName[$ws][$nz($label)])) { return 'GUIDE'; }
    if (isset($ANCHOR[$route]))                                               { return 'ANCHOR'; }
    if (isset($byWsRoute['WS-MY'][$route]))                                   { return 'PERSONAL'; }
    if (isset($routeAnyWs[$route]))                                           { return 'SHARED'; }
    return 'LEGACY';
};

/* ═══════════════════════════════════════════════════════════════════════════
   ③ الحكمُ الآليّ — ثمانيةُ أحكامِ §18 بمصدرٍ مُسمًّى لكلٍّ
   ═══════════════════════════════════════════════════════════════════════════
   يُرجِع: placement_type(§9) · action(§19) · disposition(§16|'') · reason_code
         · evidence · governing_source · access_path · need_case(§12|'')
         · level(§34) · rendered(‏أيبقى في سايدبارِ هذه المساحة؟)                */
function navarch_rule($ws, $it, $layer, $ctx)
{
    extract($ctx, EXTR_SKIP);          /* المصادرُ السبعةُ + wsType + الدوالّ */
    $route = $it['route']; $label = $it['label'];
    $c  = isset($canon[$route]) ? $canon[$route] : null;
    $sc = $c ? trim((string) $c['space_class']) : '';
    $wt = isset($wsType[$ws]) ? $wsType[$ws] : 'DEPARTMENT';
    $V  = function ($pt, $ac, $dp, $rc, $ev, $gs, $ap, $nc, $lv, $rn) {
        return array('placement_type' => $pt, 'action' => $ac, 'disposition' => $dp,
                     'reason_code' => $rc, 'evidence' => $ev, 'governing_source' => $gs,
                     'access_path' => $ap, 'need_case' => $nc, 'level' => $lv, 'rendered' => $rn);
    };

    /* ── R1 · مرساةُ المنصّة (§10) ───────────────────────────────────────── */
    if ($layer === 'ANCHOR') {
        return $V('GLOBAL_SHELL', 'MOVE_TO_GLOBAL_SHELL', '', 'PLATFORM_ANCHOR_S10',
            'قِيست مرساةً في 18 مساحةٍ من 18 · SIDEBAR_LAYER_MAP ورقة 01',
            'NAV-ARCH-02 §10 — الرئيسيةُ والمراسلاتُ قشرةُ تطبيقٍ لا دورةُ إدارة',
            'القشرةُ العامّةُ — ظاهرةٌ في كلِّ مساحةٍ خارجَ مقامِ الدورة', '', 'L1_ARCHITECTURE', true);
    }

    /* ── R2 · الدليلُ — الشاشةُ جزءٌ أصيلٌ من دورةِ هذه المساحة (§9 PRIMARY) ──
       ⛔ **وهذا الحكمُ يسبق `space_class` قصدًا**: §14 و§17 يوجبان الاتجاهَ
          `Governing Source → Target → Implementation`. فورقةُ الإدارةِ في
          الدليلِ **مصدرٌ حاكم**، و`nav_canonical.space_class` تصنيفُ تشغيلٍ
          مشتقّ. ولو قُدِّم الثاني لَنُزِعت شاشةُ دورةٍ من ورقتِها لأنَّ تصنيفًا
          تشغيليًّا سمّاها «شخصيّة» — **وهو تعديلُ الهدفِ من الواقعِ بعينِه**.
          (‏قيست في أوّلِ صيغةٍ: 214 موضعًا `PERSONAL` مقابلَ 88 في الطبقة.) */
    if ($layer === 'GUIDE') {
        $p  = isset($byWsRoute[$ws][$route]) ? $byWsRoute[$ws][$route] : null;
        $pt = $p ? (string) $p['placement_type'] : '';
        $tg = isset($tgtByWsTitle[$ws][$nz($label)]) ? $tgtByWsTitle[$ws][$nz($label)] : null;
        $vc = $tg ? (string) $tg['visibility_class'] : '';
        if ($pt === 'TAB_CHILD' || $vc === 'TAB_CHILD') {
            return $V('TAB_CHILD', 'MOVE_TO_PARENT', '', 'TAB_CHILD_S18',
                'صنفُ الموضعِ/الهدفِ TAB_CHILD · ' . ($p ? 'nav_placements' : 'nav_targets'),
                'NAV-ARCH-02 §9 — تبويبٌ داخلَ أبٍ لا بندُ قائمةٍ مستقلّ',
                'من داخلِ الشاشةِ الأمِّ تبويبًا', '', 'L1_ARCHITECTURE', false);
        }
        if ($pt === 'DIRECT_ONLY') {
            return $V('DIRECT_ONLY', 'CONTEXTUALIZE', '', 'DIRECT_ONLY_S9',
                'nav_placements.placement_type = DIRECT_ONLY',
                'NAV-ARCH-02 §9 — يفتح بالرابطِ والبحثِ والمهمّة',
                'الرابطُ المباشرُ والبحثُ والمهمّة', '', 'L1_ARCHITECTURE', false);
        }
        if ($pt === 'PROJECTION') {
            return $V('EXECUTIVE_PROJECTION', 'KEEP_SECONDARY', '', 'EXEC_PROJECTION_S9',
                'nav_placements.placement_type = PROJECTION',
                'NAV-ARCH-02 §9 — عرضُ قيادةٍ لا شاشةَ مصدرِ حقيقة',
                'إسقاطُ قراءةٍ في مساحةِ القيادة', '', 'L1_ARCHITECTURE', true);
        }
        return $V('PRIMARY', 'KEEP_PRIMARY', '', 'GUIDE_OWNED_LIFECYCLE_S9',
            'ورقةُ ' . $ws . ' في الدليلِ المعماريّ · nav_placements@' . $ws,
            'NAV-ARCH-02 §9 — الشاشةُ جزءٌ أصيلٌ من دورةِ المساحةِ المالكة',
            'سايدبارُ المساحةِ نفسِها', '', 'L1_ARCHITECTURE', true);
    }

    /* ── R3 · الشخصيُّ ⇒ WS-MY (§11 · §18) — **بعدَ الدليلِ لا قبلَه** ─────── */
    if ($layer === 'PERSONAL' || $sc === 'PERSONAL_SPACE') {
        return $V('PERSONAL', 'MOVE_TO_WS_MY', ($layer === 'LEGACY' ? 'PERSONAL' : ''),
            'PERSONAL_WS_MY_S11',
            $layer === 'PERSONAL' ? 'له موضعٌ في ورقةِ WS-MY · nav_placements@WS-MY'
                                  : 'nav_canonical.space_class = PERSONAL_SPACE',
            'NAV-ARCH-02 §11 — مساحةُ عملي، ولا تدخل مقامَ دورةِ الإدارة',
            'مساحةُ عملي WS-MY — تظهر لكلِّ دورٍ بحكمٍ قائم', '', 'L1_ARCHITECTURE', true);
    }

    /* ── R4 · العابرُ للإدارات (§12) — نوعُ الاحتياجِ لا نقلُ الشاشة ───────── */
    if ($layer === 'SHARED') {
        $own = isset($routeAnyWs[$route]) ? $routeAnyWs[$route] : '';
        $ownName = isset($wsName[$own]) ? $wsName[$own] : $own;
        $base = 'مملوكةٌ لـ' . $own . ' (' . $ownName . ') · nav_placements@' . $own;

        if ($sc === 'FORBIDDEN') {
            return $V('', '', '', 'CROSS_WORKSPACE_PERMISSION_ONLY',
                $base . ' · nav_canonical.space_class = FORBIDDEN',
                'NAV-ARCH-02 §18 — موضعٌ أصيلٌ في إدارةٍ أخرى وظهورُه هنا بالصلاحيّةِ فقط',
                'مبدِّلُ المساحاتِ إلى ' . $own . ' · البحثُ · الرابطُ المباشرُ يبقى نافذًا',
                'D_WORKSPACE_SWITCH', 'L1_ARCHITECTURE', false);
        }
        if ($sc === 'CONTEXTUAL_READ_ONLY') {
            return $V('', '', '', 'CROSS_DOMAIN_READ_ONLY_S12A',
                $base . ' · nav_canonical.space_class = CONTEXTUAL_READ_ONLY',
                'NAV-ARCH-02 §12-أ — يحتاج المعلومةَ فقط: إسقاطُ قراءةٍ في سياقِه لا نقلُ الشاشة',
                'إسقاطُ قراءةٍ داخلَ سياقِ المستخدم · ومبدِّلُ المساحاتِ للشاشةِ الكاملة',
                'A_PROJECTION', 'L1_ARCHITECTURE', false);
        }
        if ($sc === 'SHARED_WORK_ITEM') {
            /* §12-هـ: استثناءٌ محكومٌ — سببٌ ومصدرٌ ونطاقٌ وتاريخٌ **واعتماد**.
               والاعتمادُ هنا قرارُ حوكمةٍ مسجَّلٌ في nav_canonical لا استنتاجٌ من دور. */
            /* ══ **والاعتمادُ وحدَه لا يضع الشاشةَ في دورةِ إدارةٍ ليست دورتَها** ══
               ◆ **المقيسُ**: خمسةَ عشرَ بندَ عملٍ مشتركٍ («طلب الترحيل» · «طلبات
                 الشراء» · «صندوق موافقاتي») كُتبت `SECONDARY_APPROVED` في مساحاتٍ
                 مستهلِكةٍ **لا رأسَ طيٍّ لها فيها**: مجموعتُها المُعلَنةُ («دورة
                 الترحيل») رأسُ طيٍّ في مساحةِ **مالكِها**. فبقيت مواضعَ نشطةً
                 بلا مجموعةٍ **يحجبها المُصيِّرُ صامتًا** (§23-①).
               ◆ **و§12-ب يسمّي علاجَها حرفًا**: «يحتاج أن يطلب إجراء من الإدارة
                 الأخرى ⇒ يستخدم `Request / Handoff`… **ولا تنقل له شاشات الإدارة
                 المستلمة**» — وأمثلتُه نصًّا: **طلب شراء · طلب ترحيل**.
               ⇒ فمن **لا رأسَ طيٍّ له في دورةِ هذه المساحةِ** يُحكَم
                 `CONTEXTUAL_ACTION` (§9 · §12-ج): **لا يدخل السايدبار**،
                 ⛔ **وبديلُ وصولِه مكتوبٌ لا مسكوتٌ عنه** (§4) — والصلاحيّةُ
                 والمسارُ المباشرُ باقيانِ حرفًا (§22 · §42). */
            $hasGrp = isset($gidFor) && $gidFor($ws, $route) !== null;
            $ok = $c && (string) $c['status'] === 'APPROVED';
            if ($ok && !$hasGrp) {
                return $V('CONTEXTUAL_ACTION', 'CONTEXTUALIZE', '', 'REQUEST_HANDOFF_S12B',
                    $base . ' · space_class = SHARED_WORK_ITEM · ولا رأسَ طيٍّ لها في دورةِ '
                          . $ws . ' — مجموعتُها المُعلَنةُ من دورةِ مالكِها',
                    'NAV-ARCH-02 §12-ب — «طلب شراء · طلب ترحيل» تُطلب بـRequest/Handoff '
                        . 'ولا تُنقل شاشةُ الإدارةِ المستلمةِ إلى سايدبارِ الطالب',
                    'فعلٌ سياقيٌّ من سجلِّ الطلبِ نفسِه · ومبدِّلُ المساحاتِ إلى ' . $own
                        . ' · والبحثُ · **والرابطُ المباشرُ يبقى نافذًا والصلاحيّةُ لم تُمَسّ (§22·§42)**',
                    'B_REQUEST_HANDOFF', 'L1_ARCHITECTURE', false);
            }
            return $V($ok ? 'SECONDARY_APPROVED' : '', $ok ? 'KEEP_SECONDARY' : 'ESCALATE', '',
                $ok ? 'SECONDARY_APPROVED_S12E' : 'UNAPPROVED_SECONDARY_NEEDS_DECISION',
                $base . ' · space_class = SHARED_WORK_ITEM · nav_canonical.status = '
                     . ($c ? $c['status'] : '—'),
                $ok ? 'NAV-ARCH-02 §12-هـ — بندُ عملٍ مشتركٌ باعتمادٍ مسجَّلٍ في nav_canonical'
                    : 'NAV-ARCH-02 §12-هـ — ثانويٌّ بلا اعتمادٍ مسجَّل ⇒ يصعد لمالكِ المجال',
                'صندوقُ العملِ المشتركُ في هذه المساحة', 'E_SECONDARY_APPROVED',
                $ok ? 'L1_ARCHITECTURE' : 'L2_DOMAIN_OWNER', $ok);
        }
        if ($sc === 'CONTROL_OVERSIGHT') {
            $isOv = in_array($wt, array('EXECUTIVE', 'INDEPENDENT_ASSURANCE'), true) || $ws === 'DEP-08';
            return $V($isOv ? 'SECONDARY_APPROVED' : '', $isOv ? 'KEEP_SECONDARY' : '',
                '', $isOv ? 'CONTROL_OVERSIGHT_S12E' : 'CROSS_WORKSPACE_PERMISSION_ONLY',
                $base . ' · space_class = CONTROL_OVERSIGHT · نوعُ المساحة ' . $wt,
                $isOv ? 'NAV-ARCH-02 §12-هـ — رقابةٌ: المساحةُ رقابيّةٌ فالظهورُ وظيفتُها'
                      : 'NAV-ARCH-02 §18 — رقابةٌ في مساحةٍ غيرِ رقابيّة ⇒ ظهورٌ بالصلاحيّةِ فقط',
                $isOv ? 'سايدبارُ المساحةِ الرقابيّة'
                      : 'مبدِّلُ المساحاتِ إلى ' . $own . ' · الرابطُ المباشرُ يبقى نافذًا',
                $isOv ? 'E_SECONDARY_APPROVED' : 'D_WORKSPACE_SWITCH', 'L1_ARCHITECTURE', $isOv);
        }
        if ($sc === 'EXECUTIVE_OVERSIGHT') {
            $isEx = ($wt === 'EXECUTIVE');
            return $V($isEx ? 'EXECUTIVE_PROJECTION' : '', $isEx ? 'KEEP_SECONDARY' : '',
                '', $isEx ? 'EXEC_PROJECTION_S9' : 'CROSS_WORKSPACE_PERMISSION_ONLY',
                $base . ' · space_class = EXECUTIVE_OVERSIGHT · نوعُ المساحة ' . $wt,
                $isEx ? 'NAV-ARCH-02 §9 — إسقاطُ قيادةٍ لا شاشةَ مصدرِ حقيقة'
                      : 'NAV-ARCH-02 §18 — إسقاطُ قيادةٍ في مساحةٍ غيرِ قياديّة',
                $isEx ? 'مساحةُ القيادة' : 'مبدِّلُ المساحاتِ إلى ' . $own,
                $isEx ? 'A_PROJECTION' : 'D_WORKSPACE_SWITCH', 'L1_ARCHITECTURE', $isEx);
        }
        /* الحكمُ الافتراضيُّ لـ§18 حرفًا: موضعٌ أصيلٌ هناك وظهورٌ هنا بالصلاحيّةِ فقط */
        return $V('', '', '', 'CROSS_WORKSPACE_PERMISSION_ONLY',
            $base . ($sc === '' ? ' · بلا `space_class` مسجَّل' : ' · space_class = ' . $sc),
            'NAV-ARCH-02 §18 — «إذا كان Screen له Primary Placement في إدارة أخرى '
                . 'والظهور هنا سببه Permission فقط ⇒ لا يظهر في Workspace الحالية»',
            'مبدِّلُ المساحاتِ إلى ' . $own . ' · البحثُ · الرابطُ المباشرُ يبقى نافذًا (§22)',
            'D_WORKSPACE_SWITCH', 'L1_ARCHITECTURE', false);
    }

    /* ── R5 · الإرث (§14 · §16 · §18) ────────────────────────────────────── */
    if (isset($redir[$route])) {
        return $V('', 'REDIRECT', 'REPLACED', 'REPLACE_AND_REDIRECT_S18',
            'nav_redirects: ' . $route . ' ⇒ ' . $redir[$route],
            'NAV-ARCH-02 §18 — له بديلٌ معياريٌّ مسجَّل',
            'المسارُ الجديد: ' . $redir[$route] . ' (‏والقديمُ يحوَّل إليه — §33-ب)',
            '', 'L1_ARCHITECTURE', false);
    }
    if ($c && (string) $c['retirement_status'] === 'MERGE_THEN_REDIRECT') {
        return $V('', 'REDIRECT', 'DUPLICATE', 'MERGE_THEN_REDIRECT_S16',
            'nav_canonical.retirement_status = MERGE_THEN_REDIRECT'
                . ($c['merge_into'] ? ' ⇐ ' . $c['merge_into'] : ''),
            'NAV-ARCH-02 §16 — وظيفةٌ مكرَّرةٌ محكومةٌ بالدمجِ ثمَّ التحويل',
            'الشاشةُ المدموجُ فيها: ' . (string) $c['merge_into'], '', 'L1_ARCHITECTURE', false);
    }
    if ($c && (string) $c['retirement_status'] === 'RETIRE_AFTER_PROOF') {
        return $V('', 'RETIRE', 'OBSOLETE', 'RETIRE_AFTER_PROOF_S33',
            'nav_canonical.retirement_status = RETIRE_AFTER_PROOF',
            'NAV-ARCH-02 §33 — تقاعدٌ بعدَ دليلٍ، والمراحلُ خمسٌ لا قفزة',
            'لا بديلَ مطلوبٌ — والتقاعدُ لا يبدأ قبلَ فحصِ التبعيّاتِ الستِّ (§33)',
            '', 'L2_DOMAIN_OWNER', false);
    }
    if ($c && (string) $c['view_of'] !== '' && $c['view_of'] !== null) {
        return $V('', 'REDIRECT', 'DUPLICATE', 'VIEW_OF_S16',
            'nav_canonical.view_of = ' . $c['view_of'],
            'NAV-ARCH-02 §16 — منظرٌ لبياناتِ شاشةٍ أخرى لا شاشةٌ مستقلّة',
            'الشاشةُ الأصل: ' . (string) $c['view_of'], '', 'L1_ARCHITECTURE', false);
    }
    if (isset($dedup[$route])) {
        return $V('', 'REDIRECT', 'DUPLICATE', 'DEDUP_VERDICT_S16',
            'nav_dedup_verdicts: ' . $dedup[$route]['cls'] . ' مع ' . $dedup[$route]['other'],
            'NAV-ARCH-02 §16 — حكمُ تكرارٍ مسجَّلٌ بزوجِه',
            'الشاشةُ المقابلة: ' . $dedup[$route]['other'], '', 'L1_ARCHITECTURE', false);
    }
    /* تبويبٌ تابعٌ محكومٌ في مكانٍ آخر */
    if (isset($hidden[$route]) && (string) $hidden[$route]['reachable'] === 'TAB_IN_PARENT') {
        return $V('TAB_CHILD', 'MOVE_TO_PARENT', 'TAB_CHILD', 'TAB_CHILD_S18',
            'gov_nav_hidden_log.reachable = TAB_IN_PARENT · ' . $hidden[$route]['doc_code'],
            'NAV-ARCH-02 §18 — تبويب/سجلٌّ تابعٌ ⇒ إلى أبيه',
            'تبويبٌ داخلَ الشاشةِ الأمّ', '', 'L1_ARCHITECTURE', false);
    }
    /* حكمُ مصالحةٍ سابقٌ بسندٍ مكتوب */
    $rc = null;
    if (isset($recon[$route])) { $rc = array_values($recon[$route])[0]; }
    if ($rc) {
        $v = (string) $rc['verdict'];
        if ($v === 'VALID_UTILITY') {
            return $V('UTILITY', 'MOVE_TO_GLOBAL_SHELL', 'UTILITY', 'PLATFORM_UTILITY_S18',
                'gov_legacy_nav_recon.verdict = VALID_UTILITY · ' . $rc['doc_code'],
                'NAV-ARCH-02 §18 — أداةُ منصّةٍ لا شاشةَ أعمال',
                'القشرةُ العامّةُ — خارجَ مقامِ دورةِ الإدارة', '', 'L1_ARCHITECTURE', true);
        }
        if ($v === 'SUPERSEDED') {
            return $V('', 'REPLACE', 'REPLACED', 'SUPERSEDED_S16',
                'gov_legacy_nav_recon.verdict = SUPERSEDED · ' . $rc['doc_code'],
                'NAV-ARCH-02 §16 — استُبدل بشاشةٍ جديدة',
                'الشاشةُ البديلةُ في ورقةِ الإدارة', '', 'L1_ARCHITECTURE', false);
        }
        if ($v === 'MATCHES_GOVERNING_TARGET' || $v === 'APPROVED_POST_GUIDE_ADDITION') {
            /* ══ **وصندوقُ العملِ المشتركِ ليس بندَ دورةِ إدارة** (§11) ═══════════
               ◆ **المقيس**: «صندوق موافقاتي وما ينتظر يدي» (`Approvals/requests`)
                 صُولح هدفًا في **أربعِ مساحاتٍ**، فكُتب `PRIMARY` في كلٍّ —
                 **ولا رأسَ طيٍّ له في دورةِ أيٍّ منها**، فبقي موضعًا نشطًا
                 يحجبه المُصيِّرُ صامتًا.
               ◆ **و§11 يسمّي محلَّه**: صناديقُ «ما ينتظرني» **مساحةُ عملي**
                 لا دورةُ الإدارة — وهو ما يفعله المُصيِّرُ القديمُ أصلًا
                 (‏القسمُ المفروضُ «ما ينتظرني» داخلَ «مساحتي»).
               ⇒ فما كان `SHARED_WORK_ITEM` **ولا مجموعةَ له في هذه الدورة**
                 يُكتب `PERSONAL`: **يظهر في مِسمارِه ولا يُحتسَب إدارةً**
                 (‏وهو ما يقيسه `NT-06`). ⛔ **ولا يختفي**: مساحةُ عملي مطبوعةٌ
                 في سايدبارِ كلِّ مساحةٍ مقلوبة. */
            if ($sc === 'SHARED_WORK_ITEM' && isset($gidFor) && $gidFor($ws, $route) === null) {
                return $V('PERSONAL', 'MOVE_TO_WS_MY', '', 'SHARED_WORK_ITEM_TO_WS_MY_S11',
                    'gov_legacy_nav_recon.verdict = ' . $v . ' · ' . $rc['doc_code']
                        . ' · space_class = SHARED_WORK_ITEM · ولا رأسَ طيٍّ له في دورةِ ' . $ws,
                    'NAV-ARCH-02 §11 — صناديقُ «ما ينتظرني» في «مساحة عملي» لا في دورةِ الإدارة',
                    'مجموعةُ «مساحتي» في سايدبارِ هذه المساحةِ نفسِها — ظاهرٌ لا مخفيّ',
                    '', 'L1_ARCHITECTURE', true);
            }
            return $V('PRIMARY', 'KEEP_PRIMARY', 'CANONICAL_EQUIVALENT',
                'RECONCILED_TO_TARGET_S16',
                'gov_legacy_nav_recon.verdict = ' . $v . ' · ' . $rc['doc_code'] . ' · ' . $rc['basis'],
                'NAV-ARCH-02 §16 — له هدفٌ مطابقٌ أو إضافةٌ معتمَدةٌ بسندٍ مكتوبٍ سابقٍ لهذه الجولة',
                'سايدبارُ هذه المساحة', '', 'L1_ARCHITECTURE', true);
        }
    }
    /* مملوكةٌ لإدارةٍ أخرى بشهادةِ الاسمِ المعياريِّ لا بموضعٍ مبنيّ */
    if ($c && (string) $c['owner_dept'] !== '' && $c['owner_dept'] !== null
        && $nz($c['owner_dept']) !== $nz(isset($wsName[$ws]) ? $wsName[$ws] : '')) {
        return $V('', 'CONTEXTUALIZE', 'CROSS_DOMAIN', 'LEGACY_CROSS_DOMAIN_S16',
            'nav_canonical.owner_dept = ' . $c['owner_dept'] . ' · وهذه ' . $wsName[$ws],
            'NAV-ARCH-02 §13 — Consumer reads; Owner writes',
            'مبدِّلُ المساحاتِ إلى الإدارةِ المالكة · والرابطُ المباشرُ يبقى نافذًا',
            '', 'L1_ARCHITECTURE', false);
    }
    /* ⛔ ولا `RETIRE_CANDIDATE` آليًّا: الاستعمالُ **لم يُقَس** فغيابُه ليس دليلًا */
    /* ◆ **وحكمُ الموضعِ محجوبٌ لا محذوف** (§35: `Block the affected Placement, not
     *   the program`): لا يُكتب له موضعٌ حاكمٌ فلا يُصيَّر (§22)، **والصلاحيّةُ
     *   تبقى صحيحةً** فالرابطُ المباشرُ والبحثُ ومبدِّلُ المساحاتِ تعمل (§3·§22).
     *   ⛔ **ولا يُتقاعَد**: التقاعدُ يلزمه قياسُ استعمالٍ وفحصُ تبعيّاتٍ (§32·§33).
     *   وهذا هو **الوحيدُ** الذي يصعد سلّمَ §34 إلى مالكِ المجال. */
    return $V('', 'ESCALATE', 'UNKNOWN_REQUIRES_DECISION', 'LEGACY_REQUIRES_DOMAIN_REVIEW',
        'لا استبدالَ ولا حكمَ مصالحةٍ ولا مالكَ مسجَّل · والاستعمالُ غيرُ مقيسٍ في هذه الشجرة',
        'NAV-ARCH-02 §18 — «Legacy بلا Target وله Usage ⇒ LEGACY_REQUIRES_DOMAIN_REVIEW»؛ '
            . 'وغيابُ قياسِ الاستعمالِ يمنع `RETIRE_CANDIDATE` (§32) · والحجبُ بـ§35',
        'الرابطُ المباشرُ (‏الصلاحيّةُ باقيةٌ — §22) · البحثُ العامّ · مبدِّلُ المساحات '
            . '— **حتى يحكم مالكُ المجال (§34 L2)، ولا تقاعدَ قبلَ فحصِ التبعيّاتِ الستّ**',
        '', 'L2_DOMAIN_OWNER', false);
}

/* ═══════════════════════════════════════════════════════════════════════════
   ④ المرور
   ═══════════════════════════════════════════════════════════════════════════ */
$ctx = compact('canon', 'redir', 'recon', 'dedup', 'hidden', 'byWsRoute', 'byWsName',
               'tgtByWsTitle', 'routeAnyWs', 'wsType', 'wsName', 'nz', 'gidFor');

$rows = array(); $layerCnt = array(); $reasonCnt = array(); $ptCnt = array();
foreach ($BL['snapshot'] as $ws => $s) {
    if ($s['rendered'] === null) { continue; }
    if ($onlyWs !== '' && $ws !== $onlyWs) { continue; }
    foreach ($s['items'] as $it) {
        $L = $layerOf($ws, $it['route'], $it['label']);
        $v = navarch_rule($ws, $it, $L, $ctx);
        $layerCnt[$L] = (isset($layerCnt[$L]) ? $layerCnt[$L] : 0) + 1;
        $reasonCnt[$v['reason_code']] = (isset($reasonCnt[$v['reason_code']]) ? $reasonCnt[$v['reason_code']] : 0) + 1;
        $key = $v['placement_type'] !== '' ? $v['placement_type'] : '(‏لا موضعَ في هذه المساحة)';
        $ptCnt[$key] = (isset($ptCnt[$key]) ? $ptCnt[$key] : 0) + 1;
        $rows[] = array('ws' => $ws, 'n' => $it['n'], 'group' => $it['group'],
                        'label' => $it['label'], 'route' => $it['route'], 'layer' => $L) + $v;
    }
}

/* ═══ ⑤ حارسُ الثبات: مجاميعُ الطبقاتِ يجب أن تطابقَ **القارئَ الثانيَ الحيَّ** ══
   ◆ **غرضُ الحارسِ**: طبقةٌ تُحسب بحسابَين — هذا الملفُ و`tools/sidebar_layer_map.php`
     — **يكذب أحدُهما إن اختلفا** [[counter-parity-two-readers]].
   ◆ **⛔ وليس غرضُه تجميدَ رقم**: كانت التوقُّعاتُ مصفوفةً حرفيّةً منقولةً من §1
     (‏342/36/88/323/400 = 1,189) — وهي مجاميعُ **لقطةٍ بعينِها**. فلمّا بُنيت
     شاشاتُ `GOV_UI_FINISH` الثلاثُ صار الحيُّ 1,192 **فرسَب الحارسُ على بناءٍ
     صحيح** — سقّاطةٌ ترسُب على التحسُّن [[ratchet-guards-progress-not]] وحاجبٌ
     بثابتٍ رقميٍّ يجمّد [[repair01-w04-field]].
   ⇒ **فالتوقُّعُ يُقرأ من مخرَجِ القارئِ الثاني نفسِه على الأساسِ نفسِه**؛ فإن
     غاب الملفُ أو اختلف أساسُه **لا يُحكَم بثباتٍ ولا بحركة** — يُعلَن أنّه
     غيرُ مقيس (§5 حرفًا: «لا تستخدم قياسات مولدة من Commits مختلفة»). */
$LTFILE = $ROOT . '/docs/REPAIR01_20260823/navarch/SIDEBAR_LAYER_TOTALS.json';
$EXPECT = null; $expectNote = '';
if (is_file($LTFILE)) {
    $lt = json_decode(file_get_contents($LTFILE), true);
    if (is_array($lt) && isset($lt['layers'])) {
        if ((string) $lt['baseline_id'] === (string) $BLID) { $EXPECT = $lt['layers']; }
        else { $expectNote = 'أساسُ القارئِ الثاني `' . $lt['baseline_id'] . '` لا يطابق `' . $BLID . '`'; }
    }
} else {
    $expectNote = 'مخرَجُ القارئِ الثاني غائبٌ — شغّل tools/sidebar_layer_map.php';
}
$stable = null;
if ($onlyWs === '' && $EXPECT !== null) {
    $stable = true;
    foreach ($EXPECT as $k => $n) {
        $got = isset($layerCnt[$k]) ? $layerCnt[$k] : 0;
        if ($got !== (int) $n) { $stable = false; }
    }
}

echo "══ NAV-ARCH-02 §18 — التصنيفُ الآليُّ · الأساس {$BLID} ══\n";
printf("  ظهوراتٌ مصنَّفة: **%d**%s\n", count($rows), $onlyWs !== '' ? " (‏{$onlyWs} وحدَها)" : '');
echo "  الطبقات: ";
foreach (array('GUIDE', 'ANCHOR', 'PERSONAL', 'SHARED', 'LEGACY') as $k) {
    printf("%s=%d%s ", $k, isset($layerCnt[$k]) ? $layerCnt[$k] : 0,
        ($EXPECT !== null ? '/' . (int) $EXPECT[$k] : ''));
}
echo "\n";
if ($onlyWs === '') {
    if ($stable === null) { echo "  ◆ **ثباتُ القياسِ غيرُ مقيسٍ** — {$expectNote}
"; }
    else {
        echo $stable ? "  ✔ **ثباتُ القياسِ مؤكَّد** — الطبقاتُ الخمسُ تطابق SIDEBAR_LAYER_MAP حرفًا\n"
                     : "  ✘ **القياسُ تحرّك** — طبقةٌ لا تطابق الخريطةَ المقيسة\n";
    }
}

echo "\n  ── أصنافُ الموضعِ (§9) ──\n";
arsort($ptCnt);
foreach ($ptCnt as $k => $v) { printf("     %-34s %4d\n", $k, $v); }
echo "\n  ── رموزُ السببِ (§18) ──\n";
arsort($reasonCnt);
foreach ($reasonCnt as $k => $v) { printf("     %-42s %4d\n", $k, $v); }

/* ═══ فائضُ الظهورِ: مسارٌ واحدٌ مرَّتَين في سايدبارِ مساحةٍ واحدة ══════════
   ◆ **ولا يُبتلَع بالمفتاحِ الفريد**: السجلُّ الحاكمُ مفتاحُه `(مساحة, مسار)`
     فينطوي المكرَّرُ صامتًا — والانطواءُ الصامتُ هو العطبُ نفسُه الذي تحاربه
     الجولة. فيُعَدُّ هنا ويُسمَّى: **هذا `UNEXPLAINED_EXTRA` مقيسٌ لا مستنتَج.** */
$dupSeen = array(); $dupExtra = array();
foreach ($rows as $x) {
    $k = $x['ws'] . '|' . $x['route'];
    if (isset($dupSeen[$k])) { $dupExtra[$k][] = $x; } else { $dupSeen[$k] = $x; }
}
if ($dupExtra) {
    $dn = 0; foreach ($dupExtra as $g) { $dn += count($g); }
    printf("
  ── فائضُ ظهورٍ: مسارٌ مكرَّرٌ في المساحةِ نفسِها — **%d ظهورًا زائدًا** في %d مسارًا ──
",
        $dn, count($dupExtra));
    foreach ($dupExtra as $k => $g) {
        list($w, $rr) = explode('|', $k, 2);
        printf("     %-8s %-44s ×%d · %s
", $w, mb_substr($rr, 0, 42), count($g) + 1,
            mb_substr($dupSeen[$k]['label'], 0, 26) . ' | ' . mb_substr($g[0]['label'], 0, 26));
    }
}

$esc = 0; foreach ($rows as $x) { if ($x['action'] === 'ESCALATE') { $esc++; } }
printf("\n  ◆ محسومٌ آليًّا: **%d** · يصعد سلّمَ §34: **%d** (%.1f%%)\n",
    count($rows) - $esc, $esc, count($rows) ? $esc * 100 / count($rows) : 0);

if ($explain) {
    echo "\n  ── تفصيلُ كلِّ ظهورٍ ──\n";
    foreach ($rows as $x) {
        printf("  %-8s %3d %-38s %-46s %-14s %-34s %s\n", $x['ws'], $x['n'],
            mb_substr($x['label'], 0, 36), mb_substr($x['route'], 0, 44), $x['layer'],
            $x['reason_code'], $x['rendered'] ? 'يظهر' : 'لا يظهر');
    }
}

/* ═══ ⑥ الكتابةُ في السجلَّاتِ الثلاثة ═══════════════════════════════════════ */
if ($dry) { echo "\n  ◆ `--dry` — لم يُكتب صفّ\n"; exit($stable ? 0 : 1); }

$now = date('Y-m-d H:i:s');
$conn->query("DELETE FROM nav_workspace_placements" . ($onlyWs !== '' ? " WHERE workspace_id='" . $conn->real_escape_string($onlyWs) . "'" : ''));
$conn->query("DELETE FROM nav_cross_domain_register" . ($onlyWs !== '' ? " WHERE consumer_workspace='" . $conn->real_escape_string($onlyWs) . "'" : ''));
$conn->query("DELETE FROM nav_legacy_disposition" . ($onlyWs !== '' ? " WHERE current_workspace='" . $conn->real_escape_string($onlyWs) . "'" : ''));

$stP = $conn->prepare("INSERT INTO nav_workspace_placements
    (placement_id, screen_id, workspace_id, group_id, placement_type, sort_no, route,
     canonical_label, governing_source, source_ref, reason_code, effective_from, status,
     version, created_by, approved_by, legacy_ref, created_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,?,?,?)");
$stC = $conn->prepare("INSERT INTO nav_cross_domain_register
    (cd_id, screen_id, route, current_label, consumer_workspace, owner_workspace, need_case,
     remedy, access_path, scope, governing_source, approved_by, decided_level, created_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
$stL = $conn->prepare("INSERT INTO nav_legacy_disposition
    (legacy_item_id, screen_id, current_workspace, current_label, current_route, usage_count,
     target_match, replacement_screen_id, disposition, action, reason, domain_owner,
     decision_ref, evidence, access_replacement, decided_level, retire_stage, created_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

$nP = $nC = $nL = 0;
$errP = $errC = $errL = array();
foreach ($rows as $x) {
    $sid = isset($canon[$x['route']]) ? (string) $canon[$x['route']]['screen_id'] : '';
    $sid = ($sid === '' ? null : $sid);
    $pid = 'WP-' . strtoupper(substr(sha1($x['ws'] . '|' . $x['route']), 0, 16));

    if ($x['placement_type'] !== '') {
        $p   = isset($byWsRoute[$x['ws']][$x['route']]) ? $byWsRoute[$x['ws']][$x['route']] : null;
        /* المجموعةُ من **ورقةِ الدليلِ ثمَّ الجدولِ المستهدَفِ المنشور** — ولا
           تُؤخذ مجموعةُ مساحةٍ أخرى ولا تُصطنع افتراضيّةٌ (انظر `$gidFor`). */
        $gid = $gidFor($x['ws'], $x['route']);
        $ap  = ($x['placement_type'] === 'SECONDARY_APPROVED') ? 'NAV-ARCH-02 §12-هـ · nav_canonical APPROVED' : null;
        $st  = ($x['action'] === 'ESCALATE') ? 'BLOCKED' : 'ACTIVE';
        $cb  = 'tools/navarch/classify.php@' . $BLID;
        $ef  = date('Y-m-d');
        $lr  = $p ? (int) $p['gid'] : null;
        /* سبعةَ عشرَ مربطًا — والعددُ يُعَدُّ من `VALUES` لا من الذاكرة */
        $stP->bind_param('sssisisssssssssis', $pid, $sid, $x['ws'], $gid, $x['placement_type'],
            $x['n'], $x['route'], $x['label'], $x['governing_source'], $x['evidence'],
            $x['reason_code'], $ef, $st, $cb, $ap, $lr, $now);
        if ($stP->execute()) { $nP++; } else { $errP[$stP->errno] = $stP->error; }
    }

    if ($x['layer'] === 'SHARED') {
        $own = isset($routeAnyWs[$x['route']]) ? $routeAnyWs[$x['route']] : '';
        $cid = 'CD-' . strtoupper(substr(sha1($x['ws'] . '|' . $x['route']), 0, 16));
        $nc  = $x['need_case'] !== '' ? $x['need_case'] : 'D_WORKSPACE_SWITCH';
        $apv = ($x['placement_type'] === 'SECONDARY_APPROVED') ? 'nav_canonical:APPROVED' : null;
        $sco = null;
        $stC->bind_param('ssssssssssssss', $cid, $sid, $x['route'], $x['label'], $x['ws'], $own,
            $nc, $x['governing_source'], $x['access_path'], $sco, $x['evidence'], $apv,
            $x['level'], $now);
        if ($stC->execute()) { $nC++; } else { $errC[$stC->errno] = $stC->error; }
    }

    if ($x['layer'] === 'LEGACY') {
        $lid  = 'LG-' . strtoupper(substr(sha1($x['ws'] . '|' . $x['route']), 0, 16));
        $disp = $x['disposition'] !== '' ? $x['disposition'] : 'UNKNOWN_REQUIRES_DECISION';
        $act  = $x['action'] !== '' ? $x['action'] : 'ESCALATE';
        $usage = -1;                      /* ⛔ «لم يُقَس» لا «صفرُ استعمال» */
        $tm   = isset($redir[$x['route']]) ? $redir[$x['route']] : null;
        $rep  = null;
        $dom  = isset($canon[$x['route']]) ? (string) $canon[$x['route']]['owner_dept'] : null;
        $dref = 'NAV-ARCH-02 §18 · ' . $BLID;
        $rst  = ($act === 'RETIRE') ? 'A_COEXIST' : 'NONE';
        $stL->bind_param('ssssssssssssssssss', $lid, $sid, $x['ws'], $x['label'], $x['route'],
            $usage, $tm, $rep, $disp, $act, $x['governing_source'], $dom, $dref, $x['evidence'],
            $x['access_path'], $x['level'], $rst, $now);
        if ($stL->execute()) { $nL++; } else { $errL[$stL->errno] = $stL->error; }
    }
}
printf("\n  ⇒ كُتب: مواضعُ حاكمة **%d** · عابرٌ للإدارات **%d** · حكمُ إرثٍ **%d**\n", $nP, $nC, $nL);
exit($stable ? 0 : 1);
