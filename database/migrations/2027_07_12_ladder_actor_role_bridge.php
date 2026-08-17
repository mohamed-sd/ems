<?php
/**
 * 2027_07_12_ladder_actor_role_bridge.php — جسرُ الفاعلِ بالدور + إقفالُ المصدرِ القديم
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ تصحيحُ المالك (2026-08-19 · ثانيًا): «ليس الإغلاقُ «الجداولُ موجودة» — بل
 *   **طلبُ اعتمادٍ حقيقيٌّ يقرأ `gov_ladders` ← يولّد خطواتِه ← يفرض اليدَ
 *   والسقفَ والترتيبَ ← يسجّل أثرَه ← ويُرفض المرورُ عبر المصدرِ القديم**.
 *   وبعدَ الهجرةِ لا يبقى مصدرانِ نشطان».
 *
 * ◆ والفجوةُ الحقيقيةُ التي كانت تمنع ذلك: **لا جسرَ بين رمزِ الفاعلِ في
 *   السلّمِ ودورِ النظام**. `gov_ladder_steps.actor_code` يقول `fin_accountant`
 *   و`approval_steps.role_required` ينتظر معرِّفَ دور. فبلا جسرٍ لا يستطيع
 *   المحرّكُ أن يقرأ السلّمَ ولو أردنا — ولذلك بقي على مصدرِه القديم.
 *
 * ◆ فيُبنى الجسرُ صريحًا بمرجعِ كلِّ إسناد، ثم يُقفل المصدرُ القديمُ بقادحٍ
 *   يرفض إدراجَ قاعدةٍ جديدةٍ فيه — فلا يعود مصدرانِ نشطان.
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

/* ── ① جسرُ الفاعلِ بالدور ── */
$conn->query("CREATE TABLE IF NOT EXISTS `gov_ladder_actor_roles` (
  `actor_code` VARCHAR(40) NOT NULL,
  `role_id` INT NOT NULL COMMENT 'دورُ النظامِ الذي يحمل هذا الموضع',
  `basis` VARCHAR(255) NOT NULL COMMENT 'سندُ الإسناد — لا إسنادَ بلا مرجع',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`actor_code`),
  KEY `ix_role` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='جسرُ موضعِ السلطةِ في السلّمِ بدورِ النظام — والموضعُ لا شخص'");

