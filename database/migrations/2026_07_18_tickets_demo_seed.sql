-- ═══════════════════════════════════════════════════════════════════════════
-- S12 — بذر تجريبي شامل لوحدة البلاغات (شركة الاختبار co4) — نمط *_demo_seed
--
-- 12 تذكرة تغطي كل الإدارات التسع بمراحل متنوعة (محالة/تنفيذ/انتظار/متابعة/
-- منجزة/مغلقة/ملغاة + رئيسية/فرعية) مع أحداثها وتحويلها ومتابعيها +
-- سياستا SLA + قاعدة تصعيد + قالب دوري.
-- أرقام العرض 26-07-9001+ (نطاق ديمو) + رفع أرضية متتالية c4/y26 إلى 9100
-- فلا يصطدم الترقيم الحي بها أبدًا (uq ticket_no يحمي بنيويًا أيضًا).
-- idempotent: حارس NOT EXISTS لكل صف. المُبلِّغ الافتراضي = مستخدم UAT #324.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ⓪ رفع أرضية المتتالية (نمط ServerId — قفل صف) ───────────────────────────
INSERT INTO `ems_sequences` (`scope`, `next_val`) VALUES ('tickets:c4:y26', 9100)
ON DUPLICATE KEY UPDATE `next_val` = GREATEST(`next_val`, 9100);

-- ── ① سياسات SLA (شركة 4) ───────────────────────────────────────────────────
INSERT INTO ticket_sla_policies (company_id, name, priority, response_hours, resolution_hours, remind_before_hours)
SELECT 4, 'سياسة الحرِج — استجابة ساعتان وإنجاز 24 ساعة', 'critical', 2.00, 24.00, 4.00
WHERE NOT EXISTS (SELECT 1 FROM ticket_sla_policies WHERE company_id = 4 AND name LIKE 'سياسة الحرِج%');
INSERT INTO ticket_sla_policies (company_id, name, response_hours, resolution_hours, remind_before_hours)
SELECT 4, 'السياسة العادية — استجابة 24 ساعة وإنجاز 72 ساعة', 24.00, 72.00, 12.00
WHERE NOT EXISTS (SELECT 1 FROM ticket_sla_policies WHERE company_id = 4 AND name LIKE 'السياسة العادية%');

-- ── ② قاعدة تصعيد (شركة 4) ──────────────────────────────────────────────────
INSERT INTO ticket_escalation_rules (company_id, name, level_no, escalate_after_hours, escalate_to_role)
SELECT 4, 'تجاوز الاستحقاق ← مدير الإدارة المنفِّذة', 1, 24.00, 'dept_manager'
WHERE NOT EXISTS (SELECT 1 FROM ticket_escalation_rules WHERE company_id = 4 AND level_no = 1);

-- ── ③ قالب دوري (شركة 4) ────────────────────────────────────────────────────
INSERT INTO ticket_recurrence_templates
  (company_id, name, ticket_type_id, recurrence_interval, recurrence_unit, next_occurrence_date, lead_time_days, default_priority)
SELECT 4, 'تفتيش شهري دوري لمعدات الموقع',
       (SELECT id FROM ticket_types WHERE company_id IS NULL AND code = 'fleet_inspection'),
       1, 'month', '2026-08-01', 3, 'normal'
WHERE NOT EXISTS (SELECT 1 FROM ticket_recurrence_templates WHERE company_id = 4 AND name LIKE 'تفتيش شهري%');

-- ═══════════════════════════════════════════════════════════════════════════
-- ④ التذاكر (نمط موحد: INSERT..SELECT بحارس ticket_no)
-- ═══════════════════════════════════════════════════════════════════════════

-- 9001 · صيانة (13) · محالة · حرجة توقف الإنتاج
INSERT INTO tickets (company_id, ticket_no, ticket_type_id, stage, ticket_nature, priority, business_impact, production_critical,
                     call_date, call_time, reporting_person, reporter_contact, reporter_user_id,
                     equipment_id, machine_condition, complaint, owner_role_id, created_by, created_at,
                     category_id)
SELECT 4, '26-07-9001', (SELECT id FROM ticket_types WHERE company_id IS NULL AND code='mnt_breakdown'),
       'routed', 'incident', 'critical', 'production_critical', 1,
       '2026-07-16', '07:45', 'علي حمدان', '0920001001', 324,
       (SELECT id FROM equipments WHERE company_id=4 ORDER BY id LIMIT 1), 'stopped',
       'توقف كامل لمحرك الحفار الرئيسي في موقع التعدين — صوت طرقٍ ودخان أبيض.',
       13, 324, '2026-07-16 07:50:00',
       (SELECT id FROM ticket_categories WHERE company_id IS NULL AND code='engine')
