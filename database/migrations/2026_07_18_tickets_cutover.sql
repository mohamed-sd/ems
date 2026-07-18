-- ═══════════════════════════════════════════════════════════════════════════
-- S12 — القطع (المرحلة 9): إحلال نظام البلاغات الجديد محلّ القديم
--
-- قرارُ المستخدم الصريح (2026-07-18): «ألغِ القديم نهائيًّا واعتمد الجديد فقط».
--
-- «الإلغاء النهائيّ» هنا = **تقاعدُ سطح الاستقبال** (الرابط + الشاشة + الوحدة 42)
-- مع **إبقاء جدول mnt_breakdown أرشيفًا**، لأنّ `mnt_order.breakdown_id` يشير
-- إليه كمصدرٍ للأمر. (تحقّقٌ حيٌّ 2026-07-18: صفر أوامرَ تشير إليه فعليًّا الآن،
-- فالإبقاء احتياطٌ رخيصٌ لا ضرورة — ولا نحذف بيانات المستخدم بلا داعٍ.)
-- بعد هذه الهجرة: لا كاتبَ جديدًا في mnt_breakdown إطلاقًا؛ يبقى للقراءة فقط.
--
-- ① ترحيل الصفوف إلى tickets  ② حدث تتبّع بالكود الأصلي  ③ تقاعد الوحدة 42
-- idempotent: الترحيل محروسٌ بعدم وجود حدث الترحيل لنفس الكود.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① الترحيل: mnt_breakdown ← tickets ──────────────────────────────────────
-- الخريطة: description→complaint · target_role→owner_role_id (فارغ ⇒ الصيانة 13)
--          severity→priority · is_stopped→machine_condition · state→stage
--          report_datetime→call_date/call_time · reported_by→reporter_user_id
--          order_id→linked_ref (إن وُجد) · الترقيم في نطاق 8xxx (لا يصطدم بالحيّ 9100+)
SET @seq := 8000;

INSERT INTO tickets
  (company_id, ticket_no, ticket_type_id, stage, ticket_nature, priority, business_impact,
   production_critical, call_date, call_time, reporting_person, reporter_user_id,
   project_id, equipment_id, machine_condition, complaint, owner_role_id,
   linked_ref_table, linked_ref_id, created_by, created_at)
SELECT
  b.company_id,
  CONCAT(DATE_FORMAT(b.report_datetime, '%y-%m'), '-', (@seq := @seq + 1)),
  (SELECT id FROM ticket_types WHERE company_id IS NULL AND code = 'mnt_breakdown'),
  CASE b.state WHEN 'مغلق' THEN 'closed' WHEN 'محوّل' THEN 'in_progress' ELSE 'routed' END,
  'incident',
  CASE b.severity WHEN 'حرجة' THEN 'critical' WHEN 'عالية' THEN 'high' ELSE 'normal' END,
  CASE WHEN b.severity = 'حرجة' THEN 'production_critical' ELSE 'admin' END,
  COALESCE(b.is_stopped, 0),
  DATE(b.report_datetime),
  DATE_FORMAT(b.report_datetime, '%H:%i'),
  COALESCE((SELECT u.name FROM users u WHERE u.id = b.reported_by),
           NULLIF(b.reporter_dept, ''), 'مُبلِّغ سابق'),
  b.reported_by,
  b.project_id,
  b.equipment_id,
  CASE WHEN COALESCE(b.is_stopped, 0) = 1 THEN 'stopped' ELSE 'running' END,
  COALESCE(NULLIF(b.description, ''), 'بلاغ مُرحَّل بلا وصف'),
  COALESCE(b.target_role, 13),
  CASE WHEN b.order_id IS NOT NULL THEN 'mnt_order' ELSE NULL END,
  b.order_id,
  b.reported_by,
  b.report_datetime
FROM mnt_breakdown b
WHERE COALESCE(b.is_deleted, 0) = 0
  AND NOT EXISTS (
    SELECT 1 FROM ticket_events e
     WHERE e.event_type = 'system'
       AND e.body = CONCAT('مُرحَّل من نظام البلاغات القديم — الكود الأصلي: ', b.code)
  );

