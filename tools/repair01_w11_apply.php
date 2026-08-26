<?php
/**
 * tools/repair01_w11_apply.php — قياسٌ وكتابةٌ للمرحلةِ الحاديةَ عشرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **السايدبارُ قبل الشاشات** (§٤ · RPR-PATCH-01 ③): الخطواتُ السبعُ بترتيبها
 *   على أسطحِ النطاقِ — وأسطحُ النموِّ تُسجَّل أوّلًا لأنّها جزءٌ من مقامِه.
 *
 * ⛔ `origin` = `W11` بالضبط (RPR-PATCH-02): أساسُ السجلِّ (٦٥١) مُجمَّدٌ،
 *   والنموُّ مسموحٌ **مختومًا وحدَه**.
 *
 * ◆ **والترتيبُ من دورةِ العملِ لا من الأبجديّة** (§٤-٤): `sort_no` يُشتَقُّ من
 *   `step` — موضعِ السطحِ من الدورةِ المحاسبيّةِ في §23 — لا من اسمِ الملفِّ
 *   ولا من تاريخِ الإنشاء. و⛔ **لا مصفوفةَ بنودٍ مكتوبةٌ في صفحة**.
 *
 * التشغيل: php tools/repair01_w11_apply.php [--report] [--revert]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w11_scan.php';
require_once $ROOT . '/app/Services/Ui/UiLabelRegistry.php';
require_once $ROOT . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("تعذّر الاتصال بالقاعدة\n"); }
$conn->set_charset('utf8mb4');
while (ob_get_level()) { ob_end_clean(); }

$REPORT = in_array('--report', $argv, true);
$REVERT = in_array('--revert', $argv, true);
$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) { return repair01_w11_one($conn, $sql); };
$W = function ($sql) use ($conn, $REPORT) {
    if ($REPORT) { return true; }
    if ($conn->query($sql) === true) { return true; }
    echo '  ✘ ' . $conn->error . "\n  ⇐ " . mb_substr(preg_replace('/\s+/', ' ', $sql), 0, 180) . "\n";
    return false;
};

echo "══ REPAIR01 · W11 — " . ($REVERT ? 'إرجاع' : ($REPORT ? 'قياسٌ بلا كتابة' : 'قياسٌ وكتابة')) . " ══\n\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ⓪ الإرجاع — يُفرِّغ ما كتبته هذه الأداةُ وحدَها
   ═══════════════════════════════════════════════════════════════════════════ */
if ($REVERT) {
    foreach (repair01_w11_new_surfaces() as $s) {
        $rt = $esc($s['route']);
        $conn->query("DELETE FROM nav_items WHERE route = '$rt'");
        $conn->query("DELETE FROM nav_canonical WHERE route = '$rt'");
        $conn->query("DELETE FROM role_permissions WHERE module_id IN (SELECT id FROM modules WHERE code = '$rt')");
        $conn->query("DELETE FROM modules WHERE code = '$rt'");
        $conn->query("DELETE FROM repair01_screen_registry WHERE route = '$rt' AND origin = 'W11'");
        $conn->query("DELETE FROM gov_screen_cycle WHERE screen_file = '" . $esc(basename($s['route'])) . "' AND inputs_note LIKE 'RPR-W11 %'");
    }
    $conn->query("DELETE FROM link_groups WHERE group_code LIKE 'n9o_w11\\_%'");
    foreach (array('repair01_w11_scope', 'repair01_w11_sidebar', 'repair01_w11_decisions',
                   'repair01_w11_states', 'repair01_w11_sod', 'repair01_w11_thresholds',
                   'repair01_w11_fixes', 'repair01_w11_journey', 'repair01_w11_consolidated') as $t) {
        $conn->query("DELETE FROM `$t`");
    }
    $conn->query("DELETE FROM repair01_events WHERE wave = 'W11'");
    $conn->query("DELETE FROM repair01_w6_code_dict WHERE src_ref LIKE 'RPR-W11%'");
    echo "الحكم: رجعت ✔ (والجداولُ تُنزع بهجرةِ التراجع)\n";
    exit(0);
}

