<?php
/**
 * ra07_journeys.php — قياسُ رحلاتِ العملِ الستِّ على القاعدةِ الحيّة (قراءةٌ فقط)
 * ═══════════════════════════════════════════════════════════════════════════
 * لا يقيسُ «أيوجدُ الجدول» وحدَه — بل يقيسُ **أيصلُ الصفُّ إلى المرحلةِ التالية**.
 * لكلِّ وصلةٍ بين مرحلتين نحسب:
 *   from_total : صفوفُ المرحلةِ السابقةِ الحيّة (بعد استبعادِ is_deleted)
 *   linked     : كم منها له خلفٌ فعليٌّ في المرحلةِ التالية (COUNT DISTINCT للمفتاح)
 *   pct        : linked / from_total
 * ثم الحكم:
 *   MISSING_TABLE  الجدولُ غيرُ موجود            → فجوةُ بناء
 *   MISSING_COL    الجدولُ موجودٌ والمفتاحُ مفقود → انقطاعٌ بنيويٌّ (لا يمكن الوصل أصلًا)
 *   DEAD_LINK      المفتاحُ موجودٌ وصفوفُ السابقِ >0 والموصولُ =0 → انقطاعٌ حيّ (الأخطر)
 *   PARTIAL        0 < pct < العتبة
 *   LIVE           pct ≥ العتبة
 *   NOT_EXERCISED  المرحلةُ السابقةُ خاليةٌ — لا حكمَ على الوصلة (لا يُحسب في أيِّ مقام)
 * ◆ العتبةُ 50٪ للوصلاتِ الإلزاميةِ 1:1، و1٪ للاختياريةِ (عكس/تصعيد) — مُعلَنةٌ لكلِّ وصلة.
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$db = @mysqli_connect('127.0.0.1', 'root', '', 'equipation_manage', 3307);
if (!$db) { fwrite(STDERR, "فشل الاتصال\n"); exit(2); }
$db->set_charset('utf8mb4');
$SCHEMA = 'equipation_manage';

/* ── أدواتُ المخطط ─────────────────────────────────────────────── */
$cols = [];
$rs = $db->query("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$SCHEMA'");
while ($r = $rs->fetch_row()) { $cols[$r[0]][$r[1]] = true; }
$tableExists = fn(string $t): bool => isset($cols[$t]);
$colExists   = fn(string $t, string $c): bool => isset($cols[$t][$c]);

/** صفوفٌ حيّةٌ (تستبعد is_deleted إن وُجد) */
function liveCount(mysqli $db, array $cols, string $t, string $extra = ''): int {
    if (!isset($cols[$t])) { return -1; }
    $w = [];
    if (isset($cols[$t]['is_deleted'])) { $w[] = "is_deleted=0"; }
    if ($extra !== '') { $w[] = $extra; }
    $sql = "SELECT COUNT(*) FROM `$t`" . ($w ? ' WHERE ' . implode(' AND ', $w) : '');
    $r = $db->query($sql);
    if (!$r) { return -2; }
    return (int) $r->fetch_row()[0];
}

/* ── تعريفُ الرحلاتِ الست ───────────────────────────────────────── */
// stage: [code, name, table, extra_where]
// link : [from, to, fk_col_in_to_table, kind(req|opt), custom_sql|null]
$J = [];

