<?php
/**
 * tools/lib/repair01_w3_scan.php — القياسُ المشترَكُ للمرحلةِ الثالثة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مكتبةُ قياسٍ لا مكتبةُ قراءة**: كلُّ دالّةٍ هنا **تشتقُّ** رقمَها من
 *   المخطَّطِ الحيِّ أو من الصفوفِ الحيّةِ — ولا واحدةٌ تقرأ جدولَ المرحلةِ
 *   نفسَه. فالأداةُ تكتب بها، والبوّابةُ تعيد القياسَ بها، فلا تقرأ البوّابةُ
 *   مخرَجَ الأداةِ التي تفحصها (‏_CONTEXT §قواعد القياس ١).
 *
 * ◆ **وتعريفُ «المعرّفِ البديل» مقيسٌ لا موصوف**: جدولٌ يسمّي حقيقةً أمًّا
 *   بنصٍّ **ولا يحمل مفتاحَها** يُعرِّفها بمعرّفٍ بديل. والحكمُ عليه يُشتقُّ من
 *   ثلاثةِ أرقامٍ حيّةٍ: صفوفُه · صفوفُه المبذورة · صفوفُه التي **تجد مرجعَها**
 *   في الجدولِ المالك. فوسمُ جدولٍ حيٍّ بأنّه «بذرةٌ بلا مرجع» **يسقط** لأنَّ
 *   القياسَ يكذّبه — لا لأنَّ أحدًا راجعه.
 *
 * ◆ **والمفاتيحُ الثلاثةَ عشرَ إعلانٌ من الوثيقةِ لا اشتقاقٌ من المخطَّط**:
 *   أسماؤها في §٤-٢ من ملفِّ المرحلة، ومالكُها **يُقاس** (الجدولُ موجودٌ؟
 *   العمودُ مفتاحٌ أساسيّ؟ يحمل `company_id`؟) — فالإعلانُ يحدّد ما يُقاس،
 *   والقياسُ يحدّد الحكم.
 * ═══════════════════════════════════════════════════════════════════════════
 */

/** المفاتيحُ الثلاثةَ عشرَ بترتيبِ §٤-٢ حرفًا — الإعلانُ، والمالكُ يُقاس بعدَه. */
function repair01_w3_keys()
{
    return array(
        /* key_code => [ar, seq, owner_table, owner_column, owner_dept, company_scope, company_column, is_master] */
        'Company_ID'              => array('الكيان القانوني',        1,  'admin_companies',   'id',         'EX-CEO', 'ROOT',   '',           1),
        'Project_ID'              => array('المشروع',                 2,  'project',           'id',         'DEP-01', 'SCOPED', 'company_id', 1),
        'Site_ID'                 => array('الموقع',                  3,  'sites',             'id',         'DEP-12', 'SCOPED', 'company_id', 1),
        'Unit_ID'                 => array('وحدة العمل التشغيلية',    4,  'unit_entries',      'id',         'DEP-11', 'SCOPED', 'company_id', 1),
        'Asset_ID'                => array('الأصل/المعدة',            5,  'equipments',        'id',         'DEP-04', 'SCOPED', 'company_id', 1),
        'Person_ID'               => array('الشخص',                   6,  'employees',         'id',         'DEP-07', 'SCOPED', 'company_id', 1),
        'Workforce_Assignment_ID' => array('التكليف التنظيمي',        7,  'org_assignments',   'asg_id',     'DEP-13', 'SCOPED', 'company_id', 1),
        'Shift_ID'                => array('نمط الوردية',             8,  'shift_patterns',    'pattern_id', 'DEP-12', 'SCOPED', 'company_id', 1),
        'Timesheet_ID'            => array('التايم شيت',              9,  'timesheet',         'id',         'DEP-12', 'SCOPED', 'company_id', 0),
        'Maintenance_Order_ID'    => array('أمر الصيانة',            10,  'mnt_order',         'id',         'DEP-14', 'SCOPED', 'company_id', 0),
        'Transport_Request_ID'    => array('طلب الترحيل',            11,  'transfer_requests', 'id',         'DEP-15', 'SCOPED', 'company_id', 0),
        'Transport_Order_ID'      => array('أمر الترحيل',            12,  'transfer_orders',   'id',         'DEP-15', 'SCOPED', 'company_id', 0),
        'Ticket_ID'               => array('البلاغ',                 13,  'tickets',           'id',         'DEP-10', 'SCOPED', 'company_id', 0),
    );
}

