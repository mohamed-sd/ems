-- ═══════════════════════════════════════════════════════════════════════════
-- EMS · قسم الصيانة (Maintenance Module) — هجرة M1
-- @date 2026-06-20
--
-- الغرض: إضافة قسم صيانة متكامل كدور جديد، عبر بيانات لا كود (نمط بقية الأدوار):
--   (4.1) دوران جديدان: «ادارة الصيانة» (مدير) + «مشرف صيانة» (فرعي).
--   (4.2) تسجيل 5 شاشات صيانة في modules + نقل ملكية شاشة تصنيف الأعطال للصيانة.
--   (4.3) صلاحيات صريحة: المدير = كل الصلاحيات، المشرف = عرض فقط.
--   (4.4) إنشاء 9 جداول mnt_ (utf8mb4_unicode_ci + company_id NOT NULL + حذف ناعم).
--   (4.5) محور الصحة الجديد على operations: equipment_health + حقول مرافقة.
--   (4.6) فهارس الأداء.
--
-- مبادئ (من القرارات الملزمة):
--   • إضافي فقط — لا تعديل/حذف لأي عمود أو جدول قائم سوى ALTER ADD على operations.
--   • عزل الشركات: company_id INT NOT NULL على كل جداول الصيانة (الفلترة في الكود).
--   • الربط بالجداول القائمة عبر مفاتيح رقمية id فقط. لتفادي هشاشة الهجرة ومنع حظر
--     الحذف المتبادل، لا نضيف قيود FK صلبة نحو الجداول الخارجية (equipments/users/…)
--     بل أعمدة INT مفهرسة. قيود FK الصلبة محصورة بين جداول mnt_ نفسها (أب→أبناء CASCADE).
--   • هذه الهجرة مكتوبة لتكون قابلة لإعادة التشغيل بأمان (idempotent).
--
-- ┌─ قبل التطبيق ─────────────────────────────────────────────────────────────┐
-- │ خذ نسخة احتياطية كاملة:                                                     │
-- │   mysqldump -uroot --single-transaction --default-character-set=utf8mb4 \  │
-- │     equipation_manage > backup_before_maintenance_2026_06_20.sql           │
-- └───────────────────────────────────────────────────────────────────────────┘
-- ═══════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────────────────
-- (4.1) الأدوار — «ادارة الصيانة» (مدير، level=1) + «مشرف صيانة» (فرعي، level=2)
-- ───────────────────────────────────────────────────────────────────────────
INSERT INTO roles (name, parent_role_id, level, role_scope, status)
SELECT 'ادارة الصيانة', NULL, 1, 'gloable', '1'
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM (SELECT id FROM roles WHERE name = 'ادارة الصيانة') t);

SET @mnt_mgr := (SELECT id FROM roles WHERE name = 'ادارة الصيانة' ORDER BY id LIMIT 1);

INSERT INTO roles (name, parent_role_id, level, role_scope, status)
SELECT 'مشرف صيانة', @mnt_mgr, 2, 'gloable', '1'
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM (SELECT id FROM roles WHERE name = 'مشرف صيانة') t);

SET @mnt_sup := (SELECT id FROM roles WHERE name = 'مشرف صيانة' ORDER BY id LIMIT 1);

-- ───────────────────────────────────────────────────────────────────────────
-- (4.2) الشاشات (modules) — owner_role_id = دور «ادارة الصيانة»
-- ملاحظة: شاشة البلاغات موحّدة للجميع (تُعرض أيضاً في التوبار لكل مستخدم) لكنها
-- تُسجَّل كموديول لتظهر في قائمة دور الصيانة ولفحص الصلاحية على القائمة.
-- ───────────────────────────────────────────────────────────────────────────
INSERT INTO modules (name, code, owner_role_id, is_link, icon, display_order)
SELECT 'البلاغات', 'Maintenance/breakdowns.php', @mnt_mgr, '1', 'fa fa-triangle-exclamation', 10
FROM dual WHERE NOT EXISTS (SELECT 1 FROM (SELECT id FROM modules WHERE code = 'Maintenance/breakdowns.php') t);

INSERT INTO modules (name, code, owner_role_id, is_link, icon, display_order)
SELECT 'أوامر الصيانة', 'Maintenance/orders.php', @mnt_mgr, '1', 'fa fa-wrench', 20
FROM dual WHERE NOT EXISTS (SELECT 1 FROM (SELECT id FROM modules WHERE code = 'Maintenance/orders.php') t);

