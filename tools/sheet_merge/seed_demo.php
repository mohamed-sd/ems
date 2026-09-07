<?php
/**
 * tools/sheet_merge/seed_demo.php — بيانات تجريبيةٌ شبه حقيقيةٍ لأعمدةِ الدمج
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **القيمةُ تُشتقُّ من اسمِ الحقلِ العربيِّ لا من نوعِه وحدَه**: الاسمُ محفوظٌ في
 *   `COMMENT` العمود (‏هجرة `2028_06_01_sheet_merge_fields.php`)، فـ«حالة الأمر»
 *   تأخذ مفردةَ حالاتٍ و«قيمة العرض» رقمًا ماليًّا و«تاريخ الاعتماد» تاريخًا
 *   داخلَ نافذةٍ معقولة — **فالتجربةُ تحتاج قيمةً تُشبه الحقيقةَ لا حشوًا**.
 *
 * ◆ **والجدولُ الفارغُ يُبذَر بصفوفٍ كاملة**: عشرون صفًّا، ومفاتيحُه الأجنبيةُ
 *   **من صفوفٍ حيّةٍ في الشركةِ نفسِها** لا بأرقامٍ مخترَعةٍ تكسر الربط.
 *   وما لم يُوجد له أبٌ حيٌّ **يُترك فارغًا ويُعلَن** — ولا يُلفَّق.
 *
 * ⛔ **ومفرداتُ ENUM من المخطَّطِ لا من الذاكرة**: قيمةٌ خارجَها تُبتلَع `''`
 *   صامتًا (‏گوتشا مثبَّتة) — فالمفردةُ تُقرأ من `COLUMN_TYPE` لحظةَ التوليد.
 *
 * الاستعمال:  php tools/sheet_merge/seed_demo.php [--company=4] [--rows=20] [--apply]
 *             بلا `--apply` يعرض ما سيفعل ولا يكتب شيئًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__, 2);
require_once $ROOT . '/includes/env.php';

$COMPANY = 4; $ROWS = 20; $APPLY = false;
foreach ($argv as $a) {
    if (strpos($a, '--company=') === 0) { $COMPANY = (int) substr($a, 10); }
    elseif (strpos($a, '--rows=') === 0) { $ROWS = (int) substr($a, 7); }
    elseif ($a === '--apply') { $APPLY = true; }
}

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_PASS'); if ($p === null || $p === '') { $p = ems_env('DB_PASS'); }
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_error) { exit('تعذر الاتصال: ' . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');

$plan = json_decode(file_get_contents(__DIR__ . '/plan.json'), true);
if (!$plan) { exit("⛔ تعذّرت قراءةُ plan.json\n"); }

/* ─────────────────────────── مفرداتٌ عربيةٌ للتوليد ─────────────────────── */
$V = array(
    'state'    => array('مسودة', 'قيد المراجعة', 'معتمد', 'منفذ', 'مغلق', 'موقوف'),
    'decision' => array('موافقة', 'موافقة بتحفظ', 'إعادة للاستكمال', 'رفض مسبب'),
    'result'   => array('مطابق', 'مطابق بملاحظة', 'غير مطابق'),
    'check'    => array('اجتاز', 'اجتاز بملاحظة', 'لم يجتز'),
    'type'     => array('تشغيلي', 'تعاقدي', 'طارئ', 'دوري', 'استثنائي'),
    'scope'    => array('كامل الموقع', 'وحدة تشغيلية', 'بند مفرد', 'المشروع كاملا'),
    'severity' => array('منخفضة', 'متوسطة', 'عالية', 'حرجة'),
    'dept'     => array('التشغيل', 'الصيانة', 'المشتريات', 'المالية', 'النقل', 'المستودعات'),
    'person'   => array('محمد إدريس', 'يسن سيد أحمد', 'آدم عمر إبراهيم', 'أروينا داؤود',
                        'مصعب الطيب', 'حسن عبد الله', 'سارة محجوب', 'خالد الأمين'),
    'place'    => array('موقع الروسية', 'موقع أبو حمد', 'ميناء بورتسودان', 'مخزن الخرطوم',
                        'ورشة أم درمان', 'موقع دلقو'),
    'unit'     => array('ساعة', 'يوم', 'طن', 'متر مكعب', 'رحلة', 'وردية'),
    'model'    => array('بالساعة', 'بالوردية', 'بالكمية المنجزة', 'مقطوعية شهرية'),
    'basis'    => array('العقد الأصلي', 'ملحق معتمد', 'أمر تشغيل', 'قرار لجنة'),
    'note'     => array('لا ملاحظات جوهرية على البند.',
                        'روجعت المستندات وطوبقت مع المرجع المعتمد.',
                        'يحتاج متابعة في الدورة القادمة.',
                        'اعتمد بعد استيفاء المرفقات الناقصة.',
                        'سجل للاطلاع ولا يترتب عليه أثر مالي.'),
    'yesno'    => array('نعم', 'لا'),
);

