<?php
/**
 * tools/repair01_w6_apply.php
 *   منقّي لغةِ الواجهة — REPAIR01 · W06
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الترتيبُ يحكم** (‏W06 §٤-٤ و§٤-٥): **المولِّدُ قبل المولَّد**.
 *   `nav09_action_map` يُنقَّى ويُرفع ضمُّه بشرطةِ الربطِ في
 *   `WorkItemService::fromNavAction` **قبل** تنقيةِ `work_items.title` —
 *   وإلّا عاد الدَّينُ مع أوّلِ فعلٍ يقع في النظام.
 *
 * ◆ **وكلُّ تغييرٍ يُقيَّد** في `repair01_w6_rewrite` بقديمِه وجديدِه وقاعدتِه
 *   ومرجعِه. فالتراجعُ ليس وعدًا: `--revert` يعيد النصَّ من السجلِّ صفًّا صفًّا.
 *
 * ◆ **ولا يُمَسُّ ما لا يُصيَّر**: ثلاثةُ مصادرَ في دفترِ النطاقِ `is_rendered=0`
 *   تُصنَّف وتُعذَر ولا تُنقّى (‏§٤-٧ · §٤-١٠) — وإعلانُها يحفظ المقامَ كاملًا.
 *
 * ◆ **والذيلُ بين قوسين نصُّ مستخدم** (‏البند ٢٠): وصفُ بلاغٍ ومبرِّرُ رفضٍ
 *   يُعادانِ حرفًا. وما بين «…» هويّةُ فاعلٍ من البيانات — لا يُترجَم ولا يُنقّى.
 *
 * التشغيل:
 *   php tools/repair01_w6_apply.php --dry      قياسٌ وعرضٌ بلا كتابة
 *   php tools/repair01_w6_apply.php            التنقيةُ والتسجيل
 *   php tools/repair01_w6_apply.php --revert   إرجاعُ النصِّ من سجلِّ التغيير
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
require_once $ROOT . '/app/Services/Ui/UiPurity.php';
require_once $ROOT . '/app/Services/Ui/UiLabelRegistry.php';
require_once $ROOT . '/tools/lib/repair01_w6_sources.php';
require_once $ROOT . '/tools/lib/repair01_w6_transform.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

use App\Services\Ui\UiPurity as P;
use App\Services\Ui\UiLabelRegistry as R;

$DRY    = in_array('--dry', $argv, true);
$REVERT = in_array('--revert', $argv, true);
$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) {
    $r = $conn->query($sql);
    if (!$r) { return null; }
    $x = $r->fetch_row();
    return $x ? $x[0] : null;
};

echo "══ REPAIR01 · W06 — منقّي لغةِ الواجهة ══\n";
echo ($DRY ? "الوضع: قياسٌ بلا كتابة\n\n" : ($REVERT ? "الوضع: إرجاع\n\n" : "الوضع: تنقيةٌ وتسجيل\n\n"));

/* ═══════════════════════════════════════════════════════════════════════════
   الإرجاع — يسبق كلَّ شيءٍ ويخرج
   ═══════════════════════════════════════════════════════════════════════════ */
