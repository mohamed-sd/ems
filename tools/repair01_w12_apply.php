<?php
/**
 * tools/repair01_w12_apply.php — قياسٌ وكتابةٌ للمرحلةِ الثانيةَ عشرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **السايدبارُ قبل الشاشات** (§٤ · RPR-PATCH-01 ③): الخطواتُ السبعُ بترتيبها
 *   على أسطحِ النطاقِ — وأسطحُ النموِّ تُسجَّل أوّلًا لأنّها جزءٌ من مقامِه.
 *
 * ⛔ `origin` = `W12` بالضبط (RPR-PATCH-02): أساسُ السجلِّ (٦٥١) مُجمَّدٌ،
 *   والنموُّ مسموحٌ **مختومًا وحدَه**.
 *
 * ◆ **والترتيبُ من دورةِ العملِ لا من الأبجديّة** (§٤-٤): `sort_no` يُشتَقُّ من
 *   `step` — موضعِ السطحِ من دورةِ التمويل — لا من اسمِ الملفِّ ولا من تاريخِ
 *   الإنشاء. و⛔ **لا مصفوفةَ بنودٍ مكتوبةٌ في صفحة**.
 *
 * ◆ **والفجوةُ اسمٌ مستهدَفٌ لا اسمُ ملفٍّ مُلزِم** (‏سابقتا `W9-D-08` و
 *   `W11-D-11`): سبعُ فجواتٍ لـ`DEP-03` **مبنيّةٌ فعلًا بأسماءٍ أخرى** —
 *   فتُقيَّد موفّاةً في `built_counterpart` ⛔ **ولا يُمَسُّ صفُّ الشبحِ ولا
 *   `on_disk` ولا سجلُّ مرحلةٍ مُغلقة**، فمقاما الأشباحِ والأساسِ لا يتحرّكان.
 *
 * التشغيل: php tools/repair01_w12_apply.php [--report] [--revert]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w12_scan.php';
require_once $ROOT . '/app/Services/Ui/UiLabelRegistry.php';
require_once $ROOT . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("تعذّر الاتصال بالقاعدة\n"); }
$conn->set_charset('utf8mb4');
while (ob_get_level()) { ob_end_clean(); }

$REPORT = in_array('--report', $argv, true);
$REVERT = in_array('--revert', $argv, true);
$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) { return repair01_w12_one($conn, $sql); };
$W = function ($sql) use ($conn, $REPORT) {
    if ($REPORT) { return true; }
    if ($conn->query($sql) === true) { return true; }
    echo '  ✘ ' . $conn->error . "\n  ⇐ " . mb_substr(preg_replace('/\s+/', ' ', $sql), 0, 180) . "\n";
    return false;
};

echo "══ REPAIR01 · W12 — " . ($REVERT ? 'إرجاع' : ($REPORT ? 'قياسٌ بلا كتابة' : 'قياسٌ وكتابة')) . " ══\n\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ⓪ الإرجاع — يُفرِّغ ما كتبته هذه الأداةُ وحدَها
   ═══════════════════════════════════════════════════════════════════════════ */
