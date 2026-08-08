-- update0012 · م1 — أعمدةُ عمقِ الربطِ الناقصةُ ⑤⑩⑫ وبذرُ أفعالِ الوحدتين العشرة
-- ═══════════════════════════════════════════════════════════════════════════
-- المرجع: UXR-01 v1 §٤-٩ (UXR-0174: «ستةٌ من أعمدةِ عمقِ الربطِ الاثني عشرَ بلا
-- تخزينٍ في القاعدة — وبوابةُ الأفعالِ G4 لا تُغلق قبل تخزينِها وحكمِها لكل فعل»)
-- والورقة 33 في INJAZ-MASTER-MAP-6: ⑤ Guard_verified و⑩ Idempotency_verified
-- و⑫ Uat_verified «✘ لا عمودَ في القاعدة».
--
-- ما تنفّذه بنيويًّا:
--   ① ثلاثةُ أعمدةِ حكمٍ على nav09_action_map — القيمُ أربع:
--      pending (لم يُحكم) · yes (مثبَتٌ بدليل) · no (فُحص وفشل) · n_a (لا ينطبق
--      بطبيعته — كالقارئ بلا مفتاح تكرار). ولكل حكمٍ عمودُ دليلٍ (evidence).
--   ② بذرُ أفعالِ M-10/M-14 النطاقيةِ العشرة الغائبةِ من الخريطة:
--      risk.fin.view/raise/evidence · gov.fin.view/attest ·
--      risk.gov.view/raise/evidence · gov.gov.view/attest — بعقودها السداسية
--      نصًّا من الوثيقتين (§7-1 في كلٍّ).
--   ③ أحكامٌ أوليةٌ صادقة: قارئٌ بلا كتابة ⇒ idempotency=n_a · uat=pending
--      للجميع («لم يبدأ» لكل الأفعال — الورقة 33) · guard=pending حتى يشهد
--      الفحصُ الحي (لا يُدَّعى ما لم يُقَس — UXR-0131).
--
-- النمط الحارس: information_schema + PREPARE (MySQL 8.4 — لا IF NOT EXISTS
-- للأعمدة) · INSERT IGNORE للبذر — idempotent يعاد تشغيلها بلا أثر مزدوج.

