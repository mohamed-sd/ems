-- ═══════════════════════════════════════════════════════════════════════════
-- إكمال بذر الدور 1: «اعتماد الوحدات التشغيلية» — 2026-07-26
-- ───────────────────────────────────────────────────────────────────────────
-- الرابطُ الثابت الوحيد الحيّ في insidebar للأدوار -1..5 (بعدّاده الحي) ولم
-- يكن في البذر الأول — بدونه يفقد الدورُ الرائد وظيفةً مرئيةً اليوم (خرقُ
-- «لا فقدانَ وظيفة» UX-01 §2-⑩). لا صفَّ له في سجل الشاشات → permission_code
-- NULL = يظهر بلا فحصٍ (سلوكُ اليوم حرفيًّا)، وعدّادُه من سجل العدّادات
-- بمفتاح hours_approval (القيمةُ تُحسب في insidebar وتُمرَّر للمصيِّر).
-- ═══════════════════════════════════════════════════════════════════════════

INSERT INTO `nav_items`
  (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
VALUES
  (1, 'APPR', NULL, NULL, 'اعتماد الوحدات التشغيلية', 'Approvals/hours_approval.php', 'fa fa-check-double', 5, 'hours_approval', NULL, 1),
  -- الرابط الذكي الثاني الحي: الدور 1 له 23 تقريرًا في النظام الجديد فيرى
  -- «التقارير» → emsreports (بجانب «مركز التقارير» القديم المملوك — كما اليوم 1:1)
  (1, 'REP', NULL, NULL, 'التقارير', 'emsreports/index.php', 'fas fa-chart-pie', 5, NULL, NULL, 1)
ON DUPLICATE KEY UPDATE
  `door` = VALUES(`door`), `label_ar` = VALUES(`label_ar`), `icon` = VALUES(`icon`),
  `sort_order` = VALUES(`sort_order`), `counter_source` = VALUES(`counter_source`), `active` = 1;
