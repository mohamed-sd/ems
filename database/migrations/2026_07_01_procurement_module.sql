-- ═══════════════════════════════════════════════════════════════════════════
-- 2026_07_01_procurement_module.sql
-- وحدة المشتريات والإمداد التشغيلي (EQUIP-OPE-S09) — النسخة المخصّصة لـ EMS
--
-- مبادئ التنفيذ:
--   • مستقلة تماماً: كل الجداول ببادئة proc_ ولا تحتوي أي مفتاح أجنبي (FK) نحو
--     جداول النظام القائمة (equipments/project/mnt_order/suppliers/users). تُخزَّن
--     المعرّفات كأرقام وتُربط بالـ JOIN عند العرض فقط ⇒ لا كسر ولا تأثير على الموجود.
--   • عزل الشركة عبر company_id في كل جدول (نفس نمط mnt_).
--   • حذف ناعم (is_deleted/deleted_at/deleted_by) على الكيانات الرئيسية.
--   • آمنة لإعادة التشغيل (IF NOT EXISTS / NOT EXISTS / INSERT IGNORE).
--
-- طريقة التطبيق (يجب utf8mb4 حتى لا تتشوّه العربية):
--   mysql -uroot --default-character-set=utf8mb4 equipation_manage < 2026_07_01_procurement_module.sql
-- ═══════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ───────────────────────────────────────────────────────────────────────────
-- 1) الدور الجديد: مسؤول المشتريات (أعلى المستوى، نطاق عام) — idempotent
-- ───────────────────────────────────────────────────────────────────────────
INSERT INTO roles (name, parent_role_id, level, role_scope, status)
SELECT 'مسؤول المشتريات', NULL, 1, 'gloable', '1'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name = 'مسؤول المشتريات');

SET @proc_role := (SELECT id FROM roles WHERE name = 'مسؤول المشتريات' ORDER BY id LIMIT 1);

-- ───────────────────────────────────────────────────────────────────────────
-- 2) الجداول (proc_*)
-- ───────────────────────────────────────────────────────────────────────────

