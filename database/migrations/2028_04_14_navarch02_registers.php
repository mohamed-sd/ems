<?php
/**
 * 2028_04_14_navarch02_registers.php — سجلَّاتُ NAV-ARCH-02 الحاكمة (§6 · §8 · §15)
 * @migration-objects: nav_workspaces columns, nav_workspace_placements,
 *                     nav_legacy_disposition, nav_cross_domain_register
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **§6** يوجب لسجلِّ المساحاتِ ستةَ أعمدةٍ ناقصةً: `workspace_code` ·
 *   `canonical_name` · `workspace_type` · `owner_domain` · `governing_source` ·
 *   `version`. والقائمُ `kind` يؤدّي دورَ `workspace_type` **لكنَّه ينقصه
 *   `INDEPENDENT_ASSURANCE`** — ولذلك عمودٌ جديدٌ بالمفرداتِ الخمسِ كاملةً،
 *   و`kind` **يبقى كما هو** فلا ينكسر قارئٌ قائم.
 *
 * ◆ **§8 يقول حرفًا**: «ينشأ `nav_workspace_placements` وهو **مصدر الحقيقة
 *   الجديد** لمكان ظهور الشاشات». ⇒ **سجلٌّ جديدٌ لا إزاحةُ مفرداتِ القائم**:
 *   لو فُكِّك `nav_placements.placement_type` في مكانِه لسقطت صفوفٌ صامتةً عند
 *   كلِّ قارئٍ يعُدُّ `MENU_ITEM` بالاسمِ — وهو العطبُ المقيسُ حرفًا في
 *   [[enum-vocabulary-consumers]] (‏17/17 ⇒ 0/17 بلا تغيُّرِ صفٍّ واحد).
 *   فالقائمُ يبقى لقرّائه، والجديدُ يحمل مفرداتِ §9 التسعَ، **والتقاعدُ بمراحلِ
 *   §33 لا بهجرةٍ واحدة**.
 *
 * ◆ **§15** سجلُّ حكمِ الإرثِ بحقولِه الخمسةَ عشرَ، و**§12** سجلُّ العابرِ
 *   للإدارات بحالاتِه الخمس.
 *
 * ⛔ ولا يُحذَف صفٌّ ولا عمودٌ هنا — إضافةٌ محضة. والعكسُ في `_down.php`.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("connect fail\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);

/** إضافةُ عمودٍ إن غاب — والقائمُ يُترَك كما هو، لا يُدهَس */
$addCol = function ($table, $col, $ddl) use ($conn) {
    $q = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$col}'");
    if ($q && $q->num_rows) { echo "= {$table}.{$col} قائمٌ سلفًا\n"; return; }
    if ($conn->query("ALTER TABLE `{$table}` ADD COLUMN {$ddl}")) { echo "+ {$table}.{$col}\n"; }
    else { echo "x {$table}.{$col}: " . $conn->error . "\n"; }
};

/* ═══ ① §6 — أعمدةُ سجلِّ المساحاتِ الستّة ══════════════════════════════════ */
$addCol('nav_workspaces', 'workspace_code',
    "`workspace_code` VARCHAR(24) NULL DEFAULT NULL COMMENT 'رمزُ المساحةِ الحاكمُ — §6'");
$addCol('nav_workspaces', 'canonical_name',
    "`canonical_name` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الاسمُ المعياريُّ — §6'");
