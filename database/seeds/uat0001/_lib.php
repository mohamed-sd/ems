<?php
/**
 * UAT-0001 · مكتبةٌ مشتركةٌ لبذور التجربة التشغيلية.
 *
 * مبادئ:
 *  · **العطالة**: كل بذرةٍ تُعاد تشغيلًا فلا تُضاعف صفًّا — المفتاحُ الطبيعيُّ يحكم.
 *  · **الشركة 4 حصرًا** — ولا يُكتب صفٌّ بلا company_id حيث يوجد العمود.
 *  · **لا مساسَ بجداول الأدوار والصفحات والصلاحيات** (قائمةُ المنع أدناه تُفحص فعليًّا).
 *  · **لا DDL** — الأعلامُ تقول EMS_DDL_FREEZE=true، والبذورُ بياناتٌ فقط.
 */

const UAT_COMPANY   = 4;
const UAT_TAG       = 'UAT-2026';
const UAT_IMPORT_DIR = __DIR__ . '/../../../storage/uat_import';

/** جداولُ ممنوعةٌ بقرار المالك: الأدوار والصفحات والصلاحيات. */
const UAT_FORBIDDEN = [
    'roles', 'role_permissions', 'report_role_permissions', 'modules', 'nav_items', 'nav_redirects',
    'link_groups', 'permission_templates', 'permission_template_versions', 'template_permissions',
    'permission_change_requests', 'permission_approval_steps', 'permission_exceptions',
    'permission_audit_events', 'permission_review_cycles', 'permission_review_lines',
    'effective_permissions', 'perm_shadow_diffs', 'visibility_keys', 'visibility_audit_log',
    'sensitive_field_policies', 'sensitive_access_grants', 'sensitive_read_log',
    'ownership_access_grants', 'portal_elements', 'workspace_cards', 'workspace_layouts',
];

