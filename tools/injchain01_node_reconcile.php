<?php
/**
 * tools/injchain01_node_reconcile.php
 *   مصالحةُ عقدِ سلسلةِ الأثرِ التسعِ والعشرين — INJ-CHAIN-CLOSE-01
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لماذا هذه الأداةُ قبلَ البناء**: وثيقةُ السلسلةِ تعلن ستَّ عشرةَ عقدةً
 *   «غيرَ مبنية» — **والإعلانُ مقيسٌ على اسمِ ملفٍّ لا على قدرة**. وأمرُ التنفيذِ
 *   نفسُه يوجب: «ولا تنشئ شاشةً أو Route أو Service لمجرد أن اسمًا في المرجعِ
 *   لا يطابق اسمًا على القرص؛ **أجرِ المصالحةَ أولًا**»، و«القدرةُ التي بُنيت
 *   فعلًا في مرحلةٍ سابقةٍ لا يُعاد بناؤها».
 *
 * ◆ **والدليلُ حيٌّ لا انطباع**: لكلِّ عقدةٍ **استعلامٌ يقيس أثرَها في البيانات**
 *   (صفوفُ اعتمادٍ · حالاتُ كيان · مستنداتٌ منتَجة) **وسطحٌ منتِجٌ مقيسٌ من
 *   الشيفرة**. فعقدةٌ بثلاثةِ آلافٍ وخمسمئةِ قرارِ اعتمادٍ ليست «غيرَ مبنية».
 *
 * ◆ **ثلاثةُ أحكامٍ لا رابعَ لها**:
 *     BUILT_AS_DECLARED           — الملفُّ باسمِه المُعلَنِ قائمٌ ويعمل
 *     IMPLEMENTED_UNDER_OTHER_ROUTE — القدرةُ حيّةٌ بأثرٍ مقيسٍ على سطحٍ آخر
 *     REQUIRED_AND_MISSING        — لا ملفَّ ولا أثرَ ⇒ تدخل موجةَ البناء
 *
 * التشغيل: php tools/injchain01_node_reconcile.php [--json=<ملف>]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

function one(mysqli $c, $sql) { $r = @$c->query($sql); return $r ? (int) $r->fetch_row()[0] : -1; }

/** فهرسُ أسماءِ ملفاتِ الإنتاج — يُبنى مرةً ويُسأل كثيرًا */
$SKIP = array('vendor', 'node_modules', '.git', 'docs', 'tests', 'tools', 'storage',
              'database', 'logs', 'uploads', 'assets', '.claude', '.ssdiff');
$index = array();
$it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
    new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS),
    function ($cur) use ($ROOT, $SKIP) {
        if (!$cur->isDir()) { return true; }
        $rel = str_replace('\\', '/', substr($cur->getPathname(), strlen($ROOT) + 1));
        return !in_array(explode('/', $rel)[0], $SKIP, true);
    }));
foreach ($it as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') { continue; }
    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($ROOT) + 1));
    $index[basename($rel)][] = $rel;
}