-- 2.1 قيم مرجعية (فئات/وحدات/أنواع مخازن/طبيعة مادة) — نظير mnt_lookup
CREATE TABLE IF NOT EXISTS proc_lookup (
  id INT NOT NULL AUTO_INCREMENT,
  company_id INT NOT NULL,
  type VARCHAR(40) NOT NULL COMMENT 'فئة صنف / وحدة قياس / نوع مخزن / طبيعة مادة',
  name VARCHAR(150) NOT NULL,
  extra VARCHAR(255) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  deleted_at DATETIME DEFAULT NULL,
  deleted_by INT DEFAULT NULL,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_proc_lookup_company_type (company_id, type),
  KEY idx_proc_lookup_deleted (is_deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.2 المورد التشغيلي (مستقل عن suppliers — لا هرم حصص، §0/S02)
CREATE TABLE IF NOT EXISTS proc_supplier (
  id INT NOT NULL AUTO_INCREMENT,
  company_id INT NOT NULL,
  code VARCHAR(50) DEFAULT NULL,
  name VARCHAR(200) NOT NULL,
  supply_role VARCHAR(30) NOT NULL DEFAULT 'تشغيلي' COMMENT 'تشغيلي دائماً في هذه الوحدة',
  dealing_nature VARCHAR(255) DEFAULT NULL COMMENT 'قطع/زيوت/فلاتر/خدمات إصلاح',
  contact_person VARCHAR(150) DEFAULT NULL,
  phone VARCHAR(50) DEFAULT NULL,
  email VARCHAR(150) DEFAULT NULL,
  address MEDIUMTEXT,
  payment_terms VARCHAR(100) DEFAULT NULL,
  notes MEDIUMTEXT,
  status TINYINT(1) NOT NULL DEFAULT 1,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  deleted_at DATETIME DEFAULT NULL,
  deleted_by INT DEFAULT NULL,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_proc_supplier_company (company_id),
  KEY idx_proc_supplier_deleted (is_deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.3 المخازن/المواقع (بسيط)
CREATE TABLE IF NOT EXISTS proc_warehouse (
  id INT NOT NULL AUTO_INCREMENT,
  company_id INT NOT NULL,
  code VARCHAR(50) DEFAULT NULL,
  name VARCHAR(150) NOT NULL,
  type VARCHAR(30) NOT NULL DEFAULT 'مخزن' COMMENT 'مخزن / ورشة / مباشر للآلية',
  location VARCHAR(255) DEFAULT NULL,
  notes MEDIUMTEXT,
  status TINYINT(1) NOT NULL DEFAULT 1,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  deleted_at DATETIME DEFAULT NULL,
  deleted_by INT DEFAULT NULL,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_proc_wh_company (company_id),
  KEY idx_proc_wh_deleted (is_deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.4 بطاقة الصنف / القطعة الحرجة (§15.5)
CREATE TABLE IF NOT EXISTS proc_item (
  id INT NOT NULL AUTO_INCREMENT,
  company_id INT NOT NULL,
  code VARCHAR(50) DEFAULT NULL,
  name VARCHAR(200) NOT NULL,
  category VARCHAR(100) DEFAULT NULL COMMENT 'فلاتر/زيوت وشحوم/إسبيرات/بطاريات/أسنان جردل/سيور',
  material_nature VARCHAR(30) NOT NULL DEFAULT 'قابل للتخزين' COMMENT 'قابل للتخزين / غير قابل للتخزين / خدمة ومصنعيات',
  uom VARCHAR(20) NOT NULL DEFAULT 'قطعة' COMMENT 'قطعة/لتر/كجم',
  is_critical TINYINT(1) NOT NULL DEFAULT 0,
  min_qty DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  max_qty DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  lead_time_days INT NOT NULL DEFAULT 0,
  safety_stock DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  served_equipment_id INT DEFAULT NULL COMMENT 'equipments.id (بلا FK)',
  served_category VARCHAR(100) DEFAULT NULL,
  notes MEDIUMTEXT,
  status TINYINT(1) NOT NULL DEFAULT 1,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  deleted_at DATETIME DEFAULT NULL,
  deleted_by INT DEFAULT NULL,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_proc_item_company (company_id),
  KEY idx_proc_item_critical (company_id, is_critical),
  KEY idx_proc_item_deleted (is_deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.5 قواعد إعادة الطلب (§15.6)
CREATE TABLE IF NOT EXISTS proc_orderpoint (
  id INT NOT NULL AUTO_INCREMENT,
  company_id INT NOT NULL,
  item_id INT NOT NULL COMMENT 'proc_item.id',
  warehouse_id INT DEFAULT NULL COMMENT 'proc_warehouse.id',
  min_qty DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  max_qty DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  trigger_qty DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'ROP - نقطة إعادة الطلب',
  safety_stock DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  mode VARCHAR(20) NOT NULL DEFAULT 'يدوي' COMMENT 'تلقائي / يدوي',
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  deleted_at DATETIME DEFAULT NULL,
  deleted_by INT DEFAULT NULL,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_proc_orderpoint_company (company_id),
  KEY idx_proc_orderpoint_item (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.6 طلب الشراء التشغيلي + سطوره (§15.1)
CREATE TABLE IF NOT EXISTS proc_request (
  id INT NOT NULL AUTO_INCREMENT,
  company_id INT NOT NULL,
  code VARCHAR(50) DEFAULT NULL,
  need_source VARCHAR(30) NOT NULL DEFAULT 'نقص مخزون' COMMENT 'خطة وقائية/أمر صيانة/نقص مخزون/إعادة طلب',
  source_ref VARCHAR(100) DEFAULT NULL COMMENT 'مرجع المصدر (خطة/أمر/نقطة طلب)',
  op_classification VARCHAR(20) NOT NULL DEFAULT 'استهلاكية' COMMENT 'وقائية/تصحيحية/رأسمالية/استهلاكية',
  requesting_dept VARCHAR(40) DEFAULT NULL,
  equipment_id INT DEFAULT NULL,
  project_id INT DEFAULT NULL,
  priority VARCHAR(20) NOT NULL DEFAULT 'عادي' COMMENT 'عادي/عاجل/حرج',
  fin_approval_state VARCHAR(20) NOT NULL DEFAULT 'بانتظار' COMMENT 'بانتظار/معتمد مالياً/مرفوض',
  state VARCHAR(30) NOT NULL DEFAULT 'مسودة' COMMENT 'مسودة/مقدَّم/اعتماد المشتريات/مراجعة مالية/معتمد مالياً/محوَّل لأمر شراء/مغلق/مرفوض',
  notes MEDIUMTEXT,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  deleted_at DATETIME DEFAULT NULL,
  deleted_by INT DEFAULT NULL,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_proc_request_company_state (company_id, state),
  KEY idx_proc_request_deleted (is_deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proc_request_line (
  id INT NOT NULL AUTO_INCREMENT,
  company_id INT NOT NULL,
  request_id INT NOT NULL,
  item_id INT DEFAULT NULL COMMENT 'proc_item.id (اختياري)',
  item_name VARCHAR(200) NOT NULL COMMENT 'لقطة نصية للصنف',
  qty DECIMAL(12,2) NOT NULL DEFAULT 1.00,
  op_classification VARCHAR(20) DEFAULT NULL COMMENT 'تصنيف على مستوى البند',
  note VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_proc_reqline_request (request_id),
  CONSTRAINT fk_proc_reqline_req FOREIGN KEY (request_id) REFERENCES proc_request (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.7 أمر الشراء + سطوره (§15.2)
CREATE TABLE IF NOT EXISTS proc_order (
  id INT NOT NULL AUTO_INCREMENT,
  company_id INT NOT NULL,
  code VARCHAR(50) DEFAULT NULL,
  supplier_id INT DEFAULT NULL COMMENT 'proc_supplier.id',
  request_id INT DEFAULT NULL COMMENT 'proc_request.id',
  fin_approval_ref VARCHAR(100) DEFAULT NULL COMMENT 'مرجع الاعتماد المالي (شرط الإصدار)',
  op_classification VARCHAR(20) NOT NULL DEFAULT 'استهلاكية',
  currency VARCHAR(10) NOT NULL DEFAULT 'SDG' COMMENT 'SDG/USD',
  fx_rate DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
  payment_time VARCHAR(20) NOT NULL DEFAULT 'فوري' COMMENT 'فوري/مؤجل/آجل 30/60/90',
  expected_receipt_type VARCHAR(20) NOT NULL DEFAULT 'مخزن' COMMENT 'مخزن/مباشر للمعدة/مشروع/ورشة',
  total_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  state VARCHAR(30) NOT NULL DEFAULT 'مسودة' COMMENT 'مسودة/مؤكَّد/استلام أولي/استلام نهائي/مطابَق/مغلق',
  notes MEDIUMTEXT,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  deleted_at DATETIME DEFAULT NULL,
  deleted_by INT DEFAULT NULL,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_proc_order_company_state (company_id, state),
  KEY idx_proc_order_deleted (is_deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proc_order_line (
  id INT NOT NULL AUTO_INCREMENT,
  company_id INT NOT NULL,
  order_id INT NOT NULL,
  item_id INT DEFAULT NULL,
  item_name VARCHAR(200) NOT NULL,
  qty DECIMAL(12,2) NOT NULL DEFAULT 1.00,
  unit_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  op_classification VARCHAR(20) DEFAULT NULL,
  subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_proc_ordline_order (order_id),
  CONSTRAINT fk_proc_ordline_ord FOREIGN KEY (order_id) REFERENCES proc_order (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.8 عهدة الاستلام المؤقت + سطوره (§15.3)
CREATE TABLE IF NOT EXISTS proc_receipt_custody (
  id INT NOT NULL AUTO_INCREMENT,
  company_id INT NOT NULL,
  code VARCHAR(50) DEFAULT NULL,
  holder_id INT DEFAULT NULL COMMENT 'المستلِم (users/employees.id بلا FK)',
  holder_name VARCHAR(150) DEFAULT NULL COMMENT 'لقطة نصية للمستلِم',
  receipt_date DATE DEFAULT NULL,
  supplier_id INT DEFAULT NULL COMMENT 'proc_supplier.id',
  order_id INT DEFAULT NULL COMMENT 'proc_order.id',
  receipt_location VARCHAR(255) DEFAULT NULL COMMENT 'عطبرة/موقع المورد/…',
  expected_destination VARCHAR(30) NOT NULL DEFAULT 'مخزن' COMMENT 'مخزن/ورشة/مشروع/معدة',
  state VARCHAR(30) NOT NULL DEFAULT 'مستلَمة' COMMENT 'مستلَمة/قيد الترحيل/مسلَّمة للوجهة',
  notes MEDIUMTEXT,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  deleted_at DATETIME DEFAULT NULL,
  deleted_by INT DEFAULT NULL,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_proc_rc_company_state (company_id, state),
  KEY idx_proc_rc_deleted (is_deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proc_receipt_line (
  id INT NOT NULL AUTO_INCREMENT,
  company_id INT NOT NULL,
  custody_id INT NOT NULL,
  item_id INT DEFAULT NULL,
  item_name VARCHAR(200) NOT NULL,
  qty DECIMAL(12,2) NOT NULL DEFAULT 1.00,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_proc_rcline_custody (custody_id),
  CONSTRAINT fk_proc_rcline_custody FOREIGN KEY (custody_id) REFERENCES proc_receipt_custody (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.9 حركة المخزون (بديل الجرد المستمر — الرصيد = SUM عند العرض) (§9/§15.7)
CREATE TABLE IF NOT EXISTS proc_stock_move (
  id INT NOT NULL AUTO_INCREMENT,
  company_id INT NOT NULL,
  item_id INT NOT NULL,
  warehouse_id INT DEFAULT NULL,
  move_type VARCHAR(20) NOT NULL COMMENT 'استلام/صرف/إرجاع/تحويل',
  qty DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  ref_type VARCHAR(30) DEFAULT NULL COMMENT 'proc_order/proc_issue/proc_receipt/يدوي',
  ref_id INT DEFAULT NULL,
  note VARCHAR(255) DEFAULT NULL,
  moved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_proc_move_company (company_id),
  KEY idx_proc_move_item_wh (item_id, warehouse_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.10 الصرف وتحميل التكلفة + سطوره (§15.8)
CREATE TABLE IF NOT EXISTS proc_issue (
  id INT NOT NULL AUTO_INCREMENT,
  company_id INT NOT NULL,
  code VARCHAR(50) DEFAULT NULL,
  warehouse_id INT DEFAULT NULL,
  holder_id INT DEFAULT NULL COMMENT 'المستلِم',
  holder_name VARCHAR(150) DEFAULT NULL,
  issue_date DATE DEFAULT NULL,
  equipment_id INT DEFAULT NULL COMMENT 'بُعد تكلفة',
  project_id INT DEFAULT NULL COMMENT 'بُعد تكلفة',
  maintenance_order_id INT DEFAULT NULL COMMENT 'mnt_order.id (بلا FK)',
  maint_type VARCHAR(20) DEFAULT NULL COMMENT 'وقائية/تصحيحية/رأسمالية',
  contract_id INT DEFAULT NULL,
  supplier_id INT DEFAULT NULL,
  total_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  state VARCHAR(20) NOT NULL DEFAULT 'مسودة' COMMENT 'مسودة/محجوز/مصروف/محمَّل التكلفة',
  notes MEDIUMTEXT,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  deleted_at DATETIME DEFAULT NULL,
  deleted_by INT DEFAULT NULL,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_proc_issue_company_state (company_id, state),
  KEY idx_proc_issue_deleted (is_deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proc_issue_line (
  id INT NOT NULL AUTO_INCREMENT,
  company_id INT NOT NULL,
  issue_id INT NOT NULL,
  item_id INT DEFAULT NULL,
  item_name VARCHAR(200) NOT NULL,
  qty DECIMAL(12,2) NOT NULL DEFAULT 1.00,
  unit_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_proc_issline_issue (issue_id),
  CONSTRAINT fk_proc_issline_iss FOREIGN KEY (issue_id) REFERENCES proc_issue (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.11 سلسلة عهدة الصرف (§15.9)
CREATE TABLE IF NOT EXISTS proc_custody (
  id INT NOT NULL AUTO_INCREMENT,
  company_id INT NOT NULL,
  issue_id INT DEFAULT NULL COMMENT 'proc_issue.id',
  issue_line_id INT DEFAULT NULL COMMENT 'proc_issue_line.id',
  item_id INT DEFAULT NULL,
  item_name VARCHAR(200) DEFAULT NULL,
  holder_id INT DEFAULT NULL,
  holder_name VARCHAR(150) DEFAULT NULL,
  transfer_date DATE DEFAULT NULL,
  equipment_id INT DEFAULT NULL,
  project_id INT DEFAULT NULL,
  maintenance_order_id INT DEFAULT NULL,
  qty_issued DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  qty_returned DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  qty_consumed DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'المصروفة - المرتجعة',
  state VARCHAR(20) NOT NULL DEFAULT 'مصروفة' COMMENT 'مصروفة/إرجاع جزئي/مستهلكة/مُقفلة',
  notes MEDIUMTEXT,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  deleted_at DATETIME DEFAULT NULL,
  deleted_by INT DEFAULT NULL,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_proc_custody_company_state (company_id, state),
  KEY idx_proc_custody_deleted (is_deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────────────────
-- 3) تسجيل الشاشات في جدول modules (idempotent عبر NOT EXISTS على code)
--    is_link='1' يعني تظهر في القائمة الجانبية.
-- ───────────────────────────────────────────────────────────────────────────
-- ملاحظة مهمة: أسماء الملفات تحمل لاحقة "_proc" عمداً حتى لا يلتقطها حارس الصلاحيات
-- المركزي (get_module_id_by_script_path يطابق code LIKE '%basename%')؛ فلولاها كانت
-- 'Procurement/dashboard.php' تُظلّل 'main/dashboard.php' وتُحدث حلقة إعادة توجيه للجميع.
INSERT INTO modules (name, code, owner_role_id, is_link, icon, display_order)
SELECT * FROM (
  SELECT 'لوحة المشتريات'        AS name, 'Procurement/dashboard_proc.php'        AS code, @proc_role AS owner_role_id, '1' AS is_link, 'fa fa-gauge-high'         AS icon, 10 AS display_order UNION ALL
  SELECT 'كتالوج الأصناف',             'Procurement/items_proc.php',             @proc_role, '1', 'fa fa-boxes-stacked',        20 UNION ALL
  SELECT 'طلبات الشراء',               'Procurement/requests_proc.php',          @proc_role, '1', 'fa fa-file-lines',           30 UNION ALL
  SELECT 'أوامر الشراء',               'Procurement/orders_proc.php',            @proc_role, '1', 'fa fa-file-invoice-dollar',  40 UNION ALL
  SELECT 'عهدة الاستلام المؤقت',       'Procurement/receipt_custody_proc.php',   @proc_role, '1', 'fa fa-truck-ramp-box',       50 UNION ALL
  SELECT 'المخزون التشغيلي',           'Procurement/stock_proc.php',             @proc_role, '1', 'fa fa-warehouse',            60 UNION ALL
  SELECT 'الصرف والعهدة',              'Procurement/issue_proc.php',             @proc_role, '1', 'fa fa-hand-holding-box',     70 UNION ALL
  SELECT 'قواعد إعادة الطلب',          'Procurement/reordering_proc.php',        @proc_role, '1', 'fa fa-arrows-rotate',        80 UNION ALL
  SELECT 'الموردون التشغيليون',        'Procurement/suppliers_proc.php',         @proc_role, '1', 'fa fa-truck-field',          90 UNION ALL
  SELECT 'بيانات مرجعية',             'Procurement/master_data_proc.php',       @proc_role, '1', 'fa fa-sliders',             100
) AS new_mods
WHERE NOT EXISTS (
  SELECT 1 FROM modules m WHERE m.code = new_mods.code
);

-- ───────────────────────────────────────────────────────────────────────────
-- 4) منح دور المشتريات كل الصلاحيات على كل شاشاته (idempotent عبر INSERT IGNORE
--    والمفتاح الفريد role_id+module_id)
-- ───────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
SELECT @proc_role, m.id, 1, 1, 1, 1
FROM modules m
WHERE m.owner_role_id = @proc_role;

-- ───────────────────────────────────────────────────────────────────────────
-- تقرير سريع بعد التطبيق
-- ───────────────────────────────────────────────────────────────────────────
SELECT @proc_role AS proc_role_id;
SELECT id, name, code, display_order FROM modules WHERE owner_role_id = @proc_role ORDER BY display_order;
