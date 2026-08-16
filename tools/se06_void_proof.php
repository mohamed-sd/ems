<?php
/**
 * se06_void_proof.php — إثباتُ فعلِ الإلغاءِ بحركةٍ عاكسة (shift.entry.void)
 * ═══════════════════════════════════════════════════════════════════════════
 * ① يُنشئ قيدًا ثم يُلغيه عبرَ الخدمة
 * ② يثبت أنَّ الأصلَ **لم يُحذف** وصار reversed ويشير إلى عاكسِه
 * ③ ويثبت أنَّ العاكسَ يحمل كميةً وساعاتٍ **سالبةً** بمرجعِ الأصل
 * ④ وأنَّ مجموعَ الأصلِ والعاكسِ صفرٌ — أثرٌ مُلغًى لا مطموس
 * ⑤ وأنَّ الخانةَ تحرّرت فيمرّ بديلٌ مشروع
 * ⑥ ورفضُ العكسِ مرتين · ورفضُه بلا سبب
 * ⑦ والعميلُ اشتُقَّ من المشروعِ لا أُدخل
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = 'C:/wamp64/www/ems';
require_once $ROOT . '/config.php';
require_once $ROOT . '/app/Services/Unit/TimesheetEntryService.php';
use App\Services\Unit\TimesheetEntryService as SVC;

$db = $conn; $db->set_charset('utf8mb4');
$TAG = 'se06-void';
$one = function (string $s) use ($db) { $r = $db->query($s); return $r ? $r->fetch_row()[0] : null; };
$fails = 0;
$ok = function (bool $c, string $m) use (&$fails) { echo ($c ? '  ✔ ' : '  ✘ ') . $m . "\n"; if (!$c) { $fails++; } };

$CO  = (int) $one("SELECT company_id FROM unit_entries ORDER BY id DESC LIMIT 1");
$EQ  = (int) $one("SELECT equipment_id FROM unit_entries WHERE equipment_id>0 ORDER BY id DESC LIMIT 1");
$USR = (int) $one("SELECT id FROM users WHERE role='6' AND company_id=$CO LIMIT 1");
$_SESSION = ['user' => ['id' => $USR, 'company_id' => $CO, 'role' => '6']];
$gate = ems_tenant_db();
$D = '2026-08-16';
$before = (int) $one("SELECT COUNT(*) FROM unit_entries");

echo "══ إثباتُ الإلغاءِ بحركةٍ عاكسة — كيان=$CO · آلية=$EQ ══\n\n";

/* ── ① إنشاءُ قيدٍ ─────────────────────────────────────────────── */
$in = ['company_id' => $CO, 'equipment_id' => $EQ, 'date' => $D, 'shift' => 'night',
       'qty' => 7.5, 'unit_type' => 'hour', 'source_ref' => 'SE06', 'seed_tag' => $TAG,
       'note' => $TAG . ' أصل',
       'time_lines' => [['ops_state' => 'actual_work', 'hours' => 7.5, 'resp_party' => 'company', 'cause_note' => '']]];
$r = SVC::submit($db, $gate, $in, $USR);
$id = (int) ($r['entry']['id'] ?? 0);
$ok(!empty($r['ok']) && $id > 0, "أُنشئ القيدُ #$id");

/* ── ⑦ العميلُ مشتق ──────────────────────────────────────────── */
$row = $db->query("SELECT client_id, project_id, qty, state FROM unit_entries WHERE id=$id")->fetch_assoc();
$projClient = $one("SELECT client_id FROM project WHERE id=" . (int) $row['project_id']);
$ok((string) $row['client_id'] === (string) $projClient && $row['client_id'] !== null,
    'العميلُ اشتُقَّ من المشروع: client_id=' . var_export($row['client_id'], true) . ' (مشروع#' . $row['project_id'] . ')');

/* ── ⑥-أ رفضُ العكسِ بلا سبب ─────────────────────────────────── */
$v0 = SVC::voidEntry($db, $gate, $CO, $id, '   ', $USR);
$ok(empty($v0['ok']) && (int) $v0['code'] === 422, 'رُفض العكسُ بلا سبب (422)');

