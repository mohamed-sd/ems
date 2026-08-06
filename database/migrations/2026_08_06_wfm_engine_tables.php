<?php
/**
 * محرّك العمل الشخصي — الجداول السبعة عشر (WFM-01 §5-8 · WFM-080/081)
 * ───────────────────────────────────────────────────────────────────────────
 * DDL إضافيٌّ خالص (قرار ق-01 في docs/UPDATE0008_DECISIONS_LOG_ar.md):
 * سبعة عشر جدولًا جديدًا — **صفر ALTER على جدولٍ قائم** فقفل المخطط محفوظ.
 *
 * البنية الحاكمة في كل جدولٍ تشغيلي (M-00/M-14 §8 — الأعمدة السبعة):
 *   company_id (الكيان · EN-03) · created_by بصفته · created_at ·
 *   approved_by بصفته · approved_at · delegation_ref · parent_ref
 * والقاموسان request_types/request_routes مرجعيان مشتركان (قرار ق-03).
 *
 * حقول الربط الخمسة عشر (WFM-081) على work_items نصًّا، مع عمود التشغيل
 * الفعلي assigned_user_id إلى جانب الجسر assigned_person_id (قرار ق-02).
 *
 * حالات المهمة الخمس عشرة (الورقة 02) رموزًا لاتينية — گوتشا ENUM العربية:
 *   draft·scheduled·assigned·accepted·in_progress·blocked·done_pending_verify·
 *   closed_accepted·returned·rejected·cancelled·reassigned·delegated·overdue·reopened
 *
 * idempotent: CREATE TABLE IF NOT EXISTS · المحرّك InnoDB صراحةً (افتراض
 * الخادم MyISAM يفجّر القيود) · utf8mb4_unicode_ci صراحةً (توافق MariaDB).
 */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__, 2) . '/includes/env.php';
$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "اتصال المرحِّل فشل\n"); exit(1); }
$conn->set_charset('utf8mb4');
$conn->query("SET collation_connection = 'utf8mb4_unicode_ci'");

$TAIL = " ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
/** الأعمدة السبعة الحاكمة — تُلحق بكل جدولٍ تشغيلي */
$GOV = "
  created_by INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'المنشئ',
  created_capacity VARCHAR(60) NULL COMMENT 'صفة المنشئ لحظة الفعل',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  approved_by INT UNSIGNED NULL COMMENT 'المعتمِد',
  approved_capacity VARCHAR(60) NULL COMMENT 'صفة المعتمِد',
  approved_at DATETIME NULL,
  delegation_ref VARCHAR(60) NULL COMMENT 'مرجع التفويض إن اعتُمد به',
  parent_ref VARCHAR(60) NULL COMMENT 'المرجع الأب'";

function mk($conn, $name, $ddl) {
    if (!$conn->query($ddl)) { fwrite(STDERR, "تعذر {$name}: {$conn->error}\n"); exit(1); }
    echo "  + {$name}\n";
}

echo "── WFM: الجداول السبعة عشر\n";

