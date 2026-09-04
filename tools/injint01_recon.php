<?php
/**
 * tools/injint01_recon.php — مِسبارُ استطلاعِ INJ-INT-01 / RJ-01 (قراءةٌ محضة)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ يُسنِد ادّعاءاتِ الأمرِ التنفيذيِّ إلى المخزنِ الحيِّ **قبل** أيِّ تنفيذ.
 * ⛔ **ولا يُصدَّق صفرٌ حتى يُثبَت أنَّ المقياسَ يرى**: عمودُ حالةِ التسليمِ اسمُه
 *   `state` لا `status`، ومَن سأل بـ`status` أخرج «صفرَ dlq» وهي سبعةٌ وثلاثون.
 * ⛔ **ولا يُقرأ رقمُ الـDLQ رقمًا واحدًا**: فيه عائلةُ قرارٍ مكتوبٍ (CONSUMER_RETIRED)
 *   وعائلةُ عطبٍ حقيقيٍّ (HANDLER_ERROR) — وخلطُهما يُخفي الأخطرَ ويُضخّم الأهون.
 * التشغيل: php tools/injint01_recon.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8'); mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = dirname(__DIR__); require_once $ROOT . '/includes/env.php';
$h = ems_env('DB_HOST'); $p = 3306;
if (strpos($h, ':') !== false) { list($h, $p) = explode(':', $h); $p = (int) $p; }
$c = new mysqli($h, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $p);
if ($c->connect_errno) { exit('تعذّر الاتصال: ' . $c->connect_error . "\n"); }
$c->set_charset('utf8mb4'); $DB = ems_env('DB_NAME');

function one($c, $q) { $r = $c->query($q); if (!$r) { echo '  SQL: ' . $c->error . "\n"; return null; } $x = $r->fetch_row(); return $x ? $x[0] : null; }
function rows($c, $q) { $r = $c->query($q); if (!$r) { echo '  SQL: ' . $c->error . "\n"; return array(); } $o = array(); while ($x = $r->fetch_assoc()) { $o[] = $x; } return $o; }
function tex($c, $t) { $r = $c->query("SHOW TABLES LIKE '" . $c->real_escape_string($t) . "'"); return $r && $r->num_rows > 0; }
/** نوعُ الكيانِ ليس اسمَ جدول: `fin_asset` كيانٌ و`fin_assets` جدولٌ — تُجرَّب
 *  صيغُ الجمعِ الثلاثُ قبلَ الحكمِ بعدمِ الحلّ، وإلا خرج «لا جدولَ» كاذبًا.
 *  والحالُّ هنا **هو حالُّ `retry_disposition` نفسُه** — مصدرٌ واحدٌ لا اثنان
 *  يتفرَّقان فيتناقض تقريرانِ عن مخزنٍ واحد. */
function resolve_table($c, $entity) {
    $e = strtolower(trim((string) $entity));
    if ($e === '') { return ''; }
    foreach (array($e, $e . 's', $e . 'es', rtrim($e, 'y') . 'ies') as $cand) {
        if (tex($c, $cand)) { return $cand; }
    }
    return '';
}

/* ══ ① الساعة — ادّعاءُ الأمرِ: انزياحٌ مثبَت ══════════════════════════════ */
echo "══ ① الساعةُ ══\n";
$dbu = one($c, 'SELECT UTC_TIMESTAMP()');
printf("  OS(UTC)=%s  PHP.tz=%s  DB(UTC)=%s  DB.tz=%s  |فرق|=%d ثانية\n",
    gmdate('Y-m-d H:i:s'), date_default_timezone_get(), $dbu, one($c, 'SELECT @@session.time_zone'),
    abs(strtotime(gmdate('Y-m-d H:i:s')) - strtotime($dbu)));
/* والختمُ المستقبليُّ ليس انزياحَ ساعةٍ إن كان جدولَ إهلاكٍ أو أقساطٍ مُسقَطًا عمدًا */
printf("  occurred_at مستقبليّ = %s · created_at مستقبليّ = %s\n",
    one($c, 'SELECT COUNT(*) FROM ems_business_events WHERE occurred_at > UTC_TIMESTAMP()'),
    one($c, 'SELECT COUNT(*) FROM ems_business_events WHERE created_at  > UTC_TIMESTAMP()'));
foreach (rows($c, 'SELECT YEAR(occurred_at) y, COUNT(*) n FROM ems_business_events GROUP BY y ORDER BY y') as $r) {
    printf("    سنة %-6s %s\n", $r['y'], $r['n']);
}

/* ══ ② الناقلُ — المقامُ الحقيقيّ ═════════════════════════════════════════ */
echo "\n══ ② الناقلُ ══\n";
printf("  ems_business_events=%s · deliveries=%s · gov_event_rulings=%s\n",
    one($c, 'SELECT COUNT(*) FROM ems_business_events'), one($c, 'SELECT COUNT(*) FROM ems_event_deliveries'),
    one($c, 'SELECT COUNT(*) FROM gov_event_rulings'));
