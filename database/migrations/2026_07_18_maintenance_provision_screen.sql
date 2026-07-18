-- ═══════════════════════════════════════════════════════════════════════════
-- شاشة «مخصّص الصيانة (المروحة)» — تفعيل أثر metric_update (D04 §12.6 · §6.1) — 2026-07-18
-- ───────────────────────────────────────────────────────────────────────────
-- يغلق فجوة الأثر المعطّل «مخصّص الصيانة»: المحرّك EffectFanout يحسبه على ساعات
-- التشغيل الفعلية (قرار المستخدم)، لكن معدّله (param_value) قرارٌ ماليٌّ لم يُضبط.
-- هذه الشاشة **حقلٌ يملؤه المدير المالي يدويًّا** فيُكتب في fin_effect_map ويُفعّل
-- الأثر تلقائيًّا عند معدّلٍ موجب (فارغ = معطّل = لا تلفيق). **لا تغيير مخطّط**.
-- الصلاحية: الضبط سياسةٌ ماليةٌ ⇒ تعديلٌ للمدير المالي (17) وحده · 18-22 عرضًا.
-- التراجع: DELETE للموديول وصفوف role_permissions هنا (والأثر يعود معطّلًا بإفراغ المعدّل).
-- idempotent: NOT EXISTS يحرس كل إدراج.
-- ═══════════════════════════════════════════════════════════════════════════

-- ① تسجيل الشاشة (owner_role_id=17 ⇒ سايدبار المالية عبر dynamic_nav)
INSERT INTO modules (name, code, owner_role_id, is_link, is_quick, icon, display_order)
SELECT 'مخصّص الصيانة (المروحة)', 'Finance/maintenance_provision_fin.php', 17, 1, 0, 'fa fa-screwdriver-wrench', 115
WHERE NOT EXISTS (
    SELECT 1 FROM modules WHERE code = 'Finance/maintenance_provision_fin.php'
);

-- ② الصلاحيات: 17 عرض+تعديل (صاحب السياسة) · 18-22 عرض فقط
INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
SELECT g.role_id, m.id, g.cv, 0, g.ce, 0
FROM modules m
JOIN (
    SELECT 17 AS role_id, 1 AS cv, 1 AS ce UNION ALL   -- المدير المالي (يضبط المعدّل)
    SELECT 18, 1, 0 UNION ALL                          -- محاسب الإدارة المالية (عرض)
    SELECT 19, 1, 0 UNION ALL                          -- مدير الإدارة المالية (عرض)
    SELECT 20, 1, 0 UNION ALL                          -- المراجع والمدقق (عرض)
    SELECT 21, 1, 0 UNION ALL                          -- أمين الخزينة (عرض)
    SELECT 22, 1, 0                                     -- قارئ مالي (عرض)
) g ON 1 = 1
WHERE m.code = 'Finance/maintenance_provision_fin.php'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = g.role_id AND rp.module_id = m.id
  );