INSERT INTO modules (name, code, owner_role_id, is_link, icon, display_order)
SELECT 'التفتيش الفني', 'Maintenance/inspections.php', @mnt_mgr, '1', 'fa fa-clipboard-check', 30
FROM dual WHERE NOT EXISTS (SELECT 1 FROM (SELECT id FROM modules WHERE code = 'Maintenance/inspections.php') t);

INSERT INTO modules (name, code, owner_role_id, is_link, icon, display_order)
SELECT 'الخطة الوقائية', 'Maintenance/preventive_plans.php', @mnt_mgr, '1', 'fa fa-calendar-check', 40
FROM dual WHERE NOT EXISTS (SELECT 1 FROM (SELECT id FROM modules WHERE code = 'Maintenance/preventive_plans.php') t);

INSERT INTO modules (name, code, owner_role_id, is_link, icon, display_order)
SELECT 'إعدادات الصيانة', 'Maintenance/master_data.php', @mnt_mgr, '1', 'fa fa-sliders', 50
FROM dual WHERE NOT EXISTS (SELECT 1 FROM (SELECT id FROM modules WHERE code = 'Maintenance/master_data.php') t);

-- نقل ملكية شاشة تصنيف الأعطال من دور الأسطول إلى دور الصيانة (DEC-07).
-- إضافي وغير مدمّر: تغيير owner_role_id فقط (يتحكم في ظهور القائمة)، وبيانات
-- failure_codes نفسها وصلاحيات الأدوار القديمة تبقى دون مساس.
UPDATE modules SET owner_role_id = @mnt_mgr, display_order = 60
WHERE code = 'Equipments/manage_failure_codes.php';

-- ───────────────────────────────────────────────────────────────────────────
-- (4.3) الصلاحيات (role_permissions) — صريحة لكلا الدورين (لا اعتماد على الوراثة)
-- المدير: كل الصلاحيات · المشرف: عرض فقط. UNIQUE(role_id,module_id)+IGNORE ⇒ idempotent.
-- ───────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
SELECT @mnt_mgr, m.id, 1, 1, 1, 1
FROM modules m
WHERE m.code IN (
    'Maintenance/breakdowns.php', 'Maintenance/orders.php', 'Maintenance/inspections.php',
    'Maintenance/preventive_plans.php', 'Maintenance/master_data.php', 'Equipments/manage_failure_codes.php'
);

INSERT IGNORE INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
SELECT @mnt_sup, m.id, 1, 0, 0, 0
FROM modules m
WHERE m.code IN (
    'Maintenance/breakdowns.php', 'Maintenance/orders.php', 'Maintenance/inspections.php',
    'Maintenance/preventive_plans.php', 'Maintenance/master_data.php', 'Equipments/manage_failure_codes.php'
);

-- ───────────────────────────────────────────────────────────────────────────
-- (4.4) جداول الصيانة الـ9 — utf8mb4_unicode_ci + company_id NOT NULL + حذف ناعم
-- ترتيب الإنشاء: الأب قبل الأبناء (للـ FK الداخلية CASCADE).
-- ───────────────────────────────────────────────────────────────────────────

