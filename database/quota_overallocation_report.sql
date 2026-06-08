-- ════════════════════════════════════════════════════════════════════════
-- تقرير تشخيصي: تجاوز الحصص التعاقدية (Allocation / Quota Over-Allocation Report)
-- نظام EMS — يكشف أين «مجموع الأبناء > سعة الأب» عبر الهرم:
--   عميل ← مشروع ← عقد مورّد ← نوع معدة ← معدّات ← مشغّلون ← ساعات مستهلكة
--
-- نسخة مصحّحة: كل فحص مُجمَّع ملفوف في «جدول مشتق» (subquery) والتصفية في الخارج،
-- لتفادي خطأ MySQL/MariaDB رقم 1247 (reference to group function in HAVING).
--
-- كيفية التشغيل:
--   phpMyAdmin: اختر قاعدة البيانات (equipation_manage) ← تبويب SQL ← الصق الملف ← Go.
--   أو CLI:  mysql -u root equipation_manage < database/quota_overallocation_report.sql
--
-- كل استعلام يُرجع «الحالات المتجاوزة فقط» (الصفر = لا تجاوز). عدد الصفوف = عدد الحالات.
-- منطق المطابقة مطابق لـ Oprators/get_contract_stats.php.
-- الأعمدة النصّية (project_id/supplier_id/equipment/equipment_type/operator) تُحوَّل رقمياً عبر CAST.
-- ════════════════════════════════════════════════════════════════════════


-- ───────────────────────────────────────────────────────────────────────
-- (1) تجاوز عدد المعدات لكل (عقد مورّد + نوع معدة) — أهم فحص
-- ───────────────────────────────────────────────────────────────────────
SELECT *, (allocated_count - contracted_count) AS overage
FROM (
  SELECT
    sc.company_id, sc.project_id, p.name AS project_name,
    sc.supplier_id, s.name AS supplier_name, sc.id AS supplier_contract_id,
    CAST(sce.equip_type AS UNSIGNED) AS equip_type_id, et.type AS type_name,
    SUM(sce.equip_count) AS contracted_count,
    (SELECT COUNT(*) FROM operations o
       LEFT JOIN equipments e ON CAST(NULLIF(o.equipment,'') AS UNSIGNED)=e.id
       WHERE o.status=1
         AND CAST(NULLIF(o.project_id,'')  AS UNSIGNED)=sc.project_id
         AND CAST(NULLIF(o.supplier_id,'') AS UNSIGNED)=sc.supplier_id
         AND (CAST(NULLIF(o.equipment_type,'') AS UNSIGNED)=CAST(sce.equip_type AS UNSIGNED)
              OR e.type=CAST(sce.equip_type AS UNSIGNED))) AS allocated_count
  FROM suppliercontractequipments sce
  JOIN supplierscontracts sc ON sce.contract_id=sc.id AND sc.status=1
  LEFT JOIN suppliers s  ON sc.supplier_id=s.id
  LEFT JOIN project   p  ON sc.project_id=p.id
  LEFT JOIN equipments_types et ON CAST(sce.equip_type AS UNSIGNED)=et.id
  GROUP BY sc.id, CAST(sce.equip_type AS UNSIGNED)
) x
WHERE x.allocated_count > x.contracted_count
ORDER BY overage DESC;


