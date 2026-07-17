-- فجوة مكشوفة بدخان §6.2: عائلة المالية (18-22) بلا can_view على نموذج الطلب —
-- فزر «التفاصيل والسجل» في مكتب المحاسب كان يعاد للوحة. عرضٌ فقط (الإنشاء
-- يبقى بعضوية التوجيه + can_add). إدراجٌ عاطل.

INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
SELECT r.role_id, m.id, 1, 0, 0, 0
FROM modules m
JOIN (
    SELECT 18 AS role_id UNION ALL SELECT 19 UNION ALL SELECT 20 UNION ALL
    SELECT 21 UNION ALL SELECT 22
) r
WHERE m.code = 'FinRequests/request_form.php'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.role_id AND rp.module_id = m.id
  );
