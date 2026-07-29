-- ═══════════════════════════════════════════════════════════════════════════
-- H-01 · المرحلة ① — بنيةُ الحاويات وقيودُها **بلا سلوك** (OPM-01 §4) — 2026-07-29
-- ───────────────────────────────────────────────────────────────────────────
-- **صفرُ أثرٍ على المسار الحي**: هذا الترحيلُ يُنشئ جداولَ فارغةً بقيودها ولا
-- يمسّ صفًّا قائمًا ولا يحجب إدخالًا. والتحوّلُ لعقدٍ رائد (المرحلة ②) والحجبُ
-- بعلَمٍ لكل موقع (المرحلة ③) يأتيان بعده — **لا قلبةً واحدةً على الجميع**.
--
-- > الخطرُ الحاكمُ الذي يفرض هذا التدرُّج: إنفاذُ «لا تُسجَّل وحدةٌ في موقعٍ لم
-- > تكتمل حاوياتُه» اليومَ **يوقف كلَّ إدخال تايم شيت** — صفرُ حاويةٍ موجودة.
-- > وهو فخُّ E-08 نفسُه (138 من 138)، وقد نجحت معالجتُه بالعلَم فتُتبَع.
--
-- ═══ المستوياتُ الأربعةُ في جدولٍ واحد ═══════════════════════════════════
-- شجرةٌ ذاتيةُ المرجع لأن المستويات **تتشارك البنية** (سقفٌ · مخصَّصٌ · مستهلَك ·
-- مدةٌ · حالة) وتختلف في **مرجعها** وحده. وأربعةُ جداولَ متطابقةِ الأعمدة تعني
-- أربعَ نسخٍ من كل قاعدةٍ وأربعةَ مواضعَ للخطأ.
--   ① رئيسية → سقفُ الالتزام كاملًا لبندٍ من بنود العقد — **مصدرُها العقدُ لا اليد**
--   ② مورد   → حصتُه من البند · Σ الإخوة ≤ سقفُ الرئيسية
--   ③ معدة   → حصتُها من حصة موردها · Σ ≤ حصةُ المورد
--   ④ مشغّل  → حصتُه من حصة معدته  · Σ ≤ حصةُ المعدة
--
-- ═══ «قيدٌ بنيويٌّ لا فحصٌ تطبيقي» — كيف يتحقق Σ ══════════════════════════
-- `CHECK` في MySQL لا يرى صفوفًا أخرى، فلا يُكتب فيه «Σ الأبناء ≤ الأب» مباشرةً.
-- والحيلةُ المعيارية: يحمل **الأبُ** مجموعَ ما وزّعه (`allocated_qty`) ويُحرَس
-- بـ`CHECK (allocated_qty <= cap_qty)` — وهو قيدُ صفٍّ واحدٍ يقدر عليه المحرّك.
-- فيصير التخصيصُ: إدراجُ الابن + زيادةُ `allocated_qty` عند الأب **في معاملةٍ
-- واحدة**؛ فإن تجاوز المجموعُ السقفَ **رفض المحرّكُ المعاملةَ كلَّها**.
-- وبهذا يكون الحارسُ في القاعدة لا في `if` بـPHP — ولا يلتفّ عليه مسارٌ جديد.
--
-- والاستهلاكُ كذلك: `CHECK (consumed_qty <= cap_qty)` — فلا يُستهلك ما لم يُخصَّص.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `op_containers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `container_no` VARCHAR(40) NOT NULL COMMENT 'CNT-سنة-تسلسل — ترقيمٌ خادمي',

  `level` ENUM('رئيسية','مورد','معدة','مشغّل')
      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL للرئيسية حصرًا — يحرسه ck_container_parent',

  -- المصدرُ: بندُ العقد. «الرئيسيةُ لا تُنشأ يدويًّا» — تتولّد من البند بسقفه.
  `contract_id` INT UNSIGNED NOT NULL,
  `contract_item_id` INT UNSIGNED DEFAULT NULL COMMENT 'contractequipments.id — مصدرُ سقف الرئيسية',

  `unit_type` ENUM('hour','ton','meter','cbm','day','shift','trip')
      NOT NULL DEFAULT 'hour' COMMENT 'وحدةُ البند — والسقفُ والمستهلَكُ بها',
  `work_model` VARCHAR(40) DEFAULT NULL COMMENT 'نموذجُ العمل كما في البند',

  -- ── الأرقامُ الثلاثة وقيودُها ──────────────────────────────────────────
  `cap_qty` DECIMAL(16,2) NOT NULL DEFAULT 0.00 COMMENT 'السقف — لا يُتجاوز',
  `allocated_qty` DECIMAL(16,2) NOT NULL DEFAULT 0.00 COMMENT 'Σ ما وُزّع على الأبناء — قيدُ Σ البنيوي',
  `consumed_qty` DECIMAL(16,2) NOT NULL DEFAULT 0.00 COMMENT 'Σ ما استُهلك فعلًا',
  `remaining_qty` DECIMAL(16,2) AS (`cap_qty` - `consumed_qty`) STORED
      COMMENT 'مولَّدٌ لا يُكتب — فلا مصدرانِ للرقم الواحد',

  -- ── المراجعُ بحسب المستوى ─────────────────────────────────────────────
  `supplier_id` INT UNSIGNED DEFAULT NULL,
  `equipment_id` INT UNSIGNED DEFAULT NULL,
  `operator_employee_id` INT UNSIGNED DEFAULT NULL,
  `project_id` INT UNSIGNED DEFAULT NULL COMMENT 'الموقع — مفتاحُ الحجب المرحليّ (المرحلة ③)',

  -- الدورُ: للمعدة (أساسية/احتياطية) وللمشغّل (أساسي/بديل أول/بديل ثانٍ/مشترك)
  `role_kind` ENUM('أساسية','احتياطية','أساسي','بديل أول','بديل ثانٍ','مشترك')
      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shift_no` TINYINT UNSIGNED DEFAULT NULL COMMENT 'نوبةُ المشغّل',

  `valid_from` DATE DEFAULT NULL,
  `valid_to` DATE DEFAULT NULL,

  -- الحالةُ **موروثةٌ من العقد** (H-02 §وراثة): تُقرأ اشتقاقًا ولا تُغيَّر من هنا.
  -- والعمودُ للإقفال المحليِّ وحدَه (حاويةٌ أُقفلت وعقدُها ما زال نافذًا).
  `state` ENUM('نشطة','معلَّقة','مقفلة')
      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'نشطة',
  `close_reason` VARCHAR(200) DEFAULT NULL,

  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at` DATETIME DEFAULT NULL,
  `deleted_by` INT UNSIGNED DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_container_no` (`company_id`, `container_no`),
  -- «الرئيسيةُ مصدرُها بنودُ العقد ولا تُنشأ يدويًّا»: بندٌ واحدٌ ⇒ رئيسيةٌ واحدة
  UNIQUE KEY `uq_main_per_item` (`company_id`, `contract_item_id`, `level`),
  KEY `ix_parent` (`company_id`, `parent_id`),
  KEY `ix_contract` (`company_id`, `contract_id`, `level`),
  KEY `ix_site` (`company_id`, `project_id`, `state`),

  -- ═══ القيودُ البنيوية ═══
  CONSTRAINT `ck_container_alloc`
      CHECK (`allocated_qty` >= 0 AND `allocated_qty` <= `cap_qty`),
  CONSTRAINT `ck_container_consumed`
      CHECK (`consumed_qty` >= 0 AND `consumed_qty` <= `cap_qty`),
  CONSTRAINT `ck_container_cap`
      CHECK (`cap_qty` >= 0),
  -- الرئيسيةُ بلا أب، وما دونها بأبٍ — فلا شجرةَ معلَّقةٌ في الهواء
  CONSTRAINT `ck_container_parent`
      CHECK ((`level` = 'رئيسية' AND `parent_id` IS NULL)
          OR (`level` <> 'رئيسية' AND `parent_id` IS NOT NULL)),
  CONSTRAINT `fk_container_parent`
      FOREIGN KEY (`parent_id`) REFERENCES `op_containers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='H-01 §4 — حاوياتُ العقد بمستوياتها الأربعة وقيدِ Σ البنيوي';


-- ═══ دفترُ الاستهلاك — ذريٌّ وعطِلٌ بمفتاحه ════════════════════════════════
-- «الاستهلاكُ الذري» = معاملةٌ **واحدة** تخصم من المستويات الأربعة أو لا تخصم
-- شيئًا. وهذا الجدولُ سجلُّها: صفٌّ لكل خصمٍ بمرجع واقعته، و`uq_consumption_idem`
-- يمنع خصمَ الواقعة نفسِها مرتين — فإعادةُ الإرسال لا تستهلك ثانيةً.
CREATE TABLE IF NOT EXISTS `container_consumption` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `container_id` INT UNSIGNED NOT NULL COMMENT 'الحاويةُ الورقية (مستوى المشغّل غالبًا)',
  `source_kind` ENUM('unit_entry','timesheet','manual') NOT NULL DEFAULT 'unit_entry',
  `source_ref` INT UNSIGNED NOT NULL COMMENT 'الواقعةُ التي استهلكت',
  `qty` DECIMAL(16,2) NOT NULL COMMENT 'موجبٌ استهلاكًا · سالبٌ ردًّا (عكسٌ موثَّق)',
  `unit_type` ENUM('hour','ton','meter','cbm','day','shift','trip') NOT NULL DEFAULT 'hour',
  `consumed_on` DATE NOT NULL,
  `idem_key` VARCHAR(80) NOT NULL COMMENT 'مفتاحُ العطالة — يمنع تكرارَ الاستهلاك',
  `note` VARCHAR(200) DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_consumption_idem` (`company_id`, `idem_key`),
  KEY `ix_container` (`company_id`, `container_id`, `consumed_on`),
  KEY `ix_source` (`company_id`, `source_kind`, `source_ref`),
  CONSTRAINT `fk_consumption_container`
      FOREIGN KEY (`container_id`) REFERENCES `op_containers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='H-01 §4 — دفترُ استهلاك الحاويات؛ الخصمُ الذريُّ يُسجَّل هنا';


-- ═══ التبديلُ — معدةٌ أو مشغّلٌ يحلّ محلَّ آخر ═══════════════════════════════
-- التبديلُ **حركةٌ موثَّقة** لا تعديلٌ صامتٌ لحاوية: مَن خرج ومَن دخل ومتى ولماذا.
-- ولا يُمسّ رصيدُ الحاوية هنا — الرصيدُ ينتقل بتخصيصٍ جديدٍ يحرسه قيدُ Σ نفسُه.
CREATE TABLE IF NOT EXISTS `container_swaps` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `container_id` INT UNSIGNED NOT NULL COMMENT 'الحاويةُ التي وقع فيها التبديل',
  `swap_kind` ENUM('معدة','مشغّل')
      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `out_ref` INT UNSIGNED DEFAULT NULL COMMENT 'الخارج (معدة/موظف)',
  `in_ref` INT UNSIGNED DEFAULT NULL COMMENT 'الداخل',
  `effective_from` DATE NOT NULL,
  `reason` VARCHAR(255) NOT NULL COMMENT 'إلزام — لا تبديلَ بلا سبب',
  `doc_ref` VARCHAR(120) DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `ix_container_swap` (`company_id`, `container_id`, `effective_from`),
  -- الداخلُ والخارجُ لا يكونان واحدًا — تبديلٌ بلا تبديل
  CONSTRAINT `ck_swap_differs` CHECK (`out_ref` IS NULL OR `in_ref` IS NULL OR `out_ref` <> `in_ref`),
  CONSTRAINT `fk_swap_container`
      FOREIGN KEY (`container_id`) REFERENCES `op_containers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='H-01 §4 — تبديلُ معدةٍ أو مشغّلٍ داخل حاوية، بسببه ومستنده';


