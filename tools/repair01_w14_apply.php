<?php
/**
 * tools/repair01_w14_apply.php — قياسٌ وكتابةٌ للمرحلةِ الرابعةَ عشرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **السايدبارُ قبل الشاشات** (§٤ · RPR-PATCH-01 ③): الخطواتُ السبعُ بترتيبها
 *   على أسطحِ النطاقِ — وأسطحُ النموِّ تُسجَّل أوّلًا لأنّها جزءٌ من مقامِه.
 *
 * ⛔ `origin` = `W14` بالضبط (RPR-PATCH-02): أساسُ السجلِّ (٦٥١) مُجمَّدٌ،
 *   والنموُّ مسموحٌ **مختومًا وحدَه**.
 *
 * ◆ **وثلاثةُ نطاقاتٍ لا محرّكٌ واحد** (‏قيدُ المالك §١): `repair01_w14_domains`
 *   يُعلن لكلِّ جدولٍ نطاقًا واحدًا وخدمةً واحدةً تكتب فيه، والحاجبُ يقرأ
 *   **شيفرةَ الخدماتِ** فيرصد كتابةَ نطاقٍ في جدولِ نطاقٍ آخر.
 *
 * ◆ **والعتبةُ بحالتِها لا بقيمتِها وحدَها**: ما نصَّ عليه المالكُ حرفًا
 *   `OWNER_APPROVED` بمرجعِ قرارِه، وما لم يُجب عنه `CONFIG_PENDING` **بقيمةِ
 *   عدمٍ** وقيمةِ اختبارٍ موسومةٍ لا تنتقل. ⛔ **ولا رقمَ يُخترَع.**
 *
 * ◆ **وما لم يُجب عنه المالكُ يُسجَّل مؤجَّلًا صراحةً** في `repair01_w14_deferred`
 *   ⛔ **ولا يُخمَّن** — و`SYSTEM_ASSUMED_APPROVAL` يبقى صفرًا.
 *
 * التشغيل: php tools/repair01_w14_apply.php [--report] [--revert]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w14_scan.php';
require_once $ROOT . '/app/Services/Ui/UiLabelRegistry.php';
require_once $ROOT . '/app/Core/TenantRegistry.php';
require_once $ROOT . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("تعذّر الاتصال بالقاعدة\n"); }
$conn->set_charset('utf8mb4');
while (ob_get_level()) { ob_end_clean(); }

$REPORT = in_array('--report', $argv, true);
$REVERT = in_array('--revert', $argv, true);
$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) { return repair01_w14_one($conn, $sql); };
$W = function ($sql) use ($conn, $REPORT) {
    if ($REPORT) { return true; }
    if ($conn->query($sql) === true) { return true; }
    echo '  ✘ ' . $conn->error . "\n  ⇐ " . mb_substr(preg_replace('/\s+/', ' ', $sql), 0, 180) . "\n";
    return false;
};

echo "══ REPAIR01 · W14 — " . ($REVERT ? 'إرجاع' : ($REPORT ? 'قياسٌ بلا كتابة' : 'قياسٌ وكتابة')) . " ══\n\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ⓪ الإرجاع — يُفرِّغ ما كتبته هذه الأداةُ وحدَها
   ═══════════════════════════════════════════════════════════════════════════ */
