-- ═══════════════════════════════════════════════════════════════════════════
-- تقويةُ دور مدير البلاغات — ترميمُ بياناتٍ وتعطيلُ مرجعيةٍ دخيلة
-- ───────────────────────────────────────────────────────────────────────────
-- بياناتٌ فقط (DML) — لا DDL، فحظرُ التعديل البنيوي قائم.
-- المبدأ: **لا حذف**. الدخيلُ يُعطَّل ويبقى صفُّه كاملًا للتدقيق.
-- كلُّ عبارةٍ مُعادةُ التشغيل (idempotent): تكرارُها لا يغيّر شيئًا.
-- ═══════════════════════════════════════════════════════════════════════════

-- ① ترميمُ نصوص البلاغات التي انصهرت إلى «0»
--    السبب كان انزياحَ أنواع bind_param في مسار الإنشاء البرمجي، فمُرِّر نصُّ
--    الشكوى بوصفه عددًا صحيحًا. الملخّصُ التشغيلي نجا (250 حرفًا) فهو مصدرُ
--    الترميم الوحيد المتاح — والتالفُ الذي لا ملخّصَ له يُوسَم ولا يُختلق له نص.
UPDATE tickets
   SET complaint = operational_summary
 WHERE complaint = '0'
   AND operational_summary IS NOT NULL
   AND TRIM(operational_summary) <> '';

UPDATE tickets
   SET complaint = CONCAT('[نصُّ البلاغ الأصلي فُقد بخللٍ تقنيٍّ عند التسجيل — راجع المُبلِّغ] ',
                          COALESCE(NULLIF(TRIM(operational_summary), ''), ''))
 WHERE complaint = '0';

-- أثرٌ في خط الزمن لكل بلاغٍ رُمِّم — الترميمُ واقعةٌ تُوثَّق لا تصحيحٌ صامت
INSERT INTO ticket_events (company_id, ticket_id, event_type, body, new_value)
SELECT t.company_id, t.id, 'system',
       'ترميمٌ تقني: أُعيد نصُّ الشكوى من الملخّص التشغيلي بعد فقده عند التسجيل', 'repaired'
  FROM tickets t
 WHERE t.complaint = t.operational_summary
   AND t.operational_summary IS NOT NULL
   AND TRIM(t.operational_summary) <> ''
   AND NOT EXISTS (SELECT 1 FROM ticket_events e
                    WHERE e.ticket_id = t.id AND e.new_value = 'repaired');

-- ② تعطيلُ سياسات المهلة الدخيلة
--    التوقيعُ موضوعيٌّ لا اسميّ: سياسةٌ حقيقيةٌ تجعل الاستجابةَ أقصرَ من
--    الإنجاز. الصفوفُ المستوردة خطأً تجعلهما متساويين (6.50/6.50 …) وتحمل
--    ticket_type_id فتتفوّق في «الأكثر تحديدًا» على السياستين الحقيقيتين،
--    فتخطف حسابَ المهلة: بلاغُ نقلٍ حرِجٌ يصير 6.5 ساعة بدل 24.
UPDATE ticket_sla_policies
   SET active = 0
 WHERE active = 1
   AND response_hours >= resolution_hours;

-- ③ تعطيلُ القوالب الدورية المستوردة بتواريخ ما قبل عهد التشغيل
--    قالبٌ استحقاقُه في 2024 يُطلق توليدًا فوريًّا بأثرٍ رجعيٍّ عند كل دورة كنس.
UPDATE ticket_recurrence_templates
   SET active = 0
 WHERE active = 1
   AND next_occurrence_date < '2026-01-01';

-- ④ تعطيلُ أنواع البلاغات المستوردة خطأً
--    توقيعُ الاستيراد التالف: حقلُ التصنيف يحمل ملاحظةَ تدقيقٍ لا تصنيفًا،
--    والنوعُ يظهر خيارًا أمام المُبلِّغ في شاشة الاستقبال.
UPDATE ticket_types
   SET active = 0
 WHERE active = 1
   AND category LIKE '%UAT-%';

-- ⑤ اتساقُ نوع بلاغ الحوكمة: إدارةٌ مالكةٌ حقيقية بدل الصفر
UPDATE ticket_types
   SET owner_role_id = 15
 WHERE owner_role_id = 0
   AND name = 'بلاغ حوكمة وصلاحيات';

-- ⑥ مصالحةُ head_state مع stage للصفوف التي انحرفت
--    زرُّ الإغلاق كان يكتب stage وحده، فبقي الرأسُ مفتوحًا للبلاغ المغلق
--    فظلّ ظاهرًا في لوحة المسارات ومرشَّحًا في كاشف التكرار.
--    الإغلاقُ لا يُفرَض على بلاغٍ ما زال له مسارٌ إلزاميٌّ مفتوح.
UPDATE tickets t
   SET t.head_state = 'closed'
 WHERE t.stage IN ('closed', 'cancelled')
   AND t.head_state = 'open'
   AND NOT EXISTS (
        SELECT 1 FROM ticket_workstreams w
         WHERE w.tk_id = t.id AND w.mandatory = 1 AND w.activation_state = 'opened'
           AND w.state NOT IN ('closed', 'admin_closed'));

UPDATE tickets
   SET head_state = 'open'
 WHERE head_state = 'closed'
   AND stage NOT IN ('closed', 'cancelled', 'done');
