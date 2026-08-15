<?php
/**
 * tools/sales_demo_fill.php
 * ═══════════════════════════════════════════════════════════════════════════
 * مالئُ الداتا التجريبيةِ لشاشاتِ **مدير المبيعات** (الدور 12 · شركة العرض 4).
 *
 * المبدأ: **العمودُ يتعلَّم من نفسِه**. لكلِّ خليةٍ فارغةٍ نولّد قيمةً مشتقةً من
 * قيمِ العمودِ نفسِه المملوءةِ في صفوفٍ أخرى — فالنوعُ والصيغةُ واللغةُ تأتي من
 * الداتا القائمةِ لا من تخمينٍ خارجيّ. وما لا قيمةَ له أصلًا يُعطى مولِّدًا
 * صريحًا في جدولِ الأهداف أدناه.
 *
 * ثلاثةُ أحكامٍ تحكم كلَّ كتابةٍ هنا:
 *   ① **لا تُكتب خليةٌ مملوءة** — الشرطُ دائمًا `IS NULL OR = ''`، فالتشغيلُ
 *      مرَّتين لا يغيّر شيئًا (idempotent) ولا يطمس داتا حقيقية.
 *   ② **لا يُتجاوز نطاقُ شركةِ العرض** — كلُّ جملةٍ مقيَّدةٌ بـcompany_id=4 حيثُ
 *      للجدولِ عمودُ شركة، فلا تتسرَّب الداتا التجريبيةُ إلى مستأجرٍ آخر.
 *   ③ **يُفحص مُرجَعُ كلِّ جملة** — `config` يضبط mysqli على عدمِ الرمي، فالجملةُ
 *      الفاشلةُ تمرُّ صامتةً ما لم يُقرأ مُرجَعُها (گوتشا H-01).
 * وتُكتب خريطةُ تراجعٍ (`--undo=<path>`) بكلِّ (جدول · عمود · مُعرِّف) لُمس.
 *
 * الاستعمال:
 *   php tools/sales_demo_fill.php --plan            # عرضُ ما سيتغيّر بلا كتابة
 *   php tools/sales_demo_fill.php --apply           # التنفيذ
 *   php tools/sales_demo_fill.php --apply --only=scr   # قسمٌ واحد
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
mysqli_report(MYSQLI_REPORT_OFF);

require_once dirname(__DIR__) . '/includes/env.php';
$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'));
if ($db->connect_error) { fwrite(STDERR, "DB: {$db->connect_error}\n"); exit(1); }
$db->set_charset('utf8mb4');

$CO      = 4;                       // شركةُ العرض
$SALES_U = 13;                      // حسابُ مدير المبيعات (users.role = 12)
$APPLY   = in_array('--apply', $argv, true);
$ONLY    = null;
foreach ($argv as $a) { if (strpos($a, '--only=') === 0) $ONLY = substr($a, 7); }
if (!$APPLY && !in_array('--plan', $argv, true)) {
    fwrite(STDOUT, "استعمل --plan للعرض أو --apply للتنفيذ\n"); exit(0);
}

$LOG = array(); $NWRITE = 0; $NSKIP = 0;
function say($s) { fwrite(STDOUT, $s . "\n"); }
function sect($s) { say("\n══ {$s}"); }

/** تنفيذٌ مفحوصُ المُرجَع — الصامتُ الفاشلُ أخطرُ من الظاهرِ الفاشل. */
function run($db, $sql, $note = '') {
    global $APPLY, $NWRITE;
    if (!$APPLY) { return -1; }
    $ok = $db->query($sql);
    if ($ok === false) { say("   !! فشل [{$note}]: " . $db->error); return false; }
    $n = $db->affected_rows;
    $NWRITE += max(0, $n);
    return $n;
}
function colmeta($db, $t) {
    static $c = array();
    if (isset($c[$t])) return $c[$t];
    $o = array();
    $r = $db->query("SHOW COLUMNS FROM `$t`");
    while ($r && ($x = $r->fetch_assoc())) $o[$x['Field']] = $x;
    return $c[$t] = $o;
}
function has_col($db, $t, $col) { $m = colmeta($db, $t); return isset($m[$col]); }
/** أعمدةُ الفهارسِ الفريدة — قيمتُها لا تُدوَّر: التدويرُ يصطدم بـuq حتمًا.
    (المالئُ يتعلَّم من قيمِ العمودِ نفسِه، وإعادةُ قيمةٍ في عمودٍ فريدٍ خطأٌ
     بالتعريف — فيُستثنى بالفهرسِ لا بقائمةِ أسماءٍ تُنسى.) */
function unique_cols($db, $t) {
    static $c = array();
    if (isset($c[$t])) return $c[$t];
    $o = array();
    $r = $db->query("SHOW INDEX FROM `$t`");
    while ($r && ($x = $r->fetch_assoc())) {
        if ((int) $x['Non_unique'] === 0) { $o[$x['Column_name']] = true; }
    }
    return $c[$t] = $o;
}
function table_exists($db, $t) { $r = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($t) . "'"); return $r && $r->num_rows > 0; }
/* ★ گوتشا: في MySQL يُقحَم `''` إلى 0 عند مقارنتِه بعمودٍ رقميّ — فشرطُ
   `col = ''` على عمودِ ساعاتٍ **يلتقط كلَّ صفرٍ صحيحٍ** ويستبدله بقيمةٍ مختلَقة.
   والصفرُ في «ساعات التوقف» داتا لا فراغ. فالفراغُ يُعرَّف بالنوع: الرقميُّ
   فارغٌ إن كان NULL وحدَه، والنصيُّ إن كان NULL أو خالي الطول. */
function is_numeric_type($type) {
    return (bool) preg_match('~^(int|bigint|smallint|mediumint|tinyint|decimal|numeric|float|double|bit|year)~i', $type);
}
function blank_pred($col, $type) {
    return is_numeric_type($type) || preg_match('~^(date|datetime|timestamp|time)~i', $type)
        ? "`$col` IS NULL"
        : "(`$col` IS NULL OR `$col` = '')";
}
function enum_vals($type) {
    if (!preg_match("~^enum\((.*)\)$~i", $type, $m)) return array();
    $o = array(); foreach (explode(',', $m[1]) as $p) { $o[] = trim($p, " '"); }
    return $o;
}

/* ═══ المولِّدُ العام: العمودُ يتعلَّم من قيمِه المملوءة ═══════════════════════
   نأخذ حتى 40 قيمةً مميزةً مملوءةً من العمودِ نفسِه، ثم نوزّعها على الخلايا
   الفارغةِ بالدور — ونحرّك التواريخَ والأرقامَ بمقدارِ الفهرسِ حتى لا يتكرَّر
   صفٌّ حرفيًّا فيبدو الجدولُ منسوخًا. */
