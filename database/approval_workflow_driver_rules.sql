-- =====================================================
-- إضافة قواعد الموافقة لتشغيل وإيقاف المشغلين
-- تاريخ: 2026-03-03
-- =====================================================

-- قاعدة الموافقة لتشغيل مشغل جديد (role 10 -> role 3,-1)
INSERT IGNORE INTO approval_workflow_rules (entity_type, action, role_required, step_order, is_active, created_at)
VALUES ('driver', 'activate_driver', '3,-1', 1, 1, NOW());

-- قاعدة الموافقة لإيقاف مشغل (role 10 -> role 3,-1)
INSERT IGNORE INTO approval_workflow_rules (entity_type, action, role_required, step_order, is_active, created_at)
VALUES ('driver', 'deactivate_driver', '3,-1', 1, 1, NOW());

-- قاعدة الموافقة لإعادة تشغيل مشغل (role 10 -> role 3,-1)
INSERT IGNORE INTO approval_workflow_rules (entity_type, action, role_required, step_order, is_active, created_at)
VALUES ('driver', 'reactivate_driver', '3,-1', 1, 1, NOW());

-- قاعدة الموافقة لإيقاف آلية (role 10 -> role 4,-1)
INSERT IGNORE INTO approval_workflow_rules (entity_type, action, role_required, step_order, is_active, created_at)
VALUES ('equipment', 'deactivate_equipment', '4,-1', 1, 1, NOW());

-- قاعدة الموافقة لإعادة تشغيل آلية (role 10 -> role 4,-1)
INSERT IGNORE INTO approval_workflow_rules (entity_type, action, role_required, step_order, is_active, created_at)
VALUES ('equipment', 'reactivate_equipment', '4,-1', 1, 1, NOW());

-- =====================================================
-- عرض القواعد المضافة
-- =====================================================
SELECT * FROM approval_workflow_rules 
WHERE entity_type IN ('driver', 'equipment')
ORDER BY entity_type, action;