-- ───────────────────────────────────────────────────────────────────────
-- (2) تجاوز فئة المعدات (أساسي / احتياطي)
-- ───────────────────────────────────────────────────────────────────────
SELECT *
FROM (
  SELECT
    sc.company_id, sc.project_id, p.name AS project_name,
    sc.supplier_id, s.name AS supplier_name, sc.id AS supplier_contract_id,
    CAST(sce.equip_type AS UNSIGNED) AS equip_type_id, et.type AS type_name,
    SUM(sce.equip_count_basic) AS contracted_basic,
    (SELECT COUNT(*) FROM operations o
       LEFT JOIN equipments e ON CAST(NULLIF(o.equipment,'') AS UNSIGNED)=e.id
       WHERE o.status=1 AND o.equipment_category='أساسي'
         AND CAST(NULLIF(o.project_id,'')  AS UNSIGNED)=sc.project_id
         AND CAST(NULLIF(o.supplier_id,'') AS UNSIGNED)=sc.supplier_id
         AND (CAST(NULLIF(o.equipment_type,'') AS UNSIGNED)=CAST(sce.equip_type AS UNSIGNED)
              OR e.type=CAST(sce.equip_type AS UNSIGNED))) AS allocated_basic,
    SUM(sce.equip_count_backup) AS contracted_backup,
    (SELECT COUNT(*) FROM operations o
       LEFT JOIN equipments e ON CAST(NULLIF(o.equipment,'') AS UNSIGNED)=e.id
       WHERE o.status=1 AND o.equipment_category='احتياطي'
         AND CAST(NULLIF(o.project_id,'')  AS UNSIGNED)=sc.project_id
         AND CAST(NULLIF(o.supplier_id,'') AS UNSIGNED)=sc.supplier_id
         AND (CAST(NULLIF(o.equipment_type,'') AS UNSIGNED)=CAST(sce.equip_type AS UNSIGNED)
              OR e.type=CAST(sce.equip_type AS UNSIGNED))) AS allocated_backup
  FROM suppliercontractequipments sce
  JOIN supplierscontracts sc ON sce.contract_id=sc.id AND sc.status=1
  LEFT JOIN suppliers s  ON sc.supplier_id=s.id
  LEFT JOIN project   p  ON sc.project_id=p.id
  LEFT JOIN equipments_types et ON CAST(sce.equip_type AS UNSIGNED)=et.id
  GROUP BY sc.id, CAST(sce.equip_type AS UNSIGNED)
) x
WHERE x.allocated_basic > x.contracted_basic
   OR x.allocated_backup > x.contracted_backup
ORDER BY (x.allocated_basic-x.contracted_basic)+(x.allocated_backup-x.contracted_backup) DESC;


-- ───────────────────────────────────────────────────────────────────────
-- (3) تجاوز إجمالي معدات عقد المورّد (كل الأنواع مجتمعة)
-- ───────────────────────────────────────────────────────────────────────
SELECT *, (allocated_total - contracted_total) AS overage
FROM (
  SELECT
    sc.company_id, sc.project_id, p.name AS project_name,
    sc.supplier_id, s.name AS supplier_name, sc.id AS supplier_contract_id,
    (SELECT COALESCE(SUM(x.equip_count),0)
       FROM suppliercontractequipments x WHERE x.contract_id=sc.id) AS contracted_total,
    (SELECT COUNT(*) FROM operations o
       WHERE o.status=1
         AND CAST(NULLIF(o.project_id,'')  AS UNSIGNED)=sc.project_id
         AND CAST(NULLIF(o.supplier_id,'') AS UNSIGNED)=sc.supplier_id) AS allocated_total
  FROM supplierscontracts sc
  LEFT JOIN suppliers s ON sc.supplier_id=s.id
  LEFT JOIN project   p ON sc.project_id=p.id
  WHERE sc.status=1
) x
WHERE x.allocated_total > x.contracted_total
ORDER BY overage DESC;


-- ───────────────────────────────────────────────────────────────────────
-- (4) تجاوز الساعات: المستهلَك (timesheet) > المتعاقد عليه (عقد المورّد)
--     إن أعطى خطأ على عمود forecasted_contracted_hours استبدله بـ equip_total_contract_daily أو ما يقابله.
-- ───────────────────────────────────────────────────────────────────────
SELECT *, (consumed_hours - contracted_hours) AS overage
FROM (
  SELECT
    sc.company_id, sc.project_id, p.name AS project_name,
    sc.supplier_id, s.name AS supplier_name, sc.id AS supplier_contract_id,
    sc.forecasted_contracted_hours AS contracted_hours,
    (SELECT COALESCE(SUM(t.total_work_hours),0)
       FROM timesheet t
       JOIN operations o ON CAST(NULLIF(t.operator,'') AS UNSIGNED)=o.id
       WHERE t.status=1
         AND CAST(NULLIF(o.project_id,'')  AS UNSIGNED)=sc.project_id
         AND CAST(NULLIF(o.supplier_id,'') AS UNSIGNED)=sc.supplier_id) AS consumed_hours
  FROM supplierscontracts sc
  LEFT JOIN suppliers s ON sc.supplier_id=s.id
  LEFT JOIN project   p ON sc.project_id=p.id
  WHERE sc.status=1
) x
WHERE x.contracted_hours > 0 AND x.consumed_hours > x.contracted_hours
ORDER BY overage DESC;


