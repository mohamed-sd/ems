-- ═══════════════════════════════════════════════════════════════════════════
-- 2026_12_07_u12_entitlement_nav_repoint.sql
-- «توليدُ المستحق من العمل المعتمد» يشير إلى شاشتِه لا إلى بوابتِها
-- ───────────────────────────────────────────────────────────────────────────
-- كشفَ فاحصُ المطابقةِ (tools/nav09_verify.php) ثمانيَ مخالفاتِ موضعٍ: عنصرُ
-- التنقّلِ «توليد المستحق من العمل المعتمد» يشير إلى `Finance/entitlement_gate.php`
-- بينما الوثيقةُ تجعل وجهتَه `Finance/entitlement.php` — الشاشةَ التي وُلدت في
-- هذه الحقبة (M-10 §7-2 · fin.entitle).
--
-- والشاشتان مختلفتان لا مكرَّرتان:
--   · entitlement_gate.php  — «فحصُ شروطِ الاستحقاق» (update0007 · S-07): تعرض
--     الأثرَ الأوليَّ المنتظرَ وتُمرّره بوابةً. تبقى كما هي بعنصرِها الخاص.
--   · entitlement.php       — «توليدُ المستحق من العمل المعتمد» (M-10): البوابةُ
--     الرباعيةُ ثم التوليدُ بأحكامٍ ثلاثةٍ مستقلة.
-- فالخللُ أنَّ العنصرَ الثانيَ ظلَّ يشير للأولى بعد ولادةِ الثانية.
--
-- ما يفعله: يعيد توجيهَ عنصرِ التنقّلِ بعنوانِه (لا بوجهتِه) إلى الشاشةِ
-- الصحيحةِ مع وحدةِ صلاحياتِها ورمزِها — للأدوارِ الستةِ الماليةِ (17..22)
-- والقوى التشغيلية (27). ومرساةُ التكرارِ #n9g3i1 تُحفظ كما هي.
--
-- عكسُه: إعادةُ route/module_id/permission_code إلى entitlement_gate.php.
-- ═══════════════════════════════════════════════════════════════════════════

-- ① منحُ العرضِ لدور القوى التشغيلية (27) على الشاشةِ الجديدة إن غاب
INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
SELECT 27, mo.id, 1, 0, 0, 0
  FROM modules mo
 WHERE mo.code = 'Finance/entitlement.php'
   AND NOT EXISTS (
        SELECT 1 FROM role_permissions rp
         WHERE rp.role_id = 27 AND rp.module_id = mo.id);

-- ② إعادةُ توجيهِ العنصرِ بعنوانِه — مع حفظِ مرساةِ التكرارِ إن وُجدت
UPDATE nav_items ni
  JOIN modules mo ON mo.code = 'Finance/entitlement.php'
   SET ni.route = CONCAT('Finance/entitlement.php',
                         CASE WHEN LOCATE('#', ni.route) > 0
                              THEN SUBSTRING(ni.route, LOCATE('#', ni.route))
                              ELSE '' END),
       ni.module_id = mo.id,
       ni.permission_code = 'Finance/entitlement.php'
 WHERE ni.active = 1
   AND ni.label_ar = 'توليد المستحق من العمل المعتمد'
   AND ni.route LIKE 'Finance/entitlement_gate.php%';