printf("  مفاتيحُ منتَجةٌ متميّزة = %s\n", one($c, 'SELECT COUNT(DISTINCT event_key) FROM ems_business_events'));
foreach (rows($c, 'SELECT state, COUNT(*) n FROM ems_event_deliveries GROUP BY state ORDER BY n DESC') as $r) {
    printf("    state %-11s %7d\n", $r['state'], $r['n']);
}
printf("  ems_event_dead_letter=%s · delivery_orphans=%s · events.in_dlq=%s\n",
    one($c, 'SELECT COUNT(*) FROM ems_event_dead_letter'), one($c, 'SELECT COUNT(*) FROM ems_event_delivery_orphans'),
    one($c, 'SELECT COUNT(*) FROM ems_business_events WHERE in_dlq=1'));

/* ══ ③ الـDLQ بعائلاتِه — لا رقمًا واحدًا ═══════════════════════════════ */
echo "\n══ ③ الـDLQ بعائلاتِه ══\n";
foreach (rows($c, "SELECT COALESCE(consumer_key,consumer) k, fail_code, COUNT(*) n
                     FROM ems_event_deliveries WHERE state='dlq' GROUP BY k,fail_code ORDER BY n DESC") as $r) {
    printf("  %-22s %-18s %3d\n", $r['k'], $r['fail_code'], $r['n']);
}
echo "  ⑵ صفوفُ HANDLER_ERROR — أَمُحقَّقٌ أثرُها؟ أَقابلٌ مصدرُها للحلّ؟\n";
foreach (rows($c, "SELECT d.id, b.event_key, b.entity_type, b.entity_id, b.amount,
                          (SELECT COUNT(*) FROM fin_event_links   l WHERE l.event_id=b.id) links,
                          (SELECT COUNT(*) FROM fin_event_effects e WHERE e.event_id=b.id) eff
                     FROM ems_event_deliveries d JOIN ems_business_events b ON b.id=d.event_id
                    WHERE d.state='dlq' AND d.fail_code='HANDLER_ERROR' ORDER BY d.id") as $r) {
    $ent = (string) $r['entity_type'];
    $tbl = resolve_table($c, $ent);
    $alive = $tbl === '' ? 'لا جدولَ' : one($c, "SELECT COUNT(*) FROM `$tbl` WHERE id=" . (int) $r['entity_id']);
    $span = $tbl === '' ? '' : one($c, "SELECT CONCAT(COUNT(*),' صفًّا · المدى ',COALESCE(MIN(id),'—'),'..',COALESCE(MAX(id),'—')) FROM `$tbl`");
    printf("    #%-6s %-40s %s#%-6s links=%s eff=%s حيّ=%s  [%s: %s]\n",
        $r['id'], $r['event_key'], $ent, $r['entity_id'], $r['links'], $r['eff'], $alive, $tbl ?: '—', $span);
}

/* ══ ④ سجلّا المستهلكين — أيُّهما يقرؤه الإنتاج؟ ═════════════════════════ */
echo "\n══ ④ سجلّا المستهلكين ══\n";
printf("  event_consumers=%s  ⇐ الحاكمُ (يقرؤه EventOutboxFanout وEventDeliveryWorker)\n", one($c, 'SELECT COUNT(*) FROM event_consumers'));
printf("  ems_event_subscriptions=%s  ⇐ إعلانُ نيّةٍ لا يقرؤه سطرُ إنتاج\n", one($c, 'SELECT COUNT(*) FROM ems_event_subscriptions'));
foreach (rows($c, 'SELECT consumer_class, active, COUNT(DISTINCT event_name) k FROM event_consumers GROUP BY consumer_class, active ORDER BY k DESC') as $r) {
    printf("    %-56s active=%s مفاتيح=%s\n", $r['consumer_class'], $r['active'], $r['k']);
}
echo "  ⑵ EffectFanout — أقرارٌ مكتوبٌ أم عطبُ FQCN؟\n";
foreach (rows($c, "SELECT DISTINCT consumer_class, active, inactive_reason FROM event_consumers WHERE consumer_class LIKE '%EffectFanout%'") as $r) {
    printf("    class=%s active=%s\n    سبب: %s\n", $r['consumer_class'], $r['active'], $r['inactive_reason']);
}
foreach (array('App/Core/EffectFanout.php', 'App/Services/EffectFanout.php') as $f) {
    printf("    على القرص %-32s %s\n", $f, file_exists("$ROOT/$f") ? 'موجود' : 'غائب');
}

/* ══ ⑤ أحكامُ الأحداث ═══════════════════════════════════════════════════ */
echo "\n══ ⑤ أحكامُ الأحداث ══\n";
foreach (rows($c, 'SELECT ruling, COUNT(*) n FROM gov_event_rulings GROUP BY ruling') as $r) { printf("  %-10s %s\n", $r['ruling'], $r['n']); }
echo '  آخرُ قياسٍ للأحكام: ' . one($c, 'SELECT MAX(measured_at) FROM gov_event_rulings') . "\n";
echo "  الـbusiness ومستهلكوها النشِطون:\n";
foreach (rows($c, "SELECT g.event_key, g.produced_count,
                          GROUP_CONCAT(DISTINCT ec.consumer_class ORDER BY ec.consumer_class SEPARATOR ' | ') cc
                     FROM gov_event_rulings g
                     LEFT JOIN event_consumers ec ON ec.event_name=g.event_key AND ec.active=1
                    WHERE g.ruling='business' GROUP BY g.event_key, g.produced_count ORDER BY g.produced_count DESC") as $r) {
    printf("    %-40s prod=%-6s %s\n", $r['event_key'], $r['produced_count'], $r['cc'] ? $r['cc'] : 'لا مستهلكَ نشِط');
}

/* ══ ⑥ الأساسُ المحاسبيّ ════════════════════════════════════════════════ */
echo "\n══ ⑥ الأساسُ المحاسبيّ ══\n";
$b = rows($c, 'SELECT ROUND(SUM(debit),2) d, ROUND(SUM(credit),2) cr, ROUND(SUM(debit)-SUM(credit),2) diff FROM fin_journal_lines');
printf("  قيود=%s أسطر=%s | مدين=%s دائن=%s فرق=%s\n",
    one($c, 'SELECT COUNT(*) FROM fin_journal_entries'), one($c, 'SELECT COUNT(*) FROM fin_journal_lines'),
    $b[0]['d'], $b[0]['cr'], $b[0]['diff']);
printf("  fin_event_effects=%s · fin_event_links=%s · fin_financial_events=%s\n",
    one($c, 'SELECT COUNT(*) FROM fin_event_effects'), one($c, 'SELECT COUNT(*) FROM fin_event_links'),
    one($c, 'SELECT COUNT(*) FROM fin_financial_events'));

/* ══ ⑦ سلسلةُ RJ-01 — والنوعُ يفرّق الجدولَ عن الرؤية ═══════════════════ */
echo "\n══ ⑦ سلسلةُ RJ-01 ══\n";
$chain = array(
    'DEP-01 تجاري'    => array('clients', 'project', 'opportunities', 'sal_client_needs', 'sal_client_need_rfq', 'quotations', 'sal_quotations', 'contracts', 'client_contracts', 'contract_commitments', 'contract_monthly_plan'),
    'DEP-02 موردون'   => array('suppliers', 'supplier_contracts', 'supplierscontracts', 'supplier_rfqs', 'rfq_lines', 'rfq_quotes', 'rfq_awards', 'supplier_capacity', 'wf_coverage', 'sup_quota_supplier_unit', 'sup_allocation_unit_equipment', 'suppliercontractequipments'),
    'DEP-04 أسطول'    => array('equipments', 'contractequipments', 'asset_use_right', 'asset_readiness', 'equipment_operators'),
    'DEP-13 قوى'      => array('employees', 'equipment_drivers', 'operator_rotations'),
    'DEP-12/11 تشغيل' => array('sites', 'contract_operational_sites', 'site_day', 'site_day_shift', 'operations', 'timesheet', 'ops_timesheet', 'unit_entries', 'unit_time_log', 'unit_approvals', 'unit_final_approvals'),
    'DEP-05 مالية'    => array('claims', 'claim_lines', 'sal_claims', 'ar_claim_invoices', 'tax_invoices', 'fin_receivables'),
    'DEP-06 خزينة'    => array('fin_payments', 'fin_collection_allocations', 'tre_cash_box', 'tre_cash_move', 'fina_collections'),
);
$types = array();
foreach (rows($c, "SELECT TABLE_NAME n, TABLE_TYPE t FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DB'") as $r) { $types[$r['n']] = $r['t']; }
foreach ($chain as $dep => $ts) {
    echo "  ◆ $dep\n";
    foreach ($ts as $t) {
        if (!isset($types[$t])) { printf("     %-30s   غائب\n", $t); continue; }
        printf("     %-30s %8s %s\n", $t, one($c, "SELECT COUNT(*) FROM `$t`"), $types[$t] === 'VIEW' ? '(رؤية)' : '');
    }
}

/* ══ ⑧ النطاق ══════════════════════════════════════════════════════════ */
echo "\n══ ⑧ النطاق ══\n";
foreach (rows($c, 'SELECT id, company_name, status FROM admin_companies ORDER BY id') as $r) {
    printf("  شركة #%s %s [%s]\n", $r['id'], $r['company_name'], $r['status']);
}
foreach (rows($c, 'SELECT company_id, COUNT(*) n FROM ems_business_events GROUP BY company_id') as $r) {
    printf("  أحداثُ الشركة %s = %s\n", $r['company_id'], $r['n']);
}
echo "\nتمّ.\n";