-- (1) mnt_lookup — كتالوج موحّد (سبب عطل/سبب توقّف/نوع مهمة/ورشة)
CREATE TABLE IF NOT EXISTS mnt_lookup (
    id          INT NOT NULL AUTO_INCREMENT,
    company_id  INT NOT NULL COMMENT 'عزل الشركة (إجباري)',
    type        VARCHAR(40)  NOT NULL COMMENT 'سبب عطل/سبب توقّف/نوع مهمة/ورشة',
    name        VARCHAR(150) NOT NULL,
    extra       VARCHAR(255) NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    is_deleted  TINYINT(1)   NOT NULL DEFAULT 0,
    deleted_at  DATETIME     NULL,
    deleted_by  INT          NULL,
    created_by  INT          NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_lookup_company_type (company_id, type),
    KEY idx_lookup_deleted (is_deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- (2) mnt_breakdown — تذكرة البلاغ الموحّدة
CREATE TABLE IF NOT EXISTS mnt_breakdown (
    id              INT NOT NULL AUTO_INCREMENT,
    company_id      INT NOT NULL COMMENT 'عزل الشركة (إجباري)',
    code            VARCHAR(50)  NULL COMMENT 'مرجع البلاغ، مثل BR-2026-0001',
    equipment_id    INT          NULL COMMENT 'FK→equipments.id (ربط رقمي)',
    project_id      INT          NULL COMMENT 'FK→project.id',
    reported_by     INT          NULL COMMENT 'FK→users.id (المُبلِّغ)',
    reporter_dept   VARCHAR(100) NULL COMMENT 'القسم المُبلِّغ',
    report_datetime DATETIME     NULL,
    failure_code_id INT          NULL COMMENT 'FK→failure_codes.id (إعادة استخدام دون تعديل)',
    severity        VARCHAR(30)  NULL COMMENT 'منخفضة/متوسطة/عالية/حرجة',
    is_stopped      TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'هل المعدة متوقفة',
    description     TEXT         NULL,
    attachment      VARCHAR(255) NULL,
    order_id        INT          NULL COMMENT 'FK→mnt_order.id بعد التحويل لأمر',
    state           VARCHAR(30)  NOT NULL DEFAULT 'جديد' COMMENT 'جديد/قيد التقييم/محوّل/مغلق',
    is_deleted      TINYINT(1)   NOT NULL DEFAULT 0,
    deleted_at      DATETIME     NULL,
    deleted_by      INT          NULL,
    created_by      INT          NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_breakdown_eq_company_state (equipment_id, company_id, state),
    KEY idx_breakdown_company_state (company_id, state),
    KEY idx_breakdown_order (order_id),
    KEY idx_breakdown_deleted (is_deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- (3) mnt_order — أمر الصيانة (المحور)
CREATE TABLE IF NOT EXISTS mnt_order (
    id                INT NOT NULL AUTO_INCREMENT,
    company_id        INT NOT NULL COMMENT 'عزل الشركة (إجباري)',
    code              VARCHAR(50)  NULL COMMENT 'مرجع الأمر، مثل MNT-2026-0001',
    breakdown_id      INT          NULL COMMENT 'FK→mnt_breakdown.id (مصدر بلاغ)',
    plan_id           INT          NULL COMMENT 'FK→mnt_plan.id (مصدر وقائي)',
    inspection_id     INT          NULL COMMENT 'FK→mnt_inspection.id (مصدر تفتيش)',
    equipment_id      INT          NULL COMMENT 'FK→equipments.id',
    project_id        INT          NULL COMMENT 'FK→project.id',
    source            VARCHAR(20)  NOT NULL DEFAULT 'بلاغ' COMMENT 'بلاغ/وقائي/تفتيش',
    maint_type        VARCHAR(50)  NULL COMMENT 'نوع الصيانة',
    priority          VARCHAR(20)  NULL,
    cost_party        VARCHAR(20)  NULL COMMENT 'جهة التكلفة: داخلي/خارجي',
    vendor_id         INT          NULL COMMENT 'FK→suppliers.id (ورشة خارجية)',
    workshop          VARCHAR(150) NULL,
    technician_id     INT          NULL COMMENT 'FK→users.id (الفني)',
    supervisor_id     INT          NULL COMMENT 'FK→users.id (المشرف)',
    failure_code_id   INT          NULL COMMENT 'FK→failure_codes.id',
    diagnosis         TEXT         NULL,
    root_cause_id     INT          NULL COMMENT 'FK→mnt_lookup.id (سبب جذري)',
    actions_taken     TEXT         NULL,
    work_start        DATETIME     NULL,
    work_end          DATETIME     NULL,
    downtime_hours    DECIMAL(10,2) NOT NULL DEFAULT 0,
    labor_cost        DECIMAL(12,2) NOT NULL DEFAULT 0,
    parts_cost        DECIMAL(12,2) NOT NULL DEFAULT 0,
    external_cost     DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_cost        DECIMAL(12,2) NOT NULL DEFAULT 0,
    inspection_result VARCHAR(20)  NULL COMMENT 'ناجح/راسب',
    state             VARCHAR(20)  NOT NULL DEFAULT 'بلاغ' COMMENT 'بلاغ/تنفيذ/فحص/إغلاق/ملغى',
    closed_at         DATETIME     NULL,
    closed_by         INT          NULL,
    is_deleted        TINYINT(1)   NOT NULL DEFAULT 0,
    deleted_at        DATETIME     NULL,
    deleted_by        INT          NULL,
    created_by        INT          NULL,
    created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_order_eq_company_state (equipment_id, company_id, state),
    KEY idx_order_company_state (company_id, state),
    KEY idx_order_breakdown (breakdown_id),
    KEY idx_order_plan (plan_id),
    KEY idx_order_inspection (inspection_id),
    KEY idx_order_deleted (is_deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- (4) mnt_order_labor — أسطر العمالة (إدخال يدوي)
CREATE TABLE IF NOT EXISTS mnt_order_labor (
    id          INT NOT NULL AUTO_INCREMENT,
    company_id  INT NOT NULL,
    order_id    INT NOT NULL COMMENT 'FK→mnt_order.id',
    employee_id INT          NULL COMMENT 'FK→users.id (اختياري)',
    role        VARCHAR(100) NULL,
    hours       DECIMAL(8,2)  NOT NULL DEFAULT 0,
    hourly_rate DECIMAL(10,2) NOT NULL DEFAULT 0,
    cost        DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_labor_order (order_id),
    CONSTRAINT fk_labor_order FOREIGN KEY (order_id) REFERENCES mnt_order (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- (5) mnt_order_part — أسطر القطع (إدخال يدوي بلا مخزون)
CREATE TABLE IF NOT EXISTS mnt_order_part (
    id                 INT NOT NULL AUTO_INCREMENT,
    company_id         INT NOT NULL,
    order_id           INT NOT NULL COMMENT 'FK→mnt_order.id',
    part_name          VARCHAR(200) NOT NULL,
    category           VARCHAR(100) NULL,
    quantity           DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_cost          DECIMAL(12,2) NOT NULL DEFAULT 0,
    subtotal           DECIMAL(12,2) NOT NULL DEFAULT 0,
    is_major_component TINYINT(1)   NOT NULL DEFAULT 0,
    created_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_part_order (order_id),
    CONSTRAINT fk_part_order FOREIGN KEY (order_id) REFERENCES mnt_order (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- (6) mnt_inspection — التفتيش/الزيارة الميدانية (نوع مدموج)
CREATE TABLE IF NOT EXISTS mnt_inspection (
    id                  INT NOT NULL AUTO_INCREMENT,
    company_id          INT NOT NULL COMMENT 'عزل الشركة (إجباري)',
    code                VARCHAR(50)  NULL COMMENT 'مرجع التفتيش، مثل INS-2026-0001',
    inspection_type     VARCHAR(50)  NOT NULL DEFAULT 'دوري' COMMENT 'دوري/زيارة ميدانية/استلام/بعد حادث',
    equipment_id        INT          NULL COMMENT 'FK→equipments.id',
    project_id          INT          NULL COMMENT 'FK→project.id',
    inspector_id        INT          NULL COMMENT 'FK→users.id (الفاحص)',
    scheduled_date      DATE         NULL,
    completed_at        DATETIME     NULL,
    score               INT          NULL,
    overall_result      VARCHAR(50)  NULL,
    tech_readiness_state VARCHAR(50) NULL COMMENT 'الجاهزية الفنية',
    equipment_condition VARCHAR(50)  NULL COMMENT 'تُكتب لكرت المعدة عند الإكمال + تُخزّن',
    engine_condition    VARCHAR(50)  NULL COMMENT 'تُكتب لكرت المعدة عند الإكمال + تُخزّن',
    notes               TEXT         NULL,
    state               VARCHAR(30)  NOT NULL DEFAULT 'جديد' COMMENT 'جديد/مجدول/قيد التنفيذ/مكتمل/مغلق',
    is_deleted          TINYINT(1)   NOT NULL DEFAULT 0,
    deleted_at          DATETIME     NULL,
    deleted_by          INT          NULL,
    created_by          INT          NULL,
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_inspection_equipment (equipment_id),
    KEY idx_inspection_company_state (company_id, state),
    KEY idx_inspection_deleted (is_deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- (7) mnt_inspection_line — بنود استمارة التفتيش (condition محجوزة ⇒ condition_state)
CREATE TABLE IF NOT EXISTS mnt_inspection_line (
    id              INT NOT NULL AUTO_INCREMENT,
    company_id      INT NOT NULL,
    inspection_id   INT NOT NULL COMMENT 'FK→mnt_inspection.id',
    component       VARCHAR(150) NOT NULL,
    condition_state VARCHAR(30)  NULL COMMENT 'سليم/ملاحظة/حرج',
    recommendation  TEXT         NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_inspline_inspection (inspection_id),
    CONSTRAINT fk_inspline_inspection FOREIGN KEY (inspection_id) REFERENCES mnt_inspection (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- (8) mnt_plan — الخطة الوقائية (interval محجوزة ⇒ interval_value)
CREATE TABLE IF NOT EXISTS mnt_plan (
    id              INT NOT NULL AUTO_INCREMENT,
    company_id      INT NOT NULL COMMENT 'عزل الشركة (إجباري)',
    code            VARCHAR(50)  NULL COMMENT 'مرجع الخطة، مثل PLN-2026-0001',
    name            VARCHAR(200) NOT NULL,
    scope           VARCHAR(50)  NULL COMMENT 'معدة/فئة',
    equipment_id    INT          NULL COMMENT 'FK→equipments.id',
    category_id     INT          NULL COMMENT 'FK→equipments_types.id',
    trigger_basis   VARCHAR(20)  NOT NULL DEFAULT 'ساعات' COMMENT 'ساعات/زمن',
    interval_value  INT          NULL COMMENT 'الفاصل (ساعات أو أيام)',
    tolerance       INT          NULL,
    last_done_date  DATE         NULL,
    last_done_meter DECIMAL(12,2) NULL,
    next_due_date   DATE         NULL,
    next_due_meter  DECIMAL(12,2) NULL,
    state           VARCHAR(30)  NOT NULL DEFAULT 'نشطة' COMMENT 'نشطة/متوقفة',
    is_deleted      TINYINT(1)   NOT NULL DEFAULT 0,
    deleted_at      DATETIME     NULL,
    deleted_by      INT          NULL,
    created_by      INT          NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_plan_eq_due (equipment_id, next_due_date),
    KEY idx_plan_company_state (company_id, state),
    KEY idx_plan_deleted (is_deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- (9) mnt_plan_task — مهام الخطة الوقائية
CREATE TABLE IF NOT EXISTS mnt_plan_task (
    id         INT NOT NULL AUTO_INCREMENT,
    company_id INT NOT NULL,
    plan_id    INT NOT NULL COMMENT 'FK→mnt_plan.id',
    name       VARCHAR(200) NOT NULL,
    task_type  INT          NULL COMMENT 'FK→mnt_lookup.id (نوع مهمة)',
    component  VARCHAR(150) NULL,
    est_hours  DECIMAL(8,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_plantask_plan (plan_id),
    CONSTRAINT fk_plantask_plan FOREIGN KEY (plan_id) REFERENCES mnt_plan (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────────────────
-- (4.5) محور الصحة الجديد على operations (مستقل عن status التشغيلي).
-- محروس بفحص وجود العمود ⇒ idempotent (MySQL 8 لا يدعم ADD COLUMN IF NOT EXISTS).
-- ───────────────────────────────────────────────────────────────────────────
SET @has_health := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'operations' AND COLUMN_NAME = 'equipment_health'
);
SET @sql := IF(@has_health = 0,
    'ALTER TABLE operations
       ADD COLUMN equipment_health ENUM(''سليمة'',''معطلة'') NOT NULL DEFAULT ''سليمة''
           COMMENT ''الصحة الفنية للمعدة (مستقلة عن status التشغيلي)'' AFTER status,
       ADD COLUMN health_reason VARCHAR(150) NULL COMMENT ''سبب العطل، مثل: صيانة'' AFTER equipment_health,
       ADD COLUMN health_updated_at DATETIME NULL AFTER health_reason,
       ADD COLUMN health_updated_by INT NULL AFTER health_updated_at',
    'DO 0');
PREPARE _s FROM @sql; EXECUTE _s; DEALLOCATE PREPARE _s;

-- ───────────────────────────────────────────────────────────────────────────
-- (4.6) فهرس operations(equipment_health) — محروس بفحص وجود الفهرس.
-- ───────────────────────────────────────────────────────────────────────────
SET @has_idx := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'operations'
      AND INDEX_NAME = 'idx_operations_equipment_health'
);
SET @sql := IF(@has_idx = 0,
    'ALTER TABLE operations ADD INDEX idx_operations_equipment_health (equipment_health)',
    'DO 0');
PREPARE _s FROM @sql; EXECUTE _s; DEALLOCATE PREPARE _s;

-- ───────────────────────────────────────────────────────────────────────────
-- (5.8) تسجيل تقرير الصيانة في report_role_permissions لدوري الصيانة (المدير والمشرف).
-- UNIQUE(role_id, report_code) + INSERT IGNORE ⇒ idempotent. (السوبر أدمن ودور 1 يريانه
-- تلقائياً عبر منطق emsreports). تعريف التقرير في getReportsCatalog() + قالب التقارير.
-- ───────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO report_role_permissions (role_id, report_code)
SELECT r.id, 'maintenance_summary'
FROM roles r
WHERE r.name IN ('ادارة الصيانة', 'مشرف صيانة');

-- ═══════════════════════════════════════════════════════════════════════════
-- نهاية هجرة قسم الصيانة. التحقق بعد التطبيق (راجع سكربت التحقق المرافق).
-- ═══════════════════════════════════════════════════════════════════════════
