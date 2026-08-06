<?php
/**
 * tools/m00_followup_migrate.php — ترحيل صفوف M-00 من المخزن البيني · v1
 * ═══════════════════════════════════════════════════════════════════════════
 * مهمة اللحاق (CMP03_FOLLOWUP): صفوف الشاشات الخمس في cmp03_screen_rows
 * تُرحَّل إلى جداولها الأصلية المفصلة (هجرة 2026_11_14) ثم يُحرَّر المخزن منها:
 *   ceo_approvals.php   → exec_approvals
 *   ceo_contracts.php   → exec_contract_signings
 *   project_charter.php → exec_project_charters
 *   ceo_risk.php        → exec_decisions
 *   ceo_board.php       → exec_board_snapshots
 * ويبذر سقوف الإدارات التسعة في exec_dept_caps (BR-CEO-05) بقيم اختبارية
 * قابلة للاستبدال بقرار المالك (is_seed=1 · authority_ref قرار الموازنة).
 *
 * php tools/m00_followup_migrate.php            → تجريب (عدّ فقط)
 * php tools/m00_followup_migrate.php --apply    → ترحيل + بذر السقوف + تحرير
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$APPLY = in_array('--apply', $argv, true);
$o = function ($s) { fwrite(STDOUT, $s . "\n"); };

/** رقم من نص منسّق «446,000» → 446000.00 — وغير الرقمي NULL */
function m00_num($v) {
    $v = str_replace(array(',', ' '), '', trim((string) $v));
    return ($v !== '' && is_numeric($v)) ? $v : null;
}
/** تاريخ صالح YYYY-MM-DD وإلا NULL */
function m00_date($v) {
    $v = trim((string) $v);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
}
/** نص أو NULL — و«—» فراغ معلن */
function m00_txt($v) {
    $v = trim((string) $v);
    return ($v === '' || $v === '—') ? null : $v;
}