-- ── ② حدث التتبّع: يحفظ الكود الأصلي BR ورمز العطل (لا يُفقد أثرٌ) ──────────
INSERT INTO ticket_events (company_id, ticket_id, event_type, actor_user_id, actor_role_id, body, new_value, created_at)
SELECT t.company_id, t.id, 'system', b.reported_by, 24,
       CONCAT('مُرحَّل من نظام البلاغات القديم — الكود الأصلي: ', b.code),
       t.stage, b.report_datetime
FROM tickets t
JOIN mnt_breakdown b
  ON b.company_id = t.company_id
 AND DATE(b.report_datetime) = t.call_date
 AND COALESCE(NULLIF(b.description, ''), 'بلاغ مُرحَّل بلا وصف') = t.complaint
WHERE t.ticket_no LIKE '%-8%'
  AND NOT EXISTS (
    SELECT 1 FROM ticket_events e2
     WHERE e2.ticket_id = t.id AND e2.event_type = 'system'
       AND e2.body LIKE 'مُرحَّل من نظام البلاغات القديم%'
  );

-- ── متابعة المُبلِّغ على تذاكره المُرحَّلة ────────────────────────────────────
INSERT INTO ticket_watchers (company_id, ticket_id, user_id, role_id, watch_reason)
SELECT t.company_id, t.id, t.reporter_user_id, NULL, 'reporter'
FROM tickets t
WHERE t.ticket_no LIKE '%-8%' AND t.reporter_user_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM ticket_watchers w WHERE w.ticket_id = t.id AND w.user_id = t.reporter_user_id);

-- ── استحقاق المُرحَّلة (نفس قاعدة المطابقة: الحرِج 2/24 · العام 24/72) ────────
UPDATE tickets t
   SET t.sla_policy_id = (SELECT id FROM ticket_sla_policies
                           WHERE company_id = t.company_id AND priority = 'critical' AND active = 1 LIMIT 1),
       t.response_due_at   = DATE_ADD(CONCAT(t.call_date,' ',COALESCE(NULLIF(t.call_time,''),'00:00')), INTERVAL 2 HOUR),
       t.resolution_due_at = DATE_ADD(CONCAT(t.call_date,' ',COALESCE(NULLIF(t.call_time,''),'00:00')), INTERVAL 24 HOUR)
 WHERE t.ticket_no LIKE '%-8%' AND t.priority = 'critical' AND t.resolution_due_at IS NULL;

UPDATE tickets t
   SET t.sla_policy_id = (SELECT id FROM ticket_sla_policies
                           WHERE company_id = t.company_id AND priority IS NULL AND ticket_type_id IS NULL
                             AND business_impact IS NULL AND active = 1 LIMIT 1),
       t.response_due_at   = DATE_ADD(CONCAT(t.call_date,' ',COALESCE(NULLIF(t.call_time,''),'00:00')), INTERVAL 24 HOUR),
       t.resolution_due_at = DATE_ADD(CONCAT(t.call_date,' ',COALESCE(NULLIF(t.call_time,''),'00:00')), INTERVAL 72 HOUR)
 WHERE t.ticket_no LIKE '%-8%' AND t.priority <> 'critical' AND t.resolution_due_at IS NULL;

-- ── ③ تقاعد الوحدة 42 (تختفي من السايدبار؛ الصف يبقى للتاريخ) ───────────────
UPDATE `modules` SET `is_link` = '0', `name` = 'البلاغات (متقاعدة — أُحيلت للنظام الموحّد)'
 WHERE `id` = 42;

-- ═══════════════════════════════════════════════════════════════════════════
-- ROLLBACK (يدويًا عند الطلب):
--   DELETE FROM tickets WHERE ticket_no LIKE '%-8%' AND ticket_no NOT LIKE '26-07-9%';  -- الأبناء CASCADE
--   UPDATE modules SET is_link='1', name='البلاغات' WHERE id=42;
--   + إرجاع رابط التوبار وملف Maintenance/breakdowns.php من git
-- ═══════════════════════════════════════════════════════════════════════════
