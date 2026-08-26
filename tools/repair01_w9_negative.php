<?php
/**
 * tools/repair01_w9_negative.php — الفحصُ السلبيُّ لبوّابةِ المرحلةِ التاسعة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الأخضرُ لا يُثبت شيئًا وحدَه**: بوّابةٌ تفحص ما اخترتُ فحصَه تُخضِرُّ على
 *   العدم. فهنا يُكسَر كلُّ حاجبٍ على حِدةٍ ويُطلَب من البوّابةِ أن تسقط — ثمَّ
 *   تُرجَع الحالة. والحاجبُ الذي لا يسقط عند كسرِه **أعمى**.
 *
 * ◆ **والرسوُّ على رمزِ الحاجبِ لا على عبارتِه**: نصُّ حالةِ الخطأِ يطابق
 *   العبارةَ العربيّةَ فيُخضِرُّ كذبًا — فالالتقاطُ على `✘ W9-nn`.
 *
 * ◆ **وستُّ حواجبَ مقامُها صفرٌ اليوم** (`W9-12` و`W9-14` و`W9-15` و`W9-17`
 *   و`W9-18` و`W9-19`): تمرُّ بإعلانِ الخلاءِ في `W9-D-09`. وكسرُها **نزعُ
 *   الإعلان** — فبلا ذلك تبقى ستُّ بوّاباتٍ خضراءَ لم تُختبَر مرّةً واحدة.
 *
 * ◆ **وحاجبانِ يُكسَرانِ في الشيفرةِ لا في القاعدة** (`W9-20` و`W9-22`): ما
 *   يمنعه المخطَّطُ لا يُختبَر، وما تمنعه **بنيةُ ملفٍّ** لا يُختبَر إلّا
 *   بتشويهِ تلك البنيةِ ثمَّ إرجاعِها بايتًا ببايت.
 *
 * التشغيل: php tools/repair01_w9_negative.php
 * الخروج : 0 كلُّ الحواجبِ يقظة · 1 حاجبٌ أعمى أو إرجاعٌ فاشل
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) {
    $r = @$conn->query($sql); if (!$r) { return null; }
    $x = $r->fetch_row(); return $x ? $x[0] : null;
};

$PHP  = PHP_BINARY;
$GATE = $ROOT . '/tools/repair01_w9_gate.php';
$PSVC = $ROOT . '/app/Services/Procurement/ProcurementCycleService.php';
$WSVC = $ROOT . '/app/Services/Warehouse/WarehouseCycleService.php';
$APPLY = $ROOT . '/tools/repair01_w9_apply.php';

/** يشغّل البوّابةَ ويعيد [رمزُ الخروج، أيُّ حاجبٍ سقط] */
function run_gate($PHP, $GATE)
{
    $out = array(); $code = 0;
    exec('"' . $PHP . '" "' . $GATE . '" 2>&1', $out, $code);
    $failed = array();
    foreach ($out as $l) {
        if (mb_strpos($l, '✘ W9-') !== false && preg_match('/W9-\d+/', $l, $m)) { $failed[] = $m[0]; }
    }
    return array($code, $failed);
}

list($c0, $f0) = run_gate($PHP, $GATE);
if ($c0 !== 0) {
    echo "✘ البوّابةُ ساقطةٌ قبل الكسر (" . implode(',', $f0) . ") — لا معنى لفحصٍ سلبيٍّ على أساسٍ أحمر.\n";
    exit(1);
}
echo "الأساس: البوّابةُ خضراء ✔\n\n";

/* ── قيمٌ تُلتقَط قبل الكسرِ ليكون الإرجاعُ بالقيمةِ لا بالتخمين ────────── */
$pOrig = (string) file_get_contents($PSVC);
$wOrig = (string) file_get_contents($WSVC);

