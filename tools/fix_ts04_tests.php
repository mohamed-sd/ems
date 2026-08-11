<?php
/**
 * tools/fix_ts04_tests.php — TS-04 · TS-05 · TS-16: أتُميّز الأحكامُ فعلًا؟
 * ═══════════════════════════════════════════════════════════════════════════
 * المرجع: `docs/specs/SPEC_TIMESHEET_CYCLE_ar.md`.
 *
 * ◆ ثلاثةُ أحكامٍ من مواصفةِ المالك، ولكلٍّ **جسٌّ في الاتجاهين**: يمنع المخالفَ
 *   ولا يمنع السليم. وفاحصٌ يمنع الجميعَ عاجزٌ كفاحصٍ لا يمنع أحدًا.
 *
 * ◆ ويُختبر ما تفرضه القاعدةُ **و**ما يفرضه الحارس — فلا يُفترض أن أحدَهما يكفي.
 *
 * التشغيل: php tools/fix_ts04_tests.php
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__) . '/includes/client_match_guard.php';

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; if ($c) { $PASS++; echo "  ✔ {$m}\n"; } else { $FAIL++; echo "  ✘ FAIL: {$m}\n"; } }
function head($m) { echo "\n── {$m}\n"; }

/* مدخلٌ مِسباريٌّ خاصٌّ — لا يُلمس صفٌّ من البذرة */
$CO = 4;
$conn->query("DELETE FROM unit_match_overrides WHERE reason LIKE 'PROBE-TS04%'");
$conn->query("DELETE FROM unit_entries WHERE entry_no LIKE 'PROBE-TS04%'");
$ref = $conn->query('SELECT project_id, equipment_id FROM unit_entries LIMIT 1')->fetch_assoc();
$conn->query("INSERT INTO unit_entries (company_id, entry_no, entry_date, project_id, equipment_id,
              unit_type, qty, state) VALUES ({$CO}, 'PROBE-TS04-1', CURDATE(), {$ref['project_id']},
              {$ref['equipment_id']}, 'hour', 8, 'parties_approved')");
