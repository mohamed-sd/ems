-- ═══════════════════════════════════════════════════════════════════════════
-- M-05 · إحكامُ التحصيل — 2026-07-31
-- البطاقة: docs/specs/M-05_collection_control.md
-- المصدر: ENT-03 §4 («التحصيل · قبضٌ **بمرجعٍ بنكيٍّ أو سند** · Collected
--         جزئيًّا أو كليًّا — **والرصيدُ وعمرُه يتحدثان فورًا**» · «**التحصيل
--         الجزئي** — يُطبَّق على **أقدم فاتورةٍ أولًا** ما لم يحدد العميلُ مرجعًا
--         صريحًا — **والتخصيصُ ظاهرٌ في الكشف لا صامتًا**» · «Invoiced →
--         **PartiallyCollected** → Collected») · §7-Schema `collections`
--         («**bank_ref (إلزامي)** — UQ (bank_ref, amount, received_at)»)
-- ───────────────────────────────────────────────────────────────────────────
-- المقيسُ قبل البناء: `fin_payments` **بلا مرجعٍ بنكيٍّ ألبتة ولا فريدٍ يمنع
-- ازدواجَ القبض**، والتحصيلُ **لا يرتدّ إلى حالة المستخلص** (أربعُ حالاتٍ في
-- `claim_states()` وليس فيها «محصَّلٌ جزئيًّا»)، والتخصيصُ **يُكتب في عمودٍ
-- واحدٍ `receivable_id`** فلا يُرى توزيعُ دفعةٍ على فاتورتين.
-- والقائمُ: **صفُّ تحصيلٍ واحدٌ** بلا مرجع — يُوسم `legacy_no_ref` **ويُعلَن
-- ولا يُمحى** (نمطُ M-11 حرفيًّا).
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① المرجعُ البنكيُّ على الدفعة ──────────────────────────────────────────
ALTER TABLE `fin_payments`
  ADD COLUMN `bank_ref` VARCHAR(120) NULL DEFAULT NULL
      COMMENT 'المرجعُ البنكيُّ أو السند — إلزاميٌّ للتحصيل (ENT-03 §4)' AFTER `method`,
  ADD COLUMN `received_on` DATE NULL DEFAULT NULL
      COMMENT 'تاريخُ القبض — جزءُ مفتاح منع الازدواج' AFTER `bank_ref`;

-- الموروثُ يُوسم ويُعلَن ولا يُمحى (نمطُ M-11 `legacy_no_ref`)
UPDATE `fin_payments`
   SET `bank_ref` = 'legacy_no_ref',
       `received_on` = COALESCE(DATE(`paid_at`), DATE(`created_at`))
 WHERE `direction` = 'collection' AND `bank_ref` IS NULL;

-- **قبضٌ بلا مرجعٍ مستحيلٌ بنيويًّا** — والموروثُ مرَّ بوسمه المعلَن
ALTER TABLE `fin_payments`
  ADD CONSTRAINT `ck_collection_bank_ref` CHECK (
      `direction` <> 'collection' OR (`bank_ref` IS NOT NULL AND `bank_ref` <> ''));

-- **ولا يُقبض المرجعُ نفسُه بالمبلغ نفسِه في اليوم نفسِه مرتين** (§7)
CREATE UNIQUE INDEX `uq_collection_ref`
    ON `fin_payments` (`company_id`, `bank_ref`, `amount`, `received_on`);

-- ── ② تخصيصٌ ظاهرٌ لا صامت — «والتخصيصُ ظاهرٌ في الكشف» ───────────────────
CREATE TABLE IF NOT EXISTS `fin_collection_allocations` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `payment_id` INT NOT NULL,
  `receivable_id` INT NOT NULL,
  `amount` DECIMAL(18,2) NOT NULL,
  `basis` ENUM('explicit','oldest_first') NOT NULL DEFAULT 'oldest_first'
      COMMENT 'أساسُ التخصيص: مرجعٌ صريحٌ من العميل · أو **أقدمُ فاتورةٍ أولًا** (§4)',
  `created_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_alloc` (`payment_id`, `receivable_id`),
  KEY `ix_alloc_recv` (`company_id`, `receivable_id`),
  CONSTRAINT `fk_alloc_payment` FOREIGN KEY (`payment_id`)
      REFERENCES `fin_payments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_alloc_receivable` FOREIGN KEY (`receivable_id`)
      REFERENCES `fin_receivables` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_alloc_amount` CHECK (`amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ③ «Invoiced → PartiallyCollected → Collected» (§4) ─────────────────────
-- `claims.state` كان `varchar(16)` — و«partially_collected» تسعةَ عشرَ حرفًا.
ALTER TABLE `claims`
  MODIFY COLUMN `state` VARCHAR(24) NOT NULL DEFAULT 'draft'
      COMMENT 'حالاتُ §4 — ومنها **partially_collected** (M-05)';

-- ── ④ تسجيلُ شاشة «الذمم والتحصيل» — الوحدة 166 (ENT-03 §6) ────────────────
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 166, 'الذمم والتحصيل', 'Contracts/collections.php', 17, 0, 0, 'fa fa-hand-holding-dollar', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Contracts/collections.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 166, 1, r.a, r.a, 0
  FROM (SELECT 17 AS rid, 1 AS a UNION ALL SELECT 21, 1 UNION ALL SELECT 12, 0) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 166);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'REC', NULL, 166, 'الذمم والتحصيل', 'Contracts/collections.php',
       'fa fa-hand-holding-dollar', 65, NULL, 'Contracts/collections.php', 1
  FROM (SELECT 17 AS rid UNION ALL SELECT 21 UNION ALL SELECT 12) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Contracts/collections.php');
