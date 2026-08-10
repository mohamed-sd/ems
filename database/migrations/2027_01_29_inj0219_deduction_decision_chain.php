<?php
/**
 * 2027_01_29 — INJ-0219: قرارُ الخصمِ لا يصير معتمدًا إلا بسلّمٍ ويدين مختلفتين
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ العطلُ المقيسُ حرفًا بحرف (لا نقلًا عن الحكم):
 *   ① شاشةُ `Workforce/deductions.php` مولَّدةٌ من قالب CMP-03، ومنتقي «الحالة»
 *     فيها يعرض «معتمد» **عند الإدراج** — فالصفُّ **يُولد معتمدًا بيدٍ واحدة**.
 *     وأعمدةُ السلسلةِ الخمسةُ (اقترحه · راجعته الموارد · اعتماد الإدارة ·
 *     الاعتماد المالي · اعتماد الإدارة العامة) كلُّها `varchar(300)` يكتبها من
 *     يشاء — سلسلةٌ **مرسومةٌ** لا محكومة.
 *   ② و`cmp03_store_update` يمنع تعديلَ المعتمدِ (CS-11) — فالبابُ الخلفيُّ
 *     الوحيدُ الباقي هو **الميلادُ معتمدًا**، وهو مفتوحٌ في 381 سطحًا من 404.
 *   ③ وآلةُ الحالاتِ الحقيقيةُ قائمةٌ سلفًا في `deduction_proposals`
 *     (Proposed→Reviewed→Approved→Posted→Waived) — لكن **لا مسارَ إنتاجيًّا
 *     يبلّغ Reviewed ولا Approved**؛ `postDeduction` يشترط Approved ولا أحدَ
 *     يصنعها. فالآلةُ بلا وسطٍ، و**لا شاشةَ تقرؤها إطلاقًا**.
 *   ④ و`approval_workflow_rules` مملوءٌ بنصوصِ ملاحظاتِ UAT في خانةِ
 *     `entity_type`/`action`/`role_required` — فكلُّ سلّمٍ يسقط على الاحتياطِ
 *     «خطوةٌ واحدةٌ للسوبر»، ومنشئُ الطلبِ يُعتمَد له تلقائيًّا: **يدٌ واحدةٌ
 *     تمشي السلّمَ كلَّه**.
 *
 * ◆ فالإصلاحُ لا يبني آلةً ثانيةً (سجلّان متنازعان أسوأُ من واحدٍ ضعيف):
 *   ① أعمدةُ **سندِ القرار** في `scr_deductions` — لا أعمدةَ حالةٍ جديدة.
 *   ② **قيدُ CHECK** يجعل الشرطَ بنيويًّا لا تعبديًّا: لا «معتمد» بلا مقترحٍ
 *      ولا طلبِ سلّمٍ مكتملٍ ولا معتمِدٍ **يخالف المنشئ**.
 *   ③ **قواعدُ سلّمٍ حقيقيةٌ** لهذه الشاشة: ثلاثُ خطواتٍ بثلاثةِ أدوار.
 *   ④ وتسجيلُ فعلَي الشاشةِ في `actions` (الحارسُ enforce).
 *
 * ◆ والصفوفُ القائمةُ: أربعةٌ حالتُها «معتمد» — كلُّها `is_seed=1` (بيانُ عرضٍ
 *   مُعلَنٌ). فالقيدُ يستثني المُعلَنَ بذرًا صراحةً **ولا يمسّ صفًّا حيًّا** —
 *   إعلانٌ لا محوٌ (سابقةُ `legacy_no_ref` في M-11).
 *
 * ◆ مُتحمِّلٌ للتكرار: كلُّ إضافةٍ مسبوقةٌ بفحصِ وجودِها.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
/* ◆ گوتشا مسجَّلةٌ ظهرت هنا في موضعٍ جديد: هذه الهجرةُ لا تُدرج `config.php`
     (الذي يُطفئ الرميَ)، فينفُذ افتراضُ PHP 8.1+ — mysqli **ترمي** استثناءً.
     فكلُّ `if (!$db->query(...))` لا يرى false أبدًا، و§⑤ يقوم كلُّه على قراءةِ
     المُرجَع. فيُطفأ الرميُ صراحةً ويُفحَص المُرجَعُ — لا يُفترض أيُّهما. */
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');
$db->query("SET SESSION character_set_connection = 'utf8mb4'");

