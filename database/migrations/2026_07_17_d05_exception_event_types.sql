-- D05 §8.3 — أنواع سجلّ الاستثناء الثلاثة تُلحق بذيل ENUM سجل الطلب الإلحاقي
-- (إلحاقٌ في الذيل حصرًا — نمط K3 الآمن: القيم القائمة لا تتزحزح)
--   exception_requested: طلب تنفيذٍ طارئ من صاحبه/مدير إدارته
--   exception_denied:    رفض المدير المالي للطلب بسببٍ مسجَّل
--   exception_overdue:   خرق مهلة الاستكمال الرجعي (72 ساعة) — يكتبه cron

ALTER TABLE `fin_request_events`
  MODIFY COLUMN `event_type` ENUM(
    'create','attach','submit','dept_review','dept_approve','acct_review','verify',
    'fin_approve','reject','return','resubmit','post','pay','collect','settle',
    'close','archive','withdraw','cancel','suspend','resume','expire','merge',
    'duplicate_check','escalate','exception','publish','edit','note','system',
    'exception_requested','exception_denied','exception_overdue'
  ) NOT NULL;