$addCol('nav_workspaces', 'workspace_type',
    "`workspace_type` ENUM('DEPARTMENT','EXECUTIVE','PERSONAL','PLATFORM_UTILITY','INDEPENDENT_ASSURANCE')
        NULL DEFAULT NULL COMMENT 'نوعُ المساحةِ بمفرداتِ §6 الخمس — و`kind` يبقى لقرّائه'");
$addCol('nav_workspaces', 'owner_domain',
    "`owner_domain` VARCHAR(120) NULL DEFAULT NULL COMMENT 'المجالُ المالك — §6'");
$addCol('nav_workspaces', 'governing_source',
    "`governing_source` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المصدرُ الحاكم — §6'");
$addCol('nav_workspaces', 'version',
    "`version` SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'نسخةُ سجلِّ المساحة — §6'");

/* ═══ ② §8 — سجلُّ الموضعِ الحاكمُ الجديد ═══════════════════════════════════ */
$sql = "CREATE TABLE IF NOT EXISTS `nav_workspace_placements` (
  `placement_id`   VARCHAR(28)  NOT NULL COMMENT 'معرِّفُ الموضعِ الحاكم — §8',
  `screen_id`      VARCHAR(24)  NULL DEFAULT NULL COMMENT 'هويّةُ الشاشةِ — لا تتغيّر بالنقل (§42)',
  `workspace_id`   VARCHAR(24)  NOT NULL COMMENT 'المساحة',
  `group_id`       INT          NULL DEFAULT NULL COMMENT 'مجموعةُ دورةِ العمل',
  `placement_type` ENUM('PRIMARY','SECONDARY_APPROVED','GLOBAL_SHELL','PERSONAL',
                        'CONTEXTUAL_ACTION','TAB_CHILD','DIRECT_ONLY',
                        'EXECUTIVE_PROJECTION','UTILITY')
                   NOT NULL COMMENT 'مفرداتُ §9 التسع — ولا يدخل السايدبارَ إلا PRIMARY وSECONDARY_APPROVED',
  `sort_no`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `route`          VARCHAR(190) NULL DEFAULT NULL COMMENT 'المسارُ — مفتاحُ المطابقةِ مع التصييرِ الحيّ',
  `canonical_label` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الاسمُ الحاكمُ في هذه المساحة',
  `governing_source` VARCHAR(255) NOT NULL COMMENT 'المصدرُ الحاكمُ — ⛔ ولا موضعَ بلا مصدر (§41)',
  `source_ref`     VARCHAR(255) NULL DEFAULT NULL COMMENT 'الإحالةُ الدقيقة',
  `reason_code`    VARCHAR(48)  NOT NULL COMMENT 'سببُ الموضعِ — مفرداتُ §18/§19',
  `effective_from` DATE         NULL DEFAULT NULL,
  `effective_to`   DATE         NULL DEFAULT NULL,
  `status`         ENUM('DRAFT','ACTIVE','SUPERSEDED','RETIRED','BLOCKED')
                   NOT NULL DEFAULT 'DRAFT' COMMENT 'BLOCKED = حُجب هذا الموضعُ وحدَه (§35) لا البرنامج',
  `version`        SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `created_by`     VARCHAR(64)  NOT NULL DEFAULT '' COMMENT 'مَن أنشأ — أداةً كان أو إنسانًا',
  `approved_by`    VARCHAR(64)  NULL DEFAULT NULL COMMENT '⛔ SECONDARY_APPROVED بلا معتمِدٍ غيرُ معتمَد (§12-هـ)',
  `legacy_ref`     INT          NULL DEFAULT NULL COMMENT 'صفُّ nav_placements المقابلُ إن وُجد',
  `created_at`     DATETIME     NOT NULL,
  `updated_at`     DATETIME     NULL DEFAULT NULL,
  PRIMARY KEY (`placement_id`),
  UNIQUE KEY `uq_ws_route` (`workspace_id`,`route`),
  KEY `ix_ws_status` (`workspace_id`,`status`,`placement_type`),
  KEY `ix_screen` (`screen_id`),
  KEY `ix_route` (`route`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT 'NAV-ARCH-02 §8 — سجلُّ الموضعِ الحاكم: مصدرُ الحقيقةِ لمكانِ ظهورِ الشاشة'";
echo $conn->query($sql) ? "+ جدول nav_workspace_placements\n"
                        : "x nav_workspace_placements: " . $conn->error . "\n";

/* ═══ ③ §15 — سجلُّ حكمِ الإرثِ بحقولِه الخمسةَ عشرَ ═════════════════════════ */
$sql = "CREATE TABLE IF NOT EXISTS `nav_legacy_disposition` (
  `legacy_item_id`      VARCHAR(28)  NOT NULL COMMENT '§15-1',
  `screen_id`           VARCHAR(24)  NULL DEFAULT NULL COMMENT '§15-2',
  `current_workspace`   VARCHAR(24)  NOT NULL COMMENT '§15-3',
  `current_label`       VARCHAR(190) NOT NULL COMMENT '§15-4',
  `current_route`       VARCHAR(190) NOT NULL COMMENT '§15-5',
  `usage_count`         INT          NOT NULL DEFAULT 0 COMMENT '§15-6 — أثرٌ لا قرار (§32)',
  `target_match`        VARCHAR(190) NULL DEFAULT NULL COMMENT '§15-7',
  `replacement_screen_id` VARCHAR(24) NULL DEFAULT NULL COMMENT '§15-8',
  `disposition`         ENUM('CANONICAL_EQUIVALENT','DUPLICATE','REPLACED','TAB_CHILD',
                             'DIRECT_ONLY','CROSS_DOMAIN','UTILITY','PERSONAL',
                             'TRUE_TARGET_GAP','OBSOLETE','UNKNOWN_REQUIRES_DECISION')
                        NOT NULL COMMENT '§16 — إحدى إحدى عشرةَ مفردةً حصرًا',
  `action`              ENUM('KEEP_PRIMARY','KEEP_SECONDARY','MOVE_TO_WS_MY','MOVE_TO_GLOBAL_SHELL',
                             'MOVE_TO_PARENT','CONTEXTUALIZE','REPLACE','REDIRECT','RETIRE',
                             'TARGET_GAP_REVIEW','ESCALATE')
                        NOT NULL COMMENT '§19 — ⛔ والنموذجُ الثنائيُّ يبقى/يُخفى ملغًى',
  `reason`              VARCHAR(400) NOT NULL COMMENT '§15-10',
  `domain_owner`        VARCHAR(120) NULL DEFAULT NULL COMMENT '§15-11',
  `decision_ref`        VARCHAR(190) NULL DEFAULT NULL COMMENT '§15-12',
  `effective_date`      DATE         NULL DEFAULT NULL COMMENT '§15-13',
  `retirement_date`     DATE         NULL DEFAULT NULL COMMENT '§15-14',
  `evidence`            VARCHAR(400) NOT NULL COMMENT '§15-15 — ⛔ ولا إخفاءَ بلا دليل (§4)',
  `access_replacement`  VARCHAR(255) NULL DEFAULT NULL COMMENT 'بديلُ الوصولِ — شرطُ §4 الثاني',
  `decided_level`       ENUM('L1_ARCHITECTURE','L2_DOMAIN_OWNER','L3_GOVERNANCE','L4_OWNER')
                        NOT NULL DEFAULT 'L1_ARCHITECTURE' COMMENT 'سلّمُ §34 الرباعيّ',
  `retire_stage`        ENUM('NONE','A_COEXIST','B_REDIRECT','C_OUT_OF_SIDEBAR','D_ROUTE_OFF','E_EVIDENCE')
                        NOT NULL DEFAULT 'NONE' COMMENT 'مراحلُ §33 الخمس',
  `created_at`          DATETIME     NOT NULL,
  `updated_at`          DATETIME     NULL DEFAULT NULL,
  PRIMARY KEY (`legacy_item_id`),
  KEY `ix_ws` (`current_workspace`),
  KEY `ix_disp` (`disposition`),
  KEY `ix_action` (`action`),
  KEY `ix_route` (`current_route`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT 'NAV-ARCH-02 §15 — حكمُ كلِّ ظهورٍ إرثيّ: ⛔ ولا إرثَ يظهر بلا حكم (§41)'";
echo $conn->query($sql) ? "+ جدول nav_legacy_disposition\n"
                        : "x nav_legacy_disposition: " . $conn->error . "\n";

/* ═══ ④ §12 — سجلُّ العابرِ للإداراتِ بحالاتِه الخمس ════════════════════════ */
$sql = "CREATE TABLE IF NOT EXISTS `nav_cross_domain_register` (
  `cd_id`             VARCHAR(28)  NOT NULL,
  `screen_id`         VARCHAR(24)  NULL DEFAULT NULL,
  `route`             VARCHAR(190) NOT NULL,
  `current_label`     VARCHAR(190) NOT NULL,
  `consumer_workspace` VARCHAR(24) NOT NULL COMMENT 'المساحةُ التي يظهر فيها اليوم',
  `owner_workspace`   VARCHAR(24)  NOT NULL COMMENT 'المساحةُ المالكة — ⛔ ولا يتغيّر المالكُ بالظهور (§42)',
  `need_case`         ENUM('A_PROJECTION','B_REQUEST_HANDOFF','C_CONTEXTUAL_ACTION',
                           'D_WORKSPACE_SWITCH','E_SECONDARY_APPROVED')
                      NOT NULL COMMENT 'حالاتُ §12 الخمس — نوعُ الاحتياجِ لا نقلُ الشاشة',
  `remedy`            VARCHAR(255) NOT NULL COMMENT 'العلاجُ المقابلُ للحالة',
  `access_path`       VARCHAR(255) NOT NULL COMMENT 'كيف يصل المستخدمُ بعدَ الإزالةِ من السايدبار',
  `scope`             VARCHAR(190) NULL DEFAULT NULL COMMENT 'نطاقُ الاستثناء — لِـE وحدَها',
  `governing_source`  VARCHAR(255) NOT NULL,
  `approved_by`       VARCHAR(64)  NULL DEFAULT NULL COMMENT 'E بلا معتمِدٍ = UNAPPROVED_SECONDARY',
  `approved_at`       DATE         NULL DEFAULT NULL,
  `decided_level`     ENUM('L1_ARCHITECTURE','L2_DOMAIN_OWNER','L3_GOVERNANCE','L4_OWNER')
                      NOT NULL DEFAULT 'L1_ARCHITECTURE',
  `created_at`        DATETIME     NOT NULL,
  PRIMARY KEY (`cd_id`),
  UNIQUE KEY `uq_ws_route` (`consumer_workspace`,`route`),
  KEY `ix_case` (`need_case`),
  KEY `ix_owner` (`owner_workspace`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT 'NAV-ARCH-02 §12 — لكلِّ ظهورٍ عابرٍ نوعُ احتياجٍ وعلاجٌ وبديلُ وصول'";
echo $conn->query($sql) ? "+ جدول nav_cross_domain_register\n"
                        : "x nav_cross_domain_register: " . $conn->error . "\n";

/* ═══ ⑤ ملءُ أعمدةِ §6 من القائمِ — ⛔ ولا تُنشأ مساحةٌ ولا يتغيّر صفّ ═══════ */
$map = array('DEPARTMENT' => 'DEPARTMENT', 'EXECUTIVE' => 'EXECUTIVE',
             'PERSONAL' => 'PERSONAL', 'PLATFORM_UTILITY' => 'PLATFORM_UTILITY');
$n = 0;
$r = $conn->query("SELECT workspace_id, kind, name_ar, dept_code FROM nav_workspaces");
while ($x = $r->fetch_assoc()) {
    $wid = $x['workspace_id'];
    /* IAF مسجَّلةٌ `DEPARTMENT` والدستورُ §6 يسمّيها `INDEPENDENT_ASSURANCE`
       «عند الحاجة للمراجعة الداخلية إذا كانت خارج الـ17» — وهي كذلك. */
    $type = ($wid === 'IAF') ? 'INDEPENDENT_ASSURANCE'
          : (isset($map[$x['kind']]) ? $map[$x['kind']] : 'DEPARTMENT');
    $st = $conn->prepare("UPDATE nav_workspaces
                             SET workspace_code = ?, canonical_name = ?, workspace_type = ?,
                                 owner_domain = ?, governing_source = ?
                           WHERE workspace_id = ?
                             AND (workspace_code IS NULL OR workspace_type IS NULL)");
    $own = ($x['dept_code'] !== null && $x['dept_code'] !== '') ? $x['dept_code'] : $wid;
    $gsrc = 'NAV-ARCH-02 §6 · nav_workspaces@' . $wid;
    $st->bind_param('ssssss', $wid, $x['name_ar'], $type, $own, $gsrc, $wid);
    $st->execute();
    $n += $st->affected_rows;
    $st->close();
}
echo "= ملء §6: {$n} مساحةً\n";

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
