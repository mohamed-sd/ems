-- ═══════════════════════════════════════════════════════════════════════════
-- update0004 · الموجة ④ · ORG-15→ORG-18 — تسجيل شاشات ORG الأربع
-- modules + role_permissions (الدور 1 مدير التشغيل كاملًا · الدور 6 مدير
-- الحركة: الأذونات والتكليفات عرضًا) + nav_items للدورين.
-- idempotent: بذر بمفاتيح طبيعية (code · role+module · role+route).
-- ═══════════════════════════════════════════════════════════════════════════

-- ① الموديولات الأربعة
INSERT INTO modules (name, code, icon, display_order)
SELECT t.name, t.code, t.icon, t.ord
FROM (SELECT 'لوحة مدير التشغيل' name, 'admin/ops_manager_board.php' code, 'fa fa-tachometer-alt' icon, 300 ord UNION ALL
      SELECT 'التكليفات التنظيمية', 'admin/org_assignments.php', 'fa fa-id-badge', 301 UNION ALL
      SELECT 'الهيكل التنظيمي',     'admin/org_structure.php',   'fa fa-sitemap',  302 UNION ALL
      SELECT 'أذونات المواقع',      'admin/org_permits.php',     'fa fa-key',      303) t
WHERE NOT EXISTS (SELECT 1 FROM modules m WHERE m.code = t.code);

-- ② صلاحيات الدور 1 (مدير التشغيل) — كاملة على الأربع
INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
SELECT 1, m.id, 1, 1, 1, 0
FROM modules m
WHERE m.code IN ('admin/ops_manager_board.php','admin/org_assignments.php',
                 'admin/org_structure.php','admin/org_permits.php')
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = 1 AND rp.module_id = m.id);

-- ③ صلاحيات الدور 6 (مدير الحركة): الأذونات إنشاءً وموافقةً · والتكليفات والهيكل عرضًا
INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
SELECT 6, m.id,
       1,
       IF(m.code = 'admin/org_permits.php', 1, 0),
       IF(m.code = 'admin/org_permits.php', 1, 0),
       0
FROM modules m
WHERE m.code IN ('admin/org_assignments.php','admin/org_structure.php','admin/org_permits.php')
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = 6 AND rp.module_id = m.id);

-- ④ روابط التنقل — الدور 1
INSERT INTO nav_items (role_id, door, module_id, label_ar, route, icon, sort_order)
SELECT 1, t.door, m.id, t.label, t.route, t.icon, t.ord
FROM (SELECT 'HOME' door, 'لوحة مدير التشغيل' label, 'admin/ops_manager_board.php' route, 'fa fa-tachometer-alt' icon, 5 ord UNION ALL
      SELECT 'REC',  'التكليفات التنظيمية', 'admin/org_assignments.php', 'fa fa-id-badge', 90 UNION ALL
      SELECT 'SET',  'الهيكل التنظيمي',     'admin/org_structure.php',   'fa fa-sitemap',  90 UNION ALL
      SELECT 'APPR', 'أذونات المواقع',      'admin/org_permits.php',     'fa fa-key',      90) t
JOIN modules m ON m.code = t.route
WHERE NOT EXISTS (SELECT 1 FROM nav_items n WHERE n.role_id = 1 AND n.route = t.route);

-- ⑤ روابط التنقل — الدور 6 (الأذونات في الاعتماد والتكليفات في السجلات)
INSERT INTO nav_items (role_id, door, module_id, label_ar, route, icon, sort_order)
SELECT 6, t.door, m.id, t.label, t.route, t.icon, t.ord
FROM (SELECT 'APPR' door, 'أذونات المواقع' label, 'admin/org_permits.php' route, 'fa fa-key' icon, 90 ord UNION ALL
      SELECT 'REC', 'التكليفات التنظيمية', 'admin/org_assignments.php', 'fa fa-id-badge', 95) t
JOIN modules m ON m.code = t.route
WHERE NOT EXISTS (SELECT 1 FROM nav_items n WHERE n.role_id = 6 AND n.route = t.route);