/* ═══════════════════════════════════════════════════════════════════════════
   ① قاموسُ الرموزِ — الرمزُ يبقى لاتينيًّا ويُعرَض عربيًّا
   ═══════════════════════════════════════════════════════════════════════════
   ◆ **يُبذَر قبل تسجيلِ الأسطح**: السطحُ يعرض حالتَه من القاموسِ لحظةَ فتحِه،
     ورمزٌ بلا مسمًّى يُعرَض خامًّا فيسقط `W6-09`.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "① قاموسُ رموزِ النطاق ─────────────────────────────────────────\n";
$conn->query("DELETE FROM repair01_w6_code_dict WHERE src_ref LIKE 'RPR-W11%'");
$DICT = array(
    /* حالاتُ طلبِ الاعترافِ والقيدِ والتسوية */
    array('pending', 'قيد الدراسة', 'W11_STATE'), array('accepted', 'مقبول', 'W11_STATE'),
    array('rejected', 'مردود', 'W11_STATE'), array('posted', 'مرحل', 'W11_STATE'),
    array('reversed', 'معكوس', 'W11_STATE'), array('applied', 'مطبق', 'W11_STATE'),
    array('reclosed', 'اعيد اقفاله', 'W11_STATE'), array('approved', 'معتمد', 'W11_STATE'),
    array('open', 'مفتوح', 'W11_STATE'), array('reviewed', 'مراجع', 'W11_STATE'),
    array('closed', 'مقفل', 'W11_STATE'), array('settled', 'مسوى', 'W11_STATE'),
    array('executed', 'منفذ', 'W11_STATE'), array('cancelled', 'ملغى', 'W11_STATE'),
    array('resolved', 'مغلق', 'W11_STATE'),
    /* حالاتُ الأدواتِ الماليّة */
    array('received', 'مستلمة', 'W11_INSTRUMENT'), array('deposited', 'مودعة', 'W11_INSTRUMENT'),
    array('collected', 'محصلة', 'W11_INSTRUMENT'), array('bounced', 'مرتجعة', 'W11_INSTRUMENT'),
    array('returned', 'معادة', 'W11_INSTRUMENT'), array('handed', 'مسلمة', 'W11_INSTRUMENT'),
    array('cheque_in', 'شيك وارد', 'W11_INSTRUMENT'), array('cheque_out', 'شيك صادر', 'W11_INSTRUMENT'),
    array('promissory', 'كمبيالة', 'W11_INSTRUMENT'),
    /* أنواعُ التسوية */
    array('accrual', 'استحقاق', 'W11_ADJ'), array('prepaid', 'مصروف مقدم', 'W11_ADJ'),
    array('provision', 'مخصص', 'W11_ADJ'),
    /* أوعيةُ الخزينة */
    array('bank', 'حساب بنكي', 'W11_VESSEL'), array('cash_box', 'صندوق نقدي', 'W11_VESSEL'),
    /* بنودُ قائمةِ الإقفال */
    array('reconcile_bank', 'مطابقة البنك', 'W11_CLOSING'),
    array('reconcile_ar', 'مطابقة ذمم العملاء', 'W11_CLOSING'),
    array('reconcile_ap', 'مطابقة ذمم الموردين', 'W11_CLOSING'),
    array('post_accruals', 'ترحيل التسويات', 'W11_CLOSING'),
    array('post_depreciation', 'ترحيل الاهلاك', 'W11_CLOSING'),
    array('settle_supplier', 'تسوية الموردين', 'W11_CLOSING'),
    array('payroll_posted', 'ترحيل الرواتب', 'W11_CLOSING'),
    array('variance_reviewed', 'مراجعة الفروق', 'W11_CLOSING'),
    array('intercompany_settled', 'تسوية ما بين الكيانات', 'W11_CLOSING'),
    array('reports_issued', 'اصدار التقارير', 'W11_CLOSING'),
    array('done', 'مكتمل', 'W11_CLOSING'), array('na', 'لا ينطبق', 'W11_CLOSING'),
    /* بوّاباتُ بندِ الاستحقاق */
    array('three_way_match', 'مطابقة ثلاثية', 'W11_GATE'),
    array('contract_closure', 'اقفال تعاقدي', 'W11_GATE'),
    /* أنواعُ فروقِ المطابقة */
    array('timing', 'فرق توقيت', 'W11_DIFF'), array('bank_error', 'خطا بنكي', 'W11_DIFF'),
    array('book_error', 'خطا دفتري', 'W11_DIFF'), array('missing_entry', 'قيد ناقص', 'W11_DIFF'),
    array('fx', 'فرق صرف', 'W11_DIFF'), array('error', 'خطا', 'W11_DIFF'),
    array('missing', 'ناقص', 'W11_DIFF'), array('other', 'اخرى', 'W11_DIFF'),
    /* طلباتُ الصرفِ الواردةُ من الإدارات */
    array('purchase', 'شراء', 'W11_REQUEST'), array('disbursement', 'صرف', 'W11_REQUEST'),
    array('advance', 'دفعة مقدمة', 'W11_REQUEST'), array('supplier_payment', 'دفع مورد', 'W11_REQUEST'),
    array('employee_payment', 'دفع موظف', 'W11_REQUEST'), array('transfer', 'تحويل', 'W11_REQUEST'),
    array('settlement', 'تسوية', 'W11_REQUEST'), array('refund', 'استرداد', 'W11_REQUEST'),
    array('discount', 'خصم', 'W11_REQUEST'), array('collection', 'تحصيل', 'W11_REQUEST'),
    array('under_review', 'قيد المراجعة', 'W11_STATE'),
    array('pending_approval', 'بانتظار الاعتماد', 'W11_STATE'),
    array('paid', 'مدفوع', 'W11_STATE'), array('archived', 'مؤرشف', 'W11_STATE'),
    array('withdrawn', 'مسحوب', 'W11_STATE'), array('suspended', 'موقوف', 'W11_STATE'),
    array('expired', 'منتهي', 'W11_STATE'), array('merged', 'مدموج', 'W11_STATE'),
    array('draft', 'مسودة', 'W11_STATE'), array('claimed', 'مطالب به', 'W11_STATE'),
    array('returned_req', 'معاد', 'W11_STATE'),
    /* أهدافُ التخصيصِ وأساسُه */
    array('invoice', 'فاتورة', 'W11_TARGET'), array('milestone', 'مرحلة', 'W11_TARGET'),
    array('retention', 'محتجز', 'W11_TARGET'), array('final', 'مستخلص ختامي', 'W11_TARGET'),
    array('explicit', 'تخصيص صريح', 'W11_BASIS'), array('oldest_first', 'الاقدم اولا', 'W11_BASIS'),
    /* الجهاتُ المالكةُ لمركزِ التكلفة */
    array('sales', 'المبيعات', 'W11_OWNER'), array('suppliers', 'الموردون', 'W11_OWNER'),
    array('workforce', 'القوى العاملة', 'W11_OWNER'), array('procurement', 'المشتريات', 'W11_OWNER'),
    array('warehouse', 'المخازن', 'W11_OWNER'), array('maintenance', 'الصيانة', 'W11_OWNER'),
    array('projects', 'المشاريع', 'W11_OWNER'), array('revenue', 'الايرادات', 'W11_OWNER'),
    array('assets', 'الاصول', 'W11_OWNER'), array('treasury', 'الخزينة', 'W11_OWNER'),
    array('general', 'عام', 'W11_OWNER'),
);
$dictN = 0;
foreach ($DICT as $d) {
    if ($W("INSERT INTO repair01_w6_code_dict
            (raw_code, display_ar, display_short, code_family, allowed_context, why, src_ref)
            VALUES ('" . $esc($d[0]) . "','" . $esc($d[1]) . "','" . $esc($d[1]) . "',
                    '" . $esc($d[2]) . "','SCREEN_CELL',
                    'قيمة تقارن في الشيفرة وفي CHECK فتبقى لاتينية وتعرض عربية من القاموس',
                    'RPR-W11 §١')
            ON DUPLICATE KEY UPDATE display_ar = VALUES(display_ar)")) { $dictN++; }
}
printf("  رموزٌ مسجَّلةٌ للعرض %d\n\n", $dictN);

/* ═══════════════════════════════════════════════════════════════════════════
   ② أسطحُ النموِّ — ثمانيةَ عشرَ سطحًا مختومةً بموجتِها
   ═══════════════════════════════════════════════════════════════════════════ */
echo "② أسطحُ النموِّ — مختومةٌ بـW11 ─────────────────────────────────\n";
$newN = 0; $navN = 0; $permN = 0; $labelN = 0; $fillN = 0; $missing = array();
$maxSid = (int) preg_replace('/\D/', '', (string) $one("SELECT screen_id FROM repair01_screen_registry
                                                          ORDER BY screen_id DESC LIMIT 1"));
foreach (repair01_w11_new_surfaces() as $s) {
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

    /* ⓒ **المسمّى يُسجَّل قبل أن يُصيَّر** (‏W06) — واسمٌ مشكولٌ أو تقنيٌّ يُردُّ */
    if (!$REPORT) {
        $lr = \App\Services\Ui\UiLabelRegistry::register($conn, 'screen:' . strtolower($s['route']), $s['ar'], array(
            'allowed_context' => 'SIDEBAR SCREEN_TITLE',
            'source_table' => 'nav_canonical', 'source_column' => 'canonical_ar',
            'source_key' => $s['route'], 'owner_code' => $s['owner'],
            'visibility_class' => 'USER_VISIBLE', 'label_state' => 'ACTIVE',
            'rule_id' => 'W11_NEW_SURFACE_LABEL', 'origin' => 'W11',
            'src_ref' => 'RPR-W11 §٤ · سطحُ نموٍّ مختوم', 'caller' => 'repair01_w11_apply.php',
        ));
        if (!$lr['ok']) { echo '  ⚠ رُدَّ مسمّى ' . $s['route'] . ' — ' . $lr['code'] . ': ' . $lr['detail'] . "\n"; }
        else { $labelN++; }
        $gr = \App\Services\Ui\UiLabelRegistry::register($conn, 'group:w11:' . strtolower($s['group']), $s['group'], array(
            'allowed_context' => 'SIDEBAR', 'source_table' => 'nav_canonical', 'source_column' => 'group_name',
            'source_key' => $s['group'], 'owner_code' => $s['owner'],
            'visibility_class' => 'USER_VISIBLE', 'label_state' => 'ACTIVE',
            'rule_id' => 'W11_CYCLE_GROUP_LABEL', 'origin' => 'W11',
            'src_ref' => 'RPR-W11 §٤ · مجموعةُ دورةِ العمل', 'caller' => 'repair01_w11_apply.php',
        ));
        if ($gr['ok']) { $labelN++; }
    }

    /* ⓓ السجلُّ المعياريُّ للتنقُّل — والترتيبُ من موضعِ السطحِ في الدورة
       ⚠ **والسطحُ المستهدَفُ المُعلَنُ يُملأ ولا يُتوأَم**: `tre_board.php` كان
         **شبحًا مُعلَنًا** في السجلِّ (`SCR-0344` · `origin=SURFACES` · بلا مسار).
         وإنشاءُ مُعرِّفٍ جديدٍ له يترك صفَّين لملفٍّ واحد: شبحًا يُنتظر بناؤه
         وبناءً لا يعرف أنَّه هو. فيُبحَث أوّلًا باسمِ الملفِّ عن صفٍّ مُعلَنٍ
         غيرِ مبنيٍّ — ويُملأ بختمِه الأصليِّ محفوظًا (‏الأساسُ عددُه لا يتغيّر). */
    $sid = (string) $one("SELECT screen_id FROM repair01_screen_registry WHERE route = '$rt' LIMIT 1");
    if ($sid === '') {
        $sid = (string) $one("SELECT screen_id FROM repair01_screen_registry
                               WHERE screen_file = '" . $esc($file) . "'
                                 AND (route IS NULL OR route = '') ORDER BY screen_id LIMIT 1");
    }
    /* **والحكمُ بأصلِ الصفِّ لا بطريقةِ إيجادِه**: صفٌّ ختمُه من الأساسِ الثلاثيِّ
       سطحٌ **مستهدَفٌ مُعلَنٌ** يُملأ ويُحفَظ ختمُه؛ وما عداه نموٌّ يُختَم بموجتِه.
       ولو عُلِّق الحكمُ على «وُجد بالمسار أم بالاسم» لانقلب في التشغيلِ الثاني. */
    $filled = false;
    if ($sid !== '') {
        $og = (string) $one("SELECT origin FROM repair01_screen_registry
                              WHERE screen_id = '" . $esc($sid) . "' LIMIT 1");
        $filled = in_array($og, array('SURFACES', 'DISK', 'NAV'), true);
    }
    if ($sid === '') { $maxSid++; $sid = 'SCR-' . str_pad((string) $maxSid, 4, '0', STR_PAD_LEFT); }
    if ($filled) {
        $g0 = repair01_w11_guard_of($ROOT, $s['route']);
        $W("UPDATE repair01_screen_registry
               SET route = '$rt', on_disk = 1, guard_kind = '" . $esc($g0['kind']) . "',
                   guard_evidence = '" . $esc($g0['evidence']) . "',
                   lifecycle = 'LIVE_UNREGISTERED', visibility_class = 'MENU_ITEM',
                   ghost_verdict = 'BUILT_BY_W11',
                   ghost_why = 'سطح مستهدف معلن بني في المرحلة الحادية عشرة فملئ صفه ولا يتوأم',
                   src_ref = 'RPR-W11 · ملء سطح مستهدف معلن'
             WHERE screen_id = '" . $esc($sid) . "'");
        /* **والقياسُ المخزَّنُ يتبع الحيَّ** — `on_disk` قياسٌ لا حكم.
           ⚠ **والمسارُ يُكتب معه**: `W2-01` يبني مقامَه من `disk_path` لا من
             `screen_file`، فرفعُ العلمِ وحدَه يُدخل مفتاحًا فارغًا في المقام
             فيسقط الحاجبُ بشاشةٍ «ناقصة» لا وجودَ لها. */
        $W("UPDATE repair01_surfaces SET on_disk = 1, disk_path = '$rt'
             WHERE screen_file = '" . $esc($file) . "' AND on_disk = 0");
        echo "  ✔ سطحٌ مستهدَفٌ مُعلَنٌ مُلئ: $sid ⇐ " . $s['route'] . "\n";
    }
    $W("INSERT INTO nav_canonical (route, canonical_ar, level_no, level_name, group_name, sort_no,
                                   status, decision_state, application_state, decision_source,
                                   derivation, retirement_status, screen_id)
        VALUES ('$rt','" . $esc($s['ar']) . "',2,'العمليات','" . $esc($s['group']) . "'," . (int) $s['sort'] . ",
                'APPROVED','APPROVED','DEPLOYED','RPR-W11 · دفاتر الكيانات (2026-08-26)',
                'ترتيب الدورة المحاسبية في الحزمة','ACTIVE','" . $esc($sid) . "')
        ON DUPLICATE KEY UPDATE canonical_ar=VALUES(canonical_ar), group_name=VALUES(group_name),
          sort_no=VALUES(sort_no), status=VALUES(status), screen_id=VALUES(screen_id)");

    /* ⓔ **مجموعةُ الدورةِ لا مجموعةُ الشقيق** — والمُعرِّفُ من `screen_id`
         لا من المسار: `link_groups.group_code` عرضُه أربعون محرفًا، والاشتقاقُ
         من المسارِ يبتُر ويتصادم (‏عطبُ W07 المقيس). */
    if ($modId > 0) {
        $gkey = 'n9o_w11_' . strtolower(str_replace('-', '', $sid));
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
                    VALUES ('" . $esc($s['group']) . "','" . $esc($code) . "',$rid,'" . $esc($s['icon']) . "',
                            " . ((int) $sx['display_order'] + 1) . "," . (int) $sx['stage_no'] . ",
                            '" . $esc((string) $sx['stage_title']) . "',1)");
                $gid = (int) $one("SELECT id FROM link_groups WHERE group_code = '" . $esc($code) . "' LIMIT 1");
            } else {
                $W("UPDATE link_groups SET name = '" . $esc($s['group']) . "', is_active = 1,
                        stage_no = " . (int) $sx['stage_no'] . " WHERE id = $gid");
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
       ⚠ **والإدراجُ يُسبَق بكنسِ ما كتبته هذه الأداةُ وحدَها**: `gov_screen_cycle`
         بلا مفتاحٍ فريدٍ على اسمِ الملفّ، فإعادةُ التشغيلِ تضاعف الصفوفَ صامتةً
         (‏المقيس: أربعةُ صفوفٍ لكلِّ سطحٍ بعد أربعِ جولات). والكنسُ **بوسمِ
         الموجةِ في `inputs_note`** لا باسمِ الملفِّ وحدَه — وإلّا مُحي صفٌّ
         مُعلَنٌ سابقٌ لملفٍّ بُني الآن (‏وهو ما وقع مع `tre_board.php`). */
    if ($modId > 0) {
        $deptAr = (string) $one("SELECT legacy_name FROM repair01_dept_crosswalk
                                  WHERE canonical_code = '" . $esc($s['owner']) . "' ORDER BY id LIMIT 1");
        if ($deptAr === '') { echo '  ⚠ لا جسرَ مسمًّى للإدارة ' . $s['owner'] . " — الصفُّ لا يُكتب\n"; }
        else {
            $W("DELETE FROM gov_screen_cycle
                 WHERE screen_file = '" . $esc($file) . "' AND inputs_note LIKE 'RPR-W11 %'");
            $W("INSERT INTO gov_screen_cycle
                (company_id, dept_name, layer_name, stage_order, stage_name, group_name, screen_title,
                 screen_file, inputs_note, output_doc, resp_role, next_state, consumers, fin_impact, stage_kind)
                VALUES (0,'" . $esc($deptAr) . "','" . $esc($s['group']) . "','" . (int) $s['sort'] . "',
                        '" . $esc($s['group']) . "','" . $esc($s['group']) . "',
                        '" . $esc($s['ar']) . "','" . $esc($file) . "',
                        '" . $esc('RPR-W11 · متطلبات: ' . $s['req']) . "','" . $esc($s['doc']) . "',
                        '" . $esc($s['role']) . "','" . $esc($s['next']) . "','" . $esc($s['cons']) . "',
                        '" . $esc($s['fin']) . "','canonical')");
        }
    }

    /* ⓖ سجلُّ الشاشاتِ — بختمِ الموجةِ لا بلا ختم
       ⛔ **والسطحُ المملوءُ لا يُختَم**: صفُّه صفُّ أساسٍ مُعلَنٌ في الدراسةِ
         (‏`origin=SURFACES`)، وإعادةُ ختمِه بـ`W11` **تحوّل أساسًا إلى نموّ**
         فينقص مقامُ الأساسِ ويسقط `W3-14` و`W4-15` معًا. البناءُ ملأ ما أُعلن،
         ولم يخترع سطحًا جديدًا — والفرقُ بينهما هو ما يحرسه الحاجب. */
    if ($filled) { $newN++; $fillN++; continue; }
    $guard = repair01_w11_guard_of($ROOT, $s['route']);
    $W("INSERT INTO repair01_screen_registry
        (screen_id, screen_file, route, route_rule, owner_code, owner_role, owner_rule,
         lifecycle, lifecycle_rule, parent_screen_id, parent_rule, visibility_class, visibility_rule,
         on_disk, origin, guard_kind, guard_evidence, w2_why, src_ref)
        VALUES ('" . $esc($sid) . "','" . $esc($file) . "','$rt','W11_NEW_SURFACE_ROUTE',
                '" . $esc($s['owner']) . "','" . $esc($s['role']) . "','W11_REQUIREMENT_OWNER',
                'LIVE_UNREGISTERED','W11_GROWTH_OUTSIDE_STUDY_MATRIX','','','MENU_ITEM','NAV_ITEMS_ACTIVE',
                1,'W11','" . $esc($guard['kind']) . "','" . $esc($guard['evidence']) . "',
                '" . $esc($s['ar']) . " (" . $esc($file) . ")','RPR-W11 · دفاتر الكيانات')
        ON DUPLICATE KEY UPDATE owner_code=VALUES(owner_code), owner_role=VALUES(owner_role),
          visibility_class=VALUES(visibility_class), guard_kind=VALUES(guard_kind),
          guard_evidence=VALUES(guard_evidence), origin='W11', on_disk=1");
    $newN++;
}
printf("  أسطحٌ مبنيّة %d (‏نموٌّ مختومٌ %d · مستهدَفٌ مُعلَنٌ مُلئ %d) · بنودُ قائمةٍ نشِطة %d"
     . " · منحٌ %d · مسمّياتٌ مسجَّلة %d · بلا ملفٍّ %d%s\n\n",
    $newN, $newN - $fillN, $fillN, $navN, $permN, $labelN, count($missing),
    $missing ? ' ⇐ ' . implode('، ', $missing) : '');

/* ═══════════════════════════════════════════════════════════════════════════
   ②-ب · مصفوفةُ الواجهة — **السطحُ المُصيَّرُ يلزمه صفٌّ فيها** (‏`U1`)
   ═══════════════════════════════════════════════════════════════════════════
   ◆ **والتسجيلُ ستٌّ لا خمس** (‏درسُ W07): سجلٌّ · دورةٌ · ملاحةٌ · مسمًّى ·
     مساحاتٌ — **ومصفوفةُ الواجهة**. وسطحٌ يُصيَّر بلا صفٍّ فيها يُسقط خطّافَ
     الالتزامِ عند `U1` بعد أن تكون كلُّ بوّاباتِ المرحلةِ خضراء.
   ◆ **والمصفوفةُ ملفُّ CSV** لا جدول — فالكتابةُ تُعيد بناءَ صفوفِ هذه الموجةِ
     وحدَها وتُبقي ما عداها كما هو، والتشغيلُ المتكرِّرُ لا يضاعف.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "②-ب مصفوفةُ الواجهةِ — صفٌّ لكلِّ سطحٍ مُصيَّر ──────────────────\n";
$MTX = $ROOT . '/docs/uxui_matrix_20260818.csv';
$mtxN = 0;
if (!is_file($MTX)) { echo "  ⚠ مصفوفةُ الواجهةِ غيرُ موجودة — التسجيلُ يُتخطّى\n"; }
elseif ($REPORT) { echo "  ↷ قياسٌ بلا كتابة\n"; }
else {
    /* ⚠ **الصفوفُ الباقيةُ تُنقَل خامًّا لا يُعاد ترميزُها**: `fputcsv` يكتب
         الفارغَ بلا اقتباسٍ ويُعيد صوغَ ما لم يتغيّر — فيظهر في الفرقِ أربعةَ
         عشرَ سطرًا لموجاتٍ سابقةٍ لم تُمَسّ. والقراءةُ سطريّةٌ والكتابةُ إلحاقٌ. */
    $lines = file($MTX, FILE_IGNORE_NEW_LINES);
    $hdr = array_shift($lines);
    $mine = array(); $keep = array(); $maxN = 0;
    foreach (repair01_w11_new_surfaces() as $s) { $mine[strtolower($s['route'])] = $s; }
    foreach ($lines as $ln) {
        if (trim($ln) === '') { continue; }
        $cells = str_getcsv($ln);
        if (!$cells || count($cells) < 2) { continue; }
        $maxN = max($maxN, (int) $cells[0]);
        if (isset($mine[strtolower(trim($cells[1]))])) { continue; }  /* صفُّ موجةٍ يُعاد بناؤه */
        $keep[] = $ln;
    }

    $deptAr = array();
    $rd = $conn->query("SELECT canonical_code, name_ar FROM repair01_departments
                         WHERE canonical_code IN ('DEP-05','DEP-06')");
    while ($rd && $dx = $rd->fetch_assoc()) { $deptAr[$dx['canonical_code']] = (string) $dx['name_ar']; }

    /* مُرمِّزٌ يطابق أسلوبَ الملفِّ القائم: يُقتبَس ما فيه فاصلةٌ أو مسافة */
    $cell = function ($v) {
        $v = (string) $v;
        if ($v === '') { return '""'; }
        if (preg_match('/[",\s]/u', $v)) { return '"' . str_replace('"', '""', $v) . '"'; }
        return $v;
    };
    $rows = array();
    foreach (repair01_w11_new_surfaces() as $s) {
        $maxN++;
        $dep = isset($deptAr[$s['owner']]) ? $deptAr[$s['owner']] : $s['owner'];
        $def = 'تعرض ' . $s['ar'] . ' في دورة ' . $s['group'] . ' لدى ' . $dep
             . '. المستند الناتج ' . $s['doc'] . ' والخطوة التالية ' . $s['next'] . '.';
        $vals = array($maxN, $s['route'], $s['ar'], $s['ar'], '', '—', $def, $dep,
            '2 — العمليات', $s['group'], $s['sort'], 'شاشةٌ مستقلة', 1, $s['cons'],
            'قدرةٌ ثبت غيابُها فبُنيت في موضعِها المعياريّ', 'APPROVED',
            'ترتيبُ الدورةِ المحاسبيّةِ في الحزمة — RPR-W11', '—', '—', 'ACTIVE', '—',
            $s['ar'], $s['group'], 'موضعُه من دورةِ العمل — قرارُ الورقة', $s['group']);
        $rows[] = implode(',', array_map($cell, $vals));
        $mtxN++;
    }
    file_put_contents($MTX, $hdr . "\n" . implode("\n", $keep) . "\n"
                          . implode("\n", $rows) . "\n");
}
printf("  صفوفُ مصفوفةٍ مكتوبةٌ لأسطحِ الموجة %d\n\n", $mtxN);

/* ═══════════════════════════════════════════════════════════════════════════
   ②-ج · سجلُّ تصنيفِ المساحات — **الغيابُ ليس منعًا** (`NF-24` · `GAP-22`)
   ═══════════════════════════════════════════════════════════════════════════
   ◆ مسارٌ نشطٌ خارجَ سجلِّ التصنيفِ يُقرأ **مفتوحًا افتراضًا**، فالسقّاطةُ
     تُرسِّب على كلِّ جديدٍ غيرِ مصنَّف. وسطحُ النموِّ يُصنَّف بسلّمِ الحسمِ
     السداسيِّ نفسِه الذي صنَّفت به W09 أسطحَها — والمساحةُ هي **الإدارةُ
     المالكةُ في السجلِّ المعياريّ** لا مساحةٌ مخترَعة.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "②-ج تصنيفُ المساحاتِ — سطحٌ نشطٌ لا يُقرأ مفتوحًا افتراضًا ────────\n";
$spaceN = 0;
if (!repair01_w11_table_exists($conn, 'gov_space_appearances')) {
    echo "  ⚠ سجلُّ المساحاتِ غيرُ موجود — التصنيفُ يُتخطّى\n";
} else {
    $deptAr2 = array();
    $rd2 = $conn->query("SELECT canonical_code, name_ar FROM repair01_departments
                          WHERE canonical_code IN ('DEP-05','DEP-06')");
    while ($rd2 && $dx2 = $rd2->fetch_assoc()) { $deptAr2[$dx2['canonical_code']] = (string) $dx2['name_ar']; }
    foreach (repair01_w11_new_surfaces() as $s) {
        $rt = $esc($s['route']);
        $dep = isset($deptAr2[$s['owner']]) ? $deptAr2[$s['owner']] : $s['owner'];
        $W("DELETE FROM gov_space_appearances WHERE route = '$rt' AND src_class = 'RPR-W11'");
        /* ⚠ **المفتاحُ هنا لا يتزايد ذاتيًّا** — والإدراجُ بلا `id` يصطدم بالصفر
             المكرَّر. فيُشتقُّ من أقصى القائمِ في كلِّ صفٍّ لا مرّةً واحدة. */
        $nextId = (int) $one("SELECT COALESCE(MAX(id), 0) + 1 FROM gov_space_appearances");
        $W("INSERT INTO gov_space_appearances
            (id, space_ar, space_kind, tab_ar, screen_ar, route, owner_dept_ar, owner_kind,
             src_class, src_ownership, src_decision, src_note, spaces_count,
             cls, ownership, decision, basis, rule_step, view_fields, updated_at)
            VALUES ($nextId,'" . $esc($dep) . "','DEPARTMENT','','" . $esc($s['ar']) . "','$rt',
                    '" . $esc($dep) . "','BUSINESS_DEPARTMENT',
                    'RPR-W11','VALID','CONFIRMED',
                    '" . $esc('سطح نمو مختوم W11 - صنف بسلم الحسم السداسي') . "',1,
                    'OWNED','VALID','CONFIRMED',
                    '" . $esc('المساحة هي الادارة المالكة للسطح في السجل المعياري (' . $s['owner'] . ')') . "',
                    1,'',NOW())");
        $spaceN++;
    }
}
printf("  أسطحٌ مصنَّفةٌ في سجلِّ المساحات %d\n\n", $spaceN);

/* ═══════════════════════════════════════════════════════════════════════════
   ③ نطاقُ المرحلة — ٤٣ متطلَّبًا إلى مِرساتِها المُثبَتةِ قياسًا
   ═══════════════════════════════════════════════════════════════════════════ */
echo "③ نطاقُ المرحلة ───────────────────────────────────────────────\n";
$W("DELETE FROM repair01_w11_scope");
$ANCH = repair01_w11_anchors();
$anchored = 0; $unproven = array(); $ownerMismatch = array(); $unscoped = array();
$newRoutes = array_column(repair01_w11_new_surfaces(), 'route');

$rq = $conn->query("SELECT requirement_id, unit, group_name, surface, src_ref
                      FROM repair01_requirements WHERE stage_no = 11 ORDER BY unit, seq");
while ($rq && $q = $rq->fetch_assoc()) {
    $rid = $q['requirement_id'];
    $dept = preg_match('/^(\d{2})\s/u', $q['unit'], $mm) ? 'DEP-' . $mm[1] : '';
    if (!isset($ANCH[$rid])) { $unproven[] = $rid . ' (بلا مِرساةٍ مُعلَنة)'; continue; }
    $a = $ANCH[$rid];
    $pr = repair01_w11_prove_anchor($conn, $ROOT, $a);
    if ($pr['verdict'] === 'ANCHORED') { $anchored++; }
    else { $unproven[] = $rid . ' (' . $pr['verdict'] . ')'; }

    $verdictOwner = ($pr['owner'] !== '' && $dept !== '' && $pr['owner'] !== $dept) ? 'MISMATCH' : 'MATCH';
    if ($verdictOwner === 'MISMATCH') { $ownerMismatch[] = $rid . ' ' . $pr['owner'] . ' بدل ' . $dept; }
    $build = in_array($a['route'], $newRoutes, true) ? 'BUILT_W11' : 'LIVE';
    /* **الحبّةُ تُقاس لا تُدَّعى**: الجدولُ يحمل الكيانَ غيرَ قابلٍ للعدمِ أو لا */
    $scoped = ($a['kind'] === 'TABLE' && repair01_w11_entity_scoped($conn, $a['probe'])) ? 1 : 0;
    if ($a['kind'] === 'TABLE' && $scoped === 0) { $unscoped[] = $rid . ' ⇐ ' . $a['probe']; }

    $W("INSERT INTO repair01_w11_scope
        (requirement_id,unit,group_name,surface,anchor_screen_id,anchor_route,anchor_probe,
         owner_measured,owner_expected,owner_verdict,build_verdict,cycle_step,entity_scoped,
         map_rule,map_why,src_ref)
        VALUES ('" . $esc($rid) . "','" . $esc($q['unit']) . "','" . $esc($q['group_name']) . "',
                '" . $esc($q['surface']) . "','" . $esc($pr['sid']) . "','" . $esc($a['route']) . "',
                '" . $esc($a['probe']) . "','" . $esc($pr['owner']) . "','" . $esc($dept) . "',
                '" . $esc($verdictOwner) . "','" . $esc($build) . "'," . (int) $a['step'] . ",$scoped,
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
                      WHERE stage_no = 11 ORDER BY unit, seq");
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
    $sortNo = (int) $ANCH[$rid]['step'] * 10 + (int) preg_replace('/\D/', '', $rid);
    if (!$REPORT) {
        $lr2 = \App\Services\Ui\UiLabelRegistry::register($conn, 'screen:' . strtolower($rt), (string) $q2['surface'], array(
            'allowed_context' => 'SIDEBAR SCREEN_TITLE',
            'source_table' => 'nav_canonical', 'source_column' => 'canonical_ar',
            'source_key' => $rt, 'owner_code' => $own !== '' ? $own : 'DEP-05',
            'visibility_class' => 'USER_VISIBLE', 'label_state' => 'ACTIVE',
            'rule_id' => 'W11_SCOPE_SURFACE_LABEL', 'origin' => 'W11',
            'src_ref' => 'RPR-W11 §٣-ب · ربطُ سطحٍ قائمٍ بالسجلِّ المعياريّ',
            'caller' => 'repair01_w11_apply.php',
        ));
        if (!$lr2['ok']) { echo '  ⚠ رُدَّ مسمّى ' . $rt . ' — ' . $lr2['code'] . "\n"; }
    }
    $W("INSERT INTO nav_canonical (route, canonical_ar, level_no, level_name, group_name, sort_no,
                                   status, decision_state, application_state, decision_source,
                                   derivation, retirement_status, screen_id, placement_kind)
        VALUES ('$rtE','" . $esc($q2['surface']) . "',2,'العمليات','" . $esc($q2['group_name']) . "'," . $sortNo . ",
                'APPROVED','APPROVED','DEPLOYED','RPR-W11 · ربط سطح النطاق بالسجل المعياري (2026-08-26)',
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
     مالكِه، فـ⛔ **لا يُعاد تسميتُه هنا** (‏`W6-D-09`: تغييرُ اسمٍ اعتمده مالكُه
     انتحالٌ لقرارِه). والتصحيحُ يمضي في الاتّجاهِ الصحيح: **البندُ الحيُّ يتبع
     المعياريّ** لا العكس.
   ◆ ومحورُ **المجموعةِ** مرجعُه **دورةُ العمل** (‏`repair01_requirements.group_name`)
     لا التجميعُ التاريخيّ. والمقيسُ كشف أنَّ المعياريَّ نفسَه يحمل تجميعًا
     قديمًا: «القوائم المالية» تحت «إقفال الفترة» و«القيود اليومية» تحتها أيضًا.
     فيُصحَّح المعياريُّ على الدورةِ ثمَّ يتبعه الحيُّ بمجموعةٍ مختومةٍ بموجتِها.
   ⛔ **ولا مصفوفةَ بنودٍ في الشيفرة** — الأسماءُ والمجموعاتُ من السجلِّ وحدَه.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "③-ج تصحيحُ الاسمِ والمجموعةِ على السجلِّ ودورةِ العمل ───────────\n";
$fixLabel = 0; $fixGroupCanon = 0; $fixGroupLive = 0;
$reqGroup = array();
$rq3 = $conn->query("SELECT requirement_id, group_name FROM repair01_requirements WHERE stage_no = 11");
while ($rq3 && $q3 = $rq3->fetch_assoc()) { $reqGroup[(string) $q3['requirement_id']] = (string) $q3['group_name']; }

/* مجموعةُ الدورةِ لكلِّ مسار — أدنى خطوةٍ تحكم حين يخدم المسارُ متطلَّبَين */
$routeCycle = array();
foreach ($ANCH as $rid => $a) {
    if ($a['route'] === '' || !isset($reqGroup[$rid])) { continue; }
    if (!isset($routeCycle[$a['route']]) || (int) $a['step'] < (int) $routeCycle[$a['route']]['step']) {
        $routeCycle[$a['route']] = array('step' => (int) $a['step'], 'group' => repair01_w11_group_ar($reqGroup[$rid]),
                                         'icon' => 'fa fa-list', 'sort' => (int) $a['step'] * 10
                                                   + (int) preg_replace('/\D/', '', $rid));
    }
}
foreach ($routeCycle as $rt => $cy) {
    $rtE = $esc($rt);
    $canonAr = (string) $one("SELECT canonical_ar FROM nav_canonical WHERE route = '$rtE' LIMIT 1");
    if ($canonAr === '') { continue; }

    /* ② الاسمُ: البندُ الحيُّ يتبع المعياريَّ المعتمَد */
    $drift = (int) $one("SELECT COUNT(*) FROM nav_items
                          WHERE route = '$rtE' AND label_ar <> '" . $esc($canonAr) . "'");
    if ($drift > 0) {
        $W("UPDATE nav_items SET label_ar = '" . $esc($canonAr) . "' WHERE route = '$rtE'");
        $fixLabel += $drift;
    }

    /* ③ المجموعةُ: المعياريُّ يُصحَّح على دورةِ العملِ ثمَّ يتبعه الحيّ */
    $curG = (string) $one("SELECT group_name FROM nav_canonical WHERE route = '$rtE' LIMIT 1");
    if ($curG !== $cy['group']) {
        $W("UPDATE nav_canonical SET group_name = '" . $esc($cy['group']) . "' WHERE route = '$rtE'");
        $fixGroupCanon++;
    }
    if (!$REPORT) {
        $gr3 = \App\Services\Ui\UiLabelRegistry::register($conn, 'group:w11:' . strtolower($cy['group']),
            $cy['group'], array(
                'allowed_context' => 'SIDEBAR', 'source_table' => 'nav_canonical',
                'source_column' => 'group_name', 'source_key' => $cy['group'],
                'owner_code' => (string) $one("SELECT owner_code FROM repair01_screen_registry
                                                WHERE route = '$rtE' LIMIT 1"),
                'visibility_class' => 'USER_VISIBLE', 'label_state' => 'ACTIVE',
                'rule_id' => 'W11_CYCLE_GROUP_LABEL', 'origin' => 'W11',
                'src_ref' => 'RPR-W11 §٣-ج · مجموعةُ دورةِ العمل', 'caller' => 'repair01_w11_apply.php',
            ));
        if (!$gr3['ok']) { echo '  ⚠ رُدَّت مجموعةٌ ' . $cy['group'] . ' — ' . $gr3['code'] . "\n"; }
    }
    /* البندُ الحيُّ ينتقل إلى مجموعةِ دورتِه — مختومةً بموجتِها لكلِّ دور */
    $sidR = (string) $one("SELECT screen_id FROM repair01_screen_registry WHERE route = '$rtE' LIMIT 1");
    if ($sidR === '') { continue; }
    $items = $conn->query("SELECT n.id, n.role_id, g.name AS gname, g.stage_no, g.stage_title, g.display_order
                             FROM nav_items n LEFT JOIN link_groups g ON g.id = n.group_id
                            WHERE n.route = '$rtE' AND n.active = 1");
    while ($items && $it = $items->fetch_assoc()) {
        if ((string) $it['gname'] === $cy['group']) { continue; }
        $code = 'n9o_w11_' . strtolower(str_replace('-', '', $sidR)) . '_r' . (int) $it['role_id'];
        $gid = (int) $one("SELECT id FROM link_groups WHERE group_code = '" . $esc($code) . "' LIMIT 1");
        if ($gid === 0) {
            $W("INSERT INTO link_groups (name, group_code, owner_role_id, icon, display_order,
                                         stage_no, stage_title, is_active)
                VALUES ('" . $esc($cy['group']) . "','" . $esc($code) . "'," . (int) $it['role_id'] . ",
                        '" . $esc($cy['icon']) . "'," . ((int) $it['display_order'] + 1) . ",
                        " . (int) $it['stage_no'] . ",'" . $esc((string) $it['stage_title']) . "',1)");
            $gid = (int) $one("SELECT id FROM link_groups WHERE group_code = '" . $esc($code) . "' LIMIT 1");
        } else {
            /* ⚠ **المجموعةُ المختومةُ قائمةٌ باسمٍ سابق**: مفتاحُها مشتقٌّ من
                 الشاشةِ والدورِ فيصيبه النداءُ الثاني، ولو اكتُفي بالإيجادِ
                 لبقي الاسمُ القديمُ مُصيَّرًا و«صُحِّح» حكمٌ لم يقع. */
            $W("UPDATE link_groups SET name = '" . $esc($cy['group']) . "', is_active = 1 WHERE id = $gid");
        }
        if ($gid <= 0) { continue; }
        $W("UPDATE nav_items SET group_id = $gid, sort_order = " . (int) $cy['sort']
            . " WHERE id = " . (int) $it['id']);
        $fixGroupLive++;
    }
}
printf("  اسمٌ حيٌّ صُحِّح على المعياريّ %d · مجموعةٌ معياريّةٌ على الدورة %d · بندٌ حيٌّ نُقل %d\n\n",
    $fixLabel, $fixGroupCanon, $fixGroupLive);

/* ═══════════════════════════════════════════════════════════════════════════
   ④ الخطواتُ السبعُ للسايدبار — على أسطحِ النطاقِ كلِّها
   ═══════════════════════════════════════════════════════════════════════════ */
echo "④ السايدبارُ — سبعُ خطواتٍ بحكمٍ وقاعدةٍ لكلِّ سطح ─────────────\n";
$W("DELETE FROM repair01_w11_sidebar");
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
    /* **الترتيبُ من دورةِ العملِ لا من الأبجديّة**: الحكمُ يفحص أنَّ للسطحِ
       موضعًا في الدورةِ ومصدرَ ترتيبٍ في السجلِّ معًا. */
    $s4 = ($s4no > 0) ? 'ORDER_FROM_CYCLE' : 'NO_ORDER_SOURCE';
    $s5 = ((string) $reg['visibility_class'] === 'TAB_CHILD') ? 'TAB_IN_PARENT' : 'MENU_ITEM';
    $permRows = (int) $one("SELECT COUNT(*) FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                             WHERE m.code = '$rtE' AND rp.can_view = 1");
    $guard = repair01_w11_guard_of($ROOT, $rt);
    $s6 = ($guard['kind'] !== 'NONE' && $permRows > 0) ? 'GUARDED_AND_GRANTED'
        : ($guard['kind'] === 'NONE' ? 'NO_SERVER_GUARD' : 'NO_GRANT');
    $s7 = ($can && (string) $can['canonical_ar'] !== '' && $sid !== '') ? 1 : 0;

    $W("INSERT INTO repair01_w11_sidebar
        (screen_id,route,owner_code,s1_verdict,s1_rule,s2_label_live,s2_label_canon,s2_verdict,s2_rule,
         s3_group_live,s3_group_canon,s3_verdict,s3_rule,s4_order_src,s4_order_no,s4_cycle_step,
         s4_verdict,s4_rule,s5_parent,s5_verdict,s5_rule,s5_why,s6_visibility,s6_perm_rows,
         s6_guard_kind,s6_verdict,s6_rule,s7_linked,s7_verdict,s7_rule,measured_at)
        VALUES ('" . $esc($sid) . "','$rtE','" . $esc($reg['owner_code']) . "',
                '" . $esc($s1) . "','W11_S1_ACTIVE_BY_TARGET',
                '" . $esc($s2live) . "','" . $esc($s2can) . "','" . $esc($s2) . "','W11_S2_LABEL_FROM_REQUIREMENT',
                '" . $esc($s3live) . "','" . $esc($s3can) . "','" . $esc($s3) . "','W11_S3_GROUP_FROM_CYCLE',
                'nav_canonical.sort_no'," . $s4no . "," . (int) $step . ",
                '" . $esc($s4) . "','W11_S4_ORDER_FROM_CYCLE',
                '','" . $esc($s5) . "','W11_S5_PARENT_FROM_DECISION','موضعُ السطحِ من قرارِ الورقةِ لا من الذوق',
                '" . $esc((string) $reg['visibility_class']) . "'," . $permRows . ",
                '" . $esc($guard['kind']) . "','" . $esc($s6) . "','W11_S6_GUARD_AND_GRANT',
                " . $s7 . ",'" . ($s7 ? 'LINKED' : 'NOT_LINKED') . "','W11_S7_CANONICAL_SCREEN_ID',NOW())");
    $sbN++;
}
printf("  أسطحٌ مقيسةٌ بسبعِ خطوات %d · بلا صفٍّ في السجلّ %d\n\n", $sbN, $sbBad);

/* ═══════════════════════════════════════════════════════════════════════════
   ⑤ العتباتُ — من السجلِّ لا من الشيفرة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑤ العتبات ────────────────────────────────────────────────────\n";
$W("DELETE FROM repair01_w11_thresholds");
$TH = array(
    array('ACC_CLOSE_LAG_DAYS', 7, 'يوم', 'مهلة اقفال الفترة بعد نهايتها',
          'بعدها ينبه النظام ولا يقفل تلقائيا — الاقفال اثبات لا جدولة', 'W11-D-05'),
    array('ACC_RECON_TOLERANCE', 1, 'وحدة عملة الاساس', 'سماح فرق المطابقة قبل فتح بند فرق',
          'الفرق دون هذه القيمة تقريب فاصلة والباقي يلزمه بند بسببه ومسؤوله', 'W11-D-05'),
    array('ACC_REOPEN_WINDOW_DAYS', 30, 'يوم', 'نافذة اعادة فتح الفترة بعد اقفالها',
          'بعدها الطلب يصعد لسلطة اعلى — والقيمة تقرا ولا تكتب في الشيفرة', 'W11-D-06'),
    array('TRE_PETTY_CEILING', 5000, 'وحدة عملة الاساس', 'الحد الاعلى المبدئي لعهدة النثرية',
          'قيمة مبدئية قابلة للضبط تقرا من السجل ولا يكتب رقمها في خدمة', 'W11-D-05'),
    array('TRE_COUNT_COMMITTEE_MIN', 2, 'عضو', 'الحد الادنى لاعضاء لجنة الجرد النقدي',
          'الجرد بلجنة لا بامين الصندوق وحده — والقيمة يفرضها CHECK ايضا', 'W11-D-07'),
    array('TRE_FX_GAP_ALERT_PCT', 2, 'نسبة مئوية', 'فرق سعر الصفقة عن سعر الجدول الذي ينبه',
          'الفرق فوقها ينبه ولا يحجب — والصفقة بسعرها الموثق لا بسعر الجدول', 'W11-D-07'),
    array('ACC_CREDIT_ESCALATE_PCT', 90, 'نسبة مئوية', 'نسبة استهلاك الحد الائتماني التي تنبه',
          'التنبيه قبل التجاوز — والحجب او التصعيد بقاعدة الحد لا بهذه النسبة', 'W11-D-06'),
);
foreach ($TH as $t) {
    $W("INSERT INTO repair01_w11_thresholds (threshold_key,value_num,unit_ar,title_ar,why,decision_ref,src_ref)
        VALUES ('" . $esc($t[0]) . "'," . (float) $t[1] . ",'" . $esc($t[2]) . "','" . $esc($t[3]) . "',
                '" . $esc($t[4]) . "','" . $esc($t[5]) . "','RPR-W11 §٥')");
}
printf("  عتباتٌ مسجَّلة %d\n\n", count($TH));

/* ═══════════════════════════════════════════════════════════════════════════
   ⑥ آلاتُ الحالة — لكلِّ كيانٍ ممنوعٌ صريحٌ بسبب
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑥ آلاتُ الحالة ───────────────────────────────────────────────\n";
$W("DELETE FROM repair01_w11_states");
$ST = array(
    /* طلبُ الاعتراف */
    array('acc_recognition_request', 'pending', 'accepted', 1, 'مدير المالية',
          'الطلب من نطاق مصدري بمرجع واقعته ومن طلب لا يقرر',
          'قرار قبول اعتراف', 'بوابة فصل الواجبات', 'يفتح برد الطلب واعادة اصداره من مصدره',
          'التصحيح بطلب جديد يحمل مرجع السابق لا بتعديل المقرر', ''),
    array('acc_recognition_request', 'pending', 'rejected', 1, 'مدير المالية',
          'سبب رد مكتوب', 'قرار رد اعتراف', 'بوابة فصل الواجبات',
          'يفتح باصدار طلب جديد من النطاق المصدري',
          'التصحيح باستيفاء ما نقص ثم اعادة الطلب', ''),
    array('acc_recognition_request', 'pending', 'posted', 0, '', '', '', '', '', '',
          'الترحيل قبل قرار القبول يجعل المالية تثبت ما لم تقرره — وهو نقض §48 نفسه'),
    array('acc_recognition_request', 'rejected', 'accepted', 0, '', '', '', '', '', '',
          'قلب الرد قبولا على الطلب نفسه يمحو قرارا مسببا — والقبول بطلب جديد'),
    /* القيدُ اليوميّ */
    array('fin_journal_entries', 'draft', 'posted', 1, 'مدير المالية',
          'طلب اعتراف مقبول وفترة مفتوحة ومدين يساوي دائن ومن قرر لا يرحل',
          'قيد يومية مرحل', 'بوابة فصل الواجبات', 'لا يفتح — التصحيح بقيد عكسي',
          'التصحيح بقيد عكسي يحمل مرجع الاصل لا بتعديل المرحل', ''),
    array('fin_journal_entries', 'posted', 'reversed', 1, 'مدير المالية',
          'سبب عكس مكتوب وفترة الاصل مفتوحة او فترة العكس مفتوحة',
          'قيد عكسي', 'اعتماد مدير المالية', 'لا يفتح — العكس واقعة مستقلة',
          'التصحيح بقيد جديد بعد العكس لا بمحو الاصل', ''),
    array('fin_journal_entries', 'posted', 'draft', 0, '', '', '', '', '', '',
          'ارجاع القيد المرحل الى المسودة يفك رقما دخل الميزان ووقع عليه في القوائم'),
    /* التسوية */
    array('acc_period_adjustment', 'draft', 'posted', 1, 'مدير المالية',
          'مستند اساس مكتوب وفترة مفتوحة ومن اعد لا يعتمد',
          'قيد تسوية معتمد', 'بوابة فصل الواجبات', 'يفتح بعكس التسوية في الفترة التالية',
          'التصحيح بتسوية عكسية لا بتعديل المرحلة', ''),
    array('acc_period_adjustment', 'posted', 'reversed', 1, 'مدير المالية',
          'التسوية موسومة بالعكس في الفترة التالية او بقرار مكتوب',
          'قيد عكس تسوية', 'اعتماد مدير المالية', 'لا يفتح — العكس واقعة',
          'التصحيح بتسوية جديدة تحمل مرجع المعكوسة', ''),
    array('acc_period_adjustment', 'reversed', 'posted', 0, '', '', '', '', '', '',
          'اعادة ترحيل تسوية معكوسة تضاعف الاثر مرتين على الفترة نفسها'),
    /* مطابقةُ الحساب */
    array('acc_account_recon', 'open', 'reviewed', 1, 'المحاسب',
          'الرصيدان مقروءان من مصدريهما والفرق مشتق',
          'ورقة مطابقة حساب', 'توقيع المراجع', 'يفتح بفتح بند فرق جديد',
          'التصحيح بفتح بند فرق لا بتعديل الرصيد', ''),
    array('acc_account_recon', 'reviewed', 'closed', 1, 'مدير المالية',
          'صفر بند فرق مفتوح ومن اعد لا يقفل',
          'محضر مطابقة مقفل', 'بوابة فصل الواجبات', 'يفتح بقرار مكتوب قبل اقفال الفترة',
          'التصحيح بجلسة مطابقة جديدة تحمل مرجع السابقة', ''),
    array('acc_account_recon', 'open', 'closed', 0, '', '', '', '', '', '',
          'الاقفال بلا مراجعة يفقد الطبقة التي تكشف خطا الاعداد'),
    /* الفترةُ المحاسبيّة */
    array('fin_financial_periods', 'open', 'closed', 1, 'مدير المالية',
          'ميزان متوازن لهذه الفترة وصفر بند حاجب في قائمة الاقفال ومن اجرى الميزان لا يقفل',
          'محضر اقفال فترة', 'بوابة فصل الواجبات', 'يفتح بطلب اعادة فتح معتمد بقاعدة',
          'التصحيح بقيد تسوية في الفترة التالية لا بتعديل المقفلة', ''),
    array('fin_financial_periods', 'closed', 'reopened', 1, 'مدير المالية',
          'طلب اعادة فتح معتمد بمبرره ونطاقه وقاعدة صلاحيته ومن طلب لا يعتمد',
          'قرار اعادة فتح فترة', 'بوابة فصل الواجبات', 'يعاد الاقفال بعد التصحيح',
          'التصحيح داخل النطاق المعتمد وحده لا في كل الفترة', ''),
    array('fin_financial_periods', 'closed', 'open', 0, '', '', '', '', '', '',
          'فتح فترة مقفلة بلا طلب معتمد يغير رقما وقع عليه في القوائم بلا اثر يراجع'),
    array('fin_financial_periods', 'open', 'locked', 0, '', '', '', '', '', '',
          'القفل النهائي لفترة لم تقفل يتخطى الميزان وقائمة الاقفال معا'),
    /* طلبُ إعادةِ الفتح */
    array('acc_period_reopen_request', 'pending', 'approved', 1, 'مدير المالية',
          'مبرر وقاعدة صلاحية ونطاق زمني ووحدات محددة ومن طلب لا يعتمد',
          'قرار اعتماد اعادة فتح', 'بوابة فصل الواجبات', 'لا يفتح — الطلب واقعة',
          'التصحيح بطلب جديد يحمل مرجع السابق', ''),
    array('acc_period_reopen_request', 'applied', 'reclosed', 1, 'مدير المالية',
          'انتهاء التصحيح داخل النطاق المعتمد',
          'محضر اعادة اقفال', 'اعتماد مدير المالية', 'يفتح بطلب اعادة فتح جديد',
          'التصحيح بطلب جديد لا بتمديد المطبق', ''),
    array('acc_period_reopen_request', 'pending', 'applied', 0, '', '', '', '', '', '',
          'تطبيق اعادة الفتح بلا اعتماد يجعل الاستثناء المحكوم فعلا عاديا'),
    /* أمرُ الدفع */
    array('fin_payments', 'approved', 'executed', 1, 'أمين الخزينة',
          'طلب استوفى اعتماده ومستفيد محقق ومن اعد لا ينفذ',
          'امر دفع منفذ', 'بوابة فصل الواجبات', 'لا يفتح — الاسترداد بسند مستقل',
          'التصحيح بسند استرداد لا بتعديل المنفذ', ''),
    array('fin_payments', 'executed', 'reconciled', 1, 'أمين الخزينة',
          'مطابقة الحركة بكشف البنك',
          'سطر مطابقة بنكية', 'مراجعة المالية', 'يفتح بفتح بند فرق',
          'التصحيح ببند فرق بسببه ومسؤوله', ''),
    array('fin_payments', 'draft', 'executed', 0, '', '', '', '', '', '',
          'التنفيذ على طلب لم يعتمد يتخطى بوابة الاعتماد التي هي غاية الطلب'),
    /* التحويلُ بين الأوعية */
    array('tre_transfer', 'draft', 'executed', 1, 'أمين الخزينة',
          'قاعدة صلاحية وتوقيع مفوض ووعاءان مختلفان ورصيد كاف',
          'امر تحويل منفذ', 'توقيع المفوض', 'لا يفتح — الرجوع بتحويل معاكس',
          'التصحيح بتحويل معاكس يحمل مرجع الاصل', ''),
    array('tre_transfer', 'draft', 'cancelled', 1, 'أمين الخزينة',
          'لم ينفذ بعد',
          'محضر الغاء تحويل', 'اعتماد امين الخزينة', 'لا يفتح — ينشا امر جديد',
          'التصحيح بامر جديد لا بتعديل الملغى', ''),
    array('tre_transfer', 'executed', 'draft', 0, '', '', '', '', '', '',
          'ارجاع تحويل منفذ الى التحرير يفك حركتي خروج ودخول قائمتين في وعاءين'),
    /* الأداةُ الماليّة */
    array('tre_instrument', 'received', 'deposited', 1, 'أمين الخزينة',
          'الاداة مسجلة بمبلغها وتاريخ استحقاقها',
          'قسيمة ايداع', 'اعتماد امين الخزينة', 'يفتح بارتجاع الاداة',
          'التصحيح بتسجيل الارتجاع لا بمحو الايداع', ''),
    array('tre_instrument', 'deposited', 'bounced', 1, 'أمين الخزينة',
          'سبب ارتجاع مكتوب',
          'اشعار ارتجاع بنكي', 'اعتماد امين الخزينة', 'يفتح باعادة الايداع',
          'التصحيح باعادة ايداع او باعادة الاداة لصاحبها', ''),
    array('tre_instrument', 'received', 'collected', 0, '', '', '', '', '', '',
          'التحصيل بلا ايداع يقفز حلقة يثبتها كشف البنك — فيصير التحصيل دعوى'),
    /* العهدةُ النثريّة */
    array('tre_petty_custody', 'open', 'settled', 1, 'أمين الخزينة',
          'صفر بند لم يراجع وكل بند بمستنده',
          'محضر تسوية عهدة', 'اعتماد امين الخزينة', 'لا تفتح — تفتح عهدة جديدة',
          'التصحيح بعهدة جديدة بعد التسوية', ''),
    array('tre_petty_custody', 'settled', 'closed', 1, 'مدير المالية',
          'التسوية معتمدة والمبلغ مسدد',
          'محضر اقفال عهدة', 'اعتماد مدير المالية', 'لا تفتح',
          'التصحيح بعهدة جديدة', ''),
    array('tre_petty_custody', 'open', 'closed', 0, '', '', '', '', '', '',
          'اقفال عهدة بلا تسوية يمحو مطالبة قائمة على امينها'),
    /* الجردُ النقديّ */
    array('tre_cash_count', 'draft', 'reviewed', 1, 'مدير المالية',
          'لجنة من عضوين فاكثر والرصيد الدفتري مشتق من الحركات',
          'كشف جرد نقدي', 'توقيع اللجنة', 'يفتح باعادة العد',
          'التصحيح باعادة عد لا بكتابة فوق الكشف', ''),
    array('tre_cash_count', 'reviewed', 'approved', 1, 'مدير المالية',
          'الفرق بمعالجة مكتوبة ومن عد لا يعتمد',
          'محضر جرد معتمد', 'بوابة فصل الواجبات', 'يفتح بقرار مكتوب',
          'التصحيح بجلسة جرد جديدة تحمل مرجع السابقة', ''),
    array('tre_cash_count', 'draft', 'approved', 0, '', '', '', '', '', '',
          'اعتماد جرد بلا مراجعة يفقد الطبقة التي تكشف خطا العد'),
    /* جلسةُ المطابقةِ البنكيّة */
    array('bank_statements', 'matching', 'reconciled', 1, 'أمين الخزينة',
          'كل سطر مطابق او له بند فرق مفتوح بسببه',
          'ورقة مطابقة بنكية', 'مراجعة المالية', 'يفتح بفتح بند فرق جديد',
          'التصحيح ببند فرق لا بتعديل سطر الكشف', ''),
    array('bank_statements', 'reconciled', 'closed', 1, 'مدير المالية',
          'صفر بند فرق مفتوح ومن اعد لا يراجع',
          'محضر مطابقة مقفل', 'بوابة فصل الواجبات', 'يفتح بقرار مكتوب قبل اقفال الفترة',
          'التصحيح بجلسة جديدة تحمل مرجع السابقة', ''),
    array('bank_statements', 'imported', 'closed', 0, '', '', '', '', '', '',
          'اقفال كشف لم تجر مطابقته يعلن توافقا لم يقس'),
    /* خطابُ الضمان */
    array('tre_guarantee', 'requested', 'issued', 1, 'أمين الخزينة',
          'تسهيل قائم وقاعدة صلاحية مكتوبة',
          'خطاب ضمان صادر', 'اعتماد صاحب الصلاحية', 'يفتح بالتمديد بقرار',
          'التصحيح بخطاب معدل يحمل مرجع الاصل', ''),
    array('tre_guarantee', 'issued', 'released', 1, 'أمين الخزينة',
          'مرجع الافراج مكتوب من المستفيد او من العقد',
          'اشعار افراج', 'اعتماد صاحب الصلاحية', 'لا يفتح — يصدر خطاب جديد',
          'التصحيح بخطاب جديد', ''),
    array('tre_guarantee', 'requested', 'released', 0, '', '', '', '', '', '',
          'الافراج عن خطاب لم يصدر افراج عن التزام لا وجود له'),
);
foreach ($ST as $s) {
    $W("INSERT INTO repair01_w11_states
        (entity,from_state,to_state,allowed,owner_role,precondition,official_doc,approval_gate,
         reopen_rule,correct_rule,forbid_reason,src_ref)
        VALUES ('" . $esc($s[0]) . "','" . $esc($s[1]) . "','" . $esc($s[2]) . "'," . (int) $s[3] . ",
                '" . $esc($s[4]) . "','" . $esc($s[5]) . "','" . $esc($s[6]) . "','" . $esc($s[7]) . "',
                '" . $esc($s[8]) . "','" . $esc($s[9]) . "','" . $esc($s[10]) . "','RPR-W11 §٧')");
}
$stN = (int) $one("SELECT COUNT(*) FROM repair01_w11_states");
$stE = (int) $one("SELECT COUNT(DISTINCT entity) FROM repair01_w11_states");
$stF = (int) $one("SELECT COUNT(*) FROM repair01_w11_states WHERE allowed = 0");
printf("  كيانات %d · انتقالات %d · ممنوعٌ صراحةً %d\n\n", $stE, $stN, $stF);

/* ═══════════════════════════════════════════════════════════════════════════
   ⑦ فصلُ الواجبات — بستّةِ أدوارٍ وتركيبةٍ ممنوعةٍ ورمزِ ردٍّ يُنفِّذها
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑦ فصلُ الواجبات ─────────────────────────────────────────────\n";
$W("DELETE FROM repair01_w11_sod");
$SOD = array(
    array('acc.recognition.decide', 'قرار الاعتراف بواقعة نطاق مصدري', 'النطاق المصدري', 'المحاسب',
          'مدير المالية', 'المحاسب', 'مدير المالية',
          'من طلب الاعتراف لا يقرره', 'SAME_ACTOR_REQUEST_AND_DECIDE', 'AAM-ACC-01',
          'نائب مدير المالية', 'ضمن الكيان القانوني والفترة المفتوحة', 'بتفويض مكتوب ومؤقت'),
    array('acc.entry.post', 'ترحيل القيد الى دفتر الاستاذ', 'المحاسب', 'مدير المالية',
          'مدير المالية', 'المحاسب', 'مدير المالية',
          'من قرر الاعتراف لا يرحل قيده', 'SAME_ACTOR_PREPARE_AND_POST', 'AAM-ACC-02',
          'نائب مدير المالية', 'الفترة المفتوحة وحدها', 'بتفويض مكتوب ومؤقت'),
    array('acc.adjustment.approve', 'اعتماد قيد التسوية', 'المحاسب', 'مدير المالية',
          'مدير المالية', 'المحاسب', 'مدير المالية',
          'من اعد التسوية لا يعتمدها', 'SAME_ACTOR_PREPARE_AND_APPROVE_ADJ', 'AAM-ACC-03',
          'نائب مدير المالية', 'تسويات الفترة المفتوحة', 'بتفويض مكتوب ومؤقت'),
    array('acc.recon.close', 'اقفال مطابقة حساب رقابي', 'المحاسب', 'مدير المالية',
          'مدير المالية', 'المحاسب', 'مدير المالية',
          'من اعد المطابقة لا يقفلها', 'SAME_ACTOR_PREPARE_AND_CLOSE_RECON', 'AAM-ACC-04',
          'نائب مدير المالية', 'الحساب الرقابي والفترة معا', 'بتفويض مكتوب ومؤقت'),
    array('acc.period.close', 'اقفال الفترة المحاسبية', 'المحاسب', 'مدير المالية',
          'مدير المالية', 'المحاسب', 'مدير المالية',
          'من اجرى الميزان لا يقفل الفترة', 'SAME_ACTOR_PREPARE_AND_CLOSE', 'AAM-ACC-05',
          'نائب مدير المالية', 'الكيان القانوني والفترة معا', 'لا تفويض في الاقفال'),
    array('acc.period.reopen', 'اعتماد اعادة فتح فترة مقفلة', 'المحاسب', 'مدير المالية',
          'مدير المالية', 'المحاسب', 'مدير المالية',
          'من طلب اعادة الفتح لا يعتمدها', 'SAME_ACTOR_REQUEST_AND_APPROVE_REOPEN', 'AAM-ACC-06',
          'نائب مدير المالية', 'النطاق الزمني والوحدات المحددة وحدها', 'لا تفويض في اعادة الفتح'),
    array('tre.payment.execute', 'تنفيذ امر الدفع', 'الادارة الطالبة', 'المحاسب',
          'مدير المالية', 'أمين الخزينة', 'مدير المالية',
          'من اعد الدفعة لا ينفذها', 'SAME_ACTOR_PREPARE_AND_EXECUTE', 'AAM-TRS-01',
          'نائب امين الخزينة', 'المستفيد المحقق وحده', 'بتفويض مكتوب ومؤقت'),
    array('tre.bank.review', 'مراجعة المطابقة البنكية واقفالها', 'أمين الخزينة', 'المحاسب',
          'مدير المالية', 'أمين الخزينة', 'مدير المالية',
          'من اعد المطابقة لا يراجعها', 'SAME_ACTOR_PREPARE_AND_REVIEW_BANK', 'AAM-TRS-02',
          'نائب مدير المالية', 'الحساب البنكي والشهر معا', 'بتفويض مكتوب ومؤقت'),
    array('tre.count.approve', 'اعتماد الجرد النقدي', 'أمين الصندوق', 'لجنة الجرد',
          'مدير المالية', 'أمين الخزينة', 'مدير المالية',
          'من عد لا يعتمد جرده', 'SAME_ACTOR_COUNT_AND_APPROVE', 'AAM-TRS-03',
          'نائب مدير المالية', 'الصندوق وجلسته', 'لا تفويض في الجرد المفاجئ'),
    array('tre.petty.settle', 'قبول بنود عهدة النثرية', 'أمين العهدة', 'المحاسب',
          'مدير المالية', 'أمين الخزينة', 'مدير المالية',
          'امين العهدة لا يقبل بنود عهدته', 'SAME_ACTOR_HOLD_AND_ACCEPT', 'AAM-TRS-04',
          'نائب المحاسب', 'بنود العهدة المفتوحة وحدها', 'بتفويض مكتوب ومؤقت'),
    array('tre.transfer.execute', 'تنفيذ التحويل بين اوعية الشركة', 'أمين الخزينة', 'المحاسب',
          'مدير المالية', 'أمين الخزينة', 'مدير المالية',
          'تحويل بلا قاعدة صلاحية او بلا توقيع مفوض', 'TRANSFER_WITHOUT_AUTHORITY', 'AAM-TRS-05',
          'نائب امين الخزينة', 'اوعية الشركة نفسها لا مستفيدا خارجيا', 'بتفويض مكتوب ومؤقت'),
);
foreach ($SOD as $s) {
    $W("INSERT INTO repair01_w11_sod
        (process_key,process_name,initiator_role,reviewer_role,approver_role,executor_role,closer_role,
         forbidden_combo,enforced_by,authority_rule_id,deputy_role,scope_rule,delegation,effective_date,src_ref)
        VALUES ('" . $esc($s[0]) . "','" . $esc($s[1]) . "','" . $esc($s[2]) . "','" . $esc($s[3]) . "',
                '" . $esc($s[4]) . "','" . $esc($s[5]) . "','" . $esc($s[6]) . "','" . $esc($s[7]) . "',
                '" . $esc($s[8]) . "','" . $esc($s[9]) . "','" . $esc($s[10]) . "','" . $esc($s[11]) . "',
                '" . $esc($s[12]) . "','2026-08-26','RPR-W11 §٧')");
}
printf("  عملياتٌ حرِجة %d\n\n", count($SOD));

/* ═══════════════════════════════════════════════════════════════════════════
   ⑧ عقودُ الأثر — لكلِّ حدثٍ مستهلكونَ بالاسمِ لا «كلُّ المستهلكين»
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑧ عقودُ الأثر ────────────────────────────────────────────────\n";
$W("DELETE FROM repair01_events WHERE wave = 'W11'");
$EV = array(
    array('acc.recognition.requested', 'طلب اعتراف من نطاق مصدري', 'acc_recognition_request',
          'Finance/journal_form_fin.php',
          'اصدار نطاق مصدري طلب اعتراف بواقعة ذات اثر مالي',
          'source_module · source_ref · amount · currency',
          'AccountingCycleService::onRecognitionRequested',
          'يحل فترة الطلب من تقويم الفترات ويكتبها في الطلب فلا يرحل طلب بلا فترة',
          'المصدر ليس المالية والمرجع مكتوب والمبلغ موجب',
          'إعادة بلا أثر', 'w11:acc.recognition.requested', 'يرفع ولا يبتلع',
          'الطلب يبقى معلقا ويعاد اصداره من مصدره'),
    array('acc.recognition.decided', 'قرار المالية في طلب الاعتراف', 'acc_recognition_request',
          'Finance/journal_form_fin.php',
          'قبول المالية للطلب او رده بسبب مكتوب',
          'request_id · decision · decided_by',
          'AccountingCycleService::onRecognitionDecided',
          'ينشئ واقعة الاعتراف المالية عند القبول ويختمها في الطلب — والرد لا ينشئ شيئا',
          'الطلب معلق ومن طلب لا يقرر',
          'إعادة بلا أثر', 'w11:acc.recognition.decided', 'يرفع ولا يبتلع',
          'الرد يقيد بسببه ولا واقعة تنشا'),
    array('acc.entry.posted', 'ترحيل قيد على طلب مقبول', 'fin_journal_entries',
          'Finance/journal_form_fin.php',
          'تثبيت قيد متوازن في فترة مفتوحة على طلب اعتراف مقبول',
          'entry_id · request_id · total_debit · total_credit',
          'AccountingCycleService::onEntryPosted',
          'يعيد احتساب التعرض الائتماني لكل عميل بحد فعال فتقرا الاتاحة من الذمم القائمة',
          'طلب مقبول وفترة مفتوحة ومدين يساوي دائن',
          'إعادة بلا أثر', 'w11:acc.entry.posted', 'يرفع ولا يبتلع',
          'التصحيح بقيد عكسي يحمل مرجع الاصل'),
    array('acc.adjustment.posted', 'ترحيل تسوية نهاية الفترة', 'acc_period_adjustment',
          'Finance/acc_adjustments.php',
          'اعتماد تسوية بمستند اساسها',
          'adjustment_id · period_id · adj_kind',
          'AccountingCycleService::onAdjustmentPosted',
          'يغلق بند ترحيل التسويات في قائمة اقفال الفترة فيتقدم شرط الاقفال',
          'مستند اساس مكتوب وفترة مفتوحة ومن اعد لا يعتمد',
          'إعادة بلا أثر', 'w11:acc.adjustment.posted', 'يرفع ولا يبتلع',
          'العكس في الفترة التالية لا تعديل المرحلة'),
    array('acc.account.reconciled', 'اقفال مطابقة حساب رقابي', 'acc_account_recon',
          'Finance/acc_reconciliations.php',
          'اقفال جلسة مطابقة بصفر فرق مفتوح',
          'recon_id · period_id · account_code',
          'AccountingCycleService::onAccountReconciled',
          'يغلق بند مطابقة الذمم المدينة او الدائنة في قائمة الاقفال بحسب طبيعة الحساب',
          'صفر بند فرق مفتوح ومن اعد لا يقفل',
          'إعادة بلا أثر', 'w11:acc.account.reconciled', 'يرفع ولا يبتلع',
          'جلسة مطابقة جديدة تحمل مرجع السابقة'),
    array('acc.trial.balanced', 'جولة ميزان مراجعة', 'acc_trial_balance_run',
          'Finance/acc_trial_balance.php',
          'اشتقاق ميزان الفترة من القيود المنشورة',
          'run_id · period_id · balanced',
          'AccountingCycleService::onTrialBalanced',
          'يغلق بند مراجعة الفروق في قائمة الاقفال عند التوازن وحده — وغير المتوازن لا يغلق شيئا',
          'الفترة معرفة والقيود منشورة',
          'إعادة تنشئ جولة جديدة بمرجعها', 'w11:acc.trial.balanced', 'يرفع ولا يبتلع',
          'الجولة لقطة بزمنها ولا تعدل'),
    array('acc.period.closed', 'اقفال الفترة المحاسبية', 'fin_financial_periods',
          'Finance/periods_fin.php',
          'اقفال فترة بميزان متوازن وصفر بند حاجب',
          'period_id · run_id',
          'AccountingCycleService::onPeriodClosed',
          'يمنع الترحيل على الفترة فيصير الاقفال اثباتا لا اعلانا',
          'ميزان متوازن وصفر بند حاجب ومن اجرى الميزان لا يقفل',
          'إعادة بلا أثر', 'w11:acc.period.closed', 'يرفع ولا يبتلع',
          'اعادة الفتح بطلب معتمد بقاعدته'),
    array('acc.period.reopened', 'اعادة فتح فترة مقفلة', 'fin_financial_periods',
          'Finance/acc_reopen_governance.php',
          'اعتماد طلب اعادة فتح بمبرره ونطاقه',
          'reopen_id · period_id',
          'AccountingCycleService::onPeriodReopened',
          'يعيد السماح بالترحيل في نطاق الطلب المعتمد وحده',
          'مبرر وقاعدة صلاحية ونطاق ووحدات ومن طلب لا يعتمد',
          'إعادة بلا أثر', 'w11:acc.period.reopened', 'يرفع ولا يبتلع',
          'اعادة الاقفال بعد التصحيح'),
    array('acc.statements.issued', 'اصدار القوائم المالية', 'fin_financial_periods',
          'Finance/financial_statements_fin.php',
          'اشتقاق القوائم بعد الاقفال من الميزان المقفل',
          'period_id · run_id',
          'AccountingCycleService::onStatementsIssued',
          'يغلق بند اصدار التقارير في قائمة الاقفال فتكتمل دورة الفترة',
          'الفترة مقفلة وميزانها متوازن',
          'إعادة بلا أثر', 'w11:acc.statements.issued', 'يرفع ولا يبتلع',
          'اصدار نسخة جديدة بعد اعادة الفتح والاقفال'),
    array('tre.receipt.allocated', 'تخصيص تحصيل على فواتير', 'fin_payments',
          'Finance/tre_allocations.php',
          'تخصيص سند قبض على فاتورة او اكثر',
          'receipt_id · lines',
          'TreasuryCycleService::onReceiptAllocated',
          'يعيد احتساب المحصل والمتبقي وحالة كل ذمة مخصص عليها فتقفل الفاتورة بتغطيتها',
          'السند قبض ومجموع التخصيص لا يتجاوز مبلغه',
          'إعادة بلا أثر', 'w11:tre.receipt.allocated', 'يرفع ولا يبتلع',
          'عكس التخصيص بسطر مقابل لا بمحو السطر'),
    array('tre.payment.executed', 'تنفيذ امر دفع', 'fin_payments',
          'Finance/tre_pay_batch.php',
          'تنفيذ امر دفع لمستفيد محقق من طلب معتمد',
          'payment_id · vessel_id · beneficiary_id',
          'TreasuryCycleService::onPaymentExecuted',
          'يكتب حركة خروج نقد من الوعاء فيتغير رصيده المشتق',
          'الطلب معتمد والمستفيد محقق ومن اعد لا ينفذ',
          'إعادة بلا أثر', 'w11:tre.payment.executed', 'يرفع ولا يبتلع',
          'الاسترداد بسند مستقل لا بمحو الحركة'),
    array('tre.bank.reconciled', 'اقفال جلسة مطابقة بنكية', 'bank_statements',
          'Finance/bank_reconciliation_fin.php',
          'اقفال كشف بصفر فرق مفتوح',
          'statement_id · period_to',
          'TreasuryCycleService::onBankReconciled',
          'يغلق بند مطابقة البنك في قائمة اقفال الفترة التي يقع فيها الكشف',
          'صفر بند فرق مفتوح ومن اعد لا يراجع',
          'إعادة بلا أثر', 'w11:tre.bank.reconciled', 'يرفع ولا يبتلع',
          'جلسة مطابقة جديدة تحمل مرجع السابقة'),
    array('tre.count.approved', 'اعتماد جرد نقدي', 'tre_cash_count',
          'Finance/tre_cash_count.php',
          'اعتماد جلسة جرد بلجنتها ومعالجة فرقها',
          'count_id · box_id · difference',
          'TreasuryCycleService::onCashCountApproved',
          'يكتب حركة تسوية بفرق الجرد فيتطابق الرصيد الدفتري مع المعدود',
          'لجنة من عضوين فاكثر ومن عد لا يعتمد والفرق بمعالجة مكتوبة',
          'إعادة بلا أثر', 'w11:tre.count.approved', 'يرفع ولا يبتلع',
          'جلسة جرد جديدة تحمل مرجع السابقة'),
);
foreach ($EV as $e) {
    $W("INSERT INTO repair01_events
        (event_code,name,wave,source_unit,source_screen,idempotency_key,consumers,effect_type,
         retry_policy,src_ref,trigger_rule,min_payload,consumer_list,consumer_effect,
         preconditions,failure_policy,compensation,contract_status,contract_rule,contract_stage)
        VALUES ('" . $esc($e[0]) . "','" . $esc($e[1]) . "','W11',
                '" . $esc(strpos($e[0], 'tre.') === 0 ? '06 إدارة الخزينة' : '05 الإدارة المالية') . "',
                '" . $esc($e[3]) . "','" . $esc($e[10]) . "','" . $esc($e[6]) . "',
                '" . $esc($e[7]) . "','" . $esc($e[9]) . "','RPR-W11 §٧',
                '" . $esc($e[4]) . "','" . $esc($e[5]) . "','" . $esc($e[6]) . "','" . $esc($e[7]) . "',
                '" . $esc($e[8]) . "','" . $esc($e[11]) . "','" . $esc($e[12]) . "',
                'RECORDED','W11_EVENT_CONTRACT','W11')");
}
printf("  عقودُ أثرٍ مكتوبة %d\n\n", count($EV));

/* ═══════════════════════════════════════════════════════════════════════════
   ⑨ الرقمُ العابرُ للكيانات — يُوسَم أو يُرفض
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑨ الأرقامُ العابرةُ للكيانات ─────────────────────────────────\n";
$W("DELETE FROM repair01_w11_consolidated");
$CONSOL = array(
    array('w11.entity.trial_balance', 'Finance/acc_trial_balance.php', 'ميزان المراجعة',
          1, 'SINGLE_ENTITY', '', '', 'الميزان لكيان وفترة — ولا يجمع كيانين'),
    array('w11.entity.statements', 'Finance/financial_statements_fin.php', 'القوائم المالية',
          1, 'SINGLE_ENTITY', '', '', 'القوائم تشتق من ميزان كيان واحد بعد اقفاله'),
    array('w11.entity.period_close', 'Finance/periods_fin.php', 'اقفال الفترة',
          1, 'SINGLE_ENTITY', '', '', 'الحبة كيان قانوني في فترة محاسبية'),
    array('w11.group.executive_board', 'Finance/executive_dashboard_fin.php', 'لوحة المؤشرات التنفيذية',
          2, 'GROUP_PROJECTION', 'مجمع على مستوى المجموعة', 'مدير المالية',
          'اللوحة تقرا اكثر من كيان فتوسم صراحة — ورقم بلا وسم يقرا رقم كيان وهو ليس كذلك'),
    array('w11.group.cash_position', 'Finance/tre_board.php', 'مركز السيولة',
          2, 'GROUP_PROJECTION', 'مجمع على مستوى المجموعة', 'مدير المالية',
          'ارصدة اوعية اكثر من كيان تقرا معا للقيادة — وتوسم لا تخلط صامتة'),
);
foreach ($CONSOL as $c) {
    $W("INSERT INTO repair01_w11_consolidated
        (figure_key,surface,figure_name,entity_count,tag,tag_label_ar,read_owner,why,src_ref)
        VALUES ('" . $esc($c[0]) . "','" . $esc($c[1]) . "','" . $esc($c[2]) . "'," . (int) $c[3] . ",
                '" . $esc($c[4]) . "','" . $esc($c[5]) . "','" . $esc($c[6]) . "','" . $esc($c[7]) . "',
                'RPR-W11 §٤-٣')");
}
printf("  أرقامٌ مسجَّلةٌ بوسمِها %d (‏مجمَّعةٌ %d)\n\n", count($CONSOL),
    (int) $one("SELECT COUNT(*) FROM repair01_w11_consolidated WHERE entity_count > 1"));

/* ═══════════════════════════════════════════════════════════════════════════
   ⑩ قراراتُ المرحلة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑩ قراراتُ المرحلة ────────────────────────────────────────────\n";
$W("DELETE FROM repair01_w11_decisions");
$mismatchN = count($ownerMismatch);
$emptyN = (int) $one("SELECT COUNT(*) FROM acc_recognition_request")
        + (int) $one("SELECT COUNT(*) FROM acc_account_recon")
        + (int) $one("SELECT COUNT(*) FROM tre_cash_count");
$DEC = array(
    array('W11-D-01', 'ما حبة الاقفال والقيد والفاتورة والحساب البنكي',
          'كيان قانوني في فترة محاسبية — DEC-OPEN-03 معتمد. وكل جدول بني هنا يحمل company_id غير قابل للعدم',
          'المنصة واحدة والكيانات عدة ودفتر كل كيان مستقل. وعمود يقبل العدم يسمح بصف بلا كيان '
        . 'فتصير الحبة دعوى لا بنية — ولذلك يقاس NOT NULL لا وجود العمود',
          21),
    array('W11-D-02', 'كيف يمنع ان يكتب نطاق قيدا بيده',
          'سجل طلب اعتراف مستقل: النطاق المصدري يصدر الطلب والمالية تقرر وتثبت. و CHECK يمنع ان يكون المصدر المالية',
          '§48 نصا. ولو ترك المنع لشيفرة الخدمة وحدها لجاز تجاوزه باستعلام خام — '
        . 'فوضع في القاعدة حيث لا يعبره مسار. والقيد لا يرحل الا على طلب حالته مقبول',
          1),
    array('W11-D-03', 'اين توضع الاستحقاقات والمقدمات والمخصصات والاصول الثابتة والتسويات',
          'في التسويات والمحاسبة العامة لا تحت الذمم الدائنة — بجدولها المستقل acc_period_adjustment '
        . 'وبسطحها في مجموعة التسويات',
          'وضعها تحت الذمم الدائنة يجعل تسوية نهاية الفترة فرعا من دفتر الموردين، '
        . 'وهي ليست كذلك: الاستحقاق قد يكون بلا مورد اصلا والمخصص تقدير لا التزام قائم. '
        . 'و§٤-٤ يمنع هذا الجمع صراحة',
          4),
    array('W11-D-04', 'ماذا عن سطح المالك المخالف داخل النطاق',
          'يعلن ولا يدهس. ورفع الملكية قرار الادارة المالكة لا قرار هذه الموجة',
          'السطح تحت مالك غير مالك متطلبه يقاس ويكتب في repair01_w11_scope بحكم MISMATCH. '
        . 'وتغيير المالك يغير من يرى الشاشة فهو قرار حوكمة لا تصحيح تقني — والشق نفسه حسم في W10',
          $mismatchN),
    array('W11-D-05', 'ما عتبات الاقفال والمطابقة والعهدة',
          'مهلة الاقفال سبعة ايام وسماح المطابقة وحدة واحدة وحد العهدة المبدئي مسجل — كلها في repair01_w11_thresholds',
          'العتبة من نوع ضبط لا بنية فتبنى قابلة الضبط وتقرا من السجل. '
        . 'والمهلة تنبه ولا تقفل تلقائيا لان الاقفال اثبات لا جدولة',
          3),
    array('W11-D-06', 'ما نافذة اعادة الفتح ومتى ينبه الحد الائتماني',
          'ثلاثون يوما بعد الاقفال وتسعون بالمئة من الحد — كلاهما في السجل لا في الشيفرة',
          'اعادة الفتح استثناء محكوم فيلزمه افق زمني مكتوب، وبعده يصعد الطلب لسلطة اعلى. '
        . 'والتنبيه قبل التجاوز يمنع الحجب المفاجئ في منتصف صفقة',
          2),
    array('W11-D-07', 'ما ضوابط الجرد النقدي وفرق الصرف',
          'لجنة الجرد عضوان فاكثر — يفرضها CHECK والسجل معا؛ وفرق سعر الصفقة عن الجدول ينبه فوق نسبته ولا يحجب',
          'الجرد بامين الصندوق وحده توقيع على نفسه لا رقابة. '
        . 'وسعر الصفقة الموثق هو الواقعة وسعر الجدول مقارنة — فالفرق ينبه ولا يحل محل الواقع',
          2),
    array('W11-D-08', 'اربعة سجلات بنيت ولم تمارس بعد — أتمر بواباتها خضراء على مجموعة خاوية',
          'الخلاء معلن هنا بنصه فتمر البوابات مرة واحدة. والرحلة تمارس السجلات فعلا ثم تكنس اثرها '
        . 'فالبناء مثبت وظيفيا لا مدعى. والقبول النهائي في W15 برحلة موظف حقيقي',
          'طلبات الاعتراف ومطابقات الحسابات وجولات الميزان وجلسات الجرد النقدي كلها اليوم صفر صف في القاعدة الحية. '
        . 'وبوابة تقارن صفرا بصفر تمر على تطابق لا شيء — وهو نمط وقع في W1-08 و W7-10 و W9-D-09. '
        . 'فالصفر يمر معلنا بقرار وحده ويسقط بلا اعلان',
          4),
    array('W11-D-09', 'ماذا عن السجلات التابعة الثلاثة — أتبنى اسطحا مستقلة',
          'تعرض في شاشة ابيها: بنود الفاتورة في شاشة الفاتورة وبنود الاستحقاق في شاشة الذمم '
        . 'وبنود فروق المطابقة في شاشة المطابقة',
          'الحبة في المتطلب نفسه تقول Child Register — والابن سطح مستقل يشق مصدر الحقيقة '
        . 'ويجعل للبند مسارا لا يمر باب ابيه. والنمط نفسه طبقته W09 على بنود الطلب والعرض',
          3),
    array('W11-D-11', 'الفجوة المعلنة لوحة الخزينة والبنوك اسمها ملف قائم في سجل الاشباح — أيبنى باسمه',
          'يبنى باسمه هو Finance/tre_liquidity_board.php وتقيد الفجوة موفاة في built_counterpart — '
        . 'ولا يمس صف الشبح ولا سجل مرحلة مغلقة',
          'البناء باسم الشبح نفسه انتج عطبين مقيسين: صفين في سجل الشاشات لملف واحد — شبح ينتظر وبناء لا يعرف انه هو — '
        . 'ثم فارقا في G0-08 و W2-07 لان الشبح صار مبنيا وسجلا مرحلتين مغلقتين ما زالا يعدانه منتظرا. '
        . 'وتسوية الفارق بتعديل origin_stage في دفتر الفجوات كتابة تاريخ لم يقع، وهو ما يمنعه نص الحملة: '
        . 'لا يعدل حاجب مغلق ولا سجله لتيسير مرحلة تستفيد منه. والسابقة قائمة: W9-D-08 حكم بان الفجوة اسم مستهدف '
        . 'لا اسم ملف ملزم فبنى rfq.php باسم proc_rfq.php. و built_counterpart هو الحقل الذي وضع لهذا بعينه',
          1),
    array('W11-D-10', 'كيف يرتب السايدبار داخل النطاق',
          'على ترتيب الدورة المحاسبية في §23 لا على الابجدية ولا على تاريخ الانشاء — والموضع مكتوب في cycle_step',
          'تاسيس ثم دفاتر مساعدة ثم تسويات ثم مطابقات ثم ميزان ثم قائمة اقفال ثم اقفال ثم قوائم. '
        . 'وترتيب ابجدي يضع القوائم المالية قبل القيود ويضع الاقفال قبل المطابقة — فيقرا المستخدم الدورة معكوسة',
          43),
);
foreach ($DEC as $d) {
    $W("INSERT INTO repair01_w11_decisions (decision_id,question,ruling,rationale,scope_rows)
        VALUES ('" . $esc($d[0]) . "','" . $esc($d[1]) . "','" . $esc($d[2]) . "','" . $esc($d[3]) . "'," . (int) $d[4] . ")");
}
printf("  قرارات %d\n\n", count($DEC));

/* ═══════════════════════════════════════════════════════════════════════════
   ⑪ الإصلاحاتُ — كلٌّ بمتطلَّبِه الكاشف
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑪ الإصلاحاتُ بكاشفِها ────────────────────────────────────────\n";
$W("DELETE FROM repair01_w11_fixes");
$FIX = array(
    array('W11-FIX-01', 'SCHEMA', 'acc_recognition_request',
          'بناء سجل طلب الاعتراف بين النطاق المصدري والمالية',
          'ACC-11', 'لا كاتب بشري لدفتر الاستاذ: النطاق يصدر طلبا والمالية تقرر وتثبت',
          'المقيس قبل الاصلاح: صفر جدول يحمل طلب اعتراف — والقيد ينشا من الواقعة مباشرة بلا قرار مسجل'),
    array('W11-FIX-02', 'SCHEMA', 'acc_invoice_line',
          'بناء بنود فاتورة العميل — والاجماليات تشتق للام',
          'ACC-08', 'بند × فاتورة سجل تابع بوصفه وكميته وسعره وضريبته',
          'المقيس قبل الاصلاح: ar_claim_invoices تحمل amount واحدا بلا بند'),
    array('W11-FIX-03', 'SCHEMA', 'acc_supplier_accrual_line',
          'بناء بنود استحقاق المورد بمرجع كل بند في بوابته',
          'ACC-10', 'كل بند بمرجعه في بوابته — سطر المطابقة او بند الاقفال التعاقدي',
          'المقيس قبل الاصلاح: fin_dues بلا بنود ولا موضع لمرجع البوابة'),
    array('W11-FIX-04', 'SCHEMA', 'acc_credit_limit',
          'بناء الحد الائتماني والتعرض المشتق وقاعدة التجاوز',
          'ACC-15', 'المالية تضبط الحد والتجاوز يحجب او يصعد بقاعدة',
          'المقيس قبل الاصلاح: صفر جدول حد ائتماني — والبيع لا يقرا حدا'),
    array('W11-FIX-05', 'SCHEMA', 'acc_period_adjustment',
          'فصل تسويات نهاية الفترة عن دفتر الموردين',
          'ACC-17', 'استحقاق لم يفوتر ومصروف مقدم يستهلك ومخصص بمستنده',
          'المقيس قبل الاصلاح: صفر جدول تسوية فترة — والتسوية تكتب قيدا يدويا بلا وسم نوعها'),
    array('W11-FIX-06', 'SCHEMA', 'acc_account_recon · acc_account_recon_line',
          'بناء جلسة مطابقة الحساب الرقابي وبنود فروقها',
          'ACC-20', 'كل حساب رقابي يطابق مع مصدره التفصيلي ولا فرق مدفون في حقل',
          'المقيس قبل الاصلاح: صفر جدول مطابقة حساب — والمطابقة البنكية وحدها موجودة'),
    array('W11-FIX-07', 'SCHEMA', 'acc_trial_balance_run · acc_trial_balance_line',
          'بناء جولة ميزان المراجعة المشتقة ولقطتها بزمنها',
          'ACC-21', 'مشتق كليا من القيود المنشورة وتوازنه شرط الاقفال',
          'المقيس قبل الاصلاح: القوائم المالية تحسب مباشرة من القيود بلا لقطة ميزان يستند اليها الاقفال'),
    array('W11-FIX-08', 'SCHEMA', 'fin_closing_items · اربعة اعمدة',
          'توثيق استثناء البند الناقص في قائمة الاقفال بقرار',
          'ACC-22', 'لا اقفال قبل اكتمال البنود كلها او توثيق استثناء كل بند ناقص بقرار',
          'المقيس قبل الاصلاح: البند حالته pending او done او na بلا موضع لسبب استثناء ولا لحجب الاقفال'),
    array('W11-FIX-09', 'SCHEMA', 'acc_period_reopen_request',
          'بناء طلب اعادة فتح الفترة بمبرره ونطاقه وقاعدة صلاحيته',
          'ACC-25', 'اعادة الفتح استثناء محكوم لا حقل سبب في الفترة',
          'المقيس قبل الاصلاح: fin_financial_periods.reopen_reason حقل نصي بلا طلب ولا اعتماد ولا نطاق'),
    array('W11-FIX-10', 'SCHEMA', 'tre_cash_box · tre_cash_move',
          'بناء الصندوق النقدي وحركة الاوعية والرصيد المشتق منها',
          'TRS-10', 'كل حركة نقد بسطر موثق بمرجعه وفرق الصرف حركة مستقلة',
          'المقيس قبل الاصلاح: fin_bank_accounts تحمل البنوك وحدها بلا صندوق ولا حركة'),
    array('W11-FIX-11', 'SCHEMA', 'tre_transfer',
          'فصل التحويل بين اوعية الشركة عن الدفع لمستفيد',
          'TRS-11', 'التحويل ليس دفعا لمستفيد: مسار اخف بقاعدته وبتوقيع مفوض',
          'المقيس قبل الاصلاح: صفر جدول تحويل — والتحويل يمر مسار الدفع الكامل او لا يسجل'),
    array('W11-FIX-12', 'SCHEMA', 'tre_fx_deal',
          'بناء صفقة الصرف بسعرها الموثق وفرقها عن سعر الجدول',
          'TRS-12', 'الشراء والبيع الفعلي بسعر الصفقة الموثق وجدول الاسعار مرجع لا محل',
          'المقيس قبل الاصلاح: fin_fx_rates جدول اسعار فقط — ولا موضع لصفقة فعلية'),
    array('W11-FIX-13', 'SCHEMA', 'tre_instrument',
          'بناء سجل الادوات المالية بدورتها',
          'TRS-06', 'كل شيك اداة مسجلة بدورتها من الاستلام الى التحصيل او الارتجاع',
          'المقيس قبل الاصلاح: fin_payments.method تحمل cheque بلا دورة اداة ولا ارتجاع'),
    array('W11-FIX-14', 'SCHEMA', 'tre_guarantee',
          'بناء خطابات الضمان والاعتمادات على تسهيلها',
          'TRS-15', 'الاصدار على تسهيله وبقاعدته والكفالات النظامية عند الحوكمة',
          'المقيس قبل الاصلاح: fin_funding_facilities تحمل التسهيل بلا خطاب صادر عليه'),
    array('W11-FIX-15', 'SCHEMA', 'tre_recon_difference',
          'اخراج فرق المطابقة من حقل الى سطر بنوعه ومسؤوله واجرائه',
          'TRS-16', 'كل فرق سطر بنوعه وسببه ومسؤوله واجرائه حتى الاغلاق — لا فروق مدفونة في حقل',
          'المقيس قبل الاصلاح: bank_recon_matches.difference_reason حقل نصي واحد بلا مسؤول ولا اجراء'),
    array('W11-FIX-16', 'SCHEMA', 'tre_petty_custody · tre_petty_expense',
          'بناء عهدة النثرية بحدها وسقفها الزمني وبنودها بمستنداتها',
          'TRS-17', 'العهدة بحد وسقف زمني ولا تجديد قبل تسوية السابقة بمستنداتها',
          'المقيس قبل الاصلاح: صفر جدول عهدة نثرية — والنثرية تصرف كدفعة عادية'),
    array('W11-FIX-17', 'SCHEMA', 'tre_cash_count · tre_cash_count_line',
          'بناء الجرد النقدي بلجنته وفئاته وفرقه',
          'TRS-18', 'الجرد بلجنة لا بامين الصندوق وحده والفرق يعالج فورا بمساره',
          'المقيس قبل الاصلاح: صفر جدول جرد نقدي — ولا رصيد دفتري يقارن به المعدود'),
    array('W11-FIX-18', 'SCHEMA', 'tre_beneficiaries · عمودا القفل والتوثيق',
          'اقفال حساب المستفيد ضد التعديل بعد التحقق وربطه بمصدر توثيقه',
          'TRS-03', 'الحساب البنكي يوثق من مصدره ويقفل ضد التعديل',
          'المقيس قبل الاصلاح: verified_at وحده بلا قفل ولا مرجع توثيق — فالحساب يبدل بعد التحقق صامتا'),
    array('W11-FIX-19', 'SCHEMA', 'fin_journal_entries.entity_scope',
          'وسم القيد بنطاق كيانه فلا يخلط رقم كيانين بلا اعلان',
          'ACC-11', 'اي رقم يخلط كيانين يوسم Consolidated او يرفض',
          'المقيس قبل الاصلاح: لا موضع في القيد يميز رقم كيان من رقم مجموعة'),
);
foreach ($FIX as $f) {
    $W("INSERT INTO repair01_w11_fixes (fix_key,kind,target,what,revealed_by,reveal_why,evidence)
        VALUES ('" . $esc($f[0]) . "','" . $esc($f[1]) . "','" . $esc($f[2]) . "','" . $esc($f[3]) . "',
                '" . $esc($f[4]) . "','" . $esc($f[5]) . "','" . $esc($f[6]) . "')");
}
printf("  إصلاحاتٌ بكاشفِها %d\n\n", count($FIX));

echo "───────────────────────────────────────────────────────────────\n";
printf("الخلاصة: نطاق %d · سايدبار %d · حالات %d · فصلُ واجبات %d · عقود %d · عتبات %d · قرارات %d\n",
    (int) $one("SELECT COUNT(*) FROM repair01_w11_scope"),
    (int) $one("SELECT COUNT(*) FROM repair01_w11_sidebar"),
    (int) $one("SELECT COUNT(*) FROM repair01_w11_states"),
    (int) $one("SELECT COUNT(*) FROM repair01_w11_sod"),
    (int) $one("SELECT COUNT(*) FROM repair01_events WHERE wave = 'W11'"),
    (int) $one("SELECT COUNT(*) FROM repair01_w11_thresholds"),
    (int) $one("SELECT COUNT(*) FROM repair01_w11_decisions"));
