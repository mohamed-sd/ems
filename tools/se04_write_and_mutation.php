<?php
/**
 * se04_write_and_mutation.php — إثباتُ المسارِ الكاتبِ واختبارُ الطفرة
 * ═══════════════════════════════════════════════════════════════════════════
 * «الفحصُ الأخضرُ على صفرِ صفٍّ ليس دليلًا». فهذا المسبارُ:
 *   ① يكتب قيدًا حقيقيًّا **عبر الخدمة** (لا SQL خام) بوسمِ بذرٍ
 *   ② يثبت وصولَه إلى unit_entries و unit_time_log معًا
 *   ③ يثبت أن التسعةَ الجديدةَ **حُفظت فعلًا** لا ابتُلعت صامتةً
 *   ④ يثبت العطالة: النداءُ الثاني بالمفتاحِ نفسِه لا ينشئ قيدًا ثانيًا
 *   ⑤ **طفرة**: يُدخل عيبًا عمدًا (قيدٌ بلا مشغّل) ويثبت أن CK-04 يرسب
 *   ⑥ ينظّف كلَّ ما كتبه بالوسمِ ويثبت عودةَ العدِّ إلى ما كان
 * ◆ كلُّ صفٍّ يكتبه موسومٌ seed_tag='se04-probe' — ولا يُترك أثرٌ.
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = 'C:/wamp64/www/ems';
require_once $ROOT . '/config.php';
require_once $ROOT . '/app/Services/Unit/TimesheetEntryService.php';

/* بوابةُ العزلِ تُبنى من الجلسة (TenantContext::fromSession) وهي fail-closed.
   وفي CLI لا جلسةَ — فتُهيَّأ هويةٌ صريحةٌ قبلَ أولِ نداءٍ لـems_tenant_db()،
   وإلا رُفض كلُّ استعلامٍ بـ«no tenant in context». (درس TenantDb-CLI) */
$__co = null; $__usr = null;
{
    $r = $conn->query("SELECT company_id FROM unit_entries ORDER BY id DESC LIMIT 1");
    $__co = $r ? (int) $r->fetch_row()[0] : 0;
    $r = $conn->query("SELECT id FROM users WHERE role='6' AND company_id=" . (int) $__co . " LIMIT 1");
    $__usr = $r ? (int) ($r->fetch_row()[0] ?? 0) : 0;
}
$_SESSION = ['user' => ['id' => $__usr, 'company_id' => $__co, 'role' => '6', 'name' => 'مسبار se04']];

$TAG = 'se04-probe';
$db  = $conn;
$db->set_charset('utf8mb4');

$one = function (string $sql) use ($db) { $r = $db->query($sql); return $r ? $r->fetch_row()[0] : null; };
$fails = 0;
$okline = function (bool $ok, string $msg) use (&$fails) {
    echo ($ok ? '  ✔ ' : '  ✘ ') . $msg . "\n";
    if (!$ok) { $fails++; }
};

/* سياقٌ حقيقيٌّ من القاعدة */
$CO  = (int) $one("SELECT company_id FROM unit_entries ORDER BY id DESC LIMIT 1");
$EQ  = (int) $one("SELECT equipment_id FROM unit_entries WHERE equipment_id>0 ORDER BY id DESC LIMIT 1");
$OP  = (int) $one("SELECT operator_employee_id FROM unit_entries WHERE operator_employee_id>0 ORDER BY id DESC LIMIT 1");
$USER = (int) $one("SELECT id FROM users WHERE role='6' LIMIT 1");
$DATE = date('Y-m-d');
echo "══ السياق: كيان=$CO · آلية=$EQ · مشغّل=$OP · مستخدم=$USER · تاريخ=$DATE ══\n\n";

$before = (int) $one("SELECT COUNT(*) FROM unit_entries");
$beforeLog = (int) $one("SELECT COUNT(*) FROM unit_time_log");