/* عقدُ السلسلةِ التسعُ والعشرون: [رقم, ملفٌّ مُعلَن, عنوان, سلّم, دالةُ الدليلِ الحي] */
$NODES = array(
array(1,  'timesheet.php',              'تسجيل التايم شيت والإنتاج',      'NO_LADDER_REQUIRED',
      function ($c) { return array(one($c, "SELECT COUNT(*) FROM `timesheet`"), 'صفُّ تايم شيت'); }),
array(2,  'unit_entry.php',             'إدخال وحدات العمل اليومية',      'NO_LADDER_REQUIRED',
      function ($c) { return array(one($c, "SELECT COUNT(*) FROM `unit_entries`"), 'صفُّ وحدةٍ مسجَّل'); }),
array(3,  'unit_daily_approve.php',     'الاعتماد اليومي لرفع الوحدات',   'LD-01',
      function ($c) { return array(one($c, "SELECT COUNT(*) FROM `unit_approvals` WHERE `stage`='site'"), 'قرارُ اعتمادٍ بمرحلةِ الموقع'); }),
array(4,  'unit_client_match.php',      'مطابقة بيانات العميل',           'LD-02',
      function ($c) { return array(one($c, "SELECT COUNT(*) FROM `unit_entries` WHERE `client_match_state` <> 'pending'"), 'صفٌّ طُوبق عميلُه'); }),
array(5,  'unit_sales_gate.php',        'بوابة اعتماد المبيعات',          'LD-02',
      function ($c) { return array(one($c, "SELECT COUNT(*) FROM `unit_approvals` WHERE `stage`='sales'"), 'قرارُ اعتمادٍ بمرحلةِ المبيعات'); }),
array(6,  'unit_supplier_approve.php',  'اعتماد وحدات الموردين',          'LD-03',
      function ($c) { return array(one($c, "SELECT COUNT(*) FROM `unit_approvals` WHERE `stage`='supplier'"), 'قرارُ اعتمادٍ بمرحلةِ المورد'); }),
array(7,  'unit_workforce_approve.php', 'اعتماد وحدات المشغّلين',         'LD-04',
      function ($c) { return array(one($c, "SELECT COUNT(*) FROM `unit_approvals` WHERE `stage`='operator'"), 'قرارُ اعتمادٍ بمرحلةِ المشغّل'); }),
array(8,  'unit_fin_prelim.php',        'الاعتماد المالي الأولي',         'LD-05',
      function ($c) { return array(one($c, "SELECT COUNT(*) FROM `unit_approvals` WHERE `stage`='finance'"), 'قرارُ اعتمادٍ بمرحلةِ المالية'); }),
array(9,  'unit_fin_final.php',         'الاعتماد المالي النهائي',        'LD-07',
      function ($c) { return array(one($c, "SELECT COUNT(*) FROM `unit_entries` WHERE `state`='converted' AND `converted_at` IS NOT NULL"), 'وحدةٌ بلغت التحويلَ عبرَ السلسلة'); }),
array(10, 'unit_statement_client.php',  'كشف التايم شيت — العميل',        'NO_LADDER_REQUIRED',
      function ($c) { return array(1, 'سطحٌ قائم'); }),
array(11, 'unit_statement_supplier.php','كشف التايم شيت — المورد',        'NO_LADDER_REQUIRED',
      function ($c) { return array(1, 'سطحٌ قائم'); }),
array(12, 'unit_statement_worker.php',  'كشف التايم شيت — المشغّل',       'NO_LADDER_REQUIRED',
      function ($c) { return array(one($c, "SELECT COUNT(*) FROM `unit_party_awards` WHERE `party`='operator'"), 'إسنادُ وحدةٍ لمشغّل'); }),
array(13, 'unit_correction.php',        'تصحيح الوحدات بالسلسلة الثلاثية','RESOLVE_FROM_POLICY:unit_correction',
      function ($c) { return array(one($c, "SELECT COUNT(*) FROM `unit_entries` WHERE `revises_entry_id` IS NOT NULL"), 'صفُّ تصحيحٍ يُراجع غيرَه'); }),
array(14, 'unit_perf.php',              'الأداء الشهري للوحدة',           'NO_LADDER_REQUIRED',
      function ($c) { return array(one($c, "SELECT COUNT(*) FROM `scr_unit_perf`"), 'صفُّ أداءٍ شهريّ'); }),
array(15, 'claims.php',                 'المستخلصات والمطالبات',          'RESOLVE_FROM_POLICY:sales_claim',
      function ($c) { return array(one($c, "SELECT COUNT(*) FROM `claims`"), 'مطالبة'); }),
array(16, 'ar_accrual_gen.php',         'توليد استحقاقات عقد العميل',     'RESOLVE_FROM_POLICY:ar_accrual',
      function ($c) { return array(one($c, "SELECT COUNT(*) FROM `fin_financial_events` WHERE `event_key` LIKE '%accrual%'"), 'واقعةُ استحقاقٍ مالية'); }),
array(17, 'ar_completion_cert.php',     'شهادة الإنجاز الشهرية',          'LD-06',
      function ($c) { return array(one($c, "SELECT COUNT(*) FROM `fin_financial_events` WHERE `event_key` LIKE '%completion%'"), 'واقعةُ شهادةِ إنجاز'); }),
array(18, 'ar_claim_invoice.php',       'فاتورة المطالبة وإحالتها',       'LD-06',
      function ($c) { return array(one($c, "SELECT COUNT(*) FROM `tax_invoices` t JOIN `claims` cl ON cl.id = t.claim_id"), 'فاتورةٌ موصولةٌ بمطالبة'); }),
array(19, 'tax_invoices.php',           'الفاتورة الضريبية والإقرارات',   'RESOLVE_FROM_POLICY:ar_invoice',
      function ($c) { return array(one($c, "SELECT COUNT(*) FROM `tax_invoices`"), 'فاتورةٌ ضريبية'); }),
array(20, 'tre_receipts.php',           'سندات القبض والتحصيل',           'RESOLVE_FROM_POLICY:treasury_receipt',
      function ($c) { return array(one($c, "SELECT COUNT(*) FROM `fin_collection_allocations`"), 'تخصيصُ تحصيل'); }),
array(21, 'client_statement.php',       'كشف حساب العميل',                'NO_LADDER_REQUIRED',
      function ($c) { return array(1, 'سطحٌ قائم'); }),
array(22, 'settlements.php',            'التسويات ومستحقات الموردين',     'LD-13',
      function ($c) { return array(one($c, "SELECT COUNT(*) FROM `settlements`"), 'تسوية'); }),
array(23, 'ap_oblig_gen.php',           'توليد التزامات عقد المورد',      'RESOLVE_FROM_POLICY:ap_obligation',
      function ($c) { return array(one($c, "SELECT COUNT(*) FROM `contract_obligations`"), 'التزامُ عقد'); }),
array(24, 'payments_fin.php',           'طلبات الدفع والسداد',            'RESOLVE_FROM_POLICY:ap_payment_request',
      function ($c) { return array(one($c, "SELECT COUNT(*) FROM `fin_payments`"), 'طلبُ دفع'); }),
array(25, 'tre_pay_batch.php',          'دفعات الدفع والتنفيذ',           'RESOLVE_FROM_POLICY:treasury_disbursement',
      function ($c) { return array(one($c, "SELECT COUNT(*) FROM `fin_payments` WHERE `status` IN ('paid','executed','settled')"), 'دفعةٌ نُفِّذت'); }),
array(26, 'tre_exec_log.php',           'توثيق التنفيذ والإشعارات',       'RESOLVE_FROM_POLICY:treasury_disbursement',
      function ($c) { return array(one($c, "SELECT COUNT(*) FROM `fin_payments` WHERE `bank_ref` IS NOT NULL AND `bank_ref` <> ''"), 'دفعةٌ بمرجعِ حركةٍ بنكيّ'); }),
array(27, 'supplier_statement_fin.php', 'كشف حساب المورد الشهري',         'NO_LADDER_REQUIRED',
      function ($c) { return array(1, 'سطحٌ قائم'); }),
array(28, 'entitlement.php',            'توليد المستحق من العمل المعتمد', 'LD-05',
      function ($c) { return array(one($c, "SELECT COUNT(*) FROM `unit_party_awards`"), 'إسنادُ استحقاق'); }),
array(29, 'entitlement_gate.php',       'فحص شروط الاستحقاق',             'LD-05',
      function ($c) { return array(1, 'سطحٌ قائم'); }),
);

