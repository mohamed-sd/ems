-- ═══════════════════════════════════════════════════════════════════════════
-- S12 — حسابٌ رجعيٌّ لمواعيد الاستحقاق على تذاكر الديمو (م5) — co4
--
-- تذاكر البذر (26-07-90xx) أُنشئت قبل محرّك الاستحقاق فبقيت مواعيدُها فارغة.
-- هذه الهجرة تطبّق **نفس قاعدة المطابقة** المنفَّذة في tkt_match_sla_policy
-- (الأكثر تحديدًا يفوز): سياسة الحرِج (priority='critical' ⇒ 2/24) تسبق
-- السياسة العامة (بلا شروط ⇒ 24/72)؛ والساعات تقويميّة (قرار المستخدم ②).
-- النتيجة: مشهدٌ واقعيٌّ فيه متأخّراتٌ فعلية تُغذّي الدورة المجدوَلة واللوحة.
-- idempotent: يقتصر على الصفوف التي لم تُحسب بعد (resolution_due_at IS NULL).
-- ═══════════════════════════════════════════════════════════════════════════

-- ── الحرِجة ⇒ سياسة الحرِج (2 ساعة استجابة · 24 ساعة إنجاز) ─────────────────
UPDATE tickets t
   SET t.sla_policy_id = (SELECT id FROM ticket_sla_policies
                           WHERE company_id = 4 AND priority = 'critical' AND active = 1 LIMIT 1),
       t.response_due_at   = DATE_ADD(CONCAT(t.call_date, ' ', COALESCE(NULLIF(t.call_time,''), '00:00')), INTERVAL 2 HOUR),
       t.resolution_due_at = DATE_ADD(CONCAT(t.call_date, ' ', COALESCE(NULLIF(t.call_time,''), '00:00')), INTERVAL 24 HOUR)
 WHERE t.company_id = 4
   AND t.ticket_no LIKE '26-07-90%'
   AND t.priority = 'critical'
   AND t.resolution_due_at IS NULL;

-- ── البقيّة ⇒ السياسة العامة (24 ساعة استجابة · 72 ساعة إنجاز) ──────────────
UPDATE tickets t
   SET t.sla_policy_id = (SELECT id FROM ticket_sla_policies
                           WHERE company_id = 4 AND priority IS NULL AND ticket_type_id IS NULL
                             AND business_impact IS NULL AND active = 1 LIMIT 1),
       t.response_due_at   = DATE_ADD(CONCAT(t.call_date, ' ', COALESCE(NULLIF(t.call_time,''), '00:00')), INTERVAL 24 HOUR),
       t.resolution_due_at = DATE_ADD(CONCAT(t.call_date, ' ', COALESCE(NULLIF(t.call_time,''), '00:00')), INTERVAL 72 HOUR)
 WHERE t.company_id = 4
   AND t.ticket_no LIKE '26-07-90%'
   AND t.priority <> 'critical'
   AND t.resolution_due_at IS NULL;

-- ── أول إجراء للتذاكر التي بدأت فعلًا (لقياس الاستجابة §10) ────────────────
UPDATE tickets
   SET first_action_at = CONCAT(call_date, ' ', COALESCE(NULLIF(call_time,''), '00:00'))
 WHERE company_id = 4 AND ticket_no LIKE '26-07-90%'
   AND first_action_at IS NULL
   AND stage IN ('in_progress','waiting','follow_up','done','closed');

-- ═══════════════════════════════════════════════════════════════════════════
-- ROLLBACK: UPDATE tickets SET sla_policy_id=NULL, response_due_at=NULL,
--           resolution_due_at=NULL WHERE company_id=4 AND ticket_no LIKE '26-07-90%';
-- ═══════════════════════════════════════════════════════════════════════════
