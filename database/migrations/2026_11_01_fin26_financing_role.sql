-- ═══════════════════════════════════════════════════════════════════════════
-- FIN-26 — «إدارة التمويل» دورًا كاملًا — 2026-08-01 (استكمال DEC-01 ②)
-- المصدر: FIN-01 §1.1 (الصلاحية فردية لا عضوية · fail-closed) · §8 (شاشتا
--         الباب خلف المجال المقيَّد) · DEC-01 ② (بابان لمستويي سرية) ·
--         SCR-02 §3-⑤ (لا زر حذف في الشاشات المالية — المنح في الجدول لا زر).
-- النموذج: M-50 (2026_10_13) حرفيًّا — والدور 26 جديد بثابته في includes/roles.php.
-- idempotent كله بـ WHERE NOT EXISTS.
-- ═══════════════════════════════════════════════════════════════════════════

-- ① الدور 26 — إدارة التمويل (حارس ADR-07 حُدّث بثابته واسمه معًا · 9 المحذوف لا يُعاد)
INSERT INTO `roles` (`id`, `name`)
SELECT 26, 'إدارة التمويل'
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `roles`) r WHERE r.`id` = 26);

-- ② الشاشتان الجديدتان: 213 منح المجال المقيَّد (باب الحوكمة · خلف 1/19/السوبر)
--    و214 لوحة الدور 26 (HOME — نمط لوحة أمين المستودع 199)
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT * FROM (
    SELECT 213 i, 'منح المجال المقيَّد'  n, 'Governance/ownership_grants.php' c,  1 r, 0 l, 1 q, 'fa fa-user-lock'          ic, 0 d UNION ALL
    SELECT 214,   'لوحة إدارة التمويل',     'Financing/financing_board.php',     26,   0,   1,   'fa fa-money-bill-trend-up',    0
) m
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `modules`) x WHERE x.`code` = m.c);

-- ③ صلاحيات الدور 26: كاملة على شاشتي بابه (210·211) — والحذف منحٌ في الجدول
--    لا زرٌّ في الشاشة (SCR-02 §3-⑤: الشاشات المالية بلا زر حذف بنيويًّا،
--    والتصحيح عكسٌ بمرجعه) · وعرضًا على حوكمته (206–208) والمؤشر 212 ولوحته 214.
--    وشاشة المنح 213 للدورين 1 و19 (منحًا وإلغاءً — لا حذف صفوف).
INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT p.rid, p.mid, p.v, p.a, p.e, p.d
  FROM (
    SELECT 26 rid, 210 mid, 1 v, 1 a, 1 e, 1 d UNION ALL
    SELECT 26, 211, 1, 1, 1, 1 UNION ALL
    SELECT 26, 206, 1, 0, 0, 0 UNION ALL
    SELECT 26, 207, 1, 0, 0, 0 UNION ALL
    SELECT 26, 208, 1, 0, 0, 0 UNION ALL
    SELECT 26, 212, 1, 0, 0, 0 UNION ALL
    SELECT 26, 214, 1, 0, 0, 0 UNION ALL
    SELECT  1, 213, 1, 1, 1, 0 UNION ALL
    SELECT 19, 213, 1, 1, 1, 0
  ) p
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
                    WHERE rp.`role_id` = p.rid AND rp.`module_id` = p.mid);