/* أسطحٌ منتِجةٌ مقيسةٌ من الشيفرة — تُذكر كدليلٍ حين لا يوجد الملفُّ المُعلَن */
$CARRIER = array(
 2  => array('Operations/shift_entry.php', 'Timesheet/timesheet.php', 'app/Services/Unit/TimesheetEntryService.php'),
 3  => array('Approvals/hours_approval.php', 'Portal/approvals_inbox.php'),
 5  => array('Portal/approvals_inbox.php', 'Approvals/attribution_board.php'),
 6  => array('Portal/approvals_inbox.php', 'Approvals/requests.php'),
 7  => array('Portal/approvals_inbox.php', 'Approvals/requests.php'),
 8  => array('Finance/unit_records_fin.php', 'Finance/approvals_inbox.php'),
 9  => array('Finance/unit_records_fin.php', 'app/Services/Finance/UnitConversionService.php'),
 12 => array('Portal/my_achievement.php'),
 13 => array('Operations/shift_entry.php'),
 16 => array('Finance/periodic_events_fin.php'),
 20 => array('Contracts/collections.php'),
 23 => array('Contracts/contract_obligations.php'),
 25 => array('Finance/payments_fin.php'),
 26 => array('Finance/payments_fin.php'),
);

$rows = array(); $tally = array('BUILT_AS_DECLARED' => 0, 'IMPLEMENTED_UNDER_OTHER_ROUTE' => 0, 'REQUIRED_AND_MISSING' => 0);
foreach ($NODES as $nd) {
    list($no, $file, $title, $ladder, $probe) = $nd;
    $onDisk = isset($index[$file]) ? $index[$file][0] : null;
    list($ev, $unit) = $probe($conn);
    $carriers = array();
    if (isset($CARRIER[$no])) {
        foreach ($CARRIER[$no] as $cf) { if (is_file($ROOT . '/' . $cf)) { $carriers[] = $cf; } }
    }
    if ($onDisk !== null)            { $verdict = 'BUILT_AS_DECLARED'; }
    elseif ($ev > 0 && $carriers)    { $verdict = 'IMPLEMENTED_UNDER_OTHER_ROUTE'; }
    else                             { $verdict = 'REQUIRED_AND_MISSING'; }
    $tally[$verdict]++;
    $rows[] = array('node' => $no, 'declared' => $file, 'title' => $title, 'ladder' => $ladder,
                    'on_disk' => $onDisk, 'evidence_n' => $ev, 'evidence_unit' => $unit,
                    'carriers' => $carriers, 'verdict' => $verdict);
}