/** رموزُ الإداراتِ التي تحمل نطاقَ W03 — من `repair01_requirements.stage_no=3`. */
function repair01_w3_scope_codes(mysqli $c)
{
    static $codes = null;
    if ($codes !== null) { return $codes; }
    $codes = array();
    /* الوحدةُ في السجلِّ تبدأ برقمِها («04 إدارة الأسطول…») — والرمزُ منه لا من الاسم */
    $r = $c->query("SELECT DISTINCT unit FROM repair01_requirements WHERE stage_no = 3");
    while ($r && $x = $r->fetch_row()) {
        if (preg_match('/^(\d{2})\s/u', $x[0], $m)) { $codes['DEP-' . $m[1]] = true; }
    }
    ksort($codes);
    return $codes = array_keys($codes);
}

/** هل الجدولُ موجود؟ */
function repair01_w3_table_exists(mysqli $c, $t)
{
    $r = $c->query("SELECT COUNT(*) FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
                      AND TABLE_NAME = '" . $c->real_escape_string($t) . "'");
    return $r && (int) $r->fetch_row()[0] === 1;
}

/** هل العمودُ موجود؟ ونوعُه. */
function repair01_w3_col_type(mysqli $c, $t, $col)
{
    $r = $c->query("SELECT DATA_TYPE FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = '" . $c->real_escape_string($t) . "'
                      AND COLUMN_NAME = '" . $c->real_escape_string($col) . "'");
    if (!$r || !($x = $r->fetch_row())) { return ''; }
    return (string) $x[0];
}

/** رقمٌ واحدٌ من استعلام — أو null. */
function repair01_w3_one(mysqli $c, $sql)
{
    $r = $c->query($sql); if (!$r) { return null; }
    $x = $r->fetch_row(); return $x ? $x[0] : null;
}

/**
 * قياسُ مالكِ المفتاح: الجدولُ قائمٌ · العمودُ مفتاحٌ أساسيّ · الكيانُ محمول.
 * يعيد مصفوفةً بالحقائقِ المقيسةِ — والحكمُ يُتَّخذ في البوّابةِ لا هنا.
 */
function repair01_w3_measure_owner(mysqli $c, $table, $column, $companyColumn)
{
    $out = array('table_ok' => 0, 'pk_ok' => 0, 'company_ok' => 0, 'rows' => 0, 'rows_no_company' => 0);
    if (!repair01_w3_table_exists($c, $table)) { return $out; }
    $out['table_ok'] = 1;
    $pk = repair01_w3_one($c, "SELECT COUNT(*) FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $c->real_escape_string($table) . "'
          AND INDEX_NAME = 'PRIMARY' AND COLUMN_NAME = '" . $c->real_escape_string($column) . "'");
    $out['pk_ok'] = ((int) $pk === 1) ? 1 : 0;
    $out['rows'] = (int) repair01_w3_one($c, "SELECT COUNT(*) FROM `$table`");
    if ($companyColumn !== '' && repair01_w3_col_type($c, $table, $companyColumn) !== '') {
        $out['company_ok'] = 1;
        $out['rows_no_company'] = (int) repair01_w3_one($c,
            "SELECT COUNT(*) FROM `$table` WHERE `$companyColumn` IS NULL OR `$companyColumn` = 0");
    }
    return $out;
}

/**
 * مرشَّحاتُ المعرّفِ البديل — **اكتشافٌ آليٌّ لا قائمةٌ يدويّة**.
 * لكلِّ مفتاحٍ: أعمدةُ الوصلِ المقبولةُ (عددية) وأعمدةُ التسميةِ (نصّية).
 * الجدولُ الذي يحمل عمودَ تسميةٍ **ولا يحمل عمودَ وصل** مرشَّحُ `LABEL_ONLY`.
 * والقائمةُ لا تُختار: تُشتقُّ من `information_schema` في كلِّ تشغيل.
 */
function repair01_w3_alias_patterns()
{
    return array(
        'Site_ID'     => array('fk'   => array('site_id', 'operational_site_id', 'from_location_id', 'to_location_id'),
                               'text' => array('site_name', 'site_code')),
        'Asset_ID'    => array('fk'   => array('equipment_id', 'asset_id', 'equipment', 'vehicle_id'),
                               'text' => array('equipment_name', 'equipment_code', 'code_equipment')),
        'Person_ID'   => array('fk'   => array('employee_id', 'person_id', 'operator_employee_id', 'driver_id',
                                               'technician_id', 'operator_person_id', 'assigned_person_id'),
                               'text' => array('person_name', 'operator_assignee', 'operator_name', 'worker_name')),
        'Project_ID'  => array('fk'   => array('project_id'),
                               'text' => array('project_name', 'project_code')),
        'Shift_ID'    => array('fk'   => array('pattern_id', 'shift_pattern_id', 'shift_id'),
                               'text' => array('shift_name')),
    );
}

/** الجداولُ المستثناةُ من المسح: دفترُ الحملةِ نفسُه ونُسَخُ الأمان. */
function repair01_w3_skip_table($t)
{
    return strpos($t, 'repair01_') === 0
        || strpos($t, 'injfrd66_') === 0
        || strpos($t, '_bak') !== false
        || strpos($t, 'backup') !== false
        || strpos($t, 'archive') !== false
        || strpos($t, '_seed_archive') !== false;
}

/**
 * يمسح المخطَّطَ الحيَّ ويعيد مرشَّحاتِ `LABEL_ONLY` لكلِّ مفتاح:
 *   [key_code][] = array(table, column, rows, rows_seed, rows_resolvable)
 * `rows_resolvable` = صفوفٌ نصُّها **يجد مرجعَه** في الجدولِ المالك — وهو
 * الرقمُ الذي يفصل «تسميةً بلا مرجع» عن «تسميةٍ لها مرجعٌ ينقصها المفتاح».
 */
function repair01_w3_scan_aliases(mysqli $c)
{
    $keys = repair01_w3_keys();
    $pat  = repair01_w3_alias_patterns();
    /* خريطةُ الأعمدةِ كاملةً بمسحةٍ واحدة */
    $cols = array();
    $r = $c->query("SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()");
    while ($r && $x = $r->fetch_assoc()) { $cols[$x['TABLE_NAME']][$x['COLUMN_NAME']] = $x['DATA_TYPE']; }
    $base = array();
    $r = $c->query("SELECT TABLE_NAME FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'");
    while ($r && $x = $r->fetch_row()) { $base[$x[0]] = true; }

    $intTypes = array('int', 'bigint', 'smallint', 'mediumint', 'tinyint');
    $txtTypes = array('varchar', 'char', 'text', 'tinytext', 'mediumtext');
    $out = array();
    foreach ($pat as $key => $d) {
        $ownerTable  = $keys[$key][2];
        $ownerColumn = $keys[$key][3];
        $out[$key] = array();
        foreach (array_keys($base) as $t) {
            if ($t === $ownerTable || repair01_w3_skip_table($t) || !isset($cols[$t])) { continue; }
            $hasFk = false;
            foreach ($d['fk'] as $f) {
                if (isset($cols[$t][$f]) && in_array($cols[$t][$f], $intTypes, true)) { $hasFk = true; break; }
            }
            if ($hasFk) { continue; }
            foreach ($d['text'] as $f) {
                if (!isset($cols[$t][$f]) || !in_array($cols[$t][$f], $txtTypes, true)) { continue; }
                $rows = (int) repair01_w3_one($c, "SELECT COUNT(*) FROM `$t`");
                $seed = isset($cols[$t]['is_seed'])
                    ? (int) repair01_w3_one($c, "SELECT COUNT(*) FROM `$t` WHERE `is_seed` = 1") : 0;
                $res  = repair01_w3_resolvable($c, $t, $f, $ownerTable, $ownerColumn, $key, $cols);
                $out[$key][] = array('table' => $t, 'column' => $f, 'rows' => $rows,
                                     'rows_seed' => $seed, 'rows_resolvable' => $res);
            }
        }
    }
    return $out;
}

/** كم صفًّا من صفوفِ التسميةِ يجد مرجعَه فعلًا في الجدولِ المالك. */
function repair01_w3_resolvable(mysqli $c, $t, $col, $ownerTable, $ownerColumn, $key, $cols)
{
    /* أعمدةُ الاسمِ في الجدولِ المالكِ التي يُطابَق عليها */
    $nameCols = array(
        'Site_ID'    => array('name'),
        'Asset_ID'   => array('code', 'name', 'plate_no', 'machine_number'),
        'Person_ID'  => array('name', 'worker_code', 'employee_code'),
        'Project_ID' => array('name', 'project_code'),
        'Shift_ID'   => array('name_ar'),
    );
    if (!isset($nameCols[$key])) { return 0; }
    $conds = array();
    foreach ($nameCols[$key] as $nc) {
        if (repair01_w3_col_type($c, $ownerTable, $nc) === '') { continue; }
        $conds[] = "o.`$nc` = x.`$col`";
    }
    if (!$conds) { return 0; }
    $join = '(' . implode(' OR ', $conds) . ')';
    /* حصرُ الكيانِ حين يحمله الطرفان — فمطابقةٌ عابرةٌ للكيانِ ليست مرجعًا */
    if (isset($cols[$t]['company_id']) && repair01_w3_col_type($c, $ownerTable, 'company_id') !== '') {
        $join .= ' AND o.`company_id` = x.`company_id`';
    }
    $n = repair01_w3_one($c, "SELECT COUNT(DISTINCT x.`$col`) FROM `$t` x
                               JOIN `$ownerTable` o ON $join
                              WHERE x.`$col` IS NOT NULL AND x.`$col` <> ''");
    return (int) $n;
}

/**
 * السجلُّ الثاني (`PARALLEL_REGISTER`) — إعلانٌ مقيسٌ لا اكتشافٌ نمطيّ:
 * جدولٌ حبّتُه حقيقةٌ أمٌّ ومعرّفُه مستقلٌّ عن مفتاحِها.
 * لكلِّ صفٍّ: عمودُ الوصلِ المطلوبُ وعددُ الصفوفِ الموصولةِ مقيسًا حيًّا.
 */
function repair01_w3_parallel_registers()
{
    return array(
        array('key' => 'Person_ID', 'table' => 'persons', 'link' => 'employee_id',
              'in_use' => "active = 1 AND person_class <> 'UNRESOLVED'",
              'why' => 'سجل هوية ثان للانسان بمعرف مستقل PERS-nnnnn — و45 عمودا في النظام اسمها person_id تحمل employees.id'),
    );
}

/** عددُ الصفوفِ الموصولةِ في سجلٍّ ثانٍ — مقيسٌ من الجدولِ نفسِه. */
function repair01_w3_parallel_measure(mysqli $c, $reg)
{
    $t = $reg['table']; $link = $reg['link'];
    $out = array('rows' => 0, 'linked' => 0, 'in_use' => 0, 'in_use_unlinked' => 0, 'col_ok' => 0);
    if (!repair01_w3_table_exists($c, $t)) { return $out; }
    $out['rows'] = (int) repair01_w3_one($c, "SELECT COUNT(*) FROM `$t`");
    if (repair01_w3_col_type($c, $t, $link) === '') { return $out; }
    $out['col_ok'] = 1;
    $out['linked'] = (int) repair01_w3_one($c, "SELECT COUNT(*) FROM `$t` WHERE `$link` IS NOT NULL AND `$link` > 0");
    $out['in_use'] = (int) repair01_w3_one($c, "SELECT COUNT(*) FROM `$t` WHERE " . $reg['in_use']);
    $out['in_use_unlinked'] = (int) repair01_w3_one($c,
        "SELECT COUNT(*) FROM `$t` WHERE " . $reg['in_use'] . " AND (`$link` IS NULL OR `$link` = 0)
           AND `person_class` = 'WORKFORCE'");
    return $out;
}

/**
 * القادحُ الحيُّ — جسٌّ **وظيفيّ** لا قراءةُ `information_schema.TRIGGERS`
 * (تحتاج امتيازًا لا يملكه مستخدمُ التطبيق · M-00). الجسُّ يحاول الفعلَ
 * الممنوعَ داخلَ معاملةٍ ثمّ يتراجع — فالنتيجةُ سلوكٌ لا سطرُ بيانات.
 */
function repair01_w3_probe_person_guard(mysqli $c)
{
    $out = array('company_blocked' => 0, 'link_blocked' => 0, 'error' => '');
    if (repair01_w3_col_type($c, 'persons', 'company_id') === '') { $out['error'] = 'العمود company_id مفقود'; return $out; }
    $c->query('START TRANSACTION');
    /* ① صفٌّ بلا كيان — يجب أن يُمنع */
    $ok1 = @$c->query("INSERT INTO `persons` (full_name, active, person_class) VALUES ('__w3_probe_no_company__', 1, 'IDENTITY_ONLY')");
    $out['company_blocked'] = ($ok1 === false) ? 1 : 0;
    if ($ok1 !== false) { $c->query("DELETE FROM `persons` WHERE full_name = '__w3_probe_no_company__'"); }
    /* ② صفُّ قوى عاملةٍ بلا وصلٍ بالمفتاحِ الأمّ — يجب أن يُمنع */
    $co = (int) repair01_w3_one($c, "SELECT id FROM admin_companies ORDER BY id LIMIT 1");
    $ok2 = @$c->query("INSERT INTO `persons` (full_name, active, company_id, person_class)
                       VALUES ('__w3_probe_no_link__', 1, $co, 'WORKFORCE')");
    $out['link_blocked'] = ($ok2 === false) ? 1 : 0;
    if ($ok2 !== false) { $c->query("DELETE FROM `persons` WHERE full_name = '__w3_probe_no_link__'"); }
    $c->query('ROLLBACK');
    return $out;
}

/**
 * شرطُ مطابقةِ المسارِ في `nav_items` — **بالمسارِ المعياريِّ لا بالنصِّ الحرفيّ**.
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ولماذا لا `route = '…' OR route = '../…'`**: القائمةُ تحمل مدخلاتٍ
 *   بمِرساةٍ (`…php#2`) ومنظرٍ (`…php?view=x`) — وهي **مداخلُ مقصودةٌ** لا
 *   تكرارٌ عشوائيّ. فمطابقةٌ حرفيّةٌ تُطفئ البندَ الأصلَ وتترك مِرساتَه نشِطةً،
 *   فيبقى في القائمةِ سطحٌ حُكم عليه بأنّه تبويبٌ — وهو ما أسقط `W2-12` فعلًا.
 * ◆ فالمطابقةُ تُجرَّد من `?` و`#` و`../` والحالةِ معًا، في الطرفَين.
 */
function repair01_w3_nav_pred(mysqli $c, $route)
{
    $k = str_replace('\\', '/', (string) $route);
    $k = preg_replace('~[?#].*$~', '', $k);
    $k = strtolower(trim($k, '/'));
    return "LOWER(TRIM(BOTH '/' FROM REPLACE(SUBSTRING_INDEX(SUBSTRING_INDEX(`route`,'?',1),'#',1),'../','')))"
         . " = '" . $c->real_escape_string($k) . "'";
}

/**
 * أدوارُ مسارٍ **في السايدبارِ المُصيَّرِ فعلًا** — لا في صفوفِ `nav_items`.
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ولماذا لا الصفوف**: صفٌّ نشِطٌ في `nav_items` **لا يعني بندًا يراه الدور**.
 *   المُصيِّرُ يرشِّح بالصلاحيةِ وبحالةِ السجلِّ المعياريِّ وبالمجموعةِ والدمج.
 *   والمقيسُ الذي كشفه: `Maintenance/orders.php` له صفٌّ نشِطٌ للدورِ 3 —
 *   **ولا يظهر في سايدبارِه المُصيَّرِ البتّة** (٣١ رابطًا صفرٌ منها في الصيانة).
 * ◆ فبرهانُ البلوغِ قبلَ خفضِ ابنٍ إلى تبويبٍ **يجب أن يُقاس على المُصيَّر**:
 *   قياسُه على الصفوفِ يعطي «كلُّ أدوارِ الابنِ في الأب» وهو كاذبٌ للدورِ 3،
 *   فيُقطع طريقُه إلى الشاشةِ بينما يقول الدفترُ إنّه محفوظ.
 * ◆ والتصييرُ يُقرأ من مِسبارِ الواجهةِ نفسِه (`includes/uxui_nav_probe.php`)
 *   الذي تقرأ به بوّابةُ الحفظِ — مصدرٌ واحدٌ للحقيقةِ لا مصدران.
 */
function repair01_w3_rendered_map($ROOT, mysqli $c)
{
    static $map = null;
    if ($map !== null) { return $map; }
    $map = array();
    $probe = $ROOT . '/includes/uxui_nav_probe.php';
    if (!is_file($probe)) { return $map; }
    require_once $ROOT . '/includes/unified_nav.php';
    require_once $probe;
    if (!function_exists('uxp_render_role') || !function_exists('uxp_root_roles')) { return $map; }
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    foreach (uxp_root_roles() as $rid) {
        foreach (uxp_render_role($c, (int) $rid) as $p) {
            $f = strtolower(preg_replace('~^(\.\./)+~', '', preg_replace('/[?#].*$/u', '', (string) $p['href'])));
            if ($f !== '') { $map[$f][(int) $rid] = true; }
        }
    }
    return $map;
}

/**
 * أدوارُ سطحٍ **خُفض سلفًا** — من سجلِّ الإخفاءِ لا من التصيير.
 * فبندُه لم يعد يُصيَّر، وقياسُه على المُصيَّرِ يعطي صفرَ دورٍ فيبدو أنّه لا يفقد
 * أحدًا مهما كان — وهو **أخضرُ بالبناء**. والسجلُّ يحفظ مَن كان يراه.
 */
function repair01_w3_hidden_roles(mysqli $c, $route)
{
    $o = array();
    $r = $c->query("SELECT DISTINCT role_id FROM gov_nav_hidden_log
                     WHERE doc_code = 'RPR-W03' AND reachable = 'TAB_IN_PARENT'
                       AND " . repair01_w3_nav_pred($c, $route) . " ORDER BY role_id");
    while ($r && $x = $r->fetch_row()) { $o[] = (int) $x[0]; }
    return $o;
}

/** أدوارُ مسارٍ في السايدبارِ المُصيَّر. */
function repair01_w3_nav_roles($ROOT, mysqli $c, $route)
{
    $map = repair01_w3_rendered_map($ROOT, $c);
    $f = strtolower(preg_replace('~^(\.\./)+~', '', preg_replace('/[?#].*$/u', '', (string) $route)));
    if (!isset($map[$f])) { return array(); }
    $o = array_keys($map[$f]);
    sort($o);
    return $o;
}

/**
 * سجلُّ تبويباتِ الكيانات — يُقرأ من مصدرِه الواحدِ `includes/entity_tabs.php`
 * ولا يُنسخ هنا. يعيد: [route المُبن] => array(parentRoute, tabName)
 * فالادّعاءُ «تبويبٌ في أبٍ» يُثبَت بمِرساةٍ **مقيسةٍ** لا بعذرٍ مكتوب.
 */
function repair01_w3_entity_tab_map($ROOT)
{
    static $map = null;
    if ($map !== null) { return $map; }
    $map = array();
    $f = $ROOT . '/includes/entity_tabs.php';
    if (!is_file($f)) { return $map; }
    require_once $f;
    if (!function_exists('ems_entity_tabs_registry')) { return $map; }
    foreach (ems_entity_tabs_registry() as $entity => $d) {
        $tabs = $d['tabs'];
        $parent = '';
        foreach ($tabs as $name => $route) {
            if ($route === '') { continue; }
            if ($parent === '') { $parent = $route; continue; }   /* أوّلُ مبنيٍّ هو «نظرةٌ عامة» = الأب */
            $map[strtolower($route)] = array('parent' => $parent, 'tab' => $name, 'entity' => $entity);
        }
    }
    return $map;
}

/**
 * الإصلاحُ البنيويُّ للتسميةِ الحيّةِ بلا مفتاح — **قائمةٌ مُشتقَّةٌ من القياسِ**
 * لا مختارةٌ برأي: هذه هي الجداولُ التي قاست البوّابةُ فيها صفوفًا **حيّةً**
 * (‏`rows > rows_seed`) تعرّف حقيقةً أمًّا بنصٍّ ولا تحمل مفتاحَها.
 * الإصلاحُ **إضافيٌّ لا هادم**: يُضاف عمودُ المفتاحِ فيصير النصُّ لافتةً بجانبِ
 * مفتاحٍ، ويُملأ حيث يجد النصُّ مرجعَه — وما لا يجده يبقى مقيسًا لموجتِه.
 * (‏الجدولُ · عمودُ النصِّ · عمودُ المفتاحِ · الجدولُ المالكُ · أعمدةُ المطابقة)
 */
function repair01_w3_label_repairs()
{
    return array(
        array('table' => 'scr_production',      'text' => 'site_name',
              'key_col' => 'site_id',               'owner' => 'sites',      'match' => array('name'),
              'key_code' => 'Site_ID',   'comment' => 'REPAIR01 W03 - Site_ID بدل التعريف بالاسم'),
        array('table' => 'scr_unit_perf',        'text' => 'code_equipment',
              'key_col' => 'equipment_id',          'owner' => 'equipments', 'match' => array('code', 'name'),
              'key_code' => 'Asset_ID',  'comment' => 'REPAIR01 W03 - Asset_ID بدل التعريف بالكود النصي'),
        array('table' => 'scr_site_shift_plan',  'text' => 'operator_assignee',
              'key_col' => 'operator_employee_id',  'owner' => 'employees',  'match' => array('name'),
              'key_code' => 'Person_ID', 'comment' => 'REPAIR01 W03 - Person_ID بدل التعريف بالاسم'),
    );
}

/** هل يطبع الملفُّ شريطَ تبويباتِ الكيان؟ — قياسٌ من القرصِ لا دعوى. */
function repair01_w3_renders_tabs($ROOT, $route)
{
    $p = $ROOT . '/' . $route;
    if (!is_file($p)) { return 0; }
    return (strpos((string) file_get_contents($p), 'ems_entity_tabs') !== false) ? 1 : 0;
}