$J['C'] = [
  'name' => 'الرحلةُ التجارية — عميل ← … ← قيد ← عكس',
  'stages' => [
    ['CL','عميل','clients',''],
    ['OP','فرصة','opportunities',''],
    ['QT','عرض','quotations',''],
    ['CN','عقد','contracts',''],
    ['PR','مشروع','project',''],
    ['SC','عقد مورد','supplier_contracts',''],
    ['EQ','معدة مرتبطة بعقد','contractequipments',''],
    ['OPR','مشغل معدة','equipment_operators',''],
    ['TS','تايم شيت / قيد وحدة','unit_entries',''],
    ['AP','اعتماد الوحدة','unit_approvals',''],
    ['CM','مستخلص','claims',''],
    ['CML','بنود المستخلص','claim_lines',''],
    ['IV','فاتورة','tax_invoices',''],
    ['PY','تحصيل','fin_payments',"direction='collection'"],
    ['AL','تخصيص التحصيل','fin_collection_allocations',''],
    ['JE','قيد محاسبي','fin_journal_entries',''],
    ['RV','عكس (إشعار دائن/مدين)','credit_debit_notes',''],
  ],
  'links' => [
    ['CL','OP','client_id','req',null],
    ['OP','QT','opportunity_id','req',null],
    ['QT','CN','quotation_id','req',null],
    ['CN','PR','project_id','req','SELECT COUNT(DISTINCT project_id) FROM contracts WHERE project_id IS NOT NULL AND project_id>0 AND is_deleted=0'],
    ['CN','TS','contract_id','req',null],
    ['TS','AP','entry_id','req',null],
    ['CN','CM','contract_id','req',null],
    ['CM','CML','claim_id','req',null],
    ['CM','IV','claim_id','req',null],
    ['IV','PY','__alloc__','req','SELECT COUNT(DISTINCT target_ref) FROM fin_collection_allocations WHERE target_kind="invoice" AND target_ref IS NOT NULL AND target_ref>0'],
    ['PY','AL','payment_id','req',null],
    ['CM','JE','__event__','req','SELECT COUNT(*) FROM claims c JOIN fin_financial_events e ON e.id=c.event_id WHERE c.event_id>0 AND e.journal_entry_id IS NOT NULL AND e.journal_entry_id>0 AND c.is_deleted=0'],
    ['CM','RV','claim_id','opt',null],
  ],
];

$J['M'] = [
  'name' => 'رحلةُ الصيانة — بلاغ ← … ← عودةٌ للخدمة',
  'stages' => [
    ['BD','بلاغ عطل','mnt_breakdown',''],
    ['IN','تشخيص/فحص','mnt_inspection',''],
    ['OR','أمر صيانة','mnt_order',''],
    ['PT','قطع الأمر','mnt_order_part',''],
    ['LB','عمالة الأمر (إصلاح)','mnt_order_labor',''],
    ['IS','صرف مخزني للأمر','proc_issue',"maintenance_order_id IS NOT NULL AND maintenance_order_id>0"],
    ['RD','شهادة جاهزية','readiness_lines',''],
    ['HS','أثرٌ في سجل المعدة','fleet_equipment_history',''],
    ['FE','أثرٌ مالي','fin_financial_events',"source_module='maintenance'"],
  ],
  'links' => [
    ['BD','OR','breakdown_id','req',null],
    ['IN','OR','inspection_id','opt',null],
    ['OR','PT','order_id','req',null],
    ['OR','LB','order_id','req',null],
    ['OR','IS','maintenance_order_id','opt',null],
    ['OR','RD','__ref__','req','SELECT COUNT(DISTINCT readiness_cert_ref) FROM mnt_order WHERE readiness_cert_ref IS NOT NULL AND readiness_cert_ref<>"" AND is_deleted=0'],
    ['OR','FE','__module__','req','SELECT COUNT(*) FROM fin_financial_events WHERE source_module="maintenance" AND is_deleted=0'],
  ],
];

$J['P'] = [
  'name' => 'رحلةُ المشترياتِ والمخازن — طلبُ شراء ← … ← أمرُ دفع',
  'stages' => [
    ['RQ','طلب شراء','proc_request',''],
    ['RF','طلب عروض','supplier_rfqs',''],
    ['QU','عروض الموردين (مقارنة)','rfq_quotes',''],
    ['AW','ترسية','rfq_awards',''],
    ['PO','أمر شراء','proc_order',''],
    ['CU','عهدة استلام','proc_receipt_custody',''],
    ['RL','بنود الاستلام','proc_receipt_line',''],
    ['SM','حركة مخزنية','proc_stock_move',''],
    ['IS','صرف','proc_issue',''],
    ['MT','مطابقة ثلاثية','proc_order',"match_state IS NOT NULL AND match_state<>''"],
    ['FR','أمر دفع (طلب مالي)','fin_requests',"source_module='procurement'"],
  ],
  'links' => [
    ['RQ','RF','request_id','req',null],
    ['RF','QU','rfq_id','req',null],
    ['QU','AW','quote_id','req',null],
    ['AW','PO','award_id','req',null],
    ['RQ','PO','request_id','req',null],
    ['PO','CU','order_id','req',null],
    ['CU','RL','custody_id','req',null],
    ['CU','SM','__ref__','req','SELECT COUNT(DISTINCT ref_id) FROM proc_stock_move WHERE ref_type="proc_receipt_custody" AND ref_id IS NOT NULL AND ref_id>0'],
    ['PO','FR','__module__','req','SELECT COUNT(*) FROM fin_requests WHERE source_module="procurement"'],
  ],
];