WHERE NOT EXISTS (SELECT 1 FROM tickets WHERE company_id=4 AND ticket_no='26-07-9001');

-- 9002 · صيانة (13) · مغلقة (تاريخ كامل)
INSERT INTO tickets (company_id, ticket_no, ticket_type_id, stage, ticket_nature, priority, business_impact,
                     call_date, call_time, reporting_person, reporter_user_id, machine_condition, complaint,
                     owner_role_id, created_by, created_at, first_action_at, close_date, close_time, closed_by,
                     category_id, service_team, issue_status)
SELECT 4, '26-07-9002', (SELECT id FROM ticket_types WHERE company_id IS NULL AND code='mnt_breakdown'),
       'closed', 'incident', 'high', 'revenue',
       '2026-07-12', '10:15', 'مشرف الوردية الصباحية', 324, 'running',
       'تسريب هيدروليك في ذراع الحفار — يعمل بكفاءة منخفضة.',
       13, 324, '2026-07-12 10:20:00', '2026-07-12 13:00:00', '2026-07-14', '16:30', 324,
       (SELECT id FROM ticket_categories WHERE company_id IS NULL AND code='hydraulic'),
       'internal', 'استُبدل الخرطوم HP-40 وأُعيد ضبط الضغط — عادت للعمل الكامل.'
WHERE NOT EXISTS (SELECT 1 FROM tickets WHERE company_id=4 AND ticket_no='26-07-9002');

-- 9003 · نقل (23) · قيد التنفيذ
INSERT INTO tickets (company_id, ticket_no, ticket_type_id, stage, ticket_nature, priority, business_impact,
                     call_date, call_time, reporting_person, reporter_user_id, complaint, owner_role_id,
                     created_by, created_at, first_action_at)
SELECT 4, '26-07-9003', (SELECT id FROM ticket_types WHERE company_id IS NULL AND code='transport_request'),
       'in_progress', 'request', 'high', 'production_critical',
       '2026-07-15', '09:00', 'مدير المشروع الشرقي', 324,
       'مطلوب ترحيل بلدوزر D9 من الموقع الشمالي إلى مشروع المحجر الشرقي قبل نهاية الأسبوع.',
       23, 324, '2026-07-15 09:05:00', '2026-07-15 11:30:00'
WHERE NOT EXISTS (SELECT 1 FROM tickets WHERE company_id=4 AND ticket_no='26-07-9003');

-- 9004 · مشتريات (16) · بانتظار جهة أخرى
INSERT INTO tickets (company_id, ticket_no, ticket_type_id, stage, ticket_nature, priority, business_impact,
                     call_date, call_time, reporting_person, reporter_user_id, complaint, owner_role_id,
                     created_by, created_at, first_action_at)
SELECT 4, '26-07-9004', (SELECT id FROM ticket_types WHERE company_id IS NULL AND code='parts_request'),
       'waiting', 'request', 'high', 'production_critical',
       '2026-07-14', '14:20', 'مدير الصيانة', 19,
       'مطلوب فلتر زيت رئيسي + طقم صرة أمامية للحفار — لإكمال إصلاح البلاغ 26-07-9001.',
       16, 19, '2026-07-14 14:25:00', '2026-07-15 08:10:00'
WHERE NOT EXISTS (SELECT 1 FROM tickets WHERE company_id=4 AND ticket_no='26-07-9004');

-- 9005 · تمويل (17) · منجزة
INSERT INTO tickets (company_id, ticket_no, ticket_type_id, stage, ticket_nature, priority, business_impact,
                     call_date, call_time, reporting_person, reporter_user_id, complaint, owner_role_id,
                     created_by, created_at, first_action_at)
SELECT 4, '26-07-9005', (SELECT id FROM ticket_types WHERE company_id IS NULL AND code='finance_request'),
       'done', 'request', 'normal', 'admin',
       '2026-07-13', '11:00', 'مسؤول المشتريات', 324,
       'طلب دفعة مقدمة لمورد قطع الغيار (فاتورة عرض السعر رقم Q-2211).',
       17, 324, '2026-07-13 11:05:00', '2026-07-13 12:00:00'
WHERE NOT EXISTS (SELECT 1 FROM tickets WHERE company_id=4 AND ticket_no='26-07-9005');

-- 9006 · القوى (4) · محالة
INSERT INTO tickets (company_id, ticket_no, ticket_type_id, stage, ticket_nature, priority, business_impact,
                     call_date, call_time, reporting_person, reporter_user_id, complaint, owner_role_id, created_by, created_at)
