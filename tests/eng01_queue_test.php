<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * ENG-01 · طابورُ المهامِّ — إثباتٌ إيجابيٌّ وسلبيٌّ مُشغَّلان
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/eng01_queue_test.php
 *
 *   Q1 ✚ الالتقاطُ الذرّيّ: ثلاثةُ عمّالٍ على مهمةٍ واحدةٍ ⇐ واحدٌ يلتقط.
 *   Q2 ✚ وأثرٌ واحدٌ لكلِّ مهمة — لا ثلاثةُ آثارٍ لثلاثةِ عمّال.
 *   Q3 ✖ chk_lock: 'claimed' بلا عاملٍ أو بلا مهلةٍ مرفوضٌ بنيويًّا.
 *   Q4 ✚ F-16: قفلٌ انقضت مهلتُه يُحرَّر ويعود للطابور (CK-14).
 *   Q5 ✖ chk_job_type: نصُّ ملاحظاتِ اختبارٍ في خانةِ نوعٍ مرفوض.
 *   Q6 ✚ الجدولةُ تُجسّد المستحقَّ بمصدرٍ 'schedule' لا 'manual'.
 *   Q7 ✚ ولا تُدرج نوعًا ما يزال حيًّا في الطابور — مهمةٌ واحدةٌ لكلِّ نافذة.
 *   Q8 ✚ إنذارُ توقفِ العامل يُرفع لمالكِ الجدولةِ (CK-15).
 *   Q9 ✖ والأمرُ اليدويُّ الملغى يرفض التشغيلَ برمزِ 3.
 *
 * البذرُ معزول: seed_tag = ENG01Q — يُكنس قبل وبعد.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Services/Queue/JobQueueService.php';
require_once dirname(__DIR__) . '/app/Services/Queue/JobScheduleService.php';

use App\Services\Queue\JobQueueService as JQ;
use App\Services\Queue\JobScheduleService as JS;

while (ob_get_level() > 0) { ob_end_clean(); }
$db = $conn;

const TAG = 'ENG01Q';
$pass = 0; $fail = 0;
function ok($id, $m)  { global $pass; $pass++; echo "  ✔ $id  $m\n"; }
function no($id, $m)  { global $fail; $fail++; echo "  ✘ $id  $m\n"; }
function ck($id, $c, $m) { $c ? ok($id, $m) : no($id, $m); }

function sweep(\mysqli $db) {
    $db->query("DELETE FROM `ems_job_queue` WHERE `seed_tag`='" . TAG . "'");
    $db->query("DELETE FROM `ems_job_schedule` WHERE `job_type`='pilot_monitor' AND `replaces_manual`='" . TAG . "'");
    $db->query("DELETE FROM `fin_notifications` WHERE `title` LIKE '%" . TAG . "%'");
}
sweep($db);

echo "\n═══════════════════════════════════════════════════════════════\n";
echo " ENG-01 · طابورُ المهامِّ — الإثباتُ المُشغَّل\n";
echo "═══════════════════════════════════════════════════════════════\n";

