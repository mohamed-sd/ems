-- SEC-013 — أبعاد الصلاحية الأربعة والأفعال الـ16 (E-04 نصًّا · تفويض إكمال الـ66)
-- ═══════════════════════════════════════════════════════════════════════════
-- «① الظهور (شاشة·تبويب·حقل) ② الفعل (ستة عشر فعلًا لا أربع رايات) ③ الاعتماد
--  (نوع المستند وسقفه) ④ النطاق (تسعة نطاقات لا ثنائية)» — تُبنى مرةً واحدةً
-- فوق طبقة القوالب (المصدر الجديد SEC-01) لا فوق legacy، والإنفاذ خلف علم
-- EMS_SEC013 (off حتى قلب EMS_PERM_SOURCE يوم 2026-08-19 — قرار المالك قائم).
-- DDL إضافي خالص: ثلاثة جداول جديدة وبذور قاموسية، صفر ALTER على قائم.

-- ── ① قاموس الأفعال الستة عشر (SEC-013-ب حرفيًّا) ────────────────────────
CREATE TABLE IF NOT EXISTS `sec_actions` (
  `action_code` VARCHAR(24) NOT NULL,
  `name_ar` VARCHAR(60) NOT NULL,
  `family` ENUM('visibility','mutation','workflow','output','admin') NOT NULL,
  `display_order` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`action_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='SEC-013 ②: الأفعال الستة عشر لا أربع رايات';

INSERT IGNORE INTO `sec_actions` (`action_code`, `name_ar`, `family`, `display_order`) VALUES
('screen_view',  'رؤية الشاشة',        'visibility', 1),
('tab_view',     'رؤية التبويب',        'visibility', 2),
('field_view',   'رؤية الحقل',          'visibility', 3),
('create',       'إنشاء',               'mutation',   4),
('edit',         'تعديل',               'mutation',   5),
('submit',       'إرسال',               'workflow',   6),
('return_fix',   'إعادة للتصحيح',       'workflow',   7),
('approve',      'اعتماد',              'workflow',   8),
('reject',       'رفض',                 'workflow',   9),
('cancel',       'إلغاء',               'workflow',  10),
('reverse',      'عكس',                 'workflow',  11),
('draft_delete', 'حذف مسوَّدة فقط',     'mutation',  12),
('export',       'تصدير',               'output',    13),
('print',        'طباعة',               'output',    14),
('grant_perm',   'منح صلاحية',          'admin',     15),
('cap_override', 'تجاوز سقف',           'admin',     16);

-- ── ② قاموس النطاقات التسعة (SEC-013-ب حرفيًّا) ──────────────────────────
CREATE TABLE IF NOT EXISTS `sec_scopes` (
  `scope_code` VARCHAR(24) NOT NULL,
  `name_ar` VARCHAR(60) NOT NULL,
  `narrowness` TINYINT UNSIGNED NOT NULL COMMENT '1 أوسع (شركة) … 9 أضيق (سجلاته هو)',
  PRIMARY KEY (`scope_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='SEC-013 ④: تسعة نطاقات لا ثنائية — الفعل نفسه يختلف بالنطاق';

INSERT IGNORE INTO `sec_scopes` (`scope_code`, `name_ar`, `narrowness`) VALUES
('company',    'شركة',          1),
('dept',       'إدارة',         2),
('section',    'قسم',           3),
('unit',       'وحدة',          4),
('project',    'مشروع',         5),
('site',       'موقع',          6),
('site_group', 'مجموعة مواقع',  7),
('shift',      'وردية',         8),
('own',        'سجلاته هو',     9);

-- ── ③ تنقيحات البنود: البعد الرباعي لكل بند قالب ─────────────────────────
CREATE TABLE IF NOT EXISTS `template_permission_dims` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tp_id` INT UNSIGNED NOT NULL COMMENT 'بند القالب template_permissions.tp_id',
  `action_code` VARCHAR(24) NOT NULL COMMENT 'من قاموس الستة عشر',
  `scope_code` VARCHAR(24) NOT NULL DEFAULT 'company' COMMENT 'من النطاقات التسعة',
  `field_rule` VARCHAR(190) DEFAULT NULL COMMENT 'ظهور الحقل/التبويب المسمى (NULL = الشاشة كلها)',
  `doc_type` VARCHAR(60) DEFAULT NULL COMMENT 'بعد الاعتماد: نوع المستند',
  `amount_cap` DECIMAL(18,2) DEFAULT NULL COMMENT 'بعد الاعتماد: السقف النقدي',
  `currency` VARCHAR(8) DEFAULT NULL,
  `effect` ENUM('grant','deny') NOT NULL DEFAULT 'grant',
  `derived_from` VARCHAR(40) DEFAULT NULL COMMENT 'baseline4 = اشتقاق الرايات الأربع · manual',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tp_dim` (`tp_id`, `action_code`, `scope_code`),
  KEY `ix_tpd_action` (`action_code`),
  CONSTRAINT `fk_tpd_action` FOREIGN KEY (`action_code`) REFERENCES `sec_actions` (`action_code`),
  CONSTRAINT `fk_tpd_scope` FOREIGN KEY (`scope_code`) REFERENCES `sec_scopes` (`scope_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='SEC-013: البعد الرباعي لكل بند قالب — يُشتق baseline ويُنقح يدويًّا';