function pick(array $a, $i) { return $a[$i % count($a)]; }

/** مفرداتُ ENUM من المخطَّطِ نفسِه — لا من الذاكرة. */
function enum_values($type) {
    if (stripos($type, 'enum(') !== 0 && stripos($type, 'set(') !== 0) { return null; }
    if (!preg_match('~\((.*)\)~s', $type, $m)) { return null; }
    $out = array();
    foreach (str_getcsv($m[1], ',', "'") as $v) { $out[] = $v; }
    return $out;
}

/** قيمةٌ تُشبه الحقيقةَ لهذا العمود، مشتقّةٌ من اسمِه العربيِّ ونوعِه. */
function demo_value($label, $type, $i, array $V) {
    $t = strtolower($type);
    $L = (string) $label;
    $en = enum_values($type);
    if ($en) { return $en[$i % count($en)]; }

    if (strpos($t, 'tinyint(1)') === 0) { return ($i % 3 === 0) ? 0 : 1; }
    if (strpos($t, 'datetime') === 0 || strpos($t, 'timestamp') === 0) {
        return date('Y-m-d H:i:s', strtotime('2026-03-01 07:00:00') + $i * 9137 * 60);
    }
    if (strpos($t, 'date') === 0) {
        return date('Y-m-d', strtotime('2026-02-01') + $i * 6 * 86400);
    }
    if (strpos($t, 'decimal') === 0 || strpos($t, 'double') === 0 || strpos($t, 'float') === 0
        || strpos($t, 'int') !== false) {
        if (preg_match('~عدد|سجلات|ورديات|أيام|دورات|نسخ~u', $L)) { return 1 + ($i % 40); }
        if (preg_match('~نسبة|معامل|متوسط~u', $L))                { return round(1 + ($i % 90) + ($i % 7) / 10, 2); }
        if (preg_match('~ساعة|ساعات|عداد|مدة|زمن~u', $L))          { return round(4 + ($i % 300) + ($i % 4) * 0.25, 2); }
        if (preg_match('~كمية|وزن|سعة|مسافة~u', $L))               { return round(5 + ($i % 500) * 1.5, 2); }
        return round(1500 + ($i % 97) * 1250.75, 2);
    }

    /* نصّ — والمفردةُ تتبع معنى الاسم */
    if (preg_match('~^حالة|الحالة~u', $L))                       { return pick($V['state'], $i); }
    if (preg_match('~^قرار|القرار~u', $L))                       { return pick($V['decision'], $i); }
    if (preg_match('~^نتيجة~u', $L))                             { return pick($V['result'], $i); }
    if (preg_match('~^فحص|^مطابقة~u', $L))                       { return pick($V['check'], $i); }
    if (preg_match('~^نوع|^فئة|^تصنيف~u', $L))                   { return pick($V['type'], $i); }
    if (preg_match('~^نطاق~u', $L))                              { return pick($V['scope'], $i); }
    if (preg_match('~خطورة|شدة~u', $L))                          { return pick($V['severity'], $i); }
    if (preg_match('~إدارة|الإدارة|جهة|الجهة~u', $L))            { return pick($V['dept'], $i); }
    if (preg_match('~المنشئ|المعتمد|المراجع|المشرف|المنفذ|أمين|مستلم|السائق|المالك|المستفيد|مسؤول|معتمد~u', $L))
                                                                 { return pick($V['person'], $i); }
    if (preg_match('~موقع|مكان|منطقة|إقليم|مخزن~u', $L))         { return pick($V['place'], $i); }
    if (preg_match('~^وحدة|الوحدة~u', $L))                       { return pick($V['unit'], $i); }
    if (preg_match('~نموذج|أسلوب~u', $L))                        { return pick($V['model'], $i); }
    if (preg_match('~^أساس|مرجع|مصدر|سند|مفتاح~u', $L))          { return pick($V['basis'], $i) . ' #' . (1000 + $i); }
    if (preg_match('~ملاحظ|وصف|تفسير|مبرر|سبب|تحليل|تفصيل~u', $L)) { return pick($V['note'], $i); }
    if (preg_match('~؟$~u', $L))                                 { return pick($V['yesno'], $i); }
    if (preg_match('~^رقم|^كود|^معرف|^تسلسل~u', $L)) {
        return 'EMS-' . strtoupper(substr(md5($L), 0, 3)) . '-' . str_pad((string) (1 + $i), 4, '0', STR_PAD_LEFT);
    }
    if (preg_match('~عملة~u', $L)) { return ($i % 3 === 0) ? 'USD' : (($i % 3 === 1) ? 'SDG' : 'AED'); }
    if (preg_match('~شهر~u', $L))  { return date('Y-m', strtotime('2026-01-01') + $i * 31 * 86400); }
    return $L . ' — قيد ' . (1 + $i);
}