/* ═══ ① الكتابةُ عبر الخدمة ═══════════════════════════════════════ */
echo "── ① كتابةُ قيدٍ حقيقيٍّ عبر TimesheetEntryService ──\n";
$gate = ems_tenant_db();
$input = [
    'company_id'   => $CO,
    'equipment_id' => $EQ,
    'operator_employee_id' => $OP,
    'date'         => $DATE,
    'shift'        => 'day',
    'qty'          => 9.5,
    'unit_type'    => 'hour',
    'source_ref'   => 'SE04-PROBE-' . $DATE,
    'note'         => 'قيدُ اختبارٍ آليٍّ — se04',
    'seed_tag'     => $TAG,
    'time_lines'   => [
        ['ops_state' => 'actual_work',    'hours' => 9.5, 'resp_party' => 'company', 'cause_note' => ''],
        ['ops_state' => 'tech_breakdown', 'hours' => 1.5, 'resp_party' => 'supplier', 'cause_note' => 'عطلٌ هيدروليكي'],
        ['ops_state' => 'standby',        'hours' => 2.0, 'resp_party' => 'client',   'cause_note' => 'انتظارُ جبهةِ عمل'],
    ],
    'meter_before'      => 1000.50,
    'meter_after'       => 1010.00,
    'fuel_received_qty' => 200.00,
    'fuel_issued_qty'   => 175.25,
    'container_key'     => 'SE04-CK-001',
    'created_by_role'   => 6,
];
$res = \App\Services\Unit\TimesheetEntryService::submit($db, $gate, $input, $USER);
$okline(!empty($res['ok']), 'الخدمةُ أعادت ok — ' . json_encode(
    array_intersect_key($res, array_flip(['ok', 'code', 'existing', 'missing', 'reasons'])), JSON_UNESCAPED_UNICODE));
$entryId = (int) ($res['entry']['id'] ?? 0);
if (!$entryId) {
    $r = $db->query("SELECT id FROM unit_entries WHERE seed_tag='$TAG' ORDER BY id DESC LIMIT 1");
    $entryId = $r ? (int) ($r->fetch_row()[0] ?? 0) : 0;
}
$okline($entryId > 0, "القيدُ أُنشئ برقم #$entryId");

/* ═══ ② وصولُه إلى الجدولين ═══════════════════════════════════════ */
echo "\n── ② أوصلَ إلى الجدولين؟ ──\n";
$afterE = (int) $one("SELECT COUNT(*) FROM unit_entries");
$afterL = (int) $one("SELECT COUNT(*) FROM unit_time_log WHERE entry_id=$entryId");
$okline($afterE === $before + 1, "unit_entries: $before ⇐ $afterE (+1)");
$okline($afterL === 3, "unit_time_log: $afterL سطرِ ساعاتٍ للقيد (المتوقَّع 3)");
$sumH = $one("SELECT ROUND(SUM(hours),2) FROM unit_time_log WHERE entry_id=$entryId");
$okline((float) $sumH === 13.0, "مجموعُ الساعات = $sumH (المتوقَّع 13.00)");
$r = $db->query("SELECT ops_state, hours, resp_party FROM unit_time_log WHERE entry_id=$entryId ORDER BY id");
while ($x = $r->fetch_row()) { printf("      · %-16s %5.2f ساعة · %s\n", $x[0], $x[1], $x[2]); }