/* ① work_items — عنصر العمل الموحد (مهمة · تكليف) والربط الخمسة عشر */
mk($conn, 'work_items', "CREATE TABLE IF NOT EXISTS work_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id INT UNSIGNED NOT NULL COMMENT 'الكيان — EN-03',
  item_type VARCHAR(12) NOT NULL DEFAULT 'task' COMMENT 'task|assignment',
  title VARCHAR(300) NOT NULL,
  details TEXT NULL,
  -- حقول الربط الخمسة عشر (WFM-081)
  source_type VARCHAR(12) NOT NULL COMMENT 'SRC-01..SRC-14 — لا مصدر خارجها',
  source_ref VARCHAR(120) NOT NULL COMMENT 'مرجع المستند/الواقعة/القرار المنشئ',
  source_screen VARCHAR(120) NULL COMMENT 'شاشة الأصل وملفها',
  action_code VARCHAR(60) NULL COMMENT 'رمز الفعل من NAV-09 إن اشتُق من فعل',
  event_ref VARCHAR(60) NULL,
  org_unit_id INT UNSIGNED NULL,
  project_id INT UNSIGNED NULL,
  site_id INT UNSIGNED NULL,
  assigned_person_id INT UNSIGNED NULL COMMENT 'جسر الهوية E-05 — يُشتق',
  assigned_role_id INT UNSIGNED NULL,
  due_at DATETIME NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'draft',
  completed_at DATETIME NULL,
  evidence_ref VARCHAR(200) NULL COMMENT 'دليل الإنجاز المرفوع',
  -- عقد السبعة (WF-02): مصدر ومالك ومنفذ ونطاق وموعد ومخرج ودليل إغلاق
  owner_user_id INT UNSIGNED NOT NULL COMMENT 'المالك',
  assigned_user_id INT UNSIGNED NULL COMMENT 'المنفذ الفعلي (users)',
  deliverable VARCHAR(300) NOT NULL COMMENT 'المخرَج المطلوب',
  evidence_required VARCHAR(200) NOT NULL DEFAULT 'أثر الفعل في سجل التدقيق' COMMENT 'دليل الإغلاق المطلوب',
  verifier_user_id INT UNSIGNED NULL COMMENT 'المتحقق — لا يُغلق أحدٌ مهمته (WF-04)',
  priority VARCHAR(4) NOT NULL DEFAULT 'P3' COMMENT 'P0..P4 — الورقة 05',
  response_due_at DATETIME NULL COMMENT 'مهلة الاستجابة',
  accepted_at DATETIME NULL,
  sla_paused_at DATETIME NULL,
  sla_pause_reason VARCHAR(60) NULL COMMENT 'من قائمة الأسباب الموقفة وحدها',
  escalation_level TINYINT UNSIGNED NOT NULL DEFAULT 0,
  reopened_of BIGINT UNSIGNED NULL COMMENT 'أعيد فتحها من',
  status_reason VARCHAR(300) NULL COMMENT 'سبب آخر انتقالٍ يشترط سببًا',
  closed_at DATETIME NULL,
  {$GOV},
  PRIMARY KEY (id),
  KEY ix_wi_co_status (company_id, status),
  KEY ix_wi_assignee (company_id, assigned_user_id, status),
  KEY ix_wi_owner (company_id, owner_user_id, status),
  KEY ix_wi_due (company_id, due_at),
  KEY ix_wi_source (source_type, source_ref)
){$TAIL} COMMENT='WFM-01: عنصر العمل — واجهة قراءة وتنفيذ لا مصدر بيانات'");

/* ② task_assignments — سجل الإسناد وتحولاته */
mk($conn, 'task_assignments', "CREATE TABLE IF NOT EXISTS task_assignments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id INT UNSIGNED NOT NULL,
  item_id BIGINT UNSIGNED NOT NULL,
  kind VARCHAR(16) NOT NULL COMMENT 'assign|reassign|delegate|transfer',
  from_user_id INT UNSIGNED NULL,
  to_user_id INT UNSIGNED NOT NULL,
  reason VARCHAR(300) NULL COMMENT 'إلزامي لإعادة الإسناد',
  {$GOV},
  PRIMARY KEY (id),
  KEY ix_ta_item (item_id),
  KEY ix_ta_to (company_id, to_user_id)
){$TAIL} COMMENT='WFM: تاريخ الإسناد — العدّ يستمر ولا يُصفَّر'");

/* ③ task_dependencies */
mk($conn, 'task_dependencies', "CREATE TABLE IF NOT EXISTS task_dependencies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id INT UNSIGNED NOT NULL,
  item_id BIGINT UNSIGNED NOT NULL,
  depends_on_item_id BIGINT UNSIGNED NOT NULL,
  dep_type VARCHAR(12) NOT NULL DEFAULT 'blocks',
  created_by INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_td (item_id, depends_on_item_id),
  KEY ix_td_dep (depends_on_item_id)
){$TAIL}");

