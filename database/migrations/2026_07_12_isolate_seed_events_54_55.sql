-- ═══════════════════════════════════════════════════════════════════════════
-- عزل ناعم قابل للعكس للحدثين التجريبيين 54/55 وقيدَيهما المشتقَّين 25/23
-- ───────────────────────────────────────────────────────────────────────────
-- القرار: مفوَّض من المالك (2026-07-12 «انت قرر باحترافية») ونُفِّذ بعد تحقيق
-- جنائي قرائي حاسم:
--   • الحدثان (FIN-EV-0009 رواتب 2,650,000 · FIN-EV-0010 وقود 950,000) جزء من
--     جلسة بذرٍ تجريبي واحدة (2026-07-06 11:29:16 — الدفتر كله بُذر في 4 ثوانٍ).
--   • مرجعاهما النصّيان لمصادر ثبت أنها غير موجودة: worker_settlement فارغ
--     تمامًا (PAYROLL-JUL-2026)، ولا أمر شراء يطابق مبلغًا أو وسمًا (FUEL-JUL-B1).
--   • صفر مراجع فرعية (cost/dues/allocations/payments/receivables/tax) وصفر
--     أثر على الناقل (لا deliveries/DLQ، idempotency_key=NULL — حارس §12 لا يمسّه).
--   • يُعزل كل حدثٍ مع قيده الآلي المشتق (54→قيد 25 · 55→قيد 23) معًا ذرّيًّا
--     منعًا لتباعد جذر/مشتق في ميزان المراجعة.
-- العكس: database/backups/M3_down_isolate_seed_events_54_55.sql
-- idempotent: شرط is_deleted=0 يمنع التطبيق المزدوج.
-- ═══════════════════════════════════════════════════════════════════════════

START TRANSACTION;

UPDATE fin_financial_events
   SET is_deleted = 1, deleted_at = NOW()
 WHERE id IN (54, 55)
   AND company_id = 4
   AND idempotency_key IS NULL
   AND is_deleted = 0;

UPDATE fin_journal_entries
   SET is_deleted = 1, deleted_at = NOW()
 WHERE id IN (23, 25)
   AND company_id = 4
   AND event_id IN (54, 55)
   AND is_deleted = 0;

COMMIT;
