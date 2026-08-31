<?php
/**
 * tools/navr_import_guide.php — استيرادُ مواضعِ الدليلِ المعماريِّ آليًّا (NAVR·المطلوب ٧)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ يبني من ورقةِ كلِّ إدارةٍ: ربطَ الدورِ (`nav_ws_roles`) · مجموعاتِ الدورةِ
 *   (`nav_lifecycle_groups`) · المواضعَ (`nav_placements`) — **بجسرِ الحملةِ
 *   الواحدِ** `tools/lib/navr_bridge.php` لا بإدخالٍ يدويٍّ ولا جسرٍ ثانٍ.
 *
 * ◆ **التصنيفُ قبل المقام** (المطلوب ١٠): كلُّ هدفٍ يُصنَّف
 *   MENU_ITEM/TAB_CHILD/PROJECTION/NOT_BUILT بقاعدةٍ مكتوبةٍ في source_ref —
 *   وغيرُ المبنيِّ موضعُه مستهدَفٌ مسجَّلٌ لا يُصيَّر (المطلوب ٨).
 *
 * ◆ **ولا يُداس حكمٌ لاحق** [[registry-rule-vs-value]]: الصفُّ الموجودُ لا
 *   يُحدَّث إلا إن كان مصدرُه استيرادَ دليلٍ سابقًا — والمعدَّلُ يدويًّا/بحكمٍ
 *   يُترك ويُعَدُّ (`kept_ruled`).
 *
 * التشغيل:
 *   php tools/navr_import_guide.php            ← قياسٌ فقط (dry-run)
 *   php tools/navr_import_guide.php --apply    ← يكتب
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/tools/lib/navr_bridge.php';
$APPLY = in_array('--apply', $argv, true);

ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$SRC = 'GUIDE-IMPORT·01 الدليل المعماري';
$spec = navr_guide_spec($ROOT);
$dep = navr_dep_roles($conn);
$bridge = navr_label_routes($conn);
$reqState = navr_req_state($conn);

/** قاعدةُ التصنيفِ المكتوبة (المطلوب ١٠) — تُطبع في source_ref بقاعدتِها. */
function navr_classify($guideType, $route, $screenId, $state)
{
    if ($screenId === null) {
        return array('NOT_BUILT', ($state === '' ? 'NO_REQ_ROW' : $state));
    }
    if (preg_match('~Child~i', $guideType)) { return array('TAB_CHILD', 'نوع الدليل: ' . $guideType); }
    if (strpos((string) $route, '?view=') !== false || strpos((string) $route, '#') !== false) {
        return array('PROJECTION', 'مسار متغاير ?view=/#');
    }
    return array('MENU_ITEM', 'الافتراض للشاشة المبنية المستقلة');
}

$tot = array('ws' => 0, 'roles' => 0, 'groups' => 0, 'pl_new' => 0, 'pl_upd' => 0,
             'kept_ruled' => 0, 'not_built' => 0, 'tab' => 0, 'proj' => 0, 'menu' => 0, 'unres_built' => 0);

echo "══ استيرادُ الدليلِ المعماريِّ إلى نموذجِ المواضع " . ($APPLY ? '(كتابة)' : '(قياسٌ فقط)') . " ══\n";