/* ④ task_evidence — دليل الإنجاز (التصريح ادعاء والدليل إثبات) */
mk($conn, 'task_evidence', "CREATE TABLE IF NOT EXISTS task_evidence (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id INT UNSIGNED NOT NULL,
  item_id BIGINT UNSIGNED NOT NULL,
  kind VARCHAR(12) NOT NULL DEFAULT 'note' COMMENT 'file|link|record|note',
  ref VARCHAR(300) NOT NULL,
  note VARCHAR(400) NULL,
  created_by INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_te_item (item_id)
){$TAIL}");

/* ⑤ task_templates — قوالب المهام الدورية */
mk($conn, 'task_templates', "CREATE TABLE IF NOT EXISTS task_templates (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id INT UNSIGNED NOT NULL,
  code VARCHAR(30) NOT NULL,
  title VARCHAR(300) NOT NULL,
  details TEXT NULL,
  org_unit_id INT UNSIGNED NULL,
  owner_role_id INT UNSIGNED NULL COMMENT 'الدور المالك — تُعاد للدور لا للشخص',
  priority VARCHAR(4) NOT NULL DEFAULT 'P3',
  deliverable VARCHAR(300) NOT NULL,
  evidence_required VARCHAR(200) NOT NULL DEFAULT 'أثر الفعل في سجل التدقيق',
  active TINYINT(1) NOT NULL DEFAULT 1,
  {$GOV},
  PRIMARY KEY (id),
  UNIQUE KEY uq_tt (company_id, code)
){$TAIL} COMMENT='WFM: SRC-08 — المهمة الدورية تتولد بدوريتها من قالبها'");

/* ⑥ recurring_tasks — جدولة القوالب */
mk($conn, 'recurring_tasks', "CREATE TABLE IF NOT EXISTS recurring_tasks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id INT UNSIGNED NOT NULL,
  template_id INT UNSIGNED NOT NULL,
  freq VARCHAR(12) NOT NULL DEFAULT 'monthly' COMMENT 'daily|weekly|monthly|quarterly',
  day_key TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'يوم الأسبوع/الشهر بحسب النمط',
  next_run_at DATETIME NULL,
  last_run_at DATETIME NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_rt_next (active, next_run_at)
){$TAIL}");

/* ⑦ request_types — قاموس الأنواع الـ62 (مرجعي مشترك — قرار ق-03) */
mk($conn, 'request_types', "CREATE TABLE IF NOT EXISTS request_types (
  code VARCHAR(12) NOT NULL COMMENT 'RQ-HR-01…',
  name_ar VARCHAR(160) NOT NULL,
  owner_dept VARCHAR(80) NOT NULL COMMENT 'الإدارة المالكة',
  submitter VARCHAR(160) NOT NULL COMMENT 'من يقدّمه',
  receiver VARCHAR(160) NOT NULL COMMENT 'من يستقبله — صفر طلب بلا جهة',
  approval_chain VARCHAR(300) NOT NULL,
  sla_hours INT UNSIGNED NOT NULL DEFAULT 72,
  deliverable VARCHAR(200) NOT NULL COMMENT 'المخرَج الناتج',
  source_ref VARCHAR(160) NOT NULL COMMENT 'مرجع الدورة في وثيقة الإدارة',
  status VARCHAR(16) NOT NULL DEFAULT 'active' COMMENT 'active|proposed|retired',
  display_order SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  PRIMARY KEY (code)
){$TAIL} COMMENT='WFM الورقة 04 — تُستخرج من الدورات ولا تُخترع'");

