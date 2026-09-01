<?php
/**
 * 2028_03_09_govui_universe_follows_wiring.php — جسرُ القياسِ يتبع حكمَ الوصل
 * ═══════════════════════════════════════════════════════════════════════════
 * @migration-objects: update:repair01_target_universe(screen_id,verdict,witness) + log:govui_wiring_log
 *
 * ◆ **العطبُ الذي يعالجه — أحدثتُه أنا في `2028_03_03`**: أُعيد وصلُ سبعةِ
 *   أهدافٍ إلى أسطحِها الصحيحة، **وبقي `repair01_target_universe` على الوصلِ
 *   القديم** — وهو الجسرُ الذي يقيس عليه `rpr02_field_measure`. فصار زوجانِ
 *   يقيسان **أثرَ الشاشةِ الخطأ** ويقرآن `0/10` و`0/14` وهما ليسا فارغَين:
 *   `MNT-11` تُقاس على `external_repairs` وسطحُها `part_requests`، و`MNT-13`
 *   تُقاس على `daily_care` وسطحُها `preventive_plans`.
 *   ⇒ **حكمٌ صُحِّح في طبقةٍ ولم يتبعه قارئُه في طبقةٍ أخرى** — والرقمُ يُقرأ
 *   صحيحًا وهو خطأ [[data-mismatch-campaign]].
 *
 * ◆ **وثلاثةُ أحكامِ `MERGED_INTO` سقطت مقدّمتُها**: شواهدُها تقول حرفًا
 *   *«لا شاشةَ مستقلّةً باسمِه»* — وهذه الجولةُ **قاست العكس**: لكلٍّ منها سطحٌ
 *   مبنيٌّ مستقلٌّ على القرصِ (`tickets_list` بقيدِ دمجِ `dept_inbox` المسجَّلِ ·
 *   `ticket_sla_config` · `ticket_contextual_open`). فالحكمُ يُعاد بشاهدِه
 *   الجديدِ إلى `MATCHED` — ⛔ **ولا يُلغى الشاهدُ القديمُ بل يُذكَر ويُردّ**.
 *
 * ◆ **وأثرُ ذلك على المقام مُعلَنٌ سلفًا**: ثلاثةُ أسطحٍ تدخل مقامَ الحقول،
 *   **فالنسبةُ قد تهبط والعملُ صاعد** — وهو فخُّ المقام المنصوصُ في §19.
 *
 * ◆ **والمعرِّفُ الجديدُ يُقرأ من `nav_placements` وقتَ التشغيل** لا يُكتب
 *   يدويًّا — فلو تغيّر الوصلُ ثانيةً تبعه الجسرُ ولم يتفرّق عنه.
 *
 * التشغيل: php database/migrations/2028_03_09_govui_universe_follows_wiring.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);

$conn->query("CREATE TABLE IF NOT EXISTS `govui_universe_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `target_uid` varchar(12) NOT NULL,
  `requirement_id` varchar(48) NOT NULL,
  `old_screen_id` varchar(12) NOT NULL DEFAULT '',
  `new_screen_id` varchar(12) NOT NULL DEFAULT '',
  `old_verdict` varchar(24) NOT NULL DEFAULT '',
  `new_verdict` varchar(24) NOT NULL DEFAULT '',
  `old_witness` varchar(400) NOT NULL DEFAULT '',
  `witness` varchar(400) NOT NULL,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_gul_t` (`target_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='GOV_UI_EXEC: قيدُ رجوعِ جسرِ القياسِ بعدَ حكمِ الوصل'");

/* متطلَّبٌ ⇒ هدفُ الوصلِ الذي يخدمه — والمعرِّفُ يُقرأ من الموضعِ لا يُكتب */
$MAP = array(
    'TKT-02' => array('TGT-0344', 'NT-DEP-10-002', 'MATCHED',
        'أُعيد وصلُه في 2028_03_03 إلى Tickets/tickets_list.php بقيدِ nav_canonical: «dept_inbox يُدمج في tickets_list تبويبًا موجَّهة لإدارتي». والشاهدُ القديمُ «لا شاشةَ مستقلّةً باسمِه» سقطت مقدّمتُه'),
    'TKT-03' => array('TGT-0345', 'NT-DEP-10-003', 'MATCHED',
        'أُعيد وصلُه إلى Tickets/ticket_sla_config.php — وغرضُ بطاقتِه «مصفوفة SLA: زمن الاستجابة وزمن الحل وسلّم التصعيد» موضوعُ الشاشةِ حرفًا'),
    'TKT-04' => array('TGT-0346', 'NT-DEP-10-004', 'MATCHED',
        'أُعيد وصلُه إلى Tickets/ticket_contextual_open.php — واسمُ السطحِ المخزَّنُ «أبلغ عن مشكلة من هذه الشاشة»'),
    'MNT-10' => array('TGT-0204', 'NT-DEP-14-010', '',
        'أُعيد وصلُه إلى Maintenance/work_orders.php — «بنود أمر العمل» ابنُ أمرِ العملِ لا ابنُ طلبِ القطع'),
    'MNT-11' => array('TGT-0205', 'NT-DEP-14-011', '',
        'أُعيد وصلُه إلى Maintenance/part_requests.php — وسجلُّ الشاشاتِ يسمّيه «طلبات صرف القطع»'),
    'MNT-13' => array('TGT-0207', 'NT-DEP-14-013', '',
        'أُعيد وصلُه إلى Maintenance/preventive_plans.php — وسجلُّ الشاشاتِ يسمّيه «خطط الصيانة الوقائية»'),
    'SUP-18' => array('TGT-0323', 'NT-DEP-02-018', '',
        'أُعيد وصلُه إلى Suppliers/shares_coverage.php — وسجلُّ الشاشاتِ يسمّيه «سجل الحصص والتغطية التعاقدية»'),
);

