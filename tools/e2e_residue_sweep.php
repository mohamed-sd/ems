<?php
/**
 * tools/e2e_residue_sweep.php — كنسُ بقايا جولاتِ `unit_chain_e2e_proof` الميتة
 * ═══════════════════════════════════════════════════════════════════════════
 * **لِمَ وُجدت البقايا؟** كنسُ الفاحصِ يحذف بالمعرّف ولا يفحص مُرجَعَ الحذف، وشاهدُ
 * نظافتِه ثمانيةُ عدّاداتٍ **ماليةٍ وحدَها** — لا واحدَ منها يعدُّ العميلَ أو
 * الموردَ أو المشروعَ أو المعدةَ أو المشغّل. فجولةٌ ماتت في منتصفِها تركت عالَمَها
 * كلَّه، **وأخطرُه عميلٌ بـ`client_code=''`** حجزَ المفتاحَ الفريدَ
 * `uq_clients_company_code (4,'')` فمنعَ **كلَّ جولةٍ بعده** — فيُقرأ عطلٌ في
 * المنتجِ والمنتجُ سليم. (أُصلح الاثنان في الفاحص: الرمزُ يُوسَم، والشاهدُ يقيس
 * التسريبَ بوسمِ الجولة.)
 *
 * وهذه الأداةُ تكنس **ما بقي** بترتيبِ التبعيةِ نفسِه الذي يستعمله الفاحص، ولا
 * تمسُّ صفًّا إلا بعد إثباتِ أنه موسومٌ بوسمِ جولةٍ (`E2E<pid>`)، وتنتهي بشاهدٍ
 * يعدُّ الصفرَ في كلِّ جدول.
 *
 * التشغيل: php tools/e2e_residue_sweep.php [--apply]
 *          (بلا --apply يقيس ويُعلن ولا يمسّ)
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__) . '/includes/env.php';

$APPLY = in_array('--apply', $argv, true);
$db = @new mysqli(ems_env('DB_HOST'), 'root', '', ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

$one = function ($sql) use ($db) { $r = $db->query($sql); return $r ? (int) $r->fetch_row()[0] : -1; };
$ids = function ($sql) use ($db) {
    $out = array(); $r = $db->query($sql);
    while ($r && ($x = $r->fetch_row())) { $out[] = (int) $x[0]; }
    return $out;
};

echo ($APPLY ? "══ كنسٌ فعليّ\n" : "══ قياسٌ بلا مسّ (أضف --apply للتنفيذ)\n");

/* ── ① الجذورُ الموسومة ─────────────────────────────────────────────────── */
$roots = array(
    'clients'    => $ids("SELECT id FROM clients    WHERE client_name LIKE 'E2E%'"),
    'suppliers'  => $ids("SELECT id FROM suppliers  WHERE name        LIKE 'E2E%'"),
    'project'    => $ids("SELECT id FROM project    WHERE name        LIKE 'E2E%'"),
    'equipments' => $ids("SELECT id FROM equipments WHERE name        LIKE 'E2E%'"),
    'employees'  => $ids("SELECT id FROM employees  WHERE name        LIKE 'E2E%'"),
);
$total = 0;
foreach ($roots as $t => $list) { $total += count($list); printf("  %-12s %d  %s\n", $t, count($list), $list ? '#' . implode(' #', $list) : ''); }
if ($total === 0) { echo "\n✅ لا بقايا — لا عمل.\n"; exit(0); }

$prj = $roots['project'];
$emp = $roots['employees'];
$eq  = $roots['equipments'];
$cl  = $roots['clients'];
$sup = $roots['suppliers'];
$in  = function (array $a) { return $a ? implode(',', $a) : '0'; };