SELECT 4, '26-07-9006', (SELECT id FROM ticket_types WHERE company_id IS NULL AND code='workforce_request'),
       'routed', 'request', 'normal', 'admin',
       '2026-07-17', '08:30', 'مشرف موقع الروسية', 324,
       'مطلوب مشغل حفار بديل لتغطية إجازة المشغل الأساسي لمدة أسبوع.',
       4, 324, '2026-07-17 08:35:00'
WHERE NOT EXISTS (SELECT 1 FROM tickets WHERE company_id=4 AND ticket_no='26-07-9006');

-- 9007 · الأسطول (3) · قيد المتابعة · دورية
INSERT INTO tickets (company_id, ticket_no, ticket_type_id, stage, ticket_nature, priority, business_impact,
                     call_date, call_time, reporting_person, reporter_user_id, complaint, owner_role_id,
                     created_by, created_at, first_action_at, is_recurring)
SELECT 4, '26-07-9007', (SELECT id FROM ticket_types WHERE company_id IS NULL AND code='fleet_inspection'),
       'follow_up', 'recurring', 'normal', 'admin',
       '2026-07-10', '09:00', 'النظام (توليد دوري)', 324,
       'التفتيش الدوري الشهري لمعدات الموقع الشمالي — يوليو 2026.',
       3, 324, '2026-07-10 09:00:00', '2026-07-11 10:00:00', 1
WHERE NOT EXISTS (SELECT 1 FROM tickets WHERE company_id=4 AND ticket_no='26-07-9007');

-- 9008 · الموردون (2) · محالة (وُجهت أولًا للصيانة خطأً ثم حُوِّلت — قيد تحويل)
INSERT INTO tickets (company_id, ticket_no, ticket_type_id, stage, ticket_nature, priority, business_impact,
                     call_date, call_time, reporting_person, reporter_user_id, complaint, owner_role_id, created_by, created_at)
SELECT 4, '26-07-9008', (SELECT id FROM ticket_types WHERE company_id IS NULL AND code='supplier_issue'),
       'routed', 'incident', 'high', 'revenue',
       '2026-07-16', '13:40', 'محاسب الموقع', 324,
       'تأخر مورد الوقود عن جدول التسليم ثلاثة أيام — يهدد تشغيل الوردية المسائية.',
       2, 324, '2026-07-16 13:45:00'
WHERE NOT EXISTS (SELECT 1 FROM tickets WHERE company_id=4 AND ticket_no='26-07-9008');

-- 9009 · المبيعات (12) · ملغاة (مكررة)
INSERT INTO tickets (company_id, ticket_no, ticket_type_id, stage, ticket_nature, priority, business_impact,
                     call_date, call_time, reporting_person, reporter_user_id, complaint, owner_role_id, created_by, created_at)
SELECT 4, '26-07-9009', (SELECT id FROM ticket_types WHERE company_id IS NULL AND code='client_complaint'),
       'cancelled', 'incident', 'normal', 'revenue',
       '2026-07-15', '16:00', 'عميل: شركة المحاجر المتحدة', 324,
       'اعتراض على احتساب ساعات الحفار في مستخلص يونيو.',
       12, 324, '2026-07-15 16:05:00'
WHERE NOT EXISTS (SELECT 1 FROM tickets WHERE company_id=4 AND ticket_no='26-07-9009');

-- 9010 · التشغيل (1) · بلاغ سلامة · رئيسية · قيد التنفيذ
INSERT INTO tickets (company_id, ticket_no, ticket_type_id, stage, ticket_nature, priority, business_impact,
                     call_date, call_time, reporting_person, reporter_user_id, complaint, owner_role_id,
                     created_by, created_at, first_action_at, is_parent, ticket_role)
SELECT 4, '26-07-9010', (SELECT id FROM ticket_types WHERE company_id IS NULL AND code='safety_incident'),
       'in_progress', 'incident', 'critical', 'safety',
       '2026-07-17', '06:50', 'مسؤول السلامة', 324,
       'انهيار جزئي لحافة المصطبة الجنوبية قرب مسار الشاحنات — مطلوب تأمين فوري للمسار.',
       1, 324, '2026-07-17 06:55:00', '2026-07-17 07:10:00', 1, 'parent'
WHERE NOT EXISTS (SELECT 1 FROM tickets WHERE company_id=4 AND ticket_no='26-07-9010');

-- 9011 · فرعية عن 9010 → مشتريات (16) · محالة
INSERT INTO tickets (company_id, ticket_no, ticket_type_id, stage, ticket_nature, priority, business_impact,
                     call_date, call_time, reporting_person, reporter_user_id, complaint, owner_role_id,
                     created_by, created_at, ticket_role, parent_id)
