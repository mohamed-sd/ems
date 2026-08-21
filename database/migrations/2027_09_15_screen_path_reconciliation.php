<?php
/**
 * 2027_09_15_screen_path_reconciliation.php
 *   مصالحةُ مساراتِ دفترِ الدورة — INJ-EXEC-01 · المرحلة ①
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العيب**: `gov_screen_cycle.screen_file` يحمل **اسمًا مقترحًا** لا اسمَ
 *   الملفِّ على القرص. فمئةٌ وأربعةٌ وثمانون صفًّا تبدو «قدرةً مفقودة» وأكثرُها
 *   مبنيٌّ باسمٍ آخر. ⇒ **أيُّ إعادةِ تسميةٍ أو اشتقاقِ سايدبارٍ قبلَ تصحيحِ
 *   هذا الجدولِ يكسر مسارًا حيًّا** — ولذلك هي المرحلةُ الأولى لا غيرُها.
 *
 * ◆ **ولا يُمحى الاسمُ القديم**: يُنقل إلى سجلٍّ حاكمٍ بحكمِه وسببِه. فالتصحيحُ
 *   الذي يمحو ما صحّح يمنع المراجعةَ — و«صفرُ فقدٍ» يشمل الأسماءَ لا البياناتِ
 *   وحدَها.
 *
 * ◆ **وخمسةُ أحكامٍ لا سادسَ لها**:
 *     RENAMED_TO      — قائمٌ على القرصِ باسمِ مسارٍ مختلف
 *     VIEW_OF         — منظورٌ فرعيٌّ داخلَ سطحٍ قائمٍ (لاحقةُ `#n`)
 *     MERGED_INTO     — وظيفتُه مدموجةٌ في سطحٍ قائمٍ بقرارِ وثيقةِ مواءمة
 *     OWNED_ELSEWHERE — قائمٌ وبيتُه إدارةٌ أخرى — يُقرأ إسقاطًا لا يُبنى هنا
 *     NOT_BUILT       — غيرُ مبنيٍّ فعلًا · ويحمل موجةَ بنائِه
 *
 * ◆ **والمصدرُ حكمٌ بشريٌّ لا تخمينُ أداة**: الجدولُ أدناه منقولٌ حرفًا من
 *   وثيقتَي المواءمة (جدول «مسارات ظُنّت مفقودة وهي قائمة باسم آخر») ومن
 *   وثيقةِ إغلاقِ السلسلةِ (العقدُ الستَّ عشرةَ غيرُ المبنيةِ بموجاتِها).
 *
 * التشغيل:  php database/migrations/2027_09_15_screen_path_reconciliation.php
 * الرجوع :  php database/migrations/2027_09_15_screen_path_reconciliation.php --revert
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
    /* الرجوعُ يُعيد الأسماءَ القديمةَ إلى دفترِ الدورةِ قبلَ إسقاطِ السجل */
    $q = $conn->query("SELECT `book_file`, `real_route` FROM `gov_screen_path_map`
                        WHERE `applied_to_cycle` = 1");
    $back = 0;
    if ($q) {
        while ($r = $q->fetch_assoc()) {
            $st = $conn->prepare("UPDATE `gov_screen_cycle` SET `screen_file` = ? WHERE `screen_file` = ?");
            $st->bind_param('ss', $r['book_file'], $r['real_route']);
            $st->execute(); $back += $st->affected_rows; $st->close();
        }
    }
    $conn->query("DROP TABLE IF EXISTS `gov_screen_path_map`");
    echo "↺ أُعيد {$back} صفًّا إلى اسمِه الدفتريِّ · وأُسقط سجلُّ المصالحة\n";
    exit(0);
}

