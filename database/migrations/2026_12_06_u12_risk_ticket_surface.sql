-- ═══════════════════════════════════════════════════════════════════════════
-- 2026_12_06_u12_risk_ticket_surface.sql
-- سطحُ البلاغاتِ لأدوارِ المخاطرِ الثلاثة (28 · 29 · 30)
-- ───────────────────────────────────────────────────────────────────────────
-- الفحصُ الحاكمُ ⑩ في tools/act_checks.php: «كلُّ دورٍ له بلاغاتُ إدارتي» —
-- وأدوارُ المخاطرِ الثلاثةُ التي وُلدت في update0011 (M-16) خرجت بلا سطحِ
-- بلاغات: لا مدخلَ في قوائمها ولا صلاحيةَ عرضٍ على الصندوق. فالإدارةُ ترى
-- مخاطرَها ولا ترى ما يُبلَّغ عنها — وهذا يكسر «كلُّ شاشةٍ موضعُ فتحِ بلاغ»
-- (TKT-15) من الطرفِ المستقبِل.
--
-- ما يفعله: منحُ عرضٍ على Tickets/dept_inbox.php للثلاثة (قراءةٌ فقط: الأدوارُ
-- الثلاثةُ لا تكتب في صندوقٍ ليس صندوقَها — الكتابةُ لمالكِ البلاغ)، ومدخلٌ في
-- بابِ RISK لكلٍّ منها بمجموعتِه القائمة.
--
-- عكسُه: حذفُ الصفوفِ الثلاثةِ من nav_items وrole_permissions بالرمزِ نفسِه.
-- ═══════════════════════════════════════════════════════════════════════════

-- ① صلاحيةُ العرضِ على صندوقِ الإدارة (قراءةٌ فقط)
INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
SELECT r.id, mo.id, 1, 0, 0, 0
  FROM roles r
  CROSS JOIN modules mo
 WHERE r.id IN (28, 29, 30)
   AND mo.code = 'Tickets/dept_inbox.php'
   AND NOT EXISTS (
        SELECT 1 FROM role_permissions rp
         WHERE rp.role_id = r.id AND rp.module_id = mo.id);

-- ② مدخلُ «بلاغاتُ إدارتي» في بابِ المخاطر لكلِّ دورٍ بمجموعتِه القائمة
INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, permission_code, active)
SELECT x.role_id, 'RISK', x.group_id, mo.id, 'بلاغاتُ إدارتي',
       'Tickets/dept_inbox.php', 'fa fa-bell', 90, 'Tickets/dept_inbox.php', 1
  FROM (
        SELECT n.role_id, MIN(n.group_id) AS group_id
          FROM nav_items n
         WHERE n.active = 1 AND n.door = 'RISK' AND n.role_id IN (28, 29, 30)
         GROUP BY n.role_id
       ) x
  CROSS JOIN modules mo
 WHERE mo.code = 'Tickets/dept_inbox.php'
   AND NOT EXISTS (
        SELECT 1 FROM nav_items n2
         WHERE n2.role_id = x.role_id AND n2.active = 1
           AND n2.route = 'Tickets/dept_inbox.php');
