-- ═══════════════════════════════════════════════════════════════════════════
-- تمريرُ البلاغات العالقة في «مصنّفة» إلى «محالة»
-- ───────────────────────────────────────────────────────────────────────────
-- شاشةُ الاستقبال صارت تُصنّف وتوجّه في خطوةٍ واحدة (كما يَعِد عنوانُها)، فلم
-- تعد `classified` مرحلةً يُنتجها النظام. الصفوفُ التي دخلتها قبل الإصلاح
-- تُمرَّر إلى «محالة» ما دامت لها إدارةٌ مالكة — فلا يبقى بلاغٌ في مرحلةٍ
-- مهجورة. (الطريقُ لم يعد مسدودًا أصلًا: زرُّ التوجيه يخرج منها، وهذا
-- تنظيفُ أثرٍ لا فتحُ طريق.)
-- ═══════════════════════════════════════════════════════════════════════════

INSERT INTO ticket_events (company_id, ticket_id, event_type, body, old_value, new_value)
SELECT t.company_id, t.id, 'status_change',
       'توجيهٌ آليٌّ عند توحيد مسار الاستقبال — التصنيفُ صار يوجّه في خطوةٍ واحدة',
       'classified', 'routed'
  FROM tickets t
 WHERE t.stage = 'classified'
   AND COALESCE(t.owner_role_id, 0) > 0;

UPDATE tickets
   SET stage = 'routed'
 WHERE stage = 'classified'
   AND COALESCE(owner_role_id, 0) > 0;

-- ما لا مالكَ له يأخذ مالكَ نوعه ثم يُحال — ولا يُترك معلَّقًا
UPDATE tickets t
   JOIN ticket_types tt ON tt.id = t.ticket_type_id
    SET t.owner_role_id = tt.owner_role_id,
        t.stage = 'routed'
 WHERE t.stage = 'classified'
   AND COALESCE(t.owner_role_id, 0) = 0
   AND COALESCE(tt.owner_role_id, 0) > 0;
