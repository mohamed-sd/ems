<?php
/**
 * 2027_09_19_chain_node_registry.php
 *   سجلُّ عقدِ سلسلةِ الأثر — تسعٌ وعشرون عقدةً بثماني خاناتٍ للملكية
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مصدرُ حقيقةٍ واحدٌ للسلسلة**: كلُّ عقدةٍ بموضعِها التقنيِّ ومالكِ عمليتِها
 *   ومحاسبِها المنتدبِ ومعرِّفِ سلّمِها ورقابتِها وتنفيذِها النقديِّ وإجازةِ
 *   ترحيلِها ومنفِّذِه. **وثماني طبقاتٍ لا تُختصر في حقلِ «مالك» واحد.**
 *
 * ◆ **وعمودُ السلّمِ عقدٌ تقنيٌّ بثلاثةِ رموزٍ لا رابعَ لها**:
 *     LD-nn · NO_LADDER_REQUIRED · RESOLVE_FROM_POLICY:key
 *   — ويُحرَس بـCHECK فلا يُكتب فيه نصٌّ وصفيٌّ حر.
 *
 * ◆ **تصحيحان مُلزمان من أمرِ التنفيذ**:
 *   ① **العقدة 28**: سلّمُها `LD-05` **وحدَه** — لا `LD-04 → LD-05`. فعمودُ
 *     السلّمِ يقبل قيمةً قانونيةً واحدة، و LD-04 شرطٌ سابقٌ تمَّ في اعتمادِ
 *     وحداتِ المشغّلين **ولا يُعاد داخلَ العقدة 28**.
 *   ② **تكرارُ معرِّفِ السلّمِ لا يعني تكرارَ طلبِ الاعتماد**: العقدتان
 *     المتصلتان بالمعرِّفِ نفسِه (LD-02 في 4 و5 · LD-06 في 17 و18) **مرحلتان
 *     داخلَ نسخةِ سلّمٍ واحدة** — يُعلَن ذلك في `instance_scope` فلا يُنشأ
 *     طلبُ اعتمادٍ ثانٍ ولا يوقّع المستخدمُ اعتمادًا واحدًا مرتين.
 *
 * ◆ **وحالةُ البناءِ مقيسةٌ لا منقولة**: `BUILT` · `UNDER_OTHER_ROUTE` ·
 *   `MISSING` — من `tools/injchain01_node_reconcile.php`. **ولا يُبنى ما هو
 *   قائمٌ باسمٍ آخر**: «قدرةٌ محكومةٌ واحدة ⇒ مالكُ تنفيذٍ واحد ⇒ خدمةٌ كنسيةٌ
 *   واحدة ⇒ سجلُّ دليلٍ واحد».
 *
 * التشغيل:  php database/migrations/2027_09_19_chain_node_registry.php
 * الرجوع :  php database/migrations/2027_09_19_chain_node_registry.php --revert
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

if (in_array('--revert', $argv, true)) {
    $conn->query("DROP TABLE IF EXISTS `gov_chain_nodes`");
    echo "↺ أُسقط سجلُّ عقدِ السلسلة\n";
    exit(0);
}

$conn->query("CREATE TABLE IF NOT EXISTS `gov_chain_nodes` (
  `node_no`             TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  `declared_file`       VARCHAR(64)  NOT NULL,
  `title_ar`            VARCHAR(120) NOT NULL,
  `technical_runtime`   VARCHAR(80)  NOT NULL COMMENT 'أين تعيش الخدمة — وصفٌ هندسيٌّ لا ملكية',
  `process_owner`       VARCHAR(80)  NOT NULL COMMENT 'مالكُ الحدثِ أو المستند',
  `embedded_accountant` VARCHAR(80)  NULL     COMMENT 'المحاسبُ المنتدبُ — يراجع ويهيّئ ولا يعتمد',
  `ladder_id`           VARCHAR(48)  NOT NULL COMMENT 'LD-nn | NO_LADDER_REQUIRED | RESOLVE_FROM_POLICY:key',
  `accounting_control`  VARCHAR(80)  NULL     COMMENT 'إجازةٌ مستقلةٌ تسبق الترحيل',
  `cash_execution`      VARCHAR(80)  NULL     COMMENT 'الخزينةُ — ولا تملك قيدًا',
  `gl_posting_approval` VARCHAR(80)  NULL     COMMENT 'السلطةُ البشريةُ التي تُجيز الترحيل',
  `gl_posting_executor` VARCHAR(80)  NULL     COMMENT 'محرّكُ الترحيلِ وحدَه — لا كاتبَ بشريّ',
  `instance_scope`      VARCHAR(24)  NULL     COMMENT 'عقدٌ يشترك في نسخةِ سلّمٍ واحدةٍ مع غيرِه',
  `build_state`         ENUM('BUILT','UNDER_OTHER_ROUTE','MISSING') NOT NULL,
  `carrier_route`       VARCHAR(160) NULL     COMMENT 'السطحُ الحاملُ إن كان باسمٍ آخر',
  `build_wave`          TINYINT UNSIGNED NULL,
  `governing_doc`       VARCHAR(32)  NOT NULL DEFAULT 'INJ-CHAIN-CLOSE-01',
  `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `chk_chain_ladder_code` CHECK (
      `ladder_id` = 'NO_LADDER_REQUIRED'
   OR `ladder_id` REGEXP '^LD-[0-9]{2}$'
   OR `ladder_id` REGEXP '^RESOLVE_FROM_POLICY:[a-z_]+$'),
  KEY `ix_state` (`build_state`),
  KEY `ix_ladder` (`ladder_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='INJ-CHAIN-CLOSE-01 — 29 عقدةً بثماني خاناتِ ملكية'");

$OPS = 'إدارة الموقع'; $OPSM = 'إدارة التشغيل'; $SAL = 'المبيعات والعقود';
$SUP = 'إدارة الموردين'; $FIN = 'المالية والمحاسبة'; $TRE = 'الخزينة والبنوك';
$WFC = 'القوى التشغيلية'; $ENG = 'محرك الوحدات'; $ENGS = 'محرك الوحدات المشترك';
$CHIEF = 'رئيس الحسابات'; $POST = 'محرّك الترحيل'; $ACC = 'المحاسبة العامة';

/* node, file, title, runtime, owner, accountant, ladder, control, cash, glApp, glExec, scope, state, carrier, wave */
$N = array(
array(1,'timesheet.php','تسجيل التايم شيت والإنتاج','نطاق الموقع',$OPS,null,'NO_LADDER_REQUIRED',null,null,null,null,null,'BUILT','Timesheet/timesheet.php',null),
array(2,'unit_entry.php','إدخال وحدات العمل اليومية',$ENG,$OPS,null,'NO_LADDER_REQUIRED',null,null,null,null,null,'UNDER_OTHER_ROUTE','Operations/shift_entry.php · Timesheet/timesheet.php',1),
array(3,'unit_daily_approve.php','الاعتماد اليومي لرفع الوحدات',$ENG,$OPS . ' والتشغيل','المحاسب المقيم بالموقع — يدٌ ثانيةٌ في هذا السلّمِ وحدَه','LD-01',null,null,null,null,null,'UNDER_OTHER_ROUTE','Approvals/hours_approval.php · Portal/approvals_inbox.php',1),
array(4,'unit_client_match.php','مطابقة بيانات العميل',$ENG,$SAL,null,'LD-02',null,null,null,null,'LD-02-INST','BUILT','Contracts/unit_client_match.php',null),
array(5,'unit_sales_gate.php','بوابة اعتماد المبيعات',$ENG,$SAL,'محاسب المبيعات يراجع الأثر ولا يعتمد','LD-02',null,null,null,null,'LD-02-INST','UNDER_OTHER_ROUTE','Portal/approvals_inbox.php · Approvals/attribution_board.php',2),
array(6,'unit_supplier_approve.php','اعتماد وحدات الموردين',$ENG,$SUP,null,'LD-03',null,null,null,null,null,'UNDER_OTHER_ROUTE','Portal/approvals_inbox.php',2),
array(7,'unit_workforce_approve.php','اعتماد وحدات المشغّلين',$ENG,$WFC,null,'LD-04',null,null,null,null,null,'UNDER_OTHER_ROUTE','Portal/approvals_inbox.php',2),
array(8,'unit_fin_prelim.php','الاعتماد المالي الأولي',$ENGS,$FIN,'محاسب الإدارة يُعدّ بيانات الأثر','LD-05',$CHIEF,null,null,null,null,'UNDER_OTHER_ROUTE','Finance/unit_records_fin.php · Finance/approvals_inbox.php',3),
array(9,'unit_fin_final.php','الاعتماد المالي النهائي',$ENGS,$FIN,'إعداد بيانات القيد فقط','LD-07',$CHIEF,null,$CHIEF,$POST,null,'MISSING',null,3),
array(10,'unit_statement_client.php','كشف التايم شيت — العميل',$ENG,$SAL,null,'NO_LADDER_REQUIRED',null,null,null,null,null,'BUILT','Contracts/unit_statement_client.php',null),
array(11,'unit_statement_supplier.php','كشف التايم شيت — المورد',$ENG,$SUP,null,'NO_LADDER_REQUIRED',null,null,null,null,null,'BUILT','Suppliers/unit_statement_supplier.php',null),
array(12,'unit_statement_worker.php','كشف التايم شيت — المشغّل',$ENG,$WFC,null,'NO_LADDER_REQUIRED',null,null,null,null,null,'UNDER_OTHER_ROUTE','Portal/my_achievement.php',4),
array(13,'unit_correction.php','تصحيح الوحدات بالسلسلة الثلاثية',$ENGS,$OPSM . ' بالتنسيق مع المالكين الثلاثة','محاسب الإدارة يراجع أثر التصحيح','RESOLVE_FROM_POLICY:unit_correction',null,null,null,null,null,'MISSING',null,4),
array(14,'unit_perf.php','الأداء الشهري للوحدة',$ENG,$OPSM,null,'NO_LADDER_REQUIRED',null,null,null,null,null,'BUILT','Operations/unit_perf.php',null),
array(15,'claims.php','المستخلصات والمطالبات','نطاق المبيعات',$SAL,'محاسب المبيعات يراجع ويهيّئ أمر التحصيل','RESOLVE_FROM_POLICY:sales_claim',null,null,null,null,null,'BUILT','Contracts/claims.php',null),
array(16,'ar_accrual_gen.php','توليد استحقاقات عقد العميل','نطاق المالية',$FIN,'محاسب المبيعات يُعدّ','RESOLVE_FROM_POLICY:ar_accrual',$CHIEF,null,$CHIEF,$POST,null,'MISSING',null,5),
array(17,'ar_completion_cert.php','شهادة الإنجاز الشهرية','نطاق المالية',$FIN,'محاسب المبيعات يهيّئ','LD-06',$CHIEF,null,null,null,'LD-06-INST','MISSING',null,5),
array(18,'ar_claim_invoice.php','فاتورة المطالبة وإحالتها','نطاق المالية',$FIN,'محاسب المبيعات يهيّئ ولا يعتمد','LD-06',$CHIEF,null,$CHIEF,$POST,'LD-06-INST','MISSING',null,5),
array(19,'tax_invoices.php','الفاتورة الضريبية والإقرارات','نطاق المالية',$FIN,null,'RESOLVE_FROM_POLICY:ar_invoice',$CHIEF,null,$CHIEF,$POST,null,'BUILT','Contracts/tax_invoices.php',null),
array(20,'tre_receipts.php','سندات القبض والتحصيل','نطاق الخزينة',$TRE,'لا ينفّذه محاسب الإدارة ولو أعدّ الأمر','RESOLVE_FROM_POLICY:treasury_receipt',null,$TRE,null,null,null,'UNDER_OTHER_ROUTE','Contracts/collections.php',6),
array(21,'client_statement.php','كشف حساب العميل','نطاق المالية',$FIN,null,'NO_LADDER_REQUIRED',null,null,null,null,null,'BUILT','Contracts/client_statement.php',null),
array(22,'settlements.php','التسويات ومستحقات الموردين','نطاق الموردين',$SUP,'محاسب الموردين حاضرٌ في سلّم التسويات','LD-13',null,null,null,null,null,'BUILT','Suppliers/settlements.php',null),
array(23,'ap_oblig_gen.php','توليد التزامات عقد المورد','نطاق الموردين',$SUP,'محاسب الموردين يُعدّ','RESOLVE_FROM_POLICY:ap_obligation',null,null,null,null,null,'UNDER_OTHER_ROUTE','Contracts/contract_obligations.php',7),
array(24,'payments_fin.php','طلبات الدفع والسداد','نطاق المالية',$FIN,'محاسب الموردين يُعدّ الأمر ولا يعتمده','RESOLVE_FROM_POLICY:ap_payment_request',$CHIEF,null,null,null,null,'BUILT','Finance/payments_fin.php',null),
array(25,'tre_pay_batch.php','دفعات الدفع والتنفيذ','نطاق الخزينة',$TRE,'لا ينفّذه محاسب الإدارة','RESOLVE_FROM_POLICY:treasury_disbursement',null,$TRE,null,null,null,'MISSING',null,7),
array(26,'tre_exec_log.php','توثيق التنفيذ والإشعارات','نطاق الخزينة',$TRE,null,'RESOLVE_FROM_POLICY:treasury_disbursement',null,$TRE,null,null,null,'UNDER_OTHER_ROUTE','Finance/payments_fin.php',7),
array(27,'supplier_statement_fin.php','كشف حساب المورد الشهري','نطاق المالية',$FIN,null,'NO_LADDER_REQUIRED',null,null,null,null,null,'BUILT','Finance/supplier_statement_fin.php',null),
/* ◆ العقدة 28: `LD-05` وحدَه — و LD-04 شرطٌ سابقٌ تمَّ ولا يُعاد داخلَها */
array(28,'entitlement.php','توليد المستحق من العمل المعتمد','نطاق القوى التشغيلية',$WFC,'محاسب الأجور بعد بوابة المالية','LD-05',$CHIEF,null,$CHIEF,$POST,null,'BUILT','Finance/entitlement.php',null),
array(29,'entitlement_gate.php','فحص شروط الاستحقاق','نطاق المالية',$FIN,null,'LD-05',null,null,null,null,null,'BUILT','Finance/entitlement_gate.php',null),
);

