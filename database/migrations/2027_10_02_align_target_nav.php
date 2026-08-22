<?php
/**
 * 2027_10_02_align_target_nav.php
 *   التنقّلُ المستهدَفُ بعدَ الدمج — ٦ مجموعات/١٣ بندًا · ٦ مجموعات/١٤ بندًا
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الدمجُ يقع في الواجهةِ لا في الدورةِ ولا في السجل** — بنصِّ الوثيقتين.
 *   فكلُّ قسمٍ يبقى سجلًّا بمعرّفِه وتاريخِه وسلّمِ اعتمادِه وأثرِه التدقيقيّ؛
 *   والذي يتغيّر **موضعُ ظهورِه**: من بندٍ في القائمةِ إلى تبويبٍ في ملفِّ أبيه.
 *
 * ◆ **وهذا الجدولُ يُعلن الهدفَ ولا يُطبِّقه**: التطبيقُ في الهجرةِ التالية،
 *   ليُقاس الفرقُ بين المُعلَنِ والحيِّ قبلَ أن يُمَسَّ صفٌّ واحد.
 *
 * ◆ **والمرساتان خارجَ المقام**: «الرئيسية» و«المراسلات» تُفرضان في كلِّ دورٍ
 *   بقرارِ مالكٍ سابق — فلا تُعَدّان في الثلاثةَ عشرَ ولا الأربعةَ عشر.
 *   ولوحةُ الإدارةِ **صفحةُ هبوطٍ للمساحةِ لا بندًا في مجموعة**.
 *
 * التشغيل:  php database/migrations/2027_10_02_align_target_nav.php
 * الرجوع :  php database/migrations/2027_10_02_align_target_nav.php --revert
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
    $conn->query("DROP TABLE IF EXISTS `gov_target_nav`");
    echo "↺ أُسقط سجلُّ التنقّلِ المستهدَف\n";
    exit(0);
}

$conn->query("CREATE TABLE IF NOT EXISTS `gov_target_nav` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `doc_code`   VARCHAR(24) NOT NULL,
  `role_id`    INT NOT NULL,
  `group_no`   TINYINT UNSIGNED NOT NULL,
  `group_ar`   VARCHAR(120) NOT NULL,
  `item_no`    TINYINT UNSIGNED NOT NULL,
  `item_ar`    VARCHAR(120) NOT NULL,
  `route`      VARCHAR(160) NOT NULL,
  `note`       VARCHAR(300) NULL,
  UNIQUE KEY `uq_tn` (`doc_code`,`route`),
  KEY `ix_tn_role` (`role_id`,`group_no`,`item_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='التنقّلُ المستهدَفُ بعدَ الدمج — منقولٌ من وثيقتَي المواءمة'");

$SAL = 'INJ-SAL-ALIGN-01'; $SUP = 'INJ-SUP-ALIGN-01';
/* doc, role, gno, group_ar, ino, item_ar, route, note */
$T = array(
/* ══ المبيعات · دور 12 — ٦ مجموعات / ١٣ بندًا ══════════════════════════ */
array($SAL,12,1,'المهام والاعتمادات',1,'الاعتمادات الواردة','Finance/approvals_inbox.php','طبقةُ عملٍ شخصيةٌ مركزيةٌ واحدة — لا نسخةَ لكلِّ إدارة'),
array($SAL,12,1,'المهام والاعتمادات',2,'المهام الواردة','Portal/my_tasks.php',null),
array($SAL,12,1,'المهام والاعتمادات',3,'البلاغات والمهل','Tickets/tickets_list.php?tab=dept',null),
array($SAL,12,2,'إدارة العملاء والفرص',1,'سجل العملاء','Clients/clients.php','ملفٌّ أمٌّ بتبويبات: البيانات · جهات الاتصال · المشاريع · العقود · المركز المالي (إسقاطٌ مقيَّد)'),
array($SAL,12,2,'إدارة العملاء والفرص',2,'سجل المشاريع','Projects/projects.php','يُبحث فيه عرضيًّا من العملياتِ والموقعِ والمالية'),
array($SAL,12,2,'إدارة العملاء والفرص',3,'سجل الفرص البيعية','Opportunities/opportunities.php','وتبتلع المناقصات — نوعُ فرصةٍ لا سجلٌّ مستقل'),
array($SAL,12,3,'العروض والتعاقد',1,'سجل العروض','Clients/quotations.php','تبويبات: بيانات العرض · بنوده · سجلُّ التفاوض · مراجعةُ ما قبل التعاقد'),
array($SAL,12,3,'العروض والتعاقد',2,'سجل عقود المشاريع','Contracts/contracts.php','ملفٌّ أمٌّ لاثنَي عشرَ تبويبًا — والمساراتُ محوَّلةٌ لا محذوفة'),
array($SAL,12,4,'الأداء والمطالبات التجارية',1,'سجل المطالبات والتسليم للمالية','Contracts/claims.php','تبويبات: الأداءُ الشهريُّ المعتمد · بنودُ المطالبة · غيرُ المفوتر · حالةُ الفاتورةِ والتحصيل (إسقاط)'),
array($SAL,12,5,'البيانات المرجعية والتقارير',1,'البيانات المرجعية','Clients/products.php','تبويبات: كتالوجُ الخدمات · قوائمُ التسعير · دفترُ الأسعار · وحداتُ القياس · نماذجُ العمل'),
array($SAL,12,5,'البيانات المرجعية والتقارير',2,'التقارير والتحليلات','Reports/reports.php',null),
array($SAL,12,6,'الحوكمة والضوابط',1,'حوكمة المبيعات والعقود','Governance/gov_dept_sal.php',null),
array($SAL,12,6,'الحوكمة والضوابط',2,'مخاطر المبيعات والعقود','Risk/risk_dept_sal.php',null),

/* ══ الموردون · دور 2 — ٦ مجموعات / ١٤ بندًا ═══════════════════════════ */
array($SUP,2,1,'المهام والاعتمادات',1,'الاعتمادات الواردة','Approvals/requests.php','المكوّنُ المركزيُّ نفسُه — يُرشَّح بالمستخدمِ والمساحةِ والدور'),
array($SUP,2,1,'المهام والاعتمادات',2,'المهام الواردة','Portal/my_tasks.php',null),
array($SUP,2,1,'المهام والاعتمادات',3,'البلاغات والمهل','Tickets/tickets_list.php?tab=dept',null),
array($SUP,2,2,'إدارة الموردين والتأهيل',1,'سجل الموردين','Suppliers/suppliers.php','ملفٌّ أمٌّ بستةِ تبويبات: البيانات · جهاتُ الاتصالِ والمفوضون · التأهيلُ والوثائقُ والحساب · المعداتُ المقدَّمة · الطاقةُ والجاهزية · التقييمُ والمخاطر'),
array($SUP,2,3,'الاحتياج والتعاقد',1,'احتياجات التغطية','Contracts/contract_coverage.php','**إسقاطُ قراءة** — جانبُ الطلبِ من عقدِ العميل · كان محجوبًا فيُفتح قراءةً'),
array($SUP,2,3,'الاحتياج والتعاقد',2,'الترشيح ومراجعة التعاقد','Suppliers/rfq_requests.php','بوابةُ الترشيحِ وفحوصُ ما قبل التعاقد'),
array($SUP,2,3,'الاحتياج والتعاقد',3,'سجل عقود الموردين','Suppliers/supplierscontracts.php','ملفٌّ أمٌّ بأربعةِ تبويبات'),
array($SUP,2,4,'الحصص والتغطية والأداء',1,'سجل الحصص والتغطية التعاقدية','Suppliers/shares_coverage.php','تبويبات: الوحداتُ التعاقدية · إسنادُ المعدات · الجاهزيةُ والإحلال · المستهدفاتُ الشهرية'),
array($SUP,2,4,'الحصص والتغطية والأداء',2,'اعتماد الوحدات والأداء المعتمد','Suppliers/unit_statement_supplier.php','والاعتمادُ نفسُه اعتماديةٌ خارجيةٌ في التشغيل — لا يُبنى هنا'),
array($SUP,2,5,'الاستحقاقات والتسويات',1,'التسويات وكشف الحساب','Suppliers/settlements.php','تبويبات: الاستحقاقات · السلفُ والنيابية · المخالفاتُ والجزاءات · طلباتُ الدفعِ وحالةُ الصرف (إسقاط)'),
array($SUP,2,6,'المرجعيات والحوكمة',1,'البيانات المرجعية ومصادر القدرة','Suppliers/supplier_capacity.php',null),
array($SUP,2,6,'المرجعيات والحوكمة',2,'تقارير الموردين','Reports/reports.php',null),
array($SUP,2,6,'المرجعيات والحوكمة',3,'حوكمة إدارة الموردين','Governance/gov_dept_sup.php',null),
array($SUP,2,6,'المرجعيات والحوكمة',4,'مخاطر الموردين','Risk/risk_dept_sup.php',null),
);