if ($REVERT) {
    $SRC = repair01_w6_sources();
    $back = 0; $miss = 0;
    $r = $conn->query("SELECT id, source_table, source_column, source_key, old_text, new_text
                         FROM repair01_w6_rewrite ORDER BY id DESC");
    $rows = array();
    while ($r && $x = $r->fetch_assoc()) { $rows[] = $x; }
    foreach ($rows as $x) {
        $key = $x['source_table'] . '.' . $x['source_column'];
        if (!isset($SRC[$key])) { $miss++; continue; }
        $where = repair01_w6_key_where($conn, $SRC[$key], $x['source_key']);
        $sql = "UPDATE `" . $x['source_table'] . "` SET `" . $x['source_column'] . "` = '"
             . $esc($x['old_text']) . "' WHERE $where";
        if ($conn->query($sql) === true) { $back++; } else { $miss++; }
    }
    $conn->query("DELETE FROM repair01_w6_rewrite");
    $conn->query("DELETE FROM repair01_w6_scope");
    $conn->query("DELETE FROM repair01_ui_labels WHERE origin = 'W06'");
    $conn->query("DELETE FROM repair01_events WHERE contract_stage = 'W06'");
    printf("أُرجع %d صفًّا · تعذّر %d\n", $back, $miss);
    echo "الحكم: " . ($miss === 0 ? "رجع ✔\n" : "فيه ما لم يرجع ✘\n");
    exit($miss === 0 ? 0 : 1);
}

$W = function ($sql) use ($conn, $DRY) {
    if ($DRY) { return true; }
    if ($conn->query($sql) === true) { return true; }
    echo "  ✘ SQL: " . $conn->error . "\n";
    return false;
};

/* ═══════════════════════════════════════════════════════════════════════════
   ① العتباتُ — من السجلِّ لا من الشيفرة (‏§٥)
   ═══════════════════════════════════════════════════════════════════════════ */
echo "① العتبات ────────────────────────────────────────────────────\n";
$TH = array(
    array('MAX_LEN_BUTTON', 24, 'اسم زر',
        'الزر يقرا في نظرة — وما جاوز ذلك جملة لا اسما', 'W06 §٤-٨ ④'),
    array('MAX_LEN_FIELD', 40, 'اسم حقل',
        'اسم الحقل يعلو خانته فلا يلتف على سطرين', 'W06 §٤-٨ ④'),
    array('MAX_LEN_TAB', 28, 'اسم تبويب',
        'التبويب يقاسم اخوته عرض السطر', 'W06 §٤-٨ ④'),
    array('MAX_LEN_MENU', 48, 'بند قائمة',
        'بند السايدبار يقرا في سطر واحد بعرض القائمة', 'W06 §٤-٨ ④'),
    array('MAX_LEN_SIDEBAR', 48, 'بند سايدبار',
        'مرادف MAX_LEN_MENU بسياق السايدبار', 'W06 §٤-٨ ④'),
    array('MAX_LEN_WORK_ITEM', 80, 'عنوان بند عمل',
        'عنوان بند العمل يقرا في سطر واحد في قائمة المهام — وهو اطول من زر لانه جملة فعل لا اسم عنصر',
        'W06 §٤-٨ ④ · نطاق work_items.title'),
);
foreach ($TH as $t) {
    $W("INSERT INTO repair01_w6_thresholds (threshold_key,value_no,unit,applies_to,why,src_ref)
        VALUES ('" . $esc($t[0]) . "'," . (int) $t[1] . ",'حرف','" . $esc($t[2]) . "','" . $esc($t[3]) . "','" . $esc($t[4]) . "')
        ON DUPLICATE KEY UPDATE value_no=VALUES(value_no), applies_to=VALUES(applies_to), why=VALUES(why), src_ref=VALUES(src_ref)");
}
echo "  ✔ " . count($TH) . " عتباتٍ مسجَّلة\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ② قاموسُ عرضِ الرموزِ الداخلية (‏§٤-٦)
      الثلاثةُ المسمّاةُ في الوثيقةِ **وما قِيس حيًّا** — والقاموسُ يُبذَر أيضًا
      من أسماءِ الأحداثِ العربيّةِ في سجلِّ العقود، فلا يُؤلَّف اسمٌ ثانٍ لحدثٍ
      له اسمٌ معتمد.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n② قاموسُ عرضِ الرموزِ الداخلية ───────────────────────────────\n";
$DICT = array(
    /* الثلاثةُ المسمّاةُ نصًّا في W06 §٤-٦ */
    array('NEEDS_SOURCE', 'يحتاج مستندا', 'يحتاج مستندا', 'READINESS', 'STATE COLUMN',
        'رمز جاهزية داخلي — والمستخدم يحتاج ما ينقصه لا اسم الشرط', 'W06 §٤-٦ · البند ١٦'),
    array('NOT_YET_OCCURRED', 'لم يحدث بعد', 'لم يحدث بعد', 'READINESS', 'STATE COLUMN',
        'رمز جاهزية داخلي', 'W06 §٤-٦ · البند ١٦'),
    array('NOT_APPLICABLE', 'لا ينطبق', 'لا ينطبق', 'READINESS', 'STATE COLUMN',
        'رمز جاهزية داخلي', 'W06 §٤-٦ · البند ١٦'),
    /* مساراتُ البلاغاتِ — مقيسةٌ حيّةً في عناوينِ بنودِ العمل */
    array('movement', 'الحركة', 'الحركة', 'WORKSTREAM', 'WORK_ITEM',
        'رمز مسار بلاغ لاتيني كان يظهر خاما في عنوان بند العمل', 'قياسٌ حيّ: work_items.title · 290 صفًّا'),
    array('maintenance', 'الصيانة', 'الصيانة', 'WORKSTREAM', 'WORK_ITEM',
        'رمز مسار بلاغ لاتيني', 'قياسٌ حيّ: work_items.title · 262 صفًّا'),
    array('operators', 'المشغلين', 'المشغلين', 'WORKSTREAM', 'WORK_ITEM',
        'رمز مسار بلاغ لاتيني', 'قياسٌ حيّ: work_items.title · 262 صفًّا'),
    array('warehouse', 'المستودع', 'المستودع', 'WORKSTREAM', 'WORK_ITEM',
        'رمز مسار بلاغ لاتيني', 'قياسٌ حيّ: work_items.title · 262 صفًّا'),
    array('support', 'الدعم', 'الدعم', 'WORKSTREAM', 'WORK_ITEM',
        'رمز مسار بلاغ لاتيني', 'قياسٌ حيّ: work_items.title · 91 صفًّا'),
    array('hr', 'الموارد البشرية', 'الموارد البشرية', 'WORKSTREAM', 'WORK_ITEM',
        'رمز مسار بلاغ لاتيني', 'قياسٌ حيّ: work_items.title · 78 صفًّا'),
    array('legacy', 'سابق', 'سابق', 'WORKSTREAM', 'WORK_ITEM',
        'رمز مسار بلاغ لاتيني', 'قياسٌ حيّ: work_items.title · 12 صفًّا'),
    /* أسبابُ المنعِ — تظهر في عناوينِ الإجراءِ التصحيحيّ */
    array('WRITE_WITHOUT_PERMISSION_DENY', 'كتابة بلا صلاحية', 'كتابة بلا صلاحية', 'DENY_REASON', 'WORK_ITEM',
        'رمز سبب منع داخلي كان يظهر خاما', 'قياسٌ حيّ: work_items.title · 17 صفًّا'),
    array('UNREGISTERED_SCREEN_WOULD_DENY', 'شاشة غير مسجلة', 'شاشة غير مسجلة', 'DENY_REASON', 'WORK_ITEM',
        'رمز سبب منع داخلي', 'قياسٌ حيّ: work_items.title · 12 صفًّا'),
    array('PERM_DENY_UNREGISTERED', 'صلاحية غير مسجلة', 'صلاحية غير مسجلة', 'DENY_REASON', 'WORK_ITEM',
        'رمز سبب منع داخلي', 'قياسٌ حيّ: work_items.title · 4 صفوفٍ'),
    array('GOVERNANCE_SCREEN_DENY', 'شاشة حوكمة محجوبة', 'شاشة حوكمة', 'DENY_REASON', 'WORK_ITEM',
        'رمز سبب منع داخلي', 'قياسٌ حيّ: work_items.title · صفٌّ واحد'),
    array('tenant_gate_would_deny', 'حاجب الكيان', 'حاجب الكيان', 'DENY_REASON', 'WORK_ITEM',
        'رمز سبب منع داخلي', 'قياسٌ حيّ: work_items.title · 3 صفوفٍ'),
    array('GUEST', 'زائر', 'زائر', 'IDENTITY', 'WORK_ITEM',
        'صيغة العرض المعتمدة لاسم المستخدم الزائر — ولا تحل محله داخل قوسي الهوية: '
        . 'ما بين القوسين قيمة بيانات لا يترجمها المنقي (البند 19)، وهذا الصف يعتمد ما يكتب حيث يؤلف النظام',
        'قياسٌ حيّ: work_items.title · 7 صفوفٍ بين قوسَي الهويّة'),
    /* حالاتٌ داخليّةٌ في سطرِ الدورة */
    array('CLIENT_DISPUTED', 'متنازع عليه من العميل', 'متنازع عليه', 'STATE', 'CYCLE',
        'رمز حالة داخلي كان يظهر في سطر الدورة', 'قياسٌ حيّ: gov_screen_cycle.next_state · صفّان'),
    /* رموزُ أحداثٍ حيّةٍ **ليست في سجلِّ عقودِ الحملة** فلا تُبذَر منه —
       تُعلَن هنا بعددِها المقيسِ في عناوينِ بنودِ العمل. */
    array('finance.hour_recognized', 'ساعة معترف بها ماليا', 'ساعة معترف بها', 'EVENT', 'WORK_ITEM',
        'رمز حدث لاتيني في عنوان بند عمل — وليس له صف في سجل عقود الحملة',
        'قياسٌ حيّ: work_items.title · 4 صفوفٍ'),
    array('settlement.approved', 'تسوية معتمدة', 'تسوية معتمدة', 'EVENT', 'WORK_ITEM',
        'رمز حدث لاتيني في عنوان بند عمل', 'قياسٌ حيّ: work_items.title · صفّان'),
    array('probe.source_logged', 'مصدر مسبار مسجل', 'مصدر مسبار', 'EVENT', 'WORK_ITEM',
        'رمز حدث لاتيني في عنوان بند عمل', 'قياسٌ حيّ: work_items.title · 3 صفوفٍ'),
    array('analytics.probe_derived', 'مؤشر مشتق من مسبار', 'مؤشر مشتق', 'EVENT', 'WORK_ITEM',
        'رمز حدث لاتيني في عنوان بند عمل', 'قياسٌ حيّ: work_items.title · صفّان'),
    array('revenue.unit.recognized', 'ايراد وحدة معترف به', 'ايراد وحدة', 'EVENT', 'WORK_ITEM',
        'رمز حدث لاتيني في عنوان بند عمل', 'قياسٌ حيّ: work_items.title · 11 صفًّا'),
    array('equipment.hour_logged', 'ساعة معدة مسجلة', 'ساعة معدة', 'EVENT', 'WORK_ITEM',
        'رمز حدث لاتيني في عنوان بند عمل', 'قياسٌ حيّ: work_items.title · 4 صفوفٍ'),
);
$dictN = 0;
foreach ($DICT as $d) {
    if ($W("INSERT INTO repair01_w6_code_dict (raw_code,display_ar,display_short,code_family,allowed_context,why,src_ref)
            VALUES ('" . $esc($d[0]) . "','" . $esc($d[1]) . "','" . $esc($d[2]) . "','" . $esc($d[3]) . "','"
            . $esc($d[4]) . "','" . $esc($d[5]) . "','" . $esc($d[6]) . "')
            ON DUPLICATE KEY UPDATE display_ar=VALUES(display_ar), display_short=VALUES(display_short),
              code_family=VALUES(code_family), allowed_context=VALUES(allowed_context),
              why=VALUES(why), src_ref=VALUES(src_ref)")) { $dictN++; }
}
/* أبعادُ التحليلِ التسعةُ — **من مصدرِها الحيِّ لا من تأليف**: `CoaService::DIMS`
   يحمل أسماءَها العربيّةَ المعتمدةَ (‏وثيقةُ المالية §02)، ورمزاها `D2` و`D5`
   مقيسانِ ظاهرَينِ في سطرِ دورةِ شاشتَين. */
require_once $ROOT . '/app/Services/Finance/CoaService.php';
$dimN = 0;
foreach (\App\Services\Finance\CoaService::DIMS as $dim => $nameAr) {
    if ($W("INSERT INTO repair01_w6_code_dict (raw_code,display_ar,display_short,code_family,allowed_context,why,src_ref)
            VALUES ('" . $esc($dim) . "','" . $esc($nameAr) . "','" . $esc($nameAr) . "','DIMENSION','CYCLE COLUMN',
                    'رمز بعد تحليلي داخلي — واسمه العربي معتمد في دليل الحسابات',
                    'App\\\\Services\\\\Finance\\\\CoaService::DIMS › " . $esc($dim) . "')
            ON DUPLICATE KEY UPDATE display_ar=VALUES(display_ar), display_short=VALUES(display_short),
              code_family=VALUES(code_family), why=VALUES(why), src_ref=VALUES(src_ref)")) { $dimN++; }
}

/* رموزُ الأحداثِ من سجلِّ العقود — الاسمُ العربيُّ موجودٌ فلا يُؤلَّف ثانيًا. */
$evN = 0;
$r = $conn->query("SELECT event_code, name FROM repair01_events
                    WHERE event_code REGEXP '^[a-z][a-z0-9_]*\\\\.' AND name <> ''");
while ($r && $x = $r->fetch_assoc()) {
    $disp = P::purifyGenerated($x['name']);
    if ($disp === '' || P::hasTechTerm($disp)) { continue; }
    if ($W("INSERT INTO repair01_w6_code_dict (raw_code,display_ar,display_short,code_family,allowed_context,why,src_ref)
            VALUES ('" . $esc($x['event_code']) . "','" . $esc($disp) . "','" . $esc(mb_substr($disp, 0, 60)) . "',
                    'EVENT','CYCLE WORK_ITEM',
                    'رمز حدث لاتيني — واسمه العربي معتمد في سجل عقود الاثر',
                    'repair01_events.name › " . $esc($x['event_code']) . "')
            ON DUPLICATE KEY UPDATE display_ar=VALUES(display_ar), display_short=VALUES(display_short)")) { $evN++; }
}
printf("  ✔ %d رمزًا معلَنًا · %d بُعدًا من دليلِ الحسابات · %d رمزَ حدثٍ من سجلِّ العقود\n",
    $dictN, $dimN, $evN);

/* ═══════════════════════════════════════════════════════════════════════════
   ③ قياسُ ما قبل — ثمَّ التنقيةُ بالترتيبِ المُعلَن
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n③ التنقيةُ بالترتيب ──────────────────────────────────────────\n";
$SRC = repair01_w6_sources();

/** قياسُ مصدرٍ: [الصفوف, تشكيل, زخرفة, تقني, معادلة] */
function w6_measure(mysqli $conn, $src)
{
    $rows = repair01_w6_read($conn, $src);
    $c = (int) $src['composite'] === 1;
    $m = array(count($rows), 0, 0, 0, 0);
    foreach ($rows as $v) {
        if (P::hasDiacritics($v, $c)) { $m[1]++; }
        if (P::hasDecoration($v, $c)) { $m[2]++; }
        if (P::hasTechTerm($v, $c))   { $m[3]++; }
        if (P::hasEquation($v, $c))   { $m[4]++; }
    }
    return $m;
}

/**
 * قياسُ **ما قبل** — يُعادُ اشتقاقُه لا يُقرأ من عمودٍ مخزَّن.
 * ◆ **ولماذا لا «اقرأْ ما خزّنَته الجولةُ الأولى»** (‏_CONTEXT §قواعد القياس ١):
 *   الأداةُ تُعاد بلا أثرٍ جانبيّ. وجولةٌ ثانيةٌ على قاعدةٍ مُنقّاةٍ تقيس
 *   «قبل = ٠» فتمحو الدليلَ الذي تحكي عنه. **فالأصلُ يُعاد بناؤه**: أوّلُ
 *   `old_text` مقيَّدٍ لكلِّ صفٍّ في سجلِّ التغيير، وما لم يتغيَّر فحالُه اليوم
 *   هو حالُه أمس.
 */
function w6_measure_before(mysqli $conn, $src, $sourceKey)
{
    $rows = repair01_w6_read($conn, $src);
    $orig = array();
    $q = $conn->query("SELECT source_key, old_text FROM repair01_w6_rewrite
                        WHERE source_table = '" . $conn->real_escape_string($src['table']) . "'
                          AND source_column = '" . $conn->real_escape_string($src['column']) . "'
                        ORDER BY id ASC");
    while ($q && $x = $q->fetch_assoc()) {
        if (!isset($orig[$x['source_key']])) { $orig[$x['source_key']] = (string) $x['old_text']; }
    }
    foreach ($rows as $k => $v) { if (isset($orig[$k])) { $rows[$k] = $orig[$k]; } }
    $c = (int) $src['composite'] === 1;
    $m = array(count($rows), 0, 0, 0, 0);
    foreach ($rows as $v) {
        if (P::hasDiacritics($v, $c)) { $m[1]++; }
        if (P::hasDecoration($v, $c)) { $m[2]++; }
        if (P::hasTechTerm($v, $c))   { $m[3]++; }
        if (P::hasEquation($v, $c))   { $m[4]++; }
    }
    return $m;
}

$before = array(); $after = array(); $touched = array();
foreach ($SRC as $key => $src) { $before[$key] = w6_measure_before($conn, $src, $key); }

$totalChanged = 0;
foreach (repair01_w6_rendered_sources() as $key => $src) {
    $rows = repair01_w6_read($conn, $src);
    $changed = 0;
    foreach ($rows as $rowKey => $old) {
        $t = repair01_w6_transform($conn, $key, $src, $rowKey, $old);
        if ($t['new'] === $old) { continue; }
        $where = repair01_w6_key_where($conn, $src, $rowKey);
        $ok = $W("UPDATE `" . $src['table'] . "` SET `" . $src['column'] . "` = '"
               . $esc($t['new']) . "' WHERE $where");
        if (!$ok) { continue; }
        $W("INSERT INTO repair01_w6_rewrite
              (source_table,source_column,source_key,old_text,new_text,defect_kind,rule_id,why,src_ref)
            VALUES ('" . $esc($src['table']) . "','" . $esc($src['column']) . "','" . $esc($rowKey) . "','"
            . $esc(mb_substr($old, 0, 600)) . "','" . $esc(mb_substr($t['new'], 0, 600)) . "','"
            . $esc(implode(',', $t['kinds'])) . "','" . $esc(implode(',', $t['rules'])) . "','"
            . $esc($t['why'] !== '' ? $t['why'] : $src['why']) . "','" . $esc($src['src_ref']) . "')");
        $changed++;
    }
    $touched[$key] = $changed;
    $totalChanged += $changed;
    printf("  %-34s %5d صفًّا مُنقّى من %d\n", $key, $changed, $before[$key][0]);
}
foreach ($SRC as $key => $src) { $after[$key] = w6_measure($conn, $src); }

/* ═══════════════════════════════════════════════════════════════════════════
   ④ دفترُ النطاقِ — قبلَ وبعدَ لكلِّ مصدرٍ **بما فيه ما لا يُنقّى**
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n④ دفترُ النطاق ───────────────────────────────────────────────\n";
foreach ($SRC as $key => $src) {
    $b = $before[$key]; $a = $after[$key];
    $W("INSERT INTO repair01_w6_scope
          (source_key,source_table,source_column,row_filter,is_rendered,renderer,visibility_class,
           rows_total,dia_before,dia_after,decor_before,decor_after,tech_before,tech_after,
           eq_before,eq_after,purify_order,map_rule,map_why,src_ref,measured_at)
        VALUES ('" . $esc($key) . "','" . $esc($src['table']) . "','" . $esc($src['column']) . "','"
        . $esc($src['where']) . "'," . (int) $src['rendered'] . ",'" . $esc($src['renderer']) . "','"
        . $esc($src['visibility']) . "'," . (int) $a[0] . "," . (int) $b[1] . "," . (int) $a[1] . ","
        . (int) $b[2] . "," . (int) $a[2] . "," . (int) $b[3] . "," . (int) $a[3] . ","
        . (int) $b[4] . "," . (int) $a[4] . "," . (int) $src['order'] . ",'"
        . $esc((int) $src['rendered'] === 1 ? 'W6-PURIFY' : 'W6-CLASSIFY-ONLY') . "','"
        . $esc($src['why']) . "','" . $esc($src['src_ref']) . "', NOW())
        ON DUPLICATE KEY UPDATE rows_total=VALUES(rows_total), dia_before=VALUES(dia_before),
          dia_after=VALUES(dia_after), decor_before=VALUES(decor_before), decor_after=VALUES(decor_after),
          tech_before=VALUES(tech_before), tech_after=VALUES(tech_after), eq_before=VALUES(eq_before),
          eq_after=VALUES(eq_after), is_rendered=VALUES(is_rendered), renderer=VALUES(renderer),
          visibility_class=VALUES(visibility_class), map_rule=VALUES(map_rule), map_why=VALUES(map_why),
          src_ref=VALUES(src_ref), measured_at=NOW()");
    printf("  %-34s %s تشكيل %d⇒%d · زخرفة %d⇒%d · تقني %d⇒%d · معادلة %d⇒%d\n",
        $key, ((int) $src['rendered'] === 1 ? '◆' : '·'),
        $b[1], $a[1], $b[2], $a[2], $b[3], $a[3], $b[4], $a[4]);
}

/* ═══════════════════════════════════════════════════════════════════════════
   ⑤ بناءُ سجلِّ المسمّياتِ المركزيّ (‏§٤-٣)
      المفتاحُ التقنيُّ واحدٌ لكلِّ مسمًّى، والمتقاعدُ يُحفَظ لا يُحذف.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n⑤ سجلُّ المسمّيات ────────────────────────────────────────────\n";

/** الصيغةُ المتقاعدةُ لصفّ — من سجلِّ التغييرِ إن غُيِّر. */
$oldOf = array();
$r = $conn->query("SELECT source_table, source_column, source_key, old_text FROM repair01_w6_rewrite");
while ($r && $x = $r->fetch_assoc()) {
    $oldOf[$x['source_table'] . '.' . $x['source_column'] . '|' . $x['source_key']] = (string) $x['old_text'];
}
$reg = function ($key, $label, array $o) use ($conn, $DRY, &$oldOf) {
    if ($DRY) {
        /* **ولا صفرٌ كاذبٌ في وضعِ القياس**: الحكمُ يُحسب ولو لم يُكتب شيء —
           فعدُّ «مرفوضٌ 0» بلا فحصٍ ادّعاءٌ لا قياس. */
        $ctx = trim(explode(' ', (string) ($o['allowed_context'] ?? ''))[0]);
        $v = P::verdict($label, R::maxLen($conn, $ctx));
        if (!$v['clean']) { echo "  ⚠ سيُرفض $key — " . implode(' · ', $v['defects']) . "\n"; }
        return $v['clean'];
    }
    $sk = isset($o['__lookup']) ? $o['__lookup'] : '';
    if ($sk !== '' && isset($oldOf[$sk])) {
        $o['deprecated_label'] = mb_substr($oldOf[$sk], 0, 190);
        $o['replacement_label'] = mb_substr($label, 0, 190);
        $o['label_state'] = 'REPLACED';
    }
    unset($o['__lookup']);
    $res = R::register($conn, $key, $label, $o);
    if (!$res['ok']) { echo "  ⚠ رُفض $key — " . $res['code'] . ' · ' . $res['detail'] . "\n"; }
    return $res['ok'];
};

$labN = 0; $labRej = 0;

/* ⓐ أسماءُ الشاشاتِ — **كلُّ اسمٍ حيٍّ للمسار لا الاسمُ الأعلى أسبقيّةً وحدَه**.
   ◆ الاسمُ يُقرأ من **أربعةِ مصادرَ بأسبقيّة** (‏`nav_canonical.canonical_ar`
     المعتمَدُ ← `nav_canonical.current_label` ← `nav_canonical_current.cur_label`
     لكلِّ دور ← `nav_items.label_ar`)، والمُصيَّرُ قد يكون أيَّها بحسبِ حالةِ
     الصفِّ ودورِ الناظر. فتسجيلُ الأعلى وحدَه يترك أسماءً **تُصيَّر ولا سجلَّ
     لها** — وهو عينُ ما يمنعه §٤-٣ (قِيس: خمسةُ أسماءٍ مُصيَّرةٍ خارجَ السجلّ).
   ◆ **والمسارُ ذو الاسمين لا يُدهَس بواحد**: الاسمُ الأولُ يأخذ `screen:<route>`
     وما بعده `screen:<route>#n` بقاعدةِ `W6-L-VARIANT` — فيبقى الازدواجُ
     **مرئيًّا في السجلِّ** ويُحسم بقرارِ مالكٍ لا بصمتِ أداة. */
$routeNames = array();   /* مسارٌ مُطبَّع ⇒ [الاسم ⇒ [الجدول, العمود, المفتاح]] */
/**
 * ◆ **والمسارُ يُطبَّع قبل أن يصير مفتاحًا**: `nav_canonical.route` يكتب
 *   `Operations/shift_entry.php` و`nav_canonical_current.route` يكتب
 *   `operations/shift_entry.php` — **مسارٌ واحدٌ بحرفَي حالةٍ مختلفَين**.
 *   وترتيبُ الجداولِ في القاعدةِ **غيرُ حسّاسٍ لحالةِ الحرف**، فمفتاحان
 *   يبدوان متمايزَين في PHP يصيران مفتاحًا واحدًا في `PRIMARY KEY` —
 *   **فيدهس أحدُهما الآخرَ صامتًا** ويضيع اسمٌ حيٌّ من السجلّ فيُعَدُّ
 *   «خارجَ السجلّ» وهو مُسجَّلٌ فوقَه غيرُه. (قِيس: عشرون اسمًا مُصيَّرًا.)
 *   والمِرساةُ `#n` **تبقى** — هي سطحٌ متمايزٌ باسمِه لا زخرفةَ مسار.
 */
$normRoute = function ($route) {
    $r = preg_replace('~^(\.\./)+~', '', trim((string) $route));
    return mb_strtolower($r, 'UTF-8');
};
$addName = function ($route, $name, $tb, $col, $key) use (&$routeNames, $normRoute) {
    $route = $normRoute($route); $name = trim((string) $name);
    if ($route === '' || $name === '') { return; }
    if (!isset($routeNames[$route][$name])) { $routeNames[$route][$name] = array($tb, $col, $key); }
};
$r = $conn->query("SELECT id, route, canonical_ar, current_label, status FROM nav_canonical");
while ($r && $x = $r->fetch_assoc()) {
    $addName($x['route'], $x['canonical_ar'], 'nav_canonical', 'canonical_ar', (string) $x['id']);
    if ($x['current_label'] !== null) {
        $addName($x['route'], $x['current_label'], 'nav_canonical', 'current_label', (string) $x['id']);
    }
}
$r = $conn->query("SELECT route, role_id, cur_label FROM nav_canonical_current");
while ($r && $x = $r->fetch_assoc()) {
    $addName($x['route'], $x['cur_label'], 'nav_canonical_current', 'cur_label',
             $x['route'] . '|' . $x['role_id']);
}
$r = $conn->query("SELECT id, route, label_ar FROM nav_items WHERE active = 1 AND route <> ''");
while ($r && $x = $r->fetch_assoc()) {
    $addName($x['route'], $x['label_ar'], 'nav_items', 'label_ar', (string) $x['id']);
}
/* ◆ مفاتيحُ الشاشاتِ تُعاد بناؤها لا تُحدَّث: ترتيبُ الاسمِ الثاني (`#n`)
     يتغيَّر بتغيُّرِ الأسماءِ الحيّة، فالتحديثُ وحدَه يترك مفاتيحَ يتيمةً
     لأسماءٍ لم تعد قائمة. والصيغةُ المتقاعدةُ لا تضيع — تُشتقُّ من سجلِّ
     التغييرِ في كلِّ جولة. */
$W("DELETE FROM repair01_ui_labels WHERE origin = 'W06' AND technical_key LIKE 'screen:%'");
$variantN = 0;
foreach ($routeNames as $route => $names) {
    $i = 0;
    foreach ($names as $name => $meta) {
        $i++;
        $k = 'screen:' . $route . ($i > 1 ? '#' . $i : '');
        if ($i > 1) { $variantN++; }
        $ok = $reg($k, $name, array(
            'short_label' => mb_substr($name, 0, 60),
            /* **السياقُ حيث يظهر النصُّ فعلًا لا حيث قد يظهر**: اسمُ الشاشةِ
               يُصيَّر بندًا في السايدبارِ وعنوانًا في الترويسة — **ولا مستهلكَ
               يطبعه تبويبًا**. ووسمُه `TAB` يُخضعه لحدِّ التبويبِ فيرفع خمسين
               مخالفةً لا وجودَ لها في شاشة، **ويردُّ عشرةَ أسماءٍ حيّةٍ عن
               السجلِّ فتصير «خارجَ السجلّ»** — عيبٌ صنعه التصنيفُ لا النصّ. */
            'allowed_context' => 'SIDEBAR SCREEN_TITLE',
            'visibility_class' => 'USER_VISIBLE',
            'source_table' => $meta[0], 'source_column' => $meta[1], 'source_key' => $meta[2],
            'rule_id' => $i === 1 ? 'W6-L-SCREEN' : 'W6-L-VARIANT',
            'src_ref' => $meta[0] . '.' . $meta[1] . ' › ' . $route
                       . ($i > 1 ? ' (اسمٌ حيٌّ ثانٍ للمسار نفسِه)' : ''),
            '__lookup' => $meta[0] . '.' . $meta[1] . '|' . $meta[2],
        ));
        $ok ? $labN++ : $labRej++;
    }
}

/* ⓑ مجموعاتُ السايدبار */
$r = $conn->query("SELECT DISTINCT name FROM link_groups WHERE name <> ''");
while ($r && $x = $r->fetch_assoc()) {
    $ok = $reg('group:' . $x['name'], (string) $x['name'], array(
        'short_label' => mb_substr((string) $x['name'], 0, 60),
        'allowed_context' => 'SIDEBAR',
        'visibility_class' => 'USER_VISIBLE',
        'source_table' => 'link_groups', 'source_column' => 'name',
        'source_key' => (string) $x['name'],
        'rule_id' => 'W6-L-GROUP',
        'src_ref' => 'link_groups.name (مقامٌ متفرّدٌ بالاسم)',
    ));
    $ok ? $labN++ : $labRej++;
}

/* ⓑ-٢ الاثنتا عشرةَ مجموعةً الحاكمةَ في السايدبار — كانت **نصًّا مكتوبًا في
     ملفّ** (`includes/nav_groups.php`)، وهو عينُ ما يمنعه §٤-٣. تُسجَّل
     بمفاتيحِها الثابتةِ فيصير الملفُّ بذرةً والسجلُّ حاكمًا. */
require_once $ROOT . '/includes/nav_groups.php';
foreach (ems_nav_groups_def_raw() as $code => $g) {
    $clean = P::purifyGenerated((string) $g['name']);
    $ok = $reg('group_key:' . $code, $clean, array(
        'short_label' => mb_substr($clean, 0, 60),
        'allowed_context' => 'SIDEBAR',
        'visibility_class' => 'USER_VISIBLE',
        'source_table' => 'includes/nav_groups.php', 'source_column' => 'ems_nav_groups_def_raw',
        'source_key' => (string) $code,
        'rule_id' => 'W6-L-GROUPKEY',
        'src_ref' => 'includes/nav_groups.php › ems_nav_groups_def_raw › ' . $code,
        'deprecated_label' => ($clean !== (string) $g['name'] ? (string) $g['name'] : ''),
        'replacement_label' => ($clean !== (string) $g['name'] ? $clean : ''),
        'label_state' => ($clean !== (string) $g['name'] ? 'REPLACED' : 'ACTIVE'),
    ));
    $ok ? $labN++ : $labRej++;
}

/* ⓒ أسماءُ الأفعالِ — مصدرُ عنوانِ بندِ العمل */
$r = $conn->query("SELECT canonical_code, label_ar FROM nav09_action_map");
while ($r && $x = $r->fetch_assoc()) {
    $ok = $reg('action:' . $x['canonical_code'], (string) $x['label_ar'], array(
        'short_label' => mb_substr((string) $x['label_ar'], 0, 60),
        /* **سياقُ الفعلِ عنوانُ بندِ عملٍ لا زرّ**: المقيسُ أنَّ `label_ar`
           يُصيَّر في عنوانِ بندِ العملِ وحدَه — ولا مستهلكَ يطبعه زرًّا.
           ووسمُه `BUTTON` يُخضِع جملةَ فعلٍ لحدِّ اسمِ عنصر، فيرفع ١٣٦ مخالفةً
           لا وجودَ لها في الشاشة. */
        'allowed_context' => 'WORK_ITEM',
        'visibility_class' => 'USER_VISIBLE',
        'source_table' => 'nav09_action_map', 'source_column' => 'label_ar',
        'source_key' => (string) $x['canonical_code'],
        'rule_id' => 'W6-L-ACTION',
        'src_ref' => 'nav09_action_map › ' . $x['canonical_code'],
        '__lookup' => 'nav09_action_map.label_ar|' . $x['canonical_code'],
    ));
    $ok ? $labN++ : $labRej++;
}

/* ⓓ عرضُ الرموزِ الداخليّة — المسمّى الذي يراه المستخدمُ بدل الرمز */
$r = $conn->query("SELECT raw_code, display_ar, display_short, code_family, allowed_context FROM repair01_w6_code_dict");
while ($r && $x = $r->fetch_assoc()) {
    $ok = $reg('code:' . $x['raw_code'], (string) $x['display_ar'], array(
        'short_label' => (string) $x['display_short'],
        'allowed_context' => (string) $x['allowed_context'],
        'visibility_class' => 'USER_VISIBLE',
        'source_table' => 'repair01_w6_code_dict', 'source_column' => 'display_ar',
        'source_key' => (string) $x['raw_code'],
        'rule_id' => 'W6-L-CODE',
        'src_ref' => 'repair01_w6_code_dict › ' . $x['code_family'],
        'deprecated_label' => (string) $x['raw_code'],
        'replacement_label' => (string) $x['display_ar'],
    ));
    $ok ? $labN++ : $labRej++;
}

/* ⓔ **تقاعُدُ المسمّى المخالفِ لا حذفُه** (‏الجولةُ الثانية · §٤-٤)
   ═══════════════════════════════════════════════════════════════════════════
   ◆ **العطبُ المقيس**: السجلُّ **تراكميّ** — يُبنى من المصادرِ ولا يُمحى منه
     صفّ. فلمّا نُقّيت المصادرُ في الجولةِ الثانيةِ بقيت **٣٥ صيغةً بنقطتَين**
     كتبتها الجولةُ الأولى (`group:أخرى: للمراجعة` وأخواتُها) **حيّةً في
     السجلِّ المركزيّ** وإن لم يعد يُصيَّرها مصدر. وسجلٌّ حاكمٌ يحمل اسمًا
     تمنعه القاعدةُ ليس سجلًّا حاكمًا.
   ◆ **والعلاجُ تقاعُدٌ لا حذف** — وهو حكمُ الجدولِ نفسِه: «النصُّ القديمُ دليلٌ،
     ومحوُه يمحو القدرةَ على إثباتِ أنَّ الحيَّ لم يعد يحمله». فيُوسَم
     `DEPRECATED` ويُكتب في `deprecated_label` ويشير `replacement_label` إلى
     الصيغةِ المعتمدة — فيصير الصفُّ **دليلَ الاستبدالِ** بدل أن يكون مخالفةً.
   ◆ **وما لا بديلَ له يُنقّى في موضعِه**: صيغةٌ مخالفةٌ بلا خلفٍ مسجَّلٍ تبقى
     مخالفةً لو اكتُفي بوسمِها. */
$retired = 0; $fixed = 0;
$live = array();
$q = $conn->query("SELECT technical_key, arabic_ui_label, label_state FROM repair01_ui_labels
                    WHERE arabic_ui_label <> ''");
$allLabels = array();
while ($q && $x = $q->fetch_assoc()) {
    $allLabels[] = $x;
    if ($x['label_state'] !== 'DEPRECATED') { $live[trim((string) $x['arabic_ui_label'])] = true; }
}
foreach ($allLabels as $x) {
    $old = trim((string) $x['arabic_ui_label']);
    if ($x['label_state'] === 'DEPRECATED') { continue; }
    if (!P::hasNameColon(P::maskProtected($old)) && !P::hasDiacritics($old)
        && !P::hasTechTerm($old) && !P::hasEquation($old)) { continue; }
    $rw  = repair01_w6_name_rewrites();
    $new = isset($rw[$old]) ? $rw[$old] : P::purifyGenerated($old);
    if ($new === '' || $new === $old) { continue; }
    if (isset($live[$new])) {
        /* للصيغةِ خلفٌ حيٌّ مسجَّل ⇒ الصفُّ يصير دليلَ استبدالٍ لا مخالفة */
        $W("UPDATE repair01_ui_labels
               SET label_state = 'DEPRECATED', deprecated_label = '" . $esc($old) . "',
                   replacement_label = '" . $esc($new) . "'
             WHERE technical_key = '" . $esc($x['technical_key']) . "'");
        $retired++;
    } else {
        /* لا خلفَ له ⇒ يُنقّى في موضعِه ويحفظ قديمَه دليلًا */
        $W("UPDATE repair01_ui_labels
               SET arabic_ui_label = '" . $esc($new) . "', deprecated_label = '" . $esc($old) . "',
                   replacement_label = '" . $esc($new) . "'
             WHERE technical_key = '" . $esc($x['technical_key']) . "'");
        $live[$new] = true;
        $fixed++;
    }
}
if ($retired || $fixed) {
    printf("  ✔ %d صيغةً متقاعدةً بخلفِها · %d مُنقّاةً في موضعِها (‏الجولةُ الأولى كتبتها بنقطتَين)\n",
           $retired, $fixed);
}

printf("  ✔ %d مسمًّى مسجَّلًا (منها %d اسمًا حيًّا ثانيًا لمسارِه) · مرفوضٌ %d\n",
    $labN, $variantN, $labRej);

/* ═══════════════════════════════════════════════════════════════════════════
   ⑥ آلةُ حالةِ المسمّى وفصلُ الواجبات (§٧)
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n⑥ آلةُ الحالةِ وفصلُ الواجبات ────────────────────────────────\n";
$ST = array(
    /* entity, from, to, allowed, owner, precondition, doc, gate, reopen, correct */
    array('ui_label', 'NEW', 'DRAFT', 1, 'DEP-08 الحوكمة والالتزام',
        'مفتاح تقني متفرد ومصدر يعود اليه', 'طلب تسمية', 'مراجعة مالك السطح',
        'المسودة تعاد الى NEW بحذف الطلب لا بحذف الصف', 'التعديل على المسودة حر قبل الاعتماد'),
    array('ui_label', 'DRAFT', 'ACTIVE', 1, 'DEP-08 الحوكمة والالتزام',
        'المسمى خال من التشكيل والمصطلح التقني والمعادلة وضمن حد طوله المسجل',
        'قرار تسمية معتمد', 'UiLabelRegistry::register',
        'المتقاعد يعاد تفعيله بصف جديد يشير اليه', 'التصحيح باعتماد صيغة جديدة لا بتحرير القائمة'),
    array('ui_label', 'ACTIVE', 'DEPRECATED', 1, 'DEP-08 الحوكمة والالتزام',
        'بديل معتمد قائم — ولا تقاعد بلا بديل', 'قرار تقاعد', 'UiLabelRegistry::deprecate',
        'يعاد الى ACTIVE بقرار مالك مسجل', 'الصيغة القديمة تبقى دليلا ولا تحذف'),
    array('ui_label', 'DEPRECATED', 'REPLACED', 1, 'DEP-08 الحوكمة والالتزام',
        'البديل صار هو المصير في كل مستهلك', 'محضر تنقية', 'repair01_w6_gate · W6-06',
        'لا اعادة فتح بعد الاستبدال — يفتح صف جديد', 'التصحيح بصف جديد بمفتاحه'),
    array('ui_label', 'ACTIVE', 'REPLACED', 0, '',
        'ممنوع صراحة: الاستبدال بلا مرور بالتقاعد يمحو نافذة المراجعة', '', '', '', ''),
    array('ui_label', 'REPLACED', 'ACTIVE', 0, '',
        'ممنوع صراحة: احياء مسمى مستبدل يعيد المصطلح بصيغتين', '', '', '', ''),
    array('ui_label', 'DRAFT', 'REPLACED', 0, '',
        'ممنوع صراحة: مسودة لم تعتمد لا تستبدل شيئا', '', '', '', ''),
    array('ui_text_source', 'RAW', 'MEASURED', 1, 'DEP-08 الحوكمة والالتزام',
        'المصدر مسجل في دفتر النطاق بمصيره بالاسم', 'دفتر النطاق', 'repair01_w6_apply §٣',
        'يعاد القياس في كل جولة — القياس لا يقفل', 'الفارق يعلن ولا يدهس'),
    array('ui_text_source', 'MEASURED', 'PURIFIED', 1, 'DEP-08 الحوكمة والالتزام',
        'كل تغيير مقيد في سجل التغيير بقاعدته وعذره', 'سجل اعادة الكتابة', 'repair01_w6_gate · W6-03',
        'الارجاع بامر الاداة من السجل نفسه', 'التصحيح باعادة كتابة جديدة تقيد فوق سابقتها'),
    array('ui_text_source', 'PURIFIED', 'LOCKED', 1, 'DEP-08 الحوكمة والالتزام',
        'صنفا الدين UI-01 وUI-02 مقفلا الاتجاه في السقاطة', 'خط اساس الدين',
        'tools/u12_debt_ratchet.php', 'لا اعادة فتح — الزيادة رسوب يوقف البناء',
        'الخفض يثبت خطا جديدا تلقائيا'),
    array('ui_text_source', 'RAW', 'PURIFIED', 0, '',
        'ممنوع صراحة: تنقية بلا قياس سابق تمحو المقام الذي يقاس عليه الاثر', '', '', '', ''),
    array('ui_text_source', 'LOCKED', 'RAW', 0, '',
        'ممنوع صراحة: فك القفل يعيد الدين من الباب الخلفي', '', '', '', ''),
);
$stN = 0;
foreach ($ST as $s) {
    if ($W("INSERT INTO repair01_w6_states
              (entity,from_state,to_state,allowed,owner_role,precondition,official_doc,approval_gate,reopen_rule,correct_rule,src_ref)
            VALUES ('" . $esc($s[0]) . "','" . $esc($s[1]) . "','" . $esc($s[2]) . "'," . (int) $s[3] . ",'"
            . $esc($s[4]) . "','" . $esc($s[5]) . "','" . $esc($s[6]) . "','" . $esc($s[7]) . "','"
            . $esc($s[8]) . "','" . $esc($s[9]) . "','W06 §٧ · آلةُ حالةِ المسمّى')
            ON DUPLICATE KEY UPDATE allowed=VALUES(allowed), owner_role=VALUES(owner_role),
              precondition=VALUES(precondition), official_doc=VALUES(official_doc),
              approval_gate=VALUES(approval_gate), reopen_rule=VALUES(reopen_rule),
              correct_rule=VALUES(correct_rule)")) { $stN++; }
}

$SOD = array(
    array('UI_LABEL_APPROVE', 'اعتماد مسمى واجهة جديد',
        'DEP-OWNER مالك السطح', 'DEP-08 الحوكمة والالتزام', 'EX-DVP نائب الرئيس',
        'UiLabelRegistry::register', 'DEP-08 الحوكمة والالتزام',
        'مالك السطح لا يعتمد مسماه ولا يقفل مراجعته — والمقترح لا يكون هو المراجع',
        'AUTH-UI-01', 'DEP-08 مفوض الحوكمة', 'الكيان القانوني الواحد', 'تفويض مكتوب بمدة', '2026-08-25'),
    array('UI_LABEL_DEPRECATE', 'تقاعد مسمى واستبداله',
        'DEP-08 الحوكمة والالتزام', 'DEP-OWNER مالك السطح', 'EX-DVP نائب الرئيس',
        'repair01_w6_apply', 'DEP-08 الحوكمة والالتزام',
        'من اقترح البديل لا يعتمده — ومن نفذ الاستبدال لا يقفل التحقق منه',
        'AUTH-UI-02', 'DEP-08 مفوض الحوكمة', 'الكيان القانوني الواحد', 'تفويض مكتوب بمدة', '2026-08-25'),
    array('UI_CODE_DICT', 'اعتماد عرض رمز داخلي',
        'DEP-OWNER مالك الرمز', 'DEP-08 الحوكمة والالتزام', 'DEP-08 الحوكمة والالتزام',
        'UiLabelRegistry::display', 'DEP-14 المراجعة الداخلية',
        'مالك الرمز لا يراجع عرضه ولا يقفله — والمقفل جهة ثالثة',
        'AUTH-UI-03', 'DEP-08 مفوض الحوكمة', 'الكيان القانوني الواحد', 'تفويض مكتوب بمدة', '2026-08-25'),
    array('UI_PURITY_RELEASE', 'اطلاق جولة تنقية على مصدر حي',
        'DEP-08 الحوكمة والالتزام', 'DEP-14 المراجعة الداخلية', 'EX-DVP نائب الرئيس',
        'repair01_w6_apply', 'repair01_w6_gate',
        'من نفذ التنقية لا يراجع اثرها ولا يعلن اغلاقها — والبوابة تقفل لا المنفذ',
        'AUTH-UI-04', 'DEP-08 مفوض الحوكمة', 'الكيان القانوني الواحد', 'تفويض مكتوب بمدة', '2026-08-25'),
);
$sodN = 0;
foreach ($SOD as $s) {
    if ($W("INSERT INTO repair01_w6_sod
              (process_key,process_name,initiator_role,reviewer_role,approver_role,executor_role,closer_role,
               forbidden_combo,authority_rule_id,deputy_role,scope_rule,delegation,effective_date,src_ref)
            VALUES ('" . $esc($s[0]) . "','" . $esc($s[1]) . "','" . $esc($s[2]) . "','" . $esc($s[3]) . "','"
            . $esc($s[4]) . "','" . $esc($s[5]) . "','" . $esc($s[6]) . "','" . $esc($s[7]) . "','"
            . $esc($s[8]) . "','" . $esc($s[9]) . "','" . $esc($s[10]) . "','" . $esc($s[11]) . "','"
            . $esc($s[12]) . "','W06 §٧ · مصفوفةُ فصلِ الواجبات')
            ON DUPLICATE KEY UPDATE process_name=VALUES(process_name), initiator_role=VALUES(initiator_role),
              reviewer_role=VALUES(reviewer_role), approver_role=VALUES(approver_role),
              executor_role=VALUES(executor_role), closer_role=VALUES(closer_role),
              forbidden_combo=VALUES(forbidden_combo)")) { $sodN++; }
}
printf("  ✔ %d انتقالًا (منها %d ممنوعٌ صراحةً) · %d عمليةً بفصلِ واجبات\n",
    $stN, count(array_filter($ST, function ($s) { return (int) $s[3] === 0; })), $sodN);

/* ═══════════════════════════════════════════════════════════════════════════
   ⑦ عقودُ الأثرِ لأحداثِ المرحلة (§٧ · §٤٦)
      **وحدثٌ بلا عقدِ أثرٍ مسجَّلٍ لا يُنفَّذ** — فالعقدُ يُكتب قبل أوّلِ إطلاق.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n⑦ عقودُ الأثر ────────────────────────────────────────────────\n";
$EV = array(
    array(
        'ui.label.registered', 'تسجيلُ مسمًّى في السجلِّ المركزيّ',
        '08 الحوكمة والالتزام', 'UiLabelRegistry::register · tools/repair01_w6_apply.php',
        'w6:label:{technical_key} — والمفتاح التقني نفسه مفتاح منع التكرار فلا يسجل مسمى مرتين',
        'governance',
        'مالك سطح او اداة تنقية تسجل مسمى عربيا معتمدا لمفتاح تقني',
        'technical_key · arabic_ui_label · allowed_context · visibility_class · label_state · source_table · source_column · source_key · rule_id',
        "includes/unified_nav.php\nincludes/page_header.php\napp/Services/Work/WorkItemService.php\ntools/repair01_ui_purity.php\ntools/repair01_w6_gate.php",
        "includes/unified_nav.php ⇐ اسم البند والمجموعة يصيران من الصيغة المعتمدة نفسها · دليل: MEASURED (السايدبار المصير)\n"
        . "includes/page_header.php ⇐ اسم الشاشة في الترويسة يطابق السايدبار فلا يظهر المصطلح بصيغتين · دليل: MEASURED (سطر الدورة)\n"
        . "app/Services/Work/WorkItemService.php ⇐ عنوان بند العمل يركب من المسمى المعتمد بلا شرطة ربط · دليل: MEASURED (fromNavAction)\n"
        . "tools/repair01_ui_purity.php ⇐ الفاحص يقارن المصير بالسجل ويرفع اسما خارج السجل · دليل: MEASURED (W6-05)\n"
        . "tools/repair01_w6_gate.php ⇐ البوابة تسقط على اسم خارج السجل او مسمى متقاعد حي · دليل: MEASURED (W6-05 · W6-06)",
        'مفتاح تقني غير فارغ · مسمى خال من التشكيل والمصطلح التقني والمعادلة · طول ضمن حد سياقه المسجل',
        'مرة اخرى بالمفتاح نفسه',
        'فشل التسجيل لا يكسر شاشة: القارئ يعود الى الاحتياطي المنقى ويقيد الرفض — والقيد هو الاثر',
        'لا محو: المسمى المرفوض يبقى في سجل الرفض بسببه ويراجع',
        'W06 §٤-٣ · §٦-أ رحلةُ النصّ',
    ),
    array(
        'ui.label.deprecated', 'تقاعدُ مسمًّى وإعلانُ بديلِه',
        '08 الحوكمة والالتزام', 'UiLabelRegistry::deprecate · tools/repair01_w6_apply.php',
        'w6:deprecate:{technical_key}:{replacement_label} — فلا يتقاعد المسمى مرتين لنفس البديل',
        'governance',
        'مالك السطح يعتمد صيغة جديدة فتوسم القديمة متقاعدة ويكتب بديلها',
        'technical_key · deprecated_label · replacement_label · label_state · decided_by',
        "tools/repair01_w6_gate.php\ntools/repair01_ui_purity.php\ntools/u12_debt_ratchet.php",
        "tools/repair01_w6_gate.php ⇐ W6-06 تسقط ان ظهرت الصيغة المتقاعدة حية في اي مصدر مصير · دليل: MEASURED\n"
        . "tools/repair01_ui_purity.php ⇐ يعد المتقاعد الحي ويسميه بمفتاحه · دليل: MEASURED\n"
        . "tools/u12_debt_ratchet.php ⇐ UI-02 يقفل اتجاه الزخرفة والرمز التقني فلا يعود المتقاعد بصيغته · دليل: MEASURED",
        'بديل معتمد قائم — ولا تقاعد بلا بديل (repair01_w6_states)',
        'مرة اخرى بالمفتاح نفسه',
        'فشل الوسم لا يحذف الصيغة القديمة — وهي الدليل الذي يقاس عليه',
        'لا محو: الصيغة المتقاعدة تبقى عمودا في السجل لا صفا محذوفا',
        'W06 §٤-٣ · §٧ آلةُ حالةِ المسمّى',
    ),
    array(
        'ui.label.rejected', 'ردُّ مسمًّى مخالفٍ وتقييدُ سببِه',
        '08 الحوكمة والالتزام', 'UiLabelRegistry::register · UiLabelRegistry::label · UiLabelRegistry::display',
        'w6:reject:{technical_key}:{reject_code}:{created_at} — والواقعة تقيد بزمنها فلا تبتلع محاولة اخرى',
        'governance',
        'محاولة تسجيل مسمى مشكول او تقني او خارج السجل — او طلب مسمى تقني في سياق مستخدم نهائي',
        'technical_key · attempted · reject_code · reject_detail · caller · actor_id · created_at',
        "repair01_w6_reject_log\ntools/repair01_w6_gate.php\ntools/repair01_w6_journey.php",
        "repair01_w6_reject_log ⇐ الرفض يقيد بسببه وفاعله وزمنه فيصير دليلا يراجع · دليل: MEASURED (صف مقيد)\n"
        . "tools/repair01_w6_gate.php ⇐ W6-07 تشترط ان يكون الرد قد وقع فعلا لا ان يكون معلنا · دليل: MEASURED\n"
        . "tools/repair01_w6_journey.php ⇐ المحطة الاخيرة: مسمى مشكول يرد ويقيد رفضه · دليل: MEASURED",
        'اتصال قاعدة قائم · سجل الرفض موجود',
        'لا اعادة — كل محاولة واقعة مستقلة تقيد بزمنها',
        'تعذر التقييد يعيد false ولا يمرر المسمى المخالف — والرد يقع ولو لم يقيد',
        'لا تعويض: الرفض ليس عملية مالية — والتصحيح باعتماد صيغة نقية',
        'W06 §٦-أ · «تُرفَض ويُقيَّد الرفض»',
    ),
);
/* عقودُ الموجةِ تُعاد كتابتُها لا تُضاعَف — و`repair01_events` بلا مفتاحٍ
   فريدٍ على رمزِ الحدث، فجولةٌ ثانيةٌ تُنشئ عقدًا ثانيًا للحدثِ نفسِه. */
$W("DELETE FROM repair01_events WHERE contract_stage = 'W06'");
$evCon = 0;
foreach ($EV as $e) {
    if ($W("INSERT INTO repair01_events
              (event_code,name,wave,source_unit,source_screen,idempotency_key,consumers,effect_type,
               retry_policy,src_ref,trigger_rule,min_payload,consumer_list,consumer_effect,
               preconditions,failure_policy,compensation,contract_status,contract_rule,contract_stage)
            VALUES ('" . $esc($e[0]) . "','" . $esc($e[1]) . "','W06','" . $esc($e[2]) . "','" . $esc($e[3]) . "','"
            . $esc($e[4]) . "','" . $esc(str_replace("\n", ' · ', $e[8])) . "','" . $esc($e[5]) . "','"
            . $esc($e[10]) . "','" . $esc($e[14]) . "','" . $esc($e[6]) . "','" . $esc($e[7]) . "','"
            . $esc($e[8]) . "','" . $esc($e[9]) . "','" . $esc($e[11]) . "','" . $esc($e[12]) . "','"
            . $esc($e[13]) . "','RECORDED','LIVE_SERVICE_MEASURED','W06')")) { $evCon++; }
}
printf("  ✔ %d عقدَ أثرٍ مسجَّلًا (‏W06)\n", $evCon);

/* ═══════════════════════════════════════════════════════════════════════════
   ⑧ قراراتُ المرحلة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n⑧ قراراتُ المرحلة ───────────────────────────────────────────\n";
/* الطولُ الزائدُ يُقاس بعد بناءِ السجلِّ — والمقياسُ **هو نفسُه** الذي تقرؤه
   البوّابةُ والفاحص (‏`repair01_w6_scan_length`)، فلا رقمانِ في أداتَين. */
require_once $ROOT . '/tools/lib/repair01_w6_scan.php';
$lenOver = $DRY ? 0 : count(repair01_w6_scan_length($ROOT, $conn)['over']);

$sumDiaBefore = 0; $sumDiaAfter = 0; $notRendered = 0;
foreach ($SRC as $key => $src) {
    if ((int) $src['rendered'] === 1) { $sumDiaBefore += $before[$key][1]; $sumDiaAfter += $after[$key][1]; }
    else { $notRendered++; }
}
$DEC = array(
    array('W6-D-01', 'معيارُ نقاءِ لغةِ الواجهةِ قاعدةٌ دستوريّةٌ غيرُ قابلةٍ للاجتهاد', 1,
        'نصُّ قرارِ المالك (2026-08-25) مثبَّتٌ حرفًا في `_CONTEXT.md` §«معيارُ نقاءِ لغةِ الواجهة»، '
        . 'ويسري على السايدبارِ وأسماءِ الشاشاتِ والتبويباتِ ورؤوسِ الأعمدةِ والحقولِ والأزرارِ والحالاتِ '
        . 'والقوائمِ والرسائلِ والتنبيهاتِ واللوحاتِ والبطاقاتِ والفلاترِ والنوافذِ وصفحاتِ التفاصيلِ '
        . 'والإدخالِ والاعتمادِ والتقاريرِ ومساحاتِ الرئيسِ والنوّابِ وعملي. وسبعةُ ممنوعاتٍ وثلاثةٌ لا تُمَسّ.',
        'W06 §٤-٢ · قرارُ المالك 2026-08-25'),
    array('W6-D-02', 'المقامُ يشمل ما لا يُنقّى — والمصنَّفُ يُعذَر لا يُحذَف', $notRendered,
        'ثلاثةُ مصادرَ في دفترِ النطاقِ لا يُصيِّرها مستهلكٌ حيّ: `gov_screen_cycle.screen_title` '
        . '(يحمل اسمَ الملفِّ بين قوسين لكلِّ صفّ) و`gov_screen_cycle.group_name` (مقامُ مقارنةٍ في '
        . 'بوّابتَي W03 وW05) و`nav09_action_map.screen_title` (رُفع ضمُّه بشرطةِ الربطِ في W06 §٤-٤). '
        . 'تُصنَّف `ADMIN_VISIBLE` وتبقى في الدفتر: حذفُها يجعل المقامَ مختارًا، وتنقيتُها تمسُّ مقامَ '
        . 'حاجبٍ مُغلقٍ بلا حاجة.',
        'W06 §٤-٧ · §٤-١٠ · قياسٌ حيّ: مسحُ الشيفرةِ يجد page_header وحدَه على gov_screen_cycle'),
    array('W6-D-03', 'الردُّ في الخدمةِ لا في المخطَّط — فلا يكون الحاجبُ أعمى بالبناء', 0,
        'لم يُوضع `CHECK` يمنع التشكيلَ في `repair01_ui_labels`: ما يمنعه المخطَّطُ لا يُختبَر، '
        . 'وحاجبُ «تشكيلٌ ظاهر» يصير أخضرَ بالبناء. فالردُّ في `UiLabelRegistry::register()` '
        . 'ويُقيَّد في `repair01_w6_reject_log`، ويُكسَر ويُقاس في `repair01_w6_negative.php`.',
        '_CONTEXT §قواعد القياس ٢ · W05 (‏«ولا CHECK يجعل الحاجبَ أعمى»)'),
    array('W6-D-04', 'شرطةُ الربطِ تُرفع من المولِّدِ قبل تنقيةِ المولَّد', 1,
        '`WorkItemService::fromNavAction` كان يركّب `label_ar . " — " . screen_title`، فكلُّ فعلٍ في '
        . 'النظامِ يولّد عنوانًا مخالفًا. رُفع الضمُّ ويُقرأ المسمّى من السجلِّ المركزيِّ، والشاشةُ '
        . 'تُحفَظ في `source_screen` حيث كانت تُحفَظ — فلا تُفقَد معلومةٌ ولا يُولَد دَينٌ جديد.',
        'W06 §٤-٤ · app/Services/Work/WorkItemService.php'),
    array('W6-D-05', 'ذيلُ العنوانِ بين قوسين نصُّ مستخدمٍ لا يُمَسّ — وما بين «…» هويّةُ فاعل', 0,
        'عناوينُ بنودِ العملِ تحمل وصفَ البلاغِ ومبرِّرَ الرفضِ في ذيلٍ بين قوسين، وهويّةَ الفاعلِ '
        . 'بين «…». كلاهما نصُّ مستخدمٍ أو قيمةُ بياناتٍ (البندان ١٩ و٢٠)، فيُحجبان قبل الفحصِ '
        . 'ويُعادان حرفًا بعد التنقية. **والقوسانِ العربيّانِ ترقيمٌ ضروريٌّ لا زخرفة** — هما حدُّ '
        . 'ما لا يُمَسّ، ونزعُهما يمحو الحدَّ نفسَه.',
        'W06 §٤-١٠ · البنود ١٩ · ٢٠ · ٢١'),
    array('W6-D-06', 'إعادةُ الكتابةِ الصريحةُ بمفتاحِ الصفِّ ومرجعِه — لا نمطَ يخمّن المعنى', 0,
        'ما لا يُصلحه نمطٌ (معادلةٌ ظاهرةٌ · اسمُ جدولٍ · رمزُ حدث) أُعيدت كتابتُه بمفتاحِ صفِّه '
        . 'ونصٍّ يحمل **بيانَ الأعمالِ لا قاعدةَ الاشتقاق**، وكلُّ صفٍّ بقاعدتِه وعذرِه في '
        . '`repair01_w6_rewrite`. وبه وحدَه يقع `--revert`.',
        'W06 §٤-٥ · repair01_w6_rewrite'),
    array('W6-D-09', 'ثمانيةُ مساراتٍ اسمُها ينتظر المالكَ — والتنقيةُ غيّرت ما يُصيَّر منها', 8,
        '**الحاجزُ الذي أوقف الالتزام.** المقيسُ: اثنا عشرَ اسمًا مُصيَّرًا في سبعةِ أدوارٍ '
        . 'أُعيدت تسميتُه بالتنقية، **وكلُّها على ثمانيةِ مساراتٍ حالتُها في `nav_canonical` '
        . '`PENDING_OWNER`**: `Approvals/requests.php` · `Portal/visibility_keys.php` · '
        . '`Finance/operator_pay_policies_fin.php` · `Finance/unit_records_fin.php` · '
        . '`Audit/iaf_access_log.php` · `Audit/iaf_escalations.php` · '
        . '`Finance/ctrl_authority_limits.php` · `Finance/ctrl_doc_variance.php`. '
        . '**ولا رابطَ فُقد**: قِيس بالمسارِ لا بالاسمِ فكلُّ واحدٍ منها ما زال يُصيَّر في دورِه '
        . 'باسمِه الجديد. و`tools/uxui_preserve_check.php` يُعذِر إعادةَ التسميةِ **بالاسمِ '
        . 'المعياريِّ المعتمَدِ وحدَه** (‏`status = APPROVED`) — فالمعلَّقُ لا عذرَ له. '
        . 'وهو محقٌّ: تغييرُ اسمِ صفٍّ لم يعتمده مالكُه انتحالٌ لقرارِه، وشاهدُ '
        . '`injfrd66_xc01_names_test` ⑤ مبنيٌّ على هذا نصًّا. '
        . '**والعلاجُ مقيسٌ ومجرَّب**: `canonical_ar` للثمانيةِ **يساوي الاسمَ الجديدَ المُصيَّرَ '
        . 'حرفًا** بالفعل — فاعتمادُها (‏`status = APPROVED`) **بيانٌ لا شيفرة** يُخضِرُّ '
        . 'بوّابةَ الحفظِ وUXUI بالإنفاذِ وUXW وشاهدَي XC-01 وXC-02 معًا (‏مُحوكًى بإرجاعٍ متحقَّق). '
        . '⛔ **ولم تُعتمد من داخلِ هذه المرحلة**: الاعتمادُ قرارُ مالكٍ، ومرحلةٌ تستفيد منه '
        . 'لا تصدره لنفسِها.',
        'W06 §٥ · قياسٌ حيّ: tools/uxui_preserve_check.php --gate · nav_canonical.status'),
    array('W6-D-08', 'سقفُ الطولِ الزائدِ المُعلَن', $lenOver,
        'أسماءٌ حيّةٌ تجاوز طولُها حدَّ سياقِها المسجَّل. **وهي مسجَّلةٌ في السجلِّ لا مردودةٌ عنه**: '
        . 'الطولُ عتبةُ جودةٍ لا قاعدةٌ دستوريّة، وردُّ الاسمِ الطويلِ يُخفيه فيقرأ الفاحصُ '
        . '«اسمٌ خارجَ السجلّ» و«طولٌ زائد ٠» معًا وكلاهما كاذب. والعددُ **سقفٌ مقفلُ الاتّجاه**: '
        . 'البوّابةُ `W6-08` تسقط إن نما. ولا واحدٌ منها مُصيَّرٌ اليوم في سايدبارِ أيِّ دورٍ جذريّ '
        . '(‏المقيسُ على المُصيَّر: صفر) — فهي أسماءٌ ثانيةٌ لمساراتِها تنتظر حسمَ مالكِها.',
        'W06 §٤-٨ ④ · قياسٌ حيٌّ من repair01_ui_labels مقابلَ repair01_w6_thresholds'),
    array('W6-D-07', 'مجموعُ التشكيلِ في المصادرِ المُصيَّرة', $sumDiaBefore,
        'المقيسُ قبل التنقيةِ في المصادرِ المُصيَّرةِ الثلاثةَ عشر · وبعدَها ' . $sumDiaAfter . '. '
        . 'والفارقُ مُقيَّدٌ صفًّا صفًّا في `repair01_w6_rewrite`.',
        'W06 §٣ · قياسٌ حيٌّ من repair01_w6_scope'),
);
$decN = 0;
foreach ($DEC as $d) {
    if ($W("INSERT INTO repair01_w6_decisions (decision_id,title,rationale,scope_rows,decided_by,src_ref)
            VALUES ('" . $esc($d[0]) . "','" . $esc($d[1]) . "','" . $esc($d[3]) . "'," . (int) $d[2] . ",
                    'REPAIR01 · W06','" . $esc($d[4]) . "')
            ON DUPLICATE KEY UPDATE title=VALUES(title), rationale=VALUES(rationale),
              scope_rows=VALUES(scope_rows), src_ref=VALUES(src_ref)")) { $decN++; }
}
printf("  ✔ %d قرارًا مسجَّلًا\n", $decN);

echo "\n" . str_repeat('─', 78) . "\n";
printf("صفوفٌ مُنقّاة %d · مسمّياتٌ %d · عقودٌ %d · تشكيلٌ %d ⇐ %d\n",
    $totalChanged, $labN, $evCon, $sumDiaBefore, $sumDiaAfter);
echo ($DRY ? "الحكم: قياسٌ فقط — لم يُكتب شيء\n" : "الحكم: تمّت ✔\n");
exit(0);