$ins = $conn->prepare(
 "INSERT INTO `gov_chain_nodes`
   (`node_no`,`declared_file`,`title_ar`,`technical_runtime`,`process_owner`,`embedded_accountant`,
    `ladder_id`,`accounting_control`,`cash_execution`,`gl_posting_approval`,`gl_posting_executor`,
    `instance_scope`,`build_state`,`carrier_route`,`build_wave`)
  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
  ON DUPLICATE KEY UPDATE
    `declared_file`=VALUES(`declared_file`), `title_ar`=VALUES(`title_ar`),
    `technical_runtime`=VALUES(`technical_runtime`), `process_owner`=VALUES(`process_owner`),
    `embedded_accountant`=VALUES(`embedded_accountant`), `ladder_id`=VALUES(`ladder_id`),
    `accounting_control`=VALUES(`accounting_control`), `cash_execution`=VALUES(`cash_execution`),
    `gl_posting_approval`=VALUES(`gl_posting_approval`), `gl_posting_executor`=VALUES(`gl_posting_executor`),
    `instance_scope`=VALUES(`instance_scope`), `build_state`=VALUES(`build_state`),
    `carrier_route`=VALUES(`carrier_route`), `build_wave`=VALUES(`build_wave`)");
$n = 0; $err = array();
foreach ($N as $r) {
    $ins->bind_param('isssssssssssssi', $r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $r[6], $r[7],
                     $r[8], $r[9], $r[10], $r[11], $r[12], $r[13], $r[14]);
    if ($ins->execute()) { $n++; } else { $err[] = "عقدة {$r[0]}: {$ins->error}"; }
}
$ins->close();
printf("① قُيِّدت %d عقدةً من 29\n", $n);
foreach ($err as $e) { echo "  ✘ {$e}\n"; }

$q = $conn->query("SELECT `build_state`, COUNT(*) FROM `gov_chain_nodes` GROUP BY `build_state`");
while ($q && $r = $q->fetch_row()) { printf("   %-20s %s\n", $r[0], $r[1]); }

$q = $conn->query("SELECT `instance_scope`, GROUP_CONCAT(`node_no` ORDER BY `node_no`) FROM `gov_chain_nodes`
                    WHERE `instance_scope` IS NOT NULL GROUP BY `instance_scope`");
echo "② نسخُ السلّمِ المشتركة (تكرارُ المعرِّفِ ليس تكرارَ طلبِ اعتماد):\n";
while ($q && $r = $q->fetch_row()) { printf("   %-14s العقد %s\n", $r[0], $r[1]); }

$q = $conn->query("SELECT `ladder_id` FROM `gov_chain_nodes` WHERE `node_no` = 28");
printf("③ سلّمُ العقدة 28: **%s** — لا `LD-04 → LD-05`\n", $q ? $q->fetch_row()[0] : '?');

ems_migration_recorded(__FILE__, $conn, 0);
