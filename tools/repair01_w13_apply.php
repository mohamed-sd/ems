<?php
/**
 * tools/repair01_w13_apply.php — قياسٌ وكتابةٌ للمرحلةِ الثالثةَ عشرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **السايدبارُ قبل الشاشات** (§٤ · RPR-PATCH-01 ③): الخطواتُ السبعُ بترتيبها
 *   على أسطحِ النطاقِ — وأسطحُ النموِّ تُسجَّل أوّلًا لأنّها جزءٌ من مقامِه.
 *
 * ⛔ `origin` = `W13` بالضبط (RPR-PATCH-02): أساسُ السجلِّ (٦٥١) مُجمَّدٌ،
 *   والنموُّ مسموحٌ **مختومًا وحدَه**.
 *
 * ◆ **والترتيبُ من دورةِ العملِ لا من الأبجديّة** (§٤-٤): `sort_no` يُشتَقُّ من
 *   `step` — موضعِ السطحِ من دورةِ الموظّفِ أو دورةِ البلاغِ — لا من اسمِ
 *   الملفِّ ولا من تاريخِ الإنشاء. و⛔ **لا مصفوفةَ بنودٍ مكتوبةٌ في صفحة**.
 *
 * ◆ **والفجوةُ اسمٌ مستهدَفٌ لا اسمُ ملفٍّ مُلزِم** (‏سوابقُ `W9-D-08` و
 *   `W11-D-11` و`W12-D-04`): ثلاثَ عشرةَ فجوةً لـ`DEP-07` و`DEP-10` أسطحُها
 *   **قائمةٌ فعلًا بأسماءٍ أخرى** — فتُقيَّد موفّاةً ⛔ **ولا يُمَسُّ صفُّ الشبحِ
 *   ولا `on_disk` ولا سجلُّ مرحلةٍ مُغلقة**.
 *
 * التشغيل: php tools/repair01_w13_apply.php [--report] [--revert]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w13_scan.php';
require_once $ROOT . '/app/Services/Ui/UiLabelRegistry.php';
require_once $ROOT . '/app/Core/TenantRegistry.php';
require_once $ROOT . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("تعذّر الاتصال بالقاعدة\n"); }
$conn->set_charset('utf8mb4');
while (ob_get_level()) { ob_end_clean(); }

$REPORT = in_array('--report', $argv, true);
$REVERT = in_array('--revert', $argv, true);
$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) { return repair01_w13_one($conn, $sql); };
$W = function ($sql) use ($conn, $REPORT) {
    if ($REPORT) { return true; }
    if ($conn->query($sql) === true) { return true; }
    echo '  ✘ ' . $conn->error . "\n  ⇐ " . mb_substr(preg_replace('/\s+/', ' ', $sql), 0, 180) . "\n";
    return false;
};

echo "══ REPAIR01 · W13 — " . ($REVERT ? 'إرجاع' : ($REPORT ? 'قياسٌ بلا كتابة' : 'قياسٌ وكتابة')) . " ══\n\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ⓪ الإرجاع — يُفرِّغ ما كتبته هذه الأداةُ وحدَها
   ═══════════════════════════════════════════════════════════════════════════ */
