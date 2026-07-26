-- ═══════════════════════════════════════════════════════════════════════════
-- المصدر الموحّد لقوائم التنقل — UX-01 §10.2 · بوابة UX-02 §9-④
-- ───────────────────────────────────────────────────────────────────────────
-- اليوم تُركَّب قائمةُ كل دورٍ من خمسة مصادر (روابطُ ثابتة · جدول modules ·
-- بوابةُ الطلبات · منحُ العرض المالي · رابطُ التقارير الذكي) — فنشأت خمسةُ
-- روابطَ مكرَّرةٍ ورابطٌ ميت، ولا استعلامَ واحدٌ يجيب «ماذا يرى هذا الدور؟».
--
-- والسببُ الجذريُّ المقيس: الظهورُ محكومٌ بـ modules.owner_role_id (الملكية)
-- بينما الوصولُ محكومٌ بـ role_permissions (الصلاحية) — و425 منحةَ عرضٍ لا
-- يعبّر عنها نموذجُ الملكية، فاختُرعت المصادرُ الإضافية ترقيعًا لها.
--
-- النموذج المعتمد (قرار المالك 2026-07-26): **الجدولُ يحدد المكان،
-- والصلاحيةُ تحدد الظهور** — nav_items يقول أين يقع الرابط وبأي ترتيب،
-- و role_permissions.can_view يقول أيظهر أصلًا. فلا رابطَ ميت (ظهورٌ بلا
-- صلاحية)، ولا صلاحيةٌ صامتة (وصولٌ بلا رابط) — ما لم نضعه نحن.
--
-- المجموعات (link_groups) تبقى **داخل** الأبواب الستة لا بديلًا عنها.
-- والمصادرُ الخمسة القديمة تبقى قراءةً — لا يُحذف منها شيء (UX-01 §10.1).
-- ═══════════════════════════════════════════════════════════════════════════

-- ── عناصر القوائم: صفٌّ لكل (دور × مسار) ──────────────────────────────────
CREATE TABLE IF NOT EXISTS `nav_items` (
  `id`              INT NOT NULL AUTO_INCREMENT,
  `role_id`         INT NOT NULL COMMENT 'الدور المالك لهذا العنصر في قائمته',
  `door`            VARCHAR(16) NOT NULL COMMENT 'HOME·DAILY·APPR·REC·REP·SET — الأبواب الستة',
  `group_id`        INT NULL COMMENT 'link_groups — مجموعةٌ قابلةٌ للطيّ داخل الباب؛ NULL = مباشرةً تحته',
  `module_id`       INT NULL COMMENT 'modules.id حين يكون العنصر شاشةً مسجَّلة — مرجعُ الصلاحية والاسم',
  `label_ar`        VARCHAR(64) NOT NULL COMMENT 'اسم العرض؛ يُفحص خلوّه من المحظور المعماري عند الحفظ',
  `route`           VARCHAR(128) NOT NULL COMMENT 'المسار كما في سجل الشاشات',
  `icon`            VARCHAR(50) NULL,
  `sort_order`      INT NOT NULL DEFAULT 0 COMMENT 'الترتيب داخل الباب/المجموعة',
  `counter_source`  VARCHAR(64) NULL COMMENT 'مُعرِّف العدّاد من سجل العدّادات — عدّادٌ واحدٌ بقيمةٍ واحدة',
  `permission_code` VARCHAR(128) NULL COMMENT 'كود الشاشة لفحص can_view؛ NULL = ظهورٌ بلا فحص (ثوابت)',
  `active`          TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nav_role_route` (`role_id`, `route`),
  KEY `ix_nav_role_door` (`role_id`, `door`, `sort_order`),
  KEY `ix_nav_group` (`group_id`),
  KEY `ix_nav_module` (`module_id`),
  CONSTRAINT `chk_nav_door` CHECK (`door` IN ('HOME','DAILY','APPR','REC','REP','SET'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='المصدر الموحّد لعناصر السايدبار — UX-01 §10.2';

-- ── تحويلات المسارات: لا يُحذف مسارٌ قبل هبوط hits صفرًا فترةً موثَّقة ────
CREATE TABLE IF NOT EXISTS `nav_redirects` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `old_route`  VARCHAR(128) NOT NULL,
  `new_route`  VARCHAR(128) NOT NULL,
  `active`     TINYINT(1) NOT NULL DEFAULT 1,
  `hits`       INT NOT NULL DEFAULT 0 COMMENT 'عدّادُ استعمالٍ يقيس أمان الحذف لاحقًا',
  `last_hit_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_navred_old` (`old_route`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='تحويلُ المسارات القديمة — UX-01 §10.2';