-- ④ سايدبار الدور 26 — الثلاثية: HOME بلوحته + بابا عمله + مؤشره + حوكمته قراءةً
--    (باب FIN يظل خلف بوابة المجال المقيَّد في unified_nav — بلا منحة لا يُصيَّر)
INSERT INTO `nav_items` (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`,
                         `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT 26, p.door, NULL, m.id, p.lbl, m.`code`, m.`icon`, p.so, NULL, m.`code`, 1
  FROM (
    SELECT 'HOME' door, 'Financing/financing_board.php' code,        'الرئيسية' lbl,             1 so UNION ALL
    SELECT 'FIN',  'Financing/financiers_registry.php',              'سجل الممولين',             1 UNION ALL
    SELECT 'FIN',  'Financing/financing_operation_new.php',          'إنشاء عملية تمويل',        2 UNION ALL
    SELECT 'REP',  'Reports/approval_lag_report.php',                'الاعتمادات المتأخرة والوثائق', 90 UNION ALL
    SELECT 'GOV',  'Governance/entities_registry.php',               'سجل الكيانات',             1 UNION ALL
    SELECT 'GOV',  'Governance/signing_authority.php',               'التفويض بالتوقيع',         2 UNION ALL
    SELECT 'GOV',  'Governance/licenses_guarantees.php',             'التراخيص والكفالات',       3
  ) p
  JOIN (SELECT * FROM `modules`) m ON m.`code` = p.code
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `nav_items`) n
                    WHERE n.`role_id` = 26 AND n.`route` = p.code);

-- ④-ب شاشة المنح 213 في باب الحوكمة للدورين 1 و19 (إضافة لقائمتيهما — لا مساس بالقائم)
INSERT INTO `nav_items` (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`,
                         `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT p.rid, 'GOV', NULL, m.id, m.`name`, m.`code`, m.`icon`, 5, NULL, m.`code`, 1
  FROM (SELECT 1 rid UNION ALL SELECT 19) p
  JOIN (SELECT * FROM `modules`) m ON m.`code` = 'Governance/ownership_grants.php'
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `nav_items`) n
                    WHERE n.`role_id` = p.rid AND n.`route` = 'Governance/ownership_grants.php');

-- ⑤ الإسناد: حساب «تمويل» (نمط حسابات UAT · كلمة المرور الموحدة) مربوطًا
--    بالموظف القائم 27 «مروان» (مقبول · بلا حساب — قيد LEG-01: لا شخص بحسابين
--    نشطين محفوظ، وusers.employee_id UNIQUE يحرسه بنيويًّا)
INSERT INTO `users` (`name`, `username`, `password`, `role`, `company_id`, `employee_id`, `status`)
SELECT 'إدارة التمويل', 'تمويل', '$2y$10$R8hMdjxSlQ18I1rrMHakr.MUXGOONeal4CNTUtVNuVA7Tnxm2q3bq', '26', 4, 27, 'active'
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `users`) u WHERE u.`username` = 'تمويل')
   AND NOT EXISTS (SELECT 1 FROM (SELECT * FROM `users`) u2 WHERE u2.`employee_id` = 27);

-- ⑥ منحتا المجال المقيَّد للحساب: owner_view + finance_terms **بلا مدة** —
--    ولا purchase_value (الأشد لا تُمنح إلا بقرار لاحق بسبب ومدة — القاعدة 422
--    في OwnershipDomainGuard::grant وCHECK بنيوي يسندها). المانح: مدير الإدارة
--    المالية (الدور 19) بصفته صاحب شاشة المنح.
INSERT INTO `ownership_access_grants` (`company_id`, `person_id`, `permission_code`, `reason`, `granted_by`, `state`)
SELECT 4, u.`id`, p.code, 'FIN-26: عضو إدارة التمويل المخوَّل فرديًّا — DEC-01 ② · FIN-01 §1.1',
       COALESCE((SELECT MIN(g.`id`) FROM (SELECT * FROM `users`) g WHERE g.`role` = '19' AND g.`company_id` = 4), 1), 'active'
  FROM (SELECT * FROM `users`) u
  JOIN (SELECT 'ownership.owner_view' code UNION ALL SELECT 'ownership.finance_terms') p
 WHERE u.`username` = 'تمويل'
   AND NOT EXISTS (SELECT 1 FROM (SELECT * FROM `ownership_access_grants`) og
                    WHERE og.`person_id` = u.`id` AND og.`permission_code` = p.code AND og.`state` = 'active');
