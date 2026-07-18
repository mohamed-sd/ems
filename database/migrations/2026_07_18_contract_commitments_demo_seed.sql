-- ══════════════════════════════════════════════════════════════════════════════
-- بذرة تجريبية · التزامات العقد (§ت.2) — company_id = 4 · created_by = 13 (مسؤول المبيعات)
-- مربوطة بعقودٍ حقيقية (1..8). تغطّي الأنواع السبعة + الطرفين + وحداتٍ متعددة + أحكام العجز/الزيادة.
-- idempotent: تُحذف صفوف CMT-00% للشركة 4 أولًا. التنفيذ بعميل utf8mb4.
-- ══════════════════════════════════════════════════════════════════════════════
SET NAMES utf8mb4;

DELETE FROM `contract_commitments` WHERE `company_id` = 4 AND `commitment_code` LIKE 'CMT-00%';

INSERT INTO `contract_commitments`
  (`company_id`,`commitment_code`,`party_scope`,`contract_ref`,`commitment_type`,`unit_type`,`qty`,`period`,`obliged_party`,`shortfall_rule`,`surplus_rule`,`note`,`created_by`)
VALUES
  -- عقد 1 (تأجير بالساعة): عدد معدات + إتاحة يومية + حد أدنى مضمون
  (4,'CMT-0001','client',1,'equipment_count',           NULL, 6.00,   'contract','company','invoice_actual','same_price',    'عدد المعدات المتعاقد عليها',4),
  (4,'CMT-0002','client',1,'daily_availability_hours',  'hour',20.00,  'daily',   'company','penalty',       'same_price',    'ساعات الإتاحة اليومية للأسطول',4),
  (4,'CMT-0003','client',1,'min_guaranteed',            'hour',600.00, 'monthly', 'company','invoice_actual','same_price',    'حدٌّ أدنى مضمونٌ شهريًّا',4),
  -- عقد 2: إجمالي ساعات المدة (السقف)
  (4,'CMT-0004','client',2,'total_qty',                 'hour',7200.00,'contract','company','carry_over',    'different_price','إجمالي ساعات المدة — السقف الكلي',4),
  -- عقد 3 (نقل بالطن): كمية شهرية + إجمالي المدة
  (4,'CMT-0005','client',3,'period_qty',                'ton', 5000.00,'monthly', 'company','invoice_actual','same_price',    'كمية طنٍّ شهرية',4),
  (4,'CMT-0006','client',3,'total_qty',                 'ton', 60000.00,'contract','company','negotiate',    'pre_approval',  'إجمالي الأطنان المتعاقدة',4),
  -- عقد 4: طاقة تشغيلية مساندة
  (4,'CMT-0007','client',4,'capacity_support',          NULL, 3.00,   'contract','company','invoice_actual','open',          'طاقةٌ تشغيليةٌ مساندة (التزامٌ مساند)',4),
  -- عقد 6 (تخريم بالمتر): أمتار شهرية
  (4,'CMT-0008','client',6,'period_qty',                'meter',1200.00,'monthly','company','invoice_actual','same_price',    'أمتارٌ محفورةٌ شهرية',4),
  -- عقد 7 (التزام على المورد): إتاحة أسطول المورد اليومية
  (4,'CMT-0009','supplier',7,'daily_availability_hours','hour',18.00,  'daily',   'supplier','waive_if_client','not_billable','إتاحةُ أسطول المورد اليومية',4),
  -- عقد 8 (التزام على المورد): عدد معدات المورد
  (4,'CMT-0010','supplier',8,'equipment_count',         NULL, 4.00,   'contract','supplier','extend_term',   'same_price',    'عدد معدات المورد ضمن العقد',4);

-- ══════════════════════════════════════════════════════════════════════════════
-- ROLLBACK: DELETE FROM contract_commitments WHERE company_id=4 AND commitment_code LIKE 'CMT-00%';
-- ══════════════════════════════════════════════════════════════════════════════