$conn->query("CREATE TABLE IF NOT EXISTS `gov_screen_path_map` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `book_file`        VARCHAR(128) NOT NULL COMMENT 'الاسمُ كما في دفترِ الدورة',
    `real_route`       VARCHAR(192) NULL     COMMENT 'المسارُ الحقيقيُّ على القرص',
    `ruling`           VARCHAR(24)  NOT NULL
        COMMENT 'RENAMED_TO | VIEW_OF | MERGED_INTO | OWNED_ELSEWHERE | NOT_BUILT',
    `owner_dept`       VARCHAR(64)  NULL,
    `chain_node`       TINYINT UNSIGNED NULL COMMENT 'رقمُ العقدةِ في سلسلةِ الأثرِ إن كانت منها',
    `build_wave`       TINYINT UNSIGNED NULL COMMENT 'موجةُ البناءِ لغيرِ المبنيّ',
    `source_doc`       VARCHAR(32)  NOT NULL COMMENT 'الوثيقةُ الحاكمةُ لهذا الحكم',
    `reason`           VARCHAR(400) NOT NULL,
    `applied_to_cycle` TINYINT(1) NOT NULL DEFAULT 0,
    `decided_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_book` (`book_file`),
    KEY `ix_ruling` (`ruling`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='INJ-EXEC-01 ① — الاسمُ الدفتريُّ ومسارُه الحقيقيُّ بحكمٍ مكتوب'");

/* ── الأحكامُ المنقولةُ حرفًا من الوثائقِ الثلاث ────────────────────────────── */
$MAP = array(
/* book_file, real_route, ruling, owner_dept, chain_node, wave, doc, reason */
array('price_adjust.php', 'Contracts/price_terms.php', 'RENAMED_TO', 'المبيعات والعقود', null, null, 'INJ-SAL-ALIGN-01',
  'قائمةٌ على القرصِ باسمِ مسارٍ مختلف — والخللُ في اسمِ الملفِّ بالدفترِ لا في الوجود (تصحيحُ ت-1)'),
array('invoices.php', 'Contracts/tax_invoices.php', 'OWNED_ELSEWHERE', 'المالية والخزينة', null, null, 'INJ-SAL-ALIGN-01',
  'الفاتورةُ الرسميةُ قائمةٌ فعلًا وبيتُها المالية — تُقرأ في المبيعاتِ إسقاطًا (تصحيحُ ت-2)'),
array('unit_client_invoice.php', 'Contracts/tax_invoices.php', 'OWNED_ELSEWHERE', 'المالية والخزينة', null, null, 'INJ-SAL-ALIGN-01',
  'الفاتورةُ النهائيةُ للعميلِ هي الفاتورةُ الضريبيةُ نفسُها عند مالكِها — ولا تُبنى ثانيةً في المبيعات'),
array('unit_client_accept.php', 'Contracts/claims.php', 'MERGED_INTO', 'المبيعات والعقود', null, null, 'INJ-SAL-ALIGN-01',
  'قبولُ العميلِ يلحق بشاشةِ المطالباتِ بدلَ شاشةٍ مستقلة — قرارُ الورقة 16'),
array('contract_terms.php', 'Contracts/contract_card.php', 'RENAMED_TO', 'المبيعات والعقود', null, null, 'INJ-SAL-ALIGN-01',
  'اسمان لمسارٍ واحد: بطاقةُ العقدِ في السجلِّ وأحكامُ العقدِ في الدورة — والاسمُ الكنسيُّ أحكامُ العقد'),
array('coverage.php', 'Contracts/contract_coverage.php', 'RENAMED_TO', 'المبيعات والعقود', null, null, 'INJ-SAL-ALIGN-01',
  'التزامُ العقدِ بنوعِ المعدةِ هو التغطيةُ التعاقديةُ للفترة — ولا تظهر كلمةُ الحاويةِ في التنقّل'),
array('messages.php', 'chats/index.php', 'RENAMED_TO', 'مساحة العمل الشخصية', null, null, 'INJ-EXEC-01',
  'المراسلاتُ قائمةٌ على مسارِ المحادثاتِ المركزيّ — سطحٌ مشتركٌ لا نسخةَ إدارة'),
array('units.php', 'Operations/equipment_quota.php', 'MERGED_INTO', 'إدارة التشغيل', null, null, 'INJ-SAL-ALIGN-01',
  'الوحداتُ التعاقديةُ المرقَّمة: **معلَّقةٌ على إثباتِ عدمِ التكافؤ** مع شاشةِ إسنادِ المعداتِ القائمة — ولا تُبنى قبلَه (تصحيحُ ت-3)'),

array('supplier_equip.php', 'Suppliers/equipment_plan.php#2', 'VIEW_OF', 'إدارة الموردين', null, null, 'INJ-SUP-ALIGN-01',
  'معداتُ الموردِ ومالكوها منظورٌ فرعيٌّ داخلَ خطةِ المعدات — لا شاشةَ مستقلة'),
array('supplier_plan.php', 'Suppliers/equipment_plan.php', 'RENAMED_TO', 'إدارة الموردين', null, null, 'INJ-SUP-ALIGN-01',
  'خطةُ معداتِ الموردِ المتعهَّدِ بها قائمةٌ باسمِ خطةِ المعدات'),
array('supplier_perf.php', 'Suppliers/supplier_capacity.php#2', 'VIEW_OF', 'إدارة الموردين', null, null, 'INJ-SUP-ALIGN-01',
  'أداءُ المورّدين وجاهزيتُهم منظورٌ فرعيٌّ على مسارِ طاقةِ المورد'),
array('quota_ledger.php', 'Suppliers/shares_coverage.php#2', 'VIEW_OF', 'إدارة الموردين', null, null, 'INJ-SUP-ALIGN-01',
  'دفترُ استهلاكِ الحصصِ منظورٌ محفوظٌ لا قدرةٌ مفقودة'),
array('supplier_settle.php', 'Suppliers/settlements.php', 'RENAMED_TO', 'إدارة الموردين', null, null, 'INJ-SUP-ALIGN-01',
  'تسوياتُ ومستحقاتُ الموردين قائمةٌ باسمِ مسارٍ مختلف'),
array('supplier_contracts.php', 'Suppliers/supplierscontracts.php', 'RENAMED_TO', 'إدارة الموردين', null, null, 'INJ-SUP-ALIGN-01',
  'اسمُ المسارِ في الدورةِ يخالف اسمَه على القرص — والقرصُ هو الحقيقةُ ويُصحَّح الدفتر'),
array('supplier_quota.php', 'Suppliers/shares_coverage.php', 'RENAMED_TO', 'إدارة الموردين', null, null, 'INJ-SUP-ALIGN-01',
  'حصصُ الموردين من العقودِ قائمةٌ باسمِ حصصِ التغطية'),
array('supplier_stmt.php', 'Finance/supplier_statement_fin.php', 'OWNED_ELSEWHERE', 'المالية والخزينة', 27, null, 'INJ-SUP-ALIGN-01',
  'كشفُ الحسابِ المحاسبيُّ بيتُه المالية — والتجاريُّ تبويبٌ في التسوياتِ لا شاشةٌ ثانية'),
array('fin_routing.php', 'Finance/acc_routing_matrix.php', 'RENAMED_TO', 'المالية والخزينة', null, null, 'INJ-SUP-ALIGN-01',
  'مصفوفةُ توجيهِ الطلباتِ الماليةِ قائمةٌ باسمِ مسارٍ مختلف'),
array('fin_backflow.php', 'Finance/acc_backflow.php', 'RENAMED_TO', 'المالية والخزينة', null, null, 'INJ-SUP-ALIGN-01',
  'المرتجَعُ الماليُّ للإداراتِ قائمٌ باسمِ مسارٍ مختلف'),

/* ── عقدُ سلسلةِ الأثرِ غيرُ المبنية — بموجاتِها من INJ-CHAIN-CLOSE-01 ────── */
array('unit_entry.php', null, 'NOT_BUILT', 'إدارة الموقع', 2, 1, 'INJ-CHAIN-CLOSE-01',
  'إدخالُ وحداتِ العملِ اليومية — بلا هذه لا تدخل واقعةٌ السلسلةَ أصلًا'),
array('unit_daily_approve.php', null, 'NOT_BUILT', 'إدارة الموقع', 3, 1, 'INJ-CHAIN-CLOSE-01',
  'الاعتمادُ اليوميُّ لرفعِ الوحدات — LD-01 · والمحاسبُ المقيمُ يدٌ ثانيةٌ في هذا السلّمِ وحدَه'),
array('unit_sales_gate.php', null, 'NOT_BUILT', 'المبيعات والعقود', 5, 2, 'INJ-CHAIN-CLOSE-01',
  'بوابةُ اعتمادِ المبيعات — LD-02 · موضعُها التقنيُّ محرّكُ الوحداتِ ومالكُ عمليتِها المبيعات'),
array('unit_supplier_approve.php', null, 'NOT_BUILT', 'إدارة الموردين', 6, 2, 'INJ-CHAIN-CLOSE-01',
  'اعتمادُ وحداتِ الموردين — LD-03 · لا محاسبَ في الخطوةِ والفحصُ آليٌّ من المحرك'),
array('unit_workforce_approve.php', null, 'NOT_BUILT', 'القوى التشغيلية', 7, 2, 'INJ-CHAIN-CLOSE-01',
  'اعتمادُ وحداتِ المشغّلين — LD-04 · لا ترحيلَ — المرحلةُ تشغيلية'),
array('unit_fin_prelim.php', null, 'NOT_BUILT', 'المالية والخزينة', 8, 3, 'INJ-CHAIN-CLOSE-01',
  'الاعتمادُ الماليُّ الأوّليّ — LD-05 · **بوابةُ الماليةِ تبدأ هنا ولا قبلَها**'),
array('unit_fin_final.php', null, 'NOT_BUILT', 'المالية والخزينة', 9, 3, 'INJ-CHAIN-CLOSE-01',
  'الاعتمادُ الماليُّ النهائيّ — LD-07 · إجازةُ رئيسِ الحساباتِ قبلَ الترحيل'),
array('unit_statement_worker.php', null, 'NOT_BUILT', 'القوى التشغيلية', 12, 4, 'INJ-CHAIN-CLOSE-01',
  'كشفُ التايم شيت للمشغّل — قراءةٌ لا اعتماد · وبغيرِه يبقى الطرفُ الثالثُ بلا مرآة'),
array('unit_correction.php', null, 'NOT_BUILT', 'إدارة التشغيل', 13, 4, 'INJ-CHAIN-CLOSE-01',
  'تصحيحُ الوحداتِ بالسلسلةِ الثلاثية — RESOLVE_FROM_POLICY:unit_correction · لا تصحيحَ إلا بمرورِ السلسلةِ كاملةً'),
array('ar_accrual_gen.php', null, 'NOT_BUILT', 'المالية والخزينة', 16, 5, 'INJ-CHAIN-CLOSE-01',
  'توليدُ استحقاقاتِ عقدِ العميل — RESOLVE_FROM_POLICY:ar_accrual · إجازةُ رئيسِ الحساباتِ قبلَ الترحيل'),
array('ar_completion_cert.php', null, 'NOT_BUILT', 'المالية والخزينة', 17, 5, 'INJ-CHAIN-CLOSE-01',
  'شهادةُ الإنجازِ الشهرية — LD-06 · المستندُ الذي تُبنى عليه الفاتورة'),
array('ar_claim_invoice.php', null, 'NOT_BUILT', 'المالية والخزينة', 18, 5, 'INJ-CHAIN-CLOSE-01',
  'فاتورةُ المطالبةِ وإحالتُها — LD-06 · محاسبُ المبيعاتِ يهيّئ ولا يعتمد'),
array('tre_receipts.php', null, 'NOT_BUILT', 'المالية والخزينة', 20, 6, 'INJ-CHAIN-CLOSE-01',
  'سنداتُ القبضِ والتحصيل — RESOLVE_FROM_POLICY:treasury_receipt · تنفيذٌ نقديٌّ ينتج مرجعَ الحركةِ ولا قيد'),
array('ap_oblig_gen.php', null, 'NOT_BUILT', 'إدارة الموردين', 23, 7, 'INJ-CHAIN-CLOSE-01',
  'توليدُ التزاماتِ عقدِ المورد — **قدرةٌ واحدةٌ لا مهمتان**: العقدةُ 23 في السلسلةِ هي نفسُها القدرةُ المفقودةُ في وثيقةِ الموردين'),
array('tre_pay_batch.php', null, 'NOT_BUILT', 'المالية والخزينة', 25, 7, 'INJ-CHAIN-CLOSE-01',
  'دفعاتُ الدفعِ والتنفيذ — RESOLVE_FROM_POLICY:treasury_disbursement · تنفيذٌ نقديٌّ ولا قيد'),
array('tre_exec_log.php', null, 'NOT_BUILT', 'المالية والخزينة', 26, 7, 'INJ-CHAIN-CLOSE-01',
  'توثيقُ التنفيذِ والإشعارات — ينتج مرجعَ الحركة'),
array('tre_beneficiary.php', null, 'NOT_BUILT', 'المالية والخزينة', null, 7, 'INJ-CHAIN-CLOSE-01',
  '**شرطٌ سابقٌ خارجَ العقدِ الستَّ عشرة**: سجلُّ المستفيدين والحساباتِ البنكية — فمقامُ العقدِ ستَّ عشرةَ ومقامُ المهامِّ سبعَ عشرة'),

/* ── قدراتُ المبيعاتِ والموردين المفقودةُ خارجَ السلسلة ─────────────────────── */
array('client_need_rfq.php', null, 'NOT_BUILT', 'المبيعات والعقود', null, null, 'INJ-SAL-ALIGN-01',
  'احتياجُ العميلِ وطلبُ العرض — لا مكافئ: طلباتُ العروضِ القائمةُ تخصُّ الموردين لا العملاء (28 عمودًا)'),
array('quotation_lines.php', null, 'NOT_BUILT', 'المبيعات والعقود', null, null, 'INJ-SAL-ALIGN-01',
  'بنودُ العروضِ بالسعرِ والكميةِ والوحدة — لا سطحَ بنودٍ للعرضِ في السجل (24 عمودًا)'),
array('quotation_negotiation.php', null, 'NOT_BUILT', 'المبيعات والعقود', null, null, 'INJ-SAL-ALIGN-01',
  'التفاوضُ ومراجعاتُ العرض — سجلُّ نسخِ العرضِ ووقائعِ التغيير (20 عمودًا)'),
array('supplier_violations.php', null, 'NOT_BUILT', 'إدارة الموردين', null, null, 'INJ-SUP-ALIGN-01',
  'المخالفاتُ والجزاءات — القواعدُ موجودةٌ والوقائعُ لا · سجلٌّ تابعٌ للتسوية'),
array('supplier_board.php', null, 'NOT_BUILT', 'إدارة الموردين', null, null, 'INJ-SUP-ALIGN-01',
  'لوحةُ إدارةِ الموردين — لا لوحةَ للموردين في النظامِ كلِّه · صفحةُ هبوطٍ للمساحةِ لا بندًا في مجموعة'),
);

$ins = $conn->prepare(
  "INSERT INTO `gov_screen_path_map`
     (`book_file`,`real_route`,`ruling`,`owner_dept`,`chain_node`,`build_wave`,`source_doc`,`reason`)
   VALUES (?,?,?,?,?,?,?,?)
   ON DUPLICATE KEY UPDATE
     `real_route`=VALUES(`real_route`), `ruling`=VALUES(`ruling`),
     `owner_dept`=VALUES(`owner_dept`), `chain_node`=VALUES(`chain_node`),
     `build_wave`=VALUES(`build_wave`), `source_doc`=VALUES(`source_doc`),
     `reason`=VALUES(`reason`)");
$n = 0;
foreach ($MAP as $m) {
    list($bf, $rr, $ru, $od, $cn, $bw, $sd, $rs) = $m;
    $ins->bind_param('ssssiiss', $bf, $rr, $ru, $od, $cn, $bw, $sd, $rs);
    if (!$ins->execute()) { echo "✘ فشل {$bf}: {$ins->error}\n"; }
    $n++;
}
$ins->close();
printf("① قُيِّد %d حكمَ مصالحةٍ في `gov_screen_path_map`\n", $n);

/* ── ② تصحيحُ دفترِ الدورةِ — للأحكامِ التي لها مسارٌ حقيقيٌّ فقط ───────────── */
$q = $conn->query("SELECT `book_file`, `real_route` FROM `gov_screen_path_map`
                    WHERE `real_route` IS NOT NULL
                      AND `ruling` IN ('RENAMED_TO','VIEW_OF','MERGED_INTO','OWNED_ELSEWHERE')");
$applied = 0; $touched = 0;
while ($r = $q->fetch_assoc()) {
    $st = $conn->prepare("UPDATE `gov_screen_cycle` SET `screen_file` = ? WHERE `screen_file` = ?");
    $st->bind_param('ss', $r['real_route'], $r['book_file']);
    $st->execute();
    $aff = $st->affected_rows;
    $st->close();
    if ($aff > 0) {
        $touched += $aff;
        $st = $conn->prepare("UPDATE `gov_screen_path_map` SET `applied_to_cycle` = 1 WHERE `book_file` = ?");
        $st->bind_param('s', $r['book_file']); $st->execute(); $st->close();
        $applied++;
    }
}
printf("② صُحِّح %d اسمًا في دفترِ الدورة — %d صفًّا\n", $applied, $touched);

/* ── ③ ما بقي بلا وجودٍ ولا حكم — يُعلَن ولا يُطوى ───────────────────────── */
$q = $conn->query("SELECT COUNT(*) FROM `gov_screen_cycle` c
                    WHERE NOT EXISTS (SELECT 1 FROM `gov_screen_path_map` m
                                       WHERE m.book_file = c.screen_file)");
$rest = $q ? (int) $q->fetch_row()[0] : -1;
echo "③ صفوفُ دورةٍ خارجَ سجلِّ المصالحةِ بعدُ: {$rest}\n";
echo "   ◆ منها ما هو موجودٌ على القرصِ فعلًا (لا يحتاج حكمًا) — والباقي يُحكَم\n";
echo "     في جولاتِ الإداراتِ الأخرى بمالكِها. **ولا يُدَّعى إغلاقُ الـ184 هنا.**\n";

/* القاعدةُ النافذة: الهجرةُ تُقيِّد نفسَها ذريًّا — NF-05 */
ems_migration_recorded(__FILE__, $conn, 0);