/* ⑧ request_routes — مصفوفة التوجيه (الورقة 07 · مرجعي مشترك) */
mk($conn, 'request_routes', "CREATE TABLE IF NOT EXISTS request_routes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  item_kind VARCHAR(20) NOT NULL COMMENT 'nav_action|approval|request|ticket|recurring|escalation',
  trigger_key VARCHAR(60) NOT NULL DEFAULT '*' COMMENT 'رمز النوع/الفعل أو * للقاعدة العامة',
  rule_text VARCHAR(300) NOT NULL COMMENT 'قاعدة التوجيه المعلنة — تفسير الظهور ②',
  receiver_dept VARCHAR(80) NOT NULL,
  receiver_role VARCHAR(120) NOT NULL,
  fallback_role VARCHAR(120) NOT NULL COMMENT 'البديل عند الغياب',
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rr (item_kind, trigger_key)
){$TAIL} COMMENT='WFM: التوجيه بقاعدة لا باجتهاد — واليدوي استثناء يُسجَّل'");

/* ⑨ requests — الطلب (WI-REQ) */
mk($conn, 'requests', "CREATE TABLE IF NOT EXISTS requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id INT UNSIGNED NOT NULL,
  request_no VARCHAR(20) NULL COMMENT 'REQ-000001',
  request_type_code VARCHAR(12) NOT NULL,
  requester_user_id INT UNSIGNED NOT NULL,
  beneficiary_ref VARCHAR(60) NULL COMMENT 'المستفيد إن خالف المقدّم',
  org_unit_id INT UNSIGNED NULL,
  project_id INT UNSIGNED NULL,
  site_id INT UNSIGNED NULL,
  title VARCHAR(300) NOT NULL,
  fields_json MEDIUMTEXT NULL COMMENT 'حقول النموذج المشتقة من الصفة والعقد',
  status VARCHAR(24) NOT NULL DEFAULT 'draft'
    COMMENT 'draft|submitted|routed|in_approval|approved|rejected|executing|executed|closed|returned|cancelled',
  current_holder_user_id INT UNSIGNED NULL COMMENT 'من هو عنده الآن — AC-WFM-07',
  current_step SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  submitted_at DATETIME NULL,
  sla_due_at DATETIME NULL,
  executed_at DATETIME NULL,
  closed_at DATETIME NULL,
  status_reason VARCHAR(300) NULL,
  {$GOV},
  PRIMARY KEY (id),
  KEY ix_rq_type (request_type_code, status),
  KEY ix_rq_requester (company_id, requester_user_id, status),
  KEY ix_rq_holder (company_id, current_holder_user_id, status)
){$TAIL} COMMENT='WFM: الطلب يُقدَّم قصدًا — وصفر طلب لا يُعرف أين توقف'");

/* ⑩ request_responses — مسار الرد التسعة (WFM-064) أعمدةً لا نصًّا */
mk($conn, 'request_responses', "CREATE TABLE IF NOT EXISTS request_responses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id INT UNSIGNED NOT NULL,
  request_id BIGINT UNSIGNED NOT NULL,
  decision VARCHAR(24) NOT NULL COMMENT '① القرار',
  decided_by INT UNSIGNED NOT NULL COMMENT '② من قرّر',
  decided_capacity VARCHAR(60) NULL COMMENT '③ صفته',
  decided_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '④ تاريخه',
  notes VARCHAR(400) NULL COMMENT '⑤ الملاحظات',
  action_required VARCHAR(300) NULL COMMENT '⑥ ما يجب فعله',
  result_doc_ref VARCHAR(200) NULL COMMENT '⑦ المستند الناتج',
  executed_summary VARCHAR(300) NULL COMMENT '⑧ التنفيذ الذي تم',
  next_step VARCHAR(200) NULL COMMENT '⑨ الخطوة اللاحقة',
  origin_link VARCHAR(200) NOT NULL DEFAULT '' COMMENT 'رابط الأصل',
  PRIMARY KEY (id),
  KEY ix_rr_req (request_id)
){$TAIL} COMMENT='WF-05: الطلب لا يُغلق بتغيير حالة — تسعة عناصر تصل مقدّمه'");