function uat_db()
{
    static $db = null;
    if ($db !== null) return $db;
    $env = [];
    foreach (file(dirname(__DIR__, 3) . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
        if ($ln[0] === '#' || strpos($ln, '=') === false) continue;
        [$k, $v] = explode('=', $ln, 2);
        $env[trim($k)] = trim($v);
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli($env['DB_HOST'], $env['DB_MIGRATOR_USER'], $env['DB_MIGRATOR_PASS'], $env['DB_NAME']);
    $db->set_charset('utf8mb4');
    // **لا يُطفأ sql_mode**: الوضعُ المتساهل يبتلع قيمةَ ENUM غير الصالحة صامتةً
    // فيُكتب '' بدل الخطأ — وهو أخطرُ من الفشل. الصارمُ مع السماح بتواريخَ تاريخية.
    $db->query("SET SESSION sql_mode='STRICT_ALL_TABLES,ALLOW_INVALID_DATES'");
    return $db;
}

function uat_guard($table)
{
    if (in_array($table, UAT_FORBIDDEN, true)) {
        throw new RuntimeException("محظور: محاولةُ كتابةٍ في جدول الأدوار/الصفحات/الصلاحيات «$table»");
    }
}

/** يقرأ ورقةً مستخرجةً. */
function uat_json($basename)
{
    $f = UAT_IMPORT_DIR . '/' . $basename . '.json';
    if (!is_file($f)) throw new RuntimeException("ورقةٌ مفقودة: $basename");
    $d = json_decode(file_get_contents($f), true);
    if (!is_array($d)) throw new RuntimeException("JSON تالف: $basename");
    // بعضُ الأوراق تُعيد صفَّ العناوين داخل البيانات — يُسقط
    return array_values(array_filter($d, function ($r) {
        $k = array_keys($r); $v = array_values($r);
        return $k !== $v;
    }));
}

/** رقمٌ من نصٍّ عربيٍّ/إنجليزيٍّ فيه فواصلُ أو رموزُ عملة. */
function uat_num($v, $default = null)
{
    if ($v === null || $v === '') return $default;
    $v = str_replace(['٬', ',', '$', '٪', '%', ' ', "\u{00A0}"], '', (string) $v);
    $v = strtr($v, ['٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
    if (!is_numeric($v)) {
        if (preg_match('/-?\d+(\.\d+)?/', $v, $m)) return (float) $m[0];
        return $default;
    }
    return (float) $v;
}

function uat_int($v, $default = null)
{
    $n = uat_num($v, null);
    return $n === null ? $default : (int) round($n);
}

/** تاريخٌ صالحٌ أو null. */
function uat_date($v)
{
    if ($v === null || $v === '') return null;
    $v = trim((string) $v);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v;
    if (preg_match('/^\d{4}-\d{2}$/', $v)) return $v . '-01';
    if (is_numeric($v)) {                       // تسلسلُ إكسل
        $s = (float) $v;
        if ($s > 20000 && $s < 60000) return gmdate('Y-m-d', (int) (($s - 25569) * 86400));
        return null;
    }
    $ts = strtotime($v);
    return $ts ? date('Y-m-d', $ts) : null;
}

/** الأعمدةُ القابلةُ للكتابة — تُستبعد المولَّدةُ (STORED/VIRTUAL GENERATED) فلا يرفضها الخادم. */
function uat_writable($table)
{
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    $db = uat_db();
    $st = $db->prepare("SELECT COLUMN_NAME, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    $st->bind_param('s', $table);
    $st->execute();
    $res = $st->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) {
        if (stripos($r['EXTRA'], 'GENERATED') !== false && stripos($r['EXTRA'], 'DEFAULT_GENERATED') === false) continue;
        $out[$r['COLUMN_NAME']] = true;
    }
    return $cache[$table] = $out;
}

function uat_filter($table, array $row)
{
    $ok = uat_writable($table);
    return array_intersect_key($row, $ok);
}

/** إدراجٌ عاطل: يبحث بالمفتاح الطبيعي، فإن وُجد أعاد المعرّف، وإلا أدرج. */
function uat_upsert($table, array $key, array $row, $idCol = 'id')
{
    uat_guard($table);
    $db = uat_db();
    $row = uat_filter($table, $row);
    $where = []; $types = ''; $vals = [];
    foreach ($key as $k => $v) {
        if ($v === null) { $where[] = "`$k` IS NULL"; continue; }
        $where[] = "`$k` = ?"; $types .= 's'; $vals[] = $v;
    }
    $sql = "SELECT `$idCol` FROM `$table` WHERE " . implode(' AND ', $where) . " LIMIT 1";
    $st = $db->prepare($sql);
    if ($vals) $st->bind_param($types, ...$vals);
    $st->execute();
    $res = $st->get_result()->fetch_assoc();
    if ($res) return (int) $res[$idCol];

    $all = $key + $row;
    $cols = array_keys($all);
    $ph   = implode(',', array_fill(0, count($cols), '?'));
    $sql  = "INSERT INTO `$table` (`" . implode('`,`', $cols) . "`) VALUES ($ph)";
    $st   = $db->prepare($sql);
    $t    = str_repeat('s', count($cols));
    $v    = array_values($all);
    $st->bind_param($t, ...$v);
    $st->execute();
    return (int) $db->insert_id;
}

/** إدراجٌ صريحٌ بلا بحث (للجداول التي لا مفتاحَ طبيعيَّ لها) — يُستعمل بعد تفريغٍ موسوم. */
function uat_insert($table, array $row)
{
    uat_guard($table);
    $db   = uat_db();
    $row  = uat_filter($table, $row);
    $cols = array_keys($row);
    $ph   = implode(',', array_fill(0, count($cols), '?'));
    $st   = $db->prepare("INSERT INTO `$table` (`" . implode('`,`', $cols) . "`) VALUES ($ph)");
    $st->bind_param(str_repeat('s', count($cols)), ...array_values($row));
    $st->execute();
    return (int) $db->insert_id;
}

function uat_one($sql, array $params = [])
{
    $db = uat_db();
    $st = $db->prepare($sql);
    if ($params) $st->bind_param(str_repeat('s', count($params)), ...array_values($params));
    $st->execute();
    $r = $st->get_result()->fetch_assoc();
    return $r ?: null;
}

function uat_count($table, $where = '1')
{
    $r = uat_db()->query("SELECT COUNT(*) c FROM `$table` WHERE $where")->fetch_assoc();
    return (int) $r['c'];
}

/** مستخدمٌ منشئٌ للسجلات — حسابُ التشغيل الحقيقيّ. */
function uat_actor()
{
    static $id = null;
    if ($id !== null) return $id;
    $r = uat_one("SELECT id FROM users WHERE company_id=? AND username=? LIMIT 1", [UAT_COMPANY, 'محمد']);
    $id = $r ? (int) $r['id'] : 1;
    return $id;
}

// ── تقريرُ التنفيذ ────────────────────────────────────────────────────────────

$GLOBALS['uat_report'] = [];

function uat_log($table, $action, $n = 1)
{
    $k = $table . '|' . $action;
    $GLOBALS['uat_report'][$k] = ($GLOBALS['uat_report'][$k] ?? 0) + $n;
}

function uat_print_report($title)
{
    echo "\n── $title ──\n";
    ksort($GLOBALS['uat_report']);
    foreach ($GLOBALS['uat_report'] as $k => $n) {
        [$t, $a] = explode('|', $k);
        printf("   %-34s %-10s %6d\n", $t, $a, $n);
    }
    $GLOBALS['uat_report'] = [];
}