$J['F'] = [
  'name' => 'رحلةُ الاعتمادِ المالي — طلب ← … ← إقفال',
  'stages' => [
    ['RQ','طلب مالي','fin_requests',''],
    ['RT','توجيه/مسار','fin_routing_log',''],
    ['AP','خطوات الاعتماد','fin_approvals',"entity_type='fin_request'"],
    ['CP','سقف الصلاحية','fin_authority_caps',''],
    ['PY','تنفيذ الخزينة','fin_payments',"direction='disbursement'"],
    ['FE','حدث مالي','fin_financial_events',''],
    ['JE','قيد','fin_journal_entries',''],
    ['JL','بنود القيد','fin_journal_lines',''],
    ['BR','مطابقة بنكية','bank_recon_matches',''],
    ['CI','بنود الإقفال','fin_closing_items',''],
  ],
  'links' => [
    ['FE','RT','__src__','req','SELECT COUNT(DISTINCT financial_event_id) FROM fin_routing_log WHERE financial_event_id IS NOT NULL AND financial_event_id>0'],
    ['RQ','AP','entity_id','req','SELECT COUNT(DISTINCT entity_id) FROM fin_approvals WHERE entity_type="fin_request" AND entity_id>0'],
    ['RQ','FE','__event__','req','SELECT COUNT(DISTINCT event_id) FROM fin_requests WHERE event_id IS NOT NULL AND event_id>0'],
    ['FE','JE','event_id','req',null],
    ['JE','JL','entry_id','req',null],
    ['PY','BR','__ref__','opt','SELECT COUNT(*) FROM bank_recon_matches'],
    ['JE','CI','__any__','opt','SELECT COUNT(*) FROM fin_closing_items'],
  ],
];

$J['R'] = [
  'name' => 'رحلةُ المخاطر — إشارة ← … ← إغلاق/إعادةُ فتح',
  'stages' => [
    ['SG','إشارة','risk_signals',''],
    ['TR','فرز (إشارة مفروزة)','risk_signals',"state<>'new'"],
    ['RG','خطر مسجَّل','risk_register',''],
    ['AS','تقييم','risk_assessments',''],
    ['CT','ضوابط','risk_controls',''],
    ['CL','ربط ضابط بخطر','risk_control_links',''],
    ['TT','معالجة','risk_treatments',''],
    ['AC','قبول','risk_acceptances',''],
    ['ES','تصعيد','risk_escalations',''],
    ['RV','إعادة تقييم/مراجعة','risk_reviews',''],
  ],
  'links' => [
    ['SG','RG','linked_risk_id','req','SELECT COUNT(DISTINCT linked_risk_id) FROM risk_signals WHERE linked_risk_id IS NOT NULL AND linked_risk_id>0'],
    ['RG','AS','risk_id','req',null],
    ['RG','TT','risk_id','req',null],
    ['RG','CL','risk_id','req',null],
    ['RG','AC','risk_id','opt',null],
    ['RG','ES','risk_id','opt',null],
    ['RG','RV','risk_id','req',null],
  ],
];