-- ═══ ① أعمدةُ الحكم الثلاثة وأدلتها ═══════════════════════════════════════
SET @c = (SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = 'nav09_action_map' AND column_name = 'guard_verified');
SET @ddl = IF(@c = 0,
  'ALTER TABLE nav09_action_map
     ADD COLUMN guard_verified ENUM(''pending'',''yes'',''no'',''n_a'') NOT NULL DEFAULT ''pending''
         COMMENT ''⑤ أيمنعه حارسٌ في الخادم؟ — شاهدُه محاولةٌ غيرُ مخوَّلةٍ تُرفض برمز'' AFTER state,
     ADD COLUMN guard_evidence VARCHAR(190) NOT NULL DEFAULT ''''
         COMMENT ''دليلُ حكمِ الحارس — رمزُ الرفضِ أو مسارُ الفحص'' AFTER guard_verified,
     ADD COLUMN idempotency_verified ENUM(''pending'',''yes'',''no'',''n_a'') NOT NULL DEFAULT ''pending''
         COMMENT ''⑩ أتُرفض إعادةُ النداء؟ — الإعادةُ ترجع مرجعَ الأول'' AFTER guard_evidence,
     ADD COLUMN idempotency_evidence VARCHAR(190) NOT NULL DEFAULT '''' AFTER idempotency_verified,
     ADD COLUMN uat_verified ENUM(''pending'',''yes'',''no'',''n_a'') NOT NULL DEFAULT ''pending''
         COMMENT ''⑫ أاجتاز رحلةً حيةً بمستخدمٍ حقيقي؟ — محضرُ UAT موقَّع'' AFTER idempotency_evidence,
     ADD COLUMN uat_evidence VARCHAR(190) NOT NULL DEFAULT '''' AFTER uat_verified',
  'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ═══ ② بذرُ الأفعالِ النطاقيةِ العشرة (M-10 §7-1 · M-14 §7-1) ═══════════════
-- الأعمدة: canonical_code · label_ar · screen_title · canonical_file · actor_ar
--          · writes_text · event_name · consumers_text · effect_text
--          · reverse_text · live_code · state
INSERT IGNORE INTO nav09_action_map
  (canonical_code, label_ar, screen_title, canonical_file, actor_ar, writes_text,
   event_name, consumers_text, effect_text, reverse_text, live_code, state, updated_at)
VALUES
  ('risk.fin.view', 'عرضُ المخاطر المالية', 'المخاطر المالية', 'risk_dept_fin.php',
   'مديرُ الإدارةِ وموظفوها المخوَّلون', '—', '', '—',
   'عرضُ مخاطرِ المالية بنطاقِ الإدارة — قراءةٌ لا كتابة · والتعديلُ يمرُّ بإدارة المخاطر',
   'لا يُعكس — قارئ', 'Risk/risk_dept_fin.php', 'bound_page', NOW()),
  ('risk.fin.raise', 'الإبلاغُ عن خطرٍ في المخاطر المالية', 'المخاطر المالية', 'risk_dept_fin.php',
   'أيُّ موظفٍ مخوَّلٍ في الإدارة', 'risk_signals',
   'RiskSignalRaised', 'إدارةُ المخاطر',
   'إشارةٌ تنتظر الفرزَ — ولا تُنشئ خطرًا مباشرةً · فليس كلُّ ما يُبلَّغ عنه خطرًا',
   'سحبُ الإشارةِ قبل الفرز', 'Risk/risk_actions.php::signal_create', 'bound_page', NOW()),
  ('risk.fin.evidence', 'تسجيلُ دليلِ تنفيذِ ضابطٍ في المخاطر المالية', 'المخاطر المالية', 'risk_dept_fin.php',
   'مالكُ الضابطِ في الإدارة', 'risk_controls',
   'ControlEvidenceLogged', 'إدارةُ المخاطر',
   'دليلُ التنفيذِ مسجَّلٌ — والفعاليةُ يحكمها متحقِّقٌ مستقلٌّ لا مالكُ الضابط',
   'تصحيحُ الدليلِ بمرجعٍ لا حذفًا', 'Risk/risk_actions.php::control_evidence', 'bound_page', NOW()),
  ('gov.fin.view', 'عرضُ حوكمة المالية والخزينة', 'حوكمة المالية والخزينة', 'gov_dept_fin.php',
   'مديرُ الإدارة', '—', '', '—',
   'عرضُ حوكمةِ الإدارةِ بنطاقها — قراءةٌ لا كتابة',
   'لا يُعكس — قارئ', 'Finance/gov_dept_fin.php', 'bound_page', NOW()),
  ('gov.fin.attest', 'تصديقُ مراجعةِ وصولٍ في المالية والخزينة', 'حوكمة المالية والخزينة', 'gov_dept_fin.php',
   'مديرُ الإدارة', 'access_reviews',
   'AccessReviewAttested', 'الحوكمةُ والالتزام',
   'المديرُ يصدّق على قائمةِ فريقه — والتصديقُ لا يمنح صلاحيةً بل يشهد بصحتها',
   'سحبُ التصديقِ بسبب', 'Finance/fin_m10_actions.php::gov_attest', 'bound_page', NOW()),
  ('risk.gov.view', 'عرضُ مخاطر الحوكمة والالتزام', 'مخاطر الحوكمة والالتزام', 'risk_dept_gov.php',
   'مديرُ الإدارةِ وموظفوها المخوَّلون', '—', '', '—',
   'عرضُ مخاطرِ الحوكمة والالتزام بنطاقِ الإدارة — قراءةٌ لا كتابة · والتعديلُ يمرُّ بإدارة المخاطر',
   'لا يُعكس — قارئ', 'Risk/risk_dept_gov.php', 'bound_page', NOW()),
  ('risk.gov.raise', 'الإبلاغُ عن خطرٍ في مخاطر الحوكمة والالتزام', 'مخاطر الحوكمة والالتزام', 'risk_dept_gov.php',
   'أيُّ موظفٍ مخوَّلٍ في الإدارة', 'risk_signals',
   'RiskSignalRaised', 'إدارةُ المخاطر',
   'إشارةٌ تنتظر الفرزَ — ولا تُنشئ خطرًا مباشرةً · فليس كلُّ ما يُبلَّغ عنه خطرًا',
   'سحبُ الإشارةِ قبل الفرز', 'Risk/risk_actions.php::signal_create', 'bound_page', NOW()),
  ('risk.gov.evidence', 'تسجيلُ دليلِ تنفيذِ ضابطٍ في مخاطر الحوكمة والالتزام', 'مخاطر الحوكمة والالتزام', 'risk_dept_gov.php',
   'مالكُ الضابطِ في الإدارة', 'risk_controls',
   'ControlEvidenceLogged', 'إدارةُ المخاطر',
   'دليلُ التنفيذِ مسجَّلٌ — والفعاليةُ يحكمها متحقِّقٌ مستقلٌّ لا مالكُ الضابط',
   'تصحيحُ الدليلِ بمرجعٍ لا حذفًا', 'Risk/risk_actions.php::control_evidence', 'bound_page', NOW()),
  ('gov.gov.view', 'عرضُ حوكمة الحوكمة والالتزام', 'حوكمة الحوكمة والالتزام', 'gov_dept_gov.php',
   'مديرُ الإدارة', '—', '', '—',
   'عرضُ حوكمةِ الإدارةِ بنطاقها — قراءةٌ لا كتابة',
   'لا يُعكس — قارئ', 'Governance/gov_dept_gov.php', 'bound_page', NOW()),
  ('gov.gov.attest', 'تصديقُ مراجعةِ وصولٍ في الحوكمة والالتزام', 'حوكمة الحوكمة والالتزام', 'gov_dept_gov.php',
   'مديرُ الإدارة', 'access_reviews',
   'AccessReviewAttested', 'الحوكمةُ والالتزام',
   'المديرُ يصدّق على قائمةِ فريقه — والتصديقُ لا يمنح صلاحيةً بل يشهد بصحتها',
   'سحبُ التصديقِ بسبب', 'Governance/gov_m14_actions.php::gov_attest', 'bound_page', NOW());

-- ═══ ③ الأحكامُ الأوليةُ الصادقة ═══════════════════════════════════════════
-- القارئ (reverse_text يعلن قارئًا): لا مفتاحَ تكرارٍ بطبيعته ⇒ n_a — لا نقصًا.
UPDATE nav09_action_map
   SET idempotency_verified = 'n_a',
       idempotency_evidence = 'قارئٌ لا يكتب — لا مفتاحَ بطبيعته (M-10 §8-5)'
 WHERE idempotency_verified = 'pending'
   AND (reverse_text LIKE '%قارئ%' OR writes_text IN ('—', '') OR writes_text IS NULL);

-- أفعالُ M-16 الحية المثبتة في جولة update0011 (شاهد G4: محاولاتٌ غيرُ مخوَّلةٍ
-- رُفضت برمزٍ حيًّا · التكرارُ صُدَّ وعرض مطابقَه — docs/update0011/G_BLOCK_METRICS_ar.md)
UPDATE nav09_action_map
   SET guard_verified = 'yes',
       guard_evidence = 'G4 حي: رفضٌ برمز RSK-403 لغير المخوَّل (update0011)'
 WHERE canonical_code IN ('risk.raise', 'risk.assess', 'risk.accept', 'risk.close')
   AND guard_verified = 'pending';
UPDATE nav09_action_map
   SET idempotency_verified = 'yes',
       idempotency_evidence = 'مفتاحٌ مركبٌ حي — التكرارُ يعرض مطابقَه (update0011)'
 WHERE canonical_code IN ('risk.raise', 'risk.assess')
   AND idempotency_verified = 'pending';
