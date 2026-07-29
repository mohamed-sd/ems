-- ═══════════════════════════════════════════════════════════════════════════
-- دورةُ رفع الموازنات من الإدارات وإجازتها من المالية — 2026-07-27
-- ───────────────────────────────────────────────────────────────────────────
-- UX-02 §5 دورة ⑥ نصًّا: «**إعدادُ الميزانية ← اعتمادُها** ← الفعلي/المخطط …» —
-- خطوتان متمايزتان. وSPEC-01 §20/§21 يجعلان «الإدارة» عمودًا في الموازنة وفي
-- الانحراف. وUX-01 §8 يضع «الميزانية والانحراف» في باب **المتابعة والموافقات**
-- لستِّ إداراتٍ تشغيلية — بابِ ما ينتظر قرارًا لا بابِ التقارير.
--
-- **قرارات المالك (2026-07-27):**
--   ① يرفعها **مديرُ الإدارة وحده** — لا منشئو طلباتها.
--   ② يُجيزها **مديرُ الإدارة المالية (19) وحده** — وتُسحب الإجازةُ من 17/18/20/21.
--   ③ كلُّ إدارةٍ ترى **موازنتَها وحدها** (قاعدة «التبعيةُ تحدد والصلاحيةُ ترشّح»).
--
-- **خريطةُ الدور ← الإدارة**: `fin_request_routing.manager_role_id` — موجودةٌ سلفًا
-- وأقسامُها هي نفسُها الأحدَ عشرَ في `fin_budgets.dept_module` بالحرف. مصدرٌ واحدٌ
-- يخدم بوابتَي الطلب والموازنة — لا جدولَ ربطٍ جديد.
--
-- الرجوع: إعادةُ الـENUM بلا `returned`، وإسقاطُ الأعمدة الخمسة، وإعادةُ منح
-- التعديل للأدوار 17/18/20/21 وسحبِه من مديري الإدارات.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① حالةُ «معادة»: الثلاثيةُ الموحّدة تقتضيها (الدستور §4.3) ─────────────
--    كانت الحالاتُ خمسًا بلا إعادة — فالماليةُ تعتمد أو تصمت، ولا تُعيد بسبب.
ALTER TABLE `fin_budgets`
  MODIFY COLUMN `state` ENUM('draft','submitted','returned','approved','active','closed')
  NOT NULL DEFAULT 'draft'
  COMMENT 'مسودة → مقدَّمة → (معادة بسبب | معتمدة) → نشطة → مقفلة';

-- ── ② أعمدةُ الرفع والإعادة: مَن رفع ومتى، ولماذا أُعيدت ───────────────────
ALTER TABLE `fin_budgets`
  ADD COLUMN `submitted_by`  INT NULL COMMENT 'مديرُ الإدارة الذي رفعها' AFTER `state`,
  ADD COLUMN `submitted_at`  DATETIME NULL COMMENT 'لحظةُ الرفع' AFTER `submitted_by`,
  ADD COLUMN `approved_at`   DATETIME NULL COMMENT 'لحظةُ الإجازة' AFTER `approved_by`,
  ADD COLUMN `returned_by`   INT NULL COMMENT 'من أعادها' AFTER `approved_at`,
  ADD COLUMN `returned_at`   DATETIME NULL AFTER `returned_by`,
  ADD COLUMN `return_reason` VARCHAR(255) NULL
      COMMENT 'سببُ الإعادة — بارزٌ للإدارة (الدستور §4.3: «أُعيد إليك لاستكمال: السبب»)'
      AFTER `returned_at`;

ALTER TABLE `fin_budgets`
  ADD INDEX `ix_fin_budget_dept_state` (`company_id`, `dept_module`, `state`);

-- ── ③ الصلاحيات: مديرو الإدارات يُعدّون ويرفعون ───────────────────────────
--    مديرو الأقسام من جدول التوجيه: 1 التشغيل · 2 الموردون · 3 الأسطول
--    · 4 الموارد · 12 المبيعات · 13 الصيانة · 16 المشتريات · 17 المالية
--    (لأقسام الإيرادات والخزينة والعام). والنطاقُ الصفّي تحرسه الشاشة.
INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, m.id, 1, 1, 1, 0
  FROM (SELECT 1 AS rid UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
        UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 16) r
  CROSS JOIN (SELECT id FROM `modules` WHERE `code` = 'Finance/budget_form_fin.php' LIMIT 1) m
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = m.id);

UPDATE `role_permissions` rp
  JOIN `modules` m ON m.`id` = rp.`module_id` AND m.`code` = 'Finance/budget_form_fin.php'
   SET rp.`can_add` = 1, rp.`can_edit` = 1
 WHERE rp.`role_id` IN (1, 2, 3, 4, 12, 13, 16, 17);

-- ── ④ الإجازةُ للمدير المالي وحده — تُسحب ممن سواه ────────────────────────
--    (المحاسبُ 18 وأمينُ الخزينة 21 والمدقّق 20 يبقون عرضًا: الموازنةُ مرجعُهم
--     لا قرارُهم — والدستور يمنع أن يعتمد المرءُ ما أعدّه.)
UPDATE `role_permissions` rp
  JOIN `modules` m ON m.`id` = rp.`module_id` AND m.`code` = 'Finance/budget_form_fin.php'
   SET rp.`can_add` = 0, rp.`can_edit` = 0
 WHERE rp.`role_id` IN (18, 20, 21);

UPDATE `role_permissions` rp
  JOIN `modules` m ON m.`id` = rp.`module_id` AND m.`code` = 'Finance/budget_form_fin.php'
   SET rp.`can_view` = 1, rp.`can_add` = 1, rp.`can_edit` = 1
 WHERE rp.`role_id` = 19;
