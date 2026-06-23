-- ════════════════════════════════════════════════════════════════════════════
-- 2026-06-23 — حالات الآليات (op_state) + علم الأمر التلقائي (is_auto)
-- ════════════════════════════════════════════════════════════════════════════
-- نفصل «الحالة التشغيلية» (تعمل/جاهزة/معطلة) عن «الدور» (أساسي/احتياطي).
-- الحالة تُدار من صفحة الحركة فقط؛ الدور ثابت من شاشة التشغيل.
-- خذ نسخة احتياطية قبل التطبيق (تم: scratchpad/db_backup_2026_06_23).
--
-- مهم: طبّق هذا الملف بعميل utf8mb4 وإلا تُخزَّن تسميات ENUM العربية مُكرّرة الترميز
-- (mojibake) فلا تطابق اتصال التطبيق utf8mb4. السطر التالي يضمن ذلك عند mysql.exe < file:
SET NAMES utf8mb4;

-- (أ) عمود الحالة التشغيلية في التشغيلات
ALTER TABLE operations
  ADD COLUMN op_state ENUM('تعمل','جاهزة','معطلة') NOT NULL DEFAULT 'جاهزة'
  COMMENT 'حالة الآلية النشطة — تُدار من صفحة الحركة فقط' AFTER status;

-- ترحيل البيانات: المعطّلة سابقًا تأخذ الحالة الجديدة، والدور يُسترجع من العمود الالتفافي
UPDATE operations
SET op_state = 'معطلة',
    equipment_category = COALESCE(NULLIF(prev_equipment_category,''), 'أساسي')
WHERE equipment_category = 'متعطل';

UPDATE operations
SET op_state = 'جاهزة'
WHERE status = 1 AND equipment_category <> 'متعطل';

-- (ب) علم الأمر التلقائي في أوامر الصيانة (يُستخدم في الجزء 2)
ALTER TABLE mnt_order
  ADD COLUMN is_auto TINYINT(1) NOT NULL DEFAULT 0
  COMMENT 'أمر صيانة أُنشئ تلقائيًا من صفحة الحركة' AFTER source;

CREATE INDEX idx_mnt_order_auto_open
  ON mnt_order (company_id, equipment_id, project_id, is_auto, state);