$scopeReq  = (string) $one("SELECT requirement_id FROM repair01_w9_scope ORDER BY requirement_id LIMIT 1");
$scopeRule = (string) $one("SELECT map_rule FROM repair01_w9_scope WHERE requirement_id = '" . $esc($scopeReq) . "'");
$scopeWhy  = (string) $one("SELECT map_why  FROM repair01_w9_scope WHERE requirement_id = '" . $esc($scopeReq) . "'");
$misRows   = (int) $one("SELECT scope_rows FROM repair01_w9_decisions WHERE decision_id = 'W9-D-04'");
$basisRows = (int) $one("SELECT scope_rows FROM repair01_w9_decisions WHERE decision_id = 'W9-D-03'");
$d09Why    = (string) $one("SELECT rationale FROM repair01_w9_decisions WHERE decision_id = 'W9-D-09'");
$sbSid     = (string) $one("SELECT screen_id FROM repair01_w9_sidebar ORDER BY screen_id LIMIT 1");
$sbS1      = (string) $one("SELECT s1_verdict FROM repair01_w9_sidebar WHERE screen_id = '" . $esc($sbSid) . "'");
$sbPerm    = (int) $one("SELECT s6_perm_rows FROM repair01_w9_sidebar WHERE screen_id = '" . $esc($sbSid) . "'");
$sbOrder   = (int) $one("SELECT s4_order_no FROM repair01_w9_sidebar WHERE screen_id = '" . $esc($sbSid) . "'");
$sbVerd4   = (string) $one("SELECT s4_verdict FROM repair01_w9_sidebar WHERE screen_id = '" . $esc($sbSid) . "'");
$sbLink    = (int) $one("SELECT s7_linked FROM repair01_w9_sidebar WHERE screen_id = '" . $esc($sbSid) . "'");
$growSid   = (string) $one("SELECT screen_id FROM repair01_screen_registry WHERE origin = 'W09' ORDER BY screen_id LIMIT 1");
$growGuard = (string) $one("SELECT guard_kind FROM repair01_screen_registry WHERE screen_id = '" . $esc($growSid) . "'");
$growRoute = (string) $one("SELECT route FROM repair01_screen_registry WHERE screen_id = '" . $esc($growSid) . "'");
$stRow     = $conn->query("SELECT entity, from_state, to_state, forbid_reason FROM repair01_w9_states
                            WHERE allowed = 0 ORDER BY entity, from_state LIMIT 1");
$stRow     = $stRow ? $stRow->fetch_assoc() : null;
$sodKey    = (string) $one("SELECT process_key FROM repair01_w9_sod ORDER BY process_key LIMIT 1");
$sodEnf    = (string) $one("SELECT enforced_by FROM repair01_w9_sod WHERE process_key = '" . $esc($sodKey) . "'");
$thKey     = 'PRC_MATCH_TOLERANCE_PCT';
$thWhy     = (string) $one("SELECT why FROM repair01_w9_thresholds WHERE threshold_key = '$thKey'");
$evCode    = (string) $one("SELECT event_code FROM repair01_events WHERE wave = 'W09' ORDER BY event_code LIMIT 1");
$evCons    = (string) $one("SELECT consumer_list FROM repair01_events WHERE event_code = '" . $esc($evCode) . "' AND wave = 'W09'");
$dfKey     = (string) $one("SELECT defer_key FROM repair01_w9_deferred ORDER BY defer_key LIMIT 1");
$dfStep    = (string) $one("SELECT resume_step FROM repair01_w9_deferred WHERE defer_key = '" . $esc($dfKey) . "'");
$company   = (int) $one("SELECT company_id FROM proc_item WHERE COALESCE(is_deleted,0)=0
                          GROUP BY company_id ORDER BY COUNT(*) DESC LIMIT 1");
$itemX     = (int) $one("SELECT id FROM proc_item WHERE company_id = $company ORDER BY id LIMIT 1");
$whX       = (int) $one("SELECT id FROM proc_warehouse WHERE company_id = $company ORDER BY id LIMIT 1");

if ($scopeReq === '' || $sbSid === '' || $growSid === '' || $stRow === null
    || $sodKey === '' || $evCode === '' || $dfKey === '' || $itemX <= 0 || $whX <= 0) {
    echo "✘ أرضيّةٌ ناقصةٌ للكسر — شغّلْ tools/repair01_w9_apply.php أوّلًا\n";
    exit(1);
}

/* ══════════════════════════════════════════════════════════════════════════
   حالاتُ الكسر — لكلٍّ: الحاجبُ المتوقَّعُ سقوطُه · كسرٌ · إرجاع
   ══════════════════════════════════════════════════════════════════════════ */
$CASES = array(
    array('W9-01', 'نزعُ قاعدةِ الربطِ عن متطلَّبٍ في النطاق',
        "UPDATE repair01_w9_scope SET map_rule='', map_why='' WHERE requirement_id='" . $esc($scopeReq) . "'",
        "UPDATE repair01_w9_scope SET map_rule='" . $esc($scopeRule) . "', map_why='" . $esc($scopeWhy)
        . "' WHERE requirement_id='" . $esc($scopeReq) . "'"),

    array('W9-02', 'إخراجُ سطحٍ من سجلِّ الشاشاتِ فتسقط مِرساتُه',
        "UPDATE repair01_screen_registry SET on_disk=0 WHERE screen_id='" . $esc($growSid) . "'",
        "UPDATE repair01_screen_registry SET on_disk=1 WHERE screen_id='" . $esc($growSid) . "'"),

    array('W9-03', 'تغييرُ عددِ المالكِ المخالفِ في قرارِه',
        "UPDATE repair01_w9_decisions SET scope_rows=" . ($misRows + 1) . " WHERE decision_id='W9-D-04'",
        "UPDATE repair01_w9_decisions SET scope_rows=$misRows WHERE decision_id='W9-D-04'"),

    array('W9-04', 'نزعُ حكمِ خطوةٍ من خطواتِ السايدبارِ السبع',
        "UPDATE repair01_w9_sidebar SET s1_verdict='' WHERE screen_id='" . $esc($sbSid) . "'",
        "UPDATE repair01_w9_sidebar SET s1_verdict='" . $esc($sbS1) . "' WHERE screen_id='" . $esc($sbSid) . "'"),

    array('W9-05', 'تصفيرُ منحِ الصلاحيةِ لسطحٍ حيّ',
        "UPDATE repair01_w9_sidebar SET s6_perm_rows=0 WHERE screen_id='" . $esc($sbSid) . "'",
        "UPDATE repair01_w9_sidebar SET s6_perm_rows=$sbPerm WHERE screen_id='" . $esc($sbSid) . "'"),

    array('W9-07', 'نزعُ مصدرِ الترتيبِ عن سطحٍ في النطاق',
        "UPDATE repair01_w9_sidebar SET s4_verdict='NO_ORDER_SOURCE', s4_order_no=0
           WHERE screen_id='" . $esc($sbSid) . "'",
        "UPDATE repair01_w9_sidebar SET s4_verdict='" . $esc($sbVerd4) . "', s4_order_no=$sbOrder
           WHERE screen_id='" . $esc($sbSid) . "'"),

    array('W9-08', 'فكُّ ربطِ سطحٍ بمُعرِّفِ شاشتِه المعياريّ',
        "UPDATE repair01_w9_sidebar SET s7_linked=0 WHERE screen_id='" . $esc($sbSid) . "'",
        "UPDATE repair01_w9_sidebar SET s7_linked=$sbLink WHERE screen_id='" . $esc($sbSid) . "'"),

    array('W9-09', 'تشويهُ حارسِ سطحِ نموٍّ في السجلِّ فيخالف المقيسَ من القرص',
        "UPDATE repair01_screen_registry SET guard_kind='SHELL' WHERE screen_id='" . $esc($growSid) . "'",
        "UPDATE repair01_screen_registry SET guard_kind='" . $esc($growGuard) . "'
           WHERE screen_id='" . $esc($growSid) . "'"),

    /* ⚠ **الكسرُ من زاويةٍ لا يحرسها المخطَّط**: `chk_w9st_forbid` يمنع تفريغَ
         السبب، فمحاولةُ كسرِه بذلك تُردُّ ولا تختبر شيئًا. والزاويةُ المكشوفةُ
         هي **حذفُ الممنوعِ كلِّه** — فيصير الكيانُ بلا ممنوعٍ صريحٍ وهو ما
         يفحصه `$noForbid`. **وحاجبٌ يُكسَر من زاويةٍ يحرسها المخطَّطُ أعمى
         بالبناء لا يقظًا** (‏درسُ W02). */
    array('W9-10', 'حذفُ الممنوعِ الصريحِ كلِّه عن كيانٍ في آلةِ حالتِه',
        "DELETE FROM repair01_w9_states WHERE entity='" . $esc($stRow['entity']) . "' AND allowed=0",
        'APPLY'),

    array('W9-11', 'إعلانُ رمزِ ردٍّ لا وجودَ له في الشيفرة',
        "UPDATE repair01_w9_sod SET enforced_by='W9_GHOST_CODE_NOT_IN_SOURCE' WHERE process_key='" . $esc($sodKey) . "'",
        "UPDATE repair01_w9_sod SET enforced_by='" . $esc($sodEnf) . "' WHERE process_key='" . $esc($sodKey) . "'"),

    array('W9-12', 'نزعُ إعلانِ الخلاءِ عن عدّاداتِ الحزمة',
        "UPDATE repair01_w9_decisions SET rationale='' WHERE decision_id='W9-D-09'",
        "UPDATE repair01_w9_decisions SET rationale='" . $esc($d09Why) . "' WHERE decision_id='W9-D-09'"),

    array('W9-13', 'تغييرُ عددِ الأوامرِ بلا سندٍ في قرارِه',
        "UPDATE repair01_w9_decisions SET scope_rows=" . ($basisRows + 7) . " WHERE decision_id='W9-D-03'",
        "UPDATE repair01_w9_decisions SET scope_rows=$basisRows WHERE decision_id='W9-D-03'"),

    array('W9-16', 'نزعُ عتبةِ سماحِ المطابقةِ من السجلّ',
        "DELETE FROM repair01_w9_thresholds WHERE threshold_key='$thKey'",
        'APPLY'),

    array('W9-20', 'كتابةُ مقارنةِ عتبةٍ صلبةٍ في خدمةِ النطاق',
        'CODE_HARD', 'CODE_RESTORE'),

    array('W9-21', 'إبهامُ مستهلكي حدثٍ إلى «كلُّ المستهلكين»',
        "UPDATE repair01_events SET consumer_list='كل المستهلكين' WHERE event_code='" . $esc($evCode)
        . "' AND wave='W09'",
        "UPDATE repair01_events SET consumer_list='" . $esc($evCons) . "' WHERE event_code='" . $esc($evCode)
        . "' AND wave='W09'"),

    array('W9-22', 'إدخالُ صفِّ حركةٍ بمفردةٍ مخترَعةٍ في الجدولِ الحيّ',
        "INSERT INTO proc_stock_move (company_id,item_id,warehouse_id,move_type,qty,unit_cost,
                                      ref_type,ref_id,note,moved_at,created_by)
         VALUES ($company,$itemX,$whX,'adjust',0,0,'w9neg',0,'W9NEG مفردة مخترعة',NOW(),0)",
        "DELETE FROM proc_stock_move WHERE ref_type='w9neg' AND note='W9NEG مفردة مخترعة'"),

    array('W9-23', 'إقحامُ صفٍّ بلا ختمِ موجةٍ في سجلِّ الشاشات',
        "INSERT INTO repair01_screen_registry (screen_id, screen_file, route, owner_code, on_disk, origin, src_ref)
         VALUES ('SCR-W9NG','w9neg.php','Neg/w9neg.php','DEP-16',1,'NEG','W9NEG')",
        "DELETE FROM repair01_screen_registry WHERE origin='NEG'"),

    /* ⚠ **القفلُ يعمل في الاتّجاهَين، والكسرُ يتبع الجهةَ القائمة.**
         قبل جوابِ `DEC-OPEN-15` كانت الجهةُ «مفتوحٌ ببنودٍ منتظرة»، فكان
         الكسرُ حذفَ بندٍ أو رفعَ استهلاكِه. وبعد الجوابِ صارت الجهةُ «مُغلَقٌ
         ببنودٍ مستهلَكة» — فذانِك الكسرانِ لم يعودا يُسقطانه (‏صفرٌ غيرُ
         مستهلَكٍ يبقى صفرًا). والزاويتانِ الحيّتانِ الآن:
         ① نكضُ استهلاكِ بندٍ والحاجبُ مُغلَق ⇒ مؤجَّلٌ باقٍ بلا سبب.
         ② إعادةُ فتحِ الحاجبِ والبنودُ مستهلَكة ⇒ إعلانٌ متقادم. */
    array('W9-24', 'نكضُ استهلاكِ بندٍ والحاجبُ مُغلَق',
        "UPDATE repair01_w9_deferred SET consumed=0 WHERE defer_key='" . $esc($dfKey) . "'",
        "UPDATE repair01_w9_deferred SET consumed=1 WHERE defer_key='" . $esc($dfKey) . "'"),

    array('W9-24', 'إعادةُ فتحِ الحاجبِ وبنودُه مستهلَكةٌ فيتقادم الإعلان',
        "UPDATE repair01_decisions SET status='NEEDS_OWNER_DECISION' WHERE decision_id='DEC-OPEN-15'",
        "UPDATE repair01_decisions SET status='APPROVED' WHERE decision_id='DEC-OPEN-15'"),

    array('W9-25', 'نزعُ عتبةٍ تحتاجها الرحلةُ فلا تنعقد',
        "DELETE FROM repair01_w9_thresholds WHERE threshold_key='PRC_DIRECT_PURCHASE_CAP'",
        'APPLY'),

    /* ── حواجبُ سياسةِ التتبّعِ بعد جوابِ DEC-OPEN-15 ─────────────────── */
    array('W9-26', 'إنشاءُ نسخةٍ ثانيةٍ ساريةٍ لنطاقٍ واحدٍ فتتداخل',
        "INSERT INTO proc_track_policy
            (company_id, scope_kind, scope_key, version, effective_from, effective_to,
             lot, serial, mfg_date, expiry, warranty, expiry_enforce, issue_policy, requalify,
             override_authority, why, strict_why, decision_ref)
         SELECT company_id, scope_kind, scope_key, version + 100, effective_from, NULL,
                lot, serial, mfg_date, expiry, warranty, expiry_enforce, issue_policy, requalify,
                override_authority, why, strict_why, 'W9NEG'
           FROM proc_track_policy WHERE decision_ref = 'DEC-OPEN-15' LIMIT 1",
        "DELETE FROM proc_track_policy WHERE decision_ref='W9NEG'"),

    /* ⚠ حقولُ السياسةِ الثلاثةُ يحرسها `CHECK` صفًّا صفًّا، فكسرُها من زاويتِه
         يُردُّ ولا يختبر شيئًا. والزاويةُ الحيّةُ **تطابقُ صفَّين**: دورُ
         المعتمِدِ يجب أن يساويَ سلطةَ السياسةِ — وهو قيدٌ لا يُعبَّر عنه في
         `CHECK` أصلًا. وأمينُ المخزنِ لا يمدّد الصلاحيةَ من عنده. */
    array('W9-27', 'تجاوزُ صلاحيةٍ بدورٍ يخالف سلطةَ السياسة',
        "INSERT INTO proc_expiry_override
            (company_id, item_id, op_kind, op_ref, requested_by, approver_role, reason)
         VALUES (" . $company . ", " . $itemX . ", 'ISSUE', 'W9NEG', 0,
                 'امين المخزن', 'تمديد من عندي')",
        "DELETE FROM proc_expiry_override WHERE op_ref='W9NEG'"),

    /* ⚠ **حاجبُ سلوكٍ يُكسَر بالسلوكِ لا بصفّ**: نُشدِّد خاصيّةً إلى `REQUIRED`
         بسياسةِ صنفٍ، فيصير النقصُ الاختياريُّ منعًا — و`W9-28` يستدعي فعلًا
         ويقيس أنَّ الحكمَ صار `block`. وبلا هذا الكسرِ يبقى الحاجبُ يقرأ
         نيّةً لا سلوكًا. */
    array('W9-28', 'تشديدُ خاصيّةٍ الى الزاميّةٍ فيصير نقصُها منعًا',
        "INSERT INTO proc_track_policy
            (company_id, scope_kind, scope_key, version, effective_from, effective_to,
             lot, serial, mfg_date, expiry, warranty, expiry_enforce, issue_policy, requalify,
             override_authority, why, strict_why, decision_ref)
         VALUES (NULL,'ITEM','" . $itemX . "',900,'2000-01-01',NULL,
                 'REQUIRED','OFF','OFF','OFF','OFF','WARNING','FIFO','DISABLED',
                 '','كسر متعمد','كسر متعمد','W9NEG')",
        'W9NEG_STRICT'),

    /* ⚠ `chk_gap_what` يحرس وصفَ النقصِ فكسرُه عبث. والزاويةُ الحيّةُ **صنفٌ
         بلا حكمِ نطاقٍ محلول** — وهو ما لا يستطيع `CHECK` أن يوجبه لأنّه
         مشتقٌّ من سجلٍّ آخر. */
    array('W9-30', 'نزعُ حكمِ النطاقِ المحلولِ عن صنف',
        "UPDATE proc_item SET policy_scope='' WHERE id=" . $itemX,
        'W9NEG_STRICT'),
);

$done = 0; $blind = 0; $skipped = 0;
foreach ($CASES as $c) {
    list($want, $label, $break, $restore) = $c;

    if ($break === 'CODE_HARD') {
        /* مقارنةُ عتبةٍ صلبةٌ تُحقَن في سطرٍ تنفيذيٍّ لا في تعليق */
        $hacked = str_replace('$cap = self::threshold(', '$cap = 0; if ($cap > 50000) { $cap = 0; } $cap = self::threshold(', $pOrig);
        if ($hacked === $pOrig) { printf("  ↷ %-8s تعذَّر الحقنُ — مُتخطًّى\n", $want); $skipped++; continue; }
        file_put_contents($PSVC, $hacked);
    } elseif (is_string($break)) {
        if ($conn->query($break) === false) {
            printf("  ⛔ %-8s فشلَ الكسرُ: %s\n", $want, $conn->error); $blind++; continue;
        }
    }

    list($cx, $fx) = run_gate($PHP, $GATE);
    $fell = in_array($want, $fx, true);
    printf("  %s %-8s %-52s %s\n", $fell ? '✔' : '⛔', $want, $label,
           $fell ? 'سقطت كما يجب' : '**لم تسقط — الحاجبُ أعمى**');
    if (!$fell) { $blind++; }

    /* ── الإرجاع ─────────────────────────────────────────────────────── */
    if ($restore === 'CODE_RESTORE') {
        file_put_contents($PSVC, $pOrig);
        if ((string) file_get_contents($PSVC) !== $pOrig) { printf("  ⛔ %-8s فشلَ إرجاعُ الملفّ\n", $want); $blind++; }
    } elseif ($restore === 'W9NEG_STRICT') {
        /* الإرجاعُ **بحذفِ التشديدِ ثمَّ إعادةِ حلِّ الصنف** — فالعمودُ المحلولُ
           مشتقٌّ، وحذفُ السياسةِ وحدَها يترك الصنفَ على درجةٍ متقادمة. */
        $conn->query("DELETE FROM proc_track_policy WHERE decision_ref='W9NEG'");
        $o = array(); $cc = 0;
        exec('"' . $PHP . '" "' . $ROOT . '/tools/repair01_w9_resume.php" 2>&1', $o, $cc);
        $lvl = (string) $one("SELECT track_lot_level FROM proc_item WHERE id = " . (int) $itemX);
        if ($lvl === 'REQUIRED') { printf("  ⛔ %-8s فشلَ إرجاعُ درجةِ الصنف\n", $want); $blind++; }
    } elseif ($restore === 'APPLY') {
        /* العتبةُ تُعاد ببناءِ أداتِها لا بصفٍّ مكتوبٍ يدًا */
        $o = array(); $cc = 0;
        exec('"' . $PHP . '" "' . $APPLY . '" 2>&1', $o, $cc);
        if ($cc !== 0) { printf("  ⛔ %-8s فشلَ إرجاعُ السجلِّ بالأداة\n", $want); $blind++; }
    } elseif (is_string($restore) && $conn->query($restore) === false) {
        printf("  ⛔ %-8s فشلَ الإرجاع: %s\n", $want, $conn->error); $blind++;
    }
    $done++;
}

/* ── التحقّقُ من الإرجاعِ بإعادةِ تشغيلِ البوّابة ─────────────────────── */
echo "\n";
list($cz, $fz) = run_gate($PHP, $GATE);
if ($cz === 0) { echo "الإرجاع: البوّابةُ عادت خضراء ✔\n"; }
else { echo "⛔ الإرجاع فاشل — البوّابةُ ما زالت ساقطةً في: " . implode(',', $fz) . "\n"; $blind++; }

/* ولا يبقى صفُّ كسرٍ واحد */
$leftover = (int) $one("SELECT (SELECT COUNT(*) FROM repair01_screen_registry WHERE origin='NEG')
                             + (SELECT COUNT(*) FROM proc_stock_move WHERE ref_type='w9neg')");
if ($leftover > 0) { echo "⛔ بقيَ $leftover صفَّ كسرٍ لم يُنزَع\n"; $blind++; }
else { echo "النظافة: لا صفَّ كسرٍ باقٍ ✔\n"; }
if ((string) file_get_contents($PSVC) !== $pOrig || (string) file_get_contents($WSVC) !== $wOrig) {
    echo "⛔ ملفُّ خدمةٍ لم يُرجَع بايتًا ببايت\n"; $blind++;
} else { echo "الشيفرة: الملفّانِ عادا بايتًا ببايت ✔\n"; }

printf("\nالفحصُ السلبيّ: %d حالةَ كسرٍ · مُتخطًّى %d · أعمى %d\n", $done, $skipped, $blind);
echo ($blind === 0 && $skipped === 0 ? "الحكم: كلُّ الحواجبِ يقظة ✔\n" : "الحكم: يوجد حاجبٌ أعمى أو غيرُ مُختبَر ✘\n");
exit(($blind === 0 && $skipped === 0) ? 0 : 1);