foreach ($spec as $code => $S) {
    /* المساحةُ يجب أن تكون مبذورةً بحكمِها — الهجرةُ لا الاستيراد */
    $q = $conn->query("SELECT workspace_id FROM nav_workspaces WHERE workspace_id = '"
        . $conn->real_escape_string($code) . "'");
    if (!$q || !$q->num_rows) { echo "  ⛔ {$code}: مساحةٌ غيرُ مبذورةٍ بحكمِها — تُتخطّى وتُقيَّد\n"; continue; }
    $tot['ws']++;

    /* ── ربطُ الدور (PRIMARY) — من الجسرِ الواحد ─────────────────────────── */
    if (isset($dep['roles'][$code])) {
        list($rid, $rname) = $dep['roles'][$code];
        /* WS-MY تمثيليٌّ في القياسِ فقط — لا ربطَ PRIMARY لمساحةٍ شخصيّة */
        if ($code !== 'WS-MY') {
            if ($APPLY) {
                $conn->query("INSERT IGNORE INTO nav_ws_roles (workspace_id, role_id, binding, source_ref)
                    VALUES ('" . $conn->real_escape_string($code) . "', " . (int) $rid . ", 'PRIMARY',
                            'navr_bridge·جسر إدارة⇒دور (" . $conn->real_escape_string($rname) . ")')");
            }
            $tot['roles']++;
        }
    } elseif ($APPLY) {
        $conn->query("INSERT INTO gov_nav_findings (kind, role_id, workspace_id, detail)
            VALUES ('NO_ROLE_BINDING', NULL, '" . $conn->real_escape_string($code) . "',
                    'مساحةٌ بورقةِ دليلٍ بلا دورٍ حيٍّ — فجوةُ دورٍ لا فجوةُ ملاحة (حكم NAVR_ROOT_AUDIT §⑤)')
            ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = NOW()");
    }

    /* ── مجموعاتُ الدورةِ بترتيبِ الورقة ─────────────────────────────────── */
    $gid = array();
    foreach ($S['groups'] as $i => $g) {
        $sort = $i + 1;
        /* اسمُ العرضِ خامُّ الورقةِ — والمفتاحُ مطبَّعٌ للمطابقة */
        $glabel = isset($S['group_labels'][$g]) ? $S['group_labels'][$g] : $g;
        if ($APPLY) {
            $conn->query("INSERT INTO nav_lifecycle_groups (workspace_id, group_key, label_ar, sort_no, source_ref)
                VALUES ('" . $conn->real_escape_string($code) . "', '" . $conn->real_escape_string($g) . "',
                        '" . $conn->real_escape_string($glabel) . "', {$sort}, '{$SRC}·ورقة {$code}')
                ON DUPLICATE KEY UPDATE sort_no = VALUES(sort_no), label_ar = VALUES(label_ar)");
        }
        $r = $conn->query("SELECT id FROM nav_lifecycle_groups WHERE workspace_id = '"
            . $conn->real_escape_string($code) . "' AND group_key = '" . $conn->real_escape_string($g) . "'");
        $gid[$g] = ($r && $r->num_rows) ? (int) $r->fetch_row()[0] : 0;
        $tot['groups']++;
    }

    /* ── المواضع — بترتيبِ الورقةِ داخل كلِّ مجموعة ──────────────────────── */
    $sortInGroup = array();
    foreach ($S['screens'] as $sc) {
        list($sid, $route, $how) = navr_resolve_screen($conn, $code, $sc['name'], $bridge);
        $state = isset($reqState[$sc['name']]) ? $reqState[$sc['name']] : '';
        if ($sid !== null && $state === 'NOT_IMPLEMENTED') {
            /* مبنيّةٌ بسجلِّها وهدفُها NOT_IMPLEMENTED — السجلُّ المبنيُّ يغلب */
            $state = '';
        }
        list($ptype, $why) = navr_classify($sc['type'], $route, $sid, $state);
        if ($sid === null && $state !== 'NOT_IMPLEMENTED' && $state !== '' && $state !== 'NO_REQ_ROW') {
            $tot['unres_built']++; /* مبنيٌّ بدفترِه ولم يُجسَر بالاسم — يُقاس ويُسمّى */
        }
        $tot[$ptype === 'MENU_ITEM' ? 'menu' : ($ptype === 'TAB_CHILD' ? 'tab'
            : ($ptype === 'PROJECTION' ? 'proj' : 'not_built'))]++;

        $g = $sc['group'];
        $sortInGroup[$g] = ($sortInGroup[$g] ?? 0) + 1;
        $tref = $code . '·' . $sc['i'] . '·' . mb_substr($sc['name'], 0, 120);
        /* §١٩: هويّةُ الهدفِ الثابتة — NT-<code>-<nnn> بترتيبِ الورقة */
        $tid = 'NT-' . $code . '-' . str_pad((string) $sc['i'], 3, '0', STR_PAD_LEFT);
        if (!$APPLY) { continue; }
        if (empty($gid[$g])) { continue; }
        $conn->query("INSERT INTO nav_targets
                (target_id, source_doc, sheet_code, row_no, canonical_title, workspace_id, group_key, target_order, visibility_class)
            VALUES ('" . $conn->real_escape_string($tid) . "', '01 · الدليل المعماري.xlsx',
                    '" . $conn->real_escape_string($code) . "', " . (int) $sc['i'] . ",
                    '" . $conn->real_escape_string(mb_substr($sc['raw'], 0, 180)) . "',
                    '" . $conn->real_escape_string($code) . "', '" . $conn->real_escape_string($g) . "',
                    " . (int) $sc['i'] . ", '" . $conn->real_escape_string($ptype) . "')
            ON DUPLICATE KEY UPDATE canonical_title = VALUES(canonical_title),
                group_key = VALUES(group_key), visibility_class = VALUES(visibility_class)");

        $esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
        $sidSql = $sid !== null ? "'" . $esc($sid) . "'" : 'NULL';
        $rtSql  = $route !== null ? "'" . $esc($route) . "'" : 'NULL';
        $srcRef = $SRC . '·' . $how . '·' . mb_substr($why, 0, 60);
        $ok = $conn->query("INSERT INTO nav_placements
                (workspace_id, screen_id, route, target_ref, target_id, group_id, sort_no, placement_type, source_ref)
            VALUES ('" . $esc($code) . "', {$sidSql}, {$rtSql}, '" . $esc($tref) . "', '" . $esc($tid) . "',
                    " . (int) $gid[$g] . ", " . (int) $sortInGroup[$g] . ",
                    '" . $esc($ptype) . "', '" . $esc($srcRef) . "')
            ON DUPLICATE KEY UPDATE
                target_id = VALUES(target_id),
                screen_id = IF(source_ref LIKE 'GUIDE-IMPORT%', VALUES(screen_id), screen_id),
                route = IF(source_ref LIKE 'GUIDE-IMPORT%', VALUES(route), route),
                group_id = IF(source_ref LIKE 'GUIDE-IMPORT%', VALUES(group_id), group_id),
                sort_no = IF(source_ref LIKE 'GUIDE-IMPORT%', VALUES(sort_no), sort_no),
                placement_type = IF(source_ref LIKE 'GUIDE-IMPORT%', VALUES(placement_type), placement_type),
                source_ref = IF(source_ref LIKE 'GUIDE-IMPORT%', VALUES(source_ref), source_ref)");
        if ($ok) {
            if ($conn->affected_rows === 1) { $tot['pl_new']++; }
            elseif ($conn->affected_rows === 2) { $tot['pl_upd']++; }
            else {
                $r = $conn->query("SELECT source_ref FROM nav_placements WHERE workspace_id='" . $esc($code)
                    . "' AND target_ref='" . $esc($tref) . "'");
                $x = $r ? $r->fetch_row() : null;
                if ($x && strpos((string) $x[0], 'GUIDE-IMPORT') !== 0) { $tot['kept_ruled']++; }
            }
        }
    }
}

echo "\n── الحصيلة ──\n";
printf("  مساحاتٌ بورقة: %d · روابطُ دورٍ PRIMARY: %d · مجموعاتُ دورة: %d\n",
    $tot['ws'], $tot['roles'], $tot['groups']);
printf("  مواضع: جديدٌ %d · مُحدَّثٌ %d · محفوظٌ بحكمٍ لاحقٍ %d\n",
    $tot['pl_new'], $tot['pl_upd'], $tot['kept_ruled']);
printf("  التصنيف: MENU_ITEM %d · TAB_CHILD %d · PROJECTION %d · NOT_BUILT %d\n",
    $tot['menu'], $tot['tab'], $tot['proj'], $tot['not_built']);
printf("  ◆ مبنيٌّ بدفترِه ولم يُجسَر بالاسم (يُسمّى لا يُبتلع): %d\n", $tot['unres_built']);
if (!$APPLY) { echo "\nقياسٌ فقط — أعد التشغيل بـ--apply للكتابة.\n"; }
