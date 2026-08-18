<?php
/**
 * 2027_07_06_two_axis_decision_application.php — محورا القرارِ والتطبيقِ (ف١٣ الجديد)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ تعديلُ المالكِ (2026-08-18 · أولًا): «أوقفْ ترقيةَ PENDING_OWNER إلى APPROVED
 *   بالصمت. **غيابُ القرارِ ليس قرارًا.** وافصلْ محورَين بدلَ حشرِ المعاني في
 *   تعدادٍ واحد».
 *
 * ◆ والحكمُ القديمُ لا يُمحى — يُسجَّل بتاريخِ تعديلِه وسببِه في
 *   `gov_policy_changes` أدناه. وما كان مسلَّحًا فعلًا: كنّاسُ الصمتِ
 *   (`tools/uxui_pending_sweep.php`) كان سيطلق في **2026-08-21 11:42:59**
 *   فيرقّي 78 صفًّا إلى APPROVED — نُزع سلاحُه قبلَ موعدِه.
 *
 * ◆ المحوران:
 *   ① حالةُ القرار (decision_state): من يقرّر وهل قرَّر
 *      PENDING_OWNER ← OWNER_REVIEW_OVERDUE ← APPROVED | REJECTED
 *      و`decided_by = NULL` ما لم يوجد فاعلٌ حقيقيّ — فلا يُنسب قرارٌ لأحد.
 *   ② حالةُ التطبيق (application_state): ما يراه المستخدمُ فعلًا
 *      CURRENT ← PROVISIONALLY_APPLIED_NO_OBJECTION ← DEPLOYED | ROLLED_BACK
 *      فالمقترحُ يُطبَّق **تطبيقًا مؤقَّتًا قابلًا للعكس** وسجلُّ القرارِ يبقى
 *      صادقًا أنه لم يُحسَم. والتقريرُ يعدُّهما **منفصلَين** أبدًا.
 *
 * ◆ ونطاقُ التطبيقِ المؤقَّتِ **محصورٌ بنيويًّا** في التنقلِ والتسميةِ والموضعِ
 *   لأنها تُعكس. ولا يُطبَّق بلا اعتراضٍ في: الصلاحياتِ · سلاليمِ الاعتمادِ ·
 *   السقوفِ الماليةِ · فصلِ الواجباتِ · الالتزاماتِ القانونيةِ · القراراتِ
 *   المالية. والحصرُ مفروضٌ بقادحٍ لا بتوصيةٍ (أدناه).
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$col = function ($t, $c) use ($conn) {
    $r = $conn->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
                         AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}'");
    return $r && $r->num_rows > 0;
};

/* ══ ⓪ سجلُّ تغييرِ السياسة — الحكمُ القديمُ يُسجَّل ولا يُمحى ══ */
$conn->query("CREATE TABLE IF NOT EXISTS `gov_policy_changes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `policy_key` VARCHAR(80) NOT NULL,
  `old_rule` TEXT NOT NULL COMMENT 'نصُّ الحكمِ السابقِ كما كان — لا يُمحى',
  `new_rule` TEXT NOT NULL,
  `changed_on` DATE NOT NULL,
  `reason` TEXT NOT NULL COMMENT 'سببُ التعديلِ بنصِّ صاحبِ القرار',
  `source_ref` VARCHAR(120) NOT NULL COMMENT 'مرجعُ الوثيقةِ والفصل',
  `recorded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_key_date` (`policy_key`, `changed_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='تاريخُ الأحكامِ الحاكمة — القديمُ يبقى بتاريخِه وسببِه'");

$ins = $conn->prepare("INSERT INTO gov_policy_changes (policy_key, old_rule, new_rule, changed_on, reason, source_ref)
                       SELECT ?,?,?,?,?,? FROM DUAL
                        WHERE NOT EXISTS (SELECT 1 FROM gov_policy_changes WHERE policy_key = ? AND changed_on = ?)");
$k1 = 'pending_owner_closure';
$old1 = 'من لم يرد خلال ثلاثة أيام يُعتمد المقترح تلقائيًا بوسم «auto-approved by silence» — ويُرقَّى صفُّه في nav_canonical إلى APPROVED فيُولَّد في الإنتاج.';
$new1 = 'الصمتُ لا يُرقِّي قرارًا. المهلةُ تنقل حالةَ القرارِ إلى OWNER_REVIEW_OVERDUE ثم تُصعِّد للنائبِ يومَين، ثم يُطبَّق المقترحُ تطبيقًا مؤقَّتًا قابلًا للعكس (application_state = PROVISIONALLY_APPLIED_NO_OBJECTION) وسجلُّ القرارِ يبقى غيرَ محسوم و decided_by = NULL.';
$d1 = '2026-08-18';
$r1 = 'غيابُ القرارِ ليس قرارًا — وحشرُ «طُبِّق» و«اعتُمد» في تعدادٍ واحدٍ يجعل السجلَّ يكذب. والفصلُ يُبقي الواجهةَ متقدمةً والسجلَّ صادقًا.';
$s1 = 'INJAZ-UXUI-01 · الفصل 13 (تعديل 2026-08-18) · أولًا';
$ins->bind_param('ssssssss', $k1, $old1, $new1, $d1, $r1, $s1, $k1, $d1);
$ins->execute();

$k2 = 'golden_screen_promotion';
$old2 = 'شرطُ التعميمِ توقيعُ المالكِ شاشةً شاشةً في gov_golden_approvals.';
$new2 = 'شرطُ التعميمِ بوابةُ ترقيةٍ موضوعيةٌ بتسعةِ فحوصٍ ⇒ VISUAL_PATTERN_APPROVED. والاختبارُ البشريُّ وجولةُ العرضِ (٨ و٩) ينفّذهما شخصٌ مستقلٌّ عن الباني. وGOLDEN_SCREEN_FINAL لا يُمنح إلا بعد استقرارِ آلةِ الحالةِ ومحرّكِ الاعتمادِ والظهورِ بالدورِ والحرّاس.';
$r2 = 'التوقيعُ الواحدُ عنقُ زجاجةٍ يوقف المشروع؛ والبوابةُ الموضوعيةُ تفحص ما كان التوقيعُ يفترضه. ولئلا نستبدل «توقيعَ المالك» بـ«مصادقةِ الصانعِ على صنعتِه» فُصل منفِّذُ البندَين ٨ و٩ عن الباني.';
$s2 = 'INJAZ-UXUI-01 · الفصل 13 (تعديل 2026-08-18) · ثالثًا';
$ins->bind_param('ssssssss', $k2, $old2, $new2, $d1, $r2, $s2, $k2, $d1);
$ins->execute();
echo "سجلُّ تغييرِ السياسة: " . (int) $conn->query("SELECT COUNT(*) c FROM gov_policy_changes")->fetch_assoc()['c'] . " حكمًا مؤرَّخًا\n";

/* ══ ① المحوران في سجلِّ التنقلِ المعياري ══ */
if (!$col('nav_canonical', 'decision_state')) {
    $conn->query("ALTER TABLE nav_canonical
        ADD `decision_state` ENUM('PENDING_OWNER','OWNER_REVIEW_OVERDUE','APPROVED','REJECTED') NULL
            COMMENT 'محورُ القرار: هل حُسم ومن حسمه — والصمتُ لا يُرقّيه' AFTER `status`,
        ADD `application_state` ENUM('CURRENT','PROVISIONALLY_APPLIED_NO_OBJECTION','DEPLOYED','ROLLED_BACK')
            NOT NULL DEFAULT 'CURRENT'
            COMMENT 'محورُ التطبيق: ما يراه المستخدمُ فعلًا — مستقلٌّ عن محورِ القرار' AFTER `decision_state`,
        ADD `decided_by` INT NULL COMMENT 'الفاعلُ الحقيقيُّ وحدَه — NULL ما لم يوجد' AFTER `application_state`,
        ADD `decided_at` DATETIME NULL AFTER `decided_by`,
        ADD `provisional_since` DATETIME NULL COMMENT 'متى بدأ التطبيقُ المؤقَّت' AFTER `decided_at`,
        ADD `provisional_reversible` TINYINT(1) NOT NULL DEFAULT 1
            COMMENT 'حصرُ النطاق: 1 = تسميةٌ/موضعٌ يُعكسان · 0 يمنع التطبيقَ المؤقَّت' AFTER `provisional_since`");
}

/* الاشتقاقُ من الحالةِ القائمةِ — بلا اختراعِ قرار */
$conn->query("UPDATE nav_canonical SET decision_state = 'APPROVED'
               WHERE status = 'APPROVED' AND decision_state IS NULL");
$conn->query("UPDATE nav_canonical SET application_state = 'DEPLOYED'
               WHERE status = 'APPROVED'");
$conn->query("UPDATE nav_canonical SET decision_state = 'PENDING_OWNER'
               WHERE status IN ('PENDING_OWNER','PENDING_OWNER_MERGE') AND decision_state IS NULL");
$conn->query("UPDATE nav_canonical SET application_state = 'CURRENT'
               WHERE status IN ('PENDING_OWNER','PENDING_OWNER_MERGE')");
/* لا فاعلَ ⇒ لا نسبةَ قرارٍ لأحد */
$conn->query("UPDATE nav_canonical SET decided_by = NULL WHERE decided_by = 0");

/* ══ ② المحوران في دفترِ جلسةِ الإغلاق ══ */
if (!$col('nav_pending_closure', 'decision_state')) {
    $conn->query("ALTER TABLE nav_pending_closure
        ADD `decision_state` ENUM('PENDING_OWNER','OWNER_REVIEW_OVERDUE','APPROVED','REJECTED')
            NOT NULL DEFAULT 'PENDING_OWNER' AFTER `decision`,
        ADD `application_state` ENUM('CURRENT','PROVISIONALLY_APPLIED_NO_OBJECTION','DEPLOYED','ROLLED_BACK')
            NOT NULL DEFAULT 'CURRENT' AFTER `decision_state`,
        ADD `escalated_at` DATETIME NULL COMMENT 'وقتُ التصعيدِ للنائبِ المختص' AFTER `application_state`,
        ADD `escalated_to_role` INT NULL COMMENT 'موضعُ السلطةِ لا اسمُ شخص' AFTER `escalated_at`,
        ADD `escalation_due_at` DATETIME NULL COMMENT 'مهلةُ النائبِ: يومان' AFTER `escalated_to_role`,
        ADD `provisional_since` DATETIME NULL AFTER `escalation_due_at`,
        ADD `rolled_back_at` DATETIME NULL AFTER `provisional_since`");
}
$conn->query("UPDATE nav_pending_closure SET decision_state='PENDING_OWNER' WHERE decision='pending'");
/* أثرُ الحكمِ القديمِ إن كان قد وقع — يُعاد إلى الصدقِ ولا يُمحى أثرُه من السجل */
$fixed = 0;
$conn->query("UPDATE nav_pending_closure
                 SET decision_state = 'OWNER_REVIEW_OVERDUE',
                     application_state = 'PROVISIONALLY_APPLIED_NO_OBJECTION',
                     decided_by = NULL,
                     modification_note = CONCAT(COALESCE(modification_note,''),
                        ' | صُحِّح 2026-08-18: الصمتُ لا يُرقِّي قرارًا — التطبيقُ مؤقَّتٌ والقرارُ غيرُ محسوم')
               WHERE decision = 'auto_approved_by_silence'");
$fixed = $conn->affected_rows;
$conn->query("UPDATE nav_canonical
                 SET decision_state = 'OWNER_REVIEW_OVERDUE',
                     application_state = 'PROVISIONALLY_APPLIED_NO_OBJECTION',
                     decided_by = NULL,
                     provisional_since = NOW()
               WHERE derivation LIKE '%auto-approved by silence%'");
$fixed += $conn->affected_rows;

/* ══ ③ حصرُ النطاقِ بقادحٍ لا بتوصية ══
   التطبيقُ المؤقَّتُ لا يُسمح به إلا لصفٍّ معلَنٍ قابلَ العكس. */
$conn->query("DROP TRIGGER IF EXISTS `trg_nav_provisional_scope`");
$ok = $conn->query("CREATE TRIGGER `trg_nav_provisional_scope`
    BEFORE UPDATE ON `nav_canonical` FOR EACH ROW
    BEGIN
        IF NEW.application_state = 'PROVISIONALLY_APPLIED_NO_OBJECTION'
           AND NEW.provisional_reversible <> 1 THEN
            SIGNAL SQLSTATE '45000'
              SET MESSAGE_TEXT = 'التطبيقُ المؤقَّتُ محصورٌ في التسميةِ والموضعِ — ولا يُطبَّق بلا اعتراضٍ في الصلاحياتِ والسلاليمِ والسقوفِ وفصلِ الواجبات';
        END IF;
        IF NEW.decision_state IN ('APPROVED','REJECTED') AND NEW.decided_by IS NULL THEN
            SIGNAL SQLSTATE '45000'
              SET MESSAGE_TEXT = 'قرارٌ بلا فاعلٍ مرفوض — لا يُنسب حسمٌ إلى أحدٍ بالصمت';
        END IF;
    END");
if (!$ok) { echo "⚠ القادحُ لم يُنشأ: {$conn->error}\n"; }

/* ══ ④ أساسُ الاعتمادِ في سجلِّ الذهبية ══ */
if (!$col('gov_golden_approvals', 'approval_basis')) {
    $conn->query("ALTER TABLE gov_golden_approvals
        ADD `approval_basis` ENUM('OBJECTIVE_GATE','HUMAN_REVIEW','OWNER_OVERRIDE') NULL
            COMMENT 'كيفَ اعتُمد: بوابةٌ موضوعيةٌ · مراجعةٌ بشريةٌ مستقلةٌ · تجاوزُ المالك' AFTER `state`,
        ADD `basis_ref` VARCHAR(190) NULL COMMENT 'مرجعُ الأساس: تقريرُ البوابةِ أو محضرُ المراجعةِ ومنفِّذُها' AFTER `approval_basis`,
        ADD `pattern_state` ENUM('PENDING','VISUAL_PATTERN_APPROVED','GOLDEN_SCREEN_FINAL','REJECTED')
            NOT NULL DEFAULT 'PENDING'
            COMMENT 'اعتمادٌ على مرحلتَين: النمطُ يفتح التعميمَ · والنهائيُّ بعد (ب) و(ج)' AFTER `basis_ref`,
        ADD `category` VARCHAR(60) NULL COMMENT 'فئةُ التعميم: سجلات · كيانات · نماذج · لوحات · طوابير' AFTER `pattern_state`");
}
/* الفئاتُ من نصِّ المالكِ (رابعًا) — اعتمادُ شاشةٍ يفتح فئتَها وحدَها */
$CAT = array(
    'Contracts/contracts.php'          => 'RECORD_PAGE',
    'Suppliers/supplier_profile.php'   => 'ENTITY_CARD',
    'FinRequests/request_form.php'     => 'FORM',
    'Portal/ceo_board.php'             => 'DASHBOARD',
    'Finance/approvals_inbox.php'      => 'APPROVAL_QUEUE',
    'Portal/my_tasks.php'              => 'DASHBOARD',
    'Operations/sites_board.php'       => 'DASHBOARD',
    'Timesheet/timesheet.php'          => 'RECORD_PAGE',
    'Maintenance/orders.php'           => 'RECORD_PAGE',
    'Risk/risk_register.php'           => 'RECORD_PAGE',
);
$uc = $conn->prepare("UPDATE gov_golden_approvals SET category = ? WHERE screen_file = ?");
foreach ($CAT as $f => $c) { $uc->bind_param('ss', $c, $f); $uc->execute(); }

/* ══ الإثبات ══ */
echo "أثرُ الحكمِ القديمِ المصحَّح: {$fixed} صفًّا\n";
$r = $conn->query("SELECT decision_state, application_state, COUNT(*) n FROM nav_canonical
                    GROUP BY decision_state, application_state ORDER BY decision_state, application_state");
echo "\n▐ المحوران في سجلِّ التنقل\n";
while ($x = $r->fetch_assoc()) {
    printf("  قرار=%-22s تطبيق=%-34s %s\n", $x['decision_state'], $x['application_state'], $x['n']);
}
$noActor = (int) $conn->query("SELECT COUNT(*) c FROM nav_canonical WHERE decision_state IN ('APPROVED','REJECTED') AND decided_by IS NULL")->fetch_assoc()['c'];
echo "\nصفوفٌ محسومةٌ بلا فاعلٍ مسجَّل: {$noActor}";
echo $noActor > 0 ? "  ◆ (موروثةٌ من مصفوفةِ المالكِ v3 — قرارُها في الوثيقةِ لا في الجدول)\n" : "\n";
$r = $conn->query("SELECT pattern_state, COUNT(*) n, COALESCE(approval_basis,'—') b FROM gov_golden_approvals GROUP BY pattern_state, approval_basis");
echo "\n▐ الذهبيةُ بأساسِ اعتمادِها\n";
while ($x = $r->fetch_assoc()) { printf("  %-26s أساس=%-16s %s\n", $x['pattern_state'], $x['b'], $x['n']); }
$cats = (int) $conn->query("SELECT COUNT(*) c FROM gov_golden_approvals WHERE category IS NOT NULL")->fetch_assoc()['c'];
echo "\nشاشاتٌ مسنَدةٌ لفئةِ تعميم: {$cats}/10\n";
echo "✔ المحوران مفصولان · والصمتُ لا يُرقِّي · والقادحُ يمنع قرارًا بلا فاعلٍ وتطبيقًا خارجَ النطاق\n";