$J['S'] = [
  'name' => 'رحلةُ الصلاحيات — مستخدم ← … ← انتهاءُ التفويض',
  'stages' => [
    ['US','مستخدم','users',''],
    ['RO','دور','roles',''],
    ['UR','تكليفُ مستخدمٍ بدور','users',"role_id IS NOT NULL AND role_id>0"],
    ['SC','نطاق','sec_scopes',''],
    ['MD','وحدة صلاحية (ظهور)','modules',''],
    ['GR','منحة دور','role_permissions',''],
    ['AC','فعلٌ مسجَّل','nav09_action_map',''],
    ['GP','سياسةُ حارس','guard_policies',''],
    ['DN','رفضٌ مسجَّل','guard_denials',''],
    ['AU','سجل تدقيق','activity_logs',''],
    ['DL','تفويض','work_delegations',''],
    ['DX','تفويضٌ منتهٍ','work_delegations',"ends_at IS NOT NULL AND ends_at < NOW()"],
  ],
  'links' => [
    ['RO','GR','role_id','req',null],
    ['MD','GR','module_id','req',null],
    ['RO','UR','__role__','req','SELECT COUNT(DISTINCT role_id) FROM users WHERE role_id IS NOT NULL AND role_id>0 AND is_deleted=0'],
    ['AC','DN','__code__','opt','SELECT COUNT(DISTINCT guard_code) FROM guard_denials'],
    ['DL','DX','__self__','opt','SELECT COUNT(*) FROM work_delegations WHERE ends_at IS NOT NULL AND ends_at < NOW()'],
  ],
];

/* ── التنفيذ ────────────────────────────────────────────────────── */
$out = ['measured_at' => gmdate('c'), 'journeys' => []];

foreach ($J as $jk => $j) {
    $stages = [];
    $byCode = [];
    foreach ($j['stages'] as [$c, $n, $t, $extra]) {
        $exists = $tableExists($t);
        $cnt = $exists ? liveCount($db, $cols, $t, $extra) : -1;
        $stages[$c] = [
            'code' => $c, 'name' => $n, 'table' => $t, 'filter' => $extra,
            'exists' => $exists, 'rows' => $cnt,
            'verdict' => !$exists ? 'MISSING_TABLE' : ($cnt > 0 ? 'HAS_DATA' : 'EMPTY'),
        ];
        $byCode[$c] = $stages[$c];
    }

    $links = [];
    foreach ($j['links'] as [$from, $to, $fk, $kind, $custom]) {
        $fromS = $byCode[$from] ?? null;
        $toS   = $byCode[$to] ?? null;
        $rec = [
            'from' => $from, 'from_name' => $fromS['name'] ?? '?', 'from_table' => $fromS['table'] ?? '?',
            'to' => $to, 'to_name' => $toS['name'] ?? '?', 'to_table' => $toS['table'] ?? '?',
            'fk' => $fk, 'kind' => $kind,
            'from_total' => $fromS['rows'] ?? -1, 'linked' => -1, 'pct' => null, 'verdict' => '',
            'note' => '',
        ];

        if (!$fromS || !$toS || !$fromS['exists'] || !$toS['exists']) {
            $rec['verdict'] = 'MISSING_TABLE';
            $rec['note'] = 'جدولُ إحدى المرحلتين غيرُ موجود';
            $links[] = $rec; continue;
        }

        // هل المفتاحُ موجودٌ في جدولِ الوجهة؟
        $isCustom = ($custom !== null) || str_starts_with($fk, '__');
        if (!$isCustom && !$colExists($toS['table'], $fk)) {
            $rec['verdict'] = 'MISSING_COL';
            $rec['note'] = "العمود `$fk` غيرُ موجودٍ في `{$toS['table']}` — لا يمكن الوصلُ بنيويًّا";
            $links[] = $rec; continue;
        }
        if ($isCustom && $custom === null) {
            $rec['verdict'] = 'NO_STRUCTURAL_LINK';
            $rec['note'] = 'لا مفتاحَ مباشرًا مُعرَّفًا — الوصلُ غيرُ مقيسٍ في هذه الجولة';
            $links[] = $rec; continue;
        }

        if ($custom !== null) {
            $r = $db->query($custom);
            $rec['linked'] = $r ? (int) $r->fetch_row()[0] : -2;
            $rec['note'] = 'قياسٌ مخصَّص: ' . $custom;
        } else {
            $w = ["`$fk` IS NOT NULL", "`$fk` > 0"];
            if ($colExists($toS['table'], 'is_deleted')) { $w[] = 'is_deleted=0'; }
            $sql = "SELECT COUNT(DISTINCT `$fk`) FROM `{$toS['table']}` WHERE " . implode(' AND ', $w);
            $r = $db->query($sql);
            $rec['linked'] = $r ? (int) $r->fetch_row()[0] : -2;
        }

        $ft = $rec['from_total'];
        if ($ft <= 0) {
            $rec['verdict'] = 'NOT_EXERCISED';
            $rec['note'] = trim($rec['note'] . ' · المرحلةُ السابقةُ خاليةٌ — لا حكم');
        } elseif ($rec['linked'] <= 0) {
            $rec['verdict'] = 'DEAD_LINK';
        } else {
            $pct = round($rec['linked'] / $ft * 100, 1);
            $rec['pct'] = $pct;
            $thr = ($kind === 'req') ? 50.0 : 1.0;
            $rec['verdict'] = ($pct >= $thr) ? 'LIVE' : 'PARTIAL';
        }
        $links[] = $rec;
    }

    // خلاصةُ الرحلة — المقامُ الوصلاتُ القابلةُ للحكم فقط
    $judgeable = array_values(array_filter($links, fn($l) => !in_array($l['verdict'], ['NOT_EXERCISED', 'NO_STRUCTURAL_LINK'], true)));
    $live = count(array_filter($judgeable, fn($l) => $l['verdict'] === 'LIVE'));
    $out['journeys'][$jk] = [
        'key' => $jk, 'name' => $j['name'],
        'stages' => array_values($stages),
        'links' => $links,
        'stage_count' => count($stages),
        'stages_missing' => count(array_filter($stages, fn($s) => $s['verdict'] === 'MISSING_TABLE')),
        'stages_empty' => count(array_filter($stages, fn($s) => $s['verdict'] === 'EMPTY')),
        'links_total' => count($links),
        'links_judgeable' => count($judgeable),
        'links_live' => $live,
        'links_dead' => count(array_filter($links, fn($l) => $l['verdict'] === 'DEAD_LINK')),
        'links_partial' => count(array_filter($links, fn($l) => $l['verdict'] === 'PARTIAL')),
        'links_missing_col' => count(array_filter($links, fn($l) => $l['verdict'] === 'MISSING_COL')),
        'continuity_pct' => count($judgeable) ? round($live / count($judgeable) * 100, 1) : null,
    ];
}