SELECT 4, '26-07-9011', (SELECT id FROM ticket_types WHERE company_id IS NULL AND code='parts_request'),
       'routed', 'request', 'critical', 'safety',
       '2026-07-17', '07:30', 'مسؤول السلامة', 324,
       'فرعية عن بلاغ السلامة 26-07-9010: توريد حواجز خرسانية وشرائط تحذير للمسار البديل.',
       16, 324, '2026-07-17 07:35:00', 'child',
       (SELECT t2.id FROM (SELECT id FROM tickets WHERE company_id=4 AND ticket_no='26-07-9010') t2)
WHERE NOT EXISTS (SELECT 1 FROM tickets WHERE company_id=4 AND ticket_no='26-07-9011');

-- 9012 · التشغيل (1) · دعم تشغيلي · محالة
INSERT INTO tickets (company_id, ticket_no, ticket_type_id, stage, ticket_nature, priority, business_impact,
                     call_date, call_time, reporting_person, reporter_user_id, complaint, owner_role_id, created_by, created_at)
SELECT 4, '26-07-9012', (SELECT id FROM ticket_types WHERE company_id IS NULL AND code='ops_support'),
       'routed', 'request', 'normal', 'admin',
       '2026-07-18', '07:15', 'مشرف الوردية المسائية', 19,
       'مطلوب تمديد الوردية المسائية ساعتين لتعويض توقف أمس.',
       1, 19, '2026-07-18 07:20:00'
WHERE NOT EXISTS (SELECT 1 FROM tickets WHERE company_id=4 AND ticket_no='26-07-9012');

-- ═══════════════════════════════════════════════════════════════════════════
-- ⑤ الأحداث (إلحاقية) — حدث إنشاء لكل تذكرة + أحداث مراحل مطابقة للحالة
-- ═══════════════════════════════════════════════════════════════════════════

-- حدث الإنشاء الموحد (لكل تذاكر الديمو دفعة واحدة)
INSERT INTO ticket_events (company_id, ticket_id, event_type, actor_user_id, actor_role_id, body, new_value, created_at)
SELECT 4, t.id, 'system', t.created_by, 24, 'إنشاء البلاغ وتوجيهه تلقائيًا بحسب النوع', 'routed', t.created_at
FROM tickets t
WHERE t.company_id = 4 AND t.ticket_no LIKE '26-07-90%'
  AND NOT EXISTS (SELECT 1 FROM ticket_events e WHERE e.ticket_id = t.id AND e.event_type = 'system');

-- متابعة المُبلِّغ (لكل تذاكر الديمو)
INSERT INTO ticket_watchers (company_id, ticket_id, user_id, role_id, watch_reason)
SELECT 4, t.id, t.created_by, 24, 'reporter'
FROM tickets t
WHERE t.company_id = 4 AND t.ticket_no LIKE '26-07-90%'
  AND NOT EXISTS (SELECT 1 FROM ticket_watchers w WHERE w.ticket_id = t.id AND w.user_id = t.created_by);

-- 9002 المغلقة: سلسلة كاملة (بدء ← إنجاز ← إغلاق)
INSERT INTO ticket_events (company_id, ticket_id, event_type, actor_user_id, actor_role_id, body, old_value, new_value, created_at)
SELECT 4, t.id, 'status_change', 324, 24, 'بدء التنفيذ', 'routed', 'in_progress', '2026-07-12 13:00:00'
FROM tickets t WHERE t.company_id=4 AND t.ticket_no='26-07-9002'
  AND NOT EXISTS (SELECT 1 FROM ticket_events e WHERE e.ticket_id=t.id AND e.new_value='in_progress');
INSERT INTO ticket_events (company_id, ticket_id, event_type, actor_user_id, actor_role_id, body, old_value, new_value, created_at)
SELECT 4, t.id, 'status_change', 324, 24, 'إنجاز العمل — استُبدل الخرطوم وأُعيد الضبط', 'in_progress', 'done', '2026-07-14 15:00:00'
FROM tickets t WHERE t.company_id=4 AND t.ticket_no='26-07-9002'
  AND NOT EXISTS (SELECT 1 FROM ticket_events e WHERE e.ticket_id=t.id AND e.new_value='done');
INSERT INTO ticket_events (company_id, ticket_id, event_type, actor_user_id, actor_role_id, body, old_value, new_value, created_at)
SELECT 4, t.id, 'status_change', 324, 24, 'إغلاق التذكرة بعد تأكيد الموقع', 'done', 'closed', '2026-07-14 16:30:00'
FROM tickets t WHERE t.company_id=4 AND t.ticket_no='26-07-9002'
  AND NOT EXISTS (SELECT 1 FROM ticket_events e WHERE e.ticket_id=t.id AND e.new_value='closed');

