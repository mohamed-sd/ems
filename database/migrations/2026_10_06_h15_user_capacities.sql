-- ═══════════════════════════════════════════════════════════════════════════
-- H-15 · طبقةُ الصفات — شخصٌ × صفةٌ × نطاقٌ × مدةٌ × مصدرُها — 2026-08-01
-- البطاقة: docs/specs/H-15_user_capacities.md
-- المصدر: USR-01 §2 · §9.1 — «الشخصُ واحدٌ، والصفاتُ متعددةٌ ومتزامنة» ·
--         «حزمةُ صلاحياتٍ مرتبطةٌ بالصفة لا بالشخص — فانتهاءُ العقد أو
--         التفويض يُسقطها آليًّا».
-- ───────────────────────────────────────────────────────────────────────────
-- ⚠ بناءٌ إضافيٌّ لا يكسر (USR-01 §9.3-التوافق): حسابُ الدخول والأدوارُ
--   القائمةُ كما هي — والصفاتُ تُبنى **فوقها**؛ ولا عمودَ يُمسّ في `users`.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `user_capacities` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL,

  -- الهوية: الشخصُ (قد يكون خارجيًّا بلا سجل موظف) والحسابُ (المرساةُ الواحدة)
  `person_id`     INT NULL COMMENT 'employees.id — NULL للخارجي بلا سجل موظف',
  `account_id`    INT NOT NULL COMMENT 'users.id — حسابُ دخولٍ واحدٌ لكل الصفات',

  -- الصفة (USR-01 §9.1 نصًّا) ودورُها: الصلاحياتُ بالصفة لا بالشخص
  `capacity_type` ENUM('employee','project_employee','operator','technician',
                       'shift_supervisor','project_manager','supplier_supervisor',
                       'client_rep','auditor','executive') NOT NULL,
  `role`          VARCHAR(30) NOT NULL COMMENT 'حزمةُ الصلاحيات المرتبطة بالصفة (roles.id)',

  -- النطاق: الكيانُ الذي تنحصر فيه الرؤية — يُحقن بنيويًّا لا زخرفيًّا
  `scope_type`    ENUM('company','project','site','supplier','client') NOT NULL DEFAULT 'company',
  `scope_id`      INT NULL COMMENT 'معرّفُ النطاق — إلزاميٌّ لغير company',

  -- المصدر: عقدٌ أو تفويض — «لا صفةَ بلا مصدر»؛ والموروثُ يُعلَن لا يُلفَّق
  `source_type`   ENUM('contract','delegation') NOT NULL,
  `source_id`     INT NULL COMMENT 'مرجعُ المصدر — إلزاميٌّ للعقد',
  `source_note`   VARCHAR(190) NULL COMMENT 'إعلانُ التفويض الموروث ونحوه',

  -- المدة والحالة: الانتهاءُ يجمّد ولا يحذف — والسجلُّ يبقى للقراءة
  `valid_from`    DATE NOT NULL,
  `valid_to`      DATE NULL,
  `state`         ENUM('active','frozen','expired') NOT NULL DEFAULT 'active',
  `state_reason`  VARCHAR(255) NULL,
  `state_at`      DATETIME NULL,

  `created_by`    INT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  -- الفريدُ بالحساب لا بالشخص: person_id قد يكون NULL (خارجي) وNULL في
  -- UNIQUE لا يمنع التكرار — والحسابُ «واحدٌ للشخص بكل صفاته» (§2-②)
  UNIQUE KEY `uq_uc_capacity` (`account_id`,`capacity_type`,`scope_type`,`scope_id`,`valid_from`),
  KEY `ix_uc_account_state` (`account_id`,`state`),
  KEY `ix_uc_person` (`person_id`),
  KEY `ix_uc_company` (`company_id`),
  KEY `ix_uc_scope` (`scope_type`,`scope_id`),

  -- ① نطاقٌ مسمًّى بلا معرّفٍ مستحيل — «النطاقُ يُحقن في كل استعلام» (§2-⑤)
  CONSTRAINT `ck_uc_scope` CHECK (`scope_type` = 'company' OR `scope_id` IS NOT NULL),
  -- ② مصدرُ العقد يلزمه مرجعُه — والتفويضُ الموروثُ يُعلَن بملاحظته
  CONSTRAINT `ck_uc_source` CHECK (`source_type` <> 'contract' OR `source_id` IS NOT NULL),
  -- ③ ولا نافذةَ معكوسة
  CONSTRAINT `ck_uc_window` CHECK (`valid_to` IS NULL OR `valid_to` >= `valid_from`),
  -- ④ ولا تجميدَ صامتًا: غيرُ النشط بسببه ووقته
  CONSTRAINT `ck_uc_state` CHECK (`state` = 'active'
      OR (`state_reason` IS NOT NULL AND `state_at` IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='H-15 · USR-01 §2/§9.1 — طبقةُ الصفات: تعددٌ وتزامنٌ وانتهاءٌ آلي';

-- ───────────────────────────────────────────────────────────────────────────
-- الشاشة 182 — «صفاتي ومبدّل المساحة» (الشريحة ③)
-- ───────────────────────────────────────────────────────────────────────────

INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 182, 'صفاتي ومبدّل المساحة', 'user_capacities.php', 15, 0, 1, 'fa fa-id-badge', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'user_capacities.php');

-- العرضُ لكل الأدوار الداخلية الفاعلة (الشاشةُ تعرض صفاتِ صاحبها فقط) —
-- والإدارةُ (الاشتقاق والتجميد) محصورةٌ بمالك الشاشة: مديرُ الصلاحيات (15)
INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.id, 182, 1, 0, CASE WHEN r.id = 15 THEN 1 ELSE 0 END, 0
  FROM `roles` r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.id AND rp.`module_id` = 182);