$ins = $conn->prepare(
  "INSERT INTO `gov_target_nav` (`doc_code`,`role_id`,`group_no`,`group_ar`,`item_no`,`item_ar`,`route`,`note`)
   VALUES (?,?,?,?,?,?,?,?)
   ON DUPLICATE KEY UPDATE `group_no`=VALUES(`group_no`), `group_ar`=VALUES(`group_ar`),
     `item_no`=VALUES(`item_no`), `item_ar`=VALUES(`item_ar`), `note`=VALUES(`note`)");
$n = 0;
foreach ($T as $r) {
    $ins->bind_param('siisisss', $r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $r[6], $r[7]);
    if ($ins->execute()) { $n++; } else { echo "  ✘ {$r[6]}: {$ins->error}\n"; }
}
$ins->close();
printf("① قُيِّد %d بندَ تنقّلٍ مستهدَف\n", $n);

foreach (array(array($SAL, 12, 6, 13), array($SUP, 2, 6, 14)) as $x) {
    list($doc, $role, $g, $i) = $x;
    $q = $conn->query("SELECT COUNT(DISTINCT `group_no`), COUNT(*) FROM `gov_target_nav`
                        WHERE `doc_code` = '{$doc}'");
    $r = $q ? $q->fetch_row() : array(0, 0);
    printf("   %-18s دور %-3d مجموعات **%d/%d** · بنود **%d/%d** %s\n",
           $doc, $role, (int) $r[0], $g, (int) $r[1], $i,
           ((int) $r[0] === $g && (int) $r[1] === $i) ? '✔' : '✘');
}

/* ── الفرقُ بين المُعلَنِ والحيِّ — يُقاس قبلَ أن يُمَسَّ صفٌّ واحد ─────────── */
echo "② الفرقُ اليومَ (قبلَ التطبيق):\n";
foreach (array(12 => $SAL, 2 => $SUP) as $role => $doc) {
    $q = $conn->query("SELECT COUNT(*) FROM `nav_items`
                        WHERE `role_id` = {$role} AND `active` = 1
                          AND `route` NOT IN ('main/role_board.php','chats/index.php')");
    $live = $q ? (int) $q->fetch_row()[0] : -1;
    $q = $conn->query("SELECT COUNT(*) FROM `gov_target_nav` WHERE `doc_code` = '{$doc}'");
    $tgt = $q ? (int) $q->fetch_row()[0] : -1;
    printf("   دور %-3d الحيُّ %-4d ⇐ المستهدَف %-3d (يُخفى %d بندًا — والمسارُ يبقى)\n",
           $role, $live, $tgt, max(0, $live - $tgt));
}

ems_migration_recorded(__FILE__, $conn, 0);
