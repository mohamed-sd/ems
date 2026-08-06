<?php
/**
 * tools/h13_h15_sales_seed.php — إغلاقُ ح-13 وح-14 وح-15 · v1
 * ═══════════════════════════════════════════════════════════════════════════
 * ح-13 · وثيقةُ القوائم (NAV-D2 صفّا 145 و147) تقول لـ«أنشطة العملاء»
 *        و«المناقصات»: ★قائمة / ★إبقاء في باب DAILY. وهما اليوم active=0
 *        في باب REC — تعطيلٌ يخالف الوثيقة. البديلُ المزعوم مجموعةٌ اسمُها
 *        «الفرص والزيارات والمناقصات» — وعدُ دمجٍ لم يقع (الشاشتان حيّتان
 *        ببياناتهما ولوحةُ الدور تحيل إليهما). ⇒ تُفعَّلان في مجموعتها نفسِها.
 *
 * ح-14 · «بلاغاتُ إدارتي» فارغةٌ لأن لا مسارَ بلاغٍ موجَّهٌ لوحدة المبيعات (2)؛
 *        المساراتُ القائمة كلُّها للوحدات 8·9·10·11·12. ⇒ بذرُ مساراتٍ للوحدة 2.
 *
 * ح-15 · صفُّ موازنةِ المبيعات قائمٌ (id=209) لكن fiscal_year=17 وهي بيانةُ
 *        بذرٍ تالفة، والشاشةُ ترشّح fiscal_year = السنةُ الجارية. ⇒ موازنةُ
 *        2026 كاملةٌ ببنودها.
 *
 * php tools/h13_h15_sales_seed.php            → تجريب
 * php tools/h13_h15_sales_seed.php --apply    → تنفيذ
 * php tools/h13_h15_sales_seed.php --revert   → تراجع
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

$APPLY  = in_array('--apply', $argv, true);
$REVERT = in_array('--revert', $argv, true);
$o = function ($s) { fwrite(STDOUT, $s . "\n"); };

$CO        = 4;         // ايكوبيشن
$SALES_UNIT = 2;        // وحدةُ المبيعات (ems_dept_unit_of_role(12))
$NAV_IDS   = array(145, 147);
$NAV_GROUP = 3413;      // مجموعةُ «الفرص والزيارات والمناقصات»
$YEAR      = (int) date('Y');
$BUDGET_NO = 'BUD-SALES-' . $YEAR;
$WS_TYPE   = 'sales_review';

$o('══ بذرُ المبيعات — ح-13 · ح-14 · ح-15 ══');

// ───────────────────────────── التراجع ─────────────────────────────────────
if ($REVERT) {
    mysqli_query($conn, "UPDATE nav_items SET active=0, door='REC' WHERE id IN (" . implode(',', $NAV_IDS) . ")");
    $o('  ح-13 · أُعيد التعطيل: ' . mysqli_affected_rows($conn));
    mysqli_query($conn, "DELETE ws FROM ticket_workstreams ws JOIN tickets t ON t.id=ws.tk_id
                          WHERE t.company_id=$CO AND ws.org_unit_id=$SALES_UNIT AND ws.workstream_type='$WS_TYPE'");
    $o('  ح-14 · حُذفت مسارات: ' . mysqli_affected_rows($conn));
    $b = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM fin_budgets WHERE company_id=$CO AND budget_no='$BUDGET_NO'"));
    if ($b) {
        mysqli_query($conn, "DELETE FROM fin_budget_lines WHERE budget_id=" . (int) $b['id']);
        mysqli_query($conn, "DELETE FROM fin_budgets WHERE id=" . (int) $b['id']);
        $o('  ح-15 · حُذفت الموازنة #' . $b['id']);
    } else { $o('  ح-15 · لا موازنةَ للحذف'); }
    exit(0);
}

// ───────────────────────────── ح-13 ────────────────────────────────────────
$o('');
$o('── ح-13 · تفعيلُ المناقصات والأنشطة وفق الوثيقة');
$q = mysqli_query($conn, "SELECT id, label_ar, route, door, group_id, active FROM nav_items WHERE id IN (" . implode(',', $NAV_IDS) . ")");
$need13 = 0;
while ($r = mysqli_fetch_assoc($q)) {
    $ok = ((int) $r['active'] === 1 && $r['door'] === 'DAILY' && (int) $r['group_id'] === $NAV_GROUP);
    if (!$ok) { $need13++; }
    $o(sprintf('   #%-5s %-22s active=%s door=%-6s group=%-6s %s',
        $r['id'], mb_substr($r['label_ar'], 0, 21), $r['active'], $r['door'], $r['group_id'], $ok ? '✓' : '← يُعدَّل'));
}

// ───────────────────────────── ح-14 ────────────────────────────────────────
$o('');
$o('── ح-14 · مساراتُ بلاغاتٍ لوحدة المبيعات (' . $SALES_UNIT . ')');
$have14 = (int) reset(mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) c FROM ticket_workstreams ws JOIN tickets t ON t.id=ws.tk_id
      WHERE t.company_id=$CO AND ws.org_unit_id=$SALES_UNIT")));
$o('   مساراتٌ قائمةٌ للوحدة: ' . $have14);
$cand = array();
$q = mysqli_query($conn, "SELECT id, ticket_no, complaint FROM tickets WHERE company_id=$CO ORDER BY id DESC LIMIT 6");
while ($r = mysqli_fetch_assoc($q)) { $cand[] = $r; }
$o('   بلاغاتٌ مرشَّحةٌ للربط: ' . count($cand));

// ───────────────────────────── ح-15 ────────────────────────────────────────
$o('');
$o('── ح-15 · موازنةُ المبيعات لسنة ' . $YEAR);
$have15 = (int) reset(mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) c FROM fin_budgets WHERE company_id=$CO AND dept_module='sales' AND fiscal_year=$YEAR AND is_deleted=0")));
$o('   موازناتُ مبيعاتٍ للسنة الجارية: ' . $have15);

if (!$APPLY) { $o(''); $o('  (تجريبٌ — أعِد التشغيل بـ --apply)'); exit(0); }

// ═════════════════════════════ التنفيذ ═════════════════════════════════════
$o('');
$o('══ التنفيذ ══');

// ح-13
mysqli_query($conn, "UPDATE nav_items SET active=1, door='DAILY', group_id=$NAV_GROUP, updated_at=NOW()
                      WHERE id IN (" . implode(',', $NAV_IDS) . ")");
$o('  ح-13 · فُعِّلت ونُقلت: ' . mysqli_affected_rows($conn) . ' صفًّا');

// ح-14 — مسارٌ لكل بلاغٍ مرشَّح، بحالاتٍ متنوعةٍ لتظهر اللوحةُ بأرقامٍ ذاتِ معنى
$states = array(
    array('new', null), array('received', null), array('in_progress', null),
    array('done_pending', null), array('closed', '-3 days'), array('closed', '-10 days'),
);
$made = 0; $i = 0;
foreach ($cand as $t) {
    if ($i >= count($states)) { break; }
    list($st, $closedAgo) = $states[$i];
    $dup = mysqli_fetch_assoc(mysqli_query($conn, "SELECT ws_id FROM ticket_workstreams
        WHERE tk_id=" . (int) $t['id'] . " AND org_unit_id=$SALES_UNIT AND workstream_type='$WS_TYPE'"));
    if ($dup) { $i++; continue; }
    $closedAt  = $closedAgo ? "'" . date('Y-m-d H:i:s', strtotime($closedAgo)) . "'" : 'NULL';
    $respDue   = "'" . date('Y-m-d H:i:s', strtotime('+2 days')) . "'";
    $resolDue  = "'" . date('Y-m-d H:i:s', strtotime('+5 days')) . "'";
    $recvAt    = ($st === 'new') ? 'NULL' : "'" . date('Y-m-d H:i:s', strtotime('-1 day')) . "'";
    $act       = ($st === 'new') ? 'pending' : 'opened';
    mysqli_query($conn, "INSERT INTO ticket_workstreams
        (tk_id, workstream_type, seq_no, org_unit_id, mandatory, state, activation_state,
         response_due_at, resolve_due_at, received_at, closed_at, reopen_count, created_at)
        VALUES (" . (int) $t['id'] . ", '$WS_TYPE', 1, $SALES_UNIT, 1, '$st', '$act',
         $respDue, $resolDue, $recvAt, $closedAt, 0, NOW())");
    if (mysqli_affected_rows($conn) > 0) { $made++; }
    $i++;
}
$o('  ح-14 · مساراتٌ أُنشئت: ' . $made);

// ح-15 — موازنةٌ سنويةٌ نشطةٌ ببنودها
$b = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM fin_budgets WHERE company_id=$CO AND budget_no='$BUDGET_NO'"));
if (!$b) {
    mysqli_query($conn, "INSERT INTO fin_budgets
        (company_id, budget_no, dept_module, period_type, fiscal_year, period_no,
         total_revenue, total_expense, state, submitted_by, submitted_at, approved_by, approved_at,
         note, is_deleted, created_by, created_at, updated_at)
        VALUES ($CO, '$BUDGET_NO', 'sales', 'annual', $YEAR, 1,
         4800000.00, 1350000.00, 'active', 4, NOW(), 4, NOW(),
         'موازنةُ إدارة المبيعات — بيانةٌ تجريبية', 0, 4, NOW(), NOW())");
    $bid = (int) mysqli_insert_id($conn);
    $o('  ح-15 · أُنشئت الموازنة #' . $bid . ' (' . $BUDGET_NO . ')');

    $lines = array(
        array('revenue', 'operational_needs', 3600000, 3180000, 'تأخُّرُ إقفال عقدين', 'تسريعُ التفاوض'),
        array('revenue', 'other',              1200000, 1410000, 'مناقصةٌ غيرُ مخطَّطة', 'لا إجراء — تجاوزٌ إيجابي'),
        array('expense', 'transport',           420000,  398000, '', ''),
        array('expense', 'catering',            180000,  205000, 'زياراتٌ ميدانيةٌ أكثرُ من المخطَّط', 'ضبطُ جدول الزيارات'),
        array('expense', 'operational_needs',   750000,  690000, '', ''),
    );
    foreach ($lines as $l) {
        list($kind, $cat, $plan, $act, $cause, $corr) = $l;
        $var  = $act - $plan;
        $pct  = $plan > 0 ? round(100.0 * $var / $plan, 2) : 0;
        $vst  = ($cause === '') ? 'closed' : 'open';
        $st = $conn->prepare("INSERT INTO fin_budget_lines
            (company_id, budget_id, line_kind, category, planned_amount, actual_amount,
             variance, variance_pct, cause, corrective_action, responsible_id, var_state, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
        $rid = 13; // حسابُ المبيعات
        $st->bind_param('iissddddssis', $CO, $bid, $kind, $cat, $plan, $act, $var, $pct, $cause, $corr, $rid, $vst);
        $st->execute(); $st->close();
    }
    $o('  ح-15 · بنودٌ أُضيفت: ' . count($lines));
} else { $o('  ح-15 · الموازنة قائمةٌ سلفًا #' . $b['id']); }

// ═════════════════════════════ التحقق ══════════════════════════════════════
$o('');
$o('══ التحقق ══');
$q = mysqli_query($conn, "SELECT COUNT(*) c FROM nav_items WHERE id IN (" . implode(',', $NAV_IDS) . ") AND active=1 AND door='DAILY'");
$o('  ح-13 · صفوفٌ مفعَّلةٌ في DAILY : ' . (int) reset(mysqli_fetch_assoc($q)) . '/2');
$q = mysqli_query($conn, "SELECT COUNT(*) c FROM ticket_workstreams ws JOIN tickets t ON t.id=ws.tk_id
                           WHERE t.company_id=$CO AND ws.org_unit_id=$SALES_UNIT");
$o('  ح-14 · مساراتُ وحدة المبيعات : ' . (int) reset(mysqli_fetch_assoc($q)));
$q = mysqli_query($conn, "SELECT COUNT(*) c FROM fin_budgets WHERE company_id=$CO AND dept_module='sales' AND fiscal_year=$YEAR AND is_deleted=0");
$o('  ح-15 · موازناتُ ' . $YEAR . '            : ' . (int) reset(mysqli_fetch_assoc($q)));
$q = mysqli_query($conn, "SELECT COUNT(*) c FROM fin_budget_lines l JOIN fin_budgets b ON b.id=l.budget_id
                           WHERE b.company_id=$CO AND b.budget_no='$BUDGET_NO'");
$o('  ح-15 · بنودُ الموازنة        : ' . (int) reset(mysqli_fetch_assoc($q)));
