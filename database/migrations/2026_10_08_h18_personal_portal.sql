-- ═══════════════════════════════════════════════════════════════════════════
-- H-18 · البوابةُ الشخصية — اللقطاتُ والتقييمُ والشهادةُ وسجلُّ النشاط — 2026-08-01
-- البطاقة: docs/specs/H-18_personal_portal.md
-- المصدر: USR-01 §6 (قياسُ الإنجاز بين تاريخين) · §7 (التقييمُ الثنائي
--         والشهادة) · §9.1 (البنية نصًّا) — «البوابةُ لا تملك بيانًا واحدًا:
--         كلُّ رقمٍ يُقرأ من مالكه؛ وما تملكه: الصفاتُ والمفاتيحُ ولقطاتُ
--         الإنجاز والتقييمُ وشهادتُه وسجلُّ نشاطها».
-- ═══════════════════════════════════════════════════════════════════════════

-- ① لقطاتُ الإنجاز — المؤشراتُ السبعةُ ببصمة مصدرها لفترةٍ حرة
CREATE TABLE IF NOT EXISTS `achievement_snapshots` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL,
  `person_id`     INT NULL,
  `capacity_id`   INT UNSIGNED NOT NULL,
  `period_from`   DATE NOT NULL,
  `period_to`     DATE NOT NULL,
  `metrics_json`  TEXT NOT NULL COMMENT 'المؤشراتُ السبعةُ بأرقامها — و«لا ينطبق» يُعلَن لا صفرًا',
  `computed_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `source_fingerprint` VARCHAR(64) NOT NULL COMMENT 'بصمةُ المصادر لحظةَ الحساب',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_snap` (`capacity_id`,`period_from`,`period_to`),
  KEY `ix_snap_person` (`person_id`),
  CONSTRAINT `fk_snap_capacity` FOREIGN KEY (`capacity_id`) REFERENCES `user_capacities` (`id`),
  CONSTRAINT `ck_snap_window` CHECK (`period_to` >= `period_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='USR-01 §6/§9.1 — قياسُ الإنجاز بين تاريخين لكل صفة';

-- ② التقييمُ الثنائي — انتقالاتُ §7 بفحص النسخة
CREATE TABLE IF NOT EXISTS `evaluations` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`       INT UNSIGNED NOT NULL,
  `capacity_id`      INT UNSIGNED NOT NULL,
  `period_from`      DATE NOT NULL,
  `period_to`        DATE NOT NULL,
  `self_scores_json` TEXT NULL,
  `self_closed_at`   DATETIME NULL,
  `mgr_scores_json`  TEXT NULL,
  `mgr_by`           INT NULL,
  `mgr_comment`      VARCHAR(500) NULL COMMENT 'إلزاميٌّ عند فارقٍ ≥ درجتين',
  `discussion_notes` TEXT NULL,
  `final_score`      DECIMAL(5,2) NULL,
  `state`            ENUM('SelfDraft','SelfClosed','MgrDraft','Discussed','Approved') NOT NULL DEFAULT 'SelfDraft',
  `version`          INT NOT NULL DEFAULT 1,
  `approved_by`      INT NULL,
  `approved_at`      DATETIME NULL,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_eval` (`capacity_id`,`period_from`,`period_to`),
  CONSTRAINT `fk_eval_capacity` FOREIGN KEY (`capacity_id`) REFERENCES `user_capacities` (`id`),
  -- الاعتمادُ يلزمه معتمِدٌ ووقتٌ ودرجةٌ نهائية — لا اعتمادَ فارغًا
  CONSTRAINT `ck_eval_approved` CHECK (`state` <> 'Approved'
      OR (`approved_by` IS NOT NULL AND `approved_at` IS NOT NULL AND `final_score` IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='USR-01 §7 — التقييمُ الثنائي: ذاتيٌّ ثم مديرٌ ثم مناقشةٌ فاعتماد';

-- ③ شهادةُ الإنجاز — برقمٍ تسلسليٍّ ورمزِ تحققٍ ولا تُصدَر لغير المعتمد
CREATE TABLE IF NOT EXISTS `achievement_certificates` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT UNSIGNED NOT NULL,
  `eval_id`     INT UNSIGNED NULL,
  `snap_id`     INT UNSIGNED NOT NULL,
  `serial_no`   VARCHAR(40) NOT NULL,
  `verify_code` VARCHAR(40) NOT NULL,
  `issued_by`   INT NOT NULL,
  `issued_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `pdf_ref`     VARCHAR(190) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cert_serial` (`serial_no`),
  UNIQUE KEY `uq_cert_verify` (`verify_code`),
  UNIQUE KEY `uq_cert_snap` (`snap_id`),
  CONSTRAINT `fk_cert_snap` FOREIGN KEY (`snap_id`) REFERENCES `achievement_snapshots` (`id`),
  CONSTRAINT `fk_cert_eval` FOREIGN KEY (`eval_id`) REFERENCES `evaluations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='USR-01 §7-⑤ — الشهادةُ تُولَّد من الأرقام المقاسة ولا تُصدَر مرتين';

-- ④ سجلُّ نشاط البوابة — Insert-only: لا يُعدَّل ولا يُحذف
CREATE TABLE IF NOT EXISTS `portal_activity_log` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT UNSIGNED NOT NULL,
  `account_id`  INT NOT NULL,
  `capacity_id` INT UNSIGNED NULL,
  `action_code` VARCHAR(40) NOT NULL,
  `target_type` VARCHAR(40) NULL,
  `target_id`   VARCHAR(64) NULL,
  `result`      ENUM('ok','denied') NOT NULL DEFAULT 'ok',
  `ip`          VARCHAR(45) NULL,
  `device`      VARCHAR(190) NULL,
  `at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_pal_account` (`account_id`,`at`),
  KEY `ix_pal_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='USR-01 §5 — سجلُّ النشاط: يراه صاحبُه والمدقّقُ وHR ولا يُعدَّل ولا يُحذف';

-- ───────────────────────────────────────────────────────────────────────────
-- الشاشات 187–190: بوابتي · إنجازي · التقييم · شهادة الإنجاز
-- «متاحةٌ بنقرةٍ من أي صفحة» — بابُها في الشريط العلوي لكل مسجَّل
-- ───────────────────────────────────────────────────────────────────────────

INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT * FROM (
    SELECT 187 i, 'بوابتي'        n, 'Portal/my_portal.php'        c, 15 r, 0 l, 1 q, 'fa fa-id-card'       ic, 0 d UNION ALL
    SELECT 188,   'إنجازي',          'Portal/my_achievement.php',     15,   0,   1,   'fa fa-chart-simple',     0   UNION ALL
    SELECT 189,   'التقييم الثنائي', 'Portal/my_evaluation.php',      15,   0,   1,   'fa fa-user-check',       0   UNION ALL
    SELECT 190,   'شهادة الإنجاز',   'Portal/my_certificate.php',     15,   0,   1,   'fa fa-certificate',      0
) m
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `modules`) x WHERE x.`code` = m.c);

-- البوابةُ شخصيةٌ لكل مسجَّل — العرضُ لكل الأدوار والحارسُ الثلاثيُّ هو الحكم
INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.id, m.mid, 1, CASE WHEN m.mid IN (189) THEN 1 ELSE 0 END, 0, 0
  FROM `roles` r
  CROSS JOIN (SELECT 187 mid UNION ALL SELECT 188 UNION ALL SELECT 189 UNION ALL SELECT 190) m
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
                    WHERE rp.`role_id` = r.id AND rp.`module_id` = m.mid);
