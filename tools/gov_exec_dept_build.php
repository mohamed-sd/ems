<?php
/**
 * tools/gov_exec_dept_build.php — بناءُ مواضعِ الدليلِ إدارةً إدارة (GOV_EXEC §5 · §12)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لماذا عُدّةٌ ثانيةٌ بعد `gov_exec_screen_wire.php`**: تلك تُوصِّل شاشةً
 *   **بُنيت** بسجلاتِها السبعة. وهذه تبني **الشاشةَ نفسَها** من ورقةِ الدليل:
 *   جدولُها وحقولُها بترتيبِ دورتِها المستنديّةِ ومصنِّفُها وملفُّها — ثمَّ
 *   تُنادي تلك للتوصيل. فلا ازدواجَ: **بناءٌ ثمَّ توصيلٌ بأداتِه**.
 *
 * ◆ **والحقلُ لا يُكتب مرّتَين**: اسمُ الحقلِ العربيُّ يُقرأ من `repair01_fields`
 *   (ورقة `02 · تتبع الحقول`) **حرفًا**، والمواصفةُ تحمل **مفاتيحَه الإنجليزيّةَ
 *   بترتيبِ الورقةِ نفسِه** لا غير. فإن اختلف العددُ **تُردُّ المواصفةُ ولا تُبنى**
 *   — وهذا هو حارسُ «الحقولُ بترتيبِ دورتِها المستنديّة» بالبناءِ لا بالفحص.
 *
 * ◆ **والتصييرُ شرطُ التسجيل**: كلُّ حقلٍ يُقيَّد في `gov_field_class` **إلّا ما
 *   تُخفيه العُدّةُ تقنيًّا** (`u13_hidden_cols`) — فلا يُقيَّد حقلٌ لا يظهر،
 *   ولا يظهر حقلٌ لا يُقيَّد. ⛔ ودرسُ «الرندر لا المخزن» منفَّذٌ بالبناء.
 *
 * ◆ **والمصدرُ الواحد**: `'table_mode' => 'extend'` حين يكون للحبّةِ جدولٌ
 *   مالكٌ قائمٌ — فلا يُنشأ جدولٌ موازٍ (الدستور §7 · §17).
 *
 * التشغيل:
 *   php tools/gov_exec_dept_build.php --spec=<file.php> --plan
 *   php tools/gov_exec_dept_build.php --spec=<file.php> --emit=<migration_slug>
 *   php tools/gov_exec_dept_build.php --spec=<file.php> --apply
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once $ROOT . '/includes/u13_screen_kit_cols.php';

$SPEC = null; $PLAN = false; $EMIT = null; $APPLY = false;
foreach ($argv as $a) {
    if (strpos($a, '--spec=') === 0) { $SPEC = substr($a, 7); }
    elseif ($a === '--plan')          { $PLAN = true; }
    elseif (strpos($a, '--emit=') === 0) { $EMIT = substr($a, 7); }
    elseif ($a === '--apply')         { $APPLY = true; }
}
if ($SPEC === null || !is_file($SPEC)) { exit("⛔ --spec=<file.php> مطلوب\n"); }
$S = require $SPEC;
foreach (array('dept', 'dept_ar', 'role_id', 'unit', 'dir', 'screens') as $k) {
    if (!isset($S[$k])) { exit("⛔ ناقصٌ في المواصفة: {$k}\n"); }
}

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("⛔ تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$e = function ($x) use ($conn) { return $conn->real_escape_string((string) $x); };
$one = function ($sql) use ($conn) { $q = $conn->query($sql); $r = $q ? $q->fetch_row() : null; return $r ? $r[0] : null; };

/* ═══ ① حقولُ الورقةِ — المصدرُ الحاكمُ للأسماءِ والترتيب ═══════════════════ */
function gdb_guide_fields(mysqli $conn, $unit, $surface)
{
    $out = array();
    $st = $conn->prepare("SELECT seq, field_name, field_type, visibility_rule
                            FROM repair01_fields WHERE unit = ? AND surface = ? ORDER BY id");
    $st->bind_param('ss', $unit, $surface);
    $st->execute();
    $rs = $st->get_result();
    while ($r = $rs->fetch_assoc()) { $out[] = $r; }
    $st->close();
    return $out;
}

/** نوعُ العمودِ مُستنبَطًا من اسمِ الحقلِ وصنفِه — والمواصفةُ تَغلِب دائمًا. */
function gdb_type($label, $ftype, $key)
{
    $l = (string) $label;
    $has = function ($n) use ($l) { return mb_strpos($l, $n) !== false; };
    if (preg_match('~_at$~', $key))                      { return 'DATETIME NULL DEFAULT NULL'; }
    if (preg_match('~_by$~', $key))                      { return 'INT NULL DEFAULT NULL'; }
    if ($has('وقت '))                                     { return 'TIME NULL DEFAULT NULL'; }
    if ($has('تاريخ') || $has('التاريخ') || $has('المهلة')) { return 'DATE NULL DEFAULT NULL'; }
    /* «وحدةُ العدد» وحدةُ قياسٍ لا عدد — والفحصُ يسبق فحصَ «عدد» عمدًا. */
    if ($has('وحدة') || $has('الوحدة'))                    { return 'VARCHAR(80) NULL DEFAULT NULL'; }
    if ($has('نسبة') || $has('النسبة'))                    { return 'DECIMAL(8,2) NULL DEFAULT NULL'; }
    if ($has('ساعات') || $has('الساعات') || $has('ساعة') || $has('العداد') || $has('عداد')) {
        return 'DECIMAL(12,2) NULL DEFAULT NULL';
    }
    if ($has('قيمة') || $has('القيمة') || $has('تكلفة') || $has('مبلغ') || $has('سعر') || $has('الحد المقبول')) {
        return 'DECIMAL(18,2) NULL DEFAULT NULL';
    }
    if ($has('عدد') || $has('سنة') || $has('تسلسل') || $has('عددها') || $has('(يوم)') || $has('أيام')) {
        return 'INT NULL DEFAULT NULL';
    }
    if ($has('الأطنان') || $has('الأمتار') || $has('الوقود') || $has('توقف') || $has('التوقف')) {
        return 'DECIMAL(12,2) NULL DEFAULT NULL';
    }
    if ($has('ملاحظ') || $has('وصف') || $has('الوصف') || $has('شرح') || $has('الشرح')
        || $has('مبرر') || $has('تفسير') || $has('نص ') || $has('التوصية') || $has('الإجراء')
        || $has('الخيار') || $has('القرار') || $has('كيف حُسم') || $has('الأثر')) {
        return 'VARCHAR(500) NULL DEFAULT NULL';
    }
    if ($ftype === 'REFERENCE')                          { return 'VARCHAR(80) NULL DEFAULT NULL'; }
    if (preg_match('~^(رقم|كود|رمز|مرجع|مفتاح)~u', $l))   { return 'VARCHAR(80) NULL DEFAULT NULL'; }
    return 'VARCHAR(255) NULL DEFAULT NULL';
}

/** صنفُ العرضِ DC — المواصفةُ تَغلِب والافتراضُ تشغيليّ. */
function gdb_dc($label, $ftype)
{
    $l = (string) $label;
    foreach (array('قيمة','تكلفة','مبلغ','سعر','محاسب','مالي','الإهلاك','رسمل','IFRS','تمويل','ممول') as $n) {
        if (mb_strpos($l, $n) !== false) { return 'DC-2'; }
    }
    foreach (array('قانون','عقد','ملكية','المالك','تأمين','رخصة','مستند','حجية','اعتماد') as $n) {
        if (mb_strpos($l, $n) !== false) { return 'DC-3'; }
    }
    return 'DC-1';
}

/* ═══ ② بناءُ الخطّةِ من المواصفةِ والورقة ═══════════════════════════════ */
$plans = array(); $ERR = 0;
foreach ($S['screens'] as $sc) {
    foreach (array('code', 'file', 'surface', 'title', 'table', 'table_mode', 'nature',
                   'target_ref', 'group', 'sort_no', 'grain', 'keys') as $k) {
        if (!isset($sc[$k])) { echo "⛔ [{$sc['code']}] ناقصٌ: {$k}\n"; $ERR++; continue 2; }
    }
    $g = gdb_guide_fields($conn, $S['unit'], $sc['surface']);
    if (!$g) { echo "⛔ [{$sc['code']}] لا حقولَ في الورقةِ للسطح «{$sc['surface']}»\n"; $ERR++; continue; }
    $keys = is_array($sc['keys']) ? $sc['keys'] : preg_split('~\s*,\s*~u', trim((string) $sc['keys']));
    $keys = array_values(array_filter(array_map('trim', $keys), function ($x) { return $x !== ''; }));
    if (count($keys) !== count($g)) {
        echo "⛔ [{$sc['code']}] عددُ المفاتيحِ " . count($keys) . " ≠ حقولِ الورقةِ " . count($g)
           . " — الترتيبُ المستنديُّ لا يُكسر\n";
        $ERR++; continue;
    }
    $types = isset($sc['types']) && is_array($sc['types']) ? $sc['types'] : array();
    $dcs   = isset($sc['dc'])    && is_array($sc['dc'])    ? $sc['dc']    : array();
    $sens  = isset($sc['sensitive']) && is_array($sc['sensitive']) ? $sc['sensitive'] : array();
    $cols = array();
    foreach ($g as $i => $f) {
        $k = $keys[$i];
        $cols[] = array(
            'key'   => $k,
            'label' => $f['field_name'],
            'ftype' => $f['field_type'],
            'seq'   => $f['seq'],
            'sql'   => isset($types[$k]) ? $types[$k] : gdb_type($f['field_name'], $f['field_type'], $k),
            'dc'    => isset($dcs[$k]) ? $dcs[$k] : gdb_dc($f['field_name'], $f['field_type']),
            'sens'  => in_array($k, $sens, true) ? 1 : 0,
        );
    }
    $sc['cols'] = $cols;
    $plans[] = $sc;
}
if ($ERR > 0) { exit("\n⛔ المواصفةُ مردودةٌ — {$ERR} خطأً\n"); }

/* الأعمدةُ القائمةُ فعلًا */
function gdb_existing(mysqli $conn, $table)
{
    $out = array();
    $st = $conn->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS
                            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $st->bind_param('s', $table);
    $st->execute();
    $rs = $st->get_result();
    while ($r = $rs->fetch_row()) { $out[$r[0]] = 1; }
    $st->close();
    return $out;
}
$TECH = array('id', 'company_id', 'created_by', 'created_at', 'updated_at', 'updated_by',
              'is_deleted', 'deleted_at', 'deleted_by');

$DDL = array();      // جملُ الهجرة
$DDLDOWN = array();
foreach ($plans as $ix => $pl) {
    $have = gdb_existing($conn, $pl['table']);
    $isNew = empty($have);
    if ($pl['table_mode'] === 'extend' && $isNew) {
        echo "⛔ [{$pl['code']}] `{$pl['table']}` معلَنٌ extend ولا وجودَ له\n"; $ERR++; continue;
    }
    if ($pl['table_mode'] === 'create' && !$isNew) {
        echo "· [{$pl['code']}] `{$pl['table']}` قائمٌ سلفًا — يُستكمل بالناقصِ لا يُنشأ\n";
    }
    $add = array();
    foreach ($pl['cols'] as $c) {
        if (in_array($c['key'], $TECH, true)) { continue; }
        if (isset($have[$c['key']])) { continue; }
        $add[] = $c;
    }
    /* ◆ **فعلُ التسجيلِ يكتب `created_by`** — وجدولٌ مالكٌ قائمٌ قد لا يحمله
         (`sites` · `site_day` مثالًا). والعمودُ تقنيٌّ فيتخطّاه المرورُ أعلاه،
         فيُطلَب صراحةً هنا **حين يُعلَن فعلٌ فقط** — فلا نضخّم جدولًا لا يكتب. */
    if (!$isNew && !empty($pl['create']) && !isset($have['created_by'])) {
        $add[] = array('key' => 'created_by', 'label' => 'المُنشئ', 'ftype' => 'AUDIT',
                       'seq' => '0', 'sql' => 'INT NULL DEFAULT NULL', 'dc' => 'DC-1', 'sens' => 0);
    }
    $plans[$ix]['add'] = $add;
    $plans[$ix]['isNew'] = $isNew;
    $plans[$ix]['have'] = $have;
}
if ($ERR > 0) { exit("\n⛔ {$ERR} خطأً في الجداول\n"); }

/* ═══ ③ --plan ═══════════════════════════════════════════════════════════ */
if ($PLAN) {
    printf("═ خطّةُ %s (%s) · %d شاشة ═\n\n", $S['dept'], $S['dept_ar'], count($plans));
    $tAdd = 0; $tCol = 0;
    foreach ($plans as $pl) {
        printf("● %-22s %-28s جدول %-28s %s · حقول %2d · يُضاف %2d\n",
            $pl['code'], mb_substr($pl['title'], 0, 26), $pl['table'],
            ($pl['isNew'] ? 'جديد ' : 'قائم '), count($pl['cols']), count($pl['add']));
        $tAdd += count($pl['add']); $tCol += count($pl['cols']);
    }
    printf("\nمجموعُ حقولِ الورقة: %d · أعمدةٌ تُضاف: %d\n", $tCol, $tAdd);
    exit(0);
}

/* ═══ ④ --emit — كتابةُ الهجرةِ وعكسِها ═══════════════════════════════════ */
if ($EMIT !== null) {
    $up = array(); $down = array();
    foreach ($plans as $pl) {
        if ($pl['isNew']) {
            $lines = array(
                "    `id` INT NOT NULL AUTO_INCREMENT",
                "    `company_id` INT NOT NULL DEFAULT 0",
            );
            foreach ($pl['cols'] as $c) {
                if (in_array($c['key'], $TECH, true)) { continue; }
                $lines[] = "    `{$c['key']}` {$c['sql']} COMMENT " . gdb_q($c['label']);
            }
            /* كتلةُ التدقيقِ الإلحاقيّةُ (§7 الخطوة ١١) — دائمًا، وحلقةُ الأعمدةِ
               أعلاه تتخطّى التقنيَّ فلا ازدواج. */
            $lines[] = "    `created_by` INT NULL DEFAULT NULL";
            $lines[] = "    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP";
            $lines[] = "    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
            $lines[] = "    PRIMARY KEY (`id`)";
            $lines[] = "    KEY `ix_" . substr(md5($pl['table']), 0, 8) . "_co` (`company_id`)";
            $up[] = "CREATE TABLE IF NOT EXISTS `{$pl['table']}` (\n" . implode(",\n", $lines)
                  . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT "
                  . gdb_q($S['dept'] . ' — ' . $pl['title'] . ' · الحبة: ' . $pl['grain']);
            $down[] = "DROP TABLE IF EXISTS `{$pl['table']}`";
        } elseif ($pl['add'] || !empty($pl['drop_cols'])) {
            $parts = array(); $dparts = array();
            foreach ($pl['add'] as $c) {
                $parts[]  = "ADD COLUMN `{$c['key']}` {$c['sql']} COMMENT " . gdb_q($c['label']);
                $dparts[] = "DROP COLUMN `{$c['key']}`";
            }
            /* ◆ **أعمدةٌ مولَّدةٌ باسمٍ مبهمٍ في موجةٍ سابقة** (`c5` · `offer_22`):
                 تحمل حقلَ الورقةِ في تعليقِها ولا تحمله في اسمِها، فلا يُقرأ
                 السطرُ بلا قاموس. والجدولُ **فارغٌ ولا كاتبَ له** فالتسميةُ
                 تصحَّح: تُنشأ المسمّاةُ وتُسقَط المبهمة.
                 ⛔ **والعكسُ يعيدها بتعريفِها الحرفيِّ** المنتزَعِ من المخطَّطِ
                    وقتَ التوليد — فالتراجعُ يردُّ الشكلَ لا يقاربه. */
            if (!empty($pl['drop_cols'])) {
                $defs = gdb_col_defs($conn, $pl['table'], $pl['drop_cols']);
                foreach ($pl['drop_cols'] as $dc) {
                    if (!isset($defs[$dc])) { continue; }
                    $parts[]  = "DROP COLUMN `{$dc}`";
                    $dparts[] = "ADD COLUMN `{$dc}` " . $defs[$dc];
                }
            }
            if ($parts) { $up[] = "ALTER TABLE `{$pl['table']}`\n    " . implode(",\n    ", $parts); }
            if ($dparts) { $down[] = "ALTER TABLE `{$pl['table']}`\n    " . implode(",\n    ", $dparts); }
        }
    }
    $stamp = date('Y_m_d');
    $mUp   = $ROOT . '/database/migrations/' . $EMIT . '.php';
    $mDown = $ROOT . '/database/migrations/' . $EMIT . '_down.php';
    file_put_contents($mUp, gdb_migration_src($EMIT, $S, $up, false));
    file_put_contents($mDown, gdb_migration_src($EMIT, $S, array_reverse($down), true));
    echo "✔ هجرةٌ: database/migrations/{$EMIT}.php (+ عكسُها) · " . count($up) . " جملة\n";
    exit(0);
}

/* ═══ ⑤ --apply ══════════════════════════════════════════════════════════ */
if (!$APPLY) { exit("لا شيءَ ليُفعَل — استعمل --plan أو --emit=<slug> أو --apply\n"); }

$missing = array();
foreach ($plans as $pl) {
    $have = gdb_existing($conn, $pl['table']);
    if (!$have) { $missing[] = $pl['table'] . ' (الجدولُ نفسُه)'; continue; }
    foreach ($pl['cols'] as $c) {
        if (in_array($c['key'], $TECH, true)) { continue; }
        if (!isset($have[$c['key']])) { $missing[] = $pl['table'] . '.' . $c['key']; }
    }
}
if ($missing) {
    echo "⛔ شغِّلِ الهجرةَ أوّلًا — ناقصٌ في القاعدة:\n";
    foreach (array_slice($missing, 0, 40) as $m) { echo "   · {$m}\n"; }
    if (count($missing) > 40) { echo "   … و" . (count($missing) - 40) . " غيرُها\n"; }
    exit(1);
}

$HID = u13_hidden_cols();
$nCls = 0; $nFile = 0; $nBind = 0; $wireSpecs = array();
foreach ($plans as $pl) {
    /* ─ أ · دفترُ الحقولِ المبنيّة: مقيَّدٌ ⇒ مُصيَّرٌ ولا فرق ─────────────── */
    foreach ($pl['cols'] as $c) {
        if (in_array($c['key'], $HID, true)) { continue; }   // لا يُصيَّر ⇒ لا يُقيَّد
        $ok = $conn->query("INSERT INTO gov_field_class
            (company_id, screen_code, field_key, label_ar, dc_code, is_sensitive, doc_ref, active)
            VALUES (0, '{$e($pl['code'])}', '{$e($c['key'])}', '{$e($c['label'])}',
                    '{$e($c['dc'])}', {$c['sens']}, 'GOV_EXEC-5', 1)
            ON DUPLICATE KEY UPDATE label_ar = VALUES(label_ar), dc_code = VALUES(dc_code), active = 1");
        if (!$ok) { exit("⛔ gov_field_class {$pl['code']}.{$c['key']}: {$conn->error}\n"); }
        $nCls++;
    }

    /* ─ ب · ملفُّ الشاشةِ — تصريحٌ فوقَ العُدّةِ المشتركة ─────────────────── */
    $rel  = $S['dir'] . '/' . $pl['file'];
    $path = $ROOT . '/' . $rel;
    if (!is_dir(dirname($path))) { @mkdir(dirname($path), 0777, true); }
    $body = gdb_screen_src($rel, $pl, $S);
    if (is_file($path) && strpos((string) file_get_contents($path), 'gov_exec:generated') === false) {
        echo "· [{$pl['code']}] ملفٌّ بيدٍ سابقةٍ فلا يُدهَس: {$rel}\n";
    } else {
        file_put_contents($path, $body);
        $nFile++;
    }

    /* ─ ج · مواصفةُ التوصيلِ للعُدّةِ المعمَّمة ───────────────────────────── */
    $wireSpecs[] = array(
        'route' => $rel, 'file' => $pl['file'], 'label' => $pl['title'],
        'target_title' => isset($pl['target_title']) ? $pl['target_title'] : $pl['surface'],
        'group' => $pl['group'], 'grain' => $pl['grain'],
        'entity' => $pl['table'], 'dept' => $S['dept'], 'dept_ar' => $S['dept_ar'],
        'role_id' => (int) $S['role_id'], 'owner_role_ar' => $S['dept_ar'],
        'guide_ref' => isset($pl['guide_ref']) ? $pl['guide_ref']
                     : ('01 · الدليل المعماري · ورقة ' . $S['dept_ar'] . ' · ' . $pl['surface']),
        'sort_no' => (int) $pl['sort_no'],
        'neighbor_route' => $S['neighbor_route'],
        'stage_name' => $pl['group'],
        'output_doc' => isset($pl['output_doc']) ? $pl['output_doc'] : $pl['title'],
        'next_state' => isset($pl['next_state']) ? $pl['next_state'] : 'مسجَّل',
        'consumers' => isset($pl['consumers']) ? $pl['consumers'] : $S['dept_ar'],
        'icon' => isset($pl['icon']) ? $pl['icon'] : 'fa fa-table',
        'level_name' => isset($pl['level_name']) ? $pl['level_name'] : '2 — العمليات',
        'purpose' => isset($pl['purpose']) ? $pl['purpose'] : $pl['title'],
    );
}
echo "① دفترُ الحقولِ المبنيّة: {$nCls} مفردة\n";
echo "② ملفّاتُ الشاشات: {$nFile}\n";

/* ─ د · التوصيلُ بالعُدّةِ المعمَّمةِ — شاشةً شاشة ───────────────────────── */
$tmp = sys_get_temp_dir() . '/gov_exec_wire_' . getmypid() . '.json';
$php = PHP_BINARY;
foreach ($wireSpecs as $ws) {
    file_put_contents($tmp, json_encode($ws, JSON_UNESCAPED_UNICODE));
    $out = shell_exec(escapeshellarg($php) . ' ' . escapeshellarg($ROOT . '/tools/gov_exec_screen_wire.php')
                    . ' --spec=' . escapeshellarg($tmp) . ' 2>&1');
    if (strpos((string) $out, '⛔') !== false) { echo "⛔ توصيلُ {$ws['route']}:\n{$out}\n"; exit(1); }
    echo "③ وُصِّلت: {$ws['route']}\n";
}
@unlink($tmp);

/* ─ هـ · ربطُ موضعِ الدليل: `nav_placements` هو مقامُ التغطية ──────────── */
foreach ($plans as $pl) {
    $rel = $S['dir'] . '/' . $pl['file'];
    $sid = $one("SELECT screen_id FROM repair01_screen_registry WHERE route = '{$e($rel)}'");
    if ($sid === null) { echo "⛔ لا سجلَّ شاشةٍ لـ{$rel}\n"; exit(1); }
    /* الموضعُ يُعرَّف بمعرِّفِه حين يُصرَّح — فمفتاحُ `target_ref` نصٌّ عربيٌّ
       يُنقل بالنسخِ وأولى بألّا يُعتمد عليه في الربط. */
    $cond = isset($pl['placement_id'])
          ? 'id = ' . (int) $pl['placement_id']
          : "workspace_id = '{$e($S['dept'])}' AND target_ref = '{$e($pl['target_ref'])}'";
    if ((int) $one("SELECT COUNT(*) FROM nav_placements WHERE {$cond}") === 0) {
        echo "⛔ لا موضعَ بهذا المفتاح: {$pl['target_ref']}\n"; exit(1);
    }
    $ok = $conn->query("UPDATE nav_placements
        SET route = '{$e($rel)}', screen_id = '{$e($sid)}', placement_type = 'MENU_ITEM',
            source_ref = 'GUIDE-IMPORT·01 الدليل المعماري·BUILT·GOV_EXEC §5'
        WHERE {$cond}");
    if (!$ok) { exit("⛔ nav_placements: {$conn->error}\n"); }
    if ($conn->affected_rows > 0) { $nBind++; }
}
echo "④ مواضعُ الدليلِ المربوطة: {$nBind}\n";

/* ─ و · القفلُ الثالث: بندُ القائمةِ لدورِ المساحة ───────────────────────────
   **درسٌ مقيسٌ في أوّلِ تشغيلةٍ (DEP-04)**: بعد التوصيلِ الخماسيِّ وربطِ المواضعِ
   بقيت الثلاثُ والعشرون **غيرَ مُصيَّرةٍ**: `TARGET_BUILD_COVERAGE` صعد و
   `RENDERED` لم يتحرّك. فالمواضعُ ورقةُ الدليلِ و`nav_items` هي **ما يُصيَّر**،
   والقفلُ الثالثُ من أربعةٍ لا يفتحه التوصيل. ⇒ فالأداةُ تفتحه بنفسِها
   بالأداةِ المعتمدةِ لذلك، ولا يبقى بابًا يُنسى في الإدارةِ التالية. */
$out = shell_exec(escapeshellarg($php) . ' ' . escapeshellarg($ROOT . '/tools/navr_wire_missing.php')
                . ' --apply 2>&1');
echo "⑤ بنودُ القائمةِ لدورِ المساحة:\n" . preg_replace('~^~m', '   ', trim((string) $out)) . "\n";
echo "═ تمَّ — أعد القياسَ: sidebar_guide_compare · rpr02_field_measure ═\n";

/* ═══ مولِّداتُ النصّ ═════════════════════════════════════════════════════ */
function gdb_q($s) { return "'" . str_replace(array('\\', "'"), array('\\\\', "\\'"), (string) $s) . "'"; }

/** تعريفُ العمودِ حرفًا من المخطَّط — ليردَّه العكسُ كما كان لا كما يُظنّ. */
function gdb_col_defs(mysqli $conn, $table, array $cols)
{
    $out = array();
    if (!$cols) { return $out; }
    $in = array();
    foreach ($cols as $c) { $in[] = "'" . $conn->real_escape_string($c) . "'"; }
    $q = $conn->query("SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_COMMENT
                         FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '"
                     . $conn->real_escape_string($table) . "' AND COLUMN_NAME IN (" . implode(',', $in) . ")");
    while ($q && ($r = $q->fetch_assoc())) {
        $d = $r['COLUMN_TYPE'] . ($r['IS_NULLABLE'] === 'YES' ? ' NULL' : ' NOT NULL');
        if ($r['COLUMN_DEFAULT'] !== null) { $d .= " DEFAULT " . gdb_q($r['COLUMN_DEFAULT']); }
        elseif ($r['IS_NULLABLE'] === 'YES') { $d .= ' DEFAULT NULL'; }
        if ((string) $r['COLUMN_COMMENT'] !== '') { $d .= ' COMMENT ' . gdb_q($r['COLUMN_COMMENT']); }
        $out[$r['COLUMN_NAME']] = $d;
    }
    return $out;
}

function gdb_screen_src($rel, $pl, $S)
{
    $q = 'gdb_q';
    foreach (array('intro' => $pl['title'], 'rule' => 'الحقولُ بترتيبِ دورتِها المستنديّةِ من ورقةِ الدليل',
                   'empty' => 'لا سجلَّ بعدُ في هذا السطح') as $k => $d) {
        if (!isset($pl[$k]) || $pl[$k] === '') { $pl[$k] = $d; }
    }
    $acts = '';
    if (!empty($pl['create'])) {
        if (empty($pl['action_code'])) { $pl['action_code'] = 'gov.' . $pl['code'] . '.register'; }
        $fields = array();
        foreach ($pl['cols'] as $c) {
            if (!in_array($c['key'], $pl['create'], true)) { continue; }
            $fields[$c['key']] = $c['label'];
        }
        $src = "\n    'actions' => array(\n"
             . "        'register' => array(\n"
             . "            'code'  => " . $q($pl['action_code']) . ",\n"
             . "            'label' => " . $q('تسجيلُ ' . $pl['title']) . ",\n"
             . "            'rule'  => " . $q($pl['rule']) . ",\n"
             . "            'fields' => array(\n";
        foreach ($fields as $k => $l) { $src .= "                " . $q($k) . " => " . $q($l) . ",\n"; }
        $src .= "            ),\n"
              . "            'run' => function (\$conn, \$co, \$uid, \$in) {\n"
              . "                \$keys = array(" . implode(', ', array_map($q, array_keys($fields))) . ");\n"
              . "                \$row = array();\n"
              . "                foreach (\$keys as \$k) {\n"
              . "                    \$v = trim((string) (\$in[\$k] ?? ''));\n"
              . "                    if (\$v !== '') { \$row[\$k] = \$v; }\n"
              . "                }\n"
              . "                if (!\$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }\n"
              . "                \$row['created_by'] = \$uid;\n"
              . "                try {\n"
              . "                    ems_tenant_db()->insert(" . $q($pl['table']) . ", \$row);\n"
              . "                } catch (\\Throwable \$t) {\n"
              . "                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');\n"
              . "                }\n"
              . (empty($pl['pk_code']) ? ''
                 /* المفتاحُ المولَّدُ يُشتقُّ من المعرِّفِ بعد الإدراج — «يولّده النظامُ
                    ولا يُحرَّر». ⛔ **وبالبوابةِ لا باستعلامٍ خام**: سقّاطةُ GAP-29
                    ترسّب أيَّ ملفٍّ جديدٍ يلمس جدولَ مستأجِرٍ بـmysqli مباشرةً،
                    والصوابُ أن تمرَّ الكتابةُ ببوابةِ المستأجِرِ أصلًا. */
                 : "                \$nid = (int) \$conn->insert_id;\n"
                 . "                if (\$nid > 0) {\n"
                 . "                    \$code = " . $q($pl['pk_code'][1] . '-') . " . str_pad((string) \$nid, 5, '0', STR_PAD_LEFT);\n"
                 . "                    try {\n"
                 . "                        ems_tenant_db()->update(" . $q($pl['table']) . ",\n"
                 . "                            array(" . $q($pl['pk_code'][0]) . " => \$code), array('id' => \$nid));\n"
                 . "                    } catch (\\Throwable \$t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }\n"
                 . "                }\n")
              . "                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في " . $pl['table'] . "');\n"
              . "            }),\n"
              . "    ),";
        $acts = $src;
    }
    $opt = '';
    foreach (array('where', 'order', 'limit', 'global_ref', 'scope_user') as $k) {
        if (isset($pl[$k]) && $pl[$k] !== '') {
            $opt .= "\n    " . str_pad("'{$k}'", 14) . '=> ' . (is_int($pl[$k]) ? $pl[$k] : $q($pl[$k])) . ',';
        }
    }
    return "<?php\n"
      . "/**\n"
      . " * {$rel} — {$pl['title']} ({$S['dept']} · GOV_EXEC §5)\n"
      . " * " . str_repeat('─', 70) . "\n"
      . " * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`\n"
      . " *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.\n"
      . " *\n"
      . " * الحبّة: {$pl['grain']}\n"
      . " * المالك: {$S['dept_ar']} · مصدرُ الحقيقة: {$pl['table']}\n"
      . " * الأصل: ورقةُ «{$S['dept_ar']}» — السطح «{$pl['surface']}»\n"
      . " *\n"
      . " * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر\n"
      . " *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.\n"
      . " */\n"
      . "\$U13 = array(\n"
      . "    'file'       => " . $q($rel) . ",\n"
      . "    'screen'     => " . $q($pl['code']) . ",\n"
      . "    'table'      => " . $q($pl['table']) . ",\n"
      . "    'title'      => " . $q($pl['title']) . ",\n"
      . "    'icon'       => " . $q(isset($pl['icon']) ? $pl['icon'] : 'fa fa-table') . ",\n"
      . "    'nature'     => " . $q($pl['nature']) . ",\n"
      . "    'doc'        => " . $q(isset($pl['guide_ref']) ? $pl['guide_ref'] : ('01 · الدليل المعماري · ' . $pl['surface'])) . ",\n"
      . "    'intro'      => " . $q($pl['intro']) . ",\n"
      . "    'rule'       => " . $q($pl['rule']) . ",\n"
      . "    'empty_hint' => " . $q($pl['empty']) . ","
      . $opt . "\n"
      . $acts . "\n"
      . ");\n"
      . "require __DIR__ . '/../includes/u13_screen_kit.php';\n";
}

function gdb_migration_src($slug, $S, $stmts, $isDown)
{
    $head = "<?php\n"
      . "/**\n"
      . " * {$slug}" . ($isDown ? '_down' : '') . ".php — "
      . ($isDown ? 'العكسُ المسوّى' : ($S['dept'] . ' · ' . $S['dept_ar'] . ' — جداولُ مواضعِ الدليل'))
      . " (GOV_EXEC §5)\n"
      . " * @migration-objects: " . ($isDown ? 'reverse ' : '') . "tables for {$S['dept']}\n"
      . " * مولَّدةٌ من `tools/gov_exec_dept_build.php --emit` على مواصفةِ الإدارة —\n"
      . " * وأسماءُ الأعمدةِ تعليقُها اسمُ الحقلِ في ورقةِ الدليلِ حرفًا.\n"
      . " */\n"
      . "if (php_sapi_name() !== 'cli') { exit(\"CLI فقط\\n\"); }\n"
      . "error_reporting(E_ALL & ~E_DEPRECATED);\n"
      . "mb_internal_encoding('UTF-8');\n"
      . "\$ROOT = dirname(dirname(__DIR__));\n"
      . "require_once \$ROOT . '/includes/env.php';\n"
      . "require_once __DIR__ . '/_ledger.php';\n"
      . "\$host = ems_env('DB_HOST'); \$port = 3306;\n"
      . "if (strpos(\$host, ':') !== false) { list(\$host, \$port) = explode(':', \$host); \$port = (int) \$port; }\n"
      . "mysqli_report(MYSQLI_REPORT_OFF);\n"
      . "\$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');\n"
      . "\$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');\n"
      . "\$conn = new mysqli(\$host, \$u, \$p, ems_env('DB_NAME'), \$port);\n"
      . "if (\$conn->connect_errno) { exit(\"تعذّر الاتصال: {\$conn->connect_error}\\n\"); }\n"
      . "\$conn->set_charset('utf8mb4');\n"
      . "\$t0 = microtime(true);\n\n"
      . "\$SQL = array(\n";
    $body = '';
    foreach ($stmts as $s) {
        $body .= '    <<<\'SQL\'' . "\n" . $s . "\nSQL,\n";
    }
    $tail = ");\n"
      . "\$n = 0;\n"
      . "foreach (\$SQL as \$s) {\n"
      . "    if (!\$conn->query(\$s)) {\n"
      . "        \$msg = \$conn->error;\n"
      . ($isDown
          ? "        if (stripos(\$msg, \"check that column\") !== false || stripos(\$msg, \"doesn't exist\") !== false) { continue; }\n"
          : "        if (stripos(\$msg, 'Duplicate column') !== false) { continue; }\n")
      . "        exit(\"⛔ {\$msg}\\n  في: \" . substr(\$s, 0, 120) . \"\\n\");\n"
      . "    }\n"
      . "    \$n++;\n"
      . "}\n"
      . "echo \"✔ {\$n} جملةً نُفِّذت\\n\";\n"
      . "ems_migration_recorded(__FILE__, \$conn, (int) round((microtime(true) - \$t0) * 1000));\n";
    return $head . $body . $tail;
}
