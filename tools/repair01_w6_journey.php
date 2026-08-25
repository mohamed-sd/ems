<?php
/**
 * tools/repair01_w6_journey.php
 *   رحلةُ النصّ — REPAIR01 · W06 §٦-أ · **شرطُ بوّابةٍ لا توصية**
 * ═══════════════════════════════════════════════════════════════════════════
 * مسمًّى يُسجَّل في `repair01_ui_labels` ← يظهر في السايدبارِ بالصيغةِ المعتمدةِ
 * نفسِها ← وفي مجموعتِه وقسمِه ← وفي سطرِ الدورةِ في الترويسة ← ويُركَّب منه
 * عنوانُ بندِ عملٍ **جديدٍ** عبر `WorkItemService` ← فيصل المستخدمَ بلا تشكيلٍ
 * ولا شرطةِ ربطٍ ولا رمزٍ تقنيّ ← ومحاولةُ إدخالِ مسمًّى مشكولٍ أو خارجَ السجلِّ
 * **تُرفَض ويُقيَّد الرفض**.
 *
 * ◆ **والأثرُ المقيسُ عند كلِّ محطّةٍ النصُّ المُصيَّرُ نفسُه لا صفُّ الجدول**
 *   (‏§٦-أ): السايدبارُ يُصيَّر بجلسةِ مستخدمٍ حقيقيّ، وسطرُ الدورةِ يُبنى
 *   بمنطقِ `page_header` ودالّةِ `ems_next_step` نفسِها، وبندُ العملِ **يُنشأ
 *   فعلًا** ويُقرأ عنوانُه من القاعدة.
 *
 * ◆ **ومُعرِّفُ الجولةِ بالميكروثانية** (‏درسُ W04): مُعرِّفٌ بالثانيةِ يخلط
 *   جولتَين تقعان في الثانيةِ نفسِها فتُقرأ محطّاتُ إحداهما للأخرى.
 *
 * ◆ **والكنسُ بالعائلةِ لا بالجولة** (‏درسُ «وسمِ الاختبار»): ما تخلّفه جولةٌ
 *   سابقةٌ يُكنَس بالبادئةِ `W6J-` قبل البدء — وإلّا عمِيت الجولةُ عن سابقتها.
 *
 * التشغيل: php tools/repair01_w6_journey.php
 * المخرَج : سطرُ `RUN=W6J-…` تقرؤه البوّابة، ثم المحطّاتُ بحكمِها.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
require_once $ROOT . '/tools/lib/repair01_w6_scan.php';
require_once $ROOT . '/app/Services/Work/WorkItemService.php';
require_once $ROOT . '/includes/ux_components.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

use App\Services\Ui\UiPurity as P;
use App\Services\Ui\UiLabelRegistry as R;
use App\Services\Work\WorkItemService as WIS;

$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) { return repair01_w6_one($conn, $sql); };

/* مُعرِّفُ الجولةِ بالميكروثانيةِ من ساعةِ القاعدة. */
$RUN = 'W6J-' . preg_replace('/\D/', '', (string) $one("SELECT NOW(6)"));

/* ── الكنسُ بالعائلة: أثرُ كلِّ جولةٍ سابقةٍ يُنزع قبل البدء ─────────────── */
$conn->query("DELETE FROM work_items WHERE source_ref LIKE 'W6J-%'");
$conn->query("DELETE FROM repair01_w6_reject_log WHERE run_id LIKE 'W6J-%'");
$conn->query("DELETE FROM repair01_ui_labels WHERE technical_key LIKE 'w6j:%'");

