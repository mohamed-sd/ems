-- ═══════════════════════════════════════════════════════════════════════════
-- 2026_12_15_u12_action_state_handler.sql
-- التسعةُ الباقيةُ: فعلٌ يخدمه مُرسِلٌ في معالجٍ — لا وحدةَ صلاحياتٍ لمعالج
-- ───────────────────────────────────────────────────────────────────────────
-- بقيت تسعةُ أفعالٍ بحالةٍ فارغةٍ بعد 2026_12_14، وكلُّها تشير إلى
-- `Risk/risk_actions.php` — وهو **معالجُ أفعالٍ (AJAX) لا شاشة**. والمعالجُ
-- لا يُسجَّل وحدةَ صلاحياتٍ في `modules` بحقّ: وحدةُ الصلاحياتِ للوجهةِ التي
-- يفتحها المستخدم، والمعالجُ يُحرَس بحارسِ الأفعالِ لا بمنحِ عرض. ولذلك لم
-- يُطابق شرطُ الهجرةِ السابقةِ هذه التسعة.
--
-- والسابقةُ قائمةٌ في القاعدةِ نفسِها: **24 فعلًا** يشير إلى المعالجِ نفسِه
-- وحالتُه `bound_page` سلفًا. فالتسعةُ استثناءُ إغفالٍ لا استثناءُ حكم.
--
-- وقد قيست حياةُ كلِّ واحدٍ منها بمُرسِلِه في `Risk/risk_actions.php` — رمزُ
-- الوثيقةِ ⇐ حالةُ المُرسِل:
--   GOV-RSK-ATTEST      ⇐ case 'gov_attest'
--   RSK-APPETITE-SET    ⇐ case 'appetite_set'
--   RSK-CTL-FAIL        ⇐ case 'control_fail'
--   RSK-FIELD-SYNC      ⇐ case 'field_sync'
--   RSK-INCIDENT-LOG    ⇐ case 'incident_log'
--   RSK-KRI-THRESHOLD   ⇐ case 'kri_threshold'
--   RSK-REPORT-EXPORT   ⇐ case 'report_export'
--   RSK-REVIEW          ⇐ case 'risk_review'
--   RSK-TAXONOMY-DEFINE ⇐ case 'taxonomy_define'
-- والتسعُ حالاتٍ موجودةٌ حرفًا في المعالجِ لحظةَ كتابةِ هذه الهجرة.
--
-- ◆ الحارسُ: الترقيةُ للرموزِ التسعةِ المسمّاةِ حصرًا — لا لكلِّ فارغٍ، فلا
--   تُرقّى حالةُ فعلٍ لم يُقَس مُرسِلُه.
--
-- عكسُه: UPDATE nav09_action_map SET state = '' للرموزِ التسعة.
-- ═══════════════════════════════════════════════════════════════════════════

UPDATE nav09_action_map
   SET state = 'bound_page',
       updated_at = NOW()
 WHERE (state = '' OR state IS NULL)
   AND canonical_file = 'Risk/risk_actions.php'
   AND canonical_code IN ('GOV-RSK-ATTEST', 'RSK-APPETITE-SET', 'RSK-CTL-FAIL',
                          'RSK-FIELD-SYNC', 'RSK-INCIDENT-LOG', 'RSK-KRI-THRESHOLD',
                          'RSK-REPORT-EXPORT', 'RSK-REVIEW', 'RSK-TAXONOMY-DEFINE');