$log = $conn->prepare("INSERT INTO govui_universe_log
    (target_uid, requirement_id, old_screen_id, new_screen_id, old_verdict, new_verdict, old_witness, witness)
    VALUES (?,?,?,?,?,?,?,?)");
if (!$log) { exit("⛔ prepare: {$conn->error}\n"); }
$n = 0; $skip = 0;
foreach ($MAP as $req => $m) {
    list($uid, $tid, $newVerdict, $why) = $m;
    $st = $conn->prepare("SELECT screen_id FROM nav_placements WHERE target_id = ?");
    $st->bind_param('s', $tid); $st->execute();
    $pl = $st->get_result()->fetch_assoc(); $st->close();
    if (!$pl || (string) $pl['screen_id'] === '') { echo "  ⚠ {$req}: لا معرِّفَ شاشةٍ في موضعِ {$tid}\n"; $skip++; continue; }
    $newSid = (string) $pl['screen_id'];

    $st = $conn->prepare("SELECT screen_id, verdict, verdict_witness FROM repair01_target_universe WHERE target_uid = ?");
    $st->bind_param('s', $uid); $st->execute();
    $cur = $st->get_result()->fetch_assoc(); $st->close();
    if (!$cur) { echo "  ⚠ {$req}: لا صفَّ {$uid} في كونِ الأهداف\n"; $skip++; continue; }
    $verdict = $newVerdict !== '' ? $newVerdict : (string) $cur['verdict'];
    $wit = 'GOV_UI_EXEC §9 — ' . $why . ' · والشاهدُ السابقُ: ' . mb_substr((string) $cur['verdict_witness'], 0, 150);
    $wit = mb_substr($wit, 0, 395);

    $st = $conn->prepare("UPDATE repair01_target_universe
                             SET screen_id = ?, verdict = ?, verdict_witness = ?, verdict_at = NOW()
                           WHERE target_uid = ?");
    $st->bind_param('ssss', $newSid, $verdict, $wit, $uid);
    if (!$st->execute()) { exit("⛔ {$uid}: {$conn->error}\n"); }
    $st->close();
    $log->bind_param('ssssssss', $uid, $req, $cur['screen_id'], $newSid,
        $cur['verdict'], $verdict, $cur['verdict_witness'], $wit);
    $log->execute();
    printf("  ✔ %-8s %s : %s ⇒ %s · حكم %s ⇒ %s\n", $req, $uid, $cur['screen_id'], $newSid, $cur['verdict'], $verdict);
    $n++;
}
echo "المصحَّح: {$n} صفًّا في كونِ الأهداف · المتخطّى {$skip}\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
