-- ═══════════════════════════════════════════════════════════════════════════
-- P-01-ب · «صفرُ عقدٍ بلا مشروع» — المفتاحُ الخارجيُّ هو الحارسُ الحقيقي
-- 2026-08-01 · تكملةٌ لـ`2026_09_22_p01_contract_operational_sites.sql`
-- ───────────────────────────────────────────────────────────────────────────
-- ⚠ گوتشا مقيسةٌ أثناء اختبار القبول — **و`NOT NULL` وحدَه لا يكفي**:
--   `SELECT @@sql_mode` = **فارغ** في هذه البيئة (غيرُ صارم). فإدراجٌ يُغفل
--   عمودًا `INT NOT NULL` بلا افتراضٍ **يكتب صفرًا صامتًا** — فيمرّ «عقدٌ بلا
--   مشروع» رغم `NOT NULL`. وهو النظيرُ الحرفيُّ لگوتشا «ENUM يبتلع الخطأ».
--
-- الحلُّ: **مفتاحٌ خارجيٌّ إلى `project`** — والصفرُ ليس معرّفًا في `project`
-- فيُرفض بنيويًّا مهما كان `sql_mode`.
--
-- المقيسُ قبل التطبيق: **صفرُ صفٍّ يتيمٍ** في `contracts.project_id` (10 عقود
-- كلُّها بمشاريعَ قائمة) — فالإضافةُ آمنةٌ ولا تكسر صفًّا.
-- ═══════════════════════════════════════════════════════════════════════════

SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'contracts'
                  AND CONSTRAINT_NAME = 'fk_contracts_project'),
    'ALTER TABLE `contracts`
       ADD CONSTRAINT `fk_contracts_project` FOREIGN KEY (`project_id`)
           REFERENCES `project` (`id`)',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;