/* ⑪ approval_links — الموافقة عنصرًا (WI-APR) فوق خطوات الاعتماد */
mk($conn, 'approval_links', "CREATE TABLE IF NOT EXISTS approval_links (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id INT UNSIGNED NOT NULL,
  source_kind VARCHAR(30) NOT NULL COMMENT 'request|document|…',
  source_ref VARCHAR(60) NOT NULL,
  action_code VARCHAR(60) NOT NULL COMMENT 'رمز فعل الاعتماد — الورقة 09',
  step_no SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  approver_user_id INT UNSIGNED NULL,
  approver_role VARCHAR(120) NULL COMMENT 'أو دور مستقبِل يُحل وقت العرض',
  status VARCHAR(16) NOT NULL DEFAULT 'pending' COMMENT 'pending|approved|returned|rejected|withdrawn',
  sla_due_at DATETIME NULL,
  decided_at DATETIME NULL,
  decision_note VARCHAR(400) NULL,
  {$GOV},
  PRIMARY KEY (id),
  UNIQUE KEY uq_al (source_kind, source_ref, action_code, step_no),
  KEY ix_al_approver (company_id, approver_user_id, status)
){$TAIL} COMMENT='WFM: صفر موافقة بلا صلاحية ونطاق — تُقرأ من E-04'");

/* ⑫ work_delegations — الأنواع الستة (الورقة 08) */
mk($conn, 'work_delegations', "CREATE TABLE IF NOT EXISTS work_delegations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id INT UNSIGNED NOT NULL,
  kind VARCHAR(20) NOT NULL COMMENT 'task_assign|role_assign|deputize|delegate_approval|reassign|workload_move',
  from_user_id INT UNSIGNED NOT NULL,
  to_user_id INT UNSIGNED NOT NULL,
  scope_ref VARCHAR(160) NOT NULL COMMENT 'المهمة/الدور/نوع المستند — لا تفويض مفتوح النطاق',
  cap_amount DECIMAL(14,2) NULL COMMENT 'سقف تفويض الاعتماد',
  cap_currency VARCHAR(3) NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL COMMENT 'لا تفويض مفتوح المدة',
  status VARCHAR(12) NOT NULL DEFAULT 'active' COMMENT 'active|ended|revoked',
  effect_on_open VARCHAR(200) NOT NULL DEFAULT 'تعود للأصل فورًا بانتهائها',
  approval_ref VARCHAR(60) NULL COMMENT 'جهة الموافقة — الحوكمة',
  {$GOV},
  PRIMARY KEY (id),
  KEY ix_wd_to (company_id, to_user_id, status),
  KEY ix_wd_window (status, starts_at, ends_at)
){$TAIL} COMMENT='WF-08: انتهاء التفويض يوقف التوليد ولا يلغي المفتوح'");

/* ⑬ work_escalations */
mk($conn, 'work_escalations', "CREATE TABLE IF NOT EXISTS work_escalations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id INT UNSIGNED NOT NULL,
  item_kind VARCHAR(16) NOT NULL COMMENT 'work_item|request|approval|ticket',
  item_ref BIGINT UNSIGNED NOT NULL,
  from_user_id INT UNSIGNED NULL,
  to_user_id INT UNSIGNED NOT NULL,
  level TINYINT UNSIGNED NOT NULL DEFAULT 1,
  reason VARCHAR(24) NOT NULL DEFAULT 'sla_response' COMMENT 'sla_response|sla_completion|manual|risk',
  note VARCHAR(300) NULL,
  escalated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  company_scope VARCHAR(60) NULL,
  created_by INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_we_item (item_kind, item_ref),
  KEY ix_we_open (company_id, resolved_at)
){$TAIL} COMMENT='AC-WFM-09: صفر مهمة متأخرة بلا تصعيد'");

