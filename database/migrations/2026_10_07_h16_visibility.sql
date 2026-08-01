-- ═══════════════════════════════════════════════════════════════════════════
-- H-16 · منظومةُ الظهور — القاموسُ والمفاتيحُ والسجل (ADM-01) — 2026-08-01
-- البطاقة: docs/specs/H-16_visibility_system.md
-- المصدر: ADM-01 §2: «كلُّ ما يمكن إظهارُه له كودٌ واحدٌ في قاموسٍ واحد —
--         وما ليس في القاموس لا يُصيَّر أصلًا» · «الحسابُ يغلب الفئة …
--         وما لا سياسةَ له مغلقٌ افتراضيًّا» · §4 البنية نصًّا.
-- ═══════════════════════════════════════════════════════════════════════════

-- ① القاموس — عالميٌّ (أكوادٌ لا بيانات) يملكه مديرُ البوابة
CREATE TABLE IF NOT EXISTS `portal_elements` (
  `element_code` VARCHAR(64) NOT NULL,
  `title_ar`     VARCHAR(190) NOT NULL,
  `owner_doc`    VARCHAR(32) NOT NULL COMMENT 'الوثيقةُ المالكة (USR-01 · WSP-01 …)',
  `sensitivity`  ENUM('normal','sensitive') NOT NULL DEFAULT 'normal',
  `default_mode` ENUM('open','closed') NOT NULL DEFAULT 'closed',
  `active`       TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`element_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ADM-01 §2 — قاموسُ عناصر البوابة: ما ليس فيه لا يُصيَّر أصلًا';

-- ② المفاتيح — بنطاقاتها الستة وشركتها؛ scope_id نصيٌّ يسع الفئةَ والرقم
CREATE TABLE IF NOT EXISTS `visibility_keys` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`   INT UNSIGNED NOT NULL,
  `element_code` VARCHAR(64) NOT NULL,
  `scope_type`   ENUM('account','capacity_type','department','project','supplier','client') NOT NULL,
  `scope_id`     VARCHAR(64) NOT NULL COMMENT 'معرّفُ النطاق — رقمٌ أو كودُ فئة',
  `mode`         ENUM('open','closed','inherit') NOT NULL,
  `reason`       VARCHAR(255) NULL COMMENT 'إلزاميٌّ لغير inherit (CHECK)',
  `granted_by`   INT NOT NULL,
  `granted_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at`   DATETIME NULL COMMENT 'إلزاميٌّ لفتح الحساس (حارسُ الخدمة)',
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vk_key` (`company_id`,`element_code`,`scope_type`,`scope_id`),
  KEY `ix_vk_element` (`element_code`),
  KEY `ix_vk_scope` (`scope_type`,`scope_id`),
  CONSTRAINT `fk_vk_element` FOREIGN KEY (`element_code`)
      REFERENCES `portal_elements` (`element_code`),
  -- «سببٌ إلزاميٌّ لغير الموروث» — لا تغييرَ صامتًا على خصوصية أحد
  CONSTRAINT `ck_vk_reason` CHECK (`mode` = 'inherit' OR `reason` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ADM-01 §2 — مفاتيحُ الظهور بنطاقاتها الستة وأولويتها المحسومة';

-- ③ سجلُّ التدقيق — Insert-only: لا يُعدَّل ولا يُحذف
CREATE TABLE IF NOT EXISTS `visibility_audit_log` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT UNSIGNED NOT NULL,
  `element_code`   VARCHAR(64) NOT NULL,
  `scope_type`     VARCHAR(24) NOT NULL,
  `scope_id`       VARCHAR(64) NOT NULL,
  `from_mode`      VARCHAR(12) NULL,
  `to_mode`        VARCHAR(24) NOT NULL COMMENT 'open·closed·inherit·grant_expired·denied_self',
  `actor`          INT NOT NULL,
  `reason`         VARCHAR(255) NULL,
  `expires_at`     DATETIME NULL,
  `affected_count` INT NOT NULL DEFAULT 0,
  `at`             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_val_element` (`element_code`),
  KEY `ix_val_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ADM-01 §2 — «لا تغييرَ صامت»: كلُّ فتحٍ وإغلاقٍ بفاعله وسببه ومدته';

-- ───────────────────────────────────────────────────────────────────────────
-- بذرُ القاموس من عناصر USR-01 §8-② — **والحساسُ مغلقٌ افتراضًا** (سياسةٌ
-- محافظة §5): لا يُفتح راتبٌ ولا تقييمٌ إلا بقرارٍ موثَّق.
-- ───────────────────────────────────────────────────────────────────────────

INSERT INTO `portal_elements` (`element_code`,`title_ar`,`owner_doc`,`sensitivity`,`default_mode`)
SELECT * FROM (
    SELECT 'card.contract'    c, 'بطاقةُ العقد'                     t, 'USR-01' o, 'normal'    s, 'open'   d UNION ALL
    SELECT 'card.attendance',    'الحضورُ والإجازات',                  'USR-01',   'normal',      'open'     UNION ALL
    SELECT 'card.units',         'وحداتُ العمل',                       'USR-01',   'normal',      'open'     UNION ALL
    SELECT 'card.requests',      'الطلبات',                            'USR-01',   'normal',      'open'     UNION ALL
    SELECT 'card.approvals',     'الاعتمادات',                         'USR-01',   'normal',      'open'     UNION ALL
    SELECT 'card.custody',       'العهدُ المسلَّمة',                   'USR-01',   'normal',      'open'     UNION ALL
    SELECT 'card.achievement',   'مؤشراتُ الإنجاز',                    'USR-01',   'normal',      'open'     UNION ALL
    SELECT 'card.timeline',      'الخطُّ الزمني المهني',               'USR-01',   'normal',      'open'     UNION ALL
    SELECT 'card.tickets',       'البلاغات',                           'USR-01',   'normal',      'open'     UNION ALL
    SELECT 'card.payroll',       'الراتبُ وكشفُه',                     'USR-01',   'sensitive',   'closed'   UNION ALL
    SELECT 'card.incentives',    'الحوافز',                            'USR-01',   'sensitive',   'closed'   UNION ALL
    SELECT 'card.advances',      'السلفُ وأقساطُها',                   'USR-01',   'sensitive',   'closed'   UNION ALL
    SELECT 'card.penalties',     'الجزاءات',                           'USR-01',   'sensitive',   'closed'   UNION ALL
    SELECT 'field.evaluation',   'التقييمُ ونتيجتُه',                  'USR-01',   'sensitive',   'closed'   UNION ALL
    SELECT 'card.documents',     'المستنداتُ الشخصية',                 'USR-01',   'sensitive',   'closed'   UNION ALL
    SELECT 'card.activity',      'سجلُّ النشاط',                       'USR-01',   'normal',      'open'
) seed
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `portal_elements`) pe
                    WHERE pe.`element_code` = seed.c);

-- ───────────────────────────────────────────────────────────────────────────
-- الشاشات 183–186 — فصلُ الواجبات: المفاتيحُ لشؤون الموظفين (4) والقاموسُ
-- لمدير الصلاحيات (15) والمحاكاةُ والسجلُّ لكليهما قراءةً
-- ───────────────────────────────────────────────────────────────────────────

INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT * FROM (
    SELECT 183 i, 'مفاتيح الظهور'      n, 'Portal/visibility_keys.php'      c, 4  r, 0 l, 1 q, 'fa fa-key'            ic, 0 d UNION ALL
    SELECT 184,   'مكوّنات البوابة',      'Portal/portal_elements.php',        15,   0,   1,   'fa fa-puzzle-piece',      0   UNION ALL
    SELECT 185,   'من يرى ماذا (محاكاة)', 'Portal/visibility_simulator.php',   4,    0,   1,   'fa fa-user-secret',       0   UNION ALL
    SELECT 186,   'سجل تدقيق الظهور',     'Portal/visibility_audit.php',       4,    0,   1,   'fa fa-clipboard-list',    0
) m
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `modules`) x WHERE x.`code` = m.c);

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT p.rid, p.mid, 1, 0, p.ed, 0
  FROM (
    SELECT 4 rid, 183 mid, 1 ed UNION ALL SELECT 15, 183, 0 UNION ALL
    SELECT 15, 184, 1 UNION ALL SELECT 4, 184, 0 UNION ALL
    SELECT 4, 185, 0 UNION ALL SELECT 15, 185, 0 UNION ALL
    SELECT 4, 186, 0 UNION ALL SELECT 15, 186, 0
  ) p
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
                    WHERE rp.`role_id` = p.rid AND rp.`module_id` = p.mid);
