<?php
/**
 * 2027_10_01_align_sheet_decisions.php
 *   قرارُ السطحِ لكلِّ ورقة — INJ-SAL-ALIGN-01 (26) و INJ-SUP-ALIGN-01 (29)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **«الورقةُ لا تعني شاشةً جديدة»**: لكلِّ ورقةٍ **قرارٌ واحدٌ لا غير**، ولا
 *   يُعلَن نقصٌ إلا بعد إثباتِ غيابِ المسارِ والتبويبِ والسطحِ التابعِ والمنظورِ
 *   والوظيفةِ المدمجةِ في سطحٍ آخر. وهذه قراراتُ الوثيقتين **منقولةً حرفًا**.
 *
 * ◆ **والمقامُ يُسمّى عند كلِّ استعمالٍ ولا يُخلط**: ورقةُ الفهرسِ (`00` و`م00`)
 *   لها قرارٌ أيضًا **ولا تُعَدُّ في مقامِ أوراقِ العمل** — فمقامُ المبيعاتِ ستٌّ
 *   وعشرون (`01`..`25` ومعها `12-2`)، ومقامُ الموردين تسعٌ وعشرون (`م01`..`م29`).
 *
 * ◆ **وسبعةُ أحكامٍ لا ثامنَ لها** — هي ألفاظُ الوثيقتين نفسِها:
 *   شاشةُ قائمة · تبويبٌ في الملفِّ الأم · سجلٌّ تابع · دمجٌ في شاشةِ قائمة ·
 *   إسقاطُ قراءة · منظورٌ محفوظ · مرجعٌ لا شاشة · أثرٌ تدقيقيّ · قدرةٌ مفقودة ·
 *   تقريرٌ أو لوحة · بندُ عملٍ مشترك.
 *
 * التشغيل:  php database/migrations/2027_10_01_align_sheet_decisions.php
 * الرجوع :  php database/migrations/2027_10_01_align_sheet_decisions.php --revert
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
    $conn->query("DROP TABLE IF EXISTS `gov_sheet_decisions`");
    echo "↺ أُسقط سجلُّ قراراتِ الأوراق\n";
    exit(0);
}

$conn->query("CREATE TABLE IF NOT EXISTS `gov_sheet_decisions` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `doc_code`    VARCHAR(24)  NOT NULL COMMENT 'INJ-SAL-ALIGN-01 | INJ-SUP-ALIGN-01',
  `sheet_code`  VARCHAR(12)  NOT NULL,
  `sheet_ar`    VARCHAR(160) NOT NULL,
  `stage_ar`    VARCHAR(80)  NOT NULL COMMENT 'مرحلةُ الدورةِ الحاكمة',
  `decision`    VARCHAR(40)  NOT NULL,
  `target`      VARCHAR(220) NOT NULL COMMENT 'السطحُ المستهدَفُ أو سببُ عدمِ وجودِه',
  `is_index`    TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'ورقةُ فهرسٍ — خارجَ مقامِ أوراقِ العمل',
  `note`        VARCHAR(300) NULL,
  `decided_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_sheet` (`doc_code`,`sheet_code`),
  KEY `ix_decision` (`decision`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='قرارُ السطحِ لكلِّ ورقةٍ في وثيقتَي المواءمة — منقولٌ حرفًا'");

$SAL = 'INJ-SAL-ALIGN-01'; $SUP = 'INJ-SUP-ALIGN-01';
/* doc, code, ar, stage, decision, target, is_index, note */
$R = array(
/* ── المبيعات: 00 فهرس + 26 ورقةَ عمل ───────────────────────────────── */
array($SAL,'00','الرئيسية','الإدارة','تقرير أو لوحة','commercial_board.php',1,'تُشتق من اللوحةِ التجاريةِ القائمة'),
array($SAL,'01','سجل العملاء','التمهيدية','شاشة قائمة','Clients/clients.php · SCR-0025',0,'تُنقل مرحلتُها من الأصولِ إلى التمهيدية'),
array($SAL,'02','جهات اتصال العملاء','التمهيدية','تبويب في الملف الأم','client_profile.php · SCR-0024',0,'لا شاشةَ مستقلة'),
array($SAL,'03','سجل المشاريع','التمهيدية','شاشة قائمة','projects.php · SCR-0374',0,'تُصحَّح مرحلتُها من الأصولِ والحركةِ إلى التمهيدية'),
array($SAL,'04','الفرص البيعية','إدارة الفرص البيعية','شاشة قائمة','opportunities.php · SCR-0314',0,'وتبتلع شاشةَ المناقصاتِ المكررة'),
array($SAL,'05','الأنشطة والمتابعات','إدارة الفرص البيعية','سجل تابع','activities.php · SCR-0023',0,'قائمٌ وغيرُ مسجَّلٍ في الدورة'),
array($SAL,'06','احتياج العميل وطلب العرض','إدارة الفرص البيعية','قدرة مفقودة','child of opportunity — 28 عمودًا',0,'لا مكافئ: طلباتُ العروضِ القائمةُ تخصُّ الموردين'),
array($SAL,'07','سجل العروض','العرض والتفاوض','شاشة قائمة','quotations.php · SCR-0032',0,'كما هي'),
array($SAL,'08','بنود العروض','العرض والتفاوض','سجل تابع','child of quotations.php — 24 عمودًا',0,'بنودُ العرضِ تابعةٌ لرأسِه'),
array($SAL,'09','التفاوض ومراجعات العرض','العرض والتفاوض','سجل تابع','child of quotations.php — 20 عمودًا',0,'سجلُّ نسخٍ ووقائعِ تفاوض'),
array($SAL,'10','مراجعة ما قبل العقد','العرض والتفاوض','شاشة قائمة','readiness_lines.php · SCR-0034',0,'جاهزيةُ العروضِ القائمةُ هي هذه المراجعة — تُعاد تسميتُها'),
array($SAL,'11','سجل عقود المشاريع','التعاقد','شاشة قائمة','contracts.php · SCR-0055',0,'رأسُ السجل — ويصير ملفًّا أمًّا لأربعَ عشرةَ شاشة'),
array($SAL,'12','بنود العقد والالتزام','التعاقد','تبويب في الملف الأم','contract_lines.php · SCR-0048',0,''),
array($SAL,'12-2','مصفوفة الالتزامات','التعاقد','دمج في شاشة قائمة','contract_obligations + contract_commitments',0,'المصفوفةُ هي الكنسية'),
array($SAL,'13','خط الأساس والمستهدفات','التعاقد','دمج في شاشة قائمة','contract_baseline + contract_monthly_plan',0,'سطحٌ واحد'),
array($SAL,'14','الحاويات التعاقدية','التعاقد','تبويب في الملف الأم','contract_coverage.php · SCR-0045',0,'ولا يظهر باسمِه الفنيّ — التغطيةُ التعاقديةُ للفترة'),
array($SAL,'15','الأداء والمبيعات الشهرية','الأداء والمطالبات','إسقاط قراءة','unit_statement_client + plan_actual_link',0,'لا إدخالَ ساعاتٍ في المبيعات'),
array($SAL,'16','المطالبات والتسليم للمالية','الأداء والمطالبات','شاشة قائمة','claims.php · SCR-0037',0,'ويلحق بها قبولُ العميلِ بدلَ شاشةٍ مستقلة'),
array($SAL,'17','الملحقات والتجديد والإغلاق','الأداء والمطالبات','دمج في شاشة قائمة','contract_amendments + contract_events',0,'سطحٌ واحد'),
array($SAL,'18','لوحة المبيعات','الإدارة','شاشة قائمة','commercial_board.php · SCR-0041',0,'اللوحةُ التجاريةُ تُعاد تسميتُها'),
array($SAL,'19','القوائم المرجعية','الإدارة','شاشة قائمة','products · pricelists · rate_books · uom · business_models',0,'خمسُ شاشاتٍ مرجعيةٍ تُجمع'),
array($SAL,'20','قاموس البيانات','الإدارة','مرجع لا شاشة','feeds field registry',0,'تغذّي سجلَّ الحقولِ وتصنيفَ البيانات'),
array($SAL,'21','خريطة الترحيل','الإدارة','مرجع لا شاشة','feeds INJ-FIX-02',0,''),
array($SAL,'22','تقرير المراجعة','الإدارة','أثر تدقيقي','acceptance evidence',0,'دليلُ قبولٍ لا سطح'),
array($SAL,'23','قاموس قواعد الاستنتاج','الإدارة','مرجع لا شاشة','derivation rules',0,''),
array($SAL,'24','سجل تتبع القيم الرجعية','الإدارة','أثر تدقيقي','audit trail',0,'يُحفظ ولا يُعرض شاشةً'),
array($SAL,'25','تقرير اكتمال البيانات','الإدارة','أثر تدقيقي','completeness evidence',0,'دليلُ قبولٍ لا سطح'),

/* ── الموردون: م00 فهرس + 29 ورقةَ عمل ──────────────────────────────── */
array($SUP,'م00','الرئيسية ودليل المنظومة','التأسيس','مرجع لا شاشة','SoD matrix',1,'فهرسٌ حاكمٌ ومصفوفةُ فصلِ واجبات'),
array($SUP,'م01','سجل الموردين','التأسيس','شاشة قائمة','suppliers.php · SCR-0461',0,'تُصحَّح مرحلتُه من طلباتِ العروضِ إلى تسجيلِ الموردين'),
array($SUP,'م02','جهات الاتصال والمفوضون','التأسيس','تبويب في الملف الأم','supplier_profile.php · SCR-0459',0,'والتفويضُ حقلُ حجيةٍ لا شاشة'),
array($SUP,'م03','التأهيل القانوني والائتماني','التأسيس','دمج في شاشة قائمة','supplier_documents + supplier_bank',0,'سطحان لمفهومٍ واحد'),
array($SUP,'م04','المعدات المقدَّمة تحت العقد','التأسيس','إسقاط قراءة','equipments.php + equipment_plan.php',0,'سجلُّ المعدةِ بيتُ الأسطول'),
array($SUP,'م05','احتياجات التغطية','التعاقد','إسقاط قراءة','contract_coverage.php',0,'جانبُ الطلبِ من عقدِ العميل — محجوبٌ اليومَ ويجب فتحُه قراءةً'),
array($SUP,'م06','الترشيح ومراجعة التعاقد','التعاقد','شاشة قائمة','rfq_requests.php · SCR-0442',0,'بوابةُ الترشيح — تُسجَّل في الدورةِ وتُضاف فحوصُها'),
array($SUP,'م07','سجل عقود الموردين','التعاقد','شاشة قائمة','supplierscontracts.php · SCR-0464',0,'رأسُ السجل — ويصير ملفًّا أمًّا'),
array($SUP,'م08','بنود عقود الموردين','التعاقد','تبويب في الملف الأم','supplier_contract_lines.php · SCR-0456',0,'قائمٌ وغيرُ مسجَّلٍ في الدورة'),
array($SUP,'م09','مصفوفة المسؤوليات والتكاليف','التعاقد','شاشة قائمة','supplier_rules.php · SCR-0460',0,'قواعدُ التحميلِ القائمةُ هي هذه المصفوفة'),
array($SUP,'م10','الحصص والحاويات التعاقدية','الحصص والتغطية','شاشة قائمة','shares_coverage.php · SCR-0444',0,'ولا يظهر اسمُ الخانةِ الفنيُّ في التنقّل'),
array($SUP,'م11','توزيع الخانات على المعدات','الحصص والتغطية','إسقاط قراءة','equipment_quota.php · SCR-0294',0,'الإسنادُ واقعةٌ تشغيليةٌ بيتُها التشغيل'),
array($SUP,'م12','الجاهزية والإحلال والاحتياط','الحصص والتغطية','دمج في شاشة قائمة','supplier_capacity + sup_handover',0,'سطحان لوقائعَ واحدة'),
array($SUP,'م13','مستهدفات الموردين','الحصص والتغطية','منظور محفوظ','derived from م10',0,'مشتقٌّ كليًّا من الحصص — منظرٌ لا شاشةٌ ولا إدخال'),
array($SUP,'م14','الأداء والوحدات المعتمدة','الحصص والتغطية','إسقاط قراءة','unit_supplier_approve — اعتمادية خارجية',0,'بوابةُ الاعتمادِ مسجَّلةٌ في التشغيل'),
array($SUP,'م15','النيابية والسلف والخصومات','الاستحقاقات','دمج في شاشة قائمة','supplier_advances.php · SCR-0448',0,'وتُضاف الواقعةُ النيابيةُ سجلًّا تابعًا للتسوية'),
array($SUP,'م16','استحقاقات الموردين','الاستحقاقات','تبويب في الملف الأم','settlements.php · SCR-0443',0,'الاستحقاقُ مشتقٌّ من الأداءِ والبنود'),
array($SUP,'م17','التسويات وكشف الحساب','الاستحقاقات','شاشة قائمة','settlements.php · SCR-0443',0,'وكشفُ الحسابِ التجاريُّ تبويبٌ فيها لا شاشةٌ مالية'),
array($SUP,'م18','طلبات الدفع وحالة الصرف','الاستحقاقات','بند عمل مشترك','payments_fin.php · finance-owned',0,'الطلبُ يُنشأ هنا ويُنفَّذ في المالية'),
array($SUP,'م19','المخالفات والجزاءات','التقييم والتصفية','قدرة مفقودة','child of settlements',0,'القواعدُ موجودةٌ والوقائعُ لا'),
array($SUP,'م20','تقييم المورد والأداء','التقييم والتصفية','شاشة قائمة','supplier_evaluation.php · SCR-0458',0,'ويستقبل مشتقاتِ المخاطرِ الثمانيةَ عشر'),
array($SUP,'م21','الملاحق والتصفية والإغلاق','التقييم والتصفية','دمج في شاشة قائمة','supplier_closure.php · SCR-0454',0,'والملاحقُ تُضاف سجلًّا تابعًا لعقدِ المورد'),
array($SUP,'م22','مصادر القدرة والتكامل','المرجعيات','تقرير أو لوحة','fin_assets + supplier_capacity',0,'قراءةٌ تجميعية'),
array($SUP,'م23','لوحة إدارة الموردين','المرجعيات','قدرة مفقودة','build dashboard',0,'لا لوحةَ إدارةٍ للموردين في النظامِ كلِّه'),
array($SUP,'م24','القوائم المرجعية','المرجعيات','مرجع لا شاشة','lookup lists',0,'سبعٌ وأربعون قائمةً تغذّي البياناتِ المرجعية'),
array($SUP,'م25','القاموس وخريطة الترحيل','المرجعيات','مرجع لا شاشة','feeds field registry',0,''),
array($SUP,'م26','تتبع التعبئة والترحيل','المرجعيات','أثر تدقيقي','migration trace',0,'يُحفظ ولا يُعرض'),
array($SUP,'م27','تخصيص الدفع على الإقفالات','الاستحقاقات','إسقاط قراءة','finance allocation',0,'التخصيصُ قاعدةٌ ماليةٌ ويُقرأ أثرُه في كشفِ الحساب'),
array($SUP,'م28','خريطة الترحيل','المرجعيات','مرجع لا شاشة','feeds INJ-FIX-02',0,''),
array($SUP,'م29','تقرير المراجعة والقبول','المرجعيات','أثر تدقيقي','acceptance evidence',0,'دليلُ قبولٍ لا سطح'),
);

