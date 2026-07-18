-- ═══════════════════════════════════════════════════════════════════════════
-- شاشة إدارة الانحراف (D04 §3.10 + §7.3 + §4 variance_monitor) — 2026-07-18
-- ───────────────────────────────────────────────────────────────────────────
-- الفجوة المغلَقة: أعمدة الانحراف المُدار موجودةٌ سلفًا على fin_budget_lines
-- (cause · corrective_action · responsible_id · var_state)، والانحراف محسوبٌ
-- (variance/variance_pct مولَّدان)، والتنبيه قائمٌ في cron_finance_fin. الناقص
-- كان **واجهة المعالجة**: توثيق السبب والإجراء التصحيحي والمالك، ونقل الحالة
-- open → in_progress → closed. هذا الترحيل يسجّل الشاشة ويمنح صلاحياتها فقط —
-- **لا تغيير مخطّط** (الأعمدة قائمة). صلاحيات الإدخال وفق §15 RBAC حرفيًّا:
-- «إدخال سبب الانحراف وإجراؤه» = محاسب(18)/مدير معني(19)/مراجع(20)/مدير مالي(17).
-- التراجع: DELETE للموديول وصفوف role_permissions المدرجة هنا.
-- idempotent: NOT EXISTS يحرس كل إدراج.
-- ═══════════════════════════════════════════════════════════════════════════

-- ① تسجيل الشاشة (owner_role_id=17 ⇒ تظهر في سايدبار المالية 17-22 عبر dynamic_nav)
INSERT INTO modules (name, code, owner_role_id, is_link, is_quick, icon, display_order)
SELECT 'مراقبة الانحراف والمعالجة', 'Finance/variance_monitor_fin.php', 17, 1, 0, 'fa fa-triangle-exclamation', 42
WHERE NOT EXISTS (
    SELECT 1 FROM modules WHERE code = 'Finance/variance_monitor_fin.php'
);

-- ② الصلاحيات لعائلة المالية (§15): 17-20 عرض+تعديل · 21-22 عرض فقط
INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
SELECT g.role_id, m.id, g.cv, 0, g.ce, 0
FROM modules m
JOIN (
    SELECT 17 AS role_id, 1 AS cv, 1 AS ce UNION ALL   -- المدير المالي
    SELECT 18, 1, 1 UNION ALL                          -- محاسب الإدارة المالية
    SELECT 19, 1, 1 UNION ALL                          -- مدير الإدارة المالية
    SELECT 20, 1, 1 UNION ALL                          -- المراجع والمدقق
    SELECT 21, 1, 0 UNION ALL                          -- أمين الخزينة (عرض)
    SELECT 22, 1, 0                                     -- قارئ مالي (عرض)
) g ON 1 = 1
WHERE m.code = 'Finance/variance_monitor_fin.php'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = g.role_id AND rp.module_id = m.id
  );