$EID = (int) $conn->insert_id;
register_shutdown_function(static function () use ($conn) {
    $conn->query("DELETE FROM unit_match_overrides WHERE entry_id IN
                    (SELECT id FROM unit_entries WHERE entry_no LIKE 'PROBE-TS04%')");
    $conn->query("DELETE FROM unit_entries WHERE entry_no LIKE 'PROBE-TS04%'");
});

echo "══════════════════════════════════════════════════════════════════════\n";
echo " TS-04 · TS-05 · TS-16 — " . date('Y-m-d H:i') . "\n";
echo "══════════════════════════════════════════════════════════════════════\n";
check($EID > 0, 'أُنشئ مدخلٌ مِسباريٌّ #' . $EID);

/* ══ ① TS-04: الحالاتُ الأربعُ موجودةٌ ولا خامسةَ مخترَعة ═══════════════ */
head('① TS-04: أربعُ حالاتِ مطابقةٍ لا أقلَّ ولا أكثر');
$rs = $conn->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
                     AND TABLE_NAME='unit_entries' AND COLUMN_NAME='client_match_state'");
$t = $rs ? (string) $rs->fetch_row()[0] : '';
foreach (array('matched', 'mismatched', 'client_data_unavailable', 'client_response_overdue') as $s) {
    check(strpos($t, "'" . $s . "'") !== false, "الحالةُ `{$s}` مُعرَّفة");
}
check(strpos($t, "'pending'") !== false, 'وحالٌ ابتدائيٌّ `pending` — «قبلَ الحكم» لا خامسةٌ فيه');

/* ══ ② القاعدةُ تفرض السند ═══════════════════════════════════════════ */
head('② القاعدة: مطابقةٌ محسومةٌ بلا وقتٍ ولا يدٍ تُرفض');
$bad = $conn->query("UPDATE unit_entries SET client_match_state='mismatched',
                      client_match_at=NULL, client_match_by=NULL WHERE id={$EID}");
check($bad === false, 'رُفضت — ' . mb_substr($conn->error, 0, 44));
$ok = $conn->query("UPDATE unit_entries SET client_match_state='mismatched',
                     client_match_at=NOW(), client_match_by=7, client_match_ref='MATCH-DOC-1' WHERE id={$EID}");
check($ok !== false, 'وبسندِها قُبلت — القيدُ لا يمنع الصحيح');

/* ══ ③ TS-05-أ: لا اعتمادَ مبيعاتٍ بلا مطابقةٍ أو تجاوزٍ مسجَّل ══════════ */
head('③ TS-05-أ: بوابةُ المبيعاتِ قرارٌ مسجَّلٌ لا مرورٌ صامت');
$d = ems_client_match_can_sales_approve($conn, $EID);
check($d !== null && $d['code'] === 403, 'غيرُ مطابقٍ وبلا تجاوز ⇒ منعٌ 403 — ' . mb_substr((string) ($d['reason'] ?? ''), 0, 52));

/* ══ ④ TS-05: قرارُ التجاوزِ بحقولِه السبعة ═══════════════════════════ */
head('④ TS-05-ب: سبعةُ حقولٍ إلزامية');
$can = ems_client_match_can_override($conn, $EID);
check($can === null, 'ويجوز تسجيلُ قرارِ تجاوزٍ (المطابقةُ محسومةٌ غيرُ مكتملة)');
$ins = $conn->query("INSERT INTO unit_match_overrides
    (company_id, entry_id, reason, evidence_ref, decided_by, scope_note, allows, match_state_at_decision)
    VALUES ({$CO}, {$EID}, 'PROBE-TS04 لا مشرفَ للعميلِ في هذا الموقع', 'DOC-9', 12,
            'وحداتُ الفترةِ 2026-06', 'primary_only', 'mismatched')");
check($ins !== false, 'سُجِّل قرارُ تجاوزٍ مستوفٍ');
$d = ems_client_match_can_sales_approve($conn, $EID);
check($d === null, 'وبعده يجوز اعتمادُ المبيعات');
$d = ems_client_match_can_sales_approve($conn, $EID, true);
check($d !== null && $d['code'] === 403,
    'لكنه **لا يبلغ الفوترة** — القرارُ يسمح بالأثرِ الأوليِّ فقط (TS-05-ب ⑥)');

/* ══ ⑤ TS-05-ج: لا تجاوزَ فوقَ رفضٍ صريح ═════════════════════════════ */
head('⑤ TS-05-ج: الرفضُ الصريحُ ليس غيابَ ردّ');
$conn->query("UPDATE unit_entries SET client_decision='disputed', dispute_ref='DISP-1' WHERE id={$EID}");
$can = ems_client_match_can_override($conn, $EID);
check($can !== null && $can['code'] === 403,
    'تجاوزٌ فوقَ رفضٍ صريحٍ **ممنوع** — ' . mb_substr((string) ($can['reason'] ?? ''), 0, 46));
$d = ems_client_match_can_sales_approve($conn, $EID);
check($d !== null && $d['code'] === 403, 'والمتنازعُ عليه لا يتقدم إلى أثرٍ نهائيّ (TS-16)');

/* ══ ⑥ TS-16: نزاعٌ بلا مرجعِ ملفٍّ يُرفض ═══════════════════════════════ */
head('⑥ TS-16: لا نزاعَ بلا ملفِّ اختلاف');
$bad = $conn->query("UPDATE unit_entries SET client_decision='disputed', dispute_ref=NULL WHERE id={$EID}");
check($bad === false, 'رُفض — ' . mb_substr($conn->error, 0, 44));

/* ══ ⑦ القبولُ الجزئي: القرارُ لكلِّ مدخلٍ على حدة ═══════════════════════ */
head('⑦ TS-10 · TS-16-ج: القبولُ الجزئيُّ لا يُوقف المقبول');
/* ◆ گوتشا كشفها الاختبارُ نفسُه: قاعدةُ **ق-18** تمنع تكرارَ
     (معدة × تاريخ × وردية) بنيويًّا. فمِسبارٌ ثانٍ بالمعدةِ والتاريخِ نفسِهما
     يُرفض — **وهو حارسٌ صحيحٌ يعمل**، والعيبُ كان في افتراضِ الاختبارِ لا فيه.
     فيُستعمل يومٌ آخرُ ووردياتٌ مختلفة. */
$ok2 = $conn->query("INSERT INTO unit_entries (company_id, entry_no, entry_date, project_id, equipment_id,
              unit_type, qty, state, shift, client_match_state, client_match_at, client_match_by, client_decision)
              VALUES ({$CO}, 'PROBE-TS04-2', DATE_ADD(CURDATE(), INTERVAL 1 DAY), {$ref['project_id']},
              {$ref['equipment_id']}, 'hour', 6, 'parties_approved', 'night', 'matched', NOW(), 7, 'accepted')");
$EID2 = (int) $conn->insert_id;
check($EID2 > 0, 'أُنشئ مدخلٌ ثانٍ مقبولٌ من العميل'
    . ($ok2 === false ? ' — خطأ: ' . mb_substr($conn->error, 0, 90) : ''));
$d2 = ems_client_match_can_sales_approve($conn, $EID2);
check($d2 === null, 'والمقبولُ **يمضي** رغم أن أخاه متنازعٌ عليه — فلا عكسَ تلقائيٌّ للكل');
$d1 = ems_client_match_can_sales_approve($conn, $EID);
check($d1 !== null, 'والمتنازعُ عليه **وحدَه** يقف');

/* ══ ⑧ لا آلةَ حالاتٍ ثانية ═══════════════════════════════════════════ */
head('⑧ المطابقةُ عمودٌ متعامدٌ لا حالٌ في آلةِ الدورة');
$rs = $conn->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
                     AND TABLE_NAME='unit_entries' AND COLUMN_NAME='state'");
$stateT = $rs ? (string) $rs->fetch_row()[0] : '';
check(strpos($stateT, 'matched') === false && strpos($stateT, 'disputed') === false,
    'حالُ الدورةِ لم تُحشَ بحالاتِ المطابقةِ — فلا تعدادَ بمعنيين');

echo "\n" . str_repeat('═', 70) . "\n";
printf("ناجحٌ: %d · فاشلٌ: %d\n", $PASS, $FAIL);
echo "◆ الأحكامُ الثلاثةُ تميّز: تمنع المخالفَ ولا تمنع السليم.\n";
echo str_repeat('═', 70) . "\n";
exit($FAIL === 0 ? 0 : 1);
