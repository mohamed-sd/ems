-- D05 — منح صلاحيات بوابة الطلبات لكل الأدوار التشغيلية بعد التفعيل الشامل
-- ═══════════════════════════════════════════════════════════════════════════
-- بلا هذا الترحيل تفعيلُ الإدارة بلا معنى: الدور يملك طريقًا في جدول التوجيه
-- لكنه يُصدّ عن الشاشة بفحص الصلاحية. المنح هنا مطابقٌ لدور كلٍّ في الدورة:
--   • أدوار الإنشاء            → النموذج (عرض/إضافة/تعديل) + طلباتي (عرض)
--   • أدوار المراجعة والاعتماد → صندوق الإدارة (عرض/تعديل) + طلباتي
-- (إدراج عاطل — يتخطى الموجود، وقابلٌ لإعادة التشغيل.)
-- ═══════════════════════════════════════════════════════════════════════════

-- ① النموذج الموحّد + طلباتي: لكل دورٍ يجوز له الإنشاء
INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
SELECT r.role_id, m.id, 1, IF(m.code = 'FinRequests/request_form.php', 1, 0),
       IF(m.code = 'FinRequests/request_form.php', 1, 0), 0
FROM modules m
CROSS JOIN (
    SELECT 1 AS role_id UNION ALL SELECT 2  UNION ALL SELECT 3  UNION ALL SELECT 4  UNION ALL
    SELECT 5           UNION ALL SELECT 6  UNION ALL SELECT 7  UNION ALL SELECT 8  UNION ALL
    SELECT 10          UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL
    SELECT 14          UNION ALL SELECT 15 UNION ALL SELECT 16 UNION ALL SELECT 21 UNION ALL
    SELECT 23
) r
WHERE m.code IN ('FinRequests/request_form.php', 'FinRequests/my_requests.php')
  AND EXISTS (SELECT 1 FROM roles ro WHERE ro.id = r.role_id)
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.role_id AND rp.module_id = m.id
  );

-- ② صندوق الإدارة: لكل دورٍ مراجعٍ أو معتمِد في أي صفّ توجيه
INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
SELECT r.role_id, m.id, 1, 0, 1, 0
FROM modules m
CROSS JOIN (
    SELECT 1 AS role_id UNION ALL SELECT 2  UNION ALL SELECT 3  UNION ALL SELECT 4  UNION ALL
    SELECT 7           UNION ALL SELECT 8  UNION ALL SELECT 10 UNION ALL SELECT 12 UNION ALL
    SELECT 13          UNION ALL SELECT 14 UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL
    SELECT 21
) r
WHERE m.code = 'FinRequests/dept_inbox.php'
  AND EXISTS (SELECT 1 FROM roles ro WHERE ro.id = r.role_id)
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.role_id AND rp.module_id = m.id
  );