/* ═══ ③ أحُفظت التسعةُ الجديدة؟ ═══════════════════════════════════ */
echo "\n── ③ التسعةُ الجديدةُ: أحُفظت أم ابتُلعت صامتةً؟ ──\n";
$r = $db->query("SELECT meter_before, meter_after, fuel_received_qty, fuel_issued_qty,
                        container_key, created_by_role, seed_tag, entity_layer, client_id
                 FROM unit_entries WHERE id=$entryId");
$row = $r ? $r->fetch_assoc() : [];
$expect = ['meter_before' => '1000.50', 'meter_after' => '1010.00', 'fuel_received_qty' => '200.00',
           'fuel_issued_qty' => '175.25', 'container_key' => 'SE04-CK-001', 'created_by_role' => '6',
           'seed_tag' => $TAG, 'entity_layer' => 'operations'];
foreach ($expect as $k => $v) {
    $got = (string) ($row[$k] ?? '');
    $okline($got === $v, sprintf('%-18s = %-14s (المتوقَّع %s)', $k, $got === '' ? 'فارغ' : $got, $v));
}
echo '      · client_id = ' . ($row['client_id'] === null ? 'NULL — لم يُمرَّر (مشتقٌّ لاحقًا)' : $row['client_id']) . "\n";

/* ═══ ④ العطالة ══════════════════════════════════════════════════ */
echo "\n── ④ العطالة: النداءُ الثاني بالمفتاحِ نفسِه ──\n";
$res2 = \App\Services\Unit\TimesheetEntryService::submit($db, $gate, $input, $USER);
$afterE2 = (int) $one("SELECT COUNT(*) FROM unit_entries");
$okline(!empty($res2['existing']), 'الخدمةُ أعلنت existing=true ولم تُنشئ قيدًا ثانيًا');
$okline($afterE2 === $afterE, "عددُ القيودِ ثابتٌ عند $afterE2");

/* ═══ ⑤ الطفرة: عيبٌ مُعادٌ عمدًا — أيرسب CK-04؟ ═══════════════════ */
echo "\n── ⑤ اختبارُ الطفرة: قيدٌ بلا مشغّلٍ — أيمسكه CK-04؟ ──\n";
$ck04 = "SELECT COUNT(*) FROM unit_entries
          WHERE entry_date >= '$DATE'
            AND (equipment_id IS NULL OR equipment_id = 0
                 OR operator_employee_id IS NULL OR operator_employee_id = 0)";
$pre = (int) $one($ck04);
$okline($pre === 0, "CK-04 قبلَ الطفرة = $pre (أخضر)");
/* الطفرةُ تُحقن مباشرةً — فالخدمةُ تمنعها، والمقصودُ اختبارُ الفاحصِ لا الخدمة */
$db->query("UPDATE unit_entries SET operator_employee_id=0 WHERE id=$entryId");
$post = (int) $one($ck04);
$okline($post === 1, "CK-04 بعدَ الطفرة = $post (أحمر — الفاحصُ يمسك العيب)");
$db->query("UPDATE unit_entries SET operator_employee_id=$OP WHERE id=$entryId");
$restored = (int) $one($ck04);
$okline($restored === 0, "CK-04 بعدَ ردِّ العيب = $restored (أخضرُ ثانيةً)");

/* ═══ ⑥ التنظيفُ بالوسم ══════════════════════════════════════════ */
echo "\n── ⑥ التنظيفُ بالوسمِ لا بالمعرِّف ──\n";
$db->query("DELETE FROM unit_time_log WHERE entry_id IN (SELECT id FROM unit_entries WHERE seed_tag='$TAG')");
$delLog = $db->affected_rows;
$db->query("DELETE FROM unit_entries WHERE seed_tag='$TAG'");
$delEnt = $db->affected_rows;
echo "      · حُذف $delEnt قيدًا و$delLog سطرَ ساعات\n";
$finalE = (int) $one("SELECT COUNT(*) FROM unit_entries");
$finalL = (int) $one("SELECT COUNT(*) FROM unit_time_log");
$okline($finalE === $before, "unit_entries عادت إلى $before");
$okline($finalL === $beforeLog, "unit_time_log عادت إلى $beforeLog");
$left = (int) $one("SELECT COUNT(*) FROM unit_entries WHERE seed_tag='$TAG'");
$okline($left === 0, "صفرُ بقيةٍ موسومةٍ بـ$TAG");

echo "\n" . ($fails === 0 ? "✔ المسارُ الكاتبُ مثبَتٌ والفاحصُ يرسب عند العيب — صفرُ إخفاق\n"
                          : "✘ إخفاقات: $fails\n");
exit($fails === 0 ? 0 : 1);