/** هل العمودُ قائم؟ */
function col_exists(mysqli $db, $table, $col)
{
    $st = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    if (!$st) { fwrite(STDERR, 'col_exists: ' . $db->error . "\n"); exit(1); }
    $st->bind_param('ss', $table, $col);
    $st->execute();
    $n = (int) $st->get_result()->fetch_row()[0];
    $st->close();
    return $n > 0;
}
function chk_exists(mysqli $db, $name)
{
    $st = $db->prepare("SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
                         WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = ?");
    if (!$st) { fwrite(STDERR, 'chk_exists: ' . $db->error . "\n"); exit(1); }
    $st->bind_param('s', $name);
    $st->execute();
    $n = (int) $st->get_result()->fetch_row()[0];
    $st->close();
    return $n > 0;
}
function fk_exists(mysqli $db, $name)
{
    $st = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                         WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = ?
                           AND CONSTRAINT_TYPE = 'FOREIGN KEY'");
    $st->bind_param('s', $name);
    $st->execute();
    $n = (int) $st->get_result()->fetch_row()[0];
    $st->close();
    return $n > 0;
}
function run(mysqli $db, $sql, $what)
{
    if (!$db->query($sql)) { fwrite(STDERR, "{$what}: " . $db->error . "\n"); exit(1); }
    echo "    ✔ {$what}\n";
}

/* ══ ① أعمدةُ سندِ القرار في `scr_deductions` ══════════════════════════════ */
echo "① سندُ القرارِ في scr_deductions:\n";
$ADD = array(
    'proposal_ref'         => "BIGINT(20) UNSIGNED NULL COMMENT 'مقترحُ الخصم — لا خصمَ معتمدًا بلا مقترحه (deduction_proposals.ded_id)'",
    'approval_request_ref' => "INT(11) NULL COMMENT 'طلبُ سلّمِ الموافقاتِ المكتمل (approval_requests.id)'",
    'approved_by'          => "INT(11) NULL COMMENT 'المعتمِدُ — يدٌ ثانيةٌ تخالف المنشئ (users.id)'",
    'approved_at'          => "DATETIME NULL COMMENT 'لحظةُ الاعتماد — يكتبها منفّذُ السلّم لا الشاشة'",
);
foreach ($ADD as $col => $def) {
    if (col_exists($db, 'scr_deductions', $col)) { echo "    · {$col} قائم\n"; continue; }
    run($db, "ALTER TABLE `scr_deductions` ADD COLUMN `{$col}` {$def}", "عمود {$col}");
}
foreach (array('idx_scr_ded_proposal' => 'proposal_ref', 'idx_scr_ded_request' => 'approval_request_ref') as $idx => $on) {
    $st = $db->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS
                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'scr_deductions' AND INDEX_NAME = ?");
    $st->bind_param('s', $idx);
    $st->execute();
    $ex = (int) $st->get_result()->fetch_row()[0];
    $st->close();
    if ($ex > 0) { echo "    · فهرس {$idx} قائم\n"; continue; }
    run($db, "CREATE INDEX `{$idx}` ON `scr_deductions` (`{$on}`)", "فهرس {$idx}");
}

/* المراجعُ قيودًا حقيقيةً — لا عمودًا يشير إلى العدم.
   ◆ گوتشا مقيسةٌ لا منقولة: ماريادي **ترفض قيدَ CHECK على عمودٍ في مفتاحٍ
     أجنبيٍّ بـ`ON UPDATE CASCADE`** — «Function or expression 'proposal_ref'
     cannot be used in the CHECK clause». وجُسّت الحدودُ فردًا فردًا: العمودان
     المقيَّدان وحدَهما يُرفضان، وبتحويلِهما إلى RESTRICT يُقبل القيد.
   ◆ وRESTRICT هو الصحيحُ أصلًا لا حلًّا وسطًا: مرجعُ قرارٍ معتمدٍ لا يُعاد
     كتابتُه تحتَه صامتًا؛ تغييرُ هويةِ المقترحِ يجب أن **يفشل** لا أن يُتابَع. */
if (!fk_exists($db, 'fk_scr_ded_proposal')) {
    run($db, "ALTER TABLE `scr_deductions`
              ADD CONSTRAINT `fk_scr_ded_proposal` FOREIGN KEY (`proposal_ref`)
              REFERENCES `deduction_proposals` (`ded_id`) ON DELETE RESTRICT ON UPDATE RESTRICT",
        'قيدُ مرجعِ المقترح');
} else { echo "    · قيدُ مرجعِ المقترحِ قائم\n"; }
if (!fk_exists($db, 'fk_scr_ded_request')) {
    run($db, "ALTER TABLE `scr_deductions`
              ADD CONSTRAINT `fk_scr_ded_request` FOREIGN KEY (`approval_request_ref`)
              REFERENCES `approval_requests` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT",
        'قيدُ مرجعِ الطلب');
} else { echo "    · قيدُ مرجعِ الطلبِ قائم\n"; }

/* ══ ② القيدُ البنيويّ — «معتمد» لا تُكتب إلا بسندٍ كامل ══════════════════ */
echo "② القيدُ البنيويّ:\n";
/* الصفوفُ الحيةُ المخالفةُ تُقاس **قبلَ** القيد — قيدٌ يفشل نصفَ تطبيقٍ أسوأُ من لا قيد */
$rs = $db->query("SELECT COUNT(*) FROM `scr_deductions`
                   WHERE is_seed = 0 AND status LIKE '%معتمد%'
                     AND (proposal_ref IS NULL OR approval_request_ref IS NULL
                          OR approved_by IS NULL OR created_by IS NULL OR created_by <= 0
                          OR approved_by = created_by)");
if (!$rs) { fwrite(STDERR, 'قياسُ المخالف: ' . $db->error . "\n"); exit(1); }
$viol = (int) $rs->fetch_row()[0];
echo "    · صفوفٌ حيةٌ تخالف القيد: {$viol}\n";
if ($viol > 0) {
    fwrite(STDERR, "توقّف: {$viol} صفًّا حيًّا حالتُه «معتمد» بلا سند. القيدُ لا يُطبَّق فوق\n"
        . "بياناتٍ تخالفه — تُعالَج أولًا بإعلانٍ (لا محوًا) ثم يُعاد التشغيل.\n");
    exit(1);
}
if (!chk_exists($db, 'chk_scr_ded_approved_evidence')) {
    run($db, "ALTER TABLE `scr_deductions` ADD CONSTRAINT `chk_scr_ded_approved_evidence` CHECK (
                  `is_seed` = 1
               OR `status` NOT LIKE '%معتمد%'
               OR (`proposal_ref` IS NOT NULL
                   AND `approval_request_ref` IS NOT NULL
                   AND `approved_by` IS NOT NULL
                   AND `created_by` IS NOT NULL AND `created_by` > 0
                   AND `approved_by` <> `created_by`)
              )", 'قيدُ سندِ الاعتماد');
} else { echo "    · قيدُ سندِ الاعتمادِ قائم\n"; }

/* ══ ②-ب أيدي الآلةِ نفسِها — `deduction_proposals` ═════════════════════════
   الآلةُ تسجّل `reviewed_by` ولا تسجّل من اقترحَ ولا من اعتمد؛ فلا تُقاس
   «يدان مختلفتان» داخلَها إلا بحرفٍ ناقص. والعمودان يُكملان القياس. */
echo "②-ب أيدي مقترحِ الخصم:\n";
$ADD2 = array(
    'proposed_by' => "INT(11) NULL COMMENT 'من اقترحَ الخصم (users.id) — أساسُ منعِ اعتمادِ الذات'",
    'approved_by' => "INT(11) NULL COMMENT 'من اعتمدَه — يدٌ تخالف المراجعَ والمقترح'",
);
foreach ($ADD2 as $col => $def) {
    if (col_exists($db, 'deduction_proposals', $col)) { echo "    · {$col} قائم\n"; continue; }
    run($db, "ALTER TABLE `deduction_proposals` ADD COLUMN `{$col}` {$def}", "عمود {$col}");
}
if (!chk_exists($db, 'chk_ded_prop_two_hands')) {
    /* NULL لا يخالف: البذرُ القديمُ بلا أيدٍ يمضي، والمكتوبان لا يتساويان أبدًا. */
    run($db, "ALTER TABLE `deduction_proposals` ADD CONSTRAINT `chk_ded_prop_two_hands` CHECK (
                  `approved_by` IS NULL OR `proposed_by` IS NULL OR `approved_by` <> `proposed_by`
              )", 'قيدُ يدين مختلفتين في المقترح');
} else { echo "    · قيدُ اليدين قائم\n"; }
if (!chk_exists($db, 'chk_ded_prop_review_hand')) {
    run($db, "ALTER TABLE `deduction_proposals` ADD CONSTRAINT `chk_ded_prop_review_hand` CHECK (
                  `approved_by` IS NULL OR `reviewed_by` IS NULL OR `approved_by` <> `reviewed_by`
              )", 'قيدُ من راجع لا يعتمد');
} else { echo "    · قيدُ المراجعِ قائم\n"; }

/* ══ ③ قواعدُ سلّمِ الموافقاتِ — ثلاثُ خطواتٍ بثلاثةِ أدوار ═══════════════ */
echo "③ سلّمُ الموافقات:\n";
/* الأدوارُ مرآةُ أعمدةِ الشاشةِ نفسِها: راجعته الموارد ← 4 · اعتماد الإدارة ← 1
   · الاعتماد المالي ← 19. والسوبرُ يطابق أيَّ خطوةٍ بحكمِ المحرّك، وقاعدةُ
   «لا يدَ تمشي خطوتين» تمنعه من إكمالِ السلّمِ وحدَه. */
$STEPS = array(
    1 => array('4',  'مراجعةُ الموارد البشرية'),
    2 => array('1',  'اعتمادُ الإدارة المختصة'),
    3 => array('19', 'الاعتمادُ المالي'),
);
$ENTITY = 'scr_deductions';
$ACTION = 'approve';
foreach ($STEPS as $order => $spec) {
    $st = $db->prepare("SELECT COUNT(*) FROM approval_workflow_rules
                         WHERE entity_type = ? AND action = ? AND step_order = ?");
    $st->bind_param('ssi', $ENTITY, $ACTION, $order);
    $st->execute();
    $ex = (int) $st->get_result()->fetch_row()[0];
    $st->close();
    if ($ex > 0) { echo "    · الخطوةُ {$order} مسجَّلة\n"; continue; }
    $st = $db->prepare("INSERT INTO approval_workflow_rules
        (entity_type, action, role_required, step_order, is_active, created_at)
        VALUES (?, ?, ?, ?, 1, NOW())");
    if (!$st) { fwrite(STDERR, 'prepare rule: ' . $db->error . "\n"); exit(1); }
    $st->bind_param('sssi', $ENTITY, $ACTION, $spec[0], $order);
    if (!$st->execute()) { fwrite(STDERR, 'insert rule: ' . $st->error . "\n"); exit(1); }
    $st->close();
    echo "    ✔ خطوةٌ {$order}: {$spec[1]} (دور {$spec[0]})\n";
}

/* ══ ④ تسجيلُ فعلَي الشاشةِ — الحارسُ enforce يحجب غيرَ المسجَّل ══════════ */
echo "④ الأفعال:\n";
$rs = $db->query("SELECT id FROM modules WHERE code = 'Workforce/deductions.php' LIMIT 1");
$mrow = $rs ? $rs->fetch_row() : null;
$moduleId = $mrow ? (int) $mrow[0] : null;
echo '    · الوحدة: ' . ($moduleId !== null ? '#' . $moduleId : 'غير مسجَّلة') . "\n";
$GUARDS = json_encode(array('session', 'action_permission', 'tenant_isolation', 'csrf'));
$ACTS = array(
    array('screen.workforce.deduction.request_approval', 'طلبُ اعتمادِ قرارِ خصم', 0),
    array('screen.workforce.deduction.approve_step',     'اعتمادُ خطوةٍ في سلّمِ الخصم', 1),
);
foreach ($ACTS as $a) {
    $st = $db->prepare('SELECT COUNT(*) FROM actions WHERE action_code = ?');
    $st->bind_param('s', $a[0]);
    $st->execute();
    $ex = (int) $st->get_result()->fetch_row()[0];
    $st->close();
    if ($ex > 0) { echo "    · {$a[0]} مسجَّل\n"; continue; }
    $st = $db->prepare("INSERT INTO actions
        (action_code, name_ar, module_id, placement, handler_path, is_write, guards_json,
         is_financial, owner_doc, active, created_at)
        VALUES (?, ?, ?, 'row', 'Workforce/deductions.php', 1, ?, ?, 'INJAZ-FRD-01 §11-3', 1, NOW())");
    if (!$st) { fwrite(STDERR, 'prepare action: ' . $db->error . "\n"); exit(1); }
    $st->bind_param('ssisi', $a[0], $a[1], $moduleId, $GUARDS, $a[2]);
    if (!$st->execute()) { fwrite(STDERR, 'insert action: ' . $st->error . "\n"); exit(1); }
    $st->close();
    echo "    ✔ فعلٌ: {$a[0]}\n";
}

/* ══ ⑤ بلوغُ السلّم — قفلٌ بلا مفتاحٍ ليس حوكمةً ═════════════════════════════
 * ◆ قِيس بعد بناءِ السلّم: خطواتُه الثلاثُ لأدوارٍ **لا تبلغ سطحَ اعتمادٍ**.
 *     · `Workforce/deductions.php`: الدورُ 4 وحدَه يفتحها (1 و19 بلا منح).
 *     · `Approvals/requests.php` (صندوقُ الموافقاتِ العامُّ · الوحدة 402):
 *       ممنوحٌ **قراءةً لـ27 دورًا** و`can_edit` لـ**صفر**، و**بلا بابٍ في قائمةِ
 *       أيِّ دور** — منحٌ بلا باب (صنفُ INJ-0128 نفسُه).
 *     · و`Approvals/approval_api.php` — القناةُ الوحيدةُ للاعتمادِ من الصندوق —
 *       يحرسه `ems_guard_handler('Approvals/hours_approval.php','edit')`:
 *       **حارسٌ باسمِ شاشةٍ لا تملكه**. والدورُ 19 بلا منحٍ عليها إطلاقًا،
 *       فالخطوةُ الثالثةُ (الاعتمادُ المالي) كانت **مسدودةً بنيويًّا**.
 * ◆ فسلّمُ الثلاثِ خطواتٍ كان سيتوقف عند الثانية إلى الأبد. والمنحُ أدناه
 *   **اتحادٌ لا استبدال**: من يملك `edit` على 247 اليومَ (1·2·3·4) يملكه على
 *   402، ويُضاف 19 — فلا دورَ يفقد ما كان له، وواحدٌ ينال ما كان محجوبًا.
 * ◆ والبابُ في مجموعةِ المالكِ `n9o_` وحدَها: `nav09_verify` يقابل `n9s%`
 *   بالوثيقةِ فيرسب على مجموعةٍ ليست فيها، و`sweep_others` يكنس ما عداهما. */
echo "⑤ بلوغُ السلّم:\n";
$INBOX = 'Approvals/requests.php';
$rs = $db->query("SELECT id FROM modules WHERE code = '{$INBOX}' LIMIT 1");
$mrow = $rs ? $rs->fetch_row() : null;
if (!$mrow) { fwrite(STDERR, "صندوقُ الموافقاتِ غيرُ مسجَّلٍ في modules — لا يُفتح بابٌ لمجهول\n"); exit(1); }
$inboxId = (int) $mrow[0];

$rs = $db->query("SELECT GROUP_CONCAT(role_id) FROM role_permissions WHERE module_id = 247 AND can_edit = 1");
$cur = $rs ? (string) $rs->fetch_row()[0] : '';
$GRANT = array_values(array_unique(array_filter(array_map('intval', explode(',', $cur)))));
foreach (array(4, 1, 19) as $r) { if (!in_array($r, $GRANT, true)) { $GRANT[] = $r; } }
sort($GRANT);
echo '    · المنحُ اتحادًا: ' . implode('، ', $GRANT) . "\n";

foreach ($GRANT as $r) {
    $st = $db->prepare('SELECT can_view, can_edit FROM role_permissions WHERE module_id = ? AND role_id = ?');
    $st->bind_param('ii', $inboxId, $r);
    $st->execute();
    $p = $st->get_result()->fetch_assoc();
    $st->close();
    if ($p && (int) $p['can_edit'] === 1) { echo "    · الدور {$r}: منحُ الفعلِ قائم\n"; continue; }
    if ($p) {
        $st = $db->prepare('UPDATE role_permissions SET can_view = 1, can_edit = 1 WHERE module_id = ? AND role_id = ?');
        $st->bind_param('ii', $inboxId, $r);
    } else {
        $st = $db->prepare('INSERT INTO role_permissions (module_id, role_id, can_view, can_edit) VALUES (?, ?, 1, 1)');
        $st->bind_param('ii', $inboxId, $r);
    }
    if (!$st->execute()) { fwrite(STDERR, 'منح: ' . $st->error . "\n"); exit(1); }
    $st->close();
    echo "    ✔ الدور {$r}: قراءةٌ وفعلٌ على صندوقِ الموافقات\n";
}

/* البابُ في القائمة — لا منحَ بلا بابٍ (المنحُ الأعمى أسوأُ من المنع) */
foreach ($GRANT as $r) {
    $st = $db->prepare('SELECT COUNT(*) FROM nav_items WHERE role_id = ? AND route = ?');
    $st->bind_param('is', $r, $INBOX);
    $st->execute();
    if ((int) $st->get_result()->fetch_row()[0] > 0) { $st->close(); echo "    · الدور {$r}: بابٌ قائم\n"; continue; }
    $st->close();

    $st = $db->prepare("SELECT id FROM link_groups WHERE group_code LIKE 'n9o\\_%' AND owner_role_id = ?
                          AND stage_no < 90 ORDER BY stage_no LIMIT 1");
    $st->bind_param('i', $r);
    $st->execute();
    $g = $st->get_result()->fetch_row();
    $st->close();
    if ($g) {
        $gid = (int) $g[0];
    } else {
        /* مرحلةُ المجموعةِ بعد أعلى مرحلةٍ حقيقيةٍ للدور (99 دلوُ «أخرى» لا مرحلة) */
        $st = $db->prepare('SELECT COALESCE(MAX(lg.stage_no), 0) FROM link_groups lg
                             JOIN nav_items ni ON ni.group_id = lg.id
                            WHERE ni.role_id = ? AND lg.stage_no < 90');
        $st->bind_param('i', $r);
        $st->execute();
        $stage = ((int) $st->get_result()->fetch_row()[0]) + 1;
        $st->close();
        $code = 'n9o_' . $r . '_' . $stage;
        $st = $db->prepare("INSERT INTO link_groups
            (name, group_code, owner_role_id, icon, display_order, stage_no, stage_title, is_active)
            VALUES (?, ?, ?, 'fa fa-list-check', 810, ?, ?, 1)");
        if (!$st) { fwrite(STDERR, 'prepare group: ' . $db->error . "\n"); exit(1); }
        $nm = '— بقرار المالك';
        $stt = 'صندوقُ الموافقات';
        $st->bind_param('ssiis', $nm, $code, $r, $stage, $stt);
        if (!$st->execute()) { fwrite(STDERR, 'insert group: ' . $st->error . "\n"); exit(1); }
        $gid = (int) $st->insert_id;
        $st->close();
        echo "    ✔ مجموعةُ مالكٍ جديدة #{$gid} ({$code}) للدور {$r}\n";
    }

    $st = $db->prepare("INSERT INTO nav_items
        (role_id, group_id, module_id, label_ar, route, icon, sort_order, active)
        VALUES (?, ?, ?, 'صندوقُ موافقاتي — ما ينتظر يدي', ?, 'fa fa-list-check', 900, 1)");
    if (!$st) { fwrite(STDERR, 'prepare nav: ' . $db->error . "\n"); exit(1); }
    $st->bind_param('iiis', $r, $gid, $inboxId, $INBOX);
    if (!$st->execute()) { fwrite(STDERR, 'insert nav: ' . $st->error . "\n"); exit(1); }
    $st->close();
    echo "    ✔ الدور {$r}: بابٌ إلى صندوقِ الموافقات (مجموعة #{$gid})\n";
}

/* ══ إثباتٌ وظيفيّ: القيدُ يرفض فعلًا (لا يكفي وجودُه في information_schema) ══ */
echo "⑥ إثباتُ الرفض — قيدٌ لا يُجَسُّ ادعاءٌ:\n";
$db->query('START TRANSACTION');
$probeOk = false;
$ins = $db->query("INSERT INTO `scr_deductions`
    (company_id, no_decision, status, is_seed, created_by, created_by_name, created_at, updated_at)
    VALUES (4, 'PROBE-INJ0219', 'معتمد', 0, 7, 'مِسبار', NOW(), NOW())");
if ($ins === false) {
    $probeOk = true;
    echo '    ✔ رُفض ميلادُ صفٍّ «معتمد» بلا سند — ' . mb_substr($db->error, 0, 70) . "\n";
} else {
    echo "    ✘ نفذَ الإدراجُ! القيدُ لا يمنع\n";
}
$db->query('ROLLBACK');
if (!$probeOk) { fwrite(STDERR, "القيدُ موجودٌ ولا يمنع — لا يُعلن نجاحٌ\n"); exit(1); }

/* والوجهُ الموجب: صفٌّ بسندٍ كاملٍ يُقبل — قيدٌ يمنع الصحيحَ عطلٌ آخر */
$db->query('START TRANSACTION');
$rs = $db->query('SELECT ded_id FROM deduction_proposals LIMIT 1');
$ded = $rs ? $rs->fetch_row() : null;
$rs = $db->query('SELECT id FROM approval_requests LIMIT 1');
$req = $rs ? $rs->fetch_row() : null;
if ($ded && $req) {
    $ok = $db->query("INSERT INTO `scr_deductions`
        (company_id, no_decision, status, is_seed, created_by, created_by_name,
         proposal_ref, approval_request_ref, approved_by, approved_at, created_at, updated_at)
        VALUES (4, 'PROBE-INJ0219-OK', 'معتمد', 0, 7, 'مِسبار',
                " . (int) $ded[0] . ', ' . (int) $req[0] . ", 9, NOW(), NOW(), NOW())");
    echo '    ' . ($ok ? '✔ قُبل صفٌّ بسندٍ كاملٍ ويدين مختلفتين' : '✘ رُفض الصحيحُ — ' . mb_substr($db->error, 0, 70)) . "\n";
    if (!$ok) { $db->query('ROLLBACK'); fwrite(STDERR, "القيدُ يمنع الصحيح\n"); exit(1); }
} else { echo "    · لا مقترحَ/طلبَ للجسِّ الموجب — تُخطّي\n"; }
$db->query('ROLLBACK');

echo "\n  الشاهد: php tools/fix_inj0219_tests.php · php tools/fix_od19_probe.php\n";
exit(0);
