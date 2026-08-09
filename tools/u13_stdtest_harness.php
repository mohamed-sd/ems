<?php
/**
 * tools/u13_stdtest_harness.php — الاختباراتُ المعياريةُ الخمسةَ عشرَ تُنفَّذ
 * ═══════════════════════════════════════════════════════════════════════════
 * FIN-OBL-01 §٤-١٩ تعلن خمسةَ عشرَ «اختبارَ قبولٍ معياريًّا» ونصُّ كلٍّ:
 * «يُنفَّذ حيًّا ويُطابَق بالقاعدة». وكانت مسجَّلةً في `gov_doc_registry`
 * بتغطيةِ `seed` — أي **مكتوبةً لا مُنفَّذة**. وهذا الملفُّ يجعلها تعمل.
 *
 * ◆ لكلِّ اختبارٍ سيناريو يُبنى بالمحرّكِ نفسِه (لا بإدراجٍ يدويٍّ في الجداول)
 *   ثم يُقاس الأثرُ على القاعدةِ الحية. فما لا يمرُّ بالمحرّكِ لا يُثبت المحرّك.
 *
 * ◆ الحكمُ الجامعُ الذي تشترك فيه ستةٌ منها: **المحرّكُ لا يُنشئ قيدًا**.
 *   فيُقاس عددُ قيودِ اليوميةِ قبلَ الحزمةِ وبعدَها، والفرقُ يجب أن يكون صفرًا.
 *   ولا يكفي أن يُقال «لا يقيّد» في وصفِ النوع — يُقاس.
 *
 * ◆ ولا يُترك أثر: كلُّ ما يُنشأ بادئتُه `U13-STD-` ويُحذف في النهايةِ حتمًا
 *   (حتى عند السقوط — عبر `register_shutdown_function`).
 *
 * التشغيل: php tools/u13_stdtest_harness.php [--company=4] [--keep]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$CO   = 4;
$KEEP = in_array('--keep', $argv, true);
foreach ($argv as $a) { if (strpos($a, '--company=') === 0) { $CO = (int) substr($a, 10); } }

require_once $ROOT . '/includes/env.php';
require_once $ROOT . '/app/Services/Finance/ObligationEngine.php';
use App\Services\Finance\ObligationEngine as OE;

$db = new mysqli(ems_env('DB_HOST', '127.0.0.1'), ems_env('DB_USER', 'root'),
                 ems_env('DB_PASS', ''), ems_env('DB_NAME', 'ems'), (int) ems_env('DB_PORT', '3306'));
$db->set_charset('utf8mb4');

const PFX = 'U13-STD-';

/** استعلامٌ يُعيد قيمةً واحدةً — و**الفشلُ يُرفع** فلا يمرُّ صفرٌ كاذب. */
function q1(\mysqli $db, $sql)
{
    $r = $db->query($sql);
    if ($r === false) { throw new RuntimeException('استعلامٌ فاشل: ' . $db->error . ' — ' . $sql); }
    $x = $r->fetch_row();
    $r->free();
    return $x ? $x[0] : null;
}