/* ⑭ achievement_records — الإنجاز يُشتق ولا يُدخَل (WF-03) */
mk($conn, 'achievement_records', "CREATE TABLE IF NOT EXISTS achievement_records (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id INT UNSIGNED NOT NULL,
  source_kind VARCHAR(24) NOT NULL
    COMMENT 'task|request|approval|work_order|unit|claim|ticket|corrective — الثمانية حصرًا',
  source_ref VARCHAR(60) NOT NULL,
  person_user_id INT UNSIGNED NOT NULL,
  attribution VARCHAR(12) NOT NULL DEFAULT 'executive' COMMENT 'executive|supervisory|decision',
  weight_pct DECIMAL(5,2) NOT NULL DEFAULT 100.00,
  title VARCHAR(300) NOT NULL,
  evidence_ref VARCHAR(200) NOT NULL COMMENT 'صفر إنجاز بلا دليل — AC-WFM-05',
  recognized_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reversed_at DATETIME NULL COMMENT 'يُعكس آليًّا إن عُكس أصله — AC-WFM-14',
  reverse_reason VARCHAR(300) NULL,
  event_ref VARCHAR(60) NULL,
  created_by INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'محرّك الإنجاز — لا إدخال يدوي',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ach (source_kind, source_ref, person_user_id, attribution),
  KEY ix_ach_person (company_id, person_user_id, recognized_at)
){$TAIL} COMMENT='WF-03: منع التضاعف بنيوي بالمفتاح الفريد'");

/* ⑮ achievement_attributions — نسب المساهمين حين تعدد الأيدي (قرار 7) */
mk($conn, 'achievement_attributions', "CREATE TABLE IF NOT EXISTS achievement_attributions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id INT UNSIGNED NOT NULL,
  work_item_ref VARCHAR(60) NOT NULL,
  person_user_id INT UNSIGNED NOT NULL,
  share_pct DECIMAL(5,2) NOT NULL,
  share_kind VARCHAR(12) NOT NULL DEFAULT 'executive',
  decided_by INT UNSIGNED NOT NULL COMMENT 'المكلِّف يقررها عند الإغلاق',
  decided_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_aa (work_item_ref, person_user_id, share_kind)
){$TAIL}");

/* ⑯ personal_notifications — التنبيه (WI-NTF) وقاعدة WF-06 */
mk($conn, 'personal_notifications', "CREATE TABLE IF NOT EXISTS personal_notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  kind VARCHAR(24) NOT NULL DEFAULT 'info',
  title VARCHAR(300) NOT NULL,
  body VARCHAR(600) NULL,
  link VARCHAR(300) NULL COMMENT 'رابط الأصل بضغطة واحدة',
  requires_action TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'WF-06',
  task_item_id BIGINT UNSIGNED NULL COMMENT 'المهمة المولَّدة إن تطلب فعلًا — AC-WFM-08',
  read_at DATETIME NULL,
  expires_at DATETIME NULL,
  created_by INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_pn_user (company_id, user_id, read_at),
  KEY ix_pn_action (requires_action, task_item_id)
){$TAIL} COMMENT='WFM: التنبيه إحاطة — ولا يصير مهمة إلا بفعل مطلوب'");

/* ⑰ workspace_views — عروض مساحة عملي الثمانية (WFM-066) */
mk($conn, 'workspace_views', "CREATE TABLE IF NOT EXISTS workspace_views (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  screen VARCHAR(40) NOT NULL COMMENT 'my_tasks|my_requests|…',
  view_key VARCHAR(40) NOT NULL COMMENT 'today|late|upcoming|blocked|returned|delegated|assigned_by_me|team',
  filters_json TEXT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wv (user_id, screen, view_key)
){$TAIL}");

echo "✔ اكتملت جداول WFM السبعة عشر\n";
$conn->close();
exit(0);