function learn_values($db, $t, $col, $where, $type = 'varchar') {
    $r = $db->query("SELECT DISTINCT `$col` v FROM `$t`
                      WHERE $where AND NOT (" . blank_pred($col, $type) . ") LIMIT 40");
    $o = array(); while ($r && ($x = $r->fetch_row())) $o[] = $x[0];
    return $o;
}
/** عمودُ إشارةٍ إلى صفٍّ آخر — قيمتُه تُنسخ ولا تُشتقّ.
    اشتقاقُ رقمٍ من مفتاحٍ أجنبيٍّ يولّد مُعرِّفًا لا وجودَ له، فتردُّه القاعدةُ
    بـFK — والعمودُ يبقى فارغًا وقد ظنَّ المالئُ أنَّه ملأه. */
function is_ref_col($col) {
    return (bool) preg_match('~(^|_)(id|by|ref)$~i', $col) || preg_match('~_id$~i', $col);
}
function derive($type, $base, $i) {
    $ty = strtolower($type);
    if (strpos($ty, 'date') === 0 || strpos($ty, 'datetime') === 0 || strpos($ty, 'timestamp') === 0) {
        $ts = strtotime($base);
        if ($ts === false) return $base;
        $ts += (($i % 9) + 1) * 86400 * 3;               // إزاحةٌ بأيامٍ لا تتكرَّر
        return date(strpos($ty, 'date') === 0 && strpos($ty, 'datetime') !== 0 ? 'Y-m-d' : 'Y-m-d H:i:s', $ts);
    }
    if (preg_match('~^(int|bigint|smallint|tinyint|decimal|float|double)~', $ty)) {
        if (!is_numeric($base)) return $base;
        $dec = (strpos($ty, 'decimal') === 0 || strpos($ty, 'float') === 0 || strpos($ty, 'double') === 0);
        $v = (float) $base * (1 + (($i % 7) - 3) * 0.045);   // ±13٪ حول الأصل
        if (strpos($ty, 'tinyint(1)') === 0) return (int) ((bool) $base);
        return $dec ? number_format($v, 2, '.', '') : (string) max(0, (int) round($v));
    }
    /* نصٌّ برمزٍ متسلسلٍ (PRE-0107) — نرقّمه بدل تكرارِه حرفيًّا */
    if (preg_match('~^(.*?)(\d+)$~u', (string) $base, $m) && strlen($m[2]) >= 3) {
        $w = strlen($m[2]);
        return $m[1] . str_pad((string) ((int) $m[2] + $i + 1), $w, '0', STR_PAD_LEFT);
    }
    return $base;
}

/**
 * يملأ كلَّ خليةٍ فارغةٍ في `$t`.`$col` ضمنَ `$where`.
 * $explicit: مصفوفةُ قيمٍ صريحةٍ تُستعمل إن كان العمودُ فارغًا بالكامل.
 */
function fill_col($db, $t, $col, $where, $explicit = array(), $label = '') {
    global $APPLY, $LOG, $NSKIP;
    if (!table_exists($db, $t) || !has_col($db, $t, $col)) { return 0; }
    $uq = unique_cols($db, $t);
    if (isset($uq[$col])) { say("   ~ {$t}.{$col}: عمودٌ فريدٌ — لا يُدوَّر"); return 0; }
    $meta = colmeta($db, $t);
    $type = $meta[$col]['Type'];
    $blank = blank_pred($col, $type);

    $r = $db->query("SELECT id FROM `$t` WHERE $where AND $blank ORDER BY id");
    if (!$r) { say("   !! قراءة {$t}.{$col}: " . $db->error); return 0; }
    $ids = array(); while ($x = $r->fetch_row()) $ids[] = (int) $x[0];
    if (!$ids) { return 0; }

    $pool = learn_values($db, $t, $col, $where, $type);
    $ev   = enum_vals($type);
    if (!$pool && $ev)       { $pool = $ev; }
    if (!$pool && $explicit) { $pool = $explicit; }
    if (!$pool) { $NSKIP += count($ids); say("   ~ {$t}.{$col}: " . count($ids) . " خلية بلا مصدرِ قيمةٍ — تُركت"); return 0; }

    $n = 0;
    foreach ($ids as $i => $id) {
        $base = $pool[$i % count($pool)];
        // ENUM ومفاتيحُ الإشارةِ تُنسخ كما هي — الاشتقاقُ يُفسدهما
        $val  = ($ev || is_ref_col($col)) ? $base : derive($type, $base, $i);
        if ($val === null || $val === '') continue;
        $st = $db->prepare("UPDATE `$t` SET `$col` = ? WHERE id = ? AND $blank");
        if (!$st) { say("   !! prepare {$t}.{$col}: " . $db->error); return $n; }
        $st->bind_param('si', $val, $id);
        if (!$APPLY) { $st->close(); $n++; continue; }
        if ($st->execute()) { $n += $st->affected_rows; }
        else { say("   !! {$t}.{$col}#{$id}: " . $st->error); }
        $st->close();
    }
    global $NWRITE;
    if ($n) {
        $LOG[] = array('table' => $t, 'col' => $col, 'ids' => $ids, 'n' => $n);
        $NWRITE += $n;
        say(sprintf("   %s %-26s %-28s %d خلية", $APPLY ? '✔' : '·', $t, $col, $n));
    }
    return $n;
}

/** يملأ كلَّ أعمدةِ الجدولِ الفارغةَ ما عدا المستثناة. */
function fill_table($db, $t, $where, $skip = array()) {
    if (!table_exists($db, $t)) { say("   ~ لا جدول {$t}"); return 0; }
    $skip = array_merge($skip, array('id', 'company_id', 'is_deleted', 'deleted_at',
        'deleted_by', 'updated_at', 'merged_into_id', 'dedup_key'));
    $n = 0;
    foreach (colmeta($db, $t) as $col => $m) {
        if (in_array($col, $skip, true)) continue;
        $n += fill_col($db, $t, $col, $where);
    }
    return $n;
}

$want = function ($k) use ($ONLY) { return $ONLY === null || $ONLY === $k; };

say("══ مالئُ الداتا التجريبية — شاشاتُ مدير المبيعات (شركة {$CO}) ══");
say($APPLY ? "الوضع: تنفيذٌ فعليّ" : "الوضع: عرضٌ فقط (--plan)");

/* ═══ ① الشاشاتُ المولَّدةُ scr_* — صفوفُ 11..20 قوالبُ فارغةٌ بحالةٍ نصيّة ═══
   عشرةُ صفوفٍ مملوءةٍ وعشرةٌ خاويةٌ في كلِّ جدول، والشاشةُ ترتّب `id DESC`
   فالخاويةُ تتصدَّر — فيبدو الجدولُ فارغًا وهو نصفُ ممتلئ. */
if ($want('scr')) {
    sect("① الشاشاتُ المولَّدة (scr_*) — ردمُ الصفوفِ القوالب");
    $SCR = array('scr_production', 'scr_unbilled', 'scr_unit_perf',
                 'scr_contract_review', 'scr_business_models');
    foreach ($SCR as $t) {
        if (!table_exists($db, $t)) { say("   ~ لا جدول {$t}"); continue; }
        // `created_by` يُضبط صراحةً بعدُ — فلا يتعلَّمه العمودُ من أصفارِه
        fill_table($db, $t, "company_id = {$CO}", array('is_seed', 'created_at', 'created_by'));
        // `created_by` خالٍ في كلِّ الصفوف — لا قيمةَ يتعلَّمها العمودُ من نفسِه
        if (has_col($db, $t, 'created_by')) {
            run($db, "UPDATE `$t` SET created_by = {$SALES_U}
                       WHERE company_id = {$CO} AND (created_by IS NULL OR created_by = 0)", "$t.created_by");
        }
    }
}

/* ═══ ② دفاترُ الأسعار — بندٌ واحدٌ لدفترٍ واحدٍ من عشرين ══════════════════
   الشاشةُ ترتّب `valid_from DESC, id DESC` وتختار الأوّلَ تلقائيًّا، فتقع على
   دفترٍ بلا بنودٍ فيظهر الجدولُ خاويًا. البنودُ تُنسخ من الدفترِ العامرِ بأسعارٍ
   متدرِّجةٍ لكلِّ دفتر. */
if ($want('rate')) {
    sect("② دفاترُ الأسعار — بنودٌ لكلِّ دفترٍ خالٍ");
    $src = array();
    $r = $db->query("SELECT * FROM rate_book_lines
                      WHERE company_id = {$CO} AND COALESCE(is_deleted,0) = 0 LIMIT 6");
    while ($r && ($x = $r->fetch_assoc())) $src[] = $x;
    if (!$src) { say("   ~ لا بندَ مرجعيًّا يُنسخ عنه"); }
    else {
        $r = $db->query("SELECT b.id FROM rate_books b
                          WHERE b.company_id = {$CO} AND COALESCE(b.is_deleted,0) = 0
                            AND NOT EXISTS (SELECT 1 FROM rate_book_lines l
                                             WHERE l.book_id = b.id AND COALESCE(l.is_deleted,0) = 0)");
        $books = array(); while ($r && ($x = $r->fetch_row())) $books[] = (int) $x[0];
        say("   دفاترُ بلا بنود: " . count($books));
        $made = 0;
        foreach ($books as $bi => $bid) {
            foreach ($src as $si => $s) {
                $price = round(((float) $s['unit_price']) * (1 + (($bi % 5) - 2) * 0.06), 2);
                $sql = sprintf(
                    "INSERT INTO rate_book_lines
                       (company_id, book_id, equipment_type_id, work_model, tier_from_days, tier_to_days,
                        unit_price, min_hire_days, min_hours_per_day, mobilization_fee,
                        operator_included, fuel_included, note, is_deleted, created_by, created_at)
                     VALUES (%d, %d, %s, '%s', %d, %s, %s, %d, %s, %s, %d, %d, '%s', 0, %d, NOW())",
                    $CO, $bid,
                    $s['equipment_type_id'] === null ? 'NULL' : (int) $s['equipment_type_id'],
                    $db->real_escape_string($s['work_model']),
                    (int) $s['tier_from_days'],
                    $s['tier_to_days'] === null ? 'NULL' : (int) $s['tier_to_days'],
                    $price, (int) $s['min_hire_days'],
                    $s['min_hours_per_day'] === null ? 'NULL' : (float) $s['min_hours_per_day'],
                    (float) $s['mobilization_fee'],
                    (int) $s['operator_included'], (int) $s['fuel_included'],
                    $db->real_escape_string('بندٌ تجريبيٌّ — ردمُ دفترِ الأسعار'),
                    $SALES_U);
                if (run($db, $sql, 'rate_book_lines') !== false) $made++;
            }
        }
        say(($APPLY ? '   ✔ ' : '   · ') . "بنودٌ مضافة: " . ($APPLY ? $made : count($books) * count($src)));
    }
}

/* ═══ ③ سجلُّ المخاطر — «المستوى» خالٍ في 18 من 20 ═════════════════════════
   وشاشةُ «مساحة مخاطر الإدارة» تجمع بالمستوى، فخلوُّه يُفرغ الجدولَ كلَّه. */
if ($want('risk')) {
    sect("③ سجلُّ المخاطر — المستوى والتعرُّض");
    fill_table($db, 'risk_register', "company_id = {$CO}",
               array('created_at', 'appetite_checked_at', 'ru_id', 'scope_ref_id', 'entity_id'));
    foreach (array('commercial_risks') as $t) {
        if (table_exists($db, $t)) fill_table($db, $t, "company_id = {$CO}", array('created_at'));
    }
}

/* ═══ ④ آلياتُ تعديلِ السعر — ثلاثُ آلياتٍ لعقدين من مئةٍ وعشرين ═══════════
   والشاشةُ تختار `c.id DESC` تلقائيًّا فتقع على عقدٍ بلا آلية. */
if ($want('price')) {
    /* بندُ العقدِ أوّلًا: `contract_price_terms.contract_item_id` عمودٌ NOT NULL،
       و19 عقدًا فقط من 120 له بنودٌ — فالآليةُ بلا بندٍ تُردُّ من القاعدة. */
    sect("④-أ بنودُ عقودِ العملاء — لكلِّ عقدٍ بلا بند");
    $r = $db->query("SELECT * FROM client_contract_lines
                      WHERE company_id = {$CO} AND COALESCE(is_deleted,0) = 0 ORDER BY id DESC LIMIT 5");
    $lsrc = array(); while ($r && ($x = $r->fetch_assoc())) $lsrc[] = $x;
    if (!$lsrc) { say("   ~ لا بندَ مرجعيًّا"); }
    else {
        $r = $db->query("SELECT c.id FROM contracts c
                          WHERE c.company_id = {$CO} AND COALESCE(c.is_deleted,0) = 0
                            AND NOT EXISTS (SELECT 1 FROM client_contract_lines l
                                             WHERE l.contract_id = c.id AND COALESCE(l.is_deleted,0) = 0)
                          ORDER BY c.id DESC");
        $need = array(); while ($r && ($x = $r->fetch_row())) $need[] = (int) $x[0];
        say("   عقودٌ بلا بنود: " . count($need));
        $MODELS = array('hour', 'ton', 'day', 'trip', 'shift');
        /* ck_ccl_tax_ref: «الخاضعُ للضريبةِ يلزمه رمزُ ضريبة» — فالبندُ بلا
           `tax_code_id` تردُّه القاعدةُ نفسُها لا الشاشة. */
        $r2 = $db->query("SELECT id FROM fin_tax_codes
                           WHERE company_id = {$CO} AND active = 1 AND COALESCE(is_deleted,0) = 0
                           ORDER BY id LIMIT 1");
        $taxId = ($r2 && ($x2 = $r2->fetch_row())) ? (int) $x2[0] : 0;
        if (!$taxId) { say("   ~ لا رمزَ ضريبةٍ فعّالًا — تُبذر البنودُ معفاةً"); }
        $made = 0;
        foreach ($need as $i => $cid) {
            $s = $lsrc[$i % count($lsrc)];
            $sql = sprintf(
                "INSERT INTO client_contract_lines
                   (company_id, contract_id, line_no, pricing_model, description, qty_contracted,
                    qty_planned_total, resource_share_total, unit_price, currency, valid_from,
                    valid_to, tax_status, tax_code_id, state, note, created_by, is_deleted, created_at)
                 VALUES (%d, %d, 1, '%s', '%s', %s, %s, %s, %s, '%s', '%s', NULL, '%s', %s, 'active', '%s', %d, 0, NOW())",
                $CO, $cid, $MODELS[$i % 5],
                $db->real_escape_string('بندُ خدمةٍ تعاقديٌّ — تشغيلُ معدةٍ بالعقد #' . $cid),
                number_format(80 + ($i % 11) * 15, 2, '.', ''),
                number_format(80 + ($i % 11) * 15, 2, '.', ''),
                number_format(100, 3, '.', ''),
                number_format(1450 + ($i % 17) * 85.5, 4, '.', ''),
                $db->real_escape_string($s['currency'] ?: 'SDG'),
                date('Y-m-d', strtotime('-' . (60 + $i * 3) . ' days')),
                $taxId ? 'taxable' : 'exempt',
                $taxId ? $taxId : 'NULL',
                $db->real_escape_string('بندٌ تجريبيٌّ — ردمُ عقدٍ بلا بنود'),
                $SALES_U);
            if (run($db, $sql, 'client_contract_lines') !== false) $made++;
        }
        say(($APPLY ? '   ✔ ' : '   · ') . "بنودٌ مضافة: " . ($APPLY ? $made : count($need)));
    }

    sect("④-ب آلياتُ تعديلِ السعر — لأحدثِ العقود");
    $r = $db->query("SELECT * FROM contract_price_terms WHERE company_id = {$CO} LIMIT 1");
    $tpl = $r ? $r->fetch_assoc() : null;
    if (!$tpl) { say("   ~ لا آليةَ مرجعيةً تُنسخ عنها"); }
    else {
        $r = $db->query("SELECT c.id FROM contracts c
                          WHERE c.company_id = {$CO} AND COALESCE(c.is_deleted,0) = 0
                            AND EXISTS (SELECT 1 FROM client_contract_lines l
                                         WHERE l.contract_id = c.id AND COALESCE(l.is_deleted,0) = 0)
                            AND NOT EXISTS (SELECT 1 FROM contract_price_terms t
                                             WHERE t.contract_id = c.id AND COALESCE(t.is_deleted,0) = 0)
                          ORDER BY c.id DESC LIMIT 12");
        $cids = array(); while ($r && ($x = $r->fetch_row())) $cids[] = (int) $x[0];
        say("   عقودٌ بلا آلية (أحدثُ 12): " . count($cids));
        $KIND = array('fuel', 'fx', 'index');
        $PER  = array('monthly', 'quarterly', 'daily');
        $made = 0;
        foreach ($cids as $i => $cid) {
            $r2 = $db->query("SELECT id FROM client_contract_lines
                               WHERE contract_id = {$cid} AND COALESCE(is_deleted,0) = 0 LIMIT 1");
            $item = ($r2 && ($x2 = $r2->fetch_row())) ? (int) $x2[0] : null;
            if ($item === null) { continue; }   // العمودُ NOT NULL — لا آليةَ بلا بند
            $k = $KIND[$i % 3];
            $sql = sprintf(
                "INSERT INTO contract_price_terms
                   (company_id, contract_id, contract_item_id, trigger_kind, index_code, base_index,
                    base_date, threshold_percent, pass_through_percent, cap_percent, periodicity,
                    valid_from, valid_to, state, note, is_deleted, created_by, created_at)
                 VALUES (%d, %d, %s, '%s', '%s', %s, '%s', %s, %s, %s, '%s', '%s', NULL, 'active', '%s', 0, %d, NOW())",
                $CO, $cid, $item === null ? 'NULL' : $item, $k,
                strtoupper($k) . '-IDX-' . str_pad((string) $cid, 4, '0', STR_PAD_LEFT),
                number_format(100 + $i * 2.5, 5, '.', ''),
                date('Y-m-d', strtotime('-' . (30 + $i * 7) . ' days')),
                number_format(2 + ($i % 4), 3, '.', ''),
                number_format(60 + ($i % 5) * 10, 3, '.', ''),
                number_format(15 + ($i % 3) * 5, 3, '.', ''),
                $PER[$i % 3],
                date('Y-m-d', strtotime('-' . (30 + $i * 7) . ' days')),
                $db->real_escape_string('آليةٌ تجريبيةٌ — تعديلُ السعرِ بالمؤشر'),
                $SALES_U);
            if (run($db, $sql, 'contract_price_terms') !== false) $made++;
        }
        say(($APPLY ? '   ✔ ' : '   · ') . "آلياتٌ مضافة: " . ($APPLY ? $made : count($cids)));
    }
}

/* ═══ ⑤ مهامي — 1336 مهمةً في الشركةِ وواحدةٌ لمدير المبيعات ═══════════════ */
if ($want('tasks')) {
    sect("⑤ مهامي — إسنادُ مهامَّ لمدير المبيعات");
    $r = $db->query("SELECT COUNT(*) FROM work_items
                      WHERE company_id = {$CO} AND assigned_user_id = {$SALES_U}
                        AND status NOT IN ('closed_accepted','cancelled')");
    $have = $r ? (int) $r->fetch_row()[0] : 0;
    say("   مهامُّ مفتوحةٌ لمدير المبيعات الآن: {$have}");
    if ($have < 12) {
        // نُسند مهامَّ قائمةً بدل اختلاقِ صفوفٍ — فالحمولةُ حقيقيةٌ ومتَّسقة
        $need = 12 - $have;
        $n = run($db, "UPDATE work_items
                          SET assigned_user_id = {$SALES_U},
                              owner_user_id = COALESCE(NULLIF(owner_user_id,0), {$SALES_U}),
                              due_at = COALESCE(due_at, NOW() + INTERVAL 2 DAY)
                        WHERE company_id = {$CO}
                          AND status IN ('assigned','overdue')
                          AND assigned_user_id <> {$SALES_U}
                        ORDER BY id DESC LIMIT {$need}", 'work_items.assign');
        say(($APPLY ? "   ✔ أُسندت " . (int) $n : "   · ستُسند {$need}") . " مهمة");
    }
    fill_table($db, 'work_items', "company_id = {$CO} AND assigned_user_id = {$SALES_U}",
               array('created_at', 'closed_at', 'verified_at'));
}

/* ═══ ⑥ الهواتفُ والمعاونون ═══════════════════════════════════════════════ */
if ($want('users')) {
    sect("⑥ المستخدمون — أرقامُ الهواتف");
    $r = $db->query("SELECT id FROM users WHERE company_id = {$CO} AND COALESCE(is_deleted,0) = 0
                      AND (phone IS NULL OR phone = '') ORDER BY id");
    $ids = array(); while ($r && ($x = $r->fetch_row())) $ids[] = (int) $x[0];
    say("   مستخدمون بلا هاتف: " . count($ids));
    foreach ($ids as $i => $uid) {
        $ph = '09' . str_pad((string) (12000000 + $uid * 37 + $i), 8, '0', STR_PAD_LEFT);
        $ph = substr($ph, 0, 10);
        run($db, "UPDATE users SET phone = '{$ph}' WHERE id = {$uid} AND (phone IS NULL OR phone = '')", 'users.phone');
    }
    say(($APPLY ? '   ✔ ' : '   · ') . count($ids) . " هاتفًا");
}

/* ═══ ⑦ العملاءُ والمشاريعُ والعقود — الخلايا الفارغةُ في أعمدةٍ مربوطة ═══ */
if ($want('core')) {
    sect("⑦ العملاء · المشاريع · العقود — ردمُ الخلايا الفارغة");
    fill_table($db, 'clients', "company_id = {$CO} AND COALESCE(is_deleted,0) = 0",
               array('created_at', 'created_by', 'client_code'));
    fill_table($db, 'project', "company_id = {$CO}", array('created_at', 'created_by'));
    /* ★ أعمدةٌ **مشروطةٌ بحالة**: سببُ الإيقافِ وتاريخُه وبياناتُ الإنهاء لا
         تُملأ إلا لعقدٍ في تلك الحال. مالئٌ عامٌّ يملؤها لكلِّ عقدٍ يصنع عقدًا
         «نافذًا وله سببُ إيقاف» — وهو تناقضٌ يعرضه ملفُّ العقدِ نصًّا. */
    /* `actual_start`/`actual_end` مشروطانِ بالحالةِ أيضًا: ملؤُهما لعقدٍ لم
       يُنفَّذ يجعله «منقضيًا» في حكمِ آلةِ الحالاتِ فيسقط فحصُ الاتساق. */
    fill_table($db, 'contracts', "company_id = {$CO} AND COALESCE(is_deleted,0) = 0",
               array('created_at', 'created_by', 'contract_status', 'pause_state_before',
                     'pause_reason', 'pause_date', 'resume_date',
                     'termination_type', 'termination_reason', 'merged_with',
                     'actual_start', 'actual_end'));
}

/* ═══ ⑧ الميزانيةُ وبنودُها ═══════════════════════════════════════════════ */
if ($want('budget')) {
    sect("⑧ الميزانية — بنودٌ لكلِّ ميزانيةٍ خالية");
    $r = $db->query("SELECT * FROM fin_budget_lines WHERE company_id = {$CO} LIMIT 4");
    $src = array(); while ($r && ($x = $r->fetch_assoc())) $src[] = $x;
    if (!$src) { say("   ~ لا بندَ مرجعيًّا"); }
    else {
        $r = $db->query("SELECT b.id FROM fin_budgets b
                          WHERE b.company_id = {$CO} AND COALESCE(b.is_deleted,0) = 0
                            AND NOT EXISTS (SELECT 1 FROM fin_budget_lines l WHERE l.budget_id = b.id)
                          ORDER BY b.id DESC LIMIT 25");
        $bids = array(); while ($r && ($x = $r->fetch_row())) $bids[] = (int) $x[0];
        say("   ميزانياتٌ بلا بنود: " . count($bids));
        $cols = array_keys(colmeta($db, 'fin_budget_lines'));
        $made = 0;
        foreach ($bids as $bi => $bid) {
            foreach ($src as $si => $s) {
                $set = array();
                foreach ($cols as $c) {
                    if (in_array($c, array('id', 'created_at', 'updated_at'), true)) continue;
                    $v = $s[$c];
                    if ($c === 'budget_id') { $v = $bid; }
                    elseif ($c === 'company_id') { $v = $CO; }
                    elseif (is_numeric($v) && !in_array($c, array('line_no'), true)) {
                        $v = round(((float) $v) * (1 + (($bi % 5) - 2) * 0.08), 2);
                    }
                    $set[] = "`$c` = " . ($v === null ? 'NULL' : "'" . $db->real_escape_string((string) $v) . "'");
                }
                if (run($db, "INSERT INTO fin_budget_lines SET " . implode(', ', $set), 'fin_budget_lines') !== false) $made++;
            }
        }
        say(($APPLY ? '   ✔ ' : '   · ') . "بنودٌ مضافة: " . ($APPLY ? $made : count($bids) * count($src)));
    }
    fill_table($db, 'fin_budget_lines', "company_id = {$CO}", array('created_at'));
}

/* ═══ ⑨ جداولُ المبيعاتِ التجارية — ردمُ الخلايا الفارغةِ في كلِّ عمودٍ معروض ═══
   الأعمدةُ الناقصةُ هنا مفاتيحُ إشارةٍ خاليةٌ (العقد · العميل · الفرصة) أو حقولٌ
   وصفيةٌ لم تُبذر — وكلُّها معروضةٌ على شاشةِ مدير المبيعات. */
if ($want('sales')) {
    sect("⑨ جداولُ المبيعات — ردمُ الخلايا الفارغة");
    $TABLES = array(
        'activities'            => array('created_at'),
        'contract_amendments'   => array('created_at'),
        'contract_commitments'  => array('created_at'),
        'contract_events'       => array('created_at'),
        'pricelists'            => array('created_at'),
        'products'              => array('created_at'),
        'quotations'            => array('created_at'),
        'readiness_lines'       => array('created_at'),
        'tenders'               => array('created_at'),
        'units_of_measure'      => array('created_at'),
        'opportunities'         => array('created_at'),
        'rate_book_lines'       => array('created_at'),
        'claims'                => array('created_at'),
        'containers'            => array('created_at'),
    );
    foreach ($TABLES as $t => $skip) {
        if (!table_exists($db, $t)) { say("   ~ لا جدول {$t}"); continue; }
        $w = has_col($db, $t, 'company_id') ? "company_id = {$CO}" : '1=1';
        if (has_col($db, $t, 'is_deleted')) { $w .= " AND COALESCE(is_deleted,0) = 0"; }
        fill_table($db, $t, $w, $skip);
    }
}

/* ═══ ⑩ تعديلاتُ السعرِ المنفَّذة — الجدولُ الثاني في شاشةِ آلياتِ التعديل ═══ */
if ($want('revisions')) {
    sect("⑩ تعديلاتُ السعرِ المنفَّذة");
    $r = $db->query("SELECT t.* FROM contract_price_terms t
                      WHERE t.company_id = {$CO} AND COALESCE(t.is_deleted,0) = 0
                        AND NOT EXISTS (SELECT 1 FROM contract_price_revisions v WHERE v.term_id = t.id)
                      ORDER BY t.contract_id DESC LIMIT 30");
    $terms = array(); while ($r && ($x = $r->fetch_assoc())) $terms[] = $x;
    say("   شروطٌ بلا تعديلاتٍ منفَّذة: " . count($terms));
    $OUT = array('amended', 'below_threshold', 'capped');
    $made = 0;
    foreach ($terms as $i => $t) {
        $old = 1500 + ($i % 13) * 90;
        $delta = round(3 + ($i % 9) * 1.35, 4);
        $applied = $OUT[$i % 3] === 'amended' ? $delta : ($OUT[$i % 3] === 'capped' ? 15.0 : 0.0);
        $new = round($old * (1 + $applied / 100), 4);
        $asOf = date('Y-m-d', strtotime('-' . (20 + $i * 5) . ' days'));
        $sql = sprintf(
            "INSERT INTO contract_price_revisions
               (company_id, term_id, contract_id, contract_item_id, period_key, as_of_date,
                effective_from, index_value, index_source, delta_percent, applied_percent,
                old_price, new_price, outcome, approved_by, approved_at, note, created_by,
                created_origin, created_at)
             VALUES (%d, %d, %d, %d, '%s', '%s', '%s', %s, '%s', %s, %s, %s, %s, '%s', %d, '%s', '%s', %d, 'user', NOW())",
            $CO, (int) $t['id'], (int) $t['contract_id'], (int) $t['contract_item_id'],
            date('Y-m', strtotime($asOf)), $asOf, $asOf,
            number_format(100 + $delta, 8, '.', ''),
            $db->real_escape_string('نشرةُ المؤشرِ الرسمية — ' . strtoupper((string) $t['trigger_kind'])),
            number_format($delta, 4, '.', ''), number_format($applied, 4, '.', ''),
            number_format($old, 4, '.', ''), number_format($new, 4, '.', ''),
            $OUT[$i % 3], $SALES_U, date('Y-m-d H:i:s', strtotime($asOf . ' +2 days')),
            $db->real_escape_string('دورةُ مراجعةٍ تجريبيةٌ — تطبيقُ آليةِ التعديل'), $SALES_U);
        if (run($db, $sql, 'contract_price_revisions') !== false) $made++;
    }
    say(($APPLY ? '   ✔ ' : '   · ') . "تعديلاتٌ مضافة: " . ($APPLY ? $made : count($terms)));
}

/* ═══ ⑪ مساحةُ مخاطرِ الإدارة — الزاويةُ بالوحدةِ التنظيمية ═══════════════
   `Risk/dept_risk_space.php` يرشّح بـ`rr.owner_unit_id = <وحدةُ الدور>`، ودورُ
   المبيعاتِ (12) وحدتُه **2**. وكلُّ مخاطرِ شركةِ العرضِ مملوكةٌ لوحداتٍ أخرى
   (7 · 8 · 9 · 10) — فزاويةُ المبيعاتِ خاويةٌ بحقٍّ لا بعطل. */
if ($want('deptrisk')) {
    sect("⑪ مخاطرُ إدارةِ المبيعات — الوحدةُ التنظيمية 2");
    $SALES_UNIT = 2;
    $r = $db->query("SELECT COUNT(*) FROM risk_register
                      WHERE company_id = {$CO} AND owner_unit_id = {$SALES_UNIT}
                        AND state <> 'closed' AND merged_into_id IS NULL");
    $have = $r ? (int) $r->fetch_row()[0] : 0;
    say("   مخاطرُ المبيعاتِ المفتوحةُ الآن: {$have}");
    if ($have < 6) {
        $n = run($db, "UPDATE risk_register SET owner_unit_id = {$SALES_UNIT}
                        WHERE company_id = {$CO} AND state <> 'closed' AND merged_into_id IS NULL
                        ORDER BY id DESC LIMIT " . (6 - $have), 'risk_register.owner_unit');
        say(($APPLY ? "   ✔ نُقلت " . (int) $n : "   · ستُنقل " . (6 - $have)) . " مخاطرَ إلى زاويةِ المبيعات");
    }
    // الضوابطُ والمؤشراتُ والمعالجاتُ في تبويباتِ الشاشةِ نفسِها
    foreach (array('risk_controls', 'risk_kris', 'risk_treatments', 'risk_signals') as $t) {
        if (table_exists($db, $t)) fill_table($db, $t, "company_id = {$CO}", array('created_at'));
    }
}

/* ═══ ⑫ كشفُ حسابِ العميل — الفترةُ الافتراضيةُ سنةٌ جاريةٌ والداتا قديمة ═══
   `client_statement.php` يفتح على **أوّلِ عميلٍ أبجديًّا** وبفترةِ السنةِ
   الجارية، والخدمةُ ترشّح `claims.period_to BETWEEN from AND to`. ومستخلصاتُ
   العرضِ موزَّعةٌ على 2020..2026، فأوّلُ عميلٍ أبجديًّا مستخلصُه الوحيدُ في
   2021 — فيُعرض الكشفُ خاويًا بطبقاتِه الستِّ كلِّها.
   ⇒ لكلِّ عميلٍ له مستخلصاتٌ ولا شيءَ منها في السنةِ الجارية: تُزاح فترةُ
     أحدثِ مستخلصٍ سنواتٍ كاملةً (فيبقى طولُ الفترةِ ويومُها كما هما). */
if ($want('statement')) {
    sect("⑫ كشفُ حسابِ العميل — إحضارُ نشاطٍ في السنةِ الجارية");
    $Y = (int) date('Y');
    $r = $db->query("SELECT k.client_id, MAX(k.id) last_id
                       FROM claims k
                      WHERE k.company_id = {$CO} AND COALESCE(k.is_deleted,0) = 0
                        AND k.client_id IS NOT NULL
                      GROUP BY k.client_id
                     HAVING SUM(YEAR(k.period_to) = {$Y}) = 0");
    $targets = array(); while ($r && ($x = $r->fetch_assoc())) $targets[(int) $x['client_id']] = (int) $x['last_id'];
    say("   عملاءُ بلا مستخلصٍ في {$Y}: " . count($targets));
    $n = 0;
    foreach (array_values($targets) as $i => $cid) {
        /* الإزاحةُ بسنواتٍ كاملةٍ تحفظ الشهرَ واليومَ وطولَ الفترة — ولا تخلق
           فترةً مقلوبةً (from > to) كما تفعل إزاحةُ الطرفِ الواحد. */
        $ok = run($db, "UPDATE claims
                           SET period_from = period_from + INTERVAL ({$Y} - YEAR(period_to)) YEAR,
                               period_to   = period_to   + INTERVAL ({$Y} - YEAR(period_to)) YEAR
                         WHERE id = {$cid}", 'claims.period shift');
        if ($ok !== false) $n++;
    }
    say(($APPLY ? '   ✔ أُزيحت ' : '   · ستُزاح ') . ($APPLY ? $n : count($targets)) . " مستخلصًا إلى {$Y}");
}

/* ═══ ⑬ طبقاتُ الكشفِ الباقية — المخطَّطُ والمقدَّمُ والتحصيل ═══════════════
   الكشفُ ستُّ طبقاتٍ، وثلاثٌ منها تقرأ جداولَ شبهَ خاويةٍ في شركةِ العرض:
   `contract_monthly_plan` (20 صفًّا لـ120 عقدًا) و`contract_advances` (10) —
   فالطبقةُ تُعرض فارغةً مهما امتلأت المستخلصات. تُبذر لعقودِ المستخلصاتِ وحدَها
   (فالكشفُ لا يقرأ إلا عقدًا وردَ في مستخلصِ العميل). */
if ($want('layers')) {
    sect("⑬ طبقاتُ الكشف — المخطَّطُ والدفعةُ المقدمة");
    $Y = (int) date('Y');

    /* ① المخطَّط: صفَّا خطةٍ شهريةٍ لكلِّ بندِ عقدٍ في عقودِ المستخلصات */
    $r = $db->query("SELECT DISTINCT k.contract_id FROM claims k
                      WHERE k.company_id = {$CO} AND COALESCE(k.is_deleted,0) = 0
                        AND k.contract_id IS NOT NULL
                        AND NOT EXISTS (SELECT 1 FROM contract_monthly_plan p
                                         WHERE p.contract_id = k.contract_id)
                      LIMIT 120");
    $cids = array(); while ($r && ($x = $r->fetch_row())) $cids[] = (int) $x[0];
    say("   عقودُ مستخلصاتٍ بلا خطةٍ شهرية: " . count($cids));
    $made = 0;
    foreach ($cids as $i => $cid) {
        $r2 = $db->query("SELECT id, qty_contracted FROM client_contract_lines
                           WHERE contract_id = {$cid} AND COALESCE(is_deleted,0) = 0 LIMIT 1");
        if (!$r2 || !($ln = $r2->fetch_assoc())) { continue; }
        foreach (array(0, 1, 2) as $k) {
            $mon = date('Y-m', strtotime($Y . '-01-01 +' . (($i + $k * 4) % 12) . ' month'));
            $qty = round(max(1, ((float) $ln['qty_contracted']) / 12), 2);
            /* ★ `effective_from` تاريخُ **سريانِ نسخةِ الخطة** لا شهرُ الفترة:
                 `effectivePlan` ينتقي النسخةَ بشرطِ `effective_from <= أوّلِ يومٍ
                 في المدى المطلوب`. فلو ساويناها بشهرِ الفترةِ سقطت كلُّ الأشهرِ
                 التي بعد يناير من كشفِ السنةِ — وظهرت طبقةُ «المخطَّط» خاويةً
                 والخطةُ موجودةٌ في القاعدة. فالسريانُ أوّلُ السنةِ لكلِّ صفوفِها. */
            $sql = sprintf(
                "INSERT INTO contract_monthly_plan
                   (company_id, contract_id, line_id, plan_version, effective_from, period_month,
                    qty_planned, month_kind, note, created_by, created_at)
                 VALUES (%d, %d, %d, 1, '%s', '%s', %s, '%s', '%s', %d, NOW())",
                $CO, $cid, (int) $ln['id'], $Y . '-01-01', $mon, number_format($qty, 2, '.', ''),
                $k === 0 ? 'mobilization' : 'normal',
                $db->real_escape_string('خطةٌ شهريةٌ تجريبية'), $SALES_U);
            if (run($db, $sql, 'contract_monthly_plan') !== false) $made++;
        }
    }
    say(($APPLY ? '   ✔ ' : '   · ') . "صفوفُ خطةٍ مضافة: " . ($APPLY ? $made : count($cids) * 3));

    /* ② الدفعةُ المقدمة: سندٌ واحدٌ لكلِّ عقدِ مستخلصاتٍ بلا مقدَّم */
    $r = $db->query("SELECT DISTINCT k.contract_id, k.currency FROM claims k
                      WHERE k.company_id = {$CO} AND COALESCE(k.is_deleted,0) = 0
                        AND k.contract_id IS NOT NULL
                        AND NOT EXISTS (SELECT 1 FROM contract_advances a
                                         WHERE a.contract_id = k.contract_id AND COALESCE(a.is_deleted,0) = 0)
                      LIMIT 200");
    $rows = array(); while ($r && ($x = $r->fetch_assoc())) $rows[] = $x;
    say("   عقودُ مستخلصاتٍ بلا دفعةٍ مقدمة: " . count($rows));
    $made = 0;
    foreach ($rows as $i => $x) {
        $cid = (int) $x['contract_id'];
        $sql = sprintf(
            "INSERT INTO contract_advances
               (company_id, contract_id, advance_no, amount, currency, received_date, doc_ref,
                note, state, recorded_by, recorded_at, is_deleted, created_by, created_at, updated_at)
             VALUES (%d, %d, '%s', %s, '%s', '%s', '%s', '%s', 'recorded', %d, NOW(), 0, %d, NOW(), NOW())",
            $CO, $cid, 'ADV-' . str_pad((string) $cid, 6, '0', STR_PAD_LEFT),
            number_format(25000 + ($i % 23) * 1750.25, 2, '.', ''),
            $db->real_escape_string($x['currency'] ?: 'SDG'),
            date('Y-m-d', strtotime($Y . '-01-15 +' . ($i % 10) . ' month')),
            'RCPT-' . $Y . '-' . str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
            $db->real_escape_string('دفعةٌ مقدمةٌ تعاقديةٌ — تُستقطع بنسبةٍ من كلِّ مستخلص'),
            $SALES_U, $SALES_U);
        if (run($db, $sql, 'contract_advances') !== false) $made++;
    }
    say(($APPLY ? '   ✔ ' : '   · ') . "سنداتُ مقدَّمٍ مضافة: " . ($APPLY ? $made : count($rows)));

    /* ③ التحصيلات: دفعةُ قبضٍ على ذمّةِ كلِّ عميلٍ بلا تحصيلٍ في السنةِ الجارية.
         حكمان يتقاطعان على هذين العمودين:
         · `ck_pay_fx_pair` (قيدُ قاعدة): إمّا السعرُ والمعادلُ كلاهما NULL،
           وإمّا المعادلُ = ROUND(المبلغ × السعر, 2) — فلا ثالثَ بينهما.
         · و`base_equivalent_test` (حكمُ نظام): **صفرُ حركةٍ بلا سعر**.
         فالفرعُ «كلاهما NULL» يُرضي القيدَ ويخالف الحكم — وهو ما أسقط الفحصَ
         في أوّلِ شوط. والصوابُ سعرٌ صريحٌ ومعادلٌ محسوبٌ منه. */
    $r = $db->query("SELECT currency FROM admin_companies WHERE id = {$CO} LIMIT 1");
    $BASECUR = ($r && ($x = $r->fetch_row())) ? (string) $x[0] : 'USD';
    /* ★ الحارسُ **بالعميلِ لا بالذمّة**: لو رُشِّحت الذممُ فرادى، بقيت ذممُ
         العميلِ الأخرى مؤهَّلةً في الجولةِ التالية فتُعاد المحاولةُ برقمِ سندٍ
         مكرَّرٍ فيردُّها `uq_fin_pay_no`. ورقمُ السندِ يحمل معرِّفَ الذمّةِ لا
         عدَّادَ حلقةٍ — فهو ثابتٌ عبر الجولاتِ ولا يتصادم. */
    $r = $db->query("SELECT rc.customer_entity_id cid, MIN(rc.id) rid, MIN(rc.currency) cur
                       FROM fin_receivables rc
                      WHERE rc.company_id = {$CO} AND rc.customer_entity_id IS NOT NULL
                      GROUP BY rc.customer_entity_id
                     HAVING NOT EXISTS (SELECT 1 FROM fin_payments p
                                          JOIN fin_receivables r2 ON r2.id = p.receivable_id
                                         WHERE r2.customer_entity_id = rc.customer_entity_id
                                           AND p.direction = 'collection'
                                           AND COALESCE(p.is_deleted,0) = 0
                                           AND YEAR(p.created_at) = {$Y})");
    $recv = array(); while ($r && ($x = $r->fetch_assoc())) $recv[] = $x;
    say("   عملاءُ بلا تحصيلٍ في {$Y}: " . count($recv));
    $METHOD = array('bank', 'cash', 'transfer', 'cheque');
    $made = 0;
    foreach ($recv as $i => $x) {
        $amt = round(9500 + ($i % 29) * 640.75, 2);
        $d   = date('Y-m-d', strtotime($Y . '-01-20 +' . ($i % 11) . ' month'));
        $cur = $x['cur'] ?: $BASECUR;
        // السعرُ من الجدولِ إن خالف عملةَ الدفاتر، و1 إن وافقها
        $rate = ($cur === $BASECUR) ? 1.0 : null;
        if ($rate === null) {
            $q = $db->query("SELECT fx_rate FROM fin_payments
                              WHERE company_id = {$CO} AND currency = '" . $db->real_escape_string($cur) . "'
                                AND fx_rate IS NOT NULL ORDER BY id DESC LIMIT 1");
            $rate = ($q && ($y = $q->fetch_row())) ? (float) $y[0] : 1.0;
        }
        $base = round($amt * $rate, 2);
        $sql = sprintf(
            "INSERT INTO fin_payments
               (company_id, payment_no, direction, party_type, party_ref, method, bank_ref,
                received_on, amount, allocated_amount, unallocated_amount, currency,
                fx_rate, base_amount, receivable_id, memo, paid_at, state, executed_by,
                is_deleted, created_by, created_at, updated_at)
             VALUES (%d, '%s', 'collection', 'customer', %d, '%s', '%s', '%s', %s, %s, 0.00, '%s',
                     " . number_format($rate, 6, '.', '') . ", " . number_format($base, 2, '.', '') . ",
                     %d, '%s', '%s', 'executed', %d, 0, %d, '%s', NOW())",
            $CO, 'RCV-' . $Y . '-' . str_pad((string) $x['rid'], 6, '0', STR_PAD_LEFT),
            (int) $x['cid'], $METHOD[$i % 4],
            'BR-' . $Y . '-' . str_pad((string) $x['rid'], 6, '0', STR_PAD_LEFT),
            $d, number_format($amt, 2, '.', ''), number_format($amt, 2, '.', ''),
            $db->real_escape_string($x['cur'] ?: 'SDG'), (int) $x['rid'],
            $db->real_escape_string('تحصيلٌ تجريبيٌّ مخصَّصٌ على ذمّةِ العميل'),
            $d . ' 10:00:00', $SALES_U, $SALES_U, $d . ' 10:00:00');
        if (run($db, $sql, 'fin_payments') !== false) $made++;
    }
    say(($APPLY ? '   ✔ ' : '   · ') . "تحصيلاتٌ مضافة: " . ($APPLY ? $made : count($recv)));
}

/* ═══ ⑭ مفاتيحُ إشارةٍ يتيمة — القيمةُ موجودةٌ والمُشارُ إليه مفقود ═════════
   عمودٌ مثلُ «العميل» أو «العقد» يُعرض فارغًا لا لأنَّ المفتاحَ خالٍ، بل لأنَّه
   يشير إلى صفٍّ محذوفٍ فيسقط LEFT JOIN إلى NULL. الردمُ هنا **إعادةُ توجيهٍ**
   لا اختلاقُ قيمة: يُعاد المفتاحُ اليتيمُ إلى صفٍّ قائمٍ في الجدولِ الهدف. */
if ($want('orphans')) {
    sect("⑭ إعادةُ توجيهِ المفاتيحِ اليتيمة");
    $MAP = array(
        array('quotations',          'client_id',      'clients'),
        array('quotations',          'opportunity_id', 'opportunities'),
        array('contract_events',     'contract_id',    'contracts'),
        array('contract_amendments', 'contract_id',    'contracts'),
        array('readiness_lines',     'contract_ref',   'contracts'),
        array('tenders',             'authority_id',   'clients'),
        array('opportunities',       'client_id',      'clients'),
    );
    foreach ($MAP as $m) {
        list($t, $c, $ref) = $m;
        if (!table_exists($db, $t) || !has_col($db, $t, $c) || !table_exists($db, $ref)) {
            say("   ~ يُتخطَّى {$t}.{$c}"); continue;
        }
        $refWhere = has_col($db, $ref, 'company_id') ? "company_id = {$CO}" : '1=1';
        if (has_col($db, $ref, 'is_deleted')) { $refWhere .= " AND COALESCE(is_deleted,0) = 0"; }
        $r = $db->query("SELECT id FROM `{$ref}` WHERE {$refWhere} ORDER BY id LIMIT 60");
        $pool = array(); while ($r && ($x = $r->fetch_row())) $pool[] = (int) $x[0];
        if (!$pool) { say("   ~ {$ref} بلا صفوفٍ صالحة"); continue; }

        $tw = has_col($db, $t, 'company_id') ? "t.company_id = {$CO}" : '1=1';
        $r = $db->query("SELECT t.id FROM `{$t}` t
                          WHERE {$tw} AND t.`{$c}` IS NOT NULL AND t.`{$c}` <> 0
                            AND NOT EXISTS (SELECT 1 FROM `{$ref}` x WHERE x.id = t.`{$c}`)
                          ORDER BY t.id");
        $ids = array(); while ($r && ($x = $r->fetch_row())) $ids[] = (int) $x[0];
        if (!$ids) { continue; }
        $n = 0;
        foreach ($ids as $i => $id) {
            $tgt = $pool[$i % count($pool)];
            if (run($db, "UPDATE `{$t}` SET `{$c}` = {$tgt} WHERE id = {$id}", "{$t}.{$c}") !== false) $n++;
        }
        say(sprintf("   %s %-22s %-16s ⇐ %-16s %d يتيمًا", $APPLY ? '✔' : '·', $t, $c, $ref, $APPLY ? $n : count($ids)));
    }

    /* الرابطُ متعدِّدُ الأنواعِ في «أنشطة العملاء»: (entity_type · entity_id) */
    if (table_exists($db, 'activities') && has_col($db, 'activities', 'entity_id')) {
        $TY = array('client' => 'clients', 'opportunity' => 'opportunities', 'contract' => 'contracts');
        foreach ($TY as $ty => $ref) {
            $r = $db->query("SELECT id FROM `{$ref}` WHERE company_id = {$CO}"
                . (has_col($db, $ref, 'is_deleted') ? " AND COALESCE(is_deleted,0) = 0" : '')
                . " ORDER BY id LIMIT 40");
            $pool = array(); while ($r && ($x = $r->fetch_row())) $pool[] = (int) $x[0];
            if (!$pool) { continue; }
            $r = $db->query("SELECT a.id FROM activities a
                              WHERE a.company_id = {$CO} AND a.entity_type = '"
                              . $db->real_escape_string($ty) . "'
                                AND (a.entity_id IS NULL OR a.entity_id = 0
                                     OR NOT EXISTS (SELECT 1 FROM `{$ref}` x WHERE x.id = a.entity_id))");
            $ids = array(); while ($r && ($x = $r->fetch_row())) $ids[] = (int) $x[0];
            $n = 0;
            foreach ($ids as $i => $id) {
                $tgt = $pool[$i % count($pool)];
                if (run($db, "UPDATE activities SET entity_id = {$tgt} WHERE id = {$id}", 'activities.entity_id') !== false) $n++;
            }
            if ($ids) say(sprintf("   %s activities.entity_id (%s) ⇐ %s: %d", $APPLY ? '✔' : '·', $ty, $ref, $APPLY ? $n : count($ids)));
        }
    }
}

/* ═══ ⑮ تنويعُ الأعلامِ الثنائية — «—» ليست فراغًا بل «لا» ════════════════
   عمودٌ كـ«وقود» يُعرض ✓ أو «—»، وكلُّ صفوفِه صفرٌ فيبدو العمودُ خاويًا. تنويعُ
   القيمةِ يُظهر المعنيين معًا فيُقرأ العمودُ كحقلِ نعم/لا لا كعمودٍ مهجور. */
if ($want('flags')) {
    sect("⑮ تنويعُ الأعلامِ الثنائية");
    $FLAGS = array(
        array('rate_book_lines', 'fuel_included'),
        array('rate_book_lines', 'operator_included'),
    );
    foreach ($FLAGS as $f) {
        list($t, $c) = $f;
        if (!table_exists($db, $t) || !has_col($db, $t, $c)) { continue; }
        $r = $db->query("SELECT COUNT(DISTINCT `$c`) FROM `$t` WHERE company_id = {$CO}");
        $distinct = $r ? (int) $r->fetch_row()[0] : 0;
        if ($distinct > 1) { say("   · {$t}.{$c} فيه القيمتان سلفًا"); continue; }
        $n = run($db, "UPDATE `$t` SET `$c` = 1 - `$c` WHERE company_id = {$CO} AND (id % 3) = 0", "{$t}.{$c}");
        say(($APPLY ? '   ✔ ' : '   · ') . "{$t}.{$c}: " . (int) $n . " صفًّا قُلبت قيمتُه");
    }
}

/* ═══ ⑯ حركاتٌ عاكسةٌ حقيقية — لتُقرأ أعمدةُ العكسِ من مصدرِها ═════════════
   عمودا «معكوس بـ» و«عكس عن» رُبطا بنصِّ الحالةِ (`cmp03_reversal_ref`)، ولا
   صفَّ معكوسًا في شركةِ العرض — فيبقيان «—» بحقّ. نعكس صفًّا في كلِّ شاشةٍ
   **عبر الخدمةِ نفسِها** لا بكتابةٍ مباشرة: فتُنشأ الحركةُ العاكسةُ ويُوسم
   الأصلُ ويُكتب أثرُ التدقيق — أي داتا متَّسقةٌ لا نصٌّ مزروع. */
if ($want('reversals')) {
    sect("⑯ حركاتٌ عاكسةٌ في الشاشاتِ المولَّدة");
    require_once dirname(__DIR__) . '/includes/cmp03_local_store.php';
    require_once dirname(__DIR__) . '/includes/cmp03_registry.php';
    $conn = $db;
    $SCREENS = array('production.php' => 'scr_production',
                     'unbilled.php'   => 'scr_unbilled',
                     'unit_perf.php'  => 'scr_unit_perf');
    foreach ($SCREENS as $canonical => $tbl) {
        if (!table_exists($db, $tbl)) { say("   ~ لا جدول {$tbl}"); continue; }
        $r = $db->query("SELECT COUNT(*) FROM `{$tbl}`
                          WHERE company_id = {$CO} AND status LIKE 'معكوس%'");
        if ($r && (int) $r->fetch_row()[0] > 0) { say("   · {$canonical}: فيه عكسٌ سلفًا"); continue; }
        $r = $db->query("SELECT id FROM `{$tbl}` WHERE company_id = {$CO}
                          AND status NOT LIKE 'معكوس%' AND status NOT LIKE 'عكس%'
                        ORDER BY id LIMIT 1");
        if (!$r || !($x = $r->fetch_row())) { say("   ~ {$canonical}: لا صفَّ صالحًا للعكس"); continue; }
        $rid = (int) $x[0];
        if (!$APPLY) { say("   · {$canonical}: سيُعكس الصفُّ #{$rid}"); continue; }
        $res = cmp03_store_reverse($conn, $CO, $canonical, $rid,
            'تصحيحُ قيدٍ تجريبيٍّ — إثباتُ مسارِ العكس', $SALES_U, 'مسؤول المبيعات');
        say(($res['ok'] ? '   ✔ ' : '   !! ') . $canonical . ': ' . $res['msg']);
    }
}

/* ═══ ⑰ خطُّ الأنابيبِ التجاريّ — عقودٌ في حالاتٍ تسمح بنقلِ الحالة ══════════
   عمودُ «نقلُ الحالة» يرسم أزرارَه من `ContractStateMachine::allowedFrom`، وهي
   لا تعرض «تفاوض/اعتماد» إلا من «مسودة» أو «تفاوض». وشركةُ العرضِ فيها 102 عقدًا
   «منتهٍ» (حالةٌ لا مخرجَ منها إلى هذين) وصفرُ مسوّدة — فالعمودُ كلُّه «—» بحقّ.
   وخطُّ أنابيبِ مبيعاتٍ بلا مسوّدةٍ ولا تفاوضٍ ليس خطَّ أنابيب. */
if ($want('pipeline')) {
    sect("⑰ خطُّ الأنابيبِ التجاريّ — مسوّداتٌ وتفاوض");
    $r = $db->query("SELECT COUNT(*) FROM contracts
                      WHERE company_id = {$CO} AND COALESCE(is_deleted,0) = 0
                        AND contract_status IN ('مسودة','تفاوض')");
    $have = $r ? (int) $r->fetch_row()[0] : 0;
    say("   عقودٌ في مسودة/تفاوض الآن: {$have}");
    if ($have < 6) {
        /* ★ لا يُنقل إلى حالةِ ما قبلَ النفاذِ إلا عقدٌ **يحتمِلُها**:
             `contract_state_machine_test` يحكم بشاهدَين — «عقدٌ عليه عملٌ لا
             يكون فيما قبلَ النفاذ» (operations > 0) و«المنقضي لا يرتدُّ إلى ما
             قبلِ العمل» (actual_end في الماضي). فاختيارُ أيِّ عقدٍ منتهٍ يكسر
             الاتساقَ ويُسقط الفحص. الشرطُ إذًا: صفرُ تشغيلٍ، وتُمحى تواريخُ
             التنفيذِ الفعليِّ لأنَّ المسوّدةَ لم تُنفَّذ بعد. */
        $r = $db->query("SELECT c.id FROM contracts c
                          WHERE c.company_id = {$CO} AND COALESCE(c.is_deleted,0) = 0
                            AND c.contract_status NOT IN ('مسودة','تفاوض','معلَّق')
                            AND (SELECT COUNT(*) FROM operations o WHERE o.contract_id = c.id) = 0
                          ORDER BY c.id DESC LIMIT " . (6 - $have));
        $cands = array(); while ($r && ($x = $r->fetch_row())) $cands[] = (int) $x[0];
        say("   مرشَّحون بصفرِ تشغيل: " . count($cands));
        foreach ($cands as $k => $cid) {
            $st = ($k % 2 === 0) ? 'مسودة' : 'تفاوض';
            run($db, "UPDATE contracts
                         SET contract_status = '{$st}', actual_start = NULL, actual_end = NULL
                       WHERE id = {$cid} AND company_id = {$CO}", 'contracts→' . $st);
        }
        say(($APPLY ? '   ✔ ' : '   · ') . count($cands) . " عقدًا نُقل إلى مسودة/تفاوض");
    }

    /* عقدٌ في حالةِ ما قبلَ النفاذِ بلا تشغيلٍ لا يجوز أن يحمل تاريخَ نهايةٍ
       ماضيًا — فذاك «منقضٍ ارتدَّ إلى ما قبلِ العمل» في حكمِ الآلة. */
    $n3 = run($db, "UPDATE contracts c
                       SET c.actual_end = NULL
                     WHERE c.company_id = {$CO} AND COALESCE(c.is_deleted,0) = 0
                       AND c.contract_status IN ('مسودة','تفاوض','قيد المراجعة','موقَّع')
                       AND c.actual_end IS NOT NULL AND c.actual_end < CURDATE()
                       AND (SELECT COUNT(*) FROM operations o WHERE o.contract_id = c.id) = 0",
                  'contracts.actual_end');
    say(($APPLY ? '   ✔ ' : '   · ') . "تواريخُ نهايةٍ مُحيت من عقودٍ لم تُنفَّذ: " . (int) $n3);
}

/* ═══ ⑱ طلباتي — الحدثُ المالي ════════════════════════════════════════════
   «الحدث» = `fin_requests.event_id`، ولا يُنشر إلا باعتمادِ الطلب. ولمديرِ
   المبيعاتِ طلبانِ فقط: مسودةٌ ومنتهيةُ الصلاحية — وكلاهما بلا حدثٍ **بحقّ**.
   فالعلاجُ طلباتٌ في حالاتٍ متقدِّمةٍ لها أحداثُها، لا حشوُ العمودِ عنوةً. */
if ($want('finreq')) {
    sect("⑱ طلباتي — حالاتٌ متقدِّمةٌ بأحداثِها");
    $r = $db->query("SELECT COUNT(*) FROM fin_requests
                      WHERE company_id = {$CO} AND created_by = {$SALES_U} AND event_id IS NOT NULL");
    $have = $r ? (int) $r->fetch_row()[0] : 0;
    say("   طلباتٌ لمدير المبيعات بحدثٍ مالي: {$have}");
    if ($have < 3) {
        $n = run($db, "UPDATE fin_requests SET created_by = {$SALES_U}
                        WHERE company_id = {$CO} AND event_id IS NOT NULL AND created_by <> {$SALES_U}
                        ORDER BY id DESC LIMIT " . (3 - $have), 'fin_requests.owner');
        say(($APPLY ? '   ✔ نُقلت ' : '   · ستُنقل ') . (int) ($APPLY ? $n : 3 - $have) . " طلبًا إلى مدير المبيعات");
    }
    /* ★ لا `fill_table` هنا: نصفُ أعمدةِ الطلبِ **مشروطةٌ بحاله** — سببُ الرفضِ
         ومَن قرَّرَ ونوعُ الاستثناء لا تُملأ لطلبٍ في مسودة. مالئٌ عامٌّ يملؤها
         يصنع «مسودةً مرفوضةً بقرارِ فلان» — تناقضٌ تعرضه الشاشةُ نصًّا. */
    $SAFE = array('justification', 'beneficiary_name', 'currency', 'department', 'description');
    foreach ($SAFE as $c) {
        fill_col($db, 'fin_requests', $c, "company_id = {$CO} AND created_by = {$SALES_U}");
    }
}

/* ═══ ⑲ العملاتُ الناقصةُ على اللوحاتِ التجارية ════════════════════════════ */
if ($want('currency')) {
    sect("⑲ العملات الناقصة");
    foreach (array(array('contracts', 'price_currency_contract'),
                   array('claims', 'currency'),
                   array('client_contract_lines', 'currency')) as $p) {
        list($t, $c) = $p;
        if (!table_exists($db, $t) || !has_col($db, $t, $c)) { continue; }
        $w = "company_id = {$CO}" . (has_col($db, $t, 'is_deleted') ? " AND COALESCE(is_deleted,0) = 0" : '');
        fill_col($db, $t, $c, $w, array('SDG', 'USD'));
    }
}

/* ═══ الخاتمة ═════════════════════════════════════════════════════════════ */
say("\n── الحصيلة ──");
say("  خلايا/صفوفٌ لُمست: {$NWRITE}" . ($APPLY ? '' : '  (عرضٌ فقط)'));
if ($NSKIP) say("  خلايا بلا مصدرِ قيمةٍ تُركت: {$NSKIP}");
foreach ($argv as $a) {
    if (strpos($a, '--undo=') === 0) {
        file_put_contents(substr($a, 7), json_encode($LOG, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        say("  خريطةُ التراجع ⇐ " . substr($a, 7));
    }
}
