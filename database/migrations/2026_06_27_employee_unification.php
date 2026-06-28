<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * Migration: توحيد كيان الموظف (Employee Unification) — 2026-06-27
 * ───────────────────────────────────────────────────────────────────────────
 * يدمج worker_profile داخل employees (المصدر الوحيد)، ويحذفه، ويعيد توجيه كل
 * جداول worker_* من worker_id (=worker_profile.id) إلى employee_id (=employees.id)،
 * وينشئ نظام المسميات الوظيفية (job_titles) وأدوار الموظفين (employee_roles)
 * وجدول السائقين/المشغلين (equipment_operators).
 *
 * ⚠️ مُلِف الأدوار: employee_roles منفصلٌ تماماً عن جدول roles (أدوار المستخدمين/الصلاحيات).
 *
 * idempotent: آمنٌ لإعادة التشغيل. التشغيل:
 *   php database/migrations/2026_06_27_employee_unification.php
 * النسخ الاحتياطية تُؤخذ تلقائياً قبل أي تغييرٍ على worker_profile/employees.
 * ═══════════════════════════════════════════════════════════════════════════
 */
mysqli_report(MYSQLI_REPORT_OFF);
$DB = ['localhost','root','','equipation_manage'];
$conn = new mysqli($DB[0],$DB[1],$DB[2],$DB[3]);
if ($conn->connect_error) { die("CONN FAIL: ".$conn->connect_error."\n"); }
$conn->set_charset("utf8mb4");
$conn->query("SET collation_connection='utf8mb4_unicode_ci'");

function hascol($c,$t,$col){ $r=@$c->query("SHOW COLUMNS FROM `$t` LIKE '".$c->real_escape_string($col)."'"); return $r&&$r->num_rows>0; }
function hastbl($c,$t){ $r=@$c->query("SHOW TABLES LIKE '".$c->real_escape_string($t)."'"); return $r&&$r->num_rows>0; }
function fkex($c,$t,$n){ $r=$c->query("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='".$c->real_escape_string($t)."' AND CONSTRAINT_NAME='".$c->real_escape_string($n)."' AND CONSTRAINT_TYPE='FOREIGN KEY'"); return $r&&$r->num_rows>0; }
function addcol($c,$t,$col,$ddl){ if(hascol($c,$t,$col)) return; $c->query("ALTER TABLE `$t` ADD COLUMN $ddl") or print("  ! add $t.$col: ".$c->error."\n"); }
function run($c,$sql){ return $c->query($sql); }

if (!hastbl($conn,'employees')) { die("employees table missing — abort.\n"); }

echo "[1] backups\n";
if (hastbl($conn,'worker_profile')) {
  @$conn->query("DROP TABLE IF EXISTS `_bak_unify_worker_profile`");
  $conn->query("CREATE TABLE `_bak_unify_worker_profile` LIKE `worker_profile`");
  $conn->query("INSERT INTO `_bak_unify_worker_profile` SELECT * FROM `worker_profile`");
}