/* ───────────────────────── معلوماتُ الأعمدةِ من المخطَّط ────────────────── */
function cols_meta(mysqli $c, $t) {
    $out = array();
    $q = $c->query("SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, COLUMN_COMMENT
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . $c->real_escape_string($t) . "'
                    ORDER BY ORDINAL_POSITION");
    if (!$q) { return $out; }
    while ($x = $q->fetch_assoc()) { $out[$x['COLUMN_NAME']] = $x; }
    return $out;
}

/** الجدولُ الأبُ المرجَّحُ لعمودِ معرِّفٍ — بالاسمِ ثم بوجودِه حيًّا. */
function parent_table(mysqli $c, $tbl, $col) {
    $q = $c->query("SELECT REFERENCED_TABLE_NAME t FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . $c->real_escape_string($tbl) . "'
                      AND COLUMN_NAME='" . $c->real_escape_string($col) . "'
                      AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1");
    if ($q && ($r = $q->fetch_assoc())) { return $r['t']; }
    $base = preg_replace('~_id$~', '', $col);
    /* «origin_order» و«parent_order» أبوهما «order» — الوصفُ قبلَ الاسمِ يُسقَط */
    $tail = preg_replace('~^(origin|parent|source|target|from|to|new|old)_~', '', $base);
    $pre  = explode('_', $tbl); $pre = $pre[0];
    $plural = function ($s) { return substr($s, -1) === 'y' ? substr($s, 0, -1) . 'ies' : $s . 's'; };
    $try  = array();
    foreach (array_unique(array($base, $tail)) as $b) {
        $try = array_merge($try, array($pre . '_' . $b, $b, $plural($b), rtrim($b, 's'),
                                       'proc_' . $b, 'trp_' . $b, 'mnt_' . $b,
                                       'transfer_' . $plural($b)));
    }
    if ($base === 'order')    { array_unshift($try, $pre . '_order', 'transfer_orders', 'proc_order', 'mnt_order'); }
    if ($base === 'supplier') { array_unshift($try, 'proc_supplier', 'suppliers'); }
    if ($base === 'equipment'){ array_unshift($try, 'equipments'); }
    if ($base === 'item')     { array_unshift($try, 'proc_item'); }
    /* أسماءٌ لا يدلُّ اشتقاقُها على أبيها — تُصرَّح ولا تُخمَّن */
    $ALIAS = array('winner' => 'proc_offer', 'receipt' => 'proc_receipt_custody',
                   'requester' => 'users', 'from_wh' => 'proc_warehouse',
                   'to_wh' => 'proc_warehouse', 'delivery_doc' => 'proc_delivery_event', 'lowest' => 'proc_offer',
                   'maintenance_order' => 'mnt_order', 'work_order' => 'mnt_order',
                   'approved_by' => 'users', 'reviewed_by' => 'users', 'closed_by' => 'users');
    if (isset($ALIAS[$base])) { array_unshift($try, $ALIAS[$base]); }
    if (in_array($base, array('created_by', 'prepared_by', 'user'), true)) { array_unshift($try, 'users'); }
    foreach ($try as $t) {
        $r = $c->query("SELECT 1 FROM information_schema.TABLES
                        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . $c->real_escape_string($t) . "' LIMIT 1");
        if ($r && $r->num_rows) { return $t; }
    }
    return null;
}

function live_ids(mysqli $c, $t, $company) {
    $has = $c->query("SELECT 1 FROM information_schema.COLUMNS
                      WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . $c->real_escape_string($t) . "'
                        AND COLUMN_NAME='company_id' LIMIT 1");
    $w = ($has && $has->num_rows) ? " WHERE company_id = " . (int) $company : '';
    $r = $c->query("SELECT id FROM `$t`$w ORDER BY id LIMIT 60");
    $out = array();
    if ($r) { while ($x = $r->fetch_assoc()) { $out[] = (int) $x['id']; } }
    if (!$out && $w !== '') {
        $r = $c->query("SELECT id FROM `$t` ORDER BY id LIMIT 60");
        if ($r) { while ($x = $r->fetch_assoc()) { $out[] = (int) $x['id']; } }
    }
    return $out;
}

/* ─────────────────────────────── التنفيذ ───────────────────────────────── */
$byOwner = array();
foreach ($plan as $p) {
    foreach ($p['cols'] as $c) { $byOwner[$p['owner']][$c['col']] = $c['label']; }
}

/* ◆ **آباءٌ مُمكِّنون**: جدولان خارجَ الخطةِ لكنّ صفَّين من صفوفِ الخطةِ لا يقومان
 *   بدونهما (‏`proc_offer` و`proc_award` أبوهما `proc_rfq`، وأبوه `proc_package`).
 *   يُبذَران بأقلِّ ما يلزم — وإن كان فيهما صفٌّ واحدٌ لم يُمَسّا. */
$ENABLERS = array('proc_package' => 6, 'proc_rfq' => 6);   /* بترتيبِ النسب: الأبُ أوّلًا */
$ROWS_BY = array();
$first   = array();
foreach ($ENABLERS as $tbl => $n) {
    if (isset($byOwner[$tbl])) { continue; }
    $c = $conn->query("SELECT COUNT(*) c FROM `$tbl` WHERE company_id = " . (int) $COMPANY);
    if (!$c || (int) $c->fetch_assoc()['c'] > 0) { continue; }
    $first[$tbl] = array();
    $ROWS_BY[$tbl] = $n;
}
$byOwner = $first + $byOwner;   /* الآباءُ يُبذَرون قبلَ أبنائهم — وإلّا سقط الابن */

$filled = 0; $inserted = 0; $skipped = array(); $blockedRows = array();
foreach ($byOwner as $tbl => $newCols) {
    $meta = cols_meta($conn, $tbl);
    if (!$meta) { $skipped[$tbl] = 'جدول غائب'; continue; }
    $r = $conn->query("SELECT COUNT(*) c FROM `$tbl` WHERE company_id = " . (int) $COMPANY);
    $have = $r ? (int) $r->fetch_assoc()['c'] : 0;

    /* ① جدولٌ فارغٌ — يُبذَر بصفوفٍ كاملة */
    if ($have === 0) {
        /* ⛔ **لا يكفي ملءُ الإلزاميِّ**: لهذه الجداولِ قيودُ `CHECK` من طرازِ
         *   «`state_rule` <> ''» على أعمدةٍ افتراضُها السلسلةُ الفارغة — فالصفُّ
         *   المكتفي بالإلزاميِّ **يُردُّ بقيدٍ لا بخطأِ نوع**. فيُملأ كلُّ عمودٍ
         *   إلّا المولَّدَ ذاتيًّا وأختامَ الزمنِ التي يكتبها المحرِّك. */
        $req = array();
        foreach ($meta as $col => $m) {
            if (isset($newCols[$col])) { continue; }
            if (strpos($m['EXTRA'], 'auto_increment') !== false) { continue; }
            if (stripos($m['EXTRA'], 'GENERATED') !== false) { continue; }
            if (in_array($col, array('created_at', 'updated_at', 'deleted_at'), true)) { continue; }
            $req[$col] = $m;
        }
        $parents = array(); $blocked = null; $drop = array();
        foreach ($req as $col => $m) {
            if ($col === 'company_id') { continue; }
            if (!preg_match('~_id$~', $col) && !in_array($col, array('created_by', 'prepared_by'), true)) { continue; }
            $pt = parent_table($conn, $tbl, $col);
            $ids = $pt ? live_ids($conn, $pt, $COMPANY) : array();
            if ($ids) { $parents[$col] = $ids; continue; }
            /* لا أبَ حيّ: يُترك NULL إن جاز، وإلّا وقف الجدولُ كلُّه ولا يُلفَّق رقم */
            if ($m['IS_NULLABLE'] === 'YES') { $drop[$col] = true; continue; }
            $blocked = $col . ' (' . ($pt ?: 'لا أب') . ')'; break;
        }
        if ($blocked !== null) { $skipped[$tbl] = 'لا سجل أب حي لـ' . $blocked; continue; }
        foreach ($drop as $col => $_) { unset($req[$col]); }

        $want = isset($ROWS_BY[$tbl]) ? $ROWS_BY[$tbl] : $ROWS;
        for ($i = 0; $i < $want; $i++) {
            $f = array(); $v = array(); $seen2 = array();
            foreach ($req as $col => $m) {
                $f[] = "`$col`";
                if ($col === 'company_id') { $v[] = (int) $COMPANY; continue; }
                if (isset($parents[$col])) {
                    /* عمودان يشيران لأبٍ واحدٍ لا يأخذان الصفَّ نفسَه — `chk_trf_diff`
                       يشترط «من مخزنٍ» غيرَ «إلى مخزن»، فيُزاح المؤشِّرُ بترتيبِ الظهور. */
                    $k = isset($seen2[$col]) ? $seen2[$col] : ($seen2[$col] = count($seen2));
                    $n = count($parents[$col]);
                    $v[] = $parents[$col][($i + $k) % $n];
                    continue;
                }
                /* ⛔ **عمودُ الحالةِ يُثبَّت على أوّلِ مفردة**: قيودُ `CHECK` المشروطةُ
                 *   تعلّق على الحالاتِ المتقدّمةِ شروطًا (‏موقِّعٌ ووقتُ اعتمادٍ وعدّادٌ
                 *   منشور)، فصفٌّ بحالةٍ متقدّمةٍ يُردّ. والحالةُ الأولى مسودةٌ دائمًا. */
                if (in_array($col, array('state', 'status'), true) && ($ev = enum_values($m['COLUMN_TYPE']))) {
                    $val = $ev[0];
                } else {
                    $val = demo_value($m['COLUMN_COMMENT'] !== '' ? $m['COLUMN_COMMENT'] : $col,
                                      $m['COLUMN_TYPE'], $i, $V);
                }
                if (preg_match('~idem_key|_no$|_key$~', $col)) {
                    $val = strtoupper(substr($tbl, 0, 6)) . '-' . date('Ymd') . '-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);
                    $val = substr($val, 0, 28);
                }
                $v[] = is_numeric($val) && strpos($m['COLUMN_TYPE'], 'char') === false
                     ? $val : "'" . $conn->real_escape_string((string) $val) . "'";
            }
            foreach ($newCols as $col => $lab) {
                if (!isset($meta[$col]) || isset($req[$col])) { continue; }
                $f[] = "`$col`";
                $val = demo_value($lab, $meta[$col]['COLUMN_TYPE'], $i, $V);
                $v[] = is_numeric($val) && strpos($meta[$col]['COLUMN_TYPE'], 'char') === false
                     ? $val : "'" . $conn->real_escape_string((string) $val) . "'";
            }
            $sql = "INSERT INTO `$tbl` (" . implode(',', $f) . ") VALUES (" . implode(',', $v) . ")";
            if (!$APPLY) { $inserted++; continue; }
            if ($conn->query($sql)) { $inserted++; continue; }
            /* صفٌّ يردُّه مفتاحٌ فريدٌ لا يوقف الجدول: «ترسيةٌ واحدةٌ لكلِّ طلبِ عروض»
               حكمُ عملٍ لا عطبُ بذر — فيُعدُّ ويُعلَن ويمضي البذرُ لما بعده. */
            $skipped[$tbl] = 'صفوفٌ ردّها المخطَّط: ' . $conn->error;
            if (stripos($conn->error, 'Duplicate entry') === false) { break; }
        }
        continue;
    }

    /* ② جدولٌ فيه صفوفٌ — تُملأ أعمدتُه الجديدةُ وحدَها */
    $ids = live_ids($conn, $tbl, $COMPANY);
    $r = $conn->query("SELECT id FROM `$tbl` WHERE company_id = " . (int) $COMPANY . " ORDER BY id");
    $ids = array();
    if ($r) { while ($x = $r->fetch_assoc()) { $ids[] = (int) $x['id']; } }
    foreach ($ids as $i => $id) {
        $set = array();
        foreach ($newCols as $col => $lab) {
            if (!isset($meta[$col])) { continue; }
            $val = demo_value($lab, $meta[$col]['COLUMN_TYPE'], $i, $V);
            $set[] = "`$col` = " . (is_numeric($val) && strpos($meta[$col]['COLUMN_TYPE'], 'char') === false
                    ? $val : "'" . $conn->real_escape_string((string) $val) . "'");
        }
        if (!$set) { continue; }
        if (!$APPLY) { $filled++; continue; }
        if ($conn->query("UPDATE `$tbl` SET " . implode(',', $set) . " WHERE id = " . (int) $id)) { $filled++; }
        else { $blockedRows[$tbl] = (isset($blockedRows[$tbl]) ? $blockedRows[$tbl] : 0) + 1;
               $skipped[$tbl] = 'صفوفٌ ردّها حارسُ عملٍ: ' . $conn->error; }
    }
}

printf("%s — صفوفٌ مُلئت: %d · صفوفٌ أُدرجت: %d · جداولُ مُتخطّاة: %d\n",
       $APPLY ? 'تنفيذ' : 'عرضٌ فقط (بلا --apply)', $filled, $inserted, count($skipped));
foreach ($skipped as $t => $why) { printf("   ⚠ %-24s %s\n", $t, $why); }
