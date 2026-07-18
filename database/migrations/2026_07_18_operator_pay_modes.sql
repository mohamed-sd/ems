-- ═══════════════════════════════════════════════════════════════════════════
-- وضع دفع المشغّل + تفعيل أثر employee_due (D04 §12 · §6.1) — 2026-07-18
-- ───────────────────────────────────────────────────────────────────────────
-- قرار المستخدم: لكل مشغّلٍ وضعٌ يقرّره المدير المالي — «بالراتب» (تدفعه الرواتب،
-- فالمستحق من المروحة = 0) أو «بالمستحق» (فالمروحة تدفعه بالساعة). هذا يمنع
-- ازدواجَ الأجر جذريًّا. الأثر employee_due (كان معطّلًا) يولّد الآن على مسار
-- الدوام: operator_hours × المعدّل، تصنيفه overtime، **فقط لمن وضعه «بالمستحق»**.
-- الافتراض «بالراتب» (غياب صفٍّ = بالراتب) ⇒ لا تلفيق حتى يُفعَّل مشغّلٌ صراحةً.
-- الصلاحية: الضبط سياسةٌ ⇒ تعديلٌ للمدير المالي (17) · 18-22 عرضًا.
-- التراجع: DROP TABLE fin_operator_pay + DELETE الموديول وصفوف role_permissions.
-- idempotent: IF NOT EXISTS / NOT EXISTS يحرس كل خطوة.
-- ═══════════════════════════════════════════════════════════════════════════

-- ① جدول وضع دفع المشغّل (T_TENANT · soft=false · مسجَّل في TenantRegistry)
CREATE TABLE IF NOT EXISTS fin_operator_pay (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id  INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,                 -- employees (ADM) soft ref
    pay_mode    ENUM('salary','due') NOT NULL DEFAULT 'salary',
    note        VARCHAR(160) NULL,
    created_by  INT UNSIGNED NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_op_pay (company_id, employee_id),
    KEY ix_mode (company_id, pay_mode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ② تسجيل الشاشة (owner_role_id=17 ⇒ سايدبار المالية عبر dynamic_nav)
INSERT INTO modules (name, code, owner_role_id, is_link, is_quick, icon, display_order)
SELECT 'مستحق المشغّل (المروحة)', 'Finance/operator_pay_fin.php', 17, 1, 0, 'fa fa-user-clock', 116
WHERE NOT EXISTS (
    SELECT 1 FROM modules WHERE code = 'Finance/operator_pay_fin.php'
);

-- ③ الصلاحيات: 17 عرض+تعديل (صاحب السياسة) · 18-22 عرض فقط
INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
SELECT g.role_id, m.id, g.cv, 0, g.ce, 0
FROM modules m
JOIN (
    SELECT 17 AS role_id, 1 AS cv, 1 AS ce UNION ALL
    SELECT 18, 1, 0 UNION ALL
    SELECT 19, 1, 0 UNION ALL
    SELECT 20, 1, 0 UNION ALL
    SELECT 21, 1, 0 UNION ALL
    SELECT 22, 1, 0
) g ON 1 = 1
WHERE m.code = 'Finance/operator_pay_fin.php'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = g.role_id AND rp.module_id = m.id
  );