-- 9004 المنتظِرة: سبب الانتظار (تواصل عابر للإدارات)
INSERT INTO ticket_events (company_id, ticket_id, event_type, actor_user_id, actor_role_id, body, old_value, new_value, created_at)
SELECT 4, t.id, 'status_change', 324, 24, 'تعليق (بانتظار جهة) — السبب: بانتظار عرض سعر المورد للفلتر الأصلي', 'in_progress', 'waiting', '2026-07-15 12:40:00'
FROM tickets t WHERE t.company_id=4 AND t.ticket_no='26-07-9004'
  AND NOT EXISTS (SELECT 1 FROM ticket_events e WHERE e.ticket_id=t.id AND e.new_value='waiting');
INSERT INTO ticket_events (company_id, ticket_id, event_type, actor_user_id, actor_role_id, body, created_at)
SELECT 4, t.id, 'communication', 19, 13, 'الصيانة: القطعة البديلة المتوفرة محليًا غير مطابقة — نرجو الالتزام بالأصلي.', '2026-07-15 13:05:00'
FROM tickets t WHERE t.company_id=4 AND t.ticket_no='26-07-9004'
  AND NOT EXISTS (SELECT 1 FROM ticket_events e WHERE e.ticket_id=t.id AND e.event_type='communication');

-- 9008: قيد تحويل (وُجهت للصيانة خطأً ثم حُوِّلت للموردين) + حدث التحويل
INSERT INTO ticket_transfers (company_id, ticket_id, from_role_id, to_role_id, transferred_by, reason, transfer_datetime)
SELECT 4, t.id, 13, 2, 324, 'التصنيف الأول خطأ — المشكلة تعاقدية مع المورد لا فنية', '2026-07-16 15:00:00'
FROM tickets t WHERE t.company_id=4 AND t.ticket_no='26-07-9008'
  AND NOT EXISTS (SELECT 1 FROM ticket_transfers x WHERE x.ticket_id=t.id);
INSERT INTO ticket_events (company_id, ticket_id, event_type, actor_user_id, actor_role_id, body, old_value, new_value, created_at)
SELECT 4, t.id, 'transfer', 324, 24, 'تحويل الملكية — السبب: المشكلة تعاقدية مع المورد لا فنية', 'ادارة الصيانة', 'ادارة الموردين', '2026-07-16 15:00:00'
FROM tickets t WHERE t.company_id=4 AND t.ticket_no='26-07-9008'
  AND NOT EXISTS (SELECT 1 FROM ticket_events e WHERE e.ticket_id=t.id AND e.event_type='transfer');

-- 9009 الملغاة: سبب الإلغاء
INSERT INTO ticket_events (company_id, ticket_id, event_type, actor_user_id, actor_role_id, body, old_value, new_value, created_at)
SELECT 4, t.id, 'status_change', 324, 24, 'إلغاء التذكرة — السبب: مكررة (نفس اعتراض التذكرة السابقة لدى المبيعات)', 'routed', 'cancelled', '2026-07-15 17:00:00'
FROM tickets t WHERE t.company_id=4 AND t.ticket_no='26-07-9009'
  AND NOT EXISTS (SELECT 1 FROM ticket_events e WHERE e.ticket_id=t.id AND e.new_value='cancelled');

-- 9010 السلامة: تواصل ميداني
INSERT INTO ticket_events (company_id, ticket_id, event_type, actor_user_id, actor_role_id, body, created_at)
SELECT 4, t.id, 'communication', 19, 13, 'أُغلق المسار الجنوبي وحُوِّلت الشاحنات للمسار البديل — بانتظار الحواجز.', '2026-07-17 08:00:00'
FROM tickets t WHERE t.company_id=4 AND t.ticket_no='26-07-9010'
  AND NOT EXISTS (SELECT 1 FROM ticket_events e WHERE e.ticket_id=t.id AND e.event_type='communication');

-- ═══════════════════════════════════════════════════════════════════════════
-- ROLLBACK (يدويًا عند الطلب — الديمو فقط):
--   DELETE FROM tickets WHERE company_id=4 AND ticket_no LIKE '26-07-90%';  -- الأبناء CASCADE
--   DELETE FROM ticket_sla_policies WHERE company_id=4;
--   DELETE FROM ticket_escalation_rules WHERE company_id=4;
--   DELETE FROM ticket_recurrence_templates WHERE company_id=4;
-- ═══════════════════════════════════════════════════════════════════════════
