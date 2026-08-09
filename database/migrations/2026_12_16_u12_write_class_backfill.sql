-- v19 · الفاحصُ المستقلُّ (2026-08-08): إكمالُ تصنيفِ الكتابةِ للأفعالِ العشرين
-- التي أضافتها update0012 (التحليلُ الماليُّ ومساحاتُ الإداراتِ العابرة).
-- ═══════════════════════════════════════════════════════════════════════════
-- الجذر: البوابةُ العابرةُ «07 · تصنيفُ الكتابةِ مكتملٌ للرموزِ كلِّها» رسبت
-- لأن الجديدَ سُجِّل في سجلِّ وحدتِه (m10/m14 خضراوان) ولم يُسجَّل في السجلِّ
-- العابر. والتصنيفُ هنا **مشتقٌّ من عقدِ كلِّ فعلٍ نفسِه** (`writes_text`)
-- لا بالتخمين — بقاعدةِ الورقة 21:
--   Read Only        لا يكتب شيئًا
--   Domain Write     يكتب جداولَ المجال
--   Governance Write يكتب جداولَ الحوكمة (access_reviews)
-- idempotent: يشترط الفراغَ فلا يمسُّ مصنَّفًا.

-- ① قراءةٌ خالصة — عقدُها بلا «يكتب» (7)
UPDATE `nav09_action_map` SET `write_class` = 'read_only'
 WHERE (`write_class` IS NULL OR `write_class` = '')
   AND `canonical_code` IN ('fin.contract.margin','fin.ratio.drill','fin.unit.economics',
                            'gov.fin.view','gov.gov.view','risk.fin.view','risk.gov.view');

-- ② كتابةُ حوكمة — تكتبان `access_reviews` (2)
UPDATE `nav09_action_map` SET `write_class` = 'governance_write'
 WHERE (`write_class` IS NULL OR `write_class` = '')
   AND `canonical_code` IN ('gov.fin.attest','gov.gov.attest');

-- ③ كتابةُ مجال — تكتب جداولَ المجال (11)
UPDATE `nav09_action_map` SET `write_class` = 'domain_write'
 WHERE (`write_class` IS NULL OR `write_class` = '')
   AND `canonical_code` IN ('fin.cashflow.generate','fin.equity.generate','fin.posting.matrix',
                            'fin.project.pl','fin.ratio.compute','fin.ratio.target','fin.signal.raise',
                            'risk.fin.evidence','risk.fin.raise','risk.gov.evidence','risk.gov.raise');
