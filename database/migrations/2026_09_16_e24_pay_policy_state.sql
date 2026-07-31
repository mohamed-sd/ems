-- ═══════════════════════════════════════════════════════════════════════════
-- E-24 · آلةُ حالات سياسة الأجر صراحةً — 2026-07-31
-- البطاقة: docs/specs/E-24_pay_policy_state.md
-- المصدر: UX-06 §8.2 (State السياسة): «آلةُ السياسة: **Draft → Active (بسريانٍ
--         UQ) → Superseded (بسياسةٍ أحدث) → Expired** — و**لا تعديلَ رجعيًّا**:
--         أثرُ الماضي بحكم سياسته النافذة يومَها» · §8.3-W4: «سياسةٌ جديدةٌ
--         بسريان أول الشهر ← DB: **Active جديدة وSuperseded للقديمة** · أثرُ ما
--         قبل السريان بالقديمة (لا رجعية)».
-- ───────────────────────────────────────────────────────────────────────────
-- المقيسُ قبل البناء: `contract_hour_policies` **بلا أي عمود حالة** — فصفٌّ
-- يُكتب من الشاشة يصير نافذًا في اللحظة نفسِها، و«الاستبدال» لا أثرَ له:
-- سياستان متداخلتان تُقرآن معًا والقديمةُ لا تُغلَق ولا تشير إلى خَلَفها.
--
-- ⚠ الملفُّ **idempotent بنمط الشجرة القائم** (SET @ddl + PREPARE): محاولةٌ
-- أولى فشلت عند `CHECK` يشير إلى عمود auto_increment، فبقيت الأعمدةُ مضافةً —
-- والإعادةُ يجب أن تمرّ لا أن تصطدم بـ«Duplicate column».
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① عمودُ الحالة وأثرُ الانتقال ──────────────────────────────────────────
-- الافتراضُ `draft` **عمدًا**: كاتبٌ ينسى الحالةَ يُنتج صفًّا عاطلًا لا صفًّا
-- يسعّر — فالنسيانُ يُسقط ولا يُسرّب (fail-closed).
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'contract_hour_policies'
                  AND COLUMN_NAME = 'policy_state'),
    'ALTER TABLE `contract_hour_policies`
       ADD COLUMN `policy_state` ENUM(''draft'',''active'',''superseded'',''expired'')
           NOT NULL DEFAULT ''draft''
           COMMENT ''UX-06 §8.2: Draft→Active→Superseded→Expired — والمسودةُ لا تُقرأ في أي احتساب'' AFTER `is_trial`,
       ADD COLUMN `superseded_by` INT UNSIGNED NULL DEFAULT NULL
           COMMENT ''السياسةُ الأحدثُ التي أخلفتها — «Superseded بسياسةٍ أحدث» بمرجعها لا بالدعوى'' AFTER `policy_state`,
       ADD COLUMN `state_changed_at` DATETIME NULL DEFAULT NULL AFTER `superseded_by`,
       ADD COLUMN `state_changed_by` INT UNSIGNED NULL DEFAULT NULL AFTER `state_changed_at`,
       ADD COLUMN `state_note` VARCHAR(200) NULL DEFAULT NULL
           COMMENT ''سببُ الانتقال — إلزاميٌّ عند الإنهاء'' AFTER `state_changed_by`',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'contract_hour_policies'
                  AND INDEX_NAME = 'ix_policy_state'),
    'ALTER TABLE `contract_hour_policies`
       ADD KEY `ix_policy_state` (`company_id`, `party_scope`, `policy_state`),
       ADD KEY `ix_policy_superseded` (`superseded_by`)',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- ── ② القيود — البنيةُ تحرس ما لا يُنسى ────────────────────────────────────
-- ⚠ «لا تخلف السياسةُ نفسَها» **لا يمكن أن يكون CHECK**: MySQL يرفض قيدًا يشير
-- إلى عمود auto_increment (مقيسٌ حرفيًّا: «cannot refer to an auto-increment
-- column»). فالحارسُ في `PayPolicyStateMachine::activate` وحدَه — **معلَنًا
-- هنا** ولا يُدَّعى أنه بنيوي.
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'contract_hour_policies'
                  AND CONSTRAINT_NAME = 'ck_chp_superseded'),
    'ALTER TABLE `contract_hour_policies`
       ADD CONSTRAINT `ck_chp_superseded`
           CHECK (`policy_state` <> ''superseded'' OR `superseded_by` IS NOT NULL)',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'contract_hour_policies'
                  AND CONSTRAINT_NAME = 'ck_chp_expired_note'),
    'ALTER TABLE `contract_hour_policies`
       ADD CONSTRAINT `ck_chp_expired_note`
           CHECK (`policy_state` <> ''expired''
                  OR (`state_note` IS NOT NULL AND `state_note` <> ''''))',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- ── ③ التعبئةُ الرجعية — **تصريحُ حالةٍ لا تغييرُ سلوك** ────────────────────
-- القائمُ كلُّه يُقرأ اليوم فعلًا، فحالتُه الصادقةُ `active`. ثم يُصرَّح ما
-- انقضى سريانُه أو أُوقف ناعمًا — و**صفرُ صفٍّ يتغير سلوكُه** لأن القرّاء
-- يستثنون `draft` وحدَها. والشرطُ `state_changed_at IS NULL` يجعلها تمرّ مرةً
-- فلا تدهس تصريحًا لاحقًا عند إعادة التشغيل.
UPDATE `contract_hour_policies`
   SET `policy_state` = 'active',
       `state_changed_at` = NOW(),
       `state_note` = 'تصريحُ حالةٍ رجعيٌّ عند E-24 — الصفُّ كان يُقرأ فعلًا'
 WHERE `state_changed_at` IS NULL;

UPDATE `contract_hour_policies`
   SET `policy_state` = 'expired',
       `state_note` = 'انقضى سريانُها قبل E-24 — تصريحٌ رجعيٌّ بتاريخها المكتوب'
 WHERE `policy_state` = 'active'
   AND `effective_to` IS NOT NULL AND `effective_to` < CURDATE();

UPDATE `contract_hour_policies`
   SET `policy_state` = 'expired',
       `state_note` = 'أُوقفت ناعمًا قبل E-24 — تصريحٌ رجعيٌّ لما وقع'
 WHERE `policy_state` = 'active'
   AND (`deleted_at` IS NOT NULL OR COALESCE(`is_deleted`,0) = 1);
