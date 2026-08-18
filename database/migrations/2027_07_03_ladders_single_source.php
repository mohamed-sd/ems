<?php
/**
 * 2027_07_03_ladders_single_source.php — gov_ladders مصدرَ قاعدةٍ وحيدًا (المرحلة ج)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ تفويضُ المالكِ (ج): «وصل محرك الاعتماد بـ gov_ladders مصدرَ قاعدةٍ وحيدًا —
 *   ودفترُ التنفيذِ يبقى بتاريخه».
 * ◆ الحالُ قبل: مصدرانِ متوازيان — `gov_ladders` + `gov_ladder_steps` (السلاليمُ
 *   السبعةُ المحكومةُ بوثيقةِ LAD-01) بلا منفِّذ، و`approval_workflow_rules`
 *   (سلسلةُ المحرّكِ الفعلية) بلا حاكم. فما في الوثيقةِ لا يُنفَّذ، وما يُنفَّذ
 *   لا تحكمه وثيقة.
 * ◆ وما وجدتُه في القاعدةِ يفسّر الصمت: 23 صفَّ قاعدةٍ منها **20 ملوَّثةٌ
 *   ببياناتِ UAT** (نصُّ ملاحظةٍ في خانةِ الرمز — وُسمت «معطَّل» في 2027_01_30
 *   ولم تُنظَّف)، والصحيحُ صفٌّ واحدٌ فعليٌّ هو `scr_deductions`.
 *
 * ما تفعله هذه الهجرة:
 *   ① منظرٌ حاكم `v_approval_rules_effective` يشتقُّ سلسلةَ الاعتمادِ من
 *      `gov_ladder_steps` — فمصدرُ القاعدةِ واحدٌ ولا يُكتب في مكانَين.
 *   ② `approval_workflow_rules` تُوسَم بمصدرِها (`source_ladder`) — وما جاء من
 *      سلّمٍ لا يُحرَّر يدويًّا. والملوَّثُ يُحجَر ثلاثين يومًا **ولا يُحذف**.
 *   ③ قيدُ الثلاثِ خطوات (قرارُ المالك ثانيًا) يُفحَص بمنظرٍ يُظهر أيَّ سلّمٍ
 *      يتجاوزها ⇒ اليومَ صفر.
 *   ④ دفترُ التنفيذِ (`approval_requests`/`approval_steps`) **لا يُمَسّ**:
 *      48 طلبًا قائمًا يبقى بتاريخِه وسلسلتِه كما نُفِّذت.
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

/* ── ② وسمُ المصدرِ على قاعدةِ المحرّك ── */
$has = $conn->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME='approval_workflow_rules' AND COLUMN_NAME='source_ladder'");
if (!$has || $has->num_rows === 0) {
    $conn->query("ALTER TABLE approval_workflow_rules
                  ADD `source_ladder` VARCHAR(12) NULL COMMENT 'رمزُ السلّمِ الحاكمِ في gov_ladders — والصفُّ المشتقُّ لا يُحرَّر يدويًّا'");
}

/* ── حجرُ الصفوفِ الملوَّثةِ ببياناتِ UAT — نسخٌ ثم تعطيلٌ ولا حذف ── */
$conn->query("CREATE TABLE IF NOT EXISTS `approval_rules_quarantine` (
  `id` INT NOT NULL,
  `entity_type` VARCHAR(200) NOT NULL,
  `action` VARCHAR(300) NOT NULL,
  `role_required` VARCHAR(200) NOT NULL,
  `step_order` INT NOT NULL,
  `was_active` TINYINT(1) NOT NULL,
  `quarantined_at` DATETIME NOT NULL,
  `purge_after` DATE NOT NULL,
  `reason` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='حجرُ قواعدِ اعتمادٍ ملوَّثةٍ ببياناتِ UAT — ثلاثون يومًا ثم حذف'");

$purge = date('Y-m-d', strtotime('+30 days'));
$conn->query("INSERT IGNORE INTO approval_rules_quarantine
   (id, entity_type, action, role_required, step_order, was_active, quarantined_at, purge_after, reason)
   SELECT id, entity_type, action, role_required, step_order, is_active, NOW(), '{$purge}',
          'نصُّ ملاحظةٍ في خانةِ الرمز — بياناتُ UAT-2026 لا قاعدةَ اعتماد'
     FROM approval_workflow_rules WHERE entity_type LIKE '%UAT-2026%'");
$q = (int) $conn->query("SELECT COUNT(*) c FROM approval_rules_quarantine")->fetch_assoc()['c'];
$conn->query("UPDATE approval_workflow_rules SET is_active = 0 WHERE entity_type LIKE '%UAT-2026%'");
$stillActive = (int) $conn->query("SELECT COUNT(*) c FROM approval_workflow_rules WHERE entity_type LIKE '%UAT-2026%' AND is_active = 1")->fetch_assoc()['c'];
$totalRows = (int) $conn->query("SELECT COUNT(*) c FROM approval_workflow_rules")->fetch_assoc()['c'];
echo "قواعدُ ملوَّثةٌ محجورةٌ: {$q} · ما زال نشطًا منها: {$stillActive} · وصفوفُ الجدولِ كلُّها باقية: {$totalRows}\n";

/* ── ① المنظرُ الحاكم: السلسلةُ من السلاليمِ لا من جدولٍ ثانٍ ── */
$conn->query("DROP VIEW IF EXISTS `v_approval_rules_effective`");
$ok = $conn->query("CREATE VIEW `v_approval_rules_effective` AS
    SELECT l.ladder_code,
           l.slug              AS entity_type,
           s.step_no           AS step_order,
           s.actor_code        AS role_required,
           s.actor_name_ar,
           s.step_kind,
           s.may_approve,
           l.name_ar           AS ladder_name,
           l.cap_kind, l.cap_amount, l.cap_currency, l.cap_state,
           l.escalate_after_hours,
           l.is_active
      FROM gov_ladders l
      JOIN gov_ladder_steps s ON s.ladder_code = l.ladder_code
     WHERE l.is_active = 1");
if (!$ok) { exit("✗ تعذّر إنشاءُ المنظر: {$conn->error}\n"); }

/* ── ③ قيدُ الثلاثِ خطوات — منظرُ الفحص ── */
$conn->query("DROP VIEW IF EXISTS `v_ladder_step_limit`");
$conn->query("CREATE VIEW `v_ladder_step_limit` AS
    SELECT l.ladder_code, l.name_ar, COUNT(s.id) steps,
           SUM(s.may_approve) approvers,
           CASE WHEN COUNT(s.id) > 3 THEN 'exceeds_three' ELSE 'within_limit' END AS verdict
      FROM gov_ladders l LEFT JOIN gov_ladder_steps s ON s.ladder_code = l.ladder_code
     WHERE l.is_active = 1 GROUP BY l.ladder_code, l.name_ar");

/* ── الإثبات ── */
$eff = (int) $conn->query("SELECT COUNT(*) c FROM v_approval_rules_effective")->fetch_assoc()['c'];
$lad = (int) $conn->query("SELECT COUNT(DISTINCT ladder_code) c FROM v_approval_rules_effective")->fetch_assoc()['c'];
echo "المنظرُ الحاكم: {$eff} خطوةً في {$lad} سلّمًا\n";
$r = $conn->query("SELECT ladder_code, name_ar, steps, approvers, verdict FROM v_ladder_step_limit ORDER BY ladder_code");
$over = 0;
while ($x = $r->fetch_assoc()) {
    if ($x['verdict'] === 'exceeds_three') { $over++; }
    echo "  {$x['ladder_code']} · {$x['name_ar']}: {$x['steps']} خطوةً · معتمِدون {$x['approvers']} · {$x['verdict']}\n";
}
$reqs = (int) $conn->query("SELECT COUNT(*) c FROM approval_requests")->fetch_assoc()['c'];
$steps = (int) $conn->query("SELECT COUNT(*) c FROM approval_steps")->fetch_assoc()['c'];
echo "دفترُ التنفيذِ لم يُمَسّ: {$reqs} طلبًا · {$steps} خطوةً منفَّذة\n";
if ($over > 0) { exit("✗ {$over} سلّمًا يتجاوز الثلاثَ خطوات — يُراجَع بقاعدةِ المالك\n"); }
echo "✔ مصدرُ القاعدةِ واحدٌ (gov_ladders) · وصفرُ سلّمٍ يتجاوز الثلاثَ خطوات · والملوَّثُ محجورٌ حتى {$purge}\n";