/* خرائط الحمولة (مفاتيح $FIELDS الحرفية) → أعمدة الجدول المفصل */
$MAPS = array(
    'ceo_approvals.php' => array('table' => 'exec_approvals', 'cols' => array(
        'request_no'      => array('رقم الطلب', 'txt'),
        'received_date'   => array('تاريخ الورود', 'date'),
        'doc_type'        => array('نوع المستند', 'txt'),
        'document'        => array('المستند', 'txt'),
        'requesting_dept' => array('الإدارة الطالبة', 'txt'),
        'raise_reason'    => array('سبب الرفع للأعلى', 'txt'),
        'amount'          => array('القيمة', 'num'),
        'currency'        => array('العملة', 'txt'),
        'dept_cap'        => array('سقف الإدارة', 'num'),
        'overage'         => array('التجاوز', 'num'),
        'prior_approvers' => array('المعتمِدون قبلي', 'txt'),
        'deadline'        => array('المهلة', 'txt'),
        'decision'        => array('قراري', 'txt'),
        'decision_reason' => array('سبب القرار', 'txt'),
        'decision_date'   => array('تاريخ القرار', 'date'),
        'approver_name'   => array('المعتمِد — الاسم والصفة', 'txt'),
        'authority_ref'   => array('مرجع التفويض', 'txt'),
    )),
    'ceo_contracts.php' => array('table' => 'exec_contract_signings', 'cols' => array(
        'contract_no'           => array('رقم العقد', 'txt'),
        'contract_kind'         => array('نوع العقد', 'txt'),
        'other_party'           => array('الطرف الآخر', 'txt'),
        'party_type'            => array('نوع الطرف', 'txt'),
        'amount'                => array('القيمة', 'num'),
        'currency'              => array('العملة', 'txt'),
        'duration'              => array('المدة', 'txt'),
        'work_model'            => array('نموذج العمل', 'txt'),
        'contract_unit'         => array('وحدة التعاقد', 'txt'),
        'units_count'           => array('عدد الوحدات', 'txt'),
        'bond_required'         => array('الكفالة المطلوبة', 'txt'),
        'bond_value'            => array('قيمة الكفالة', 'txt'),
        'legal_review'          => array('مراجعة قانونية', 'txt'),
        'financial_review'      => array('مراجعة مالية', 'txt'),
        'signed_by_us'          => array('الموقّع عنّا', 'txt'),
        'other_signer_capacity' => array('صفته', 'txt'), // مفتاح الحمولة المزدوج كان يحمل صفة الطرف الآخر
        'authority_ref'         => array('مرجع سلطته', 'txt'),
        'signing_date'          => array('تاريخ التوقيع', 'date'),
        'other_signer'          => array('الموقّع عن الطرف الآخر', 'txt'),
        'other_authority_doc'   => array('مستند تخويله', 'txt'),
        'registry_recorded'     => array('سُجّل في السجل الموحَّد؟', 'txt'),
    )),
    'project_charter.php' => array('table' => 'exec_project_charters', 'cols' => array(
        'decision_no'      => array('رقم القرار', 'txt'),
        'project_name'     => array('اسم المشروع', 'txt'),
        'client'           => array('العميل', 'txt'),
        'contract_ref'     => array('العقد', 'txt'),
        'sites_text'       => array('الموقع أو المواقع', 'txt'),
        'work_model'       => array('نموذج العمل', 'txt'),
        'work_unit'        => array('وحدة العمل', 'txt'),
        'contracted_qty'   => array('الكمية المتعاقدة', 'txt'),
        'planned_start'    => array('تاريخ البدء المخطط', 'date'),
        'duration'         => array('المدة', 'txt'),
        'equipment_needed' => array('المعدات المطلوبة', 'txt'),
        'operators_needed' => array('المشغّلون المطلوبون', 'txt'),
        'equipment_source' => array('مصدر المعدات', 'txt'),
        'financing_need'   => array('احتياج التمويل', 'txt'),
        'cost_center'      => array('مركز التكلفة', 'txt'),
        'site_manager'     => array('مدير الموقع المعيَّن', 'txt'),
        'manager_powers'   => array('صلاحياته', 'txt'),
        'cert_operations'  => array('إفادة التشغيل', 'txt'),
        'cert_sales'       => array('إفادة المبيعات', 'txt'),
        'cert_workforce'   => array('إفادة القوى', 'txt'),
        'cert_finance'     => array('إفادة المالية', 'txt'),
        'cert_fleet'       => array('إفادة الأسطول', 'txt'),
        'cert_financing'   => array('إفادة التمويل', 'txt'),
        'approver_name'    => array('المعتمِد — الاسم والصفة', 'txt'),
        'approval_date'    => array('تاريخ الاعتماد', 'txt'),
    )),
    'ceo_risk.php' => array('table' => 'exec_decisions', 'cols' => array(
        'decision_no'   => array('رقم القرار', 'txt'),
        'raised_date'   => array('تاريخ الرفع', 'date'),
        'raising_dept'  => array('الجهة الرافعة', 'txt'),
        'issue_type'    => array('نوع القضية', 'txt'),
        'issue_desc'    => array('وصف القضية', 'txt'),
        'est_impact'    => array('الأثر المقدَّر', 'txt'),
        'currency'      => array('العملة', 'txt'),
        'options_text'  => array('الخيارات المطروحة', 'txt'),
        'chosen_option' => array('الخيار المختار', 'txt'),
        'choice_reason' => array('مبرر الاختيار', 'txt'),
        'assigned_dept' => array('الجهة المكلَّفة بالتنفيذ', 'txt'),
        'exec_deadline' => array('مهلة التنفيذ', 'txt'),
        'followup_date' => array('تاريخ المتابعة', 'date'),
        'approver_name' => array('المعتمِد — الاسم والصفة', 'txt'),
        'decision_date' => array('تاريخ القرار', 'date'),
    )),
    'ceo_board.php' => array('table' => 'exec_board_snapshots', 'cols' => array(
        'period'                => array('الفترة', 'txt'),
        'active_contracts'      => array('العقود النافذة', 'txt'),
        'portfolio_value'       => array('قيمة المحفظة', 'txt'),
        'recognized_revenue'    => array('الإيراد المعترف', 'txt'),
        'collection'            => array('التحصيل', 'txt'),
        'overdue_receivables'   => array('الذمم المتأخرة', 'txt'),
        'expected_cashflow'     => array('التدفق المتوقع', 'txt'),
        'financing_commitments' => array('التزامات التمويل', 'txt'),
        'working_equipment'     => array('المعدات العاملة', 'txt'),
        'readiness_pct'         => array('نسبة الجاهزية', 'txt'),
        'approved_units'        => array('الوحدات المعتمدة', 'txt'),
        'margin_pct'            => array('الهامش', 'txt'),
        'open_risks'            => array('المخاطر المفتوحة', 'txt'),
        'pending_approvals'     => array('الاعتمادات المعلَّقة', 'txt'),
        'last_updated'          => array('آخر تحديث', 'txt'),
    )),
);

/* سقوف الإدارات التسعة — قيم اختبارية بعملتين (تُستبدل بقرار المالك) */
$CAPS = array(
    // dept                     SDG          USD
    'المالية والخزينة'   => array(50000000, 20000),
    'المبيعات والعقود'   => array(40000000, 15000),
    'إدارة الموردين'     => array(30000000, 12000),
    'إدارة التشغيل'      => array(35000000, 15000),
    'الموارد البشرية'    => array(20000000,  8000),
    'إدارة الأسطول'      => array(30000000, 12000),
    'التمويل والملكية'   => array(25000000, 10000),
    'المشتريات'          => array(25000000, 10000),
    'الصيانة'            => array(20000000,  8000),
);
$COMPANY = 4;