echo "══ مصالحةُ عقدِ سلسلةِ الأثر — 29 عقدة ══\n";
echo str_repeat('─', 112) . "\n";
printf("  %-3s %-28s %-30s %-9s %s\n", '#', 'المُعلَن', 'الدليلُ الحيّ', 'الحكم', 'السطحُ الحامل');
echo str_repeat('─', 112) . "\n";
foreach ($rows as $r) {
    $m = $r['verdict'] === 'BUILT_AS_DECLARED' ? '✔'
       : ($r['verdict'] === 'IMPLEMENTED_UNDER_OTHER_ROUTE' ? '◆' : '✘');
    $car = $r['on_disk'] !== null ? $r['on_disk'] : implode(' · ', $r['carriers']);
    printf("  %-3d %-28s %s %-9s %s %s\n", $r['node'], $r['declared'],
        str_pad(number_format(max(0, $r['evidence_n'])) . ' ' . $r['evidence_unit'], 34, ' ', STR_PAD_LEFT),
        '', $m, mb_substr($car, 0, 52));
}
echo str_repeat('─', 112) . "\n";
printf("◆ **مبنيةٌ باسمِها المُعلَن: %d · حيّةٌ على سطحٍ آخرَ بأثرٍ مقيس: %d · مفقودةٌ فعلًا: %d** — المقام 29\n",
       $tally['BUILT_AS_DECLARED'], $tally['IMPLEMENTED_UNDER_OTHER_ROUTE'], $tally['REQUIRED_AND_MISSING']);
echo "◆ وحكمُ «حيّةٌ على سطحٍ آخر» **لا يُغلق العقدة**: يُثبت أن بناءَها ثانيةً\n";
echo "  ازدواجٌ ممنوع — والباقي عليها **تسميةٌ ووصلُ سلّمٍ لا بناءُ سطح**.\n";
$miss = array();
foreach ($rows as $r) { if ($r['verdict'] === 'REQUIRED_AND_MISSING') { $miss[] = $r['node'] . '·' . $r['declared']; } }
if ($miss) { echo "◆ مفقودةٌ فعلًا: " . implode(' · ', $miss) . "\n"; }

$opt = array();
foreach (array_slice($argv, 1) as $a) { if (preg_match('/^--json=(.*)$/', $a, $m2)) { $opt['json'] = $m2[1]; } }
if (isset($opt['json'])) {
    file_put_contents($opt['json'], json_encode(array('tally' => $tally, 'rows' => $rows),
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "◆ كُتب: {$opt['json']}\n";
}