if ($REVERT) {
    foreach (repair01_w14_new_surfaces() as $s) {
        $rt = $esc($s['route']);
        $conn->query("DELETE FROM nav_items WHERE route = '$rt'");
        $conn->query("DELETE FROM nav_canonical WHERE route = '$rt'");
        $conn->query("DELETE FROM role_permissions WHERE module_id IN (SELECT id FROM modules WHERE code = '$rt')");
        $conn->query("DELETE FROM modules WHERE code = '$rt'");
        $conn->query("DELETE FROM repair01_screen_registry WHERE route = '$rt' AND origin = 'W14'");
        $conn->query("DELETE FROM gov_screen_cycle WHERE screen_file = '"
                     . $esc(basename($s['route'])) . "' AND inputs_note LIKE 'RPR-W14 %'");
        $conn->query("DELETE FROM gov_space_appearances WHERE route = '$rt' AND src_class = 'RPR-W14'");
    }
    /* **البندُ المنقولُ يعود إلى موضعِه قبلَ حذفِ مجموعتِه** (‏درسُ W12 §١-⑤) */
    $back = 0;
    $rb = $conn->query("SELECT nav_item_id, from_group_id FROM repair01_w14_nav_moves");
    while ($rb && $bx = $rb->fetch_assoc()) {
        if ($conn->query("UPDATE nav_items SET group_id = " . (int) $bx['from_group_id']
                         . " WHERE id = " . (int) $bx['nav_item_id'])) { $back++; }
    }
    echo "  ✔ بنودٌ أُعيدت إلى موضعِها الأصليّ $back\n";
    $conn->query("DELETE FROM repair01_w14_nav_moves");
    $conn->query("DELETE FROM link_groups WHERE group_code LIKE 'n9o_w14\\_%'");
    $orphan = (int) repair01_w14_one($conn, "SELECT COUNT(*) FROM nav_items n
                                              LEFT JOIN link_groups g ON g.id = n.group_id
                                             WHERE n.group_id > 0 AND g.id IS NULL");
    echo '  ' . ($orphan === 0 ? '✔' : '✘') . " بندٌ يتيمٌ بعد الإرجاع $orphan\n";
    foreach (array('repair01_w14_scope', 'repair01_w14_sidebar', 'repair01_w14_decisions',
                   'repair01_w14_states', 'repair01_w14_sod', 'repair01_w14_thresholds',
                   'repair01_w14_fixes', 'repair01_w14_journey', 'repair01_w14_domains',
                   'repair01_w14_deferred') as $t) {
        $conn->query("DELETE FROM `$t`");
    }
    /* **والمسمّياتُ المسجَّلةُ من هذه الأداةِ تُنزَع معها** — وإلّا بقي مسمًّى
       مسجَّلٌ لسطحٍ نُزع، فيقرأ كاشفُ W06 اسمًا في السجلِّ بلا مُصيَّرٍ يقابله. */
    $conn->query("DELETE FROM repair01_ui_labels WHERE origin = 'W14'");
    /* **والفجواتُ الموفّاةُ تعود بلا موفٍّ** — ولا يُمَسُّ سواه */
    foreach (array('iaf_board.php', 'iaf_programs.php', 'iaf_samples.php') as $gh) {
        $conn->query("UPDATE repair01_target_gaps SET built_counterpart = ''
                       WHERE origin_note LIKE '%" . $esc($gh) . "%' AND origin_stage = 'W02'");
    }
    $conn->query("DELETE FROM rsk_taxonomy WHERE src_ref LIKE 'RPR-W14%'");
    $conn->query("DELETE FROM ctl_classification_rule WHERE src_ref LIKE 'RPR-W14%'");
    $conn->query("DELETE FROM repair01_events WHERE wave = 'W14'");
    $conn->query("DELETE FROM repair01_w6_code_dict WHERE src_ref LIKE 'RPR-W14%'");
    /* ⛔ **وقراراتُ المالكِ لا تُقلَب بإرجاعِ أداة** — `DEC-OPEN-16` و`DEC-OPEN-17`
       و`DEC-OPEN-03` جوابُها قرارُ مالكٍ لا أثرُ تشغيل، وإرجاعُها انتحالٌ
       لقرارِه بالمقلوب. فتبقى كما اعتُمدت. */
    echo "الحكم: رجعت ✔ (والجداولُ تُنزع بهجرةِ التراجع · وقراراتُ المالكِ تبقى)\n";
    exit(0);
}

/* ═══════════════════════════════════════════════════════════════════════════
   ① قاموسُ الرموزِ — الرمزُ يبقى لاتينيًّا ويُعرَض عربيًّا
   ═══════════════════════════════════════════════════════════════════════════
   ⛔ **ولا يُدهَس مسمًّى سجّلَته موجةٌ سابقة**: `raw_code` مفتاحٌ عالميٌّ عبرَ
     الموجات — فالكتابةُ **إدراجٌ لما غاب وحدَه**.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "① قاموسُ رموزِ النطاق ─────────────────────────────────────────\n";
$DICT = array(
    /* التمييزُ الثلاثيُّ — محورُ المرحلة */
    array('DEVIATION_ONLY', 'انحراف تشغيلي فقط', 'W14_CLASS'),
    array('RISK_EXPOSURE', 'تعرض للخطر', 'W14_CLASS'),
    array('GOVERNANCE_BREACH', 'خرق ضابط', 'W14_CLASS'),
    array('EXPOSURE_AND_BREACH', 'تعرض وخرق معا', 'W14_CLASS'),
    array('PENDING', 'قيد التصنيف', 'W14_CLASS'),
    array('retained', 'باق عند مالكه', 'W14_STATE'),
    array('referred', 'محال', 'W14_STATE'),
    array('classified', 'مصنف', 'W14_STATE'),
    /* أنواعُ التوقّفِ من قرارِ المالكِ الثاني */
    array('UNPLANNED_DOWNTIME', 'توقف غير مخطط', 'W14_DOWNTIME'),
    array('PLANNED_MAINTENANCE', 'صيانة رئيسية مخططة', 'W14_DOWNTIME'),
    array('PLANNED_OVERHAUL', 'عمرة مخططة', 'W14_DOWNTIME'),
    array('CLIENT_STANDBY', 'استعداد بسبب العميل', 'W14_DOWNTIME'),
    array('OPERATIONAL_STANDBY', 'استعداد تشغيلي معتمد', 'W14_DOWNTIME'),
    array('PREVENTABLE_DOWNTIME', 'توقف كان يمكن منعه', 'W14_DOWNTIME'),
    array('TECHNICAL_CAPABILITY_DELAY', 'تأخر بسبب نقص القدرة الفنية', 'W14_DOWNTIME'),
    /* قواعدُ المحفِّز */
    array('UNPLANNED_24H', 'توقف غير مخطط تجاوز الحد', 'W14_TRIGGER'),
    array('SIMPLE_ISSUE_3D', 'مشكلة بسيطة امتدت فوق الحد', 'W14_TRIGGER'),
    array('RECURRENCE_3X', 'تكرار فوق الحد', 'W14_TRIGGER'),
    array('PREVENTABLE', 'عطل كان يمكن منعه', 'W14_TRIGGER'),
    array('MATERIAL_PRODUCTION_IMPACT', 'أثر إنتاجي جوهري', 'W14_TRIGGER'),
    array('TECHNICAL_CAPABILITY_GAP', 'فجوة قدرة فنية متكررة', 'W14_TRIGGER'),
    array('MATERIAL_PROCUREMENT_DELAY', 'تأخر شراء جوهري', 'W14_TRIGGER'),
    array('raised', 'مفتوح', 'W14_STATE'),
    array('triaged', 'مفروز', 'W14_STATE'),
    array('converted', 'تحول إلى خطر', 'W14_STATE'),
    array('dismissed', 'مستبعد بسببه', 'W14_STATE'),
    /* عائلاتُ المخاطرِ الأربع */
    array('OPERATIONAL', 'تشغيلية', 'W14_FAMILY'),
    array('CAPITAL', 'رأسمالية', 'W14_FAMILY'),
    array('CUSTOMER_CONTRACTUAL', 'تعاقدية مع العملاء', 'W14_FAMILY'),
    array('PROCUREMENT_SUPPLY', 'مشتريات وسلاسل توريد', 'W14_FAMILY'),
    array('near_miss', 'واقعة كادت تقع', 'W14_EVENT_KIND'),
    array('loss', 'واقعة خسارة', 'W14_EVENT_KIND'),
    array('linked', 'مرتبط بخطر', 'W14_STATE'),
    array('RESIDUAL_WITHIN_LIMIT', 'المتبقي ضمن الحد المقبول', 'W14_CLOSURE'),
    array('CAUSE_REMOVED', 'زال سبب الخطر', 'W14_CLOSURE'),
    array('SCOPE_ENDED', 'انتهى نطاق الخطر', 'W14_CLOSURE'),
    array('MERGED_INTO_OTHER', 'دمج في خطر آخر', 'W14_CLOSURE'),
    array('proposed', 'مقترح', 'W14_STATE'),
    array('evidenced', 'موثق بدليله', 'W14_STATE'),
    /* أساسُ حالةِ الحوكمة */
    array('MANDATORY_STEP_IGNORED', 'تجاهل إجراء إلزامي', 'W14_BASIS'),
    array('NO_ESCALATION', 'عدم تصعيد مطلوب', 'W14_BASIS'),
    array('AUTHORITY_EXCEEDED', 'تجاوز صلاحية', 'W14_BASIS'),
    array('MANIPULATION', 'تلاعب', 'W14_BASIS'),
    array('CONCEALMENT', 'إخفاء', 'W14_BASIS'),
    array('FORGERY', 'تزوير', 'W14_BASIS'),
    array('POLICY_BREACH', 'خرق سياسة', 'W14_BASIS'),
    array('CONTROL_BROKEN', 'كسر ضابط', 'W14_BASIS'),
    array('action_assigned', 'أسند له إجراء', 'W14_STATE'),
    array('remediated', 'عولج', 'W14_STATE'),
    array('investigated', 'حقق فيه', 'W14_STATE'),
    /* التحقيقات */
    array('DISCIPLINARY', 'تأديبي', 'W14_INV'),
    array('INTEGRITY', 'نزاهة وامتثال', 'W14_INV'),
    array('OPERATIONAL_FACT', 'تقصي وقائع تشغيلي', 'W14_INV'),
    array('SPECIAL_INDEPENDENT', 'تحقيق مستقل بتكليف', 'W14_INV'),
    array('INTEGRITY_REPORT', 'بلاغ نزاهة', 'W14_ORIGIN'),
    array('DENIAL', 'سجل محاولة ممنوعة', 'W14_ORIGIN'),
    array('BREACH', 'إخلال مسجل', 'W14_ORIGIN'),
    array('AUDIT_FINDING', 'ملاحظة مراجعة', 'W14_ORIGIN'),
    array('MANAGEMENT_REQUEST', 'طلب إداري', 'W14_ORIGIN'),
    array('OWNER_ORDER', 'أمر المالك', 'W14_ORIGIN'),
    array('mandated', 'صدر تكليفه', 'W14_STATE'),
    array('concluded', 'انتهى بنتيجة', 'W14_STATE'),
    /* الحوكمةُ عمومًا */
    array('effective', 'نافذ', 'W14_STATE'),
    array('superseded', 'خلفه إصدار أحدث', 'W14_STATE'),
    array('monitored', 'قيد المراقبة', 'W14_STATE'),
    array('met', 'مستوفى', 'W14_STATE'),
    array('breached', 'مخالف', 'W14_STATE'),
    array('prepared', 'جاهز للتقديم', 'W14_STATE'),
    array('acknowledged', 'مستلم بإيصال', 'W14_STATE'),
    array('late', 'متأخر', 'W14_STATE'),
    array('disclosed', 'مفصح عنه', 'W14_STATE'),
    array('recused', 'تنحى عنه', 'W14_STATE'),
    array('declared', 'معلن', 'W14_STATE'),
    array('returned', 'مردود', 'W14_STATE'),
    array('declined', 'مرفوض قبوله', 'W14_STATE'),
    array('defined', 'معرف', 'W14_STATE'),
    array('detected', 'مكتشف', 'W14_STATE'),
    array('formed', 'مشكل', 'W14_STATE'),
    array('dissolved', 'منحل', 'W14_STATE'),
    array('gift', 'هدية', 'W14_GIFT'),
    array('hospitality', 'ضيافة', 'W14_GIFT'),
    array('travel', 'سفر', 'W14_GIFT'),
    array('semiannual', 'نصف سنوي', 'W14_CYCLE'),
    array('on_event', 'عند الحدث', 'W14_CYCLE'),
    array('on_call', 'عند الطلب', 'W14_CYCLE'),
    array('tracking', 'قيد المتابعة', 'W14_STATE'),
    array('plan_done', 'الخطة منجزة', 'W14_STATE'),
    array('mitigate', 'تخفيف بضوابط', 'W14_DECISION'),
    array('recuse', 'تجنيب', 'W14_DECISION'),
    array('accept', 'قبول', 'W14_DECISION'),
    array('decline', 'رد', 'W14_DECISION'),
    array('evidence_submitted', 'قدم دليله', 'W14_STATE'),
    /* المراجعةُ الداخلية */
    array('drafted', 'مسودة', 'W14_STATE'),
    array('executing', 'قيد التنفيذ', 'W14_STATE'),
    array('requested', 'مطلوب', 'W14_STATE'),
    array('provided', 'زود', 'W14_STATE'),
    array('drawn', 'مسحوب', 'W14_STATE'),
    array('tested', 'اختبر', 'W14_STATE'),
    array('not_applicable', 'غير منطبق', 'W14_RESULT'),
    array('inquiry', 'استفسار', 'W14_METHOD'),
    array('observation', 'ملاحظة ميدانية', 'W14_METHOD'),
    array('inspection', 'فحص مستندي', 'W14_METHOD'),
    array('reperformance', 'إعادة تنفيذ', 'W14_METHOD'),
    array('analytics', 'تحليل بياني', 'W14_METHOD'),
    array('INDEPENDENCE_LOSS', 'فقد الاستقلال', 'W14_FRISK'),
    array('COMPETENCY_GAP', 'نقص الكفاءة', 'W14_FRISK'),
    array('COVERAGE_GAP', 'ضعف التغطية', 'W14_FRISK'),
    array('PLAN_DELAY', 'تأخر عن الخطة', 'W14_FRISK'),
    array('QUALITY_GAP', 'ضعف الجودة', 'W14_FRISK'),
    array('ACCESS_DENIED', 'منع الوصول', 'W14_FRISK'),
    array('identified', 'محدد', 'W14_STATE'),
    array('treated', 'معالج', 'W14_STATE'),
    array('owner', 'المالك', 'W14_REPORT_LINE'),
    array('audit_committee', 'لجنة المراجعة', 'W14_REPORT_LINE'),
    /* رموزٌ مُعلَنةٌ بقيت بلا مسمًّى في القاموسِ المركزيّ — تُبذَر هنا */
    array('registered', 'مسجل', 'W14_STATE'),
    array('recorded', 'مقيد', 'W14_STATE'),
    array('assessed', 'مقيَّم', 'W14_STATE'),
    array('evidence', 'جمع الأدلة', 'W14_STATE'),
    array('retired', 'متقاعد', 'W14_STATE'),
    array('mitigated', 'معالج بضوابط', 'W14_STATE'),
    array('exempt', 'معفى', 'W14_STATE'),
    array('assigned', 'مسند', 'W14_STATE'),
    array('escalated', 'مصعَّد', 'W14_STATE'),
    array('exception', 'استثناء في النتيجة', 'W14_RESULT'),
    array('reject', 'رفض', 'W14_DECISION'),
    array('once', 'مرة واحدة', 'W14_CYCLE'),
    array('weekly', 'أسبوعي', 'W14_CYCLE'),
    array('external', 'خارجي', 'W14_SOURCE'),
    /* حالاتُ العتبة */
    array('OWNER_APPROVED', 'معتمدة من المالك', 'W14_TH_STATUS'),
    array('CONFIG_PENDING', 'بانتظار اعتماد قيمتها', 'W14_TH_STATUS'),
);
$dictN = 0; $dictSkip = 0;
foreach ($DICT as $d) {
    $have = (int) $one("SELECT COUNT(*) FROM repair01_w6_code_dict
                         WHERE raw_code = '" . $esc($d[0]) . "' AND display_ar <> ''");
    if ($have > 0) { $dictSkip++; continue; }
    if ($W("INSERT INTO repair01_w6_code_dict (raw_code, display_ar, code_family, src_ref)
            VALUES ('" . $esc($d[0]) . "','" . $esc($d[1]) . "','" . $esc($d[2]) . "','RPR-W14 §١')
            ON DUPLICATE KEY UPDATE display_ar = IF(display_ar = '', VALUES(display_ar), display_ar)")) {
        $dictN++;
    }
}
$dictMiss = repair01_w14_dict_missing($conn);
printf("  رموزٌ بذُرت %d · قائمةٌ من موجاتٍ سابقةٍ %d · بلا مسمًّى بعد %d%s\n\n",
    $dictN, $dictSkip, count($dictMiss),
    $dictMiss ? ' ⇐ ' . implode('، ', array_slice($dictMiss, 0, 6)) : '');

/* ═══════════════════════════════════════════════════════════════════════════
   ② أسطحُ النموِّ — مختومةٌ بـW14
   ═══════════════════════════════════════════════════════════════════════════ */
echo "② أسطحُ النموِّ — مختومةٌ بـW14 ─────────────────────────────────\n";
$newN = 0; $navN = 0; $permN = 0; $labelN = 0; $missing = array(); $fulfilled = array(); $collide = array();
$maxSid = (int) preg_replace('/\D/', '', (string) $one("SELECT screen_id FROM repair01_screen_registry
                                                          ORDER BY screen_id DESC LIMIT 1"));
foreach (repair01_w14_new_surfaces() as $s) {
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

    /* ⓑ المنحُ — لكلِّ دورٍ يرى الشقيقَ اليوم؛ **والشقيقُ يُقاس ببلوغِه**
         (‏درسُ W12 §١-③): شقيقٌ بصفرِ منحٍ ينتج صفرًا فيُبنى السطحُ ولا يبلغه أحد. */
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
        } else {
            echo '  ⚠ لا شقيقَ في الموديولات لـ' . $s['route'] . " — لا منحَ يُشتقّ\n";
        }
    }

    /* ⓒ **المسمّى يُسجَّل قبل أن يُصيَّر** (‏W06) — واسمٌ مشكولٌ أو تقنيٌّ يُردّ */
    if (!$REPORT) {
        $lr = \App\Services\Ui\UiLabelRegistry::register($conn, 'screen:' . strtolower($s['route']), $s['ar'], array(
            'allowed_context' => 'SIDEBAR SCREEN_TITLE',
            'source_table' => 'nav_canonical', 'source_column' => 'canonical_ar',
            'source_key' => $s['route'], 'owner_code' => $s['owner'],
            'visibility_class' => 'USER_VISIBLE', 'label_state' => 'ACTIVE',
            'rule_id' => 'W14_NEW_SURFACE_LABEL', 'origin' => 'W14',
            'src_ref' => 'RPR-W14 §٤ · سطحُ نموٍّ مختوم', 'caller' => 'repair01_w14_apply.php',
        ));
        if (!$lr['ok']) { echo '  ⚠ رُدَّ مسمّى ' . $s['route'] . ' — ' . $lr['code'] . ': ' . $lr['detail'] . "\n"; }
        else { $labelN++; }
        $gr = \App\Services\Ui\UiLabelRegistry::register($conn, 'group:w14:' . strtolower($s['group']),
            repair01_w14_group_ar($s['group']), array(
            'allowed_context' => 'SIDEBAR', 'source_table' => 'nav_canonical', 'source_column' => 'group_name',
            'source_key' => $s['group'], 'owner_code' => $s['owner'],
            'visibility_class' => 'USER_VISIBLE', 'label_state' => 'ACTIVE',
            'rule_id' => 'W14_CYCLE_GROUP_LABEL', 'origin' => 'W14',
            'src_ref' => 'RPR-W14 §٤ · مجموعةُ دورةِ العمل', 'caller' => 'repair01_w14_apply.php',
        ));
        if ($gr['ok']) { $labelN++; }
    }

    /* ⓓ السجلُّ المعياريُّ للتنقُّل — والترتيبُ من موضعِ السطحِ في الدورة
       ⚠ **والشبحُ المبنيُّ باسمِه ليس سطحًا ثانيًا**: إن كان في السجلِّ صفُّ
         شبحٍ (‏`route` عدمٌ) باسمِ الملفِّ نفسِه، فهو **مستهدَفُ الدراسةِ الذي
         بنيناه** — فيُوفَّى في مكانِه ⛔ **ولا يُمنَح مُعرِّفًا ثانيًا**، وإلّا
         صارت الشاشةُ الواحدةُ بمُعرِّفَين وسقط `W2-01` و`W2-02` و`W2-06`.
         ⛔ **ولا يُمَسُّ ختمُه** — يبقى من الأساسِ ولا يصير نموًّا (RPR-PATCH-02). */
    /* ⚠ **ولا يُبنى ملفٌّ باسمِ شبحٍ في لقطةِ الدراسة** — **الفجوةُ اسمٌ
         مستهدَفٌ لا اسمُ ملفٍّ مُلزِم** (‏سوابقُ `W9-D-08` و`W11-D-11` و
         `W12-D-04` و`W13 §٢-د`). وبناؤه باسمِه يفكُّ شبحيّتَه فيتفرَّق
         مخزونُ ثلاثةِ دفاترَ عن الحيِّ ويسقط `W2-06` أو `W2-07` أو `W8-09`
         أو `W9-23` — **والحكمُ لا يُنقَض بتعديلِ حاجبٍ مُغلَق**. فالسطحُ يُبنى
         باسمِه المعياريِّ الخاصّ، **والفجوةُ تُقيَّد موفّاةً بـ`built_counterpart`**. */
    $ghostSid = (string) $one("SELECT screen_id FROM repair01_screen_registry
                                WHERE screen_file = '" . $esc($file) . "'
                                  AND (route IS NULL OR route = '') LIMIT 1");
    if ($ghostSid !== '') {
        echo '  ⛔ ' . $s['route'] . " يصطدم باسمِ شبحٍ في لقطةِ الدراسة — لا يُسجَّل\n";
        $collide[] = $s['route']; continue;
    }
    $sid = (string) $one("SELECT screen_id FROM repair01_screen_registry WHERE route = '$rt' LIMIT 1");
    if ($sid === '') { $maxSid++; $sid = 'SCR-' . str_pad((string) $maxSid, 4, '0', STR_PAD_LEFT); }
    $baseOrigin = 'W14'; $gVerdict = ''; $gWhy = ''; $isFulfil = false;
    $W("INSERT INTO nav_canonical (route, canonical_ar, level_no, level_name, group_name, sort_no,
                                   status, decision_state, application_state, decision_source,
                                   derivation, retirement_status, screen_id)
        VALUES ('$rt','" . $esc($s['ar']) . "',2,'العمليات','" . $esc(repair01_w14_group_ar($s['group'])) . "',"
                . (int) $s['sort'] . ",
                'APPROVED','APPROVED','DEPLOYED','RPR-W14 · المخاطر والحوكمة والمراجعة (2026-08-27)',
                'ترتيب دورة الحوكمة ودورة المخاطر ودورة المراجعة في الحزمة','ACTIVE','" . $esc($sid) . "')
        ON DUPLICATE KEY UPDATE canonical_ar=VALUES(canonical_ar), group_name=VALUES(group_name),
          sort_no=VALUES(sort_no), status=VALUES(status), screen_id=VALUES(screen_id)");

    /* ⓔ **مجموعةُ الدورةِ لا مجموعةُ الشقيق** — والمُعرِّفُ من `screen_id` لا
         من المسار (‏عطبُ W07 المقيس: `group_code` عرضُه محدودٌ فيبتُر ويتصادم). */
    if ($modId > 0) {
        $gkey = 'n9o_w14_' . strtolower(str_replace('-', '', $sid));
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
                    VALUES ('" . $esc(repair01_w14_group_ar($s['group'])) . "','" . $esc($code) . "',$rid,
                            '" . $esc($s['icon']) . "'," . ((int) $sx['display_order'] + 1) . ","
                            . (int) $sx['stage_no'] . ",'" . $esc((string) $sx['stage_title']) . "',1)");
                $gid = (int) $one("SELECT id FROM link_groups WHERE group_code = '" . $esc($code) . "' LIMIT 1");
            } else {
                $W("UPDATE link_groups SET name = '" . $esc(repair01_w14_group_ar($s['group'])) . "',
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

    /* ⓕ مصفوفةُ الدورةِ الحيّة — واسمُ الإدارةِ من جسرِ المسمّياتِ لا مخترَعًا */
    if ($modId > 0) {
        $deptAr = (string) $one("SELECT legacy_name FROM repair01_dept_crosswalk
                                  WHERE canonical_code = '" . $esc($s['owner']) . "' ORDER BY id LIMIT 1");
        if ($deptAr === '') {
            $deptAr = (string) $one("SELECT name_ar FROM repair01_departments
                                      WHERE canonical_code = '" . $esc($s['owner']) . "' LIMIT 1");
        }
        if ($deptAr === '') { echo '  ⚠ لا جسرَ مسمًّى للإدارة ' . $s['owner'] . " — الصفُّ لا يُكتب\n"; }
        else {
            $W("DELETE FROM gov_screen_cycle
                 WHERE screen_file = '" . $esc($file) . "' AND inputs_note LIKE 'RPR-W14 %'");
            $W("INSERT INTO gov_screen_cycle
                (company_id, dept_name, layer_name, stage_order, stage_name, group_name, screen_title,
                 screen_file, inputs_note, output_doc, resp_role, next_state, consumers, fin_impact, stage_kind)
                VALUES (0,'" . $esc($deptAr) . "','" . $esc(repair01_w14_group_ar($s['group'])) . "','"
                        . (int) $s['sort'] . "','" . $esc(repair01_w14_group_ar($s['group'])) . "','"
                        . $esc(repair01_w14_group_ar($s['group'])) . "',
                        '" . $esc($s['ar']) . "','" . $esc($file) . "',
                        '" . $esc('RPR-W14 · متطلبات: ' . $s['req']) . "','" . $esc($s['doc']) . "',
                        '" . $esc($s['role']) . "','" . $esc($s['next']) . "','" . $esc($s['cons']) . "',
                        '" . $esc($s['fin']) . "','canonical')");
        }
    }

    /* ⓖ سجلُّ الشاشاتِ — **بختمِ الموجةِ وبالحقولِ الاثنَي عشرَ كاملةً**
         (‏سقّاطةُ البند 9: الجديدُ `Zero Tolerance`). */
    $guard = repair01_w14_guard_of($ROOT, $s['route']);
    /* **الصنفُ وحكمُ الملكيّةِ مُعلَنانِ لا مُشتقّانِ من اسمِ ملفّ** — والقيدُ
       `chk_w135_ownv` يحصر الحكمَ، و`chk_w135_why` يشترط قاعدةً معه. */
    list($sKind, $sOwnv) = repair01_w14_surface_class($s['route']);
    $W("INSERT INTO repair01_screen_registry
        (screen_id, screen_file, route, route_rule, owner_code, owner_role, owner_rule,
         lifecycle, lifecycle_rule, parent_screen_id, parent_rule, visibility_class, visibility_rule,
         on_disk, origin, ghost_verdict, ghost_why, guard_kind, guard_evidence, w2_why, src_ref,
         canonical_label_ar, surface_kind, ownership_verdict, verdict_rule, verdict_at,
         action_guard, permission_policy, grain_ar, source_of_truth, state_model_ref)
        VALUES ('" . $esc($sid) . "','" . $esc($file) . "','$rt','W14_NEW_SURFACE_ROUTE',
                '" . $esc($s['owner']) . "','" . $esc($s['role']) . "','W14_REQUIREMENT_OWNER',
                'LIVE_UNREGISTERED','" . ($isFulfil ? 'W14_TARGET_FULFILLED_IN_PLACE' : 'W14_GROWTH_OUTSIDE_STUDY_MATRIX') . "','','','MENU_ITEM','NAV_ITEMS_ACTIVE',
                1,'" . $esc($baseOrigin) . "','" . $esc($gVerdict) . "','" . $esc($gWhy) . "',
                '" . $esc($guard['kind']) . "','" . $esc($guard['evidence']) . "',
                '" . $esc($s['ar']) . " (" . $esc($file) . ")','RPR-W14 · المخاطر والحوكمة والمراجعة',
                '" . $esc($s['ar']) . "','" . $esc($sKind) . "','" . $esc($sOwnv) . "',
                '" . $esc('RPR-W14 §٢ · صنف السطح وحكم ملكيته معلنان في سجل الموجة لا مشتقان من اسم ملف') . "',
                NOW(),'ems_action_guard',
                'ROLE_GRANT_VIA_MODULE','" . $esc($s['doc']) . "','" . $esc($s['role']) . "',
                'W14_STATE_MACHINES')
        ON DUPLICATE KEY UPDATE owner_code=VALUES(owner_code), owner_role=VALUES(owner_role),
          visibility_class=VALUES(visibility_class), guard_kind=VALUES(guard_kind),
          guard_evidence=VALUES(guard_evidence), origin=VALUES(origin), on_disk=1,
          route=VALUES(route), route_rule=VALUES(route_rule), lifecycle=VALUES(lifecycle),
          ghost_verdict=VALUES(ghost_verdict), ghost_why=VALUES(ghost_why),
          canonical_label_ar=VALUES(canonical_label_ar), surface_kind=VALUES(surface_kind),
          ownership_verdict=VALUES(ownership_verdict), verdict_rule=VALUES(verdict_rule),
          verdict_at=VALUES(verdict_at), action_guard=VALUES(action_guard),
          permission_policy=VALUES(permission_policy), grain_ar=VALUES(grain_ar),
          source_of_truth=VALUES(source_of_truth), state_model_ref=VALUES(state_model_ref)");
    $newN++;
}
printf("  أسطحُ نموٍّ مختومةٌ %d · بنودُ قائمةٍ نشِطة %d · منحٌ %d · مسمّياتٌ مسجَّلة %d"
     . " · بلا ملفٍّ %d%s · مصطدمٌ باسمِ شبحٍ %d%s\n\n",
    $newN, $navN, $permN, $labelN, count($missing),
    $missing ? ' ⇐ ' . implode('، ', $missing) : '',
    count($collide), $collide ? ' ⇐ ' . implode('، ', $collide) : '');

/* ═══════════════════════════════════════════════════════════════════════════
   ②-ب · مصفوفةُ الواجهة — **السطحُ المُصيَّرُ يلزمه صفٌّ فيها** (‏`U1`)
   ═══════════════════════════════════════════════════════════════════════════ */
echo "②-ب مصفوفةُ الواجهةِ — صفٌّ لكلِّ سطحٍ مُصيَّر ──────────────────\n";
$MTX = $ROOT . '/docs/uxui_matrix_20260818.csv';
$mtxN = 0;
if (!is_file($MTX)) { echo "  ⚠ مصفوفةُ الواجهةِ غيرُ موجودة — التسجيلُ يُتخطّى\n"; }
elseif ($REPORT) { echo "  ↷ قياسٌ بلا كتابة\n"; }
else {
    /* ⚠ **الصفوفُ الباقيةُ تُنقَل خامًّا لا يُعاد ترميزُها** (‏درسُ W13) */
    $lines = file($MTX, FILE_IGNORE_NEW_LINES);
    $hdr = array_shift($lines);
    $mine = array(); $keep = array(); $maxN = 0;
    foreach (repair01_w14_new_surfaces() as $s) { $mine[strtolower($s['route'])] = $s; }
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
    $rowsCsv = array();
    foreach (repair01_w14_new_surfaces() as $s) {
        $maxN++;
        $grp = repair01_w14_group_ar($s['group']);
        $depAr = (string) $one("SELECT name_ar FROM repair01_departments WHERE canonical_code = '"
                               . $esc($s['owner']) . "'");
        if ($depAr === '') { $depAr = $s['owner']; }
        $def = 'تعرض ' . $s['ar'] . ' في دورة ' . $grp . ' لدى ' . $depAr
             . '. المستند الناتج ' . $s['doc'] . ' والخطوة التالية ' . $s['next'] . '.';
        $vals = array($maxN, $s['route'], $s['ar'], $s['ar'], '', '—', $def, $depAr,
            '2 — العمليات', $grp, $s['sort'], 'شاشةٌ مستقلة', 1, $s['cons'],
            'قدرةٌ ثبت غيابُها فبُنيت في موضعِها المعياريّ', 'APPROVED',
            'ترتيبُ دورةِ العملِ في الحزمة — RPR-W14', '—', '—', 'ACTIVE', '—',
            $s['ar'], $grp, 'موضعُه من دورةِ العمل — قرارُ الورقة', $grp);
        $rowsCsv[] = implode(',', array_map($cell, $vals));
        $mtxN++;
    }
    file_put_contents($MTX, $hdr . "\n" . implode("\n", $keep) . "\n" . implode("\n", $rowsCsv) . "\n");
}
printf("  صفوفُ مصفوفةٍ مكتوبةٌ لأسطحِ الموجة %d\n\n", $mtxN);

/* ═══════════════════════════════════════════════════════════════════════════
   ②-ج · سجلُّ تصنيفِ المساحات — **الغيابُ ليس منعًا** (`NF-24` · `GAP-22`)
   ═══════════════════════════════════════════════════════════════════════════ */
echo "②-ج تصنيفُ المساحاتِ — سطحٌ نشطٌ لا يُقرأ مفتوحًا افتراضًا ────────\n";
$spaceN = 0;
if (!repair01_w14_table_exists($conn, 'gov_space_appearances')) {
    echo "  ⚠ سجلُّ المساحاتِ غيرُ موجود — التصنيفُ يُتخطّى\n";
} elseif ($REPORT) { echo "  ↷ قياسٌ بلا كتابة\n"; }
else {
    foreach (repair01_w14_new_surfaces() as $s) {
        $rt = $esc($s['route']);
        $depAr2 = (string) $one("SELECT name_ar FROM repair01_departments WHERE canonical_code = '"
                                . $esc($s['owner']) . "'");
        if ($depAr2 === '') { $depAr2 = $s['owner']; }
        $W("DELETE FROM gov_space_appearances WHERE route = '$rt' AND src_class = 'RPR-W14'");
        /* ⚠ **المفتاحُ هنا لا يتزايد ذاتيًّا** — فيُشتقُّ في كلِّ صفٍّ (‏درسُ W11) */
        $nextId = (int) $one("SELECT COALESCE(MAX(id), 0) + 1 FROM gov_space_appearances");
        $W("INSERT INTO gov_space_appearances
            (id, space_ar, space_kind, tab_ar, screen_ar, route, owner_dept_ar, owner_kind,
             src_class, src_ownership, src_decision, src_note, spaces_count,
             cls, ownership, decision, basis, rule_step, view_fields, updated_at)
            VALUES ($nextId,'" . $esc($depAr2) . "','DEPARTMENT','','" . $esc($s['ar']) . "','$rt',
                    '" . $esc($depAr2) . "','BUSINESS_DEPARTMENT',
                    'RPR-W14','VALID','CONFIRMED',
                    '" . $esc('سطح نمو مختوم W14 - صنف بسلم الحسم السداسي') . "',1,
                    'OWNED','VALID','CONFIRMED',
                    '" . $esc('المساحة هي الادارة المالكة للسطح في السجل المعياري') . "',
                    1,'',NOW())");
        $spaceN++;
    }
}
printf("  أسطحٌ مصنَّفةٌ في سجلِّ المساحات %d\n\n", $spaceN);

/* ═══════════════════════════════════════════════════════════════════════════
   ②-د · شبحٌ أُوفيَ ببناءٍ باسمِه — **يُقاس على القرصِ لا يُترَك مخزَّنًا**
   ═══════════════════════════════════════════════════════════════════════════
   ◆ ثلاثةُ أسطحٍ مستهدَفةٍ في لقطةِ الدراسةِ كانت `on_disk = 0` — واسمُها
     المستهدَفُ **هو نفسُه** اسمُ ما بنته هذه الموجة. فبناؤها **يفكُّ شبحيّتَها**،
     و`G0-08` يقيس الشبحَ **مسحًا عوديًّا حيًّا** ويقارنه بالمخزَّن: فترْكُ
     المخزَّنِ على حالِه **يجعل الرقمَ كاذبًا في الاتّجاهِ المتشائم**.
   ⛔ **ولا يُمَسُّ صفٌّ لم يُبنَ له ملفٌّ فعلًا** — والقياسُ من القرصِ وحدَه.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "②-د الفجواتُ — موفّاةٌ باسمٍ آخرَ لا توأمًا ─────────────────────\n";
/* **ثلاثُ فجواتٍ مستهدَفةٍ ختمَتها W02 وأجّلتها W13 بُنيت قدرتُها هنا** —
   وبأسماءٍ معياريّةٍ خاصّةٍ لا بأسماءِ أشباحِها. فتُقيَّد موفّاةً في
   `built_counterpart` ⛔ **ولا يُمَسُّ `on_disk` ولا `origin_stage` ولا صفُّ
   الشبح** — وهو عينُ ما فعلته W13 في ثلاثَ عشرةَ فجوة. */
$GAPFILL = array(
    'iaf_board.php'    => 'Audit/iaf_overview.php',
    'iaf_programs.php' => 'Audit/iaf_audit_programs.php',
    'iaf_samples.php'  => 'Audit/iaf_test_samples.php',
);
$gapN = 0; $gapMiss = array();
foreach ($GAPFILL as $ghost => $built) {
    if (!is_file($ROOT . '/' . $built)) { $gapMiss[] = $ghost . ' (لا ملف)'; continue; }
    $hit = (int) $one("SELECT COUNT(*) FROM repair01_target_gaps
                        WHERE origin_note LIKE '%" . $esc($ghost) . "%' AND origin_stage = 'W02'");
    if ($hit === 0) { $gapMiss[] = $ghost . ' (لا فجوة)'; continue; }
    $W("UPDATE repair01_target_gaps SET built_counterpart = '" . $esc($built) . "'
         WHERE origin_note LIKE '%" . $esc($ghost) . "%' AND origin_stage = 'W02'");
    $gapN += $hit;
}
$ghostStored = (int) $one("SELECT COUNT(*) FROM repair01_surfaces WHERE on_disk = 0");
printf("  فجواتٌ قُيِّدت موفّاةً باسمٍ آخر %d · بلا موفٍّ %d%s · الشبحُ المخزَّنُ كما هو %d\n\n",
    $gapN, count($gapMiss), $gapMiss ? ' ⇐ ' . implode('، ', $gapMiss) : '', $ghostStored);

/* ═══════════════════════════════════════════════════════════════════════════
   ③ نطاقُ المرحلة — ٦١ متطلَّبًا إلى مِرساتِها المُثبَتةِ قياسًا
   ═══════════════════════════════════════════════════════════════════════════ */
echo "③ نطاقُ المرحلة ───────────────────────────────────────────────\n";
$W("DELETE FROM repair01_w14_scope");
$ANCH = repair01_w14_anchors();
$anchored = 0; $unproven = array(); $ownerMismatch = array(); $unscoped = array();
$newRoutes = array_column(repair01_w14_new_surfaces(), 'route');

$rq = $conn->query("SELECT requirement_id, unit, group_name, surface, src_ref
                      FROM repair01_requirements WHERE stage_no = 14 ORDER BY unit, seq");
while ($rq && $q = $rq->fetch_assoc()) {
    $rid = $q['requirement_id'];
    if (!isset($ANCH[$rid])) { $unproven[] = $rid . ' (بلا مِرساةٍ مُعلَنة)'; continue; }
    $a = $ANCH[$rid];
    /* **والإدارةُ المتوقَّعةُ من إعلانِ المِرساةِ لا من رقمِ الوحدة**: `IAF`
       خارجَ التسلسلِ برمزٍ لا برقمِ إدارة (`DEC-OPEN-18`) — فاشتقاقُ `DEP-nn`
       من نصِّ الوحدةِ يُنتج `DEP-AS` وهو رمزٌ لا وجودَ له. */
    $dept = $a['domain'];
    $pr = repair01_w14_prove_anchor($conn, $ROOT, $a);
    if ($pr['verdict'] === 'ANCHORED') { $anchored++; }
    else { $unproven[] = $rid . ' (' . $pr['verdict'] . ')'; }

    $verdictOwner = ($pr['owner'] !== '' && $dept !== '' && $pr['owner'] !== $dept) ? 'MISMATCH' : 'MATCH';
    if ($verdictOwner === 'MISMATCH') { $ownerMismatch[] = $rid . ' ' . $pr['owner'] . ' بدل ' . $dept; }
    $build = in_array($a['route'], $newRoutes, true) ? 'BUILT_W14' : 'LIVE';
    $scoped = ($a['kind'] === 'TABLE' && repair01_w14_entity_scoped($conn, $a['probe'])) ? 1 : 0;
    if ($a['kind'] === 'TABLE' && $scoped === 0) { $unscoped[] = $rid . ' ⇐ ' . $a['probe']; }

    $W("INSERT INTO repair01_w14_scope
        (requirement_id,unit,group_name,surface,anchor_screen_id,anchor_route,anchor_probe,
         owner_measured,owner_expected,owner_verdict,build_verdict,cycle_step,entity_scoped,
         domain_code,line_of_defence,map_rule,map_why,src_ref)
        VALUES ('" . $esc($rid) . "','" . $esc($q['unit']) . "','" . $esc($q['group_name']) . "',
                '" . $esc($q['surface']) . "','" . $esc($pr['sid']) . "','" . $esc($a['route']) . "',
                '" . $esc($a['probe']) . "','" . $esc($pr['owner']) . "','" . $esc($dept) . "',
                '" . $esc($verdictOwner) . "','" . $esc($build) . "'," . (int) $a['step'] . ",$scoped,
                '" . $esc($a['domain']) . "','" . $esc($a['line']) . "',
                '" . $esc($pr['rule']) . "','" . $esc($a['why']) . "','" . $esc($q['src_ref']) . "')");
}
printf("  مُثبَتٌ من القرص %d · غيرُ مُثبَتٍ %d%s · مالكٌ مخالفٌ %d%s · جدولٌ بلا كيانٍ إلزاميٍّ %d%s\n\n",
    $anchored, count($unproven), $unproven ? ' ⇐ ' . implode('، ', array_slice($unproven, 0, 4)) : '',
    count($ownerMismatch), $ownerMismatch ? ' ⇐ ' . implode('، ', array_slice($ownerMismatch, 0, 4)) : '',
    count($unscoped), $unscoped ? ' ⇐ ' . implode('، ', array_slice($unscoped, 0, 4)) : '');

/* ═══════════════════════════════════════════════════════════════════════════
   ③-ب · الخطوةُ السابعةُ لأسطحِ النطاقِ القائمة — الربطُ بالسجلِّ المعياريّ
   ═══════════════════════════════════════════════════════════════════════════ */
echo "③-ب ربطُ أسطحِ النطاقِ بالسجلِّ المعياريّ ────────────────────────\n";
$linkFix = 0; $seen = array();
$rq2 = $conn->query("SELECT requirement_id, surface, group_name FROM repair01_requirements
                      WHERE stage_no = 14 ORDER BY unit, seq");
while ($rq2 && $q2 = $rq2->fetch_assoc()) {
    $rid = (string) $q2['requirement_id'];
    if (!isset($ANCH[$rid]) || $ANCH[$rid]['route'] === '') { continue; }
    $rt = $ANCH[$rid]['route'];
    if (isset($seen[$rt])) { continue; }
    $seen[$rt] = true;
    $rtE = $esc($rt);
    /* ⚠ **والصفُّ القائمُ قد يكون بلا مُعرِّف** — فتخطّي المسارِ لوجودِ صفٍّ
         يترك الخطوةَ السابعةَ ناقصةً وهي مُعلَنةٌ مكتملة. والخطوةُ السابعةُ
         **ربطٌ بالمُعرِّفِ لا وجودُ صفّ**: خمسةٌ وثلاثونَ سطحًا قائمًا كانت
         صفوفُها في السجلِّ المعياريِّ بلا `screen_id` فتُملأ من سجلِّ الشاشات. */
    if ((int) $one("SELECT COUNT(*) FROM nav_canonical WHERE route = '$rtE'") > 0) {
        $sidHave = (string) $one("SELECT COALESCE(screen_id,'') FROM nav_canonical
                                   WHERE route = '$rtE' LIMIT 1");
        if ($sidHave === '') {
            $sidReg = (string) $one("SELECT screen_id FROM repair01_screen_registry
                                      WHERE route = '$rtE' LIMIT 1");
            if ($sidReg === '') { echo "  ⚠ $rt بلا مُعرِّفٍ في سجلِّ الشاشات — لا يُربَط\n"; }
            else {
                $W("UPDATE nav_canonical SET screen_id = '" . $esc($sidReg) . "' WHERE route = '$rtE'");
                echo "  ✔ $rt ⇐ رُبط بمُعرِّفِه " . $sidReg . "\n";
                $linkFix++;
            }
        }
        continue;
    }
    $sid = (string) $one("SELECT screen_id FROM repair01_screen_registry WHERE route = '$rtE' LIMIT 1");
    if ($sid === '') { echo "  ⚠ $rt بلا مُعرِّفٍ في سجلِّ الشاشات — لا يُربَط\n"; continue; }
    $vis = (string) $one("SELECT visibility_class FROM repair01_screen_registry WHERE route = '$rtE' LIMIT 1");
    $own = (string) $one("SELECT owner_code FROM repair01_screen_registry WHERE route = '$rtE' LIMIT 1");
    $sortNo = (int) $ANCH[$rid]['step'];
    /* **والاسمُ يُنقّى قبل أن يُصيَّر**: لاحقةُ «بحسب انطباق الشركة» شرطُ تطبيقٍ
       لا اسمُ شاشة، والمصطلحُ اللاتينيُّ بعد الشرطةِ رمزٌ تقنيٌّ يُمنَع. */
    $label = repair01_w14_surface_label((string) $q2['surface']);
    if (!$REPORT) {
        $lr2 = \App\Services\Ui\UiLabelRegistry::register($conn, 'screen:' . strtolower($rt),
            $label, array(
            'allowed_context' => 'SIDEBAR SCREEN_TITLE',
            'source_table' => 'nav_canonical', 'source_column' => 'canonical_ar',
            'source_key' => $rt, 'owner_code' => $own !== '' ? $own : $ANCH[$rid]['domain'],
            'visibility_class' => 'USER_VISIBLE', 'label_state' => 'ACTIVE',
            'rule_id' => 'W14_SCOPE_SURFACE_LABEL', 'origin' => 'W14',
            'src_ref' => 'RPR-W14 §٣-ب · ربطُ سطحٍ قائمٍ بالسجلِّ المعياريّ',
            'caller' => 'repair01_w14_apply.php',
        ));
        if (!$lr2['ok']) { echo '  ⚠ رُدَّ مسمّى ' . $rt . ' — ' . $lr2['code'] . "\n"; }
    }
    $W("INSERT INTO nav_canonical (route, canonical_ar, level_no, level_name, group_name, sort_no,
                                   status, decision_state, application_state, decision_source,
                                   derivation, retirement_status, screen_id, placement_kind)
        VALUES ('$rtE','" . $esc($label) . "',2,'العمليات','"
                . $esc(repair01_w14_group_ar($q2['group_name'])) . "'," . $sortNo . ",
                'APPROVED','APPROVED','DEPLOYED','RPR-W14 · ربط سطح النطاق بالسجل المعياري (2026-08-27)',
                'التسمية المعيارية من repair01_requirements.surface منقاة','ACTIVE','" . $esc($sid) . "',
                '" . $esc($vis === 'TAB_CHILD' ? 'TAB' : 'MENU_ITEM') . "')");
    echo "  ✔ $rt ⇐ " . $label . " · ترتيب $sortNo\n";
    $linkFix++;
}
printf("  أسطحٌ رُبطت بالسجلِّ المعياريّ %d\n\n", $linkFix);

/* ═══════════════════════════════════════════════════════════════════════════
   ③-ج · الخطوتانِ ② و③ **تصحيحٌ لا قياس** — الاسمُ ثمَّ المجموعةُ ثمَّ الترتيب
   ═══════════════════════════════════════════════════════════════════════════
   ⛔ **ولا يُعاد تسميةُ مجموعةٍ مشتركة** (‏عطبُ W12 الثاني): السطحُ **يُنقَل
     إلى مجموعةٍ مختومةٍ بموجتِه** والمشتركةُ تبقى لأهلِها.
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
                              WHERE requirement_id = '" . $esc($rid) . "' AND stage_no = 14 LIMIT 1");
    if ($reqGrp === '') { continue; }
    $grpAr = repair01_w14_group_ar($reqGrp);
    $canGrp = (string) $one("SELECT group_name FROM nav_canonical WHERE route = '$rtE' LIMIT 1");
    if ($canGrp !== '' && $canGrp !== $grpAr) {
        $W("UPDATE nav_canonical SET group_name = '" . $esc($grpAr) . "' WHERE route = '$rtE'");
        $grpCanonFix++;
    }
    $wantSort = isset($stepByRoute[$a['route']]) ? (int) $stepByRoute[$a['route']] : (int) $a['step'];
    $curSort = (int) $one("SELECT sort_no FROM nav_canonical WHERE route = '$rtE' LIMIT 1");
    if ($curSort !== $wantSort) {
        $W("UPDATE nav_canonical SET sort_no = $wantSort WHERE route = '$rtE'");
        $W("UPDATE nav_items SET sort_order = $wantSort WHERE route = '$rtE'");
        $ordFix++;
    }
    $canAr = (string) $one("SELECT canonical_ar FROM nav_canonical WHERE route = '$rtE' LIMIT 1");
    if ($canAr === '') { continue; }
    $drift = (int) $one("SELECT COUNT(*) FROM nav_items WHERE route = '$rtE'
                          AND label_ar <> '" . $esc($canAr) . "'");
    if ($drift > 0) {
        $W("UPDATE nav_items SET label_ar = '" . $esc($canAr) . "' WHERE route = '$rtE'");
        $lblFix += $drift;
    }
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
        $code3 = 'n9o_w14_' . strtolower(str_replace('-', '', $sid3)) . '_r' . $rid3;
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
        /* **الموضعُ الأصليُّ يُقيَّد قبل النقل** — فالإرجاعُ يعيده حرفًا */
        $W("INSERT INTO repair01_w14_nav_moves
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
$W("DELETE FROM repair01_w14_sidebar");
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
    $guard = repair01_w14_guard_of($ROOT, $rt);
    $s6 = ($guard['kind'] !== 'NONE' && $permRows > 0) ? 'GUARDED_AND_GRANTED'
        : ($guard['kind'] === 'NONE' ? 'NO_SERVER_GUARD' : 'NO_GRANT');
    $s7 = ($can && (string) $can['canonical_ar'] !== '' && $sid !== '') ? 1 : 0;

    $W("INSERT INTO repair01_w14_sidebar
        (screen_id,route,owner_code,s1_verdict,s1_rule,s2_label_live,s2_label_canon,s2_verdict,s2_rule,
         s3_group_live,s3_group_canon,s3_verdict,s3_rule,s4_order_src,s4_order_no,s4_cycle_step,
         s4_verdict,s4_rule,s5_parent,s5_verdict,s5_rule,s5_why,s6_visibility,s6_perm_rows,
         s6_guard_kind,s6_verdict,s6_rule,s7_linked,s7_verdict,s7_rule,measured_at)
        VALUES ('" . $esc($sid) . "','$rtE','" . $esc($reg['owner_code']) . "',
                '" . $esc($s1) . "','W14_S1_ACTIVE_BY_TARGET',
                '" . $esc($s2live) . "','" . $esc($s2can) . "','" . $esc($s2) . "','W14_S2_LABEL_FROM_REQUIREMENT',
                '" . $esc($s3live) . "','" . $esc($s3can) . "','" . $esc($s3) . "','W14_S3_GROUP_FROM_CYCLE',
                'nav_canonical.sort_no'," . $s4no . "," . (int) $step . ",
                '" . $esc($s4) . "','W14_S4_ORDER_FROM_CYCLE',
                '','" . $esc($s5) . "','W14_S5_PARENT_FROM_DECISION','موضعُ السطحِ من قرارِ الورقةِ لا من الذوق',
                '" . $esc((string) $reg['visibility_class']) . "'," . $permRows . ",
                '" . $esc($guard['kind']) . "','" . $esc($s6) . "','W14_S6_GUARD_AND_GRANT',
                " . $s7 . ",'" . ($s7 ? 'LINKED' : 'NOT_LINKED') . "','W14_S7_CANONICAL_SCREEN_ID',NOW())");
    $sbN++;
}
printf("  أسطحٌ مقيسةٌ بسبعِ خطوات %d · بلا صفٍّ في السجلّ %d\n\n", $sbN, $sbBad);

/* ═══════════════════════════════════════════════════════════════════════════
   ⑤ العتباتُ — بحالتِها الثلاثيّةِ ⛔ ولا رقمَ يُخترَع
   ═══════════════════════════════════════════════════════════════════════════
   ◆ `OWNER_APPROVED` **نصَّ عليها المالكُ حرفًا** في القرار ② — الأربعُ
     والعشرون ساعةً والثلاثةُ أيّامٍ والثلاثُ مرّاتٍ والثلاثون دقيقةً والثلاثُ
     ساعات — وكلُّها بمرجعِ قرارِها.
   ◆ `CONFIG_PENDING` **قيمتُها عدمٌ** بقرارِ المالكِ الأخير: «هذه لا أريد
     `Hardcode` لها الآن … ولا يجوز للمبرمجِ أن يخترع قيمتَها». وقيمةُ
     الاختبارِ في عمودٍ منفصلٍ **موسومةٍ ولا تنتقل**.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑤ العتبات ────────────────────────────────────────────────────\n";
$W("DELETE FROM repair01_w14_thresholds");
$SRC_D2 = 'OWNER_DECISIONS_20260827 · القرار الثاني';
$SRC_D7 = 'OWNER_DECISIONS_20260827 · القرار الأخير';
$TH = array(
    /* ── ما نصَّ عليه المالكُ حرفًا ─────────────────────────────────── */
    array('rsk.trigger.unplanned_downtime_hours', 24, null, 'OWNER_APPROVED', 'Risk Trigger Rules',
          'ساعة', 'توقف غير مخطط يفتح محفز خطر تشغيلي',
          'نص المالك: توقف غير مخطط يتجاوز 24 ساعة يفتح Operational Risk Trigger الزاميا', $SRC_D2),
    array('rsk.trigger.simple_issue_days', 3, null, 'OWNER_APPROVED', 'Risk Trigger Rules',
          'يوم', 'مشكلة بسيطة تمتد فوق الحد فتصير فشل ادارة توقف',
          'نص المالك: مشكلة بسيطة تمتد لاكثر من ثلاثة ايام تعتبر Critical Downtime Management Failure', $SRC_D2),
    array('rsk.trigger.recurrence_count', 3, null, 'OWNER_APPROVED', 'Risk Trigger Rules',
          'مرة', 'تكرار نفس المشكلة يفتح مراجعة تكرار',
          'نص المالك: تكرار نفس المشكلة اكثر من ثلاث مرات يفتح Recurrent Failure Review', $SRC_D2),
    array('mnt.target.small_issue_minutes', 30, null, 'OWNER_APPROVED', 'Operational Targets',
          'دقيقة', 'الحد المستهدف لعودة المعدة في المشكلات الصغيرة',
          'نص المالك: المشكلات الصغيرة الحد المستهدف 30 دقيقة من التوقف الى العودة الفعلية', $SRC_D2),
    array('mnt.target.major_issue_hours', 3, null, 'OWNER_APPROVED', 'Operational Targets',
          'ساعة', 'الحد المستهدف للمشكلات الكبيرة القابلة للاصلاح المباشر',
          'نص المالك: المشكلات الكبيرة القابلة للاصلاح او الاستبدال المباشر الحد المستهدف 3 ساعات', $SRC_D2),
    /* ── وما لم يُجب عنه المالكُ: قيمتُه عدمٌ ولا تُخترَع ──────────────── */
    array('rsk.appetite.limit_amount', null, 100, 'CONFIG_PENDING', 'Risk Appetite Registry',
          'مبلغ', 'حد شهية المخاطر الكمي',
          'DEC-OPEN-08 عتبة غير معتمدة عدديا - والمحرك يقرا من السجل ويرد بلا قيمة', 'DEC-OPEN-08'),
    array('rsk.appetite.tolerance_amount', null, 200, 'CONFIG_PENDING', 'Risk Appetite Registry',
          'مبلغ', 'حد التحمل فوق الشهية',
          'قيمة كمية لم تعتمد بعد - تصنف CONFIG_PENDING بنص المالك ولا تمنع البناء', $SRC_D7),
    array('gov.approval.direct_award_threshold', null, 500, 'CONFIG_PENDING', 'Procurement Policy',
          'مبلغ', 'حد الاسناد المباشر',
          'Direct Award Threshold من القيم التي اجلها المالك الى ما قبل التشغيل', $SRC_D7),
    array('gov.petty_cash_limit', null, 50, 'CONFIG_PENDING', 'Treasury Policy',
          'مبلغ', 'سقف المصروف النثري',
          'Petty Cash Limit من القيم المؤجلة بنص المالك', $SRC_D7),
    array('gov.aggregation_window_days', null, 90, 'CONFIG_PENDING', 'Procurement Policy',
          'يوم', 'نافذة تجميع الطلبات المتقاربة',
          'Aggregation Window من القيم المؤجلة بنص المالك', $SRC_D7),
    array('gov.reserved_matters_value', null, 1000, 'CONFIG_PENDING', 'AAM',
          'مبلغ', 'حد المسائل المحجوزة للمالك',
          'Reserved Matters values من القيم المؤجلة بنص المالك', $SRC_D7),
    array('gov.gift.disclosure_threshold', null, 75, 'CONFIG_PENDING', 'Policy Registry',
          'مبلغ', 'الحد الذي يوجب الافصاح عن الهدية او الضيافة',
          'نص المتطلب: الافصاح فوق الحد المضبوط - والحد لم يعتمد بعد فلا يخترع', 'GOV-12'),
    array('gov.exception.max_duration_days', null, 90, 'CONFIG_PENDING', 'Policy Registry',
          'يوم', 'اقصى مدة للاستثناء الواحد',
          'لا استثناء دائما - والمدة القصوى قيمة سياسة لم تعتمد بعد', 'GOV-21'),
    array('gov.break_glass.window_minutes', null, 60, 'CONFIG_PENDING', 'Policy Registry',
          'دقيقة', 'نافذة صلاحية الطوارئ اللحظية',
          'Break-Glass بمدة قصيرة - والمدة قيمة سياسة لم تعتمد بعد', 'GOV-17'),
    array('gov.dr.rto_minutes', null, 240, 'CONFIG_PENDING', 'Policy Registry',
          'دقيقة', 'زمن الاستعادة المستهدف',
          'تمرين الاستعادة يقيس RTO فعليا - والهدف قيمة لم تعتمد بعد', 'GOV-31'),
    array('gov.dr.rpo_minutes', null, 60, 'CONFIG_PENDING', 'Policy Registry',
          'دقيقة', 'اقصى فقد بيانات مقبول',
          'تمرين الاستعادة يقيس RPO فعليا - والهدف قيمة لم تعتمد بعد', 'GOV-31'),
    array('iaf.finding.escalation_days', null, 15, 'CONFIG_PENDING', 'Policy Registry',
          'يوم', 'مهلة تصعيد الملاحظة المتاخرة',
          'التاخر عن المهلة يصعد اليا بسلمه - والمهلة قيمة لم تعتمد بعد', 'IAF-15'),
    array('iaf.quality.external_review_years', null, 5, 'CONFIG_PENDING', 'Policy Registry',
          'سنة', 'دورية التقييم الخارجي المستقل لوظيفة المراجعة',
          'تقييم خارجي مستقل بدورية معلنة - والدورية لم تعتمد بعد', 'IAF-16'),
    array('gov.committee.quorum_ratio', null, 60, 'CONFIG_PENDING', 'Policy Registry',
          'نسبة', 'نصاب انعقاد اللجنة',
          'اللجان بتشكيلها ودورية انعقادها - والنصاب قيمة لم تعتمد بعد', 'GOV-30'),
);
$thN = 0; $thApproved = 0; $thPending = 0;
foreach ($TH as $t) {
    $val  = $t[1] === null ? 'NULL' : (string) $t[1];
    $test = $t[2] === null ? 'NULL' : (string) $t[2];
    if ($W("INSERT INTO repair01_w14_thresholds
            (threshold_key,value_num,test_value_num,status,registry,unit_ar,title_ar,why,decision_ref,src_ref)
            VALUES ('" . $esc($t[0]) . "'," . $val . "," . $test . ",'" . $esc($t[3]) . "',
                    '" . $esc($t[4]) . "','" . $esc($t[5]) . "','" . $esc($t[6]) . "',
                    '" . $esc($t[7]) . "','" . $esc($t[8]) . "','RPR-W14 §٥')")) {
        $thN++;
        if ($t[3] === 'OWNER_APPROVED') { $thApproved++; } else { $thPending++; }
    }
}
printf("  عتباتٌ مسجَّلة %d · معتمَدةٌ بنصِّ المالك %d · معلَّقةٌ بقيمةِ عدمٍ %d ⛔ ولا رقمَ مخترَع\n\n",
    $thN, $thApproved, $thPending);

/* ═══════════════════════════════════════════════════════════════════════════
   ⑥ آلاتُ الحالةِ — لكلِّ كيانٍ رئيسيٍّ في النطاق
   ═══════════════════════════════════════════════════════════════════════════
   لكلِّ صفٍّ: مالكُ الانتقالِ · شروطُه · مستندُه · بوّابةُ اعتمادِه · قاعدةُ
   إعادةِ الفتحِ · قاعدةُ التصحيح. **والممنوعُ صريحٌ بسببِه لا بغيابِه.**
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑥ آلاتُ الحالة ───────────────────────────────────────────────\n";
$W("DELETE FROM repair01_w14_states");
$ST = array();
$add = function ($e, $f, $t, $ok, $role, $pre, $doc, $gate, $reopen, $fix, $why) use (&$ST) {
    $ST[] = array($e, $f, $t, $ok ? 1 : 0, $role, $pre, $doc, $gate, $reopen, $fix, $why);
};

/* ① الانحرافُ التشغيليّ — حبّةُ الرحلة */
$add('ctl_deviation', 'registered', 'classified', 1, 'مالك الانحراف التشغيلي',
     'قاعدة تصنيف نافذة بشروطها الثلاثة وواقعة مصدر بمرجعها',
     'ورقة تصنيف الانحراف', 'قاعدة مكتوبة في سجل القواعد',
     'يعاد التصنيف بقاعدة لاحقة تحمل مرجع الاولى', 'التصحيح بتصنيف جديد بمرجعه لا بمحو السابق', '');
$add('ctl_deviation', 'registered', 'retained', 1, 'مالك الانحراف التشغيلي',
     'التصنيف انحراف فقط - لا تعرض ولا خرق', 'ورقة تصنيف الانحراف',
     'قاعدة مكتوبة', 'يعاد فتحه بواقعة تكرار', 'التصحيح بتصنيف جديد', '');
$add('ctl_deviation', 'classified', 'referred', 1, 'مالك الانحراف مع جهة الاحالة',
     'تصنيف غير انحراف فقط ومرجع مفتوح عند نطاقه', 'مرجع الاحالة',
     'الجهة المستقبلة تفتح من بابها', 'تعاد الاحالة بمرجع جديد', 'التصحيح باحالة تالية بمرجعها', '');
$add('ctl_deviation', 'referred', 'closed', 1, 'مالك الانحراف التشغيلي',
     'اغلاق المرجع عند نطاقه', 'دليل الاغلاق', 'اقفال المرجع عند نطاقه',
     'يعاد الفتح بتكرار الواقعة', 'التصحيح بواقعة جديدة', '');
$add('ctl_deviation', 'registered', 'referred', 0, '—', '—', '—', '—', '—', '—',
     'لا احالة قبل تصنيف - والاحالة بلا قاعدة مكتوبة اجتهاد لا حكم');
$add('ctl_deviation', 'retained', 'referred', 0, '—', '—', '—', '—', '—', '—',
     'الانحراف التشغيلي الصرف يبقى عند مالكه ولا تفتح له حالة حوكمة');
$add('ctl_classification_rule', 'draft', 'active', 1, 'الجهة المعتمدة للقاعدة',
     'الشروط الثلاثة مكتوبة ومن كتب القاعدة لا يعتمدها', 'وثيقة القاعدة',
     'اعتماد بيد غير يد الكاتب', 'يعاد فتحها باصدار تال', 'التصحيح باصدار جديد يحمل مرجع الاول', '');
$add('ctl_classification_rule', 'active', 'retired', 1, 'الجهة المعتمدة للقاعدة',
     'قاعدة خلف نافذة', 'قرار التقاعد', 'اعتماد', 'لا يعاد فتح متقاعدة', 'الخلف يحمل مرجع السلف', '');
$add('ctl_classification_rule', 'draft', 'retired', 0, '—', '—', '—', '—', '—', '—',
     'قاعدة لم تنفذ لا تتقاعد - والمسودة تلغى لا تتقاعد');

/* ② سلسلةُ المخاطر */
$add('rsk_trigger', 'raised', 'triaged', 1, 'إدارة المخاطر',
     'مرجع مصدر قائم وعتبة معتمدة من السجل', 'ورقة الفرز', 'فرز المخاطر',
     'يعاد فتحه بواقعة جديدة', 'التصحيح بمحفز تال بمرجعه', '');
$add('rsk_trigger', 'triaged', 'converted', 1, 'إدارة المخاطر',
     'سجل خطر مفتوح برمزه', 'بطاقة الخطر', 'فتح التعرض',
     'يعاد بمحفز جديد', 'التصحيح بربط الخطر الصحيح', '');
$add('rsk_trigger', 'triaged', 'dismissed', 1, 'إدارة المخاطر',
     'سبب استبعاد مكتوب', 'ورقة الاستبعاد', 'فرز المخاطر',
     'يعاد فتحه بتكرار', 'التصحيح بمحفز جديد', '');
$add('rsk_trigger', 'raised', 'converted', 0, '—', '—', '—', '—', '—', '—',
     'لا تحويل قبل فرز - والتحويل الالي يملا سجل المخاطر بصيانة مخططة');
$add('rsk_event', 'recorded', 'assessed', 1, 'إدارة المخاطر',
     'مرجع مصدر قائم', 'ورقة التقييم', 'تقييم المخاطر', 'يعاد بتقييم تال', 'التصحيح بتقييم جديد', '');
$add('rsk_event', 'assessed', 'linked', 1, 'إدارة المخاطر',
     'خطر قائم برمزه', 'بطاقة الخطر', 'ربط الحدث بخطره', 'يعاد بربط جديد', 'التصحيح بربط بديل', '');
$add('rsk_event', 'linked', 'closed', 1, 'إدارة المخاطر',
     'اغلاق الخطر المرتبط', 'دليل الاغلاق', 'اعتماد الاغلاق', 'يعاد بواقعة جديدة', 'التصحيح بحدث جديد', '');
$add('rsk_event', 'recorded', 'closed', 0, '—', '—', '—', '—', '—', '—',
     'لا اغلاق حدث قبل تقييمه وربطه - والاغلاق المباشر يفقد الاثر');
$add('rsk_closure', 'proposed', 'evidenced', 1, 'إدارة المخاطر',
     'دليل اغلاق مرفق', 'دليل الاغلاق', 'مراجعة الدليل', 'يعاد باقتراح جديد', 'التصحيح بدليل بديل', '');
$add('rsk_closure', 'evidenced', 'approved', 1, 'سلطة اعتماد الاغلاق',
     'من اقترح لا يعتمد ودليل قائم', 'محضر الاعتماد', 'اعتماد بيد ثانية',
     'يعاد الفتح بواقعة تكرار', 'التصحيح باغلاق تال بمرجعه', '');
$add('rsk_closure', 'approved', 'closed', 1, 'إدارة المخاطر',
     'اعتماد قائم', 'سجل الاغلاق', 'اعتماد', 'يعاد الفتح بتكرار', 'التصحيح باغلاق جديد', '');
$add('rsk_closure', 'closed', 'reopened', 1, 'إدارة المخاطر',
     'واقعة تكرار او تغير جوهري', 'ورقة اعادة الفتح', 'فرز المخاطر',
     'اعادة الفتح واقعة بسببها', 'التصحيح بواقعة تالية', '');
$add('rsk_closure', 'proposed', 'approved', 0, '—', '—', '—', '—', '—', '—',
     'لا اعتماد اغلاق بلا دليل - والاغلاق بكتابة نية اغلاق بلا اثبات');
$add('rsk_taxonomy', 'draft', 'active', 1, 'إدارة المخاطر',
     'عائلة من الاربع وعقدة اب ان كانت فرعا', 'شجرة التصنيف', 'اعتماد التصنيف',
     'يعاد بتعديل الشجرة', 'التصحيح بعقدة بديلة', '');
$add('rsk_taxonomy', 'active', 'retired', 1, 'إدارة المخاطر',
     'عقدة خلف قائمة', 'قرار التقاعد', 'اعتماد', 'لا يعاد فتح متقاعدة', 'الخلف يحمل مرجع السلف', '');

/* ③ سلسلةُ الحوكمة */
$add('gov_policy', 'draft', 'reviewed', 1, 'مسؤول السياسات', 'مسودة مكتملة',
     'مسودة السياسة', 'مراجعة الحوكمة', 'يعاد باصدار تال', 'التصحيح باصدار جديد', '');
$add('gov_policy', 'reviewed', 'approved', 1, 'سلطة اعتماد السياسة',
     'من كتب لا يعتمد', 'محضر الاعتماد', 'اعتماد بيد ثانية',
     'يعاد باصدار تال', 'التصحيح باصدار جديد يحمل مرجع الاول', '');
$add('gov_policy', 'approved', 'effective', 1, 'إدارة الحوكمة والالتزام',
     'وثيقة وتاريخ نفاذ', 'وثيقة السياسة النافذة', 'اعتماد',
     'يعاد باصدار تال', 'الخلف يحمل مرجع السلف', '');
$add('gov_policy', 'effective', 'superseded', 1, 'إدارة الحوكمة والالتزام',
     'اصدار خلف نافذ', 'وثيقة الاصدار الجديد', 'اعتماد الخلف',
     'لا يعاد نفاذ اصدار خلفه اخر', 'التصحيح باصدار ثالث', '');
$add('gov_policy', 'draft', 'effective', 0, '—', '—', '—', '—', '—', '—',
     'لا نفاذ بلا مراجعة واعتماد - ونفاذ المسودة يجعل السياسة قرارا بيد كاتبها');
$add('gov_obligation', 'registered', 'monitored', 1, 'إدارة الحوكمة والالتزام',
     'جهة ودورية ومالك', 'سند الالتزام', 'اعتماد التسجيل',
     'يعاد بتغير السند', 'التصحيح بتحديث السند', '');
$add('gov_obligation', 'monitored', 'met', 1, 'الإدارة المالكة للالتزام',
     'تقديم مستلم بايصاله', 'ايصال الجهة', 'استلام الجهة',
     'يعاد بالدورة التالية', 'التصحيح بتقديم تال', '');
$add('gov_obligation', 'monitored', 'breached', 1, 'إدارة الحوكمة والالتزام',
     'انقضاء الموعد بلا تقديم', 'محضر الاخلال', 'فتح حالة حوكمة',
     'يعاد بالتقديم المتاخر', 'التصحيح بتقديم متاخر بمرجعه', '');
$add('gov_obligation', 'registered', 'met', 0, '—', '—', '—', '—', '—', '—',
     'لا استيفاء قبل مراقبة - والاستيفاء المباشر يخفي دورة الالتزام');
$add('gov_compliance_due', 'due', 'met', 1, 'الإدارة المالكة',
     'مرجع تنفيذ قائم', 'ايصال التنفيذ', 'استلام الجهة', 'يعاد بالدورة التالية', 'التصحيح بتنفيذ تال', '');
$add('gov_compliance_due', 'due', 'late', 1, 'إدارة الحوكمة والالتزام',
     'انقضاء الموعد', 'محضر التاخر', 'تصعيد', 'يعاد بالتنفيذ', 'التصحيح بتنفيذ متاخر بمرجعه', '');
$add('gov_compliance_due', 'due', 'waived', 1, 'إدارة الحوكمة والالتزام',
     'استثناء بمدته ومعتمد', 'وثيقة الاستثناء', 'اعتماد الاستثناء',
     'ينتهي الاستثناء باجله', 'التصحيح باستثناء تال', '');
$add('gov_filing', 'due', 'prepared', 1, 'الإدارة المالكة', 'محتوى جاهز',
     'مسودة التقديم', 'مراجعة الحوكمة', 'يعاد بالدورة التالية', 'التصحيح بمسودة بديلة', '');
$add('gov_filing', 'prepared', 'submitted', 1, 'الإدارة المالكة', 'تقديم فعلي بتاريخه',
     'نسخة التقديم', 'اعتماد التقديم', 'يعاد بتقديم معدل', 'التصحيح بتقديم تال بمرجعه', '');
$add('gov_filing', 'submitted', 'acknowledged', 1, 'إدارة الحوكمة والالتزام',
     'ايصال الجهة', 'ايصال الجهة', 'استلام الجهة', 'يعاد بمراجعة الجهة', 'التصحيح بايصال بديل', '');
$add('gov_filing', 'due', 'acknowledged', 0, '—', '—', '—', '—', '—', '—',
     'لا استلام بلا تقديم - والاستلام بلا ايصال دعوى لا اثبات');
$add('gov_conflict_disclosure', 'disclosed', 'assessed', 1, 'إدارة الحوكمة والالتزام',
     'صاحب الافصاح لا يقيمه', 'ورقة التقييم', 'تقييم الحوكمة',
     'يعاد بافصاح تال', 'التصحيح بافصاح جديد', '');
$add('gov_conflict_disclosure', 'assessed', 'recused', 1, 'إدارة الحوكمة والالتزام',
     'نطاق التجنيب مكتوب', 'قرار التجنيب', 'قرار الحوكمة',
     'ينتهي التجنيب بزوال سببه', 'التصحيح بقرار تال', '');
$add('gov_conflict_disclosure', 'assessed', 'mitigated', 1, 'إدارة الحوكمة والالتزام',
     'ضوابط مكتوبة', 'قرار الضوابط', 'قرار الحوكمة', 'يعاد بتغير الظرف', 'التصحيح بقرار تال', '');
$add('gov_conflict_disclosure', 'disclosed', 'closed', 0, '—', '—', '—', '—', '—', '—',
     'لا اغلاق افصاح بلا تقييم - والاغلاق الصامت يجعل الافصاح ورقة تحفظ');
$add('gov_related_party', 'declared', 'verified', 1, 'إدارة الحوكمة والالتزام',
     'افصاح قائم ووسم بين الكيانات مكتمل ان كان', 'ورقة التحقق', 'تحقق الحوكمة',
     'يعاد بتغير العلاقة', 'التصحيح بتحديث الوسم', '');
$add('gov_related_party', 'verified', 'active', 1, 'إدارة الحوكمة والالتزام',
     'اعتماد التعامل', 'قرار الاعتماد', 'اعتماد', 'يعاد بتعامل جديد', 'التصحيح بقرار تال', '');
$add('gov_related_party', 'active', 'ended', 1, 'إدارة الحوكمة والالتزام',
     'انتهاء العلاقة بتاريخها', 'محضر الانتهاء', 'اعتماد', 'يعاد بعلاقة جديدة', 'التصحيح بسجل جديد', '');
$add('gov_related_party', 'declared', 'active', 0, '—', '—', '—', '—', '—', '—',
     'لا تعامل نافذ بلا تحقق - والنفاذ المباشر يتخطى الافصاح الالزامي');
$add('gov_gift_disclosure', 'disclosed', 'assessed', 1, 'إدارة الحوكمة والالتزام',
     'الحد مقروء من السجل ومفصح غير مقرر', 'ورقة التقييم', 'تقييم الحوكمة',
     'يعاد بافصاح تال', 'التصحيح بافصاح جديد', '');
$add('gov_gift_disclosure', 'assessed', 'accepted', 1, 'إدارة الحوكمة والالتزام',
     'ضمن السياسة', 'قرار القبول', 'قرار الحوكمة', 'يعاد بمراجعة السياسة', 'التصحيح بقرار تال', '');
$add('gov_gift_disclosure', 'assessed', 'returned', 1, 'إدارة الحوكمة والالتزام',
     'خارج السياسة', 'محضر الرد', 'قرار الحوكمة', 'يعاد بمراجعة', 'التصحيح بقرار تال', '');
$add('gov_conduct_ack', 'due', 'acknowledged', 1, 'الموظف',
     'دليل الاقرار مرفق', 'الاقرار الموقع', 'استلام الموارد البشرية',
     'يعاد عند كل اصدار جديد', 'التصحيح باقرار تال', '');
$add('gov_conduct_ack', 'due', 'overdue', 1, 'إدارة الحوكمة والالتزام',
     'انقضاء الموعد', 'كشف الناقص', 'تصعيد', 'يعاد بالاقرار', 'التصحيح باقرار متاخر', '');
$add('gov_conduct_ack', 'due', 'acknowledged_without_evidence', 0, '—', '—', '—', '—', '—', '—',
     'لا اقرار بلا دليل - وخانة اختيار بلا مستند لا تثبت اقرارا');
$add('gov_sod_conflict', 'defined', 'detected', 1, 'إدارة الحوكمة والالتزام',
     'طرفان متمايزان وفاعل يجمعهما', 'كشف التعارض', 'كشف دوري',
     'يعاد بكل كشف', 'التصحيح بنزع احد الطرفين', '');
$add('gov_sod_conflict', 'detected', 'mitigated', 1, 'إدارة الحوكمة والالتزام',
     'معالجة مكتوبة', 'ورقة المعالجة', 'قرار الحوكمة', 'يعاد بكشف تال', 'التصحيح بمعالجة بديلة', '');
$add('gov_sod_conflict', 'detected', 'accepted', 1, 'سلطة اعتماد الاستثناء',
     'استثناء بمدته ومعتمد', 'وثيقة الاستثناء', 'اعتماد الاستثناء',
     'ينتهي بالاجل', 'التصحيح باستثناء تال', '');
$add('gov_sod_conflict', 'detected', 'closed', 0, '—', '—', '—', '—', '—', '—',
     'لا اغلاق تعارض بلا معالجة او استثناء معتمد');
$add('gov_integrity_report', 'received', 'triaged', 1, 'إدارة الحوكمة والالتزام',
     'رمز مبلغ قائم وهوية محجوبة', 'محضر الفرز', 'فرز الحوكمة',
     'يعاد ببلاغ تال', 'التصحيح بفرز ثان بمرجعه', '');
$add('gov_integrity_report', 'triaged', 'referred', 1, 'إدارة الحوكمة والالتزام',
     'جهة احالة معلنة', 'مرجع الاحالة', 'قرار الفرز', 'يعاد باحالة جديدة', 'التصحيح باحالة تالية', '');
$add('gov_integrity_report', 'received', 'referred', 0, '—', '—', '—', '—', '—', '—',
     'لا احالة قبل فرز - والاحالة الالية تحول البلاغ اتهاما بلا مراجعة');
$add('gov_investigation', 'mandated', 'evidence', 1, 'مالك التحقيق بحسب نوعه',
     'تكليف ونطاق ومحقق غير موضوع التحقيق', 'وثيقة التكليف', 'تكليف مكتوب',
     'يعاد بتكليف تال', 'التصحيح بتكليف جديد بمرجعه', '');
$add('gov_investigation', 'evidence', 'concluded', 1, 'مالك التحقيق بحسب نوعه',
     'من فتح لا يحسم ونتيجة مكتوبة', 'تقرير التحقيق', 'اعتماد بيد ثانية',
     'يعاد بتحقيق تال', 'التصحيح بتقرير تكميلي بمرجعه', '');
$add('gov_investigation', 'concluded', 'referred', 1, 'مالك التحقيق بحسب نوعه',
     'جهة اثر النتيجة معلنة', 'مرجع الاحالة', 'قرار الاحالة',
     'الجهة تستقبل الاثر ولا تعيد التحقيق', 'التصحيح باحالة تالية', '');
$add('gov_investigation', 'referred', 'closed', 1, 'مالك التحقيق بحسب نوعه',
     'استلام جهة الاثر', 'محضر الاغلاق', 'اعتماد', 'يعاد بواقعة جديدة', 'التصحيح بتحقيق جديد', '');
$add('gov_investigation', 'mandated', 'concluded', 0, '—', '—', '—', '—', '—', '—',
     'لا نتيجة بلا ادلة - والنتيجة بلا مرحلة ادلة رأي لا تحقيق');
$add('gov_breach', 'opened', 'investigated', 1, 'إدارة الحوكمة والالتزام',
     'اساس من الثمانية وضابط مكسور', 'ملف الحالة', 'فتح الحالة',
     'يعاد الفتح بتكرار', 'التصحيح بحالة تالية بمرجعها', '');
$add('gov_breach', 'investigated', 'action_assigned', 1, 'إدارة الحوكمة والالتزام',
     'اجراء بمالك ومهلة', 'ورقة الاجراء', 'اسناد الاجراء',
     'يعاد باجراء تال', 'التصحيح باجراء بديل', '');
$add('gov_breach', 'action_assigned', 'remediated', 1, 'الإدارة المالكة للاجراء',
     'دليل تنفيذ', 'دليل التنفيذ', 'تحقق بيد ثانية', 'يعاد بتحقق سالب', 'التصحيح باجراء تكميلي', '');
$add('gov_breach', 'remediated', 'closed', 1, 'إدارة الحوكمة والالتزام',
     'من فتح لا يغلق ودليل اغلاق قائم', 'محضر الاغلاق', 'اعتماد بيد ثانية',
     'يعاد الفتح بتكرار الاخلال', 'التصحيح بحالة تالية تحمل مرجع الاولى', '');
$add('gov_breach', 'closed', 'reopened', 1, 'إدارة الحوكمة والالتزام',
     'تكرار الاخلال او ظهور دليل جديد', 'ورقة اعادة الفتح', 'قرار الحوكمة',
     'اعادة الفتح واقعة بسببها', 'التصحيح بحالة جديدة', '');
$add('gov_breach', 'opened', 'closed', 0, '—', '—', '—', '—', '—', '—',
     'لا اغلاق حالة بلا اجراء ودليل - والاغلاق المباشر يجعل الحالة سجل شكوى');
$add('gov_corrective_action', 'assigned', 'in_progress', 1, 'الإدارة المالكة للاجراء',
     'مالك ومهلة', 'ورقة الاجراء', 'اسناد', 'يعاد باجراء تال', 'التصحيح باجراء بديل', '');
$add('gov_corrective_action', 'in_progress', 'evidence_submitted', 1, 'الإدارة المالكة للاجراء',
     'دليل مرفق', 'دليل التنفيذ', 'تقديم الدليل', 'يعاد بدليل تال', 'التصحيح بدليل بديل', '');
$add('gov_corrective_action', 'evidence_submitted', 'verified', 1, 'إدارة الحوكمة والالتزام',
     'مالك الاجراء لا يتحقق منه', 'محضر التحقق', 'تحقق بيد ثانية',
     'يعاد بتحقق سالب', 'التصحيح باجراء تكميلي', '');
$add('gov_corrective_action', 'verified', 'closed', 1, 'إدارة الحوكمة والالتزام',
     'تحقق قائم', 'محضر الاغلاق', 'اعتماد', 'يعاد بتكرار السبب', 'التصحيح باجراء جديد', '');
$add('gov_corrective_action', 'assigned', 'overdue', 1, 'إدارة الحوكمة والالتزام',
     'انقضاء المهلة', 'كشف المتاخر', 'تصعيد', 'يعاد بالتنفيذ', 'التصحيح بتمديد معتمد', '');
$add('gov_corrective_action', 'assigned', 'closed', 0, '—', '—', '—', '—', '—', '—',
     'لا اغلاق اجراء بلا دليل وتحقق - والاغلاق بادعاء المالك يجعل التحقق شكلا');
$add('gov_audit_followup', 'tracking', 'overdue', 1, 'إدارة الحوكمة والالتزام',
     'انقضاء مهلة خطة الادارة', 'كشف المتاخر', 'تصعيد', 'يعاد بتنفيذ الخطة', 'التصحيح بخطة معدلة', '');
$add('gov_audit_followup', 'overdue', 'escalated', 1, 'إدارة الحوكمة والالتزام',
     'تكرار التاخر', 'محضر التصعيد', 'تصعيد', 'يعاد بالتنفيذ', 'التصحيح بخطة بديلة', '');
$add('gov_audit_followup', 'tracking', 'plan_done', 1, 'الإدارة المالكة للخطة',
     'تنفيذ الخطة بدليلها', 'دليل التنفيذ', 'استلام الحوكمة',
     'يعاد بتحقق سالب من المراجعة', 'التصحيح بخطة تكميلية', '');
$add('gov_audit_followup', 'tracking', 'finding_closed', 0, '—', '—', '—', '—', '—', '—',
     'الحوكمة لا تغلق ملاحظة المراجعة - الاغلاق بتحقق المراجعة وحدها');
$add('gov_committee', 'formed', 'active', 1, 'إدارة الحوكمة والالتزام',
     'ميثاق ورئيس واعضاء', 'ميثاق اللجنة', 'اعتماد التشكيل',
     'يعاد بتشكيل جديد', 'التصحيح بتعديل التشكيل بقراره', '');
$add('gov_committee', 'active', 'suspended', 1, 'سلطة تشكيل اللجنة',
     'قرار تعليق', 'قرار التعليق', 'اعتماد', 'يعاد بقرار', 'التصحيح بقرار تال', '');
$add('gov_committee', 'active', 'dissolved', 1, 'سلطة تشكيل اللجنة',
     'قرار حل', 'قرار الحل', 'اعتماد', 'لا يعاد فتح منحلة', 'التصحيح بلجنة جديدة', '');
$add('gov_committee', 'formed', 'active_without_charter', 0, '—', '—', '—', '—', '—', '—',
     'لا لجنة نافذة بلا ميثاق ورئيس واعضاء - والنفاذ بلا ميثاق سلطة بلا حدود');
$add('gov_request_type', 'draft', 'approved', 1, 'إدارة الحوكمة والالتزام',
     'تعريف من مجاله وسلطة اعتماد وقاعدة توجيه', 'وثيقة النوع', 'اعتماد الحوكمة',
     'يعاد باصدار تال', 'التصحيح باصدار جديد', '');
$add('gov_request_type', 'approved', 'active', 1, 'إدارة الحوكمة والالتزام',
     'سياسة صلاحية معلنة', 'وثيقة النوع النافذ', 'اعتماد',
     'يعاد باصدار تال', 'الخلف يحمل مرجع السلف', '');
$add('gov_request_type', 'active', 'superseded', 1, 'إدارة الحوكمة والالتزام',
     'اصدار خلف نافذ', 'وثيقة الاصدار الجديد', 'اعتماد الخلف', 'لا يعاد', 'التصحيح باصدار ثالث', '');
$add('gov_request_type', 'active', 'retired', 1, 'إدارة الحوكمة والالتزام',
     'قرار تقاعد بتاريخه', 'قرار التقاعد', 'اعتماد', 'لا يعاد فتح متقاعد', 'التصحيح بنوع جديد', '');
$add('gov_request_type', 'draft', 'active', 0, '—', '—', '—', '—', '—', '—',
     'لا نفاذ نوع طلب بلا اعتماد الحوكمة وسلطة اعتماد وقاعدة توجيه');

/* ④ سلسلةُ المراجعةِ الداخلية */
$add('iaf_program', 'drafted', 'approved', 1, 'رئيس فريق المراجعة',
     'هدف وعينة بمنهجية ومن نفذ لا يراجع ونطاق من المراجعة', 'برنامج المهمة',
     'مراجعة رئيس الفريق', 'يعاد ببرنامج تال', 'التصحيح بخطوة بديلة بمرجعها', '');
$add('iaf_program', 'approved', 'executing', 1, 'منفذ الخطوة',
     'برنامج معتمد', 'ورقة العمل', 'اعتماد البرنامج', 'يعاد بتنفيذ تال', 'التصحيح باعادة التنفيذ', '');
$add('iaf_program', 'executing', 'completed', 1, 'رئيس فريق المراجعة',
     'كل مفردات العينة مختبرة', 'ورقة الاستنتاج', 'مراجعة رئيس الفريق',
     'يعاد بمهمة تالية', 'التصحيح بخطوة تكميلية', '');
$add('iaf_program', 'drafted', 'completed', 0, '—', '—', '—', '—', '—', '—',
     'لا انجاز خطوة بلا اعتماد وتنفيذ - والانجاز المباشر يفرغ البرنامج من الاختبار');
$add('iaf_evidence_request', 'requested', 'provided', 1, 'الجهة الخاضعة للمراجعة',
     'دليل بمرجعه', 'الدليل المقدم', 'تسليم الدليل', 'يعاد بطلب تال', 'التصحيح بدليل بديل', '');
$add('iaf_evidence_request', 'requested', 'overdue', 1, 'المراجعة الداخلية',
     'انقضاء المهلة', 'كشف التاخر', 'تصعيد', 'يعاد بالتزويد', 'التصحيح بتزويد متاخر', '');
$add('iaf_evidence_request', 'overdue', 'escalated', 1, 'المراجعة الداخلية',
     'تجاوز عتبة التصعيد من السجل', 'محضر التصعيد', 'تصعيد بسلمه',
     'يعاد بالتزويد', 'التصحيح بتزويد متاخر بمرجعه', '');
$add('iaf_evidence_request', 'provided', 'closed', 1, 'المراجعة الداخلية',
     'دليل مقبول', 'ورقة القبول', 'قبول المراجعة', 'يعاد بطلب تكميلي', 'التصحيح بطلب جديد', '');
$add('iaf_evidence_request', 'requested', 'closed', 0, '—', '—', '—', '—', '—', '—',
     'لا اغلاق طلب دليل بلا تزويد - والاغلاق بلا دليل يفرغ الاختبار');
$add('iaf_sample', 'drawn', 'tested', 1, 'منفذ الاختبار',
     'مرجع مفردة قائم', 'ورقة نتيجة المفردة', 'تنفيذ الاختبار',
     'يعاد باختبار تال', 'التصحيح باعادة اختبار المفردة بمرجعها', '');
$add('iaf_sample', 'tested', 'concluded', 1, 'رئيس فريق المراجعة',
     'نتيجة لكل مفردة', 'ورقة الاستنتاج', 'مراجعة رئيس الفريق',
     'يعاد بعينة تالية', 'التصحيح بعينة تكميلية', '');
$add('iaf_sample', 'drawn', 'concluded', 0, '—', '—', '—', '—', '—', '—',
     'لا استنتاج قبل اختبار كل مفردة - والاستنتاج من عينة غير مختبرة رأي لا دليل');
$add('iaf_function_risk', 'identified', 'assessed', 1, 'رئيس المراجعة الداخلية',
     'نوع من الستة ومستوى', 'ورقة التقييم', 'تقييم المراجعة',
     'يعاد بتقييم دوري', 'التصحيح بتقييم تال', '');
$add('iaf_function_risk', 'assessed', 'treated', 1, 'رئيس المراجعة الداخلية',
     'معالجة مكتوبة', 'خطة المعالجة', 'اعتماد خط الرفع',
     'يعاد بتقييم تال', 'التصحيح بمعالجة بديلة', '');
$add('iaf_function_risk', 'treated', 'closed', 1, 'خط الرفع بالميثاق',
     'اثبات المعالجة', 'محضر الاغلاق', 'اعتماد خط الرفع',
     'يعاد بواقعة جديدة', 'التصحيح بخطر جديد', '');
$add('iaf_function_risk', 'identified', 'closed', 0, '—', '—', '—', '—', '—', '—',
     'لا اغلاق خطر وظيفة بلا تقييم ومعالجة');

$stN = 0; $stEnt = array();
foreach ($ST as $x) {
    $stEnt[$x[0]] = true;
    if ($W("INSERT INTO repair01_w14_states
            (entity,from_state,to_state,allowed,owner_role,preconditions,output_doc,approval_gate,
             reopen_rule,correct_rule,forbid_why,src_ref)
            VALUES ('" . $esc($x[0]) . "','" . $esc($x[1]) . "','" . $esc($x[2]) . "'," . (int) $x[3] . ",
                    '" . $esc($x[4]) . "','" . $esc($x[5]) . "','" . $esc($x[6]) . "','" . $esc($x[7]) . "',
                    '" . $esc($x[8]) . "','" . $esc($x[9]) . "','" . $esc($x[10]) . "','RPR-W14 §٦')
            ON DUPLICATE KEY UPDATE allowed=VALUES(allowed), owner_role=VALUES(owner_role)")) { $stN++; }
}
$stDeclared = repair01_w14_state_entities();
$stMissing = array_values(array_diff($stDeclared, array_keys($stEnt)));
printf("  انتقالاتٌ مسجَّلة %d · كياناتٌ لها آلةُ حالة %d من %d%s\n\n",
    $stN, count($stEnt), count($stDeclared),
    $stMissing ? ' ⇐ بلا آلةٍ: ' . implode('، ', $stMissing) : '');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑦ فصلُ الواجبات — ستّةُ أدوارٍ والتركيبةُ الممنوعةُ صريحة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑦ فصلُ الواجبات ─────────────────────────────────────────────\n";
$W("DELETE FROM repair01_w14_sod");
$SOD = array(
    array('ctl.deviation.classify', 'تصنيف الانحراف التشغيلي', 'SOURCE',
          'مالك الانحراف', 'مسؤول الجودة في إدارته', 'مالك الانحراف', 'النظام', 'مالك الانحراف',
          'لا يصنف انحراف بلا قاعدة نافذة ولا يصنفه من لم يسجل هويته',
          'DeviationClassifier::classify', 'AAM-CTL-01', 'نائب مدير الإدارة',
          'إدارة الواقعة التشغيلية وحدها', 'تفويض بالمنصب لا بالشخص'),
    array('ctl.deviation.refer', 'إحالة الانحراف إلى نطاق رقابة', 'SOURCE',
          'مالك الانحراف', 'إدارة المخاطر أو الحوكمة', 'الجهة المستقبلة', 'النظام', 'الجهة المستقبلة',
          'لا يحيل المصنف نفسه إلى الوجهتين معا بيد واحدة',
          'DeviationClassifier::markReferred', 'AAM-CTL-02', 'نائب مدير الإدارة',
          'الانحراف المصنف وحده', 'لا تفويض في الإحالة المزدوجة'),
    array('gov.policy.issue', 'إصدار سياسة ونفاذها', 'DEP-08',
          'مسؤول السياسات', 'مدير الحوكمة والالتزام', 'سلطة اعتماد السياسة', 'النظام', 'إدارة الحوكمة',
          'من كتب السياسة لا يعتمدها',
          'GovernanceDomainService::effectPolicy', 'AAM-GOV-01', 'نائب مدير الحوكمة',
          'السياسات في مجالها المعلن', 'تفويض بالمنصب وبمدة'),
    array('gov.exception.grant', 'منح استثناء من قاعدة منع', 'DEP-08',
          'طالب الاستثناء', 'مسؤول الامتثال', 'سلطة اعتماد الاستثناء بخطورته', 'النظام', 'إدارة الحوكمة',
          'لا يعتمد الطالب استثناءه ولا استثناء بلا مدة',
          'Governance/exceptions.php', 'AAM-GOV-02', 'نائب مدير الحوكمة',
          'الاستثناء بحسب خطورته', 'تفويض بمدة ولا يمدد ذاتيا'),
    array('gov.breach.close', 'إغلاق حالة الحوكمة', 'DEP-08',
          'مسؤول الامتثال', 'مدير الحوكمة والالتزام', 'مدير الحوكمة والالتزام',
          'الإدارة المالكة للإجراء', 'مدير الحوكمة والالتزام',
          'من فتح الحالة لا يغلقها ولا إغلاق بلا إجراء ودليل',
          'GovernanceDomainService::closeBreach', 'AAM-GOV-03', 'نائب مدير الحوكمة',
          'حالات الحوكمة وحدها', 'تفويض بالمنصب'),
    array('gov.investigation.conduct', 'إجراء تحقيق نزاهة', 'DEP-08',
          'من فتح التحقيق', 'المحقق', 'سلطة اعتماد النتيجة', 'المحقق', 'مالك التحقيق',
          'من فتح لا يحسم ولا يحقق أحد في قضية هو طرف فيها ولا محقق هو موضوع التحقيق',
          'GovernanceDomainService::concludeInvestigation', 'AAM-GOV-04', 'السلطة المحجوزة عند التعارض',
          'قضايا النزاهة والامتثال', 'تنح عند التعارض وإحالة لسلطة محجوزة'),
    array('gov.action.verify', 'التحقق من إجراء تصحيحي', 'DEP-08',
          'إدارة الحوكمة', 'مالك الإجراء', 'إدارة الحوكمة', 'مالك الإجراء', 'إدارة الحوكمة',
          'مالك الإجراء لا يتحقق من إجرائه',
          'GovernanceDomainService::verifyAction', 'AAM-GOV-05', 'نائب مدير الحوكمة',
          'الإجراءات التصحيحية', 'تفويض بالمنصب'),
    array('gov.conflict.decide', 'البت في إفصاح تضارب', 'DEP-08',
          'صاحب الإفصاح', 'مسؤول الامتثال', 'مدير الحوكمة والالتزام', 'النظام', 'إدارة الحوكمة',
          'صاحب الإفصاح لا يقرر فيه ولا يشارك في قرار محل التضارب',
          'GovernanceDomainService::decideConflict', 'AAM-GOV-06', 'نائب مدير الحوكمة',
          'الإفصاحات وحدها', 'لا تفويض لصاحب المصلحة'),
    array('gov.gift.decide', 'البت في إفصاح هدية', 'DEP-08',
          'المفصح', 'مسؤول الامتثال', 'مدير الحوكمة والالتزام', 'النظام', 'إدارة الحوكمة',
          'المفصح لا يقرر في إفصاحه',
          'GovernanceDomainService::discloseGift', 'AAM-GOV-07', 'نائب مدير الحوكمة',
          'الهدايا والضيافة', 'لا تفويض لصاحب المصلحة'),
    array('gov.request_type.register', 'تسجيل نوع طلب في السجل المركزي', 'DEP-08',
          'المجال المالك للتعريف', 'إدارة الحوكمة', 'إدارة الحوكمة', 'النظام', 'إدارة الحوكمة',
          'الحوكمة لا تملك تعريف طلب إدارة ولا توجيهه اليومي',
          'GovernanceDomainService::registerRequestType', 'AAM-GOV-08', 'نائب مدير الحوكمة',
          'حوكمة السجل لا محتواه', 'تفويض بالمنصب'),
    array('rsk.trigger.raise', 'فتح محفز خطر من واقعة تشغيلية', 'DEP-09',
          'النظام بقاعدته', 'إدارة المخاطر', 'إدارة المخاطر', 'النظام', 'إدارة المخاطر',
          'لا محفز أربع وعشرين ساعة على صيانة مخططة أو استعداد عميل',
          'RiskDomainService::raiseTrigger', 'AAM-RSK-01', 'نائب مدير المخاطر',
          'الوقائع التشغيلية بمرجعها', 'تفويض بالمنصب'),
    array('rsk.risk.accept', 'قبول خطر ضمن الشهية', 'DEP-09',
          'مالك الخطر', 'إدارة المخاطر', 'سلطة القبول بمستوى الخطر', 'النظام', 'إدارة المخاطر',
          'لا يقبل مالك الخطر خطره فوق الشهية ولا قبول بشهية غير معتمدة',
          'RiskDomainService::acceptRisk', 'AAM-RSK-02', 'لجنة المخاطر',
          'المخاطر في نطاق مالكها', 'تفويض بسقف'),
    array('rsk.closure.approve', 'اعتماد إغلاق خطر', 'DEP-09',
          'مالك الخطر', 'إدارة المخاطر', 'سلطة اعتماد الإغلاق', 'النظام', 'إدارة المخاطر',
          'من اقترح الإغلاق لا يعتمده ولا إغلاق بلا دليل',
          'RiskDomainService::approveClosure', 'AAM-RSK-03', 'لجنة المخاطر',
          'المخاطر المعالجة', 'تفويض بالمنصب'),
    array('rsk.escalate', 'تصعيد خطر', 'DEP-09',
          'إدارة المخاطر', 'مدير المخاطر', 'الجهة المصعد إليها', 'النظام', 'إدارة المخاطر',
          'لا تصعيد بلا سبب مكتوب',
          'RiskDomainService::escalate', 'AAM-RSK-04', 'نائب مدير المخاطر',
          'المخاطر الحرجة والخارجة عن الشهية', 'تفويض بالمنصب'),
    array('iaf.engagement.plan', 'تحديد نطاق مهمة مراجعة', 'IAF',
          'رئيس المراجعة الداخلية', 'رئيس فريق المراجعة', 'المالك أو لجنة المراجعة',
          'فريق المراجعة', 'رئيس المراجعة الداخلية',
          'الحوكمة لا تعطي المراجع نطاقه ولا تعتمد خطته',
          'AuditDomainService::draftProgram', 'AAM-IAF-01', 'نائب رئيس المراجعة',
          'الكون الرقابي كله', 'تفويض بالميثاق'),
    array('iaf.program.review', 'مراجعة خطوة برنامج', 'IAF',
          'منفذ الخطوة', 'رئيس فريق المراجعة', 'رئيس فريق المراجعة', 'منفذ الخطوة', 'رئيس الفريق',
          'من نفذ الخطوة لا يراجعها',
          'AuditDomainService::approveProgram', 'AAM-IAF-02', 'رئيس المراجعة الداخلية',
          'برامج المهمة', 'تفويض بالمنصب'),
    array('iaf.finding.close', 'إغلاق ملاحظة مراجعة', 'IAF',
          'الجهة الخاضعة للمراجعة', 'فريق المراجعة', 'رئيس المراجعة الداخلية',
          'الجهة الخاضعة', 'المراجعة الداخلية',
          'الخاضع للمراجعة لا يغلق ملاحظته والحوكمة لا تغلقها نيابة عن المراجعة',
          'AuditDomainService::closeFinding', 'AAM-IAF-03', 'نائب رئيس المراجعة',
          'ملاحظات المهمة', 'لا تفويض خارج المراجعة'),
    array('iaf.independence.declare', 'إقرار الاستقلال قبل التكليف', 'IAF',
          'المراجع', 'رئيس فريق المراجعة', 'رئيس المراجعة الداخلية', 'النظام', 'رئيس المراجعة',
          'لا يراجع أحد عملا شارك فيه',
          'Audit/iaf_independence.php', 'AAM-IAF-04', 'المالك أو لجنة المراجعة',
          'كل تكليف', 'لا تفويض في الاستقلال'),
    array('iaf.evidence.request', 'طلب دليل من جهة خاضعة', 'IAF',
          'فريق المراجعة', 'رئيس فريق المراجعة', 'رئيس المراجعة الداخلية',
          'الجهة الخاضعة', 'فريق المراجعة',
          'لا يطلب المراجع دليلا من نفسه ولا وصول كاتب للمعاملات',
          'AuditDomainService::requestEvidence', 'AAM-IAF-05', 'نائب رئيس المراجعة',
          'نطاق المهمة وحده', 'قراءة تأكيد لا كتابة معاملة'),
);
$sodN = 0;
foreach ($SOD as $s) {
    if ($W("INSERT INTO repair01_w14_sod
            (process_key,process_name,domain_code,initiator_role,reviewer_role,approver_role,
             executor_role,closer_role,forbidden_combo,enforced_by,authority_rule_id,deputy_role,
             scope_rule,delegation,effective_date,src_ref)
            VALUES ('" . $esc($s[0]) . "','" . $esc($s[1]) . "','" . $esc($s[2]) . "',
                    '" . $esc($s[3]) . "','" . $esc($s[4]) . "','" . $esc($s[5]) . "',
                    '" . $esc($s[6]) . "','" . $esc($s[7]) . "','" . $esc($s[8]) . "',
                    '" . $esc($s[9]) . "','" . $esc($s[10]) . "','" . $esc($s[11]) . "',
                    '" . $esc($s[12]) . "','" . $esc($s[13]) . "','2026-08-27','RPR-W14 §٧')")) { $sodN++; }
}
$sodCodes = repair01_w14_sod_codes();
printf("  عملياتٌ حرجةٌ بستّةِ أدوارٍ وتركيبةٍ ممنوعة %d · رموزُ ردٍّ مُعلَنة %d\n\n",
    $sodN, count($sodCodes));

/* ═══════════════════════════════════════════════════════════════════════════
   ⑧ عقودُ الأثر — **حدثٌ بلا عقدٍ مسجَّلٍ لا يُنفَّذ**
   ═══════════════════════════════════════════════════════════════════════════
   لكلِّ حدث: المصدرُ · المحفِّزُ · الحمولةُ الدنيا · **كلُّ مستهلكٍ بالاسم** ·
   أثرُ كلٍّ منهم · شروطُه المسبقة · سياسةُ الإعادة · مفتاحُ منعِ التكرار ·
   سلوكُ الفشلِ والتعويض.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑧ عقودُ الأثر ────────────────────────────────────────────────\n";
$W("DELETE FROM repair01_events WHERE wave = 'W14'");
$EV = array(
    array('ctl.deviation.registered', 'تسجيل انحراف تشغيلي', 'ctl_deviation',
          'Risk/risk_events.php', 'وقوع انحراف تشغيلي عند مالكه بمرجع مصدره',
          'deviation_no · owner_dept · source_table · source_row_id',
          'DeviationClassifier::classify',
          'التصنيف يصير ممكنا - وقبل التسجيل لا يقرأ نطاق رقابة الواقعة اصلا',
          'مالك تشغيلي غير نطاق رقابة ومرجع مصدر قائم',
          'إعادة بلا أثر', 'w14:ctl.deviation.registered', 'يرفع ولا يبتلع',
          'التصحيح بواقعة انحراف تالية بمرجعها لا بتعديل المسجلة', 'control'),
    array('ctl.deviation.classified', 'تصنيف الانحراف بقاعدة مكتوبة', 'ctl_deviation',
          'Risk/risk_events.php', 'تطبيق قاعدة تصنيف نافذة على انحراف مسجل',
          'deviation_no · classification · rule_code',
          'RiskDomainService::raiseTrigger · GovernanceDomainService::openBreach',
          'المخاطر تفتح محفزا ان صار تعرضا · والحوكمة تفتح حالة ان صار خرقا · '
          . 'وان بقي انحرافا فقط فلا يفتح اي منهما ويبقى عند مالكه',
          'قاعدة نافذة بشروطها الثلاثة وفاعل مسجل',
          'إعادة بلا أثر', 'w14:ctl.deviation.classified', 'يرفع ولا يبتلع',
          'التصحيح بتصنيف تال بقاعدته لا بقلب التصنيف صامتا', 'control'),
    array('rsk.trigger.raised', 'فتح محفز خطر', 'rsk_trigger',
          'Risk/risk_events.php', 'تحقق قاعدة محفز على واقعة غير مخططة',
          'trigger_no · rule_code · threshold_key · deviation_no',
          'RiskDomainService::openExposure',
          'فتح التعرض يصير ممكنا - والمخطط لا يفتح محفزا اصلا فلا يملأ سجل المخاطر بصيانة مخططة',
          'عتبة معتمدة من السجل ونوع توقف غير مخطط ومرجع مصدر',
          'إعادة بلا أثر', 'w14:rsk.trigger.raised', 'يرفع ولا يبتلع',
          'التصحيح باستبعاد المحفز بسببه لا بمحوه', 'risk'),
    array('rsk.exposure.opened', 'فتح تعرض في سجل المخاطر', 'risk_register',
          'Risk/risk_register.php', 'تحويل محفز مفروز الى خطر مسجل',
          'risk_code · trigger_id · deviation_no',
          'Risk/risk_assessment.php · Risk/risk_treatments.php',
          'التقييم والمعالجة يقرآن الخطر برمزه فيصير للتعرض مالك ومهلة',
          'محفز مفروز ورمز خطر غير مكرر',
          'إعادة بلا أثر', 'w14:rsk.exposure.opened', 'يرفع ولا يبتلع',
          'التصحيح بدمج الخطر في اخر بمرجعه لا بحذفه', 'risk'),
    array('rsk.event.recorded', 'تسجيل حدث خطر او خسارة', 'rsk_event',
          'Risk/risk_events.php', 'وقوع حدث عند مصدره التشغيلي',
          'event_no · source_module · source_table · source_row_id',
          'Risk/risk_assessment.php',
          'التقييم يقرأ الحدث بمرجعه ولا ينسخ حمولته - والمفتاح الفريد يرد نسخة ثانية للمصدر نفسه',
          'مرجع مصدر كامل وعائلة من الاربع',
          'إعادة بلا أثر', 'w14:rsk.event.recorded', 'يرفع ولا يبتلع',
          'التصحيح بحدث عكسي بمرجعه لا بتعديل المسجل', 'risk'),
    array('rsk.risk.accepted', 'قبول خطر ضمن الشهية', 'risk_acceptances',
          'Risk/risk_acceptance.php', 'قرار قبول بسلطة مستواه',
          'risk_id · accepted_by · valid_until',
          'Risk/risk_reviews.php',
          'المراجعة الدورية تقرأ القبول بأجله فينتهي القبول بانتهائه ولا يبقى مفتوحا',
          'شهية معتمدة في السجل والمتبقي ضمنها',
          'إعادة بلا أثر', 'w14:rsk.risk.accepted', 'يرفع ولا يبتلع',
          'التصحيح بقبول تال بمرجعه او بتصعيد', 'risk'),
    array('rsk.risk.escalated', 'تصعيد خطر', 'risk_escalations',
          'Risk/risk_escalations.php', 'اختراق حرج او خروج عن الشهية او تأخر معالجة',
          'risk_id · to_authority · reason_ar',
          'Risk/risk_board.php',
          'اللوحة تعرض المصعد فيراه مستواه - ولا يسكت التصعيد الا باستلام مسجل',
          'سبب مكتوب وخطر قائم',
          'إعادة بلا أثر', 'w14:rsk.risk.escalated', 'يرفع ولا يبتلع',
          'الخفض بواقعة معالجة لا بمحو التصعيد', 'risk'),
    array('rsk.risk.closed', 'اعتماد اغلاق خطر', 'rsk_closure',
          'Risk/risk_closure.php', 'اعتماد اغلاق مقترح بدليله',
          'closure_no · risk_code · closure_basis',
          'Risk/risk_register.php · Audit/iaf_universe.php',
          'سجل المخاطر يقرأ الخطر مغلقا · والكون الرقابي يقرأ انخفاض تقديره فتتغير أولوية مراجعته',
          'دليل قائم ومن اقترح لا يعتمد',
          'إعادة بلا أثر', 'w14:rsk.risk.closed', 'يرفع ولا يبتلع',
          'التصحيح باعادة فتح بواقعتها لا بقلب الاغلاق', 'risk'),
    array('gov.policy.effective', 'نفاذ سياسة', 'gov_policy',
          'Governance/policies.php', 'اعتماد اصدار سياسة وتحديد تاريخ نفاذه',
          'policy_no · version_no · effective_from',
          'Governance/guards.php · Governance/obligations.php',
          'قواعد المنع تقرأ سندها في السياسة النافذة · والالتزامات تقرأ مرجعها فيها',
          'وثيقة وتاريخ نفاذ ومن كتب لا يعتمد',
          'إعادة بلا أثر', 'w14:gov.policy.effective', 'يرفع ولا يبتلع',
          'التصحيح باصدار خلف يحمل مرجع السلف لا بتعديل النافذ', 'governance'),
    array('gov.obligation.due', 'استحقاق التزام تنظيمي', 'gov_compliance_due',
          'Governance/compliance_calendar.php', 'حلول موعد مشتق من التزام مسجل',
          'obligation_no · due_date · derived_from',
          'Governance/regulatory_filings.php',
          'التقديم يفتح على الاستحقاق فيصير للموعد مالك ومهلة - ولا استحقاق بلا مرجع اشتقاق',
          'التزام مسجل بدوريته',
          'إعادة بلا أثر', 'w14:gov.obligation.due', 'يرفع ولا يبتلع',
          'التصحيح باستحقاق تال لا بتعديل الماضي', 'governance'),
    array('gov.filing.submitted', 'تقديم نظامي', 'gov_filing',
          'Governance/regulatory_filings.php', 'تقديم فعلي لجهة نظامية',
          'filing_no · authority_ar · submitted_at',
          'Governance/compliance_calendar.php',
          'الاستحقاق يقرأ التقديم فيقفل - وبلا ايصال يبقى مقدما لا مستلما',
          'استحقاق قائم ومحتوى جاهز',
          'إعادة بلا أثر', 'w14:gov.filing.submitted', 'يرفع ولا يبتلع',
          'التصحيح بتقديم معدل يحمل مرجع الاول', 'governance'),
    array('gov.breach.opened', 'فتح حالة حوكمة', 'gov_breach',
          'Governance/breaches.php', 'خرق ضابط او سياسة او التزام بأساس من الثمانية',
          'case_no · opened_basis · control_ref · deviation_no',
          'Governance/corrective_actions.php · Audit/iaf_universe.php',
          'الاجراء التصحيحي يفتح على الحالة · والكون الرقابي يرفع تقدير مخاطر مجالها فتتقدم في الخطة',
          'اساس من الثمانية وضابط مكسور وانحراف مصنف غير انحراف فقط',
          'إعادة بلا أثر', 'w14:gov.breach.opened', 'يرفع ولا يبتلع',
          'التصحيح بحالة تالية تحمل مرجع الاولى لا بمحو المفتوحة', 'governance'),
    array('gov.action.assigned', 'اسناد اجراء تصحيحي', 'gov_corrective_action',
          'Governance/corrective_actions.php', 'اسناد اجراء على مصدر مسجل',
          'action_no · source_kind · source_ref · owner_person · due_date',
          'Governance/breaches.php · Governance/audit_followup.php',
          'حالة الحوكمة تقرأ اجراءها فتصير قابلة للاغلاق · ومتابعة المراجعة تقرأ رقم الاجراء',
          'مصدر مسجل ومالك ومهلة',
          'إعادة بلا أثر', 'w14:gov.action.assigned', 'يرفع ولا يبتلع',
          'التصحيح باجراء بديل بمرجعه', 'governance'),
    array('gov.action.closed', 'التحقق من اجراء تصحيحي', 'gov_corrective_action',
          'Governance/corrective_actions.php', 'تحقق بيد غير يد المالك بدليله',
          'action_no · evidence_ref · verified_by',
          'Governance/breaches.php',
          'اغلاق حالة الحوكمة يصير ممكنا - وقبل التحقق يرد الاغلاق في الخدمة وفي قيد القاعدة معا',
          'دليل قائم ومالك الاجراء لا يتحقق منه',
          'إعادة بلا أثر', 'w14:gov.action.closed', 'يرفع ولا يبتلع',
          'التصحيح باجراء تكميلي لا بقلب التحقق', 'governance'),
    array('gov.investigation.concluded', 'حسم تحقيق', 'gov_investigation',
          'Governance/investigations.php', 'اصدار نتيجة تحقيق بعد ادلته',
          'inv_no · inv_kind · conclusion_ar · referred_to',
          'Employees/hr_disciplinary.php · Governance/corrective_actions.php',
          'الموارد البشرية تستقبل الاثر التأديبي ولا تعيد التحقيق · والاجراء التصحيحي يفتح على النتيجة',
          'مرحلة ادلة سابقة ومن فتح لا يحسم',
          'إعادة بلا أثر', 'w14:gov.investigation.concluded', 'يرفع ولا يبتلع',
          'التصحيح بتقرير تكميلي يحمل مرجع الاول', 'governance'),
    array('gov.integrity.triaged', 'فرز بلاغ نزاهة', 'gov_integrity_report',
          'Governance/integrity_reports.php', 'فرز بلاغ مستلم قبل اي تحقيق',
          'report_no · referred_to · triage_at',
          'Governance/investigations.php',
          'التحقيق يصير ممكنا بمرجع الفرز - والنقر الممنوع لا يفتح تحقيقا الا بعد فرز',
          'بلاغ مستلم برمز مبلغ',
          'إعادة بلا أثر', 'w14:gov.integrity.triaged', 'يرفع ولا يبتلع',
          'التصحيح بفرز ثان يحمل مرجع الاول', 'governance'),
    array('iaf.program.approved', 'اعتماد برنامج مراجعة', 'iaf_program',
          'Audit/iaf_audit_programs.php', 'مراجعة رئيس الفريق لخطوة برنامج بمنهجية عينتها',
          'program_no · step_no · sample_size · sampling_basis',
          'Audit/iaf_test_samples.php · Audit/iaf_evidence_requests.php',
          'سحب العينة يصير ممكنا · وطلب الدليل يفتح على الخطوة المعتمدة',
          'من نفذ لا يراجع ونطاق من المراجعة ومنهجية عينة معلنة',
          'إعادة بلا أثر', 'w14:iaf.program.approved', 'يرفع ولا يبتلع',
          'التصحيح بخطوة بديلة بمرجعها', 'audit'),
    array('iaf.evidence.overdue', 'تأخر تزويد دليل', 'iaf_evidence_request',
          'Audit/iaf_evidence_requests.php', 'تجاوز مهلة التزويد بعتبة من السجل',
          'request_no · auditee_dept · delay_days',
          'Audit/iaf_escalations.php · Governance/breaches.php',
          'التصعيد يقرأ الواقعة بسلمها · والحوكمة تفتح حالة ان تكرر المنع بأساس من الثمانية',
          'مهلة منقضية وعتبة تصعيد معتمدة',
          'إعادة بلا أثر', 'w14:iaf.evidence.overdue', 'يرفع ولا يبتلع',
          'التصحيح بتزويد متأخر بمرجعه لا بمحو الواقعة', 'audit'),
    array('iaf.finding.raised', 'رفع ملاحظة مراجعة', 'iaf_findings',
          'Audit/iaf_findings.php', 'استثناء في عينة او خلاصة اختبار',
          'finding_no · auditee_dept · severity',
          'Governance/audit_followup.php · Audit/iaf_action_plans.php',
          'الحوكمة تتابع خطة الادارة بمرجع الملاحظة ولا تلمس نتيجتها · والمتابعة تفتح خطة معالجتها',
          'برنامج معتمد ونتيجة اختبار وواضع النتيجة المراجعة وحدها',
          'إعادة بلا أثر', 'w14:iaf.finding.raised', 'يرفع ولا يبتلع',
          'التصحيح بملاحظة تالية تحمل مرجع الاولى لا بتعديل الصادرة', 'audit'),
    array('iaf.finding.closed', 'اغلاق ملاحظة مراجعة', 'iaf_findings',
          'Audit/iaf_findings.php', 'تحقق المراجعة من معالجة الملاحظة بدليلها',
          'finding_no · evidence_ref · closed_by_dept',
          'Governance/audit_followup.php · Audit/iaf_overview.php',
          'متابعة الحوكمة تقرأ الملاحظة مغلقة ولا تغلقها هي · واللوحة تنقص عداد المفتوح',
          'دليل قائم والخاضع لا يغلق ملاحظته والمغلق المراجعة وحدها',
          'إعادة بلا أثر', 'w14:iaf.finding.closed', 'يرفع ولا يبتلع',
          'التصحيح باعادة فتح بواقعتها لا بقلب الاغلاق', 'audit'),
);
$evN = 0; $evUndeclared = array();
$declaredEv = repair01_w14_stage_events();
$unitOf = array('control' => 'AS الانحراف التشغيلي عند مالكه',
                'risk' => '09 إدارة المخاطر',
                'governance' => '08 الحوكمة والالتزام',
                'audit' => 'AS المراجعة الداخلية المستقلة');
foreach ($EV as $e) {
    if (!in_array($e[0], $declaredEv, true)) { $evUndeclared[] = $e[0]; continue; }
    if ($W("INSERT INTO repair01_events
        (event_code,name,wave,source_unit,source_screen,idempotency_key,consumers,effect_type,
         retry_policy,src_ref,trigger_rule,min_payload,consumer_list,consumer_effect,
         preconditions,failure_policy,compensation,contract_status,contract_rule,contract_stage)
        VALUES ('" . $esc($e[0]) . "','" . $esc($e[1]) . "','W14',
                '" . $esc($unitOf[$e[13]]) . "','" . $esc($e[3]) . "','" . $esc($e[10]) . "',
                '" . $esc($e[6]) . "','" . $esc($e[7]) . "','" . $esc($e[9]) . "','RPR-W14 §٨',
                '" . $esc($e[4]) . "','" . $esc($e[5]) . "','" . $esc($e[6]) . "','" . $esc($e[7]) . "',
                '" . $esc($e[8]) . "','" . $esc($e[11]) . "','" . $esc($e[12]) . "',
                'RECORDED','W14_EVENT_CONTRACT','W14')")) { $evN++; }
}
$evMissing = array_values(array_diff($declaredEv, array_column($EV, 0)));
printf("  عقودُ أثرٍ مكتوبة %d · حدثٌ مُعلَنٌ بلا عقد %d%s · عقدٌ بلا إعلان %d%s\n\n",
    $evN, count($evMissing), $evMissing ? ' ⇐ ' . implode('، ', $evMissing) : '',
    count($evUndeclared), $evUndeclared ? ' ⇐ ' . implode('، ', $evUndeclared) : '');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑨ دفترُ النطاقاتِ الثلاثة — **مقامٌ ثابتٌ لا يخلو**
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑨ دفترُ النطاقاتِ الثلاثة ───────────────────────────────────\n";
$W("DELETE FROM repair01_w14_domains");
$domN = 0; $domMissing = array();
foreach (repair01_w14_domains() as $tbl => $d) {
    if (!repair01_w14_table_exists($conn, $tbl)) { $domMissing[] = $tbl; }
    if ($W("INSERT INTO repair01_w14_domains
            (table_name,domain_code,domain_ar,source_key,line,owns,never_owns,read_by,service_file,why,src_ref)
            VALUES ('" . $esc($tbl) . "','" . $esc($d[0]) . "','" . $esc($d[1]) . "','" . $esc($d[2]) . "',
                    '" . $esc($d[3]) . "','" . $esc($d[4]) . "','" . $esc($d[5]) . "','" . $esc($d[6]) . "',
                    '" . $esc($d[7]) . "','" . $esc($d[8]) . "','RPR-W14 §٩')")) { $domN++; }
}
$xw = repair01_w14_cross_domain_writes($ROOT);
printf("  جداولٌ بنطاقٍ واحدٍ وخدمةٍ مالكة %d · جدولٌ غيرُ موجود %d%s · خدماتٌ مُسِحت %d · كتابةٌ عابرةٌ للنطاق %d%s\n\n",
    $domN, count($domMissing), $domMissing ? ' ⇐ ' . implode('، ', array_slice($domMissing, 0, 4)) : '',
    $xw['scanned'], $xw['n'], $xw['n'] ? ' ⇐ ' . implode('، ', array_slice($xw['detail'], 0, 4)) : '');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑩ قراراتُ المرحلة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑩ قراراتُ المرحلة ────────────────────────────────────────────\n";
$W("DELETE FROM repair01_w14_decisions");
$govCase = repair01_w14_gov_case_on_pure_deviation($conn);
$riskCopy = repair01_w14_risk_event_copies($conn);
$audTouch = repair01_w14_gov_touched_audit_result($conn);
$DEC = array(
    array('W14-D-01', 'هل تبنى الثلاثة محرّكا واحدا ام ثلاثة نطاقات؟',
          'ثلاث خدمات نطاق مستقلة وخدمة محايدة رابعة للانحراف',
          'امر المالك الاول البند 46 ينهى صراحة عن Governance Risk Audit محركا واحدا. '
          . 'والفصل ليس شعارا بل يقاس: repair01_w14_domains يعلن لكل جدول نطاقا واحدا بمفتاح فريد، '
          . 'وماسح بنيوي يقرأ شيفرة الخدمات الاربع فيرصد كتابة نطاق في جدول نطاق اخر. '
          . 'والقراءة بمرجع لا ترصد لانها عين ما امر به المالك.',
          count(repair01_w14_domains()), '2026-08-27', 'قيدُ المالك §١'),
    array('W14-D-02', 'اين يسكن الانحراف التشغيلي؟',
          'عند مالكه التشغيلي وحده والرقابة تقرؤه بمرجعه',
          'قرار المالك الثاني: العطل يبقى Source Event عند التشغيل والصيانة. '
          . 'فجدول الانحراف يحمل قيدا يرد ان يملكه DEP-08 او DEP-09 او IAF، '
          . 'وحالة الحوكمة تحمل مرجع الانحراف لا نسخته، وحدث الخطر كذلك. '
          . 'وهذا هو الفرق بين مرجع ومشاركة.',
          1, '2026-08-27', 'قرارُ المالك ②'),
    array('W14-D-03', 'متى يفتح العطل حالة حوكمة؟',
          'لا يفتحها الا باساس من الثمانية التي سماها المالك',
          'قرار المالك الثاني يعدد ثمانية: تجاهل اجراء الزامي وعدم تصعيد وتجاوز صلاحية '
          . 'وتلاعب واخفاء وتزوير وخرق سياسة، ويضاف اليها كسر ضابط. '
          . 'والقيد chk_gvb_basis يحصرها، والخدمة ترد BREACH_ON_PURE_DEVIATION على انحراف مصنف انحرافا فقط. '
          . 'والمقيس الان: ' . $govCase['n'] . ' على ' . $govCase['front'] . ' جبهات.',
          $govCase['n'], '2026-08-27', 'قرارُ المالك ②'),
    array('W14-D-04', 'هل ينسخ سجل المخاطر احداث المصدر؟',
          'لا - يقرؤها بمرجعها ومفتاح فريد يرد النسخة الثانية',
          'قيد المالك الثالث: لا تنسخ Source Events. فحدث الخطر يشترط source_module و source_table '
          . 'و source_row_id، ومفتاح uq_rev_source يرد نسخة ثانية للمصدر نفسه في القاعدة لا في النية. '
          . 'والمقيس الان: ' . $riskCopy['n'] . ' على ' . $riskCopy['front'] . ' جبهات.',
          $riskCopy['n'], '2026-08-27', 'قيدُ المالك §٣'),
    array('W14-D-05', 'هل تملك الحوكمة نتيجة المراجعة؟',
          'لا - تتابع خطة الادارة بمرجع الملاحظة ولا تضع النتيجة ولا تغلقها',
          'قيد المالك الاول: الحوكمة لا تعطي المراجع نطاقه ولا تغير نتيجة ولا تغلقها نيابة عنه. '
          . 'فعمودان اضيفا الى سجل الملاحظات بقيدين يحصران واضع النتيجة ومغلقها في IAF، '
          . 'وجدول متابعة الحوكمة لا يحمل عمود نتيجة اصلا فالباب مغلق بنية لا بسياسة. '
          . 'والمقيس الان: ' . $audTouch['n'] . ' على ' . $audTouch['front'] . ' جبهات.',
          $audTouch['n'], '2026-08-27', 'قيدُ المالك §١'),
    array('W14-D-06', 'اي عتبة تكتب رقما واي عتبة تبقى عدما؟',
          'ما نصّ عليه المالك حرفا معتمد بمرجعه وما سواه قيمته عدم',
          'قرار المالك الاخير: هذه لا اريد Hardcode لها الان ولا يجوز للمبرمج ان يخترع قيمتها. '
          . 'فسجل العتبات بثلاث حالات وقيود قاعدة اربعة: معتمدة تلزمها قيمة ومرجع، '
          . 'ومعلقة قيمتها عدم، وقيمة الاختبار لا تكون على معتمدة. '
          . 'والمعتمد ' . $thApproved . ' والمعلق ' . $thPending . '.',
          $thPending, '2026-08-27', 'قرارُ المالك الأخير'),
    array('W14-D-07', 'ماذا يعني خلاء المقام في حواجب هذه المرحلة؟',
          'الصفر يمر معلنا في هذا القرار وحده ويسقط بلا اعلان',
          'درس W01 و W07 و W09: مجموعة خاوية تخضر الحاجب على تطابق لا شيء. '
          . 'فمقامات هذه المرحلة معلنة في المكتبة: النطاقات والقيود والرموز وكيانات الحالة والاحداث، '
          . 'وكل حاجب يقارن المقيس بالمعلن لا بالحي وحده.',
          1, '2026-08-27', 'قواعدُ القياس ④'),
    array('W14-D-08', 'هل يبنى سطح لسجل انواع الطلبات؟',
          'لا - القدرة تبنى وتحكم بقيودها ولا سطح لها في متطلبات هذه المرحلة',
          'قرار المالك الثالث اعتمد Unified Request Type Registry قدرة منصية مركزية. '
          . 'ونص المرحلة §٣ يقول: هذه قائمتك الكاملة لا تشتق غيرها — وليس بين الواحد والستين متطلب يحمله. '
          . 'فالجدول بني وقيوده الاربعة تنفذ القاعدة الرباعية، والسطح مؤجل معلنا الى مرحلة يحمله نطاقها.',
          1, '2026-08-27', 'قرارُ المالك ③ · نصُّ المرحلة §٣'),
    array('W14-D-10', 'هل عداد الجبهات في حاجب عتبة صلبة؟',
          'لا - المقام مقامان: طبقة اعمال تشترط صفرا وطبقة قياس تعلن بعددها',
          'كاشف W13 رسا على حجم الرقم فمرت عتبات رقمين، وكاشف هذه المرحلة يرسو على القيمة '
          . 'في موضع مقارنة فيصير == 3 في حاجب عتبة وهو عداد جبهات يقول قست ثلاثا. '
          . 'فالمقام قسم قسمين بمعنى لا باعفاء: خدمات النطاق الاربع واسطحها الستة والعشرون '
          . 'يشترط فيها صفر لان الرقم هناك يقرر امر عمل، وادوات القياس تعلن بعددها وسطورها '
          . 'ولا يدعى صفرها. والاعفاء المسكوت عنه هو ما ترده هذه القسمة لا ما تفعله.',
          0, '2026-08-27', 'قواعدُ القياس ① · درسُ W13'),
    array('W14-D-11', 'عشرون سطحا حيا خرجت بلا حارس — أهي بلا حارس فعلا؟',
          'لا - الكاشف كان اعمى والحارس داخل عدة مضمنة',
          'الحاجب قال بلا حارس 20 وهي محروسة كلها: عائلة المخاطر تحرس بـrisk_guard_screen في '
          . '_risk_common.php وعائلة المراجعة بـu13_screen_kit، وكلاهما ينادي check_page_permissions. '
          . 'وكاشف يقرأ ملف الشاشة وحده يقرأ العطب في المنقى وهو في المنقي — وهو عين درس W06. '
          . 'فالكاشف صار يتبع مضمنه مستوى واحدا اتباعا عاما لا بقائمة اسماء.',
          20, '2026-08-27', 'درسُ W06 · العطبُ في المُنقّي'),
    array('W14-D-12', 'عائلة المراجعة الداخلية كلها خارج سجل بوابة العزل — ماذا يفعل؟',
          'تسجل احد عشر جدولا T_TENANT بعمودها هي - اضافة لا تخفيف',
          'كشفته الرحلة عند اول كتابة في سجل الملاحظات عبر البوابة: TenantGate unregistered table. '
          . 'وكل جدول من الاحد عشر يحمل company_id غير قابل للعدم اصلا، فالتسجيل يثبت ما كان '
          . 'المخطط يضمنه ولا يفتح بابا. ولم يكن احد كتب في هذه العائلة عبر البوابة من قبل — '
          . 'وهو عين ما وقع في W13 مع ستة وعشرين ابنا.',
          11, '2026-08-27', 'RPR-W14 §١٢ · W14-F-14'),
    array('W14-D-09', 'اي مرحلة يقف عندها ما بني في هذه الموجة؟',
          'Connected لا Accepted',
          'قيد المالك الحادي عشر: التدرج تسع حالات لا واحدة. وما بني هنا مسجل ومملوك ومحروس '
          . 'وموصول بمستهلكيه بعقود اثرها، ولم يمر بعد بـ Data Ready على بيانات حية ولا Human UAT. '
          . 'فلا تكتب نسبة واحدة ولا يقال مكتمل.',
          9, '2026-08-27', 'قيدُ المالك §١١'),
);
$decN = 0;
foreach ($DEC as $d) {
    if ($W("INSERT INTO repair01_w14_decisions (decision_id,question,answer,rationale,scope_rows,decided_at,src_ref)
            VALUES ('" . $esc($d[0]) . "','" . $esc($d[1]) . "','" . $esc($d[2]) . "','" . $esc($d[3]) . "',
                    " . (int) $d[4] . ",'" . $esc($d[5]) . "','" . $esc($d[6]) . "')")) { $decN++; }
}
printf("  قراراتٌ مسجَّلة %d\n\n", $decN);

/* ═══════════════════════════════════════════════════════════════════════════
   ⑩-ب · المؤجَّلُ بقرارِ المالك — **يُسجَّل صراحةً ولا يُخمَّن**
   ═══════════════════════════════════════════════════════════════════════════
   ⛔ **ولا يُكتب قرارُ مالكٍ نيابةً عنه** (‏قيدُ المالك §٩): كلُّ سؤالٍ احتاجته
     هذه المرحلةُ ولم يُجب عنه المالكُ يُسجَّل هنا **ببيانِ ما بُني رغمه وكيف**
     — فالتأجيلُ يُعلَن بأثرِه لا بذكرِه.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑩-ب المؤجَّلُ بقرارِ المالك ─────────────────────────────────────\n";
$W("DELETE FROM repair01_w14_deferred");
$DEF = array(
    array('W14-P-01', 'اي مستوى مخول يجوز له كشف هوية المبلغ في القناة المحمية؟',
          'GOV-22 يقول: هوية المبلغ محجوبة الا لمستوى مخول — والمستوى نفسه لم يسمه المالك',
          'لا شيء - السطح والقناة والقيود كلها بنيت',
          'الهوية محجوبة بالبناء: رمز مبلغ في عمود، والاسم يمنع في المجهول بقيد chk_gir_anon، '
          . 'والمستوى يقرأ من disclosure_role_key في السجل — فالحقل قائم فارغا وينتظر قيمته',
          'STRUCTURAL', '2026-08-27', 'RPR-W14 §١٠-ب · GOV-22'),
    array('W14-P-02', 'ما القيم الكمية لشهية المخاطر وحدود التحمل؟',
          'DEC-OPEN-08 عتبة عددية والقبول فوق الشهية يحتاجها',
          'قبول خطر بقيمة كمية - والمحرك يرد APPETITE_NOT_CONFIGURED ولا يفترض',
          'المحرك مبني قابل الضبط يقرأ من Risk Appetite Registry، وقيمة الاختبار موسومة '
          . 'في عمود منفصل وقيد القاعدة يمنع انتقالها الى الانتاج',
          'THRESHOLD', '2026-08-27', 'RPR-W14 §١٠-ب · DEC-OPEN-08'),
    array('W14-P-03', 'ما حد الافصاح عن الهدية والضيافة؟',
          'GOV-12 يقول: الافصاح فوق الحد المضبوط الزامي — والحد لم يعتمد',
          'الافصاح الالي فوق الحد - والافصاح اليدوي يعمل',
          'السطح والجدول والدورة مبنية، والحد يقرأ من Policy Registry بمفتاحه المسجل في صف الافصاح نفسه',
          'THRESHOLD', '2026-08-27', 'RPR-W14 §١٠-ب · GOV-12'),
    array('W14-P-04', 'ما دورية التقييم الخارجي المستقل لوظيفة المراجعة؟',
          'IAF-16 يقول: خارجي مستقل بدورية معلنة — والدورية لم تعلن',
          'لا شيء - سطح تقييم الجودة قائم ويعمل',
          'الدورية مفتاح في سجل العتبات معلق بقيمة عدم، والتقييم يسجل بتاريخه بلا انتظارها',
          'THRESHOLD', '2026-08-27', 'RPR-W14 §١٠-ب · IAF-16'),
    array('W14-P-05', 'ما نصاب انعقاد كل لجنة؟',
          'GOV-30 يقول: اللجان بتشكيلها ودورية انعقادها — والنصاب لم يعتمد',
          'لا شيء - سجل اللجان وتشكيلها ودوريتها مبني',
          'النصاب مفتاح في صف اللجنة يقرأ من السجل، وحقل quorum_key قائم ينتظر قيمته',
          'THRESHOLD', '2026-08-27', 'RPR-W14 §١٠-ب · GOV-30'),
    array('W14-P-06', 'اي مرحلة يحمل نطاقها سطح سجل انواع الطلبات؟',
          'قرار المالك الثالث اعتمد القدرة، ونص هذه المرحلة لا يحمل لها متطلبا في الواحد والستين',
          'سطح واحد - ولا يعطل قدرة ولا قيدا',
          'الجدول وقيوده الاربعة مبنية وتنفذ القاعدة الرباعية، والخدمة ترد تعريفا تملكه الحوكمة، '
          . 'والسطح ينتظر مرحلة يحمله نطاقها او Enterprise Debt Closure',
          'STRUCTURAL', '2026-08-27', 'RPR-W14 §١٠-ب · DEC-OPEN-17'),
);
$defN = 0;
foreach ($DEF as $d) {
    if ($W("INSERT INTO repair01_w14_deferred
            (deferred_id,question,why_needed,blocked_what,built_anyway,kind,raised_at,src_ref)
            VALUES ('" . $esc($d[0]) . "','" . $esc($d[1]) . "','" . $esc($d[2]) . "','" . $esc($d[3]) . "',
                    '" . $esc($d[4]) . "','" . $esc($d[5]) . "','" . $esc($d[6]) . "','" . $esc($d[7]) . "')")) {
        $defN++;
    }
}
printf("  مؤجَّلٌ مسجَّلٌ صراحةً %d ⛔ ولا جوابَ يُخمَّن\n\n", $defN);

/* ═══════════════════════════════════════════════════════════════════════════
   ⑪ الشجرةُ الحاكمةُ وقاعدةُ التصنيف — بذرةُ المرجعِ لا بيانات عمل
   ═══════════════════════════════════════════════════════════════════════════
   ◆ **العائلاتُ الأربعُ معتمَدةٌ بقيدِ المخطَّط** — فتُبذَر عقدُها الجذريّةُ
     ليكون للتصنيفِ مرجعٌ من أوّلِ يوم. **وهذا مرجعٌ لا بياناتُ تشغيل.**
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑪ الشجرةُ الحاكمةُ وقاعدةُ التصنيف ──────────────────────────\n";
$FAM = array(
    array('RSKF-OPR', 'OPERATIONAL', 'المخاطر التشغيلية', 'توقف الأصول وتعثر التنفيذ'),
    array('RSKF-CAP', 'CAPITAL', 'المخاطر الرأسمالية', 'التمويل والموردون والالتزامات الرأسمالية'),
    array('RSKF-CUS', 'CUSTOMER_CONTRACTUAL', 'المخاطر التعاقدية مع العملاء', 'التزامات العقود والمطالبات'),
    array('RSKF-PRC', 'PROCUREMENT_SUPPLY', 'مخاطر المشتريات وسلاسل التوريد', 'التوريد والمخزون والنقل'),
);
$famN = 0;
foreach ($FAM as $f) {
    if ($W("INSERT INTO rsk_taxonomy (company_id,node_code,family_code,category_ar,type_ar,depth_no,state,src_ref)
            VALUES (0,'" . $esc($f[0]) . "','" . $esc($f[1]) . "','" . $esc($f[2]) . "','" . $esc($f[3]) . "',
                    1,'active','RPR-W14 §١١')
            ON DUPLICATE KEY UPDATE category_ar=VALUES(category_ar), state='active'")) { $famN++; }
}
$RULES = array(
    array('CTLR-DOWNTIME', 'تصنيف انحراف التوقف غير المخطط', 'UNPLANNED_DOWNTIME',
          'يصير تعرضا اذا تجاوز التوقف غير المخطط الحد المسجل او امتدت مشكلة بسيطة فوق حدها '
          . 'او تكرر فوق حده او كان يمكن منعه',
          'يصير خرقا اذا ظهر تجاهل اجراء الزامي او عدم تصعيد مطلوب او تجاوز صلاحية '
          . 'او تلاعب او اخفاء او تزوير او خرق سياسة',
          'يبقى انحرافا عند مالكه اذا لم يتحقق شرط التعرض ولا شرط الخرق - ولا تفتح له حالة حوكمة',
          'rsk.trigger.unplanned_downtime_hours'),
    array('CTLR-PLANNED', 'تصنيف التوقف المخطط والاستعداد', 'PLANNED_DOWNTIME',
          'لا يصير تعرضا بمحفز الاربع والعشرين ساعة - وله مؤشره الخاص بنوعه',
          'يصير خرقا اذا نفذ بلا خطة معتمدة او خارج مواعيدها المجازة',
          'يبقى واقعة تشغيلية مسجلة بنوعها ولا يملأ سجل المخاطر',
          'rsk.trigger.unplanned_downtime_hours'),
    array('CTLR-CONTROL', 'تصنيف انحراف ضابط او سياسة', 'CONTROL_DEVIATION',
          'يصير تعرضا اذا نتج عنه اثر يتجاوز حد الشهية المسجل',
          'يصير خرقا بكسر الضابط نفسه او خرق السياسة النافذة',
          'يبقى ملاحظة تحسين عند مالك العملية اذا لم يكسر ضابطا ولم يتجاوز حدا',
          'rsk.appetite.limit_amount'),
);
$ruleN = 0;
foreach ($RULES as $r) {
    if ($W("INSERT INTO ctl_classification_rule
            (company_id,rule_code,title_ar,deviation_kind,exposure_test,breach_test,retain_test,
             appetite_key,state,authored_by,src_ref)
            VALUES (0,'" . $esc($r[0]) . "','" . $esc($r[1]) . "','" . $esc($r[2]) . "',
                    '" . $esc($r[3]) . "','" . $esc($r[4]) . "','" . $esc($r[5]) . "',
                    '" . $esc($r[6]) . "','draft',0,'RPR-W14 §١١')
            ON DUPLICATE KEY UPDATE exposure_test=VALUES(exposure_test), breach_test=VALUES(breach_test),
              retain_test=VALUES(retain_test)")) { $ruleN++; }
}
/* ⚠ **والقاعدةُ تُكتب هنا ولا تُنفَّذ هنا.**
   `chk_ctlr_sod` يشترط أن يعتمدها غيرُ كاتبِها، وكاتبُها هنا **الحزمةُ لا
   إنسان** — فاعتمادُها بيدٍ مخترَعةٍ انتحالٌ لقرارِ اعتماد. فتُخزَّن **مسودّةً
   بنصِّها الكامل**، وتنفيذُها فعلٌ بشريٌّ بيدٍ ثانيةٍ يقع في `Human UAT`.
   ⛔ **ولا يُصنَّف انحرافٌ بقاعدةٍ غيرِ نافذة** — والخدمةُ تردُّه. */
$ruleActive = (int) $one("SELECT COUNT(*) FROM ctl_classification_rule WHERE state = 'active'");
printf("  عقدُ عائلاتٍ جذريّة %d · قواعدُ تصنيفٍ مكتوبةٌ مسودّةً %d · نافذةٌ الآن %d "
     . "(‏التنفيذُ فعلٌ بشريٌّ بيدٍ ثانيةٍ لا تكتبه الأداة)\n\n", $famN, $ruleN, $ruleActive);

/* ═══════════════════════════════════════════════════════════════════════════
   ⑫ الإصلاحاتُ — كلٌّ بمتطلَّبِه الكاشف
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑫ الإصلاحات ─────────────────────────────────────────────────\n";
$W("DELETE FROM repair01_w14_fixes");
$FIX = array(
    array('W14-F-01', 'الانحراف التشغيلي لم يكن كيانا فينسب الى نطاق رقابة بلا مالك تشغيلي',
          'GOV-24', 'لا جدول للانحراف - والتوقف يقرأ من سجل الصيانة والخطر ينسخه',
          'ctl_deviation بقيد chk_ctd_owner_not_control يرد ملكية نطاق الرقابة',
          'حين لا يكون للانحراف كيان يملكه مالكه التشغيلي تصير كل واقعة اما خطرا واما لا شيء - '
          . 'وهو عين ما نهى عنه المالك في القرار الثاني'),
    array('W14-F-02', 'التمييز الثلاثي كان قاعدة مكتوبة في سرد لا حقلا يقاس',
          'RSK-04', 'لا عمود تصنيف ولا مرجع قاعدة',
          'classification بخمس قيم و rule_code بقيد chk_ctd_rule_required',
          'قاعدة في نص لا ترد تصنيفا مخالفا - والقاعدة في القاعدة ترده'),
    array('W14-F-03', 'حالة الحوكمة كانت تفتح لاي واقعة بلا اساس محصور',
          'GOV-24', 'لا سجل حالات حوكمة ولا حصر لاساس الفتح',
          'gov_breach بقيد chk_gvb_basis يحصر الاساس في الثمانية',
          'اساس مفتوح يجعل كل توقف قضية حوكمة فيغرق الخط الثاني في تشغيل يومي'),
    array('W14-F-04', 'سجل الملاحظات لم يكن يميز من وضع النتيجة ومن اغلقها',
          'IAF-12', 'closed_by مستخدم بلا ادارة - والحوكمة تستطيع الاغلاق بلا رد',
          'عمودان مضافان بقيدي chk_iaf_result_dept و chk_iaf_close_dept يحصران الاثنين في IAF',
          'استقلال الخط الثالث لا يثبت بسياسة اذا كان الباب مفتوحا في المخطط'),
    array('W14-F-05', 'التحقيق كان مفهوما واحدا بلا ثلاثة ملاك',
          'GOV-23', 'لا جدول تحقيقات - والتاديبي والنزاهة والتقصي بلا فصل',
          'gov_investigation بقيد chk_gin_kind_owner يربط كل نوع بمالكه',
          'DEC-OPEN-16 حسم ثلاثة ملاك - وحسم في وثيقة بلا قيد يعود اجتهادا عند اول قضية'),
    array('W14-F-06', 'المراجعة الداخلية كان يمكن ان تعطى طابور تحقيق يومي',
          'GOV-23', 'لا فرق بين تحقيق باختصاص اصيل وتحقيق بتكليف',
          'chk_gin_iaf_mandate يشترط تكليفا مكتوبا لكل تحقيق مستقل',
          'الخط الثالث اذا حقق يوميا صار خطا ثانيا وفقد تاكيده المستقل'),
    array('W14-F-07', 'النقر الممنوع كان يمكن ان يصير تحقيقا بلا فرز',
          'GOV-15', 'سجل المحاولات قائم بلا سلسلة الى التحقيق',
          'chk_gin_denial_triage يشترط مرجع فرز قبل تحقيق مصدره سجل المنع',
          'تحويل الرفض الالي الى تحقيق يجعل خطأ صلاحية تهمة'),
    array('W14-F-08', 'محفز الخطر كان سيفتح على الصيانة المخططة',
          'RSK-03', 'لا جدول محفزات - والقاعدة العامة تفتح على كل توقف',
          'chk_rtg_planned_excluded يرد محفز الاربع والعشرين على الانواع الاربعة المخططة',
          'نص المالك: لا نريد ملء Risk Register بصيانة مخططة طبيعية'),
    array('W14-F-09', 'حدث الخطر كان سينسخ حمولة مصدره',
          'RSK-04', 'لا مفتاح يمنع نسختين لمصدر واحد',
          'uq_rev_source مفتاح فريد على المصدر ونوع الحدث',
          'النسخة الثانية لا تظهر خطأ بل تظهر رقما مضاعفا في تقرير - وهو اخطر من الخطأ'),
    array('W14-F-10', 'العتبة المعلقة كانت ستكتب صفرا او رقما افتراضيا',
          'RSK-09', 'لا حالة للعتبة - والقيمة اما رقم واما عدم بلا معنى',
          'اربعة قيود على سجل العتبات ومحرك يرد THRESHOLD_NOT_CONFIGURED',
          'صفر ليس عدم اعتماد بل حد يساوي صفرا - والفرق بينهما قرار مالك'),
    array('W14-F-11', 'قيمة الاختبار كانت ستنتقل الى الانتاج بلا حاجب',
          'RSK-09', 'لا فصل بين قيمة معتمدة وقيمة اختبار',
          'عمود منفصل وقيد chk_w14_th_test_not_prod يمنعها على معتمدة',
          'نص المالك: TEST_ONLY_VALUE موسومة بوضوح وغير قابلة للانتقال الى Production'),
    array('W14-F-12', 'التعامل بين كيانات المجموعة كان يكتشف باثر رجعي',
          'GOV-11', 'لا وسم في صف الطرف ذي العلاقة',
          'خماسي الوسم في gov_related_party بقيد chk_grp_intercompany منذ الانشاء',
          'نص المالك الاول: اكتشاف معاملات Intercompany بعد سنوات باثر رجعي سيكون غير موثوق'),
    array('W14-F-14', 'عائلة المراجعة الداخلية كلها خارج سجل بوابة العزل',
          'IAF-12', 'احد عشر جدولا حيا بـcompany_id غير قابل للعدم وصفر منها مسجل',
          'مسجلة T_TENANT في سجل المستأجر بعمودها هي',
          'جدول غير مسجل تنفجر عليه كل كتابة عبر البوابة برمز unregistered table - '
          . 'وكشفته الرحلة عند اول ملاحظة كتبت عبر البوابة لا محلل ساكن. '
          . 'والتسجيل اضافة لا تخفيف: يثبت ما كان المخطط يضمنه ولا يفتح بابا'),
    array('W14-F-15', 'كاشف الحارس كان يقرأ ملف الشاشة ولا يتبع مضمنه',
          'RSK-01', 'عشرون سطحا محروسا يقرأ بلا حارس',
          'الكاشف يتبع كل تضمين محلي مستوى واحدا اتباعا عاما لا بقائمة اسماء',
          'العطب في المنقي لا المنقى - وهو درس W06 يعاد هنا على كاشف حارس لا على منقي نص'),
    array('W14-F-16', 'خمسة وثلاثون صفا في السجل المعياري بلا معرف شاشة',
          'GOV-02', 'معرف الشاشة فارغ في 35 مسارا من النطاق',
          'ملء المعرف من سجل الشاشات لكل مسار في النطاق',
          'الخطوة السابعة ربط بالمعرف لا وجود صف - وتخطي المسار لوجود صفه يعلنها مكتملة وهي ناقصة'),
    array('W14-F-17', 'اسم السطح كان يحمل مصطلحا لاتينيا او لاحقة نطاق فيصير عنوانا',
          'RSK-02', 'تصنيف المخاطر — Taxonomy و سجل السياسات — بحسب انطباق الشركة',
          'repair01_w14_surface_label ينقي اللاحقة والمصطلح قبل ان يسجل الاسم',
          'الشرطة لا تصير نقطتين ولا يبقى الرمز التقني - وهو درس W06 المقيس'),
);
$fixN = 0;
foreach ($FIX as $f) {
    if ($W("INSERT INTO repair01_w14_fixes (fix_key,title,revealed_by,before_num,after_num,why,src_ref)
            VALUES ('" . $esc($f[0]) . "','" . $esc($f[1]) . "','" . $esc($f[2]) . "','" . $esc($f[3]) . "',
                    '" . $esc($f[4]) . "','" . $esc($f[5]) . "','RPR-W14 §١٢')")) { $fixN++; }
}
printf("  إصلاحاتٌ بمتطلَّبِها الكاشف %d\n\n", $fixN);

/* ═══════════════════════════════════════════════════════════════════════════
   الخلاصة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "──────────────────────────────────────────────────────────────\n";
printf("متطلَّبات %d · مِرساةٌ مُثبَتة %d · أسطحُ نموٍّ %d · سايدبار %d · حالات %d · فصلُ واجبات %d · أحداث %d · نطاقات %d\n",
    (int) $one("SELECT COUNT(*) FROM repair01_w14_scope"), $anchored, $newN, $sbN,
    (int) $one("SELECT COUNT(*) FROM repair01_w14_states"),
    (int) $one("SELECT COUNT(*) FROM repair01_w14_sod"),
    (int) $one("SELECT COUNT(*) FROM repair01_events WHERE wave = 'W14'"),
    (int) $one("SELECT COUNT(*) FROM repair01_w14_domains"));
printf("حالةُ حوكمةٍ على انحرافٍ صِرفٍ %d · نسخُ حدثٍ في المخاطر %d · تعديلُ الحوكمةِ لنتيجةِ مراجعةٍ %d · كتابةٌ عابرةٌ للنطاق %d\n",
    $govCase['n'], $riskCopy['n'], $audTouch['n'], $xw['n']);
echo "الحكم: كُتب ✔\n";