if ($REVERT) {
    foreach (repair01_w12_new_surfaces() as $s) {
        $rt = $esc($s['route']);
        $conn->query("DELETE FROM nav_items WHERE route = '$rt'");
        $conn->query("DELETE FROM nav_canonical WHERE route = '$rt'");
        $conn->query("DELETE FROM role_permissions WHERE module_id IN (SELECT id FROM modules WHERE code = '$rt')");
        $conn->query("DELETE FROM modules WHERE code = '$rt'");
        $conn->query("DELETE FROM repair01_screen_registry WHERE route = '$rt' AND origin = 'W12'");
        $conn->query("DELETE FROM gov_screen_cycle WHERE screen_file = '"
                     . $esc(basename($s['route'])) . "' AND inputs_note LIKE 'RPR-W12 %'");
        $conn->query("DELETE FROM gov_space_appearances WHERE route = '$rt' AND src_class = 'RPR-W12'");
    }
    /* **البندُ المنقولُ يعود إلى موضعِه قبلَ حذفِ مجموعتِه** — وإلّا بقي يشير
       إلى صفٍّ لا وجودَ له، فيقرأ المُصيِّرُ مجموعةً فارغةً ويسقط `U5`. */
    $back = 0;
    $rb = $conn->query("SELECT nav_item_id, from_group_id FROM repair01_w12_nav_moves");
    while ($rb && $bx = $rb->fetch_assoc()) {
        if ($conn->query("UPDATE nav_items SET group_id = " . (int) $bx['from_group_id']
                         . " WHERE id = " . (int) $bx['nav_item_id'])) { $back++; }
    }
    echo "  ✔ بنودٌ أُعيدت إلى موضعِها الأصليّ $back\n";
    $conn->query("DELETE FROM repair01_w12_nav_moves");
    $conn->query("DELETE FROM link_groups WHERE group_code LIKE 'n9o_w12\\_%'");
    $orphan = (int) repair01_w12_one($conn, "SELECT COUNT(*) FROM nav_items n
                                              LEFT JOIN link_groups g ON g.id = n.group_id
                                             WHERE n.group_id > 0 AND g.id IS NULL");
    echo '  ' . ($orphan === 0 ? '✔' : '✘') . " بندٌ يتيمٌ بعد الإرجاع $orphan\n";
    $conn->query("UPDATE repair01_target_gaps SET built_counterpart = ''
                   WHERE unit = 'DEP-03' AND wave_stage = 'W12'");
    foreach (array('repair01_w12_scope', 'repair01_w12_sidebar', 'repair01_w12_decisions',
                   'repair01_w12_states', 'repair01_w12_sod', 'repair01_w12_thresholds',
                   'repair01_w12_fixes', 'repair01_w12_journey', 'repair01_w12_layers') as $t) {
        $conn->query("DELETE FROM `$t`");
    }
    $conn->query("DELETE FROM fin_close_consumption WHERE src_ref LIKE 'RPR-W12%'");
    /* ⛔ **ما سجّلته W03 لا يُمَسّ** — والحذفُ بوسمِ هذه الموجةِ وحدَه */
    $conn->query("DELETE FROM repair01_key_alias WHERE wave_stage = 'W12'");
    $conn->query("DELETE FROM repair01_events WHERE wave = 'W12'");
    $conn->query("DELETE FROM repair01_w6_code_dict WHERE src_ref LIKE 'RPR-W12%'");
    echo "الحكم: رجعت ✔ (والجداولُ تُنزع بهجرةِ التراجع)\n";
    exit(0);
}

/* ═══════════════════════════════════════════════════════════════════════════
   ① قاموسُ الرموزِ — الرمزُ يبقى لاتينيًّا ويُعرَض عربيًّا
   ═══════════════════════════════════════════════════════════════════════════
   ◆ **يُبذَر قبل تسجيلِ الأسطح**: السطحُ يعرض حالتَه من القاموسِ لحظةَ فتحِه،
     ورمزٌ بلا مسمًّى يُعرَض خامًّا فيسقط معيارُ نقاءِ اللغة.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "① قاموسُ رموزِ النطاق ─────────────────────────────────────────\n";
$conn->query("DELETE FROM repair01_w6_code_dict WHERE src_ref LIKE 'RPR-W12%'");
$DICT = array(
    /* أصنافُ الإقفالِ الثلاثةُ — ولكلٍّ اسمُه فلا يُقرأ أحدُها مكانَ الآخر */
    array('CONTRACTUAL', 'اقفال تعاقدي', 'W12_CLOSE_KIND'),
    array('MONTHLY', 'اقفال شهري', 'W12_CLOSE_KIND'),
    array('FINAL', 'اقفال نهائي', 'W12_CLOSE_KIND'),
    /* طبقتا الدفع */
    array('FUTURE', 'نموذج امر الدفع', 'W12_LAYER'),
    array('LEGACY', 'طبقة تاريخية مجمعة', 'W12_LAYER'),
    /* حالاتٌ عامّةٌ في النطاق */
    array('prepared', 'معد', 'W12_STATE'), array('superseded', 'مستبدل', 'W12_STATE'),
    array('requested', 'مطلوب', 'W12_STATE'), array('negotiating', 'قيد التفاوض', 'W12_STATE'),
    array('shortlisted', 'ضمن القائمة القصيرة', 'W12_STATE'),
    array('declined', 'مستبعد', 'W12_STATE'), array('received', 'وارد', 'W12_STATE'),
    array('sourced', 'وجد له مصدر', 'W12_STATE'), array('submitted', 'مرفوع', 'W12_STATE'),
    array('cleared', 'مجاز', 'W12_STATE'), array('blocked', 'محجوب', 'W12_STATE'),
    array('signed', 'موقع', 'W12_STATE'), array('breached', 'مخل به', 'W12_STATE'),
    array('waived', 'متنازل عنه', 'W12_STATE'), array('active', 'ساري', 'W12_STATE'),
    array('verified', 'محقق', 'W12_STATE'), array('defaulted', 'متعثر', 'W12_STATE'),
    array('negotiation', 'تفاوض', 'W12_STATE'), array('paying', 'قيد السداد', 'W12_STATE'),
    array('scheduled', 'مجدول', 'W12_STATE'), array('due', 'مستحق', 'W12_STATE'),
    array('overdue', 'متأخر', 'W12_STATE'), array('rescheduled', 'معاد جدولته', 'W12_STATE'),
    array('matched', 'مطابق', 'W12_STATE'), array('unmatched', 'غير مطابق', 'W12_STATE'),
    array('disputed', 'محل نزاع', 'W12_STATE'), array('legacy', 'تاريخي', 'W12_STATE'),
    array('derived', 'مشتق', 'W12_STATE'),
    /* حجّيّةُ الصفِّ التاريخيّ */
    array('documented', 'حجية مستندية', 'W12_EVIDENCE'),
    array('aggregate', 'حجية تجميعية', 'W12_EVIDENCE'),
    array('asserted', 'حجية مدعاة', 'W12_EVIDENCE'),
    /* مكوّناتُ التخصيص */
    array('principal', 'أصل', 'W12_PART'), array('profit', 'عائد', 'W12_PART'),
    array('fees', 'رسوم', 'W12_PART'),
    /* أغراضُ التمويلِ وطرقُ السداد */
    array('equipment', 'معدات', 'W12_PURPOSE'), array('operational', 'تشغيلي', 'W12_PURPOSE'),
    array('supplier', 'مورد', 'W12_PURPOSE'), array('general', 'عام', 'W12_PURPOSE'),
    array('cheque', 'شيك', 'W12_METHOD'),
    /* أطرافُ الالتزامِ ودوريّتُه */
    array('us', 'علينا', 'W12_PARTY'), array('financier', 'على الممول', 'W12_PARTY'),
    array('both', 'على الطرفين', 'W12_PARTY'),
    array('monthly', 'شهري', 'W12_FREQ'), array('quarterly', 'ربع سنوي', 'W12_FREQ'),
    array('annual', 'سنوي', 'W12_FREQ'), array('event', 'عند الواقعة', 'W12_FREQ'),
    /* أنواعُ وثائقِ التأهيل */
    array('license', 'ترخيص', 'W12_DOC'), array('registry', 'سجل تجاري', 'W12_DOC'),
    array('tax', 'شهادة ضريبية', 'W12_DOC'), array('kyc', 'اعرف عميلك', 'W12_DOC'),
    array('rating', 'تصنيف ائتماني', 'W12_DOC'),
    /* انحرافاتُ التمويل */
    array('no_ledger', 'عقد بلا حركة', 'W12_DEV'), array('payment_gap', 'فرق سداد', 'W12_DEV'),
    array('unrecorded_exit', 'خروج غير مسجل', 'W12_DEV'),
);
$dictN = 0;
foreach ($DICT as $d) {
    if ($W("INSERT INTO repair01_w6_code_dict
            (raw_code, display_ar, display_short, code_family, allowed_context, why, src_ref)
            VALUES ('" . $esc($d[0]) . "','" . $esc($d[1]) . "','" . $esc($d[1]) . "',
                    '" . $esc($d[2]) . "','SCREEN_CELL',
                    'قيمة تقارن في الشيفرة وفي CHECK فتبقى لاتينية وتعرض عربية من القاموس',
                    'RPR-W12 §١')
            ON DUPLICATE KEY UPDATE display_ar = VALUES(display_ar)")) { $dictN++; }
}
printf("  رموزٌ مسجَّلةٌ للعرض %d\n\n", $dictN);

/* ═══════════════════════════════════════════════════════════════════════════
   ② أسطحُ النموِّ — ثمانيةَ عشرَ سطحًا مختومةً بموجتِها
   ═══════════════════════════════════════════════════════════════════════════ */
echo "② أسطحُ النموِّ — مختومةٌ بـW12 ─────────────────────────────────\n";
$newN = 0; $navN = 0; $permN = 0; $labelN = 0; $missing = array();
$maxSid = (int) preg_replace('/\D/', '', (string) $one("SELECT screen_id FROM repair01_screen_registry
                                                          ORDER BY screen_id DESC LIMIT 1"));
foreach (repair01_w12_new_surfaces() as $s) {
    $rt = $esc($s['route']); $file = basename($s['route']);
    if (!is_file($ROOT . '/' . $s['route'])) { $missing[] = $s['route']; continue; }

    /* ⓐ الموديول — مرجعُ الصلاحيةِ والاسم */
    $modId = (int) $one("SELECT id FROM modules WHERE code = '$rt' LIMIT 1");
    if ($modId === 0) {
        $ownerRole = (int) $one("SELECT owner_role_id FROM modules WHERE code = '" . $esc($s['sibling']) . "' LIMIT 1");
        $W("INSERT INTO modules (name, code, owner_role_id, is_link, icon, display_order, owner_dept_note)
            VALUES ('" . $esc($s['ar']) . "','$rt'," . ($ownerRole > 0 ? $ownerRole : 'NULL') . ",'0',
                    '" . $esc($s['icon']) . "'," . (int) $s['sort'] . ",'" . $esc($s['owner']) . "')");
        $modId = (int) $one("SELECT id FROM modules WHERE code = '$rt' LIMIT 1");
    }

    /* ⓑ المنحُ — لكلِّ دورٍ يرى الشقيقَ اليوم؛ فالبلوغُ يُقاس ولا يُخترع */
    if ($modId > 0) {
        $sibMod = (int) $one("SELECT id FROM modules WHERE code = '" . $esc($s['sibling']) . "' LIMIT 1");
        if ($sibMod > 0) {
            $W("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                SELECT rp.role_id, $modId, 1, 0, 0, 0
                  FROM role_permissions rp WHERE rp.module_id = $sibMod AND rp.can_view = 1
                ON DUPLICATE KEY UPDATE can_view = 1");
            $permN += (int) $one("SELECT COUNT(*) FROM role_permissions WHERE module_id = $modId");
        }
    }

    /* ⓒ **المسمّى يُسجَّل قبل أن يُصيَّر** (‏W06) — واسمٌ مشكولٌ أو تقنيٌّ يُردّ */
    if (!$REPORT) {
        $lr = \App\Services\Ui\UiLabelRegistry::register($conn, 'screen:' . strtolower($s['route']), $s['ar'], array(
            'allowed_context' => 'SIDEBAR SCREEN_TITLE',
            'source_table' => 'nav_canonical', 'source_column' => 'canonical_ar',
            'source_key' => $s['route'], 'owner_code' => $s['owner'],
            'visibility_class' => 'USER_VISIBLE', 'label_state' => 'ACTIVE',
            'rule_id' => 'W12_NEW_SURFACE_LABEL', 'origin' => 'W12',
            'src_ref' => 'RPR-W12 §٤ · سطحُ نموٍّ مختوم', 'caller' => 'repair01_w12_apply.php',
        ));
        if (!$lr['ok']) { echo '  ⚠ رُدَّ مسمّى ' . $s['route'] . ' — ' . $lr['code'] . ': ' . $lr['detail'] . "\n"; }
        else { $labelN++; }
        $gr = \App\Services\Ui\UiLabelRegistry::register($conn, 'group:w12:' . strtolower($s['group']),
            repair01_w12_group_ar($s['group']), array(
            'allowed_context' => 'SIDEBAR', 'source_table' => 'nav_canonical', 'source_column' => 'group_name',
            'source_key' => $s['group'], 'owner_code' => $s['owner'],
            'visibility_class' => 'USER_VISIBLE', 'label_state' => 'ACTIVE',
            'rule_id' => 'W12_CYCLE_GROUP_LABEL', 'origin' => 'W12',
            'src_ref' => 'RPR-W12 §٤ · مجموعةُ دورةِ العمل', 'caller' => 'repair01_w12_apply.php',
        ));
        if ($gr['ok']) { $labelN++; }
    }

    /* ⓓ السجلُّ المعياريُّ للتنقُّل — والترتيبُ من موضعِ السطحِ في الدورة */
    $sid = (string) $one("SELECT screen_id FROM repair01_screen_registry WHERE route = '$rt' LIMIT 1");
    if ($sid === '') { $maxSid++; $sid = 'SCR-' . str_pad((string) $maxSid, 4, '0', STR_PAD_LEFT); }
    $W("INSERT INTO nav_canonical (route, canonical_ar, level_no, level_name, group_name, sort_no,
                                   status, decision_state, application_state, decision_source,
                                   derivation, retirement_status, screen_id)
        VALUES ('$rt','" . $esc($s['ar']) . "',2,'العمليات','" . $esc(repair01_w12_group_ar($s['group'])) . "',"
                . (int) $s['sort'] . ",
                'APPROVED','APPROVED','DEPLOYED','RPR-W12 · التمويل والممولون (2026-08-26)',
                'ترتيب دورة التمويل في الحزمة','ACTIVE','" . $esc($sid) . "')
        ON DUPLICATE KEY UPDATE canonical_ar=VALUES(canonical_ar), group_name=VALUES(group_name),
          sort_no=VALUES(sort_no), status=VALUES(status), screen_id=VALUES(screen_id)");

    /* ⓔ **مجموعةُ الدورةِ لا مجموعةُ الشقيق** — والمُعرِّفُ من `screen_id` لا
         من المسار: `link_groups.group_code` عرضُه أربعون محرفًا، والاشتقاقُ من
         المسارِ يبتُر ويتصادم (‏عطبُ W07 المقيس). */
    if ($modId > 0) {
        $gkey = 'n9o_w12_' . strtolower(str_replace('-', '', $sid));
        $sib = $conn->query("SELECT n.role_id, n.door, g.stage_no, g.stage_title, g.display_order
                               FROM nav_items n
                               LEFT JOIN link_groups g ON g.id = n.group_id
                              WHERE n.route = '" . $esc($s['sibling']) . "' AND n.active = 1
                              GROUP BY n.role_id, n.door, g.stage_no, g.stage_title, g.display_order");
        while ($sib && $sx = $sib->fetch_assoc()) {
            $rid  = (int) $sx['role_id'];
            $code = $gkey . '_r' . $rid;
            $gid  = (int) $one("SELECT id FROM link_groups WHERE group_code = '" . $esc($code) . "' LIMIT 1");
            if ($gid === 0) {
                $W("INSERT INTO link_groups (name, group_code, owner_role_id, icon, display_order,
                                             stage_no, stage_title, is_active)
                    VALUES ('" . $esc(repair01_w12_group_ar($s['group'])) . "','" . $esc($code) . "',$rid,
                            '" . $esc($s['icon']) . "'," . ((int) $sx['display_order'] + 1) . ","
                            . (int) $sx['stage_no'] . ",'" . $esc((string) $sx['stage_title']) . "',1)");
                $gid = (int) $one("SELECT id FROM link_groups WHERE group_code = '" . $esc($code) . "' LIMIT 1");
            } else {
                $W("UPDATE link_groups SET name = '" . $esc(repair01_w12_group_ar($s['group'])) . "',
                        is_active = 1, stage_no = " . (int) $sx['stage_no'] . " WHERE id = $gid");
            }
            if ($gid <= 0) { continue; }
            $W("INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route, icon,
                                       sort_order, permission_code, active)
                VALUES ($rid,'" . $esc($sx['door']) . "',$gid,$modId,'" . $esc($s['ar']) . "','$rt',
                        '" . $esc($s['icon']) . "'," . (int) $s['sort'] . ",'$rt',1)
                ON DUPLICATE KEY UPDATE label_ar=VALUES(label_ar), icon=VALUES(icon), group_id=VALUES(group_id),
                  sort_order=VALUES(sort_order), permission_code=VALUES(permission_code),
                  module_id=VALUES(module_id), active=1");
        }
        $navN += (int) $one("SELECT COUNT(*) FROM nav_items WHERE route = '$rt' AND active = 1");
    }

    /* ⓕ مصفوفةُ الدورةِ الحيّة — واسمُ الإدارةِ من جسرِ المسمّياتِ لا مخترَعًا
       ⚠ **والكنسُ بوسمِ الموجةِ لا باسمِ الملفِّ وحدَه** (‏درسُ W11): الجدولُ
         بلا مفتاحٍ فريدٍ، فالكنسُ باسمِ الملفِّ يمحو صفًّا مُعلَنًا سابقًا. */
    if ($modId > 0) {
        $deptAr = (string) $one("SELECT legacy_name FROM repair01_dept_crosswalk
                                  WHERE canonical_code = '" . $esc($s['owner']) . "' ORDER BY id LIMIT 1");
        if ($deptAr === '') { echo '  ⚠ لا جسرَ مسمًّى للإدارة ' . $s['owner'] . " — الصفُّ لا يُكتب\n"; }
        else {
            $W("DELETE FROM gov_screen_cycle
                 WHERE screen_file = '" . $esc($file) . "' AND inputs_note LIKE 'RPR-W12 %'");
            $W("INSERT INTO gov_screen_cycle
                (company_id, dept_name, layer_name, stage_order, stage_name, group_name, screen_title,
                 screen_file, inputs_note, output_doc, resp_role, next_state, consumers, fin_impact, stage_kind)
                VALUES (0,'" . $esc($deptAr) . "','" . $esc(repair01_w12_group_ar($s['group'])) . "','"
                        . (int) $s['sort'] . "','" . $esc(repair01_w12_group_ar($s['group'])) . "','"
                        . $esc(repair01_w12_group_ar($s['group'])) . "',
                        '" . $esc($s['ar']) . "','" . $esc($file) . "',
                        '" . $esc('RPR-W12 · متطلبات: ' . $s['req']) . "','" . $esc($s['doc']) . "',
                        '" . $esc($s['role']) . "','" . $esc($s['next']) . "','" . $esc($s['cons']) . "',
                        '" . $esc($s['fin']) . "','canonical')");
        }
    }

    /* ⓖ سجلُّ الشاشاتِ — بختمِ الموجةِ لا بلا ختم */
    $guard = repair01_w12_guard_of($ROOT, $s['route']);
    $W("INSERT INTO repair01_screen_registry
        (screen_id, screen_file, route, route_rule, owner_code, owner_role, owner_rule,
         lifecycle, lifecycle_rule, parent_screen_id, parent_rule, visibility_class, visibility_rule,
         on_disk, origin, guard_kind, guard_evidence, w2_why, src_ref)
        VALUES ('" . $esc($sid) . "','" . $esc($file) . "','$rt','W12_NEW_SURFACE_ROUTE',
                '" . $esc($s['owner']) . "','" . $esc($s['role']) . "','W12_REQUIREMENT_OWNER',
                'LIVE_UNREGISTERED','W12_GROWTH_OUTSIDE_STUDY_MATRIX','','','MENU_ITEM','NAV_ITEMS_ACTIVE',
                1,'W12','" . $esc($guard['kind']) . "','" . $esc($guard['evidence']) . "',
                '" . $esc($s['ar']) . " (" . $esc($file) . ")','RPR-W12 · التمويل والممولون')
        ON DUPLICATE KEY UPDATE owner_code=VALUES(owner_code), owner_role=VALUES(owner_role),
          visibility_class=VALUES(visibility_class), guard_kind=VALUES(guard_kind),
          guard_evidence=VALUES(guard_evidence), origin='W12', on_disk=1");
    $newN++;
}
printf("  أسطحُ نموٍّ مختومةٌ %d · بنودُ قائمةٍ نشِطة %d · منحٌ %d · مسمّياتٌ مسجَّلة %d · بلا ملفٍّ %d%s\n\n",
    $newN, $navN, $permN, $labelN, count($missing),
    $missing ? ' ⇐ ' . implode('، ', $missing) : '');

/* ═══════════════════════════════════════════════════════════════════════════
   ②-ب · مصفوفةُ الواجهة — **السطحُ المُصيَّرُ يلزمه صفٌّ فيها** (‏`U1`)
   ═══════════════════════════════════════════════════════════════════════════
   ◆ **والتسجيلُ ستٌّ لا خمس** (‏درسُ W07): سجلٌّ · دورةٌ · ملاحةٌ · مسمًّى ·
     مساحاتٌ — **ومصفوفةُ الواجهة**. وسطحٌ يُصيَّر بلا صفٍّ فيها يُسقط خطّافَ
     الالتزامِ عند `U1` بعد أن تكون كلُّ بوّاباتِ المرحلةِ خضراء.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "②-ب مصفوفةُ الواجهةِ — صفٌّ لكلِّ سطحٍ مُصيَّر ──────────────────\n";
$MTX = $ROOT . '/docs/uxui_matrix_20260818.csv';
$mtxN = 0;
if (!is_file($MTX)) { echo "  ⚠ مصفوفةُ الواجهةِ غيرُ موجودة — التسجيلُ يُتخطّى\n"; }
elseif ($REPORT) { echo "  ↷ قياسٌ بلا كتابة\n"; }
else {
    /* ⚠ **الصفوفُ الباقيةُ تُنقَل خامًّا لا يُعاد ترميزُها** — `fputcsv` يُعيد
         صوغَ ما لم يتغيّر فيظهر في الفرقِ سطورُ موجاتٍ لم تُمَسّ. */
    $lines = file($MTX, FILE_IGNORE_NEW_LINES);
    $hdr = array_shift($lines);
    $mine = array(); $keep = array(); $maxN = 0;
    foreach (repair01_w12_new_surfaces() as $s) { $mine[strtolower($s['route'])] = $s; }
    foreach ($lines as $ln) {
        if (trim($ln) === '') { continue; }
        $cells = str_getcsv($ln);
        if (!$cells || count($cells) < 2) { continue; }
        $maxN = max($maxN, (int) $cells[0]);
        if (isset($mine[strtolower(trim($cells[1]))])) { continue; }
        $keep[] = $ln;
    }
    $depAr = (string) $one("SELECT name_ar FROM repair01_departments WHERE canonical_code = 'DEP-03'");
    if ($depAr === '') { $depAr = 'DEP-03'; }
    $cell = function ($v) {
        $v = (string) $v;
        if ($v === '') { return '""'; }
        if (preg_match('/[",\s]/u', $v)) { return '"' . str_replace('"', '""', $v) . '"'; }
        return $v;
    };
    $rows = array();
    foreach (repair01_w12_new_surfaces() as $s) {
        $maxN++;
        $grp = repair01_w12_group_ar($s['group']);
        $def = 'تعرض ' . $s['ar'] . ' في دورة ' . $grp . ' لدى ' . $depAr
             . '. المستند الناتج ' . $s['doc'] . ' والخطوة التالية ' . $s['next'] . '.';
        $vals = array($maxN, $s['route'], $s['ar'], $s['ar'], '', '—', $def, $depAr,
            '2 — العمليات', $grp, $s['sort'], 'شاشةٌ مستقلة', 1, $s['cons'],
            'قدرةٌ ثبت غيابُها فبُنيت في موضعِها المعياريّ', 'APPROVED',
            'ترتيبُ دورةِ التمويلِ في الحزمة — RPR-W12', '—', '—', 'ACTIVE', '—',
            $s['ar'], $grp, 'موضعُه من دورةِ العمل — قرارُ الورقة', $grp);
        $rows[] = implode(',', array_map($cell, $vals));
        $mtxN++;
    }
    file_put_contents($MTX, $hdr . "\n" . implode("\n", $keep) . "\n" . implode("\n", $rows) . "\n");
}
printf("  صفوفُ مصفوفةٍ مكتوبةٌ لأسطحِ الموجة %d\n\n", $mtxN);

/* ═══════════════════════════════════════════════════════════════════════════
   ②-ج · سجلُّ تصنيفِ المساحات — **الغيابُ ليس منعًا** (`NF-24` · `GAP-22`)
   ═══════════════════════════════════════════════════════════════════════════ */
echo "②-ج تصنيفُ المساحاتِ — سطحٌ نشطٌ لا يُقرأ مفتوحًا افتراضًا ────────\n";
$spaceN = 0;
if (!repair01_w12_table_exists($conn, 'gov_space_appearances')) {
    echo "  ⚠ سجلُّ المساحاتِ غيرُ موجود — التصنيفُ يُتخطّى\n";
} else {
    $depAr2 = (string) $one("SELECT name_ar FROM repair01_departments WHERE canonical_code = 'DEP-03'");
    if ($depAr2 === '') { $depAr2 = 'DEP-03'; }
    foreach (repair01_w12_new_surfaces() as $s) {
        $rt = $esc($s['route']);
        $W("DELETE FROM gov_space_appearances WHERE route = '$rt' AND src_class = 'RPR-W12'");
        /* ⚠ **المفتاحُ هنا لا يتزايد ذاتيًّا** — فيُشتقُّ من أقصى القائمِ في
             كلِّ صفٍّ لا مرّةً واحدة (‏درسُ W11). */
        $nextId = (int) $one("SELECT COALESCE(MAX(id), 0) + 1 FROM gov_space_appearances");
        $W("INSERT INTO gov_space_appearances
            (id, space_ar, space_kind, tab_ar, screen_ar, route, owner_dept_ar, owner_kind,
             src_class, src_ownership, src_decision, src_note, spaces_count,
             cls, ownership, decision, basis, rule_step, view_fields, updated_at)
            VALUES ($nextId,'" . $esc($depAr2) . "','DEPARTMENT','','" . $esc($s['ar']) . "','$rt',
                    '" . $esc($depAr2) . "','BUSINESS_DEPARTMENT',
                    'RPR-W12','VALID','CONFIRMED',
                    '" . $esc('سطح نمو مختوم W12 - صنف بسلم الحسم السداسي') . "',1,
                    'OWNED','VALID','CONFIRMED',
                    '" . $esc('المساحة هي الادارة المالكة للسطح في السجل المعياري (DEP-03)') . "',
                    1,'',NOW())");
        $spaceN++;
    }
}
printf("  أسطحٌ مصنَّفةٌ في سجلِّ المساحات %d\n\n", $spaceN);

/* ═══════════════════════════════════════════════════════════════════════════
   ②-د · الفجواتُ السبعُ — **موفّاةٌ باسمٍ آخرَ لا مبنيّةٌ توأمًا**
   ═══════════════════════════════════════════════════════════════════════════
   ◆ سابقتا `W9-D-08` و`W11-D-11`: **الفجوةُ اسمٌ مستهدَفٌ لا اسمُ ملفٍّ مُلزِم**.
     وسبعُ فجواتِ `DEP-03` أسطحُها **قائمةٌ فعلًا** بأسماءٍ أخرى — وثلاثٌ منها
     تبويباتٌ داخلَ سطحٍ واحدٍ بـ`?view=`. فبناؤها بأسمائها يصنع توأمًا لا قدرة.
   ⛔ **ولا يُمَسُّ صفُّ الشبحِ ولا `on_disk` ولا سجلُّ مرحلةٍ مُغلقة** — فمقاما
     الأشباحِ (٢٥٧) والأساسِ (٦٥١) لا يتحرّكان بهذا القيد.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "②-د الفجواتُ — موفّاةٌ باسمٍ آخرَ لا توأمًا ─────────────────────\n";
$GAPFILL = array(
    'fin_deviations.php' => 'Financing/deviations.php',
    'fin_exit.php'       => 'Financing/asset_disposal.php',
    'fin_idle.php'       => 'Financing/deviations.php?view=v649001',
    'fin_ops.php'        => 'Financing/operation_profile.php',
    'fin_variance.php'   => 'Financing/deviations.php?view=v08c5c1',
    'financiers.php'     => 'Financing/financiers_registry.php',
    'shares.php'         => 'Financing/owners_registry.php',
);
$gapN = 0; $gapMiss = array();
$rg = $conn->query("SELECT id, surface_name FROM repair01_target_gaps
                     WHERE unit = 'DEP-03' AND wave_stage = 'W12' ORDER BY id");
while ($rg && $g = $rg->fetch_assoc()) {
    $hit = '';
    foreach ($GAPFILL as $ghost => $built) {
        if (mb_strpos((string) $g['surface_name'], $ghost) !== false) { $hit = $built; break; }
    }
    if ($hit === '') { $gapMiss[] = (string) $g['id']; continue; }
    /* **والموفّى يُثبَت من القرصِ لا يُدَّعى** — مسارُ التبويبِ يُقاس بملفِّه */
    $base = preg_replace('/\?.*$/', '', $hit);
    if (!is_file($ROOT . '/' . $base)) { $gapMiss[] = (string) $g['id'] . ' (لا ملف)'; continue; }
    $W("UPDATE repair01_target_gaps SET built_counterpart = '" . $esc($hit) . "'
         WHERE id = " . (int) $g['id']);
    $gapN++;
}
printf("  فجواتٌ قُيِّدت موفّاةً %d · بلا موفٍّ %d%s\n\n", $gapN, count($gapMiss),
    $gapMiss ? ' ⇐ ' . implode('، ', $gapMiss) : '');

/* ═══════════════════════════════════════════════════════════════════════════
   ③ نطاقُ المرحلة — ٢٨ متطلَّبًا إلى مِرساتِها المُثبَتةِ قياسًا
   ═══════════════════════════════════════════════════════════════════════════ */
echo "③ نطاقُ المرحلة ───────────────────────────────────────────────\n";
$W("DELETE FROM repair01_w12_scope");
$ANCH = repair01_w12_anchors();
$anchored = 0; $unproven = array(); $ownerMismatch = array(); $unscoped = array();
$newRoutes = array_column(repair01_w12_new_surfaces(), 'route');
$CLOSEKIND = array('FIN-15' => 'CONTRACTUAL', 'FIN-16' => 'MONTHLY', 'FIN-24' => 'FINAL');
$PAYLAYER  = array('FIN-17' => 'FUTURE', 'FIN-18' => 'FUTURE', 'FIN-27' => 'LEGACY');

$rq = $conn->query("SELECT requirement_id, unit, group_name, surface, src_ref
                      FROM repair01_requirements WHERE stage_no = 12 ORDER BY unit, seq");
while ($rq && $q = $rq->fetch_assoc()) {
    $rid = $q['requirement_id'];
    $dept = preg_match('/^(\d{2})\s/u', $q['unit'], $mm) ? 'DEP-' . $mm[1] : '';
    if (!isset($ANCH[$rid])) { $unproven[] = $rid . ' (بلا مِرساةٍ مُعلَنة)'; continue; }
    $a = $ANCH[$rid];
    $pr = repair01_w12_prove_anchor($conn, $ROOT, $a);
    if ($pr['verdict'] === 'ANCHORED') { $anchored++; }
    else { $unproven[] = $rid . ' (' . $pr['verdict'] . ')'; }

    $verdictOwner = ($pr['owner'] !== '' && $dept !== '' && $pr['owner'] !== $dept) ? 'MISMATCH' : 'MATCH';
    if ($verdictOwner === 'MISMATCH') { $ownerMismatch[] = $rid . ' ' . $pr['owner'] . ' بدل ' . $dept; }
    $build = in_array($a['route'], $newRoutes, true) ? 'BUILT_W12' : 'LIVE';
    /* **الحبّةُ تُقاس لا تُدَّعى**: الجدولُ يحمل الكيانَ غيرَ قابلٍ للعدمِ أو لا */
    $scoped = ($a['kind'] === 'TABLE' && repair01_w12_entity_scoped($conn, $a['probe'])) ? 1 : 0;
    if ($a['kind'] === 'TABLE' && $scoped === 0) { $unscoped[] = $rid . ' ⇐ ' . $a['probe']; }

    $W("INSERT INTO repair01_w12_scope
        (requirement_id,unit,group_name,surface,anchor_screen_id,anchor_route,anchor_probe,
         owner_measured,owner_expected,owner_verdict,build_verdict,cycle_step,entity_scoped,
         close_kind,payment_layer,map_rule,map_why,src_ref)
        VALUES ('" . $esc($rid) . "','" . $esc($q['unit']) . "','" . $esc($q['group_name']) . "',
                '" . $esc($q['surface']) . "','" . $esc($pr['sid']) . "','" . $esc($a['route']) . "',
                '" . $esc($a['probe']) . "','" . $esc($pr['owner']) . "','" . $esc($dept) . "',
                '" . $esc($verdictOwner) . "','" . $esc($build) . "'," . (int) $a['step'] . ",$scoped,
                '" . $esc(isset($CLOSEKIND[$rid]) ? $CLOSEKIND[$rid] : '') . "',
                '" . $esc(isset($PAYLAYER[$rid]) ? $PAYLAYER[$rid] : '') . "',
                '" . $esc($pr['rule']) . "','" . $esc($a['why']) . "','" . $esc($q['src_ref']) . "')");
}
printf("  مُثبَتٌ من القرص %d · غيرُ مُثبَتٍ %d%s · مالكٌ مخالفٌ %d · جدولٌ بلا كيانٍ إلزاميٍّ %d%s\n\n",
    $anchored, count($unproven), $unproven ? ' ⇐ ' . implode('، ', array_slice($unproven, 0, 3)) : '',
    count($ownerMismatch), count($unscoped),
    $unscoped ? ' ⇐ ' . implode('، ', array_slice($unscoped, 0, 3)) : '');

/* ═══════════════════════════════════════════════════════════════════════════
   ③-ب · الخطوةُ السابعةُ لأسطحِ النطاقِ القائمة — الربطُ بالسجلِّ المعياريّ
   ═══════════════════════════════════════════════════════════════════════════ */
echo "③-ب ربطُ أسطحِ النطاقِ بالسجلِّ المعياريّ ────────────────────────\n";
$linkFix = 0; $seen = array();
$rq2 = $conn->query("SELECT requirement_id, surface, group_name FROM repair01_requirements
                      WHERE stage_no = 12 ORDER BY unit, seq");
while ($rq2 && $q2 = $rq2->fetch_assoc()) {
    $rid = (string) $q2['requirement_id'];
    if (!isset($ANCH[$rid]) || $ANCH[$rid]['route'] === '') { continue; }
    $rt = $ANCH[$rid]['route'];
    if (isset($seen[$rt])) { continue; }
    $seen[$rt] = true;
    $rtE = $esc($rt);
    if ((int) $one("SELECT COUNT(*) FROM nav_canonical WHERE route = '$rtE'") > 0) { continue; }
    $sid = (string) $one("SELECT screen_id FROM repair01_screen_registry WHERE route = '$rtE' LIMIT 1");
    if ($sid === '') { echo "  ⚠ $rt بلا مُعرِّفٍ في سجلِّ الشاشات — لا يُربَط\n"; continue; }
    $vis = (string) $one("SELECT visibility_class FROM repair01_screen_registry WHERE route = '$rtE' LIMIT 1");
    $own = (string) $one("SELECT owner_code FROM repair01_screen_registry WHERE route = '$rtE' LIMIT 1");
    $sortNo = (int) $ANCH[$rid]['step'];
    if (!$REPORT) {
        $lr2 = \App\Services\Ui\UiLabelRegistry::register($conn, 'screen:' . strtolower($rt),
            (string) $q2['surface'], array(
            'allowed_context' => 'SIDEBAR SCREEN_TITLE',
            'source_table' => 'nav_canonical', 'source_column' => 'canonical_ar',
            'source_key' => $rt, 'owner_code' => $own !== '' ? $own : 'DEP-03',
            'visibility_class' => 'USER_VISIBLE', 'label_state' => 'ACTIVE',
            'rule_id' => 'W12_SCOPE_SURFACE_LABEL', 'origin' => 'W12',
            'src_ref' => 'RPR-W12 §٣-ب · ربطُ سطحٍ قائمٍ بالسجلِّ المعياريّ',
            'caller' => 'repair01_w12_apply.php',
        ));
        if (!$lr2['ok']) { echo '  ⚠ رُدَّ مسمّى ' . $rt . ' — ' . $lr2['code'] . "\n"; }
    }
    $W("INSERT INTO nav_canonical (route, canonical_ar, level_no, level_name, group_name, sort_no,
                                   status, decision_state, application_state, decision_source,
                                   derivation, retirement_status, screen_id, placement_kind)
        VALUES ('$rtE','" . $esc($q2['surface']) . "',2,'العمليات','"
                . $esc(repair01_w12_group_ar($q2['group_name'])) . "'," . $sortNo . ",
                'APPROVED','APPROVED','DEPLOYED','RPR-W12 · ربط سطح النطاق بالسجل المعياري (2026-08-26)',
                'التسمية المعيارية من repair01_requirements.surface','ACTIVE','" . $esc($sid) . "',
                '" . $esc($vis === 'TAB_CHILD' ? 'TAB' : 'MENU_ITEM') . "')");
    echo "  ✔ $rt ⇐ " . $q2['surface'] . " · ترتيب $sortNo\n";
    $linkFix++;
}
printf("  أسطحٌ رُبطت بالسجلِّ المعياريّ %d\n\n", $linkFix);

/* ═══════════════════════════════════════════════════════════════════════════
   ③-ج · الخطوتانِ ② و③ **تصحيحٌ لا قياس** — الاسمُ ثمَّ المجموعة
   ═══════════════════════════════════════════════════════════════════════════
   ◆ **محوَرانِ منفصلانِ ولا يُخلطان** (‏درسُ `nav-group-key-is-route-not-role`):
     محورُ **الاسمِ** مرجعُه `nav_canonical.canonical_ar` — وهو معتمَدٌ بقرارِ
     مالكِه، فـ⛔ **لا يُعاد تسميتُه هنا** (‏`W6-D-09`)؛ والتصحيحُ يمضي في
     الاتّجاهِ الصحيح: **البندُ الحيُّ يتبع المعياريَّ** لا العكس.
   ◆ ومحورُ **المجموعةِ** مرجعُه **دورةُ العمل** (`repair01_requirements.group_name`)
     فيُصحَّح المعياريُّ عليها ثمَّ يتبعه الحيُّ بمجموعةٍ مختومةٍ بموجتِها.
   ⛔ **ولا مصفوفةَ بنودٍ في الشيفرة** — الأسماءُ والمجموعاتُ من السجلِّ وحدَه.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "③-ج تصحيحُ الاسمِ ثمَّ المجموعةِ ثمَّ الترتيبِ على دورةِ العمل ──────\n";
$lblFix = 0; $grpFix = 0; $grpCanonFix = 0; $ordFix = 0;
/* **موضعُ كلِّ سطحٍ من الدورةِ يُشتَقُّ مرّةً ثمَّ يُفرَض على السجلّ** — وسطحٌ
   يخدم أكثرَ من متطلَّبٍ يأخذ **أقدمَ** مواضعِه في الدورةِ لا آخرَها. */
$stepByRoute = array();
foreach ($ANCH as $a2) {
    if ($a2['route'] === '') { continue; }
    if (!isset($stepByRoute[$a2['route']]) || (int) $a2['step'] < $stepByRoute[$a2['route']]) {
        $stepByRoute[$a2['route']] = (int) $a2['step'];
    }
}
foreach ($ANCH as $rid => $a) {
    if ($a['route'] === '') { continue; }
    $rtE = $esc($a['route']);
    $reqGrp = (string) $one("SELECT group_name FROM repair01_requirements
                              WHERE requirement_id = '" . $esc($rid) . "' AND stage_no = 12 LIMIT 1");
    if ($reqGrp === '') { continue; }
    $grpAr = repair01_w12_group_ar($reqGrp);
    /* ① المعياريُّ يتبع دورةَ العمل */
    $canGrp = (string) $one("SELECT group_name FROM nav_canonical WHERE route = '$rtE' LIMIT 1");
    if ($canGrp !== $grpAr) {
        $W("UPDATE nav_canonical SET group_name = '" . $esc($grpAr) . "' WHERE route = '$rtE'");
        $grpCanonFix++;
    }
    /* ①-ب **والترتيبُ من دورةِ العملِ لا من تاريخِ الإنشاء** (§٤-٤):
       ⚠ السطحُ القائمُ يحمل `sort_no` موروثًا من موجةٍ سابقةٍ، فتصحيحُ
         المجموعةِ وحدَه يترك القائمةَ تُقرأ معكوسةً داخلَها. والموضعُ يُفرَض
         **من `cycle_step`** — و`W12-07` يعيد اشتقاقَه ويسقط على أيِّ انعكاس. */
    $wantSort = isset($stepByRoute[$a['route']]) ? (int) $stepByRoute[$a['route']] : (int) $a['step'];
    $curSort = (int) $one("SELECT sort_no FROM nav_canonical WHERE route = '$rtE' LIMIT 1");
    if ($curSort !== $wantSort) {
        $W("UPDATE nav_canonical SET sort_no = $wantSort WHERE route = '$rtE'");
        $W("UPDATE nav_items SET sort_order = $wantSort WHERE route = '$rtE'");
        $ordFix++;
    }
    $canAr = (string) $one("SELECT canonical_ar FROM nav_canonical WHERE route = '$rtE' LIMIT 1");
    if ($canAr === '') { continue; }
    /* ② البندُ الحيُّ يتبع المعياريَّ اسمًا */
    $drift = (int) $one("SELECT COUNT(*) FROM nav_items WHERE route = '$rtE'
                          AND label_ar <> '" . $esc($canAr) . "'");
    if ($drift > 0) {
        $W("UPDATE nav_items SET label_ar = '" . $esc($canAr) . "' WHERE route = '$rtE'");
        $lblFix += $drift;
    }
    /* ③ ومجموعةُ البندِ الحيِّ تتبع مجموعةَ الدورة
       ⛔ **ولا يُعاد تسميةُ مجموعةٍ مشتركة**: `link_groups` صفٌّ واحدٌ قد
         تحمله عدّةُ مسارات (‏المقيس: «الأصول» تحمل ثلاثةَ مسارات)، وتسميتُه
         باسمِ دورةِ أحدِها **تدهس مجموعةَ الآخرَين** — ثمَّ يعيدها تصحيحُهم
         فيتأرجح الاسمُ بين التشغيلَين ولا يستقرّ. فالسطحُ **يُنقَل إلى مجموعةٍ
         مختومةٍ بموجتِه** باسمِ دورتِه — والمشتركةُ تبقى لأهلِها كما هي. */
    $sid3 = (string) $one("SELECT screen_id FROM repair01_screen_registry WHERE route = '$rtE' LIMIT 1");
    if ($sid3 === '') { continue; }
    $gDrift = $conn->query("SELECT n.id, n.role_id, n.group_id, COALESCE(g.name, '') gname,
                                   COALESCE(g.stage_no, 0) stage_no, COALESCE(g.stage_title, '') stage_title,
                                   COALESCE(g.display_order, 0) display_order, COALESCE(g.icon, '') icon
                              FROM nav_items n
                              LEFT JOIN link_groups g ON g.id = n.group_id
                             WHERE n.route = '$rtE' AND COALESCE(g.name, '') <> '" . $esc($grpAr) . "'");
    $moves = array();
    while ($gDrift && $gx = $gDrift->fetch_assoc()) { $moves[] = $gx; }
    foreach ($moves as $gx) {
        $rid3 = (int) $gx['role_id'];
        $code3 = 'n9o_w12_' . strtolower(str_replace('-', '', $sid3)) . '_r' . $rid3;
        $gid3 = (int) $one("SELECT id FROM link_groups WHERE group_code = '" . $esc($code3) . "' LIMIT 1");
        if ($gid3 === 0) {
            $W("INSERT INTO link_groups (name, group_code, owner_role_id, icon, display_order,
                                         stage_no, stage_title, is_active)
                VALUES ('" . $esc($grpAr) . "','" . $esc($code3) . "',$rid3,'" . $esc($gx['icon']) . "',
                        " . ((int) $gx['display_order'] + 1) . "," . (int) $gx['stage_no'] . ",
                        '" . $esc((string) $gx['stage_title']) . "',1)");
            $gid3 = (int) $one("SELECT id FROM link_groups WHERE group_code = '" . $esc($code3) . "' LIMIT 1");
        } else {
            $W("UPDATE link_groups SET name = '" . $esc($grpAr) . "', is_active = 1 WHERE id = $gid3");
        }
        if ($gid3 <= 0) { continue; }
        /* **الموضعُ الأصليُّ يُقيَّد قبل النقل** — فالإرجاعُ يعيده حرفًا ولا يترك
           البندَ يشير إلى مجموعةٍ حُذفت (‏أثرٌ باقٍ أسوأُ من عدمِ الإرجاع). */
        $W("INSERT INTO repair01_w12_nav_moves
            (nav_item_id, route, role_id, from_group_id, to_group_id, to_group_code, why)
            VALUES (" . (int) $gx['id'] . ",'$rtE',$rid3," . (int) $gx['group_id'] . ",$gid3,
                    '" . $esc($code3) . "',
                    '" . $esc('مجموعة الدورة تخالف المجموعة الحية والمجموعة الحية مشتركة فلا تسمى') . "')
            ON DUPLICATE KEY UPDATE to_group_id = VALUES(to_group_id),
              to_group_code = VALUES(to_group_code)");
        $W("UPDATE nav_items SET group_id = $gid3 WHERE id = " . (int) $gx['id']);
        $grpFix++;
    }
}
printf("  اسمٌ حيٌّ صُحِّح %d · مجموعةٌ معياريّةٌ صُحِّحت %d · مجموعةٌ حيّةٌ صُحِّحت %d · ترتيبٌ صُحِّح %d\n\n",
    $lblFix, $grpCanonFix, $grpFix, $ordFix);

/* ═══════════════════════════════════════════════════════════════════════════
   ④ الخطواتُ السبعُ للسايدبار — على أسطحِ النطاقِ كلِّها
   ═══════════════════════════════════════════════════════════════════════════ */
echo "④ السايدبارُ — سبعُ خطواتٍ بحكمٍ وقاعدةٍ لكلِّ سطح ─────────────\n";
$W("DELETE FROM repair01_w12_sidebar");
$routes = array();
foreach ($ANCH as $rid => $a) {
    if ($a['route'] === '') { continue; }
    if (!isset($routes[$a['route']]) || (int) $a['step'] < (int) $routes[$a['route']]) {
        $routes[$a['route']] = (int) $a['step'];
    }
}
$sbN = 0; $sbBad = 0;
foreach ($routes as $rt => $step) {
    $rtE = $esc($rt);
    $reg = $conn->query("SELECT screen_id, owner_code, visibility_class, guard_kind
                           FROM repair01_screen_registry WHERE route = '$rtE' LIMIT 1");
    $reg = $reg ? $reg->fetch_assoc() : null;
    if (!$reg) { $sbBad++; continue; }
    $sid = (string) $reg['screen_id'];

    $can = $conn->query("SELECT canonical_ar, group_name, sort_no, status FROM nav_canonical WHERE route='$rtE' LIMIT 1");
    $can = $can ? $can->fetch_assoc() : null;
    $live = $conn->query("SELECT n.label_ar, g.name AS gname, n.sort_order, n.active
                            FROM nav_items n LEFT JOIN link_groups g ON g.id = n.group_id
                           WHERE n.route = '$rtE' ORDER BY n.active DESC LIMIT 1");
    $live = $live ? $live->fetch_assoc() : null;

    $s1 = $live ? ((int) $live['active'] === 1 ? 'ACTIVE_APPROVED' : 'DISABLED_WITH_REASON') : 'NO_NAV_ITEM';
    $s2live = $live ? (string) $live['label_ar'] : '';
    $s2can  = $can ? (string) $can['canonical_ar'] : '';
    $s2 = ($s2can !== '' && $s2live === $s2can) ? 'LABEL_MATCH' : ($s2can === '' ? 'NO_CANONICAL' : 'LABEL_DRIFT');
    $s3live = $live ? (string) $live['gname'] : '';
    $s3can  = $can ? (string) $can['group_name'] : '';
    $s3 = ($s3can !== '' && $s3live === $s3can) ? 'GROUP_MATCH' : ($s3can === '' ? 'NO_CANONICAL' : 'GROUP_DRIFT');
    $s4no = $can ? (int) $can['sort_no'] : 0;
    /* **الترتيبُ من دورةِ العملِ لا من الأبجديّة**: للسطحِ موضعٌ في الدورةِ
       ومصدرُ ترتيبٍ في السجلِّ معًا — و`W12-07` يعيد اشتقاقَه ويقارنه. */
    $s4 = ($s4no > 0 || (int) $step === 0) ? 'ORDER_FROM_CYCLE' : 'NO_ORDER_SOURCE';
    $s5 = ((string) $reg['visibility_class'] === 'TAB_CHILD') ? 'TAB_IN_PARENT' : 'MENU_ITEM';
    $permRows = (int) $one("SELECT COUNT(*) FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                             WHERE m.code = '$rtE' AND rp.can_view = 1");
    $guard = repair01_w12_guard_of($ROOT, $rt);
    $s6 = ($guard['kind'] !== 'NONE' && $permRows > 0) ? 'GUARDED_AND_GRANTED'
        : ($guard['kind'] === 'NONE' ? 'NO_SERVER_GUARD' : 'NO_GRANT');
    $s7 = ($can && (string) $can['canonical_ar'] !== '' && $sid !== '') ? 1 : 0;

    $W("INSERT INTO repair01_w12_sidebar
        (screen_id,route,owner_code,s1_verdict,s1_rule,s2_label_live,s2_label_canon,s2_verdict,s2_rule,
         s3_group_live,s3_group_canon,s3_verdict,s3_rule,s4_order_src,s4_order_no,s4_cycle_step,
         s4_verdict,s4_rule,s5_parent,s5_verdict,s5_rule,s5_why,s6_visibility,s6_perm_rows,
         s6_guard_kind,s6_verdict,s6_rule,s7_linked,s7_verdict,s7_rule,measured_at)
        VALUES ('" . $esc($sid) . "','$rtE','" . $esc($reg['owner_code']) . "',
                '" . $esc($s1) . "','W12_S1_ACTIVE_BY_TARGET',
                '" . $esc($s2live) . "','" . $esc($s2can) . "','" . $esc($s2) . "','W12_S2_LABEL_FROM_REQUIREMENT',
                '" . $esc($s3live) . "','" . $esc($s3can) . "','" . $esc($s3) . "','W12_S3_GROUP_FROM_CYCLE',
                'nav_canonical.sort_no'," . $s4no . "," . (int) $step . ",
                '" . $esc($s4) . "','W12_S4_ORDER_FROM_CYCLE',
                '','" . $esc($s5) . "','W12_S5_PARENT_FROM_DECISION','موضعُ السطحِ من قرارِ الورقةِ لا من الذوق',
                '" . $esc((string) $reg['visibility_class']) . "'," . $permRows . ",
                '" . $esc($guard['kind']) . "','" . $esc($s6) . "','W12_S6_GUARD_AND_GRANT',
                " . $s7 . ",'" . ($s7 ? 'LINKED' : 'NOT_LINKED') . "','W12_S7_CANONICAL_SCREEN_ID',NOW())");
    $sbN++;
}
printf("  أسطحٌ مقيسةٌ بسبعِ خطوات %d · بلا صفٍّ في السجلّ %d\n\n", $sbN, $sbBad);

/* ═══════════════════════════════════════════════════════════════════════════
   ⑤ العتباتُ — من السجلِّ لا من الشيفرة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑤ العتبات ────────────────────────────────────────────────────\n";
$W("DELETE FROM repair01_w12_thresholds");
$TH = array(
    array('FIN_CLOSE_LAG_DAYS', 5, 'يوم', 'مهلة اعداد الاقفال التعاقدي بعد نهاية فترته',
          'بعدها ينبه النظام ولا يقفل تلقائيا - الاقفال اثبات لا جدولة', 'W12-D-05'),
    array('FIN_ARREARS_ALERT_DAYS', 30, 'يوم', 'ايام التاخير التي تفتح انحرافا',
          'التاخير دونها متابعة وفوقها انحراف بمساره ومسؤوله', 'W12-D-05'),
    array('FIN_ALLOCATION_TOLERANCE', 1, 'وحدة عملة الاساس', 'سماح فرق التخصيص قبل فتح فرق',
          'الفرق دون هذه القيمة تقريب فاصلة والباقي يلزمه بند بسببه', 'W12-D-05'),
    array('FIN_ORDER_ESCALATE_PCT', 90, 'نسبة مئوية', 'نسبة المطلوب من سقف الصلاحية التي تصعد',
          'التصعيد قبل التجاوز - والسقف من قاعدة الصلاحية لا من هذه النسبة', 'W12-D-06'),
    array('FIN_STATEMENT_MATCH_TOLERANCE', 1, 'وحدة عملة الاساس', 'سماح فرق مطابقة كشف الممول',
          'الفرق فوقها يفتح نزاعا موثقا ولا يقفل الشهر صامتا', 'W12-D-06'),
    array('FIN_LEGACY_EVIDENCE_MIN_ROWS', 1, 'صف', 'الحد الادنى لصفوف الدفتر خلف السطر المجمع',
          'صف مجمع بلا صف دفتر خلفه دعوى لا رقم - والقيمة تقرا ولا تكتب', 'W12-D-07'),
    array('FIN_COVENANT_GRACE_DAYS', 15, 'يوم', 'مهلة تصحيح الاخلال قبل تسجيله',
          'المهلة تعطى مرة وتوثق - وبعدها الاخلال يقيد بمرجعه', 'W12-D-07'),
);
foreach ($TH as $t) {
    $W("INSERT INTO repair01_w12_thresholds (threshold_key,value_num,unit_ar,title_ar,why,decision_ref,src_ref)
        VALUES ('" . $esc($t[0]) . "'," . (float) $t[1] . ",'" . $esc($t[2]) . "','" . $esc($t[3]) . "',
                '" . $esc($t[4]) . "','" . $esc($t[5]) . "','RPR-W12 §٥')");
}
printf("  عتباتٌ مسجَّلة %d\n\n", count($TH));

/* ═══════════════════════════════════════════════════════════════════════════
   ⑥ آلاتُ الحالة — لكلِّ كيانٍ ممنوعٌ صريحٌ بسبب
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑥ آلاتُ الحالة ───────────────────────────────────────────────\n";
$W("DELETE FROM repair01_w12_states");
$ST = array(
    /* الحاجةُ التمويليّة */
    array('fin_funding_need', 'submitted', 'approved', 1, 'مدير التمويل',
          'مبرر مكتوب ومبلغ موجب وعملة ومن رفعها لا يعتمدها',
          'حاجة تمويلية معتمدة', 'بوابة فصل الواجبات',
          'تفتح باعادة رفعها من الادارة الطالبة',
          'التصحيح بحاجة جديدة تحمل مرجع السابقة لا بتعديل المعتمدة', ''),
    array('fin_funding_need', 'submitted', 'rejected', 1, 'مدير التمويل',
          'سبب رد مكتوب', 'قرار رد حاجة', 'بوابة فصل الواجبات',
          'تفتح باستيفاء ما نقص ورفعها من جديد',
          'التصحيح باستيفاء المبرر ثم اعادة الرفع', ''),
    array('fin_funding_need', 'draft', 'approved', 0, '', '', '', '', '', '',
          'اعتماد مسودة يتجاوز الرفع فلا يبقى للادارة الطالبة اثر في السلسلة'),
    /* عرضُ التمويل */
    array('fin_funding_offer', 'received', 'negotiating', 1, 'مدير التمويل',
          'اصدار جديد يحمل مرجع سابقه', 'عرض باصداره', 'مدير التمويل',
          'يفتح باصدار نسخة تالية', 'التصحيح بنسخة جديدة لا بتعديل الوارد', ''),
    array('fin_funding_offer', 'received', 'shortlisted', 1, 'مدير التمويل',
          'مستند العرض مرفق ومقارنة موثقة', 'قائمة قصيرة موثقة', 'مدير التمويل',
          'يفتح باستبعاد او اعادة تفاوض', 'التصحيح باستبعاد معلل ثم ترشيح غيره', ''),
    array('fin_funding_offer', 'shortlisted', 'accepted', 1, 'مدير التمويل',
          'مراجعة ما قبل التعاقد مجازة', 'قرار قبول عرض', 'مراجعة ما قبل التعاقد',
          'لا يفتح - القبول واقعة والعدول باستبعاد جديد',
          'التصحيح بعرض جديد لا بقلب المقبول', ''),
    array('fin_funding_offer', 'received', 'accepted', 0, '', '', '', '', '', '',
          'قبول عرض بلا قائمة قصيرة ولا مراجعة يلغي المقارنة ويجعل الاختيار بلا سند'),
    /* المراجعةُ قبل التعاقد */
    array('fin_precontract_review', 'pending', 'cleared', 1, 'مدير التمويل',
          'راي القانوني وراي المالية مسجلان بمسؤوليهما',
          'محضر مراجعة مجاز', 'بوابة فصل الواجبات',
          'يفتح بمراجعة جديدة تحمل مرجع السابقة',
          'التصحيح بمراجعة جديدة لا بتعديل المجازة', ''),
    array('fin_precontract_review', 'pending', 'blocked', 1, 'مدير التمويل',
          'سبب حجب مكتوب', 'قرار حجب', 'بوابة فصل الواجبات',
          'يفتح بمعالجة سبب الحجب ثم مراجعة جديدة',
          'التصحيح بمعالجة السبب لا بقلب الحكم', ''),
    array('fin_precontract_review', 'blocked', 'cleared', 0, '', '', '', '', '', '',
          'قلب الحجب اجازة على المحضر نفسه يمحو قرارا مسببا - والاجازة بمحضر جديد'),
    /* عقدُ التمويل */
    array('fin_finance_contract', 'draft', 'signed', 1, 'المخول بسقفه',
          'مراجعة مجازة ومستند عقد موقع ومن اعده لا يوقعه',
          'عقد تمويل موقع', 'بوابة فصل الواجبات',
          'لا يفتح - التعديل بملحق مؤرخ يحمل مرجع الاصل',
          'التصحيح بملحق تعديل لا بتعديل الموقع', ''),
    array('fin_finance_contract', 'signed', 'active', 1, 'مدير التمويل',
          'العملية مفتوحة وجدول الاقساط مولد', 'عملية تمويل مفتوحة', 'مدير التمويل',
          'يفتح بتعليق ثم اعادة تنشيط بقرار', 'التصحيح باعادة جدولة مؤرخة', ''),
    array('fin_finance_contract', 'active', 'closed', 1, 'مدير التمويل',
          'اقفال نهائي معتمد للعملية', 'شهادة اقفال', 'بوابة الاقفال النهائي',
          'لا يفتح - اعادة الفتح بعقد جديد',
          'التصحيح بعقد جديد يحمل مرجع المقفل', ''),
    array('fin_finance_contract', 'draft', 'active', 0, '', '', '', '', '', '',
          'تنشيط مسودة يقفز التوقيع فتصير التزامات بلا مستند يسندها'),
    array('fin_finance_contract', 'closed', 'active', 0, '', '', '', '', '', '',
          'اعادة تنشيط عقد مقفل تنقض شهادة اقفال صدرت للممول'),
    /* الالتزامُ التعاقديّ */
    array('fin_contract_covenant', 'active', 'breached', 1, 'مدير التمويل',
          'قياس بقاعدته ومرجع اخلال مكتوب', 'سجل اخلال', 'مدير التمويل',
          'يفتح بمعالجة الاخلال وتوثيقها', 'التصحيح بمعالجة موثقة ثم اعادة القياس', ''),
    array('fin_contract_covenant', 'breached', 'waived', 1, 'المخول بسقفه',
          'مستند تنازل من الممول ومن رصد الاخلال لا يتنازل عنه',
          'مستند تنازل', 'بوابة صلاحية التنازل',
          'يفتح بانتهاء مدة التنازل', 'التصحيح بتنازل جديد لا بمحو الاخلال', ''),
    array('fin_contract_covenant', 'active', 'waived', 0, '', '', '', '', '', '',
          'تنازل عن التزام لم يرصد اخلاله يفتح بابا للاعفاء المسبق بلا واقعة'),
    /* **الإقفالُ التعاقديّ** */
    array('fin_contract_close', 'draft', 'prepared', 1, 'محاسب التمويل',
          'رقم فترة تعاقدية موجب ورصيد افتتاحي يساوي ختامي السابقة',
          'مسودة اقفال تعاقدي', 'اثبات ترحيل الرصيد',
          'يفتح باعادة اشتقاقه ما دام غير معتمد',
          'التصحيح باعادة الاشتقاق قبل الاعتماد', ''),
    array('fin_contract_close', 'prepared', 'reviewed', 1, 'مدير التمويل',
          'مراجعة الارصدة والمتاخرات', 'اقفال تعاقدي مراجع', 'مدير التمويل',
          'يفتح باعادته للاعداد بسبب مكتوب', 'التصحيح باعادة الاعداد قبل الاعتماد', ''),
    array('fin_contract_close', 'reviewed', 'approved', 1, 'مدير التمويل',
          'ترحيل رصيد مثبت ومن اعده لا يعتمده',
          'اقفال تعاقدي معتمد', 'بوابة فصل الواجبات',
          'لا يفتح - التصحيح باقفال لاحق يحمل الفرق',
          'التصحيح باقفال تعاقدي جديد للفترة التالية يحمل التعديل المعتمد', ''),
    array('fin_contract_close', 'approved', 'superseded', 1, 'مدير التمويل',
          'اقفال بديل معتمد يحمل مرجع المستبدل', 'اقفال بديل', 'مدير التمويل',
          'لا يفتح - الاستبدال واقعة مستقلة', 'التصحيح بالبديل لا بمحو الاصل', ''),
    array('fin_contract_close', 'approved', 'draft', 0, '', '', '', '', '', '',
          'ارجاع اقفال معتمد الى المسودة يفك رصيدا صار افتتاحيا لفترة تالية ووقع عليه'),
    array('fin_contract_close', 'draft', 'approved', 0, '', '', '', '', '', '',
          'اعتماد مسودة يقفز المراجعة فيضيع الفصل بين من اعد ومن راجع ومن اعتمد'),
    /* **الإقفالُ الشهريّ** — كيانٌ آخرُ بحالاتِه */
    array('fin_monthly_close', 'draft', 'prepared', 1, 'محاسب التمويل',
          'شهر تقويمي كامل ورصيد اول الشهر يساوي رصيد اخر السابق',
          'مسودة اقفال شهري', 'قيد الشهر التقويمي في القاعدة',
          'يفتح باعادة اشتقاقه ما دام غير معتمد',
          'التصحيح باعادة الاشتقاق قبل الاعتماد', ''),
    array('fin_monthly_close', 'prepared', 'approved', 1, 'مدير التمويل',
          'اقفال تعاقدي واحد فاكثر مضموم اليه ومن اعده لا يعتمده',
          'اقفال شهري معتمد وكشف حساب', 'بوابة فصل الواجبات',
          'لا يفتح - التصحيح في شهر لاحق',
          'التصحيح باقفال الشهر التالي يحمل الفرق بمرجعه', ''),
    array('fin_monthly_close', 'draft', 'approved', 0, '', '', '', '', '', '',
          'اعتماد شهر لم يعد يجعل الرصيد الشهري رقما بلا اشتقاق يسنده'),
    array('fin_monthly_close', 'prepared', 'superseded', 0, '', '', '', '', '', '',
          'استبدال شهر لم يعتمد يخفي مسودة بدل ان يصححها - والتصحيح باعادة الاشتقاق'),
    /* **الإقفالُ النهائيّ** — كيانٌ ثالثٌ لا حالةٌ للسابقَين */
    array('fin_final_close', 'requested', 'reviewed', 1, 'مدير التمويل',
          'موقف الاستحقاقات والانحرافات مقيس', 'مسودة اقفال نهائي', 'مدير التمويل',
          'يفتح باعادته للطلب بسبب مكتوب', 'التصحيح باستيفاء ما نقص قبل الاعتماد', ''),
    array('fin_final_close', 'reviewed', 'approved', 1, 'المخول بسقفه',
          'صفر استحقاق مفتوح وصفر انحراف حاجب واخلاء طرف واخر اقفال دوري مرجعا ومن طلبه لا يعتمده',
          'اخلاء طرف او شهادة اقفال نهائي', 'بوابة فصل الواجبات',
          'لا يفتح - اعادة الفتح بعقد جديد لا باعادة اقفال',
          'التصحيح بتسوية لاحقة موثقة لا بقلب الاقفال', ''),
    array('fin_final_close', 'requested', 'rejected', 1, 'مدير التمويل',
          'سبب رد مكتوب', 'قرار رد اقفال نهائي', 'مدير التمويل',
          'يفتح بطلب جديد بعد معالجة السبب', 'التصحيح بمعالجة السبب ثم طلب جديد', ''),
    array('fin_final_close', 'requested', 'approved', 0, '', '', '', '', '', '',
          'اعتماد طلب لم يراجع يقفز قياس الاستحقاقات والانحرافات فيصدر اخلاء طرف على ذمة قائمة'),
    array('fin_final_close', 'approved', 'requested', 0, '', '', '', '', '', '',
          'اعادة فتح اقفال نهائي معتمد تنقض اخلاء طرف سلم للممول ووقع عليه'),
    /* **أمرُ الدفعِ المستقبليّ** */
    array('fin_payment_order', 'draft', 'requested', 1, 'محاسب التمويل',
          'طالب ومبلغ موجب وعملة وتاريخ طلب', 'طلب امر دفع', 'مدير التمويل',
          'يفتح بالغاء الطلب واعادته', 'التصحيح بطلب جديد يحمل مرجع السابق', ''),
    array('fin_payment_order', 'requested', 'approved', 1, 'المخول بسقفه',
          'المعتمد لا يتجاوز المطلوب ومن طلبه لا يعتمده',
          'امر دفع معتمد', 'بوابة فصل الواجبات',
          'يفتح بالغاء الاعتماد بسبب مكتوب قبل التنفيذ',
          'التصحيح بالغاء ثم طلب جديد لا بتعديل المعتمد', ''),
    array('fin_payment_order', 'approved', 'executed', 1, 'أمين الخزينة',
          'مرجع بنكي وطريقة سداد ومبلغ منفذ وطلب اعتراف صادر الى المالية',
          'اشعار تنفيذ بمرجعه البنكي', 'بوابة الاعتراف عند المالية',
          'لا يفتح - الاسترداد بامر مستقل',
          'التصحيح بامر استرداد يحمل مرجع المنفذ لا بمحو التنفيذ', ''),
    array('fin_payment_order', 'requested', 'rejected', 1, 'المخول بسقفه',
          'سبب رد مكتوب', 'قرار رد امر دفع', 'مدير التمويل',
          'يفتح بطلب جديد', 'التصحيح باستيفاء ما نقص ثم طلب جديد', ''),
    array('fin_payment_order', 'requested', 'executed', 0, '', '', '', '', '', '',
          'تنفيذ امر غير معتمد يخرج نقدا بلا سلطة اذنت به'),
    array('fin_payment_order', 'executed', 'cancelled', 0, '', '', '', '', '', '',
          'الغاء منفذ يمحو خروج نقد وقع فعلا - والعكس بامر استرداد لا بالالغاء'),
    /* **الطبقةُ التاريخيّةُ المجمَّعة** — حالاتُها ليست حالاتِ أمرِ الدفع */
    array('fin_legacy_payment_aggregate', 'legacy', 'legacy', 1, 'هندسة النظم',
          'حجية ومرجع صف اصلي - والسطر يبقى تاريخيا ولا يترقى',
          'سطر ترحيل موسوم', 'مراجعة خريطة الترحيل',
          'لا يفتح - السطر التاريخي لقطة بحجيتها',
          'التصحيح بسطر ترحيل جديد يحمل مرجع السابق', ''),
    array('fin_legacy_payment_aggregate', 'legacy', 'requested', 0, '', '', '', '', '', '',
          'ترقية سطر تاريخي مجمع الى امر دفع تجعل تصميم المستقبل تابعا لمحدودية الماضي - وهي ما تمنعه هذه المرحلة'),
);
$stN = 0; $stFail = 0;
foreach ($ST as $s) {
    if ($W("INSERT INTO repair01_w12_states
            (entity,from_state,to_state,allowed,owner_role,preconditions,output_doc,approval_gate,
             reopen_rule,correct_rule,forbid_why,src_ref)
            VALUES ('" . $esc($s[0]) . "','" . $esc($s[1]) . "','" . $esc($s[2]) . "'," . (int) $s[3] . ",
                    '" . $esc($s[4]) . "','" . $esc($s[5]) . "','" . $esc($s[6]) . "','" . $esc($s[7]) . "',
                    '" . $esc($s[8]) . "','" . $esc($s[9]) . "','" . $esc($s[10]) . "','RPR-W12 §٦')")) { $stN++; }
    else { $stFail++; }
}
printf("  انتقالاتٌ مسجَّلة %d · ممنوعٌ صراحةً %d · كياناتٌ %d · فشل %d\n\n",
    $stN, (int) $one("SELECT COUNT(*) FROM repair01_w12_states WHERE allowed = 0"),
    (int) $one("SELECT COUNT(DISTINCT entity) FROM repair01_w12_states"), $stFail);

/* ═══════════════════════════════════════════════════════════════════════════
   ⑦ فصلُ الواجبات — بستّةِ أدوارٍ وتركيبةٍ ممنوعةٍ ورمزِ ردٍّ يُنفِّذها
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑦ فصلُ الواجبات ─────────────────────────────────────────────\n";
$W("DELETE FROM repair01_w12_sod");
$SOD = array(
    array('fin.need.approve', 'اعتماد الحاجة التمويلية', 'الادارة الطالبة', 'محاسب التمويل',
          'مدير التمويل', 'محاسب التمويل', 'مدير التمويل',
          'من رفع الحاجة لا يعتمدها', 'SAME_ACTOR_RAISE_AND_APPROVE_NEED', 'AAM-FIN-01',
          'نائب مدير التمويل', 'ضمن الكيان القانوني وحده', 'بتفويض مكتوب ومؤقت'),
    array('fin.contract.sign', 'توقيع عقد التمويل', 'محاسب التمويل', 'القانوني',
          'المخول بسقفه', 'محاسب التمويل', 'مدير التمويل',
          'من اعد العقد لا يوقعه', 'SAME_ACTOR_PREPARE_AND_SIGN', 'AAM-FIN-02',
          'نائب المخول بسقفه', 'ضمن سقف الصلاحية المعلن', 'لا تفويض فوق السقف'),
    array('fin.order.approve', 'اعتماد امر الدفع', 'محاسب التمويل', 'مدير التمويل',
          'المخول بسقفه', 'أمين الخزينة', 'مدير التمويل',
          'من طلب امر الدفع لا يعتمده', 'SAME_ACTOR_REQUEST_AND_APPROVE_ORDER', 'AAM-FIN-03',
          'نائب المخول بسقفه', 'الممول المستفيد وحده', 'بتفويض مكتوب ومؤقت'),
    array('fin.order.execute', 'تنفيذ امر الدفع', 'محاسب التمويل', 'مدير التمويل',
          'المخول بسقفه', 'أمين الخزينة', 'مدير التمويل',
          'تنفيذ امر غير معتمد', 'EXECUTE_WITHOUT_APPROVED_ORDER', 'AAM-FIN-04',
          'نائب امين الخزينة', 'المبلغ المعتمد لا اكثر', 'بتفويض مكتوب ومؤقت'),
    array('fin.contract.close', 'اعتماد الاقفال التعاقدي', 'محاسب التمويل', 'مدير التمويل',
          'مدير التمويل', 'محاسب التمويل', 'مدير التمويل',
          'من اعد الاقفال التعاقدي لا يعتمده', 'SAME_ACTOR_PREPARE_AND_APPROVE_CLOSE', 'AAM-FIN-05',
          'نائب مدير التمويل', 'العملية وفترتها التعاقدية معا', 'بتفويض مكتوب ومؤقت'),
    array('fin.monthly.close', 'اعتماد الاقفال الشهري', 'محاسب التمويل', 'مدير التمويل',
          'مدير التمويل', 'محاسب التمويل', 'مدير التمويل',
          'من اعد الاقفال الشهري لا يعتمده', 'SAME_ACTOR_PREPARE_AND_APPROVE_MONTHLY', 'AAM-FIN-06',
          'نائب مدير التمويل', 'العملية والشهر التقويمي معا', 'بتفويض مكتوب ومؤقت'),
    array('fin.final.close', 'اعتماد الاقفال النهائي', 'محاسب التمويل', 'مدير التمويل',
          'المخول بسقفه', 'محاسب التمويل', 'المخول بسقفه',
          'من طلب الاقفال النهائي لا يعتمده', 'SAME_ACTOR_PREPARE_AND_APPROVE_FINAL', 'AAM-FIN-07',
          'نائب المخول بسقفه', 'العملية وحدها', 'لا تفويض في الاقفال النهائي'),
    array('fin.deviation.resolve', 'حسم انحراف التمويل', 'محاسب التمويل', 'مدير التمويل',
          'مدير التمويل', 'محاسب التمويل', 'مدير التمويل',
          'من رفع الانحراف لا يحسمه', 'SAME_ACTOR_RAISE_AND_RESOLVE_DEVIATION', 'AAM-FIN-08',
          'نائب مدير التمويل', 'الانحراف وموضوعه معا', 'بتفويض مكتوب ومؤقت'),
    array('fin.covenant.waive', 'التنازل عن التزام تمويلي', 'محاسب التمويل', 'القانوني',
          'المخول بسقفه', 'محاسب التمويل', 'مدير التمويل',
          'تنازل بلا قاعدة صلاحية ولا مستند من الممول', 'WAIVE_WITHOUT_AUTHORITY', 'AAM-FIN-09',
          'نائب المخول بسقفه', 'الالتزام المرصود اخلاله وحده', 'لا تفويض في التنازل'),
);
foreach ($SOD as $s) {
    $W("INSERT INTO repair01_w12_sod
        (process_key,process_name,initiator_role,reviewer_role,approver_role,executor_role,closer_role,
         forbidden_combo,enforced_by,authority_rule_id,deputy_role,scope_rule,delegation,effective_date,src_ref)
        VALUES ('" . $esc($s[0]) . "','" . $esc($s[1]) . "','" . $esc($s[2]) . "','" . $esc($s[3]) . "',
                '" . $esc($s[4]) . "','" . $esc($s[5]) . "','" . $esc($s[6]) . "','" . $esc($s[7]) . "',
                '" . $esc($s[8]) . "','" . $esc($s[9]) . "','" . $esc($s[10]) . "','" . $esc($s[11]) . "',
                '" . $esc($s[12]) . "','2026-08-26','RPR-W12 §٧')");
}
printf("  عملياتٌ حرِجة %d\n\n", count($SOD));

/* ═══════════════════════════════════════════════════════════════════════════
   ⑧ عقودُ الأثر — لكلِّ حدثٍ مستهلكونَ بالاسمِ لا «كلُّ المستهلكين»
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑧ عقودُ الأثر ────────────────────────────────────────────────\n";
$W("DELETE FROM repair01_events WHERE wave = 'W12'");
$EV = array(
    array('fin.contract.signed', 'توقيع عقد تمويل', 'fin_finance_contract',
          'Financing/fin_contracts.php',
          'توقيع عقد بمستنده بعد اجازة مراجعة ما قبل التعاقد',
          'contract_id · op_id · principal · currency',
          'FinancingCycleService::onContractSigned',
          'يربط العملية بعقدها فتقرا العملية سندها ولا تبقى بلا مستند',
          'مراجعة مجازة ومستند موقع ومن اعده لا يوقعه',
          'إعادة بلا أثر', 'w12:fin.contract.signed', 'يرفع ولا يبتلع',
          'التعديل بملحق مؤرخ يحمل مرجع الاصل'),
    array('fin.schedule.generated', 'توليد جدول الاقساط', 'financing_operations',
          'Financing/installments.php',
          'توليد جدول اقساط العملية بعد توقيع عقدها',
          'op_id · installments_no',
          'FinancingCycleService::onScheduleGenerated',
          'يعطي كل قسط فترته التعاقدية فيقرا القسط في اقفاله لا خارجه',
          'العقد موقع والعملية مفتوحة',
          'إعادة بلا أثر', 'w12:fin.schedule.generated', 'يرفع ولا يبتلع',
          'اعادة الجدولة نسخة مؤرخة لا دهس للجدول'),
    array('fin.order.approved', 'اعتماد امر دفع', 'fin_payment_order',
          'Financing/fin_payment_orders.php',
          'اعتماد امر دفع بمبلغ لا يتجاوز المطلوب',
          'order_id · approved_amount',
          'FinancingCycleService::onOrderApproved',
          'يفتح باب التنفيذ ويصفر حالة المطابقة فلا ينفذ امر غير معتمد',
          'من طلب لا يعتمد والمعتمد لا يتجاوز المطلوب',
          'إعادة بلا أثر', 'w12:fin.order.approved', 'يرفع ولا يبتلع',
          'الالغاء قبل التنفيذ بسبب مكتوب'),
    array('fin.order.executed', 'تنفيذ امر دفع', 'fin_payment_order',
          'Financing/fin_payment_orders.php',
          'تنفيذ امر معتمد بمرجع بنكي وطريقة سداد',
          'order_id · executed_amount · bank_ref · recognition_request_id',
          'FinancingCycleService::onOrderExecuted',
          'ينزل الرصيد القائم للعملية بقيمة المنفذ فيصير الرصيد مشتقا من الحركة',
          'الامر معتمد ومرجع بنكي وطلب اعتراف صادر الى المالية',
          'إعادة بلا أثر', 'w12:fin.order.executed', 'يرفع ولا يبتلع',
          'الاسترداد بامر مستقل يحمل مرجع المنفذ'),
    array('fin.payment.allocated', 'تخصيص سداد على اقساط', 'fin_payment_order',
          'Financing/fin_payment_allocation.php',
          'تخصيص المنفذ على قسط او اكثر',
          'order_id · lines',
          'FinancingCycleService::onPaymentAllocated',
          'يرفع المخصص على كل قسط ويقفله بتغطيته فتقرا الاقساط سدادها',
          'الامر منفذ ومجموع التخصيص لا يتجاوز المنفذ ولا تخصيص من صف مجمع',
          'إعادة بلا أثر', 'w12:fin.payment.allocated', 'يرفع ولا يبتلع',
          'عكس التخصيص بسطر مقابل لا بمحو السطر'),
    array('fin.contract.closed', 'اعتماد اقفال تعاقدي', 'fin_contract_close',
          'Financing/fin_contract_close.php',
          'اعتماد اقفال فترة تعاقدية بترحيل رصيد مثبت',
          'close_id · op_id · contract_period_no',
          'FinancingCycleService::onContractClosed',
          'يختم اقساط الفترة باقفالها فلا يقرا قسط في فترتين',
          'رقم فترة تعاقدية موجب وترحيل مثبت ومن اعده لا يعتمده',
          'إعادة بلا أثر', 'w12:fin.contract.closed', 'يرفع ولا يبتلع',
          'التصحيح باقفال تعاقدي لاحق يحمل الفرق'),
    array('fin.monthly.closed', 'اعتماد اقفال شهري', 'fin_monthly_close',
          'Financing/fin_monthly_close.php',
          'اعتماد اقفال شهر تقويمي ضم اقفالاته التعاقدية',
          'close_id · accounting_month · contract_closes_n',
          'FinancingCycleService::onMonthlyClosed',
          'يثبت عدد الاقفالات التعاقدية المضمومة فيقرا الشهر ضما لا كيانا بديلا',
          'شهر تقويمي كامل واقفال تعاقدي واحد فاكثر مربوط ومن اعده لا يعتمده',
          'إعادة بلا أثر', 'w12:fin.monthly.closed', 'يرفع ولا يبتلع',
          'التصحيح في اقفال الشهر التالي بمرجعه'),
    array('fin.final.closed', 'اعتماد اقفال نهائي', 'fin_final_close',
          'Financing/fin_final_close.php',
          'اعتماد اقفال نهائي بصفر استحقاق مفتوح وصفر انحراف حاجب واخلاء طرف',
          'close_id · op_id · clearance_doc_ref',
          'FinancingCycleService::onFinalClosed',
          'يقفل العملية ويربطها باقفالها النهائي فتقرا العملية ختامها',
          'صفر استحقاق مفتوح وصفر انحراف حاجب واخلاء طرف واخر اقفال دوري مرجعا',
          'إعادة بلا أثر', 'w12:fin.final.closed', 'يرفع ولا يبتلع',
          'التسوية اللاحقة بمستندها لا بقلب الاقفال'),
    array('fin.deviation.raised', 'رفع انحراف تمويل', 'financing_deviations',
          'Financing/deviations.php',
          'رصد انحراف في السداد او الملكية او التوثيق',
          'deviation_id · dev_type · subject_ref',
          'FinancingCycleService::onDeviationRaised',
          'يعد الانحرافات الحاجبة فيحجب الاقفال النهائي ما دامت مفتوحة',
          'موضوع الانحراف مكتوب ونوعه معلن',
          'إعادة بلا أثر', 'w12:fin.deviation.raised', 'يرفع ولا يبتلع',
          'الحسم بقرار مكتوب من غير من رفعه'),
    array('fin.ownership.transferred', 'انتقال ملكية عين ممولة', 'financing_operations',
          'Financing/asset_disposal.php',
          'انتقال ملكية او خروج عين ممولة بمستنده',
          'op_id · ownership_doc_ref',
          'FinancingCycleService::onOwnershipTransferred',
          'يوسم اكتمال نقل الملكية في الاقفال النهائي بمستنده فلا يقفل بلا حكم ملكية',
          'مستند ملكية مكتوب واقفال نهائي قائم للعملية',
          'إعادة بلا أثر', 'w12:fin.ownership.transferred', 'يرفع ولا يبتلع',
          'التصحيح بواقعة انتقال جديدة تحمل مرجع السابقة'),
);
foreach ($EV as $e) {
    $W("INSERT INTO repair01_events
        (event_code,name,wave,source_unit,source_screen,idempotency_key,consumers,effect_type,
         retry_policy,src_ref,trigger_rule,min_payload,consumer_list,consumer_effect,
         preconditions,failure_policy,compensation,contract_status,contract_rule,contract_stage)
        VALUES ('" . $esc($e[0]) . "','" . $esc($e[1]) . "','W12',
                '03 إدارة التمويل والممولين',
                '" . $esc($e[3]) . "','" . $esc($e[10]) . "','" . $esc($e[6]) . "',
                '" . $esc($e[7]) . "','" . $esc($e[9]) . "','RPR-W12 §٧',
                '" . $esc($e[4]) . "','" . $esc($e[5]) . "','" . $esc($e[6]) . "','" . $esc($e[7]) . "',
                '" . $esc($e[8]) . "','" . $esc($e[11]) . "','" . $esc($e[12]) . "',
                'RECORDED','W12_EVENT_CONTRACT','W12')");
}
printf("  عقودُ أثرٍ مكتوبة %d\n\n", count($EV));

/* ═══════════════════════════════════════════════════════════════════════════
   ⑨ سجلُّ استهلاكِ الإقفالات — **من يقرأ أيَّ صنفٍ ولأيِّ غرض**
   ═══════════════════════════════════════════════════════════════════════════
   ◆ **وغرضٌ واحدٌ يقرأ صنفَين هو عينُ «إقفالٍ يخدم معنيَين» عند القارئ** —
     فيُقاس من هذا السجلِّ لا من النيّة.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑨ سجلُّ استهلاكِ الإقفالات ──────────────────────────────────\n";
$W("DELETE FROM fin_close_consumption WHERE src_ref LIKE 'RPR-W12%'");
$CONSUME = array(
    array('w12.balance.capital', 'Financing/fin_capital_balance.php', 'CONTRACTUAL', 'capital_balance',
          'fin_contract_close', 'رصيد راس المال يشتق من الاقفال التعاقدي وحده - وقراءة الشهري هنا تخلط حبتين'),
    array('w12.statement.financier', 'Financing/fin_monthly_close.php', 'MONTHLY', 'financier_statement',
          'fin_monthly_close', 'كشف حساب الممول شهري تقويمي - ولا يقارن بفترة تعاقدية'),
    array('w12.close.final_gate', 'Financing/fin_final_close.php', 'FINAL', 'final_gate',
          'fin_final_close', 'بوابة الاقفال النهائي تقرا كيانها وحده وتشير الى اخر دوري بالربط'),
    array('w12.close.final_gate', 'Financing/fin_final_close.php', 'CONTRACTUAL', 'last_periodic_ref',
          'fin_contract_close', 'قراءة اخر اقفال دوري مرجعا - غرض مستقل عن بوابة الاقفال نفسها'),
    array('w12.audit.report', 'Financing/fin_close_audit.php', 'CONTRACTUAL', 'audit_contractual',
          'fin_contract_close', 'التقرير يقرا التعاقدي بغرضه ويعرضه صفا مستقلا'),
    array('w12.audit.report', 'Financing/fin_close_audit.php', 'MONTHLY', 'audit_monthly',
          'fin_monthly_close', 'التقرير يقرا الشهري بغرض مستقل ولا يجمعه مع التعاقدي في رقم واحد'),
    array('w12.audit.report', 'Financing/fin_close_audit.php', 'FINAL', 'audit_final',
          'fin_final_close', 'التقرير يقرا النهائي بغرض ثالث - ثلاثة اغراض لثلاثة اصناف'),
    array('w12.installment.seal', 'Financing/installments.php', 'CONTRACTUAL', 'installment_period',
          'fin_contract_close', 'القسط يختم بفترته التعاقدية لا بشهرها'),
    array('w12.allocation.target', 'Financing/fin_payment_allocation.php', 'CONTRACTUAL', 'allocation_target',
          'fin_contract_close', 'التخصيص يقع في الفترة التعاقدية التي يقع فيها القسط'),
);
$conN = 0;
foreach ($CONSUME as $c) {
    if ($W("INSERT INTO fin_close_consumption
            (consumer_key,consumer_surface,close_kind,purpose,read_table,why,src_ref)
            VALUES ('" . $esc($c[0]) . "','" . $esc($c[1]) . "','" . $esc($c[2]) . "','" . $esc($c[3]) . "',
                    '" . $esc($c[4]) . "','" . $esc($c[5]) . "','RPR-W12 §٩')
            ON DUPLICATE KEY UPDATE why = VALUES(why), read_table = VALUES(read_table)")) { $conN++; }
}
printf("  مستهلكو إقفالٍ مسجَّلون %d · غرضٌ يقرأ صنفَين %d\n\n",
    $conN, repair01_w12_consumer_dual_kind($conn));

/* ═══════════════════════════════════════════════════════════════════════════
   ⑩ دفترُ الطبقتَين — **قدرةُ المستقبلِ ومقابلُها في التاريخيّ**
   ═══════════════════════════════════════════════════════════════════════════
   ◆ كلُّ قدرةٍ تُعلَن: أهي إلزاميّةٌ في النموذج؟ وهل يستطيع التاريخيُّ
     توفيرَها؟ **وهل خُفِّض التصميمُ من أجلِه؟** والعمودُ الأخيرُ هو ما تطلب
     البوّابةُ أن يكون **صفرًا** — وقيدُ القاعدةِ يمنع إعلانَ تخفيضٍ على قدرةٍ
     إلزاميّةٍ أصلًا، فلا يُجمَّل الرقمُ بإعلانٍ متناقض.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑩ دفترُ الطبقتَين ───────────────────────────────────────────\n";
$W("DELETE FROM repair01_w12_layers");
$LAYERS = array(
    array('order_requester', 'هوية طالب امر الدفع', 'requested_by', 1, 0, 0,
          'الصف المجمع التاريخي لا يحمل طالبا لان الدفع وقع قبل النظام - والنموذج يلزمه ولا يخفض'),
    array('order_request_date', 'تاريخ طلب امر الدفع', 'requested_at', 1, 0, 0,
          'التاريخي يحمل فترة لا لحظة طلب - والنموذج يلزم اللحظة ولا يقنع بالفترة'),
    array('order_approver', 'معتمد امر الدفع', 'approved_by', 0, 0, 0,
          'الاعتماد الزامي عند حالتي معتمد ومنفذ بقيد chk_fpo_appr لا في كل صف - وهذا قيد حالة لا تخفيض'),
    array('order_amount', 'المبلغ المطلوب', 'requested_amount', 1, 0, 0,
          'التاريخي يحمل مجموعا لا مطلوبا لكل امر - والنموذج يفصل المطلوب عن المعتمد عن المنفذ'),
    array('order_currency', 'عملة امر الدفع', 'currency', 1, 1, 0,
          'العملة متوفرة في الطبقتين - ولا يقاس تخفيض حيث لا فرق'),
    array('order_bank_ref', 'المرجع البنكي للتنفيذ', 'bank_ref', 0, 0, 0,
          'الزامي عند التنفيذ بقيد chk_fpo_exec - والتاريخي لا يحمله فيبقى في طبقته ولا ينفذ من هنا'),
    array('order_allocation', 'التخصيص على الاقساط', 'id', 0, 0, 0,
          'التخصيص من امر لا من صف مجمع بقيد chk_fpa_order - والمجمع allocatable صفر بقيده'),
    array('order_recognition', 'طلب الاعتراف عند المالية', 'recognition_request_id', 0, 0, 0,
          'التنفيذ يصدر طلب اعتراف §48 - والتاريخي اعترف به قبل النظام فلا يعاد الاعتراف'),
    array('order_match_state', 'حالة مطابقة التنفيذ', 'match_state', 0, 0, 0,
          'المطابقة تخص الاوامر المنفذة - والتاريخي حجيته وسمه لا مطابقته'),
);
$layN = 0;
foreach ($LAYERS as $l) {
    if ($W("INSERT INTO repair01_w12_layers
            (capability_key,title_ar,future_column,future_required,legacy_can_supply,
             constrained_by_legacy,why,src_ref)
            VALUES ('" . $esc($l[0]) . "','" . $esc($l[1]) . "','" . $esc($l[2]) . "'," . (int) $l[3] . ",
                    " . (int) $l[4] . "," . (int) $l[5] . ",'" . $esc($l[6]) . "','RPR-W12 §١٠')")) { $layN++; }
}
printf("  قدراتٌ مسجَّلة %d · مقيَّدةٌ بالتاريخيِّ %d\n\n", $layN, repair01_w12_capability_downgraded($conn));

/* ═══════════════════════════════════════════════════════════════════════════
   ⑩-ب · نموُّ المرحلةِ يُعرَض على قاعدةِ W03 — «تسميةٌ بلا مفتاح»
   ═══════════════════════════════════════════════════════════════════════════
   ◆ **جدولٌ جديدٌ يحمل اسمَ إنسانٍ نصًّا يخلق مرشَّحًا لمعرِّفٍ بديل** — و`W3-03`
     يمسح المخطَّطَ الحيَّ كلَّه فيراه ويسقط لأنَّ دفترَه لا يعرفه. وهو **حاجبٌ
     يعمل لا حاجبٌ يُعطَّل**: نموُّ هذه المرحلةِ هو الذي دخل مداه.
   ◆ **والحكمُ من قاعدةِ W03 نفسِها لا من اجتهادٍ هنا**: يُستدعى ماسحُها
     (`repair01_w3_scan_aliases`) ويُشتقُّ الحكمُ بأرقامِه الثلاثةِ حرفًا كما
     تشتقُّه أداتُها — فقيمةٌ مكتوبةٌ بيدٍ تخالف المقيسَ يكذّبها `W3-03` نفسُه.
   ⛔ **ولا يُمَسُّ صفٌّ سجّلته W03 ولا تُشغَّل أداتُها من هنا** — والكتابةُ
     مقصورةٌ على جداولِ هذه المرحلةِ وحدَها بوسمِ `wave_stage = 'W12'`.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑩-ب نموُّ المرحلةِ على قاعدةِ W03 ──────────────────────────────\n";
$aliasN = 0; $aliasAlt = array();
if (is_file($ROOT . '/tools/lib/repair01_w3_scan.php')) {
    require_once $ROOT . '/tools/lib/repair01_w3_scan.php';
    $mine = array_flip(repair01_w12_entity_tables());
    foreach (repair01_w3_scan_aliases($conn) as $key => $rowsA) {
        foreach ($rowsA as $a) {
            if (!isset($mine[$a['table']])) { continue; }
            /* الحكمُ **مقيسٌ** — وقاعدتُه قاعدةُ W03 حرفًا */
            if ($a['rows'] > 0 && $a['rows_seed'] === $a['rows'] && $a['rows_resolvable'] === 0) {
                $verdict = 'SEED_NO_REFERENT'; $rule = 'MEASURED_ALL_SEED_ZERO_REFERENT';
                $why = 'كل صفوفه بذرة والنص لا يجد مرجعا في الجدول المالك';
            } elseif ($a['rows'] === 0) {
                $verdict = 'SEED_NO_REFERENT'; $rule = 'MEASURED_EMPTY_TABLE';
                $why = 'جدول فارغ — لا صف يعرف حقيقة أم بنص';
            } else {
                $verdict = 'ALTERNATE_ID'; $rule = 'MEASURED_LIVE_LABEL_NO_KEY';
                $why = 'صفوف حية تعرف الحقيقة الأم بنص ولا تحمل مفتاحها';
                $aliasAlt[] = $a['table'] . '.' . $a['column'];
            }
            $W("INSERT INTO repair01_key_alias
                (key_code,alias_table,alias_column,alias_kind,verdict,verdict_rule,verdict_why,
                 rows_total,rows_seed,rows_resolvable,link_column,rows_linked,resolved_at,wave_stage,src_ref)
                VALUES ('" . $esc($key) . "','" . $esc($a['table']) . "','" . $esc($a['column']) . "',
                        'LABEL_ONLY','" . $esc($verdict) . "','" . $esc($rule) . "','" . $esc($why) . "',
                        " . (int) $a['rows'] . "," . (int) $a['rows_seed'] . "," . (int) $a['rows_resolvable'] . ",
                        '',0,NULL,'W12','" . $esc('قياسٌ حيّ من ماسح W03: ' . $a['table'] . '.' . $a['column']) . "')
                ON DUPLICATE KEY UPDATE verdict=VALUES(verdict), verdict_rule=VALUES(verdict_rule),
                  rows_total=VALUES(rows_total), rows_seed=VALUES(rows_seed),
                  rows_resolvable=VALUES(rows_resolvable), wave_stage='W12'");
            $aliasN++;
        }
    }
}
printf("  مرشَّحاتُ نموِّ المرحلةِ مسجَّلةٌ بحكمِها المقيس %d · معرّفٌ بديلٌ حيٌّ %d%s\n\n",
    $aliasN, count($aliasAlt), $aliasAlt ? ' ⇐ ' . implode('، ', $aliasAlt) : '');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑪ قراراتُ المرحلة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑪ قراراتُ المرحلة ────────────────────────────────────────────\n";
$W("DELETE FROM repair01_w12_decisions");
$mismatchN = count($ownerMismatch);
$emptyN = (int) $one("SELECT COUNT(*) FROM fin_contract_close")
        + (int) $one("SELECT COUNT(*) FROM fin_monthly_close")
        + (int) $one("SELECT COUNT(*) FROM fin_final_close")
        + (int) $one("SELECT COUNT(*) FROM fin_payment_order");
$DEC = array(
    array('W12-D-01', 'هل الاقفالات الثلاثة حالات لكيان واحد ام ثلاثة كيانات',
          'ثلاثة كيانات في ثلاثة جداول بثلاث حبات وثلاثة مفاتيح فريدة',
          'التعاقدي حبته ممول وعملية وفترة تعاقدية والشهري حبته شهر تقويمي وعملة والنهائي حبته عملية '
          . 'مرة واحدة. وجعلها حالات لكيان واحد يجبر الثلاثة على مفتاح واحد فيضيع معنى اثنين منها. '
          . 'والفصل مفروض بقيد close_kind في كل جدول وبمفتاح فريد لكل حبة لا بتوثيق.', 3),
    array('W12-D-02', 'كيف يربط الشهري بالتعاقدي والنهائي بهما بلا دمج معانيها',
          'جدول ربط بازواج مسموحة ثلاثة والاب لا يكون من صنف ابنه',
          'الشهري يضم التعاقدي والنهائي يقرا اخر دوري - وكلاهما ربط لا احلال. '
          . 'ورابط من صنف الى صنفه نفسه هو عين اقفال يخدم معنيين فيرد في القاعدة بقيد chk_fcl_self.', 3),
    array('W12-D-03', 'اين تسكن الصفوف المجمعة التاريخية لاوامر الدفع',
          'في جدولها المستقل بطبقة LEGACY وحجية ومرجع صف اصلي وغير قابلة للتخصيص',
          'ادخالها في نموذج امر الدفع يجبر النموذج على قبول صف بلا طالب وبلا تاريخ طلب وبلا مرجع بنكي '
          . 'فتخفض اعمدته لتقبله - وهو عين تصميم مقيد ببيانات تاريخية. '
          . 'وقيدا chk_fpo_future و chk_flpa_layer يمنعان الاختلاط في القاعدة لا في المراجعة.', 0),
    array('W12-D-04', 'ماذا يفعل بسبع فجوات DEP-03 التي اسطحها قائمة باسماء اخرى',
          'تقيد موفاة في built_counterpart ولا يبنى لها توأم',
          'سابقتا W9-D-08 و W11-D-11 قضتا بان الفجوة اسم مستهدف لا اسم ملف ملزم. وثلاث منها '
          . 'تبويبات ?view= داخل شاشة الانحرافات. وبناؤها باسمائها يصنع سبعة توائم لا سبع قدرات. '
          . 'ولم يمس صف الشبح ولا on_disk فمقاما الاشباح والاساس لم يتحركا.', 7),
    array('W12-D-05', 'من اين تقرا عتبات النطاق',
          'من repair01_w12_thresholds ولا رقم مكتوب في خدمة ولا في شاشة',
          'مهلة الاقفال وايام التاخير وسماح التخصيص ارقام سياسة تتغير بقرار مالك - وكتابتها في '
          . 'الشيفرة تجعل تغيير السياسة تعديل شيفرة. وحاجب W12-19 يسقط على اي مقارنة صلبة في ادوات النطاق.', 7),
    array('W12-D-06', 'هل يكتب النطاق قيدا في دفتر المالية',
          'لا - يصدر طلب اعتراف الى acc_recognition_request والمالية تقرر وتثبت',
          'قاعدة §48. وباب الاعتراف بني في W11 بحارسين: CHECK يمنع ان يكون مصدر الطلب المالية نفسها '
          . 'ورمز رد يمنع الترحيل على طلب لم يقبل. وتنفيذ امر الدفع هنا يمر به ولا يلتف عليه.', 0),
    array('W12-D-07', 'ثلاثة سجلات بنيت ومقامها صفر اليوم فكيف تقاس',
          'خلاء معلن بقرار مرة واحدة - والرحلة تمارسها فعلا ثم تكنس اثرها',
          'بوابة تقارن صفرا بصفر تمر على تطابق لا شيء. فالخلاء يمر معلنا وحده وحاجبا W12-15 و W12-24 '
          . 'يسقطان بلا هذا الاعلان. والقبول النهائي في W15 برحلة موظف حقيقي لا ببذور.', 0),
    array('W12-D-08', 'ما موضع كل سطح من السايدبار',
          'من دورة التمويل: تاسيس ثم دورة ثم تعاقد ثم اصول ثم مالية واقفالات ثم حوكمة ثم مرجعيات',
          'الترتيب الابجدي يضع الاقفال النهائي قبل التعاقدي ويضع اوامر الدفع قبل الاقساط فيقرا '
          . 'المستخدم الدورة معكوسة. والموضع مكتوب في cycle_step و W12-07 يعيد اشتقاقه ويقارنه بالمخزن.', 28),
    array('W12-D-09', 'لماذا مرساة واحدة من ثمان وعشرين بلا كيان قانوني الزامي',
          'سجل الكيانات القانونية عالمي بالتصميم فلا يحمل company_id - والاعلان بعدده لا يدعى صفرا',
          'FIN-01 سجل الممولين مرساته legal_entities وهو سجل الكيانات نفسها: الممول كيان قانوني '
          . 'يشترك فيه اكثر من شركة في المجموعة ودوره في entity_roles. وحمله company_id يجعل لكل '
          . 'شركة نسخة من الممول نفسه فتتعدد هويته - وهو عين ما منعه درس persons في W03. '
          . 'فالقياس 27 من 28 صادق ولا يدعى 28 - و W12-11 يسقط ان تغير العدد بلا تحديث هذا القرار.', 1),
    array('W12-D-10', 'جدول المرحلة يحمل اسم انسان نصا فيدخل مدى W3-03 - فماذا يفعل',
          'يسجل في دفتر W03 بحكمه المقيس من ماسح W03 نفسه ولا تعدل قاعدة ولا يشغل اداة مرحلة مغلقة',
          'جهة اتصال الممول انسان خارجي لا صف له في employees - فلا مرجع يشار اليه، والحكم المقيس '
          . 'SEED_NO_REFERENT لا ALTERNATE_ID. و W3-03 مسح المخطط الحي كله فراى مرشحا لا يعرفه دفتره '
          . 'وسقط - وهو حاجب يعمل لا حاجب يعطل. وثلاثة طرق كانت امام هذا: تعديل قاعدة W03 وهو تخفيف '
          . 'حاجب مغلق، او اعادة تسمية العمود ليفلت من الكاشف وهو تهرب يخفي الحقيقة، او تسجيل المرشح '
          . 'بحكمه المقيس - وهو المتبع. والتسجيل يزيد ما تحرسه W03 ولا ينقصه: ان امتلات الجداول بصفوف '
          . 'تجد مرجعها انقلب الحكم الى ALTERNATE_ID و W3-03 يسقط عليه.', 0),
);
foreach ($DEC as $d) {
    $W("INSERT INTO repair01_w12_decisions (decision_id,question,answer,rationale,scope_rows,decided_at,src_ref)
        VALUES ('" . $esc($d[0]) . "','" . $esc($d[1]) . "','" . $esc($d[2]) . "','" . $esc($d[3]) . "',
                " . (int) $d[4] . ",'2026-08-26','RPR-W12 §١١')");
}
/* **والخلاءُ يُعلَن بعددِه المقيسِ لحظتَه لا برقمٍ مكتوب** */
$W("UPDATE repair01_w12_decisions SET scope_rows = $emptyN WHERE decision_id = 'W12-D-07'");
$W("UPDATE repair01_w12_decisions SET scope_rows = " . count($unscoped) . "
     WHERE decision_id = 'W12-D-09'");
printf("  قراراتٌ مسجَّلة %d\n\n", count($DEC));

/* ═══════════════════════════════════════════════════════════════════════════
   ⑫ الإصلاحاتُ — كلٌّ بمتطلَّبِه الكاشف
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑫ الإصلاحات ─────────────────────────────────────────────────\n";
$W("DELETE FROM repair01_w12_fixes");
$FIX = array(
    array('w12.close.split_three', 'فصل الاقفالات الثلاثة الى ثلاثة جداول بثلاث حبات', 'FIN-15',
          'اقفال واحد بثلاثة معان', 'ثلاثة كيانات بثلاثة قيود صنف',
          'الدراسة تسمي ثلاثة معرفات FCON و Monthly_Close_ID و FOP - وكانت بلا جدول يفصلها'),
    array('w12.close.monthly_calendar', 'قيد الشهر التقويمي في القاعدة', 'FIN-16',
          'شهر بالنية', 'شهر بقيد chk_fmc_month',
          'فترة تعاقدية تدس في وعاء الشهر تجعل الشهري يخدم معنى التعاقدي ايضا'),
    array('w12.close.final_once', 'الاقفال النهائي مرة واحدة لعملية بمفتاح فريد', 'FIN-24',
          'تكرار ممكن', 'uq_ffin_grain يمنعه',
          'نهائي يتكرر يجعل شهادة الاقفال رقما لا واقعة'),
    array('w12.pay.layer_split', 'فصل نموذج امر الدفع عن الطبقة التاريخية المجمعة', 'FIN-17',
          'طبقة واحدة', 'طبقتان بقيدين',
          'الدراسة تعلن طبقة Legacy/Migration بصفوف مجمعة وحجية ومرجع صف - ودمجها يخفض النموذج'),
    array('w12.pay.alloc_from_order', 'التخصيص من امر دفع وحده لا من صف مجمع', 'FIN-18',
          'تخصيص من اي مصدر', 'chk_fpa_order و allocatable صفر',
          'تخصيص صف مجمع على قسط يوهم بسداد مفصل لا وجود له'),
    array('w12.pay.recognition_door', 'التنفيذ يصدر طلب اعتراف ولا يكتب قيدا', 'FIN-17',
          'لا باب معلن', 'EXECUTE_WITHOUT_RECOGNITION_REQUEST',
          'قاعدة §48 - وباب W11 الواحد يمنع ان يكتب نطاق قيده بيده'),
    array('w12.close.rollforward', 'اثبات ترحيل الرصيد بين الفترات', 'FIN-15',
          'اختبار ترحيل بلا حارس', 'ROLLFORWARD_BROKEN',
          'الدراسة تسمي اختبار الترحيل Opening=Closing السابق - وكان نصا بلا رد'),
    array('w12.gap.counterpart', 'سبع فجوات DEP-03 قيدت موفاة باسمائها القائمة', 'FIN-22',
          'سبع فجوات مفتوحة', 'سبع موفاة بلا توأم',
          'سابقتا W9-D-08 و W11-D-11 - والفجوة اسم مستهدف لا اسم ملف ملزم'),
    array('w12.nav.cycle_order', 'ترتيب اسطح النطاق من دورة التمويل لا من الابجدية', 'FIN-25',
          'ترتيب بلا مصدر', '28 سطحا بترتيب دورة',
          'الابجدية تضع النهائي قبل التعاقدي فيقرا المستخدم الدورة معكوسة'),
    array('w12.sod.nine_processes', 'تسع عمليات حرجة بفصل واجبات منفذ برمز رد', 'FIN-24',
          'فصل واجبات بالنص', 'تسعة رموز رد مقيسة من القرص',
          'التركيبة الممنوعة التي لا ينفذها رمز رد اعلان لا حكم'),
);
foreach ($FIX as $f) {
    $W("INSERT INTO repair01_w12_fixes (fix_key,title,revealed_by,before_num,after_num,why,src_ref)
        VALUES ('" . $esc($f[0]) . "','" . $esc($f[1]) . "','" . $esc($f[2]) . "','" . $esc($f[3]) . "',
                '" . $esc($f[4]) . "','" . $esc($f[5]) . "','RPR-W12 §١٢')");
}
printf("  إصلاحاتٌ بمتطلَّبِها الكاشف %d\n\n", count($FIX));

/* ═══════════════════════════════════════════════════════════════════════════
   الخلاصة
   ═══════════════════════════════════════════════════════════════════════════ */
echo str_repeat('─', 78) . "\n";
printf("إقفالٌ واحدٌ يخدم معنيَين %d · تصميمٌ مقيَّدٌ ببياناتٍ تاريخية %d\n",
    repair01_w12_close_dual_role($conn), repair01_w12_design_constrained($conn));
printf("نطاقٌ %d · أسطحُ نموٍّ %d · سايدبار %d · حالات %d · فصلُ واجبات %d · عقودُ أثر %d\n",
    (int) $one("SELECT COUNT(*) FROM repair01_w12_scope"), $newN, $sbN,
    (int) $one("SELECT COUNT(*) FROM repair01_w12_states"),
    (int) $one("SELECT COUNT(*) FROM repair01_w12_sod"),
    (int) $one("SELECT COUNT(*) FROM repair01_events WHERE wave = 'W12'"));
echo "الحكم: كُتب ✔\n";
exit(0);