-- ───────────────────────────────────────────────────────────────────────
-- (5) خرق سلامة: معدّة في أكثر من تشغيل ساري في آن واحد
-- ───────────────────────────────────────────────────────────────────────
SELECT *
FROM (
  SELECT
    CAST(NULLIF(o.equipment,'') AS UNSIGNED) AS equipment_id,
    MAX(e.code) AS code, MAX(e.name) AS name,
    COUNT(*) AS active_operations
  FROM operations o
  LEFT JOIN equipments e ON CAST(NULLIF(o.equipment,'') AS UNSIGNED)=e.id
  WHERE o.status=1
  GROUP BY CAST(NULLIF(o.equipment,'') AS UNSIGNED)
) x
WHERE x.active_operations > 1
ORDER BY x.active_operations DESC;


-- ───────────────────────────────────────────────────────────────────────
-- (6) خرق سلامة: سائق/مشغّل نشط على أكثر من معدّة في آن واحد
-- ───────────────────────────────────────────────────────────────────────
SELECT *
FROM (
  SELECT
    ed.driver_id, MAX(d.name) AS driver_name, MAX(d.driver_code) AS driver_code,
    COUNT(DISTINCT ed.equipment_id) AS active_equipments
  FROM equipment_drivers ed
  LEFT JOIN drivers d ON ed.driver_id=d.id
  WHERE ed.status=1
  GROUP BY ed.driver_id
) x
WHERE x.active_equipments > 1
ORDER BY x.active_equipments DESC;


-- ───────────────────────────────────────────────────────────────────────
-- (7) ملخّص تنفيذي: عدد الحالات المتجاوزة لكل فحص
-- ───────────────────────────────────────────────────────────────────────
SELECT 'تجاوز عدد المعدات (عقد+نوع)' AS check_name, COUNT(*) AS violations FROM (
  SELECT 1 FROM (
    SELECT sc.id AS scid, CAST(sce.equip_type AS UNSIGNED) AS t,
      SUM(sce.equip_count) AS cc,
      (SELECT COUNT(*) FROM operations o
         LEFT JOIN equipments e ON CAST(NULLIF(o.equipment,'') AS UNSIGNED)=e.id
         WHERE o.status=1
           AND CAST(NULLIF(o.project_id,'')  AS UNSIGNED)=sc.project_id
           AND CAST(NULLIF(o.supplier_id,'') AS UNSIGNED)=sc.supplier_id
           AND (CAST(NULLIF(o.equipment_type,'') AS UNSIGNED)=CAST(sce.equip_type AS UNSIGNED)
                OR e.type=CAST(sce.equip_type AS UNSIGNED))) AS ac
    FROM suppliercontractequipments sce
    JOIN supplierscontracts sc ON sce.contract_id=sc.id AND sc.status=1
    GROUP BY sc.id, CAST(sce.equip_type AS UNSIGNED)
  ) a WHERE a.ac > a.cc
) v1
UNION ALL
SELECT 'إجمالي معدات عقد المورّد', COUNT(*) FROM (
  SELECT 1 FROM (
    SELECT sc.id AS scid,
      (SELECT COALESCE(SUM(x.equip_count),0) FROM suppliercontractequipments x WHERE x.contract_id=sc.id) AS cc,
      (SELECT COUNT(*) FROM operations o WHERE o.status=1
         AND CAST(NULLIF(o.project_id,'')  AS UNSIGNED)=sc.project_id
         AND CAST(NULLIF(o.supplier_id,'') AS UNSIGNED)=sc.supplier_id) AS ac
    FROM supplierscontracts sc WHERE sc.status=1
  ) a WHERE a.ac > a.cc
) v2
UNION ALL
SELECT 'معدّة في تشغيلين ساريين', COUNT(*) FROM (
  SELECT CAST(NULLIF(o.equipment,'') AS UNSIGNED) eq FROM operations o WHERE o.status=1
  GROUP BY CAST(NULLIF(o.equipment,'') AS UNSIGNED) HAVING COUNT(*)>1
) v3
UNION ALL
SELECT 'سائق على أكثر من معدّة', COUNT(*) FROM (
  SELECT ed.driver_id FROM equipment_drivers ed WHERE ed.status=1
  GROUP BY ed.driver_id HAVING COUNT(DISTINCT ed.equipment_id)>1
) v4;