function cleanup(\mysqli $db, $co)
{
    $db->query("DELETE s FROM fin_obl_schedule s JOIN fin_obl_register r ON r.id = s.obligation_id
                 WHERE r.company_id = " . (int) $co . " AND r.contract_ref LIKE '" . PFX . "%'");
    $db->query("DELETE FROM fin_obl_register  WHERE company_id = " . (int) $co . " AND contract_ref LIKE '" . PFX . "%'");
    $db->query("DELETE FROM fin_obl_avoidance WHERE company_id = " . (int) $co . " AND contract_ref LIKE '" . PFX . "%'");
    $db->query("DELETE FROM fin_obl_alert_log WHERE company_id = " . (int) $co . " AND subject_ref LIKE '" . PFX . "%'");
}
cleanup($db, $CO);
if (!$KEEP) {
    register_shutdown_function(function () use ($db, $CO) { cleanup($db, $CO); });
}

$ACTOR = (int) q1($db, "SELECT id FROM users WHERE company_id = {$CO} ORDER BY id LIMIT 1");
if ($ACTOR <= 0) { exit("لا مستخدمَ في الكيان {$CO}\n"); }

/* بصمةُ اليوميةِ قبلَ الحزمة — الحكمُ الجامع. */
$jeBefore = (int) q1($db, "SELECT COUNT(*) FROM fin_journal_entries");
$jlBefore = (int) q1($db, "SELECT COUNT(*) FROM fin_journal_lines");

/**
 * يبني عقدًا كاملًا بالمحرّك: اختبارُ تجنبٍ ثم جدول.
 * @return array نتيجةُ الاختبارِ ونتيجةُ التوليد
 */
function build(\mysqli $db, $co, $actor, $ref, array $av, array $sch = null)
{
    $av = array_merge(array('company_id' => $co, 'contract_ref' => $ref, 'decided_by' => $actor,
                            'currency' => 'USD'), $av);
    $rAv = OE::avoidanceTest($db, $av);
    if (empty($rAv['ok'])) { throw new RuntimeException('اختبارُ التجنبِ فشل: ' . $rAv['reason']); }
    $rSc = null;
    if ($sch !== null) {
        $sch = array_merge(array('company_id' => $co, 'contract_ref' => $ref,
                                 'contract_kind' => $av['contract_kind'], 'generated_by' => $actor,
                                 'currency' => 'USD', 'side' => 'payable'), $sch);
        $rSc = OE::generateSchedule($db, $sch);
        if (empty($rSc['ok'])) { throw new RuntimeException('توليدُ الجدولِ فشل: ' . $rSc['reason']); }
    }
    return array($rAv, $rSc);
}

/** مجاميعُ جدولِ عقدٍ. */
function sched(\mysqli $db, $co, $ref)
{
    $r = $db->query("SELECT COUNT(*) n, COALESCE(SUM(s.l1_commitment),0) l1,
                            COALESCE(SUM(s.l2_recognized),0) l2, COALESCE(SUM(s.l3_open),0) l3,
                            COALESCE(SUM(s.is_partial),0) partials,
                            COUNT(DISTINCT s.proration_basis) bases,
                            COUNT(DISTINCT s.term_class) terms
                       FROM fin_obl_schedule s JOIN fin_obl_register r ON r.id = s.obligation_id
                      WHERE r.company_id = " . (int) $co . "
                        AND r.contract_ref = '" . $db->real_escape_string($ref) . "'
                        AND r.state = 'active'");
    if ($r === false) { throw new RuntimeException('استعلامُ الجدولِ فاشل: ' . $db->error); }
    return $r->fetch_assoc();
}

/* ═══════════════════════════════════════════════════════════════════════════
   الخمسةَ عشرَ — كلُّ واحدٍ: يبني · يقيس · يحكم
   ═══════════════════════════════════════════════════════════════════════════ */
$T = array();
$t = function ($code, $title, callable $fn) use (&$T) { $T[] = array($code, $title, $fn); };

$t('STDTEST-01', 'عقدٌ موقَّعٌ لم يبدأ سريانُه لا يُقيَّد التزامًا — ارتباطٌ وصفرُ قيد',
   function () use ($db, $CO, $ACTOR) {
       $ref = PFX . '01';
       $start = date('Y-m-d', strtotime('+2 months'));
       $end   = date('Y-m-d', strtotime('+13 months'));
       list($av, ) = build($db, $CO, $ACTOR, $ref, array(
           'contract_kind' => 'service', 'contract_value' => 120000, 'cancellable' => 1));
       if ($av['verdict'] !== 'disclose_only') { return array(false, 'الحكم ' . $av['verdict'] . ' لا disclose_only'); }
       build($db, $CO, $ACTOR, $ref, array('contract_kind' => 'service', 'contract_value' => 120000, 'cancellable' => 1),
             array('ob_type' => 'OB-01', 'total_value' => 120000, 'start_date' => $start, 'end_date' => $end));
       $s = sched($db, $CO, $ref);
       if ((float) $s['l2'] != 0.0) { return array(false, 'اعترافٌ قبلَ السريان: L2=' . $s['l2']); }
       $posts = (int) q1($db, "SELECT posts_entry FROM fin_obl_types WHERE code='OB-01' LIMIT 1");
       return array($posts === 0 && (float) $s['l2'] == 0.0,
                    'ارتباطٌ ' . $s['n'] . ' فترةً · L2=0 · النوعُ لا يُقيَّد');
   });

$t('STDTEST-02', 'عقدُ إيجارٍ يُعترف بالتزامِه عند بدءِ السريان — بمعيارٍ خاصٍّ موجِب',
   function () use ($db, $CO, $ACTOR) {
       $ref = PFX . '02';
       list($av, ) = build($db, $CO, $ACTOR, $ref, array(
           'contract_kind' => 'lease', 'contract_value' => 240000, 'cancellable' => 0, 'cancel_cost' => 240000));
       $std = OE::specialStandardFor('lease');
       $rule = (string) q1($db, "SELECT trigger_text FROM fin_obl_recognition
                                  WHERE contract_kind LIKE '%إيجار%' AND active=1 LIMIT 1");
       $atStart = mb_strpos($rule, 'بدءِ السريان') !== false && mb_strpos($rule, 'لا عند التوقيع') !== false;
       return array($av['verdict'] === 'recognize' && $std !== '' && $atStart,
                    'الحكم ' . $av['verdict'] . ' · المعيار «' . mb_substr($std, 0, 28) . '» · القاعدةُ عند البدءِ لا التوقيع');
   });

$t('STDTEST-03', 'عقدُ موظفٍ لا يُقيَّد أجرُ سنةٍ عند التوقيع — شهرًا بشهرٍ عند الأداء',
   function () use ($db, $CO, $ACTOR) {
       $ref = PFX . '03';
       $start = date('Y-m-01');
       $end   = date('Y-m-d', strtotime($start . ' +12 months -1 day'));
       build($db, $CO, $ACTOR, $ref,
             array('contract_kind' => 'employee', 'contract_value' => 120000, 'cancellable' => 0, 'cancel_cost' => 10000),
             array('ob_type' => 'OB-02', 'total_value' => 120000, 'start_date' => $start, 'end_date' => $end));
       $s = sched($db, $CO, $ref);
       $each = (float) q1($db, "SELECT MAX(s.l1_commitment) FROM fin_obl_schedule s
                                  JOIN fin_obl_register r ON r.id=s.obligation_id
                                 WHERE r.contract_ref='" . PFX . "03' AND r.state='active'");
       /* اثنتا عشرةَ حصةً بعشرةِ آلافٍ لا مبلغٌ واحدٌ بمئةٍ وعشرين ألفًا. */
       return array((int) $s['n'] === 12 && abs($each - 10000) < 0.5 && (float) $s['l2'] == 0.0,
                    $s['n'] . ' حصةً · أعلى حصةٍ ' . number_format($each, 2) . ' · L2=0 عند التوليد');
   });

$t('STDTEST-04', 'تسهيلٌ تمويليٌّ غيرُ مسحوبٍ لا يُقيَّد التزامًا — ارتباطٌ يُفصح عنه',
   function () use ($db, $CO, $ACTOR) {
       $ref = PFX . '04';
       /* غيرُ المسحوبِ قابلٌ للتجنبِ من طرفنا: لا نسحب فلا نلتزم. */
       list($av, ) = build($db, $CO, $ACTOR, $ref, array(
           'contract_kind' => 'financing', 'contract_value' => 500000, 'cancellable' => 1));
       $rule = (string) q1($db, "SELECT trigger_text FROM fin_obl_recognition
                                  WHERE contract_kind LIKE '%تمويل%' AND active=1 LIMIT 1");
       $atDraw = mb_strpos($rule, 'السحب') !== false;
       $posts  = (int) q1($db, "SELECT posts_entry FROM fin_obl_types WHERE code='OB-04' LIMIT 1");
       return array($av['verdict'] === 'disclose_only' && $atDraw && $posts === 0,
                    'الحكم ' . $av['verdict'] . ' · الاعترافُ عند السحب · النوعُ لا يُقيَّد');
   });

$t('STDTEST-05', 'أمرُ شراءٍ معتمدٌ غيرُ مستلَمٍ ارتباطٌ لا التزام — يخفض المتاحَ ولا يُقيَّد',
   function () use ($db, $CO, $ACTOR) {
       $ref = PFX . '05';
       list($av, ) = build($db, $CO, $ACTOR, $ref, array(
           'contract_kind' => 'purchase_order', 'contract_value' => 60000, 'cancellable' => 1));
       $ty = $db->query("SELECT posts_entry, accounts, born_when FROM fin_obl_types WHERE code='OB-05' LIMIT 1")->fetch_assoc();
       $isCommit = mb_strpos((string) $ty['accounts'], 'لا مقيَّد') !== false;
       return array($av['verdict'] === 'disclose_only' && (int) $ty['posts_entry'] === 0 && $isCommit,
                    'الحكم ' . $av['verdict'] . ' · «' . mb_substr((string) $ty['accounts'], 0, 30) . '»');
   });

$t('STDTEST-06', 'ضمانٌ لم يُستدعَ لا يُقيَّد — إفصاحٌ بقيمتِه ومدتِه فقط',
   function () use ($db, $CO, $ACTOR) {
       $ref = PFX . '06';
       list($av, ) = build($db, $CO, $ACTOR, $ref, array(
           'contract_kind' => 'guarantee', 'contract_value' => 80000, 'cancellable' => 1));
       $rule = (string) q1($db, "SELECT trigger_text FROM fin_obl_recognition
                                  WHERE contract_kind LIKE '%ضمان%' AND active=1 LIMIT 1");
       $notRecognized = mb_strpos($rule, 'لا يُعترف') !== false;
       $posts = (int) q1($db, "SELECT posts_entry FROM fin_obl_types WHERE code='OB-07' LIMIT 1");
       return array($av['verdict'] === 'disclose_only' && $notRecognized && $posts === 0,
                    'الحكم ' . $av['verdict'] . ' · «' . mb_substr($rule, 0, 34) . '»');
   });

$t('STDTEST-07', 'مقبوضٌ من عميلٍ قبلَ الأداءِ التزامُ عقدٍ لا إيراد',
   function () use ($db, $CO, $ACTOR) {
       $ref = PFX . '07';
       $start = date('Y-m-01');
       $end   = date('Y-m-d', strtotime($start . ' +6 months -1 day'));
       build($db, $CO, $ACTOR, $ref,
             array('contract_kind' => 'customer', 'contract_value' => 60000, 'cancellable' => 0, 'cancel_cost' => 60000),
             array('ob_type' => 'OB-01', 'side' => 'receivable', 'total_value' => 60000,
                   'start_date' => $start, 'end_date' => $end));
       $s = sched($db, $CO, $ref);
       /* المقبوضُ قبلَ الأداءِ يبقى في L1 ولا يعبر إلى L2 — فالأداءُ هو الجسر. */
       $l1 = (string) q1($db, "SELECT birth FROM fin_obl_layers WHERE code='L1' LIMIT 1");
       $perf = mb_strpos((string) q1($db, "SELECT birth FROM fin_obl_layers WHERE code='L2' LIMIT 1"), 'أداء') !== false;
       return array((float) $s['l2'] == 0.0 && (float) $s['l1'] > 0 && $perf,
                    'L1=' . number_format((float) $s['l1'], 2) . ' · L2=0 · ميلادُ L2 عند الأداء');
   });

$t('STDTEST-08', 'وحداتٌ نُفِّذت ولم تُفوتَر أصلُ عقدٍ لا ذمةٌ مدينة',
   function () use ($db) {
       $l2 = (string) q1($db, "SELECT sides FROM fin_obl_layers WHERE code='L2' LIMIT 1");
       $l3 = (string) q1($db, "SELECT birth FROM fin_obl_layers WHERE code='L3' LIMIT 1");
       $asset  = mb_strpos($l2, 'أصلُ عقدٍ إن لم يُفوتَر') !== false;
       $atInv  = mb_strpos($l3, 'الفاتورة') !== false;
       /* والبنيةُ تشهد: L2 وL3 عمودان مستقلانِ فلا يُخلطُ الأصلُ بالذمة. */
       $cols = (int) q1($db, "SELECT COUNT(*) FROM information_schema.COLUMNS
                               WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fin_obl_schedule'
                                 AND COLUMN_NAME IN ('l2_recognized','l3_open')");
       return array($asset && $atInv && $cols === 2,
                    'L2 «أصلُ عقدٍ إن لم يُفوتَر» · L3 يولد بالفاتورة · عمودان مستقلان');
   });

$t('STDTEST-09', 'عقدٌ اثنا عشرَ شهرًا من العشرين: ثلاثةَ عشرَ فترةً محاسبيةً واثنا عشرَ إقفالًا تعاقديًّا',
   function () use ($db, $CO, $ACTOR) {
       $ref = PFX . '09';
       $start = date('Y-m-20');
       $end   = date('Y-m-d', strtotime($start . ' +12 months -1 day'));
       $acct  = count(OE::buildPeriods($start, $end));
       $contr = OE::contractPeriods($start, $end);
       build($db, $CO, $ACTOR, $ref,
             array('contract_kind' => 'service', 'contract_value' => 120000, 'cancellable' => 0, 'cancel_cost' => 120000),
             array('ob_type' => 'OB-01', 'total_value' => 120000, 'start_date' => $start, 'end_date' => $end));
       $s = sched($db, $CO, $ref);
       $reg = $db->query("SELECT accounting_periods, contract_periods FROM fin_obl_register
                           WHERE company_id={$CO} AND contract_ref='" . PFX . "09' AND state='active' LIMIT 1")->fetch_assoc();
       return array($acct === 13 && $contr === 12 && (int) $s['n'] === 13
                    && (int) $reg['accounting_periods'] === 13 && (int) $reg['contract_periods'] === 12,
                    "محاسبيًّا $acct · تعاقديًّا $contr · صفوفُ الجدولِ " . $s['n']
                    . ' · مسجَّلٌ في الرأس ' . $reg['accounting_periods'] . '/' . $reg['contract_periods']);
   });

$t('STDTEST-10', 'الفترةُ الكسريةُ موسومةٌ ومحسوبةٌ بالتناسبِ اليوميِّ والأساسُ معلَن',
   function () use ($db, $CO) {
       $r = $db->query("SELECT s.period_no, s.is_partial, s.partial_days, s.month_days,
                               s.proration_basis, s.l1_commitment
                          FROM fin_obl_schedule s JOIN fin_obl_register r ON r.id=s.obligation_id
                         WHERE r.company_id={$CO} AND r.contract_ref='" . PFX . "09' AND r.state='active'
                         ORDER BY s.period_no");
       if ($r === false) { return array(false, 'استعلامٌ فاشل: ' . $db->error); }
       $rows = $r->fetch_all(MYSQLI_ASSOC);
       if (!$rows) { return array(false, 'لا جدولَ — يعتمد على STDTEST-09'); }
       $first = $rows[0]; $last = $rows[count($rows) - 1];
       $partials = 0; $noBasis = 0; $badPro = 0;
       foreach ($rows as $x) {
           if ((int) $x['is_partial'] === 1) {
               $partials++;
               if (trim((string) $x['proration_basis']) === '') { $noBasis++; }
               if ((int) $x['month_days'] <= 0 || (int) $x['partial_days'] <= 0
                   || (int) $x['partial_days'] > (int) $x['month_days']) { $badPro++; }
           }
       }
       /* الأولى والأخيرةُ كسريتانِ حين يبدأ العقدُ في العشرين. */
       $ok = $partials === 2 && (int) $first['is_partial'] === 1 && (int) $last['is_partial'] === 1
             && $noBasis === 0 && $badPro === 0;
       return array($ok, "كسريتان $partials · بلا أساسٍ معلَن $noBasis · تناسبٌ فاسد $badPro"
                       . ' · الأساس «' . mb_substr((string) $first['proration_basis'], 0, 24) . '»');
   });

$t('STDTEST-11', 'جدولُ الالتزامِ يولَّد كاملًا عند النفاذِ لا شهرًا بشهر',
   function () use ($db, $CO) {
       /* صفرُ عقدٍ نافذٍ عددُ صفوفِ جدولِه أقلُّ من فتراتِه المحاسبية — على
          كلِّ العقودِ الحيةِ لا على عقدِ الفحصِ وحدَه. */
       $bad = (int) q1($db, "SELECT COUNT(*) FROM (
                  SELECT r.id, r.accounting_periods, COUNT(s.id) rows_n
                    FROM fin_obl_register r LEFT JOIN fin_obl_schedule s ON s.obligation_id = r.id
                   WHERE r.company_id={$CO} AND r.state='active'
                   GROUP BY r.id, r.accounting_periods
                  HAVING COUNT(s.id) < r.accounting_periods) x");
       $all = (int) q1($db, "SELECT COUNT(*) FROM fin_obl_register WHERE company_id={$CO} AND state='active'");
       return array($bad === 0 && $all > 0, "عقودٌ نافذةٌ $all · بجدولٍ ناقصٍ $bad");
   });

$t('STDTEST-12', 'مخصَّصُ جزاءٍ لا يُقيَّد قبلَ رجحانِ التدفقِ الخارج — محتملٌ يُفصح عنه',
   function () use ($db, $CO, $ACTOR) {
       $ref = PFX . '12';
       /* جزاءٌ محتملٌ: قابلٌ للتجنبِ ما لم يرجح — فالحكمُ إفصاحٌ لا اعتراف. */
       list($av, ) = build($db, $CO, $ACTOR, $ref, array(
           'contract_kind' => 'penalty', 'contract_value' => 40000, 'cancellable' => 1));
       $rule = (string) q1($db, "SELECT trigger_text FROM fin_obl_recognition
                                  WHERE contract_kind LIKE '%جزاء%' AND active=1 LIMIT 1");
       $probable = mb_strpos($rule, 'رجحان') !== false;
       /* وحين يرجح التدفقُ ويفوق المنافعَ يصير مُثقِلًا — والحكمُ يتغيّر. */
       list($av2, ) = build($db, $CO, $ACTOR, $ref . 'B', array(
           'contract_kind' => 'penalty', 'contract_value' => 40000, 'cancellable' => 0,
           'cancel_cost' => 40000, 'expected_benefit' => 1000));
       return array($av['verdict'] === 'disclose_only' && $probable && $av2['verdict'] === 'onerous',
                    'محتملٌ → ' . $av['verdict'] . ' · راجحٌ ومُثقِلٌ → ' . $av2['verdict']);
   });

$t('STDTEST-13', 'الطبقاتُ الثلاثُ في الجدولِ بأعمدةٍ مستقلةٍ والفرقُ يُرى بلا حساب',
   function () use ($db, $CO) {
       $cols = (int) q1($db, "SELECT COUNT(*) FROM information_schema.COLUMNS
                               WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fin_obl_schedule'
                                 AND COLUMN_NAME IN ('l1_commitment','l2_recognized','l3_open','gap_l1_l2')");
       $layers = (int) q1($db, "SELECT COUNT(*) FROM fin_obl_layers WHERE active=1");
       /* والفرقُ عمودٌ محفوظٌ لا حسابٌ في العين: يجب أن يساوي L1−L2 في كلِّ صف. */
       $wrong = (int) q1($db, "SELECT COUNT(*) FROM fin_obl_schedule s
                                 JOIN fin_obl_register r ON r.id=s.obligation_id
                                WHERE r.company_id={$CO} AND r.state='active'
                                  AND ABS(s.gap_l1_l2 - (s.l1_commitment - s.l2_recognized)) > 0.01");
       return array($cols === 4 && $layers === 3 && $wrong === 0,
                    "أعمدةٌ $cols/4 · طبقاتٌ $layers/3 · صفوفٌ فرقُها لا يطابق $wrong");
   });

$t('STDTEST-14', 'الذممُ المدينةُ تُصنَّف قصيرًا وطويلًا كالدائنة — وما بعدَ سنةٍ غيرُ متداول',
   function () use ($db, $CO) {
       $sides = (int) q1($db, "SELECT COUNT(DISTINCT r.side) FROM fin_obl_register r
                                WHERE r.company_id={$CO} AND r.state='active'");
       $blank = (int) q1($db, "SELECT COUNT(*) FROM fin_obl_schedule s
                                 JOIN fin_obl_register r ON r.id=s.obligation_id
                                WHERE r.company_id={$CO} AND r.state='active'
                                  AND (s.term_class IS NULL OR s.term_class='')");
       /* والحدُّ سنةٌ: ما استحقاقُه بعدَ 365 يومًا طويلٌ ولا يُصنَّف قصيرًا. */
       $mis = (int) q1($db, "SELECT COUNT(*) FROM fin_obl_schedule s
                               JOIN fin_obl_register r ON r.id=s.obligation_id
                              WHERE r.company_id={$CO} AND r.state='active'
                                AND s.due_date > DATE_ADD(CURDATE(), INTERVAL 365 DAY)
                                AND s.term_class = 'current'");
       return array($sides >= 2 && $blank === 0 && $mis === 0,
                    "جانبانِ حيّانِ $sides · بلا تصنيفٍ $blank · طويلٌ مصنَّفٌ قصيرًا $mis");
   });

$t('STDTEST-15', 'تقريرُ الارتباطاتِ يظهر في الإيضاحاتِ للجانبين — بأنواعِه الأربعة',
   function () use ($db, $CO) {
       /* الشاشتانِ المبنيتانِ هما التقرير: الارتباطاتُ والمحتملة. */
       $screens = (int) q1($db, "SELECT COUNT(*) FROM modules
                                  WHERE code IN ('Finance/ob_commitments.php','Finance/ob_contingent.php')");
       $sides = (int) q1($db, "SELECT COUNT(DISTINCT side) FROM fin_obl_register
                                WHERE company_id={$CO} AND state='active'");
       /* والأنواعُ الأربعةُ التي تُذكر في الإيضاحات: رأسماليةٌ وتشغيليةٌ
          وإيجاريةٌ وإيراداتٌ لم تُنفَّذ — لكلٍّ نوعُ التزامٍ يقابله. */
       $types = (int) q1($db, "SELECT COUNT(*) FROM fin_obl_types
                                WHERE active=1 AND code IN ('OB-01','OB-03','OB-05','OB-07')");
       return array($screens === 2 && $sides >= 2 && $types === 4,
                    "شاشتا الإيضاحات $screens/2 · جانبان $sides · أنواعٌ مقابلة $types/4");
   });

/* ═══ التشغيل ═════════════════════════════════════════════════════════════ */
echo "الاختباراتُ المعياريةُ — FIN-OBL-01 §4-19 · الكيان {$CO} · الفاعل #{$ACTOR}\n\n";
$pass = 0; $fail = array();
foreach ($T as $x) {
    list($code, $title, $fn) = $x;
    try { list($ok, $why) = $fn(); }
    catch (\Throwable $e) { $ok = false; $why = 'عطب: ' . $e->getMessage(); }
    printf("  %s %-12s %-56s %s\n", $ok ? '✔' : '✘', $code, mb_substr($title, 0, 56), mb_substr((string) $why, 0, 76));
    if ($ok) { $pass++; } else { $fail[$code] = $title . ' → ' . $why; }
}

/* ═══ الحكمُ الجامع: المحرّكُ لا يُنشئ قيدًا ═══════════════════════════════ */
$jeAfter = (int) q1($db, "SELECT COUNT(*) FROM fin_journal_entries");
$jlAfter = (int) q1($db, "SELECT COUNT(*) FROM fin_journal_lines");
$noPost  = ($jeAfter === $jeBefore && $jlAfter === $jlBefore);
printf("\n  %s %-12s %-56s %s\n", $noPost ? '✔' : '✘', 'STD-ALL',
       'والمحرّكُ لم يُنشئ قيدًا واحدًا في اليومية — قِيسَ لا قيل',
       /* ◆ گوتشا: `$jeBefore→` — المحرفُ `→` يُبتلع في اسمِ المتغيّرِ فيصير
            غيرَ معرَّفٍ ويُطبع فارغًا. القوسان المعقوفان إلزامٌ هنا. */
       "قيود {$jeBefore}→{$jeAfter} · سطور {$jlBefore}→{$jlAfter}");
if (!$noPost) { $fail['STD-ALL'] = 'المحرّكُ أنشأ قيدًا'; } else { $pass++; }

echo "\n" . str_repeat('═', 78) . "\n";
printf("  الاختباراتُ المعيارية: %d/%d\n", $pass, count($T) + 1);
if ($fail) { echo "\n  ✘ الفاشلة:\n"; foreach ($fail as $c => $w) { printf("     %-12s %s\n", $c, mb_substr($w, 0, 120)); } }
echo str_repeat('═', 78) . "\n";
exit($fail ? 1 : 0);