/* ── ② العكسُ الفعليّ ────────────────────────────────────────── */
$v = SVC::voidEntry($db, $gate, $CO, $id, 'قيدٌ مكرَّرٌ أُدخل خطأً', $USR);
$vid = (int) ($v['void_entry_id'] ?? 0);
$ok(!empty($v['ok']) && $vid > 0, "أُنشئ الصفُّ العاكسُ #$vid");

$orig = $db->query("SELECT state, superseded_by_id, qty FROM unit_entries WHERE id=$id")->fetch_assoc();
$ok($orig !== null, 'الأصلُ ما زال موجودًا — لم يُحذف');
$ok((string) $orig['state'] === 'reversed', "الأصلُ صار reversed (كان {$row['state']})");
$ok((int) $orig['superseded_by_id'] === $vid, 'الأصلُ يشير إلى عاكسِه superseded_by_id=' . $orig['superseded_by_id']);

$rev = $db->query("SELECT entry_no, revises_entry_id, revision_kind, state, qty FROM unit_entries WHERE id=$vid")->fetch_assoc();
$ok((int) $rev['revises_entry_id'] === $id, 'والعاكسُ يشير إلى أصلِه revises_entry_id=' . $rev['revises_entry_id']);
$ok((string) $rev['revision_kind'] === 'reversal', "نوعُ المراجعة = {$rev['revision_kind']}");
$ok((float) $rev['qty'] === -7.5, "كميةُ العاكسِ سالبة = {$rev['qty']}");
$ok(strpos((string) $rev['entry_no'], 'REV-') === 0, "رقمُه {$rev['entry_no']}");

/* ── ③④ الساعاتُ العاكسةُ والمجموعُ صفر ──────────────────────── */
$hOrig = (float) $one("SELECT COALESCE(SUM(hours),0) FROM unit_time_log WHERE entry_id=$id");
$hRev  = (float) $one("SELECT COALESCE(SUM(hours),0) FROM unit_time_log WHERE entry_id=$vid");
$ok($hRev === -7.5, "ساعاتُ العاكسِ سالبة = $hRev");
$ok(($hOrig + $hRev) === 0.0, "مجموعُ الأصلِ والعاكسِ = " . ($hOrig + $hRev) . " ساعة — أثرٌ مُلغًى لا مطموس");

/* ── ⑤ الخانةُ تحرّرت ───────────────────────────────────────── */
$k1 = $one("SELECT shift_slot_key FROM unit_entries WHERE id=$id");
$k2 = $one("SELECT shift_slot_key FROM unit_entries WHERE id=$vid");
$ok($k1 === null && $k2 === null, 'مفتاحا القفلِ فارغانِ — الخانةُ متاحة');
$in2 = $in; $in2['note'] = $TAG . ' بديل'; $in2['qty'] = 8.0;
$r2 = SVC::submit($db, $gate, $in2, $USR);
$ok(!empty($r2['ok']) && empty($r2['existing']), 'مرّ قيدٌ بديلٌ لنفسِ (الآلية × التاريخ × الوردية)');

/* ── ⑥-ب رفضُ العكسِ مرتين ──────────────────────────────────── */
$v2 = SVC::voidEntry($db, $gate, $CO, $id, 'محاولةٌ ثانية', $USR);
$ok(empty($v2['ok']) && (int) $v2['code'] === 409, 'رُفض عكسُ ما عُكس (409)');

/* ── التنظيف ────────────────────────────────────────────────── */
echo "\n── التنظيف ──\n";
$db->query("DELETE FROM unit_time_log WHERE entry_id IN (SELECT id FROM unit_entries WHERE seed_tag='$TAG' OR note LIKE '$TAG%' OR note LIKE 'عكسُ القيد%')");
$db->query("DELETE FROM unit_entries WHERE seed_tag='$TAG' OR note LIKE '$TAG%' OR note LIKE 'عكسُ القيد #$id%'");
$after = (int) $one("SELECT COUNT(*) FROM unit_entries");
$ok($after === $before, "unit_entries عادت إلى " . number_format($before) . " (وجد " . number_format($after) . ")");

echo "\n" . ($fails === 0 ? "✔ الإلغاءُ حركةٌ عاكسةٌ موصولةٌ بالاتجاهين — صفرُ إخفاق\n" : "✘ إخفاقات: $fails\n");
exit($fails === 0 ? 0 : 1);