$N = 0; $PASS = 0;
$st = function ($station, $consumer, $rendered, $effect, $ok, $detail)
      use ($conn, $RUN, &$N, &$PASS, $esc) {
    $N++;
    if ($ok) { $PASS++; }
    $conn->query("INSERT INTO repair01_w6_journey
        (run_id, station_no, station, consumer, rendered_text, business_effect, passed, detail)
        VALUES ('" . $esc($RUN) . "'," . $N . ",'" . $esc($station) . "','" . $esc($consumer) . "','"
        . $esc(mb_substr((string) $rendered, 0, 600)) . "','" . $esc($effect) . "'," . ($ok ? 1 : 0) . ",'"
        . $esc(mb_substr((string) $detail, 0, 600)) . "')");
    printf("  %s %-2d %-46s %s\n", $ok ? '✔' : '✘', $N, $station, mb_substr((string) $detail, 0, 60));
};

echo "══ رحلةُ النصّ — REPAIR01 · W06 ══\n";
echo "RUN=$RUN\n\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ① اختيارُ الحبّة: مسارٌ **يُصيَّر فعلًا** وله مسمًّى مسجَّلٌ وسطرُ دورةٍ وفعلٌ
      في خريطةِ الأفعال. والاختيارُ يقع على المُصيَّرِ لا على الجدول.
   ═══════════════════════════════════════════════════════════════════════════ */
$ren = repair01_w6_rendered_text($ROOT, $conn);
$cyc = repair01_w6_scan_cycle($conn);

$known = array();
$q = $conn->query("SELECT arabic_ui_label, technical_key FROM repair01_ui_labels WHERE arabic_ui_label <> ''");
while ($q && $x = $q->fetch_assoc()) { $known[trim((string) $x['arabic_ui_label'])] = (string) $x['technical_key']; }

$pickLabel = ''; $pickKey = '';
foreach (array_keys($ren['labels']) as $s) {
    if (isset($known[$s])) { $pickLabel = $s; $pickKey = $known[$s]; break; }
}
$pickGroup = '';
foreach (array_keys($ren['groups']) as $s) { if (isset($known[$s])) { $pickGroup = $s; break; } }

/* فعلٌ حيٌّ من خريطةِ الأفعالِ له مسمًّى مسجَّلٌ ومَلَفٌّ معلوم */
$act = null;
$q = $conn->query("SELECT a.canonical_code, a.label_ar, a.canonical_file
                     FROM nav09_action_map a
                     JOIN repair01_ui_labels l ON l.technical_key = CONCAT('action:', a.canonical_code)
                    WHERE a.canonical_file IS NOT NULL AND a.canonical_file <> ''
                    ORDER BY a.canonical_code LIMIT 1");
if ($q) { $act = $q->fetch_assoc(); }

/* ═══════════════════════════════════════════════════════════════════════════
   ② المحطّات
   ═══════════════════════════════════════════════════════════════════════════ */

/* ① المسمّى مسجَّلٌ بمفتاحٍ تقنيٍّ واحد */
$dupKeys = (int) $one("SELECT COUNT(*) FROM (SELECT arabic_ui_label FROM repair01_ui_labels
                        WHERE technical_key LIKE 'group_key:%' GROUP BY arabic_ui_label HAVING COUNT(*) > 1) z");
$st('المسمّى مسجَّلٌ بمفتاحٍ تقنيٍّ واحد', 'repair01_ui_labels', $pickLabel,
    'الاسمُ يُقرأ من مفتاحٍ واحدٍ فلا يظهر المصطلحُ بثلاثِ صيغٍ في ثلاثِ شاشات',
    $pickLabel !== '' && $pickKey !== '' && $dupKeys === 0,
    "المفتاح $pickKey · مجموعةٌ باسمين $dupKeys");

/* ② الصيغةُ المعتمدةُ نقيّةٌ بالفواحصِ الأربعة */
$v = P::verdict($pickLabel, R::maxLen($conn, 'SIDEBAR'));
$st('الصيغةُ المعتمدةُ نقيّةٌ بالفواحصِ الأربعة', 'UiPurity::verdict', $pickLabel,
    'المسمّى المعتمدُ خالٍ من التشكيلِ والمصطلحِ التقنيِّ والمعادلةِ وضمن حدِّ طوله',
    $v['clean'], $v['clean'] ? 'نقيّ' : implode(' · ', $v['defects']));

/* ③ السايدبارُ يُصيِّره بالصيغةِ نفسِها */
$sameForm = ($pickLabel !== '' && isset($ren['labels'][$pickLabel]));
$st('السايدبارُ يُصيِّره بالصيغةِ المعتمدةِ نفسِها', 'includes/unified_nav.php', $pickLabel,
    'ما يقرؤه المستخدمُ في القائمةِ هو نصُّ السجلِّ حرفًا — لا صيغةٌ ثانية',
    $sameForm, $sameForm ? ('مُصيَّرٌ في ' . $ren['labels'][$pickLabel] . ' موضعًا') : 'غيرُ مُصيَّر');

/* ④ مجموعتُه المُصيَّرةُ مسجَّلةٌ ونقيّة */
$gOk = $pickGroup !== '' && !P::hasDiacritics($pickGroup) && !P::hasTechTerm($pickGroup);
$st('مجموعتُه المُصيَّرةُ مسجَّلةٌ ونقيّة', 'nav_group_taxonomy › unified_nav', $pickGroup,
    'رأسُ المجموعةِ الذي يقع عليه البصرُ أوّلًا نصٌّ نقيٌّ من السجلِّ نفسِه',
    $gOk, $gOk ? ('المفتاح ' . (isset($known[$pickGroup]) ? $known[$pickGroup] : '—')) : 'مجموعةٌ غيرُ مسجَّلةٍ أو غيرُ نقيّة');

/* ⑤ الأقسامُ المقروءةُ كلُّها نقيّة */
$secBad = 0;
foreach (array_keys($ren['sections']) as $s) { if (P::hasDiacritics($s) || P::hasTechTerm($s)) { $secBad++; } }
$st('الأقسامُ المقروءةُ نقيّةٌ كلُّها', 'includes/unified_nav.php · nav-subhead',
    (string) count($ren['sections']) . ' قسمًا',
    'كتلةُ القراءةِ التي تمسح بها العينُ القائمةَ خاليةٌ من التشكيلِ والرمز',
    $secBad === 0, "أقسامٌ " . count($ren['sections']) . " · مخالفٌ $secBad");

/* ⑥ سطرُ الدورةِ يُصيَّر في الترويسةِ نقيًّا */
$cycFile = ''; $cycText = '';
foreach ($cyc['texts'] as $f => $t) { if ($t !== '') { $cycFile = $f; $cycText = $t; break; } }
$cycOk = $cycText !== '' && !P::hasDiacritics($cycText) && !P::hasTechTerm($cycText) && !P::hasEquation($cycText);
$st('سطرُ الدورةِ يُصيَّر في الترويسةِ نقيًّا', 'includes/page_header.php', $cycText,
    'الحالةُ التاليةُ والمستندُ الناتجُ يصلانِ المستخدمَ بلا قاعدةِ اشتقاقٍ ولا اسمِ جدول',
    $cycOk, "الشاشة $cycFile · شاشاتٌ تُصيِّره " . $cyc['screens']);

/* ⑦ لافتةُ «الخطوة التالية» في المُصيَّرِ بلا تشكيل */
$hdr = ems_next_step($cycText);
$hdrTxt = trim(html_entity_decode(strip_tags($hdr), ENT_QUOTES, 'UTF-8'));
$hdrOk = !P::hasDiacritics($hdrTxt) && mb_strpos($hdr, 'الخطوة التالية') !== false;
$st('لافتةُ الترويسةِ نفسُها بلا تشكيل', 'includes/ux_components.php · ems_next_step', $hdrTxt,
    'اللافتةُ الثابتةُ في كلِّ ترويسةِ دورةٍ نصٌّ نقيّ — وهي أكثرُ ما يقع عليه البصرُ يوميًّا',
    $hdrOk, $hdrOk ? 'نقيّة' : 'فيها تشكيلٌ أو تغيَّرت صيغتُها');

/* ⑧ المستندُ الناتجُ يُصيَّر بلا سهم */
$phSrc = (string) @file_get_contents($ROOT . '/includes/page_header.php');
$arrowGone = (mb_strpos($phSrc, 'ems-cycle-doc">ينتج:') !== false)
          && (mb_strpos($phSrc, 'ems-cycle-doc">← ينتج') === false);
$st('المستندُ الناتجُ يُصيَّر بلا سهمِ زخرفة', 'includes/page_header.php · ems-cycle-doc',
    'ينتج: …', 'العلاقةُ تُحمَل باللفظِ لا بالسهم — والسهمُ زينةٌ لا معنى',
    $arrowGone, $arrowGone ? 'السهمُ منزوع' : 'السهمُ ما زال في المُصيَّر');

/* ⑨ مصدرُ عنوانِ بندِ العملِ نقيّ */
$actClean = $act !== null && !P::hasDiacritics($act['label_ar']) && !P::hasTechTerm($act['label_ar']);
$st('مصدرُ عنوانِ بندِ العملِ نقيّ', 'nav09_action_map.label_ar',
    $act ? $act['label_ar'] : '', 'المولِّدُ نقيٌّ قبل المولَّد — وإلّا عاد الدَّينُ مع أوّلِ فعل',
    $actClean, $act ? ('الفعل ' . $act['canonical_code']) : 'لا فعلَ مسجَّل');

/* ⑩ بندُ عملٍ **جديدٌ** يُركَّب من السجلِّ — يُنشأ فعلًا */
$co = 4;
$users = array();
$q = $conn->query("SELECT id FROM users WHERE company_id = $co ORDER BY id LIMIT 2");
while ($q && $x = $q->fetch_row()) { $users[] = (int) $x[0]; }
/* النطاقُ يُقرأ من الحيِّ لا من جدولٍ مُفترَض: `departments` **غيرُ موجودٍ**
   في هذه القاعدة، و«لا نطاقَ» يجعل المحطّةَ تسقط لسببٍ خارجَ ما تقيسه.
   فالمقياسُ إدارةٌ يستعملها بندُ عملٍ قائمٌ فعلًا. */
$org = (int) $one("SELECT org_unit_id FROM work_items
                    WHERE org_unit_id IS NOT NULL AND company_id = $co
                    GROUP BY org_unit_id ORDER BY COUNT(*) DESC LIMIT 1");
$wiId = 0; $wiTitle = ''; $wiReason = '';
if ($act !== null && count($users) >= 2 && $org > 0) {
    $res = WIS::fromNavAction($conn, $act['canonical_code'], array(
        'company_id' => $co,
        'source_type' => 'SRC-03',
        'source_ref' => $RUN . ':journey',
        'owner_user_id' => $users[0],
        'assigned_user_id' => $users[1],
        'verifier_user_id' => $users[0],
        'org_unit_id' => $org,
        'due_at' => date('Y-m-d H:i:s', time() + 86400),
        'deliverable' => 'اثر الفعل في سجل التدقيق',
        'evidence_required' => 'اثر الفعل في سجل التدقيق',
        'created_by' => $users[0],
    ));
    if (!empty($res['ok'])) { $wiId = (int) $res['id']; }
    else { $wiReason = isset($res['reason']) ? (string) $res['reason'] : 'تعذّر الإنشاء'; }
    if ($wiId > 0) { $wiTitle = (string) $one("SELECT title FROM work_items WHERE id = $wiId"); }
}
$st('بندُ عملٍ جديدٌ يُركَّب من السجلِّ المركزيّ', 'WorkItemService::fromNavAction', $wiTitle,
    'كلُّ فعلٍ جديدٍ يولّد عنوانًا من المسمّى المعتمدِ — فالدَّينُ لا ينمو تلقائيًّا',
    $wiId > 0, $wiId > 0 ? "بندٌ #$wiId" : ('لم يُنشأ: ' . $wiReason));

/* ⑪ العنوانُ المولَّدُ بلا شرطةِ ربط */
$noDash = $wiTitle !== '' && mb_strpos($wiTitle, '—') === false;
$st('العنوانُ المولَّدُ بلا شرطةِ ربط', 'work_items.title', $wiTitle,
    'شرطةُ الربطِ التي كانت تضمُّ اسمَ الشاشةِ رُفعت من المولِّد — والشاشةُ في source_screen',
    $noDash, $noDash ? 'بلا شرطة' : 'ما زالت الشرطةُ في التركيب');

/* ⑫ العنوانُ المولَّدُ نقيٌّ بالفواحصِ الأربعة */
$wiV = P::verdict($wiTitle, R::maxLen($conn, 'WORK_ITEM'), true);
$st('العنوانُ المولَّدُ نقيٌّ بالفواحصِ الأربعة', 'UiPurity::verdict', $wiTitle,
    'ما يصل المستخدمَ في قائمةِ مهامِّه خالٍ من التشكيلِ والرمزِ والمعادلة',
    $wiTitle !== '' && $wiV['clean'], $wiV['clean'] ? 'نقيّ' : implode(' · ', $wiV['defects']));

/* ⑬ العنوانُ يطابق المسمّى المعتمدَ حرفًا */
$regLabel = $act ? R::label($conn, 'action:' . $act['canonical_code'], '') : '';
$match = $wiTitle !== '' && $regLabel !== '' && $wiTitle === $regLabel;
$st('العنوانُ يطابق المسمّى المعتمدَ حرفًا', 'repair01_ui_labels ⇐ work_items', $wiTitle,
    'المستهلكُ يقرأ من السجلِّ لا من الجدولِ — فتغييرُ المسمّى يسري على كلِّ ما يولد بعدَه',
    $match, $match ? 'مطابق' : "السجل «{$regLabel}»");

/* ⑭ رمزٌ داخليٌّ يُعرَض من القاموسِ لا خامًّا */
$disp = R::display($conn, 'NEEDS_SOURCE');
$dispOk = $disp !== '' && $disp !== 'NEEDS_SOURCE' && !P::hasTechTerm($disp);
$st('الرمزُ الداخليُّ يُعرَض من القاموسِ لا خامًّا', 'UiLabelRegistry::display', $disp,
    'المستخدمُ يقرأ ما ينقصه لا اسمَ الشرطِ الذي في القاعدة',
    $dispOk, "NEEDS_SOURCE ⇐ $disp");

/* ⑮ مسمًّى مشكولٌ يُرفَض ويُقيَّد الرفض */
$before = (int) $one("SELECT COUNT(*) FROM repair01_w6_reject_log WHERE run_id = '" . $esc($RUN) . "'");
$r1 = R::register($conn, 'w6j:diacritic', 'سجلُّ المشغِّلين المُعتمَد', array(
    'allowed_context' => 'SIDEBAR', 'caller' => 'repair01_w6_journey', 'run_id' => $RUN));
$after1 = (int) $one("SELECT COUNT(*) FROM repair01_w6_reject_log
                       WHERE run_id = '" . $esc($RUN) . "' AND reject_code = 'DIACRITICS'");
$stored1 = (int) $one("SELECT COUNT(*) FROM repair01_ui_labels WHERE technical_key = 'w6j:diacritic'");
$st('مسمًّى مشكولٌ يُرفَض ويُقيَّد الرفض', 'UiLabelRegistry::register › repair01_w6_reject_log',
    'سجلُّ المشغِّلين المُعتمَد',
    'المخالفُ لا يدخل السجلَّ — والرفضُ يصير صفًّا يُراجَع لا صمتًا',
    empty($r1['ok']) && $r1['code'] === 'DIACRITICS' && $after1 > $before && $stored1 === 0,
    "الرد {$r1['code']} · قيدٌ $after1 · دخل السجلَّ $stored1");

/* ⑯ مسمًّى بمصطلحٍ تقنيٍّ يُرفَض ويُقيَّد */
$r2 = R::register($conn, 'w6j:tech', 'سجل الحركة من جدول stock_move', array(
    'allowed_context' => 'SIDEBAR', 'caller' => 'repair01_w6_journey', 'run_id' => $RUN));
$after2 = (int) $one("SELECT COUNT(*) FROM repair01_w6_reject_log
                       WHERE run_id = '" . $esc($RUN) . "' AND reject_code = 'TECH_TERM'");
$stored2 = (int) $one("SELECT COUNT(*) FROM repair01_ui_labels WHERE technical_key = 'w6j:tech'");
$st('مسمًّى بمصطلحٍ تقنيٍّ يُرفَض ويُقيَّد', 'UiLabelRegistry::register › repair01_w6_reject_log',
    'سجل الحركة من جدول stock_move',
    'اسمُ الجدولِ لغةُ نظامٍ لا لغةُ عمل — ويُردُّ عند البابِ لا بعد الظهور',
    empty($r2['ok']) && $r2['code'] === 'TECH_TERM' && $after2 > 0 && $stored2 === 0,
    "الرد {$r2['code']} · قيدٌ $after2 · دخل السجلَّ $stored2");

/* ⑰ مسمًّى خارجَ السجلِّ يُطلب فيُقيَّد رفضُه ويُعاد مُنقّى */
$fb = R::label($conn, 'w6j:absent', 'سجلُّ الوردياتِ المُعتمَد');
$after3 = (int) $one("SELECT COUNT(*) FROM repair01_w6_reject_log WHERE reject_code = 'NOT_REGISTERED'
                       AND technical_key = 'w6j:absent'");
$fbOk = !P::hasDiacritics($fb) && $after3 > 0;
$st('طلبُ مسمًّى خارجَ السجلِّ يُقيَّد ويُعاد مُنقّى', 'UiLabelRegistry::label', $fb,
    'الشاشةُ لا تنكسر ولا يمرُّ اسمٌ خارجَ السجلِّ بلا أثرٍ — والقيدُ هو الأثر',
    $fbOk, "أُعيد «{$fb}» · قيدٌ {$after3}");

/* ⑱ الصيغةُ المتقاعدةُ لا تظهر حيّةً في أيِّ مصدرٍ مُصيَّر */
$dep = repair01_w6_deprecated_live($conn);
$st('الصيغةُ المتقاعدةُ لا تظهر حيّةً', 'repair01_ui_labels.deprecated_label ⇐ المصادرُ المُصيَّرة',
    (string) $dep['checked'] . ' صيغةً متقاعدة',
    'ما تقاعد لا يعود من البابِ الخلفيّ — والقديمُ محفوظٌ دليلًا لا حيًّا',
    count($dep['alive']) === 0,
    'متقاعدٌ مسجَّلٌ ' . $dep['checked'] . ' · حيٌّ ' . count($dep['alive']));

/* ⑲ **المحطّةُ التي أضافتها الجولةُ الثانية**: النصُّ المكتوبُ في **ملفِّ
      الشاشةِ نفسِه** — لا في جدول. والمحطّةُ تُقاس على **قشرتَي كلِّ صفحة**
      (`insidebar.php` و`inheader.php`): هما ما يقع عليه بصرُ المستخدمِ في كلِّ
      طلبٍ يفتحه، وكانتا خارجَ مقامِ الجولةِ الأولى كلَّه.
      ◆ **والأثرُ يُقاس على نصِّ الواجهةِ المصنَّفِ لا على الملفِّ خامًّا**:
        تعليقُ الشيفرةِ ليس واجهةً، والنصُّ المقترنُ قيمةُ بياناتٍ تُعذَر —
        وقياسُ الخامِّ يخلط الثلاثةَ فيعطي رقمًا يصف أسلوبَ التوثيق. */
require_once $ROOT . '/tools/lib/repair01_w6_files.php';
$shellVocab = repair01_w6_coupled_vocab($ROOT, $conn);
$shellUi = 0; $shellSeen = 0; $shellTxt = array();
foreach (array('insidebar.php', 'inheader.php') as $shell) {
    $p = $ROOT . '/' . $shell;
    if (!is_file($p)) { continue; }
    $m = repair01_w6_file_measure((string) file_get_contents($p), $shellVocab);
    $shellSeen++;
    $shellUi += $m['ui'];
    $shellTxt[] = $shell . ' ' . $m['ui'];
}
$st('قشرةُ كلِّ صفحةٍ بلا تشكيلٍ في نصِّها المُصيَّر', 'insidebar.php · inheader.php',
    implode(' · ', $shellTxt),
    'المستخدم يفتح اي شاشة فيرى قشرتها اولا — ونصها كان خارج مقام الجولة الاولى كله',
    $shellSeen === 2 && $shellUi === 0,
    'قشرتان ' . $shellSeen . ' · تشكيلُ واجهةٍ ' . $shellUi);

/* ⑳ **والمعجمُ المقترنُ مُعلَنٌ بعددِه لا مُدَّعًى صفرًا**: ما أُعفي من التنقيةِ
      لأنّه يُقارَن مكتوبٌ في `repair01_w6_coupled` بمفردتِه وسببِه وعددِ ما
      أعفت — فالصفرُ في المحطّةِ السابقةِ صفرُ **نصِّ واجهةٍ**، لا صفرُ ادّعاء. */
$cpN = (int) $one("SELECT COUNT(*) FROM repair01_w6_coupled");
$cpM = (int) $one("SELECT COALESCE(SUM(marks),0) FROM repair01_w6_coupled");
$cpBare = (int) $one("SELECT COUNT(*) FROM repair01_w6_coupled WHERE why = '' OR couple_kind = ''");
$st('المُعفى من التنقيةِ مُعلَنٌ بمفردتِه وسببِه', 'repair01_w6_coupled',
    $cpN . ' مفردةً تُقارَن ولا تُعرَض',
    'نص يقارن به لا يعرض — ونزع تشكيله في طرف دون طرف يفك حكما بلا خطأ ظاهر فيعلن ولا يمس',
    $cpN > 0 && $cpBare === 0,
    'مفرداتٌ ' . $cpN . ' · علاماتٌ مُعفاةٌ ' . $cpM . ' · بلا سببٍ ' . $cpBare);

/* ㉑ **ولا نقطتَينِ في اسمِ عنصرٍ في آخِرِ المحطّات**: الجولةُ الأولى عبرت
      رحلتَها كاملةً وهي تولّد النقطتَين — لأنَّ الرحلةَ لم تكن تسأل عنها. */
$colonJ = 0;
foreach (repair01_w6_rendered_sources() as $ck => $csrc) {
    foreach (repair01_w6_read($conn, $csrc) as $v) {
        if (P::hasNameColon(P::maskProtected($v, (int) $csrc['composite'] === 1))) { $colonJ++; }
    }
}
/* والقياسُ على قاعدةِ نزعِ الزخرفةِ نفسِها — لا على مخرَجٍ يُصلحه حزامٌ تالٍ */
$dashOk = mb_strpos(P::stripDecoration('لوحة الإدارة — النقل'), ':') === false
       && mb_strpos(P::purifyGenerated('لوحة الإدارة — النقل'), ':') === false;
$st('لا نقطتَينِ في اسمِ عنصرٍ ولا شرطةَ ربطٍ تصير نقطتَين',
    'UiPurity::purifyGenerated ⇐ كلُّ مصدرٍ مُصيَّر',
    'لوحة الإدارة — النقل ⇐ ' . P::purifyGenerated('لوحة الإدارة — النقل'),
    'اسم العنصر يقرا اسما لا سطر تصنيف — والقاعدة الثالثة تمنع النقطتين بمثال حرفي',
    $colonJ === 0 && $dashOk,
    'نقطتانِ في المصادر ' . $colonJ . ' · الشرطةُ لا تصير نقطتَين ' . ($dashOk ? 'نعم' : 'لا'));

/* ── الكنسُ: بندُ العملِ المُنشأُ للرحلةِ يُنزع، والقيدُ يبقى دليلًا ────────── */
$conn->query("DELETE FROM work_items WHERE source_ref LIKE 'W6J-%'");
$conn->query("DELETE FROM repair01_ui_labels WHERE technical_key LIKE 'w6j:%'");

echo "\n" . str_repeat('─', 78) . "\n";
printf("محطّات %d · عابرٌ %d · ساقطٌ %d\n", $N, $PASS, $N - $PASS);
echo 'الحكم: ' . ($PASS === $N ? "الرحلةُ تعبر ✔\n" : "الرحلةُ لا تعبر ✘\n");
exit($PASS === $N ? 0 : 1);