-- ═══ دورةُ تناوب المشغّلين ═════════════════════════════════════════════════
-- «حاوياتُ مشغّلين بسلسلتهم **ودورةِ تناوبهم**» — الدورةُ تعريفٌ زمنيٌّ (أيامُ
-- عملٍ وأيامُ راحة) يُقرأ ليُعرف مَن المناوبُ يومَ كذا، ولا يُكتب في كل يوم.
CREATE TABLE IF NOT EXISTS `operator_rotations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `container_id` INT UNSIGNED NOT NULL COMMENT 'حاويةُ المشغّل',
  `operator_employee_id` INT UNSIGNED NOT NULL,
  `cycle_on_days` SMALLINT UNSIGNED NOT NULL COMMENT 'أيامُ العمل في الدورة',
  `cycle_off_days` SMALLINT UNSIGNED NOT NULL COMMENT 'أيامُ الراحة',
  `cycle_start` DATE NOT NULL COMMENT 'مبدأُ الدورة — منه يُحسب المناوب',
  `shift_no` TINYINT UNSIGNED DEFAULT NULL,
  `valid_to` DATE DEFAULT NULL,
  `note` VARCHAR(200) DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rotation` (`company_id`, `container_id`, `operator_employee_id`, `cycle_start`),
  KEY `ix_rotation_op` (`company_id`, `operator_employee_id`),
  -- دورةٌ بلا يومِ عملٍ واحدٍ ليست دورة
  CONSTRAINT `ck_rotation_cycle` CHECK (`cycle_on_days` > 0),
  CONSTRAINT `fk_rotation_container`
      FOREIGN KEY (`container_id`) REFERENCES `op_containers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='H-01 §4 — دوراتُ تناوب المشغّلين داخل حاوياتهم';