if ($REVERT) {
    foreach (repair01_w13_new_surfaces() as $s) {
        $rt = $esc($s['route']);
        $conn->query("DELETE FROM nav_items WHERE route = '$rt'");
        $conn->query("DELETE FROM nav_canonical WHERE route = '$rt'");
        $conn->query("DELETE FROM role_permissions WHERE module_id IN (SELECT id FROM modules WHERE code = '$rt')");
        $conn->query("DELETE FROM modules WHERE code = '$rt'");
        $conn->query("DELETE FROM repair01_screen_registry WHERE route = '$rt' AND origin = 'W13'");
        $conn->query("DELETE FROM gov_screen_cycle WHERE screen_file = '"
                     . $esc(basename($s['route'])) . "' AND inputs_note LIKE 'RPR-W13 %'");
        $conn->query("DELETE FROM gov_space_appearances WHERE route = '$rt' AND src_class = 'RPR-W13'");
    }
    /* **البندُ المنقولُ يعود إلى موضعِه قبلَ حذفِ مجموعتِه** — وإلّا بقي يشير
       إلى صفٍّ لا وجودَ له، فيقرأ المُصيِّرُ مجموعةً فارغةً ويسقط `U5`
       (‏درسُ W12 §١-⑤). */
    $back = 0;
    $rb = $conn->query("SELECT nav_item_id, from_group_id FROM repair01_w13_nav_moves");
    while ($rb && $bx = $rb->fetch_assoc()) {
        if ($conn->query("UPDATE nav_items SET group_id = " . (int) $bx['from_group_id']
                         . " WHERE id = " . (int) $bx['nav_item_id'])) { $back++; }
    }
    echo "  ✔ بنودٌ أُعيدت إلى موضعِها الأصليّ $back\n";
    $conn->query("DELETE FROM repair01_w13_nav_moves");
    $conn->query("DELETE FROM link_groups WHERE group_code LIKE 'n9o_w13\\_%'");
    $orphan = (int) repair01_w13_one($conn, "SELECT COUNT(*) FROM nav_items n
                                              LEFT JOIN link_groups g ON g.id = n.group_id
                                             WHERE n.group_id > 0 AND g.id IS NULL");
    echo '  ' . ($orphan === 0 ? '✔' : '✘') . " بندٌ يتيمٌ بعد الإرجاع $orphan\n";
    $conn->query("UPDATE repair01_target_gaps SET built_counterpart = ''
                   WHERE unit IN ('DEP-07','DEP-10') AND wave_stage = 'W13'");
    foreach (array('repair01_w13_scope', 'repair01_w13_sidebar', 'repair01_w13_decisions',
                   'repair01_w13_states', 'repair01_w13_sod', 'repair01_w13_thresholds',
                   'repair01_w13_fixes', 'repair01_w13_journey', 'repair01_w13_parties') as $t) {
        $conn->query("DELETE FROM `$t`");
    }
    $conn->query("DELETE FROM tkt_subject_type WHERE src_ref LIKE 'RPR-W13%'");
    $conn->query("DELETE FROM repair01_events WHERE wave = 'W13'");
    $conn->query("DELETE FROM repair01_w6_code_dict WHERE src_ref LIKE 'RPR-W13%'");
    /* ⛔ **وقرارا المالكِ لا يُقلبان بإرجاعِ أداة** — `DEC-OPEN-05` و
       `DEC-OPEN-16` جوابُهما قرارُ مالكٍ لا أثرُ تشغيل، وإرجاعُهما إلى
       `NEEDS_OWNER_DECISION` انتحالٌ لقرارِه بالمقلوب. فيبقيان كما اعتُمدا. */
    echo "الحكم: رجعت ✔ (والجداولُ تُنزع بهجرةِ التراجع · وقرارا المالكِ يبقيان)\n";
    exit(0);
}

/* ═══════════════════════════════════════════════════════════════════════════
   ① قاموسُ الرموزِ — الرمزُ يبقى لاتينيًّا ويُعرَض عربيًّا
   ═══════════════════════════════════════════════════════════════════════════
   ◆ **يُبذَر قبل تسجيلِ الأسطح**: السطحُ يعرض حالتَه من القاموسِ لحظةَ فتحِه،
     ورمزٌ بلا مسمًّى يُعرَض خامًّا فيسقط معيارُ نقاءِ اللغة.
   ⛔ **ولا يُدهَس مسمًّى سجّلَته موجةٌ سابقة**: `raw_code` مفتاحٌ عالميٌّ عبرَ
     الموجات، وتحديثُه هنا يغيّر معنى رمزٍ في شاشةٍ أخرى بلا أن يُقاس.
     فالكتابةُ **إدراجٌ لما غاب وحدَه**.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "① قاموسُ رموزِ النطاق ─────────────────────────────────────────\n";
$DICT = array(
    /* الأطرافُ الأربعةُ — وهي محورُ المرحلة */
    array('REPORTER', 'المبلغ', 'W13_PARTY'),
    array('SUBJECT', 'محل البلاغ', 'W13_PARTY'),
    array('TICKET_OWNER', 'مالك دورة التذكرة', 'W13_PARTY'),
    array('RESOLUTION_OWNER', 'مالك الحل', 'W13_PARTY'),
    /* التوجيهُ وإعادةُ الفتحِ وصفةُ المتحقّق */
    array('AUTO', 'توجيه الي بقاعدته', 'W13_ROUTE'),
    array('CENTER_CORRECTION', 'تصحيح من المركز بسببه', 'W13_ROUTE'),
    array('REPORTER_OBJECTION', 'اعتراض المبلغ', 'W13_REOPEN'),
    array('RECURRENCE', 'تكرار المشكلة', 'W13_REOPEN'),
    array('SPECIALIST', 'تحقق المختص', 'W13_VERIFY'),
    array('AUTO_WINDOW', 'انتهاء نافذة التحقق', 'W13_VERIFY'),
    /* حالاتُ دورةِ البلاغ */
    array('resolved', 'معالج', 'W13_STATE'),
    array('verification', 'قيد التحقق', 'W13_STATE'),
    array('verified', 'متحقق منه', 'W13_STATE'),
    array('reopened', 'اعيد فتحه', 'W13_STATE'),
    /* حالاتُ القضيّةِ التأديبيّةِ وقرارُها */
    array('incident', 'واقعة', 'W13_STATE'),
    array('investigation', 'تحقيق', 'W13_STATE'),
    array('decided', 'محسوم', 'W13_STATE'),
    array('appealed', 'مستانف', 'W13_STATE'),
    array('warning', 'انذار', 'W13_DECISION'),
    array('deduction', 'خصم', 'W13_DECISION'),
    array('suspension', 'ايقاف عن العمل', 'W13_DECISION'),
    array('termination', 'انهاء خدمة', 'W13_DECISION'),
    /* الحركاتُ الوظيفيّة */
    array('promotion', 'ترقية', 'W13_MOVE'),
    array('secondment', 'انتداب', 'W13_MOVE'),
    array('demotion', 'خفض درجة', 'W13_MOVE'),
    array('return', 'عودة من انتداب', 'W13_MOVE'),
    /* التهيئةُ والتدريبُ والتقييم */
    array('waived', 'مستثنى بتوثيق', 'W13_STATE'),
    array('planned', 'مخطط', 'W13_STATE'),
    array('in_progress', 'قيد التنفيذ', 'W13_STATE'),
    array('moderated', 'روجع', 'W13_STATE'),
    array('finalized', 'معتمد نهائيا', 'W13_STATE'),
    array('disputed', 'محل اعتراض', 'W13_STATE'),
    array('safety', 'سلامة', 'W13_TRAINING'),
    array('compliance', 'امتثال', 'W13_TRAINING'),
    array('technical', 'فني', 'W13_TRAINING'),
    array('admin', 'اداري', 'W13_TRAINING'),
    /* المستنداتُ والاشتراكات */
    array('valid', 'ساري المفعول', 'W13_STATE'),
    array('expiring', 'يقترب انتهاؤه', 'W13_STATE'),
    array('replaced', 'مستبدل', 'W13_STATE'),
    array('suspended', 'موقوف', 'W13_STATE'),
    array('ended', 'منته', 'W13_STATE'),
    /* أصنافُ الكيانِ في كتالوجِ محلِّ البلاغ */
    array('PERSON', 'شخص', 'W13_ENTITY'),
    array('ASSET', 'اصل', 'W13_ENTITY'),
    array('CONTRACT', 'عقد', 'W13_ENTITY'),
    array('SITE', 'موقع', 'W13_ENTITY'),
    array('ORG_UNIT', 'وحدة تنظيمية', 'W13_ENTITY'),
    array('DOCUMENT', 'مستند', 'W13_ENTITY'),
    array('rejected', 'مردود', 'W13_STATE'),
    array('applied', 'مطبق', 'W13_STATE'),
    /* ورموزُ الجداولِ الحيّةِ التي تُصيَّرها أسطحُ الموجة */
    array('received', 'وارد', 'W13_REC'),
    array('screening', 'فرز', 'W13_REC'),
    array('interview', 'مقابلة', 'W13_REC'),
    array('practical_test', 'اختبار عملي', 'W13_REC'),
    array('offer', 'عرض عمل', 'W13_REC'),
    array('offer_accepted', 'قبول العرض', 'W13_REC'),
    array('contracting', 'تعاقد', 'W13_REC'),
    array('onboarded', 'مباشرة', 'W13_REC'),
    array('probation', 'تحت التجربة', 'W13_REC'),
    array('confirmed', 'مثبت', 'W13_REC'),
    array('withdrawn', 'منسحب', 'W13_REC'),
    array('component', 'مكون اجر', 'W13_PAYLINE'),
    array('overtime', 'عمل اضافي', 'W13_PAYLINE'),
    array('absence_deduction', 'خصم غياب', 'W13_PAYLINE'),
    array('production', 'انتاج', 'W13_PAYLINE'),
    array('incentive', 'حافز', 'W13_PAYLINE'),
    array('computed', 'محتسب', 'W13_PAYLINE'),
    array('pending_slice', 'بانتظار شريحة', 'W13_PAYLINE'),
    array('blocked', 'محجوب', 'W13_PAYLINE'),
    array('system', 'النظام', 'W13_CHANNEL'),
    array('phone', 'هاتف', 'W13_CHANNEL'),
    array('field', 'ميداني', 'W13_CHANNEL'),
    array('sla_breach', 'تجاوز المهلة', 'W13_ESC'),
    array('reopen_threshold', 'تكرار اعادة الفتح', 'W13_ESC'),
    array('hold_overdue', 'تعليق تجاوز مدته', 'W13_ESC'),
);
$dictN = 0; $dictKept = 0;
foreach ($DICT as $d) {
    $have = (int) $one("SELECT COUNT(*) FROM repair01_w6_code_dict
                         WHERE raw_code = '" . $esc($d[0]) . "' AND display_ar <> ''");
    if ($have > 0) { $dictKept++; continue; }
    if ($W("INSERT INTO repair01_w6_code_dict
            (raw_code, display_ar, display_short, code_family, allowed_context, why, src_ref)
            VALUES ('" . $esc($d[0]) . "','" . $esc($d[1]) . "','" . $esc($d[1]) . "',
                    '" . $esc($d[2]) . "','SCREEN_CELL',
                    'قيمة تقارن في الشيفرة وفي CHECK فتبقى لاتينية وتعرض عربية من القاموس',
                    'RPR-W13 §١')
            ON DUPLICATE KEY UPDATE display_ar = VALUES(display_ar)")) { $dictN++; }
}
printf("  رموزٌ أُضيفت %d · مسمًّى سابقٌ حُفظ %d\n\n", $dictN, $dictKept);

/* ═══════════════════════════════════════════════════════════════════════════
   ①-ب · كتالوجُ أنواعِ محلِّ البلاغ — **المقامُ الذي يُقاس عليه الطرفُ الثاني**
   ═══════════════════════════════════════════════════════════════════════════
   ◆ «المركزُ يتعامل مع كلِّ الإدارات: لا قائمةَ ثابتةً قصيرة» (`TKT-03`) —
     فالكتالوجُ يُبذَر بأنواعٍ من إداراتٍ مختلفةٍ **كلٌّ بسجلِّه المرجعيّ**،
     ولا يُخترَع نوعٌ بلا جدولٍ يعود إليه.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "①-ب كتالوجُ أنواعِ محلِّ البلاغ ────────────────────────────────\n";
$SUBJ = array(
    array('EQUIPMENT', 'معدة', 'ASSET', 'equipments', 'id', 'DEP-04'),
    array('EMPLOYEE', 'موظف', 'PERSON', 'employees', 'id', 'DEP-07'),
    array('SITE', 'موقع عمل', 'SITE', 'projects', 'id', 'DEP-12'),
    array('CONTRACT', 'عقد عميل', 'CONTRACT', 'contracts', 'id', 'DEP-01'),
    array('ORG_UNIT', 'وحدة تنظيمية', 'ORG_UNIT', 'org_units', 'unit_id', 'DEP-07'),
    array('SUPPLIER', 'مورد', 'ORG_UNIT', 'suppliers', 'id', 'DEP-02'),
    array('WAREHOUSE_ITEM', 'صنف مخزني', 'ASSET', 'wh_items', 'id', 'DEP-17'),
    array('TRIP', 'رحلة نقل', 'DOCUMENT', 'trp_trips', 'id', 'DEP-15'),
);
$subjN = 0; $subjSkip = array();
$companies = array();
$rc = $conn->query("SELECT DISTINCT company_id FROM tickets WHERE company_id > 0");
while ($rc && $cx = $rc->fetch_row()) { $companies[] = (int) $cx[0]; }
if (!$companies) {
    $rc2 = $conn->query("SELECT id FROM companies ORDER BY id LIMIT 1");
    while ($rc2 && $cx2 = $rc2->fetch_row()) { $companies[] = (int) $cx2[0]; }
}
foreach ($SUBJ as $t) {
    /* **والسجلُّ المرجعيُّ يُثبَت من المخطَّطِ لا يُدَّعى** — نوعٌ يشير إلى جدولٍ
       لا وجودَ له كتالوجٌ يعِد ولا يفي (‏سابقةُ `NF-09` في حملةِ INJ-FIX-02). */
    if (!repair01_w13_table_exists($conn, $t[3])) { $subjSkip[] = $t[0] . ' ⇐ ' . $t[3]; continue; }
    foreach ($companies as $cid) {
        if ($W("INSERT INTO tkt_subject_type
                (company_id, type_code, name_ar, entity_kind, ref_table, ref_key, owner_dept, active, why, src_ref)
                VALUES ($cid,'" . $esc($t[0]) . "','" . $esc($t[1]) . "','" . $esc($t[2]) . "',
                        '" . $esc($t[3]) . "','" . $esc($t[4]) . "','" . $esc($t[5]) . "',1,
                        'محل البلاغ نوع بسجله المرجعي لا نص حر في راس البلاغ','RPR-W13 §١-ب')
                ON DUPLICATE KEY UPDATE name_ar = VALUES(name_ar), ref_table = VALUES(ref_table),
                  ref_key = VALUES(ref_key), owner_dept = VALUES(owner_dept), active = 1")) { $subjN++; }
    }
}
printf("  أنواعُ محلِّ بلاغٍ مسجَّلة %d · كياناتٌ %d · بلا سجلٍّ مرجعيٍّ %d%s\n\n",
    $subjN, count($companies), count($subjSkip),
    $subjSkip ? ' ⇐ ' . implode('، ', $subjSkip) : '');

/* ═══════════════════════════════════════════════════════════════════════════
   ② أسطحُ النموِّ — واحدٌ وعشرون سطحًا مختومةً بموجتِها
   ═══════════════════════════════════════════════════════════════════════════ */
echo "② أسطحُ النموِّ — مختومةٌ بـW13 ─────────────────────────────────\n";
$newN = 0; $navN = 0; $permN = 0; $labelN = 0; $missing = array();
$maxSid = (int) preg_replace('/\D/', '', (string) $one("SELECT screen_id FROM repair01_screen_registry
                                                          ORDER BY screen_id DESC LIMIT 1"));
foreach (repair01_w13_new_surfaces() as $s) {
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

    /* ⓑ المنحُ — لكلِّ دورٍ يرى الشقيقَ اليوم؛ فالبلوغُ يُقاس ولا يُخترع.
       ⚠ **والشقيقُ يُقاس ببلوغِه لا بقربِه الموضوعيّ** (‏درسُ W12 §١-③):
         شقيقٌ بصفرِ منحٍ ينتج صفرًا، فالسطحُ يُبنى ولا يبلغه أحد. */
    if ($modId > 0) {
        $sibMod = (int) $one("SELECT id FROM modules WHERE code = '" . $esc($s['sibling']) . "' LIMIT 1");
        if ($sibMod > 0) {
            $sibGrants = (int) $one("SELECT COUNT(*) FROM role_permissions WHERE module_id = $sibMod AND can_view = 1");
            if ($sibGrants === 0) { echo '  ⚠ شقيقُ ' . $s['route'] . " بصفرِ منح — البلوغُ يبقى صفرًا\n"; }
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
            'rule_id' => 'W13_NEW_SURFACE_LABEL', 'origin' => 'W13',
            'src_ref' => 'RPR-W13 §٤ · سطحُ نموٍّ مختوم', 'caller' => 'repair01_w13_apply.php',
        ));
        if (!$lr['ok']) { echo '  ⚠ رُدَّ مسمّى ' . $s['route'] . ' — ' . $lr['code'] . ': ' . $lr['detail'] . "\n"; }
        else { $labelN++; }
        $gr = \App\Services\Ui\UiLabelRegistry::register($conn, 'group:w13:' . strtolower($s['group']),
            repair01_w13_group_ar($s['group']), array(
            'allowed_context' => 'SIDEBAR', 'source_table' => 'nav_canonical', 'source_column' => 'group_name',
            'source_key' => $s['group'], 'owner_code' => $s['owner'],
            'visibility_class' => 'USER_VISIBLE', 'label_state' => 'ACTIVE',
            'rule_id' => 'W13_CYCLE_GROUP_LABEL', 'origin' => 'W13',
            'src_ref' => 'RPR-W13 §٤ · مجموعةُ دورةِ العمل', 'caller' => 'repair01_w13_apply.php',
        ));
        if ($gr['ok']) { $labelN++; }
    }

    /* ⓓ السجلُّ المعياريُّ للتنقُّل — والترتيبُ من موضعِ السطحِ في الدورة */
    $sid = (string) $one("SELECT screen_id FROM repair01_screen_registry WHERE route = '$rt' LIMIT 1");
    if ($sid === '') { $maxSid++; $sid = 'SCR-' . str_pad((string) $maxSid, 4, '0', STR_PAD_LEFT); }
    $W("INSERT INTO nav_canonical (route, canonical_ar, level_no, level_name, group_name, sort_no,
                                   status, decision_state, application_state, decision_source,
                                   derivation, retirement_status, screen_id)
        VALUES ('$rt','" . $esc($s['ar']) . "',2,'العمليات','" . $esc(repair01_w13_group_ar($s['group'])) . "',"
                . (int) $s['sort'] . ",
                'APPROVED','APPROVED','DEPLOYED','RPR-W13 · الموارد البشرية والبلاغات (2026-08-26)',
                'ترتيب دورة الموظف ودورة البلاغ في الحزمة','ACTIVE','" . $esc($sid) . "')
        ON DUPLICATE KEY UPDATE canonical_ar=VALUES(canonical_ar), group_name=VALUES(group_name),
          sort_no=VALUES(sort_no), status=VALUES(status), screen_id=VALUES(screen_id)");

    /* ⓔ **مجموعةُ الدورةِ لا مجموعةُ الشقيق** — والمُعرِّفُ من `screen_id` لا
         من المسار: `link_groups.group_code` عرضُه محدود، والاشتقاقُ من المسارِ
         يبتُر ويتصادم (‏عطبُ W07 المقيس). */
    if ($modId > 0) {
        $gkey = 'n9o_w13_' . strtolower(str_replace('-', '', $sid));
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
                    VALUES ('" . $esc(repair01_w13_group_ar($s['group'])) . "','" . $esc($code) . "',$rid,
                            '" . $esc($s['icon']) . "'," . ((int) $sx['display_order'] + 1) . ","
                            . (int) $sx['stage_no'] . ",'" . $esc((string) $sx['stage_title']) . "',1)");
                $gid = (int) $one("SELECT id FROM link_groups WHERE group_code = '" . $esc($code) . "' LIMIT 1");
            } else {
                $W("UPDATE link_groups SET name = '" . $esc(repair01_w13_group_ar($s['group'])) . "',
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
                 WHERE screen_file = '" . $esc($file) . "' AND inputs_note LIKE 'RPR-W13 %'");
            $W("INSERT INTO gov_screen_cycle
                (company_id, dept_name, layer_name, stage_order, stage_name, group_name, screen_title,
                 screen_file, inputs_note, output_doc, resp_role, next_state, consumers, fin_impact, stage_kind)
                VALUES (0,'" . $esc($deptAr) . "','" . $esc(repair01_w13_group_ar($s['group'])) . "','"
                        . (int) $s['sort'] . "','" . $esc(repair01_w13_group_ar($s['group'])) . "','"
                        . $esc(repair01_w13_group_ar($s['group'])) . "',
                        '" . $esc($s['ar']) . "','" . $esc($file) . "',
                        '" . $esc('RPR-W13 · متطلبات: ' . $s['req']) . "','" . $esc($s['doc']) . "',
                        '" . $esc($s['role']) . "','" . $esc($s['next']) . "','" . $esc($s['cons']) . "',
                        '" . $esc($s['fin']) . "','canonical')");
        }
    }

    /* ⓖ سجلُّ الشاشاتِ — بختمِ الموجةِ لا بلا ختم */
    $guard = repair01_w13_guard_of($ROOT, $s['route']);
    $W("INSERT INTO repair01_screen_registry
        (screen_id, screen_file, route, route_rule, owner_code, owner_role, owner_rule,
         lifecycle, lifecycle_rule, parent_screen_id, parent_rule, visibility_class, visibility_rule,
         on_disk, origin, guard_kind, guard_evidence, w2_why, src_ref)
        VALUES ('" . $esc($sid) . "','" . $esc($file) . "','$rt','W13_NEW_SURFACE_ROUTE',
                '" . $esc($s['owner']) . "','" . $esc($s['role']) . "','W13_REQUIREMENT_OWNER',
                'LIVE_UNREGISTERED','W13_GROWTH_OUTSIDE_STUDY_MATRIX','','','MENU_ITEM','NAV_ITEMS_ACTIVE',
                1,'W13','" . $esc($guard['kind']) . "','" . $esc($guard['evidence']) . "',
                '" . $esc($s['ar']) . " (" . $esc($file) . ")','RPR-W13 · الموارد البشرية والبلاغات')
        ON DUPLICATE KEY UPDATE owner_code=VALUES(owner_code), owner_role=VALUES(owner_role),
          visibility_class=VALUES(visibility_class), guard_kind=VALUES(guard_kind),
          guard_evidence=VALUES(guard_evidence), origin='W13', on_disk=1");
    $newN++;
}
printf("  أسطحُ نموٍّ مختومةٌ %d · بنودُ قائمةٍ نشِطة %d · منحٌ %d · مسمّياتٌ مسجَّلة %d · بلا ملفٍّ %d%s\n\n",
    $newN, $navN, $permN, $labelN, count($missing),
    $missing ? ' ⇐ ' . implode('، ', $missing) : '');

/* ═══════════════════════════════════════════════════════════════════════════
   ②-ب · مصفوفةُ الواجهة — **السطحُ المُصيَّرُ يلزمه صفٌّ فيها** (‏`U1`)
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
    foreach (repair01_w13_new_surfaces() as $s) { $mine[strtolower($s['route'])] = $s; }
    foreach ($lines as $ln) {
        if (trim($ln) === '') { continue; }
        $cells = str_getcsv($ln);
        if (!$cells || count($cells) < 2) { continue; }
        $maxN = max($maxN, (int) $cells[0]);
        if (isset($mine[strtolower(trim($cells[1]))])) { continue; }
        $keep[] = $ln;
    }
    $cell = function ($v) {
        $v = (string) $v;
        if ($v === '') { return '""'; }
        if (preg_match('/[",\s]/u', $v)) { return '"' . str_replace('"', '""', $v) . '"'; }
        return $v;
    };
    $rows = array();
    foreach (repair01_w13_new_surfaces() as $s) {
        $maxN++;
        $grp = repair01_w13_group_ar($s['group']);
        $depAr = (string) $one("SELECT name_ar FROM repair01_departments WHERE canonical_code = '"
                               . $esc($s['owner']) . "'");
        if ($depAr === '') { $depAr = $s['owner']; }
        $def = 'تعرض ' . $s['ar'] . ' في دورة ' . $grp . ' لدى ' . $depAr
             . '. المستند الناتج ' . $s['doc'] . ' والخطوة التالية ' . $s['next'] . '.';
        $vals = array($maxN, $s['route'], $s['ar'], $s['ar'], '', '—', $def, $depAr,
            '2 — العمليات', $grp, $s['sort'], 'شاشةٌ مستقلة', 1, $s['cons'],
            'قدرةٌ ثبت غيابُها فبُنيت في موضعِها المعياريّ', 'APPROVED',
            'ترتيبُ دورةِ العملِ في الحزمة — RPR-W13', '—', '—', 'ACTIVE', '—',
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
if (!repair01_w13_table_exists($conn, 'gov_space_appearances')) {
    echo "  ⚠ سجلُّ المساحاتِ غيرُ موجود — التصنيفُ يُتخطّى\n";
} else {
    foreach (repair01_w13_new_surfaces() as $s) {
        $rt = $esc($s['route']);
        $depAr2 = (string) $one("SELECT name_ar FROM repair01_departments WHERE canonical_code = '"
                                . $esc($s['owner']) . "'");
        if ($depAr2 === '') { $depAr2 = $s['owner']; }
        $W("DELETE FROM gov_space_appearances WHERE route = '$rt' AND src_class = 'RPR-W13'");
        /* ⚠ **المفتاحُ هنا لا يتزايد ذاتيًّا** — فيُشتقُّ من أقصى القائمِ في
             كلِّ صفٍّ لا مرّةً واحدة (‏درسُ W11). */
        $nextId = (int) $one("SELECT COALESCE(MAX(id), 0) + 1 FROM gov_space_appearances");
        $W("INSERT INTO gov_space_appearances
            (id, space_ar, space_kind, tab_ar, screen_ar, route, owner_dept_ar, owner_kind,
             src_class, src_ownership, src_decision, src_note, spaces_count,
             cls, ownership, decision, basis, rule_step, view_fields, updated_at)
            VALUES ($nextId,'" . $esc($depAr2) . "','DEPARTMENT','','" . $esc($s['ar']) . "','$rt',
                    '" . $esc($depAr2) . "','BUSINESS_DEPARTMENT',
                    'RPR-W13','VALID','CONFIRMED',
                    '" . $esc('سطح نمو مختوم W13 - صنف بسلم الحسم السداسي') . "',1,
                    'OWNED','VALID','CONFIRMED',
                    '" . $esc('المساحة هي الادارة المالكة للسطح في السجل المعياري') . "',
                    1,'',NOW())");
        $spaceN++;
    }
}
printf("  أسطحٌ مصنَّفةٌ في سجلِّ المساحات %d\n\n", $spaceN);

/* ═══════════════════════════════════════════════════════════════════════════
   ②-د · فجواتُ النطاقِ — **موفّاةٌ باسمٍ آخرَ لا مبنيّةٌ توأمًا**
   ═══════════════════════════════════════════════════════════════════════════
   ◆ سوابقُ `W9-D-08` و`W11-D-11` و`W12-D-04`: **الفجوةُ اسمٌ مستهدَفٌ لا اسمُ
     ملفٍّ مُلزِم**. وثلاثَ عشرةَ فجوةً لـ`DEP-07` و`DEP-10` أسطحُها **قائمةٌ
     فعلًا** بأسماءٍ أخرى — فبناؤها بأسمائها يصنع توأمًا لا قدرة.
   ⛔ **ولا يُمَسُّ صفُّ الشبحِ ولا `on_disk` ولا سجلُّ مرحلةٍ مُغلقة**.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "②-د الفجواتُ — موفّاةٌ باسمٍ آخرَ لا توأمًا ─────────────────────\n";
$GAPFILL = array(
    'advances.php'          => 'Workforce/employee_advances.php',
    'emp_contracts.php'     => 'Employees/employee_contracts.php',
    'equip_docs.php'        => 'Equipments/equipment_documents.php',
    'leaves.php'            => 'Workforce/worker_leave_absence.php',
    'op_assign.php'         => 'Employees/equipment_operators.php',
    'op_deduct.php'         => 'Workforce/proposed_deductions.php',
    'operators.php'         => 'Oprators/oprators.php',
    'recruitment.php'       => 'Workforce/recruitment_pipeline.php',
    'ticket_config.php'     => 'Tickets/ticket_types_config.php',
    'ticket_escalate.php'   => 'Tickets/ticket_escalation_config.php',
    'ticket_periodic.php'   => 'Tickets/ticket_recurrence.php',
    'ticket_route.php'      => 'Tickets/intake_classify.php',
    'tickets.php'           => 'Tickets/tickets_list.php',
);
$gapN = 0; $gapMiss = array();
$rg = $conn->query("SELECT id, surface_name FROM repair01_target_gaps
                     WHERE unit IN ('DEP-07','DEP-10') AND wave_stage = 'W13' ORDER BY id");
while ($rg && $g = $rg->fetch_assoc()) {
    $hit = '';
    foreach ($GAPFILL as $ghost => $built) {
        if (mb_strpos((string) $g['surface_name'], $ghost) !== false) { $hit = $built; break; }
    }
    if ($hit === '') { $gapMiss[] = (string) $g['id']; continue; }
    /* **والموفّى يُثبَت من القرصِ لا يُدَّعى** */
    if (!is_file($ROOT . '/' . preg_replace('/\?.*$/', '', $hit))) {
        $gapMiss[] = (string) $g['id'] . ' (لا ملف)'; continue;
    }
    $W("UPDATE repair01_target_gaps SET built_counterpart = '" . $esc($hit) . "'
         WHERE id = " . (int) $g['id']);
    $gapN++;
}
$iafGaps = (int) $one("SELECT COUNT(*) FROM repair01_target_gaps WHERE unit = 'IAF' AND wave_stage = 'W13'");
printf("  فجواتٌ قُيِّدت موفّاةً %d · بلا موفٍّ %d%s · فجواتُ IAF المختومةُ W13 %d (‏خارجَ متطلَّباتِ المرحلة — W13-D-08)\n\n",
    $gapN, count($gapMiss), $gapMiss ? ' ⇐ ' . implode('، ', $gapMiss) : '', $iafGaps);

/* ═══════════════════════════════════════════════════════════════════════════
   ③ نطاقُ المرحلة — ٣٥ متطلَّبًا إلى مِرساتِها المُثبَتةِ قياسًا
   ═══════════════════════════════════════════════════════════════════════════ */
echo "③ نطاقُ المرحلة ───────────────────────────────────────────────\n";
$W("DELETE FROM repair01_w13_scope");
$ANCH = repair01_w13_anchors();
$anchored = 0; $unproven = array(); $ownerMismatch = array(); $unscoped = array();
$newRoutes = array_column(repair01_w13_new_surfaces(), 'route');
/* **أيُّ طرفٍ من الأربعةِ يمسُّه السطح** — والمِحورُ يُعلَن على المتطلَّبِ لا
   يُستنتَج من اسمِه، فبوّابةُ الأطرافِ تقيس مقامًا مُعلَنًا لا مُخمَّنًا. */
$PARTY = array(
    'TKT-03' => 'SUBJECT', 'TKT-04' => 'REPORTER', 'TKT-05' => 'RESOLUTION_OWNER',
    'TKT-06' => 'RESOLUTION_OWNER', 'TKT-07' => 'RESOLUTION_OWNER',
    'TKT-08' => 'TICKET_OWNER', 'TKT-09' => 'TICKET_OWNER', 'TKT-10' => 'REPORTER',
    'TKT-11' => 'TICKET_OWNER', 'TKT-01' => 'TICKET_OWNER', 'TKT-02' => 'TICKET_OWNER',
);
/* **ومالكُ تنفيذِ الحلِّ ليس البلاغات** — يُعلَن على كلِّ متطلَّبِ بلاغٍ ينفَّذ
   حلُّه في إدارةٍ مختصّة، ويُقاس أنَّه ليس `DEP-10` أبدًا. */
$RESOWN = array(
    'TKT-05' => 'DEPT_OF_SUBJECT', 'TKT-06' => 'DEPT_OF_SUBJECT',
    'TKT-07' => 'DEPT_OF_SUBJECT', 'TKT-11' => 'DEPT_OF_SUBJECT',
);

$rq = $conn->query("SELECT requirement_id, unit, group_name, surface, src_ref
                      FROM repair01_requirements WHERE stage_no = 13 ORDER BY unit, seq");
while ($rq && $q = $rq->fetch_assoc()) {
    $rid = $q['requirement_id'];
    $dept = preg_match('/^(\d{2})\s/u', $q['unit'], $mm) ? 'DEP-' . $mm[1] : '';
    if (!isset($ANCH[$rid])) { $unproven[] = $rid . ' (بلا مِرساةٍ مُعلَنة)'; continue; }
    $a = $ANCH[$rid];
    $pr = repair01_w13_prove_anchor($conn, $ROOT, $a);
    if ($pr['verdict'] === 'ANCHORED') { $anchored++; }
    else { $unproven[] = $rid . ' (' . $pr['verdict'] . ')'; }

    $verdictOwner = ($pr['owner'] !== '' && $dept !== '' && $pr['owner'] !== $dept) ? 'MISMATCH' : 'MATCH';
    if ($verdictOwner === 'MISMATCH') { $ownerMismatch[] = $rid . ' ' . $pr['owner'] . ' بدل ' . $dept; }
    $build = in_array($a['route'], $newRoutes, true) ? 'BUILT_W13' : 'LIVE';
    /* **الحبّةُ تُقاس لا تُدَّعى**: الجدولُ يحمل الكيانَ غيرَ قابلٍ للعدمِ أو لا */
    $scoped = ($a['kind'] === 'TABLE' && repair01_w13_entity_scoped($conn, $a['probe'])) ? 1 : 0;
    if ($a['kind'] === 'TABLE' && $scoped === 0) { $unscoped[] = $rid . ' ⇐ ' . $a['probe']; }

    $W("INSERT INTO repair01_w13_scope
        (requirement_id,unit,group_name,surface,anchor_screen_id,anchor_route,anchor_probe,
         owner_measured,owner_expected,owner_verdict,build_verdict,cycle_step,entity_scoped,
         party_axis,resolution_owner,map_rule,map_why,src_ref)
        VALUES ('" . $esc($rid) . "','" . $esc($q['unit']) . "','" . $esc($q['group_name']) . "',
                '" . $esc($q['surface']) . "','" . $esc($pr['sid']) . "','" . $esc($a['route']) . "',
                '" . $esc($a['probe']) . "','" . $esc($pr['owner']) . "','" . $esc($dept) . "',
                '" . $esc($verdictOwner) . "','" . $esc($build) . "'," . (int) $a['step'] . ",$scoped,
                '" . $esc(isset($PARTY[$rid]) ? $PARTY[$rid] : '') . "',
                '" . $esc(isset($RESOWN[$rid]) ? $RESOWN[$rid] : '') . "',
                '" . $esc($pr['rule']) . "','" . $esc($a['why']) . "','" . $esc($q['src_ref']) . "')");
}
printf("  مُثبَتٌ من القرص %d · غيرُ مُثبَتٍ %d%s · مالكٌ مخالفٌ %d%s · جدولٌ بلا كيانٍ إلزاميٍّ %d%s\n\n",
    $anchored, count($unproven), $unproven ? ' ⇐ ' . implode('، ', array_slice($unproven, 0, 3)) : '',
    count($ownerMismatch), $ownerMismatch ? ' ⇐ ' . implode('، ', array_slice($ownerMismatch, 0, 3)) : '',
    count($unscoped), $unscoped ? ' ⇐ ' . implode('، ', array_slice($unscoped, 0, 3)) : '');

/* ═══════════════════════════════════════════════════════════════════════════
   ③-ب · الخطوةُ السابعةُ لأسطحِ النطاقِ القائمة — الربطُ بالسجلِّ المعياريّ
   ═══════════════════════════════════════════════════════════════════════════ */
echo "③-ب ربطُ أسطحِ النطاقِ بالسجلِّ المعياريّ ────────────────────────\n";
$linkFix = 0; $seen = array();
$rq2 = $conn->query("SELECT requirement_id, surface, group_name FROM repair01_requirements
                      WHERE stage_no = 13 ORDER BY unit, seq");
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
    /* **والاسمُ المعياريُّ يُنقّى قبل أن يُصيَّر** — تسميةُ الحزمةِ قد تحمل
       لاحقةَ نطاقٍ بشرطةٍ («بحسب انطباق الشركة») وهي ملاحظةُ نطاقٍ لا اسمُ
       شاشة، ⛔ وشرحٌ في اسمٍ مُصيَّرٍ يخرق معيارَ نقاءِ اللغة. */
    $label = trim(preg_split('~\s+—\s+~u', (string) $q2['surface'])[0]);
    if (!$REPORT) {
        $lr2 = \App\Services\Ui\UiLabelRegistry::register($conn, 'screen:' . strtolower($rt),
            $label, array(
            'allowed_context' => 'SIDEBAR SCREEN_TITLE',
            'source_table' => 'nav_canonical', 'source_column' => 'canonical_ar',
            'source_key' => $rt, 'owner_code' => $own !== '' ? $own : 'DEP-07',
            'visibility_class' => 'USER_VISIBLE', 'label_state' => 'ACTIVE',
            'rule_id' => 'W13_SCOPE_SURFACE_LABEL', 'origin' => 'W13',
            'src_ref' => 'RPR-W13 §٣-ب · ربطُ سطحٍ قائمٍ بالسجلِّ المعياريّ',
            'caller' => 'repair01_w13_apply.php',
        ));
        if (!$lr2['ok']) { echo '  ⚠ رُدَّ مسمّى ' . $rt . ' — ' . $lr2['code'] . "\n"; }
    }
    $W("INSERT INTO nav_canonical (route, canonical_ar, level_no, level_name, group_name, sort_no,
                                   status, decision_state, application_state, decision_source,
                                   derivation, retirement_status, screen_id, placement_kind)
        VALUES ('$rtE','" . $esc($label) . "',2,'العمليات','"
                . $esc(repair01_w13_group_ar($q2['group_name'])) . "'," . $sortNo . ",
                'APPROVED','APPROVED','DEPLOYED','RPR-W13 · ربط سطح النطاق بالسجل المعياري (2026-08-26)',
                'التسمية المعيارية من repair01_requirements.surface','ACTIVE','" . $esc($sid) . "',
                '" . $esc($vis === 'TAB_CHILD' ? 'TAB' : 'MENU_ITEM') . "')");
    echo "  ✔ $rt ⇐ " . $label . " · ترتيب $sortNo\n";
    $linkFix++;
}
printf("  أسطحٌ رُبطت بالسجلِّ المعياريّ %d\n\n", $linkFix);

/* ═══════════════════════════════════════════════════════════════════════════
   ③-ج · الخطوتانِ ② و③ **تصحيحٌ لا قياس** — الاسمُ ثمَّ المجموعةُ ثمَّ الترتيب
   ═══════════════════════════════════════════════════════════════════════════
   ◆ **محوَرانِ منفصلانِ ولا يُخلطان** (‏درسُ `nav-group-key-is-route-not-role`):
     محورُ **الاسمِ** مرجعُه `nav_canonical.canonical_ar` — وهو معتمَدٌ بقرارِ
     مالكِه، فـ⛔ **لا يُعاد تسميتُه هنا** (‏`W6-D-09`)؛ والتصحيحُ يمضي في
     الاتّجاهِ الصحيح: **البندُ الحيُّ يتبع المعياريَّ** لا العكس.
   ◆ ومحورُ **المجموعةِ** مرجعُه **دورةُ العمل** (`repair01_requirements.group_name`).
   ⛔ **ولا يُعاد تسميةُ مجموعةٍ مشتركة** (‏عطبُ W12 الثاني): الصفُّ الواحدُ في
     `link_groups` قد تحمله عدّةُ مسارات، وتسميتُه باسمِ دورةِ أحدِها تدهس
     مجموعةَ الآخرَين ثمَّ يعيدها تصحيحُهم — فيتأرجح الاسمُ ولا يستقرّ.
     فالسطحُ **يُنقَل إلى مجموعةٍ مختومةٍ بموجتِه** والمشتركةُ تبقى لأهلِها.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "③-ج تصحيحُ الاسمِ ثمَّ المجموعةِ ثمَّ الترتيبِ على دورةِ العمل ──────\n";
$lblFix = 0; $grpFix = 0; $grpCanonFix = 0; $ordFix = 0;
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
                              WHERE requirement_id = '" . $esc($rid) . "' AND stage_no = 13 LIMIT 1");
    if ($reqGrp === '') { continue; }
    $grpAr = repair01_w13_group_ar($reqGrp);
    /* ① المعياريُّ يتبع دورةَ العمل */
    $canGrp = (string) $one("SELECT group_name FROM nav_canonical WHERE route = '$rtE' LIMIT 1");
    if ($canGrp !== '' && $canGrp !== $grpAr) {
        $W("UPDATE nav_canonical SET group_name = '" . $esc($grpAr) . "' WHERE route = '$rtE'");
        $grpCanonFix++;
    }
    /* ①-ب **والترتيبُ من دورةِ العملِ لا من تاريخِ الإنشاء** (§٤-٤) */
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
    /* ③ ومجموعةُ البندِ الحيِّ تتبع مجموعةَ الدورةِ بنقلٍ لا بتسمية */
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
        $code3 = 'n9o_w13_' . strtolower(str_replace('-', '', $sid3)) . '_r' . $rid3;
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
        $W("INSERT INTO repair01_w13_nav_moves
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
$W("DELETE FROM repair01_w13_sidebar");
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
    $s4 = ($s4no > 0 || (int) $step === 0) ? 'ORDER_FROM_CYCLE' : 'NO_ORDER_SOURCE';
    $s5 = ((string) $reg['visibility_class'] === 'TAB_CHILD') ? 'TAB_IN_PARENT' : 'MENU_ITEM';
    $permRows = (int) $one("SELECT COUNT(*) FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                             WHERE m.code = '$rtE' AND rp.can_view = 1");
    $guard = repair01_w13_guard_of($ROOT, $rt);
    $s6 = ($guard['kind'] !== 'NONE' && $permRows > 0) ? 'GUARDED_AND_GRANTED'
        : ($guard['kind'] === 'NONE' ? 'NO_SERVER_GUARD' : 'NO_GRANT');
    $s7 = ($can && (string) $can['canonical_ar'] !== '' && $sid !== '') ? 1 : 0;

    $W("INSERT INTO repair01_w13_sidebar
        (screen_id,route,owner_code,s1_verdict,s1_rule,s2_label_live,s2_label_canon,s2_verdict,s2_rule,
         s3_group_live,s3_group_canon,s3_verdict,s3_rule,s4_order_src,s4_order_no,s4_cycle_step,
         s4_verdict,s4_rule,s5_parent,s5_verdict,s5_rule,s5_why,s6_visibility,s6_perm_rows,
         s6_guard_kind,s6_verdict,s6_rule,s7_linked,s7_verdict,s7_rule,measured_at)
        VALUES ('" . $esc($sid) . "','$rtE','" . $esc($reg['owner_code']) . "',
                '" . $esc($s1) . "','W13_S1_ACTIVE_BY_TARGET',
                '" . $esc($s2live) . "','" . $esc($s2can) . "','" . $esc($s2) . "','W13_S2_LABEL_FROM_REQUIREMENT',
                '" . $esc($s3live) . "','" . $esc($s3can) . "','" . $esc($s3) . "','W13_S3_GROUP_FROM_CYCLE',
                'nav_canonical.sort_no'," . $s4no . "," . (int) $step . ",
                '" . $esc($s4) . "','W13_S4_ORDER_FROM_CYCLE',
                '','" . $esc($s5) . "','W13_S5_PARENT_FROM_DECISION','موضعُ السطحِ من قرارِ الورقةِ لا من الذوق',
                '" . $esc((string) $reg['visibility_class']) . "'," . $permRows . ",
                '" . $esc($guard['kind']) . "','" . $esc($s6) . "','W13_S6_GUARD_AND_GRANT',
                " . $s7 . ",'" . ($s7 ? 'LINKED' : 'NOT_LINKED') . "','W13_S7_CANONICAL_SCREEN_ID',NOW())");
    $sbN++;
}
printf("  أسطحٌ مقيسةٌ بسبعِ خطوات %d · بلا صفٍّ في السجلّ %d\n\n", $sbN, $sbBad);

/* ═══════════════════════════════════════════════════════════════════════════
   ⑤ العتباتُ — من السجلِّ لا من الشيفرة
   ═══════════════════════════════════════════════════════════════════════════
   ◆ **وجوابُ `DEC-OPEN-05`** يسكن هنا لا في `if` مكتوبٍ في خدمة: نافذةُ
     التحقّقِ **مؤقّتٌ قابلُ الضبط** بقيمتَين بحسبِ الأولويّة.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑤ العتبات ────────────────────────────────────────────────────\n";
$W("DELETE FROM repair01_w13_thresholds");
$TH = array(
    array('TKT_VERIFY_WINDOW_NORMAL_H', 72, 'ساعة', 'نافذة التحقق للبلاغ العادي قبل الاغلاق',
          'بعدها يغلق بتحقق النافذة وتبقى الواقعة مسجلة بصفة المتحقق', 'DEC-OPEN-05'),
    array('TKT_VERIFY_WINDOW_CRITICAL_H', 48, 'ساعة', 'نافذة التحقق للبلاغ الحرج',
          'اقصر للحرج ولا اغلاق الي له - التحقق بشخص لا بمرور الوقت', 'DEC-OPEN-05'),
    array('TKT_ESCALATION_MAX_LEVEL', 5, 'مستوى', 'سقف سلم التصعيد',
          'التصعيد سلم معلن لا رقم مفتوح - وما فوق السقف يخرج عن السلم فيرد', 'W13-D-03'),
    array('TKT_REOPEN_WINDOW_DAYS', 30, 'يوم', 'مهلة قبول اعادة الفتح باعتراض المبلغ',
          'بعدها الاعتراض بلاغ جديد يحمل مرجع سابقه لا اعادة فتح', 'W13-D-03'),
    array('TKT_ASSIGN_ACK_HOURS', 12, 'ساعة', 'مهلة استلام المكلف للاسناد',
          'لا مكلف بلا وقت استلام - وتجاوزها يفتح تصعيدا لا يبتلع صامتا', 'W13-D-03'),
    array('HR_DOC_EXPIRY_ALERT_DAYS', 30, 'يوم', 'ايام التنبيه قبل انتهاء مستند الزامي',
          'التنبيه قبل الانتهاء لا بعده - والمنتهي يعلم الملف', 'W13-D-04'),
    array('HR_TRAINING_EXPIRY_ALERT_DAYS', 45, 'يوم', 'ايام التنبيه قبل انتهاء تدريب الزامي',
          'تدريب السلامة والامتثال يتابع بصلاحيته لا باكتماله وحده', 'W13-D-04'),
    array('HR_PROBATION_REVIEW_DAYS', 7, 'يوم', 'ايام التنبيه قبل انتهاء فترة التجربة',
          'قرار التثبيت يسبق انتهاء التجربة ولا يتخذ بعد فواتها', 'W13-D-04'),
    array('HR_INVESTIGATION_MAX_DAYS', 10, 'يوم', 'مهلة انجاز التحقيق التاديبي',
          'المهلة تحمي الموظف من تحقيق مفتوح بلا نهاية وتوثق التجاوز', 'W13-D-05'),
    array('HR_SETTLEMENT_CLEARANCE_DAYS', 14, 'يوم', 'مهلة ابراء العهد قبل صرف التصفية',
          'لا صرف قبل ابراء العهد وتسوية السلف - والمهلة تقاس لا تفترض', 'W13-D-05'),
);
foreach ($TH as $t) {
    $W("INSERT INTO repair01_w13_thresholds (threshold_key,value_num,unit_ar,title_ar,why,decision_ref,src_ref)
        VALUES ('" . $esc($t[0]) . "'," . (float) $t[1] . ",'" . $esc($t[2]) . "','" . $esc($t[3]) . "',
                '" . $esc($t[4]) . "','" . $esc($t[5]) . "','RPR-W13 §٥')");
}
printf("  عتباتٌ مسجَّلة %d\n\n", count($TH));

/* ═══════════════════════════════════════════════════════════════════════════
   ⑥ آلاتُ الحالة — لكلِّ كيانٍ ممنوعٌ صريحٌ بسبب
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑥ آلاتُ الحالة ───────────────────────────────────────────────\n";
$W("DELETE FROM repair01_w13_states");
$ST = array(
    /* ── دورةُ التحقّقِ والإغلاق ─────────────────────────────────────── */
    array('tkt_verification', 'resolved', 'verification', 1, 'مالك الحل في ادارته',
          'اجراء معالجة واحد على الاقل مسجل بمرجعه في شاشة ادارته ونافذة تحقق من السجل',
          'اعلان معالجة بنافذة تحققه', 'لا اعتماد - المعالجة واقعة تعلن',
          'تعاد بالفتح من واقعة اعادة فتح لا بقلب الحالة',
          'التصحيح باجراء معالجة جديد يحمل مرجع سابقه', ''),
    array('tkt_verification', 'verification', 'verified', 1, 'المبلغ او المختص',
          'المتحقق غير المعالج وصفة التحقق معلنة ونافذة الحرج لا تغلق اليا',
          'شهادة تحقق بصفة المتحقق', 'فصل الواجبات في الخدمة وقيد القاعدة',
          'تفتح باعتراض المبلغ خلال مهلة السجل',
          'التصحيح بتحقق جديد في دورة تالية لا بقلب المتحقق منه', ''),
    array('tkt_verification', 'verified', 'closed', 1, 'مالك دورة التذكرة',
          'تحقق مسجل بتاريخه وفاعله', 'محضر اغلاق البلاغ', 'قيد القاعدة chk_tkv_close',
          'تفتح بواقعة اعادة فتح مسجلة', 'التصحيح بدورة تالية لا بقلب الاغلاق', ''),
    array('tkt_verification', 'closed', 'reopened', 1, 'المبلغ او مالك دورة التذكرة',
          'سبب اعادة فتح من قيمتين وتفصيل مكتوب وادارة عودة ليست البلاغات',
          'واقعة اعادة فتح بسببها', 'لا اعتماد - الاعتراض حق للمبلغ',
          'الدورة الجديدة تبدا من المعالجة لا من التسجيل',
          'التصحيح بواقعة اعادة فتح ثانية تحمل مرجع الاولى', ''),
    array('tkt_verification', 'resolved', 'closed', 0, '', '', '', '', '', '',
          'اغلاق بلا تحقق يجعل المعالج شاهدا على عمله ويلغي حق المبلغ في الاعتراض'),
    array('tkt_verification', 'verification', 'closed', 0, '', '', '', '', '', '',
          'الاغلاق يقع بعد التحقق لا بدله - وقفز التحقق يمحو صفة المتحقق من السجل'),
    /* ── الإسناد ─────────────────────────────────────────────────────── */
    array('tkt_assignment', 'assigned', 'received', 1, 'المكلف نفسه',
          'الاستلام من المكلف نفسه لا من مسنده', 'وقت استلام مسجل',
          'لا اعتماد - الاستلام واقعة', 'يعاد بالاسناد الى مكلف اخر بسببه',
          'التصحيح باسناد جديد يحمل سببه لا بتعديل السابق', ''),
    array('tkt_assignment', 'received', 'superseded', 1, 'مالك دورة التذكرة',
          'سبب تغيير المكلف مكتوب والمكلف الجديد غير السابق',
          'واقعة اسناد جديدة بسببها', 'لا اعتماد - الاسناد قرار مركز',
          'يعاد بالاسناد للمكلف الاول بسببه', 'التصحيح بواقعة اسناد لا بمحو السابقة', ''),
    array('tkt_assignment', 'assigned', 'superseded', 0, '', '', '', '', '', '',
          'اسناد يستبدل قبل ان يستلم يترك البلاغ بلا وقت استلام ويخفي مهلة الاستجابة'),
    /* ── التوجيه ─────────────────────────────────────────────────────── */
    array('tkt_routing', 'auto', 'center_correction', 1, 'مالك دورة التذكرة',
          'سبب التصحيح مكتوب والوجهة ليست ادارة البلاغات',
          'واقعة توجيه بسببها', 'لا اعتماد - التوجيه قرار مركز',
          'يعاد بتوجيه لاحق يحمل سببه', 'التصحيح بواقعة توجيه جديدة لا بتعديل الالي', ''),
    array('tkt_routing', 'auto', 'auto', 0, '', '', '', '', '', '',
          'توجيه الي يعقب اليا بلا سبب يعني قاعدة توجيه معطوبة تخفى بتكرار التوجيه'),
    /* ── إعادةُ الفتح ───────────────────────────────────────────────── */
    array('tkt_reopen', 'raised', 'routed_back', 1, 'مالك دورة التذكرة',
          'ادارة العودة ليست البلاغات والدورة السابقة مغلقة',
          'اعادة توجيه لمسار المعالجة', 'لا اعتماد',
          'لا يفتح - العودة واقعة', 'التصحيح بواقعة اعادة فتح جديدة', ''),
    array('tkt_reopen', 'raised', 'closed', 0, '', '', '', '', '', '',
          'اعادة فتح تغلق بلا عودة الى مسار المعالجة تحول الاعتراض الى اجراء شكلي'),
    /* ── كتالوجُ محلِّ البلاغ ────────────────────────────────────────── */
    array('tkt_subject_type', 'active', 'inactive', 1, 'مالك دورة التذكرة',
          'لا بلاغ مفتوح يشير الى النوع', 'قرار تعطيل نوع', 'لا اعتماد',
          'يفتح بتفعيل النوع نفسه', 'التصحيح بتعطيل النوع واضافة خلفه لا بتعديل رمزه', ''),
    array('tkt_subject_type', 'active', 'deleted', 0, '', '', '', '', '', '',
          'حذف نوع محل بلاغ يترك بلاغات قديمة تشير الى نوع لا وجود له فتفقد محلها'),
    /* ── القضيّةُ التأديبيّة ────────────────────────────────────────── */
    array('hr_disciplinary_case', 'incident', 'investigation', 1, 'مدير الموارد البشرية',
          'محقق غير المبلغ وغير الموظف وادارة تحقيق من الثلاث المعلنة وتكليف موثق للمراجعة الداخلية',
          'قرار تكليف بالتحقيق', 'فصل الواجبات في الخدمة وقيد القاعدة',
          'تفتح باستئناف الموظف', 'التصحيح بتكليف محقق اخر بقرار لا بتعديل القائم', ''),
    array('hr_disciplinary_case', 'investigation', 'decided', 1, 'المخول بسقفه',
          'مصدر القرار غير المحقق وغير الموظف ومرجع قرار مكتوب ومراحل ثلاث مسجلة',
          'قرار تاديبي بمرجعه', 'فصل الواجبات في الخدمة وقيد القاعدة',
          'تفتح باستئناف الموظف خلال مهلته', 'التصحيح بقرار لاحق يحمل مرجع الاول', ''),
    array('hr_disciplinary_case', 'decided', 'closed', 1, 'مدير الموارد البشرية',
          'اثر القرار نفذ او الخصم رفع بمرجعه', 'محضر اغلاق قضية', 'لا اعتماد ثان',
          'تفتح بالاستئناف', 'التصحيح بقضية جديدة تحمل مرجع المغلقة', ''),
    array('hr_disciplinary_case', 'decided', 'appealed', 1, 'الموظف',
          'استئناف مكتوب خلال مهلته', 'طلب استئناف', 'لجنة الاستئناف',
          'الاستئناف يعيد القضية الى قرار جديد لا الى واقعتها',
          'التصحيح بقرار استئناف يحمل مرجع القرار الاول', ''),
    array('hr_disciplinary_case', 'incident', 'decided', 0, '', '', '', '', '', '',
          'قرار بلا تحقيق يجعل الواقعة حكما - وهو عين ما يمنعه الفصل بين المراحل الثلاث'),
    array('hr_disciplinary_case', 'investigation', 'closed', 0, '', '', '', '', '', '',
          'اغلاق تحقيق بلا قرار يترك الموظف بلا براءة ولا عقوبة والقضية بلا نتيجة'),
    /* ── الحركةُ الوظيفيّة ─────────────────────────────────────────── */
    array('hr_job_movement', 'submitted', 'approved', 1, 'المخول بسقفه',
          'من طلب الحركة لا يعتمدها ومنصب هدف موجب ومرجع قرار مكتوب',
          'قرار حركة وظيفية', 'فصل الواجبات في الخدمة وقيد القاعدة',
          'تفتح بطلب حركة جديد', 'التصحيح بحركة عكسية بقرارها لا بمحو المعتمدة', ''),
    array('hr_job_movement', 'approved', 'applied', 1, 'مدير الموارد البشرية',
          'تاريخ السريان حل والمنصب الهدف شاغر', 'اشعار تطبيق حركة', 'لا اعتماد ثان',
          'تفتح بحركة عودة', 'التصحيح بحركة عودة بقرارها', ''),
    array('hr_job_movement', 'submitted', 'rejected', 1, 'المخول بسقفه',
          'سبب رد مكتوب', 'قرار رد حركة', 'فصل الواجبات في الخدمة',
          'تفتح بطلب جديد يستوفي ما نقص', 'التصحيح باستيفاء الموجب ثم اعادة الطلب', ''),
    array('hr_job_movement', 'submitted', 'applied', 0, '', '', '', '', '', '',
          'تطبيق حركة بلا اعتماد يغير المنصب والاجر بقرار طالبها وحده'),
    /* ── بندُ التهيئة ──────────────────────────────────────────────── */
    array('hr_onboarding_item', 'pending', 'done', 1, 'مدير الموارد البشرية',
          'منجز البند مسجل', 'سند انجاز البند', 'لا اعتماد',
          'يفتح باعادة البند لحالته عند تغير العهدة', 'التصحيح ببند جديد لا بمحو المنجز', ''),
    array('hr_onboarding_item', 'pending', 'waived', 1, 'مدير الموارد البشرية',
          'مستند استثناء مكتوب', 'قرار استثناء بند تهيئة', 'قيد القاعدة chk_hron_waiver',
          'يفتح بالغاء الاستثناء بقرار', 'التصحيح بقرار الغاء استثناء لا بمحوه', ''),
    array('hr_onboarding_item', 'pending', 'waived_without_doc', 0, '', '', '', '', '', '',
          'استثناء بلا مستند يحول قائمة التهيئة الى شكل بلا اثر ويسقط شرط المباشرة'),
    /* ── مستندُ الموظّف ────────────────────────────────────────────── */
    array('hr_employee_document', 'valid', 'expiring', 1, 'مدير الموارد البشرية',
          'التاريخ دخل نافذة التنبيه من السجل', 'تنبيه اقتراب انتهاء', 'لا اعتماد',
          'يعود ساريا بمستند بديل', 'التصحيح بمستند بديل يحمل مرجع سابقه', ''),
    array('hr_employee_document', 'expiring', 'expired', 1, 'النظام',
          'تاريخ الانتهاء مضى', 'وسم انتهاء مستند', 'لا اعتماد',
          'يعود ساريا بمستند بديل', 'التصحيح بمستند بديل لا بتمديد تاريخ القائم', ''),
    array('hr_employee_document', 'expired', 'replaced', 1, 'مدير الموارد البشرية',
          'مستند بديل مسجل بمرجع الملف', 'مستند بديل', 'لا اعتماد',
          'لا يفتح - المستبدل تاريخ', 'التصحيح ببديل ثالث يحمل مرجع الثاني', ''),
    array('hr_employee_document', 'expired', 'valid', 0, '', '', '', '', '', '',
          'اعادة مستند منته الى ساري بلا بديل تمديد صامت لصلاحية لا يملكها النظام'),
    /* ── سجلُّ التدريب ─────────────────────────────────────────────── */
    array('hr_training_record', 'planned', 'in_progress', 1, 'مدير الموارد البشرية',
          'تاريخ بدء مسجل', 'سجل التحاق ببرنامج', 'لا اعتماد',
          'يفتح باعادة تخطيط البرنامج', 'التصحيح بسجل جديد لا بتعديل المنتهي', ''),
    array('hr_training_record', 'in_progress', 'completed', 1, 'مدير الموارد البشرية',
          'شهادة مرجعها مكتوب والالزامي بتاريخ انتهاء صلاحيته',
          'شهادة تدريب', 'قيد القاعدة chk_hrtr_cert', 'يفتح بانتهاء الصلاحية',
          'التصحيح باعادة التاهيل بسجل جديد', ''),
    array('hr_training_record', 'completed', 'expired', 1, 'النظام',
          'تاريخ صلاحية الشهادة مضى', 'وسم انتهاء صلاحية تدريب', 'لا اعتماد',
          'يعود مكتملا باعادة تاهيل', 'التصحيح باعادة تاهيل لا بتمديد الشهادة', ''),
    array('hr_training_record', 'in_progress', 'failed', 1, 'مدير الموارد البشرية',
          'نتيجة رسوب مسجلة', 'محضر نتيجة', 'لا اعتماد',
          'يفتح باعادة التخطيط', 'التصحيح بمحاولة جديدة بسجلها', ''),
    array('hr_training_record', 'planned', 'completed', 0, '', '', '', '', '', '',
          'اكتمال بلا التحاق يجعل الشهادة اعلانا لا اثرا لتدريب وقع'),
    /* ── تقييمُ الأداء ─────────────────────────────────────────────── */
    array('hr_performance_review', 'draft', 'submitted', 1, 'المقيم المباشر',
          'معايير مرجعها مكتوب والمقيم غير الموظف', 'نموذج تقييم مرفوع',
          'قيد القاعدة chk_hrpr_self', 'يفتح باعادته للمقيم',
          'التصحيح بنسخة جديدة تحمل مرجع سابقتها', ''),
    array('hr_performance_review', 'submitted', 'moderated', 1, 'مدير الموارد البشرية',
          'مراجع معلن غير المقيم', 'محضر مراجعة تقييم', 'لجنة المعايرة',
          'يفتح باعتراض الموظف', 'التصحيح بمراجعة ثانية بمرجعها', ''),
    array('hr_performance_review', 'moderated', 'finalized', 1, 'مدير الموارد البشرية',
          'درجة مسجلة وتاريخ اعتماد', 'تقييم نهائي معتمد', 'قيد القاعدة chk_hrpr_final',
          'يفتح باعتراض الموظف خلال مهلته', 'التصحيح بدورة تقييم تالية لا بقلب المعتمد', ''),
    array('hr_performance_review', 'finalized', 'disputed', 1, 'الموظف',
          'اعتراض مكتوب خلال مهلته', 'طلب اعتراض على تقييم', 'لجنة الاعتراضات',
          'الاعتراض ينتج تقييما معدلا بمرجعه', 'التصحيح بتقييم معدل يحمل مرجع الاول', ''),
    array('hr_performance_review', 'draft', 'finalized', 0, '', '', '', '', '', '',
          'اعتماد مسودة يتخطى الرفع والمعايرة فيصير التقييم راي فرد بلا مراجعة'),
    /* ── اشتراكُ المزايا ───────────────────────────────────────────── */
    array('hr_benefit_enrollment', 'active', 'suspended', 1, 'مدير الموارد البشرية',
          'سبب الايقاف مسجل ومكون المسير يتوقف معه', 'قرار ايقاف اشتراك',
          'لا اعتماد', 'يعود ساريا بقرار', 'التصحيح باشتراك جديد بتاريخ سريانه', ''),
    array('hr_benefit_enrollment', 'suspended', 'ended', 1, 'مدير الموارد البشرية',
          'تاريخ انتهاء مسجل لا يسبق تاريخ السريان', 'قرار انهاء اشتراك',
          'لا اعتماد', 'لا يفتح - المنتهي تاريخ', 'التصحيح باشتراك جديد لا باعادة المنتهي', ''),
    array('hr_benefit_enrollment', 'ended', 'active', 0, '', '', '', '', '', '',
          'اعادة اشتراك منته الى ساري تمحو تاريخ انتهائه فتختلط حصص فترتين في مسير واحد'),
);
$stN = 0; $stFail = 0;
foreach ($ST as $s) {
    if ($W("INSERT INTO repair01_w13_states
            (entity,from_state,to_state,allowed,owner_role,preconditions,output_doc,approval_gate,
             reopen_rule,correct_rule,forbid_why,src_ref)
            VALUES ('" . $esc($s[0]) . "','" . $esc($s[1]) . "','" . $esc($s[2]) . "'," . (int) $s[3] . ",
                    '" . $esc($s[4]) . "','" . $esc($s[5]) . "','" . $esc($s[6]) . "','" . $esc($s[7]) . "',
                    '" . $esc($s[8]) . "','" . $esc($s[9]) . "','" . $esc($s[10]) . "','RPR-W13 §٦')")) { $stN++; }
    else { $stFail++; }
}
printf("  انتقالاتٌ مسجَّلة %d · ممنوعٌ صراحةً %d · كياناتٌ %d · فشل %d\n\n",
    $stN, (int) $one("SELECT COUNT(*) FROM repair01_w13_states WHERE allowed = 0"),
    (int) $one("SELECT COUNT(DISTINCT entity) FROM repair01_w13_states"), $stFail);

/* ═══════════════════════════════════════════════════════════════════════════
   ⑦ فصلُ الواجبات — بستّةِ أدوارٍ وتركيبةٍ ممنوعةٍ ورمزِ ردٍّ يُنفِّذها
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑦ فصلُ الواجبات ─────────────────────────────────────────────\n";
$W("DELETE FROM repair01_w13_sod");
$SODCODES = repair01_w13_sod_codes();
$SOD = array(
    array('hr.movement.approve', 'اعتماد حركة وظيفية', 'مدير الادارة الطالبة', 'اخصائي الموارد',
          'المخول بسقفه', 'اخصائي الموارد', 'مدير الموارد البشرية',
          'من طلب الحركة لا يعتمدها', 'AAM-HR-01',
          'نائب المخول بسقفه', 'الموظف ومنصبه معا', 'بتفويض مكتوب ومؤقت'),
    array('hr.discipline.investigate', 'التكليف بالتحقيق التاديبي', 'المبلغ عن الواقعة', 'مدير الموارد البشرية',
          'مدير الموارد البشرية', 'المحقق المكلف', 'مدير الموارد البشرية',
          'من بلغ لا يحقق ولا يحقق الموظف في واقعته', 'AAM-HR-02',
          'نائب مدير الموارد البشرية', 'القضية وحدها', 'لا تفويض للمبلغ'),
    array('hr.discipline.decide', 'اصدار القرار التاديبي', 'المحقق المكلف', 'القانوني',
          'المخول بسقفه', 'اخصائي الموارد', 'مدير الموارد البشرية',
          'من حقق لا يقرر ولا يقرر الموظف في قضيته', 'AAM-HR-03',
          'نائب المخول بسقفه', 'القضية وقرارها معا', 'لا تفويض في العقوبات الجسيمة'),
    array('hr.deduction.raise', 'رفع خصم تاديبي على المسير', 'اخصائي الموارد', 'مدير الموارد البشرية',
          'المخول بسقفه', 'محاسب المسير', 'مدير الموارد البشرية',
          'خصم بلا قرار قضية محسوم يسنده', 'AAM-HR-04',
          'نائب مدير الموارد البشرية', 'الموظف والقرار معا', 'بتفويض مكتوب ومؤقت'),
    array('hr.review.final', 'اعتماد التقييم الوظيفي', 'المقيم المباشر', 'لجنة المعايرة',
          'مدير الموارد البشرية', 'اخصائي الموارد', 'مدير الموارد البشرية',
          'لا يقيم احد نفسه', 'AAM-HR-05',
          'نائب مدير الموارد البشرية', 'الموظف ودورته معا', 'بتفويض مكتوب ومؤقت'),
    array('hr.settlement.approve', 'اعتماد التصفية النهائية', 'اخصائي الموارد', 'محاسب المسير',
          'المخول بسقفه', 'أمين الخزينة', 'مدير الموارد البشرية',
          'من اعد التصفية لا يعتمدها ولا صرف قبل ابراء العهد', 'AAM-HR-06',
          'نائب المخول بسقفه', 'الموظف وعهدته معا', 'لا تفويض فوق السقف'),
    array('tkt.route.correct', 'تصحيح توجيه بلاغ', 'مسجل البلاغ', 'مالك دورة التذكرة',
          'مالك دورة التذكرة', 'مالك دورة التذكرة', 'مالك دورة التذكرة',
          'تصحيح توجيه بلا سبب مكتوب او توجيه لادارة البلاغات كمالك حل', 'AAM-TKT-01',
          'نائب مالك دورة التذكرة', 'البلاغ ووجهته معا', 'بتفويض مكتوب ومؤقت'),
    array('tkt.assign', 'اسناد بلاغ لمكلف', 'مالك دورة التذكرة', 'مدير الادارة المعالجة',
          'مالك دورة التذكرة', 'مالك الحل', 'مالك دورة التذكرة',
          'اسناد معالجة الى ادارة البلاغات', 'AAM-TKT-02',
          'نائب مالك دورة التذكرة', 'البلاغ ومكلفه معا', 'بتفويض مكتوب ومؤقت'),
    array('tkt.resolve', 'تنفيذ معالجة البلاغ', 'مالك الحل', 'مدير الادارة المعالجة',
          'مدير الادارة المعالجة', 'مالك الحل', 'مالك دورة التذكرة',
          'تنفيذ معالجة من ادارة البلاغات', 'AAM-TKT-03',
          'نائب مالك الحل في ادارته', 'البلاغ واجراؤه معا', 'لا تفويض خارج الادارة المعالجة'),
    array('tkt.verify', 'التحقق من معالجة البلاغ', 'مالك الحل', 'المبلغ او المختص',
          'مالك دورة التذكرة', 'المبلغ او المختص', 'مالك دورة التذكرة',
          'من عالج لا يتحقق من عمله', 'AAM-TKT-04',
          'مختص بديل من غير ادارة المعالجة', 'البلاغ ودورته معا', 'لا تفويض للمعالج'),
    array('tkt.close', 'اغلاق البلاغ', 'مالك الحل', 'المبلغ او المختص',
          'مالك دورة التذكرة', 'مالك دورة التذكرة', 'مالك دورة التذكرة',
          'اغلاق بلا تحقق مسجل', 'AAM-TKT-05',
          'نائب مالك دورة التذكرة', 'البلاغ ودورته معا', 'بتفويض مكتوب ومؤقت'),
    array('tkt.reopen', 'اعادة فتح البلاغ', 'المبلغ', 'مالك دورة التذكرة',
          'مالك دورة التذكرة', 'مالك الحل', 'مالك دورة التذكرة',
          'اعادة فتح بلا سبب مكتوب او عودة الى مركز البلاغات بدل ادارة المعالجة', 'AAM-TKT-06',
          'نائب مالك دورة التذكرة', 'البلاغ ودورته السابقة معا', 'لا تفويض عن المبلغ'),
);
$sodN = 0; $sodNoCode = array();
foreach ($SOD as $s) {
    $key = $s[0];
    if (!isset($SODCODES[$key])) { $sodNoCode[] = $key; continue; }
    if ($W("INSERT INTO repair01_w13_sod
        (process_key,process_name,initiator_role,reviewer_role,approver_role,executor_role,closer_role,
         forbidden_combo,enforced_by,authority_rule_id,deputy_role,scope_rule,delegation,effective_date,src_ref)
        VALUES ('" . $esc($s[0]) . "','" . $esc($s[1]) . "','" . $esc($s[2]) . "','" . $esc($s[3]) . "',
                '" . $esc($s[4]) . "','" . $esc($s[5]) . "','" . $esc($s[6]) . "','" . $esc($s[7]) . "',
                '" . $esc($SODCODES[$key]) . "','" . $esc($s[8]) . "','" . $esc($s[9]) . "',
                '" . $esc($s[10]) . "','" . $esc($s[11]) . "','2026-08-26','RPR-W13 §٧')")) { $sodN++; }
}
printf("  عملياتٌ حرِجة %d · بلا رمزِ ردٍّ مُعلَنٍ %d%s\n\n", $sodN, count($sodNoCode),
    $sodNoCode ? ' ⇐ ' . implode('، ', $sodNoCode) : '');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑧ عقودُ الأثر — لكلِّ حدثٍ مستهلكٌ **بالاسمِ** لا «كلُّ المستهلكين»
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑧ عقودُ الأثر ────────────────────────────────────────────────\n";
$W("DELETE FROM repair01_events WHERE wave = 'W13'");
$EV = array(
    array('tkt.reported', 'تسجيل بلاغ باطرافه', 'tkt_party', 'Tickets/tkt_parties.php',
          'تسجيل مبلغ ومحل بلاغ متمايزين بمفتاحيهما',
          'ticket_id · reporter_actor_id · subject_type_code',
          'PeopleCycleService::route',
          'التوجيه يقرا ادارة محل البلاغ من الكتالوج فلا يوجه بلاغ بلا محل معلن',
          'المبلغ ومحل البلاغ صفان بدورين مختلفين ونوع المحل من الكتالوج',
          'إعادة بلا أثر', 'w13:tkt.reported', 'يرفع ولا يبتلع',
          'التصحيح بتسجيل طرف بديل بعد ابطال السابق لا بتعديل مفتاحه'),
    array('tkt.routed', 'توجيه بلاغ لادارة مختصة', 'tkt_routing_history', 'Tickets/tkt_routing.php',
          'توجيه البلاغ الى ادارة غير ادارة البلاغات',
          'ticket_id · to_dept · seq_no',
          'PeopleCycleService::assign',
          'الاسناد لا يقع الا في ادارة وجهت اليها فعلا فلا يكلف احد خارج مسار التوجيه',
          'الوجهة ليست ادارة البلاغات والالي بقاعدته والتصحيح بسببه',
          'إعادة بلا أثر', 'w13:tkt.routed', 'يرفع ولا يبتلع',
          'التصحيح بواقعة توجيه تالية بسببها لا بتعديل السابقة'),
    array('tkt.assigned', 'اسناد بلاغ لمكلف', 'tkt_assignment_history', 'Tickets/tkt_assignment.php',
          'اسناد المعالجة لمكلف في ادارة مختصة',
          'ticket_id · to_person_id · to_dept',
          'PeopleCycleService::acknowledge',
          'الاسناد الاول يثبت مالك الحل طرفا رابعا فيصير للبلاغ اربعة اطراف متمايزة',
          'المكلف غير سابقه والسبب مكتوب والادارة ليست البلاغات',
          'إعادة بلا أثر', 'w13:tkt.assigned', 'يرفع ولا يبتلع',
          'التصحيح باسناد جديد بسببه لا بمحو السابق'),
    array('tkt.action.recorded', 'تسجيل اجراء معالجة', 'tkt_resolution_action', 'Tickets/tkt_resolution_actions.php',
          'تنفيذ اجراء معالجة في ادارة مختصة بمرجعه',
          'ticket_id · executor_dept · dept_screen_ref',
          'PeopleCycleService::resolve',
          'المعالجة لا تعلن الا وللبلاغ اجراء واحد على الاقل فلا يقفل بلاغ بلا عمل وقع',
          'المنفذ ليس ادارة البلاغات ولكل اجراء مرجع في شاشة ادارته',
          'إعادة بلا أثر', 'w13:tkt.action.recorded', 'يرفع ولا يبتلع',
          'التصحيح باجراء عكسي بمرجعه لا بمحو السطر'),
    array('tkt.escalated', 'تصعيد بلاغ تجاوز مهلته', 'ticket_escalations', 'Tickets/tkt_escalation.php',
          'تجاوز مهلة الاستجابة او المعالجة',
          'ws_id · level',
          'Tickets/tkt_escalation.php',
          'مستوى التصعيد يقرا في سطحه فيرى مدير الادارة المعالجة بلاغه المتاخر',
          'المستوى ضمن سقف السلم المسجل',
          'إعادة بلا أثر', 'w13:tkt.escalated', 'يرفع ولا يبتلع',
          'الخفض بواقعة معالجة او تعليق مبرر لا بمحو التصعيد'),
    array('tkt.resolved', 'اعلان معالجة البلاغ', 'tkt_verification', 'Tickets/tkt_verification.php',
          'اعلان المعالجة من ادارة مختصة بعد اجرائها',
          'ticket_id · cycle_no · window_hours',
          'PeopleCycleService::verify',
          'نافذة التحقق تفتح بمدتها من السجل فيقرا المبلغ حقه في الاعتراض بزمن معلن',
          'اجراء معالجة واحد على الاقل وادارة المعالجة ليست البلاغات',
          'إعادة بلا أثر', 'w13:tkt.resolved', 'يرفع ولا يبتلع',
          'التصحيح باجراء معالجة جديد ثم اعلان معالجة في دورة تالية'),
    array('tkt.verified', 'التحقق من معالجة البلاغ', 'tkt_verification', 'Tickets/tkt_verification.php',
          'تحقق المبلغ او المختص او انتهاء نافذة غير الحرج',
          'verification_id · verify_kind',
          'PeopleCycleService::close',
          'الاغلاق يصير ممكنا - وقبل التحقق يرد في الخدمة وفي قيد القاعدة معا',
          'المتحقق غير المعالج والاغلاق الالي ممتنع على البلاغ الحرج',
          'إعادة بلا أثر', 'w13:tkt.verified', 'يرفع ولا يبتلع',
          'التصحيح بدورة تحقق تالية لا بقلب المتحقق منه'),
    array('tkt.closed', 'اغلاق البلاغ', 'tkt_verification', 'Tickets/tkt_verification.php',
          'اغلاق دورة تحقق مكتملة',
          'verification_id',
          'PeopleCycleService::reopen',
          'اعادة الفتح تصير ممكنة على دورة مغلقة وحدها فلا يعاد فتح ما لم يقفل',
          'تحقق مسجل بتاريخه',
          'إعادة بلا أثر', 'w13:tkt.closed', 'يرفع ولا يبتلع',
          'التصحيح بواقعة اعادة فتح بسببها'),
    array('tkt.reopened', 'اعادة فتح البلاغ', 'tkt_reopen', 'Tickets/tkt_reopen.php',
          'اعتراض المبلغ او تكرار المشكلة',
          'ticket_id · reopen_reason · back_to_dept',
          'PeopleCycleService::resolve',
          'دورة تحقق جديدة تفتح برقمها فتقرا الدورتان منفصلتين لا دورة تدهس سابقتها',
          'الدورة السابقة مغلقة وادارة العودة ليست البلاغات',
          'إعادة بلا أثر', 'w13:tkt.reopened', 'يرفع ولا يبتلع',
          'التصحيح بواقعة اعادة فتح ثانية تحمل مرجع الاولى'),
    array('hr.employee.onboarded', 'اكتمال تهيئة موظف', 'hr_onboarding_item', 'Employees/hr_onboarding.php',
          'اغلاق كل بنود التهيئة الالزامية انجازا او استثناء موثقا',
          'employee_id',
          'Workforce/payroll_runs.php',
          'الموظف يصير مؤهلا للادراج في المسير فلا يدرج من لم تكتمل مباشرته',
          'صفر بند الزامي معلق',
          'إعادة بلا أثر', 'w13:hr.employee.onboarded', 'يرفع ولا يبتلع',
          'التصحيح بفتح بند تهيئة جديد لا بالغاء المباشرة'),
    array('hr.movement.approved', 'اعتماد حركة وظيفية', 'hr_job_movement', 'Employees/hr_job_movements.php',
          'اعتماد نقل او ترقية او انتداب بموجبه',
          'movement_id · employee_id',
          'Employees/hr_job_movements.php',
          'المنصب الجديد يقرا في سجل الحركات فيتغير موضع الموظف بقرار لا بتعديل صامت',
          'من طلب الحركة لا يعتمدها ومرجع القرار مكتوب',
          'إعادة بلا أثر', 'w13:hr.movement.approved', 'يرفع ولا يبتلع',
          'التصحيح بحركة عودة بقرارها لا بمحو المعتمدة'),
    array('hr.discipline.decided', 'اصدار قرار تاديبي', 'hr_disciplinary_case', 'Employees/hr_disciplinary.php',
          'حسم قضية تاديبية بقرار بعد تحقيقها',
          'case_id · decision_kind · decision_ref',
          'PeopleCycleService::raiseDeduction',
          'الخصم يصير ممكنا بمرجع القرار وحده فلا يرفع خصم تاديبي بلا قضية محسومة',
          'من حقق لا يقرر ومرجع القرار مكتوب والمراحل الثلاث مسجلة',
          'إعادة بلا أثر', 'w13:hr.discipline.decided', 'يرفع ولا يبتلع',
          'التصحيح بقرار لاحق يحمل مرجع الاول لا بتعديل الصادر'),
    array('hr.deduction.raised', 'رفع خصم تاديبي', 'payroll_deductions', 'Workforce/deductions.php',
          'رفع خصم مسنود بقرار قضية محسوم',
          'deduction_id · case_id · amount',
          'Workforce/payroll_lines.php',
          'سطر الخصم يظهر في اسطر المسير بمرجع قراره فيقرا الموظف سبب خصمه',
          'قرار قضية بحالة محسوم او مغلق ونوعه خصم',
          'إعادة بلا أثر', 'w13:hr.deduction.raised', 'يرفع ولا يبتلع',
          'العكس بسطر مقابل بمرجعه لا بمحو الخصم'),
    array('hr.payroll.approved', 'اعتماد مسير الرواتب', 'payroll_runs', 'Workforce/payroll_runs.php',
          'اعتماد مسير شهر بعد اعداده',
          'run_id · lines_count',
          'Workforce/payroll_lines.php',
          'اسطر المسير تصير مقروءة باعتمادها فيصير الصرف على مسير معتمد لا على مسودة',
          'من اعد المسير لا يعتمده',
          'إعادة بلا أثر', 'w13:hr.payroll.approved', 'يرفع ولا يبتلع',
          'التصحيح بمسير تسوية يحمل مرجع الاول'),
    array('hr.settlement.approved', 'اعتماد التصفية النهائية', 'employee_final_settlements',
          'Workforce/final_settlement.php',
          'اعتماد تصفية موظف بعد ابراء عهدته وتسوية سلفه',
          'settlement_id · employee_id',
          'Workforce/final_settlement.php',
          'التصفية تصير قابلة للصرف عند الخزينة فلا يصرف مستحق قبل ابراء العهد',
          'من اعد التصفية لا يعتمدها ومستند ابراء عهدة قائم وصفر سلفة قائمة',
          'إعادة بلا أثر', 'w13:hr.settlement.approved', 'يرفع ولا يبتلع',
          'التسوية اللاحقة بمستندها لا بقلب التصفية'),
);
$evN = 0; $evUndeclared = array();
$declaredEv = repair01_w13_stage_events();
foreach ($EV as $e) {
    if (!in_array($e[0], $declaredEv, true)) { $evUndeclared[] = $e[0]; continue; }
    if ($W("INSERT INTO repair01_events
        (event_code,name,wave,source_unit,source_screen,idempotency_key,consumers,effect_type,
         retry_policy,src_ref,trigger_rule,min_payload,consumer_list,consumer_effect,
         preconditions,failure_policy,compensation,contract_status,contract_rule,contract_stage)
        VALUES ('" . $esc($e[0]) . "','" . $esc($e[1]) . "','W13',
                '" . $esc(strpos($e[0], 'hr.') === 0 ? '07 إدارة الموارد البشرية' : '10 إدارة البلاغات') . "',
                '" . $esc($e[3]) . "','" . $esc($e[10]) . "','" . $esc($e[6]) . "',
                '" . $esc($e[7]) . "','" . $esc($e[9]) . "','RPR-W13 §٨',
                '" . $esc($e[4]) . "','" . $esc($e[5]) . "','" . $esc($e[6]) . "','" . $esc($e[7]) . "',
                '" . $esc($e[8]) . "','" . $esc($e[11]) . "','" . $esc($e[12]) . "',
                'RECORDED','W13_EVENT_CONTRACT','W13')")) { $evN++; }
}
printf("  عقودُ أثرٍ مكتوبة %d · حدثٌ غيرُ مُعلَنٍ في السجلّ %d%s\n\n",
    $evN, count($evUndeclared), $evUndeclared ? ' ⇐ ' . implode('، ', $evUndeclared) : '');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑨ دفترُ الأطرافِ الأربعة — **مقامٌ ثابتٌ لا يخلو**
   ═══════════════════════════════════════════════════════════════════════════
   ◆ **درسُ `W12-27`**: حاجبٌ مقامُه القيمُ الحيّةُ وحدَها يخرج أخضرَ على العدم.
     فالأطرافُ تُعلَن هنا بقيدِها في القاعدةِ **وتُقاس على الحيِّ معًا** — فيقظُ
     الحاجبِ من أوّلِ يومٍ لا من أوّلِ صفّ.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑨ دفترُ الأطرافِ الأربعة ────────────────────────────────────\n";
$W("DELETE FROM repair01_w13_parties");
$partyN = 0; $partyNoChk = array();
foreach (repair01_w13_party_roles() as $p) {
    /* **والقيدُ يُثبَت من المخطَّطِ لا يُدَّعى** */
    $chk = $p[7];
    $live = repair01_w13_check_exists($conn, 'tkt_party', $chk)
         || (int) $one("SELECT COUNT(*) FROM information_schema.STATISTICS
                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tkt_party'
                           AND INDEX_NAME = '" . $esc($chk) . "'") > 0;
    if (!$live) { $partyNoChk[] = $p[0] . ' ⇐ ' . $chk; }
    if ($W("INSERT INTO repair01_w13_parties
            (party_role,name_ar,owns,never_owns,key_column,legacy_column,merge_rule,db_constraint,why,src_ref)
            VALUES ('" . $esc($p[0]) . "','" . $esc($p[1]) . "','" . $esc($p[2]) . "','" . $esc($p[3]) . "',
                    '" . $esc($p[4]) . "','" . $esc($p[5]) . "','" . $esc($p[6]) . "','" . $esc($p[7]) . "',
                    '" . $esc($p[8]) . "','RPR-W13 §٩')")) { $partyN++; }
}
printf("  أطرافٌ مُعلَنة %d · قيدُ قاعدةٍ غيرُ مُثبَتٍ %d%s\n\n", $partyN, count($partyNoChk),
    $partyNoChk ? ' ⇐ ' . implode('، ', $partyNoChk) : '');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑩ قراراتُ المرحلة — وجوابا المالكِ يُغلقان في السجلِّ الحاكمِ لا هنا وحدَه
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑩ قراراتُ المرحلة ────────────────────────────────────────────\n";
$W("DELETE FROM repair01_w13_decisions");
$DEC = array(
    array('W13-D-01', 'أين تُكتب الأطرافُ الأربعةُ: أعمدةٌ في رأسِ البلاغِ أم صفوفٌ في سجلّ؟',
          'صفوفٌ في سجلٍّ واحدٍ بمفتاحَين فريدَين لا أعمدةٌ في الرأس',
          'اربعة اعمدة في راس البلاغ لا تمنع ان يحمل عمودان القيمة نفسها - والمنع يحتاج مفتاحا لا نية. '
          . 'وسجل tkt_party بمفتاحين: (بلاغ، دور) يمنع دورين لفاعل واحد، و(بلاغ، صنف، مفتاح) يمنع '
          . 'فاعلا واحدا في دورين. فالفصل الثلاثي في المادة 28 صار قيدا في القاعدة لا نصا في وثيقة.', 4),
    array('W13-D-02', 'من يملك تنفيذَ الحلِّ ومن يملك دورةَ التذكرة؟',
          'البلاغات تملك الدورة وحدها والادارة المختصة تملك التنفيذ وحدها',
          'ثلاثة قيود ترد DEP-10 عن التنفيذ: مالك الحل ومنفذ الاجراء وادارة المعالجة. وقيد رابع '
          . 'يوجب DEP-10 على مالك التذكرة. فالملكيتان مفصولتان في الاتجاهين لا في اتجاه واحد - '
          . 'ومنع المركز من التنفيذ وحده يترك ادارة اخرى تملك الدورة.', 4),
    array('W13-D-03', 'ما مدّةُ نافذةِ التحقّقِ ومتى يُغلَق البلاغُ آليًّا؟',
          'محرك قابل الضبط بقيمتين من السجل ولا اغلاق الي للحرج - والقرار يبقى لمالكه',
          'ما تطلبه المرحلة من DEC-OPEN-05 محرك قابل الضبط لا قلب حالة قرار. فالقيمتان '
          . 'المرشحتان في الدراسة مسجلتان في repair01_w13_thresholds بمرجع قرارهما: 72 ساعة '
          . 'للعادي و48 للحرج، تقران ولا تكتبان في الشيفرة، وتغييرهما قرار ادارة لا تعديل '
          . 'برنامج. وقيد chk_tkv_auto_not_critical يرد الاغلاق الالي على الحرج في القاعدة. '
          . 'والقرار يبقى THRESHOLD و CONFIG_PENDING لمالكه: تصنيفه صفة قرار لا حالته، و G0-04 '
          . 'في بوابة الاساس يعد الاثنتي عشرة ثابتة - فقلبه من داخل مرحلة تستفيد منه انتحال '
          . 'لقرار لم يصدر ويسقط بوابة الاساس معا.', 3),
    array('W13-D-04', 'كيف يُتابَع المستندُ الإلزاميُّ والتدريبُ الإلزاميّ؟',
          'بانتهاء الصلاحية لا باكتمال الادخال',
          'مستند الزامي بلا تاريخ انتهاء يمر في السجل ولا يمكن تنبيهه - فقيد chk_hred_mand يوجب '
          . 'التاريخ على الالزامي وحده، ومثله chk_hrtr_mand في التدريب. ونوافذ التنبيه من السجل.', 3),
    array('W13-D-05', 'أين يُكتب الخصمُ التأديبيُّ ومن يقرّره؟',
          'القضية تنتج قرارا والخصم يتفرع بمرجعه ولا يكتب في شاشة القضية',
          'المتطلب HR-17 والمتطلب HR-18 سطحان لا سطح: القضية عملية بمراحلها الثلاث والخصم سطر في '
          . 'المسير يحمل مرجع قرارها. وثلاث ايد في القضية: من بلغ لا يحقق ومن حقق لا يقرر. '
          . 'وخصم بلا قرار محسوم يرد برمز DEDUCTION_WITHOUT_DECIDED_CASE.', 2),
    array('W13-D-06', 'ماذا يُفعَل بأربعةِ أعمدةٍ حيّةٍ تقبل صفًّا بلا كيانٍ قانونيّ؟',
          'يعلن عددها ولا تشدد على بيانات قائمة ولا على جدول مركزي',
          'ticket_escalations و ticket_participants و ticket_responses فيها 48 صفا بلا كيان سابقة '
          . 'لهذه المرحلة، والتشديد عليها يمنع الهجرة او يدهس صفوفا حية. و employees عمودها نظيف '
          . 'اليوم (صفر مخالف) لكنها سجل الانسان الام تكتب من مسارات في موجات اخرى - وتشديدها من '
          . 'داخل هذه المرحلة تغيير مخطط عابر لنطاقها. فالاربعة معلنة بعددها وتقاس في البوابة، '
          . 'وارتفاع العدد يسقطها.', 48),
    array('W13-D-11', 'خمسةُ أسطحٍ مالكُها في السجلِّ الحيِّ القوى التشغيليةُ ومالكُها في الحزمةِ الموارد',
          'يعلن الانحراف بعدده ويقاس ولا يقلب من داخل مرحلة تستفيد منه',
          'خطة القوى والتوظيف والاجازات والسلف والمسير خمسة اسطح في مجلد القوى، ومالكها المقيس '
          . 'DEP-13 من nav_canonical ومالك متطلبها في الحزمة DEP-07. والمحور مشترك فعلا: نص '
          . 'HR-14 يقول الرصيد النظامي عند الموارد والاجازة التبادلية الميدانية تجدول عند القوى. '
          . 'وقلب ملكية سطح يغير من يراه في سايدبار ادارتين احداهما خارج نطاق هذه المرحلة - وهو '
          . 'قرار بنية قائمة لادارة اخرى (سابقة W12 في U9). فالخمسة معلنة بعددها، والبوابة تسقط '
          . 'ان زاد العدد او ان لمس الانحراف سطحا غير الخمسة.', 5),
    array('W13-D-07', 'اثنا عشرَ سجلًّا بُنيت ولم تُمارَس بعد — أتمرُّ البوّابةُ عليها؟',
          'تمر باعلان الخلاء مرة واحدة والرحلة تمارسها فعلا',
          'بوابة تقارن صفرا بصفر تمر على تطابق لا شيء (درس W01 و W07 و W09). فالخلاء يعلن هنا '
          . 'مرة واحدة، ورحلة الاثبات تمارس كل جدول ثم تكنس اثرها - فالبناء مثبت وظيفيا لا مدعى. '
          . 'والقبول النهائي في W15 برحلة موظف حقيقي.', 12),
    array('W13-D-08', 'تسعُ فجواتٍ لـIAF مختومةٌ بـW13 ولا متطلَّبَ في المرحلةِ يغطّيها',
          'تؤجل معلنة بعددها ولا تبنى خارج قائمة المتطلبات',
          'خريطة الموجات ختمت تسع فجوات للمراجعة الداخلية بـW13، ونطاق المرحلة المقيس 35 متطلبا '
          . 'في وحدتي 07 و10 وحدهما. وبناء سطح خارج القائمة يخالف نص المرحلة صراحة، وتغيير ختم '
          . 'الفجوات تعديل خريطة موجات من داخل مرحلة تستفيد منه. فتؤجل معلنة، ومالك ملفها '
          . 'المراجعة الداخلية في W14 مع الحوكمة.', 9),
    array('W13-D-09', 'أينَ تُرسى مِرساةُ متطلَّبِ التحقّقِ والإغلاق؟',
          'سطح دورة التحقق الجديد لا شاشة الاغلاق القائمة',
          'شاشة ticket_close.php زر يقلب حالة الراس، ودورة التحقق كيان بدورتها وعدد اشواطها '
          . 'وصفة متحققها. وحقنها في شاشة الاغلاق يجعل الدورة حقلا في زر - فبني لها سطحها.', 1),
    array('W13-D-10', 'ثلاثةَ عشرَ اسمَ فجوةٍ لهذه الوحدتَين وأسطحُها قائمةٌ بأسماءٍ أخرى',
          'تقيد موفاة في built_counterpart ولا تبنى تواما',
          'سوابق W9-D-08 و W11-D-11 و W12-D-04: الفجوة اسم مستهدف لا اسم ملف ملزم. وبناء '
          . 'advances.php بجانب employee_advances.php يصنع توأما لا قدرة. ولا يمس صف الشبح '
          . 'ولا on_disk ولا سجل مرحلة مغلقة - فمقاما الاشباح والاساس لا يتحركان.', 13),
);
foreach ($DEC as $d) {
    $W("INSERT INTO repair01_w13_decisions (decision_id,question,answer,rationale,scope_rows,decided_at,src_ref)
        VALUES ('" . $esc($d[0]) . "','" . $esc($d[1]) . "','" . $esc($d[2]) . "','" . $esc($d[3]) . "',
                " . (int) $d[4] . ",'2026-08-26','RPR-W13 §١٠')");
}
printf("  قراراتُ مرحلةٍ مسجَّلة %d\n", count($DEC));

/* ── قرارُ المالكِ البنيويُّ في السجلِّ الحاكم ────────────────────────────
   ◆ **والبنيويُّ وحدَه يُغلَق هنا**: `DEC-OPEN-16` حاجبٌ `STRUCTURAL` يمنع فتحَ
     البوّابةِ ونطاقُه هذه المرحلة، وجوابُه **يُحفَظ حرفًا** في `owner_decision`
     والحالةُ تتبعه.
   ⛔ **و`DEC-OPEN-05` عتبةٌ لا تُغلَق من داخلِ المرحلة**: صنفُه `THRESHOLD`
     و`blocking_level = CONFIG_PENDING` **صفةُ قرارٍ لا حالتُه**، و`G0-04` في
     بوّابةِ الأساسِ يعدُّ الاثنتَي عشرةَ ثابتةً. وما تطلبه §٤-٤ **محرّكٌ قابلُ
     الضبطِ يقرأ من السجلّ** لا قلبُ حالةِ القرار — فالقيمتان مسجَّلتانِ
     بمرجعِ قرارِهما، والقيدُ حيٌّ في القاعدة، والقرارُ يبقى مفتوحًا لمالكِه.
     وقلبُه هنا **انتحالٌ لقرارِ مالكٍ لم يصدر** ويُسقط `G0-04` معًا. */
$ans16 = 'التحقيق اختصاص اصيل لمالك موضوعه: التاديبي للموارد البشرية DEP-07 والحوكمي للحوكمة '
       . 'والالتزام DEP-08. والمراجعة الداخلية IAF لا اختصاص اصيل لها في التحقيق - تدخل بتكليف '
       . 'موثق حالة بحالة، ولكل تكليف مستنده ومداه وتاريخه. وعمود investigation_owner_dept يقبل '
       . 'ثلاث قيم لا غير، وقيد chk_hrdc_iaf يرد تكليف المراجعة الداخلية بلا مستند تكليف. '
       . 'واستقلال المراجعة الداخلية محفوظ: من يراجع النظام لا يصير طرفا دائما في تحقيقاته.';
$ownerN = 0;
$cur16 = (string) $one("SELECT status FROM repair01_decisions WHERE decision_id = 'DEC-OPEN-16'");
if ($cur16 === '') { echo "  ⚠ DEC-OPEN-16 غير موجود في السجلِّ الحاكم\n"; }
elseif ($W("UPDATE repair01_decisions
               SET owner_decision = '" . $esc($ans16) . "', status = 'APPROVED',
                   blocking_level = 'NONE', approved_at = '2026-08-26'
             WHERE decision_id = 'DEC-OPEN-16'")) { $ownerN++; }
/* **و`DEC-OPEN-05` يُرَدُّ إلى تصنيفِه إن كان قد قُلب** — فالإرجاعُ جزءٌ من
   القياسِ لا استثناءٌ منه. */
$W("UPDATE repair01_decisions
       SET status = 'NEEDS_OWNER_DECISION', blocking_level = 'CONFIG_PENDING'
     WHERE decision_id = 'DEC-OPEN-05' AND blocker_type = 'THRESHOLD'");
$stillOpen = (int) $one("SELECT COUNT(*) FROM repair01_decisions
                          WHERE blocker_type = 'STRUCTURAL' AND status = 'NEEDS_OWNER_DECISION'");
$cfgPending = (int) $one("SELECT COUNT(*) FROM repair01_decisions WHERE blocking_level = 'CONFIG_PENDING'");
printf("  قرارٌ بنيويٌّ أُغلق %d · حاجبٌ بنيويٌّ ما زال مفتوحًا %d · تصنيفُ العتباتِ %d\n\n",
    $ownerN, $stillOpen, $cfgPending);

/* ═══════════════════════════════════════════════════════════════════════════
   ⑪ الإصلاحاتُ — كلٌّ بمتطلَّبِه الكاشف
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑪ الإصلاحات ─────────────────────────────────────────────────\n";
$W("DELETE FROM repair01_w13_fixes");
$mergedNow = repair01_w13_party_merged($conn);
$crpNow    = repair01_w13_resolution_owned_by_crp($conn);
$FIX = array(
    array('W13-F-01', 'المبلغ كان اسما نصيا في راس البلاغ فلا يقاس تمايزه عن غيره',
          'TKT-04', 'reporting_person نص في 66 بلاغا و reporter_user_id فارغ في 2',
          'tkt_party.actor_id مفتاح انسان بقيد chk_tkp_actor',
          'اسم بلا مفتاح لا يربط بشخص ولا يقاس تمايزه عن طرف اخر - وهو عين درس persons في W03'),
    array('W13-F-02', 'محل البلاغ لم يكن طرفا معلنا بل خلط بشاشة المصدر',
          'TKT-03', 'source_entity_type نص حر بلا كتالوج',
          'tkt_subject_type كتالوج بسجل مرجعي مثبت من المخطط',
          'نوع بلا سجل مرجعي كتالوج يعد ولا يفي - فالسجل يثبت من information_schema لا يدعى'),
    array('W13-F-03', 'مالك التذكرة كان دورا ومالك الحل مكلفا فاندمج الطرفان',
          'TKT-04', 'owner_role_id دور و assigned_user_id شخص في الجدول نفسه',
          'صفان بدورين في tkt_party بمفتاح فريد يمنع اجتماعهما في فاعل واحد',
          'دور لا يقاس تمايزه عن شخص - والدمج يظهر حين يعالج المركز ويغلق ويتحقق من عمله'),
    array('W13-F-04', 'ticket_participants يعرف ثلاثة ادوار لا اربعة',
          'TKT-04', "enum('reporter','assignee','watcher','duplicate_reporter')",
          'اربعة ادوار معلنة في repair01_w13_parties ومقيدة بـchk_tkp_role',
          'قائمة ادوار بلا محل بلاغ ولا مالك دورة تجعل الفصل الثلاثي غير قابل للقياس اصلا'),
    array('W13-F-05', 'الخصم التاديبي لم يكن مسنودا بقرار قضية',
          'HR-18', 'payroll_deductions.source_type=penalty بلا قيد على مصدره',
          'raiseDeduction ترد بلا قضية محسومة نوع قرارها خصم',
          'الخصم حكم مالي على انسان - وحكم بلا قرار يسنده عقوبة بلا اجراء'),
    array('W13-F-06', 'ثلاثة اعمدة حية تقبل صفا بلا كيان قانوني',
          'HR-01', '48 صفا بلا company_id في ثلاثة جداول بلاغات',
          'معلن بعدده في W13-D-06 ويقاس في البوابة',
          'التشديد على بيانات حية دهس والاعلان بعدد يقاس اصدق من ادعاء صفر'),
    array('W13-F-07', 'اربعة اعمدة حية اخرى كانت تقبل العدم وامكن تشديدها',
          'HR-03', 'workforce_requirement و worker_evaluation و job_titles و ticket_communications',
          'شددت على صفر مخالف بعد قياسه اولا',
          'التشديد يقاس قبل ان يقع - وصف واحد بلا كيان يمنعه ويعلنه بدل ان يدهس'),
    array('W13-F-08', 'التصعيد كان يكتب محفزا خارج قائمة الجدول',
          'TKT-09', "triggered_by='sla' وقيمة الجدول sla_breach",
          'المحفز من قيم الجدول نفسها',
          'قيمة خارج القائمة ترد في القاعدة فتسقط الرحلة عند اول تصعيد بلا سبب مقروء'),
    array('W13-F-09', 'حالة التقييم النهائي كانت تصطدم بمسمى موجة سابقة في القاموس',
          'HR-16', "final معناه في القاموس اقفال نهائي من W11",
          'finalized برمزه ومسماه الخاص',
          'raw_code مفتاح عالمي عبر الموجات - ورمز يعاد استعماله يعرض معنى موجة اخرى في شاشة هذه'),
    array('W13-F-10', 'تسع فجوات للمراجعة الداخلية مختومة بهذه الموجة بلا متطلب يغطيها',
          'HR-01', '9 صفوف unit=IAF و wave_stage=W13',
          'مؤجلة معلنة بعددها في W13-D-08',
          'بناء سطح خارج قائمة المتطلبات يخالف نص المرحلة وتغيير ختمها تعديل خريطة من داخل مستفيد'),
);
$fixN = 0;
foreach ($FIX as $f) {
    if ($W("INSERT INTO repair01_w13_fixes (fix_key,title,revealed_by,before_num,after_num,why,src_ref)
            VALUES ('" . $esc($f[0]) . "','" . $esc($f[1]) . "','" . $esc($f[2]) . "','" . $esc($f[3]) . "',
                    '" . $esc($f[4]) . "','" . $esc($f[5]) . "','RPR-W13 §١١')")) { $fixN++; }
}
printf("  إصلاحاتٌ بمتطلَّبِها الكاشف %d\n\n", $fixN);

/* ═══════════════════════════════════════════════════════════════════════════
   الخلاصة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "──────────────────────────────────────────────────────────────\n";
printf("متطلَّبات %d · مِرساةٌ مُثبَتة %d · أسطحُ نموٍّ %d · سايدبار %d · حالات %d · فصلُ واجبات %d · أحداث %d\n",
    (int) $one("SELECT COUNT(*) FROM repair01_w13_scope"), $anchored, $newN, $sbN,
    (int) $one("SELECT COUNT(*) FROM repair01_w13_states"),
    (int) $one("SELECT COUNT(*) FROM repair01_w13_sod"),
    (int) $one("SELECT COUNT(*) FROM repair01_events WHERE wave = 'W13'"));
printf("طرفٌ من الأربعةِ مدموجٌ %d · تنفيذُ حلٍّ مملوكٌ للبلاغاتِ %d\n",
    $mergedNow['total'], $crpNow['total']);
echo "الحكم: كُتب ✔\n";