/* ── ② الفروعُ المعلَّقةُ على تلك الجذور ───────────────────────────────────── */
$tsIds    = $prj ? $ids("SELECT t.id FROM timesheet t JOIN operations o ON o.id = t.operator
                          WHERE o.project_id IN (" . $in($prj) . ")") : array();
$tsIds    = array_unique(array_merge($tsIds, $emp ? $ids("SELECT id FROM timesheet WHERE employee_id IN (" . $in($emp) . ")") : array()));
$entryIds = $prj ? $ids("SELECT id FROM unit_entries WHERE project_id IN (" . $in($prj) . ")") : array();
$conIds   = $prj ? $ids("SELECT id FROM contracts WHERE project_id IN (" . $in($prj) . ")") : array();
$sconIds  = $prj ? $ids("SELECT id FROM supplierscontracts WHERE project_id IN (" . $in($prj) . ")") : array();
$opIds    = $prj ? $ids("SELECT id FROM operations WHERE project_id IN (" . $in($prj) . ")") : array();
$clmIds   = $prj ? $ids("SELECT id FROM claims WHERE project_id IN (" . $in($prj) . ")") : array();

printf("  ── فروع: دوام=%d وقائع=%d عقود=%d عقودُ مورد=%d تشغيلات=%d مستخلصات=%d\n",
    count($tsIds), count($entryIds), count($conIds), count($sconIds), count($opIds), count($clmIds));

if (!$APPLY) { echo "\n○ قياسٌ فقط — لم يُمَسَّ شيء.\n"; exit(0); }

/* ── ③ الكنسُ بترتيبِ التبعية · وكلُّ حذفٍ يُفحَص مُرجَعُه ────────────────── */
$errs = array();
$run  = function ($sql) use ($db, &$errs) {
    if (!$db->query($sql)) { $errs[] = mb_substr($db->error, 0, 70) . ' ← ' . mb_substr($sql, 0, 70); return 0; }
    return $db->affected_rows;
};
$n = 0;

foreach ($clmIds as $id) {
    $n += $run("DELETE FROM fin_receivables WHERE id IN (SELECT receivable_id FROM claims WHERE id={$id} AND receivable_id IS NOT NULL)");
    $n += $run("DELETE FROM fin_financial_events WHERE entity_type='claim' AND entity_id={$id}");
    $n += $run("DELETE FROM ems_business_events  WHERE entity_type='claim' AND entity_id={$id}");
    $n += $run("DELETE FROM claim_lines WHERE claim_id={$id}");
    $n += $run("DELETE FROM claims WHERE id={$id}");
}
foreach ($tsIds as $id) {
    $n += $run("DELETE FROM fin_cost_records WHERE id IN (SELECT target_id FROM fin_event_links WHERE parent_kind='timesheet' AND parent_ref={$id} AND target_table='fin_cost_records')");
    $n += $run("DELETE FROM fin_dues        WHERE id IN (SELECT target_id FROM fin_event_links WHERE parent_kind='timesheet' AND parent_ref={$id} AND target_table='fin_dues')");
    $n += $run("DELETE FROM fin_financial_events WHERE id IN (SELECT target_id FROM fin_event_links WHERE parent_kind='timesheet' AND parent_ref={$id} AND target_table='fin_financial_events')");
    $n += $run("DELETE FROM unit_party_awards WHERE source_kind='timesheet' AND source_ref={$id}");
    $n += $run("DELETE FROM fin_event_links   WHERE parent_kind='timesheet' AND parent_ref={$id}");
    $n += $run("DELETE FROM fin_financial_events WHERE entity_type='timesheet' AND entity_id={$id}");
    $n += $run("DELETE FROM ems_business_events  WHERE entity_type='timesheet' AND entity_id={$id}");
    $n += $run("DELETE FROM timesheet_approvals      WHERE timesheet_id={$id}");
    $n += $run("DELETE FROM timesheet_approval_notes WHERE timesheet_id={$id}");
    $n += $run("DELETE FROM timesheet_failure_hours  WHERE timesheet_id={$id}");
    $n += $run("DELETE FROM timesheet WHERE id={$id}");
}
foreach ($entryIds as $id) {
    $n += $run("DELETE FROM ems_business_events WHERE entity_type='unit_entry' AND entity_id={$id}");
    $n += $run("DELETE FROM unit_capacity_flags WHERE entry_id={$id}");
    $n += $run("DELETE FROM unit_approvals      WHERE entry_id={$id}");
    $n += $run("DELETE FROM unit_time_log       WHERE entry_id={$id}");
    $n += $run("DELETE FROM unit_entries        WHERE id={$id}");
}
// سياساتُ الساعاتِ تُنسب بـ`operator_id` لا بعقدٍ (`contract_ref` نصٌّ حرّ) —
// فالبقايا تُقاس بالمشغّلِ الموسومِ نفسِه.
if ($emp)     { $n += $run("DELETE FROM contract_hour_policies WHERE operator_id IN (" . $in($emp) . ")"); }
if ($conIds)  { $n += $run("DELETE FROM contractequipments WHERE contract_id IN (" . $in($conIds) . ")"); }
if ($sconIds) { $n += $run("DELETE FROM suppliercontractequipments WHERE contract_id IN (" . $in($sconIds) . ")"); }
if ($sconIds) { $n += $run("DELETE FROM supplierscontracts WHERE id IN (" . $in($sconIds) . ")"); }
if ($conIds)  { $n += $run("DELETE FROM contracts WHERE id IN (" . $in($conIds) . ")"); }
if ($opIds)   { $n += $run("DELETE FROM operations WHERE id IN (" . $in($opIds) . ")"); }
if ($eq)      { $n += $run("DELETE FROM equipments WHERE id IN (" . $in($eq) . ")"); }
if ($emp)     { $n += $run("DELETE FROM employees  WHERE id IN (" . $in($emp) . ")"); }
if ($prj)     { $n += $run("DELETE FROM project    WHERE id IN (" . $in($prj) . ")"); }
if ($sup)     { $n += $run("DELETE FROM suppliers  WHERE id IN (" . $in($sup) . ")"); }
if ($cl)      { $n += $run("DELETE FROM clients    WHERE id IN (" . $in($cl) . ")"); }

echo "  ── حُذف {$n} صفًّا\n";
foreach ($errs as $e) { echo "     ⚠ {$e}\n"; }

/* ── ④ الشاهدُ المُشغَّل: صفرٌ في كلِّ جدولٍ جذر ───────────────────────────── */
echo "── الشاهدُ المُشغَّل\n";
$left = array();
foreach (array('clients' => 'client_name', 'suppliers' => 'name', 'project' => 'name',
               'equipments' => 'name', 'employees' => 'name') as $t => $c) {
    $k = $one("SELECT COUNT(*) FROM {$t} WHERE {$c} LIKE 'E2E%'");
    printf("     %-12s %d %s\n", $t, $k, $k === 0 ? '✔' : '✘');
    if ($k !== 0) { $left[] = "{$t}={$k}"; }
}
$freeKey = $one("SELECT COUNT(*) FROM clients WHERE company_id = 4 AND (client_code IS NULL OR client_code = '')");
echo "     المفتاحُ الفريدُ (4,'') محرَّرٌ: " . ($freeKey === 0 ? "نعم ✔\n" : "لا — {$freeKey} صفًّا ✘\n");

$ok = empty($left) && empty($errs) && $freeKey === 0;
echo "\n" . ($ok ? "✅ البقايا مكنوسةٌ والمفتاحُ الفريدُ محرَّر — الجولةُ القادمةُ تبذر نظيفةً.\n"
                 : "⚠ راجِع أعلاه\n");
exit($ok ? 0 : 1);