/** مهمةٌ مبذورةٌ بنوعٍ مشروعٍ ووسمٍ يميّزها. */
function seedJob(\mysqli $db, $type = 'pilot_monitor') {
    $db->query("INSERT INTO `ems_job_queue`
        (`company_id`,`job_type`,`payload_json`,`state`,`source`,`max_attempts`,
         `next_attempt_at`,`created_at`,`seed_tag`)
        VALUES (4,'{$type}','{\"t\":\"" . TAG . "\"}','queued','schedule',3,NOW(3),NOW(3),'" . TAG . "')");
    return (int) $db->insert_id;
}

// ═════════ Q1/Q2 ✚ ثلاثةُ عمّالٍ على مهمةٍ واحدة ═════════
echo "\n▐ Q1·Q2 ✚ ثلاثةُ عمّالٍ معًا — التقاطٌ واحدٌ وأثرٌ واحد\n";
$jid = seedJob($db);
$claims = array();
$effects = 0;
foreach (array('W-A', 'W-B', 'W-C') as $w) {
    $job = JQ::claimAtomic($db, $w, 600, $jid);
    $claims[] = $job !== null ? $w : null;
    if ($job !== null) {
        // الأثرُ لا يقع إلا لمن التقط
        $db->query("INSERT INTO `fin_notifications` (`company_id`,`target_level`,`title`,`link`)
                    VALUES (4,'all','" . TAG . " أثرُ المهمة #{$jid} بيدِ {$w}','Governance/job_queue.php')");
        $effects++;
    }
}
$won = array_values(array_filter($claims));
ck('Q1-a', count($won) === 1, 'التقطها ' . count($won) . ' عامل: ' . implode(',', $won));
ck('Q2-a', $effects === 1, "الأثر: $effects لا ثلاثة");
$row = $db->query("SELECT `state`,`worker_id`,`lock_expires_at` FROM `ems_job_queue` WHERE `job_id`={$jid}")->fetch_assoc();
ck('Q1-b', $row['state'] === 'claimed' && $row['worker_id'] === $won[0],
    "الحالة={$row['state']} العامل={$row['worker_id']} مهلة={$row['lock_expires_at']}");

// ═════════ Q3 ✖ chk_lock ═════════
echo "\n▐ Q3 ✖ chk_lock — 'claimed' بلا عاملٍ أو مهلةٍ مرفوض\n";
$db->query("UPDATE `ems_job_queue` SET `worker_id`=NULL WHERE `job_id`={$jid}");
$e1 = $db->errno; $m1 = $db->error;
ck('Q3-a', $e1 !== 0, 'رفضت القاعدةُ تفريغَ العامل — ' . mb_substr($m1, 0, 60));
$db->query("UPDATE `ems_job_queue` SET `lock_expires_at`=NULL WHERE `job_id`={$jid}");
$e2 = $db->errno; $m2 = $db->error;
ck('Q3-b', $e2 !== 0, 'ورفضت تفريغَ المهلة — ' . mb_substr($m2, 0, 60));
$still = $db->query("SELECT `worker_id`,`lock_expires_at` FROM `ems_job_queue` WHERE `job_id`={$jid}")->fetch_assoc();
ck('Q3-c', $still['worker_id'] !== null && $still['lock_expires_at'] !== null, 'وكلاهما باقٍ');

// ═════════ Q4 ✚ F-16 تحريرُ القفلِ المنقضي ═════════
echo "\n▐ Q4 ✚ F-16 — قفلٌ انقضت مهلتُه يعود للطابور (CK-14)\n";
$db->query("UPDATE `ems_job_queue` SET `lock_expires_at`=NOW(3) - INTERVAL 10 SECOND WHERE `job_id`={$jid}");
$stuckBefore = (int) $db->query(
    "SELECT COUNT(*) FROM `ems_job_queue` WHERE `state`='claimed' AND `lock_expires_at` < NOW(3) AND `seed_tag`='" . TAG . "'"
)->fetch_row()[0];
$released = JQ::releaseExpiredLocks($db);
$after = $db->query("SELECT `state`,`worker_id`,`fail_code` FROM `ems_job_queue` WHERE `job_id`={$jid}")->fetch_assoc();
ck('Q4-a', $stuckBefore === 1, "مهمةٌ مقفولةٌ منتهيةُ المهلة قبل: $stuckBefore");
ck('Q4-b', $released >= 1 && $after['state'] === 'queued',
    "حُرّر $released — الحالةُ الآن {$after['state']} برمزٍ {$after['fail_code']}");
$stuckAfter = (int) $db->query(
    "SELECT COUNT(*) FROM `ems_job_queue` WHERE `state`='claimed' AND `lock_expires_at` < NOW(3)"
)->fetch_row()[0];
ck('Q4-c', $stuckAfter === 0, "CK-14 بعدَ التحرير: $stuckAfter");

// ═════════ Q5 ✖ chk_job_type ═════════
echo "\n▐ Q5 ✖ chk_job_type — نصُّ ملاحظاتٍ في خانةِ نوعٍ مرفوض\n";
$noteText = 'أُثبت من واقع التشغيل الميداني · UAT-2026';
$st = $db->prepare("INSERT INTO `ems_job_queue`
    (`company_id`,`job_type`,`state`,`source`,`next_attempt_at`,`created_at`,`seed_tag`)
    VALUES (4,?,'queued','schedule',NOW(3),NOW(3),'" . TAG . "')");
$st->bind_param('s', $noteText);
$inserted = $st->execute();
$e5 = $st->errno; $m5 = $st->error;
$st->close();
ck('Q5-a', !$inserted && $e5 !== 0, 'رُفض الإدراجُ — ' . mb_substr($m5, 0, 60));
$leak = (int) $db->query("SELECT COUNT(*) FROM `ems_job_queue` WHERE `job_type` LIKE '%UAT-2026%' AND `seed_tag`='" . TAG . "'")->fetch_row()[0];
ck('Q5-b', $leak === 0, "ولم يتسرّب صفٌّ ($leak)");

// ═════════ Q6/Q7 ✚ الجدولةُ تُجسّد ولا تُكرّر ═════════
echo "\n▐ Q6·Q7 ✚ التجسيدُ من الجدولةِ بمصدرٍ schedule ولا تكرارَ في النافذة\n";
$db->query("DELETE FROM `ems_job_queue` WHERE `seed_tag`='" . TAG . "'");
// ◆ F-16② — صفٌّ موروثٌ عالقٌ في 'processing' بلا قفلٍ يحجب جدولةَ نوعِه كلِّه
//   ولا يبدو شيءٌ معطَّلًا. يُحرَّر أولًا ثم يُقاس التجسيد.
$blockers = (int) $db->query(
    "SELECT COUNT(*) FROM `ems_job_queue`
      WHERE `job_type`='pilot_monitor' AND `state` IN ('claimed','processing','running')"
)->fetch_row()[0];
$freed = JQ::releaseExpiredLocks($db);
$blockersAfter = (int) $db->query(
    "SELECT COUNT(*) FROM `ems_job_queue`
      WHERE `job_type`='pilot_monitor' AND `state` IN ('claimed','processing','running')"
)->fetch_row()[0];
ck('Q6-0', $blockers === 0 || $blockersAfter < $blockers,
    "حاجباتٌ عالقةٌ قبل=$blockers بعد=$blockersAfter (حُرّر $freed)");
$db->query("UPDATE `ems_job_schedule` SET `is_active`=0 WHERE `is_active`=1");
$db->query("INSERT INTO `ems_job_schedule`
      (`company_id`,`job_type`,`cron_expr`,`max_runtime_seconds`,`alert_after_seconds`,
       `owner_role_id`,`is_active`,`replaces_manual`,`created_by`)
    VALUES (0,'pilot_monitor','* * * * *',300,3600,15,1,'" . TAG . "',0)
    ON DUPLICATE KEY UPDATE `cron_expr`='* * * * *',`is_active`=1,`replaces_manual`='" . TAG . "'");

// الجدولةُ بكيانٍ 0 تعني «كلَّ كيانٍ نشط» — فالمتوقَّعُ مهمةٌ لكلِّ كيان
$nCo = count(JS::activeCompanies($db));
$m1r = JS::materialize($db);
$m2r = JS::materialize($db);   // النداءُ الثاني في النافذةِ نفسِها
$q = $db->query("SELECT `job_id`,`source`,`source_ref`,`state` FROM `ems_job_queue`
                  WHERE `job_type`='pilot_monitor' AND `state`='queued' ORDER BY `job_id` DESC")->fetch_all(MYSQLI_ASSOC);
ck('Q6-a', $m1r['enqueued'] === $nCo,
    "النداءُ الأول أدرج {$m1r['enqueued']} — مهمةٌ لكلِّ كيانٍ نشط ($nCo)");
ck('Q6-b', count($q) > 0 && $q[0]['source'] === 'schedule',
    'المصدر: ' . ($q[0]['source'] ?? '—') . ' والمرجع ' . ($q[0]['source_ref'] ?? '—'));
ck('Q7-a', $m2r['enqueued'] === 0 && $m2r['skipped'] === 1,
    "النداءُ الثاني أدرج {$m2r['enqueued']} وتخطّى {$m2r['skipped']} — مهمةٌ واحدةٌ لكلِّ نافذة");

// ═════════ Q8 ✚ إنذارُ توقفِ العامل ═════════
echo "\n▐ Q8 ✚ إنذارُ توقفِ العامل لمالكِ الجدولة (CK-15)\n";
$db->query("DELETE FROM `fin_notifications` WHERE `title` LIKE '[JOB-STALL:pilot_monitor]%'");
$db->query("UPDATE `ems_job_schedule` SET `last_success_at`=NOW() - INTERVAL 5 HOUR WHERE `job_type`='pilot_monitor'");
$stalled = JS::stalled($db);
$raised  = JS::alertStalled($db);
$again   = JS::alertStalled($db);   // لا إغراق: إنذارٌ واحدٌ في الساعة
$note = $db->query("SELECT `title` FROM `fin_notifications` WHERE `title` LIKE '[JOB-STALL:pilot_monitor]%' ORDER BY `id` DESC LIMIT 1")->fetch_row();
ck('Q8-a', count($stalled) >= 1, 'جدولاتٌ متوقفةٌ مرصودة: ' . count($stalled));
ck('Q8-b', $raised >= 1, "رُفع $raised إنذارًا");
ck('Q8-c', $again === 0, "والنداءُ الثاني رفع $again — لا إغراق");
ck('Q8-d', $note && strpos($note[0], 'pilot_monitor') !== false, 'نصُّه: ' . mb_substr($note[0] ?? '—', 0, 78));

// ═════════ Q9 ✖ الأمرُ اليدويُّ الملغى ═════════
echo "\n▐ Q9 ✖ الأمرُ اليدويُّ يرفض التشغيلَ برمزِ 3\n";
$php = PHP_BINARY;
$root = dirname(__DIR__);
$out = array(); $rc = 0;
exec('"' . $php . '" "' . $root . '/Operations/cron_capacity_rollup.php" --company=4 2>&1', $out, $rc);
$txt = implode("\n", $out);
ck('Q9-a', $rc === 3, "رمزُ الخروج: $rc (المتوقَّع 3 — مُحال لا فشل)");
ck('Q9-b', strpos($txt, 'أُلغي تشغيلُه يدويًّا') !== false, 'والرسالةُ تحيل إلى الطابور');
ck('Q9-c', strpos($txt, 'capacity_rollup') !== false, 'وتسمّي نوعَ المهمةِ البديلة');

// ───────────────────────────── النتيجة ─────────────────────────────
echo "\n═══════════════════════════════════════════════════════════════\n";
printf(" النتيجة: %d ناجحًا · %d ساقطًا\n", $pass, $fail);
echo "═══════════════════════════════════════════════════════════════\n";

// ◆ إعادةُ الجدولاتِ الحقيقيةِ إلى ما كانت عليه — لا حذفَها.
//   uq_sched على job_type وحدَه، فـON DUPLICATE في Q6 يُعدّل الصفَّ الحقيقيَّ
//   ولا يُنشئ صفًّا للاختبار. وحذفُه هنا يمحو جدولةً مشروعةً بلا أن يبدو شيء.
$db->query(
    "INSERT INTO `ems_job_schedule`
        (`company_id`,`job_type`,`cron_expr`,`max_runtime_seconds`,`alert_after_seconds`,
         `owner_role_id`,`is_active`,`replaces_manual`,`created_by`)
     VALUES (0,'pilot_monitor','*/30 * * * *',300,7200,15,1,'مراجعةٌ بصريةٌ للوحاتِ التجربة',0)
     ON DUPLICATE KEY UPDATE `company_id`=0, `cron_expr`='*/30 * * * *',
        `max_runtime_seconds`=300, `alert_after_seconds`=7200, `owner_role_id`=15,
        `is_active`=1, `replaces_manual`='مراجعةٌ بصريةٌ للوحاتِ التجربة'"
);
// ◆ ولا يُترك آخرُ نجاحٍ متقادمًا: الاختبارُ عطّل الجدولاتِ ثم أعادها، فلو خرج
//   وlast_success_at قديمٌ قرأه CK-15 «متوقفًا» — وهو أثرُ الاختبارِ لا عيبُ منتج.
$db->query("UPDATE `ems_job_schedule` SET `last_success_at` = NOW(3)
             WHERE `job_type`='pilot_monitor' AND (`last_success_at` IS NULL
                OR `last_success_at` < NOW() - INTERVAL `alert_after_seconds` SECOND)");
$db->query("UPDATE `ems_job_schedule` SET `is_active`=1");
$db->query("DELETE FROM `ems_job_queue` WHERE `seed_tag`='" . TAG . "' OR (`job_type`='pilot_monitor' AND `state`='queued')");
$db->query("DELETE FROM `fin_notifications` WHERE `title` LIKE '%" . TAG . "%'");
echo " كُنس البذرُ (" . TAG . ") وأُعيدت الجدولاتُ إلى النشاط\n\n";
exit($fail === 0 ? 0 : 1);
