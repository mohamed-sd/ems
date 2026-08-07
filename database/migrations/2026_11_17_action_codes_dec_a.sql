-- DEC-A (2026-08-06 · تفويض المالك جلسة update0009): نمطُ domain.resource.action
-- ═══════════════════════════════════════════════════════════════════════════
-- يغلق DEF-001 (رمزان بمعنيين: model.define · req.raise — أربعةُ صفوفٍ في ورقة 07
-- والقاموسُ الحيُّ كان قد أسقط المعنى الثاني في الاستيراد فيُستعاد هنا نصًّا).
-- القياس قبل التنفيذ: صفرُ ظهورٍ للرمزين القديمين في الكود الحي (مصفوفة الاستعمال
-- docs/update0009/ACTION_USAGE_MATRIX.csv) — فالفصل بلا خطر كسر.
-- الرمزُ القديم يبقى alias دورةَ إصدارٍ كاملةً في nav09_action_alias (خطة الورقة 08).
-- idempotent: إعادة التشغيل لا تكرر ولا تكسر.

-- ① بنية alias — القديمُ يُحلُّ إلى الجديد بسياق شاشته (القديمُ الغامضُ يحمل صفين)
CREATE TABLE IF NOT EXISTS nav09_action_alias (
  old_code VARCHAR(60) NOT NULL,
  new_code VARCHAR(60) NOT NULL,
  canonical_file VARCHAR(80) NULL COMMENT 'محدِّد السياق حين يكون القديم غامضًا',
  note VARCHAR(255) NULL,
  status ENUM('active','planned','retired') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (old_code, new_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ② توسيع حالات القاموس لحملة الربط (فحص ⑤ يقبل «معالجًا أو حالةً معلنة»)
ALTER TABLE nav09_action_map
  MODIFY state ENUM('alias','pending','bound_page','declared_unbuilt') NOT NULL DEFAULT 'pending';

-- ③ فصل model.define — الصفُّ الحيُّ يحمل معنى المبيعات فيُعاد ترميزُه
UPDATE nav09_action_map SET canonical_code = 'sales.model.define'
 WHERE canonical_code = 'model.define';

INSERT INTO nav09_action_map
  (canonical_code, label_ar, screen_title, canonical_file, actor_ar, writes_text,
   event_name, consumers_text, effect_text, reverse_text, live_code, state)
VALUES
  ('fleet.model.define', 'تعريفُ موديلٍ ومواصفاتِه', 'موديلات المعدات ومواصفاتها',
   'equip_models.php', 'إدارة الأسطول', 'equipment_models · service_specs',
   'ModelDefined', 'الصيانة · المشتريات',
   'كلُّ معدةٍ من الموديل ترث مواصفاتِه ودورياتِ غياره وقطعَه القياسية',
   'تعديلٌ بأثرٍ مستقبليٍّ لا رجعي', NULL, 'pending')
ON DUPLICATE KEY UPDATE label_ar = VALUES(label_ar);

-- ④ فصل req.raise — الصفُّ الحيُّ يحمل معنى مساحة عملي فيُعاد ترميزُه
UPDATE nav09_action_map SET canonical_code = 'workspace.request.submit'
 WHERE canonical_code = 'req.raise';

INSERT INTO nav09_action_map
  (canonical_code, label_ar, screen_title, canonical_file, actor_ar, writes_text,
   event_name, consumers_text, effect_text, reverse_text, live_code, state)
VALUES
  ('transport.request.submit', 'رفعُ طلب ترحيل', 'طلبات الترحيل',
   'transfer_req.php', 'الإدارةُ الطالبة', 'transfer_requests',
   'TransferRequested', 'النقل',
   'الطلبُ يصل النقلَ بمصدره ومبرّره — ولا يُنفَّذ قبل أمرٍ معتمد',
   'سحبُ الطلب قبل الأمر', NULL, 'pending')
ON DUPLICATE KEY UPDATE label_ar = VALUES(label_ar);

-- ⑤ خريطة alias — القديمُ يُحلُّ بسياقه دورةَ إصدارٍ كاملةً ثم يُقاعَد
INSERT IGNORE INTO nav09_action_alias (old_code, new_code, canonical_file, note, status) VALUES
  ('model.define', 'sales.model.define',       'business_models.php', 'MEANING_COLLISION — معنى المبيعات (ورقة 14)', 'active'),
  ('model.define', 'fleet.model.define',       'equip_models.php',    'MEANING_COLLISION — معنى الأسطول (ورقة 14)',  'active'),
  ('req.raise',    'workspace.request.submit', 'my_requests.php',     'MEANING_COLLISION — معنى مساحة عملي (ورقة 14)', 'active'),
  ('req.raise',    'transport.request.submit', 'transfer_req.php',    'MEANING_COLLISION — معنى النقل (ورقة 14)',      'active'),
  ('rpt.export',   'finance.report.export',    NULL,                  'ALIAS محلول — معنًى واحدٌ في شاشاتٍ عدة (ورقة 14) · يُرحَّل في دفعات DEC-A اللاحقة', 'planned');