$ins = $conn->prepare(
  "INSERT INTO `gov_sheet_decisions`
     (`doc_code`,`sheet_code`,`sheet_ar`,`stage_ar`,`decision`,`target`,`is_index`,`note`)
   VALUES (?,?,?,?,?,?,?,?)
   ON DUPLICATE KEY UPDATE
     `sheet_ar`=VALUES(`sheet_ar`), `stage_ar`=VALUES(`stage_ar`),
     `decision`=VALUES(`decision`), `target`=VALUES(`target`),
     `is_index`=VALUES(`is_index`), `note`=VALUES(`note`)");
$n = 0; $bad = array();
foreach ($R as $r) {
    $ins->bind_param('ssssssis', $r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $r[6], $r[7]);
    if ($ins->execute()) { $n++; } else { $bad[] = $r[0] . '/' . $r[1] . ': ' . $ins->error; }
}
$ins->close();
printf("① قُيِّد %d قرارَ ورقة\n", $n);
foreach ($bad as $b) { echo "  ✘ {$b}\n"; }

foreach (array($SAL => 26, $SUP => 29) as $doc => $target) {
    $q = $conn->query("SELECT COUNT(*) FROM `gov_sheet_decisions`
                        WHERE `doc_code` = '{$doc}' AND `is_index` = 0");
    $c = $q ? (int) $q->fetch_row()[0] : -1;
    printf("   %-18s أوراقُ عملٍ بقرار: **%d من %d** %s\n", $doc, $c, $target,
           $c === $target ? '✔' : '✘');
}
$q = $conn->query("SELECT `decision`, COUNT(*) FROM `gov_sheet_decisions` GROUP BY `decision` ORDER BY 2 DESC");
echo "② توزيعُ الأحكام:\n";
while ($q && $r = $q->fetch_row()) { printf("   %-26s %s\n", $r[0], $r[1]); }

ems_migration_recorded(__FILE__, $conn, 0);
