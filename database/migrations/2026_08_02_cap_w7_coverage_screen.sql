-- ═══════════════════════════════════════════════════════════════════════════
-- update0005 · الموجة ⑦ · CAP-35 — شاشةُ «التغطية التعاقدية» وتنظيفُ القائمة
-- CAP-01 §1/§11:
--   · تسجيلُ Contracts/contract_coverage.php وحدةً — تُفتح من ملف العقد.
--   · العرضُ يرثه كلُّ دورٍ يرى ملفَّ العقد (contracts_details) — قراءةٌ حصرًا.
--   · **ولا عنصرَ في القائمة الجانبية باسم «الحاويات»** — عنصرُ
--     «حاويات العقود» يُطفأ (المفهومُ فنيٌّ داخليٌّ والرحلةُ داخل ملف العقد).
--     والشاشةُ الفنيةُ Operations/containers.php تبقى حيةً لمن يقصدها بربطها.
-- idempotent بالكامل.
-- ═══════════════════════════════════════════════════════════════════════════

INSERT INTO modules (name, code, icon, display_order)
SELECT 'التغطية التعاقدية', 'Contracts/contract_coverage.php', 'fas fa-shield-halved', 330
WHERE NOT EXISTS (SELECT 1 FROM modules m WHERE m.code = 'Contracts/contract_coverage.php');

-- العرضُ بوراثة رؤية ملف العقد — «الملكيةُ والاطّلاع» (NAV-01 §5)
INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
SELECT rp.role_id, m2.id, 1, 0, 0, 0
FROM role_permissions rp
JOIN modules m1 ON m1.id = rp.module_id AND m1.code = 'Contracts/contracts_details.php'
JOIN modules m2 ON m2.code = 'Contracts/contract_coverage.php'
WHERE rp.can_view = 1
  AND NOT EXISTS (SELECT 1 FROM role_permissions x
                   WHERE x.role_id = rp.role_id AND x.module_id = m2.id);

-- §1: لا عنصرَ باسم «الحاويات» في القائمة الجانبية — يُطفأ لا يُحذف (التراجعُ قلبُ راية)
UPDATE nav_items SET active = 0
 WHERE label_ar LIKE '%حاويات%' AND active = 1;
