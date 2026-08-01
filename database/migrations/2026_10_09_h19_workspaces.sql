-- ═══════════════════════════════════════════════════════════════════════════
-- H-19 · مساحاتُ العمل — الصفحةُ الأولى للنظام (WSP-01) — 2026-08-01
-- البطاقة: docs/specs/H-19_workspaces.md
-- المصدر: WSP-01 §2 (المساحاتُ الست) · §3 (جناحُ الفريق والطبقات) · §4 (عقدُ
--         التغذية) · §7 (البنية نصًّا) — «لا مساحةَ تملك جدولًا ولا تحسب
--         مؤشرًا: الحسابُ في خدمة مالكه والعرضُ هنا».
-- ═══════════════════════════════════════════════════════════════════════════

-- ① تخطيطاتُ المساحات — **بالنوع لا بالكيان** فلا تتفرق الأشكال
CREATE TABLE IF NOT EXISTS `workspace_layouts` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_type` ENUM('department','project','supplier','client','equipment','person') NOT NULL,
  `layout_json` TEXT NOT NULL COMMENT 'البطاقاتُ وترتيبُها لهذا النوع',
  `version`     INT NOT NULL DEFAULT 1,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wl` (`entity_type`,`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='WSP-01 §7 — التخطيطُ بالنوع لا بالكيان (قاموسٌ عالمي)';

-- ② قاموسُ البطاقات — واحدٌ يمنع تعريفَ مؤشرين بمعنًى واحد
CREATE TABLE IF NOT EXISTS `workspace_cards` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`            VARCHAR(64) NOT NULL,
  `title_ar`        VARCHAR(190) NOT NULL,
  `owner_doc`       VARCHAR(32) NOT NULL,
  `source_service`  VARCHAR(120) NOT NULL COMMENT 'الخدمةُ المالكةُ للحساب — لا تحسب اللوحة',
  `permission_code` VARCHAR(64) NULL,
  `counter_source`  VARCHAR(120) NULL,
  `cache_ttl`       INT NOT NULL DEFAULT 0 COMMENT '0 = حيٌّ بلا كاش (عدّاداتُ الانتظار)',
  `active`          TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wc_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='WSP-01 §7 — قاموسُ بطاقات المساحات بمالكيها';

-- ③ تفضيلاتُ العرض — للمستخدم «لا بياناتٍ»
CREATE TABLE IF NOT EXISTS `workspace_prefs` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`        INT UNSIGNED NOT NULL,
  `account_id`        INT NOT NULL,
  `entity_type`       VARCHAR(24) NOT NULL,
  `pinned_cards_json` TEXT NULL,
  `default_period`    VARCHAR(24) NOT NULL DEFAULT 'today',
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wp` (`account_id`,`entity_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ④ سجلُّ التنقل — Insert-only للقياس والتدقيق
CREATE TABLE IF NOT EXISTS `workspace_navigation_log` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `account_id` INT NOT NULL,
  `from_layer` VARCHAR(64) NULL,
  `to_layer`   VARCHAR(64) NOT NULL,
  `entity_ref` VARCHAR(64) NULL,
  `result`     ENUM('ok','denied') NOT NULL DEFAULT 'ok',
  `at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_wnl_account` (`account_id`,`at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='WSP-01 §7 — WorkspaceOpened · LayerSwitched · 403 مسجَّلة';

-- ── بذرُ قاموس البطاقات من مؤشرات وثائق الإدارات القائمة (§8-الترحيل①) ──────
INSERT INTO `workspace_cards` (`code`,`title_ar`,`owner_doc`,`source_service`,`permission_code`,`cache_ttl`)
SELECT * FROM (
    SELECT 'prod.units.period'   c,'الإنتاجُ والوحدات'          t,'UX-03' o,'unit_entries'              s, NULL p, 300 l UNION ALL
    SELECT 'stops.by_owner',       'التوقفاتُ بمسؤوليها',          'UX-03',  'ts_stop_lines',               NULL,   300   UNION ALL
    SELECT 'decisions.pending',    'ما يحتاج قرارًا',              'UX-02',  'fin_requests',                NULL,   0     UNION ALL
    SELECT 'tickets.open',         'البلاغاتُ المفتوحة',           'UX-07',  'tickets',                     NULL,   0     UNION ALL
    SELECT 'claims.period',        'المستخلصاتُ والذمم',           'ENT-03', 'claims',                      NULL,   300   UNION ALL
    SELECT 'supplier.capacity',    'الحصةُ واستهلاكُها',           'ENT-02', 'container_allocations',       NULL,   300   UNION ALL
    SELECT 'equipment.health',     'جاهزيةُ المعدات',              'UX-10',  'fleet_equipment',             NULL,   300   UNION ALL
    SELECT 'contract.commitment',  'الالتزامُ بالعقد',             'CON-02', 'contract_obligations',        NULL,   300   UNION ALL
    SELECT 'events.pulse',         'نبضُ الأحداث',                 'FES-01', 'ems_business_events',         NULL,   0     UNION ALL
    SELECT 'person.achievement',   'إنجازُ الشخص',                 'USR-01', 'AchievementService',          NULL,   300
) seed
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `workspace_cards`) wc WHERE wc.`code` = seed.c);

-- ── تخطيطُ كل نوعٍ (نسخة 1) — البنيةُ واحدةٌ والمحتوى يختلف ────────────────
INSERT INTO `workspace_layouts` (`entity_type`,`layout_json`,`version`)
SELECT * FROM (
    SELECT 'department' e, '["decisions.pending","tickets.open","events.pulse"]' j, 1 v UNION ALL
    SELECT 'project',      '["prod.units.period","stops.by_owner","decisions.pending","tickets.open","events.pulse"]', 1 UNION ALL
    SELECT 'supplier',     '["supplier.capacity","prod.units.period","claims.period","tickets.open"]', 1 UNION ALL
    SELECT 'client',       '["contract.commitment","prod.units.period","claims.period","tickets.open"]', 1 UNION ALL
    SELECT 'equipment',    '["equipment.health","tickets.open","events.pulse"]', 1 UNION ALL
    SELECT 'person',       '["person.achievement","decisions.pending","tickets.open"]', 1
) seed
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `workspace_layouts`) wl
                    WHERE wl.`entity_type` = seed.e AND wl.`version` = seed.v);

-- ── الشاشة 191 — المساحةُ السياقية (لكل الأدوار · الحارسُ في الخدمة) ────────
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 191, 'مساحة العمل', 'Portal/workspace.php', 15, 0, 1, 'fa fa-table-columns', 0
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Portal/workspace.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.id, 191, 1, 0, 0, 0 FROM `roles` r
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
                    WHERE rp.`role_id` = r.id AND rp.`module_id` = 191);
