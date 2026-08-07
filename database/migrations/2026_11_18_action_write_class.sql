-- U10-A5 (2026-08-06 · تفويض المالك جلسة update0010): تصنيف الكتابة للأفعال
-- ═══════════════════════════════════════════════════════════════════════════
-- الورقة 21 من INJAZ-MASTER-MAP-1: لكل فعل write_class يحدد لازم سجل التدقيق:
--   Read Only            لا يكتب — سجل الاطلاع إن مسّ حساسًا
--   Domain Write         يكتب جداول المجال — تدقيق بالفاعل والوقت وقبل/بعد
--   Governance Write     يكتب الحوكمة (صلاحيات/قوالب) — تدقيق مشدد
--   External Side Effect أثر خارجي (بريد/ملف) — سجل الإرسال
-- idempotent.

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nav09_action_map'
              AND COLUMN_NAME = 'write_class');
SET @ddl := IF(@c = 0,
  'ALTER TABLE `nav09_action_map`
     ADD COLUMN `write_class` ENUM(''read_only'',''domain_write'',''governance_write'',''external_side_effect'') NULL
       COMMENT ''U10 ورقة 21 — تصنيف الكتابة ولازم التدقيق'' AFTER `state`',
  'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