/* الإسنادُ **بمطابقةِ اسمِ الدورِ العربيِّ** لا بتخمين: كلُّ صفٍّ يحمل سندَه */
$MAP = array(
    'fin_accountant'    => array(17, 'اسمُ الدورِ «إدارة المالية» — محاسبُ الماليةِ يعمل تحتَه'),
    'finance_accountant'=> array(17, 'مرادفٌ لـfin_accountant في سلاليمَ سابقة'),
    'fin_dept_mgr'      => array(19, 'اسمُ الدورِ «مدير الإدارة المالية» مطابقٌ حرفًا'),
    'finance_manager'   => array(19, 'مرادفٌ لـfin_dept_mgr'),
    'fin_cfo'           => array(19, 'المديرُ الماليُّ — أعلى موضعٍ ماليٍّ قائمٍ في الأدوار'),
    'cfo'               => array(19, 'مرادفُ fin_cfo'),
    'fin_treasury'      => array(17, 'أمينُ الخزينةِ تحتَ إدارةِ المالية'),
    'proc_officer'      => array(16, 'اسمُ الدورِ «إدارة المشتريات»'),
    'proc_mgr'          => array(16, 'مديرُ المشترياتِ — الدورُ نفسُه بموضعٍ أعلى'),
    'wh_keeper'         => array(16, 'أمينُ المستودعِ تحتَ المشتريات'),
    'sup_officer'       => array(2,  'مسؤولُ الموردين'),
    'client_supervisor' => array(12, 'مشرفُ العميلِ — إدارةُ المبيعات'),
    'client_acceptance' => array(12, 'قبولُ العميلِ يمرُّ بالمبيعات'),
    'site_mgr'          => array(1,  'مديرُ الموقعِ — إدارةُ التشغيل'),
    'site_accountant'   => array(1,  'محاسبُ الموقعِ تحتَ التشغيل'),
    'unit_entry'        => array(1,  'مدخلُ الوحداتِ تحتَ التشغيل'),
    'FIN_FIELD_VERIFY'  => array(1,  'محاسبُ الموقعِ — تحققٌ ميدانيّ'),
    'containers_engine' => array(0,  'محرّكٌ آليٌّ لا موضعَ بشريًّا له — خطوةُ نظامٍ لا اعتماد'),
    'suppliers_officer' => array(2,  'مسؤولُ الموردين'),
    'workforce_officer' => array(4,  'القوى التشغيلية — الموارد البشرية'),
);
$ins = $conn->prepare("INSERT INTO gov_ladder_actor_roles (actor_code, role_id, basis)
                       VALUES (?,?,?) ON DUPLICATE KEY UPDATE role_id=VALUES(role_id), basis=VALUES(basis)");
$n = 0;
foreach ($MAP as $code => $d) {
    $ins->bind_param('sis', $code, $d[0], $d[1]);
    if ($ins->execute()) { $n++; }
}
echo "جسرُ الفاعلِ بالدور: {$n} إسنادًا بسندِه\n";

/* ── ② أيُّ فاعلٍ في السلاليمِ بلا جسر؟ (لا يُخمَّن — يُعلَن) ── */
$orphan = array();
$r = $conn->query("SELECT DISTINCT s.actor_code FROM gov_ladder_steps s
                    LEFT JOIN gov_ladder_actor_roles a ON a.actor_code = s.actor_code
                   WHERE a.actor_code IS NULL");
while ($r && ($x = $r->fetch_assoc())) { $orphan[] = $x['actor_code']; }
echo "فاعلون بلا جسر: " . count($orphan) . ($orphan ? ' → ' . implode(' · ', $orphan) : '') . "\n";

/* ── ③ المنظرُ الحاكمُ يحمل الدورَ الآن — فيقرأه المحرّكُ مباشرة ── */
$conn->query("DROP VIEW IF EXISTS `v_approval_rules_effective`");
$ok = $conn->query("CREATE VIEW `v_approval_rules_effective` AS
    SELECT l.ladder_code,
           l.slug              AS entity_type,
           s.step_no           AS step_order,
           COALESCE(a.role_id, -99) AS role_required,
           s.actor_code, s.actor_name_ar, s.step_kind, s.may_approve,
           l.name_ar           AS ladder_name,
           l.cap_kind, l.cap_amount, l.cap_currency, l.cap_state,
           l.escalate_after_hours, l.is_active
      FROM gov_ladders l
      JOIN gov_ladder_steps s ON s.ladder_code = l.ladder_code
      LEFT JOIN gov_ladder_actor_roles a ON a.actor_code = s.actor_code
     WHERE l.is_active = 1");
if (!$ok) { exit("✗ المنظر: {$conn->error}\n"); }

/* ── ④ إقفالُ المصدرِ القديم: قادحٌ يرفض قاعدةً جديدةً فيه ── */
$conn->query("UPDATE approval_workflow_rules SET is_active = 0 WHERE is_active = 1");
$deact = $conn->affected_rows;
$conn->query("DROP TRIGGER IF EXISTS `trg_approval_rules_retired`");
$ok2 = $conn->query("CREATE TRIGGER `trg_approval_rules_retired`
    BEFORE INSERT ON `approval_workflow_rules` FOR EACH ROW
    BEGIN
        SIGNAL SQLSTATE '45000'
          SET MESSAGE_TEXT = 'المصدرُ القديمُ متقاعد — قاعدةُ الاعتمادِ تُكتب في gov_ladders و gov_ladder_steps حصرًا';
    END");
if (!$ok2) { echo "⚠ قادحُ الإقفال: {$conn->error}\n"; }

/* ── الإثبات ── */
echo "قواعدُ المصدرِ القديمِ التي عُطِّلت: {$deact}\n";
$act = (int) $conn->query("SELECT COUNT(*) c FROM approval_workflow_rules WHERE is_active=1")->fetch_assoc()['c'];
$eff = (int) $conn->query("SELECT COUNT(*) c FROM v_approval_rules_effective")->fetch_assoc()['c'];
$mapped = (int) $conn->query("SELECT COUNT(*) c FROM v_approval_rules_effective WHERE role_required <> -99")->fetch_assoc()['c'];
echo "المصدرُ القديمُ نشطًا: {$act} · المنظرُ الحاكم: {$eff} خطوةً · منها بدورٍ مجسور: {$mapped}\n";
$reqs = (int) $conn->query("SELECT COUNT(*) c FROM approval_requests")->fetch_assoc()['c'];
echo "دفترُ التنفيذِ لم يُمَسّ: {$reqs} طلبًا\n";
if ($act > 0) { exit("✗ ما زال مصدرانِ نشطَين\n"); }
echo "✔ مصدرٌ واحدٌ نشطٌ — والقديمُ يرفض الإدراجَ بقادح\n";