$dir = dirname(__DIR__) . '/evidence';
file_put_contents($dir . '/journeys.json', json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

/* ── تقريرٌ نصيٌّ للشاشة ─────────────────────────────────────────── */
foreach ($out['journeys'] as $j) {
    printf("\n══ %s [%s]\n", $j['name'], $j['key']);
    printf("   مراحل: %d (مفقودةُ الجدول %d · خالية %d) · وصلات: %d (قابلةُ للحكم %d) · متصلةٌ حيًّا %d · ميتة %d · جزئية %d · بلا عمود %d ⇒ الاتصالية %s٪\n",
        $j['stage_count'], $j['stages_missing'], $j['stages_empty'], $j['links_total'], $j['links_judgeable'],
        $j['links_live'], $j['links_dead'], $j['links_partial'], $j['links_missing_col'],
        $j['continuity_pct'] === null ? 'ن/م' : $j['continuity_pct']);
    foreach ($j['stages'] as $s) {
        if ($s['verdict'] !== 'HAS_DATA') {
            printf("   ◇ مرحلة %-4s %-28s %-26s %s\n", $s['code'], mb_substr($s['name'], 0, 26), $s['table'], $s['verdict']);
        }
    }
    foreach ($j['links'] as $l) {
        if (in_array($l['verdict'], ['LIVE'], true)) { continue; }
        printf("   ✖ %-4s→%-4s %-22s→%-22s %-16s from=%d linked=%d %s\n",
            $l['from'], $l['to'], mb_substr($l['from_name'], 0, 22), mb_substr($l['to_name'], 0, 22),
            $l['verdict'], $l['from_total'], $l['linked'], $l['pct'] === null ? '' : $l['pct'] . '٪');
    }
}
echo "\n\nكُتب: evidence/journeys.json\n";