echo "[2] lookup + operator tables\n";
run($conn,"CREATE TABLE IF NOT EXISTS `job_titles` (
  `id` INT NOT NULL AUTO_INCREMENT, `company_id` INT NULL, `name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NULL, `is_operator` TINYINT(1) NOT NULL DEFAULT 0,
  `status` TINYINT(1) NOT NULL DEFAULT 1, `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_jobtitle_company_name` (`company_id`,`name`),
  KEY `idx_jobtitle_company` (`company_id`), KEY `idx_jobtitle_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
run($conn,"CREATE TABLE IF NOT EXISTS `employee_roles` (
  `id` INT NOT NULL AUTO_INCREMENT, `company_id` INT NULL, `name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NULL, `status` TINYINT(1) NOT NULL DEFAULT 1, `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_emprole_company_name` (`company_id`,`name`),
  KEY `idx_emprole_company` (`company_id`), KEY `idx_emprole_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
run($conn,"CREATE TABLE IF NOT EXISTS `equipment_operators` (
  `id` INT NOT NULL AUTO_INCREMENT, `company_id` INT NULL, `employee_id` INT NOT NULL,
  `license_number` VARCHAR(100) NULL, `license_type` VARCHAR(100) NULL, `license_grade` VARCHAR(40) NULL,
  `license_issuer` VARCHAR(255) NULL, `license_issue_date` DATE NULL, `license_expiry_date` DATE NULL,
  `license_photo` VARCHAR(255) NULL, `operating_categories` MEDIUMTEXT NULL, `driving_authorizations` VARCHAR(255) NULL,
  `medical_report_path` VARCHAR(255) NULL, `status` TINYINT(1) NOT NULL DEFAULT 1, `notes` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_operator_employee` (`employee_id`), KEY `idx_operator_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

echo "[3] employees columns (merged worker_profile + FK columns)\n";
addcol($conn,'employees','job_title_id',"`job_title_id` INT NULL");
addcol($conn,'employees','employee_role_id',"`employee_role_id` INT NULL");
addcol($conn,'employees','is_workforce',"`is_workforce` TINYINT(1) NOT NULL DEFAULT 0");
addcol($conn,'employees','worker_category',"`worker_category` VARCHAR(40) NULL");
addcol($conn,'employees','source_type',"`source_type` ENUM('شركة','مورد','مقاول') NULL");
addcol($conn,'employees','workforce_class',"`workforce_class` ENUM('أساسي','احتياطي','بديل مؤقت','تغطية إجازة','تجاري مؤقت') NULL");
addcol($conn,'employees','job_grade',"`job_grade` VARCHAR(40) NULL");
addcol($conn,'employees','workforce_state',"`workforce_state` ENUM('مرشّح','مسجّل','مؤهّل','متعاقد','مخصّص','في إجازة','منتهٍ') NULL");
addcol($conn,'employees','medical_fitness_status',"`medical_fitness_status` ENUM('لائق للعمل','لائق بشروط','موقوف طبيًّا','يحتاج إعادة تقييم') NULL");
addcol($conn,'employees','fitness_conditions',"`fitness_conditions` VARCHAR(255) NULL");
addcol($conn,'employees','primary_backup_id',"`primary_backup_id` INT NULL");
addcol($conn,'employees','is_replaceable',"`is_replaceable` TINYINT(1) NULL DEFAULT 1");
addcol($conn,'employees','worker_code',"`worker_code` VARCHAR(50) NULL");
foreach ([['idx_emp_job_title','job_title_id'],['idx_emp_role','employee_role_id'],['idx_emp_is_workforce','is_workforce']] as $ix){
  $r=$conn->query("SHOW INDEX FROM employees WHERE Key_name='".$ix[0]."'");
  if ($r && $r->num_rows==0) run($conn,"ALTER TABLE employees ADD KEY `".$ix[0]."` (`".$ix[1]."`)");
}

echo "[4] seed job_titles + employee_roles (global)\n";
$titles=['مدير'=>0,'مهندس'=>0,'فني'=>0,'كهربائي'=>0,'مراقب'=>0,'عامل مساندة'=>0,'سائق'=>1,'مشغل'=>1,
  'سائق/مشغّل'=>1,'مساعد'=>1,'مبنشر'=>1,'مشرف'=>0,'إداري'=>0,'فني ورشة'=>0,'أمن'=>0,'أخرى'=>0];
$so=0; foreach($titles as $n=>$op){ $so+=10;
  $st=$conn->prepare("INSERT INTO job_titles (company_id,name,is_operator,status,sort_order) SELECT NULL,?,?,1,? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM job_titles WHERE company_id IS NULL AND name=?)");
  $st->bind_param('siis',$n,$op,$so,$n); $st->execute(); $st->close(); }
$roles=['مشغّل/سائق','سائق/مشغّل','فني','مهندس','مشرف','مراقب','عمالة مساندة','إداري'];
$so=0; foreach($roles as $n){ $so+=10;
  $st=$conn->prepare("INSERT INTO employee_roles (company_id,name,status,sort_order) SELECT NULL,?,1,? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM employee_roles WHERE company_id IS NULL AND name=?)");
  $st->bind_param('sis',$n,$so,$n); $st->execute(); $st->close(); }

echo "[5] backfill job_title_id from employee_type\n";
run($conn,"UPDATE employees e JOIN job_titles jt ON jt.company_id IS NULL AND jt.name=e.employee_type SET e.job_title_id=jt.id WHERE e.job_title_id IS NULL AND e.employee_type IS NOT NULL AND e.employee_type<>''");

if (hastbl($conn,'worker_profile')) {
  echo "[6] migrate worker_profile -> employees\n";
  run($conn,"UPDATE employees e JOIN worker_profile wp ON wp.employee_id=e.id SET
      e.is_workforce=1, e.worker_category=COALESCE(e.worker_category,wp.worker_category), e.source_type=COALESCE(e.source_type,wp.source_type),
      e.workforce_class=COALESCE(e.workforce_class,wp.workforce_class), e.job_grade=COALESCE(e.job_grade,wp.job_grade),
      e.workforce_state=COALESCE(e.workforce_state,wp.state), e.medical_fitness_status=COALESCE(e.medical_fitness_status,wp.medical_fitness_status),
      e.fitness_conditions=COALESCE(e.fitness_conditions,wp.fitness_conditions), e.is_replaceable=COALESCE(e.is_replaceable,wp.is_replaceable),
      e.worker_code=COALESCE(e.worker_code,wp.code), e.supplier_id=COALESCE(e.supplier_id,wp.supplier_id), e.general_notes=COALESCE(e.general_notes,wp.notes)");
  run($conn,"UPDATE employees e JOIN worker_profile wp ON wp.employee_id=e.id JOIN worker_profile bp ON bp.id=wp.primary_backup_id SET e.primary_backup_id=bp.employee_id WHERE wp.primary_backup_id IS NOT NULL");
}
run($conn,"UPDATE employees e JOIN employee_roles r ON r.company_id IS NULL AND r.name=e.worker_category SET e.employee_role_id=r.id WHERE e.employee_role_id IS NULL AND e.worker_category IS NOT NULL AND e.worker_category<>''");

echo "[7] migrate operator licenses -> equipment_operators\n";
// المسمّى التشغيليّ فقط (سائق/مشغّل)؛ لا يُدرج موظف بمسمّى إداريّ حتى لو كان لديه رخصة.
run($conn,"INSERT INTO equipment_operators (company_id,employee_id,license_number,license_type,license_grade,license_issuer,license_issue_date,license_expiry_date,license_photo,operating_categories,medical_report_path,status)
  SELECT e.company_id,e.id,e.license_number,e.license_type,e.license_grade,e.license_issuer,e.license_issue_date,e.license_expiry_date,e.license_photo,e.specialized_equipment,e.medical_report_path,1
  FROM employees e LEFT JOIN job_titles jt ON jt.id=e.job_title_id
  WHERE NOT EXISTS (SELECT 1 FROM equipment_operators o WHERE o.employee_id=e.id)
    AND COALESCE(jt.is_operator,0)=1");

echo "[8] repoint worker_* : drop worker_profile FKs, remap, rename, add employees FKs\n";
$dropfks=['worker_allocation'=>['fk_wa_worker'],'worker_backup'=>['fk_wb_backup','fk_wb_worker'],'worker_contract'=>['fk_wc_worker'],
  'worker_evaluation'=>['fk_we_worker'],'worker_leave_absence'=>['fk_wla_worker'],'worker_movement'=>['fk_wm_worker'],
  'worker_profile'=>['fk_wp_primary_backup'],'worker_qualification'=>['fk_wq_worker'],'worker_restricted_site'=>['fk_wrs_worker'],'worker_settlement'=>['fk_ws_worker']];
foreach($dropfks as $t=>$fks){ if(!hastbl($conn,$t)) continue; foreach($fks as $fk){ if(fkex($conn,$t,$fk)) run($conn,"ALTER TABLE `$t` DROP FOREIGN KEY `$fk`"); } }

// build map + remap (only if worker_profile still present this run)
if (hastbl($conn,'worker_profile')) {
  $map=[]; $r=$conn->query("SELECT id,employee_id FROM worker_profile"); while($x=$r->fetch_assoc())$map[(int)$x['id']]=(int)$x['employee_id'];
  $remap=['worker_qualification'=>['worker_id'],'worker_backup'=>['worker_id','backup_worker_id'],'worker_restricted_site'=>['worker_id'],
    'worker_contract'=>['worker_id','planned_backup_id'],'worker_allocation'=>['worker_id','active_backup_id'],'worker_evaluation'=>['worker_id'],
    'worker_leave_absence'=>['worker_id','substitute_id'],'worker_movement'=>['worker_id'],'worker_settlement'=>['worker_id']];
  foreach($remap as $t=>$cs){ if(!hastbl($conn,$t)) continue; foreach($cs as $c){ if(!hascol($conn,$t,$c)) continue; foreach($map as $o=>$n){ $conn->query("UPDATE `$t` SET `$c`=$n WHERE `$c`=$o"); } } }
}
// rename worker_id->employee_id, backup_worker_id->backup_employee_id
$ren=['worker_qualification'=>[['worker_id','employee_id']],'worker_backup'=>[['worker_id','employee_id'],['backup_worker_id','backup_employee_id']],
  'worker_restricted_site'=>[['worker_id','employee_id']],'worker_contract'=>[['worker_id','employee_id']],'worker_allocation'=>[['worker_id','employee_id']],
  'worker_evaluation'=>[['worker_id','employee_id']],'worker_leave_absence'=>[['worker_id','employee_id']],'worker_movement'=>[['worker_id','employee_id']],'worker_settlement'=>[['worker_id','employee_id']]];
foreach($ren as $t=>$rs){ if(!hastbl($conn,$t)) continue; foreach($rs as $r){ list($o,$n)=$r; if(hascol($conn,$t,$n)||!hascol($conn,$t,$o)) continue; run($conn,"ALTER TABLE `$t` RENAME COLUMN `$o` TO `$n`"); } }
// add employees FKs
$addfks=['worker_qualification'=>['fk_wq_emp','employee_id'],'worker_restricted_site'=>['fk_wrs_emp','employee_id'],'worker_contract'=>['fk_wc_emp','employee_id'],
  'worker_allocation'=>['fk_wa_emp','employee_id'],'worker_evaluation'=>['fk_we_emp','employee_id'],'worker_leave_absence'=>['fk_wla_emp','employee_id'],
  'worker_movement'=>['fk_wm_emp','employee_id'],'worker_settlement'=>['fk_ws_emp','employee_id'],'worker_backup'=>['fk_wb_emp','employee_id']];
foreach($addfks as $t=>$d){ list($fk,$col)=$d; if(!hastbl($conn,$t)||fkex($conn,$t,$fk)) continue; run($conn,"ALTER TABLE `$t` ADD CONSTRAINT `$fk` FOREIGN KEY (`$col`) REFERENCES `employees`(`id`) ON DELETE CASCADE ON UPDATE CASCADE"); }
if (hastbl($conn,'worker_backup') && !fkex($conn,'worker_backup','fk_wb_backup_emp'))
  run($conn,"ALTER TABLE `worker_backup` ADD CONSTRAINT `fk_wb_backup_emp` FOREIGN KEY (`backup_employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE ON UPDATE CASCADE");

echo "[9] recreate views on employees\n";
run($conn,"CREATE OR REPLACE SQL SECURITY DEFINER VIEW `v_worker_billable_hours` AS
  SELECT wp.id AS employee_id, t.date AS work_date, CAST(t.operator AS UNSIGNED) AS operation_id,
    COALESCE(SUM(t.executed_hours),0) AS productive_hours, COALESCE(SUM(t.standby_hours),0) AS standby_hours,
    COALESCE(SUM(t.hr_fault),0) AS worker_downtime, COALESCE(SUM(t.maintenance_fault),0) AS maintenance_downtime,
    GREATEST(COALESCE(SUM(t.executed_hours),0)+COALESCE(SUM(t.standby_hours),0)-COALESCE(SUM(t.hr_fault),0),0) AS billable_baseline
  FROM employees wp JOIN timesheet t ON CAST(t.employee_id AS UNSIGNED)=wp.id WHERE wp.is_workforce=1
  GROUP BY wp.id, t.date, CAST(t.operator AS UNSIGNED)");
run($conn,"CREATE OR REPLACE SQL SECURITY DEFINER VIEW `v_worker_presence` AS
  SELECT wp.id AS employee_id, CASE
    WHEN wp.workforce_state='منتهٍ' THEN 'منتهٍ'
    WHEN EXISTS(SELECT 1 FROM worker_leave_absence la WHERE la.employee_id=wp.id AND la.state IN ('معتمد','مفتوح','مُغطًّى') AND (la.date_from IS NULL OR la.date_from<=CURDATE()) AND (la.date_to IS NULL OR la.date_to>=CURDATE())) THEN 'خارج الموقع/إجازة'
    WHEN EXISTS(SELECT 1 FROM worker_movement m WHERE m.employee_id=wp.id AND m.state IN ('أمرٌ صادر','في الطريق')) THEN 'في الطريق'
    WHEN EXISTS(SELECT 1 FROM worker_allocation a WHERE a.employee_id=wp.id AND a.state='نشط') THEN 'داخل الموقع'
    WHEN EXISTS(SELECT 1 FROM worker_allocation a WHERE a.employee_id=wp.id AND a.state IN ('مخطّط','معتمد')) THEN 'بانتظار التحرّك'
    ELSE 'بانتظار التخصيص' END AS presence_state
  FROM employees wp WHERE wp.is_workforce=1");
run($conn,"CREATE OR REPLACE SQL SECURITY DEFINER VIEW `v_worker_worklog` AS
  SELECT wp.id AS employee_id, wp.name AS worker_name, wp.worker_category AS worker_category, wp.workforce_state AS worker_state,
    (SELECT COUNT(DISTINCT a.operation_id) FROM worker_allocation a WHERE a.employee_id=wp.id) AS operations_count,
    (SELECT COALESCE(SUM(b.billable_baseline),0) FROM v_worker_billable_hours b WHERE b.employee_id=wp.id) AS total_billable_hours,
    (SELECT COUNT(0) FROM worker_leave_absence la WHERE la.employee_id=wp.id) AS leave_absence_count,
    (SELECT COUNT(0) FROM worker_movement m WHERE m.employee_id=wp.id) AS movement_count,
    (SELECT COUNT(0) FROM worker_evaluation ev WHERE ev.employee_id=wp.id) AS evaluation_count,
    (SELECT COALESCE(SUM(ev.amount),0) FROM worker_evaluation ev WHERE ev.employee_id=wp.id AND ev.incentive_penalty_type='حافز') AS incentive_total,
    (SELECT COALESCE(SUM(ev.amount),0) FROM worker_evaluation ev WHERE ev.employee_id=wp.id AND ev.incentive_penalty_type='جزاء') AS penalty_total
  FROM employees wp WHERE wp.is_workforce=1");

echo "[10] register modules (job_titles, employee_roles) for HR role 4\n";
$mods=[['المسميات الوظيفية','Employees/job_titles.php','fa fa-user-tag',11],['أدوار الموظفين','Employees/employee_roles.php','fa fa-people-arrows',12],['السائقون والمشغّلون','Employees/equipment_operators.php','fa fa-id-card-clip',13]];
foreach($mods as $m){ list($name,$code,$icon,$ord)=$m;
  $r=$conn->query("SELECT id FROM modules WHERE code='".$conn->real_escape_string($code)."' LIMIT 1");
  if($r && $r->num_rows){ $mid=$r->fetch_assoc()['id']; }
  else { $st=$conn->prepare("INSERT INTO modules (name,code,owner_role_id,is_link,icon,display_order) VALUES (?,?,4,'1',?,?)"); $st->bind_param('sssi',$name,$code,$icon,$ord); $st->execute(); $mid=$st->insert_id; $st->close(); }
  $c=$conn->query("SELECT id FROM role_permissions WHERE role_id=4 AND module_id=$mid LIMIT 1");
  if(!($c && $c->num_rows)) $conn->query("INSERT INTO role_permissions (role_id,module_id,can_view,can_add,can_edit,can_delete) VALUES (4,$mid,1,1,1,1)");
}

echo "[11] drop worker_profile (no inbound FKs remain)\n";
if (hastbl($conn,'worker_profile')) {
  $r=$conn->query("SELECT COUNT(*) n FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME='worker_profile'");
  if ((int)$r->fetch_assoc()['n']===0) run($conn,"DROP TABLE `worker_profile`");
  else echo "  ! worker_profile still referenced — not dropped\n";
}

echo "DONE — employee unification applied.\n";