/* ── العدّ ───────────────────────────────────────────────────────────────── */
$counts = array();
foreach ($MAPS as $canon => $m) {
    $r = $conn->query("SELECT COUNT(*) c FROM cmp03_screen_rows WHERE canonical_file='"
        . $conn->real_escape_string($canon) . "'");
    $counts[$canon] = $r ? intval($r->fetch_assoc()['c']) : 0;
    $o(sprintf('  %-22s %d صفًّا بينيًّا → %s', $canon, $counts[$canon], $m['table']));
}
$o('  سقوف: ' . count($CAPS) . ' إدارات × عملتين = ' . (count($CAPS) * 2) . ' صفًّا');
if (!$APPLY) { $o('تجريب — أعد بـ --apply.'); exit(0); }

/* ── الترحيل ─────────────────────────────────────────────────────────────── */
$totalMoved = 0;
foreach ($MAPS as $canon => $m) {
    $rows = array();
    $rs = $conn->query("SELECT * FROM cmp03_screen_rows WHERE canonical_file='"
        . $conn->real_escape_string($canon) . "' ORDER BY id");
    while ($x = $rs->fetch_assoc()) { $rows[] = $x; }
    if (!$rows) { continue; }

    $cols = array_keys($m['cols']);
    $all  = array_merge(array('company_id'), $cols,
        array('status', 'is_seed', 'created_by', 'created_by_name', 'created_at'));
    $ph   = implode(',', array_fill(0, count($all), '?'));
    $sql  = "INSERT INTO `{$m['table']}` (`" . implode('`,`', $all) . "`) VALUES ({$ph})";
    $st   = $conn->prepare($sql);
    if (!$st) { $o("  ✗ {$m['table']}: " . $conn->error); continue; }

    $moved = 0;
    foreach ($rows as $row) {
        $p = json_decode((string) $row['payload'], true) ?: array();
        $vals = array(intval($row['company_id']));
        foreach ($m['cols'] as $col => $def) {
            list($label, $kind) = $def;
            $raw = isset($p[$label]) ? $p[$label] : null;
            $vals[] = ($kind === 'num') ? m00_num($raw) : (($kind === 'date') ? m00_date($raw) : m00_txt($raw));
        }
        $vals[] = (string) $row['status'];
        $vals[] = intval($row['is_seed']);
        $vals[] = $row['created_by'] !== null ? intval($row['created_by']) : null;
        $vals[] = $row['created_by_name'];
        $vals[] = $row['created_at'];
        $types = str_repeat('s', count($vals));
        $st->bind_param($types, ...$vals);
        if ($st->execute()) { $moved++; }
        else { $o('  ✗ صف ' . $row['id'] . ': ' . $st->error); }
    }
    $st->close();

    // تصحيح سجل التوقيع: مفتاح «صفته» المزدوج كان يحمل صفة الطرف الآخر —
    // صفة موقّعنا تُستنبط من هويته (المدير التنفيذي بالسلطة الأصلية)
    if ($m['table'] === 'exec_contract_signings') {
        $conn->query("UPDATE exec_contract_signings
            SET signer_capacity = 'المدير التنفيذي'
            WHERE signed_by_us IS NOT NULL AND signer_capacity IS NULL");
    }

    if ($moved === count($rows)) {
        $conn->query("DELETE FROM cmp03_screen_rows WHERE canonical_file='"
            . $conn->real_escape_string($canon) . "'");
        $o(sprintf('  ✔ %-22s رُحّل %d وحُرّر المخزن البيني', $canon, $moved));
    } else {
        $o(sprintf('  ⚠ %-22s رُحّل %d من %d — المخزن لم يُحرَّر', $canon, $moved, count($rows)));
    }
    $totalMoved += $moved;
}

/* ── بذر السقوف ──────────────────────────────────────────────────────────── */
$conn->query("DELETE FROM exec_dept_caps WHERE is_seed=1 AND company_id={$COMPANY}");
$st = $conn->prepare("INSERT INTO exec_dept_caps
    (company_id, dept_name, cap_amount, currency, effective_from, authority_ref, note, is_seed, created_by_name)
    VALUES (?, ?, ?, ?, '2026-01-01', 'قرار الموازنة العامة 2026', 'قيمة اختبارية — تستبدل بقرار المالك', 1, 'باذر M-00')");
$capsOk = 0;
foreach ($CAPS as $dept => $pair) {
    foreach (array(array($pair[0], 'SDG'), array($pair[1], 'USD')) as $c) {
        $st->bind_param('isds', $COMPANY, $dept, $c[0], $c[1]);
        if ($st->execute()) { $capsOk++; }
    }
}
$st->close();

$o("رُحّل {$totalMoved} صفًّا · بُذر {$capsOk} سقفًا");
foreach ($MAPS as $canon => $m) {
    $r = $conn->query("SELECT COUNT(*) c FROM `{$m['table']}`");
    $o(sprintf('  %-24s %d صفًّا', $m['table'], intval($r->fetch_assoc()['c'])));
}
$left = $conn->query("SELECT COUNT(*) c FROM cmp03_screen_rows WHERE canonical_file IN ('"
    . implode("','", array_keys($MAPS)) . "')")->fetch_assoc()['c'];
$o("المتبقي في المخزن البيني لشاشات M-00: {$left}");
$o('تم ✅');
